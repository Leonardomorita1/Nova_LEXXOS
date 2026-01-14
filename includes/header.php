<?php
// includes/header.php
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
}

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;
$user_type = $_SESSION['user_type'] ?? 'cliente';
$user_theme = $_SESSION['user_theme'] ?? 'dark';
$user_avatar = $_SESSION['user_avatar'] ?? null;

// Buscar avatar atualizado do banco se logado
if ($user_id && !$user_avatar) {
    $stmt = $pdo->prepare("SELECT avatar_url FROM usuario WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    $user_avatar = $user_data['avatar_url'] ?? null;
    $_SESSION['user_avatar'] = $user_avatar;
}

// Avatar default
$default_avatar = SITE_URL . '/assets/images/default-avatar.png';
$avatar_url = $user_avatar ? $user_avatar : $default_avatar;

// Buscar contagem do carrinho
$cart_count = 0;
$wishlist_count = 0;
$is_developer = false; // Nova variável para verificar se é desenvolvedor

if ($user_id) {
    $cart_count = getCartCount($user_id, $pdo);

    // Contar wishlist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lista_desejos WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_count = $stmt->fetchColumn();

    // Verificar se usuário é desenvolvedor (baseado na tabela desenvolvedor)
    $stmt = $pdo->prepare("SELECT id, status FROM desenvolvedor WHERE usuario_id = ? AND status = 'ativo'");
    $stmt->execute([$user_id]);
    $dev_data = $stmt->fetch();
    $is_developer = ($dev_data !== false);
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?php echo $user_theme; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="<?php echo $page_description ?? 'A maior plataforma de jogos indies do Brasil'; ?>">
    <title><?php echo $page_title ?? 'Lexxos - Jogos Indies'; ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/game-card-styles.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/navigation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/images/favicon.png">

    <!-- Theme Script -->
    <script>
        const localTheme = localStorage.getItem('theme');
        if (localTheme) {
            document.documentElement.setAttribute('data-theme', localTheme);
        }
    </script>
</head>

<body>
    <!-- ============================================
         NAVBAR DESKTOP
         ============================================ -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-content">
                <!-- Logo -->
                <a href="<?php echo SITE_URL; ?>/pages/home.php" class="navbar-brand">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1080">
                        <path fill="currentColor" d="M810 1c3 8-1 13-6 18l-9 14-16 24-9 17-11 16c-4 5-6 11-9 16l-20 32-13 21-19 29-14 23-14 20-5 9c-4 7-8 15-14 22l-11 18-11 17-10 17-10 14-15 24-9 14-11 18-13 19-7 13-10 16-6 7-16 28c-3 4-7 8-9 13l-11 17-12 17-9 15-9 15-12 19-15 22-5 9-10 16-14 22-8 14-12 18-16 23-9 16-14 21-11 18-14 23-10 16-25 39-23 36-18 30-14 19-11 17-3 3-22 3-74 1c-7 0-7-1-8-7-1-8 3-13 6-19l19-29c5-7 8-15 13-21 10-11 16-25 24-37l22-33 18-27 17-28 12-16 12-20 14-21 10-15 14-21 11-17 15-24 22-36 20-26 8-16 15-21 13-22 10-15c3-4 3-8 0-12l-24-32-17-21c-7-12-15-22-24-33l-14-20-16-23-34-45-15-22-15-21-16-19-14-22-7-9-21-29-27-35-18-25-23-31-17-24c-3-4-5-7-5-12 1-7 4-10 12-10l50-1a4284 4284 0 0 0 50-2c9-2 14 3 19 11l14 19 12 17 14 19 23 34 16 22a1534 1534 0 0 1 19 25l22 33a2344 2344 0 0 0 20 27l13 17 24 34 22 32 25 34 14 21c2 2 5 1 6-2 3-9 10-17 15-24l10-16 13-21 7-13 11-15 10-17 10-16 15-24 9-12 14-24 9-13 12-19 5-9c5-6 11-12 14-19 3-8 9-15 14-23l15-24 21-34 16-22 2-7h101M301 1072h-26c-3 0-7 0-8-4 0-3 0-6 2-9l19-30 18-27 11-18 21-33 13-19 6-10 15-23 13-22 10-16 15-24 9-13 11-18 12-16 8-15 21-32 17-29 14-20 14-24 15-21 5-8c3-5 5-11 9-16 5-5 7-12 11-17l14-21 11-18 9-13 11-17 19-31 10-16 12-16 8-15 8-12 8-13 19-30 4-6 18-27 14-23 23-37 22-34 26-41 13-23 14-20 10-16 14-19 6-3 68-2 24-1c7 0 11 3 13 9l-1 7-14 20-10 17-14 20-5 8c-6 11-14 20-21 30l-18 30-19 27-17 30-11 13-5 10-11 16-9 14-15 23-10 15-18 29-10 17-15 23-23 35-13 18-10 17-15 21c-2 3-2 5 0 8l17 22 18 27 17 22 19 27 21 29 28 40 26 34 11 15 17 25 22 30 17 21 22 32 22 28 13 20c2 4-2 8-8 8h-25l-16 2-11 1-49 1c-11 1-19-3-26-12l-16-24-20-28-15-21-19-26a3322 3322 0 0 0-26-36c-7-10-15-18-20-28-6-11-15-20-21-30l-14-20-11-14-9-14-24-34c-2-3-5-2-7 1l-19 31-13 21-13 20-8 14-11 15-9 16-14 21-11 18-9 14-11 17-18 27-6 12-12 18c-4 5-6 11-9 16l-12 16-8 15-19 27-10 19-11 15-2 4c-2 5-5 7-10 7h-67" />
                    </svg>
                    <span><?php echo SITE_NAME; ?></span>
                </a>

                <!-- Search Desktop -->
                <div class="navbar-search">
                    <form action="<?php echo SITE_URL; ?>/pages/busca.php" method="GET" class="search-form">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" placeholder="Buscar jogos..."
                            value="<?php echo isset($_GET['q']) ? sanitize($_GET['q']) : ''; ?>">
                    </form>
                </div>

                <!-- Desktop Actions -->
                <div class="navbar-actions">
                    <?php if ($user_id): ?>
                        <!-- Wishlist -->
                        <a href="<?php echo SITE_URL; ?>/user/lista-desejos.php" class="nav-action" title="Lista de Desejos">
                            <i class="fas fa-heart"></i>
                            <?php if ($wishlist_count > 0): ?>
                                <span class="action-badge"><?php echo $wishlist_count; ?></span>
                            <?php endif; ?>
                        </a>

                        <!-- Cart -->
                        <a href="<?php echo SITE_URL; ?>/user/carrinho.php" class="nav-action" title="Carrinho">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cart_count > 0): ?>
                                <span class="action-badge"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>

                        <!-- User Profile Dropdown -->
                        <div class="user-dropdown">
                            <button class="user-dropdown-toggle" onclick="toggleUserDropdown()">
                                <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="user-avatar"
                                    onerror="this.src='<?php echo $default_avatar; ?>'">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="user-dropdown-menu" id="userDropdownMenu">
                                <!-- User Info -->
                                <div class="dropdown-user-info">
                                    <img src="<?php echo $avatar_url; ?>" alt="Avatar"
                                        onerror="this.src='<?php echo $default_avatar; ?>'">
                                    <div>
                                        <strong><?php echo sanitize($user_name); ?></strong>
                                        <span><?php echo ucfirst($user_type); ?></span>
                                    </div>
                                </div>

                                <div class="dropdown-divider"></div>

                                <?php if ($user_type == 'admin'): ?>
                                    <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="dropdown-item">
                                        <i class="fas fa-shield-alt"></i> Painel Admin
                                    </a>
                                <?php endif; ?>

                                <?php if ($is_developer): ?>
                                    <a href="<?php echo SITE_URL; ?>/developer/dashboard.php" class="dropdown-item">
                                        <i class="fas fa-code"></i> Dashboard Dev
                                    </a>
                                <?php endif; ?>

                                <a href="<?php echo SITE_URL; ?>/user/biblioteca.php" class="dropdown-item">
                                    <i class="fas fa-gamepad"></i> Minha Biblioteca
                                </a>
                                <a href="<?php echo SITE_URL; ?>/user/pedidos.php" class="dropdown-item">
                                    <i class="fas fa-receipt"></i> Meus Pedidos
                                </a>

                                <div class="dropdown-divider"></div>

                                <a href="<?php echo SITE_URL; ?>/user/perfil.php" class="dropdown-item">
                                    <i class="fas fa-user-cog"></i> Configurações
                                </a>
                                <button class="dropdown-item" onclick="toggleTheme()">
                                    <i class="fas fa-moon" id="theme-icon"></i>
                                    <span id="theme-text">Modo Claro</span>
                                </button>

                                <div class="dropdown-divider"></div>

                                <!-- Novos botões -->
                                <?php if (!$is_developer): ?>
                                    <a href="<?php echo SITE_URL; ?>/user/seja-dev.php" class="dropdown-item">
                                        <i class="fas fa-rocket"></i> Seja um Dev
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>/pages/suporte.php" class="dropdown-item">
                                    <i class="fas fa-headset"></i> Suporte
                                </a>

                                <div class="dropdown-divider"></div>

                                <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="dropdown-item dropdown-item-danger">
                                    <i class="fas fa-sign-out-alt"></i> Sair
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-outline btn-sm">Entrar</a>
                        <a href="<?php echo SITE_URL; ?>/auth/register.php" class="btn btn-primary btn-sm">Criar Conta</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- ============================================
         MOBILE HEADER (Simplified)
         ============================================ -->
    <header class="mobile-header">
        <a href="<?php echo SITE_URL; ?>/pages/home.php" class="mobile-brand">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1080">
                <path fill="currentColor" d="M810 1c3 8-1 13-6 18l-9 14-16 24-9 17-11 16c-4 5-6 11-9 16l-20 32-13 21-19 29-14 23-14 20-5 9c-4 7-8 15-14 22l-11 18-11 17-10 17-10 14-15 24-9 14-11 18-13 19-7 13-10 16-6 7-16 28c-3 4-7 8-9 13l-11 17-12 17-9 15-9 15-12 19-15 22-5 9-10 16-14 22-8 14-12 18-16 23-9 16-14 21-11 18-14 23-10 16-25 39-23 36-18 30-14 19-11 17-3 3-22 3-74 1c-7 0-7-1-8-7-1-8 3-13 6-19l19-29c5-7 8-15 13-21 10-11 16-25 24-37l22-33 18-27 17-28 12-16 12-20 14-21 10-15 14-21 11-17 15-24 22-36 20-26 8-16 15-21 13-22 10-15c3-4 3-8 0-12l-24-32-17-21c-7-12-15-22-24-33l-14-20-16-23-34-45-15-22-15-21-16-19-14-22-7-9-21-29-27-35-18-25-23-31-17-24c-3-4-5-7-5-12 1-7 4-10 12-10l50-1a4284 4284 0 0 0 50-2c9-2 14 3 19 11l14 19 12 17 14 19 23 34 16 22a1534 1534 0 0 1 19 25l22 33a2344 2344 0 0 0 20 27l13 17 24 34 22 32 25 34 14 21c2 2 5 1 6-2 3-9 10-17 15-24l10-16 13-21 7-13 11-15 10-17 10-16 15-24 9-12 14-24 9-13 12-19 5-9c5-6 11-12 14-19 3-8 9-15 14-23l15-24 21-34 16-22 2-7h101M301 1072h-26c-3 0-7 0-8-4 0-3 0-6 2-9l19-30 18-27 11-18 21-33 13-19 6-10 15-23 13-22 10-16 15-24 9-13 11-18 12-16 8-15 21-32 17-29 14-20 14-24 15-21 5-8c3-5 5-11 9-16 5-5 7-12 11-17l14-21 11-18 9-13 11-17 19-31 10-16 12-16 8-15 8-12 8-13 19-30 4-6 18-27 14-23 23-37 22-34 26-41 13-23 14-20 10-16 14-19 6-3 68-2 24-1c7 0 11 3 13 9l-1 7-14 20-10 17-14 20-5 8c-6 11-14 20-21 30l-18 30-19 27-17 30-11 13-5 10-11 16-9 14-15 23-10 15-18 29-10 17-15 23-23 35-13 18-10 17-15 21c-2 3-2 5 0 8l17 22 18 27 17 22 19 27 21 29 28 40 26 34 11 15 17 25 22 30 17 21 22 32 22 28 13 20c2 4-2 8-8 8h-25l-16 2-11 1-49 1c-11 1-19-3-26-12l-16-24-20-28-15-21-19-26a3322 3322 0 0 0-26-36c-7-10-15-18-20-28-6-11-15-20-21-30l-14-20-11-14-9-14-24-34c-2-3-5-2-7 1l-19 31-13 21-13 20-8 14-11 15-9 16-14 21-11 18-9 14-11 17-18 27-6 12-12 18c-4 5-6 11-9 16l-12 16-8 15-19 27-10 19-11 15-2 4c-2 5-5 7-10 7h-67" />
            </svg>
        </a>

        <button class="mobile-search-toggle" onclick="openSearchPanel()">
            <i class="fas fa-search"></i>
        </button>
    </header>

    <!-- ============================================
         MOBILE BOTTOM NAVIGATION
         ============================================ -->
    <nav class="bottom-nav">
        <a href="<?php echo SITE_URL; ?>/pages/home.php" class="bottom-nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'home.php') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>

        <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="bottom-nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'busca.php') ? 'active' : ''; ?>">
            <i class="fas fa-compass"></i>
            <span>Explorar</span>
        </a>

        <?php if ($user_id): ?>
            <a href="<?php echo SITE_URL; ?>/user/biblioteca.php" class="bottom-nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'biblioteca.php') ? 'active' : ''; ?>">
                <i class="fas fa-gamepad"></i>
                <span>Biblioteca</span>
            </a>

            <a href="<?php echo SITE_URL; ?>/user/carrinho.php" class="bottom-nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'carrinho.php') ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Carrinho</span>
                <?php if ($cart_count > 0): ?>
                    <span class="bottom-nav-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>

            <button class="bottom-nav-item" onclick="openBottomSheet()">
                <img src="<?php echo $avatar_url; ?>" alt="Menu" class="bottom-nav-avatar"
                    onerror="this.src='<?php echo $default_avatar; ?>'">
                <span>Menu</span>
            </button>
        <?php else: ?>
            <a href="<?php echo SITE_URL; ?>/auth/login.php" class="bottom-nav-item">
                <i class="fas fa-sign-in-alt"></i>
                <span>Entrar</span>
            </a>

            <button class="bottom-nav-item" onclick="openBottomSheet()">
                <i class="fas fa-bars"></i>
                <span>Menu</span>
            </button>
        <?php endif; ?>
    </nav>

    <!-- ============================================
         BOTTOM SHEET (Mobile Menu Panel)
         ============================================ -->
    <div class="bottom-sheet-overlay" id="bottomSheetOverlay" onclick="closeBottomSheet()"></div>
    <div class="bottom-sheet" id="bottomSheet">
        <div class="bottom-sheet-handle" onclick="closeBottomSheet()">
            <span></span>
        </div>

        <div class="bottom-sheet-content">
            <?php if ($user_id): ?>
                <!-- User Header -->
                <div class="sheet-user-header">
                    <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="sheet-avatar"
                        onerror="this.src='<?php echo $default_avatar; ?>'">
                    <div class="sheet-user-info">
                        <strong><?php echo sanitize($user_name); ?></strong>
                        <span><?php echo ucfirst($user_type); ?></span>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/user/perfil.php" class="sheet-edit-btn">
                        <i class="fas fa-pen"></i>
                    </a>
                </div>

                <!-- Quick Stats -->
                <div class="sheet-stats">
                    <a href="<?php echo SITE_URL; ?>/user/biblioteca.php" class="sheet-stat">
                        <i class="fas fa-gamepad"></i>
                        <span>Biblioteca</span>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/user/lista-desejos.php" class="sheet-stat">
                        <i class="fas fa-heart"></i>
                        <span>Desejos</span>
                        <?php if ($wishlist_count > 0): ?>
                            <span class="stat-badge"><?php echo $wishlist_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/user/pedidos.php" class="sheet-stat">
                        <i class="fas fa-receipt"></i>
                        <span>Pedidos</span>
                    </a>
                </div>

                <div class="sheet-divider"></div>

                <!-- Menu Items -->
                <div class="sheet-menu">
                    <?php if ($user_type == 'admin'): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="sheet-menu-item sheet-menu-highlight">
                            <i class="fas fa-shield-alt"></i>
                            <span>Painel Admin</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>

                    <?php if ($is_developer): ?>
                        <a href="<?php echo SITE_URL; ?>/developer/dashboard.php" class="sheet-menu-item sheet-menu-highlight">
                            <i class="fas fa-code"></i>
                            <span>Dashboard Dev</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>

                    <a href="<?php echo SITE_URL; ?>/user/perfil.php" class="sheet-menu-item">
                        <i class="fas fa-user-cog"></i>
                        <span>Configurações da Conta</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>

                    <button class="sheet-menu-item" onclick="toggleTheme(); updateThemeUI();">
                        <i class="fas fa-moon" id="mobile-theme-icon"></i>
                        <span id="mobile-theme-text">Modo Claro</span>
                        <div class="theme-toggle-switch">
                            <span class="toggle-track">
                                <span class="toggle-thumb"></span>
                            </span>
                        </div>
                    </button>

                    <div class="sheet-divider"></div>

                    <!-- Novos botões: Seja Dev e Suporte -->
                    <?php if (!$is_developer): ?>
                        <a href="<?php echo SITE_URL; ?>/user/seja-dev.php" class="sheet-menu-item">
                            <i class="fas fa-rocket"></i>
                            <span>Seja um Dev</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>

                    <a href="<?php echo SITE_URL; ?>/pages/suporte.php" class="sheet-menu-item">
                        <i class="fas fa-headset"></i>
                        <span>Suporte</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>

                <div class="sheet-divider"></div>

                <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="sheet-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Sair da Conta
                </a>
            <?php else: ?>
                <!-- Guest View -->
                <div class="sheet-guest">
                    <div class="sheet-guest-icon">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <h3>Bem-vindo ao <?php echo SITE_NAME; ?></h3>
                    <p>Entre para acessar sua biblioteca, lista de desejos e muito mais!</p>
                    <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Entrar
                    </a>
                    <a href="<?php echo SITE_URL; ?>/auth/register.php" class="btn btn-outline btn-block">
                        Criar Conta Grátis
                    </a>
                </div>

                <div class="sheet-divider"></div>

                <!-- Links para visitantes -->
                <div class="sheet-menu">
                    <a href="<?php echo SITE_URL; ?>/user/seja-dev.php" class="sheet-menu-item">
                        <i class="fas fa-rocket"></i>
                        <span>Seja um Dev</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>

                    <a href="<?php echo SITE_URL; ?>/pages/suporte.php" class="sheet-menu-item">
                        <i class="fas fa-headset"></i>
                        <span>Suporte</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>

                <div class="sheet-divider"></div>

                <button class="sheet-menu-item" onclick="toggleTheme(); updateThemeUI();">
                    <i class="fas fa-moon" id="mobile-theme-icon-guest"></i>
                    <span id="mobile-theme-text-guest">Modo Claro</span>
                    <div class="theme-toggle-switch">
                        <span class="toggle-track">
                            <span class="toggle-thumb"></span>
                        </span>
                    </div>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================
         SEARCH PANEL (Mobile)
         ============================================ -->
    <div class="search-panel" id="searchPanel">
        <div class="search-panel-header">
            <form action="<?php echo SITE_URL; ?>/pages/busca.php" method="GET" class="search-panel-form">
                <button type="button" class="search-back" onclick="closeSearchPanel()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <input type="text" name="q" placeholder="Buscar jogos..." autofocus
                    value="<?php echo isset($_GET['q']) ? sanitize($_GET['q']) : ''; ?>">
                <button type="submit" class="search-submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- ============================================
         MAIN CONTENT
         ============================================ -->
    <main class="main-content">