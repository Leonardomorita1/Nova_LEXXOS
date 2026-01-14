<?php
/**
 * API: Toggle Wishlist
 * Adiciona ou remove um jogo da lista de desejos
 * 
 * @method POST
 * @param int jogo_id - ID do jogo
 * @return JSON {success, action, message, in_wishlist, wishlist_count}
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
    
    // Verificar se o jogo existe
    $stmt = $pdo->prepare("SELECT id, titulo FROM jogo WHERE id = ? AND status = 'publicado'");
    $stmt->execute([$jogo_id]);
    $jogo = $stmt->fetch();
    
    if (!$jogo) {
        echo json_encode(['success' => false, 'message' => 'Jogo não encontrado']);
        exit;
    }
    
    // Verificar se está na lista de desejos
    $stmt = $pdo->prepare("SELECT id FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Remover da lista
        $stmt = $pdo->prepare("DELETE FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogo_id]);
        $action = 'removed';
        $message = 'Removido da lista de desejos';
        $in_wishlist = false;
    } else {
        // Adicionar à lista
        $stmt = $pdo->prepare("INSERT INTO lista_desejos (usuario_id, jogo_id, adicionado_em) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $jogo_id]);
        $action = 'added';
        $message = 'Adicionado à lista de desejos';
        $in_wishlist = true;
    }
    
    // Contar itens na lista
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lista_desejos WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'message' => $message,
        'in_wishlist' => $in_wishlist,
        'wishlist_count' => (int)$wishlist_count,
        'jogo_titulo' => $jogo['titulo']
    ]);
    
} catch (PDOException $e) {
    error_log("Erro toggle-wishlist: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}