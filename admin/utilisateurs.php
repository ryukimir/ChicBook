<?php
session_start();
require_once '../config/database.php';
require_once 'auth_guard.php';

$db = Database::getInstance()->getConnection();

// Actions POST
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($action === 'delete' && $user_id && $user_id !== (int)$_SESSION['user_id']) {
        $db->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $user_id]);
        $message = 'Utilisateur supprimé.';
    } elseif ($action === 'toggle_admin' && $user_id && $user_id !== (int)$_SESSION['user_id']) {
        $db->prepare("UPDATE users SET is_admin = NOT is_admin WHERE id = :id")->execute([':id' => $user_id]);
        $message = 'Droits admin modifiés.';
    } elseif ($action === 'toggle_suspend' && $user_id) {
        $db->prepare("UPDATE users SET is_suspended = NOT is_suspended WHERE id = :id")->execute([':id' => $user_id]);
        $message = 'Statut de suspension modifié.';
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

// Filters
$search  = trim($_GET['q'] ?? '');
$prof    = trim($_GET['profession'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$per     = 25;
$offset  = ($page - 1) * $per;

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = "(full_name ILIKE :q OR email ILIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($prof !== '') {
    $where[] = "specific_profession ILIKE :prof";
    $params[':prof'] = '%' . $prof . '%';
}
$whereSQL = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM users WHERE $whereSQL");
$total->execute($params);
$total = $total->fetchColumn();
$pages = ceil($total / $per);

$stmt = $db->prepare(
    "SELECT id, full_name, email, specific_profession, city, country, created_at, is_admin, is_suspended
     FROM users WHERE $whereSQL ORDER BY created_at DESC LIMIT $per OFFSET $offset"
);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Utilisateurs — Admin ChicBook</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
</head>
<body class="bg-[#050505] text-white min-h-screen" style="padding-left:220px;">

<?php include 'sidebar.php'; ?>

<main class="p-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Utilisateurs</h1>
            <p class="text-[#555] text-sm mt-1"><?= number_format($total) ?> membres au total</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="mb-4 p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="flex gap-3 mb-6">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nom ou email…"
            class="bg-[#111] border border-[#222] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand w-64">
        <input type="text" name="profession" value="<?= htmlspecialchars($prof) ?>" placeholder="Profession…"
            class="bg-[#111] border border-[#222] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand w-48">
        <button type="submit" class="bg-brand text-black font-bold px-5 py-2.5 rounded-xl text-sm hover:opacity-90">Filtrer</button>
        <?php if ($search || $prof): ?>
        <a href="utilisateurs.php" class="px-4 py-2.5 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Effacer</a>
        <?php endif; ?>
    </form>

    <!-- Table -->
    <div class="bg-[#111] rounded-2xl border border-[#1a1a1a] overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[#1a1a1a] text-[#555] text-xs uppercase tracking-wider">
                    <th class="px-6 py-4 text-left font-semibold">Nom</th>
                    <th class="px-4 py-4 text-left font-semibold">Profession</th>
                    <th class="px-4 py-4 text-left font-semibold">Localisation</th>
                    <th class="px-4 py-4 text-left font-semibold">Inscrit le</th>
                    <th class="px-4 py-4 text-left font-semibold">Statut</th>
                    <th class="px-4 py-4 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr class="border-b border-[#161616] hover:bg-[#161616] transition-colors">
                <td class="px-6 py-4">
                    <a href="../profil.php?id=<?= $u['id'] ?>" target="_blank" class="font-semibold text-white hover:text-brand">
                        <?= htmlspecialchars($u['full_name']) ?>
                    </a>
                    <div class="text-[#555] text-xs mt-0.5"><?= htmlspecialchars($u['email']) ?></div>
                </td>
                <td class="px-4 py-4 text-[#888] text-xs"><?= htmlspecialchars($u['specific_profession'] ?? '—') ?></td>
                <td class="px-4 py-4 text-[#666] text-xs"><?= htmlspecialchars(trim(($u['city'] ?? '') . ', ' . ($u['country'] ?? ''), ', ') ?: '—') ?></td>
                <td class="px-4 py-4 text-[#555] text-xs whitespace-nowrap"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                <td class="px-4 py-4">
                    <div class="flex flex-wrap gap-1">
                        <?php if ($u['is_admin']): ?><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand/20 text-brand">Admin</span><?php endif; ?>
                        <?php if ($u['is_suspended']): ?><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-500/20 text-red-400">Suspendu</span><?php else: ?><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-500/20 text-green-400">Actif</span><?php endif; ?>
                    </div>
                </td>
                <td class="px-4 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <!-- Contact -->
                        <button onclick="openContact('<?= addslashes(htmlspecialchars($u['email'])) ?>', '<?= addslashes(htmlspecialchars($u['full_name'])) ?>')"
                            class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-brand/50 hover:text-brand transition-colors">
                            Contacter
                        </button>
                        <!-- Toggle suspend -->
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="toggle_suspend">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-yellow-500/50 hover:text-yellow-400 transition-colors">
                                <?= $u['is_suspended'] ? 'Réactiver' : 'Suspendre' ?>
                            </button>
                        </form>
                        <!-- Toggle admin -->
                        <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="toggle_admin">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-brand/50 hover:text-brand transition-colors">
                                <?= $u['is_admin'] ? 'Retirer admin' : 'Rendre admin' ?>
                            </button>
                        </form>
                        <!-- Delete -->
                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer définitivement cet utilisateur ?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-[#222] text-[#666] hover:border-red-500/50 hover:text-red-400 transition-colors">
                                Supprimer
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
            <tr><td colspan="6" class="px-6 py-12 text-center text-[#444]">Aucun utilisateur trouvé.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal : contacter l'utilisateur -->
    <div id="modal-contact" class="hidden fixed inset-0 bg-black/70 z-50 flex items-center justify-center">
        <div class="bg-[#111] rounded-2xl border border-[#222] p-6 w-[480px]">
            <h3 class="font-bold text-lg mb-4">Contacter l'utilisateur</h3>
            <form method="POST">
                <input type="hidden" name="action" value="send_email">
                <input type="hidden" name="to_email" id="contact-to">
                <div class="mb-3">
                    <label class="text-xs text-[#555] mb-1 block">Destinataire</label>
                    <div id="contact-to-display" class="text-sm text-brand px-4 py-2 bg-[#1a1a1a] rounded-xl border border-[#333]"></div>
                </div>
                <div class="mb-3">
                    <label class="text-xs text-[#555] mb-1 block">Objet</label>
                    <input type="text" name="subject" required
                        class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand">
                </div>
                <div class="mb-4">
                    <label class="text-xs text-[#555] mb-1 block">Message</label>
                    <textarea name="body" rows="5" required
                        class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand resize-none"></textarea>
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" onclick="document.getElementById('modal-contact').classList.add('hidden')"
                        class="px-4 py-2 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Annuler</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-sm bg-brand text-black font-bold hover:opacity-90">Envoyer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openContact(email, name) {
        document.getElementById('contact-to').value = email;
        document.getElementById('contact-to-display').textContent = name + ' <' + email + '>';
        document.getElementById('modal-contact').classList.remove('hidden');
    }
    document.getElementById('modal-contact').addEventListener('click', function(e) {
        if (e.target === this) this.classList.add('hidden');
    });
    </script>

    <!-- Pagination -->
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
