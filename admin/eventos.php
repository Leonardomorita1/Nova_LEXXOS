<?php
// admin/eventos.php - COM UPLOAD E COMPRESSÃO DE IMAGENS
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();
if (getUserType() !== 'admin') {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$message = '';
$error = '';

// ============================================
// FUNÇÃO DE UPLOAD E COMPRESSÃO DE IMAGEM
// ============================================
function uploadAndCompressImage($file, $tipo = 'banner', $maxWidth = 1920, $quality = 80)
{
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/images/eventos/';

    // Criar diretório se não existir
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Validar arquivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $fileType = mime_content_type($file['tmp_name']);

    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, WebP ou GIF.');
    }

    // Limite de 10MB para upload original
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('Arquivo muito grande. Máximo 10MB.');
    }

    // Gerar nome único
    $extension = 'jpg'; // Sempre salvar como JPG para melhor compressão
    $filename = $tipo . '-' . uniqid() . '-' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Carregar imagem original
    switch ($fileType) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $source = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($file['tmp_name']);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($file['tmp_name']);
            break;
        default:
            throw new Exception('Formato de imagem não suportado.');
    }

    if (!$source) {
        throw new Exception('Erro ao processar imagem.');
    }

    // Obter dimensões originais
    $origWidth = imagesx($source);
    $origHeight = imagesy($source);

    // Calcular novas dimensões mantendo proporção
    if ($origWidth > $maxWidth) {
        $ratio = $maxWidth / $origWidth;
        $newWidth = $maxWidth;
        $newHeight = (int)($origHeight * $ratio);
    } else {
        $newWidth = $origWidth;
        $newHeight = $origHeight;
    }

    // Criar nova imagem redimensionada
    $resized = imagecreatetruecolor($newWidth, $newHeight);

    // Preservar transparência para PNGs (converter para fundo branco)
    $white = imagecolorallocate($resized, 255, 255, 255);
    imagefill($resized, 0, 0, $white);

    // Redimensionar
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Salvar como JPEG comprimido
    imagejpeg($resized, $filepath, $quality);

    // Liberar memória
    imagedestroy($source);
    imagedestroy($resized);

    // Retornar caminho relativo
    return '/assets/images/eventos/' . $filename;
}

function uploadOverlayImage($file, $maxWidth = 800)
{
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/images/eventos/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/png', 'image/webp', 'image/gif'];
    $fileType = mime_content_type($file['tmp_name']);

    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Overlay deve ser PNG, WebP ou GIF (com transparência).');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Overlay muito grande. Máximo 5MB.');
    }

    $filename = 'overlay-' . uniqid() . '-' . time() . '.png';
    $filepath = $uploadDir . $filename;

    // Carregar imagem
    switch ($fileType) {
        case 'image/png':
            $source = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($file['tmp_name']);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($file['tmp_name']);
            break;
    }

    $origWidth = imagesx($source);
    $origHeight = imagesy($source);

    if ($origWidth > $maxWidth) {
        $ratio = $maxWidth / $origWidth;
        $newWidth = $maxWidth;
        $newHeight = (int)($origHeight * $ratio);
    } else {
        $newWidth = $origWidth;
        $newHeight = $origHeight;
    }

    // Criar imagem com transparência
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagesavealpha($resized, true);
    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefill($resized, 0, 0, $transparent);

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Salvar como PNG (mantém transparência)
    imagepng($resized, $filepath, 8); // Compressão nível 8

    imagedestroy($source);
    imagedestroy($resized);

    return '/assets/images/eventos/' . $filename;
}

// ============================================
// 1. CRIAR EVENTO + BANNER PROMOCIONAL AUTOMÁTICO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['criar_evento'])) {
    try {
        $pdo->beginTransaction();

        $nome = trim($_POST['nome']);
        $descricao = trim($_POST['descricao']);
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nome)));
        $slug = trim($slug, '-');
        $data_inicio = $_POST['data_inicio'];
        $data_fim = $_POST['data_fim'];

        // Upload da imagem de fundo
        $imagem_banner = '/assets/images/default-event-banner.jpg';
        if (!empty($_FILES['imagem_banner']['tmp_name'])) {
            $imagem_banner = uploadAndCompressImage($_FILES['imagem_banner'], 'banner', 1920, 85);
        }

        // Upload da imagem overlay (opcional)
        $imagem_overlay = null;
        if (!empty($_FILES['imagem_overlay']['tmp_name'])) {
            $imagem_overlay = uploadOverlayImage($_FILES['imagem_overlay'], 800);
        }

        // Campos do banner promocional
        $texto_principal = trim($_POST['texto_principal'] ?? 'TERMINA EM');
        $texto_secundario = trim($_POST['texto_secundario'] ?? 'ECONOMIZE ATÉ');
        $texto_destaque = trim($_POST['texto_destaque'] ?? '');
        $cor_fundo = $_POST['cor_fundo'] ?? '#131314';
        $cor_texto = $_POST['cor_texto'] ?? '#E3E3E3';
        $cor_destaque = $_POST['cor_destaque'] ?? '#4C8BF5';

        if (empty($nome) || empty($data_inicio) || empty($data_fim)) {
            throw new Exception('Preencha todos os campos obrigatórios.');
        }

        // Verificar se slug já existe
        $stmt = $pdo->prepare("SELECT id FROM evento WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $slug .= '-' . rand(100, 999);
        }

        // Criar evento
        $stmt = $pdo->prepare("
            INSERT INTO evento (nome, slug, descricao, imagem_banner, data_inicio, data_fim, ativo) 
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$nome, $slug, $descricao, $imagem_banner, $data_inicio, $data_fim]);
        $evento_id = $pdo->lastInsertId();

        // Criar banner promocional automaticamente
        $stmt = $pdo->prepare("
            INSERT INTO banner (
                titulo, subtitulo, 
                texto_principal, texto_destaque, texto_secundario,
                imagem_desktop, imagem_overlay,
                cor_fundo, cor_texto, cor_destaque,
                estilo_banner, url_destino, ordem, ativo, 
                data_inicio, data_fim
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'promocional', ?, 0, 1, ?, ?)
        ");

        $url_evento = '/pages/evento.php?slug=' . $slug;
        $subtitulo_banner = $descricao ?: 'Aproveite as ofertas especiais!';

        $stmt->execute([
            $nome,
            $subtitulo_banner,
            $texto_principal,
            $texto_destaque,
            $texto_secundario,
            $imagem_banner,
            $imagem_overlay,
            $cor_fundo,
            $cor_texto,
            $cor_destaque,
            $url_evento,
            date('Y-m-d', strtotime($data_inicio)),
            date('Y-m-d', strtotime($data_fim))
        ]);

        $pdo->commit();
        $message = 'Evento criado com sucesso! Banner promocional gerado automaticamente.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// ============================================
// 2. TOGGLE EVENTO (sincroniza banner)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_evento'])) {
    $evento_id = $_POST['evento_id'];

    $stmt = $pdo->prepare("SELECT slug, ativo FROM evento WHERE id = ?");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch();

    if ($evento) {
        $novo_status = $evento['ativo'] ? 0 : 1;

        $stmt = $pdo->prepare("UPDATE evento SET ativo = ? WHERE id = ?");
        $stmt->execute([$novo_status, $evento_id]);

        $stmt = $pdo->prepare("UPDATE banner SET ativo = ? WHERE url_destino LIKE ? AND estilo_banner = 'promocional'");
        $stmt->execute([$novo_status, '%' . $evento['slug'] . '%']);

        $message = 'Status do evento e banner atualizado.';
    }
}

// ============================================
// 3. DELETAR EVENTO (remove banner e imagens)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deletar_evento'])) {
    $evento_id = $_POST['evento_id'];

    $stmt = $pdo->prepare("SELECT slug, imagem_banner FROM evento WHERE id = ?");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch();

    if ($evento) {
        // Buscar imagem overlay do banner
        $stmt = $pdo->prepare("SELECT imagem_overlay FROM banner WHERE url_destino LIKE ? AND estilo_banner = 'promocional'");
        $stmt->execute(['%' . $evento['slug'] . '%']);
        $banner = $stmt->fetch();

        // Deletar arquivos de imagem (se não for a padrão)
        if ($evento['imagem_banner'] && $evento['imagem_banner'] !== '/assets/images/default-event-banner.jpg') {
            $filepath = $_SERVER['DOCUMENT_ROOT'] . $evento['imagem_banner'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

        if ($banner && $banner['imagem_overlay']) {
            $filepath = $_SERVER['DOCUMENT_ROOT'] . $banner['imagem_overlay'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

        // Deletar banner
        $stmt = $pdo->prepare("DELETE FROM banner WHERE url_destino LIKE ? AND estilo_banner = 'promocional'");
        $stmt->execute(['%' . $evento['slug'] . '%']);

        // Deletar evento
        $stmt = $pdo->prepare("DELETE FROM evento WHERE id = ?");
        $stmt->execute([$evento_id]);

        $message = 'Evento, banner e imagens removidos.';
    }
}

// ============================================
// 4. BUSCAR DADOS
// ============================================
$stmt = $pdo->query("
    SELECT e.*, 
           (SELECT id FROM banner b WHERE b.url_destino LIKE CONCAT('%', e.slug, '%') AND b.estilo_banner = 'promocional' LIMIT 1) as banner_id
    FROM evento e 
    ORDER BY e.criado_em DESC
");
$eventos = $stmt->fetchAll();

// Métricas do dia
$stmt = $pdo->prepare("SELECT * FROM metrica_venda WHERE data = CURDATE()");
$stmt->execute();
$metricas_hoje = $stmt->fetch();

if (!$metricas_hoje) {
    try {
        $pdo->query("CALL atualizar_metricas_hoje()");
        $stmt = $pdo->prepare("SELECT * FROM metrica_venda WHERE data = CURDATE()");
        $stmt->execute();
        $metricas_hoje = $stmt->fetch();
    } catch (Exception $e) {
        $metricas_hoje = ['total_vendas' => 0, 'total_receita_centavos' => 0, 'jogos_mais_vendidos' => '[]', 'devs_top' => '[]'];
    }
}

// Auditoria Cupons
$stmt = $pdo->query("
    SELECT dc.*, d.nome_estudio 
    FROM dev_cupom dc 
    JOIN desenvolvedor d ON dc.desenvolvedor_id = d.id 
    WHERE dc.criado_em >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
    ORDER BY dc.criado_em DESC 
    LIMIT 30
");
$cupons_log = $stmt->fetchAll();

$page_title = 'Monitoramento & Eventos - Admin';
require_once '../includes/header.php';
?>

<style>
    /* =========================================
       LAYOUT ADMIN
       ========================================= */
    

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 20px;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .section-title i {
        color: var(--accent);
    }

    /* =========================================
       METRICS CARDS
       ========================================= */
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .metric-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 25px;
        transition: transform 0.2s, border-color 0.2s;
    }

    .metric-card:hover {
        transform: translateY(-2px);
        border-color: var(--accent);
    }

    .metric-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .metric-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        background: rgba(255, 255, 255, 0.05);
        color: var(--text-secondary);
    }

    .metric-card.highlight .metric-icon {
        background: var(--accent);
        color: white;
    }

    .metric-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        font-variant-numeric: tabular-nums;
        line-height: 1;
        margin-bottom: 5px;
    }

    .metric-label {
        font-size: 0.85rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    /* =========================================
       SPLIT VIEW
       ========================================= */
    .split-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 50px;
    }

    .panel {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
    }

    .panel-header {
        padding: 20px;
        border-bottom: 1px solid var(--border);
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .list-item {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid var(--border);
        gap: 15px;
    }

    .list-item:last-child {
        border-bottom: none;
    }

    .rank-badge {
        width: 24px;
        height: 24px;
        border-radius: 4px;
        background: var(--bg-primary);
        color: var(--text-secondary);
        font-size: 0.75rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--border);
    }

    .list-item:nth-child(1) .rank-badge {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
    }

    .item-info {
        flex: 1;
    }

    .item-name {
        font-weight: 600;
        color: var(--text-primary);
        display: block;
        margin-bottom: 2px;
    }

    .item-meta {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    /* =========================================
       EVENT ROW
       ========================================= */
    .event-row {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .event-thumb {
        width: 120px;
        height: 70px;
        border-radius: 6px;
        object-fit: cover;
        background: #000;
        border: 1px solid var(--border);
    }

    .event-details {
        flex: 1;
    }

    .event-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #444;
    }

    .status-dot.active {
        background: #2ecc71;
        box-shadow: 0 0 10px rgba(46, 204, 113, 0.4);
    }

    .event-meta-info {
        font-size: 0.85rem;
        color: var(--text-secondary);
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .event-actions {
        display: flex;
        gap: 10px;
    }

    .badge-promo {
        background: rgba(243, 156, 18, 0.15);
        color: #f39c12;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 700;
    }

    /* =========================================
       BUTTONS
       ========================================= */
    .btn {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
    }

    .btn-primary:hover {
        filter: brightness(1.1);
    }

    .btn-outline {
        background: transparent;
        border-color: var(--border);
        color: var(--text-secondary);
    }

    .btn-outline:hover {
        border-color: var(--text-primary);
        color: var(--text-primary);
        background: var(--bg-primary);
    }

    .btn-danger {
        color: #e74c3c;
        background: rgba(231, 76, 60, 0.1);
        border-color: rgba(231, 76, 60, 0.2);
    }

    .btn-danger:hover {
        background: rgba(231, 76, 60, 0.2);
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: 0.8rem;
    }

    /* =========================================
       DATA TABLE
       ========================================= */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    .data-table th {
        text-align: left;
        padding: 15px;
        color: var(--text-secondary);
        font-weight: 600;
        border-bottom: 1px solid var(--border);
    }

    .data-table td {
        padding: 15px;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
    }

    .data-table tr:last-child td {
        border-bottom: none;
    }

    .code-pill {
        font-family: monospace;
        background: var(--bg-primary);
        padding: 4px 8px;
        border-radius: 4px;
        border: 1px solid var(--border);
        color: var(--accent);
    }

    /* =========================================
       MODAL - SCROLL INVISÍVEL
       ========================================= */
    .modal {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal.active {
        display: flex;
    }

    .modal-box {
        background: var(--bg-secondary);
        width: 650px;
        max-width: 100%;
        max-height: 90vh;
        border-radius: 12px;
        border: 1px solid var(--border);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
        background: var(--bg-secondary);
    }

    .modal-body {
        padding: 24px;
        overflow-y: auto;
        max-height: calc(90vh - 140px);
        /* Altura máxima = modal - header - footer */
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .modal-body::-webkit-scrollbar {
        width: 0;
        height: 0;
        display: none;
    }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: var(--bg-primary);
        flex-shrink: 0;
    }

    /* =========================================
       FORM STYLES
       ========================================= */
    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-size: 0.9rem;
        color: var(--text-primary);
        font-weight: 500;
    }

    .form-label .required {
        color: #e74c3c;
        margin-left: 2px;
    }

    .form-input {
        width: 100%;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        padding: 12px 14px;
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 0.95rem;
        transition: border-color 0.2s;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
    }

    .form-input::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }

    .form-hint {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: 6px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 16px;
    }

    .form-divider {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--accent);
        margin: 28px 0 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-divider i {
        font-size: 0.9rem;
    }

    /* =========================================
       FILE UPLOAD
       ========================================= */
    .file-upload-area {
        border: 2px dashed var(--border);
        border-radius: 10px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background: var(--bg-primary);
    }

    .file-upload-area:hover {
        border-color: var(--accent);
        background: rgba(76, 139, 245, 0.05);
    }

    .file-upload-area.dragover {
        border-color: var(--accent);
        background: rgba(76, 139, 245, 0.1);
    }

    .file-upload-area.has-file {
        border-color: #2ecc71;
        border-style: solid;
    }

    .file-upload-icon {
        font-size: 2rem;
        color: var(--text-secondary);
        margin-bottom: 10px;
    }

    .file-upload-area.has-file .file-upload-icon {
        color: #2ecc71;
    }

    .file-upload-text {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .file-upload-text strong {
        color: var(--accent);
    }

    .file-upload-preview {
        margin-top: 12px;
        display: none;
    }

    .file-upload-preview img {
        max-width: 100%;
        max-height: 120px;
        border-radius: 6px;
        border: 1px solid var(--border);
    }

    .file-upload-preview.visible {
        display: block;
    }

    .file-upload-info {
        margin-top: 8px;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    /* =========================================
       COLOR PICKER
       ========================================= */
    .color-picker-group {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .color-preview-input {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        border: 2px solid var(--border);
        cursor: pointer;
        padding: 0;
        overflow: hidden;
    }

    .color-preview-input::-webkit-color-swatch-wrapper {
        padding: 0;
    }

    .color-preview-input::-webkit-color-swatch {
        border: none;
    }

    .color-value {
        font-family: monospace;
        font-size: 0.85rem;
        color: var(--text-secondary);
        background: var(--bg-primary);
        padding: 6px 10px;
        border-radius: 4px;
        border: 1px solid var(--border);
    }

    @media (max-width: 900px) {
        .split-grid {
            grid-template-columns: 1fr;
        }

        .event-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .event-thumb {
            width: 100%;
            height: 150px;
        }

        .event-actions {
            width: 100%;
            justify-content: flex-end;
        }

        .form-row,
        .form-row-3 {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-layout">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <main class="admin-content">
        <!-- HEADER -->
        <div class="section-header">
            <h1 class="section-title">
                <i class="fas fa-chart-line"></i> Dashboard & Eventos
            </h1>
            <button onclick="toggleModal('createEventModal')" class="btn btn-primary">
                <i class="fas fa-plus"></i> Novo Evento
            </button>
        </div>

        <!-- MENSAGENS -->
        <?php if ($message): ?>
            <div style="padding: 15px; background: rgba(46, 204, 113, 0.1); border: 1px solid #2ecc71; border-radius: 8px; color: #2ecc71; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="padding: 15px; background: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; border-radius: 8px; color: #e74c3c; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- KPI CARDS -->
        <div class="metrics-grid">
            <div class="metric-card highlight">
                <div class="metric-header">
                    <span class="metric-label">Vendas (Hoje)</span>
                    <div class="metric-icon"><i class="fas fa-shopping-cart"></i></div>
                </div>
                <div class="metric-value"><?= number_format($metricas_hoje['total_vendas'] ?? 0) ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">Receita (Hoje)</span>
                    <div class="metric-icon"><i class="fas fa-dollar-sign"></i></div>
                </div>
                <div class="metric-value"><?= formatPrice($metricas_hoje['total_receita_centavos'] ?? 0) ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">Jogos Movimentados</span>
                    <div class="metric-icon"><i class="fas fa-gamepad"></i></div>
                </div>
                <div class="metric-value">
                    <?= count(json_decode($metricas_hoje['jogos_mais_vendidos'] ?? '[]', true)) ?>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">Devs Ativos</span>
                    <div class="metric-icon"><i class="fas fa-code"></i></div>
                </div>
                <div class="metric-value">
                    <?= count(json_decode($metricas_hoje['devs_top'] ?? '[]', true)) ?>
                </div>
            </div>
        </div>

        <!-- RANKINGS -->
        <div class="split-grid">
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-trophy"></i> Top Jogos (24h)
                </div>
                <div>
                    <?php
                    $jogos = json_decode($metricas_hoje['jogos_mais_vendidos'] ?? '[]', true);
                    if (empty($jogos)) echo '<div style="padding:20px; text-align:center; color:var(--text-secondary);">Sem dados hoje</div>';
                    $rank = 1;
                    foreach (array_slice($jogos, 0, 5) as $jogo): ?>
                        <div class="list-item">
                            <div class="rank-badge"><?= $rank++ ?></div>
                            <div class="item-info">
                                <span class="item-name"><?= htmlspecialchars($jogo['titulo']) ?></span>
                                <span class="item-meta"><?= $jogo['vendas'] ?> unidades</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-user-secret"></i> Top Desenvolvedores (24h)
                </div>
                <div>
                    <?php
                    $devs = json_decode($metricas_hoje['devs_top'] ?? '[]', true);
                    if (empty($devs)) echo '<div style="padding:20px; text-align:center; color:var(--text-secondary);">Sem dados hoje</div>';
                    $rank = 1;
                    foreach (array_slice($devs, 0, 5) as $dev): ?>
                        <div class="list-item">
                            <div class="rank-badge"><?= $rank++ ?></div>
                            <div class="item-info">
                                <span class="item-name"><?= htmlspecialchars($dev['nome']) ?></span>
                                <span class="item-meta"><?= formatPrice($dev['receita']) ?> gerados</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- EVENTOS -->
        <h2 class="section-title" style="margin-bottom: 20px;">
            <i class="fas fa-calendar-alt"></i> Eventos Sazonais
        </h2>

        <div class="events-container">
            <?php foreach ($eventos as $evento): ?>
                <div class="event-row">
                    <img src="<?= SITE_URL . $evento['imagem_banner'] ?>" class="event-thumb" alt="Banner"
                        onerror="this.src='<?= SITE_URL ?>/assets/images/default-event-banner.jpg'">

                    <div class="event-details">
                        <div class="event-name">
                            <span class="status-dot <?= $evento['ativo'] ? 'active' : '' ?>"></span>
                            <?= htmlspecialchars($evento['nome']) ?>
                            <?php if ($evento['banner_id']): ?>
                                <span class="badge-promo"><i class="fas fa-star"></i> Banner</span>
                            <?php endif; ?>
                        </div>
                        <div class="event-meta-info">
                            <span><i class="fas fa-clock"></i> <?= date('d/m H:i', strtotime($evento['data_inicio'])) ?> - <?= date('d/m H:i', strtotime($evento['data_fim'])) ?></span>
                        </div>
                    </div>

                    <div class="event-actions">
                        <a href="<?= SITE_URL ?>/pages/evento.php?slug=<?= $evento['slug'] ?>" target="_blank" class="btn btn-outline btn-sm">
                            <i class="fas fa-eye"></i>
                        </a>

                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="evento_id" value="<?= $evento['id'] ?>">
                            <button type="submit" name="toggle_evento" class="btn btn-outline btn-sm">
                                <i class="fas fa-power-off"></i>
                            </button>
                        </form>

                        <form method="POST" onsubmit="return confirm('Deletar evento e banner?')" style="display:inline;">
                            <input type="hidden" name="evento_id" value="<?= $evento['id'] ?>">
                            <button type="submit" name="deletar_evento" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($eventos)): ?>
                <div style="text-align:center; padding: 50px; border: 2px dashed var(--border); border-radius: 12px; color: var(--text-secondary);">
                    <i class="fas fa-calendar-plus" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 15px; display: block;"></i>
                    <p>Nenhum evento cadastrado</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- LOG CUPONS -->
        <h2 class="section-title" style="margin: 60px 0 20px;">
            <i class="fas fa-file-invoice"></i> Log de Cupons
        </h2>

        <div class="panel" style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Estúdio</th>
                        <th>Desconto</th>
                        <th>Uso</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cupons_log as $log): ?>
                        <tr>
                            <td><span class="code-pill"><?= $log['codigo'] ?></span></td>
                            <td><?= htmlspecialchars($log['nome_estudio']) ?></td>
                            <td>
                                <?= $log['tipo_desconto'] == 'percentual'
                                    ? $log['valor_desconto'] . '%'
                                    : formatPrice($log['valor_desconto']) ?>
                            </td>
                            <td><?= $log['usos_atuais'] ?> / <?= $log['usos_maximos'] ?: '∞' ?></td>
                            <td style="color: var(--text-secondary);"><?= date('d/m/Y', strtotime($log['criado_em'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cupons_log)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 30px;">Nenhum registro</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<!-- ============================================
     MODAL CRIAR EVENTO
     ============================================ -->
<div id="createEventModal" class="modal">
    <div class="modal-box">
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-header">
                <h3 style="color: var(--text-primary); margin:0; font-size: 1.1rem;">
                    <i class="fas fa-calendar-star" style="color: var(--accent);"></i> Novo Evento Sazonal
                </h3>
                <button type="button" onclick="toggleModal('createEventModal')"
                    style="background:none; border:none; color:var(--text-secondary); cursor:pointer; font-size: 1.2rem; padding: 5px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">

                <!-- INFORMAÇÕES DO EVENTO -->
                <div class="form-divider">
                    <i class="fas fa-info-circle"></i> Informações do Evento
                </div>

                <div class="form-group">
                    <label class="form-label">Nome do Evento <span class="required">*</span></label>
                    <input type="text" name="nome" class="form-input" required placeholder="Ex: Summer Sale 2026">
                </div>

                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" class="form-input" rows="2" placeholder="Descrição curta para o banner"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Início <span class="required">*</span></label>
                        <input type="datetime-local" name="data_inicio" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Término <span class="required">*</span></label>
                        <input type="datetime-local" name="data_fim" class="form-input" required>
                    </div>
                </div>

                <!-- IMAGENS DO BANNER -->
                <div class="form-divider">
                    <i class="fas fa-image"></i> Imagens do Banner
                </div>

                <div class="form-group">
                    <label class="form-label">Imagem de Fundo</label>
                    <div class="file-upload-area" id="bannerUploadArea" onclick="document.getElementById('imagemBannerInput').click()">
                        <input type="file" name="imagem_banner" id="imagemBannerInput" accept="image/*" style="display:none;">
                        <div class="file-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <div class="file-upload-text">
                            <strong>Clique para enviar</strong> ou arraste a imagem
                        </div>
                        <div class="file-upload-preview" id="bannerPreview">
                            <img src="" alt="Preview">
                        </div>
                        <div class="file-upload-info" id="bannerInfo"></div>
                    </div>
                    <p class="form-hint">Recomendado: 1920x500px • Máx: 10MB • Será comprimida automaticamente</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Imagem Overlay (Opcional)</label>
                    <div class="file-upload-area" id="overlayUploadArea" onclick="document.getElementById('imagemOverlayInput').click()">
                        <input type="file" name="imagem_overlay" id="imagemOverlayInput" accept="image/png,image/webp,image/gif" style="display:none;">
                        <div class="file-upload-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="file-upload-text">
                            <strong>PNG transparente</strong> (personagem, produto)
                        </div>
                        <div class="file-upload-preview" id="overlayPreview">
                            <img src="" alt="Preview">
                        </div>
                        <div class="file-upload-info" id="overlayInfo"></div>
                    </div>
                    <p class="form-hint">Aparece sobreposta ao fundo • Use PNG com transparência</p>
                </div>

                <!-- TEXTOS DO BANNER -->
                <div class="form-divider">
                    <i class="fas fa-font"></i> Textos Promocionais
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Texto Principal</label>
                        <input type="text" name="texto_principal" class="form-input" value="TERMINA EM" placeholder="TERMINA EM">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Texto Secundário</label>
                        <input type="text" name="texto_secundario" class="form-input" value="ECONOMIZE ATÉ" placeholder="ECONOMIZE ATÉ">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Destaque</label>
                        <input type="text" name="texto_destaque" class="form-input" placeholder="75%">
                        <p class="form-hint">Ex: 75%, 50% OFF</p>
                    </div>
                </div>

                <!-- CORES -->
                <div class="form-divider">
                    <i class="fas fa-palette"></i> Cores
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Fundo</label>
                        <div class="color-picker-group">
                            <input type="color" name="cor_fundo" value="#131314" class="color-preview-input" id="corFundo">
                            <span class="color-value" id="corFundoVal">#131314</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Texto</label>
                        <div class="color-picker-group">
                            <input type="color" name="cor_texto" value="#E3E3E3" class="color-preview-input" id="corTexto">
                            <span class="color-value" id="corTextoVal">#E3E3E3</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Destaque</label>
                        <div class="color-picker-group">
                            <input type="color" name="cor_destaque" value="#4C8BF5" class="color-preview-input" id="corDestaque">
                            <span class="color-value" id="corDestaqueVal">#4C8BF5</span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" onclick="toggleModal('createEventModal')" class="btn btn-outline">
                    Cancelar
                </button>
                <button type="submit" name="criar_evento" class="btn btn-primary">
                    <i class="fas fa-rocket"></i> Criar Evento
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleModal(id) {
        const modal = document.getElementById(id);
        modal.classList.toggle('active');
    }

    // Color pickers
    ['corFundo', 'corTexto', 'corDestaque'].forEach(id => {
        document.getElementById(id).addEventListener('input', function() {
            document.getElementById(id + 'Val').textContent = this.value.toUpperCase();
        });
    });

    // File upload handlers
    function setupFileUpload(inputId, areaId, previewId, infoId) {
        const input = document.getElementById(inputId);
        const area = document.getElementById(areaId);
        const preview = document.getElementById(previewId);
        const info = document.getElementById(infoId);

        input.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();

                reader.onload = function(e) {
                    preview.querySelector('img').src = e.target.result;
                    preview.classList.add('visible');
                    area.classList.add('has-file');

                    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                    info.textContent = `${file.name} (${sizeMB} MB)`;
                };

                reader.readAsDataURL(file);
            }
        });

        // Drag and drop
        area.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        area.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });

        area.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');

            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });
    }

    setupFileUpload('imagemBannerInput', 'bannerUploadArea', 'bannerPreview', 'bannerInfo');
    setupFileUpload('imagemOverlayInput', 'overlayUploadArea', 'overlayPreview', 'overlayInfo');

    // Fechar modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
        }
    });

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>