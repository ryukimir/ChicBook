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

**Database credentials** (hardcoded in `config/database.php`):
- Host: `db` (Docker service name)
- DB: `chicbook`, User: `chicuser`, Password: `chicpassword`

**First-time setup:** The `sql/init.sql` file is automatically executed by PostgreSQL on first container boot (via `docker-entrypoint-initdb.d`). If the database already exists, re-run it manually in Adminer or psql. Always append migrations at the bottom of `sql/init.sql` — never rewrite existing statements.

**Apply a migration to a running DB:**
```bash
docker exec chicbook_db psql -U chicuser -d chicbook -c "ALTER TABLE ... "
```

---

## Architecture

### Stack
- **Backend:** PHP 8.2 (no framework) served by Apache
- **Database:** PostgreSQL 15 via PDO
- **Frontend:** Vanilla JS + Tailwind CSS (CDN, no build step)
- **Email:** PHP `mail()` routed to Mailpit via msmtp (dev only)

### File layout

```
/                        PHP pages (web-accessible at root)
├── config/database.php  Singleton PDO connection
├── controllers/         Business logic (AuthController)
├── models/              Data access: User, Profession, Portfolio
├── includes/header.php  Shared nav — included on every page, contains theme toggle
├── assets/
│   ├── css/custom.css   Theme system + CSS selectors Tailwind can't handle inline
│   ├── js/script.js     Shared JS: theme toggle, nav scroll, city autocomplete, carousels
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

### Theme system (dark/light)

A toggle button (☀️/🌙) in the header switches between dark (default) and light theme. The preference is persisted in `localStorage`. The theme is applied by adding/removing class `light` on `<html>`. The inline `<script>` at the top of `includes/header.php` runs immediately to apply the saved theme before the page renders (prevents flash).

`assets/css/custom.css` overrides Tailwind's hardcoded arbitrary-value classes (e.g. `.bg-\[#1a1a1a\]`, `.text-white`) under `html.light { ... }`. Dark is the default; light overrides with `!important`.

### Tailwind usage

No build step. Tailwind CDN is loaded on every page with a custom config block:
```html
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: { colors: { brand: '#d4a5d4', dark: '#1a1a1a' } } }
  }
</script>
```
Brand color: `#d4a5d4` (mauve/purple). Dark background: `#1a1a1a`. Secondary dark: `#111`. Card surface: `#222`.

### Database schema key points

- `users` — central table; `full_name`, `birth_date DATE`, `specific_profession`, `expertise_tags` (comma-separated), `login_code` (temp 2FA)
- `user_professions` — many-to-many to `professions` lookup (set at registration)
- `measurements` — one-to-one with `users`, physical talent types only
- `castings` — has `casting_date DATE` (audition day) and `performance_date DATE` (realization day), `city`, `country`, `collaboration_type`, `cover_image`
- `casting_profiles` — multiple profiles per casting (role, quantity, age range, gender, measurements as min-max strings like "165 - 175", eye/hair/ethnicity FKs)
- `casting_favorites` — many-to-many: users save castings as favorites
- `portfolios` — images per user, path under `uploads/`

When adding a column/table, append at the bottom of `sql/init.sql` and run the migration manually on the running container.

### castings.php — key behaviors

- Three tabs: **Opportunités** (castings seeking user's profession), **Favoris** (saved), **Mes Castings créés**
- Filters (sidebar GET form): country → city (dependent dropdown, JS-filtered), date range on `performance_date`
- Favorite toggle is AJAX (`fetch` POST to same page with `toggle_favorite=1`) — no page reload
- Cards are clickable → modal overlay with full casting details including all profiles with their criteria
- Modal is populated from `data-casting` JSON attribute embedded on each card by PHP (`json_agg` with LEFT JOINs to eye/hair/ethnicity tables)

### creer_casting.php — key behaviors

- Live preview card (right sidebar) updates as user types — role, city, description, company, dates, image
- Measurement inputs for Mannequin/Comédien/Danseur roles use **min → max range pairs** (stored as "165 - 175" strings in `VARCHAR` columns)
- Two date fields: `casting_date` (audition) + `performance_date` (realization)
- "Add profile" button clones the first `.profile-card` — relies on class names `profile-card`, `profile-card-header`, `role-selector`, `mensurations-grid`

### External APIs used

- **Countries list:** `https://restcountries.com/v3.1/all?fields=name,translations` — country dropdowns
- **City autocomplete:** `https://geocoding-api.open-meteo.com/v1/search` — debounced 300ms, min 3 chars

### File uploads

Uploaded files go to `uploads/` (gitignored). Path relative to webroot stored in DB (e.g. `uploads/avatar_12_1234567890.jpg`).

---

## Pages reference

| File | Purpose |
|---|---|
| `index.php` | Homepage: two talent carousels + expertise tag grid |
| `inscription.php` | Registration: prenom+nom, birth_date, conditional mensurations for physical roles |
| `connexion.php` | Login step 1 (email + password) |
| `verifier_code.php` | Login step 2 (2FA code entry) |
| `profil.php` | Public talent profile — portfolio masonry, age calculated from `birth_date` |
| `edit_profil.php` | Settings: avatar, general info, expertise tags, bio, portfolio upload, password |
| `castings.php` | Browse/filter castings, favorites, own castings, modal detail view |
| `creer_casting.php` | Create casting: multi-profile builder with ranges, two dates, live preview |
| `edit_casting.php` | Edit existing casting — **not yet migrated to Tailwind** (still uses `src/` CSS) |
| `poster_projet.php` | Post a longer-term creative project |
| `apropos.php` | About page with Z-pattern pillar layout |
| `logout.php` | Destroys session, redirects to index |

> **Note:** `edit_casting.php` still references `src/style.css` and `src/edit_casting.css` — not yet migrated to Tailwind.
