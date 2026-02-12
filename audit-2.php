<?php
/**
 * GEO Audit Tool - Backend PHP
 * Analyse une URL et retourne les données GEO
 */

// Activation des logs d'erreurs pour le debug
error_reporting(E_ALL);
ini_set('display_errors', 0); // Ne pas afficher dans la réponse
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/audit_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Récupération de l'URL à analyser
$input = json_decode(file_get_contents('php://input'), true);
$mode = $input['mode'] ?? 'url';
$pageType = $input['pageType'] ?? 'article';

if ($mode === 'html') {
    // Mode HTML copié-collé
    $html = $input['html'] ?? '';
    $url = $input['url'] ?? 'HTML copié-collé';
    
    if (empty($html) || strlen($html) < 100) {
        http_response_code(400);
        echo json_encode(['error' => 'HTML invalide ou trop court']);
        exit;
    }
    
} else {
    // Mode URL classique
    $url = filter_var($input['url'] ?? '', FILTER_VALIDATE_URL);
    $useProxy = $input['useProxy'] ?? false;
    
    if (!$url) {
        http_response_code(400);
        echo json_encode(['error' => 'URL invalide ou manquante']);
        exit;
    }
    
    // Vérifier que CURL est disponible
    if (!function_exists('curl_init')) {
        http_response_code(500);
        echo json_encode(['error' => 'Extension CURL non disponible sur le serveur']);
        exit;
    }
    
    try {
        // Récupération du HTML
        $html = fetchHTML($url);
        if (!$html) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Impossible de récupérer la page',
                'details' => 'La page ne répond pas, est inaccessible, ou bloque les requêtes. Essayez le mode "Analyser du HTML".'
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
    // Analyse de la page
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
 * Récupère le HTML d'une URL
 */
function fetchHTML($url) {
    // Essayer d'abord avec des headers plus réalistes
    $html = fetchHTMLWithRealHeaders($url);
    
    // Si échec, essayer avec cURL basique
    if (!$html) {
        $html = fetchHTMLBasic($url);
    }
    
    return $html;
}

/**
 * Fetch avec headers réalistes (simule un vrai navigateur)
 */
function fetchHTMLWithRealHeaders($url) {
    $ch = curl_init();
    
    // Headers d'un navigateur Chrome réel
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
        'sec-ch-ua: "Google Chrome";v="119", "Chromium";v="119", "Not?A_Brand";v="24"',
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
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '', // Support gzip/deflate
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_COOKIEFILE => '', // Activer les cookies
        CURLOPT_REFERER => 'https://www.google.com/', // Simuler un accès depuis Google
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errorNo = curl_errno($ch);
    curl_close($ch);
    
    // Log des erreurs CURL
    if ($errorNo) {
        error_log("CURL Error Real Headers ($errorNo): $error pour URL: $url");
        return false;
    }
    
    // Accepter les codes 2xx et 3xx
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
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; GEO-Audit-Bot/1.0; +https://ticoet.fr)',
        CURLOPT_ENCODING => '',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errorNo = curl_errno($ch);
    curl_close($ch);
    
    if ($errorNo) {
        error_log("CURL Error Basic ($errorNo): $error pour URL: $url");
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("HTTP Code $httpCode Basic pour URL: $url");
        return false;
    }
    
    return $html;
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
        'entities' => analyzeEntities($html, $xpath),
        'media' => analyzeMedia($xpath),
        'content' => analyzeContent($html, $xpath),
        'metadata' => analyzeMetadata($xpath),
        'jsonld' => extractJSONLD($html),
        'breakdown' => []
    ];
    
    // Calcul du score global
    $audit['breakdown'] = calculateBreakdown($audit, $pageType);
    $audit['score'] = floorTo2Decimals(array_sum($audit['breakdown']));
    $audit['recommendations'] = generateRecommendations($audit);
    
    return $audit;
}

/**
 * Extraction des scripts JSON-LD
 */
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

/**
 * Analyse des entités Schema.org
 */
function analyzeEntities($html, $xpath) {
    $entities = [
        'organization' => 0,
        'person' => 0,
        'service' => 0,
        'product' => 0,
        'localBusiness' => 0,
        'details' => []
    ];
    
    // Extraction JSON-LD
    preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', 
                   $html, $jsonldMatches);
    
    foreach ($jsonldMatches[1] as $jsonld) {
        $data = json_decode($jsonld, true);
        if (!$data) continue;
        
        // Support @graph
        $items = [];
        if (isset($data['@graph'])) {
            $items = $data['@graph'];
        } else {
            $items = [$data];
        }
        
        foreach ($items as $item) {
            $type = $item['@type'] ?? '';
            
            switch ($type) {
                case 'Organization':
                    $entities['organization']++;
                    $entities['details'][] = [
                        'type' => 'Organization',
                        'name' => $item['name'] ?? 'Sans nom',
                        'url' => $item['url'] ?? '',
                        'logo' => $item['logo'] ?? '',
                        'hasJSONLD' => true
                    ];
                    break;
                    
                case 'Person':
                    $entities['person']++;
                    $entities['details'][] = [
                        'type' => 'Person',
                        'name' => $item['name'] ?? 'Sans nom',
                        'jobTitle' => $item['jobTitle'] ?? '',
                        'worksFor' => isset($item['worksFor']) ? 'Oui' : 'Non',
                        'hasJSONLD' => true
                    ];
                    break;
                    
                case 'Service':
                    $entities['service']++;
                    $entities['details'][] = [
                        'type' => 'Service',
                        'name' => $item['name'] ?? 'Sans nom',
                        'hasJSONLD' => true
                    ];
                    break;
                    
                case 'Product':
                    $entities['product']++;
                    $entities['details'][] = [
                        'type' => 'Product',
                        'name' => $item['name'] ?? 'Sans nom',
                        'hasJSONLD' => true
                    ];
                    break;
                    
                case 'LocalBusiness':
                    $entities['localBusiness']++;
                    $entities['details'][] = [
                        'type' => 'LocalBusiness',
                        'name' => $item['name'] ?? 'Sans nom',
                        'hasJSONLD' => true
                    ];
                    break;
            }
        }
    }
    
    // Microdata (itemscope/itemtype)
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

/**
 * Analyse des médias
 */
function analyzeMedia($xpath) {
    // Images
    $images = $xpath->query('//img');
    $imagesWithAlt = $xpath->query('//img[@alt]');
    
    // Vidéos
    $videos = $xpath->query('//video | //iframe[contains(@src, "youtube") or contains(@src, "vimeo")]');
    
    // Audio
    $audios = $xpath->query('//audio');
    
    // Figures GEO
    $geoImages = $xpath->query('//*[contains(@class, "geo-image")]');
    $geoVideos = $xpath->query('//*[contains(@class, "geo-video")]');
    $geoAudios = $xpath->query('//*[contains(@class, "geo-audio")]');
    
    return [
        'images' => $images->length,
        'imagesWithAlt' => $imagesWithAlt->length,
        'imagesWithoutAlt' => $images->length - $imagesWithAlt->length,
        'videos' => $videos->length,
        'audios' => $audios->length,
        'geoOptimized' => [
            'images' => $geoImages->length,
            'videos' => $geoVideos->length,
            'audios' => $geoAudios->length
        ]
    ];
}

/**
 * Analyse du contenu structuré
 */
function analyzeContent($html, $xpath) {
    // FAQ
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
    
    // Vérifier si FAQ Schema.org
    $faqJSONLD = preg_match('/"@type"\s*:\s*"FAQPage"/', $html);
    if ($faqJSONLD) {
        preg_match_all('/"name"\s*:\s*"([^"]+)".*?"text"\s*:\s*"([^"]+)"/s', $html, $faqSchemaMatches);
        
        if (!empty($faqSchemaMatches[1])) {
            $faqDetails = []; // Remplacer par les FAQ Schema.org
            for ($i = 0; $i < count($faqSchemaMatches[1]); $i++) {
                $faqDetails[] = [
                    'question' => $faqSchemaMatches[1][$i],
                    'answer' => $faqSchemaMatches[2][$i],
                    'hasSchema' => true
                ];
            }
        }
    }
    
    // Citations
    $quotesDetails = [];
    $blockquotes = $xpath->query('//blockquote');
    
    foreach ($blockquotes as $quote) {
        $text = trim($quote->textContent);
        $cite = $quote->getAttribute('cite');
        
        // Chercher author
        $citeElement = $xpath->query('.//cite', $quote)->item(0);
        $author = $citeElement ? trim($citeElement->textContent) : '';
        
        if ($text) {
            $quotesDetails[] = [
                'text' => $text,
                'cite' => $cite,
                'author' => $author,
                'hasSchema' => false // Pourrait être amélioré pour détecter Schema.org Quotation
            ];
        }
    }
    
    $blockquotesCount = $blockquotes->length;
    $cites = $xpath->query('//cite');
    
    // Schema.org
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

/**
 * Analyse des métadonnées
 */
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

/**
 * Arrondit un nombre à 2 décimales vers le bas
 */
function floorTo2Decimals($number) {
    return floor($number * 100) / 100;
}

/**
 * Calcul de la répartition du score
 */
function calculateBreakdown($audit, $pageType) {
    $breakdown = [
        'entities' => 0,
        'media' => 0,
        'structure' => 0,
        'metadata' => 0
    ];
    
    // ENTITÉS (max 30 points)
    $totalEntities = $audit['entities']['organization'] + 
                     $audit['entities']['person'] + 
                     $audit['entities']['service'] + 
                     $audit['entities']['product'];
    
    if ($audit['entities']['organization'] > 0) $breakdown['entities'] += 10;
    if ($audit['entities']['person'] > 0) $breakdown['entities'] += 5 * min($audit['entities']['person'], 2);
    if ($totalEntities >= 3) $breakdown['entities'] += 10;
    
    $breakdown['entities'] = floorTo2Decimals(min(30, $breakdown['entities']));
    
    // MÉDIAS (max 25 points)
    if ($audit['media']['images'] > 0) {
        $altRatio = $audit['media']['imagesWithAlt'] / $audit['media']['images'];
        $breakdown['media'] += 10 * $altRatio;
    }
    if ($audit['media']['videos'] > 0) $breakdown['media'] += 10;
    if ($audit['media']['audios'] > 0) $breakdown['media'] += 5;
    
    $breakdown['media'] = floorTo2Decimals(min(25, $breakdown['media']));
    
    // STRUCTURE (max 25 points)
    if ($audit['content']['faq'] >= 2) $breakdown['structure'] += 10;
    if ($audit['content']['hasFAQSchema']) $breakdown['structure'] += 5;
    if ($audit['content']['blockquotes'] > 0) $breakdown['structure'] += 5;
    if ($audit['content']['hasJSONLD']) $breakdown['structure'] += 5;
    
    $breakdown['structure'] = floorTo2Decimals(min(25, $breakdown['structure']));
    
    // MÉTADONNÉES (max 20 points)
    if ($audit['metadata']['hasTitle']) $breakdown['metadata'] += 5;
    if ($audit['metadata']['hasDescription']) $breakdown['metadata'] += 5;
    if ($audit['metadata']['hasOG']) $breakdown['metadata'] += 5;
    if ($audit['content']['hasJSONLD']) $breakdown['metadata'] += 5;
    
    $breakdown['metadata'] = floorTo2Decimals(min(20, $breakdown['metadata']));
    
    return $breakdown;
}

/**
 * Génération des recommandations
 */
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