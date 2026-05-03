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
    <link rel="stylesheet" href="src/style.css" />
    <style>
        .code-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 30px 0;
        }

        .code-inputs input {
            width: 45px;
            height: 55px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border-radius: 8px;
            border: 2px solid #ddd;
            background: white;
            color: #1a1a1a;
        }

        .code-inputs input:focus {
            border-color: #d4a5d4;
            outline: none;
        }

        .dev-box {
            background: #333;
            color: #d4a5d4;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <header id="main-header">
        <div class="nav-center">
            <a href="index.php"><img src="img/logo.png" class="logo-img" alt="ChicBook" /></a>
        </div>
    </header>

    <main class="auth-main">
        <div class="auth-header-text">
            <h1>Vérification par email</h1>
            <p>Nous avons envoyé un code à 6 chiffres à <strong><?php echo $_SESSION['temp_email']; ?></strong></p>
        </div>

        <div class="auth-card">
            <?php if ($dev_code): ?>
                <div class="dev-box">🛠 MODE DEV : Ton code est <?php echo $dev_code; ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" style="background:#ffebee; color:#c62828; padding:10px; border-radius:8px; text-align:center; margin-bottom:15px;">
                    <?php foreach ($errors as $error) echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="verifier_code.php" method="POST">
                <div class="code-inputs">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <input type="text" name="code[]" maxlength="1" pattern="\d" required oninput="moveNext(this, <?php echo $i; ?>)">
                    <?php endfor; ?>
                </div>

                <button type="submit" class="btn-submit-auth">Vérifier le code</button>

                <p class="auth-footer-link" style="margin-top:20px; text-align:center;">
                    <a href="connexion.php" style="color:#888;">Retour à la connexion</a>
                </p>
            </form>
        </div>
    </main>

    <script>
        // Petit script JS pour passer automatiquement au champ suivant quand on tape un chiffre
        function moveNext(input, index) {
            if (input.value.length === 1 && index < 5) {
                document.getElementsByName('code[]')[index + 1].focus();
            }
        }
        // Focus auto sur le premier champ
        window.onload = () => document.getElementsByName('code[]')[0].focus();
    </script>
</body>

</html>