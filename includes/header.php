<?php
$current_user_id = $_SESSION['user_id'] ?? null;
$user_avatar = $_SESSION['user_avatar'] ?? null;
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav id="sidebar">

    <a href="index.php" class="sidebar-logo">
        <img src="assets/img/logo.png" alt="ChicBook">
        <span class="sidebar-logo-text">ChicBook</span>
    </a>

    <div class="sidebar-divider"></div>

    <a href="index.php" class="sidebar-item <?= $current_page === 'index.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        <span class="sidebar-label">Accueil</span>
    </a>

    <a href="trouver_talent.php" class="sidebar-item <?= $current_page === 'trouver_talent.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span class="sidebar-label">Trouver un talent</span>
    </a>

    <a href="castings.php" class="sidebar-item <?= $current_page === 'castings.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M7 4v16M17 4v16M3 8h4m10 0h4M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
        </svg>
        <span class="sidebar-label">Castings</span>
    </a>

    <a href="messagerie.php" class="sidebar-item <?= $current_page === 'messagerie.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
        </svg>
        <span class="sidebar-label">Messagerie</span>
    </a>

    <a href="evenements.php" class="sidebar-item <?= $current_page === 'evenements.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <path d="M16 2v4M8 2v4M3 10h18"/>
        </svg>
        <span class="sidebar-label">Événements</span>
    </a>

    <a href="apropos.php" class="sidebar-item <?= $current_page === 'apropos.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 16v-4m0-4h.01"/>
        </svg>
        <span class="sidebar-label">À propos</span>
    </a>

    <div class="sidebar-spacer"></div>
    <div class="sidebar-divider"></div>

    <?php if ($current_user_id): ?>
        <a href="profil.php" class="sidebar-item <?= $current_page === 'profil.php' ? 'active' : '' ?>">
            <?php if ($user_avatar): ?>
                <img src="<?= htmlspecialchars($user_avatar) ?>" class="sidebar-avatar" alt="Profil">
            <?php else: ?>
                <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            <?php endif; ?>
            <span class="sidebar-label">Mon Profil</span>
        </a>
        <a href="logout.php" class="sidebar-item sidebar-item-danger">
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            <span class="sidebar-label">Déconnexion</span>
        </a>
    <?php else: ?>
        <a href="connexion.php" class="sidebar-item sidebar-item-accent">
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
            <span class="sidebar-label">S'identifier</span>
        </a>
    <?php endif; ?>

</nav>
