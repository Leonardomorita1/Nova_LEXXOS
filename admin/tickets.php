<?php
// ============================================
// admin/tickets.php
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();
$message = '';

// Atualizar status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $stmt = $pdo->prepare("UPDATE ticket SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $_POST['ticket_id']]);
    $message = 'Status atualizado!';
}

// Responder ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['responder'])) {
    $ticket_id = $_POST['ticket_id'];
    $mensagem = $_POST['mensagem'];
    
    $stmt = $pdo->prepare("INSERT INTO ticket_mensagem (ticket_id, usuario_id, mensagem, tipo) VALUES (?, ?, ?, 'admin')");
    $stmt->execute([$ticket_id, $_SESSION['user_id'], $mensagem]);
    
    $stmt = $pdo->prepare("UPDATE ticket SET status = 'respondido', atualizado_em = NOW() WHERE id = ?");
    $stmt->execute([$ticket_id]);
    
    $message = 'Resposta enviada!';
}

// Listar tickets
$where = ['1=1'];
$params = [];

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where[] = 't.status = ?';
    $params[] = $_GET['status'];
}

$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT t.*, u.nome_usuario, u.email
    FROM ticket t
    JOIN usuario u ON t.usuario_id = u.id
    WHERE $where_sql
    ORDER BY 
        CASE t.prioridade
            WHEN 'urgente' THEN 1
            WHEN 'alta' THEN 2
            WHEN 'normal' THEN 3
            WHEN 'baixa' THEN 4
        END,
        t.criado_em DESC
");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$page_title = 'Tickets de Suporte - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title"><i class="fas fa-life-ring"></i> Tickets de Suporte</h1>
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
                            <option value="aberto" <?php echo ($_GET['status'] ?? '') == 'aberto' ? 'selected' : ''; ?>>Aberto</option>
                            <option value="respondido" <?php echo ($_GET['status'] ?? '') == 'respondido' ? 'selected' : ''; ?>>Respondido</option>
                            <option value="aguardando" <?php echo ($_GET['status'] ?? '') == 'aguardando' ? 'selected' : ''; ?>>Aguardando</option>
                            <option value="fechado" <?php echo ($_GET['status'] ?? '') == 'fechado' ? 'selected' : ''; ?>>Fechado</option>
                        </select>
                    </div>
                    <div class="filter-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="<?php echo SITE_URL; ?>/admin/tickets.php" class="btn btn-secondary">
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
                            <th>Ticket</th>
                            <th>Usuário</th>
                            <th>Assunto</th>
                            <th>Prioridade</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><strong><?php echo $ticket['numero']; ?></strong></td>
                            <td>
                                <strong><?php echo sanitize($ticket['nome_usuario']); ?></strong>
                                <br><small style="color: var(--text-secondary);"><?php echo sanitize($ticket['email']); ?></small>
                            </td>
                            <td><?php echo sanitize($ticket['assunto']); ?></td>
                            <td>
                                <?php
                                $prioridade_colors = [
                                    'baixa' => 'secondary',
                                    'normal' => 'info',
                                    'alta' => 'warning',
                                    'urgente' => 'danger'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $prioridade_colors[$ticket['prioridade']]; ?>">
                                    <?php echo ucfirst($ticket['prioridade']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'aberto' => 'danger',
                                    'respondido' => 'info',
                                    'aguardando' => 'warning',
                                    'fechado' => 'secondary'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $status_colors[$ticket['status']]; ?>">
                                    <?php echo ucfirst($ticket['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($ticket['criado_em'])); ?></td>
                            <td>
                                <a href="<?php echo SITE_URL; ?>/admin/ticket-detalhes.php?id=<?php echo $ticket['id']; ?>" 
                                   class="btn-icon view" title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>