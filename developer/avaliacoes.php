<?php
// developer/avaliacoes.php
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
$jogo_filter = $_GET['jogo'] ?? 'todos';

// Processar resposta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'responder') {
    $avaliacao_id = $_POST['avaliacao_id'];
    $resposta = trim($_POST['resposta']);
    
    if (!empty($resposta)) {
        $stmt = $pdo->prepare("UPDATE avaliacao SET resposta_dev = ?, respondido_em = NOW() WHERE id = ?");
        if ($stmt->execute([$resposta, $avaliacao_id])) {
            $success = 'Resposta enviada com sucesso!';
        }
    }
}

// Estatísticas gerais
$stmt = $pdo->prepare("
    SELECT 
        COUNT(a.id) as total_avaliacoes,
        AVG(a.nota) as media_geral,
        SUM(CASE WHEN a.nota = 5 THEN 1 ELSE 0 END) as nota_5,
        SUM(CASE WHEN a.nota = 4 THEN 1 ELSE 0 END) as nota_4,
        SUM(CASE WHEN a.nota = 3 THEN 1 ELSE 0 END) as nota_3,
        SUM(CASE WHEN a.nota = 2 THEN 1 ELSE 0 END) as nota_2,
        SUM(CASE WHEN a.nota = 1 THEN 1 ELSE 0 END) as nota_1,
        SUM(CASE WHEN a.resposta_dev IS NULL THEN 1 ELSE 0 END) as sem_resposta
    FROM avaliacao a
    INNER JOIN jogo j ON a.jogo_id = j.id
    WHERE j.desenvolvedor_id = ? AND a.status = 'ativo'
");
$stmt->execute([$dev['id']]);
$stats = $stmt->fetch();

// Buscar jogos do desenvolvedor
$stmt = $pdo->prepare("
    SELECT id, titulo, slug 
    FROM jogo 
    WHERE desenvolvedor_id = ? 
    ORDER BY titulo
");
$stmt->execute([$dev['id']]);
$jogos = $stmt->fetchAll();

// Buscar avaliações
$where = ["j.desenvolvedor_id = ?", "a.status = 'ativo'"];
$params = [$dev['id']];

if ($jogo_filter != 'todos') {
    $where[] = "a.jogo_id = ?";
    $params[] = $jogo_filter;
}

$where_clause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT a.*, u.nome_usuario, u.avatar_url, j.titulo as jogo_titulo, j.slug as jogo_slug
    FROM avaliacao a
    INNER JOIN jogo j ON a.jogo_id = j.id
    LEFT JOIN usuario u ON a.usuario_id = u.id
    WHERE $where_clause
    ORDER BY a.criado_em DESC
");
$stmt->execute($params);
$avaliacoes = $stmt->fetchAll();

$page_title = 'Avaliações - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="dev-content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-comments"></i> Avaliações dos Jogadores
                </h1>
            </div>
            
            <?php if ($success): ?>
                <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <!-- Estatísticas -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px; text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: var(--accent);">
                        <?php echo $stats['total_avaliacoes']; ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">Total de Avaliações</div>
                </div>
                
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px; text-align: center;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <span style="font-size: 36px; font-weight: 700; color: #ffc107;">
                            <?php echo number_format($stats['media_geral'], 1); ?>
                        </span>
                        <i class="fas fa-star" style="font-size: 28px; color: #ffc107;"></i>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">Média Geral</div>
                </div>
                
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px; text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: var(--warning);">
                        <?php echo $stats['sem_resposta']; ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">Aguardando Resposta</div>
                </div>
            </div>
            
            <!-- Gráfico de Distribuição -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 30px; margin-bottom: 30px;">
                <h2 style="margin-bottom: 20px;"><i class="fas fa-chart-bar"></i> Distribuição de Notas</h2>
                
                <div style="display: grid; grid-template-columns: 300px 1fr; gap: 30px; align-items: center;">
                    <canvas id="notasChart" style="max-height: 300px;"></canvas>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php
                        $total = $stats['total_avaliacoes'] ?: 1;
                        for ($i = 5; $i >= 1; $i--):
                            $count = $stats["nota_$i"];
                            $percent = round(($count / $total) * 100);
                        ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span><?php echo $i; ?> <i class="fas fa-star" style="color: #ffc107;"></i></span>
                                <span style="color: var(--text-secondary);"><?php echo $count; ?> (<?php echo $percent; ?>%)</span>
                            </div>
                            <div style="background: var(--bg-primary); height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="background: #ffc107; height: 100%; width: <?php echo $percent; ?>%;"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            
            <!-- Filtro -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 30px;">
                <form method="GET" style="display: flex; gap: 15px; align-items: end;">
                    <div class="form-group" style="margin: 0; flex: 1;">
                        <label class="form-label">Filtrar por Jogo</label>
                        <select name="jogo" class="form-control">
                            <option value="todos" <?php echo $jogo_filter == 'todos' ? 'selected' : ''; ?>>Todos os Jogos</option>
                            <?php foreach ($jogos as $jogo): ?>
                                <option value="<?php echo $jogo['id']; ?>" <?php echo $jogo_filter == $jogo['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($jogo['titulo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <?php if ($jogo_filter != 'todos'): ?>
                        <a href="<?php echo SITE_URL; ?>/developer/avaliacoes.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Lista de Avaliações -->
            <?php if (count($avaliacoes) > 0): ?>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <?php foreach ($avaliacoes as $aval): ?>
                    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px;">
                        <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <img src="<?php echo getAvatar($aval['avatar_url']); ?>" 
                                 style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                            
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                    <div>
                                        <strong><?php echo sanitize($aval['nome_usuario']); ?></strong>
                                        <span style="color: var(--text-secondary); margin-left: 10px; font-size: 13px;">
                                            <?php echo date('d/m/Y H:i', strtotime($aval['criado_em'])); ?>
                                        </span>
                                    </div>
                                    <div style="color: #ffc107;">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $aval['nota'] ? '' : ' far'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                
                                <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $aval['jogo_slug']; ?>" 
                                   style="display: inline-block; color: var(--accent); font-size: 14px; margin-bottom: 10px;">
                                    <i class="fas fa-gamepad"></i> <?php echo sanitize($aval['jogo_titulo']); ?>
                                </a>
                                
                                <p style="margin: 0; line-height: 1.6;">
                                    <?php echo nl2br(sanitize($aval['comentario'])); ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($aval['resposta_dev']): ?>
                            <!-- Resposta Existente -->
                            <div style="background: var(--bg-primary); padding: 20px; border-radius: 8px; border-left: 3px solid var(--accent); margin-top: 15px;">
                                <strong style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                    <i class="fas fa-reply"></i> Sua Resposta
                                    <span style="font-weight: normal; font-size: 13px; color: var(--text-secondary);">
                                        - <?php echo date('d/m/Y H:i', strtotime($aval['respondido_em'])); ?>
                                    </span>
                                </strong>
                                <p style="margin: 0;"><?php echo nl2br(sanitize($aval['resposta_dev'])); ?></p>
                            </div>
                        <?php else: ?>
                            <!-- Formulário de Resposta -->
                            <div style="background: var(--bg-primary); padding: 20px; border-radius: 8px; margin-top: 15px;">
                                <form method="POST">
                                    <input type="hidden" name="action" value="responder">
                                    <input type="hidden" name="avaliacao_id" value="<?php echo $aval['id']; ?>">
                                    
                                    <div class="form-group" style="margin-bottom: 15px;">
                                        <label class="form-label">
                                            <i class="fas fa-reply"></i> Responder como Desenvolvedor
                                        </label>
                                        <textarea name="resposta" class="form-control" rows="3" required
                                                  placeholder="Escreva sua resposta..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-paper-plane"></i> Enviar Resposta
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border-radius: 15px;">
                    <i class="fas fa-comments" style="font-size: 64px; color: var(--text-secondary); margin-bottom: 20px;"></i>
                    <h3>Nenhuma avaliação encontrada</h3>
                    <p style="color: var(--text-secondary);">
                        <?php if ($jogo_filter != 'todos'): ?>
                            Este jogo ainda não recebeu avaliações.
                        <?php else: ?>
                            Seus jogos ainda não receberam avaliações.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Gráfico de Distribuição de Notas
const ctx = document.getElementById('notasChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['5 Estrelas', '4 Estrelas', '3 Estrelas', '2 Estrelas', '1 Estrela'],
        datasets: [{
            data: [
                <?php echo $stats['nota_5']; ?>,
                <?php echo $stats['nota_4']; ?>,
                <?php echo $stats['nota_3']; ?>,
                <?php echo $stats['nota_2']; ?>,
                <?php echo $stats['nota_1']; ?>
            ],
            backgroundColor: [
                '#28a745',
                '#6ba3ff',
                '#ffc107',
                '#fd7e14',
                '#dc3545'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#E3E3E3',
                    padding: 15
                }
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>