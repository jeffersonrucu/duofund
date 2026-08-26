<?php

namespace App\Livewire\Concerns;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

/**
 * Navegação de mês compartilhada pelas páginas que filtram por período.
 * Espera uma propriedade `currentMonth` no formato Y-m-d (primeiro dia do mês).
 */
trait HasMonthNavigation
{
    /**
     * `currentMonth` vem da URL (state ->url()), então pode chegar com
     * qualquer coisa. Sanear aqui evita o 500 do Carbon::parse.
     */
    public function bootedHasMonthNavigation(): void
    {
        $this->currentMonth = $this->normalizeMonth($this->currentMonth);
    }

    /** O `booted` roda antes do set() ser aplicado, então valida aqui também. */
    public function updatedHasMonthNavigation(string $name): void
    {
        if ($name === 'currentMonth') {
            $this->currentMonth = $this->normalizeMonth($this->currentMonth);
        }
    }

    public function prevMonth(): void
    {
        $this->applyMonth($this->monthDate()->subMonth());
    }

    public function nextMonth(): void
    {
        $this->applyMonth($this->monthDate()->addMonth());
    }

    public function today(): void
    {
        $this->applyMonth(now());
    }

    public function selectMonth(?string $value): void
    {
        if (! $value) {
            return;
        }

        $this->applyMonth($this->normalizeMonth($value));
    }

    /** O mês ativo como Carbon, sempre no primeiro dia. */
    public function monthDate(): Carbon
    {
        return Carbon::parse($this->currentMonth)->startOfMonth();
    }

    protected function applyMonth(Carbon|string $month): void
    {
        $this->currentMonth = $month instanceof Carbon
            ? $month->startOfMonth()->format('Y-m-d')
            : $month;

        session(['current_month' => $this->currentMonth]);

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /**
     * Aceita Y-m-d (estado interno) e Y-m (input type="month").
     * Qualquer outra coisa cai no mês atual.
     */
    protected function normalizeMonth(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        $format = match (true) {
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) => 'Y-m-d',
            (bool) preg_match('/^\d{4}-\d{2}$/', $value) => 'Y-m',
            default => null,
        };

        if ($format !== null) {
            try {
                // Formato bate, mas o valor ainda pode ser irreal (2026-13).
                // Em strict mode o Carbon lança em vez de retornar false.
                return Carbon::createFromFormat($format, $value)
                    ->startOfMonth()
                    ->format('Y-m-d');
            } catch (InvalidFormatException) {
                // cai no mês atual
            }
        }

        return now()->startOfMonth()->format('Y-m-d');
    }
}
