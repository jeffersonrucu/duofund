<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $familyId = session('invite_family_id');

        if ($familyId) {
            $family = \App\Models\Family::find($familyId);
            if ($family && $family->users()->count() >= 2) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => ['Esta família já está completa. Peça um novo link de convite se alguém saiu.'],
                ]);
            }
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'family_id' => $familyId, // Vincula à família existente
        ]);

        if ($familyId) {
            session()->forget('invite_family_id');
        }

        return $user;
    }
}