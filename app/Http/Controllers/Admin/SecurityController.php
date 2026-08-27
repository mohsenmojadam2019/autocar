<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SecurityController extends Controller
{
    /** Shows staff users, role matrix, IP rules, devices and recent immutable audit logs. */
    public function index(): View
    {
        return view('admin.security.index', [
            'users' => User::query()->with('roles')->latest()->paginate(30),
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('group')->orderBy('slug')->get(),
            'ipRules' => DB::table('admin_ip_rules')->latest()->get(),
            'auditLogs' => DB::table('activity_logs')->latest()->limit(50)->get(),
        ]);
    }

    /** Assigns a bounded list of existing roles to one user. */
    public function roles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['roles' => ['nullable', 'array'], 'roles.*' => ['integer', 'exists:roles,id']]);
        $user->roles()->sync($data['roles'] ?? []);

        return back()->with('success', 'نقش‌های کاربر ذخیره شد.');
    }

    /** Creates a custom role and attaches a granular permission set. */
    public function storeRole(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['nullable', 'string', 'max:100', 'unique:roles,slug'], 'permissions' => ['nullable', 'array'], 'permissions.*' => ['integer', 'exists:permissions,id']]);
        $role = Role::query()->create(['name' => $data['name'], 'slug' => $data['slug'] ?: Str::slug($data['name']), 'is_system' => false]);
        $role->permissions()->sync($data['permissions'] ?? []);

        return back()->with('success', 'نقش جدید ایجاد شد.');
    }

    /** Adds an allow/deny CIDR rule scoped globally or to one admin. */
    public function storeIpRule(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'exists:users,id'], 'cidr' => ['required', 'string', 'max:64'], 'is_allowed' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:300']]);
        DB::table('admin_ip_rules')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'قانون IP ثبت شد.');
    }

    /** Revokes one remembered device owned by the selected user. */
    public function revokeDevice(User $user, int $device): RedirectResponse
    {
        DB::table('user_devices')->where('id', $device)->where('user_id', $user->id)->update(['revoked_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'دستگاه لغو شد.');
    }

    /** Resets administrative two-factor secret after a verified staff operation. */
    public function resetTwoFactor(User $user): RedirectResponse
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        return back()->with('success', 'احراز دومرحله‌ای کاربر ریست شد.');
    }
}
