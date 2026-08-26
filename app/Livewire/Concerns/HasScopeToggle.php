<?php

namespace App\Livewire\Concerns;

/**
 * Alternância entre visão pessoal e do casal.
 * Espera uma propriedade `view` com 'personal' ou 'shared'.
 */
trait HasScopeToggle
{
    /** `view` vem da URL, então precisa ser validada a cada request. */
    public function bootedHasScopeToggle(): void
    {
        $this->view = $this->normalizeView($this->view);
    }

    /** O `booted` roda antes do set() ser aplicado, então valida aqui também. */
    public function updatedHasScopeToggle(string $name): void
    {
        if ($name === 'view') {
            $this->view = $this->normalizeView($this->view);
        }
    }

    public function setView(string $mode): void
    {
        $this->view = $this->normalizeView($mode);

        session(['view_mode' => $this->view]);
        $this->dispatch('scope-changed', mode: $this->view);

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    protected function normalizeView(mixed $value): string
    {
        return in_array($value, ['personal', 'shared'], true) ? $value : 'personal';
    }
}
