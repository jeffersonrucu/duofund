<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>DuoFund - Finanças a Dois</title>
    @include('partials.favicon')

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
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap');
        body { font-family: 'DM Sans', sans-serif; background-color: #eef2f7; }

        .modal-overlay { visibility: hidden; opacity: 0; transition: opacity 0.3s, visibility 0.3s; }
        .modal-overlay.active { visibility: visible; opacity: 1; }
        .modal-content { transform: translateY(20px); transition: transform 0.3s ease-out; }
        .modal-overlay.active .modal-content { transform: translateY(0); }

        .nav-btn { color: #6b7280; background-color: transparent; padding: 7px 12px; border-radius: 0.625rem; transition: background-color 0.15s, color 0.15s; font-weight: 500; display: flex; align-items: center; gap: 6px; }
        .nav-btn.active { color: #2674D9 !important; background-color: #dbeafe !important; font-weight: 600; }
        .nav-btn:hover:not(.active) { color: #2674D9; background-color: #eff6ff; }

        .safe-area-bottom { padding-bottom: env(safe-area-inset-bottom, 0); }
        [x-cloak] { display: none !important; }

        @keyframes pulse-ring {
            0% { transform: scale(0.88); opacity: 1; }
            50% { transform: scale(1.04); opacity: 0.5; }
            100% { transform: scale(0.88); opacity: 1; }
        }
        .loading-pulse { animation: pulse-ring 1.4s ease-in-out infinite; }
    </style>
</head>
@php
    $currentScope = session('view_mode', 'personal');
@endphp
<body class="min-h-screen flex flex-col"
      x-data="{
          transactionModalOpen: false,
          categoryModalOpen: false,
          goalModalOpen: false,
          mobileMenuOpen: false,
          toastMessage: '',
          currentScope: '{{ $currentScope }}',
          showToast(msg) {
              this.toastMessage = msg;
              setTimeout(() => this.toastMessage = '', 3000);
          }
      }"
      @notify.window="showToast($event.detail)"
      @scope-changed.window="currentScope = $event.detail">

    {{-- Header --}}
    <header class="bg-white border-b border-gray-200/80 sticky top-0 z-40">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-5">
                {{-- Logo --}}
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5 flex-shrink-0">
                    <div class="w-8 h-8 bg-primary rounded-xl flex items-center justify-center shadow-md shadow-primary/25 flex-shrink-0">
                        <span class="text-white font-black text-xs tracking-tighter leading-none">DF</span>
                    </div>
                    <span class="text-[17px] font-bold text-gray-900 tracking-tight leading-none"><span class="text-primary">Duo</span>Fund</span>
                </a>

                <nav class="hidden lg:flex items-center space-x-0.5">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <button class="nav-btn {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i data-lucide="layout-dashboard" class="w-4 h-4"></i>Painel
                        </button>
                    </a>
                    <a href="{{ route('expenses') }}" wire:navigate>
                        <button class="nav-btn {{ request()->routeIs('expenses') ? 'active' : '' }}">
                            <i data-lucide="arrow-left-right" class="w-4 h-4"></i>Transações
                        </button>
                    </a>
                    <a href="{{ route('installments') }}" wire:navigate>
                        <button class="nav-btn {{ request()->routeIs('installments') ? 'active' : '' }}">
                            <i data-lucide="credit-card" class="w-4 h-4"></i>Parcelas
                        </button>
                    </a>
                    <a href="{{ route('budget') }}" wire:navigate>
                        <button class="nav-btn {{ request()->routeIs('budget') ? 'active' : '' }}">
                            <i data-lucide="pie-chart" class="w-4 h-4"></i>Orçamento
                        </button>
                    </a>
                    <a href="{{ route('goals') }}" wire:navigate>
                        <button class="nav-btn {{ request()->routeIs('goals') ? 'active' : '' }}">
                            <i data-lucide="target" class="w-4 h-4"></i>Metas
                        </button>
                    </a>
                    <a href="{{ route('wishlist') }}" wire:navigate>
                        <button class="nav-btn {{ request()->routeIs('wishlist') ? 'active' : '' }}">
                            <i data-lucide="gift" class="w-4 h-4"></i>Desejos
                        </button>
                    </a>
                    <a href="{{ route('report') }}" wire:navigate>
                        <button class="nav-btn {{ request()->routeIs('report') ? 'active' : '' }}">
                            <i data-lucide="bar-chart-3" class="w-4 h-4"></i>Relatório
                        </button>
                    </a>
                </nav>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('help') }}" wire:navigate
                   class="p-2 rounded-lg text-gray-400 hover:text-primary hover:bg-blue-50 transition {{ request()->routeIs('help') ? 'text-primary bg-blue-50' : '' }}"
                   title="Como usar o DuoFund">
                    <i data-lucide="help-circle" class="w-5 h-5"></i>
                </a>
                {{-- Menu do usuário --}}
                <div class="relative" x-data="{ userMenu: false }" @keydown.escape.window="userMenu = false">
                    <button @click="userMenu = !userMenu"
                            class="flex items-center gap-1.5 rounded-full p-0.5 pr-1.5 transition hover:bg-gray-50"
                            :class="userMenu && 'bg-gray-50'" aria-label="Menu do usuário">
                        <div class="flex -space-x-2">
                            @if(auth()->user()->family)
                                @foreach(auth()->user()->family->users as $member)
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-xs ring-2 ring-white shadow-sm {{ $member->id === auth()->id() ? 'bg-primary' : 'bg-purple-500' }}"
                                         title="{{ $member->name }}">
                                        {{ substr($member->name, 0, 1) }}
                                    </div>
                                @endforeach
                            @else
                                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white font-bold text-xs ring-2 ring-white shadow-sm">
                                    {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 transition-transform" :class="userMenu && 'rotate-180'"></i>
                    </button>

                    <div x-show="userMenu" x-cloak @click.away="userMenu = false"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1"
                         class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl border border-gray-100 bg-white py-1.5 shadow-xl shadow-gray-200/60 z-50">
                        <div class="px-3.5 py-2 border-b border-gray-100">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ auth()->user()->email }}</p>
                        </div>

                        <a href="{{ route('home') }}"
                           class="flex items-center gap-2.5 px-3.5 py-2.5 text-sm text-gray-600 transition hover:bg-gray-50 hover:text-primary">
                            <i data-lucide="globe" class="w-4 h-4"></i> Ver site
                        </a>
                        <a href="{{ route('profile.edit') }}" wire:navigate
                           class="flex items-center gap-2.5 px-3.5 py-2.5 text-sm text-gray-600 transition hover:bg-gray-50 hover:text-primary">
                            <i data-lucide="settings" class="w-4 h-4"></i> Configurações
                        </a>

                        <div class="my-1 border-t border-gray-100"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-sm text-red-600 transition hover:bg-red-50">
                                <i data-lucide="log-out" class="w-4 h-4"></i> Sair da conta
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    @php
        $inMoreMenu = request()->routeIs('budget') || request()->routeIs('wishlist')
                   || request()->routeIs('report') || request()->routeIs('help')
                   || request()->routeIs('goals');
        $mobileMenu = [
            ['route' => 'dashboard',    'icon' => 'layout-dashboard', 'label' => 'Painel'],
            ['route' => 'expenses',     'icon' => 'arrow-left-right', 'label' => 'Transações'],
            ['route' => 'installments', 'icon' => 'credit-card',      'label' => 'Parcelas'],
            ['route' => 'budget',       'icon' => 'pie-chart',        'label' => 'Orçamento'],
            ['route' => 'goals',        'icon' => 'target',           'label' => 'Metas'],
            ['route' => 'wishlist',     'icon' => 'gift',             'label' => 'Desejos'],
            ['route' => 'report',       'icon' => 'bar-chart-3',      'label' => 'Relatório'],
            ['route' => 'help',         'icon' => 'help-circle',      'label' => 'Ajuda'],
        ];
    @endphp

    {{-- Mobile Bottom Nav --}}
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200/80 z-40 safe-area-bottom">
        <div class="flex items-end justify-around py-2 px-0.5">
            <a href="{{ route('dashboard') }}" wire:navigate
               class="flex flex-col items-center gap-0.5 py-1.5 flex-1 min-w-0 rounded-xl transition {{ request()->routeIs('dashboard') ? 'text-primary' : 'text-gray-400' }}">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span class="text-[9px] font-semibold truncate max-w-full">Painel</span>
            </a>
            <a href="{{ route('expenses') }}" wire:navigate
               class="flex flex-col items-center gap-0.5 py-1.5 flex-1 min-w-0 rounded-xl transition {{ request()->routeIs('expenses') ? 'text-primary' : 'text-gray-400' }}">
                <i data-lucide="arrow-left-right" class="w-5 h-5"></i>
                <span class="text-[9px] font-semibold truncate max-w-full">Transações</span>
            </a>
            <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: currentScope })"
                    class="flex flex-col items-center -mt-5 flex-shrink-0 px-1">
                <div class="w-14 h-14 bg-primary rounded-2xl flex items-center justify-center shadow-lg shadow-primary/40 border-4 border-white">
                    <i data-lucide="plus" class="w-7 h-7 text-white"></i>
                </div>
            </button>
            <a href="{{ route('installments') }}" wire:navigate
               class="flex flex-col items-center gap-0.5 py-1.5 flex-1 min-w-0 rounded-xl transition {{ request()->routeIs('installments') ? 'text-primary' : 'text-gray-400' }}">
                <i data-lucide="credit-card" class="w-5 h-5"></i>
                <span class="text-[9px] font-semibold truncate max-w-full">Parcelas</span>
            </a>
            <button @click="mobileMenuOpen = true"
                    class="flex flex-col items-center gap-0.5 py-1.5 flex-1 min-w-0 rounded-xl transition {{ $inMoreMenu ? 'text-primary' : 'text-gray-400' }}">
                <i data-lucide="menu" class="w-5 h-5"></i>
                <span class="text-[9px] font-semibold truncate max-w-full">Mais</span>
            </button>
        </div>
    </nav>

    {{-- Mobile "Mais" Bottom Sheet --}}
    <div x-show="mobileMenuOpen" x-cloak class="lg:hidden fixed inset-0 z-50">
        {{-- Backdrop --}}
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @click="mobileMenuOpen = false"
             class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

        {{-- Sheet --}}
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl shadow-2xl safe-area-bottom">
            <div class="flex items-center justify-between px-5 pt-4 pb-2">
                <div class="flex items-center gap-2">
                    <span class="w-9 h-1 bg-gray-200 rounded-full absolute left-1/2 -translate-x-1/2 top-2"></span>
                    <h3 class="text-sm font-bold text-gray-800 mt-2">Menu</h3>
                </div>
                <button @click="mobileMenuOpen = false" class="p-1.5 -mr-1.5 text-gray-400 hover:text-gray-600 mt-2">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="grid grid-cols-4 gap-1 px-3 pb-6 pt-1">
                @foreach($mobileMenu as $item)
                    <a href="{{ route($item['route']) }}" wire:navigate @click="mobileMenuOpen = false"
                       class="flex flex-col items-center justify-center gap-1.5 py-3 rounded-2xl transition {{ request()->routeIs($item['route']) ? 'bg-blue-50 text-primary' : 'text-gray-500 hover:bg-gray-50' }}">
                        <i data-lucide="{{ $item['icon'] }}" class="w-5 h-5"></i>
                        <span class="text-[10px] font-semibold text-center leading-tight">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-24 lg:pb-8 flex-grow">
        {{ $slot }}
    </main>

    <footer class="hidden lg:block bg-white border-t border-gray-100 py-4 mt-auto">
        <div class="container mx-auto px-4 text-center sm:text-left text-xs text-gray-400 flex flex-col sm:flex-row justify-between">
            <p>© 2025 DuoFund. Gerenciando finanças juntos.</p>
            <div class="mt-2 sm:mt-0">
                <a href="{{ route('help') }}" wire:navigate class="hover:text-primary transition">Como usar</a>
            </div>
        </div>
    </footer>

    {{-- Toast Notification --}}
    <div x-show="toastMessage"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-3"
         x-cloak
         class="fixed bottom-24 lg:bottom-6 left-1/2 -translate-x-1/2 z-50 pointer-events-none">
        <div class="bg-gray-900 text-white text-sm font-medium px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-2.5 whitespace-nowrap">
            <span class="w-1.5 h-1.5 rounded-full bg-green-400 flex-shrink-0"></span>
            <span x-text="toastMessage"></span>
        </div>
    </div>

    {{-- Loading overlay --}}
    <div id="page-loading"
         class="fixed inset-0 z-[100] flex items-center justify-center bg-white/95 backdrop-blur-sm transition-opacity duration-300">
        <div class="flex flex-col items-center">
            <div class="w-14 h-14 bg-primary rounded-2xl flex items-center justify-center shadow-lg shadow-primary/30 loading-pulse">
                <svg class="w-7 h-7 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <p class="mt-4 text-sm font-medium text-gray-500">Carregando...</p>
        </div>
    </div>

    @include('partials.install-pwa', ['offset' => 'bottom-24'])

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

            hideLoader();

            document.addEventListener('livewire:navigate', showLoader);
            document.addEventListener('livewire:navigated', hideLoader);

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
