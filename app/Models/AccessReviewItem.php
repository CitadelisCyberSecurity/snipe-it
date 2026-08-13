<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessReviewItem extends SnipeModel
{
    use HasFactory;

    public const STATUS_KEEP = 'keep';
    public const STATUS_MODIFY = 'modify';
    public const STATUS_DELETE = 'delete';

    public const VALID_STATUSES = [
        self::STATUS_KEEP,
        self::STATUS_MODIFY,
        self::STATUS_DELETE,
    ];

    protected $table = 'access_review_items';

    protected $fillable = [
        'manager_status',
        'manager_comment',
    ];

    protected $casts = [
        'cost_per_seat_snapshot' => 'decimal:2',
        'manager_completed_at' => 'datetime',
        'admin_executed_at' => 'datetime',
        'campaign_id' => 'integer',
        'user_id' => 'integer',
        'manager_id' => 'integer',
        'license_id' => 'integer',
        'license_seat_id' => 'integer',
        'admin_executed_by' => 'integer',
        'auto_executed' => 'boolean',
    ];

    /**
     * The relation methods below carry native `: BelongsTo` return types,
     * which PHPStan reads in preference to inferring from the body. A bare
     * `BelongsTo` means `BelongsTo<Model, Model>`, so `$item->manager` and
     * friends degrade to the base Model class and every property read off
     * them ($manager->email, $licenseSeat->assigned_to) is flagged as
     * undefined. The generics below restore the concrete related type.
     *
     * @return BelongsTo<AccessReviewCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AccessReviewCampaign::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id')->withTrashed();
    }

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class, 'license_id')->withTrashed();
    }

    /**
     * @return BelongsTo<LicenseSeat, $this>
     */
    public function licenseSeat(): BelongsTo
    {
        return $this->belongsTo(LicenseSeat::class, 'license_seat_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_executed_by')->withTrashed();
    }

    public function isReviewed(): bool
    {
        return $this->manager_status !== null;
    }

    public function isCompleted(): bool
    {
        return $this->manager_completed_at !== null;
    }

    public function isExecuted(): bool
    {
        return $this->admin_executed_at !== null;
    }
}
