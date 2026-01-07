<?php
// developer/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
.dev-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 30px;
    padding: 30px 0;
}

.dev-sidebar {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 25px;
    height: fit-content;
    position: sticky;
    top: 90px;
}

.dev-sidebar-header {
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 20px;
}

.dev-sidebar-header h3 {
    font-size: 18px;
    margin-bottom: 5px;
}

.dev-sidebar-header p {
    font-size: 13px;
    color: var(--text-secondary);
}

.dev-sidebar-menu {
    list-style: none;
}

.dev-sidebar-menu li {
    margin-bottom: 5px;
}

.dev-sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: var(--text-primary);
    border-radius: 8px;
    transition: all 0.3s;
    font-size: 14px;
}

.dev-sidebar-menu a:hover {
    background: var(--bg-primary);
}

.dev-sidebar-menu a.active {
    background: var(--accent);
    color: white;
}

.dev-sidebar-menu i {
    width: 20px;
    text-align: center;
}

.dev-sidebar-divider {
    height: 1px;
    background: var(--border);
    margin: 15px 0;
}

@media (max-width: 992px) {
    .dev-layout {
        grid-template-columns: 1fr;
    }
    
    .dev-sidebar {
        position: static;
    }
}
</style>

<div class="dev-sidebar">
    <div class="dev-sidebar-header">
        <h3><?php echo sanitize($dev['nome_estudio']); ?></h3>
        <p>Painel do Desenvolvedor</p>
    </div>
    
    <ul class="dev-sidebar-menu">
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
    </ul>
</div>