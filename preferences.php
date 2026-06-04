<?php
session_start();
$is_logged_in = isset($_SESSION['user_id']);
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();
$is_light = (($_COOKIE['chicbook_theme'] ?? 'dark') === 'light');
?>
<!doctype html>
<html lang="fr" class="<?= $is_light ? 'light' : '' ?>">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Préférences — ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css"/>
    <style>
        .theme-switch { position:relative; display:inline-block; width:52px; height:28px; }
        .theme-switch input { opacity:0; width:0; height:0; }
        .theme-slider { position:absolute; inset:0; cursor:pointer; background:#333; border-radius:28px; transition:background .25s; }
        .theme-slider::before { content:''; position:absolute; width:20px; height:20px; left:4px; bottom:4px; background:#fff; border-radius:50%; transition:transform .25s; box-shadow:0 1px 4px rgba(0,0,0,.3); }
        input:checked + .theme-slider { background:#d4a5d4; }
        input:checked + .theme-slider::before { transform:translateX(24px); }
        html.light .theme-slider { background:#d0cac4; }
        html.light input:checked + .theme-slider { background:#d4a5d4; }

        /* About cards */
        .about-card { border-radius:20px; padding:32px; display:flex; flex-direction:column; gap:12px; }
        html:not(.light) .about-card { background:#111; border:1px solid #1a1a1a; }
        html.light .about-card { background:#fff; border:1px solid #e0dbd4; }
        .about-card-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:rgba(212,165,212,0.12); flex-shrink:0; }
        .about-card-icon svg { width:22px; height:22px; stroke:#d4a5d4; }
    </style>
</head>
<body class="bg-black text-white" style="font-family:'Open Sans',sans-serif;">
<?php include 'includes/header.php'; ?>

<div class="max-w-[860px] mx-auto px-6 pt-10 pb-20">

    <h1 class="text-2xl font-bold mb-1">Préférences</h1>
    <p class="text-[#555] text-sm mb-8">Personnalisez votre expérience ChicBook.</p>

    <!-- ══ APPARENCE ══ -->
    <section class="bg-[#111] border border-[#1a1a1a] rounded-2xl mb-10 overflow-hidden">
        <div class="px-6 py-4 border-b border-[#1a1a1a]">
            <h2 class="font-semibold text-xs uppercase tracking-widest text-[#666]">Apparence</h2>
        </div>
        <div class="px-6 py-5 flex items-center justify-between">
            <div>
                <p class="font-semibold text-[15px]">Thème de l'interface</p>
                <p class="text-[#555] text-sm mt-0.5"><?= $is_light ? 'Thème clair activé' : 'Thème sombre activé' ?></p>
            </div>
            <div class="flex items-center gap-3">
                <svg id="icon-dark" class="w-5 h-5" style="color:<?= $is_light ? '#bbb' : '#d4a5d4' ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"/>
                </svg>
                <label class="theme-switch" title="Changer de thème">
                    <input type="checkbox" id="theme-toggle" <?= $is_light ? 'checked' : '' ?>>
                    <span class="theme-slider"></span>
                </label>
                <svg id="icon-light" class="w-5 h-5" style="color:<?= $is_light ? '#d4a5d4' : '#555' ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="5"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                </svg>
            </div>
        </div>
    </section>

    <!-- ══ À PROPOS ══ -->
    <section class="bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-[#1a1a1a]">
            <h2 class="font-semibold text-xs uppercase tracking-widest text-[#666]">À propos de ChicBook</h2>
        </div>

        <!-- Tagline -->
        <div class="px-6 pt-6 pb-2">
            <p class="text-[15px] text-[#ccc] leading-relaxed">
                Un hub 360° dédié à la mode et à l'image — où chaque talent dispose de son propre <span class="text-white font-semibold">book professionnel</span> pour exposer, collaborer et évoluer.
                Conçu pour connecter créatifs, marques et agences au sein d'une seule plateforme.
            </p>
        </div>

        <!-- 3 piliers en grille -->
        <div class="grid grid-cols-1 gap-4 px-6 py-6" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">

            <!-- Marque & Créateur -->
            <div class="about-card">
                <div class="about-card-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-[15px] text-white mb-1">Marque & Créateur</h3>
                    <p class="text-[#666] text-sm leading-relaxed">Vision, identité, intention créative. Marques, créateurs et directeurs artistiques qui imaginent un univers et cherchent les talents pour le réaliser.</p>
                </div>
            </div>

            <!-- Création & Design -->
            <div class="about-card">
                <div class="about-card-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 11l6.293-6.293a1 1 0 011.414 0l1.586 1.586a1 1 0 010 1.414L12 14H9v-3z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-[15px] text-white mb-1">Création & Design</h3>
                    <p class="text-[#666] text-sm leading-relaxed">Pièces, volumes, matières. Stylistes, modélistes, designers textile et accessoires — les métiers qui donnent forme concrète aux collections.</p>
                </div>
            </div>

            <!-- Image & Production -->
            <div class="about-card">
                <div class="about-card-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <circle cx="12" cy="13" r="3"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-[15px] text-white mb-1">Image & Production</h3>
                    <p class="text-[#666] text-sm leading-relaxed">Shootings, campagnes, contenus éditoriaux. Photographes, vidéastes, mannequins, maquilleurs et coiffeurs qui donnent vie à la création.</p>
                </div>
            </div>

        </div>

        <!-- CTA -->
        <div class="px-6 pb-6 flex items-center justify-between flex-wrap gap-3 border-t border-[#1a1a1a] pt-5">
            <p class="text-[#555] text-sm">Vous êtes un professionnel de la mode ?</p>
            <a href="inscription.php" class="inline-flex items-center gap-2 bg-[#d4a5d4] text-black text-sm font-bold px-5 py-2.5 rounded-xl hover:opacity-90 transition-opacity">
                Rejoindre ChicBook
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </section>

</div>

<script>
const toggle = document.getElementById('theme-toggle');

toggle.addEventListener('change', function() {
    const isLight = this.checked;
    const val = isLight ? 'light' : 'dark';
    // Sauvegarder cookie + localStorage
    document.cookie = 'chicbook_theme=' + val + ';path=/;max-age=31536000;SameSite=Lax';
    localStorage.setItem('chicbook_theme', val);
    // Recharger pour que PHP applique la classe sur <html> sans flash
    window.location.reload();
});
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
