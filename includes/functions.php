<?php
// includes/functions.php

// Função para buscar configurações do banco
function getConfig($chave, $pdo) {
    $stmt = $pdo->prepare("SELECT valor FROM configuracao WHERE chave = ?");
    $stmt->execute([$chave]);
    $result = $stmt->fetch();
    return $result ? $result['valor'] : null;
}

// Função para verificar se jogo está na biblioteca
function isInLibrary($user_id, $jogo_id, $pdo) {
    $stmt = $pdo->prepare("SELECT id FROM biblioteca WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    return $stmt->fetch() !== false;
}

// Função para verificar se jogo está no carrinho
function isInCart($user_id, $jogo_id, $pdo) {
    $stmt = $pdo->prepare("SELECT id FROM carrinho WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    return $stmt->fetch() !== false;
}

// Função para verificar se jogo está na lista de desejos
function isInWishlist($user_id, $jogo_id, $pdo) {
    $stmt = $pdo->prepare("SELECT id FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo_id]);
    return $stmt->fetch() !== false;
}

// Função para contar itens do carrinho
function getCartCount($user_id, $pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM carrinho WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['count'];
}

// Função para obter tema do usuário
function getUserTheme($user_id, $pdo) {
    $stmt = $pdo->prepare("SELECT tema_preferencia FROM usuario WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['tema_preferencia'] : 'dark';
}

// Função para calcular desconto
function calculateDiscount($preco, $preco_promocional, $em_promocao) {
    if (!$em_promocao || !$preco_promocional) return 0;
    return round((($preco - $preco_promocional) / $preco) * 100);
}

// Função para gerar slug
function generateSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Função para upload de arquivo
function uploadFile($file, $destination_path) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Tipo de arquivo não permitido'];
    }
    
    if ($file['size'] > 5242880) { // 5MB
        return ['success' => false, 'message' => 'Arquivo muito grande'];
    }
    
    $filename = time() . '-' . basename($file['name']);
    $target_file = $destination_path . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'path' => $target_file];
    }
    
    return ['success' => false, 'message' => 'Erro ao fazer upload'];
}