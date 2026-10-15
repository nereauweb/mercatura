<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Messages\SubmitReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * JSON endpoint of the storefront report dialog (docs/06_USER_REPORTS.md R4/R5):
 * SubmitReport decides who may send (403), the limits (429) and the
 * validation (422). A bot caught by the honeypot gets the same answer as a sender.
 */
final class FrontendReportController extends Controller
{
    public function store(Request $request, SubmitReport $submit): JsonResponse
    {
        $user = Auth::user();
        $submit->handle(
            $request->except('attachments'),
            array_values((array) $request->file('attachments', [])),
            $user instanceof User ? $user : null,
            $request,
        );

        return response()->json(['message' => __('frontend.reports.sent_text')], 201);
    }
}
