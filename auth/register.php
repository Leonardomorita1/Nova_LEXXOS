<?php
// auth/register.php
require_once '../config/config.php';
require_once '../config/database.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_usuario = trim($_POST['nome_usuario']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $senha_confirmar = $_POST['senha_confirmar'];
    
    // Validações
    if (empty($nome_usuario) || empty($email) || empty($senha) || empty($senha_confirmar)) {
        $error = 'Preencha todos os campos';
    } elseif (strlen($nome_usuario) < 3 || strlen($nome_usuario) > 50) {
        $error = 'Nome de usuário deve ter entre 3 e 50 caracteres';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido';
    } elseif (strlen($senha) < 6) {
        $error = 'Senha deve ter no mínimo 6 caracteres';
    } elseif ($senha !== $senha_confirmar) {
        $error = 'As senhas não coincidem';
    } else {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Verificar se email já existe
        $stmt = $pdo->prepare("SELECT id FROM usuario WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Este email já está cadastrado';
        } else {
            // Verificar se nome de usuário já existe
            $stmt = $pdo->prepare("SELECT id FROM usuario WHERE nome_usuario = ?");
            $stmt->execute([$nome_usuario]);
            if ($stmt->fetch()) {
                $error = 'Este nome de usuário já está em uso';
            } else {
                // Criar usuário
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO usuario (nome_usuario, email, senha_hash, tipo, status, criado_em) 
                    VALUES (?, ?, ?, 'cliente', 'ativo', NOW())
                ");
                
                if ($stmt->execute([$nome_usuario, $email, $senha_hash])) {
                    $success = 'Conta criada com sucesso! Você já pode fazer login.';
                    
                    // Limpar campos
                    $_POST = array();
                } else {
                    $error = 'Erro ao criar conta. Tente novamente.';
                }
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
    <title>Cadastro - <?php echo SITE_NAME; ?></title>
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
        .success-message {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
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
        .password-requirements {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">
                <h1><?php echo SITE_NAME; ?></h1>
                <p>Crie sua conta e comece a jogar</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <br><br>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Fazer Login
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Nome de Usuário
                        </label>
                        <input type="text" 
                               name="nome_usuario" 
                               class="form-control" 
                               placeholder="seunome"
                               value="<?php echo isset($_POST['nome_usuario']) ? sanitize($_POST['nome_usuario']) : ''; ?>"
                               minlength="3"
                               maxlength="50"
                               required>
                    </div>
                    
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
                               minlength="6"
                               required>
                        <div class="password-requirements">
                            Mínimo de 6 caracteres
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Confirmar Senha
                        </label>
                        <input type="password" 
                               name="senha_confirmar" 
                               class="form-control" 
                               placeholder="••••••••"
                               minlength="6"
                               required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i> Criar Conta
                    </button>
                </form>
                
                <div class="auth-divider">
                    <span>JÁ TEM UMA CONTA?</span>
                </div>
                
                <a href="login.php" class="btn btn-secondary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Fazer Login
                </a>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="<?php echo SITE_URL; ?>/pages/home.php" style="color: var(--text-secondary); font-size: 14px;">
                    <i class="fas fa-arrow-left"></i> Voltar para home
                </a>
            </div>
        </div>
    </div>
</body>
</html>