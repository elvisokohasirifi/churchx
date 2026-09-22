<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateChurchRequest;
use App\Models\Church;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChurchSettingsController extends Controller
{
    public function edit(): View
    {
        $church = Church::query()->firstOrFail();
        Gate::forUser(backpack_user())->authorize('update', $church);

        return view('admin.church-settings.edit', [
            'church' => $church,
            'title' => 'Church Settings',
            'breadcrumbs' => ['Admin' => backpack_url('dashboard'), 'Church Settings' => false],
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(UpdateChurchRequest $request, AuditLogService $audit): RedirectResponse
    {
        $church = Church::query()->firstOrFail();
        $before = $church->only(['name', 'email', 'phone', 'website', 'address', 'country', 'currency', 'timezone', 'logo']);
        $attributes = $request->safe()->except('logo');
        $attributes['currency'] = Str::upper($attributes['currency']);

        if ($request->hasFile('logo')) {
            $attributes['logo'] = $request->file('logo')->store('churches', 'public');
        }

        $church->update($attributes);
        $after = $church->fresh()->only(array_keys($before));
        $changed = array_keys(array_diff_assoc($after, $before));

        $audit->record(
            'church.settings.updated',
            backpack_user(),
            $church,
            ['before' => Arr::only($before, $changed), 'after' => Arr::only($after, $changed)],
            request: $request,
        );

        return back()->with('success', 'Church settings updated.');
    }
}
