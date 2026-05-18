<?php

namespace Database\Factories;

use App\Models\AccessReviewCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccessReviewCampaignFactory extends Factory
{
    protected $model = AccessReviewCampaign::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'status' => AccessReviewCampaign::STATUS_DRAFT,
            'created_by' => User::factory(),
        ];
    }

    public function active(): self
    {
        return $this->state(fn () => [
            'status' => AccessReviewCampaign::STATUS_ACTIVE,
            'launched_at' => now(),
        ]);
    }

    public function closed(): self
    {
        return $this->state(fn () => [
            'status' => AccessReviewCampaign::STATUS_CLOSED,
            'launched_at' => now()->subDays(7),
            'closed_at' => now(),
        ]);
    }
}
