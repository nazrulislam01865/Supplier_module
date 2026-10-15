<?php
namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class SupplierFileSecurity
{
    public function assertClean(UploadedFile $file): void
    {
        $scan = new Process([config('supplier.file_scan_command', 'clamscan'), '--no-summary', $file->getRealPath()]);
        try {
            $scan->setTimeout(40);
            $scan->run();
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => 'Upload security scanning is temporarily unavailable.']);
        }
        if (!$scan->isSuccessful()) {
            throw ValidationException::withMessages(['file' => 'File rejected by security scanning.']);
        }
    }
}
