<?php
// ============================================
// admin/promocoes.php
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();
$message = '';

// Adicionar promoção
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $stmt = $pdo->prepare("INSERT INTO promocao (nome, descricao, percentual_desconto, data_inicio, data_fim, ativa) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['nome'],
        $_POST['descricao'],
        $_POST['percentual_desconto'],
        $_POST['data_inicio'],
        $_POST['data_fim'],
        $_POST['ativa']
    ]);
    $message = 'Promoção criada!';
}

// Adicionar jogo à promoção
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_jogo'])) {
    $stmt = $pdo->prepare("INSERT INTO promocao_jogo (promocao_id, jogo_id) VALUES (?, ?)");
    $stmt->execute([$_POST['promocao_id'], $_POST['jogo_id']]);
    
    // Atualizar jogo
    $stmt = $pdo->prepare("UPDATE jogo SET em_promocao = 1 WHERE id = ?");
    $stmt->execute([$_POST['jogo_id']]);
    
    $message = 'Jogo adicionado à promoção!';
}

// Toggle promoção
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle'])) {
    $stmt = $pdo->prepare("UPDATE promocao SET ativa = NOT ativa WHERE id = ?");
    $stmt->execute([$_POST['promocao_id']]);
    $message = 'Status atualizado!';
}

$stmt = $pdo->query("SELECT * FROM promocao ORDER BY criado_em DESC");
$promocoes = $stmt->fetchAll();

// Buscar jogos disponíveis
$stmt = $pdo->query("SELECT id, titulo FROM jogo WHERE status = 'publicado' ORDER BY titulo");
$jogos_disponiveis = $stmt->fetchAll();

$page_title = 'Gerenciar Promoções - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title"><i class="fas fa-tags"></i> Gerenciar Promoções</h1>
                <button onclick="document.getElementById('formNova').style.display='block'" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nova Promoção
                </button>
            </div>

            <?php if ($message): ?>
            <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Formulário Nova Promoção -->
            <div id="formNova" class="form-section" style="display: none; margin-bottom: 30px;">
                <h2>Nova Promoção</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome da Promoção</label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Percentual de Desconto</label>
                            <input type="number" name="percentual_desconto" class="form-control" min="1" max="100" required>
                        </div>
                        <div class="form-group">
                            <label>Data de Início</label>
                            <input type="datetime-local" name="data_inicio" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Data de Fim</label>
                            <input type="datetime-local" name="data_fim" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Ativa</label>
                        <select name="ativa" class="form-control">
                            <option value="1">Sim</option>
                            <option value="0">Não</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="add" class="btn btn-primary">Criar Promoção</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('formNova').style.display='none'">Cancelar</button>
                    </div>
                </form>
            </div>

            <!-- Lista de Promoções -->
            <?php foreach ($promocoes as $promocao): ?>
            <?php
            // Buscar jogos da promoção
            $stmt = $pdo->prepare("
                SELECT j.id, j.titulo, j.imagem_capa 
                FROM jogo j
                JOIN promocao_jogo pj ON j.id = pj.jogo_id
                WHERE pj.promocao_id = ?
            ");
            $stmt->execute([$promocao['id']]);
            $jogos_promocao = $stmt->fetchAll();
            ?>
            <div class="form-section" style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                    <div>
                        <h2><?php echo sanitize($promocao['nome']); ?> 
                            <span class="badge badge-<?php echo $promocao['ativa'] ? 'success' : 'secondary'; ?>">
                                <?php echo $promocao['percentual_desconto']; ?>% OFF
                            </span>
                        </h2>
                        <p style="color: var(--text-secondary); margin-top: 5px;">
                            <?php echo date('d/m/Y H:i', strtotime($promocao['data_inicio'])); ?> até 
                            <?php echo date('d/m/Y H:i', strtotime($promocao['data_fim'])); ?>
                        </p>
                        <?php if ($promocao['descricao']): ?>
                            <p style="margin-top: 10px;"><?php echo sanitize($promocao['descricao']); ?></p>
                        <?php endif; ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="promocao_id" value="<?php echo $promocao['id']; ?>">
                        <button type="submit" name="toggle" class="btn btn-<?php echo $promocao['ativa'] ? 'danger' : 'success'; ?>">
                            <i class="fas fa-toggle-<?php echo $promocao['ativa'] ? 'on' : 'off'; ?>"></i>
                            <?php echo $promocao['ativa'] ? 'Desativar' : 'Ativar'; ?>
                        </button>
                    </form>
                </div>

                <div style="margin-bottom: 15px;">
                    <button onclick="document.getElementById('addJogo<?php echo $promocao['id']; ?>').style.display='block'" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Adicionar Jogo
                    </button>
                </div>

                <!-- Formulário Adicionar Jogo -->
                <div id="addJogo<?php echo $promocao['id']; ?>" style="display: none; background: var(--bg-primary); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <form method="POST" style="display: flex; gap: 10px; align-items: flex-end;">
                        <input type="hidden" name="promocao_id" value="<?php echo $promocao['id']; ?>">
                        <div class="form-group" style="flex: 1; margin: 0;">
                            <label>Selecione o Jogo</label>
                            <select name="jogo_id" class="form-control" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($jogos_disponiveis as $jogo): ?>
                                    <option value="<?php echo $jogo['id']; ?>"><?php echo sanitize($jogo['titulo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_jogo" class="btn btn-primary">Adicionar</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('addJogo<?php echo $promocao['id']; ?>').style.display='none'">Cancelar</button>
                    </form>
                </div>

                <!-- Jogos da Promoção -->
                <?php if (count($jogos_promocao) > 0): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                    <?php foreach ($jogos_promocao as $jogo): ?>
                    <div style="background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">
                        <img src="<?php echo SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                             style="width: 100%; aspect-ratio: 3/4; object-fit: cover;">
                        <div style="padding: 10px;">
                            <strong style="font-size: 14px;"><?php echo sanitize($jogo['titulo']); ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; background: var(--bg-primary); border-radius: 8px;">
                    <i class="fas fa-gamepad" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 10px;"></i>
                    <p style="color: var(--text-secondary);">Nenhum jogo adicionado a esta promoção</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
