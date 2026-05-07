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
            $message .= "Ce code est personnel, ne le partagez avec personne.\n\n";
            $message .= "À très vite,\nL'équipe ChicBook.";

            $headers = "From: contact@chicbook.com\r\n";
            $headers .= "Reply-To: contact@chicbook.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

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
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Connexion - ChicBook</title>
    <link rel="stylesheet" href="src/style.css" />
    <style>
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
        }

        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <main class="auth-main">
        <div class="auth-header-text">
            <h1>Bon retour parmi nous</h1>
            <p>Connectez-vous pour accéder à votre espace talent ou recruteur.</p>
        </div>

        <div class="auth-card">
            <h3>Connexion</h3>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error) echo htmlspecialchars($error) . "<br>"; ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" action="connexion.php" method="POST">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Adresse email" required>
                </div>

                <div class="form-group">
                    <input type="password" name="password" placeholder="Mot de passe" required>
                </div>

                <button type="submit" class="btn-submit-auth">Se connecter</button>

                <p class="auth-footer-link">
                    Pas encore de compte ? <a href="inscription.php">S'inscrire</a>
                </p>
            </form>
        </div>
    </main>
</body>

</html>