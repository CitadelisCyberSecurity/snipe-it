<?php

namespace Tests\Feature\AccessReview;

use App\Models\AccessReviewCampaign;
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

    public function test_delete_is_blocked_when_campaign_is_not_draft(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('access-review.campaigns.destroy', $campaign))
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertDatabaseHas('access_review_campaigns', ['id' => $campaign->id]);
    }
}
