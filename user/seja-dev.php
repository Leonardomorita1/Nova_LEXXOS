<?php
// ===== user/seja-dev.php =====
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Verificar se já é desenvolvedor
$stmt = $pdo->prepare("SELECT id FROM desenvolvedor WHERE usuario_id = ?");
$stmt->execute([$user_id]);
if ($stmt->fetch()) {
    header('Location: ' . SITE_URL . '/developer/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_estudio = trim($_POST['nome_estudio']);
    $descricao_curta = trim($_POST['descricao_curta']);
    $tipo_pessoa = $_POST['tipo_pessoa'];
    $cpf_cnpj = trim($_POST['cpf_cnpj']);
    $chave_pix = trim($_POST['chave_pix']);
    
    if (empty($nome_estudio) || empty($tipo_pessoa) || empty($cpf_cnpj)) {
        $error = 'Preencha todos os campos obrigatórios';
    } else {
        try {
            $slug = generateSlug($nome_estudio);
            
            // Verificar slug único
            $stmt = $pdo->prepare("SELECT id FROM desenvolvedor WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $slug = $slug . '-' . uniqid();
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO desenvolvedor (usuario_id, nome_estudio, slug, descricao_curta, tipo_pessoa, cpf_cnpj, chave_pix, status, criado_em)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente', NOW())
            ");
            $stmt->execute([$user_id, $nome_estudio, $slug, $descricao_curta, $tipo_pessoa, $cpf_cnpj, $chave_pix]);
            
            // Atualizar tipo do usuário
            $stmt = $pdo->prepare("UPDATE usuario SET tipo = 'desenvolvedor' WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['user_type'] = 'desenvolvedor';
            
            $success = 'Solicitação enviada! Aguarde análise da nossa equipe.';
        } catch (Exception $e) {
            $error = 'Erro ao enviar solicitação';
        }
    }
}

$page_title = 'Seja um Desenvolvedor - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.seja-dev-page {
    padding: 30px 0;
    max-width: 800px;
    margin: 0 auto;
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin: 40px 0;
}

.benefit-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 25px;
    text-align: center;
}

.benefit-icon {
    font-size: 48px;
    color: var(--accent);
    margin-bottom: 15px;
}

.benefit-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}

.benefit-description {
    font-size: 14px;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .benefits-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container">
    <div class="seja-dev-page">
        <div class="page-header" style="text-align: center;">
            <h1 class="page-title">
                <i class="fas fa-rocket"></i> Seja um Desenvolvedor
            </h1>
            <p class="page-subtitle">
                Publique seus jogos e alcance milhares de jogadores
            </p>
        </div>
        
        <?php if ($success): ?>
            <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 20px; border-radius: 10px; text-align: center; margin-bottom: 30px;">
                <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 15px;"></i>
                <h3><?php echo $success; ?></h3>
                <p style="margin-top: 10px;">Você receberá uma notificação quando sua conta for aprovada.</p>
            </div>
        <?php else: ?>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-percentage"></i></div>
                    <h3 class="benefit-title">Taxa Competitiva</h3>
                    <p class="benefit-description">Apenas <?php echo getConfig('taxa_plataforma', $pdo); ?>% de comissão sobre vendas</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-users"></i></div>
                    <h3 class="benefit-title">Grande Audiência</h3>
                    <p class="benefit-description">Milhares de jogadores ativos na plataforma</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-bolt"></i></div>
                    <h3 class="benefit-title">Publicação Rápida</h3>
                    <p class="benefit-description">Análise em até 48 horas</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-chart-line"></i></div>
                    <h3 class="benefit-title">Analytics Completo</h3>
                    <p class="benefit-description">Dashboards detalhados de vendas e estatísticas</p>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div style="background: rgba(220,53,69,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 40px;">
                <h2 style="margin-bottom: 25px;">Formulário de Cadastro</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Nome do Estúdio *</label>
                        <input type="text" name="nome_estudio" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Descrição Curta</label>
                        <input type="text" name="descricao_curta" class="form-control" maxlength="300" 
                               placeholder="Uma breve descrição sobre seu estúdio">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Pessoa *</label>
                        <select name="tipo_pessoa" class="form-control" required>
                            <option value="">Selecione...</option>
                            <option value="fisica">Pessoa Física</option>
                            <option value="juridica">Pessoa Jurídica</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">CPF/CNPJ *</label>
                        <input type="text" name="cpf_cnpj" class="form-control" required
                               placeholder="Apenas números">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Chave PIX (para recebimentos)</label>
                        <input type="text" name="chave_pix" class="form-control"
                               placeholder="CPF, email, telefone ou chave aleatória">
                    </div>
                    
                    <div style="background: rgba(76,139,245,0.1); border: 1px solid var(--accent); border-radius: 8px; padding: 15px; margin: 20px 0;">
                        <p style="font-size: 13px; margin: 0;">
                            <i class="fas fa-info-circle"></i>
                            Ao enviar este formulário, sua conta será analisada por nossa equipe. 
                            Você receberá uma notificação com o resultado em até 48 horas.
                        </p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-paper-plane"></i> Enviar Solicitação
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>