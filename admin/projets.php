<?php
session_start();
require_once '../config/database.php';
require_once 'auth_guard.php';

$db = Database::getInstance()->getConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = intval($_POST['project_id']);
    if ($id) {
        $db->prepare("DELETE FROM projects WHERE id=:id")->execute([':id' => $id]);
        $message = 'Projet supprimé.';
    }
}

$search = trim($_GET['q'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$per    = 25;
$offset = ($page - 1) * $per;

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[]     = "(p.title ILIKE :q OR u.full_name ILIKE :q OR p.project_type ILIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
$whereSQL = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM projects p JOIN users u ON u.id=p.user_id WHERE $whereSQL");
$total->execute($params);
$total = $total->fetchColumn();
$pages = ceil($total / $per);

$stmt = $db->prepare("
    SELECT p.id, p.title, p.project_type, p.expected_date, p.searched_profiles, p.created_at,
           u.id AS user_id, u.full_name, u.email,
           (SELECT COUNT(*) FROM required_profiles rp WHERE rp.project_id=p.id) AS profile_count
    FROM projects p
    JOIN users u ON u.id = p.user_id
    WHERE $whereSQL
    ORDER BY p.created_at DESC
    LIMIT $per OFFSET $offset
");
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Projets — Admin ChicBook</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
</head>
<body class="bg-[#050505] text-white min-h-screen" style="padding-left:220px;">

<?php include 'sidebar.php'; ?>

<main class="p-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Projets</h1>
            <p class="text-[#555] text-sm mt-1"><?= number_format($total) ?> projet(s) au total</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="mb-4 p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="flex gap-3 mb-6">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Titre, auteur, type…"
            class="bg-[#111] border border-[#222] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand w-72">
        <button type="submit" class="bg-brand text-black font-bold px-5 py-2.5 rounded-xl text-sm hover:opacity-90">Filtrer</button>
        <?php if ($search): ?>
        <a href="projets.php" class="px-4 py-2.5 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Effacer</a>
        <?php endif; ?>
    </form>

    <div class="bg-[#111] rounded-2xl border border-[#1a1a1a] overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[#1a1a1a] text-[#555] text-xs uppercase tracking-wider">
                    <th class="px-6 py-4 text-left font-semibold">Projet</th>
                    <th class="px-4 py-4 text-left font-semibold">Auteur</th>
                    <th class="px-4 py-4 text-left font-semibold">Type</th>
                    <th class="px-4 py-4 text-left font-semibold">Profils</th>
                    <th class="px-4 py-4 text-left font-semibold">Date prévue</th>
                    <th class="px-4 py-4 text-left font-semibold">Créé le</th>
                    <th class="px-4 py-4 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $p): ?>
            <tr class="border-b border-[#161616] hover:bg-[#161616] transition-colors">
                <td class="px-6 py-4">
                    <a href="../profil.php?id=<?= $p['user_id'] ?>" target="_blank" class="font-semibold text-white hover:text-brand">
                        <?= htmlspecialchars($p['title']) ?>
                    </a>
                    <?php if ($p['searched_profiles']): ?>
                    <div class="text-[#555] text-xs mt-0.5"><?= htmlspecialchars($p['searched_profiles']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-4">
                    <a href="../profil.php?id=<?= $p['user_id'] ?>" target="_blank" class="text-[#888] text-xs hover:text-brand">
                        <?= htmlspecialchars($p['full_name']) ?>
                    </a>
                    <div class="text-[#555] text-xs"><?= htmlspecialchars($p['email']) ?></div>
                </td>
                <td class="px-4 py-4 text-[#666] text-xs"><?= htmlspecialchars($p['project_type'] ?? '—') ?></td>
                <td class="px-4 py-4 text-center">
                    <span class="px-2 py-0.5 rounded-full text-[11px] bg-[#222] text-[#888]"><?= $p['profile_count'] ?></span>
                </td>
                <td class="px-4 py-4 text-[#555] text-xs whitespace-nowrap">
                    <?= $p['expected_date'] ? date('d/m/Y', strtotime($p['expected_date'])) : '—' ?>
                </td>
                <td class="px-4 py-4 text-[#555] text-xs whitespace-nowrap"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                <td class="px-4 py-4 text-right">
                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce projet ?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-red-500/50 hover:text-red-400 transition-colors">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($projects)): ?>
            <tr><td colspan="7" class="px-6 py-12 text-center text-[#444]">Aucun projet trouvé.</td></tr>
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
