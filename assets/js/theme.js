// assets/js/theme.js

// Função auxiliar para atualizar o texto do botão
function updateThemeText(theme) {
    const themeText = document.getElementById('theme-text');
    if (themeText) {
        // Se for dark, o texto sugere mudar para claro (ou mostra o estado atual)
        // Ajuste o texto conforme sua preferência de UI
        themeText.textContent = theme === 'dark' ? 'Modo Claro' : 'Modo Escuro';
    }
}

// Toggle theme
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    // 1. Aplica o novo tema no HTML
    html.setAttribute('data-theme', newTheme);
    
    // 2. SALVA NO LOCALSTORAGE (Isso resolve o seu problema)
    localStorage.setItem('theme', newTheme);
    
    // 3. Atualiza o texto
    updateThemeText(newTheme);
    
    // 4. Salva no banco de dados (Opcional, mantido do seu código original)
    fetch('/lexxos/api/save-theme.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ theme: newTheme })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Tema sincronizado com o servidor:', newTheme);
        }
    })
    .catch(error => {
        console.error('Erro ao salvar tema no servidor:', error);
    });
}

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const html = document.documentElement;
    
    // 1. VERIFICA SE JÁ EXISTE UM TEMA SALVO NO NAVEGADOR
    const savedTheme = localStorage.getItem('theme');
    
    // Se tiver salvo, usa ele. Se não, usa o que estiver no HTML ou padrão 'light'
    const currentTheme = savedTheme || html.getAttribute('data-theme') || 'light';
    
    // Aplica o tema recuperado
    html.setAttribute('data-theme', currentTheme);
    
    // Atualiza o texto do botão para corresponder ao tema carregado
    updateThemeText(currentTheme);
});