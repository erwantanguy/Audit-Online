<?php
/**
 * GEO Audit Tool - Backend PHP
 * Analyse une URL et retourne les données GEO
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Récupération de l'URL à analyser
$input = json_decode(file_get_contents('php://input'), true);
$url = filter_var($input['url'] ?? '', FILTER_VALIDATE_URL);
$pageType = $input['pageType'] ?? 'article';

if (!$url) {
    http_response_code(400);
    echo json_encode(['error' => 'URL invalide']);
    exit;
}

// Récupération du HTML
$html = fetchHTML($url);
if (!$html) {
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de récupérer la page']);
    exit;
}

// Analyse de la page
$audit = analyzeHTML($html, $url, $pageType);

echo json_encode($audit, JSON_PRETTY_PRINT);

/**
 * Récupère le HTML d'une URL
 */
function fetchHTML($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; GEO-Audit-Bot/1.0)',
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode === 200) ? $html : false;
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
        'breakdown' => []
    ];
    
    // Calcul du score global
    $audit['breakdown'] = calculateBreakdown($audit, $pageType);
    $audit['score'] = array_sum($audit['breakdown']);
    $audit['recommendations'] = generateRecommendations($audit);
    
    return $audit;
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
    $faqDetails = $xpath->query('//details[summary]');
    $faqJSONLD = preg_match('/"@type"\s*:\s*"FAQPage"/', $html);
    
    // Citations
    $blockquotes = $xpath->query('//blockquote');
    $cites = $xpath->query('//cite');
    
    // Schema.org
    $hasSchemaOrg = $xpath->query('//*[@itemscope or @itemtype]')->length > 0;
    $hasJSONLD = preg_match('/<script[^>]*type=["\']application\/ld\+json["\']/', $html);
    
    return [
        'faq' => $faqDetails->length,
        'hasFAQSchema' => $faqJSONLD ? true : false,
        'blockquotes' => $blockquotes->length,
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
    $description = $xpath->query('//meta[@name="description"]/@content')->item(0);
    $ogTitle = $xpath->query('//meta[@property="og:title"]/@content')->item(0);
    $ogImage = $xpath->query('//meta[@property="og:image"]/@content')->item(0);
    
    return [
        'hasTitle' => $title ? true : false,
        'titleLength' => $title ? strlen($title->textContent) : 0,
        'hasDescription' => $description ? true : false,
        'descriptionLength' => $description ? strlen($description->value) : 0,
        'hasOG' => ($ogTitle && $ogImage) ? true : false
    ];
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
    
    $breakdown['entities'] = min(30, $breakdown['entities']);
    
    // MÉDIAS (max 25 points)
    if ($audit['media']['images'] > 0) {
        $altRatio = $audit['media']['imagesWithAlt'] / $audit['media']['images'];
        $breakdown['media'] += 10 * $altRatio;
    }
    if ($audit['media']['videos'] > 0) $breakdown['media'] += 10;
    if ($audit['media']['audios'] > 0) $breakdown['media'] += 5;
    
    $breakdown['media'] = min(25, $breakdown['media']);
    
    // STRUCTURE (max 25 points)
    if ($audit['content']['faq'] >= 2) $breakdown['structure'] += 10;
    if ($audit['content']['hasFAQSchema']) $breakdown['structure'] += 5;
    if ($audit['content']['blockquotes'] > 0) $breakdown['structure'] += 5;
    if ($audit['content']['hasJSONLD']) $breakdown['structure'] += 5;
    
    $breakdown['structure'] = min(25, $breakdown['structure']);
    
    // MÉTADONNÉES (max 20 points)
    if ($audit['metadata']['hasTitle']) $breakdown['metadata'] += 5;
    if ($audit['metadata']['hasDescription']) $breakdown['metadata'] += 5;
    if ($audit['metadata']['hasOG']) $breakdown['metadata'] += 5;
    if ($audit['content']['hasJSONLD']) $breakdown['metadata'] += 5;
    
    $breakdown['metadata'] = min(20, $breakdown['metadata']);
    
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