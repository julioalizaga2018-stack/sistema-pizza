// CONTROL 1: Desplegar/Recoger el submenú de Configuración de forma elástica
function toggleMenu() {
    const submenu = document.getElementById('config-submenu');
    const arrow = document.getElementById('menu-arrow');
    const btn = document.querySelector('.menu-btn');
    
    if (submenu.style.maxHeight && submenu.style.maxHeight !== "0px") {
        submenu.style.maxHeight = "0px";
        arrow.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
    } else {
        // scrollHeight calcula en píxeles el alto real dinámico del contenido
        submenu.style.maxHeight = submenu.scrollHeight + "px";
        arrow.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
    }
}

// CONTROL 2: Mostrar/Ocultar el Sidebar completo en celulares (Hamburguesa)
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar-menu');
    const overlay = document.querySelector('.sidebar-overlay');
    
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}

// INTELIGENCIA DE PERSISTENCIA: Deja abierto el menú si está en la vista correspondiente
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const vista = urlParams.get('v');
    
    // Si la URL actual es index.php?v=usuarios, forzamos la apertura automática
    if (vista === 'usuarios') {
        const submenu = document.getElementById('config-submenu');
        const arrow = document.getElementById('menu-arrow');
        const btn = document.querySelector('.menu-btn');
        
        if (submenu && arrow && btn) {
            submenu.style.maxHeight = submenu.scrollHeight + "px";
            arrow.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
        }
    }
});