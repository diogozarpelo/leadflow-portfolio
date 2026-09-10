<?php

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    public const TYPE_CONTACT = 'contact';

    public const TYPE_SAMPLE_REQUEST = 'sample_request';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const array TYPES = [
        self::TYPE_CONTACT,
        self::TYPE_SAMPLE_REQUEST,
        self::TYPE_WHATSAPP,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_FAILED = 'failed';

    public const array STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_RETRYING,
        self::STATUS_SENT,
        self::STATUS_FAILED,
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_STATUS_TRANSITIONS = [
        self::STATUS_PENDING => [
            self::STATUS_PROCESSING,
        ],
        self::STATUS_PROCESSING => [
            self::STATUS_SENT,
            self::STATUS_RETRYING,
            self::STATUS_FAILED,
        ],
        self::STATUS_RETRYING => [
            self::STATUS_PROCESSING,
            self::STATUS_FAILED,
        ],
        self::STATUS_FAILED => [
            self::STATUS_RETRYING,
        ],
        self::STATUS_SENT => [],
    ];

    protected $fillable = [
        'type',
        'name',
        'email',
        'company',
        'company_registration',
        'phone',
        'sector',
        'location',
        'quantity',
        'message',
        'language',
        'source_page',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function assignExternalId(string $externalId): void
    {
        $this->forceFill([
            'external_id' => $externalId,
        ])->save();
    }

    public static function resolveByExternalId(
        string $externalId
    ): self {
        return static::query()
            ->where('external_id', $externalId)
            ->sole();
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(LeadDeliveryAttempt::class);
    }

    public function canTransitionTo(string $nextStatus): bool
    {
        $allowedStatuses = self::ALLOWED_STATUS_TRANSITIONS[$this->status] ?? [];

        return in_array($nextStatus, $allowedStatuses, true);
    }

    public function transitionTo(string $nextStatus): void
    {
        $currentStatus = $this->status;

        if (! $this->canTransitionTo($nextStatus)) {
            throw new LogicException(
                "Invalid lead status transition from {$currentStatus} to {$nextStatus}."
            );
        }

        $this->forceFill([
            'status' => $nextStatus,
        ])->save();
    }
}
