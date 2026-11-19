<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

/**
 * 公開 API の書き込み系（更新・削除）の認可。本人または管理者のみ許可する
 */
class AttendanceRecordPolicy
{
    /**
     * 管理者はすべての操作を許可する（null を返すと各メソッドの判定に進む）
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->admin_status ? true : null;
    }

    /**
     * 勤怠を更新できるか（本人のみ）
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * 勤怠を削除できるか（本人のみ）
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }
}
