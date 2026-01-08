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
    $dev_id = $_POST['dev_id'];
    
    if ($_POST['action'] == 'aprovar') {
        $stmt = $pdo->prepare("UPDATE desenvolvedor SET status = 'ativo' WHERE id = ?");
        $stmt->execute([$dev_id]);
        $message = 'Desenvolvedor aprovado!';
    } elseif ($_POST['action'] == 'rejeitar') {
        $stmt = $pdo->prepare("UPDATE desenvolvedor SET status = 'pendente' WHERE id = ?");
        $stmt->execute([$dev_id]);
        
        // Notificar
        $stmt_user = $pdo->prepare("SELECT usuario_id FROM desenvolvedor WHERE id = ?");
        $stmt_user->execute([$dev_id]);
        $user_id = $stmt_user->fetch()['usuario_id'];
        
        $stmt = $pdo->prepare("INSERT INTO notificacao (usuario_id, tipo, titulo, mensagem) VALUES (?, 'sistema', 'Solicitação de desenvolvedor não aprovada', 'Sua solicitação não foi aprovada. Você pode revisar seus dados e tentar novamente.')");
        $stmt->execute([$user_id]);
        
        $message = 'Desenvolvedor rejeitado!';
    } elseif ($_POST['action'] == 'verificar') {
        $stmt = $pdo->prepare("UPDATE desenvolvedor SET verificado = 1 WHERE id = ?");
        $stmt->execute([$dev_id]);
        $message = 'Desenvolvedor verificado!';
    }
}

// Filtros
$where = ['1=1'];
$params = [];

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where[] = 'd.status = ?';
    $params[] = $_GET['status'];
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM desenvolvedor d WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT d.*, u.nome_usuario, u.email,
           (SELECT COUNT(*) FROM jogo WHERE desenvolvedor_id = d.id) as total_jogos,
           (SELECT SUM(total_vendas) FROM jogo WHERE desenvolvedor_id = d.id) as total_vendas
    FROM desenvolvedor d
    JOIN usuario u ON d.usuario_id = u.id
    WHERE $where_sql
    ORDER BY d.criado_em DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$desenvolvedores = $stmt->fetchAll();

$page_title = 'Gerenciar Desenvolvedores - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title"><i class="fas fa-code"></i> Gerenciar Desenvolvedores</h1>
                <div style="color: var(--text-secondary);">Total: <?php echo $total; ?> desenvolvedores</div>
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
                        <label>Status</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="pendente" <?php echo ($_GET['status'] ?? '') == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="ativo" <?php echo ($_GET['status'] ?? '') == 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="suspenso" <?php echo ($_GET['status'] ?? '') == 'suspenso' ? 'selected' : ''; ?>>Suspenso</option>
                        </select>
                    </div>
                    <div class="filter-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="<?php echo SITE_URL; ?>/admin/desenvolvedores.php" class="btn btn-secondary">
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
                            <th>Estúdio</th>
                            <th>Usuário</th>
                            <th>Jogos</th>
                            <th>Vendas</th>
                            <th>Verificado</th>
                            <th>Status</th>
                            <th>Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($desenvolvedores as $dev): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($dev['nome_estudio']); ?></strong>
                                <?php if ($dev['logo_url']): ?>
                                    <br><img src="<?php echo SITE_URL . $dev['logo_url']; ?>" 
                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-top: 5px;">
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo sanitize($dev['nome_usuario']); ?></strong>
                                <br><small style="color: var(--text-secondary);"><?php echo sanitize($dev['email']); ?></small>
                            </td>
                            <td><?php echo $dev['total_jogos'] ?? 0; ?></td>
                            <td><?php echo $dev['total_vendas'] ?? 0; ?></td>
                            <td>
                                <?php if ($dev['verificado']): ?>
                                    <i class="fas fa-check-circle" style="color: var(--success);" title="Verificado"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle" style="color: var(--text-secondary);" title="Não verificado"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'pendente' => 'warning',
                                    'ativo' => 'success',
                                    'suspenso' => 'danger'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $status_colors[$dev['status']]; ?>">
                                    <?php echo ucfirst($dev['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($dev['criado_em'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?php echo SITE_URL; ?>/pages/desenvolvedor.php?slug=<?php echo $dev['slug']; ?>" 
                                       class="btn-icon view" title="Ver Perfil" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($dev['status'] == 'pendente'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="dev_id" value="<?php echo $dev['id']; ?>">
                                        <input type="hidden" name="action" value="aprovar">
                                        <button type="submit" class="btn-icon edit" title="Aprovar">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="dev_id" value="<?php echo $dev['id']; ?>">
                                        <input type="hidden" name="action" value="rejeitar">
                                        <button type="submit" class="btn-icon delete" title="Rejeitar">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if (!$dev['verificado'] && $dev['status'] == 'ativo'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="dev_id" value="<?php echo $dev['id']; ?>">
                                        <input type="hidden" name="action" value="verificar">
                                        <button type="submit" class="btn-icon edit" title="Verificar">
                                            <i class="fas fa-certificate"></i>
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

<?php require_once '../includes/footer.php'; ?>