<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\GivingType;
use App\Models\OfferingCollection;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\OfferingCollectionStatus;
use App\PermissionCode;
use App\Services\BranchAccessService;
use App\Services\ChurchContext;
use App\Services\OfferingPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OfferingWorkflowController extends Controller
{
    public function index(BranchAccessService $access): View
    {
        $captureBranchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::OfferingsCapture);
        $verifyBranchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::OfferingsVerify);
        $ids = $captureBranchIds->merge($verifyBranchIds)->unique();
        $hasGlobalAccess = $access->allows(backpack_user(), PermissionCode::OfferingsCapture)
            || $access->allows(backpack_user(), PermissionCode::OfferingsVerify);
        abort_if($ids->isEmpty() && ! $hasGlobalAccess, 403);

        return view('admin.offerings.index', ['collections' => OfferingCollection::query()->whereIn('branch_id', $ids)->withCount('items')->latest('date')->paginate(25)]);
    }

    public function create(BranchAccessService $access, ChurchContext $church): View
    {
        $ids = $access->accessibleBranchIds(backpack_user(), PermissionCode::OfferingsCapture);
        abort_if($ids->isEmpty() && ! $access->allows(backpack_user(), PermissionCode::OfferingsCapture), 403);

        return view('admin.offerings.capture', ['branches' => Branch::query()->whereIn('id', $ids)->orderBy('name')->get(), 'services' => Service::query()->whereIn('branch_id', $ids)->latest('date')->get(), 'givingTypes' => GivingType::query()->where('is_active', true)->get(), 'funds' => Fund::query()->where('is_active', true)->get(), 'paymentMethods' => PaymentMethod::query()->where('is_active', true)->get(), 'accounts' => FinancialAccount::query()->whereIn('branch_id', $ids)->where('is_active', true)->get(), 'currency' => $church->currency()]);
    }

    public function store(Request $request, BranchAccessService $access): RedirectResponse
    {
        $data = $request->validate(['service_id' => ['required', 'exists:services,id'], 'branch_id' => ['required', 'exists:branches,id'], 'date' => ['required', 'date'], 'status' => ['required', 'in:draft,counted'], 'notes' => ['nullable', 'string'], 'items' => ['required', 'array', 'min:1'], 'items.*.giving_type_id' => ['required', 'exists:giving_types,id'], 'items.*.fund_id' => ['required', 'exists:funds,id'], 'items.*.payment_method_id' => ['required', 'exists:payment_methods,id'], 'items.*.financial_account_id' => ['required', 'exists:financial_accounts,id'], 'items.*.member_id' => ['nullable', 'exists:members,id'], 'items.*.giver_name' => ['nullable', 'string'], 'items.*.giver_phone' => ['nullable', 'string'], 'items.*.amount' => ['required', 'decimal:0,4', 'gt:0'], 'items.*.currency' => ['required', 'string', 'size:3']]);
        abort_unless($access->allows(backpack_user(), PermissionCode::OfferingsCapture, $data['branch_id']), 403);
        $collection = DB::transaction(function () use ($data): OfferingCollection {
            $collection = OfferingCollection::query()->create([...array_diff_key($data, ['items' => true]), 'counted_by' => backpack_user()->id]);
            $collection->items()->createMany($data['items']);

            return $collection;
        });

        return redirect()->route('admin.offerings.index')->with('success', 'Offering collection saved with '.$collection->items()->count().' items.');
    }

    public function verify(OfferingCollection $collection, BranchAccessService $access): RedirectResponse
    {
        abort_unless($access->allows(backpack_user(), PermissionCode::OfferingsVerify, $collection->branch_id), 403);
        abort_unless($collection->status === OfferingCollectionStatus::Counted, 422, 'Only counted offerings can be verified.');
        $collection->update(['status' => OfferingCollectionStatus::Verified, 'verified_by' => backpack_user()->id, 'verified_at' => now()]);

        return back()->with('success', 'Offering verified.');
    }

    public function post(OfferingCollection $collection, OfferingPostingService $posting, BranchAccessService $access): RedirectResponse
    {
        abort_unless($access->allows(backpack_user(), PermissionCode::OfferingsVerify, $collection->branch_id), 403);
        $posting->post($collection, backpack_user());

        return back()->with('success', 'Offering posted to income.');
    }
}
