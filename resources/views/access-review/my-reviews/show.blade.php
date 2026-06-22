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
            <div id="my-review-toolbar" class="hidden-print"></div>
            <table class="table table-striped snipe-table" id="review-table"
                data-cookie-id-table="accessReviewMyReview{{ $campaign->id }}"
                data-id-table="accessReviewMyReview{{ $campaign->id }}"
                data-toolbar="#my-review-toolbar"
                data-pagination="false"
                data-show-refresh="false"
                data-row-attributes="getReviewRowAttrs">
                <thead>
                    <tr>
                        <th data-field="user" data-sortable="true">{{ trans('admin/access-review/general.user') }}</th>
                        <th data-field="license" data-sortable="true">{{ trans('admin/access-review/general.license') }}</th>
                        <th data-field="cost_per_seat" data-sortable="true" data-sorter="costSorter">{{ trans('admin/access-review/general.cost_per_seat') }}</th>
                        <th data-field="decision" data-escape="false" data-sortable="false">{{ trans('admin/access-review/general.decision') }}</th>
                        <th data-field="comment" data-escape="false" data-sortable="false">{{ trans('admin/access-review/general.comment') }}</th>
                        <th data-field="save_indicator" data-escape="false" data-sortable="false" data-switchable="false" style="width:32px;"></th>
                        <th data-field="_meta" data-visible="false" data-switchable="false"></th>
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
                                <div class="form-group comment-group" style="margin-bottom:0;">
                                    <textarea class="form-control review-comment"
                                              rows="1"
                                              style="min-width:160px;"
                                              {{ $item->isCompleted() ? 'disabled' : '' }}>{{ $item->manager_comment }}</textarea>
                                    <span class="help-block comment-error" style="display:none;margin-bottom:0;">
                                        {{ trans('admin/access-review/general.comment_required') }}
                                    </span>
                                </div>
                            </td>
                            <td class="save-indicator text-center" style="vertical-align:middle;">
                                @if($item->isCompleted())
                                    <i class="fa fa-lock text-muted" title="{{ trans('admin/access-review/general.review_already_completed') }}"></i>
                                @elseif($item->manager_status)
                                    <i class="fa fa-check text-success"></i>
                                @endif
                            </td>
                            <td>{{ json_encode([
                                'data-item-id' => (string) $item->id,
                                'data-save-url' => route('access-review.my-reviews.items.save', [$campaign, $item]),
                                'data-reviewed' => $item->manager_status ? '1' : '0',
                            ]) }}</td>
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
function getReviewRowAttrs(row) {
    try { return JSON.parse(row._meta); } catch (e) { return {}; }
}
</script>
@include('partials.bootstrap-table')
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
        var $ta     = $row.find('.review-comment');

        // Second click on the active button = deselect
        if ($btn.hasClass('active')) {
            $btn.removeClass('active btn-success btn-warning btn-danger').addClass('btn-default');
            $ta.closest('.comment-group').removeClass('has-error');
            $row.find('.comment-error').hide();
            states[itemId] = false;
            updateSubmitButton();
            doSave(itemId, null, '', $row);
            return;
        }

        // Update button group appearance
        $row.find('.decision-btn')
            .removeClass('active btn-success btn-warning btn-danger')
            .addClass('btn-default');
        $btn.removeClass('btn-default').addClass('active ' + colors[status]);

        // Modify requires a comment — highlight the textarea and wait for input
        if (status === 'modify' && $ta.val().trim() === '') {
            $ta.closest('.comment-group').addClass('has-error');
            $row.find('.comment-error').show();
            $ta.focus();
            return;
        }

        $ta.closest('.comment-group').removeClass('has-error');
        $row.find('.comment-error').hide();
        states[itemId] = true;
        updateSubmitButton();
        doSave(itemId, status, $ta.val(), $row);
    });

    $(document).on('input', '.review-comment:not([disabled])', function () {
        var $row   = $(this).closest('tr');
        var itemId = $row.data('item-id');
        var $ta    = $(this);

        clearTimeout(timers[itemId]);
        timers[itemId] = setTimeout(function () {
            var status = $row.find('.decision-btn.active').data('status');
            if (!status) return;
            if (status === 'modify' && $ta.val().trim() === '') {
                $ta.closest('.comment-group').addClass('has-error');
                $row.find('.comment-error').show();
                states[itemId] = false;
                updateSubmitButton();
                return;
            }
            $ta.closest('.comment-group').removeClass('has-error');
            $row.find('.comment-error').hide();
            states[itemId] = true;
            updateSubmitButton();
            doSave(itemId, status, $ta.val(), $row);
        }, 600);
    });

    function doSave(itemId, status, comment, $row) {
        var $ind = $row.find('.save-indicator');
        $ind.html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url:         $row.data('save-url'),
            method:      'PATCH',
            data:        { _token: csrfToken, manager_status: status || '', manager_comment: comment || '' },
            success:     function () { $ind.html(status ? '<i class="fa fa-check text-success"></i>' : ''); },
            error:       function () { $ind.html('<i class="fa fa-times text-danger"></i>'); },
        });
    }

    function updateSubmitButton() {
        var allDone = Object.keys(states).every(function (k) { return states[k]; });
        if (allDone) {
            $('#review-table tbody tr').each(function () {
                if ($(this).find('.decision-btn.active[data-status="modify"]').length &&
                    $(this).find('.review-comment').val().trim() === '') {
                    allDone = false;
                    return false;
                }
            });
        }
        $('#mark-complete-btn').prop('disabled', !allDone);
        $('#mark-complete-btn').siblings('small').toggle(!allDone);
    }
});
</script>
@stop
