<?php

namespace Tests\Feature;

use App\Application\Documents\Data\DocumentCategoryWorkspaceData;
use App\Application\Documents\Data\DocumentCommandData;
use App\Application\Documents\Data\ManagedDocumentWorkspaceData;
use ReflectionClass;
use Tests\TestCase;

class DocumentsApplicationLayerTest extends TestCase
{
    public function test_document_application_data_is_immutable(): void
    {
        foreach ([DocumentCommandData::class, DocumentCategoryWorkspaceData::class, ManagedDocumentWorkspaceData::class] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must be readonly.');
        }
    }

    public function test_document_controllers_use_focused_actions_without_queries_or_services(): void
    {
        foreach ([
            app_path('Http/Controllers/Documents/DocumentCategoryController.php'),
            app_path('Http/Controllers/Documents/ManagedDocumentController.php'),
        ] as $path) {
            $source = file_get_contents($path);

            $this->assertIsString($source);
            $this->assertStringContainsString('App\\Application\\Documents\\Actions', $source);
            $this->assertStringNotContainsString('::query()', $source);
            $this->assertStringNotContainsString('App\\Services\\', $source);
        }
    }
}
