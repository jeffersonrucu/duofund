<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        .modal-content { transform: translateY(-20px); transition: transform 0.3s ease-out; }
        .modal-overlay.active .modal-content { transform: translateY(0); }

        .nav-btn.active { color: white !important; background-color: #2674D9 !important; font-weight: 600; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) inset; }
        .nav-btn { color: #4b5563; background-color: transparent; padding: 8px 12px; border-radius: 0.5rem; transition: background-color 0.15s, color 0.15s; }
        .nav-btn:hover:not(.active) { color: #2674D9; background-color: #eff6ff; }
        
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-gray-50" 
      x-data="{ 
          transactionModalOpen: false, 
          categoryModalOpen: false, 
          goalModalOpen: false,
          toastMessage: '',
          showToast(msg) {
              this.toastMessage = msg;
              setTimeout(() => this.toastMessage = '', 3000);
          }
      }"
      @notify.window="showToast($event.detail)">

    <header class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-10">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-6">
                <a href="{{ route('dashboard') }}" wire:navigate class="text-xl font-extrabold text-gray-800 flex items-center">
                    <span class="text-primary">Duo</span>Fund <span class="text-sm font-normal text-gray-500 ml-2 border-l pl-2">Juntos</span>
                </a>
                
                <nav class="hidden lg:flex space-x-1">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <button class="nav-btn flex items-center {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2"></i>Painel
                        </button>
                    </a>
                    <a href="{{ route('expenses') }}" wire:navigate>
                        <button class="nav-btn flex items-center {{ request()->routeIs('expenses') ? 'active' : '' }}">
                            <i data-lucide="receipt" class="w-4 h-4 mr-2"></i>Despesas
                        </button>
                    </a>
                    <a href="{{ route('budget') }}" wire:navigate>
                        <button class="nav-btn flex items-center {{ request()->routeIs('budget') ? 'active' : '' }}">
                            <i data-lucide="target" class="w-4 h-4 mr-2"></i>Orçamento
                        </button>
                    </a>
                    <a href="{{ route('goals') }}" wire:navigate>
                        <button class="nav-btn flex items-center {{ request()->routeIs('goals') ? 'active' : '' }}">
                            <i data-lucide="trophy" class="w-4 h-4 mr-2"></i>Metas
                        </button>
                    </a>
                </nav>
            </div>

            <div class="flex items-center space-x-3">
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

        <div class="lg:hidden flex justify-around py-5 bg-gray-50 border-t text-xs">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex flex-col items-center {{ request()->routeIs('dashboard') ? 'text-primary' : 'text-gray-600' }}"><i data-lucide="layout-dashboard" class="w-5 h-5"></i></a>
            <a href="{{ route('goals') }}" wire:navigate class="flex flex-col items-center {{ request()->routeIs('goals') ? 'text-primary' : 'text-gray-600' }}"><i data-lucide="trophy" class="w-5 h-5"></i></a>
            <button @click="transactionModalOpen = true" class="flex flex-col items-center text-primary"><i data-lucide="plus-circle" class="w-8 h-8 -mt-2 drop-shadow-md"></i></button>
            <a href="{{ route('budget') }}" wire:navigate class="flex flex-col items-center {{ request()->routeIs('budget') ? 'text-primary' : 'text-gray-600' }}"><i data-lucide="target" class="w-5 h-5"></i></a>
            <a href="{{ route('expenses') }}" wire:navigate class="flex flex-col items-center {{ request()->routeIs('expenses') ? 'text-primary' : 'text-gray-600' }}"><i data-lucide="receipt" class="w-5 h-5"></i></a>
        </div>
    </header>

    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-grow">
        {{ $slot }}
    </main>

    <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
        <div class="container mx-auto px-4 text-center sm:text-left text-xs text-gray-500 flex flex-col sm:flex-row justify-between">
            <p>© 2025 DuoFund. Gerenciando finanças juntos.</p>
            <div class="space-x-4 mt-2 sm:mt-0">
                <a href="#" class="hover:text-primary">Privacidade</a>
                <a href="#" class="hover:text-primary">Termos</a>
            </div>
        </div>
    </footer>

    <div x-show="toastMessage" x-transition x-cloak
         class="fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-3 rounded-lg shadow-lg z-50" 
         x-text="toastMessage"></div>

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
    </script>
</body>
</html>