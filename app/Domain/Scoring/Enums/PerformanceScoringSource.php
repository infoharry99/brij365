<?php

namespace App\Domain\Scoring\Enums;

enum PerformanceScoringSource: string
{
    case KpiAchievement = 'kpi_achievement';
    case KraAchievement = 'kra_achievement';
    case Competencies = 'competencies';
    case Behaviour = 'behaviour';
    case Attendance = 'attendance';
    case SelfReview = 'self_review';
    case ManagerReview = 'manager_review';
    case HrCalibration = 'hr_calibration';

    public function label(): string
    {
        return match ($this) {
            self::KpiAchievement => 'KPI achievement',
            self::KraAchievement => 'KRA achievement',
            self::Competencies => 'Competencies',
            self::Behaviour => 'Behaviour',
            self::Attendance => 'Finalized attendance',
            self::SelfReview => 'Self review',
            self::ManagerReview => 'Manager review',
            self::HrCalibration => 'HR calibration',
        };
    }
}
