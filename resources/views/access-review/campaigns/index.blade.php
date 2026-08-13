@extends('layouts/default')

@section('title')
    {{ trans('admin/access-review/general.campaigns') }}
    @parent
@stop

@section('header_right')
    <a href="{{ route('access-review.campaigns.create') }}" class="btn btn-primary pull-right">
        <x-icon type="plus" class="fa-fw" />
        {{ trans('admin/access-review/general.new_campaign') }}
    </a>
@stop

@section('content')
    @php($showingDeleted = request('status') === 'deleted')
    <x-container>
        <x-box name="accessReviewCampaigns">
            {{-- Titled rather than given a bulk-actions dropdown, matching the
                 Consumables and Licenses tables.

                 The dropdown could never do anything: bulk actions operate on
                 checked rows, but AccessReviewCampaignPresenter defines no
                 checkbox column, so nothing was ever selectable and the Go
                 button stayed permanently disabled. Its single action, Delete,
                 is in any case already on every row as its own button (see
                 AccessReviewCampaignsTransformer). All it contributed was a
                 500px-wide dead control above the table.

                 The POST route and CampaignsController::bulkDestroy are left
                 in place — they are covered by AdminCampaignCrudTest and are
                 reachable by other callers. --}}
            <x-slot:table_header>
                {{ trans('admin/access-review/general.campaigns') }}
            </x-slot:table_header>
            {{-- Show Deleted lives in the table toolbar (next to refresh/print) via
                 the accessReviewCampaignsButtons function, matching the Users tab. --}}
            <x-table
                    show_column_search="false"
                    fixed_right_number="1"
                    fixed_number="1"
                    buttons="accessReviewCampaignsButtons"
                    api_url="{{ route('api.access-review.campaigns.index', $showingDeleted ? ['status' => 'deleted'] : []) }}"
                    :presenter="\App\Presenters\AccessReviewCampaignPresenter::dataTableLayout()"
                    export_filename="export-access-review-campaigns-{{ date('Y-m-d') }}"
            />
        </x-box>
    </x-container>
@stop


@section('moar_scripts')
    @include ('partials.bootstrap-table')
    @if(session('created_id'))
    <script>
    $(function () {
        var $table = $('[data-id-table="accessReviewCampaigns"]');
        var createdId = {{ (int) session('created_id') }};

        // Highlight the newly created row after the table renders
        $table.on('post-body.bs.table', function () {
            $table.find('tbody tr').each(function () {
                if ($(this).find('a[href*="/' + createdId + '/"], form[action*="/' + createdId + '/"]').length) {
                    $(this).addClass('warning');
                    return false;
                }
            });
        });

        // Reset search and page so the new campaign is visible at the top
        $table.bootstrapTable('resetSearch', '');
        $table.bootstrapTable('refresh', { pageNumber: 1 });
    });
    </script>
    @endif
@stop
