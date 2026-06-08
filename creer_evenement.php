<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$db = Database::getInstance()->getConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cover_image = null;
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
            $filename = 'event_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target)) $cover_image = $target;
        }

        $tags = implode(',', array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))));
        $capacity = !empty($_POST['capacity']) ? intval($_POST['capacity']) : null;
        $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;

        $stmt = $db->prepare(
            "INSERT INTO events (user_id, title, type, organizer, city, country, event_date, cover_image, description, price, capacity, tags)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([
            $_SESSION['user_id'],
            trim($_POST['title']),
            trim($_POST['type']),
            trim($_POST['organizer']),
            trim($_POST['city']),
            trim($_POST['country']),
            $event_date,
            $cover_image,
            trim($_POST['description']),
            trim($_POST['price']),
            $capacity,
            $tags,
        ]);
        $event_id = $stmt->fetchColumn();
        header("Location: evenements.php");
        exit();
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
    }
}

$types = ['Soirée', 'Formation', 'Workshop', 'Salon', 'Défilé', 'Networking', 'Conférence', 'Casting', 'Autre'];
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
<head>
<meta charset="UTF-8">
<title>Proposer un événement — ChicBook</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
<link rel="stylesheet" href="assets/css/custom.css">
<style>
.field label { display:block; font-size:12px; font-weight:700; color:#666; text-transform:uppercase; letter-spacing:.06em; margin-bottom:8px; }
.inp { width:100%; background:#0a0a0a; border:1px solid #222; border-radius:10px; padding:12px 16px; color:#fff; font-size:14px; outline:none; transition:border-color .15s; font-family:inherit; }
.inp:focus { border-color:#d4a5d4; }
.inp::placeholder { color:#444; }
</style>
</head>
<body class="bg-black text-white">
<?php include 'includes/header.php'; ?>

<main class="max-w-[720px] mx-auto mt-10 mb-16 px-6">
    <div class="mb-8">
        <a href="evenements.php" class="text-[#555] text-sm hover:text-brand transition-colors">← Retour aux événements</a>
        <h1 class="text-3xl font-black mt-4 mb-1">Proposer un événement</h1>
        <p class="text-[#555] text-sm">Partagez un événement avec la communauté ChicBook.</p>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-6">

        <div class="field">
            <label>Titre de l'événement *</label>
            <input type="text" name="title" class="inp" placeholder="Ex : Fashion Week Paris — Soirée de lancement" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="field">
                <label>Type *</label>
                <select name="type" class="inp" required style="appearance:none;cursor:pointer;">
                    <option value="" disabled <?= empty($_POST['type']) ? 'selected' : '' ?>>Choisir un type</option>
                    <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>" <?= ($_POST['type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Organisateur *</label>
                <input type="text" name="organizer" class="inp" placeholder="Nom de l'organisateur" required value="<?= htmlspecialchars($_POST['organizer'] ?? '') ?>">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="field">
                <label>Ville *</label>
                <input type="text" name="city" class="inp" placeholder="Paris" required value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Pays *</label>
                <input type="text" name="country" class="inp" placeholder="France" required value="<?= htmlspecialchars($_POST['country'] ?? '') ?>">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="field">
                <label>Date *</label>
                <input type="date" name="event_date" class="inp" required value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Prix</label>
                <input type="text" name="price" class="inp" placeholder="Gratuit / €50 / Sur invitation" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Capacité (places)</label>
                <input type="number" name="capacity" class="inp" placeholder="100" min="1" value="<?= htmlspecialchars($_POST['capacity'] ?? '') ?>">
            </div>
        </div>

        <div class="field">
            <label>Description *</label>
            <textarea name="description" class="inp" rows="5" placeholder="Décrivez l'événement…" required style="resize:vertical;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="field">
            <label>Tags <span class="normal-case text-[#444] font-normal">(séparés par des virgules)</span></label>
            <input type="text" name="tags" class="inp" placeholder="Networking, Fashion Week, Portfolio" value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>">
        </div>

        <div class="field">
            <label>Image de couverture</label>
            <input type="file" name="cover_image" accept="image/*" class="inp" style="padding:8px 16px;cursor:pointer;">
        </div>

        <button type="submit" class="w-full bg-brand text-black font-black py-4 rounded-2xl text-base hover:opacity-90 transition-opacity mt-2">
            Publier l'événement
        </button>
    </form>
</main>

<script src="assets/js/script.js"></script>
</body>
</html>
