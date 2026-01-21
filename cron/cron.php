<?php

// Evitar timeout
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '256M');

// Configuração
define('CRON_SECRET_KEY', 'SUA_CHAVE_SECRETA_AQUI_ALTERE_ISSO'); // Altere para uma chave única
define('LOG_FILE', __DIR__ . '/cron.log');
define('EXECUTION_LOCK', __DIR__ . '/cron.lock');
define('MAX_LOCK_TIME', 600); // 10 minutos

// Carregar configurações
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ============================================
// AUTENTICAÇÃO E SEGURANÇA
// ============================================

/**
 * Verificar se a requisição é autorizada
 */
function verificarAutenticacao() {
    // Método 1: Chave secreta via GET ou cabeçalho
    $key_get = $_GET['key'] ?? '';
    $key_header = $_SERVER['HTTP_X_CRON_KEY'] ?? '';
    
    // Método 2: Executando via CLI
    $is_cli = php_sapi_name() === 'cli';
    
    // Método 3: Localhost (desenvolvimento)
    $is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);
    
    if ($is_cli) {
        return true; // CLI sempre autorizado
    }
    
    if ($key_get === CRON_SECRET_KEY || $key_header === CRON_SECRET_KEY) {
        return true;
    }
    
    if ($is_local && isset($_GET['manual'])) {
        return true; // Permite execução manual local para testes
    }
    
    http_response_code(403);
    die("Acesso negado. Use a chave correta ou execute via CLI.");
}

/**
 * Sistema de lock para evitar execuções simultâneas
 */
function adquirirLock() {
    if (file_exists(EXECUTION_LOCK)) {
        $lock_time = filemtime(EXECUTION_LOCK);
        
        // Se o lock tem menos de MAX_LOCK_TIME, outra instância está executando
        if (time() - $lock_time < MAX_LOCK_TIME) {
            log_cron("Lock ativo. Outra instância está executando. Abortando.");
            die("Execução já em andamento.");
        }
        
        // Lock antigo, pode ter travado - remover
        unlink(EXECUTION_LOCK);
    }
    
    file_put_contents(EXECUTION_LOCK, time());
}

function liberarLock() {
    if (file_exists(EXECUTION_LOCK)) {
        unlink(EXECUTION_LOCK);
    }
}

/**
 * Logger simples
 */
function log_cron($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents(LOG_FILE, $log_message, FILE_APPEND);
    
    // Output para CLI
    if (php_sapi_name() === 'cli') {
        echo $log_message;
    }
}

// ============================================
// TAREFAS DE AUTOMAÇÃO
// ============================================

/**
 * Limpar promoções expiradas
 */
function limparPromocoesExpiradas($pdo) {
    try {
        $stmt = $pdo->exec("CALL limpar_promocoes_expiradas()");
        log_cron("✓ Promoções limpas: $stmt registros afetados");
        return true;
    } catch (Exception $e) {
        log_cron("✗ Erro ao limpar promoções: " . $e->getMessage());
        return false;
    }
}

/**
 * Desativar cupons expirados
 */
function desativarCuponsExpirados($pdo) {
    try {
        $stmt = $pdo->exec("CALL desativar_cupons_expirados()");
        log_cron("✓ Cupons desativados: $stmt registros afetados");
        return true;
    } catch (Exception $e) {
        log_cron("✗ Erro ao desativar cupons: " . $e->getMessage());
        return false;
    }
}

/**
 * Desativar eventos finalizados
 */
function desativarEventosFinalizados($pdo) {
    try {
        $stmt = $pdo->exec("CALL desativar_eventos_finalizados()");
        log_cron("✓ Eventos finalizados: $stmt registros afetados");
        return true;
    } catch (Exception $e) {
        log_cron("✗ Erro ao desativar eventos: " . $e->getMessage());
        return false;
    }
}

/**
 * Liberar saldo pendente dos desenvolvedores
 */
function liberarSaldoPendente($pdo) {
    try {
        $stmt = $pdo->exec("CALL liberar_saldo_pendente()");
        log_cron("✓ Saldo liberado para desenvolvedores");
        return true;
    } catch (Exception $e) {
        log_cron("✗ Erro ao liberar saldo: " . $e->getMessage());
        return false;
    }
}

/**
 * Limpar carrinhos abandonados
 */
function limparCarrinhosAbandonados($pdo) {
    try {
        $stmt = $pdo->exec("CALL limpar_carrinhos_abandonados()");
        log_cron("✓ Carrinhos abandonados limpos: $stmt registros afetados");
        return true;
    } catch (Exception $e) {
        log_cron("✗ Erro ao limpar carrinhos: " . $e->getMessage());
        return false;
    }
}

/**
 * Atualizar métricas diárias
 */
function atualizarMetricas($pdo) {
    try {
        $stmt = $pdo->exec("CALL atualizar_metricas_hoje()");
        log_cron("✓ Métricas atualizadas");
        return true;
    } catch (Exception $e) {
        log_cron("✗ Erro ao atualizar métricas: " . $e->getMessage());
        return false;
    }
}

/**
 * Ativar/Desativar promoções baseado em datas
 */
function gerenciarPromocoesAgendadas($pdo) {
    try {
        // Ativar promoções que chegaram na data de início
        $stmt = $pdo->prepare("
            UPDATE dev_promocao 
            SET ativa = 1 
            WHERE ativa = 0 
            AND data_inicio <= NOW() 
            AND data_fim > NOW()
        ");
        $stmt->execute();
        $ativadas = $stmt->rowCount();
        
        if ($ativadas > 0) {
            // Aplicar preços promocionais
            $pdo->exec("
                UPDATE jogo j
                INNER JOIN dev_promocao_jogo dpj ON j.id = dpj.jogo_id
                INNER JOIN dev_promocao p ON dpj.promocao_id = p.id
                SET 
                    j.preco_promocional_centavos = dpj.preco_promocional_centavos,
                    j.em_promocao = 1
                WHERE p.ativa = 1 
                AND p.data_inicio <= NOW() 
                AND p.data_fim > NOW()
            ");
        }
        
        log_cron("✓ Promoções agendadas: $ativadas ativadas");
        return true;
    } catch (Exception $e) {
        log_cron("✗ Erro ao gerenciar promoções: " . $e->getMessage());
        return false;
    }
}

// ============================================
// SCHEDULER DE TAREFAS
// ============================================

/**
 * Determinar quais tarefas executar baseado no horário
 */
function executarTarefasAgendadas($pdo) {
    $hora = (int)date('H');
    $dia_semana = (int)date('w'); // 0 = domingo
    $dia_mes = (int)date('d');
    
    $tarefas_executadas = [];
    
    // === TAREFAS DE ALTA FREQUÊNCIA (A cada execução) ===
    
    // Gerenciar promoções agendadas
    if (gerenciarPromocoesAgendadas($pdo)) {
        $tarefas_executadas[] = 'Promoções Agendadas';
    }
    
    // === TAREFAS A CADA HORA ===
    
    // Limpar promoções (a cada hora ímpar)
    if ($hora % 2 == 1) {
        if (limparPromocoesExpiradas($pdo)) {
            $tarefas_executadas[] = 'Limpeza de Promoções';
        }
    }
    
    // === TAREFAS DIÁRIAS ===
    
    // Meia-noite: Atualizar métricas e desativar cupons
    if ($hora == 0) {
        if (atualizarMetricas($pdo)) {
            $tarefas_executadas[] = 'Atualização de Métricas';
        }
        if (desativarCuponsExpirados($pdo)) {
            $tarefas_executadas[] = 'Desativação de Cupons';
        }
    }
    
    // 3h da manhã: Liberar saldo pendente
    if ($hora == 3) {
        if (liberarSaldoPendente($pdo)) {
            $tarefas_executadas[] = 'Liberação de Saldo';
        }
    }
    
    // 6h da manhã: Desativar eventos
    if ($hora == 6) {
        if (desativarEventosFinalizados($pdo)) {
            $tarefas_executadas[] = 'Desativação de Eventos';
        }
    }
    
    // === TAREFAS SEMANAIS ===
    
    // Domingo às 4h: Limpar carrinhos abandonados
    if ($dia_semana == 0 && $hora == 4) {
        if (limparCarrinhosAbandonados($pdo)) {
            $tarefas_executadas[] = 'Limpeza de Carrinhos';
        }
    }
    
    // === TAREFAS MENSAIS ===
    
    // Primeiro dia do mês às 2h: Limpeza profunda
    if ($dia_mes == 1 && $hora == 2) {
        // Limpar logs antigos
        limparLogsAntigos();
        $tarefas_executadas[] = 'Limpeza de Logs';
    }
    
    return $tarefas_executadas;
}

/**
 * Limpar logs antigos (mantém últimos 90 dias)
 */
function limparLogsAntigos() {
    if (file_exists(LOG_FILE)) {
        $log_content = file_get_contents(LOG_FILE);
        $lines = explode("\n", $log_content);
        
        $data_limite = strtotime('-90 days');
        $new_lines = [];
        
        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                $line_date = strtotime($matches[1]);
                if ($line_date >= $data_limite) {
                    $new_lines[] = $line;
                }
            }
        }
        
        file_put_contents(LOG_FILE, implode("\n", $new_lines));
        log_cron("✓ Logs antigos limpos");
    }
}

// ============================================
// EXECUÇÃO PRINCIPAL
// ============================================

try {
    // Verificar autenticação
    verificarAutenticacao();
    
    // Adquirir lock
    adquirirLock();
    
    log_cron("========================================");
    log_cron("INICIANDO EXECUÇÃO DO CRON");
    log_cron("========================================");
    
    // Conectar ao banco
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Executar tarefas agendadas
    $tarefas = executarTarefasAgendadas($pdo);
    
    if (empty($tarefas)) {
        log_cron("Nenhuma tarefa executada neste horário.");
    } else {
        log_cron("Tarefas executadas: " . implode(', ', $tarefas));
    }
    
    // Estatísticas
    $memory = round(memory_get_peak_usage() / 1024 / 1024, 2);
    $time = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
    
    log_cron("Memória usada: {$memory}MB | Tempo: {$time}s");
    log_cron("========================================");
    log_cron("EXECUÇÃO CONCLUÍDA COM SUCESSO");
    log_cron("========================================\n");
    
    // Liberar lock
    liberarLock();
    
    // Resposta HTTP
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'tarefas' => $tarefas,
            'memoria' => $memory . 'MB',
            'tempo' => $time . 's',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    }
    
    exit(0);
    
} catch (Exception $e) {
    log_cron("ERRO FATAL: " . $e->getMessage());
    log_cron("Trace: " . $e->getTraceAsString());
    liberarLock();
    
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit(1);
}