<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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

    /** @return BelongsTo<Family, $this> */
    public function family(): BelongsTo
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

    public function getInitial()
    {
        return strtoupper(substr($this->name, 0, 1));
    }

    public function getFamilyInitials()
    {
        if (!$this->family) {
            return $this->getInitial();
        }

        $initials = $this->family->users()
            ->orderBy('name')
            ->get()
            ->map(fn(User $user) => $user->getInitial())
            ->join(' & ');

        return $initials;
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