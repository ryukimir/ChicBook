<?php
session_start();
require_once 'config/Database.php';
require_once 'models/User.php';

if (!isset($_SESSION['temp_user_id'])) {
    header("Location: connexion.php");
    exit();
}

$errors = [];
$dev_code = isset($_GET['dev_code']) ? $_GET['dev_code'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code_saisi = implode('', $_POST['code']);

    if (strlen($code_saisi) < 6) {
        $errors[] = "Veuillez saisir le code complet à 6 chiffres.";
    } else {
        $db = Database::getInstance()->getConnection();
        $userModel = new User($db);

        if ($userModel->verifyLoginCode($_SESSION['temp_user_id'], $code_saisi)) {
            $_SESSION['user_id'] = $_SESSION['temp_user_id'];
            $userData = $userModel->getUserProfile($_SESSION['user_id']);
            $_SESSION['user_avatar'] = $userData['profile_picture_url'];
            $userModel->clearLoginCode($_SESSION['user_id']);
            unset($_SESSION['temp_user_id']);
            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Code de vérification invalide.";
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Vérification - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: { extend: { colors: { brand: '#d4a5d4', dark: '#1a1a1a' } } }
      }
    </script>
    <link rel="stylesheet" href="assets/css/custom.css" />
</head>
<body class="bg-black text-white font-['Arial',sans-serif]">
    <?php include 'includes/header.php'; ?>

    <main class="pt-16 pb-20 min-h-screen flex flex-col items-center bg-black">
        <div class="text-center mb-10 text-white">
            <h1 class="text-4xl mb-2.5 font-bold">Vérification par email</h1>
            <p class="text-sm text-[#aaa]">Nous avons envoyé un code à 6 chiffres à <strong class="text-white"><?= htmlspecialchars($_SESSION['temp_email']) ?></strong></p>
        </div>

        <div class="bg-[#1a1a1a] w-full max-w-[450px] rounded-xl p-10 shadow-[0_10px_30px_rgba(0,0,0,0.1)]">

            <?php if ($dev_code): ?>
                <div class="bg-[#333] text-[#d4a5d4] p-2.5 rounded mb-5 font-mono text-xs">🛠 MODE DEV : Ton code est <?= $dev_code ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="bg-[#ffebee] text-[#c62828] border border-[#ef9a9a] p-3 rounded-lg text-center mb-4 text-sm">
                    <?php foreach ($errors as $error) echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="verifier_code.php" method="POST">
                <div class="flex gap-2.5 justify-center my-8">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <input type="text" name="code[]" maxlength="1" pattern="\d" required
                               oninput="moveNext(this, <?= $i ?>)"
                               class="w-11 h-14 text-center text-2xl font-bold rounded-lg border-2 border-[#ddd] bg-white text-[#1a1a1a] focus:border-[#d4a5d4] focus:outline-none">
                    <?php endfor; ?>
                </div>
                <button type="submit" class="w-full bg-[#d4a5d4] text-[#1a1a1a] py-4 rounded-full text-base font-bold hover:opacity-90 transition-opacity cursor-pointer border-none">Vérifier le code</button>
                <p class="text-center mt-5">
                    <a href="connexion.php" class="text-[#888] text-sm hover:text-[#d4a5d4] transition-colors">Retour à la connexion</a>
                </p>
            </form>
        </div>
    </main>

    <script>
        function moveNext(input, index) {
            if (input.value.length === 1 && index < 5) {
                document.getElementsByName('code[]')[index + 1].focus();
            }
        }
        window.onload = () => document.getElementsByName('code[]')[0].focus();
    </script>
</body>
</html>
