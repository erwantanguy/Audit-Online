# 🔍 Outil d'Audit GEO

Analyseur en ligne pour évaluer l'optimisation GEO (Generative Engine Optimization) d'une page web.

## 📦 Installation

### 1. Structure des fichiers

Créez un dossier sur votre serveur avec cette structure :

```
geo-audit/
├── index.html          (Interface utilisateur)
├── audit.php           (Backend d'analyse)
└── README.md           (Ce fichier)
```

### 2. Prérequis serveur

- **PHP** : 7.4 ou supérieur
- **Extensions PHP** :
  - `curl` (pour récupérer les pages)
  - `dom` (pour parser le HTML)
  - `json` (inclus par défaut)
- **Serveur web** : Apache, Nginx ou autre

### 3. Configuration Apache

Si vous utilisez Apache, créez un fichier `.htaccess` :

```apache
# Réécriture d'URL
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Forcer HTTPS (optionnel mais recommandé)
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>

# Sécurité
<Files "audit.php">
    Order allow,deny
    Allow from all
</Files>

# Compression GZIP
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
</IfModule>

# Cache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/html "access plus 0 seconds"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### 4. Configuration Nginx

Si vous utilisez Nginx, ajoutez à votre configuration :

```nginx
location / {
    try_files $uri $uri/ /index.html;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}

# Sécurité
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
```

---

## 🚀 Utilisation

### URL d'accès

Accédez à l'outil via : `https://votre-domaine.com/geo-audit/`

### Analyse d'une page

1. **Entrez l'URL** de la page à analyser
2. **Sélectionnez le type** de page (article, homepage, etc.)
3. Cliquez sur **"Lancer l'audit GEO"**
4. Consultez les résultats détaillés

---

## 📊 Métriques analysées

### 🏢 Entités Schema.org

- **Organization** : Détecte les entreprises/organisations
- **Person** : Détecte les personnes avec relations (worksFor, memberOf)
- **Service** : Détecte les services proposés
- **Product** : Détecte les produits
- **LocalBusiness** : Détecte les entreprises locales

### 🎨 Éléments multimédias

- **Images** : Comptage total et vérification des attributs `alt`
- **Vidéos** : Détection (YouTube, Vimeo, hébergées)
- **Audio** : Détection des fichiers audio
- **Médias GEO** : Détection des blocs MediaGEO optimisés

### 📝 Contenu structuré

- **FAQ** : Détection des FAQ (`<details>`, Schema.org FAQPage)
- **Citations** : Comptage des `<blockquote>` et `<cite>`
- **JSON-LD** : Vérification de la présence de Schema.org en JSON-LD
- **Microdata** : Détection du balisage microdata

### 🎯 Métadonnées

- **Title** : Présence et longueur
- **Description** : Présence et longueur
- **Open Graph** : Vérification des balises OG

---

## 📈 Calcul du score (max 100 points)

| Catégorie | Points max | Critères |
|-----------|-----------|----------|
| **Entités** | 30 | Organization (+10), Person (+5 chacune), Total ≥3 (+10) |
| **Médias** | 25 | Images avec alt (+10), Vidéos (+10), Audio (+5) |
| **Structure** | 25 | FAQ ≥2 (+10), FAQSchema (+5), Citations (+5), JSON-LD (+5) |
| **Métadonnées** | 20 | Title (+5), Description (+5), Open Graph (+5), JSON-LD (+5) |

### Interprétation du score

- 🟢 **80-100** : Excellent - Optimisé pour les IA
- 🟡 **50-79** : Bon - Améliorations possibles
- 🔴 **0-49** : À améliorer - Travail nécessaire

---

## 📥 Export des résultats

### Export CSV

Télécharge un fichier `.csv` avec toutes les métriques :

```csv
Métrique;Valeur
URL;https://example.com
Score GEO;85
Organizations;1
Persons;3
Images;12
Images avec alt;10
...
```

### Export PDF (à venir)

Version PDF complète avec graphiques et recommandations détaillées.

---

## 🔧 Personnalisation

### Modifier les coefficients de score

Éditez `audit.php`, fonction `calculateBreakdown()` :

```php
// Exemple : augmenter l'importance des FAQ
if ($audit['content']['faq'] >= 2) $breakdown['structure'] += 15; // au lieu de 10
```

### Ajouter de nouvelles analyses

1. Créez une fonction dans `audit.php` :

```php
function analyzeNewMetric($xpath) {
    // Votre analyse
    return $result;
}
```

2. Appelez-la dans `analyzeHTML()` :

```php
$audit['newMetric'] = analyzeNewMetric($xpath);
```

3. Mettez à jour l'affichage dans `index.html`

---

## 🐛 Dépannage

### Erreur "Impossible de récupérer la page"

**Cause** : L'URL cible bloque les requêtes (Cloudflare, anti-bot) ou CURL n'est pas configuré

**Solutions** :

1. **Mode compatible** : Cochez l'option "Utiliser un mode compatible" dans le formulaire
2. **Service de scraping** : Configurez un service tiers (voir section ci-dessous)
3. **Mode HTML** : Utilisez l'onglet "Analyser du HTML" en copiant le code source
4. **Vérifier CURL** :
```bash
php -m | grep curl

# Installer CURL si absent (Ubuntu/Debian)
sudo apt-get install php-curl
sudo systemctl restart apache2
```

---

## 🌐 Services de scraping tiers

Pour les sites protégés par Cloudflare ou des systèmes anti-bot, vous pouvez configurer un service de scraping tiers.

### Services supportés

| Service | Description | Tarification |
|---------|-------------|--------------|
| [ScrapingBee](https://www.scrapingbee.com/) | Excellent pour Cloudflare, JavaScript rendering | 1000 crédits gratuits |
| [ScraperAPI](https://www.scraperapi.com/) | Rotation d'IP automatique, bon rapport qualité/prix | 1000 requêtes/mois gratuites |
| [Browserless](https://www.browserless.io/) | Headless Chrome complet | Limité sans abonnement |
| [ZenRows](https://www.zenrows.com/) | Anti-bot avec IA | 1000 crédits gratuits |

### Configuration

1. Créez un compte sur le service de votre choix
2. Récupérez votre clé API
3. Modifiez le fichier `scraping-config.json` :

```json
{
    "service": "scrapingbee",
    "api_key": "VOTRE_CLE_API",
    "options": {
        "render_js": "true",
        "premium_proxy": "true",
        "country_code": "fr"
    }
}
```

### Utilisation

Une fois configuré :
- **Option manuelle** : Cochez "Utiliser un service de scraping tiers" dans le formulaire
- **Automatique** : Le service est utilisé en dernier recours si toutes les autres méthodes échouent

### Stratégies de récupération

L'outil utilise plusieurs stratégies en cascade :

1. **Service de scraping** (si demandé et configuré)
2. **Mode compatible avancé** : Google Cache, Web Archive, Googlebot UA, Mobile UA
3. **Headers Chrome réalistes**
4. **cURL basique**
5. **file_get_contents**
6. **Fallback service de scraping** (si configuré mais non demandé)

### Erreur "JSON invalide"

**Cause** : JSON-LD mal formé sur la page cible

**Solution** : L'erreur est normale, le script continue l'analyse

### Timeout

**Cause** : Page trop lourde ou serveur lent

**Solution** : Augmentez le timeout dans `audit.php` :

```php
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 secondes au lieu de 30
```

---

## 🔐 Sécurité

### Protection contre les abus

Ajoutez un rate limiting dans `audit.php` :

```php
session_start();

// Limite : 10 audits par heure
if (!isset($_SESSION['audit_count'])) {
    $_SESSION['audit_count'] = 0;
    $_SESSION['audit_reset'] = time() + 3600;
}

if (time() > $_SESSION['audit_reset']) {
    $_SESSION['audit_count'] = 0;
    $_SESSION['audit_reset'] = time() + 3600;
}

if ($_SESSION['audit_count'] >= 10) {
    http_response_code(429);
    echo json_encode(['error' => 'Limite atteinte, réessayez dans 1 heure']);
    exit;
}

$_SESSION['audit_count']++;
```

### Validation des URLs

Le script valide déjà les URLs avec `FILTER_VALIDATE_URL`.

Pour plus de sécurité, ajoutez une whitelist de domaines :

```php
$allowedDomains = ['example.com', 'monsite.fr'];
$domain = parse_url($url, PHP_URL_HOST);

if (!in_array($domain, $allowedDomains)) {
    http_response_code(403);
    echo json_encode(['error' => 'Domaine non autorisé']);
    exit;
}
```

---

## 📝 Licence

MIT License - Libre d'utilisation et de modification

---

## 👨‍💻 Auteur

**Erwan Tanguy - Ticoët**  
🌐 [ticoet.fr](https://www.ticoet.fr/)

---

## 🆘 Support

Pour toute question ou bug :
- 📧 Contact via [ticoet.fr](https://www.ticoet.fr/)
- 🐛 Issues GitHub (si hébergé sur GitHub)

---

## 🚧 Roadmap

### Version 1.1 (à venir)

- [ ] Export PDF avec graphiques
- [ ] Analyse des performances (Core Web Vitals)
- [ ] Détection du fichier `llms.txt`
- [ ] Comparaison avec concurrents
- [ ] Historique des audits
- [ ] API REST pour intégrations

### Version 1.2 (future)

- [ ] Analyse multi-pages (site complet)
- [ ] Suggestions de contenu IA
- [ ] Monitoring automatique
- [ ] Alertes par email

---

## 📚 Ressources

- [Schema.org Documentation](https://schema.org/)
- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org Validator](https://validator.schema.org/)
- [GEO Best Practices](https://www.ticoet.fr/)