<?php

namespace Tests\Feature\LicenseSeats\Api;

use App\Models\Company;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;
use Tests\TestCase;

/**
 * Coverage for the `assigned_user.company` sort on the license-seats
 * endpoint (LicenseSeat::scopeOrderCompany).
 *
 * The scope used to order on users.company_id, a column that no longer
 * exists -- it was renamed to legacy_company_id once the company_user pivot
 * became the authoritative source of membership -- so every request for this
 * sort died with "unknown column". Nothing covered it, so the break was
 * silent in CI.
 *
 * Sorting through a many-to-many pivot also invites a subtler bug than the
 * crash: joining the pivot directly emits one row per membership, so a user
 * in three companies would return their seat three times and corrupt both
 * the row count and the pagination. The multi-company case below is the
 * guard against that.
 */
class LicenseSeatSortByCompanyTest extends TestCase
{
    public function test_can_sort_seats_by_assigned_user_company(): void
    {
        $acme = Company::factory()->create(['name' => 'Acme']);
        $zenith = Company::factory()->create(['name' => 'Zenith']);

        $license = License::factory()->create(['seats' => 2]);

        $acmeUser = User::factory()->forCompany($acme)->create();
        $zenithUser = User::factory()->forCompany($zenith)->create();

        $seats = LicenseSeat::where('license_id', $license->id)->orderBy('id')->get();
        $seats[0]->update(['assigned_to' => $zenithUser->id]);
        $seats[1]->update(['assigned_to' => $acmeUser->id]);

        $response = $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.licenses.seats.index', ['license' => $license->id]).'?sort=assigned_user.company&order=asc')
            ->assertOk();

        $returned = collect($response->json('rows'))->pluck('id')->all();

        // Acme before Zenith on an ascending sort.
        $this->assertSame([$seats[1]->id, $seats[0]->id], $returned);
    }

    public function test_sorting_by_company_is_reversible(): void
    {
        $acme = Company::factory()->create(['name' => 'Acme']);
        $zenith = Company::factory()->create(['name' => 'Zenith']);

        $license = License::factory()->create(['seats' => 2]);

        $acmeUser = User::factory()->forCompany($acme)->create();
        $zenithUser = User::factory()->forCompany($zenith)->create();

        $seats = LicenseSeat::where('license_id', $license->id)->orderBy('id')->get();
        $seats[0]->update(['assigned_to' => $acmeUser->id]);
        $seats[1]->update(['assigned_to' => $zenithUser->id]);

        $actor = User::factory()->superuser()->create();

        $descending = collect(
            $this->actingAsForApi($actor)
                ->getJson(route('api.licenses.seats.index', ['license' => $license->id]).'?sort=assigned_user.company&order=desc')
                ->assertOk()
                ->json('rows')
        )->pluck('id')->all();

        $this->assertSame([$seats[1]->id, $seats[0]->id], $descending);
    }

    public function test_seat_is_not_duplicated_when_user_belongs_to_multiple_companies(): void
    {
        $license = License::factory()->create(['seats' => 1]);

        $user = User::factory()->forCompany(Company::factory()->create(['name' => 'Acme']))->create();
        $user->companies()->syncWithoutDetaching([
            Company::factory()->create(['name' => 'Borealis'])->id,
            Company::factory()->create(['name' => 'Cerulean'])->id,
        ]);

        $seat = LicenseSeat::where('license_id', $license->id)->first();
        $seat->update(['assigned_to' => $user->id]);

        $response = $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.licenses.seats.index', ['license' => $license->id]).'?sort=assigned_user.company&order=asc')
            ->assertOk();

        // Three memberships must still yield exactly one seat row, and the
        // reported total must agree with the rows actually returned.
        $this->assertCount(1, $response->json('rows'));
        $this->assertSame(1, $response->json('total'));
    }
}
