<?php

namespace Tests\Feature\Database;

use App\Models\SharedReport;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SharedReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_token_is_long_and_never_stored_in_clear_text(): void
    {
        $request = VerificationRequest::factory()->create();

        $shared = SharedReport::issue($request, $request->user);
        $token = $shared->token();

        $this->assertSame(48, strlen($token));

        $stored = (array) DB::table('shared_reports')->first();
        foreach ($stored as $column => $value) {
            $this->assertStringNotContainsString($token, (string) $value, "الرمز ظهر نصًا واضحًا في العمود $column");
        }
        $this->assertSame(hash('sha256', $token), $stored['token_hash']);
    }

    public function test_tokens_are_unique(): void
    {
        $request = VerificationRequest::factory()->create();

        $a = SharedReport::issue($request, $request->user)->token();
        $b = SharedReport::issue($request, $request->user)->token();

        $this->assertNotSame($a, $b);
    }

    public function test_active_link_is_found_by_its_token(): void
    {
        $request = VerificationRequest::factory()->create();
        $shared = SharedReport::issue($request, $request->user);

        $found = SharedReport::findActiveByToken($shared->token());

        $this->assertNotNull($found);
        $this->assertTrue($found->is($shared));
    }

    public function test_unknown_revoked_and_expired_tokens_are_rejected(): void
    {
        $request = VerificationRequest::factory()->create();

        $revoked = SharedReport::issue($request, $request->user);
        $revoked->revoke();

        $expired = SharedReport::issue($request, $request->user, expiresAt: now()->subMinute());

        $this->assertNull(SharedReport::findActiveByToken('not-a-real-token'));
        $this->assertNull(SharedReport::findActiveByToken($revoked->token()));
        $this->assertNull(SharedReport::findActiveByToken($expired->token()));
    }

    public function test_owner_is_hidden_by_default(): void
    {
        $request = VerificationRequest::factory()->create();

        $shared = SharedReport::issue($request, $request->user);

        $this->assertTrue($shared->hide_owner);
        $this->assertFalse($shared->show_preview);
    }
}
