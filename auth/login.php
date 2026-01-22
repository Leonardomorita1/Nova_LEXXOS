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
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $old_email = $email;

    if (empty($email)) $email_error = true;
    if (empty($senha)) $pass_error = true;

    if (!$email_error && !$pass_error) {
        $database = new Database();
        $pdo = $database->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM usuario WHERE email = ? AND status != 'banido'");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nome_usuario'];
            $_SESSION['user_type'] = $usuario['tipo'];
            $_SESSION['user_theme'] = $usuario['tema'] ?? 'dark';

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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg-primary);
            min-height: 100vh;
            display: flex;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* Layout Split */
        .login-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Left Side - Form */
        .login-form-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            background: var(--bg-primary);
            position: relative;
        }

        /* Right Side - Branding */
        .login-brand {
            flex: 1.2;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }

        .login-brand::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, var(--accent) 0%, transparent 70%);
            opacity: 0.05;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: breathe 8s ease-in-out infinite;
        }

        .login-brand::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(var(--accent-rgb, 99, 102, 241), 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(var(--accent-rgb, 99, 102, 241), 0.03) 0%, transparent 50%);
        }

        @keyframes breathe {

            0%,
            100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 0.05;
            }

            50% {
                transform: translate(-50%, -50%) scale(1.1);
                opacity: 0.08;
            }
        }

        .brand-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 400px;
        }

        .brand-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent), var(--accent-hover, #4f46e5));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 2rem;
            color: white;
            box-shadow: 0 20px 40px rgba(var(--accent-rgb, 99, 102, 241), 0.3);
        }

        .brand-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 16px;
        }

        .brand-description {
            color: var(--text-secondary);
            font-size: 1.05rem;
            line-height: 1.7;
            margin-bottom: 40px;
        }

        .brand-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--accent);
            display: block;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        /* Form Container */
        .form-container {
            width: 100%;
            max-width: 400px;
        }

        .form-logo {
            margin-bottom: 48px;
        }

        .form-logo a {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            transition: opacity 0.2s ease;
        }

        .form-logo a:hover {
            opacity: 0.85;
        }

        .form-logo svg {
            width: 44px;
            height: 44px;
            color: var(--accent);
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }

        .form-logo a:hover svg {
            transform: scale(1.05);
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: var(--accent);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.25rem;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: -0.5px;
        }

        .logo-text span {
            color: var(--accent);
        }

        /* Form Header */
        .form-header {
            margin-bottom: 32px;
        }

        .form-header h1 {
            color: var(--text-primary);
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Alert */
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .alert i {
            font-size: 1.1rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            color: var(--text-primary);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .forgot-link {
            font-size: 0.85rem;
            color: var(--accent);
            text-decoration: none;
            transition: opacity 0.2s;
        }

        .forgot-link:hover {
            opacity: 0.8;
            text-decoration: underline;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(var(--accent-rgb, 99, 102, 241), 0.1);
        }

        .form-control.error {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }

        /* Password Wrapper */
        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.2s;
            padding: 4px;
        }

        .password-toggle:hover {
            color: var(--text-primary);
        }

        /* Remember Me */
        .remember-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .custom-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .remember-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            cursor: pointer;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 14px 24px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(var(--accent-rgb, 99, 102, 241), 0.2);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit i {
            transition: transform 0.2s;
        }

        .btn-submit:hover i {
            transform: translateX(4px);
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            margin: 32px 0;
            gap: 16px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Social Buttons */
        .social-buttons {
            display: flex;
            gap: 12px;
        }

        .btn-social {
            flex: 1;
            padding: 12px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-social:hover {
            background: var(--border);
            transform: translateY(-2px);
        }

        .btn-social i {
            font-size: 1.1rem;
        }

        .btn-social.google i {
            color: #ea4335;
        }

        .btn-social.discord i {
            color: #5865f2;
        }

        /* Register Link */
        .register-link {
            text-align: center;
            margin-top: 32px;
            padding-top: 32px;
            border-top: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .register-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            margin-left: 4px;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        /* Footer */
        .form-footer {
            position: absolute;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 24px;
            font-size: 0.8rem;
        }

        .form-footer a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s;
        }

        .form-footer a:hover {
            color: var(--text-primary);
        }

        /* Mobile Responsive */
        @media (max-width: 968px) {
            .login-brand {
                display: none;
            }

            .login-form-side {
                padding: 24px;
            }

            .form-logo {
                text-align: center;
                margin-bottom: 40px;
            }

            .form-footer {
                position: relative;
                bottom: auto;
                left: auto;
                transform: none;
                justify-content: center;
                margin-top: 32px;
            }
        }

        @media (max-width: 480px) {
            .social-buttons {
                flex-direction: column;
            }

            .brand-stats {
                flex-direction: column;
                gap: 20px;
            }
        }

        /* Loading State */
        .btn-submit.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-submit.loading::after {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-left: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <!-- Left Side - Form -->
        <div class="login-form-side">
            <div class="form-container">
                <!-- Logo -->
                <div class="form-logo">
                    <a href="<?= SITE_URL ?>">
                        <?php echo SITE_LOGO; ?>
                        <?php echo SITE_NAME; ?>
                    </a>
                </div>

                <!-- Header -->
                <div class="form-header">
                    <h1>Bem-vindo de volta</h1>
                    <p>Entre na sua conta para continuar</p>
                </div>

                <!-- Error Alert -->
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= $error_msg ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input type="email"
                            name="email"
                            id="email"
                            class="form-control <?= $email_error ? 'error' : '' ?>"
                            value="<?= htmlspecialchars($old_email) ?>"
                            placeholder="Digite seu email"
                            autocomplete="email">
                    </div>

                    <div class="form-group">
                        <div class="label-row">
                            <label class="form-label" for="senha">Senha</label>
                            <a href="forgot-password.php" class="forgot-link">Esqueceu a senha?</a>
                        </div>
                        <div class="password-wrapper">
                            <input type="password"
                                name="senha"
                                id="senha"
                                class="form-control <?= $pass_error ? 'error' : '' ?>"
                                placeholder="Digite sua senha"
                                autocomplete="current-password">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
                        </div>
                    </div>

                    <div class="remember-row">
                        <input type="checkbox" name="lembrar" id="lembrar" class="custom-checkbox">
                        <label for="lembrar" class="remember-label">Manter conectado</label>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span>Entrar</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>



                <!-- Register Link -->
                <div class="register-link">
                    Não tem uma conta?<a href="register.php">Criar conta</a>
                </div>
            </div>

            <!-- Footer Links -->
            <div class="form-footer">
                <a href="#">Termos de Uso</a>
                <a href="#">Privacidade</a>
                <a href="#">Suporte</a>
            </div>
        </div>

        <!-- Right Side - Branding -->
        <div class="login-brand">
            <div class="brand-content">
                <div class="brand-icon">
                    <i class="fas fa-gamepad"></i>
                </div>
                <h2 class="brand-title">Sua jornada começa aqui</h2>
                <p class="brand-description">
                    Acesse milhares de jogos, conecte-se com amigos e faça parte
                    de uma comunidade incrível. A aventura te espera.
                </p>

            </div>
        </div>
    </div>

    <script>
        // Toggle Password Visibility
        function togglePassword() {
            const input = document.getElementById('senha');
            const icon = document.querySelector('.password-toggle');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Form Submit Loading State
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const email = document.getElementById('email').value.trim();
            const senha = document.getElementById('senha').value;

            // Basic validation
            let hasError = false;

            if (!email) {
                document.getElementById('email').classList.add('error');
                hasError = true;
            } else {
                document.getElementById('email').classList.remove('error');
            }

            if (!senha) {
                document.getElementById('senha').classList.add('error');
                hasError = true;
            } else {
                document.getElementById('senha').classList.remove('error');
            }

            if (hasError) {
                e.preventDefault();
                return;
            }

            btn.classList.add('loading');
            btn.querySelector('span').textContent = 'Entrando...';
            btn.querySelector('i').style.display = 'none';
        });

        // Remove error on input
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('error');
            });
        });

        // Enter key handling
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('loginForm').dispatchEvent(new Event('submit'));
                }
            });
        });
    </script>
</body>

</html>