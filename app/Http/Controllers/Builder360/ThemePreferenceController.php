<?php

namespace App\Http\Controllers\Builder360;

use App\Application\Identity\Actions\UpdateThemePreference;
use App\Application\Identity\DTOs\ThemePreferenceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Builder360\ThemePreferenceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class ThemePreferenceController extends Controller
{
    public function __invoke(ThemePreferenceRequest $request, UpdateThemePreference $update): RedirectResponse|Response
    {
        $update->handle(new ThemePreferenceData($request->validated('theme')));

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back();
    }
}
