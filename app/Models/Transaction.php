<?php

namespace App\Models;

use App\Models\Concerns\ScopedToView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    use HasFactory, ScopedToView;

    /**
     * Quantos meses à frente uma recorrência é materializada.
     * O comando duofund:extend-recurrences mantém as séries ativas
     * sempre preenchidas até este horizonte.
     */
    public const RECURRENCE_HORIZON_MONTHS = 12;

    protected $fillable = [
        'description', 'type', 'amount', 'category', 'date',
        'user_id', 'scope', 'payment_method', 'card_id',
        'is_recurring', 'is_installment', 'installment_current', 'installment_count',
        'recurring_group_id', 'mirror_transaction_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_recurring' => 'boolean',
        'is_installment' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    /** Receita shared que originou esta despesa espelho. */
    public function mirrorOf()
    {
        return $this->belongsTo(self::class, 'mirror_transaction_id');
    }

    /** Despesa espelho gerada por esta receita shared. */
    public function mirror()
    {
        return $this->hasOne(self::class, 'mirror_transaction_id');
    }

    public function scopeInMonth(Builder $query, Carbon|string $month): Builder
    {
        $date = $month instanceof Carbon ? $month : Carbon::parse($month);

        return $query->whereYear('date', $date->year)
                     ->whereMonth('date', $date->month);
    }
}
