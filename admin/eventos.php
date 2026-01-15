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
// CRIAR EVENTO SAZONAL
// ============================================
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
        
        // Inserir evento (trigger criará o banner automaticamente)
        $stmt = $pdo->prepare("
            INSERT INTO evento (nome, slug, descricao, imagem_banner, data_inicio, data_fim, ativo) 
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$nome, $slug, $descricao, $imagem, $data_inicio, $data_fim]);
        
        $message = 'Evento criado! Banner automático adicionado ao carrossel.';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ============================================
// TOGGLE EVENTO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_evento'])) {
    $evento_id = $_POST['evento_id'];
    
    $stmt = $pdo->prepare("UPDATE evento SET ativo = NOT ativo WHERE id = ?");
    $stmt->execute([$evento_id]);
    
    // Atualizar banner correspondente
    $stmt = $pdo->prepare("
        UPDATE banner SET ativo = NOT ativo 
        WHERE url_destino LIKE CONCAT('%', (SELECT slug FROM evento WHERE id = ?), '%')
    ");
    $stmt->execute([$evento_id]);
    
    $message = 'Status do evento atualizado';
}

// ============================================
// DELETAR EVENTO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deletar_evento'])) {
    $evento_id = $_POST['evento_id'];
    
    // Deletar banner associado
    $stmt = $pdo->prepare("
        DELETE FROM banner 
        WHERE url_destino LIKE CONCAT('%', (SELECT slug FROM evento WHERE id = ?), '%')
    ");
    $stmt->execute([$evento_id]);
    
    // Deletar evento
    $stmt = $pdo->prepare("DELETE FROM evento WHERE id = ?");
    $stmt->execute([$evento_id]);
    
    $message = 'Evento e banner excluídos';
}

// ============================================
// BUSCAR EVENTOS
// ============================================
$stmt = $pdo->query("
    SELECT e.*,
           (SELECT COUNT(*) FROM banner b WHERE b.url_destino LIKE CONCAT('%', e.slug, '%')) as tem_banner
    FROM evento e
    ORDER BY e.criado_em DESC
");
$eventos = $stmt->fetchAll();

// ============================================
// BUSCAR MÉTRICAS DO DIA
// ============================================
$stmt = $pdo->prepare("SELECT * FROM metrica_venda WHERE data = CURDATE()");
$stmt->execute();
$metricas_hoje = $stmt->fetch();

if (!$metricas_hoje) {
    try {
        // Gerar métricas se não existirem
        $pdo->query("CALL atualizar_metricas_hoje()");
        
        // Buscar novamente
        $stmt = $pdo->prepare("SELECT * FROM metrica_venda WHERE data = CURDATE()");
        $stmt->execute();
        $metricas_hoje = $stmt->fetch();
    } catch (Exception $e) {
        // Se falhar, criar registro vazio
        $metricas_hoje = [
            'total_vendas' => 0,
            'total_receita_centavos' => 0,
            'jogos_mais_vendidos' => '[]',
            'devs_top' => '[]'
        ];
    }
}

// ============================================
// AUDITORIA DE CUPONS (últimos 30 dias)
// ============================================
$stmt = $pdo->query("
    SELECT 
        dc.codigo,
        dc.tipo_desconto,
        dc.valor_desconto,
        dc.usos_atuais,
        dc.usos_maximos,
        dc.criado_em,
        d.nome_estudio,
        j.titulo as jogo_titulo
    FROM dev_cupom dc
    JOIN desenvolvedor d ON dc.desenvolvedor_id = d.id
    LEFT JOIN jogo j ON dc.jogo_id = j.id
    WHERE dc.criado_em >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY dc.criado_em DESC
    LIMIT 50
");
$cupons_log = $stmt->fetchAll();

$page_title = 'Eventos e Monitoramento - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.admin-eventos-container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }

/* Dashboard Cards */
.dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 40px; }
.metric-card { background: linear-gradient(135deg, var(--bg-secondary), var(--bg-primary)); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
.metric-card.primary { border-left: 4px solid var(--accent); }
.metric-card.success { border-left: 4px solid var(--success); }
.metric-card.warning { border-left: 4px solid var(--warning); }
.metric-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px; }
.metric-icon.primary { background: rgba(76, 139, 245, 0.2); color: var(--accent); }
.metric-icon.success { background: rgba(46, 204, 113, 0.2); color: var(--success); }
.metric-icon.warning { background: rgba(255, 193, 7, 0.2); color: var(--warning); }
.metric-value { font-size: 32px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px; }
.metric-label { font-size: 14px; color: var(--text-secondary); }

/* Eventos */
.eventos-list { display: flex; flex-direction: column; gap: 20px; }
.evento-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; display: flex; }
.evento-banner { width: 300px; height: 180px; object-fit: cover; }
.evento-content { flex: 1; padding: 24px; }
.evento-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; }
.evento-title { font-size: 22px; font-weight: 700; color: var(--text-primary); }
.evento-meta { display: flex; gap: 20px; margin: 12px 0; font-size: 14px; color: var(--text-secondary); }
.evento-actions { display: flex; gap: 10px; margin-top: 16px; }

/* Top Lists */
.top-list { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
.top-list-item { display: flex; align-items: center; gap: 16px; padding: 12px; border-radius: 8px; margin-bottom: 8px; background: var(--bg-primary); }
.top-rank { width: 32px; height: 32px; border-radius: 50%; background: var(--accent); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; }
.top-info { flex: 1; }
.top-name { font-weight: 600; color: var(--text-primary); }
.top-value { font-size: 14px; color: var(--text-secondary); }

/* Modal */
.modal { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
.modal.active { display: flex; }
.modal-content { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 16px; max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; }
.modal-header { padding: 24px; border-bottom: 1px solid var(--border); }
.modal-body { padding: 24px; }
.modal-footer { padding: 24px; border-top: 1px solid var(--border); display: flex; gap: 12px; justify-content: flex-end; }

.form-group { margin-bottom: 20px; }
.form-label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-primary); }
.form-control { width: 100%; padding: 12px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); }
.form-control:focus { outline: none; border-color: var(--accent); }

.btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
.btn-primary { background: var(--accent); color: white; }
.btn-secondary { background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border); }
.btn-danger { background: var(--danger); color: white; }
.btn-success { background: var(--success); color: white; }

.alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
.alert-success { background: rgba(46, 204, 113, 0.2); color: var(--success); border: 1px solid var(--success); }
.alert-error { background: rgba(220, 53, 69, 0.2); color: var(--danger); border: 1px solid var(--danger); }

.badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.badge-success { background: rgba(46, 204, 113, 0.2); color: var(--success); }
.badge-secondary { background: rgba(149, 165, 166, 0.2); color: var(--text-secondary); }

@media (max-width: 768px) {
    .evento-card { flex-direction: column; }
    .evento-banner { width: 100%; height: 200px; }
}
</style>

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-eventos-container">
                
                <div class="admin-header">
                    <h1 class="admin-title"><i class="fas fa-chart-line"></i> Dashboard de Monitoramento</h1>
                    <button onclick="openModal('eventoModal')" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Criar Evento Sazonal
                    </button>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
                <?php endif; ?>
                
                <!-- Métricas em Tempo Real -->
                <h2 style="margin: 40px 0 20px; font-size: 20px; color: var(--text-primary);">
                    <i class="fas fa-tachometer-alt"></i> Métricas de Hoje
                </h2>
                <div class="dashboard-grid">
                    <div class="metric-card primary">
                        <div class="metric-icon primary">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="metric-value"><?= $metricas_hoje['total_vendas'] ?? 0 ?></div>
                        <div class="metric-label">Vendas Realizadas</div>
                    </div>
                    
                    <div class="metric-card success">
                        <div class="metric-icon success">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="metric-value"><?= formatPrice($metricas_hoje['total_receita_centavos'] ?? 0) ?></div>
                        <div class="metric-label">Receita Total</div>
                    </div>
                    
                    <div class="metric-card warning">
                        <div class="metric-icon warning">
                            <i class="fas fa-gamepad"></i>
                        </div>
                        <div class="metric-value">
                            <?php
                            $jogos_vendidos = 0;
                            if ($metricas_hoje && $metricas_hoje['jogos_mais_vendidos']) {
                                $jogos = json_decode($metricas_hoje['jogos_mais_vendidos'], true);
                                $jogos_vendidos = count($jogos);
                            }
                            echo $jogos_vendidos;
                            ?>
                        </div>
                        <div class="metric-label">Jogos Diferentes Vendidos</div>
                    </div>
                    
                    <div class="metric-card primary">
                        <div class="metric-icon primary">
                            <i class="fas fa-code"></i>
                        </div>
                        <div class="metric-value">
                            <?php
                            $devs_ativos = 0;
                            if ($metricas_hoje && $metricas_hoje['devs_top']) {
                                $devs = json_decode($metricas_hoje['devs_top'], true);
                                $devs_ativos = count($devs);
                            }
                            echo $devs_ativos;
                            ?>
                        </div>
                        <div class="metric-label">Devs com Vendas Hoje</div>
                    </div>
                </div>
                
                <!-- Top 10 -->
                <?php if ($metricas_hoje): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 40px 0;">
                    <!-- Top Jogos -->
                    <div class="top-list">
                        <h3 style="margin-bottom: 20px; color: var(--text-primary);">
                            <i class="fas fa-trophy"></i> Top 10 Jogos Hoje
                        </h3>
                        <?php
                        $jogos = json_decode($metricas_hoje['jogos_mais_vendidos'], true) ?? [];
                        $rank = 1;
                        foreach ($jogos as $jogo):
                        ?>
                            <div class="top-list-item">
                                <div class="top-rank"><?= $rank++ ?></div>
                                <div class="top-info">
                                    <div class="top-name"><?= htmlspecialchars($jogo['titulo']) ?></div>
                                    <div class="top-value"><?= $jogo['vendas'] ?> vendas</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($jogos)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                Nenhuma venda hoje
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Top Devs -->
                    <div class="top-list">
                        <h3 style="margin-bottom: 20px; color: var(--text-primary);">
                            <i class="fas fa-code"></i> Top 10 Desenvolvedores Hoje
                        </h3>
                        <?php
                        $devs = json_decode($metricas_hoje['devs_top'], true) ?? [];
                        $rank = 1;
                        foreach ($devs as $dev):
                        ?>
                            <div class="top-list-item">
                                <div class="top-rank"><?= $rank++ ?></div>
                                <div class="top-info">
                                    <div class="top-name"><?= htmlspecialchars($dev['nome']) ?></div>
                                    <div class="top-value">
                                        <?= $dev['vendas'] ?> vendas · <?= formatPrice($dev['receita']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($devs)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                Nenhuma venda hoje
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Eventos Sazonais -->
                <h2 style="margin: 60px 0 20px; font-size: 20px; color: var(--text-primary);">
                    <i class="fas fa-calendar-alt"></i> Eventos Sazonais
                </h2>
                
                <div class="eventos-list">
                    <?php foreach ($eventos as $evento): ?>
                        <div class="evento-card">
                            <img src="<?= SITE_URL . $evento['imagem_banner'] ?>" alt="" class="evento-banner">
                            
                            <div class="evento-content">
                                <div class="evento-header">
                                    <h3 class="evento-title"><?= htmlspecialchars($evento['nome']) ?></h3>
                                    <span class="badge <?= $evento['ativo'] ? 'badge-success' : 'badge-secondary' ?>">
                                        <?= $evento['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </div>
                                
                                <p style="color: var(--text-secondary); margin-bottom: 12px;">
                                    <?= htmlspecialchars($evento['descricao']) ?>
                                </p>
                                
                                <div class="evento-meta">
                                    <span><i class="fas fa-calendar"></i> 
                                        <?= date('d/m/Y H:i', strtotime($evento['data_inicio'])) ?>
                                    </span>
                                    <span><i class="fas fa-calendar-check"></i> 
                                        <?= date('d/m/Y H:i', strtotime($evento['data_fim'])) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-<?= $evento['tem_banner'] ? 'check-circle' : 'times-circle' ?>"></i>
                                        Banner <?= $evento['tem_banner'] ? 'Criado' : 'Não Criado' ?>
                                    </span>
                                </div>
                                
                                <div class="evento-actions">
                                    <a href="<?= SITE_URL ?>/pages/evento.php?slug=<?= $evento['slug'] ?>" 
                                       target="_blank" class="btn btn-secondary">
                                        <i class="fas fa-external-link-alt"></i> Ver Página
                                    </a>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="evento_id" value="<?= $evento['id'] ?>">
                                        <button type="submit" name="toggle_evento" 
                                                class="btn <?= $evento['ativo'] ? 'btn-danger' : 'btn-success' ?>">
                                            <i class="fas fa-power-off"></i>
                                            <?= $evento['ativo'] ? 'Desativar' : 'Ativar' ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Excluir evento e banner? Isso não pode ser desfeito.')">
                                        <input type="hidden" name="evento_id" value="<?= $evento['id'] ?>">
                                        <button type="submit" name="deletar_evento" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Excluir
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($eventos)): ?>
                        <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px;">
                            <i class="fas fa-calendar-alt" style="font-size: 64px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 20px;"></i>
                            <h3 style="color: var(--text-primary); margin-bottom: 8px;">Nenhum Evento Criado</h3>
                            <p style="color: var(--text-secondary); margin-bottom: 24px;">
                                Crie eventos sazonais como Black Friday, Natal, etc.
                            </p>
                            <button onclick="openModal('eventoModal')" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Criar Primeiro Evento
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Auditoria de Cupons -->
                <h2 style="margin: 60px 0 20px; font-size: 20px; color: var(--text-primary);">
                    <i class="fas fa-ticket-alt"></i> Auditoria de Cupons (Últimos 30 Dias)
                </h2>
                
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--bg-primary); border-bottom: 1px solid var(--border);">
                                <th style="padding: 16px; text-align: left; color: var(--text-primary); font-weight: 600;">Código</th>
                                <th style="padding: 16px; text-align: left; color: var(--text-primary); font-weight: 600;">Desenvolvedor</th>
                                <th style="padding: 16px; text-align: left; color: var(--text-primary); font-weight: 600;">Desconto</th>
                                <th style="padding: 16px; text-align: left; color: var(--text-primary); font-weight: 600;">Usos</th>
                                <th style="padding: 16px; text-align: left; color: var(--text-primary); font-weight: 600;">Criado Em</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cupons_log as $cupom): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 16px;">
                                        <code style="background: var(--bg-primary); padding: 4px 8px; border-radius: 4px;">
                                            <?= $cupom['codigo'] ?>
                                        </code>
                                    </td>
                                    <td style="padding: 16px; color: var(--text-primary);">
                                        <?= htmlspecialchars($cupom['nome_estudio']) ?>
                                    </td>
                                    <td style="padding: 16px; color: var(--text-secondary);">
                                        <?php if ($cupom['tipo_desconto'] == 'percentual'): ?>
                                            <?= $cupom['valor_desconto'] ?>% OFF
                                        <?php else: ?>
                                            <?= formatPrice($cupom['valor_desconto']) ?> OFF
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 16px; color: var(--text-secondary);">
                                        <?= $cupom['usos_atuais'] ?>/<?= $cupom['usos_maximos'] ?? '∞' ?>
                                    </td>
                                    <td style="padding: 16px; color: var(--text-secondary);">
                                        <?= date('d/m/Y H:i', strtotime($cupom['criado_em'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($cupons_log)): ?>
                        <p style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            Nenhum cupom criado nos últimos 30 dias
                        </p>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal: Criar Evento -->
<div id="eventoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-calendar-plus"></i> Criar Evento Sazonal</h2>
            <p style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">
                Um banner será criado automaticamente no carrossel
            </p>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nome do Evento *</label>
                    <input type="text" name="nome" class="form-control" required 
                           placeholder="Ex: Black Friday 2026">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3" 
                              placeholder="Aproveite descontos incríveis..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">URL da Imagem do Banner</label>
                    <input type="text" name="imagem_banner" class="form-control" 
                           placeholder="Deixe em branco para usar imagem padrão">
                    <small style="color: var(--text-secondary); font-size: 12px; display: block; margin-top: 4px;">
                        Recomendado: 1200x600px
                    </small>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Data de Início *</label>
                        <input type="datetime-local" name="data_inicio" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data de Término *</label>
                        <input type="datetime-local" name="data_fim" class="form-control" required>
                    </div>
                </div>
                
                <div style="background: rgba(76, 139, 245, 0.1); border: 1px solid var(--accent); border-radius: 8px; padding: 16px; margin-top: 20px;">
                    <p style="color: var(--text-primary); margin: 0; font-size: 14px;">
                        <i class="fas fa-info-circle" style="color: var(--accent);"></i>
                        <strong>Automação:</strong> Ao criar este evento, um banner será automaticamente 
                        adicionado ao carrossel da home. Ele aparecerá para todos os usuários durante 
                        o período do evento.
                    </p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('eventoModal')">
                    Cancelar
                </button>
                <button type="submit" name="criar_evento" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Criar Evento
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

// Fechar modal ao clicar fora
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal(this.id);
        }
    });
});

// Atualizar métricas a cada 30 segundos
setInterval(() => {
    fetch('<?= SITE_URL ?>/api/atualizar-metricas.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Erro ao atualizar métricas:', error));
}, 30000);
</script>

<?php require_once '../includes/footer.php'; ?>