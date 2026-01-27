<?php
// developer/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);

// Contadores para badges (se necessário)
$pending_reviews = 0;

try {
    // Conta avaliações não respondidas dos jogos do desenvolvedor
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM avaliacoes a
        INNER JOIN jogos j ON a.jogo_id = j.id
        WHERE j.desenvolvedor_id = ? AND a.resposta_dev IS NULL
    ");
    $stmt->execute([$dev['id']]);
    $pending_reviews = $stmt->fetchColumn();
} catch(Exception $e) {}
?>

<style>
/* =============================================
   DEV LAYOUT - FULL WIDTH + FIXED SIDEBAR
   ============================================= */
body.dev-body {
    margin: 0;
    padding: 0;
    overflow-x: hidden;
}

.dev-layout {
    display: flex;
    min-height: 100vh;
    width: 100%;
}

/* =============================================
   SIDEBAR - FIXED POSITION
   ============================================= */
.dev-sidebar {
    width: 250px;
    min-width: 250px;
    background: var(--bg-secondary);
    border-right: 1px solid var(--border);
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    display: flex;
    flex-direction: column;
    z-index: 100000;
}

.dev-sidebar-spacer {
    width: 250px;
    min-width: 250px;
    flex-shrink: 0;
}

/* ===== SIDEBAR HEADER ===== */
.dev-sidebar-header {
    padding: 20px 16px;
    border-bottom: 1px solid var(--border);
    
}

.dev-sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.dev-sidebar-logo {
    width: 42px;
    height: 42px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: white;
    overflow: hidden;
}

.dev-sidebar-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 10px;
}

.dev-sidebar-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}

.dev-sidebar-subtitle {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.8);
    margin-top: 2px;
}

.dev-sidebar-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6rem;
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 2px 8px;
    border-radius: 20px;
    margin-top: 4px;
}

.dev-sidebar-badge.verified {
    background: rgba(46, 204, 113, 0.4);
}

/* ===== SCROLLABLE NAV ===== */
.dev-sidebar-scroll {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 12px 0;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.dev-sidebar-scroll::-webkit-scrollbar {
    display: none;
}

/* ===== NAVIGATION ===== */
.dev-nav-group {
    margin-bottom: 6px;
}

.dev-nav-label {
    padding: 10px 16px 6px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-secondary);
    opacity: 0.5;
}

.dev-nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 16px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.15s ease;
    border-left: 3px solid transparent;
}

.dev-nav-link i {
    width: 18px;
    text-align: center;
    font-size: 0.95rem;
    opacity: 0.6;
}

.dev-nav-link:hover {
    background: rgba(255,255,255,0.03);
    color: var(--text-primary);
}

.dev-nav-link:hover i {
    opacity: 1;
}

.dev-nav-link.active {
    background: rgba(76, 139, 245, 0.08);
    color: var(--accent);
    border-left-color: var(--accent);
}

.dev-nav-link.active i {
    opacity: 1;
    color: var(--accent);
}

.dev-nav-badge {
    margin-left: auto;
    background: var(--accent);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 8px;
    min-width: 16px;
    text-align: center;
}

.dev-nav-badge.warning {
    background: #e67e22;
}

.dev-nav-badge.danger {
    background: #e74c3c;
}

/* ===== SIDEBAR FOOTER ===== */
.dev-sidebar-footer {
    padding: 12px;
    border-top: 1px solid var(--border);
}

.dev-sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: var(--bg-primary);
    border-radius: 8px;
}

.dev-user-avatar {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--accent), #667eea);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.85rem;
    overflow: hidden;
}

.dev-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px;
}

.dev-user-info {
    flex: 1;
    min-width: 0;
}

.dev-user-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dev-user-role {
    font-size: 0.65rem;
    color: var(--text-secondary);
}

.dev-btn-logout {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    text-decoration: none;
}

.dev-btn-logout:hover {
    background: rgba(231, 76, 60, 0.1);
    border-color: #e74c3c;
    color: #e74c3c;
}

.dev-btn-view-site {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 10px;
    margin-top: 10px;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.15s;
}

.dev-btn-view-site:hover {
    background: var(--bg-primary);
    color: var(--text-primary);
    border-color: var(--accent);
}

/* =============================================
   DEV CONTENT AREA - APROVEITANDO ESPAÇO
   ============================================= */
.dev-content {
    flex: 1;
    min-width: 0;
    padding: 24px 30px;
    background: var(--bg-primary);
}

.container {
    max-width: none;
    width: 100%;
    padding-left: 0;
    padding-right: 0;
}

/* =============================================
   MOBILE - BOTTOM NAVIGATION APP STYLE
   ============================================= */
.dev-mobile-bottom-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--bg-secondary);
    border-top: 1px solid var(--border);
    z-index: 1000;
    padding-bottom: env(safe-area-inset-bottom);
}

.dev-bottom-nav-container {
    display: flex;
    justify-content: space-around;
    align-items: center;
    height: 60px;
    max-width: 100%;
}

.dev-bottom-nav-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 8px 4px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.65rem;
    font-weight: 500;
    transition: color 0.15s;
    position: relative;
}

.dev-bottom-nav-item i {
    font-size: 1.2rem;
    opacity: 0.7;
}

.dev-bottom-nav-item.active {
    color: var(--accent);
}

.dev-bottom-nav-item.active i {
    opacity: 1;
}

.dev-bottom-nav-item .dev-nav-badge {
    position: absolute;
    top: 4px;
    right: 50%;
    transform: translateX(14px);
    font-size: 0.55rem;
    padding: 1px 4px;
    min-width: 14px;
}

.dev-bottom-nav-menu-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 8px 4px;
    color: var(--text-secondary);
    background: none;
    border: none;
    font-size: 0.65rem;
    font-weight: 500;
    cursor: pointer;
}

.dev-bottom-nav-menu-btn i {
    font-size: 1.2rem;
    opacity: 0.7;
}

/* ===== MOBILE FULL MENU ===== */
.dev-mobile-menu-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(4px);
    z-index: 1001;
    opacity: 0;
    transition: opacity 0.3s;
}

.dev-mobile-menu-overlay.open {
    display: block;
    opacity: 1;
}

.dev-mobile-menu-panel {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--bg-secondary);
    border-radius: 20px 20px 0 0;
    z-index: 1002;
    transform: translateY(100%);
    transition: transform 0.3s ease;
    max-height: 85vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.dev-mobile-menu-panel.open {
    transform: translateY(0);
}

.dev-mobile-menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
}

.dev-mobile-menu-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
}

.dev-mobile-menu-close {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.dev-mobile-menu-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 12px 0;
    padding-bottom: calc(12px + env(safe-area-inset-bottom));
}

.dev-mobile-menu-group {
    padding: 0 16px;
    margin-bottom: 16px;
}

.dev-mobile-menu-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-secondary);
    opacity: 0.5;
    margin-bottom: 8px;
    padding-left: 4px;
}

.dev-mobile-menu-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.dev-mobile-menu-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 16px 8px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.7rem;
    font-weight: 500;
    transition: all 0.15s;
    position: relative;
}

.dev-mobile-menu-item i {
    font-size: 1.3rem;
    opacity: 0.7;
}

.dev-mobile-menu-item.active {
    background: rgba(76, 139, 245, 0.1);
    border-color: var(--accent);
    color: var(--accent);
}

.dev-mobile-menu-item.active i {
    opacity: 1;
}

.dev-mobile-menu-item:hover {
    border-color: var(--accent);
}

.dev-mobile-menu-item .dev-menu-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #e67e22;
    color: white;
    font-size: 0.55rem;
    padding: 2px 5px;
    border-radius: 6px;
    font-weight: 700;
}

/* Mobile User Section */
.dev-mobile-user-section {
    padding: 16px;
    border-top: 1px solid var(--border);
    background: var(--bg-primary);
}

.dev-mobile-user-card {
    display: flex;
    align-items: center;
    gap: 12px;
}

.dev-mobile-user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--accent), #667eea);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    overflow: hidden;
}

.dev-mobile-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 12px;
}

.dev-mobile-user-info {
    flex: 1;
}

.dev-mobile-user-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
}

.dev-mobile-user-role {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.dev-mobile-user-actions {
    display: flex;
    gap: 8px;
}

.dev-mobile-action-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.15s;
}

.dev-mobile-action-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.dev-mobile-action-btn.logout:hover {
    border-color: #e74c3c;
    color: #e74c3c;
}

/* =============================================
   RESPONSIVE
   ============================================= */
@media (max-width: 1024px) {
    .dev-sidebar,
    .dev-sidebar-spacer {
        display: none;
    }

    .dev-mobile-bottom-nav {
        display: block;
    }

    .dev-content {
        padding: 16px;
        padding-bottom: 80px;
    }

    .dev-layout {
        flex-direction: column;
    }
}

@media (max-width: 768px) {
    .dev-content {
        padding: 16px;
        padding-bottom: 80px;
    }
}

@media (max-width: 480px) {
    .dev-content {
        padding: 12px;
        padding-bottom: 80px;
    }

    .dev-mobile-menu-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
    }

    .dev-mobile-menu-item {
        padding: 14px 6px;
        font-size: 0.65rem;
    }

    .dev-mobile-menu-item i {
        font-size: 1.2rem;
    }
}
</style>

<!-- ==================== DESKTOP SIDEBAR ==================== -->
<div class="dev-sidebar-spacer"></div>

<aside class="dev-sidebar">
    
    <!-- Header -->
    <div class="dev-sidebar-header">
        <div class="dev-sidebar-brand">
            <div class="dev-sidebar-logo">
                <?php if (!empty($dev['logo_url'])): ?>
                    <img src="<?php echo $dev['logo_url']; ?>" alt="Logo">
                <?php else: ?>
                    <i class="fas fa-code"></i>
                <?php endif; ?>
            </div>
            <div>
                <div class="dev-sidebar-title"><?php echo sanitize($dev['nome_estudio']); ?></div>
                <div class="dev-sidebar-subtitle">Painel Dev</div>
                <?php if ($dev['verificado']): ?>
                    <span class="dev-sidebar-badge verified">
                        <i class="fas fa-check-circle"></i> Verificado
                    </span>
                <?php else: ?>
                    <span class="dev-sidebar-badge">
                        <i class="fas fa-clock"></i> <?php echo ucfirst($dev['status']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Navigation Scroll -->
    <div class="dev-sidebar-scroll">
        
        <!-- Principal -->
        <div class="dev-nav-group">
            <div class="dev-nav-label">Principal</div>
            <a href="<?php echo SITE_URL; ?>/developer/dashboard.php" class="dev-nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?php echo SITE_URL; ?>/developer/jogos.php" class="dev-nav-link <?php echo $current_page == 'jogos.php' ? 'active' : ''; ?>">
                <i class="fas fa-gamepad"></i>
                <span>Meus Jogos</span>
            </a>
            <a href="<?php echo SITE_URL; ?>/developer/publicar-jogo.php" class="dev-nav-link <?php echo $current_page == 'publicar-jogo.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Publicar Jogo</span>
            </a>
        </div>

        <!-- Marketing -->
        <div class="dev-nav-group">
            <div class="dev-nav-label">Marketing</div>
            <a href="<?php echo SITE_URL; ?>/developer/promocoes.php" class="dev-nav-link <?php echo $current_page == 'promocoes.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span>Promoções e Cupons</span>
            </a>
        </div>

        <!-- Financeiro -->
        <div class="dev-nav-group">
            <div class="dev-nav-label">Financeiro</div>
            <a href="<?php echo SITE_URL; ?>/developer/vendas.php" class="dev-nav-link <?php echo $current_page == 'vendas.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Relatório de Vendas</span>
            </a>
            <a href="<?php echo SITE_URL; ?>/developer/saldo.php" class="dev-nav-link <?php echo $current_page == 'saldo.php' ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i>
                <span>Saldo e Saques</span>
            </a>
        </div>

        <!-- Comunidade -->
        <div class="dev-nav-group">
            <div class="dev-nav-label">Comunidade</div>
            <a href="<?php echo SITE_URL; ?>/developer/avaliacoes.php" class="dev-nav-link <?php echo $current_page == 'avaliacoes.php' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i>
                <span>Avaliações</span>
                <?php if ($pending_reviews > 0): ?>
                    <span class="dev-nav-badge warning"><?php echo $pending_reviews; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Configurações -->
        <div class="dev-nav-group">
            <div class="dev-nav-label">Configurações</div>
            <a href="<?php echo SITE_URL; ?>/developer/perfil.php" class="dev-nav-link <?php echo $current_page == 'perfil.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-edit"></i>
                <span>Editar Perfil</span>
            </a>
            <a href="<?php echo SITE_URL; ?>/pages/desenvolvedor.php?slug=<?php echo $dev['slug']; ?>" class="dev-nav-link" target="_blank">
                <i class="fas fa-external-link-alt"></i>
                <span>Ver Página Pública</span>
            </a>
        </div>

    </div>

    <!-- Footer -->
    <div class="dev-sidebar-footer">
        <div class="dev-sidebar-user">
            <div class="dev-user-avatar">
                <?php if (!empty($dev['logo_url'])): ?>
                    <img src="<?php echo $dev['logo_url']; ?>" alt="Logo">
                <?php else: ?>
                    <?php echo strtoupper(substr($dev['nome_estudio'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="dev-user-info">
                <div class="dev-user-name"><?php echo sanitize($dev['nome_estudio']); ?></div>
                <div class="dev-user-role">Desenvolvedor</div>
            </div>
            <a href="<?php echo SITE_URL; ?>/pages/logout.php" class="dev-btn-logout" title="Sair">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        <a href="<?php echo SITE_URL; ?>/pages/home.php" class="dev-btn-view-site">
            <i class="fas fa-arrow-left"></i>
            Voltar à Loja
        </a>
    </div>
</aside>


<!-- ==================== MOBILE BOTTOM NAV ==================== -->
<nav class="dev-mobile-bottom-nav">
    <div class="dev-bottom-nav-container">
        <a href="<?php echo SITE_URL; ?>/developer/dashboard.php" class="dev-bottom-nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Home</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/developer/jogos.php" class="dev-bottom-nav-item <?php echo $current_page == 'jogos.php' ? 'active' : ''; ?>">
            <i class="fas fa-gamepad"></i>
            <span>Jogos</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/developer/publicar-jogo.php" class="dev-bottom-nav-item <?php echo $current_page == 'publicar-jogo.php' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Publicar</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/developer/vendas.php" class="dev-bottom-nav-item <?php echo $current_page == 'vendas.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Vendas</span>
        </a>
        <button class="dev-bottom-nav-menu-btn" onclick="openDevMobileMenu()">
            <i class="fas fa-grip-horizontal"></i>
            <span>Menu</span>
        </button>
    </div>
</nav>


<!-- ==================== MOBILE FULL MENU PANEL ==================== -->
<div class="dev-mobile-menu-overlay" id="devMobileMenuOverlay" onclick="closeDevMobileMenu()"></div>

<div class="dev-mobile-menu-panel" id="devMobileMenuPanel">
    <div class="dev-mobile-menu-header">
        <span class="dev-mobile-menu-title">Menu Completo</span>
        <button class="dev-mobile-menu-close" onclick="closeDevMobileMenu()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="dev-mobile-menu-scroll">
        
        <!-- Principal -->
        <div class="dev-mobile-menu-group">
            <div class="dev-mobile-menu-label">Principal</div>
            <div class="dev-mobile-menu-grid">
                <a href="<?php echo SITE_URL; ?>/developer/dashboard.php" class="dev-mobile-menu-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/developer/jogos.php" class="dev-mobile-menu-item <?php echo $current_page == 'jogos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-gamepad"></i>
                    <span>Meus Jogos</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/developer/publicar-jogo.php" class="dev-mobile-menu-item <?php echo $current_page == 'publicar-jogo.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>Publicar</span>
                </a>
            </div>
        </div>

        <!-- Marketing & Financeiro -->
        <div class="dev-mobile-menu-group">
            <div class="dev-mobile-menu-label">Marketing & Financeiro</div>
            <div class="dev-mobile-menu-grid">
                <a href="<?php echo SITE_URL; ?>/developer/promocoes.php" class="dev-mobile-menu-item <?php echo $current_page == 'promocoes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span>Promoções</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/developer/vendas.php" class="dev-mobile-menu-item <?php echo $current_page == 'vendas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Vendas</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/developer/saldo.php" class="dev-mobile-menu-item <?php echo $current_page == 'saldo.php' ? 'active' : ''; ?>">
                    <i class="fas fa-wallet"></i>
                    <span>Saldo</span>
                </a>
            </div>
        </div>

        <!-- Comunidade & Config -->
        <div class="dev-mobile-menu-group">
            <div class="dev-mobile-menu-label">Comunidade & Config</div>
            <div class="dev-mobile-menu-grid">
                <a href="<?php echo SITE_URL; ?>/developer/avaliacoes.php" class="dev-mobile-menu-item <?php echo $current_page == 'avaliacoes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i>
                    <span>Avaliações</span>
                    <?php if ($pending_reviews > 0): ?>
                        <span class="dev-menu-badge"><?php echo $pending_reviews; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo SITE_URL; ?>/developer/perfil.php" class="dev-mobile-menu-item <?php echo $current_page == 'perfil.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-edit"></i>
                    <span>Perfil</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/desenvolvedor.php?slug=<?php echo $dev['slug']; ?>" class="dev-mobile-menu-item" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Página</span>
                </a>
            </div>
        </div>

        <!-- Voltar -->
        <div class="dev-mobile-menu-group">
            <div class="dev-mobile-menu-label">Navegação</div>
            <div class="dev-mobile-menu-grid">
                <a href="<?php echo SITE_URL; ?>/pages/home.php" class="dev-mobile-menu-item">
                    <i class="fas fa-store"></i>
                    <span>Loja</span>
                </a>
            </div>
        </div>

    </div>

    <!-- User Section -->
    <div class="dev-mobile-user-section">
        <div class="dev-mobile-user-card">
            <div class="dev-mobile-user-avatar">
                <?php if (!empty($dev['logo_url'])): ?>
                    <img src="<?php echo $dev['logo_url']; ?>" alt="Logo">
                <?php else: ?>
                    <?php echo strtoupper(substr($dev['nome_estudio'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="dev-mobile-user-info">
                <div class="dev-mobile-user-name"><?php echo sanitize($dev['nome_estudio']); ?></div>
                <div class="dev-mobile-user-role">Desenvolvedor</div>
            </div>
            <div class="dev-mobile-user-actions">
                <a href="<?php echo SITE_URL; ?>/developer/perfil.php" class="dev-mobile-action-btn">
                    <i class="fas fa-cog"></i>
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/logout.php" class="dev-mobile-action-btn logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
</div>


<script>
function openDevMobileMenu() {
    document.getElementById('devMobileMenuOverlay').classList.add('open');
    document.getElementById('devMobileMenuPanel').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDevMobileMenu() {
    document.getElementById('devMobileMenuOverlay').classList.remove('open');
    document.getElementById('devMobileMenuPanel').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDevMobileMenu();
});

document.querySelectorAll('.dev-mobile-menu-item').forEach(item => {
    item.addEventListener('click', () => closeDevMobileMenu());
});
</script>