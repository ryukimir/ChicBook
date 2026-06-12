<?php
session_start();
require_once '../config/database.php';
require_once 'auth_guard.php';

$db = Database::getInstance()->getConnection();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $event_id = intval($_POST['event_id'] ?? 0);
    if ($action === 'delete' && $event_id) {
        $row = $db->prepare("SELECT cover_image FROM events WHERE id = :id");
        $row->execute([':id' => $event_id]);
        $r = $row->fetch(PDO::FETCH_ASSOC);
        $db->prepare("DELETE FROM events WHERE id = :id")->execute([':id' => $event_id]);
        if ($r && !empty($r['cover_image']) && file_exists('../' . $r['cover_image'])) unlink('../' . $r['cover_image']);
        $message = 'Événement supprimé.';
    }
}

$search = trim($_GET['q'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$per    = 25; $offset = ($page - 1) * $per;

$where = ['1=1']; $params = [];
if ($search !== '') {
    $where[] = "(e.title ILIKE :q OR e.city ILIKE :q OR u.full_name ILIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
$w = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM events e JOIN users u ON u.id=e.user_id WHERE $w");
$total->execute($params); $total = $total->fetchColumn();
$pages = ceil($total / $per);

$stmt = $db->prepare("SELECT e.*, u.full_name FROM events e JOIN users u ON u.id=e.user_id WHERE $w ORDER BY e.event_date DESC LIMIT $per OFFSET $offset");
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"><title>Événements — Admin ChicBook</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{brand:'#d4a5d4'}}}}</script>
</head>
<body class="bg-[#050505] text-white min-h-screen" style="padding-left:220px;">
<?php include 'sidebar.php'; ?>
<main class="p-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Événements</h1>
        <p class="text-[#555] text-sm mt-1"><?= number_format($total) ?> événements publiés</p>
    </div>
    <?php if ($message): ?><div class="mb-4 p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <form method="GET" class="flex gap-3 mb-6">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Titre, ville, auteur…" class="bg-[#111] border border-[#222] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand w-72">
        <button type="submit" class="bg-brand text-black font-bold px-5 py-2.5 rounded-xl text-sm hover:opacity-90">Filtrer</button>
        <?php if ($search): ?><a href="evenements.php" class="px-4 py-2.5 rounded-xl text-sm text-[#666] border border-[#222]">Effacer</a><?php endif; ?>
    </form>

    <div class="bg-[#111] rounded-2xl border border-[#1a1a1a] overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-[#1a1a1a] text-[#555] text-xs uppercase tracking-wider">
                <th class="px-6 py-4 text-left">Événement</th>
                <th class="px-4 py-4 text-left">Créé par</th>
                <th class="px-4 py-4 text-left">Type</th>
                <th class="px-4 py-4 text-left">Date</th>
                <th class="px-4 py-4 text-left">Lieu</th>
                <th class="px-4 py-4 text-right">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($events as $ev): ?>
            <tr class="border-b border-[#161616] hover:bg-[#161616] transition-colors">
                <td class="px-6 py-4"><div class="font-semibold text-white"><?= htmlspecialchars($ev['title']) ?></div><div class="text-[#555] text-xs mt-0.5">#<?= $ev['id'] ?></div></td>
                <td class="px-4 py-4 text-[#888] text-xs"><?= htmlspecialchars($ev['full_name']) ?></td>
                <td class="px-4 py-4"><span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#1a1a1a] text-[#888] border border-[#222]"><?= htmlspecialchars($ev['type'] ?? '—') ?></span></td>
                <td class="px-4 py-4 text-[#555] text-xs"><?= $ev['event_date'] ? date('d/m/Y', strtotime($ev['event_date'])) : '—' ?></td>
                <td class="px-4 py-4 text-[#666] text-xs"><?= htmlspecialchars(trim(($ev['city']??'').', '.($ev['country']??''), ', ') ?: '—') ?></td>
                <td class="px-4 py-4 text-right">
                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer cet événement ?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-red-500/50 hover:text-red-400 transition-colors">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($events)): ?><tr><td colspan="6" class="px-6 py-12 text-center text-[#444]">Aucun événement.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="flex items-center justify-center gap-2 mt-6">
        <?php for ($i=1;$i<=$pages;$i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="w-9 h-9 flex items-center justify-center rounded-lg text-sm <?= $i===$page ? 'bg-brand text-black font-bold' : 'bg-[#111] text-[#666] border border-[#1a1a1a]' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
