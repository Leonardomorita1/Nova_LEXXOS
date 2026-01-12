<?php
// auth/register.php
require_once '../config/config.php';
require_once '../config/database.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $old = $_POST;
    $nome_usuario = trim($_POST['nome_usuario']);
    $nome_completo = trim($_POST['nome_completo']);
    $email = trim($_POST['email']);

    // Converte data de nascimento dos 3 campos
    $dia = str_pad($_POST['dia_nascimento'], 2, '0', STR_PAD_LEFT);
    $mes = str_pad($_POST['mes_nascimento'], 2, '0', STR_PAD_LEFT);
    $ano = $_POST['ano_nascimento'];
    $data_nascimento = "$ano-$mes-$dia";

    $senha = $_POST['senha'];
    $senha_confirmar = $_POST['senha_confirmar'];

    // Validações
    if (empty($nome_usuario)) $errors['nome_usuario'] = 'Nome de usuário é obrigatório';
    elseif (strlen($nome_usuario) < 3) $errors['nome_usuario'] = 'Mínimo 3 caracteres';
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nome_usuario)) $errors['nome_usuario'] = 'Apenas letras, números e _';

    if (empty($nome_completo)) $errors['nome_completo'] = 'Nome completo é obrigatório';

    if (empty($dia) || empty($mes) || empty($ano)) {
        $errors['data_nascimento'] = 'Data de nascimento completa é obrigatória';
    } elseif (!checkdate((int)$mes, (int)$dia, (int)$ano)) {
        $errors['data_nascimento'] = 'Data inválida';
    } else {
        $idade = date_diff(date_create($data_nascimento), date_create('today'))->y;
        if ($idade < 13) $errors['data_nascimento'] = 'Você deve ter pelo menos 13 anos';
    }

    if (empty($email)) {
        $errors['email'] = 'Email é obrigatório';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email inválido';
    }

    if (empty($senha)) {
        $errors['senha'] = 'Senha é obrigatória';
    } elseif (strlen($senha) < 6) {
        $errors['senha'] = 'Mínimo 6 caracteres';
    }

    if ($senha !== $senha_confirmar) {
        $errors['senha_confirmar'] = 'As senhas não coincidem';
    }

    if (empty($errors)) {
        $database = new Database();
        $pdo = $database->getConnection();

        $stmt = $pdo->prepare("SELECT id FROM usuario WHERE email = ? OR nome_usuario = ?");
        $stmt->execute([$email, $nome_usuario]);
        if ($stmt->fetch()) {
            $errors['geral'] = 'Email ou Nome de Usuário já cadastrados.';
        } else {
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

// Gera lista de anos
$anoAtual = date('Y');
$anos = range($anoAtual - 100, $anoAtual);
rsort($anos);

$meses = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Março',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro'
];
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - Lexxos</title>
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
        .register-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Left Side - Branding */
        .register-brand {
            flex: 1;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }

        .register-brand::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, var(--accent) 0%, transparent 70%);
            opacity: 0.03;
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .brand-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 40px;
        }

        .brand-logo svg {
            width: 64px;
            height: 64px;
            color: var(--accent);
            filter: drop-shadow(0 4px 12px rgba(var(--accent-rgb, 99, 102, 241), 0.4));
        }

        .brand-logo span {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -1px;
        }

        .brand-tagline {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 300px;
            line-height: 1.6;
        }

        .brand-features {
            margin-top: 60px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .brand-feature {
            display: flex;
            align-items: center;
            gap: 16px;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .brand-feature i {
            width: 40px;
            height: 40px;
            background: var(--bg-primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 1rem;
        }

        /* Right Side - Form */
        .register-form-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            background: var(--bg-primary);
        }

        .form-container {
            width: 100%;
            max-width: 420px;
        }

        /* Progress Bar */
        .progress-container {
            margin-bottom: 40px;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 8px;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--border);
            transform: translateY(-50%);
            z-index: 0;
        }

        .progress-line {
            position: absolute;
            top: 50%;
            left: 0;
            height: 2px;
            background: var(--accent);
            transform: translateY(-50%);
            transition: width 0.4s ease;
            z-index: 1;
        }

        .step-indicator {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-secondary);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .step-indicator.active {
            border-color: var(--accent);
            color: var(--accent);
        }

        .step-indicator.completed {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }

        .step-labels {
            display: flex;
            justify-content: space-between;
        }

        .step-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-align: center;
            width: 80px;
            margin-left: -22px;
        }

        .step-label:first-child {
            margin-left: 0;
            text-align: left;
        }

        .step-label:last-child {
            margin-left: 0;
            text-align: right;
        }

        .step-label.active {
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

        /* Steps Container */
        .steps-wrapper {
            position: relative;
            overflow: hidden;
        }

        .step-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .step-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: var(--text-primary);
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
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
        }

        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.6;
        }

        /* Date Select Grid */
        .date-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr 1fr;
            gap: 12px;
        }

        .date-grid select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
            cursor: pointer;
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
        }

        .password-toggle:hover {
            color: var(--text-primary);
        }

        /* Password Strength */
        .password-strength {
            margin-top: 12px;
        }

        .strength-bars {
            display: flex;
            gap: 4px;
            margin-bottom: 6px;
        }

        .strength-bar {
            height: 3px;
            flex: 1;
            background: var(--border);
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .strength-bar.weak {
            background: var(--danger);
        }

        .strength-bar.medium {
            background: #f59e0b;
        }

        .strength-bar.strong {
            background: #10b981;
        }

        .strength-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* Error Message */
        .error-msg {
            display: block;
            color: var(--danger);
            font-size: 0.8rem;
            margin-top: 6px;
        }

        /* Alert */
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        /* Buttons */
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 32px;
        }

        .btn {
            padding: 14px 24px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary {
            flex: 1;
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .login-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* Terms */
        .terms-text {
            font-size: 0.8rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-top: 16px;
        }

        .terms-text a {
            color: var(--accent);
            text-decoration: none;
        }

        /* Mobile */
        @media (max-width: 968px) {
            .register-brand {
                display: none;
            }

            .register-form-side {
                padding: 24px;
            }
        }

        @media (max-width: 480px) {
            .date-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .date-grid select:nth-child(2) {
                grid-column: span 2;
                order: -1;
            }

            .step-label {
                font-size: 0.65rem;
                width: 60px;
            }
        }
    </style>
</head>

<body>
    <div class="register-wrapper">
        <!-- Left Side - Branding -->
        <div class="register-brand">
            <div class="brand-content">
                <div class="brand-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1080"><path fill="currentColor" d="M810 1c3 8-1 13-6 18l-9 14-16 24-9 17-11 16c-4 5-6 11-9 16l-20 32-13 21-19 29-14 23-14 20-5 9c-4 7-8 15-14 22l-11 18-11 17-10 17-10 14-15 24-9 14-11 18-13 19-7 13-10 16-6 7-16 28c-3 4-7 8-9 13l-11 17-12 17-9 15-9 15-12 19-15 22-5 9-10 16-14 22-8 14-12 18-16 23-9 16-14 21-11 18-14 23-10 16-25 39-23 36-18 30-14 19-11 17-3 3-22 3-74 1c-7 0-7-1-8-7-1-8 3-13 6-19l19-29c5-7 8-15 13-21 10-11 16-25 24-37l22-33 18-27 17-28 12-16 12-20 14-21 10-15 14-21 11-17 15-24 22-36 20-26 8-16 15-21 13-22 10-15c3-4 3-8 0-12l-24-32-17-21c-7-12-15-22-24-33l-14-20-16-23-34-45-15-22-15-21-16-19-14-22-7-9-21-29-27-35-18-25-23-31-17-24c-3-4-5-7-5-12 1-7 4-10 12-10l50-1a4284 4284 0 0 0 50-2c9-2 14 3 19 11l14 19 12 17 14 19 23 34 16 22a1534 1534 0 0 1 19 25l22 33a2344 2344 0 0 0 20 27l13 17 24 34 22 32 25 34 14 21c2 2 5 1 6-2 3-9 10-17 15-24l10-16 13-21 7-13 11-15 10-17 10-16 15-24 9-12 14-24 9-13 12-19 5-9c5-6 11-12 14-19 3-8 9-15 14-23l15-24 21-34 16-22 2-7h101M301 1072h-26c-3 0-7 0-8-4 0-3 0-6 2-9l19-30 18-27 11-18 21-33 13-19 6-10 15-23 13-22 10-16 15-24 9-13 11-18 12-16 8-15 21-32 17-29 14-20 14-24 15-21 5-8c3-5 5-11 9-16 5-5 7-12 11-17l14-21 11-18 9-13 11-17 19-31 10-16 12-16 8-15 8-12 8-13 19-30 4-6 18-27 14-23 23-37 22-34 26-41 13-23 14-20 10-16 14-19 6-3 68-2 24-1c7 0 11 3 13 9l-1 7-14 20-10 17-14 20-5 8c-6 11-14 20-21 30l-18 30-19 27-17 30-11 13-5 10-11 16-9 14-15 23-10 15-18 29-10 17-15 23-23 35-13 18-10 17-15 21c-2 3-2 5 0 8l17 22 18 27 17 22 19 27 21 29 28 40 26 34 11 15 17 25 22 30 17 21 22 32 22 28 13 20c2 4-2 8-8 8h-25l-16 2-11 1-49 1c-11 1-19-3-26-12l-16-24-20-28-15-21-19-26a3322 3322 0 0 0-26-36c-7-10-15-18-20-28-6-11-15-20-21-30l-14-20-11-14-9-14-24-34c-2-3-5-2-7 1l-19 31-13 21-13 20-8 14-11 15-9 16-14 21-11 18-9 14-11 17-18 27-6 12-12 18c-4 5-6 11-9 16l-12 16-8 15-19 27-10 19-11 15-2 4c-2 5-5 7-10 7h-67"/></svg>
                    <?php echo SITE_NAME; ?>
                </div>
                <p class="brand-tagline">Sua jornada começa aqui. Crie sua conta e descubra um novo universo.</p>

                <div class="brand-features">
                    <div class="brand-feature">
                        <i class="fas fa-bolt"></i>
                        <span>Acesso rápido e seguro</span>
                    </div>
                    <div class="brand-feature">
                        <i class="fas fa-shield-alt"></i>
                        <span>Seus dados protegidos</span>
                    </div>
                    <div class="brand-feature">
                        <i class="fas fa-users"></i>
                        <span>Comunidade ativa</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Form -->
        <div class="register-form-side">
            <div class="form-container">
                <!-- Progress Bar -->
                <div class="progress-container">
                    <div class="progress-steps">
                        <div class="progress-line" id="progressLine"></div>
                        <div class="step-indicator active" data-step="1">1</div>
                        <div class="step-indicator" data-step="2">2</div>
                        <div class="step-indicator" data-step="3">3</div>
                    </div>
                    <div class="step-labels">
                        <span class="step-label active">Pessoal</span>
                        <span class="step-label">Conta</span>
                        <span class="step-label">Segurança</span>
                    </div>
                </div>

                <!-- Form Header -->
                <div class="form-header">
                    <h1 id="stepTitle">Informações Pessoais</h1>
                    <p id="stepDescription">Vamos começar com seus dados básicos</p>
                </div>

                <?php if (isset($errors['geral'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $errors['geral'] ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm" novalidate>
                    <div class="steps-wrapper">
                        <!-- Step 1: Personal Info -->
                        <div class="step-content active" data-step="1">
                            <div class="form-group">
                                <label class="form-label">Nome Completo</label>
                                <input type="text" name="nome_completo" id="nome_completo"
                                    class="form-control <?= isset($errors['nome_completo']) ? 'error' : '' ?>"
                                    value="<?= htmlspecialchars($old['nome_completo'] ?? '') ?>"
                                    placeholder="Digite seu nome completo">
                                <?php if (isset($errors['nome_completo'])): ?>
                                    <span class="error-msg"><?= $errors['nome_completo'] ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Data de Nascimento</label>
                                <div class="date-grid">
                                    <select name="dia_nascimento" id="dia_nascimento"
                                        class="form-control <?= isset($errors['data_nascimento']) ? 'error' : '' ?>">
                                        <option value="">Dia</option>
                                        <?php for ($d = 1; $d <= 31; $d++): ?>
                                            <option value="<?= $d ?>" <?= (isset($old['dia_nascimento']) && $old['dia_nascimento'] == $d) ? 'selected' : '' ?>>
                                                <?= str_pad($d, 2, '0', STR_PAD_LEFT) ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>

                                    <select name="mes_nascimento" id="mes_nascimento"
                                        class="form-control <?= isset($errors['data_nascimento']) ? 'error' : '' ?>">
                                        <option value="">Mês</option>
                                        <?php foreach ($meses as $num => $nome): ?>
                                            <option value="<?= $num ?>" <?= (isset($old['mes_nascimento']) && $old['mes_nascimento'] == $num) ? 'selected' : '' ?>>
                                                <?= $nome ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <select name="ano_nascimento" id="ano_nascimento"
                                        class="form-control <?= isset($errors['data_nascimento']) ? 'error' : '' ?>">
                                        <option value="">Ano</option>
                                        <?php foreach ($anos as $ano): ?>
                                            <option value="<?= $ano ?>" <?= (isset($old['ano_nascimento']) && $old['ano_nascimento'] == $ano) ? 'selected' : '' ?>>
                                                <?= $ano ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if (isset($errors['data_nascimento'])): ?>
                                    <span class="error-msg"><?= $errors['data_nascimento'] ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="btn-group">
                                <button type="button" class="btn btn-primary" onclick="nextStep(2)">
                                    Continuar <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Account Info -->
                        <div class="step-content" data-step="2">
                            <div class="form-group">
                                <label class="form-label">Nome de Usuário</label>
                                <input type="text" name="nome_usuario" id="nome_usuario"
                                    class="form-control <?= isset($errors['nome_usuario']) ? 'error' : '' ?>"
                                    value="<?= htmlspecialchars($old['nome_usuario'] ?? '') ?>"
                                    placeholder="Como você quer ser chamado">
                                <?php if (isset($errors['nome_usuario'])): ?>
                                    <span class="error-msg"><?= $errors['nome_usuario'] ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" id="email"
                                    class="form-control <?= isset($errors['email']) ? 'error' : '' ?>"
                                    value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                                    placeholder="seu@email.com">
                                <?php if (isset($errors['email'])): ?>
                                    <span class="error-msg"><?= $errors['email'] ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="btn-group">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(1)">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                                <button type="button" class="btn btn-primary" onclick="nextStep(3)">
                                    Continuar <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Security -->
                        <div class="step-content" data-step="3">
                            <div class="form-group">
                                <label class="form-label">Senha</label>
                                <div class="password-wrapper">
                                    <input type="password" name="senha" id="senha"
                                        class="form-control <?= isset($errors['senha']) ? 'error' : '' ?>"
                                        placeholder="Mínimo 6 caracteres"
                                        oninput="checkPasswordStrength(this.value)">
                                    <i class="fas fa-eye password-toggle" onclick="togglePass('senha')"></i>
                                </div>
                                <div class="password-strength" id="passwordStrength" style="display: none;">
                                    <div class="strength-bars">
                                        <div class="strength-bar" id="bar1"></div>
                                        <div class="strength-bar" id="bar2"></div>
                                        <div class="strength-bar" id="bar3"></div>
                                        <div class="strength-bar" id="bar4"></div>
                                    </div>
                                    <span class="strength-text" id="strengthText"></span>
                                </div>
                                <?php if (isset($errors['senha'])): ?>
                                    <span class="error-msg"><?= $errors['senha'] ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Confirmar Senha</label>
                                <div class="password-wrapper">
                                    <input type="password" name="senha_confirmar" id="senha_confirmar"
                                        class="form-control <?= isset($errors['senha_confirmar']) ? 'error' : '' ?>"
                                        placeholder="Digite a senha novamente">
                                    <i class="fas fa-eye password-toggle" onclick="togglePass('senha_confirmar')"></i>
                                </div>
                                <?php if (isset($errors['senha_confirmar'])): ?>
                                    <span class="error-msg"><?= $errors['senha_confirmar'] ?></span>
                                <?php endif; ?>
                            </div>

                            <p class="terms-text">
                                Ao criar sua conta, você concorda com nossos
                                <a href="#">Termos de Serviço</a> e
                                <a href="#">Política de Privacidade</a>.
                            </p>

                            <div class="btn-group">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(2)">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    Criar Conta <i class="fas fa-check" style="margin-left: 8px;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="login-link">
                    Já tem uma conta? <a href="login.php">Fazer Login</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;

        const stepTitles = {
            1: {
                title: 'Informações Pessoais',
                desc: 'Vamos começar com seus dados básicos'
            },
            2: {
                title: 'Configurar Conta',
                desc: 'Escolha seu nome de usuário e email'
            },
            3: {
                title: 'Criar Senha',
                desc: 'Proteja sua conta com uma senha forte'
            }
        };

        function updateProgress(step) {
            // Update indicators
            document.querySelectorAll('.step-indicator').forEach((ind, idx) => {
                ind.classList.remove('active', 'completed');
                if (idx + 1 < step) {
                    ind.classList.add('completed');
                    ind.innerHTML = '<i class="fas fa-check"></i>';
                } else if (idx + 1 === step) {
                    ind.classList.add('active');
                    ind.textContent = idx + 1;
                } else {
                    ind.textContent = idx + 1;
                }
            });

            // Update labels
            document.querySelectorAll('.step-label').forEach((label, idx) => {
                label.classList.toggle('active', idx + 1 === step);
            });

            // Update progress line
            const progressLine = document.getElementById('progressLine');
            const percentage = ((step - 1) / 2) * 100;
            progressLine.style.width = percentage + '%';

            // Update header
            document.getElementById('stepTitle').textContent = stepTitles[step].title;
            document.getElementById('stepDescription').textContent = stepTitles[step].desc;
        }

        function showStep(step) {
            document.querySelectorAll('.step-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelector(`.step-content[data-step="${step}"]`).classList.add('active');
            updateProgress(step);
            currentStep = step;
        }

        function validateStep(step) {
            let isValid = true;

            if (step === 1) {
                const nome = document.getElementById('nome_completo').value.trim();
                const dia = document.getElementById('dia_nascimento').value;
                const mes = document.getElementById('mes_nascimento').value;
                const ano = document.getElementById('ano_nascimento').value;

                if (!nome) {
                    markError('nome_completo', 'Nome completo é obrigatório');
                    isValid = false;
                } else {
                    clearError('nome_completo');
                }

                if (!dia || !mes || !ano) {
                    markError('dia_nascimento', 'Data de nascimento completa é obrigatória');
                    isValid = false;
                } else {
                    clearError('dia_nascimento');
                }
            }

            if (step === 2) {
                const usuario = document.getElementById('nome_usuario').value.trim();
                const email = document.getElementById('email').value.trim();

                if (!usuario) {
                    markError('nome_usuario', 'Nome de usuário é obrigatório');
                    isValid = false;
                } else if (usuario.length < 3) {
                    markError('nome_usuario', 'Mínimo 3 caracteres');
                    isValid = false;
                } else {
                    clearError('nome_usuario');
                }

                if (!email) {
                    markError('email', 'Email é obrigatório');
                    isValid = false;
                } else if (!isValidEmail(email)) {
                    markError('email', 'Email inválido');
                    isValid = false;
                } else {
                    clearError('email');
                }
            }

            return isValid;
        }

        function markError(fieldId, message) {
            const field = document.getElementById(fieldId);
            field.classList.add('error');

            // Remove existing error message
            const existingError = field.parentElement.querySelector('.error-msg');
            if (existingError) existingError.remove();

            // Add new error message
            const errorSpan = document.createElement('span');
            errorSpan.className = 'error-msg';
            errorSpan.textContent = message;

            if (field.closest('.date-grid')) {
                field.closest('.form-group').appendChild(errorSpan);
            } else {
                field.parentElement.appendChild(errorSpan);
            }
        }

        function clearError(fieldId) {
            const field = document.getElementById(fieldId);
            field.classList.remove('error');

            const errorMsg = field.parentElement.querySelector('.error-msg');
            if (errorMsg) errorMsg.remove();

            if (field.closest('.date-grid')) {
                const gridError = field.closest('.form-group').querySelector('.error-msg');
                if (gridError) gridError.remove();
            }
        }

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function nextStep(step) {
            if (validateStep(currentStep)) {
                showStep(step);
            }
        }

        function prevStep(step) {
            showStep(step);
        }

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

        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('passwordStrength');
            const bars = [
                document.getElementById('bar1'),
                document.getElementById('bar2'),
                document.getElementById('bar3'),
                document.getElementById('bar4')
            ];
            const strengthText = document.getElementById('strengthText');

            if (password.length === 0) {
                strengthDiv.style.display = 'none';
                return;
            }

            strengthDiv.style.display = 'block';

            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            // Reset bars
            bars.forEach(bar => {
                bar.className = 'strength-bar';
            });

            const levels = ['', 'weak', 'weak', 'medium', 'strong', 'strong'];
            const texts = ['', 'Muito fraca', 'Fraca', 'Média', 'Forte', 'Muito forte'];

            for (let i = 0; i < Math.min(strength, 4); i++) {
                bars[i].classList.add(levels[strength]);
            }

            strengthText.textContent = texts[strength];
        }

        // Check for PHP errors and go to the correct step
        <?php if (!empty($errors)): ?>
            <?php if (isset($errors['nome_completo']) || isset($errors['data_nascimento'])): ?>
                showStep(1);
            <?php elseif (isset($errors['nome_usuario']) || isset($errors['email'])): ?>
                showStep(2);
            <?php elseif (isset($errors['senha']) || isset($errors['senha_confirmar'])): ?>
                showStep(3);
            <?php endif; ?>
        <?php endif; ?>
    </script>
</body>

</html>