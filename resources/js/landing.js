import Alpine from 'alpinejs';

// Landing e telas de auth não carregam Livewire, então precisam do próprio
// Alpine. No app autenticado ele já vem junto do Livewire — não incluir aqui.
window.Alpine = Alpine;

Alpine.start();
