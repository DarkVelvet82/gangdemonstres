# Guide de déploiement - Gang de Monstres Standalone

## 📦 Ce qui a été créé

L'application WordPress "Objectifs Multijoueur" a été complètement transposée en application PHP standalone, totalement indépendante de WordPress.

### Structure complète

```
gang-de-monstres-standalone/
├── api/                           # API REST
│   ├── game.php                  # Gestion des parties
│   ├── player.php                # Gestion des objectifs
│   └── scores.php                # Gestion des scores
├── assets/
│   ├── css/objectif.css          # Styles
│   ├── js/                       # Scripts JavaScript
│   │   ├── app-config.js         # Config standalone
│   │   ├── objectif-main.js
│   │   ├── objectif-creation.js
│   │   ├── objectif-join.js
│   │   ├── objectif-objectives.js
│   │   ├── objectif-qr.js
│   │   ├── objectif-scores.js
│   │   └── objectif-status.js
│   └── uploads/                  # Uploads
├── config/
│   ├── database.php              # Config DB (gitignored)
│   └── database.example.php      # Template de config
├── includes/
│   ├── functions.php             # Fonctions utilitaires
│   └── install.php               # Installation DB
├── public/                       # Pages publiques
│   ├── index.php                 # Accueil
│   ├── creer-partie.php          # Création de partie
│   ├── rejoindre.php             # Rejoindre une partie
│   ├── objectif.php              # Page objectif joueur
│   └── scores.php                # Tableau des scores
├── admin/                        # Backoffice
│   ├── index.php                 # Dashboard
│   ├── login.php                 # Connexion
│   └── logout.php                # Déconnexion
├── .gitignore                    # Fichiers à ignorer
├── .htaccess                     # Routing Apache
├── install.php                   # Assistant d'installation
├── README.md                     # Documentation
└── DEPLOYMENT.md                 # Ce fichier
```

## 🔄 Migrations réalisées

### Backend (PHP)

✅ **Suppression dépendances WordPress:**
- `wp_send_json_success/error` → `send_json_response()`
- `$wpdb` → PDO natif
- `wp_ajax_*` hooks → Routing direct via .htaccess
- `check_ajax_referer()` → `verify_nonce()` custom
- `sanitize_*()` → `clean_string()`, `clean_int()`
- `get_var/get_row/get_results` → PDO prepare/execute

✅ **API REST native:**
- Endpoints RESTful (/api/game, /api/player, /api/scores)
- Gestion JSON native
- CORS headers
- Nonces simplifiés

✅ **Base de données:**
- Même structure que plugin WordPress
- Préfixe: `wp_objectif_`
- 8 tables principales
- Installation automatisée

### Frontend (JavaScript/HTML)

✅ **Adaptation JavaScript:**
- Remplacement `objectif_ajax` WordPress par config native
- Fonction `objectifAjax()` pour appels API
- URLs adaptées pour routing standalone
- Conservation de toute la logique métier

✅ **Pages HTML/PHP:**
- Templates indépendants (pas de shortcodes)
- Design conservé
- Navigation simplifiée
- Intégration directe des scripts

## 🚀 Déploiement Local (avec Local by Flywheel)

### 1. Installation

```bash
# L'application est déjà dans:
C:\Users\these\Local Sites\gang-de-monstres\app\public\gang-de-monstres-standalone
```

### 2. Configuration automatique

Le fichier `config/database.php` détecte automatiquement Local et utilise le `wp-config.php` parent.

### 3. Installer la base de données

Via navigateur: `http://gang-de-monstres.local/gang-de-monstres-standalone/install.php`

Ou via CLI:
```bash
cd "C:\Users\these\Local Sites\gang-de-monstres\app\public\gang-de-monstres-standalone"
php includes/install.php
```

### 4. Accès

- **Application:** `http://gang-de-monstres.local/gang-de-monstres-standalone/public/`
- **Admin:** `http://gang-de-monstres.local/gang-de-monstres-standalone/admin/`
  - User: `admin`
  - Pass: `admin123`

## 🌐 Déploiement Production (Hostinger)

### Méthode 1: Via Git (Recommandé)

```bash
# Sur le serveur Hostinger via SSH
cd public_html
git clone https://github.com/DarkVelvet82/gangdemonstres.git
cd gangdemonstres

# Copier et configurer la base de données
cp config/database.example.php config/database.php
nano config/database.php
# Modifier avec vos identifiants Hostinger

# Installer la base de données
php includes/install.php

# Configurer les permissions
chmod 755 -R .
chmod 644 config/database.php
```

### Méthode 2: Via FTP

1. Uploader tous les fichiers sauf `config/database.php`
2. Copier `config/database.example.php` en `config/database.php`
3. Éditer `config/database.php` avec les identifiants
4. Accéder à `https://votredomaine.com/install.php`

### Configuration Hostinger

Dans `config/database.php` (production):
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u282641111_gangdemonstres');
define('DB_USER', 'u282641111_theogang');
define('DB_PASS', 'L8Wh3cBKmQ9q');
define('APP_URL', 'https://gangdemonstres.com/');
define('DEBUG_MODE', false);
```

## 🔐 Sécurité Production

### Actions à effectuer IMMÉDIATEMENT:

1. **Changer le mot de passe admin:**
   ```sql
   UPDATE wp_objectif_users
   SET password = '$2y$10$...'
   WHERE username = 'admin';
   ```
   Ou via backoffice après connexion

2. **Supprimer/Renommer install.php:**
   ```bash
   mv install.php install.php.bak
   ```

3. **Protéger le dossier admin avec .htpasswd:**
   ```apache
   # Dans admin/.htaccess
   AuthType Basic
   AuthName "Administration"
   AuthUserFile /path/to/.htpasswd
   Require valid-user
   ```

4. **Vérifier les permissions:**
   ```bash
   find . -type d -exec chmod 755 {} \;
   find . -type f -exec chmod 644 {} \;
   chmod 600 config/database.php
   ```

5. **Activer HTTPS:**
   - Certificat SSL via Hostinger
   - Forcer HTTPS dans .htaccess

## 📊 État actuel

✅ **Complété:**
- Migration complète du plugin WordPress
- API REST fonctionnelle
- Interface utilisateur adaptée
- Backoffice basique
- Installation automatisée
- Git initialisé
- README complet

⚠️ **À compléter (si besoin):**
- Pages admin complètes (types, jeux, difficultés)
- Upload d'images dans le backoffice
- Système de nonce plus robuste
- Tests unitaires
- Logs avancés

## 🔗 Git & GitHub

### Remote configuré:
```
https://github.com/DarkVelvet82/gangdemonstres.git
```

### Pour pusher vers GitHub:
```bash
cd "C:\Users\these\Local Sites\gang-de-monstres\app\public\gang-de-monstres-standalone"
git push -u origin main
```

### Pour cloner en production:
```bash
git clone https://github.com/DarkVelvet82/gangdemonstres.git
```

## 📞 Support

Pour toute question ou problème:
1. Vérifier le README.md
2. Vérifier les logs d'erreur PHP
3. Activer DEBUG_MODE dans config/database.php
4. Vérifier la console navigateur pour erreurs JS

## ✅ Checklist de déploiement

- [ ] Cloner/uploader les fichiers
- [ ] Configurer config/database.php
- [ ] Lancer l'installation (install.php)
- [ ] Tester la création d'une partie
- [ ] Tester la génération d'objectifs
- [ ] Tester les scores
- [ ] Se connecter au backoffice
- [ ] Changer le mot de passe admin
- [ ] Supprimer install.php
- [ ] Vérifier les permissions
- [ ] Tester en HTTPS
- [ ] Backup de la base de données

---

**Version:** 2.0.0
**Date:** 2025-01-30
**Auteur:** TheoDumont
