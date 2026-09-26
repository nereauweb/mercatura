<?php

namespace App\Console\Commands;

use App\Contracts\ImportConnector;
use App\Events\ImportStageCompleted;
use App\Support\ImportConnectors;

class Import extends ImportCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import
		{--download-data= : Override download_data flag (true/false, 1/0)}
		{--update-live= : Override update_live flag (true/false, 1/0)}
		{--process-product-data= : Override process_product_data flag (true/false, 1/0)}
		{--full-products-update= : Override full_products_update flag (true/false, 1/0)}
		{--update-categories= : Override update_categories flag (true/false, 1/0)}
		{--process-customization-data= : Override process_customization_data flag (true/false, 1/0)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run full import process';

    protected $import_id;

    protected $context = 'full_import';

    /** Run one stage of every enabled connector (config mercatura.features.connectors). */
    private function runConnectorStage(string $stage): void
    {
        foreach (app(ImportConnectors::class)->all() as $connector) {
            if (! $connector->enabled()) {
                $this->warn('Connector '.$connector->key().' disabled, stage '.$stage.' skipped');

                continue;
            }
            foreach ($connector->commands($stage) as $command) {
                $this->call($command, ['import_id' => $this->import_id]);
            }
        }
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->applyRuntimeFlags();
        $this->publishFlags();
        app(\App\Support\Connectors\MarkupRules::class)->flush();
        $this->import_id = time();
        $start = microtime(true);

        $flags_output = '';
        if ($this->download_data) {
            $flags_output .= '"Import dati API" ';
        }
        if ($this->update_live) {
            $flags_output .= '"Aggiornamento anticipato prezzi e quantità" ';
        }
        if ($this->process_product_data) {
            $flags_output .= '"Processazione dati prodotti" ';
        }
        if ($this->full_products_update) {
            $flags_output .= '"Forzatura aggiornamento prodotti" ';
        }
        if ($this->process_customization_data) {
            $flags_output .= '"Processazione dati stampa" ';
        }

        $this->db_log('start', 'Processo di import iniziato alle '.date('H:i').' del '.date('d/m/Y').', flag attivi: '.$flags_output, 'warn');

        $this->runConnectorStage(ImportConnector::STAGE_DOWNLOAD);
        if ($this->process_product_data) {
            $this->runConnectorStage(ImportConnector::STAGE_PRODUCTS);
            $this->call('app:ProcessNormalizedProductData', ['import_id' => $this->import_id]);
            $this->call('catalog:color-codes');
            $this->call('app:DisableWrongProducts');
            $this->call('scout:import', ['model' => 'App\Models\Product']);
            ImportStageCompleted::dispatch((int) $this->import_id, ImportConnector::STAGE_PRODUCTS);
        }
        if ($this->process_customization_data) {
            $this->runConnectorStage(ImportConnector::STAGE_CUSTOMIZATIONS);
            $this->call('cleanup:customizations');
            ImportStageCompleted::dispatch((int) $this->import_id, ImportConnector::STAGE_CUSTOMIZATIONS);
        }
        $this->call('app:GenerateSitemap');
        $time_elapsed_secs = microtime(true) - $start;
        $this->db_log('end', 'Processo di import finito alle '.date('H:i').' del '.date('d/m/Y').', durata totale: '.round($time_elapsed_secs / 60, 2).' minuti ('.round($time_elapsed_secs / 60 / 60, 2).' h)', 'success');

    }

    /** The connector commands run by the stages read the same flags as this wrapper. */
    protected function publishFlags(): void
    {
        \App\Support\Connectors\ImportFlags::publish(new \App\Support\Connectors\ImportFlags(
            download_data: (bool) $this->download_data,
            update_live: (bool) $this->update_live,
            process_product_data: (bool) $this->process_product_data,
            full_products_update: (bool) $this->full_products_update,
            update_categories: (bool) $this->update_categories,
            process_customization_data: (bool) $this->process_customization_data,
        ));
    }

    protected function applyRuntimeFlags(): void
    {
        $optionToProperty = [
            'download-data' => 'download_data',
            'update-live' => 'update_live',
            'process-product-data' => 'process_product_data',
            'full-products-update' => 'full_products_update',
            'update-categories' => 'update_categories',
            'process-customization-data' => 'process_customization_data',
        ];

        foreach ($optionToProperty as $option => $property) {
            $rawValue = $this->option($option);
            if ($rawValue === null) {
                continue;
            }

            $parsedValue = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsedValue === null) {
                $this->error("Valore non valido per --{$option}: {$rawValue}. Usa true/false oppure 1/0.");
                throw new \InvalidArgumentException("Invalid boolean option: --{$option}");
            }

            $this->{$property} = $parsedValue;
        }
    }
}
