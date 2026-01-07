<?php
// auth/login.php
require_once '../config/config.php';
require_once '../config/database.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    
    if (empty($email) || empty($senha)) {
        $error = 'Preencha todos os campos';
    } else {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM usuario WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nome_usuario'];
            $_SESSION['user_type'] = $usuario['tipo'];
            $_SESSION['user_theme'] = $usuario['tema_preferencia'];
            
            // Log de acesso
            $stmt = $pdo->prepare("INSERT INTO log_acesso (usuario_id, email, evento, ip, user_agent) VALUES (?, ?, 'login_sucesso', ?, ?)");
            $stmt->execute([
                $usuario['id'],
                $email,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
            
            // Atualizar último login
            $stmt = $pdo->prepare("UPDATE usuario SET ultimo_login = NOW() WHERE id = ?");
            $stmt->execute([$usuario['id']]);
            
            // Redirecionar baseado no tipo de usuário
            if ($usuario['tipo'] == 'admin') {
                header('Location: ' . SITE_URL . '/admin/dashboard.php');
            } elseif ($usuario['tipo'] == 'desenvolvedor') {
                header('Location: ' . SITE_URL . '/developer/dashboard.php');
            } else {
                header('Location: ' . SITE_URL . '/pages/home.php');
            }
            exit;
        } else {
            $error = 'Email ou senha incorretos';
            
            // Log de falha
            if ($usuario) {
                $stmt = $pdo->prepare("INSERT INTO log_acesso (usuario_id, email, evento, ip, user_agent) VALUES (?, ?, 'login_falha', ?, ?)");
                $stmt->execute([
                    $usuario['id'],
                    $email,
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                ]);
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
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .auth-logo h1 {
            font-size: 36px;
            color: var(--accent);
            margin-bottom: 10px;
        }
        .auth-logo p {
            color: var(--text-secondary);
        }
        .error-message {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        .auth-divider {
            text-align: center;
            margin: 20px 0;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">
                <h1><?php echo SITE_NAME; ?></h1>
                <p>Entre na maior plataforma de jogos indies</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i> Email
                    </label>
                    <input type="email" 
                           name="email" 
                           class="form-control" 
                           placeholder="seu@email.com"
                           value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>"
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Senha
                    </label>
                    <input type="password" 
                           name="senha" 
                           class="form-control" 
                           placeholder="••••••••"
                           required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>
            
            <div class="auth-divider">
                <span>OU</span>
            </div>
            
            <a href="register.php" class="btn btn-secondary btn-block">
                <i class="fas fa-user-plus"></i> Criar uma conta
            </a>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="<?php echo SITE_URL; ?>/pages/home.php" style="color: var(--text-secondary); font-size: 14px;">
                    <i class="fas fa-arrow-left"></i> Voltar para home
                </a>
            </div>
        </div>
    </div>
</body>
</html>