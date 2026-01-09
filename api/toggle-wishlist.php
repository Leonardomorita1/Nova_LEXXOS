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
    // Verifica se já está na lista de desejos
    $stmt = $pdo->prepare("SELECT id FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Remove da lista de desejos
        $stmt = $pdo->prepare("DELETE FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogo_id]);
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removido da lista de desejos']);
    } else {
        // Adiciona à lista de desejos
        $stmt = $pdo->prepare("INSERT INTO lista_desejos (usuario_id, jogo_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $jogo_id]);
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Adicionado à lista de desejos']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar lista de desejos']);
}