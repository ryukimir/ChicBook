<?php
session_start();
require_once 'config/Database.php';
require_once 'models/User.php';
require_once 'models/Portfolio.php';
require_once 'config/i18n.php';

$is_logged_in = isset($_SESSION['user_id']);

function extractYoutubeId(string $url): ?string {
    if (preg_match('/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
        return $m[1];
    }
    return null;
}

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
            $allowed_exts = ['jpg','jpeg','png','gif','webp'];
            $img_info = @getimagesize($_FILES['photo']['tmp_name']);
            $allowed_mimes = ['image/jpeg','image/png','image/gif','image/webp'];
            if (!in_array($ext, $allowed_exts) || !$img_info || !in_array($img_info['mime'], $allowed_mimes)) {
                echo json_encode(['ok' => false, 'err' => 'Type de fichier non autorisé']);
                exit;
            }
            $name = bin2hex(random_bytes(12)) . '.' . $ext;
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

    if ($_POST['photo_action'] === 'add_video') {
        $url = trim($_POST['video_url'] ?? '');
        $yt_id = extractYoutubeId($url);
        if (!$yt_id) {
            echo json_encode(['ok' => false, 'err' => 'URL YouTube invalide']);
            exit;
        }
        $clean_url = 'https://www.youtube.com/watch?v=' . $yt_id;
        $maxPos = $db->prepare("SELECT COALESCE(MAX(position),0)+1 FROM portfolios WHERE user_id=:u");
        $maxPos->execute([':u' => $_SESSION['user_id']]);
        $pos = $maxPos->fetchColumn();
        $stmt = $db->prepare("INSERT INTO portfolios (user_id, video_url, position) VALUES (:u, :url, :pos) RETURNING id");
        $stmt->execute([':u' => $_SESSION['user_id'], ':url' => $clean_url, ':pos' => $pos]);
        $new_id = $stmt->fetchColumn();
        echo json_encode(['ok' => true, 'id' => $new_id, 'video_url' => $clean_url, 'yt_id' => $yt_id, 'thumb' => 'https://img.youtube.com/vi/'.$yt_id.'/hqdefault.jpg']);
        exit;
    }
}

// ── Toggle follow ─────────────────────────────────────────────────────────
if ($is_logged_in && isset($_POST['toggle_follow'])) {
    $db = Database::getInstance()->getConnection();
    $target = intval($_POST['target_id']);
    if ($target && $target !== $_SESSION['user_id']) {
        $check = $db->prepare("SELECT id FROM follows WHERE follower_id=:f AND following_id=:t");
        $check->execute([':f' => $_SESSION['user_id'], ':t' => $target]);
        if ($check->fetch()) {
            $db->prepare("DELETE FROM follows WHERE follower_id=:f AND following_id=:t")
               ->execute([':f' => $_SESSION['user_id'], ':t' => $target]);
            $following = false;
        } else {
            $db->prepare("INSERT INTO follows (follower_id, following_id) VALUES (:f,:t)")
               ->execute([':f' => $_SESSION['user_id'], ':t' => $target]);
            $following = true;
        }
        $count = $db->prepare("SELECT COUNT(*) FROM follows WHERE following_id=:t");
        $count->execute([':t' => $target]);
        echo json_encode(['ok' => true, 'following' => $following, 'count' => (int)$count->fetchColumn()]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

// ── Suppression projet ────────────────────────────────────────────────────
if ($is_logged_in && isset($_POST['delete_project'])) {
    $db = Database::getInstance()->getConnection();
    $pid = intval($_POST['project_id']);
    $stmt = $db->prepare("DELETE FROM projects WHERE id=:id AND user_id=:uid");
    $stmt->execute([':id' => $pid, ':uid' => $_SESSION['user_id']]);
    header("Location: profil.php");
    exit;
}

// ── Toggle like photo ─────────────────────────────────────────────────────
if ($is_logged_in && isset($_POST['toggle_photo_like'])) {
    $db = Database::getInstance()->getConnection();
    $photo_id = intval($_POST['photo_id'] ?? 0);
    $me = $_SESSION['user_id'];
    if (!$photo_id) { echo json_encode(['ok' => false]); exit; }
    $check = $db->prepare("SELECT id FROM photo_likes WHERE user_id=:u AND photo_id=:p");
    $check->execute([':u' => $me, ':p' => $photo_id]);
    if ($check->fetch()) {
        $db->prepare("DELETE FROM photo_likes WHERE user_id=:u AND photo_id=:p")->execute([':u' => $me, ':p' => $photo_id]);
        $liked = false;
    } else {
        $db->prepare("INSERT INTO photo_likes (user_id, photo_id) VALUES (:u,:p)")->execute([':u' => $me, ':p' => $photo_id]);
        $liked = true;
    }
    $cnt = $db->prepare("SELECT COUNT(*) FROM photo_likes WHERE photo_id=:p");
    $cnt->execute([':p' => $photo_id]);
    echo json_encode(['ok' => true, 'liked' => $liked, 'count' => (int)$cnt->fetchColumn()]);
    exit;
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

// ── Followers ──────────────────────────────────────────────────────────────
$stmt_fc = $db->prepare("SELECT COUNT(*) FROM follows WHERE following_id=:t");
$stmt_fc->execute([':t' => $profile_id]);
$followers_count = (int)$stmt_fc->fetchColumn();

$is_following = false;
if ($is_logged_in && !$is_own_profile) {
    $stmt_fq = $db->prepare("SELECT 1 FROM follows WHERE follower_id=:f AND following_id=:t");
    $stmt_fq->execute([':f' => $_SESSION['user_id'], ':t' => $profile_id]);
    $is_following = (bool)$stmt_fq->fetchColumn();
}

$age = null;
// Afficher l'âge seulement si show_age est TRUE (booléen) et birth_date est défini
if (!empty($profile_data['birth_date']) && $profile_data['show_age'] === true) {
    $birth = new DateTime($profile_data['birth_date']);
    $age = (new DateTime())->diff($birth)->y;
}

// Mensurations (uniquement pour professions has_measurements)
$measurements = null;
$has_measurements_prof = (bool)($profile_data['has_measurements'] ?? false);
if ($has_measurements_prof) {
    $stmt_m = $db->prepare(
        "SELECT m.*, ec.name AS eye_color_name, hc.name AS hair_color_name, eth.name AS ethnicity_name
         FROM measurements m
         LEFT JOIN eye_colors ec ON ec.id = m.eye_color_id
         LEFT JOIN hair_colors hc ON hc.id = m.hair_color_id
         LEFT JOIN ethnicities eth ON eth.id = m.ethnicity_id
         WHERE m.user_id = :uid"
    );
    $stmt_m->execute([':uid' => $profile_id]);
    $measurements = $stmt_m->fetch(PDO::FETCH_ASSOC) ?: null;
}

$portfolioModel = new Portfolio($db);
$photos = $portfolioModel->getPhotos($profile_id);
$theme = $profile_data['profile_theme'] ?? 'classique';

// Likes par photo
$photo_ids = array_column($photos, 'id');
$likes_by_photo = [];
$user_liked_photos = [];
if (!empty($photo_ids)) {
    $in = implode(',', array_map('intval', $photo_ids));
    $lk_counts = $db->query("SELECT photo_id, COUNT(*) AS cnt FROM photo_likes WHERE photo_id IN ($in) GROUP BY photo_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($lk_counts as $row) $likes_by_photo[$row['photo_id']] = (int)$row['cnt'];
    if ($is_logged_in) {
        $lk_user = $db->prepare("SELECT photo_id FROM photo_likes WHERE user_id=:u AND photo_id IN ($in)");
        $lk_user->execute([':u' => $_SESSION['user_id']]);
        $user_liked_photos = array_flip($lk_user->fetchAll(PDO::FETCH_COLUMN));
    }
}

// Projets + required_profiles
$stmt_proj = $db->prepare(
    "SELECT p.*,
        COALESCE(json_agg(
            json_build_object(
                'role', rp.role_name,
                'min_height', rp.min_height, 'max_height', rp.max_height,
                'min_age', rp.min_age, 'max_age', rp.max_age,
                'chest', rp.chest_size, 'waist', rp.waist_size,
                'hip', rp.hip_size, 'shoe', rp.shoe_size,
                'eye', ec.name, 'hair', hc.name, 'ethnicity', eth.name
            ) ORDER BY rp.id
        ) FILTER (WHERE rp.id IS NOT NULL), '[]') AS required_profiles_json
    FROM projects p
    LEFT JOIN required_profiles rp ON rp.project_id = p.id
    LEFT JOIN eye_colors ec ON ec.id = rp.eye_color_id
    LEFT JOIN hair_colors hc ON hc.id = rp.hair_color_id
    LEFT JOIN ethnicities eth ON eth.id = rp.ethnicity_id
    WHERE p.user_id = :uid
    GROUP BY p.id
    ORDER BY p.created_at DESC"
);
$stmt_proj->execute([':uid' => $profile_id]);
$projects = $stmt_proj->fetchAll(PDO::FETCH_ASSOC);

$tags = [];
if (!empty($profile_data['expertise_tags'])) {
    $tags = array_values(array_filter(array_map('trim', explode(',', $profile_data['expertise_tags']))));
}
$tags_visible = array_slice($tags, 0, 3);
$tags_hidden  = array_slice($tags, 3);
$tags_uid     = 'tags-' . $profile_id; // ID unique pour le toggle

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
    <style>
    /* ── Mobile Instagram-style profile ── */
    #profil-mobile-header { display: none; }
    @media (max-width: 768px) {
      /* Show mobile header, hide topbar (already in mobile header) */
      #profil-mobile-header { display: block !important; }
      #mobile-topbar { display: none !important; }
      /* Hide desktop-only elements */
      .profil-classique-aside { display: none !important; }
      .profil-classique-name-row { display: none !important; }
      .profil-editorial-hero { display: none !important; }
      .profil-editorial-actions-row { display: none !important; }
      .profil-editorial-tags-row { display: none !important; }
      .profil-luxe-header { display: none !important; }
      .profil-luxe-separator { display: none !important; }
      /* Main wrappers */
      .profil-classique-main { flex-direction: column !important; padding: 0 0 100px !important; margin-top: 0 !important; gap: 0 !important; }
      .profil-editorial-main { padding: 0 0 100px !important; margin-top: 0 !important; }
      .profil-luxe-main { padding: 0 0 100px !important; margin-top: 0 !important; }
      /* Instagram 3-col square grid for all themes */
      .profil-masonry, .profil-editorial-grid, .profil-luxe-grid {
        column-count: unset !important;
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 2px !important;
      }
      .profil-masonry-item, .profil-editorial-item, .profil-luxe-item {
        margin-bottom: 0 !important;
        break-inside: unset !important;
        aspect-ratio: 1 / 1 !important;
        overflow: hidden !important;
        border-radius: 0 !important;
        background: #111;
      }
      .profil-masonry-item img, .profil-editorial-item img, .profil-luxe-item img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        border-radius: 0 !important;
        transform: none !important;
      }
      /* Luxe first photo (was col-span-2 banner) → normal square */
      .profil-luxe-item.profil-luxe-banner { aspect-ratio: 1 / 1 !important; grid-column: unset !important; }
      /* Edit mode: hidden must win over display:grid !important */
      #photos-view.hidden { display: none !important; }
      /* Projects section */
      .profil-projects-section { padding: 0 12px 16px !important; }
      /* Light theme mobile header */
      html.light .pmh-card { background: #f5f0eb !important; border-color: #e0d8d0 !important; }
      html.light .pmh-btn { background: #ece8e3 !important; border-color: #d0c8c0 !important; color: #333 !important; }
      html.light .pmh-tag { background: #ece8e3 !important; border-color: #d0c8c0 !important; color: #666 !important; }
      html.light .pmh-stat-label { color: #999 !important; }
      html.light .pmh-stat-val { color: #111 !important; }
      html.light .pmh-name { color: #111 !important; }
      html.light .pmh-profession { color: #a07aa0 !important; }
      html.light .pmh-sep { background: #e0d8d0 !important; }
    }
    /* ── Light theme — aside classique ── */
    html.light .cl-aside-sep       { border-color: #e8e2db !important; }
    html.light .cl-tag-pill        { background: #ece8e3 !important; border-color: #d8d2cb !important; color: #666 !important; }
    html.light .cl-meas-card       { background: #f5f0eb !important; border-color: #e0dbd4 !important; }
    html.light .cl-meas-label      { color: #aaa !important; }
    html.light .cl-meas-value      { color: #1a1a1a !important; }
    html.light .cl-meas-title      { color: #bbb !important; }
    html.light .cl-action-btn      { background: #f5f0eb !important; border-color: #e0dbd4 !important; color: #666 !important; }
    html.light .cl-action-btn:hover{ border-color: #a060a0 !important; color: #a060a0 !important; }
    /* Light theme — modal mensurations */
    html.light #measurements-modal > div { background: #ffffff !important; border-color: #e8e2db !important; }
    html.light #measurements-modal .meas-modal-item-bg { background: #f5f0eb !important; border-color: #e8e2db !important; }
    </style>
</head>
<body class="bg-black text-white">
<?php include 'includes/header.php'; ?>

<?php
// ─── Avatar mobile ────────────────────────────────────────────────────────
$mob_avatar = $profile_data['profile_picture_url'] ?? '';
if (empty($mob_avatar) && !empty($photos)) $mob_avatar = $photos[0]['image_url'];
$mob_initial = mb_strtoupper(mb_substr($profile_data['full_name'] ?? 'T', 0, 1));
$mob_city = '';
if (!empty($profile_data['city'])) $mob_city = $profile_data['city'];
elseif ($location) $mob_city = explode(',', $location)[0];
?>
<!-- ══ MOBILE HEADER style Instagram (visible ≤768px seulement) ══ -->
<div id="profil-mobile-header">
  <div style="padding:20px 16px 0 16px;text-align:center;">
    <!-- Avatar centré -->
    <div style="display:flex;justify-content:center;margin-bottom:12px;">
      <div style="width:82px;height:82px;border-radius:50%;overflow:hidden;background:#1a1a1a;border:2px solid #2a2a2a;">
        <?php if ($mob_avatar): ?>
          <img src="<?= htmlspecialchars($mob_avatar) ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
        <?php else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:900;color:#d4a5d4;"><?= $mob_initial ?></div>
        <?php endif; ?>
      </div>
    </div>
    <!-- Nom + profession + ville -->
    <div style="margin-bottom:8px;text-align:center;">
      <div class="pmh-name" style="font-size:15px;font-weight:900;color:#fff;line-height:1.2;"><?= $name ?></div>
      <div style="font-size:12px;color:#d4a5d4;font-weight:700;margin-top:3px;display:flex;align-items:center;justify-content:center;gap:6px;">
        <span><?= $profession ?></span>
        <?php if ($mob_city): ?>
          <span style="color:#444;">·</span>
          <span style="color:#666;font-weight:500;"><?= htmlspecialchars($mob_city) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <!-- Tags -->
    <?php if ($tags): ?>
    <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px;justify-content:center;" id="<?= $tags_uid ?>-mob">
      <?php foreach ($tags_visible as $t): ?>
        <span class="pmh-tag" style="background:#1a1a1a;border:1px solid #2a2a2a;color:#888;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;">#<?= htmlspecialchars($t) ?></span>
      <?php endforeach; ?>
      <?php foreach ($tags_hidden as $t): ?>
        <span class="pmh-tag tags-extra-mob" style="background:#1a1a1a;border:1px solid #2a2a2a;color:#888;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;display:none;">#<?= htmlspecialchars($t) ?></span>
      <?php endforeach; ?>
      <?php if ($tags_hidden): ?>
        <button onclick="toggleTags('mob')" id="<?= $tags_uid ?>-mob-btn" style="background:none;border:1px solid #333;color:#888;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;cursor:pointer;">+<?= count($tags_hidden) ?> voir plus</button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <!-- Boutons action -->
    <div style="display:flex;gap:7px;margin-bottom:14px;flex-wrap:wrap;justify-content:center;">
      <?php
      $lg = 'backdrop-filter:blur(16px) saturate(180%);-webkit-backdrop-filter:blur(16px) saturate(180%);background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.13);border-radius:20px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;color:#fff;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:5px;white-space:nowrap;';
      $lg_accent = 'backdrop-filter:blur(16px) saturate(180%);-webkit-backdrop-filter:blur(16px) saturate(180%);background:rgba(212,165,212,0.25);border:1px solid rgba(212,165,212,0.35);border-radius:20px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;color:#d4a5d4;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;';
      ?>
      <?php if (!$is_own_profile): ?>
        <button id="follow-btn-mobile" onclick="toggleFollow()"
            style="flex:1;<?= $is_following ? 'backdrop-filter:blur(16px) saturate(180%);-webkit-backdrop-filter:blur(16px) saturate(180%);background:rgba(212,165,212,0.35);border:1px solid rgba(212,165,212,0.5);border-radius:20px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;color:#d4a5d4;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:5px;white-space:nowrap;' : $lg ?>"
            data-following="<?= $is_following ? '1' : '0' ?>"
            data-target="<?= $profile_id ?>">
            <?= $is_following ? t('profile.following') : t('profile.follow') ?>
        </button>
        <a href="messagerie.php?with=<?= $profile_id ?>" style="flex:1;<?= $lg_accent ?>"><?= t('profile.contact') ?></a>
        <?php if ($followers_count > 0): ?>
          <span id="followers-count-mobile" style="font-size:11px;color:#888;text-align:center;width:100%;display:block;margin-top:-6px;margin-bottom:4px;">
            <strong style="color:#fff;"><?= $followers_count ?></strong> follower<?= $followers_count > 1 ? 's' : '' ?>
          </span>
        <?php else: ?>
          <span id="followers-count-mobile" style="font-size:11px;color:#888;display:none;width:100%;text-align:center;"></span>
        <?php endif; ?>
      <?php else: ?>
        <button onclick="toggleEditMode()" id="edit-photos-btn-mob" style="<?= $lg ?>"><?= t('profile.photos') ?></button>
        <a href="edit_profil.php" style="flex:1;<?= $lg ?>"><?= t('profile.edit') ?></a>
      <?php endif; ?>
      <?php if (!empty($profile_data['bio'])): ?>
        <button onclick="openBioModal()" style="<?= $lg ?>"><?= t('profile.bio') ?></button>
      <?php endif; ?>
      <?php if ($measurements): ?>
        <button onclick="openMeasurementsModal()" style="<?= $lg ?>"><?= t('profile.measurements') ?></button>
      <?php endif; ?>
      <button onclick="doShare()" style="<?= $lg ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
      </button>
    </div>
  </div>
  <!-- Séparateur -->
  <div class="pmh-sep" style="height:1px;background:#1a1a1a;margin-bottom:2px;"></div>
</div>

<?php
// ─── Bloc mensurations desktop ─────────────────────────────────────────────
function renderMeasurementsDesktop(array $m, bool $centered = false): void {
    $items = [];
    if (!empty($m['height']))          $items[] = ['Taille',            $m['height'] . ' cm'];
    if (!empty($m['chest_size']))      $items[] = ['Poitrine',          $m['chest_size'] . ' cm'];
    if (!empty($m['waist_size']))      $items[] = ['Tour de taille',    $m['waist_size'] . ' cm'];
    if (!empty($m['hip_size']))        $items[] = ['Hanches',           $m['hip_size'] . ' cm'];
    if (!empty($m['shoe_size']))       $items[] = ['Pointure',          $m['shoe_size']];
    if (!empty($m['eye_color_name']))  $items[] = ['Yeux',              $m['eye_color_name']];
    if (!empty($m['hair_color_name'])) $items[] = ['Cheveux',           $m['hair_color_name']];
    if (!empty($m['ethnicity_name']))  $items[] = ['Origine',           $m['ethnicity_name']];
    if (empty($items)) return;
    $center = $centered ? 'justify-center ' : '';
    echo '<div class="mb-4">';
    echo '<p class="text-[#555] text-xs font-bold uppercase tracking-widest mb-2 ' . ($centered ? 'text-center' : '') . '">Mensurations</p>';
    echo '<div class="flex flex-wrap gap-x-5 gap-y-1 ' . $center . '">';
    foreach ($items as [$label, $val]) {
        echo '<span class="text-xs text-[#777]"><span class="text-[#555] font-semibold">' . htmlspecialchars($label) . '</span> · ' . htmlspecialchars($val) . '</span>';
    }
    echo '</div></div>';
}

// ─── Actions dropdown (commun à tous les thèmes) ───────────────────────────
function renderActions($is_own_profile, $profile_id = 0, $is_following = false, $followers_count = 0) { ?>
    <div class="flex gap-3 items-center">
        <?php if (!$is_own_profile): ?>
            <button id="follow-btn-desktop"
                onclick="toggleFollow()"
                class="follow-btn px-5 py-2 rounded-full text-sm font-semibold border transition-all"
                style="<?= $is_following ? 'background:#d4a5d4;color:#000;border-color:#d4a5d4;' : 'background:#1e1e1e;color:#fff;border-color:#333;' ?>"
                data-following="<?= $is_following ? '1' : '0' ?>"
                data-target="<?= $profile_id ?>">
                <?= $is_following ? t('profile.following') : t('profile.follow') ?>
            </button>
            <a href="messagerie.php?with=<?= $profile_id ?>" class="bg-[#d4a5d4] text-black px-5 py-2 rounded-full text-sm font-bold hover:opacity-90 transition-opacity inline-flex items-center"><?= t('profile.contact') ?></a>
            <?php if ($followers_count > 0): ?>
                <span id="followers-count-desktop" class="text-[#666] text-sm"><span class="text-white font-bold"><?= $followers_count ?></span> follower<?= $followers_count > 1 ? 's' : '' ?></span>
            <?php else: ?>
                <span id="followers-count-desktop" class="text-[#666] text-sm" style="display:none;"></span>
            <?php endif; ?>
        <?php else: ?>
            <button onclick="toggleEditMode()" id="edit-photos-btn" class="flex items-center gap-2 bg-[#1e1e1e] text-white px-5 py-2 rounded-full text-sm font-semibold border border-[#333] hover:border-brand hover:text-brand transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <?= t('profile.photos') ?>
            </button>
            <a href="edit_profil.php" class="bg-[#1e1e1e] text-white px-5 py-2 rounded-full text-sm font-semibold border border-[#333] hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all"><?= t('profile.edit') ?></a>
            <?php if ($followers_count > 0): ?>
                <span class="text-[#666] text-sm"><span class="text-white font-bold"><?= $followers_count ?></span> follower<?= $followers_count > 1 ? 's' : '' ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php } ?>

<?php if ($theme === 'classique'): ?>
<!-- ═══════════════════════════════════════════════════════════════
     THÈME CLASSIQUE — sidebar gauche + masonry
═══════════════════════════════════════════════════════════════ -->
<main class="max-w-[1400px] mx-auto mt-10 mb-10 px-8 flex gap-12 profil-classique-main">

    <aside class="w-[240px] flex-shrink-0 flex flex-col profil-classique-aside" style="gap:0;">

        <!-- Profession + localisation -->
        <div class="mb-5">
            <p class="text-[#d4a5d4] text-[11px] font-black uppercase tracking-[0.2em] mb-1"><?= $profession ?></p>
            <?php if ($location): ?><p class="text-[#555] text-xs"><?= htmlspecialchars($location) ?><?= $age ? ' · '.$age.' ans' : '' ?></p><?php endif; ?>
        </div>

        <!-- Tags -->
        <?php if ($tags): ?>
        <div class="cl-aside-sep" style="border-top:1px solid #1e1e1e; padding-top:16px; margin-bottom:16px;">
            <div class="flex flex-wrap gap-1.5">
                <?php foreach ($tags_visible as $t): ?>
                    <span class="cl-tag-pill" style="background:#161616;border:1px solid #272727;color:#666;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;">#<?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
                <?php foreach ($tags_hidden as $t): ?>
                    <span class="cl-tag-pill tags-extra-cl" style="background:#161616;border:1px solid #272727;color:#666;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;display:none;">#<?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
                <?php if ($tags_hidden): ?>
                    <button onclick="toggleTags('cl')" id="<?= $tags_uid ?>-cl-btn" class="cl-tag-pill" style="background:none;border:1px solid #272727;color:#555;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;cursor:pointer;">+<?= count($tags_hidden) ?> voir plus</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Mensurations -->
        <?php if ($measurements): ?>
        <?php
        $meas_rows = [];
        if (!empty($measurements['height']))          $meas_rows[] = ['Taille',      $measurements['height'].' cm'];
        if (!empty($measurements['chest_size']))      $meas_rows[] = ['Poitrine',    $measurements['chest_size'].' cm'];
        if (!empty($measurements['waist_size']))      $meas_rows[] = ['Taille',      $measurements['waist_size'].' cm'];
        if (!empty($measurements['hip_size']))        $meas_rows[] = ['Hanches',     $measurements['hip_size'].' cm'];
        if (!empty($measurements['shoe_size']))       $meas_rows[] = ['Pointure',    $measurements['shoe_size']];
        if (!empty($measurements['eye_color_name']))  $meas_rows[] = ['Yeux',        $measurements['eye_color_name']];
        if (!empty($measurements['hair_color_name'])) $meas_rows[] = ['Cheveux',     $measurements['hair_color_name']];
        if (!empty($measurements['ethnicity_name']))  $meas_rows[] = ['Origine',     $measurements['ethnicity_name']];
        ?>
        <?php if ($meas_rows): ?>
        <div class="cl-aside-sep" style="border-top:1px solid #1e1e1e; padding-top:16px; margin-bottom:16px;">
            <p class="cl-meas-title" style="color:#333;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.15em;margin-bottom:10px;">Mensurations</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 6px;">
                <?php foreach ($meas_rows as [$lbl, $val]): ?>
                <div class="cl-meas-card" style="background:#111;border:1px solid #1e1e1e;border-radius:8px;padding:7px 10px;">
                    <p class="cl-meas-label" style="color:#444;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:2px;"><?= htmlspecialchars($lbl) ?></p>
                    <p class="cl-meas-value" style="color:#ccc;font-size:13px;font-weight:600;"><?= htmlspecialchars($val) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Actions -->
        <div class="cl-aside-sep" style="border-top:1px solid #1e1e1e; padding-top:16px; display:flex; flex-direction:column; gap:8px;">
            <?php if (!empty($profile_data['bio'])): ?>
                <button onclick="openBioModal()" class="cl-action-btn" style="display:flex;align-items:center;gap:8px;background:#111;border:1px solid #1e1e1e;border-radius:10px;padding:10px 14px;color:#777;font-size:12px;font-weight:600;cursor:pointer;transition:border-color .15s,color .15s;text-align:left;" onmouseover="this.style.borderColor='#d4a5d4';this.style.color='#d4a5d4'" onmouseout="this.style.borderColor='#1e1e1e';this.style.color='#777'">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <?= t('profile.bio') ?>
                </button>
            <?php elseif ($is_own_profile): ?>
                <a href="edit_profil.php" class="cl-meas-title" style="color:#333;font-size:12px;text-decoration:none;" onmouseover="this.style.color='#d4a5d4'" onmouseout="this.style.color='#333'">+ Ajouter une biographie</a>
            <?php endif; ?>
            <button id="btn-share" class="cl-action-btn" style="display:flex;align-items:center;gap:8px;background:#111;border:1px solid #1e1e1e;border-radius:10px;padding:10px 14px;color:#555;font-size:12px;font-weight:600;cursor:pointer;transition:border-color .15s,color .15s;" onmouseover="this.style.borderColor='#d4a5d4';this.style.color='#d4a5d4'" onmouseout="this.style.borderColor='#1e1e1e';this.style.color='#555'">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                <?= t('profile.share') ?>
            </button>
        </div>

    </aside>

    <section class="flex-grow min-w-0">
        <div class="flex justify-between items-center mb-8 pb-6 border-b border-[#1a1a1a] profil-classique-name-row">
            <h1 class="text-4xl font-bold text-white"><?= $name ?></h1>
            <?php renderActions($is_own_profile, $profile_id, $is_following, $followers_count); ?>
        </div>

        <!-- Vue normale -->
        <div id="photos-view" class="profil-masonry" style="column-count:3; column-gap:12px;">
            <?php if (empty($photos)): ?>
                <p class="text-[#555]">Aucune photo dans le book pour le moment.</p>
            <?php else: foreach ($photos as $idx => $photo): ?>
                <div class="profil-masonry-item" style="break-inside:avoid; margin-bottom:12px;">
                <?php if (!empty($photo['video_url'])): $yt_id = extractYoutubeId($photo['video_url']); ?>
                    <div class="relative rounded-xl overflow-hidden cursor-pointer hover:scale-[1.01] transition-transform duration-300" style="aspect-ratio:16/9;background:#111;" onclick="openPhotoLightbox(<?= $idx ?>)">
                        <img src="https://img.youtube.com/vi/<?= $yt_id ?>/hqdefault.jpg" class="w-full h-full object-cover" alt="">
                        <div class="absolute inset-0 flex items-center justify-center" style="background:rgba(0,0,0,0.25);">
                            <div style="width:52px;height:52px;border-radius:50%;background:rgba(212,165,212,0.92);display:flex;align-items:center;justify-content:center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="#000"><path d="M8 5v14l11-7z"/></svg>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <img src="<?= htmlspecialchars($photo['image_url']) ?>"
                         alt=""
                         class="w-full block rounded-xl hover:scale-[1.01] transition-transform duration-300 cursor-pointer"
                         onclick="openPhotoLightbox(<?= $idx ?>)"
                         data-photo-idx="<?= $idx ?>">
                <?php endif; ?>
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
<div class="relative w-full profil-editorial-hero" style="height: 400px; overflow:hidden;">
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
<div class="max-w-[1400px] mx-auto px-8 mt-4 flex justify-between items-center profil-editorial-actions-row">
    <button id="btn-share" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-[#2a2a2a] bg-black/40 text-[#aaa] text-xs font-medium hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        Partager le profil
    </button>
    <?php renderActions($is_own_profile, $profile_id, $is_following, $followers_count); ?>
</div>

<main class="max-w-[1400px] mx-auto px-8 mt-10 mb-10 profil-editorial-main">

    <!-- Tags + bio -->
    <?php if ($tags || !empty($profile_data['bio'])): ?>
    <div class="flex items-center gap-4 mb-10 pb-8 border-b border-[#1a1a1a] flex-wrap profil-editorial-tags-row">
        <?php if ($tags): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($tags_visible as $t): ?>
                    <span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] px-3 py-1 rounded-full text-xs font-semibold">#<?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
                <?php foreach ($tags_hidden as $t): ?>
                    <span class="tags-extra-ed bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] px-3 py-1 rounded-full text-xs font-semibold" style="display:none;">#<?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
                <?php if ($tags_hidden): ?>
                    <button onclick="toggleTags('ed')" id="<?= $tags_uid ?>-ed-btn" class="bg-transparent border border-[#333] text-[#888] px-3 py-1 rounded-full text-xs font-semibold cursor-pointer hover:border-[#888] transition-colors">+<?= count($tags_hidden) ?> voir plus</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($profile_data['bio'])): ?>
            <button onclick="openBioModal()" class="flex items-center gap-2 text-[#888] text-sm font-semibold border border-[#2a2a2a] rounded-full px-4 py-2 hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all bg-[#111]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Biographie
            </button>
        <?php endif; ?>
        <?php if ($measurements): ?>
            <?php renderMeasurementsDesktop($measurements); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($measurements && !($tags || !empty($profile_data['bio']))): ?>
    <div class="mb-10 pb-8 border-b border-[#1a1a1a] profil-editorial-tags-row">
        <?php renderMeasurementsDesktop($measurements); ?>
    </div>
    <?php endif; ?>

    <!-- Vue normale -->
    <div id="photos-view" class="grid gap-3 profil-editorial-grid" style="grid-template-columns: repeat(3, 1fr);">
        <?php
        $grid_photos = !empty($photos) ? array_slice($photos, 1) : [];
        foreach ($grid_photos as $idx => $photo):
            $real_idx = $idx + 1;
        ?>
            <div class="overflow-hidden rounded-xl profil-editorial-item <?= !empty($photo['video_url']) ? '' : 'aspect-square' ?> bg-[#111] relative cursor-pointer" <?= !empty($photo['video_url']) ? 'style="aspect-ratio:16/9;"' : '' ?> onclick="openPhotoLightbox(<?= $real_idx ?>)">
                <?php if (!empty($photo['video_url'])): $yt_id = extractYoutubeId($photo['video_url']); ?>
                    <img src="https://img.youtube.com/vi/<?= $yt_id ?>/hqdefault.jpg" class="w-full h-full object-cover hover:scale-[1.04] transition-transform duration-500" alt="">
                    <div class="absolute inset-0 flex items-center justify-center" style="background:rgba(0,0,0,0.25);">
                        <div style="width:44px;height:44px;border-radius:50%;background:rgba(212,165,212,0.92);display:flex;align-items:center;justify-content:center;">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="#000"><path d="M8 5v14l11-7z"/></svg>
                        </div>
                    </div>
                <?php else: ?>
                    <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="" class="w-full h-full object-cover hover:scale-[1.04] transition-transform duration-500">
                <?php endif; ?>
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
<main class="max-w-[1100px] mx-auto px-8 mt-16 mb-16 profil-luxe-main">

    <!-- Header centré -->
    <div class="text-center mb-4 profil-luxe-header">
        <p class="text-[#d4a5d4] text-[10px] font-black uppercase tracking-[0.4em] mb-6"><?= $profession ?></p>
        <h1 class="text-7xl font-black text-white leading-none mb-4" style="letter-spacing:-2px;"><?= $name ?></h1>
        <div class="flex items-center justify-center gap-3 text-[#555] text-sm mb-6">
            <?php if ($location): ?><span><?= htmlspecialchars($location) ?></span><?php endif; ?>
            <?php if ($age): ?><span>·</span><span><?= $age ?> ans</span><?php endif; ?>
        </div>
        <?php if ($tags): ?>
            <div class="flex flex-wrap gap-2 justify-center mb-6">
                <?php foreach ($tags_visible as $t): ?>
                    <span class="text-[#444] text-xs font-semibold uppercase tracking-wider"><?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
                <?php foreach ($tags_hidden as $t): ?>
                    <span class="tags-extra-lx text-[#444] text-xs font-semibold uppercase tracking-wider" style="display:none;"><?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
                <?php if ($tags_hidden): ?>
                    <button onclick="toggleTags('lx')" id="<?= $tags_uid ?>-lx-btn" class="bg-transparent border border-[#333] text-[#555] px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider cursor-pointer hover:border-[#555] transition-colors">+<?= count($tags_hidden) ?> voir plus</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($profile_data['bio'])): ?>
            <div class="flex justify-center mb-6">
                <button onclick="openBioModal()" class="flex items-center gap-2 text-[#888] text-sm font-semibold border border-[#2a2a2a] rounded-full px-4 py-2 hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all bg-[#111]">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <?= t('profile.bio') ?>
                </button>
            </div>
        <?php endif; ?>
        <?php if ($measurements): ?>
            <div class="flex justify-center mb-6">
                <?php renderMeasurementsDesktop($measurements, true); ?>
            </div>
        <?php endif; ?>
        <div class="flex justify-center gap-3 mb-4">
            <?php renderActions($is_own_profile, $profile_id, $is_following, $followers_count); ?>
        </div>
        <button id="btn-share" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-[#ddd] bg-white text-[#555] text-xs font-medium hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            Partager le profil
        </button>
    </div>

    <!-- Séparateur -->
    <div class="flex items-center gap-4 my-10 profil-luxe-separator">
        <div class="flex-grow h-px bg-[#1a1a1a]"></div>
        <div class="w-1.5 h-1.5 rounded-full bg-[#d4a5d4]"></div>
        <div class="flex-grow h-px bg-[#1a1a1a]"></div>
    </div>

    <!-- Vue normale -->
    <div id="photos-view" class="grid grid-cols-2 gap-3 profil-luxe-grid">
        <?php if (empty($photos)): ?>
            <p class="text-[#555] col-span-2 text-center">Aucune photo dans le book pour le moment.</p>
        <?php else: foreach ($photos as $idx => $photo): ?>
            <div class="overflow-hidden rounded-2xl bg-[#0e0e0e] profil-luxe-item relative cursor-pointer <?= $idx === 0 ? 'col-span-2 aspect-video profil-luxe-banner' : (!empty($photo['video_url']) ? 'col-span-2' : 'aspect-square') ?>" <?= (!empty($photo['video_url']) && $idx !== 0) ? 'style="aspect-ratio:16/9;"' : '' ?> onclick="openPhotoLightbox(<?= $idx ?>)">
                <?php if (!empty($photo['video_url'])): $yt_id = extractYoutubeId($photo['video_url']); ?>
                    <img src="https://img.youtube.com/vi/<?= $yt_id ?>/hqdefault.jpg" alt="" class="w-full h-full object-cover hover:scale-[1.03] transition-transform duration-500">
                    <div class="absolute inset-0 flex items-center justify-center" style="background:rgba(0,0,0,0.25);">
                        <div style="width:52px;height:52px;border-radius:50%;background:rgba(212,165,212,0.92);display:flex;align-items:center;justify-content:center;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="#000"><path d="M8 5v14l11-7z"/></svg>
                        </div>
                    </div>
                <?php else: ?>
                    <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="" class="w-full h-full object-cover hover:scale-[1.03] transition-transform duration-500">
                <?php endif; ?>
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

<!-- ═══════════════════════════════════════════════════════════════
     SECTION PROJETS (tous thèmes)
═══════════════════════════════════════════════════════════════ -->
<?php if (!empty($projects) || $is_own_profile): ?>
<div class="max-w-[1100px] mx-auto px-8 mb-16 profil-projects-section">

    <div class="flex items-center justify-between mb-6 pb-4 border-b border-[#1a1a1a]">
        <div>
            <h2 class="text-xl font-bold text-white"><?= t('profile.projects') ?></h2>
            <p class="text-[#555] text-sm mt-0.5">Collaborations et projets en cours de constitution</p>
        </div>
        <?php if ($is_own_profile): ?>
            <a href="creer_projet.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-[#d4a5d4] text-black text-sm font-bold hover:opacity-90 transition-opacity">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/></svg>
                <?= t('profile.new_project') ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($projects)): ?>
        <p class="text-[#444] text-sm">Aucun projet pour le moment.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
            <?php foreach ($projects as $proj):
                $profs = array_filter(array_map('trim', explode(',', $proj['searched_profiles'] ?? '')));
                $date_label = '';
                if (!empty($proj['expected_date'])) {
                    $d = new DateTime($proj['expected_date']);
                    $months = ['jan.','fév.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
                    $date_label = $d->format('j') . ' ' . $months[(int)$d->format('n')-1] . ' ' . $d->format('Y');
                }
            ?>
                <div class="bg-[#111] border border-[#1a1a1a] rounded-2xl p-6 flex flex-col gap-4 hover:border-[#2a2a2a] transition-colors cursor-pointer project-card"
                     data-project='<?= htmlspecialchars(json_encode([
                        'title'      => $proj['title'],
                        'type'       => $proj['project_type'] ?? '',
                        'date'       => $date_label,
                        'description'=> $proj['description'] ?? '',
                        'searched'   => $proj['searched_profiles'] ?? '',
                        'contact_name'  => $proj['contact_name'] ?? '',
                        'contact_email' => $proj['contact_email'] ?? '',
                        'contact_phone' => $proj['contact_phone'] ?? '',
                        'profiles'   => json_decode($proj['required_profiles_json'] ?? '[]', true),
                     ]), ENT_QUOTES) ?>'>

                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-grow min-w-0">
                            <?php if (!empty($proj['project_type'])): ?>
                                <span class="text-[#d4a5d4] text-[10px] font-black uppercase tracking-widest"><?= htmlspecialchars($proj['project_type']) ?></span>
                            <?php endif; ?>
                            <h3 class="text-white font-bold text-base mt-1 leading-snug truncate"><?= htmlspecialchars($proj['title']) ?></h3>
                        </div>
                        <?php if ($is_own_profile): ?>
                            <form method="POST" action="profil.php" onsubmit="return confirm('Supprimer ce projet ?')">
                                <input type="hidden" name="delete_project" value="1">
                                <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                                <button type="submit" class="w-7 h-7 flex items-center justify-center rounded-full text-[#444] hover:text-[#e05555] hover:bg-[#1a1a1a] transition-all flex-shrink-0" onclick="event.stopPropagation()">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if ($date_label): ?>
                        <div class="flex items-center gap-2 text-[#666] text-xs">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?= $date_label ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($proj['description'])): ?>
                        <p class="text-[#888] text-sm leading-relaxed line-clamp-2" style="-webkit-line-clamp:2;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden;"><?= htmlspecialchars($proj['description']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($profs)): ?>
                        <div class="flex flex-wrap gap-1.5 mt-auto pt-2 border-t border-[#1a1a1a]">
                            <?php foreach ($profs as $p): ?>
                                <span class="bg-[#1a1a1a] border border-[#2a2a2a] text-[#777] px-2.5 py-0.5 rounded-full text-xs font-semibold"><?= htmlspecialchars($p) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Modal détail projet -->
<div id="project-modal" class="hidden fixed inset-0 z-[3000] flex items-center justify-center bg-black/80 backdrop-blur-sm" onclick="if(event.target===this)closeProjectModal()">
    <div class="relative bg-[#111] rounded-2xl w-full max-w-lg mx-4 shadow-[0_32px_80px_rgba(0,0,0,0.8)] border border-[#1e1e1e]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#1e1e1e]">
            <div>
                <div id="pm-type" class="text-[#d4a5d4] text-[10px] font-black uppercase tracking-widest mb-1"></div>
                <div id="pm-title" class="text-white font-bold text-lg"></div>
            </div>
            <button onclick="closeProjectModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-[#1e1e1e] hover:bg-[#2a2a2a] text-[#888] hover:text-white transition-colors text-lg leading-none">✕</button>
        </div>
        <div class="px-6 py-5 flex flex-col gap-4 max-h-[65vh] overflow-y-auto">
            <div id="pm-date" class="text-[#666] text-xs flex items-center gap-2"></div>
            <div id="pm-description" class="text-[#aaa] text-sm leading-relaxed"></div>
            <div id="pm-searched"></div>
            <div id="pm-contact" class="border-t border-[#1e1e1e] pt-4 flex flex-col gap-2"></div>
        </div>
    </div>
</div>

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

<!-- Modale mensurations (mobile) -->
<?php if ($measurements): ?>
<?php
$meas_modal_items = [];
if (!empty($measurements['height']))          $meas_modal_items[] = ['Taille',         $measurements['height'] . ' cm'];
if (!empty($measurements['chest_size']))      $meas_modal_items[] = ['Poitrine',        $measurements['chest_size'] . ' cm'];
if (!empty($measurements['waist_size']))      $meas_modal_items[] = ['Tour de taille',  $measurements['waist_size'] . ' cm'];
if (!empty($measurements['hip_size']))        $meas_modal_items[] = ['Hanches',         $measurements['hip_size'] . ' cm'];
if (!empty($measurements['shoe_size']))       $meas_modal_items[] = ['Pointure',        $measurements['shoe_size']];
if (!empty($measurements['eye_color_name']))  $meas_modal_items[] = ['Yeux',            $measurements['eye_color_name']];
if (!empty($measurements['hair_color_name'])) $meas_modal_items[] = ['Cheveux',         $measurements['hair_color_name']];
if (!empty($measurements['ethnicity_name']))  $meas_modal_items[] = ['Origine',         $measurements['ethnicity_name']];
?>
<?php if ($meas_modal_items): ?>
<div id="measurements-modal" class="hidden fixed inset-0 z-[3000] flex items-end justify-center bg-black/80 backdrop-blur-sm" onclick="if(event.target===this)closeMeasurementsModal()">
    <div class="relative bg-[#111] rounded-t-2xl w-full max-w-lg shadow-[0_-16px_60px_rgba(0,0,0,0.8)] border-t border-x border-[#1e1e1e]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#1e1e1e]">
            <div>
                <div class="text-white font-bold text-base">Mensurations</div>
                <div class="text-[#d4a5d4] text-xs font-semibold mt-0.5"><?= $name ?></div>
            </div>
            <button onclick="closeMeasurementsModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-[#1e1e1e] hover:bg-[#2a2a2a] text-[#888] hover:text-white transition-colors text-lg leading-none">✕</button>
        </div>
        <div class="px-6 py-5 grid grid-cols-2 gap-x-6 gap-y-4 pb-10">
            <?php foreach ($meas_modal_items as [$label, $val]): ?>
            <div>
                <p class="text-[#555] text-xs font-bold uppercase tracking-widest mb-0.5"><?= htmlspecialchars($label) ?></p>
                <p class="text-white text-base font-semibold"><?= htmlspecialchars($val) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Modal Lightbox Photos -->
<div id="photo-lightbox" class="hidden fixed inset-0 z-[3500] flex items-center justify-center bg-black/95 backdrop-blur-sm" onclick="if(event.target.id==='photo-lightbox')closePhotoLightbox()">
    <div class="relative w-[95vw] h-[90vh] max-w-6xl flex flex-col">
        <!-- Bouton fermer -->
        <button onclick="closePhotoLightbox()" class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center rounded-full bg-[#1e1e1e] hover:bg-[#2a2a2a] text-[#888] hover:text-white transition-colors text-xl leading-none z-10">✕</button>
        
        <!-- Image / Vidéo principale -->
        <div class="flex-grow flex items-center justify-center min-h-0 relative">
            <img id="lightbox-image" src="" alt="" class="max-w-full max-h-full object-contain">
            <div id="lightbox-video-container" class="hidden w-full h-full flex items-center justify-content:center p-4">
                <div style="width:100%;max-width:900px;margin:auto;aspect-ratio:16/9;">
                    <iframe id="lightbox-iframe" src="" style="width:100%;height:100%;border-radius:12px;border:none;" allowfullscreen></iframe>
                </div>
            </div>
            
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
                <div class="flex items-center gap-3 flex-shrink-0">
                    <!-- Bouton like lightbox -->
                    <button id="lightbox-like-btn" onclick="toggleLightboxLike()" class="flex items-center gap-2 px-4 py-2 rounded-lg bg-[#1e1e1e] hover:bg-[#2a2a2a] transition-colors text-sm font-semibold">
                        <svg id="lightbox-like-icon" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                        <span id="lightbox-like-count">0</span>
                    </button>
                    <?php if ($is_own_profile): ?>
                    <button onclick="openPhotoEditor()" class="px-4 py-2 bg-[#1e1e1e] text-white rounded-lg text-sm font-semibold hover:bg-[#2a2a2a] transition-colors">
                        ✎ Éditer
                    </button>
                    <?php endif; ?>
                </div>
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
<?php
// Enrichir chaque photo avec likes_count + user_liked
$photos_with_likes = array_map(function($ph) use ($likes_by_photo, $user_liked_photos) {
    $ph['likes_count'] = $likes_by_photo[$ph['id']] ?? 0;
    $ph['user_liked']  = isset($user_liked_photos[$ph['id']]);
    return $ph;
}, $photos);
?>
const allPhotos = <?= json_encode($photos_with_likes) ?>;
const PROFILE_PAGE_URL = 'profil.php?id=<?= $profile_id ?>';
const IS_LOGGED_IN = <?= $is_logged_in ? 'true' : 'false' ?>;

function openPhotoLightbox(idx) {
    if (!allPhotos || allPhotos.length === 0) return;
    currentPhotoIdx = idx;
    updateLightbox();
    document.getElementById('photo-lightbox').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closePhotoLightbox() {
    document.getElementById('photo-lightbox').classList.add('hidden');
    document.getElementById('lightbox-iframe').src = '';
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

    const img = document.getElementById('lightbox-image');
    const videoContainer = document.getElementById('lightbox-video-container');
    const iframe = document.getElementById('lightbox-iframe');
    const likeBtn = document.getElementById('lightbox-like-btn');

    if (photo.video_url) {
        const ytId = photo.video_url.match(/[?&]v=([a-zA-Z0-9_-]{11})/)?.[1] || '';
        iframe.src = 'https://www.youtube.com/embed/' + ytId + '?autoplay=1&rel=0';
        img.classList.add('hidden');
        img.src = '';
        videoContainer.classList.remove('hidden');
        if (likeBtn) likeBtn.style.display = 'none';
    } else {
        iframe.src = '';
        videoContainer.classList.add('hidden');
        img.classList.remove('hidden');
        img.src = photo.image_url;
        if (likeBtn) likeBtn.style.display = '';
    }
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
    // Likes
    const likeCount = document.getElementById('lightbox-like-count');
    const likeIcon  = document.getElementById('lightbox-like-icon');
    const likeBtn   = document.getElementById('lightbox-like-btn');
    if (likeCount) likeCount.textContent = photo.likes_count || 0;
    if (likeIcon && likeBtn) {
        const liked = photo.user_liked;
        likeIcon.setAttribute('fill', liked ? '#d4a5d4' : 'none');
        likeIcon.setAttribute('stroke', liked ? '#d4a5d4' : '#888');
        likeBtn.style.color = liked ? '#d4a5d4' : '#888';
    }
}

function toggleLightboxLike() {
    if (!IS_LOGGED_IN) { window.location.href = 'connexion.php'; return; }
    const photo = allPhotos[currentPhotoIdx];
    if (!photo) return;
    const likeIcon  = document.getElementById('lightbox-like-icon');
    const likeCount = document.getElementById('lightbox-like-count');
    const likeBtn   = document.getElementById('lightbox-like-btn');
    // Optimistic update
    photo.user_liked = !photo.user_liked;
    photo.likes_count = photo.user_liked ? (photo.likes_count || 0) + 1 : Math.max(0, (photo.likes_count || 0) - 1);
    likeIcon.setAttribute('fill', photo.user_liked ? '#d4a5d4' : 'none');
    likeIcon.setAttribute('stroke', photo.user_liked ? '#d4a5d4' : '#888');
    likeBtn.style.color = photo.user_liked ? '#d4a5d4' : '#888';
    likeCount.textContent = photo.likes_count;
    fetch(PROFILE_PAGE_URL, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'toggle_photo_like=1&photo_id=' + photo.id
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            photo.user_liked = data.liked;
            photo.likes_count = data.count;
            likeCount.textContent = data.count;
            likeIcon.setAttribute('fill', data.liked ? '#d4a5d4' : 'none');
            likeIcon.setAttribute('stroke', data.liked ? '#d4a5d4' : '#888');
            likeBtn.style.color = data.liked ? '#d4a5d4' : '#888';
        }
    });
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
        if (!document.getElementById('project-modal').classList.contains('hidden')) { closeProjectModal(); return; }
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
    const btnMob = document.getElementById('edit-photos-btn-mob');
    if (!view || !edit) return;
    const editing = !edit.classList.contains('hidden');
    if (editing) {
        edit.classList.add('hidden');
        view.classList.remove('hidden');
        if (btn) { btn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg> Photos`; btn.style.color = ''; btn.style.borderColor = ''; }
        if (btnMob) { btnMob.textContent = 'Photos'; btnMob.style.color = ''; }
        location.reload();
    } else {
        view.classList.add('hidden');
        edit.classList.remove('hidden');
        if (btn) { btn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Terminer`; btn.style.color = '#d4a5d4'; btn.style.borderColor = '#d4a5d4'; }
        if (btnMob) { btnMob.textContent = 'Terminer'; btnMob.style.color = '#d4a5d4'; }
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
function openMeasurementsModal() {
    document.getElementById('measurements-modal')?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeMeasurementsModal() {
    document.getElementById('measurements-modal')?.classList.add('hidden');
    document.body.style.overflow = '';
}

// ─── Modal Projets ────────────────────────────────────────────────────────
document.querySelectorAll('.project-card').forEach(card => {
    card.addEventListener('click', () => {
        const p = JSON.parse(card.dataset.project);
        document.getElementById('pm-type').textContent = p.type || '';
        document.getElementById('pm-title').textContent = p.title;

        const dateEl = document.getElementById('pm-date');
        if (p.date) {
            dateEl.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>${p.date}`;
            dateEl.classList.remove('hidden');
        } else { dateEl.classList.add('hidden'); }

        const descEl = document.getElementById('pm-description');
        descEl.textContent = p.description || '';
        descEl.style.display = p.description ? '' : 'none';

        const searchedEl = document.getElementById('pm-searched');
        searchedEl.innerHTML = '';
        if (p.searched) {
            const profs = p.searched.split(',').map(s => s.trim()).filter(s => s);
            if (profs.length) {
                const label = document.createElement('p');
                label.className = 'text-[#555] text-xs font-bold uppercase tracking-widest mb-2';
                label.textContent = 'Profils recherchés';
                searchedEl.appendChild(label);
                const wrap = document.createElement('div');
                wrap.className = 'flex flex-wrap gap-2 mb-3';
                profs.forEach(pr => {
                    const sp = document.createElement('span');
                    sp.className = 'bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] px-3 py-1 rounded-full text-xs font-semibold';
                    sp.textContent = pr;
                    wrap.appendChild(sp);
                });
                searchedEl.appendChild(wrap);
            }
        }

        // Mensurations par profil talent
        if (p.profiles && p.profiles.length) {
            p.profiles.forEach(rp => {
                if (!rp.role) return;
                const block = document.createElement('div');
                block.className = 'bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl p-4 mb-2';

                const roleTitle = document.createElement('p');
                roleTitle.className = 'text-[#d4a5d4] text-[10px] font-black uppercase tracking-widest mb-3';
                roleTitle.textContent = rp.role + ' — Critères physiques';
                block.appendChild(roleTitle);

                const grid = document.createElement('div');
                grid.className = 'grid grid-cols-2 gap-2 text-xs';

                const fields = [
                    ['Taille', rp.min_height && rp.max_height ? rp.min_height + ' – ' + rp.max_height + ' cm' : (rp.min_height ? '≥ ' + rp.min_height + ' cm' : (rp.max_height ? '≤ ' + rp.max_height + ' cm' : null))],
                    ['Âge', rp.min_age && rp.max_age ? rp.min_age + ' – ' + rp.max_age + ' ans' : (rp.min_age ? '≥ ' + rp.min_age + ' ans' : (rp.max_age ? '≤ ' + rp.max_age + ' ans' : null))],
                    ['Poitrine', rp.chest],
                    ['Taille', rp.waist],
                    ['Hanches', rp.hip],
                    ['Pointure', rp.shoe],
                    ['Yeux', rp.eye],
                    ['Cheveux', rp.hair],
                    ['Ethnicité', rp.ethnicity],
                ];
                fields.forEach(([lbl, val]) => {
                    if (!val) return;
                    const cell = document.createElement('div');
                    cell.innerHTML = `<span style="color:#555;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">${lbl}</span><br><span style="color:#aaa;">${val}</span>`;
                    grid.appendChild(cell);
                });

                if (grid.children.length) {
                    block.appendChild(grid);
                    searchedEl.appendChild(block);
                }
            });
        }

        const contactEl = document.getElementById('pm-contact');
        contactEl.innerHTML = '';
        if (p.contact_name || p.contact_email || p.contact_phone) {
            const label = document.createElement('p');
            label.className = 'text-[#555] text-xs font-bold uppercase tracking-widest mb-1';
            label.textContent = 'Contact';
            contactEl.appendChild(label);
            if (p.contact_name) { const el = document.createElement('p'); el.className = 'text-[#aaa] text-sm'; el.textContent = p.contact_name; contactEl.appendChild(el); }
            if (p.contact_email) { const el = document.createElement('a'); el.className = 'text-[#d4a5d4] text-sm hover:underline'; el.href = 'mailto:' + p.contact_email; el.textContent = p.contact_email; contactEl.appendChild(el); }
            if (p.contact_phone) { const el = document.createElement('p'); el.className = 'text-[#888] text-sm'; el.textContent = p.contact_phone; contactEl.appendChild(el); }
            contactEl.style.display = '';
        } else { contactEl.style.display = 'none'; }

        document.getElementById('project-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    });
});

function closeProjectModal() {
    document.getElementById('project-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

async function doShare() {
    const shareData = {
        title: '<?= addslashes($name) ?> - ChicBook',
        text: 'Découvrez le portfolio de <?= addslashes($name) ?> sur ChicBook !',
        url: '<?= $share_url ?>'
    };
    if (navigator.share) {
        try { await navigator.share(shareData); } catch(e) {}
    } else {
        navigator.clipboard.writeText(shareData.url).then(() => {
            const btn = document.getElementById('btn-share') || document.querySelector('[onclick="doShare()"]');
            if (!btn) return;
            const orig = btn.innerHTML;
            btn.textContent = '✓ Copié !';
            btn.style.color = '#d4a5d4';
            setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; }, 3000);
        });
    }
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

// ── Système de suivi (Follow) ─────────────────────────────────────────────
async function toggleFollow() {
    <?php if (!$is_logged_in): ?>
    window.location.href = 'connexion.php';
    return;
    <?php endif; ?>
    const btns = [document.getElementById('follow-btn-desktop'), document.getElementById('follow-btn-mobile')];
    const btn = btns.find(b => b);
    if (!btn) return;
    const targetId = btn.dataset.target;
    const fd = new FormData();
    fd.append('toggle_follow', '1');
    fd.append('target_id', targetId);
    const res = await fetch('profil.php?id=<?= $profile_id ?>', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) return;
    // Mettre à jour tous les boutons follow
    btns.forEach(b => {
        if (!b) return;
        b.dataset.following = data.following ? '1' : '0';
        if (data.following) {
            b.textContent = 'Suivi ✓';
            b.style.background = 'rgba(212,165,212,0.35)';
            b.style.borderColor = 'rgba(212,165,212,0.5)';
            b.style.color = '#d4a5d4';
        } else {
            b.textContent = 'Suivre';
            b.style.background = '#1e1e1e';
            b.style.borderColor = '#333';
            b.style.color = '#fff';
        }
    });
    // Mettre à jour compteurs
    ['followers-count-desktop', 'followers-count-mobile'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (data.count > 0) {
            el.innerHTML = `<strong style="color:#fff;">${data.count}</strong> follower${data.count > 1 ? 's' : ''}`;
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    });
}

// Tags "voir plus / voir moins"
function toggleTags(suffix) {
    const extras = document.querySelectorAll('.tags-extra-' + suffix);
    const btn    = document.getElementById('<?= $tags_uid ?>-' + suffix + '-btn');
    const hidden = extras[0] && extras[0].style.display === 'none';
    extras.forEach(el => el.style.display = hidden ? '' : 'none');
    if (btn) btn.textContent = hidden ? 'voir moins' : '+<?= count($tags_hidden) ?> voir plus';
}
</script>
</body>
</html>
