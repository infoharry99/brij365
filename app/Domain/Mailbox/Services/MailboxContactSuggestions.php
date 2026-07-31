<?php

namespace App\Domain\Mailbox\Services;

use App\Models\Customer;
use App\Models\MailboxAccount;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Collection;

final class MailboxContactSuggestions
{
    /** @return Collection<int, array{email:string,name:string,source:string}> */
    public function forAccount(MailboxAccount $account, User $user): Collection
    {
        $correspondents = $account->emails()
            ->latest('received_at')
            ->limit(500)
            ->get(['from_addresses', 'to_addresses', 'cc_addresses'])
            ->flatMap(fn ($email): array => array_merge(
                $email->from_addresses ?? [],
                $email->to_addresses ?? [],
                $email->cc_addresses ?? [],
            ))
            ->filter(fn (mixed $item): bool => is_array($item) && filter_var($item['email'] ?? null, FILTER_VALIDATE_EMAIL))
            ->map(fn (array $item): array => [
                'email' => strtolower($item['email']),
                'name' => trim((string) ($item['name'] ?? $item['email'])),
                'source' => 'Previous correspondence',
            ]);

        $employees = User::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->whereNotNull('email')
            ->limit(200)
            ->get(['name', 'email'])
            ->map(fn (User $item): array => ['email' => strtolower($item->email), 'name' => $item->name, 'source' => 'Employee']);

        $vendors = Vendor::query()
            ->where('company_id', $user->company_id)
            ->whereNotNull('email')
            ->limit(200)
            ->get(['name', 'email'])
            ->map(fn (Vendor $item): array => ['email' => strtolower($item->email), 'name' => $item->name, 'source' => 'Vendor']);

        $customers = Customer::query()
            ->whereNotNull('email')
            ->whereHas('leads', fn ($query) => $query->where('company_id', $user->company_id))
            ->limit(200)
            ->get(['name', 'email'])
            ->map(fn (Customer $item): array => ['email' => strtolower($item->email), 'name' => $item->name, 'source' => 'Customer']);

        return $correspondents
            ->concat($employees)
            ->concat($vendors)
            ->concat($customers)
            ->filter(fn (array $item): bool => filter_var($item['email'], FILTER_VALIDATE_EMAIL) !== false)
            ->unique('email')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->take(500)
            ->values();
    }
}
