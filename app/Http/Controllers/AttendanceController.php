<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /**
     * 勤怠登録画面を表示する
     */
    public function index(Request $request): View
    {
        $now = now();

        return view('user.attendance-register', [
            'user' => $request->user(),
            'formattedDate' => $now->format('Y年n月j日') . '(' . self::WEEKDAYS[$now->dayOfWeek] . ')',
            'formattedTime' => $now->format('H:i'),
        ]);
    }
}
