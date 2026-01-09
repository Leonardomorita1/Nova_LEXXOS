<?php
// auth/register.php
require_once '../config/config.php';
require_once '../config/database.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$errors = [];
$old = []; // Para manter os dados preenchidos se der erro

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Coleta dados
    $old = $_POST;
    $nome_usuario = trim($_POST['nome_usuario']);
    $nome_completo = trim($_POST['nome_completo']);
    $email = trim($_POST['email']);
    $data_nascimento = $_POST['data_nascimento'];
    $senha = $_POST['senha'];
    $senha_confirmar = $_POST['senha_confirmar'];
    
    // Validações
    if (empty($nome_usuario)) $errors['nome_usuario'] = 'Obrigatório';
    if (empty($nome_completo)) $errors['nome_completo'] = 'Obrigatório';
    if (empty($data_nascimento)) $errors['data_nascimento'] = 'Obrigatório';
    
    if (empty($email)) {
        $errors['email'] = 'Obrigatório';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email inválido';
    }

    if (empty($senha)) {
        $errors['senha'] = 'Obrigatório';
    } elseif (strlen($senha) < 6) {
        $errors['senha'] = 'Mínimo 6 caracteres';
    }

    if ($senha !== $senha_confirmar) {
        $errors['senha_confirmar'] = 'As senhas não coincidem';
    }

    // Se não houver erros locais, checa banco de dados
    if (empty($errors)) {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Verificar duplicidade
        $stmt = $pdo->prepare("SELECT id FROM usuario WHERE email = ? OR nome_usuario = ?");
        $stmt->execute([$email, $nome_usuario]);
        if ($stmt->fetch()) {
            $errors['geral'] = 'Email ou Nome de Usuário já cadastrados.';
        } else {
            // Inserir
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuario (nome_usuario, nome_completo, email, data_nascimento, senha_hash, tipo, status) VALUES (?, ?, ?, ?, ?, 'cliente', 'ativo')");
            
            if ($stmt->execute([$nome_usuario, $nome_completo, $email, $data_nascimento, $senha_hash])) {
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['user_name'] = $nome_usuario;
                $_SESSION['user_type'] = 'cliente';
                header('Location: ' . SITE_URL . '/pages/home.php');
                exit;
            } else {
                $errors['geral'] = 'Erro ao criar conta. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - Lexxos</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body style="background: var(--bg-primary); min-height: 100vh; display: flex; align-items: center; justify-content: center;">

    <div class="auth-container" style="width: 100%; max-width: 500px; padding: 20px;">
        <div class="auth-card" style="background: var(--bg-secondary); padding: 40px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            
            <div class="text-center mb-4">
                <h2 style="color: var(--text-primary); margin-bottom: 10px;">Crie sua conta</h2>
                <p style="color: var(--text-secondary);">Junte-se à comunidade Lexxos</p>
            </div>

            <?php if (isset($errors['geral'])): ?>
                <div class="alert alert-danger mb-3" style="color: var(--danger); background: rgba(220, 53, 69, 0.1); padding: 10px; border-radius: 8px; font-size: 0.9rem;">
                    <?= $errors['geral'] ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Nome Completo</label>
                        <input type="text" name="nome_completo" 
                               class="form-control <?= isset($errors['nome_completo']) ? 'error' : '' ?>" 
                               value="<?= htmlspecialchars($old['nome_completo'] ?? '') ?>"
                               placeholder="Seu nome real">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nascimento</label>
                        <input type="date" name="data_nascimento" 
                               class="form-control <?= isset($errors['data_nascimento']) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($old['data_nascimento'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label class="form-label">Nome de Usuário</label>
                    <input type="text" name="nome_usuario" 
                           class="form-control <?= isset($errors['nome_usuario']) ? 'error' : '' ?>"
                           value="<?= htmlspecialchars($old['nome_usuario'] ?? '') ?>"
                           placeholder="Como quer ser chamado">
                </div>

                <div class="form-group mt-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" 
                           class="form-control <?= isset($errors['email']) ? 'error' : '' ?>"
                           value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                           placeholder="seu@email.com">
                    <?php if(isset($errors['email'])): ?><span class="error-msg"><?= $errors['email'] ?></span><?php endif; ?>
                </div>

                <div class="form-group mt-3">
                    <label class="form-label">Senha</label>
                    <div class="password-wrapper">
                        <input type="password" name="senha" id="senha"
                               class="form-control <?= isset($errors['senha']) ? 'error' : '' ?>"
                               placeholder="Mínimo 6 caracteres">
                        <i class="fas fa-eye password-toggle" onclick="togglePass('senha')"></i>
                    </div>
                    <?php if(isset($errors['senha'])): ?><span class="error-msg"><?= $errors['senha'] ?></span><?php endif; ?>
                </div>

                <div class="form-group mt-3">
                    <label class="form-label">Confirmar Senha</label>
                    <div class="password-wrapper">
                        <input type="password" name="senha_confirmar" id="senha_conf"
                               class="form-control <?= isset($errors['senha_confirmar']) ? 'error' : '' ?>"
                               placeholder="Repita a senha">
                        <i class="fas fa-eye password-toggle" onclick="togglePass('senha_conf')"></i>
                    </div>
                    <?php if(isset($errors['senha_confirmar'])): ?><span class="error-msg"><?= $errors['senha_confirmar'] ?></span><?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block mt-4" style="width: 100%; padding: 12px; font-weight: bold;">
                    Cadastrar
                </button>
            </form>

            <div class="divider" style="text-align: center; margin: 20px 0; position: relative;">
                <span style="background: var(--bg-secondary); padding: 0 10px; color: var(--text-secondary); position: relative; z-index: 1;">OU</span>
                <div style="position: absolute; top: 50%; left: 0; width: 100%; height: 1px; background: var(--border);"></div>
            </div>

            

            <p class="text-center mt-4" style="color: var(--text-secondary);">
                Já tem uma conta? <a href="login.php" style="color: var(--accent); font-weight: bold;">Fazer Login</a>
            </p>
        </div>
    </div>

    <script>
    function togglePass(id) {
        const input = document.getElementById(id);
        const icon = input.nextElementSibling;
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = "password";
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    
    </script>
</body>
</html>