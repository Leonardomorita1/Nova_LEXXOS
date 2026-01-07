<?php
// auth/logout.php
require_once '../config/config.php';
require_once '../config/database.php';

if (isLoggedIn()) {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Log de logout
    $stmt = $pdo->prepare("INSERT INTO log_acesso (usuario_id, email, evento, ip, user_agent) VALUES (?, ?, 'logout', ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['user_name'] ?? '',
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
}

// Destruir sessão
session_unset();
session_destroy();

// Redirecionar para home
header('Location: ' . SITE_URL . '/pages/home.php');
exit;