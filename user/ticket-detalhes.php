<?php
// ============================================
// user/ticket-detalhes.php (NOVA PÁGINA)
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();

if (!isset($_GET['id'])) {
    header('Location: ' . SITE_URL . '/pages/suporte.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];
$ticket_id = $_GET['id'];
$message = '';

// Buscar ticket
$stmt = $pdo->prepare("SELECT * FROM ticket WHERE id = ? AND usuario_id = ?");
$stmt->execute([$ticket_id, $user_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: ' . SITE_URL . '/pages/suporte.php');
    exit;
}

// Responder
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['responder'])) {
    $mensagem = trim($_POST['mensagem']);
    
    $stmt = $pdo->prepare("INSERT INTO ticket_mensagem (ticket_id, usuario_id, mensagem, tipo) VALUES (?, ?, ?, 'usuario')");
    $stmt->execute([$ticket_id, $user_id, $mensagem]);
    
    $stmt = $pdo->prepare("UPDATE ticket SET status = 'aguardando', atualizado_em = NOW() WHERE id = ?");
    $stmt->execute([$ticket_id]);
    
    $message = 'Resposta enviada!';
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

$page_title = 'Ticket #' . $ticket['numero'] . ' - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
    .ticket-container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
    .ticket-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 30px; margin-bottom: 20px; }
    .msg-box { padding: 20px; margin-bottom: 15px; border-radius: 8px; border-left: 3px solid; }
    .msg-admin { background: var(--bg-primary); border-left-color: var(--accent); }
    .msg-user { background: var(--bg-secondary); border-left-color: var(--success); }
</style>

<div class="container">
    <div class="ticket-container">
        <a href="<?php echo SITE_URL; ?>/pages/suporte.php" style="color: var(--text-secondary); margin-bottom: 20px; display: inline-block;">
            <i class="fas fa-arrow-left"></i> Voltar para Suporte
        </a>

        <?php if ($message): ?>
        <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <div class="ticket-card">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                <div>
                    <h1 style="font-size: 24px; margin-bottom: 10px;"><?php echo sanitize($ticket['assunto']); ?></h1>
                    <div style="color: var(--text-secondary);">
                        Ticket #<?php echo $ticket['numero']; ?> • 
                        Criado em <?php echo date('d/m/Y H:i', strtotime($ticket['criado_em'])); ?>
                    </div>
                </div>
                <div>
                    <?php
                    $status_colors = ['aberto' => 'danger', 'respondido' => 'info', 'aguardando' => 'warning', 'fechado' => 'secondary'];
                    $prioridade_colors = ['baixa' => 'secondary', 'normal' => 'info', 'alta' => 'warning', 'urgente' => 'danger'];
                    ?>
                    <span class="badge badge-<?php echo $status_colors[$ticket['status']]; ?>"><?php echo ucfirst($ticket['status']); ?></span>
                    <br>
                    <span class="badge badge-<?php echo $prioridade_colors[$ticket['prioridade']]; ?>" style="margin-top: 5px;">
                        <?php echo ucfirst($ticket['prioridade']); ?>
                    </span>
                </div>
            </div>

            <hr style="border-color: var(--border); margin: 20px 0;">

            <!-- Mensagens -->
            <?php foreach ($mensagens as $msg): ?>
            <div class="<?php echo $msg['tipo'] == 'admin' ? 'msg-admin' : 'msg-user'; ?>" style="padding: 20px; margin-bottom: 15px; border-radius: 8px; border-left: 3px solid <?php echo $msg['tipo'] == 'admin' ? 'var(--accent)' : 'var(--success)'; ?>;">
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
            <form method="POST" style="margin-top: 30px;">
                <div class="form-group">
                    <label class="form-label">Sua Resposta</label>
                    <textarea name="mensagem" class="form-control" rows="6" required 
                              placeholder="Digite sua resposta..."></textarea>
                </div>
                <button type="submit" name="responder" class="btn btn-primary">
                    <i class="fas fa-reply"></i> Enviar Resposta
                </button>
            </form>
            <?php else: ?>
            <div style="background: rgba(108,117,125,0.1); border: 1px solid var(--text-secondary); padding: 20px; border-radius: 8px; text-align: center; margin-top: 20px;">
                <i class="fas fa-check-circle" style="font-size: 48px; color: var(--success); margin-bottom: 10px;"></i>
                <p style="color: var(--text-secondary);">Este ticket foi fechado</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>