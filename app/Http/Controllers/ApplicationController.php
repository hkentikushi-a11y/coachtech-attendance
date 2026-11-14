<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    /**
     * 申請一覧を表示する（一般ユーザーは自分の申請、管理者は全ユーザーの申請）
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->admin_status) {
            // Blade が $application->AttendanceRecord で参照するため、同じ名前で eager load する
            return view('admin.admin-application-list', [
                'applications' => Application::with(['user', 'AttendanceRecord'])->latest('id')->get(),
            ]);
        }

        return view('user.user-application-list', [
            'user' => $user,
            'formattedApplications' => $user->applications()
                ->latest('id')
                ->get()
                ->map(fn (Application $application) => [
                    'id' => $application->id,
                    'approval_status' => $application->approval_status,
                    'date' => $application->new_date->format('Y/m/d'),
                    'comment' => $application->comment,
                    'application_date' => $application->application_date->format('Y/m/d'),
                ])
                ->all(),
        ]);
    }

    /**
     * 申請の「詳細」リンク。申請の対象の勤怠詳細画面へ移す
     */
    public function show(Request $request, int $id): RedirectResponse
    {
        $application = Application::findOrFail($id);

        abort_unless($application->user_id === $request->user()->id, 403);

        return redirect("/attendance/detail/{$application->attendance_record_id}");
    }
}
