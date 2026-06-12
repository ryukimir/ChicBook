<?php
session_start();
require_once 'config/Database.php';
require_once 'models/User.php';

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $errors[] = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format d'email invalide.";
    } else {
        $db = Database::getInstance()->getConnection();
        $userModel = new User($db);

        $user = $userModel->verifyCredentials($email, $password);

        if ($user) {
            $verification_code = sprintf("%06d", mt_rand(1, 999999));
            $userModel->saveLoginCode($user['id'], $verification_code);

            $sujet = "Votre code de connexion ChicBook";
            $message = "Bonjour " . $user['full_name'] . ",\n\n";
            $message .= "Voici votre code de vérification à 6 chiffres : " . $verification_code . "\n\n";
            $message .= "Ce code est personnel, ne le partagez avec personne.\n\nÀ très vite,\nL'équipe ChicBook.";
            $headers = "From: contact@chicbook.com\r\nReply-To: contact@chicbook.com\r\nX-Mailer: PHP/" . phpversion();
            mail($email, $sujet, $message, $headers);

            $_SESSION['temp_user_id'] = $user['id'];
            $_SESSION['temp_email'] = $email;
            header("Location: verifier_code.php");
            exit();
        } else {
            $errors[] = "Email ou mot de passe incorrect.";
        }
    }
}
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Connexion - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: { extend: { colors: { brand: '#d4a5d4', dark: '#1a1a1a' } } }
      }
    </script>
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
        <div class="text-center mb-10 text-white">
            <h1 class="text-5xl mb-3 font-bold">Bon retour parmi nous</h1>
            <p class="text-base text-[#aaa]">Connectez-vous pour accéder à votre espace talent ou recruteur.</p>
        </div>

        <div class="bg-[#1a1a1a] w-full max-w-[620px] rounded-xl p-10 shadow-[0_10px_30px_rgba(0,0,0,0.1)]">
            <h3 class="text-white text-center text-2xl mb-8 font-semibold">Connexion</h3>

            <?php if (!empty($errors)): ?>
                <div class="bg-[#ffebee] text-[#c62828] border border-[#ef9a9a] p-4 mb-5 rounded-lg text-sm text-center">
                    <?php foreach ($errors as $error) echo htmlspecialchars($error) . "<br>"; ?>
                </div>
            <?php endif; ?>

            <form class="flex flex-col gap-5" action="connexion.php" method="POST">
                <div><input type="email" name="email" placeholder="Adresse email" required class="input-field"></div>
                <div><input type="password" name="password" placeholder="Mot de passe" required class="input-field"></div>
                <button type="submit" class="bg-[#d4a5d4] text-[#1a1a1a] py-4 rounded-full text-base font-bold mt-2.5 hover:opacity-90 transition-opacity cursor-pointer border-none">Se connecter</button>
                <p class="text-center mt-2">
                    <a href="mot_de_passe_oublie.php" class="text-[#888] text-sm hover:text-[#d4a5d4] transition-colors">Mot de passe oublié ?</a>
                </p>
                <p class="text-center text-[#888] text-sm mt-2">
                    Pas encore de compte ? <a href="inscription.php" class="text-[#888] underline hover:text-[#d4a5d4] transition-colors">S'inscrire</a>
                </p>
            </form>
        </div>
    </main>
</body>
</html>

