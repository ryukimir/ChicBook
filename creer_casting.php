<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$db = Database::getInstance()->getConnection();

$stmtEyes = $db->query("SELECT id, name FROM eye_colors ORDER BY name");
$eyeColors = $stmtEyes->fetchAll(PDO::FETCH_ASSOC);

$stmtHair = $db->query("SELECT id, name FROM hair_colors ORDER BY name");
$hairColors = $stmtHair->fetchAll(PDO::FETCH_ASSOC);

$stmtEthnicities = $db->query("SELECT id, name FROM ethnicities ORDER BY name");
$ethnicities = $stmtEthnicities->fetchAll(PDO::FETCH_ASSOC);

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        $cover_image = null;
        if (isset($_FILES['casting_image']) && $_FILES['casting_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true); 

            $filename = time() . '_' . basename($_FILES['casting_image']['name']);
            $target_file = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['casting_image']['tmp_name'], $target_file)) {
                $cover_image = $target_file;
            }
        }

        $roles_summary = isset($_POST['roles']) ? $_POST['roles'][0] : 'Talent';

        $stmtCasting = $db->prepare("
            INSERT INTO castings (user_id, description, company_name, country, city, role_sought, performance_date, collaboration_type, cover_image) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
        ");

        $stmtCasting->execute([
            $_SESSION['user_id'],
            $_POST['description'],
            $_POST['company_name'] ?: null,
            $_POST['country'],
            $_POST['city'],
            $roles_summary,
            $_POST['performance_date'] ?: null,
            $_POST['collaboration_type'],
            $cover_image 

        ]);

        $casting_id = $stmtCasting->fetchColumn();

        if (isset($_POST['roles'])) {
            $stmtProfile = $db->prepare("
                INSERT INTO casting_profiles 
                (casting_id, role_name, quantity, age_range, gender, eye_color_id, hair_color_id, ethnicity_id, height, shoe_size, waist_size, hip_size, chest_size, cup_size) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($_POST['roles'] as $index => $role) {

                $eye = !empty($_POST['eye_colors'][$index]) ? $_POST['eye_colors'][$index] : null;
                $hair = !empty($_POST['hair_colors'][$index]) ? $_POST['hair_colors'][$index] : null;
                $eth = !empty($_POST['ethnicities'][$index]) ? $_POST['ethnicities'][$index] : null;

                $stmtProfile->execute([
                    $casting_id,
                    $role,
                    $_POST['quantities'][$index] ?: 1,
                    $_POST['age_ranges'][$index] ?: null,
                    $_POST['genders'][$index] ?: null,
                    $eye,
                    $hair,
                    $eth,
                    $_POST['heights'][$index] ?: null,
                    $_POST['shoes'][$index] ?: null,
                    $_POST['waists'][$index] ?: null,
                    $_POST['hips'][$index] ?: null,
                    $_POST['chests'][$index] ?: null,
                    $_POST['cups'][$index] ?: null
                ]);
            }
        }

        $db->commit();
        $message = "Boum ! Votre casting et tous les profils recherchés ont été enregistrés avec succès ! 🎉";
    } catch (Exception $e) {
        $db->rollBack();

        $message = "Oups, petite erreur technique : " . $e->getMessage();
    }
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
        <main class="casting-main">
            <div class="casting-header">
                <h1>POSTER VOTRE CASTING</h1>
                <p>Définissez précisément les talents dont vous avez besoin.</p>
            </div>

            <?php if ($message): ?>
                <div style="background: rgba(46, 125, 50, 0.2); color: #81c784; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #2e7d32; text-align: center;">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form action="creer_casting.php" method="POST" enctype="multipart/form-data">

                <hr style="border-color: #333; margin: 30px 0;">
                <div class="form-group">
                    <label>Image d'illustration (Optionnel)</label>
                    <input type="file" name="casting_image" class="form-control" accept="image/*" style="padding: 10px;">
                </div>

                <div class="form-group">
                    <label>Description globale du projet *</label>
                    <textarea name="description" class="form-control" placeholder="Décrivez le contexte du projet..." required></textarea>
                </div>

                <hr style="border-color: #333; margin: 30px 0;">
                <h3 style="margin-bottom: 20px;">👥 Profils recherchés</h3>

                <div id="profiles-container">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h4>Profil #1</h4>
                        </div>

                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 2;">
                                <label>Rôle recherché</label>
                                <select name="roles[]" class="form-control role-selector" required>
                                    <option value="">Sélectionnez un rôle</option>
                                    <option value="Mannequin">Mannequin</option>
                                    <option value="Comédien">Comédien</option>
                                    <option value="Danseur">Danseur</option>
                                    <option value="Photographe">Photographe</option>
                                    <option value="Vidéaste">Vidéaste</option>
                                    <option value="Maquilleur">Maquilleur</option>
                                    <option value="Styliste">Styliste</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Quantité</label>
                                <input type="number" name="quantities[]" class="form-control" value="1" min="1" required>
                            </div>
                        </div>

                        <div class="mensurations-grid">
                            <div class="form-group" style="grid-column: span 2;">
                                <label>Tranche d'âge & Genre</label>
                                <div style="display: flex; gap: 15px;">
                                    <select name="age_ranges[]" class="form-control">
                                        <option value="">Âge (Peu importe)</option>
                                        <option value="Moins de 18 ans">Moins de 18 ans</option>
                                        <option value="18 - 25 ans">18 - 25 ans</option>
                                        <option value="26 - 35 ans">26 - 35 ans</option>
                                        <option value="36 ans et +">36 ans et +</option>
                                    </select>
                                    <select name="genders[]" class="form-control">
                                        <option value="">Genre (Peu importe)</option>
                                        <option value="Femme">Femme</option>
                                        <option value="Homme">Homme</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Couleur des yeux</label>
                                <select name="eye_colors[]" class="form-control">
                                    <option value="">Peu importe</option>
                                    <?php foreach ($eyeColors as $color): ?>
                                        <option value="<?= $color['id'] ?>"><?= htmlspecialchars($color['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Couleur des cheveux</label>
                                <select name="hair_colors[]" class="form-control">
                                    <option value="">Peu importe</option>
                                    <?php foreach ($hairColors as $hair): ?>
                                        <option value="<?= $hair['id'] ?>"><?= htmlspecialchars($hair['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label>Origine / Ethnie</label>
                                <select name="ethnicities[]" class="form-control">
                                    <option value="">Peu importe</option>
                                    <?php foreach ($ethnicities as $eth): ?>
                                        <option value="<?= $eth['id'] ?>"><?= htmlspecialchars($eth['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group"><label>Taille (hauteur cm)</label><input type="text" name="heights[]" class="form-control" placeholder="Ex: 175, Peu importe"></div>
                            <div class="form-group"><label>Pointure</label><input type="text" name="shoes[]" class="form-control" placeholder="Ex: 39, Peu importe"></div>

                            <div class="form-group"><label>Tour de taille (cm)</label><input type="text" name="waists[]" class="form-control" placeholder="Ex: 64, Peu importe"></div>
                            <div class="form-group"><label>Tour de hanches (cm)</label><input type="text" name="hips[]" class="form-control" placeholder="Ex: 92, Peu importe"></div>

                            <div class="form-group"><label>Tour de poitrine</label><input type="text" name="chests[]" class="form-control" placeholder="Ex: 85, Peu importe"></div>
                            <div class="form-group"><label>Bonnet</label><input type="text" name="cups[]" class="form-control" placeholder="Ex: B, C, Peu importe"></div>
                        </div>
                    </div>
                </div>

                <button type="button" id="btn-add-profile" class="btn-add-profile">+ Ajouter un autre profil recherché</button>

                <hr style="border-color: #333; margin: 30px 0;">

                <div class="form-group">
                    <label>Nom / Entreprise</label>
                    <input type="text" name="company_name" class="form-control" placeholder="(Par défaut)">
                </div>

                <div style="display: flex; gap: 20px;">
                    <div class="form-group autocomplete-wrapper" style="flex: 1;">
                        <label>Ville</label>
                        <input type="text" name="city" id="ville-input" class="form-control" autocomplete="off" placeholder="Commencez à taper...">
                        <ul id="ville-suggestions" class="suggestions-list" style="background: #1a1a1a; border: 1px solid #444; color: white;"></ul>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Pays</label>
                        <select name="country" id="pays-select" class="form-control">
                            <option value="" disabled selected>Sélectionnez un pays</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="width: 50%;">
                    <label>Date de la prestation</label>
                    <input type="date" name="performance_date" class="form-control">
                </div>

                <div class="collaboration-box">
                    <h4 style="color: white; margin-bottom: 15px;">Type de collaboration</h4>
                    <div class="radio-group" style="justify-content: center;">
                        <label class="radio-label"><input type="radio" name="collaboration_type" value="Payé" required> Payé</label>
                        <label class="radio-label"><input type="radio" name="collaboration_type" value="Échange / Troc" required> Échange / Troc</label>
                        <label class="radio-label"><input type="radio" name="collaboration_type" value="Non rémunéré" required> Non rémunéré</label>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Publier le casting</button>
            </form>
        </main>

        <aside class="casting-sidebar">
            <div class="info-card">
                <h3>Image & Production</h3>
                <p>Créer votre casting<br>Gagner du temps</p>
            </div>
        </aside>
    </div>

    <script src="creer_casting.js"></script>
</body>

</html>