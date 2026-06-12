<?php
session_start();
require_once 'config/database.php';
require_once 'models/User.php';

$is_logged_in = isset($_SESSION['user_id']);
$db = Database::getInstance()->getConnection();

// Catégories et professions depuis la DB
$cats_rows = $db->query("
    SELECT pc.id, pc.name AS cat_name, pc.display_order,
           COALESCE(json_agg(p.name ORDER BY p.name) FILTER (WHERE p.id IS NOT NULL), '[]') AS professions
    FROM profession_categories pc
    LEFT JOIN professions p ON p.category_id = pc.id
    GROUP BY pc.id ORDER BY pc.display_order ASC
")->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
foreach ($cats_rows as $row) {
    $profs = json_decode($row['professions'], true) ?? [];
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $row['cat_name'])));
    $slug = trim($slug, '-');
    $categories[$slug] = ['label' => $row['cat_name'], 'professions' => $profs];
}
// Fallback si DB vide
if (empty($categories)) {
    $all_profs = $db->query("SELECT name FROM professions ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $categories['tous'] = ['label' => 'Tous les métiers', 'professions' => $all_profs ?: ['—']];
}

$category = $_GET['category'] ?? array_key_first($categories);
if (!isset($categories[$category])) $category = array_key_first($categories);
$cat = $categories[$category];

$profession = $_GET['profession'] ?? ($cat['professions'][0] ?? '');
if (!empty($cat['professions']) && !in_array($profession, $cat['professions'])) $profession = $cat['professions'][0];

$filter_search  = trim($_GET['search'] ?? '');
$filter_city    = trim($_GET['city'] ?? '');
$filter_country = trim($_GET['country'] ?? '');
$filter_tag     = trim($_GET['tag'] ?? '');

$talent_professions = $db->query("SELECT name FROM professions WHERE has_measurements=TRUE ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$is_talent_prof = in_array($profession, $talent_professions);

$filter_height_min  = $_GET['height_min'] ?? '';
$filter_height_max  = $_GET['height_max'] ?? '';
$filter_chest_min   = $_GET['chest_min'] ?? '';
$filter_chest_max   = $_GET['chest_max'] ?? '';
$filter_waist_min   = $_GET['waist_min'] ?? '';
$filter_waist_max   = $_GET['waist_max'] ?? '';
$filter_hip_min     = $_GET['hip_min'] ?? '';
$filter_hip_max     = $_GET['hip_max'] ?? '';
$filter_shoe_min    = $_GET['shoe_min'] ?? '';
$filter_shoe_max    = $_GET['shoe_max'] ?? '';
$filter_eye         = $_GET['eye'] ?? '';
$filter_hair        = $_GET['hair'] ?? '';
$filter_ethnicity   = $_GET['ethnicity'] ?? '';

// Function to convert age to age range
function getAgeRange($birthDate) {
    if (!$birthDate) return null;
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
    
    $ranges = [
        [18, 24], [25, 29], [30, 34], [35, 39], [40, 44], [45, 49], [50, 54], [55, 59]
    ];
    
    foreach ($ranges as $range) {
        if ($age >= $range[0] && $age <= $range[1]) {
            return $range[0] . ' à ' . $range[1] . ' ans';
        }
    }
    
    if ($age >= 60) return '60 et plus';
    return null;
}

// Query profiles
$where = "(u.specific_profession ILIKE :p1 OR p.name ILIKE :p2)";
$binds = ['p1' => $profession, 'p2' => $profession];
if ($filter_search)  { $where .= " AND u.full_name ILIKE :search";  $binds['search']  = "%$filter_search%"; }
if ($filter_city)    { $where .= " AND u.city ILIKE :city";        $binds['city']    = "%$filter_city%"; }
if ($filter_country) { $where .= " AND u.country = :country";      $binds['country'] = $filter_country; }
if ($filter_tag)     { $where .= " AND u.expertise_tags ILIKE :tag"; $binds['tag']   = "%$filter_tag%"; }
if ($is_talent_prof) {
    if ($filter_height_min !== '') { $where .= " AND m.height >= :hmin"; $binds['hmin'] = (int)$filter_height_min; }
    if ($filter_height_max !== '') { $where .= " AND m.height <= :hmax"; $binds['hmax'] = (int)$filter_height_max; }
    if ($filter_chest_min  !== '') { $where .= " AND m.chest_size >= :cmin"; $binds['cmin'] = (int)$filter_chest_min; }
    if ($filter_chest_max  !== '') { $where .= " AND m.chest_size <= :cmax"; $binds['cmax'] = (int)$filter_chest_max; }
    if ($filter_waist_min  !== '') { $where .= " AND m.waist_size >= :wmin"; $binds['wmin'] = (int)$filter_waist_min; }
    if ($filter_waist_max  !== '') { $where .= " AND m.waist_size <= :wmax"; $binds['wmax'] = (int)$filter_waist_max; }
    if ($filter_hip_min    !== '') { $where .= " AND m.hip_size >= :hpmin"; $binds['hpmin'] = (int)$filter_hip_min; }
    if ($filter_hip_max    !== '') { $where .= " AND m.hip_size <= :hpmax"; $binds['hpmax'] = (int)$filter_hip_max; }
    if ($filter_shoe_min   !== '') { $where .= " AND m.shoe_size >= :smin"; $binds['smin'] = (int)$filter_shoe_min; }
    if ($filter_shoe_max   !== '') { $where .= " AND m.shoe_size <= :smax"; $binds['smax'] = (int)$filter_shoe_max; }
    if ($filter_eye        !== '') { $where .= " AND m.eye_color_id = :eye"; $binds['eye'] = (int)$filter_eye; }
    if ($filter_hair       !== '') { $where .= " AND m.hair_color_id = :hair"; $binds['hair'] = (int)$filter_hair; }
    if ($filter_ethnicity  !== '') { $where .= " AND m.ethnicity_id = :eth"; $binds['eth'] = (int)$filter_ethnicity; }
}

$stmt = $db->prepare("
    SELECT u.id, u.full_name, u.specific_profession, u.city, u.country,
           u.profile_picture_url, u.expertise_tags, u.birth_date, p.name AS profession_name,
           (SELECT image_url FROM portfolios WHERE user_id=u.id ORDER BY position ASC, created_at DESC LIMIT 1) AS fallback_avatar,
           m.height, m.chest_size, m.cup_size, m.waist_size, m.hip_size, m.shoe_size,
           ec.name AS eye_color, hc.name AS hair_color, et.name AS ethnicity
    FROM users u
    LEFT JOIN user_professions up ON u.id = up.user_id
    LEFT JOIN professions p ON up.profession_id = p.id
    LEFT JOIN measurements m ON u.id = m.user_id
    LEFT JOIN eye_colors ec ON m.eye_color_id = ec.id
    LEFT JOIN hair_colors hc ON m.hair_color_id = hc.id
    LEFT JOIN ethnicities et ON m.ethnicity_id = et.id
    WHERE $where
    ORDER BY RANDOM()
");
$stmt->execute($binds);
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countries   = $db->query("SELECT DISTINCT country FROM users WHERE country IS NOT NULL AND country != '' ORDER BY country")->fetchAll(PDO::FETCH_COLUMN);
$eye_colors  = $db->query("SELECT id, name FROM eye_colors ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$hair_colors = $db->query("SELECT id, name FROM hair_colors ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$ethnicities = $db->query("SELECT id, name FROM ethnicities ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Tous les tags distincts utilisés par les profils
$raw_tags = $db->query("SELECT expertise_tags FROM users WHERE expertise_tags IS NOT NULL AND expertise_tags != ''")->fetchAll(PDO::FETCH_COLUMN);
$all_tags = [];
foreach ($raw_tags as $row) {
    foreach (explode(',', $row) as $t) {
        $t = trim($t);
        if ($t) $all_tags[$t] = true;
    }
}
ksort($all_tags);
$all_tags = array_keys($all_tags);

function buildUrl($params) {
    return 'trouver_talent.php?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== false));
}
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trouver un talent — <?= htmlspecialchars($cat['label']) ?> · ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
    .tt-filter-toggle { display: none; }
    .tt-cats-mobile { display: none; }
    @media (max-width: 768px) {
      .tt-page-wrapper { padding: 12px 12px 100px; }
      .tt-header-block { margin-bottom: 0 !important; }
      .tt-main-layout { flex-direction: column !important; }
      .tt-filter-toggle { display: flex !important; }
      /* Catégories : cacher les pills, montrer le dropdown mobile */
      .tt-cats-pills { display: none !important; }
      .tt-cats-mobile { display: block !important; }
      #mobile-topbar { display: none !important; }
      /* Filtres : overlay flottant au-dessus des profils */
      .tt-aside {
        position: fixed !important;
        top: auto !important;
        bottom: 90px;
        left: 12px;
        right: 12px;
        width: auto !important;
        max-height: 78vh;
        overflow-y: auto;
        z-index: 600;
        border-radius: 20px;
        visibility: hidden;
        opacity: 0;
        transform: translateY(12px);
        transition: opacity 0.22s ease, transform 0.22s ease, visibility 0.22s;
      }
      .tt-aside.open {
        visibility: visible;
        opacity: 1;
        transform: translateY(0);
      }
      /* Backdrop */
      #tt-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.55);
        z-index: 599;
        backdrop-filter: blur(2px);
      }
      #tt-backdrop.open { display: block; }
    }
    </style>
</head>
<body class="bg-black text-white font-['Open_Sans',sans-serif]">
<?php include 'includes/header.php'; ?>

<div class="max-w-[1400px] mx-auto px-8 pt-8 pb-20 tt-page-wrapper">

    <!-- Backdrop filtre mobile -->
    <div id="tt-backdrop" onclick="closeTTFilters()"></div>

    <!-- En-tête catégorie + onglets professions -->
    <div class="mb-8 tt-header-block">

        <!-- Tabs catégories (desktop) -->
        <div class="flex gap-2 mb-6 flex-wrap tt-cats-pills">
            <?php foreach ($categories as $slug => $c): ?>
                <a href="<?= buildUrl(['category' => $slug, 'profession' => $c['professions'][0]]) ?>"
                   class="px-5 py-2 rounded-full text-sm font-bold border transition-all
                          <?= $slug === $category
                              ? 'bg-white text-black border-white'
                              : 'text-[#666] border-[#2a2a2a] hover:border-[#555] hover:text-white' ?>">
                    <?= htmlspecialchars($c['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Dropdown catégorie (mobile uniquement) + boutons Plus/Avatar intégrés -->
        <div class="tt-cats-mobile mb-3">
          <div style="display:flex; align-items:center; gap:8px;">
            <div class="relative" id="tt-cats-dd-wrap" style="flex:1; min-width:0;">
            <button id="tt-cats-btn"
                    class="flex items-center justify-between gap-3 w-full px-4 py-3 bg-[#111] border border-[#2a2a2a] rounded-2xl text-white font-bold text-sm">
                <span><?= htmlspecialchars($cat['label']) ?></span>
                <svg id="tt-cats-chevron" class="w-4 h-4 text-[#666] transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div id="tt-cats-menu" class="hidden absolute left-0 right-0 mt-2 bg-[#111] border border-[#2a2a2a] rounded-2xl overflow-hidden z-50 shadow-[0_8px_32px_rgba(0,0,0,0.7)]">
                <?php foreach ($categories as $slug => $c): ?>
                    <a href="<?= buildUrl(['category' => $slug, 'profession' => $c['professions'][0]]) ?>"
                       class="flex items-center justify-between px-4 py-3.5 text-sm font-semibold transition-colors hover:bg-[#1e1e1e] <?= $slug === $category ? 'text-white' : 'text-[#888] hover:text-white' ?>">
                        <?= htmlspecialchars($c['label']) ?>
                        <?php if ($slug === $category): ?><svg class="w-4 h-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            </div><!-- /tt-cats-dd-wrap -->
            <!-- Boutons Plus + Avatar -->
            <a href="preferences.php" class="mtop-btn <?= $current_page === 'preferences.php' ? 'mtop-active' : '' ?>" title="Plus" style="flex-shrink:0;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
              </svg>
            </a>
            <?php if ($current_user_id): ?>
              <a href="profil.php" class="mtop-avatar <?= $current_page === 'profil.php' ? 'mtop-active' : '' ?>" title="Mon profil" style="flex-shrink:0;">
                <?php if ($user_avatar): ?>
                  <img src="<?= htmlspecialchars($user_avatar) ?>" alt="Profil">
                <?php else: ?>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?php endif; ?>
              </a>
            <?php else: ?>
              <a href="connexion.php" class="mtop-avatar" title="Se connecter" style="flex-shrink:0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
              </a>
            <?php endif; ?>
          </div><!-- /flex row -->
        </div><!-- /tt-cats-mobile -->

        <!-- Dropdown professions -->
        <div class="relative mb-3" id="profession-dropdown-wrapper">
            <button id="profession-toggle"
                    class="flex items-center gap-3 px-5 py-3 bg-[#111] border border-[#2a2a2a] rounded-2xl text-white font-semibold text-sm hover:border-[#555] transition-colors justify-between"
                    style="min-width:260px;">
                <span>
                    <span class="text-[#666] font-normal mr-2"><?= htmlspecialchars($cat['label']) ?> ·</span>
                    <span id="profession-label"><?= htmlspecialchars($profession) ?></span>
                </span>
                <svg id="profession-chevron" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#666] transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div id="profession-menu" class="hidden absolute left-0 mt-2 bg-[#111] border border-[#2a2a2a] rounded-2xl overflow-hidden z-50 shadow-[0_8px_32px_rgba(0,0,0,0.6)]" style="min-width:260px;">
                <?php foreach ($cat['professions'] as $prof): ?>
                    <a href="<?= buildUrl(['category' => $category, 'profession' => $prof, 'city' => $filter_city, 'country' => $filter_country, 'tag' => $filter_tag]) ?>"
                       class="block px-5 py-3.5 text-sm font-semibold transition-colors hover:bg-[#1e1e1e] <?= $prof === $profession ? 'text-white' : 'text-[#aaa] hover:text-white' ?>">
                        <?= htmlspecialchars($prof) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
        // Dropdown profession (desktop + mobile)
        (function() {
            const btn = document.getElementById('profession-toggle');
            const menu = document.getElementById('profession-menu');
            const chevron = document.getElementById('profession-chevron');
            if (!btn) return;
            btn.addEventListener('click', e => {
                e.stopPropagation();
                const open = !menu.classList.contains('hidden');
                menu.classList.toggle('hidden', open);
                chevron.style.transform = open ? '' : 'rotate(180deg)';
            });
            document.addEventListener('click', () => {
                menu.classList.add('hidden');
                chevron.style.transform = '';
            });
        })();
        // Dropdown catégorie mobile
        (function() {
            const btn = document.getElementById('tt-cats-btn');
            const menu = document.getElementById('tt-cats-menu');
            const chevron = document.getElementById('tt-cats-chevron');
            if (!btn) return;
            btn.addEventListener('click', e => {
                e.stopPropagation();
                const open = !menu.classList.contains('hidden');
                menu.classList.toggle('hidden', open);
                chevron.style.transform = open ? '' : 'rotate(180deg)';
            });
            document.addEventListener('click', () => {
                menu.classList.add('hidden');
                chevron.style.transform = '';
            });
        })();
        // Filtres overlay mobile
        function openTTFilters() {
            document.querySelector('.tt-aside').classList.add('open');
            document.getElementById('tt-backdrop').classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeTTFilters() {
            document.querySelector('.tt-aside').classList.remove('open');
            document.getElementById('tt-backdrop').classList.remove('open');
            document.body.style.overflow = '';
        }
        </script>
    </div>

    <!-- Bouton filtres + recherche mobile -->
    <?php
    $has_active_filters_mobile = $filter_search || $filter_city || $filter_country || $filter_tag
        || $filter_height_min || $filter_height_max || $filter_chest_min || $filter_chest_max
        || $filter_waist_min || $filter_waist_max || $filter_hip_min || $filter_hip_max
        || $filter_shoe_min || $filter_shoe_max || $filter_eye || $filter_hair || $filter_ethnicity;
    ?>
    <form method="GET" action="trouver_talent.php" class="tt-filter-toggle items-center gap-2 mb-3">
        <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
        <input type="hidden" name="profession" value="<?= htmlspecialchars($profession) ?>">
        <?php if ($filter_city):    ?><input type="hidden" name="city"    value="<?= htmlspecialchars($filter_city) ?>"><?php endif; ?>
        <?php if ($filter_country): ?><input type="hidden" name="country" value="<?= htmlspecialchars($filter_country) ?>"><?php endif; ?>
        <?php if ($filter_tag):     ?><input type="hidden" name="tag"     value="<?= htmlspecialchars($filter_tag) ?>"><?php endif; ?>
        <!-- Champ recherche par nom -->
        <div style="flex:1; min-width:0; position:relative;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                 style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#666; pointer-events:none;">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" name="search" value="<?= htmlspecialchars($filter_search) ?>"
                   placeholder="Rechercher un talent…"
                   style="width:100%; background:#111; border:1px solid #2a2a2a; border-radius:12px; padding:10px 12px 10px 34px; color:#fff; font-size:13px; outline:none; font-family:inherit;"
                   onfocus="this.style.borderColor='#d4a5d4'" onblur="this.style.borderColor='#2a2a2a'">
        </div>
        <!-- Bouton Filtres -->
        <button type="button" onclick="openTTFilters()"
                style="flex-shrink:0; display:flex; align-items:center; gap:6px; padding:10px 14px; background:#111; border:1px solid <?= $has_active_filters_mobile ? '#d4a5d4' : '#333' ?>; border-radius:12px; color:<?= $has_active_filters_mobile ? '#d4a5d4' : '#fff' ?>; font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="20" y2="12"/><line x1="12" y1="18" x2="20" y2="18"/></svg>
            Filtres<?= $has_active_filters_mobile ? ' ●' : '' ?>
        </button>
    </form>

    <div class="flex gap-8 items-start tt-main-layout">

        <!-- Grille des cartes profils -->
        <div class="flex-grow min-w-0">
            <?php if (empty($profiles)): ?>
                <div class="bg-[#111] border border-dashed border-[#2a2a2a] rounded-2xl py-20 text-center">
                    <p class="text-[#555] text-lg mb-2">Aucun profil trouvé</p>
                    <p class="text-[#444] text-sm">Essayez de modifier les filtres ou la profession sélectionnée.</p>
                </div>
            <?php else: ?>
                <p class="text-[#555] text-sm mb-5"><?= count($profiles) ?> profil<?= count($profiles) > 1 ? 's' : '' ?> · <?= htmlspecialchars($profession) ?></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($profiles as $p): ?>
                        <?php 
                            $is_talent_with_measurements = in_array($p['specific_profession'] ?? $p['profession_name'] ?? '', ['Mannequin', 'Danseur', 'Comédien']);
                            $age_range = getAgeRange($p['birth_date']);
                            $avatar_url = $p['profile_picture_url'] ?: $p['fallback_avatar'];
                            $has_measurements = $is_talent_with_measurements && ($p['height'] || $p['chest_size'] || $p['waist_size'] || $p['hip_size'] || $p['shoe_size'] || $p['eye_color'] || $p['hair_color'] || $p['ethnicity']);
                        ?>
                        <a href="profil.php?id=<?= $p['id'] ?>"
                           class="group bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden hover:border-[#333] transition-all">
                            
                            <!-- Image conteneur -->
                            <div class="relative overflow-hidden bg-[#222] aspect-[3/4] flex items-center justify-center">
                                <?php if ($avatar_url): ?>
                                    <img src="<?= htmlspecialchars($avatar_url) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($p['full_name']) ?>">
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="w-12 h-12 text-[#333]"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?php endif; ?>

                                <!-- Overlay mensurations (talents only) -->
                                <?php if ($has_measurements): ?>
                                    <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-end p-5">
                                        <div class="text-xs text-[#ddd] w-full">
                                            <div class="space-y-1.5">
                                                <?php if ($p['height']): ?>
                                                    <div class="flex justify-between"><span class="text-[#aaa]">HAUTEUR</span> <span class="font-semibold"><?= htmlspecialchars($p['height']) ?> cm</span></div>
                                                <?php endif; ?>
                                                <?php if ($p['chest_size']): ?>
                                                    <div class="flex justify-between"><span class="text-[#aaa]">POITRINE</span> <span class="font-semibold"><?= htmlspecialchars($p['chest_size']) ?></span></div>
                                                <?php endif; ?>
                                                <?php if ($p['cup_size']): ?>
                                                    <div class="flex justify-between"><span class="text-[#aaa]">BONNET</span> <span class="font-semibold"><?= htmlspecialchars($p['cup_size']) ?></span></div>
                                                <?php endif; ?>
                                                <?php if ($p['waist_size']): ?>
                                                    <div class="flex justify-between"><span class="text-[#aaa]">TAILLE</span> <span class="font-semibold"><?= htmlspecialchars($p['waist_size']) ?></span></div>
                                                <?php endif; ?>
                                                <?php if ($p['hip_size']): ?>
                                                    <div class="flex justify-between"><span class="text-[#aaa]">HANCHES</span> <span class="font-semibold"><?= htmlspecialchars($p['hip_size']) ?></span></div>
                                                <?php endif; ?>
                                                <?php if ($p['shoe_size']): ?>
                                                    <div class="flex justify-between"><span class="text-[#aaa]">POINTURE</span> <span class="font-semibold"><?= htmlspecialchars($p['shoe_size']) ?></span></div>
                                                <?php endif; ?>
                                                <?php if ($p['eye_color']): ?>
                                                    <div class="flex justify-between"><span class="text-[#aaa]">YEUX</span> <span class="font-semibold"><?= htmlspecialchars($p['eye_color']) ?></span></div>
                                                <?php endif; ?>
                                                <?php if ($p['hair_color']): ?>
                                                    <div class="flex justify-between"><span class="text-[#aaa]">CHEVEUX</span> <span class="font-semibold"><?= htmlspecialchars($p['hair_color']) ?></span></div>
                                                <?php endif; ?>
                                                <?php if ($p['ethnicity']): ?>
                                                    <div class="flex justify-between"><span class="text-[#aaa]">ETHNICITÉ</span> <span class="font-semibold"><?= htmlspecialchars($p['ethnicity']) ?></span></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Infos en bas -->
                            <div class="px-5 py-4">
                                <div class="mb-2">
                                    <h3 class="font-bold text-white text-sm group-hover:text-[#d4a5d4] transition-colors line-clamp-1">
                                        <?= htmlspecialchars($p['full_name']) ?>
                                    </h3>
                                    <p class="text-[#888] text-xs">
                                        <?= htmlspecialchars($p['specific_profession'] ?? $p['profession_name'] ?? $profession) ?>
                                    </p>
                                </div>

                                <?php if (!empty($p['city']) || !empty($p['country']) || $age_range): ?>
                                    <div class="text-[#666] text-xs mb-2 space-y-0.5">
                                        <?php if (!empty($p['city'])): ?>
                                            <div><?= htmlspecialchars($p['city']) ?><?= !empty($p['country']) ? ', '.htmlspecialchars($p['country']) : '' ?></div>
                                        <?php endif; ?>
                                        <?php if ($age_range): ?>
                                            <div><?= htmlspecialchars($age_range) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($p['expertise_tags'])): ?>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach (array_slice(explode(',', $p['expertise_tags']), 0, 3) as $tag): ?>
                                            <?php if (trim($tag)): ?>
                                                <span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] text-[9px] font-semibold px-2 py-1 rounded-full">
                                                    <?= htmlspecialchars(trim($tag)) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Filtres (droite) -->
        <aside class="w-[260px] flex-shrink-0 sticky top-8 tt-aside">
            <form method="GET" action="trouver_talent.php">
                <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                <input type="hidden" name="profession" value="<?= htmlspecialchars($profession) ?>">
                <div class="bg-[#0e0e0e] rounded-2xl p-6 flex flex-col gap-5" style="box-shadow: 0 1px 0 rgba(255,255,255,0.04) inset, 0 -2px 0 rgba(0,0,0,0.8), 0 8px 24px rgba(0,0,0,0.5);">
                    <div class="flex items-center justify-between">
                        <h3 class="text-white font-bold text-base">Filtres</h3>
                        <button type="button" onclick="closeTTFilters()" class="tt-filter-toggle w-7 h-7 flex items-center justify-center rounded-full bg-[#1a1a1a] text-[#666] hover:text-white transition-colors text-base leading-none">✕</button>
                    </div>

                    <div>
                        <label class="block text-[#555] text-xs font-bold uppercase tracking-widest mb-2">Mot-clé</label>
                        <select name="tag" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-2.5 text-white text-sm outline-none focus:border-[#d4a5d4] transition-colors">
                            <option value="">Tous les tags</option>
                            <?php foreach ($all_tags as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $filter_tag === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[#555] text-xs font-bold uppercase tracking-widest mb-2">Pays</label>
                        <select name="country" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-2.5 text-white text-sm outline-none focus:border-[#d4a5d4] transition-colors">
                            <option value="">Tous les pays</option>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $filter_country === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[#555] text-xs font-bold uppercase tracking-widest mb-2">Ville</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($filter_city) ?>"
                               placeholder="Paris, Lyon…"
                               class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-2.5 text-white text-sm outline-none focus:border-[#d4a5d4] transition-colors placeholder:text-[#444]">
                    </div>

                    <?php if ($is_talent_prof): ?>
                    <div class="border-t border-[#1a1a1a] pt-4">
                        <p class="text-[#d4a5d4] text-xs font-bold uppercase tracking-widest mb-4">Mensurations</p>

                        <?php
                        $range_fields = [
                            ['label'=>'Taille (cm)',      'min'=>'height_min', 'max'=>'height_max', 'vmin'=>$filter_height_min, 'vmax'=>$filter_height_max, 'ph'=>['155','195']],
                            ['label'=>'Poitrine (cm)',    'min'=>'chest_min',  'max'=>'chest_max',  'vmin'=>$filter_chest_min,  'vmax'=>$filter_chest_max,  'ph'=>['80','110']],
                            ['label'=>'Tour de taille',   'min'=>'waist_min',  'max'=>'waist_max',  'vmin'=>$filter_waist_min,  'vmax'=>$filter_waist_max,  'ph'=>['55','90']],
                            ['label'=>'Hanches (cm)',     'min'=>'hip_min',    'max'=>'hip_max',    'vmin'=>$filter_hip_min,    'vmax'=>$filter_hip_max,    'ph'=>['80','120']],
                            ['label'=>'Pointure',         'min'=>'shoe_min',   'max'=>'shoe_max',   'vmin'=>$filter_shoe_min,   'vmax'=>$filter_shoe_max,   'ph'=>['35','48']],
                        ];
                        foreach ($range_fields as $f): ?>
                        <div class="mb-3">
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-widest mb-1.5"><?= $f['label'] ?></label>
                            <div class="flex gap-2 items-center">
                                <input type="number" name="<?= $f['min'] ?>" value="<?= htmlspecialchars($f['vmin']) ?>" placeholder="<?= $f['ph'][0] ?>" min="0" max="999"
                                    class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-lg px-3 py-2 text-white text-xs outline-none focus:border-[#d4a5d4] placeholder:text-[#333]">
                                <span class="text-[#444] text-xs flex-shrink-0">–</span>
                                <input type="number" name="<?= $f['max'] ?>" value="<?= htmlspecialchars($f['vmax']) ?>" placeholder="<?= $f['ph'][1] ?>" min="0" max="999"
                                    class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-lg px-3 py-2 text-white text-xs outline-none focus:border-[#d4a5d4] placeholder:text-[#333]">
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="mb-3">
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-widest mb-1.5">Yeux</label>
                            <select name="eye" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-3 py-2 text-white text-xs outline-none focus:border-[#d4a5d4]">
                                <option value="">Toutes</option>
                                <?php foreach ($eye_colors as $e): ?>
                                    <option value="<?= $e['id'] ?>" <?= $filter_eye == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-widest mb-1.5">Cheveux</label>
                            <select name="hair" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-3 py-2 text-white text-xs outline-none focus:border-[#d4a5d4]">
                                <option value="">Tous</option>
                                <?php foreach ($hair_colors as $h): ?>
                                    <option value="<?= $h['id'] ?>" <?= $filter_hair == $h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-widest mb-1.5">Ethnicité</label>
                            <select name="ethnicity" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-3 py-2 text-white text-xs outline-none focus:border-[#d4a5d4]">
                                <option value="">Toutes</option>
                                <?php foreach ($ethnicities as $e): ?>
                                    <option value="<?= $e['id'] ?>" <?= $filter_ethnicity == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <button type="submit" onclick="closeTTFilters()" class="w-full py-2.5 bg-[#d4a5d4] text-black rounded-xl font-bold text-sm hover:opacity-90 transition-opacity border-none cursor-pointer">
                        Appliquer les filtres
                    </button>

                    <?php
                    $has_active_filters = $filter_city || $filter_country || $filter_tag
                        || $filter_height_min || $filter_height_max || $filter_chest_min || $filter_chest_max
                        || $filter_waist_min || $filter_waist_max || $filter_hip_min || $filter_hip_max
                        || $filter_shoe_min || $filter_shoe_max || $filter_eye || $filter_hair || $filter_ethnicity;
                    if ($has_active_filters): ?>
                        <a href="<?= buildUrl(['category' => $category, 'profession' => $profession]) ?>"
                           class="text-center text-[#555] text-xs hover:text-[#d4a5d4] transition-colors">
                            ✕ Effacer les filtres
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </aside>

    </div>
</div>

<script src="assets/js/script.js"></script>
</body>
</html>
