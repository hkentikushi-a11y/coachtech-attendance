<?php

namespace App\Http\Resources;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 公開 API の修正申請
 *
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request  リクエスト
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'approval_status' => $this->approval_status,
            'application_date' => $this->application_date->toDateString(),
            'new_date' => $this->new_date->toDateString(),
            'new_clock_in' => $this->new_clock_in,
            'new_clock_out' => $this->new_clock_out,
            'comment' => $this->comment,
        ];
    }
}
