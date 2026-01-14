<?php
/**
 * API: Toggle Cart
 * Adiciona ou remove um jogo do carrinho
 * 
 * @method POST
 * @param int jogo_id - ID do jogo
 * @return JSON {success, action, message, in_cart, cart_count}
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Faça login para continuar', 'require_login' => true]);
    exit;
}

// Obter dados
$data = json_decode(file_get_contents('php://input'), true);
$jogo_id = filter_var($data['jogo_id'] ?? 0, FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

if (!$jogo_id || $jogo_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do jogo inválido']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar se o jogo existe e está publicado
    $stmt = $pdo->prepare("SELECT id, titulo, preco_centavos FROM jogo WHERE id = ? AND status = 'publicado'");
    $stmt->execute([$jogo_id]);
    $jogo = $stmt->fetch();
    
    if (!$jogo) {
        echo json_encode(['success' => false, 'message' => 'Jogo não encontrado']);
        exit;
    }
    
    // Verificar se já possui o jogo na biblioteca
    $stmt = $pdo->prepare("SELECT id FROM biblioteca WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Você já possui este jogo', 'owned' => true]);
        exit;
    }
    
    // Verificar se está no carrinho
    $stmt = $pdo->prepare("SELECT id FROM carrinho WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Remover do carrinho
        $stmt = $pdo->prepare("DELETE FROM carrinho WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogo_id]);
        $action = 'removed';
        $message = 'Removido do carrinho';
        $in_cart = false;
    } else {
        // Adicionar ao carrinho
        $stmt = $pdo->prepare("INSERT INTO carrinho (usuario_id, jogo_id, adicionado_em) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $jogo_id]);
        $action = 'added';
        $message = 'Adicionado ao carrinho';
        $in_cart = true;
    }
    
    // Contar itens no carrinho
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM carrinho WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'message' => $message,
        'in_cart' => $in_cart,
        'cart_count' => (int)$cart_count,
        'jogo_titulo' => $jogo['titulo']
    ]);
    
} catch (PDOException $e) {
    error_log("Erro toggle-cart: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}