<?php
session_start();
require_once 'config/database.php';
require_once 'models/User.php';

$db = Database::getInstance()->getConnection();

$talent_professions = $db->query("SELECT name FROM professions WHERE has_measurements=TRUE ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

function getAgeRange($birthDate) {
    if (!$birthDate) return null;
    $age = (new DateTime())->diff(new DateTime($birthDate))->y;
    foreach ([[18,24],[25,29],[30,34],[35,39],[40,44],[45,49],[50,54],[55,59]] as $r) {
        if ($age >= $r[0] && $age <= $r[1]) return $r[0].' à '.$r[1].' ans';
    }
    return $age >= 60 ? '60 et plus' : null;
}

function runSearch($db, $params) {
    extract($params); // q, profession, city, country, tag, h_min, h_max, chest_min, chest_max, waist_min, waist_max, hip_min, hip_max, shoe_min, shoe_max, eye_color_id, hair_color_id, ethnicity_id
    $where = "1=1";
    $binds = [];

    if ($q !== '') {
        $where .= " AND (u.full_name ILIKE :q OR u.specific_profession ILIKE :q2 OR u.expertise_tags ILIKE :q3 OR u.city ILIKE :q4)";
        $binds['q'] = "%$q%"; $binds['q2'] = "%$q%"; $binds['q3'] = "%$q%"; $binds['q4'] = "%$q%";
    }
    if ($profession)    { $where .= " AND (u.specific_profession ILIKE :prof OR p.name ILIKE :prof2)"; $binds['prof'] = $profession; $binds['prof2'] = $profession; }
    if ($city)          { $where .= " AND u.city ILIKE :city";      $binds['city']    = "%$city%"; }
    if ($country)       { $where .= " AND u.country = :country";    $binds['country'] = $country; }
    if ($tag)           { $where .= " AND u.expertise_tags ILIKE :tag"; $binds['tag'] = "%$tag%"; }
    // Mensurations
    if ($h_min !== '')      { $where .= " AND m.height >= :h_min";         $binds['h_min']      = (int)$h_min; }
    if ($h_max !== '')      { $where .= " AND m.height <= :h_max";         $binds['h_max']      = (int)$h_max; }
    if ($chest_min !== '')  { $where .= " AND m.chest_size >= :chest_min"; $binds['chest_min']  = (int)$chest_min; }
    if ($chest_max !== '')  { $where .= " AND m.chest_size <= :chest_max"; $binds['chest_max']  = (int)$chest_max; }
    if ($waist_min !== '')  { $where .= " AND m.waist_size >= :waist_min"; $binds['waist_min']  = (int)$waist_min; }
    if ($waist_max !== '')  { $where .= " AND m.waist_size <= :waist_max"; $binds['waist_max']  = (int)$waist_max; }
    if ($hip_min !== '')    { $where .= " AND m.hip_size >= :hip_min";     $binds['hip_min']    = (int)$hip_min; }
    if ($hip_max !== '')    { $where .= " AND m.hip_size <= :hip_max";     $binds['hip_max']    = (int)$hip_max; }
    if ($shoe_min !== '')   { $where .= " AND m.shoe_size >= :shoe_min";   $binds['shoe_min']   = (int)$shoe_min; }
    if ($shoe_max !== '')   { $where .= " AND m.shoe_size <= :shoe_max";   $binds['shoe_max']   = (int)$shoe_max; }
    if ($eye_color_id !== '') { $where .= " AND m.eye_color_id = :eye_id"; $binds['eye_id']     = (int)$eye_color_id; }
    if ($hair_color_id !== '') { $where .= " AND m.hair_color_id = :hair_id"; $binds['hair_id'] = (int)$hair_color_id; }
    if ($ethnicity_id !== '') { $where .= " AND m.ethnicity_id = :eth_id"; $binds['eth_id']     = (int)$ethnicity_id; }

    $stmt = $db->prepare("
        SELECT DISTINCT ON (u.id)
               u.id, u.full_name, u.specific_profession, u.city, u.country,
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
        ORDER BY u.id, u.full_name
    ");
    $stmt->execute($binds);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFilterParams($src) {
    return [
        'q'             => trim($src['q'] ?? ''),
        'profession'    => trim($src['profession'] ?? ''),
        'city'          => trim($src['city'] ?? ''),
        'country'       => trim($src['country'] ?? ''),
        'tag'           => trim($src['tag'] ?? ''),
        'h_min'         => trim($src['h_min'] ?? ''),
        'h_max'         => trim($src['h_max'] ?? ''),
        'chest_min'     => trim($src['chest_min'] ?? ''),
        'chest_max'     => trim($src['chest_max'] ?? ''),
        'waist_min'     => trim($src['waist_min'] ?? ''),
        'waist_max'     => trim($src['waist_max'] ?? ''),
        'hip_min'       => trim($src['hip_min'] ?? ''),
        'hip_max'       => trim($src['hip_max'] ?? ''),
        'shoe_min'      => trim($src['shoe_min'] ?? ''),
        'shoe_max'      => trim($src['shoe_max'] ?? ''),
        'eye_color_id'  => trim($src['eye_color_id'] ?? ''),
        'hair_color_id' => trim($src['hair_color_id'] ?? ''),
        'ethnicity_id'  => trim($src['ethnicity_id'] ?? ''),
    ];
}

function hasSearch($p) {
    foreach ($p as $v) { if ($v !== '') return true; }
    return false;
}

// ── Réponse AJAX ─────────────────────────────────────────────────────────────
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    $params = getFilterParams($_GET);
    $profiles = hasSearch($params) ? runSearch($db, $params) : [];
    foreach ($profiles as &$p) { $p['age_range'] = getAgeRange($p['birth_date'] ?? null); }
    unset($p);
    echo json_encode(['count' => count($profiles), 'profiles' => $profiles]);
    exit;
}

// ── Rendu initial ─────────────────────────────────────────────────────────────
$params     = getFilterParams($_GET);
$has_search = hasSearch($params);
$profiles   = $has_search ? runSearch($db, $params) : [];

$professions   = $db->query("SELECT name FROM professions ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$countries     = $db->query("SELECT DISTINCT country FROM users WHERE country IS NOT NULL AND country != '' ORDER BY country")->fetchAll(PDO::FETCH_COLUMN);
$eye_colors    = $db->query("SELECT id, name FROM eye_colors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$hair_colors   = $db->query("SELECT id, name FROM hair_colors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$ethnicities   = $db->query("SELECT id, name FROM ethnicities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$raw_tags      = $db->query("SELECT expertise_tags FROM users WHERE expertise_tags IS NOT NULL AND expertise_tags != ''")->fetchAll(PDO::FETCH_COLUMN);
$all_tags = [];
foreach ($raw_tags as $row) {
    foreach (explode(',', $row) as $t) { $t = trim($t); if ($t) $all_tags[$t] = true; }
}
ksort($all_tags);
$all_tags = array_keys($all_tags);

$show_measurements = in_array($params['profession'], $talent_professions);
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche — ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        #search-zone { transition: padding-top 0.45s cubic-bezier(0.4,0,0.2,1); padding-top: 28vh; }
        #search-zone.has-results { padding-top: 40px; }
        #results-area { transition: opacity 0.2s ease; }
        #results-area.loading { opacity: 0.4; pointer-events: none; }
        .result-card { animation: fadeInUp 0.18s ease both; }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

        #mensuration-filters {
            overflow: hidden;
            max-height: 0;
            opacity: 0;
            transition: max-height 0.35s cubic-bezier(0.4,0,0.2,1), opacity 0.25s ease, margin-top 0.3s ease;
            margin-top: 0;
        }
        #mensuration-filters.visible {
            max-height: 200px;
            opacity: 1;
            margin-top: 8px;
        }

        .range-pair { display:flex; align-items:center; gap:6px; }
        .range-input {
            width: 72px;
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 6px 12px;
            font-size: 13px;
            color: #fff;
            outline: none;
            transition: border-color 0.15s;
        }
        .range-input:focus { border-color: #d4a5d4; }
        .range-sep { color: #444; font-size: 12px; }
        .range-label { font-size: 11px; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; }
        @media (max-width: 768px) {
          #search-zone { padding: 0 12px 100px; }
          #search-bar-wrap { padding-top: 40px !important; }
          #mensuration-filters { max-height: none !important; flex-wrap: wrap; }
          #mensuration-filters.visible { max-height: 600px; }
          .range-pair { flex-wrap: wrap; }
          .range-input { width: 60px; }
          #results-grid { grid-template-columns: repeat(2, 1fr) !important; }
          .rech-inline-filters { flex-wrap: wrap; gap: 6px !important; }
          .rech-inline-filters select, .rech-inline-filters input { font-size: 12px !important; padding: 6px 10px !important; }
          #mobile-topbar { display: none !important; }
          #rech-mobile-toprow { display: flex !important; }
        }
    </style>
</head>
<body class="bg-black text-white font-['Open_Sans',sans-serif]">
<?php include 'includes/header.php'; ?>

<div class="max-w-[1200px] mx-auto px-8 pb-20">

    <div id="rech-mobile-toprow" style="display:none; justify-content:flex-end; align-items:center; gap:8px; padding:10px 0 4px;">
      <a href="preferences.php" class="mtop-btn" title="Plus">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      </a>
      <?php if ($current_user_id ?? false): ?>
        <a href="profil.php" class="mtop-avatar" title="Mon profil">
          <?php if ($user_avatar ?? null): ?><img src="<?= htmlspecialchars($user_avatar) ?>" alt="Profil"><?php else: ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <?php endif; ?>
        </a>
      <?php else: ?>
        <a href="connexion.php" class="mtop-avatar" title="Se connecter">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
        </a>
      <?php endif; ?>
    </div>

    <div id="search-zone" class="<?= $has_search ? 'has-results' : '' ?>">

        <div id="search-title" class="text-center mb-8 <?= $has_search ? 'hidden' : '' ?>">
            <h1 class="text-2xl font-bold mb-1">Recherche</h1>
            <p class="text-[#555] text-sm">Trouvez un talent par nom, profession, ville ou tag.</p>
        </div>

        <!-- Barre de recherche -->
        <div class="relative mb-3">
            <svg class="absolute left-5 top-1/2 -translate-y-1/2 w-5 h-5 text-[#555] pointer-events-none" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" id="search-input" value="<?= htmlspecialchars($params['q']) ?>"
                   placeholder="Rechercher un talent, une profession, une ville…"
                   autocomplete="off"
                   class="w-full bg-[#111] border border-[#2a2a2a] rounded-2xl pl-14 pr-12 py-4 text-white text-base outline-none focus:border-[#d4a5d4] transition-colors placeholder:text-[#444]">
            <button id="clear-btn" onclick="clearSearch()" class="absolute right-5 top-1/2 -translate-y-1/2 text-[#555] hover:text-white transition-colors text-lg leading-none <?= $params['q'] ? '' : 'hidden' ?>">✕</button>
        </div>

        <!-- Filtres principaux -->
        <div class="flex flex-wrap gap-2">
            <select id="filter-profession" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 pr-8 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Toutes les professions</option>
                <?php foreach ($professions as $prof): ?>
                    <option value="<?= htmlspecialchars($prof) ?>" <?= $params['profession'] === $prof ? 'selected' : '' ?>><?= htmlspecialchars($prof) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filter-country" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 pr-8 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Tous les pays</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $params['country'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="filter-city" value="<?= htmlspecialchars($params['city']) ?>" placeholder="Ville…"
                   class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 py-2 text-sm text-white outline-none focus:border-[#d4a5d4] transition-colors placeholder:text-[#444] w-32">
            <select id="filter-tag" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 pr-8 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Tous les tags</option>
                <?php foreach ($all_tags as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $params['tag'] === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>
            <button id="clear-all-btn" onclick="clearSearch()" class="<?= $has_search ? '' : 'hidden' ?> flex items-center px-4 py-2 text-sm text-[#555] hover:text-[#d4a5d4] transition-colors">
                ✕ Effacer
            </button>
        </div>

        <!-- Filtres mensurations (apparaissent si Mannequin / Danseur / Comédien) -->
        <div id="mensuration-filters" class="<?= $show_measurements ? 'visible' : '' ?>">
            <div class="flex flex-wrap gap-x-6 gap-y-3 bg-[#111] border border-[#1a1a1a] rounded-2xl px-6 py-4">

                <div class="range-pair flex-col !items-start gap-1">
                    <span class="range-label">Taille (cm)</span>
                    <div class="flex items-center gap-1.5">
                        <input type="number" id="m-h-min" class="range-input" placeholder="Min" min="140" max="220" value="<?= htmlspecialchars($params['h_min']) ?>">
                        <span class="range-sep">—</span>
                        <input type="number" id="m-h-max" class="range-input" placeholder="Max" min="140" max="220" value="<?= htmlspecialchars($params['h_max']) ?>">
                    </div>
                </div>

                <div class="range-pair flex-col !items-start gap-1">
                    <span class="range-label">Poitrine (cm)</span>
                    <div class="flex items-center gap-1.5">
                        <input type="number" id="m-chest-min" class="range-input" placeholder="Min" value="<?= htmlspecialchars($params['chest_min']) ?>">
                        <span class="range-sep">—</span>
                        <input type="number" id="m-chest-max" class="range-input" placeholder="Max" value="<?= htmlspecialchars($params['chest_max']) ?>">
                    </div>
                </div>

                <div class="range-pair flex-col !items-start gap-1">
                    <span class="range-label">Tour de taille (cm)</span>
                    <div class="flex items-center gap-1.5">
                        <input type="number" id="m-waist-min" class="range-input" placeholder="Min" value="<?= htmlspecialchars($params['waist_min']) ?>">
                        <span class="range-sep">—</span>
                        <input type="number" id="m-waist-max" class="range-input" placeholder="Max" value="<?= htmlspecialchars($params['waist_max']) ?>">
                    </div>
                </div>

                <div class="range-pair flex-col !items-start gap-1">
                    <span class="range-label">Hanches (cm)</span>
                    <div class="flex items-center gap-1.5">
                        <input type="number" id="m-hip-min" class="range-input" placeholder="Min" value="<?= htmlspecialchars($params['hip_min']) ?>">
                        <span class="range-sep">—</span>
                        <input type="number" id="m-hip-max" class="range-input" placeholder="Max" value="<?= htmlspecialchars($params['hip_max']) ?>">
                    </div>
                </div>

                <div class="range-pair flex-col !items-start gap-1">
                    <span class="range-label">Pointure</span>
                    <div class="flex items-center gap-1.5">
                        <input type="number" id="m-shoe-min" class="range-input" placeholder="Min" value="<?= htmlspecialchars($params['shoe_min']) ?>">
                        <span class="range-sep">—</span>
                        <input type="number" id="m-shoe-max" class="range-input" placeholder="Max" value="<?= htmlspecialchars($params['shoe_max']) ?>">
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="range-label">Yeux</span>
                    <select id="m-eye" class="bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-3 pr-8 py-1.5 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] cursor-pointer">
                        <option value="">Tous</option>
                        <?php foreach ($eye_colors as $ec): ?>
                            <option value="<?= $ec['id'] ?>" <?= $params['eye_color_id'] == $ec['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ec['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="range-label">Cheveux</span>
                    <select id="m-hair" class="bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-3 pr-8 py-1.5 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] cursor-pointer">
                        <option value="">Tous</option>
                        <?php foreach ($hair_colors as $hc): ?>
                            <option value="<?= $hc['id'] ?>" <?= $params['hair_color_id'] == $hc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($hc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="range-label">Ethnicité</span>
                    <select id="m-ethnicity" class="bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-3 pr-8 py-1.5 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] cursor-pointer">
                        <option value="">Toutes</option>
                        <?php foreach ($ethnicities as $et): ?>
                            <option value="<?= $et['id'] ?>" <?= $params['ethnicity_id'] == $et['id'] ? 'selected' : '' ?>><?= htmlspecialchars($et['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
        </div>

    </div><!-- /search-zone -->

    <!-- Résultats -->
    <div id="results-area" class="mt-6">
        <p id="results-count" class="text-[#555] text-sm mb-5 <?= !$has_search ? 'hidden' : '' ?>">
            <?= count($profiles) ?> résultat<?= count($profiles) > 1 ? 's' : '' ?>
        </p>

        <div id="results-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php if ($has_search && empty($profiles)): ?>
                <div class="col-span-full py-20 text-center">
                    <p class="text-[#555] text-lg mb-1">Aucun résultat</p>
                    <p class="text-[#333] text-sm">Essayez d'autres mots-clés ou filtres.</p>
                </div>
            <?php elseif ($has_search): ?>
                <?php foreach ($profiles as $p):
                    $avatar_url   = $p['profile_picture_url'] ?: $p['fallback_avatar'];
                    $is_talent    = in_array($p['specific_profession'] ?? $p['profession_name'] ?? '', $talent_professions);
                    $age_range    = getAgeRange($p['birth_date'] ?? null);
                    $has_m = $is_talent && ($p['height'] || $p['chest_size'] || $p['waist_size'] || $p['hip_size'] || $p['shoe_size'] || $p['eye_color'] || $p['hair_color'] || $p['ethnicity']);
                ?>
                <a href="profil.php?id=<?= $p['id'] ?>" class="result-card group bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden hover:border-[#333] transition-all">
                    <div class="relative overflow-hidden bg-[#222] aspect-[3/4] flex items-center justify-center">
                        <?php if ($avatar_url): ?>
                            <img src="<?= htmlspecialchars($avatar_url) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($p['full_name']) ?>">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="w-12 h-12 text-[#333]"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?php endif; ?>
                        <?php if ($has_m): ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-end p-5">
                            <div class="text-xs text-[#ddd] w-full space-y-1.5">
                                <?php if ($p['height']): ?><div class="flex justify-between"><span class="text-[#aaa]">HAUTEUR</span><span class="font-semibold"><?= $p['height'] ?> cm</span></div><?php endif; ?>
                                <?php if ($p['chest_size']): ?><div class="flex justify-between"><span class="text-[#aaa]">POITRINE</span><span class="font-semibold"><?= $p['chest_size'] ?></span></div><?php endif; ?>
                                <?php if ($p['cup_size']): ?><div class="flex justify-between"><span class="text-[#aaa]">BONNET</span><span class="font-semibold"><?= $p['cup_size'] ?></span></div><?php endif; ?>
                                <?php if ($p['waist_size']): ?><div class="flex justify-between"><span class="text-[#aaa]">TAILLE</span><span class="font-semibold"><?= $p['waist_size'] ?></span></div><?php endif; ?>
                                <?php if ($p['hip_size']): ?><div class="flex justify-between"><span class="text-[#aaa]">HANCHES</span><span class="font-semibold"><?= $p['hip_size'] ?></span></div><?php endif; ?>
                                <?php if ($p['shoe_size']): ?><div class="flex justify-between"><span class="text-[#aaa]">POINTURE</span><span class="font-semibold"><?= $p['shoe_size'] ?></span></div><?php endif; ?>
                                <?php if ($p['eye_color']): ?><div class="flex justify-between"><span class="text-[#aaa]">YEUX</span><span class="font-semibold"><?= htmlspecialchars($p['eye_color']) ?></span></div><?php endif; ?>
                                <?php if ($p['hair_color']): ?><div class="flex justify-between"><span class="text-[#aaa]">CHEVEUX</span><span class="font-semibold"><?= htmlspecialchars($p['hair_color']) ?></span></div><?php endif; ?>
                                <?php if ($p['ethnicity']): ?><div class="flex justify-between"><span class="text-[#aaa]">ETHNICITÉ</span><span class="font-semibold"><?= htmlspecialchars($p['ethnicity']) ?></span></div><?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="px-5 py-4">
                        <div class="mb-2">
                            <h3 class="font-bold text-white text-sm group-hover:text-[#d4a5d4] transition-colors line-clamp-1"><?= htmlspecialchars($p['full_name']) ?></h3>
                            <p class="text-[#888] text-xs"><?= htmlspecialchars($p['specific_profession'] ?? $p['profession_name'] ?? '') ?></p>
                        </div>
                        <?php if (!empty($p['city']) || $age_range): ?>
                        <div class="text-[#666] text-xs mb-2 space-y-0.5">
                            <?php if (!empty($p['city'])): ?><div><?= htmlspecialchars($p['city']) ?><?= !empty($p['country']) ? ', '.htmlspecialchars($p['country']) : '' ?></div><?php endif; ?>
                            <?php if ($age_range): ?><div><?= $age_range ?></div><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($p['expertise_tags'])): ?>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (array_slice(explode(',', $p['expertise_tags']), 0, 3) as $tag): ?>
                                <?php if (trim($tag)): ?>
                                <span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] text-[9px] font-semibold px-2 py-1 rounded-full"><?= htmlspecialchars(trim($tag)) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
const talentProfessions = <?= json_encode($talent_professions) ?>;

const searchInput    = document.getElementById('search-input');
const clearBtn       = document.getElementById('clear-btn');
const clearAllBtn    = document.getElementById('clear-all-btn');
const searchZone     = document.getElementById('search-zone');
const searchTitle    = document.getElementById('search-title');
const resultsList    = document.getElementById('results-list');
const resultsCount   = document.getElementById('results-count');
const resultsArea    = document.getElementById('results-area');
const mensFilters    = document.getElementById('mensuration-filters');

const selProfession  = document.getElementById('filter-profession');
const selCountry     = document.getElementById('filter-country');
const inputCity      = document.getElementById('filter-city');
const selTag         = document.getElementById('filter-tag');

const mInputs = {
    h_min: document.getElementById('m-h-min'),
    h_max: document.getElementById('m-h-max'),
    chest_min: document.getElementById('m-chest-min'),
    chest_max: document.getElementById('m-chest-max'),
    waist_min: document.getElementById('m-waist-min'),
    waist_max: document.getElementById('m-waist-max'),
    hip_min: document.getElementById('m-hip-min'),
    hip_max: document.getElementById('m-hip-max'),
    shoe_min: document.getElementById('m-shoe-min'),
    shoe_max: document.getElementById('m-shoe-max'),
    eye_color_id: document.getElementById('m-eye'),
    hair_color_id: document.getElementById('m-hair'),
    ethnicity_id: document.getElementById('m-ethnicity'),
};

let debounceTimer = null;

function toggleMensFilters(show) {
    mensFilters.classList.toggle('visible', show);
    if (!show) {
        Object.values(mInputs).forEach(el => { el.value = ''; });
    }
}

selProfession.addEventListener('change', () => {
    const isTalent = talentProfessions.includes(selProfession.value);
    toggleMensFilters(isTalent);
    doSearch();
});

function esc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function buildCard(p) {
    const avatar = p.profile_picture_url || p.fallback_avatar;
    const avatarHtml = avatar
        ? `<img src="${esc(avatar)}" class="w-full h-full object-cover" alt="${esc(p.full_name)}">`
        : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="w-12 h-12 text-[#333]"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`;

    const isTalent = talentProfessions.includes(p.specific_profession || p.profession_name || '');
    const mRows = [
        p.height      ? `<div class="flex justify-between"><span class="text-[#aaa]">HAUTEUR</span><span class="font-semibold">${esc(p.height)} cm</span></div>` : '',
        p.chest_size  ? `<div class="flex justify-between"><span class="text-[#aaa]">POITRINE</span><span class="font-semibold">${esc(p.chest_size)}</span></div>` : '',
        p.cup_size    ? `<div class="flex justify-between"><span class="text-[#aaa]">BONNET</span><span class="font-semibold">${esc(p.cup_size)}</span></div>` : '',
        p.waist_size  ? `<div class="flex justify-between"><span class="text-[#aaa]">TAILLE</span><span class="font-semibold">${esc(p.waist_size)}</span></div>` : '',
        p.hip_size    ? `<div class="flex justify-between"><span class="text-[#aaa]">HANCHES</span><span class="font-semibold">${esc(p.hip_size)}</span></div>` : '',
        p.shoe_size   ? `<div class="flex justify-between"><span class="text-[#aaa]">POINTURE</span><span class="font-semibold">${esc(p.shoe_size)}</span></div>` : '',
        p.eye_color   ? `<div class="flex justify-between"><span class="text-[#aaa]">YEUX</span><span class="font-semibold">${esc(p.eye_color)}</span></div>` : '',
        p.hair_color  ? `<div class="flex justify-between"><span class="text-[#aaa]">CHEVEUX</span><span class="font-semibold">${esc(p.hair_color)}</span></div>` : '',
        p.ethnicity   ? `<div class="flex justify-between"><span class="text-[#aaa]">ETHNICITÉ</span><span class="font-semibold">${esc(p.ethnicity)}</span></div>` : '',
    ].join('');
    const overlay = (isTalent && mRows.trim())
        ? `<div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-end p-5"><div class="text-xs text-[#ddd] w-full space-y-1.5">${mRows}</div></div>`
        : '';

    const profession = esc(p.specific_profession || p.profession_name || '');
    const city  = p.city ? `<div>${esc(p.city)}${p.country ? ', '+esc(p.country) : ''}</div>` : '';
    const age   = p.age_range ? `<div>${esc(p.age_range)}</div>` : '';
    const tags  = (p.expertise_tags||'').split(',').slice(0,3).filter(t=>t.trim()).map(t=>
        `<span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] text-[9px] font-semibold px-2 py-1 rounded-full">${esc(t.trim())}</span>`
    ).join('');

    return `<a href="profil.php?id=${p.id}" class="result-card group bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden hover:border-[#333] transition-all">
        <div class="relative overflow-hidden bg-[#222] aspect-[3/4] flex items-center justify-center">${avatarHtml}${overlay}</div>
        <div class="px-5 py-4">
            <div class="mb-2">
                <h3 class="font-bold text-white text-sm group-hover:text-[#d4a5d4] transition-colors line-clamp-1">${esc(p.full_name)}</h3>
                <p class="text-[#888] text-xs">${profession}</p>
            </div>
            ${(city||age) ? `<div class="text-[#666] text-xs mb-2 space-y-0.5">${city}${age}</div>` : ''}
            ${tags ? `<div class="flex flex-wrap gap-1">${tags}</div>` : ''}
        </div>
    </a>`;
}

function getFilters() {
    const f = {
        q: searchInput.value.trim(),
        profession: selProfession.value,
        country: selCountry.value,
        city: inputCity.value.trim(),
        tag: selTag.value,
    };
    Object.entries(mInputs).forEach(([k, el]) => { f[k] = el.value.trim(); });
    return f;
}

function hasAny(f) { return Object.values(f).some(v => v !== ''); }

function doSearch() {
    const f = getFilters();
    clearBtn.classList.toggle('hidden', !f.q);

    if (!hasAny(f)) {
        searchZone.classList.remove('has-results');
        searchTitle.classList.remove('hidden');
        clearAllBtn.classList.add('hidden');
        resultsCount.classList.add('hidden');
        resultsList.innerHTML = '';
        return;
    }

    searchZone.classList.add('has-results');
    searchTitle.classList.add('hidden');
    clearAllBtn.classList.remove('hidden');
    resultsArea.classList.add('loading');

    fetch('recherche.php?' + new URLSearchParams({ajax:'1', ...f}))
        .then(r => r.json())
        .then(data => {
            resultsArea.classList.remove('loading');
            resultsCount.classList.remove('hidden');
            if (data.count === 0) {
                resultsCount.textContent = 'Aucun résultat';
                resultsList.innerHTML = `<div class="col-span-full py-20 text-center"><p class="text-[#555] text-lg mb-1">Aucun résultat</p><p class="text-[#333] text-sm">Essayez d'autres mots-clés ou filtres.</p></div>`;
            } else {
                resultsCount.textContent = data.count + ' résultat' + (data.count > 1 ? 's' : '');
                resultsList.innerHTML = data.profiles.map(buildCard).join('');
            }
        })
        .catch(() => resultsArea.classList.remove('loading'));
}

function clearSearch() {
    searchInput.value = ''; selProfession.value = ''; selCountry.value = ''; inputCity.value = ''; selTag.value = '';
    toggleMensFilters(false);
    doSearch();
    searchInput.focus();
}

searchInput.addEventListener('input', () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(doSearch, 350); });
inputCity.addEventListener('input', () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(doSearch, 400); });
[selCountry, selTag].forEach(el => el.addEventListener('change', doSearch));
Object.values(mInputs).forEach(el => {
    const ev = el.tagName === 'SELECT' ? 'change' : 'input';
    el.addEventListener(ev, () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(doSearch, 400); });
});
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
