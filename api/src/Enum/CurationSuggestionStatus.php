<?php

declare(strict_types=1);

namespace App\Enum;

enum CurationSuggestionStatus: string
{
    case PendingReview = 'pending_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
