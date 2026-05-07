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

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_general'])) {
        if ($userModel->updateGeneralInfo($_SESSION['user_id'], $_POST['full_name'], $_POST['city'], $_POST['country'])) {
            $message = "Informations générales mises à jour !";
        } else { $error = "Erreur lors de la mise à jour."; }
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
        $tags = $_POST['tags'] ?? [];
        $custom_tag = trim($_POST['custom_tag'] ?? '');
        if (!empty($custom_tag)) $tags[] = $custom_tag;
        $tags_string = implode(', ', $tags);
        if ($userModel->updateExpertise($_SESSION['user_id'], $profession, $tags_string)) {
            $message = "Expertise et mots-clés mis à jour !";
        } else { $error = "Erreur lors de la mise à jour de l'expertise."; }
        $user = $userModel->getUserProfile($_SESSION['user_id']);
    }
    if (isset($_POST['update_password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            if ($userModel->updatePassword($_SESSION['user_id'], $_POST['current_password'], $_POST['new_password'])) {
                $message = "Mot de passe modifié avec succès !";
            } else { $error = "L'ancien mot de passe est incorrect."; }
        } else { $error = "Les nouveaux mots de passe ne correspondent pas."; }
    }
    if (isset($_POST['update_bio'])) {
        $userModel->updateInfo($_SESSION['user_id'], $_POST['bio']);
        $message = "Biographie mise à jour !";
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

$user = $userModel->getUserProfile($_SESSION['user_id']);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paramètres du Profil - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: { extend: { colors: { brand: '#d4a5d4', dark: '#1a1a1a' } } }
      }
    </script>
    <link rel="stylesheet" href="assets/css/custom.css" />
    <style>
      .input-field { width:100%; padding:12px; border-radius:6px; border:1px solid #444; background:#111; color:white; font-family:inherit; font-size:14px; }
      .input-field:disabled { background:#222; color:#666; cursor:not-allowed; }
      textarea.input-field { height:120px; resize:vertical; }
      label { display:block; margin-bottom:8px; color:#ccc; font-size:14px; }
    </style>
</head>
<body class="bg-white font-['Arial',sans-serif]">
    <?php include 'includes/header.php'; ?>

    <main class="max-w-[800px] mx-auto mt-[100px] mb-10 bg-[#1a1a1a] text-white rounded-xl flex overflow-hidden">

        <!-- Menu latéral -->
        <aside class="w-[250px] bg-[#222] p-8 flex-shrink-0">
            <h3 class="text-white mb-5 pl-2.5 text-base font-semibold">Paramètres</h3>
            <?php foreach (['#section-infos' => 'Informations générales', '#section-expertise' => 'Expertise & Mots-clés', '#section-bio' => 'Biographie', '#section-portfolio' => 'Photos du Book', '#section-securite' => 'Sécurité'] as $href => $label): ?>
                <a href="<?= $href ?>" class="block text-[#aaa] no-underline px-3 py-3 mb-1 rounded-md hover:bg-[#333] hover:text-white transition-all"><?= $label ?></a>
            <?php endforeach; ?>

            <div class="mt-8 border-t border-[#333] pt-5 flex flex-col gap-1">
                <a href="profil.php" class="block text-[#d4a5d4] px-3 py-2 rounded hover:bg-[#333] transition-colors">Retour au profil</a>
                <a href="logout.php" class="block text-[#e57373] px-3 py-2 rounded hover:bg-[#333] transition-colors">Se déconnecter</a>
            </div>
        </aside>

        <!-- Contenu -->
        <section class="flex-grow p-10">
            <?php if ($message): ?><div class="bg-[rgba(46,125,50,0.2)] text-[#81c784] border border-[#2e7d32] p-4 rounded-lg mb-5"><?= $message ?></div><?php endif; ?>
            <?php if ($error): ?><div class="bg-[rgba(198,40,40,0.2)] text-[#e57373] border border-[#c62828] p-4 rounded-lg mb-5"><?= $error ?></div><?php endif; ?>

            <!-- Photo de profil -->
            <div id="section-avatar" class="mb-12 pb-8 border-b border-[#333]">
                <h2 class="text-xl font-semibold mb-5">Photo de profil</h2>
                <div class="flex items-center gap-8 mt-5">
                    <div class="w-[100px] h-[100px] bg-[#d4a5d4] rounded-full flex justify-center items-center text-4xl border-2 border-transparent overflow-hidden flex-shrink-0">
                        <?php if (!empty($user['profile_picture_url'])): ?>
                            <img src="<?= htmlspecialchars($user['profile_picture_url']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span>👤</span>
                        <?php endif; ?>
                    </div>
                    <form action="edit_profil.php#section-avatar" method="POST" enctype="multipart/form-data" class="flex-grow">
                        <div class="mb-5"><label>Choisir une nouvelle photo</label><input type="file" name="profile_pic" accept="image/*" required class="input-field"></div>
                        <button type="submit" class="bg-[#d4a5d4] text-[#1a1a1a] px-6 py-2.5 rounded-full text-sm font-medium cursor-pointer border-none hover:opacity-90 transition-opacity">Changer la photo</button>
                    </form>
                </div>
            </div>

            <!-- Informations générales -->
            <div id="section-infos" class="mb-12 pb-8 border-b border-[#333]">
                <h2 class="text-xl font-semibold mb-5">Informations générales</h2>
                <form action="edit_profil.php#section-infos" method="POST">
                    <div class="mb-5"><label>Email (Non modifiable)</label><input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled class="input-field"></div>
                    <div class="mb-5"><label>Nom complet</label><input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required class="input-field"></div>
                    <div class="flex gap-4 mb-5">
                        <div class="flex-1"><label>Ville</label><input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" required class="input-field"></div>
                        <div class="flex-1"><label>Pays</label><input type="text" name="country" value="<?= htmlspecialchars($user['country'] ?? '') ?>" required class="input-field"></div>
                    </div>
                    <button type="submit" name="update_general" class="bg-[#d4a5d4] text-[#1a1a1a] px-6 py-2.5 rounded-full text-sm font-medium cursor-pointer border-none hover:opacity-90 transition-opacity">Mettre à jour</button>
                </form>
            </div>

            <!-- Expertise -->
            <div id="section-expertise" class="mb-12 pb-8 border-b border-[#333]">
                <h2 class="text-xl font-semibold mb-5">Expertise & Mots-clés</h2>
                <form action="edit_profil.php#section-expertise" method="POST">
                    <div class="flex gap-5 bg-[#222] p-6 rounded-lg border border-[#333]">
                        <div class="w-[35%] border-r border-[#444] pr-5">
                            <h4 class="text-[#d4a5d4] mb-4 text-sm font-semibold">Image & Production</h4>
                            <div class="flex flex-col gap-2.5 text-sm text-[#ccc]">
                                <label><input type="radio" name="profession" value="Photographe" onclick="showTags('tags-photo')" <?= ($user['specific_profession'] == 'Photographe') ? 'checked' : '' ?> class="accent-[#d4a5d4]"> Photographe</label>
                                <label><input type="radio" name="profession" value="Vidéaste" onclick="showTags('tags-video')" <?= ($user['specific_profession'] == 'Vidéaste') ? 'checked' : '' ?> class="accent-[#d4a5d4]"> Vidéaste</label>
                                <label><input type="radio" name="profession" value="Mannequin" onclick="showTags('tags-mannequin')" <?= ($user['specific_profession'] == 'Mannequin') ? 'checked' : '' ?> class="accent-[#d4a5d4]"> Mannequin</label>
                            </div>
                        </div>
                        <div class="w-[65%]">
                            <h4 class="text-sm font-semibold mb-4">Mots-clés <span class="float-right text-xs text-[#888] font-normal">Choix multiple</span></h4>
                            <?php
                            $saved_tags = explode(', ', $user['expertise_tags'] ?? '');
                            $isChecked = function($tag) use ($saved_tags) { return in_array($tag, $saved_tags) ? 'checked' : ''; };
                            ?>
                            <div id="tags-photo" class="tags-grid hidden grid-cols-2 gap-2.5 text-sm text-[#ccc]" style="display:none;">
                                <?php foreach (['mode', 'shooting éditorial', 'campagne', 'e-commerce', 'studio'] as $t): ?>
                                    <label><input type="checkbox" name="tags[]" value="<?= $t ?>" <?= $isChecked($t) ?> class="accent-[#d4a5d4]"> <?= $t ?></label>
                                <?php endforeach; ?>
                            </div>
                            <div id="tags-video" class="tags-grid hidden grid-cols-2 gap-2.5 text-sm text-[#ccc]" style="display:none;">
                                <?php foreach (['fashion film', 'vidéo de campagne', 'storytelling', 'montage vidéo'] as $t): ?>
                                    <label><input type="checkbox" name="tags[]" value="<?= $t ?>" <?= $isChecked($t) ?> class="accent-[#d4a5d4]"> <?= $t ?></label>
                                <?php endforeach; ?>
                            </div>
                            <div id="tags-mannequin" class="tags-grid hidden grid-cols-2 gap-2.5 text-sm text-[#ccc]" style="display:none;">
                                <?php foreach (['éditorial', 'runway', 'commercial'] as $t): ?>
                                    <label><input type="checkbox" name="tags[]" value="<?= $t ?>" <?= $isChecked($t) ?> class="accent-[#d4a5d4]"> <?= $t ?></label>
                                <?php endforeach; ?>
                            </div>
                            <hr class="border-[#444] my-5">
                            <label class="text-xs text-[#aaa] block mb-1">Autre mot-clé personnalisé</label>
                            <input type="text" name="custom_tag" placeholder="Ajouter un mot-clé..." class="w-full bg-transparent border border-[#d4a5d4] rounded-md p-2.5 text-white text-sm outline-none">
                        </div>
                    </div>
                    <button type="submit" name="update_expertise" class="mt-5 bg-[#d4a5d4] text-[#1a1a1a] px-6 py-2.5 rounded-full text-sm font-medium cursor-pointer border-none hover:opacity-90 transition-opacity">Sauvegarder l'expertise</button>
                </form>
            </div>

            <!-- Bio -->
            <div id="section-bio" class="mb-12 pb-8 border-b border-[#333]">
                <h2 class="text-xl font-semibold mb-5">Ma Biographie</h2>
                <form action="edit_profil.php#section-bio" method="POST">
                    <div class="mb-5"><textarea name="bio" placeholder="Racontez votre parcours..." class="input-field"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea></div>
                    <button type="submit" name="update_bio" class="bg-[#d4a5d4] text-[#1a1a1a] px-6 py-2.5 rounded-full text-sm font-medium cursor-pointer border-none hover:opacity-90 transition-opacity">Enregistrer la bio</button>
                </form>
            </div>

            <!-- Portfolio -->
            <div id="section-portfolio" class="mb-12 pb-8 border-b border-[#333]">
                <h2 class="text-xl font-semibold mb-5">Ajouter au Book</h2>
                <form action="edit_profil.php#section-portfolio" method="POST" enctype="multipart/form-data">
                    <div class="mb-5"><label>Sélectionnez une image de haute qualité</label><input type="file" name="portfolio_image" accept="image/*" required class="input-field"></div>
                    <button type="submit" class="bg-[#d4a5d4] text-[#1a1a1a] px-6 py-2.5 rounded-full text-sm font-medium cursor-pointer border-none hover:opacity-90 transition-opacity">Uploader l'image</button>
                </form>
            </div>

            <!-- Sécurité -->
            <div id="section-securite">
                <h2 class="text-xl font-semibold mb-5">Sécurité</h2>
                <form action="edit_profil.php#section-securite" method="POST">
                    <div class="mb-5"><label>Mot de passe actuel</label><input type="password" name="current_password" required class="input-field"></div>
                    <div class="mb-5"><label>Nouveau mot de passe</label><input type="password" name="new_password" required minlength="8" class="input-field"></div>
                    <div class="mb-5"><label>Confirmer le nouveau mot de passe</label><input type="password" name="confirm_password" required minlength="8" class="input-field"></div>
                    <button type="submit" name="update_password" class="bg-[#d4a5d4] text-[#1a1a1a] px-6 py-2.5 rounded-full text-sm font-medium cursor-pointer border-none hover:opacity-90 transition-opacity">Changer le mot de passe</button>
                </form>
            </div>

        </section>
    </main>

    <script src="assets/js/script.js"></script>
</body>
</html>
