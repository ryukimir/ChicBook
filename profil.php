<?php
session_start();
require_once 'config/Database.php';
require_once 'models/User.php';

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
    <header id="main-header">
        <div class="nav-center">
            <div class="nav-links-left">
                <a href="#">Trouver un talent</a>
                <a href="#">Poster un projet</a>
            </div>
            <a href="index.php"><img src="img/logo.png" class="logo-img" alt="ChicBook" /></a>
            <div class="nav-links-right">
                <a href="#">Créer un casting</a>
                <a href="#">À propos</a>
            </div>
        </div>
        <div class="nav-right">
            <?php if ($is_logged_in): ?>
                <a href="profil.php" class="profile-avatar" title="Mon Profil"><span>👤</span></a>
            <?php else: ?>
                <a class="btn-auth" href="connexion.php">S'identifier</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="profile-container">

        <aside class="profile-sidebar">
            <h2 class="profile-profession"><?= htmlspecialchars($profile_data['profession_name'] ?? 'Talent') ?></h2>
            <p class="profile-location">📍 <?= htmlspecialchars($profile_data['city']) ?>, <?= htmlspecialchars($profile_data['country']) ?></p>

            <div class="profile-bio">
                <?php if (!empty($profile_data['bio'])): ?>
                    <p><?= nl2br(htmlspecialchars($profile_data['bio'])) ?></p>
                <?php else: ?>
                    <?php if ($is_own_profile): ?>
                        <div class="add-bio-prompt">
                            <p>Votre biographie est vide.</p>
                            <button class="btn-small">Ajouter une biographie ✏️</button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="profile-share">
                <a href="#" style="color: #888; text-decoration: none; font-size: 14px;">🔗 Partager le profil</a>
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
                        <button class="btn-follow">Modifier le profil</button>
                        <button class="btn-contact">Ajouter des photos</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="masonry-grid">
                <div class="masonry-item"><img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=500&q=80" alt="Portfolio"></div>
                <div class="masonry-item"><img src="https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=500&q=80" alt="Portfolio"></div>
                <div class="masonry-item"><img src="https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=500&q=80" alt="Portfolio"></div>
                <div class="masonry-item"><img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=500&q=80" alt="Portfolio"></div>
                <div class="masonry-item"><img src="https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?auto=format&fit=crop&w=500&q=80" alt="Portfolio"></div>
                <div class="masonry-item"><img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=500&q=80" alt="Portfolio"></div>
            </div>
        </section>

    </main>

</body>

</html>