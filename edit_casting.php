<?php
session_start();
require_once 'config/Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$db = Database::getInstance()->getConnection();
$message = "";

if (!isset($_GET['id'])) {
    die("ID du casting manquant.");
}
$casting_id = intval($_GET['id']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmtUpdate = $db->prepare("
        UPDATE castings 
        SET description = ?, company_name = ?, country = ?, city = ?, performance_date = ?, collaboration_type = ?
        WHERE id = ? AND user_id = ?
    ");
    
    $stmtUpdate->execute([
        $_POST['description'],
        $_POST['company_name'] ?: null,
        $_POST['country'],
        $_POST['city'],
        $_POST['performance_date'] ?: null,
        $_POST['collaboration_type'],
        $casting_id,
        $_SESSION['user_id']
    ]);
    
    $message = "Le casting a été mis à jour avec succès !";
}

$stmt = $db->prepare("SELECT * FROM castings WHERE id = ? AND user_id = ?");
$stmt->execute([$casting_id, $_SESSION['user_id']]);
$casting = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$casting) {
    die("Casting introuvable ou vous n'avez pas l'autorisation de le modifier.");
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Éditer un casting - ChicBook</title>
    <link rel="stylesheet" href="src/style.css">
    <link rel="stylesheet" href="src/edit_casting.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="casting-container">
        <h1 style="text-align: center; margin-bottom: 30px;">ÉDITER LE CASTING</h1>
        
        <?php if($message): ?>
            <div style="background: rgba(46, 125, 50, 0.2); color: #81c784; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #2e7d32; text-align: center;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form action="edit_casting.php?id=<?= $casting_id ?>" method="POST">
            
            <div class="form-group">
                <label>Description globale du projet *</label>
                <textarea name="description" class="form-control" required><?= htmlspecialchars($casting['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label>Nom / Entreprise</label>
                <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($casting['company_name'] ?? '') ?>">
            </div>

            <div style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;"><label>Pays</label><input type="text" name="country" class="form-control" value="<?= htmlspecialchars($casting['country'] ?? '') ?>"></div>
                <div class="form-group" style="flex: 1;"><label>Ville</label><input type="text" name="city" class="form-control" value="<?= htmlspecialchars($casting['city'] ?? '') ?>"></div>
            </div>

            <div class="form-group" style="width: 50%;">
                <label>Date de la prestation</label>
                <input type="date" name="performance_date" class="form-control" value="<?= htmlspecialchars($casting['performance_date'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Type de collaboration</label>
                <div class="radio-group">
                    <label class="radio-label"><input type="radio" name="collaboration_type" value="Payé" <?= $casting['collaboration_type'] === 'Payé' ? 'checked' : '' ?> required> Payé</label>
                    <label class="radio-label"><input type="radio" name="collaboration_type" value="Échange / Troc" <?= $casting['collaboration_type'] === 'Échange / Troc' ? 'checked' : '' ?> required> Échange / Troc</label>
                    <label class="radio-label"><input type="radio" name="collaboration_type" value="Non rémunéré" <?= $casting['collaboration_type'] === 'Non rémunéré' ? 'checked' : '' ?> required> Non rémunéré</label>
                </div>
            </div>

            <button type="submit" class="btn-submit">Enregistrer les modifications</button>
            <div style="text-align: center; margin-top: 15px;">
                <a href="castings.php?view=mes_castings" style="color: #888; font-size: 14px;">← Retour à mes castings</a>
            </div>
        </form>
    </div>
</body>
</html>