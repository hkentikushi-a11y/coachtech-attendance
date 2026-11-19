<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 月次勤怠の CSV 出力（スタッフ別月次勤怠一覧画面の「CSV出力」）
 */
class AttendanceExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool 常に true（ロールの判定はミドルウェアや Policy で行う）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('admin_status', false)],
            'year_month' => ['required', 'date_format:Y-m'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'ユーザーを指定してください',
            'user_id.integer' => 'ユーザーの指定が不正です',
            'user_id.exists' => 'ユーザーの指定が不正です',
            'year_month.required' => '年月を指定してください',
            'year_month.date_format' => '年月は YYYY-MM 形式で指定してください',
        ];
    }
}
