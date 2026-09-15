<?php

namespace App\Jobs;

use App\Contracts\ImportConnector;
use App\Events\ImportStageCompleted;
use App\Support\CaughtExceptionLogger;

class ImportProductsJob extends ImportJob
{
    protected $context = 'job_import_products';

    // Override flags for product import
    protected $download_data = true;

    protected $update_live = true;

    protected $process_product_data = true;

    protected $full_products_update = false;

    protected $update_categories = false;

    protected $process_customization_data = false;

    protected $process_source = 'all';

    /**
     * Create a new job instance.
     *
     * @param  array  $options  Array of flags to override defaults
     *                          Esempio: ['full_products_update' => true, 'download_data' => false]
     */
    public function __construct(array $options = [])
    {
        parent::__construct();

        // Override default flags with provided options
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $start = microtime(true);

        $flags_output = $this->buildFlagsOutput();

        $this->db_log(
            'start',
            'Processo di import prodotti (job) iniziato alle '.date('H:i').' del '.date('d/m/Y').', flag attivi: '.$flags_output,
            'warn'
        );

        try {
            // Import raw data from APIs based on process_source
            $this->runConnectorStage(ImportConnector::STAGE_DOWNLOAD);

            // Process product data if enabled
            if ($this->process_product_data) {
                $this->runConnectorStage(ImportConnector::STAGE_PRODUCTS);
                $this->callCommand('app:ProcessNormalizedProductData', ['import_id' => $this->import_id]);
                $this->callCommand('app:DisableWrongProducts');
                $this->callCommand('scout:import', ['model' => 'App\Models\Product']);
                ImportStageCompleted::dispatch((int) $this->import_id, ImportConnector::STAGE_PRODUCTS, (string) $this->process_source);
            }

            $time_elapsed_secs = microtime(true) - $start;

            $this->db_log(
                'end',
                'Processo di import prodotti (job) finito alle '.date('H:i').' del '.date('d/m/Y').
                ', durata totale: '.round($time_elapsed_secs / 60, 2).' minuti ('.round($time_elapsed_secs / 60 / 60, 2).' h)',
                'success'
            );

        } catch (\Exception $e) {
            CaughtExceptionLogger::error('ImportProductsJob::handle failed', $e);
            $this->db_log(
                'error',
                'Errore durante il processo di import prodotti: '.$e->getMessage(),
                'error'
            );
            throw $e;
        }
    }

    /**
     * Build flags output string for logging
     */
    private function buildFlagsOutput(): string
    {
        $flags = [];

        if ($this->download_data) {
            $flags[] = 'Import dati API';
        }
        if ($this->update_live) {
            $flags[] = 'Aggiornamento anticipato prezzi e quantità';
        }
        if ($this->process_product_data) {
            $flags[] = 'Processazione dati prodotti';
        }
        if ($this->full_products_update) {
            $flags[] = 'Forzatura aggiornamento prodotti';
        }
        if ($this->update_categories) {
            $flags[] = 'Aggiornamento categorie';
        }

        // Aggiungi info sulla fonte
        $source_text = $this->process_source === 'all'
            ? 'Tutte le fonti'
            : 'Fonte: '.(app(\App\Support\ImportConnectors::class)->forSource($this->process_source)?->label() ?? $this->process_source);
        $flags[] = $source_text;

        return implode(', ', array_map(fn ($flag) => "\"$flag\"", $flags));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->db_log(
            'failed',
            'Job di import prodotti fallito: '.$exception->getMessage(),
            'error'
        );
    }
}
