<?php

namespace App\Enums;

/**
 * Derived from submissions, never stored.
 *
 *  VERIFIED – one submission is VERIFIED
 *  PENDING  – none is VERIFIED, at least one is PENDING
 *  LOCKED   – everything else
 */
enum MilestoneState: string
{
    case Locked = 'locked';
    case Pending = 'pending';
    case Verified = 'verified';

    public function label(): string
    {
        return __("milestones.state.{$this->value}");
    }
}
