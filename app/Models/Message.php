<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Enums\ReporterRole;
use App\Enums\ReportKind;
use App\Mail\NotifyQuotationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A contact-form message or a user report (docs/06_USER_REPORTS.md). Report
 * attachments live in the `attachments` collection on the private `reports` disk.
 */
class Message extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const ATTACHMENTS = 'attachments';

    protected $table = 'messages';

    protected $fillable = [
        'type',
        'user_id',
        'reporter_role',
        'kind',
        'page_url',
        'context',
        'screenshot_consent_at',
        'screenshot_consent_version',
        'email',
        'phone',
        'name',
        'surname',
        'company',
        'activity',
        'subject',
        'message',
        'consent_gdpr',
        'subscribe_newsletter',
        'consent_terms',
        'read_at',
        'forwarded_at',
        'forwarded_by',
        'forward_reference',
    ];

    protected $casts = [
        'read_at' => 'datetime', 'forwarded_at' => 'datetime', 'screenshot_consent_at' => 'datetime', 'consent_gdpr' => 'boolean', 'consent_terms' => 'boolean', 'subscribe_newsletter' => 'boolean',
        'type' => MessageType::class, 'reporter_role' => ReporterRole::class, 'kind' => ReportKind::class, 'context' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function forwarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_by');
    }

    /**
     * The messages a user may open in the account area: linked to the account,
     * never matched by email (registration does not verify the address).
     *
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeOwnedBy(Builder $query, ?User $user): Builder
    {
        return $query->where('user_id', $user->id ?? 0);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENTS)->useDisk('reports');
    }

    public function notify_admin()
    {
        Mail::to(config('emails.merchant'))
            ->cc(config('emails.technical'))
            ->send(new NotifyQuotationRequest($this));
    }
}
