<?php
session_start();
$is_logged_in = isset($_SESSION['user_id']);
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();
$is_light = (($_COOKIE['chicbook_theme'] ?? 'dark') === 'light');

$report_success = false;
if (!empty($_POST['submit_report'])) {
    $msg = trim($_POST['report_message'] ?? '');
    $cat = $_POST['report_category'] ?? 'autre';
    if (strlen($msg) > 0) {
        $stmt = $db->prepare("INSERT INTO reports (user_id, category, message) VALUES (:uid, :cat, :msg)");
        $stmt->execute([
            'uid' => $_SESSION['user_id'] ?? null,
            'cat' => $cat,
            'msg' => mb_substr($msg, 0, 2000),
        ]);
        $report_success = true;
    }
}

$suggestion_success = false;
if (!empty($_POST['submit_suggestion'])) {
    $msg = trim($_POST['suggestion_message'] ?? '');
    if (strlen($msg) > 0) {
        $stmt = $db->prepare("INSERT INTO suggestions (user_id, message) VALUES (:uid, :msg)");
        $stmt->execute([
            'uid' => $_SESSION['user_id'] ?? null,
            'msg' => mb_substr($msg, 0, 2000),
        ]);
        $suggestion_success = true;
    }
}
?>
<!doctype html>
<html lang="fr" class="<?= $is_light ? 'light' : '' ?>">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Préférences — ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css"/>
    <style>
        .theme-switch { position:relative; display:inline-block; width:52px; height:28px; }
        .theme-switch input { opacity:0; width:0; height:0; }
        .theme-slider { position:absolute; inset:0; cursor:pointer; background:#333; border-radius:28px; transition:background .25s; }
        .theme-slider::before { content:''; position:absolute; width:20px; height:20px; left:4px; bottom:4px; background:#fff; border-radius:50%; transition:transform .25s; box-shadow:0 1px 4px rgba(0,0,0,.3); }
        input:checked + .theme-slider { background:#d4a5d4; }
        input:checked + .theme-slider::before { transform:translateX(24px); }
        html.light .theme-slider { background:#d0cac4; }
        html.light input:checked + .theme-slider { background:#d4a5d4; }

        /* About cards */
        .about-card { border-radius:20px; padding:32px; display:flex; flex-direction:column; gap:12px; }
        html:not(.light) .about-card { background:#111; border:1px solid #1a1a1a; }
        html.light .about-card { background:#fff; border:1px solid #e0dbd4; }
        .about-card-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:rgba(212,165,212,0.12); flex-shrink:0; }
        .about-card-icon svg { width:22px; height:22px; stroke:#d4a5d4; }
        @media (max-width: 768px) { #mobile-topbar { display: none !important; } }
    </style>
</head>
<body class="bg-black text-white" style="font-family:'Open Sans',sans-serif;">
<?php include 'includes/header.php'; ?>

<div class="max-w-[860px] mx-auto px-6 pt-10 pb-20">

    <h1 class="text-2xl font-bold mb-1">Préférences</h1>
    <p class="text-[#555] text-sm mb-8">Personnalisez votre expérience ChicBook.</p>

    <!-- ══ APPARENCE ══ -->
    <section class="bg-[#111] border border-[#1a1a1a] rounded-2xl mb-10 overflow-hidden">
        <div class="px-6 py-4 border-b border-[#1a1a1a]">
            <h2 class="font-semibold text-xs uppercase tracking-widest text-[#666]">Apparence</h2>
        </div>
        <div class="px-6 py-5 flex items-center justify-between">
            <div>
                <p class="font-semibold text-[15px]">Thème de l'interface</p>
                <p class="text-[#555] text-sm mt-0.5"><?= $is_light ? 'Thème clair activé' : 'Thème sombre activé' ?></p>
            </div>
            <div class="flex items-center gap-3">
                <svg id="icon-dark" class="w-5 h-5" style="color:<?= $is_light ? '#bbb' : '#d4a5d4' ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"/>
                </svg>
                <label class="theme-switch" title="Changer de thème">
                    <input type="checkbox" id="theme-toggle" <?= $is_light ? 'checked' : '' ?>>
                    <span class="theme-slider"></span>
                </label>
                <svg id="icon-light" class="w-5 h-5" style="color:<?= $is_light ? '#d4a5d4' : '#555' ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="5"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                </svg>
            </div>
        </div>
    </section>

    <!-- ══ SIGNALER UN PROBLÈME ══ -->
    <section class="bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-[#1a1a1a]">
            <h2 class="font-semibold text-xs uppercase tracking-widest text-[#666]">Signaler un problème</h2>
        </div>
        <div class="px-6 py-6">
            <?php if (!empty($report_success)): ?>
                <div class="flex items-center gap-3 bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-5 py-4 mb-5">
                    <svg class="w-5 h-5 flex-shrink-0" style="color:#d4a5d4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <p class="text-[#ccc] text-sm">Votre signalement a bien été envoyé. Merci !</p>
                </div>
            <?php endif; ?>
            <p class="text-[#666] text-sm mb-5 leading-relaxed">
                Un bug, un contenu inapproprié, un problème technique ? Décrivez-le ci-dessous et notre équipe s'en occupera.
            </p>
            <form method="POST" action="preferences.php">
                <input type="hidden" name="submit_report" value="1">
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-[#555] uppercase tracking-widest mb-2">Catégorie</label>
                    <select name="report_category" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#444] pr-8">
                        <option value="bug">Bug technique</option>
                        <option value="contenu">Contenu inapproprié</option>
                        <option value="compte">Problème de compte</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-[#555] uppercase tracking-widest mb-2">Description</label>
                    <textarea name="report_message" rows="4" placeholder="Décrivez le problème en détail…" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#444] resize-none" maxlength="2000"></textarea>
                </div>
                <button type="submit" class="inline-flex items-center gap-2 bg-[#1a1a1a] border border-[#2a2a2a] text-white text-sm font-semibold px-5 py-2.5 rounded-xl hover:border-[#444] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Envoyer le signalement
                </button>
            </form>
        </div>
    </section>

    <!-- ══ SUGGÉRER UNE AMÉLIORATION ══ -->
    <section class="bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-[#1a1a1a]">
            <h2 class="font-semibold text-xs uppercase tracking-widest text-[#666]">Suggérer une amélioration</h2>
        </div>
        <div class="px-6 py-6">
            <?php if (!empty($suggestion_success)): ?>
                <div class="flex items-center gap-3 bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl px-5 py-4 mb-5">
                    <svg class="w-5 h-5 flex-shrink-0" style="color:#d4a5d4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <p class="text-[#ccc] text-sm">Merci pour votre suggestion, nous en tiendrons compte !</p>
                </div>
            <?php endif; ?>
            <p class="text-[#666] text-sm mb-5 leading-relaxed">
                Une idée pour améliorer ChicBook ? Une fonctionnalité que vous aimeriez voir ? Partagez-la avec nous.
            </p>
            <form method="POST" action="preferences.php">
                <input type="hidden" name="submit_suggestion" value="1">
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-[#555] uppercase tracking-widest mb-2">Votre idée</label>
                    <textarea name="suggestion_message" rows="4" placeholder="Décrivez votre idée ou suggestion…" class="w-full bg-[#1a1a1a] border border-[#2a2a2a] text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#444] resize-none" maxlength="2000"></textarea>
                </div>
                <button type="submit" class="inline-flex items-center gap-2 bg-[#1a1a1a] border border-[#2a2a2a] text-white text-sm font-semibold px-5 py-2.5 rounded-xl hover:border-[#444] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.347.347a3.001 3.001 0 00-.184 3.187A2 2 0 0118 20H6a2 2 0 01-1.94-1.487 3.001 3.001 0 00-.184-3.187l-.347-.347z"/></svg>
                    Envoyer ma suggestion
                </button>
            </form>
        </div>
    </section>

    <!-- ══ COMPTE ══ -->
    <section class="bg-[#111] border border-[#1a1a1a] rounded-2xl overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-[#1a1a1a]">
            <h2 class="font-semibold text-xs uppercase tracking-widest text-[#666]">Compte</h2>
        </div>
        <div class="px-6 py-5 flex items-center justify-between">
            <div>
                <p class="font-semibold text-[15px]">Déconnexion</p>
                <p class="text-[#555] text-sm mt-0.5">Vous serez redirigé vers la page d'accueil.</p>
            </div>
            <a href="logout.php" class="inline-flex items-center gap-2 bg-[#1a1a1a] border border-[#2a2a2a] text-[#e05555] text-sm font-semibold px-5 py-2.5 rounded-xl hover:border-[#e05555] hover:bg-[#1f1313] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Se déconnecter
            </a>
        </div>
    </section>

</div>

<script>
const toggle = document.getElementById('theme-toggle');

toggle.addEventListener('change', function() {
    const isLight = this.checked;
    const val = isLight ? 'light' : 'dark';
    // Sauvegarder cookie + localStorage
    document.cookie = 'chicbook_theme=' + val + ';path=/;max-age=31536000;SameSite=Lax';
    localStorage.setItem('chicbook_theme', val);
    // Recharger pour que PHP applique la classe sur <html> sans flash
    window.location.reload();
});
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
