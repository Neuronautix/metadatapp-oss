<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

class CageSearchQueryDto
{
    public function __construct(
        public ?string $barcode = null,
        public ?string $tag = null,
        public ?string $status = null,
        public int $limit = 100,
        public int $page = 1,

        public array $additional = [],
    ) {
    }

    public function toArray(): array
    {
        $query = [
            'pageSize' => $this->limit,
            'pageIndex' => $this->page,
        ];
        if ($this->barcode) {
            $query['barcode'] = $this->barcode;
        }
        if ($this->tag) {
            $query['tag'] = $this->tag;
        }
        if ($this->status) {
            $query['status'] = $this->status;
        }
        foreach ($this->additional as $k => $v) {
            $query[$k] = $v;
        }

        return $query;
    }
}
