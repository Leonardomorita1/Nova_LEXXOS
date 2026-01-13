<?php
// developer/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
/* ============================================
   DEV LAYOUT - DESKTOP
   ============================================ */
.dev-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 30px;
    padding: 30px 0;
    min-height: calc(100vh - 80px);
}

.dev-sidebar {
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
.dev-sidebar-header {
    padding: 24px;
    background: linear-gradient(135deg, var(--accent), var(--accent-hover));
    color: white;
}

.dev-sidebar-header .studio-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.dev-sidebar-header .studio-logo {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    object-fit: cover;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
}

.dev-sidebar-header .studio-logo i {
    font-size: 20px;
}

.dev-sidebar-header .studio-logo img {
    width: 100%;
    height: 100%;
    border-radius: 12px;
    object-fit: cover;
}

.dev-sidebar-header h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 160px;
}

.dev-sidebar-header p {
    font-size: 12px;
    opacity: 0.9;
}

.dev-sidebar-header .studio-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    background: rgba(255,255,255,0.2);
    padding: 3px 8px;
    border-radius: 20px;
    margin-top: 4px;
}

.dev-sidebar-header .studio-badge.verified {
    background: rgba(46, 204, 113, 0.3);
}

/* Sidebar Menu */
.dev-sidebar-menu {
    list-style: none;
    padding: 12px;
}

.dev-sidebar-menu li {
    margin-bottom: 4px;
}

.dev-sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: var(--text-primary);
    border-radius: 10px;
    transition: all 0.2s ease;
    font-size: 14px;
    font-weight: 500;
}

.dev-sidebar-menu a:hover {
    background: var(--bg-primary);
    transform: translateX(4px);
}

.dev-sidebar-menu a.active {
    background: var(--accent);
    color: white;
    box-shadow: 0 4px 12px rgba(108, 92, 231, 0.3);
}

.dev-sidebar-menu i {
    width: 20px;
    text-align: center;
    font-size: 15px;
}

.dev-sidebar-menu .menu-badge {
    margin-left: auto;
    background: var(--danger);
    color: white;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.dev-sidebar-menu a.active .menu-badge {
    background: rgba(255,255,255,0.3);
}

.dev-sidebar-divider {
    height: 1px;
    background: var(--border);
    margin: 12px 16px;
}

.dev-sidebar-section-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-secondary);
    padding: 8px 16px 4px;
    font-weight: 600;
}

/* Quick Stats in Sidebar */
.dev-sidebar-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding: 12px 16px;
    background: var(--bg-primary);
    margin: 0 12px 12px;
    border-radius: 10px;
}

.dev-sidebar-stat {
    text-align: center;
    padding: 8px;
}

.dev-sidebar-stat .stat-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--accent);
}

.dev-sidebar-stat .stat-label {
    font-size: 10px;
    color: var(--text-secondary);
    text-transform: uppercase;
}

/* ============================================
   MOBILE STYLES
   ============================================ */
.dev-mobile-header {
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

.dev-mobile-header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 100%;
}

.dev-mobile-header .studio-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.dev-mobile-header .studio-logo {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.dev-mobile-header .studio-logo img {
    width: 100%;
    height: 100%;
    border-radius: 8px;
    object-fit: cover;
}

.dev-mobile-header h3 {
    font-size: 14px;
    font-weight: 600;
}

.dev-mobile-header p {
    font-size: 11px;
    color: var(--text-secondary);
}

.dev-mobile-menu-btn {
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
}

/* Mobile Bottom Navigation */
.dev-bottom-nav {
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

.dev-bottom-nav-content {
    display: flex;
    justify-content: space-around;
    align-items: center;
    max-width: 100%;
}

.dev-bottom-nav-item {
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

.dev-bottom-nav-item i {
    font-size: 20px;
}

.dev-bottom-nav-item.active {
    color: var(--accent);
}

.dev-bottom-nav-item.active::before {
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

.dev-bottom-nav-item .nav-badge {
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

/* Dev Bottom Sheet */
.dev-sheet-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1001;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.dev-sheet-overlay.active {
    display: block;
    opacity: 1;
}

.dev-sheet {
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

.dev-sheet.active {
    transform: translateY(0);
}

.dev-sheet-handle {
    display: flex;
    justify-content: center;
    padding: 12px;
    cursor: pointer;
}

.dev-sheet-handle span {
    width: 40px;
    height: 4px;
    background: var(--border);
    border-radius: 2px;
}

.dev-sheet-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 20px 20px;
    border-bottom: 1px solid var(--border);
}

.dev-sheet-header .studio-logo {
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

.dev-sheet-header .studio-logo img {
    width: 100%;
    height: 100%;
    border-radius: 12px;
    object-fit: cover;
}

.dev-sheet-header h3 {
    font-size: 16px;
    font-weight: 600;
}

.dev-sheet-header p {
    font-size: 12px;
    color: var(--text-secondary);
}

.dev-sheet-header .badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    background: var(--success);
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    margin-top: 4px;
}

/* Sheet Stats */
.dev-sheet-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    padding: 16px 20px;
    background: var(--bg-primary);
    margin: 16px;
    border-radius: 12px;
}

.dev-sheet-stat {
    text-align: center;
}

.dev-sheet-stat .stat-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--accent);
}

.dev-sheet-stat .stat-label {
    font-size: 10px;
    color: var(--text-secondary);
    text-transform: uppercase;
}

/* Sheet Menu */
.dev-sheet-menu {
    padding: 0 12px 20px;
}

.dev-sheet-menu-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-secondary);
    padding: 12px 12px 8px;
    font-weight: 600;
}

.dev-sheet-menu-item {
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

.dev-sheet-menu-item:hover,
.dev-sheet-menu-item:active {
    background: var(--bg-primary);
}

.dev-sheet-menu-item.active {
    background: var(--accent);
    color: white;
}

.dev-sheet-menu-item i {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.dev-sheet-menu-item span {
    flex: 1;
    font-size: 14px;
    font-weight: 500;
}

.dev-sheet-menu-item .chevron {
    color: var(--text-secondary);
    font-size: 12px;
}

.dev-sheet-menu-item.active .chevron {
    color: rgba(255,255,255,0.7);
}

.dev-sheet-menu-item .item-badge {
    background: var(--danger);
    color: white;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.dev-sheet-divider {
    height: 1px;
    background: var(--border);
    margin: 8px 16px;
}

.dev-sheet-footer {
    padding: 16px 20px;
    padding-bottom: calc(16px + env(safe-area-inset-bottom));
    border-top: 1px solid var(--border);
}

.dev-sheet-footer a {
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
}

.dev-sheet-footer a:hover {
    background: var(--accent);
    color: white;
    border-color: var(--accent);
}

/* ============================================
   RESPONSIVE BREAKPOINTS
   ============================================ */
@media (max-width: 1024px) {
    .dev-layout {
        grid-template-columns: 240px 1fr;
        gap: 20px;
    }
}

@media (max-width: 900px) {
    .dev-layout {
        grid-template-columns: 1fr;
        padding: 0;
        padding-top: 70px;
        padding-bottom: 80px;
    }
    
    .dev-sidebar {
        display: none;
    }
    
    .dev-mobile-header {
        display: block;
    }
    
    .dev-bottom-nav {
        display: block;
    }
    
    .dev-content {
        padding: 16px;
    }
}

@media (max-width: 480px) {
    .dev-sheet-stats {
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        padding: 12px;
        margin: 12px;
    }
    
    .dev-sheet-stat .stat-value {
        font-size: 18px;
    }
}
</style>

<!-- ============================================
     MOBILE HEADER
     ============================================ -->
<div class="dev-mobile-header">
    <div class="dev-mobile-header-content">
        <div class="studio-info">
            <div class="studio-logo">
                <?php if (!empty($dev['logo_url'])): ?>
                    <img src="<?php echo $dev['logo_url']; ?>" alt="Logo">
                <?php else: ?>
                    <i class="fas fa-code"></i>
                <?php endif; ?>
            </div>
            <div>
                <h3><?php echo sanitize($dev['nome_estudio']); ?></h3>
                <p>Painel Dev</p>
            </div>
        </div>
        <button class="dev-mobile-menu-btn" onclick="openDevSheet()">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</div>

<!-- ============================================
     DESKTOP SIDEBAR
     ============================================ -->
<div class="dev-sidebar">
    <div class="dev-sidebar-header">
        <div class="studio-info">
            <div class="studio-logo">
                <?php if (!empty($dev['logo_url'])): ?>
                    <img src="<?php echo $dev['logo_url']; ?>" alt="Logo">
                <?php else: ?>
                    <i class="fas fa-code"></i>
                <?php endif; ?>
            </div>
            <div>
                <h3><?php echo sanitize($dev['nome_estudio']); ?></h3>
                <p>Desenvolvedor</p>
                <?php if ($dev['verificado']): ?>
                    <span class="studio-badge verified">
                        <i class="fas fa-check-circle"></i> Verificado
                    </span>
                <?php else: ?>
                    <span class="studio-badge">
                        <i class="fas fa-clock"></i> <?php echo ucfirst($dev['status']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <ul class="dev-sidebar-menu">
        <span class="dev-sidebar-section-title">Principal</span>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/developer/dashboard.php" 
               class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                Dashboard
            </a>
        </li>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/developer/jogos.php" 
               class="<?php echo $current_page == 'jogos.php' ? 'active' : ''; ?>">
                <i class="fas fa-gamepad"></i>
                Meus Jogos
            </a>
        </li>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/developer/publicar-jogo.php" 
               class="<?php echo $current_page == 'publicar-jogo.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                Publicar Jogo
            </a>
        </li>
        
        <div class="dev-sidebar-divider"></div>
        <span class="dev-sidebar-section-title">Financeiro</span>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/developer/vendas.php" 
               class="<?php echo $current_page == 'vendas.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                Relatório de Vendas
            </a>
        </li>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/developer/saldo.php" 
               class="<?php echo $current_page == 'saldo.php' ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i>
                Saldo e Saques
            </a>
        </li>
        
        <div class="dev-sidebar-divider"></div>
        <span class="dev-sidebar-section-title">Comunidade</span>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/developer/avaliacoes.php" 
               class="<?php echo $current_page == 'avaliacoes.php' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i>
                Avaliações
                <?php if (isset($pending_reviews) && $pending_reviews > 0): ?>
                    <span class="menu-badge"><?php echo $pending_reviews; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <div class="dev-sidebar-divider"></div>
        <span class="dev-sidebar-section-title">Configurações</span>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/developer/perfil.php" 
               class="<?php echo $current_page == 'perfil.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-edit"></i>
                Editar Perfil
            </a>
        </li>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/pages/desenvolvedor.php?slug=<?php echo $dev['slug']; ?>" 
               target="_blank">
                <i class="fas fa-external-link-alt"></i>
                Ver Página Pública
            </a>
        </li>
        
        <div class="dev-sidebar-divider"></div>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/pages/home.php">
                <i class="fas fa-arrow-left"></i>
                Voltar à Loja
            </a>
        </li>
    </ul>
</div>

<!-- ============================================
     MOBILE BOTTOM NAVIGATION
     ============================================ -->
<nav class="dev-bottom-nav">
    <div class="dev-bottom-nav-content">
        <a href="<?php echo SITE_URL; ?>/developer/dashboard.php" 
           class="dev-bottom-nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?php echo SITE_URL; ?>/developer/jogos.php" 
           class="dev-bottom-nav-item <?php echo $current_page == 'jogos.php' ? 'active' : ''; ?>">
            <i class="fas fa-gamepad"></i>
            <span>Jogos</span>
        </a>
        
        <a href="<?php echo SITE_URL; ?>/developer/publicar-jogo.php" 
           class="dev-bottom-nav-item <?php echo $current_page == 'publicar-jogo.php' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Publicar</span>
        </a>
        
        <a href="<?php echo SITE_URL; ?>/developer/vendas.php" 
           class="dev-bottom-nav-item <?php echo $current_page == 'vendas.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Vendas</span>
        </a>
        
        <button class="dev-bottom-nav-item" onclick="openDevSheet()">
            <i class="fas fa-ellipsis-h"></i>
            <span>Mais</span>
        </button>
    </div>
</nav>

<!-- ============================================
     MOBILE BOTTOM SHEET
     ============================================ -->
<div class="dev-sheet-overlay" id="devSheetOverlay" onclick="closeDevSheet()"></div>
<div class="dev-sheet" id="devSheet">
    <div class="dev-sheet-handle" onclick="closeDevSheet()">
        <span></span>
    </div>
    
    <!-- Sheet Header -->
    <div class="dev-sheet-header">
        <div class="studio-logo">
            <?php if (!empty($dev['logo_url'])): ?>
                <img src="<?php echo $dev['logo_url']; ?>" alt="Logo">
            <?php else: ?>
                <i class="fas fa-code"></i>
            <?php endif; ?>
        </div>
        <div>
            <h3><?php echo sanitize($dev['nome_estudio']); ?></h3>
            <p>Painel do Desenvolvedor</p>
            <?php if ($dev['verificado']): ?>
                <span class="badge"><i class="fas fa-check-circle"></i> Verificado</span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <?php if (isset($total_vendas) && isset($total_jogos)): ?>
    <div class="dev-sheet-stats">
        <div class="dev-sheet-stat">
            <div class="stat-value"><?php echo $total_jogos ?? 0; ?></div>
            <div class="stat-label">Jogos</div>
        </div>
        <div class="dev-sheet-stat">
            <div class="stat-value"><?php echo $total_vendas ?? 0; ?></div>
            <div class="stat-label">Vendas</div>
        </div>
        <div class="dev-sheet-stat">
            <div class="stat-value">R$ <?php echo number_format($saldo_disponivel ?? 0, 0, ',', '.'); ?></div>
            <div class="stat-label">Saldo</div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Menu Items -->
    <div class="dev-sheet-menu">
        <div class="dev-sheet-menu-title">Financeiro</div>
        
        <a href="<?php echo SITE_URL; ?>/developer/vendas.php" 
           class="dev-sheet-menu-item <?php echo $current_page == 'vendas.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Relatório de Vendas</span>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
        
        <a href="<?php echo SITE_URL; ?>/developer/saldo.php" 
           class="dev-sheet-menu-item <?php echo $current_page == 'saldo.php' ? 'active' : ''; ?>">
            <i class="fas fa-wallet"></i>
            <span>Saldo e Saques</span>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
        
        <div class="dev-sheet-divider"></div>
        <div class="dev-sheet-menu-title">Comunidade</div>
        
        <a href="<?php echo SITE_URL; ?>/developer/avaliacoes.php" 
           class="dev-sheet-menu-item <?php echo $current_page == 'avaliacoes.php' ? 'active' : ''; ?>">
            <i class="fas fa-comments"></i>
            <span>Avaliações</span>
            <?php if (isset($pending_reviews) && $pending_reviews > 0): ?>
                <span class="item-badge"><?php echo $pending_reviews; ?></span>
            <?php endif; ?>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
        
        <div class="dev-sheet-divider"></div>
        <div class="dev-sheet-menu-title">Configurações</div>
        
        <a href="<?php echo SITE_URL; ?>/developer/perfil.php" 
           class="dev-sheet-menu-item <?php echo $current_page == 'perfil.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-edit"></i>
            <span>Editar Perfil do Estúdio</span>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
        
        <a href="<?php echo SITE_URL; ?>/pages/desenvolvedor.php?slug=<?php echo $dev['slug']; ?>" 
           class="dev-sheet-menu-item" target="_blank">
            <i class="fas fa-external-link-alt"></i>
            <span>Ver Página Pública</span>
            <i class="fas fa-chevron-right chevron"></i>
        </a>
    </div>
    
    <!-- Footer -->
    <div class="dev-sheet-footer">
        <a href="<?php echo SITE_URL; ?>/pages/home.php">
            <i class="fas fa-arrow-left"></i>
            Voltar à Loja
        </a>
    </div>
</div>

<script>
// Dev Bottom Sheet Functions
function openDevSheet() {
    document.getElementById('devSheetOverlay').classList.add('active');
    document.getElementById('devSheet').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDevSheet() {
    document.getElementById('devSheetOverlay').classList.remove('active');
    document.getElementById('devSheet').classList.remove('active');
    document.body.style.overflow = '';
}

// Swipe to close
let devSheetStartY = 0;
const devSheet = document.getElementById('devSheet');

if (devSheet) {
    devSheet.addEventListener('touchstart', (e) => {
        devSheetStartY = e.touches[0].clientY;
    });

    devSheet.addEventListener('touchmove', (e) => {
        const currentY = e.touches[0].clientY;
        const diff = currentY - devSheetStartY;
        
        if (diff > 0 && devSheet.scrollTop === 0) {
            devSheet.style.transform = `translateY(${diff}px)`;
        }
    });

    devSheet.addEventListener('touchend', (e) => {
        const currentY = e.changedTouches[0].clientY;
        const diff = currentY - devSheetStartY;
        
        if (diff > 100) {
            closeDevSheet();
        }
        devSheet.style.transform = '';
    });
}

// Close on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeDevSheet();
    }
});
</script>