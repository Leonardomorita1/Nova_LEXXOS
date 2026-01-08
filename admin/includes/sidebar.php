<?php
// admin/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
/* Estrutura do Layout Admin */
.admin-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 30px;
    padding: 30px 0;
}

/* Sidebar Estilo Card */
.admin-sidebar {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 25px;
    height: fit-content;
    position: sticky;
    top: 90px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.admin-sidebar-header {
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 20px;
}

.admin-sidebar-header h3 {
    font-size: 18px;
    margin-bottom: 5px;
    color: var(--text-primary);
    font-weight: 700;
}

.admin-sidebar-header p {
    font-size: 13px;
    color: var(--accent);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.admin-sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.admin-sidebar-menu li {
    margin-bottom: 5px;
}

.admin-sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: var(--text-secondary);
    border-radius: 10px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 14px;
    text-decoration: none;
}

.admin-sidebar-menu a:hover {
    background: var(--bg-primary);
    color: var(--text-primary);
    transform: translateX(5px);
}

.admin-sidebar-menu a.active {
    background: var(--accent);
    color: #fff !important;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(var(--accent-rgb), 0.3);
}

.admin-sidebar-menu i {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.admin-sidebar-divider {
    height: 1px;
    background: var(--border);
    margin: 15px 0;
}

.admin-sidebar-label {
    display: block;
    padding: 10px 15px;
    font-size: 11px;
    text-transform: uppercase;
    color: var(--text-secondary);
    letter-spacing: 1px;
    font-weight: 800;
    opacity: 0.6;
}

.logout-item a:hover {
    background: rgba(255, 68, 68, 0.1) !important;
    color: #ff4444 !important;
}

@media (max-width: 992px) {
    .admin-layout {
        grid-template-columns: 1fr;
    }
    .admin-sidebar {
        position: static;
        margin-bottom: 20px;
    }
}
</style>

<div class="admin-sidebar">
    <div class="admin-sidebar-header">
        <h3><?= sanitize($usuario['nome'] ?? 'Administrador'); ?></h3>
        <p><i class="fas fa-shield-alt"></i> Painel de Controle</p>
    </div>
    
    <ul class="admin-sidebar-menu">
        <li>
            <a href="<?= SITE_URL; ?>/admin/dashboard.php" 
               class="<?= $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
        </li>

        <small class="admin-sidebar-label">Loja e Vendas</small>
        <li>
            <a href="<?= SITE_URL; ?>/admin/pedidos.php" 
               class="<?= $current_page == 'pedidos.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i> Gerenciar Pedidos
            </a>
        </li>
        <li>
            <a href="<?= SITE_URL; ?>/admin/promocoes.php" 
               class="<?= $current_page == 'promocoes.php' ? 'active' : ''; ?>">
                <i class="fas fa-percentage"></i> Promoções
            </a>
        </li>
        <li>
            <a href="<?= SITE_URL; ?>/admin/cupons.php" 
               class="<?= $current_page == 'cupons.php' ? 'active' : ''; ?>">
                <i class="fas fa-ticket-alt"></i> Cupons de Desconto
            </a>
        </li>

        <div class="admin-sidebar-divider"></div>
        <small class="admin-sidebar-label">Suporte e Comunicação</small>
        <li>
            <a href="<?= SITE_URL; ?>/admin/tickets.php" 
               class="<?= ($current_page == 'tickets.php' || $current_page == 'ticket-detalhes.php') ? 'active' : ''; ?>">
                <i class="fas fa-headset"></i> Tickets de Suporte
            </a>
        </li>
        <li>
            <a href="<?= SITE_URL; ?>/admin/banners.php" 
               class="<?= $current_page == 'banners.php' ? 'active' : ''; ?>">
                <i class="fas fa-images"></i> Banners da Home
            </a>
        </li>
        <li>
            <a href="<?= SITE_URL; ?>/admin/faqs.php" 
               class="<?= $current_page == 'faqs.php' ? 'active' : ''; ?>">
                <i class="fas fa-question-circle"></i> Gerenciar FAQs
            </a>
        </li>

        <div class="admin-sidebar-divider"></div>
        <small class="admin-sidebar-label">Configurações</small>
        <li>
            <a href="<?= SITE_URL; ?>/admin/logs.php" 
               class="<?= $current_page == 'logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-terminal"></i> Logs do Sistema
            </a>
        </li>
        <li>
            <a href="<?= SITE_URL; ?>/admin/configuracoes.php" 
               class="<?= $current_page == 'configuracoes.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Configurações
            </a>
        </li>

        <div class="admin-sidebar-divider"></div>
        <li class="logout-item">
            <a href="<?= SITE_URL; ?>/auth/logout.php">
                <i class="fas fa-sign-out-alt"></i> Sair do Painel
            </a>
        </li>
    </ul>
</div>