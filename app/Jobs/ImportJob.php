<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Support\CaughtExceptionLogger;
use App\Support\ImportConnectors;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

abstract class ImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 7200; // 2 hours

    protected $import_id;

    protected $context;

    // Import flags
    protected $download_data = true;

    protected $update_live = true;

    protected $process_product_data = true;

    protected $full_products_update = false;

    protected $update_categories = false;

    protected $process_print_data = false;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->import_id = time();
    }

    /**
     * Execute the job.
     */
    abstract public function handle(): void;

    /**
     * Log to database and Laravel log
     */
    protected function db_log($event, $message, $type = 'info', $ref_type = null, $ref = null, $source_ref = null)
    {
        ImportLog::create([
            'import_id' => $this->import_id,
            'type' => $type,
            'context' => $this->context,
            'event' => $event,
            'message' => $message,
            'ref_type' => $ref_type,
            'ref' => $ref,
            'source_ref' => $source_ref,
        ]);

        $logMessage = "[Import {$this->import_id}] [{$this->context}] $message";

        switch ($type) {
            case 'success':
                Log::info($logMessage);
                break;
            case 'warn':
                Log::warning($logMessage);
                break;
            case 'error':
                Log::error($logMessage);
                break;
            default:
                Log::info($logMessage);
        }
    }

    /**
     * Utility method to return value or null
     */
    protected function value_or_null($value = '')
    {
        return $value != '' ? $value : null;
    }

    protected function normalized_price($quantity, $price, $source = null, $sku = null)
    {
        return app(\App\Support\Connectors\MarkupRules::class)->price((float) $quantity, (float) $price, $source, $sku);
    }

    protected function generate_tiers_normalized_prices($price, $source = null, $sku = null)
    {
        return app(\App\Support\Connectors\MarkupRules::class)->tiers((float) $price, $source, $sku);
    }

    protected function normalize_print_setup_price($original_price)
    {
        return $original_price < 1 ? 10 : round($original_price * 1.5, 2);
    }

    /**
     * Call an artisan command with parameters
     */
    /**
     * Run one stage of every enabled connector matching process_source
     * (config mercatura.features.connectors). Disabled connectors are logged and skipped.
     */
    protected function runConnectorStage(string $stage): void
    {
        $source = (string) ($this->process_source ?? 'all');
        $connectors = app(ImportConnectors::class);
        $matching = $source === 'all' ? $connectors->all() : array_values(array_filter($connectors->all(), fn ($c) => in_array(strtolower($source), array_map('strtolower', $c->sourceValues()), true)));
        foreach ($matching as $connector) {
            if (! $connector->enabled()) {
                $this->db_log('skip', 'Connettore '.$connector->label().' disattivato (MERCATURA_CONNECTOR_'.strtoupper($connector->key()).'), fase '.$stage.' saltata', 'warn');

                continue;
            }
            foreach ($connector->commands($stage) as $command) {
                $this->callCommand($command, ['import_id' => $this->import_id]);
            }
        }
    }

    protected function callCommand($command, $parameters = [])
    {
        try {
            Artisan::call($command, $parameters);

            return true;
        } catch (\Exception $e) {
            CaughtExceptionLogger::error('ImportJob::callCommand failed', $e, ['command' => $command]);
            $this->db_log('error', "Errore nell'esecuzione del comando {$command}: ".$e->getMessage(), 'error');
            throw $e;
        }
    }
}
