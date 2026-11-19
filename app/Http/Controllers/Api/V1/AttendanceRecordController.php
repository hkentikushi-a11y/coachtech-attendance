<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * 公開 API v1：勤怠（attendance-records）
 */
class AttendanceRecordController extends Controller
{
    /**
     * 勤怠一覧を返す（user_id・date・month で絞り込み、per_page・page でページネーション）
     *
     * @param  IndexAttendanceRecordRequest  $request  検索条件（検証済み）
     * @return AnonymousResourceCollection ページネーション付きの勤怠一覧（data・links・meta）
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        $attendanceRecords = AttendanceRecord::with(['user', 'breaks'])
            ->when($request->filled('user_id'), fn (Builder $query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('date'), fn (Builder $query) => $query->whereDate('date', $request->input('date')))
            ->when($request->filled('month'), function (Builder $query) use ($request) {
                $month = Carbon::createFromFormat('!Y-m', $request->input('month'));

                return $query->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()]);
            })
            ->latest('date')
            ->orderBy('id')
            ->paginate($request->integer('per_page', IndexAttendanceRecordRequest::DEFAULT_PER_PAGE))
            ->withQueryString();

        return AttendanceRecordResource::collection($attendanceRecords);
    }

    /**
     * 勤怠詳細（ユーザー・休憩・修正申請を含む）を返す。存在しない ID は 404
     *
     * @param  AttendanceRecord  $attendanceRecord  対象の勤怠
     * @return AttendanceRecordResource 勤怠の JSON
     */
    public function show(AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        return new AttendanceRecordResource($attendanceRecord->load(['user', 'breaks', 'applications']));
    }

    /**
     * 認証ユーザーの勤怠を登録し、201 で返す
     *
     * @param  StoreAttendanceRecordRequest  $request  登録内容（検証済み）
     * @return JsonResponse 201 Created と登録した勤怠の JSON
     */
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $attendanceRecord = $request->user()->attendanceRecords()->create($request->validated());

        return (new AttendanceRecordResource($attendanceRecord->load(['user', 'breaks'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 勤怠を更新する（送信された項目のみ。本人または管理者のみ）
     *
     * @param  UpdateAttendanceRecordRequest  $request  更新内容（検証済み）
     * @param  AttendanceRecord  $attendanceRecord  対象の勤怠
     * @return AttendanceRecordResource 勤怠の JSON
     */
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $this->authorize('update', $attendanceRecord);

        $attendanceRecord->update($request->validated());

        return new AttendanceRecordResource($attendanceRecord->load(['user', 'breaks']));
    }

    /**
     * 勤怠を削除し、204 を返す（休憩・修正申請も連鎖して削除される。本人または管理者のみ）
     *
     * @param  AttendanceRecord  $attendanceRecord  対象の勤怠
     * @return Response 204 No Content
     */
    public function destroy(AttendanceRecord $attendanceRecord): Response
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
