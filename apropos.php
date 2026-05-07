<?php
session_start();
// Si on a besoin de vérifier la connexion pour certains éléments
$is_logged_in = isset($_SESSION['user_id']);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>À propos - ChicBook</title>
    <link rel="stylesheet" href="src/style.css" />
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <main class="about-page">
        <section class="about-hero">
            <div class="about-hero-content">
                <h1>REJOIGNEZ CHICBOOK ET FAÇONNEZ L'AVENIR DE LA MODE AVEC VOTRE TALENT.</h1>
                <p>Chaque membre dispose de son propre book, un espace unique pour exposer, collaborer et évoluer au sein du premier hub 360° de la mode.</p>
            </div>
        </section>

        <section class="about-intro container">
            <div class="intro-text-center">
                <h2 style="color: #d4a5d4; margin-bottom: 10px;">À propos de Chicbook</h2>
                <h3>Un réseau centralisé... Connectez vos talents et projets créatifs</h3>
                <p>Un hub dédié à la mode et à l'image, où chaque talent dispose de son propre book professionnel : un espace personnel et élégant, comme une boutique digitale au sein de Chicbook.</p>
                <p>Conçu pour connecter les créatifs, les marques et les agences, Chicbook simplifie la recherche de talents, la gestion de projets et la création de visuels de haute qualité.</p>
            </div>
        </section>

        <section class="about-pillars container">
            
            <div class="pillar-row">
                <div class="pillar-image">
                    <img src="https://images.unsplash.com/photo-1558769132-cb1aea458c5e?auto=format&fit=crop&w=800&q=80" alt="Atelier de création">
                </div>
                <div class="pillar-text">
                    <h2>Marque & Créateur</h2>
                    <p>Cet univers représente la vision, l'identité et l'intention créative du projet. Il rassemble les profils qui imaginent une marque, définissent son esthétique et posent les bases de son univers. ChicBook permet ainsi aux créateurs, marques et directeurs de projet de trouver les talents qui les aideront à transformer une idée en proposition cohérente, forte et désirable.</p>
                </div>
            </div>

            <div class="pillar-row reverse">
                <div class="pillar-image">
                    <img src="https://images.unsplash.com/photo-1598532163257-ae3c6b2524b6?auto=format&fit=crop&w=800&q=80" alt="Design et croquis">
                </div>
                <div class="pillar-text">
                    <h2>Création & Design</h2>
                    <p>Cet univers donne forme aux idées. Il regroupe les métiers qui conçoivent les pièces, les volumes, les matières, les accessoires et les détails qui composent une collection. Styliste, modéliste, designer textile, designer accessoires ou brodeur participent à la mise en forme concrète d'une vision créative. ChicBook centralise ces expertises pour simplifier la collaboration entre conception et développement.</p>
                </div>
            </div>

            <div class="pillar-row">
                <div class="pillar-image">
                    <img src="https://images.unsplash.com/photo-1583391733958-65e298dde912?auto=format&fit=crop&w=800&q=80" alt="Shooting photo">
                </div>
                <div class="pillar-text">
                    <h2>Image & Production</h2>
                    <p>Cet univers valorise la création et lui donne vie à travers l'image. Il réunit les professionnels qui produisent les shootings, campagnes, contenus éditoriaux et visuels de marque : photographes, vidéastes, mannequins, maquilleurs, coiffeurs, stylistes photo et autres talents de production. ChicBook permet de connecter facilement création et mise en scène au sein d'un même réseau professionnel.</p>
                </div>
            </div>
        </section>

        <section class="about-cta-section">
            <div class="container text-center">
                <h3 style="color: #d4a5d4;">Un espace pour les professionnels et les entreprises</h3>
                <p>Chicbook facilite la collaboration entre talents, marques et agences. Vous êtes une entreprise à la recherche de créateurs, d'équipes pour vos campagnes ou de talents pour vos défilés ? Parcourez notre plateforme pour découvrir des opportunités adaptées à vos besoins.</p>
                
                <div class="cta-box">
                    <h2>Rejoignez la communauté !</h2>
                    <p>Vous êtes à un clic de trouver votre partenaire stratégique. Faites partie des pionniers et contribuez à l'évolution de Chicbook !</p>
                    <a href="inscription.php" class="btn-auth" style="display: inline-block; padding: 15px 40px; font-size: 18px; margin-top: 20px;">S'inscrire maintenant</a>
                </div>
            </div>
        </section>
    </main>

    <footer style="background-color: #111; padding: 40px 0; text-align: center; border-top: 1px solid #333;">
        <p style="color: #666; font-size: 14px;">© 2026 ChicBook. Tous droits réservés.</p>
    </footer>

    <script src="script.js"></script>
</body>
</html>