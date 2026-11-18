<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, array<int|string, mixed>>  $rows
     */
    public function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $filename = $this->safeFilename($filename, 'csv');

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($out, array_values($row), ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function pdf(string $view, array $data, string $filename): Response
    {
        $filename = $this->safeFilename($filename, 'pdf');

        $data['salon_name'] = $data['salon_name'] ?? $this->settings->salonName();
        $data['currency'] = $data['currency'] ?? $this->settings->currency();

        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    private function safeFilename(string $name, string $ext): string
    {
        $base = preg_replace('/[^A-Za-z0-9_\-]+/', '_', pathinfo($name, PATHINFO_FILENAME)) ?: 'relatorio';

        return $base.'.'.$ext;
    }
}
