<?php

declare(strict_types=1);

namespace App\Controller\Api;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ExcelAnalysisController extends AbstractController
{
    private const array ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'ods'];
    private const int ANALYSIS_SAMPLE_ROWS = 100;
    private const int PREVIEW_ROWS = 10;

    #[Route('/excel/analyze', name: 'api_excel_analyze', methods: ['POST'])]
    public function analyze(Request $request): JsonResponse
    {
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile instanceof UploadedFile) {
            return new JsonResponse(['message' => 'No file uploaded.'], Response::HTTP_BAD_REQUEST);
        }

        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return new JsonResponse(
                ['message' => \sprintf('Unsupported file type. Allowed: %s.', implode(', ', self::ALLOWED_EXTENSIONS))],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $spreadsheet = IOFactory::load($uploadedFile->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = $worksheet->getHighestDataRow();
            $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());

            // Read headers from first row; skip columns with empty header
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; ++$col) {
                $value = (string) $this->getCell($worksheet, $col, 1)->getValue();
                if ('' !== $value) {
                    $headers[$col] = $value;
                }
            }

            $columnCount = \count($headers);
            $rowCount = max(0, $highestRow - 1);

            $previewRows = [];
            $typesFound = array_fill_keys(array_values($headers), []);
            $missingValueStats = array_fill_keys(array_values($headers), 0);

            $limit = min($highestRow, self::ANALYSIS_SAMPLE_ROWS + 1);
            $sampledRowCount = max(0, $limit - 1);
            for ($row = 2; $row <= $limit; ++$row) {
                $rowData = [];
                foreach ($headers as $col => $header) {
                    $cell = $this->getCell($worksheet, $col, $row);
                    $value = $cell->getFormattedValue();
                    $rowData[$header] = $value;

                    if ('' === $value) {
                        ++$missingValueStats[$header];
                    } else {
                        $typesFound[$header][$this->detectCellType($cell)] = true;
                    }
                }

                if ($row <= self::PREVIEW_ROWS + 1) {
                    $previewRows[] = $rowData;
                }
            }

            $detectedTypes = [];
            foreach (array_values($headers) as $header) {
                $types = array_keys($typesFound[$header]);
                $detectedTypes[$header] = match (\count($types)) {
                    0 => 'string',
                    1 => $types[0],
                    default => 'mixed',
                };
            }

            return new JsonResponse([
                'rowCount' => $rowCount,
                'sampledRowCount' => $sampledRowCount,
                'columnCount' => $columnCount,
                'headers' => array_values($headers),
                'detectedTypes' => $detectedTypes,
                'missingValueStats' => $missingValueStats,
                'previewRows' => $previewRows,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'message' => 'Unable to read Excel file.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function detectCellType(Cell $cell): string
    {
        $dataType = $cell->getDataType();

        if (DataType::TYPE_NUMERIC === $dataType) {
            $formatCode = $cell->getStyle()->getNumberFormat()->getFormatCode();
            if (\is_string($formatCode) && Date::isDateTimeFormatCode($formatCode)) {
                return 'date';
            }

            return 'number';
        }

        if (DataType::TYPE_BOOL === $dataType) {
            return 'boolean';
        }

        return 'string';
    }

    private function getCell(Worksheet $worksheet, int $column, int $row): Cell
    {
        return $worksheet->getCell(Coordinate::stringFromColumnIndex($column) . $row);
    }
}
