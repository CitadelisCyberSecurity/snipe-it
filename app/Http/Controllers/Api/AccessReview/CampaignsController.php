<?php

namespace App\Http\Controllers\Api\AccessReview;

use App\Http\Controllers\Controller;
use App\Http\Transformers\AccessReviewCampaignsTransformer;
use App\Models\AccessReviewCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignsController extends Controller
{
    public function index(Request $request): JsonResponse|array
    {
        $this->authorize('admin');

        $allowed_columns = ['id', 'name', 'status', 'status_label', 'launched_at', 'closed_at', 'created_at'];

        $campaigns = AccessReviewCampaign::with('creator')->withCount('items');

        if ($request->filled('search')) {
            $campaigns->where('name', 'LIKE', '%'.$request->input('search').'%');
        }

        $validStatuses = [
            AccessReviewCampaign::STATUS_DRAFT,
            AccessReviewCampaign::STATUS_ACTIVE,
            AccessReviewCampaign::STATUS_CLOSED,
        ];

        if ($request->filled('status') && in_array($request->input('status'), $validStatuses, true)) {
            $campaigns->where('status', $request->input('status'));
        }

        $total = $campaigns->count();
        $offset = ($request->input('offset') > $total) ? $total : app('api_offset_value');
        $limit = app('api_limit_value');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'created_at';
        $sortColumn = $sort === 'status_label' ? 'status' : $sort;

        $campaigns->orderBy($sortColumn, $order);

        $campaigns = $campaigns->skip($offset)->take($limit)->get();

        return (new AccessReviewCampaignsTransformer)->transformCampaigns($campaigns, $total);
    }
}
