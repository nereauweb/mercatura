<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Drivers\Mail\MandrillTransactionalMailer;
use App\Support\MailTemplateCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Creates or updates the Mandrill (Mailchimp Transactional) templates of an
 * installation from a directory holding `<key>.html` files and a
 * `manifest.json` ({key: {slug, name, subject}}), then prints the
 * MANDRILL_TPL_* lines for the .env. Templates are published unless --draft.
 */
final class MailMandrillPush extends Command
{
    protected $signature = 'mail:mandrill-push {dir : Directory with manifest.json and the <key>.html files} {--draft : Upload without publishing} {--dry-run : Show what would be sent}';

    protected $description = 'Create or update the Mandrill templates of the installation from a directory of Handlebars HTML files';

    public function handle(): int
    {
        $dir = rtrim((string) $this->argument('dir'), '/');
        $manifestFile = $dir.'/manifest.json';
        if (! File::isFile($manifestFile)) {
            $this->error("manifest.json not found in {$dir}");

            return self::FAILURE;
        }
        /** @var array<string, array{slug: string, name: string, subject: string}> $manifest */
        $manifest = (array) json_decode(File::get($manifestFile), true);
        $key = (string) config('services.mandrill.key');
        if ($key === '' && ! $this->option('dry-run')) {
            $this->error('MANDRILL_API_KEY is not set');

            return self::FAILURE;
        }
        $existing = [];
        if (! $this->option('dry-run')) {
            try {
                $existing = array_fill_keys($this->mandrill()->templateSlugs(), true);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }
        $env = [];
        foreach ($manifest as $templateKey => $meta) {
            $html = $dir.'/'.$templateKey.'.html';
            if (! File::isFile($html)) {
                $this->error("missing {$html}");

                return self::FAILURE;
            }
            $payload = [
                'name' => (string) $meta['slug'],
                'code' => File::get($html),
                'subject' => (string) $meta['subject'],
                'from_email' => (string) config('services.mandrill.from_email'),
                'from_name' => (string) config('services.mandrill.from_name'),
                'labels' => ['mercatura', (string) config('mercatura.skin', 'core')],
                'publish' => ! $this->option('draft'),
            ];
            $action = isset($existing[$meta['slug']]) ? 'update' : 'add';
            if ($this->option('dry-run')) {
                $this->line("{$action} {$meta['slug']} ({$meta['subject']}, ".strlen($payload['code']).' bytes)');
            } else {
                try {
                    $slug = $this->mandrill()->upsertTemplate($payload, $action === 'update');
                } catch (\RuntimeException $e) {
                    $this->error($e->getMessage());

                    return self::FAILURE;
                }
                $this->info("{$action} {$slug}".($payload['publish'] ? ' (published)' : ' (draft)'));
            }
            $env[] = 'MANDRILL_TPL_'.strtoupper($templateKey).'='.$meta['slug'];
        }
        $this->newLine();
        $this->line('# .env');
        foreach ($env as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    private function mandrill(): MandrillTransactionalMailer
    {
        return MandrillTransactionalMailer::fromConfig(app(MailTemplateCatalog::class));
    }
}
