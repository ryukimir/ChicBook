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
$is_admin_user = false;
if ($current_user_id && isset($db)) {
    $adm = $db->prepare("SELECT is_admin FROM users WHERE id=:id LIMIT 1");
    $adm->execute(['id' => $current_user_id]);
    $is_admin_user = (bool)$adm->fetchColumn();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
/* Mobile nav — injecté via header.php pour éviter les problèmes de cache CSS */
#mobile-nav, #mobile-topbar { display: none; }
@media (max-width: 768px) {
  #sidebar { display: none !important; }
  body { padding-left: 0 !important; padding-bottom: 90px; }
  #scroll-top-btn { bottom: 90px !important; }

  #mobile-nav {
    display: flex !important;
    position: fixed !important;
    bottom: 16px; left: 16px; right: 16px;
    height: 64px; z-index: 1000;
    border-radius: 22px; overflow: hidden;
    background: rgba(10,10,10,0.70);
    backdrop-filter: blur(28px) saturate(200%);
    -webkit-backdrop-filter: blur(28px) saturate(200%);
    border: 1px solid rgba(255,255,255,0.09);
    box-shadow: 0 8px 32px rgba(0,0,0,0.6), 0 2px 8px rgba(0,0,0,0.25),
                inset 0 1px 0 rgba(255,255,255,0.07), inset 0 -1px 0 rgba(0,0,0,0.2);
  }
  .mnav-item {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 3px; color: rgba(255,255,255,0.45);
    text-decoration: none; font-size: 9px; font-weight: 500;
    transition: color .15s, background .15s; padding: 6px 2px;
  }
  .mnav-item svg { width: 22px; height: 22px; stroke: currentColor; flex-shrink: 0; }
  .mnav-item:hover { color: rgba(255,255,255,0.8); background: rgba(255,255,255,0.06); }
  .mnav-item.mnav-active { color: #d4a5d4; }
  .mnav-item.mnav-active svg { stroke: #d4a5d4; stroke-width: 2.8; }

  #mobile-topbar {
    display: none !important;
  }
  .mtop-btn, .mtop-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; color: rgba(255,255,255,0.65);
    background: rgba(10,10,10,0.72);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255,255,255,0.10);
    box-shadow: 0 4px 16px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.08);
    transition: color .15s, background .15s; overflow: hidden;
  }
  .mtop-avatar img { width: 38px; height: 38px; object-fit: cover; border-radius: 50%; display: block; }
  .mtop-btn.mtop-active { color: #d4a5d4; border-color: rgba(212,165,212,0.35); }
  .mtop-avatar.mtop-active { border-color: #d4a5d4; box-shadow: 0 0 0 2px #d4a5d4, 0 4px 16px rgba(0,0,0,0.5); }

  html.light #mobile-nav { background: rgba(245,240,235,0.80); border-color: rgba(0,0,0,0.07); box-shadow: 0 8px 32px rgba(0,0,0,0.10), inset 0 1px 0 rgba(255,255,255,0.7); }
  html.light .mnav-item { color: rgba(40,30,20,0.45); }
  html.light .mnav-item.mnav-active { color: #a060a0; }
  html.light .mnav-item.mnav-active svg { stroke: #a060a0; }
  html.light .mtop-btn, html.light .mtop-avatar { color: rgba(40,30,20,0.6); background: rgba(245,240,235,0.82); border-color: rgba(0,0,0,0.08); }
  html.light .mtop-btn.mtop-active { color: #a060a0; }
  html.light .mtop-avatar.mtop-active { border-color: #a060a0; box-shadow: 0 0 0 2px #a060a0; }
}
</style>

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

    <?php if ($is_admin_user): ?>
    <a href="admin/" class="sidebar-item" style="display:none;" id="sidebar-admin-link">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
        <span class="sidebar-label">Back Office</span>
    </a>
    <script>
    if (window.innerWidth >= 1024) document.getElementById('sidebar-admin-link').style.display = '';
    </script>
    <?php endif; ?>

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
        <a href="profil.php" class="sidebar-item sidebar-mobile-hide <?= $current_page === 'profil.php' ? 'active' : '' ?>">
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
        <a href="connexion.php" class="sidebar-item sidebar-item-accent sidebar-mobile-hide">
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
            <span class="sidebar-label">S'identifier</span>
        </a>
    <?php endif; ?>

</nav>

<!-- Nav mobile (bottom bar) -->
<nav id="mobile-nav">
    <a href="index.php" class="mnav-item <?= $current_page === 'index.php' ? 'mnav-active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        <span>Accueil</span>
    </a>
    <a href="trouver_talent.php" class="mnav-item <?= $current_page === 'trouver_talent.php' ? 'mnav-active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span>Talents</span>
    </a>
    <a href="castings.php" class="mnav-item <?= $current_page === 'castings.php' ? 'mnav-active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M7 4v16M17 4v16M3 8h4m10 0h4M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
        </svg>
        <span>Castings</span>
    </a>
    <a href="messagerie.php" class="mnav-item <?= $current_page === 'messagerie.php' ? 'mnav-active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
        </svg>
        <span>Messages</span>
    </a>
    <a href="evenements.php" class="mnav-item <?= $current_page === 'evenements.php' ? 'mnav-active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <path d="M16 2v4M8 2v4M3 10h18"/>
        </svg>
        <span>Événements</span>
    </a>
</nav>

<!-- Topbar mobile : Plus + Avatar (haut droite) -->
<div id="mobile-topbar">
    <a href="preferences.php" class="mtop-btn <?= $current_page === 'preferences.php' ? 'mtop-active' : '' ?>" title="Plus">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
        </svg>
    </a>
    <?php if ($current_user_id): ?>
        <a href="profil.php" class="mtop-avatar <?= $current_page === 'profil.php' ? 'mtop-active' : '' ?>" title="Mon profil">
            <?php if ($user_avatar): ?>
                <img src="<?= htmlspecialchars($user_avatar) ?>" alt="Profil">
            <?php else: ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            <?php endif; ?>
        </a>
    <?php else: ?>
        <a href="connexion.php" class="mtop-avatar" title="Se connecter">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
        </a>
    <?php endif; ?>
</div>

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
  // Affiche la nav mobile uniquement sur petit écran
  // La nav mobile est gérée par CSS media query (display: none / flex !important)
  // Le JS ne gère que le cas desktop où le sidebar doit rester visible
  if (window.innerWidth > 768) {
    document.getElementById('sidebar').style.display = 'flex';
  }
})();

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
