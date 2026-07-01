<?php

declare(strict_types=1);

namespace App\Import\Nwb;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class NwbImportService
{
    public function __construct(
        private readonly string $nwbToolsDir,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(UploadedFile $file): array
    {
        $outputPath = $this->temporaryPath('metadatapp-nwb-import-', '.json');

        try {
            $process = new Process(['python3', $this->nwbToolsDir . '/import_nwb.py', $file->getPathname(), $outputPath]);
            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'NWB import failed.');
            }

            $payload = json_decode((string) file_get_contents($outputPath), true, 512, \JSON_THROW_ON_ERROR);

            return \is_array($payload) ? $payload : [];
        } finally {
            @unlink($outputPath);
        }
    }

    private function temporaryPath(string $prefix, string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if (false === $path) {
            throw new \RuntimeException('Unable to allocate temporary file.');
        }

        $target = $path . $suffix;
        rename($path, $target);

        return $target;
    }
}
