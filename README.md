# Gang de Monstres - Objectifs Multijoueur

Application standalone pour générer des objectifs aléatoires dans le jeu Gang de Monstres, avec support multijoueur via codes QR et codes à 6 chiffres.

## 📋 Fonctionnalités

- ✨ Création de parties multijoueur (2-10 joueurs)
- 🎯 Génération d'objectifs aléatoires basés sur la difficulté
- 🔗 Partage via QR code ou code à 6 chiffres
- 🎮 Support de jeu de base et extensions
- 🏆 Système de scores et statistiques
- 📊 Backoffice d'administration complet

## 🚀 Installation

### Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web (Apache/Nginx)
- Extension PHP: PDO, PDO_MySQL

### Installation en local (avec Local by Flywheel)

1. **Placer les fichiers**
   ```
   Copier le dossier gang-de-monstres-standalone dans:
   C:\Users\[USER]\Local Sites\[SITE]\app\public\
   ```

2. **Configurer la base de données**

   Le fichier `config/database.php` détecte automatiquement l'environnement.

   Pour Local, il utilise automatiquement le wp-config.php parent si disponible.

   Sinon, modifier les valeurs dans le bloc "ENVIRONNEMENT LOCAL":
   ```php
   define('DB_HOST', 'localhost:10023');
   define('DB_NAME', 'local');
   define('DB_USER', 'root');
   define('DB_PASS', 'root');
   ```

3. **Installer la base de données**

   Option A - Via navigateur:
   ```
   http://gang-de-monstres.local/gang-de-monstres-standalone/includes/install.php?install=run
   ```

   Option B - Via CLI:
   ```bash
   php includes/install.php
   ```

4. **Accéder à l'application**
   ```
   http://gang-de-monstres.local/gang-de-monstres-standalone/public/
   ```

### Installation en production (Hostinger)

1. **Uploader les fichiers via Git**
   ```bash
   git clone https://github.com/DarkVelvet82/gangdemonstres.git
   ```

2. **Configurer la base de données**

   Modifier le fichier `config/database.php` avec vos identifiants Hostinger:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'votre_nom_de_base');
   define('DB_USER', 'votre_utilisateur');
   define('DB_PASS', 'votre_mot_de_passe');
   ```

3. **Installer la base de données**

   Accéder à: `https://votredomaine.com/includes/install.php?install=run`

4. **Sécuriser l'installation**

   Supprimer ou protéger le fichier install.php après installation.

## 📁 Structure du projet

```
gang-de-monstres-standalone/
├── api/                    # API REST
│   ├── game.php           # Endpoints jeu (créer, rejoindre, status)
│   ├── player.php         # Endpoints joueurs (générer objectif)
│   └── scores.php         # Endpoints scores et notifications
├── assets/
│   ├── css/               # Styles
│   │   └── objectif.css
│   ├── js/                # Scripts
│   │   ├── app-config.js
│   │   ├── objectif-main.js
│   │   ├── objectif-creation.js
│   │   ├── objectif-join.js
│   │   ├── objectif-objectives.js
│   │   ├── objectif-qr.js
│   │   ├── objectif-scores.js
│   │   └── objectif-status.js
│   └── uploads/           # Fichiers uploadés
├── config/
│   └── database.php       # Configuration BDD
├── includes/
│   ├── functions.php      # Fonctions utilitaires
│   └── install.php        # Installation BDD
├── public/
│   ├── index.php          # Page accueil
│   ├── creer-partie.php   # Créer une partie
│   ├── rejoindre.php      # Rejoindre une partie
│   ├── objectif.php       # Page objectif joueur
│   └── scores.php         # Tableau des scores
├── admin/                 # Backoffice (à venir)
├── .gitignore
├── .htaccess              # Routing Apache
└── README.md
```

## 🎮 Utilisation

### Créer une partie

1. Aller sur la page d'accueil
2. Cliquer sur "Créer une nouvelle partie"
3. Sélectionner le jeu de base et les extensions
4. Choisir la difficulté
5. Entrer le nombre de joueurs et leurs prénoms
6. Cliquer sur "Créer la partie"
7. Partager les codes QR ou codes à 6 chiffres

### Rejoindre une partie

1. Scanner le QR code OU
2. Aller sur "Rejoindre une partie"
3. Entrer le code à 6 chiffres
4. Générer son objectif

### Administration

Accéder au backoffice via `/admin/`

Identifiants par défaut:
- Utilisateur: `admin`
- Mot de passe: `admin123`

**⚠️ IMPORTANT: Changer le mot de passe après la première connexion!**

## 🔧 Configuration

### Ajouter des types d'objectifs

Via le backoffice:
1. Aller dans "Types"
2. Ajouter un nouveau type avec nom, emoji ou image
3. Sauvegarder

### Ajouter des jeux/extensions

Via le backoffice:
1. Aller dans "Jeux & Extensions"
2. Créer un nouveau jeu
3. Associer les types d'objectifs
4. Configurer les limites

### Configurer les difficultés

Via le backoffice:
1. Aller dans "Difficultés"
2. Définir les quantités min/max par difficulté
3. Définir le nombre de types par objectif

## 🔒 Sécurité

- Changer le mot de passe admin par défaut
- Protéger le dossier `/admin/` avec .htpasswd en production
- Vérifier les permissions des fichiers (755 pour dossiers, 644 pour fichiers)
- Activer HTTPS en production
- Nettoyer régulièrement les anciennes parties

## 🐛 Dépannage

### Erreur de connexion à la base de données

Vérifier:
- Les identifiants dans `config/database.php`
- Que la base de données existe
- Que l'utilisateur a les permissions nécessaires

### Les images/CSS ne se chargent pas

Vérifier:
- Le fichier `.htaccess` est présent
- La réécriture d'URL est activée (`mod_rewrite`)
- Les chemins dans les pages HTML

### Les requêtes AJAX échouent

Vérifier:
- Le fichier `assets/js/app-config.js` est chargé en premier
- Les URLs des endpoints dans `.htaccess`
- La console navigateur pour les erreurs

## 📝 Changelog

### Version 2.0.0 (2025)
- Migration depuis WordPress vers application standalone
- Refonte complète de l'architecture
- API REST native
- Système de routing amélioré

### Version 1.0 (WordPress Plugin)
- Version initiale sous forme de plugin WordPress

## 👥 Auteur

**TheoDumont**

## 📄 Licence

Usage privé - Gang de Monstres

## 🔗 Liens

- Repo GitHub: https://github.com/DarkVelvet82/gangdemonstres.git
- Site production: https://gangdemonstres.com/
