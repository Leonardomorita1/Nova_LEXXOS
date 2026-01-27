<?php
// config/config.php
session_start();

// Configurações do site
define('SITE_NAME', 'Lexxos');
define('SITE_LOGO', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300">
                        <path fill="currentColor" d="M810,-2.166556q0,0-582,734.641212-104-2.374917-104-2.374917t311.189574-392.395028c0,0-311.189574-319.288648-311.189574-319.288648s104,0,104,0L481,281.24029q2,1.583279,230.533506-283.406846h98.466494M267,845.679068q0,0,582-721.183347q103.574312,0,103.574312,0L652.630475,512.287658q-2,2.374917,263.412778,266.102074c0,0-91.741215,0-91.741215,0Q605,559.105663,603,561.480581L374.688025,845.679068" transform="matrix(0.302982 0 0 0.353838 -11.4894 0.76661)" />
                    </svg>');
define('SITE_URL', '');
define('BASE_PATH', __DIR__ . '/..');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Funções auxiliares globais
require_once BASE_PATH . '/includes/functions.php';

// Função para verificar se está logado
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Função para verificar tipo de usuário
function getUserType()
{
    return $_SESSION['user_type'] ?? 'cliente';
}

// Função para redirecionar se não estiver logado
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit;
    }
}

// Função para formatar preço
function formatPrice($centavos)
{
    return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
}

// Função para obter avatar padrão
function getAvatar($avatar_url)
{
    return $avatar_url ?: SITE_URL . '/assets/images/default-avatar.png';
}

// Proteção contra XSS
function sanitize($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// ============================================
// VERIFICAÇÕES DE SISTEMA
// ============================================

// Conectar ao banco para verificações
require_once __DIR__ . '/database.php';
$db_check = new Database();
$pdo_check = $db_check->getConnection();

// Verificar modo manutenção
$stmt = $pdo_check->prepare("SELECT valor FROM configuracao WHERE chave = 'modo_manutencao'");
$stmt->execute();
$modo_manutencao = $stmt->fetch()['valor'] ?? '0';

// Verificar se não é admin e site está em manutenção
if ($modo_manutencao == '1' && getUserType() !== 'admin') {
    // Não bloquear páginas de login/logout
    $current_page = basename($_SERVER['PHP_SELF']);
    $allowed_pages = ['login.php', 'logout.php'];

    if (!in_array($current_page, $allowed_pages)) {
        showMaintenancePage();
        exit;
    }
}

// Verificar se usuário está suspenso ou banido
if (isLoggedIn()) {
    $stmt = $pdo_check->prepare("SELECT status FROM usuario WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_status = $stmt->fetch()['status'] ?? 'ativo';

    // Permitir logout mesmo suspenso/banido
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page !== 'logout.php') {
        if ($user_status === 'suspenso') {
            showSuspendedPage();
            exit;
        } elseif ($user_status === 'banido') {
            showBannedPage();
            exit;
        }
    }

    // ============================================
    // LIMPEZA AUTOMÁTICA: CARRINHO E LISTA DE DESEJOS
    // Remove jogos que já estão na biblioteca do usuário
    // ============================================
    cleanupOwnedGames($pdo_check, $_SESSION['user_id']);
}

// ============================================
// FUNÇÃO DE LIMPEZA DE JOGOS JÁ POSSUÍDOS
// ============================================

/**
 * Remove jogos do carrinho e lista de desejos que já estão na biblioteca
 * Usa controle de frequência para não executar em toda requisição
 */
function cleanupOwnedGames($pdo, $user_id)
{
    // Controle de frequência: executar no máximo a cada 5 minutos por usuário
    $session_key = 'last_cleanup_' . $user_id;
    $cleanup_interval = 300; // 5 minutos em segundos

    if (isset($_SESSION[$session_key]) && (time() - $_SESSION[$session_key]) < $cleanup_interval) {
        return; // Ainda não é hora de executar novamente
    }

    try {
        // Remover do carrinho jogos que já estão na biblioteca
        $stmt = $pdo->prepare("
            DELETE FROM carrinho 
            WHERE usuario_id = ? 
            AND jogo_id IN (
                SELECT jogo_id FROM biblioteca WHERE usuario_id = ?
            )
        ");
        $stmt->execute([$user_id, $user_id]);
        $removed_cart = $stmt->rowCount();

        // Remover da lista de desejos jogos que já estão na biblioteca
        $stmt = $pdo->prepare("
            DELETE FROM lista_desejos 
            WHERE usuario_id = ? 
            AND jogo_id IN (
                SELECT jogo_id FROM biblioteca WHERE usuario_id = ?
            )
        ");
        $stmt->execute([$user_id, $user_id]);
        $removed_wishlist = $stmt->rowCount();

        // Atualizar timestamp da última limpeza
        $_SESSION[$session_key] = time();

        // Log opcional (pode ser removido em produção)
        if ($removed_cart > 0 || $removed_wishlist > 0) {
            error_log("Cleanup para usuário $user_id: $removed_cart do carrinho, $removed_wishlist da lista de desejos");
        }
    } catch (PDOException $e) {
        // Silenciosamente falha para não interromper a navegação
        error_log("Erro na limpeza de jogos possuídos: " . $e->getMessage());
    }
}

/**
 * Função auxiliar para forçar limpeza imediata
 * Útil após uma compra ser finalizada
 */
function forceCleanupOwnedGames($pdo, $user_id)
{
    // Remove o controle de tempo para forçar execução
    $session_key = 'last_cleanup_' . $user_id;
    unset($_SESSION[$session_key]);

    cleanupOwnedGames($pdo, $user_id);
}

// ============================================
// PROCESSAMENTO AUTOMÁTICO DE EVENTOS TEMPORAIS
// ============================================
if (isLoggedIn()) {
    processTimeBasedEvents($pdo_check);
}

/**
 * Processa eventos baseados em tempo (liberação de saldo, expiração de promoções, etc)
 * Usa controle de frequência para não executar em toda requisição
 */
function processTimeBasedEvents($pdo) {
    // Controle de frequência: executar no máximo a cada 10 minutos globalmente
    $session_key = 'last_time_events_check';
    $check_interval = 600; // 10 minutos em segundos
    
    if (isset($_SESSION[$session_key]) && (time() - $_SESSION[$session_key]) < $check_interval) {
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // 1. LIBERAR SALDO PENDENTE DOS DESENVOLVEDORES
        liberarSaldosPendentes($pdo);
        
        // 2. DESATIVAR PROMOÇÕES DE DESENVOLVEDORES EXPIRADAS
        desativarPromocoesExpiradas($pdo);
        
        // 3. DESATIVAR CUPONS EXPIRADOS
        desativarCuponsExpirados($pdo);
        
        // 4. DESATIVAR PROMOÇÕES GLOBAIS EXPIRADAS
        desativarPromocoesGlobaisExpiradas($pdo);
        
        // 5. DESATIVAR BANNERS EXPIRADOS
        desativarBannersExpirados($pdo);
        
        // 6. DESATIVAR EVENTOS EXPIRADOS
        desativarEventosExpirados($pdo);
        
        $pdo->commit();
        
        // Atualizar timestamp da última verificação
        $_SESSION[$session_key] = time();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro no processamento de eventos temporais: " . $e->getMessage());
    }
}

/**
 * Libera saldo pendente após o período de carência configurado
 */
function liberarSaldosPendentes($pdo) {
    // Buscar dias de liberação configurados
    $stmt = $pdo->query("SELECT valor FROM configuracao WHERE chave = 'dias_liberacao_saldo'");
    $dias_liberacao = (int)($stmt->fetch()['valor'] ?? 7);
    
    // Buscar vendas que já passaram do período de carência
    $stmt = $pdo->prepare("
        SELECT 
            dm.desenvolvedor_id,
            dm.valor_centavos,
            dm.id as movimentacao_id
        FROM desenvolvedor_movimentacao dm
        WHERE dm.tipo = 'venda'
        AND dm.criado_em <= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND NOT EXISTS (
            SELECT 1 FROM desenvolvedor_movimentacao dm2 
            WHERE dm2.tipo = 'liberacao' 
            AND dm2.referencia_id = dm.id
        )
    ");
    $stmt->execute([$dias_liberacao]);
    $vendas_liberar = $stmt->fetchAll();
    
    $total_liberado = 0;
    $devs_atualizados = [];
    
    foreach ($vendas_liberar as $venda) {
        // Registrar movimentação de liberação
        $stmt = $pdo->prepare("
            INSERT INTO desenvolvedor_movimentacao 
            (desenvolvedor_id, tipo, valor_centavos, descricao, referencia_tipo, referencia_id, criado_em)
            VALUES (?, 'liberacao', ?, 'Saldo liberado após período de carência', 'movimentacao', ?, NOW())
        ");
        $stmt->execute([
            $venda['desenvolvedor_id'],
            $venda['valor_centavos'],
            $venda['movimentacao_id']
        ]);
        
        // Acumular valor por desenvolvedor
        if (!isset($devs_atualizados[$venda['desenvolvedor_id']])) {
            $devs_atualizados[$venda['desenvolvedor_id']] = 0;
        }
        $devs_atualizados[$venda['desenvolvedor_id']] += $venda['valor_centavos'];
        $total_liberado++;
    }
    
    // Atualizar saldos dos desenvolvedores
    foreach ($devs_atualizados as $dev_id => $valor) {
        $stmt = $pdo->prepare("
            UPDATE desenvolvedor_saldo 
            SET saldo_disponivel_centavos = saldo_disponivel_centavos + ?,
                saldo_pendente_centavos = saldo_pendente_centavos - ?,
                atualizado_em = NOW()
            WHERE desenvolvedor_id = ?
        ");
        $stmt->execute([$valor, $valor, $dev_id]);
    }
    
    if ($total_liberado > 0) {
        error_log("Liberados $total_liberado saldos pendentes para " . count($devs_atualizados) . " desenvolvedores");
    }
}

/**
 * Desativa promoções de desenvolvedores que expiraram
 */
function desativarPromocoesExpiradas($pdo) {
    $stmt = $pdo->prepare("
        UPDATE dev_promocao 
        SET ativa = 0 
        WHERE ativa = 1 
        AND data_fim < NOW()
    ");
    $stmt->execute();
    $count = $stmt->rowCount();
    
    if ($count > 0) {
        // Remover preços promocionais dos jogos afetados
        $stmt = $pdo->prepare("
            UPDATE jogo j
            INNER JOIN dev_promocao_jogo dpj ON j.id = dpj.jogo_id
            INNER JOIN dev_promocao dp ON dpj.promocao_id = dp.id
            SET j.preco_promocional_centavos = NULL,
                j.em_promocao = 0
            WHERE dp.ativa = 0 
            AND dp.data_fim < NOW()
        ");
        $stmt->execute();
        
        error_log("Desativadas $count promoções de desenvolvedores expiradas");
    }
}

/**
 * Desativa cupons que expiraram ou atingiram limite de usos
 */
function desativarCuponsExpirados($pdo) {
    $stmt = $pdo->prepare("
        UPDATE dev_cupom 
        SET ativo = 0 
        WHERE ativo = 1 
        AND (
            (validade IS NOT NULL AND validade < CURDATE())
            OR (usos_maximos IS NOT NULL AND usos_atuais >= usos_maximos)
        )
    ");
    $stmt->execute();
    $count = $stmt->rowCount();
    
    if ($count > 0) {
        error_log("Desativados $count cupons expirados ou com limite atingido");
    }
}

/**
 * Desativa promoções globais que expiraram
 */
function desativarPromocoesGlobaisExpiradas($pdo) {
    $stmt = $pdo->prepare("
        UPDATE promocao 
        SET ativa = 0 
        WHERE ativa = 1 
        AND data_fim < NOW()
    ");
    $stmt->execute();
    $count = $stmt->rowCount();
    
    if ($count > 0) {
        // Remover preços promocionais dos jogos afetados
        $stmt = $pdo->prepare("
            UPDATE jogo j
            INNER JOIN promocao_jogo pj ON j.id = pj.jogo_id
            INNER JOIN promocao p ON pj.promocao_id = p.id
            SET j.preco_promocional_centavos = NULL,
                j.em_promocao = 0
            WHERE p.ativa = 0 
            AND p.data_fim < NOW()
        ");
        $stmt->execute();
        
        error_log("Desativadas $count promoções globais expiradas");
    }
}

/**
 * Desativa banners que expiraram
 */
function desativarBannersExpirados($pdo) {
    $stmt = $pdo->prepare("
        UPDATE banner 
        SET ativo = 0 
        WHERE ativo = 1 
        AND data_fim IS NOT NULL 
        AND data_fim < CURDATE()
    ");
    $stmt->execute();
    $count = $stmt->rowCount();
    
    if ($count > 0) {
        error_log("Desativados $count banners expirados");
    }
}
/**
 * Desativa eventos que expiraram
 */
function desativarEventosExpirados($pdo) {
    $stmt = $pdo->prepare("
        UPDATE evento 
        SET ativo = 0 
        WHERE ativo = 1 
        AND data_fim < NOW()
    ");
    $stmt->execute();
    $count = $stmt->rowCount();
    
    if ($count > 0) {
        error_log("Desativados $count eventos expirados");
    }
}

/**
 * Verificar se um jogo específico já está na biblioteca
 */
function userOwnsGame($pdo, $user_id, $jogo_id)
{
    $stmt = $pdo->prepare("SELECT 1 FROM biblioteca WHERE usuario_id = ? AND jogo_id = ? LIMIT 1");
    $stmt->execute([$user_id, $jogo_id]);
    return $stmt->fetch() !== false;
}

/**
 * Adicionar jogo à biblioteca e limpar do carrinho/desejos
 */
function addGameToLibrary($pdo, $user_id, $jogo_id, $pedido_id = null)
{
    try {
        $pdo->beginTransaction();

        // Verificar se já não está na biblioteca
        if (!userOwnsGame($pdo, $user_id, $jogo_id)) {
            // Adicionar à biblioteca
            $stmt = $pdo->prepare("
                INSERT INTO biblioteca (usuario_id, jogo_id, pedido_id, adicionado_em) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $jogo_id, $pedido_id]);
        }

        // Remover do carrinho
        $stmt = $pdo->prepare("DELETE FROM carrinho WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogo_id]);

        // Remover da lista de desejos
        $stmt = $pdo->prepare("DELETE FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogo_id]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao adicionar jogo à biblioteca: " . $e->getMessage());
        return false;
    }
}

// ============================================
// PÁGINAS DE AVISO
// ============================================

function showMaintenancePage()
{
    // Buscar mensagem personalizada
    global $pdo_check;
    $stmt = $pdo_check->prepare("SELECT valor FROM configuracao WHERE chave = 'msg_manutencao'");
    $stmt->execute();
    $mensagem = $stmt->fetch()['valor'] ?? 'Estamos em manutenção. Voltamos em breve!';
?>
    <!DOCTYPE html>
    <html lang="pt-BR" data-theme="dark">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manutenção - <?php echo SITE_NAME; ?></title>
        <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
            }

            .maintenance-container {
                text-align: center;
                max-width: 600px;
                padding: 40px 20px;
            }

            .maintenance-icon {
                font-size: 120px;
                color: var(--warning);
                margin-bottom: 30px;
                animation: pulse 2s infinite;
            }

            .maintenance-title {
                font-size: 42px;
                font-weight: 700;
                margin-bottom: 20px;
            }

            .maintenance-message {
                font-size: 18px;
                color: var(--text-secondary);
                line-height: 1.6;
                margin-bottom: 30px;
            }

            .maintenance-loader {
                display: inline-block;
                width: 50px;
                height: 50px;
                border: 5px solid var(--border);
                border-top: 5px solid var(--accent);
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }

            @keyframes pulse {

                0%,
                100% {
                    transform: scale(1);
                }

                50% {
                    transform: scale(1.1);
                }
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }
        </style>
    </head>

    <body>
        <div class="maintenance-container">
            <div class="maintenance-icon">
                <i class="fas fa-tools"></i>
            </div>
            <h1 class="maintenance-title">Estamos em Manutenção</h1>
            <p class="maintenance-message"><?php echo htmlspecialchars($mensagem); ?></p>
            <div class="maintenance-loader"></div>
            <p style="margin-top: 30px; color: var(--text-secondary); font-size: 14px;">
                Em caso de urgência, contate <a href="mailto:suporte@lexxos.com" style="color: var(--accent);">suporte@lexxos.com</a>
            </p>
        </div>
    </body>

    </html>
<?php
}

function showSuspendedPage()
{
?>
    <!DOCTYPE html>
    <html lang="pt-BR" data-theme="dark">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Conta Suspensa - <?php echo SITE_NAME; ?></title>
        <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
            }

            .suspended-container {
                text-align: center;
                max-width: 600px;
                padding: 40px 20px;
            }

            .suspended-icon {
                font-size: 120px;
                color: var(--warning);
                margin-bottom: 30px;
            }

            .suspended-title {
                font-size: 42px;
                font-weight: 700;
                margin-bottom: 20px;
            }

            .suspended-message {
                font-size: 18px;
                color: var(--text-secondary);
                line-height: 1.6;
                margin-bottom: 30px;
            }
        </style>
    </head>

    <body>
        <div class="suspended-container">
            <div class="suspended-icon">
                <i class="fas fa-user-slash"></i>
            </div>
            <h1 class="suspended-title">Conta Suspensa</h1>
            <p class="suspended-message">
                Sua conta foi temporariamente suspensa devido a violação de nossas políticas.<br>
                Entre em contato com o suporte para mais informações.
            </p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="mailto:suporte@lexxos.com" class="btn btn-primary">
                    <i class="fas fa-envelope"></i> Contatar Suporte
                </a>
                <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>
    </body>

    </html>
<?php
}

function showBannedPage()
{
?>
    <!DOCTYPE html>
    <html lang="pt-BR" data-theme="dark">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Conta Banida - <?php echo SITE_NAME; ?></title>
        <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
            }

            .banned-container {
                text-align: center;
                max-width: 600px;
                padding: 40px 20px;
            }

            .banned-icon {
                font-size: 120px;
                color: var(--danger);
                margin-bottom: 30px;
            }

            .banned-title {
                font-size: 42px;
                font-weight: 700;
                margin-bottom: 20px;
                color: var(--danger);
            }

            .banned-message {
                font-size: 18px;
                color: var(--text-secondary);
                line-height: 1.6;
                margin-bottom: 30px;
            }
        </style>
    </head>

    <body>
        <div class="banned-container">
            <div class="banned-icon">
                <i class="fas fa-ban"></i>
            </div>
            <h1 class="banned-title">Conta Banida Permanentemente</h1>
            <p class="banned-message">
                Sua conta foi banida permanentemente devido a violações graves de nossos termos de serviço.<br>
                Esta decisão é final e irreversível.
            </p>
            <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </body>

    </html>
<?php
}

function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
