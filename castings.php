<?php
session_start();
require_once 'config/database.php';
require_once 'models/user.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$db = Database::getInstance()->getConnection();
$userModel = new User($db);
$current_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_casting_id'])) {
    $delete_id = intval($_POST['delete_casting_id']);
    $stmtDel = $db->prepare("DELETE FROM castings WHERE id = :id AND user_id = :uid");
    $stmtDel->execute(['id' => $delete_id, 'uid' => $current_user_id]);

    header("Location: castings.php?view=mes_castings");
    exit();
}

$user_profile = $userModel->getUserProfile($current_user_id);
$user_profession = $user_profile['profession_name'] ?? 'Inconnu';

$view = isset($_GET['view']) ? $_GET['view'] : 'offres';

$castings = [];

if ($view === 'mes_castings') {

    $stmt = $db->prepare("SELECT * FROM castings WHERE user_id = :uid ORDER BY created_at DESC");
    $stmt->execute(['uid' => $current_user_id]);
    $castings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {

    $stmt = $db->prepare("SELECT DISTINCT c.*, u.full_name as creator_name 
                          FROM castings c 
                          JOIN users u ON c.user_id = u.id 
                          JOIN casting_profiles cp ON c.id = cp.casting_id
                          WHERE cp.role_name = :profession 
                          AND c.user_id != :uid 
                          ORDER BY c.created_at DESC");
    $stmt->execute(['profession' => $user_profession, 'uid' => $current_user_id]);
    $castings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Castings - ChicBook</title>
    <link rel="stylesheet" href="src/style.css">
    <link rel="stylesheet" href="src/castings.css">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="page-container">

        <aside class="filters-sidebar">
            <input type="text" class="search-box" placeholder="🔍 Rechercher des évènements">

            <h2 style="font-size: 24px; margin-bottom: 25px;">Castings</h2>

            <div class="filter-btn">
                <span>Ma localisation</span>
                <span>▼</span>
            </div>

            <div class="filter-btn">
                <span>N'importe quelle date</span>
                <span>▼</span>
            </div>
        </aside>

        <main class="content-main">

            <div class="page-header">
                <div class="tabs">
                    <a href="castings.php?view=offres" class="tab-btn <?= $view === 'offres' ? 'active' : '' ?>">Opportunités pour <?= htmlspecialchars($user_profession) ?></a>
                    <a href="castings.php?view=mes_castings" class="tab-btn <?= $view === 'mes_castings' ? 'active' : '' ?>">Mes Castings créés</a>
                </div>

                <?php if ($view === 'offres'): ?>
                    <a href="creer_casting.php" class="btn-create" style="padding: 10px 20px; font-size: 12px;">+ Créer un casting</a>
                <?php endif; ?>
            </div>

            <?php if (empty($castings)): ?>

                <div class="empty-state">
                    <?php if ($view === 'mes_castings'): ?>
                        <h3>Vous n'avez aucun casting en cours</h3>
                        <p>Publiez votre premier casting pour trouver les meilleurs talents de la plateforme.</p>
                        <a href="creer_casting.php" class="btn-create">Créer mon premier casting</a>
                    <?php else: ?>
                        <h3>Aucune offre pour le moment</h3>
                        <p>Il n'y a actuellement aucun casting recherchant des profils de type <strong><?= htmlspecialchars($user_profession) ?></strong>.<br>Revenez plus tard !</p>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="castings-grid">
                    <?php foreach ($castings as $c): ?>
                        <div class="casting-card">
                            <img src="<?= !empty($c['cover_image']) ? htmlspecialchars($c['cover_image']) : 'https://images.unsplash.com/photo-1542204165-65bf26472b9b?auto=format&fit=crop&w=600&q=80' ?>" alt="Casting" class="card-image">
                            <div class="card-body">
                                <div class="card-date">
                                    Prestation le : <?= date('d/m/Y', strtotime($c['performance_date'])) ?>
                                </div>
                                <h3 class="card-title">
                                    Recherche <?= htmlspecialchars($c['role_sought'] ?? 'Talent') ?>
                                    - <?= htmlspecialchars($c['city'] ?? 'Lieu à définir') ?>
                                </h3>
                                <div class="card-company">
                                    <?= htmlspecialchars($c['company_name'] ?? ($c['creator_name'] ?? 'ChicBook Member')) ?>
                                </div>

                                <p style="color: #bbb; font-size: 13px; line-height: 1.4; margin-bottom: 15px; flex-grow: 1;">
                                    <?= htmlspecialchars(substr($c['description'], 0, 100)) ?>...
                                </p>

                                <div class="card-actions">
                                    <?php if ($view === 'mes_castings'): ?>
                                        <a href="edit_casting.php?id=<?= $c['id'] ?>" class="btn-interest" style="background: #444; display: inline-block; box-sizing: border-box;">Éditer</a>
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce casting ? Cette action est irréversible.');">
                                            <input type="hidden" name="delete_casting_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn-share" style="height: 100%;">🗑️</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn-interest">☆ Ça m'intéresse</button>
                                        <button class="btn-share">↗</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </main>
    </div>
</body>

</html>