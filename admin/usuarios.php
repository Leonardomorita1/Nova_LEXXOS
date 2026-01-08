<?php
// admin/usuarios.php
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

// Ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $user_id = $_POST['user_id'];
        
        switch ($_POST['action']) {
            case 'suspend':
                $stmt = $pdo->prepare("UPDATE usuario SET status = 'suspenso' WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'Usuário suspenso com sucesso!';
                break;
                
            case 'activate':
                $stmt = $pdo->prepare("UPDATE usuario SET status = 'ativo' WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'Usuário ativado com sucesso!';
                break;
                
            case 'ban':
                $stmt = $pdo->prepare("UPDATE usuario SET status = 'banido' WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'Usuário banido com sucesso!';
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM usuario WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'Usuário excluído com sucesso!';
                break;
        }
    }
}

// Filtros
$where = ['1=1'];
$params = [];

if (isset($_GET['tipo']) && !empty($_GET['tipo'])) {
    $where[] = 'tipo = ?';
    $params[] = $_GET['tipo'];
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where[] = 'status = ?';
    $params[] = $_GET['status'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where[] = '(nome_usuario LIKE ? OR email LIKE ? OR nome_completo LIKE ?)';
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuario WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT * FROM usuario 
    WHERE $where_sql
    ORDER BY criado_em DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$page_title = 'Gerenciar Usuários - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title">
                    <i class="fas fa-users"></i> Gerenciar Usuários
                </h1>
                <div style="color: var(--text-secondary);">
                    Total: <?php echo $total; ?> usuários
                </div>
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
                        <input type="text" name="search" placeholder="Nome, email..." 
                               value="<?php echo $_GET['search'] ?? ''; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="">Todos</option>
                            <option value="cliente" <?php echo ($_GET['tipo'] ?? '') == 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                            <option value="desenvolvedor" <?php echo ($_GET['tipo'] ?? '') == 'desenvolvedor' ? 'selected' : ''; ?>>Desenvolvedor</option>
                            <option value="admin" <?php echo ($_GET['tipo'] ?? '') == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="ativo" <?php echo ($_GET['status'] ?? '') == 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="suspenso" <?php echo ($_GET['status'] ?? '') == 'suspenso' ? 'selected' : ''; ?>>Suspenso</option>
                            <option value="banido" <?php echo ($_GET['status'] ?? '') == 'banido' ? 'selected' : ''; ?>>Banido</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="<?php echo SITE_URL; ?>/admin/usuarios.php" class="btn btn-secondary">
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
                            <th>ID</th>
                            <th>Usuário</th>
                            <th>Email</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Cadastro</th>
                            <th>Último Login</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $user): ?>
                        <tr>
                            <td><strong>#<?php echo $user['id']; ?></strong></td>
                            <td>
                                <strong><?php echo sanitize($user['nome_usuario']); ?></strong>
                                <?php if ($user['nome_completo']): ?>
                                    <br><small style="color: var(--text-secondary);">
                                        <?php echo sanitize($user['nome_completo']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo sanitize($user['email']); ?></td>
                            <td>
                                <?php
                                $tipo_colors = [
                                    'cliente' => 'info',
                                    'desenvolvedor' => 'warning',
                                    'admin' => 'danger'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $tipo_colors[$user['tipo']]; ?>">
                                    <?php echo ucfirst($user['tipo']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'ativo' => 'success',
                                    'suspenso' => 'warning',
                                    'banido' => 'danger'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $status_colors[$user['status']]; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($user['criado_em'])); ?></td>
                            <td>
                                <?php echo $user['ultimo_login'] ? date('d/m/Y H:i', strtotime($user['ultimo_login'])) : '-'; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?php echo SITE_URL; ?>/admin/usuario-detalhes.php?id=<?php echo $user['id']; ?>" 
                                       class="btn-icon view" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($user['status'] != 'ativo' && $user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="btn-icon edit" title="Ativar" 
                                                onclick="return confirm('Ativar este usuário?')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['status'] == 'ativo' && $user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="btn-icon delete" title="Suspender" 
                                                onclick="return confirm('Suspender este usuário?')">
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
                
                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['tipo']) ? '&tipo=' . $_GET['tipo'] : ''; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . $_GET['search'] : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo isset($_GET['tipo']) ? '&tipo=' . $_GET['tipo'] : ''; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . $_GET['search'] : ''; ?>" 
                           class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['tipo']) ? '&tipo=' . $_GET['tipo'] : ''; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . $_GET['search'] : ''; ?>">
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