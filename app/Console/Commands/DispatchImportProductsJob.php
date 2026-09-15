<?php

namespace App\Console\Commands;

use App\Jobs\ImportProductsJob;
use Illuminate\Console\Command;

class DispatchImportProductsJob extends Command
{
    protected $signature = 'job:import-products
                            {--download-data=1 : Scarica dati da API (1=sì, 0=no)}
                            {--update-live=1 : Aggiorna prezzi e stock live (1=sì, 0=no)}
                            {--process-product-data=1 : Processa dati prodotti (1=sì, 0=no)}
                            {--full-products-update=0 : Forza aggiornamento completo prodotti (1=sì, 0=no)}
                            {--update-categories=0 : Aggiorna categorie (1=sì, 0=no)}
                            {--process-source=all : Connettore da processare (all oppure la chiave di un connettore attivo)}
                            {--sync : Esegui in modo sincrono (per debug)}';

    protected $description = 'Dispatch manual import products job con opzioni configurabili';

    public function handle()
    {
        $this->info('🚀 Preparazione Import Prodotti...');
        $this->newLine();

        // Costruisci array opzioni dai parametri
        $options = [
            'download_data' => (bool) $this->option('download-data'),
            'update_live' => (bool) $this->option('update-live'),
            'process_product_data' => (bool) $this->option('process-product-data'),
            'full_products_update' => (bool) $this->option('full-products-update'),
            'update_categories' => (bool) $this->option('update-categories'),
            'process_source' => $this->option('process-source'),
        ];

        // Mostra configurazione
        $this->table(
            ['Opzione', 'Valore'],
            collect($options)->map(fn ($value, $key) => [
                $key,
                $value ? '✓ Sì' : '✗ No',
            ])->values()->toArray()
        );

        $this->newLine();

        // Conferma
        if (! $this->confirm('Procedere con l\'import?', true)) {
            $this->warn('Import annullato.');

            return 0;
        }

        // Dispatch job
        if ($this->option('sync')) {
            // Esecuzione sincrona (per debug)
            $this->info('⚙️  Esecuzione SINCRONA...');
            $job = new ImportProductsJob($options);
            $job->handle();
            $this->info('✅ Import completato!');
        } else {
            // Esecuzione asincrona (normale)
            $this->info('📤 Job accodato per esecuzione asincrona...');
            ImportProductsJob::dispatch($options);
            $this->info('✅ Job accodato con successo!');
            $this->comment('   Monitora con: php artisan queue:work');
        }

        return 0;
    }
}
