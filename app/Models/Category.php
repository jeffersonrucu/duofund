<?php

namespace App\Models;

use App\Models\Concerns\ScopedToView;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, ScopedToView;

    protected $fillable = ['name', 'limit', 'user_id', 'scope'];

    protected $casts = ['limit' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}