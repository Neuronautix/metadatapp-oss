<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\ExcelAnalysisController;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class ExcelAnalysisControllerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/excel_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*') ?: []);
        rmdir($this->tempDir);
    }

    private function buildSpreadsheet(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $col => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . '1', $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $col => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . ($rowIndex + 2), $value);
            }
        }

        $path = $this->tempDir . '/test_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function makeRequest(string $filePath, string $originalName = 'test.xlsx'): Request
    {
        $uploadedFile = new UploadedFile(
            $filePath,
            $originalName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $request = new Request();
        $request->files->set('file', $uploadedFile);

        return $request;
    }

    #[Test]
    public function analyzeReturnsBadRequestWhenNoFileProvided(): void
    {
        $controller = new ExcelAnalysisController();
        $response = $controller->analyze(new Request());

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    #[Test]
    public function analyzeRejectsDisallowedFileExtension(): void
    {
        $csvPath = $this->tempDir . '/test.csv';
        file_put_contents($csvPath, "col1,col2\nval1,val2\n");

        $uploadedFile = new UploadedFile($csvPath, 'test.txt', 'text/plain', null, true);
        $request = new Request();
        $request->files->set('file', $uploadedFile);

        $response = (new ExcelAnalysisController())->analyze($request);

        $this->assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    #[Test]
    public function analyzeReturnsFullReportShapeForValidXlsxFile(): void
    {
        $filePath = $this->buildSpreadsheet(
            ['Name', 'Age', 'Score'],
            [['Alice', 30, 95.5], ['Bob', 25, 87.0], ['Charlie', 40, 92.0]],
        );

        $response = (new ExcelAnalysisController())->analyze($this->makeRequest($filePath));

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame(3, $data['rowCount']);
        $this->assertSame(3, $data['columnCount']);
        $this->assertSame(['Name', 'Age', 'Score'], $data['headers']);
        $this->assertArrayHasKey('detectedTypes', $data);
        $this->assertArrayHasKey('missingValueStats', $data);
        $this->assertCount(3, $data['previewRows']);
    }

    #[Test]
    public function analyzeCountsMissingValues(): void
    {
        $filePath = $this->buildSpreadsheet(
            ['Label', 'Value'],
            [['A', 1], ['', 2], ['C', '']],
        );

        $response = (new ExcelAnalysisController())->analyze($this->makeRequest($filePath));

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame(1, $data['missingValueStats']['Label']);
        $this->assertSame(1, $data['missingValueStats']['Value']);
    }

    #[Test]
    public function analyzeCapPreviewRowsAtTen(): void
    {
        $rows = array_map(static fn (int $i) => [$i], range(1, 15));
        $filePath = $this->buildSpreadsheet(['Id'], $rows);

        $response = (new ExcelAnalysisController())->analyze($this->makeRequest($filePath));

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame(15, $data['rowCount']);
        $this->assertCount(10, $data['previewRows']);
    }
}
