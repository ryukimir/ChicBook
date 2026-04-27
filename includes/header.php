<?php
$current_user_id = $_SESSION['user_id'] ?? null;
$user_avatar = $_SESSION['user_avatar'] ?? null;
?>
<header id="main-header">
    <div class="nav-center">
        <div class="nav-links-left">
            <a href="#">Trouver un talent</a>
            <a href="#">Poster un projet</a>
        </div>

        <a href="index.php">
            <img src="img/logo.png" class="logo-img" alt="ChicBook" />
        </a>

        <div class="nav-links-right">
            <a href="#">Créer un casting</a>
            <a href="apropos.php">À propos</a>
        </div>
    </div>

    <div class="nav-right">
        <?php if ($current_user_id): ?>
            <a href="profil.php" class="profile-avatar" title="Mon Profil">
                <?php if ($user_avatar): ?>
                    <img src="<?= htmlspecialchars($user_avatar) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span>👤</span>
                <?php endif; ?>
            </a>
        <?php else: ?>
            <a class="btn-auth" href="connexion.php">S'identifier</a>
        <?php endif; ?>

        <div class="navicon" id="navicon">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</header>