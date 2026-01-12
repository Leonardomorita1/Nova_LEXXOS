<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

$count = 0;

if (isLoggedIn()) {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM carrinho WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetch()['total'];
}

echo json_encode(['count' => $count]);