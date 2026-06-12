<?php
session_start();
require_once 'config/Database.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;
$valid_token = false;
$user = null;

if (empty($token)) {
    header("Location: connexion.php");
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE password_reset_token=:t AND password_reset_expires > NOW() AND is_suspended = FALSE");
$stmt->execute([':t' => $token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $valid_token = true;
}

if ($valid_token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8 || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Le mot de passe doit faire au moins 8 caractères, contenir une lettre et un chiffre.";
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET password_hash=:h, password_reset_token=NULL, password_reset_expires=NULL WHERE id=:id")
           ->execute([':h' => $hash, ':id' => $user['id']]);
        $success = true;
    }
}
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Nouveau mot de passe - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css" />
    <style>
        .input-field { width:100%; padding:15px 20px; border-radius:8px; border:none; background:#fff; font-size:15px; font-family:inherit; color:#1a1a1a; outline:none; }
        .input-field::placeholder { color:#999; }
        @media (max-width:768px) { #mobile-topbar { display:none !important; } }
    </style>
</head>
<body class="bg-black text-white font-['Arial',sans-serif]">
    <?php include 'includes/header.php'; ?>

    <main class="pt-16 pb-20 min-h-screen flex flex-col items-center bg-black">
        <div class="text-center mb-10">
            <h1 class="text-4xl mb-3 font-bold">Nouveau mot de passe</h1>
        </div>

        <div class="bg-[#1a1a1a] w-full max-w-[520px] rounded-xl p-10 shadow-[0_10px_30px_rgba(0,0,0,0.1)]">

            <?php if ($success): ?>
                <div class="text-center">
                    <div class="text-5xl mb-5">✅</div>
                    <h3 class="text-white text-xl font-semibold mb-3">Mot de passe mis à jour !</h3>
                    <p class="text-[#aaa] text-sm mb-6">Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.</p>
                    <a href="connexion.php" class="bg-[#d4a5d4] text-[#1a1a1a] px-8 py-3 rounded-full font-bold hover:opacity-90 transition-opacity inline-block">
                        Se connecter
                    </a>
                </div>

            <?php elseif (!$valid_token): ?>
                <div class="text-center">
                    <div class="text-5xl mb-5">⛔</div>
                    <h3 class="text-white text-xl font-semibold mb-3">Lien invalide ou expiré</h3>
                    <p class="text-[#aaa] text-sm mb-6">Ce lien de réinitialisation a expiré (validité 1 heure) ou a déjà été utilisé.</p>
                    <a href="mot_de_passe_oublie.php" class="bg-[#d4a5d4] text-[#1a1a1a] px-8 py-3 rounded-full font-bold hover:opacity-90 transition-opacity inline-block">
                        Refaire une demande
                    </a>
                </div>

            <?php else: ?>
                <?php if ($error): ?>
                    <div class="bg-[#ffebee] text-[#c62828] border border-[#ef9a9a] p-4 mb-5 rounded-lg text-sm text-center">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <p class="text-[#aaa] text-sm mb-6 text-center">Bonjour <strong class="text-white"><?= htmlspecialchars($user['full_name']) ?></strong>, choisissez un nouveau mot de passe.</p>

                <form class="flex flex-col gap-5" action="reinitialiser_mdp.php?token=<?= htmlspecialchars($token) ?>" method="POST">
                    <div>
                        <label class="text-[#aaa] text-sm mb-2 block">Nouveau mot de passe</label>
                        <input type="password" name="password" placeholder="Minimum 8 caractères" required class="input-field">
                        <p class="text-[#666] text-xs mt-1">Au moins 8 caractères, une lettre et un chiffre.</p>
                    </div>
                    <div>
                        <label class="text-[#aaa] text-sm mb-2 block">Confirmer le mot de passe</label>
                        <input type="password" name="confirm_password" placeholder="Répétez le mot de passe" required class="input-field">
                    </div>
                    <button type="submit" class="bg-[#d4a5d4] text-[#1a1a1a] py-4 rounded-full text-base font-bold hover:opacity-90 transition-opacity cursor-pointer border-none">
                        Enregistrer le mot de passe
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
