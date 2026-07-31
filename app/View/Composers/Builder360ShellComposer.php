<?php

namespace App\View\Composers;

use App\Services\Builder360\Builder360Bootstrap;
use App\Domain\Identity\Services\ThemePreference;
use App\Application\Shell\Actions\BuildBuilder360Shell;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\View\View;

final class Builder360ShellComposer
{
    public function __construct(
        private readonly Builder360Bootstrap $bootstrap,
        private readonly Guard $auth,
        private readonly ThemePreference $themePreference,
        private readonly BuildBuilder360Shell $buildShell,
    ) {
    }

    public function compose(View $view): void
    {
        $theme = $this->themePreference->current();
        $view->with('theme', $theme);

        if (array_key_exists('bootstrap', $view->getData())) {
            $view->with('shell', $this->buildShell->handle($view->getData()['bootstrap'], $theme));
            return;
        }

        $user = $this->auth->user();

        if ($user === null) {
            $view->with('bootstrap', []);

            return;
        }

        $selectedRoleSlug = session('builder360.selected_role_slug');
        $selectedProjectId = session('builder360.selected_project_id');
        $dashboardPeriod = session('builder360.dashboard_period');

        $bootstrap = $this->bootstrap->forRoleContext(
            $user,
            is_string($selectedRoleSlug) ? $selectedRoleSlug : null,
            is_numeric($selectedProjectId) ? (int) $selectedProjectId : null,
            is_array($dashboardPeriod) ? $dashboardPeriod : null,
        );

        $view->with('bootstrap', $bootstrap);
        $view->with('shell', $this->buildShell->handle($bootstrap, $theme));
    }
}
