<?php
// api/remove-wishlist.php
header('Content-Type: application/json');
session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
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

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("DELETE FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    
    echo json_encode(['success' => true, 'message' => 'Jogo removido da lista de desejos']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao remover: ' . $e->getMessage()]);
}