<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AttendanceRecord extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
    ];

    /**
     * 勤怠のユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 勤怠の休憩
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    /**
     * 勤怠に対する修正申請
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * 承認待ちの修正申請（申請の休憩を含む）。なければ null
     */
    public function pendingApplication(): ?Application
    {
        return $this->applications()
            ->with('proposalBreaks')
            ->where('approval_status', Application::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    /**
     * 終了していない（休憩中の）休憩。休憩中でなければ null
     */
    public function openBreak(): ?AttendanceBreak
    {
        return $this->breaks->first(fn (AttendanceBreak $break) => is_null($break->break_out));
    }

    /**
     * 休憩時間の合計（分）。終了していない休憩は含めない
     */
    public function breakMinutes(): int
    {
        return $this->breaks
            ->filter(fn (AttendanceBreak $break) => ! is_null($break->break_out))
            ->sum(fn (AttendanceBreak $break) => Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out)));
    }

    /**
     * 労働時間（分）。退勤していない場合は null
     */
    public function workMinutes(): ?int
    {
        if (is_null($this->clock_out)) {
            return null;
        }

        return Carbon::parse($this->clock_in)->diffInMinutes(Carbon::parse($this->clock_out)) - $this->breakMinutes();
    }

    /**
     * 休憩時間の合計（例：1:00）
     */
    protected function totalBreakTime(): Attribute
    {
        return Attribute::get(fn (): string => self::formatMinutes($this->breakMinutes()));
    }

    /**
     * 勤務時間の合計（例：8:00）。退勤していない場合は null
     */
    protected function totalTime(): Attribute
    {
        return Attribute::get(function (): ?string {
            $workMinutes = $this->workMinutes();

            return is_null($workMinutes) ? null : self::formatMinutes($workMinutes);
        });
    }

    /**
     * 分を「時:分」形式の文字列にする
     */
    private static function formatMinutes(int $minutes): string
    {
        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
