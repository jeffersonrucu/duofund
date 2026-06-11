@props(['heading' => '', 'subheading' => ''])

<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900">Configurações</h1>
        <p class="text-sm text-gray-500">Gerencie seu perfil e a segurança da conta.</p>
    </div>

    <div class="flex flex-col gap-6 md:flex-row">
        {{-- Navegação --}}
        <nav class="flex-shrink-0 md:w-52">
            <div class="flex gap-1 overflow-x-auto rounded-xl border border-gray-100 bg-white p-1.5 shadow-sm md:flex-col md:overflow-visible">
                @php
                    $tabs = [
                        ['route' => 'profile.edit',        'icon' => 'user',  'label' => 'Perfil'],
                        ['route' => 'user-password.edit',  'icon' => 'lock',  'label' => 'Senha'],
                    ];
                @endphp
                @foreach($tabs as $tab)
                    <a href="{{ route($tab['route']) }}" wire:navigate
                       class="flex items-center gap-2.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs($tab['route']) ? 'bg-primary text-white shadow' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i data-lucide="{{ $tab['icon'] }}" class="h-4 w-4"></i> {{ $tab['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>

        {{-- Conteúdo --}}
        <div class="flex-1 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="mb-5 border-b border-gray-100 pb-4">
                <h2 class="text-base font-bold text-gray-900">{{ $heading }}</h2>
                @if($subheading)
                    <p class="mt-0.5 text-sm text-gray-500">{{ $subheading }}</p>
                @endif
            </div>

            {{ $slot }}
        </div>
    </div>
</div>
