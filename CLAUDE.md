# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What is ChicBook

ChicBook is a **fashion talent marketplace** — a platform where models, photographers, stylists, videographers, dancers, and other fashion/image professionals create portfolio profiles ("books"), discover casting opportunities, and post collaborative projects. Think of it as a LinkedIn/Casting platform specifically for the French fashion industry.

---

## Running the project

The entire stack runs in Docker. All commands are run from the project root.

```bash
# Start all services (web + db + mail + adminer)
docker compose -f docker/compose.yml up -d

# Rebuild the PHP image after Dockerfile changes
docker compose -f docker/compose.yml up -d --build

# Stop everything
docker compose -f docker/compose.yml down

# View PHP/Apache logs
docker logs chicbook_web -f

# Run a SQL query directly
docker exec -it chicbook_db psql -U chicuser -d chicbook
```

**Service URLs once running:**
| Service | URL |
|---|---|
| App | http://localhost:8080 |
| Adminer (DB GUI) | http://localhost:5050 |
| Mailpit (email catcher) | http://localhost:8025 |
| Back office | http://localhost:8080/admin/ |

**Database credentials** (hardcoded in `config/database.php`):
- Host: `db` (Docker service name)
- DB: `chicbook`, User: `chicuser`, Password: `chicpassword`

**First-time setup:** The `sql/init.sql` file is automatically executed by PostgreSQL on first container boot. Always append migrations at the bottom of `sql/init.sql` — never rewrite existing statements.

**Apply a migration to a running DB:**
```bash
docker exec chicbook_db psql -U chicuser -d chicbook -c "ALTER TABLE ... "
```

**PowerShell file edits:** When using PowerShell to edit PHP files, always write without BOM:
```powershell
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($path, $content, $utf8NoBom)
```
Using `-Encoding UTF8` in PowerShell adds a BOM that breaks PHP `session_start()`.

---

## Architecture

### Stack
- **Backend:** PHP 8.2 (no framework) served by Apache
- **Database:** PostgreSQL 15 via PDO
- **Font:** Open Sans (Google Fonts, loaded via `@import` in `custom.css`)
- **Frontend:** Vanilla JS + Tailwind CSS (CDN, no build step)
- **Email:** PHP `mail()` routed to Mailpit via msmtp (dev only)

### File layout

```
/                        PHP pages (web-accessible at root)
├── admin/               Back office (login, dashboard, users, castings, events, portfolios)
├── config/database.php  Singleton PDO connection
├── controllers/         Business logic (AuthController)
├── models/              Data access: User, Profession, Portfolio
├── includes/
│   ├── header.php       Left sidebar nav — included on every page
│   └── photos_edit_grid.php  Drag-drop photo editor (shared by all 3 profil themes)
├── assets/
│   ├── css/custom.css   Sidebar styles + dark theme + Open Sans import + global body offset
│   ├── js/script.js     Shared JS: city autocomplete, measurements toggle
│   └── js/creer_casting.js  Dynamic profile cards + live preview for casting creation
├── sql/init.sql         Full schema + seed data + migrations (append-only)
└── docker/              Dockerfile + compose.yml
```

### How pages are structured

Every PHP page follows the same pattern:
1. `session_start()` + auth guard if needed
2. `require_once` the models/controllers needed
3. Handle POST (including AJAX POST at the top, before HTML output)
4. Output HTML with `<?php include 'includes/header.php'; ?>`
5. Load Tailwind CDN + `assets/css/custom.css` in `<head>`
6. Load `assets/js/script.js` before `</body>`

### Authentication flow

Login is two-factor: email/password → 6-digit code sent via `mail()` → verified at `verifier_code.php`. Session keys: `$_SESSION['user_id']` (set after 2FA), `$_SESSION['user_avatar']`. During 2FA: `$_SESSION['temp_user_id']` + `$_SESSION['temp_email']`.

**Remember me :** après 2FA réussi, un token 64 chars est généré (`bin2hex(random_bytes(32))`), stocké dans `users.remember_token`, et placé dans un cookie `chicbook_remember` (30 jours, HttpOnly). `header.php` vérifie ce cookie si `$_SESSION['user_id']` absent → restaure la session automatiquement. `logout.php` efface le token en DB et supprime le cookie.

Registration collects `prenom` + `nom` (combined into `full_name` in DB), `birth_date` (stored in `users` table, required for all). The mensurations sub-form (height, sizes, eye/hair color) is stored in `measurements` table and only shown for: Mannequin, Comédien, Danseur. Password validation: min 8 chars, at least one letter and one digit, all special characters allowed.

**Registration tags:** `inscription.php` shows clickable pill tags loaded from `expertise_tags_list` DB table (ORDER BY name ASC) — user clicks to toggle, stored comma-separated in `users.expertise_tags`. Implemented via `data-selected` attribute + inline styles (not Tailwind classes, to avoid escaping issues with querySelector). Tags managed from `admin/metiers.php`.

### Navigation — left sidebar (Instagram-style) + mobile bottom nav + mobile topbar

`includes/header.php` renders **trois éléments nav** :
- `#sidebar` — sidebar gauche desktop (position fixed, left 0)
- `#mobile-nav` — bottom bar mobile (position fixed, bottom 16px, liquid glass)
- `#mobile-topbar` — boutons Plus + Avatar fixés en haut à droite sur mobile

**Bascule CSS (sans JS de combat) :** `custom.css` + bloc `<style>` injecté dans `header.php` :
- Base (hors media query) : `#mobile-nav, #mobile-topbar { display: none; }` — `#mobile-topbar` est **toujours caché** (`display: none !important` dans la media query aussi), chaque page intègre ses propres boutons Plus/Avatar
- `@media (max-width: 768px)` : `display: flex !important` sur `#mobile-nav`, `display: none !important` sur `#sidebar` et `#mobile-topbar`
- Le JS dans `header.php` force uniquement `document.getElementById('sidebar').style.display = 'flex'` sur desktop — il ne combat plus le CSS mobile
- Les styles mobiles critiques sont injectés via `<style>` dans `header.php` (jamais mis en cache, toujours frais). Ne pas supprimer ce bloc.

**Desktop sidebar :**
- **Collapsed:** 80px wide (icons only)
- **Expanded:** 260px wide on hover — labels slide in with `translateX` + opacity transition
- `body { padding-left: 260px }` toujours fixé — sur mobile overridé à `padding-left: 0; padding-bottom: 90px`
- Active page détectée via `basename($_SERVER['PHP_SELF'])` → classe `active`

**Sidebar nav items (in order):**
1. Accueil → `index.php`
2. Trouver un talent → `trouver_talent.php`
3. Recherche → `recherche.php` (icône loupe)
4. Castings → `castings.php`
5. Messagerie → `messagerie.php`
6. Événements → `evenements.php`
7. *(spacer)*
8. **Plus** → `preferences.php` (icône engrenage)
9. Mon Profil / S'identifier → `profil.php` / `connexion.php` — classe `sidebar-mobile-hide` (cachés sur mobile via CSS)
10. Back Office → `admin/` — visible uniquement admins + desktop ≥1024px (JS `window.innerWidth`)

**Mobile bottom nav (`#mobile-nav`) :**
- 5 items : Accueil, Talents, Castings, Messages, Événements — **pas de Plus, pas de profil, pas de back office**
- Liquid glass : `backdrop-filter: blur(28px) saturate(200%)`, fond `rgba(10,10,10,0.70)`, bordure `rgba(255,255,255,0.09)`, coins 22px, flotte à 16px du bas
- Item actif en mauve `#d4a5d4` (classe `mnav-active` via PHP `$current_page`)
- SVGs avec `width="22" height="22"` attributs HTML obligatoires
- Light theme : fond `rgba(245,240,235,0.80)` avec shadow légère

**Mobile topbar (`#mobile-topbar`) :**
- **Toujours masqué** par `header.php` (`display: none !important` dans la media query mobile)
- Le HTML reste dans `header.php` mais n'est jamais affiché — chaque page intègre ses propres boutons `.mtop-btn` / `.mtop-avatar` directement dans sa row mobile
- **Intégration par page :**
  - `index.php` → `#feed-topbar-btns` inline dans la rangée filtre (via JS)
  - `trouver_talent.php` → dans la rangée dropdown catégorie (`.tt-cats-mobile`)
  - `castings.php` → à la fin de la `.cast-mobile-nav` (après le bouton +)
  - `evenements.php` → dans la rangée dropdown onglets (`.ev-tabs-mobile`)
  - `recherche.php` → `#rech-mobile-toprow` alignée à droite
  - `profil.php` → déjà dans `#profil-mobile-header`
  - Pages auth / formulaires / `preferences.php` / `messagerie.php` → non affichés

**Logo sidebar :** une seule `<img id="sidebar-logo-img">`. JS `mouseenter`/`mouseleave` sur `#sidebar` swap le `src` entre `assets/img/navicon.png` (collapsed, 52×52px) et `assets/img/logo.png` (expanded, height 44px auto-width) avec fondu opacity via `setTimeout(180ms)`.

**Viewport meta tag** : toutes les pages PHP doivent avoir `<meta name="viewport" content="width=device-width, initial-scale=1">` — sans ça, Safari iPhone rend à 980px et le CSS mobile ne se déclenche pas.

CSS class names that must exist: `#sidebar`, `.sidebar-item`, `.sidebar-icon`, `.sidebar-label`, `.sidebar-logo`, `.sidebar-divider`, `.sidebar-spacer`, `.sidebar-mobile-hide`, `#mobile-nav`, `.mnav-item`, `.mnav-active`, `#mobile-topbar`, `.mtop-btn`, `.mtop-avatar`, `.mtop-active`.

### Responsive mobile — stratégie par page

**Règle générale :** les fixes responsive sont injectés via un bloc `<style>` inline dans chaque page (jamais dans `custom.css` seul, qui peut être mis en cache). Classes CSS dédiées ajoutées sur les wrappers pour cibler précisément.

**Pattern filtres overlay (castings, trouver_talent, evenements) :**
- Aside filtres : `position: fixed; top: auto !important; bottom: 90px; left/right: 12px; z-index: 600; border-radius: 20px` — caché via `visibility: hidden; opacity: 0; transform: translateY(12px)` + transition, visible avec classe `.open`
- **Important :** `top: auto !important` obligatoire pour neutraliser le `top` Tailwind (ex. `sticky top-8`) qui sinon positionne le panel en haut même en `position:fixed`
- Backdrop `#cast-backdrop` / `#tt-backdrop` / `#ev-backdrop` : `position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(2px)` — `display: none` → `display: block` avec `.open`
- Fonctions JS `openXFilters()`/`closeXFilters()` + `document.body.style.overflow`
- Bouton "Filtres" : badge ● + bordure mauve si filtres actifs (`$has_active_X_filters`)
- Header du panel : titre "Filtres" + bouton ✕ `onclick="closeXFilters()"`
- Bouton "Appliquer les filtres" : `onclick="closeXFilters()"` avant soumission

**castings.php — navigation mobile :**
- `.cast-tabs-desktop` → `display: none !important` sur mobile
- `.cast-mobile-nav` → `display: flex` sur mobile : barre onglets arrondie + boutons Plus/Avatar à droite
  - **Opportunités** — toujours visible
  - **♡ Favoris** — toujours visible (compte affiché si > 0)
  - **Mes castings** — visible uniquement si `$has_my_castings` (`COUNT FROM castings WHERE user_id=:uid > 0`)
  - Bouton **+** circulaire mauve → `creer_casting.php`
- Item actif : `bg-[#d4a5d4] text-black`, inactif : `text-[#888]`

**trouver_talent.php — navigation mobile :**
- `.tt-cats-pills` → `display: none !important` sur mobile
- `.tt-cats-mobile` → rangée flex : dropdown catégories + boutons Plus/Avatar à droite
- Dessous : form GET avec input recherche par nom (`name="search"`, `u.full_name ILIKE`) + bouton Filtres compact — même row, `justify-content:flex-end` n'est pas utilisé, le champ prend `flex:1`
- Filtres : pattern overlay (backdrop `#tt-backdrop`, fonctions `openTTFilters`/`closeTTFilters`)

**evenements.php — navigation mobile :**
- `.ev-tabs` → `display: none !important` sur mobile
- `.ev-tabs-mobile` → rangée flex : dropdown "Tous/À venir/Mes inscriptions/Mes événements" + boutons Plus/Avatar à droite
- Dessous : row "Filtres" + "✕ Effacer" à gauche, "+ Proposer" à droite (`.ev-desktop-cta` masqué sur mobile)
- Filtres : pattern overlay (backdrop `#ev-backdrop`, fonctions `openEvFilters`/`closeEvFilters`)

**profil.php — header Instagram style mobile :**
- `#profil-mobile-header` : `display: none` base, `display: block !important` dans media query mobile
- Structure (tout centré via `text-align:center`) :
  1. Avatar 82px centré
  2. Nom + ligne "Profession · Ville" (mauve + gris, flex centré)
  3. Pills de tags (max 6, centrées)
  4. Boutons action liquid glass : `backdrop-filter:blur(16px)`, fond `rgba(255,255,255,0.08)`, border `rgba(255,255,255,0.13)`, border-radius 20px — **Photos** + **Modifier le profil** (owner) ou **Suivre** (`#follow-btn-mobile`) + **Contacter** (visiteur, accent mauve), + **Bio** si bio, + icône share. Compteur followers `#followers-count-mobile` sous les boutons si > 0.
  5. Séparateur fin `height:1px`
- Âge affiché uniquement si `show_age=TRUE` — stat "photos" supprimée
- Classes de masquage desktop sur mobile : `.profil-classique-aside`, `.profil-classique-name-row`, `.profil-editorial-hero`, `.profil-editorial-actions-row`, `.profil-editorial-tags-row`, `.profil-luxe-header`, `.profil-luxe-separator`
- Grille photos → 3 colonnes carrées `object-cover` (style Instagram) pour les 3 thèmes via classes `.profil-masonry-item`, `.profil-editorial-item`, `.profil-luxe-item` + `aspect-ratio: 1/1; overflow: hidden`
- Avatar : `$profile_data['profile_picture_url'] ?: $photos[0]['image_url']` (fallback première photo book)
- Bouton share appelle `doShare()` (fonction JS partagée, définie avant le listener `btn-share` desktop)
- `toggleEditMode()` synchronise les deux boutons photos : `#edit-photos-btn` (desktop) + `#edit-photos-btn-mob` (mobile header)

**Formulaires (creer_casting, creer_evenement, creer_projet) :**
- Wrapper principal → classe (ex. `cc-wrapper`) → padding réduit, margin-top 0
- Preview aside `creer_casting.php` → `.cc-preview { display: none !important }` sur mobile
- Grilles 2/3 cols → `.cev-grid-2`, `.cev-grid-3`, `.cp-grid-2`, `.cp-grid-3` → `1fr` sur mobile
- Mensurations dans cartes profil → `.cp-mensuration-grid` → conserve 2 col (assez compact)

**messagerie.php :**
- `html, body { height: calc(100% - 90px) }` sur mobile
- Layout mobile : vue liste (`#conv-list-panel`, pleine largeur) par défaut. Chat (`#chat-panel`) en `position:fixed; inset:0; transform:translateX(100%)` — slide-in avec classe `.open` quand une conversation est ouverte
- `body.chat-open #mobile-nav { display:none !important }` — nav bottom masquée en mode chat, restaurée au retour (`closeChatMobile()`)
- Bouton ← (`#msg-back-btn`, `display:none` base, `display:flex` mobile) pour revenir à la liste
- Bulles messages `.msg-bubble` → `max-width: 85%` + `word-break:break-word; overflow-wrap:anywhere` (évite le sidescroll sur URLs longues)

**recherche.php :**
- Grille résultats `#results-grid` → `repeat(2, 1fr)` sur mobile
- Filtres inline `.rech-inline-filters` → `flex-wrap: wrap` + font réduit

### Thème clair / sombre

Le site supporte deux thèmes. Le sombre est le défaut.

**Persistance :** cookie `chicbook_theme` (`light` ou `dark`, max-age 1 an) + `localStorage`. Le cookie est lu par PHP côté serveur.

**Application sans FOUC :** chaque page PHP lit `$_COOKIE['chicbook_theme']` et injecte `class="light"` directement sur le tag `<html>` avant toute feuille de style. Pattern sur toutes les pages :
```php
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
```
`header.php` injecte aussi un `<script>` pour appliquer la classe si le cookie est présent.

**CSS :** `assets/css/custom.css` contient une section `/* ===== LIGHT THEME ===== */` avec des overrides `html.light .bg-black`, `html.light .text-white`, etc. pour toutes les classes Tailwind sombres courantes. La palette claire est warm off-white (`#f2ede8`) avec cartes `#ffffff`/`#ece8e3`.

**Attention inline styles :** les overrides Tailwind dans `custom.css` ne couvrent que les classes CSS — pas les `style="background:#xxx"` inline. Pour les éléments avec inline styles sombres (ex: `messagerie.php` panels, widgets `index.php`), les overrides light theme sont dans un `<style>` block de la page concernée, ciblant les éléments par ID (`#conv-list-panel`, `#chat-header-bar`, `#chat-input-bar`, etc.) ou par classe ajoutée (`fashion-widget`, `events-widget`). Pattern : `html.light #element-id { background: #faf7f4 !important; }`

**Toggle :** `preferences.php` — toggle iOS-style qui sauvegarde cookie + localStorage puis recharge la page (`window.location.reload()`). Le rechargement est nécessaire pour que PHP applique `class="light"` sans flash — la mise à jour CSS instantanée via JS seul entrait en conflit avec l'injection de styles de Tailwind CDN.

- Card surfaces sombres : `#111` (page bg), `#1a1a1a` (cards), `#222` (nested)
- Card surfaces claires : `#ffffff`, `#ece8e3`, `#e8e3dd`

### Tailwind usage

No build step. Tailwind CDN is loaded on every page:
```html
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
```
Brand color: `#d4a5d4` (mauve/purple). Page background: `#000`. Card surface: `#111`/`#1a1a1a`.

### Database schema key points

- `users` — central table; `full_name`, `birth_date DATE`, `specific_profession`, `expertise_tags` (comma-separated), `login_code` (temp 2FA), `profile_theme VARCHAR(50) DEFAULT 'classique'`, `is_admin BOOLEAN DEFAULT FALSE`, `is_suspended BOOLEAN DEFAULT FALSE`, `show_age BOOLEAN DEFAULT FALSE`, `gender VARCHAR(50)`
- `profession_categories` — catégories de métiers gérées depuis le back office : `name`, `display_order`
- `professions` — lookup table with `name`, `category_id FK→profession_categories`, `has_measurements BOOLEAN DEFAULT FALSE`. `has_measurements=TRUE` on Mannequin/Comédien/Danseur (and any talent profession with physical criteria). `Profession::getAll()` returns `id, name, has_measurements`.
- `user_professions` — many-to-many to `professions` lookup (set at registration)
- `measurements` — one-to-one with `users`, physical talent types only: height, chest/waist/hip/shoe sizes, eye_color_id, hair_color_id, ethnicity_id (FKs to lookup tables)
- `castings` — has `casting_date DATE` (audition day) and `performance_date DATE` (realization day), `city`, `country`, `collaboration_type`, `cover_image`
- `casting_profiles` — multiple profiles per casting (role, quantity, age range, gender, measurements as min-max strings like "165 - 175", eye/hair/ethnicity FKs)
- `casting_favorites` — many-to-many: users save castings as favorites
- `portfolios` — images and YouTube videos per user. `image_url VARCHAR` (path under `uploads/`, nullable), `video_url VARCHAR(255)` (YouTube watch URL, nullable), `position INT` for ordering (ORDER BY position ASC, created_at DESC). An item is a photo if `image_url` is set, a video if `video_url` is set.
- `events` — title, type, organizer, city, country, event_date, cover_image, description, price, capacity, tags (comma-separated), user_id
- `event_registrations` — many-to-many: users register interest in events
- `conversations` — one row per pair of users; `user1_id = MIN(id)`, `user2_id = MAX(id)`, UNIQUE(user1_id, user2_id) prevents duplicates
- `messages` — `conversation_id`, `sender_id`, `content TEXT`, `image_url VARCHAR(255)` (nullable, chemin relatif upload), `is_read BOOLEAN DEFAULT FALSE`
- `reports` — signalements utilisateurs : `user_id` (nullable FK), `category VARCHAR(50)` (bug/contenu/compte/autre), `message TEXT`, `is_read BOOLEAN DEFAULT FALSE`, `created_at`
- `suggestions` — suggestions d'amélioration : `user_id` (nullable FK), `message TEXT`, `is_read BOOLEAN DEFAULT FALSE`, `created_at`
- `expertise_tags_list` — tags expertise gérés depuis le BO : `name VARCHAR(100) UNIQUE`, `display_order INT`. Chargés dans `inscription.php` et `edit_profil.php` via requête DB (ORDER BY name ASC).
- `users.remember_token VARCHAR(64)` — token remember me 30 jours, généré après 2FA, effacé au logout
- `users.password_reset_token VARCHAR(64)` — token reset mot de passe, valide 1h, NULL après utilisation
- `users.password_reset_expires TIMESTAMP` — expiry du token reset
- `projects` — projets créés par les utilisateurs : `user_id`, `title`, `project_type VARCHAR(100)`, `description`, `expected_date DATE`, `searched_profiles TEXT` (professions uniques comma-separated, calculé depuis required_profiles), `contact_name/email/phone`, `created_at`
- `required_profiles` — profils requis pour un projet : `project_id`, `role_name VARCHAR(100)` (nom de la profession), min/max height/age (INT), chest/waist/hip/shoe (VARCHAR), `eye_color_id`, `hair_color_id`, `ethnicity_id` — plusieurs lignes par projet, une par profil ajouté
- `follows` — système de suivi : `follower_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE`, `following_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE`, UNIQUE(follower_id, following_id)

When adding a column/table, append at the bottom of `sql/init.sql` and run the migration manually on the running container.

### Mot de passe oublié

Flux en deux pages :
- **`mot_de_passe_oublie.php`** : formulaire email → génère token 64 chars (`bin2hex(random_bytes(32))`), stocké dans `users.password_reset_token` + `users.password_reset_expires` (NOW() + 1h). Envoie email avec lien `reinitialiser_mdp.php?token=...`. Affiche toujours le message de succès (pas d'énumération d'emails). Comptes suspendus exclus.
- **`reinitialiser_mdp.php`** : vérifie token + expiry (`password_reset_expires > NOW()`). Formulaire nouveau mot de passe (min 8 chars, lettre + chiffre). Après save : `password_reset_token = NULL`, `password_reset_expires = NULL`. Lien invalide/expiré → bouton "Refaire une demande".
- Lien "Mot de passe oublié ?" ajouté sous le bouton Se connecter dans `connexion.php`.
- Colonnes DB : `users.password_reset_token VARCHAR(64)`, `users.password_reset_expires TIMESTAMP`.

### Notifications email castings

Déclenchées dans `creer_casting.php` après `$db->commit()`. Pour chaque profil (`casting_profiles`) du casting créé :
1. Requête `user_professions JOIN professions` pour trouver les users avec la même profession, hors créateur, hors suspendus.
2. Si `professions.has_measurements = TRUE` : filtre aussi par mensurations via `addMeasurementConditions()` — parse les plages VARCHAR (`"165 - 175"`, `"170+"`, `"< 180"`) avec `parseRange()`, génère des conditions SQL `(expr IS NULL OR expr >= :min)`. **Lénient** : un user sans mensuration renseignée passe quand même (ne pas rater un talent).
3. Critères exacts : `eye_color_id`, `hair_color_id`, `ethnicity_id`, `gender` — avec `OR IS NULL` pour être lénient.
4. Email envoyé via `sendCastingNotification()` : société, lieu, profil recherché + mensurations attendues, dates, lien `castings.php?view=offres&highlight=ID`.
5. `castings.php` : à `DOMContentLoaded`, si `?highlight=ID` présent → `scrollIntoView` + `openModal(card)` après 400ms.
- Fonctions : `parseRange(?string): [min|null, max|null]`, `addMeasurementConditions(array, array&, array&)`, `sendCastingNotification(array, array, array, string)`.

### castings.php — key behaviors

- Three tabs: **Opportunités** (castings seeking user's profession), **Favoris** (saved), **Mes Castings créés**
- **Mobile :** onglets `.cast-mobile-nav` (barre pill compacte), "Mes castings" tab conditionnel (`$has_my_castings` = COUNT query), bouton + circulaire mauve, filtres en overlay (voir section responsive)
- Filters are on the **right** of the castings list (not left)
- Filters (aside GET form): country → city (dependent dropdown, JS-filtered), date range on `performance_date`
- Favorite toggle is AJAX (`fetch` POST to same page with `toggle_favorite=1`) — no page reload
- Cards are clickable → modal overlay with full casting details including all profiles with their criteria
- Modal is populated from `data-casting` JSON attribute embedded on each card by PHP (`json_agg` with LEFT JOINs to eye/hair/ethnicity tables)
- Filter aside: `sticky top-8` (not `top-[100px]` — there is no top navbar)
- Requires login — redirects to `connexion.php` if not authenticated

### creer_casting.php — key behaviors

- Live preview card (right sidebar) updates as user types — role, city, description, company, dates, image
- Profession options in role select loaded from DB (`professions` table). Measurement inputs shown for roles with `has_measurements=TRUE` — list injected as `window.CHICBOOK_TALENT_PROFESSIONS` before `creer_casting.js` loads.
- Two date fields: `casting_date` (audition) + `performance_date` (realization)
- "Add profile" button clones the first `.profile-card` — relies on class names `profile-card`, `profile-card-header`, `role-selector`, `mensurations-grid`

### Avatar fallback — première photo du book

Partout où un avatar est affiché, si `users.profile_picture_url` est vide, la **première photo du book** (`portfolios ORDER BY position ASC, created_at DESC LIMIT 1`) est utilisée automatiquement. Pattern SQL :
```sql
(SELECT image_url FROM portfolios WHERE user_id=u.id ORDER BY position ASC, created_at DESC LIMIT 1) AS fallback_avatar
```
Rendu PHP : `$avatar = $user['profile_picture_url'] ?: $user['fallback_avatar']`. Appliqué dans : `index.php` (feed), `trouver_talent.php`, `messagerie.php` (bulles + header chat), `includes/header.php` (sidebar).

### index.php — feed

The homepage is a professional feed (not carousels). Structure:
- **Filter dropdown** at top: "Fil d'actualité · Tous les talents" — dropdown with profession options, JS filtering via `data-filter` on each `.feed-post`, no page reload. Closes on outside click.
- **Feed posts (real data):** sourced from `portfolios` table — **one post per user, the most recent photo**, sorted by `created_at DESC`. Uses `DISTINCT ON (po.user_id)` + PHP `usort`. Each post: avatar (avec fallback book) + name (clickable link to `profil.php?id=`), image in original format (no crop), expertise tags from `users.expertise_tags` (max 5), heart like button (client-side only). No caption field. `data-filter` is the normalized profession slug (lowercase, no accents/spaces via `iconv + preg_replace`).
- **Right column:** conditional by login state:
  - **Non connecté:** "Rejoindre ChicBook" CTA + widget "La mode en mouvement" + footer liens
  - **Connecté:** widget événements à venir (4 prochains depuis DB, date + titre + ville) + widget "La mode en mouvement" + footer liens. Le widget événements est masqué pour les non-connectés.
- **Footer right column:** petits liens style Instagram (À propos `target="_blank"`, Préférences, Castings, Événements, Trouver un talent) + copyright. `À propos` ouvre `apropos.php` dans un nouvel onglet.
- **Filter JS:** utilise `post.style.display = ''/'none'` (inline style direct) + `e.stopPropagation()` sur les options — évite les conflits CSS Tailwind et les interférences du listener document.
- **Welcome popup**: shown on first visit (non-logged-in users only), tracked via `localStorage('chicbook_visited')`. Contains "S'inscrire" CTA. Closes on ✕ or outside click.

### trouver_talent.php — key behaviors

- URL params: `?category=<slug>&profession=<name>&city=&country=&tag=&search=`
- **Catégories et professions chargées depuis la DB** : `profession_categories` JOIN `professions`. Les slugs d'URL sont générés depuis le nom de la catégorie (iconv + lowercase + tirets). Fallback "Tous les métiers" si DB vide.
- Top: category pills (white = active, desktop) / **dropdown `.tt-cats-mobile`** (mobile) + **profession dropdown** (same style as the feed filter on `index.php` — button shows `Category · Profession`, chevron rotates on open, closes on outside click)
- **Mobile :** catégories en dropdown `#tt-cats-menu` (coche sur actif), filtres en overlay avec backdrop `#tt-backdrop` (voir section responsive)
- Profiles displayed as **grille 3 colonnes** with portrait cards `aspect-[3/4]` — hover overlay affiche les mensurations pour les professions avec `has_measurements=TRUE`
- Filters on the **right**: mot-clé (**`<select>` dropdown** populated from all distinct `expertise_tags` values in DB, aggregated and sorted alphabetically), pays, ville
- **Filtres mensurations** (visibles uniquement si `has_measurements=TRUE`) : 5 plages min–max (taille, poitrine, tour de taille, hanches, pointure) + selects yeux/cheveux/ethnicité — appliqués dans la requête SQL via `WHERE m.height >= :hmin` etc.
- Results ordered by `RANDOM()`
- Query joins `measurements`, `eye_colors`, `hair_colors`, `ethnicities` to show physical data on hover

### recherche.php — key behaviors

- Page dédiée à la recherche de talents, accessible via la sidebar (icône loupe)
- **Barre centrée au chargement** (padding-top `28vh`), remonte à `40px` dès qu'une recherche est active (transition CSS)
- **Recherche dynamique** : debounce 350ms sur le texte, 400ms sur ville/mensurations — AJAX vers `?ajax=1` qui retourne JSON, rendu JS côté client
- **Filtres principaux** (en ligne) : profession, pays, ville, tag
- **Filtres mensurations** : apparaissent automatiquement (animation `max-height`) quand une profession avec `has_measurements=TRUE` est sélectionnée. 5 plages min–max (taille, poitrine, tour de taille, hanches, pointure) + 3 selects (yeux, cheveux, ethnicité)
- Cards identiques à `trouver_talent.php` : grille portrait `aspect-[3/4]`, overlay mensurations au hover
- Handler AJAX : `?ajax=1` retourne `{count, profiles[]}` — `age_range` calculé côté PHP avant sérialisation JSON

### profil.php — 3 themes

The profile page renders one of 3 layouts based on `users.profile_theme`:

- **`classique`** (default): left sidebar (profession, location, tags, mensurations cards, bio/share buttons) + masonry 3-col portfolio on the right
- **`editorial`**: full-width hero (first photo as background + dark gradient overlay, name in 6xl at bottom-left) + uniform 3-col square grid below (skips first photo used in hero)
- **`luxe`**: centered layout, name in 7xl, thin decorative divider with brand dot, 2-col grid (first photo spans full width as banner)

Theme is saved via `edit_profil.php?tab=theme` → `POST update_theme` (hidden input) → `User::updateTheme()`. Allowed values: `classique`, `editorial`, `luxe`.

**Mobile header (Instagram-style) :** `#profil-mobile-header` rendu une seule fois avant les blocs de thème, caché sur desktop. Remplace la sidebar/hero sur mobile avec : avatar 82px + stats (photos/ville/âge) + nom + profession + tags + boutons actions + séparateur. Voir section "Responsive mobile — profil.php" pour le détail complet.

**Tags "voir plus" :** max 3 tags visibles au chargement. Si plus de 3, un bouton `+N voir plus` apparaît. Clic → tous les tags s'affichent + bouton devient "voir moins". Variables PHP : `$tags_visible = array_slice($tags, 0, 3)`, `$tags_hidden = array_slice($tags, 3)`. Fonction JS `toggleTags(suffix)` — suffixes : `mob` (mobile header), `cl` (classique), `ed` (editorial), `lx` (luxe).

**Mensurations sur le profil :** affichées uniquement si `professions.has_measurements = TRUE` pour la profession de l'utilisateur. Données chargées depuis la table `measurements` via requête séparée après `getUserProfile()`. `getUserProfile()` retourne désormais `p.has_measurements`. Variable PHP `$measurements` (null si pas de profession à mensurations, ou pas de ligne en DB).
- **Desktop (thème classique)** : grille 2 colonnes de mini-cartes dans l'aside — label en gris, valeur en blanc. Classes CSS light-theme : `.cl-meas-card`, `.cl-meas-label`, `.cl-meas-value`.
- **Desktop (editorial/luxe)** : fonction `renderMeasurementsDesktop($measurements, $centered)` — inline dans la zone tags.
- **Mobile** : bouton **"Mensurations"** dans les boutons d'action du header (même style que "Bio") → ouvre `#measurements-modal` (sheet du bas, grille 2 colonnes, `border-radius: top only`).

**Bio button:** If `users.bio` is set, a "Biographie" pill button appears (all 3 themes) that opens a popup modal with the full bio text. If no bio, no button is shown (own profile shows "+ Ajouter une biographie" link in classique theme only).

**Bouton partager :** `doShare()` — fonction JS globale appelée depuis le header mobile ET depuis `btn-share` desktop. `navigator.share` si dispo, sinon `clipboard.writeText`. Feedback "✓ Copié !" pendant 3s.

**Système de suivi (Follow) :** `renderActions($is_own_profile, $profile_id, $is_following, $followers_count)` — bouton **Suivre / Suivi ✓** présent sur desktop (dans `renderActions`) et mobile (header `#profil-mobile-header`). Toggle AJAX POST `toggle_follow=1` + `target_id` → réponse `{ok, following, count}`. Compteur followers affiché inline (`#followers-count-desktop`, `#followers-count-mobile`). Si non connecté, redirige vers `connexion.php`. `$is_following` et `$followers_count` calculés en PHP avant le HTML via requêtes sur la table `follows`.

**Action buttons (owner only):** Two buttons rendered by `renderActions()` next to the profile name:
- **"Photos"** — toggles inline drag-drop edit mode (see below). `toggleEditMode()` synchronise `#edit-photos-btn` (desktop) et `#edit-photos-btn-mob` (mobile header).
- **"Gérer mon profil"** → `edit_profil.php` direct link.

**Edit profile modal:** `edit_profil.php` is opened via a direct `<a href="edit_profil.php">` link (pas d'iframe). Après un save réussi (`$message` set, pas d'erreur), `edit_profil.php` fait un `header("Location: profil.php")` redirect automatique.

**Projets (section en bas de toutes les pages profil) :** `profil.php` charge les projets du profil via `SELECT * FROM projects + LEFT JOIN required_profiles` (avec `json_agg`). Section affichée sous le book pour tous les visiteurs. Owner uniquement : bouton "＋ Nouveau projet" → `creer_projet.php`, bouton ✕ par carte → POST `delete_project`. Clic sur une carte → modal de détail (`#project-modal`) avec description, profils recherchés + leurs mensurations, contact.

**Aside classique — design :** sections séparées par des traits fins `#1e1e1e` (classe `.cl-aside-sep`). Tags en pills sobres fond `#161616`. Boutons Bio et Partager en rectangles arrondis `border-radius:10px` (pas `rounded-full`). Light theme : classes `.cl-tag-pill`, `.cl-meas-card`, `.cl-meas-label`, `.cl-meas-value`, `.cl-action-btn` — overridées dans le `<style>` inline de `profil.php` (pas dans `custom.css`) car les éléments ont des styles inline.

**Editorial theme fix:** Actions rendered **outside** the `overflow:hidden` hero container — placed in a separate `<div>` below the hero — so the dropdown is never clipped.

**Inline photo/video editing (`includes/photos_edit_grid.php`):** Toggled by the "Photos" button. Shows a uniform grid (auto-fill minmax 180px desktop, 3 colonnes fixes sur mobile) with:
- **Drag & drop reorder** — HTML5 dragstart/dragover/drop sur desktop ; touch events (touchstart/touchmove/touchend + clone flottant) sur mobile. Saves order via AJAX `photo_action=reorder` (JSON array of IDs → `portfolios.position`)
- **Delete ✕** — toujours visible sur mobile (opacity:1), hover-reveal sur desktop. Confirms then removes via AJAX `photo_action=delete` + `unlink()` on disk (only for photos; videos have no file on disk)
- **Photo add tile** — dashed border, clicks hidden `<input type="file" multiple>`, uploads via AJAX `photo_action=upload`, animates new tile in
- **Video add tile** — "Vidéo YouTube" dashed tile, opens inline modal with URL input → AJAX `photo_action=add_video`. PHP extracts YouTube ID via `extractYoutubeId()` (supports `watch?v=`, `youtu.be/`, `embed/`, `shorts/`). Returns `{ok, id, video_url, yt_id, thumb}`. Tile shows YouTube thumbnail + mauve play icon overlay.
- All AJAX calls POST to `profil.php?id=<profile_id>`, handled at the top of the file before HTML output
- Clicking "Terminer" or Échap exits edit mode and reloads the page to reflect new order
- **Fix mobile CSS :** `#photos-view.hidden { display: none !important }` nécessaire car le CSS mobile force `display: grid !important` sur `.profil-masonry` — sans ce override, les photos normales restent visibles sous la grille d'édition

**Vidéos YouTube dans le book :** les 3 thèmes affichent les vidéos avec thumbnail `https://img.youtube.com/vi/ID/hqdefault.jpg` + icône play (pas d'iframe dans la grille pour éviter les conflits click). Clic → lightbox avec `<iframe id="lightbox-iframe">` en `16:9`, autoplay. Fermer le lightbox vide `iframe.src` pour stopper la lecture. Bouton like masqué sur les items vidéo (`likeBtn.style.display='none'`). `extractYoutubeId()` est une fonction PHP globale définie en haut de `profil.php`.

**Portfolio management:** `Portfolio::getPhotos()` orders by `position ASC, created_at DESC`. `Portfolio::deletePhoto()` + `Portfolio::getPhotoById()` for ownership-checked deletion.

**PHP upload limits:** `docker/Dockerfile` sets `upload_max_filesize = 50M`, `post_max_size = 55M`, `memory_limit = 256M` via `/usr/local/etc/php/conf.d/uploads.ini`.

**2FA code copy-paste :** `verifier_code.php` écoute l'événement `paste` sur les 6 inputs → distribue automatiquement les chiffres dans chaque case. Backspace sur case vide → focus case précédente.

### edit_profil.php — tab system

`edit_profil.php` is a **tab-based settings panel** — no scroll on desktop, full scroll on mobile. Key design:

- **Layout desktop:** fixed sidebar (240px, `bg-[#111]`, classe `.ep-sidebar`) on the left + scrollable content area on the right (`.ep-content`). No `header.php` included.
- **Layout mobile:** sidebar masquée (`.ep-sidebar { display:none }`), `html/body` passe en `height:auto; overflow:auto`. Tous les onglets affichés à la suite avec titres de section `.ep-section-title` (labels uppercase). Liens "Retour au profil" + "Se déconnecter" en bas du scroll (`.ep-mobile-footer`). Barre sticky Annuler/Valider pleine largeur (`left:0; right:0` via classe `.ep-sticky-bar`).
- **`.ep-section-title` :** `display: none` par défaut (desktop) — visible uniquement sur mobile via `display: flex !important` dans la media query. **Ne pas supprimer le `display:none` du CSS base**, sinon les titres apparaissent en double sur desktop (bug corrigé).
- **`$tabs` et `$active_tab`** définis avant `<!doctype html>` (pas dans l'`<aside>`) pour être disponibles partout dans la page.
- **Tabs:** `infos`, `expertise`, `bio`, `mensurations`, `theme`, `securite` — stored in `?tab=` URL param so the correct tab is restored after a POST redirect.
- **Tab switching:** pure JS `switchTab(key)` — swaps `.tab-panel.active` et `.nav-item.active`. Sur mobile : `window.scrollTo(0,0)`.
- **Global sticky bar (`.ep-sticky-bar`):** `position:fixed; bottom:0; right:0` avec **"Annuler"** + **"Valider"**. Sur mobile : `left:0; right:0; border-radius:0`.
- **`infos` tab:** photo upload (`profile_pic`), nom complet, **date de naissance** (date input → `users.birth_date`), **genre** (4 radio pills: Homme/Femme/Non-binaire/Autre), ville, pays, **toggle switch "Afficher mon âge"** (`show_age`) — `multipart/form-data`, hidden `update_general=1`. Avatar previewed via `FileReader`. `User::updateGeneralInfo()` prend `$show_age`, `$gender`, `$birth_date`.
- **`expertise` tab:** tag pills (`data-selected` + inline style). Hidden `tags_string` mis à jour par `toggleEditTag()`. Hidden `update_expertise=1`. **Important :** `User::updateExpertise()` met à jour en même temps `users.specific_profession`, `users.expertise_tags`, ET `user_professions` (DELETE + INSERT par nom de profession). Sans ça, `$show_measurements` dans `edit_profil.php` lirait l'ancienne profession après un changement.
- **`mensurations` tab:** visible uniquement si `$show_measurements = true` (au moins une profession de l'utilisateur a `has_measurements=TRUE` dans `user_professions JOIN professions`). Taille, poitrine, taille, hanches, pointure (number inputs) + selects pour yeux/cheveux/ethnicité (lookup tables). `User::upsertMeasurements()` fait INSERT ou UPDATE selon l'existence de la ligne. Hidden `update_measurements=1`.
- **`bio`, `theme`, `securite` tabs:** même pattern hidden-input pour leurs handlers respectifs.
- **`update_password` guard:** PHP vérifie `!empty($_POST['current_password'])` pour éviter le déclenchement sur d'autres soumissions.
- **Age display:** `profil.php` calcule `$age` uniquement si `users.show_age = TRUE`.

### creer_projet.php — key behaviors

- Accessible depuis le bouton "＋ Nouveau projet" sur `profil.php` (owner uniquement)
- Formulaire en 3 sections : Informations principales, Profils recherchés, Contact
- **Profils recherchés — cartes dynamiques :** bouton "Ajouter un profil" clone une carte avec un `<select>` de professions. Plusieurs cartes du même métier autorisées. Bouton ✕ retire la carte, les numéros se réordonnent via JS `renumberCards()`.
- **Mensurations conditionnelles :** quand le select d'une carte vaut une profession avec `has_measurements=TRUE`, un bloc mensurations (`max-height` CSS transition) apparaît. Liste chargée depuis DB et injectée en JS via `window.CHICBOOK_TALENT_PROFESSIONS`.
- **Soumission PHP :** `profiles[]` tableau de cartes → INSERT dans `projects`, puis une ligne `required_profiles` par carte non-vide. `searched_profiles` (VARCHAR sur projects) = professions uniques jointes.
- Redirect vers `profil.php` après création.

### evenements.php — key behaviors

- Requires login — redirects to `connexion.php` if not authenticated
- **Four tabs:** **Tous**, **À venir** (`event_date >= CURRENT_DATE`), **Mes inscriptions** (JOIN on `event_registrations`), **Mes événements** (`e.user_id = current_user`)
- Registration toggle is AJAX (`toggle_registration=1`) — no page reload; updates button state and card badge
- Filters on the **right**: type (select from DB), ville (text ILIKE)
- Cards clickable → modal with full event details + "Je suis intéressé(e)" AJAX button
- Create button → `creer_evenement.php` (own form, image upload, redirects back to list)
- **"Mes événements" tab:** shows cards with extra "✎ Modifier" + "✕ Supprimer" buttons. Modifier opens an **edit modal** (`#edit-event-modal`) pre-filled via `eventsById[id]` JS object (built from PHP `array_column`). Form POSTs `update_event=1` + all fields + optional new cover image. Supprimer POSTs `delete_event=1` with confirmation. Both redirect to `?view=mes_creations`. Cover image is replaced on disk only if a new file is uploaded.

### messagerie.php — key behaviors

- Requires login — redirects to `connexion.php` if not authenticated
- **Layout desktop:** flex row — `#conv-list-panel` (360px, liste verticale) + `#chat-panel` (flex:1, chat). Sur mobile : liste plein écran par défaut, chat en slide-over `position:fixed`.
- **Liste de conversations (`#conv-list-panel`):** header "Messages" (sans "Demandes"). Chaque `.conv-row` : avatar 54px, nom (bold si non lu), preview dernier message tronqué, timestamp relatif, point mauve si non lu. Classe `.unread` sur la row si `$c['unread'] > 0`.
- **Ouverture depuis un profil:** `?with=USER_ID` → trouve ou crée la conversation (`MIN/MAX` pour garantir l'unicité), marque comme lue, charge les messages. Sur mobile ouvre directement le chat (slide-in + `body.chat-open`).
- **AJAX handlers** (POST `action=`):
  - `send` — insère un message texte et/ou image (`FormData`, pas `application/x-www-form-urlencoded`), retourne `id` + `created_at` + `image_url`
  - `poll` — retourne les messages `> last_id` (avec `image_url`), marque les reçus comme lus, retourne aussi la map `unread` par conv pour mettre à jour les badges
  - `open_conversation` — trouve ou crée une conv entre `$me` et `other_id`
- **Envoi d'images :** bouton 🖼 à gauche de la textarea → `<input type="file" accept="image/*">` caché. Preview au-dessus de la zone de saisie avec bouton ✕. Upload via `FormData`. Validation MIME + extension côté PHP. Fichier stocké dans `uploads/msg_<random>.ext`. Colonne `messages.image_url VARCHAR(255)`.
- **Rendu des images :** dans `renderMessage()` (JS) et le rendu PHP initial — image cliquable (`target="_blank"`), `max-width: 240px`. Si message texte + image : image en haut, texte en bas dans la bulle.
- **Polling:** `setInterval(poll, 2500)` sur la conv active
- **Envoi:** Entrée (sans Shift) ou bouton. Textarea auto-resize via JS.
- **Rows JS:** `data-conv-id`, `data-other-id`, `data-name`, `data-avatar`, `data-profession` sur chaque `.conv-row`. Listener `click` délégué en JS.
- **URL:** `history.replaceState` vers `?conv=ID` lors du changement de conversation (sans rechargement).

### preferences.php — contenu

Renommée "Plus" dans la sidebar (icône engrenage inchangée). Quatre sections dans cet ordre :
- **Apparence** — toggle thème clair/sombre (iOS-style, reload page)
- **Signaler un problème** — select catégorie (bug/contenu/compte/autre) + textarea → INSERT dans `reports`. Confirmation visuelle (`$report_success`) après envoi.
- **Suggérer une amélioration** — textarea libre → INSERT dans `suggestions`. Confirmation visuelle (`$suggestion_success`) après envoi.
- **Compte** — bouton "Se déconnecter" → `logout.php` (tout en bas)

### Scroll-to-top button

Bouton flottant `position:fixed; bottom:28px; right:28px; z-index:9999` injecté à la fin de `includes/header.php` (donc présent sur toutes les pages qui incluent header). Également dupliqué dans `apropos.php` (standalone). Apparaît après 300px de scroll (opacity + translateY animation). IIFE isolée — aucun variable globale.

### apropos.php — structure

Page standalone (pas de `header.php`, pas de `body { padding-left: 260px }`). Sections :
1. Hero plein-écran : eyebrow animé, titre `clamp`, CTA
2. Marquee défilant CSS des 14 métiers
3. Stats bar 3 colonnes avec compteurs JS animés (easeOut cubic)
4. Section intro centrée
5. 3 piliers alternés (image + texte, scroll-reveal `.reveal-left`/`.reveal-right` via IntersectionObserver)
6. CTA final + bouton "Revenir sur ChicBook" → `index.php`

### Back office (`admin/`)

Accessible at `/admin/`. Protected by `auth_guard.php` which checks `$_SESSION['is_admin']`.

**Lien depuis le site :** visible dans la sidebar uniquement pour les admins (`is_admin=TRUE`), uniquement sur desktop (≥1024px, injecté via JS `window.innerWidth`).

- `login.php` — standalone login page, checks `users.is_admin = TRUE`
- `index.php` — dashboard: 8 stat cards (users, castings, projets, événements, signalements non lus, portfolios, métiers, nouveaux 30j). Quand des signalements non lus existent, ils remplacent le widget castings dans la 2e colonne.
- `utilisateurs.php` — paginated user list, search by name/email/profession, actions: **Contacter** (modal email → `mail()`), suspend/reactivate, toggle admin, **delete** (modale raison obligatoire → email utilisateur avant DELETE)
- `castings.php` — paginated casting list, search, delete
- `projets.php` — paginated project list (titre, auteur, type, nb profils, date prévue), search, delete
- `portfolios.php` — photo grid (5-col), hover-reveal **delete** (modale raison obligatoire → email utilisateur avant DELETE + `unlink()`)
- `evenements.php` — event list, search, delete
- `signalements.php` — tabs Signalements / Suggestions. Badges non lus. Marquer lu / tout marquer lu / répondre par email (modal) / supprimer.
- `metiers.php` — gestion catégories (`profession_categories`) + professions (`professions`) + **tags expertise** (`expertise_tags_list`). Catégories et tags dans la colonne gauche (flex-col gap-6), métiers dans la colonne droite. Modals ajouter/modifier pour chaque entité. Toggle "a des mensurations" par profession. Tags classés alphabétiquement.
- `sidebar.php` — shared 220px fixed left sidebar. Charge les counts non lus depuis DB (`reports` + `suggestions`). Badge rouge sur "Signalements" si non lus.

To grant admin to a user: `UPDATE users SET is_admin = TRUE WHERE email = 'xxx';`

### Tags expertise

Les tags sont stockés dans `expertise_tags_list` (table DB) et gérés depuis `admin/metiers.php`. 60 tags seedés par défaut. `inscription.php` et `edit_profil.php` chargent les tags via `SELECT name FROM expertise_tags_list ORDER BY name ASC` — plus de tableau hardcodé.

**Liste pays `inscription.php` :** 196 `<option>` générées directement en PHP dans le HTML — **pas de JS**. La liste est un tableau PHP inline dans `inscription.php`. Ne pas revenir à une approche JS (causait des bugs selon l'ordre d'exécution des scripts).

### Suppression de compte

Dans `edit_profil.php` onglet Sécurité — bouton "Supprimer mon compte" ouvre une **modale** avec double saisie du mot de passe. Côté PHP : vérifie que les deux mots de passe correspondent + `password_verify` contre le hash DB. Si OK : supprime les fichiers disque (photos book + avatar), `DELETE FROM users` (CASCADE supprime le reste), `session_destroy()`, redirect `index.php`.

### Internationalisation (i18n)

**Fichier :** `config/i18n.php` — chargé automatiquement par `includes/header.php` via `if (!function_exists('t')) require_once`. Toutes les pages principales ajoutent aussi `require_once 'config/i18n.php'` en haut pour avoir `t()` disponible avant le header.

**Langues supportées :** Français (`fr`), Anglais (`en`), Espagnol (`es`). Défaut : `fr`.

**Persistance :** cookie `chicbook_lang` (1 an). Changé depuis `preferences.php` (POST `set_lang` → reload).

**Utilisation :** `<?= t('nav.home') ?>` dans le HTML, `t('key')` dans les attributs PHP (`placeholder="<?= t('auth.email') ?>"`).

**Structure des clés :** préfixées par domaine — `nav.*`, `auth.*`, `register.*`, `feed.*`, `profile.*`, `castings.*`, `events.*`, `prefs.*`, `common.*`, `talents.*`. Voir `config/i18n.php` pour la liste complète (~90 clés).

**Ajouter une clé :** ajouter à la fin de la section appropriée dans `config/i18n.php` avec les 3 langues : `'my.key' => ['fr'=>'...', 'en'=>'...', 'es'=>'...'],`

### Système de likes photos

**Table :** `photo_likes` (`user_id`, `photo_id`, UNIQUE) — référence `portfolios(id)` ON DELETE CASCADE.

**Feed (`index.php`) :** chaque carte post a un bouton like avec `data-photo-id`, `data-liked`, `data-require-login`. Toggle via AJAX POST `action=toggle_like` → retourne `{ok, liked, count}`. Mise à jour optimiste + rollback en cas d'erreur. Redirige vers `connexion.php` si non connecté.

**Lightbox profil (`profil.php`) :** bouton like `#lightbox-like-btn` dans le footer du lightbox. Même logique AJAX via `PROFILE_PAGE_URL` + handler `toggle_photo_like`. `allPhotos` JS enrichi avec `likes_count` + `user_liked`.

### Sécurité — correctifs appliqués (audit 2026-06-12)

**Corrigé (critique/haute) :**
- `verifier_code.php` + `admin/login.php` : `session_regenerate_id(true)` après authentification
- `connexion.php` : validation email via `filter_var(FILTER_VALIDATE_EMAIL)` avant `mail()`
- `messagerie.php` : XSS corrigé — escape `&` en premier, puis `<>`, via `.replace()` en JS avant injection `innerHTML`
- `profil.php` + `edit_profil.php` : validation MIME uploads via `getimagesize()` + whitelist `['image/jpeg','image/png','image/gif','image/webp']`
- `profil.php` + `edit_profil.php` : noms de fichiers imprévisibles via `bin2hex(random_bytes(12))`
- `edit_profil.php` : CSRF token sur `delete_account` + `update_password` (`$_SESSION['csrf_token']`, vérification avec `goto skip_post` en cas d'échec)
- `controllers/AuthController.php` : vérification âge minimum 16 ans côté PHP (`DateTime::diff`)
- `includes/header.php` : remember-me token stocké en SHA256 en DB (`hash('sha256', $token)`), cookie conserve le token brut

**Corrigé (moyenne/faible) :**
- `config/database.php` : `die($e->getMessage())` remplacé par `error_log` + message générique
- `.htaccess` : headers sécurité `X-Frame-Options SAMEORIGIN`, `X-Content-Type-Options nosniff`, `Referrer-Policy`, `Permissions-Policy`, `ServerSignature Off`

**Restant :**
- Pas de CSRF sur la majorité des autres formulaires POST
- Pas de rate limiting sur login / 2FA / reset mot de passe
- Pas de Content-Security-Policy

### Back office — suppressions avec raison obligatoire

`admin/portfolios.php` et `admin/utilisateurs.php` : la suppression ouvre une modale (`#modal-delete-photo` / `#modal-delete-user`) avec un textarea `required`. Si une raison est saisie, un email est envoyé à l'utilisateur avant le DELETE. Format : `From: admin@chicbook.fr`.

### External APIs used

- **City autocomplete:** `https://geocoding-api.open-meteo.com/v1/search` — debounced 300ms, min 3 chars

### File uploads

Uploaded files go to `uploads/` (gitignored). Path relative to webroot stored in DB. Filename format: `bin2hex(random_bytes(12)) . '.' . $ext` (imprévisible). Validation : extension whitelist + `getimagesize()` MIME check avant `move_uploaded_file()`.

---

## Pages reference

| File | Purpose |
|---|---|
| `index.php` | Homepage: feed + dropdown filter + right column 3 blocs + first-visit popup. **Likes photos AJAX** (table `photo_likes`, toggle optimiste, count par photo) |
| `inscription.php` | Registration: prenom+nom, birth_date (max = il y a 16 ans, bloqué JS **et PHP**), clickable tag pills, conditional mensurations. **Liste pays en PHP** (196 `<option>` inline, pas de JS) |
| `config/i18n.php` | Système de traduction FR/EN/ES. Fonction `t(string $key)`. Cookie `chicbook_lang`. ~90 clés réparties par domaine |
| `connexion.php` | Login step 1 (email + password) + lien "Mot de passe oublié ?" |
| `verifier_code.php` | Login step 2 (2FA code entry) |
| `mot_de_passe_oublie.php` | Formulaire email → envoi lien reset (token 1h) |
| `reinitialiser_mdp.php` | Saisie nouveau mot de passe via token |
| `profil.php` | Public talent profile — 3 themes, tags "voir plus" (max 3 visibles), mensurations desktop (mini-cartes) + mobile (modal sheet), bio popup, follow system, followers count, section projets |
| `edit_profil.php` | Settings: avatar, general info, expertise tags (sync user_professions), bio, mensurations (si has_measurements), theme picker, password, **suppression de compte** (modale double mdp) — desktop: sidebar + tabs ; mobile: scroll unique toutes sections |
| `creer_projet.php` | Create project: title, type, date, description, dynamic profile cards (profession select + mensurations conditionnelles pour Mannequin/Comédien/Danseur), contact |
| `castings.php` | Browse/filter castings (filters on right), favorites, own castings, modal detail view |
| `creer_casting.php` | Create casting: multi-profile builder with ranges (tous facultatifs), two dates, live preview, notifications email aux talents correspondants (avec filtrage mensurations) |
| `edit_casting.php` | Edit existing casting — **not yet migrated to Tailwind** (still uses `src/` CSS) |
| `trouver_talent.php` | Browse talents by category + profession, grid portrait cards, hover overlay mensurations, filters on right |
| `recherche.php` | Recherche talents full-text + filtres inline + filtres mensurations (Mannequin/Danseur/Comédien), AJAX dynamique debounced |
| `messagerie.php` | Messagerie temps réel — liste conversations (style iMessage) à gauche/plein écran mobile, chat à droite/slide-over mobile, polling AJAX 2.5s, `?with=USER_ID` ouvre/crée une conv. **Envoi d'images** (bouton 🖼, preview, upload `FormData`, colonne `messages.image_url`) |
| `evenements.php` | Events: tabs, card grid, right filters, AJAX registration toggle, modal — login required |
| `creer_evenement.php` | Create event: title, type, organizer, city/country, date, price, capacity, description, tags, image |
| `preferences.php` | Plus : toggle thème + **sélecteur de langue FR/EN/ES** (cookie `chicbook_lang`) + signaler un problème (`reports`) + suggérer une amélioration (`suggestions`) + déconnexion |
| `apropos.php` | Page À propos **standalone** (sans sidebar, sans `header.php`) — ouverte en `target="_blank"`. Hero animé, marquee métiers, stats compteurs, 3 sections piliers avec scroll-reveal, bouton "Revenir sur ChicBook" en bas |
| `logout.php` | Destroys session, redirects to index |
| `admin/` | Back office: dashboard, users, castings, projets, portfolios, events, signalements, métiers — requires `is_admin`. Lien discret dans sidebar front (desktop + admin only). |

> **Note:** `edit_casting.php` still references `src/style.css` and `src/edit_casting.css` — not yet migrated to Tailwind.
