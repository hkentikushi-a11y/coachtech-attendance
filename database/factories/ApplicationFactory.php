<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_record_id' => AttendanceRecord::factory(),
            'user_id' => fn (array $attributes) => AttendanceRecord::find($attributes['attendance_record_id'])->user_id,
            'approval_status' => Application::STATUS_PENDING,
            'application_date' => today()->toDateString(),
            'new_date' => fn (array $attributes) => AttendanceRecord::find($attributes['attendance_record_id'])->date->toDateString(),
            'new_clock_in' => '09:00:00',
            'new_clock_out' => '18:00:00',
            'comment' => '打刻漏れのため',
        ];
    }

    /**
     * Indicate that the application has been approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => Application::STATUS_APPROVED,
        ]);
    }
}
