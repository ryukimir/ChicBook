<?php
session_start();
require_once 'config/Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = "Projet publié avec succès ! (Simulation)";
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Poster un projet - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: { extend: { colors: { brand: '#d4a5d4', dark: '#1a1a1a' } } }
      }
    </script>
    <link rel="stylesheet" href="assets/css/custom.css" />
    <style>
      .form-control { width:100%; padding:14px; border-radius:8px; border:1px solid #333; background:#111; color:white; font-size:15px; font-family:inherit; }
      .form-control:focus { outline:none; border-color:#d4a5d4; }
      textarea.form-control { height:150px; resize:vertical; }
      select.form-control { appearance:none; background-image:url('data:image/svg+xml;utf8,<svg fill="white" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>'); background-repeat:no-repeat; background-position-x:98%; background-position-y:50%; }
      label { display:block; margin-bottom:8px; color:#ddd; font-size:14px; font-weight:bold; }
      .form-group { margin-bottom:25px; }
      .team-manual, .team-search { display:none; }
      .team-active { display:block; }
    </style>
</head>
<body class="bg-black text-white font-['Arial',sans-serif]">
    <?php include 'includes/header.php'; ?>

    <main class="max-w-[900px] mx-auto mt-10 mb-10 p-12 bg-[#1a1a1a] rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)]">
        <h1 class="text-center uppercase tracking-[2px] mb-2.5 text-2xl font-bold">Poster un projet</h1>
        <p class="text-center text-[#aaa] italic mb-10">Vous avez un besoin créatif ? Trouvez les bons talents.<br>Un projet est une collaboration créative dans la durée.</p>

        <?php if ($message): ?>
            <div class="bg-[rgba(46,125,50,0.2)] text-[#81c784] p-4 rounded-lg mb-5 border border-[#2e7d32] text-center"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form action="poster_projet.php" method="POST" enctype="multipart/form-data">
            <div class="form-group"><label>Nom du projet</label><input type="text" name="title" class="form-control" placeholder="Ex: Shooting Éditorial Été 2026" required></div>

            <div class="flex gap-5">
                <div class="form-group flex-1">
                    <label>Type de projet</label>
                    <select name="project_type" class="form-control" required>
                        <option value="">Sélectionnez un type</option>
                        <?php foreach (['Shooting Photo', 'Défilé / Runway', 'Campagne Vidéo', 'Lookbook'] as $t): ?>
                            <option><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group flex-1">
                    <label>Profils recherchés</label>
                    <select name="searched_profiles" class="form-control" required>
                        <option value="">Sélectionnez un profil</option>
                        <?php foreach (['Mannequin', 'Photographe', 'Danseur', 'Maquilleur'] as $p): ?>
                            <option><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group"><label>Décrivez l'objectif du projet, sa vision et les étapes prévues.</label><textarea name="description" class="form-control" placeholder="Décrivez votre projet en détail..." required></textarea></div>
            <div class="form-group w-1/2"><label>Date prévue</label><input type="date" name="project_date" class="form-control"></div>

            <!-- Section équipe -->
            <div class="bg-[#222] p-6 rounded-lg mb-8 border border-dashed border-[#444]">
                <h3 class="text-[#d4a5d4] text-lg mb-4">👥 Talents déjà sur le projet (Optionnel)</h3>
                <p class="text-xs text-[#888] mb-4">Avez-vous déjà un membre de l'équipe à créditer ?</p>
                <div class="flex gap-2.5 mb-5">
                    <div id="btn-search" class="toggle-btn active flex-1 text-center py-2.5 bg-[#d4a5d4] text-[#111] border border-[#d4a5d4] rounded-md cursor-pointer font-bold text-sm" onclick="switchTeamMode('search')">🔍 Trouver sur ChicBook</div>
                    <div id="btn-manual" class="toggle-btn flex-1 text-center py-2.5 bg-black text-[#888] border border-[#333] rounded-md cursor-pointer text-sm hover:bg-[#222] transition-colors" onclick="switchTeamMode('manual')">✍️ Ajouter manuellement</div>
                </div>
                <div id="mode-search" class="team-search team-active">
                    <div class="form-group" style="margin-bottom:0;"><input type="text" name="search_user" class="form-control" placeholder="Rechercher par nom (ex: Melvin...)"></div>
                </div>
                <div id="mode-manual" class="team-manual">
                    <div class="flex gap-4">
                        <div class="form-group flex-1" style="margin-bottom:0;"><label>Nom complet</label><input type="text" name="manual_name" class="form-control" placeholder="Nom du talent"></div>
                        <div class="form-group flex-1" style="margin-bottom:0;"><label>Mensurations / Détails</label><input type="text" name="manual_measurements" class="form-control" placeholder="Ex: 1m75, Confection 36..."></div>
                    </div>
                </div>
            </div>

            <hr class="border-[#333] my-8">
            <div class="form-group"><label>Nom (par défaut)</label><input type="text" name="contact_name" class="form-control" value="Melvin"></div>
            <div class="flex gap-5">
                <div class="form-group flex-1"><label>E-mail de contact</label><input type="email" name="contact_email" class="form-control" placeholder="email@exemple.com"></div>
                <div class="form-group flex-1"><label>Téléphone (facultatif)</label><input type="tel" name="contact_phone" class="form-control" placeholder="+33 6 ..."></div>
            </div>
            <div class="form-group"><label>Ajoutez un moodboard (Plusieurs photos possibles)</label><input type="file" name="moodboard_photos[]" class="form-control" accept="image/*" multiple style="padding:10px;"></div>

            <button type="submit" class="w-full py-4 mt-5 bg-[#d4a5d4] text-[#111] font-bold text-base border-none rounded-lg cursor-pointer uppercase hover:bg-[#c08bc0] hover:-translate-y-0.5 transition-all">Publier votre projet</button>
        </form>
    </main>

    <script>
        function switchTeamMode(mode) {
            ['search', 'manual'].forEach(m => {
                document.getElementById('btn-' + m).className = 'toggle-btn flex-1 text-center py-2.5 bg-black text-[#888] border border-[#333] rounded-md cursor-pointer text-sm';
                document.getElementById('mode-' + m).classList.remove('team-active');
            });
            document.getElementById('btn-' + mode).classList.add('active');
            document.getElementById('btn-' + mode).style.cssText = 'background:#d4a5d4;color:#111;border-color:#d4a5d4;font-weight:bold;';
            document.getElementById('mode-' + mode).classList.add('team-active');
        }
    </script>
</body>
</html>

