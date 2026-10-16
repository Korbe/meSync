<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Full backup of a user's business data as one JSON file, and restoring it again.
 *
 * Rows are exported raw (as stored in the database), so dates, JSON columns and
 * decimals come back exactly as they were. IDs are remapped on import, which makes
 * appending to existing data possible.
 */
class Backup
{
    public const FORMAT = 'mesync-backup';

    public const VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $contractIds = DB::table('maintenance_contracts')->where('user_id', $user->id)->pluck('id');
        $invoiceIds = DB::table('invoices')->where('user_id', $user->id)->pluck('id');

        $logo = $user->getFirstMedia('logo');

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'created_at' => now()->toIso8601String(),
            'app_url' => config('app.url'),
            'data' => [
                'business_profiles' => $this->rows('business_profiles', 'user_id', [$user->id]),
                'catalog_items' => $this->rows('catalog_items', 'user_id', [$user->id]),
                'customers' => $this->rows('customers', 'user_id', [$user->id]),
                'maintenance_contracts' => $this->rows('maintenance_contracts', 'user_id', [$user->id]),
                'maintenance_invoices' => $this->rows('maintenance_invoices', 'maintenance_contract_id', $contractIds),
                'invoices' => $this->rows('invoices', 'user_id', [$user->id]),
                'invoice_items' => $this->rows('invoice_items', 'invoice_id', $invoiceIds),
                'invoice_payments' => $this->rows('invoice_payments', 'invoice_id', $invoiceIds),
                'inventory_items' => $this->rows('inventory_items', 'user_id', [$user->id]),
                'recurring_expenses' => $this->rows('recurring_expenses', 'user_id', [$user->id]),
                'recurring_payments' => $this->rows('recurring_payments', 'recurring_expense_id', DB::table('recurring_expenses')->where('user_id', $user->id)->pluck('id')),
                'finance_entries' => $this->rows('finance_entries', 'user_id', [$user->id]),
            ],
            'logo' => $logo && is_file($logo->getPath()) ? [
                'file_name' => $logo->file_name,
                'mime_type' => $logo->mime_type,
                'content' => base64_encode(file_get_contents($logo->getPath())),
            ] : null,
            'invoice_originals' => $user->invoices()->whereHas('media')->with('media')->get()
                ->map(fn (Invoice $invoice) => [$invoice, $invoice->getFirstMedia('original')])
                ->filter(fn ($pair) => $pair[1] && is_file($pair[1]->getPath()))
                ->map(fn ($pair) => [
                    'invoice_id' => $pair[0]->id,
                    'file_name' => $pair[1]->file_name,
                    'content' => base64_encode(file_get_contents($pair[1]->getPath())),
                ])
                ->values()
                ->all(),
            'receipts' => $user->inventoryItems()->with('media')->get()
                ->map(fn (InventoryItem $item) => [$item, $item->getFirstMedia('receipt')])
                ->filter(fn ($pair) => $pair[1] && is_file($pair[1]->getPath()))
                ->map(fn ($pair) => [
                    'inventory_item_id' => $pair[0]->id,
                    'file_name' => $pair[1]->file_name,
                    'content' => base64_encode(file_get_contents($pair[1]->getPath())),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Number of records per table, e.g. for the preview on the backup page.
     *
     * @param  array<string, mixed>  $backup
     * @return array<string, int>
     */
    public function counts(array $backup): array
    {
        return collect($backup['data'])->map(fn ($rows) => count($rows))->all();
    }

    /**
     * @param  array<string, mixed>  $backup
     * @return array{imported: array<string, int>, skipped_invoices: array<int, string>}
     */
    public function restore(User $user, array $backup, bool $replace): array
    {
        $this->validate($backup);

        // Older backups don't know the inventory and finances yet
        $data = $backup['data'] + ['inventory_items' => [], 'recurring_expenses' => [], 'recurring_payments' => [], 'finance_entries' => []];
        $imported = array_fill_keys(array_keys($data), 0);
        $skippedInvoices = [];

        $inventory = [];
        $restoredInvoices = [];

        DB::transaction(function () use ($user, $data, $replace, &$imported, &$skippedInvoices, &$inventory, &$restoredInvoices) {
            if ($replace) {
                $this->wipe($user);
            }

            // Business profile: one per user. When appending, an existing profile is kept.
            foreach (array_slice($data['business_profiles'], 0, 1) as $row) {
                if (! DB::table('business_profiles')->where('user_id', $user->id)->exists()) {
                    $this->insert('business_profiles', $row, ['user_id' => $user->id]);
                    $imported['business_profiles']++;
                }
            }

            foreach ($data['inventory_items'] as $row) {
                $inventory[$row['id']] = $this->insert('inventory_items', $row, ['user_id' => $user->id]);
                $imported['inventory_items']++;
            }

            // Finances: contracts with their confirmed payments, and the bookings
            $recurring = [];
            foreach ($data['recurring_expenses'] as $row) {
                $recurring[$row['id']] = $this->insert('recurring_expenses', $row, ['user_id' => $user->id]);
                $imported['recurring_expenses']++;
            }

            foreach ($data['recurring_payments'] as $row) {
                if (isset($recurring[$row['recurring_expense_id']])) {
                    $this->insert('recurring_payments', $row, ['recurring_expense_id' => $recurring[$row['recurring_expense_id']]]);
                    $imported['recurring_payments']++;
                }
            }

            foreach ($data['finance_entries'] as $row) {
                $this->insert('finance_entries', $row, ['user_id' => $user->id]);
                $imported['finance_entries']++;
            }

            foreach ($data['catalog_items'] as $row) {
                $this->insert('catalog_items', $row, ['user_id' => $user->id]);
                $imported['catalog_items']++;
            }

            $customers = [];
            foreach ($data['customers'] as $row) {
                $customers[$row['id']] = $this->insert('customers', $row, ['user_id' => $user->id]);
                $imported['customers']++;
            }

            $contracts = [];
            foreach ($data['maintenance_contracts'] as $row) {
                if (! isset($customers[$row['customer_id']])) {
                    continue;
                }

                $contracts[$row['id']] = $this->insert('maintenance_contracts', $row, [
                    'user_id' => $user->id,
                    'customer_id' => $customers[$row['customer_id']],
                ]);
                $imported['maintenance_contracts']++;
            }

            // Invoice numbers are unique: an invoice whose number already exists is skipped
            // and references to it point to the existing invoice instead.
            $invoices = [];
            $newInvoices = [];
            foreach ($data['invoices'] as $row) {
                $existing = DB::table('invoices')->where('user_id', $user->id)->where('number', $row['number'])->value('id');

                if ($existing) {
                    $invoices[$row['id']] = $existing;
                    $skippedInvoices[] = $row['number'];

                    continue;
                }

                $invoices[$row['id']] = $newInvoices[$row['id']] = $this->insert('invoices', $row, [
                    'user_id' => $user->id,
                    'customer_id' => $customers[$row['customer_id'] ?? null] ?? null,
                    'maintenance_contract_id' => $contracts[$row['maintenance_contract_id'] ?? null] ?? null,
                    'cancels_invoice_id' => null,
                ]);
                $imported['invoices']++;
            }

            $restoredInvoices = $newInvoices;

            // Cancellation links, once all invoices exist
            foreach ($data['invoices'] as $row) {
                $cancels = $invoices[$row['cancels_invoice_id'] ?? null] ?? null;

                if (isset($newInvoices[$row['id']]) && $cancels
                    && ! DB::table('invoices')->where('cancels_invoice_id', $cancels)->exists()) {
                    DB::table('invoices')->where('id', $newInvoices[$row['id']])->update(['cancels_invoice_id' => $cancels]);
                }
            }

            foreach (['invoice_items', 'invoice_payments'] as $table) {
                foreach ($data[$table] as $row) {
                    if (isset($newInvoices[$row['invoice_id']])) {
                        $this->insert($table, $row, ['invoice_id' => $newInvoices[$row['invoice_id']]]);
                        $imported[$table]++;
                    }
                }
            }

            foreach ($data['maintenance_invoices'] as $row) {
                if (isset($contracts[$row['maintenance_contract_id']])) {
                    $this->insert('maintenance_invoices', $row, [
                        'maintenance_contract_id' => $contracts[$row['maintenance_contract_id']],
                        'invoice_id' => $invoices[$row['invoice_id'] ?? null] ?? null,
                    ]);
                    $imported['maintenance_invoices']++;
                }
            }
        });

        $this->restoreLogo($user, $backup['logo'] ?? null, $replace);
        $this->restoreReceipts($backup['receipts'] ?? [], $inventory);
        $this->restoreInvoiceOriginals($backup['invoice_originals'] ?? [], $restoredInvoices);

        return ['imported' => $imported, 'skipped_invoices' => $skippedInvoices];
    }

    /**
     * @param  array<string, mixed>  $backup
     */
    public function validate(array $backup): void
    {
        if (($backup['format'] ?? null) !== self::FORMAT || ! is_array($backup['data'] ?? null)) {
            throw new InvalidArgumentException('Die Datei ist kein meSync-Backup.');
        }

        if (($backup['version'] ?? 0) > self::VERSION) {
            throw new InvalidArgumentException('Das Backup stammt aus einer neueren Version von meSync.');
        }

        foreach (['business_profiles', 'catalog_items', 'customers', 'maintenance_contracts', 'maintenance_invoices', 'invoices', 'invoice_items', 'invoice_payments'] as $table) {
            if (! is_array($backup['data'][$table] ?? null)) {
                throw new InvalidArgumentException("Im Backup fehlt der Bereich „{$table}“.");
            }
        }
    }

    /**
     * Delete all business data of the user. Foreign keys cascade to items, payments and billings.
     */
    private function wipe(User $user): void
    {
        // Files first: deleting the rows below would leave them behind
        $user->invoices()->whereHas('media')->with('media')->get()->each(fn (Invoice $invoice) => $invoice->clearMediaCollection('original'));

        DB::table('invoices')->where('user_id', $user->id)->update(['cancels_invoice_id' => null]);
        DB::table('invoices')->where('user_id', $user->id)->delete();
        DB::table('maintenance_contracts')->where('user_id', $user->id)->delete();
        DB::table('customers')->where('user_id', $user->id)->delete();
        DB::table('catalog_items')->where('user_id', $user->id)->delete();
        DB::table('recurring_expenses')->where('user_id', $user->id)->delete();
        DB::table('finance_entries')->where('user_id', $user->id)->delete();

        // Through the models, so the receipt files are deleted too
        $user->inventoryItems()->get()->each->delete();
        DB::table('business_profiles')->where('user_id', $user->id)->delete();
    }

    /**
     * Insert a backup row with a new ID. Columns unknown to the current schema are ignored,
     * columns missing in older backups fall back to their database default.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $overrides
     */
    private function insert(string $table, array $row, array $overrides): int
    {
        static $columns = [];
        $columns[$table] ??= array_flip(Schema::getColumnListing($table));

        $values = array_intersect_key([...$row, ...$overrides], $columns[$table]);
        unset($values['id']);

        return DB::table($table)->insertGetId($values);
    }

    /**
     * @param  iterable<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $table, string $column, iterable $ids): array
    {
        return DB::table($table)
            ->whereIn($column, collect($ids)->all())
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $receipts
     * @param  array<int, int>  $inventory  old ID => new ID
     */
    private function restoreReceipts(array $receipts, array $inventory): void
    {
        foreach ($receipts as $receipt) {
            $item = InventoryItem::find($inventory[$receipt['inventory_item_id'] ?? null] ?? null);

            if (! $item) {
                continue;
            }

            try {
                $item->addMediaFromBase64($receipt['content'])
                    ->usingFileName(basename($receipt['file_name'] ?? 'beleg.pdf'))
                    ->toMediaCollection('receipt');
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $originals
     * @param  array<int, int>  $invoices  old ID => new ID (only newly created invoices)
     */
    private function restoreInvoiceOriginals(array $originals, array $invoices): void
    {
        foreach ($originals as $original) {
            $invoice = Invoice::find($invoices[$original['invoice_id'] ?? null] ?? null);

            if (! $invoice) {
                continue;
            }

            try {
                $invoice->addMediaFromBase64($original['content'])
                    ->usingFileName(basename($original['file_name'] ?? 'rechnung.pdf'))
                    ->toMediaCollection('original');
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @param  array<string, string>|null  $logo
     */
    private function restoreLogo(User $user, ?array $logo, bool $replace): void
    {
        if ($replace && ! $logo) {
            $user->clearMediaCollection('logo');

            return;
        }

        if (! $logo || (! $replace && $user->getFirstMedia('logo'))) {
            return;
        }

        // The data is already restored at this point, a broken logo must not fail the whole restore
        try {
            $user->addMediaFromBase64($logo['content'])
                ->usingFileName(basename($logo['file_name'] ?? 'logo.png'))
                ->toMediaCollection('logo');
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
