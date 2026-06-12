<?php
session_start();
require_once 'config/database.php';
if (!empty($_SESSION['user_id'])) {
    $db = Database::getInstance()->getConnection();
    $db->prepare("UPDATE users SET remember_token=NULL WHERE id=:id")->execute(['id' => $_SESSION['user_id']]);
}
setcookie('chicbook_remember', '', time() - 3600, '/');
$_SESSION = [];
session_destroy();
header("Location: index.php");
exit();
