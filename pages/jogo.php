<?php
// pages/jogo.php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();
$slug = $_GET['slug'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (empty($slug)) {
    header('Location: ' . SITE_URL);
    exit;
}

// Buscar dados do jogo
$stmt = $pdo->prepare("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug, d.logo_url, d.id as dev_id, d.verificado as dev_verificado
    FROM jogo j 
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
    WHERE j.slug = ? AND j.status = 'publicado'
");
$stmt->execute([$slug]);
$jogo = $stmt->fetch();

if (!$jogo) {
    header('Location: ' . SITE_URL);
    exit;
}

// Verificar arquivo disponível
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM arquivo_jogo WHERE jogo_id = ? AND ativo = 1");
$stmt->execute([$jogo['id']]);
$tem_arquivo = $stmt->fetch()['total'] > 0;

// Verificar idade do usuário
$usuario = null;
$idade_bloqueada = false;

if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM usuario WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();
    
    if ($usuario && $usuario['data_nascimento']) {
        $nascimento = new DateTime($usuario['data_nascimento']);
        $idade_usuario = $nascimento->diff(new DateTime())->y;
        $classificacao = $jogo['classificacao_indicativa'] ?? 0;
        
        if ($classificacao > 0 && $idade_usuario < $classificacao) {
            $idade_bloqueada = true;
        }
    }
}

// Página de restrição de idade
if ($idade_bloqueada) {
    $page_title = 'Conteúdo Restrito - ' . SITE_NAME;
    require_once '../includes/header.php';
?>
    <div class="age-restricted">
        <i class="fas fa-ban"></i>
        <h1>Conteúdo Restrito</h1>
        <p>Este jogo possui classificação indicativa de <strong style="color: var(--danger);"><?php echo $jogo['classificacao_indicativa']; ?>+</strong> anos.</p>
        <a href="<?php echo SITE_URL; ?>/pages/home.php" class="btn-back">
            <i class="fas fa-home"></i> Voltar para Home
        </a>
    </div>
    <style>
        /* Estilos ajustados para a página de idade restrita usando as novas variáveis */
        .age-restricted { text-align: center; padding: 100px 20px; max-width: 500px; margin: 0 auto; color: var(--text-primary); background: var(--bg-primary); }
        .age-restricted i { font-size: 80px; color: var(--danger); margin-bottom: 30px; }
        .age-restricted h1 { font-size: 28px; margin-bottom: 15px; }
        .age-restricted p { color: var(--text-secondary); margin-bottom: 30px; }
        .btn-back { 
            display: inline-flex; align-items: center; gap: 8px; 
            background: var(--accent); color: var(--text-primary); 
            padding: 12px 24px; border-radius: 8px; text-decoration: none;
            transition: background 0.2s ease;
        }
        .btn-back:hover { background: var(--accent-hover); }
    </style>
<?php
    require_once '../includes/footer.php';
    exit;
}

// Status do usuário
$in_library = $in_cart = $in_wishlist = false;
if ($user_id) {
    $in_library = isInLibrary($user_id, $jogo['id'], $pdo);
    $in_cart = isInCart($user_id, $jogo['id'], $pdo);
    $in_wishlist = isInWishlist($user_id, $jogo['id'], $pdo);
}

// Incrementar visualizações
$pdo->prepare("UPDATE jogo SET total_visualizacoes = total_visualizacoes + 1 WHERE id = ?")->execute([$jogo['id']]);

// Buscar categorias
$stmt = $pdo->prepare("
    SELECT c.nome, c.slug, c.icone 
    FROM categoria c 
    INNER JOIN jogo_categoria jc ON c.id = jc.categoria_id 
    WHERE jc.jogo_id = ?
");
$stmt->execute([$jogo['id']]);
$categorias = $stmt->fetchAll();

// Buscar tags
$stmt = $pdo->prepare("SELECT t.* FROM tag t JOIN jogo_tag jt ON t.id = jt.tag_id WHERE jt.jogo_id = ?");
$stmt->execute([$jogo['id']]);
$tags = $stmt->fetchAll();

// Buscar plataformas
$stmt = $pdo->prepare("SELECT p.* FROM plataforma p JOIN jogo_plataforma jp ON p.id = jp.plataforma_id WHERE jp.jogo_id = ?");
$stmt->execute([$jogo['id']]);
$plataformas = $stmt->fetchAll();

// Buscar avaliações
$stmt = $pdo->prepare("
    SELECT a.*, u.nome_usuario, u.avatar_url 
    FROM avaliacao a 
    LEFT JOIN usuario u ON a.usuario_id = u.id 
    WHERE a.jogo_id = ? 
    ORDER BY a.criado_em DESC
");
$stmt->execute([$jogo['id']]);
$avaliacoes = $stmt->fetchAll();

// Distribuição de notas
$rating_dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($avaliacoes as $av) {
    if (isset($rating_dist[$av['nota']])) $rating_dist[$av['nota']]++;
}
$total_reviews = count($avaliacoes);

// Avaliação do usuário
$my_review = null;
if ($user_id) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.nome_usuario, u.avatar_url 
        FROM avaliacao a 
        LEFT JOIN usuario u ON a.usuario_id = u.id 
        WHERE a.jogo_id = ? AND a.usuario_id = ?
    ");
    $stmt->execute([$jogo['id'], $user_id]);
    $my_review = $stmt->fetch();
}

// Calcular preço
$preco_final = ($jogo['em_promocao'] && $jogo['preco_promocional_centavos']) 
    ? $jogo['preco_promocional_centavos'] 
    : $jogo['preco_centavos'];
$desconto = calculateDiscount($jogo['preco_centavos'], $jogo['preco_promocional_centavos'], $jogo['em_promocao']);

// Buscar mídia
$stmt = $pdo->prepare("SELECT * FROM jogo_imagens WHERE jogo_id = ? ORDER BY ordem");
$stmt->execute([$jogo['id']]);
$imagens = $stmt->fetchAll();

// Info do arquivo
$arquivo_info = null;
if ($tem_arquivo) {
    $stmt = $pdo->prepare("SELECT * FROM arquivo_jogo WHERE jogo_id = ? AND ativo = 1 ORDER BY criado_em DESC LIMIT 1");
    $stmt->execute([$jogo['id']]);
    $arquivo_info = $stmt->fetch();
}

$page_title = $jogo['titulo'] . ' - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
/* NOVAS VARIÁVEIS DE TEMA */
:root {
    --bg-primary: #131314;
    --bg-secondary: #1E1F20;
    --accent: #0ea5b7;
    --accent-hover: #00e5ffcc;
    --text-primary: #E3E3E3;
    --text-secondary: #7e7e7e;
    --border: #2A2B2C;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
}

* { box-sizing: border-box; }

.game-page {
    background: var(--bg-primary);
    min-height: 100vh;
    color: var(--text-primary);
}

/* Hero Section */
.game-hero {
    position: relative;
    padding: 40px 0 60px;
    background: linear-gradient(180deg, rgba(14, 165, 183, 0.15) 0%, var(--bg-primary) 100%); /* Usando --accent com opacidade */
}

.game-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url('<?php echo SITE_URL . ($jogo['imagem_banner'] ?: $jogo['imagem_capa']); ?>') center/cover;
    opacity: 0.08;
    filter: blur(40px);
    z-index: -1; /* Garante que o conteúdo fique por cima */
}

.hero-container {
    max-width: 1400px; /* Largura máxima aumentada */
    margin: 0 auto;
    padding: 0 clamp(20px, 4vw, 40px); /* Padding fluido */
    position: relative;
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 40px;
}

/* Cover */
.game-cover {
    position: relative;
}

.game-cover img {
    width: 100%;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    aspect-ratio: 3/4;
    object-fit: cover;
}

.cover-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: var(--success); /* Usando --success */
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
}

.cover-badge.discount { background: var(--success); }
.cover-badge.owned { background: var(--accent); } /* Usando --accent */

/* Info */
.game-info h1 {
    font-size: clamp(1.8rem, 4vw, 2.5rem);
    font-weight: 700;
    margin-bottom: 12px;
    line-height: 1.2;
}

.game-dev {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.05);
    padding: 8px 16px;
    border-radius: 50px;
    text-decoration: none;
    color: var(--text-primary);
    margin-bottom: 20px;
    transition: background 0.2s;
}

.game-dev:hover { background: rgba(255,255,255,0.08); }
.game-dev img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover;}
.game-dev .verified { color: var(--accent); margin-left: 4px; }

.game-desc {
    color: var(--text-secondary);
    font-size: 15px;
    line-height: 1.6;
    margin-bottom: 20px;
}

.game-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
    font-size: 14px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--text-secondary);
}

.meta-item i { color: var(--accent); }
.meta-item strong { color: var(--text-primary); }

.game-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tag {
    background: rgba(255,255,255,0.06);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.2s;
}

.tag:hover { background: var(--accent); color: white; }

/* Purchase Box */
.purchase-box {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    margin-top: 30px;
}

.price-display {
    text-align: center;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.price-main {
    font-size: 2rem;
    font-weight: 700;
}

.price-main.free { color: var(--success); }

.price-old {
    color: var(--text-secondary);
    text-decoration: line-through;
    font-size: 14px;
    margin-top: 4px;
}

.discount-tag {
    display: inline-block;
    background: var(--success);
    color: white;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 700;
    margin-left: 10px;
}

.purchase-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-action {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 20px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-primary-ps {
    background: var(--accent);
    color: var(--text-primary);
}

.btn-primary-ps:hover {
    background: var(--accent-hover);
    transform: translateY(-2px);
}

.btn-success-ps {
    background: var(--success);
    color: var(--text-primary);
}
.btn-success-ps:hover {
    filter: brightness(1.1); /* Um leve brilho para hover */
    transform: translateY(-2px);
}

.btn-outline-ps {
    background: transparent;
    border: 2px solid var(--border);
    color: var(--text-primary);
}

.btn-outline-ps:hover { border-color: var(--accent); color: var(--accent); transform: translateY(-2px);}
.btn-outline-ps.active { border-color: var(--danger); color: var(--danger); }

.platforms-row {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
    font-size: 1.5rem;
    color: var(--text-secondary);
}

.platforms-row i:hover { color: var(--text-primary); }

.unavailable-box {
    text-align: center;
    padding: 30px;
    background: rgba(220,53,69,0.1); /* Usando --danger com opacidade */
    border: 1px dashed var(--danger);
    border-radius: 12px;
    margin-bottom: 16px;
}

.unavailable-box i { font-size: 40px; color: var(--warning); margin-bottom: 12px; } /* Usando --warning */
.unavailable-box h4 { color: var(--warning); margin-bottom: 8px; } /* Usando --warning */
.unavailable-box p { color: var(--text-secondary); font-size: 14px; margin: 0; }

/* Main Content */
.main-content {
    max-width: 1400px; /* Largura máxima aumentada */
    margin: 0 auto;
    padding: 40px clamp(20px, 4vw, 40px); /* Padding fluido */
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 40px;
}

.section-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i { color: var(--accent); }

/* Media Gallery */
.media-gallery {
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 24px;
}

.media-main {
    aspect-ratio: 16/9;
    background: #000;
    position: relative;
}

.media-main img,
.media-main iframe {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border: none;
}

.media-thumbs {
    display: flex;
    gap: 8px;
    margin-top: 8px;
    overflow-x: auto;
    padding-bottom: 8px;
}

.media-thumb {
    flex-shrink: 0;
    width: 120px;
    height: 68px;
    border-radius: 6px;
    overflow: hidden;
    cursor: pointer;
    opacity: 0.6;
    transition: all 0.2s;
    border: 2px solid transparent;
}

.media-thumb:hover,
.media-thumb.active {
    opacity: 1;
    border-color: var(--accent);
}

.media-thumb img { width: 100%; height: 100%; object-fit: cover; }

/* Description */
.game-description {
    color: var(--text-secondary);
    line-height: 1.8;
    font-size: 15px;
}

/* Reviews */
.rating-summary {
    display: flex;
    gap: 30px;
    align-items: center;
    padding: 20px;
    background: rgba(0,0,0,0.2); /* Fundo escuro sutil */
    border-radius: 12px;
    margin-bottom: 24px;
}

.rating-score {
    text-align: center;
    min-width: 100px;
}

.rating-score .big { font-size: 3rem; font-weight: 700; color: var(--accent); }
.rating-score .stars { color: var(--accent); margin: 8px 0; }
.rating-score .count { font-size: 13px; color: var(--text-secondary); }

.rating-bars { flex: 1; }

.bar-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    font-size: 12px;
}

.bar-row span:first-child { width: 20px; color: var(--text-secondary); }
.bar-track { flex: 1; height: 8px; background: rgba(255,255,255,0.05); border-radius: 4px; overflow: hidden; }
.bar-fill { height: 100%; background: var(--accent); border-radius: 4px; }
.bar-row span:last-child { width: 30px; text-align: right; color: var(--text-secondary); }

.btn-write-review {
    width: 100%;
    padding: 16px;
    background: var(--accent);
    color: var(--text-primary);
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 24px;
    transition: background 0.2s;
}

.btn-write-review:hover { background: var(--accent-hover); }

.my-review {
    background: rgba(14, 165, 183, 0.1); /* Usando --accent com opacidade */
    border: 2px solid var(--accent);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    position: relative;
}

.my-review-badge {
    position: absolute;
    top: -10px;
    left: 16px;
    background: var(--accent);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.review-card {
    padding: 16px;
    background: rgba(0,0,0,0.2); /* Fundo escuro sutil */
    border-radius: 10px;
    margin-bottom: 12px;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.review-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.review-user img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.review-user-info h4 { font-size: 14px; margin-bottom: 4px; }
.review-stars { color: var(--accent); font-size: 12px; }
.review-date { color: var(--text-secondary); font-size: 11px; margin-left: 8px; }
.review-content { color: var(--text-secondary); font-size: 14px; line-height: 1.6; }

.review-actions {
    display: flex;
    gap: 8px;
}

.review-actions button {
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-primary);
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.review-actions button:hover { border-color: var(--accent); color: var(--accent); }
.review-actions button.delete:hover { border-color: var(--danger); color: var(--danger); }

.no-reviews {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.no-reviews i { font-size: 40px; margin-bottom: 12px; opacity: 0.4; }

/* Sidebar */
.content-sidebar {
    position: sticky; /* Mantém a sidebar visível ao scrollar */
    top: 80px; /* Ajuste conforme a altura do seu header */
    align-self: flex-start; /* Impede que a sticky sidebar se estique */
}

.sidebar-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
}

.sidebar-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sidebar-title i { color: var(--accent); }

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

.info-row:last-child { border-bottom: none; }
.info-row span:first-child { color: var(--text-secondary); }
.info-row span:last-child { font-weight: 500; }

.requirements-section { margin-bottom: 16px; }
.requirements-section:last-child { margin-bottom: 0; }
.req-label { font-size: 13px; font-weight: 600; margin-bottom: 8px; }
.req-content {
    font-size: 12px;
    color: var(--text-secondary);
    line-height: 1.6;
    background: rgba(0,0,0,0.2); /* Fundo escuro sutil */
    padding: 12px;
    border-radius: 8px;
    white-space: pre-wrap;
}

.dev-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(0,0,0,0.2); /* Fundo escuro sutil */
    border-radius: 10px;
    text-decoration: none;
    color: var(--text-primary);
    transition: background 0.2s;
}

.dev-card:hover { background: rgba(0,0,0,0.3); }
.dev-card img { width: 44px; height: 44px; border-radius: 8px; object-fit: cover;}
.dev-card .name { font-weight: 600; }
.dev-card .verified { color: var(--accent); font-size: 12px; margin-left: 4px; }
.dev-card .label { font-size: 12px; color: var(--text-secondary); }

/* Review Overlay (Container Flutuante) */
.review-overlay {
    position: fixed;
    inset: 0; /* Ocupa toda a tela */
    background: rgba(0,0,0,0.85); /* Fundo escuro semi-transparente */
    display: none; /* Escondido por padrão */
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
    backdrop-filter: blur(4px); /* Efeito de blur no que está por trás */
}

.review-overlay.active { display: flex; } /* Ativa o overlay */

.review-panel {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
    width: 100%;
    max-width: 500px; /* Largura máxima para o painel */
    animation: slideUp 0.3s ease forwards; /* Animação ao aparecer */
    box-shadow: 0 10px 30px rgba(0,0,0,0.5); /* Sombra para destacar */
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.review-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
}

.review-panel-header h3 { font-size: 18px; font-weight: 600; }

.btn-close-panel {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: rgba(255,255,255,0.05);
    color: var(--text-secondary);
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-close-panel:hover { background: rgba(255,255,255,0.1); color: var(--text-primary); }

.review-panel-body { padding: 24px; }

.star-rating {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 24px;
}

.star-rating i {
    font-size: 36px;
    color: var(--border); /* Cor das estrelas "vazias" */
    cursor: pointer;
    transition: all 0.2s;
}

.star-rating i:hover,
.star-rating i.active { color: var(--accent); transform: scale(1.1); }

.review-textarea {
    width: 100%;
    min-height: 150px;
    background: rgba(0,0,0,0.3); /* Fundo escuro para a textarea */
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    color: var(--text-primary);
    font-size: 14px;
    resize: vertical;
    font-family: inherit; /* Garante a fonte padrão do tema */
}

.review-textarea:focus { outline: none; border-color: var(--accent); }
.review-textarea::placeholder { color: var(--text-secondary); }

.review-panel-footer {
    display: flex;
    gap: 12px;
    padding: 20px 24px;
    border-top: 1px solid var(--border);
}

.review-panel-footer button {
    flex: 1;
    padding: 14px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-primary);
}

.btn-cancel:hover { border-color: var(--text-secondary); }

.btn-submit {
    background: var(--accent);
    border: none;
    color: var(--text-primary);
}

.btn-submit:hover { background: var(--accent-hover); }

/* Toast */
.toast-notification {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    padding: 14px 24px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    z-index: 10000;
    opacity: 0;
    transition: all 0.3s ease;
}

.toast-notification.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}

.toast-notification.success { border-color: var(--success); }
.toast-notification.success i { color: var(--success); }
.toast-notification.error { border-color: var(--danger); }
.toast-notification.error i { color: var(--danger); }

/* Responsive Adjustments */
@media (max-width: 1024px) {
    .main-content { 
        grid-template-columns: 1fr; /* Sidebar move below main content */
        gap: 30px;
    }
    .content-sidebar {
        position: static; /* Remove sticky behavior */
        top: auto;
        order: 1; /* Move sidebar below main content */
    }
    .content-main {
        order: 0;
    }
}

@media (max-width: 768px) {
    .hero-container {
        grid-template-columns: 1fr; /* Image and info stack vertically */
        text-align: center;
        gap: 30px;
        padding: 0 clamp(15px, 4vw, 20px); /* Ajustar padding */
    }
    
    .game-cover { max-width: 250px; margin: 0 auto; } /* Centraliza a capa e limita largura */
    .game-dev { justify-content: center; } /* Centraliza badge do desenvolvedor */
    .game-meta { justify-content: center; }
    .game-tags { justify-content: center; }
    .rating-summary { flex-direction: column; text-align: center; gap: 20px;}
    .rating-bars { width: 100%; }
    .purchase-box { margin-top: 20px; } /* Ajuste de margem */
    .main-content { padding: 30px clamp(15px, 4vw, 20px); }
    .section-card, .sidebar-card { padding: 20px; }
    .review-panel { border-radius: 16px; } /* Arredondar menos em telas menores */
}

@media (max-width: 480px) {
    .hero-container, .main-content {
        padding: 20px clamp(10px, 4vw, 15px); /* Padding ainda menor para telas muito pequenas */
    }
    .game-info h1 { font-size: clamp(1.6rem, 7vw, 2.2rem); } /* Reduzir ainda mais o título */
    .game-cover { max-width: 200px; margin-bottom: 20px; }
    .purchase-box { padding: 18px; }
    .btn-action { padding: 12px 15px; font-size: 14px; }
    .price-main { font-size: 1.8rem; }
    .review-panel { border-radius: 12px; } /* Reduzir arredondamento */
}
</style>

<div class="game-page">
    <!-- Hero Section -->
    <div class="game-hero">
        <div class="hero-container">
            <!-- Cover -->
            <div class="game-cover">
                <img src="<?php echo SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                     alt="<?php echo sanitize($jogo['titulo']); ?>">
                <?php if ($in_library): ?>
                    <span class="cover-badge owned"><i class="fas fa-check"></i> Na Biblioteca</span>
                <?php elseif ($desconto > 0): ?>
                    <span class="cover-badge discount">-<?php echo $desconto; ?>%</span>
                <?php endif; ?>
            </div>

            <!-- Game Info -->
            <div class="game-info">
                <h1><?php echo sanitize($jogo['titulo']); ?></h1>
                
                <a href="<?php echo SITE_URL; ?>/pages/desenvolvedor.php?slug=<?php echo $jogo['dev_slug']; ?>" class="game-dev">
                    <img src="<?php echo SITE_URL . ($jogo['logo_url'] ?: '/assets/images/default-dev.png'); ?>" alt="">
                    <span><?php echo sanitize($jogo['nome_estudio']); ?></span>
                    <?php if ($jogo['dev_verificado']): ?>
                        <i class="fas fa-check-circle verified"></i>
                    <?php endif; ?>
                </a>

                <p class="game-desc"><?php echo sanitize($jogo['descricao_curta']); ?></p>

                <div class="game-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <strong><?php echo date('d/m/Y', strtotime($jogo['data_lancamento'] ?? $jogo['criado_em'])); ?></strong>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-download"></i>
                        <strong><?php echo number_format($jogo['total_vendas'], 0, ',', '.'); ?></strong> vendas
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-star"></i>
                        <strong><?php echo number_format($jogo['nota_media'], 1); ?>/5</strong>
                    </div>
                    <?php if ($jogo['classificacao_indicativa'] > 0): ?>
                        <div class="meta-item">
                            <i class="fas fa-user-shield"></i>
                            <strong><?php echo $jogo['classificacao_indicativa']; ?>+</strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="game-tags">
                    <?php foreach ($categorias as $cat): ?>
                        <a href="<?php echo SITE_URL; ?>/pages/categoria.php?slug=<?php echo $cat['slug']; ?>" class="tag">
                            <i class="fas fa-<?php echo htmlspecialchars($cat['icone']); ?>"></i>
                            <?php echo htmlspecialchars($cat['nome']); ?>
                        </a>
                    <?php endforeach; ?>
                    <?php foreach ($tags as $t): ?>
                        <span class="tag"><?php echo sanitize($t['nome']); ?></span>
                    <?php endforeach; ?>
                </div>

                <!-- Purchase Box -->
                <div class="purchase-box">
                    <?php if (!$tem_arquivo): ?>
                        <div class="unavailable-box">
                            <i class="fas fa-clock"></i>
                            <h4>Em Breve</h4>
                            <p>Este jogo ainda não está disponível para download.</p>
                        </div>
                        <?php if ($user_id): ?>
                            <button class="btn-action btn-outline-ps <?php echo $in_wishlist ? 'active' : ''; ?>" 
                                    onclick="toggleWishlist(<?php echo $jogo['id']; ?>, this)">
                                <i class="fas fa-heart"></i>
                                <span><?php echo $in_wishlist ? 'Na Lista de Desejos' : 'Adicionar à Lista'; ?></span>
                            </button>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn-action btn-primary-ps">
                                <i class="fas fa-sign-in-alt"></i> Entre para Acompanhar
                            </a>
                        <?php endif; ?>

                    <?php elseif ($in_library): ?>
                        <div class="price-display">
                            <div style="color: var(--success); font-weight: 600;">
                                <i class="fas fa-check-circle"></i> Você possui este jogo
                            </div>
                        </div>
                        <div class="purchase-actions">
                            <a href="<?php echo SITE_URL; ?>/user/biblioteca.php" class="btn-action btn-success-ps">
                                <i class="fas fa-gamepad"></i> Ir para Biblioteca
                            </a>
                            <a href="<?php echo SITE_URL; ?>/user/download-jogo.php?jogo_id=<?php echo $jogo['id']; ?>" class="btn-action btn-outline-ps">
                                <i class="fas fa-download"></i> Baixar Jogo
                            </a>
                        </div>

                    <?php else: ?>
                        <div class="price-display">
                            <?php if ($preco_final == 0): ?>
                                <div class="price-main free">GRÁTIS</div>
                            <?php else: ?>
                                <div class="price-main">
                                    <?php echo formatPrice($preco_final); ?>
                                    <?php if ($desconto > 0): ?>
                                        <span class="discount-tag">-<?php echo $desconto; ?>%</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($desconto > 0): ?>
                                    <div class="price-old"><?php echo formatPrice($jogo['preco_centavos']); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="purchase-actions">
                            <?php if ($user_id): ?>
                                <button class="btn-action <?php echo $in_cart ? 'btn-success-ps' : 'btn-primary-ps'; ?>" 
                                        onclick="toggleCart(<?php echo $jogo['id']; ?>, this)">
                                    <i class="fas <?php echo $in_cart ? 'fa-check' : 'fa-cart-plus'; ?>"></i>
                                    <span><?php echo $in_cart ? 'No Carrinho' : 'Adicionar ao Carrinho'; ?></span>
                                </button>
                                <?php if ($in_cart): ?>
                                    <a href="<?php echo SITE_URL; ?>/user/carrinho.php" class="btn-action btn-outline-ps">
                                        <i class="fas fa-shopping-bag"></i> Finalizar Compra
                                    </a>
                                <?php endif; ?>
                                <button class="btn-action btn-outline-ps <?php echo $in_wishlist ? 'active' : ''; ?>" 
                                        onclick="toggleWishlist(<?php echo $jogo['id']; ?>, this)">
                                    <i class="fas fa-heart"></i>
                                    <span><?php echo $in_wishlist ? 'Na Lista de Desejos' : 'Lista de Desejos'; ?></span>
                                </button>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn-action btn-primary-ps">
                                    <i class="fas fa-sign-in-alt"></i> Entre para Comprar
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($plataformas)): ?>
                        <div class="platforms-row">
                            <?php foreach ($plataformas as $p): ?>
                                <i class="<?php echo $p['icone']; ?>" title="<?php echo $p['nome']; ?>"></i>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-main">
            <!-- Media Gallery -->
            <?php if ($jogo['video_trailer'] || count($imagens) > 0): ?>
                <div class="media-gallery">
                    <div class="media-main" id="mediaMain">
                        <?php if ($jogo['video_trailer']): ?>
                            <iframe src="<?php echo $jogo['video_trailer']; ?>" allowfullscreen></iframe>
                        <?php elseif (count($imagens) > 0): ?>
                            <img src="<?php echo SITE_URL . $imagens[0]['imagem']; ?>" alt="Screenshot">
                        <?php endif; ?>
                    </div>
                    <?php if (($jogo['video_trailer'] ? 1 : 0) + count($imagens) > 1): ?>
                        <div class="media-thumbs">
                            <?php if ($jogo['video_trailer']): ?>
                                <div class="media-thumb <?php echo (count($imagens) === 0) ? 'active' : ''; ?>" data-type="video" data-src="<?php echo $jogo['video_trailer']; ?>">
                                    <img src="https://img.youtube.com/vi/<?php echo getYoutubeId($jogo['video_trailer']); ?>/mqdefault.jpg" alt="Video">
                                </div>
                            <?php endif; ?>
                            <?php foreach ($imagens as $i => $img): ?>
                                <div class="media-thumb <?php echo (!$jogo['video_trailer'] && $i === 0) ? 'active' : ''; ?>" 
                                     data-type="image" data-src="<?php echo SITE_URL . $img['imagem']; ?>">
                                    <img src="<?php echo SITE_URL . $img['imagem']; ?>" alt="Screenshot">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Description -->
            <div class="section-card">
                <h2 class="section-title"><i class="fas fa-align-left"></i> Sobre o Jogo</h2>
                <div class="game-description">
                    <?php echo nl2br(sanitize($jogo['descricao_completa'])); ?>
                </div>
            </div>

            <!-- Reviews -->
            <div class="section-card" id="reviews">
                <h2 class="section-title"><i class="fas fa-star"></i> Avaliações</h2>

                <div class="rating-summary">
                    <div class="rating-score">
                        <div class="big"><?php echo number_format($jogo['nota_media'], 1); ?></div>
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?php echo $i <= round($jogo['nota_media']) ? 'fas' : 'far'; ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="count"><?php echo $total_reviews; ?> avaliações</div>
                    </div>
                    <div class="rating-bars">
                        <?php for ($i = 5; $i >= 1; $i--):
                            $pct = $total_reviews > 0 ? ($rating_dist[$i] / $total_reviews) * 100 : 0;
                        ?>
                            <div class="bar-row">
                                <span><?php echo $i; ?></span>
                                <div class="bar-track"><div class="bar-fill" style="width: <?php echo $pct; ?>%"></div></div>
                                <span><?php echo $rating_dist[$i]; ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <?php if ($in_library): ?>
                    <?php if ($my_review): ?>
                        <div class="my-review">
                            <span class="my-review-badge">Sua Avaliação</span>
                            <div class="review-header">
                                <div class="review-user">
                                    <img src="<?php echo getAvatar($my_review['avatar_url']); ?>" alt="">
                                    <div class="review-user-info">
                                        <h4>Você</h4>
                                        <span class="review-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo $i <= $my_review['nota'] ? 'fas' : 'far'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </span>
                                        <span class="review-date"><?php echo date('d/m/Y', strtotime($my_review['criado_em'])); ?></span>
                                    </div>
                                </div>
                                <div class="review-actions">
                                    <button onclick="openReviewPanel(<?php echo $my_review['nota']; ?>, '<?php echo addslashes(htmlspecialchars($my_review['comentario'])); ?>')">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <form action="<?php echo SITE_URL; ?>/api/avaliar.php" method="POST" style="display:inline" 
                                          onsubmit="return confirm('Remover sua avaliação?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="jogo_id" value="<?php echo $jogo['id']; ?>">
                                        <button type="submit" class="delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <div class="review-content"><?php echo nl2br(htmlspecialchars($my_review['comentario'])); ?></div>
                        </div>
                    <?php else: ?>
                        <button class="btn-write-review" onclick="openReviewPanel()">
                            <i class="fas fa-pen"></i> Escrever Avaliação
                        </button>
                    <?php endif; ?>
                <?php elseif ($user_id): ?>
                    <div style="text-align: center; padding: 16px; background: rgba(0,0,0,0.2); border-radius: 10px; margin-bottom: 20px; color: var(--text-secondary); font-size: 14px;">
                        <i class="fas fa-info-circle"></i> Você precisa possuir este jogo para avaliar.
                    </div>
                <?php endif; ?>

                <?php 
                $has_other = false;
                foreach ($avaliacoes as $rev): 
                    if ($rev['usuario_id'] == $user_id) continue;
                    $has_other = true;
                ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="review-user">
                                <img src="<?php echo getAvatar($rev['avatar_url']); ?>" alt="">
                                <div class="review-user-info">
                                    <h4><?php echo sanitize($rev['nome_usuario']); ?></h4>
                                    <span class="review-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="<?php echo $i <= $rev['nota'] ? 'fas' : 'far'; ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </span>
                                    <span class="review-date"><?php echo date('d/m/Y', strtotime($rev['criado_em'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="review-content"><?php echo nl2br(sanitize($rev['comentario'])); ?></div>
                    </div>
                <?php endforeach; ?>

                <?php if (!$has_other && !$my_review): ?>
                    <div class="no-reviews">
                        <i class="fas fa-comment-slash"></i>
                        <p>Nenhuma avaliação ainda.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="content-sidebar">
            <?php if ($arquivo_info): ?>
                <div class="sidebar-card">
                    <h3 class="sidebar-title"><i class="fas fa-file-archive"></i> Arquivo</h3>
                    <div class="info-row"><span>Versão</span><span><?php echo sanitize($arquivo_info['versao']); ?></span></div>
                    <div class="info-row"><span>Tamanho</span><span><?php echo formatFileSize($arquivo_info['tamanho_bytes']); ?></span></div>
                    <div class="info-row"><span>Downloads</span><span><?php echo number_format($arquivo_info['downloads'], 0, ',', '.'); ?></span></div>
                </div>
            <?php endif; ?>

            <?php if ($jogo['requisitos_minimos'] || $jogo['requisitos_recomendados']): ?>
                <div class="sidebar-card">
                    <h3 class="sidebar-title"><i class="fas fa-microchip"></i> Requisitos</h3>
                    <?php if ($jogo['requisitos_minimos']): ?>
                        <div class="requirements-section">
                            <div class="req-label">Mínimos</div>
                            <div class="req-content"><?php echo sanitize($jogo['requisitos_minimos']); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($jogo['requisitos_recomendados']): ?>
                        <div class="requirements-section">
                            <div class="req-label">Recomendados</div>
                            <div class="req-content"><?php echo sanitize($jogo['requisitos_recomendados']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="sidebar-card">
                <h3 class="sidebar-title"><i class="fas fa-code"></i> Desenvolvedor</h3>
                <a href="<?php echo SITE_URL; ?>/pages/desenvolvedor.php?slug=<?php echo $jogo['dev_slug']; ?>" class="dev-card">
                    <img src="<?php echo SITE_URL . ($jogo['logo_url'] ?: '/assets/images/default-dev.png'); ?>" alt="">
                    <div>
                        <div class="name">
                            <?php echo sanitize($jogo['nome_estudio']); ?>
                            <?php if ($jogo['dev_verificado']): ?>
                                <i class="fas fa-check-circle verified"></i>
                            <?php endif; ?>
                        </div>
                        <div class="label">Ver perfil</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Review Panel Overlay (Container Flutuante) -->
<div class="review-overlay" id="reviewOverlay">
    <div class="review-panel">
        <div class="review-panel-header">
            <h3 id="reviewPanelTitle">Escrever Avaliação</h3>
            <button class="btn-close-panel" onclick="closeReviewPanel()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="<?php echo SITE_URL; ?>/api/avaliar.php" method="POST" id="reviewForm">
            <input type="hidden" name="action" id="reviewAction" value="add">
            <input type="hidden" name="jogo_id" value="<?php echo $jogo['id']; ?>">
            <input type="hidden" name="nota" id="reviewNota" required>
            
            <div class="review-panel-body">
                <div class="star-rating" id="starRating">
                    <i class="far fa-star" data-value="1"></i>
                    <i class="far fa-star" data-value="2"></i>
                    <i class="far fa-star" data-value="3"></i>
                    <i class="far fa-star" data-value="4"></i>
                    <i class="far fa-star" data-value="5"></i>
                </div>
                <textarea name="comentario" id="reviewText" class="review-textarea" 
                          placeholder="Conte sua experiência com o jogo..." required></textarea>
            </div>
            
            <div class="review-panel-footer">
                <button type="button" class="btn-cancel" onclick="closeReviewPanel()">Cancelar</button>
                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Publicar</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast -->
<div class="toast-notification" id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toastMessage"></span>
</div>

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';

// Media Gallery
document.querySelectorAll('.media-thumb').forEach(thumb => {
    thumb.addEventListener('click', function() {
        document.querySelectorAll('.media-thumb').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const main = document.getElementById('mediaMain');
        const type = this.dataset.type;
        const src = this.dataset.src;
        
        main.innerHTML = ''; // Limpa o conteúdo atual
        if (type === 'video') {
            main.innerHTML = `<iframe src="${src}" allowfullscreen></iframe>`;
        } else {
            main.innerHTML = `<img src="${src}" alt="Screenshot">`;
        }
    });
});

// Review Panel
function openReviewPanel(nota = 0, text = '') {
    // Se o usuário não estiver logado, redireciona para o login
    if (!<?php echo $user_id ? 'true' : 'false'; ?>) {
        showToast('Você precisa estar logado para avaliar.', 'error');
        window.location.href = `${SITE_URL}/auth/login.php`; // Redireciona para o login
        return;
    }

    document.getElementById('reviewOverlay').classList.add('active');
    document.getElementById('reviewAction').value = nota > 0 ? 'update' : 'add';
    document.getElementById('reviewPanelTitle').textContent = nota > 0 ? 'Editar Avaliação' : 'Escrever Avaliação';
    document.getElementById('reviewNota').value = nota;
    document.getElementById('reviewText').value = text;
    updateStars(nota);
    document.body.style.overflow = 'hidden'; // Evita scroll na página principal
}

function closeReviewPanel() {
    document.getElementById('reviewOverlay').classList.remove('active');
    document.body.style.overflow = ''; // Restaura scroll da página
}

// Star Rating
const stars = document.querySelectorAll('#starRating i');
const notaInput = document.getElementById('reviewNota');

stars.forEach(star => {
    star.addEventListener('click', function() {
        notaInput.value = this.dataset.value;
        updateStars(this.dataset.value);
    });
    
    star.addEventListener('mouseenter', function() {
        updateStars(this.dataset.value);
    });
});

document.getElementById('starRating').addEventListener('mouseleave', function() {
    updateStars(notaInput.value || 0); // Volta para a nota selecionada ou 0
});

function updateStars(value) {
    stars.forEach(star => {
        const v = parseInt(star.dataset.value);
        if (v <= parseInt(value)) {
            star.classList.remove('far');
            star.classList.add('fas', 'active');
        } else {
            star.classList.remove('fas', 'active');
            star.classList.add('far');
        }
    });
}

// Close on outside click
document.getElementById('reviewOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeReviewPanel();
});

// Close on ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('reviewOverlay').classList.contains('active')) closeReviewPanel();
});

// Toggle Cart
async function toggleCart(jogoId, btn) {
    // Se o usuário não estiver logado, redireciona para o login
    if (!<?php echo $user_id ? 'true' : 'false'; ?>) {
        showToast('Você precisa estar logado para adicionar ao carrinho.', 'error');
        window.location.href = `${SITE_URL}/auth/login.php`;
        return;
    }

    btn.disabled = true;
    try {
        const res = await fetch(`${SITE_URL}/api/toggle-cart.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ jogo_id: jogoId })
        });
        const data = await res.json();
        
        if (data.success) {
            const inCart = data.action === 'added';
            // Seleciona todos os botões de carrinho relacionados ao jogo
            document.querySelectorAll(`[onclick*="toggleCart(${jogoId}"]`).forEach(cartBtn => {
                const icon = cartBtn.querySelector('i');
                const span = cartBtn.querySelector('span');
                
                cartBtn.className = `btn-action ${inCart ? 'btn-success-ps' : 'btn-primary-ps'}`;
                if (icon) icon.className = `fas ${inCart ? 'fa-check' : 'fa-cart-plus'}`;
                if (span) span.textContent = inCart ? 'No Carrinho' : 'Adicionar ao Carrinho';
            });
            
            // Lida com o botão "Finalizar Compra" (se existir)
            const finalizeBtn = document.querySelector('.purchase-actions a[href*="carrinho.php"]');
            if (finalizeBtn) {
                finalizeBtn.style.display = inCart ? 'flex' : 'none';
            }
            
            showToast(data.message, 'success');
            updateCartCount();
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Erro ao atualizar carrinho', 'error');
    } finally {
        btn.disabled = false;
    }
}

// Toggle Wishlist
async function toggleWishlist(jogoId, btn) {
    // Se o usuário não estiver logado, redireciona para o login
    if (!<?php echo $user_id ? 'true' : 'false'; ?>) {
        showToast('Você precisa estar logado para adicionar à lista de desejos.', 'error');
        window.location.href = `${SITE_URL}/auth/login.php`;
        return;
    }

    btn.disabled = true;
    try {
        const res = await fetch(`${SITE_URL}/api/toggle-wishlist.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ jogo_id: jogoId })
        });
        const data = await res.json();
        
        if (data.success) {
            const inWishlist = data.action === 'added';
            // Seleciona todos os botões de wishlist relacionados ao jogo
            document.querySelectorAll(`[onclick*="toggleWishlist(${jogoId}"]`).forEach(wishBtn => {
                const span = wishBtn.querySelector('span');
                
                wishBtn.classList.toggle('active', inWishlist);
                if (span) span.textContent = inWishlist ? 'Na Lista de Desejos' : 'Lista de Desejos';
            });
            
            showToast(data.message, 'success');
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Erro ao atualizar lista', 'error');
    } finally {
        btn.disabled = false;
    }
}

// Toast
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const icon = toast.querySelector('i');
    
    // Reset classes
    toast.className = 'toast-notification';
    icon.className = 'fas';

    // Apply new type classes
    toast.classList.add(type);
    icon.classList.add(type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle');
    document.getElementById('toastMessage').textContent = message;
    
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// Update Cart Count
function updateCartCount() {
    fetch(`${SITE_URL}/api/get-cart-count.php`)
        .then(res => res.json())
        .then(data => {
            const badge = document.querySelector('.cart-count'); // Assumindo que você tem um elemento com a classe cart-count no seu header
            if (badge && data.count !== undefined) {
                badge.textContent = data.count;
                badge.style.display = data.count > 0 ? 'flex' : 'none';
            }
        })
        .catch(err => console.error('Erro ao atualizar contador do carrinho:', err));
}

// Inicializa o contador do carrinho ao carregar a página
document.addEventListener('DOMContentLoaded', updateCartCount);

// Helper: Get YouTube ID (para PHP, já estava lá, mas repetindo para JS)
function getYoutubeId(url) {
    const match = url.match(/(?:embed\/|v=)([^&?]+)/);
    return match ? match[1] : '';
}
</script>

<?php 
// Helper function for YouTube ID (PHP side, para usar na imagem thumbnail)
function getYoutubeId($url) {
    preg_match('/(?:embed\/|v=)([^&?]+)/', $url, $matches);
    return $matches[1] ?? '';
}

require_once '../includes/footer.php'; 
?>