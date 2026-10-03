<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Verification\VerificationCleaner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        // الحساب النظامي لتحليلات الزوار غير المسجَّلين لا يظهر في إدارة المستخدمين
        $query = User::query()->with('role')->withCount('verificationRequests')
            ->where('email', '!=', User::GUEST_EMAIL)->latest();

        if ($request->query('deleted') === '1') {
            $query->onlyTrashed();
        }
        if ($status = UserStatus::tryFrom((string) $request->query('status'))) {
            $query->where('status', $status->value);
        }
        if ($role = $request->query('role')) {
            $query->whereHas('role', fn ($q) => $q->where('slug', $role));
        }
        if (($search = trim((string) $request->query('q'))) !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }

        return view('admin.users.index', [
            'users' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['q', 'status', 'role', 'deleted']),
        ]);
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user->load('role')->loadCount('verificationRequests'),
            'recent' => $user->verificationRequests()->with(['mediaFile', 'result'])->latest()->limit(8)->get(),
            'activities' => $user->activityLogs()->latest('created_at')->limit(10)->get(),
        ]);
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(UserStatus::class)]]);
        $this->guardSelf($request, $user);

        $new = UserStatus::from($data['status']);
        if ($new === UserStatus::Suspended) {
            $this->guardLastAdmin($user);
        }

        $user->forceFill(['status' => $new])->save();
        ActivityLog::record($new === UserStatus::Suspended ? 'admin.user_suspended' : 'admin.user_activated', 'تغيير حالة حساب', $user, ['status' => $new->value]);

        return back()->with('success', __('admin.users.status_updated'));
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in([Role::ADMIN, Role::USER])]]);
        $this->guardSelf($request, $user);

        if ($data['role'] === Role::USER) {
            $this->guardLastAdmin($user);
        }

        $user->forceFill(['role_id' => Role::idFor($data['role'])])->save();
        ActivityLog::record('admin.user_role_changed', 'تغيير دور مستخدم', $user, ['role' => $data['role']]);

        return back()->with('success', __('admin.users.role_updated'));
    }

    public function destroy(Request $request, User $user, VerificationCleaner $cleaner): RedirectResponse
    {
        $this->guardSelf($request, $user);
        $this->guardLastAdmin($user);

        // نحذف ملفاته من القرص فورًا ثم نحذف الحساب حذفًا منطقيًا (يبقى أثر تدقيق)
        foreach ($user->verificationRequests()->with(['mediaFile', 'result'])->cursor() as $verification) {
            $cleaner->deleteFiles($verification);
        }
        $user->forceFill(['status' => UserStatus::Suspended])->save();
        $user->delete();

        ActivityLog::record('admin.user_deleted', 'حذف مستخدم', $user, ['email' => $user->email]);

        return redirect()->route('admin.users.index')->with('success', __('admin.users.deleted'));
    }

    public function restore(int $id): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();
        ActivityLog::record('admin.user_restored', 'استعادة مستخدم', $user);

        return redirect()->route('admin.users.show', $user)->with('success', __('admin.users.restored'));
    }

    private function guardSelf(Request $request, User $user): void
    {
        abort_if($request->user()->is($user), 422, __('admin.users.cannot_self'));
    }

    private function guardLastAdmin(User $user): void
    {
        $isAdmin = $user->isAdmin();
        $otherActiveAdmins = User::query()->whereHas('role', fn ($q) => $q->where('slug', Role::ADMIN))
            ->where('status', UserStatus::Active->value)->whereKeyNot($user->id)->count();

        abort_if($isAdmin && $otherActiveAdmins === 0, 422, __('admin.users.last_admin'));
    }
}
