// public/js/main.js

// CONTROL 1: Desplegar/Recoger el submenú de Configuración original (Tu código intacto)
function toggleMenu() {
    const submenu = document.getElementById('config-submenu');
    const arrow = document.getElementById('menu-arrow');
    const btn = document.querySelector('.menu-btn');
    
    if (submenu.style.maxHeight && submenu.style.maxHeight !== "0px") {
        submenu.style.maxHeight = "0px";
        arrow.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
    } else {
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
    
    if (vista === 'gestion_usuarios' || vista === 'config_empresa') {
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
