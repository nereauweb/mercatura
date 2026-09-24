<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\TransactionalMailer;
use App\Support\MailSampleData;
use App\Support\MailTemplateCatalog;
use Illuminate\Console\Command;

/** Sends one or every transactional template with sample data through the configured provider. */
final class MailSendTest extends Command
{
    protected $signature = 'mail:send-test {to : Recipient address} {--template= : One template key; all of them when omitted}';

    protected $description = 'Send the transactional templates with sample data to an address (checks the configured mail provider and its hosted templates)';

    public function handle(MailTemplateCatalog $catalog, TransactionalMailer $mailer): int
    {
        $keys = $this->option('template') ? [(string) $this->option('template')] : $catalog->keys();
        $failed = 0;
        foreach ($keys as $key) {
            try {
                $mailer->reset();
                foreach (MailSampleData::attributes() as $name => $value) {
                    $mailer->attribute($name, $value);
                }
                $mailer->to((string) $this->argument('to'))->send($key);
                $this->info("sent {$key}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("{$key}: ".$e->getMessage());
            }
        }
        $this->line('provider: '.config('mercatura.providers.mail').', failed: '.$failed.'/'.count($keys));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
