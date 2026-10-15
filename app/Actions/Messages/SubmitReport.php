<?php

declare(strict_types=1);

namespace App\Actions\Messages;

use App\Enums\MessageType;
use App\Enums\ReporterRole;
use App\Enums\ReportKind;
use App\Models\Message;
use App\Models\User;
use App\Rules\Captcha;
use App\Support\Reports\ReportAccess;
use App\Support\Reports\ReportAttachments;
use App\Support\Reports\ReportContext;
use App\Support\Reports\ReportLimits;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A user's report to the admin, stored as a `messages` row (docs/06_USER_REPORTS.md).
 * Identity, role and area come from the session and the server, never from
 * the form; images are re-encoded before storage; guests attach nothing.
 * Returns null when the honeypot caught a bot (the caller answers as if sent).
 */
final class SubmitReport
{
    public const HONEYPOT = 'website';

    public function __construct(
        private readonly ReportAccess $access,
        private readonly ReportLimits $limits,
        private readonly ReportAttachments $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $input  kind, title, description, page_url, name/email (guests), viewport, js_errors, captcha token
     * @param  array<int, mixed>  $files  the request's uploads; anything that is not an uploaded file is ignored
     *
     * @throws AuthorizationException when the access mode refuses this sender
     * @throws ThrottleRequestsException when a rate limit is reached
     * @throws ValidationException
     */
    public function handle(array $input, array $files, ?User $user, Request $request): ?Message
    {
        if (! $this->access->allows($user)) {
            throw new AuthorizationException(__('frontend.reports.errors.not_allowed'));
        }
        if ($user === null && filled($input[self::HONEYPOT] ?? null)) {
            return null;
        }
        $this->limits->hit($user, (string) $request->ip());

        $files = array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile));
        if ($files !== [] && ! $this->access->canAttach($user)) {
            throw ValidationException::withMessages(['attachments' => __('frontend.reports.errors.guest_attachments')]);
        }
        $data = $this->validate($input, $files, $user);
        if ($user !== null) {
            $this->limits->upload($user, (int) array_sum(array_map(static fn (UploadedFile $f): int => (int) $f->getSize(), $files)));
        }
        $clean = $this->attachments->prepare(array_map(static fn (UploadedFile $f): array => [(string) $f->getRealPath(), (string) $f->getClientOriginalName()], $files));

        try {
            return $this->store($data, $clean, $user, $request);
        } finally {
            $this->attachments->cleanup($clean);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{0: string, 1: string, 2: string}>  $clean
     */
    private function store(array $data, array $clean, ?User $user, Request $request): Message
    {
        $pageUrl = ReportContext::sameSiteUrl($data['page_url'] ?? null, $request);
        $message = DB::transaction(fn (): Message => Message::create([
            'type' => MessageType::Report,
            'user_id' => $user?->id,
            'reporter_role' => $user !== null ? ReporterRole::Customer : ReporterRole::Guest,
            'kind' => ReportKind::from((string) $data['kind']),
            ...$this->identity($user, $data),
            'subject' => (string) $data['title'],
            'message' => (string) $data['description'],
            'page_url' => $pageUrl,
            'context' => ReportContext::build($pageUrl, $request, $data),
            // The capture consent (§5) is recorded only when something was attached under it.
            ...($clean !== [] && filter_var($data['screenshot_consent'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ? ['screenshot_consent_at' => now(), 'screenshot_consent_version' => (string) config('mercatura.reports.screenshot_consent_version')]
                : []),
        ]));

        foreach ($clean as [$path, $original]) {
            $message->addMedia($path)
                ->usingName(Str::limit($original, 120, ''))
                ->usingFileName(Str::random(24).'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION)))
                ->toMediaCollection(Message::ATTACHMENTS);
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $files
     * @return array<string, mixed>
     */
    private function validate(array $input, array $files, ?User $user): array
    {
        $guest = $user === null;
        $rules = [
            'kind' => ['required', Rule::enum(ReportKind::class)],
            'title' => ['required', 'string', 'max:'.(int) config('mercatura.reports.title_max')],
            'description' => ['required', 'string', 'max:'.(int) config('mercatura.reports.description_max')],
            'page_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'viewport' => ['nullable', 'string', 'regex:/^\d{2,5}x\d{2,5}$/'],
            'js_errors' => ['nullable', 'array', 'max:5'],
            'js_errors.*' => ['string', 'max:300'],
            'screenshot_consent' => ['nullable', 'boolean'],
            'name' => [$guest ? 'required' : 'prohibited', 'string', 'max:64'],
            'email' => [$guest ? 'required' : 'prohibited', 'email', 'max:64'],
            'attachments' => ['array', 'max:'.(int) config('mercatura.reports.max_files')],
            'attachments.*' => ReportAttachments::fileRules(),
            ...Captcha::rules('report'),
        ];

        return Validator::make([...$input, 'attachments' => $files], $rules)->validate();
    }

    /**
     * Name and email of the sender: from the account when logged in (the form
     * fields are prohibited then), typed by a guest otherwise.
     *
     * @param  array<string, mixed>  $data
     * @return array{email: string, name: string, surname: ?string}
     */
    private function identity(?User $user, array $data): array
    {
        if ($user === null) {
            return ['email' => (string) $data['email'], 'name' => (string) $data['name'], 'surname' => null];
        }
        // The relation, not the `customer` accessor: that one creates an empty customer row when missing.
        $customer = $user->customer()->first();

        return [
            'email' => (string) $user->email,
            'name' => Str::limit((string) ($customer->name ?? $user->name), 64, ''),
            'surname' => $customer?->surname !== null ? Str::limit((string) $customer->surname, 64, '') : null,
        ];
    }
}
