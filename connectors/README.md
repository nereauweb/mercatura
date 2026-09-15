# connectors/

Private packages checked out here (supplier connectors, and installation
extensions such as a feed for a legacy platform) are discovered at boot
(`docs/ARCHITECTURE.md §13`): `connectors/<name>/composer.json` gives the
PSR-4 namespace and the service provider. The directory is git-ignored:
connectors are private packages, never part of the public core. An
installation may instead require them with Composer; both work the same.

A connector extends `App\Support\Connectors\BaseConnector`, registers
itself on `App\Support\ImportConnectors` from its provider and ships its
own commands, raw tables (migrations), config (with the `enabled` switch),
lang and Filament plugin. Packages: `nereauweb/mercatura-connector-<key>`,
private GitHub repositories; `composer.json` declares the compatible core
in `extra.mercatura.core`.

Development checkout:

```
git clone git@github.com:nereauweb/mercatura-connector-<key>.git connectors/<key>
```

An installation instead requires the package (`ARCHITECTURE.md §8`).
