# Import jobs

`ImportProductsJob` (daily) and `ImportCustomizationsJob` (monthly) run the same
pipeline as `php artisan app:import`, in the queue: the download, products
and printings stages of every enabled connector (`docs/ARCHITECTURE.md §13`),
then the core processing (`app:ProcessNormalizedProductData`,
`app:DisableWrongProducts`, `scout:import`, `cleanup:customizations`).
When a stage is done the job fires `App\Events\ImportStageCompleted`;
installation packages listen to it for their own post-import work.

Flags (constructor options, the admin Imports page, `job:import-products`
and `job:import-customizations`): `download_data`, `update_live`,
`process_product_data`, `full_products_update`, `update_categories`,
`process_customization_data`, `process_source` (`all` or a connector key).

Runs are logged in `import_logs` (`import_id`, `context`, `type`, `event`,
`message`) and shown by the admin Imports page. Timeout 2 hours, one attempt.
Schedule them in `routes/console.php`; the queue worker is described in
`deploy/supervisor-queue.conf.example`.
