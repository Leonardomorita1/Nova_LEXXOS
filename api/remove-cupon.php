<?php
/**
 * API: Remove Coupon
 * Remove o cupom aplicado ao carrinho
 * 
 * @method POST
 * @return JSON {success, message}
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Faça login para continuar']);
    exit;
}

if (isset($_SESSION['cupom_aplicado'])) {
    unset($_SESSION['cupom_aplicado']);
    echo json_encode(['success' => true, 'message' => 'Cupom removido']);
} else {
    echo json_encode(['success' => false, 'message' => 'Nenhum cupom aplicado']);
}