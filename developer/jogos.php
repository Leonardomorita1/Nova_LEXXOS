<?php
// developer/jogos.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Buscar desenvolvedor
$stmt = $pdo->prepare("SELECT * FROM desenvolvedor WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$dev = $stmt->fetch();

if (!$dev || $dev['status'] != 'ativo') {
    header('Location: ' . SITE_URL . '/developer/dashboard.php');
    exit;
}

// Filtros
$status_filter = $_GET['status'] ?? 'todos';
$ordem = $_GET['ordem'] ?? 'recente';

// Construir query
$where = ["j.desenvolvedor_id = ?"];
$params = [$dev['id']];

if ($status_filter != 'todos') {
    $where[] = "j.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where);

// Ordenação
$order_by = match($ordem) {
    'vendas' => 'j.total_vendas DESC',
    'nota' => 'j.nota_media DESC',
    'alfabetica' => 'j.titulo ASC',
    default => 'j.criado_em DESC'
};

// Buscar jogos
$stmt = $pdo->prepare("
    SELECT j.*,
           COUNT(DISTINCT a.id) as total_avaliacoes_count,
           SUM(CASE WHEN ip.id IS NOT NULL THEN ip.valor_final_centavos ELSE 0 END) as receita_total
    FROM jogo j
    LEFT JOIN avaliacao a ON j.id = a.jogo_id AND a.status = 'ativo'
    LEFT JOIN item_pedido ip ON j.id = ip.jogo_id
    WHERE $where_clause
    GROUP BY j.id
    ORDER BY $order_by
");
$stmt->execute($params);
$jogos = $stmt->fetchAll();

// Estatísticas gerais
$total_jogos = count($jogos);
$jogos_publicados = count(array_filter($jogos, fn($j) => $j['status'] == 'publicado'));
$jogos_rascunho = count(array_filter($jogos, fn($j) => $j['status'] == 'rascunho'));
$jogos_revisao = count(array_filter($jogos, fn($j) => $j['status'] == 'em_revisao'));

$page_title = 'Meus Jogos - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="dev-content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-gamepad"></i> Meus Jogos
                </h1>
                <div style="display: flex; gap: 15px; margin-top: 15px;">
                    <a href="<?php echo SITE_URL; ?>/developer/publicar-jogo.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Publicar Novo Jogo
                    </a>
                </div>
            </div>
            
            <!-- Estatísticas Rápidas -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 20px; text-align: center;">
                    <div style="font-size: 32px; font-weight: 700; color: var(--accent);"><?php echo $total_jogos; ?></div>
                    <div style="color: var(--text-secondary); font-size: 14px;">Total de Jogos</div>
                </div>
                
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 20px; text-align: center;">
                    <div style="font-size: 32px; font-weight: 700; color: var(--success);"><?php echo $jogos_publicados; ?></div>
                    <div style="color: var(--text-secondary); font-size: 14px;">Publicados</div>
                </div>
                
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 20px; text-align: center;">
                    <div style="font-size: 32px; font-weight: 700; color: var(--warning);"><?php echo $jogos_revisao; ?></div>
                    <div style="color: var(--text-secondary); font-size: 14px;">Em Revisão</div>
                </div>
                
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 20px; text-align: center;">
                    <div style="font-size: 32px; font-weight: 700; color: var(--text-secondary);"><?php echo $jogos_rascunho; ?></div>
                    <div style="color: var(--text-secondary); font-size: 14px;">Rascunhos</div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 30px;">
                <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="todos" <?php echo $status_filter == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="publicado" <?php echo $status_filter == 'publicado' ? 'selected' : ''; ?>>Publicados</option>
                            <option value="em_revisao" <?php echo $status_filter == 'em_revisao' ? 'selected' : ''; ?>>Em Revisão</option>
                            <option value="rascunho" <?php echo $status_filter == 'rascunho' ? 'selected' : ''; ?>>Rascunhos</option>
                            <option value="suspenso" <?php echo $status_filter == 'suspenso' ? 'selected' : ''; ?>>Suspensos</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                        <label class="form-label">Ordenar por</label>
                        <select name="ordem" class="form-control">
                            <option value="recente" <?php echo $ordem == 'recente' ? 'selected' : ''; ?>>Mais Recentes</option>
                            <option value="vendas" <?php echo $ordem == 'vendas' ? 'selected' : ''; ?>>Mais Vendidos</option>
                            <option value="nota" <?php echo $ordem == 'nota' ? 'selected' : ''; ?>>Melhor Avaliados</option>
                            <option value="alfabetica" <?php echo $ordem == 'alfabetica' ? 'selected' : ''; ?>>A-Z</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    
                    <?php if ($status_filter != 'todos' || $ordem != 'recente'): ?>
                        <a href="<?php echo SITE_URL; ?>/developer/jogos.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Lista de Jogos -->
            <?php if (count($jogos) > 0): ?>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <?php foreach ($jogos as $jogo): ?>
                    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 20px;">
                        <div style="display: grid; grid-template-columns: 120px 1fr auto; gap: 20px; align-items: center;">
                            <!-- Imagem -->
                            <img src="<?php echo SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                                 style="width: 100%; aspect-ratio: 3/4; object-fit: cover; border-radius: 8px;"
                                 alt="<?php echo sanitize($jogo['titulo']); ?>">
                            
                            <!-- Informações -->
                            <div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <h3 style="font-size: 20px; margin: 0;">
                                        <?php echo sanitize($jogo['titulo']); ?>
                                    </h3>
                                    <span style="padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;
                                        <?php
                                        echo match($jogo['status']) {
                                            'publicado' => 'background: rgba(40,167,69,0.1); color: var(--success);',
                                            'em_revisao' => 'background: rgba(255,193,7,0.1); color: var(--warning);',
                                            'rascunho' => 'background: rgba(108,117,125,0.1); color: var(--text-secondary);',
                                            'suspenso' => 'background: rgba(220,53,69,0.1); color: var(--danger);',
                                            default => ''
                                        };
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $jogo['status'])); ?>
                                    </span>
                                </div>
                                
                                <div style="display: flex; gap: 20px; margin-top: 12px; font-size: 14px; color: var(--text-secondary);">
                                    <span><i class="fas fa-download"></i> <?php echo $jogo['total_vendas']; ?> vendas</span>
                                    <span><i class="fas fa-star"></i> <?php echo number_format($jogo['nota_media'], 1); ?> (<?php echo $jogo['total_avaliacoes_count']; ?>)</span>
                                    <span><i class="fas fa-eye"></i> <?php echo $jogo['total_visualizacoes']; ?> views</span>
                                    <span><i class="fas fa-dollar-sign"></i> <?php echo formatPrice($jogo['receita_total'] ?? 0); ?></span>
                                </div>
                                
                                <div style="margin-top: 12px;">
                                    <strong>Preço:</strong> 
                                    <?php
                                    if ($jogo['preco_centavos'] == 0) {
                                        echo '<span style="color: var(--success);">Grátis</span>';
                                    } else {
                                        echo formatPrice($jogo['preco_centavos']);
                                        if ($jogo['em_promocao'] && $jogo['preco_promocional_centavos']) {
                                            echo ' <span style="color: var(--success);">→ ' . formatPrice($jogo['preco_promocional_centavos']) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <!-- Ações -->
                            <div style="display: flex; flex-direction: column; gap: 8px; min-width: 120px;">
                                <a href="<?php echo SITE_URL; ?>/developer/editar-jogo.php?id=<?php echo $jogo['id']; ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                
                                <a href="<?php echo SITE_URL; ?>/developer/arquivo-jogo.php?jogo=<?php echo $jogo['id']; ?>" 
                                   class="btn btn-secondary btn-sm">
                                    <i class="fas fa-file-archive"></i> Arquivos
                                </a>
                                
                                <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>" 
                                   class="btn btn-secondary btn-sm" target="_blank">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border-radius: 15px;">
                    <i class="fas fa-gamepad" style="font-size: 64px; color: var(--text-secondary); margin-bottom: 20px;"></i>
                    <h3>Nenhum jogo encontrado</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 30px;">
                        <?php if ($status_filter != 'todos'): ?>
                            Não há jogos com este status.
                        <?php else: ?>
                            Comece publicando seu primeiro jogo!
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo SITE_URL; ?>/developer/publicar-jogo.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Publicar Jogo
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>