<?php

namespace App\Services;

use App\Models\DebtCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DebtCaseInvoicePdfService
{
    public const DISK = 'local';

    public const MAX_KB = 5120;

    public function hasPdf(DebtCase $case): bool
    {
        $path = trim((string) ($case->invoice_pdf_path ?? ''));

        return $path !== '' && Storage::disk(self::DISK)->exists($path);
    }

    public function store(DebtCase $case, UploadedFile $file): DebtCase
    {
        $this->deleteStoredFile($case);

        $dir = 'debt-cases/'.$case->id;
        $storedPath = $file->storeAs($dir, 'invoice.pdf', self::DISK);

        $case->invoice_pdf_path = $storedPath;
        $case->invoice_pdf_original_name = $file->getClientOriginalName() ?: 'faktura.pdf';
        $case->invoice_pdf_uploaded_at = now();
        $case->invoice_pdf_uploaded_by = Auth::id();
        $case->save();

        return $case;
    }

    public function delete(DebtCase $case): DebtCase
    {
        $this->deleteStoredFile($case);

        $case->invoice_pdf_path = null;
        $case->invoice_pdf_original_name = null;
        $case->invoice_pdf_uploaded_at = null;
        $case->invoice_pdf_uploaded_by = null;
        $case->save();

        return $case;
    }

    public function absolutePath(DebtCase $case): ?string
    {
        if (! $this->hasPdf($case)) {
            return null;
        }

        return Storage::disk(self::DISK)->path((string) $case->invoice_pdf_path);
    }

    public function downloadName(DebtCase $case): string
    {
        $name = trim((string) ($case->invoice_pdf_original_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $invoice = trim((string) ($case->invoice_number ?: $case->formOrder?->invoice_number ?: ''));
        if ($invoice !== '') {
            return 'FV-'.preg_replace('/[^\w.\-]+/u', '_', $invoice).'.pdf';
        }

        return 'faktura-sprawa-'.$case->id.'.pdf';
    }

    private function deleteStoredFile(DebtCase $case): void
    {
        $path = trim((string) ($case->invoice_pdf_path ?? ''));
        if ($path !== '' && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
