<?php
session_start();
require_once 'config/database.php';
require_once 'models/user.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$db = Database::getInstance()->getConnection();
$current_user_id = $_SESSION['user_id'];

// AJAX: toggle favorite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite'])) {
    header('Content-Type: application/json');
    $cid = intval($_POST['casting_id']);
    $check = $db->prepare("SELECT 1 FROM casting_favorites WHERE user_id=:u AND casting_id=:c");
    $check->execute(['u' => $current_user_id, 'c' => $cid]);
    if ($check->rowCount() > 0) {
        $db->prepare("DELETE FROM casting_favorites WHERE user_id=:u AND casting_id=:c")->execute(['u' => $current_user_id, 'c' => $cid]);
        echo json_encode(['favorited' => false]);
    } else {
        $db->prepare("INSERT INTO casting_favorites (user_id, casting_id) VALUES (:u,:c)")->execute(['u' => $current_user_id, 'c' => $cid]);
        echo json_encode(['favorited' => true]);
    }
    exit();
}

// Delete casting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_casting_id'])) {
    $delete_id = intval($_POST['delete_casting_id']);
    $db->prepare("DELETE FROM castings WHERE id=:id AND user_id=:uid")->execute(['id' => $delete_id, 'uid' => $current_user_id]);
    header("Location: castings.php?view=mes_castings");
    exit();
}

$userModel = new User($db);
$user_profile = $userModel->getUserProfile($current_user_id);
$user_profession = $user_profile['profession_name'] ?? 'Inconnu';

$view = $_GET['view'] ?? 'offres';
$filter_country  = trim($_GET['country'] ?? '');
$filter_city     = trim($_GET['city'] ?? '');
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to'] ?? '');

// Favorited IDs for current user
$favStmt = $db->prepare("SELECT casting_id FROM casting_favorites WHERE user_id=:u");
$favStmt->execute(['u' => $current_user_id]);
$favoritedIds = array_column($favStmt->fetchAll(PDO::FETCH_ASSOC), 'casting_id');

// Profiles aggregation sub-expression
$profilesAgg = "COALESCE(json_agg(json_build_object(
    'role_name', cp.role_name,
    'quantity',  cp.quantity,
    'age_range', cp.age_range,
    'gender',    cp.gender,
    'height',    cp.height,
    'shoe_size', cp.shoe_size,
    'waist_size',cp.waist_size,
    'hip_size',  cp.hip_size,
    'chest_size',cp.chest_size,
    'cup_size',  cp.cup_size,
    'eye_color', ec.name,
    'hair_color',hc.name,
    'ethnicity', eth.name
) ORDER BY cp.id) FILTER (WHERE cp.id IS NOT NULL), '[]'::json) as profiles";

// Filter WHERE builder
function filterConditions($params, &$binds) {
    $c = [];
    if (!empty($params['country'])) { $c[] = "c.country = :country"; $binds['country'] = $params['country']; }
    if (!empty($params['city']))    { $c[] = "c.city ILIKE :city";   $binds['city']    = '%'.$params['city'].'%'; }
    if (!empty($params['date_from'])){ $c[] = "c.performance_date >= :date_from"; $binds['date_from'] = $params['date_from']; }
    if (!empty($params['date_to']))  { $c[] = "c.performance_date <= :date_to";   $binds['date_to']   = $params['date_to']; }
    return $c;
}
$fp = ['country'=>$filter_country,'city'=>$filter_city,'date_from'=>$filter_date_from,'date_to'=>$filter_date_to];

$castings = [];
$joins = "JOIN users u ON c.user_id = u.id
          LEFT JOIN casting_profiles cp ON c.id = cp.casting_id
          LEFT JOIN eye_colors ec ON cp.eye_color_id = ec.id
          LEFT JOIN hair_colors hc ON cp.hair_color_id = hc.id
          LEFT JOIN ethnicities eth ON cp.ethnicity_id = eth.id";

if ($view === 'mes_castings') {
    $binds = ['uid' => $current_user_id];
    $fc = filterConditions($fp, $binds);
    $where = "c.user_id = :uid" . ($fc ? " AND ".implode(" AND ",$fc) : "");
    $stmt = $db->prepare("SELECT c.*, u.full_name as creator_name, $profilesAgg FROM castings c $joins WHERE $where GROUP BY c.id, u.full_name ORDER BY c.created_at DESC");
    $stmt->execute($binds);
    $castings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($view === 'favoris') {
    $binds = ['uid' => $current_user_id];
    $fc = filterConditions($fp, $binds);
    $where = "cf.user_id = :uid" . ($fc ? " AND ".implode(" AND ",$fc) : "");
    $stmt = $db->prepare("SELECT c.*, u.full_name as creator_name, $profilesAgg FROM castings c JOIN casting_favorites cf ON c.id = cf.casting_id $joins WHERE $where GROUP BY c.id, u.full_name ORDER BY c.created_at DESC");
    $stmt->execute($binds);
    $castings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    $binds = ['profession' => $user_profession, 'uid' => $current_user_id];
    $fc = filterConditions($fp, $binds);
    $where = "c.id IN (SELECT DISTINCT casting_id FROM casting_profiles WHERE role_name = :profession) AND c.user_id != :uid" . ($fc ? " AND ".implode(" AND ",$fc) : "");
    $stmt = $db->prepare("SELECT c.*, u.full_name as creator_name, $profilesAgg FROM castings c $joins WHERE $where GROUP BY c.id, u.full_name ORDER BY c.created_at DESC");
    $stmt->execute($binds);
    $castings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Location data for filters
$locRows = $db->query("SELECT DISTINCT country, city FROM castings WHERE country IS NOT NULL AND country != '' ORDER BY country, city")->fetchAll(PDO::FETCH_ASSOC);
$countries = array_unique(array_filter(array_column($locRows, 'country')));
sort($countries);
$citiesByCountry = [];
foreach ($locRows as $r) {
    if ($r['country'] && $r['city']) $citiesByCountry[$r['country']][] = $r['city'];
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Castings - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand:'#d4a5d4', dark:'#1a1a1a' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css" />
</head>
<body class="bg-black font-['Arial',sans-serif] text-white">
    <?php include 'includes/header.php'; ?>

    <div class="max-w-[1400px] mx-auto mt-10 mb-10 px-8 flex gap-10">

        <main class="flex-grow min-w-0">
            <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
                <div class="flex gap-2 flex-wrap">
                    <a href="castings.php?view=offres<?= $filter_country ? '&country='.urlencode($filter_country) : '' ?><?= $filter_city ? '&city='.urlencode($filter_city) : '' ?><?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?>"
                       class="px-6 py-2.5 rounded-2xl font-bold text-base border transition-all <?= $view === 'offres' ? 'bg-[#d4a5d4] text-[#1a1a1a] border-[#d4a5d4]' : 'text-[#aaa] border-[#444] hover:bg-[#333] hover:text-white' ?>">
                        Opportunités pour <?= htmlspecialchars($user_profession) ?>
                    </a>
                    <a href="castings.php?view=favoris<?= $filter_country ? '&country='.urlencode($filter_country) : '' ?><?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?>"
                       class="px-6 py-2.5 rounded-2xl font-bold text-base border transition-all <?= $view === 'favoris' ? 'bg-[#d4a5d4] text-[#1a1a1a] border-[#d4a5d4]' : 'text-[#aaa] border-[#444] hover:bg-[#333] hover:text-white' ?>">
                        ♡ Favoris <span class="text-xs">(<?= count($favoritedIds) ?>)</span>
                    </a>
                    <a href="castings.php?view=mes_castings<?= $filter_country ? '&country='.urlencode($filter_country) : '' ?><?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?>"
                       class="px-6 py-2.5 rounded-2xl font-bold text-base border transition-all <?= $view === 'mes_castings' ? 'bg-[#d4a5d4] text-[#1a1a1a] border-[#d4a5d4]' : 'text-[#aaa] border-[#444] hover:bg-[#333] hover:text-white' ?>">
                        Mes Castings créés
                    </a>
                </div>
                <?php if ($view !== 'mes_castings'): ?>
                    <a href="creer_casting.php" class="inline-block bg-[#d4a5d4] text-[#111] px-6 py-3 rounded-xl font-bold text-sm no-underline uppercase hover:bg-[#c08bc0] hover:-translate-y-0.5 transition-all">+ Créer un casting</a>
                <?php endif; ?>
            </div>

            <?php if (empty($castings)): ?>
                <div class="bg-[#1a1a1a] border border-dashed border-[#444] rounded-xl py-16 px-5 text-center">
                    <?php if ($view === 'mes_castings'): ?>
                        <h3 class="text-xl mb-3">Aucun casting créé</h3>
                        <p class="text-[#aaa] mb-6">Publiez votre premier casting pour trouver les meilleurs talents.</p>
                        <a href="creer_casting.php" class="inline-block bg-[#d4a5d4] text-[#111] px-8 py-3 rounded-lg font-bold no-underline uppercase hover:bg-[#c08bc0] transition-colors">Créer mon premier casting</a>
                    <?php elseif ($view === 'favoris'): ?>
                        <h3 class="text-xl mb-3">Aucun favori</h3>
                        <p class="text-[#aaa]">Cliquez sur ♡ sur un casting pour l'ajouter à vos favoris.</p>
                    <?php else: ?>
                        <h3 class="text-xl mb-3">Aucune offre pour le moment</h3>
                        <p class="text-[#aaa]">Aucun casting ne recherche de profil <strong><?= htmlspecialchars($user_profession) ?></strong> avec ces critères.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid gap-6" style="grid-template-columns: repeat(auto-fill, minmax(360px,1fr));">
                    <?php foreach ($castings as $c):
                        $isFav = in_array($c['id'], $favoritedIds);
                        $castingJson = htmlspecialchars(json_encode($c), ENT_QUOTES);
                    ?>
                        <div class="casting-card bg-[#1a1a1a] rounded-xl overflow-hidden border border-[#333] flex flex-col hover:-translate-y-1.5 hover:shadow-[0_10px_20px_rgba(0,0,0,0.3)] transition-all duration-200 cursor-pointer"
                             data-id="<?= $c['id'] ?>" data-casting='<?= $castingJson ?>'
                             onclick="openModal(this)">
                            <img src="<?= !empty($c['cover_image']) ? htmlspecialchars($c['cover_image']) : 'https://images.unsplash.com/photo-1542204165-65bf26472b9b?auto=format&fit=crop&w=600&q=80' ?>"
                                 alt="Casting" class="w-full h-[210px] object-cover bg-[#333]">
                            <div class="p-5 flex-grow flex flex-col">
                                <div class="text-[#d4a5d4] text-sm font-bold mb-2">
                                    <?= $c['performance_date'] ? 'Prestation le '.date('d/m/Y', strtotime($c['performance_date'])) : 'Date à définir' ?>
                                </div>
                                <h3 class="text-lg font-bold text-white mb-1.5 leading-snug">
                                    Recherche <?= htmlspecialchars($c['role_sought'] ?? 'Talent') ?> — <?= htmlspecialchars($c['city'] ?? 'Lieu à définir') ?>
                                </h3>
                                <div class="text-[#888] text-sm mb-3"><?= htmlspecialchars($c['company_name'] ?? ($c['creator_name'] ?? 'ChicBook Member')) ?></div>
                                <p class="text-[#bbb] text-sm leading-relaxed mb-4 flex-grow"><?= htmlspecialchars(mb_substr($c['description'] ?? '', 0, 120)) ?>...</p>
                                <div class="flex gap-2 mt-auto pt-3" onclick="event.stopPropagation()">
                                    <?php if ($view === 'mes_castings'): ?>
                                        <a href="edit_casting.php?id=<?= $c['id'] ?>" class="flex-grow text-center bg-[#444] text-white p-3 rounded-lg no-underline font-bold hover:bg-[#d4a5d4] hover:text-[#111] transition-colors text-sm">Éditer</a>
                                        <form method="POST" onsubmit="return confirm('Supprimer ce casting ?')">
                                            <input type="hidden" name="delete_casting_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="p-2.5 bg-[#333] text-white border-none rounded-lg cursor-pointer hover:bg-[#555] transition-colors">🗑️</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="fav-btn flex-grow py-3 rounded-lg border-none font-bold cursor-pointer transition-colors text-sm <?= $isFav ? 'bg-[#d4a5d4] text-[#111]' : 'bg-[#333] text-white hover:bg-[#d4a5d4] hover:text-[#111]' ?>"
                                                data-id="<?= $c['id'] ?>" data-fav="<?= $isFav ? '1' : '0' ?>">
                                            <?= $isFav ? '♥ Favori' : '♡ Favori' ?>
                                        </button>
                                        <button class="px-4 py-3 bg-[#333] text-white border-none rounded-lg cursor-pointer hover:bg-[#555] transition-colors text-sm" onclick="openModal(this.closest('.casting-card'))">↗</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <!-- Sidebar filtres -->
        <aside class="w-[300px] flex-shrink-0">
            <form method="GET" action="castings.php" id="filter-form">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                <div class="bg-[#1a1a1a] p-6 rounded-2xl border border-[#333] sticky top-8 flex flex-col gap-5">
                    <h2 class="text-xl font-bold">Filtres</h2>

                    <!-- Pays -->
                    <div>
                        <label class="block text-[#aaa] text-sm mb-2 font-bold uppercase tracking-wider">Pays</label>
                        <select name="country" id="filter-country" class="w-full rounded-lg border border-[#444] bg-black text-white text-base outline-none focus:border-[#d4a5d4] p-3" onchange="updateCities()">
                            <option value="">Tous les pays</option>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $filter_country === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Ville -->
                    <div>
                        <label class="block text-[#aaa] text-sm mb-2 font-bold uppercase tracking-wider">Ville</label>
                        <select name="city" id="filter-city" class="w-full rounded-lg border border-[#444] bg-black text-white text-base outline-none focus:border-[#d4a5d4] p-3">
                            <option value="">Toutes les villes</option>
                            <?php
                            $cityPool = $filter_country && isset($citiesByCountry[$filter_country])
                                ? $citiesByCountry[$filter_country]
                                : array_unique(array_column($locRows, 'city'));
                            sort($cityPool);
                            foreach ($cityPool as $city): ?>
                                <option value="<?= htmlspecialchars($city) ?>" <?= $filter_city === $city ? 'selected' : '' ?>><?= htmlspecialchars($city) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date de prestation -->
                    <div>
                        <label class="block text-[#aaa] text-sm mb-2 font-bold uppercase tracking-wider">Date de prestation</label>
                        <div class="flex flex-col gap-2">
                            <div>
                                <span class="text-[#888] text-sm mb-1 block">Du</span>
                                <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>"
                                    class="w-full rounded-lg border border-[#444] bg-black text-white text-base outline-none focus:border-[#d4a5d4] p-3">
                            </div>
                            <div>
                                <span class="text-[#888] text-sm mb-1 block">Au</span>
                                <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>"
                                    class="w-full rounded-lg border border-[#444] bg-black text-white text-base outline-none focus:border-[#d4a5d4] p-3">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 bg-[#d4a5d4] text-[#111] rounded-xl font-bold text-base border-none cursor-pointer hover:opacity-90 transition-opacity">Appliquer</button>
                    <?php if ($filter_country || $filter_city || $filter_date_from || $filter_date_to): ?>
                        <a href="castings.php?view=<?= $view ?>" class="text-center text-[#888] text-sm hover:text-[#d4a5d4] transition-colors">✕ Effacer les filtres</a>
                    <?php endif; ?>
                </div>
            </form>
        </aside>
    </div>

    <!-- MODAL -->
    <div id="casting-modal" class="fixed inset-0 z-[2000] items-center justify-center bg-black/80 hidden" onclick="if(event.target===this)closeModal()">
        <div class="bg-[#1a1a1a] rounded-xl w-full max-w-[860px] mx-4 max-h-[90vh] overflow-y-auto relative flex flex-col">
            <button onclick="closeModal()" class="absolute top-3 right-3 z-10 w-8 h-8 bg-[#333] hover:bg-[#444] rounded-full text-white border-none cursor-pointer text-base leading-none">✕</button>
            <img id="modal-img" src="" alt="" class="w-full h-[280px] object-cover rounded-t-xl bg-[#333]">
            <div class="p-8">
                <!-- Badges -->
                <div class="flex gap-2 flex-wrap mb-3" id="modal-badges"></div>
                <h2 class="text-3xl font-bold mb-2" id="modal-title"></h2>
                <div class="text-[#888] text-base mb-1" id="modal-company"></div>
                <div class="text-[#aaa] text-base mb-5 flex gap-4 flex-wrap" id="modal-meta"></div>

                <!-- Description -->
                <div class="bg-[#222] rounded-lg p-4 mb-4">
                    <h4 class="text-[#d4a5d4] text-sm font-bold uppercase tracking-wider mb-3">Description</h4>
                    <p class="text-[#bbb] text-base leading-relaxed" id="modal-desc"></p>
                </div>

                <!-- Profils -->
                <div id="modal-profiles-section">
                    <h4 class="text-white font-bold mb-4 text-base uppercase tracking-wider">👥 Profils recherchés</h4>
                    <div id="modal-profiles" class="flex flex-col gap-3"></div>
                </div>

                <div class="flex gap-3 mt-5" id="modal-actions"></div>
            </div>
        </div>
    </div>

    <script>
    // Cities by country (for filter)
    const citiesByCountry = <?= json_encode($citiesByCountry) ?>;
    const currentCity = <?= json_encode($filter_city) ?>;

    function updateCities() {
        const country = document.getElementById('filter-country').value;
        const citySelect = document.getElementById('filter-city');
        citySelect.innerHTML = '<option value="">Toutes les villes</option>';
        const cities = country && citiesByCountry[country] ? citiesByCountry[country] : [];
        cities.sort().forEach(c => {
            const opt = document.createElement('option');
            opt.value = c; opt.textContent = c;
            if (c === currentCity) opt.selected = true;
            citySelect.appendChild(opt);
        });
    }
    // Init cities on load
    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('filter-country').value) updateCities();
    });

    // Favorite toggle
    document.querySelectorAll('.fav-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append('toggle_favorite', '1');
            fd.append('casting_id', id);
            fetch('castings.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.favorited) {
                        this.textContent = '♥ Favori';
                        this.className = this.className.replace('bg-[#333] text-white hover:bg-[#d4a5d4] hover:text-[#111]', 'bg-[#d4a5d4] text-[#111]');
                        this.dataset.fav = '1';
                    } else {
                        this.textContent = '♡ Favori';
                        this.className = this.className.replace('bg-[#d4a5d4] text-[#111]', 'bg-[#333] text-white hover:bg-[#d4a5d4] hover:text-[#111]');
                        this.dataset.fav = '0';
                    }
                });
        });
    });

    // Modal
    function openModal(cardEl) {
        const data = JSON.parse(cardEl.dataset.casting);
        const modal = document.getElementById('casting-modal');

        // Image
        const img = document.getElementById('modal-img');
        img.src = data.cover_image || 'https://images.unsplash.com/photo-1542204165-65bf26472b9b?auto=format&fit=crop&w=700&q=80';

        // Badges
        const badges = document.getElementById('modal-badges');
        badges.innerHTML = '';
        if (data.collaboration_type) {
            badges.innerHTML += `<span class="bg-[#d4a5d4] text-[#111] text-xs font-bold px-3 py-1 rounded-full">${data.collaboration_type}</span>`;
        }
        if (data.casting_date) {
            badges.innerHTML += `<span class="bg-[#333] text-white text-xs px-3 py-1 rounded-full">🎬 Casting : ${fmtDate(data.casting_date)}</span>`;
        }
        if (data.performance_date) {
            badges.innerHTML += `<span class="bg-[#333] text-[#d4a5d4] text-xs px-3 py-1 rounded-full">⭐ Prestation : ${fmtDate(data.performance_date)}</span>`;
        }

        // Title, company, meta
        document.getElementById('modal-title').textContent = `Recherche ${data.role_sought || '...'} — ${data.city || 'Lieu à définir'}`;
        document.getElementById('modal-company').textContent = data.company_name || data.creator_name || '';
        document.getElementById('modal-meta').innerHTML = [
            data.city ? `📍 ${data.city}${data.country ? ', '+data.country : ''}` : '',
        ].filter(Boolean).map(s => `<span>${s}</span>`).join('');
        document.getElementById('modal-desc').textContent = data.description || '';

        // Profiles
        const profSection = document.getElementById('modal-profiles-section');
        const profContainer = document.getElementById('modal-profiles');
        profContainer.innerHTML = '';
        let profiles = [];
        try { profiles = typeof data.profiles === 'string' ? JSON.parse(data.profiles) : data.profiles; } catch(e){}
        if (profiles && profiles.length > 0) {
            profSection.style.display = '';
            profiles.forEach(p => {
                const tags = [
                    p.gender, p.age_range,
                    p.height ? `Taille : ${p.height} cm` : null,
                    p.shoe_size ? `Pointure : ${p.shoe_size}` : null,
                    p.waist_size ? `Taille : ${p.waist_size} cm` : null,
                    p.hip_size ? `Hanches : ${p.hip_size} cm` : null,
                    p.chest_size ? `Poitrine : ${p.chest_size} cm` : null,
                    p.cup_size ? `Bonnet : ${p.cup_size}` : null,
                    p.eye_color ? `Yeux : ${p.eye_color}` : null,
                    p.hair_color ? `Cheveux : ${p.hair_color}` : null,
                    p.ethnicity ? `Origine : ${p.ethnicity}` : null,
                ].filter(Boolean);
                profContainer.innerHTML += `
                    <div class="bg-[#222] rounded-lg p-4 border border-[#333]">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-[#d4a5d4] text-sm">${p.role_name || 'Profil'}</span>
                            <span class="text-[#888] text-xs">${p.quantity > 1 ? p.quantity+' personnes' : '1 personne'}</span>
                        </div>
                        ${tags.length ? `<div class="flex flex-wrap gap-1.5">${tags.map(t=>`<span class="bg-[#333] text-[#bbb] text-xs px-2 py-0.5 rounded-full">${t}</span>`).join('')}</div>` : '<p class="text-[#555] text-xs">Aucun critère spécifique</p>'}
                    </div>`;
            });
        } else {
            profSection.style.display = 'none';
        }

        // Actions
        const actDiv = document.getElementById('modal-actions');
        actDiv.innerHTML = `<button class="flex-grow bg-[#d4a5d4] text-[#111] py-3 rounded-lg font-bold border-none cursor-pointer hover:opacity-90 transition-opacity">☆ Ça m'intéresse</button>`;

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = document.getElementById('casting-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    function fmtDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('fr-FR');
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>

