<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\AccessReviewCampaign;
use Illuminate\Database\Eloquent\Collection;

class AccessReviewCampaignsTransformer
{
    public function transformCampaigns(Collection $campaigns, int $total): array
    {
        $array = [];
        foreach ($campaigns as $campaign) {
            $array[] = self::transformCampaign($campaign);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformCampaign(?AccessReviewCampaign $campaign = null): ?array
    {
        if (! $campaign) {
            return null;
        }

        $statusLabelClass = match ($campaign->status) {
            AccessReviewCampaign::STATUS_DRAFT => 'default',
            AccessReviewCampaign::STATUS_ACTIVE => 'primary',
            AccessReviewCampaign::STATUS_CLOSED => 'success',
            default => 'default',
        };

        return [
            'id' => (int) $campaign->id,
            'name' => e($campaign->name),
            'description' => $campaign->description ? e($campaign->description) : null,
            'status' => e($campaign->status),
            'status_label' => '<span class="label label-'.$statusLabelClass.'">'
                .e(trans('admin/access-review/general.status_'.$campaign->status))
                .'</span>',
            'created_by' => $campaign->creator ? [
                'id' => (int) $campaign->creator->id,
                'name' => e($campaign->creator->display_name ?: $campaign->creator->first_name.' '.$campaign->creator->last_name),
                'first_name' => e($campaign->creator->first_name),
                'last_name' => e($campaign->creator->last_name),
            ] : null,
            'items_count' => (int) ($campaign->items_count ?? $campaign->items()->count()),
            'launched_at' => Helper::getFormattedDateObject($campaign->launched_at, 'datetime'),
            'closed_at' => Helper::getFormattedDateObject($campaign->closed_at, 'datetime'),
            'created_at' => Helper::getFormattedDateObject($campaign->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($campaign->updated_at, 'datetime'),
            'actions' => self::renderActions($campaign),
        ];
    }

    private static function renderActions(AccessReviewCampaign $campaign): string
    {
        $csrf = csrf_token();
        $html = '<nobr>';

        if ($campaign->isDraft()) {
            $editUrl = route('access-review.campaigns.edit', $campaign);
            $launchUrl = route('access-review.campaigns.launch', $campaign);
            $destroyUrl = route('access-review.campaigns.destroy', $campaign);
            $launchConfirm = e(json_encode(trans('admin/access-review/general.launch_confirm')));
            $deleteConfirm = e(json_encode(trans('general.delete_confirm', ['item' => $campaign->name])));

            $html .= '<a href="'.$editUrl.'" class="btn btn-sm btn-warning hidden-print" data-tooltip="true" title="'.e(trans('general.edit')).'">'
                .'<i class="fa-solid fa-pen-to-square fa-fw" aria-hidden="true"></i>'
                .'<span class="sr-only">'.e(trans('general.edit')).'</span></a>&nbsp;';

            $html .= '<form method="POST" action="'.$launchUrl.'" style="display:inline">'
                .'<input type="hidden" name="_token" value="'.$csrf.'">'
                .'<button type="submit" class="btn btn-sm btn-primary hidden-print" data-tooltip="true" title="'.e(trans('admin/access-review/general.launch')).'" '
                .'onclick="return confirm('.$launchConfirm.')">'
                .'<i class="fa-solid fa-rocket fa-fw" aria-hidden="true"></i>'
                .'<span class="sr-only">'.e(trans('admin/access-review/general.launch')).'</span></button></form>&nbsp;';

            $html .= '<form method="POST" action="'.$destroyUrl.'" style="display:inline">'
                .'<input type="hidden" name="_token" value="'.$csrf.'">'
                .'<input type="hidden" name="_method" value="DELETE">'
                .'<button type="submit" class="btn btn-sm btn-danger hidden-print" data-tooltip="true" title="'.e(trans('general.delete')).'" '
                .'onclick="return confirm('.$deleteConfirm.')">'
                .'<i class="fa-solid fa-trash fa-fw" aria-hidden="true"></i>'
                .'<span class="sr-only">'.e(trans('general.delete')).'</span></button></form>';
        } elseif ($campaign->isActive()) {
            $resultsUrl = route('access-review.campaigns.results', $campaign);
            $closeUrl = route('access-review.campaigns.close', $campaign);
            $closeConfirm = e(json_encode(trans('admin/access-review/general.close_confirm')));

            $html .= '<a href="'.$resultsUrl.'" class="btn btn-sm btn-info hidden-print" data-tooltip="true" title="'.e(trans('admin/access-review/general.view_results')).'">'
                .'<i class="fa-solid fa-chart-bar fa-fw" aria-hidden="true"></i>'
                .'<span class="sr-only">'.e(trans('admin/access-review/general.view_results')).'</span></a>&nbsp;';

            $html .= '<form method="POST" action="'.$closeUrl.'" style="display:inline">'
                .'<input type="hidden" name="_token" value="'.$csrf.'">'
                .'<button type="submit" class="btn btn-sm btn-warning hidden-print" data-tooltip="true" title="'.e(trans('admin/access-review/general.close')).'" '
                .'onclick="return confirm('.$closeConfirm.')">'
                .'<i class="fa-solid fa-lock fa-fw" aria-hidden="true"></i>'
                .'<span class="sr-only">'.e(trans('admin/access-review/general.close')).'</span></button></form>';
        } elseif ($campaign->isClosed()) {
            $resultsUrl = route('access-review.campaigns.results', $campaign);

            $html .= '<a href="'.$resultsUrl.'" class="btn btn-sm btn-info hidden-print" data-tooltip="true" title="'.e(trans('admin/access-review/general.view_results')).'">'
                .'<i class="fa-solid fa-chart-bar fa-fw" aria-hidden="true"></i>'
                .'<span class="sr-only">'.e(trans('admin/access-review/general.view_results')).'</span></a>';
        }

        $html .= '</nobr>';

        return $html;
    }
}
