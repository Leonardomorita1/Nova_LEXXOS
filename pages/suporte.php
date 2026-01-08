
<?php
// ============================================
// pages/suporte.php (NOVA PÁGINA COMPLETA)
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Criar ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['criar_ticket'])) {
    $assunto = trim($_POST['assunto']);
    $mensagem = trim($_POST['mensagem']);
    $prioridade = $_POST['prioridade'];
    
    if (empty($assunto) || empty($mensagem)) {
        $error = 'Preencha todos os campos';
    } else {
        $stmt = $pdo->prepare("INSERT INTO ticket (usuario_id, assunto, prioridade) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $assunto, $prioridade]);
        $ticket_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO ticket_mensagem (ticket_id, usuario_id, mensagem, tipo) VALUES (?, ?, ?, 'usuario')");
        $stmt->execute([$ticket_id, $user_id, $mensagem]);
        
        $message = 'Ticket criado com sucesso! Nossa equipe responderá em breve.';
    }
}

// Responder ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['responder_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    $mensagem = trim($_POST['mensagem']);
    
    $stmt = $pdo->prepare("INSERT INTO ticket_mensagem (ticket_id, usuario_id, mensagem, tipo) VALUES (?, ?, ?, 'usuario')");
    $stmt->execute([$ticket_id, $user_id, $mensagem]);
    
    $stmt = $pdo->prepare("UPDATE ticket SET status = 'aguardando', atualizado_em = NOW() WHERE id = ?");
    $stmt->execute([$ticket_id]);
    
    $message = 'Resposta enviada!';
}

// Buscar tickets do usuário
$stmt = $pdo->prepare("SELECT * FROM ticket WHERE usuario_id = ? ORDER BY criado_em DESC");
$stmt->execute([$user_id]);
$tickets = $stmt->fetchAll();

// Buscar FAQs
$stmt = $pdo->query("
    SELECT * FROM faq 
    WHERE ativo = 1 
    ORDER BY categoria, ordem, id
");
$faqs = $stmt->fetchAll();
$categorias_faq = array_unique(array_column($faqs, 'categoria'));

$page_title = 'Suporte - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
    .suporte-container { display: grid; grid-template-columns: 300px 1fr; gap: 30px; padding: 40px 0; }
    .suporte-sidebar { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 20px; position: sticky; top: 100px; height: fit-content; }
    .suporte-menu { list-style: none; }
    .suporte-menu li { margin-bottom: 10px; }
    .suporte-menu a { display: flex; align-items: center; gap: 10px; padding: 12px; color: var(--text-secondary); border-radius: 8px; transition: 0.3s; }
    .suporte-menu a:hover, .suporte-menu a.active { background: var(--bg-primary); color: var(--accent); }
    .suporte-content { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 30px; }
    .ticket-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 20px; margin-bottom: 15px; transition: 0.3s; cursor: pointer; }
    .ticket-card:hover { border-color: var(--accent); }
    .ticket-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; }
    .faq-item { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 20px; margin-bottom: 15px; }
    .faq-question { font-size: 16px; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; }
    .faq-answer { color: var(--text-secondary); line-height: 1.6; }
    @media (max-width: 992px) { .suporte-container { grid-template-columns: 1fr; } .suporte-sidebar { position: static; } }
</style>

<div class="container">
    <div class="suporte-container">
        <aside class="suporte-sidebar">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-life-ring"></i> Suporte</h3>
            <ul class="suporte-menu">
                <li><a href="#novo-ticket" onclick="showSection('novo-ticket')" class="active">
                    <i class="fas fa-plus-circle"></i> Novo Ticket
                </a></li>
                <li><a href="#meus-tickets" onclick="showSection('meus-tickets')">
                    <i class="fas fa-ticket-alt"></i> Meus Tickets (<?php echo count($tickets); ?>)
                </a></li>
                <li><a href="#faq" onclick="showSection('faq')">
                    <i class="fas fa-question-circle"></i> Perguntas Frequentes
                </a></li>
            </ul>
        </aside>

        <div class="suporte-content">
            <?php if ($message): ?>
            <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div style="background: rgba(220,53,69,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Novo Ticket -->
            <div id="section-novo-ticket" class="section-content">
                <h2><i class="fas fa-plus-circle"></i> Criar Novo Ticket</h2>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">
                    Descreva seu problema ou dúvida. Nossa equipe responderá em breve.
                </p>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Assunto</label>
                        <input type="text" name="assunto" class="form-control" required maxlength="255" 
                               placeholder="Ex: Problema ao baixar jogo">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Prioridade</label>
                        <select name="prioridade" class="form-control">
                            <option value="normal">Normal</option>
                            <option value="alta">Alta</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea name="mensagem" class="form-control" rows="8" required 
                                  placeholder="Descreva seu problema com o máximo de detalhes possível..."></textarea>
                    </div>

                    <button type="submit" name="criar_ticket" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i> Enviar Ticket
                    </button>
                </form>
            </div>

            <!-- Meus Tickets -->
            <div id="section-meus-tickets" class="section-content" style="display: none;">
                <h2><i class="fas fa-ticket-alt"></i> Meus Tickets</h2>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">
                    Acompanhe suas solicitações de suporte.
                </p>

                <?php if (count($tickets) > 0): ?>
                    <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-card" onclick="verTicket(<?php echo $ticket['id']; ?>)">
                        <div class="ticket-header">
                            <div>
                                <strong style="font-size: 16px;"><?php echo sanitize($ticket['assunto']); ?></strong>
                                <br>
                                <small style="color: var(--text-secondary);">
                                    Ticket #<?php echo $ticket['numero']; ?> • 
                                    Criado em <?php echo date('d/m/Y H:i', strtotime($ticket['criado_em'])); ?>
                                </small>
                            </div>
                            <div style="text-align: right;">
                                <?php
                                $status_colors = [
                                    'aberto' => 'danger',
                                    'respondido' => 'info',
                                    'aguardando' => 'warning',
                                    'fechado' => 'secondary'
                                ];
                                $prioridade_colors = [
                                    'baixa' => 'secondary',
                                    'normal' => 'info',
                                    'alta' => 'warning',
                                    'urgente' => 'danger'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $status_colors[$ticket['status']]; ?>">
                                    <?php echo ucfirst($ticket['status']); ?>
                                </span>
                                <br>
                                <span class="badge badge-<?php echo $prioridade_colors[$ticket['prioridade']]; ?>" style="margin-top: 5px;">
                                    <?php echo ucfirst($ticket['prioridade']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px;">
                        <i class="fas fa-ticket-alt" style="font-size: 64px; color: var(--text-secondary); margin-bottom: 20px;"></i>
                        <p style="color: var(--text-secondary);">Você ainda não tem tickets</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- FAQ -->
            <div id="section-faq" class="section-content" style="display: none;">
                <h2><i class="fas fa-question-circle"></i> Perguntas Frequentes</h2>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">
                    Encontre respostas rápidas para as dúvidas mais comuns.
                </p>

                <?php foreach ($categorias_faq as $categoria): ?>
                    <h3 style="margin: 30px 0 15px; color: var(--accent);"><?php echo sanitize($categoria); ?></h3>
                    <?php
                    $faqs_cat = array_filter($faqs, function($f) use ($categoria) {
                        return $f['categoria'] == $categoria;
                    });
                    ?>
                    <?php foreach ($faqs_cat as $faq): ?>
                    <div class="faq-item">
                        <div class="faq-question">
                            <i class="fas fa-question-circle" style="color: var(--accent);"></i>
                            <?php echo sanitize($faq['pergunta']); ?>
                        </div>
                        <div class="faq-answer">
                            <?php echo nl2br(sanitize($faq['resposta'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
function showSection(section) {
    document.querySelectorAll('.section-content').forEach(s => s.style.display = 'none');
    document.getElementById('section-' + section).style.display = 'block';
    
    document.querySelectorAll('.suporte-menu a').forEach(a => a.classList.remove('active'));
    document.querySelector(`a[href="#${section}"]`).classList.add('active');
}

function verTicket(id) {
    window.location.href = '<?php echo SITE_URL; ?>/user/ticket-detalhes.php?id=' + id;
}
</script>

<?php require_once '../includes/footer.php'; ?>