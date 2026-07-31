<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalSchemaIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_audit_and_notification_query_indexes_exist(): void
    {
        $this->assertSqliteTableHasIndexes('audit_events', [
            'audit_events_user_created_index',
            'audit_events_type_created_index',
            'audit_events_auditable_created_index',
            'audit_events_request_context_index',
        ]);

        $this->assertSqliteTableHasIndexes('user_notifications', [
            'notifications_recipient_created_index',
            'notifications_recipient_status_created_index',
            'notifications_recipient_category_status_index',
            'notifications_recipient_severity_status_index',
        ]);
    }

    /**
     * @param array<int, string> $expectedIndexes
     */
    private function assertSqliteTableHasIndexes(string $table, array $expectedIndexes): void
    {
        $indexes = collect(DB::select("PRAGMA index_list('{$table}')"))
            ->pluck('name')
            ->all();

        foreach ($expectedIndexes as $index) {
            $this->assertContains($index, $indexes, "Expected index [{$index}] to exist on [{$table}].");
        }
    }
}
