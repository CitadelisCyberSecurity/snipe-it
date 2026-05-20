<?php

namespace App\Models;

use App\Presenters\AccessReviewCampaignPresenter;
use App\Presenters\Presentable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccessReviewCampaign extends SnipeModel
{
    use HasFactory;
    use Presentable;
    use SoftDeletes;

    protected $presenter = AccessReviewCampaignPresenter::class;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    protected $table = 'access_review_campaigns';

    protected $fillable = [
        'name',
        'description',
        'status',
        'launched_at',
        'closed_at',
        'created_by',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'launched_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_by' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AccessReviewItem::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
