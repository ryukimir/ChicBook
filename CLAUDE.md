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

Registration collects `prenom` + `nom` (combined into `full_name` in DB), `birth_date` (stored in `users` table, required for all). The mensurations sub-form (height, sizes, eye/hair color) is stored in `measurements` table and only shown for: Mannequin, Comédien, Danseur. Password validation: min 8 chars, at least one letter and one digit, all special characters allowed.

**Registration tags:** `inscription.php` shows clickable pill tags (expertise keywords) — user clicks to toggle, stored comma-separated in `users.expertise_tags`. Implemented via `data-selected` attribute + inline styles (not Tailwind classes, to avoid escaping issues with querySelector).

### Navigation — left sidebar (Instagram-style)

`includes/header.php` renders a fixed left sidebar (`#sidebar`). There is **no top navbar**.

- **Collapsed:** 80px wide (icons only)
- **Expanded:** 260px wide on hover — labels slide in with `translateX` + opacity transition
- `body { padding-left: 260px }` is **always fixed** — the sidebar zone is permanently reserved so expanding never overlaps content
- Active page detected via `basename($_SERVER['PHP_SELF'])` → `active` class on the matching item
- No theme toggle — dark theme is permanent
- Item padding: `10px 18px` (reduced from 15px to slim the bar without shrinking icons/text)

**Sidebar nav items (in order):**
1. Accueil → `index.php`
2. Trouver un talent → `trouver_talent.php`
3. Castings → `castings.php`
4. Messagerie → `messagerie.php`
5. Événements → `evenements.php`
6. À propos → `apropos.php`
7. *(spacer)*
8. Mon Profil / S'identifier → `profil.php` / `connexion.php`
9. Déconnexion → `logout.php`

CSS class names that must exist in the HTML for the sidebar to work: `#sidebar`, `.sidebar-item`, `.sidebar-icon`, `.sidebar-label`, `.sidebar-logo`, `.sidebar-logo-text`, `.sidebar-divider`, `.sidebar-spacer`.

### Dark theme (permanent)

There is **no light/dark toggle**. The entire site is dark-only:
- All pages use `<body class="bg-black text-white ...">` 
- Card surfaces: `#111` (page bg), `#1a1a1a` (cards), `#222` (nested elements)
- `assets/css/custom.css` sets `body { background-color: #000; font-family: 'Open Sans', sans-serif }` as the global default
- No `html.light` CSS overrides exist

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
- `measurements` — one-to-one with `users`, physical talent types only
- `castings` — has `casting_date DATE` (audition day) and `performance_date DATE` (realization day), `city`, `country`, `collaboration_type`, `cover_image`
- `casting_profiles` — multiple profiles per casting (role, quantity, age range, gender, measurements as min-max strings like "165 - 175", eye/hair/ethnicity FKs)
- `casting_favorites` — many-to-many: users save castings as favorites
- `portfolios` — images per user, path under `uploads/`, `position INT` for ordering (ORDER BY position ASC, created_at DESC)
- `events` — title, type, organizer, city, country, event_date, cover_image, description, price, capacity, tags (comma-separated), user_id
- `event_registrations` — many-to-many: users register interest in events

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

### index.php — feed

The homepage is a professional feed (not carousels). Structure:
- **Filter dropdown** at top: "Fil d'actualité · Tous les talents" — dropdown with profession options, JS filtering via `data-filter` on each `.feed-post`, no page reload. Closes on outside click.
- **Feed posts (real data):** sourced from `portfolios` table — **one post per user, the most recent photo**, sorted by `created_at DESC`. Uses `DISTINCT ON (po.user_id)` + PHP `usort`. Each post: avatar + name (clickable link to `profil.php?id=`), image in original format (no crop), expertise tags from `users.expertise_tags` (max 5), heart like button (client-side only). No caption field — real users don't have one. `data-filter` is the normalized profession slug (lowercase, no accents/spaces via `iconv + preg_replace`).
- **Right column:** conditional by login state:
  - **Non connecté:** "Rejoindre ChicBook" CTA + "La mode en mouvement"
  - **Connecté:** widget événements à venir (4 prochains depuis DB, date + titre + ville) + "La mode en mouvement". Le widget événements est masqué pour les non-connectés.
- **Welcome popup**: shown on first visit (non-logged-in users only), tracked via `localStorage('chicbook_visited')`. Contains "S'inscrire" CTA. Closes on ✕ or outside click.

### trouver_talent.php — key behaviors

- URL params: `?category=creation-design|image-production|marques-createurs&profession=Styliste&city=&country=&tag=`
- Three categories with their professions:
  - `creation-design`: Styliste, Modéliste, Designer, Illustrateur, Directeur artistique
  - `image-production`: Photographe, Vidéaste, Mannequin, Maquilleur, Coiffeur
  - `marques-createurs`: Marque, Créateur, Agence, Casting director
- Top: category pills (white = active) + **profession dropdown** (same style as the feed filter on `index.php` — button shows `Category · Profession`, chevron rotates on open, closes on outside click)
- Profiles displayed as horizontal list rows: avatar, name, profession, city, tags → click goes to `profil.php?id=`
- Filters on the **right**: mot-clé (**`<select>` dropdown** populated from all distinct `expertise_tags` values in DB, aggregated and sorted alphabetically), pays, ville
- Results ordered by `RANDOM()`

### profil.php — 3 themes

The profile page renders one of 3 layouts based on `users.profile_theme`:

- **`classique`** (default): left sidebar (profession, age, location, tags, bio button) centered + masonry 3-col portfolio on the right
- **`editorial`**: full-width hero (first photo as background + dark gradient overlay, name in 6xl at bottom-left) + uniform 3-col square grid below (skips first photo used in hero)
- **`luxe`**: centered layout, name in 7xl, thin decorative divider with brand dot, 2-col grid (first photo spans full width as banner)

Theme is saved via `edit_profil.php?tab=theme` → `POST update_theme` (hidden input) → `User::updateTheme()`. Allowed values: `classique`, `editorial`, `luxe`.

**Bio button:** If `users.bio` is set, a "Biographie" pill button appears (all 3 themes) that opens a popup modal with the full bio text. If no bio, no button is shown (own profile shows "+ Ajouter une biographie" link in classique theme only).

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
- **Tabs:** `infos`, `expertise`, `bio`, `theme`, `securite` — stored in `?tab=` URL param so the correct tab is restored after a POST redirect.
- **Tab switching:** pure JS `switchTab(key)` — swaps `.tab-panel.active` and `.nav-item.active`.
- **Global sticky bar:** a single `position:fixed; bottom:0; right:0` bar with **"Annuler"** + **"Valider"** buttons. "Valider" calls `document.querySelector('.tab-panel.active form').submit()`. "Annuler" calls `.reset()` on the active form. No per-tab submit buttons exist. **After a successful save (`$message` set), the modal is auto-closed** via `window.parent.closeEditModal()` (only when running in iframe).
- **`infos` tab:** contains photo upload (`profile_pic`), general info fields, **genre** (4 radio pills: Homme/Femme/Non-binaire/Autre), and **toggle switch "Afficher mon âge"** (`show_age` checkbox styled as a custom toggle) — all in one `multipart/form-data` form with `<input type="hidden" name="update_general" value="1">`. Avatar previewed immediately via `FileReader`. `User::updateGeneralInfo()` takes `$show_age` (bool) and `$gender` (string).
- **`expertise` tab:** tag pills (same `data-selected` + inline style pattern as `inscription.php`). Hidden input `name="tags_string"` updated by `toggleEditTag()`. Form has `<input type="hidden" name="update_expertise" value="1">`.
- **`bio`, `theme`, `securite` tabs:** same hidden-input pattern for their respective PHP handlers.
- **`update_password` guard:** PHP checks `!empty($_POST['current_password'])` before processing to avoid triggering on other form submits.
- **Age display:** `profil.php` only computes `$age` if `users.show_age = TRUE`. `getUserProfile()` selects `show_age` and `gender` from DB.

### evenements.php — key behaviors

- Requires login — redirects to `connexion.php` if not authenticated
- **Four tabs:** **Tous**, **À venir** (`event_date >= CURRENT_DATE`), **Mes inscriptions** (JOIN on `event_registrations`), **Mes événements** (`e.user_id = current_user`)
- Registration toggle is AJAX (`toggle_registration=1`) — no page reload; updates button state and card badge
- Filters on the **right**: type (select from DB), ville (text ILIKE)
- Cards clickable → modal with full event details + "Je suis intéressé(e)" AJAX button
- Create button → `creer_evenement.php` (own form, image upload, redirects back to list)
- **"Mes événements" tab:** shows cards with extra "✎ Modifier" + "✕ Supprimer" buttons. Modifier opens an **edit modal** (`#edit-event-modal`) pre-filled via `eventsById[id]` JS object (built from PHP `array_column`). Form POSTs `update_event=1` + all fields + optional new cover image. Supprimer POSTs `delete_event=1` with confirmation. Both redirect to `?view=mes_creations`. Cover image is replaced on disk only if a new file is uploaded.

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
| `inscription.php` | Registration: prenom+nom, birth_date, clickable tag pills, conditional mensurations |
| `connexion.php` | Login step 1 (email + password) |
| `verifier_code.php` | Login step 2 (2FA code entry) |
| `profil.php` | Public talent profile — 3 themes, bio popup, inline photo drag-drop edit |
| `edit_profil.php` | Settings: avatar, general info, expertise tags, bio, portfolio upload, theme picker, password |
| `castings.php` | Browse/filter castings (filters on right), favorites, own castings, modal detail view |
| `creer_casting.php` | Create casting: multi-profile builder with ranges, two dates, live preview |
| `edit_casting.php` | Edit existing casting — **not yet migrated to Tailwind** (still uses `src/` CSS) |
| `trouver_talent.php` | Browse talents by category + profession, list view, filters on right |
| `messagerie.php` | Messaging — **not yet created** |
| `evenements.php` | Events: tabs, card grid, right filters, AJAX registration toggle, modal — login required |
| `creer_evenement.php` | Create event: title, type, organizer, city/country, date, price, capacity, description, tags, image |
| `apropos.php` | About page |
| `logout.php` | Destroys session, redirects to index |
| `admin/` | Back office: dashboard, users, castings, portfolios, events — requires `is_admin` |

> **Note:** `edit_casting.php` still references `src/style.css` and `src/edit_casting.css` — not yet migrated to Tailwind. `messagerie.php` does not exist yet.
