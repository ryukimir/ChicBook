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
        } else {
            $error = "Erreur lors de la mise à jour.";
        }
    }

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "uploads/";

        $file_name = "avatar_" . $_SESSION['user_id'] . "_" . time() . "." . pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $userModel->updateProfilePicture($_SESSION['user_id'], $target_file);
            $_SESSION['user_avatar'] = $target_file;
            $message = "Photo de profil mise à jour !";
        } else {
            $error = "Erreur lors du téléchargement de l'avatar.";
        }
    }

    if (isset($_POST['update_expertise'])) {
        $profession = $_POST['profession'] ?? '';
        $tags = $_POST['tags'] ?? [];
        $custom_tag = trim($_POST['custom_tag'] ?? '');

        if (!empty($custom_tag)) {
            $tags[] = $custom_tag;
        }
        $tags_string = implode(', ', $tags);

        if ($userModel->updateExpertise($_SESSION['user_id'], $profession, $tags_string)) {
            $message = "Expertise et mots-clés mis à jour !";
        } else {
            $error = "Erreur lors de la mise à jour de l'expertise.";
        }

        $user = $userModel->getUserProfile($_SESSION['user_id']);
    }

    if (isset($_POST['update_password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            if ($userModel->updatePassword($_SESSION['user_id'], $_POST['current_password'], $_POST['new_password'])) {
                $message = "Mot de passe modifié avec succès !";
            } else {
                $error = "L'ancien mot de passe est incorrect.";
            }
        } else {
            $error = "Les nouveaux mots de passe ne correspondent pas.";
        }
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
        } else {
            $error = "Erreur lors du téléchargement de l'image.";
        }
    }
}

$user = $userModel->getUserProfile($_SESSION['user_id']);
?>
<!doctype html>
<html lang="fr">

<head>
    <title>Paramètres du Profil - ChicBook</title>
    <link rel="stylesheet" href="src/style.css">
    <link rel="stylesheet" href="src/edit_profil.css">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <main class="edit-container">

        <aside class="settings-menu">
            <h3 style="margin-bottom: 20px; padding-left: 10px;">Paramètres</h3>
            <a href="#section-infos">Informations générales</a>
            <a href="#section-expertise">Expertise & Mots-clés</a>
            <a href="#section-bio">Biographie</a>
            <a href="#section-portfolio">Photos du Book</a>
            <a href="#section-securite">Sécurité</a>
            

            <div style="margin-top: 30px; border-top: 1px solid #333; padding-top: 20px;">
                <a href="profil.php" style="color: #d4a5d4;">Retour au profil</a>
                <a href="logout.php" style="color: #e57373; margin-top: 5px;">Se déconnecter</a>
            </div>
        </aside>

        <section class="settings-content">

            <?php if ($message): ?> <div class="alert alert-success"><?= $message ?></div> <?php endif; ?>
            <?php if ($error): ?> <div class="alert alert-error"><?= $error ?></div> <?php endif; ?>
            <div id="section-avatar" class="settings-section">
                <h2>Photo de profil</h2>
                <div style="display: flex; align-items: center; gap: 30px; margin-top: 20px;">
                    <div class="profile-avatar" style="width: 100px; height: 100px; font-size: 40px;">
                        <?php if (!empty($user['profile_picture_url'])): ?>
                            <img src="<?= htmlspecialchars($user['profile_picture_url']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <span>👤</span>
                            <?php opacity:
                            0.5; ?>
                        <?php endif; ?>
                    </div>

                    <form action="edit_profil.php#section-avatar" method="POST" enctype="multipart/form-data" style="flex-grow: 1;">
                        <div class="form-group">
                            <label>Choisir une nouvelle photo</label>
                            <input type="file" name="profile_pic" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn-auth">Changer la photo</button>
                    </form>
                </div>
            </div>
            <div id="section-infos" class="settings-section">
                <h2>Informations générales</h2>
                <form action="edit_profil.php#section-infos" method="POST">
                    <div class="form-group">
                        <label>Email (Non modifiable)</label>
                        <input type="email" value="<?= htmlspecialchars($user['email'] ?? 'Votre email') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Nom complet</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                    </div>
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Ville</label>
                            <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Pays</label>
                            <input type="text" name="country" value="<?= htmlspecialchars($user['country'] ?? '') ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="update_general" class="btn-auth">Mettre à jour</button>
                </form>
            </div>

            <div id="section-expertise" class="settings-section">
                <h2>Expertise & Mots-clés</h2>
                <form action="edit_profil.php#section-expertise" method="POST">
                    
                    <div class="expertise-panel" style="display: flex; gap: 20px; background: #222; padding: 25px; border-radius: 8px; border: 1px solid #333;">
                        
                        <div class="expertise-left" style="width: 35%; border-right: 1px solid #444; padding-right: 20px;">
                            <h4 style="color: #d4a5d4; margin-bottom: 15px;">Image & Production</h4>
                            <div class="radio-group" style="display: flex; flex-direction: column; gap: 10px;">
                                <label><input type="radio" name="profession" value="Photographe" onclick="showTags('tags-photo')" <?= ($user['specific_profession'] == 'Photographe') ? 'checked' : '' ?>> Photographe</label>
                                <label><input type="radio" name="profession" value="Vidéaste" onclick="showTags('tags-video')" <?= ($user['specific_profession'] == 'Vidéaste') ? 'checked' : '' ?>> Vidéaste</label>
                                <label><input type="radio" name="profession" value="Mannequin" onclick="showTags('tags-mannequin')" <?= ($user['specific_profession'] == 'Mannequin') ? 'checked' : '' ?>> Mannequin</label>
                                </div>
                        </div>

                        <div class="expertise-right" style="width: 65%;">
                            <h4 style="margin-bottom: 15px;">Mots-clés <span style="float: right; font-size: 12px; color: #888;">Choix multiple</span></h4>
                            
                            <?php 
                            $saved_tags = explode(', ', $user['expertise_tags'] ?? ''); 
                            $isChecked = function($tag) use ($saved_tags) { return in_array($tag, $saved_tags) ? 'checked' : ''; };
                            ?>

                            <div id="tags-photo" class="tags-grid" style="display: none; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <label><input type="checkbox" name="tags[]" value="mode" <?= $isChecked('mode') ?>> mode</label>
                                <label><input type="checkbox" name="tags[]" value="shooting éditorial" <?= $isChecked('shooting éditorial') ?>> shooting éditorial</label>
                                <label><input type="checkbox" name="tags[]" value="campagne" <?= $isChecked('campagne') ?>> campagne</label>
                                <label><input type="checkbox" name="tags[]" value="e-commerce" <?= $isChecked('e-commerce') ?>> e-commerce</label>
                                <label><input type="checkbox" name="tags[]" value="studio" <?= $isChecked('studio') ?>> studio</label>
                            </div>

                            <div id="tags-video" class="tags-grid" style="display: none; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <label><input type="checkbox" name="tags[]" value="fashion film" <?= $isChecked('fashion film') ?>> fashion film</label>
                                <label><input type="checkbox" name="tags[]" value="vidéo de campagne" <?= $isChecked('vidéo de campagne') ?>> vidéo de campagne</label>
                                <label><input type="checkbox" name="tags[]" value="storytelling" <?= $isChecked('storytelling') ?>> storytelling</label>
                                <label><input type="checkbox" name="tags[]" value="montage vidéo" <?= $isChecked('montage vidéo') ?>> montage vidéo</label>
                            </div>

                            <div id="tags-mannequin" class="tags-grid" style="display: none; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <label><input type="checkbox" name="tags[]" value="éditorial" <?= $isChecked('éditorial') ?>> éditorial</label>
                                <label><input type="checkbox" name="tags[]" value="runway" <?= $isChecked('runway') ?>> runway</label>
                                <label><input type="checkbox" name="tags[]" value="commercial" <?= $isChecked('commercial') ?>> commercial</label>
                            </div>

                            <hr style="border-color: #444; margin: 20px 0;">
                            
                            <label style="font-size: 12px; color: #aaa;">Autre mot-clé personnalisé</label>
                            <input type="text" name="custom_tag" placeholder="Ajouter un mot-clé..." style="width: 100%; background: transparent; border: 1px solid #d4a5d4; border-radius: 5px; padding: 10px; color: white; margin-top: 5px;">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_expertise" class="btn-auth" style="margin-top: 20px;">Sauvegarder l'expertise</button>
                </form>
            </div>

            <div id="section-bio" class="settings-section">
                <h2>Ma Biographie</h2>
                <form action="edit_profil.php#section-bio" method="POST">
                    <div class="form-group">
                        <textarea name="bio" placeholder="Racontez votre parcours..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" name="update_bio" class="btn-auth">Enregistrer la bio</button>
                </form>
            </div>

            <div id="section-portfolio" class="settings-section">
                <h2>Ajouter au Book</h2>
                <form action="edit_profil.php#section-portfolio" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Sélectionnez une image de haute qualité</label>
                        <input type="file" name="portfolio_image" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn-auth">Uploader l'image</button>
                </form>
            </div>

            <div id="section-securite" class="settings-section">
                <h2>Sécurité</h2>
                <form action="edit_profil.php#section-securite" method="POST">
                    <div class="form-group">
                        <label>Mot de passe actuel</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>Nouveau mot de passe</label>
                        <input type="password" name="new_password" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label>Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_password" required minlength="8">
                    </div>
                    <button type="submit" name="update_password" class="btn-auth">Changer le mot de passe</button>
                </form>
            </div>

        </section>
    </main>
</body>

</html>