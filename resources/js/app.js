import './bootstrap';
import Alpine from 'alpinejs';
import echo from './echo';

// Make Echo globally available for inline Blade scripts
window.Echo = echo;

// Start Alpine.js
window.Alpine = Alpine;
Alpine.start();

console.debug('[App] Alpine.js started');
