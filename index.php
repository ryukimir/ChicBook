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
    <link rel="stylesheet" href="style.css" />
  </head>
  <body>
    <header id="main-header">
      <div class="nav-center">
        <div class="nav-links-left">
          <a href="#">Trouver un talent</a>
          <a href="#">Poster un projet</a>
        </div>

        <img src="img/logo.png" class="logo-img" alt="ChicBook" />

        <div class="nav-links-right">
          <a href="#">Créer un casting</a>
          <a href="#">À propos</a>
        </div>
      </div>

      <div class="nav-right">
        <?php if ($is_logged_in): ?>
          <a href="profil.php" class="profile-avatar" title="Mon Profil">
            <span>👤</span>
          </a>
        <?php else: ?>
          <a class="btn-auth" href="connexion.php">S'identifier</a>
        <?php endif; ?>

        <div class="navicon" id="navicon">
          <span></span>
          <span></span>
          <span></span>
        </div>
      </div>
    </header>

    <section class="hero"></section>

    <main class="container">
      <div class="image-grid">
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="image-block" alt="talent" />
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="image-block" alt="talent" />
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="image-block" alt="talent" />
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="image-block" alt="talent" />
      </div>
    </main>

    <section class="expertise-section">
      <h2>Les talents, classés par expertise en UN CLICK</h2>
      <div class="tags-container">
        <div class="tag"></div><div class="tag"></div><div class="tag"></div><div class="tag"></div>
        <div class="tag"></div><div class="tag"></div><div class="tag"></div><div class="tag"></div>
      </div>
    </section>

    <div class="container">
      <div class="image-grid">
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="image-block" alt="talent" />
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="image-block" alt="talent" />
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="image-block" alt="talent" />
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="image-block" alt="talent" />
      </div>
    </div>

    <section class="cta-section">
      <button class="btn-post">Poster un projet</button>
    </section>

    <script src="script.js"></script>
  </body>
</html>