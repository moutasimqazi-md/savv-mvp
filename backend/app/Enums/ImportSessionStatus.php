<?php

namespace Savv\Enums;

enum ImportSessionStatus: string
{
    case Requested = 'requested';
    case Starting = 'starting';
    case Ready = 'ready';
    case AwaitingLogin = 'awaiting_login';
    case ReadyToScan = 'ready_to_scan';
    case Scanning = 'scanning';
    case PreviewReady = 'preview_ready';
    case Importing = 'importing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Failed = 'failed';
    case Terminating = 'terminating';
    case Terminated = 'terminated';

    /** Statuses that count as "still running" for the one-active-session-per-user rule. */
    public static function activeStatuses(): array
    {
        return [
            self::Requested, self::Starting, self::Ready, self::AwaitingLogin,
            self::ReadyToScan, self::Scanning, self::PreviewReady, self::Importing,
        ];
    }

    /** Terminal statuses after which the runner process must not exist. */
    public static function terminalStatuses(): array
    {
        return [self::Completed, self::Cancelled, self::Expired, self::Failed, self::Terminated];
    }

    public function isActive(): bool
    {
        return in_array($this, self::activeStatuses(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminalStatuses(), true);
    }
}
