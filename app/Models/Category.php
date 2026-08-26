<?php

namespace App\Models;

use App\Models\Concerns\ScopedToView;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Category extends Model
{
    use HasFactory, ScopedToView;

    protected $fillable = ['name', 'limit', 'user_id', 'scope'];

    protected $casts = ['limit' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Mudanças de limite "deste mês em diante". */
    public function limits(): HasMany
    {
        return $this->hasMany(CategoryLimit::class);
    }

    /** Limite vigente no mês: última mudança até aquele mês, senão o limite base. */
    public function limitFor(Carbon $month): float
    {
        $row = $this->limits()
            ->where('month', '<=', $month->copy()->startOfMonth()->toDateString())
            ->orderByDesc('month')
            ->first();

        return (float) ($row?->limit ?? $this->limit);
    }

    /**
     * Categorias da visão com `limit` já trocado pelo valor vigente no mês.
     * A troca é só em memória — não salve os modelos devolvidos daqui.
     *
     * @return Collection<int, static>
     */
    public static function forViewInMonth(User $user, string $view, Carbon $month): Collection
    {
        $categories = static::forView($user, $view)->with('user')->orderBy('name')->get();

        $changes = CategoryLimit::whereIn('category_id', $categories->modelKeys())
            ->where('month', '<=', $month->copy()->startOfMonth()->toDateString())
            ->orderBy('month')
            ->get()
            ->groupBy('category_id');

        foreach ($categories as $category) {
            $latest = $changes->get($category->id)?->last();
            if ($latest) {
                $category->limit = $latest->limit;
            }
        }

        return $categories;
    }

    /** Um valor só para todos os meses, passados e futuros. */
    public function setLimitForAll(float $limit): void
    {
        $this->limits()->delete();
        $this->update(['limit' => $limit]);
    }

    /** Vale deste mês em diante; os meses anteriores mantêm o que valia. */
    public function setLimitFrom(Carbon $month, float $limit): void
    {
        $start = $month->copy()->startOfMonth();

        $this->limits()->where('month', '>=', $start->toDateString())->delete();
        $this->limits()->create(['month' => $start, 'limit' => $limit]);
    }

    /** Vale só neste mês; o mês seguinte volta ao valor que valia antes. */
    public function setLimitForMonth(Carbon $month, float $limit): void
    {
        $start = $month->copy()->startOfMonth();
        $next = $start->copy()->addMonth();

        if (! $this->limits()->where('month', $next->toDateString())->exists()) {
            $this->limits()->create(['month' => $next, 'limit' => $this->limitFor($next)]);
        }

        $this->limits()->updateOrCreate(['month' => $start->toDateString()], ['limit' => $limit]);
    }
}
