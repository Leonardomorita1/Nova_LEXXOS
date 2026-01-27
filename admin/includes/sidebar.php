<?php
// Obtém o nome do arquivo da página atual
$current_page = basename($_SERVER['PHP_SELF']);

// Contadores para badges
$pending_devs = 0;
$pending_tickets = 0;
$pending_saques = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM desenvolvedor WHERE status = 'pendente'");
    $pending_devs = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'aberto'");
    $pending_tickets = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM saques WHERE status = 'pendente'");
    $pending_saques = $stmt->fetchColumn();
} catch(Exception $e) {}
?>

<style>
/* =============================================
   ADMIN LAYOUT - FULL WIDTH + FIXED SIDEBAR
   ============================================= */
body.admin-body {
    margin: 0;
    padding: 0;
    overflow-x: hidden;
}

.admin-layout {
    display: flex;
    min-height: 100vh;
    width: 100%;
}

/* =============================================
   SIDEBAR - FIXED POSITION
   ============================================= */
.admin-sidebar {
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

.sidebar-spacer {
    width: 250px;
    min-width: 250px;
    flex-shrink: 0;
}

/* ===== SIDEBAR HEADER ===== */
.sidebar-header {
    padding: 20px 16px;
    border-bottom: 1px solid var(--border);
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-logo {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, var(--accent), #667eea);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: white;
}

.sidebar-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
}

.sidebar-subtitle {
    font-size: 0.7rem;
    color: var(--text-secondary);
    margin-top: 2px;
}

/* ===== SCROLLABLE NAV ===== */
.sidebar-scroll {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 12px 0;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.sidebar-scroll::-webkit-scrollbar {
    display: none;
}

/* ===== NAVIGATION ===== */
.nav-group {
    margin-bottom: 6px;
}

.nav-label {
    padding: 10px 16px 6px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-secondary);
    opacity: 0.5;
}

.nav-link {
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

.nav-link i {
    width: 18px;
    text-align: center;
    font-size: 0.95rem;
    opacity: 0.6;
}

.nav-link:hover {
    background: rgba(255,255,255,0.03);
    color: var(--text-primary);
}

.nav-link:hover i {
    opacity: 1;
}

.nav-link.active {
    background: rgba(76, 139, 245, 0.08);
    color: var(--accent);
    border-left-color: var(--accent);
}

.nav-link.active i {
    opacity: 1;
    color: var(--accent);
}

.nav-badge {
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

.nav-badge.warning {
    background: #e67e22;
}

.nav-badge.danger {
    background: #e74c3c;
}

/* ===== SIDEBAR FOOTER ===== */
.sidebar-footer {
    padding: 12px;
    border-top: 1px solid var(--border);
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: var(--bg-primary);
    border-radius: 8px;
}

.user-avatar {
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
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 0.65rem;
    color: var(--text-secondary);
}

.btn-logout {
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

.btn-logout:hover {
    background: rgba(231, 76, 60, 0.1);
    border-color: #e74c3c;
    color: #e74c3c;
}

.btn-view-site {
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

.btn-view-site:hover {
    background: var(--bg-primary);
    color: var(--text-primary);
    border-color: var(--accent);
}

/* =============================================
   ADMIN CONTENT AREA - APROVEITANDO ESPAÇO
   ============================================= */
.admin-content {
    flex: 1;
    min-width: 0;
    padding: 24px 30px; /* Menos padding lateral */
    background: var(--bg-primary);
}

/* Remove qualquer max-width ou container interno */
.container {
    max-width: none;
    width: 100%;
    padding-left: 0;
    padding-right: 0;
}

/* =============================================
   MOBILE - BOTTOM NAVIGATION APP STYLE
   ============================================= */
.mobile-bottom-nav {
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

.bottom-nav-container {
    display: flex;
    justify-content: space-around;
    align-items: center;
    height: 60px;
    max-width: 100%;
}

.bottom-nav-item {
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

.bottom-nav-item i {
    font-size: 1.2rem;
    opacity: 0.7;
}

.bottom-nav-item.active {
    color: var(--accent);
}

.bottom-nav-item.active i {
    opacity: 1;
}

.bottom-nav-item .nav-badge {
    position: absolute;
    top: 4px;
    right: 50%;
    transform: translateX(14px);
    font-size: 0.55rem;
    padding: 1px 4px;
    min-width: 14px;
}

.bottom-nav-menu-btn {
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

.bottom-nav-menu-btn i {
    font-size: 1.2rem;
    opacity: 0.7;
}

/* ===== MOBILE FULL MENU ===== */
.mobile-menu-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(4px);
    z-index: 1001;
    opacity: 0;
    transition: opacity 0.3s;
}

.mobile-menu-overlay.open {
    display: block;
    opacity: 1;
}

.mobile-menu-panel {
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

.mobile-menu-panel.open {
    transform: translateY(0);
}

.mobile-menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
}

.mobile-menu-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
}

.mobile-menu-close {
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

.mobile-menu-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 12px 0;
    padding-bottom: calc(12px + env(safe-area-inset-bottom));
}

.mobile-menu-group {
    padding: 0 16px;
    margin-bottom: 16px;
}

.mobile-menu-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-secondary);
    opacity: 0.5;
    margin-bottom: 8px;
    padding-left: 4px;
}

.mobile-menu-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.mobile-menu-item {
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

.mobile-menu-item i {
    font-size: 1.3rem;
    opacity: 0.7;
}

.mobile-menu-item.active {
    background: rgba(76, 139, 245, 0.1);
    border-color: var(--accent);
    color: var(--accent);
}

.mobile-menu-item.active i {
    opacity: 1;
}

.mobile-menu-item:hover {
    border-color: var(--accent);
}

.mobile-menu-item .menu-badge {
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
.mobile-user-section {
    padding: 16px;
    border-top: 1px solid var(--border);
    background: var(--bg-primary);
}

.mobile-user-card {
    display: flex;
    align-items: center;
    gap: 12px;
}

.mobile-user-avatar {
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
}

.mobile-user-info {
    flex: 1;
}

.mobile-user-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
}

.mobile-user-role {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.mobile-user-actions {
    display: flex;
    gap: 8px;
}

.mobile-action-btn {
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

.mobile-action-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.mobile-action-btn.logout:hover {
    border-color: #e74c3c;
    color: #e74c3c;
}

/* =============================================
   RESPONSIVE
   ============================================= */
@media (max-width: 1024px) {
    .admin-sidebar,
    .sidebar-spacer {
        display: none;
    }

    .mobile-bottom-nav {
        display: block;
    }

    .admin-content {
        padding: 16px;
        padding-bottom: 80px;
    }

    .admin-layout {
        flex-direction: column;
    }
}

@media (max-width: 768px) {
    .admin-content {
        padding: 16px;
        padding-bottom: 80px;
    }
}

@media (max-width: 480px) {
    .admin-content {
        padding: 12px;
        padding-bottom: 80px;
    }

    .mobile-menu-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
    }

    .mobile-menu-item {
        padding: 14px 6px;
        font-size: 0.65rem;
    }

    .mobile-menu-item i {
        font-size: 1.2rem;
    }
}
</style>

<!-- ==================== DESKTOP SIDEBAR ==================== -->
<div class="sidebar-spacer"></div>

<aside class="admin-sidebar">
    

    <!-- Navigation Scroll -->
    <div class="sidebar-scroll">
        
        <!-- Principal -->
        <div class="nav-group">
            <div class="nav-label">Principal</div>
            <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= SITE_URL ?>/admin/eventos.php" class="nav-link <?= $current_page == 'eventos.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i>
                <span>Monitoramento</span>
            </a>
        </div>

        <!-- Gestão -->
        <div class="nav-group">
            <div class="nav-label">Gestão</div>
            <a href="<?= SITE_URL ?>/admin/usuarios.php" class="nav-link <?= $current_page == 'usuarios.php' || $current_page == 'usuario-detalhes.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>Usuários</span>
            </a>
            <a href="<?= SITE_URL ?>/admin/desenvolvedores.php" class="nav-link <?= $current_page == 'desenvolvedores.php' ? 'active' : '' ?>">
                <i class="fas fa-code"></i>
                <span>Desenvolvedores</span>
                <?php if ($pending_devs > 0): ?>
                    <span class="nav-badge warning"><?= $pending_devs ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= SITE_URL ?>/admin/jogos.php" class="nav-link <?= $current_page == 'jogos.php' || $current_page == 'jogo-detalhes.php' ? 'active' : '' ?>">
                <i class="fas fa-gamepad"></i>
                <span>Jogos</span>
            </a>
            <a href="<?= SITE_URL ?>/admin/categorias.php" class="nav-link <?= $current_page == 'categorias.php' ? 'active' : '' ?>">
                <i class="fas fa-folder"></i>
                <span>Categorias</span>
            </a>
            <a href="<?= SITE_URL ?>/admin/tags.php" class="nav-link <?= $current_page == 'tags.php' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i>
                <span>Tags</span>
            </a>
        </div>

        <!-- Marketing -->
        <div class="nav-group">
            <div class="nav-label">Marketing</div>
            <a href="<?= SITE_URL ?>/admin/banners.php" class="nav-link <?= $current_page == 'banners.php' ? 'active' : '' ?>">
                <i class="fas fa-images"></i>
                <span>Banners</span>
            </a>
        </div>

        <!-- Financeiro -->
        <div class="nav-group">
            <div class="nav-label">Financeiro</div>
            <a href="<?= SITE_URL ?>/admin/pedidos.php" class="nav-link <?= $current_page == 'pedidos.php' || $current_page == 'pedido-detalhes.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Pedidos</span>
            </a>
            <a href="<?= SITE_URL ?>/admin/saques.php" class="nav-link <?= $current_page == 'saques.php' ? 'active' : '' ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span>Saques</span>
                <?php if ($pending_saques > 0): ?>
                    <span class="nav-badge warning"><?= $pending_saques ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Suporte -->
        <div class="nav-group">
            <div class="nav-label">Suporte</div>
            <a href="<?= SITE_URL ?>/admin/tickets.php" class="nav-link <?= $current_page == 'tickets.php' || $current_page == 'ticket-detalhes.php' ? 'active' : '' ?>">
                <i class="fas fa-headset"></i>
                <span>Tickets</span>
                <?php if ($pending_tickets > 0): ?>
                    <span class="nav-badge danger"><?= $pending_tickets ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= SITE_URL ?>/admin/faqs.php" class="nav-link <?= $current_page == 'faqs.php' ? 'active' : '' ?>">
                <i class="fas fa-question-circle"></i>
                <span>FAQs</span>
            </a>
        </div>

        <!-- Sistema -->
        <div class="nav-group">
            <div class="nav-label">Sistema</div>
            <a href="<?= SITE_URL ?>/admin/configuracoes.php" class="nav-link <?= $current_page == 'configuracoes.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span>Configurações</span>
            </a>
            <a href="<?= SITE_URL ?>/admin/logs.php" class="nav-link <?= $current_page == 'logs.php' ? 'active' : '' ?>">
                <i class="fas fa-history"></i>
                <span>Logs</span>
            </a>
        </div>

    </div>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">
                <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="user-avatar"
                                    onerror="this.src='<?php echo $default_avatar; ?>'">
            </div>
            <div class="user-info">
                <strong><?php echo sanitize($user_name); ?></strong>
                <div class="user-role">Administrador</div>
            </div>
            <a href="<?= SITE_URL ?>/pages/logout.php" class="btn-logout" title="Sair">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        <a href="<?= SITE_URL ?>/" class="btn-view-site" target="_blank">
            <i class="fas fa-external-link-alt"></i>
            Ver Loja
        </a>
    </div>
</aside>


<!-- ==================== MOBILE BOTTOM NAV ==================== -->
<nav class="mobile-bottom-nav">
    <div class="bottom-nav-container">
        <a href="<?= SITE_URL ?>/admin/dashboard.php" class="bottom-nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i>
            <span>Home</span>
        </a>
        <a href="<?= SITE_URL ?>/admin/jogos.php" class="bottom-nav-item <?= $current_page == 'jogos.php' ? 'active' : '' ?>">
            <i class="fas fa-gamepad"></i>
            <span>Jogos</span>
        </a>
        <a href="<?= SITE_URL ?>/admin/pedidos.php" class="bottom-nav-item <?= $current_page == 'pedidos.php' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Pedidos</span>
        </a>
        <a href="<?= SITE_URL ?>/admin/tickets.php" class="bottom-nav-item <?= $current_page == 'tickets.php' ? 'active' : '' ?>">
            <i class="fas fa-headset"></i>
            <span>Tickets</span>
            <?php if ($pending_tickets > 0): ?>
                <span class="nav-badge danger"><?= $pending_tickets ?></span>
            <?php endif; ?>
        </a>
        <button class="bottom-nav-menu-btn" onclick="openMobileMenu()">
            <i class="fas fa-grip-horizontal"></i>
            <span>Menu</span>
        </button>
    </div>
</nav>


<!-- ==================== MOBILE FULL MENU PANEL ==================== -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>

<div class="mobile-menu-panel" id="mobileMenuPanel">
    <div class="mobile-menu-header">
        <span class="mobile-menu-title">Menu Completo</span>
        <button class="mobile-menu-close" onclick="closeMobileMenu()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="mobile-menu-scroll">
        
        <!-- Principal -->
        <div class="mobile-menu-group">
            <div class="mobile-menu-label">Principal</div>
            <div class="mobile-menu-grid">
                <a href="<?= SITE_URL ?>/admin/dashboard.php" class="mobile-menu-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/eventos.php" class="mobile-menu-item <?= $current_page == 'eventos.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Monitor</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/pedidos.php" class="mobile-menu-item <?= $current_page == 'pedidos.php' ? 'active' : '' ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Pedidos</span>
                </a>
            </div>
        </div>

        <!-- Gestão -->
        <div class="mobile-menu-group">
            <div class="mobile-menu-label">Gestão</div>
            <div class="mobile-menu-grid">
                <a href="<?= SITE_URL ?>/admin/usuarios.php" class="mobile-menu-item <?= $current_page == 'usuarios.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    <span>Usuários</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/desenvolvedores.php" class="mobile-menu-item <?= $current_page == 'desenvolvedores.php' ? 'active' : '' ?>">
                    <i class="fas fa-code"></i>
                    <span>Devs</span>
                    <?php if ($pending_devs > 0): ?>
                        <span class="menu-badge"><?= $pending_devs ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= SITE_URL ?>/admin/jogos.php" class="mobile-menu-item <?= $current_page == 'jogos.php' ? 'active' : '' ?>">
                    <i class="fas fa-gamepad"></i>
                    <span>Jogos</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/categorias.php" class="mobile-menu-item <?= $current_page == 'categorias.php' ? 'active' : '' ?>">
                    <i class="fas fa-folder"></i>
                    <span>Categorias</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/tags.php" class="mobile-menu-item <?= $current_page == 'tags.php' ? 'active' : '' ?>">
                    <i class="fas fa-tags"></i>
                    <span>Tags</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/banners.php" class="mobile-menu-item <?= $current_page == 'banners.php' ? 'active' : '' ?>">
                    <i class="fas fa-images"></i>
                    <span>Banners</span>
                </a>
            </div>
        </div>

        <!-- Financeiro & Suporte -->
        <div class="mobile-menu-group">
            <div class="mobile-menu-label">Financeiro & Suporte</div>
            <div class="mobile-menu-grid">
                <a href="<?= SITE_URL ?>/admin/saques.php" class="mobile-menu-item <?= $current_page == 'saques.php' ? 'active' : '' ?>">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Saques</span>
                    <?php if ($pending_saques > 0): ?>
                        <span class="menu-badge"><?= $pending_saques ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= SITE_URL ?>/admin/tickets.php" class="mobile-menu-item <?= $current_page == 'tickets.php' ? 'active' : '' ?>">
                    <i class="fas fa-headset"></i>
                    <span>Tickets</span>
                    <?php if ($pending_tickets > 0): ?>
                        <span class="menu-badge"><?= $pending_tickets ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= SITE_URL ?>/admin/faqs.php" class="mobile-menu-item <?= $current_page == 'faqs.php' ? 'active' : '' ?>">
                    <i class="fas fa-question-circle"></i>
                    <span>FAQs</span>
                </a>
            </div>
        </div>

        <!-- Sistema -->
        <div class="mobile-menu-group">
            <div class="mobile-menu-label">Sistema</div>
            <div class="mobile-menu-grid">
                <a href="<?= SITE_URL ?>/admin/configuracoes.php" class="mobile-menu-item <?= $current_page == 'configuracoes.php' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i>
                    <span>Config</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/logs.php" class="mobile-menu-item <?= $current_page == 'logs.php' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i>
                    <span>Logs</span>
                </a>
                <a href="<?= SITE_URL ?>/" class="mobile-menu-item" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Ver Loja</span>
                </a>
            </div>
        </div>

    </div>

    <!-- User Section -->
    <div class="mobile-user-section">
        <div class="mobile-user-card">
            <div class="mobile-user-avatar">
                <?= strtoupper(substr($_SESSION['usuario_nome'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="mobile-user-info">
                <div class="mobile-user-name"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Admin') ?></div>
                <div class="mobile-user-role">Administrador</div>
            </div>
            <div class="mobile-user-actions">
                <a href="<?= SITE_URL ?>/admin/configuracoes.php" class="mobile-action-btn">
                    <i class="fas fa-cog"></i>
                </a>
                <a href="<?= SITE_URL ?>/pages/logout.php" class="mobile-action-btn logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
</div>


<script>
function openMobileMenu() {
    document.getElementById('mobileMenuOverlay').classList.add('open');
    document.getElementById('mobileMenuPanel').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeMobileMenu() {
    document.getElementById('mobileMenuOverlay').classList.remove('open');
    document.getElementById('mobileMenuPanel').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMobileMenu();
});

document.querySelectorAll('.mobile-menu-item').forEach(item => {
    item.addEventListener('click', () => closeMobileMenu());
});
</script>