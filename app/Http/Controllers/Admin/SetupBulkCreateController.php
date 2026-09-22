<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkSetupRequest;
use App\Services\SetupBulkRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SetupBulkCreateController extends Controller
{
    public function create(string $resource, SetupBulkRegistry $registry): View
    {
        return view('admin.setup-bulk-create', [
            'resource' => $resource,
            'definition' => $registry->definition($resource),
        ]);
    }

    public function store(BulkSetupRequest $request, string $resource, SetupBulkRegistry $registry): RedirectResponse
    {
        $definition = $registry->definition($resource);
        $modelClass = $definition['model'];
        $items = $request->validated('items');
        $normalizedItems = $registry->normalizeItems($resource, $items);

        DB::transaction(function () use ($modelClass, $normalizedItems): void {
            foreach ($normalizedItems as $item) {
                $modelClass::query()->create($item);
            }
        });

        return redirect()
            ->route($resource.'.index')
            ->with('success', count($items).' '.$definition['label'].' created successfully.');
    }
}
