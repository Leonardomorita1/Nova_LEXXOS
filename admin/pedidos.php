<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();

// Filtros
$where = ['1=1'];
$params = [];

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where[] = 'p.status = ?';
    $params[] = $_GET['status'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where[] = '(p.numero LIKE ? OR u.nome_usuario LIKE ? OR u.email LIKE ?)';
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM pedido p
    JOIN usuario u ON p.usuario_id = u.id
    WHERE $where_sql
");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT p.*, u.nome_usuario, u.email
    FROM pedido p
    JOIN usuario u ON p.usuario_id = u.id
    WHERE $where_sql
    ORDER BY p.criado_em DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

$page_title = 'Gerenciar Pedidos - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title"><i class="fas fa-shopping-cart"></i> Gerenciar Pedidos</h1>
                <div style="color: var(--text-secondary);">Total: <?php echo $total; ?> pedidos</div>
            </div>

            <!-- Filtros -->
            <form method="GET" class="filters">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Buscar</label>
                        <input type="text" name="search" placeholder="Nº pedido, usuário..." value="<?php echo $_GET['search'] ?? ''; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="pendente" <?php echo ($_GET['status'] ?? '') == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="pago" <?php echo ($_GET['status'] ?? '') == 'pago' ? 'selected' : ''; ?>>Pago</option>
                            <option value="cancelado" <?php echo ($_GET['status'] ?? '') == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            <option value="reembolsado" <?php echo ($_GET['status'] ?? '') == 'reembolsado' ? 'selected' : ''; ?>>Reembolsado</option>
                        </select>
                    </div>
                    <div class="filter-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="<?php echo SITE_URL; ?>/admin/pedidos.php" class="btn btn-secondary">
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
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Itens</th>
                            <th>Total</th>
                            <th>Pagamento</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                        <?php
                        // Buscar itens do pedido
                        $stmt_itens = $pdo->prepare("SELECT COUNT(*) as total FROM item_pedido WHERE pedido_id = ?");
                        $stmt_itens->execute([$pedido['id']]);
                        $total_itens = $stmt_itens->fetch()['total'];
                        ?>
                        <tr>
                            <td><strong>#<?php echo $pedido['numero']; ?></strong></td>
                            <td>
                                <strong><?php echo sanitize($pedido['nome_usuario']); ?></strong>
                                <br><small style="color: var(--text-secondary);"><?php echo sanitize($pedido['email']); ?></small>
                            </td>
                            <td><?php echo $total_itens; ?> item(ns)</td>
                            <td><strong style="color: var(--success);"><?php echo formatPrice($pedido['total_centavos']); ?></strong></td>
                            <td>
                                <?php 
                                $metodos = [
                                    'pix' => 'PIX',
                                    'cartao_credito' => 'Cartão',
                                    'boleto' => 'Boleto'
                                ];
                                echo $metodos[$pedido['metodo_pagamento']] ?? ucfirst($pedido['metodo_pagamento']);
                                ?>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'pendente' => 'warning',
                                    'pago' => 'success',
                                    'cancelado' => 'danger',
                                    'reembolsado' => 'info'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $status_colors[$pedido['status']]; ?>">
                                    <?php echo ucfirst($pedido['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($pedido['criado_em'])); ?>
                                <br><small style="color: var(--text-secondary);"><?php echo date('H:i', strtotime($pedido['criado_em'])); ?></small>
                            </td>
                            <td>
                                <a href="<?php echo SITE_URL; ?>/admin/pedido-detalhes.php?id=<?php echo $pedido['id']; ?>" 
                                   class="btn-icon view" title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </a>
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