<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VerificationRequest;

class VerificationRequestPolicy
{
    public function view(User $user, VerificationRequest $request): bool
    {
        return $this->owns($user, $request) || $user->isAdmin();
    }

    /** الوصول إلى الملف الأصلي: المالك أو المدير (لمراجعة المحتوى المخالف). */
    public function viewMedia(User $user, VerificationRequest $request): bool
    {
        return $this->owns($user, $request) || $user->isAdmin();
    }

    public function delete(User $user, VerificationRequest $request): bool
    {
        return $this->owns($user, $request) || $user->isAdmin();
    }

    /** المشاركة العامة حق لصاحب الطلب وحده، ولا تتاح إلا لنتيجة مكتملة. */
    public function share(User $user, VerificationRequest $request): bool
    {
        return $this->owns($user, $request) && $request->status->hasResult();
    }

    public function retry(User $user, VerificationRequest $request): bool
    {
        return $this->owns($user, $request) && $request->status->value === 'failed';
    }

    private function owns(User $user, VerificationRequest $request): bool
    {
        return $request->user_id === $user->id;
    }
}
