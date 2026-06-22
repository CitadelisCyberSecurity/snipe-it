<?php

namespace App\Presenters;

class AccessReviewCampaignPresenter extends Presenter
{
    public static function dataTableLayout(): string
    {
        $layout = [
            [
                'field' => 'id',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.id'),
                'visible' => false,
            ],
            [
                'field' => 'name',
                'searchable' => true,
                'sortable' => true,
                'switchable' => false,
                'title' => trans('admin/access-review/general.name'),
                'visible' => true,
            ],
            [
                'field' => 'status_label',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/access-review/general.status'),
                'visible' => true,
            ],
            [
                'field' => 'created_by',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/access-review/general.created_by'),
                'visible' => true,
                'formatter' => 'usersLinkObjFormatter',
            ],
            [
                'field' => 'launched_at',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/access-review/general.launched_at'),
                'visible' => true,
                'formatter' => 'dateDisplayFormatter',
            ],
            [
                'field' => 'closed_at',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/access-review/general.closed_at'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ],
            [
                'field' => 'items_count',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/access-review/general.items_count'),
                'visible' => true,
            ],
            [
                'field' => 'created_at',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.created_at'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ],
            [
                'field' => 'actions',
                'searchable' => false,
                'sortable' => false,
                'switchable' => false,
                'title' => trans('table.actions'),
                'visible' => true,
                'printIgnore' => true,
                'class' => 'hidden-print',
            ],
        ];

        return json_encode($layout);
    }
}
