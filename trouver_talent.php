<?php
session_start();
require_once 'config/database.php';
require_once 'models/User.php';

$is_logged_in = isset($_SESSION['user_id']);
$db = Database::getInstance()->getConnection();

$categories = [
    'creation-design' => [
        'label' => 'Création & Design',
        'professions' => ['Styliste', 'Modéliste', 'Designer', 'Illustrateur', 'Directeur artistique'],
    ],
    'image-production' => [
        'label' => 'Image & Production',
        'professions' => ['Photographe', 'Vidéaste', 'Mannequin', 'Maquilleur', 'Coiffeur'],
    ],
    'marques-createurs' => [
        'label' => 'Marques & Créateurs',
        'professions' => ['Marque', 'Créateur', 'Agence', 'Casting director'],
    ],
];

$category = $_GET['category'] ?? 'creation-design';
if (!isset($categories[$category])) $category = 'creation-design';
$cat = $categories[$category];

$profession = $_GET['profession'] ?? $cat['professions'][0];
if (!in_array($profession, $cat['professions'])) $profession = $cat['professions'][0];

$filter_city    = trim($_GET['city'] ?? '');
$filter_country = trim($_GET['country'] ?? '');
$filter_tag     = trim($_GET['tag'] ?? '');

// Query profiles
$where = "(u.specific_profession ILIKE :p1 OR p.name ILIKE :p2)";
$binds = ['p1' => $profession, 'p2' => $profession];
if ($filter_city)    { $where .= " AND u.city ILIKE :city";        $binds['city']    = "%$filter_city%"; }
if ($filter_country) { $where .= " AND u.country = :country";      $binds['country'] = $filter_country; }
if ($filter_tag)     { $where .= " AND u.expertise_tags ILIKE :tag"; $binds['tag']   = "%$filter_tag%"; }

$stmt = $db->prepare("
    SELECT u.id, u.full_name, u.specific_profession, u.city, u.country,
           u.profile_picture_url, u.expertise_tags, p.name AS profession_name
    FROM users u
    LEFT JOIN user_professions up ON u.id = up.user_id
    LEFT JOIN professions p ON up.profession_id = p.id
    WHERE $where
    ORDER BY RANDOM()
");
$stmt->execute($binds);
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countries = $db->query("SELECT DISTINCT country FROM users WHERE country IS NOT NULL AND country != '' ORDER BY country")->fetchAll(PDO::FETCH_COLUMN);

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
    return 'trouver_talent.php?' . http_build_query(array_filter($params));
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trouver un talent — <?= htmlspecialchars($cat['label']) ?> · ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="bg-black text-white font-['Open_Sans',sans-serif]">
<?php include 'includes/header.php'; ?>

<div class="max-w-[1400px] mx-auto px-8 pt-8 pb-20">

    <!-- En-tête catégorie + onglets professions -->
    <div class="mb-8">

        <!-- Tabs catégories -->
        <div class="flex gap-2 mb-6 flex-wrap">
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

        <!-- Dropdown professions -->
        <div class="relative" id="profession-dropdown-wrapper">
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
        (function() {
            const btn = document.getElementById('profession-toggle');
            const menu = document.getElementById('profession-menu');
            const chevron = document.getElementById('profession-chevron');
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
        </script>
    </div>

    <div class="flex gap-8 items-start">

        <!-- Liste des profils -->
        <div class="flex-grow min-w-0">
            <?php if (empty($profiles)): ?>
                <div class="bg-[#111] border border-dashed border-[#2a2a2a] rounded-2xl py-20 text-center">
                    <p class="text-[#555] text-lg mb-2">Aucun profil trouvé</p>
                    <p class="text-[#444] text-sm">Essayez de modifier les filtres ou la profession sélectionnée.</p>
                </div>
            <?php else: ?>
                <p class="text-[#555] text-sm mb-5"><?= count($profiles) ?> profil<?= count($profiles) > 1 ? 's' : '' ?> · <?= htmlspecialchars($profession) ?></p>
                <div class="flex flex-col gap-3">
                    <?php foreach ($profiles as $p): ?>
                        <a href="profil.php?id=<?= $p['id'] ?>"
                           class="flex items-center gap-5 bg-[#111] border border-[#1a1a1a] rounded-2xl px-6 py-4 hover:border-[#333] hover:bg-[#141414] transition-all group">

                            <!-- Avatar -->
                            <div class="w-14 h-14 rounded-full flex-shrink-0 overflow-hidden bg-[#222]">
                                <?php if (!empty($p['profile_picture_url'])): ?>
                                    <img src="<?= htmlspecialchars($p['profile_picture_url']) ?>" class="w-full h-full object-cover" alt="">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-[#444] text-xl">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-7 h-7"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Infos -->
                            <div class="flex-grow min-w-0">
                                <div class="flex items-baseline gap-2 flex-wrap">
                                    <span class="font-bold text-white text-[15px] group-hover:text-[#d4a5d4] transition-colors">
                                        <?= htmlspecialchars($p['full_name']) ?>
                                    </span>
                                    <span class="text-[#555] text-xs">·</span>
                                    <span class="text-[#888] text-sm">
                                        <?= htmlspecialchars($p['specific_profession'] ?? $p['profession_name'] ?? $profession) ?>
                                    </span>
                                </div>
                                <?php if (!empty($p['city'])): ?>
                                    <div class="text-[#555] text-xs mt-0.5">
                                        <?= htmlspecialchars($p['city']) ?><?= !empty($p['country']) ? ', '.htmlspecialchars($p['country']) : '' ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($p['expertise_tags'])): ?>
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        <?php foreach (array_slice(explode(',', $p['expertise_tags']), 0, 4) as $tag): ?>
                                            <?php if (trim($tag)): ?>
                                                <span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase tracking-wide">
                                                    <?= htmlspecialchars(trim($tag)) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Flèche -->
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-[#333] group-hover:text-[#d4a5d4] flex-shrink-0 transition-colors"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Filtres (droite) -->
        <aside class="w-[260px] flex-shrink-0 sticky top-8">
            <form method="GET" action="trouver_talent.php">
                <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                <input type="hidden" name="profession" value="<?= htmlspecialchars($profession) ?>">
                <div class="bg-[#0e0e0e] rounded-2xl p-6 flex flex-col gap-5" style="box-shadow: 0 1px 0 rgba(255,255,255,0.04) inset, 0 -2px 0 rgba(0,0,0,0.8), 0 8px 24px rgba(0,0,0,0.5);">
                    <h3 class="text-white font-bold text-base">Filtres</h3>

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

                    <button type="submit" class="w-full py-2.5 bg-[#d4a5d4] text-black rounded-xl font-bold text-sm hover:opacity-90 transition-opacity border-none cursor-pointer">
                        Rechercher
                    </button>

                    <?php if ($filter_city || $filter_country || $filter_tag): ?>
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
