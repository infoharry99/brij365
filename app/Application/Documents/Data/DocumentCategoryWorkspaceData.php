<?php

namespace App\Application\Documents\Data;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class DocumentCategoryWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $categories,
        public array $filters,
        public array $ownerTypes,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
