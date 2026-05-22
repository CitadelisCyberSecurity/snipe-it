<?php

namespace Tests\Feature\AccessReview;

use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\User;
use Tests\TestCase;

class ManagerReviewTest extends TestCase
{
    // -----------------------------------------------------------------------
    // index
    // -----------------------------------------------------------------------

    public function test_unauthenticated_cannot_view_review_index(): void
    {
        $this->get(route('access-review.my-reviews.index'))
            ->assertRedirect();
    }

    public function test_authenticated_user_can_view_review_index(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('access-review.my-reviews.index'))
            ->assertOk();
    }

    public function test_index_only_shows_active_campaigns_with_items_for_the_manager(): void
    {
        $manager = User::factory()->create();
        $other   = User::factory()->create();

        $active = AccessReviewCampaign::factory()->active()->create();
        $draft  = AccessReviewCampaign::factory()->create();
        $closed = AccessReviewCampaign::factory()->closed()->create();

        // Only this item should count
        AccessReviewItem::factory()->create(['campaign_id' => $active->id, 'manager_id' => $manager->id]);
        // Different manager — should not appear for $manager
        AccessReviewItem::factory()->create(['campaign_id' => $active->id, 'manager_id' => $other->id]);

        $response = $this->actingAs($manager)
            ->get(route('access-review.my-reviews.index'))
            ->assertOk();

        $response->assertViewHas('campaigns', fn ($campaigns) =>
            $campaigns->count() === 1 && $campaigns->first()->id === $active->id
        );
    }

    // -----------------------------------------------------------------------
    // show
    // -----------------------------------------------------------------------

    public function test_manager_can_view_their_campaign_review_page(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();
        AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->get(route('access-review.my-reviews.show', $campaign))
            ->assertOk();
    }

    public function test_manager_cannot_view_campaign_they_have_no_items_in(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs($manager)
            ->get(route('access-review.my-reviews.show', $campaign))
            ->assertForbidden();
    }

    public function test_show_redirects_for_non_active_campaign(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->closed()->create();
        AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->get(route('access-review.my-reviews.show', $campaign))
            ->assertRedirect(route('access-review.my-reviews.index'));
    }

    // -----------------------------------------------------------------------
    // saveItem
    // -----------------------------------------------------------------------

    public function test_manager_can_save_a_keep_decision(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->patch(route('access-review.items.save', $item), [
                'manager_status'  => AccessReviewItem::STATUS_KEEP,
                'manager_comment' => '',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('access_review_items', [
            'id'             => $item->id,
            'manager_status' => AccessReviewItem::STATUS_KEEP,
        ]);
    }

    public function test_manager_can_save_a_delete_decision_with_comment(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->patch(route('access-review.items.save', $item), [
                'manager_status'  => AccessReviewItem::STATUS_DELETE,
                'manager_comment' => 'No longer needed.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('access_review_items', [
            'id'              => $item->id,
            'manager_status'  => AccessReviewItem::STATUS_DELETE,
            'manager_comment' => 'No longer needed.',
        ]);
    }

    public function test_manager_cannot_save_decision_for_another_managers_item(): void
    {
        $manager      = User::factory()->create();
        $otherManager = User::factory()->create();
        $campaign     = AccessReviewCampaign::factory()->active()->create();
        $item         = AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_id' => $otherManager->id]);

        $this->actingAs($manager)
            ->patch(route('access-review.items.save', $item), [
                'manager_status' => AccessReviewItem::STATUS_KEEP,
            ])
            ->assertForbidden();
    }

    public function test_manager_cannot_save_an_invalid_status(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->patch(route('access-review.items.save', $item), [
                'manager_status' => 'approve',
            ])
            ->assertSessionHasErrors('manager_status');
    }

    public function test_manager_cannot_save_decision_after_marking_complete(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()->completed()->create(['campaign_id' => $campaign->id, 'manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->patch(route('access-review.items.save', $item), [
                'manager_status' => AccessReviewItem::STATUS_DELETE,
            ])
            ->assertStatus(422);
    }

    public function test_manager_cannot_save_decision_on_non_active_campaign(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->closed()->create();
        $item     = AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->patch(route('access-review.items.save', $item), [
                'manager_status' => AccessReviewItem::STATUS_KEEP,
            ])
            ->assertStatus(422);
    }

    // -----------------------------------------------------------------------
    // complete
    // -----------------------------------------------------------------------

    public function test_manager_can_mark_review_complete(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_KEEP)
            ->create(['campaign_id' => $campaign->id, 'manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->post(route('access-review.my-reviews.complete', $campaign))
            ->assertRedirect(route('access-review.my-reviews.index'));

        $this->assertDatabaseHas('access_review_items', [
            'id'                   => $item->id,
            'manager_status'       => AccessReviewItem::STATUS_KEEP,
        ]);
        $this->assertNotNull($item->fresh()->manager_completed_at);
    }

    public function test_manager_cannot_mark_complete_with_unreviewed_items(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $item     = AccessReviewItem::factory()->create(['campaign_id' => $campaign->id, 'manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->post(route('access-review.my-reviews.complete', $campaign))
            ->assertRedirect();

        $this->assertNull($item->fresh()->manager_completed_at);
    }

    public function test_manager_cannot_mark_complete_for_campaign_they_have_no_items_in(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs($manager)
            ->post(route('access-review.my-reviews.complete', $campaign))
            ->assertForbidden();
    }

    public function test_manager_cannot_mark_complete_for_non_active_campaign(): void
    {
        $manager  = User::factory()->create();
        $campaign = AccessReviewCampaign::factory()->closed()->create();
        AccessReviewItem::factory()
            ->reviewedAs(AccessReviewItem::STATUS_KEEP)
            ->create(['campaign_id' => $campaign->id, 'manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->post(route('access-review.my-reviews.complete', $campaign))
            ->assertRedirect(route('access-review.my-reviews.index'));
    }
}
