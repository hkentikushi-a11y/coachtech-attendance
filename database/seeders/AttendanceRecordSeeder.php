<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceRecordSeeder extends Seeder
{
    /**
     * 一般ユーザーの勤怠と休憩を作成する
     */
    public function run(): void
    {
        $this->seedReportData(User::where('email', 'user1@example.com')->firstOrFail());
        $this->seedDailyData(User::where('email', 'user2@example.com')->firstOrFail());
    }

    /**
     * マイ勤怠レポート検証用の意図的なデータ（user1）
     *
     * 過去5ヶ月：各月の最初の平日15日を 9:00-18:00
     * 当月：今日を除いた平日17日を「通常10 / 残業3 / 遅刻2 / 早退1 / 長時間1」
     * 今日は採点時に打刻を試せるよう空けておく
     */
    private function seedReportData(User $user): void
    {
        $today = today();

        collect(range(5, 1))
            ->flatMap(fn (int $monthsAgo) => $this->weekdaysOfMonth($today->copy()->startOfMonth()->subMonthsNoOverflow($monthsAgo))->take(15))
            ->each(fn (CarbonInterface $date) => $this->createRecord($user, $date, '09:00', '18:00'));

        $currentMonthPatterns = [
            ...array_fill(0, 10, ['09:00', '18:00']),
            ...array_fill(0, 3, ['09:00', '20:00']),
            ...array_fill(0, 2, ['09:30', '18:00']),
            ['09:00', '17:00'],
            ['08:00', '21:00'],
        ];

        $this->weekdaysOfMonth($today->copy()->startOfMonth())
            ->reject(fn (CarbonInterface $date) => $date->isSameDay($today))
            ->take(count($currentMonthPatterns))
            ->values()
            ->each(fn (CarbonInterface $date, int $index) => $this->createRecord($user, $date, ...$currentMonthPatterns[$index]));
    }

    /**
     * 実運用に近い日々のデータ（user2）
     *
     * 3ヶ月前の月初から昨日までの平日。出退勤時刻は日付から決まる値で少しずつずらす
     */
    private function seedDailyData(User $user): void
    {
        $start = today()->startOfMonth()->subMonthsNoOverflow(3);

        collect(CarbonPeriod::create($start, today()->subDay()))
            ->filter(fn (CarbonInterface $date) => $date->isWeekday())
            ->each(function (CarbonInterface $date) use ($user) {
                $clockIn = Carbon::parse('08:50')->addMinutes($date->day % 21)->format('H:i');
                $clockOut = Carbon::parse('17:55')->addMinutes(($date->day * 7) % 66)->format('H:i');
                $breaks = $date->day % 5 === 0
                    ? [['12:00', '13:00'], ['15:00', '15:15']]
                    : [['12:00', '13:00']];

                $this->createRecord($user, $date, $clockIn, $clockOut, $breaks);
            });
    }

    /**
     * 指定した月の平日を返す
     *
     * @return Collection<int, CarbonInterface>
     */
    private function weekdaysOfMonth(CarbonInterface $firstDay): Collection
    {
        return collect(CarbonPeriod::create($firstDay, $firstDay->copy()->endOfMonth()))
            ->filter(fn (CarbonInterface $date) => $date->isWeekday())
            ->values();
    }

    /**
     * 勤怠と休憩を作成する
     *
     * @param  array<int, array{0: string, 1: string}>  $breaks
     */
    private function createRecord(User $user, CarbonInterface $date, string $clockIn, string $clockOut, array $breaks = [['12:00', '13:00']]): AttendanceRecord
    {
        $record = $user->attendanceRecords()->create([
            'date' => $date->toDateString(),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);

        $record->breaks()->createMany(
            collect($breaks)->map(fn (array $break) => ['break_in' => $break[0], 'break_out' => $break[1]])->all()
        );

        return $record;
    }
}
