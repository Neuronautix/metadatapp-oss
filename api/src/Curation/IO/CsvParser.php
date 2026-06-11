<?php

declare(strict_types=1);

namespace App\Curation\IO;

final class CsvParser
{
    public function parse(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (!$headers) {
            fclose($handle);

            return ['headers' => [], 'rows' => []];
        }

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $data[$index] ?? null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }
}
