<?php
// api/avaliar.php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'add';
    $jogo_id = $_POST['jogo_id'];
    
    // Validar se usuário tem o jogo (segurança básica)
    $stmt = $pdo->prepare("SELECT id FROM biblioteca WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Você não possui este jogo.';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    try {
        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM avaliacao WHERE usuario_id = ? AND jogo_id = ?");
            $stmt->execute([$user_id, $jogo_id]);
            $_SESSION['success'] = 'Avaliação removida.';

        } else {
            // Add ou Update
            $nota = (int)$_POST['nota'];
            $comentario = trim($_POST['comentario']);

            if ($nota < 1 || $nota > 5 || empty($comentario)) {
                throw new Exception("Dados inválidos.");
            }

            if ($action === 'update') {
                $stmt = $pdo->prepare("UPDATE avaliacao SET nota = ?, comentario = ?, atualizado_em = NOW() WHERE usuario_id = ? AND jogo_id = ?");
                $stmt->execute([$nota, $comentario, $user_id, $jogo_id]);
                $_SESSION['success'] = 'Avaliação atualizada!';
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO avaliacao (usuario_id, jogo_id, nota, comentario) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $jogo_id, $nota, $comentario]);
                $_SESSION['success'] = 'Avaliação publicada!';
            }
        }
        
        // Recalcular média do jogo (Opcional, mas recomendado)
        $stmt = $pdo->prepare("SELECT AVG(nota) as media, COUNT(id) as total FROM avaliacao WHERE jogo_id = ?");
        $stmt->execute([$jogo_id]);
        $stats = $stmt->fetch();
        $pdo->prepare("UPDATE jogo SET nota_media = ?, total_avaliacoes = ? WHERE id = ?")
            ->execute([$stats['media'] ?? 0, $stats['total'], $jogo_id]);

    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}