<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    private const SESSION_KEY = 'impersonator_user_id';

    public function start(Request $request, User $user, AuditLogService $audit): RedirectResponse
    {
        $administrator = backpack_user();
        abort_unless($administrator instanceof User && $administrator->hasActiveRole('App Administrator'), 403);
        abort_if($request->session()->has(self::SESSION_KEY), 409, 'An impersonation session is already active.');
        abort_if($administrator->is($user), 422, 'You cannot impersonate yourself.');
        abort_unless(
            $user->is_active && ($user->roleAssignments()->where('is_active', true)->exists() || $user->hasActiveZoneLeadership()),
            422,
            'Only active leaders can be impersonated.',
        );

        $audit->record('user.impersonation.started', $administrator, $user, request: $request);
        $request->session()->put(self::SESSION_KEY, $administrator->id);
        $this->switchAuthenticatedUser($request, $user);

        return redirect()->route('admin.dashboard')->with('success', 'You are now viewing the app as '.$user->name.'.');
    }

    public function stop(Request $request, AuditLogService $audit): RedirectResponse
    {
        $administratorId = $request->session()->get(self::SESSION_KEY);
        abort_unless(is_string($administratorId), 403);
        $impersonatedUser = backpack_user();
        $administrator = User::query()->findOrFail($administratorId);
        abort_unless($administrator->is_active && $administrator->hasActiveRole('App Administrator'), 403);

        $this->switchAuthenticatedUser($request, $administrator);
        $request->session()->forget(self::SESSION_KEY);
        $audit->record('user.impersonation.stopped', $administrator, $impersonatedUser, request: $request);

        return redirect()->route('users.index')->with('success', 'Impersonation ended.');
    }

    private function switchAuthenticatedUser(Request $request, User $user): void
    {
        backpack_auth()->login($user);
        $request->session()->regenerate();
        $request->session()->put(
            'password_hash_'.backpack_guard_name(),
            $user->getAuthPassword(),
        );
    }
}
