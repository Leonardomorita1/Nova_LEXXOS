<?php
// api/follow-dev.php
header('Content-Type: application/json');
session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$desenvolvedor_id = $data['desenvolvedor_id'] ?? null;
$action = $data['action'] ?? 'follow'; // follow ou unfollow
$user_id = $_SESSION['user_id'];

if (!$desenvolvedor_id) {
    echo json_encode(['success' => false, 'message' => 'Desenvolvedor não especificado']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar se desenvolvedor existe
    $stmt = $pdo->prepare("SELECT id FROM desenvolvedor WHERE id = ? AND status = 'ativo'");
    $stmt->execute([$desenvolvedor_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Desenvolvedor não encontrado']);
        exit;
    }
    
    if ($action == 'follow') {
        // Verificar se já segue
        $stmt = $pdo->prepare("SELECT id FROM seguidor WHERE usuario_id = ? AND desenvolvedor_id = ?");
        $stmt->execute([$user_id, $desenvolvedor_id]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Você já segue este desenvolvedor']);
            exit;
        }
        
        // Seguir
        $stmt = $pdo->prepare("INSERT INTO seguidor (usuario_id, desenvolvedor_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $desenvolvedor_id]);
        
        echo json_encode(['success' => true, 'message' => 'Agora você segue este desenvolvedor', 'action' => 'followed']);
    } else {
        // Deixar de seguir
        $stmt = $pdo->prepare("DELETE FROM seguidor WHERE usuario_id = ? AND desenvolvedor_id = ?");
        $stmt->execute([$user_id, $desenvolvedor_id]);
        
        echo json_encode(['success' => true, 'message' => 'Você deixou de seguir este desenvolvedor', 'action' => 'unfollowed']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}