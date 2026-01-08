<?php
// ============================================
// admin/ticket-detalhes.php
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

if (!isset($_GET['id'])) {
    header('Location: ' . SITE_URL . '/admin/tickets.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$ticket_id = $_GET['id'];
$message = '';

// Responder
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['responder'])) {
    $stmt = $pdo->prepare("INSERT INTO ticket_mensagem (ticket_id, usuario_id, mensagem, tipo) VALUES (?, ?, ?, 'admin')");
    $stmt->execute([$ticket_id, $_SESSION['user_id'], $_POST['mensagem']]);
    
    $stmt = $pdo->prepare("UPDATE ticket SET status = 'respondido', atualizado_em = NOW() WHERE id = ?");
    $stmt->execute([$ticket_id]);
    
    $message = 'Resposta enviada!';
}

// Atualizar status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $stmt = $pdo->prepare("UPDATE ticket SET status = ?, atualizado_em = NOW() WHERE id = ?");
    $stmt->execute([$_POST['status'], $ticket_id]);
    $message = 'Status atualizado!';
}

// Buscar ticket
$stmt = $pdo->prepare("
    SELECT t.*, u.nome_usuario, u.email 
    FROM ticket t
    JOIN usuario u ON t.usuario_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: ' . SITE_URL . '/admin/tickets.php');
    exit;
}

// Buscar mensagens
$stmt = $pdo->prepare("
    SELECT tm.*, u.nome_usuario 
    FROM ticket_mensagem tm
    JOIN usuario u ON tm.usuario_id = u.id
    WHERE tm.ticket_id = ?
    ORDER BY tm.criado_em
");
$stmt->execute([$ticket_id]);
$mensagens = $stmt->fetchAll();

$page_title = 'Ticket #' . $ticket['numero'] . ' - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <div>
                    <a href="<?php echo SITE_URL; ?>/admin/tickets.php" style="color: var(--text-secondary); margin-bottom: 10px; display: inline-block;">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <h1 class="admin-title">Ticket #<?php echo $ticket['numero']; ?></h1>
                </div>
                <form method="POST" style="display: flex; gap: 10px;">
                    <select name="status" class="form-control" style="width: auto;">
                        <option value="aberto" <?php echo $ticket['status'] == 'aberto' ? 'selected' : ''; ?>>Aberto</option>
                        <option value="respondido" <?php echo $ticket['status'] == 'respondido' ? 'selected' : ''; ?>>Respondido</option>
                        <option value="aguardando" <?php echo $ticket['status'] == 'aguardando' ? 'selected' : ''; ?>>Aguardando</option>
                        <option value="fechado" <?php echo $ticket['status'] == 'fechado' ? 'selected' : ''; ?>>Fechado</option>
                    </select>
                    <button type="submit" name="update_status" class="btn btn-primary">Atualizar</button>
                </form>
            </div>

            <?php if ($message): ?>
            <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <!-- Conversação -->
                <div>
                    <div class="form-section">
                        <h2><?php echo sanitize($ticket['assunto']); ?></h2>
                        
                        <!-- Mensagens -->
                        <?php foreach ($mensagens as $msg): ?>
                        <div style="padding: 20px; background: <?php echo $msg['tipo'] == 'admin' ? 'var(--bg-primary)' : 'var(--bg-secondary)'; ?>; border-left: 3px solid <?php echo $msg['tipo'] == 'admin' ? 'var(--accent)' : 'var(--success)'; ?>; margin-bottom: 15px; border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <strong><?php echo sanitize($msg['nome_usuario']); ?></strong>
                                <small style="color: var(--text-secondary);">
                                    <?php echo date('d/m/Y H:i', strtotime($msg['criado_em'])); ?>
                                </small>
                            </div>
                            <p style="margin: 0; white-space: pre-wrap;"><?php echo sanitize($msg['mensagem']); ?></p>
                        </div>
                        <?php endforeach; ?>

                        <!-- Responder -->
                        <?php if ($ticket['status'] != 'fechado'): ?>
                        <form method="POST" style="margin-top: 20px;">
                            <div class="form-group">
                                <label>Sua Resposta</label>
                                <textarea name="mensagem" class="form-control" rows="6" required></textarea>
                            </div>
                            <button type="submit" name="responder" class="btn btn-primary">
                                <i class="fas fa-reply"></i> Enviar Resposta
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info do Ticket -->
                <div>
                    <div class="form-section">
                        <h2>Informações</h2>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Usuário</label>
                            <strong><?php echo sanitize($ticket['nome_usuario']); ?></strong>
                            <br><small style="color: var(--text-secondary);"><?php echo sanitize($ticket['email']); ?></small>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Prioridade</label>
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
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Status</label>
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
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Criado em</label>
                            <strong><?php echo date('d/m/Y H:i', strtotime($ticket['criado_em'])); ?></strong>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Atualizado em</label>
                            <strong><?php echo date('d/m/Y H:i', strtotime($ticket['atualizado_em'])); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>