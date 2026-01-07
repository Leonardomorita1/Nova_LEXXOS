<?php
// api/add-review.php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

$jogo_id = $_POST['jogo_id'] ?? null;
$nota = $_POST['nota'] ?? null;
$comentario = trim($_POST['comentario'] ?? '');

// Validações
if (!$jogo_id || !$nota || empty($comentario)) {
    $_SESSION['error'] = 'Preencha todos os campos';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

if ($nota < 1 || $nota > 5) {
    $_SESSION['error'] = 'Nota inválida';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

try {
    // Verificar se o jogo está na biblioteca
    $stmt = $pdo->prepare("SELECT id FROM biblioteca WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Você precisa ter o jogo para avaliá-lo';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Verificar se já avaliou
    $stmt = $pdo->prepare("SELECT id FROM avaliacao WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Você já avaliou este jogo';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Adicionar avaliação
    $stmt = $pdo->prepare("
        INSERT INTO avaliacao (usuario_id, jogo_id, nota, comentario, status)
        VALUES (?, ?, ?, ?, 'ativo')
    ");
    $stmt->execute([$user_id, $jogo_id, $nota, $comentario]);
    
    $_SESSION['success'] = 'Avaliação enviada com sucesso!';
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Erro ao enviar avaliação: ' . $e->getMessage();
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;