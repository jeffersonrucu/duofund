<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopedToView
{
    /**
     * Filtra registros conforme a visão ativa: 'personal' (só do usuário)
     * ou 'shared' (de qualquer membro da família).
     */
    public function scopeForView(Builder $query, User $user, string $view): Builder
    {
        return $view === 'personal'
            ? $query->where('user_id', $user->id)->where('scope', 'personal')
            : $query->whereIn('user_id', $user->getFamilyUserIds())->where('scope', 'shared');
    }

    /**
     * O usuário pode gerenciar (editar/deletar) este registro?
     * Personal: só o dono. Shared: qualquer membro da família do dono.
     */
    public function manageableBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->scope === 'shared'
            && in_array($this->user_id, $user->getFamilyUserIds());
    }
}
