<?php
// api/save-theme.php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$theme = $data['theme'] ?? 'dark';
$user_id = $_SESSION['user_id'];

// Validar tema
if (!in_array($theme, ['dark', 'light'])) {
    echo json_encode(['success' => false, 'message' => 'Tema inválido']);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

try {
    $stmt = $pdo->prepare("UPDATE usuario SET tema_preferencia = ? WHERE id = ?");
    $stmt->execute([$theme, $user_id]);
    
    $_SESSION['user_theme'] = $theme;
    
    echo json_encode(['success' => true, 'theme' => $theme]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar tema']);
}