<?php
$current_user_id = $_SESSION['user_id'] ?? null;
$user_avatar = $_SESSION['user_avatar'] ?? null;

// Auto-login via cookie remember me
if (!$current_user_id && !empty($_COOKIE['chicbook_remember']) && isset($db)) {
    $stmt = $db->prepare("SELECT id, profile_picture_url FROM users WHERE remember_token=:t AND is_suspended=FALSE LIMIT 1");
    $stmt->execute(['t' => $_COOKIE['chicbook_remember']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_avatar'] = $row['profile_picture_url'];
        $current_user_id = $row['id'];
        $user_avatar = $row['profile_picture_url'];
    }
}
// Appliquer le thème côté serveur via cookie (évite le flash)
$_theme_is_light = (($_COOKIE['chicbook_theme'] ?? 'dark') === 'light');
if ($_theme_is_light) {
    echo '<script>document.documentElement.classList.add("light")</script>';
}
if (!$user_avatar && $current_user_id && isset($db)) {
    $fa = $db->prepare("SELECT image_url FROM portfolios WHERE user_id=:id ORDER BY position ASC, created_at DESC LIMIT 1");
    $fa->execute(['id' => $current_user_id]);
    $fa_row = $fa->fetch(PDO::FETCH_ASSOC);
    if ($fa_row) $user_avatar = $fa_row['image_url'];
}
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav id="sidebar">

    <a href="index.php" class="sidebar-logo">
        <img id="sidebar-logo-img" src="assets/img/navicon.png" alt="ChicBook">
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

    <a href="recherche.php" class="sidebar-item <?= $current_page === 'recherche.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/>
        </svg>
        <span class="sidebar-label">Recherche</span>
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

    <div class="sidebar-spacer"></div>
    <div class="sidebar-divider"></div>

    <a href="preferences.php" class="sidebar-item <?= $current_page === 'preferences.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
        </svg>
        <span class="sidebar-label">Plus</span>
    </a>

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
    <?php else: ?>
        <a href="connexion.php" class="sidebar-item sidebar-item-accent">
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
            <span class="sidebar-label">S'identifier</span>
        </a>
    <?php endif; ?>

</nav>

<!-- Bouton scroll-to-top -->
<button id="scroll-top-btn" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Retour en haut" style="
  position:fixed; bottom:28px; right:28px; z-index:9999;
  width:44px; height:44px; border-radius:50%;
  background:#111; border:1px solid #2a2a2a; color:#fff;
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; opacity:0; pointer-events:none;
  transition:opacity 0.25s, transform 0.25s, border-color 0.2s;
  transform:translateY(10px);
  box-shadow:0 4px 16px rgba(0,0,0,0.5);
">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
    <path d="M18 15l-6-6-6 6"/>
  </svg>
</button>
<script>
(function(){
  var logo = document.getElementById('sidebar-logo-img');
  var sidebar = document.getElementById('sidebar');
  sidebar.addEventListener('mouseenter', function(){
    logo.style.opacity = '0';
    setTimeout(function(){
      logo.src = 'assets/img/logo.png';
      logo.style.width = 'auto';
      logo.style.height = '44px';
      logo.style.maxWidth = '220px';
      logo.style.opacity = '1';
    }, 180);
  });
  sidebar.addEventListener('mouseleave', function(){
    logo.style.opacity = '0';
    setTimeout(function(){
      logo.src = 'assets/img/navicon.png';
      logo.style.width = '52px';
      logo.style.height = '52px';
      logo.style.maxWidth = '';
      logo.style.opacity = '1';
    }, 180);
  });
})();

(function(){
  var btn = document.getElementById('scroll-top-btn');
  window.addEventListener('scroll', function(){
    var show = window.scrollY > 300;
    btn.style.opacity = show ? '1' : '0';
    btn.style.pointerEvents = show ? 'auto' : 'none';
    btn.style.transform = show ? 'translateY(0)' : 'translateY(10px)';
  }, {passive:true});
  btn.addEventListener('mouseenter', function(){ btn.style.borderColor='#555'; btn.style.background='#1a1a1a'; });
  btn.addEventListener('mouseleave', function(){ btn.style.borderColor='#2a2a2a'; btn.style.background='#111'; });
})();
</script>
