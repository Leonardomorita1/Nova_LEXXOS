<?php
// api/process-payment.php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);
$metodo_pagamento = $input['metodo_pagamento'] ?? 'pix';
$simular = $input['simular'] ?? false;

try {
    $pdo->beginTransaction();
    
    // ============================================
    // 1. BUSCAR ITENS DO CARRINHO
    // ============================================
    $stmt = $pdo->prepare("
        SELECT c.*, j.*, d.id as dev_id
        FROM carrinho c
        INNER JOIN jogo j ON c.jogo_id = j.id
        LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
        WHERE c.usuario_id = ? AND j.status = 'publicado'
    ");
    $stmt->execute([$user_id]);
    $itens = $stmt->fetchAll();
    
    if (empty($itens)) {
        throw new Exception('Carrinho vazio');
    }
    
    // ============================================
    // 2. CALCULAR VALORES
    // ============================================
    $subtotal = 0;
    $desconto_promo = 0;
    
    foreach ($itens as $item) {
        $preco_original = $item['preco_centavos'];
        $preco_atual = $item['em_promocao'] && $item['preco_promocional_centavos'] 
            ? $item['preco_promocional_centavos'] 
            : $item['preco_centavos'];
        
        $subtotal += $preco_atual;
        
        if ($preco_original > $preco_atual) {
            $desconto_promo += ($preco_original - $preco_atual);
        }
    }
    
    // ============================================
    // 3. APLICAR CUPOM E INCREMENTAR USOS
    // ============================================
    $cupom_id = null;
    $desconto_cupom = 0;
    $cupom_aplicado = $_SESSION['cupom_aplicado'] ?? null;
    
    if ($cupom_aplicado) {
        // Validar cupom novamente
        $stmt = $pdo->prepare("
            SELECT * FROM dev_cupom 
            WHERE id = ? 
            AND ativo = 1
            AND (validade IS NULL OR validade >= CURDATE())
            AND (usos_maximos IS NULL OR usos_atuais < usos_maximos)
        ");
        $stmt->execute([$cupom_aplicado['id']]);
        $cupom = $stmt->fetch();
        
        if ($cupom) {
            $cupom_id = $cupom['id'];
            
            // Calcular desconto do cupom
            foreach ($itens as $item) {
                // Verificar se cupom se aplica a este item
                if ($item['dev_id'] == $cupom['desenvolvedor_id'] && 
                    ($cupom['jogo_id'] === null || $cupom['jogo_id'] == $item['id'])) {
                    
                    $preco_item = $item['em_promocao'] && $item['preco_promocional_centavos'] 
                        ? $item['preco_promocional_centavos'] 
                        : $item['preco_centavos'];
                    
                    if ($cupom['tipo_desconto'] == 'percentual') {
                        $desconto_item = ($preco_item * $cupom['valor_desconto']) / 100;
                    } else {
                        $desconto_item = min($cupom['valor_desconto'], $preco_item);
                    }
                    
                    $desconto_cupom += $desconto_item;
                }
            }
            
            // *** INCREMENTAR USOS DO CUPOM ***
            $stmt = $pdo->prepare("
                UPDATE dev_cupom 
                SET usos_atuais = usos_atuais + 1 
                WHERE id = ?
            ");
            $stmt->execute([$cupom_id]);
            
            // Limpar cupom da sessão
            unset($_SESSION['cupom_aplicado']);
        }
    }
    
    $total = max(0, $subtotal - $desconto_cupom);
    
    // ============================================
    // 4. CRIAR PEDIDO
    // ============================================
    $numero_pedido = 'PED' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("
        INSERT INTO pedido (
            usuario_id, 
            numero, 
            subtotal_centavos, 
            desconto_centavos, 
            total_centavos, 
            cupom_id,
            metodo_pagamento, 
            status, 
            ip_compra,
            pago_em
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pago', ?, NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $numero_pedido,
        $subtotal,
        $desconto_cupom,
        $total,
        $cupom_id,
        $metodo_pagamento,
        $_SERVER['REMOTE_ADDR']
    ]);
    
    $pedido_id = $pdo->lastInsertId();
    
    // ============================================
    // 5. CRIAR ITENS DO PEDIDO E MOVIMENTAÇÕES
    // ============================================
    $taxa_plataforma = (int)getConfig('taxa_plataforma', $pdo);
    
    foreach ($itens as $item) {
        $preco_final = $item['em_promocao'] && $item['preco_promocional_centavos'] 
            ? $item['preco_promocional_centavos'] 
            : $item['preco_centavos'];
        
        // Calcular valores (plataforma e dev)
        $valor_plataforma = ($preco_final * $taxa_plataforma) / 100;
        $valor_dev = $preco_final - $valor_plataforma;
        
        // Inserir item do pedido
        $stmt = $pdo->prepare("
            INSERT INTO item_pedido (
                pedido_id, 
                jogo_id, 
                preco_centavos, 
                desconto_centavos, 
                valor_final_centavos,
                valor_plataforma_centavos,
                valor_desenvolvedor_centavos
            ) VALUES (?, ?, ?, 0, ?, ?, ?)
        ");
        
        $stmt->execute([
            $pedido_id,
            $item['id'],
            $item['preco_centavos'],
            $preco_final,
            $valor_plataforma,
            $valor_dev
        ]);
        
        // Adicionar jogo à biblioteca
        $stmt = $pdo->prepare("
            INSERT INTO biblioteca (usuario_id, jogo_id, pedido_id, adicionado_em) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE pedido_id = VALUES(pedido_id)
        ");
        $stmt->execute([$user_id, $item['id'], $pedido_id]);
        
        // Atualizar total de vendas do jogo
        $stmt = $pdo->prepare("
            UPDATE jogo 
            SET total_vendas = total_vendas + 1 
            WHERE id = ?
        ");
        $stmt->execute([$item['id']]);
        
        // Criar movimentação para o desenvolvedor
        if ($item['dev_id']) {
            $stmt = $pdo->prepare("
                INSERT INTO desenvolvedor_movimentacao (
                    desenvolvedor_id, 
                    tipo, 
                    valor_centavos, 
                    descricao, 
                    referencia_tipo, 
                    referencia_id
                ) VALUES (?, 'venda', ?, ?, 'pedido', ?)
            ");
            $stmt->execute([
                $item['dev_id'],
                $valor_dev,
                'Venda: ' . $item['titulo'],
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
    
    // ============================================
    // 6. LIMPAR CARRINHO
    // ============================================
    $stmt = $pdo->prepare("DELETE FROM carrinho WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    
    // ============================================
    // 7. ATUALIZAR MÉTRICAS (OPCIONAL)
    // ============================================
    try {
        $pdo->query("CALL atualizar_metricas_hoje()");
    } catch (Exception $e) {
        // Não falhar se métricas não atualizarem
        error_log("Erro ao atualizar métricas: " . $e->getMessage());
    }
    
    $pdo->commit();
    
    // ============================================
    // 8. RESPOSTA DE SUCESSO
    // ============================================
    echo json_encode([
        'success' => true,
        'message' => 'Pagamento processado com sucesso!',
        'pedido' => [
            'id' => $pedido_id,
            'numero' => $numero_pedido,
            'total' => formatPrice($total),
            'total_jogos' => count($itens)
        ],
        'cupom_usado' => $cupom_id ? true : false
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}