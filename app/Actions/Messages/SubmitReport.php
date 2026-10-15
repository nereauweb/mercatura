<?php

declare(strict_types=1);

namespace App\Actions\Messages;

use App\Enums\MessageType;
use App\Enums\ReporterRole;
use App\Enums\ReportKind;
use App\Models\Message;
use App\Models\User;
use App\Rules\Captcha;
use App\Support\ImageSanitizer;
use App\Support\Reports\ReportAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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

    private const MIMETYPES = ['image/png', 'image/jpeg', 'image/webp', 'application/pdf'];

    private const EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'pdf'];

    public function __construct(private readonly ReportAccess $access) {}

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
        $this->throttle($user, (string) $request->ip());

        $files = array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile));
        if ($files !== [] && ! $this->access->canAttach($user)) {
            throw ValidationException::withMessages(['attachments' => __('frontend.reports.errors.guest_attachments')]);
        }
        $data = $this->validate($input, $files, $user);
        $this->checkUploadQuota($user, $files);
        $clean = $this->sanitize($files);

        try {
            return $this->store($data, $clean, $user, $request);
        } finally {
            foreach ($clean as [$path]) {
                @unlink($path);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{0: string, 1: string}>  $clean
     */
    private function store(array $data, array $clean, ?User $user, Request $request): Message
    {
        $message = DB::transaction(function () use ($data, $user, $request): Message {
            return Message::create([
                'type' => MessageType::Report,
                'user_id' => $user?->id,
                'reporter_role' => $user !== null ? ReporterRole::Customer : ReporterRole::Guest,
                'kind' => ReportKind::from((string) $data['kind']),
                ...$this->identity($user, $data),
                'subject' => (string) $data['title'],
                'message' => (string) $data['description'],
                'page_url' => $this->sameSiteUrl($data['page_url'] ?? null, $request),
                'context' => $this->context($data, $request),
            ]);
        });

        foreach ($clean as [$path, $original]) {
            $message->addMedia($path)
                ->usingName(Str::limit($original, 120, ''))
                ->usingFileName(Str::random(24).'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION)))
                ->toMediaCollection(Message::ATTACHMENTS);
        }

        return $message;
    }

    private function throttle(?User $user, string $ip): void
    {
        $limits = (array) config('mercatura.reports.limits');
        $windows = $user !== null
            ? ['reports:user-minute:'.$user->id => [(int) $limits['user_per_minute'], 60], 'reports:user-day:'.$user->id => [(int) $limits['user_per_day'], 86400]]
            : ['reports:ip-minute:'.$ip => [(int) $limits['guest_per_minute'], 60], 'reports:ip-day:'.$ip => [(int) $limits['guest_per_day'], 86400]];

        foreach ($windows as $key => [$max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new ThrottleRequestsException(__('frontend.reports.errors.too_many'));
            }
        }
        foreach ($windows as $key => [$max, $decay]) {
            RateLimiter::hit($key, $decay);
        }
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
            'name' => [$guest ? 'required' : 'prohibited', 'string', 'max:64'],
            'email' => [$guest ? 'required' : 'prohibited', 'email', 'max:64'],
            'attachments' => ['array', 'max:'.(int) config('mercatura.reports.max_files')],
            'attachments.*' => ['file', 'max:'.(int) config('mercatura.reports.max_file_kb'), 'mimetypes:'.implode(',', self::MIMETYPES), 'mimes:'.implode(',', self::EXTENSIONS)],
            ...Captcha::rules('report'),
        ];

        return Validator::make([...$input, 'attachments' => $files], $rules)->validate();
    }

    /** @param  list<UploadedFile>  $files */
    private function checkUploadQuota(?User $user, array $files): void
    {
        if ($user === null || $files === []) {
            return;
        }
        $kb = (int) ceil(array_sum(array_map(static fn (UploadedFile $f): int => (int) $f->getSize(), $files)) / 1024);
        $key = 'reports:user-kb:'.$user->id;
        if (RateLimiter::attempts($key) + $kb > (int) config('mercatura.reports.limits.user_mb_per_day') * 1024) {
            throw new ThrottleRequestsException(__('frontend.reports.errors.quota'));
        }
        RateLimiter::increment($key, 86400, $kb);
    }

    /**
     * Copies each upload to a temporary file; images are re-encoded from their
     * pixels, PDFs kept as received. A file that claims to be an image but does
     * not decode is refused.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array{0: string, 1: string}> path, original name
     */
    private function sanitize(array $files): array
    {
        $clean = [];
        foreach ($files as $i => $file) {
            $mime = (string) $file->getMimeType();
            $extension = $mime === 'application/pdf' ? 'pdf' : match ($mime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
            $base = (string) tempnam(sys_get_temp_dir(), 'report');
            $path = $base.'.'.$extension;
            @unlink($base);
            copy((string) $file->getRealPath(), $path);
            if ($extension !== 'pdf' && ! ImageSanitizer::reencode($path)) {
                @unlink($path);
                foreach ($clean as [$done]) {
                    @unlink($done);
                }
                throw ValidationException::withMessages(['attachments.'.$i => __('frontend.reports.errors.invalid_image')]);
            }
            $clean[] = [$path, (string) $file->getClientOriginalName()];
        }

        return $clean;
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

    /** The page URL only when it belongs to this site; anything else is dropped. */
    private function sameSiteUrl(mixed $url, Request $request): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return parse_url($url, PHP_URL_HOST) === $request->getHost() ? $url : null;
    }

    /**
     * Technical context: the route and area of the reported page (resolved
     * here, not trusted from the client), viewport, user agent, recent JS errors.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function context(array $data, Request $request): array
    {
        $url = $this->sameSiteUrl($data['page_url'] ?? null, $request);
        $route = null;
        if ($url !== null) {
            try {
                $route = app('router')->getRoutes()->match(Request::create($url))->getName();
            } catch (\Throwable) {
                $route = null;
            }
        }

        return array_filter([
            'area' => self::area($route),
            'route' => $route,
            'viewport' => $data['viewport'] ?? null,
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'js_errors' => array_values((array) ($data['js_errors'] ?? [])) ?: null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** admin | account | storefront, from the route name of the reported page. */
    public static function area(?string $route): string
    {
        return match (true) {
            $route === null => 'storefront',
            str_starts_with($route, 'filament.') => 'admin',
            str_starts_with($route, 'frontend.auth.'), str_starts_with($route, 'frontend.contacts.list'), str_starts_with($route, 'frontend.contacts.show'),
            str_starts_with($route, 'frontend.quotation.index'), str_starts_with($route, 'customer.') => 'account',
            default => 'storefront',
        };
    }
}
