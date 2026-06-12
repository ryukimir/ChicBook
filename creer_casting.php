<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$db = Database::getInstance()->getConnection();
$eyeColors          = $db->query("SELECT id, name FROM eye_colors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$hairColors         = $db->query("SELECT id, name FROM hair_colors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$ethnicities        = $db->query("SELECT id, name FROM ethnicities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$professions_list   = $db->query("SELECT name FROM professions ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$talent_professions = $db->query("SELECT name FROM professions WHERE has_measurements=TRUE ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();
        $cover_image = null;
        if (isset($_FILES['casting_image']) && $_FILES['casting_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . basename($_FILES['casting_image']['name']);
            $target_file = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['casting_image']['tmp_name'], $target_file)) $cover_image = $target_file;
        }
        $roles_summary = isset($_POST['roles']) ? $_POST['roles'][0] : 'Talent';
        $stmtCasting = $db->prepare("INSERT INTO castings (user_id, description, company_name, country, city, role_sought, performance_date, casting_date, collaboration_type, cover_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
        $stmtCasting->execute([$_SESSION['user_id'], $_POST['description'], $_POST['company_name'] ?: null, $_POST['country'], $_POST['city'], $roles_summary, $_POST['performance_date'] ?: null, $_POST['casting_date'] ?: null, $_POST['collaboration_type'], $cover_image]);
        $casting_id = $stmtCasting->fetchColumn();
        if (isset($_POST['roles'])) {
            $stmtProfile = $db->prepare("INSERT INTO casting_profiles (casting_id, role_name, quantity, age_range, gender, eye_color_id, hair_color_id, ethnicity_id, height, shoe_size, waist_size, hip_size, chest_size, cup_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['roles'] as $index => $role) {
                // Build range strings
                $height = buildRange($_POST['heights_min'][$index] ?? '', $_POST['heights_max'][$index] ?? '');
                $shoe   = buildRange($_POST['shoes_min'][$index] ?? '', $_POST['shoes_max'][$index] ?? '');
                $waist  = buildRange($_POST['waists_min'][$index] ?? '', $_POST['waists_max'][$index] ?? '');
                $hip    = buildRange($_POST['hips_min'][$index] ?? '', $_POST['hips_max'][$index] ?? '');
                $chest  = buildRange($_POST['chests_min'][$index] ?? '', $_POST['chests_max'][$index] ?? '');
                $cup    = $_POST['cups'][$index] ?: null;
                $stmtProfile->execute([
                    $casting_id, $role,
                    $_POST['quantities'][$index] ?: 1,
                    $_POST['age_ranges'][$index] ?: null,
                    $_POST['genders'][$index] ?: null,
                    !empty($_POST['eye_colors'][$index]) ? $_POST['eye_colors'][$index] : null,
                    !empty($_POST['hair_colors'][$index]) ? $_POST['hair_colors'][$index] : null,
                    !empty($_POST['ethnicities'][$index]) ? $_POST['ethnicities'][$index] : null,
                    $height, $shoe, $waist, $hip, $chest, $cup
                ]);
            }
        }
        $db->commit();
        $message = "success";

        // Notifications email aux talents correspondants
        $casting_info = [
            'company'          => $_POST['company_name'] ?: 'Non précisé',
            'city'             => $_POST['city'],
            'country'          => $_POST['country'],
            'description'      => $_POST['description'],
            'casting_date'     => $_POST['casting_date'] ?: null,
            'performance_date' => $_POST['performance_date'] ?: null,
            'collab_type'      => $_POST['collaboration_type'],
            'id'               => $casting_id,
        ];
        $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];

        // Charger les profils insérés avec le flag has_measurements
        $stmtCP = $db->prepare("
            SELECT cp.*, pr.has_measurements
            FROM casting_profiles cp
            JOIN professions pr ON pr.name = cp.role_name
            WHERE cp.casting_id = ?
        ");
        $stmtCP->execute([$casting_id]);
        $cp_list = $stmtCP->fetchAll(PDO::FETCH_ASSOC);

        $notified_ids = [];
        foreach ($cp_list as $cp) {
            $where  = ["p.name = :role", "u.id != :creator", "u.is_suspended = FALSE"];
            $binds  = [':role' => $cp['role_name'], ':creator' => $_SESSION['user_id']];
            $join_m = '';

            if ($cp['has_measurements']) {
                $join_m = "LEFT JOIN measurements m ON m.user_id = u.id";
                addMeasurementConditions($cp, $where, $binds);
            }

            $sql = "SELECT DISTINCT u.id, u.email, u.full_name, p.name AS profession
                    FROM users u
                    JOIN user_professions up ON up.user_id = u.id
                    JOIN professions p ON p.id = up.profession_id
                    $join_m
                    WHERE " . implode(' AND ', $where);

            $stmtU = $db->prepare($sql);
            $stmtU->execute($binds);
            foreach ($stmtU->fetchAll(PDO::FETCH_ASSOC) as $rec) {
                if (!in_array($rec['id'], $notified_ids)) {
                    $notified_ids[] = $rec['id'];
                    sendCastingNotification($rec, $casting_info, $cp, $host);
                }
            }
        }

    } catch (Exception $e) {
        $db->rollBack();
        $message = "Erreur : " . $e->getMessage();
    }
}

// Parse une chaîne de plage "min - max" / "min+" / "< max" → [min|null, max|null]
function parseRange(?string $range): array {
    if (!$range) return [null, null];
    $r = trim($range);
    if (preg_match('/^(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)$/', $r, $m)) return [(float)$m[1], (float)$m[2]];
    if (preg_match('/^(\d+(?:\.\d+)?)\+$/', $r, $m))                       return [(float)$m[1], null];
    if (preg_match('/^<\s*(\d+(?:\.\d+)?)$/', $r, $m))                     return [null, (float)$m[1]];
    return [null, null];
}

// Ajoute les conditions SQL de mensurations (lénient : user sans mensuration = OK)
function addMeasurementConditions(array $cp, array &$where, array &$binds): void {
    $numeric = [
        'height'     => 'm.height',
        'chest_size' => "NULLIF(regexp_replace(m.chest_size, '[^0-9]', '', 'g'), '')::NUMERIC",
        'waist_size' => "NULLIF(regexp_replace(m.waist_size, '[^0-9]', '', 'g'), '')::NUMERIC",
        'hip_size'   => "NULLIF(regexp_replace(m.hip_size, '[^0-9]', '', 'g'), '')::NUMERIC",
        'shoe_size'  => "NULLIF(regexp_replace(m.shoe_size, '[^0-9]', '', 'g'), '')::NUMERIC",
    ];
    foreach ($numeric as $field => $sql_expr) {
        [$min, $max] = parseRange($cp[$field] ?? null);
        $key = $field;
        if ($min !== null) {
            $where[] = "($sql_expr IS NULL OR $sql_expr >= :min_$key)";
            $binds[":min_$key"] = $min;
        }
        if ($max !== null) {
            $where[] = "($sql_expr IS NULL OR $sql_expr <= :max_$key)";
            $binds[":max_$key"] = $max;
        }
    }
    // Yeux, cheveux, ethnicité : uniquement si le user a renseigné et ça ne correspond pas
    if (!empty($cp['eye_color_id'])) {
        $where[] = "(m.eye_color_id IS NULL OR m.eye_color_id = :eye_color_id)";
        $binds[':eye_color_id'] = $cp['eye_color_id'];
    }
    if (!empty($cp['hair_color_id'])) {
        $where[] = "(m.hair_color_id IS NULL OR m.hair_color_id = :hair_color_id)";
        $binds[':hair_color_id'] = $cp['hair_color_id'];
    }
    if (!empty($cp['ethnicity_id'])) {
        $where[] = "(m.ethnicity_id IS NULL OR m.ethnicity_id = :ethnicity_id)";
        $binds[':ethnicity_id'] = $cp['ethnicity_id'];
    }
    // Genre
    if (!empty($cp['gender'])) {
        $where[] = "(u.gender IS NULL OR u.gender = :gender)";
        $binds[':gender'] = $cp['gender'];
    }
}

function sendCastingNotification(array $user, array $c, array $cp, string $host): void {
    $casting_url = $host . '/castings.php?view=offres&highlight=' . $c['id'];

    $casting_date_fmt     = $c['casting_date']     ? date('d/m/Y', strtotime($c['casting_date']))     : null;
    $performance_date_fmt = $c['performance_date'] ? date('d/m/Y', strtotime($c['performance_date'])) : null;

    $dates_line = '';
    if ($casting_date_fmt)     $dates_line .= "Date d'audition    : " . $casting_date_fmt . "\n";
    if ($performance_date_fmt) $dates_line .= "Date de realisation : " . $performance_date_fmt . "\n";

    // Résumé des mensurations du profil recherché
    $meas_lines = '';
    if ($cp['has_measurements']) {
        $fields = ['height' => 'Taille', 'chest_size' => 'Poitrine', 'waist_size' => 'Tour de taille', 'hip_size' => 'Hanches', 'shoe_size' => 'Pointure'];
        foreach ($fields as $f => $label) {
            if (!empty($cp[$f])) $meas_lines .= "  $label : " . $cp[$f] . "\n";
        }
        if (!empty($cp['age_range']))  $meas_lines .= "  Age : " . $cp['age_range'] . "\n";
        if (!empty($cp['gender']))     $meas_lines .= "  Genre : " . $cp['gender'] . "\n";
    }

    $subject = "Nouveau casting pour vous - " . ($c['company'] !== 'Non précisé' ? $c['company'] : $c['city']);
    $body  = "Bonjour " . $user['full_name'] . ",\n\n";
    $body .= "Un nouveau casting correspondant a votre profil (" . $user['profession'] . ") vient d'etre publie sur ChicBook.\n\n";
    $body .= "----------------------------\n";
    $body .= "Societe / Client : " . $c['company'] . "\n";
    $body .= "Lieu             : " . $c['city'] . ", " . $c['country'] . "\n";
    $body .= "Profil recherche : " . $cp['role_name'] . ($cp['quantity'] > 1 ? " (x{$cp['quantity']})" : '') . "\n";
    $body .= "Collaboration    : " . $c['collab_type'] . "\n";
    if ($dates_line) $body .= $dates_line;
    if ($meas_lines) $body .= "Mensurations attendues :\n" . $meas_lines;
    $body .= "----------------------------\n\n";
    if (!empty($c['description'])) {
        $body .= "Description :\n" . $c['description'] . "\n\n";
    }
    $body .= "Voir le casting complet : " . $casting_url . "\n\n";
    $body .= "A tres vite,\nL'equipe ChicBook.\n\n";
    $body .= "---\nPour ne plus recevoir ces notifications, rendez-vous dans vos preferences sur ChicBook.";

    $headers = "From: contact@chicbook.com\r\nReply-To: contact@chicbook.com\r\nX-Mailer: PHP/" . phpversion();
    mail($user['email'], $subject, $body, $headers);
}

function buildRange($min, $max) {
    $min = trim($min); $max = trim($max);
    if ($min && $max) return "$min - $max";
    if ($min) return "$min+";
    if ($max) return "< $max";
    return null;
}
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Créer un casting - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: { extend: { colors: { brand: '#d4a5d4', dark: '#1a1a1a' } } }
      }
    </script>
    <link rel="stylesheet" href="assets/css/custom.css" />
    <style>
      .form-control { width:100%; padding:10px 12px; border-radius:6px; border:1px solid #333; background:#111; color:white; font-size:14px; font-family:inherit; }
      .form-control:focus { outline:none; border-color:#d4a5d4; }
      @media (max-width: 768px) {
        .cc-wrapper { flex-direction: column !important; padding: 16px 12px 100px !important; margin-top: 0 !important; }
        .cc-preview { display: none !important; }
        .mensurations-grid { grid-template-columns: 1fr 1fr !important; }
        #mobile-topbar { display: none !important; }
      }
      textarea.form-control { height:120px; resize:vertical; }
      select.form-control { appearance:none; background-image:url('data:image/svg+xml;utf8,<svg fill="white" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>'); background-repeat:no-repeat; background-position-x:98%; background-position-y:50%; }
      label { display:block; margin-bottom:6px; color:#ddd; font-size:13px; font-weight:bold; }
      .form-group { margin-bottom:16px; }
      .range-pair { display:flex; align-items:center; gap:8px; }
      .range-pair input { flex:1; }
      .range-sep { color:#888; font-size:12px; white-space:nowrap; }
    </style>
</head>
<body class="bg-black text-white font-['Arial',sans-serif]">
    <?php include 'includes/header.php'; ?>

    <?php if ($message === 'success'): ?>
    <div class="max-w-[600px] mx-auto mt-32 mb-10 p-10 bg-[#1a1a1a] rounded-xl text-center border border-[#333]">
        <div class="text-5xl mb-5">✅</div>
        <h2 class="text-2xl font-bold mb-3">Casting publié !</h2>
        <p class="text-[#aaa] mb-8">Votre casting est en ligne et visible par les talents.</p>
        <div class="flex gap-4 justify-center">
            <a href="castings.php?view=mes_castings" class="bg-[#d4a5d4] text-[#111] px-6 py-3 rounded-lg font-bold no-underline hover:opacity-90 transition-opacity">Voir mes castings</a>
            <a href="creer_casting.php" class="bg-[#333] text-white px-6 py-3 rounded-lg font-bold no-underline hover:bg-[#444] transition-colors">Créer un autre</a>
        </div>
    </div>
    <?php else: ?>

    <div class="max-w-[1400px] mx-auto mt-10 mb-10 flex gap-10 px-8 cc-wrapper">
        <!-- Formulaire -->
        <main class="flex-[2] bg-[#1a1a1a] p-8 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)]">
            <div class="text-center mb-8">
                <h1 class="uppercase text-2xl mb-2 tracking-wide">Poster votre casting</h1>
                <p class="text-[#aaa] text-sm">Définissez précisément les talents dont vous avez besoin.</p>
            </div>

            <?php if ($message && $message !== 'success'): ?>
                <div class="bg-[rgba(198,40,40,0.15)] text-[#ef9a9a] p-4 rounded-lg mb-5 border border-[#c62828] text-center text-sm"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form action="creer_casting.php" method="POST" enctype="multipart/form-data" id="casting-form">
                <div class="form-group"><label>Image d'illustration (Optionnel)</label><input type="file" name="casting_image" id="casting_image_input" class="form-control" accept="image/*" style="padding:8px;"></div>
                <div class="form-group"><label>Description globale du projet *</label><textarea name="description" id="form_description" class="form-control" placeholder="Décrivez le contexte du projet..." required></textarea></div>

                <hr class="border-[#333] my-6">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-wider text-[#d4a5d4]">👥 Profils recherchés</h3>

                <div id="profiles-container" class="flex flex-col gap-4">
                    <div class="profile-card profile-card-anim bg-[#222] p-5 rounded-lg border border-[#444] relative">
                        <div class="profile-card-header flex justify-between items-center mb-4 border-b border-[#333] pb-2.5">
                            <h4 class="text-[#d4a5d4] text-sm font-bold m-0">Profil #1</h4>
                        </div>
                        <div class="flex gap-4">
                            <div class="form-group flex-[2]">
                                <label>Rôle recherché</label>
                                <select name="roles[]" class="form-control role-selector" required>
                                    <option value="">Sélectionnez un rôle</option>
                                    <?php foreach ($professions_list as $r): ?>
                                        <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group flex-1"><label>Quantité</label><input type="number" name="quantities[]" class="form-control" value="1" min="1" required></div>
                        </div>
                        <div class="mensurations-grid" style="display:none; margin-top:16px; padding-top:16px; border-top:1px dashed #444;">
                            <p class="text-[#888] text-xs mb-3" style="grid-column:1/-1;">Tous les critères ci-dessous sont facultatifs — laissez vide pour ne pas filtrer.</p>
                            <div class="form-group">
                                <label>Tranche d'âge &amp; Genre</label>
                                <div class="flex gap-3">
                                    <select name="age_ranges[]" class="form-control"><option value="">Âge (Peu importe)</option><option>Moins de 18 ans</option><option>18 - 25 ans</option><option>26 - 35 ans</option><option>36 ans et +</option></select>
                                    <select name="genders[]" class="form-control"><option value="">Genre (Peu importe)</option><option>Femme</option><option>Homme</option></select>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="form-group"><label>Couleur des yeux</label><select name="eye_colors[]" class="form-control"><option value="">Peu importe</option><?php foreach ($eyeColors as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
                                <div class="form-group"><label>Couleur des cheveux</label><select name="hair_colors[]" class="form-control"><option value="">Peu importe</option><?php foreach ($hairColors as $h): ?><option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option><?php endforeach; ?></select></div>
                                <div class="form-group col-span-2"><label>Origine / Ethnie</label><select name="ethnicities[]" class="form-control"><option value="">Peu importe</option><?php foreach ($ethnicities as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option><?php endforeach; ?></select></div>

                                <div class="form-group">
                                    <label>Taille (cm)</label>
                                    <div class="range-pair">
                                        <input type="number" name="heights_min[]" class="form-control" placeholder="Min" min="100" max="250">
                                        <span class="range-sep">→</span>
                                        <input type="number" name="heights_max[]" class="form-control" placeholder="Max" min="100" max="250">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Pointure</label>
                                    <div class="range-pair">
                                        <input type="text" name="shoes_min[]" class="form-control" placeholder="Min (ex: 37)">
                                        <span class="range-sep">→</span>
                                        <input type="text" name="shoes_max[]" class="form-control" placeholder="Max (ex: 40)">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Tour de taille (cm)</label>
                                    <div class="range-pair">
                                        <input type="number" name="waists_min[]" class="form-control" placeholder="Min">
                                        <span class="range-sep">→</span>
                                        <input type="number" name="waists_max[]" class="form-control" placeholder="Max">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Tour de hanches (cm)</label>
                                    <div class="range-pair">
                                        <input type="number" name="hips_min[]" class="form-control" placeholder="Min">
                                        <span class="range-sep">→</span>
                                        <input type="number" name="hips_max[]" class="form-control" placeholder="Max">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Tour de poitrine (cm)</label>
                                    <div class="range-pair">
                                        <input type="number" name="chests_min[]" class="form-control" placeholder="Min">
                                        <span class="range-sep">→</span>
                                        <input type="number" name="chests_max[]" class="form-control" placeholder="Max">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Bonnet</label>
                                    <input type="text" name="cups[]" class="form-control" placeholder="Ex: B, C ou A-C">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" id="btn-add-profile" class="w-full py-3 mt-2 bg-transparent text-[#d4a5d4] border-2 border-dashed border-[#d4a5d4] rounded-lg cursor-pointer font-bold text-sm hover:bg-[rgba(212,165,212,0.1)] transition-colors">+ Ajouter un autre profil</button>

                <hr class="border-[#333] my-6">

                <div class="form-group"><label>Nom / Entreprise</label><input type="text" name="company_name" id="form_company" class="form-control" placeholder="Votre nom ou entreprise"></div>

                <!-- Ville + Pays : compact -->
                <div class="flex gap-4 max-w-[480px]">
                    <div class="form-group flex-1 relative autocomplete-wrapper">
                        <label>Ville</label>
                        <input type="text" name="city" id="ville-input" class="form-control" autocomplete="off" placeholder="Ville...">
                        <ul id="ville-suggestions" class="absolute top-full left-0 right-0 bg-[#1a1a1a] border border-[#444] text-white rounded-lg list-none mt-1 p-0 max-h-[200px] overflow-y-auto z-[1000]" style="display:none;"></ul>
                    </div>
                    <div class="form-group flex-1">
                        <label>Pays</label>
                        <select name="country" id="pays-select" class="form-control"><option value="" disabled selected>Pays</option></select>
                    </div>
                </div>

                <div class="flex gap-4 max-w-[480px]">
                    <div class="form-group flex-1">
                        <label>📅 Date du casting / audition</label>
                        <input type="date" name="casting_date" class="form-control">
                    </div>
                    <div class="form-group flex-1">
                        <label>⭐ Date de la prestation</label>
                        <input type="date" name="performance_date" id="form_date" class="form-control">
                    </div>
                </div>

                <div class="bg-[#222] p-4 rounded-lg text-center border border-[#333] mt-6">
                    <h4 class="text-white mb-3 text-sm font-bold">Type de collaboration</h4>
                    <div class="flex gap-5 justify-center">
                        <?php foreach (['Payé', 'Échange / Troc', 'Non rémunéré'] as $collab): ?>
                            <label class="flex items-center gap-2 text-sm text-[#ccc] cursor-pointer"><input type="radio" name="collaboration_type" value="<?= $collab ?>" required class="accent-[#d4a5d4] cursor-pointer"> <?= $collab ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="w-full py-4 mt-6 bg-[#cfa935] text-[#111] font-bold text-base border-none rounded-lg cursor-pointer uppercase hover:bg-[#b8952c] hover:-translate-y-0.5 transition-all">Publier le casting</button>
            </form>
        </main>

        <!-- Prévisualisation -->
        <aside class="w-[300px] flex-shrink-0 cc-preview">
            <div class="sticky top-[100px]">
                <p class="text-[#888] text-xs uppercase tracking-wider mb-3 font-bold">Aperçu de la carte</p>
                <div class="casting-card bg-[#1a1a1a] rounded-xl overflow-hidden border border-[#333] flex flex-col">
                    <div id="preview-img-wrap" class="w-full h-[140px] bg-[#2a2a2a] flex items-center justify-center text-[#555] text-xs overflow-hidden">
                        <span>Image d'illustration</span>
                    </div>
                    <div class="p-4 flex flex-col gap-1">
                        <div class="text-[#d4a5d4] text-xs font-bold" id="preview-date">Prestation le : --/--/----</div>
                        <h3 class="text-sm font-bold text-white leading-snug" id="preview-title">Recherche ... - ...</h3>
                        <div class="text-[#888] text-xs" id="preview-company">Nom / Entreprise</div>
                        <p class="text-[#bbb] text-xs leading-snug mt-1" id="preview-desc">La description apparaîtra ici...</p>
                        <div class="flex gap-2 mt-3">
                            <button class="flex-grow bg-[#333] text-white py-2 rounded-lg border-none font-bold text-xs cursor-default">☆ Ça m'intéresse</button>
                            <button class="px-3 py-2 bg-[#333] text-white border-none rounded-lg cursor-default">↗</button>
                        </div>
                    </div>
                </div>
                <p class="text-[#555] text-xs mt-3 text-center">Aperçu non contractuel</p>
            </div>
        </aside>
    </div>

    <?php endif; ?>

    <script>window.CHICBOOK_TALENT_PROFESSIONS = <?= json_encode($talent_professions) ?>;</script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/creer_casting.js"></script>
</body>
</html>

