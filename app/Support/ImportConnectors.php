<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ImportConnector;
use App\Models\Product;
use Filament\Contracts\Plugin;
use InvalidArgumentException;

/**
 * Registry of the supplier connectors present in this installation.
 * Connector packages register themselves from their service provider
 * (docs/ARCHITECTURE.md §13); the core ships none. Enabled = registered
 * and mercatura.features.connectors.<key> on.
 */
final class ImportConnectors
{
    /** @var array<string, ImportConnector> keyed by key() */
    private array $connectors = [];

    public function register(ImportConnector $connector): void
    {
        $this->connectors[$connector->key()] = $connector;
    }

    /** @return list<ImportConnector> */
    public function all(): array
    {
        return array_values($this->connectors);
    }

    /** @return list<ImportConnector> */
    public function enabled(): array
    {
        return array_values(array_filter($this->connectors, fn (ImportConnector $c) => $c->enabled()));
    }

    public function has(string $key): bool
    {
        return isset($this->connectors[$key]);
    }

    public function get(string $key): ImportConnector
    {
        return $this->connectors[$key] ?? throw new InvalidArgumentException("Unknown import connector [{$key}].");
    }

    public function find(string $key): ?ImportConnector
    {
        return $this->connectors[$key] ?? null;
    }

    public function isEnabled(string $key): bool
    {
        return $this->find($key)?->enabled() ?? false;
    }

    /** The connector owning a source value in any of its spellings (key, label, legacy aliases), registered or not enabled. */
    public function forSource(?string $source): ?ImportConnector
    {
        if ($source === null || $source === '') {
            return null;
        }
        foreach ($this->connectors as $connector) {
            foreach ($connector->sourceValues() as $value) {
                if (strcasecmp($value, $source) === 0) {
                    return $connector;
                }
            }
        }

        return null;
    }

    public function forProduct(Product $product): ?ImportConnector
    {
        return $this->forSource($product->source);
    }

    /**
     * Enabled connectors matching a process_source ("all", a key, a label or an alias).
     *
     * @return list<ImportConnector>
     */
    public function forProcessSource(string $source): array
    {
        if ($source === 'all' || $source === '') {
            return $this->enabled();
        }
        $connector = $this->forSource($source);

        return $connector !== null && $connector->enabled() ? [$connector] : [];
    }

    /** Every source value of every registered connector (for queries on products.source). @return list<string> */
    public function allSourceValues(): array
    {
        $values = [];
        foreach ($this->connectors as $connector) {
            $values = [...$values, ...$connector->sourceValues()];
        }

        return array_values(array_unique($values));
    }

    /** Admin plugins of the enabled connectors. @return list<Plugin> */
    public function adminPlugins(): array
    {
        $plugins = [];
        foreach ($this->enabled() as $connector) {
            $plugin = $connector->adminPlugin();
            if ($plugin !== null) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }
}
