<?php
// config/config.php
session_start();

// Configurações do site
define('SITE_NAME', 'Lexxos');
define('SITE_URL', '');
define('BASE_PATH', __DIR__ . '/..');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Funções auxiliares globais
require_once BASE_PATH . '/includes/functions.php';

// Função para verificar se está logado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Função para verificar tipo de usuário
function getUserType() {
    return $_SESSION['user_type'] ?? 'cliente';
}

// Função para redirecionar se não estiver logado
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit;
    }
}

// Função para formatar preço
function formatPrice($centavos) {
    return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
}

// Função para obter avatar padrão
function getAvatar($avatar_url) {
    return $avatar_url ?: SITE_URL . '/assets/images/default-avatar.png';
}

// Proteção contra XSS
function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}