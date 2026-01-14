/**
 * lexxos GAMES - JavaScript Global
 * Funções globais para carrinho, wishlist, notificações e utilidades
 */

// ============================================
// CONFIGURAÇÃO
// ============================================
const lexxosApp = {
    baseUrl: typeof SITE_URL !== 'undefined' ? SITE_URL : '',
    
    state: {
        cartCount: 0,
        wishlistCount: 0,
        isProcessing: false
    },
    
    init() {
        this.bindEvents();
    },
    
    bindEvents() {
        document.addEventListener('click', (e) => {
            // Botões de carrinho
            const cartBtn = e.target.closest('[data-action="toggle-cart"]');
            if (cartBtn) {
                e.preventDefault();
                e.stopPropagation();
                const jogoId = cartBtn.dataset.jogoId;
                if (jogoId) Cart.toggle(jogoId, cartBtn);
            }
            
            // Botões de wishlist
            const wishBtn = e.target.closest('[data-action="toggle-wishlist"]');
            if (wishBtn) {
                e.preventDefault();
                e.stopPropagation();
                const jogoId = wishBtn.dataset.jogoId;
                if (jogoId) Wishlist.toggle(jogoId, wishBtn);
            }
            
            // Botão limpar carrinho
            const clearBtn = e.target.closest('[data-action="clear-cart"]');
            if (clearBtn) {
                e.preventDefault();
                Cart.clear();
            }
            
            // Fechar toast
            if (e.target.closest('.toast-close')) {
                Toast.hide();
            }
        });
    }
};

// ============================================
// SISTEMA DE NOTIFICAÇÕES (TOAST)
// ============================================
const Toast = {
    container: null,
    timeout: null,
    
    init() {
        if (!this.container) {
            this.createContainer();
        }
    },
    
    createContainer() {
        this.container = document.createElement('div');
        this.container.id = 'toast-container';
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },
    
    show(message, type = 'success', duration = 4000) {
        this.init();
        
        if (this.timeout) {
            clearTimeout(this.timeout);
        }
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        const titles = {
            success: 'Sucesso!',
            error: 'Erro!',
            warning: 'Atenção!',
            info: 'Informação'
        };
        
        this.container.innerHTML = `
            <div class="toast toast-${type} toast-show">
                <div class="toast-icon">
                    <i class="fas ${icons[type] || icons.info}"></i>
                </div>
                <div class="toast-content">
                    <strong class="toast-title">${titles[type] || ''}</strong>
                    <p class="toast-message">${message}</p>
                </div>
                <button class="toast-close" aria-label="Fechar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        this.timeout = setTimeout(() => this.hide(), duration);
    },
    
    hide() {
        if (this.container) {
            const toast = this.container.querySelector('.toast');
            if (toast) {
                toast.classList.remove('toast-show');
                toast.classList.add('toast-hide');
                setTimeout(() => {
                    this.container.innerHTML = '';
                }, 300);
            }
        }
    },
    
    success(message, duration) { this.show(message, 'success', duration); },
    error(message, duration) { this.show(message, 'error', duration); },
    warning(message, duration) { this.show(message, 'warning', duration); },
    info(message, duration) { this.show(message, 'info', duration); }
};

// Função global de compatibilidade
function showNotification(message, type = 'success') {
    Toast.show(message, type);
}

// ============================================
// CARRINHO
// ============================================
const Cart = {
    async toggle(jogoId, button = null) {
        if (lexxosApp.state.isProcessing) return;
        lexxosApp.state.isProcessing = true;
        
        if (button) {
            button.classList.add('loading');
            button.disabled = true;
        }
        
        try {
            const response = await fetch(`${lexxosApp.baseUrl}/api/toggle-cart.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ jogo_id: parseInt(jogoId) })
            });
            
            const data = await response.json();
            
            if (data.require_login) {
                Toast.warning('Faça login para adicionar ao carrinho');
                setTimeout(() => {
                    window.location.href = `${lexxosApp.baseUrl}/auth/login.php`;
                }, 1500);
                return;
            }
            
            if (data.owned) {
                Toast.info(data.message);
                return;
            }
            
            if (data.success) {
                this.updateAllButtons(jogoId, data.in_cart);
                this.updateCounter(data.cart_count);
                Toast.success(data.message);
            } else {
                Toast.error(data.message || 'Erro ao atualizar carrinho');
            }
            
        } catch (error) {
            console.error('Erro:', error);
            Toast.error('Erro de conexão. Tente novamente.');
        } finally {
            lexxosApp.state.isProcessing = false;
            if (button) {
                button.classList.remove('loading');
                button.disabled = false;
            }
        }
    },
    
    updateButton(button, inCart) {
        const icon = button.querySelector('i');
        
        if (button.classList.contains('action-btn')) {
            // Botão ícone circular
            if (inCart) {
                button.classList.add('active');
                button.setAttribute('data-tooltip', 'Remover do carrinho');
                if (icon) icon.className = 'fas fa-check';
            } else {
                button.classList.remove('active');
                button.setAttribute('data-tooltip', 'Adicionar ao carrinho');
                if (icon) icon.className = 'fas fa-shopping-cart';
            }
        } else {
            // Botão com texto
            if (inCart) {
                button.classList.remove('btn-primary');
                button.classList.add('btn-success');
                if (icon) icon.className = 'fas fa-check';
                const textNode = button.childNodes[button.childNodes.length - 1];
                if (textNode && textNode.nodeType === Node.TEXT_NODE) {
                    textNode.textContent = ' No Carrinho';
                }
            } else {
                button.classList.remove('btn-success');
                button.classList.add('btn-primary');
                if (icon) icon.className = 'fas fa-cart-plus';
                const textNode = button.childNodes[button.childNodes.length - 1];
                if (textNode && textNode.nodeType === Node.TEXT_NODE) {
                    textNode.textContent = ' Comprar';
                }
            }
        }
    },
    
    updateAllButtons(jogoId, inCart) {
        document.querySelectorAll(`[data-action="toggle-cart"][data-jogo-id="${jogoId}"]`).forEach(btn => {
            this.updateButton(btn, inCart);
        });
    },
    
    updateCounter(count) {
        lexxosApp.state.cartCount = count;
        document.querySelectorAll('.cart-count, [data-cart-count]').forEach(el => {
            el.textContent = count;
            el.style.display = count > 0 ? 'flex' : 'none';
        });
    },
    
    async clear() {
        if (!confirm('Tem certeza que deseja limpar o carrinho?')) return;
        
        try {
            const response = await fetch(`${lexxosApp.baseUrl}/api/clear-cart.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            const data = await response.json();
            
            if (data.success) {
                Toast.success(data.message);
                this.updateCounter(0);
                setTimeout(() => location.reload(), 1000);
            } else {
                Toast.error(data.message);
            }
        } catch (error) {
            Toast.error('Erro ao limpar carrinho');
        }
    },
    
    async removeItem(jogoId, element = null) {
        try {
            const response = await fetch(`${lexxosApp.baseUrl}/api/toggle-cart.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ jogo_id: parseInt(jogoId) })
            });
            
            const data = await response.json();
            
            if (data.success && data.action === 'removed') {
                if (element) {
                    const item = element.closest('.cart-item, [data-cart-item]');
                    if (item) {
                        item.style.transition = 'all 0.3s ease';
                        item.style.transform = 'translateX(100%)';
                        item.style.opacity = '0';
                        setTimeout(() => {
                            item.remove();
                            this.recalculateTotals();
                        }, 300);
                    }
                }
                
                this.updateCounter(data.cart_count);
                this.updateAllButtons(jogoId, false);
                Toast.success('Item removido do carrinho');
                
                if (data.cart_count === 0) {
                    setTimeout(() => location.reload(), 500);
                }
            }
        } catch (error) {
            Toast.error('Erro ao remover item');
        }
    },
    
    recalculateTotals() {
        if (typeof updateCartTotals === 'function') {
            updateCartTotals();
        }
    }
};

// Funções globais de compatibilidade
function toggleCart(jogoId, button) {
    Cart.toggle(jogoId, button);
}

function clearCart() {
    Cart.clear();
}

function removeFromCart(jogoId, element) {
    Cart.removeItem(jogoId, element);
}

// ============================================
// LISTA DE DESEJOS
// ============================================
const Wishlist = {
    async toggle(jogoId, button = null) {
        if (lexxosApp.state.isProcessing) return;
        lexxosApp.state.isProcessing = true;
        
        if (button) {
            button.classList.add('loading');
            button.disabled = true;
        }
        
        try {
            const response = await fetch(`${lexxosApp.baseUrl}/api/toggle-wishlist.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ jogo_id: parseInt(jogoId) })
            });
            
            const data = await response.json();
            
            if (data.require_login) {
                Toast.warning('Faça login para usar a lista de desejos');
                setTimeout(() => {
                    window.location.href = `${lexxosApp.baseUrl}/auth/login.php`;
                }, 1500);
                return;
            }
            
            if (data.success) {
                this.updateAllButtons(jogoId, data.in_wishlist);
                this.updateCounter(data.wishlist_count);
                Toast.success(data.message);
            } else {
                Toast.error(data.message || 'Erro ao atualizar lista');
            }
            
        } catch (error) {
            console.error('Erro:', error);
            Toast.error('Erro de conexão. Tente novamente.');
        } finally {
            lexxosApp.state.isProcessing = false;
            if (button) {
                button.classList.remove('loading');
                button.disabled = false;
            }
        }
    },
    
    updateButton(button, inWishlist) {
        const icon = button.querySelector('i');
        
        if (inWishlist) {
            button.classList.add('active', 'in-wishlist');
            button.setAttribute('data-tooltip', 'Remover da lista');
            if (icon) icon.className = 'fas fa-heart';
        } else {
            button.classList.remove('active', 'in-wishlist');
            button.setAttribute('data-tooltip', 'Adicionar à lista');
            if (icon) icon.className = 'far fa-heart';
        }
    },
    
    updateAllButtons(jogoId, inWishlist) {
        document.querySelectorAll(`[data-action="toggle-wishlist"][data-jogo-id="${jogoId}"]`).forEach(btn => {
            this.updateButton(btn, inWishlist);
        });
    },
    
    updateCounter(count) {
        lexxosApp.state.wishlistCount = count;
        document.querySelectorAll('.wishlist-count, [data-wishlist-count]').forEach(el => {
            el.textContent = count;
            el.style.display = count > 0 ? 'flex' : 'none';
        });
    },
    
    async remove(jogoId, element = null) {
        try {
            const response = await fetch(`${lexxosApp.baseUrl}/api/toggle-wishlist.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ jogo_id: parseInt(jogoId) })
            });
            
            const data = await response.json();
            
            if (data.success && data.action === 'removed') {
                if (element) {
                    const item = element.closest('.wishlist-item, [data-wishlist-item]');
                    if (item) {
                        item.style.transition = 'all 0.3s ease';
                        item.style.transform = 'translateX(100%)';
                        item.style.opacity = '0';
                        setTimeout(() => item.remove(), 300);
                    }
                }
                
                this.updateCounter(data.wishlist_count);
                Toast.success('Removido da lista de desejos');
            }
        } catch (error) {
            Toast.error('Erro ao remover da lista');
        }
    }
};

// Funções globais de compatibilidade
function toggleWishlist(jogoId, button) {
    Wishlist.toggle(jogoId, button);
}

function removeFromWishlist(jogoId, element) {
    Wishlist.remove(jogoId, element);
}

// ============================================
// CUPONS
// ============================================
const Coupon = {
    async apply(codigo) {
        if (!codigo || codigo.trim() === '') {
            Toast.warning('Digite um código de cupom');
            return;
        }
        
        try {
            const response = await fetch(`${lexxosApp.baseUrl}/api/apply-coupon.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ codigo: codigo.trim() })
            });
            
            const data = await response.json();
            
            if (data.success) {
                Toast.success(data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                Toast.error(data.message);
            }
        } catch (error) {
            Toast.error('Erro ao aplicar cupom');
        }
    },
    
    async remove() {
        try {
            const response = await fetch(`${lexxosApp.baseUrl}/api/remove-coupon.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            const data = await response.json();
            
            if (data.success) {
                Toast.success(data.message);
                setTimeout(() => location.reload(), 1000);
            }
        } catch (error) {
            Toast.error('Erro ao remover cupom');
        }
    }
};

// ============================================
// UTILIDADES
// ============================================
const Utils = {
    formatPrice(centavos) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(centavos / 100);
    },
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    async copyToClipboard(text, successMessage = 'Copiado!') {
        try {
            await navigator.clipboard.writeText(text);
            Toast.success(successMessage);
            return true;
        } catch (error) {
            Toast.error('Erro ao copiar');
            return false;
        }
    }
};

// ============================================
// INICIALIZAÇÃO
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    lexxosApp.init();
});

// Expor para uso global
window.lexxosApp = lexxosApp;
window.Toast = Toast;
window.Cart = Cart;
window.Wishlist = Wishlist;
window.Coupon = Coupon;
window.Utils = Utils;