<?php
session_start();
require_once 'config/database.php';

$is_logged_in = isset($_SESSION['user_id']);
if (!$is_logged_in) {
    header("Location: connexion.php");
    exit();
}
$db = Database::getInstance()->getConnection();

// POST — supprimer un événement (propriétaire)
if ($is_logged_in && isset($_POST['delete_event'])) {
    $eid = intval($_POST['event_id']);
    $check = $db->prepare("SELECT user_id, cover_image FROM events WHERE id = :id");
    $check->execute([':id' => $eid]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['user_id'] == $_SESSION['user_id']) {
        if (!empty($row['cover_image']) && file_exists($row['cover_image'])) unlink($row['cover_image']);
        $db->prepare("DELETE FROM events WHERE id = :id")->execute([':id' => $eid]);
    }
    header("Location: evenements.php?view=mes_creations");
    exit;
}

// POST — modifier un événement (propriétaire)
if ($is_logged_in && isset($_POST['update_event'])) {
    $eid = intval($_POST['event_id']);
    $check = $db->prepare("SELECT user_id, cover_image FROM events WHERE id = :id");
    $check->execute([':id' => $eid]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['user_id'] == $_SESSION['user_id']) {
        $cover_image = $row['cover_image'];
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
            $filename = 'event_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $target = 'uploads/' . $filename;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target)) {
                if (!empty($cover_image) && file_exists($cover_image)) unlink($cover_image);
                $cover_image = $target;
            }
        }
        $tags     = implode(',', array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))));
        $capacity = !empty($_POST['capacity']) ? intval($_POST['capacity']) : null;
        $db->prepare("UPDATE events SET title=:t, type=:type, organizer=:org, city=:city, country=:country, event_date=:date, cover_image=:cover, description=:desc, price=:price, capacity=:cap, tags=:tags WHERE id=:id")
           ->execute([':t'=>trim($_POST['title']),':type'=>trim($_POST['type']),':org'=>trim($_POST['organizer']),':city'=>trim($_POST['city']),':country'=>trim($_POST['country']),':date'=>$_POST['event_date']??null,':cover'=>$cover_image,':desc'=>trim($_POST['description']),':price'=>trim($_POST['price']),':cap'=>$capacity,':tags'=>$tags,':id'=>$eid]);
    }
    header("Location: evenements.php?view=mes_creations");
    exit;
}

// AJAX — toggle inscription
if ($is_logged_in && isset($_POST['toggle_registration'])) {
    $event_id = intval($_POST['event_id']);
    $uid = $_SESSION['user_id'];
    $check = $db->prepare("SELECT 1 FROM event_registrations WHERE user_id=:u AND event_id=:e");
    $check->execute([':u' => $uid, ':e' => $event_id]);
    if ($check->fetchColumn()) {
        $db->prepare("DELETE FROM event_registrations WHERE user_id=:u AND event_id=:e")->execute([':u'=>$uid,':e'=>$event_id]);
        echo json_encode(['registered' => false]);
    } else {
        $db->prepare("INSERT INTO event_registrations (user_id, event_id) VALUES (:u,:e)")->execute([':u'=>$uid,':e'=>$event_id]);
        echo json_encode(['registered' => true]);
    }
    exit;
}

$view        = $_GET['view'] ?? 'tous';
$filter_type = trim($_GET['type'] ?? '');
$filter_city = trim($_GET['city'] ?? '');

// Inscriptions de l'utilisateur
$my_registrations = [];
if ($is_logged_in) {
    $r = $db->prepare("SELECT event_id FROM event_registrations WHERE user_id = :u");
    $r->execute([':u' => $_SESSION['user_id']]);
    $my_registrations = array_column($r->fetchAll(PDO::FETCH_ASSOC), 'event_id');
}

// Construction de la requête
$where  = ['1=1'];
$params = [];

if ($view === 'a_venir') {
    $where[] = "e.event_date >= CURRENT_DATE";
} elseif ($view === 'mes_evenements' && $is_logged_in) {
    $where[] = "er.user_id = :uid";
    $params[':uid'] = $_SESSION['user_id'];
} elseif ($view === 'mes_creations' && $is_logged_in) {
    $where[] = "e.user_id = :uid";
    $params[':uid'] = $_SESSION['user_id'];
}

if ($filter_type !== '') {
    $where[] = "e.type = :type";
    $params[':type'] = $filter_type;
}
if ($filter_city !== '') {
    $where[] = "e.city ILIKE :city";
    $params[':city'] = '%' . $filter_city . '%';
}

$join = ($view === 'mes_evenements' && $is_logged_in)
    ? "JOIN event_registrations er ON er.event_id = e.id"
    : "";
$whereSQL = implode(' AND ', $where);

$stmt = $db->prepare(
    "SELECT e.*, u.full_name AS creator_name
     FROM events e
     JOIN users u ON u.id = e.user_id
     $join
     WHERE $whereSQL
     ORDER BY e.event_date ASC"
);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Types distincts pour le filtre
$all_types = $db->query("SELECT DISTINCT type FROM events WHERE type IS NOT NULL ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);

$type_colors = [
    'Soirée'      => '#d4a5d4',
    'Formation'   => '#a5c4d4',
    'Salon'       => '#d4c4a5',
    'Workshop'    => '#a5d4b4',
    'Défilé'      => '#d4a5a5',
    'Networking'  => '#c4a5d4',
    'Conférence'  => '#a5d4d4',
    'Casting'     => '#d4b4a5',
    'Autre'       => '#888',
];

function fmtDate($d) {
    if (!$d) return '';
    $months = ['jan','fév','mar','avr','mai','juin','juil','août','sep','oct','nov','déc'];
    $dt = new DateTime($d);
    return $dt->format('d') . ' ' . $months[$dt->format('n') - 1] . '. ' . $dt->format('Y');
}
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
<head>
    <meta charset="UTF-8">
    <title>Événements — ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand:'#d4a5d4' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="bg-black text-white">
    <?php include 'includes/header.php'; ?>

    <div class="max-w-[1400px] mx-auto mt-10 mb-10 px-8 flex gap-10">

        <main class="flex-grow min-w-0">
            <!-- Tabs + CTA -->
            <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
                <div class="flex gap-2 flex-wrap">
                    <?php
                    $tabs = ['tous' => 'Tous', 'a_venir' => 'À venir', 'mes_evenements' => 'Mes inscriptions', 'mes_creations' => 'Mes événements'];
                    foreach ($tabs as $k => $label):
                        $q = http_build_query(array_filter(['view'=>$k,'type'=>$filter_type,'city'=>$filter_city]));
                    ?>
                    <a href="evenements.php?<?= $q ?>"
                       class="px-6 py-2.5 rounded-2xl font-bold text-base border transition-all <?= $view===$k ? 'bg-brand text-[#1a1a1a] border-brand' : 'text-[#aaa] border-[#444] hover:bg-[#333] hover:text-white' ?>">
                        <?= $label ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($is_logged_in): ?>
                <a href="creer_evenement.php" class="bg-brand text-[#111] px-6 py-3 rounded-xl font-bold text-sm hover:opacity-90 transition-opacity">+ Proposer un événement</a>
                <?php endif; ?>
            </div>

            <!-- Grille -->
            <?php if (empty($events)): ?>
                <div class="bg-[#111] border border-dashed border-[#2a2a2a] rounded-2xl py-20 text-center">
                    <div class="text-4xl mb-4">📅</div>
                    <h3 class="text-xl font-semibold mb-2">Aucun événement</h3>
                    <p class="text-[#555] text-sm">
                        <?php if ($view === 'mes_evenements'): ?>Vous n'êtes inscrit(e) à aucun événement pour l'instant.
                        <?php elseif ($filter_type || $filter_city): ?>Aucun événement ne correspond à ces filtres.
                        <?php else: ?>Aucun événement publié pour le moment. <a href="creer_evenement.php" class="text-brand hover:underline">Proposez-en un !</a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="grid gap-6" style="grid-template-columns: repeat(auto-fill, minmax(360px,1fr));">
                    <?php foreach ($events as $ev):
                        $typeColor   = $type_colors[$ev['type'] ?? ''] ?? '#888';
                        $is_reg      = in_array($ev['id'], $my_registrations);
                        $tags_arr    = $ev['tags'] ? array_filter(array_map('trim', explode(',', $ev['tags']))) : [];
                        $cover       = $ev['cover_image'] ? htmlspecialchars($ev['cover_image']) : 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=700&q=80';
                        $modal_data  = json_encode([
                            'id'          => $ev['id'],
                            'title'       => $ev['title'],
                            'type'        => $ev['type'],
                            'organizer'   => $ev['organizer'],
                            'city'        => $ev['city'],
                            'country'     => $ev['country'],
                            'date'        => $ev['event_date'],
                            'cover'       => $cover,
                            'description' => $ev['description'],
                            'price'       => $ev['price'],
                            'capacity'    => $ev['capacity'],
                            'tags'        => $tags_arr,
                            'is_reg'      => $is_reg,
                        ]);
                    ?>
                    <div class="bg-[#111] rounded-2xl overflow-hidden border border-[#1a1a1a] flex flex-col hover:-translate-y-1 hover:shadow-[0_12px_24px_rgba(0,0,0,0.4)] transition-all duration-200 cursor-pointer"
                         data-event='<?= htmlspecialchars($modal_data, ENT_QUOTES) ?>'
                         onclick="openModal(this)">
                        <div class="relative">
                            <img src="<?= $cover ?>" alt="" class="w-full h-[200px] object-cover bg-[#222]">
                            <span class="absolute top-3 left-3 text-xs font-bold px-3 py-1 rounded-full" style="background:<?= $typeColor ?>;color:#111;"><?= htmlspecialchars($ev['type'] ?? '') ?></span>
                            <?php if ($is_reg): ?>
                            <span class="absolute top-3 right-3 text-xs font-bold px-3 py-1 rounded-full bg-brand text-black">✓ Inscrit</span>
                            <?php endif; ?>
                        </div>
                        <div class="p-5 flex-grow flex flex-col">
                            <div class="text-brand text-sm font-bold mb-1.5"><?= fmtDate($ev['event_date']) ?></div>
                            <h3 class="text-base font-bold text-white mb-1 leading-snug"><?= htmlspecialchars($ev['title']) ?></h3>
                            <div class="text-[#666] text-xs mb-3"><?= htmlspecialchars($ev['organizer'] ?? '') ?></div>
                            <p class="text-[#888] text-sm leading-relaxed mb-4 flex-grow line-clamp-3"><?= htmlspecialchars($ev['description'] ?? '') ?></p>
                            <div class="flex justify-between items-center mt-auto pt-3 border-t border-[#1a1a1a]" onclick="event.stopPropagation()">
                                <div class="flex items-center gap-3 text-xs text-[#555]">
                                    <span>📍 <?= htmlspecialchars($ev['city'] ?? '') ?></span>
                                    <?php if ($ev['capacity']): ?><span>👥 <?= $ev['capacity'] ?> places</span><?php endif; ?>
                                </div>
                                <?php if ($ev['price']): ?>
                                <span class="text-sm font-bold <?= strtolower($ev['price']) === 'gratuit' ? 'text-green-400' : 'text-white' ?>"><?= htmlspecialchars($ev['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($view === 'mes_creations'): ?>
                            <div class="flex gap-2 mt-3" onclick="event.stopPropagation()">
                                <button onclick="openEditModal(<?= $ev['id'] ?>)"
                                        class="flex-1 py-2 rounded-xl text-xs font-bold bg-[#1a1a1a] border border-[#2a2a2a] text-white hover:border-brand hover:text-brand transition-all">
                                    ✎ Modifier
                                </button>
                                <form method="POST" action="evenements.php" onsubmit="return confirm('Supprimer cet événement ?')">
                                    <input type="hidden" name="delete_event" value="1">
                                    <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                    <button type="submit" class="py-2 px-4 rounded-xl text-xs font-bold bg-[#1a1a1a] border border-[#2a2a2a] text-[#e57373] hover:border-[#e57373] transition-all">
                                        ✕
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <!-- Filtres -->
        <aside class="w-[280px] flex-shrink-0">
            <form method="GET" action="evenements.php">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                <div class="bg-[#111] p-6 rounded-2xl border border-[#1a1a1a] sticky top-8 flex flex-col gap-5">
                    <h2 class="text-base font-bold">Filtres</h2>

                    <div>
                        <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Type</label>
                        <select name="type" class="w-full rounded-xl border border-[#222] bg-[#0a0a0a] text-white text-sm outline-none focus:border-brand p-3" style="appearance:none;">
                            <option value="">Tous les types</option>
                            <?php foreach ($all_types as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= $filter_type===$t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Ville</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($filter_city) ?>" placeholder="Paris, Lyon…"
                               class="w-full rounded-xl border border-[#222] bg-[#0a0a0a] text-white text-sm outline-none focus:border-brand p-3 placeholder-[#444]">
                    </div>

                    <button type="submit" class="w-full py-3 bg-brand text-black rounded-xl font-bold text-sm hover:opacity-90 transition-opacity">Appliquer</button>
                    <?php if ($filter_type || $filter_city): ?>
                    <a href="evenements.php?view=<?= $view ?>" class="text-center text-[#555] text-xs hover:text-brand transition-colors">✕ Effacer les filtres</a>
                    <?php endif; ?>
                </div>
            </form>
        </aside>
    </div>

    <!-- Modal édition événement -->
    <div id="edit-event-modal" class="fixed inset-0 z-[3000] hidden items-center justify-center bg-black/80 backdrop-blur-sm" onclick="if(event.target===this)closeEditModal()">
        <div class="bg-[#111] rounded-2xl w-full max-w-[680px] mx-4 max-h-[90vh] overflow-y-auto border border-[#1e1e1e] relative">
            <button onclick="closeEditModal()" class="absolute top-4 right-4 z-10 w-8 h-8 flex items-center justify-center bg-[#222] hover:bg-[#333] rounded-full text-white border-none cursor-pointer text-base">✕</button>
            <div class="p-8">
                <h2 class="text-xl font-black mb-6">Modifier l'événement</h2>
                <form id="edit-event-form" method="POST" action="evenements.php" enctype="multipart/form-data" class="flex flex-col gap-4">
                    <input type="hidden" name="update_event" value="1">
                    <input type="hidden" name="event_id" id="edit-event-id">

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Titre</label>
                            <input type="text" name="title" id="edit-title" required class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-brand">
                        </div>
                        <div>
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Type</label>
                            <select name="type" id="edit-type" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-brand">
                                <?php foreach (array_keys($type_colors) as $t): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Organisateur</label>
                            <input type="text" name="organizer" id="edit-organizer" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-brand">
                        </div>
                        <div>
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Ville</label>
                            <input type="text" name="city" id="edit-city" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-brand">
                        </div>
                        <div>
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Pays</label>
                            <input type="text" name="country" id="edit-country" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-brand">
                        </div>
                        <div>
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Date</label>
                            <input type="date" name="event_date" id="edit-date" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-brand">
                        </div>
                        <div>
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Prix</label>
                            <input type="text" name="price" id="edit-price" placeholder="Gratuit / 20€…" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-brand">
                        </div>
                        <div>
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Capacité</label>
                            <input type="number" name="capacity" id="edit-capacity" min="1" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-brand">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Description</label>
                            <textarea name="description" id="edit-description" rows="4" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-brand resize-none"></textarea>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Tags (séparés par des virgules)</label>
                            <input type="text" name="tags" id="edit-tags" placeholder="Mode, Paris, Networking…" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-brand">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[#555] text-xs font-bold uppercase tracking-wider mb-2">Image de couverture <span class="text-[#444] font-normal normal-case">(laisser vide pour conserver l'actuelle)</span></label>
                            <input type="file" name="cover_image" accept="image/*" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-4 py-3 text-white text-sm outline-none">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 bg-brand text-black rounded-xl font-bold text-sm hover:opacity-90 transition-opacity mt-2">
                        Enregistrer les modifications
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal détail -->
    <div id="event-modal" class="fixed inset-0 z-[2000] hidden items-center justify-center bg-black/80 backdrop-blur-sm" onclick="if(event.target===this)closeModal()">
        <div class="bg-[#111] rounded-2xl w-full max-w-[800px] mx-4 max-h-[90vh] overflow-y-auto relative flex flex-col border border-[#1e1e1e]">
            <button onclick="closeModal()" class="absolute top-4 right-4 z-10 w-8 h-8 flex items-center justify-center bg-[#222] hover:bg-[#333] rounded-full text-white border-none cursor-pointer text-base">✕</button>
            <img id="modal-img" src="" alt="" class="w-full h-[260px] object-cover rounded-t-2xl bg-[#222]">
            <div class="p-8">
                <div class="flex gap-2 flex-wrap mb-3" id="modal-badges"></div>
                <h2 class="text-2xl font-black mb-1" id="modal-title"></h2>
                <div class="text-[#666] text-sm mb-4" id="modal-organizer"></div>
                <div class="flex gap-5 flex-wrap text-[#888] text-sm mb-5" id="modal-meta"></div>

                <div class="bg-[#0e0e0e] rounded-xl p-5 mb-5 border border-[#1a1a1a]">
                    <h4 class="text-brand text-xs font-bold uppercase tracking-wider mb-3">Description</h4>
                    <p class="text-[#aaa] text-sm leading-relaxed" id="modal-desc"></p>
                </div>

                <div id="modal-tags" class="flex flex-wrap gap-2 mb-6"></div>

                <div class="flex gap-3" id="modal-actions"></div>
            </div>
        </div>
    </div>

    <script>
    const typeColors = <?= json_encode($type_colors) ?>;
    const isLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;
    const eventsById = <?= json_encode(array_column($events, null, 'id')) ?>;

    function openEditModal(id) {
        const ev = eventsById[id];
        if (!ev) return;
        document.getElementById('edit-event-id').value        = ev.id;
        document.getElementById('edit-title').value           = ev.title || '';
        document.getElementById('edit-type').value            = ev.type || '';
        document.getElementById('edit-organizer').value       = ev.organizer || '';
        document.getElementById('edit-city').value            = ev.city || '';
        document.getElementById('edit-country').value         = ev.country || '';
        document.getElementById('edit-date').value            = ev.event_date || '';
        document.getElementById('edit-price').value           = ev.price || '';
        document.getElementById('edit-capacity').value        = ev.capacity || '';
        document.getElementById('edit-description').value     = ev.description || '';
        document.getElementById('edit-tags').value            = ev.tags || '';
        const modal = document.getElementById('edit-event-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        const modal = document.getElementById('edit-event-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    function openModal(cardEl) {
        const ev = JSON.parse(cardEl.dataset.event);
        document.getElementById('modal-img').src = ev.cover;
        document.getElementById('modal-title').textContent = ev.title;
        document.getElementById('modal-organizer').textContent = ev.organizer || '';

        const color = typeColors[ev.type] || '#888';
        const priceClass = ev.price && ev.price.toLowerCase() === 'gratuit' ? 'background:#1a3a1a;color:#81c784' : 'background:#222;color:#fff';
        document.getElementById('modal-badges').innerHTML =
            `<span class="text-xs font-bold px-3 py-1 rounded-full" style="background:${color};color:#111">${ev.type||''}</span>` +
            (ev.price ? `<span class="text-xs px-3 py-1 rounded-full" style="${priceClass}">${ev.price}</span>` : '');

        const date = ev.date ? new Date(ev.date + 'T00:00:00').toLocaleDateString('fr-FR',{day:'numeric',month:'long',year:'numeric'}) : '';
        document.getElementById('modal-meta').innerHTML =
            (date ? `<span>📅 ${date}</span>` : '') +
            (ev.city ? `<span>📍 ${ev.city}${ev.country ? ', '+ev.country : ''}</span>` : '') +
            (ev.capacity ? `<span>👥 ${ev.capacity} places</span>` : '');

        document.getElementById('modal-desc').textContent = ev.description || '';

        document.getElementById('modal-tags').innerHTML = (ev.tags||[]).map(t =>
            `<span class="bg-[#1a1a1a] border border-[#222] text-[#666] px-3 py-1 rounded-full text-xs">#${t}</span>`
        ).join('');

        const actionsEl = document.getElementById('modal-actions');
        if (isLoggedIn) {
            actionsEl.innerHTML = `<button id="reg-btn" onclick="toggleRegistration(${ev.id}, this)"
                class="flex-grow py-3 rounded-xl font-bold text-sm border-none cursor-pointer transition-all ${ev.is_reg ? 'bg-[#1a1a1a] text-brand border border-brand' : 'bg-brand text-black hover:opacity-90'}">
                ${ev.is_reg ? '✓ Inscrit(e)' : '☆ Je suis intéressé(e)'}
            </button>`;
        } else {
            actionsEl.innerHTML = `<a href="connexion.php" class="flex-grow text-center py-3 rounded-xl font-bold text-sm bg-brand text-black hover:opacity-90">Se connecter pour s'inscrire</a>`;
        }

        const modal = document.getElementById('event-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = document.getElementById('event-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    function toggleRegistration(eventId, btn) {
        fetch('evenements.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'toggle_registration=1&event_id=' + eventId
        })
        .then(r => r.json())
        .then(data => {
            if (data.registered) {
                btn.textContent = '✓ Inscrit(e)';
                btn.className = 'flex-grow py-3 rounded-xl font-bold text-sm cursor-pointer transition-all bg-[#1a1a1a] text-brand border border-brand';
            } else {
                btn.textContent = '☆ Je suis intéressé(e)';
                btn.className = 'flex-grow py-3 rounded-xl font-bold text-sm cursor-pointer transition-all bg-brand text-black hover:opacity-90 border-none';
            }
            // Mettre à jour le badge sur la carte
            const card = document.querySelector(`[data-event*='"id":${eventId}']`);
            if (card) {
                const badge = card.querySelector('.absolute.top-3.right-3');
                if (data.registered && !badge) {
                    const img = card.querySelector('.relative');
                    const b = document.createElement('span');
                    b.className = 'absolute top-3 right-3 text-xs font-bold px-3 py-1 rounded-full bg-brand text-black';
                    b.textContent = '✓ Inscrit';
                    img.appendChild(b);
                } else if (!data.registered && badge) {
                    badge.remove();
                }
                // Mettre à jour data-event
                const ev = JSON.parse(card.dataset.event);
                ev.is_reg = data.registered;
                card.dataset.event = JSON.stringify(ev);
            }
        });
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); closeEditModal(); } });
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>
