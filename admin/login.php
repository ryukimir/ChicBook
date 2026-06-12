<?php
session_start();
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, password_hash, is_admin FROM users WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['is_admin'] && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['is_admin'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Identifiants invalides ou accès non autorisé.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — ChicBook</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center">
<div class="w-full max-w-sm">
    <div class="text-center mb-8">
        <div class="text-3xl font-black text-brand tracking-tight">ChicBook</div>
        <div class="text-xs text-[#555] mt-1 uppercase tracking-widest font-semibold">Back Office</div>
    </div>
    <div class="bg-[#111] rounded-2xl p-8 border border-[#1a1a1a]">
        <?php if ($error): ?>
        <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-[#888] uppercase tracking-wider mb-2">Email</label>
                <input type="email" name="email" required autofocus
                    class="w-full bg-[#0a0a0a] border border-[#222] rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-brand transition-colors"
                    placeholder="admin@chicbook.com">
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#888] uppercase tracking-wider mb-2">Mot de passe</label>
                <input type="password" name="password" required
                    class="w-full bg-[#0a0a0a] border border-[#222] rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-brand transition-colors"
                    placeholder="••••••••">
            </div>
            <button type="submit"
                class="w-full bg-brand text-black font-bold py-3 rounded-xl text-sm hover:opacity-90 transition-opacity mt-2">
                Connexion
            </button>
        </form>
    </div>
</div>
</body>
</html>
