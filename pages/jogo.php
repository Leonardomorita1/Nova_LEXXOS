<?php
// pages/jogo.php - PlayStation App + Epic Games Style (IMPROVED)
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();
$slug = $_GET['slug'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;

if (empty($slug)) { 
    header('Location: ' . SITE_URL); 
    exit; 
}

// Buscar jogo (sem filtro de status inicialmente)
$stmt = $pdo->prepare("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug, d.logo_url, d.verificado as dev_verificado,
           d.usuario_id as dev_usuario_id
    FROM jogo j 
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
    WHERE j.slug = ?
");
$stmt->execute([$slug]);
$jogo = $stmt->fetch();

if (!$jogo) { 
    header('Location: ' . SITE_URL); 
    exit; 
}

// VERIFICAÇÃO DE PERMISSÃO DE ACESSO
$pode_acessar = false;

if ($jogo['status'] === 'publicado') {
    $pode_acessar = true;
} elseif ($user_id) {
    if ($user_type === 'admin') {
        $pode_acessar = true;
    } elseif ($user_type === 'desenvolvedor' && $jogo['dev_usuario_id'] == $user_id) {
        $pode_acessar = true;
    }
}

if (!$pode_acessar) {
    $_SESSION['error'] = 'Este jogo não está disponível.';
    header('Location: ' . SITE_URL);
    exit;
}

$is_preview = ($jogo['status'] !== 'publicado');

// Verificar arquivo
$stmt = $pdo->prepare("SELECT * FROM arquivo_jogo WHERE jogo_id = ? AND ativo = 1 ORDER BY criado_em DESC LIMIT 1");
$stmt->execute([$jogo['id']]);
$arquivo_info = $stmt->fetch();
$tem_arquivo = (bool)$arquivo_info;

// LÓGICA DE VERIFICAÇÃO DE IDADE
$idade_bloqueada = false;

if ($user_id && !$is_preview) {
    $stmt = $pdo->prepare("SELECT data_nascimento FROM usuario WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario_data = $stmt->fetch();

    if ($usuario_data && $usuario_data['data_nascimento']) {
        $idade = (new DateTime($usuario_data['data_nascimento']))->diff(new DateTime())->y;
        $classificacao = $jogo['classificacao_etaria'];

        if ($classificacao !== 'L' && $idade < (int)$classificacao) {
            $idade_bloqueada = true;
        }
    }
}

// EXIBIÇÃO DO BLOQUEIO DE IDADE (TELA CHEIA)
if ($idade_bloqueada) {
    $page_title = 'Conteúdo Restrito - ' . SITE_NAME;
    require_once '../includes/header.php';
    ?>
    <style>
        .age-restriction-page {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-primary);
            overflow: hidden;
        }
        .age-restriction-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('<?= SITE_URL . ($jogo['imagem_banner'] ?: $jogo['imagem_capa']) ?>') center/cover no-repeat;
            filter: blur(30px) brightness(0.2);
            transform: scale(1.1);
        }
        .age-restriction-page::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at center, rgba(220, 53, 69, 0.1) 0%, transparent 70%);
        }
        .age-card {
            position: relative;
            z-index: 1;
            background: rgba(30, 31, 32, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 48px 40px;
            max-width: 440px;
            width: 90%;
            text-align: center;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            animation: ageCardIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes ageCardIn {
            from { opacity: 0; transform: scale(0.9) translateY(30px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .age-icon {
            width: 80px;
            height: 80px;
            background: rgba(220, 53, 69, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
            color: var(--danger);
            box-shadow: 0 0 40px rgba(220, 53, 69, 0.2);
        }
        .age-card h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 16px 0;
        }
        .age-card p {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.7;
            margin: 0 0 32px 0;
        }
        .age-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--danger);
            color: white;
            font-weight: 800;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 1rem;
            margin: 0 4px;
        }
        .age-game-info {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background: var(--bg-primary);
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: left;
        }
        .age-game-info img {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
        }
        .age-game-info .info h4 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0 0 4px 0;
            color: var(--text-primary);
        }
        .age-game-info .info span {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .age-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .age-btn:hover {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
    </style>

    <div class="age-restriction-page">
        <div class="age-card">
            <div class="age-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h1>Conteúdo Restrito</h1>
            <div class="age-game-info">
                <img src="<?= SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png') ?>" alt="">
                <div class="info">
                    <h4><?= sanitize($jogo['titulo']) ?></h4>
                    <span><?= sanitize($jogo['nome_estudio']) ?></span>
                </div>
            </div>
            <p>
                Este título possui classificação indicativa para maiores de 
                <span class="age-badge"><?= $jogo['classificacao_etaria'] ?>+</span>
                anos. Sua conta não atende aos requisitos de idade.
            </p>
            <a href="<?= SITE_URL ?>/" class="age-btn">
                <i class="fas fa-arrow-left"></i> 
                Voltar para a Loja
            </a>
        </div>
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

// Incrementar views (apenas para jogos publicados)
if (!$is_preview) {
    $pdo->prepare("UPDATE jogo SET total_visualizacoes = total_visualizacoes + 1 WHERE id = ?")->execute([$jogo['id']]);
}

// Buscar dados relacionados
$categorias = $pdo->prepare("SELECT c.* FROM categoria c JOIN jogo_categoria jc ON c.id = jc.categoria_id WHERE jc.jogo_id = ?");
$categorias->execute([$jogo['id']]);
$categorias = $categorias->fetchAll();

$tags = $pdo->prepare("SELECT t.* FROM tag t JOIN jogo_tag jt ON t.id = jt.tag_id WHERE jt.jogo_id = ?");
$tags->execute([$jogo['id']]);
$tags = $tags->fetchAll();

$plataformas = $pdo->prepare("SELECT p.* FROM plataforma p JOIN jogo_plataforma jp ON p.id = jp.plataforma_id WHERE jp.jogo_id = ?");
$plataformas->execute([$jogo['id']]);
$plataformas = $plataformas->fetchAll();

$imagens = $pdo->prepare("SELECT * FROM jogo_imagens WHERE jogo_id = ? ORDER BY ordem");
$imagens->execute([$jogo['id']]);
$imagens = $imagens->fetchAll();

// Avaliações
$avaliacoes = $pdo->prepare("SELECT a.*, u.nome_usuario, u.avatar_url FROM avaliacao a LEFT JOIN usuario u ON a.usuario_id = u.id WHERE a.jogo_id = ? ORDER BY a.criado_em DESC");
$avaliacoes->execute([$jogo['id']]);
$avaliacoes = $avaliacoes->fetchAll();

$rating_dist = [5=>0,4=>0,3=>0,2=>0,1=>0];
foreach ($avaliacoes as $av) if (isset($rating_dist[$av['nota']])) $rating_dist[$av['nota']]++;
$total_reviews = count($avaliacoes);

$my_review = null;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM avaliacao WHERE jogo_id = ? AND usuario_id = ?");
    $stmt->execute([$jogo['id'], $user_id]);
    $my_review = $stmt->fetch();
}

// Jogos relacionados (apenas publicados)
$cat_ids = array_column($categorias, 'id');
$related_sql = "SELECT DISTINCT j.*, d.nome_estudio FROM jogo j 
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
    LEFT JOIN jogo_categoria jc ON j.id = jc.jogo_id 
    WHERE j.id != ? AND j.status = 'publicado' AND (j.desenvolvedor_id = ?" . 
    (!empty($cat_ids) ? " OR jc.categoria_id IN (" . implode(',', array_fill(0, count($cat_ids), '?')) . ")" : "") . 
    ") ORDER BY j.nota_media DESC LIMIT 8";
$params = [$jogo['id'], $jogo['desenvolvedor_id'], ...$cat_ids];
$jogos_relacionados = $pdo->prepare($related_sql);
$jogos_relacionados->execute($params);
$jogos_relacionados = $jogos_relacionados->fetchAll();

// Preços
$preco_final = ($jogo['em_promocao'] && $jogo['preco_promocional_centavos']) ? $jogo['preco_promocional_centavos'] : $jogo['preco_centavos'];
$desconto = calculateDiscount($jogo['preco_centavos'], $jogo['preco_promocional_centavos'], $jogo['em_promocao']);
$is_free = $preco_final == 0;

function getYoutubeId($url) { 
    preg_match('/(?:embed\/|v=|youtu\.be\/)([^&?\/]+)/', $url, $m); 
    return $m[1] ?? ''; 
}

// Preparar mídia para o carrossel
$media_items = [];
if ($jogo['video_trailer']) {
    $media_items[] = [
        'type' => 'video',
        'url' => $jogo['video_trailer'],
        'thumb' => 'https://img.youtube.com/vi/' . getYoutubeId($jogo['video_trailer']) . '/mqdefault.jpg'
    ];
}
foreach ($imagens as $img) {
    $media_items[] = [
        'type' => 'image',
        'url' => SITE_URL . $img['imagem'],
        'thumb' => SITE_URL . $img['imagem']
    ];
}

$page_title = $jogo['titulo'] . ' - ' . SITE_NAME;
require_once '../includes/header.php';

?>

<style>


/* ========== BASE ========== */
.game-page {
    background: var(--bg-primary);
    min-height: 100vh;
    padding-bottom: 60px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* ========== PREVIEW BANNER ========== */
.preview-banner {
    background: linear-gradient(135deg, var(--warning), #ff9800);
    color: #000;
    padding: 12px 20px;
    text-align: center;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    position: sticky;
    top: 0;
    z-index: 100;
}

/* ========== MOBILE HEADER (PS App Style) ========== */
.mobile-header {
    display: none;
    position: relative;
    z-index: 10;
    background: var(--bg-primary);
}

.mobile-header-bg {
    position: absolute;
    inset: 0;
    background: url('<?= SITE_URL . ($jogo['imagem_banner'] ?: $jogo['imagem_capa']) ?>') center/cover;
    opacity: 0.15;
    filter: blur(20px);
    z-index: -1;
}

.mobile-game-info {
    position: relative;
    display: flex;
    gap: 16px;
    padding: 20px 16px;
}

.mobile-cover {
    width: 100px;
    height: 100px;
    border-radius: 16px;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
}

.mobile-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mobile-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
}

.mobile-details .title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 6px 0;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.mobile-details .dev {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.mobile-details .rating {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
}

.mobile-details .rating i {
    color: var(--warning);
}

.mobile-details .rating span {
    color: var(--text-secondary);
}

/* ========== MOBILE ACTION BAR (PS App Style) ========== */
.mobile-action-bar {
    display: none;
    padding: 16px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
}

.mobile-price-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.mobile-price-row .discount {
    background: var(--success);
    color: #fff;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 700;
}

.mobile-price-row .old-price {
    color: var(--text-secondary);
    text-decoration: line-through;
    font-size: 0.9rem;
}

.mobile-price-row .price {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.mobile-price-row .price.free {
    color: var(--success);
}

.mobile-owned-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: rgba(40, 167, 69, 0.15);
    border: 1px solid var(--success);
    border-radius: 10px;
    color: var(--success);
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 14px;
}

.mobile-unavailable {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid var(--warning);
    border-radius: 10px;
    color: var(--warning);
    font-size: 0.9rem;
    margin-bottom: 14px;
}

.mobile-buttons {
    display: flex;
    gap: 12px;
    align-items: center;
}

.mobile-btn-main {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}

.mobile-btn-main.primary {
    background: var(--accent);
    color: #fff;
}

.mobile-btn-main.success {
    background: var(--success);
    color: #fff;
}

.mobile-btn-main.outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-primary);
}

.mobile-btn-circle {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
    text-decoration: none;
}

.mobile-btn-circle.wishlist {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.mobile-btn-circle.wishlist.active {
    background: rgba(220, 53, 69, 0.15);
    border-color: var(--danger);
    color: var(--danger);
}

.mobile-btn-circle.download {
    background: var(--success);
    color: #fff;
}

.mobile-btn-circle.library {
    background: var(--bg-tertiary);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

/* ========== MOBILE CAROUSEL ========== */
.mobile-carousel {
    display: none;
    position: relative;
    z-index: 5;
    background: var(--bg-primary);
}

.carousel-container {
    position: relative;
    overflow: hidden;
}

.carousel-track {
    display: flex;
    transition: transform 0.3s ease;
    touch-action: pan-y;
}

.carousel-slide {
    flex: 0 0 100%;
    aspect-ratio: 16/9;
    background: var(--bg-secondary);
}

.carousel-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.carousel-slide iframe {
    width: 100%;
    height: 100%;
    border: 0;
}

.carousel-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 14px;
    background: var(--bg-primary);
}

.carousel-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--border);
    transition: all 0.3s;
    cursor: pointer;
}

.carousel-dot.active {
    background: var(--accent);
    width: 24px;
    border-radius: 4px;
}

/* ========== DESKTOP HERO ========== */
.desktop-hero {
    position: relative;
    height: 450px;
    overflow: hidden;
}

.hero-bg {
    position: absolute;
    inset: 0;
    background: url('<?= SITE_URL . ($jogo['imagem_banner'] ?: $jogo['imagem_capa']) ?>') center/cover;
}

.hero-bg::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, var(--bg-primary) 0%, rgba(19,19,20,0.7) 50%, rgba(19,19,20,0.4) 100%);
}

/* ========== LAYOUT ========== */
.main-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 32px;
    margin-top: -160px;
    position: relative;
    z-index: 2;
}

.main-content {
    min-width: 0;
    padding: 0;
}

.sidebar {
    position: sticky;
    top: 24px;
    align-self: start;
}

/* ========== DESKTOP MEDIA GALLERY ========== */
.media-gallery {
    background: var(--bg-secondary);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 24px;
    border: 1px solid var(--border);
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
    border: 0;
}

/* Thumbnail Navigation */
.media-nav {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    background: var(--bg-tertiary);
}

.nav-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
}

.nav-btn:hover:not(:disabled) {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}

.nav-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.media-thumbs-wrapper {
    flex: 1;
    overflow: hidden;
    position: relative;
}

.media-thumbs {
    display: flex;
    gap: 10px;
    transition: transform 0.3s ease;
}

.media-thumb {
    flex: 0 0 140px;
    height: 80px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    position: relative;
    border: 2px solid transparent;
    opacity: 0.6;
    transition: all 0.2s;
}

.media-thumb:hover,
.media-thumb.active {
    opacity: 1;
    border-color: var(--accent);
}

.media-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.media-thumb.video::after {
    content: '\f04b';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.5);
    color: #fff;
    font-size: 18px;
}

/* ========== GAME INFO ========== */
.game-info {
    padding: 28px 0;
}

.game-title {
    font-size: 2.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 16px 0;
    line-height: 1.2;
}

.game-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.meta-item i {
    color: var(--accent);
}

.meta-item a {
    color: var(--accent);
    text-decoration: none;
}

.meta-item a:hover {
    text-decoration: underline;
}

.rating-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-secondary);
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.rating-badge i {
    color: var(--warning);
}

.rating-badge strong {
    color: var(--text-primary);
}

/* ========== TAGS & CATEGORIES ========== */
.categories-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}

.category-tag {
    background: var(--accent);
    color: #fff;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: filter 0.2s;
}

.category-tag:hover {
    filter: brightness(1.15);
}

.tags-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tag-item {
    background: var(--bg-secondary);
    color: var(--text-secondary);
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    border: 1px solid var(--border);
    transition: all 0.2s;
}

.tag-item:hover {
    color: var(--text-primary);
    border-color: var(--accent);
}

/* ========== CONTENT BLOCKS ========== */
.content-block {
    background: var(--bg-secondary);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
}

.block-header {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 16px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
}

.block-header i {
    color: var(--accent);
}

.description-text {
    color: var(--text-secondary);
    line-height: 1.8;
    font-size: 0.95rem;
    white-space: pre-wrap;
}

.description-text.collapsed {
    max-height: 150px;
    overflow: hidden;
    position: relative;
}

.description-text.collapsed::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: linear-gradient(transparent, var(--bg-secondary));
}

.expand-btn {
    background: none;
    border: none;
    color: var(--accent);
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 12px 0 0;
    font-weight: 500;
}

.expand-btn:hover {
    text-decoration: underline;
}

/* ========== SIDEBAR CARDS ========== */
.sidebar-card {
    background: var(--bg-secondary);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 16px;
    border: 1px solid var(--border);
}

.sidebar-cover {
    aspect-ratio: 1/1;
    position: relative;
    overflow: hidden;
}

.sidebar-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.promo-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: var(--success);
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}

.owned-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: var(--accent);
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}

.sidebar-body {
    padding: 20px;
}

/* ========== PRICE ========== */
.price-section {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.price-main {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
}

.price-main.free {
    color: var(--success);
}

.price-old {
    font-size: 1rem;
    color: var(--text-secondary);
    text-decoration: line-through;
}

.discount-badge {
    background: var(--success);
    color: #fff;
    padding: 4px 10px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 0.85rem;
}

/* ========== OWNED STATE ========== */
.owned-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px;
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid var(--success);
    border-radius: 10px;
    color: var(--success);
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 16px;
}

/* ========== UNAVAILABLE STATE ========== */
.unavailable-state {
    text-align: center;
    padding: 24px;
    background: rgba(255, 193, 7, 0.08);
    border: 1px solid var(--warning);
    border-radius: 10px;
    margin-bottom: 16px;
}

.unavailable-state i {
    font-size: 36px;
    color: var(--warning);
    margin-bottom: 12px;
}

.unavailable-state h4 {
    color: var(--warning);
    font-size: 1rem;
    margin: 0 0 6px 0;
}

.unavailable-state p {
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin: 0;
}

/* ========== BUTTONS ========== */
.btn-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 20px;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-primary {
    background: var(--accent);
    color: #fff;
}

.btn-primary:hover {
    filter: brightness(1.1);
}

.btn-success {
    background: var(--success);
    color: #fff;
}

.btn-success:hover {
    filter: brightness(1.1);
}

.btn-outline {
    background: transparent;
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-outline:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.btn-outline.wishlist.active {
    border-color: var(--danger);
    color: var(--danger);
}

/* ========== PLATFORMS ========== */
.platforms-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-top: 16px;
    margin-top: 16px;
    border-top: 1px solid var(--border);
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.platforms-icons {
    display: flex;
    gap: 12px;
    font-size: 1.2rem;
}

/* ========== INFO CARD ========== */
.info-card {
    background: var(--bg-secondary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
    border: 1px solid var(--border);
}

.info-card h4 {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
    margin: 0 0 16px 0;
}

.info-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
}

.info-row .label {
    color: var(--text-secondary);
}

.info-row .value {
    font-weight: 500;
    color: var(--text-primary);
}

/* ========== DEVELOPER LINK ========== */
.dev-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px;
    background: var(--bg-tertiary);
    border-radius: 10px;
    text-decoration: none;
    transition: background 0.2s;
}

.dev-link:hover {
    background: #353637;
}

.dev-link img {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    object-fit: cover;
}

.dev-link .name {
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}

.dev-link .name i {
    color: var(--accent);
    font-size: 0.75rem;
}

.dev-link .sub {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

/* ========== REQUIREMENTS TABS ========== */
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
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.req-tab.active {
    background: var(--accent);
    color: #fff;
}

.req-text {
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.7;
    white-space: pre-wrap;
}

/* ========== REVIEWS ========== */
.reviews-summary {
    display: flex;
    gap: 24px;
    padding: 20px;
    background: var(--bg-tertiary);
    border-radius: 12px;
    margin-bottom: 20px;
}

.reviews-score {
    text-align: center;
    min-width: 100px;
}

.reviews-score .number {
    font-size: 3rem;
    font-weight: 700;
    color: var(--accent);
    line-height: 1;
}

.reviews-score .stars {
    display: flex;
    justify-content: center;
    gap: 2px;
    margin: 8px 0;
    color: var(--warning);
    font-size: 0.9rem;
}

.reviews-score .count {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.reviews-bars {
    flex: 1;
}

.bar-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
    font-size: 0.8rem;
    color: var(--text-secondary);
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

.review-card {
    padding: 20px;
    background: var(--bg-tertiary);
    border-radius: 12px;
    margin-bottom: 12px;
}

.review-card.mine {
    border: 1px solid var(--accent);
    position: relative;
}

.review-card.mine::before {
    content: 'Sua Avaliação';
    position: absolute;
    top: -10px;
    left: 16px;
    background: var(--accent);
    color: #fff;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}

.review-header {
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
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
}

.review-author .name {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.review-author .meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
}

.review-author .meta .stars {
    color: var(--warning);
}

.review-author .meta .date {
    color: var(--text-secondary);
}

.review-actions {
    display: flex;
    gap: 6px;
}

.review-actions button {
    padding: 6px 10px;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 4px;
    color: var(--text-secondary);
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}

.review-actions button:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.review-actions button.delete:hover {
    border-color: var(--danger);
    color: var(--danger);
}

.review-text {
    font-size: 0.9rem;
    color: var(--text-secondary);
    line-height: 1.7;
}

.write-review-btn {
    width: 100%;
    padding: 16px;
    background: var(--bg-tertiary);
    border: 1px dashed var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.2s;
    margin-bottom: 16px;
}

.write-review-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.no-reviews {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.no-reviews i {
    font-size: 48px;
    opacity: 0.3;
    margin-bottom: 12px;
}

/* ========== RELATED GAMES ========== */
.related-section {
    padding: 48px 0;
}

.related-section h2 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 24px 0;
}

.related-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.related-card {
    background: var(--bg-secondary);
    border-radius: 12px;
    overflow: hidden;
    text-decoration: none;
    transition: all 0.3s;
    border: 1px solid transparent;
}

.related-card:hover {
    transform: translateY(-6px);
    border-color: var(--accent);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
}

.related-cover {
    aspect-ratio: 1/1;
    overflow: hidden;
}

.related-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.related-card:hover .related-cover img {
    transform: scale(1.05);
}

.related-info {
    padding: 16px;
}

.related-info h4 {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 6px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.related-info .dev {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-bottom: 10px;
}

.related-price {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.related-price .discount {
    background: var(--success);
    color: #fff;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
}

.related-price .old {
    color: var(--text-secondary);
    text-decoration: line-through;
    font-size: 0.8rem;
}

.related-price .current {
    font-weight: 600;
    color: var(--text-primary);
}

.related-price .free {
    color: var(--success);
    font-weight: 600;
}

/* ========== MODAL ========== */
.modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.9);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: var(--bg-secondary);
    border-radius: 16px;
    width: 100%;
    max-width: 480px;
    animation: modalIn 0.25s ease;
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

.modal-header h3 {
    font-size: 1.1rem;
    margin: 0;
    color: var(--text-primary);
}

.modal-close {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--border);
    color: var(--text-primary);
}

.modal-body {
    padding: 24px;
}

.star-selector {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 24px;
}

.star-selector i {
    font-size: 2.5rem;
    color: var(--border);
    cursor: pointer;
    transition: all 0.15s;
}

.star-selector i:hover,
.star-selector i.active {
    color: var(--warning);
    transform: scale(1.15);
}

.review-textarea {
    width: 100%;
    min-height: 120px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px;
    color: var(--text-primary);
    font-size: 0.95rem;
    font-family: inherit;
    resize: vertical;
    box-sizing: border-box;
}

.review-textarea:focus {
    outline: none;
    border-color: var(--accent);
}

.modal-footer {
    display: flex;
    gap: 12px;
    padding: 20px 24px;
    border-top: 1px solid var(--border);
}

.modal-footer .btn {
    flex: 1;
}

/* ========== TOAST ========== */
.toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    padding: 14px 24px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    z-index: 10000;
    opacity: 0;
    transition: all 0.3s;
}

.toast.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}

.toast.success {
    border-color: var(--success);
}

.toast.success i {
    color: var(--success);
}

.toast.error {
    border-color: var(--danger);
}

.toast.error i {
    color: var(--danger);
}

/* ========== RESPONSIVE ========== */
@media (max-width: 1024px) {
    .main-layout {
        grid-template-columns: 1fr 340px;
        gap: 24px;
    }
    
    .related-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    /* Esconder elementos desktop */
    .desktop-hero,
    .media-gallery,
    .game-title {
        display: none;
    }
    
    /* Mostrar elementos mobile */
    .mobile-header,
    .mobile-action-bar,
    .mobile-carousel {
        display: block;
    }
    
    .main-layout {
        display: block;
        margin-top: 0;
    }
    
    .sidebar {
        display: none;
    }
    
    .game-info {
        padding: 20px 16px;
    }
    
    .container {
        padding: 0;
    }
    
    .main-content .container,
    .related-section {
        padding: 0 16px;
    }
    
    .content-block {
        border-radius: 0;
        border-left: none;
        border-right: none;
        margin-bottom: 0;
        margin-top: -1px;
    }
    
    .related-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .reviews-summary {
        flex-direction: column;
        text-align: center;
    }
    
    .related-info {
        padding: 12px;
    }
    
    .related-info h4 {
        font-size: 0.85rem;
    }
    
    .toast {
        bottom: 80px;
        left: 16px;
        right: 16px;
        transform: translateX(0) translateY(100px);
    }
    
    .toast.show {
        transform: translateX(0) translateY(0);
    }
}

@media (max-width: 480px) {
    .mobile-cover {
        width: 85px;
        height: 85px;
    }
    
    .mobile-details .title {
        font-size: 1.1rem;
    }
    
    .mobile-btn-main {
        padding: 12px 16px;
        font-size: 0.9rem;
    }
    
    .mobile-btn-circle {
        width: 48px;
        height: 48px;
        font-size: 1.1rem;
    }
    
    .reviews-summary {
        padding: 16px;
    }
    
    .reviews-score .number {
        font-size: 2.5rem;
    }
    
    .related-grid {
        gap: 10px;
    }
}
</style>

<div class="game-page">
    <!-- Preview Banner -->
    <?php if ($is_preview): ?>
    <div class="preview-banner">
        <i class="fas fa-eye"></i>
        <span>Modo de Visualização - Este jogo ainda não está publicado</span>
        <span style="background: rgba(0,0,0,0.2); padding: 4px 10px; border-radius: 4px; margin-left: 10px;">
            Status: <?= ucfirst(str_replace('_', ' ', $jogo['status'])) ?>
        </span>
    </div>
    <?php endif; ?>

    <!-- ==================== MOBILE SECTION ==================== -->
    
    <!-- Mobile Header (PS App Style) -->
    <div class="mobile-header">
        <div class="mobile-header-bg"></div>
        <div class="mobile-game-info">
            <div class="mobile-cover">
                <img src="<?= SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png') ?>" alt="">
            </div>
            <div class="mobile-details">
                <h1 class="title"><?= sanitize($jogo['titulo']) ?></h1>
                <div class="dev"><?= sanitize($jogo['nome_estudio']) ?></div>
                <div class="rating">
                    <i class="fas fa-star"></i>
                    <strong><?= number_format($jogo['nota_media'], 1) ?></strong>
                    <span>(<?= $total_reviews ?>)</span>
                </div>
            </div>
        </div>
    </div>
    <br><br><br>

    

    <!-- Mobile Action Bar (PS App Style) -->
    <div class="mobile-action-bar">
        <?php if (!$tem_arquivo): ?>
            <!-- Jogo não disponível -->
            <div class="mobile-unavailable">
                <i class="fas fa-clock"></i>
                <span>Em Breve - Ainda não disponível para download</span>
            </div>
            <div class="mobile-buttons">
                <?php if ($user_id): ?>
                    <button class="mobile-btn-main outline" disabled style="flex:1;">
                        <i class="fas fa-clock"></i> Indisponível
                    </button>
                    <button class="mobile-btn-circle wishlist <?= $in_wishlist ? 'active' : '' ?>" 
                            data-action="wishlist" data-id="<?= $jogo['id'] ?>" title="Lista de Desejos">
                        <i class="fas fa-heart"></i>
                    </button>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/auth/login.php" class="mobile-btn-main primary" style="flex:1;">
                        <i class="fas fa-sign-in-alt"></i> Entrar
                    </a>
                <?php endif; ?>
            </div>

        <?php elseif ($in_library): ?>
            <!-- Usuário já possui o jogo -->
            <div class="mobile-owned-badge">
                <i class="fas fa-check-circle"></i> Você possui este jogo
            </div>
            <div class="mobile-buttons">
                <a href="<?= SITE_URL ?>/user/biblioteca.php" class="mobile-btn-main outline" style="flex:1;">
                    <i class="fas fa-gamepad"></i> Biblioteca
                </a>
                <a href="<?= SITE_URL ?>/user/download-jogo.php?jogo_id=<?= $jogo['id'] ?>" 
                   class="mobile-btn-circle download" title="Baixar">
                    <i class="fas fa-download"></i>
                </a>
            </div>

        <?php else: ?>
            <!-- Jogo disponível para compra -->
            <div class="mobile-price-row">
                <?php if ($desconto > 0): ?>
                    <span class="discount">-<?= $desconto ?>%</span>
                    <span class="old-price"><?= formatPrice($jogo['preco_centavos']) ?></span>
                <?php endif; ?>
                <span class="price <?= $is_free ? 'free' : '' ?>">
                    <?= $is_free ? 'Gratuito' : formatPrice($preco_final) ?>
                </span>
            </div>
            <div class="mobile-buttons">
                <?php if ($user_id): ?>
                    <button class="mobile-btn-main <?= $in_cart ? 'success' : 'primary' ?>" 
                            data-action="cart" data-id="<?= $jogo['id'] ?>" style="flex:1;">
                        <i class="fas <?= $in_cart ? 'fa-check' : 'fa-cart-plus' ?>"></i>
                        <span><?= $in_cart ? 'No Carrinho' : 'Adicionar ao Carrinho' ?></span>
                    </button>
                    <button class="mobile-btn-circle wishlist <?= $in_wishlist ? 'active' : '' ?>" 
                            data-action="wishlist" data-id="<?= $jogo['id'] ?>" title="Lista de Desejos">
                        <i class="fas fa-heart"></i>
                    </button>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/auth/login.php" class="mobile-btn-main primary" style="flex:1;">
                        <i class="fas fa-sign-in-alt"></i> Entrar para Comprar
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Mobile Carousel -->
    <?php if (count($media_items) > 0): ?>
    <div class="mobile-carousel">
        <div class="carousel-container">
            <div class="carousel-track" id="carouselTrack">
                <?php foreach ($media_items as $index => $media): ?>
                <div class="carousel-slide" data-index="<?= $index ?>">
                    <?php if ($media['type'] === 'video'): ?>
                        <iframe src="<?= $media['url'] ?>" allowfullscreen loading="lazy"></iframe>
                    <?php else: ?>
                        <img src="<?= $media['url'] ?>" alt="" loading="lazy">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (count($media_items) > 1): ?>
        <div class="carousel-dots" id="carouselDots">
            <?php foreach ($media_items as $index => $media): ?>
            <div class="carousel-dot <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>"></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ==================== DESKTOP SECTION ==================== -->
    
    <!-- Desktop Hero -->
    <div class="desktop-hero">
        <div class="hero-bg"></div>
    </div>

    <div class="container">
        <div class="main-layout">
            <!-- Main Content -->
            <div class="main-content">
                <!-- Desktop Media Gallery with Navigation Buttons -->
                <?php if (count($media_items) > 0): ?>
                <div class="media-gallery">
                    <div class="media-main" id="mediaMain">
                        <?php if ($media_items[0]['type'] === 'video'): ?>
                            <iframe src="<?= $media_items[0]['url'] ?>" allowfullscreen></iframe>
                        <?php else: ?>
                            <img src="<?= $media_items[0]['url'] ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <?php if (count($media_items) > 1): ?>
                    <div class="media-nav">
                        <button class="nav-btn" id="navPrev" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="media-thumbs-wrapper">
                            <div class="media-thumbs" id="mediaThumbs">
                                <?php foreach ($media_items as $index => $media): ?>
                                <div class="media-thumb <?= $media['type'] === 'video' ? 'video' : '' ?> <?= $index === 0 ? 'active' : '' ?>" 
                                     data-index="<?= $index ?>"
                                     data-type="<?= $media['type'] ?>" 
                                     data-url="<?= $media['url'] ?>">
                                    <img src="<?= $media['thumb'] ?>" alt="">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button class="nav-btn" id="navNext">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Game Info -->
                <div class="game-info">
                    <h1 class="game-title"><?= sanitize($jogo['titulo']) ?></h1>
                    
                    <div class="game-meta">
                        <div class="rating-badge">
                            <i class="fas fa-star"></i>
                            <strong><?= number_format($jogo['nota_media'], 1) ?></strong>
                            <span style="color: var(--text-secondary);">(<?= $total_reviews ?>)</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-building"></i>
                            <a href="<?= SITE_URL ?>/pages/desenvolvedor.php?slug=<?= $jogo['dev_slug'] ?>"><?= sanitize($jogo['nome_estudio']) ?></a>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <?= date('d/m/Y', strtotime($jogo['data_lancamento'] ?? $jogo['criado_em'])) ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-download"></i>
                            <?= number_format($jogo['total_vendas'], 0, ',', '.') ?>
                        </div>
                        <?php if ($jogo['classificacao_etaria'] !== 'L'): ?>
                        <div class="meta-item">
                            <i class="fas fa-shield-alt"></i>
                            <?= $jogo['classificacao_etaria'] ?>+
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($categorias)): ?>
                    <div class="categories-list">
                        <?php foreach ($categorias as $c): ?>
                        <a href="<?= SITE_URL ?>/pages/categoria.php?slug=<?= $c['slug'] ?>" class="category-tag"><?= sanitize($c['nome']) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($tags)): ?>
                    <div class="tags-list">
                        <?php foreach ($tags as $t): ?>
                        <span class="tag-item"><?= sanitize($t['nome']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="content-block">
                    <div class="block-header">
                        <i class="fas fa-info-circle"></i>
                        Sobre o Jogo
                    </div>
                    <div class="description-text <?= strlen($jogo['descricao_completa'] ?? '') > 400 ? 'collapsed' : '' ?>" id="descriptionText">
                        <?= nl2br(sanitize($jogo['descricao_completa'] ?: $jogo['descricao_curta'])) ?>
                    </div>
                    <?php if (strlen($jogo['descricao_completa'] ?? '') > 400): ?>
                    <button class="expand-btn" onclick="toggleDescription()">
                        <i class="fas fa-chevron-down" id="expandIcon"></i>
                        <span id="expandText">Ler mais</span>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Reviews -->
                <div class="content-block" id="reviews">
                    <div class="block-header">
                        <i class="fas fa-star"></i>
                        Avaliações
                    </div>

                    <div class="reviews-summary">
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
                            <?php for ($r = 5; $r >= 1; $r--): 
                                $pct = $total_reviews > 0 ? ($rating_dist[$r] / $total_reviews) * 100 : 0; 
                            ?>
                            <div class="bar-row">
                                <span><?= $r ?></span>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width: <?= $pct ?>%"></div>
                                </div>
                                <span><?= $rating_dist[$r] ?></span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <?php if ($in_library): ?>
                        <?php if ($my_review): ?>
                        <div class="review-card mine">
                            <div class="review-header">
                                <div class="review-author">
                                    <img src="<?= getAvatar($usuario_data['avatar_url'] ?? null) ?>" alt="">
                                    <div>
                                        <div class="name">Você</div>
                                        <div class="meta">
                                            <span class="stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?= $i <= $my_review['nota'] ? 'fas' : 'far' ?> fa-star"></i>
                                                <?php endfor; ?>
                                            </span>
                                            <span class="date"><?= date('d/m/Y', strtotime($my_review['criado_em'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="review-actions">
                                    <button onclick="openReviewModal(<?= $my_review['nota'] ?>, '<?= addslashes(htmlspecialchars($my_review['comentario'])) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="<?= SITE_URL ?>/api/avaliar.php" method="POST" style="display: inline;" onsubmit="return confirm('Remover avaliação?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="jogo_id" value="<?= $jogo['id'] ?>">
                                        <button type="submit" class="delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <div class="review-text"><?= nl2br(sanitize($my_review['comentario'])) ?></div>
                        </div>
                        <?php else: ?>
                        <button class="write-review-btn" onclick="openReviewModal()">
                            <i class="fas fa-pen"></i> Escrever uma avaliação
                        </button>
                        <?php endif; ?>
                    <?php elseif ($user_id): ?>
                    <div style="text-align: center; padding: 16px; background: var(--bg-tertiary); border-radius: 10px; color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 16px;">
                        <i class="fas fa-info-circle"></i> Adquira o jogo para deixar sua avaliação
                    </div>
                    <?php endif; ?>

                    <?php 
                    $has_other_reviews = false;
                    foreach ($avaliacoes as $rev): 
                        if ($rev['usuario_id'] == $user_id) continue;
                        $has_other_reviews = true;
                    ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="review-author">
                                <img src="<?= getAvatar($rev['avatar_url']) ?>" alt="">
                                <div>
                                    <div class="name"><?= sanitize($rev['nome_usuario']) ?></div>
                                    <div class="meta">
                                        <span class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="<?= $i <= $rev['nota'] ? 'fas' : 'far' ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </span>
                                        <span class="date"><?= date('d/m/Y', strtotime($rev['criado_em'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="review-text"><?= nl2br(sanitize($rev['comentario'])) ?></div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (!$has_other_reviews && !$my_review): ?>
                    <div class="no-reviews">
                        <i class="fas fa-comment-slash"></i>
                        <p>Nenhuma avaliação ainda</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar (Desktop Only) -->
            <div class="sidebar">
                <!-- Purchase Card -->
                <div class="sidebar-card">
                    <div class="sidebar-cover">
                        <img src="<?= SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png') ?>" alt="">
                        <?php if ($in_library): ?>
                            <span class="owned-badge"><i class="fas fa-check"></i> Na Biblioteca</span>
                        <?php elseif ($desconto > 0): ?>
                            <span class="promo-badge">-<?= $desconto ?>%</span>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-body">
                        <?php if (!$tem_arquivo): ?>
                            <div class="unavailable-state">
                                <i class="fas fa-clock"></i>
                                <h4>Em Breve</h4>
                                <p>Ainda não disponível para download</p>
                            </div>
                            <div class="btn-group">
                                <?php if ($user_id): ?>
                                <button class="btn btn-outline wishlist <?= $in_wishlist ? 'active' : '' ?>" data-action="wishlist" data-id="<?= $jogo['id'] ?>">
                                    <i class="fas fa-heart"></i>
                                    <span><?= $in_wishlist ? 'Na Lista de Desejos' : 'Adicionar à Lista' ?></span>
                                </button>
                                <?php else: ?>
                                <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Entrar
                                </a>
                                <?php endif; ?>
                            </div>

                        <?php elseif ($in_library): ?>
                            <div class="owned-state">
                                <i class="fas fa-check-circle"></i> Você possui este jogo
                            </div>
                            <div class="btn-group">
                                <a href="<?= SITE_URL ?>/user/download-jogo.php?jogo_id=<?= $jogo['id'] ?>" class="btn btn-success">
                                    <i class="fas fa-download"></i> Baixar
                                </a>
                                <a href="<?= SITE_URL ?>/user/biblioteca.php" class="btn btn-outline">
                                    <i class="fas fa-gamepad"></i> Biblioteca
                                </a>
                            </div>

                        <?php else: ?>
                            <div class="price-section">
                                <?php if ($desconto > 0): ?>
                                    <span class="discount-badge">-<?= $desconto ?>%</span>
                                    <span class="price-old"><?= formatPrice($jogo['preco_centavos']) ?></span>
                                <?php endif; ?>
                                <span class="price-main <?= $is_free ? 'free' : '' ?>">
                                    <?= $is_free ? 'Gratuito' : formatPrice($preco_final) ?>
                                </span>
                            </div>
                            <div class="btn-group">
                                <?php if ($user_id): ?>
                                    <button class="btn <?= $in_cart ? 'btn-success' : 'btn-primary' ?>" data-action="cart" data-id="<?= $jogo['id'] ?>">
                                        <i class="fas <?= $in_cart ? 'fa-check' : 'fa-cart-plus' ?>"></i>
                                        <span><?= $in_cart ? 'No Carrinho' : 'Adicionar ao Carrinho' ?></span>
                                    </button>
                                    <?php if ($in_cart): ?>
                                    <a href="<?= SITE_URL ?>/user/carrinho.php" class="btn btn-outline">
                                        <i class="fas fa-shopping-bag"></i> Finalizar Compra
                                    </a>
                                    <?php endif; ?>
                                    <button class="btn btn-outline wishlist <?= $in_wishlist ? 'active' : '' ?>" data-action="wishlist" data-id="<?= $jogo['id'] ?>">
                                        <i class="fas fa-heart"></i>
                                        <span><?= $in_wishlist ? 'Na Lista' : 'Lista de Desejos' ?></span>
                                    </button>
                                <?php else: ?>
                                    <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Entrar para Comprar
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($plataformas)): ?>
                        <div class="platforms-row">
                            <span>Plataformas</span>
                            <div class="platforms-icons">
                                <?php foreach ($plataformas as $p): ?>
                                <i class="<?= $p['icone'] ?? 'fas fa-desktop' ?>" title="<?= $p['nome'] ?>"></i>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Developer -->
                <div class="info-card">
                    <h4>Desenvolvedor</h4>
                    <a href="<?= SITE_URL ?>/pages/desenvolvedor.php?slug=<?= $jogo['dev_slug'] ?>" class="dev-link">
                        <img src="<?= SITE_URL . ($jogo['logo_url'] ?: '/assets/images/default-dev.png') ?>" alt="">
                        <div>
                            <div class="name">
                                <?= sanitize($jogo['nome_estudio']) ?>
                                <?php if ($jogo['dev_verificado']): ?>
                                <i class="fas fa-check-circle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="sub">Ver perfil</div>
                        </div>
                    </a>
                </div>

                <!-- File Info -->
                <?php if ($arquivo_info): ?>
                <div class="info-card">
                    <h4>Informações</h4>
                    <div class="info-list">
                        <div class="info-row">
                            <span class="label">Versão</span>
                            <span class="value"><?= sanitize($arquivo_info['versao']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Tamanho</span>
                            <span class="value"><?= formatFileSize($arquivo_info['tamanho_bytes']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Downloads</span>
                            <span class="value"><?= number_format($arquivo_info['downloads'], 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Requirements -->
                <?php if ($jogo['requisitos_minimos'] || $jogo['requisitos_recomendados']): ?>
                <div class="info-card">
                    <h4>Requisitos do Sistema</h4>
                    <div class="req-tabs">
                        <?php if ($jogo['requisitos_minimos']): ?>
                        <button class="req-tab active" data-req="min">Mínimos</button>
                        <?php endif; ?>
                        <?php if ($jogo['requisitos_recomendados']): ?>
                        <button class="req-tab <?= !$jogo['requisitos_minimos'] ? 'active' : '' ?>" data-req="rec">Recomendados</button>
                        <?php endif; ?>
                    </div>
                    <div class="req-text" id="reqText"><?= sanitize($jogo['requisitos_minimos'] ?: $jogo['requisitos_recomendados']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Related Games -->
    <?php if (!empty($jogos_relacionados)): ?>
    <div class="container">
        <div class="related-section">
            <h2>Jogos Relacionados</h2>
            <div class="related-grid">
                <?php foreach ($jogos_relacionados as $rel):
                    $rel_preco = ($rel['em_promocao'] && $rel['preco_promocional_centavos']) ? $rel['preco_promocional_centavos'] : $rel['preco_centavos'];
                    $rel_desc = calculateDiscount($rel['preco_centavos'], $rel['preco_promocional_centavos'], $rel['em_promocao']);
                ?>
                <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $rel['slug'] ?>" class="related-card">
                    <div class="related-cover">
                        <img src="<?= SITE_URL . ($rel['imagem_capa'] ?: '/assets/images/no-image.png') ?>" alt="" loading="lazy">
                    </div>
                    <div class="related-info">
                        <h4><?= sanitize($rel['titulo']) ?></h4>
                        <div class="dev"><?= sanitize($rel['nome_estudio']) ?></div>
                        <div class="related-price">
                            <?php if ($rel_desc > 0): ?>
                                <span class="discount">-<?= $rel_desc ?>%</span>
                                <span class="old"><?= formatPrice($rel['preco_centavos']) ?></span>
                            <?php endif; ?>
                            <?php if ($rel_preco == 0): ?>
                                <span class="free">Gratuito</span>
                            <?php else: ?>
                                <span class="current"><?= formatPrice($rel_preco) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Review Modal -->
<div class="modal" id="reviewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="reviewModalTitle">Escrever Avaliação</h3>
            <button class="modal-close" onclick="closeReviewModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="<?= SITE_URL ?>/api/avaliar.php" method="POST">
            <input type="hidden" name="action" id="reviewAction" value="add">
            <input type="hidden" name="jogo_id" value="<?= $jogo['id'] ?>">
            <input type="hidden" name="nota" id="reviewRating" required>
            <div class="modal-body">
                <div class="star-selector" id="starSelector">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="far fa-star" data-value="<?= $i ?>"></i>
                    <?php endfor; ?>
                </div>
                <textarea name="comentario" id="reviewText" class="review-textarea" placeholder="Conte sua experiência com o jogo..." required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeReviewModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Publicar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toastMessage"></span>
</div>

<script>
const SITE_URL = '<?= SITE_URL ?>';
const isLogged = <?= $user_id ? 'true' : 'false' ?>;
const reqMin = <?= json_encode($jogo['requisitos_minimos'] ?? '') ?>;
const reqRec = <?= json_encode($jogo['requisitos_recomendados'] ?? '') ?>;
const totalMedia = <?= count($media_items) ?>;

// ==================== MOBILE CAROUSEL ====================
const carouselTrack = document.getElementById('carouselTrack');
const carouselDots = document.querySelectorAll('.carousel-dot');
let currentSlide = 0;
let startX = 0;
let isDragging = false;

if (carouselTrack && totalMedia > 0) {
    const slides = carouselTrack.children;
    const totalSlides = slides.length;

    function goToSlide(index) {
        if (index < 0) index = 0;
        if (index >= totalSlides) index = totalSlides - 1;
        currentSlide = index;
        carouselTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
        updateDots();
    }

    function updateDots() {
        carouselDots.forEach((dot, i) => {
            dot.classList.toggle('active', i === currentSlide);
        });
    }

    // Touch events
    carouselTrack.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        isDragging = true;
    }, { passive: true });

    carouselTrack.addEventListener('touchmove', (e) => {
        if (!isDragging) return;
        const diff = startX - e.touches[0].clientX;
        if (Math.abs(diff) > 50) {
            if (diff > 0) goToSlide(currentSlide + 1);
            else goToSlide(currentSlide - 1);
            isDragging = false;
        }
    }, { passive: true });

    carouselTrack.addEventListener('touchend', () => {
        isDragging = false;
    });

    // Dot click
    carouselDots.forEach(dot => {
        dot.addEventListener('click', () => {
            goToSlide(parseInt(dot.dataset.index));
        });
    });
}

// ==================== DESKTOP MEDIA GALLERY WITH NAV BUTTONS ====================
const mediaThumbs = document.getElementById('mediaThumbs');
const navPrev = document.getElementById('navPrev');
const navNext = document.getElementById('navNext');
const thumbs = document.querySelectorAll('.media-thumb');
let thumbOffset = 0;
const thumbWidth = 150; // thumb width + gap
const visibleThumbs = 4;

function updateNavButtons() {
    if (!navPrev || !navNext) return;
    navPrev.disabled = thumbOffset <= 0;
    navNext.disabled = thumbOffset >= (thumbs.length - visibleThumbs) * thumbWidth;
}

function scrollThumbs(direction) {
    const maxOffset = Math.max(0, (thumbs.length - visibleThumbs) * thumbWidth);
    thumbOffset += direction * thumbWidth * 2;
    thumbOffset = Math.max(0, Math.min(thumbOffset, maxOffset));
    mediaThumbs.style.transform = `translateX(-${thumbOffset}px)`;
    updateNavButtons();
}

if (navPrev && navNext) {
    navPrev.addEventListener('click', () => scrollThumbs(-1));
    navNext.addEventListener('click', () => scrollThumbs(1));
    updateNavButtons();
}

// Thumb clicks
thumbs.forEach(thumb => {
    thumb.addEventListener('click', function() {
        thumbs.forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const mediaMain = document.getElementById('mediaMain');
        const { type, url } = this.dataset;
        
        if (type === 'video') {
            mediaMain.innerHTML = `<iframe src="${url}" allowfullscreen></iframe>`;
        } else {
            mediaMain.innerHTML = `<img src="${url}" alt="">`;
        }
    });
});

// ==================== DESCRIPTION EXPAND ====================
function toggleDescription() {
    const text = document.getElementById('descriptionText');
    const icon = document.getElementById('expandIcon');
    const label = document.getElementById('expandText');
    
    if (!text) return;
    
    text.classList.toggle('collapsed');
    
    if (text.classList.contains('collapsed')) {
        icon.className = 'fas fa-chevron-down';
        label.textContent = 'Ler mais';
    } else {
        icon.className = 'fas fa-chevron-up';
        label.textContent = 'Ler menos';
    }
}

// ==================== REQUIREMENTS TABS ====================
document.querySelectorAll('.req-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.req-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const reqText = document.getElementById('reqText');
        if (reqText) {
            reqText.textContent = this.dataset.req === 'min' ? reqMin : reqRec;
        }
    });
});

// ==================== REVIEW MODAL ====================
const reviewModal = document.getElementById('reviewModal');
const starSelector = document.getElementById('starSelector');
const stars = starSelector?.querySelectorAll('i') || [];
const ratingInput = document.getElementById('reviewRating');

function openReviewModal(rating = 0, text = '') {
    if (!isLogged) {
        showToast('Faça login para avaliar', 'error');
        setTimeout(() => window.location.href = `${SITE_URL}/auth/login.php`, 1500);
        return;
    }
    
    reviewModal.classList.add('show');
    document.getElementById('reviewAction').value = rating ? 'update' : 'add';
    document.getElementById('reviewModalTitle').textContent = rating ? 'Editar Avaliação' : 'Escrever Avaliação';
    document.getElementById('reviewText').value = text;
    ratingInput.value = rating;
    updateStars(rating);
    document.body.style.overflow = 'hidden';
}

function closeReviewModal() {
    reviewModal.classList.remove('show');
    document.body.style.overflow = '';
}

function updateStars(value) {
    stars.forEach(star => {
        const v = parseInt(star.dataset.value);
        star.className = (v <= value ? 'fas' : 'far') + ' fa-star' + (v <= value ? ' active' : '');
    });
}

stars.forEach(star => {
    star.addEventListener('click', () => {
        const value = parseInt(star.dataset.value);
        ratingInput.value = value;
        updateStars(value);
    });
    star.addEventListener('mouseenter', () => {
        updateStars(parseInt(star.dataset.value));
    });
});

starSelector?.addEventListener('mouseleave', () => {
    updateStars(parseInt(ratingInput.value) || 0);
});

reviewModal?.addEventListener('click', (e) => {
    if (e.target === reviewModal) closeReviewModal();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeReviewModal();
});

// ==================== CART/WISHLIST ACTIONS ====================
document.querySelectorAll('[data-action]').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!isLogged) {
            showToast('Faça login primeiro', 'error');
            setTimeout(() => window.location.href = `${SITE_URL}/auth/login.php`, 1500);
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
                
                // Update all buttons with same action and id
                document.querySelectorAll(`[data-action="${action}"][data-id="${id}"]`).forEach(b => {
                    const icon = b.querySelector('i');
                    const span = b.querySelector('span');
                    
                    if (action === 'cart') {
                        // Handle both desktop and mobile cart buttons
                        if (b.classList.contains('mobile-btn-main')) {
                            b.classList.remove('primary', 'success');
                            b.classList.add(added ? 'success' : 'primary');
                        } else {
                            b.classList.remove('btn-primary', 'btn-success');
                            b.classList.add(added ? 'btn-success' : 'btn-primary');
                        }
                        if (icon) icon.className = `fas ${added ? 'fa-check' : 'fa-cart-plus'}`;
                        if (span) span.textContent = added ? 'No Carrinho' : 'Adicionar ao Carrinho';
                    } else {
                        // Wishlist buttons
                        b.classList.toggle('active', added);
                        if (span) span.textContent = added ? 'Na Lista' : 'Lista de Desejos';
                    }
                });

                if (action === 'cart' && added) {
                    setTimeout(() => location.reload(), 500);
                }

                showToast(data.message, 'success');
            } else {
                showToast(data.message, 'error');
            }
        } catch (e) {
            showToast('Erro ao processar', 'error');
        }

        this.disabled = false;
    });
});

// ==================== TOAST ====================
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const icon = toast.querySelector('i');
    const msg = document.getElementById('toastMessage');

    toast.className = `toast ${type}`;
    icon.className = `fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}`;
    msg.textContent = message;

    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3500);
}
</script>

<?php require_once '../includes/footer.php'; ?>