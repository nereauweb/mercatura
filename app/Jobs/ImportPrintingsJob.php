<?php

namespace App\Jobs;

use App\Contracts\ImportConnector;
use App\Events\ImportStageCompleted;
use App\Support\CaughtExceptionLogger;

class ImportPrintingsJob extends ImportJob
{
    protected $context = 'job_import_printings';

    // Override flags for printing import
    protected $download_data = true;

    protected $update_live = true;

    protected $process_product_data = false;

    protected $full_products_update = false;

    protected $update_categories = false;

    protected $process_print_data = true;

    protected $process_source = 'all';

    /**
     * Create a new job instance.
     *
     * @param  array  $options  Array of flags to override defaults
     *                          Esempio: ['download_data' => false, 'process_print_data' => true]
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
            'Processo di import personalizzazioni (job) iniziato alle '.date('H:i').' del '.date('d/m/Y').', flag attivi: '.$flags_output,
            'warn'
        );

        try {
            // Import raw data from APIs (needed for printing data) based on process_source
            $this->runConnectorStage(ImportConnector::STAGE_DOWNLOAD);

            // Process printing data
            if ($this->process_print_data) {
                $this->runConnectorStage(ImportConnector::STAGE_PRINTINGS);
                $this->callCommand('cleanup:printing_variants');
                ImportStageCompleted::dispatch((int) $this->import_id, ImportConnector::STAGE_PRINTINGS, (string) $this->process_source);
            }

            $time_elapsed_secs = microtime(true) - $start;

            $this->db_log(
                'end',
                'Processo di import personalizzazioni (job) finito alle '.date('H:i').' del '.date('d/m/Y').
                ', durata totale: '.round($time_elapsed_secs / 60, 2).' minuti ('.round($time_elapsed_secs / 60 / 60, 2).' h)',
                'success'
            );

        } catch (\Exception $e) {
            CaughtExceptionLogger::error('ImportPrintingsJob::handle failed', $e);
            $this->db_log(
                'error',
                'Errore durante il processo di import personalizzazioni: '.$e->getMessage(),
                'error'
            );
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->db_log(
            'failed',
            'Job di import personalizzazioni fallito: '.$exception->getMessage(),
            'error'
        );
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
        if ($this->process_print_data) {
            $flags[] = 'Processazione dati stampa';
        }

        // Aggiungi info sulla fonte
        $source_text = $this->process_source === 'all'
            ? 'Tutte le fonti'
            : 'Fonte: '.(app(\App\Support\ImportConnectors::class)->forSource($this->process_source)?->label() ?? $this->process_source);
        $flags[] = $source_text;

        return implode(', ', array_map(fn ($flag) => "\"$flag\"", $flags));
    }
}
