<?php
// api/atualizar-metricas.php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

// Apenas admins podem atualizar
if (!isLoggedIn() || getUserType() !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

try {
    // Chamar procedure para atualizar métricas
    $pdo->query("CALL atualizar_metricas_hoje()");
    
    echo json_encode([
        'success' => true,
        'message' => 'Métricas atualizadas com sucesso',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao atualizar métricas: ' . $e->getMessage()
    ]);
}