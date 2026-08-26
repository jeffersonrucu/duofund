<?php

namespace App\Models;

use App\Models\Concerns\ScopedToView;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory, ScopedToView;

    protected $fillable = [
        'user_id', 'scope', 'last4', 'label',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // "•••• 1234" ou "Apelido (•••• 1234)"
    public function getDisplayNameAttribute(): string
    {
        $masked = '•••• ' . $this->last4;
        return $this->label ? $this->label . ' (' . $masked . ')' : $masked;
    }
}
