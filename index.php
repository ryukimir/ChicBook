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
  <script>
    tailwind.config = {
      theme: { extend: { colors: { brand: '#d4a5d4', dark: '#1a1a1a' } } }
    }
  </script>
  <link rel="stylesheet" href="assets/css/custom.css" />
</head>
<body class="bg-white font-['Arial',sans-serif]">
  <?php include 'includes/header.php'; ?>

  <!-- Hero -->
  <section class="bg-[#d9d9d9] h-screen w-full"></section>

  <!-- Carousel 1 : Les talents qui donnent forme à la création -->
  <main class="max-w-[1200px] mx-auto py-16 px-5">
    <section class="relative">
      <h2 class="mb-8 text-3xl font-normal text-[#1a1a1a]">Les talents qui donnent forme à la création</h2>
      <div class="relative flex items-center">
        <button class="absolute left-[-25px] z-10 w-12 h-12 bg-white text-[#1a1a1a] border border-[#ddd] rounded-full text-3xl flex justify-center items-center shadow-md hover:bg-[#d4a5d4] hover:text-white hover:border-[#d4a5d4] transition-all" id="btn-prev">‹</button>
        <div class="flex gap-5 overflow-x-auto scroll-smooth [scroll-snap-type:x_mandatory] [scrollbar-width:none] [-ms-overflow-style:none] py-4 px-1" id="talent-track">
          <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
            <img src="https://images.unsplash.com/photo-1558769132-cb1aea458c5e?auto=format&fit=crop&w=400&q=80" alt="Styliste" class="w-full h-[220px] object-cover block">
            <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">STYLISTE</h3><p class="text-sm text-[#666] leading-relaxed">Découvrez des stylistes capables de construire une silhouette, une direction visuelle et une cohérence créative pour chaque projet.</p></div>
          </div>
          <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
            <img src="https://images.unsplash.com/photo-1598532163257-ae3c6b2524b6?auto=format&fit=crop&w=400&q=80" alt="Modéliste" class="w-full h-[220px] object-cover block">
            <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">MODÉLISTE</h3><p class="text-sm text-[#666] leading-relaxed">Les modélistes transforment les idées en volumes grâce aux toiles, patronages et prototypes techniques.</p></div>
          </div>
          <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
            <img src="https://images.unsplash.com/photo-1605289982774-9a6fef564df8?auto=format&fit=crop&w=400&q=80" alt="Designer Accessoires" class="w-full h-[220px] object-cover block">
            <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">DESIGNER ACCESSOIRES</h3><p class="text-sm text-[#666] leading-relaxed">Découvrez des designers accessoires capables d'imaginer sacs, bijoux et pièces qui enrichissent une collection.</p></div>
          </div>
          <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
            <img src="https://images.unsplash.com/photo-1584992236310-6edddc08acff?auto=format&fit=crop&w=400&q=80" alt="Designer Textile" class="w-full h-[220px] object-cover block">
            <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">DESIGNER TEXTILE</h3><p class="text-sm text-[#666] leading-relaxed">Les designers textile développent matières, motifs et surfaces qui donnent une identité forte à la collection.</p></div>
          </div>
          <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
            <img src="https://images.unsplash.com/photo-1550684848-fac1c5b4e853?auto=format&fit=crop&w=400&q=80" alt="Brodeur" class="w-full h-[220px] object-cover block">
            <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">BRODEUR / ORNEMENTATION</h3><p class="text-sm text-[#666] leading-relaxed">Découvrez des spécialistes de la broderie et de l'ornementation pour apporter relief, détail et finitions aux pièces.</p></div>
          </div>
        </div>
        <button class="absolute right-[-25px] z-10 w-12 h-12 bg-white text-[#1a1a1a] border border-[#ddd] rounded-full text-3xl flex justify-center items-center shadow-md hover:bg-[#d4a5d4] hover:text-white hover:border-[#d4a5d4] transition-all" id="btn-next">›</button>
      </div>
    </section>
  </main>

  <!-- Tags expertise -->
  <section class="bg-[#1a1a1a] py-16 px-5 text-center">
    <h2 class="text-white mb-10 font-normal text-2xl">Les talents, classés par expertise en UN CLICK</h2>
    <div class="flex flex-wrap justify-center gap-4 max-w-[900px] mx-auto px-5">
      <?php
      $tags = ['Photographe','Modéliste','Directeur artistique','Créateur','Brodeur','Maquilleur','Mannequin','Vidéaste','Comédien','Danseur','Designer textile','Coiffeur','Maroquinier','Styliste','Designer Accessoires'];
      foreach ($tags as $tag): ?>
        <a href="#" class="bg-[#d4a5d4] text-[#1a1a1a] px-5 py-2.5 rounded-3xl font-bold text-sm hover:scale-105 transition-transform no-underline"><?= $tag ?></a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Carousel 2 : Les talents qui construisent votre image -->
  <section class="max-w-[1200px] mx-auto py-16 px-5 relative">
    <h2 class="mb-8 text-3xl font-normal text-[#1a1a1a]">Les talents qui construisent votre image</h2>
    <div class="relative flex items-center">
      <button class="absolute left-[-25px] z-10 w-12 h-12 bg-white text-[#1a1a1a] border border-[#ddd] rounded-full text-3xl flex justify-center items-center shadow-md hover:bg-[#d4a5d4] hover:text-white hover:border-[#d4a5d4] transition-all" id="btn-prev-image">‹</button>
      <div class="flex gap-5 overflow-x-auto [scroll-snap-type:x_mandatory] [scrollbar-width:none] [-ms-overflow-style:none] py-4 px-1" id="image-track">
        <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
          <img src="https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=400&q=80" alt="Photographe" class="w-full h-[220px] object-cover block">
          <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">PHOTOGRAPHES</h3><p class="text-sm text-[#666] leading-relaxed">Découvrez des photographes capables de sublimer vos produits, campagnes et identités visuelles.</p></div>
        </div>
        <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
          <img src="https://images.unsplash.com/photo-1601506521937-0121a7fc2a6b?auto=format&fit=crop&w=400&q=80" alt="Vidéaste" class="w-full h-[220px] object-cover block">
          <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">VIDÉASTES</h3><p class="text-sm text-[#666] leading-relaxed">Découvrez des vidéastes pour raconter votre univers de marque à travers films, contenus et campagnes.</p></div>
        </div>
        <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
          <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80" alt="Mannequin" class="w-full h-[220px] object-cover block">
          <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">MANNEQUINS</h3><p class="text-sm text-[#666] leading-relaxed">Découvrez des mannequins pour incarner vos silhouettes, shootings, défilés et campagnes.</p></div>
        </div>
        <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
          <img src="https://images.unsplash.com/photo-1514306191717-452ec28c7814?auto=format&fit=crop&w=400&q=80" alt="Comédiens" class="w-full h-[220px] object-cover block">
          <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">COMÉDIENS</h3><p class="text-sm text-[#666] leading-relaxed">Découvrez des comédiens pour donner vie à vos films de marque, contenus et campagnes publicitaires.</p></div>
        </div>
        <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
          <img src="https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?auto=format&fit=crop&w=400&q=80" alt="Danseur" class="w-full h-[220px] object-cover block">
          <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">DANSEUR</h3><p class="text-sm text-[#666] leading-relaxed">Découvrez des danseurs pour apporter mouvement, énergie et présence scénique à vos projets visuels.</p></div>
        </div>
        <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
          <img src="https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80" alt="Coiffeurs" class="w-full h-[220px] object-cover block">
          <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">COIFFEURS</h3><p class="text-sm text-[#666] leading-relaxed">Découvrez des coiffeurs pour construire des looks coiffure cohérents avec votre univers mode.</p></div>
        </div>
        <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
          <img src="https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?auto=format&fit=crop&w=400&q=80" alt="Maquilleures" class="w-full h-[220px] object-cover block">
          <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">MAQUILLEURES</h3><p class="text-sm text-[#666] leading-relaxed">Découvrez des maquilleures pour révéler une esthétique précise sur shootings, défilés et campagnes.</p></div>
        </div>
        <div class="flex-[0_0_auto] w-80 bg-white rounded-xl overflow-hidden [scroll-snap-align:start] shadow-md hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300">
          <img src="https://images.unsplash.com/photo-1558769132-cb1aea458c5e?auto=format&fit=crop&w=400&q=80" alt="Styliste" class="w-full h-[220px] object-cover block">
          <div class="p-5"><h3 class="text-base font-bold mb-3 uppercase text-[#1a1a1a]">STYLISTE</h3><p class="text-sm text-[#666] leading-relaxed">Découvrez des stylistes pour penser les looks, les silhouettes et la cohérence visuelle de vos projets.</p></div>
        </div>
      </div>
      <button class="absolute right-[-25px] z-10 w-12 h-12 bg-white text-[#1a1a1a] border border-[#ddd] rounded-full text-3xl flex justify-center items-center shadow-md hover:bg-[#d4a5d4] hover:text-white hover:border-[#d4a5d4] transition-all" id="btn-next-image">›</button>
    </div>
  </section>

  <!-- CTA -->
  <section class="bg-[#1a1a1a] py-20 px-5 text-center">
    <button class="bg-[#d4a5d4] text-[#1a1a1a] py-4 px-16 rounded-2xl text-lg font-medium cursor-pointer hover:opacity-90 transition-opacity">Poster un projet</button>
  </section>

  <script src="assets/js/script.js"></script>
</body>
</html>
