<?php
$current_user_id = $_SESSION['user_id'] ?? null;
$user_avatar = $_SESSION['user_avatar'] ?? null;
?>
<script>
  (function() {
    if (localStorage.getItem('theme') === 'light') {
      document.documentElement.classList.add('light');
    }
  })();
</script>
<header id="main-header" class="bg-[#1a1a1a] h-20 flex items-center justify-end px-10 fixed top-0 w-full z-[1000] transition-transform duration-300">
    <div class="nav-center absolute left-1/2 -translate-x-1/2 flex items-center gap-10">
        <div class="nav-links-left hidden lg:flex gap-8">
            <a href="trouver_talent.php" class="text-white text-sm whitespace-nowrap hover:text-[#d4a5d4] transition-colors">Trouver un talent</a>
            <a href="poster_projet.php" class="text-white text-sm whitespace-nowrap hover:text-[#d4a5d4] transition-colors">Poster un projet</a>
        </div>

        <a href="index.php">
            <img src="assets/img/logo.png" class="logo-img h-10 w-auto" style="filter: brightness(0) invert(1) sepia(1) saturate(1000%) hue-rotate(250deg);" alt="ChicBook" />
        </a>

        <div class="nav-links-right hidden lg:flex gap-8">
            <a href="castings.php" class="text-white text-sm whitespace-nowrap hover:text-[#d4a5d4] transition-colors">Accéder aux castings</a>
            <a href="apropos.php" class="text-white text-sm whitespace-nowrap hover:text-[#d4a5d4] transition-colors">À propos</a>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <!-- Bouton thème -->
        <button id="theme-toggle" title="Changer de thème"
            class="w-9 h-9 flex items-center justify-center rounded-full bg-[#333] hover:bg-[#444] transition-colors border-none cursor-pointer text-base leading-none">
            <span id="theme-icon">☀️</span>
        </button>

        <?php if ($current_user_id): ?>
            <a href="profil.php" class="w-11 h-11 bg-[#d4a5d4] rounded-full flex justify-center items-center text-xl border-2 border-transparent hover:border-white hover:scale-105 hover:shadow-[0_0_15px_rgba(212,165,212,0.4)] transition-all overflow-hidden" title="Mon Profil">
                <?php if ($user_avatar): ?>
                    <img src="<?= htmlspecialchars($user_avatar) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <span>👤</span>
                <?php endif; ?>
            </a>
        <?php else: ?>
            <a class="bg-[#d4a5d4] text-[#1a1a1a] px-6 py-2.5 rounded-full text-sm font-medium hover:opacity-90 transition-opacity" href="connexion.php">S'identifier</a>
        <?php endif; ?>

        <div class="navicon lg:hidden flex flex-col gap-[5px] cursor-pointer" id="navicon">
            <span class="w-6 h-0.5 bg-white transition-all duration-300"></span>
            <span class="w-6 h-0.5 bg-white transition-all duration-300"></span>
            <span class="w-6 h-0.5 bg-white transition-all duration-300"></span>
        </div>
    </div>
</header>
