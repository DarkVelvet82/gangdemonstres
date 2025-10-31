# Migrations de base de données

## Comment exécuter les migrations

### Via le navigateur (recommandé)

Accédez à l'URL suivante dans votre navigateur:

**Local:**
```
http://gang-de-monstres.local/gang-de-monstres-standalone/db/run-migration.php
```

**Production:**
```
https://gangdemonstres.com/db/run-migration.php
```

### Les migrations disponibles

#### 001_remove_slug_column.sql
Supprime la colonne `slug` de la table `wp_objectif_types` car elle n'est plus utilisée dans la logique. On utilise maintenant uniquement l'ID des types.

## Notes importantes

- Les migrations sont exécutées dans l'ordre alphabétique
- Si une migration échoue, le script continue avec les suivantes
- Si une colonne a déjà été supprimée, le script l'indiquera sans erreur critique
