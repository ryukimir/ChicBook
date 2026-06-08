<?php
session_start();
require_once 'config/database.php';
require_once 'models/User.php';

$db = Database::getInstance()->getConnection();

$talent_professions = ['Mannequin', 'Danseur', 'Comédien'];

function getAgeRange($birthDate) {
    if (!$birthDate) return null;
    $age = (new DateTime())->diff(new DateTime($birthDate))->y;
    foreach ([[18,24],[25,29],[30,34],[35,39],[40,44],[45,49],[50,54],[55,59]] as $r) {
        if ($age >= $r[0] && $age <= $r[1]) return $r[0].' à '.$r[1].' ans';
    }
    return $age >= 60 ? '60 et plus' : null;
}

function runSearch($db, $q, $filter_profession, $filter_city, $filter_country, $filter_tag) {
    $where = "1=1";
    $binds = [];
    if ($q !== '') {
        $where .= " AND (u.full_name ILIKE :q OR u.specific_profession ILIKE :q2 OR u.expertise_tags ILIKE :q3 OR u.city ILIKE :q4)";
        $binds['q'] = "%$q%"; $binds['q2'] = "%$q%"; $binds['q3'] = "%$q%"; $binds['q4'] = "%$q%";
    }
    if ($filter_profession) { $where .= " AND (u.specific_profession ILIKE :prof OR p.name ILIKE :prof2)"; $binds['prof'] = $filter_profession; $binds['prof2'] = $filter_profession; }
    if ($filter_city)       { $where .= " AND u.city ILIKE :city";      $binds['city']    = "%$filter_city%"; }
    if ($filter_country)    { $where .= " AND u.country = :country";    $binds['country'] = $filter_country; }
    if ($filter_tag)        { $where .= " AND u.expertise_tags ILIKE :tag"; $binds['tag'] = "%$filter_tag%"; }

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

// ── Réponse AJAX ─────────────────────────────────────────────────────────────
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    $q                 = trim($_GET['q'] ?? '');
    $filter_profession = trim($_GET['profession'] ?? '');
    $filter_city       = trim($_GET['city'] ?? '');
    $filter_country    = trim($_GET['country'] ?? '');
    $filter_tag        = trim($_GET['tag'] ?? '');
    $has_search = $q !== '' || $filter_profession !== '' || $filter_city !== '' || $filter_country !== '' || $filter_tag !== '';
    $profiles = $has_search ? runSearch($db, $q, $filter_profession, $filter_city, $filter_country, $filter_tag) : [];
    // Ajouter age_range côté PHP
    foreach ($profiles as &$p) {
        $p['age_range'] = getAgeRange($p['birth_date'] ?? null);
    }
    unset($p);
    echo json_encode(['count' => count($profiles), 'profiles' => $profiles]);
    exit;
}

// ── Rendu initial ─────────────────────────────────────────────────────────────
$q                 = trim($_GET['q'] ?? '');
$filter_profession = trim($_GET['profession'] ?? '');
$filter_city       = trim($_GET['city'] ?? '');
$filter_country    = trim($_GET['country'] ?? '');
$filter_tag        = trim($_GET['tag'] ?? '');
$has_search = $q !== '' || $filter_profession !== '' || $filter_city !== '' || $filter_country !== '' || $filter_tag !== '';
$profiles = $has_search ? runSearch($db, $q, $filter_profession, $filter_city, $filter_country, $filter_tag) : [];

$professions = $db->query("SELECT name FROM professions ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$countries   = $db->query("SELECT DISTINCT country FROM users WHERE country IS NOT NULL AND country != '' ORDER BY country")->fetchAll(PDO::FETCH_COLUMN);
$raw_tags    = $db->query("SELECT expertise_tags FROM users WHERE expertise_tags IS NOT NULL AND expertise_tags != ''")->fetchAll(PDO::FETCH_COLUMN);
$all_tags = [];
foreach ($raw_tags as $row) {
    foreach (explode(',', $row) as $t) { $t = trim($t); if ($t) $all_tags[$t] = true; }
}
ksort($all_tags);
$all_tags = array_keys($all_tags);
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
        #search-zone {
            transition: padding-top 0.45s cubic-bezier(0.4,0,0.2,1);
            padding-top: 28vh;
        }
        #search-zone.has-results { padding-top: 40px; }
        #results-area { transition: opacity 0.2s ease; }
        #results-area.loading { opacity: 0.4; pointer-events: none; }
        .result-card { animation: fadeInUp 0.18s ease both; }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(6px); }
            to   { opacity:1; transform:translateY(0); }
        }
    </style>
</head>
<body class="bg-black text-white font-['Open_Sans',sans-serif]">
<?php include 'includes/header.php'; ?>

<div class="max-w-[1200px] mx-auto px-8 pb-20">

    <div id="search-zone" class="<?= $has_search ? 'has-results' : '' ?>">

        <!-- Titre (masqué après recherche) -->
        <div id="search-title" class="text-center mb-8 <?= $has_search ? 'hidden' : '' ?>">
            <h1 class="text-2xl font-bold mb-1">Recherche</h1>
            <p class="text-[#555] text-sm">Trouvez un talent par nom, profession, ville ou tag.</p>
        </div>

        <!-- Barre de recherche -->
        <div class="relative mb-3">
            <svg class="absolute left-5 top-1/2 -translate-y-1/2 w-5 h-5 text-[#555] pointer-events-none" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" id="search-input" value="<?= htmlspecialchars($q) ?>"
                   placeholder="Rechercher un talent, une profession, une ville…"
                   autocomplete="off"
                   class="w-full bg-[#111] border border-[#2a2a2a] rounded-2xl pl-14 pr-12 py-4 text-white text-base outline-none focus:border-[#d4a5d4] transition-colors placeholder:text-[#444]">
            <button id="clear-btn" onclick="clearSearch()" class="absolute right-5 top-1/2 -translate-y-1/2 text-[#555] hover:text-white transition-colors text-lg leading-none <?= $q ? '' : 'hidden' ?>">✕</button>
        </div>

        <!-- Filtres -->
        <div class="flex flex-wrap gap-2 mb-6">
            <select id="filter-profession" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 pr-8 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Toutes les professions</option>
                <?php foreach ($professions as $prof): ?>
                    <option value="<?= htmlspecialchars($prof) ?>" <?= $filter_profession === $prof ? 'selected' : '' ?>><?= htmlspecialchars($prof) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filter-country" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 pr-8 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Tous les pays</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $filter_country === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="filter-city" value="<?= htmlspecialchars($filter_city) ?>" placeholder="Ville…"
                   class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 py-2 text-sm text-white outline-none focus:border-[#d4a5d4] transition-colors placeholder:text-[#444] w-32">
            <select id="filter-tag" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 pr-8 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Tous les tags</option>
                <?php foreach ($all_tags as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $filter_tag === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>
            <button id="clear-all-btn" onclick="clearSearch()" class="<?= $has_search ? '' : 'hidden' ?> flex items-center px-4 py-2 text-sm text-[#555] hover:text-[#d4a5d4] transition-colors">
                ✕ Effacer
            </button>
        </div>
    </div>

    <!-- Résultats -->
    <div id="results-area">
        <p id="results-count" class="text-[#555] text-sm mb-5 <?= !$has_search ? 'hidden' : '' ?>">
            <?= count($profiles) ?> résultat<?= count($profiles) > 1 ? 's' : '' ?>
        </p>

        <div id="results-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php if (!$has_search): ?>
                <div></div><!-- grille vide, état géré par JS -->
            <?php elseif (empty($profiles)): ?>
                <div class="col-span-full py-20 text-center">
                    <p class="text-[#555] text-lg mb-1">Aucun résultat</p>
                    <p class="text-[#333] text-sm">Essayez d'autres mots-clés ou filtres.</p>
                </div>
            <?php else: ?>
                <?php foreach ($profiles as $p):
                    $avatar_url = $p['profile_picture_url'] ?: $p['fallback_avatar'];
                    $is_talent  = in_array($p['specific_profession'] ?? $p['profession_name'] ?? '', $talent_professions);
                    $age_range  = getAgeRange($p['birth_date'] ?? null);
                    $has_measurements = $is_talent && ($p['height'] || $p['chest_size'] || $p['waist_size'] || $p['hip_size'] || $p['shoe_size'] || $p['eye_color'] || $p['hair_color'] || $p['ethnicity']);
                ?>
                <a href="profil.php?id=<?= $p['id'] ?>"
                   class="result-card group bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden hover:border-[#333] transition-all">

                    <!-- Image portrait -->
                    <div class="relative overflow-hidden bg-[#222] aspect-[3/4] flex items-center justify-center">
                        <?php if ($avatar_url): ?>
                            <img src="<?= htmlspecialchars($avatar_url) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($p['full_name']) ?>">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="w-12 h-12 text-[#333]"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?php endif; ?>

                        <?php if ($has_measurements): ?>
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

                    <!-- Infos -->
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
const searchInput  = document.getElementById('search-input');
const clearBtn     = document.getElementById('clear-btn');
const clearAllBtn  = document.getElementById('clear-all-btn');
const searchZone   = document.getElementById('search-zone');
const searchTitle  = document.getElementById('search-title');
const resultsList  = document.getElementById('results-list');
const resultsCount = document.getElementById('results-count');
const resultsArea  = document.getElementById('results-area');
const selProfession = document.getElementById('filter-profession');
const selCountry    = document.getElementById('filter-country');
const inputCity     = document.getElementById('filter-city');
const selTag        = document.getElementById('filter-tag');

let debounceTimer = null;

function esc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function buildCard(p) {
    const avatar = p.profile_picture_url || p.fallback_avatar;
    const avatarHtml = avatar
        ? `<img src="${esc(avatar)}" class="w-full h-full object-cover" alt="${esc(p.full_name)}">`
        : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="w-12 h-12 text-[#333]"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`;

    const isTalent = talentProfessions.includes(p.specific_profession || p.profession_name || '');
    const fields = [
        p.height       ? `<div class="flex justify-between"><span class="text-[#aaa]">HAUTEUR</span><span class="font-semibold">${esc(p.height)} cm</span></div>` : '',
        p.chest_size   ? `<div class="flex justify-between"><span class="text-[#aaa]">POITRINE</span><span class="font-semibold">${esc(p.chest_size)}</span></div>` : '',
        p.cup_size     ? `<div class="flex justify-between"><span class="text-[#aaa]">BONNET</span><span class="font-semibold">${esc(p.cup_size)}</span></div>` : '',
        p.waist_size   ? `<div class="flex justify-between"><span class="text-[#aaa]">TAILLE</span><span class="font-semibold">${esc(p.waist_size)}</span></div>` : '',
        p.hip_size     ? `<div class="flex justify-between"><span class="text-[#aaa]">HANCHES</span><span class="font-semibold">${esc(p.hip_size)}</span></div>` : '',
        p.shoe_size    ? `<div class="flex justify-between"><span class="text-[#aaa]">POINTURE</span><span class="font-semibold">${esc(p.shoe_size)}</span></div>` : '',
        p.eye_color    ? `<div class="flex justify-between"><span class="text-[#aaa]">YEUX</span><span class="font-semibold">${esc(p.eye_color)}</span></div>` : '',
        p.hair_color   ? `<div class="flex justify-between"><span class="text-[#aaa]">CHEVEUX</span><span class="font-semibold">${esc(p.hair_color)}</span></div>` : '',
        p.ethnicity    ? `<div class="flex justify-between"><span class="text-[#aaa]">ETHNICITÉ</span><span class="font-semibold">${esc(p.ethnicity)}</span></div>` : '',
    ].join('');
    const hasM = isTalent && fields.trim();
    const overlay = hasM ? `<div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-end p-5"><div class="text-xs text-[#ddd] w-full space-y-1.5">${fields}</div></div>` : '';

    const profession = esc(p.specific_profession || p.profession_name || '');
    const city = p.city ? `<div>${esc(p.city)}${p.country ? ', '+esc(p.country) : ''}</div>` : '';
    const age  = p.age_range ? `<div>${esc(p.age_range)}</div>` : '';
    const cityBlock = (city||age) ? `<div class="text-[#666] text-xs mb-2 space-y-0.5">${city}${age}</div>` : '';
    const tags = (p.expertise_tags||'').split(',').slice(0,3).filter(t=>t.trim()).map(t=>
        `<span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] text-[9px] font-semibold px-2 py-1 rounded-full">${esc(t.trim())}</span>`
    ).join('');
    const tagsBlock = tags ? `<div class="flex flex-wrap gap-1">${tags}</div>` : '';

    return `<a href="profil.php?id=${p.id}" class="result-card group bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden hover:border-[#333] transition-all">
        <div class="relative overflow-hidden bg-[#222] aspect-[3/4] flex items-center justify-center">${avatarHtml}${overlay}</div>
        <div class="px-5 py-4">
            <div class="mb-2">
                <h3 class="font-bold text-white text-sm group-hover:text-[#d4a5d4] transition-colors line-clamp-1">${esc(p.full_name)}</h3>
                <p class="text-[#888] text-xs">${profession}</p>
            </div>
            ${cityBlock}${tagsBlock}
        </div>
    </a>`;
}

function getFilters() {
    return { q: searchInput.value.trim(), profession: selProfession.value, country: selCountry.value, city: inputCity.value.trim(), tag: selTag.value };
}
function hasAny(f) { return f.q || f.profession || f.country || f.city || f.tag; }

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
    doSearch();
    searchInput.focus();
}

searchInput.addEventListener('input', () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(doSearch, 350); });
inputCity.addEventListener('input', () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(doSearch, 400); });
[selProfession, selCountry, selTag].forEach(el => el.addEventListener('change', doSearch));
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
