<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * マイ勤怠レポート（過去6ヶ月の勤怠統計）の集計
 *
 * 集計の対象は退勤済みの勤怠のみ。労働時間 = 退勤 − 出勤 − 休憩合計
 */
class AttendanceReport
{
    /** 集計する月数（当月を含む） */
    public const MONTHS = 6;

    /** 所定労働時間（分）。これを超えた分が残業 */
    public const REGULAR_MINUTES = 8 * 60;

    /** 長時間労働とみなす労働時間（分）。これを「超えた」日を数える */
    public const LONG_WORK_MINUTES = 10 * 60;

    /** 始業時刻。これより後の出勤を遅刻とする */
    public const START_TIME = '09:00:00';

    /** 終業時刻。これより前の退勤を早退とする */
    public const END_TIME = '18:00:00';

    /**
     * @param  Collection<int, AttendanceRecord>  $records  集計期間内の退勤済みの勤怠（breaks を eager load 済み）
     */
    public function __construct(
        private readonly Collection $records,
        private readonly CarbonInterface $today,
    ) {}

    /**
     * ユーザーの集計期間（5ヶ月前の月初〜当月末）の退勤済みの勤怠を取得してレポートを作る
     */
    public static function forUser(User $user, CarbonInterface $today): self
    {
        $records = $user->attendanceRecords()
            ->with('breaks')
            ->whereNotNull('clock_out')
            ->whereBetween('date', [self::periodStart($today)->toDateString(), $today->copy()->endOfMonth()->toDateString()])
            ->get();

        return new self($records, $today);
    }

    /**
     * 集計期間の初日（5ヶ月前の月初）
     */
    public static function periodStart(CarbonInterface $today): CarbonInterface
    {
        return $today->copy()->startOfMonth()->subMonthsNoOverflow(self::MONTHS - 1);
    }

    /**
     * 基本サマリー：総労働時間・総残業時間・1日あたりの平均労働時間（いずれも分）
     *
     * @return array{total_work_minutes: int, total_overtime_minutes: int, avg_work_minutes: int}
     */
    public function summary(): array
    {
        $workMinutes = $this->workMinutes($this->records);
        $total = $workMinutes->sum();

        return [
            'total_work_minutes' => $total,
            'total_overtime_minutes' => $this->overtimeMinutes($workMinutes),
            'avg_work_minutes' => $workMinutes->isEmpty() ? 0 : intdiv($total, $workMinutes->count()),
        ];
    }

    /**
     * 月次推移：過去6ヶ月の月ごとの労働時間と残業時間（分）。データがない月は 0
     *
     * @return array<int, array{month: string, work_minutes: int, overtime_minutes: int}>
     */
    public function monthlyTrend(): array
    {
        $recordsByMonth = $this->records->groupBy(fn (AttendanceRecord $record) => $record->date->format('Y-m'));

        return collect(range(self::MONTHS - 1, 0))
            ->map(fn (int $monthsAgo) => $this->today->copy()->startOfMonth()->subMonthsNoOverflow($monthsAgo))
            ->map(function (CarbonInterface $month) use ($recordsByMonth) {
                $workMinutes = $this->workMinutes($recordsByMonth->get($month->format('Y-m'), collect()));

                return [
                    'month' => $month->format('Y/m'),
                    'work_minutes' => $workMinutes->sum(),
                    'overtime_minutes' => $this->overtimeMinutes($workMinutes),
                ];
            })
            ->all();
    }

    /**
     * 当月の異常検知：遅刻回数・早退回数・長時間労働日数
     *
     * @return array{late_count: int, early_leave_count: int, long_work_count: int}
     */
    public function anomalies(): array
    {
        $currentMonth = $this->records->filter(fn (AttendanceRecord $record) => $record->date->isSameMonth($this->today));

        return [
            'late_count' => $currentMonth->filter(fn (AttendanceRecord $record) => $record->clock_in > self::START_TIME)->count(),
            'early_leave_count' => $currentMonth->filter(fn (AttendanceRecord $record) => $record->clock_out < self::END_TIME)->count(),
            'long_work_count' => $this->workMinutes($currentMonth)->filter(fn (int $minutes) => $minutes > self::LONG_WORK_MINUTES)->count(),
        ];
    }

    /**
     * 勤怠ごとの労働時間（分）
     *
     * @param  Collection<int, AttendanceRecord>  $records
     * @return Collection<int, int>
     */
    private function workMinutes(Collection $records): Collection
    {
        return $records->map(fn (AttendanceRecord $record) => $record->workMinutes())->values();
    }

    /**
     * 残業時間の合計（分）。1日ごとに所定労働時間を超えた分を足す
     *
     * @param  Collection<int, int>  $workMinutes
     */
    private function overtimeMinutes(Collection $workMinutes): int
    {
        return $workMinutes->sum(fn (int $minutes) => max(0, $minutes - self::REGULAR_MINUTES));
    }
}
