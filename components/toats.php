<?php
/**
 * Componente: Toast
 * Inclua este arquivo no footer para ter o sistema de notificações
 */
?>
<!-- Toast Container será criado via JavaScript -->
<script>
// Inicializar Toast se não existir
if (typeof Toast === 'undefined') {
    console.warn('Toast system not loaded. Include global.js before toast.php');
}
</script>