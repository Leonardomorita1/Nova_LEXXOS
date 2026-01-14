<?php
// admin/promocoes.php - Sistema Completo de Promoções
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();
$message = '';

// Função para calcular e aplicar preço promocional
function aplicarPromocao($pdo, $jogo_id, $percentual_desconto) {
    // Buscar preço original
    $stmt = $pdo->prepare("SELECT preco_centavos FROM jogo WHERE id = ?");
    $stmt->execute([$jogo_id]);
    $jogo = $stmt->fetch();
    
    if ($jogo) {
        $preco_original = $jogo['preco_centavos'];
        $preco_promocional = $preco_original - (($preco_original * $percentual_desconto) / 100);
        $preco_promocional = round($preco_promocional);
        
        // Atualizar jogo com preço promocional
        $stmt = $pdo->prepare("
            UPDATE jogo 
            SET preco_promocional_centavos = ?, em_promocao = 1 
            WHERE id = ?
        ");
        $stmt->execute([$preco_promocional, $jogo_id]);
        
        return $preco_promocional;
    }
    return 0;
}

// Função para remover promoção
function removerPromocao($pdo, $jogo_id) {
    $stmt = $pdo->prepare("
        UPDATE jogo 
        SET preco_promocional_centavos = NULL, em_promocao = 0 
        WHERE id = ?
    ");
    $stmt->execute([$jogo_id]);
}

// Adicionar Promoção
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $stmt = $pdo->prepare("
        INSERT INTO promocao (nome, descricao, percentual_desconto, data_inicio, data_fim, ativa) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['nome'], 
        $_POST['descricao'], 
        $_POST['percentual_desconto'], 
        $_POST['data_inicio'], 
        $_POST['data_fim'], 
        $_POST['ativa']
    ]);
    $message = 'Promoção criada com sucesso!';
}

// Editar Promoção
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $promocao_id = $_POST['promocao_id'];
    $percentual_novo = $_POST['percentual_desconto'];
    
    // Atualizar promoção
    $stmt = $pdo->prepare("
        UPDATE promocao 
        SET nome=?, descricao=?, percentual_desconto=?, data_inicio=?, data_fim=?, ativa=? 
        WHERE id=?
    ");
    $stmt->execute([
        $_POST['nome'], 
        $_POST['descricao'], 
        $percentual_novo, 
        $_POST['data_inicio'], 
        $_POST['data_fim'], 
        $_POST['ativa'], 
        $promocao_id
    ]);
    
    // Recalcular preços de todos os jogos da promoção
    $stmt = $pdo->prepare("SELECT jogo_id FROM promocao_jogo WHERE promocao_id = ?");
    $stmt->execute([$promocao_id]);
    $jogos = $stmt->fetchAll();
    
    foreach ($jogos as $jogo) {
        aplicarPromocao($pdo, $jogo['jogo_id'], $percentual_novo);
    }
    
    $message = 'Promoção atualizada e preços recalculados!';
}

// Deletar Promoção
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
    $promocao_id = $_POST['promocao_id'];
    
    // Buscar jogos para remover promoção
    $stmt = $pdo->prepare("SELECT jogo_id FROM promocao_jogo WHERE promocao_id = ?");
    $stmt->execute([$promocao_id]);
    $jogos = $stmt->fetchAll();
    
    foreach ($jogos as $jogo) {
        removerPromocao($pdo, $jogo['jogo_id']);
    }
    
    // Deletar vínculos e promoção
    $stmt = $pdo->prepare("DELETE FROM promocao_jogo WHERE promocao_id = ?");
    $stmt->execute([$promocao_id]);
    
    $stmt = $pdo->prepare("DELETE FROM promocao WHERE id = ?");
    $stmt->execute([$promocao_id]);
    
    $message = 'Promoção excluída e preços restaurados!';
}

// Adicionar Jogo à Promoção
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_jogo'])) {
    $promocao_id = $_POST['promocao_id'];
    $jogo_id = $_POST['jogo_id'];
    
    // Buscar percentual de desconto
    $stmt = $pdo->prepare("SELECT percentual_desconto FROM promocao WHERE id = ?");
    $stmt->execute([$promocao_id]);
    $promocao = $stmt->fetch();
    
    // Adicionar à promoção
    $stmt = $pdo->prepare("INSERT IGNORE INTO promocao_jogo (promocao_id, jogo_id) VALUES (?, ?)");
    $stmt->execute([$promocao_id, $jogo_id]);
    
    // Calcular e aplicar preço promocional
    aplicarPromocao($pdo, $jogo_id, $promocao['percentual_desconto']);
    
    $message = 'Jogo adicionado com preço promocional calculado!';
}

// Remover Jogo da Promoção
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_jogo'])) {
    $jogo_id = $_POST['jogo_id'];
    
    // Remover da promoção
    $stmt = $pdo->prepare("DELETE FROM promocao_jogo WHERE promocao_id = ? AND jogo_id = ?");
    $stmt->execute([$_POST['promocao_id'], $jogo_id]);
    
    // Verificar se o jogo está em outra promoção ativa
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM promocao_jogo pj
        JOIN promocao p ON pj.promocao_id = p.id
        WHERE pj.jogo_id = ? AND p.ativa = 1
    ");
    $stmt->execute([$jogo_id]);
    $tem_outras = $stmt->fetch()['total'] > 0;
    
    // Se não tem outras promoções, remover preço promocional
    if (!$tem_outras) {
        removerPromocao($pdo, $jogo_id);
    }
    
    $message = 'Jogo removido da promoção!';
}

// Toggle Ativo/Inativo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle'])) {
    $promocao_id = $_POST['promocao_id'];
    
    // Toggle status
    $stmt = $pdo->prepare("SELECT ativa FROM promocao WHERE id = ?");
    $stmt->execute([$promocao_id]);
    $ativa_atual = $stmt->fetch()['ativa'];
    $nova_ativa = $ativa_atual ? 0 : 1;
    
    $stmt = $pdo->prepare("UPDATE promocao SET ativa = ? WHERE id = ?");
    $stmt->execute([$nova_ativa, $promocao_id]);
    
    // Se desativando, remover preços promocionais
    if ($nova_ativa == 0) {
        $stmt = $pdo->prepare("SELECT jogo_id FROM promocao_jogo WHERE promocao_id = ?");
        $stmt->execute([$promocao_id]);
        $jogos = $stmt->fetchAll();
        
        foreach ($jogos as $jogo) {
            removerPromocao($pdo, $jogo['jogo_id']);
        }
    }
    // Se ativando, recalcular preços
    else {
        $stmt = $pdo->prepare("SELECT jogo_id, percentual_desconto FROM promocao_jogo pj JOIN promocao p ON pj.promocao_id = p.id WHERE pj.promocao_id = ?");
        $stmt->execute([$promocao_id]);
        $jogos = $stmt->fetchAll();
        
        foreach ($jogos as $jogo) {
            aplicarPromocao($pdo, $jogo['jogo_id'], $jogo['percentual_desconto']);
        }
    }
    
    $message = 'Status atualizado e preços recalculados!';
}

// Buscar promoções
$stmt = $pdo->query("SELECT * FROM promocao ORDER BY criado_em DESC");
$promocoes = $stmt->fetchAll();

// Buscar jogos disponíveis
$stmt = $pdo->query("SELECT id, titulo FROM jogo WHERE status = 'publicado' ORDER BY titulo");
$jogos_disponiveis = $stmt->fetchAll();

$page_title = 'Gerenciar Promoções - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<style>
.promo-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    margin-left: 10px;
}

.promo-dates {
    display: flex;
    align-items: center;
    gap: 15px;
    color: var(--text-secondary);
    font-size: 13px;
    margin-top: 8px;
}

.promo-dates i {
    color: var(--accent);
}

.game-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.game-card-promo {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    transition: all 0.3s;
}

.game-card-promo:hover {
    transform: translateY(-4px);
    border-color: var(--accent);
}

.game-card-promo img {
    width: 100%;
    aspect-ratio: 3/4;
    object-fit: cover;
}

.game-card-promo .info {
    padding: 12px;
}

.game-card-promo .title {
    font-size: 13px;
    font-weight: 600;
    line-height: 1.3;
    margin-bottom: 6px;
}

.game-card-promo .price-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.game-card-promo .old-price {
    font-size: 11px;
    text-decoration: line-through;
    color: var(--text-secondary);
}

.game-card-promo .new-price {
    font-size: 14px;
    font-weight: 700;
    color: #2ecc71;
}

.remove-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 32px;
    height: 32px;
    background: rgba(231, 76, 60, 0.95);
    border: none;
    border-radius: 50%;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    opacity: 0;
}

.game-card-promo:hover .remove-btn {
    opacity: 1;
}

.remove-btn:hover {
    background: #c0392b;
    transform: scale(1.1);
}

.add-game-form {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.stats-row {
    display: flex;
    gap: 20px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--text-secondary);
}

.stat-item i {
    color: var(--accent);
}

.stat-item strong {
    color: var(--text-primary);
}
</style>

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title">
                    <i class="fas fa-tags"></i> Gerenciar Promoções
                </h1>
                <button onclick="document.getElementById('formNova').style.display='block'" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nova Promoção
                </button>
            </div>

            <?php if ($message): ?>
            <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Form Nova Promoção -->
            <div id="formNova" class="form-section" style="display: none; margin-bottom: 30px;">
                <h2><i class="fas fa-plus-circle"></i> Nova Promoção</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome da Promoção</label>
                            <input type="text" name="nome" class="form-control" placeholder="Black Friday 2025" required>
                        </div>
                        <div class="form-group">
                            <label>Desconto (%)</label>
                            <input type="number" name="percentual_desconto" class="form-control" min="1" max="100" placeholder="50" required>
                        </div>
                        <div class="form-group">
                            <label>Data de Início</label>
                            <input type="datetime-local" name="data_inicio" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Data de Término</label>
                            <input type="datetime-local" name="data_fim" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3" placeholder="Aproveite descontos incríveis..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="ativa" class="form-control">
                            <option value="1">Ativa</option>
                            <option value="0">Inativa</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="add" class="btn btn-primary">
                            <i class="fas fa-save"></i> Criar Promoção
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('formNova').style.display='none'">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de Promoções -->
            <?php foreach ($promocoes as $promocao): ?>
            <?php
            $stmt = $pdo->prepare("
                SELECT j.id, j.titulo, j.imagem_capa, j.preco_centavos, j.preco_promocional_centavos
                FROM jogo j
                JOIN promocao_jogo pj ON j.id = pj.jogo_id
                WHERE pj.promocao_id = ?
                ORDER BY j.titulo
            ");
            $stmt->execute([$promocao['id']]);
            $jogos_promocao = $stmt->fetchAll();
            
            $total_economia = 0;
            foreach ($jogos_promocao as $jp) {
                if ($jp['preco_promocional_centavos']) {
                    $total_economia += ($jp['preco_centavos'] - $jp['preco_promocional_centavos']);
                }
            }
            ?>
            <div class="form-section" style="margin-bottom: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                    <div style="flex: 1;">
                        <h2 style="display: flex; align-items: center;">
                            <?php echo sanitize($promocao['nome']); ?>
                            <span class="promo-badge"><?php echo $promocao['percentual_desconto']; ?>% OFF</span>
                        </h2>
                        
                        <div class="promo-dates">
                            <span>
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('d/m/Y H:i', strtotime($promocao['data_inicio'])); ?>
                            </span>
                            <span>→</span>
                            <span>
                                <i class="fas fa-calendar-check"></i>
                                <?php echo date('d/m/Y H:i', strtotime($promocao['data_fim'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($promocao['descricao']): ?>
                        <p style="margin-top: 12px; color: var(--text-secondary);">
                            <?php echo sanitize($promocao['descricao']); ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="stats-row">
                            <div class="stat-item">
                                <i class="fas fa-gamepad"></i>
                                <span><strong><?php echo count($jogos_promocao); ?></strong> jogos</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-piggy-bank"></i>
                                <span>Economia total: <strong><?php echo formatPrice($total_economia); ?></strong></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-toggle-<?php echo $promocao['ativa'] ? 'on' : 'off'; ?>"></i>
                                <span class="badge badge-<?php echo $promocao['ativa'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $promocao['ativa'] ? 'Ativa' : 'Inativa'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; flex-shrink: 0;">
                        <button onclick="editarPromocao(<?php echo htmlspecialchars(json_encode($promocao)); ?>)" class="btn btn-secondary">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="promocao_id" value="<?php echo $promocao['id']; ?>">
                            <button type="submit" name="toggle" class="btn btn-<?php echo $promocao['ativa'] ? 'warning' : 'success'; ?>">
                                <i class="fas fa-power-off"></i>
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="promocao_id" value="<?php echo $promocao['id']; ?>">
                            <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('Excluir esta promoção? Os preços promocionais serão removidos dos jogos.')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Adicionar Jogo -->
                <div class="add-game-form" id="addForm<?php echo $promocao['id']; ?>" style="display: none;">
                    <h3 style="margin-bottom: 15px;">
                        <i class="fas fa-plus"></i> Adicionar Jogo
                    </h3>
                    <form method="POST" style="display: flex; gap: 15px; align-items: flex-end;">
                        <input type="hidden" name="promocao_id" value="<?php echo $promocao['id']; ?>">
                        <div class="form-group" style="flex: 1; margin: 0;">
                            <label>Selecione o Jogo</label>
                            <select name="jogo_id" class="form-control" required>
                                <option value="">Escolha um jogo...</option>
                                <?php foreach ($jogos_disponiveis as $jogo): ?>
                                    <option value="<?php echo $jogo['id']; ?>">
                                        <?php echo sanitize($jogo['titulo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_jogo" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('addForm<?php echo $promocao['id']; ?>').style.display='none'">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </form>
                </div>

                <button onclick="document.getElementById('addForm<?php echo $promocao['id']; ?>').style.display='block'" class="btn btn-sm btn-primary" style="margin-bottom: 20px;">
                    <i class="fas fa-plus"></i> Adicionar Jogo à Promoção
                </button>

                <!-- Grid de Jogos -->
                <?php if (count($jogos_promocao) > 0): ?>
                <div class="game-grid">
                    <?php foreach ($jogos_promocao as $jogo): ?>
                    <div class="game-card-promo">
                        <img src="<?php echo SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                             alt="<?php echo sanitize($jogo['titulo']); ?>">
                        <div class="info">
                            <div class="title"><?php echo sanitize($jogo['titulo']); ?></div>
                            <div class="price-info">
                                <div class="old-price"><?php echo formatPrice($jogo['preco_centavos']); ?></div>
                                <div class="new-price"><?php echo formatPrice($jogo['preco_promocional_centavos']); ?></div>
                            </div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="promocao_id" value="<?php echo $promocao['id']; ?>">
                            <input type="hidden" name="jogo_id" value="<?php echo $jogo['id']; ?>">
                            <button type="submit" name="remove_jogo" class="remove-btn" 
                                    onclick="return confirm('Remover este jogo da promoção?')">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: var(--bg-primary); border: 1px dashed var(--border); border-radius: 10px;">
                    <i class="fas fa-gamepad" style="font-size: 48px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 15px;"></i>
                    <p style="color: var(--text-secondary); margin: 0;">Nenhum jogo adicionado ainda</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div id="modalEdit" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 9999; padding: 20px; align-items: center; justify-content: center; overflow-y: auto;">
    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; max-width: 700px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.5);">
        <div style="padding: 25px; border-bottom: 1px solid var(--border);">
            <h2><i class="fas fa-edit"></i> Editar Promoção</h2>
        </div>
        <form method="POST" style="padding: 25px;">
            <input type="hidden" name="promocao_id" id="edit_id">
            <div class="form-grid">
                <div class="form-group">
                    <label>Nome</label>
                    <input type="text" name="nome" id="edit_nome" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Desconto (%)</label>
                    <input type="number" name="percentual_desconto" id="edit_desconto" class="form-control" min="1" max="100" required>
                </div>
                <div class="form-group">
                    <label>Início</label>
                    <input type="datetime-local" name="data_inicio" id="edit_inicio" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Término</label>
                    <input type="datetime-local" name="data_fim" id="edit_fim" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>Descrição</label>
                <textarea name="descricao" id="edit_desc" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="ativa" id="edit_ativa" class="form-control">
                    <option value="1">Ativa</option>
                    <option value="0">Inativa</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 25px;">
                <button type="submit" name="edit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEdit').style.display='none'">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editarPromocao(promo) {
    document.getElementById('edit_id').value = promo.id;
    document.getElementById('edit_nome').value = promo.nome;
    document.getElementById('edit_desconto').value = promo.percentual_desconto;
    document.getElementById('edit_inicio').value = promo.data_inicio.replace(' ', 'T');
    document.getElementById('edit_fim').value = promo.data_fim.replace(' ', 'T');
    document.getElementById('edit_desc').value = promo.descricao || '';
    document.getElementById('edit_ativa').value = promo.ativa;
    document.getElementById('modalEdit').style.display = 'flex';
}

// Fechar modal ao clicar fora
document.getElementById('modalEdit').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>