<?php

namespace Tests\Feature;

use App\Domain\Mailbox\Services\SafeEmailHtmlSanitizer;
use App\Models\MailboxAccount;
use App\Models\MailboxEmail;
use App\Models\MailboxFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyBusinessMailboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailbox_uses_assigned_company_accounts_and_not_internal_messaging(): void
    {
        $this->seed();
        $owner = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $account = $this->account($owner);

        $this->actingAs($owner)->get(route('mailbox.index'))
            ->assertRedirect(route('mailbox.external.show', $account));

        $this->actingAs($owner)->get(route('mailbox.external.show', [$account, 'compose' => 'new']))
            ->assertOk()
            ->assertSee('Send business email through IMAP and SMTP.')
            ->assertSee('name="to[]"', false)
            ->assertSee('name="cc[]"', false)
            ->assertSee('name="bcc[]"', false)
            ->assertDontSee('Builder360 Internal')
            ->assertDontSee('Search employees')
            ->assertDontSee('No project link')
            ->assertDontSee('Priority');
    }

    public function test_shared_mailbox_assignment_controls_view_and_send_capabilities(): void
    {
        $this->seed();
        $owner = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $account = $this->account($owner);

        $this->actingAs($owner)->post(route('mailbox.accounts.assignments.store', $account), [
            'user_id' => $employee->id,
            'can_view' => 1,
            'can_send' => 0,
            'can_manage' => 0,
        ])->assertRedirect();

        $this->actingAs($employee)->get(route('mailbox.external.show', $account))
            ->assertOk()
            ->assertDontSee('Compose');

        $this->actingAs($employee)->post(route('mailbox.external.send', $account), [
            'client_token' => fake()->uuid(),
            'to' => ['customer@example.com'],
            'subject' => 'Not allowed',
            'body' => 'This request must be blocked.',
        ])->assertForbidden();
    }

    public function test_reply_all_uses_standard_email_headers_and_excludes_the_active_account(): void
    {
        $this->seed();
        $owner = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $account = $this->account($owner);
        $folder = MailboxFolder::create(['mailbox_account_id' => $account->id, 'name' => 'Inbox', 'remote_path' => 'INBOX', 'special_use' => 'inbox']);
        $message = MailboxEmail::create([
            'mailbox_account_id' => $account->id,
            'mailbox_folder_id' => $folder->id,
            'remote_uid' => 10,
            'internet_message_id' => '<source@example.com>',
            'references' => ['<root@example.com>'],
            'thread_key' => 'thread-1',
            'subject' => 'Quarterly review',
            'from_addresses' => [['name' => 'Client', 'email' => 'client@example.com']],
            'to_addresses' => [['email' => $account->email], ['email' => 'sales@example.com']],
            'cc_addresses' => [['email' => 'accounts@example.com']],
            'reply_to_addresses' => [['email' => 'reply@example.com']],
            'text_body' => 'Original email.',
            'received_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('mailbox.external.show', [$account, 'compose' => 'reply_all', 'compose_message' => $message->id]))
            ->assertOk()
            ->assertSee('Reply all')
            ->assertSee('reply@example.com')
            ->assertSee('sales@example.com')
            ->assertSee('accounts@example.com')
            ->assertSee('data-to=\'["reply@example.com","sales@example.com"]\'', false)
            ->assertSee('source@example.com');
    }

    public function test_forward_starts_a_new_message_context_without_reply_headers(): void
    {
        $this->seed();
        $owner = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $account = $this->account($owner);
        $folder = MailboxFolder::create(['mailbox_account_id' => $account->id, 'name' => 'Inbox', 'remote_path' => 'INBOX', 'special_use' => 'inbox']);
        $message = MailboxEmail::create(['mailbox_account_id' => $account->id, 'mailbox_folder_id' => $folder->id, 'remote_uid' => 20, 'internet_message_id' => '<forward@example.com>', 'references' => ['<root@example.com>'], 'thread_key' => 'thread-2', 'subject' => 'Site update', 'from_addresses' => [['email' => 'site@example.com']], 'to_addresses' => [['email' => $account->email]], 'text_body' => 'Progress update.', 'received_at' => now()]);

        $this->actingAs($owner)->get(route('mailbox.external.show', [$account, 'compose' => 'forward', 'compose_message' => $message->id]))
            ->assertOk()
            ->assertSee('Forward')
            ->assertSee('Fwd: Site update')
            ->assertSee('---------- Forwarded message ----------')
            ->assertSee('name="in_reply_to" value=""', false)
            ->assertSee('name="references" value=""', false);
    }

    public function test_email_html_is_sanitized_with_a_business_safe_allowlist(): void
    {
        $sanitizer = app(SafeEmailHtmlSanitizer::class);
        $html = $sanitizer->sanitize('<p onclick="steal()">Hello <strong>team</strong></p><script>alert(1)</script><a href="javascript:alert(2)">bad</a><a href="https://example.com">safe</a>');

        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<strong>team</strong>', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
    }

    private function account(User $owner): MailboxAccount
    {
        return MailboxAccount::create([
            'company_id' => $owner->company_id,
            'user_id' => $owner->id,
            'name' => 'Company Sales',
            'email' => 'sales@company.test',
            'imap_host' => 'imap.company.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_validate_cert' => true,
            'smtp_host' => 'smtp.company.test',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'username' => 'sales@company.test',
            'secret' => 'app-password',
            'status' => 'active',
            'settings' => [],
        ]);
    }
}
