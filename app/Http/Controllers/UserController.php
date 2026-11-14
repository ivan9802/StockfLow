<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Enums\WarehouseType;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * Comptes utilisateurs, réservés à l'administrateur.
 * Un compte n'est jamais supprimé : il est désactivé, et son historique garde son auteur.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::with('warehouse:id,name')->orderBy('is_active', 'desc')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['is_active' => true, 'role' => Role::SalesAgent])] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $password = $data['password'] ?? null;
        $generated = blank($password);
        $password = $generated ? $this->generatePassword() : $password;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'is_active' => $data['is_active'],
            'password' => $password,
            // Le mot de passe attribué par l'administrateur est remplacé à la première connexion.
            'must_change_password' => true,
        ]);

        return redirect()->route('users.index')->with('success', $generated
            ? "Compte créé pour {$user->name}. Mot de passe provisoire : $password (affiché une seule fois)."
            : "Compte créé pour {$user->name}. Il devra changer son mot de passe à la première connexion.");
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user] + $this->formData());
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        // L'administrateur ne peut ni se retirer ses droits ni désactiver son propre compte.
        if ($user->is($request->user()) && ($data['role'] !== Role::Admin->value || ! $data['is_active'])) {
            return back()->withInput()->with('error', 'Vous ne pouvez pas retirer vos propres droits d\'administrateur ni désactiver votre compte.');
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        return redirect()->route('users.index')->with('success', "Compte de {$user->name} modifié.");
    }

    /** Mot de passe oublié : l'administrateur en attribue un nouveau (D-010). */
    public function resetPassword(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $password = $this->generatePassword();

        DB::transaction(function () use ($user, $password, $audit) {
            $user->forceFill(['password' => $password, 'must_change_password' => true, 'remember_token' => Str::random(60)])->saveQuietly();
            // Les sessions ouvertes du compte sont fermées.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $audit->record(AuditAction::Updated, $user, "Réinitialisation du mot de passe de {$user->name}");
        });

        return back()->with('success', "Nouveau mot de passe provisoire de {$user->name} : $password (affiché une seule fois, à changer à la connexion).");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(Role::values())],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'is_active' => ['required', 'boolean'],
            'password' => $user ? ['prohibited'] : ['nullable', 'string', Password::defaults(), 'max:72'],
        ], [], ['password' => 'mot de passe provisoire']);

        // Un vendeur doit être affecté à un point de vente : c'est de là qu'il vend (D-009).
        $validator->after(function (Validator $v) use ($request) {
            if ($request->input('role') === Role::SalesAgent->value) {
                $warehouse = Warehouse::find($request->integer('warehouse_id'));
                if ($warehouse === null || $warehouse->type !== WarehouseType::Store) {
                    $v->errors()->add('warehouse_id', 'Un vendeur doit être affecté à un point de vente.');
                }
            }
        });

        $request->merge(['is_active' => $request->boolean('is_active')]);
        $validator->setData($request->all());

        return $validator->validate();
    }

    private function formData(): array
    {
        return [
            'roles' => collect(Role::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()]),
            'warehouses' => Warehouse::active()->orderBy('id')->pluck('name', 'id'),
        ];
    }

    /** Mot de passe provisoire lisible à dicter : quatre groupes séparés par des tirets. */
    private function generatePassword(): string
    {
        return implode('-', array_map(fn () => Str::lower(Str::random(4)), range(1, 4)));
    }
}
