<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'description', 'type', 'amount', 'category', 'date',
        'user_id', 'scope',
        'is_recurring', 'is_installment', 'installment_current', 'installment_count',
        'recurring_group_id'
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'is_installment' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
