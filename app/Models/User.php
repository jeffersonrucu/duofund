<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'family_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function getFamilyUserIds()
    {
        if ($this->family_id) {
            return User::where('family_id', $this->family_id)->pluck('id')->toArray();
        }
        return [$this->id];
    }

    protected static function booted()
    {
        static::created(function ($user) {
            if (!$user->family_id) {
                $family = \App\Models\Family::create(['name' => 'Família de ' . $user->name]);
                $user->family_id = $family->id;
                $user->saveQuietly();
            }
        });
    }
}