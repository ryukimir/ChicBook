<?php
session_start();
require_once 'config/Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = "Le casting a été publié avec succès !";
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer un casting - ChicBook</title>
    <link rel="stylesheet" href="src/style.css">
    <link rel="stylesheet" href="src/creer_casting.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="casting-container">
        
        <!-- COLONNE PRINCIPALE : LE FORMULAIRE -->
        <main class="casting-main">
            <div class="casting-header">
                <h1>POSTER VOTRE CASTING</h1>
                <p>Vous pouvez publier un casting en 2 minutes.<br>Les détails pourront être complétés plus tard.</p>
            </div>

            <?php if($message): ?>
                <div style="background: rgba(46, 125, 50, 0.2); color: #81c784; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #2e7d32; text-align: center;">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form action="creer_casting.php" method="POST">
                
                <div class="form-group">
                    <label>Description du casting * <span style="font-weight: normal; color: #888; font-size: 12px; float: right;">max. 500 caractères</span></label>
                    <textarea name="description" class="form-control" placeholder="Décrivez le contexte du projet (défilé, campagne, éditorial, contenu digital, etc.)" maxlength="500" required></textarea>
                </div>

                <div class="form-group">
                    <label>Sélecteur de rôles recherchés</label>
                    <select name="role_sought" class="form-control">
                        <option value="">Rechercher</option>
                        <option value="Mannequin">Mannequin</option>
                        <option value="Photographe">Photographe</option>
                        <option value="Maquilleur">Maquilleur</option>
                        <option value="Danseur">Danseur</option>
                        <option value="Comédien">Comédien</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nom / Entreprise</label>
                    <input type="text" name="company_name" class="form-control" placeholder="(Par défaut)">
                </div>

                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Pays</label>
                        <input type="text" name="country" class="form-control">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Ville</label>
                        <input type="text" name="city" class="form-control">
                    </div>
                </div>

                <hr style="border-color: #333; margin: 30px 0;">

                <div class="form-group" style="width: 50%;">
                    <label>Date de la prestation</label>
                    <input type="date" name="performance_date" class="form-control">
                </div>

                <div class="form-group">
                    <label>Durée :</label>
                    <div class="radio-group">
                        <label class="radio-label"><input type="radio" name="duration" value="Quelques heures"> Quelques heures</label>
                        <label class="radio-label"><input type="radio" name="duration" value="1 jour"> 1 jour</label>
                        <label class="radio-label"><input type="radio" name="duration" value="Plusieurs jours"> Plusieurs jours</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Lieu</label>
                    <input type="text" name="location" class="form-control" placeholder="Adresse ou lieu précis">
                </div>

                <div class="collaboration-box">
                    <h4>Type de collaboration</h4>
                    <div class="radio-group">
                        <label class="radio-label"><input type="radio" name="collaboration_type" value="Payé" required> Payé</label>
                        <label class="radio-label"><input type="radio" name="collaboration_type" value="Échange / Troc" required> Échange / Troc</label>
                        <label class="radio-label"><input type="radio" name="collaboration_type" value="Non rémunéré" required> Non rémunéré</label>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Publier le casting</button>
            </form>
        </main>

        <!-- COLONNE LATÉRALE : ENCART D'INFO -->
        <aside class="casting-sidebar">
            <div class="info-card">
                <h3>Image & Production</h3>
                <p>Créer votre casting<br>Gagner du temps</p>
            </div>
        </aside>

    </div>
</body>
</html>