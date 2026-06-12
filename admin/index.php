<?php
session_start();
require_once '../config/database.php';
require_once 'auth_guard.php';

$db = Database::getInstance()->getConnection();

// Stats
$stats = [];
$queries = [
    'users'              => "SELECT COUNT(*) FROM users",
    'castings'           => "SELECT COUNT(*) FROM castings",
    'portfolios'         => "SELECT COUNT(*) FROM portfolios",
    'new_users'          => "SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL '30 days'",
    'projects'           => "SELECT COUNT(*) FROM projects",
    'events'             => "SELECT COUNT(*) FROM events",
    'unread_reports'     => "SELECT COUNT(*) FROM reports WHERE is_read=FALSE",
    'unread_suggestions' => "SELECT COUNT(*) FROM suggestions WHERE is_read=FALSE",
];
foreach ($queries as $key => $sql) {
    $stats[$key] = $db->query($sql)->fetchColumn();
}
$unread_total = $stats['unread_reports'] + $stats['unread_suggestions'];

// Recent users (last 8)
$recent_users = $db->query(
    "SELECT id, full_name, email, specific_profession, created_at FROM users ORDER BY created_at DESC LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);

// Recent castings (last 8)
$recent_castings = $db->query(
    "SELECT c.id, c.company_name, c.city, c.country, c.created_at, u.full_name
     FROM castings c JOIN users u ON u.id = c.user_id
     ORDER BY c.created_at DESC LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);

// Recent signalements non lus
$recent_reports = $db->query(
    "SELECT r.id, r.category, r.message, r.created_at, u.full_name
     FROM reports r LEFT JOIN users u ON u.id=r.user_id
     WHERE r.is_read=FALSE ORDER BY r.created_at DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — Admin ChicBook</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen" style="padding-left:220px;">

<?php include 'sidebar.php'; ?>

<main class="p-8">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-white">Dashboard</h1>
        <p class="text-[#555] text-sm mt-1">Vue d'ensemble de ChicBook</p>
    </div>

    <!-- Stat cards — row 1 -->
    <div class="grid grid-cols-4 gap-4 mb-4">
        <?php
        $cards = [
            ['label' => 'Utilisateurs', 'value' => $stats['users'], 'sub' => '+' . $stats['new_users'] . ' ce mois', 'color' => '#d4a5d4', 'link' => 'utilisateurs.php'],
            ['label' => 'Castings publiés', 'value' => $stats['castings'], 'sub' => 'total', 'color' => '#a5c4d4', 'link' => 'castings.php'],
            ['label' => 'Projets', 'value' => $stats['projects'], 'sub' => 'créés', 'color' => '#a5d4b0', 'link' => 'projets.php'],
            ['label' => 'Événements', 'value' => $stats['events'], 'sub' => 'publiés', 'color' => '#d4c4a5', 'link' => 'evenements.php'],
        ];
        foreach ($cards as $c): ?>
        <a href="<?= $c['link'] ?>" class="bg-[#111] rounded-2xl p-6 border border-[#1a1a1a] hover:border-[#2a2a2a] transition-colors block">
            <div class="text-3xl font-black" style="color:<?= $c['color'] ?>"><?= number_format($c['value']) ?></div>
            <div class="text-white font-semibold text-sm mt-2"><?= $c['label'] ?></div>
            <div class="text-[#444] text-xs mt-1"><?= $c['sub'] ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Stat cards — row 2 -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <a href="signalements.php" class="bg-[#111] rounded-2xl p-6 border <?= $unread_total > 0 ? 'border-red-500/30' : 'border-[#1a1a1a]' ?> hover:border-[#2a2a2a] transition-colors block">
            <div class="text-3xl font-black <?= $unread_total > 0 ? 'text-red-400' : 'text-[#555]' ?>"><?= $unread_total ?></div>
            <div class="text-white font-semibold text-sm mt-2">Signalements non lus</div>
            <div class="text-[#444] text-xs mt-1"><?= $stats['unread_reports'] ?> signalement(s) + <?= $stats['unread_suggestions'] ?> suggestion(s)</div>
        </a>
        <a href="portfolios.php" class="bg-[#111] rounded-2xl p-6 border border-[#1a1a1a] hover:border-[#2a2a2a] transition-colors block">
            <div class="text-3xl font-black" style="color:#c4a5d4"><?= number_format($stats['portfolios']) ?></div>
            <div class="text-white font-semibold text-sm mt-2">Photos book</div>
            <div class="text-[#444] text-xs mt-1">dans les portfolios</div>
        </a>
        <a href="metiers.php" class="bg-[#111] rounded-2xl p-6 border border-[#1a1a1a] hover:border-[#2a2a2a] transition-colors block">
            <div class="text-3xl font-black text-[#888]"><?= $db->query("SELECT COUNT(*) FROM professions")->fetchColumn() ?></div>
            <div class="text-white font-semibold text-sm mt-2">Métiers</div>
            <div class="text-[#444] text-xs mt-1"><?= $db->query("SELECT COUNT(*) FROM profession_categories")->fetchColumn() ?> catégories</div>
        </a>
        <div class="bg-[#111] rounded-2xl p-6 border border-[#1a1a1a]">
            <div class="text-3xl font-black text-[#d4a5d4]"><?= $stats['new_users'] ?></div>
            <div class="text-white font-semibold text-sm mt-2">Nouveaux (30j)</div>
            <div class="text-[#444] text-xs mt-1">inscriptions récentes</div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6">
        <!-- Recent users -->
        <div class="bg-[#111] rounded-2xl border border-[#1a1a1a] overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#1a1a1a]">
                <span class="font-semibold text-sm">Derniers inscrits</span>
                <a href="utilisateurs.php" class="text-brand text-xs hover:underline">Voir tout</a>
            </div>
            <table class="w-full text-sm">
                <tbody>
                <?php foreach ($recent_users as $u): ?>
                <tr class="border-b border-[#161616] hover:bg-[#161616] transition-colors">
                    <td class="px-6 py-3">
                        <div class="font-medium text-white"><?= htmlspecialchars($u['full_name']) ?></div>
                        <div class="text-[#555] text-xs"><?= htmlspecialchars($u['email']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-[#666] text-xs"><?= htmlspecialchars($u['specific_profession'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-[#555] text-xs whitespace-nowrap"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Signalements non lus / castings récents -->
        <?php if (!empty($recent_reports)): ?>
        <div class="bg-[#111] rounded-2xl border border-red-500/20 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#1a1a1a]">
                <span class="font-semibold text-sm flex items-center gap-2">
                    Signalements non lus
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white"><?= $unread_total ?></span>
                </span>
                <a href="signalements.php" class="text-brand text-xs hover:underline">Voir tout</a>
            </div>
            <div class="divide-y divide-[#161616]">
            <?php foreach ($recent_reports as $r): ?>
            <div class="px-6 py-3">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-500/20 text-red-400"><?= htmlspecialchars($r['category']) ?></span>
                    <span class="text-[#555] text-xs"><?= date('d/m H:i', strtotime($r['created_at'])) ?></span>
                    <?php if ($r['full_name']): ?><span class="text-[#666] text-xs">· <?= htmlspecialchars($r['full_name']) ?></span><?php endif; ?>
                </div>
                <p class="text-[#888] text-xs truncate"><?= htmlspecialchars($r['message']) ?></p>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-[#111] rounded-2xl border border-[#1a1a1a] overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#1a1a1a]">
                <span class="font-semibold text-sm">Derniers castings</span>
                <a href="castings.php" class="text-brand text-xs hover:underline">Voir tout</a>
            </div>
            <table class="w-full text-sm">
                <tbody>
                <?php foreach ($recent_castings as $c): ?>
                <tr class="border-b border-[#161616] hover:bg-[#161616] transition-colors">
                    <td class="px-6 py-3">
                        <div class="font-medium text-white"><?= htmlspecialchars($c['company_name'] ?? 'Sans nom') ?></div>
                        <div class="text-[#555] text-xs">par <?= htmlspecialchars($c['full_name']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-[#666] text-xs"><?= htmlspecialchars($c['city'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-[#555] text-xs whitespace-nowrap"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
