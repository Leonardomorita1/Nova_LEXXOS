<?php
/**
 * API: Apply Coupon
 * Aplica um cupom de desconto ao carrinho
 * 
 * @method POST
 * @param string codigo - Código do cupom
 * @return JSON {success, message, cupom, desconto}
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Faça login para continuar', 'require_login' => true]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$codigo = trim($data['codigo'] ?? '');
$user_id = $_SESSION['user_id'];

if (empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Digite um código de cupom']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Buscar cupom
    $stmt = $pdo->prepare("
        SELECT * FROM cupom 
        WHERE codigo = ? 
        AND ativo = 1 
        AND (data_inicio IS NULL OR data_inicio <= CURDATE())
        AND (data_fim IS NULL OR data_fim >= CURDATE())
        AND (uso_maximo IS NULL OR usos_atuais < uso_maximo)
    ");
    $stmt->execute([strtoupper($codigo)]);
    $cupom = $stmt->fetch();
    
    if (!$cupom) {
        echo json_encode(['success' => false, 'message' => 'Cupom inválido ou expirado']);
        exit;
    }
    
    // Verificar se usuário já usou o cupom
    $stmt = $pdo->prepare("SELECT id FROM cupom_uso WHERE cupom_id = ? AND usuario_id = ?");
    $stmt->execute([$cupom['id'], $user_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Você já utilizou este cupom']);
        exit;
    }
    
    // Calcular subtotal do carrinho
    $stmt = $pdo->prepare("
        SELECT SUM(
            CASE 
                WHEN j.em_promocao = 1 AND j.preco_promocional_centavos IS NOT NULL 
                THEN j.preco_promocional_centavos 
                ELSE j.preco_centavos 
            END
        ) as subtotal
        FROM carrinho c
        INNER JOIN jogo j ON c.jogo_id = j.id
        WHERE c.usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $subtotal = $stmt->fetchColumn() ?: 0;
    
    // Verificar valor mínimo
    if (isset($cupom['valor_minimo']) && $subtotal < $cupom['valor_minimo']) {
        $min = formatPrice($cupom['valor_minimo']);
        echo json_encode(['success' => false, 'message' => "Valor mínimo para este cupom: {$min}"]);
        exit;
    }
    
    // Calcular desconto
    if ($cupom['tipo_desconto'] == 'percentual') {
        $desconto = ($subtotal * $cupom['valor_desconto']) / 100;
        $desconto_texto = $cupom['valor_desconto'] . '%';
    } else {
        $desconto = $cupom['valor_desconto'];
        $desconto_texto = formatPrice($cupom['valor_desconto']);
    }
    
    // Limitar desconto ao valor máximo se existir
    if (isset($cupom['desconto_maximo']) && $cupom['desconto_maximo'] > 0) {
        $desconto = min($desconto, $cupom['desconto_maximo']);
    }
    
    // Salvar na sessão
    $_SESSION['cupom_aplicado'] = [
        'id' => $cupom['id'],
        'codigo' => $cupom['codigo'],
        'tipo_desconto' => $cupom['tipo_desconto'],
        'valor_desconto' => $cupom['valor_desconto'],
        'desconto_calculado' => $desconto
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Cupom aplicado com sucesso!',
        'cupom' => [
            'codigo' => $cupom['codigo'],
            'tipo' => $cupom['tipo_desconto'],
            'valor' => $desconto_texto
        ],
        'desconto' => $desconto,
        'desconto_formatado' => formatPrice($desconto),
        'novo_total' => $subtotal - $desconto,
        'novo_total_formatado' => formatPrice($subtotal - $desconto)
    ]);
    
} catch (PDOException $e) {
    error_log("Erro apply-coupon: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}