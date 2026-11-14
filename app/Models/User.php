<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    /**
     * ユーザーの勤怠
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * ユーザーの修正申請
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * 今日の勤怠（休憩を含む）。まだ出勤していない場合は null
     */
    public function todayAttendanceRecord(): ?AttendanceRecord
    {
        return $this->attendanceRecords()
            ->with('breaks')
            ->whereDate('date', today())
            ->first();
    }

    /**
     * 今日の勤怠状況（勤務外／出勤中／休憩中／退勤済）
     */
    protected function attendanceStatus(): Attribute
    {
        return Attribute::get(function (): string {
            $todayRecord = $this->todayAttendanceRecord();

            if (is_null($todayRecord)) {
                return '勤務外';
            }

            if (! is_null($todayRecord->clock_out)) {
                return '退勤済';
            }

            if (! is_null($todayRecord->openBreak())) {
                return '休憩中';
            }

            return '出勤中';
        });
    }
}
