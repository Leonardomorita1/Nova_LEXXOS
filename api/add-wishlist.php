<?php
// api/add-wishlist.php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Logue para usar a lista de desejos']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $jogo_id = $data['jogo_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    $database = new Database();
    $pdo = $database->getConnection();

    // Verificação direta no banco (neutraliza o erro de função inexistente)
    $stmt = $pdo->prepare("SELECT id FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já está na lista de desejos']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO lista_desejos (usuario_id, jogo_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $jogo_id]);
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar']);
}