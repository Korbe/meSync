<?php

namespace App\Http\Controllers;

use App\Services\Backup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class BackupController extends Controller
{
    public function __construct(private Backup $backup)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Backup/Index', [
            'counts' => [
                'customers' => $user->customers()->count(),
                'maintenance_contracts' => $user->maintenanceContracts()->count(),
                'invoices' => $user->invoices()->count(),
                'catalog_items' => $user->catalogItems()->count(),
                'inventory_items' => $user->inventoryItems()->count(),
                'finance' => $user->recurringExpenses()->count() + $user->financeEntries()->count(),
            ],
        ]);
    }

    /**
     * The whole backup as one JSON file. Nothing is stored on the server.
     */
    public function download(Request $request)
    {
        $json = json_encode($this->backup->export($request->user()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return response()->streamDownload(fn () => print($json), 'mesync-backup_'.now()->format('Y-m-d_H-i').'.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:102400'],
            'mode' => ['required', 'in:append,replace'],
        ], [
            'file.required' => 'Bitte wähle eine Backup-Datei aus.',
            'file.max' => 'Die Datei darf maximal 100 MB groß sein.',
        ]);

        $backup = json_decode(file_get_contents($request->file('file')->getRealPath()), true);

        try {
            if (! is_array($backup)) {
                throw new InvalidArgumentException('Die Datei ist kein gültiges JSON.');
            }

            $result = $this->backup->restore($request->user(), $backup, $request->input('mode') === 'replace');
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $imported = $result['imported'];
        $message = $request->input('mode') === 'replace' ? 'Backup wurde wiederhergestellt' : 'Backup wurde angehängt';
        $message .= ": {$imported['customers']} Kunden, {$imported['maintenance_contracts']} Verträge, {$imported['invoices']} Rechnungen, {$imported['catalog_items']} Katalogartikel, {$imported['inventory_items']} Inventargegenstände, ".($imported['recurring_expenses'] + $imported['finance_entries']).' Finanzeinträge.';

        if ($skipped = count($result['skipped_invoices'])) {
            $message .= " {$skipped} Rechnung(en) mit bereits vorhandener Nummer übersprungen.";
        }

        return to_route('backup.index')->with('success', $message);
    }
}
