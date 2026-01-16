<?php
// developer/promocoes.php
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

$message = '';
$error = '';
$msgType = 'success';

// ============================================
// PROCESSAMENTO DE FORMULÁRIOS (OTIMIZADO)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    
    // Tratamento de Erros e Dados
    try {
        $acao = $_POST['acao'];

        switch ($acao) {
            // --- CRIAR PROMOÇÃO ---
            case 'criar_promocao':
                $pdo->beginTransaction();

                $nome = trim($_POST['nome']);
                $descricao = trim($_POST['descricao']);
                $percentual = (int)$_POST['percentual_desconto'];
                $data_inicio = $_POST['data_inicio'];
                $data_fim = $_POST['data_fim'];
                $jogos_ids = $_POST['jogos'] ?? [];

                if (empty($nome) || $percentual < 1 || $percentual > 100 || empty($jogos_ids)) {
                    throw new Exception('Preencha os campos obrigatórios corretamente.');
                }

                // Inserir Promoção
                $stmt = $pdo->prepare("
                    INSERT INTO dev_promocao (desenvolvedor_id, nome, descricao, percentual_desconto, data_inicio, data_fim) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$dev['id'], $nome, $descricao, $percentual, $data_inicio, $data_fim]);
                $promocao_id = $pdo->lastInsertId();

                // Lógica Otimizada: Preparar statement fora do loop
                $stmtJogo = $pdo->prepare("SELECT preco_centavos FROM jogo WHERE id = ? AND desenvolvedor_id = ?");
                $stmtInsertItem = $pdo->prepare("INSERT INTO dev_promocao_jogo (promocao_id, jogo_id, preco_original_centavos, preco_promocional_centavos) VALUES (?, ?, ?, ?)");
                $stmtUpdateJogo = $pdo->prepare("UPDATE jogo SET preco_promocional_centavos = ?, em_promocao = 1 WHERE id = ?");

                foreach ($jogos_ids as $jogo_id) {
                    $stmtJogo->execute([$jogo_id, $dev['id']]);
                    $jogo = $stmtJogo->fetch();

                    if ($jogo) {
                        $preco_original = $jogo['preco_centavos'];
                        $preco_promo = (int)($preco_original - (($preco_original * $percentual) / 100)); // Casting para int para evitar erros de centavos

                        $stmtInsertItem->execute([$promocao_id, $jogo_id, $preco_original, $preco_promo]);
                        $stmtUpdateJogo->execute([$preco_promo, $jogo_id]);
                    }
                }

                $pdo->commit();
                $_SESSION['flash_msg'] = ['Promoção criada com sucesso!', 'success'];
                break;

            // --- DELETAR PROMOÇÃO ---
            case 'deletar_promocao':
                $promo_id = $_POST['promocao_id'];
                
                // Remove status de promoção dos jogos afetados
                $stmt = $pdo->prepare("
                    UPDATE jogo j
                    JOIN dev_promocao_jogo dpj ON j.id = dpj.jogo_id
                    SET j.preco_promocional_centavos = NULL, j.em_promocao = 0
                    WHERE dpj.promocao_id = ?
                ");
                $stmt->execute([$promo_id]);

                // Deleta a promoção (Cascade cuida do resto no DB se configurado, ou deletamos manual)
                $stmt = $pdo->prepare("DELETE FROM dev_promocao_jogo WHERE promocao_id = ?"); // Segurança extra
                $stmt->execute([$promo_id]);
                
                $stmt = $pdo->prepare("DELETE FROM dev_promocao WHERE id = ? AND desenvolvedor_id = ?");
                $stmt->execute([$promo_id, $dev['id']]);
                
                $_SESSION['flash_msg'] = ['Promoção excluída com sucesso!', 'success'];
                break;

            // --- EDITAR PROMOÇÃO ---
            case 'editar_promocao':
                $pdo->beginTransaction();
                $promo_id = $_POST['promocao_id'];
                $percentual = (int)$_POST['percentual_desconto'];

                $stmt = $pdo->prepare("
                    UPDATE dev_promocao 
                    SET nome=?, descricao=?, percentual_desconto=?, data_inicio=?, data_fim=?
                    WHERE id=? AND desenvolvedor_id=?
                ");
                $stmt->execute([
                    trim($_POST['nome']), trim($_POST['descricao']), 
                    $percentual, $_POST['data_inicio'], $_POST['data_fim'], 
                    $promo_id, $dev['id']
                ]);

                // Recalcular preços
                $stmt = $pdo->prepare("SELECT jogo_id, preco_original_centavos FROM dev_promocao_jogo WHERE promocao_id = ?");
                $stmt->execute([$promo_id]);
                $jogos_promo = $stmt->fetchAll();

                $stmtItem = $pdo->prepare("UPDATE dev_promocao_jogo SET preco_promocional_centavos = ? WHERE promocao_id = ? AND jogo_id = ?");
                $stmtJogo = $pdo->prepare("UPDATE jogo SET preco_promocional_centavos = ? WHERE id = ?");

                foreach ($jogos_promo as $jp) {
                    $preco_promo = (int)($jp['preco_original_centavos'] - (($jp['preco_original_centavos'] * $percentual) / 100));
                    $stmtItem->execute([$preco_promo, $promo_id, $jp['jogo_id']]);
                    $stmtJogo->execute([$preco_promo, $jp['jogo_id']]);
                }

                $pdo->commit();
                $_SESSION['flash_msg'] = ['Promoção atualizada com sucesso!', 'success'];
                break;

            // --- TOGGLE STATUS PROMOÇÃO ---
            case 'toggle_promo':
                $promo_id = $_POST['promocao_id'];
                $stmt = $pdo->prepare("SELECT ativa FROM dev_promocao WHERE id = ? AND desenvolvedor_id = ?");
                $stmt->execute([$promo_id, $dev['id']]);
                $promo = $stmt->fetch();

                if ($promo) {
                    $novo_status = $promo['ativa'] ? 0 : 1;
                    $pdo->prepare("UPDATE dev_promocao SET ativa = ? WHERE id = ?")->execute([$novo_status, $promo_id]);

                    if ($novo_status == 0) {
                        $pdo->prepare("UPDATE jogo j JOIN dev_promocao_jogo dpj ON j.id = dpj.jogo_id SET j.preco_promocional_centavos = NULL, j.em_promocao = 0 WHERE dpj.promocao_id = ?")->execute([$promo_id]);
                    } else {
                        $pdo->prepare("UPDATE jogo j JOIN dev_promocao_jogo dpj ON j.id = dpj.jogo_id SET j.preco_promocional_centavos = dpj.preco_promocional_centavos, j.em_promocao = 1 WHERE dpj.promocao_id = ?")->execute([$promo_id]);
                    }
                    $_SESSION['flash_msg'] = ['Status da promoção alterado.', 'success'];
                }
                break;

            // --- CRIAR CUPOM ---
            case 'criar_cupom':
                $codigo = strtoupper(trim($_POST['codigo']));
                $stmt = $pdo->prepare("SELECT id FROM dev_cupom WHERE codigo = ?");
                $stmt->execute([$codigo]);
                if ($stmt->fetch()) throw new Exception('Este código de cupom já existe.');

                $stmt = $pdo->prepare("INSERT INTO dev_cupom (desenvolvedor_id, codigo, tipo_desconto, valor_desconto, jogo_id, usos_maximos, validade) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $dev['id'], $codigo, $_POST['tipo_desconto'], (int)$_POST['valor_desconto'],
                    !empty($_POST['jogo_id']) ? $_POST['jogo_id'] : NULL,
                    !empty($_POST['usos_maximos']) ? $_POST['usos_maximos'] : NULL,
                    !empty($_POST['validade']) ? $_POST['validade'] : NULL
                ]);
                $_SESSION['flash_msg'] = ['Cupom criado com sucesso!', 'success'];
                break;

            // --- EDITAR CUPOM ---
            case 'editar_cupom':
                $stmt = $pdo->prepare("UPDATE dev_cupom SET tipo_desconto=?, valor_desconto=?, jogo_id=?, usos_maximos=?, validade=?, ativo=? WHERE id=? AND desenvolvedor_id=?");
                $stmt->execute([
                    $_POST['tipo_desconto'], (int)$_POST['valor_desconto'],
                    !empty($_POST['jogo_id']) ? $_POST['jogo_id'] : NULL,
                    !empty($_POST['usos_maximos']) ? $_POST['usos_maximos'] : NULL,
                    !empty($_POST['validade']) ? $_POST['validade'] : NULL,
                    $_POST['ativo'], $_POST['cupom_id'], $dev['id']
                ]);
                $_SESSION['flash_msg'] = ['Cupom atualizado!', 'success'];
                break;

            // --- DELETAR CUPOM ---
            case 'deletar_cupom':
                $stmt = $pdo->prepare("DELETE FROM dev_cupom WHERE id = ? AND desenvolvedor_id = ?");
                $stmt->execute([$_POST['cupom_id'], $dev['id']]);
                $_SESSION['flash_msg'] = ['Cupom removido!', 'success'];
                break;
        }

        // Redireciona para evitar reenvio de formulário (PRG Pattern)
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
        $msgType = 'error';
    }
}

// Recuperar mensagens da sessão após redirect
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'][0];
    $msgType = $_SESSION['flash_msg'][1];
    unset($_SESSION['flash_msg']);
}

// ============================================
// BUSCAR DADOS DE EXIBIÇÃO
// ============================================

// Eventos e Dados Estáticos
$evento_atual = $pdo->query("SELECT * FROM evento WHERE ativo = 1 AND NOW() BETWEEN data_inicio AND data_fim ORDER BY data_fim ASC LIMIT 1")->fetch();

// Jogos
$jogos = $pdo->prepare("
    SELECT j.*, 
           (SELECT preco_promocional_centavos 
            FROM dev_promocao_jogo dpj 
            JOIN dev_promocao p ON dpj.promocao_id = p.id 
            WHERE dpj.jogo_id = j.id AND p.ativa = 1 AND p.desenvolvedor_id = j.desenvolvedor_id
            LIMIT 1) as preco_promo_ativo
    FROM jogo j
    WHERE j.desenvolvedor_id = ? AND j.status = 'publicado'
    ORDER BY j.titulo
");
$jogos->execute([$dev['id']]);
$jogos = $jogos->fetchAll();

// Promoções
$promocoes = $pdo->prepare("
    SELECT p.*, COUNT(dpj.id) as total_jogos
    FROM dev_promocao p
    LEFT JOIN dev_promocao_jogo dpj ON p.id = dpj.promocao_id
    WHERE p.desenvolvedor_id = ?
    GROUP BY p.id
    ORDER BY p.criado_em DESC
");
$promocoes->execute([$dev['id']]);
$promocoes = $promocoes->fetchAll();

// Cupons
$cupons = $pdo->prepare("
    SELECT c.*, j.titulo as jogo_titulo,
           (SELECT COUNT(*) FROM cupom_uso WHERE cupom_id = c.id) as total_usos_real
    FROM dev_cupom c
    LEFT JOIN jogo j ON c.jogo_id = j.id
    WHERE c.desenvolvedor_id = ?
    ORDER BY c.criado_em DESC
");
$cupons->execute([$dev['id']]);
$cupons = $cupons->fetchAll();

$page_title = 'Promoções e Cupons - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
    :root { --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12); --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1); --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15); --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px; }
    .dev-promo-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
    .promo-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; gap: 20px; flex-wrap: wrap; }
    .promo-title { font-size: 32px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 12px; }
    .promo-title i { color: var(--accent); }
    .header-actions { display: flex; gap: 12px; flex-wrap: wrap; }
    .tabs { display: flex; gap: 8px; border-bottom: 2px solid var(--border); margin-bottom: 32px; overflow-x: auto; scrollbar-width: thin; }
    .tabs::-webkit-scrollbar { height: 4px; }
    .tabs::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
    .tab { padding: 14px 24px; background: none; border: none; color: var(--text-secondary); cursor: pointer; font-weight: 600; font-size: 15px; border-bottom: 3px solid transparent; transition: var(--transition); white-space: nowrap; display: flex; align-items: center; gap: 8px; }
    .tab:hover { color: var(--text-primary); background: rgba(var(--accent-rgb, 76, 139, 245), 0.05); }
    .tab.active { color: var(--accent); border-bottom-color: var(--accent); }
    .tab-content { display: none; animation: fadeIn 0.3s ease-in; }
    .tab-content.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .games-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .game-monitor-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; transition: var(--transition); box-shadow: var(--shadow-sm); }
    .game-monitor-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--accent); }
    .game-monitor-card h4 { font-size: 17px; margin-bottom: 16px; color: var(--text-primary); font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .price-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding: 8px 0; }
    .price-label { font-size: 13px; color: var(--text-secondary); font-weight: 500; }
    .price-value { font-size: 18px; font-weight: 700; color: var(--text-primary); }
    .price-value.promo { color: var(--success); }
    .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-badge.active { background: rgba(46, 204, 113, 0.15); color: var(--success); }
    .status-badge.inactive { background: rgba(149, 165, 166, 0.15); color: var(--text-secondary); }
    .promo-list { display: flex; flex-direction: column; gap: 20px; }
    .promo-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 24px; transition: var(--transition); box-shadow: var(--shadow-sm); }
    .promo-card:hover { box-shadow: var(--shadow-md); border-color: rgba(var(--accent-rgb, 76, 139, 245), 0.3); }
    .promo-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; gap: 20px; flex-wrap: wrap; }
    .promo-card-info { flex: 1; min-width: 250px; }
    .promo-card-title { font-size: 22px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
    .promo-card-desc { color: var(--text-secondary); font-size: 14px; line-height: 1.5; margin: 0; }
    .promo-meta { display: flex; flex-wrap: wrap; gap: 20px; margin: 16px 0; padding: 16px; background: var(--bg-primary); border-radius: var(--radius-sm); }
    .promo-meta span { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--text-secondary); font-weight: 500; }
    .promo-meta i { color: var(--accent); font-size: 16px; }
    .promo-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .evento-banner-alert { background: linear-gradient(135deg, rgba(76, 139, 245, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%); border: 2px solid var(--accent); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 32px; display: grid; grid-template-columns: auto 1fr auto; gap: 20px; align-items: center; box-shadow: var(--shadow-md); }
    .evento-banner-icon { width: 60px; height: 60px; background: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; }
    .evento-banner-content h3 { font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
    .evento-banner-content p { color: var(--text-secondary); font-size: 14px; line-height: 1.6; margin-bottom: 12px; }
    .evento-banner-meta { display: flex; align-items: center; gap: 8px; color: var(--accent); font-weight: 600; font-size: 14px; }
    .evento-banner-actions { display: flex; gap: 12px; flex-wrap: wrap; }
    .btn { padding: 12px 24px; border-radius: var(--radius-sm); font-weight: 600; font-size: 14px; cursor: pointer; border: none; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; text-decoration: none; box-shadow: var(--shadow-sm); }
    .btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .btn:active { transform: translateY(0); }
    .btn-primary { background: var(--accent); color: white; }
    .btn-primary:hover { background: var(--accent-hover, #5a8de8); }
    .btn-secondary { background: var(--bg-tertiary, #2a2a2a); color: var(--text-primary); border: 1px solid var(--border); }
    .btn-secondary:hover { background: var(--bg-secondary); border-color: var(--accent); }
    .btn-danger { background: var(--danger, #dc3545); color: white; }
    .btn-danger:hover { background: #c82333; }
    .btn-sm { padding: 8px 16px; font-size: 13px; }
    .form-modal { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; overflow-y: auto; }
    .form-modal.active { display: flex; animation: fadeIn 0.2s ease-in; }
    .form-content { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: var(--radius-lg); max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-lg); animation: slideUp 0.3s ease-out; }
    @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    .form-header { padding: 24px; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: var(--bg-secondary); z-index: 10; }
    .form-header h2 { font-size: 22px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 12px; margin: 0; }
    .form-header i { color: var(--accent); }
    .form-body { padding: 24px; }
    .form-footer { padding: 24px; border-top: 1px solid var(--border); display: flex; gap: 12px; justify-content: flex-end; position: sticky; bottom: 0; background: var(--bg-secondary); }
    .form-group { margin-bottom: 24px; }
    .form-label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 8px; color: var(--text-primary); }
    .form-label-optional { font-weight: 400; color: var(--text-secondary); font-size: 13px; }
    .form-control { width: 100%; padding: 12px 16px; background: var(--bg-primary); border: 2px solid var(--border); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 14px; transition: var(--transition); font-family: inherit; }
    .form-control:focus { outline: none; border-color: var(--accent); background: var(--bg-secondary); }
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .game-selector { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; max-height: 300px; overflow-y: auto; padding: 12px; background: var(--bg-primary); border-radius: var(--radius-sm); }
    .game-checkbox { display: flex; align-items: center; gap: 10px; padding: 12px; background: var(--bg-secondary); border: 2px solid var(--border); border-radius: var(--radius-sm); cursor: pointer; transition: var(--transition); }
    .game-checkbox:hover { border-color: var(--accent); background: rgba(var(--accent-rgb, 76, 139, 245), 0.05); }
    .game-checkbox:has(input:checked) { border-color: var(--accent); background: rgba(var(--accent-rgb, 76, 139, 245), 0.1); }
    .game-checkbox input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: var(--accent); }
    .alert { padding: 16px 20px; border-radius: var(--radius-sm); margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 500; animation: slideDown 0.3s ease-out; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .alert-success { background: rgba(46, 204, 113, 0.15); color: var(--success); border: 1px solid var(--success); }
    .alert-error { background: rgba(220, 53, 69, 0.15); color: var(--danger); border: 1px solid var(--danger); }
    .empty-state { text-align: center; padding: 80px 20px; color: var(--text-secondary); }
    .empty-state i { font-size: 64px; margin-bottom: 20px; opacity: 0.3; }
    .section-title { font-size: 20px; font-weight: 600; color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .section-title i { color: var(--accent); }
    
    @media (max-width: 1024px) { .games-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); } }
    @media (max-width: 768px) {
        .dev-promo-container { padding: 16px; }
        .promo-header { flex-direction: column; align-items: flex-start; }
        .header-actions { width: 100%; } .header-actions .btn { flex: 1; justify-content: center; }
        .games-grid { grid-template-columns: 1fr; }
        .promo-card-header { flex-direction: column; }
        .promo-actions { width: 100%; } .promo-actions .btn { flex: 1; }
        .evento-banner-alert { grid-template-columns: 1fr; text-align: center; }
        .evento-banner-icon { margin: 0 auto; }
        .form-grid-2 { grid-template-columns: 1fr; }
        .form-footer { flex-direction: column-reverse; } .form-footer .btn { width: 100%; justify-content: center; }
    }
</style>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>

        <div class="dev-content">
            <div class="dev-promo-container">

                <div class="promo-header">
                    <h1 class="promo-title"><i class="fas fa-tags"></i> Promoções e Cupons</h1>
                    <div class="header-actions">
                        <button onclick="openModal('promoModal')" class="btn btn-primary"><i class="fas fa-plus"></i> <span>Nova Promoção</span></button>
                        <button onclick="openModal('cupomModal')" class="btn btn-secondary"><i class="fas fa-ticket-alt"></i> <span>Novo Cupom</span></button>
                    </div>
                </div>

                <?php if ($message || $error): ?>
                    <div class="alert alert-<?= $msgType ?>">
                        <i class="fas <?= $msgType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                        <?= htmlspecialchars($message ?: $error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($evento_atual): ?>
                    <div class="evento-banner-alert">
                        <div class="evento-banner-icon"><i class="fas fa-calendar-star"></i></div>
                        <div class="evento-banner-content">
                            <h3><?= htmlspecialchars($evento_atual['nome']) ?> em Andamento!</h3>
                            <p>Aproveite para criar promoções durante este evento especial.</p>
                            <div class="evento-banner-meta">
                                <i class="fas fa-clock"></i> <span>Termina em <?= date('d/m/Y às H:i', strtotime($evento_atual['data_fim'])) ?></span>
                            </div>
                        </div>
                        <div class="evento-banner-actions">
                            <button onclick="openModal('promoModal')" class="btn btn-primary"><i class="fas fa-plus"></i> Criar Promoção</button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="tabs">
                    <button class="tab active" onclick="showTab('monitor')" data-tab="monitor"><i class="fas fa-chart-line"></i> <span>Monitor</span></button>
                    <button class="tab" onclick="showTab('promocoes')" data-tab="promocoes"><i class="fas fa-tags"></i> <span>Promoções (<?= count($promocoes) ?>)</span></button>
                    <button class="tab" onclick="showTab('cupons')" data-tab="cupons"><i class="fas fa-ticket-alt"></i> <span>Cupons (<?= count($cupons) ?>)</span></button>
                </div>

                <!-- Tab: Monitor -->
                <div id="tab-monitor" class="tab-content active">
                    <h3 class="section-title"><i class="fas fa-gamepad"></i> Status dos Jogos</h3>
                    <?php if (empty($jogos)): ?>
                        <div class="empty-state"><i class="fas fa-gamepad"></i><p>Você ainda não tem jogos publicados</p></div>
                    <?php else: ?>
                        <div class="games-grid">
                            <?php foreach ($jogos as $jogo): ?>
                                <div class="game-monitor-card">
                                    <h4><?= htmlspecialchars($jogo['titulo']) ?></h4>
                                    <div class="price-row"><span class="price-label">Preço Base:</span><span class="price-value"><?= formatPrice($jogo['preco_centavos']) ?></span></div>
                                    <?php if ($jogo['em_promocao']): ?>
                                        <div class="price-row"><span class="price-label">Preço Atual:</span><span class="price-value promo"><?= formatPrice($jogo['preco_promocional_centavos']) ?></span></div>
                                        <div style="margin-top: 16px;"><span class="status-badge active"><i class="fas fa-fire"></i> Em Promoção</span></div>
                                    <?php else: ?>
                                        <div style="margin-top: 16px;"><span class="status-badge inactive">Sem Promoção</span></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tab: Promoções -->
                <div id="tab-promocoes" class="tab-content">
                    <?php if (empty($promocoes)): ?>
                        <div class="empty-state"><i class="fas fa-tags"></i><p>Nenhuma promoção criada ainda</p></div>
                    <?php else: ?>
                        <div class="promo-list">
                            <?php foreach ($promocoes as $promo): ?>
                                <div class="promo-card">
                                    <div class="promo-card-header">
                                        <div class="promo-card-info">
                                            <h3 class="promo-card-title"><?= htmlspecialchars($promo['nome']) ?></h3>
                                            <?php if ($promo['descricao']): ?><p class="promo-card-desc"><?= htmlspecialchars($promo['descricao']) ?></p><?php endif; ?>
                                        </div>
                                        <div class="promo-actions">
                                            <button onclick='editarPromocao(<?= json_encode($promo, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Editar</button>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="acao" value="toggle_promo">
                                                <input type="hidden" name="promocao_id" value="<?= $promo['id'] ?>">
                                                <button type="submit" class="btn <?= $promo['ativa'] ? 'btn-danger' : 'btn-primary' ?> btn-sm">
                                                    <i class="fas fa-power-off"></i> <?= $promo['ativa'] ? 'Desativar' : 'Ativar' ?>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Excluir esta promoção? Os preços dos jogos serão restaurados.')">
                                                <input type="hidden" name="acao" value="deletar_promocao">
                                                <input type="hidden" name="promocao_id" value="<?= $promo['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="promo-meta">
                                        <span><i class="fas fa-percent"></i> <?= $promo['percentual_desconto'] ?>% OFF</span>
                                        <span><i class="fas fa-gamepad"></i> <?= $promo['total_jogos'] ?> jogos</span>
                                        <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($promo['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($promo['data_fim'])) ?></span>
                                        <span class="status-badge <?= $promo['ativa'] ? 'active' : 'inactive' ?>"><?= $promo['ativa'] ? 'Ativa' : 'Inativa' ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tab: Cupons -->
                <div id="tab-cupons" class="tab-content">
                    <?php if (empty($cupons)): ?>
                        <div class="empty-state"><i class="fas fa-ticket-alt"></i><p>Nenhum cupom criado ainda</p></div>
                    <?php else: ?>
                        <div class="promo-list">
                            <?php foreach ($cupons as $cupom): ?>
                                <div class="promo-card">
                                    <div class="promo-card-header">
                                        <div class="promo-card-info">
                                            <h3 class="promo-card-title"><code style="background: var(--bg-primary); padding: 4px 12px; border-radius: 6px; font-size: 18px;"><?= htmlspecialchars($cupom['codigo']) ?></code></h3>
                                            <p class="promo-card-desc"><?= $cupom['jogo_id'] ? 'Válido para: <strong>' . htmlspecialchars($cupom['jogo_titulo']) . '</strong>' : 'Válido para <strong>todos os seus jogos</strong>' ?></p>
                                        </div>
                                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                            <span class="status-badge <?= $cupom['ativo'] ? 'active' : 'inactive' ?>"><?= $cupom['ativo'] ? 'Ativo' : 'Inativo' ?></span>
                                            <button onclick='editarCupom(<?= json_encode($cupom, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button>
                                            
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Excluir este cupom?')">
                                                <input type="hidden" name="acao" value="deletar_cupom">
                                                <input type="hidden" name="cupom_id" value="<?= $cupom['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="promo-meta">
                                        <span><i class="fas fa-percent"></i> <?= $cupom['tipo_desconto'] == 'percentual' ? $cupom['valor_desconto'] . '% OFF' : formatPrice($cupom['valor_desconto']) . ' OFF' ?></span>
                                        <span><i class="fas fa-ticket-alt"></i> <?= $cupom['usos_atuais'] ?>/<?= $cupom['usos_maximos'] ?? '∞' ?> usos</span>
                                        <?php if ($cupom['validade']): ?><span><i class="fas fa-calendar-times"></i> Expira em <?= date('d/m/Y', strtotime($cupom['validade'])) ?></span><?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Modais -->
    <!-- Nova Promoção -->
    <div id="promoModal" class="form-modal">
        <div class="form-content">
            <div class="form-header"><h2><i class="fas fa-tags"></i> Nova Promoção</h2></div>
            <form method="POST">
                <input type="hidden" name="acao" value="criar_promocao">
                <div class="form-body">
                    <div class="form-group"><label class="form-label">Nome *</label><input type="text" name="nome" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Descrição</label><textarea name="descricao" class="form-control" rows="3"></textarea></div>
                    <div class="form-group"><label class="form-label">Desconto (%) *</label><input type="number" name="percentual_desconto" class="form-control" min="1" max="100" required></div>
                    <div class="form-grid-2">
                        <div class="form-group"><label class="form-label">Início *</label><input type="datetime-local" name="data_inicio" class="form-control" required></div>
                        <div class="form-group"><label class="form-label">Fim *</label><input type="datetime-local" name="data_fim" class="form-control" required></div>
                    </div>
                    <div class="form-group"><label class="form-label">Jogos *</label>
                        <div class="game-selector">
                            <?php foreach ($jogos as $j): ?>
                                <label class="game-checkbox"><input type="checkbox" name="jogos[]" value="<?= $j['id'] ?>"><span><?= htmlspecialchars($j['titulo']) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('promoModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Criar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Novo Cupom -->
    <div id="cupomModal" class="form-modal">
        <div class="form-content">
            <div class="form-header"><h2><i class="fas fa-ticket-alt"></i> Novo Cupom</h2></div>
            <form method="POST">
                <input type="hidden" name="acao" value="criar_cupom">
                <div class="form-body">
                    <div class="form-group"><label class="form-label">Código *</label><input type="text" name="codigo" class="form-control" required style="text-transform: uppercase;"></div>
                    <div class="form-grid-2">
                        <div class="form-group"><label class="form-label">Tipo *</label><select name="tipo_desconto" class="form-control"><option value="percentual">Percentual (%)</option><option value="fixo">Valor Fixo (R$)</option></select></div>
                        <div class="form-group"><label class="form-label">Valor *</label><input type="number" name="valor_desconto" class="form-control" min="1" required></div>
                    </div>
                    <div class="form-group"><label class="form-label">Aplicar a:</label>
                        <select name="jogo_id" class="form-control"><option value="">Todos os jogos</option><?php foreach ($jogos as $j): ?><option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['titulo']) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label class="form-label">Usos Máximos</label><input type="number" name="usos_maximos" class="form-control" min="1"></div>
                        <div class="form-group"><label class="form-label">Validade</label><input type="date" name="validade" class="form-control"></div>
                    </div>
                </div>
                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('cupomModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Criar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Editar Promoção -->
    <div id="editPromoModal" class="form-modal">
        <div class="form-content">
            <div class="form-header"><h2><i class="fas fa-edit"></i> Editar Promoção</h2></div>
            <form method="POST">
                <input type="hidden" name="acao" value="editar_promocao">
                <input type="hidden" name="promocao_id" id="edit_promo_id">
                <div class="form-body">
                    <div class="form-group"><label class="form-label">Nome *</label><input type="text" name="nome" id="edit_promo_nome" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Descrição</label><textarea name="descricao" id="edit_promo_desc" class="form-control" rows="3"></textarea></div>
                    <div class="form-group"><label class="form-label">Desconto (%) *</label><input type="number" name="percentual_desconto" id="edit_promo_perc" class="form-control" min="1" max="100" required></div>
                    <div class="form-grid-2">
                        <div class="form-group"><label class="form-label">Início *</label><input type="datetime-local" name="data_inicio" id="edit_promo_inicio" class="form-control" required></div>
                        <div class="form-group"><label class="form-label">Fim *</label><input type="datetime-local" name="data_fim" id="edit_promo_fim" class="form-control" required></div>
                    </div>
                </div>
                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editPromoModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Editar Cupom -->
    <div id="editCupomModal" class="form-modal">
        <div class="form-content">
            <div class="form-header"><h2><i class="fas fa-edit"></i> Editar Cupom</h2></div>
            <form method="POST">
                <input type="hidden" name="acao" value="editar_cupom">
                <input type="hidden" name="cupom_id" id="edit_cupom_id">
                <div class="form-body">
                    <div class="form-group"><label class="form-label">Código</label><input type="text" class="form-control" id="edit_cupom_codigo" disabled style="opacity: 0.7;"></div>
                    <div class="form-grid-2">
                        <div class="form-group"><label class="form-label">Tipo *</label><select name="tipo_desconto" id="edit_cupom_tipo" class="form-control"><option value="percentual">Percentual (%)</option><option value="fixo">Valor Fixo (R$)</option></select></div>
                        <div class="form-group"><label class="form-label">Valor *</label><input type="number" name="valor_desconto" id="edit_cupom_valor" class="form-control" min="1" required></div>
                    </div>
                    <div class="form-group"><label class="form-label">Aplicar a:</label>
                        <select name="jogo_id" id="edit_cupom_jogo" class="form-control"><option value="">Todos os jogos</option><?php foreach ($jogos as $j): ?><option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['titulo']) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label class="form-label">Usos Máx.</label><input type="number" name="usos_maximos" id="edit_cupom_usos" class="form-control" min="1"></div>
                        <div class="form-group"><label class="form-label">Validade</label><input type="date" name="validade" id="edit_cupom_validade" class="form-control"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Status *</label><select name="ativo" id="edit_cupom_ativo" class="form-control"><option value="1">Ativo</option><option value="0">Inativo</option></select></div>
                </div>
                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCupomModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function showTab(tabName) {
        document.querySelectorAll('.tab, .tab-content').forEach(el => el.classList.remove('active'));
        document.querySelector(`.tab[data-tab="${tabName}"]`).classList.add('active');
        document.getElementById(`tab-${tabName}`).classList.add('active');
        localStorage.setItem('activePromoTab', tabName);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const savedTab = localStorage.getItem('activePromoTab');
        if (savedTab) showTab(savedTab);
    });

    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
        if(id.includes('promoModal') || id.includes('cupomModal')) {
            const form = document.querySelector(`#${id} form`);
            if(form) form.reset();
        }
    }

    // Modal close handlers
    document.querySelectorAll('.form-modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { const m = document.querySelector('.form-modal.active'); if (m) closeModal(m.id); } });

    // Populate Modals
    function editarPromocao(p) {
        document.getElementById('edit_promo_id').value = p.id;
        document.getElementById('edit_promo_nome').value = p.nome;
        document.getElementById('edit_promo_desc').value = p.descricao || '';
        document.getElementById('edit_promo_perc').value = p.percentual_desconto;
        document.getElementById('edit_promo_inicio').value = p.data_inicio.replace(' ', 'T').substring(0, 16);
        document.getElementById('edit_promo_fim').value = p.data_fim.replace(' ', 'T').substring(0, 16);
        openModal('editPromoModal');
    }

    function editarCupom(c) {
        document.getElementById('edit_cupom_id').value = c.id;
        document.getElementById('edit_cupom_codigo').value = c.codigo;
        document.getElementById('edit_cupom_tipo').value = c.tipo_desconto;
        document.getElementById('edit_cupom_valor').value = c.valor_desconto;
        document.getElementById('edit_cupom_jogo').value = c.jogo_id || '';
        document.getElementById('edit_cupom_usos').value = c.usos_maximos || '';
        document.getElementById('edit_cupom_validade').value = c.validade || '';
        document.getElementById('edit_cupom_ativo').value = c.ativo;
        openModal('editCupomModal');
    }

    // Form Handling
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            // Validações simples
            const checkboxes = this.querySelectorAll('[name="jogos[]"]');
            if (checkboxes.length > 0 && this.querySelectorAll('[name="jogos[]"]:checked').length === 0) {
                e.preventDefault(); alert('Selecione ao menos um jogo.'); return false;
            }

            // UI Feedback
            const btn = this.querySelector('[type="submit"]');
            if (btn) {
                const w = btn.offsetWidth;
                btn.style.width = w + 'px';
                btn.disabled = true; // Isso não quebra mais pois usamos hidden input 'acao'
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                // Fallback de segurança
                setTimeout(() => { btn.disabled = false; btn.innerHTML = originalHtml; }, 8000);
            }
        });
    });

    // Auto-hide alerts
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if(alert) { alert.style.opacity = '0'; setTimeout(() => alert.remove(), 500); }
    }, 5000);

    // Format Cupom Input
    const codigoInput = document.querySelector('[name="codigo"]');
    if(codigoInput) codigoInput.addEventListener('input', function() { this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, ''); });
</script>

<?php require_once '../includes/footer.php'; ?>