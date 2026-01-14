<?php
// ===========================================
// pages/categoria.php
// Página de listagem de jogos por categoria
// ===========================================

require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$user_id = $_SESSION['user_id'] ?? null;
$slug = $_GET['slug'] ?? '';
$ordem = $_GET['ordem'] ?? 'popular';

// Redireciona se não houver slug
if (empty($slug)) {
    header('Location: ' . SITE_URL . '/pages/busca.php');
    exit;
}

// ===========================================
// 1. BUSCAR CATEGORIA
// ===========================================
$stmt = $pdo->prepare("SELECT * FROM categoria WHERE slug = ? AND ativa = 1");
$stmt->execute([$slug]);
$categoria = $stmt->fetch();

if (!$categoria) {
    header('Location: ' . SITE_URL . '/pages/busca.php');
    exit;
}

// ===========================================
// 2. DEFINIR ORDENAÇÃO
// ===========================================
$ordenacoes = [
    'popular' => 'j.total_vendas DESC',
    'nota' => 'j.nota_media DESC',
    'novos' => 'j.publicado_em DESC',
    'preco_menor' => 'j.preco_centavos ASC',
    'preco_maior' => 'j.preco_centavos DESC'
];
$orderBy = $ordenacoes[$ordem] ?? $ordenacoes['popular'];

// ===========================================
// 3. BUSCAR JOGOS DA CATEGORIA
// ===========================================
$stmt = $pdo->prepare("
    SELECT j.*, 
           d.nome_estudio,
           GROUP_CONCAT(DISTINCT t.nome SEPARATOR ', ') as todas_tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    INNER JOIN jogo_categoria jc ON j.id = jc.jogo_id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE jc.categoria_id = ? AND j.status = 'publicado'
    GROUP BY j.id
    ORDER BY {$orderBy}
    LIMIT 50
");
$stmt->execute([$categoria['id']]);
$todosJogos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===========================================
// 4. SEPARAR DESTAQUE E GRID
// ===========================================
$jogoDestaque = null;
$jogosGrid = [];
$tagsPopulares = [];

// Status do usuário para o destaque
$destaque_possui = false;
$destaque_in_cart = false;
$destaque_in_wishlist = false;

if (!empty($todosJogos)) {
    $jogoDestaque = $todosJogos[0];
    $jogosGrid = array_slice($todosJogos, 1);

    // Verificar status do usuário para o jogo destaque
    if ($user_id && $jogoDestaque) {
        // Verifica se possui na biblioteca
        $stmt = $pdo->prepare("SELECT id FROM biblioteca WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogoDestaque['id']]);
        $destaque_possui = $stmt->fetch() ? true : false;
        
        // Verifica se está no carrinho
        $stmt = $pdo->prepare("SELECT id FROM carrinho WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogoDestaque['id']]);
        $destaque_in_cart = $stmt->fetch() ? true : false;
        
        // Verifica se está na lista de desejos
        $stmt = $pdo->prepare("SELECT id FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogoDestaque['id']]);
        $destaque_in_wishlist = $stmt->fetch() ? true : false;
    }

    // Extrair tags únicas para nuvem de tags
    $tagsEncontradas = [];
    foreach ($todosJogos as $j) {
        if (!empty($j['todas_tags'])) {
            $tagsArray = explode(', ', $j['todas_tags']);
            foreach ($tagsArray as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $tagsEncontradas[$tag] = ($tagsEncontradas[$tag] ?? 0) + 1;
                }
            }
        }
    }
    arsort($tagsEncontradas);
    $tagsPopulares = array_slice(array_keys($tagsEncontradas), 0, 8);
}

// ===========================================
// 5. CALCULAR PREÇOS DO DESTAQUE
// ===========================================
$destaque_preco_original = $jogoDestaque['preco_centavos'] ?? 0;
$destaque_preco_promo = $jogoDestaque['preco_promocional_centavos'] ?? 0;
$destaque_em_promocao = ($jogoDestaque['em_promocao'] ?? false) && $destaque_preco_promo > 0 && $destaque_preco_promo < $destaque_preco_original;
$destaque_preco_final = $destaque_em_promocao ? $destaque_preco_promo : $destaque_preco_original;
$destaque_desconto = $destaque_em_promocao ? round((1 - $destaque_preco_promo / $destaque_preco_original) * 100) : 0;

// ===========================================
// 6. IMAGEM HERO
// ===========================================
$heroImage = '';
if ($jogoDestaque) {
    $heroImage = !empty($jogoDestaque['imagem_banner']) ? $jogoDestaque['imagem_banner'] : $jogoDestaque['imagem_capa'];
    if (strpos($heroImage, 'http') !== 0) {
        $heroImage = SITE_URL . $heroImage;
    }
} else {
    $heroImage = 'https://via.placeholder.com/1920x600/111827/ffffff?text=' . urlencode($categoria['nome']);
}

// ===========================================
// 7. PÁGINA E INCLUDES
// ===========================================
$page_title = $categoria['nome'] . ' - Jogos e Destaques';
require_once '../includes/header.php';
require_once '../components/game-card.php';

// Função auxiliar para formatar preço
if (!function_exists('formatarPreco')) {
    function formatarPreco($centavos) {
        if ($centavos == 0) return 'Grátis';
        return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
    }
}

// Função auxiliar para sanitização (caso não exista)
if (!function_exists('sanitize')) {
    function sanitize($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}
?>

<style>
/* ===========================================
   VARIÁVEIS CSS
   =========================================== */
:root {
    --promo-color: #4ade80;
    --promo-bg: rgba(74, 222, 128, 0.1);
    --danger-color: #ef4444;
    --info-color: #3b82f6;
    --warning-color: #f59e0b;
}

/* ===========================================
   HERO SECTION
   =========================================== */
.cat-hero {
    position: relative;
    height: 350px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-top: -20px;
    margin-bottom: 0;
    background-color: var(--bg-secondary);
    mask-image: linear-gradient(to bottom, black 80%, transparent 100%);
    -webkit-mask-image: linear-gradient(to bottom, black 80%, transparent 100%);
}

.cat-hero-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: url('<?php echo $heroImage; ?>');
    background-size: cover;
    background-position: center top;
    filter: blur(12px) brightness(0.3) saturate(1.2);
    transform: scale(1.1);
    z-index: 0;
}

.cat-title-big {
    position: relative;
    z-index: 2;
    font-size: 5rem;
    font-weight: 900;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.1);
    -webkit-text-stroke: 2px rgba(255, 255, 255, 0.5);
    letter-spacing: 10px;
    text-align: center;
    margin-top: -50px;
    text-shadow: 0 0 60px rgba(0,0,0,0.5);
}

.cat-breadcrumb {
    position: absolute;
    bottom: 40px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 3;
    color: #fff;
    background: rgba(0, 0, 0, 0.6);
    padding: 10px 24px;
    border-radius: 25px;
    font-size: 0.9rem;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
}

.cat-breadcrumb a {
    color: #ccc;
    text-decoration: none;
    transition: color 0.2s;
}

.cat-breadcrumb a:hover {
    color: #fff;
}

.cat-breadcrumb .separator {
    opacity: 0.5;
    margin: 0 8px;
}

/* ===========================================
   SPOTLIGHT CARD (JOGO DESTAQUE)
   =========================================== */
.spotlight-container {
    margin-top: -80px;
    position: relative;
    z-index: 10;
    margin-bottom: 50px;
}

.spotlight-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    display: flex;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
    min-height: 420px;
    position: relative;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.spotlight-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 35px 80px rgba(0, 0, 0, 0.7);
}

.spotlight-image {
    flex: 1.6;
    background-size: cover;
    background-position: center;
    position: relative;
    min-height: 320px;
}

.spotlight-image::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, rgba(0, 0, 0, 0) 40%, var(--bg-secondary) 100%);
}

/* Ações rápidas na imagem */
.spotlight-quick-actions {
    position: absolute;
    top: 20px;
    left: 20px;
    display: flex;
    gap: 10px;
    z-index: 5;
}

.spotlight-action-btn {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: none;
    background: rgba(0, 0, 0, 0.8);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
    font-size: 18px;
}

.spotlight-action-btn:hover {
    transform: scale(1.1);
    background: var(--accent);
    color: #000;
}

.spotlight-action-btn.active {
    background: var(--danger-color);
    color: #fff;
}

.spotlight-action-btn.active:hover {
    background: #dc2626;
}

/* Conteúdo do Spotlight */
.spotlight-content {
    flex: 1;
    padding: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
}

/* Badges */
.spotlight-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.badge-spotlight {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--accent);
    color: #000;
    padding: 6px 14px;
    border-radius: 6px;
    font-weight: 800;
    text-transform: uppercase;
    font-size: 0.75rem;
    box-shadow: 0 0 20px rgba(185, 255, 102, 0.4);
}

.badge-promo {
    background: var(--promo-color);
    color: #000;
    box-shadow: 0 0 20px rgba(74, 222, 128, 0.4);
    animation: pulse-promo 2s ease-in-out infinite;
}

@keyframes pulse-promo {
    0%, 100% { box-shadow: 0 0 20px rgba(74, 222, 128, 0.4); }
    50% { box-shadow: 0 0 30px rgba(74, 222, 128, 0.6), 0 0 50px rgba(74, 222, 128, 0.3); }
}

.badge-owned {
    background: var(--info-color);
    box-shadow: 0 0 20px rgba(59, 130, 246, 0.4);
}

/* Título e descrição */
.spotlight-title {
    font-size: 2.5rem;
    margin-bottom: 15px;
    line-height: 1.15;
    color: #fff;
    font-weight: 800;
}

.spotlight-title a {
    color: var(--text-primary);
    text-decoration: none;
    transition: color 0.2s;
}

.spotlight-title a:hover {
    color: var(--accent);
}

.spotlight-desc {
    color: var(--text-secondary);
    font-size: 1rem;
    line-height: 1.7;
    margin-bottom: 25px;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Meta info */
.spotlight-meta {
    display: flex;
    gap: 20px;
    margin-bottom: 25px;
    font-size: 0.9rem;
    color: #aaa;
    flex-wrap: wrap;
}

.spotlight-meta span {
    display: flex;
    align-items: center;
    gap: 8px;
}

.spotlight-meta i {
    font-size: 14px;
}

/* ===========================================
   PREÇOS DO SPOTLIGHT
   =========================================== */
.spotlight-price-wrapper {
    margin-bottom: 25px;
}

.spotlight-price-row {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.spotlight-discount-badge {
    background: var(--promo-color);
    color: #000;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 900;
    font-size: 1.5rem;
}

.spotlight-prices {
    display: flex;
    flex-direction: column;
}

.spotlight-old-price {
    font-size: 1rem;
    color: #888;
    text-decoration: line-through;
}

.spotlight-current-price {
    font-size: 2.2rem;
    font-weight: 700;
    color: #fff;
}

.spotlight-current-price.promo {
    color: var(--promo-color);
}

.spotlight-current-price.free {
    color: var(--promo-color);
    font-size: 1.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* ===========================================
   BOTÕES DE AÇÃO DO SPOTLIGHT
   =========================================== */
.spotlight-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.spotlight-btn {
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-decoration: none;
    border: none;
}

.spotlight-btn-primary {
    background: var(--accent);
    color: #000;
    flex: 1;
    min-width: 160px;
}

.spotlight-btn-primary:hover {
    background: #fff;
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(185, 255, 102, 0.3);
}

.spotlight-btn-cart {
    background: var(--promo-color);
    color: #000;
    flex: 1;
    min-width: 200px;
}

.spotlight-btn-cart:hover {
    filter: brightness(1.1);
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(74, 222, 128, 0.3);
}

.spotlight-btn-cart.in-cart {
    background: var(--danger-color);
    color: #fff;
}

.spotlight-btn-cart.in-cart:hover {
    background: #dc2626;
}

.spotlight-btn-download {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    color: #fff;
    flex: 1;
    min-width: 200px;
}

.spotlight-btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4);
}

.spotlight-btn-outline {
    background: transparent;
    border: 2px solid var(--border);
    color: #fff;
    padding: 14px 20px;
}

.spotlight-btn-outline:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(185, 255, 102, 0.05);
}

.spotlight-btn-outline.active {
    border-color: var(--danger-color);
    color: var(--danger-color);
    background: rgba(239, 68, 68, 0.1);
}

.spotlight-btn-outline.active:hover {
    background: rgba(239, 68, 68, 0.2);
}

/* ===========================================
   TAGS CLOUD
   =========================================== */
.tags-cloud {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 1px solid var(--border);
    align-items: center;
}

.tags-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-right: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.tag-pill {
    background: rgba(255, 255, 255, 0.05);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    color: var(--text-secondary);
    border: 1px solid transparent;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.tag-pill:hover {
    border-color: var(--accent);
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
}

.tag-pill i {
    font-size: 10px;
    opacity: 0.7;
}

/* ===========================================
   HEADER DA SEÇÃO
   =========================================== */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.section-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #fff;
    font-size: 1.3rem;
}

.section-header h3 i {
    color: var(--accent);
}

.results-count {
    font-weight: 400;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.order-select {
    padding: 12px 18px;
    border-radius: 10px;
    background: var(--bg-secondary);
    color: #fff;
    border: 1px solid var(--border);
    cursor: pointer;
    font-size: 0.9rem;
    transition: border-color 0.2s;
}

.order-select:hover {
    border-color: var(--accent);
}

.order-select:focus {
    outline: none;
    border-color: var(--accent);
}

/* ===========================================
   GRID DE JOGOS
   =========================================== */
.games-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 30px;
}

/* ===========================================
   RESULTADOS INFO
   =========================================== */
.results-info {
    text-align: center;
    margin-top: 50px;
    padding: 30px;
    border-top: 1px solid var(--border);
}

.results-info p {
    color: var(--text-secondary);
    margin: 0;
}

.results-divider {
    width: 60px;
    height: 3px;
    background: linear-gradient(to right, transparent, var(--border), transparent);
    margin: 20px auto 0;
    border-radius: 2px;
}

/* ===========================================
   EMPTY STATE
   =========================================== */
.empty-state {
    text-align: center;
    padding: 100px 20px;
}

.empty-state-icon {
    font-size: 80px;
    color: var(--text-secondary);
    margin-bottom: 25px;
    opacity: 0.2;
}

.empty-state h2 {
    color: #fff;
    margin-bottom: 12px;
    font-size: 1.8rem;
}

.empty-state p {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.empty-state .btn {
    margin-top: 25px;
    padding: 14px 35px;
    background: var(--accent);
    color: #000;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
}

.empty-state .btn:hover {
    background: #fff;
    transform: translateY(-2px);
}

/* ===========================================
   TOAST NOTIFICATION
   =========================================== */
.toast-notification {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    padding: 16px 24px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 14px;
    z-index: 9999;
    transform: translateY(120px);
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.5);
    color: #fff;
    max-width: 400px;
}

.toast-notification.show {
    transform: translateY(0);
    opacity: 1;
}

.toast-notification.success {
    border-color: var(--promo-color);
}

.toast-notification.success i {
    color: var(--promo-color);
    font-size: 20px;
}

.toast-notification.error {
    border-color: var(--danger-color);
}

.toast-notification.error i {
    color: var(--danger-color);
    font-size: 20px;
}

.toast-notification.info {
    border-color: var(--info-color);
}

.toast-notification.info i {
    color: var(--info-color);
    font-size: 20px;
}

/* ===========================================
   ONLY SPOTLIGHT MESSAGE
   =========================================== */
.only-spotlight-msg {
    padding: 40px;
    text-align: center;
    background: var(--bg-secondary);
    border-radius: 14px;
    margin-top: 20px;
    border: 1px solid var(--border);
}

.only-spotlight-msg p {
    color: var(--text-secondary);
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

/* ===========================================
   RESPONSIVO
   =========================================== */
@media (max-width: 1024px) {
    .spotlight-card {
        flex-direction: column;
    }
    
    .spotlight-image {
        height: 300px;
        flex: none;
    }
    
    .spotlight-image::after {
        background: linear-gradient(to bottom, rgba(0, 0, 0, 0) 40%, var(--bg-secondary) 100%);
    }
    
    .cat-title-big {
        font-size: 3.5rem;
        letter-spacing: 6px;
    }
    
    .spotlight-title {
        font-size: 2.2rem;
    }
}

@media (max-width: 768px) {
    .cat-hero {
        height: 280px;
    }
    
    .cat-title-big {
        font-size: 2.2rem;
        letter-spacing: 4px;
        -webkit-text-stroke: 1px rgba(255, 255, 255, 0.5);
    }
    
    .spotlight-container {
        margin-top: -60px;
    }
    
    .spotlight-image {
        height: 220px;
    }
    
    .spotlight-quick-actions {
        top: 15px;
        left: 15px;
    }
    
    .spotlight-action-btn {
        width: 42px;
        height: 42px;
        font-size: 16px;
    }
    
    .spotlight-content {
        padding: 25px;
    }
    
    .spotlight-title {
        font-size: 1.7rem;
    }
    
    .spotlight-current-price {
        font-size: 1.8rem;
    }
    
    .spotlight-discount-badge {
        font-size: 1.2rem;
        padding: 8px 14px;
    }
    
    .spotlight-actions {
        flex-direction: column;
    }
    
    .spotlight-btn {
        width: 100%;
        min-width: auto;
    }
    
    .spotlight-meta {
        gap: 12px;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .games-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
    }
    
    .tags-cloud {
        justify-content: center;
    }
    
    .tags-label {
        width: 100%;
        justify-content: center;
        margin-bottom: 5px;
    }
    
    .toast-notification {
        left: 15px;
        right: 15px;
        bottom: 15px;
    }
}

@media (max-width: 480px) {
    .cat-title-big {
        font-size: 1.6rem;
        letter-spacing: 2px;
    }
    
    .spotlight-title {
        font-size: 1.4rem;
    }
    
    .spotlight-desc {
        -webkit-line-clamp: 2;
    }
    
    .games-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}
</style>

<!-- ===========================================
     HERO SECTION
     =========================================== -->
<div class="cat-hero">
    <div class="cat-hero-bg"></div>
    <div class="cat-title-big"><?php echo sanitize($categoria['nome']); ?></div>
</div>

<div class="container">
    
    <?php if ($jogoDestaque): ?>
    
    <!-- ===========================================
         SPOTLIGHT (JOGO DESTAQUE)
         =========================================== -->
    <div class="spotlight-container">
        <div class="spotlight-card">
            <?php 
                $imgDestaque = !empty($jogoDestaque['imagem_banner']) ? $jogoDestaque['imagem_banner'] : $jogoDestaque['imagem_capa'];
                if (strpos($imgDestaque, 'http') !== 0) {
                    $imgDestaque = SITE_URL . $imgDestaque;
                }
            ?>
            <div class="spotlight-image" style="background-image: url('<?php echo $imgDestaque; ?>');">
                
                <?php if ($user_id && !$destaque_possui): ?>
                <div class="spotlight-quick-actions">
                    <button onclick="toggleWishlist(<?php echo $jogoDestaque['id']; ?>, this)" 
                            class="spotlight-action-btn <?php echo $destaque_in_wishlist ? 'active' : ''; ?>"
                            title="<?php echo $destaque_in_wishlist ? 'Remover da Lista de Desejos' : 'Adicionar à Lista de Desejos'; ?>">
                        <i class="fas fa-heart"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="spotlight-content">
                
                <!-- Badges -->
                <div class="spotlight-badges">
                    <?php if ($destaque_possui): ?>
                        <span class="badge-spotlight badge-owned">
                            <i class="fas fa-check-circle"></i> Na Sua Biblioteca
                        </span>
                    <?php else: ?>
                        <span class="badge-spotlight">
                            <i class="fas fa-crown"></i> Mais Popular
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($destaque_em_promocao && !$destaque_possui): ?>
                        <span class="badge-spotlight badge-promo">
                            <i class="fas fa-fire"></i> Em Promoção
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Título -->
                <h2 class="spotlight-title">
                    <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogoDestaque['slug']; ?>">
                        <?php echo sanitize($jogoDestaque['titulo']); ?>
                    </a>
                </h2>
                
                <!-- Meta informações -->
                <div class="spotlight-meta">
                    <span>
                        <i class="fas fa-star" style="color: gold;"></i>
                        <?php echo number_format($jogoDestaque['nota_media'] ?? 0, 1); ?>/5.0
                    </span>
                    
                    <?php if (!empty($jogoDestaque['nome_estudio'])): ?>
                    <span>
                        <i class="fas fa-building"></i>
                        <?php echo sanitize($jogoDestaque['nome_estudio']); ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if (($jogoDestaque['total_vendas'] ?? 0) > 0): ?>
                    <span>
                        <i class="fas fa-download"></i>
                        <?php echo number_format($jogoDestaque['total_vendas']); ?> downloads
                    </span>
                    <?php endif; ?>
                </div>
                
                <!-- Descrição -->
                <p class="spotlight-desc">
                    <?php echo sanitize($jogoDestaque['descricao'] ?? 'Descubra este incrível jogo e mergulhe em uma experiência única de jogabilidade!'); ?>
                </p>
                
                <!-- Preço (apenas se não possui) -->
                <?php if (!$destaque_possui): ?>
                <div class="spotlight-price-wrapper">
                    <?php if ($destaque_preco_final == 0): ?>
                        <span class="spotlight-current-price free">
                            <i class="fas fa-gift" style="margin-right: 8px;"></i> Grátis para Jogar
                        </span>
                    <?php elseif ($destaque_em_promocao): ?>
                        <div class="spotlight-price-row">
                            <span class="spotlight-discount-badge">-<?php echo $destaque_desconto; ?>%</span>
                            <div class="spotlight-prices">
                                <span class="spotlight-old-price"><?php echo formatarPreco($destaque_preco_original); ?></span>
                                <span class="spotlight-current-price promo"><?php echo formatarPreco($destaque_preco_final); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="spotlight-current-price"><?php echo formatarPreco($destaque_preco_final); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Botões de Ação -->
                <div class="spotlight-actions">
                    <?php if ($destaque_possui): ?>
                        <!-- Usuário já possui o jogo -->
                        <a href="<?php echo SITE_URL; ?>/user/biblioteca.php" 
                           class="spotlight-btn spotlight-btn-download">
                            <i class="fas fa-download"></i> Ir para Biblioteca
                        </a>
                        <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogoDestaque['slug']; ?>" 
                           class="spotlight-btn spotlight-btn-outline">
                            <i class="fas fa-info-circle"></i> Ver Detalhes
                        </a>
                        
                    <?php elseif ($user_id): ?>
                        <!-- Usuário logado mas não possui -->
                        <button onclick="toggleCart(<?php echo $jogoDestaque['id']; ?>, this)" 
                                class="spotlight-btn spotlight-btn-cart <?php echo $destaque_in_cart ? 'in-cart' : ''; ?>">
                            <i class="fas <?php echo $destaque_in_cart ? 'fa-times' : 'fa-cart-plus'; ?>"></i>
                            <span><?php echo $destaque_in_cart ? 'Remover do Carrinho' : 'Adicionar ao Carrinho'; ?></span>
                        </button>
                        <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogoDestaque['slug']; ?>" 
                           class="spotlight-btn spotlight-btn-primary">
                            <i class="fas fa-eye"></i> Ver Detalhes
                        </a>
                        <button onclick="toggleWishlist(<?php echo $jogoDestaque['id']; ?>, this)" 
                                class="spotlight-btn spotlight-btn-outline <?php echo $destaque_in_wishlist ? 'active' : ''; ?>"
                                title="Lista de Desejos">
                            <i class="fas fa-heart"></i>
                        </button>
                        
                    <?php else: ?>
                        <!-- Usuário não logado -->
                        <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogoDestaque['slug']; ?>" 
                           class="spotlight-btn spotlight-btn-primary">
                            <i class="fas fa-eye"></i> Ver Detalhes
                        </a>
                        <a href="<?php echo SITE_URL; ?>/auth/login.php" 
                           class="spotlight-btn spotlight-btn-outline">
                            <i class="fas fa-sign-in-alt"></i> Entrar para Comprar
                        </a>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- ===========================================
         FILTROS E ORDENAÇÃO
         =========================================== -->
    <div class="section-header">
        <h3>
            <i class="fas fa-gamepad"></i>
            Catálogo <?php echo sanitize($categoria['nome']); ?>
            <span class="results-count">(<?php echo count($todosJogos); ?> jogos)</span>
        </h3>
        
        <form action="" method="GET" style="display: inline-block;">
            <input type="hidden" name="slug" value="<?php echo sanitize($slug); ?>">
            <select name="ordem" onchange="this.form.submit()" class="order-select">
                <option value="popular" <?php echo $ordem == 'popular' ? 'selected' : ''; ?>>Mais Populares</option>
                <option value="novos" <?php echo $ordem == 'novos' ? 'selected' : ''; ?>>Lançamentos</option>
                <option value="nota" <?php echo $ordem == 'nota' ? 'selected' : ''; ?>>Melhor Avaliados</option>
                <option value="preco_menor" <?php echo $ordem == 'preco_menor' ? 'selected' : ''; ?>>Menor Preço</option>
                <option value="preco_maior" <?php echo $ordem == 'preco_maior' ? 'selected' : ''; ?>>Maior Preço</option>
            </select>
        </form>
    </div>
    
    <!-- ===========================================
         NUVEM DE TAGS
         =========================================== -->
    <?php if (!empty($tagsPopulares)): ?>
    <div class="tags-cloud">
        <span class="tags-label">
            <i class="fas fa-tags"></i> Tags populares:
        </span>
        <?php foreach ($tagsPopulares as $tag): ?>
            <a href="<?php echo SITE_URL; ?>/pages/busca.php?q=<?php echo urlencode($tag); ?>" class="tag-pill">
                <i class="fas fa-tag"></i> <?php echo sanitize($tag); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- ===========================================
         GRID DE JOGOS
         =========================================== -->
    <?php if (count($jogosGrid) > 0): ?>
        <div class="games-grid">
            <?php foreach ($jogosGrid as $jogo): ?>
                <?php renderGameCard($jogo, $pdo, $user_id); ?>
            <?php endforeach; ?>
        </div>
        
        <div class="results-info">
            <p>Mostrando <?php echo count($todosJogos); ?> jogos em <?php echo sanitize($categoria['nome']); ?></p>
            <div class="results-divider"></div>
        </div>
    <?php else: ?>
        <div class="only-spotlight-msg">
            <p>
                <i class="fas fa-info-circle"></i>
                Apenas o destaque acima foi encontrado nesta categoria.
            </p>
        </div>
    <?php endif; ?>
    
    <?php else: ?>
    
    <!-- ===========================================
         ESTADO VAZIO
         =========================================== -->
    <div class="empty-state">
        <i class="fas fa-ghost empty-state-icon"></i>
        <h2>Categoria Vazia</h2>
        <p>Nenhum jogo encontrado em <?php echo sanitize($categoria['nome']); ?>.</p>
        <a href="<?php echo SITE_URL; ?>" class="btn">
            <i class="fas fa-home"></i> Voltar ao Início
        </a>
    </div>
    
    <?php endif; ?>

</div>

<!-- ===========================================
     JAVASCRIPT
     =========================================== -->
<script>
const SITE_URL = '<?php echo SITE_URL; ?>';
const isLoggedIn = <?php echo $user_id ? 'true' : 'false'; ?>;

/**
 * Exibe notificação toast
 * @param {string} message - Mensagem a ser exibida
 * @param {string} type - Tipo: 'success', 'error', 'info'
 */
function showNotification(message, type = 'success') {
    // Remove toast existente
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }

    // Cria novo toast
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    
    let icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    if (type === 'info') icon = 'fa-info-circle';
    
    toast.innerHTML = `
        <i class="fas ${icon}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);

    // Anima entrada
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Remove após 3.5 segundos
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

/**
 * Toggle item no Carrinho
 * @param {number} jogoId - ID do jogo
 * @param {HTMLElement} button - Elemento do botão clicado
 */
function toggleCart(jogoId, button) {
    if (!isLoggedIn) {
        showNotification('Você precisa estar logado!', 'error');
        setTimeout(() => {
            window.location.href = SITE_URL + '/auth/login.php';
        }, 1500);
        return;
    }

    // Desabilita botão e mostra loading
    button.disabled = true;
    const icon = button.querySelector('i');
    const textSpan = button.querySelector('span');
    const originalIconClass = icon.className;
    icon.className = 'fas fa-spinner fa-spin';

    fetch(SITE_URL + '/api/toggle-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ jogo_id: jogoId })
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        
        if (data.success) {
            if (data.action === 'added') {
                button.classList.add('in-cart');
                icon.className = 'fas fa-times';
                if (textSpan) textSpan.textContent = 'Remover do Carrinho';
                showNotification('Adicionado ao carrinho!', 'success');
            } else {
                button.classList.remove('in-cart');
                icon.className = 'fas fa-cart-plus';
                if (textSpan) textSpan.textContent = 'Adicionar ao Carrinho';
                showNotification('Removido do carrinho', 'info');
            }
            
            // Atualiza contador do carrinho no header se existir
            if (typeof updateCartCount === 'function') {
                updateCartCount();
            }
        } else {
            icon.className = originalIconClass;
            showNotification(data.message || 'Erro ao processar', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        button.disabled = false;
        icon.className = originalIconClass;
        showNotification('Erro de conexão. Tente novamente.', 'error');
    });
}

/**
 * Toggle item na Lista de Desejos
 * @param {number} jogoId - ID do jogo
 * @param {HTMLElement} button - Elemento do botão clicado
 */
function toggleWishlist(jogoId, button) {
    if (!isLoggedIn) {
        showNotification('Você precisa estar logado!', 'error');
        setTimeout(() => {
            window.location.href = SITE_URL + '/auth/login.php';
        }, 1500);
        return;
    }

    // Desabilita botão e anima
    button.disabled = true;
    const icon = button.querySelector('i');
    icon.style.transform = 'scale(1.3)';

    fetch(SITE_URL + '/api/toggle-wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ jogo_id: jogoId })
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        icon.style.transform = 'scale(1)';
        
        if (data.success) {
            if (data.action === 'added') {
                button.classList.add('active');
                button.title = 'Remover da Lista de Desejos';
                showNotification('Adicionado à lista de desejos!', 'success');
            } else {
                button.classList.remove('active');
                button.title = 'Adicionar à Lista de Desejos';
                showNotification('Removido da lista de desejos', 'info');
            }
        } else {
            showNotification(data.message || 'Erro ao processar', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        button.disabled = false;
        icon.style.transform = 'scale(1)';
        showNotification('Erro de conexão. Tente novamente.', 'error');
    });
}

// Animação suave ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    // Anima o spotlight card
    const spotlight = document.querySelector('.spotlight-card');
    if (spotlight) {
        spotlight.style.opacity = '0';
        spotlight.style.transform = 'translateY(30px)';
        setTimeout(() => {
            spotlight.style.transition = 'all 0.6s ease-out';
            spotlight.style.opacity = '1';
            spotlight.style.transform = 'translateY(0)';
        }, 100);
    }
    
    // Anima os cards do grid
    const cards = document.querySelectorAll('.games-grid > *');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.4s ease-out';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 200 + (index * 50));
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>