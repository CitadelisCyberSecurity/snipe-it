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

    public $rules = [
        'name' => 'required|string|max:191',
    ];

    protected $fillable = [
        'name',
        'description',
        'company_ids',
        'notify_managers_on_launch',
    ];

    protected $casts = [
        'launched_at'  => 'datetime',
        'closed_at'    => 'datetime',
        'created_by'   => 'integer',
        'company_ids'  => 'array',
        'notify_managers_on_launch' => 'boolean',
    ];

    /**
     * Generics are required here: the native `: HasMany` return type takes
     * precedence over PHPStan's inference from the body, and a bare HasMany
     * means HasMany<Model, Model>. That turns `$campaign->items` into a
     * collection of base Models, so every `$item->manager_completed_at` /
     * `$item->isExecuted()` read downstream is flagged as undefined.
     *
     * @return HasMany<AccessReviewItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AccessReviewItem::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
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
