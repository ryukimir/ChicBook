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
    <?php include 'includes/header.php'; ?>

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