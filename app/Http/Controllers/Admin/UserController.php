<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff accounts. Public registration is disabled, so admins create users here.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('users/index', [
            'users' => User::query()->orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'roleLabel' => $user->role->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/form', ['user' => null, 'roles' => UserRole::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        User::query()->create($data + ['email_verified_at' => now()]);
        $this->toast('تم إضافة المستخدم');

        return to_route('users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/form', [
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'roles' => UserRole::options(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'string', Password::defaults()],
        ]);

        // An admin can't demote themselves and lock everyone out.
        if ($user->is($request->user()) && $data['role'] !== UserRole::Admin->value) {
            return back()->withErrors(['role' => 'مينفعش تشيل صلاحية المدير من نفسك']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);
        $this->toast('تم حفظ المستخدم');

        return to_route('users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            $this->toast('مينفعش تمسح حسابك من هنا', 'error');

            return back();
        }

        $user->delete();
        $this->toast('تم مسح المستخدم');

        return to_route('users.index');
    }
}
