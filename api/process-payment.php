<?php
// api/process-payment.php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$metodo_pagamento = $data['metodo_pagamento'] ?? 'pix';
$simular = $data['simular'] ?? false;

try {
    $pdo->beginTransaction();
    
    // Buscar itens do carrinho
    $stmt = $pdo->prepare("
        SELECT c.*, j.*, d.id as dev_id, d.percentual_plataforma
        FROM carrinho c
        INNER JOIN jogo j ON c.jogo_id = j.id
        LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
        WHERE c.usuario_id = ? AND j.status = 'publicado'
    ");
    $stmt->execute([$user_id]);
    $itens = $stmt->fetchAll();
    
    if (count($itens) == 0) {
        throw new Exception('Carrinho vazio');
    }
    
    // Calcular totais
    $subtotal = 0;
    foreach ($itens as $item) {
        $preco = $item['em_promocao'] && $item['preco_promocional_centavos'] 
            ? $item['preco_promocional_centavos'] 
            : $item['preco_centavos'];
        $subtotal += $preco;
    }
    
    // Criar pedido
    $stmt = $pdo->prepare("
        INSERT INTO pedido (usuario_id, subtotal_centavos, total_centavos, metodo_pagamento, status, ip_compra)
        VALUES (?, ?, ?, ?, 'pendente', ?)
    ");
    $stmt->execute([$user_id, $subtotal, $subtotal, $metodo_pagamento, $_SERVER['REMOTE_ADDR']]);
    $pedido_id = $pdo->lastInsertId();
    
    // Buscar número do pedido
    $stmt = $pdo->prepare("SELECT numero FROM pedido WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $numero_pedido = $stmt->fetch()['numero'];
    
    // Adicionar itens ao pedido
    foreach ($itens as $item) {
        $preco_final = $item['em_promocao'] && $item['preco_promocional_centavos'] 
            ? $item['preco_promocional_centavos'] 
            : $item['preco_centavos'];
        
        $desconto = ($item['em_promocao'] && $item['preco_promocional_centavos']) 
            ? ($item['preco_centavos'] - $item['preco_promocional_centavos'])
            : 0;
        
        // Calcular valores
        $percentual = $item['percentual_plataforma'] ?? 10;
        $valor_plataforma = (int)($preco_final * ($percentual / 100));
        $valor_desenvolvedor = $preco_final - $valor_plataforma;
        
        $stmt = $pdo->prepare("
            INSERT INTO item_pedido (pedido_id, jogo_id, preco_centavos, desconto_centavos, valor_final_centavos, valor_plataforma_centavos, valor_desenvolvedor_centavos)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $pedido_id,
            $item['id'],
            $item['preco_centavos'],
            $desconto,
            $preco_final,
            $valor_plataforma,
            $valor_desenvolvedor
        ]);
    }
    
    // Se é simulação, aprovar automaticamente
    if ($simular) {
        // Atualizar pedido para pago
        $stmt = $pdo->prepare("UPDATE pedido SET status = 'pago', pago_em = NOW() WHERE id = ?");
        $stmt->execute([$pedido_id]);
        
        // Adicionar jogos à biblioteca
        foreach ($itens as $item) {
            // Verificar se já não está na biblioteca
            $stmt = $pdo->prepare("SELECT id FROM biblioteca WHERE usuario_id = ? AND jogo_id = ?");
            $stmt->execute([$user_id, $item['id']]);
            
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("
                    INSERT INTO biblioteca (usuario_id, jogo_id, pedido_id, adicionado_em)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$user_id, $item['id'], $pedido_id]);
            }
            
            // Atualizar vendas do jogo
            $stmt = $pdo->prepare("UPDATE jogo SET total_vendas = total_vendas + 1 WHERE id = ?");
            $stmt->execute([$item['id']]);
            
            // Registrar movimentação financeira do desenvolvedor
            if ($item['dev_id']) {
                $preco_final = $item['em_promocao'] && $item['preco_promocional_centavos'] 
                    ? $item['preco_promocional_centavos'] 
                    : $item['preco_centavos'];
                
                $percentual = $item['percentual_plataforma'] ?? 10;
                $valor_dev = (int)($preco_final * (1 - $percentual / 100));
                
                $stmt = $pdo->prepare("
                    INSERT INTO desenvolvedor_movimentacao (desenvolvedor_id, tipo, valor_centavos, descricao, referencia_tipo, referencia_id)
                    VALUES (?, 'venda', ?, ?, 'pedido', ?)
                ");
                $stmt->execute([
                    $item['dev_id'],
                    $valor_dev,
                    "Venda: " . $item['titulo'],
                    $pedido_id
                ]);
                
                // Atualizar saldo do desenvolvedor (pendente)
                $stmt = $pdo->prepare("
                    UPDATE desenvolvedor_saldo 
                    SET saldo_pendente_centavos = saldo_pendente_centavos + ?,
                        total_vendas_centavos = total_vendas_centavos + ?
                    WHERE desenvolvedor_id = ?
                ");
                $stmt->execute([$valor_dev, $preco_final, $item['dev_id']]);
            }
        }
        
        // Limpar carrinho
        $stmt = $pdo->prepare("DELETE FROM carrinho WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido criado com sucesso',
        'pedido_numero' => $numero_pedido,
        'pedido_id' => $pedido_id
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar pagamento: ' . $e->getMessage()
    ]);
}