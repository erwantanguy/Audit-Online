<?php
/**
 * Script de test pour ScrapingBee
 * Usage: php test-scrapingbee.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Test ScrapingBee ===\n\n";

// Charger la configuration
$configFile = __DIR__ . '/scraping-config.json';
if (!file_exists($configFile)) {
    die("ERREUR: scraping-config.json non trouvé\n");
}

$config = json_decode(file_get_contents($configFile), true);
if (!$config) {
    die("ERREUR: scraping-config.json invalide (JSON mal formé)\n");
}

echo "1. Configuration chargée\n";
echo "   Service: " . ($config['service'] ?: '(vide)') . "\n";
echo "   API Key: " . (empty($config['api_key']) ? '(vide)' : substr($config['api_key'], 0, 10) . '...') . "\n\n";

if (empty($config['service'])) {
    die("ERREUR: 'service' non configuré dans scraping-config.json\n");
}

if (empty($config['api_key'])) {
    die("ERREUR: 'api_key' non configuré dans scraping-config.json\n");
}

// URL à tester
$testUrl = 'https://www.seo.com/fr/tools/ai/';

echo "2. Test de récupération de: $testUrl\n\n";

// Construire l'URL ScrapingBee
$params = [
    'api_key' => $config['api_key'],
    'url' => $testUrl,
    'render_js' => 'true',
    'premium_proxy' => 'true',
    'country_code' => 'fr',
    'block_ads' => 'true',
    'wait' => '5000',
];

$apiUrl = 'https://app.scrapingbee.com/api/v1/?' . http_build_query($params);

echo "3. Appel API ScrapingBee...\n";
echo "   URL API (masquée): https://app.scrapingbee.com/api/v1/?api_key=***&url=" . urlencode($testUrl) . "&...\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_VERBOSE => false,
]);

$startTime = microtime(true);
$html = curl_exec($ch);
$duration = round(microtime(true) - $startTime, 2);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "4. Résultats:\n";
echo "   Durée: {$duration}s\n";
echo "   Code HTTP: $httpCode\n";
echo "   Taille réponse: " . strlen($html) . " caractères\n";

if ($error) {
    echo "   ERREUR cURL: $error\n";
}

echo "\n";

// Analyser la réponse
if ($httpCode === 200) {
    echo "5. Succès HTTP 200!\n";
    
    // Vérifier si c'est du HTML valide
    if (strpos($html, '<html') !== false || strpos($html, '<!DOCTYPE') !== false) {
        echo "   HTML valide détecté\n";
        
        // Vérifier si c'est une page Cloudflare
        $cloudflarePatterns = [
            'Checking your browser',
            'Just a moment...',
            'cf-browser-verification',
            'challenge-platform',
            '_cf_chl_opt',
        ];
        
        $isCloudflare = false;
        foreach ($cloudflarePatterns as $pattern) {
            if (stripos($html, $pattern) !== false) {
                $isCloudflare = true;
                echo "   ATTENTION: Page Cloudflare détectée (pattern: $pattern)\n";
                break;
            }
        }
        
        if (!$isCloudflare) {
            echo "   Pas de protection Cloudflare détectée\n";
            
            // Afficher un extrait
            $title = '';
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                $title = trim(strip_tags($matches[1]));
            }
            echo "   Titre de la page: " . ($title ?: '(non trouvé)') . "\n";
            
            // Chercher des éléments clés
            $hasJsonLd = strpos($html, 'application/ld+json') !== false;
            echo "   JSON-LD présent: " . ($hasJsonLd ? 'Oui' : 'Non') . "\n";
        }
    } else {
        echo "   ATTENTION: La réponse ne semble pas être du HTML\n";
        echo "   Premiers 500 caractères:\n";
        echo "   " . substr($html, 0, 500) . "\n";
    }
} else {
    echo "5. ÉCHEC - Code HTTP: $httpCode\n";
    
    // Décoder les erreurs ScrapingBee
    $errorData = json_decode($html, true);
    if ($errorData) {
        echo "   Message d'erreur: " . ($errorData['message'] ?? 'Inconnu') . "\n";
        if (isset($errorData['error'])) {
            echo "   Détails: " . $errorData['error'] . "\n";
        }
    } else {
        echo "   Réponse brute: " . substr($html, 0, 300) . "\n";
    }
    
    // Codes d'erreur ScrapingBee courants
    $errorCodes = [
        401 => "Clé API invalide ou expirée",
        402 => "Crédits épuisés",
        403 => "Accès refusé (vérifiez votre plan)",
        429 => "Rate limit atteint",
        500 => "Erreur serveur ScrapingBee",
        502 => "Erreur proxy ScrapingBee",
        503 => "Service indisponible",
    ];
    
    if (isset($errorCodes[$httpCode])) {
        echo "   Explication: " . $errorCodes[$httpCode] . "\n";
    }
}

echo "\n=== Fin du test ===\n";
