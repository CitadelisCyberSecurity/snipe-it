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
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AccessReviewCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id')->withTrashed();
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class, 'license_id')->withTrashed();
    }

    public function licenseSeat(): BelongsTo
    {
        return $this->belongsTo(LicenseSeat::class, 'license_seat_id')->withTrashed();
    }

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
