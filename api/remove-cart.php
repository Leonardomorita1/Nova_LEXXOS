<?php
// api/remove-cart.php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$jogo_id = $data['jogo_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$jogo_id) {
    echo json_encode(['success' => false, 'message' => 'Jogo não especificado']);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

try {
    $stmt = $pdo->prepare("DELETE FROM carrinho WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    
    echo json_encode(['success' => true, 'message' => 'Jogo removido do carrinho']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao remover do carrinho']);
}