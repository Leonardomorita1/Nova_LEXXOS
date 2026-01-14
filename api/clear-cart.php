<?php
/**
 * API: Clear Cart
 * Remove todos os itens do carrinho do usuário
 * 
 * @method POST
 * @return JSON {success, message}
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Faça login para continuar', 'require_login' => true]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Contar itens antes de limpar
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM carrinho WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo json_encode(['success' => false, 'message' => 'O carrinho já está vazio']);
        exit;
    }
    
    // Limpar carrinho
    $stmt = $pdo->prepare("DELETE FROM carrinho WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    
    // Limpar cupom da sessão
    unset($_SESSION['cupom_aplicado']);
    
    echo json_encode([
        'success' => true,
        'message' => $count . ' item(ns) removido(s) do carrinho',
        'items_removed' => (int)$count,
        'cart_count' => 0
    ]);
    
} catch (PDOException $e) {
    error_log("Erro clear-cart: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}