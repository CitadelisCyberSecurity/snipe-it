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
            $rows = DB::table('license_seats')
                ->join('users', 'license_seats.assigned_to', '=', 'users.id')
                ->join('licenses', 'license_seats.license_id', '=', 'licenses.id')
                ->whereNull('license_seats.deleted_at')
                ->whereNull('users.deleted_at')
                ->whereNull('licenses.deleted_at')
                ->whereNotNull('users.manager_id')
                ->select([
                    'license_seats.id as license_seat_id',
                    'license_seats.license_id',
                    'users.id as user_id',
                    'users.manager_id',
                    'licenses.name as license_name_snapshot',
                    'licenses.purchase_cost',
                    'licenses.seats as total_seats',
                ])
                ->get()
                ->map(function ($row) use ($campaign, $now) {
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
                })
                ->all();

            $campaign->items()->delete();
            collect($rows)->chunk(500)->each(function ($chunk) {
                AccessReviewItem::insert($chunk->all());
            });

            $count = count($rows);
        });

        return $count;
    }
}
