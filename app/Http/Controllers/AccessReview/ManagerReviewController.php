<?php

namespace App\Http\Controllers\AccessReview;

use App\Http\Controllers\Controller;
use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManagerReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        parent::__construct();
    }

    public function index(): View
    {
        $campaigns = AccessReviewCampaign::where('status', AccessReviewCampaign::STATUS_ACTIVE)
            ->whereHas('items', fn ($q) => $q->where('manager_id', auth()->id()))
            ->withCount([
                'items as my_items_count'     => fn ($q) => $q->where('manager_id', auth()->id()),
                'items as my_reviewed_count'  => fn ($q) => $q->where('manager_id', auth()->id())->whereNotNull('manager_status'),
                'items as my_completed_count' => fn ($q) => $q->where('manager_id', auth()->id())->whereNotNull('manager_completed_at'),
            ])
            ->orderByDesc('launched_at')
            ->get();

        return view('access-review.my-reviews.index', compact('campaigns'));
    }

    public function show(AccessReviewCampaign $campaign): View|RedirectResponse
    {
        if (! $campaign->isActive()) {
            return redirect()
                ->route('access-review.my-reviews.index')
                ->with('error', trans('admin/access-review/general.campaign_not_active'));
        }

        $items = AccessReviewItem::where('campaign_id', $campaign->id)
            ->where('manager_id', auth()->id())
            ->with(['user', 'license'])
            ->orderBy('license_name_snapshot')
            ->get();

        if ($items->isEmpty()) {
            abort(403);
        }

        return view('access-review.my-reviews.show', compact('campaign', 'items'));
    }

    public function saveItem(Request $request, AccessReviewCampaign $campaign, AccessReviewItem $item): JsonResponse
    {
        if ($item->manager_id !== auth()->id() || $item->campaign_id !== $campaign->id) {
            abort(403);
        }

        if (! $campaign->isActive()) {
            return response()->json(['error' => trans('admin/access-review/general.campaign_not_active')], 422);
        }

        if ($item->isCompleted()) {
            return response()->json(['error' => trans('admin/access-review/general.review_already_completed')], 422);
        }

        $validated = $request->validate([
            'manager_status'  => ['required', Rule::in(AccessReviewItem::VALID_STATUSES)],
            'manager_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $item->fill($validated)->save();

        return response()->json(['success' => true]);
    }

    public function complete(AccessReviewCampaign $campaign): RedirectResponse
    {
        if (! $campaign->isActive()) {
            return redirect()
                ->route('access-review.my-reviews.index')
                ->with('error', trans('admin/access-review/general.campaign_not_active'));
        }

        $myItemsExist = AccessReviewItem::where('campaign_id', $campaign->id)
            ->where('manager_id', auth()->id())
            ->exists();

        if (! $myItemsExist) {
            abort(403);
        }

        $hasUnreviewed = false;

        DB::transaction(function () use ($campaign, &$hasUnreviewed) {
            $hasUnreviewed = AccessReviewItem::where('campaign_id', $campaign->id)
                ->where('manager_id', auth()->id())
                ->whereNull('manager_status')
                ->lockForUpdate()
                ->exists();

            if (! $hasUnreviewed) {
                AccessReviewItem::where('campaign_id', $campaign->id)
                    ->where('manager_id', auth()->id())
                    ->whereNull('manager_completed_at')
                    ->update(['manager_completed_at' => now()]);
            }
        });

        if ($hasUnreviewed) {
            return redirect()
                ->back()
                ->with('error', trans('admin/access-review/general.cannot_complete_unreviewed'));
        }

        return redirect()
            ->route('access-review.my-reviews.index')
            ->with('success', trans('admin/access-review/general.review_complete'));
    }
}
