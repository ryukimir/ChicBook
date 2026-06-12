<?php
session_start();
require_once '../config/database.php';
require_once 'auth_guard.php';

$db = Database::getInstance()->getConnection();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $casting_id = intval($_POST['casting_id'] ?? 0);

    if ($action === 'delete' && $casting_id) {
        $db->prepare("DELETE FROM castings WHERE id = :id")->execute([':id' => $casting_id]);
        $message = 'Casting supprimé.';
    }
}

$search = trim($_GET['q'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$per    = 25;
$offset = ($page - 1) * $per;

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = "(c.company_name ILIKE :q OR c.city ILIKE :q OR u.full_name ILIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
$whereSQL = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM castings c JOIN users u ON u.id = c.user_id WHERE $whereSQL");
$total->execute($params);
$total = $total->fetchColumn();
$pages = ceil($total / $per);

$stmt = $db->prepare(
    "SELECT c.id, c.company_name, c.city, c.country, c.collaboration_type, c.performance_date, c.created_at,
            u.id AS owner_id, u.full_name
     FROM castings c JOIN users u ON u.id = c.user_id
     WHERE $whereSQL
     ORDER BY c.created_at DESC LIMIT $per OFFSET $offset"
);
$stmt->execute($params);
$castings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Castings — Admin ChicBook</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
</head>
<body class="bg-[#050505] text-white min-h-screen" style="padding-left:220px;">

<?php include 'sidebar.php'; ?>

<main class="p-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Castings</h1>
        <p class="text-[#555] text-sm mt-1"><?= number_format($total) ?> castings publiés</p>
    </div>

    <?php if ($message): ?>
    <div class="mb-4 p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="flex gap-3 mb-6">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Entreprise, ville, auteur…"
            class="bg-[#111] border border-[#222] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand w-72">
        <button type="submit" class="bg-brand text-black font-bold px-5 py-2.5 rounded-xl text-sm hover:opacity-90">Filtrer</button>
        <?php if ($search): ?><a href="castings.php" class="px-4 py-2.5 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Effacer</a><?php endif; ?>
    </form>

    <div class="bg-[#111] rounded-2xl border border-[#1a1a1a] overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[#1a1a1a] text-[#555] text-xs uppercase tracking-wider">
                    <th class="px-6 py-4 text-left font-semibold">Casting</th>
                    <th class="px-4 py-4 text-left font-semibold">Créé par</th>
                    <th class="px-4 py-4 text-left font-semibold">Lieu</th>
                    <th class="px-4 py-4 text-left font-semibold">Type</th>
                    <th class="px-4 py-4 text-left font-semibold">Date réal.</th>
                    <th class="px-4 py-4 text-left font-semibold">Publié le</th>
                    <th class="px-4 py-4 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($castings as $c): ?>
            <tr class="border-b border-[#161616] hover:bg-[#161616] transition-colors">
                <td class="px-6 py-4">
                    <a href="../castings.php" target="_blank" class="font-semibold text-white hover:text-brand">
                        <?= htmlspecialchars($c['company_name'] ?? 'Sans nom') ?>
                    </a>
                    <div class="text-[#555] text-xs mt-0.5">#<?= $c['id'] ?></div>
                </td>
                <td class="px-4 py-4">
                    <a href="../profil.php?id=<?= $c['owner_id'] ?>" target="_blank" class="text-[#888] hover:text-brand text-xs">
                        <?= htmlspecialchars($c['full_name']) ?>
                    </a>
                </td>
                <td class="px-4 py-4 text-[#666] text-xs"><?= htmlspecialchars(trim(($c['city'] ?? '') . ', ' . ($c['country'] ?? ''), ', ') ?: '—') ?></td>
                <td class="px-4 py-4">
                    <?php if ($c['collaboration_type']): ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#1a1a1a] text-[#888] border border-[#222]">
                        <?= htmlspecialchars($c['collaboration_type']) ?>
                    </span>
                    <?php else: ?><span class="text-[#444] text-xs">—</span><?php endif; ?>
                </td>
                <td class="px-4 py-4 text-[#555] text-xs whitespace-nowrap">
                    <?= $c['performance_date'] ? date('d/m/Y', strtotime($c['performance_date'])) : '—' ?>
                </td>
                <td class="px-4 py-4 text-[#555] text-xs whitespace-nowrap"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                <td class="px-4 py-4 text-right">
                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce casting définitivement ?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="casting_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-red-500/50 hover:text-red-400 transition-colors">
                            Supprimer
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($castings)): ?>
            <tr><td colspan="7" class="px-6 py-12 text-center text-[#444]">Aucun casting trouvé.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
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
