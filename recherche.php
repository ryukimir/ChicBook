<?php
session_start();
require_once 'config/database.php';
require_once 'models/User.php';

$db = Database::getInstance()->getConnection();

$q              = trim($_GET['q'] ?? '');
$filter_profession = trim($_GET['profession'] ?? '');
$filter_city    = trim($_GET['city'] ?? '');
$filter_country = trim($_GET['country'] ?? '');
$filter_tag     = trim($_GET['tag'] ?? '');

$has_search = $q !== '' || $filter_profession !== '' || $filter_city !== '' || $filter_country !== '' || $filter_tag !== '';

$profiles = [];
if ($has_search) {
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
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Données pour les filtres
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
</head>
<body class="bg-black text-white font-['Open_Sans',sans-serif]">
<?php include 'includes/header.php'; ?>

<div class="max-w-[1100px] mx-auto px-8 pt-12 pb-20">

    <!-- Barre de recherche principale -->
    <form method="GET" action="recherche.php" id="search-form">
        <div class="relative mb-3">
            <svg class="absolute left-5 top-1/2 -translate-y-1/2 w-5 h-5 text-[#555] pointer-events-none" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" name="q" id="search-input" value="<?= htmlspecialchars($q) ?>"
                   placeholder="Rechercher un talent, une profession, une ville…"
                   autocomplete="off"
                   class="w-full bg-[#111] border border-[#2a2a2a] rounded-2xl pl-14 pr-5 py-4 text-white text-base outline-none focus:border-[#d4a5d4] transition-colors placeholder:text-[#444]"
                   style="font-size:16px;">
            <?php if ($q): ?>
                <button type="button" onclick="document.getElementById('search-input').value='';document.getElementById('search-form').submit();"
                        class="absolute right-5 top-1/2 -translate-y-1/2 text-[#555] hover:text-white transition-colors text-xl leading-none">✕</button>
            <?php endif; ?>
        </div>

        <!-- Filtres en ligne -->
        <div class="flex flex-wrap gap-2 mb-8">
            <select name="profession" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Toutes les professions</option>
                <?php foreach ($professions as $prof): ?>
                    <option value="<?= htmlspecialchars($prof) ?>" <?= $filter_profession === $prof ? 'selected' : '' ?>><?= htmlspecialchars($prof) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="country" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Tous les pays</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $filter_country === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="city" value="<?= htmlspecialchars($filter_city) ?>" placeholder="Ville…"
                   class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 py-2 text-sm text-white outline-none focus:border-[#d4a5d4] transition-colors placeholder:text-[#444] w-36">

            <select name="tag" class="bg-[#111] border border-[#2a2a2a] rounded-xl px-4 py-2 text-sm text-[#aaa] outline-none focus:border-[#d4a5d4] transition-colors cursor-pointer">
                <option value="">Tous les tags</option>
                <?php foreach ($all_tags as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $filter_tag === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="bg-[#d4a5d4] text-black font-bold text-sm px-5 py-2 rounded-xl hover:opacity-90 transition-opacity">
                Rechercher
            </button>

            <?php if ($has_search): ?>
                <a href="recherche.php" class="flex items-center px-4 py-2 text-sm text-[#555] hover:text-[#d4a5d4] transition-colors">
                    ✕ Effacer
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- État vide (avant toute recherche) -->
    <?php if (!$has_search): ?>
        <div class="flex flex-col items-center justify-center py-24 text-center">
            <div class="w-16 h-16 rounded-2xl bg-[#111] border border-[#1a1a1a] flex items-center justify-center mb-5">
                <svg class="w-8 h-8 text-[#333]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/>
                </svg>
            </div>
            <p class="text-[#444] text-base font-medium">Recherchez parmi les talents ChicBook</p>
            <p class="text-[#333] text-sm mt-1">Nom, profession, ville, tag…</p>
        </div>

    <!-- Aucun résultat -->
    <?php elseif (empty($profiles)): ?>
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <p class="text-[#555] text-lg mb-1">Aucun résultat</p>
            <p class="text-[#333] text-sm">Essayez d'autres mots-clés ou filtres.</p>
        </div>

    <!-- Résultats -->
    <?php else: ?>
        <p class="text-[#555] text-sm mb-4"><?= count($profiles) ?> résultat<?= count($profiles) > 1 ? 's' : '' ?></p>
        <div class="flex flex-col gap-3">
            <?php foreach ($profiles as $p): ?>
                <a href="profil.php?id=<?= $p['id'] ?>"
                   class="flex items-center gap-5 bg-[#111] border border-[#1a1a1a] rounded-2xl px-6 py-4 hover:border-[#333] hover:bg-[#141414] transition-all group">

                    <?php $avatar_url = $p['profile_picture_url'] ?: $p['fallback_avatar']; ?>
                    <div class="w-14 h-14 rounded-full flex-shrink-0 overflow-hidden bg-[#222]">
                        <?php if ($avatar_url): ?>
                            <img src="<?= htmlspecialchars($avatar_url) ?>" class="w-full h-full object-cover" alt="">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-[#444] text-xl font-bold">
                                <?= mb_strtoupper(mb_substr($p['full_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex-grow min-w-0">
                        <div class="flex items-baseline gap-2 flex-wrap">
                            <span class="font-bold text-white text-[15px] group-hover:text-[#d4a5d4] transition-colors">
                                <?= htmlspecialchars($p['full_name']) ?>
                            </span>
                            <span class="text-[#555] text-xs">·</span>
                            <span class="text-[#888] text-sm">
                                <?= htmlspecialchars($p['specific_profession'] ?? $p['profession_name'] ?? '') ?>
                            </span>
                        </div>
                        <?php if (!empty($p['city'])): ?>
                            <div class="text-[#555] text-xs mt-0.5">
                                <?= htmlspecialchars($p['city']) ?><?= !empty($p['country']) ? ', '.htmlspecialchars($p['country']) : '' ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($p['expertise_tags'])): ?>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <?php foreach (array_slice(explode(',', $p['expertise_tags']), 0, 5) as $tag): ?>
                                    <?php if (trim($tag)): ?>
                                        <span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase tracking-wide">
                                            <?= htmlspecialchars(trim($tag)) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-[#333] group-hover:text-[#d4a5d4] flex-shrink-0 transition-colors">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
// Soumettre automatiquement quand un filtre change (si une recherche est déjà active)
<?php if ($has_search): ?>
document.querySelectorAll('select[name]').forEach(s => {
    s.addEventListener('change', () => document.getElementById('search-form').submit());
});
<?php endif; ?>
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
