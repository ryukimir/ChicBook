<?php
session_start();
require_once '../config/database.php';
require_once 'auth_guard.php';

$db = Database::getInstance()->getConnection();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $photo_id = intval($_POST['photo_id'] ?? 0);

    if ($action === 'delete' && $photo_id) {
        $photo = $db->prepare("SELECT image_url FROM portfolios WHERE id = :id");
        $photo->execute([':id' => $photo_id]);
        $row = $photo->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $db->prepare("DELETE FROM portfolios WHERE id = :id")->execute([':id' => $photo_id]);
            $path = '../' . $row['image_url'];
            if (file_exists($path)) unlink($path);
            $message = 'Photo supprimée.';
        }
    }
}

$search = trim($_GET['q'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$per    = 30;
$offset = ($page - 1) * $per;

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = "u.full_name ILIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$whereSQL = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM portfolios p JOIN users u ON u.id = p.user_id WHERE $whereSQL");
$total->execute($params);
$total = $total->fetchColumn();
$pages = ceil($total / $per);

$stmt = $db->prepare(
    "SELECT p.id, p.image_url, p.title, p.created_at, u.id AS user_id, u.full_name
     FROM portfolios p JOIN users u ON u.id = p.user_id
     WHERE $whereSQL
     ORDER BY p.created_at DESC LIMIT $per OFFSET $offset"
);
$stmt->execute($params);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Portfolios — Admin ChicBook</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
</head>
<body class="bg-[#050505] text-white min-h-screen" style="padding-left:220px;">

<?php include 'sidebar.php'; ?>

<main class="p-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Portfolios</h1>
        <p class="text-[#555] text-sm mt-1"><?= number_format($total) ?> photos au total</p>
    </div>

    <?php if ($message): ?>
    <div class="mb-4 p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="GET" class="flex gap-3 mb-6">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nom de l'utilisateur…"
            class="bg-[#111] border border-[#222] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand w-64">
        <button type="submit" class="bg-brand text-black font-bold px-5 py-2.5 rounded-xl text-sm hover:opacity-90">Filtrer</button>
        <?php if ($search): ?><a href="portfolios.php" class="px-4 py-2.5 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Effacer</a><?php endif; ?>
    </form>

    <!-- Photo grid -->
    <div class="grid grid-cols-5 gap-3">
    <?php foreach ($photos as $p): ?>
    <div class="group relative bg-[#111] rounded-xl overflow-hidden border border-[#1a1a1a] aspect-square">
        <img src="../<?= htmlspecialchars($p['image_url']) ?>" alt="" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-black/70 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-3">
            <div>
                <a href="../profil.php?id=<?= $p['user_id'] ?>" target="_blank" class="text-xs text-white font-semibold hover:text-brand block truncate">
                    <?= htmlspecialchars($p['full_name']) ?>
                </a>
                <div class="text-[#666] text-[10px] mt-0.5"><?= date('d/m/Y', strtotime($p['created_at'])) ?></div>
            </div>
            <form method="POST" onsubmit="return confirm('Supprimer cette photo ?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
                <button type="submit" class="w-full py-1.5 rounded-lg text-xs font-semibold bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 transition-colors">
                    Supprimer
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($photos)): ?>
    <div class="col-span-5 text-center text-[#444] py-12">Aucune photo trouvée.</div>
    <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="flex items-center justify-center gap-2 mt-6">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
           class="w-9 h-9 flex items-center justify-center rounded-lg text-sm <?= $i === $page ? 'bg-brand text-black font-bold' : 'bg-[#111] text-[#666] border border-[#1a1a1a] hover:border-brand/50' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</main>

</body>
</html>
