<?php

namespace App\Enums;

/** What a screenshot shows (SUB-2). */
enum EvidenceKind: string
{
    case Stats = 'stats';
    case Ancestry = 'ancestry';
    case Other = 'other';

    public function label(): string
    {
        return __("submissions.kind.{$this->value}");
    }
}
