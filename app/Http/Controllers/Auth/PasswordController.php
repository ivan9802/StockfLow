<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Changement de son propre mot de passe par l'utilisateur connecté. */
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.password', ['forced' => $request->user()->must_change_password]);
    }

    public function update(Request $request, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            // Un seul champ, avec bouton d'affichage : pas de confirmation à ressaisir.
            // 72 caractères au plus : Bcrypt ignore ce qui dépasse.
            'password' => ['required', 'string', Password::defaults(), 'different:current_password', 'max:72'],
        ], [], ['password' => 'nouveau mot de passe']);

        $user = $request->user();

        if (strcasecmp($validated['password'], $user->email) === 0) {
            return back()->withErrors(['password' => 'Le mot de passe doit être différent de votre adresse e-mail.']);
        }

        DB::transaction(function () use ($user, $validated, $audit, $request) {
            $user->forceFill([
                'password' => $validated['password'],
                'must_change_password' => false,
                'remember_token' => Str::random(60),
            ])->saveQuietly();

            // Les sessions ouvertes ailleurs avec l'ancien mot de passe sont fermées.
            DB::table('sessions')->where('user_id', $user->id)->where('id', '<>', $request->session()->getId())->delete();

            $audit->record(AuditAction::Updated, $user, 'Changement de mot de passe de '.$user->name);
        });

        // Nouvel identifiant de session après un changement de secret.
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Votre mot de passe a été changé.');
    }
}
