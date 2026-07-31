<?php

namespace App\View\Components\Hr;

use App\Application\Hr\Data\PeopleWorkspaceLinkData;
use App\Domain\Hr\Services\PeopleWorkspaceNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class PeopleWorkspace extends Component
{
    /** @var array<int, PeopleWorkspaceLinkData> */
    public readonly array $navigationLinks;

    public function __construct(
        PeopleWorkspaceNavigation $navigation,
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly string $eyebrow = 'People / HRMS',
        public readonly string $active = 'employees',
        public readonly bool $selfService = false,
        public readonly bool $openCreate = false,
    ) {
        $this->navigationLinks = $navigation->links(request()->user());
    }

    public function render(): View
    {
        return view('components.hr.people-workspace', [
            'title' => $this->title,
            'description' => $this->description,
            'eyebrow' => $this->eyebrow,
            'active' => $this->active,
            'selfService' => $this->selfService,
            'openCreate' => $this->openCreate,
            'navigationLinks' => $this->navigationLinks,
        ]);
    }
}
