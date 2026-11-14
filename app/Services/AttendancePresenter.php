<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\ProposalBreak;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 提供 Blade に渡す勤怠の表示用データを組み立てる（一般ユーザー・管理者の画面で共通）
 */
class AttendancePresenter
{
    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /**
     * 曜日（日〜土）
     */
    public static function weekday(CarbonInterface $date): string
    {
        return self::WEEKDAYS[$date->dayOfWeek];
    }

    /**
     * クエリの日付（YYYY-MM-DD）を日付にする。指定がない・不正な場合は今日
     */
    public static function dayFromQuery(?string $value): Carbon
    {
        if (blank($value)) {
            return today();
        }

        return rescue(fn () => Carbon::createFromFormat('!Y-m-d', $value), today(), false);
    }

    /**
     * クエリの年月（YYYY-MM）を月初の日付にする。指定がない・不正な場合は今月
     */
    public static function monthFromQuery(?string $value): Carbon
    {
        if (blank($value)) {
            return today()->startOfMonth();
        }

        return rescue(fn () => Carbon::createFromFormat('!Y-m', $value)->startOfMonth(), today()->startOfMonth(), false);
    }

    /**
     * 月次勤怠一覧の行（その月の全日分。勤怠がない日は空欄）
     *
     * @param  Collection<int, AttendanceRecord>  $records  breaks を eager load した、その月の勤怠
     * @return array<int, array<string, int|string|null>>
     */
    public static function monthlyRows(Collection $records, CarbonInterface $month): array
    {
        $recordsByDate = $records->keyBy(fn (AttendanceRecord $record) => $record->date->toDateString());

        return collect(CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth()))
            ->map(function (CarbonInterface $day) use ($recordsByDate) {
                $record = $recordsByDate->get($day->toDateString());

                return [
                    'id' => $record?->id,
                    'date' => $day->format('m/d') . '(' . self::weekday($day) . ')',
                    'clock_in' => self::time($record?->clock_in),
                    'clock_out' => self::time($record?->clock_out),
                    'total_break_time' => $record?->total_break_time ?? '',
                    'total_time' => $record?->total_time ?? '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * 勤怠詳細画面のデータ。承認待ちの申請がある場合は申請内容を表示する
     *
     * @return array<string, mixed>
     */
    public static function detail(AttendanceRecord $record, ?Application $pendingApplication = null): array
    {
        $breaks = $pendingApplication ? $pendingApplication->proposalBreaks : $record->breaks;

        return [
            'id' => $record->id,
            'year' => $record->date->format('Y年'),
            'date' => $record->date->format('n月j日'),
            'clock_in' => self::time($pendingApplication ? $pendingApplication->new_clock_in : $record->clock_in),
            'clock_out' => self::time($pendingApplication ? $pendingApplication->new_clock_out : $record->clock_out),
            'breaks' => $breaks
                ->map(fn (AttendanceBreak|ProposalBreak $break) => [
                    'break_in' => self::time($break->break_in),
                    'break_out' => self::time($break->break_out),
                ])
                ->values()
                ->all(),
            'comment' => $pendingApplication ? $pendingApplication->comment : $record->comment,
            'application' => $pendingApplication,
        ];
    }

    /**
     * バリデーションエラーで戻ってきたときは、入力した値を表示する
     *
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $old  直前の入力（session の old input）
     * @return array<string, mixed>
     */
    public static function withOldInput(array $detail, array $old): array
    {
        if ($old === []) {
            return $detail;
        }

        $breakIns = $old['new_break_in'] ?? [];
        $breakOuts = $old['new_break_out'] ?? [];

        // エラーの表示位置（new_break_in.{行番号}）がずれないよう、行番号は保ったまま末尾の空行だけを除く
        $breaks = collect(range(0, max(array_keys($breakIns + $breakOuts + [0 => null]))))
            ->map(fn (int $index) => ['break_in' => $breakIns[$index] ?? '', 'break_out' => $breakOuts[$index] ?? '']);

        while ($breaks->isNotEmpty() && blank($breaks->last()['break_in']) && blank($breaks->last()['break_out'])) {
            $breaks->pop();
        }

        return [
            ...$detail,
            'clock_in' => $old['new_clock_in'] ?? '',
            'clock_out' => $old['new_clock_out'] ?? '',
            'breaks' => $breaks->all(),
            'comment' => $old['comment'] ?? '',
        ];
    }

    /**
     * 時刻（HH:MM:SS）を HH:MM にする。null は空文字
     */
    public static function time(?string $time): string
    {
        return is_null($time) ? '' : substr($time, 0, 5);
    }
}
