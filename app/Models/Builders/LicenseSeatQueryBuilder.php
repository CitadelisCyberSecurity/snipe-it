<?php

namespace App\Models\Builders;

use App\Models\LicenseSeat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @template TModel of LicenseSeat
 *
 * @extends Builder<TModel>
 */
class LicenseSeatQueryBuilder extends Builder
{
    public function orderDepartments(string $order): self
    {
        return $this
            ->leftJoin('users as license_seat_users', 'license_seats.assigned_to', '=', 'license_seat_users.id')
            ->leftJoin('departments as license_user_dept', 'license_user_dept.id', '=', 'license_seat_users.department_id')
            ->whereNotNull('license_seats.assigned_to')
            ->orderBy('license_user_dept.name', $order);
    }

    /**
     * Order on the assigned user's company.
     *
     * Company membership lives in the company_user pivot (users.company_id was
     * renamed to legacy_company_id), and a user can belong to more than one
     * company. Joining the pivot directly would emit one row per membership and
     * inflate this paginated list, so each user collapses to a single sort key
     * via MIN(name) -- the same approach as UserQueryBuilder::orderCompany.
     */
    public function orderCompany(string $order): self
    {
        // The MIN(...) aggregate has to go through DB::raw, which bypasses
        // Laravel's grammar — so the `companies` table reference inside the
        // raw string won't get DB_PREFIX applied automatically.
        $prefix = DB::getTablePrefix();
        $sub = DB::table('company_user')
            ->join('companies', 'companies.id', '=', 'company_user.company_id')
            ->select('company_user.user_id', DB::raw('MIN('.$prefix.'companies.name) as min_company_name'))
            ->groupBy('company_user.user_id');

        return $this
            ->leftJoinSub($sub, 'companies_sort', 'companies_sort.user_id', '=', 'license_seats.assigned_to')
            ->whereNotNull('license_seats.assigned_to')
            ->orderBy('companies_sort.min_company_name', $order);
    }

    public function byAssigned(): self
    {
        return $this->where(function ($query) {
            $query->whereNotNull('assigned_to')
                ->orWhereNotNull('asset_id');
        });
    }
}
