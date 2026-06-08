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

**Registration tags:** `inscription.php` shows clickable pill tags (expertise keywords) — user clicks to toggle, stored comma-separated in `users.expertise_tags`. Implemented via `data-selected` attribute + inline styles (not Tailwind classes, to avoid escaping issues with querySelector).

### Navigation — left sidebar (Instagram-style)

`includes/header.php` renders a fixed left sidebar (`#sidebar`). There is **no top navbar**.

- **Collapsed:** 80px wide (icons only)
- **Expanded:** 260px wide on hover — labels slide in with `translateX` + opacity transition
- `body { padding-left: 260px }` is **always fixed** — the sidebar zone is permanently reserved so expanding never overlaps content
- Active page detected via `basename($_SERVER['PHP_SELF'])` → `active` class on the matching item
- Item padding: `10px 18px`

**Sidebar nav items (in order):**
1. Accueil → `index.php`
2. Trouver un talent → `trouver_talent.php`
3. Recherche → `recherche.php` (icône loupe)
4. Castings → `castings.php`
5. Messagerie → `messagerie.php`
6. Événements → `evenements.php`
7. *(spacer)*
8. **Plus** → `preferences.php` (icône engrenage) — label affiché est "Plus", pas "Préférences"
9. Mon Profil / S'identifier → `profil.php` / `connexion.php`

"À propos" et "Déconnexion" ont été retirés de la sidebar. La déconnexion sera dans les paramètres plus tard.

**Logo sidebar :** une seule `<img id="sidebar-logo-img">`. JS `mouseenter`/`mouseleave` sur `#sidebar` swap le `src` entre `assets/img/navicon.png` (collapsed, 52×52px) et `assets/img/logo.png` (expanded, height 44px auto-width) avec fondu opacity via `setTimeout(180ms)`.

CSS class names that must exist in the HTML for the sidebar to work: `#sidebar`, `.sidebar-item`, `.sidebar-icon`, `.sidebar-label`, `.sidebar-logo`, `.sidebar-divider`, `.sidebar-spacer`.

### Thème clair / sombre

Le site supporte deux thèmes. Le sombre est le défaut.

**Persistance :** cookie `chicbook_theme` (`light` ou `dark`, max-age 1 an) + `localStorage`. Le cookie est lu par PHP côté serveur.

**Application sans FOUC :** chaque page PHP lit `$_COOKIE['chicbook_theme']` et injecte `class="light"` directement sur le tag `<html>` avant toute feuille de style. Pattern sur toutes les pages :
```php
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
```
`header.php` injecte aussi un `<script>` pour appliquer la classe si le cookie est présent.

**CSS :** `assets/css/custom.css` contient une section `/* ===== LIGHT THEME ===== */` avec des overrides `html.light .bg-black`, `html.light .text-white`, etc. pour toutes les classes Tailwind sombres courantes. La palette claire est warm off-white (`#f2ede8`) avec cartes `#ffffff`/`#ece8e3`.

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
- `user_professions` — many-to-many to `professions` lookup (set at registration)
- `measurements` — one-to-one with `users`, physical talent types only: height, chest/waist/hip/shoe sizes, eye_color_id, hair_color_id, ethnicity_id (FKs to lookup tables)
- `castings` — has `casting_date DATE` (audition day) and `performance_date DATE` (realization day), `city`, `country`, `collaboration_type`, `cover_image`
- `casting_profiles` — multiple profiles per casting (role, quantity, age range, gender, measurements as min-max strings like "165 - 175", eye/hair/ethnicity FKs)
- `casting_favorites` — many-to-many: users save castings as favorites
- `portfolios` — images per user, path under `uploads/`, `position INT` for ordering (ORDER BY position ASC, created_at DESC)
- `events` — title, type, organizer, city, country, event_date, cover_image, description, price, capacity, tags (comma-separated), user_id
- `event_registrations` — many-to-many: users register interest in events
- `conversations` — one row per pair of users; `user1_id = MIN(id)`, `user2_id = MAX(id)`, UNIQUE(user1_id, user2_id) prevents duplicates
- `messages` — `conversation_id`, `sender_id`, `content TEXT`, `is_read BOOLEAN DEFAULT FALSE`
- `reports` — signalements utilisateurs : `user_id` (nullable FK), `category VARCHAR(50)` (bug/contenu/compte/autre), `message TEXT`, `is_read BOOLEAN DEFAULT FALSE`, `created_at`
- `suggestions` — suggestions d'amélioration : `user_id` (nullable FK), `message TEXT`, `is_read BOOLEAN DEFAULT FALSE`, `created_at`
- `users.remember_token VARCHAR(64)` — token remember me 30 jours, généré après 2FA, effacé au logout

When adding a column/table, append at the bottom of `sql/init.sql` and run the migration manually on the running container.

### castings.php — key behaviors

- Three tabs: **Opportunités** (castings seeking user's profession), **Favoris** (saved), **Mes Castings créés**
- Filters are on the **right** of the castings list (not left)
- Filters (aside GET form): country → city (dependent dropdown, JS-filtered), date range on `performance_date`
- Favorite toggle is AJAX (`fetch` POST to same page with `toggle_favorite=1`) — no page reload
- Cards are clickable → modal overlay with full casting details including all profiles with their criteria
- Modal is populated from `data-casting` JSON attribute embedded on each card by PHP (`json_agg` with LEFT JOINs to eye/hair/ethnicity tables)
- Filter aside: `sticky top-8` (not `top-[100px]` — there is no top navbar)
- Requires login — redirects to `connexion.php` if not authenticated

### creer_casting.php — key behaviors

- Live preview card (right sidebar) updates as user types — role, city, description, company, dates, image
- Measurement inputs for Mannequin/Comédien/Danseur roles use **min → max range pairs** (stored as "165 - 175" strings in `VARCHAR` columns)
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

- URL params: `?category=creation-design|image-production|marques-createurs&profession=Styliste&city=&country=&tag=`
- Three categories with their professions:
  - `creation-design`: Styliste, Modéliste, Designer, Illustrateur, Directeur artistique
  - `image-production`: Photographe, Vidéaste, Mannequin, Maquilleur, Coiffeur
  - `marques-createurs`: Marque, Créateur, Agence, Casting director
- Top: category pills (white = active) + **profession dropdown** (same style as the feed filter on `index.php` — button shows `Category · Profession`, chevron rotates on open, closes on outside click)
- Profiles displayed as **grille 3 colonnes** with portrait cards `aspect-[3/4]` — hover overlay affiche les mensurations pour Mannequin/Danseur/Comédien
- Filters on the **right**: mot-clé (**`<select>` dropdown** populated from all distinct `expertise_tags` values in DB, aggregated and sorted alphabetically), pays, ville
- **Filtres mensurations** (visibles uniquement pour Mannequin/Danseur/Comédien) : 5 plages min–max (taille, poitrine, tour de taille, hanches, pointure) + selects yeux/cheveux/ethnicité — appliqués dans la requête SQL via `WHERE m.height >= :hmin` etc.
- Results ordered by `RANDOM()`
- Query joins `measurements`, `eye_colors`, `hair_colors`, `ethnicities` to show physical data on hover

### recherche.php — key behaviors

- Page dédiée à la recherche de talents, accessible via la sidebar (icône loupe)
- **Barre centrée au chargement** (padding-top `28vh`), remonte à `40px` dès qu'une recherche est active (transition CSS)
- **Recherche dynamique** : debounce 350ms sur le texte, 400ms sur ville/mensurations — AJAX vers `?ajax=1` qui retourne JSON, rendu JS côté client
- **Filtres principaux** (en ligne) : profession, pays, ville, tag
- **Filtres mensurations** : apparaissent automatiquement (animation `max-height`) quand Mannequin / Danseur / Comédien est sélectionné. 5 plages min–max (taille, poitrine, tour de taille, hanches, pointure) + 3 selects (yeux, cheveux, ethnicité)
- Cards identiques à `trouver_talent.php` : grille portrait `aspect-[3/4]`, overlay mensurations au hover
- Handler AJAX : `?ajax=1` retourne `{count, profiles[]}` — `age_range` calculé côté PHP avant sérialisation JSON

### profil.php — 3 themes

The profile page renders one of 3 layouts based on `users.profile_theme`:

- **`classique`** (default): left sidebar (profession, age, location, tags, bio button) centered + masonry 3-col portfolio on the right
- **`editorial`**: full-width hero (first photo as background + dark gradient overlay, name in 6xl at bottom-left) + uniform 3-col square grid below (skips first photo used in hero)
- **`luxe`**: centered layout, name in 7xl, thin decorative divider with brand dot, 2-col grid (first photo spans full width as banner)

Theme is saved via `edit_profil.php?tab=theme` → `POST update_theme` (hidden input) → `User::updateTheme()`. Allowed values: `classique`, `editorial`, `luxe`.

**Bio button:** If `users.bio` is set, a "Biographie" pill button appears (all 3 themes) that opens a popup modal with the full bio text. If no bio, no button is shown (own profile shows "+ Ajouter une biographie" link in classique theme only).

**Bouton partager :** pill arrondi avec icône share (3 cercles reliés) — `id="btn-share"` présent sur les 3 thèmes. `navigator.share` si dispo, sinon `clipboard.writeText`. Feedback "✓ Lien copié !" en mauve pendant 3s.

**Action buttons (owner only):** Two buttons rendered by `renderActions()` next to the profile name:
- **"Photos"** — toggles inline drag-drop edit mode (see below)
- **"Gérer mon profil ▾"** — click-toggled dropdown (IDs: `profile-menu-btn`, `profile-menu`). Items open the edit modal iframe.

**Edit profile modal:** Clicking any dropdown item opens a **full-screen modal** (95vw × 90vh) containing an `<iframe>` loading `edit_profil.php?tab=<tab>`. The sidebar (`#sidebar`) is forcibly hidden via CSS (`#sidebar { display:none !important }`) — `edit_profil.php` does NOT include `header.php`. `padding-left` is also reset to 0 on html/body. Closing the modal (`closeEditModal()`) reloads `profil.php`. IDs: `edit-modal`, `edit-iframe`.

**Editorial theme fix:** Actions rendered **outside** the `overflow:hidden` hero container — placed in a separate `<div>` below the hero — so the dropdown is never clipped.

**Inline photo editing (`includes/photos_edit_grid.php`):** Toggled by the "Photos" button. Shows a uniform grid (auto-fill minmax 180px) with:
- **Drag & drop reorder** — HTML5 dragstart/dragover/drop, saves order via AJAX `photo_action=reorder` (JSON array of IDs → `portfolios.position`)
- **Delete ✕** — hover-reveal button top-right of each tile, confirms then removes via AJAX `photo_action=delete` + `unlink()` on disk
- **Add tile** — last cell, dashed border, clicks hidden `<input type="file" multiple>`, uploads via AJAX `photo_action=upload`, animates new tile in
- All AJAX calls POST to `profil.php?id=<profile_id>`, handled at the top of the file before HTML output
- Clicking "Terminer" or Échap exits edit mode and reloads the page to reflect new order

**Portfolio management:** `Portfolio::getPhotos()` orders by `position ASC, created_at DESC`. `Portfolio::deletePhoto()` + `Portfolio::getPhotoById()` for ownership-checked deletion.

**PHP upload limits:** `docker/Dockerfile` sets `upload_max_filesize = 50M`, `post_max_size = 55M`, `memory_limit = 256M` via `/usr/local/etc/php/conf.d/uploads.ini`.

### edit_profil.php — tab system

`edit_profil.php` is a **tab-based settings panel** — no scroll, no individual submit buttons. Key design:

- **Layout:** fixed sidebar (240px, `bg-[#111]`) on the left + scrollable content area on the right. No `header.php` included — the sidebar is never rendered here.
- **Tabs:** `infos`, `expertise`, `bio`, `mensurations`, `theme`, `securite` — stored in `?tab=` URL param so the correct tab is restored after a POST redirect.
- **Tab switching:** pure JS `switchTab(key)` — swaps `.tab-panel.active` and `.nav-item.active`.
- **Global sticky bar:** a single `position:fixed; bottom:0; right:0` bar with **"Annuler"** + **"Valider"** buttons. "Valider" calls `document.querySelector('.tab-panel.active form').submit()`. "Annuler" calls `.reset()` on the active form. No per-tab submit buttons exist. **After a successful save (`$message` set), the modal is auto-closed** via `window.parent.closeEditModal()` (only when running in iframe).
- **`infos` tab:** photo upload (`profile_pic`), nom complet, **date de naissance** (date input → `users.birth_date`), **genre** (4 radio pills: Homme/Femme/Non-binaire/Autre), ville, pays, **toggle switch "Afficher mon âge"** (`show_age`) — `multipart/form-data`, hidden `update_general=1`. Avatar previewed via `FileReader`. `User::updateGeneralInfo()` prend `$show_age`, `$gender`, `$birth_date`.
- **`expertise` tab:** tag pills (`data-selected` + inline style). Hidden `tags_string` mis à jour par `toggleEditTag()`. Hidden `update_expertise=1`.
- **`mensurations` tab:** taille, poitrine, taille, hanches, pointure (number inputs) + selects pour yeux/cheveux/ethnicité (lookup tables). `User::upsertMeasurements()` fait INSERT ou UPDATE selon l'existence de la ligne. Hidden `update_measurements=1`.
- **`bio`, `theme`, `securite` tabs:** même pattern hidden-input pour leurs handlers respectifs.
- **`update_password` guard:** PHP vérifie `!empty($_POST['current_password'])` pour éviter le déclenchement sur d'autres soumissions.
- **Age display:** `profil.php` calcule `$age` uniquement si `users.show_age = TRUE`.

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
- **Layout:** `html, body { height:100%; overflow:hidden }` + flex **colonne**. Barre horizontale de bulles **en haut** (`#conv-topbar`, `overflow-x:auto`, scrollbar cachée). Zone de chat en dessous (`flex:1`).
- **Barre du haut (bulles):** une bulle par conversation — avatar circulaire 52px (initiale si pas de photo). Au hover + état actif via CSS : prénom apparaît en dessous (`max-height` + opacity transition). Point mauve (`#d4a5d4`) en top-right si messages non lus. Anneau mauve (`box-shadow: 0 0 0 2px #d4a5d4`) sur l'avatar actif.
- **Ouverture depuis un profil:** `?with=USER_ID` → trouve ou crée la conversation (`MIN/MAX` pour garantir l'unicité), marque comme lue, charge les messages.
- **AJAX handlers** (POST `action=`):
  - `send` — insère un message, retourne `id` + `created_at`
  - `poll` — retourne les messages `> last_id`, marque les reçus comme lus, retourne aussi la map `unread` par conv pour mettre à jour les badges
  - `open_conversation` — trouve ou crée une conv entre `$me` et `other_id`
- **Polling:** `setInterval(poll, 2500)` sur la conv active
- **Envoi:** Entrée (sans Shift) ou bouton. Textarea auto-resize via JS.
- **Bulles JS:** `data-conv-id`, `data-other-id`, `data-name`, `data-avatar`, `data-profession` sur chaque `.conv-bubble`. Listener `click` délégué en JS (pas d'`onclick` inline pour éviter les problèmes d'échappement).
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

- `login.php` — standalone login page, checks `users.is_admin = TRUE`
- `index.php` — dashboard: 4 stat cards (users, castings, portfolios, new this month) + 2 recent-items tables
- `utilisateurs.php` — paginated user list, search by name/email/profession, actions: suspend/reactivate, toggle admin, delete
- `castings.php` — paginated casting list, search, delete
- `portfolios.php` — photo grid (5-col), hover-reveal delete with `unlink()`
- `evenements.php` — event list, search, delete
- `sidebar.php` — shared 220px fixed left sidebar for all admin pages

To grant admin to a user: `UPDATE users SET is_admin = TRUE WHERE email = 'xxx';`

### External APIs used

- **Countries list:** `https://restcountries.com/v3.1/all?fields=name,translations` — country dropdowns
- **City autocomplete:** `https://geocoding-api.open-meteo.com/v1/search` — debounced 300ms, min 3 chars

### File uploads

Uploaded files go to `uploads/` (gitignored). Path relative to webroot stored in DB (e.g. `uploads/portfolio_1_1234567890.jpg`).

---

## Pages reference

| File | Purpose |
|---|---|
| `index.php` | Homepage: feed + dropdown filter + right column 3 blocs + first-visit popup |
| `inscription.php` | Registration: prenom+nom, birth_date (max = il y a 16 ans, bloqué JS), clickable tag pills, conditional mensurations |
| `connexion.php` | Login step 1 (email + password) |
| `verifier_code.php` | Login step 2 (2FA code entry) |
| `profil.php` | Public talent profile — 3 themes, bio popup, inline photo drag-drop edit |
| `edit_profil.php` | Settings: avatar, general info, expertise tags, bio, portfolio upload, theme picker, password |
| `castings.php` | Browse/filter castings (filters on right), favorites, own castings, modal detail view |
| `creer_casting.php` | Create casting: multi-profile builder with ranges, two dates, live preview |
| `edit_casting.php` | Edit existing casting — **not yet migrated to Tailwind** (still uses `src/` CSS) |
| `trouver_talent.php` | Browse talents by category + profession, grid portrait cards, hover overlay mensurations, filters on right |
| `recherche.php` | Recherche talents full-text + filtres inline + filtres mensurations (Mannequin/Danseur/Comédien), AJAX dynamique debounced |
| `messagerie.php` | Messagerie temps réel — bulles avatars en haut (barre horizontale), chat en dessous, polling AJAX 2.5s, `?with=USER_ID` ouvre/crée une conv |
| `evenements.php` | Events: tabs, card grid, right filters, AJAX registration toggle, modal — login required |
| `creer_evenement.php` | Create event: title, type, organizer, city/country, date, price, capacity, description, tags, image |
| `preferences.php` | Plus : toggle thème + signaler un problème (`reports`) + suggérer une amélioration (`suggestions`) + déconnexion |
| `apropos.php` | Page À propos **standalone** (sans sidebar, sans `header.php`) — ouverte en `target="_blank"`. Hero animé, marquee métiers, stats compteurs, 3 sections piliers avec scroll-reveal, bouton "Revenir sur ChicBook" en bas |
| `logout.php` | Destroys session, redirects to index |
| `admin/` | Back office: dashboard, users, castings, portfolios, events — requires `is_admin` |

> **Note:** `edit_casting.php` still references `src/style.css` and `src/edit_casting.css` — not yet migrated to Tailwind.
