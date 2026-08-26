@php
    $pageName = $paginator->getPageName();
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Paginação" class="flex items-center justify-between gap-3">
            <span class="text-[10px] text-gray-400">
                {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }} lançamentos
            </span>

            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="w-8 h-8 flex items-center justify-center text-gray-300 cursor-default" aria-disabled="true" aria-label="Página anterior">
                        <x-lucide-chevron-left class="w-4 h-4" />
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled"
                        class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg transition" aria-label="Página anterior">
                        <x-lucide-chevron-left class="w-4 h-4" />
                    </button>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="w-8 h-8 flex items-center justify-center text-[11px] text-gray-400 cursor-default">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <span wire:key="paginator-{{ $pageName }}-page-{{ $page }}">
                                @if ($page == $paginator->currentPage())
                                    <span class="w-8 h-8 flex items-center justify-center text-[11px] font-bold text-white bg-primary rounded-lg cursor-default" aria-current="page">{{ $page }}</span>
                                @else
                                    <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')"
                                        class="w-8 h-8 flex items-center justify-center text-[11px] font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition" aria-label="Ir para página {{ $page }}">
                                        {{ $page }}
                                    </button>
                                @endif
                            </span>
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled"
                        class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg transition" aria-label="Próxima página">
                        <x-lucide-chevron-right class="w-4 h-4" />
                    </button>
                @else
                    <span class="w-8 h-8 flex items-center justify-center text-gray-300 cursor-default" aria-disabled="true" aria-label="Próxima página">
                        <x-lucide-chevron-right class="w-4 h-4" />
                    </span>
                @endif
            </div>
        </nav>
    @endif
</div>
