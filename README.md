# ChicBook

Plateforme de mise en relation pour les professionnels de la mode et de l'image — mannequins, photographes, stylistes, vidéastes, danseurs, coiffeurs, maquilleurs et plus encore.

---

## Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installé et en cours d'exécution
- [Git](https://git-scm.com/) pour cloner le dépôt

Aucun autre outil n'est nécessaire (PHP, PostgreSQL et Apache sont inclus dans Docker).

---

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/ryukimir/ChicBook.git
cd ChicBook
```

### 2. Lancer l'application

```bash
docker compose -f docker/compose.yml up -d
```

La première fois, Docker va :
- Télécharger les images nécessaires (PHP, PostgreSQL, Adminer, Mailpit)
- Construire le conteneur PHP/Apache
- Créer la base de données et exécuter automatiquement `sql/init.sql` (schéma + données de départ)

> **Note :** La base de données est initialisée automatiquement uniquement lors du **premier démarrage** à partir d'un volume vide. Si vous avez déjà lancé le projet auparavant, le fichier `init.sql` ne sera pas réexécuté.

### 3. Vérifier que tout fonctionne

| Service | URL | Description |
|---|---|---|
| Application | http://localhost:8080 | Site principal |
| Adminer | http://localhost:5050 | Interface graphique base de données |
| Mailpit | http://localhost:8025 | Capture des emails (dev) |
| Back Office | http://localhost:8080/admin/ | Administration du site |

---

## Créer un compte et devenir administrateur

### Étape 1 — S'inscrire

Rendez-vous sur http://localhost:8080/inscription.php et créez votre compte (prénom, nom, email, mot de passe, date de naissance, profession).

### Étape 2 — Se connecter et valider le code 2FA

Connectez-vous sur http://localhost:8080/connexion.php.  
Un code de vérification à 6 chiffres est envoyé par email. En développement, consultez-le directement sur **Mailpit** : http://localhost:8025

### Étape 3 — Passer le compte en administrateur

Une fois votre compte créé, ouvrez un terminal et exécutez la commande suivante en remplaçant l'email par le vôtre :

```bash
docker exec -it chicbook_db psql -U chicuser -d chicbook -c "UPDATE users SET is_admin = TRUE WHERE email = 'votre@email.com';"
```

### Étape 4 — Accéder au back office

Reconnectez-vous sur le site (ou rechargez la page), puis rendez-vous sur http://localhost:8080/admin/

> Le lien **Back Office** apparaît aussi dans la sidebar gauche du site principal (uniquement sur desktop et pour les admins).

---

## Commandes utiles

```bash
# Démarrer les services
docker compose -f docker/compose.yml up -d

# Arrêter les services
docker compose -f docker/compose.yml down

# Reconstruire l'image PHP après modification du Dockerfile
docker compose -f docker/compose.yml up -d --build

# Voir les logs Apache/PHP en temps réel
docker logs chicbook_web -f

# Ouvrir la console PostgreSQL
docker exec -it chicbook_db psql -U chicuser -d chicbook

# Exécuter une requête SQL directement
docker exec chicbook_db psql -U chicuser -d chicbook -c "SELECT * FROM users;"

# Ouvrir un shell dans le conteneur PHP
docker exec -it chicbook_web bash
```

---

## Réinitialiser complètement la base de données

Pour repartir de zéro et supprimer toutes les données :

```bash
docker compose -f docker/compose.yml down -v
docker compose -f docker/compose.yml up -d
```

L'option `-v` supprime le volume Docker. Au redémarrage, `sql/init.sql` est réexécuté automatiquement et la base est recréée depuis zéro.

---

## Identifiants de la base de données

| Paramètre | Valeur |
|---|---|
| Hôte (depuis Docker, dans le code) | `db` |
| Hôte (depuis votre machine) | `localhost` |
| Port | `5432` |
| Base de données | `chicbook` |
| Utilisateur | `chicuser` |
| Mot de passe | `chicpassword` |

Vous pouvez aussi parcourir la base visuellement via **Adminer** : http://localhost:5050  
Système : `PostgreSQL` — Serveur : `db` — Utilisateur : `chicuser` — Mot de passe : `chicpassword` — Base de données : `chicbook`

---

## Emails en développement

Tous les emails (codes 2FA, réinitialisation de mot de passe, notifications de castings…) sont interceptés par **Mailpit** et n'arrivent jamais en boîte réelle.

Consultez les emails capturés sur : http://localhost:8025

---

## Structure du projet

```
/
├── admin/                   Back office (dashboard, users, castings, events…)
├── assets/
│   ├── css/custom.css       Styles globaux + thème clair/sombre
│   └── js/                  Scripts partagés (autocomplete, casting live preview…)
├── config/
│   ├── database.php         Connexion PDO (singleton)
│   └── i18n.php             Traductions FR / EN / ES
├── controllers/             Logique métier (AuthController)
├── docker/                  Dockerfile + compose.yml
├── includes/
│   ├── header.php           Sidebar navigation (incluse sur toutes les pages)
│   └── photos_edit_grid.php Éditeur drag-and-drop du book portfolio
├── models/                  Accès aux données (User, Portfolio, Profession)
├── sql/
│   └── init.sql             Schéma complet + seed data + toutes les migrations
├── uploads/                 Fichiers uploadés par les utilisateurs (gitignored)
└── *.php                    Pages de l'application
```

---

## Fonctionnalités

- Inscription et connexion avec authentification à deux facteurs (2FA par email)
- Book portfolio : photos et vidéos YouTube/Dailymotion, drag-and-drop, likes
- 3 thèmes de profil : Classique, Éditorial, Luxe
- Castings : créer et parcourir des offres, filtres avancés, favoris, notifications email automatiques aux talents correspondants
- Événements : création, inscription, filtres par type et ville
- Trouver un talent : grille par catégorie/profession, filtres mensurations (taille, mensurations, yeux, cheveux, origine)
- Recherche : full-text dynamique avec filtres inline
- Messagerie : conversations en temps réel, envoi d'images
- Projets collaboratifs : création avec profils recherchés
- Thème clair / sombre
- Interface disponible en français, anglais et espagnol
- Back office complet : gestion des utilisateurs, castings, portfolios, événements, signalements, métiers et tags

---

## Ajouter une migration SQL

Les modifications de schéma doivent **toujours** être ajoutées à la fin de `sql/init.sql` (ne jamais modifier les instructions existantes), puis appliquées sur le conteneur en cours d'exécution :

```bash
docker exec chicbook_db psql -U chicuser -d chicbook -c "ALTER TABLE ma_table ADD COLUMN IF NOT EXISTS ma_colonne VARCHAR(255);"
```
