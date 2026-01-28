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

// Filtros
$filtro_periodo = $_GET['periodo'] ?? '30';
$filtro_status = $_GET['status'] ?? 'todos';

// Construir query base
$where_clauses = ["p.status = 'reembolsado'"];
$params = [];

// Aplicar filtro de período
if ($filtro_periodo !== 'todos') {
    $where_clauses[] = "p.reembolso_processado_em >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = (int)$filtro_periodo;
}

$where_sql = implode(' AND ', $where_clauses);

// Buscar estatísticas
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_reembolsos,
        SUM(p.total_centavos) as valor_total_reembolsado,
        COUNT(DISTINCT p.usuario_id) as usuarios_distintos
    FROM pedido p
    WHERE {$where_sql}
");
$stmt->execute($params);
$stats = $stmt->fetch();

// Buscar lista de reembolsos
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.nome_usuario,
        u.email,
        (SELECT COUNT(*) FROM item_pedido WHERE pedido_id = p.id) as total_itens
    FROM pedido p
    INNER JOIN usuario u ON p.usuario_id = u.id
    WHERE {$where_sql}
    ORDER BY p.reembolso_processado_em DESC
    LIMIT 100
");
$stmt->execute($params);
$reembolsos = $stmt->fetchAll();

$page_title = 'Gerenciar Reembolsos - Admin';
require_once '../includes/header.php';
?>

<div class="container" style="padding: 30px 0;">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-undo"></i> Gerenciar Reembolsos</h1>
        <p class="page-subtitle">Visualização e análise de reembolsos processados</p>
    </div>

    <!-- Estatísticas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px; text-align: center;">
            <div style="font-size: 36px; font-weight: 700; color: var(--danger);">
                <?php echo number_format($stats['total_reembolsos']); ?>
            </div>
            <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">Total de Reembolsos</div>
        </div>

        <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px; text-align: center;">
            <div style="font-size: 36px; font-weight: 700; color: var(--warning);">
                <?php echo formatPrice($stats['valor_total_reembolsado'] ?? 0); ?>
            </div>
            <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">Valor Total Reembolsado</div>
        </div>

        <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px; text-align: center;">
            <div style="font-size: 36px; font-weight: 700; color: var(--accent);">
                <?php echo number_format($stats['usuarios_distintos']); ?>
            </div>
            <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">Usuários Distintos</div>
        </div>
    </div>

    <!-- Filtros -->
    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 30px;">
        <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                <label class="form-label">Período</label>
                <select name="periodo" class="form-control">
                    <option value="7" <?php echo $filtro_periodo == '7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                    <option value="30" <?php echo $filtro_periodo == '30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                    <option value="90" <?php echo $filtro_periodo == '90' ? 'selected' : ''; ?>>Últimos 90 dias</option>
                    <option value="365" <?php echo $filtro_periodo == '365' ? 'selected' : ''; ?>>Último ano</option>
                    <option value="todos" <?php echo $filtro_periodo == 'todos' ? 'selected' : ''; ?>>Todos</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filtrar
            </button>
        </form>
    </div>

    <!-- Lista de Reembolsos -->
    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px;">
        <h2 style="font-size: 20px; margin-bottom: 20px;">
            <i class="fas fa-list"></i> Reembolsos Processados
        </h2>

        <?php if (count($reembolsos) > 0): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border);">
                        <th style="padding: 12px; text-align: left;">Pedido</th>
                        <th style="padding: 12px; text-align: left;">Usuário</th>
                        <th style="padding: 12px; text-align: center;">Itens</th>
                        <th style="padding: 12px; text-align: right;">Valor</th>
                        <th style="padding: 12px; text-align: center;">Processado</th>
                        <th style="padding: 12px; text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reembolsos as $reembolso): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 15px;">
                            <strong>#<?php echo $reembolso['numero']; ?></strong>
                            <br>
                            <span style="font-size: 12px; color: var(--text-secondary);">
                                Compra: <?php echo date('d/m/Y', strtotime($reembolso['criado_em'])); ?>
                            </span>
                        </td>
                        <td style="padding: 15px;">
                            <strong><?php echo sanitize($reembolso['nome_usuario']); ?></strong>
                            <br>
                            <span style="font-size: 12px; color: var(--text-secondary);">
                                <?php echo sanitize($reembolso['email']); ?>
                            </span>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <span style="background: rgba(76,139,245,0.1); color: var(--accent); padding: 4px 12px; border-radius: 4px; font-weight: 600;">
                                <?php echo $reembolso['total_itens']; ?>
                            </span>
                        </td>
                        <td style="padding: 15px; text-align: right; font-weight: 700; color: var(--danger);">
                            <?php echo formatPrice($reembolso['total_centavos']); ?>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <span style="font-size: 12px; color: var(--text-secondary);">
                                <?php echo date('d/m/Y', strtotime($reembolso['reembolso_processado_em'])); ?>
                                <br>
                                <?php echo date('H:i', strtotime($reembolso['reembolso_processado_em'])); ?>
                            </span>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <button onclick="verDetalhes(<?php echo $reembolso['id']; ?>)" 
                                    class="btn btn-sm btn-secondary">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px;"></i>
            <p>Nenhum reembolso encontrado no período selecionado</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Detalhes -->
<div id="modalDetalhes" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div style="background: var(--bg-secondary); border-radius: 15px; padding: 30px; max-width: 800px; width: 100%; border: 1px solid var(--border); margin: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-file-invoice"></i>
                Detalhes do Reembolso
            </h2>
            <button onclick="fecharModal()" class="btn btn-secondary">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="conteudoModal" style="max-height: 70vh; overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: var(--accent);"></i>
                <p style="margin-top: 15px;">Carregando...</p>
            </div>
        </div>
    </div>
</div>

<script>
async function verDetalhes(pedidoId) {
    document.getElementById('modalDetalhes').style.display = 'flex';
    
    try {
        const response = await fetch(`<?php echo SITE_URL; ?>/api/detalhes-reembolso.php?pedido_id=${pedidoId}`);
        const result = await response.json();
        
        if (result.success) {
            mostrarDetalhes(result.data);
        } else {
            document.getElementById('conteudoModal').innerHTML = `
                <div style="text-align: center; padding: 40px; color: var(--danger);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p>Erro ao carregar detalhes: ${result.error || 'Erro desconhecido'}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Erro:', error);
        document.getElementById('conteudoModal').innerHTML = `
            <div style="text-align: center; padding: 40px; color: var(--danger);">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i>
                <p>Erro ao carregar detalhes</p>
            </div>
        `;
    }
}

function mostrarDetalhes(data) {
    const pedido = data.pedido;
    const itens = data.itens;
    
    let html = `
        <div style="display: grid; gap: 20px;">
            <!-- Informações do Pedido -->
            <div style="background: var(--bg-primary); padding: 20px; border-radius: 8px;">
                <h3 style="margin-bottom: 15px;">Informações do Pedido</h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; font-size: 14px;">
                    <div>
                        <span style="color: var(--text-secondary);">Número:</span>
                        <strong> #${pedido.numero}</strong>
                    </div>
                    <div>
                        <span style="color: var(--text-secondary);">Usuário:</span>
                        <strong> ${pedido.nome_usuario}</strong>
                    </div>
                    <div>
                        <span style="color: var(--text-secondary);">Compra:</span>
                        <strong> ${formatarData(pedido.criado_em)}</strong>
                    </div>
                    <div>
                        <span style="color: var(--text-secondary);">Pagamento:</span>
                        <strong> ${formatarData(pedido.pago_em)}</strong>
                    </div>
                    <div>
                        <span style="color: var(--text-secondary);">Reembolso Solicitado:</span>
                        <strong> ${formatarData(pedido.reembolso_solicitado_em)}</strong>
                    </div>
                    <div>
                        <span style="color: var(--text-secondary);">Reembolso Processado:</span>
                        <strong> ${formatarData(pedido.reembolso_processado_em)}</strong>
                    </div>
                </div>
            </div>

            <!-- Motivo -->
            ${pedido.reembolso_motivo ? `
            <div style="background: rgba(255,193,7,0.1); border: 1px solid var(--warning); padding: 15px; border-radius: 8px;">
                <strong style="display: block; margin-bottom: 8px;">Motivo do Reembolso:</strong>
                <p style="font-size: 14px; color: var(--text-secondary); white-space: pre-wrap;">${pedido.reembolso_motivo}</p>
            </div>
            ` : ''}

            <!-- Itens -->
            <div>
                <h3 style="margin-bottom: 15px;">Itens Reembolsados (${itens.length})</h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    ${itens.map(item => `
                        <div style="background: var(--bg-primary); padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>${item.titulo}</strong>
                                <br>
                                <span style="font-size: 12px; color: var(--text-secondary);">
                                    por ${item.nome_estudio}
                                </span>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 18px; font-weight: 700; color: var(--danger);">
                                    ${formatarPreco(item.valor_final_centavos)}
                                </div>
                                ${item.desconto_centavos > 0 ? `
                                    <div style="font-size: 12px; color: var(--text-secondary); text-decoration: line-through;">
                                        ${formatarPreco(item.preco_centavos)}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>

            <!-- Total -->
            <div style="background: rgba(220,53,69,0.1); border: 1px solid var(--danger); padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 8px;">
                    Valor Total Reembolsado
                </div>
                <div style="font-size: 32px; font-weight: 700; color: var(--danger);">
                    ${formatarPreco(pedido.total_centavos)}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('conteudoModal').innerHTML = html;
}

function formatarData(dataStr) {
    if (!dataStr) return 'N/A';
    const data = new Date(dataStr);
    return data.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatarPreco(centavos) {
    return 'R$ ' + (centavos / 100).toFixed(2).replace('.', ',');
}

function fecharModal() {
    document.getElementById('modalDetalhes').style.display = 'none';
}

// Fechar modal ao clicar fora
document.getElementById('modalDetalhes').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModal();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('modalDetalhes').style.display === 'flex') {
        fecharModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>