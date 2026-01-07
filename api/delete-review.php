<?php
// api/delete-review.php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) exit;

$database = new Database();
$pdo = $database->getConnection();
$avaliacao_id = filter_input(INPUT_POST, 'avaliacao_id', FILTER_SANITIZE_NUMBER_INT);

if ($avaliacao_id) {
    // Deleta APENAS se a avaliação pertencer ao usuário logado
    $stmt = $pdo->prepare("DELETE FROM avaliacao WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$avaliacao_id, $_SESSION['user_id']]);
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;