<?php
session_start();
$is_logged_in = isset($_SESSION['user_id']);
require_once 'config/database.php';
require_once 'config/i18n.php';
$db = Database::getInstance()->getConnection();

// ── AJAX toggle like ──────────────────────────────────────────────────────────
if ($is_logged_in && isset($_POST['action']) && $_POST['action'] === 'toggle_like') {
    header('Content-Type: application/json');
    $photo_id = intval($_POST['photo_id'] ?? 0);
    $me = $_SESSION['user_id'];
    if (!$photo_id) { echo json_encode(['ok' => false]); exit; }
    $check = $db->prepare("SELECT id FROM photo_likes WHERE user_id=:u AND photo_id=:p");
    $check->execute([':u' => $me, ':p' => $photo_id]);
    if ($check->fetch()) {
        $db->prepare("DELETE FROM photo_likes WHERE user_id=:u AND photo_id=:p")->execute([':u' => $me, ':p' => $photo_id]);
        $liked = false;
    } else {
        $db->prepare("INSERT INTO photo_likes (user_id, photo_id) VALUES (:u,:p)")->execute([':u' => $me, ':p' => $photo_id]);
        $liked = true;
    }
    $count = $db->prepare("SELECT COUNT(*) FROM photo_likes WHERE photo_id=:p");
    $count->execute([':p' => $photo_id]);
    echo json_encode(['ok' => true, 'liked' => $liked, 'count' => (int)$count->fetchColumn()]);
    exit;
}

$upcoming_events = $db->query("SELECT id, title, event_date, city, type FROM events WHERE event_date >= CURRENT_DATE ORDER BY event_date ASC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
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
    html.light .fashion-widget { background: linear-gradient(145deg,#ece8e3,#f5f0eb) !important; box-shadow: 0 1px 0 rgba(255,255,255,0.8) inset, 0 2px 12px rgba(0,0,0,0.08) !important; }
    html.light .fashion-widget .text-white { color: #1a1a1a !important; }
    html.light .fashion-widget .text-\[\#777\] { color: #555 !important; }
  </style>
</head>
<body class="bg-black text-white font-['Arial',sans-serif]">
  <?php include 'includes/header.php'; ?>

  <div class="flex gap-10 px-8 pt-8 pb-20 max-w-[1400px] mx-auto items-start">

    <!-- Feed -->
    <div class="flex-grow min-w-0 max-w-[680px]">

      <!-- Filtres dropdown + boutons mobile (Plus / Avatar) -->
      <div class="flex items-center gap-2 mb-8" id="filter-row-wrapper">
        <div class="relative flex-1" id="filter-dropdown-wrapper">
        <button id="filter-toggle" class="flex items-center gap-3 px-5 py-3 bg-[#111] border border-[#2a2a2a] rounded-2xl text-white font-semibold text-sm hover:border-[#555] transition-colors w-full justify-between">
          <span>
            <span class="text-[#666] font-normal mr-2"><?= t('feed.filter_label') ?> ·</span>
            <span id="filter-label"><?= t('feed.filter_all') ?></span>
          </span>
          <svg id="filter-chevron" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#666] transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div id="filter-menu" class="hidden absolute left-0 right-0 mt-2 bg-[#111] border border-[#2a2a2a] rounded-2xl overflow-hidden z-50 shadow-[0_8px_32px_rgba(0,0,0,0.6)]">
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-white hover:bg-[#1e1e1e] transition-colors active-option" data-filter="all" data-label="<?= t('feed.filter_all') ?>"><?= t('feed.filter_all') ?></button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="mannequin" data-label="Mannequins">Mannequins</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="photographe" data-label="Photographes">Photographes</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="styliste" data-label="Stylistes">Stylistes</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="videoaste" data-label="Vidéastes">Vidéastes</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="coiffeur" data-label="Coiffeurs">Coiffeurs</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="maquilleur" data-label="Maquilleurs">Maquilleurs</button>
          <button class="filter-option w-full text-left px-5 py-3.5 text-sm font-semibold text-[#aaa] hover:bg-[#1e1e1e] hover:text-white transition-colors" data-filter="modeliste" data-label="Modélistes">Modélistes</button>
        </div>
        </div><!-- /filter-dropdown-wrapper -->

        <!-- Boutons Plus + Avatar — visibles uniquement sur mobile, dans la continuité du filtre -->
        <div id="feed-topbar-btns" style="display:none; align-items:center; gap:6px; flex-shrink:0;">
          <a href="preferences.php" class="mtop-btn <?= ($current_page ?? '') === 'preferences.php' ? 'mtop-active' : '' ?>" title="Plus">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="3"/>
              <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
            </svg>
          </a>
          <?php if ($is_logged_in): ?>
            <a href="profil.php" class="mtop-avatar" title="Mon profil">
              <?php
                $sid = $_SESSION['user_id'];
                $av = $_SESSION['user_avatar'] ?? null;
                if (!$av) {
                  $fa2 = $db->prepare("SELECT image_url FROM portfolios WHERE user_id=:id ORDER BY position ASC, created_at DESC LIMIT 1");
                  $fa2->execute(['id'=>$sid]); $fa2r = $fa2->fetch(PDO::FETCH_ASSOC);
                  if ($fa2r) $av = $fa2r['image_url'];
                }
              ?>
              <?php if ($av): ?>
                <img src="<?= htmlspecialchars($av) ?>" alt="Profil">
              <?php else: ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <?php endif; ?>
            </a>
          <?php else: ?>
            <a href="connexion.php" class="mtop-avatar" title="Se connecter">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
            </a>
          <?php endif; ?>
        </div>
      </div><!-- /filter-row-wrapper -->

      <!-- Posts -->
      <?php
      // Dernière photo ajoutée par chaque utilisateur, triée par date décroissante
      $feed_stmt = $db->query("
          SELECT DISTINCT ON (po.user_id)
              po.id AS photo_id, po.image_url, po.created_at,
              u.id AS user_id, u.full_name, u.specific_profession,
              u.city, u.expertise_tags, u.profile_picture_url,
              (SELECT image_url FROM portfolios WHERE user_id=u.id ORDER BY position ASC, created_at DESC LIMIT 1) AS fallback_avatar,
              (SELECT COUNT(*) FROM photo_likes WHERE photo_id=po.id) AS likes_count
          FROM portfolios po
          JOIN users u ON u.id = po.user_id
          WHERE po.image_url IS NOT NULL AND po.image_url != ''
          ORDER BY po.user_id, po.created_at DESC
      ");
      $feed_raw = $feed_stmt->fetchAll(PDO::FETCH_ASSOC);
      // Re-trier par date d'ajout décroissante
      usort($feed_raw, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
      // Likes de l'utilisateur connecté
      $user_liked_ids = [];
      if ($is_logged_in) {
          $lk = $db->prepare("SELECT photo_id FROM photo_likes WHERE user_id=:u");
          $lk->execute([':u' => $_SESSION['user_id']]);
          $user_liked_ids = array_flip($lk->fetchAll(PDO::FETCH_COLUMN));
      }

      function timeAgo($datetime) {
          $diff = time() - strtotime($datetime);
          if ($diff < 3600)   return 'il y a ' . max(1, round($diff/60)) . ' min';
          if ($diff < 86400)  return 'il y a ' . round($diff/3600) . ' h';
          if ($diff < 604800) return 'il y a ' . round($diff/86400) . ' j';
          return (new DateTime($datetime))->format('d/m/Y');
      }

      if (empty($feed_raw)): ?>
        <div class="bg-[#111] border border-dashed border-[#2a2a2a] rounded-2xl py-20 text-center">
          <p class="text-[#555] text-lg mb-2">Le fil est vide pour l'instant</p>
          <p class="text-[#444] text-sm">Les photos ajoutées aux books apparaîtront ici.</p>
        </div>
      <?php else:
      foreach ($feed_raw as $p):
          $tags = array_filter(array_map('trim', explode(',', $p['expertise_tags'] ?? '')));
          $profession_lc = mb_strtolower($p['specific_profession'] ?? '', 'UTF-8');
          // normaliser pour data-filter (retirer accents, espaces)
          $filter_key = preg_replace('/[^a-z]/', '', iconv('UTF-8','ASCII//TRANSLIT', $profession_lc));
      ?>
      <article class="feed-post bg-[#111] rounded-2xl mb-5 overflow-hidden border border-[#1e1e1e]" data-filter="<?= htmlspecialchars($filter_key) ?>">

        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4">
          <a href="profil.php?id=<?= $p['user_id'] ?>" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
            <?php $avatar_url = $p['profile_picture_url'] ?: $p['fallback_avatar']; ?>
            <?php if ($avatar_url): ?>
              <img src="<?= htmlspecialchars($avatar_url) ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0" alt="<?= htmlspecialchars($p['full_name']) ?>">
            <?php else: ?>
              <div class="w-10 h-10 rounded-full bg-[#2a2a2a] flex items-center justify-center flex-shrink-0 text-[#555]">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-5 h-5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
            <?php endif; ?>
            <div>
              <div class="font-bold text-[15px] text-white leading-tight"><?= htmlspecialchars($p['full_name']) ?></div>
              <div class="text-[#666] text-xs mt-0.5">
                <?= htmlspecialchars($p['specific_profession'] ?? '') ?>
                <?php if (!empty($p['city'])): ?> · <?= htmlspecialchars($p['city']) ?><?php endif; ?>
              </div>
            </div>
          </a>
          <span class="text-[#555] text-xs"><?= timeAgo($p['created_at']) ?></span>
        </div>

        <!-- Image format original -->
        <a href="profil.php?id=<?= $p['user_id'] ?>">
          <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="Photo de <?= htmlspecialchars($p['full_name']) ?>" class="post-img">
        </a>

        <!-- Contenu -->
        <div class="px-5 pt-4 pb-5">
          <!-- Description de la photo -->
          <?php if (!empty($p['photo_description'])): ?>
            <p class="text-[#aaa] text-sm mb-3 leading-relaxed"><?= htmlspecialchars($p['photo_description']) ?></p>
          <?php endif; ?>

          <!-- Tags de la photo + expertise tags -->
          <div class="flex flex-wrap items-center gap-1.5 mb-3">
            <?php 
            $photo_tags = array_filter(array_map('trim', explode(',', $p['photo_tags'] ?? '')));
            $expertise_tags = array_filter(array_map('trim', explode(',', $p['expertise_tags'] ?? '')));
            $photo_tags_count = min(3, count($photo_tags));
            $expertise_tags_max = max(0, 5 - $photo_tags_count);
            ?>
            
            <!-- Tags de la photo (en mauve) -->
            <?php foreach (array_slice($photo_tags, 0, $photo_tags_count) as $tag): ?>
              <span class="tag-badge bg-[#d4a5d4] text-black"><?= htmlspecialchars($tag) ?></span>
            <?php endforeach; ?>
            
            <!-- Tags d'expertise (gris) -->
            <?php foreach (array_slice($expertise_tags, 0, $expertise_tags_max) as $tag): ?>
              <span class="tag-badge bg-[#1e1e1e] text-[#aaa] border border-[#2a2a2a]"><?= htmlspecialchars($tag) ?></span>
            <?php endforeach; ?>
            
            <?php
              $photo_liked = isset($user_liked_ids[$p['photo_id']]);
              $likes_count = (int)($p['likes_count'] ?? 0);
            ?>
            <button class="like-btn flex items-center gap-1.5 transition-colors text-sm font-semibold ml-auto flex-shrink-0 <?= $photo_liked ? 'text-[#d4a5d4]' : 'text-[#555] hover:text-[#d4a5d4]' ?>"
                    onclick="toggleLike(this)"
                    data-photo-id="<?= $p['photo_id'] ?>"
                    data-liked="<?= $photo_liked ? '1' : '0' ?>"
                    <?php if (!$is_logged_in): ?>data-require-login="1"<?php endif; ?>>
              <svg class="like-icon w-4 h-4" viewBox="0 0 24 24" fill="<?= $photo_liked ? '#d4a5d4' : 'none' ?>" stroke="<?= $photo_liked ? '#d4a5d4' : 'currentColor' ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
              </svg>
              <span class="like-count"><?= $likes_count ?></span>
            </button>
          </div>
        </div>

      </article>
      <?php endforeach; endif; ?>

    </div>

    <!-- Colonne droite -->
    <aside class="hidden lg:flex flex-col gap-6 w-[300px] flex-shrink-0">

      <?php if (!$is_logged_in): ?>
      <!-- Bloc inscription (non connecté seulement) -->
      <div class="rounded-3xl p-8 flex flex-col items-center text-center gap-4" style="background: linear-gradient(145deg,#1e1e1e,#111); box-shadow: 0 1px 0 rgba(255,255,255,0.04) inset, 0 -2px 0 rgba(0,0,0,0.8), 0 12px 32px rgba(0,0,0,0.6);">
        <span class="text-white text-lg font-black uppercase tracking-[0.2em]"><?= t('feed.join_title') ?></span>
        <p class="text-[#777] text-[13px] leading-relaxed">Créez votre book, trouvez des castings et connectez-vous aux talents de la mode.</p>
        <a href="inscription.php" class="mt-1 inline-block bg-[#d4a5d4] text-black px-7 py-2.5 rounded-full font-bold text-sm hover:opacity-90 transition-opacity"><?= t('feed.join_cta') ?></a>
      </div>
      <?php endif; ?>

      <?php if ($is_logged_in): ?>
      <!-- Bloc événements à venir -->
      <div class="rounded-3xl p-6 flex flex-col gap-4" style="background: linear-gradient(145deg,#1e1e1e,#111); box-shadow: 0 1px 0 rgba(255,255,255,0.04) inset, 0 -2px 0 rgba(0,0,0,0.8), 0 12px 32px rgba(0,0,0,0.6);">
        <div class="flex items-center justify-between">
          <span class="text-white text-sm font-black uppercase tracking-[0.15em]"><?= t('feed.upcoming_events') ?></span>
          <a href="evenements.php" class="text-[#d4a5d4] text-xs font-semibold hover:opacity-75 transition-opacity">Voir tout →</a>
        </div>
        <?php if (empty($upcoming_events)): ?>
          <p class="text-[#555] text-sm text-center py-4">Aucun événement à venir.</p>
        <?php else: ?>
          <div class="flex flex-col gap-3">
            <?php foreach ($upcoming_events as $ev):
              $d = new DateTime($ev['event_date']);
              $month = mb_strtoupper($d->format('M'), 'UTF-8');
              $day   = $d->format('j');
            ?>
            <a href="evenements.php" class="flex items-center gap-3 group">
              <div class="w-10 h-10 rounded-xl bg-[#2a2a2a] flex flex-col items-center justify-center flex-shrink-0">
                <span class="text-[#d4a5d4] text-[9px] font-bold uppercase leading-none"><?= $month ?></span>
                <span class="text-white text-sm font-bold leading-none"><?= $day ?></span>
              </div>
              <div class="min-w-0">
                <p class="text-white text-[13px] font-semibold truncate group-hover:text-[#d4a5d4] transition-colors"><?= htmlspecialchars($ev['title']) ?></p>
                <?php if (!empty($ev['city'])): ?>
                  <p class="text-[#555] text-[11px] truncate"><?= htmlspecialchars($ev['city']) ?></p>
                <?php endif; ?>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Bloc La mode en mouvement -->
      <div class="rounded-3xl p-8 flex flex-col items-center text-center gap-4 fashion-widget" style="background: linear-gradient(145deg,#1e1e1e,#111); box-shadow: 0 1px 0 rgba(255,255,255,0.04) inset, 0 -2px 0 rgba(0,0,0,0.8), 0 12px 32px rgba(0,0,0,0.6);">
        <span class="text-white text-lg font-black uppercase tracking-[0.2em]"><?= t('feed.fashion_movement') ?></span>
        <p class="text-[#777] text-[13px] leading-relaxed">Créatifs, marques, agences… ne manquez rien de la communauté ChicBook.</p>
      </div>

      <!-- Footer à propos style Instagram -->
      <div class="px-2">
        <div class="flex flex-wrap gap-x-3 gap-y-1 mb-3">
          <a href="apropos.php" target="_blank" class="text-[#555] text-[11px] hover:underline hover:text-[#888] transition-colors">À propos</a>
          <a href="preferences.php" class="text-[#555] text-[11px] hover:underline hover:text-[#888] transition-colors">Préférences</a>
          <a href="castings.php" class="text-[#555] text-[11px] hover:underline hover:text-[#888] transition-colors">Castings</a>
          <a href="evenements.php" class="text-[#555] text-[11px] hover:underline hover:text-[#888] transition-colors">Événements</a>
          <a href="trouver_talent.php" class="text-[#555] text-[11px] hover:underline hover:text-[#888] transition-colors">Trouver un talent</a>
        </div>
        <p class="text-[#444] text-[11px]">© 2025 ChicBook · Le réseau professionnel de la mode</p>
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
      opt.addEventListener('click', (e) => {
        e.stopPropagation();
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
          post.style.display = (filter === 'all' || post.dataset.filter === filter) ? '' : 'none';
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

    function toggleLike(btn) {
      if (btn.dataset.requireLogin) { window.location.href = 'connexion.php'; return; }
      const photoId = btn.dataset.photoId;
      if (!photoId) return;
      const icon = btn.querySelector('.like-icon');
      const count = btn.querySelector('.like-count');
      // Optimistic update
      const liked = btn.dataset.liked === '1';
      btn.dataset.liked = liked ? '0' : '1';
      icon.setAttribute('fill', liked ? 'none' : '#d4a5d4');
      icon.setAttribute('stroke', liked ? 'currentColor' : '#d4a5d4');
      btn.classList.toggle('text-[#d4a5d4]', !liked);
      btn.classList.toggle('text-[#555]', liked);
      count.textContent = liked ? Math.max(0, parseInt(count.textContent) - 1) : parseInt(count.textContent) + 1;
      fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle_like&photo_id=' + photoId
      }).then(r => r.json()).then(data => {
        if (data.ok) {
          btn.dataset.liked = data.liked ? '1' : '0';
          count.textContent = data.count;
          icon.setAttribute('fill', data.liked ? '#d4a5d4' : 'none');
          icon.setAttribute('stroke', data.liked ? '#d4a5d4' : 'currentColor');
          btn.classList.toggle('text-[#d4a5d4]', data.liked);
          btn.classList.toggle('text-[#555]', !data.liked);
        }
      }).catch(() => {
        // rollback on error
        btn.dataset.liked = liked ? '1' : '0';
        icon.setAttribute('fill', liked ? '#d4a5d4' : 'none');
        icon.setAttribute('stroke', liked ? '#d4a5d4' : 'currentColor');
        btn.classList.toggle('text-[#d4a5d4]', liked);
        btn.classList.toggle('text-[#555]', !liked);
        count.textContent = liked ? parseInt(count.textContent) + 1 : Math.max(0, parseInt(count.textContent) - 1);
      });
    }

    document.getElementById('welcome-modal').addEventListener('click', function(e) {
      if (e.target === this) closeWelcome();
    });
  </script>

  <script src="assets/js/script.js"></script>
  <script>
  (function(){
    function applyFeedTopbar() {
      var inlineBar = document.getElementById('feed-topbar-btns');
      var globalBar = document.getElementById('mobile-topbar');
      if (!inlineBar || !globalBar) return;
      if (window.innerWidth <= 768) {
        inlineBar.style.display = 'flex';
        globalBar.style.setProperty('display', 'none', 'important');
      } else {
        inlineBar.style.display = 'none';
        globalBar.style.removeProperty('display'); // laisse le CSS décider
      }
    }
    applyFeedTopbar();
    window.addEventListener('resize', applyFeedTopbar);
  })();
  </script>
</body>
</html>
