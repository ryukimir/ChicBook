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

// ── Édition description/tags photo ─────────────────────────────────────────
if ($is_logged_in && isset($_POST['edit_photo'])) {
    $db = Database::getInstance()->getConnection();
    $photo_id = intval($_POST['photo_id']);
    $description = trim($_POST['description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    
    // Vérifier que la photo appartient à l'utilisateur
    $stmt = $db->prepare("SELECT user_id FROM portfolios WHERE id=:id");
    $stmt->execute([':id' => $photo_id]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($photo && $photo['user_id'] == $_SESSION['user_id']) {
        $stmt = $db->prepare("UPDATE portfolios SET description=:desc, tags=:tags WHERE id=:id");
        $stmt->execute([':desc' => $description ?: null, ':tags' => $tags ?: null, ':id' => $photo_id]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
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
// Afficher l'âge seulement si show_age est TRUE (booléen) et birth_date est défini
if (!empty($profile_data['birth_date']) && $profile_data['show_age'] === true) {
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
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
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
function renderActions($is_own_profile, $profile_id = 0) { ?>
    <div class="flex gap-3">
        <?php if (!$is_own_profile): ?>
            <button class="bg-[#1e1e1e] text-white px-5 py-2 rounded-full text-sm font-semibold border border-[#333] hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all">Suivre</button>
            <a href="messagerie.php?with=<?= $profile_id ?>" class="bg-[#d4a5d4] text-black px-5 py-2 rounded-full text-sm font-bold hover:opacity-90 transition-opacity inline-flex items-center">Contacter</a>
        <?php else: ?>
            <button onclick="toggleEditMode()" id="edit-photos-btn" class="flex items-center gap-2 bg-[#1e1e1e] text-white px-5 py-2 rounded-full text-sm font-semibold border border-[#333] hover:border-brand hover:text-brand transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Photos
            </button>
            <a href="edit_profil.php" class="bg-[#1e1e1e] text-white px-5 py-2 rounded-full text-sm font-semibold border border-[#333] hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all">Gérer mon profil</a>
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

        <button id="btn-share" class="inline-flex items-center gap-2 mt-2 px-4 py-2 rounded-full border border-[#2a2a2a] bg-[#111] text-[#aaa] text-xs font-medium hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            Partager le profil
        </button>
    </aside>

    <section class="flex-grow min-w-0">
        <div class="flex justify-between items-center mb-8 pb-6 border-b border-[#1a1a1a]">
            <h1 class="text-4xl font-bold text-white"><?= $name ?></h1>
            <?php renderActions($is_own_profile, $profile_id); ?>
        </div>

        <!-- Vue normale -->
        <div id="photos-view" style="column-count:3; column-gap:12px;">
            <?php if (empty($photos)): ?>
                <p class="text-[#555]">Aucune photo dans le book pour le moment.</p>
            <?php else: foreach ($photos as $idx => $photo): ?>
                <div style="break-inside:avoid; margin-bottom:12px;">
                    <img src="<?= htmlspecialchars($photo['image_url']) ?>" 
                         alt="" 
                         class="w-full block rounded-xl hover:scale-[1.01] transition-transform duration-300 cursor-pointer"
                         onclick="openPhotoLightbox(<?= $idx ?>)"
                         data-photo-idx="<?= $idx ?>"
                         data-description="<?= htmlspecialchars($photo['description'] ?? '') ?>"
                         data-tags="<?= htmlspecialchars($photo['tags'] ?? '') ?>">
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
    <button id="btn-share" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-[#2a2a2a] bg-black/40 text-[#aaa] text-xs font-medium hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        Partager le profil
    </button>
    <?php renderActions($is_own_profile, $profile_id); ?>
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
        foreach ($grid_photos as $idx => $photo): 
            $real_idx = $idx + 1; // Index réel (premier exclu en éditorial)
        ?>
            <div class="overflow-hidden rounded-xl aspect-square bg-[#111]">
                <img src="<?= htmlspecialchars($photo['image_url']) ?>" 
                     alt="" 
                     class="w-full h-full object-cover hover:scale-[1.04] transition-transform duration-500 cursor-pointer"
                     onclick="openPhotoLightbox(<?= $real_idx ?>)"
                     data-photo-idx="<?= $real_idx ?>"
                     data-description="<?= htmlspecialchars($photo['description'] ?? '') ?>"
                     data-tags="<?= htmlspecialchars($photo['tags'] ?? '') ?>">
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
            <?php renderActions($is_own_profile, $profile_id); ?>
        </div>
        <button id="btn-share" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-[#ddd] bg-white text-[#555] text-xs font-medium hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            Partager le profil
        </button>
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
        <?php else: foreach ($photos as $idx => $photo): ?>
            <div class="overflow-hidden rounded-2xl bg-[#0e0e0e] <?= $idx === 0 ? 'col-span-2 aspect-video' : 'aspect-square' ?>">
                <img src="<?= htmlspecialchars($photo['image_url']) ?>" 
                     alt="" 
                     class="w-full h-full object-cover hover:scale-[1.03] transition-transform duration-500 cursor-pointer"
                     onclick="openPhotoLightbox(<?= $idx ?>)"
                     data-photo-idx="<?= $idx ?>"
                     data-description="<?= htmlspecialchars($photo['description'] ?? '') ?>"
                     data-tags="<?= htmlspecialchars($photo['tags'] ?? '') ?>">
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

<!-- Modal Lightbox Photos -->
<div id="photo-lightbox" class="hidden fixed inset-0 z-[3500] flex items-center justify-center bg-black/95 backdrop-blur-sm" onclick="if(event.target.id==='photo-lightbox')closePhotoLightbox()">
    <div class="relative w-[95vw] h-[90vh] max-w-6xl flex flex-col">
        <!-- Bouton fermer -->
        <button onclick="closePhotoLightbox()" class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center rounded-full bg-[#1e1e1e] hover:bg-[#2a2a2a] text-[#888] hover:text-white transition-colors text-xl leading-none z-10">✕</button>
        
        <!-- Image principale -->
        <div class="flex-grow flex items-center justify-center min-h-0 relative">
            <img id="lightbox-image" src="" alt="" class="max-w-full max-h-full object-contain">
            
            <!-- Flèche gauche -->
            <button onclick="prevPhoto()" class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center rounded-full bg-[#1e1e1e] hover:bg-[#2a2a2a] text-white transition-all hover:scale-110">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            
            <!-- Flèche droite -->
            <button onclick="nextPhoto()" class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center rounded-full bg-[#1e1e1e] hover:bg-[#2a2a2a] text-white transition-all hover:scale-110">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
        
        <!-- Infos + Description -->
        <div class="bg-[#0e0e0e] border-t border-[#1e1e1e] p-6">
            <div class="flex justify-between items-start gap-4">
                <div class="flex-grow">
                    <div id="lightbox-counter" class="text-[#666] text-xs font-semibold mb-3">1 / 1</div>
                    <div id="lightbox-description" class="text-[#aaa] text-sm mb-4"></div>
                    <div id="lightbox-tags" class="flex flex-wrap gap-2"></div>
                </div>
                <?php if ($is_own_profile): ?>
                    <button onclick="openPhotoEditor()" class="flex-shrink-0 px-4 py-2 bg-[#1e1e1e] text-white rounded-lg text-sm font-semibold hover:bg-[#2a2a2a] transition-colors">
                        ✎ Éditer
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'édition photo (pour propriétaire) -->
<?php if ($is_own_profile): ?>
<div id="photo-edit-modal" class="hidden fixed inset-0 z-[4000] flex items-center justify-center bg-black/80 backdrop-blur-sm" onclick="if(event.target.id==='photo-edit-modal')closePhotoEditor()">
    <div class="relative bg-[#111] rounded-2xl w-full max-w-lg mx-4 shadow-[0_32px_80px_rgba(0,0,0,0.8)] border border-[#1e1e1e]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#1e1e1e]">
            <h3 class="text-white font-bold">Éditer la photo</h3>
            <button onclick="closePhotoEditor()" class="w-8 h-8 flex items-center justify-center rounded-full bg-[#1e1e1e] hover:bg-[#2a2a2a] text-[#888] hover:text-white transition-colors text-lg leading-none">✕</button>
        </div>
        <form id="photo-edit-form" method="POST" class="p-6 flex flex-col gap-4">
            <input type="hidden" name="edit_photo" value="1">
            <input type="hidden" id="edit-photo-id" name="photo_id" value="">
            
            <div>
                <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Description</label>
                <textarea id="edit-description" name="description" rows="3" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-lg px-4 py-2 text-white text-sm outline-none focus:border-[#d4a5d4] transition-colors placeholder:text-[#444]" placeholder="Décrivez cette photo..."></textarea>
            </div>
            
            <div>
                <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Tags (séparés par des virgules)</label>
                <input type="text" id="edit-tags" name="tags" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] rounded-lg px-4 py-2 text-white text-sm outline-none focus:border-[#d4a5d4] transition-colors placeholder:text-[#444]" placeholder="tag1, tag2, tag3">
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closePhotoEditor()" class="flex-1 py-2 bg-[#1e1e1e] text-white rounded-lg text-sm font-semibold hover:bg-[#2a2a2a] transition-colors">
                    Annuler
                </button>
                <button type="submit" class="flex-1 py-2 bg-[#d4a5d4] text-black rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// ─── Lightbox Photos ───────────────────────────────────────────────────────
let currentPhotoIdx = 0;
const allPhotos = <?= json_encode($photos) ?>;

function openPhotoLightbox(idx) {
    if (!allPhotos || allPhotos.length === 0) return;
    currentPhotoIdx = idx;
    updateLightbox();
    document.getElementById('photo-lightbox').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closePhotoLightbox() {
    document.getElementById('photo-lightbox').classList.add('hidden');
    document.body.style.overflow = '';
}

function nextPhoto() {
    if (!allPhotos) return;
    currentPhotoIdx = (currentPhotoIdx + 1) % allPhotos.length;
    updateLightbox();
}

function prevPhoto() {
    if (!allPhotos) return;
    currentPhotoIdx = (currentPhotoIdx - 1 + allPhotos.length) % allPhotos.length;
    updateLightbox();
}

function updateLightbox() {
    const photo = allPhotos[currentPhotoIdx];
    if (!photo) return;
    
    document.getElementById('lightbox-image').src = photo.image_url;
    document.getElementById('lightbox-counter').textContent = (currentPhotoIdx + 1) + ' / ' + allPhotos.length;
    
    // Description
    const desc = document.getElementById('lightbox-description');
    if (photo.description) {
        desc.textContent = photo.description;
        desc.classList.remove('hidden');
    } else {
        desc.textContent = '';
    }
    
    // Tags
    const tagsDiv = document.getElementById('lightbox-tags');
    tagsDiv.innerHTML = '';
    if (photo.tags) {
        const tags = photo.tags.split(',').map(t => t.trim()).filter(t => t);
        tags.forEach(tag => {
            const span = document.createElement('span');
            span.className = 'bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] px-3 py-1 rounded-full text-xs font-semibold';
            span.textContent = tag;
            tagsDiv.appendChild(span);
        });
    }
}

function openPhotoEditor() {
    const photo = allPhotos[currentPhotoIdx];
    if (!photo) return;
    
    document.getElementById('edit-photo-id').value = photo.id;
    document.getElementById('edit-description').value = photo.description || '';
    document.getElementById('edit-tags').value = photo.tags || '';
    document.getElementById('photo-edit-modal').classList.remove('hidden');
}

function closePhotoEditor() {
    document.getElementById('photo-edit-modal').classList.add('hidden');
}

// Soumettre le formulaire d'édition
document.getElementById('photo-edit-form')?.addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    fetch('profil.php?id=<?= $profile_id ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.text())
    .then(data => {
        if (data.includes('ok')) {
            closePhotoEditor();
            closePhotoLightbox();
            location.reload();
        }
    })
    .catch(err => console.error(err));
});

// Clavier pour naviguer
document.addEventListener('keydown', e => {
    const lightbox = document.getElementById('photo-lightbox');
    if (!lightbox.classList.contains('hidden')) {
        if (e.key === 'ArrowLeft') prevPhoto();
        if (e.key === 'ArrowRight') nextPhoto();
        if (e.key === 'Escape') closePhotoLightbox();
    }
});

// ─── Ancien code ──────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (document.getElementById('photos-edit') && !document.getElementById('photos-edit').classList.contains('hidden')) {
            toggleEditMode();
        } else {
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
            btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> Lien copié !';
            btn.style.borderColor = '#d4a5d4';
            btn.style.color = '#d4a5d4';
            setTimeout(() => { btn.innerHTML = orig; btn.style.borderColor = ''; btn.style.color = ''; }, 3000);
        });
    }
});
</script>
</body>
</html>
