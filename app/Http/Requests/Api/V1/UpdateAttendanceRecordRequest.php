<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AttendanceRecord;
use Illuminate\Validation\Rule;

/**
 * 公開 API：勤怠の更新（部分更新に対応。送信された項目のみ検証・更新する）
 *
 * エラーメッセージは登録（FN060）と同じため、StoreAttendanceRecordRequest を継承する
 */
class UpdateAttendanceRecordRequest extends StoreAttendanceRecordRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var AttendanceRecord $attendanceRecord */
        $attendanceRecord = $this->route('attendanceRecord');

        return [
            // 日付の重複は自分自身を除き、対象レコードのユーザーで判定する（管理者が更新する場合に備える）
            'date' => ['sometimes', 'required', 'date_format:Y-m-d',
                Rule::unique('attendance_records', 'date')->ignore($attendanceRecord)->where('user_id', $attendanceRecord->user_id)],
            'clock_in' => ['sometimes', 'required', 'date_format:H:i:s'],
            // clock_in が送信されていない場合は、登録済みの出勤時刻と比較する
            'clock_out' => ['sometimes', 'nullable', 'date_format:H:i:s', 'after:' . ($this->has('clock_in') ? 'clock_in' : $attendanceRecord->clock_in)],
            'comment' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
