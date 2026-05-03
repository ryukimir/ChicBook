<?php
session_start();
require_once 'config/Database.php';
require_once 'models/User.php';
require_once 'models/Portfolio.php';

$is_logged_in = isset($_SESSION['user_id']);

$db = Database::getInstance()->getConnection();
$userModel = new User($db);

$profile_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$share_url = $protocol . "://" . $domain . dirname($_SERVER['PHP_SELF']) . "/profil.php?id=" . $profile_id;

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
    <link rel="stylesheet" href="src/style.css" />
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <main class="profile-container">

        <aside class="profile-sidebar">
            <h2 class="profile-profession">
                <?= htmlspecialchars($profile_data['specific_profession'] ?? $profile_data['profession_name'] ?? 'Talent') ?>
            </h2>

            <p class="profile-location"> <?= htmlspecialchars($profile_data['city']) ?>, <?= htmlspecialchars($profile_data['country']) ?></p>

            <?php if (!empty($profile_data['expertise_tags'])): ?>
                <div class="profile-tags" style="margin-bottom: 25px; display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php
                    $tags = explode(',', $profile_data['expertise_tags']);
                    foreach ($tags as $tag):
                        if (trim($tag) != ''):
                    ?>
                            <span style="background: #e6e6e6; color: #1a1a1a; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold;">
                                #<?= htmlspecialchars(trim($tag)) ?>
                            </span>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            <?php endif; ?>

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
                <a href="#" id="btn-share" style="color: #888; text-decoration: none; font-size: 14px; transition: color 0.3s;">
                    Partager le profil
                </a>
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
                        <div class="dropdown-manage">
                            <button class="btn-contact">Gérer mon profil ▼</button>
                            <div class="dropdown-content">
                                <a href="edit_profil.php#section-infos">Modifier le profil</a>
                                <a href="edit_profil.php#section-portfolio">Ajouter des photos</a>
                                <a href="edit_profil.php#section-portfolio-manage" class="delete-link">Supprimer des photos</a>
                            </div>
                        </div>
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
    <script>
        document.getElementById('btn-share').addEventListener('click', async (e) => {
            e.preventDefault();

            const shareData = {
                title: '<?= addslashes(htmlspecialchars($profile_data['full_name'])) ?> - ChicBook',
                text: 'Découvrez le portfolio de <?= addslashes(htmlspecialchars($profile_data['full_name'])) ?> sur ChicBook !',
                url: '<?= $share_url ?>'
            };

            if (navigator.share) {
                try {
                    await navigator.share(shareData);
                    console.log('Profil partagé avec succès');
                } catch (err) {
                    console.log('Partage annulé ou erreur:', err);
                }
            } else {

                navigator.clipboard.writeText(shareData.url).then(() => {

                    const btn = document.getElementById('btn-share');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '✅ Lien copié !';
                    btn.style.color = '#d4a5d4';

                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.style.color = '#888';
                    }, 3000);

                }).catch(err => {
                    alert("Erreur lors de la copie du lien.");
                });
            }
        });
    </script>
</body>

</html>