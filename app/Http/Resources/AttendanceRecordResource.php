<?php

namespace App\Http\Resources;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 公開 API の勤怠（一覧・詳細で共通。関連は eager load されたときだけ含める）
 *
 * @mixin AttendanceRecord
 */
class AttendanceRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request  リクエスト
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $workMinutes = $this->relationLoaded('breaks') ? $this->workMinutes() : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'user' => new UserResource($this->whenLoaded('user')),
            'date' => $this->date->toDateString(),
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'total_time' => is_null($workMinutes) ? null : self::formatMinutes($workMinutes),
            'total_break_time' => $this->when($this->relationLoaded('breaks'), fn () => self::formatMinutes($this->breakMinutes())),
            'comment' => $this->comment,
            'breaks' => AttendanceBreakResource::collection($this->whenLoaded('breaks')),
            'applications' => ApplicationResource::collection($this->whenLoaded('applications')),
        ];
    }

    /**
     * 分を HH:MM 形式にする（API 仕様書のレスポンス例に合わせてゼロ埋めする）
     *
     * @param  int  $minutes  時間（分）
     * @return string HH:MM 形式の文字列（例：08:00）
     */
    private static function formatMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
