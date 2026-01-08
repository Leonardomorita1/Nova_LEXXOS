<?php
// admin/usuario-detalhes.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

if (getUserType() !== 'admin') {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: ' . SITE_URL . '/admin/usuarios.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_GET['id'];

// Buscar usuário
$stmt = $pdo->prepare("SELECT * FROM usuario WHERE id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: ' . SITE_URL . '/admin/usuarios.php');
    exit;
}

// Biblioteca
$stmt = $pdo->prepare("
    SELECT b.*, j.titulo, j.imagem_capa 
    FROM biblioteca b
    JOIN jogo j ON b.jogo_id = j.id
    WHERE b.usuario_id = ?
    ORDER BY b.adicionado_em DESC
");
$stmt->execute([$user_id]);
$biblioteca = $stmt->fetchAll();

// Pedidos
$stmt = $pdo->prepare("
    SELECT * FROM pedido 
    WHERE usuario_id = ?
    ORDER BY criado_em DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$pedidos = $stmt->fetchAll();

// Logs de acesso
$stmt = $pdo->prepare("
    SELECT * FROM log_acesso 
    WHERE usuario_id = ?
    ORDER BY criado_em DESC
    LIMIT 20
");
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll();

$page_title = 'Detalhes do Usuário - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <div>
                    <a href="<?php echo SITE_URL; ?>/admin/usuarios.php" style="color: var(--text-secondary); margin-bottom: 10px; display: inline-block;">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <h1 class="admin-title">
                        <i class="fas fa-user"></i> <?php echo sanitize($usuario['nome_usuario']); ?>
                    </h1>
                </div>
            </div>

            <!-- Informações do Usuário -->
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 30px;">
                <div class="form-section">
                    <h2>Informações Básicas</h2>
                    <div style="margin-bottom: 15px;">
                        <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">ID</label>
                        <strong>#<?php echo $usuario['id']; ?></strong>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Email</label>
                        <strong><?php echo sanitize($usuario['email']); ?></strong>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Nome Completo</label>
                        <strong><?php echo $usuario['nome_completo'] ? sanitize($usuario['nome_completo']) : '-'; ?></strong>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Tipo</label>
                        <span class="badge badge-info"><?php echo ucfirst($usuario['tipo']); ?></span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Status</label>
                        <?php
                        $status_colors = [
                            'ativo' => 'success',
                            'suspenso' => 'warning',
                            'banido' => 'danger'
                        ];
                        ?>
                        <span class="badge badge-<?php echo $status_colors[$usuario['status']]; ?>">
                            <?php echo ucfirst($usuario['status']); ?>
                        </span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Cadastrado em</label>
                        <strong><?php echo date('d/m/Y H:i', strtotime($usuario['criado_em'])); ?></strong>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Último Login</label>
                        <strong><?php echo $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca'; ?></strong>
                    </div>
                </div>

                <div>
                    <!-- Estatísticas -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                        <div class="stat-card">
                            <div class="stat-icon blue">
                                <i class="fas fa-gamepad"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Jogos na Biblioteca</h3>
                                <div class="stat-value"><?php echo count($biblioteca); ?></div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon green">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total de Pedidos</h3>
                                <div class="stat-value"><?php echo count($pedidos); ?></div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon yellow">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Logs de Acesso</h3>
                                <div class="stat-value"><?php echo count($logs); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Pedidos Recentes -->
                    <div class="data-table-wrapper">
                        <div class="table-header">
                            <h2>Pedidos Recentes</h2>
                        </div>
                        <?php if (count($pedidos) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td><strong>#<?php echo $pedido['numero']; ?></strong></td>
                                    <td><?php echo formatPrice($pedido['total_centavos']); ?></td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'pendente' => 'warning',
                                            'pago' => 'success',
                                            'cancelado' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $status_colors[$pedido['status']]; ?>">
                                            <?php echo ucfirst($pedido['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($pedido['criado_em'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-shopping-cart" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 10px;"></i>
                            <p style="color: var(--text-secondary);">Nenhum pedido realizado</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Logs de Acesso -->
            <div class="data-table-wrapper">
                <div class="table-header">
                    <h2>Logs de Acesso Recentes</h2>
                </div>
                <?php if (count($logs) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>IP</th>
                            <th>User Agent</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
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
                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <small><?php echo sanitize($log['user_agent']); ?></small>
                            </td>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['criado_em'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-history" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 10px;"></i>
                    <p style="color: var(--text-secondary);">Nenhum log de acesso</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>