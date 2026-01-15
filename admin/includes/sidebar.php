<?php
// admin/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);

// Buscar dados do admin logado
$admin_id = $_SESSION['usuario_id'] ?? null;
$admin = null;

if ($admin_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuario WHERE id = ? AND tipo = 'admin'");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch();
    } catch (PDOException $e) {
        $admin = null;
    }
}

// Stats rápidos
try {
    $total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuario WHERE tipo = 'cliente'")->fetchColumn();
} catch (PDOException $e) { $total_usuarios = 0; }

try {
    $total_jogos = $pdo->query("SELECT COUNT(*) FROM jogo")->fetchColumn();
} catch (PDOException $e) { $total_jogos = 0; }

try {
    $tickets_pendentes = $pdo->query("SELECT COUNT(*) FROM ticket WHERE status = 'aberto'")->fetchColumn();
} catch (PDOException $e) { $tickets_pendentes = 0; }

try {
    $pedidos_hoje = $pdo->query("SELECT COUNT(*) FROM pedido WHERE DATE(criado_em) = CURDATE()")->fetchColumn();
} catch (PDOException $e) { $pedidos_hoje = 0; }

try {
    $receita_mes = $pdo->query("SELECT COALESCE(SUM(valor_total), 0) FROM pedido WHERE MONTH(criado_em) = MONTH(CURDATE()) AND status = 'pago'")->fetchColumn();
} catch (PDOException $e) { $receita_mes = 0; }
?>

<style>
/* ============================================
   ADMIN LAYOUT - DESKTOP
   ============================================ */
.admin-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 30px;
    padding: 30px 0;
    min-height: calc(100vh - 80px);
}

.admin-sidebar {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 0;
    height: fit-content;
    position: sticky;
    top: 90px;
    overflow: hidden;
}

/* Sidebar Header */
.admin-sidebar-header {
    padding: 24px;
    background: linear-gradient(135deg, var(--accent), #164e81);
    color: white;
}

.admin-sidebar-header .admin-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.admin-sidebar-header .admin-avatar {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    object-fit: cover;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255,255,255,0.3);
}

.admin-sidebar-header .admin-avatar i {
    font-size: 20px;
}

.admin-sidebar-header .admin-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 10px;
    object-fit: cover;
}

.admin-sidebar-header h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 160px;
}

.admin-sidebar-header p {
    font-size: 12px;
    opacity: 0.9;
}

.admin-sidebar-header .admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    background: rgba(255,255,255,0.2);
    padding: 3px 8px;
    border-radius: 20px;
    margin-top: 4px;
}

/* Sidebar Menu */
.admin-sidebar-menu {
    list-style: none;
    padding: 12px;
    margin: 0;
}

.admin-sidebar-menu li {
    margin-bottom: 4px;
}

.admin-sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: var(--text-primary);
    border-radius: 10px;
    transition: all 0.2s ease;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
}

.admin-sidebar-menu a:hover {
    background: var(--bg-primary);
    transform: translateX(4px);
}

.admin-sidebar-menu a.active {
    background: var(--accent);
    color: white;
    box-shadow: 0 4px 12px rgba(17, 85, 117, 0.3);
}

.admin-sidebar-menu i {
    width: 20px;
    text-align: center;
    font-size: 15px;
}

.admin-sidebar-menu .menu-badge {
    margin-left: auto;
    background: var(--danger);
    color: white;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.admin-sidebar-menu a.active .menu-badge {
    background: rgba(255,255,255,0.3);
}

.admin-sidebar-divider {
    height: 1px;
    background: var(--border);
    margin: 12px 16px;
}

.admin-sidebar-section-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-secondary);
    padding: 8px 16px 4px;
    font-weight: 600;
}

/* Quick Stats in Sidebar */
.admin-sidebar-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding: 12px 16px;
    background: var(--bg-primary);
    margin: 0 12px 12px;
    border-radius: 10px;
}

.admin-sidebar-stat {
    text-align: center;
    padding: 8px;
}

.admin-sidebar-stat .stat-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--accent);
}

.admin-sidebar-stat .stat-value.success {
    color: var(--success);
}

.admin-sidebar-stat .stat-value.warning {
    color: var(--warning);
}

.admin-sidebar-stat .stat-label {
    font-size: 10px;
    color: var(--text-secondary);
    text-transform: uppercase;
}

/* ============================================
   MOBILE STYLES
   ============================================ */
.admin-mobile-header {
    display: none;
    position: fixed;
    top: 60px;
    left: 0;
    right: 0;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
    padding: 12px 16px;
    z-index: 99;
    backdrop-filter: blur(10px);
}

.admin-mobile-header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 100%;
}

.admin-mobile-header .admin-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.admin-mobile-header .admin-avatar {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.admin-mobile-header .admin-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 8px;
    object-fit: cover;
}

.admin-mobile-header h3 {
    font-size: 14px;
    font-weight: 600;
}

.admin-mobile-header p {
    font-size: 11px;
    color: var(--text-secondary);
}

.admin-mobile-menu-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    color: var(--text-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-mobile-menu-btn:hover {
    background: var(--accent);
    color: white;
    border-color: var(--accent);
}

/* Mobile Bottom Navigation */
.admin-bottom-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--bg-secondary);
    border-top: 1px solid var(--border);
    padding: 8px 0;
    padding-bottom: calc(8px + env(safe-area-inset-bottom));
    z-index: 1000;
}

.admin-bottom-nav-content {
    display: flex;
    justify-content: space-around;
    align-items: center;
    max-width: 100%;
}

.admin-bottom-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 8px 12px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 10px;
    font-weight: 500;
    border-radius: 10px;
    transition: all 0.2s;
    position: relative;
    background: none;
    border: none;
    cursor: pointer;
}

.admin-bottom-nav-item i {
    font-size: 20px;
}

.admin-bottom-nav-item.active {
    color: var(--accent);
}

.admin-bottom-nav-item.active::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 24px;
    height: 3px;
    background: var(--accent);
    border-radius: 0 0 3px 3px;
}

.admin-bottom-nav-item .nav-badge {
    position: absolute;
    top: 2px;
    right: 50%;
    transform: translateX(12px);
    background: var(--danger);
    color: white;
    font-size: 9px;
    padding: 1px 5px;
    border-radius: 8px;
    font-weight: 600;
}

/* Admin Bottom Sheet */
.admin-sheet-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1001;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.admin-sheet-overlay.active {
    display: block;
    opacity: 1;
}

.admin-sheet {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--bg-secondary);
    border-radius: 20px 20px 0 0;
    z-index: 1002;
    transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    max-height: 85vh;
    overflow-y: auto;
}

.admin-sheet.active {
    transform: translateY(0);
}

.admin-sheet-handle {
    display: flex;
    justify-content: center;
    padding: 12px;
    cursor: pointer;
}

.admin-sheet-handle span {
    width: 40px;
    height: 4px;
    background: var(--border);
    border-radius: 2px;
}

.admin-sheet-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 20px 20px;
    border-bottom: 1px solid var(--border);
}

.admin-sheet-header .admin-avatar {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
}

.admin-sheet-header .admin-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 12px;
    object-fit: cover;
}

.admin-sheet-header h3 {
    font-size: 16px;
    font-weight: 600;
}

.admin-sheet-header p {
    font-size: 12px;
    color: var(--text-secondary);
}

.admin-sheet-header .badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    background: var(--accent);
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    margin-top: 4px;
}

/* Sheet Stats */
.admin-sheet-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    padding: 16px 20px;
    background: var(--bg-primary);
    margin: 16px;
    border-radius: 12px;
}

.admin-sheet-stat {
    text-align: center;
}

.admin-sheet-stat .stat-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--accent);
}

.admin-sheet-stat .stat-value.success {
    color: var(--success);
}

.admin-sheet-stat .stat-value.warning {
    color: var(--warning);
}

.admin-sheet-stat .stat-label {
    font-size: 10px;
    color: var(--text-secondary);
    text-transform: uppercase;
}

/* Sheet Menu */
.admin-sheet-menu {
    padding: 0 12px 20px;
}

.admin-sheet-menu-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-secondary);
    padding: 12px 12px 8px;
    font-weight: 600;
}

.admin-sheet-menu-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    color: var(--text-primary);
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.2s;
    margin-bottom: 4px;
}

.admin-sheet-menu-item:hover,
.admin-sheet-menu-item:active {
    background: var(--bg-primary);
}

.admin-sheet-menu-item.active {
    background: var(--accent);
    color: white;
}

.admin-sheet-menu-item i {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.admin-sheet-menu-item span {
    flex: 1;
    font-size: 14px;
    font-weight: 500;
}

.admin-sheet-menu-item .chevron {
    color: var(--text-secondary);
    font-size: 12px;
}

.admin-sheet-menu-item.active .chevron {
    color: rgba(255,255,255,0.7);
}

.admin-sheet-menu-item .item-badge {
    background: var(--danger);
    color: white;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.admin-sheet-menu-item.active .item-badge {
    background: rgba(255,255,255,0.3);
}

.admin-sheet-divider {
    height: 1px;
    background: var(--border);
    margin: 8px 16px;
}

.admin-sheet-footer {
    padding: 16px 20px;
    padding-bottom: calc(16px + env(safe-area-inset-bottom));
    border-top: 1px solid var(--border);
}

.admin-sheet-footer a {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text-primary);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
}

.admin-sheet-footer a:hover {
    background: var(--accent);
    color: white;
    border-color: var(--accent);
}

/* ============================================
   RESPONSIVE BREAKPOINTS
   ============================================ */
@media (max-width: 1024px) {
    .admin-layout {
        grid-template-columns: 240px 1fr;
        gap: 20px;
    }
}

@media (max-width: 900px) {
    .admin-layout {
        grid-template-columns: 1fr;
        padding: 0;
        padding-top: 70px;
        padding-bottom: 80px;
    }
    
    .admin-sidebar {
        display: none;
    }
    
    .admin-mobile-header {
        display: block;
    }
    
    .admin-bottom-nav {
        display: block;
    }
    
    .admin-content {
        padding: 16px;
    }
}

@media (max-width: 480px) {
    .admin-sheet-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        padding: 12px;
        margin: 12px;
    }
    
    .admin-sheet-stat .stat-value {
        font-size: 16px;
    }
}
</style>

<!-- ============================================
     MOBILE HEADER
     ============================================ -->
<div class="admin-mobile-header">
    <div class="admin-mobile-header-content">
        <div class="admin-info">
            <div class="admin-avatar">
                <?php if (!empty($admin['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($admin['avatar_url']) ?>" alt="Avatar">
                <?php else: ?>
                    <i class="fas fa-user-shield"></i>
                <?php endif; ?>
            </div>
            <div>
                <h3><?= htmlspecialchars($admin['nome_usuario'] ?? 'Admin') ?></h3>
                <p>Painel Admin</p>
            </div>
        </div>
        <button class="admin-mobile-menu-btn" onclick="openAdminSheet()">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</div>

<!-- ============================================
     DESKTOP SIDEBAR
     ============================================ -->
<div class="admin-sidebar">
    <div class="admin-sidebar-header">
        <div class="admin-info">
            <div class="admin-avatar">
                <?php if (!empty($admin['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($admin['avatar_url']) ?>" alt="Avatar">
                <?php else: ?>
                    <i class="fas fa-user-shield"></i>
                <?php endif; ?>
            </div>
            <div>
                <h3><?= htmlspecialchars($admin['nome_usuario'] ?? 'Admin') ?></h3>
                <p><?= htmlspecialchars($admin['email'] ?? '') ?></p>
                <span class="admin-badge">
                    <i class="fas fa-shield-alt"></i> Administrador
                </span>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="admin-sidebar-stats">
        <div class="admin-sidebar-stat">
            <span class="stat-value"><?= number_format($total_usuarios) ?></span>
            <span class="stat-label">Usuários</span>
        </div>
        <div class="admin-sidebar-stat">
            <span class="stat-value success"><?= number_format($total_jogos) ?></span>
            <span class="stat-label">Jogos</span>
        </div>
        <div class="admin-sidebar-stat">
            <span class="stat-value warning"><?= $tickets_pendentes ?></span>
            <span class="stat-label">Tickets</span>
        </div>
        <div class="admin-sidebar-stat">
            <span class="stat-value"><?= $pedidos_hoje ?></span>
            <span class="stat-label">Pedidos</span>
        </div>
    </div>
    
    <ul class="admin-sidebar-menu">
        <span class="admin-sidebar-section-title">Principal</span>
        
        <li>
            <a href="<?= SITE_URL ?>/admin/dashboard.php" 
               class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </a>
        </li>
        
        <div class="admin-sidebar-divider"></div>
        <span class="admin-sidebar-section-title">Conteúdo</span>
        
        <li>
            <a href="<?= SITE_URL ?>/admin/jogos.php" 
               class="<?= $current_page == 'jogos.php' ? 'active' : '' ?>">
                <i class="fas fa-gamepad"></i>
                Jogos
            </a>
        </li>
        
        <li>
            <a href="<?= SITE_URL ?>/admin/categorias.php" 
               class="<?= $current_page == 'categorias.php' ? 'active' : '' ?>">
                <i class="fas fa-folder"></i>
                Categorias
            </a>
        </li>
        
        <li>
            <a href="<?= SITE_URL ?>/admin/tags.php" 
               class="<?= $current_page == 'tags.php' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i>
                Tags
            </a>
        </li>
        
        <div class="admin-sidebar-divider"></div>
        <span class="admin-sidebar-section-title">Usuários</span>
        
        <li>
            <a href="<?= SITE_URL ?>/admin/usuarios.php" 
               class="<?= $current_page == 'usuarios.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                Usuários
            </a>
        </li>
        
        <li>
            <a href="<?= SITE_URL ?>/admin/desenvolvedores.php" 
               class="<?= $current_page == 'desenvolvedores.php' ? 'active' : '' ?>">
                <i class="fas fa-code"></i>
                Desenvolvedores
            </a>
        </li>
        
        <div class="admin-sidebar-divider"></div>
        <span class="admin-sidebar-section-title">Vendas</span>
        
        <li>
            <a href="<?= SITE_URL ?>/admin/pedidos.php" 
               class="<?= $current_page == 'pedidos.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i>
                Pedidos
                <?php if ($pedidos_hoje > 0): ?>
                    <span class="menu-badge"><?= $pedidos_hoje ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li>
            <a href="<?= SITE_URL ?>/admin/eventos.php" 
               class="<?= $current_page == 'eventos.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i>
                Eventos
            </a>
        </li>
        
        <div class="admin-sidebar-divider"></div>
        <span class="admin-sidebar-section-title">Suporte</span>
        
        <li>
            <a href="<?= SITE_URL ?>/admin/tickets.php" 
               class="<?= $current_page == 'tickets.php' ? 'active' : '' ?>">
                <i class="fas fa-life-ring"></i>
                Tickets
                <?php if ($tickets_pendentes > 0): ?>
                    <span class="menu-badge"><?= $tickets_pendentes ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <div class="admin-sidebar-divider"></div>
        <span class="admin-sidebar-section-title">Sistema</span>
        
        <li>
            <a href="<?= SITE_URL ?>/admin/configuracoes.php" 
               class="<?= $current_page == 'configuracoes.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                Configurações
            </a>
        </li>
        
        <li>
            <a href="<?= SITE_URL ?>/admin/logs.php" 
               class="<?= $current_page == 'logs.php' ? 'active' : '' ?>">
                <i class="fas fa-history"></i>
                Logs
            </a>
        </li>
        
        <div class="admin-sidebar-divider"></div>
        
        <li>
            <a href="<?= SITE_URL ?>/pages/home.php">
                <i class="fas fa-arrow-left"></i>
                Voltar à Loja
            </a>
        </li>
    </ul>
</div>

<!-- ============================================
     MOBILE BOTTOM NAVIGATION
     ============================================ -->
<nav class="admin-bottom-nav">
    <div class="admin-bottom-nav-content">
        <a href="<?= SITE_URL ?>/admin/dashboard.php" 
           class="admin-bottom-nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?= SITE_URL ?>/admin/jogos.php" 
           class="admin-bottom-nav-item <?= $current_page == 'jogos.php' ? 'active' : '' ?>">
            <i class="fas fa-gamepad"></i>
            <span>Jogos</span>
        </a>
        
        <a href="<?= SITE_URL ?>/admin/usuarios.php" 
           class="admin-bottom-nav-item <?= $current_page == 'usuarios.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Usuários</span>
        </a>
        
        <a href="<?= SITE_URL ?>/admin/pedidos.php" 
           class="admin-bottom-nav-item <?= $current_page == 'pedidos.php' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Pedidos</span>
            <?php if ($pedidos_hoje > 0): ?>
                <span class="nav-badge"><?= $pedidos_hoje ?></span>
            <?php endif; ?>
        </a>
        
        <button class="admin-bottom-nav-item" onclick="openAdminSheet()">
            <i class="fas fa-ellipsis-h"></i>
            <span>Mais</span>
        </button>
    </div>
</nav>

<!-- ============================================
     MOBILE BOTTOM SHEET
     ============================================ -->
<div class="admin-sheet-overlay" id="adminSheetOverlay" onclick="closeAdminSheet()"></div>
<div class="admin-sheet" id="adminSheet">
    <div class="admin-sheet-handle" onclick="closeAdminSheet()">
        <span></span>
    </div>
    
    <!-- Sheet Header -->
    <div class="admin-sheet-header">
        <div class="admin-avatar">
            <?php if (!empty($admin['avatar_url'])): ?>
                <img src="<?= htmlspecialchars($admin['avatar_url']) ?>" alt="Avatar">
            <?php else: ?>
                <i class="fas fa-user-shield"></i>
            <?php endif; ?>
        </div>
        <div>
            <h3><?= htmlspecialchars($admin['nome_usuario'] ?? 'Admin') ?></h3>
            <p>Painel Administrativo</p>
            <span class="badge"><i class="fas fa-shield-alt"></i> Admin</span>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="admin-sheet-stats">
        <div class="admin-sheet-stat">
            <div class="stat-value"><?= number_format($total_usuarios) ?></div>
            <div class="stat-label">Usuários</div>
        </div>
        <div class="admin-sheet-stat">
            <div class="stat-value success"><?= number_format($total_jogos) ?></div>
            <div class="stat-label">Jogos</div>
        </div>
        <div class="admin-sheet-stat">
            <div class="stat-value warning"><?= $tickets_pendentes ?></div>
            <div class="stat-label">Tickets</div>
        </div>
        <div class="admin-sheet-stat">
            <div class="stat-value"><?= $pedidos_hoje ?></div>
            <div class="stat-label">Pedidos</div>
        </div>
    </div>
    
    <!-- Menu Items -->
    <div class="admin-sheet-menu">
        <div class="admin-sheet-menu-title">Conteúdo</div>
        
        <a href="<?= SITE_URL ?>/admin/categorias.php" 
           class="admin-sheet-menu-item <?= $current_page == 'categorias.php' ? 'active' : '' ?>">
            <i class="fas fa-folder"></i>
            <span>Categorias</span>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
        
        <a href="<?= SITE_URL ?>/admin/tags.php" 
           class="admin-sheet-menu-item <?= $current_page == 'tags.php' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i>
            <span>Tags</span>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
        
        <div class="admin-sheet-divider"></div>
        <div class="admin-sheet-menu-title">Usuários</div>
        
        <a href="<?= SITE_URL ?>/admin/desenvolvedores.php" 
           class="admin-sheet-menu-item <?= $current_page == 'desenvolvedores.php' ? 'active' : '' ?>">
            <i class="fas fa-code"></i>
            <span>Desenvolvedores</span>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
        
        <div class="admin-sheet-divider"></div>
        <div class="admin-sheet-menu-title">Vendas</div>
        
        <a href="<?= SITE_URL ?>/admin/promocoes.php" 
           class="admin-sheet-menu-item <?= $current_page == 'promocoes.php' ? 'active' : '' ?>">
            <i class="fas fa-percentage"></i>
            <span>Promoções</span>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
        
        <div class="admin-sheet-divider"></div>
        <div class="admin-sheet-menu-title">Suporte</div>
        
        <a href="<?= SITE_URL ?>/admin/tickets.php" 
           class="admin-sheet-menu-item <?= $current_page == 'tickets.php' ? 'active' : '' ?>">
            <i class="fas fa-life-ring"></i>
            <span>Tickets de Suporte</span>
            <?php if ($tickets_pendentes > 0): ?>
                <span class="item-badge"><?= $tickets_pendentes ?></span>
            <?php endif; ?>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
        
        <div class="admin-sheet-divider"></div>
        <div class="admin-sheet-menu-title">Sistema</div>
        
        <a href="<?= SITE_URL ?>/admin/configuracoes.php" 
           class="admin-sheet-menu-item <?= $current_page == 'configuracoes.php' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i>
            <span>Configurações</span>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
        
        <a href="<?= SITE_URL ?>/admin/logs.php" 
           class="admin-sheet-menu-item <?= $current_page == 'logs.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i>
            <span>Logs do Sistema</span>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
    </div>
    
    <!-- Footer -->
    <div class="admin-sheet-footer">
        <a href="<?= SITE_URL ?>/pages/home.php">
            <i class="fas fa-arrow-left"></i>
            Voltar à Loja
        </a>
    </div>
</div>

<script>
// Admin Bottom Sheet Functions
function openAdminSheet() {
    document.getElementById('adminSheetOverlay').classList.add('active');
    document.getElementById('adminSheet').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAdminSheet() {
    document.getElementById('adminSheetOverlay').classList.remove('active');
    document.getElementById('adminSheet').classList.remove('active');
    document.body.style.overflow = '';
}

// Swipe to close
let adminSheetStartY = 0;
const adminSheet = document.getElementById('adminSheet');

if (adminSheet) {
    adminSheet.addEventListener('touchstart', (e) => {
        adminSheetStartY = e.touches[0].clientY;
    });

    adminSheet.addEventListener('touchmove', (e) => {
        const currentY = e.touches[0].clientY;
        const diff = currentY - adminSheetStartY;
        
        if (diff > 0 && adminSheet.scrollTop === 0) {
            adminSheet.style.transform = `translateY(${diff}px)`;
        }
    });

    adminSheet.addEventListener('touchend', (e) => {
        const currentY = e.changedTouches[0].clientY;
        const diff = currentY - adminSheetStartY;
        
        if (diff > 100) {
            closeAdminSheet();
        }
        adminSheet.style.transform = '';
    });
}

// Close on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeAdminSheet();
    }
});
</script>