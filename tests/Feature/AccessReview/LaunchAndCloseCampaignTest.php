<?php

namespace Tests\Feature\AccessReview;

use App\Models\AccessReviewCampaign;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;
use Tests\TestCase;

class LaunchAndCloseCampaignTest extends TestCase
{
    public function test_admin_can_launch_a_draft_campaign(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.launch', $campaign))
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertDatabaseHas('access_review_campaigns', [
            'id' => $campaign->id,
            'status' => AccessReviewCampaign::STATUS_ACTIVE,
        ]);

        $this->assertNotNull($campaign->fresh()->launched_at);
    }

    public function test_launching_snapshots_items_from_reportee_license_assignments(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $manager = User::factory()->create();
        $reportee = User::factory()->create(['manager_id' => $manager->id]);
        $license = License::factory()->create();
        LicenseSeat::factory()->assignedToUser($reportee)->create(['license_id' => $license->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.launch', $campaign));

        $this->assertDatabaseHas('access_review_items', [
            'campaign_id' => $campaign->id,
            'user_id' => $reportee->id,
            'manager_id' => $manager->id,
            'license_id' => $license->id,
        ]);
    }

    public function test_launch_succeeds_with_a_warning_when_notification_email_cannot_be_sent(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $manager = User::factory()->create(['email' => 'manager@example.com']);
        $reportee = User::factory()->create(['manager_id' => $manager->id]);
        $license = License::factory()->create();
        LicenseSeat::factory()->assignedToUser($reportee)->create(['license_id' => $license->id]);

        // Simulate the notification layer failing (e.g. unconfigured/unreachable SMTP)
        // WITHOUT any real mail transport or network call: make delivery throw.
        $this->mock(\Illuminate\Contracts\Notifications\Dispatcher::class, function ($mock) {
            $mock->shouldIgnoreMissing();
            $mock->shouldReceive('send')->andThrow(new \RuntimeException('mail transport unavailable'));
            $mock->shouldReceive('sendNow')->andThrow(new \RuntimeException('mail transport unavailable'));
        });

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.launch', $campaign));

        // No 500 — it redirects, reports success (it did launch), and flashes a red
        // "emails could not be sent" error.
        $response->assertRedirect(route('access-review.campaigns.index'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('error');

        // The campaign is still launched despite the email failure.
        $this->assertDatabaseHas('access_review_campaigns', [
            'id' => $campaign->id,
            'status' => AccessReviewCampaign::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('access_review_items', [
            'campaign_id' => $campaign->id,
            'manager_id' => $manager->id,
        ]);
    }

    public function test_launching_a_non_draft_campaign_is_blocked(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.launch', $campaign))
            ->assertRedirect(route('access-review.campaigns.index'));

        // status should remain active, no new launched_at overwrite
        $original = $campaign->launched_at;
        $this->assertSame($original?->timestamp, $campaign->fresh()->launched_at?->timestamp);
    }

    public function test_non_admin_cannot_launch_a_campaign(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('access-review.campaigns.launch', $campaign))
            ->assertForbidden();

        $this->assertSame(
            AccessReviewCampaign::STATUS_DRAFT,
            $campaign->fresh()->status,
        );
    }

    public function test_admin_can_close_an_active_campaign(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.close', $campaign))
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertDatabaseHas('access_review_campaigns', [
            'id' => $campaign->id,
            'status' => AccessReviewCampaign::STATUS_CLOSED,
        ]);

        $this->assertNotNull($campaign->fresh()->closed_at);
    }

    public function test_closing_a_draft_campaign_is_blocked(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.close', $campaign))
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertSame(
            AccessReviewCampaign::STATUS_DRAFT,
            $campaign->fresh()->status,
        );
    }

    public function test_closing_an_already_closed_campaign_is_blocked(): void
    {
        $campaign = AccessReviewCampaign::factory()->closed()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('access-review.campaigns.close', $campaign))
            ->assertRedirect(route('access-review.campaigns.index'));

        $this->assertSame(
            AccessReviewCampaign::STATUS_CLOSED,
            $campaign->fresh()->status,
        );
    }

    public function test_non_admin_cannot_close_a_campaign(): void
    {
        $campaign = AccessReviewCampaign::factory()->active()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('access-review.campaigns.close', $campaign))
            ->assertForbidden();

        $this->assertSame(
            AccessReviewCampaign::STATUS_ACTIVE,
            $campaign->fresh()->status,
        );
    }
}
