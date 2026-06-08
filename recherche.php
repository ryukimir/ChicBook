<?php
session_start();
require_once 'config/database.php';
require_once 'models/User.php';

$db = Database::getInstance()->getConnection();

function runSearch($db, $q, $filter_profession, $filter_city, $filter_country, $filter_tag) {
    $where = "1=1";
    $binds = [];
    if ($q !== '') {
        $where .= " AND (u.full_name ILIKE :q OR u.specific_profession ILIKE :q2 OR u.expertise_tags ILIKE :q3 OR u.city ILIKE :q4)";
        $binds['q'] = "%$q%"; $binds['q2'] = "%$q%"; $binds['q3'] = "%$q%"; $binds['q4'] = "%$q%";
    }
    if ($filter_profession) { $where .= " AND (u.specific_profession ILIKE :prof OR p.name ILIKE :prof2)"; $binds['prof'] = $filter_profession; $binds['prof2'] = $filter_profession; }
    if ($filter_city)       { $where .= " AND u.city ILIKE :city";        $binds['city']    = "%$filter_city%"; }
    if ($filter_country)    { $where .= " AND u.country = :country";      $binds['country'] = $filter_country; }
    if ($filter_tag)        { $where .= " AND u.expertise_tags ILIKE :tag"; $binds['tag']   = "%$filter_tag%"; }

    $stmt = $db->prepare("
        SELECT DISTINCT ON (u.id)
               u.id, u.full_name, u.specific_profession, u.city, u.country,
               u.profile_picture_url, u.expertise_tags, p.name AS profession_name,
               (SELECT image_url FROM portfolios WHERE user_id=u.id ORDER BY position ASC, created_at DESC LIMIT 1) AS fallback_avatar
        FROM users u
        LEFT JOIN user_professions up ON u.id = up.user_id
        LEFT JOIN professions p ON up.profession_id = p.id
        WHERE $where
        ORDER BY u.id, u.full_name
    ");
    $stmt->execute($binds);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Réponse AJAX ──────────────────────────────────────────────────────────
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    $q                 = trim($_GET['q'] ?? '');
    $filter_profession = trim($_GET['profession'] ?? '');
    $filter_city       = trim($_GET['city'] ?? '');
    $filter_country    = trim($_GET['country'] ?? '');
    $filter_tag        = trim($_GET['tag'] ?? '');
    $has_search = $q !== '' || $filter_profession !== '' || $filter_city !== '' || $filter_country !== '' || $filter_tag !== '';
    $profiles = $has_search ? runSearch($db, $q, $filter_profession, $filter_city, $filter_country, $filter_tag) : [];
    echo json_encode(['count' => count($profiles), 'profiles' => $profiles, 'has_search' => $has_search]);
    exit;
}

// ── Rendu initial ─────────────────────────────────────────────────────────
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
    foreach (explode(',', $row) as $t) {
        $t = trim($t);
        if ($t) $all_tags[$t] = true;
    }
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
        #search-zone.has-results {
            padding-top: 40px;
        }
        #results-area {
            transition: opacity 0.25s ease;
        }
        #results-area.loading {
            opacity: 0.4;
            pointer-events: none;
        }
        .result-card {
            animation: fadeInUp 0.18s ease both;
        }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(6px); }
            to   { opacity:1; transform:translateY(0); }
        }
    </style>
</head>
<body class="bg-black text-white font-['Open_Sans',sans-serif]">
<?php include 'includes/header.php'; ?>

<div class="max-w-[900px] mx-auto px-8 pb-20">

    <div id="search-zone" class="<?= $has_search ? 'has-results' : '' ?>">

        <!-- Titre (visible seulement sans recherche) -->
        <div id="search-title" class="text-center mb-8 transition-all duration-400 <?= $has_search ? 'hidden' : '' ?>">
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
            <select id="filter-profession" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Toutes les professions</option>
                <?php foreach ($professions as $prof): ?>
                    <option value="<?= htmlspecialchars($prof) ?>" <?= $filter_profession === $prof ? 'selected' : '' ?>><?= htmlspecialchars($prof) ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filter-country" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Tous les pays</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $filter_country === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="text" id="filter-city" value="<?= htmlspecialchars($filter_city) ?>" placeholder="Ville…"
                   class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 py-2 text-sm text-white outline-none focus:border-[#d4a5d4] transition-colors placeholder:text-[#444] w-32">

            <select id="filter-tag" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Tous les tags</option>
                <?php foreach ($all_tags as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $filter_tag === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>

            <button id="clear-all-btn" onclick="clearSearch()" class="<?= $has_search ? '' : 'hidden' ?> flex items-center px-4 py-2 text-sm text-[#555] hover:text-[#d4a5d4] transition-colors">
                ✕ Effacer
            </button>
        </div>

    </div><!-- /search-zone -->

    <!-- Zone résultats -->
    <div id="results-area">

        <!-- Compteur -->
        <p id="results-count" class="text-[#555] text-sm mb-4 <?= !$has_search ? 'hidden' : '' ?>">
            <?= count($profiles) ?> résultat<?= count($profiles) > 1 ? 's' : '' ?>
        </p>

        <!-- Liste -->
        <div id="results-list" class="flex flex-col gap-3">
            <?php if ($has_search && empty($profiles)): ?>
                <div id="no-results" class="py-20 text-center">
                    <p class="text-[#555] text-lg mb-1">Aucun résultat</p>
                    <p class="text-[#333] text-sm">Essayez d'autres mots-clés ou filtres.</p>
                </div>
            <?php elseif (!$has_search): ?>
                <!-- État vide -->
                <div id="empty-state" class="flex flex-col items-center py-10 text-center">
                    <p class="text-[#333] text-sm">Tapez quelque chose pour commencer.</p>
                </div>
            <?php else: ?>
                <?php foreach ($profiles as $p): ?>
                    <?php $avatar_url = $p['profile_picture_url'] ?: $p['fallback_avatar']; ?>
                    <a href="profil.php?id=<?= $p['id'] ?>"
                       class="result-card flex items-center gap-5 bg-[#111] border border-[#1a1a1a] rounded-2xl px-6 py-4 hover:border-[#333] hover:bg-[#141414] transition-all group">
                        <div class="w-14 h-14 rounded-full flex-shrink-0 overflow-hidden bg-[#222]">
                            <?php if ($avatar_url): ?>
                                <img src="<?= htmlspecialchars($avatar_url) ?>" class="w-full h-full object-cover" alt="">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-[#555] text-xl font-bold">
                                    <?= mb_strtoupper(mb_substr($p['full_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow min-w-0">
                            <div class="flex items-baseline gap-2 flex-wrap">
                                <span class="font-bold text-white text-[15px] group-hover:text-[#d4a5d4] transition-colors"><?= htmlspecialchars($p['full_name']) ?></span>
                                <?php if (!empty($p['specific_profession'] ?? $p['profession_name'])): ?>
                                    <span class="text-[#555] text-xs">·</span>
                                    <span class="text-[#888] text-sm"><?= htmlspecialchars($p['specific_profession'] ?? $p['profession_name']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($p['city'])): ?>
                                <div class="text-[#555] text-xs mt-0.5"><?= htmlspecialchars($p['city']) ?><?= !empty($p['country']) ? ', '.htmlspecialchars($p['country']) : '' ?></div>
                            <?php endif; ?>
                            <?php if (!empty($p['expertise_tags'])): ?>
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <?php foreach (array_slice(explode(',', $p['expertise_tags']), 0, 5) as $tag): ?>
                                        <?php if (trim($tag)): ?>
                                            <span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase tracking-wide"><?= htmlspecialchars(trim($tag)) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-[#333] group-hover:text-[#d4a5d4] flex-shrink-0 transition-colors"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /results-area -->
</div>

<script>
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

function getFilters() {
    return {
        q:          searchInput.value.trim(),
        profession: selProfession.value,
        country:    selCountry.value,
        city:       inputCity.value.trim(),
        tag:        selTag.value,
    };
}

function hasAnyFilter(f) {
    return f.q || f.profession || f.country || f.city || f.tag;
}

function buildCard(p) {
    const avatar = p.profile_picture_url || p.fallback_avatar;
    const avatarHtml = avatar
        ? `<img src="${avatar}" class="w-full h-full object-cover" alt="">`
        : `<div class="w-full h-full flex items-center justify-center text-[#555] text-xl font-bold">${(p.full_name||'?')[0].toUpperCase()}</div>`;

    const profession = p.specific_profession || p.profession_name || '';
    const profHtml = profession
        ? `<span class="text-[#555] text-xs">·</span><span class="text-[#888] text-sm">${escHtml(profession)}</span>`
        : '';

    const city = p.city ? `<div class="text-[#555] text-xs mt-0.5">${escHtml(p.city)}${p.country ? ', '+escHtml(p.country) : ''}</div>` : '';

    const tags = (p.expertise_tags || '').split(',').slice(0,5).filter(t=>t.trim()).map(t =>
        `<span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase tracking-wide">${escHtml(t.trim())}</span>`
    ).join('');
    const tagsHtml = tags ? `<div class="flex flex-wrap gap-1.5 mt-2">${tags}</div>` : '';

    return `<a href="profil.php?id=${p.id}" class="result-card flex items-center gap-5 bg-[#111] border border-[#1a1a1a] rounded-2xl px-6 py-4 hover:border-[#333] hover:bg-[#141414] transition-all group">
        <div class="w-14 h-14 rounded-full flex-shrink-0 overflow-hidden bg-[#222]">${avatarHtml}</div>
        <div class="flex-grow min-w-0">
            <div class="flex items-baseline gap-2 flex-wrap">
                <span class="font-bold text-white text-[15px] group-hover:text-[#d4a5d4] transition-colors">${escHtml(p.full_name)}</span>
                ${profHtml}
            </div>
            ${city}${tagsHtml}
        </div>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-[#333] group-hover:text-[#d4a5d4] flex-shrink-0 transition-colors"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </a>`;
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function doSearch() {
    const f = getFilters();
    const active = hasAnyFilter(f);

    // Animer la barre vers le haut
    if (active) {
        searchZone.classList.add('has-results');
        searchTitle.classList.add('hidden');
        clearBtn.classList.toggle('hidden', !f.q);
        clearAllBtn.classList.remove('hidden');
    } else {
        searchZone.classList.remove('has-results');
        searchTitle.classList.remove('hidden');
        clearBtn.classList.add('hidden');
        clearAllBtn.classList.add('hidden');
        resultsList.innerHTML = `<div class="flex flex-col items-center py-10 text-center"><p class="text-[#333] text-sm">Tapez quelque chose pour commencer.</p></div>`;
        resultsCount.classList.add('hidden');
        return;
    }

    resultsArea.classList.add('loading');

    const params = new URLSearchParams({ ajax: '1', ...f });
    fetch('recherche.php?' + params)
        .then(r => r.json())
        .then(data => {
            resultsArea.classList.remove('loading');
            resultsCount.classList.remove('hidden');
            if (data.count === 0) {
                resultsCount.textContent = 'Aucun résultat';
                resultsList.innerHTML = `<div class="py-20 text-center"><p class="text-[#555] text-lg mb-1">Aucun résultat</p><p class="text-[#333] text-sm">Essayez d'autres mots-clés ou filtres.</p></div>`;
            } else {
                resultsCount.textContent = data.count + ' résultat' + (data.count > 1 ? 's' : '');
                resultsList.innerHTML = data.profiles.map(buildCard).join('');
            }
        })
        .catch(() => resultsArea.classList.remove('loading'));
}

function clearSearch() {
    searchInput.value = '';
    selProfession.value = '';
    selCountry.value = '';
    inputCity.value = '';
    selTag.value = '';
    doSearch();
    searchInput.focus();
}

// Debounce 350ms sur le champ texte
searchInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(doSearch, 350);
});

// Immédiat sur les selects / ville
[selProfession, selCountry, selTag].forEach(el => el.addEventListener('change', doSearch));
inputCity.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(doSearch, 400);
});
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
