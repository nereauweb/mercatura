<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Support\ImageSanitizer;
use finfo;
use Illuminate\Validation\ValidationException;

/**
 * The attachment pipeline of a report (docs/06_USER_REPORTS.md §4): PNG,
 * JPEG, WebP, PDF only; each file is copied to a temporary path, images are
 * re-encoded from their pixels (a file that does not decode is refused),
 * PDFs are kept as received. The caller removes the copies with cleanup().
 */
final class ReportAttachments
{
    public const MIMETYPES = ['image/png', 'image/jpeg', 'image/webp', 'application/pdf'];

    public const EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'pdf'];

    /** @return list<string> validation rules for one uploaded file */
    public static function fileRules(): array
    {
        return ['file', 'max:'.(int) config('mercatura.reports.max_file_kb'), 'mimetypes:'.implode(',', self::MIMETYPES), 'mimes:'.implode(',', self::EXTENSIONS)];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $files  source path, original name
     * @return list<array{0: string, 1: string, 2: string}> clean temporary path, original name, MIME type
     *
     * @throws ValidationException
     */
    public function prepare(array $files, string $errorKey = 'attachments'): array
    {
        $clean = [];
        foreach ($files as $i => [$source, $name]) {
            $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($source);
            $extension = match ($mime) {
                'application/pdf' => 'pdf',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/jpeg' => 'jpg',
                default => null,
            };
            $path = null;
            if ($extension !== null) {
                $base = (string) tempnam(sys_get_temp_dir(), 'report');
                $path = $base.'.'.$extension;
                @unlink($base);
                copy($source, $path);
            }
            if ($path === null || ($extension !== 'pdf' && ! ImageSanitizer::reencode($path))) {
                if ($path !== null) {
                    @unlink($path);
                }
                $this->cleanup($clean);
                throw ValidationException::withMessages([$errorKey.'.'.$i => __('frontend.reports.errors.invalid_image')]);
            }
            $clean[] = [$path, $name, $mime];
        }

        return $clean;
    }

    /** @param  list<array{0: string, 1: string, 2: string}>  $clean */
    public function cleanup(array $clean): void
    {
        foreach ($clean as [$path]) {
            @unlink($path);
        }
    }
}
