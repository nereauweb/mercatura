<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Messages\SendAdminReport;
use App\Actions\Messages\SubmitReport;
use App\Contracts\MessageForwarder;
use App\Models\User;
use App\Support\Reports\ReportAccess;
use App\Support\Reports\ReportAttachments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * JSON endpoint of the storefront report dialog (docs/06_USER_REPORTS.md R4/R5).
 * Customers and guests: SubmitReport decides who may send (403), the limits
 * (429) and the validation (422); a bot caught by the honeypot gets the same
 * answer as a sender. An admin's report goes straight to the forwarder (§2.9).
 */
final class FrontendReportController extends Controller
{
    public function store(Request $request, SubmitReport $submit, ReportAccess $access): JsonResponse
    {
        $user = Auth::user();
        if ($user instanceof User && $access->forwardsDirectly($user)) {
            return $this->forward($request, $user);
        }
        $submit->handle(
            $request->except('attachments'),
            array_values((array) $request->file('attachments', [])),
            $user instanceof User ? $user : null,
            $request,
        );

        return response()->json(['message' => __($user instanceof User ? 'frontend.reports.sent_text' : 'frontend.reports.sent_text_guest')], 201);
    }

    private function forward(Request $request, User $admin): JsonResponse
    {
        $files = array_values(array_filter((array) $request->file('attachments', []), static fn (mixed $f): bool => $f instanceof UploadedFile));
        Validator::make(['attachments' => $files], [
            'attachments' => ['array', 'max:'.(int) config('mercatura.reports.max_files')],
            'attachments.*' => ReportAttachments::fileRules(),
        ])->validate();

        $reference = app(SendAdminReport::class)->handle(
            $admin,
            $request->only(['kind', 'title', 'description', 'page_url']),
            array_map(static fn (UploadedFile $f): array => [(string) $f->getRealPath(), (string) $f->getClientOriginalName()], $files),
            $request,
        );

        return response()->json(['message' => __('frontend.reports.forwarded_text', ['target' => app(MessageForwarder::class)->label(), 'reference' => $reference])], 201);
    }
}
