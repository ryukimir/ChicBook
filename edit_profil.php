<?php
session_start();
require_once 'config/Database.php';
require_once 'models/User.php';
require_once 'models/Portfolio.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$db = Database::getInstance()->getConnection();
$userModel = new User($db);
$portfolioModel = new Portfolio($db);

$eye_colors    = $db->query("SELECT id, name FROM eye_colors ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$hair_colors   = $db->query("SELECT id, name FROM hair_colors ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$ethnicities   = $db->query("SELECT id, name FROM ethnicities ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_general'])) {
        $show_age   = isset($_POST['show_age']) && $_POST['show_age'] === '1';
        $gender     = trim($_POST['gender'] ?? '');
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        if ($userModel->updateGeneralInfo($_SESSION['user_id'], $_POST['full_name'], $_POST['city'], $_POST['country'], $show_age, $gender, $birth_date)) {
            $message = "Informations générales mises à jour !";
        } else { $error = "Erreur lors de la mise à jour."; }
    }
    if (isset($_POST['update_measurements'])) {
        $mdata = [
            'height'       => trim($_POST['height'] ?? ''),
            'chest_size'   => trim($_POST['chest_size'] ?? ''),
            'waist_size'   => trim($_POST['waist_size'] ?? ''),
            'hip_size'     => trim($_POST['hip_size'] ?? ''),
            'shoe_size'    => trim($_POST['shoe_size'] ?? ''),
            'eye_color_id' => intval($_POST['eye_color_id'] ?? 0) ?: null,
            'hair_color_id'=> intval($_POST['hair_color_id'] ?? 0) ?: null,
            'ethnicity_id' => intval($_POST['ethnicity_id'] ?? 0) ?: null,
        ];
        if ($userModel->upsertMeasurements($_SESSION['user_id'], $mdata)) {
            $message = "Mensurations mises à jour !";
        } else { $error = "Erreur lors de la mise à jour des mensurations."; }
    }
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "uploads/";
        $file_name = "avatar_" . $_SESSION['user_id'] . "_" . time() . "." . pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $userModel->updateProfilePicture($_SESSION['user_id'], $target_file);
            $_SESSION['user_avatar'] = $target_file;
            $message = "Photo de profil mise à jour !";
        } else { $error = "Erreur lors du téléchargement de l'avatar."; }
    }
    if (isset($_POST['update_expertise'])) {
        $profession = $_POST['profession'] ?? '';
        $tags_string = trim($_POST['tags_string'] ?? '');
        if ($userModel->updateExpertise($_SESSION['user_id'], $profession, $tags_string)) {
            $message = "Expertise et mots-clés mis à jour !";
        } else { $error = "Erreur lors de la mise à jour de l'expertise."; }
        $user = $userModel->getUserProfile($_SESSION['user_id']);
    }
    if (isset($_POST['update_password']) && !empty($_POST['current_password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            if ($userModel->updatePassword($_SESSION['user_id'], $_POST['current_password'], $_POST['new_password'])) {
                $message = "Mot de passe modifié avec succès !";
            } else { $error = "L'ancien mot de passe est incorrect."; }
        } else { $error = "Les nouveaux mots de passe ne correspondent pas."; }
    }
    if (isset($_POST['update_theme'])) {
        $userModel->updateTheme($_SESSION['user_id'], $_POST['theme'] ?? 'classique');
        $message = "Thème mis à jour !";
    }
    if (isset($_POST['update_bio'])) {
        $userModel->updateInfo($_SESSION['user_id'], $_POST['bio']);
        $message = "Biographie mise à jour !";
    }
    if (isset($_POST['delete_photo'])) {
        $photo_id = intval($_POST['photo_id']);
        $photo = $portfolioModel->getPhotoById($photo_id);
        if ($photo && $photo['user_id'] == $_SESSION['user_id']) {
            $portfolioModel->deletePhoto($photo_id, $_SESSION['user_id']);
            if (!empty($photo['image_url']) && file_exists($photo['image_url'])) {
                unlink($photo['image_url']);
            }
            $message = "Photo supprimée.";
        }
    }
    if (isset($_FILES['portfolio_image']) && $_FILES['portfolio_image']['error'] == 0) {
        $target_dir = "uploads/";
        $file_name = time() . "_" . basename($_FILES["portfolio_image"]["name"]);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES["portfolio_image"]["tmp_name"], $target_file)) {
            $portfolioModel->addPhoto($_SESSION['user_id'], $target_file);
            $message = "Photo ajoutée au book avec succès !";
        } else { $error = "Erreur lors du téléchargement de l'image."; }
    }
}

$user         = $userModel->getUserProfile($_SESSION['user_id']);
$measurements = $userModel->getMeasurements($_SESSION['user_id']);
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paramètres du Profil - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css" />
    <style>
      html, body { height: 100%; overflow: hidden; margin: 0; padding: 0 !important; }
      #sidebar { display: none !important; }
      .input-field { width:100%; padding:12px; border-radius:6px; border:1px solid #444; background:#111; color:white; font-family:inherit; font-size:14px; }
      .input-field:disabled { background:#222; color:#666; cursor:not-allowed; }
      textarea.input-field { height:120px; resize:vertical; }
      .tab-panel { display: none; }
      .tab-panel.active { display: block; }
      .nav-item { display:block; width:100%; text-align:left; padding:10px 14px; border-radius:8px; font-size:14px; color:#aaa; background:none; border:none; cursor:pointer; transition:background .15s, color .15s; }
      .nav-item:hover { background:#333; color:#fff; }
      .nav-item.active { background:#2a2a2a; color:#fff; font-weight:600; }
    </style>
</head>
<body class="bg-[#1a1a1a] text-white" style="font-family:'Open Sans',sans-serif;">
    <div class="flex h-screen">

        <!-- Menu latéral onglets -->
        <aside class="w-[240px] bg-[#111] border-r border-[#222] flex flex-col flex-shrink-0 p-6">
            <h3 class="text-white text-sm font-bold uppercase tracking-widest mb-6 px-2 opacity-50">Paramètres</h3>

            <?php
            $tabs = [
                'infos'        => 'Informations générales',
                'expertise'    => 'Expertise & Tags',
                'bio'          => 'Biographie',
                'mensurations' => 'Mensurations',
                'theme'        => 'Thème du profil',
                'securite'     => 'Sécurité',
            ];
            $active_tab = $_GET['tab'] ?? (array_key_first($tabs));
            if (!isset($tabs[$active_tab])) $active_tab = array_key_first($tabs);
            ?>

            <nav class="flex flex-col gap-1 flex-grow">
                <?php foreach ($tabs as $key => $label): ?>
                    <button onclick="switchTab('<?= $key ?>')" id="nav-<?= $key ?>"
                            class="nav-item <?= $key === $active_tab ? 'active' : '' ?>">
                        <?= $label ?>
                    </button>
                <?php endforeach; ?>
            </nav>

            <div class="border-t border-[#2a2a2a] pt-4 flex flex-col gap-1">
                <a href="profil.php" class="nav-item" style="color:#d4a5d4;">Retour au profil</a>
                <a href="logout.php" class="nav-item" style="color:#e57373;">Se déconnecter</a>
            </div>
        </aside>

        <!-- Zone de contenu -->
        <main class="flex-grow overflow-y-auto bg-[#0e0e0e]">
            <div class="max-w-[640px] mx-auto py-10 px-8">

                <?php if ($message): ?>
                    <div class="bg-[rgba(46,125,50,0.2)] text-[#81c784] border border-[#2e7d32] p-4 rounded-lg mb-6"><?= $message ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-[rgba(198,40,40,0.2)] text-[#e57373] border border-[#c62828] p-4 rounded-lg mb-6"><?= $error ?></div>
                <?php endif; ?>

                <!-- ── Informations générales + Photo ── -->
                <div id="tab-infos" class="tab-panel <?= $active_tab === 'infos' ? 'active' : '' ?>">
                    <h2 class="text-xl font-semibold mb-6">Informations générales</h2>
                    <form id="form-infos" action="edit_profil.php?tab=infos" method="POST" enctype="multipart/form-data" class="flex flex-col gap-5">
                        <input type="hidden" name="update_general" value="1">

                        <!-- Photo de profil -->
                        <div class="flex items-center gap-6 pb-6 border-b border-[#2a2a2a]">
                            <div class="w-20 h-20 bg-[#d4a5d4] rounded-full overflow-hidden flex-shrink-0 flex items-center justify-center text-3xl" id="avatar-preview-wrap">
                                <?php if (!empty($user['profile_picture_url'])): ?>
                                    <img src="<?= htmlspecialchars($user['profile_picture_url']) ?>" class="w-full h-full object-cover" id="avatar-preview-img">
                                <?php else: ?>
                                    <span id="avatar-preview-placeholder">👤</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow">
                                <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Photo de profil</label>
                                <input type="file" id="avatar-file-input" name="profile_pic" accept="image/*" class="input-field">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Email</label>
                            <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled class="input-field">
                        </div>
                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Nom complet</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required class="input-field">
                        </div>
                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Date de naissance</label>
                            <input type="date" name="birth_date" value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>" class="input-field">
                        </div>
                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Genre</label>
                            <?php $cur_gender = $user['gender'] ?? ''; ?>
                            <div class="flex gap-2 flex-wrap">
                                <?php foreach (['Homme', 'Femme', 'Non-binaire', 'Autre'] as $g): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="gender" value="<?= $g ?>" class="sr-only gender-radio" <?= $cur_gender === $g ? 'checked' : '' ?>>
                                        <span class="gender-pill inline-block px-4 py-2 rounded-full text-sm font-semibold border transition-all"
                                              style="<?= $cur_gender === $g ? 'background:#d4a5d4;color:#000;border-color:#d4a5d4;' : 'background:transparent;color:#888;border-color:#333;' ?>">
                                            <?= $g ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Ville</label>
                                <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" required class="input-field">
                            </div>
                            <div class="flex-1">
                                <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Pays</label>
                                <input type="text" name="country" value="<?= htmlspecialchars($user['country'] ?? '') ?>" required class="input-field">
                            </div>
                        </div>

                        <!-- Afficher l'âge -->
                        <label class="flex items-center gap-3 cursor-pointer select-none group">
                            <input type="hidden" name="show_age" value="0">
                            <input type="checkbox" name="show_age" value="1" id="show-age-checkbox"
                                   <?= !empty($user['show_age']) ? 'checked' : '' ?>
                                   class="sr-only">
                            <div id="show-age-track"
                                 class="w-10 h-5 rounded-full transition-colors duration-200 flex items-center px-0.5 flex-shrink-0"
                                 style="background:<?= !empty($user['show_age']) ? '#d4a5d4' : '#333' ?>;">
                                <div id="show-age-thumb"
                                     class="w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"
                                     style="transform:<?= !empty($user['show_age']) ? 'translateX(20px)' : 'translateX(0)' ?>;"></div>
                            </div>
                            <span class="text-sm text-[#aaa] group-hover:text-white transition-colors">Afficher mon âge sur le profil public</span>
                        </label>
                    </form>
                </div>

                <!-- ── Expertise & Tags ── -->
                <div id="tab-expertise" class="tab-panel <?= $active_tab === 'expertise' ? 'active' : '' ?>">
                    <h2 class="text-xl font-semibold mb-2">Expertise & Tags</h2>
                    <p class="text-[#555] text-sm mb-6">Cliquez sur les tags pour les sélectionner.</p>
                    <form id="form-expertise" action="edit_profil.php?tab=expertise" method="POST" class="flex flex-col gap-5">
                        <input type="hidden" name="update_expertise" value="1">
                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Profession</label>
                            <input type="text" name="profession" value="<?= htmlspecialchars($user['specific_profession'] ?? '') ?>" placeholder="ex: Photographe, Styliste…" class="input-field">
                        </div>
                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-3">Tags</label>
                            <?php
                            $saved_tags_arr = array_map('trim', explode(',', $user['expertise_tags'] ?? ''));
                            $all_edit_tags = ['Brodeur','Haute couture','Luxe','Editorial','Créatif','Premium','Fashion week','Minimaliste','Streetwear','Avant-garde','Moderne','International','Haut de gamme','Commercial','Artistique','Perlage','Ornementation','Textile','Broderie','Couture','Coiffeur','Défilé','Beauté','Hair stylist','Mode','Comédien','Acteur','Campagne','Publicité','Fashion','Film','Danseur','Contemporain','Performance','Mouvement','Designer','Sacs','Bijoux','Chaussures','Maroquinerie','Accessoires','Imprimés','Maille','Surface','Mannequin','Maquilleur','Modéliste','Patronage','Atelier','Photographe','Studio','Styliste','Créateur','Photo','Célébrité','Plateau','Vidéaste','Backstage','Réalisateur','Contenu'];
                            ?>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($all_edit_tags as $t):
                                    $sel = in_array($t, $saved_tags_arr); ?>
                                    <button type="button" onclick="toggleEditTag(this)" data-tag="<?= htmlspecialchars($t) ?>" data-selected="<?= $sel ? '1' : '0' ?>"
                                            class="tag-pill px-3 py-1.5 rounded-full text-xs font-semibold border cursor-pointer"
                                            style="<?= $sel ? 'background:#d4a5d4;color:#000;border-color:#d4a5d4;' : 'background:transparent;color:#888;border-color:#333;' ?>">
                                        <?= htmlspecialchars($t) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="tags_string" id="edit-tags-input" value="<?= htmlspecialchars($user['expertise_tags'] ?? '') ?>">
                        </div>
                    </form>
                </div>

                <!-- ── Biographie ── -->
                <div id="tab-bio" class="tab-panel <?= $active_tab === 'bio' ? 'active' : '' ?>">
                    <h2 class="text-xl font-semibold mb-6">Biographie</h2>
                    <form id="form-bio" action="edit_profil.php?tab=bio" method="POST" class="flex flex-col gap-4">
                        <input type="hidden" name="update_bio" value="1">
                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Votre parcours</label>
                            <textarea name="bio" placeholder="Racontez votre parcours, votre style, votre vision…" class="input-field" style="height:200px;"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>
                    </form>
                </div>

                <!-- ── Mensurations ── -->
                <div id="tab-mensurations" class="tab-panel <?= $active_tab === 'mensurations' ? 'active' : '' ?>">
                    <h2 class="text-xl font-semibold mb-2">Mensurations</h2>
                    <p class="text-[#555] text-sm mb-6">Ces informations sont utilisées pour les castings nécessitant des critères physiques.</p>
                    <form id="form-mensurations" action="edit_profil.php?tab=mensurations" method="POST" class="flex flex-col gap-5">
                        <input type="hidden" name="update_measurements" value="1">

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Taille (cm)</label>
                                <input type="number" name="height" min="100" max="250" value="<?= htmlspecialchars($measurements['height'] ?? '') ?>" placeholder="ex: 175" class="input-field">
                            </div>
                            <div>
                                <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Pointure</label>
                                <input type="number" name="shoe_size" min="28" max="60" value="<?= htmlspecialchars($measurements['shoe_size'] ?? '') ?>" placeholder="ex: 40" class="input-field">
                            </div>
                            <div>
                                <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Tour de poitrine (cm)</label>
                                <input type="number" name="chest_size" min="50" max="150" value="<?= htmlspecialchars($measurements['chest_size'] ?? '') ?>" placeholder="ex: 88" class="input-field">
                            </div>
                            <div>
                                <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Tour de taille (cm)</label>
                                <input type="number" name="waist_size" min="40" max="150" value="<?= htmlspecialchars($measurements['waist_size'] ?? '') ?>" placeholder="ex: 65" class="input-field">
                            </div>
                            <div>
                                <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Tour de hanches (cm)</label>
                                <input type="number" name="hip_size" min="50" max="180" value="<?= htmlspecialchars($measurements['hip_size'] ?? '') ?>" placeholder="ex: 92" class="input-field">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Couleur des yeux</label>
                            <select name="eye_color_id" class="input-field">
                                <option value="">— Non renseigné —</option>
                                <?php foreach ($eye_colors as $ec): ?>
                                    <option value="<?= $ec['id'] ?>" <?= ($measurements['eye_color_id'] ?? null) == $ec['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ec['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Couleur des cheveux</label>
                            <select name="hair_color_id" class="input-field">
                                <option value="">— Non renseigné —</option>
                                <?php foreach ($hair_colors as $hc): ?>
                                    <option value="<?= $hc['id'] ?>" <?= ($measurements['hair_color_id'] ?? null) == $hc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($hc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Ethnicité</label>
                            <select name="ethnicity_id" class="input-field">
                                <option value="">— Non renseigné —</option>
                                <?php foreach ($ethnicities as $eth): ?>
                                    <option value="<?= $eth['id'] ?>" <?= ($measurements['ethnicity_id'] ?? null) == $eth['id'] ? 'selected' : '' ?>><?= htmlspecialchars($eth['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <!-- ── Thème du profil ── -->
                <div id="tab-theme" class="tab-panel <?= $active_tab === 'theme' ? 'active' : '' ?>">
                    <h2 class="text-xl font-semibold mb-2">Thème du profil</h2>
                    <p class="text-[#555] text-sm mb-6">Choisissez l'apparence de votre book public.</p>
                    <form id="form-theme" action="edit_profil.php?tab=theme" method="POST">
                        <input type="hidden" name="update_theme" value="1">
                        <?php $cur_theme = $user['profile_theme'] ?? 'classique'; ?>
                        <div class="grid grid-cols-3 gap-4">
                            <label class="cursor-pointer">
                                <input type="radio" name="theme" value="classique" class="sr-only" <?= $cur_theme === 'classique' ? 'checked' : '' ?>>
                                <div class="theme-card rounded-xl overflow-hidden border-2 transition-all <?= $cur_theme === 'classique' ? 'border-[#d4a5d4]' : 'border-[#333] hover:border-[#555]' ?>">
                                    <div class="bg-[#0a0a0a] p-3 h-[110px] flex gap-2">
                                        <div class="w-[35%] flex flex-col gap-1.5">
                                            <div class="h-2 bg-[#333] rounded w-3/4"></div>
                                            <div class="h-1.5 bg-[#222] rounded w-1/2"></div>
                                            <div class="h-1.5 bg-[#222] rounded w-2/3"></div>
                                            <div class="mt-2 flex flex-wrap gap-1"><div class="h-1.5 bg-[#2a2a2a] rounded-full w-6"></div><div class="h-1.5 bg-[#2a2a2a] rounded-full w-4"></div></div>
                                        </div>
                                        <div class="flex-grow grid grid-cols-3 gap-1 content-start">
                                            <?php foreach ([28,20,32,24,18,30] as $h): ?><div class="bg-[#1e1e1e] rounded" style="height:<?= $h ?>px"></div><?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="bg-[#111] px-3 py-2 border-t border-[#1e1e1e]"><p class="text-white text-xs font-bold">Classique</p><p class="text-[#555] text-[10px]">Sidebar + masonry</p></div>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="theme" value="editorial" class="sr-only" <?= $cur_theme === 'editorial' ? 'checked' : '' ?>>
                                <div class="theme-card rounded-xl overflow-hidden border-2 transition-all <?= $cur_theme === 'editorial' ? 'border-[#d4a5d4]' : 'border-[#333] hover:border-[#555]' ?>">
                                    <div class="bg-[#0a0a0a] p-3 h-[110px] flex flex-col gap-2">
                                        <div class="bg-[#1e1e1e] rounded-lg h-14 flex items-end p-2"><div class="flex flex-col gap-1"><div class="h-2.5 bg-white rounded w-20"></div><div class="h-1.5 bg-[#555] rounded w-12"></div></div></div>
                                        <div class="grid grid-cols-3 gap-1"><div class="bg-[#1e1e1e] rounded h-7"></div><div class="bg-[#1e1e1e] rounded h-7"></div><div class="bg-[#1e1e1e] rounded h-7"></div></div>
                                    </div>
                                    <div class="bg-[#111] px-3 py-2 border-t border-[#1e1e1e]"><p class="text-white text-xs font-bold">Éditorial</p><p class="text-[#555] text-[10px]">Hero + grille</p></div>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="theme" value="luxe" class="sr-only" <?= $cur_theme === 'luxe' ? 'checked' : '' ?>>
                                <div class="theme-card rounded-xl overflow-hidden border-2 transition-all <?= $cur_theme === 'luxe' ? 'border-[#d4a5d4]' : 'border-[#333] hover:border-[#555]' ?>">
                                    <div class="bg-[#050505] p-3 h-[110px] flex flex-col items-center gap-2 pt-4">
                                        <div class="h-3 bg-white rounded w-24"></div>
                                        <div class="h-px bg-[#333] w-full"></div>
                                        <div class="grid grid-cols-2 gap-1 w-full"><div class="bg-[#1a1a1a] rounded h-9"></div><div class="bg-[#1a1a1a] rounded h-9"></div><div class="bg-[#1a1a1a] rounded h-9"></div><div class="bg-[#1a1a1a] rounded h-9"></div></div>
                                    </div>
                                    <div class="bg-[#111] px-3 py-2 border-t border-[#1e1e1e]"><p class="text-white text-xs font-bold">Luxe</p><p class="text-[#555] text-[10px]">Centré + 2 colonnes</p></div>
                                </div>
                            </label>
                        </div>
                    </form>
                    <script>
                    document.querySelectorAll('input[name="theme"]').forEach(r => {
                        r.addEventListener('change', () => {
                            document.querySelectorAll('.theme-card').forEach(c => { c.style.borderColor='#333'; });
                            r.nextElementSibling.style.borderColor='#d4a5d4';
                        });
                    });
                    </script>
                </div>

                <!-- ── Sécurité ── -->
                <div id="tab-securite" class="tab-panel <?= $active_tab === 'securite' ? 'active' : '' ?>">
                    <h2 class="text-xl font-semibold mb-6">Sécurité</h2>
                    <form id="form-securite" action="edit_profil.php?tab=securite" method="POST" class="flex flex-col gap-4">
                        <input type="hidden" name="update_password" value="1">
                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Mot de passe actuel</label>
                            <input type="password" name="current_password" class="input-field">
                        </div>
                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Nouveau mot de passe</label>
                            <input type="password" name="new_password" minlength="8" class="input-field">
                        </div>
                        <div>
                            <label class="block text-[#888] text-xs font-bold uppercase tracking-widest mb-2">Confirmer le nouveau mot de passe</label>
                            <input type="password" name="confirm_password" minlength="8" class="input-field">
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>

    <!-- Barre sticky globale -->
    <div id="global-bar"
         style="position:fixed; bottom:0; right:0; z-index:100; padding:14px 28px;
                display:flex; align-items:center; gap:12px;">
        <button id="global-cancel"
                style="padding:10px 20px; border-radius:999px; font-size:13px; font-weight:600;
                       border:1px solid #333; color:#aaa; background:transparent; cursor:pointer; transition:all .15s;"
                onmouseover="this.style.borderColor='#555';this.style.color='#fff'"
                onmouseout="this.style.borderColor='#333';this.style.color='#aaa'">
            Annuler
        </button>
        <button id="global-submit"
                style="padding:10px 22px; border-radius:999px; font-size:13px; font-weight:700;
                       background:#d4a5d4; color:#000; border:none; cursor:pointer; transition:opacity .15s;"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            Valider
        </button>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
    function switchTab(key) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.getElementById('tab-' + key).classList.add('active');
        document.getElementById('nav-' + key).classList.add('active');
    }

    function toggleEditTag(btn) {
        const sel = btn.dataset.selected === '1';
        if (sel) {
            btn.dataset.selected = '0';
            btn.style.background = 'transparent';
            btn.style.color = '#888';
            btn.style.borderColor = '#333';
        } else {
            btn.dataset.selected = '1';
            btn.style.background = '#d4a5d4';
            btn.style.color = '#000';
            btn.style.borderColor = '#d4a5d4';
        }
        const selected = [...document.querySelectorAll('.tag-pill[data-selected="1"]')].map(b => b.dataset.tag);
        document.getElementById('edit-tags-input').value = selected.join(',');
    }

    // ── Barre sticky globale ─────────────────────────────────────
    (function() {
        const btnSubmit = document.getElementById('global-submit');
        const btnCancel = document.getElementById('global-cancel');

        btnSubmit.addEventListener('click', () => {
            const activePanel = document.querySelector('.tab-panel.active');
            if (!activePanel) return;
            const form = activePanel.querySelector('form');
            if (form) form.submit();
        });

        btnCancel.addEventListener('click', () => {
            const activePanel = document.querySelector('.tab-panel.active');
            if (!activePanel) return;
            const form = activePanel.querySelector('form');
            if (form) form.reset();
            // Restaurer la prévisualisation avatar si annulée
            const fileInput = document.getElementById('avatar-file-input');
            if (fileInput) {
                <?php if (!empty($user['profile_picture_url'])): ?>
                const img = document.getElementById('avatar-preview-img');
                if (img) img.src = '<?= htmlspecialchars($user['profile_picture_url']) ?>';
                <?php endif; ?>
            }
        });

        // Prévisualisation avatar immédiate à la sélection
        const fileInput = document.getElementById('avatar-file-input');
        if (fileInput) {
            fileInput.addEventListener('change', () => {
                if (!fileInput.files.length) return;
                const reader = new FileReader();
                reader.onload = e => {
                    let img = document.getElementById('avatar-preview-img');
                    const placeholder = document.getElementById('avatar-preview-placeholder');
                    if (!img) {
                        img = document.createElement('img');
                        img.id = 'avatar-preview-img';
                        img.className = 'w-full h-full object-cover';
                        if (placeholder) placeholder.replaceWith(img);
                        else document.getElementById('avatar-preview-wrap').appendChild(img);
                    }
                    img.src = e.target.result;
                };
                reader.readAsDataURL(fileInput.files[0]);
            });
        }
    })();

    // Pills genre
    document.querySelectorAll('.gender-radio').forEach(r => {
        r.addEventListener('change', () => {
            document.querySelectorAll('.gender-pill').forEach(p => {
                p.style.background = 'transparent';
                p.style.color = '#888';
                p.style.borderColor = '#333';
            });
            r.nextElementSibling.style.background = '#d4a5d4';
            r.nextElementSibling.style.color = '#000';
            r.nextElementSibling.style.borderColor = '#d4a5d4';
        });
    });

    // Toggle switch âge
    (function() {
        const cb = document.getElementById('show-age-checkbox');
        const track = document.getElementById('show-age-track');
        const thumb = document.getElementById('show-age-thumb');
        if (!cb) return;
        cb.addEventListener('change', () => {
            track.style.background = cb.checked ? '#d4a5d4' : '#333';
            thumb.style.transform = cb.checked ? 'translateX(20px)' : 'translateX(0)';
        });
    })();

    if (window.self !== window.top) {
        document.querySelectorAll('a[href="profil.php"]').forEach(a => {
            a.addEventListener('click', e => { e.preventDefault(); window.parent.closeEditModal(); });
        });
        <?php if ($message): ?>
        window.parent.closeEditModal();
        <?php endif; ?>
    }
    </script>
</body>
</html>

