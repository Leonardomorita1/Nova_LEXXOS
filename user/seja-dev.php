<?php
// user/seja-dev.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];
$errors = [];

// Verificar se já é dev
$stmt = $pdo->prepare("SELECT id FROM desenvolvedor WHERE usuario_id = ?");
$stmt->execute([$user_id]);
if ($stmt->fetch()) {
    header('Location: ' . SITE_URL . '/developer/dashboard.php');
    exit;
}

// Buscar dados do usuário para pré-preencher
$stmt = $pdo->prepare("SELECT nome_completo, cpf FROM usuario WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_estudio = trim($_POST['nome_estudio'] ?? '');
    $descricao_curta = trim($_POST['descricao_curta'] ?? '');
    $tipo_pessoa = $_POST['tipo_pessoa'] ?? 'fisica';
    $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $chave_pix = trim($_POST['chave_pix'] ?? '');
    $banco_nome = trim($_POST['banco_nome'] ?? '');
    $banco_agencia = trim($_POST['banco_agencia'] ?? '');
    $banco_conta = trim($_POST['banco_conta'] ?? '');
    $banco_tipo = $_POST['banco_tipo'] ?? 'corrente';

    if (empty($nome_estudio)) $errors['nome_estudio'] = "Nome do estúdio é obrigatório";
    if (empty($cpf_cnpj)) $errors['cpf_cnpj'] = "CPF/CNPJ é obrigatório";

    if (empty($errors)) {
        try {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nome_estudio)));
            
            // Verificar slug único
            $stmt = $pdo->prepare("SELECT id FROM desenvolvedor WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $slug .= '-' . time();
            }
            
            $sql = "INSERT INTO desenvolvedor 
                    (usuario_id, nome_estudio, slug, descricao_curta, website, tipo_pessoa, cpf_cnpj, chave_pix, banco_nome, banco_agencia, banco_conta, banco_tipo, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $user_id, $nome_estudio, $slug, $descricao_curta, $website, 
                $tipo_pessoa, $cpf_cnpj, $chave_pix, 
                $banco_nome, $banco_agencia, $banco_conta, $banco_tipo
            ]);

            $_SESSION['success'] = "Solicitação enviada com sucesso!";
            header('Location: ' . SITE_URL . '/developer/dashboard.php');
            exit;

        } catch (PDOException $e) {
            $errors['geral'] = "Erro ao cadastrar. Tente novamente.";
        }
    }
}

$page_title = "Torne-se Desenvolvedor - " . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
/* ============================================
   SEJA DEV - HERO SECTION
   ============================================ */
.dev-landing {
    min-height: calc(100vh - 80px);
    padding-bottom: 80px;
}

.dev-hero {
    position: relative;
    padding: 100px 20px 80px;
    text-align: center;
    overflow: hidden;
}

.dev-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    left: 50%;
    transform: translateX(-50%);
    width: 800px;
    height: 800px;
    background: radial-gradient(circle, rgba(14, 165, 233, 0.15) 0%, transparent 70%);
    pointer-events: none;
}

.dev-hero::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border), transparent);
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: rgba(14, 165, 233, 0.1);
    border: 1px solid rgba(14, 165, 233, 0.3);
    border-radius: 50px;
    font-size: 13px;
    color: var(--accent);
    margin-bottom: 24px;
    animation: fadeInDown 0.6s ease;
}

.hero-badge i {
    font-size: 10px;
}

.dev-hero h1 {
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 24px;
    animation: fadeInUp 0.6s ease 0.1s both;
}

.dev-hero h1 span {
    background: linear-gradient(135deg, var(--accent), #06b6d4);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.dev-hero .subtitle {
    font-size: 1.25rem;
    color: var(--text-secondary);
    max-width: 600px;
    margin: 0 auto 40px;
    line-height: 1.6;
    animation: fadeInUp 0.6s ease 0.2s both;
}

.hero-cta {
    display: flex;
    gap: 16px;
    justify-content: center;
    flex-wrap: wrap;
    animation: fadeInUp 0.6s ease 0.3s both;
}

.btn-hero-primary {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 32px;
    background: linear-gradient(135deg, var(--accent), #0284c7);
    color: #000;
    font-weight: 700;
    font-size: 1rem;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(14, 165, 233, 0.3);
}

.btn-hero-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(14, 165, 233, 0.4);
}

.btn-hero-secondary {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 32px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-weight: 600;
    font-size: 1rem;
    border-radius: 12px;
    border: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-hero-secondary:hover {
    border-color: var(--accent);
    color: var(--accent);
}

/* Stats Row */
.hero-stats {
    display: flex;
    justify-content: center;
    gap: 60px;
    margin-top: 60px;
    padding-top: 40px;
    border-top: 1px solid var(--border);
    animation: fadeInUp 0.6s ease 0.4s both;
}

.hero-stat {
    text-align: center;
}

.hero-stat .value {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
}

.hero-stat .value span {
    color: var(--accent);
}

.hero-stat .label {
    font-size: 14px;
    color: var(--text-secondary);
    margin-top: 8px;
}

/* ============================================
   BENEFITS SECTION
   ============================================ */
.benefits-section {
    padding: 80px 0;
    position: relative;
}

.section-header {
    text-align: center;
    margin-bottom: 60px;
}

.section-header h2 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 16px;
}

.section-header p {
    color: var(--text-secondary);
    max-width: 500px;
    margin: 0 auto;
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
}

.benefit-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 32px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.benefit-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent), #06b6d4);
    opacity: 0;
    transition: opacity 0.3s;
}

.benefit-card:hover {
    transform: translateY(-4px);
    border-color: var(--accent);
}

.benefit-card:hover::before {
    opacity: 1;
}

.benefit-icon {
    width: 56px;
    height: 56px;
    background: rgba(14, 165, 233, 0.1);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.benefit-icon i {
    font-size: 24px;
    color: var(--accent);
}

.benefit-card h3 {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 12px;
}

.benefit-card p {
    color: var(--text-secondary);
    line-height: 1.6;
    font-size: 15px;
}

.benefit-highlight {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: var(--success);
    margin-top: 16px;
}

/* ============================================
   HOW IT WORKS
   ============================================ */
.steps-section {
    padding: 80px 0;
    background: var(--bg-secondary);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.steps-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
    position: relative;
}

.steps-grid::before {
    content: '';
    position: absolute;
    top: 40px;
    left: 60px;
    right: 60px;
    height: 2px;
    background: linear-gradient(90deg, var(--accent), #06b6d4);
    opacity: 0.3;
}

.step-card {
    text-align: center;
    position: relative;
}

.step-number {
    width: 80px;
    height: 80px;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    position: relative;
    z-index: 1;
    transition: all 0.3s;
}

.step-number span {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--accent);
}

.step-card:hover .step-number {
    border-color: var(--accent);
    background: rgba(14, 165, 233, 0.1);
}

.step-card h4 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 8px;
}

.step-card p {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.5;
}

/* ============================================
   REGISTRATION FORM
   ============================================ */
.registration-section {
    padding: 80px 0;
}

.form-container {
    max-width: 900px;
    margin: 0 auto;
}

.form-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 24px;
    overflow: hidden;
}

.form-header {
    padding: 32px 40px;
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.1), rgba(6, 182, 212, 0.05));
    border-bottom: 1px solid var(--border);
}

.form-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.form-header h2 i {
    color: var(--accent);
}

.form-header p {
    color: var(--text-secondary);
}

.form-body {
    padding: 40px;
}

/* Progress Steps */
.form-progress {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 40px;
}

.progress-step {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    transition: all 0.3s;
}

.progress-step.active {
    background: rgba(14, 165, 233, 0.1);
    border-color: var(--accent);
    color: var(--accent);
}

.progress-step.completed {
    background: rgba(16, 185, 129, 0.1);
    border-color: var(--success);
    color: var(--success);
}

.progress-step i {
    font-size: 14px;
}

/* Form Sections */
.form-section {
    margin-bottom: 40px;
    display: none;
}

.form-section.active {
    display: block;
    animation: fadeIn 0.4s ease;
}

.form-section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--accent);
    margin-bottom: 24px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.form-section-title i {
    width: 32px;
    height: 32px;
    background: rgba(14, 165, 233, 0.1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-group label .required {
    color: var(--danger);
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 15px;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.form-control.error {
    border-color: var(--danger);
    background: rgba(239, 68, 68, 0.05);
}

.form-control::placeholder {
    color: var(--text-muted);
}

select.form-control {
    cursor: pointer;
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.form-hint {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
}

.error-message {
    font-size: 12px;
    color: var(--danger);
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Info Box */
.info-box {
    display: flex;
    gap: 16px;
    padding: 20px;
    background: rgba(14, 165, 233, 0.05);
    border: 1px solid rgba(14, 165, 233, 0.2);
    border-radius: 12px;
    margin-bottom: 24px;
}

.info-box.warning {
    background: rgba(245, 158, 11, 0.05);
    border-color: rgba(245, 158, 11, 0.2);
}

.info-box i {
    font-size: 20px;
    color: var(--accent);
    margin-top: 2px;
}

.info-box.warning i {
    color: var(--warning);
}

.info-box-content h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
}

.info-box-content p {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.5;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 30px;
    border-top: 1px solid var(--border);
    margin-top: 30px;
}

.btn-back {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-secondary);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-back:hover {
    border-color: var(--text-primary);
    color: var(--text-primary);
}

.btn-next,
.btn-submit {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    background: var(--accent);
    border: none;
    border-radius: 10px;
    color: #000;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-next:hover,
.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
}

.btn-submit {
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
}

/* Checkbox Custom */
.checkbox-group {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    background: var(--bg-primary);
    border-radius: 10px;
    cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--accent);
    cursor: pointer;
}

.checkbox-group label {
    font-size: 14px;
    color: var(--text-secondary);
    cursor: pointer;
}

.checkbox-group label a {
    color: var(--accent);
}

/* ============================================
   ALERT BOXES
   ============================================ */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: var(--success);
}

/* ============================================
   ANIMATIONS
   ============================================ */
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 900px) {
    .hero-stats {
        gap: 30px;
    }
    
    .steps-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .steps-grid::before {
        display: none;
    }
    
    .form-body {
        padding: 24px;
    }
    
    .form-progress {
        flex-wrap: wrap;
    }
}

@media (max-width: 600px) {
    .dev-hero {
        padding: 60px 16px;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 24px;
    }
    
    .steps-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 12px;
    }
    
    .btn-back,
    .btn-next,
    .btn-submit {
        width: 100%;
        justify-content: center;
    }
    
    .progress-step span {
        display: none;
    }
}
</style>

<div class="dev-landing">
    <!-- ============================================
         HERO SECTION
         ============================================ -->
    <section class="dev-hero">
        <div class="container">
            <span class="hero-badge">
                <i class="fas fa-circle"></i>
                Programa de Desenvolvedores
            </span>
            
            <h1>
                Transforme seu jogo<br>
                em <span>sucesso</span>
            </h1>
            
            <p class="subtitle">
                Publique seus jogos na Lexxos e alcance milhares de jogadores. 
                Taxas justas, pagamentos rápidos e controle total sobre suas criações.
            </p>
            
            <div class="hero-cta">
                <button class="btn-hero-primary" onclick="scrollToForm()">
                    <i class="fas fa-rocket"></i>
                    Começar Agora
                </button>
                <a href="#benefits" class="btn-hero-secondary">
                    <i class="fas fa-info-circle"></i>
                    Saiba Mais
                </a>
            </div>
            
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="value"><span>90</span>%</div>
                    <div class="label">Receita para você</div>
                </div>
                <div class="hero-stat">
                    <div class="value">R$<span>0</span></div>
                    <div class="label">Taxa de publicação</div>
                </div>
                <div class="hero-stat">
                    <div class="value"><span>7</span> dias</div>
                    <div class="label">Para receber</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
         BENEFITS SECTION
         ============================================ -->
    <section class="benefits-section" id="benefits">
        <div class="container">
            <div class="section-header">
                <h2>Por que publicar na Lexxos?</h2>
                <p>Tudo o que você precisa para ter sucesso como desenvolvedor indie</p>
            </div>
            
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <h3>Maior Lucro do Mercado</h3>
                    <p>
                        Fique com 90% da receita de cada venda. 
                        Uma das melhores taxas para desenvolvedores indie no Brasil.
                    </p>
                    <span class="benefit-highlight">
                        <i class="fas fa-check-circle"></i>
                        Apenas 10% de comissão
                    </span>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3>Publicação Rápida</h3>
                    <p>
                        Sem burocracia excessiva. Faça upload, preencha os dados 
                        e seu jogo estará disponível em até 24 horas.
                    </p>
                    <span class="benefit-highlight">
                        <i class="fas fa-check-circle"></i>
                        Aprovação em 24h
                    </span>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Dashboard Completo</h3>
                    <p>
                        Acompanhe vendas em tempo real, gerencie chaves de ativação, 
                        responda avaliações e analise métricas detalhadas.
                    </p>
                    <span class="benefit-highlight">
                        <i class="fas fa-check-circle"></i>
                        Analytics avançado
                    </span>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h3>Pagamentos Flexíveis</h3>
                    <p>
                        Saque via PIX ou transferência bancária. 
                        Receba seus ganhos toda semana, sem valor mínimo.
                    </p>
                    <span class="benefit-highlight">
                        <i class="fas fa-check-circle"></i>
                        PIX instantâneo
                    </span>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Comunidade Ativa</h3>
                    <p>
                        Interaja diretamente com jogadores, receba feedback 
                        e construa uma base de fãs para seus jogos.
                    </p>
                    <span class="benefit-highlight">
                        <i class="fas fa-check-circle"></i>
                        Suporte dedicado
                    </span>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Proteção Total</h3>
                    <p>
                        Sistema de chaves únicas, proteção contra fraudes 
                        e suporte jurídico para seus direitos autorais.
                    </p>
                    <span class="benefit-highlight">
                        <i class="fas fa-check-circle"></i>
                        100% seguro
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
         HOW IT WORKS
         ============================================ -->
    <section class="steps-section">
        <div class="container">
            <div class="section-header">
                <h2>Como funciona?</h2>
                <p>Em poucos passos você começa a vender</p>
            </div>
            
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">
                        <span>1</span>
                    </div>
                    <h4>Cadastre-se</h4>
                    <p>Preencha o formulário com seus dados e informações do estúdio</p>
                </div>
                
                <div class="step-card">
                    <div class="step-number">
                        <span>2</span>
                    </div>
                    <h4>Aguarde Aprovação</h4>
                    <p>Nossa equipe analisa seu perfil em até 24 horas</p>
                </div>
                
                <div class="step-card">
                    <div class="step-number">
                        <span>3</span>
                    </div>
                    <h4>Publique Jogos</h4>
                    <p>Faça upload dos arquivos e configure sua página de venda</p>
                </div>
                
                <div class="step-card">
                    <div class="step-number">
                        <span>4</span>
                    </div>
                    <h4>Receba!</h4>
                    <p>Acompanhe vendas e saque seus ganhos via PIX</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
         REGISTRATION FORM
         ============================================ -->
    <section class="registration-section" id="registration">
        <div class="container">
            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-gamepad"></i> Cadastro de Desenvolvedor</h2>
                        <p>Preencha os dados abaixo para criar seu perfil de desenvolvedor</p>
                    </div>
                    
                    <div class="form-body">
                        <?php if (!empty($errors['geral'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['geral'] ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Progress Steps -->
                        <div class="form-progress">
                            <div class="progress-step active" data-step="1">
                                <i class="fas fa-gamepad"></i>
                                <span>Estúdio</span>
                            </div>
                            <div class="progress-step" data-step="2">
                                <i class="fas fa-file-alt"></i>
                                <span>Dados Legais</span>
                            </div>
                            <div class="progress-step" data-step="3">
                                <i class="fas fa-wallet"></i>
                                <span>Pagamento</span>
                            </div>
                        </div>
                        
                        <form method="POST" action="" id="devForm">
                            <!-- Step 1: Studio Info -->
                            <div class="form-section active" data-step="1">
                                <div class="form-section-title">
                                    <i class="fas fa-gamepad"></i>
                                    Identidade do Estúdio
                                </div>
                                
                                <div class="info-box">
                                    <i class="fas fa-lightbulb"></i>
                                    <div class="info-box-content">
                                        <h4>Dica importante</h4>
                                        <p>Escolha um nome memorável para seu estúdio. Ele aparecerá em todas as páginas dos seus jogos e não poderá ser alterado facilmente.</p>
                                    </div>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label>
                                            Nome do Estúdio / Desenvolvedor
                                            <span class="required">*</span>
                                        </label>
                                        <input type="text" 
                                               name="nome_estudio" 
                                               class="form-control <?= isset($errors['nome_estudio']) ? 'error' : '' ?>" 
                                               placeholder="Ex: Meu Estúdio Indie"
                                               value="<?= htmlspecialchars($_POST['nome_estudio'] ?? '') ?>"
                                               required>
                                        <?php if (isset($errors['nome_estudio'])): ?>
                                            <span class="error-message">
                                                <i class="fas fa-exclamation-circle"></i>
                                                <?= $errors['nome_estudio'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="form-group full-width">
                                        <label>Descrição Curta</label>
                                        <input type="text" 
                                               name="descricao_curta" 
                                               class="form-control" 
                                               placeholder="Slogan ou breve resumo (aparece nos cards)"
                                               value="<?= htmlspecialchars($_POST['descricao_curta'] ?? '') ?>"
                                               maxlength="150">
                                        <span class="form-hint">Máximo 150 caracteres</span>
                                    </div>
                                    
                                    <div class="form-group full-width">
                                        <label>Website (Opcional)</label>
                                        <input type="url" 
                                               name="website" 
                                               class="form-control" 
                                               placeholder="https://seu-estudio.com"
                                               value="<?= htmlspecialchars($_POST['website'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Step 2: Legal Info -->
                            <div class="form-section" data-step="2">
                                <div class="form-section-title">
                                    <i class="fas fa-file-alt"></i>
                                    Dados Legais
                                </div>
                                
                                <div class="info-box warning">
                                    <i class="fas fa-shield-alt"></i>
                                    <div class="info-box-content">
                                        <h4>Seus dados estão seguros</h4>
                                        <p>Essas informações são obrigatórias para emissão de notas fiscais e cumprimento de obrigações legais. Nunca compartilhamos com terceiros.</p>
                                    </div>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Tipo de Pessoa</label>
                                        <select name="tipo_pessoa" class="form-control" id="tipoPessoa">
                                            <option value="fisica" <?= ($_POST['tipo_pessoa'] ?? '') == 'fisica' ? 'selected' : '' ?>>
                                                Pessoa Física (CPF)
                                            </option>
                                            <option value="juridica" <?= ($_POST['tipo_pessoa'] ?? '') == 'juridica' ? 'selected' : '' ?>>
                                                Pessoa Jurídica (CNPJ)
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <span id="docLabel">CPF</span>
                                            <span class="required">*</span>
                                        </label>
                                        <input type="text" 
                                               name="cpf_cnpj" 
                                               id="cpfCnpjInput"
                                               class="form-control <?= isset($errors['cpf_cnpj']) ? 'error' : '' ?>" 
                                               placeholder="000.000.000-00"
                                               value="<?= htmlspecialchars($_POST['cpf_cnpj'] ?? $user['cpf'] ?? '') ?>"
                                               required>
                                        <?php if (isset($errors['cpf_cnpj'])): ?>
                                            <span class="error-message">
                                                <i class="fas fa-exclamation-circle"></i>
                                                <?= $errors['cpf_cnpj'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Step 3: Payment Info -->
                            <div class="form-section" data-step="3">
                                <div class="form-section-title">
                                    <i class="fas fa-wallet"></i>
                                    Dados de Recebimento
                                </div>
                                
                                <div class="info-box">
                                    <i class="fas fa-info-circle"></i>
                                    <div class="info-box-content">
                                        <h4>Como você vai receber?</h4>
                                        <p>Preencha pelo menos uma forma de recebimento. Recomendamos o PIX por ser mais rápido. Você pode atualizar depois no painel.</p>
                                    </div>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label>
                                            <i class="fas fa-bolt" style="color: var(--warning);"></i>
                                            Chave PIX (Recomendado)
                                        </label>
                                        <input type="text" 
                                               name="chave_pix" 
                                               class="form-control" 
                                               placeholder="Email, CPF, Telefone ou Chave Aleatória"
                                               value="<?= htmlspecialchars($_POST['chave_pix'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Nome do Banco</label>
                                        <input type="text" 
                                               name="banco_nome" 
                                               class="form-control" 
                                               placeholder="Ex: Nubank, Itaú, Bradesco"
                                               value="<?= htmlspecialchars($_POST['banco_nome'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Tipo de Conta</label>
                                        <select name="banco_tipo" class="form-control">
                                            <option value="corrente" <?= ($_POST['banco_tipo'] ?? '') == 'corrente' ? 'selected' : '' ?>>
                                                Conta Corrente
                                            </option>
                                            <option value="poupanca" <?= ($_POST['banco_tipo'] ?? '') == 'poupanca' ? 'selected' : '' ?>>
                                                Conta Poupança
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Agência</label>
                                        <input type="text" 
                                               name="banco_agencia" 
                                               class="form-control" 
                                               placeholder="0000"
                                               value="<?= htmlspecialchars($_POST['banco_agencia'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Conta com Dígito</label>
                                        <input type="text" 
                                               name="banco_conta" 
                                               class="form-control" 
                                               placeholder="00000-0"
                                               value="<?= htmlspecialchars($_POST['banco_conta'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="checkbox-group" style="margin-top: 24px;">
                                    <input type="checkbox" id="termos" required>
                                    <label for="termos">
                                        Li e concordo com os <a href="<?= SITE_URL ?>/pages/termos-desenvolvedor.php" target="_blank">Termos de Distribuição</a> 
                                        e a <a href="<?= SITE_URL ?>/pages/politica-privacidade.php" target="_blank">Política de Privacidade</a> da Lexxos.
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button type="button" class="btn-back" id="btnBack" style="display: none;">
                                    <i class="fas fa-arrow-left"></i>
                                    Voltar
                                </button>
                                
                                <div style="margin-left: auto; display: flex; gap: 12px;">
                                    <button type="button" class="btn-next" id="btnNext">
                                        Próximo
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                    
                                    <button type="submit" class="btn-submit" id="btnSubmit" style="display: none;">
                                        <i class="fas fa-paper-plane"></i>
                                        Enviar Cadastro
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
// ============================================
// MULTI-STEP FORM LOGIC
// ============================================
let currentStep = 1;
const totalSteps = 3;

const sections = document.querySelectorAll('.form-section');
const progressSteps = document.querySelectorAll('.progress-step');
const btnBack = document.getElementById('btnBack');
const btnNext = document.getElementById('btnNext');
const btnSubmit = document.getElementById('btnSubmit');

function updateForm() {
    // Update sections
    sections.forEach(section => {
        section.classList.remove('active');
        if (parseInt(section.dataset.step) === currentStep) {
            section.classList.add('active');
        }
    });
    
    // Update progress
    progressSteps.forEach(step => {
        const stepNum = parseInt(step.dataset.step);
        step.classList.remove('active', 'completed');
        
        if (stepNum === currentStep) {
            step.classList.add('active');
        } else if (stepNum < currentStep) {
            step.classList.add('completed');
        }
    });
    
    // Update buttons
    btnBack.style.display = currentStep > 1 ? 'flex' : 'none';
    btnNext.style.display = currentStep < totalSteps ? 'flex' : 'none';
    btnSubmit.style.display = currentStep === totalSteps ? 'flex' : 'none';
}

function validateStep(step) {
    if (step === 1) {
        const nome = document.querySelector('input[name="nome_estudio"]');
        if (!nome.value.trim()) {
            nome.classList.add('error');
            nome.focus();
            return false;
        }
        nome.classList.remove('error');
    }
    
    if (step === 2) {
        const cpf = document.querySelector('input[name="cpf_cnpj"]');
        if (!cpf.value.trim()) {
            cpf.classList.add('error');
            cpf.focus();
            return false;
        }
        cpf.classList.remove('error');
    }
    
    return true;
}

btnNext.addEventListener('click', () => {
    if (validateStep(currentStep)) {
        currentStep++;
        updateForm();
        window.scrollTo({ top: document.getElementById('registration').offsetTop - 100, behavior: 'smooth' });
    }
});

btnBack.addEventListener('click', () => {
    currentStep--;
    updateForm();
});

// ============================================
// CPF/CNPJ MASK & TOGGLE
// ============================================
const tipoPessoa = document.getElementById('tipoPessoa');
const cpfCnpjInput = document.getElementById('cpfCnpjInput');
const docLabel = document.getElementById('docLabel');

tipoPessoa.addEventListener('change', function() {
    if (this.value === 'juridica') {
        docLabel.textContent = 'CNPJ';
        cpfCnpjInput.placeholder = '00.000.000/0000-00';
        cpfCnpjInput.maxLength = 18;
    } else {
        docLabel.textContent = 'CPF';
        cpfCnpjInput.placeholder = '000.000.000-00';
        cpfCnpjInput.maxLength = 14;
    }
    cpfCnpjInput.value = '';
});

cpfCnpjInput.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    
    if (tipoPessoa.value === 'juridica') {
        // CNPJ mask: 00.000.000/0000-00
        if (value.length > 14) value = value.slice(0, 14);
        if (value.length > 12) {
            value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
        } else if (value.length > 8) {
            value = value.replace(/(\d{2})(\d{3})(\d{3})(\d+)/, "$1.$2.$3/$4");
        } else if (value.length > 5) {
            value = value.replace(/(\d{2})(\d{3})(\d+)/, "$1.$2.$3");
        } else if (value.length > 2) {
            value = value.replace(/(\d{2})(\d+)/, "$1.$2");
        }
    } else {
        // CPF mask: 000.000.000-00
        if (value.length > 11) value = value.slice(0, 11);
        if (value.length > 9) {
            value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        } else if (value.length > 6) {
            value = value.replace(/(\d{3})(\d{3})(\d+)/, "$1.$2.$3");
        } else if (value.length > 3) {
            value = value.replace(/(\d{3})(\d+)/, "$1.$2");
        }
    }
    
    e.target.value = value;
});

// ============================================
// SCROLL TO FORM
// ============================================
function scrollToForm() {
    document.getElementById('registration').scrollIntoView({ behavior: 'smooth' });
}

// Show form directly if there are errors
<?php if (!empty($errors)): ?>
    // Find which step has errors
    <?php if (isset($errors['nome_estudio'])): ?>
        currentStep = 1;
    <?php elseif (isset($errors['cpf_cnpj'])): ?>
        currentStep = 2;
    <?php endif; ?>
    updateForm();
    setTimeout(() => scrollToForm(), 100);
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>