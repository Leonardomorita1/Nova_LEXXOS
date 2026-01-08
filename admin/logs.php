<?php
// ============================================
// admin/logs.php
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();

// Filtros
$where = ['1=1'];
$params = [];

if (isset($_GET['evento']) && !empty($_GET['evento'])) {
    $where[] = 'evento = ?';
    $params[] = $_GET['evento'];
}

if (isset($_GET['usuario']) && !empty($_GET['usuario'])) {
    $where[] = 'email LIKE ?';
    $params[] = '%' . $_GET['usuario'] . '%';
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;
$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM log_acesso WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT * FROM log_acesso 
    WHERE $where_sql
    ORDER BY criado_em DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$page_title = 'Logs do Sistema - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title"><i class="fas fa-history"></i> Logs do Sistema</h1>
            </div>

            <!-- Filtros -->
            <form method="GET" class="filters">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Usuário/Email</label>
                        <input type="text" name="usuario" placeholder="Buscar..." value="<?php echo $_GET['usuario'] ?? ''; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Evento</label>
                        <select name="evento">
                            <option value="">Todos</option>
                            <option value="login_sucesso" <?php echo ($_GET['evento'] ?? '') == 'login_sucesso' ? 'selected' : ''; ?>>Login Sucesso</option>
                            <option value="login_falha" <?php echo ($_GET['evento'] ?? '') == 'login_falha' ? 'selected' : ''; ?>>Login Falha</option>
                            <option value="logout" <?php echo ($_GET['evento'] ?? '') == 'logout' ? 'selected' : ''; ?>>Logout</option>
                        </select>
                    </div>
                    <div class="filter-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="<?php echo SITE_URL; ?>/admin/logs.php" class="btn btn-secondary">
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
                            <th>Data/Hora</th>
                            <th>Email</th>
                            <th>Evento</th>
                            <th>IP</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['criado_em'])); ?></td>
                            <td><strong><?php echo sanitize($log['email']); ?></strong></td>
                            <td>
                                <?php
                                $evento_colors = [
                                    'login_sucesso' => 'success',
                                    'login_falha' => 'danger',
                                    'logout' => 'info'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $evento_colors[$log['evento']] ?? 'secondary'; ?>">
                                    <?php echo str_replace('_', ' ', ucfirst($log['evento'])); ?>
                                </span>
                            </td>
                            <td><code><?php echo $log['ip']; ?></code></td>
                            <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <small style="color: var(--text-secondary);"><?php echo sanitize($log['user_agent']); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
