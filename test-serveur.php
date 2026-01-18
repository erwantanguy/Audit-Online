<?php
/**
 * Test de configuration serveur pour GEO Audit Tool
 * Accédez à ce fichier dans votre navigateur pour diagnostiquer les problèmes
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Serveur - GEO Audit Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #ccc;
        }
        .test.ok {
            border-left-color: #28a745;
        }
        .test.error {
            border-left-color: #dc3545;
        }
        .test.warning {
            border-left-color: #ffc107;
        }
        h1 {
            color: #333;
        }
        .status {
            font-weight: bold;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 14px;
        }
        .status.ok {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>🔧 Diagnostic Serveur GEO Audit Tool</h1>
    
    <?php
    // Test 1: Version PHP
    $phpVersion = phpversion();
    $phpOk = version_compare($phpVersion, '7.4', '>=');
    ?>
    <div class="test <?php echo $phpOk ? 'ok' : 'error'; ?>">
        <span class="status <?php echo $phpOk ? 'ok' : 'error'; ?>">
            <?php echo $phpOk ? '✓' : '✗'; ?>
        </span>
        <strong>Version PHP:</strong> <?php echo $phpVersion; ?>
        <?php if (!$phpOk): ?>
            <br><small>⚠️ PHP 7.4 ou supérieur requis</small>
        <?php endif; ?>
    </div>
    
    <?php
    // Test 2: Extension CURL
    $curlOk = function_exists('curl_init');
    ?>
    <div class="test <?php echo $curlOk ? 'ok' : 'error'; ?>">
        <span class="status <?php echo $curlOk ? 'ok' : 'error'; ?>">
            <?php echo $curlOk ? '✓' : '✗'; ?>
        </span>
        <strong>Extension CURL:</strong> <?php echo $curlOk ? 'Installée' : 'Non installée'; ?>
        <?php if (!$curlOk): ?>
            <br><small>⚠️ Installez CURL: <code>sudo apt-get install php-curl</code></small>
        <?php endif; ?>
    </div>
    
    <?php
    // Test 3: Extension DOM
    $domOk = class_exists('DOMDocument');
    ?>
    <div class="test <?php echo $domOk ? 'ok' : 'error'; ?>">
        <span class="status <?php echo $domOk ? 'ok' : 'error'; ?>">
            <?php echo $domOk ? '✓' : '✗'; ?>
        </span>
        <strong>Extension DOM:</strong> <?php echo $domOk ? 'Installée' : 'Non installée'; ?>
        <?php if (!$domOk): ?>
            <br><small>⚠️ Installez DOM: <code>sudo apt-get install php-xml</code></small>
        <?php endif; ?>
    </div>
    
    <?php
    // Test 4: Extension JSON
    $jsonOk = function_exists('json_encode');
    ?>
    <div class="test <?php echo $jsonOk ? 'ok' : 'error'; ?>">
        <span class="status <?php echo $jsonOk ? 'ok' : 'error'; ?>">
            <?php echo $jsonOk ? '✓' : '✗'; ?>
        </span>
        <strong>Extension JSON:</strong> <?php echo $jsonOk ? 'Installée' : 'Non installée'; ?>
    </div>
    
    <?php
    // Test 5: Permissions fichiers
    $logDir = __DIR__;
    $writable = is_writable($logDir);
    ?>
    <div class="test <?php echo $writable ? 'ok' : 'warning'; ?>">
        <span class="status <?php echo $writable ? 'ok' : 'warning'; ?>">
            <?php echo $writable ? '✓' : '⚠'; ?>
        </span>
        <strong>Permissions d'écriture:</strong> <?php echo $writable ? 'OK' : 'Limitées'; ?>
        <?php if (!$writable): ?>
            <br><small>⚠️ Les logs ne pourront pas être créés. Exécutez: <code>chmod 755 <?php echo $logDir; ?></code></small>
        <?php endif; ?>
    </div>
    
    <?php
    // Test 6: Fichier audit.php
    $auditExists = file_exists(__DIR__ . '/audit.php');
    ?>
    <div class="test <?php echo $auditExists ? 'ok' : 'error'; ?>">
        <span class="status <?php echo $auditExists ? 'ok' : 'error'; ?>">
            <?php echo $auditExists ? '✓' : '✗'; ?>
        </span>
        <strong>Fichier audit.php:</strong> <?php echo $auditExists ? 'Présent' : 'Manquant'; ?>
        <?php if (!$auditExists): ?>
            <br><small>⚠️ Uploadez audit.php dans le même dossier</small>
        <?php endif; ?>
    </div>
    
    <?php
    // Test 7: Fichier index.html
    $indexExists = file_exists(__DIR__ . '/index.html');
    ?>
    <div class="test <?php echo $indexExists ? 'ok' : 'error'; ?>">
        <span class="status <?php echo $indexExists ? 'ok' : 'error'; ?>">
            <?php echo $indexExists ? '✓' : '✗'; ?>
        </span>
        <strong>Fichier index.html:</strong> <?php echo $indexExists ? 'Présent' : 'Manquant'; ?>
        <?php if (!$indexExists): ?>
            <br><small>⚠️ Uploadez index.html dans le même dossier</small>
        <?php endif; ?>
    </div>
    
    <?php if ($curlOk): ?>
    <?php
    // Test 8: Test CURL réel
    $testUrl = 'https://ticoet.fr';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $testUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'GEO-Audit-Test',
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $curlTestOk = ($httpCode === 200 && !empty($result));
    ?>
    <div class="test <?php echo $curlTestOk ? 'ok' : 'error'; ?>">
        <span class="status <?php echo $curlTestOk ? 'ok' : 'error'; ?>">
            <?php echo $curlTestOk ? '✓' : '✗'; ?>
        </span>
        <strong>Test CURL:</strong> <?php echo $curlTestOk ? 'Fonctionne' : 'Échec'; ?>
        <?php if (!$curlTestOk): ?>
            <br><small>⚠️ HTTP Code: <?php echo $httpCode; ?> | Erreur: <?php echo $error ?: 'Aucune'; ?></small>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php
    // Résumé
    $allOk = $phpOk && $curlOk && $domOk && $jsonOk && $auditExists && $indexExists;
    ?>
    
    <div class="test <?php echo $allOk ? 'ok' : 'warning'; ?>" style="margin-top: 30px; border-width: 4px;">
        <h2 style="margin-top: 0;">
            <?php if ($allOk): ?>
                ✅ Serveur prêt !
            <?php else: ?>
                ⚠️ Configuration incomplète
            <?php endif; ?>
        </h2>
        
        <?php if ($allOk): ?>
            <p>Votre serveur est correctement configuré pour GEO Audit Tool.</p>
            <p><a href="index.html" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin-top: 10px;">🚀 Accéder à l'outil</a></p>
        <?php else: ?>
            <p>Corrigez les problèmes ci-dessus avant d'utiliser l'outil.</p>
        <?php endif; ?>
    </div>
    
    <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
    
    <h2>📊 Informations système</h2>
    <div class="test">
        <strong>Système d'exploitation:</strong> <?php echo PHP_OS; ?><br>
        <strong>Serveur:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu'; ?><br>
        <strong>Document Root:</strong> <code><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Inconnu'; ?></code><br>
        <strong>Répertoire actuel:</strong> <code><?php echo __DIR__; ?></code><br>
        <strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?><br>
        <strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?><br>
        <strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?>s<br>
    </div>
    
    <p style="text-align: center; color: #666; margin-top: 30px; font-size: 14px;">
        GEO Audit Tool v1.0 | <a href="https://ticoet.fr" target="_blank">ticoet.fr</a>
    </p>
</body>
</html>