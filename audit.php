<?php
/**
 * GEO Audit Tool - Backend PHP
 * Version améliorée avec contournement renforcé des protections
 * + Support services de scraping tiers (ScrapingBee, ScraperAPI, Browserless)
 */

define('GEO_AUDIT_VERSION', '1.2.0');
define('GEO_AUDIT_USER_AGENT', 'GEO-Audit-Bot/' . GEO_AUDIT_VERSION . ' (+https://audit.ticoet.me; contact@ticoet.fr)');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/audit_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$SCRAPING_CONFIG = loadScrapingConfig();

$input = json_decode(file_get_contents('php://input'), true);
$mode = $input['mode'] ?? 'url';
$pageType = $input['pageType'] ?? 'article';

if ($mode === 'html') {
    $html = $input['html'] ?? '';
    $url = $input['url'] ?? 'HTML copié-collé';
    
    if (empty($html) || strlen($html) < 100) {
        http_response_code(400);
        echo json_encode(['error' => 'HTML invalide ou trop court']);
        exit;
    }
    
} else {
    $url = filter_var($input['url'] ?? '', FILTER_VALIDATE_URL);
    $useProxy = $input['useProxy'] ?? false;
    $useScrapingService = $input['useScrapingService'] ?? false;
    $identifyAsBot = $input['identifyAsBot'] ?? false;
    
    if (!$url) {
        http_response_code(400);
        echo json_encode(['error' => 'URL invalide ou manquante']);
        exit;
    }
    
    if (!function_exists('curl_init')) {
        http_response_code(500);
        echo json_encode(['error' => 'Extension CURL non disponible sur le serveur']);
        exit;
    }
    
    try {
        $html = fetchHTML($url, $useProxy, $useScrapingService, $identifyAsBot);
        if (!$html) {
            $hasScrapingService = !empty($SCRAPING_CONFIG['service']);
            http_response_code(500);
            echo json_encode([
                'error' => 'Impossible de récupérer la page',
                'details' => 'La page est protégée par Cloudflare ou un système anti-bot. ' .
                            'Toutes les méthodes de contournement ont échoué. ' .
                            ($hasScrapingService 
                                ? 'Le service de scraping configuré n\'a pas pu contourner la protection.' 
                                : 'Conseil : configurez un service de scraping (ScrapingBee, ScraperAPI) dans scraping-config.json pour de meilleurs résultats.') . ' ' .
                            'Sinon, utilisez le mode "Analyser du HTML" en copiant le code source depuis votre navigateur.',
                'suggestion' => 'html_mode',
                'scraping_configured' => $hasScrapingService
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Erreur fetch: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => 'Erreur lors de la récupération',
            'details' => $e->getMessage()
        ]);
        exit;
    }
}

try {
    $audit = analyzeHTML($html, $url, $pageType);
    echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erreur audit GEO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur lors de l\'analyse',
        'details' => $e->getMessage()
    ]);
}

/**
 * Charge la configuration des services de scraping
 */
function loadScrapingConfig() {
    $configFile = __DIR__ . '/scraping-config.json';
    
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if ($config) {
            return $config;
        }
    }
    
    return [
        'service' => '',
        'api_key' => '',
        'options' => []
    ];
}

/**
 * Récupère le HTML d'une URL avec stratégies multiples
 */
function fetchHTML($url, $useProxy = false, $useScrapingService = false, $identifyAsBot = false) {
    global $SCRAPING_CONFIG;
    
    error_log("fetchHTML: Début - URL: $url, useProxy: " . ($useProxy ? 'true' : 'false') . ", useScrapingService: " . ($useScrapingService ? 'true' : 'false') . ", identifyAsBot: " . ($identifyAsBot ? 'true' : 'false'));
    error_log("fetchHTML: Config service: " . ($SCRAPING_CONFIG['service'] ?? 'non défini') . ", API key présente: " . (!empty($SCRAPING_CONFIG['api_key']) ? 'oui' : 'non'));
    
    // Si identification comme bot demandée, utiliser directement le User-Agent dédié
    if ($identifyAsBot) {
        $html = fetchHTMLAsBot($url);
        if ($html && isValidHTML($html)) {
            error_log("Succès avec User-Agent bot identifiable: " . GEO_AUDIT_USER_AGENT);
            return $html;
        }
        error_log("fetchHTML: Échec avec User-Agent bot, tentative avec stratégies standard...");
    }
    
    // Stratégie 0: Service de scraping tiers (si demandé et configuré)
    if ($useScrapingService && !empty($SCRAPING_CONFIG['service']) && !empty($SCRAPING_CONFIG['api_key'])) {
        error_log("fetchHTML: Tentative avec service de scraping: " . $SCRAPING_CONFIG['service']);
        $html = fetchWithScrapingService($url, $SCRAPING_CONFIG);
        if ($html && isValidHTML($html)) {
            error_log("Succès avec service de scraping: " . $SCRAPING_CONFIG['service']);
            return $html;
        }
        error_log("fetchHTML: Échec du service de scraping, isValidHTML: " . ($html ? (isValidHTML($html) ? 'true' : 'false') : 'null'));
    }
    
    // Stratégie 1: Mode compatible avancé (si demandé)
    if ($useProxy) {
        $html = fetchWithAdvancedBypass($url);
        if ($html && isValidHTML($html)) return $html;
    }
    
    // Stratégie 2: Headers réalistes (Chrome moderne)
    $html = fetchHTMLWithRealHeaders($url);
    if ($html && isValidHTML($html)) return $html;
    
    // Stratégie 3: cURL basique avec User-Agent bot
    $html = fetchHTMLBasic($url);
    if ($html && isValidHTML($html)) return $html;
    
    // Stratégie 4: file_get_contents avec contexte (dernier recours)
    $html = fetchWithFileGetContents($url);
    if ($html && isValidHTML($html)) return $html;
    
    // Stratégie 5: Service de scraping en dernier recours (si configuré mais pas demandé)
    if (!$useScrapingService && !empty($SCRAPING_CONFIG['service']) && !empty($SCRAPING_CONFIG['api_key'])) {
        error_log("Tentative de fallback avec service de scraping...");
        $html = fetchWithScrapingService($url, $SCRAPING_CONFIG);
        if ($html && isValidHTML($html)) {
            error_log("Succès fallback avec service de scraping: " . $SCRAPING_CONFIG['service']);
            return $html;
        }
    }
    
    return false;
}

/**
 * Récupère le HTML via un service de scraping tiers
 */
function fetchWithScrapingService($url, $config) {
    $service = strtolower($config['service']);
    $apiKey = $config['api_key'];
    $options = $config['options'] ?? [];
    
    switch ($service) {
        case 'scrapingbee':
            return fetchWithScrapingBee($url, $apiKey, $options);
        case 'scraperapi':
            return fetchWithScraperAPI($url, $apiKey, $options);
        case 'browserless':
            return fetchWithBrowserless($url, $apiKey, $options);
        case 'zenrows':
            return fetchWithZenRows($url, $apiKey, $options);
        default:
            error_log("Service de scraping inconnu: $service");
            return false;
    }
}

/**
 * ScrapingBee - https://www.scrapingbee.com/
 * Excellent pour contourner Cloudflare avec JavaScript rendering
 */
function fetchWithScrapingBee($url, $apiKey, $options = []) {
    error_log("ScrapingBee: Début de la requête pour URL: $url");
    error_log("ScrapingBee: API Key (5 premiers chars): " . substr($apiKey, 0, 5) . "...");
    
    $params = [
        'api_key' => $apiKey,
        'url' => $url,
        'render_js' => $options['render_js'] ?? 'true',
        'premium_proxy' => $options['premium_proxy'] ?? 'true',
        'country_code' => $options['country_code'] ?? 'fr',
        'block_ads' => 'true',
        'block_resources' => 'false',
        'wait' => $options['wait'] ?? '5000',
    ];
    
    $apiUrl = 'https://app.scrapingbee.com/api/v1/?' . http_build_query($params);
    error_log("ScrapingBee: URL API construite (longueur: " . strlen($apiUrl) . ")");
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);
    
    error_log("ScrapingBee: Réponse reçue - HTTP $httpCode, Taille: " . strlen($html) . " chars, Temps: {$totalTime}s");
    
    if ($error) {
        error_log("ScrapingBee cURL Error: $error");
        return false;
    }
    
    if ($httpCode === 200 && strlen($html) > 500) {
        error_log("ScrapingBee: Succès pour URL: $url");
        return $html;
    }
    
    error_log("ScrapingBee: Échec HTTP $httpCode pour URL: $url - Réponse: " . substr($html, 0, 500));
    return false;
}

/**
 * ScraperAPI - https://www.scraperapi.com/
 * Bon rapport qualité/prix avec rotation d'IP automatique
 */
function fetchWithScraperAPI($url, $apiKey, $options = []) {
    $params = [
        'api_key' => $apiKey,
        'url' => $url,
        'render' => $options['render'] ?? 'true',
        'country_code' => $options['country_code'] ?? 'fr',
        'premium' => $options['premium'] ?? 'true',
    ];
    
    $apiUrl = 'https://api.scraperapi.com/?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("ScraperAPI Error: $error");
        return false;
    }
    
    if ($httpCode === 200 && strlen($html) > 500) {
        error_log("ScraperAPI: Succès pour URL: $url");
        return $html;
    }
    
    error_log("ScraperAPI: Échec HTTP $httpCode pour URL: $url");
    return false;
}

/**
 * Browserless - https://www.browserless.io/
 * Headless Chrome complet dans le cloud
 */
function fetchWithBrowserless($url, $apiKey, $options = []) {
    $apiUrl = 'https://chrome.browserless.io/content?token=' . $apiKey;
    
    $payload = json_encode([
        'url' => $url,
        'waitFor' => $options['wait_for'] ?? 3000,
        'gotoOptions' => [
            'waitUntil' => 'networkidle2',
            'timeout' => 60000
        ],
        'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ],
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Browserless Error: $error");
        return false;
    }
    
    if ($httpCode === 200 && strlen($html) > 500) {
        error_log("Browserless: Succès pour URL: $url");
        return $html;
    }
    
    error_log("Browserless: Échec HTTP $httpCode pour URL: $url");
    return false;
}

/**
 * ZenRows - https://www.zenrows.com/
 * Spécialisé anti-bot avec AI
 */
function fetchWithZenRows($url, $apiKey, $options = []) {
    $params = [
        'apikey' => $apiKey,
        'url' => $url,
        'js_render' => $options['js_render'] ?? 'true',
        'antibot' => $options['antibot'] ?? 'true',
        'premium_proxy' => $options['premium_proxy'] ?? 'true',
    ];
    
    $apiUrl = 'https://api.zenrows.com/v1/?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("ZenRows Error: $error");
        return false;
    }
    
    if ($httpCode === 200 && strlen($html) > 500) {
        error_log("ZenRows: Succès pour URL: $url");
        return $html;
    }
    
    error_log("ZenRows: Échec HTTP $httpCode pour URL: $url");
    return false;
}

/**
 * Vérifie si le HTML récupéré est valide (pas une page de challenge)
 */
function isValidHTML($html) {
    if (empty($html) || strlen($html) < 500) {
        return false;
    }
    
    $invalidPatterns = [
        'Checking your browser',
        'Just a moment...',
        'Please wait while we verify',
        'cf-browser-verification',
        'challenge-platform',
        '_cf_chl_opt',
        'Cloudflare Ray ID',
        'Enable JavaScript and cookies',
        'Attention Required!',
        'DDoS protection by',
        'Incapsula incident ID',
        'Access denied',
        'Bot verification',
        'please complete the security check'
    ];
    
    foreach ($invalidPatterns as $pattern) {
        if (stripos($html, $pattern) !== false) {
            error_log("HTML invalide détecté: contient '$pattern'");
            return false;
        }
    }
    
    if (!preg_match('/<(html|head|body|div|article|main|section)/i', $html)) {
        error_log("HTML invalide: pas de structure HTML standard");
        return false;
    }
    
    return true;
}

/**
 * Mode contournement avancé (multiples techniques)
 */
function fetchWithAdvancedBypass($url) {
    $attempts = [
        'fetchWithGoogleCacheProxy',
        'fetchWithWebArchive',
        'fetchAsGoogleBot',
        'fetchWithCloudflareBypass',
        'fetchWithRotatingUserAgents',
        'fetchWithDelayAndRetry',
        'fetchWithMobileUA'
    ];
    
    foreach ($attempts as $method) {
        if (function_exists($method)) {
            $html = $method($url);
            if ($html && isValidHTML($html)) {
                error_log("Succès avec méthode: $method pour URL: $url");
                return $html;
            }
        }
    }
    
    return false;
}

/**
 * Tentative via Google Cache (contourne souvent les protections)
 */
function fetchWithGoogleCacheProxy($url) {
    $cacheUrl = 'https://webcache.googleusercontent.com/search?q=cache:' . urlencode($url);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $cacheUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8',
        ],
        CURLOPT_ENCODING => '',
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && strlen($html) > 1000) {
        $html = preg_replace('/<div[^>]*id="google-cache-hdr"[^>]*>.*?<\/div>/s', '', $html);
        error_log("Succès via Google Cache pour URL: $url");
        return $html;
    }
    
    return false;
}

/**
 * Tentative via Web Archive (Wayback Machine)
 */
function fetchWithWebArchive($url) {
    $archiveApiUrl = 'https://archive.org/wayback/available?url=' . urlencode($url);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $archiveApiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['archived_snapshots']['closest']['url'])) {
        $archiveUrl = $data['archived_snapshots']['closest']['url'];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $archiveUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_ENCODING => '',
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && strlen($html) > 1000) {
            error_log("Succès via Web Archive pour URL: $url");
            return $html;
        }
    }
    
    return false;
}

/**
 * Se faire passer pour Googlebot (souvent whitelist)
 */
function fetchAsGoogleBot($url) {
    $googleBotUAs = [
        'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'Googlebot/2.1 (+http://www.google.com/bot.html)',
    ];
    
    foreach ($googleBotUAs as $ua) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'From: googlebot(at)googlebot.com',
            ],
            CURLOPT_ENCODING => '',
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && strlen($html) > 1000) {
            error_log("Succès avec Googlebot UA pour URL: $url");
            return $html;
        }
    }
    
    return false;
}

/**
 * User-Agent mobile (parfois moins protégé)
 */
function fetchWithMobileUA($url) {
    $mobileUAs = [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Linux; Android 14; SM-S928B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36',
    ];
    
    $randomUA = $mobileUAs[array_rand($mobileUAs)];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => $randomUA,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: fr-FR,fr;q=0.9',
            'Accept-Encoding: gzip, deflate',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
        ],
        CURLOPT_ENCODING => '',
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && strlen($html) > 1000) {
        error_log("Succès avec Mobile UA pour URL: $url");
        return $html;
    }
    
    return false;
}

/**
 * Contournement Cloudflare amélioré
 */
function fetchWithCloudflareBypass($url) {
    $ch = curl_init();
    
    // Headers Cloudflare-friendly
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept-Encoding: gzip, deflate, br',
        'Cache-Control: max-age=0',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Sec-Fetch-User: ?1',
        'sec-ch-ua: "Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'DNT: 1',
        'Sec-GPC: 1'
    ];
    
    // Extraction du domaine pour le referer
    $parsedUrl = parse_url($url);
    $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_COOKIEJAR => '/tmp/cookies_' . md5($url) . '.txt',
        CURLOPT_COOKIEFILE => '/tmp/cookies_' . md5($url) . '.txt',
        CURLOPT_REFERER => $baseUrl,
        CURLOPT_AUTOREFERER => true,
        // Simulation de vraies connexions
        CURLOPT_TCP_FASTOPEN => true,
        CURLOPT_TCP_NODELAY => true,
    ]);
    
    // Premier appel (peut déclencher un challenge)
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Si challenge Cloudflare détecté (503, 403, ou page challenge)
    if ($httpCode == 503 || $httpCode == 403 || strpos($html, 'cloudflare') !== false) {
        error_log("Challenge Cloudflare détecté, attente de 5 secondes...");
        sleep(5); // Attendre le challenge
        
        // Deuxième tentative
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }
    
    $error = curl_error($ch);
    curl_close($ch);
    
    // Nettoyer le fichier de cookies
    @unlink('/tmp/cookies_' . md5($url) . '.txt');
    
    if ($httpCode >= 200 && $httpCode < 400 && strlen($html) > 500) {
        return $html;
    }
    
    if ($error) {
        error_log("CURL Cloudflare Bypass Error: $error pour URL: $url");
    }
    
    return false;
}

/**
 * Rotation de User-Agents (évite détection de bot)
 */
function fetchWithRotatingUserAgents($url) {
    $userAgents = [
        // Chrome Windows
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        // Firefox Windows
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0',
        // Safari macOS
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        // Edge Windows
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0',
        // Chrome macOS
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
    ];
    
    // Choisir un User-Agent aléatoire
    $randomUA = $userAgents[array_rand($userAgents)];
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => $randomUA,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ],
        CURLOPT_ENCODING => '',
        CURLOPT_COOKIEJAR => '',
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 400 && strlen($html) > 500) {
        return $html;
    }
    
    return false;
}

/**
 * Retry avec délai progressif (évite rate limiting)
 */
function fetchWithDelayAndRetry($url, $maxAttempts = 3) {
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            CURLOPT_ENCODING => '',
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 400 && strlen($html) > 500) {
            error_log("Succès à la tentative $attempt pour URL: $url");
            return $html;
        }
        
        if ($attempt < $maxAttempts) {
            $delay = $attempt * 2; // 2s, 4s, 6s...
            error_log("Tentative $attempt échouée, attente de {$delay}s avant retry...");
            sleep($delay);
        }
    }
    
    return false;
}

/**
 * Fetch avec headers réalistes (navigateur Chrome)
 */
function fetchHTMLWithRealHeaders($url) {
    $ch = curl_init();
    
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept-Encoding: gzip, deflate, br',
        'Cache-Control: max-age=0',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Sec-Fetch-User: ?1',
        'sec-ch-ua: "Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"'
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_REFERER => 'https://www.google.com/',
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("CURL Error Real Headers: $error pour URL: $url");
        return false;
    }
    
    if ($httpCode >= 200 && $httpCode < 400) {
        return $html;
    }
    
    error_log("HTTP Code $httpCode avec Real Headers pour URL: $url");
    return false;
}

/**
 * Fetch basique (fallback)
 */
function fetchHTMLBasic($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => GEO_AUDIT_USER_AGENT,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("CURL Error Basic: $error pour URL: $url");
        return false;
    }
    
    if ($httpCode === 200) {
        return $html;
    }
    
    error_log("HTTP Code $httpCode Basic pour URL: $url");
    return false;
}

/**
 * Récupère le HTML avec identification explicite comme bot
 * Utilise le User-Agent GEO-Audit-Bot pour être identifiable dans les logs serveur
 */
function fetchHTMLAsBot($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => GEO_AUDIT_USER_AGENT,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'X-GEO-Audit: true',
            'X-Bot-Purpose: SEO/GEO Analysis'
        ],
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("CURL Error AsBot: $error pour URL: $url");
        return false;
    }
    
    if ($httpCode === 200) {
        error_log("fetchHTMLAsBot: Succès HTTP 200 pour URL: $url");
        return $html;
    }
    
    error_log("HTTP Code $httpCode AsBot pour URL: $url");
    return false;
}

/**
 * file_get_contents avec contexte (dernier recours)
 */
function fetchWithFileGetContents($url) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36\r\n" .
                       "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
                       "Accept-Language: fr-FR,fr;q=0.9\r\n",
            'timeout' => 30,
            'follow_location' => true,
            'max_redirects' => 5,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    
    if ($html && strlen($html) > 500) {
        error_log("Succès avec file_get_contents pour URL: $url");
        return $html;
    }
    
    return false;
}

// Les fonctions analyzeHTML, analyzeEntities, etc. restent identiques
// (Copier tout le reste du fichier audit.php original ici)

/**
 * Détecte si le site utilise WordPress
 */
function detectWordPress($html, $xpath) {
    $indicators = [
        'wp-content' => strpos($html, 'wp-content') !== false,
        'wp-includes' => strpos($html, 'wp-includes') !== false,
        'wordpress' => stripos($html, 'wordpress') !== false,
        'wp-json' => strpos($html, 'wp-json') !== false,
        'woocommerce' => strpos($html, 'woocommerce') !== false,
        'elementor' => strpos($html, 'elementor') !== false,
        'yoast' => stripos($html, 'yoast') !== false,
        'generator_wp' => preg_match('/<meta[^>]*name=["\']generator["\'][^>]*content=["\']WordPress/i', $html),
    ];
    
    $score = 0;
    foreach ($indicators as $value) {
        if ($value) $score++;
    }
    
    return $score >= 2;
}

/**
 * Analyse complète du HTML
 */
function analyzeHTML($html, $url, $pageType) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR);
    $xpath = new DOMXPath($dom);
    
    $audit = [
        'url' => $url,
        'pageType' => $pageType,
        'timestamp' => date('Y-m-d H:i:s'),
        'score' => 0,
        'isWordPress' => detectWordPress($html, $xpath),
        'entities' => analyzeEntities($html, $xpath),
        'media' => analyzeMedia($xpath),
        'content' => analyzeContent($html, $xpath),
        'metadata' => analyzeMetadata($xpath),
        'jsonld' => extractJSONLD($html),
        'breakdown' => []
    ];
    
    $audit['breakdown'] = calculateBreakdown($audit, $pageType);
    $audit['score'] = floorTo2Decimals(array_sum($audit['breakdown']));
    $audit['recommendations'] = generateRecommendations($audit);
    
    return $audit;
}

function extractJSONLD($html) {
    $scripts = [];
    
    preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', 
                   $html, $jsonldMatches);
    
    foreach ($jsonldMatches[1] as $jsonld) {
        $data = json_decode($jsonld, true);
        if ($data) {
            $type = 'Unknown';
            
            if (isset($data['@type'])) {
                $type = $data['@type'];
            } elseif (isset($data['@graph']) && !empty($data['@graph'])) {
                $types = array_map(function($item) {
                    return $item['@type'] ?? 'Unknown';
                }, $data['@graph']);
                $type = implode(', ', array_unique($types));
            }
            
            $scripts[] = [
                'type' => $type,
                'data' => $data
            ];
        }
    }
    
    return $scripts;
}

function analyzeEntities($html, $xpath) {
    $entities = [
        'organization' => 0,
        'person' => 0,
        'service' => 0,
        'product' => 0,
        'localBusiness' => 0,
        'details' => []
    ];
    
    preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', 
                   $html, $jsonldMatches);
    
    error_log("analyzeEntities: Trouvé " . count($jsonldMatches[1]) . " script(s) JSON-LD");
    
    foreach ($jsonldMatches[1] as $idx => $jsonld) {
        $data = json_decode($jsonld, true);
        if (!$data) {
            error_log("analyzeEntities: Script #$idx - JSON invalide, erreur: " . json_last_error_msg());
            error_log("analyzeEntities: Contenu (500 premiers chars): " . substr($jsonld, 0, 500));
            continue;
        }
        
        $items = [];
        if (isset($data['@graph'])) {
            $items = $data['@graph'];
            error_log("analyzeEntities: Script #$idx - @graph avec " . count($items) . " items");
        } else {
            $items = [$data];
            error_log("analyzeEntities: Script #$idx - Entité simple de type: " . ($data['@type'] ?? 'inconnu'));
        }
        
        foreach ($items as $item) {
            $type = $item['@type'] ?? '';
            error_log("analyzeEntities: Traitement entité de type: '$type'");
            
            switch ($type) {
                case 'Organization':
                    $entities['organization']++;
                    $address = '';
                    if (isset($item['address'])) {
                        if (is_string($item['address'])) {
                            $address = $item['address'];
                        } elseif (is_array($item['address'])) {
                            $parts = [];
                            if (!empty($item['address']['streetAddress'])) $parts[] = $item['address']['streetAddress'];
                            if (!empty($item['address']['postalCode'])) $parts[] = $item['address']['postalCode'];
                            if (!empty($item['address']['addressLocality'])) $parts[] = $item['address']['addressLocality'];
                            if (!empty($item['address']['addressCountry'])) $parts[] = $item['address']['addressCountry'];
                            $address = implode(', ', $parts);
                        }
                    }
                    $entities['details'][] = [
                        'type' => 'Organization',
                        'name' => $item['name'] ?? 'Sans nom',
                        'description' => $item['description'] ?? '',
                        'url' => $item['url'] ?? '',
                        'logo' => is_string($item['logo'] ?? '') ? ($item['logo'] ?? '') : ($item['logo']['url'] ?? ''),
                        'image' => is_string($item['image'] ?? '') ? ($item['image'] ?? '') : ($item['image']['url'] ?? ''),
                        'alternateName' => is_array($item['alternateName'] ?? null) ? implode(', ', $item['alternateName']) : ($item['alternateName'] ?? ''),
                        'address' => $address,
                        'email' => $item['email'] ?? '',
                        'telephone' => $item['telephone'] ?? '',
                        'sameAs' => is_array($item['sameAs'] ?? null) ? $item['sameAs'] : [],
                        'hasJSONLD' => true
                    ];
                    break;
                    
                case 'Person':
                    $entities['person']++;
                    $worksForName = '';
                    if (isset($item['worksFor'])) {
                        if (is_string($item['worksFor'])) {
                            $worksForName = $item['worksFor'];
                        } elseif (isset($item['worksFor']['name'])) {
                            $worksForName = $item['worksFor']['name'];
                        } elseif (isset($item['worksFor']['@id'])) {
                            $worksForName = 'Référence: ' . $item['worksFor']['@id'];
                        }
                    }
                    $entities['details'][] = [
                        'type' => 'Person',
                        'name' => $item['name'] ?? 'Sans nom',
                        'description' => $item['description'] ?? '',
                        'url' => $item['url'] ?? '',
                        'image' => is_string($item['image'] ?? '') ? ($item['image'] ?? '') : ($item['image']['url'] ?? ''),
                        'jobTitle' => $item['jobTitle'] ?? '',
                        'email' => $item['email'] ?? '',
                        'telephone' => $item['telephone'] ?? '',
                        'worksFor' => $worksForName,
                        'memberOf' => isset($item['memberOf']) ? 'Oui' : 'Non',
                        'sameAs' => is_array($item['sameAs'] ?? null) ? $item['sameAs'] : [],
                        'hasJSONLD' => true
                    ];
                    break;
                    
                case 'Service':
                    $entities['service']++;
                    $providerName = '';
                    if (isset($item['provider'])) {
                        if (is_string($item['provider'])) {
                            $providerName = $item['provider'];
                        } elseif (isset($item['provider']['name'])) {
                            $providerName = $item['provider']['name'];
                        } elseif (isset($item['provider']['@id'])) {
                            $providerName = 'Référence: ' . $item['provider']['@id'];
                        }
                    }
                    $areaServed = '';
                    if (isset($item['areaServed'])) {
                        if (is_string($item['areaServed'])) {
                            $areaServed = $item['areaServed'];
                        } elseif (is_array($item['areaServed'])) {
                            if (isset($item['areaServed']['name'])) {
                                $areaServed = $item['areaServed']['name'];
                            } else {
                                $areas = array_map(function($a) {
                                    return is_string($a) ? $a : ($a['name'] ?? '');
                                }, $item['areaServed']);
                                $areaServed = implode(', ', array_filter($areas));
                            }
                        }
                    }
                    $offers = [];
                    if (isset($item['offers'])) {
                        $offersList = isset($item['offers'][0]) ? $item['offers'] : [$item['offers']];
                        foreach ($offersList as $offer) {
                            $offerInfo = [];
                            if (!empty($offer['name'])) $offerInfo[] = $offer['name'];
                            if (!empty($offer['price'])) {
                                $price = $offer['price'];
                                if (!empty($offer['priceCurrency'])) $price .= ' ' . $offer['priceCurrency'];
                                $offerInfo[] = $price;
                            }
                            if (!empty($offerInfo)) $offers[] = implode(' - ', $offerInfo);
                        }
                    }
                    $entities['details'][] = [
                        'type' => 'Service',
                        'name' => $item['name'] ?? 'Sans nom',
                        'description' => $item['description'] ?? '',
                        'url' => $item['url'] ?? '',
                        'image' => is_string($item['image'] ?? '') ? ($item['image'] ?? '') : ($item['image']['url'] ?? ''),
                        'provider' => $providerName,
                        'serviceType' => $item['serviceType'] ?? '',
                        'areaServed' => $areaServed,
                        'offers' => $offers,
                        'hasJSONLD' => true
                    ];
                    break;
                    
                case 'Product':
                    $entities['product']++;
                    $brand = '';
                    if (isset($item['brand'])) {
                        $brand = is_string($item['brand']) ? $item['brand'] : ($item['brand']['name'] ?? '');
                    }
                    $offers = [];
                    if (isset($item['offers'])) {
                        $offersList = isset($item['offers'][0]) ? $item['offers'] : [$item['offers']];
                        foreach ($offersList as $offer) {
                            $offerInfo = [];
                            if (!empty($offer['price'])) {
                                $price = $offer['price'];
                                if (!empty($offer['priceCurrency'])) $price .= ' ' . $offer['priceCurrency'];
                                $offerInfo[] = $price;
                            }
                            if (!empty($offer['availability'])) {
                                $avail = str_replace('https://schema.org/', '', $offer['availability']);
                                $offerInfo[] = $avail;
                            }
                            if (!empty($offerInfo)) $offers[] = implode(' - ', $offerInfo);
                        }
                    }
                    $entities['details'][] = [
                        'type' => 'Product',
                        'name' => $item['name'] ?? 'Sans nom',
                        'description' => $item['description'] ?? '',
                        'url' => $item['url'] ?? '',
                        'image' => is_string($item['image'] ?? '') ? ($item['image'] ?? '') : ($item['image']['url'] ?? ''),
                        'brand' => $brand,
                        'sku' => $item['sku'] ?? '',
                        'offers' => $offers,
                        'hasJSONLD' => true
                    ];
                    break;
                    
                case 'LocalBusiness':
                    $entities['localBusiness']++;
                    $address = '';
                    if (isset($item['address'])) {
                        if (is_string($item['address'])) {
                            $address = $item['address'];
                        } elseif (is_array($item['address'])) {
                            $parts = [];
                            if (!empty($item['address']['streetAddress'])) $parts[] = $item['address']['streetAddress'];
                            if (!empty($item['address']['postalCode'])) $parts[] = $item['address']['postalCode'];
                            if (!empty($item['address']['addressLocality'])) $parts[] = $item['address']['addressLocality'];
                            $address = implode(', ', $parts);
                        }
                    }
                    $entities['details'][] = [
                        'type' => 'LocalBusiness',
                        'name' => $item['name'] ?? 'Sans nom',
                        'description' => $item['description'] ?? '',
                        'url' => $item['url'] ?? '',
                        'image' => is_string($item['image'] ?? '') ? ($item['image'] ?? '') : ($item['image']['url'] ?? ''),
                        'address' => $address,
                        'telephone' => $item['telephone'] ?? '',
                        'email' => $item['email'] ?? '',
                        'priceRange' => $item['priceRange'] ?? '',
                        'openingHours' => is_array($item['openingHours'] ?? null) ? implode(', ', $item['openingHours']) : ($item['openingHours'] ?? ''),
                        'hasJSONLD' => true
                    ];
                    break;
            }
        }
    }
    
    $microdataItems = $xpath->query('//*[@itemscope]');
    foreach ($microdataItems as $item) {
        $itemtype = $item->getAttribute('itemtype');
        if (strpos($itemtype, 'schema.org/Organization') !== false) {
            $entities['organization']++;
        } elseif (strpos($itemtype, 'schema.org/Person') !== false) {
            $entities['person']++;
        }
    }
    
    return $entities;
}

function analyzeMedia($xpath) {
    $images = $xpath->query('//img');
    $imagesWithAlt = $xpath->query('//img[@alt and string-length(@alt) > 0]');
    $videos = $xpath->query('//video | //iframe[contains(@src, "youtube") or contains(@src, "vimeo")]');
    $audios = $xpath->query('//audio');
    $geoImages = $xpath->query('//*[contains(@class, "geo-image")]');
    $geoVideos = $xpath->query('//*[contains(@class, "geo-video")]');
    $geoAudios = $xpath->query('//*[contains(@class, "geo-audio")]');
    
    $imagesWithoutAltDetails = [];
    foreach ($images as $img) {
        $alt = $img->getAttribute('alt');
        if (empty($alt)) {
            $src = $img->getAttribute('src');
            if ($src) {
                $imagesWithoutAltDetails[] = [
                    'src' => $src,
                    'width' => $img->getAttribute('width') ?: 'auto',
                    'height' => $img->getAttribute('height') ?: 'auto',
                    'class' => $img->getAttribute('class') ?: ''
                ];
            }
        }
    }
    
    $imagesDetails = [];
    foreach ($images as $img) {
        $src = $img->getAttribute('src');
        $alt = $img->getAttribute('alt');
        if ($src) {
            $imagesDetails[] = [
                'src' => $src,
                'alt' => $alt ?: '',
                'hasAlt' => !empty($alt)
            ];
        }
    }
    
    return [
        'images' => $images->length,
        'imagesWithAlt' => $imagesWithAlt->length,
        'imagesWithoutAlt' => $images->length - $imagesWithAlt->length,
        'imagesWithoutAltDetails' => array_slice($imagesWithoutAltDetails, 0, 20),
        'imagesDetails' => array_slice($imagesDetails, 0, 30),
        'videos' => $videos->length,
        'audios' => $audios->length,
        'geoOptimized' => [
            'images' => $geoImages->length,
            'videos' => $geoVideos->length,
            'audios' => $geoAudios->length
        ]
    ];
}

function analyzeContent($html, $xpath) {
    $faqDetails = [];
    $faqElements = $xpath->query('//details[summary]');
    
    foreach ($faqElements as $faq) {
        $isInCookieBanner = false;
        $parent = $faq;
        while ($parent !== null) {
            if ($parent->nodeType === XML_ELEMENT_NODE) {
                $class = $parent->getAttribute('class') ?? '';
                $id = $parent->getAttribute('id') ?? '';
                if (preg_match('/cmplz|cookie|consent|gdpr|rgpd|tarteaucitron|axeptio|didomi|onetrust|cookiebot/i', $class . ' ' . $id)) {
                    $isInCookieBanner = true;
                    break;
                }
            }
            $parent = $parent->parentNode;
        }
        
        if ($isInCookieBanner) {
            continue;
        }
        
        $summary = $xpath->query('.//summary', $faq)->item(0);
        $question = $summary ? trim($summary->textContent) : '';
        
        $answer = '';
        $children = $faq->childNodes;
        foreach ($children as $child) {
            if ($child->nodeName !== 'summary' && $child->nodeType === XML_ELEMENT_NODE) {
                $answer .= trim($child->textContent) . ' ';
            }
        }
        
        if ($question) {
            $faqDetails[] = [
                'question' => $question,
                'answer' => trim($answer),
                'hasSchema' => false
            ];
        }
    }
    
    $faqJSONLD = preg_match('/"@type"\s*:\s*"FAQPage"/', $html);
    if ($faqJSONLD) {
        preg_match_all('/"name"\s*:\s*"([^"]+)".*?"text"\s*:\s*"([^"]+)"/s', $html, $faqSchemaMatches);
        
        if (!empty($faqSchemaMatches[1])) {
            $faqDetails = [];
            for ($i = 0; $i < count($faqSchemaMatches[1]); $i++) {
                $faqDetails[] = [
                    'question' => $faqSchemaMatches[1][$i],
                    'answer' => $faqSchemaMatches[2][$i],
                    'hasSchema' => true
                ];
            }
        }
    }
    
    $quotesDetails = [];
    $blockquotes = $xpath->query('//blockquote');
    
    foreach ($blockquotes as $quote) {
        $text = trim($quote->textContent);
        $cite = $quote->getAttribute('cite');
        
        $citeElement = $xpath->query('.//cite', $quote)->item(0);
        $author = $citeElement ? trim($citeElement->textContent) : '';
        
        if ($text) {
            $quotesDetails[] = [
                'text' => $text,
                'cite' => $cite,
                'author' => $author,
                'hasSchema' => false
            ];
        }
    }
    
    $blockquotesCount = $blockquotes->length;
    $cites = $xpath->query('//cite');
    
    $hasSchemaOrg = $xpath->query('//*[@itemscope or @itemtype]')->length > 0;
    $hasJSONLD = preg_match('/<script[^>]*type=["\']application\/ld\+json["\']/', $html);
    
    return [
        'faq' => count($faqDetails),
        'faqDetails' => $faqDetails,
        'hasFAQSchema' => $faqJSONLD ? true : false,
        'blockquotes' => $blockquotesCount,
        'quotesDetails' => $quotesDetails,
        'cites' => $cites->length,
        'hasSchemaOrg' => $hasSchemaOrg,
        'hasJSONLD' => $hasJSONLD ? true : false
    ];
}

function analyzeMetadata($xpath) {
    $title = $xpath->query('//title')->item(0);
    $titleText = $title ? trim($title->textContent) : '';
    
    $description = $xpath->query('//meta[@name="description"]/@content')->item(0);
    $descriptionText = $description ? trim($description->value) : '';
    
    $ogTitle = $xpath->query('//meta[@property="og:title"]/@content')->item(0);
    $ogTitleText = $ogTitle ? trim($ogTitle->value) : '';
    
    $ogImage = $xpath->query('//meta[@property="og:image"]/@content')->item(0);
    
    return [
        'hasTitle' => $title ? true : false,
        'title' => $titleText,
        'titleLength' => strlen($titleText),
        'hasDescription' => $description ? true : false,
        'description' => $descriptionText,
        'descriptionLength' => strlen($descriptionText),
        'hasOG' => ($ogTitle && $ogImage) ? true : false,
        'ogTitle' => $ogTitleText
    ];
}

function floorTo2Decimals($number) {
    return floor($number * 100) / 100;
}

function calculateBreakdown($audit, $pageType) {
    $breakdown = [
        'entities' => 0,
        'media' => 0,
        'structure' => 0,
        'metadata' => 0
    ];
    
    $totalEntities = $audit['entities']['organization'] + 
                     $audit['entities']['person'] + 
                     $audit['entities']['service'] + 
                     $audit['entities']['product'];
    
    if ($audit['entities']['organization'] > 0) $breakdown['entities'] += 10;
    if ($audit['entities']['person'] > 0) $breakdown['entities'] += 5 * min($audit['entities']['person'], 2);
    if ($totalEntities >= 3) $breakdown['entities'] += 10;
    
    $breakdown['entities'] = floorTo2Decimals(min(30, $breakdown['entities']));
    
    if ($audit['media']['images'] > 0) {
        $altRatio = $audit['media']['imagesWithAlt'] / $audit['media']['images'];
        $breakdown['media'] += 10 * $altRatio;
    }
    if ($audit['media']['videos'] > 0) $breakdown['media'] += 10;
    if ($audit['media']['audios'] > 0) $breakdown['media'] += 5;
    
    $breakdown['media'] = floorTo2Decimals(min(25, $breakdown['media']));
    
    if ($audit['content']['faq'] >= 2) $breakdown['structure'] += 10;
    if ($audit['content']['hasFAQSchema']) $breakdown['structure'] += 5;
    if ($audit['content']['blockquotes'] > 0) $breakdown['structure'] += 5;
    if ($audit['content']['hasJSONLD']) $breakdown['structure'] += 5;
    
    $breakdown['structure'] = floorTo2Decimals(min(25, $breakdown['structure']));
    
    if ($audit['metadata']['hasTitle']) $breakdown['metadata'] += 5;
    if ($audit['metadata']['hasDescription']) $breakdown['metadata'] += 5;
    if ($audit['metadata']['hasOG']) $breakdown['metadata'] += 5;
    if ($audit['content']['hasJSONLD']) $breakdown['metadata'] += 5;
    
    $breakdown['metadata'] = floorTo2Decimals(min(20, $breakdown['metadata']));
    
    return $breakdown;
}

function generateRecommendations($audit) {
    $recommendations = [];
    
    $totalEntities = $audit['entities']['organization'] + $audit['entities']['person'];
    
    if ($totalEntities === 0) {
        $recommendations[] = [
            'priority' => 'high',
            'category' => 'Entités',
            'message' => 'Ajouter des entités Schema.org (Organization, Person) avec JSON-LD'
        ];
    }
    
    if ($audit['entities']['organization'] === 0) {
        $recommendations[] = [
            'priority' => 'high',
            'category' => 'Entités',
            'message' => 'Créer une entité Organization pour votre entreprise'
        ];
    }
    
    if ($audit['content']['faq'] === 0) {
        $recommendations[] = [
            'priority' => 'high',
            'category' => 'Contenu',
            'message' => 'Ajouter une section FAQ avec Schema.org FAQPage (+30 points)'
        ];
    }
    
    if ($audit['content']['blockquotes'] === 0) {
        $recommendations[] = [
            'priority' => 'medium',
            'category' => 'Contenu',
            'message' => 'Ajouter des citations pour renforcer la crédibilité (+15 points)'
        ];
    }
    
    if ($audit['media']['imagesWithoutAlt'] > 0) {
        $recommendations[] = [
            'priority' => 'high',
            'category' => 'Médias',
            'message' => "Ajouter l'attribut alt à {$audit['media']['imagesWithoutAlt']} image(s)"
        ];
    }
    
    if (!$audit['content']['hasJSONLD']) {
        $recommendations[] = [
            'priority' => 'high',
            'category' => 'Technique',
            'message' => 'Implémenter JSON-LD Schema.org (plus performant que microdata)'
        ];
    }
    
    if ($audit['media']['videos'] === 0 && $audit['score'] < 80) {
        $recommendations[] = [
            'priority' => 'medium',
            'category' => 'Médias',
            'message' => 'Ajouter des vidéos pour enrichir le contenu (+10 points)'
        ];
    }
    
    if (!$audit['metadata']['hasOG']) {
        $recommendations[] = [
            'priority' => 'medium',
            'category' => 'Métadonnées',
            'message' => 'Ajouter les balises Open Graph (og:title, og:image)'
        ];
    }
    
    return $recommendations;
}