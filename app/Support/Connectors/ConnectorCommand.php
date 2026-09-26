<?php

declare(strict_types=1);

namespace App\Support\Connectors;

use App\Models\Customizations\Customization;
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

    protected $process_customization_data = false;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        // A connector's stage command runs as a separate Artisan call: it takes the flags the
        // wrapper (app:import options, panel job choices) published for this run.
        if ($this->connector !== null && ($flags = ImportFlags::current()) !== null) {
            foreach (ImportFlags::KEYS as $key) {
                $this->{$key} = $flags->{$key};
            }
        }

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

    /**
     * Create or update a customization of this connector, unless it is
     * protected from imports (docs/03_CUSTOMIZATIONS.md v2c.6): returns null
     * and logs a skip in that case, so the command leaves its children alone.
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    protected function upsertCustomization(array $keys, array $values): ?Customization
    {
        $existing = Customization::query()->where($keys)->first();
        if ($existing instanceof Customization && $existing->isProtectedFromImport()) {
            $this->line('Customization '.$existing->id.' ('.$existing->technique_label.' / '.$existing->position_label.') is protected from imports, skipped');

            return null;
        }

        // Listed by the source in this run: seen now, and active again if it had been switched off.
        $values += ['last_seen_at' => now(), 'active' => true];

        // Same outcome as updateOrCreate($keys, $values), without repeating the lookup above.
        if ($existing instanceof Customization) {
            $existing->fill($values)->save();

            return $existing;
        }

        return Customization::query()->create(array_merge($keys, $values));
    }

    /**
     * Fingerprint of everything a variant's customizations are computed from
     * (the supplier's rows, the variant's prices, the markup bands, the command's
     * version): equal fingerprints mean the rows already stored are still right.
     *
     * @param  array<string, mixed>  $inputs
     */
    protected function customizationFingerprint(array $inputs): string
    {
        return hash('sha256', (string) json_encode([
            'command' => static::class.'@'.static::FINGERPRINT_VERSION,
            'markup' => $this->markup()->fingerprint(),
            'inputs' => $inputs,
        ]));
    }

    /** Bumped whenever the command changes what it writes for the same inputs. */
    public const FINGERPRINT_VERSION = 1;

    /**
     * True when the variant's active importable customizations (of the given
     * pipeline) exist and all carry this fingerprint: nothing to rewrite. Rows
     * the source dropped earlier are inactive and stay out of the decision.
     */
    protected function customizationsUnchanged(string $source, string $variantSku, string $hash, ?string $pipeline = null): bool
    {
        $rows = Customization::query()->importable()->where('active', true)->where('source', $source)->where('source_variant_sku', $variantSku)
            ->when($pipeline !== null, fn ($q) => $q->where('pipeline', $pipeline))
            ->pluck('source_hash');

        return $rows->isNotEmpty() && $rows->every(fn ($h) => $h === $hash);
    }

    /**
     * The variant's customizations were listed by the source in this run: seen
     * now and active, without touching areas, options or tiers.
     */
    protected function touchCustomizations(string $source, string $variantSku, ?string $pipeline = null): int
    {
        return Customization::query()->importable()->where('source', $source)->where('source_variant_sku', $variantSku)
            ->when($pipeline !== null, fn ($q) => $q->where('pipeline', $pipeline))
            ->update(['last_seen_at' => now(), 'active' => true, 'updated_at' => \Illuminate\Support\Facades\DB::raw('`updated_at`')]);
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
