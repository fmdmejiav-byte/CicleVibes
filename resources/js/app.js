import './bootstrap';

import Alpine from 'alpinejs';

import './map';

window.Alpine = Alpine;

Alpine.start();

// Oculta el splash de carga en cuanto la página termina de cargar
// (con un fallback por si 'load' tarda demasiado).
function hideSplash() {
    const splash = document.getElementById('app-splash');
    if (!splash) return;
    splash.classList.add('app-splash--hidden');
    setTimeout(() => splash.remove(), 600);
}

window.addEventListener('load', hideSplash);
setTimeout(hideSplash, 4000);
