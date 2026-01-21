<?php
// admin/saques.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

if (getUserType() !== 'admin') {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$success = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $saque_id = (int)($_POST['saque_id'] ?? 0);
    $action = $_POST['action'];
    
    try {
        if ($action === 'aprovar') {
            $stmt = $pdo->prepare("
                UPDATE saque 
                SET status = 'pago', 
                    processado_por = ?, 
                    processado_em = NOW()
                WHERE id = ? AND status = 'solicitado'
            ");
            $stmt->execute([$_SESSION['user_id'], $saque_id]);
            $success = 'Saque aprovado com sucesso!';
            
        } elseif ($action === 'rejeitar') {
            $observacao = trim($_POST['observacao'] ?? '');
            
            $pdo->beginTransaction();
            
            // Buscar dados do saque
            $stmt = $pdo->prepare("SELECT * FROM saque WHERE id = ?");
            $stmt->execute([$saque_id]);
            $saque = $stmt->fetch();
            
            if ($saque && $saque['status'] === 'solicitado') {
                // Cancelar saque
                $stmt = $pdo->prepare("
                    UPDATE saque 
                    SET status = 'cancelado', 
                        processado_por = ?, 
                        processado_em = NOW(),
                        observacao = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $observacao, $saque_id]);
                
                // Devolver saldo
                $stmt = $pdo->prepare("
                    UPDATE desenvolvedor_saldo 
                    SET saldo_disponivel_centavos = saldo_disponivel_centavos + ?
                    WHERE desenvolvedor_id = ?
                ");
                $stmt->execute([$saque['valor_centavos'], $saque['desenvolvedor_id']]);
                
                // Registrar movimentação
                $stmt = $pdo->prepare("
                    INSERT INTO desenvolvedor_movimentacao 
                    (desenvolvedor_id, tipo, valor_centavos, descricao, criado_em)
                    VALUES (?, 'ajuste', ?, 'Saque cancelado - devolução', NOW())
                ");
                $stmt->execute([$saque['desenvolvedor_id'], $saque['valor_centavos']]);
                
                $pdo->commit();
                $success = 'Saque rejeitado e saldo devolvido!';
            } else {
                throw new Exception('Saque não encontrado ou já processado');
            }
            
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Erro: ' . $e->getMessage();
    }
}

// Filtros
$filtro_status = $_GET['status'] ?? 'todos';
$filtro_busca = $_GET['busca'] ?? '';

// Query base
$where = ["1=1"];
$params = [];

if ($filtro_status !== 'todos') {
    $where[] = "s.status = ?";
    $params[] = $filtro_status;
}

if ($filtro_busca) {
    $where[] = "(d.nome_estudio LIKE ? OR u.nome_usuario LIKE ? OR u.email LIKE ?)";
    $busca_param = "%{$filtro_busca}%";
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
}

$where_sql = implode(" AND ", $where);

// Buscar saques
$stmt = $pdo->prepare("
    SELECT s.*, 
           d.nome_estudio,
           u.nome_usuario,
           u.email,
           admin.nome_usuario as processado_por_nome
    FROM saque s
    JOIN desenvolvedor d ON s.desenvolvedor_id = d.id
    JOIN usuario u ON d.usuario_id = u.id
    LEFT JOIN usuario admin ON s.processado_por = admin.id
    WHERE {$where_sql}
    ORDER BY 
        CASE s.status 
            WHEN 'solicitado' THEN 1
            WHEN 'processando' THEN 2
            ELSE 3
        END,
        s.solicitado_em DESC
    LIMIT 50
");
$stmt->execute($params);
$saques = $stmt->fetchAll();

// Estatísticas
$stmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN status = 'solicitado' THEN 1 END) as pendentes,
        COUNT(CASE WHEN status = 'pago' THEN 1 END) as pagos,
        SUM(CASE WHEN status = 'solicitado' THEN valor_centavos ELSE 0 END) as valor_pendente,
        SUM(CASE WHEN status = 'pago' THEN valor_centavos ELSE 0 END) as valor_pago
    FROM saque
");
$stats = $stmt->fetch();

$status_config = [
    'solicitado' => ['label' => 'Solicitado', 'color' => '#f59e0b', 'icon' => 'fa-clock'],
    'processando' => ['label' => 'Processando', 'color' => '#4C8BF5', 'icon' => 'fa-spinner'],
    'pago' => ['label' => 'Pago', 'color' => '#10b981', 'icon' => 'fa-check-circle'],
    'cancelado' => ['label' => 'Cancelado', 'color' => '#6b7280', 'icon' => 'fa-times-circle'],
    'erro' => ['label' => 'Erro', 'color' => '#ef4444', 'icon' => 'fa-exclamation-circle']
];

$page_title = 'Gerenciar Saques - Admin';
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title">
                    <i class="fas fa-wallet"></i> Gerenciar Saques
                </h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pendentes</h3>
                        <div class="stat-value"><?= $stats['pendentes'] ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Valor Pendente</h3>
                        <div class="stat-value"><?= formatPrice($stats['valor_pendente']) ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pagos</h3>
                        <div class="stat-value"><?= $stats['pagos'] ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Pago</h3>
                        <div class="stat-value"><?= formatPrice($stats['valor_pago']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters">
                <form method="GET" class="filters-grid">
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="todos" <?= $filtro_status === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="solicitado" <?= $filtro_status === 'solicitado' ? 'selected' : '' ?>>Solicitado</option>
                            <option value="processando" <?= $filtro_status === 'processando' ? 'selected' : '' ?>>Processando</option>
                            <option value="pago" <?= $filtro_status === 'pago' ? 'selected' : '' ?>>Pago</option>
                            <option value="cancelado" <?= $filtro_status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Buscar</label>
                        <input type="text" 
                               name="busca" 
                               placeholder="Desenvolvedor ou email..." 
                               value="<?= htmlspecialchars($filtro_busca) ?>">
                    </div>
                    
                    <div class="filter-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                    
                    <?php if ($filtro_status !== 'todos' || $filtro_busca): ?>
                    <div class="filter-group" style="display: flex; align-items: flex-end;">
                        <a href="<?= SITE_URL; ?>/admin/saques.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabela de Saques -->
            <div class="data-table-wrapper">
                <div class="table-header">
                    <h2>Solicitações de Saque (<?= count($saques) ?>)</h2>
                </div>

                <?php if (count($saques) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Desenvolvedor</th>
                                <th>Valor</th>
                                <th>Método</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saques as $saque): 
                                $status = $status_config[$saque['status']];
                            ?>
                            <tr>
                                <td><strong>#<?= $saque['id'] ?></strong></td>
                                <td>
                                    <div style="font-weight: 600;"><?= sanitize($saque['nome_estudio']) ?></div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">
                                        <?= sanitize($saque['nome_usuario']) ?>
                                    </div>
                                </td>
                                <td><strong style="color: var(--success);"><?= formatPrice($saque['valor_centavos']) ?></strong></td>
                                <td>
                                    <?php if ($saque['metodo'] === 'pix'): ?>
                                        <i class="fas fa-qrcode"></i> PIX
                                    <?php else: ?>
                                        <i class="fas fa-university"></i> Transferência
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: <?= $status['color'] ?>20; color: <?= $status['color'] ?>;">
                                        <i class="fas <?= $status['icon'] ?>"></i> <?= $status['label'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?= date('d/m/Y', strtotime($saque['solicitado_em'])) ?></div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">
                                        <?= date('H:i', strtotime($saque['solicitado_em'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($saque['status'] === 'solicitado'): ?>
                                        <div class="action-buttons">
                                            <button type="button" 
                                                    class="btn-icon view" 
                                                    onclick="verDetalhes(<?= htmlspecialchars(json_encode($saque)) ?>)"
                                                    title="Ver Detalhes">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn-icon edit" 
                                                    onclick="aprovarSaque(<?= $saque['id'] ?>)"
                                                    title="Aprovar">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn-icon delete" 
                                                    onclick="rejeitarSaque(<?= $saque['id'] ?>)"
                                                    title="Rejeitar">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button type="button" 
                                                class="btn-icon view" 
                                                onclick="verDetalhes(<?= htmlspecialchars(json_encode($saque)) ?>)"
                                                title="Ver Detalhes">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px;">
                        <i class="fas fa-inbox" style="font-size: 64px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 20px;"></i>
                        <p style="color: var(--text-secondary);">Nenhuma solicitação encontrada</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalhes -->
<div class="modal-overlay" id="modalDetalhes">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Detalhes do Saque</h3>
        </div>
        <div class="modal-body" id="modalDetalhesContent"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalDetalhes')">Fechar</button>
        </div>
    </div>
</div>

<!-- Modal Aprovar -->
<div class="modal-overlay" id="modalAprovar">
    <div class="modal-box">
        <div class="modal-header">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center; color: #10b981; font-size: 24px;">
                <i class="fas fa-check"></i>
            </div>
            <div>
                <h3>Aprovar Saque</h3>
            </div>
        </div>
        <div class="modal-body">
            <p>Confirma a aprovação deste saque? O desenvolvedor será notificado.</p>
        </div>
        <form method="POST" id="formAprovar">
            <input type="hidden" name="action" value="aprovar">
            <input type="hidden" name="saque_id" id="aprovarSaqueId">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalAprovar')">Cancelar</button>
                <button type="submit" class="btn btn-success">Aprovar Saque</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Rejeitar -->
<div class="modal-overlay" id="modalRejeitar">
    <div class="modal-box">
        <div class="modal-header">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); display: flex; align-items: center; justify-content: center; color: #ef4444; font-size: 24px;">
                <i class="fas fa-times"></i>
            </div>
            <div>
                <h3>Rejeitar Saque</h3>
            </div>
        </div>
        <div class="modal-body">
            <p>O saldo será devolvido ao desenvolvedor.</p>
            <div class="form-group">
                <label class="form-label">Motivo (opcional)</label>
                <textarea name="observacao" 
                          form="formRejeitar" 
                          class="form-control" 
                          placeholder="Explique o motivo da rejeição..."
                          rows="4"></textarea>
            </div>
        </div>
        <form method="POST" id="formRejeitar">
            <input type="hidden" name="action" value="rejeitar">
            <input type="hidden" name="saque_id" id="rejeitarSaqueId">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalRejeitar')">Cancelar</button>
                <button type="submit" class="btn btn-danger">Rejeitar Saque</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.modal-overlay.active {
    display: flex;
}

.modal-box {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 25px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.modal-header h3 {
    font-size: 20px;
    margin: 0;
}

.modal-body {
    margin-bottom: 20px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.alert {
    padding: 15px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}
</style>

<script>
function verDetalhes(saque) {
    const modal = document.getElementById('modalDetalhes');
    const content = document.getElementById('modalDetalhesContent');
    
    const statusConfig = {
        'solicitado': { label: 'Solicitado', color: '#f59e0b' },
        'processando': { label: 'Processando', color: '#4C8BF5' },
        'pago': { label: 'Pago', color: '#10b981' },
        'cancelado': { label: 'Cancelado', color: '#6b7280' },
        'erro': { label: 'Erro', color: '#ef4444' }
    };
    
    const status = statusConfig[saque.status] || statusConfig['solicitado'];
    
    content.innerHTML = `
        <div style="display: grid; gap: 15px;">
            <div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Desenvolvedor</div>
                <div style="font-weight: 600;">${saque.nome_estudio}</div>
                <div style="font-size: 13px; color: var(--text-secondary);">${saque.email}</div>
            </div>
            
            <div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Valor</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--success);">
                    ${formatPrice(saque.valor_centavos)}
                </div>
            </div>
            
            <div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Método</div>
                <div>${saque.metodo === 'pix' ? 'PIX' : 'Transferência Bancária'}</div>
            </div>
            
            <div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Status</div>
                <span style="display: inline-block; padding: 6px 12px; border-radius: 20px; background: ${status.color}20; color: ${status.color}; font-weight: 600;">
                    ${status.label}
                </span>
            </div>
            
            <div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Data da Solicitação</div>
                <div>${new Date(saque.solicitado_em).toLocaleString('pt-BR')}</div>
            </div>
            
            ${saque.processado_em ? `
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Processado em</div>
                    <div>${new Date(saque.processado_em).toLocaleString('pt-BR')}</div>
                    ${saque.processado_por_nome ? `<div style="font-size: 13px; color: var(--text-secondary);">por ${saque.processado_por_nome}</div>` : ''}
                </div>
            ` : ''}
            
            ${saque.observacao ? `
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Observação</div>
                    <div style="padding: 10px; background: var(--bg-primary); border-radius: 6px;">
                        ${saque.observacao}
                    </div>
                </div>
            ` : ''}
        </div>
    `;
    
    modal.classList.add('active');
}

function aprovarSaque(id) {
    document.getElementById('aprovarSaqueId').value = id;
    document.getElementById('modalAprovar').classList.add('active');
}

function rejeitarSaque(id) {
    document.getElementById('rejeitarSaqueId').value = id;
    document.getElementById('modalRejeitar').classList.add('active');
}

function fecharModal(id) {
    document.getElementById(id).classList.remove('active');
}

function formatPrice(centavos) {
    return 'R$ ' + (centavos / 100).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
    }
});

// Fechar modal clicando fora
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>