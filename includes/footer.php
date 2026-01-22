</main>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <!-- Sobre -->
            <div class="footer-section">
                <h4><?php echo SITE_LOGO, SITE_NAME; ?></h4>
                <p>A maior plataforma de jogos indies do Brasil. Descubra, compre e jogue os melhores jogos independentes.</p>
                <div class="footer-social">
                    <a href="#" title="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="Discord"><i class="fab fa-discord"></i></a>
                    <a href="#" title="YouTube"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <!-- Links Rápidos -->
            <div class="footer-section">
                <h4>Links Rápidos</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo SITE_URL; ?>/pages/home.php">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/pages/busca.php">Explorar Jogos</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/pages/faq.php">FAQ</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/user/seja-dev.php">Seja um Desenvolvedor</a></li>
                </ul>
            </div>

            <!-- Suporte -->
            <div class="footer-section">
                <h4>Suporte</h4>
                <ul class="footer-links">
                    <li><a href="#">Central de Ajuda</a></li>
                    <li><a href="#">Contato</a></li>
                    <li><a href="#">Política de Reembolso</a></li>
                    <li><a href="#">Status do Sistema</a></li>
                </ul>
            </div>

            <!-- Legal -->
            <div class="footer-section">
                <h4>Legal</h4>
                <ul class="footer-links">
                    <li><a href="#">Termos de Uso</a></li>
                    <li><a href="#">Política de Privacidade</a></li>
                    <li><a href="#">Cookies</a></li>
                    <li><a href="#">Diretrizes da Comunidade</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos os direitos reservados.</p>

        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="<?php echo SITE_URL; ?>/assets/js/navigation.js"></script>
<script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
<script src="<?php echo SITE_URL; ?>/assets/js/theme.js"></script>
<!-- JavaScript Global -->
<script>
    const SITE_URL = '<?php echo SITE_URL; ?>';
</script>
<script src="<?php echo SITE_URL; ?>/assets/js/global.js"></script>
<script src="<?= SITE_URL ?>/assets/js/game-cards.js"></script>
<?php require_once BASE_PATH . '/components/toast.php'; ?>

</body>

</html>