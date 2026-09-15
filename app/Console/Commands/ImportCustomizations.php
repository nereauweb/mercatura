<?php

namespace App\Console\Commands;

use App\Contracts\ImportConnector;
use App\Support\ImportConnectors;
use Illuminate\Console\Command;

class ImportCustomizations extends ImportCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import_customizations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run printing normalization and import process';

    protected $import_id;

    protected $context = 'import_printings';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->import_id = time();
        $start = microtime(true);

        $this->db_log('start', 'Processo completo di import personalizzazioni iniziato alle '.date('H:i').' del '.date('d/m/Y'), 'warn');

        foreach (app(ImportConnectors::class)->all() as $connector) {
            if (! $connector->enabled()) {
                $this->warn('Connector '.$connector->key().' disabled, printings skipped');

                continue;
            }
            foreach ($connector->commands(ImportConnector::STAGE_CUSTOMIZATIONS) as $command) {
                $this->call($command, ['import_id' => $this->import_id]);
            }
        }

        $time_elapsed_secs = microtime(true) - $start;
        $this->db_log('end', 'Processo di import personalizzazioni finito alle '.date('H:i').' del '.date('d/m/Y').', durata totale: '.round($time_elapsed_secs / 60, 2).' minuti ('.round($time_elapsed_secs / 60 / 60, 2).' h)', 'success');

    }
}
