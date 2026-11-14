<?php

namespace App\Enums;

/**
 * Lifecycle of a claim (see the state diagram in docs/ZADANI.md).
 *
 *  PENDING → VERIFIED | REJECTED | WITHDRAWN | SUPERSEDED
 *  VERIFIED → REVOKED
 *  SUPERSEDED → PENDING (the verification that closed it was revoked)
 *  REJECTED | REVOKED → PENDING (admin reopens, OPR-3)
 */
enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Superseded = 'superseded';
    case Revoked = 'revoked';

    public function label(): string
    {
        return __("submissions.status.{$this->value}");
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Verified, self::Rejected, self::Withdrawn, self::Superseded],
            self::Verified => [self::Revoked],
            self::Superseded, self::Rejected, self::Revoked => [self::Pending],
            self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
