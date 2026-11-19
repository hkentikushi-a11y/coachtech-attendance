<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * 勤怠の修正（一般ユーザーの修正申請・管理者の直接修正で共通）
 */
class AttendanceCorrectionRequest extends FormRequest
{
    private const CLOCK_ERROR = '出勤時間もしくは退勤時間が不適切な値です';

    private const BREAK_ERROR = '休憩時間が不適切な値です';

    private const BREAK_END_ERROR = '休憩時間もしくは退勤時間が不適切な値です';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i'],
            'new_break_in' => ['nullable', 'array'],
            'new_break_out' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
            'comment' => ['required', 'string', 'max:255'],
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
            'new_clock_in.required' => '出勤時間を入力してください',
            'new_clock_in.date_format' => self::CLOCK_ERROR,
            'new_clock_out.required' => '退勤時間を入力してください',
            'new_clock_out.date_format' => self::CLOCK_ERROR,
            'new_break_in.*.date_format' => self::BREAK_ERROR,
            'new_break_out.*.date_format' => self::BREAK_END_ERROR,
            'comment.required' => '備考を記入してください',
            'comment.max' => '備考は255文字以内で入力してください',
        ];
    }

    /**
     * 出勤・退勤・休憩の前後関係をチェックする
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $errors = $validator->errors();

            if ($errors->hasAny(['new_clock_in', 'new_clock_out'])) {
                return;
            }

            $clockIn = $this->toMinutes($this->input('new_clock_in'));
            $clockOut = $this->toMinutes($this->input('new_clock_out'));

            if ($clockIn >= $clockOut) {
                $errors->add('new_clock_in', self::CLOCK_ERROR);

                return;
            }

            collect($this->breakInputs())
                ->reject(fn (array $break, int $index) => $errors->hasAny(["new_break_in.{$index}", "new_break_out.{$index}"]))
                ->map(fn (array $break, int $index) => $this->breakError($index, $break[0], $break[1], $clockIn, $clockOut))
                ->filter()
                ->each(fn (array $error) => $errors->add($error[0], $error[1]));
        });
    }

    /**
     * 1行分の休憩の前後関係をチェックする
     *
     * @return array{0: string, 1: string}|null [エラーのキー, メッセージ]。問題がなければ null
     */
    private function breakError(int $index, ?string $breakIn, ?string $breakOut, int $clockIn, int $clockOut): ?array
    {
        if (blank($breakIn) && blank($breakOut)) {
            return null;
        }

        if (blank($breakIn) || blank($breakOut)) {
            return ["new_break_in.{$index}", self::BREAK_ERROR];
        }

        $start = $this->toMinutes($breakIn);
        $end = $this->toMinutes($breakOut);

        return match (true) {
            $start < $clockIn || $start > $clockOut => ["new_break_in.{$index}", self::BREAK_ERROR],
            $end > $clockOut => ["new_break_out.{$index}", self::BREAK_END_ERROR],
            $start >= $end => ["new_break_in.{$index}", self::BREAK_ERROR],
            default => null,
        };
    }

    /**
     * 入力された休憩（開始と終了の両方が空の行は除く）
     *
     * @return array<int, array{break_in: string, break_out: string}>
     */
    public function breaks(): array
    {
        return collect($this->breakInputs())
            ->reject(fn (array $break) => blank($break[0]) && blank($break[1]))
            ->map(fn (array $break) => ['break_in' => $break[0], 'break_out' => $break[1]])
            ->values()
            ->all();
    }

    /**
     * 行番号ごとの休憩の入力 [開始, 終了]
     *
     * @return array<int, array{0: ?string, 1: ?string}>
     */
    private function breakInputs(): array
    {
        $breakIns = (array) $this->input('new_break_in', []);
        $breakOuts = (array) $this->input('new_break_out', []);

        return collect(array_keys($breakIns + $breakOuts))
            ->sort()
            ->mapWithKeys(fn (int|string $index) => [$index => [$breakIns[$index] ?? null, $breakOuts[$index] ?? null]])
            ->all();
    }

    /**
     * HH:MM を 0 時からの分にする
     */
    private function toMinutes(string $time): int
    {
        $carbon = Carbon::createFromFormat('!H:i', $time);

        return $carbon->hour * 60 + $carbon->minute;
    }
}
