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
<style>
:root{--bg-primary:#131314;--bg-secondary:#1E1F20;--accent:#0ea5b7;--accent-hover:#00e5ffcc;--text-primary:#E3E3E3;--text-secondary:#7e7e7e;--border:#2A2B2C;--success:#28a745;--warning:#ffc107;--danger:#dc3545}
.age-restricted{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;text-align:center;padding:40px 20px;color:var(--text-primary)}
.age-restricted i{font-size:64px;color:var(--danger);margin-bottom:24px}
.age-restricted h1{font-size:24px;margin-bottom:12px;font-weight:600}
.age-restricted p{color:var(--text-secondary);margin-bottom:32px;font-size:15px}
.btn-back{display:inline-flex;align-items:center;gap:8px;background:var(--accent);color:#fff;padding:12px 28px;border-radius:4px;text-decoration:none;font-weight:500;font-size:14px;transition:all .2s}
.btn-back:hover{background:var(--accent-hover)}
</style>
<div class="age-restricted">
    <i class="fas fa-ban"></i>
    <h1>Conteúdo Restrito</h1>
    <p>Este jogo possui classificação indicativa de <strong style="color:var(--danger)"><?= $jogo['classificacao_indicativa'] ?>+</strong> anos.</p>
    <a href="<?= SITE_URL ?>/pages/home.php" class="btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>
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
$stmt = $pdo->prepare("SELECT c.nome, c.slug, c.icone FROM categoria c INNER JOIN jogo_categoria jc ON c.id = jc.categoria_id WHERE jc.jogo_id = ?");
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
$stmt = $pdo->prepare("SELECT a.*, u.nome_usuario, u.avatar_url FROM avaliacao a LEFT JOIN usuario u ON a.usuario_id = u.id WHERE a.jogo_id = ? ORDER BY a.criado_em DESC");
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
    $stmt = $pdo->prepare("SELECT a.*, u.nome_usuario, u.avatar_url FROM avaliacao a LEFT JOIN usuario u ON a.usuario_id = u.id WHERE a.jogo_id = ? AND a.usuario_id = ?");
    $stmt->execute([$jogo['id'], $user_id]);
    $my_review = $stmt->fetch();
}

// Calcular preço
$preco_final = ($jogo['em_promocao'] && $jogo['preco_promocional_centavos']) ? $jogo['preco_promocional_centavos'] : $jogo['preco_centavos'];
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

// Helper function para YouTube ID
function getYoutubeId($url) {
    preg_match('/(?:embed\/|v=)([^&?]+)/', $url, $matches);
    return $matches[1] ?? '';
}

$page_title = $jogo['titulo'] . ' - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
:root {
    --bg-primary: #131314;
    --bg-secondary: #1E1F20;
    --bg-tertiary: #252627;
    --accent: #0ea5b7;
    --accent-hover: #00e5ffcc;
    --text-primary: #E3E3E3;
    --text-secondary: #7e7e7e;
    --border: #2A2B2C;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

/* Page Layout */
.game-page {
    background: var(--bg-primary);
    min-height: 100vh;
    color: var(--text-primary);
}

.store-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
}

/* Hero Banner - Desktop */
.game-hero {
    position: relative;
    height: 400px;
    overflow: hidden;
}

.hero-bg {
    position: absolute;
    inset: 0;
    background: url('<?= SITE_URL . ($jogo['imagem_banner'] ?: $jogo['imagem_capa']) ?>') center/cover no-repeat;
}

.hero-bg::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, var(--bg-primary) 0%, rgba(19,19,20,0.8) 50%, rgba(19,19,20,0.4) 100%);
}

/* Mobile Header - Estilo App */
.mobile-game-header {
    display: none;
}

/* Main Layout */
.game-layout {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 40px;
    margin-top: -120px;
    position: relative;
    z-index: 10;
    padding-bottom: 60px;
}

/* Left Column - Content */
.game-content {
    min-width: 0;
}

/* Media Section */
.media-section {
    background: var(--bg-secondary);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 24px;
}

.media-main {
    position: relative;
    aspect-ratio: 16/9;
    background: #000;
}

.media-main img,
.media-main iframe {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border: none;
}

.media-thumbs-wrapper {
    position: relative;
    padding: 12px;
    background: var(--bg-tertiary);
}

.media-thumbs-container {
    position: relative;
    overflow: hidden;
}

.media-thumbs {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
    padding: 4px 0;
    scroll-behavior: smooth;
}

.media-thumbs::-webkit-scrollbar { display: none; }

/* Navigation Arrows */
.thumb-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 50%;
    color: var(--text-primary);
    font-size: 14px;
    cursor: pointer;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    opacity: 0;
}

.media-thumbs-wrapper:hover .thumb-nav { opacity: 1; }
.thumb-nav:hover { background: var(--accent); border-color: var(--accent); }
.thumb-nav:disabled { opacity: 0.3; cursor: not-allowed; }
.thumb-nav.prev { left: -8px; }
.thumb-nav.next { right: -8px; }

.media-thumb {
    flex: 0 0 160px;
    height: 90px;
    border-radius: 4px;
    overflow: hidden;
    cursor: pointer;
    position: relative;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.media-thumb::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.4);
    transition: opacity 0.2s;
}

.media-thumb:hover::after,
.media-thumb.active::after { opacity: 0; }

.media-thumb.active { border-color: var(--accent); }

.media-thumb img { width: 100%; height: 100%; object-fit: cover; }

.media-thumb .video-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 2;
    font-size: 24px;
    color: #fff;
}

/* Game Info Section */
.game-info-section {
    padding: 32px 0;
}

.game-title {
    font-size: 32px;
    font-weight: 600;
    margin-bottom: 16px;
    line-height: 1.2;
}

.game-meta-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}

.meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--text-secondary);
}

.meta-badge i { color: var(--accent); font-size: 12px; }
.meta-badge strong { color: var(--text-primary); }

.rating-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-secondary);
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
}

.rating-badge i { color: var(--accent); }
.rating-badge span { color: var(--text-secondary); margin-left: 4px; }

/* Categories & Tags */
.categories-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}

.category-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--accent);
    color: #fff;
    padding: 6px 14px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}

.category-link:hover { background: var(--accent-hover); }

.tags-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tag-item {
    background: var(--bg-secondary);
    color: var(--text-secondary);
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    transition: all 0.2s;
}

.tag-item:hover { color: var(--text-primary); background: var(--bg-tertiary); }

/* Content Sections */
.content-block {
    background: var(--bg-secondary);
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}

.block-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.block-title {
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.block-title i { color: var(--accent); font-size: 16px; }

.description-text {
    color: var(--text-secondary);
    font-size: 15px;
    line-height: 1.8;
    white-space: pre-wrap;
}

/* Right Column - Sidebar */
.game-sidebar {
    position: sticky;
    top: 24px;
    align-self: flex-start;
}

/* Purchase Card */
.purchase-card {
    background: var(--bg-secondary);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 16px;
}

.game-cover-sidebar {
    position: relative;
    aspect-ratio: 3/4;
    max-height: 280px;
    overflow: hidden;
}

.game-cover-sidebar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cover-badges {
    position: absolute;
    top: 12px;
    left: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.badge {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.badge-discount { background: var(--success); color: #fff; }
.badge-owned { background: var(--accent); color: #fff; }

.purchase-body {
    padding: 20px;
}

/* Price Display */
.price-section {
    margin-bottom: 16px;
}

.price-row {
    display: flex;
    align-items: baseline;
    gap: 12px;
}

.price-current {
    font-size: 28px;
    font-weight: 700;
}

.price-current.free { color: var(--success); }

.price-original {
    font-size: 16px;
    color: var(--text-secondary);
    text-decoration: line-through;
}

.discount-badge {
    background: var(--success);
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.owned-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    background: rgba(40, 167, 69, 0.15);
    border: 1px solid var(--success);
    border-radius: 4px;
    color: var(--success);
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 16px;
}

/* Buttons */
.btn-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 20px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
}

.btn-primary {
    background: var(--accent);
    color: #fff;
}

.btn-primary:hover { background: var(--accent-hover); }

.btn-success {
    background: var(--success);
    color: #fff;
}

.btn-success:hover { filter: brightness(1.1); }

.btn-secondary {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}

.btn-secondary:hover { background: #303133; }

.btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-primary);
}

.btn-outline:hover { border-color: var(--text-secondary); }

.btn-wishlist.active {
    border-color: var(--danger);
    color: var(--danger);
}

.btn-wishlist.active i { color: var(--danger); }

/* Unavailable State */
.unavailable-state {
    text-align: center;
    padding: 20px;
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid var(--warning);
    border-radius: 4px;
    margin-bottom: 16px;
}

.unavailable-state i { font-size: 32px; color: var(--warning); margin-bottom: 12px; }
.unavailable-state h4 { font-size: 14px; color: var(--warning); margin-bottom: 6px; }
.unavailable-state p { font-size: 12px; color: var(--text-secondary); }

/* Platforms */
.platforms-section {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-top: 16px;
    margin-top: 16px;
    border-top: 1px solid var(--border);
}

.platforms-label {
    font-size: 12px;
    color: var(--text-secondary);
}

.platforms-icons {
    display: flex;
    gap: 10px;
    font-size: 18px;
    color: var(--text-secondary);
}

.platforms-icons i:hover { color: var(--text-primary); }

/* Info Card */
.info-card {
    background: var(--bg-secondary);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
}

.info-card-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 16px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
}

.info-item .label { color: var(--text-secondary); }
.info-item .value { font-weight: 500; }

/* Developer Card */
.dev-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--bg-tertiary);
    border-radius: 6px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s;
}

.dev-link:hover { background: #303133; }

.dev-link img {
    width: 48px;
    height: 48px;
    border-radius: 6px;
    object-fit: cover;
}

.dev-info .name {
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.dev-info .name i { color: var(--accent); font-size: 12px; }
.dev-info .role { font-size: 12px; color: var(--text-secondary); }

/* Requirements */
.req-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.req-tab {
    flex: 1;
    padding: 10px;
    background: var(--bg-tertiary);
    border: none;
    border-radius: 4px;
    color: var(--text-secondary);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.req-tab.active {
    background: var(--accent);
    color: #fff;
}

.req-content {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.6;
    white-space: pre-wrap;
}

/* Reviews Section */
.reviews-header {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 20px;
    background: var(--bg-tertiary);
    border-radius: 8px;
    margin-bottom: 20px;
}

.reviews-score {
    text-align: center;
    min-width: 100px;
}

.reviews-score .number {
    font-size: 48px;
    font-weight: 700;
    color: var(--accent);
    line-height: 1;
}

.reviews-score .stars {
    display: flex;
    justify-content: center;
    gap: 2px;
    margin: 8px 0;
    color: var(--accent);
    font-size: 14px;
}

.reviews-score .count {
    font-size: 12px;
    color: var(--text-secondary);
}

.reviews-bars {
    flex: 1;
}

.bar-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}

.bar-item:last-child { margin-bottom: 0; }

.bar-item .num {
    width: 20px;
    font-size: 12px;
    color: var(--text-secondary);
    text-align: center;
}

.bar-track {
    flex: 1;
    height: 8px;
    background: var(--bg-primary);
    border-radius: 4px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: var(--accent);
    border-radius: 4px;
    transition: width 0.3s;
}

.bar-item .qty {
    width: 30px;
    font-size: 12px;
    color: var(--text-secondary);
    text-align: right;
}

/* Review Card */
.review-item {
    padding: 20px;
    background: var(--bg-tertiary);
    border-radius: 8px;
    margin-bottom: 12px;
}

.review-item:last-child { margin-bottom: 0; }

.review-item.my-review {
    border: 1px solid var(--accent);
    position: relative;
}

.my-review-label {
    position: absolute;
    top: -10px;
    left: 16px;
    background: var(--accent);
    color: #fff;
    padding: 2px 10px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

.review-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.review-author {
    display: flex;
    align-items: center;
    gap: 12px;
}

.review-author img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.author-info .author-name {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
}

.author-info .author-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
}

.author-meta .stars { color: var(--accent); }
.author-meta .date { color: var(--text-secondary); }

.review-actions {
    display: flex;
    gap: 8px;
}

.review-actions button {
    padding: 6px 10px;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 4px;
    color: var(--text-secondary);
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.review-actions button:hover { border-color: var(--accent); color: var(--accent); }
.review-actions button.delete:hover { border-color: var(--danger); color: var(--danger); }

.review-text {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.6;
}

.btn-write-review {
    width: 100%;
    padding: 14px;
    background: var(--bg-tertiary);
    border: 1px dashed var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
    margin-bottom: 20px;
}

.btn-write-review:hover { border-color: var(--accent); color: var(--accent); }

.review-notice {
    text-align: center;
    padding: 16px;
    background: var(--bg-tertiary);
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 13px;
    margin-bottom: 20px;
}

.no-reviews {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.no-reviews i { font-size: 40px; margin-bottom: 12px; opacity: 0.3; }

/* Review Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.9);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}

.modal-overlay.active { display: flex; }

.modal-content {
    background: var(--bg-secondary);
    border-radius: 8px;
    width: 100%;
    max-width: 480px;
    animation: modalIn 0.2s ease;
}

@keyframes modalIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
}

.modal-header h3 { font-size: 18px; font-weight: 600; }

.modal-close {
    width: 32px;
    height: 32px;
    border-radius: 4px;
    border: none;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    font-size: 16px;
    cursor: pointer;
    transition: all 0.2s;
}

.modal-close:hover { background: var(--border); color: var(--text-primary); }

.modal-body { padding: 24px; }

.star-select {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 24px;
}

.star-select i {
    font-size: 36px;
    color: var(--border);
    cursor: pointer;
    transition: all 0.15s;
}

.star-select i:hover,
.star-select i.active { color: var(--accent); transform: scale(1.1); }

.review-input {
    width: 100%;
    min-height: 140px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 16px;
    color: var(--text-primary);
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
}

.review-input:focus { outline: none; border-color: var(--accent); }
.review-input::placeholder { color: var(--text-secondary); }

.modal-footer {
    display: flex;
    gap: 12px;
    padding: 20px 24px;
    border-top: 1px solid var(--border);
}

.modal-footer .btn { flex: 1; }

/* Toast */
.toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    padding: 14px 24px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    z-index: 10000;
    opacity: 0;
    transition: all 0.3s;
}

.toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
.toast.success { border-color: var(--success); }
.toast.success i { color: var(--success); }
.toast.error { border-color: var(--danger); }
.toast.error i { color: var(--danger); }

/* ===== MOBILE STYLES - App Style ===== */
@media (max-width: 768px) {
    .game-hero { display: none; }
    
    .store-container { padding: 0; }
    
    /* Mobile Header */
    .mobile-game-header {
        display: block;
        position: relative;
    }
    
    /* Mobile Hero Image - Estilo Play Store */
    .mobile-hero {
        position: relative;
        width: 100%;
        aspect-ratio: 16/9;
        background: #000;
    }
    
    .mobile-hero img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .mobile-hero::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 80px;
        background: linear-gradient(to top, var(--bg-primary), transparent);
    }
    
    /* Mobile Game Info - Estilo App Store */
    .mobile-game-info {
        display: flex;
        gap: 16px;
        padding: 16px;
        margin-top: -50px;
        position: relative;
        z-index: 5;
    }
    
    .mobile-game-cover {
        width: 100px;
        height: 100px;
        border-radius: 16px;
        overflow: hidden;
        flex-shrink: 0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        border: 2px solid var(--bg-secondary);
    }
    
    .mobile-game-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .mobile-game-details {
        flex: 1;
        min-width: 0;
        padding-top: 30px;
    }
    
    .mobile-game-title {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 4px;
        line-height: 1.2;
    }
    
    .mobile-game-dev {
        font-size: 13px;
        color: var(--accent);
        margin-bottom: 8px;
    }
    
    .mobile-game-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 12px;
        color: var(--text-secondary);
    }
    
    .mobile-game-meta .meta-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .mobile-game-meta i { font-size: 10px; }
    
    /* Mobile Purchase Section - Fixed or Inline */
    .mobile-purchase {
        padding: 16px;
        background: var(--bg-secondary);
        border-top: 1px solid var(--border);
        margin-bottom: 16px;
    }
    
    .mobile-price-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    
    .mobile-price {
        display: flex;
        align-items: baseline;
        gap: 8px;
    }
    
    .mobile-price .current {
        font-size: 24px;
        font-weight: 700;
    }
    
    .mobile-price .current.free { color: var(--success); }
    
    .mobile-price .original {
        font-size: 14px;
        color: var(--text-secondary);
        text-decoration: line-through;
    }
    
    .mobile-discount {
        background: var(--success);
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
    }
    
    .mobile-owned-badge {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--success);
        font-size: 14px;
        font-weight: 600;
    }
    
    .mobile-btn-group {
        display: flex;
        gap: 10px;
    }
    
    .mobile-btn-group .btn {
        flex: 1;
        padding: 14px;
    }
    
    .mobile-btn-group .btn-wishlist-icon {
        flex: 0 0 48px;
        padding: 14px;
    }
    
    /* Mobile Quick Info */
    .mobile-quick-info {
        display: flex;
        justify-content: space-around;
        padding: 16px;
        background: var(--bg-secondary);
        margin: 0 16px 16px;
        border-radius: 12px;
    }
    
    .quick-info-item {
        text-align: center;
    }
    
    .quick-info-item .value {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    
    .quick-info-item .value i { color: var(--accent); font-size: 12px; }
    
    .quick-info-item .label {
        font-size: 11px;
        color: var(--text-secondary);
    }
    
    /* Mobile Media Gallery */
    .mobile-media-section {
        padding: 0 16px 16px;
    }
    
    .mobile-media-scroll {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
        padding: 4px 0;
        scroll-snap-type: x mandatory;
    }
    
    .mobile-media-scroll::-webkit-scrollbar { display: none; }
    
    .mobile-media-item {
        flex: 0 0 280px;
        aspect-ratio: 16/9;
        border-radius: 8px;
        overflow: hidden;
        scroll-snap-align: start;
        position: relative;
    }
    
    .mobile-media-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .mobile-media-item.video::after {
        content: '\f04b';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 50px;
        height: 50px;
        background: rgba(0,0,0,0.7);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 18px;
    }
    
    /* Layout changes for mobile */
    .game-layout {
        display: block;
        margin-top: 0;
        padding-bottom: 0;
    }
    
    .game-content {
        padding: 0 16px 24px;
    }
    
    .game-sidebar {
        display: none;
    }
    
    /* Hide desktop elements */
    .media-section,
    .game-info-section { display: none; }
    
    /* Mobile content blocks */
    .content-block {
        margin: 0 0 16px;
        border-radius: 12px;
    }
    
    .content-block:first-of-type {
        margin-top: 0;
    }
    
    /* Mobile Categories */
    .mobile-categories {
        display: flex;
        gap: 8px;
        padding: 0 16px 16px;
        overflow-x: auto;
        scrollbar-width: none;
    }
    
    .mobile-categories::-webkit-scrollbar { display: none; }
    
    /* Reviews on mobile */
    .reviews-header {
        flex-direction: column;
        text-align: center;
        padding: 16px;
    }
    
    .reviews-bars { width: 100%; }
    
    .review-item { padding: 16px; }
}

/* Tablet */
@media (min-width: 769px) and (max-width: 1024px) {
    .game-layout {
        grid-template-columns: 1fr 300px;
        gap: 24px;
        margin-top: -80px;
    }
    
    .game-hero { height: 320px; }
    
    .store-container { padding: 0 20px; }
    
    .game-cover-sidebar { max-height: 240px; }
}

/* Small mobile */
@media (max-width: 380px) {
    .mobile-game-cover {
        width: 80px;
        height: 80px;
    }
    
    .mobile-game-title { font-size: 18px; }
    
    .mobile-media-item { flex: 0 0 240px; }
    
    .mobile-quick-info { padding: 12px; }
    
    .quick-info-item .value { font-size: 13px; }
}
</style>

<div class="game-page">
    
    <!-- ===== MOBILE LAYOUT ===== -->
    <div class="mobile-game-header">
        <!-- Mobile Hero Banner -->
        <div class="mobile-hero">
            <img src="<?= SITE_URL . ($jogo['imagem_banner'] ?: $jogo['imagem_capa']) ?>" alt="<?= sanitize($jogo['titulo']) ?>">
        </div>
        
        <!-- Mobile Game Info - App Store Style -->
        <div class="mobile-game-info">
            <div class="mobile-game-cover">
                <img src="<?= SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png') ?>" alt="<?= sanitize($jogo['titulo']) ?>">
            </div>
            <div class="mobile-game-details">
                <h1 class="mobile-game-title"><?= sanitize($jogo['titulo']) ?></h1>
                <a href="<?= SITE_URL ?>/pages/desenvolvedor.php?slug=<?= $jogo['dev_slug'] ?>" class="mobile-game-dev">
                    <?= sanitize($jogo['nome_estudio']) ?>
                </a>
                <div class="mobile-game-meta">
                    <span class="meta-item">
                        <i class="fas fa-star"></i>
                        <?= number_format($jogo['nota_media'], 1) ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-download"></i>
                        <?= number_format($jogo['total_vendas'], 0, ',', '.') ?>
                    </span>
                    <?php if ($jogo['classificacao_indicativa'] > 0): ?>
                    <span class="meta-item"><?= $jogo['classificacao_indicativa'] ?>+</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Mobile Purchase Section -->
        <div class="mobile-purchase">
            <?php if (!$tem_arquivo): ?>
                <div class="mobile-price-row">
                    <span style="color: var(--warning); font-size: 14px;">
                        <i class="fas fa-clock"></i> Em Breve
                    </span>
                </div>
                <div class="mobile-btn-group">
                    <?php if ($user_id): ?>
                    <button class="btn btn-outline btn-wishlist <?= $in_wishlist ? 'active' : '' ?>" data-action="wishlist" data-id="<?= $jogo['id'] ?>">
                        <i class="fas fa-heart"></i>
                        <span><?= $in_wishlist ? 'Na Lista' : 'Lista de Desejos' ?></span>
                    </button>
                    <?php else: ?>
                    <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Entrar
                    </a>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($in_library): ?>
                <div class="mobile-price-row">
                    <span class="mobile-owned-badge">
                        <i class="fas fa-check-circle"></i> Na sua biblioteca
                    </span>
                </div>
                <div class="mobile-btn-group">
                    <a href="<?= SITE_URL ?>/user/download-jogo.php?jogo_id=<?= $jogo['id'] ?>" class="btn btn-success">
                        <i class="fas fa-download"></i> Baixar
                    </a>
                    <a href="<?= SITE_URL ?>/user/biblioteca.php" class="btn btn-secondary">
                        <i class="fas fa-gamepad"></i> Biblioteca
                    </a>
                </div>
                
            <?php else: ?>
                <div class="mobile-price-row">
                    <div class="mobile-price">
                        <?php if ($preco_final == 0): ?>
                        <span class="current free">Gratuito</span>
                        <?php else: ?>
                            <?php if ($desconto > 0): ?>
                            <span class="original"><?= formatPrice($jogo['preco_centavos']) ?></span>
                            <?php endif; ?>
                            <span class="current"><?= formatPrice($preco_final) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($desconto > 0): ?>
                    <span class="mobile-discount">-<?= $desconto ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="mobile-btn-group">
                    <?php if ($user_id): ?>
                    <button class="btn <?= $in_cart ? 'btn-success' : 'btn-primary' ?>" data-action="cart" data-id="<?= $jogo['id'] ?>">
                        <i class="fas <?= $in_cart ? 'fa-check' : 'fa-cart-plus' ?>"></i>
                        <span><?= $in_cart ? 'No Carrinho' : 'Comprar' ?></span>
                    </button>
                    <button class="btn btn-outline btn-wishlist-icon btn-wishlist <?= $in_wishlist ? 'active' : '' ?>" data-action="wishlist" data-id="<?= $jogo['id'] ?>">
                        <i class="fas fa-heart"></i>
                    </button>
                    <?php else: ?>
                    <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Entrar
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Quick Info -->
        <div class="mobile-quick-info">
            <div class="quick-info-item">
                <div class="value">
                    <i class="fas fa-star"></i>
                    <?= number_format($jogo['nota_media'], 1) ?>
                </div>
                <div class="label"><?= $total_reviews ?> reviews</div>
            </div>
            <div class="quick-info-item">
                <div class="value"><?= number_format($jogo['total_vendas'], 0, ',', '.') ?></div>
                <div class="label">Downloads</div>
            </div>
            <?php if ($arquivo_info): ?>
            <div class="quick-info-item">
                <div class="value"><?= formatFileSize($arquivo_info['tamanho_bytes']) ?></div>
                <div class="label">Tamanho</div>
            </div>
            <div class="quick-info-item">
                <div class="value"><?= sanitize($arquivo_info['versao']) ?></div>
                <div class="label">Versão</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Categories -->
        <?php if (!empty($categorias)): ?>
        <div class="mobile-categories">
            <?php foreach ($categorias as $cat): ?>
            <a href="<?= SITE_URL ?>/pages/categoria.php?slug=<?= $cat['slug'] ?>" class="category-link">
                <?= htmlspecialchars($cat['nome']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Mobile Media Gallery -->
        <?php if ($jogo['video_trailer'] || count($imagens) > 0): ?>
        <div class="mobile-media-section">
            <div class="mobile-media-scroll">
                <?php if ($jogo['video_trailer']): ?>
                <div class="mobile-media-item video" onclick="openVideoModal('<?= $jogo['video_trailer'] ?>')">
                    <img src="https://img.youtube.com/vi/<?= getYoutubeId($jogo['video_trailer']) ?>/maxresdefault.jpg" alt="Trailer">
                </div>
                <?php endif; ?>
                <?php foreach ($imagens as $img): ?>
                <div class="mobile-media-item" onclick="openImageModal('<?= SITE_URL . $img['imagem'] ?>')">
                    <img src="<?= SITE_URL . $img['imagem'] ?>" alt="Screenshot">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- ===== DESKTOP LAYOUT ===== -->
    <!-- Hero Banner - Desktop -->
    <div class="game-hero">
        <div class="hero-bg"></div>
    </div>

    <div class="store-container">
        <div class="game-layout">
            <!-- Left Column -->
            <div class="game-content">
                <!-- Media Gallery - Desktop -->
                <?php if ($jogo['video_trailer'] || count($imagens) > 0): ?>
                <div class="media-section">
                    <div class="media-main" id="mediaMain">
                        <?php if ($jogo['video_trailer']): ?>
                            <iframe src="<?= $jogo['video_trailer'] ?>" allowfullscreen></iframe>
                        <?php elseif (count($imagens) > 0): ?>
                            <img src="<?= SITE_URL . $imagens[0]['imagem'] ?>" alt="Screenshot">
                        <?php endif; ?>
                    </div>
                    <?php if (($jogo['video_trailer'] ? 1 : 0) + count($imagens) > 1): ?>
                    <div class="media-thumbs-wrapper">
                        <button class="thumb-nav prev" id="thumbPrev" onclick="scrollThumbs(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="media-thumbs-container">
                            <div class="media-thumbs" id="mediaThumbs">
                                <?php if ($jogo['video_trailer']): ?>
                                <div class="media-thumb active" data-type="video" data-src="<?= $jogo['video_trailer'] ?>">
                                    <img src="https://img.youtube.com/vi/<?= getYoutubeId($jogo['video_trailer']) ?>/mqdefault.jpg" alt="">
                                    <i class="fas fa-play video-icon"></i>
                                </div>
                                <?php endif; ?>
                                <?php foreach ($imagens as $i => $img): ?>
                                <div class="media-thumb <?= (!$jogo['video_trailer'] && $i === 0) ? 'active' : '' ?>" data-type="image" data-src="<?= SITE_URL . $img['imagem'] ?>">
                                    <img src="<?= SITE_URL . $img['imagem'] ?>" alt="">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button class="thumb-nav next" id="thumbNext" onclick="scrollThumbs(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Game Info - Desktop -->
                <div class="game-info-section">
                    <h1 class="game-title"><?= sanitize($jogo['titulo']) ?></h1>
                    
                    <div class="game-meta-row">
                        <div class="rating-badge">
                            <i class="fas fa-star"></i>
                            <strong><?= number_format($jogo['nota_media'], 1) ?></strong>
                            <span>(<?= $total_reviews ?>)</span>
                        </div>
                        <div class="meta-badge">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?= date('d/m/Y', strtotime($jogo['data_lancamento'] ?? $jogo['criado_em'])) ?></span>
                        </div>
                        <div class="meta-badge">
                            <i class="fas fa-download"></i>
                            <strong><?= number_format($jogo['total_vendas'], 0, ',', '.') ?></strong>
                        </div>
                        <?php if ($jogo['classificacao_indicativa'] > 0): ?>
                        <div class="meta-badge">
                            <i class="fas fa-shield-alt"></i>
                            <strong><?= $jogo['classificacao_indicativa'] ?>+</strong>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($categorias)): ?>
                    <div class="categories-row">
                        <?php foreach ($categorias as $cat): ?>
                        <a href="<?= SITE_URL ?>/pages/categoria.php?slug=<?= $cat['slug'] ?>" class="category-link">
                            <?= htmlspecialchars($cat['nome']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($tags)): ?>
                    <div class="tags-row">
                        <?php foreach ($tags as $t): ?>
                        <span class="tag-item"><?= sanitize($t['nome']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="content-block">
                    <div class="block-header">
                        <h2 class="block-title"><i class="fas fa-info-circle"></i> Sobre o Jogo</h2>
                    </div>
                    <div class="description-text"><?= nl2br(sanitize($jogo['descricao_completa'] ?: $jogo['descricao_curta'])) ?></div>
                </div>

                <!-- Reviews -->
                <div class="content-block" id="reviews">
                    <div class="block-header">
                        <h2 class="block-title"><i class="fas fa-star"></i> Avaliações</h2>
                    </div>

                    <div class="reviews-header">
                        <div class="reviews-score">
                            <div class="number"><?= number_format($jogo['nota_media'], 1) ?></div>
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?= $i <= round($jogo['nota_media']) ? 'fas' : 'far' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="count"><?= $total_reviews ?> avaliações</div>
                        </div>
                        <div class="reviews-bars">
                            <?php for ($i = 5; $i >= 1; $i--): 
                                $pct = $total_reviews > 0 ? ($rating_dist[$i] / $total_reviews) * 100 : 0; 
                            ?>
                            <div class="bar-item">
                                <span class="num"><?= $i ?></span>
                                <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
                                <span class="qty"><?= $rating_dist[$i] ?></span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <?php if ($in_library): ?>
                        <?php if ($my_review): ?>
                        <div class="review-item my-review">
                            <span class="my-review-label">Sua Avaliação</span>
                            <div class="review-top">
                                <div class="review-author">
                                    <img src="<?= getAvatar($my_review['avatar_url']) ?>" alt="">
                                    <div class="author-info">
                                        <div class="author-name">Você</div>
                                        <div class="author-meta">
                                            <span class="stars"><?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= $my_review['nota'] ? 'fas' : 'far' ?> fa-star"></i><?php endfor; ?></span>
                                            <span class="date"><?= date('d/m/Y', strtotime($my_review['criado_em'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="review-actions">
                                    <button onclick="openReviewModal(<?= $my_review['nota'] ?>,'<?= addslashes(htmlspecialchars($my_review['comentario'])) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="<?= SITE_URL ?>/api/avaliar.php" method="POST" style="display:inline" onsubmit="return confirm('Remover avaliação?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="jogo_id" value="<?= $jogo['id'] ?>">
                                        <button type="submit" class="delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <div class="review-text"><?= nl2br(htmlspecialchars($my_review['comentario'])) ?></div>
                        </div>
                        <?php else: ?>
                        <button class="btn-write-review" onclick="openReviewModal()">
                            <i class="fas fa-pen"></i> Escrever uma avaliação
                        </button>
                        <?php endif; ?>
                    <?php elseif ($user_id): ?>
                    <div class="review-notice">
                        <i class="fas fa-info-circle"></i> Adquira o jogo para deixar sua avaliação
                    </div>
                    <?php endif; ?>

                    <?php 
                    $has_other = false;
                    foreach ($avaliacoes as $rev): 
                        if ($rev['usuario_id'] == $user_id) continue;
                        $has_other = true;
                    ?>
                    <div class="review-item">
                        <div class="review-top">
                            <div class="review-author">
                                <img src="<?= getAvatar($rev['avatar_url']) ?>" alt="">
                                <div class="author-info">
                                    <div class="author-name"><?= sanitize($rev['nome_usuario']) ?></div>
                                    <div class="author-meta">
                                        <span class="stars"><?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= $rev['nota'] ? 'fas' : 'far' ?> fa-star"></i><?php endfor; ?></span>
                                        <span class="date"><?= date('d/m/Y', strtotime($rev['criado_em'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="review-text"><?= nl2br(sanitize($rev['comentario'])) ?></div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (!$has_other && !$my_review): ?>
                    <div class="no-reviews">
                        <i class="fas fa-comment-slash"></i>
                        <p>Nenhuma avaliação ainda. Seja o primeiro!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column - Sidebar - Desktop -->
            <div class="game-sidebar">
                <!-- Purchase Card -->
                <div class="purchase-card">
                    <div class="game-cover-sidebar">
                        <img src="<?= SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png') ?>">
                        <div class="cover-badges">
                            <?php if ($in_library): ?>
                            <span class="badge badge-owned">Na Biblioteca</span>
                            <?php elseif ($desconto > 0): ?>
                            <span class="badge badge-discount">-<?= $desconto ?>%</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="purchase-body">
                        <?php if (!$tem_arquivo): ?>
                        <div class="unavailable-state">
                            <i class="fas fa-clock"></i>
                            <h4>Em Breve</h4>
                            <p>Este jogo ainda não está disponível</p>
                        </div>
                        <div class="btn-group">
                            <?php if ($user_id): ?>
                            <button class="btn btn-outline btn-wishlist <?= $in_wishlist ? 'active' : '' ?>" data-action="wishlist" data-id="<?= $jogo['id'] ?>">
                                <i class="fas fa-heart"></i> <span><?= $in_wishlist ? 'Na Lista de Desejos' : 'Adicionar à Lista' ?></span>
                            </button>
                            <?php else: ?>
                            <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Entrar
                            </a>
                            <?php endif; ?>
                        </div>

                        <?php elseif ($in_library): ?>
                        <div class="owned-banner">
                            <i class="fas fa-check-circle"></i> Você possui este jogo
                        </div>
                        <div class="btn-group">
                            <a href="<?= SITE_URL ?>/user/biblioteca.php" class="btn btn-success">
                                <i class="fas fa-gamepad"></i> Ir para Biblioteca
                            </a>
                            <a href="<?= SITE_URL ?>/user/download-jogo.php?jogo_id=<?= $jogo['id'] ?>" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Baixar
                            </a>
                        </div>

                        <?php else: ?>
                        <div class="price-section">
                            <?php if ($preco_final == 0): ?>
                            <div class="price-row">
                                <span class="price-current free">Gratuito</span>
                            </div>
                            <?php else: ?>
                            <div class="price-row">
                                <?php if ($desconto > 0): ?>
                                <span class="discount-badge">-<?= $desconto ?>%</span>
                                <span class="price-original"><?= formatPrice($jogo['preco_centavos']) ?></span>
                                <?php endif; ?>
                                <span class="price-current"><?= formatPrice($preco_final) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="btn-group">
                            <?php if ($user_id): ?>
                            <button class="btn <?= $in_cart ? 'btn-success' : 'btn-primary' ?>" data-action="cart" data-id="<?= $jogo['id'] ?>">
                                <i class="fas <?= $in_cart ? 'fa-check' : 'fa-cart-plus' ?>"></i>
                                <span><?= $in_cart ? 'No Carrinho' : 'Adicionar ao Carrinho' ?></span>
                            </button>
                            <?php if ($in_cart): ?>
                            <a href="<?= SITE_URL ?>/user/carrinho.php" class="btn btn-secondary btn-finalize">
                                <i class="fas fa-shopping-bag"></i> Finalizar Compra
                            </a>
                            <?php endif; ?>
                            <button class="btn btn-outline btn-wishlist <?= $in_wishlist ? 'active' : '' ?>" data-action="wishlist" data-id="<?= $jogo['id'] ?>">
                                <i class="fas fa-heart"></i> <span><?= $in_wishlist ? 'Na Lista' : 'Lista de Desejos' ?></span>
                            </button>
                            <?php else: ?>
                            <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Entrar para Comprar
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($plataformas)): ?>
                        <div class="platforms-section">
                            <span class="platforms-label">Plataformas</span>
                            <div class="platforms-icons">
                                <?php foreach ($plataformas as $p): ?>
                                <i class="<?= $p['icone'] ?>" title="<?= $p['nome'] ?>"></i>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Developer -->
                <div class="info-card">
                    <div class="info-card-title">Desenvolvedor</div>
                    <a href="<?= SITE_URL ?>/pages/desenvolvedor.php?slug=<?= $jogo['dev_slug'] ?>" class="dev-link">
                        <img src="<?= SITE_URL . ($jogo['logo_url'] ?: '/assets/images/default-dev.png') ?>" alt="">
                        <div class="dev-info">
                            <div class="name">
                                <?= sanitize($jogo['nome_estudio']) ?>
                                <?php if ($jogo['dev_verificado']): ?><i class="fas fa-check-circle"></i><?php endif; ?>
                            </div>
                            <div class="role">Ver perfil do estúdio</div>
                        </div>
                    </a>
                </div>

                <!-- File Info -->
                <?php if ($arquivo_info): ?>
                <div class="info-card">
                    <div class="info-card-title">Informações</div>
                    <div class="info-list">
                        <div class="info-item">
                            <span class="label">Versão</span>
                            <span class="value"><?= sanitize($arquivo_info['versao']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Tamanho</span>
                            <span class="value"><?= formatFileSize($arquivo_info['tamanho_bytes']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Downloads</span>
                            <span class="value"><?= number_format($arquivo_info['downloads'], 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Requirements -->
                <?php if ($jogo['requisitos_minimos'] || $jogo['requisitos_recomendados']): ?>
                <div class="info-card">
                    <div class="info-card-title">Requisitos do Sistema</div>
                    <div class="req-tabs">
                        <?php if ($jogo['requisitos_minimos']): ?>
                        <button class="req-tab active" data-req="min">Mínimos</button>
                        <?php endif; ?>
                        <?php if ($jogo['requisitos_recomendados']): ?>
                        <button class="req-tab <?= !$jogo['requisitos_minimos'] ? 'active' : '' ?>" data-req="rec">Recomendados</button>
                        <?php endif; ?>
                    </div>
                    <div class="req-content" id="reqContent"><?= sanitize($jogo['requisitos_minimos'] ?: $jogo['requisitos_recomendados']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Video Modal for Mobile -->
<div class="modal-overlay" id="videoModal">
    <div class="modal-content" style="max-width: 900px; padding: 0; background: #000;">
        <button class="modal-close" onclick="closeVideoModal()" style="position: absolute; top: 10px; right: 10px; z-index: 10;">
            <i class="fas fa-times"></i>
        </button>
        <div style="aspect-ratio: 16/9;">
            <iframe id="videoFrame" src="" style="width: 100%; height: 100%; border: none;" allowfullscreen></iframe>
        </div>
    </div>
</div>

<!-- Image Modal for Mobile -->
<div class="modal-overlay" id="imageModal">
    <div class="modal-content" style="max-width: 1000px; padding: 0; background: transparent;">
        <button class="modal-close" onclick="closeImageModal()" style="position: absolute; top: -40px; right: 0;">
            <i class="fas fa-times"></i>
        </button>
        <img id="modalImage" src="" style="width: 100%; border-radius: 8px;" alt="">
    </div>
</div>

<!-- Review Modal -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Escrever Avaliação</h3>
            <button class="modal-close" onclick="closeReviewModal()"><i class="fas fa-times"></i></button>
        </div>
        <form action="<?= SITE_URL ?>/api/avaliar.php" method="POST">
            <input type="hidden" name="action" id="reviewAction" value="add">
            <input type="hidden" name="jogo_id" value="<?= $jogo['id'] ?>">
            <input type="hidden" name="nota" id="reviewNota" required>
            <div class="modal-body">
                <div class="star-select" id="starSelect">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="far fa-star" data-v="<?= $i ?>"></i>
                    <?php endfor; ?>
                </div>
                <textarea name="comentario" id="reviewText" class="review-input" placeholder="O que você achou do jogo?" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeReviewModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publicar</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>

<script>
const SITE_URL = '<?= SITE_URL ?>';
const isLogged = <?= $user_id ? 'true' : 'false' ?>;
const reqMin = <?= json_encode($jogo['requisitos_minimos'] ?? '') ?>;
const reqRec = <?= json_encode($jogo['requisitos_recomendados'] ?? '') ?>;

// Thumbnail Navigation
const thumbsContainer = document.getElementById('mediaThumbs');
const thumbWidth = 168; // 160 + gap

function scrollThumbs(direction) {
    if (thumbsContainer) {
        thumbsContainer.scrollBy({
            left: direction * thumbWidth * 2,
            behavior: 'smooth'
        });
    }
    updateNavButtons();
}

function updateNavButtons() {
    const prevBtn = document.getElementById('thumbPrev');
    const nextBtn = document.getElementById('thumbNext');
    
    if (thumbsContainer && prevBtn && nextBtn) {
        prevBtn.disabled = thumbsContainer.scrollLeft <= 0;
        nextBtn.disabled = thumbsContainer.scrollLeft >= thumbsContainer.scrollWidth - thumbsContainer.clientWidth - 10;
    }
}

if (thumbsContainer) {
    thumbsContainer.addEventListener('scroll', updateNavButtons);
    setTimeout(updateNavButtons, 100);
}

// Media Gallery
document.querySelectorAll('.media-thumb').forEach(t => {
    t.onclick = function() {
        document.querySelectorAll('.media-thumb').forEach(x => x.classList.remove('active'));
        this.classList.add('active');
        const m = document.getElementById('mediaMain');
        const { type, src } = this.dataset;
        m.innerHTML = type === 'video' 
            ? `<iframe src="${src}" allowfullscreen></iframe>` 
            : `<img src="${src}" alt="">`;
    };
});

// Requirements Tabs
document.querySelectorAll('.req-tab').forEach(tab => {
    tab.onclick = function() {
        document.querySelectorAll('.req-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('reqContent').textContent = this.dataset.req === 'min' ? reqMin : reqRec;
    };
});

// Mobile Video Modal
function openVideoModal(src) {
    document.getElementById('videoFrame').src = src;
    document.getElementById('videoModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeVideoModal() {
    document.getElementById('videoFrame').src = '';
    document.getElementById('videoModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Mobile Image Modal
function openImageModal(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Review Modal
function openReviewModal(n = 0, t = '') {
    if (!isLogged) {
        showToast('Faça login para avaliar', 'error');
        setTimeout(() => location.href = `${SITE_URL}/auth/login.php`, 1000);
        return;
    }
    document.getElementById('reviewModal').classList.add('active');
    document.getElementById('reviewAction').value = n ? 'update' : 'add';
    document.getElementById('modalTitle').textContent = n ? 'Editar Avaliação' : 'Escrever Avaliação';
    document.getElementById('reviewNota').value = n;
    document.getElementById('reviewText').value = t;
    updateStars(n);
    document.body.style.overflow = 'hidden';
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('active');
    document.body.style.overflow = '';
}

const stars = document.querySelectorAll('#starSelect i');
const notaInput = document.getElementById('reviewNota');

stars.forEach(s => {
    s.onclick = () => { notaInput.value = s.dataset.v; updateStars(s.dataset.v); };
    s.onmouseenter = () => updateStars(s.dataset.v);
});

document.getElementById('starSelect').onmouseleave = () => updateStars(notaInput.value || 0);

function updateStars(v) {
    stars.forEach(s => {
        const val = +s.dataset.v;
        s.classList.toggle('fas', val <= v);
        s.classList.toggle('far', val > v);
        s.classList.toggle('active', val <= v);
    });
}

// Modal close handlers
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.onclick = function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
            const iframe = this.querySelector('iframe');
            if (iframe) iframe.src = '';
        }
    };
});

document.onkeydown = e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => {
            m.classList.remove('active');
            const iframe = m.querySelector('iframe');
            if (iframe) iframe.src = '';
        });
        document.body.style.overflow = '';
    }
};

// Action Buttons
document.querySelectorAll('[data-action]').forEach(btn => {
    btn.onclick = async function() {
        if (!isLogged) {
            showToast('Faça login primeiro', 'error');
            setTimeout(() => location.href = `${SITE_URL}/auth/login.php`, 1000);
            return;
        }
        
        this.disabled = true;
        const action = this.dataset.action;
        const id = this.dataset.id;
        const url = `${SITE_URL}/api/toggle-${action === 'cart' ? 'cart' : 'wishlist'}.php`;
        
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ jogo_id: id })
            });
            const data = await res.json();
            
            if (data.success) {
                const added = data.action === 'added';
                
                // Update all buttons with same action
                document.querySelectorAll(`[data-action="${action}"][data-id="${id}"]`).forEach(b => {
                    const icon = b.querySelector('i');
                    const span = b.querySelector('span');
                    
                    if (action === 'cart') {
                        b.className = b.className.replace(/btn-(primary|success)/g, '') + (added ? ' btn-success' : ' btn-primary');
                        if (icon) icon.className = `fas ${added ? 'fa-check' : 'fa-cart-plus'}`;
                        if (span) span.textContent = added ? 'No Carrinho' : (b.classList.contains('btn-wishlist-icon') ? '' : 'Comprar');
                    } else {
                        b.classList.toggle('active', added);
                        if (span) span.textContent = added ? 'Na Lista' : 'Lista de Desejos';
                    }
                });
                
                if (action === 'cart') {
                    updateCartCount();
                    // Reload to show finalize button
                    if (added && !document.querySelector('.btn-finalize')) {
                        location.reload();
                    }
                }
                
                showToast(data.message, 'success');
            } else {
                showToast(data.message, 'error');
            }
        } catch (e) {
            showToast('Erro na operação', 'error');
        }
        
        this.disabled = false;
    };
});

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    const i = t.querySelector('i');
    t.className = 'toast ' + type;
    i.className = 'fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function updateCartCount() {
    fetch(`${SITE_URL}/api/get-cart-count.php`)
        .then(r => r.json())
        .then(d => {
            const b = document.querySelector('.cart-count');
            if (b && d.count !== undefined) {
                b.textContent = d.count;
                b.style.display = d.count > 0 ? 'flex' : 'none';
            }
        }).catch(() => {});
}

document.addEventListener('DOMContentLoaded', updateCartCount);
</script>

<?php require_once '../includes/footer.php'; ?>