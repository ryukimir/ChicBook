<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$db = Database::getInstance()->getConnection();
$error = '';

$professions_list   = $db->query("SELECT name FROM professions ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$eye_colors         = $db->query("SELECT id, name FROM eye_colors ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$hair_colors        = $db->query("SELECT id, name FROM hair_colors ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$ethnicities        = $db->query("SELECT id, name FROM ethnicities ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$talent_professions = $db->query("SELECT name FROM professions WHERE has_measurements=TRUE ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title         = trim($_POST['title'] ?? '');
        $project_type  = trim($_POST['project_type'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $expected_date = !empty($_POST['expected_date']) ? $_POST['expected_date'] : null;
        $contact_name  = trim($_POST['contact_name'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');

        // Profils : tableau de cartes
        $profile_cards = $_POST['profiles'] ?? [];
        $all_profs = [];
        foreach ($profile_cards as $pc) {
            $pname = trim($pc['profession'] ?? '');
            if ($pname) $all_profs[] = $pname;
        }
        $searched_profiles = implode(', ', array_unique($all_profs));

        if (!$title) {
            $error = "Le titre est obligatoire.";
        } else {
            $stmt = $db->prepare(
                "INSERT INTO projects (user_id, title, project_type, description, expected_date, searched_profiles, contact_name, contact_email, contact_phone)
                 VALUES (:uid, :title, :type, :desc, :date, :searched, :cname, :cemail, :cphone) RETURNING id"
            );
            $stmt->execute([
                'uid'      => $_SESSION['user_id'],
                'title'    => $title,
                'type'     => $project_type,
                'desc'     => $description,
                'date'     => $expected_date,
                'searched' => $searched_profiles,
                'cname'    => $contact_name,
                'cemail'   => $contact_email,
                'cphone'   => $contact_phone,
            ]);
            $project_id = $stmt->fetchColumn();

            // Insérer chaque carte profil
            $stmt2 = $db->prepare(
                "INSERT INTO required_profiles (project_id, role_name, min_height, max_height, min_age, max_age, chest_size, waist_size, hip_size, shoe_size, eye_color_id, hair_color_id, ethnicity_id)
                 VALUES (:pid, :role, :minh, :maxh, :mina, :maxa, :chest, :waist, :hip, :shoe, :eye, :hair, :eth)"
            );
            foreach ($profile_cards as $pc) {
                $pname = trim($pc['profession'] ?? '');
                if (!$pname) continue;
                $is_talent = in_array($pname, $talent_professions);
                $stmt2->execute([
                    'pid'   => $project_id,
                    'role'  => $pname,
                    'minh'  => $is_talent && !empty($pc['height_min']) ? intval($pc['height_min']) : null,
                    'maxh'  => $is_talent && !empty($pc['height_max']) ? intval($pc['height_max']) : null,
                    'mina'  => $is_talent && !empty($pc['age_min'])    ? intval($pc['age_min'])    : null,
                    'maxa'  => $is_talent && !empty($pc['age_max'])    ? intval($pc['age_max'])    : null,
                    'chest' => $is_talent ? (trim($pc['chest'] ?? '') ?: null) : null,
                    'waist' => $is_talent ? (trim($pc['waist'] ?? '') ?: null) : null,
                    'hip'   => $is_talent ? (trim($pc['hip']   ?? '') ?: null) : null,
                    'shoe'  => $is_talent ? (trim($pc['shoe']  ?? '') ?: null) : null,
                    'eye'   => $is_talent && !empty($pc['eye'])        ? intval($pc['eye'])        : null,
                    'hair'  => $is_talent && !empty($pc['hair'])       ? intval($pc['hair'])       : null,
                    'eth'   => $is_talent && !empty($pc['ethnicity'])  ? intval($pc['ethnicity'])  : null,
                ]);
            }

            header("Location: profil.php");
            exit();
        }
    } catch (Exception $e) {
        $error = "Une erreur est survenue. Veuillez réessayer.";
    }
}

$is_light = (($_COOKIE['chicbook_theme'] ?? 'dark') === 'light');

// Préparer les options HTML pour le select professions (utilisé dans le template JS)
$prof_options = '<option value="">— Choisir un métier —</option>';
foreach ($professions_list as $p) {
    $prof_options .= '<option value="' . htmlspecialchars($p) . '">' . htmlspecialchars($p) . '</option>';
}

$eye_options = '<option value="">Tous</option>';
foreach ($eye_colors as $ec) $eye_options .= '<option value="'.$ec['id'].'">' . htmlspecialchars($ec['name']) . '</option>';

$hair_options = '<option value="">Tous</option>';
foreach ($hair_colors as $hc) $hair_options .= '<option value="'.$hc['id'].'">' . htmlspecialchars($hc['name']) . '</option>';

$eth_options = '<option value="">Toutes</option>';
foreach ($ethnicities as $e) $eth_options .= '<option value="'.$e['id'].'">' . htmlspecialchars($e['name']) . '</option>';
?>
<!doctype html>
<html lang="fr" <?php if ($is_light) echo 'class="light"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau projet — ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        .input-field {
            width: 100%; background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 12px;
            padding: 10px 16px; color: #fff; font-size: 14px; outline: none; transition: border-color .2s;
        }
        .input-field:focus { border-color: #444; }
        .input-field::placeholder { color: #444; }
        html.light .input-field { background: #fff; border-color: #ddd; color: #111; }
        html.light .input-field::placeholder { color: #aaa; }
        @media (max-width: 768px) {
          .cp-wrapper { padding: 16px 12px 100px !important; }
          .cp-grid-2 { grid-template-columns: 1fr !important; }
          .cp-grid-3 { grid-template-columns: 1fr !important; }
          .cp-mensuration-grid { grid-template-columns: 1fr 1fr !important; }
          #mobile-topbar { display: none !important; }
        }
        .label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:#555; margin-bottom:8px; }

        .profile-card { background:#1a1a1a; border:1px solid #2a2a2a; border-radius:16px; padding:20px; position:relative; }
        html.light .profile-card { background:#f5f0eb; border-color:#e0dbd4; }

        .mensuration-section { max-height:0; overflow:hidden; transition:max-height .35s ease, opacity .25s ease; opacity:0; }
        .mensuration-section.open { max-height:700px; opacity:1; }
    </style>
</head>
<body class="bg-black text-white" style="font-family:'Open Sans',sans-serif;">
<?php include 'includes/header.php'; ?>

<div class="max-w-[720px] mx-auto px-6 pt-10 pb-24 cp-wrapper">

    <div class="flex items-center gap-4 mb-8">
        <a href="profil.php" class="w-9 h-9 flex items-center justify-center rounded-full bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] hover:text-white hover:border-[#444] transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold">Nouveau projet</h1>
            <p class="text-[#555] text-sm mt-0.5">Décrivez votre projet et les profils que vous recherchez.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="bg-[#1f1313] border border-[#e05555] text-[#e05555] rounded-xl px-5 py-4 mb-6 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="creer_projet.php">

        <!-- ── Informations principales ── -->
        <div class="bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-[#1a1a1a]">
                <h2 class="font-semibold text-xs uppercase tracking-widest text-[#666]">Informations principales</h2>
            </div>
            <div class="px-6 py-6 flex flex-col gap-5">
                <div>
                    <label class="label">Titre du projet *</label>
                    <input type="text" name="title" class="input-field" placeholder="Ex : Shooting éditorial printemps" maxlength="255" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="label">Type de projet</label>
                    <select name="project_type" class="input-field" style="cursor:pointer;">
                        <option value="">— Sélectionner un type —</option>
                        <?php foreach (['Shooting photo', 'Défilé', 'Court-métrage', 'Clip musical', 'Lookbook', 'Campagne publicitaire', 'Collaboration créative', 'Autre'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($_POST['project_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label">Date prévue</label>
                    <input type="date" name="expected_date" class="input-field" value="<?= htmlspecialchars($_POST['expected_date'] ?? '') ?>">
                </div>
                <div>
                    <label class="label">Description</label>
                    <textarea name="description" rows="5" class="input-field" style="resize:vertical;" placeholder="Décrivez votre projet, le contexte, vos attentes…" maxlength="3000"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- ── Profils recherchés ── -->
        <div class="bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-[#1a1a1a]">
                <h2 class="font-semibold text-xs uppercase tracking-widest text-[#666]">Profils recherchés</h2>
            </div>
            <div class="px-6 py-6">
                <p class="text-[#555] text-sm mb-5">Ajoutez autant de profils que nécessaire. Pour Mannequin, Comédien et Danseur, des critères physiques sont disponibles.</p>

                <div id="profile-cards-list" class="flex flex-col gap-4 mb-4">
                    <!-- Les cartes sont injectées ici par JS au chargement -->
                </div>

                <button type="button" id="add-profile-btn" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-dashed border-[#333] text-[#666] text-sm font-semibold hover:border-[#d4a5d4] hover:text-[#d4a5d4] transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/></svg>
                    Ajouter un profil
                </button>
            </div>
        </div>

        <!-- ── Contact ── -->
        <div class="bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-[#1a1a1a]">
                <h2 class="font-semibold text-xs uppercase tracking-widest text-[#666]">Contact</h2>
            </div>
            <div class="px-6 py-6 flex flex-col gap-5">
                <div>
                    <label class="label">Nom du contact</label>
                    <input type="text" name="contact_name" class="input-field" placeholder="Votre nom ou celui de votre structure" maxlength="255" value="<?= htmlspecialchars($_POST['contact_name'] ?? '') ?>">
                </div>
                <div class="grid grid-cols-2 gap-4 cp-grid-2">
                    <div>
                        <label class="label">Email</label>
                        <input type="email" name="contact_email" class="input-field" placeholder="contact@exemple.fr" value="<?= htmlspecialchars($_POST['contact_email'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="label">Téléphone</label>
                        <input type="tel" name="contact_phone" class="input-field" placeholder="+33 6 00 00 00 00" value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-3 justify-end">
            <a href="profil.php" class="px-6 py-3 rounded-xl bg-[#1a1a1a] border border-[#2a2a2a] text-[#888] text-sm font-semibold hover:text-white hover:border-[#444] transition-all">Annuler</a>
            <button type="submit" class="px-8 py-3 rounded-xl bg-[#d4a5d4] text-black text-sm font-bold hover:opacity-90 transition-opacity">
                Publier le projet
            </button>
        </div>

    </form>
</div>

<script>
const TALENT_PROFESSIONS = <?= json_encode($talent_professions) ?>;
let cardCount = 0;

const PROF_OPTIONS   = <?= json_encode($prof_options) ?>;
const EYE_OPTIONS    = <?= json_encode($eye_options) ?>;
const HAIR_OPTIONS   = <?= json_encode($hair_options) ?>;
const ETH_OPTIONS    = <?= json_encode($eth_options) ?>;

function buildCard(idx) {
    const div = document.createElement('div');
    div.className = 'profile-card';
    div.dataset.idx = idx;

    div.innerHTML = `
        <div class="flex items-center justify-between mb-3">
            <span class="text-[#d4a5d4] text-[10px] font-black uppercase tracking-widest">Profil ${idx + 1}</span>
            <button type="button" class="remove-card w-7 h-7 flex items-center justify-center rounded-full text-[#444] hover:text-[#e05555] hover:bg-[#111] transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="mb-3">
            <label class="block text-xs font-bold uppercase tracking-widest text-[#555] mb-2">Métier</label>
            <select name="profiles[${idx}][profession]" class="input-field profession-select" style="cursor:pointer;">
                ${PROF_OPTIONS}
            </select>
        </div>
        <div class="mensuration-section">
            <div style="border-top:1px solid #333; margin:12px 0 14px;"></div>
            <p class="text-[#d4a5d4] text-[10px] font-black uppercase tracking-widest mb-4">Critères physiques <span style="color:#555;font-weight:400;text-transform:none;">(optionnel)</span></p>
            <div class="grid grid-cols-2 gap-4 mb-4 cp-mensuration-grid">
                <div>
                    <label class="block text-xs text-[#666] font-bold uppercase tracking-widest mb-2">Taille (cm)</label>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="block text-xs text-[#555] mb-1">Min</label><input type="number" name="profiles[${idx}][height_min]" class="input-field" placeholder="160" min="100" max="220"></div>
                        <div><label class="block text-xs text-[#555] mb-1">Max</label><input type="number" name="profiles[${idx}][height_max]" class="input-field" placeholder="185" min="100" max="220"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-[#666] font-bold uppercase tracking-widest mb-2">Âge</label>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="block text-xs text-[#555] mb-1">Min</label><input type="number" name="profiles[${idx}][age_min]" class="input-field" placeholder="18" min="16" max="99"></div>
                        <div><label class="block text-xs text-[#555] mb-1">Max</label><input type="number" name="profiles[${idx}][age_max]" class="input-field" placeholder="35" min="16" max="99"></div>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4 cp-mensuration-grid">
                <div><label class="block text-xs text-[#666] font-bold uppercase tracking-widest mb-2">Poitrine (cm)</label><input type="text" name="profiles[${idx}][chest]" class="input-field" placeholder="Ex: 85 - 95"></div>
                <div><label class="block text-xs text-[#666] font-bold uppercase tracking-widest mb-2">Tour de taille (cm)</label><input type="text" name="profiles[${idx}][waist]" class="input-field" placeholder="Ex: 60 - 70"></div>
                <div><label class="block text-xs text-[#666] font-bold uppercase tracking-widest mb-2">Hanches (cm)</label><input type="text" name="profiles[${idx}][hip]" class="input-field" placeholder="Ex: 88 - 98"></div>
                <div><label class="block text-xs text-[#666] font-bold uppercase tracking-widest mb-2">Pointure</label><input type="text" name="profiles[${idx}][shoe]" class="input-field" placeholder="Ex: 38 - 42"></div>
            </div>
            <div class="grid grid-cols-3 gap-4 cp-grid-3">
                <div><label class="block text-xs text-[#666] font-bold uppercase tracking-widest mb-2">Yeux</label><select name="profiles[${idx}][eye]" class="input-field" style="cursor:pointer;">${EYE_OPTIONS}</select></div>
                <div><label class="block text-xs text-[#666] font-bold uppercase tracking-widest mb-2">Cheveux</label><select name="profiles[${idx}][hair]" class="input-field" style="cursor:pointer;">${HAIR_OPTIONS}</select></div>
                <div><label class="block text-xs text-[#666] font-bold uppercase tracking-widest mb-2">Ethnicité</label><select name="profiles[${idx}][ethnicity]" class="input-field" style="cursor:pointer;">${ETH_OPTIONS}</select></div>
            </div>
        </div>
    `;

    // Listener profession → toggle mensurations
    const sel = div.querySelector('.profession-select');
    const mensSection = div.querySelector('.mensuration-section');
    sel.addEventListener('change', () => {
        const isTalent = TALENT_PROFESSIONS.includes(sel.value);
        mensSection.classList.toggle('open', isTalent);
    });

    // Listener supprimer
    div.querySelector('.remove-card').addEventListener('click', () => {
        div.remove();
        renumberCards();
    });

    return div;
}

function renumberCards() {
    const cards = document.querySelectorAll('#profile-cards-list .profile-card');
    cards.forEach((card, i) => {
        card.querySelector('span').textContent = 'Profil ' + (i + 1);
        // Mettre à jour les attributs name
        card.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/profiles\[\d+\]/, 'profiles[' + i + ']');
        });
    });
}

function addCard() {
    const list = document.getElementById('profile-cards-list');
    const idx = list.children.length;
    list.appendChild(buildCard(idx));
}

document.getElementById('add-profile-btn').addEventListener('click', addCard);

// Ajouter une carte vide au chargement
addCard();
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
