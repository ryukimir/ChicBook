<?php
session_start();
$is_logged_in = isset($_SESSION['user_id']);
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>À propos - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: { extend: { colors: { brand: '#d4a5d4', dark: '#1a1a1a' } } }
      }
    </script>
    <link rel="stylesheet" href="assets/css/custom.css" />
</head>
<body class="bg-black text-white font-['Arial',sans-serif] leading-relaxed">

    <?php include 'includes/header.php'; ?>

    <!-- Hero -->
    <section class="bg-cover bg-center py-[150px_20px_100px] text-center" style="background-image: linear-gradient(rgba(26,26,26,0.8), rgba(26,26,26,0.9)), url('https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=2000&q=80'); padding: 150px 20px 100px;">
        <div class="max-w-[800px] mx-auto">
            <h1 class="text-[42px] font-bold mb-5 leading-tight">REJOIGNEZ CHICBOOK ET FAÇONNEZ L'AVENIR DE LA MODE AVEC VOTRE TALENT.</h1>
            <p class="text-lg text-[#ccc]">Chaque membre dispose de son propre book, un espace unique pour exposer, collaborer et évoluer au sein du premier hub 360° de la mode.</p>
        </div>
    </section>

    <!-- Intro -->
    <section class="py-20 px-5">
        <div class="max-w-[800px] mx-auto text-center">
            <h2 class="text-[#d4a5d4] mb-2.5">À propos de Chicbook</h2>
            <h3 class="text-2xl mb-8">Un réseau centralisé... Connectez vos talents et projets créatifs</h3>
            <p class="text-[#bbb] mb-4 text-base">Un hub dédié à la mode et à l'image, où chaque talent dispose de son propre book professionnel : un espace personnel et élégant, comme une boutique digitale au sein de Chicbook.</p>
            <p class="text-[#bbb] text-base">Conçu pour connecter les créatifs, les marques et les agences, Chicbook simplifie la recherche de talents, la gestion de projets et la création de visuels de haute qualité.</p>
        </div>
    </section>

    <!-- Piliers -->
    <section class="max-w-[1200px] mx-auto py-10 px-5 pb-20">

        <div class="flex items-center gap-[60px] mb-[100px] md:flex-col md:gap-8 md:text-center">
            <div class="flex-1"><img src="https://images.unsplash.com/photo-1558769132-cb1aea458c5e?auto=format&fit=crop&w=800&q=80" alt="Atelier de création" class="w-full rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] hover:-translate-y-1.5 transition-transform duration-300"></div>
            <div class="flex-1">
                <h2 class="text-3xl text-[#d4a5d4] mb-5">Marque & Créateur</h2>
                <p class="text-[#bbb] text-base">Cet univers représente la vision, l'identité et l'intention créative du projet. Il rassemble les profils qui imaginent une marque, définissent son esthétique et posent les bases de son univers. ChicBook permet ainsi aux créateurs, marques et directeurs de projet de trouver les talents qui les aideront à transformer une idée en proposition cohérente, forte et désirable.</p>
            </div>
        </div>

        <div class="flex flex-row-reverse items-center gap-[60px] mb-[100px] md:flex-col md:gap-8 md:text-center">
            <div class="flex-1"><img src="https://images.unsplash.com/photo-1598532163257-ae3c6b2524b6?auto=format&fit=crop&w=800&q=80" alt="Design et croquis" class="w-full rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] hover:-translate-y-1.5 transition-transform duration-300"></div>
            <div class="flex-1">
                <h2 class="text-3xl text-[#d4a5d4] mb-5">Création & Design</h2>
                <p class="text-[#bbb] text-base">Cet univers donne forme aux idées. Il regroupe les métiers qui conçoivent les pièces, les volumes, les matières, les accessoires et les détails qui composent une collection. Styliste, modéliste, designer textile, designer accessoires ou brodeur participent à la mise en forme concrète d'une vision créative. ChicBook centralise ces expertises pour simplifier la collaboration entre conception et développement.</p>
            </div>
        </div>

        <div class="flex items-center gap-[60px] md:flex-col md:gap-8 md:text-center">
            <div class="flex-1"><img src="https://images.unsplash.com/photo-1583391733958-65e298dde912?auto=format&fit=crop&w=800&q=80" alt="Shooting photo" class="w-full rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] hover:-translate-y-1.5 transition-transform duration-300"></div>
            <div class="flex-1">
                <h2 class="text-3xl text-[#d4a5d4] mb-5">Image & Production</h2>
                <p class="text-[#bbb] text-base">Cet univers valorise la création et lui donne vie à travers l'image. Il réunit les professionnels qui produisent les shootings, campagnes, contenus éditoriaux et visuels de marque : photographes, vidéastes, mannequins, maquilleurs, coiffeurs, stylistes photo et autres talents de production. ChicBook permet de connecter facilement création et mise en scène au sein d'un même réseau professionnel.</p>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-[#222] py-20 px-5 border-t border-[#333]">
        <div class="max-w-[800px] mx-auto text-center">
            <h3 class="text-[#d4a5d4] text-xl mb-4">Un espace pour les professionnels et les entreprises</h3>
            <p class="text-[#bbb] text-base">Chicbook facilite la collaboration entre talents, marques et agences. Vous êtes une entreprise à la recherche de créateurs, d'équipes pour vos campagnes ou de talents pour vos défilés ? Parcourez notre plateforme pour découvrir des opportunités adaptées à vos besoins.</p>
            <div class="bg-[#1a1a1a] p-[50px] rounded-2xl mt-12 border border-[#333] shadow-[0_10px_30px_rgba(0,0,0,0.3)]">
                <h2 class="text-4xl mb-4">Rejoignez la communauté !</h2>
                <p class="text-[#bbb] mb-5">Vous êtes à un clic de trouver votre partenaire stratégique. Faites partie des pionniers et contribuez à l'évolution de Chicbook !</p>
                <a href="inscription.php" class="inline-block bg-[#d4a5d4] text-[#1a1a1a] px-10 py-4 text-lg font-medium rounded-full mt-5 hover:opacity-90 transition-opacity">S'inscrire maintenant</a>
            </div>
        </div>
    </section>

    <footer class="bg-black py-10 text-center border-t border-[#333]">
        <p class="text-[#666] text-sm">© 2026 ChicBook. Tous droits réservés.</p>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>

