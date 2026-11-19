<?php

namespace App\Http\Controllers;

use App\Services\AttendanceReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * ログインユーザーのマイ勤怠レポート（過去6ヶ月の基本サマリー・月次推移・当月の異常検知）を表示する
     */
    public function index(Request $request): View
    {
        $report = AttendanceReport::forUser($request->user(), today());

        return view('reports.index', [
            'summary' => $report->summary(),
            'monthlyTrend' => $report->monthlyTrend(),
            'anomalies' => $report->anomalies(),
        ]);
    }
}
