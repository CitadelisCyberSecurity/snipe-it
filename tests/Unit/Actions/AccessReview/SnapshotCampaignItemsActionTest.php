<?php

namespace Tests\Unit\Actions\AccessReview;

use App\Actions\AccessReview\SnapshotCampaignItemsAction;
use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;
use Tests\TestCase;

class SnapshotCampaignItemsActionTest extends TestCase
{
    public function test_it_creates_one_item_per_seat_assigned_to_a_user_with_a_manager(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $manager = User::factory()->create();
        $reportee = User::factory()->create(['manager_id' => $manager->id]);
        $license = License::factory()->create();
        $seat = LicenseSeat::factory()->assignedToUser($reportee)->create(['license_id' => $license->id]);

        $count = SnapshotCampaignItemsAction::run($campaign);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('access_review_items', [
            'campaign_id' => $campaign->id,
            'user_id' => $reportee->id,
            'manager_id' => $manager->id,
            'license_id' => $license->id,
            'license_seat_id' => $seat->id,
        ]);
    }

    public function test_it_skips_users_without_a_manager(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $orphan = User::factory()->create(['manager_id' => null]);
        $license = License::factory()->create();
        LicenseSeat::factory()->assignedToUser($orphan)->create(['license_id' => $license->id]);

        $count = SnapshotCampaignItemsAction::run($campaign);

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('access_review_items', 0);
    }

    public function test_it_skips_soft_deleted_users(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $manager = User::factory()->create();
        $deletedReportee = User::factory()->create(['manager_id' => $manager->id]);
        $license = License::factory()->create();
        LicenseSeat::factory()->assignedToUser($deletedReportee)->create(['license_id' => $license->id]);
        $deletedReportee->delete();

        $count = SnapshotCampaignItemsAction::run($campaign);

        $this->assertSame(0, $count);
    }

    public function test_it_skips_soft_deleted_seats(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $manager = User::factory()->create();
        $reportee = User::factory()->create(['manager_id' => $manager->id]);
        $license = License::factory()->create();
        $seat = LicenseSeat::factory()->assignedToUser($reportee)->create(['license_id' => $license->id]);
        $seat->delete();

        $count = SnapshotCampaignItemsAction::run($campaign);

        $this->assertSame(0, $count);
    }

    public function test_it_freezes_license_name_at_snapshot_time(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $manager = User::factory()->create();
        $reportee = User::factory()->create(['manager_id' => $manager->id]);
        $license = License::factory()->create(['name' => 'Original Name']);
        LicenseSeat::factory()->assignedToUser($reportee)->create(['license_id' => $license->id]);

        SnapshotCampaignItemsAction::run($campaign);

        $license->update(['name' => 'Renamed After Snapshot']);

        $this->assertDatabaseHas('access_review_items', [
            'campaign_id' => $campaign->id,
            'license_name_snapshot' => 'Original Name',
        ]);
    }

    public function test_it_freezes_manager_id_at_snapshot_time(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $originalManager = User::factory()->create();
        $newManager = User::factory()->create();
        $reportee = User::factory()->create(['manager_id' => $originalManager->id]);
        $license = License::factory()->create();
        LicenseSeat::factory()->assignedToUser($reportee)->create(['license_id' => $license->id]);

        SnapshotCampaignItemsAction::run($campaign);

        $reportee->update(['manager_id' => $newManager->id]);

        $item = AccessReviewItem::where('campaign_id', $campaign->id)->first();
        $this->assertSame($originalManager->id, $item->manager_id);
    }

    public function test_it_calculates_cost_per_seat_from_purchase_cost_and_seats(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $manager = User::factory()->create();
        $reportee = User::factory()->create(['manager_id' => $manager->id]);
        $license = License::factory()->create(['purchase_cost' => '400.00', 'seats' => 4]);
        LicenseSeat::factory()->assignedToUser($reportee)->create(['license_id' => $license->id]);

        SnapshotCampaignItemsAction::run($campaign);

        $item = AccessReviewItem::where('campaign_id', $campaign->id)->first();
        $this->assertSame('100.00', $item->cost_per_seat_snapshot);
    }

    public function test_it_stores_null_cost_when_purchase_cost_is_null(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $manager = User::factory()->create();
        $reportee = User::factory()->create(['manager_id' => $manager->id]);
        $license = License::factory()->create(['purchase_cost' => null, 'seats' => 5]);
        LicenseSeat::factory()->assignedToUser($reportee)->create(['license_id' => $license->id]);

        SnapshotCampaignItemsAction::run($campaign);

        $item = AccessReviewItem::where('campaign_id', $campaign->id)->first();
        $this->assertNull($item->cost_per_seat_snapshot);
    }

    public function test_it_creates_separate_items_for_each_seat_when_a_user_has_multiple(): void
    {
        $campaign = AccessReviewCampaign::factory()->create();
        $manager = User::factory()->create();
        $reportee = User::factory()->create(['manager_id' => $manager->id]);
        $licenseA = License::factory()->create();
        $licenseB = License::factory()->create();
        LicenseSeat::factory()->assignedToUser($reportee)->create(['license_id' => $licenseA->id]);
        LicenseSeat::factory()->assignedToUser($reportee)->create(['license_id' => $licenseB->id]);

        $count = SnapshotCampaignItemsAction::run($campaign);

        $this->assertSame(2, $count);
    }
}
