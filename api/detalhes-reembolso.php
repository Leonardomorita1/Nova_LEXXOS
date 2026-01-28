<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

// Verificar se é admin
if (!isLoggedIn() || getUserType() !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$pedido_id = (int)($_GET['pedido_id'] ?? 0);

if (!$pedido_id) {
    echo json_encode(['success' => false, 'error' => 'ID do pedido não fornecido']);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

try {
    // Buscar informações do pedido
    $stmt = $pdo->prepare("
        SELECT p.*, u.nome_usuario, u.email
        FROM pedido p
        INNER JOIN usuario u ON p.usuario_id = u.id
        WHERE p.id = ? AND p.status = 'reembolsado'
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        throw new Exception('Pedido não encontrado ou não reembolsado');
    }

    // Buscar itens do pedido
    $stmt = $pdo->prepare("
        SELECT ip.*, j.titulo, d.nome_estudio
        FROM item_pedido ip
        INNER JOIN jogo j ON ip.jogo_id = j.id
        INNER JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
        WHERE ip.pedido_id = ?
    ");
    $stmt->execute([$pedido_id]);
    $itens = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'pedido' => $pedido,
            'itens' => $itens
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
}