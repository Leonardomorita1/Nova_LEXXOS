<?php
// developer/promocoes.php
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

$message = '';
$error = '';

// ============================================
// CRIAR PROMOÇÃO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['criar_promocao'])) {
    try {
        $pdo->beginTransaction();
        
        $nome = trim($_POST['nome']);
        $descricao = trim($_POST['descricao']);
        $percentual = (int)$_POST['percentual_desconto'];
        $data_inicio = $_POST['data_inicio'];
        $data_fim = $_POST['data_fim'];
        $jogos_ids = $_POST['jogos'] ?? [];
        
        if (empty($nome) || $percentual < 1 || $percentual > 100 || empty($jogos_ids)) {
            throw new Exception('Preencha todos os campos obrigatórios');
        }
        
        // Criar promoção
        $stmt = $pdo->prepare("
            INSERT INTO dev_promocao (desenvolvedor_id, nome, descricao, percentual_desconto, data_inicio, data_fim) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$dev['id'], $nome, $descricao, $percentual, $data_inicio, $data_fim]);
        $promocao_id = $pdo->lastInsertId();
        
        // Adicionar jogos
        foreach ($jogos_ids as $jogo_id) {
            $stmt = $pdo->prepare("SELECT preco_centavos FROM jogo WHERE id = ? AND desenvolvedor_id = ?");
            $stmt->execute([$jogo_id, $dev['id']]);
            $jogo = $stmt->fetch();
            
            if ($jogo) {
                $preco_original = $jogo['preco_centavos'];
                $preco_promo = $preco_original - (($preco_original * $percentual) / 100);
                
                $stmt = $pdo->prepare("
                    INSERT INTO dev_promocao_jogo (promocao_id, jogo_id, preco_original_centavos, preco_promocional_centavos) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$promocao_id, $jogo_id, $preco_original, $preco_promo]);
                
                // Atualizar jogo
                $stmt = $pdo->prepare("
                    UPDATE jogo 
                    SET preco_promocional_centavos = ?, em_promocao = 1 
                    WHERE id = ?
                ");
                $stmt->execute([$preco_promo, $jogo_id]);
            }
        }
        
        $pdo->commit();
        $message = 'Promoção criada com sucesso!';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// ============================================
// CRIAR CUPOM
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['criar_cupom'])) {
    try {
        $codigo = strtoupper(trim($_POST['codigo']));
        $tipo = $_POST['tipo_desconto'];
        $valor = (int)$_POST['valor_desconto'];
        $jogo_id = !empty($_POST['jogo_id']) ? $_POST['jogo_id'] : NULL;
        $usos_max = !empty($_POST['usos_maximos']) ? $_POST['usos_maximos'] : NULL;
        $validade = !empty($_POST['validade']) ? $_POST['validade'] : NULL;
        
        if (empty($codigo) || $valor < 1) {
            throw new Exception('Preencha os campos obrigatórios');
        }
        
        // Verificar se código já existe
        $stmt = $pdo->prepare("SELECT id FROM dev_cupom WHERE codigo = ?");
        $stmt->execute([$codigo]);
        if ($stmt->fetch()) {
            throw new Exception('Código de cupom já existe');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO dev_cupom (desenvolvedor_id, codigo, tipo_desconto, valor_desconto, jogo_id, usos_maximos, validade) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$dev['id'], $codigo, $tipo, $valor, $jogo_id, $usos_max, $validade]);
        
        $message = 'Cupom criado com sucesso!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ============================================
// TOGGLE PROMOÇÃO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_promo'])) {
    $promo_id = $_POST['promocao_id'];
    
    $stmt = $pdo->prepare("SELECT ativa FROM dev_promocao WHERE id = ? AND desenvolvedor_id = ?");
    $stmt->execute([$promo_id, $dev['id']]);
    $promo = $stmt->fetch();
    
    if ($promo) {
        $novo_status = $promo['ativa'] ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE dev_promocao SET ativa = ? WHERE id = ?");
        $stmt->execute([$novo_status, $promo_id]);
        
        // Atualizar jogos
        if ($novo_status == 0) {
            // Desativar promoção nos jogos
            $stmt = $pdo->prepare("
                UPDATE jogo j
                JOIN dev_promocao_jogo dpj ON j.id = dpj.jogo_id
                SET j.preco_promocional_centavos = NULL, j.em_promocao = 0
                WHERE dpj.promocao_id = ?
            ");
            $stmt->execute([$promo_id]);
        } else {
            // Reativar promoção
            $stmt = $pdo->prepare("
                UPDATE jogo j
                JOIN dev_promocao_jogo dpj ON j.id = dpj.jogo_id
                SET j.preco_promocional_centavos = dpj.preco_promocional_centavos, j.em_promocao = 1
                WHERE dpj.promocao_id = ?
            ");
            $stmt->execute([$promo_id]);
        }
        
        $message = 'Promoção ' . ($novo_status ? 'ativada' : 'desativada');
    }
}

// ============================================
// BUSCAR DADOS
// ============================================

// Jogos do desenvolvedor
$stmt = $pdo->prepare("
    SELECT j.*, 
           dp.preco_promocional_centavos as preco_promo_ativo
    FROM jogo j
    LEFT JOIN dev_promocao_jogo dp ON j.id = dp.jogo_id
    LEFT JOIN dev_promocao p ON dp.promocao_id = p.id AND p.ativa = 1
    WHERE j.desenvolvedor_id = ? AND j.status = 'publicado'
    ORDER BY j.titulo
");
$stmt->execute([$dev['id']]);
$jogos = $stmt->fetchAll();

// Promoções ativas
$stmt = $pdo->prepare("
    SELECT p.*, 
           COUNT(dpj.id) as total_jogos
    FROM dev_promocao p
    LEFT JOIN dev_promocao_jogo dpj ON p.id = dpj.promocao_id
    WHERE p.desenvolvedor_id = ?
    GROUP BY p.id
    ORDER BY p.criado_em DESC
");
$stmt->execute([$dev['id']]);
$promocoes = $stmt->fetchAll();

// Cupons
$stmt = $pdo->prepare("
    SELECT c.*, j.titulo as jogo_titulo
    FROM dev_cupom c
    LEFT JOIN jogo j ON c.jogo_id = j.id
    WHERE c.desenvolvedor_id = ?
    ORDER BY c.criado_em DESC
");
$stmt->execute([$dev['id']]);
$cupons = $stmt->fetchAll();

$page_title = 'Promoções e Cupons - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.dev-promo-container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }
.promo-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.promo-title { font-size: 28px; font-weight: 700; color: var(--text-primary); }
.tabs { display: flex; gap: 10px; border-bottom: 2px solid var(--border); margin-bottom: 30px; }
.tab { padding: 12px 24px; background: none; border: none; color: var(--text-secondary); cursor: pointer; font-weight: 600; border-bottom: 3px solid transparent; }
.tab.active { color: var(--accent); border-bottom-color: var(--accent); }

.games-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
.game-monitor-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 16px; }
.game-monitor-card h4 { font-size: 16px; margin-bottom: 12px; color: var(--text-primary); }
.price-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.price-label { font-size: 13px; color: var(--text-secondary); }
.price-value { font-size: 16px; font-weight: 700; color: var(--text-primary); }
.price-value.promo { color: var(--success); }
.status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.status-badge.active { background: rgba(46, 204, 113, 0.2); color: var(--success); }
.status-badge.inactive { background: rgba(149, 165, 166, 0.2); color: var(--text-secondary); }

.promo-list { display: flex; flex-direction: column; gap: 20px; }
.promo-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
.promo-card-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px; }
.promo-card-title { font-size: 20px; font-weight: 700; color: var(--text-primary); }
.promo-meta { display: flex; gap: 20px; margin: 12px 0; font-size: 14px; color: var(--text-secondary); }
.promo-actions { display: flex; gap: 10px; }

.form-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
.form-modal.active { display: flex; }
.form-content { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 16px; max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; }
.form-header { padding: 24px; border-bottom: 1px solid var(--border); }
.form-body { padding: 24px; }
.form-footer { padding: 24px; border-top: 1px solid var(--border); display: flex; gap: 12px; justify-content: flex-end; }

.form-group { margin-bottom: 20px; }
.form-label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-primary); }
.form-control { width: 100%; padding: 12px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); }
.form-control:focus { outline: none; border-color: var(--accent); }

.game-selector { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
.game-checkbox { display: flex; align-items: center; gap: 10px; padding: 12px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; cursor: pointer; }
.game-checkbox:has(input:checked) { border-color: var(--accent); background: rgba(76, 139, 245, 0.1); }

.btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
.btn-primary { background: var(--accent); color: white; }
.btn-primary:hover { opacity: 0.9; }
.btn-secondary { background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border); }
.btn-danger { background: var(--danger); color: white; }

.alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
.alert-success { background: rgba(46, 204, 113, 0.2); color: var(--success); border: 1px solid var(--success); }
.alert-error { background: rgba(220, 53, 69, 0.2); color: var(--danger); border: 1px solid var(--danger); }
</style>

<div class="dev-layout">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="dev-content">
        <div class="dev-promo-container">
            
            <div class="promo-header">
                <h1 class="promo-title"><i class="fas fa-tags"></i> Promoções e Cupons</h1>
                <div style="display: flex; gap: 12px;">
                    <button onclick="openModal('promoModal')" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nova Promoção
                    </button>
                    <button onclick="openModal('cupomModal')" class="btn btn-secondary">
                        <i class="fas fa-ticket-alt"></i> Novo Cupom
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="showTab('monitor')">
                    <i class="fas fa-chart-line"></i> Monitor de Jogos
                </button>
                <button class="tab" onclick="showTab('promocoes')">
                    <i class="fas fa-tags"></i> Minhas Promoções (<?= count($promocoes) ?>)
                </button>
                <button class="tab" onclick="showTab('cupons')">
                    <i class="fas fa-ticket-alt"></i> Meus Cupons (<?= count($cupons) ?>)
                </button>
            </div>
            
            <!-- Tab: Monitor -->
            <div id="tab-monitor" class="tab-content active">
                <h3 style="margin-bottom: 20px; color: var(--text-primary);">Status dos Jogos</h3>
                <div class="games-grid">
                    <?php foreach ($jogos as $jogo): ?>
                        <div class="game-monitor-card">
                            <h4><?= htmlspecialchars($jogo['titulo']) ?></h4>
                            
                            <div class="price-row">
                                <span class="price-label">Preço Base:</span>
                                <span class="price-value"><?= formatPrice($jogo['preco_centavos']) ?></span>
                            </div>
                            
                            <?php if ($jogo['em_promocao']): ?>
                                <div class="price-row">
                                    <span class="price-label">Preço Atual:</span>
                                    <span class="price-value promo"><?= formatPrice($jogo['preco_promocional_centavos']) ?></span>
                                </div>
                                <div style="margin-top: 12px;">
                                    <span class="status-badge active">
                                        <i class="fas fa-fire"></i> Em Promoção
                                    </span>
                                </div>
                            <?php else: ?>
                                <div style="margin-top: 12px;">
                                    <span class="status-badge inactive">Sem Promoção</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Tab: Promoções -->
            <div id="tab-promocoes" class="tab-content">
                <div class="promo-list">
                    <?php foreach ($promocoes as $promo): ?>
                        <div class="promo-card">
                            <div class="promo-card-header">
                                <div>
                                    <h3 class="promo-card-title"><?= htmlspecialchars($promo['nome']) ?></h3>
                                    <p style="color: var(--text-secondary); margin-top: 4px;">
                                        <?= htmlspecialchars($promo['descricao']) ?>
                                    </p>
                                </div>
                                <div class="promo-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="promocao_id" value="<?= $promo['id'] ?>">
                                        <button type="submit" name="toggle_promo" class="btn <?= $promo['ativa'] ? 'btn-danger' : 'btn-primary' ?>">
                                            <i class="fas fa-power-off"></i>
                                            <?= $promo['ativa'] ? 'Desativar' : 'Ativar' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="promo-meta">
                                <span><i class="fas fa-percent"></i> <?= $promo['percentual_desconto'] ?>% OFF</span>
                                <span><i class="fas fa-gamepad"></i> <?= $promo['total_jogos'] ?> jogos</span>
                                <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($promo['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($promo['data_fim'])) ?></span>
                                <span class="status-badge <?= $promo['ativa'] ? 'active' : 'inactive' ?>">
                                    <?= $promo['ativa'] ? 'Ativa' : 'Inativa' ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($promocoes)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                            <i class="fas fa-tags" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                            <p>Nenhuma promoção criada ainda</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tab: Cupons -->
            <div id="tab-cupons" class="tab-content">
                <div class="promo-list">
                    <?php foreach ($cupons as $cupom): ?>
                        <div class="promo-card">
                            <div class="promo-card-header">
                                <div>
                                    <h3 class="promo-card-title"><code><?= $cupom['codigo'] ?></code></h3>
                                    <p style="color: var(--text-secondary); margin-top: 4px;">
                                        <?php if ($cupom['jogo_id']): ?>
                                            Válido para: <?= htmlspecialchars($cupom['jogo_titulo']) ?>
                                        <?php else: ?>
                                            Válido para todos os seus jogos
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="status-badge <?= $cupom['ativo'] ? 'active' : 'inactive' ?>">
                                    <?= $cupom['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </div>
                            
                            <div class="promo-meta">
                                <span><i class="fas fa-percent"></i> 
                                    <?php if ($cupom['tipo_desconto'] == 'percentual'): ?>
                                        <?= $cupom['valor_desconto'] ?>% OFF
                                    <?php else: ?>
                                        <?= formatPrice($cupom['valor_desconto']) ?> OFF
                                    <?php endif; ?>
                                </span>
                                <span><i class="fas fa-ticket-alt"></i> 
                                    <?= $cupom['usos_atuais'] ?>/<?= $cupom['usos_maximos'] ?? '∞' ?> usos
                                </span>
                                <?php if ($cupom['validade']): ?>
                                    <span><i class="fas fa-calendar-times"></i> 
                                        Expira em <?= date('d/m/Y', strtotime($cupom['validade'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($cupons)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                            <i class="fas fa-ticket-alt" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                            <p>Nenhum cupom criado ainda</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Modal: Nova Promoção -->
<div id="promoModal" class="form-modal">
    <div class="form-content">
        <div class="form-header">
            <h2><i class="fas fa-tags"></i> Nova Promoção</h2>
        </div>
        <form method="POST">
            <div class="form-body">
                <div class="form-group">
                    <label class="form-label">Nome da Promoção</label>
                    <input type="text" name="nome" class="form-control" required placeholder="Ex: Promoção de Verão">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3" placeholder="Opcional"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Desconto (%)</label>
                    <input type="number" name="percentual_desconto" class="form-control" min="1" max="100" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Data de Início</label>
                        <input type="datetime-local" name="data_inicio" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data de Término</label>
                        <input type="datetime-local" name="data_fim" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Selecione os Jogos</label>
                    <div class="game-selector">
                        <?php foreach ($jogos as $jogo): ?>
                            <label class="game-checkbox">
                                <input type="checkbox" name="jogos[]" value="<?= $jogo['id'] ?>">
                                <span><?= htmlspecialchars($jogo['titulo']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('promoModal')">Cancelar</button>
                <button type="submit" name="criar_promocao" class="btn btn-primary">Criar Promoção</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Novo Cupom -->
<div id="cupomModal" class="form-modal">
    <div class="form-content">
        <div class="form-header">
            <h2><i class="fas fa-ticket-alt"></i> Novo Cupom</h2>
        </div>
        <form method="POST">
            <div class="form-body">
                <div class="form-group">
                    <label class="form-label">Código do Cupom</label>
                    <input type="text" name="codigo" class="form-control" required placeholder="Ex: MYGAME10" style="text-transform: uppercase;">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Tipo de Desconto</label>
                        <select name="tipo_desconto" class="form-control">
                            <option value="percentual">Percentual (%)</option>
                            <option value="fixo">Valor Fixo (R$)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valor</label>
                        <input type="number" name="valor_desconto" class="form-control" min="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Aplicar a:</label>
                    <select name="jogo_id" class="form-control">
                        <option value="">Todos os meus jogos</option>
                        <?php foreach ($jogos as $jogo): ?>
                            <option value="<?= $jogo['id'] ?>"><?= htmlspecialchars($jogo['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Usos Máximos</label>
                        <input type="number" name="usos_maximos" class="form-control" placeholder="Ilimitado">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data de Validade</label>
                        <input type="date" name="validade" class="form-control">
                    </div>
                </div>
            </div>
            
            <div class="form-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('cupomModal')">Cancelar</button>
                <button type="submit" name="criar_cupom" class="btn btn-primary">Criar Cupom</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

// Fechar modal ao clicar fora
document.querySelectorAll('.form-modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal(this.id);
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>