<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();
$message = '';

// Ações
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $jogo_id = $_POST['jogo_id'];
    $action = $_POST['action'];
    
    if ($action == 'aprovar') {
        $stmt = $pdo->prepare("UPDATE jogo SET status = 'publicado', publicado_em = NOW() WHERE id = ?");
        $stmt->execute([$jogo_id]);
        $message = 'Jogo aprovado com sucesso!';
    } elseif ($action == 'reprovar') {
        $motivo = $_POST['motivo'] ?? '';
        $stmt = $pdo->prepare("UPDATE jogo SET status = 'rascunho', motivo_rejeicao = ? WHERE id = ?");
        $stmt->execute([$motivo, $jogo_id]);
        $message = 'Jogo reprovado!';
    } elseif ($action == 'suspender') {
        $stmt = $pdo->prepare("UPDATE jogo SET status = 'suspenso' WHERE id = ?");
        $stmt->execute([$jogo_id]);
        $message = 'Jogo suspenso!';
    } elseif ($action == 'remover') {
        $stmt = $pdo->prepare("UPDATE jogo SET status = 'removido' WHERE id = ?");
        $stmt->execute([$jogo_id]);
        $message = 'Jogo removido!';
    }
}

// Filtros
$where = ['1=1'];
$params = [];

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where[] = 'status = ?';
    $params[] = $_GET['status'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where[] = 'titulo LIKE ?';
    $params[] = '%' . $_GET['search'] . '%';
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM jogo WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT j.*, d.nome_estudio 
    FROM jogo j
    JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    WHERE $where_sql
    ORDER BY j.criado_em DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$jogos = $stmt->fetchAll();

$page_title = 'Gerenciar Jogos - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title"><i class="fas fa-gamepad"></i> Gerenciar Jogos</h1>
                <div style="color: var(--text-secondary);">Total: <?php echo $total; ?> jogos</div>
            </div>

            <?php if ($message): ?>
            <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Filtros -->
            <form method="GET" class="filters">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Buscar</label>
                        <input type="text" name="search" placeholder="Nome do jogo..." value="<?php echo $_GET['search'] ?? ''; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="rascunho" <?php echo ($_GET['status'] ?? '') == 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                            <option value="em_revisao" <?php echo ($_GET['status'] ?? '') == 'em_revisao' ? 'selected' : ''; ?>>Em Revisão</option>
                            <option value="publicado" <?php echo ($_GET['status'] ?? '') == 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                            <option value="suspenso" <?php echo ($_GET['status'] ?? '') == 'suspenso' ? 'selected' : ''; ?>>Suspenso</option>
                        </select>
                    </div>
                    <div class="filter-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="<?php echo SITE_URL; ?>/admin/jogos.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>

            <!-- Tabela -->
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Jogo</th>
                            <th>Desenvolvedor</th>
                            <th>Preço</th>
                            <th>Vendas</th>
                            <th>Nota</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jogos as $jogo): ?>
                        <tr>
                            <td>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <img src="<?php echo SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                                         style="width: 60px; height: 80px; object-fit: cover; border-radius: 4px;">
                                    <div>
                                        <strong><?php echo sanitize($jogo['titulo']); ?></strong>
                                        <br><small style="color: var(--text-secondary);">ID: #<?php echo $jogo['id']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo sanitize($jogo['nome_estudio']); ?></td>
                            <td><strong><?php echo formatPrice($jogo['em_promocao'] && $jogo['preco_promocional_centavos'] ? $jogo['preco_promocional_centavos'] : $jogo['preco_centavos']); ?></strong></td>
                            <td><?php echo $jogo['total_vendas']; ?></td>
                            <td>
                                <i class="fas fa-star" style="color: var(--warning);"></i>
                                <?php echo number_format($jogo['nota_media'], 1); ?>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'rascunho' => 'secondary',
                                    'em_revisao' => 'warning',
                                    'publicado' => 'success',
                                    'suspenso' => 'danger',
                                    'removido' => 'danger'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $status_colors[$jogo['status']]; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $jogo['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>" 
                                       class="btn-icon view" title="Ver Jogo" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($jogo['status'] == 'em_revisao'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="jogo_id" value="<?php echo $jogo['id']; ?>">
                                        <input type="hidden" name="action" value="aprovar">
                                        <button type="submit" class="btn-icon edit" title="Aprovar" 
                                                onclick="return confirm('Aprovar este jogo?')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn-icon delete" title="Reprovar"
                                            onclick="reprovarJogo(<?php echo $jogo['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($jogo['status'] == 'publicado'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="jogo_id" value="<?php echo $jogo['id']; ?>">
                                        <input type="hidden" name="action" value="suspender">
                                        <button type="submit" class="btn-icon delete" title="Suspender" 
                                                onclick="return confirm('Suspender este jogo?')">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>" 
                           class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reprovar -->
<div id="modalReprovar" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; padding: 20px; align-items: center; justify-content: center;">
    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; max-width: 500px; width: 100%;">
        <div style="padding: 20px; border-bottom: 1px solid var(--border);">
            <h2>Reprovar Jogo</h2>
        </div>
        <form method="POST" style="padding: 20px;">
            <input type="hidden" name="jogo_id" id="reprovar_jogo_id">
            <input type="hidden" name="action" value="reprovar">
            <div class="form-group">
                <label>Motivo da reprovação</label>
                <textarea name="motivo" class="form-control" rows="4" required></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-danger">Reprovar</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalReprovar').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function reprovarJogo(id) {
    document.getElementById('reprovar_jogo_id').value = id;
    document.getElementById('modalReprovar').style.display = 'flex';
}
</script>

<?php require_once '../includes/footer.php'; ?>