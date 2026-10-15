<?php

declare(strict_types=1);

namespace App\Actions\Messages;

use App\Contracts\MessageForwarder;
use App\Enums\ReportKind;
use App\Models\Message;
use App\Models\User;
use App\Support\Reports\ForwardedReport;
use App\Support\Reports\Redactor;
use App\Support\Reports\TechnicalBlock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Forwards a user's report to the installation's helpdesk, pseudonymised
 * (docs/06_USER_REPORTS.md §2.5, §6): the admin is the reporter, the user is
 * the reference `messages#<id>`; title and description are the ones the admin
 * reviewed; only the attachments the admin chose leave the platform. The
 * message itself is not changed, only the forward is recorded on it.
 */
final class ForwardMessage
{
    public function __construct(private readonly MessageForwarder $forwarder) {}

    /**
     * @param  list<int>  $mediaIds  attachments chosen by the admin
     *
     * @throws AuthorizationException
     * @throws RuntimeException when the target refuses or is not available
     */
    public function handle(Message $message, User $admin, string $title, string $description, array $mediaIds): string
    {
        if (! Gate::forUser($admin)->allows('forward', $message)) {
            throw new AuthorizationException;
        }
        if (! $this->forwarder->isAvailable()) {
            throw new RuntimeException('No message forwarder is available.');
        }
        Validator::make(['title' => $title, 'description' => $description], [
            'title' => ['required', 'string', 'max:'.(int) config('mercatura.reports.title_max')],
            'description' => ['required', 'string', 'max:'.(int) config('mercatura.reports.description_max')],
        ])->validate();

        $context = [
            'reference' => 'messages#'.$message->id,
            'reporter_role' => $message->reporter_role?->value,
            'area' => $message->context['area'] ?? null,
            'route' => $message->context['route'] ?? null,
            'viewport' => $message->context['viewport'] ?? null,
            'user_agent' => $message->context['user_agent'] ?? null,
            'js_errors' => array_map(static fn (mixed $e): string => Redactor::text((string) $e), (array) ($message->context['js_errors'] ?? [])) ?: null,
        ];
        $attachments = $message->getMedia(Message::ATTACHMENTS)
            ->filter(fn ($media): bool => in_array((int) $media->id, $mediaIds, true))
            ->map(fn ($media): array => [
                'path' => Storage::disk($media->disk)->path($media->getPathRelativeToRoot()),
                'name' => (string) $media->name,
                'mime' => (string) $media->mime_type,
            ])->values()->all();

        $reference = $this->forwarder->forward(new ForwardedReport(
            kind: $message->kind ?? ReportKind::Bug,
            title: $title,
            description: $description,
            pageUrl: TechnicalBlock::pageWithoutQuery($message->page_url),
            reporter: ['name' => (string) $admin->name, 'email' => (string) $admin->email],
            context: array_filter($context, static fn (mixed $v): bool => $v !== null),
            technical: TechnicalBlock::render([...$context, 'reference' => __('admin.message.forward.tech.message_reference', ['id' => $message->id, 'role' => $message->reporter_role?->getLabel() ?? '-'])]),
            attachments: $attachments,
        ));

        $message->forceFill([
            'forwarded_at' => now(),
            'forwarded_by' => $admin->id,
            'forward_reference' => Str::limit($reference, 64, ''),
        ])->save();

        return $reference;
    }
}
