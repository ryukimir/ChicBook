<?php
session_start();
$is_logged_in = isset($_SESSION['user_id']);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ChicBook - Plateforme de Talents</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
  <link rel="stylesheet" href="assets/css/custom.css" />
  <style>
    .feed-post img.post-img { display: block; width: 100%; height: auto; }

.feed-post { transition: opacity 0.2s ease; }
    .feed-post.hidden-post { display: none; }

    .tag-badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }
  </style>
</head>
<body class="bg-black text-white font-['Arial',sans-serif]">
  <?php include 'includes/header.php'; ?>

  <div class="flex gap-10 px-8 pt-8 pb-20 max-w-[1400px] mx-auto items-start">

    <!-- Feed -->
    <div class="flex-grow min-w-0 max-w-[680px]">

      <!-- Filtres dropdown -->
      <div class="relative mb-8" id="filter-dropdown-wrapper">
        <button id="filter-toggle" class="flex items-center gap-3 px-5 py-3 bg-[#111] border border-[#2a2a2a] rounded-2xl text-white font-semibold text-sm hover:border-[#555] transition-colors w-full justify-between">
          <span>
            <span class="text-[#666] font-normal mr-2">Fil d'actualité ·</span>
            <span id="filter-label">Tous les talents</span>
          </span>
          <svg id="filter-chevron" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#666] transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div id="filter-menu" class="hidden absolute left-0 right-0 mt-2 bg-[#111] border border-[#2a2a2a] rounded-2xl overflow-hidden z-50 shadow-[0_8px_32px_rgba(0,0,0,0.6)]">
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-white hover:bg-[#1e1e1e] transition-colors active-option" data-filter="all" data-label="Tous les talents">Tous les talents</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="mannequin" data-label="Mannequins">Mannequins</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="photographe" data-label="Photographes">Photographes</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="styliste" data-label="Stylistes">Stylistes</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="videoaste" data-label="Vidéastes">Vidéastes</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="coiffeur" data-label="Coiffeurs">Coiffeurs</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="maquilleur" data-label="Maquilleurs">Maquilleurs</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="modeliste" data-label="Modélistes">Modélistes</button>
        </div>
      </div>

      <!-- Posts -->
      <?php
      $posts = [
        [
          'user'       => 'lea_mannequin',
          'name'       => 'Léa Mercier',
          'profession' => 'Mannequin',
          'location'   => 'Paris',
          'filter'     => 'mannequin',
          'avatar'     => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=80&h=80&fit=crop',
          'image'      => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&w=700&q=80',
          'caption'    => 'Shooting pour la collection printemps de Maison Vidal. Un projet sur la légèreté du vêtement en mouvement — direction artistique signée Inès Fontaine, photographie Noah Pelletier.',
          'tags'       => ['Disponible', 'Mode', 'Éditorial'],
          'time'       => 'il y a 2 h',
        ],
        [
          'user'       => 'noah_ph',
          'name'       => 'Noah Pelletier',
          'profession' => 'Photographe',
          'location'   => 'Lyon',
          'filter'     => 'photographe',
          'avatar'     => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=80&h=80&fit=crop',
          'image'      => 'https://images.unsplash.com/photo-1469334031218-e382a71b716b?auto=format&w=700&q=80',
          'caption'    => 'Backstage du défilé Studio Nomade. Je travaille principalement sur des projets éditoriaux et des défilés — disponible pour des collaborations à partir de juin.',
          'tags'       => ['Backstage', 'Défilé', 'Disponible juin'],
          'time'       => 'il y a 5 h',
        ],
        [
          'user'       => 'ines.styl',
          'name'       => 'Inès Fontaine',
          'profession' => 'Styliste',
          'location'   => 'Paris',
          'filter'     => 'styliste',
          'avatar'     => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?w=80&h=80&fit=crop',
          'image'      => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&w=700&q=80',
          'caption'    => 'Recherche d\'un brodeur pour finaliser une pièce dans une collection capsule axée drapé et transparence. Si vous avez un profil à recommander ou si vous êtes intéressé, envoyez-moi un message.',
          'tags'       => ['Recherche collaboration', 'Broderie', 'Capsule'],
          'time'       => 'il y a 8 h',
        ],
        [
          'user'       => 'camille_coiff',
          'name'       => 'Camille Renaud',
          'profession' => 'Coiffeuse',
          'location'   => 'Bordeaux',
          'filter'     => 'coiffeur',
          'avatar'     => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=80&h=80&fit=crop',
          'image'      => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&w=700&q=80',
          'caption'    => 'Transformation complète pour un shooting éditorial — 4h de travail, une structure volumineuse construite sans extensions. Collaboration avec Sofia Martinez pour le maquillage.',
          'tags'       => ['Éditorial', 'Coiffure haute', 'Transformation'],
          'time'       => 'il y a 1 j',
        ],
        [
          'user'       => 'julien_video',
          'name'       => 'Julien Vasseur',
          'profession' => 'Vidéaste',
          'location'   => 'Paris',
          'filter'     => 'videoaste',
          'avatar'     => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&h=80&fit=crop',
          'image'      => 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?auto=format&w=700&q=80',
          'caption'    => 'Film de marque réalisé pour Collectif Roze — tourné en 16mm pour retrouver ce grain et cette chaleur propres à l\'argentique. Disponible pour de nouveaux projets ce mois-ci.',
          'tags'       => ['Film de marque', '16mm', 'Disponible'],
          'time'       => 'il y a 1 j',
        ],
        [
          'user'       => 'sofia_makeup',
          'name'       => 'Sofia Martinez',
          'profession' => 'Maquilleuse',
          'location'   => 'Paris',
          'filter'     => 'maquilleur',
          'avatar'     => 'https://images.unsplash.com/photo-1488426862026-3ee34a7d66df?w=80&h=80&fit=crop',
          'image'      => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?auto=format&w=700&q=80',
          'caption'    => 'Résultat d\'un travail en lumière naturelle — maquillage éditorial pour un lookbook printemps. Je privilégie les textures peaux naturelles et les teintes travaillées.',
          'tags'       => ['Éditorial', 'Lookbook', 'Naturel'],
          'time'       => 'il y a 2 j',
        ],
        [
          'user'       => 'mia_modeliste',
          'name'       => 'Mia Colbert',
          'profession' => 'Modéliste',
          'location'   => 'Marseille',
          'filter'     => 'modeliste',
          'avatar'     => 'https://images.unsplash.com/photo-1502823403499-6ccfcf4fb453?w=80&h=80&fit=crop',
          'image'      => 'https://images.unsplash.com/photo-1558769132-cb1aea458c5e?auto=format&w=700&q=80',
          'caption'    => 'Prototype final d\'une veste structurée en laine bouillie. Patronage construit à partir de toiles successives sur mesure. Recherche une styliste pour la collection complète.',
          'tags'       => ['Modélisme', 'Prototype', 'Recherche styliste'],
          'time'       => 'il y a 2 j',
        ],
      ];

      foreach ($posts as $p):
      ?>
      <article class="feed-post bg-[#111] rounded-2xl mb-5 overflow-hidden border border-[#1e1e1e]" data-filter="<?= $p['filter'] ?>">

        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4">
          <div class="flex items-center gap-3">
            <img src="<?= $p['avatar'] ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0" alt="<?= $p['name'] ?>">
            <div>
              <div class="font-bold text-[15px] text-white leading-tight"><?= $p['name'] ?></div>
              <div class="text-[#666] text-xs mt-0.5"><?= $p['profession'] ?> · <?= $p['location'] ?></div>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-[#555] text-xs"><?= $p['time'] ?></span>
            <a href="profil.php" class="text-[#d4a5d4] text-xs font-semibold border border-[#d4a5d4]/40 rounded-full px-3 py-1 hover:bg-[#d4a5d4]/10 transition-colors">Voir le profil</a>
          </div>
        </div>

        <!-- Image format original -->
        <img src="<?= $p['image'] ?>" alt="Publication de <?= $p['name'] ?>" class="post-img">

        <!-- Contenu -->
        <div class="px-5 pt-4 pb-5">
          <!-- Tags -->
          <div class="flex flex-wrap gap-1.5 mb-3">
            <?php foreach ($p['tags'] as $tag): ?>
              <span class="tag-badge bg-[#1e1e1e] text-[#aaa] border border-[#2a2a2a]"><?= $tag ?></span>
            <?php endforeach; ?>
          </div>

          <!-- Caption -->
          <p class="text-[#ccc] text-[14px] leading-relaxed"><?= $p['caption'] ?></p>

          <!-- Action -->
          <div class="mt-4 pt-4 border-t border-[#1e1e1e] flex gap-3">
            <a href="profil.php" class="flex-1 text-center py-2.5 rounded-xl bg-[#1a1a1a] text-[#ccc] text-sm font-semibold hover:bg-[#222] transition-colors">Voir le book</a>
            <button class="flex-1 py-2.5 rounded-xl bg-[#d4a5d4]/10 text-[#d4a5d4] text-sm font-semibold border border-[#d4a5d4]/20 hover:bg-[#d4a5d4]/20 transition-colors">Contacter</button>
          </div>
        </div>

      </article>
      <?php endforeach; ?>

    </div>

    <!-- Colonne droite -->
    <aside class="hidden lg:flex flex-col gap-6 w-[300px] flex-shrink-0">

      <!-- Bloc 1 : S'inscrire (en haut) -->
      <div class="rounded-3xl p-9 flex flex-col items-center text-center gap-5" style="background: linear-gradient(145deg,#1e1e1e,#111); box-shadow: 0 1px 0 rgba(255,255,255,0.04) inset, 0 -2px 0 rgba(0,0,0,0.8), 0 12px 32px rgba(0,0,0,0.6);">
        <span class="text-white text-xl font-black uppercase tracking-[0.2em]">Rejoindre ChicBook</span>
        <p class="text-white text-base font-bold leading-snug">développer<br>votre image en ligne&nbsp;!</p>
        <p class="text-[#777] text-[13px] leading-loose"></p>
        <a href="inscription.php" class="mt-1 inline-block bg-[#d4a5d4] text-black px-7 py-2.5 rounded-full font-bold text-sm hover:opacity-90 transition-opacity">S'inscrire</a>
      </div>

      <!-- Bloc 2 : La mode en mouvement -->
      <div class="rounded-3xl p-9 flex flex-col items-center text-center gap-5" style="background: linear-gradient(145deg,#1e1e1e,#111); box-shadow: 0 1px 0 rgba(255,255,255,0.04) inset, 0 -2px 0 rgba(0,0,0,0.8), 0 12px 32px rgba(0,0,0,0.6);">
        <span class="text-white text-xl font-black uppercase tracking-[0.2em]">La mode en mouvement</span>
        <h3 class="text-white text-base font-bold leading-tight">Suivez toute<br>l'actualité de la mode</h3>
        <p class="text-[#777] text-[13px] leading-loose">et de ceux qui la font.<br>Créatifs, marques, agences…<br>ne manquez rien de la<br>communauté ChicBook.</p>
      </div>

      <!-- Bloc 3 : Poster un projet (en bas) -->
      <div class="rounded-3xl p-9 flex flex-col items-center text-center gap-5" style="background: linear-gradient(145deg,#1e1e1e,#111); box-shadow: 0 1px 0 rgba(255,255,255,0.04) inset, 0 -2px 0 rgba(0,0,0,0.8), 0 12px 32px rgba(0,0,0,0.6);">
        <span class="text-white text-xl font-black uppercase tracking-[0.2em]">ChicBook</span>
        <h3 class="text-white text-base font-bold leading-tight">votre réseau<br>social mode</h3>
        <p class="text-[#777] text-[13px] leading-loose">Pour créer un produit<br>et trouver les<br>talents nécessaires.</p>
        <a href="poster_projet.php" class="mt-1 inline-block bg-[#d4a5d4] text-black px-7 py-2.5 rounded-full font-bold text-sm hover:opacity-90 transition-opacity">Poster un projet !</a>
      </div>

    </aside>

  </div>

  <!-- Pop-up première visite -->
  <div id="welcome-modal" class="hidden fixed inset-0 z-[3000] flex items-center justify-center bg-black/75 backdrop-blur-sm">
    <div class="bg-[#111] border border-[#1e1e1e] rounded-2xl p-10 max-w-[420px] w-full mx-4 relative animate-[fadeInUp_0.3s_ease]">
      <button onclick="closeWelcome()" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-[#1e1e1e] text-[#666] hover:text-white hover:bg-[#2a2a2a] transition-colors text-lg leading-none border-none cursor-pointer">✕</button>

      <div class="w-8 h-0.5 bg-[#d4a5d4] mb-6"></div>

      <h2 class="text-white text-2xl font-bold leading-snug mb-4">
        Le réseau professionnel<br>de la mode
      </h2>

      <p class="text-[#d4a5d4] text-sm font-semibold mb-4 leading-relaxed">
        Tous les talents réunis au même endroit
      </p>

      <p class="text-[#888] text-sm leading-relaxed mb-8">
        ChicBook centralise les professionnels de la mode&nbsp;: image, production, création, design, marques et projets.<br><br>
        Trouvez les bons profils et collaborez plus rapidement.
      </p>

      <a href="inscription.php" class="block w-full text-center bg-[#d4a5d4] text-black py-3 rounded-xl font-bold text-sm hover:opacity-90 transition-opacity">S'inscrire</a>
    </div>
  </div>

  <script>
    const toggle = document.getElementById('filter-toggle');
    const menu = document.getElementById('filter-menu');
    const chevron = document.getElementById('filter-chevron');
    const label = document.getElementById('filter-label');
    const posts = document.querySelectorAll('.feed-post');

    toggle.addEventListener('click', () => {
      menu.classList.toggle('hidden');
      chevron.style.transform = menu.classList.contains('hidden') ? '' : 'rotate(180deg)';
    });

    document.addEventListener('click', (e) => {
      if (!document.getElementById('filter-dropdown-wrapper').contains(e.target)) {
        menu.classList.add('hidden');
        chevron.style.transform = '';
      }
    });

    document.querySelectorAll('.filter-option').forEach(opt => {
      opt.addEventListener('click', () => {
        const filter = opt.dataset.filter;
        label.textContent = opt.dataset.label;
        menu.classList.add('hidden');
        chevron.style.transform = '';

        document.querySelectorAll('.filter-option').forEach(o => {
          o.classList.remove('active-option', 'text-white');
          o.classList.add('text-[#aaa]');
        });
        opt.classList.add('active-option', 'text-white');
        opt.classList.remove('text-[#aaa]');

        posts.forEach(post => {
          if (filter === 'all' || post.dataset.filter === filter) {
            post.classList.remove('hidden-post');
          } else {
            post.classList.add('hidden-post');
          }
        });
      });
    });

    // Pop-up première visite
    <?php if (!$is_logged_in): ?>
    if (!localStorage.getItem('chicbook_visited')) {
      document.getElementById('welcome-modal').classList.remove('hidden');
    }
    <?php endif; ?>

    function closeWelcome() {
      localStorage.setItem('chicbook_visited', '1');
      document.getElementById('welcome-modal').classList.add('hidden');
    }

    document.getElementById('welcome-modal').addEventListener('click', function(e) {
      if (e.target === this) closeWelcome();
    });
  </script>

  <script src="assets/js/script.js"></script>
</body>
</html>
