<?php
session_start();
require_once '../config/database.php';
require_once 'auth_guard.php';

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Categories
    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $max = $db->query("SELECT COALESCE(MAX(display_order),0)+1 FROM profession_categories")->fetchColumn();
            $db->prepare("INSERT INTO profession_categories (name, display_order) VALUES (:n, :o)")
               ->execute([':n' => $name, ':o' => $max]);
            $message = 'Catégorie ajoutée.';
        } else { $error = 'Nom requis.'; }

    } elseif ($action === 'edit_category') {
        $id   = intval($_POST['id']);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            $db->prepare("UPDATE profession_categories SET name=:n WHERE id=:id")
               ->execute([':n' => $name, ':id' => $id]);
            $message = 'Catégorie modifiée.';
        }

    } elseif ($action === 'delete_category') {
        $id = intval($_POST['id']);
        if ($id) {
            $db->prepare("DELETE FROM profession_categories WHERE id=:id")->execute([':id' => $id]);
            $message = 'Catégorie supprimée.';
        }

    // Professions
    } elseif ($action === 'add_profession') {
        $name    = trim($_POST['name'] ?? '');
        $cat_id  = intval($_POST['category_id'] ?? 0) ?: null;
        $has_m   = isset($_POST['has_measurements']) ? true : false;
        if ($name) {
            $db->prepare("INSERT INTO professions (name, category_id, has_measurements) VALUES (:n, :c, :m)")
               ->execute([':n' => $name, ':c' => $cat_id, ':m' => $has_m ? 'true' : 'false']);
            $message = 'Métier ajouté.';
        } else { $error = 'Nom requis.'; }

    } elseif ($action === 'edit_profession') {
        $id     = intval($_POST['id']);
        $name   = trim($_POST['name'] ?? '');
        $cat_id = intval($_POST['category_id'] ?? 0) ?: null;
        $has_m  = isset($_POST['has_measurements']) ? true : false;
        if ($id && $name) {
            $db->prepare("UPDATE professions SET name=:n, category_id=:c, has_measurements=:m WHERE id=:id")
               ->execute([':n' => $name, ':c' => $cat_id, ':m' => $has_m ? 'true' : 'false', ':id' => $id]);
            $message = 'Métier modifié.';
        }

    } elseif ($action === 'delete_profession') {
        $id = intval($_POST['id']);
        if ($id) {
            $db->prepare("DELETE FROM professions WHERE id=:id")->execute([':id' => $id]);
            $message = 'Métier supprimé.';
        }

    // Tags
    } elseif ($action === 'add_tag') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $max = $db->query("SELECT COALESCE(MAX(display_order),0)+1 FROM expertise_tags_list")->fetchColumn();
            try {
                $db->prepare("INSERT INTO expertise_tags_list (name, display_order) VALUES (:n, :o)")
                   ->execute([':n' => $name, ':o' => $max]);
                $message = 'Tag ajouté.';
            } catch (Exception $e) { $error = 'Ce tag existe déjà.'; }
        } else { $error = 'Nom requis.'; }

    } elseif ($action === 'edit_tag') {
        $id   = intval($_POST['id']);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            try {
                $db->prepare("UPDATE expertise_tags_list SET name=:n WHERE id=:id")
                   ->execute([':n' => $name, ':id' => $id]);
                $message = 'Tag modifié.';
            } catch (Exception $e) { $error = 'Ce tag existe déjà.'; }
        }

    } elseif ($action === 'delete_tag') {
        $id = intval($_POST['id']);
        if ($id) {
            $db->prepare("DELETE FROM expertise_tags_list WHERE id=:id")->execute([':id' => $id]);
            $message = 'Tag supprimé.';
        }
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$categories = $db->query("SELECT * FROM profession_categories ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$tags_list = $db->query("SELECT * FROM expertise_tags_list ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$professions = $db->query("
    SELECT p.*, pc.name AS category_name
    FROM professions p
    LEFT JOIN profession_categories pc ON pc.id = p.category_id
    ORDER BY pc.display_order ASC, pc.name ASC, p.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Group professions by category for display
$by_cat = [];
foreach ($professions as $p) {
    $key = $p['category_id'] ?? 0;
    $by_cat[$key][] = $p;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Métiers — Admin ChicBook</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
</head>
<body class="bg-[#050505] text-white min-h-screen" style="padding-left:220px;">

<?php include 'sidebar.php'; ?>

<main class="p-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Métiers</h1>
        <p class="text-[#555] text-sm mt-1">Gérer les catégories et les professions de la plateforme</p>
    </div>

    <?php if ($message): ?>
    <div class="mb-4 p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-2 gap-6">

        <!-- ── Catégories + Tags ── -->
        <div class="flex flex-col gap-6">
            <div class="bg-[#111] rounded-2xl border border-[#1a1a1a] overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-[#1a1a1a]">
                    <span class="font-semibold text-sm">Catégories <span class="text-[#555] font-normal">(<?= count($categories) ?>)</span></span>
                    <button onclick="document.getElementById('modal-add-cat').classList.remove('hidden')"
                        class="text-xs bg-brand text-black font-bold px-3 py-1.5 rounded-lg hover:opacity-90">+ Ajouter</button>
                </div>
                <table class="w-full text-sm">
                    <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr class="border-b border-[#161616] hover:bg-[#161616] transition-colors">
                        <td class="px-6 py-3 font-medium"><?= htmlspecialchars($cat['name']) ?></td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="openEditCat(<?= $cat['id'] ?>, '<?= addslashes(htmlspecialchars($cat['name'])) ?>')"
                                class="px-3 py-1 rounded-lg text-xs border border-[#222] text-[#666] hover:border-brand/50 hover:text-brand transition-colors mr-1">Modifier</button>
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette catégorie ?')">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="px-3 py-1 rounded-lg text-xs border border-[#222] text-[#666] hover:border-red-500/50 hover:text-red-400 transition-colors">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                    <tr><td colspan="2" class="px-6 py-8 text-center text-[#444]">Aucune catégorie.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ── Tags expertise ── -->
            <div class="bg-[#111] rounded-2xl border border-[#1a1a1a] overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-[#1a1a1a]">
                    <span class="font-semibold text-sm">Tags expertise <span class="text-[#555] font-normal">(<?= count($tags_list) ?>)</span></span>
                    <button onclick="document.getElementById('modal-add-tag').classList.remove('hidden')"
                        class="text-xs bg-brand text-black font-bold px-3 py-1.5 rounded-lg hover:opacity-90">+ Ajouter</button>
                </div>
                <div class="px-6 py-4 flex flex-wrap gap-2">
                    <?php foreach ($tags_list as $t): ?>
                    <div class="flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-[#1a1a1a] border border-[#222] text-[#ccc]">
                        <span><?= htmlspecialchars($t['name']) ?></span>
                        <button onclick="openEditTag(<?= $t['id'] ?>, '<?= addslashes(htmlspecialchars($t['name'])) ?>')"
                            class="ml-1 text-[#555] hover:text-brand transition-colors leading-none" title="Modifier">✎</button>
                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce tag ?')">
                            <input type="hidden" name="action" value="delete_tag">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit" class="text-[#555] hover:text-red-400 transition-colors leading-none ml-0.5" title="Supprimer">✕</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($tags_list)): ?>
                    <p class="text-[#444] text-sm py-4">Aucun tag.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Professions ── -->
        <div>
            <div class="bg-[#111] rounded-2xl border border-[#1a1a1a] overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-[#1a1a1a]">
                    <span class="font-semibold text-sm">Métiers <span class="text-[#555] font-normal">(<?= count($professions) ?>)</span></span>
                    <button onclick="document.getElementById('modal-add-prof').classList.remove('hidden')"
                        class="text-xs bg-brand text-black font-bold px-3 py-1.5 rounded-lg hover:opacity-90">+ Ajouter</button>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#1a1a1a] text-[#555] text-xs uppercase tracking-wider">
                            <th class="px-6 py-3 text-left font-semibold">Nom</th>
                            <th class="px-4 py-3 text-left font-semibold">Catégorie</th>
                            <th class="px-4 py-3 text-center font-semibold">Mensurations</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($professions as $p): ?>
                    <tr class="border-b border-[#161616] hover:bg-[#161616] transition-colors">
                        <td class="px-6 py-3 font-medium"><?= htmlspecialchars($p['name']) ?></td>
                        <td class="px-4 py-3 text-[#666] text-xs"><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($p['has_measurements']): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand/20 text-brand">Oui</span>
                            <?php else: ?>
                                <span class="text-[#444] text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="openEditProf(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>', <?= intval($p['category_id'] ?? 0) ?>, <?= $p['has_measurements'] ? 'true' : 'false' ?>)"
                                class="px-3 py-1 rounded-lg text-xs border border-[#222] text-[#666] hover:border-brand/50 hover:text-brand transition-colors mr-1">Modifier</button>
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce métier ?')">
                                <input type="hidden" name="action" value="delete_profession">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="px-3 py-1 rounded-lg text-xs border border-[#222] text-[#666] hover:border-red-500/50 hover:text-red-400 transition-colors">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($professions)): ?>
                    <tr><td colspan="4" class="px-6 py-8 text-center text-[#444]">Aucun métier.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modal : ajouter catégorie -->
<div id="modal-add-cat" class="hidden fixed inset-0 bg-black/70 z-50 flex items-center justify-center">
    <div class="bg-[#111] rounded-2xl border border-[#222] p-6 w-80">
        <h3 class="font-bold text-lg mb-4">Nouvelle catégorie</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_category">
            <input type="text" name="name" placeholder="Nom de la catégorie" required autofocus
                class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand mb-4">
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="document.getElementById('modal-add-cat').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Annuler</button>
                <button type="submit" class="px-4 py-2 rounded-xl text-sm bg-brand text-black font-bold hover:opacity-90">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal : modifier catégorie -->
<div id="modal-edit-cat" class="hidden fixed inset-0 bg-black/70 z-50 flex items-center justify-center">
    <div class="bg-[#111] rounded-2xl border border-[#222] p-6 w-80">
        <h3 class="font-bold text-lg mb-4">Modifier la catégorie</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" name="id" id="edit-cat-id">
            <input type="text" name="name" id="edit-cat-name" placeholder="Nom" required
                class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand mb-4">
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="document.getElementById('modal-edit-cat').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Annuler</button>
                <button type="submit" class="px-4 py-2 rounded-xl text-sm bg-brand text-black font-bold hover:opacity-90">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal : ajouter profession -->
<div id="modal-add-prof" class="hidden fixed inset-0 bg-black/70 z-50 flex items-center justify-center">
    <div class="bg-[#111] rounded-2xl border border-[#222] p-6 w-96">
        <h3 class="font-bold text-lg mb-4">Nouveau métier</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_profession">
            <input type="text" name="name" placeholder="Nom du métier" required
                class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand mb-3">
            <select name="category_id"
                class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand mb-3">
                <option value="">— Catégorie (optionnel) —</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="flex items-center gap-3 px-1 mb-4 cursor-pointer">
                <input type="checkbox" name="has_measurements" value="1"
                    class="w-4 h-4 rounded accent-brand">
                <span class="text-sm text-[#aaa]">Ce métier a des mensurations</span>
            </label>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="document.getElementById('modal-add-prof').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Annuler</button>
                <button type="submit" class="px-4 py-2 rounded-xl text-sm bg-brand text-black font-bold hover:opacity-90">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal : modifier profession -->
<div id="modal-edit-prof" class="hidden fixed inset-0 bg-black/70 z-50 flex items-center justify-center">
    <div class="bg-[#111] rounded-2xl border border-[#222] p-6 w-96">
        <h3 class="font-bold text-lg mb-4">Modifier le métier</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_profession">
            <input type="hidden" name="id" id="edit-prof-id">
            <input type="text" name="name" id="edit-prof-name" placeholder="Nom" required
                class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand mb-3">
            <select name="category_id" id="edit-prof-cat"
                class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand mb-3">
                <option value="">— Catégorie (optionnel) —</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="flex items-center gap-3 px-1 mb-4 cursor-pointer">
                <input type="checkbox" name="has_measurements" id="edit-prof-meas" value="1"
                    class="w-4 h-4 rounded accent-brand">
                <span class="text-sm text-[#aaa]">Ce métier a des mensurations</span>
            </label>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="document.getElementById('modal-edit-prof').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Annuler</button>
                <button type="submit" class="px-4 py-2 rounded-xl text-sm bg-brand text-black font-bold hover:opacity-90">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal : ajouter tag -->
<div id="modal-add-tag" class="hidden fixed inset-0 bg-black/70 z-50 flex items-center justify-center">
    <div class="bg-[#111] rounded-2xl border border-[#222] p-6 w-80">
        <h3 class="font-bold text-lg mb-4">Nouveau tag</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_tag">
            <input type="text" name="name" placeholder="Nom du tag" required autofocus
                class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand mb-4">
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="document.getElementById('modal-add-tag').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Annuler</button>
                <button type="submit" class="px-4 py-2 rounded-xl text-sm bg-brand text-black font-bold hover:opacity-90">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal : modifier tag -->
<div id="modal-edit-tag" class="hidden fixed inset-0 bg-black/70 z-50 flex items-center justify-center">
    <div class="bg-[#111] rounded-2xl border border-[#222] p-6 w-80">
        <h3 class="font-bold text-lg mb-4">Modifier le tag</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_tag">
            <input type="hidden" name="id" id="edit-tag-id">
            <input type="text" name="name" id="edit-tag-name" placeholder="Nom" required
                class="w-full bg-[#1a1a1a] border border-[#333] rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand mb-4">
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="document.getElementById('modal-edit-tag').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl text-sm text-[#666] border border-[#222] hover:border-[#333]">Annuler</button>
                <button type="submit" class="px-4 py-2 rounded-xl text-sm bg-brand text-black font-bold hover:opacity-90">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCat(id, name) {
    document.getElementById('edit-cat-id').value = id;
    document.getElementById('edit-cat-name').value = name;
    document.getElementById('modal-edit-cat').classList.remove('hidden');
}
function openEditProf(id, name, catId, hasMeas) {
    document.getElementById('edit-prof-id').value = id;
    document.getElementById('edit-prof-name').value = name;
    document.getElementById('edit-prof-cat').value = catId || '';
    document.getElementById('edit-prof-meas').checked = hasMeas;
    document.getElementById('modal-edit-prof').classList.remove('hidden');
}
function openEditTag(id, name) {
    document.getElementById('edit-tag-id').value = id;
    document.getElementById('edit-tag-name').value = name;
    document.getElementById('modal-edit-tag').classList.remove('hidden');
}
// Close modals on backdrop click
['modal-add-cat','modal-edit-cat','modal-add-prof','modal-edit-prof','modal-add-tag','modal-edit-tag'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.add('hidden');
    });
});
</script>

</body>
</html>
