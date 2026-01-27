<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>DuoFund - Finanças a Dois</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2674D9',
                        secondary: '#4184DD',
                        accent: '#E2B93B',
                    }
                }
            }
        }
    </script>
    
    <script src="https://unpkg.com/lucide@latest"></script>

    @livewireStyles

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fb; }
        
        .modal-overlay { visibility: hidden; opacity: 0; transition: opacity 0.3s, visibility 0.3s; }
        .modal-overlay.active { visibility: visible; opacity: 1; }
        .modal-content { transform: translateY(20px); transition: transform 0.3s ease-out; }
        .modal-overlay.active .modal-content { transform: translateY(0); }

        .nav-btn.active { color: white !important; background-color: #2674D9 !important; font-weight: 600; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) inset; }
        .nav-btn { color: #4b5563; background-color: transparent; padding: 8px 12px; border-radius: 0.5rem; transition: background-color 0.15s, color 0.15s; }
        .nav-btn:hover:not(.active) { color: #2674D9; background-color: #eff6ff; }
        
        /* Safe area para iPhones com notch */
        .safe-area-bottom { padding-bottom: env(safe-area-inset-bottom, 0); }
        
        [x-cloak] { display: none !important; }
        
        /* Loading animation */
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 1; }
            50% { transform: scale(1); opacity: 0.5; }
            100% { transform: scale(0.8); opacity: 1; }
        }
        .loading-pulse { animation: pulse-ring 1.2s ease-in-out infinite; }
    </style>
</head>
@php
    $currentScope = session('view_mode', 'personal');
@endphp
<body class="min-h-screen flex flex-col bg-gray-50" 
      x-data="{ 
          transactionModalOpen: false, 
          categoryModalOpen: false, 
          goalModalOpen: false,
          toastMessage: '',
          currentScope: '{{ $currentScope }}',
          showToast(msg) {
              this.toastMessage = msg;
              setTimeout(() => this.toastMessage = '', 3000);
          }
      }"
      @notify.window="showToast($event.detail)"
      @scope-changed.window="currentScope = $event.detail"

    {{-- Header Desktop e Mobile --}}
    <header class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-40">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-6">
                <a href="{{ route('dashboard') }}" wire:navigate class="text-xl font-extrabold text-gray-800 flex items-center">
                    <span class="text-primary">Duo</span>Fund <span class="hidden sm:inline text-sm font-normal text-gray-500 ml-2 border-l pl-2">Juntos</span>
                </a>
                
                <nav class="hidden lg:flex space-x-1">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <button class="nav-btn flex items-center {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2"></i>Painel
                        </button>
                    </a>
                    <a href="{{ route('expenses') }}" wire:navigate>
                        <button class="nav-btn flex items-center {{ request()->routeIs('expenses') ? 'active' : '' }}">
                            <i data-lucide="arrow-left-right" class="w-4 h-4 mr-2"></i>Transações
                        </button>
                    </a>
                    <a href="{{ route('budget') }}" wire:navigate>
                        <button class="nav-btn flex items-center {{ request()->routeIs('budget') ? 'active' : '' }}">
                            <i data-lucide="pie-chart" class="w-4 h-4 mr-2"></i>Orçamento
                        </button>
                    </a>
                    <a href="{{ route('goals') }}" wire:navigate>
                        <button class="nav-btn flex items-center {{ request()->routeIs('goals') ? 'active' : '' }}">
                            <i data-lucide="target" class="w-4 h-4 mr-2"></i>Metas
                        </button>
                    </a>
                </nav>
            </div>

            <div class="flex items-center space-x-3">
                <a href="{{ route('help') }}" wire:navigate class="flex items-center text-gray-500 hover:text-primary transition p-2 rounded-lg hover:bg-gray-50 {{ request()->routeIs('help') ? 'text-primary bg-primary/5' : '' }}" title="Como usar o DuoFund">
                    <i data-lucide="help-circle" class="w-5 h-5"></i>
                </a>
                <div class="flex -space-x-2">
                    @if(auth()->user()->family)
                        @foreach(auth()->user()->family->users as $member)
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-xs shadow-md ring-2 ring-white {{ $member->id === auth()->id() ? 'bg-primary' : 'bg-secondary' }}" 
                                 title="{{ $member->name }}">
                                {{ substr($member->name, 0, 1) }}
                            </div>
                        @endforeach
                    @else
                        <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white font-bold text-xs shadow-md">
                            {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </header>

    {{-- Menu Mobile Fixo no Bottom --}}
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-40 safe-area-bottom">
        <div class="flex justify-around py-2 px-2">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg {{ request()->routeIs('dashboard') ? 'text-primary bg-primary/5' : 'text-gray-500' }}">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span class="text-[10px] font-medium">Painel</span>
            </a>
            <a href="{{ route('expenses') }}" wire:navigate class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg {{ request()->routeIs('expenses') ? 'text-primary bg-primary/5' : 'text-gray-500' }}">
                <i data-lucide="arrow-left-right" class="w-5 h-5"></i>
                <span class="text-[10px] font-medium">Transações</span>
            </a>
            <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: currentScope })" class="flex flex-col items-center -mt-5">
                <div class="w-14 h-14 bg-primary rounded-full flex items-center justify-center shadow-lg shadow-primary/40 border-4 border-white">
                    <i data-lucide="plus" class="w-7 h-7 text-white"></i>
                </div>
            </button>
            <a href="{{ route('budget') }}" wire:navigate class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg {{ request()->routeIs('budget') ? 'text-primary bg-primary/5' : 'text-gray-500' }}">
                <i data-lucide="pie-chart" class="w-5 h-5"></i>
                <span class="text-[10px] font-medium">Orçamento</span>
            </a>
            <a href="{{ route('goals') }}" wire:navigate class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg {{ request()->routeIs('goals') ? 'text-primary bg-primary/5' : 'text-gray-500' }}">
                <i data-lucide="target" class="w-5 h-5"></i>
                <span class="text-[10px] font-medium">Metas</span>
            </a>
        </div>
    </nav>

    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-24 lg:pb-8 flex-grow">
        {{ $slot }}
    </main>

    <footer class="hidden lg:block bg-white border-t border-gray-200 py-4 mt-auto">
        <div class="container mx-auto px-4 text-center sm:text-left text-xs text-gray-500 flex flex-col sm:flex-row justify-between">
            <p>© 2025 DuoFund. Gerenciando finanças juntos.</p>
            <div class="space-x-4 mt-2 sm:mt-0">
                <a href="{{ route('help') }}" wire:navigate class="hover:text-primary">Como usar</a>
                <a href="#" class="hover:text-primary">Privacidade</a>
                <a href="#" class="hover:text-primary">Termos</a>
            </div>
        </div>
    </footer>

    <div x-show="toastMessage" x-transition x-cloak
         class="fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-3 rounded-lg shadow-lg z-50" 
         x-text="toastMessage"></div>

    {{-- Loading overlay para navegação entre páginas --}}
    <div id="page-loading" 
         class="fixed inset-0 z-[100] flex items-center justify-center bg-white/95 backdrop-blur-sm transition-opacity duration-300">
        <div class="flex flex-col items-center">
            <div class="w-14 h-14 bg-primary rounded-full flex items-center justify-center shadow-lg shadow-primary/30 loading-pulse">
                <svg class="w-7 h-7 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <p class="mt-4 text-sm font-medium text-gray-600">Carregando...</p>
        </div>
    </div>

    <livewire:components.transaction-modal />
    <livewire:components.category-modal />
    <livewire:components.goal-modal />
    <livewire:components.deposit-goal-modal />

    @livewireScripts

    <script>
        function refreshIcons() {
            if (window.lucide) {
                lucide.createIcons();
            }
        }
        refreshIcons();
        document.addEventListener('livewire:navigated', refreshIcons);
        document.addEventListener('livewire:init', () => {
            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => setTimeout(refreshIcons, 50));
            });
        });

        // Loading entre páginas
        (function() {
            const loader = document.getElementById('page-loading');
            
            function showLoader() {
                loader.style.display = 'flex';
                loader.style.opacity = '1';
                loader.style.pointerEvents = 'auto';
            }
            
            function hideLoader() {
                loader.style.opacity = '0';
                loader.style.pointerEvents = 'none';
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 300);
            }
            
            // Esconde o loading quando a página termina de carregar
            hideLoader();
            
            // Mostra loading ao navegar
            document.addEventListener('livewire:navigate', showLoader);
            document.addEventListener('livewire:navigated', hideLoader);
            
            // Também escuta cliques em links com wire:navigate
            document.addEventListener('click', function(e) {
                const link = e.target.closest('a[wire\\:navigate]');
                if (link && link.href !== window.location.href) {
                    showLoader();
                }
            });
        })();
    </script>
</body>
</html>