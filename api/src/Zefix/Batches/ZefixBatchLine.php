<?php

declare(strict_types=1);

namespace App\Zefix\Batches;

final readonly class ZefixBatchLine
{
    public function __construct(
        public string $lineID,
        public string $name,
        public string $genotype,
        public string $origin,
        public string $description,
        public string $creationDate,
        public string $responsibleScientist,
    ) {
    }
}
