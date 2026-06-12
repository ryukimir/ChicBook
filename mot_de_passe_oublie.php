<?php
session_start();
require_once 'config/Database.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez entrer une adresse email valide.";
    } else {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, full_name FROM users WHERE email = :email AND is_suspended = FALSE");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $db->prepare("UPDATE users SET password_reset_token=:t, password_reset_expires=:e WHERE id=:id")
               ->execute([':t' => $token, ':e' => $expires, ':id' => $user['id']]);

            $reset_link = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/reinitialiser_mdp.php?token=' . $token;

            $sujet = "Réinitialisation de votre mot de passe ChicBook";
            $message = "Bonjour " . $user['full_name'] . ",\n\n";
            $message .= "Vous avez demandé la réinitialisation de votre mot de passe.\n\n";
            $message .= "Cliquez sur ce lien pour choisir un nouveau mot de passe :\n" . $reset_link . "\n\n";
            $message .= "Ce lien est valable 1 heure.\n\n";
            $message .= "Si vous n'avez pas fait cette demande, ignorez cet email.\n\nL'équipe ChicBook.";
            $headers = "From: contact@chicbook.com\r\nReply-To: contact@chicbook.com\r\nX-Mailer: PHP/" . phpversion();
            mail($email, $sujet, $message, $headers);
        }

        // Toujours afficher le succès (sécurité : ne pas révéler si l'email existe)
        $success = true;
    }
}
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mot de passe oublié - ChicBook</title>
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
            <h1 class="text-4xl mb-3 font-bold">Mot de passe oublié</h1>
            <p class="text-base text-[#aaa]">Entrez votre email pour recevoir un lien de réinitialisation.</p>
        </div>

        <div class="bg-[#1a1a1a] w-full max-w-[520px] rounded-xl p-10 shadow-[0_10px_30px_rgba(0,0,0,0.1)]">

            <?php if ($success): ?>
                <div class="text-center">
                    <div class="text-5xl mb-5">✉️</div>
                    <h3 class="text-white text-xl font-semibold mb-3">Email envoyé !</h3>
                    <p class="text-[#aaa] text-sm mb-6">Si un compte existe avec cette adresse, vous recevrez un lien de réinitialisation dans quelques instants. Pensez à vérifier vos spams.</p>
                    <a href="connexion.php" class="text-brand hover:underline text-sm">← Retour à la connexion</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="bg-[#ffebee] text-[#c62828] border border-[#ef9a9a] p-4 mb-5 rounded-lg text-sm text-center">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form class="flex flex-col gap-5" action="mot_de_passe_oublie.php" method="POST">
                    <div>
                        <label class="text-[#aaa] text-sm mb-2 block">Adresse email</label>
                        <input type="email" name="email" placeholder="votre@email.com" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="input-field">
                    </div>
                    <button type="submit" class="bg-[#d4a5d4] text-[#1a1a1a] py-4 rounded-full text-base font-bold hover:opacity-90 transition-opacity cursor-pointer border-none">
                        Envoyer le lien
                    </button>
                    <p class="text-center text-[#888] text-sm">
                        <a href="connexion.php" class="hover:text-[#d4a5d4] transition-colors">← Retour à la connexion</a>
                    </p>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
