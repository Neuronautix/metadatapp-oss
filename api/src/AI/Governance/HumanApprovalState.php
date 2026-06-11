<?php

declare(strict_types=1);

namespace App\AI\Governance;

enum HumanApprovalState: string
{
    case Required = 'required';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Edited = 'edited';
}
