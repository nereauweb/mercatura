<?php

declare(strict_types=1);

namespace App\Actions\Messages;

use App\Contracts\MessageForwarder;
use App\Enums\ReportKind;
use App\Models\User;
use App\Support\Reports\ForwardedReport;
use App\Support\Reports\Redactor;
use App\Support\Reports\ReportAttachments;
use App\Support\Reports\ReportContext;
use App\Support\Reports\ReportLimits;
use App\Support\Reports\TechnicalBlock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * An admin's own report, sent straight to the helpdesk (docs/06_USER_REPORTS.md §2.9):
 * nothing is stored in `messages`, delivery and retries are the forwarder's.
 * The text is redacted automatically (an admin may quote a customer), the
 * attachments go through the same pipeline as the users' and all of them leave.
 */
final class SendAdminReport
{
    public function __construct(
        private readonly MessageForwarder $forwarder,
        private readonly ReportLimits $limits,
        private readonly ReportAttachments $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $input  kind, title, description, page_url
     * @param  list<array{0: string, 1: string}>  $files  stored upload path, original name
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws RuntimeException when the target refuses or is not available
     */
    public function handle(User $admin, array $input, array $files, Request $request): string
    {
        if (! $admin->hasRole('admin') || ! $admin->can('messages.forward')) {
            throw new AuthorizationException;
        }
        if (! $this->forwarder->isAvailable()) {
            throw new RuntimeException('No message forwarder is available.');
        }
        $this->limits->hit($admin, (string) $request->ip());
        $data = Validator::make($input, [
            'kind' => ['required', Rule::enum(ReportKind::class)],
            'title' => ['required', 'string', 'max:'.(int) config('mercatura.reports.title_max')],
            'description' => ['required', 'string', 'max:'.(int) config('mercatura.reports.description_max')],
            'page_url' => ['nullable', 'string', 'max:2048'],
        ])->validate();
        $this->checkFiles($files);
        $this->limits->upload($admin, (int) array_sum(array_map(static fn (array $f): int => (int) @filesize($f[0]), $files)));

        $clean = $this->attachments->prepare($files);
        try {
            $pageUrl = ReportContext::sameSiteUrl($data['page_url'] ?? null, $request);
            $context = ['reference' => null, 'reporter_role' => 'admin', ...ReportContext::build($pageUrl, $request)];

            return $this->forwarder->forward(new ForwardedReport(
                kind: ReportKind::from((string) $data['kind']),
                title: Redactor::text((string) $data['title']),
                description: Redactor::text((string) $data['description']),
                pageUrl: TechnicalBlock::pageWithoutQuery($pageUrl),
                reporter: ['name' => (string) $admin->name, 'email' => (string) $admin->email],
                context: array_filter($context, static fn (mixed $v): bool => $v !== null),
                technical: TechnicalBlock::render([...$context, 'reference' => __('admin.message.forward.tech.admin_reference')]),
                attachments: array_map(static fn (array $f): array => ['path' => $f[0], 'name' => $f[1], 'mime' => $f[2]], $clean),
            ));
        } finally {
            $this->attachments->cleanup($clean);
        }
    }

    /** @param  list<array{0: string, 1: string}>  $files */
    private function checkFiles(array $files): void
    {
        if (count($files) > (int) config('mercatura.reports.max_files')) {
            throw ValidationException::withMessages(['attachments' => __('validation.max.array', ['attribute' => 'attachments', 'max' => (int) config('mercatura.reports.max_files')])]);
        }
        foreach ($files as $i => [$path]) {
            if (! is_file($path) || (int) filesize($path) > (int) config('mercatura.reports.max_file_kb') * 1024) {
                throw ValidationException::withMessages(['attachments.'.$i => __('validation.max.file', ['attribute' => 'attachments', 'max' => (int) config('mercatura.reports.max_file_kb')])]);
            }
        }
    }
}
