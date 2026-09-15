<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Text generation for catalogue enrichment (descriptions, meta tags,
 * attribute extraction). Selected by config('mercatura.providers.ai').
 * v2a ships the contract and the null driver only.
 */
interface AIEnrichmentProvider
{
    /** Driver name, e.g. "anthropic" or "null". */
    public function key(): string;

    /** False when no model is reachable; callers keep the existing content. */
    public function available(): bool;

    /**
     * Run one instruction against optional context and return the text, or
     * null when the driver cannot answer.
     *
     * @param  array<string, mixed>  $context
     */
    public function complete(string $instruction, array $context = []): ?string;
}
