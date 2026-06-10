<?php

namespace App\Http\Controllers\AccessReview;

use App\Actions\AccessReview\SnapshotCampaignItemsAction;
use App\Events\CheckoutableCheckedIn;
use App\Http\Controllers\Controller;
use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\Asset;
use App\Models\User;
use App\Notifications\AccessReviewCampaignLaunchedNotification;
use App\Notifications\AccessReviewReminderNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        parent::__construct();
    }

    public function index(): View
    {
        $this->authorize('admin');

        $campaigns = AccessReviewCampaign::with('creator')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('access-review.campaigns.index', compact('campaigns'));
    }

    public function create(): View
    {
        $this->authorize('admin');

        return view('access-review.campaigns.edit')->with('item', new AccessReviewCampaign);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
        ]);

        $campaign = new AccessReviewCampaign($data);
        $campaign->status = AccessReviewCampaign::STATUS_DRAFT;
        $campaign->created_by = auth()->id();
        $campaign->save();

        return redirect()
            ->route('access-review.campaigns.index')
            ->with('success', trans('admin/access-review/general.created'))
            ->with('created_id', $campaign->id);
    }

    public function edit(AccessReviewCampaign $campaign): View|RedirectResponse
    {
        $this->authorize('admin');

        if (! $campaign->isDraft()) {
            return redirect()
                ->route('access-review.campaigns.index')
                ->with('error', trans('admin/access-review/general.not_editable_unless_draft'));
        }

        return view('access-review.campaigns.edit')->with('item', $campaign);
    }

    public function update(Request $request, AccessReviewCampaign $campaign): RedirectResponse
    {
        $this->authorize('admin');

        if (! $campaign->isDraft()) {
            return redirect()
                ->route('access-review.campaigns.index')
                ->with('error', trans('admin/access-review/general.not_editable_unless_draft'));
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
        ]);

        $campaign->update($data);

        return redirect()
            ->route('access-review.campaigns.index')
            ->with('success', trans('admin/access-review/general.updated'));
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $ids    = (array) $request->input('ids', []);
        $action = $request->input('bulk_actions');

        if ($action !== 'delete' || empty($ids)) {
            return redirect()->route('access-review.campaigns.index')
                ->with('error', trans('general.no_results_found'));
        }

        AccessReviewCampaign::whereIn('id', $ids)->each(function (AccessReviewCampaign $campaign) {
            if ($campaign->isDraft()) {
                DB::transaction(function () use ($campaign) {
                    $campaign->items()->delete();
                    $campaign->delete();
                });
            }
        });

        return redirect()
            ->route('access-review.campaigns.index')
            ->with('success', trans('admin/access-review/general.deleted'));
    }

    public function destroy(AccessReviewCampaign $campaign): RedirectResponse
    {
        $this->authorize('admin');

        if (! $campaign->isDraft()) {
            return redirect()
                ->route('access-review.campaigns.index')
                ->with('error', trans('admin/access-review/general.not_deletable_unless_draft'));
        }

        DB::transaction(function () use ($campaign) {
            $campaign->items()->delete();
            $campaign->delete();
        });

        return redirect()
            ->route('access-review.campaigns.index')
            ->with('success', trans('admin/access-review/general.deleted'));
    }

    public function launch(AccessReviewCampaign $campaign): RedirectResponse
    {
        $this->authorize('admin');

        if (! $campaign->isDraft()) {
            return redirect()
                ->route('access-review.campaigns.index')
                ->with('error', trans('admin/access-review/general.not_launchable_unless_draft'));
        }

        $count = DB::transaction(function () use ($campaign) {
            $locked = AccessReviewCampaign::lockForUpdate()->find($campaign->id);

            if (! $locked || ! $locked->isDraft()) {
                return null;
            }

            $items = SnapshotCampaignItemsAction::run($locked);

            $locked->status = AccessReviewCampaign::STATUS_ACTIVE;
            $locked->launched_at = now();
            $locked->save();

            return $items;
        });

        if ($count === null) {
            return redirect()
                ->route('access-review.campaigns.index')
                ->with('error', trans('admin/access-review/general.not_launchable_unless_draft'));
        }

        $campaign->items()
            ->with('manager')
            ->get()
            ->groupBy('manager_id')
            ->each(function ($managerItems) use ($campaign) {
                $manager = $managerItems->first()->manager;
                if ($manager && $manager->email) {
                    $manager->notify(new AccessReviewCampaignLaunchedNotification($campaign, $managerItems->count()));
                }
            });

        return redirect()
            ->route('access-review.campaigns.index')
            ->with('success', trans('admin/access-review/general.launched', ['count' => $count]));
    }

    public function close(AccessReviewCampaign $campaign): RedirectResponse
    {
        $this->authorize('admin');

        if (! $campaign->isActive()) {
            return redirect()
                ->route('access-review.campaigns.index')
                ->with('error', trans('admin/access-review/general.not_closable_unless_active'));
        }

        $closed = DB::transaction(function () use ($campaign) {
            $locked = AccessReviewCampaign::lockForUpdate()->find($campaign->id);

            if (! $locked || ! $locked->isActive()) {
                return false;
            }

            $locked->status = AccessReviewCampaign::STATUS_CLOSED;
            $locked->closed_at = now();
            $locked->save();

            return true;
        });

        if (! $closed) {
            return redirect()
                ->route('access-review.campaigns.index')
                ->with('error', trans('admin/access-review/general.not_closable_unless_active'));
        }

        return redirect()
            ->route('access-review.campaigns.index')
            ->with('success', trans('admin/access-review/general.closed'));
    }

    public function results(AccessReviewCampaign $campaign): View
    {
        $this->authorize('admin');

        $items = $campaign->items()
            ->with(['user', 'manager', 'license', 'executedBy'])
            ->orderBy('manager_id')
            ->orderBy('manager_status')
            ->get();

        $submitted = $items->whereNotNull('manager_completed_at');

        $summary = [
            'total'    => $items->count(),
            'keep'     => $submitted->where('manager_status', AccessReviewItem::STATUS_KEEP)->count(),
            'modify'   => $submitted->where('manager_status', AccessReviewItem::STATUS_MODIFY)->count(),
            'delete'   => $submitted->where('manager_status', AccessReviewItem::STATUS_DELETE)->count(),
            'pending'  => $items->filter(fn ($i) => $i->manager_completed_at === null)->count(),
            'executed' => $items->filter(fn ($i) => $i->isExecuted())->count(),
        ];

        $managers = $items->groupBy('manager_id')->map(function ($managerItems) {
            $first = $managerItems->first();
            return [
                'id'    => $first->manager_id,
                'name'  => $first->manager
                    ? trim($first->manager->first_name.' '.$first->manager->last_name)
                    : '—',
                'email' => $first->manager?->email,
                'total' => $managerItems->count(),
                'done'  => $managerItems->every(fn ($i) => $i->manager_completed_at !== null),
            ];
        })->values();

        return view('access-review.campaigns.results', compact('campaign', 'items', 'summary', 'managers'));
    }

    public function remindManager(AccessReviewCampaign $campaign, User $manager): JsonResponse
    {
        $this->authorize('admin');

        if (! $manager->email) {
            return response()->json([
                'error' => trans('admin/access-review/general.reminder_no_email'),
            ], 422);
        }

        $itemCount = $campaign->items()->where('manager_id', $manager->id)->count();

        $manager->notify(new AccessReviewReminderNotification($campaign, $itemCount));

        return response()->json([
            'success' => trans('admin/access-review/general.reminder_sent', ['name' => $manager->first_name]),
        ]);
    }

    public function executeItem(Request $request, AccessReviewCampaign $campaign, AccessReviewItem $item): JsonResponse
    {
        $this->authorize('admin');

        if ($campaign->isDraft()) {
            return response()->json(['error' => trans('admin/access-review/general.campaign_must_be_launched')], 422);
        }

        if ($item->campaign_id !== $campaign->id) {
            abort(404);
        }

        if ($item->isExecuted()) {
            return response()->json(['error' => trans('admin/access-review/general.item_already_executed')], 422);
        }

        if ($item->manager_status === null) {
            return response()->json(['error' => trans('admin/access-review/general.item_no_decision')], 422);
        }

        DB::transaction(function () use ($item, $campaign) {
            $lockedItem = AccessReviewItem::lockForUpdate()->find($item->id);

            if ($lockedItem->isExecuted()) {
                return;
            }

            if ($lockedItem->manager_status === AccessReviewItem::STATUS_DELETE) {
                $licenseSeat = $lockedItem->licenseSeat;

                if ($licenseSeat && ($licenseSeat->assigned_to || $licenseSeat->asset_id)) {
                    $checkedOutTo = $licenseSeat->assigned_to
                        ? User::withTrashed()->find($licenseSeat->assigned_to)
                        : Asset::find($licenseSeat->asset_id);

                    $notes = 'Checked in via Access Review: '.$campaign->name;

                    $licenseSeat->assigned_to = null;
                    $licenseSeat->asset_id = null;
                    $licenseSeat->notes = $notes;

                    if ($licenseSeat->license && ! $licenseSeat->license->reassignable) {
                        $licenseSeat->unreassignable_seat = true;
                    }

                    $licenseSeat->save();

                    event(new CheckoutableCheckedIn($licenseSeat, $checkedOutTo, auth()->user(), $notes));
                }
            }

            $lockedItem->admin_executed_at = now();
            $lockedItem->admin_executed_by = auth()->id();
            $lockedItem->save();
        });

        return response()->json(['success' => true]);
    }
}
