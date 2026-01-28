<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

// Verificar se está logado
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Pegar dados da requisição
$data = json_decode(file_get_contents('php://input'), true);
$pedido_id = (int)($data['pedido_id'] ?? 0);
$motivo = trim($data['motivo'] ?? '');

if (!$pedido_id) {
    echo json_encode(['success' => false, 'error' => 'Pedido não especificado']);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Buscar configuração de dias permitidos para reembolso
    $stmt = $pdo->prepare("SELECT valor FROM configuracao WHERE chave = 'dias_reembolso'");
    $stmt->execute();
    $dias_reembolso = (int)($stmt->fetch()['valor'] ?? 14);

    // Buscar informações do pedido
    $stmt = $pdo->prepare("
        SELECT p.*, DATEDIFF(NOW(), p.pago_em) as dias_desde_compra
        FROM pedido p
        WHERE p.id = ? AND p.usuario_id = ?
    ");
    $stmt->execute([$pedido_id, $user_id]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }

    // Validações
    if ($pedido['status'] !== 'pago') {
        throw new Exception('Apenas pedidos pagos podem ser reembolsados');
    }

    if ($pedido['status'] === 'reembolsado') {
        throw new Exception('Este pedido já foi reembolsado');
    }

    if (!$pedido['pago_em']) {
        throw new Exception('Data de pagamento não encontrada');
    }

    if ($pedido['dias_desde_compra'] > $dias_reembolso) {
        throw new Exception("Período de reembolso expirado (máximo {$dias_reembolso} dias)");
    }

    // Buscar itens do pedido
    $stmt = $pdo->prepare("
        SELECT ip.*, j.titulo, j.desenvolvedor_id
        FROM item_pedido ip
        INNER JOIN jogo j ON ip.jogo_id = j.id
        WHERE ip.pedido_id = ?
    ");
    $stmt->execute([$pedido_id]);
    $itens = $stmt->fetchAll();

    if (empty($itens)) {
        throw new Exception('Nenhum item encontrado no pedido');
    }

    // Processar reembolso para cada item
    foreach ($itens as $item) {
        // Remover jogo da biblioteca do usuário
        $stmt = $pdo->prepare("
            DELETE FROM biblioteca 
            WHERE usuario_id = ? AND jogo_id = ? AND pedido_id = ?
        ");
        $stmt->execute([$user_id, $item['jogo_id'], $pedido_id]);

        // Reverter valor do desenvolvedor
        $valor_dev = $item['valor_desenvolvedor_centavos'];
        
        // Registrar movimentação de estorno para o desenvolvedor
        $stmt = $pdo->prepare("
            INSERT INTO desenvolvedor_movimentacao 
            (desenvolvedor_id, tipo, valor_centavos, descricao, referencia_tipo, referencia_id, criado_em)
            VALUES (?, 'estorno', ?, ?, 'pedido', ?, NOW())
        ");
        $descricao = "Estorno: Reembolso do pedido #{$pedido['numero']} - {$item['titulo']}";
        $stmt->execute([
            $item['desenvolvedor_id'],
            -$valor_dev, // Valor negativo para estorno
            $descricao,
            $pedido_id
        ]);

        // Atualizar saldo do desenvolvedor
        $stmt = $pdo->prepare("
            UPDATE desenvolvedor_saldo 
            SET saldo_pendente_centavos = GREATEST(0, saldo_pendente_centavos - ?),
                saldo_disponivel_centavos = GREATEST(0, saldo_disponivel_centavos - ?),
                total_vendas_centavos = GREATEST(0, total_vendas_centavos - ?),
                atualizado_em = NOW()
            WHERE desenvolvedor_id = ?
        ");
        
        // Buscar configuração de dias de liberação
        $stmt_config = $pdo->prepare("SELECT valor FROM configuracao WHERE chave = 'dias_liberacao_saldo'");
        $stmt_config->execute();
        $dias_liberacao = (int)($stmt_config->fetch()['valor'] ?? 7);
        
        // Verificar se o valor ainda está pendente ou já foi liberado
        $dias_desde_pagamento = $pedido['dias_desde_compra'];
        
        if ($dias_desde_pagamento <= $dias_liberacao) {
            // Ainda está em saldo pendente
            $stmt->execute([$valor_dev, 0, $valor_dev, $item['desenvolvedor_id']]);
        } else {
            // Já foi liberado para saldo disponível
            $stmt->execute([0, $valor_dev, $valor_dev, $item['desenvolvedor_id']]);
        }

        // Decrementar total de vendas do jogo
        $stmt = $pdo->prepare("
            UPDATE jogo 
            SET total_vendas = GREATEST(0, total_vendas - 1)
            WHERE id = ?
        ");
        $stmt->execute([$item['jogo_id']]);
    }

    // Atualizar status do pedido
    $stmt = $pdo->prepare("
        UPDATE pedido 
        SET status = 'reembolsado',
            reembolso_solicitado_em = NOW(),
            reembolso_processado_em = NOW(),
            reembolso_motivo = ?,
            atualizado_em = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$motivo ?: 'Solicitação de reembolso do usuário', $pedido_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Reembolso processado com sucesso',
        'pedido_numero' => $pedido['numero'],
        'itens_removidos' => count($itens)
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
}