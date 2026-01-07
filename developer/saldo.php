<?php
// developer/saldo.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Buscar desenvolvedor
$stmt = $pdo->prepare("SELECT * FROM desenvolvedor WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$dev = $stmt->fetch();

if (!$dev || $dev['status'] != 'ativo') {
    header('Location: ' . SITE_URL . '/developer/dashboard.php');
    exit;
}

$success = '';
$error = '';

// Processar solicitação de saque
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $valor_centavos = (int)($_POST['valor'] * 100);
    $metodo = $_POST['metodo'];
    
    // Buscar saldo
    $stmt = $pdo->prepare("SELECT * FROM desenvolvedor_saldo WHERE desenvolvedor_id = ?");
    $stmt->execute([$dev['id']]);
    $saldo = $stmt->fetch();
    
    $valor_minimo = (int)getConfig('valor_minimo_saque', $pdo);
    
    if ($valor_centavos < $valor_minimo) {
        $error = 'Valor mínimo para saque: ' . formatPrice($valor_minimo);
    } elseif ($valor_centavos > $saldo['saldo_disponivel_centavos']) {
        $error = 'Saldo insuficiente';
    } elseif (empty($dev['chave_pix']) && $metodo == 'pix') {
        $error = 'Cadastre uma chave PIX no seu perfil';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Criar solicitação de saque
            $stmt = $pdo->prepare("
                INSERT INTO saque (desenvolvedor_id, valor_centavos, metodo, status, solicitado_em)
                VALUES (?, ?, ?, 'solicitado', NOW())
            ");
            $stmt->execute([$dev['id'], $valor_centavos, $metodo]);
            
            // Atualizar saldo
            $stmt = $pdo->prepare("
                UPDATE desenvolvedor_saldo 
                SET saldo_disponivel_centavos = saldo_disponivel_centavos - ?
                WHERE desenvolvedor_id = ?
            ");
            $stmt->execute([$valor_centavos, $dev['id']]);
            
            // Registrar movimentação
            $stmt = $pdo->prepare("
                INSERT INTO desenvolvedor_movimentacao (desenvolvedor_id, tipo, valor_centavos, descricao)
                VALUES (?, 'saque', ?, 'Saque solicitado')
            ");
            $stmt->execute([$dev['id'], -$valor_centavos]);
            
            $pdo->commit();
            $success = 'Saque solicitado com sucesso! Será processado em até 5 dias úteis.';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Erro ao solicitar saque';
        }
    }
}

// Buscar saldo
$stmt = $pdo->prepare("SELECT * FROM desenvolvedor_saldo WHERE desenvolvedor_id = ?");
$stmt->execute([$dev['id']]);
$saldo = $stmt->fetch();

// Buscar histórico de saques
$stmt = $pdo->prepare("
    SELECT * FROM saque 
    WHERE desenvolvedor_id = ? 
    ORDER BY solicitado_em DESC
    LIMIT 20
");
$stmt->execute([$dev['id']]);
$saques = $stmt->fetchAll();

// Buscar movimentações
$stmt = $pdo->prepare("
    SELECT * FROM desenvolvedor_movimentacao 
    WHERE desenvolvedor_id = ? 
    ORDER BY criado_em DESC
    LIMIT 50
");
$stmt->execute([$dev['id']]);
$movimentacoes = $stmt->fetchAll();

$valor_minimo_saque = (int)getConfig('valor_minimo_saque', $pdo);

$page_title = 'Saldo e Saques - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="dev-content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-wallet"></i> Saldo e Saques
                </h1>
            </div>
            
            <?php if ($success): ?>
                <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div style="background: rgba(220,53,69,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Cards de Saldo -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
                <div style="background: linear-gradient(135deg, var(--accent) 0%, #6ba3ff 100%); border-radius: 15px; padding: 25px; color: white;">
                    <div style="font-size: 14px; opacity: 0.9; margin-bottom: 10px;">Disponível para Saque</div>
                    <div style="font-size: 32px; font-weight: 700;">
                        <?php echo formatPrice($saldo['saldo_disponivel_centavos'] ?? 0); ?>
                    </div>
                </div>
                
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 25px; text-align: center;">
                    <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 10px;">Saldo Pendente</div>
                    <div style="font-size: 28px; font-weight: 700; color: var(--warning);">
                        <?php echo formatPrice($saldo['saldo_pendente_centavos'] ?? 0); ?>
                    </div>
                </div>
                
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 25px; text-align: center;">
                    <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 10px;">Total de Vendas</div>
                    <div style="font-size: 28px; font-weight: 700; color: var(--success);">
                        <?php echo formatPrice($saldo['total_vendas_centavos'] ?? 0); ?>
                    </div>
                </div>
                
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 25px; text-align: center;">
                    <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 10px;">Total Sacado</div>
                    <div style="font-size: 28px; font-weight: 700;">
                        <?php echo formatPrice($saldo['total_saques_centavos'] ?? 0); ?>
                    </div>
                </div>
            </div>
            
            <!-- Solicitar Saque -->
            <?php if (($saldo['saldo_disponivel_centavos'] ?? 0) >= $valor_minimo_saque): ?>
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 30px; margin-bottom: 30px;">
                <h2 style="margin-bottom: 20px;"><i class="fas fa-money-bill-wave"></i> Solicitar Saque</h2>
                
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Valor (R$) *</label>
                            <input type="number" name="valor" class="form-control" 
                                   min="<?php echo $valor_minimo_saque / 100; ?>" 
                                   max="<?php echo $saldo['saldo_disponivel_centavos'] / 100; ?>" 
                                   step="0.01" required
                                   placeholder="<?php echo $valor_minimo_saque / 100; ?>">
                            <small style="color: var(--text-secondary);">
                                Valor mínimo: <?php echo formatPrice($valor_minimo_saque); ?>
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Método *</label>
                            <select name="metodo" class="form-control" required>
                                <option value="pix">PIX</option>
                                <option value="transferencia">Transferência Bancária</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (empty($dev['chave_pix'])): ?>
                        <div style="background: rgba(255,193,7,0.1); border: 1px solid var(--warning); color: var(--warning); padding: 12px; border-radius: 6px; margin: 15px 0;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Atenção:</strong> Você ainda não cadastrou uma chave PIX. 
                            <a href="<?php echo SITE_URL; ?>/developer/perfil.php" style="color: var(--warning); text-decoration: underline;">
                                Cadastre agora
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Solicitar Saque
                    </button>
                </form>
            </div>
            <?php else: ?>
                <div style="background: rgba(255,193,7,0.1); border: 1px solid var(--warning); border-radius: 10px; padding: 20px; margin-bottom: 30px; text-align: center;">
                    <i class="fas fa-info-circle" style="font-size: 32px; color: var(--warning); margin-bottom: 10px;"></i>
                    <p style="margin: 0;">
                        Saldo insuficiente para saque. Valor mínimo: <strong><?php echo formatPrice($valor_minimo_saque); ?></strong>
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Histórico de Saques -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 30px; margin-bottom: 30px;">
                <h2 style="margin-bottom: 20px;"><i class="fas fa-history"></i> Histórico de Saques</h2>
                
                <?php if (count($saques) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border);">
                                    <th style="padding: 12px; text-align: left;">Data</th>
                                    <th style="padding: 12px; text-align: left;">Valor</th>
                                    <th style="padding: 12px; text-align: center;">Método</th>
                                    <th style="padding: 12px; text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saques as $saque): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 15px;">
                                        <?php echo date('d/m/Y H:i', strtotime($saque['solicitado_em'])); ?>
                                    </td>
                                    <td style="padding: 15px; font-weight: 600;">
                                        <?php echo formatPrice($saque['valor_centavos']); ?>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <?php echo ucfirst($saque['metodo']); ?>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <span style="padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;
                                            <?php
                                            echo match($saque['status']) {
                                                'pago' => 'background: rgba(40,167,69,0.1); color: var(--success);',
                                                'processando' => 'background: rgba(76,139,245,0.1); color: var(--accent);',
                                                'solicitado' => 'background: rgba(255,193,7,0.1); color: var(--warning);',
                                                'cancelado' => 'background: rgba(220,53,69,0.1); color: var(--danger);',
                                                default => ''
                                            };
                                            ?>">
                                            <?php echo ucfirst($saque['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-receipt" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 15px;"></i>
                        <p style="color: var(--text-secondary);">Nenhum saque realizado ainda</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Movimentações -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 30px;">
                <h2 style="margin-bottom: 20px;"><i class="fas fa-list"></i> Movimentações Recentes</h2>
                
                <?php if (count($movimentacoes) > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($movimentacoes as $mov): ?>
                        <div style="display: flex; justify-content: space-between; padding: 15px; background: var(--bg-primary); border-radius: 8px;">
                            <div>
                                <strong><?php echo sanitize($mov['descricao']); ?></strong>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                                    <?php echo date('d/m/Y H:i', strtotime($mov['criado_em'])); ?>
                                </div>
                            </div>
                            <div style="font-size: 18px; font-weight: 700; 
                                <?php echo $mov['valor_centavos'] > 0 ? 'color: var(--success);' : 'color: var(--danger);'; ?>">
                                <?php echo ($mov['valor_centavos'] > 0 ? '+' : '') . formatPrice($mov['valor_centavos']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-list" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 15px;"></i>
                        <p style="color: var(--text-secondary);">Nenhuma movimentação ainda</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>