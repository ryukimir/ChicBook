<?php
$current = basename($_SERVER['PHP_SELF']);
$nav = [
    'index.php'        => ['icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label' => 'Dashboard'],
    'utilisateurs.php' => ['icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'label' => 'Utilisateurs'],
    'castings.php'     => ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'label' => 'Castings'],
    'portfolios.php'   => ['icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'label' => 'Portfolios'],
    'evenements.php'   => ['icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'label' => 'Événements'],
];
?>
<aside style="width:220px;min-height:100vh;background:#0a0a0a;border-right:1px solid #1a1a1a;display:flex;flex-direction:column;position:fixed;left:0;top:0;z-index:100;">
    <div style="padding:28px 20px 20px;border-bottom:1px solid #1a1a1a;">
        <div style="font-size:18px;font-weight:800;color:#d4a5d4;letter-spacing:-0.5px;">ChicBook</div>
        <div style="font-size:11px;color:#555;margin-top:2px;font-weight:600;letter-spacing:1px;text-transform:uppercase;">Back Office</div>
    </div>
    <nav style="padding:16px 12px;flex:1;">
        <?php foreach ($nav as $file => $item): ?>
        <a href="<?= $file ?>" style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;text-decoration:none;margin-bottom:4px;color:<?= $current === $file ? '#fff' : '#666' ?>;background:<?= $current === $file ? '#1a1a1a' : 'transparent' ?>;transition:all .15s;">
            <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $item['icon'] ?>"/></svg>
            <span style="font-size:14px;font-weight:<?= $current === $file ? '600' : '400' ?>;"><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <div style="padding:16px 12px;border-top:1px solid #1a1a1a;">
        <a href="../index.php" style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;text-decoration:none;color:#555;transition:all .15s;">
            <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span style="font-size:14px;">Voir le site</span>
        </a>
        <a href="logout.php" style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;text-decoration:none;color:#555;transition:all .15s;">
            <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <span style="font-size:14px;">Déconnexion</span>
        </a>
    </div>
</aside>
