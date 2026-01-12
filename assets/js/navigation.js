// ============================================
// NAVIGATION SYSTEM - JavaScript
// ============================================

// User Dropdown (Desktop)
function toggleUserDropdown() {
    const dropdown = document.querySelector('.user-dropdown');
    dropdown.classList.toggle('active');
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    const dropdown = document.querySelector('.user-dropdown');
    if (dropdown && !dropdown.contains(e.target)) {
        dropdown.classList.remove('active');
    }
});

// ============================================
// BOTTOM SHEET
// ============================================
function openBottomSheet() {
    document.getElementById('bottomSheetOverlay').classList.add('active');
    document.getElementById('bottomSheet').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeBottomSheet() {
    document.getElementById('bottomSheetOverlay').classList.remove('active');
    document.getElementById('bottomSheet').classList.remove('active');
    document.body.style.overflow = '';
}

// Touch to close (swipe down)
let touchStartY = 0;
const bottomSheet = document.getElementById('bottomSheet');

if (bottomSheet) {
    bottomSheet.addEventListener('touchstart', (e) => {
        touchStartY = e.touches[0].clientY;
    }, { passive: true });

    bottomSheet.addEventListener('touchmove', (e) => {
        const touchY = e.touches[0].clientY;
        const diff = touchY - touchStartY;
        
        // If scrolled to top and swiping down
        if (bottomSheet.scrollTop === 0 && diff > 0) {
            bottomSheet.style.transform = `translateY(${diff}px)`;
        }
    }, { passive: true });

    bottomSheet.addEventListener('touchend', (e) => {
        const touchY = e.changedTouches[0].clientY;
        const diff = touchY - touchStartY;
        
        if (diff > 100) {
            closeBottomSheet();
        }
        bottomSheet.style.transform = '';
    }, { passive: true });
}

// ============================================
// SEARCH PANEL
// ============================================
function openSearchPanel() {
    const panel = document.getElementById('searchPanel');
    panel.classList.add('active');
    panel.querySelector('input').focus();
    document.body.style.overflow = 'hidden';
}

function closeSearchPanel() {
    document.getElementById('searchPanel').classList.remove('active');
    document.body.style.overflow = '';
}

// Close search panel with back button
window.addEventListener('popstate', () => {
    const searchPanel = document.getElementById('searchPanel');
    if (searchPanel && searchPanel.classList.contains('active')) {
        closeSearchPanel();
    }
});

// ============================================
// THEME TOGGLE
// ============================================
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Update via AJAX if logged in
    fetch(window.SITE_URL + '/api/update-theme.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: newTheme })
    }).catch(() => {});
    
    updateThemeUI();
}

function updateThemeUI() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    
    // Desktop
    const themeIcon = document.getElementById('theme-icon');
    const themeText = document.getElementById('theme-text');
    if (themeIcon) themeIcon.className = isDark ? 'fas fa-moon' : 'fas fa-sun';
    if (themeText) themeText.textContent = isDark ? 'Modo Claro' : 'Modo Escuro';
    
    // Mobile
    const mobileIcon = document.getElementById('mobile-theme-icon');
    const mobileText = document.getElementById('mobile-theme-text');
    if (mobileIcon) mobileIcon.className = isDark ? 'fas fa-moon' : 'fas fa-sun';
    if (mobileText) mobileText.textContent = isDark ? 'Modo Claro' : 'Modo Escuro';
    
    // Guest mobile
    const guestIcon = document.getElementById('mobile-theme-icon-guest');
    const guestText = document.getElementById('mobile-theme-text-guest');
    if (guestIcon) guestIcon.className = isDark ? 'fas fa-moon' : 'fas fa-sun';
    if (guestText) guestText.textContent = isDark ? 'Modo Claro' : 'Modo Escuro';
}

// Initialize theme UI on load
document.addEventListener('DOMContentLoaded', updateThemeUI);

// ============================================
// ACTIVE NAV ITEM HIGHLIGHT
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    // Haptic feedback simulation for mobile
    const navItems = document.querySelectorAll('.bottom-nav-item');
    navItems.forEach(item => {
        item.addEventListener('touchstart', () => {
            if (navigator.vibrate) {
                navigator.vibrate(10);
            }
        }, { passive: true });
    });
});