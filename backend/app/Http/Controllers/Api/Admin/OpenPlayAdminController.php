<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpenPlay;
use App\Services\OpenPlayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenPlayAdminController extends Controller
{
    public function __construct(
        protected OpenPlayService $openPlayService
    ) {}

    /**
     * GET /api/admin/open-plays — Danh sách toàn bộ Open Play cho Admin / Lễ tân.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->input('per_page', 20));
        $query = OpenPlay::with(['booking.court', 'host', 'participants.user', 'waitlists.user'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereHas('booking', fn ($q) => $q->where('booking_date', $request->input('date')));
        }

        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('open_play_code', 'like', "%{$s}%")
                    ->orWhereHas('host', fn ($h) => $h->where('full_name', 'like', "%{$s}%"));
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate($perPage),
        ]);
    }

    /**
     * GET /api/admin/open-plays/{id} — Chi tiết Open Play.
     */
    public function show(int $id): JsonResponse
    {
        $openPlay = OpenPlay::with(['booking.court', 'host', 'participants.user', 'waitlists.user'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $openPlay,
        ]);
    }

    /**
     * POST /api/admin/open-plays/scan-qr — Lễ tân quét QR check-in cho participant.
     */
    public function scanQr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_data' => 'required|string',
        ]);

        try {
            // QR format: "OSOP:{open_play_id}:{user_id}:{qr_token}"
            $parts = explode(':', $validated['qr_data']);
            if (count($parts) !== 4 || $parts[0] !== 'OSOP') {
                throw new \Exception('Mã QR không đúng định dạng Open Play.');
            }

            $openPlayId = (int) $parts[1];
            $token = $parts[3];

            $participant = $this->openPlayService->checkInParticipant($openPlayId, $token, auth()->guard('admin')->id());

            return response()->json([
                'status' => 'success',
                'message' => 'Check-in thành công cho người chơi: '.($participant->guest_name ?: 'Khách'),
                'data' => $participant,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
