<?php
// admin/eventos.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();
if (getUserType() !== 'admin') {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$message = '';
$error = '';

// ============================================
// LÓGICA PHP (MANTIDA INTEGRALMENTE)
// ============================================

// 1. CRIAR EVENTO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['criar_evento'])) {
    try {
        $nome = trim($_POST['nome']);
        $descricao = trim($_POST['descricao']);
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nome)));
        $data_inicio = $_POST['data_inicio'];
        $data_fim = $_POST['data_fim'];
        $imagem = $_POST['imagem_banner'] ?: '/assets/images/default-event-banner.jpg';
        
        if (empty($nome) || empty($data_inicio) || empty($data_fim)) {
            throw new Exception('Preencha todos os campos obrigatórios');
        }
        
        $stmt = $pdo->prepare("INSERT INTO evento (nome, slug, descricao, imagem_banner, data_inicio, data_fim, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$nome, $slug, $descricao, $imagem, $data_inicio, $data_fim]);
        $message = 'Evento criado e banner automático gerado.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 2. TOGGLE EVENTO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_evento'])) {
    $evento_id = $_POST['evento_id'];
    $stmt = $pdo->prepare("UPDATE evento SET ativo = NOT ativo WHERE id = ?");
    $stmt->execute([$evento_id]);
    
    // Sincronizar banner
    $stmt = $pdo->prepare("UPDATE banner SET ativo = NOT ativo WHERE url_destino LIKE CONCAT('%', (SELECT slug FROM evento WHERE id = ?), '%')");
    $stmt->execute([$evento_id]);
    $message = 'Status do evento atualizado.';
}

// 3. DELETAR EVENTO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deletar_evento'])) {
    $evento_id = $_POST['evento_id'];
    $stmt = $pdo->prepare("DELETE FROM banner WHERE url_destino LIKE CONCAT('%', (SELECT slug FROM evento WHERE id = ?), '%')");
    $stmt->execute([$evento_id]);
    $stmt = $pdo->prepare("DELETE FROM evento WHERE id = ?");
    $stmt->execute([$evento_id]);
    $message = 'Evento removido permanentemente.';
}

// 4. BUSCAR DADOS
$stmt = $pdo->query("SELECT e.*, (SELECT COUNT(*) FROM banner b WHERE b.url_destino LIKE CONCAT('%', e.slug, '%')) as tem_banner FROM evento e ORDER BY e.criado_em DESC");
$eventos = $stmt->fetchAll();

// Métricas
$stmt = $pdo->prepare("SELECT * FROM metrica_venda WHERE data = CURDATE()");
$stmt->execute();
$metricas_hoje = $stmt->fetch();

if (!$metricas_hoje) {
    try {
        $pdo->query("CALL atualizar_metricas_hoje()");
        $stmt = $pdo->prepare("SELECT * FROM metrica_venda WHERE data = CURDATE()");
        $stmt->execute();
        $metricas_hoje = $stmt->fetch();
    } catch (Exception $e) {
        $metricas_hoje = ['total_vendas' => 0, 'total_receita_centavos' => 0, 'jogos_mais_vendidos' => '[]', 'devs_top' => '[]'];
    }
}

// Auditoria Cupons
$stmt = $pdo->query("SELECT dc.*, d.nome_estudio FROM dev_cupom dc JOIN desenvolvedor d ON dc.desenvolvedor_id = d.id WHERE dc.criado_em >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY dc.criado_em DESC LIMIT 30");
$cupons_log = $stmt->fetchAll();

$page_title = 'Monitoramento & Eventos - Admin';
require_once '../includes/header.php';
?>

<style>
    /* =========================================
       LAYOUT ADMIN
       ========================================= */
    .admin-wrapper {
        display: flex;
        min-height: calc(100vh - 80px); /* Ajuste conforme seu header */
    }

    .admin-content {
        flex: 1;
        padding: 40px;
        max-width: 1600px;
        margin: 0 auto;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 20px;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .section-title i { color: var(--accent); }

    /* =========================================
       METRICS CARDS
       ========================================= */
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .metric-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 25px;
        position: relative;
        overflow: hidden;
        transition: transform 0.2s, border-color 0.2s;
    }

    .metric-card:hover {
        transform: translateY(-2px);
        border-color: var(--accent);
    }

    .metric-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .metric-icon {
        width: 40px; height: 40px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        background: rgba(255,255,255,0.05);
        color: var(--text-secondary);
    }

    .metric-card.highlight .metric-icon {
        background: var(--accent);
        color: white;
    }

    .metric-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        font-variant-numeric: tabular-nums;
        line-height: 1;
        margin-bottom: 5px;
    }

    .metric-label {
        font-size: 0.85rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    /* =========================================
       TOP LISTS (SPLIT VIEW)
       ========================================= */
    .split-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 50px;
    }

    .panel {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        display: flex;
        flex-direction: column;
    }

    .panel-header {
        padding: 20px;
        border-bottom: 1px solid var(--border);
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .list-item {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid var(--border);
        gap: 15px;
    }

    .list-item:last-child { border-bottom: none; }

    .rank-badge {
        width: 24px; height: 24px;
        border-radius: 4px;
        background: var(--bg-primary);
        color: var(--text-secondary);
        font-size: 0.75rem;
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        border: 1px solid var(--border);
    }

    .list-item:nth-child(1) .rank-badge { background: var(--accent); color: white; border-color: var(--accent); }

    .item-info { flex: 1; }
    .item-name { font-weight: 600; color: var(--text-primary); display: block; margin-bottom: 2px; }
    .item-meta { font-size: 0.8rem; color: var(--text-secondary); }

    /* =========================================
       EVENT ROW STYLE
       ========================================= */
    .event-row {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .event-thumb {
        width: 120px; height: 70px;
        border-radius: 6px;
        object-fit: cover;
        background: #000;
        border: 1px solid var(--border);
    }

    .event-details { flex: 1; }
    
    .event-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 5px;
        display: flex; align-items: center; gap: 10px;
    }

    .status-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #444;
    }
    .status-dot.active { background: #2ecc71; box-shadow: 0 0 10px rgba(46, 204, 113, 0.4); }

    .event-meta-info {
        font-size: 0.85rem;
        color: var(--text-secondary);
        display: flex; gap: 15px;
    }

    .event-actions {
        display: flex;
        gap: 10px;
    }

    /* =========================================
       BUTTONS & FORMS
       ========================================= */
    .btn {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid transparent;
        display: inline-flex; align-items: center; gap: 8px;
        text-decoration: none;
    }

    .btn-primary { background: var(--accent); color: white; }
    .btn-primary:hover { filter: brightness(1.1); }

    .btn-outline {
        background: transparent;
        border-color: var(--border);
        color: var(--text-secondary);
    }
    .btn-outline:hover {
        border-color: var(--text-primary);
        color: var(--text-primary);
        background: var(--bg-primary);
    }

    .btn-danger { color: #e74c3c; background: rgba(231, 76, 60, 0.1); border-color: rgba(231, 76, 60, 0.2); }
    .btn-danger:hover { background: rgba(231, 76, 60, 0.2); }

    .btn-sm { padding: 5px 10px; font-size: 0.8rem; }

    /* =========================================
       DATA TABLE (AUDIT)
       ========================================= */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    .data-table th {
        text-align: left;
        padding: 15px;
        color: var(--text-secondary);
        font-weight: 600;
        border-bottom: 1px solid var(--border);
    }

    .data-table td {
        padding: 15px;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
    }

    .data-table tr:last-child td { border-bottom: none; }
    
    .code-pill {
        font-family: monospace;
        background: var(--bg-primary);
        padding: 4px 8px;
        border-radius: 4px;
        border: 1px solid var(--border);
        color: var(--accent);
    }

    /* =========================================
       MODAL
       ========================================= */
    .modal {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.7);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: none;
        align-items: center; justify-content: center;
    }
    .modal.active { display: flex; }

    .modal-box {
        background: var(--bg-secondary);
        width: 600px;
        max-width: 90%;
        border-radius: 12px;
        border: 1px solid var(--border);
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    }

    .modal-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;}
    .modal-body { padding: 25px; }
    .modal-footer { padding: 20px; border-top: 1px solid var(--border); text-align: right; background: var(--bg-primary); border-radius: 0 0 12px 12px;}

    .form-group { margin-bottom: 15px; }
    .form-label { display: block; margin-bottom: 8px; font-size: 0.9rem; color: var(--text-secondary); }
    .form-input {
        width: 100%;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        padding: 10px;
        border-radius: 6px;
        color: var(--text-primary);
    }
    .form-input:focus { outline: none; border-color: var(--accent); }

    @media (max-width: 900px) {
        .split-grid { grid-template-columns: 1fr; }
        .event-row { flex-direction: column; align-items: flex-start; }
        .event-thumb { width: 100%; height: 150px; }
        .event-actions { width: 100%; justify-content: flex-end; }
    }
</style>

<div class="admin-wrapper">
    <!-- Aqui entraria o include do sidebar -->
    <?php require_once 'includes/sidebar.php'; ?>

    <div class="admin-content">
        
        <!-- HEADER -->
        <div class="section-header">
            <h1 class="section-title">
                <i class="fas fa-chart-line"></i> Dashboard & Eventos
            </h1>
            <button onclick="toggleModal('createEventModal')" class="btn btn-primary">
                <i class="fas fa-plus"></i> Novo Evento Sazonal
            </button>
        </div>

        <!-- MENSAGENS -->
        <?php if ($message): ?>
            <div style="padding: 15px; background: rgba(46, 204, 113, 0.1); border: 1px solid #2ecc71; border-radius: 6px; color: #2ecc71; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="padding: 15px; background: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; border-radius: 6px; color: #e74c3c; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- 1. KPI CARDS -->
        <div class="metrics-grid">
            <div class="metric-card highlight">
                <div class="metric-header">
                    <span class="metric-label">Vendas (Hoje)</span>
                    <div class="metric-icon"><i class="fas fa-shopping-cart"></i></div>
                </div>
                <div class="metric-value"><?= number_format($metricas_hoje['total_vendas'] ?? 0) ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">Receita (Hoje)</span>
                    <div class="metric-icon"><i class="fas fa-dollar-sign"></i></div>
                </div>
                <div class="metric-value"><?= formatPrice($metricas_hoje['total_receita_centavos'] ?? 0) ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">Jogos Movimentados</span>
                    <div class="metric-icon"><i class="fas fa-gamepad"></i></div>
                </div>
                <div class="metric-value">
                    <?= count(json_decode($metricas_hoje['jogos_mais_vendidos'] ?? '[]', true)) ?>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">Devs Ativos</span>
                    <div class="metric-icon"><i class="fas fa-code"></i></div>
                </div>
                <div class="metric-value">
                    <?= count(json_decode($metricas_hoje['devs_top'] ?? '[]', true)) ?>
                </div>
            </div>
        </div>

        <!-- 2. RANKINGS (SPLIT VIEW) -->
        <div class="split-grid">
            <!-- Top Jogos -->
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-trophy"></i> Top Jogos (24h)
                </div>
                <div>
                    <?php 
                    $jogos = json_decode($metricas_hoje['jogos_mais_vendidos'] ?? '[]', true);
                    if (empty($jogos)) echo '<div style="padding:20px; text-align:center; color:var(--text-secondary);">Sem dados hoje</div>';
                    $rank = 1;
                    foreach (array_slice($jogos, 0, 5) as $jogo): ?>
                    <div class="list-item">
                        <div class="rank-badge"><?= $rank++ ?></div>
                        <div class="item-info">
                            <span class="item-name"><?= htmlspecialchars($jogo['titulo']) ?></span>
                            <span class="item-meta"><?= $jogo['vendas'] ?> unidades</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top Devs -->
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-user-secret"></i> Top Desenvolvedores (24h)
                </div>
                <div>
                    <?php 
                    $devs = json_decode($metricas_hoje['devs_top'] ?? '[]', true);
                    if (empty($devs)) echo '<div style="padding:20px; text-align:center; color:var(--text-secondary);">Sem dados hoje</div>';
                    $rank = 1;
                    foreach (array_slice($devs, 0, 5) as $dev): ?>
                    <div class="list-item">
                        <div class="rank-badge"><?= $rank++ ?></div>
                        <div class="item-info">
                            <span class="item-name"><?= htmlspecialchars($dev['nome']) ?></span>
                            <span class="item-meta"><?= formatPrice($dev['receita']) ?> gerados</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 3. GESTÃO DE EVENTOS -->
        <h2 class="section-title" style="margin-bottom: 20px;">
            <i class="fas fa-calendar-alt"></i> Eventos Sazonais
        </h2>

        <div class="events-container">
            <?php foreach ($eventos as $evento): ?>
                <div class="event-row">
                    <img src="<?= SITE_URL . $evento['imagem_banner'] ?>" class="event-thumb" alt="Banner">
                    
                    <div class="event-details">
                        <div class="event-name">
                            <span class="status-dot <?= $evento['ativo'] ? 'active' : '' ?>"></span>
                            <?= htmlspecialchars($evento['nome']) ?>
                        </div>
                        <div class="event-meta-info">
                            <span><i class="fas fa-clock"></i> <?= date('d/m H:i', strtotime($evento['data_inicio'])) ?> até <?= date('d/m H:i', strtotime($evento['data_fim'])) ?></span>
                            <span><i class="fas fa-image"></i> Banner: <?= $evento['tem_banner'] ? 'OK' : 'Pendente' ?></span>
                        </div>
                    </div>

                    <div class="event-actions">
                        <a href="<?= SITE_URL ?>/pages/evento.php?slug=<?= $evento['slug'] ?>" target="_blank" class="btn btn-outline btn-sm">
                            <i class="fas fa-eye"></i> Ver
                        </a>
                        
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="evento_id" value="<?= $evento['id'] ?>">
                            <button type="submit" name="toggle_evento" class="btn btn-outline btn-sm">
                                <i class="fas fa-power-off"></i> <?= $evento['ativo'] ? 'Pausar' : 'Ativar' ?>
                            </button>
                        </form>

                        <form method="POST" onsubmit="return confirm('Tem certeza?')" style="display:inline;">
                            <input type="hidden" name="evento_id" value="<?= $evento['id'] ?>">
                            <button type="submit" name="deletar_evento" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if(empty($eventos)): ?>
                <div style="text-align:center; padding: 40px; border: 1px dashed var(--border); border-radius: 8px; color: var(--text-secondary);">
                    Nenhum evento ativo. Crie um para impulsionar vendas.
                </div>
            <?php endif; ?>
        </div>

        <!-- 4. AUDITORIA DE CUPONS -->
        <h2 class="section-title" style="margin: 60px 0 20px;">
            <i class="fas fa-file-invoice"></i> Log de Cupons (Recentes)
        </h2>

        <div class="panel" style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Estúdio</th>
                        <th>Desconto</th>
                        <th>Uso</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cupons_log as $log): ?>
                    <tr>
                        <td><span class="code-pill"><?= $log['codigo'] ?></span></td>
                        <td><?= htmlspecialchars($log['nome_estudio']) ?></td>
                        <td>
                            <?= $log['tipo_desconto'] == 'percentual' 
                                ? $log['valor_desconto'].'%' 
                                : formatPrice($log['valor_desconto']) ?>
                        </td>
                        <td><?= $log['usos_atuais'] ?> / <?= $log['usos_maximos'] ?: '∞' ?></td>
                        <td style="color: var(--text-secondary);"><?= date('d/m/Y', strtotime($log['criado_em'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($cupons_log)): ?>
                        <tr><td colspan="5" style="text-align:center;">Nenhum registro recente.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- MODAL CREATE -->
<div id="createEventModal" class="modal">
    <div class="modal-box">
        <form method="POST">
            <div class="modal-header">
                <h3 style="color: var(--text-primary); margin:0;">Novo Evento</h3>
                <button type="button" onclick="toggleModal('createEventModal')" style="background:none; border:none; color:var(--text-secondary); cursor:pointer;"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nome do Evento</label>
                    <input type="text" name="nome" class="form-input" required placeholder="Ex: Summer Sale 2026">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descrição Curta</label>
                    <textarea name="descricao" class="form-input" rows="2"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Imagem Banner (URL)</label>
                    <input type="text" name="imagem_banner" class="form-input" placeholder="http://...">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Início</label>
                        <input type="datetime-local" name="data_inicio" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fim</label>
                        <input type="datetime-local" name="data_fim" class="form-input" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="toggleModal('createEventModal')" class="btn btn-outline" style="margin-right: 10px;">Cancelar</button>
                <button type="submit" name="criar_evento" class="btn btn-primary">Criar Evento</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleModal(id) {
        const modal = document.getElementById(id);
        modal.classList.toggle('active');
    }
    
    // Auto-refresh das métricas
    setInterval(() => {
        // Opcional: Implementar fetch silencioso aqui se desejar update sem refresh
    }, 60000);
</script>

<?php require_once '../includes/footer.php'; ?>