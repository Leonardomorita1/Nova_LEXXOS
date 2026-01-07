// assets/js/cart.js

function toggleCart(jogoId, button) {
    const isInCart = button.classList.contains('in-cart');
    const action = isInCart ? 'remove' : 'add';
    
    fetch(`/api/${action}-cart.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ jogo_id: jogoId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Sucesso!', 'success');
            
            // Aguarda 1 segundo para o usuário ler a mensagem e recarrega
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Erro ao atualizar carrinho', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro de conexão com o servidor', 'error');
    });
}

function toggleWishlist(jogoId, button) {
    const isInWishlist = button.classList.contains('active');
    const action = isInWishlist ? 'remove' : 'add';
    
    fetch(`/api/${action}-wishlist.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ jogo_id: jogoId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Lista de desejos atualizada!', 'success');
            
            // Recarrega a página para atualizar os ícones de coração em todo o site
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Erro ao atualizar lista de desejos', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro de conexão com o servidor', 'error');
    });
}