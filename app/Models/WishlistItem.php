<?php

namespace App\Models;

use App\Models\Concerns\ScopedToView;
use Illuminate\Database\Eloquent\Model;

class WishlistItem extends Model
{
    use ScopedToView;

    protected $fillable = ['user_id', 'name', 'notes', 'url', 'price', 'priority', 'scope', 'status'];

    protected $casts = ['price' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            'high'   => 'Alta',
            'medium' => 'Média',
            'low'    => 'Baixa',
            default  => 'Média',
        };
    }
}
