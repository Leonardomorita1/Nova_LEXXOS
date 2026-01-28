<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Buscar configuração de dias permitidos para reembolso
$stmt = $pdo->prepare("SELECT valor FROM configuracao WHERE chave = 'dias_reembolso'");
$stmt->execute();
$dias_reembolso = (int)($stmt->fetch()['valor'] ?? 14);

// Buscar pedidos do usuário com informações de itens
$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM item_pedido WHERE pedido_id = p.id) as total_itens,
           DATEDIFF(NOW(), p.pago_em) as dias_desde_compra
    FROM pedido p
    WHERE p.usuario_id = ?
    ORDER BY p.criado_em DESC
");
$stmt->execute([$user_id]);
$pedidos = $stmt->fetchAll();

$page_title = 'Meus Pedidos - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container" style="padding: 30px 0;">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-receipt"></i> Meus Pedidos</h1>
        <p class="page-subtitle"><?php echo count($pedidos); ?> pedido<?php echo count($pedidos) != 1 ? 's' : ''; ?></p>
    </div>
    
    <?php if (count($pedidos) > 0): ?>
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($pedidos as $pedido): 
                $pode_reembolsar = false;
                $motivo_nao_reembolso = '';
                
                // Verificar se pode solicitar reembolso
                if ($pedido['status'] === 'pago' && $pedido['pago_em']) {
                    $dias_desde = $pedido['dias_desde_compra'];
                    
                    if ($dias_desde <= $dias_reembolso) {
                        $pode_reembolsar = true;
                    } else {
                        $motivo_nao_reembolso = "Período de reembolso expirado (máximo {$dias_reembolso} dias)";
                    }
                } elseif ($pedido['status'] === 'reembolsado') {
                    $motivo_nao_reembolso = 'Pedido já reembolsado';
                } elseif ($pedido['status'] === 'cancelado') {
                    $motivo_nao_reembolso = 'Pedido cancelado';
                } else {
                    $motivo_nao_reembolso = 'Pedido não elegível para reembolso';
                }
            ?>
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h3 style="font-size: 18px; margin-bottom: 5px;">Pedido #<?php echo $pedido['numero']; ?></h3>
                        <p style="color: var(--text-secondary); font-size: 14px;">
                            <?php echo date('d/m/Y H:i', strtotime($pedido['criado_em'])); ?>
                        </p>
                        <?php if ($pedido['pago_em']): ?>
                        <p style="color: var(--text-secondary); font-size: 12px; margin-top: 5px;">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i>
                            Pago em <?php echo date('d/m/Y H:i', strtotime($pedido['pago_em'])); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: right;">
                        <span style="display: inline-block; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; 
                            <?php
                            echo match($pedido['status']) {
                                'pago' => 'background: rgba(40,167,69,0.1); color: var(--success);',
                                'pendente' => 'background: rgba(255,193,7,0.1); color: var(--warning);',
                                'cancelado' => 'background: rgba(220,53,69,0.1); color: var(--danger);',
                                'reembolsado' => 'background: rgba(108,117,125,0.1); color: var(--text-secondary);',
                                default => 'background: var(--bg-primary); color: var(--text-secondary);'
                            };
                            ?>">
                            <?php 
                            echo match($pedido['status']) {
                                'pago' => 'Pago',
                                'pendente' => 'Pendente',
                                'cancelado' => 'Cancelado',
                                'reembolsado' => 'Reembolsado',
                                'processando' => 'Processando',
                                'erro' => 'Erro',
                                default => ucfirst($pedido['status'])
                            };
                            ?>
                        </span>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; padding: 15px; background: var(--bg-primary); border-radius: 8px; margin-bottom: 15px;">
                    <div>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">Total de Itens</p>
                        <p style="font-size: 18px; font-weight: 600;"><?php echo $pedido['total_itens']; ?></p>
                    </div>
                    <div>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">Valor Total</p>
                        <p style="font-size: 18px; font-weight: 600; color: var(--accent);">
                            <?php echo formatPrice($pedido['total_centavos']); ?>
                        </p>
                    </div>
                    <div>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">Pagamento</p>
                        <p style="font-size: 14px; font-weight: 600;">
                            <?php 
                            $metodo = match($pedido['metodo_pagamento']) {
                                'pix' => 'PIX',
                                'cartao_credito' => 'Cartão de Crédito',
                                'boleto' => 'Boleto',
                                default => ucfirst(str_replace('_', ' ', $pedido['metodo_pagamento'] ?? 'N/A'))
                            };
                            echo $metodo;
                            ?>
                        </p>
                    </div>
                    <?php if ($pedido['status'] === 'pago' && $pedido['dias_desde_compra'] !== null): ?>
                    <div>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">Tempo desde compra</p>
                        <p style="font-size: 14px; font-weight: 600;">
                            <?php echo $pedido['dias_desde_compra']; ?> dia<?php echo $pedido['dias_desde_compra'] != 1 ? 's' : ''; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <a href="<?php echo SITE_URL; ?>/user/pedido-detalhes.php?id=<?php echo $pedido['id']; ?>" 
                       class="btn btn-secondary">
                        <i class="fas fa-eye"></i> Ver Detalhes
                    </a>
                    
                    <?php if ($pode_reembolsar): ?>
                        <button onclick="solicitarReembolso(<?php echo $pedido['id']; ?>, '<?php echo $pedido['numero']; ?>')" 
                                class="btn btn-danger">
                            <i class="fas fa-undo"></i> Solicitar Reembolso
                        </button>
                        <span style="font-size: 12px; color: var(--text-secondary);">
                            (<?php echo ($dias_reembolso - $pedido['dias_desde_compra']); ?> dias restantes)
                        </span>
                    <?php elseif ($motivo_nao_reembolso): ?>
                        <span style="font-size: 12px; color: var(--text-secondary); padding: 8px 12px; background: var(--bg-primary); border-radius: 4px;">
                            <i class="fas fa-info-circle"></i> <?php echo $motivo_nao_reembolso; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border-radius: 15px;">
            <i class="fas fa-receipt" style="font-size: 64px; color: var(--text-secondary); margin-bottom: 20px;"></i>
            <h2>Nenhum pedido realizado</h2>
            <p style="color: var(--text-secondary); margin-bottom: 30px;">Comece a comprar jogos incríveis!</p>
            <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-primary">
                <i class="fas fa-search"></i> Explorar Jogos
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de Confirmação de Reembolso -->
<div id="modalReembolso" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--bg-secondary); border-radius: 15px; padding: 30px; max-width: 500px; width: 90%; border: 1px solid var(--border);">
        <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
            Confirmar Reembolso
        </h2>
        
        <p style="color: var(--text-secondary); margin-bottom: 20px;">
            Você está prestes a solicitar o reembolso do pedido <strong id="numeroPedidoModal"></strong>.
        </p>

        <div style="background: var(--bg-primary); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <p style="font-size: 14px; margin-bottom: 10px;"><strong>O que acontecerá:</strong></p>
            <ul style="font-size: 13px; color: var(--text-secondary); list-style: none; padding: 0;">
                <li style="margin-bottom: 8px;"><i class="fas fa-check" style="color: var(--success); margin-right: 8px;"></i> Os jogos serão removidos da sua biblioteca</li>
                <li style="margin-bottom: 8px;"><i class="fas fa-check" style="color: var(--success); margin-right: 8px;"></i> O pedido será marcado como reembolsado</li>
                <li><i class="fas fa-info-circle" style="color: var(--accent); margin-right: 8px;"></i> Processo irreversível</li>
            </ul>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label class="form-label">Motivo do reembolso (opcional)</label>
            <textarea id="motivoReembolso" class="form-control" rows="3" placeholder="Descreva o motivo do reembolso..."></textarea>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button onclick="fecharModalReembolso()" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button onclick="confirmarReembolso()" class="btn btn-danger">
                <i class="fas fa-check"></i> Confirmar Reembolso
            </button>
        </div>
    </div>
</div>

<script>
let pedidoReembolsoId = null;

function solicitarReembolso(pedidoId, numeroPedido) {
    pedidoReembolsoId = pedidoId;
    document.getElementById('numeroPedidoModal').textContent = numeroPedido;
    document.getElementById('motivoReembolso').value = '';
    document.getElementById('modalReembolso').style.display = 'flex';
}

function fecharModalReembolso() {
    document.getElementById('modalReembolso').style.display = 'none';
    pedidoReembolsoId = null;
}

async function confirmarReembolso() {
    if (!pedidoReembolsoId) return;

    const motivo = document.getElementById('motivoReembolso').value;
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>/api/processar-reembolso.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                pedido_id: pedidoReembolsoId,
                motivo: motivo
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('Reembolso processado com sucesso! Os jogos foram removidos da sua biblioteca.');
            window.location.reload();
        } else {
            alert('Erro ao processar reembolso: ' + (result.error || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao processar reembolso. Por favor, tente novamente.');
    }
}

// Fechar modal ao clicar fora
document.getElementById('modalReembolso').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalReembolso();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('modalReembolso').style.display === 'flex') {
        fecharModalReembolso();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>