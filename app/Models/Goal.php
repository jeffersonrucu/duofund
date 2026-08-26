<?php

namespace App\Models;

use App\Models\Concerns\ScopedToView;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Goal extends Model
{
    use HasFactory, ScopedToView;

    protected $fillable = [
        'name',
        'target',
        'current',
        'user_id',
        'is_private',
        'scope',
        'plan_mode',
        'monthly_target',
        'target_date',
    ];

    protected $casts = [
        'target' => 'decimal:2',
        'current' => 'decimal:2',
        'is_private' => 'boolean',
        'target_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getRemainingAttribute(): float
    {
        return max(0, (float) $this->target - (float) $this->current);
    }

    public function getProgressPctAttribute(): float
    {
        $target = (float) $this->target ?: 1;
        return min(100, ((float) $this->current / $target) * 100);
    }

    // Ritmo médio mensal desde a criação (estimativa: guardado ÷ meses ativos)
    public function getMonthlyPaceAttribute(): float
    {
        $months = $this->created_at ? max(1, $this->created_at->diffInMonths(now())) : 1;
        return (float) $this->current / $months;
    }

    /**
     * Previsão de conclusão e comparação com o ritmo real.
     */
    public function forecast(): array
    {
        $remaining = $this->remaining;
        $pace = $this->monthly_pace;

        $out = [
            'mode' => $this->plan_mode,
            'remaining' => $remaining,
            'pace' => $pace,
            'completed' => $remaining <= 0,
            'hasPlan' => false,
            'forecastDate' => null,
            'monthsLeft' => null,
            'neededMonthly' => null,
            'onTrack' => null,
            'overdue' => false,
        ];

        if ($remaining <= 0) {
            return $out;
        }

        if ($this->plan_mode === 'monthly' && (float) $this->monthly_target > 0) {
            $monthly = (float) $this->monthly_target;
            $months = (int) ceil($remaining / $monthly);
            $out['hasPlan'] = true;
            $out['monthsLeft'] = $months;
            $out['forecastDate'] = Carbon::now()->startOfMonth()->addMonths($months);
            $out['neededMonthly'] = $monthly;
            $out['onTrack'] = $pace >= $monthly * 0.999;
        } elseif ($this->plan_mode === 'date' && $this->target_date) {
            $now = Carbon::now()->startOfMonth();
            $target = $this->target_date->copy()->startOfMonth();
            $out['hasPlan'] = true;
            $out['forecastDate'] = $this->target_date;

            if ($target->lessThan($now)) {
                $out['overdue'] = true;
                $out['monthsLeft'] = 0;
                $out['neededMonthly'] = $remaining;
                $out['onTrack'] = false;
            } else {
                $monthsLeft = max(1, $now->diffInMonths($target));
                $needed = $remaining / $monthsLeft;
                $out['monthsLeft'] = $monthsLeft;
                $out['neededMonthly'] = $needed;
                $out['onTrack'] = $pace >= $needed * 0.999;
            }
        }

        return $out;
    }
}
