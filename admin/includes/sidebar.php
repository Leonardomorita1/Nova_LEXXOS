<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
.admin-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 30px;
    padding: 30px 0;
}

.admin-sidebar {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0;
    height: fit-content;
    position: sticky;
    top: 90px;
    overflow: hidden;
}

.admin-nav {
    list-style: none;
    margin: 0;
    padding: 0;
}

.admin-nav-item {
    border-bottom: 1px solid var(--border);
}

.admin-nav-item:last-child {
    border-bottom: none;
}

.admin-nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
}

.admin-nav-link:hover {
    background: var(--bg-primary);
    color: var(--text-primary);
}

.admin-nav-link.active {
    background: linear-gradient(90deg, rgba(76, 139, 245, 0.15) 0%, transparent 100%);
    color: var(--accent);
    border-left: 3px solid var(--accent);
}

.admin-nav-link i {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.admin-nav-section {
    padding: 12px 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-secondary);
    letter-spacing: 0.5px;
    background: var(--bg-primary);
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
    <ul class="admin-nav">
        <li class="admin-nav-item">
            <a href="<?= SITE_URL ?>/admin/dashboard.php" 
               class="admin-nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </a>
        </li>
        
        <li class="admin-nav-section">Conteúdo</li>
        
        <li class="admin-nav-item">
            <a href="<?= SITE_URL ?>/admin/jogos.php" 
               class="admin-nav-link <?= $current_page == 'jogos.php' ? 'active' : '' ?>">
                <i class="fas fa-gamepad"></i>
                Jogos
            </a>
        </li>
        
        <li class="admin-nav-item">
            <a href="<?= SITE_URL ?>/admin/categorias.php" 
               class="admin-nav-link <?= $current_page == 'categorias.php' ? 'active' : '' ?>">
                <i class="fas fa-folder"></i>
                Categorias
            </a>
        </li>
        
        <li class="admin-nav-item">
            <a href="<?= SITE_URL ?>/admin/tags.php" 
               class="admin-nav-link <?= $current_page == 'tags.php' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i>
                Tags
            </a>
        </li>
        
        <li class="admin-nav-section">Usuários</li>
        
        <li class="admin-nav-item">
            <a href="<?= SITE_URL ?>/admin/usuarios.php" 
               class="admin-nav-link <?= $current_page == 'usuarios.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                Usuários
            </a>
        </li>
        
        <li class="admin-nav-item">
            <a href="<?= SITE_URL ?>/admin/desenvolvedores.php" 
               class="admin-nav-link <?= $current_page == 'desenvolvedores.php' ? 'active' : '' ?>">
                <i class="fas fa-code"></i>
                Desenvolvedores
            </a>
        </li>
        
        <li class="admin-nav-section">Vendas</li>
        
        <li class="admin-nav-item">
            <a href="<?= SITE_URL ?>/admin/pedidos.php" 
               class="admin-nav-link <?= $current_page == 'pedidos.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i>
                Pedidos
            </a>
        </li>
        
        <li class="admin-nav-item">
            <a href="<?= SITE_URL ?>/admin/promocoes.php" 
               class="admin-nav-link <?= $current_page == 'promocoes.php' ? 'active' : '' ?>">
                <i class="fas fa-percentage"></i>
                Promoções
            </a>
        </li>
        
        <li class="admin-nav-section">Suporte</li>
        
        <li class="admin-nav-item">
            <a href="<?= SITE_URL ?>/admin/tickets.php" 
               class="admin-nav-link <?= $current_page == 'tickets.php' ? 'active' : '' ?>">
                <i class="fas fa-life-ring"></i>
                Tickets
            </a>
        </li>
        
        <li class="admin-nav-section">Configurações</li>
        
        <li class="admin-nav-item">
            <a href="<?= SITE_URL ?>/admin/configuracoes.php" 
               class="admin-nav-link <?= $current_page == 'configuracoes.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                Configurações
            </a>
        </li>
    </ul>
</div>