<?php

namespace Tests\Feature\AccessReview;

use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\User;
use Tests\TestCase;

class AdminCampaignCrudTest extends TestCase
{
    public function test_non_admin_cannot_view_index(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('access-review.campaigns.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_index(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('access-review.campaigns.index'))
            ->assertOk();
    }

    public function test_superuser_can_view_index(): void
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('access-review.campaigns.index'))
            ->assertOk();
    }

    public function test_non_admin_cannot_view_create_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('access-review.campaigns.create'))
            ->assertForbidden();
    }

    public function test_admin_can_view_create_form(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('access-review.campaigns.create'))
            ->assertOk();
    }

    public function test_admin_can_create_a_campaign(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('access-review.campaigns.store'), [
                'name' => 'Q2 License Review',
                'description' => 'Quarterly review of all reportee licenses.',
            ]);

        $response->assertRedirect(route('access-review.campaigns.index'));
        $this->assertDatabaseHas('access_review_campaigns', [
            'name' => 'Q2 License Review',
            'description' => 'Quarterly review of all reportee licenses.',
            'status' => AccessReviewCampaign::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);
    }

    public function test_campaign_is_created_without_launch_notifications_unless_asked_for(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.store'), [
                'name' => 'Silent By Default',
            ]);

        $this->assertFalse(
            AccessReviewCampaign::where('name', 'Silent By Default')->sole()->notify_managers_on_launch,
        );
    }

    public function test_admin_can_opt_a_campaign_into_launch_notifications(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.store'), [
                'name' => 'Noisy',
                'notify_managers_on_launch' => '1',
            ]);

        $this->assertTrue(
            AccessReviewCampaign::where('name', 'Noisy')->sole()->notify_managers_on_launch,
        );
    }

    public function test_launch_notifications_can_be_toggled_back_off_while_draft(): void
    {
        $campaign = AccessReviewCampaign::factory()->create(['notify_managers_on_launch' => true]);

        // The paired hidden field in the form submits "0" for an unticked box, so
        // clearing the checkbox has to actually clear the stored value.
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('access-review.campaigns.update', $campaign), [
                'name' => $campaign->name,
                'notify_managers_on_launch' => '0',
            ])
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertFalse($campaign->fresh()->notify_managers_on_launch);
    }

    public function test_non_admin_cannot_create_a_campaign(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('access-review.campaigns.store'), [
                'name' => 'Should not save',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('access_review_campaigns', 0);
    }

    public function test_creating_a_campaign_without_a_name_fails_validation(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.store'), [
                'description' => 'No name supplied',
            ])
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('access_review_campaigns', 0);
    }

    public function test_admin_can_view_edit_form_for_draft_campaign(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('access-review.campaigns.edit', $campaign))
            ->assertOk();
    }

    public function test_edit_form_redirects_when_campaign_is_not_draft(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('access-review.campaigns.edit', $campaign))
            ->assertRedirect(route('access-review.campaigns.index'));
    }

    public function test_admin_can_update_a_draft_campaign(): void
    {
        $campaign = AccessReviewCampaign::factory()->create(['name' => 'Old Name']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('access-review.campaigns.update', $campaign), [
                'name' => 'New Name',
                'description' => 'Updated description.',
            ])
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertDatabaseHas('access_review_campaigns', [
            'id' => $campaign->id,
            'name' => 'New Name',
            'description' => 'Updated description.',
        ]);
    }

    public function test_update_is_blocked_when_campaign_is_not_draft(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create(['name' => 'Frozen Name']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('access-review.campaigns.update', $campaign), [
                'name' => 'Should Not Change',
            ])
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertDatabaseHas('access_review_campaigns', [
            'id' => $campaign->id,
            'name' => 'Frozen Name',
        ]);
    }

    public function test_admin_can_delete_a_draft_campaign(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('access-review.campaigns.destroy', $campaign))
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertSoftDeleted('access_review_campaigns', ['id' => $campaign->id]);
    }

    public function test_admin_can_delete_a_non_draft_campaign(): void
    {
        // Campaigns of any status can now be soft-deleted (not just drafts).
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('access-review.campaigns.destroy', $campaign))
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertSoftDeleted('access_review_campaigns', ['id' => $campaign->id]);
    }

    public function test_admin_can_bulk_delete_draft_campaigns(): void
    {
        $a = AccessReviewCampaign::factory()->create();
        $b = AccessReviewCampaign::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.bulk-destroy'), [
                'bulk_actions' => 'delete',
                'ids' => [$a->id, $b->id],
            ])
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertSoftDeleted('access_review_campaigns', ['id' => $a->id]);
        $this->assertSoftDeleted('access_review_campaigns', ['id' => $b->id]);
    }

    public function test_bulk_delete_removes_campaigns_regardless_of_status(): void
    {
        $draft = AccessReviewCampaign::factory()->create();
        $active = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.bulk-destroy'), [
                'bulk_actions' => 'delete',
                'ids' => [$draft->id, $active->id],
            ])
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertSoftDeleted('access_review_campaigns', ['id' => $draft->id]);
        $this->assertSoftDeleted('access_review_campaigns', ['id' => $active->id]);
    }

    public function test_bulk_delete_rejects_non_integer_ids(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.bulk-destroy'), [
                'bulk_actions' => 'delete',
                'ids' => ['not-an-int'],
            ])
            ->assertSessionHasErrors(['ids.0']);

        $this->assertNotSoftDeleted('access_review_campaigns', ['id' => $campaign->id]);
    }

    public function test_bulk_delete_requires_ids(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.bulk-destroy'), [
                'bulk_actions' => 'delete',
            ])
            ->assertSessionHasErrors(['ids']);
    }

    public function test_admin_can_restore_a_deleted_campaign(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $campaign->delete();
        $this->assertSoftDeleted('access_review_campaigns', ['id' => $campaign->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.restore', $campaign))
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertNotSoftDeleted('access_review_campaigns', ['id' => $campaign->id]);
    }

    public function test_restore_rejects_a_campaign_that_is_not_deleted(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.restore', $campaign))
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertNotSoftDeleted('access_review_campaigns', ['id' => $campaign->id]);
    }

    public function test_non_admin_cannot_restore_a_campaign(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $campaign->delete();

        $this->actingAs(User::factory()->create())
            ->post(route('access-review.campaigns.restore', $campaign))
            ->assertForbidden();

        $this->assertSoftDeleted('access_review_campaigns', ['id' => $campaign->id]);
    }

    public function test_soft_deleting_a_campaign_keeps_its_items_for_a_lossless_restore(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();
        $items = AccessReviewItem::factory()->count(3)->create(['campaign_id' => $campaign->id]);

        $campaign->delete();

        // Items are NOT removed when the campaign is soft-deleted, so a restore is lossless.
        foreach ($items as $item) {
            $this->assertDatabaseHas('access_review_items', ['id' => $item->id]);
        }

        $campaign->restore();

        $this->assertNotSoftDeleted('access_review_campaigns', ['id' => $campaign->id]);
        $this->assertSame(3, $campaign->fresh()->items()->count());
    }

    public function test_deleted_view_lists_only_trashed_campaigns(): void
    {
        $live = AccessReviewCampaign::factory()->create(['name' => 'Live One']);
        $deleted = AccessReviewCampaign::factory()->create(['name' => 'Deleted One']);
        $deleted->delete();

        $response = $this->actingAsForApi(User::factory()->admin()->create())
            ->getJson(route('api.access-review.campaigns.index', ['status' => 'deleted']));

        $response->assertOk();
        $names = collect($response->json('rows'))->pluck('name')->all();
        $this->assertContains('Deleted One', $names);
        $this->assertNotContains('Live One', $names);
    }
}
