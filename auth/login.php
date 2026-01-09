<?php
// auth/login.php
require_once '../config/config.php';
require_once '../config/database.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$error_msg = '';
$email_error = false;
$pass_error = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    
    if (empty($email)) $email_error = true;
    if (empty($senha)) $pass_error = true;

    if (!$email_error && !$pass_error) {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM usuario WHERE email = ? AND status != 'banido'");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            // Login Sucesso
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nome_usuario'];
            $_SESSION['user_type'] = $usuario['tipo'];
            $_SESSION['user_theme'] = $usuario['tema'] ?? 'dark'; // Usa coluna 'tema' que adicionamos antes
            
            // Atualizar ultimo login
            $pdo->prepare("UPDATE usuario SET ultimo_login = NOW() WHERE id = ?")->execute([$usuario['id']]);

            header('Location: ' . SITE_URL . '/pages/home.php');
            exit;
        } else {
            $error_msg = 'Email ou senha incorretos.';
            $email_error = true;
            $pass_error = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lexxos</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body style="background: var(--bg-primary); min-height: 100vh; display: flex; align-items: center; justify-content: center;">

    <div class="auth-container" style="width: 100%; max-width: 450px; padding: 20px;">
        <div class="auth-card" style="background: var(--bg-secondary); padding: 40px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            
            <div class="text-center mb-5">
                <a href="<?= SITE_URL ?>" style="text-decoration: none;">
                    <h1 style="font-size: 2rem; color: var(--text-primary);"><span style="color: var(--accent);">L</span>exxos</h1>
                </a>
                <p style="color: var(--text-secondary);">Bem-vindo de volta!</p>
            </div>

            <?php if ($error_msg): ?>
                <div class="alert alert-danger text-center mb-4" style="color: var(--danger); background: rgba(220, 53, 69, 0.1); padding: 10px; border-radius: 8px;">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group mb-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" 
                           class="form-control <?= $email_error ? 'error' : '' ?>"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="seu@email.com">
                </div>
                
                <div class="form-group mb-4">
                    <div style="display: flex; justify-content: space-between;">
                        <label class="form-label">Senha</label>
                        <a href="#" style="font-size: 0.85rem; color: var(--accent);">Esqueceu a senha?</a>
                    </div>
                    <div class="password-wrapper">
                        <input type="password" name="senha" id="senha_login"
                               class="form-control <?= $pass_error ? 'error' : '' ?>"
                               placeholder="••••••••">
                        <i class="fas fa-eye password-toggle" onclick="togglePass('senha_login')"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block" style="width: 100%; padding: 12px; font-weight: bold; font-size: 1rem;">
                    Entrar
                </button>
            </form>
            
            <div class="divider" style="text-align: center; margin: 25px 0; position: relative;">
                <span style="background: var(--bg-secondary); padding: 0 10px; color: var(--text-secondary); position: relative; z-index: 1;">OU CONTINUE COM</span>
                <div style="position: absolute; top: 50%; left: 0; width: 100%; height: 1px; background: var(--border);"></div>
            </div>

            
            
            <p class="text-center mt-5" style="color: var(--text-secondary);">
                Não tem uma conta? <a href="register.php" style="color: var(--accent); font-weight: bold;">Cadastre-se</a>
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