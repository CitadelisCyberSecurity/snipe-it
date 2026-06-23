<?php

namespace Database\Factories;

use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccessReviewItemFactory extends Factory
{
    protected $model = AccessReviewItem::class;

    public function definition(): array
    {
        return [
            'campaign_id' => AccessReviewCampaign::factory(),
            'user_id' => User::factory(),
            'manager_id' => User::factory(),
            'license_id' => License::factory(),
            'license_seat_id' => LicenseSeat::factory(),
            'license_name_snapshot' => $this->faker->company().' License',
            'cost_per_seat_snapshot' => $this->faker->randomFloat(2, 0, 500),
            'manager_status' => null,
            'manager_comment' => null,
        ];
    }

    public function reviewedAs(string $status, ?string $comment = null): self
    {
        return $this->state(fn () => [
            'manager_status' => $status,
            'manager_comment' => $comment,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'manager_status' => $this->faker->randomElement(AccessReviewItem::VALID_STATUSES),
            'manager_completed_at' => now(),
        ]);
    }

    public function executed(): self
    {
        return $this->state(fn () => [
            'manager_status' => AccessReviewItem::STATUS_KEEP,
            'manager_completed_at' => now()->subDay(),
            'admin_executed_at' => now(),
            'admin_executed_by' => User::factory(),
        ]);
    }
}
