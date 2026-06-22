<?php

namespace Tests\Feature\AccessReview;

use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\User;
use Tests\TestCase;

class AdminResultsTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->get(route('access-review.campaigns.results', $campaign))
            ->assertRedirect();
    }

    public function test_non_admin_cannot_view_results(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('access-review.campaigns.results', $campaign))
            ->assertForbidden();
    }

    public function test_admin_can_view_results_for_active_campaign(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('access-review.campaigns.results', $campaign))
            ->assertOk();
    }

    public function test_admin_can_view_results_for_closed_campaign(): void
    {
        $campaign = AccessReviewCampaign::factory()->closed()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('access-review.campaigns.results', $campaign))
            ->assertOk();
    }

    public function test_superuser_can_view_results(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('access-review.campaigns.results', $campaign))
            ->assertOk();
    }

    // -----------------------------------------------------------------------
    // Summary counts
    // -----------------------------------------------------------------------

    public function test_results_page_passes_correct_summary_counts(): void
    {
        $admin    = User::factory()->admin()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $submitted = ['manager_completed_at' => now()];
        AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_status' => AccessReviewItem::STATUS_KEEP] + $submitted);
        AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_status' => AccessReviewItem::STATUS_KEEP] + $submitted);
        AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_status' => AccessReviewItem::STATUS_MODIFY] + $submitted);
        AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_status' => AccessReviewItem::STATUS_DELETE] + $submitted);
        AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_status' => null]); // unsubmitted → pending
        AccessReviewItem::factory()->executed()->create(['campaign_id' => $campaign->id]);               // submitted + executed, keep

        $response = $this->actingAs($admin)
            ->get(route('access-review.campaigns.results', $campaign))
            ->assertOk();

        // keep=3 (2 submitted keep + 1 executed keep), modify=1, delete=1, pending=1 (unsubmitted), executed=1
        $response->assertViewHas('summary', fn ($s) =>
            $s['total']    === 6 &&
            $s['keep']     === 3 &&
            $s['modify']   === 1 &&
            $s['delete']   === 1 &&
            $s['pending']  === 1 &&
            $s['executed'] === 1
        );
    }

    // -----------------------------------------------------------------------
    // Manager progress
    // -----------------------------------------------------------------------

    public function test_results_page_shows_manager_as_done_when_all_items_completed(): void
    {
        $admin    = User::factory()->admin()->create();
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();

        AccessReviewItem::factory()->completed()->create([
            'campaign_id' => $campaign->id,
            'manager_id'  => $manager->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('access-review.campaigns.results', $campaign))
            ->assertOk();

        $response->assertViewHas('managers', fn ($managers) =>
            $managers->count() === 1 && $managers->first()['done'] === true
        );
    }

    public function test_results_page_shows_manager_as_not_done_when_items_incomplete(): void
    {
        $admin    = User::factory()->admin()->create();
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();

        // One completed, one not
        AccessReviewItem::factory()->completed()->create([
            'campaign_id' => $campaign->id,
            'manager_id'  => $manager->id,
        ]);
        AccessReviewItem::factory()->create([
            'campaign_id'          => $campaign->id,
            'manager_id'           => $manager->id,
            'manager_completed_at' => null,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('access-review.campaigns.results', $campaign))
            ->assertOk();

        $response->assertViewHas('managers', fn ($managers) =>
            $managers->count() === 1 && $managers->first()['done'] === false
        );
    }

    public function test_results_page_groups_items_by_manager(): void
    {
        $admin    = User::factory()->admin()->create();
        $mgr1     = User::factory()->create();
        $mgr2     = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();

        AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_id' => $mgr1->id]);
        AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_id' => $mgr1->id]);
        AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_id' => $mgr2->id]);

        $response = $this->actingAs($admin)
            ->get(route('access-review.campaigns.results', $campaign))
            ->assertOk();

        $response->assertViewHas('managers', fn ($managers) => $managers->count() === 2);
    }
}
