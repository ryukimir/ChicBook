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
  <link rel="stylesheet" href="src/style.css" />
</head>

<body>
  <?php include 'includes/header.php'; ?>

  <section class="hero"></section>

  <main class="container">
    <section class="talents-carousel-section container">
      <h2 style="margin-bottom: 30px; font-size: 28px;">Les talents qui donnent forme à la création</h2>

      <div class="carousel-wrapper">

        <button class="carousel-btn left" id="btn-prev">‹</button>

        <div class="carousel-track" id="talent-track">

          <div class="carousel-card">
            <img src="https://images.unsplash.com/photo-1558769132-cb1aea458c5e?auto=format&fit=crop&w=400&q=80" alt="Styliste">
            <div class="card-content">
              <h3>STYLISTE</h3>
              <p>Découvrez des stylistes capables de construire une silhouette, une direction visuelle et une cohérence créative pour chaque projet.</p>
            </div>
          </div>

          <div class="carousel-card">
            <img src="https://images.unsplash.com/photo-1598532163257-ae3c6b2524b6?auto=format&fit=crop&w=400&q=80" alt="Modéliste">
            <div class="card-content">
              <h3>MODÉLISTE</h3>
              <p>Les modélistes transforment les idées en volumes grâce aux toiles, patronages et prototypes techniques.</p>
            </div>
          </div>

          <div class="carousel-card">
            <img src="https://images.unsplash.com/photo-1605289982774-9a6fef564df8?auto=format&fit=crop&w=400&q=80" alt="Designer Accessoires">
            <div class="card-content">
              <h3>DESIGNER ACCESSOIRES</h3>
              <p>Découvrez des designers accessoires capables d'imaginer sacs, bijoux et pièces qui enrichissent une collection.</p>
            </div>
          </div>

          <div class="carousel-card">
            <img src="https://images.unsplash.com/photo-1584992236310-6edddc08acff?auto=format&fit=crop&w=400&q=80" alt="Designer Textile">
            <div class="card-content">
              <h3>DESIGNER TEXTILE</h3>
              <p>Les designers textile développent matières, motifs et surfaces qui donnent une identité forte à la collection.</p>
            </div>
          </div>

          <div class="carousel-card">
            <img src="https://images.unsplash.com/photo-1550684848-fac1c5b4e853?auto=format&fit=crop&w=400&q=80" alt="Brodeur">
            <div class="card-content">
              <h3>BRODEUR / ORNEMENTATION</h3>
              <p>Découvrez des spécialistes de la broderie et de l'ornementation pour apporter relief, détail et finitions aux pièces.</p>
            </div>
          </div>

        </div>

        <button class="carousel-btn right" id="btn-next">›</button>
      </div>
    </section>
  </main>

  <section class="expertise-section" style="text-align: center;">
    <h2 style="margin-bottom: 30px; font-size: 28px;">Les talents, classés par expertise en UN CLICK</h2>

    <div class="tags-container" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; max-width: 900px; margin: 0 auto; padding: 0 20px;">

      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Photographe</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Modéliste</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Directeur artistique</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Créateur</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Brodeur</a>

      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Maquilleur</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Mannequin</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Vidéaste</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Comédien</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Danseur</a>

      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Designer textile</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Coiffeur</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Maroquinier</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Styliste</a>
      <a href="#" class="tag" style="text-decoration: none; color: #1a1a1a; padding: 10px 20px; border-radius: 25px; font-weight: bold; background-color: #d4a5d4; transition: transform 0.2s;">Designer Accessoires</a>

    </div>
  </section>

  <section class="talents-carousel-section container">
    <h2 style="margin-bottom: 30px; font-size: 28px;">Les talents qui construisent votre image</h2>

    <div class="carousel-wrapper">

      <button class="carousel-btn left" id="btn-prev-image">‹</button>

      <div class="carousel-track" id="image-track">

        <div class="carousel-card">
          <img src="https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=400&q=80" alt="Photographe">
          <div class="card-content">
            <h3>PHOTOGRAPHES</h3>
            <p>Découvrez des photographes capables de sublimer vos produits, campagnes et identités visuelles.</p>
          </div>
        </div>

        <div class="carousel-card">
          <img src="https://images.unsplash.com/photo-1601506521937-0121a7fc2a6b?auto=format&fit=crop&w=400&q=80" alt="Vidéaste">
          <div class="card-content">
            <h3>VIDÉASTES</h3>
            <p>Découvrez des vidéastes pour raconter votre univers de marque à travers films, contenus et campagnes.</p>
          </div>
        </div>

        <div class="carousel-card">
          <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80" alt="Mannequin">
          <div class="card-content">
            <h3>MANNEQUINS</h3>
            <p>Découvrez des mannequins pour incarner vos silhouettes, shootings, défilés et campagnes.</p>
          </div>
        </div>

        <div class="carousel-card">
          <img src="https://images.unsplash.com/photo-1514306191717-452ec28c7814?auto=format&fit=crop&w=400&q=80" alt="Comédiens">
          <div class="card-content">
            <h3>COMÉDIENS</h3>
            <p>Découvrez des comédiens pour donner vie à vos films de marque, contenus et campagnes publicitaires.</p>
          </div>
        </div>

        <div class="carousel-card">
          <img src="https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?auto=format&fit=crop&w=400&q=80" alt="Danseur">
          <div class="card-content">
            <h3>DANSEUR</h3>
            <p>Découvrez des danseurs pour apporter mouvement, énergie et présence scénique à vos projets visuels.</p>
          </div>
        </div>

        <div class="carousel-card">
          <img src="https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80" alt="Coiffeurs">
          <div class="card-content">
            <h3>COIFFEURS</h3>
            <p>Découvrez des coiffeurs pour construire des looks coiffure cohérents avec votre univers mode.</p>
          </div>
        </div>

        <div class="carousel-card">
          <img src="https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?auto=format&fit=crop&w=400&q=80" alt="Maquilleures">
          <div class="card-content">
            <h3>MAQUILLEURES</h3>
            <p>Découvrez des maquilleures pour révéler une esthétique précise sur shootings, défilés et campagnes.</p>
          </div>
        </div>

        <div class="carousel-card">
          <img src="https://images.unsplash.com/photo-1558769132-cb1aea458c5e?auto=format&fit=crop&w=400&q=80" alt="Styliste">
          <div class="card-content">
            <h3>STYLISTE</h3>
            <p>Découvrez des stylistes pour penser les looks, les silhouettes et la cohérence visuelle de vos projets.</p>
          </div>
        </div>

      </div>

      <button class="carousel-btn right" id="btn-next-image">›</button>
    </div>
  </section>



  <section class="cta-section">
    <button class="btn-post">Poster un projet</button>
  </section>

  <script src="script.js"></script>
</body>

</html>