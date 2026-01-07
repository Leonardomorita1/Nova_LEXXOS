<?php
// api/add-cart.php
ob_start(); // Previne qualquer saída de texto acidental
header('Content-Type: application/json');

try {
    // 1. Inclui os arquivos necessários
    require_once '../config/config.php';
    require_once '../config/database.php';

    // 2. Verifica login (usando a função do seu config.php)
    if (!isLoggedIn()) {
        throw new Exception('Sessão expirada. Faça login novamente.');
    }

    // 3. Pega os dados
    $data = json_decode(file_get_contents('php://input'), true);
    $jogo_id = $data['jogo_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    if (!$jogo_id) throw new Exception('ID do jogo não informado.');

    $database = new Database();
    $pdo = $database->getConnection();
    
    // 4. Executa a ação
    $stmt = $pdo->prepare("INSERT IGNORE INTO carrinho (usuario_id, jogo_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $jogo_id]);
    
    ob_clean(); // Limpa lixo de memória
    echo json_encode(['success' => true, 'message' => 'Sucesso!']);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;