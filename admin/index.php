<?php
session_start();
require_once '../config/database.php';
require_once 'auth_guard.php';

$db = Database::getInstance()->getConnection();

// Stats
$stats = [];
$queries = [
    'users'     => "SELECT COUNT(*) FROM users",
    'castings'  => "SELECT COUNT(*) FROM castings",
    'portfolios'=> "SELECT COUNT(*) FROM portfolios",
    'new_users' => "SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL '30 days'",
];
foreach ($queries as $key => $sql) {
    $stats[$key] = $db->query($sql)->fetchColumn();
}

// Recent users (last 10)
$recent_users = $db->query(
    "SELECT id, full_name, email, specific_profession, created_at FROM users ORDER BY created_at DESC LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

// Recent castings (last 10)
$recent_castings = $db->query(
    "SELECT c.id, c.company_name, c.city, c.country, c.created_at, u.full_name
     FROM castings c JOIN users u ON u.id = c.user_id
     ORDER BY c.created_at DESC LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
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

    <!-- Stat cards -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <?php
        $cards = [
            ['label' => 'Utilisateurs', 'value' => $stats['users'], 'sub' => '+' . $stats['new_users'] . ' ce mois', 'color' => '#d4a5d4'],
            ['label' => 'Castings publiés', 'value' => $stats['castings'], 'sub' => 'total', 'color' => '#a5c4d4'],
            ['label' => 'Photos book', 'value' => $stats['portfolios'], 'sub' => 'dans les books', 'color' => '#a5d4b0'],
            ['label' => 'Nouveaux (30j)', 'value' => $stats['new_users'], 'sub' => 'inscriptions', 'color' => '#d4c4a5'],
        ];
        foreach ($cards as $c): ?>
        <div class="bg-[#111] rounded-2xl p-6 border border-[#1a1a1a]">
            <div class="text-3xl font-black" style="color:<?= $c['color'] ?>"><?= number_format($c['value']) ?></div>
            <div class="text-white font-semibold text-sm mt-2"><?= $c['label'] ?></div>
            <div class="text-[#444] text-xs mt-1"><?= $c['sub'] ?></div>
        </div>
        <?php endforeach; ?>
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

        <!-- Recent castings -->
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
    </div>
</main>

</body>
</html>
