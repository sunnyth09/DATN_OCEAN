<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpenPlay\CreateOpenPlayRequest;
use App\Http\Requests\OpenPlay\JoinOpenPlayRequest;
use App\Http\Requests\OpenPlay\UpdateOpenPlayRequest;
use App\Models\OpenPlay;
use App\Services\OpenPlayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OpenPlayController extends Controller
{
    public function __construct(
        protected OpenPlayService $openPlayService
    ) {}

    /**
     * GET /api/open-plays — Danh sách trận Open Play (Public/Guest/User).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $openPlays = $this->openPlayService->getOpenPlays($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $openPlays,
            ]);
        } catch (\Exception $e) {
            Log::error('OpenPlayController index error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'filters' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/open-plays/{id} — Chi tiết trận đấu.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $userId = auth()->guard('api')->id();
            $openPlay = $this->openPlayService->getOpenPlayDetail($id, $userId);

            return response()->json([
                'status' => 'success',
                'data' => $openPlay,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /api/open-plays/eligible-bookings — Lấy danh sách booking hợp lệ để tạo trận.
     */
    public function eligibleBookings(): JsonResponse
    {
        $userId = auth()->guard('api')->id();
        $bookings = $this->openPlayService->getEligibleBookings($userId);

        return response()->json([
            'status' => 'success',
            'data' => $bookings,
        ]);
    }

    /**
     * POST /api/open-plays — Host tạo trận Open Play từ booking.
     */
    public function store(CreateOpenPlayRequest $request): JsonResponse
    {
        try {
            $userId = auth()->guard('api')->id();
            $openPlay = $this->openPlayService->createOpenPlay($request->validated(), $userId);

            return response()->json([
                'status' => 'success',
                'message' => 'Tạo trận giao lưu Open Play thành công!',
                'data' => $openPlay,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * PUT /api/open-plays/{id} — Host cập nhật thông tin trận.
     */
    public function update(UpdateOpenPlayRequest $request, int $id): JsonResponse
    {
        try {
            $userId = auth()->guard('api')->id();
            $openPlay = OpenPlay::whereKey($id)->where('host_user_id', $userId)->firstOrFail();
            $openPlay->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật thông tin trận thành công!',
                'data' => $openPlay,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/join — Tham gia trận đấu.
     */
    public function join(JoinOpenPlayRequest $request, int $id): JsonResponse
    {
        try {
            $userId = auth()->guard('api')->id();
            if (! $userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vui lòng đăng nhập hoặc xác thực số điện thoại để tham gia trận.',
                ], 401);
            }

            $participant = $this->openPlayService->joinOpenPlay($id, $userId, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => $participant->status === 'confirmed'
                    ? 'Bạn đã tham gia trận thành công!'
                    : 'Yêu cầu tham gia đã gửi đến Host, vui lòng chờ duyệt!',
                'data' => $participant,
            ], 201);
        } catch (\Exception $e) {
            $code = $e->getMessage() === 'OPEN_PLAY_FULL' ? 'OPEN_PLAY_FULL' : 'JOIN_ERROR';

            return response()->json([
                'status' => 'error',
                'code' => $code,
                'message' => $e->getMessage() === 'OPEN_PLAY_FULL'
                    ? 'Trận đấu đã đủ người chơi. Bạn có thể tham gia danh sách chờ!'
                    : $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/leave — Rời trận đấu.
     */
    public function leave(Request $request, int $id): JsonResponse
    {
        try {
            $userId = auth()->guard('api')->id();
            $result = $this->openPlayService->leaveOpenPlay($id, $userId, $request->input('reason'));

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/waitlist — Tham gia danh sách chờ.
     */
    public function joinWaitlist(int $id): JsonResponse
    {
        try {
            $userId = auth()->guard('api')->id();
            $waitlist = $this->openPlayService->joinWaitlist($id, $userId);

            return response()->json([
                'status' => 'success',
                'message' => "Bạn đã được thêm vào danh sách chờ ở vị trí #{$waitlist->position}!",
                'data' => $waitlist,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/waitlist/leave — Hủy khỏi danh sách chờ.
     */
    public function leaveWaitlist(int $id): JsonResponse
    {
        try {
            $userId = auth()->guard('api')->id();
            $this->openPlayService->leaveWaitlist($id, $userId);

            return response()->json([
                'status' => 'success',
                'message' => 'Bạn đã rời khỏi danh sách chờ.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/approve — Host duyệt người chơi.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $hostUserId = auth()->guard('api')->id();
            $participantId = (int) $request->input('participant_id');
            $participant = $this->openPlayService->approveParticipant($id, $participantId, $hostUserId);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã duyệt người chơi vào trận!',
                'data' => $participant,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/reject — Host từ chối người chơi.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $hostUserId = auth()->guard('api')->id();
            $participantId = (int) $request->input('participant_id');
            $participant = $this->openPlayService->rejectParticipant($id, $participantId, $hostUserId, $request->input('reason'));

            return response()->json([
                'status' => 'success',
                'message' => 'Đã từ chối yêu cầu tham gia.',
                'data' => $participant,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/remove-participant — Host loại thành viên.
     */
    public function removeParticipant(Request $request, int $id): JsonResponse
    {
        try {
            $hostUserId = auth()->guard('api')->id();
            $participantId = (int) $request->input('participant_id');
            $this->openPlayService->removeParticipant($id, $participantId, $hostUserId, $request->input('reason'));

            return response()->json([
                'status' => 'success',
                'message' => 'Đã loại thành viên khỏi trận đấu.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/close — Host đóng đăng ký.
     */
    public function close(int $id): JsonResponse
    {
        try {
            $hostUserId = auth()->guard('api')->id();
            $openPlay = $this->openPlayService->closeRegistration($id, $hostUserId);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã đóng đăng ký cho trận này.',
                'data' => $openPlay,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/cancel — Host hủy trận.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $hostUserId = auth()->guard('api')->id();
            $openPlay = $this->openPlayService->cancelOpenPlay($id, $hostUserId, $request->input('reason'));

            return response()->json([
                'status' => 'success',
                'message' => 'Đã hủy trận Open Play.',
                'data' => $openPlay,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/pay — Participant thanh toán phần tiền (Split Payment).
     */
    public function pay(Request $request, int $id): JsonResponse
    {
        try {
            $userId = auth()->guard('api')->id();
            $validated = $request->validate([
                'payment_method' => 'required|in:wallet,vnpay,momo,bank_transfer,cash',
                'transaction_code' => 'nullable|string|max:100',
            ]);

            $participant = $this->openPlayService->payParticipant($id, $userId, $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Thanh toán phần tiền thành công!',
                'data' => $participant,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/open-plays/{id}/qr — Lấy mã QR check-in của participant.
     */
    public function qr(int $id): JsonResponse
    {
        try {
            $userId = auth()->guard('api')->id();
            $token = $this->openPlayService->generateCheckInToken($id, $userId);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'open_play_id' => $id,
                    'user_id' => $userId,
                    'qr_token' => $token,
                    'qr_data' => "OSOP:{$id}:{$userId}:{$token}",
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/check-in — Check-in tham gia trận.
     */
    public function checkIn(Request $request, int $id): JsonResponse
    {
        try {
            $qrToken = $request->input('qr_token') ?? $request->input('check_in_token');
            if (empty($qrToken)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vui lòng cung cấp mã QR check-in hợp lệ.',
                ], 422);
            }

            $actorId = auth()->guard('api')->id() ?? auth()->guard('admin')->id();
            $participant = $this->openPlayService->checkInParticipant($id, $qrToken, $actorId);

            return response()->json([
                'status' => 'success',
                'message' => 'Check-in thành công!',
                'data' => $participant,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/my-open-plays — Danh sách trận của user.
     */
    public function myOpenPlays(): JsonResponse
    {
        $userId = auth()->guard('api')->id();
        $data = $this->openPlayService->getMyOpenPlays($userId);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/open-plays/guest/send-otp — Gửi OTP cho khách vãng lai.
     */
    public function sendGuestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
        ]);

        try {
            $result = $this->openPlayService->sendGuestOtp($validated['phone']);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/guest/verify-otp — Xác thực OTP cho khách vãng lai.
     */
    public function verifyGuestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string',
            'full_name' => 'nullable|string|max:100',
        ]);

        try {
            $result = $this->openPlayService->verifyGuestOtp(
                $validated['phone'],
                $validated['otp'],
                $validated['full_name'] ?? null
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/open-plays/by-booking/{bookingId} — Lấy thông tin người chơi gắn với một booking.
     */
    public function byBooking(int $bookingId): JsonResponse
    {
        try {
            $userId = auth()->guard('api')->id() ?? 0;
            $openPlay = $this->openPlayService->getOrCreateByBooking($bookingId, $userId);

            return response()->json([
                'status' => 'success',
                'data' => $openPlay,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/init-for-booking/{bookingId} — Khởi tạo hoặc cập nhật capacity cho booking.
     */
    public function initForBooking(Request $request, int $bookingId): JsonResponse
    {
        $validated = $request->validate([
            'additional_slots' => 'nullable|integer|min:1|max:11',
            'max_players' => 'nullable|integer|min:2|max:12',
            'join_mode' => 'nullable|string|in:auto,approval',
            'title' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $userId = auth()->guard('api')->id();
            $openPlay = $this->openPlayService->getOrCreateByBooking($bookingId, $userId, $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật thông tin mời người chơi thành công!',
                'data' => $openPlay,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/open-plays/{id}/invite-users — Host gửi lời mời trực tiếp tới user trong hệ thống.
     */
    public function inviteUsers(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,user_id',
        ]);

        try {
            $userId = auth()->guard('api')->id();
            $result = $this->openPlayService->sendInvites($id, $validated['user_ids'], $userId);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/open-plays/search-invitees — Tìm kiếm người dùng để mời.
     */
    public function searchInvitees(Request $request): JsonResponse
    {
        $query = $request->query('query', '');
        $openPlayId = $request->query('open_play_id') ? (int) $request->query('open_play_id') : null;
        $userId = auth()->guard('api')->id() ?? 0;

        $results = $this->openPlayService->searchInvitees($query, $userId, $openPlayId);

        return response()->json([
            'status' => 'success',
            'data' => $results,
        ]);
    }
}
