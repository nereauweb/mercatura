<?php

namespace App\Console\Commands;

use App\Jobs\ImportPrintingsJob;
use Illuminate\Console\Command;

class DispatchImportPrintingsJob extends Command
{
    protected $signature = 'job:import-printings
                            {--download-data=1 : Scarica dati da API (1=sì, 0=no)}
                            {--update-live=1 : Aggiorna prezzi e stock live (1=sì, 0=no)}
                            {--process-print-data=1 : Processa dati stampa (1=sì, 0=no)}
                            {--process-source=all : Connettore da processare (all oppure la chiave di un connettore attivo)}
                            {--sync : Esegui in modo sincrono (per debug)}';

    protected $description = 'Dispatch manual import printings job con opzioni configurabili';

    public function handle()
    {
        $this->info('🚀 Preparazione Import Personalizzazioni...');
        $this->newLine();

        // Costruisci array opzioni dai parametri
        $options = [
            'download_data' => (bool) $this->option('download-data'),
            'update_live' => (bool) $this->option('update-live'),
            'process_print_data' => (bool) $this->option('process-print-data'),
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
            $job = new ImportPrintingsJob($options);
            $job->handle();
            $this->info('✅ Import completato!');
        } else {
            // Esecuzione asincrona (normale)
            $this->info('📤 Job accodato per esecuzione asincrona...');
            ImportPrintingsJob::dispatch($options);
            $this->info('✅ Job accodato con successo!');
            $this->comment('   Monitora con: php artisan queue:work');
        }

        return 0;
    }
}
