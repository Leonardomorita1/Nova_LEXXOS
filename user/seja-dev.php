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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Dados Básicos
    $nome_estudio = trim($_POST['nome_estudio']);
    $descricao_curta = trim($_POST['descricao_curta']);
    $tipo_pessoa = $_POST['tipo_pessoa'];
    $cpf_cnpj = trim($_POST['cpf_cnpj']);
    $website = trim($_POST['website']);
    
    // Dados Financeiros (Opcionais no cadastro mas recomendados)
    $chave_pix = trim($_POST['chave_pix']);
    $banco_nome = trim($_POST['banco_nome']);
    $banco_agencia = trim($_POST['banco_agencia']);
    $banco_conta = trim($_POST['banco_conta']);
    $banco_tipo = $_POST['banco_tipo'] ?? 'corrente';

    // Validação Básica
    if (empty($nome_estudio)) $errors['nome_estudio'] = true;
    if (empty($cpf_cnpj)) $errors['cpf_cnpj'] = true;

    if (empty($errors)) {
        try {
            // Gerar Slug
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nome_estudio)));
            
            // Query Completa
            $sql = "INSERT INTO desenvolvedor 
                    (usuario_id, nome_estudio, slug, descricao_curta, website, tipo_pessoa, cpf_cnpj, chave_pix, banco_nome, banco_agencia, banco_conta, banco_tipo, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $user_id, $nome_estudio, $slug, $descricao_curta, $website, 
                $tipo_pessoa, $cpf_cnpj, $chave_pix, 
                $banco_nome, $banco_agencia, $banco_conta, $banco_tipo
            ]);

            // Redireciona para página de sucesso ou dashboard (que vai mostrar status pendente)
            $_SESSION['success'] = "Solicitação enviada! Analisaremos seus dados em breve.";
            header('Location: ' . SITE_URL . '/developer/dashboard.php');
            exit;

        } catch (PDOException $e) {
            $errors['geral'] = "Erro ao cadastrar: " . $e->getMessage();
        }
    }
}

$page_title = "Torne-se um Desenvolvedor - Lexxos";
require_once '../includes/header.php';
?>

<style>
/* Estilos Específicos para Landing Page Dev */
.dev-hero {
    text-align: center;
    padding: 80px 20px;
    background: radial-gradient(circle at top, rgba(14, 165, 183, 0.15), transparent 70%);
}

.dev-hero h1 {
    font-size: 3rem;
    background: linear-gradient(to right, #fff, var(--accent));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 20px;
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin: 60px 0;
}

.benefit-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    padding: 30px;
    border-radius: 16px;
    transition: transform 0.3s;
}
.benefit-card:hover { transform: translateY(-5px); border-color: var(--accent); }
.benefit-icon { font-size: 2.5rem; color: var(--accent); margin-bottom: 20px; }

.registration-section {
    max-width: 900px;
    margin: 0 auto;
    background: var(--bg-secondary);
    border-radius: 20px;
    border: 1px solid var(--border);
    padding: 40px;
    display: none; /* Escondido inicialmente */
    animation: slideUp 0.5s ease;
}
@keyframes slideUp { from {opacity:0; transform:translateY(20px);} to {opacity:1; transform:translateY(0);} }

.cta-btn {
    padding: 15px 40px;
    font-size: 1.2rem;
    border-radius: 50px;
    background: var(--accent);
    color: #000;
    font-weight: 800;
    border: none;
    cursor: pointer;
    box-shadow: 0 0 20px rgba(var(--accent-rgb), 0.4);
    transition: all 0.3s;
}
.cta-btn:hover { transform: scale(1.05); box-shadow: 0 0 30px rgba(var(--accent-rgb), 0.6); }

/* Steps do Form */
.form-section-title {
    color: var(--accent);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 15px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 10px;
}
</style>

<div class="container">
    
    <div class="dev-hero">
        <h1>Publique seu jogo para o mundo.</h1>
        <p style="font-size: 1.2rem; color: var(--text-secondary); max-width: 600px; margin: 0 auto 40px;">
            Junte-se à Lexxos e alcance milhares de jogadores. Taxas justas, pagamentos rápidos e total controle sobre sua criação.
        </p>
        <button class="cta-btn" onclick="showForm()">Começar Agora <i class="fas fa-arrow-right"></i></button>
    </div>

    <div class="benefits-grid" id="benefits">
        <div class="benefit-card">
            <i class="fas fa-percentage benefit-icon"></i>
            <h3>Maior Lucro para Você</h3>
            <p style="color: var(--text-secondary);">Fique com 90% da receita das suas vendas. Uma das melhores taxas do mercado indie.</p>
        </div>
        <div class="benefit-card">
            <i class="fas fa-rocket benefit-icon"></i>
            <h3>Publicação Simplificada</h3>
            <p style="color: var(--text-secondary);">Sem burocracia excessiva. Suba seus arquivos, preencha os dados e comece a vender.</p>
        </div>
        <div class="benefit-card">
            <i class="fas fa-chart-line benefit-icon"></i>
            <h3>Dashboard Completo</h3>
            <p style="color: var(--text-secondary);">Acompanhe vendas em tempo real, gerencie chaves e responda à comunidade.</p>
        </div>
    </div>

    <div id="devFormSection" class="registration-section">
        <div class="text-center mb-5">
            <h2>Cadastro de Desenvolvedor</h2>
            <p style="color: var(--text-secondary);">Preencha os dados abaixo para criar seu estúdio.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger mb-4">Verifique os campos destacados em vermelho.</div>
        <?php endif; ?>

        <form method="POST" action="">
            
            <div class="form-section-title"><i class="fas fa-gamepad"></i> Identidade do Estúdio</div>
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <div class="form-group">
                    <label>Nome do Estúdio / Desenvolvedor *</label>
                    <input type="text" name="nome_estudio" class="form-control <?= isset($errors['nome_estudio']) ? 'error' : '' ?>" required placeholder="Ex: Epic Indie Games">
                </div>
                <div class="form-group">
                    <label>Website (Opcional)</label>
                    <input type="url" name="website" class="form-control" placeholder="https://seu-estudio.com">
                </div>
                <div class="form-group full-width" style="grid-column: 1 / -1;">
                    <label>Descrição Curta</label>
                    <input type="text" name="descricao_curta" class="form-control" placeholder="Slogan ou breve resumo (aparece nos cards)">
                </div>
            </div>

            <div class="form-section-title"><i class="fas fa-file-contract"></i> Dados Legais</div>
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 30px;">
                <div class="form-group">
                    <label>Tipo de Pessoa</label>
                    <select name="tipo_pessoa" class="form-control">
                        <option value="fisica">Pessoa Física (CPF)</option>
                        <option value="juridica">Pessoa Jurídica (CNPJ)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>CPF ou CNPJ *</label>
                    <input type="text" name="cpf_cnpj" class="form-control <?= isset($errors['cpf_cnpj']) ? 'error' : '' ?>" required placeholder="Somente números">
                </div>
            </div>

            <div class="form-section-title"><i class="fas fa-wallet"></i> Dados de Recebimento</div>
            <p style="font-size: 0.85rem; color: var(--warning); margin-bottom: 15px;">Estes dados são essenciais para processarmos seus saques.</p>
            
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Chave PIX (Principal)</label>
                    <input type="text" name="chave_pix" class="form-control" placeholder="Email, CPF ou Aleatória">
                </div>
                <div class="form-group">
                    <label>Nome do Banco</label>
                    <input type="text" name="banco_nome" class="form-control" placeholder="Ex: Nubank, Banco do Brasil">
                </div>
                <div class="form-group">
                    <label>Agência</label>
                    <input type="text" name="banco_agencia" class="form-control">
                </div>
                <div class="form-group">
                    <label>Conta com Dígito</label>
                    <input type="text" name="banco_conta" class="form-control">
                </div>
            </div>

            <div class="mt-4 pt-3 border-top border-secondary">
                <button type="submit" class="btn btn-primary btn-block btn-lg" style="width: 100%;">
                    Finalizar Cadastro e Enviar
                </button>
                <p class="text-center mt-2" style="font-size: 0.85rem; color: var(--text-secondary);">
                    Ao clicar, você concorda com os termos de distribuição da Lexxos.
                </p>
            </div>
        </form>
    </div>
</div>

<script>
    function showForm() {
        // Esconde benefícios
        document.getElementById('benefits').style.display = 'none';
        document.querySelector('.dev-hero p').style.display = 'none';
        document.querySelector('.cta-btn').style.display = 'none';
        
        // Mostra Form
        const form = document.getElementById('devFormSection');
        form.style.display = 'block';
        
        // Scroll suave
        form.scrollIntoView({behavior: 'smooth'});
    }

    // Se houve erro no envio, mostrar form automaticamente
    <?php if(!empty($errors) || $_SERVER['REQUEST_METHOD'] == 'POST'): ?>
        showForm();
    <?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>