<?php
session_start();
require_once 'config/Database.php';
require_once 'models/User.php';
require_once 'models/Portfolio.php';

$is_logged_in = isset($_SESSION['user_id']);

$db = Database::getInstance()->getConnection();
$userModel = new User($db);

$profile_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);

if (!$profile_id) {

    header("Location: index.php");
    exit();
}

$profile_data = $userModel->getUserProfile($profile_id);

if (!$profile_data) {
    die("Ce profil n'existe pas.");
}

$is_own_profile = ($is_logged_in && $_SESSION['user_id'] == $profile_id);
?>

<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($profile_data['full_name']) ?> - ChicBook</title>
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <main class="profile-container">

        <aside class="profile-sidebar">
            <h2 class="profile-profession"><?= htmlspecialchars($profile_data['profession_name'] ?? 'Talent') ?></h2>
            <p class="profile-location"> <?= htmlspecialchars($profile_data['city']) ?>, <?= htmlspecialchars($profile_data['country']) ?></p>

            <div class="profile-bio">
                <?php if (!empty($profile_data['bio'])): ?>
                    <p><?= nl2br(htmlspecialchars($profile_data['bio'])) ?></p>
                <?php else: ?>
                    <?php if ($is_own_profile): ?>
                        <div class="add-bio-prompt">
                            <p>Votre biographie est vide.</p>
                            <a href="edit_profil.php#section-bio" class="btn-small" style="text-decoration: none; display: inline-block;">Ajouter une biographie</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="profile-share">
                <a href="#" style="color: #888; text-decoration: none; font-size: 14px;"> Partager le profil</a>
            </div>
        </aside>

        <section class="profile-main">
            <div class="profile-top-bar">
                <h1 class="profile-name"><?= htmlspecialchars($profile_data['full_name']) ?></h1>

                <div class="profile-actions">
                    <?php if (!$is_own_profile): ?>
                        <button class="btn-follow">Suivre</button>
                        <button class="btn-contact">Contacter</button>
                    <?php else: ?>
                        <a href="edit_profil.php#section-infos" class="btn-follow" style="text-decoration: none;">Modifier le profil</a>
                        <a href="edit_profil.php#section-portfolio" class="btn-contact" style="text-decoration: none;">Ajouter des photos</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="masonry-grid">
                <?php
                $portfolioModel = new Portfolio($db);
                $photos = $portfolioModel->getPhotos($profile_id);

                if (empty($photos)): ?>
                    <p style="color: #888;">Aucune photo dans le book pour le moment.</p>
                    <?php else:
                    foreach ($photos as $photo): ?>
                        <div class="masonry-item">
                            <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="Photo de portfolio">
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </section>

    </main>

</body>

</html>