<?php
session_start();
require_once 'config/Database.php';
require_once 'models/User.php';
require_once 'models/Portfolio.php';

$is_logged_in = isset($_SESSION['user_id']);

// ── AJAX handlers ──────────────────────────────────────────────────────────
if ($is_logged_in && isset($_POST['photo_action'])) {
    require_once 'models/Portfolio.php';
    $db = Database::getInstance()->getConnection();
    $pm = new Portfolio($db);

    if ($_POST['photo_action'] === 'delete') {
        $pid = intval($_POST['photo_id']);
        $photo = $pm->getPhotoById($pid);
        if ($photo && $photo['user_id'] == $_SESSION['user_id']) {
            $pm->deletePhoto($pid, $_SESSION['user_id']);
            if (!empty($photo['image_url']) && file_exists($photo['image_url'])) unlink($photo['image_url']);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false]);
        }
        exit;
    }

    if ($_POST['photo_action'] === 'reorder') {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        $stmt = $db->prepare("UPDATE portfolios SET position=:pos WHERE id=:id AND user_id=:u");
        foreach ($ids as $pos => $id) {
            $stmt->execute([':pos' => $pos, ':id' => intval($id), ':u' => $_SESSION['user_id']]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($_POST['photo_action'] === 'upload') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext  = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $name = 'portfolio_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $path = $upload_dir . $name;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $path)) {
                // position = max actuel + 1
                $maxPos = $db->prepare("SELECT COALESCE(MAX(position),0)+1 FROM portfolios WHERE user_id=:u");
                $maxPos->execute([':u' => $_SESSION['user_id']]);
                $pos = $maxPos->fetchColumn();
                $stmt = $db->prepare("INSERT INTO portfolios (user_id, image_url, position) VALUES (:u,:url,:pos) RETURNING id");
                $stmt->execute([':u' => $_SESSION['user_id'], ':url' => $path, ':pos' => $pos]);
                $new_id = $stmt->fetchColumn();
                echo json_encode(['ok' => true, 'id' => $new_id, 'url' => $path]);
            } else {
                echo json_encode(['ok' => false]);
            }
        } else {
            echo json_encode(['ok' => false, 'err' => $_FILES['photo']['error'] ?? 'no file']);
        }
        exit;
    }
}
$db = Database::getInstance()->getConnection();
$userModel = new User($db);

$profile_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$share_url = $protocol . "://" . $domain . dirname($_SERVER['PHP_SELF']) . "/profil.php?id=" . $profile_id;

if (!$profile_id) { header("Location: index.php"); exit(); }

$profile_data = $userModel->getUserProfile($profile_id);
if (!$profile_data) { die("Ce profil n'existe pas."); }

$is_own_profile = ($is_logged_in && $_SESSION['user_id'] == $profile_id);

$age = null;
if (!empty($profile_data['birth_date']) && !empty($profile_data['show_age'])) {
    $birth = new DateTime($profile_data['birth_date']);
    $age = (new DateTime())->diff($birth)->y;
}

$portfolioModel = new Portfolio($db);
$photos = $portfolioModel->getPhotos($profile_id);
$theme = $profile_data['profile_theme'] ?? 'classique';

$tags = [];
if (!empty($profile_data['expertise_tags'])) {
    $tags = array_filter(array_map('trim', explode(',', $profile_data['expertise_tags'])));
}

$profession = htmlspecialchars($profile_data['specific_profession'] ?? $profile_data['profession_name'] ?? 'Talent');
$name       = htmlspecialchars($profile_data['full_name']);
$location   = trim(($profile_data['city'] ?? '') . ($profile_data['country'] ? ', '.$profile_data['country'] : ''));
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $name ?> - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="bg-black text-white">
<?php include 'includes/header.php'; ?>

<?php
// ─── Actions dropdown (commun à tous les thèmes) ───────────────────────────
function renderActions($is_own_profile) { ?>
    <div class="flex gap-3">
        <?php if (!$is_own_profile): ?>
            <button class="bg-[#1e1e1e] text-white px-5 py-2 rounded-full text-sm font-semibold border border-[#333] hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all">Suivre</button>
            <button class="bg-[#d4a5d4] text-black px-5 py-2 rounded-full text-sm font-bold hover:opacity-90 transition-opacity">Contacter</button>
        <?php else: ?>
            <button onclick="toggleEditMode()" id="edit-photos-btn" class="flex items-center gap-2 bg-[#1e1e1e] text-white px-5 py-2 rounded-full text-sm font-semibold border border-[#333] hover:border-brand hover:text-brand transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Photos
            </button>
            <div class="relative inline-block">
                <button onclick="toggleProfileMenu()" id="profile-menu-btn" class="bg-[#1e1e1e] text-white px-5 py-2 rounded-full text-sm font-semibold border border-[#333] hover:border-[#555] transition-all">Gérer mon profil ▾</button>
                <div id="profile-menu" class="hidden absolute right-0 top-full mt-2 bg-[#111] min-w-[200px] shadow-[0_8px_24px_rgba(0,0,0,0.6)] border border-[#2a2a2a] rounded-xl overflow-hidden z-50">
                    <button onclick="openEditModal('section-infos')"    class="w-full text-left text-white px-4 py-3 text-sm border-b border-[#1e1e1e] hover:bg-[#1e1e1e] hover:text-[#d4a5d4] transition-colors">Modifier le profil</button>
                    <button onclick="openEditModal('section-portfolio')" class="w-full text-left text-white px-4 py-3 text-sm border-b border-[#1e1e1e] hover:bg-[#1e1e1e] hover:text-[#d4a5d4] transition-colors">Ajouter des photos</button>
                    <button onclick="openEditModal('section-theme')"    class="w-full text-left text-white px-4 py-3 text-sm border-b border-[#1e1e1e] hover:bg-[#1e1e1e] hover:text-[#d4a5d4] transition-colors">Changer le thème</button>
                    <button onclick="openEditModal('section-portfolio-manage')" class="w-full text-left text-white px-4 py-3 text-sm hover:bg-[#1e1e1e] hover:text-[#e57373] transition-colors">Supprimer des photos</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php } ?>

<?php if ($theme === 'classique'): ?>
<!-- ═══════════════════════════════════════════════════════════════
     THÈME CLASSIQUE — sidebar gauche + masonry
═══════════════════════════════════════════════════════════════ -->
<main class="max-w-[1400px] mx-auto mt-10 mb-10 px-8 flex gap-12">

    <aside class="w-[240px] flex-shrink-0 flex flex-col items-center text-center">
        <p class="text-[#d4a5d4] text-xs font-black uppercase tracking-widest mb-3"><?= $profession ?></p>
        <?php if ($age): ?><p class="text-[#666] text-sm mb-1"><?= $age ?> ans</p><?php endif; ?>
        <?php if ($location): ?><p class="text-[#555] text-sm mb-6"><?= htmlspecialchars($location) ?></p><?php endif; ?>

        <?php if ($tags): ?>
            <div class="flex flex-wrap gap-2 mb-6 justify-center">
                <?php foreach ($tags as $t): ?>
                    <span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] px-3 py-1 rounded-full text-xs font-semibold">#<?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($profile_data['bio'])): ?>
            <button onclick="openBioModal()" class="mb-6 flex items-center gap-2 text-[#888] text-sm font-semibold border border-[#2a2a2a] rounded-full px-4 py-2 hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all bg-[#111]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Biographie
            </button>
        <?php elseif ($is_own_profile): ?>
            <a href="edit_profil.php#section-bio" class="text-[#444] text-sm hover:text-[#d4a5d4] transition-colors mb-6 block">+ Ajouter une biographie</a>
        <?php endif; ?>

        <a href="#" id="btn-share" class="text-[#444] text-xs hover:text-[#d4a5d4] transition-colors">Partager le profil</a>
    </aside>

    <section class="flex-grow min-w-0">
        <div class="flex justify-between items-center mb-8 pb-6 border-b border-[#1a1a1a]">
            <h1 class="text-4xl font-bold text-white"><?= $name ?></h1>
            <?php renderActions($is_own_profile); ?>
        </div>

        <!-- Vue normale -->
        <div id="photos-view" style="column-count:3; column-gap:12px;">
            <?php if (empty($photos)): ?>
                <p class="text-[#555]">Aucune photo dans le book pour le moment.</p>
            <?php else: foreach ($photos as $photo): ?>
                <div style="break-inside:avoid; margin-bottom:12px;">
                    <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="" class="w-full block rounded-xl hover:scale-[1.01] transition-transform duration-300">
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Vue édition (masquée par défaut) -->
        <?php if ($is_own_profile): ?>
        <div id="photos-edit" class="hidden">
            <?php include_once 'includes/photos_edit_grid.php'; ?>
        </div>
        <?php endif; ?>
    </section>
</main>

<?php elseif ($theme === 'editorial'): ?>
<!-- ═══════════════════════════════════════════════════════════════
     THÈME ÉDITORIAL — hero + grille uniforme
═══════════════════════════════════════════════════════════════ -->

<!-- Hero -->
<div class="relative w-full" style="height: 400px; overflow:hidden;">
    <?php if (!empty($photos)): ?>
        <img src="<?= htmlspecialchars($photos[0]['image_url']) ?>" class="w-full h-full object-cover" alt="">
    <?php else: ?>
        <div class="w-full h-full bg-[#0e0e0e]"></div>
    <?php endif; ?>
    <div class="absolute inset-0" style="background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.3) 60%, transparent 100%);"></div>
    <div class="absolute bottom-0 left-0 px-10 pb-8">
        <p class="text-[#d4a5d4] text-xs font-black uppercase tracking-[0.25em] mb-2"><?= $profession ?></p>
        <h1 class="text-6xl font-black text-white leading-none mb-1"><?= $name ?></h1>
        <?php if ($location): ?><p class="text-[#888] text-sm mt-2"><?= htmlspecialchars($location) ?><?= $age ? ' · '.$age.' ans' : '' ?></p><?php endif; ?>
    </div>
</div>
<!-- Actions hors du overflow:hidden pour que le dropdown soit visible -->
<div class="max-w-[1400px] mx-auto px-8 mt-4 flex justify-between items-center">
    <a href="#" id="btn-share" class="text-[#555] text-xs hover:text-[#d4a5d4] transition-colors">Partager</a>
    <?php renderActions($is_own_profile); ?>
</div>

<main class="max-w-[1400px] mx-auto px-8 mt-10 mb-10">

    <!-- Tags + bio -->
    <?php if ($tags || !empty($profile_data['bio'])): ?>
    <div class="flex items-center gap-4 mb-10 pb-8 border-b border-[#1a1a1a] flex-wrap">
        <?php if ($tags): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($tags as $t): ?>
                    <span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] px-3 py-1 rounded-full text-xs font-semibold">#<?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($profile_data['bio'])): ?>
            <button onclick="openBioModal()" class="flex items-center gap-2 text-[#888] text-sm font-semibold border border-[#2a2a2a] rounded-full px-4 py-2 hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all bg-[#111]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Biographie
            </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Vue normale -->
    <div id="photos-view" class="grid gap-3" style="grid-template-columns: repeat(3, 1fr);">
        <?php
        $grid_photos = !empty($photos) ? array_slice($photos, 1) : [];
        foreach ($grid_photos as $photo): ?>
            <div class="overflow-hidden rounded-xl aspect-square bg-[#111]">
                <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="" class="w-full h-full object-cover hover:scale-[1.04] transition-transform duration-500">
            </div>
        <?php endforeach; ?>
        <?php if (empty($grid_photos) && empty($photos)): ?>
            <p class="text-[#555] col-span-3">Aucune photo dans le book pour le moment.</p>
        <?php endif; ?>
    </div>

    <!-- Vue édition -->
    <?php if ($is_own_profile): ?>
    <div id="photos-edit" class="hidden">
        <?php include_once 'includes/photos_edit_grid.php'; ?>
    </div>
    <?php endif; ?>
</main>

<?php elseif ($theme === 'luxe'): ?>
<!-- ═══════════════════════════════════════════════════════════════
     THÈME LUXE — centré, minimal, 2 colonnes
═══════════════════════════════════════════════════════════════ -->
<main class="max-w-[1100px] mx-auto px-8 mt-16 mb-16">

    <!-- Header centré -->
    <div class="text-center mb-4">
        <p class="text-[#d4a5d4] text-[10px] font-black uppercase tracking-[0.4em] mb-6"><?= $profession ?></p>
        <h1 class="text-7xl font-black text-white leading-none mb-4" style="letter-spacing:-2px;"><?= $name ?></h1>
        <div class="flex items-center justify-center gap-3 text-[#555] text-sm mb-6">
            <?php if ($location): ?><span><?= htmlspecialchars($location) ?></span><?php endif; ?>
            <?php if ($age): ?><span>·</span><span><?= $age ?> ans</span><?php endif; ?>
        </div>
        <?php if ($tags): ?>
            <div class="flex flex-wrap gap-2 justify-center mb-6">
                <?php foreach ($tags as $t): ?>
                    <span class="text-[#444] text-xs font-semibold uppercase tracking-wider"><?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($profile_data['bio'])): ?>
            <div class="flex justify-center mb-6">
                <button onclick="openBioModal()" class="flex items-center gap-2 text-[#888] text-sm font-semibold border border-[#2a2a2a] rounded-full px-4 py-2 hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all bg-[#111]">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Biographie
                </button>
            </div>
        <?php endif; ?>
        <div class="flex justify-center gap-3 mb-4">
            <?php renderActions($is_own_profile); ?>
        </div>
        <a href="#" id="btn-share" class="text-[#333] text-xs hover:text-[#d4a5d4] transition-colors">Partager le profil</a>
    </div>

    <!-- Séparateur -->
    <div class="flex items-center gap-4 my-10">
        <div class="flex-grow h-px bg-[#1a1a1a]"></div>
        <div class="w-1.5 h-1.5 rounded-full bg-[#d4a5d4]"></div>
        <div class="flex-grow h-px bg-[#1a1a1a]"></div>
    </div>

    <!-- Vue normale -->
    <div id="photos-view" class="grid grid-cols-2 gap-3">
        <?php if (empty($photos)): ?>
            <p class="text-[#555] col-span-2 text-center">Aucune photo dans le book pour le moment.</p>
        <?php else: foreach ($photos as $i => $photo): ?>
            <div class="overflow-hidden rounded-2xl bg-[#0e0e0e] <?= $i === 0 ? 'col-span-2 aspect-video' : 'aspect-square' ?>">
                <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="" class="w-full h-full object-cover hover:scale-[1.03] transition-transform duration-500">
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Vue édition -->
    <?php if ($is_own_profile): ?>
    <div id="photos-edit" class="hidden">
        <?php include_once 'includes/photos_edit_grid.php'; ?>
    </div>
    <?php endif; ?>
</main>

<?php endif; ?>

<!-- Modale biographie -->
<?php if (!empty($profile_data['bio'])): ?>
<div id="bio-modal" class="hidden fixed inset-0 z-[3000] flex items-center justify-center bg-black/80 backdrop-blur-sm" onclick="if(event.target===this)closeBioModal()">
    <div class="relative bg-[#111] rounded-2xl w-full max-w-lg mx-4 shadow-[0_32px_80px_rgba(0,0,0,0.8)] border border-[#1e1e1e]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#1e1e1e]">
            <div>
                <div class="text-white font-bold text-base"><?= $name ?></div>
                <div class="text-[#d4a5d4] text-xs font-semibold mt-0.5"><?= $profession ?></div>
            </div>
            <button onclick="closeBioModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-[#1e1e1e] hover:bg-[#2a2a2a] text-[#888] hover:text-white transition-colors text-lg leading-none">✕</button>
        </div>
        <div class="px-6 py-5">
            <p class="text-[#aaa] text-sm leading-relaxed"><?= nl2br(htmlspecialchars($profile_data['bio'])) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modale édition profil -->
<div id="edit-modal" class="hidden fixed inset-0 z-[4000] flex items-center justify-center bg-black/80 backdrop-blur-sm" onclick="if(event.target===this)closeEditModal()">
    <div class="relative bg-[#0e0e0e] rounded-2xl w-[95vw] max-w-[1100px] h-[90vh] flex flex-col overflow-hidden shadow-[0_32px_80px_rgba(0,0,0,0.8)] border border-[#1e1e1e]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#1e1e1e] flex-shrink-0">
            <span class="text-white font-bold text-base">Paramètres du profil</span>
            <button onclick="closeEditModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-[#1e1e1e] hover:bg-[#2a2a2a] text-[#888] hover:text-white transition-colors border-none cursor-pointer text-lg leading-none">✕</button>
        </div>
        <iframe id="edit-iframe" src="" class="flex-grow w-full border-none bg-[#0e0e0e]" style="min-height:0;"></iframe>
    </div>
</div>

<script>
function openEditModal(section) {
    document.getElementById('profile-menu').classList.add('hidden');
    const iframe = document.getElementById('edit-iframe');
    iframe.src = 'edit_profil.php#' + section;
    document.getElementById('edit-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeEditModal() {
    document.getElementById('edit-modal').classList.add('hidden');
    document.getElementById('edit-iframe').src = '';
    document.body.style.overflow = '';
    location.reload();
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (document.getElementById('photos-edit') && !document.getElementById('photos-edit').classList.contains('hidden')) {
            toggleEditMode();
        } else {
            closeEditModal();
            closeBioModal();
        }
    }
});

function toggleEditMode() {
    const view = document.getElementById('photos-view');
    const edit = document.getElementById('photos-edit');
    const btn  = document.getElementById('edit-photos-btn');
    if (!view || !edit) return;
    const editing = !edit.classList.contains('hidden');
    if (editing) {
        edit.classList.add('hidden');
        view.classList.remove('hidden');
        btn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg> Modifier les photos`;
        btn.style.color = '';
        btn.style.borderColor = '';
        location.reload();
    } else {
        view.classList.add('hidden');
        edit.classList.remove('hidden');
        btn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Terminer`;
        btn.style.color = '#d4a5d4';
        btn.style.borderColor = '#d4a5d4';
    }
}

function openBioModal() {
    document.getElementById('bio-modal')?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeBioModal() {
    document.getElementById('bio-modal')?.classList.add('hidden');
    document.body.style.overflow = '';
}

function toggleProfileMenu() {
    const menu = document.getElementById('profile-menu');
    if (menu) menu.classList.toggle('hidden');
}
document.addEventListener('click', (e) => {
    const btn = document.getElementById('profile-menu-btn');
    const menu = document.getElementById('profile-menu');
    if (menu && btn && !btn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.add('hidden');
    }
});

document.getElementById('btn-share')?.addEventListener('click', async (e) => {
    e.preventDefault();
    const shareData = {
        title: '<?= addslashes($name) ?> - ChicBook',
        text: 'Découvrez le portfolio de <?= addslashes($name) ?> sur ChicBook !',
        url: '<?= $share_url ?>'
    };
    if (navigator.share) {
        try { await navigator.share(shareData); } catch(e) {}
    } else {
        navigator.clipboard.writeText(shareData.url).then(() => {
            const btn = document.getElementById('btn-share');
            const orig = btn.innerHTML;
            btn.innerHTML = '✅ Lien copié !';
            btn.style.color = '#d4a5d4';
            setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; }, 3000);
        });
    }
});
</script>
</body>
</html>
