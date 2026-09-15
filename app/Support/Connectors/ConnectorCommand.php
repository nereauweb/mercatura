<?php

declare(strict_types=1);

namespace App\Support\Connectors;

use App\Models\ImportLog;
use App\Support\ImportConnectors;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class of the import commands, in the core and in connector packages:
 * database log rows (import_logs), the shared pricing helpers and the
 * connector guard. A command that sets $connector refuses to run while
 * its flag is off.
 */
abstract class ConnectorCommand extends Command
{
    /** Connector key this command belongs to (config mercatura.features.connectors.*). */
    protected ?string $connector = null;

    protected $import_id;

    protected $context = 'import';

    protected $download_data = true;

    protected $update_live = true;

    protected $process_product_data = true;

    protected $full_products_update = false;

    protected $update_categories = false;

    protected $process_print_data = false;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        if ($this->connector !== null && ! app(ImportConnectors::class)->isEnabled($this->connector)) {
            throw new RuntimeException("Connector [{$this->connector}] is disabled: set MERCATURA_CONNECTOR_".strtoupper($this->connector).'=true to run '.$this->getName().'.');
        }
    }

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
        match ($type) {
            'success' => $this->info($message),
            'warn' => $this->warn($message),
            'error' => $this->error($message),
            default => $this->line($message),
        };

        return true;
    }

    protected function value_or_null($value = '')
    {
        return $value != '' ? $value : null;
    }

    protected function markup(): MarkupRules
    {
        return app(MarkupRules::class);
    }

    protected function normalized_price($quantity, $price, $source = null, $sku = null)
    {
        return $this->markup()->price((float) $quantity, (float) $price, $source, $sku);
    }

    protected function generate_tiers_normalized_prices($price, $source = null, $sku = null)
    {
        return $this->markup()->tiers((float) $price, $source, $sku);
    }

    protected function normalize_print_setup_price($original_price)
    {
        return $original_price < 1 ? 10 : round($original_price * 1.5, 2);
    }
}
