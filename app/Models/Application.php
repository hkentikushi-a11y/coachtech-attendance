<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory;

    public const STATUS_PENDING = '承認待ち';

    public const STATUS_APPROVED = '承認済み';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'attendance_record_id',
        'approval_status',
        'application_date',
        'new_date',
        'new_clock_in',
        'new_clock_out',
        'comment',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'application_date' => 'date',
        'new_date' => 'date',
    ];

    /**
     * 申請したユーザー
     *
     * @return BelongsTo<User, Application> 申請したユーザー（多対1）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 申請の対象の勤怠
     *
     * @return BelongsTo<AttendanceRecord, Application> 申請の対象の勤怠（多対1）
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * 申請に含まれる休憩
     *
     * @return HasMany<ProposalBreak> 申請に含まれる休憩（1対多）
     */
    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(ProposalBreak::class);
    }

    /**
     * 申請の出勤時刻（HH:MM。提供 Blade がそのまま表示するため秒を除く）
     *
     * @return Attribute 申請の出勤時刻（HH:MM）を返すアクセサ
     */
    protected function newClockIn(): Attribute
    {
        return Attribute::get(fn (?string $value) => is_null($value) ? null : substr($value, 0, 5));
    }

    /**
     * 申請の退勤時刻（HH:MM。提供 Blade がそのまま表示するため秒を除く）
     *
     * @return Attribute 申請の退勤時刻（HH:MM）を返すアクセサ
     */
    protected function newClockOut(): Attribute
    {
        return Attribute::get(fn (?string $value) => is_null($value) ? null : substr($value, 0, 5));
    }
}
