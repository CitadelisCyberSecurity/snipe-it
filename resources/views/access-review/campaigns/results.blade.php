@extends('layouts/default')

@section('title')
    {{ $campaign->name }} &mdash; {{ trans('admin/access-review/general.results') }}
    @parent
@stop

@section('content')
    <x-container>
        <x-box title="{{ $campaign->name }}">

            {{-- Status badge --}}
            @php
                $statusClass = match($campaign->status) {
                    'active' => 'primary',
                    'closed' => 'success',
                    default  => 'default',
                };
            @endphp
            <div style="margin-bottom:20px;">
                <span class="label label-{{ $statusClass }}">
                    {{ trans('admin/access-review/general.status_' . $campaign->status) }}
                </span>
            </div>

            {{-- Summary stat panels --}}
            <div class="row" style="margin-bottom:24px;">
                @foreach([
                    ['count' => $summary['total'],    'label' => trans('admin/access-review/general.all'),     'style' => 'default'],
                    ['count' => $summary['keep'],     'label' => trans('admin/access-review/general.keep'),    'style' => 'success'],
                    ['count' => $summary['modify'],   'label' => trans('admin/access-review/general.modify'),  'style' => 'warning'],
                    ['count' => $summary['delete'],   'label' => trans('admin/access-review/general.remove'),  'style' => 'danger'],
                    ['count' => $summary['pending'],  'label' => trans('admin/access-review/general.pending'), 'style' => 'default'],
                    ['count' => $summary['executed'], 'label' => trans('admin/access-review/general.executed'),'style' => 'info'],
                ] as $stat)
                    <div class="col-xs-6 col-sm-2">
                        <div class="panel panel-{{ $stat['style'] }} text-center" style="margin-bottom:8px;">
                            <div class="panel-body" style="padding:10px;">
                                <h3 style="margin:0 0 4px;">{{ $stat['count'] }}</h3>
                                <small>{{ $stat['label'] }}</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Manager progress --}}
            <h4>{{ trans('admin/access-review/general.manager_progress') }}</h4>
            <table class="table table-condensed table-striped" style="margin-bottom:24px;">
                <thead>
                    <tr>
                        <th>{{ trans('admin/access-review/general.manager') }}</th>
                        <th>{{ trans('admin/access-review/general.items_count') }}</th>
                        <th>{{ trans('admin/access-review/general.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($managers as $mgr)
                        <tr>
                            <td>{{ $mgr['name'] }}</td>
                            <td>{{ $mgr['total'] }}</td>
                            <td>
                                @if($mgr['done'])
                                    <span class="label label-success">{{ trans('admin/access-review/general.review_status_complete') }}</span>
                                @else
                                    <span class="label label-warning">{{ trans('admin/access-review/general.review_status_in_progress') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Tabbed items tables --}}
            <ul class="nav nav-tabs" id="resultTabs" role="tablist" style="margin-bottom:0;">
                <li class="active"><a href="#tab-all"     data-toggle="tab">{{ trans('admin/access-review/general.all') }}    ({{ $summary['total'] }})</a></li>
                <li>            <a href="#tab-delete"  data-toggle="tab"><span class="text-danger">{{ trans('admin/access-review/general.remove') }}</span>  ({{ $summary['delete'] }})</a></li>
                <li>            <a href="#tab-modify"  data-toggle="tab"><span class="text-warning">{{ trans('admin/access-review/general.modify') }}</span>  ({{ $summary['modify'] }})</a></li>
                <li>            <a href="#tab-keep"    data-toggle="tab"><span class="text-success">{{ trans('admin/access-review/general.keep') }}</span>    ({{ $summary['keep'] }})</a></li>
                <li>            <a href="#tab-pending" data-toggle="tab">{{ trans('admin/access-review/general.pending') }} ({{ $summary['pending'] }})</a></li>
            </ul>

            <div class="tab-content" style="border:1px solid #ddd; border-top:none; padding:16px;">
                @foreach(['all', 'delete', 'modify', 'keep', 'pending'] as $tab)
                    @php
                        $tabItems = match($tab) {
                            'delete'  => $items->where('manager_status', 'delete'),
                            'modify'  => $items->where('manager_status', 'modify'),
                            'keep'    => $items->where('manager_status', 'keep'),
                            'pending' => $items->whereNull('manager_status'),
                            default   => $items,
                        };
                    @endphp
                    <div class="tab-pane {{ $tab === 'all' ? 'active' : '' }}" id="tab-{{ $tab }}">
                        @if($tabItems->isEmpty())
                            <p class="text-muted" style="margin:8px 0 0;">{{ trans('admin/access-review/general.no_items') }}</p>
                        @else
                            <table class="table table-striped table-condensed" style="margin-bottom:0;">
                                <thead>
                                    <tr>
                                        <th>{{ trans('admin/access-review/general.user') }}</th>
                                        <th>{{ trans('admin/access-review/general.manager') }}</th>
                                        <th>{{ trans('admin/access-review/general.license') }}</th>
                                        <th>{{ trans('admin/access-review/general.cost_per_seat') }}</th>
                                        <th>{{ trans('admin/access-review/general.decision') }}</th>
                                        <th>{{ trans('admin/access-review/general.comment') }}</th>
                                        <th>{{ trans('admin/access-review/general.executed') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tabItems as $item)
                                        @php
                                            $decisionClass = match($item->manager_status) {
                                                'keep'   => 'success',
                                                'modify' => 'warning',
                                                'delete' => 'danger',
                                                default  => 'default',
                                            };
                                            $decisionLabel = match($item->manager_status) {
                                                'keep'   => trans('admin/access-review/general.keep'),
                                                'modify' => trans('admin/access-review/general.modify'),
                                                'delete' => trans('admin/access-review/general.remove'),
                                                default  => trans('admin/access-review/general.no_decision_yet'),
                                            };
                                        @endphp
                                        <tr id="item-row-{{ $item->id }}">
                                            <td>{{ $item->user ? trim($item->user->first_name.' '.$item->user->last_name) : '—' }}</td>
                                            <td>{{ $item->manager ? trim($item->manager->first_name.' '.$item->manager->last_name) : '—' }}</td>
                                            <td>{{ $item->license_name_snapshot }}</td>
                                            <td>
                                                {{ $item->cost_per_seat_snapshot !== null
                                                    ? '$'.number_format($item->cost_per_seat_snapshot, 2)
                                                    : '—' }}
                                            </td>
                                            <td>
                                                <span class="label label-{{ $decisionClass }}">{{ $decisionLabel }}</span>
                                            </td>
                                            <td>{{ $item->manager_comment ?: '—' }}</td>
                                            <td>
                                                @if($item->isExecuted())
                                                    <i class="fa fa-check text-success"
                                                       title="{{ $item->admin_executed_at->format('Y-m-d H:i') }}"></i>
                                                @else
                                                    <span class="text-muted">&mdash;</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($item->manager_status && ! $item->isExecuted())
                                                    @php
                                                        $confirmMsg = match($item->manager_status) {
                                                            'delete' => trans('admin/access-review/general.execute_delete_confirm'),
                                                            'modify' => trans('admin/access-review/general.execute_modify_confirm'),
                                                            default  => trans('admin/access-review/general.execute_keep_confirm'),
                                                        };
                                                    @endphp
                                                    <button type="button"
                                                            class="btn btn-xs btn-{{ $decisionClass }} execute-btn"
                                                            data-execute-url="{{ route('access-review.campaigns.items.execute', [$campaign, $item]) }}"
                                                            data-confirm="{{ $confirmMsg }}"
                                                            data-item-id="{{ $item->id }}">
                                                        {{ trans('admin/access-review/general.execute') }}
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endforeach
            </div>

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
<script>
$(function () {
    var csrfToken = '{{ csrf_token() }}';

    $(document).on('click', '.execute-btn', function () {
        var $btn    = $(this);
        var confirm = $btn.data('confirm');
        var url     = $btn.data('execute-url');
        var itemId  = $btn.data('item-id');

        if (! window.confirm(confirm)) {
            return;
        }

        $btn.prop('disabled', true).text('...');

        $.ajax({
            url:    url,
            method: 'POST',
            data:   { _token: csrfToken },
            success: function () {
                var $row = $('#item-row-' + itemId);
                $row.find('td:nth-last-child(2)').html('<i class="fa fa-check text-success"></i>');
                $btn.closest('td').empty();
            },
            error: function (xhr) {
                var msg = '{{ trans('admin/access-review/general.execute_error') }}';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                alert(msg);
                $btn.prop('disabled', false).text('{{ trans('admin/access-review/general.execute') }}');
            },
        });
    });
});
</script>
@stop
