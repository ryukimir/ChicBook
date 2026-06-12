<?php
session_start();
require_once '../config/database.php';
require_once 'auth_guard.php';

$db = Database::getInstance()->getConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id   = intval($_POST['id']);
        $type = $_POST['type'] ?? 'report';
        $table = $type === 'suggestion' ? 'suggestions' : 'reports';
        $db->prepare("UPDATE $table SET is_read=TRUE WHERE id=:id")->execute([':id' => $id]);
        $message = 'Marqué comme lu.';

    } elseif ($action === 'mark_all_read') {
        $type  = $_POST['type'] ?? 'report';
        $table = $type === 'suggestion' ? 'suggestions' : 'reports';
        $db->exec("UPDATE $table SET is_read=TRUE");
        $message = 'Tout marqué comme lu.';

    } elseif ($action === 'delete') {
        $id   = intval($_POST['id']);
        $type = $_POST['type'] ?? 'report';
        $table = $type === 'suggestion' ? 'suggestions' : 'reports';
        $db->prepare("DELETE FROM $table WHERE id=:id")->execute([':id' => $id]);
        $message = 'Supprimé.';

    } elseif ($action === 'send_email') {
        $to      = trim($_POST['to_email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body'] ?? '');
        if ($to && $subject && $body) {
            $headers = "From: admin@chicbook.fr\r\nContent-Type: text/plain; charset=UTF-8";
            mail($to, $subject, $body, $headers);
            $message = 'Email envoyé à ' . htmlspecialchars($to) . '.';
        }
    }
}

$tab = $_GET['tab'] ?? 'signalements';

// Reports
$reports = $db->query("
    SELECT r.*, u.full_name, u.email
    FROM reports r
    LEFT JOIN users u ON u.id = r.user_id
    ORDER BY r.is_read ASC, r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Suggestions
$suggestions = $db->query("
    SELECT s.*, u.full_name, u.email
    FROM suggestions s
    LEFT JOIN users u ON u.id = s.user_id
    ORDER BY s.is_read ASC, s.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$unread_reports     = count(array_filter($reports, fn($r) => !$r['is_read']));
$unread_suggestions = count(array_filter($suggestions, fn($s) => !$s['is_read']));

$category_labels = [
    'bug'     => ['label' => 'Bug', 'color' => '#ef4444'],
    'contenu' => ['label' => 'Contenu', 'color' => '#f97316'],
    'compte'  => ['label' => 'Compte', 'color' => '#eab308'],
    'autre'   => ['label' => 'Autre', 'color' => '#6b7280'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Signalements — Admin ChicBook</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
</head>
<body class="bg-[#050505] text-white min-h-screen" style="padding-left:220px;">

<?php include 'sidebar.php'; ?>

<main class="p-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Signalements & Suggestions</h1>
        <p class="text-[#555] text-sm mt-1">Messages envoyés depuis les préférences utilisateurs</p>
    </div>

    <?php if ($message): ?>
    <div class="mb-4 p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 bg-[#111] border border-[#1a1a1a] rounded-xl p-1 w-fit">
        <a href="?tab=signalements"
            class="px-5 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 <?= $tab === 'signalements' ? 'bg-[#1a1a1a] text-white' : 'text-[#555] hover:text-white' ?>">
            Signalements
            <?php if ($unread_reports > 0): ?>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white"><?= $unread_reports ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=suggestions"
            class="px-5 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 <?= $tab === 'suggestions' ? 'bg-[#1a1a1a] text-white' : 'text-[#555] hover:text-white' ?>">
            Suggestions
            <?php if ($unread_suggestions > 0): ?>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-brand text-black"><?= $unread_suggestions ?></span>
            <?php endif; ?>
        </a>
    </div>

    <?php if ($tab === 'signalements'): ?>
    <!-- ── Signalements ── -->
    <div class="flex items-center justify-between mb-3">
        <span class="text-[#555] text-sm"><?= count($reports) ?> signalement(s), <?= $unread_reports ?> non lu(s)</span>
        <?php if ($unread_reports > 0): ?>
        <form method="POST">
            <input type="hidden" name="action" value="mark_all_read">
            <input type="hidden" name="type" value="report">
            <button type="submit" class="text-xs text-[#666] border border-[#222] px-3 py-1.5 rounded-lg hover:border-brand/50 hover:text-brand transition-colors">Tout marquer comme lu</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="space-y-3">
    <?php foreach ($reports as $r): ?>
    <div class="bg-[#111] rounded-2xl border <?= $r['is_read'] ? 'border-[#1a1a1a]' : 'border-brand/30' ?> p-5">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2 flex-wrap">
                    <?php $cl = $category_labels[$r['category']] ?? $category_labels['autre']; ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" style="background:<?= $cl['color'] ?>22;color:<?= $cl['color'] ?>"><?= $cl['label'] ?></span>
                    <?php if (!$r['is_read']): ?><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand/20 text-brand">Nouveau</span><?php endif; ?>
                    <span class="text-[#555] text-xs"><?= date('d/m/Y à H:i', strtotime($r['created_at'])) ?></span>
                </div>
                <p class="text-sm text-[#ccc] leading-relaxed mb-2"><?= nl2br(htmlspecialchars($r['message'])) ?></p>
                <div class="text-xs text-[#555]">
                    <?php if ($r['full_name']): ?>
                    <span class="text-[#888]"><?= htmlspecialchars($r['full_name']) ?></span>
                    <?php if ($r['email']): ?> · <span><?= htmlspecialchars($r['email']) ?></span><?php endif; ?>
                    <?php else: ?><span>Utilisateur anonyme</span><?php endif; ?>
                </div>
            </div>
            <div class="flex flex-col gap-2 flex-shrink-0">
                <?php if (!$r['is_read']): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="type" value="report">
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-green-500/50 hover:text-green-400 transition-colors whitespace-nowrap">✓ Lu</button>
                </form>
                <?php endif; ?>
                <?php if ($r['email']): ?>
                <button onclick="openReply('<?= addslashes(htmlspecialchars($r['email'])) ?>', '<?= addslashes(htmlspecialchars($r['full_name'] ?? 'l\'utilisateur')) ?>')"
                    class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-brand/50 hover:text-brand transition-colors">Répondre</button>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Supprimer ce signalement ?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="type" value="report">
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-red-500/50 hover:text-red-400 transition-colors">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($reports)): ?>
    <div class="text-center text-[#444] py-12">Aucun signalement.</div>
    <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ── Suggestions ── -->
    <div class="flex items-center justify-between mb-3">
        <span class="text-[#555] text-sm"><?= count($suggestions) ?> suggestion(s), <?= $unread_suggestions ?> non lue(s)</span>
        <?php if ($unread_suggestions > 0): ?>
        <form method="POST">
            <input type="hidden" name="action" value="mark_all_read">
            <input type="hidden" name="type" value="suggestion">
            <button type="submit" class="text-xs text-[#666] border border-[#222] px-3 py-1.5 rounded-lg hover:border-brand/50 hover:text-brand transition-colors">Tout marquer comme lu</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="space-y-3">
    <?php foreach ($suggestions as $s): ?>
    <div class="bg-[#111] rounded-2xl border <?= $s['is_read'] ? 'border-[#1a1a1a]' : 'border-brand/30' ?> p-5">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2">
                    <?php if (!$s['is_read']): ?><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand/20 text-brand">Nouveau</span><?php endif; ?>
                    <span class="text-[#555] text-xs"><?= date('d/m/Y à H:i', strtotime($s['created_at'])) ?></span>
                </div>
                <p class="text-sm text-[#ccc] leading-relaxed mb-2"><?= nl2br(htmlspecialchars($s['message'])) ?></p>
                <div class="text-xs text-[#555]">
                    <?php if ($s['full_name']): ?>
                    <span class="text-[#888]"><?= htmlspecialchars($s['full_name']) ?></span>
                    <?php if ($s['email']): ?> · <span><?= htmlspecialchars($s['email']) ?></span><?php endif; ?>
                    <?php else: ?><span>Utilisateur anonyme</span><?php endif; ?>
                </div>
            </div>
            <div class="flex flex-col gap-2 flex-shrink-0">
                <?php if (!$s['is_read']): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <input type="hidden" name="type" value="suggestion">
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-green-500/50 hover:text-green-400 transition-colors whitespace-nowrap">✓ Lu</button>
                </form>
                <?php endif; ?>
                <?php if ($s['email']): ?>
                <button onclick="openReply('<?= addslashes(htmlspecialchars($s['email'])) ?>', '<?= addslashes(htmlspecialchars($s['full_name'] ?? 'l\'utilisateur')) ?>')"
                    class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-brand/50 hover:text-brand transition-colors">Répondre</button>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Supprimer cette suggestion ?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <input type="hidden" name="type" value="suggestion">
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-red-500/50 hover:text-red-400 transition-colors">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($suggestions)): ?>
    <div class="text-center text-[#444] py-12">Aucune suggestion.</div>
    <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<!-- Modal : répondre par email -->
<div id="modal-reply" class="hidden fixed inset-0 bg-black/70 z-50 flex items-center justify-center">
    <div class="bg-[#111] rounded-2xl border border-[#222] p-6 w-[480px]">
        <h3 class="font-bold text-lg mb-4">Répondre par email</h3>
        <form method="POST">
            <input type="hidden" name="action" value="send_email">
            <input type="hidden" name="to_email" id="reply-to">
            <div class="mb-3">
                <label class="text-xs text-[#555] mb-1 block">Destinataire</label>
                <div id="reply-to-display" class="text-sm text-brand px-4 py-2 bg-[#1a1a1a] rounded-xl border border-[#333]"></div>
            </div>
            <div class="mb-3">
                <label class="text-xs text-[#555] mb-1 block">Objet</label>
                <input type="text" name="subject" id="reply-subject" required
                    class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand">
            </div>
            <div class="mb-4">
                <label class="text-xs text-[#555] mb-1 block">Message</label>
                <textarea name="body" rows="5" required
                    class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand resize-none"></textarea>
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="document.getElementById('modal-reply').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Annuler</button>
                <button type="submit" class="px-4 py-2 rounded-xl text-sm bg-brand text-black font-bold hover:opacity-90">Envoyer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReply(email, name) {
    document.getElementById('reply-to').value = email;
    document.getElementById('reply-to-display').textContent = name + ' <' + email + '>';
    document.getElementById('reply-subject').value = 'Réponse à votre message — ChicBook';
    document.getElementById('modal-reply').classList.remove('hidden');
}
document.getElementById('modal-reply').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>

</body>
</html>
