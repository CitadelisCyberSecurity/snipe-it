<?php

namespace App\Http\Controllers\AccessReview;

use App\Actions\AccessReview\SnapshotCampaignItemsAction;
use App\Http\Controllers\Controller;
use App\Models\AccessReviewCampaign;
use Illuminate\Contracts\View\View;
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
            ->with('success', trans('admin/access-review/general.created'));
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
}
