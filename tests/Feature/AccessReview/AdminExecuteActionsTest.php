<?php

namespace Tests\Feature\AccessReview;

use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;
use Tests\TestCase;

class AdminExecuteActionsTest extends TestCase
{
    private function executeUrl(AccessReviewCampaign $campaign, AccessReviewItem $item): string
    {
        return route('access-review.campaigns.items.execute', [$campaign, $item]);
    }

    // -----------------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_KEEP)
            ->create(['campaign_id' => $campaign->id]);

        $this->post($this->executeUrl($campaign, $item))
            ->assertRedirect();
    }

    public function test_non_admin_cannot_execute(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_KEEP)
            ->create(['campaign_id' => $campaign->id]);

        $this->actingAs(User::factory()->create())
            ->post($this->executeUrl($campaign, $item))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Validation guards
    // -----------------------------------------------------------------------

    public function test_cannot_execute_item_in_draft_campaign(): void
    {
        $admin    = User::factory()->admin()->create();
        $campaign = AccessReviewCampaign::factory()->create(); // draft
        $item     = AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_KEEP)
            ->create(['campaign_id' => $campaign->id]);

        $this->actingAs($admin)
            ->postJson($this->executeUrl($campaign, $item))
            ->assertStatus(422);
    }

    public function test_cannot_execute_item_that_has_no_decision(): void
    {
        $admin    = User::factory()->admin()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()->create([
            'campaign_id'    => $campaign->id,
            'manager_status' => null,
        ]);

        $this->actingAs($admin)
            ->postJson($this->executeUrl($campaign, $item))
            ->assertStatus(422);
    }

    public function test_cannot_execute_already_executed_item(): void
    {
        $admin    = User::factory()->admin()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()
            ->executed()
            ->create(['campaign_id' => $campaign->id]);

        $this->actingAs($admin)
            ->postJson($this->executeUrl($campaign, $item))
            ->assertStatus(422);
    }

    public function test_item_from_different_campaign_returns_404(): void
    {
        $admin     = User::factory()->admin()->create();
        $campaign  = AccessReviewCampaign::factory()->active()->create();
        $campaign2 = AccessReviewCampaign::factory()->active()->create();
        $item      = AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_KEEP)
            ->create(['campaign_id' => $campaign2->id]);

        $this->actingAs($admin)
            ->postJson($this->executeUrl($campaign, $item))
            ->assertNotFound();
    }

    // -----------------------------------------------------------------------
    // Keep / Modify — mark executed, no seat change
    // -----------------------------------------------------------------------

    public function test_executing_keep_item_marks_it_executed(): void
    {
        $admin    = User::factory()->admin()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_KEEP)
            ->create(['campaign_id' => $campaign->id]);

        $this->actingAs($admin)
            ->postJson($this->executeUrl($campaign, $item))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('access_review_items', [
            'id'                 => $item->id,
            'admin_executed_by'  => $admin->id,
        ]);
        $this->assertNotNull($item->fresh()->admin_executed_at);
    }

    public function test_executing_modify_item_marks_it_executed_without_seat_change(): void
    {
        $admin    = User::factory()->admin()->create();
        $user     = User::factory()->create();
        $license  = License::factory()->create();
        $seat     = LicenseSeat::factory()->create(['license_id' => $license->id, 'assigned_to' => $user->id]);
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_MODIFY)
            ->create([
                'campaign_id'     => $campaign->id,
                'user_id'         => $user->id,
                'license_seat_id' => $seat->id,
            ]);

        $this->actingAs($admin)
            ->postJson($this->executeUrl($campaign, $item))
            ->assertOk();

        // Seat should remain assigned
        $this->assertDatabaseHas('license_seats', [
            'id'          => $seat->id,
            'assigned_to' => $user->id,
        ]);
        $this->assertNotNull($item->fresh()->admin_executed_at);
    }

    // -----------------------------------------------------------------------
    // Delete — real seat checkin
    // -----------------------------------------------------------------------

    public function test_executing_delete_item_checks_in_the_license_seat(): void
    {
        $admin    = User::factory()->admin()->create();
        $user     = User::factory()->create();
        $license  = License::factory()->create(['reassignable' => 1]);
        $seat     = LicenseSeat::factory()->create(['license_id' => $license->id, 'assigned_to' => $user->id]);
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_DELETE)
            ->create([
                'campaign_id'     => $campaign->id,
                'user_id'         => $user->id,
                'license_id'      => $license->id,
                'license_seat_id' => $seat->id,
            ]);

        $this->actingAs($admin)
            ->postJson($this->executeUrl($campaign, $item))
            ->assertOk()
            ->assertJson(['success' => true]);

        // Seat cleared
        $this->assertDatabaseHas('license_seats', [
            'id'          => $seat->id,
            'assigned_to' => null,
            'asset_id'    => null,
        ]);

        // Item marked executed
        $this->assertNotNull($item->fresh()->admin_executed_at);
        $this->assertEquals($admin->id, $item->fresh()->admin_executed_by);
    }

    public function test_executing_delete_does_not_revoke_a_seat_reassigned_to_another_user(): void
    {
        $admin     = User::factory()->admin()->create();
        $reviewed  = User::factory()->create();
        $newHolder = User::factory()->create();
        $license   = License::factory()->create(['reassignable' => 1]);
        // Seat was reviewed for $reviewed, but has since been re-issued to $newHolder.
        $seat      = LicenseSeat::factory()->create(['license_id' => $license->id, 'assigned_to' => $newHolder->id]);
        $campaign  = AccessReviewCampaign::factory()->active()->create();
        $item      = AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_DELETE)
            ->create([
                'campaign_id'     => $campaign->id,
                'user_id'         => $reviewed->id,
                'license_id'      => $license->id,
                'license_seat_id' => $seat->id,
            ]);

        $this->actingAs($admin)
            ->postJson($this->executeUrl($campaign, $item))
            ->assertOk()
            ->assertJsonStructure(['success', 'warning']);

        // The current (innocent) holder must NOT be revoked.
        $this->assertDatabaseHas('license_seats', [
            'id'          => $seat->id,
            'assigned_to' => $newHolder->id,
        ]);

        // Item is still marked executed (the reviewed assignment no longer exists).
        $this->assertNotNull($item->fresh()->admin_executed_at);
    }

    public function test_executing_delete_when_seat_already_unassigned_still_marks_item_executed(): void
    {
        $admin    = User::factory()->admin()->create();
        $license  = License::factory()->create();
        $seat     = LicenseSeat::factory()->create([
            'license_id'  => $license->id,
            'assigned_to' => null,
            'asset_id'    => null,
        ]);
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_DELETE)
            ->create([
                'campaign_id'     => $campaign->id,
                'license_seat_id' => $seat->id,
            ]);

        $this->actingAs($admin)
            ->postJson($this->executeUrl($campaign, $item))
            ->assertOk();

        $this->assertNotNull($item->fresh()->admin_executed_at);
    }

    // -----------------------------------------------------------------------
    // Execute on closed campaign
    // -----------------------------------------------------------------------

    public function test_admin_can_execute_item_on_closed_campaign(): void
    {
        $admin    = User::factory()->admin()->create();
        $campaign = AccessReviewCampaign::factory()->closed()->create();
        $item     = AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_KEEP)
            ->create(['campaign_id' => $campaign->id]);

        $this->actingAs($admin)
            ->postJson($this->executeUrl($campaign, $item))
            ->assertOk();

        $this->assertNotNull($item->fresh()->admin_executed_at);
    }
}
