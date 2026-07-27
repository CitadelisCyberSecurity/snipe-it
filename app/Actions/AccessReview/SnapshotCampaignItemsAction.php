<?php

namespace App\Actions\AccessReview;

use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use Illuminate\Support\Facades\DB;

final class SnapshotCampaignItemsAction
{
    public static function run(AccessReviewCampaign $campaign): int
    {
        $now = now();
        $count = 0;

        DB::transaction(function () use ($campaign, $now, &$count) {
            $query = DB::table('license_seats')
                ->join('users', 'license_seats.assigned_to', '=', 'users.id')
                ->join('licenses', 'license_seats.license_id', '=', 'licenses.id')
                ->whereNull('license_seats.deleted_at')
                ->whereNull('users.deleted_at')
                ->whereNull('licenses.deleted_at')
                ->whereNotNull('users.manager_id');

            if (! empty($campaign->company_ids)) {
                // FMCS: a campaign scoped to specific companies must only snapshot seats where BOTH
                // the license and the assigned user belong to a scoped company. Filtering the license
                // alone would leak another company's user (name, manager) when a license owned by a
                // scoped company is checked out to a user outside it.
                $query->whereIn('licenses.company_id', $campaign->company_ids)
                    ->whereIn('users.company_id', $campaign->company_ids);
            }

            $query->select([
                'license_seats.id as license_seat_id',
                'license_seats.license_id',
                'users.id as user_id',
                'users.manager_id',
                'licenses.name as license_name_snapshot',
                'licenses.purchase_cost',
                'licenses.seats as total_seats',
            ]);

            $campaign->items()->delete();

            // Stream the eligible seats in batches so memory stays bounded regardless
            // of how many seats a large org has, rather than loading them all at once.
            $query->orderBy('license_seats.id')->chunk(500, function ($rows) use ($campaign, $now, &$count) {
                $insert = $rows->map(function ($row) use ($campaign, $now) {
                    $totalSeats = (int) $row->total_seats;
                    $costPerSeat = null;
                    if ($row->purchase_cost !== null && $totalSeats > 0) {
                        $costPerSeat = round((float) $row->purchase_cost / $totalSeats, 2);
                    }

                    return [
                        'campaign_id' => $campaign->id,
                        'user_id' => $row->user_id,
                        'manager_id' => $row->manager_id,
                        'license_id' => $row->license_id,
                        'license_seat_id' => $row->license_seat_id,
                        'license_name_snapshot' => $row->license_name_snapshot,
                        'cost_per_seat_snapshot' => $costPerSeat,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                AccessReviewItem::insert($insert);
                $count += count($insert);
            });
        });

        return $count;
    }
}
