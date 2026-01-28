<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();
if (getUserType() !== 'admin') {
    header('Location: ' . SITE_URL);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$message = '';
$message_type = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dias_reembolso = (int)($_POST['dias_reembolso'] ?? 14);
        
        // Validação
        if ($dias_reembolso < 1 || $dias_reembolso > 365) {
            throw new Exception('O período deve estar entre 1 e 365 dias');
        }
        
        $stmt = $pdo->prepare("
            UPDATE configuracao 
            SET valor = ?, atualizado_em = NOW() 
            WHERE chave = 'dias_reembolso'
        ");
        $stmt->execute([$dias_reembolso]);
        
        $message = 'Configuração atualizada com sucesso!';
        $message_type = 'success';
        
    } catch (Exception $e) {
        $message = 'Erro: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Buscar configurações atuais
$stmt = $pdo->prepare("SELECT chave, valor, descricao FROM configuracao WHERE chave IN ('dias_reembolso', 'dias_liberacao_saldo')");
$stmt->execute();
$configs = [];
while ($row = $stmt->fetch()) {
    $configs[$row['chave']] = $row;
}

$page_title = 'Configurações de Reembolso - Admin';
require_once '../includes/header.php';
?>

<div class="container" style="padding: 30px 0;">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-cog"></i> Configurações de Reembolso</h1>
        <p class="page-subtitle">Gerencie as políticas de reembolso da plataforma</p>
    </div>

    <?php if ($message): ?>
    <div style="padding: 15px; border-radius: 8px; margin-bottom: 30px; 
         background: <?php echo $message_type === 'success' ? 'rgba(40,167,69,0.1)' : 'rgba(220,53,69,0.1)'; ?>; 
         border: 1px solid <?php echo $message_type === 'success' ? 'var(--success)' : 'var(--danger)'; ?>; 
         color: <?php echo $message_type === 'success' ? 'var(--success)' : 'var(--danger)'; ?>;">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 30px;">
        <!-- Formulário -->
        <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 30px;">
            <h2 style="font-size: 20px; margin-bottom: 20px;">
                <i class="fas fa-edit"></i> Editar Configurações
            </h2>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">
                        Dias permitidos para reembolso
                        <i class="fas fa-info-circle" style="color: var(--text-secondary); cursor: help;" 
                           title="Número de dias após a compra que o usuário pode solicitar reembolso"></i>
                    </label>
                    <input type="number" 
                           name="dias_reembolso" 
                           class="form-control" 
                           value="<?php echo (int)($configs['dias_reembolso']['valor'] ?? 14); ?>"
                           min="1"
                           max="365"
                           required>
                    <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                        <?php echo $configs['dias_reembolso']['descricao'] ?? ''; ?>
                    </small>
                </div>

                <div style="background: var(--bg-primary); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="font-size: 14px; margin-bottom: 10px;">
                        <strong><i class="fas fa-lightbulb"></i> Recomendações:</strong>
                    </p>
                    <ul style="font-size: 13px; color: var(--text-secondary); margin-left: 20px;">
                        <li>7 dias: Política mais restritiva</li>
                        <li>14 dias: Padrão da indústria (recomendado)</li>
                        <li>30 dias: Política mais flexível</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> Salvar Configurações
                </button>
            </form>
        </div>

        <!-- Informações -->
        <div>
            <!-- Estatísticas Rápidas -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px; margin-bottom: 20px;">
                <h3 style="font-size: 18px; margin-bottom: 15px;">
                    <i class="fas fa-chart-line"></i> Estatísticas
                </h3>
                
                <?php
                // Buscar estatísticas de reembolso
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(total_centavos) as valor_total
                    FROM pedido 
                    WHERE status = 'reembolsado'
                    AND reembolso_processado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $stmt->execute();
                $stats = $stmt->fetch();
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total_vendas
                    FROM pedido 
                    WHERE status = 'pago'
                    AND pago_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $stmt->execute();
                $vendas = $stmt->fetch();
                
                $taxa_reembolso = $vendas['total_vendas'] > 0 
                    ? ($stats['total'] / $vendas['total_vendas']) * 100 
                    : 0;
                ?>
                
                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border);">
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">
                        Reembolsos (30 dias)
                    </div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--danger);">
                        <?php echo number_format($stats['total']); ?>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border);">
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">
                        Valor Reembolsado
                    </div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--warning);">
                        <?php echo formatPrice($stats['valor_total'] ?? 0); ?>
                    </div>
                </div>
                
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">
                        Taxa de Reembolso
                    </div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--accent);">
                        <?php echo number_format($taxa_reembolso, 2); ?>%
                    </div>
                </div>
            </div>

            <!-- Configurações Relacionadas -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px;">
                <h3 style="font-size: 18px; margin-bottom: 15px;">
                    <i class="fas fa-link"></i> Configurações Relacionadas
                </h3>
                
                <div style="background: var(--bg-primary); padding: 15px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 14px;">Dias para Liberação de Saldo</span>
                        <strong style="color: var(--accent);">
                            <?php echo (int)($configs['dias_liberacao_saldo']['valor'] ?? 7); ?> dias
                        </strong>
                    </div>
                    <p style="font-size: 12px; color: var(--text-secondary);">
                        Período que o saldo fica pendente antes de ser liberado para saque
                    </p>
                </div>
            </div>

            <!-- Avisos -->
            <div style="background: rgba(255,193,7,0.1); border: 1px solid var(--warning); border-radius: 10px; padding: 20px; margin-top: 20px;">
                <h3 style="font-size: 16px; margin-bottom: 10px; color: var(--warning);">
                    <i class="fas fa-exclamation-triangle"></i> Atenção
                </h3>
                <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6;">
                    Alterações nas configurações de reembolso afetam apenas novos pedidos. 
                    Pedidos existentes mantêm as regras vigentes no momento da compra.
                </p>
            </div>

            <!-- Botão de Ação -->
            <a href="<?php echo SITE_URL; ?>/admin/reembolsos.php" class="btn btn-secondary" style="width: 100%; margin-top: 20px;">
                <i class="fas fa-list"></i> Ver Todos os Reembolsos
            </a>
        </div>
    </div>
</div>

<style>
@media (max-width: 968px) {
    div[style*="grid-template-columns: 1fr 400px"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>