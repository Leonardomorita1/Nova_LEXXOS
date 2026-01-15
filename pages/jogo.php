<?php
// pages/jogo.php - Epic Games + PlayStation Style
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();
$slug = $_GET['slug'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (empty($slug)) { header('Location: ' . SITE_URL); exit; }

// Buscar jogo
$stmt = $pdo->prepare("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug, d.logo_url, d.verificado as dev_verificado
    FROM jogo j LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
    WHERE j.slug = ? AND j.status = 'publicado'
");
$stmt->execute([$slug]);
$jogo = $stmt->fetch();

if (!$jogo) { header('Location: ' . SITE_URL); exit; }

// Verificar arquivo
$stmt = $pdo->prepare("SELECT * FROM arquivo_jogo WHERE jogo_id = ? AND ativo = 1 ORDER BY criado_em DESC LIMIT 1");
$stmt->execute([$jogo['id']]);
$arquivo_info = $stmt->fetch();
$tem_arquivo = (bool)$arquivo_info;

// Verificar idade
$idade_bloqueada = false;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT data_nascimento FROM usuario WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();
    if ($usuario && $usuario['data_nascimento'] && $jogo['classificacao_etaria'] > 0) {
        $idade = (new DateTime($usuario['data_nascimento']))->diff(new DateTime())->y;
        $idade_bloqueada = $idade < $jogo['classificacao_etaria'];
    }
}

if ($idade_bloqueada) {
    $page_title = 'Conteúdo Restrito - ' . SITE_NAME;
    require_once '../includes/header.php';
    ?>
    <style>
        .age-block{display:flex;align-items:center;justify-content:center;min-height:70vh;padding:20px}
        .age-card{background:var(--bg-secondary);border:1px solid var(--border);border-radius:16px;padding:48px 32px;max-width:400px;text-align:center;box-shadow:0 20px 40px rgba(0,0,0,.5)}
        .age-card i{font-size:56px;color:var(--danger);margin-bottom:20px}
        .age-card h1{font-size:24px;margin-bottom:12px}
        .age-card p{color:var(--text-secondary);margin-bottom:24px}
        .age-badge{display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border:3px solid var(--danger);border-radius:12px;font-size:28px;font-weight:800;color:var(--danger);margin-bottom:24px}
        .age-card .btn{padding:12px 32px;background:transparent;border:1px solid var(--accent);color:var(--accent);border-radius:8px;text-decoration:none;font-weight:600;transition:.2s}
        .age-card .btn:hover{background:var(--accent);color:#fff}
    </style>
    <div class="age-block">
        <div class="age-card">
            <i class="fas fa-ban"></i>
            <h1>Acesso Restrito</h1>
            <p>Este conteúdo não é recomendado para a sua idade.</p>
            <div class="age-badge"><?= $jogo['classificacao_etaria'] ?>+</div>
            <br><br>
            <a href="<?= SITE_URL ?>" class="btn"><i class="fas fa-arrow-left"></i> Voltar</a>
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

// Incrementar views
$pdo->prepare("UPDATE jogo SET total_visualizacoes = total_visualizacoes + 1 WHERE id = ?")->execute([$jogo['id']]);

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

// Jogos relacionados
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

function getYoutubeId($url) { preg_match('/(?:embed\/|v=|youtu\.be\/)([^&?\/]+)/', $url, $m); return $m[1] ?? ''; }

$page_title = $jogo['titulo'] . ' - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
/* ========== EPIC + PLAYSTATION STYLE ========== */
.gp{background:var(--bg-primary);min-height:100vh}
.gc{max-width:1200px;margin:0 auto;padding:0 20px}

/* Hero */
.hero{position:relative;height:420px;overflow:hidden}
.hero-bg{position:absolute;inset:0;background:url('<?= SITE_URL . ($jogo['imagem_banner'] ?: $jogo['imagem_capa']) ?>') center/cover}
.hero-bg::after{content:'';position:absolute;inset:0;background:linear-gradient(to top,var(--bg-primary) 0%,rgba(19,19,20,.6) 50%,rgba(19,19,20,.3) 100%)}

/* Layout */
.layout{display:grid;grid-template-columns:1fr 360px;gap:40px;margin-top:-140px;position:relative;z-index:2;padding-bottom:60px}
.main{min-width:0}

/* Media Gallery */
.media{background:var(--bg-secondary);border-radius:12px;overflow:hidden;margin-bottom:32px}
.media-view{aspect-ratio:16/9;background:#000;position:relative}
.media-view img,.media-view iframe{width:100%;height:100%;object-fit:cover;border:0}
.thumbs{display:flex;gap:8px;padding:12px;background:var(--bg-tertiary);overflow-x:auto;scrollbar-width:none}
.thumbs::-webkit-scrollbar{display:none}
.thumb{flex:0 0 140px;height:80px;border-radius:6px;overflow:hidden;cursor:pointer;position:relative;border:2px solid transparent;opacity:.7;transition:.2s}
.thumb:hover,.thumb.active{opacity:1;border-color:var(--accent)}
.thumb img{width:100%;height:100%;object-fit:cover}
.thumb.vid::after{content:'\f04b';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);color:#fff;font-size:20px}

/* Info Section */
.info{padding:32px 0}
.title{font-size:2.5rem;font-weight:700;margin-bottom:16px;line-height:1.2}
.meta{display:flex;flex-wrap:wrap;gap:16px;margin-bottom:20px;font-size:.9rem;color:var(--text-secondary)}
.meta i{color:var(--accent);margin-right:6px}
.meta a{color:var(--accent);text-decoration:none}
.meta a:hover{text-decoration:underline}
.rating{display:inline-flex;align-items:center;gap:6px;background:var(--bg-secondary);padding:6px 12px;border-radius:6px}
.rating i{color:var(--warning)}
.cats{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.cat{background:var(--accent);color:#fff;padding:6px 14px;border-radius:6px;font-size:.8rem;font-weight:600;text-decoration:none;transition:.2s}
.cat:hover{filter:brightness(1.15)}
.tags{display:flex;flex-wrap:wrap;gap:8px}
.tag{background:var(--bg-secondary);color:var(--text-secondary);padding:6px 12px;border-radius:6px;font-size:.8rem;transition:.2s}
.tag:hover{color:var(--text-primary)}

/* Content Blocks */
.block{background:var(--bg-secondary);border-radius:12px;padding:28px;margin-bottom:24px}
.block-head{display:flex;align-items:center;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border);font-size:1.1rem;font-weight:600}
.block-head i{color:var(--accent)}
.desc{color:var(--text-secondary);line-height:1.8;white-space:pre-wrap}

/* Sidebar */
.side{position:sticky;top:24px}

/* Purchase Card */
.buy-card{background:var(--bg-secondary);border-radius:12px;overflow:hidden;margin-bottom:20px}
.cover{aspect-ratio:1/1;position:relative;overflow:hidden}
.cover img{width:100%;height:100%;object-fit:cover}
.cover .badge{position:absolute;top:12px;left:12px;padding:6px 12px;border-radius:6px;font-size:.75rem;font-weight:700;text-transform:uppercase}
.badge-off{background:var(--success);color:#fff}
.badge-own{background:var(--accent);color:#fff}
.buy-body{padding:24px}
.price-row{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.price{font-size:2rem;font-weight:700}
.price.free{color:var(--success)}
.price-old{color:var(--text-secondary);text-decoration:line-through;font-size:1rem}
.disc{background:var(--success);color:#fff;padding:4px 10px;border-radius:4px;font-weight:700;font-size:.85rem}
.owned{display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;background:rgba(40,167,69,.1);border:1px solid var(--success);border-radius:8px;color:var(--success);font-weight:600;margin-bottom:16px}
.unavail{text-align:center;padding:20px;background:rgba(255,193,7,.08);border:1px solid var(--warning);border-radius:8px;margin-bottom:16px}
.unavail i{font-size:32px;color:var(--warning);margin-bottom:10px}
.unavail h4{color:var(--warning);font-size:.9rem}
.btns{display:flex;flex-direction:column;gap:10px}
.btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:14px 20px;border-radius:8px;font-size:.95rem;font-weight:600;border:none;cursor:pointer;text-decoration:none;transition:.2s}
.btn-p{background:var(--accent);color:#fff}
.btn-p:hover{filter:brightness(1.1)}
.btn-s{background:var(--success);color:#fff}
.btn-s:hover{filter:brightness(1.1)}
.btn-o{background:transparent;border:1px solid var(--border);color:var(--text-primary)}
.btn-o:hover{border-color:var(--accent);color:var(--accent)}
.btn-o.wl.active{border-color:var(--danger);color:var(--danger)}
.plats{display:flex;align-items:center;gap:12px;padding-top:16px;margin-top:16px;border-top:1px solid var(--border);font-size:.85rem;color:var(--text-secondary)}
.plats-icons{display:flex;gap:12px;font-size:1.2rem}

/* Info Cards */
.info-card{background:var(--bg-secondary);border-radius:12px;padding:20px;margin-bottom:16px}
.info-card h4{font-size:.8rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px}
.info-list{display:flex;flex-direction:column;gap:12px}
.info-item{display:flex;justify-content:space-between;font-size:.9rem}
.info-item .l{color:var(--text-secondary)}
.info-item .v{font-weight:500}
.dev-link{display:flex;align-items:center;gap:12px;padding:14px;background:var(--bg-tertiary);border-radius:8px;text-decoration:none;transition:.2s}
.dev-link:hover{background:#303133}
.dev-link img{width:48px;height:48px;border-radius:8px;object-fit:cover}
.dev-link .n{font-weight:600;display:flex;align-items:center;gap:6px;margin-bottom:4px}
.dev-link .n i{color:var(--accent);font-size:.75rem}
.dev-link .r{font-size:.8rem;color:var(--text-secondary)}
.req-tabs{display:flex;gap:8px;margin-bottom:16px}
.req-tab{flex:1;padding:10px;background:var(--bg-tertiary);border:none;border-radius:6px;color:var(--text-secondary);font-size:.85rem;font-weight:500;cursor:pointer;transition:.2s}
.req-tab.active{background:var(--accent);color:#fff}
.req-txt{font-size:.85rem;color:var(--text-secondary);line-height:1.6;white-space:pre-wrap}

/* Reviews */
.rev-sum{display:flex;gap:24px;padding:20px;background:var(--bg-tertiary);border-radius:10px;margin-bottom:20px}
.rev-score{text-align:center;min-width:100px}
.rev-score .num{font-size:3rem;font-weight:700;color:var(--accent);line-height:1}
.rev-score .stars{display:flex;justify-content:center;gap:2px;margin:8px 0;color:var(--warning);font-size:.85rem}
.rev-score .cnt{font-size:.8rem;color:var(--text-secondary)}
.rev-bars{flex:1}
.bar-row{display:flex;align-items:center;gap:10px;margin-bottom:6px;font-size:.8rem;color:var(--text-secondary)}
.bar-track{flex:1;height:8px;background:var(--bg-primary);border-radius:4px;overflow:hidden}
.bar-fill{height:100%;background:var(--accent);border-radius:4px}
.rev-card{padding:20px;background:var(--bg-tertiary);border-radius:10px;margin-bottom:12px}
.rev-card.mine{border:1px solid var(--accent);position:relative}
.rev-card.mine::before{content:'Sua Avaliação';position:absolute;top:-10px;left:16px;background:var(--accent);color:#fff;padding:2px 10px;border-radius:4px;font-size:.7rem;font-weight:700;text-transform:uppercase}
.rev-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
.rev-author{display:flex;align-items:center;gap:12px}
.rev-author img{width:40px;height:40px;border-radius:50%;object-fit:cover}
.rev-author .name{font-weight:600;font-size:.95rem;margin-bottom:4px}
.rev-author .meta{display:flex;align-items:center;gap:8px;font-size:.8rem}
.rev-author .meta .stars{color:var(--warning)}
.rev-author .meta .date{color:var(--text-secondary)}
.rev-acts{display:flex;gap:6px}
.rev-acts button{padding:6px 10px;background:transparent;border:1px solid var(--border);border-radius:4px;color:var(--text-secondary);font-size:.75rem;cursor:pointer;transition:.2s}
.rev-acts button:hover{border-color:var(--accent);color:var(--accent)}
.rev-acts button.del:hover{border-color:var(--danger);color:var(--danger)}
.rev-txt{font-size:.9rem;color:var(--text-secondary);line-height:1.6}
.write-rev{width:100%;padding:14px;background:var(--bg-tertiary);border:1px dashed var(--border);border-radius:8px;color:var(--text-primary);font-size:.9rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s;margin-bottom:16px}
.write-rev:hover{border-color:var(--accent);color:var(--accent)}
.no-rev{text-align:center;padding:40px;color:var(--text-secondary)}
.no-rev i{font-size:40px;opacity:.3;margin-bottom:12px}

/* Related Games */
.related{padding:60px 0}
.related h2{font-size:1.5rem;font-weight:600;margin-bottom:24px}
.rel-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
.rel-card{background:var(--bg-secondary);border-radius:12px;overflow:hidden;text-decoration:none;transition:.3s;border:1px solid transparent}
.rel-card:hover{transform:translateY(-6px);border-color:var(--accent);box-shadow:0 12px 40px rgba(0,0,0,.4)}
.rel-cover{aspect-ratio:1/1;overflow:hidden}
.rel-cover img{width:100%;height:100%;object-fit:cover;transition:.3s}
.rel-card:hover .rel-cover img{transform:scale(1.05)}
.rel-info{padding:16px}
.rel-info h4{font-size:.95rem;font-weight:600;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text-primary)}
.rel-info .dev{font-size:.8rem;color:var(--text-secondary);margin-bottom:10px}
.rel-price{display:flex;align-items:center;gap:8px;font-size:.9rem}
.rel-price .off{background:var(--success);color:#fff;padding:2px 6px;border-radius:4px;font-size:.7rem;font-weight:700}
.rel-price .old{color:var(--text-secondary);text-decoration:line-through;font-size:.8rem}
.rel-price .cur{font-weight:600;color:var(--text-primary)}
.rel-price .free{color:var(--success);font-weight:600}

/* Modal */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.9);display:none;align-items:center;justify-content:center;z-index:9999;padding:20px}
.modal.show{display:flex}
.modal-box{background:var(--bg-secondary);border-radius:12px;width:100%;max-width:480px;animation:modalIn .2s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-head{display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid var(--border)}
.modal-head h3{font-size:1.1rem}
.modal-close{width:32px;height:32px;border-radius:6px;border:none;background:var(--bg-tertiary);color:var(--text-secondary);font-size:1rem;cursor:pointer;transition:.2s}
.modal-close:hover{background:var(--border);color:var(--text-primary)}
.modal-body{padding:24px}
.star-sel{display:flex;justify-content:center;gap:8px;margin-bottom:24px}
.star-sel i{font-size:2.2rem;color:var(--border);cursor:pointer;transition:.15s}
.star-sel i:hover,.star-sel i.on{color:var(--warning);transform:scale(1.1)}
.rev-input{width:100%;min-height:120px;background:var(--bg-primary);border:1px solid var(--border);border-radius:8px;padding:16px;color:var(--text-primary);font-size:.9rem;font-family:inherit;resize:vertical}
.rev-input:focus{outline:none;border-color:var(--accent)}
.modal-foot{display:flex;gap:12px;padding:20px 24px;border-top:1px solid var(--border)}
.modal-foot .btn{flex:1}

/* Toast */
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(100px);background:var(--bg-secondary);border:1px solid var(--border);padding:14px 24px;border-radius:8px;display:flex;align-items:center;gap:10px;font-size:.9rem;z-index:10000;opacity:0;transition:.3s}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.toast.success{border-color:var(--success)}.toast.success i{color:var(--success)}
.toast.error{border-color:var(--danger)}.toast.error i{color:var(--danger)}

/* ========== RESPONSIVE ========== */
@media(max-width:1024px){
    .layout{grid-template-columns:1fr 320px;gap:24px}
    .rel-grid{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:768px){
    .hero{height:260px}
    .layout{display:block;margin-top:-80px}
    .side{position:relative;top:0;margin-bottom:24px}
    .cover{aspect-ratio:16/9}
    .title{font-size:1.8rem}
    .rel-grid{grid-template-columns:repeat(2,1fr);gap:12px}
    .rev-sum{flex-direction:column;text-align:center}
    .rel-info{padding:12px}
    .rel-info h4{font-size:.85rem}
}
@media(max-width:480px){
    .gc{padding:0 16px}
    .block{padding:20px}
    .buy-body{padding:20px}
    .rel-grid{gap:10px}
    .thumb{flex:0 0 120px;height:68px}
}
</style>

<div class="gp">
    <!-- Hero Banner -->
    <div class="hero"><div class="hero-bg"></div></div>
    
    <div class="gc">
        <div class="layout">
            <!-- Main Content -->
            <div class="main">
                <!-- Media Gallery -->
                <?php if ($jogo['video_trailer'] || count($imagens) > 0): ?>
                <div class="media">
                    <div class="media-view" id="mediaView">
                        <?php if ($jogo['video_trailer']): ?>
                            <iframe src="<?= $jogo['video_trailer'] ?>" allowfullscreen></iframe>
                        <?php elseif (count($imagens) > 0): ?>
                            <img src="<?= SITE_URL . $imagens[0]['imagem'] ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <?php if (($jogo['video_trailer'] ? 1 : 0) + count($imagens) > 1): ?>
                    <div class="thumbs">
                        <?php if ($jogo['video_trailer']): ?>
                        <div class="thumb vid active" data-type="video" data-src="<?= $jogo['video_trailer'] ?>">
                            <img src="https://img.youtube.com/vi/<?= getYoutubeId($jogo['video_trailer']) ?>/mqdefault.jpg" alt="">
                        </div>
                        <?php endif; ?>
                        <?php foreach ($imagens as $i => $img): ?>
                        <div class="thumb <?= (!$jogo['video_trailer'] && $i === 0) ? 'active' : '' ?>" data-type="image" data-src="<?= SITE_URL . $img['imagem'] ?>">
                            <img src="<?= SITE_URL . $img['imagem'] ?>" alt="">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Game Info -->
                <div class="info">
                    <h1 class="title"><?= sanitize($jogo['titulo']) ?></h1>
                    <div class="meta">
                        <span class="rating"><i class="fas fa-star"></i> <strong><?= number_format($jogo['nota_media'], 1) ?></strong> <span style="color:var(--text-secondary)">(<?= $total_reviews ?>)</span></span>
                        <span><i class="fas fa-building"></i> <a href="<?= SITE_URL ?>/pages/desenvolvedor.php?slug=<?= $jogo['dev_slug'] ?>"><?= sanitize($jogo['nome_estudio']) ?></a></span>
                        <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($jogo['data_lancamento'] ?? $jogo['criado_em'])) ?></span>
                        <span><i class="fas fa-download"></i> <?= number_format($jogo['total_vendas'], 0, ',', '.') ?></span>
                        <?php if ($jogo['classificacao_etaria'] > 0): ?>
                        <span><i class="fas fa-shield-alt"></i> <?= $jogo['classificacao_etaria'] ?>+</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($categorias)): ?>
                    <div class="cats">
                        <?php foreach ($categorias as $c): ?>
                        <a href="<?= SITE_URL ?>/pages/categoria.php?slug=<?= $c['slug'] ?>" class="cat"><?= sanitize($c['nome']) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($tags)): ?>
                    <div class="tags">
                        <?php foreach ($tags as $t): ?>
                        <span class="tag"><?= sanitize($t['nome']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="block">
                    <div class="block-head"><i class="fas fa-info-circle"></i> Sobre o Jogo</div>
                    <div class="desc"><?= nl2br(sanitize($jogo['descricao_completa'] ?: $jogo['descricao_curta'])) ?></div>
                </div>

                <!-- Reviews -->
                <div class="block" id="reviews">
                    <div class="block-head"><i class="fas fa-star"></i> Avaliações</div>
                    
                    <div class="rev-sum">
                        <div class="rev-score">
                            <div class="num"><?= number_format($jogo['nota_media'], 1) ?></div>
                            <div class="stars"><?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= round($jogo['nota_media']) ? 'fas' : 'far' ?> fa-star"></i><?php endfor; ?></div>
                            <div class="cnt"><?= $total_reviews ?> avaliações</div>
                        </div>
                        <div class="rev-bars">
                            <?php for ($r = 5; $r >= 1; $r--): $pct = $total_reviews > 0 ? ($rating_dist[$r] / $total_reviews) * 100 : 0; ?>
                            <div class="bar-row">
                                <span><?= $r ?></span>
                                <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
                                <span><?= $rating_dist[$r] ?></span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <?php if ($in_library): ?>
                        <?php if ($my_review): ?>
                        <div class="rev-card mine">
                            <div class="rev-top">
                                <div class="rev-author">
                                    <img src="<?= getAvatar($usuario['avatar_url'] ?? null) ?>" alt="">
                                    <div>
                                        <div class="name">Você</div>
                                        <div class="meta">
                                            <span class="stars"><?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= $my_review['nota'] ? 'fas' : 'far' ?> fa-star"></i><?php endfor; ?></span>
                                            <span class="date"><?= date('d/m/Y', strtotime($my_review['criado_em'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="rev-acts">
                                    <button onclick="openReview(<?= $my_review['nota'] ?>,'<?= addslashes(htmlspecialchars($my_review['comentario'])) ?>')"><i class="fas fa-edit"></i></button>
                                    <form action="<?= SITE_URL ?>/api/avaliar.php" method="POST" style="display:inline" onsubmit="return confirm('Remover avaliação?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="jogo_id" value="<?= $jogo['id'] ?>">
                                        <button type="submit" class="del"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <div class="rev-txt"><?= nl2br(sanitize($my_review['comentario'])) ?></div>
                        </div>
                        <?php else: ?>
                        <button class="write-rev" onclick="openReview()"><i class="fas fa-pen"></i> Escrever uma avaliação</button>
                        <?php endif; ?>
                    <?php elseif ($user_id): ?>
                    <div style="text-align:center;padding:16px;background:var(--bg-tertiary);border-radius:8px;color:var(--text-secondary);font-size:.9rem;margin-bottom:16px">
                        <i class="fas fa-info-circle"></i> Adquira o jogo para deixar sua avaliação
                    </div>
                    <?php endif; ?>

                    <?php $has_other = false; foreach ($avaliacoes as $rev): if ($rev['usuario_id'] == $user_id) continue; $has_other = true; ?>
                    <div class="rev-card">
                        <div class="rev-top">
                            <div class="rev-author">
                                <img src="<?= getAvatar($rev['avatar_url']) ?>" alt="">
                                <div>
                                    <div class="name"><?= sanitize($rev['nome_usuario']) ?></div>
                                    <div class="meta">
                                        <span class="stars"><?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= $rev['nota'] ? 'fas' : 'far' ?> fa-star"></i><?php endfor; ?></span>
                                        <span class="date"><?= date('d/m/Y', strtotime($rev['criado_em'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="rev-txt"><?= nl2br(sanitize($rev['comentario'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$has_other && !$my_review): ?>
                    <div class="no-rev"><i class="fas fa-comment-slash"></i><p>Nenhuma avaliação ainda</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="side">
                <!-- Purchase Card -->
                <div class="buy-card">
                    <div class="cover">
                        <img src="<?= SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png') ?>" alt="">
                        <?php if ($in_library): ?>
                        <?php elseif ($desconto > 0): ?>
                        <span class="badge badge-off">-<?= $desconto ?>%</span>
                        <?php endif; ?>
                    </div>
                    <div class="buy-body">
                        <?php if (!$tem_arquivo): ?>
                        <div class="unavail">
                            <i class="fas fa-clock"></i>
                            <h4>Em Breve</h4>
                            <p style="font-size:.8rem;color:var(--text-secondary)">Ainda não disponível</p>
                        </div>
                        <div class="btns">
                            <?php if ($user_id): ?>
                            <button class="btn btn-o wl <?= $in_wishlist ? 'active' : '' ?>" data-action="wishlist" data-id="<?= $jogo['id'] ?>">
                                <i class="fas fa-heart"></i> <span><?= $in_wishlist ? 'Na Lista' : 'Lista de Desejos' ?></span>
                            </button>
                            <?php else: ?>
                            <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-p"><i class="fas fa-sign-in-alt"></i> Entrar</a>
                            <?php endif; ?>
                        </div>

                        <?php elseif ($in_library): ?>
                        <div class="owned"><i class="fas fa-check-circle"></i> Você possui este jogo</div>
                        <div class="btns">
                            <a href="<?= SITE_URL ?>/user/download-jogo.php?jogo_id=<?= $jogo['id'] ?>" class="btn btn-s"><i class="fas fa-download"></i> Baixar</a>
                            <a href="<?= SITE_URL ?>/user/biblioteca.php" class="btn btn-o"><i class="fas fa-gamepad"></i> Biblioteca</a>
                        </div>

                        <?php else: ?>
                        <div class="price-row">
                            <?php if ($desconto > 0): ?>
                            <span class="disc">-<?= $desconto ?>%</span>
                            <span class="price-old"><?= formatPrice($jogo['preco_centavos']) ?></span>
                            <?php endif; ?>
                            <span class="price <?= $is_free ? 'free' : '' ?>"><?= $is_free ? 'Gratuito' : formatPrice($preco_final) ?></span>
                        </div>
                        <div class="btns">
                            <?php if ($user_id): ?>
                            <button class="btn <?= $in_cart ? 'btn-s' : 'btn-p' ?>" data-action="cart" data-id="<?= $jogo['id'] ?>">
                                <i class="fas <?= $in_cart ? 'fa-check' : 'fa-cart-plus' ?>"></i>
                                <span><?= $in_cart ? 'No Carrinho' : 'Adicionar ao Carrinho' ?></span>
                            </button>
                            <?php if ($in_cart): ?>
                            <a href="<?= SITE_URL ?>/user/carrinho.php" class="btn btn-o"><i class="fas fa-shopping-bag"></i> Finalizar Compra</a>
                            <?php endif; ?>
                            <button class="btn btn-o wl <?= $in_wishlist ? 'active' : '' ?>" data-action="wishlist" data-id="<?= $jogo['id'] ?>">
                                <i class="fas fa-heart"></i> <span><?= $in_wishlist ? 'Na Lista' : 'Lista de Desejos' ?></span>
                            </button>
                            <?php else: ?>
                            <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-p"><i class="fas fa-sign-in-alt"></i> Entrar para Comprar</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($plataformas)): ?>
                        <div class="plats">
                            <span>Plataformas</span>
                            <div class="plats-icons">
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
                    <h4>Desenvolvedor</h4>
                    <a href="<?= SITE_URL ?>/pages/desenvolvedor.php?slug=<?= $jogo['dev_slug'] ?>" class="dev-link">
                        <img src="<?= SITE_URL . ($jogo['logo_url'] ?: '/assets/images/default-dev.png') ?>" alt="">
                        <div>
                            <div class="n"><?= sanitize($jogo['nome_estudio']) ?> <?php if ($jogo['dev_verificado']): ?><i class="fas fa-check-circle"></i><?php endif; ?></div>
                            <div class="r">Ver perfil</div>
                        </div>
                    </a>
                </div>

                <!-- File Info -->
                <?php if ($arquivo_info): ?>
                <div class="info-card">
                    <h4>Informações</h4>
                    <div class="info-list">
                        <div class="info-item"><span class="l">Versão</span><span class="v"><?= sanitize($arquivo_info['versao']) ?></span></div>
                        <div class="info-item"><span class="l">Tamanho</span><span class="v"><?= formatFileSize($arquivo_info['tamanho_bytes']) ?></span></div>
                        <div class="info-item"><span class="l">Downloads</span><span class="v"><?= number_format($arquivo_info['downloads'], 0, ',', '.') ?></span></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Requirements -->
                <?php if ($jogo['requisitos_minimos'] || $jogo['requisitos_recomendados']): ?>
                <div class="info-card">
                    <h4>Requisitos</h4>
                    <div class="req-tabs">
                        <?php if ($jogo['requisitos_minimos']): ?>
                        <button class="req-tab active" data-req="min">Mínimos</button>
                        <?php endif; ?>
                        <?php if ($jogo['requisitos_recomendados']): ?>
                        <button class="req-tab <?= !$jogo['requisitos_minimos'] ? 'active' : '' ?>" data-req="rec">Recomendados</button>
                        <?php endif; ?>
                    </div>
                    <div class="req-txt" id="reqTxt"><?= sanitize($jogo['requisitos_minimos'] ?: $jogo['requisitos_recomendados']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Related Games -->
    <?php if (!empty($jogos_relacionados)): ?>
    <div class="gc">
        <div class="related">
            <h2>Jogos Relacionados</h2>
            <div class="rel-grid">
                <?php foreach ($jogos_relacionados as $rel): 
                    $rel_preco = ($rel['em_promocao'] && $rel['preco_promocional_centavos']) ? $rel['preco_promocional_centavos'] : $rel['preco_centavos'];
                    $rel_desc = calculateDiscount($rel['preco_centavos'], $rel['preco_promocional_centavos'], $rel['em_promocao']);
                ?>
                <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $rel['slug'] ?>" class="rel-card">
                    <div class="rel-cover">
                        <img src="<?= SITE_URL . ($rel['imagem_capa'] ?: '/assets/images/no-image.png') ?>" alt="">
                    </div>
                    <div class="rel-info">
                        <h4><?= sanitize($rel['titulo']) ?></h4>
                        <div class="dev"><?= sanitize($rel['nome_estudio']) ?></div>
                        <div class="rel-price">
                            <?php if ($rel_desc > 0): ?>
                            <span class="off">-<?= $rel_desc ?>%</span>
                            <span class="old"><?= formatPrice($rel['preco_centavos']) ?></span>
                            <?php endif; ?>
                            <?php if ($rel_preco == 0): ?>
                            <span class="free">Gratuito</span>
                            <?php else: ?>
                            <span class="cur"><?= formatPrice($rel_preco) ?></span>
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
<div class="modal" id="revModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="revTitle">Escrever Avaliação</h3>
            <button class="modal-close" onclick="closeReview()"><i class="fas fa-times"></i></button>
        </div>
        <form action="<?= SITE_URL ?>/api/avaliar.php" method="POST">
            <input type="hidden" name="action" id="revAction" value="add">
            <input type="hidden" name="jogo_id" value="<?= $jogo['id'] ?>">
            <input type="hidden" name="nota" id="revNota" required>
            <div class="modal-body">
                <div class="star-sel" id="starSel">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="far fa-star" data-v="<?= $i ?>"></i>
                    <?php endfor; ?>
                </div>
                <textarea name="comentario" id="revText" class="rev-input" placeholder="O que você achou?" required></textarea>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-o" onclick="closeReview()">Cancelar</button>
                <button type="submit" class="btn btn-p"><i class="fas fa-paper-plane"></i> Publicar</button>
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

// Media Gallery
document.querySelectorAll('.thumb').forEach(t => {
    t.onclick = function() {
        document.querySelectorAll('.thumb').forEach(x => x.classList.remove('active'));
        this.classList.add('active');
        const v = document.getElementById('mediaView');
        const {type, src} = this.dataset;
        v.innerHTML = type === 'video' ? `<iframe src="${src}" allowfullscreen></iframe>` : `<img src="${src}" alt="">`;
    };
});

// Requirements Tabs
document.querySelectorAll('.req-tab').forEach(t => {
    t.onclick = function() {
        document.querySelectorAll('.req-tab').forEach(x => x.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('reqTxt').textContent = this.dataset.req === 'min' ? reqMin : reqRec;
    };
});

// Review Modal
function openReview(n = 0, t = '') {
    if (!isLogged) { showToast('Faça login', 'error'); setTimeout(() => location.href = `${SITE_URL}/auth/login.php`, 1000); return; }
    document.getElementById('revModal').classList.add('show');
    document.getElementById('revAction').value = n ? 'update' : 'add';
    document.getElementById('revTitle').textContent = n ? 'Editar Avaliação' : 'Escrever Avaliação';
    document.getElementById('revNota').value = n;
    document.getElementById('revText').value = t;
    updateStars(n);
    document.body.style.overflow = 'hidden';
}

function closeReview() {
    document.getElementById('revModal').classList.remove('show');
    document.body.style.overflow = '';
}

const stars = document.querySelectorAll('#starSel i');
const notaInput = document.getElementById('revNota');

stars.forEach(s => {
    s.onclick = () => { notaInput.value = s.dataset.v; updateStars(s.dataset.v); };
    s.onmouseenter = () => updateStars(s.dataset.v);
});

document.getElementById('starSel').onmouseleave = () => updateStars(notaInput.value || 0);

function updateStars(v) {
    stars.forEach(s => {
        const val = +s.dataset.v;
        s.className = (val <= v ? 'fas' : 'far') + ' fa-star' + (val <= v ? ' on' : '');
    });
}

document.getElementById('revModal').onclick = e => { if (e.target.id === 'revModal') closeReview(); };
document.onkeydown = e => { if (e.key === 'Escape') closeReview(); };

// Actions
document.querySelectorAll('[data-action]').forEach(btn => {
    btn.onclick = async function() {
        if (!isLogged) { showToast('Faça login', 'error'); setTimeout(() => location.href = `${SITE_URL}/auth/login.php`, 1000); return; }
        this.disabled = true;
        const action = this.dataset.action;
        const id = this.dataset.id;
        const url = `${SITE_URL}/api/toggle-${action === 'cart' ? 'cart' : 'wishlist'}.php`;
        
        try {
            const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ jogo_id: id }) });
            const data = await res.json();
            if (data.success) {
                const added = data.action === 'added';
                document.querySelectorAll(`[data-action="${action}"][data-id="${id}"]`).forEach(b => {
                    const icon = b.querySelector('i');
                    const span = b.querySelector('span');
                    if (action === 'cart') {
                        b.className = b.className.replace(/btn-(p|s)/g, '') + (added ? ' btn-s' : ' btn-p');
                        if (icon) icon.className = `fas ${added ? 'fa-check' : 'fa-cart-plus'}`;
                        if (span) span.textContent = added ? 'No Carrinho' : 'Adicionar ao Carrinho';
                    } else {
                        b.classList.toggle('active', added);
                        if (span) span.textContent = added ? 'Na Lista' : 'Lista de Desejos';
                    }
                });
                if (action === 'cart' && added) location.reload();
                showToast(data.message, 'success');
            } else showToast(data.message, 'error');
        } catch (e) { showToast('Erro', 'error'); }
        this.disabled = false;
    };
});

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.className = 'toast ' + type;
    t.querySelector('i').className = 'fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}
</script>

<?php require_once '../includes/footer.php'; ?>