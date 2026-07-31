<?php

namespace App\Domain\Scoring\Support;

final class LogicCenterPermissions
{
    public const VIEW = 'scoring.view';

    public const PERFORMANCE_MANAGE = 'scoring.performance.manage';

    public const PERFORMANCE_APPROVE = 'scoring.performance.approve';

    public const PERFORMANCE_OVERRIDE_REQUEST = 'scoring.performance.override.request';

    public const PERFORMANCE_OVERRIDE_APPROVE = 'scoring.performance.override.approve';

    public const STATUTORY_MANAGE = 'scoring.statutory.manage';

    public const STATUTORY_VERIFY = 'scoring.statutory.verify';

    public const STATUTORY_APPROVE = 'scoring.statutory.approve';

    public const STATUTORY_SIMULATE = 'scoring.statutory.simulate';

    public const ROSTER_MANAGE = 'attendance.roster.manage';

    public const ROSTER_PUBLISH = 'attendance.roster.publish';

    public const ROSTER_REOPEN = 'attendance.roster.reopen';

    public const SWAP_REQUEST = 'attendance.swap.request';

    public const SWAP_APPROVE = 'attendance.swap.approve';

    public const ATTENDANCE_FINALIZE = 'attendance.finalize';

    public const ATTENDANCE_REOPEN = 'attendance.reopen';

    public const AUDIT_VIEW = 'scoring.audit.view';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW,
            self::PERFORMANCE_MANAGE,
            self::PERFORMANCE_APPROVE,
            self::PERFORMANCE_OVERRIDE_REQUEST,
            self::PERFORMANCE_OVERRIDE_APPROVE,
            self::STATUTORY_MANAGE,
            self::STATUTORY_VERIFY,
            self::STATUTORY_APPROVE,
            self::STATUTORY_SIMULATE,
            self::ROSTER_MANAGE,
            self::ROSTER_PUBLISH,
            self::ROSTER_REOPEN,
            self::SWAP_REQUEST,
            self::SWAP_APPROVE,
            self::ATTENDANCE_FINALIZE,
            self::ATTENDANCE_REOPEN,
            self::AUDIT_VIEW,
        ];
    }

    /**
     * Permissions that make the administrative Logic Center navigation useful.
     * Employee-only swap access remains in Employee Self Service and must not
     * expose the governed formula workspace.
     *
     * @return list<string>
     */
    public static function navigation(): array
    {
        return array_values(array_diff(self::all(), [self::SWAP_REQUEST]));
    }
}
