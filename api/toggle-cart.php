<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$jogo_id = $data['jogo_id'] ?? 0;
$user_id = $_SESSION['user_id'];

if (!$jogo_id) {
    echo json_encode(['success' => false, 'message' => 'Jogo inválido']);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

try {
    // Verifica se já está no carrinho
    $stmt = $pdo->prepare("SELECT id FROM carrinho WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Remove do carrinho
        $stmt = $pdo->prepare("DELETE FROM carrinho WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogo_id]);
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removido do carrinho']);
    } else {
        // Verifica se o usuário já possui o jogo
        $stmt = $pdo->prepare("SELECT id FROM biblioteca WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogo_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Você já possui este jogo']);
            exit;
        }
        
        // Adiciona ao carrinho
        $stmt = $pdo->prepare("INSERT INTO carrinho (usuario_id, jogo_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $jogo_id]);
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Adicionado ao carrinho']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar carrinho']);
}