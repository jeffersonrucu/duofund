<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Limite de uma categoria vigente a partir de um mês. O limite base fica
 * em categories.limit; cada linha aqui é uma mudança "deste mês em diante".
 */
class CategoryLimit extends Model
{
    protected $fillable = ['category_id', 'month', 'limit'];

    protected $casts = ['month' => 'date', 'limit' => 'decimal:2'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
