<?php
session_start();
$is_logged_in = isset($_SESSION['user_id']);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>À propos · ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
      *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

      html { scroll-behavior: smooth; }

      body {
        font-family: 'Open Sans', sans-serif;
        background: #000;
        color: #fff;
        overflow-x: hidden;
      }

      /* ── Hero ── */
      .hero {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 60px 24px;
        position: relative;
        overflow: hidden;
      }

      .hero-bg {
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse 80% 60% at 50% 40%, rgba(212,165,212,0.18) 0%, transparent 70%),
                    radial-gradient(ellipse 50% 40% at 80% 80%, rgba(212,165,212,0.08) 0%, transparent 60%);
        z-index: 0;
      }

      .hero-grain {
        position: absolute;
        inset: 0;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
        z-index: 0;
        opacity: 0.4;
      }

      .hero > * { position: relative; z-index: 1; }

      .hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(212,165,212,0.12);
        border: 1px solid rgba(212,165,212,0.25);
        color: #d4a5d4;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        padding: 6px 16px;
        border-radius: 999px;
        margin-bottom: 32px;
        opacity: 0;
        transform: translateY(20px);
        animation: fadeUp 0.7s ease 0.2s forwards;
      }

      .hero-title {
        font-size: clamp(40px, 7vw, 96px);
        font-weight: 800;
        line-height: 1.02;
        letter-spacing: -0.03em;
        opacity: 0;
        transform: translateY(30px);
        animation: fadeUp 0.8s ease 0.4s forwards;
      }

      .hero-title em {
        font-style: normal;
        color: #d4a5d4;
      }

      .hero-subtitle {
        max-width: 640px;
        font-size: 18px;
        color: #888;
        line-height: 1.7;
        margin-top: 28px;
        opacity: 0;
        transform: translateY(20px);
        animation: fadeUp 0.8s ease 0.65s forwards;
      }

      .hero-cta {
        display: flex;
        gap: 14px;
        margin-top: 44px;
        flex-wrap: wrap;
        justify-content: center;
        opacity: 0;
        transform: translateY(20px);
        animation: fadeUp 0.8s ease 0.85s forwards;
      }

      .hero-scroll-hint {
        margin-top: 80px;
        opacity: 0;
        animation: fadeUp 0.6s ease 1.3s forwards;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        color: #444;
        font-size: 12px;
        letter-spacing: 0.1em;
        text-transform: uppercase;
      }

      .scroll-line {
        width: 1px;
        height: 48px;
        background: linear-gradient(to bottom, #444, transparent);
        animation: scrollPulse 2s ease-in-out infinite;
      }

      /* ── Stats bar ── */
      .stats-bar {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1px;
        background: #1a1a1a;
        border-top: 1px solid #1a1a1a;
        border-bottom: 1px solid #1a1a1a;
      }

      .stat-cell {
        background: #000;
        padding: 48px 24px;
        text-align: center;
      }

      .stat-number {
        font-size: clamp(36px, 5vw, 64px);
        font-weight: 800;
        letter-spacing: -0.03em;
        color: #fff;
        line-height: 1;
      }

      .stat-number span { color: #d4a5d4; }

      .stat-label {
        font-size: 13px;
        color: #555;
        margin-top: 8px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        font-weight: 600;
      }

      /* ── Sections scroll-reveal ── */
      .reveal {
        opacity: 0;
        transform: translateY(48px);
        transition: opacity 0.8s cubic-bezier(0.16,1,0.3,1), transform 0.8s cubic-bezier(0.16,1,0.3,1);
      }

      .reveal.visible {
        opacity: 1;
        transform: translateY(0);
      }

      .reveal-left {
        opacity: 0;
        transform: translateX(-48px);
        transition: opacity 0.9s cubic-bezier(0.16,1,0.3,1), transform 0.9s cubic-bezier(0.16,1,0.3,1);
      }

      .reveal-right {
        opacity: 0;
        transform: translateX(48px);
        transition: opacity 0.9s cubic-bezier(0.16,1,0.3,1), transform 0.9s cubic-bezier(0.16,1,0.3,1);
      }

      .reveal-left.visible, .reveal-right.visible {
        opacity: 1;
        transform: translateX(0);
      }

      /* ── Feature sections ── */
      .feature-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 80px;
        align-items: center;
        max-width: 1200px;
        margin: 0 auto;
        padding: 120px 40px;
      }

      .feature-section.reverse { direction: rtl; }
      .feature-section.reverse > * { direction: ltr; }

      .feature-visual {
        position: relative;
        border-radius: 24px;
        overflow: hidden;
        aspect-ratio: 4/3;
      }

      .feature-visual img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s ease;
      }

      .feature-visual:hover img { transform: scale(1.04); }

      .feature-visual::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 24px;
        border: 1px solid rgba(255,255,255,0.06);
        pointer-events: none;
      }

      .feature-tag {
        display: inline-block;
        background: rgba(212,165,212,0.12);
        color: #d4a5d4;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        padding: 4px 12px;
        border-radius: 999px;
        margin-bottom: 20px;
      }

      .feature-title {
        font-size: clamp(28px, 3.5vw, 44px);
        font-weight: 800;
        letter-spacing: -0.02em;
        line-height: 1.1;
        margin-bottom: 20px;
      }

      .feature-body {
        font-size: 16px;
        color: #777;
        line-height: 1.8;
      }

      /* ── Horizontal marquee ── */
      .marquee-wrap {
        overflow: hidden;
        padding: 40px 0;
        background: #000;
        border-top: 1px solid #111;
        border-bottom: 1px solid #111;
      }

      .marquee-track {
        display: flex;
        gap: 48px;
        width: max-content;
        animation: marquee 22s linear infinite;
      }

      .marquee-item {
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: #333;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 48px;
      }

      .marquee-item::after {
        content: '·';
        color: #d4a5d4;
      }

      /* ── CTA final ── */
      .cta-section {
        min-height: 70vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 120px 40px;
        position: relative;
        overflow: hidden;
      }

      .cta-bg {
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse 60% 50% at 50% 50%, rgba(212,165,212,0.12) 0%, transparent 70%);
      }

      .cta-section > * { position: relative; z-index: 1; }

      /* ── Bouton retour ── */
      .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: #111;
        border: 1px solid #2a2a2a;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        padding: 14px 28px;
        border-radius: 999px;
        text-decoration: none;
        transition: background 0.2s, border-color 0.2s, transform 0.2s;
        margin-top: 16px;
      }

      .back-btn:hover {
        background: #1a1a1a;
        border-color: #444;
        transform: translateY(-2px);
      }

      /* ── Animations ── */
      @keyframes fadeUp {
        to { opacity: 1; transform: translateY(0); }
      }

      @keyframes scrollPulse {
        0%, 100% { opacity: 0.4; transform: scaleY(1); }
        50% { opacity: 1; transform: scaleY(1.1); }
      }

      @keyframes marquee {
        from { transform: translateX(0); }
        to { transform: translateX(-50%); }
      }

      /* ── Responsive ── */
      @media (max-width: 768px) {
        .feature-section { grid-template-columns: 1fr; gap: 40px; padding: 80px 24px; }
        .feature-section.reverse { direction: ltr; }
        .stats-bar { grid-template-columns: 1fr; gap: 0; }
        .stat-cell { padding: 32px 24px; border-bottom: 1px solid #111; }
      }
    </style>
</head>
<body>

  <!-- ══════════════════════════════════════
       HERO
  ═══════════════════════════════════════ -->
  <section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-grain"></div>

    <div class="hero-eyebrow">
      <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg>
      Plateforme · Mode · Talents
    </div>

    <h1 class="hero-title">
      Le réseau professionnel<br>
      <em>de la mode française</em>
    </h1>

    <p class="hero-subtitle">
      ChicBook centralise tous les talents de l'industrie — mannequins, photographes, stylistes, créateurs, agences — au sein d'une seule plateforme conçue pour collaborer.
    </p>

    <div class="hero-cta">
      <?php if (!$is_logged_in): ?>
      <a href="inscription.php" style="background:#d4a5d4;color:#000;font-weight:700;font-size:14px;padding:14px 32px;border-radius:999px;text-decoration:none;transition:opacity 0.2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        Rejoindre ChicBook
      </a>
      <?php endif; ?>
      <a href="trouver_talent.php" style="background:#111;color:#fff;font-weight:600;font-size:14px;padding:14px 32px;border-radius:999px;text-decoration:none;border:1px solid #2a2a2a;transition:border-color 0.2s;" onmouseover="this.style.borderColor='#555'" onmouseout="this.style.borderColor='#2a2a2a'">
        Explorer les talents →
      </a>
    </div>

    <div class="hero-scroll-hint">
      <div class="scroll-line"></div>
      Défiler
    </div>
  </section>


  <!-- ══════════════════════════════════════
       MARQUEE
  ═══════════════════════════════════════ -->
  <div class="marquee-wrap">
    <div class="marquee-track">
      <?php
      $items = ['Mannequin','Photographe','Styliste','Vidéaste','Coiffeur','Maquilleur','Modéliste','Designer','Directeur artistique','Casting director','Marque','Agence','Créateur','Illustrateur'];
      $repeated = array_merge($items, $items, $items, $items);
      foreach ($repeated as $it): ?>
        <span class="marquee-item"><?= htmlspecialchars($it) ?></span>
      <?php endforeach; ?>
    </div>
  </div>


  <!-- ══════════════════════════════════════
       STATS
  ═══════════════════════════════════════ -->
  <div class="stats-bar">
    <div class="stat-cell reveal">
      <div class="stat-number"><span class="counter" data-target="14">0</span>+</div>
      <div class="stat-label">Métiers représentés</div>
    </div>
    <div class="stat-cell reveal" style="transition-delay:0.12s">
      <div class="stat-number"><span class="counter" data-target="360">0</span>°</div>
      <div class="stat-label">Plateforme complète</div>
    </div>
    <div class="stat-cell reveal" style="transition-delay:0.24s">
      <div class="stat-number"><span style="color:#d4a5d4">#</span>1</div>
      <div class="stat-label">Hub mode en France</div>
    </div>
  </div>


  <!-- ══════════════════════════════════════
       INTRO
  ═══════════════════════════════════════ -->
  <section style="padding:120px 40px;max-width:800px;margin:0 auto;text-align:center;" class="reveal">
    <p style="font-size:11px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#d4a5d4;margin-bottom:20px;">Notre mission</p>
    <h2 style="font-size:clamp(28px,4vw,48px);font-weight:800;letter-spacing:-0.025em;line-height:1.15;margin-bottom:28px;">
      Un hub centralisé pour<br>tous les professionnels de la mode
    </h2>
    <p style="font-size:17px;color:#666;line-height:1.85;">
      Conçu pour connecter les créatifs, les marques et les agences, ChicBook simplifie la recherche de talents, la gestion de projets et la création de visuels de haute qualité. Chaque membre dispose de son propre book — un espace unique pour exposer, collaborer et évoluer.
    </p>
  </section>


  <!-- ══════════════════════════════════════
       PILIER 1 — Marque & Créateur
  ═══════════════════════════════════════ -->
  <section style="background:#0a0a0a;border-top:1px solid #111;border-bottom:1px solid #111;">
    <div class="feature-section">
      <div class="feature-visual reveal-left">
        <img src="https://images.unsplash.com/photo-1558769132-cb1aea458c5e?auto=format&fit=crop&w=800&q=80" alt="Atelier de création">
      </div>
      <div class="reveal-right">
        <div class="feature-tag">Marque &amp; Créateur</div>
        <h2 class="feature-title">La vision au cœur de la mode</h2>
        <p class="feature-body">
          Cet univers représente l'identité et l'intention créative d'un projet. Il rassemble les profils qui imaginent une marque, définissent son esthétique et posent les bases de son univers. ChicBook permet aux créateurs, marques et directeurs de projet de trouver les talents qui transforment une idée en proposition cohérente, forte et désirable.
        </p>
        <div style="margin-top:32px;display:flex;flex-wrap:wrap;gap:8px;">
          <?php foreach (['Marque','Créateur','Agence','Casting director'] as $p): ?>
            <span style="background:#111;border:1px solid #222;color:#999;font-size:12px;font-weight:600;padding:5px 14px;border-radius:999px;letter-spacing:0.05em;"><?= $p ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>


  <!-- ══════════════════════════════════════
       PILIER 2 — Création & Design
  ═══════════════════════════════════════ -->
  <section>
    <div class="feature-section reverse">
      <div class="feature-visual reveal-right">
        <img src="https://images.unsplash.com/photo-1598532163257-ae3c6b2524b6?auto=format&fit=crop&w=800&q=80" alt="Design et croquis">
      </div>
      <div class="reveal-left">
        <div class="feature-tag">Création &amp; Design</div>
        <h2 class="feature-title">Donner forme aux idées</h2>
        <p class="feature-body">
          Cet univers regroupe les métiers qui conçoivent les pièces, volumes, matières et détails qui composent une collection. Styliste, modéliste, designer textile, illustrateur ou directeur artistique participent à la mise en forme concrète d'une vision créative. ChicBook centralise ces expertises pour simplifier la collaboration entre conception et développement.
        </p>
        <div style="margin-top:32px;display:flex;flex-wrap:wrap;gap:8px;">
          <?php foreach (['Styliste','Modéliste','Designer','Illustrateur','Directeur artistique'] as $p): ?>
            <span style="background:#111;border:1px solid #222;color:#999;font-size:12px;font-weight:600;padding:5px 14px;border-radius:999px;letter-spacing:0.05em;"><?= $p ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>


  <!-- ══════════════════════════════════════
       PILIER 3 — Image & Production
  ═══════════════════════════════════════ -->
  <section style="background:#0a0a0a;border-top:1px solid #111;border-bottom:1px solid #111;">
    <div class="feature-section">
      <div class="feature-visual reveal-left">
        <img src="https://images.unsplash.com/photo-1583391733958-65e298dde912?auto=format&fit=crop&w=800&q=80" alt="Shooting photo">
      </div>
      <div class="reveal-right">
        <div class="feature-tag">Image &amp; Production</div>
        <h2 class="feature-title">L'image qui donne vie à la mode</h2>
        <p class="feature-body">
          Cet univers valorise la création à travers l'image. Il réunit les professionnels qui produisent shootings, campagnes, contenus éditoriaux et visuels de marque : photographes, vidéastes, mannequins, maquilleurs, coiffeurs et autres talents de production. ChicBook connecte facilement création et mise en scène au sein d'un même réseau professionnel.
        </p>
        <div style="margin-top:32px;display:flex;flex-wrap:wrap;gap:8px;">
          <?php foreach (['Photographe','Vidéaste','Mannequin','Maquilleur','Coiffeur'] as $p): ?>
            <span style="background:#111;border:1px solid #222;color:#999;font-size:12px;font-weight:600;padding:5px 14px;border-radius:999px;letter-spacing:0.05em;"><?= $p ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>


  <!-- ══════════════════════════════════════
       CTA FINAL
  ═══════════════════════════════════════ -->
  <section class="cta-section">
    <div class="cta-bg"></div>

    <p class="reveal" style="font-size:11px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#d4a5d4;margin-bottom:20px;position:relative;z-index:1;">
      Rejoindre la communauté
    </p>

    <h2 class="reveal" style="font-size:clamp(32px,5vw,72px);font-weight:800;letter-spacing:-0.03em;line-height:1.08;margin-bottom:24px;max-width:700px;transition-delay:0.1s;">
      Faites partie des pionniers de la mode digitale
    </h2>

    <p class="reveal" style="font-size:17px;color:#666;max-width:520px;line-height:1.75;margin-bottom:44px;transition-delay:0.2s;">
      Vous êtes à un clic de trouver votre prochain collaborateur. Créez votre book, publiez vos castings, découvrez les talents qui font la mode de demain.
    </p>

    <div class="reveal" style="display:flex;flex-wrap:wrap;gap:14px;justify-content:center;transition-delay:0.3s;">
      <?php if (!$is_logged_in): ?>
      <a href="inscription.php" style="background:#d4a5d4;color:#000;font-weight:700;font-size:15px;padding:16px 36px;border-radius:999px;text-decoration:none;transition:opacity 0.2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        S'inscrire gratuitement
      </a>
      <?php else: ?>
      <a href="trouver_talent.php" style="background:#d4a5d4;color:#000;font-weight:700;font-size:15px;padding:16px 36px;border-radius:999px;text-decoration:none;transition:opacity 0.2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        Explorer les talents →
      </a>
      <?php endif; ?>
    </div>

    <!-- Bouton retour -->
    <div class="reveal" style="margin-top:80px;padding-top:80px;border-top:1px solid #111;width:100%;max-width:500px;text-align:center;transition-delay:0.4s;">
      <p style="font-size:13px;color:#444;margin-bottom:16px;letter-spacing:0.05em;">Vous avez tout vu ?</p>
      <a href="index.php" class="back-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
        Revenir sur ChicBook
      </a>
    </div>
  </section>


  <script>
    // ── Scroll reveal ──
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.reveal, .reveal-left, .reveal-right').forEach(el => {
      observer.observe(el);
    });

    // ── Compteurs animés ──
    const counterObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const el = entry.target;
        const target = parseInt(el.dataset.target);
        const duration = 1600;
        const start = performance.now();
        const easeOut = t => 1 - Math.pow(1 - t, 3);
        const tick = (now) => {
          const progress = Math.min((now - start) / duration, 1);
          el.textContent = Math.round(easeOut(progress) * target);
          if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
        counterObserver.unobserve(el);
      });
    }, { threshold: 0.5 });

    document.querySelectorAll('.counter').forEach(el => counterObserver.observe(el));
  </script>

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

</body>
</html>
