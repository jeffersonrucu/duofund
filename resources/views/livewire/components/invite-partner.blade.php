<?php

use function Livewire\Volt\{state};
use Illuminate\Support\Facades\URL;

state(['inviteLink' => '']);

$generateLink = function() {
    $user = auth()->user();

    if (!$user->family_id) {
        $this->dispatch('notify', 'Erro: Família não configurada.');
        return;
    }

    // Gera link assinado válido por 24 horas
    $this->inviteLink = URL::signedRoute('invite.accept', [
        'family' => $user->family_id
    ], expiration: now()->addHours(24));
    
    $this->dispatch('notify', 'Link gerado com sucesso!');
};

?>

<div class="bg-gradient-to-r from-violet-600 to-indigo-600 rounded-xl p-6 text-white shadow-lg mb-8">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="mb-4 md:mb-0">
            <h2 class="text-xl font-bold flex items-center">
                <i data-lucide="heart-handshake" class="w-6 h-6 mr-2"></i> Finanças a Dois
            </h2>
            <p class="text-purple-100 text-sm mt-1">
                Convide sua parceira(o) para gerenciar o painel juntos.
            </p>
        </div>
        
        <div class="flex flex-col items-end gap-2 w-full md:w-auto">
            @if(!$inviteLink)
                <button wire:click="generateLink" class="bg-white text-violet-600 px-4 py-2 rounded-lg font-bold hover:bg-gray-100 transition shadow-md whitespace-nowrap flex items-center">
                    <i data-lucide="link" class="w-4 h-4 mr-2"></i> Gerar Link de Convite
                </button>
            @else
                <div x-data="{ copied: false }" class="flex flex-col items-end gap-2">
                    <div class="flex bg-white/20 p-1 rounded-lg w-full md:w-auto min-w-[275px]">
                        <input type="text" readonly value="{{ $inviteLink }}" class="bg-transparent text-white text-xs px-2 w-full focus:outline-none truncate font-mono">
                        <button @click="navigator.clipboard.writeText('{{ $inviteLink }}'); copied = true; setTimeout(() => copied = false, 2000); $dispatch('notify', 'Link copiado!')" 
                            class="bg-white text-violet-600 p-1.5 rounded-md hover:bg-gray-100 transition" title="Copiar">
                            <i x-show="!copied" data-lucide="copy" class="w-4 h-4"></i>
                            <i x-show="copied" x-cloak data-lucide="check" class="w-4 h-4 text-green-600"></i>
                        </button>
                    </div>
                    <p class="text-xs text-purple-200">
                        <i data-lucide="clock" class="w-3 h-3 inline"></i>
                        Expira em 24 horas. Gere outro se expirar.
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>