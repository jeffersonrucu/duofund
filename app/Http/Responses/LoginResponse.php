<?php

namespace App\Http\Responses;

use App\Models\Family;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = auth()->user();
        $familyId = session('invite_family_id');

        // Se há um convite pendente na sessão
        if ($familyId) {
            session()->forget('invite_family_id');

            $family = Family::find($familyId);

            if (!$family) {
                return $this->redirectWithNotification($request, 'error', 'Família não encontrada. O link pode estar inválido.');
            }

            // Se a família já estiver cheia
            if ($family->users()->count() >= 2) {
                return $this->redirectWithNotification($request, 'error', 'Esta família já está completa.');
            }

            // Se o usuário já tiver uma família
            if ($user->family_id) {
                return $this->redirectWithNotification($request, 'error', 'Você já faz parte de uma família.');
            }

            // Associa o usuário à família
            $user->family_id = $family->id;
            $user->save();

            return $this->redirectWithNotification($request, 'success', 'Você entrou na família com sucesso!');
        }

        // Login normal sem convite
        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(route('dashboard'));
    }

    private function redirectWithNotification($request, string $type, string $message)
    {
        if ($request->wantsJson()) {
            return new JsonResponse([
                'redirect' => route('dashboard'),
                'notification' => ['type' => $type, 'message' => $message]
            ], 200);
        }

        return redirect()->route('dashboard')->with('notification', [
            'type' => $type,
            'message' => $message
        ]);
    }
}
