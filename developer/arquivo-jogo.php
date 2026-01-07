<?php
// developer/arquivo-jogo.php
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

$jogo_id = $_GET['jogo'] ?? null;
$success = '';
$error = '';

if (!$jogo_id) {
    header('Location: ' . SITE_URL . '/developer/jogos.php');
    exit;
}

// Buscar jogo e verificar propriedade
$stmt = $pdo->prepare("SELECT * FROM jogo WHERE id = ? AND desenvolvedor_id = ?");
$stmt->execute([$jogo_id, $dev['id']]);
$jogo = $stmt->fetch();

if (!$jogo) {
    $_SESSION['error'] = 'Jogo não encontrado';
    header('Location: ' . SITE_URL . '/developer/jogos.php');
    exit;
}

// Processar upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload') {
    $plataforma_id = $_POST['plataforma_id'];
    $versao = trim($_POST['versao']);
    $changelog = trim($_POST['changelog']);
    
    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] == 0) {
        try {
            $upload_dir = '../uploads/jogos/' . $jogo['slug'] . '/files';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = time() . '-' . basename($_FILES['arquivo']['name']);
            $file_path = $upload_dir . '/' . $file_name;
            
            if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $file_path)) {
                $db_path = '/uploads/jogos/' . $jogo['slug'] . '/files/' . $file_name;
                
                $stmt = $pdo->prepare("
                    INSERT INTO arquivo_jogo (jogo_id, plataforma_id, nome_arquivo, caminho, tamanho_bytes, versao, changelog)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $jogo_id,
                    $plataforma_id,
                    $_FILES['arquivo']['name'],
                    $db_path,
                    $_FILES['arquivo']['size'],
                    $versao,
                    $changelog
                ]);
                
                $success = 'Arquivo enviado com sucesso!';
            }
        } catch (Exception $e) {
            $error = 'Erro ao fazer upload: ' . $e->getMessage();
        }
    } else {
        $error = 'Selecione um arquivo para upload';
    }
}

// Deletar arquivo
if (isset($_GET['delete'])) {
    $arquivo_id = $_GET['delete'];
    
    $stmt = $pdo->prepare("SELECT * FROM arquivo_jogo WHERE id = ? AND jogo_id = ?");
    $stmt->execute([$arquivo_id, $jogo_id]);
    $arquivo = $stmt->fetch();
    
    if ($arquivo) {
        if (file_exists('..' . $arquivo['caminho'])) {
            unlink('..' . $arquivo['caminho']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM arquivo_jogo WHERE id = ?");
        $stmt->execute([$arquivo_id]);
        
        $success = 'Arquivo removido com sucesso!';
    }
}

// Buscar arquivos do jogo
$stmt = $pdo->prepare("
    SELECT a.*, p.nome as plataforma_nome, p.icone as plataforma_icone
    FROM arquivo_jogo a
    LEFT JOIN plataforma p ON a.plataforma_id = p.id
    WHERE a.jogo_id = ?
    ORDER BY a.criado_em DESC
");
$stmt->execute([$jogo_id]);
$arquivos = $stmt->fetchAll();

// Buscar plataformas do jogo
$stmt = $pdo->prepare("
    SELECT p.* FROM plataforma p
    INNER JOIN jogo_plataforma jp ON p.id = jp.plataforma_id
    WHERE jp.jogo_id = ?
");
$stmt->execute([$jogo_id]);
$plataformas = $stmt->fetchAll();

$page_title = 'Gerenciar Arquivos - ' . $jogo['titulo'] . ' - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="dev-content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-file-archive"></i> Gerenciar Arquivos
                </h1>
                <p class="page-subtitle"><?php echo sanitize($jogo['titulo']); ?></p>
            </div>
            
            <?php if ($success): ?>
                <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div style="background: rgba(220,53,69,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Upload de Novo Arquivo -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 30px; margin-bottom: 30px;">
                <h2 style="margin-bottom: 20px;"><i class="fas fa-upload"></i> Enviar Novo Arquivo</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="form-group">
                        <label class="form-label">Plataforma *</label>
                        <select name="plataforma_id" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($plataformas as $plat): ?>
                                <option value="<?php echo $plat['id']; ?>">
                                    <?php echo $plat['nome']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Versão *</label>
                        <input type="text" name="versao" class="form-control" required value="1.0" placeholder="1.0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Changelog</label>
                        <textarea name="changelog" class="form-control" rows="3" placeholder="Descreva as mudanças desta versão..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Arquivo do Jogo * (ZIP, RAR, 7Z)</label>
                        <input type="file" name="arquivo" class="form-control" required accept=".zip,.rar,.7z">
                        <small style="color: var(--text-secondary);">Tamanho máximo: 100MB</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Enviar Arquivo
                    </button>
                </form>
            </div>
            
            <!-- Lista de Arquivos -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 30px;">
                <h2 style="margin-bottom: 20px;"><i class="fas fa-list"></i> Arquivos Disponíveis</h2>
                
                <?php if (count($arquivos) > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach ($arquivos as $arquivo): ?>
                        <div style="background: var(--bg-primary); border: 1px solid var(--border); border-radius: 10px; padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: start; gap: 20px;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                        <i class="<?php echo $arquivo['plataforma_icone']; ?>" style="font-size: 24px; color: var(--accent);"></i>
                                        <h3 style="font-size: 18px; margin: 0;"><?php echo sanitize($arquivo['nome_arquivo']); ?></h3>
                                    </div>
                                    
                                    <div style="display: flex; gap: 20px; font-size: 14px; color: var(--text-secondary); margin-bottom: 10px;">
                                        <span><i class="fas fa-tag"></i> v<?php echo $arquivo['versao']; ?></span>
                                        <span><i class="fas fa-hdd"></i> <?php echo round($arquivo['tamanho_bytes'] / 1024 / 1024, 2); ?> MB</span>
                                        <span><i class="fas fa-download"></i> <?php echo $arquivo['downloads']; ?> downloads</span>
                                        <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($arquivo['criado_em'])); ?></span>
                                    </div>
                                    
                                    <?php if ($arquivo['changelog']): ?>
                                        <div style="background: var(--bg-secondary); padding: 10px; border-radius: 6px; margin-top: 10px;">
                                            <strong style="font-size: 13px;">Changelog:</strong>
                                            <p style="font-size: 13px; color: var(--text-secondary); margin: 5px 0 0 0;">
                                                <?php echo nl2br(sanitize($arquivo['changelog'])); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <a href="<?php echo SITE_URL . $arquivo['caminho']; ?>" 
                                       class="btn btn-primary btn-sm" download>
                                        <i class="fas fa-download"></i> Baixar
                                    </a>
                                    
                                    <button class="btn btn-danger btn-sm" 
                                            onclick="if(confirm('Tem certeza que deseja remover este arquivo?')) window.location.href='?jogo=<?php echo $jogo_id; ?>&delete=<?php echo $arquivo['id']; ?>'">
                                        <i class="fas fa-trash"></i> Remover
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-file-archive" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 15px;"></i>
                        <p style="color: var(--text-secondary);">Nenhum arquivo enviado ainda</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="<?php echo SITE_URL; ?>/developer/jogos.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar para Meus Jogos
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>