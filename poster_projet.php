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
    <link rel="stylesheet" href="src/style.css">
    <link rel="stylesheet" href="src/poster_projet.css">
    
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="post-project-container">
        <h1>Poster un projet</h1>
        <p class="subtitle">Vous avez un besoin créatif ? Trouvez les bons talents.<br>Un projet est une collaboration créative dans la durée.</p>

        <?php if($message): ?>
            <div style="background: rgba(46, 125, 50, 0.2); color: #81c784; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #2e7d32; text-align: center;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form action="poster_projet.php" method="POST" enctype="multipart/form-data">
            
            <div class="form-group">
                <label>Nom du projet</label>
                <input type="text" name="title" class="form-control" placeholder="Ex: Shooting Éditorial Été 2026" required>
            </div>

            <div style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label>Type de projet</label>
                    <select name="project_type" class="form-control" required>
                        <option value="">Sélectionnez un type</option>
                        <option value="Shooting Photo">Shooting Photo</option>
                        <option value="Défilé / Runway">Défilé / Runway</option>
                        <option value="Campagne Vidéo">Campagne Vidéo</option>
                        <option value="Lookbook">Lookbook</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Profils recherchés</label>
                    <select name="searched_profiles" class="form-control" required>
                        <option value="">Sélectionnez un profil</option>
                        <option value="Mannequin">Mannequin</option>
                        <option value="Photographe">Photographe</option>
                        <option value="Danseur">Danseur</option>
                        <option value="Maquilleur">Maquilleur</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Décrivez l'objectif du projet, sa vision et les étapes prévues.</label>
                <textarea name="description" class="form-control" placeholder="Décrivez votre projet en détail..." required></textarea>
            </div>

            <div class="form-group" style="width: 50%;">
                <label>Date prévue</label>
                <input type="date" name="project_date" class="form-control">
            </div>

            <!-- TON IDÉE : SECTION ÉQUIPE DÉJÀ EN PLACE -->
            <div class="team-section">
                <h3>👥 Talents déjà sur le projet (Optionnel)</h3>
                <p style="font-size: 13px; color: #888; margin-bottom: 15px;">Avez-vous déjà un membre de l'équipe à créditer ?</p>
                
                <div class="toggle-btn-group">
                    <div class="toggle-btn active" onclick="switchTeamMode('search')" id="btn-search">🔍 Trouver sur ChicBook</div>
                    <div class="toggle-btn" onclick="switchTeamMode('manual')" id="btn-manual">✍️ Ajouter manuellement</div>
                </div>

                <!-- Mode Recherche ChicBook -->
                <div id="mode-search" class="team-search team-active">
                    <div class="form-group" style="margin-bottom: 0;">
                        <input type="text" name="search_user" class="form-control" placeholder="Rechercher par nom (ex: Melvin...)">
                    </div>
                </div>

                <!-- Mode Ajout Manuel (Nom + Mensurations) -->
                <div id="mode-manual" class="team-manual">
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label>Nom complet</label>
                            <input type="text" name="manual_name" class="form-control" placeholder="Nom du talent">
                        </div>
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label>Mensurations / Détails</label>
                            <input type="text" name="manual_measurements" class="form-control" placeholder="Ex: 1m75, Confection 36...">
                        </div>
                    </div>
                </div>
            </div>
            <!-- FIN DE TA SECTION -->

            <hr style="border-color: #333; margin: 30px 0;">

            <div class="form-group">
                <label>Nom (par défaut)</label>
                <input type="text" name="contact_name" class="form-control" value="Melvin">
            </div>

            <div style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label>E-mail de contact</label>
                    <input type="email" name="contact_email" class="form-control" placeholder="email@exemple.com">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Téléphone (facultatif)</label>
                    <input type="tel" name="contact_phone" class="form-control" placeholder="+33 6 ...">
                </div>
            </div>

            <div class="form-group">
                <label>Ajoutez un moodboard (Plusieurs photos possibles)</label>
                <!-- Le paramètre "multiple" permet de sélectionner plusieurs images ! -->
                <input type="file" name="moodboard_photos[]" class="form-control" accept="image/*" multiple style="padding: 10px;">
            </div>

            <button type="submit" class="btn-submit">Publier votre projet</button>
        </form>
    </main>

    <script>
        function switchTeamMode(mode) {
            document.getElementById('btn-search').classList.remove('active');
            document.getElementById('btn-manual').classList.remove('active');
            document.getElementById('mode-search').classList.remove('team-active');
            document.getElementById('mode-manual').classList.remove('team-active');

            document.getElementById('btn-' + mode).classList.add('active');
            document.getElementById('mode-' + mode).classList.add('team-active');
        }
    </script>
</body>
</html>