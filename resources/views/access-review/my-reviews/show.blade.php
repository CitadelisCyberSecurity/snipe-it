@extends('layouts/default')

@section('title')
    {{ $campaign->name }}
    @parent
@stop

@section('content')
    @php
        $allReviewed  = $items->whereNull('manager_status')->isEmpty();
        $allCompleted = $items->whereNull('manager_completed_at')->isEmpty();
        $reviewed     = $items->whereNotNull('manager_status')->count();
        $statusClasses = [
            'keep'   => 'btn-success',
            'modify' => 'btn-warning',
            'delete' => 'btn-danger',
        ];
    @endphp

    <x-container>
        <x-box title="{{ $campaign->name }}">

            {{-- Flash messages --}}
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            {{-- Progress --}}
            <div class="row" style="margin-bottom:16px;">
                <div class="col-md-6">
                    @php $pct = $items->count() > 0 ? round($reviewed / $items->count() * 100) : 0; @endphp
                    <div class="progress">
                        <div class="progress-bar progress-bar-{{ $allReviewed ? 'success' : 'info' }}"
                             style="width:{{ $pct }}%"></div>
                    </div>
                    <small>
                        {{ trans('admin/access-review/general.progress', [
                            'reviewed' => $reviewed,
                            'total'    => $items->count(),
                        ]) }}
                    </small>
                </div>
            </div>

            {{-- Review table --}}
            <table class="table table-striped" id="review-table">
                <thead>
                    <tr>
                        <th>{{ trans('admin/access-review/general.user') }}</th>
                        <th>{{ trans('admin/access-review/general.license') }}</th>
                        <th>{{ trans('admin/access-review/general.cost_per_seat') }}</th>
                        <th>{{ trans('admin/access-review/general.decision') }}</th>
                        <th>{{ trans('admin/access-review/general.comment') }}</th>
                        <th style="width:32px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr data-item-id="{{ $item->id }}"
                            data-reviewed="{{ $item->manager_status ? '1' : '0' }}"
                            data-save-url="{{ route('access-review.my-reviews.items.save', [$campaign, $item]) }}">
                            <td>{{ $item->user->first_name }} {{ $item->user->last_name }}</td>
                            <td>{{ $item->license_name_snapshot }}</td>
                            <td>
                                {{ $item->cost_per_seat_snapshot !== null
                                    ? '$'.number_format($item->cost_per_seat_snapshot, 2)
                                    : '—' }}
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @foreach(['keep' => trans('admin/access-review/general.keep'), 'modify' => trans('admin/access-review/general.modify'), 'delete' => trans('admin/access-review/general.remove')] as $val => $label)
                                        <button type="button"
                                                class="btn decision-btn {{ $item->manager_status === $val ? $statusClasses[$val].' active' : 'btn-default' }}"
                                                data-status="{{ $val }}"
                                                {{ $item->isCompleted() ? 'disabled' : '' }}>
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <textarea class="form-control review-comment"
                                          rows="1"
                                          style="min-width:160px;"
                                          {{ $item->isCompleted() ? 'disabled' : '' }}>{{ $item->manager_comment }}</textarea>
                            </td>
                            <td class="save-indicator text-center" style="vertical-align:middle;">
                                @if($item->isCompleted())
                                    <i class="fa fa-lock text-muted" title="{{ trans('admin/access-review/general.review_already_completed') }}"></i>
                                @elseif($item->manager_status)
                                    <i class="fa fa-check text-success"></i>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Submit / already complete --}}
            <div style="margin-top:16px;">
                @if($allCompleted)
                    <p class="text-success">
                        <i class="fa fa-check-circle"></i>
                        {{ trans('admin/access-review/general.review_already_completed') }}
                    </p>
                @else
                    <form method="POST"
                          action="{{ route('access-review.my-reviews.complete', $campaign) }}"
                          onsubmit="return confirm('{{ trans('admin/access-review/general.mark_complete_confirm') }}')">
                        @csrf
                        <button type="submit"
                                id="mark-complete-btn"
                                class="btn btn-success"
                                {{ $allReviewed ? '' : 'disabled' }}>
                            <i class="fa fa-check-circle"></i>
                            {{ trans('admin/access-review/general.mark_complete') }}
                        </button>
                        @if(! $allReviewed)
                            <small class="text-muted" style="margin-left:8px;">
                                {{ trans('admin/access-review/general.cannot_complete_unreviewed') }}
                            </small>
                        @endif
                    </form>
                @endif
            </div>

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
<script>
$(function () {
    var csrfToken = '{{ csrf_token() }}';
    var timers    = {};

    // Track reviewed state per item for enabling the submit button
    var states = {};
    $('#review-table tbody tr').each(function () {
        states[$(this).data('item-id')] = $(this).data('reviewed') === 1;
    });

    $(document).on('click', '.decision-btn:not([disabled])', function () {
        var $btn    = $(this);
        var $row    = $btn.closest('tr');
        var itemId  = $row.data('item-id');
        var status  = $btn.data('status');
        var colors  = { keep: 'btn-success', modify: 'btn-warning', delete: 'btn-danger' };

        // Update button group appearance
        $row.find('.decision-btn')
            .removeClass('active btn-success btn-warning btn-danger')
            .addClass('btn-default');
        $btn.removeClass('btn-default').addClass('active ' + colors[status]);

        states[itemId] = true;
        updateSubmitButton();
        doSave(itemId, status, $row.find('.review-comment').val(), $row);
    });

    $(document).on('input', '.review-comment:not([disabled])', function () {
        var $row   = $(this).closest('tr');
        var itemId = $row.data('item-id');
        var $ta    = $(this);

        clearTimeout(timers[itemId]);
        timers[itemId] = setTimeout(function () {
            var status = $row.find('.decision-btn.active').data('status');
            if (status) {
                doSave(itemId, status, $ta.val(), $row);
            }
        }, 600);
    });

    function doSave(itemId, status, comment, $row) {
        var $ind = $row.find('.save-indicator');
        $ind.html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url:         $row.data('save-url'),
            method:      'PATCH',
            data:        { _token: csrfToken, manager_status: status, manager_comment: comment || '' },
            success:     function () { $ind.html('<i class="fa fa-check text-success"></i>'); },
            error:       function () { $ind.html('<i class="fa fa-times text-danger"></i>'); },
        });
    }

    function updateSubmitButton() {
        var allDone = Object.keys(states).every(function (k) { return states[k]; });
        $('#mark-complete-btn').prop('disabled', !allDone);
        $('#mark-complete-btn').siblings('small').toggle(!allDone);
    }
});
</script>
@stop
