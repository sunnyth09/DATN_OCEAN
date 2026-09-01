<?php

namespace App\Services;

use App\Events\OpenPlayRealtimeEvent;
use App\Models\CourtBooking;
use App\Models\OpenPlay;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlayWaitlist;
use App\Models\PhoneOtpVerification;
use App\Models\User;
use App\Notifications\OpenPlayNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class OpenPlayService
{
    /**
     * Lấy danh sách booking hợp lệ của User để tạo trận Open Play.
     * Điều kiện: Booking thuộc về user, trạng thái pending/confirmed, chưa kết thúc và chưa tạo Open Play.
     */
    public function getEligibleBookings(int $userId): Collection
    {
        return CourtBooking::where('user_id', $userId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($q) {
                $today = now()->toDateString();
                $currentTime = now()->format('H:i:s');
                $q->where('booking_date', '>', $today)
                    ->orWhere(function ($q2) use ($today, $currentTime) {
                        $q2->where('booking_date', '=', $today)
                            ->where('end_time', '>', $currentTime);
                    });
            })
            ->whereDoesntHave('openPlay', function ($q) {
                $q->whereIn('status', ['open', 'full', 'ongoing']);
            })
            ->with('court')
            ->orderBy('booking_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
    }

    /**
     * Tạo một trận Open Play mới từ booking của Host.
     */
    public function createOpenPlay(array $data, int $userId): OpenPlay
    {
        return DB::transaction(function () use ($data, $userId) {
            $booking = CourtBooking::where('booking_id', $data['booking_id'])
                ->where('user_id', $userId)
                ->whereIn('status', ['pending', 'confirmed'])
                ->lockForUpdate()
                ->first();

            if (! $booking) {
                throw new Exception('Lịch đặt sân không hợp lệ, không thuộc về bạn hoặc đã bị hủy.');
            }

            // Kiểm tra booking đã gắn Open Play đang hoạt động chưa
            $existing = OpenPlay::where('booking_id', $booking->booking_id)
                ->whereIn('status', ['open', 'full', 'ongoing'])
                ->exists();

            if ($existing) {
                throw new Exception('Lịch đặt sân này đã được tạo trận Open Play.');
            }

            $maxPlayers = (int) ($data['max_players'] ?? 4);
            if ($maxPlayers < 2 || $maxPlayers > 12) {
                throw new InvalidArgumentException('Số người chơi tối đa phải từ 2 đến 12.');
            }

            $paymentMode = $data['payment_mode'] ?? 'host_pays';
            $slotPrice = 0;

            if ($paymentMode === 'split_payment') {
                $totalAmount = (int) $booking->total_amount;
                $slotPrice = (int) floor($totalAmount / $maxPlayers);
            }

            $openPlayCode = 'OP-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));

            $openPlay = OpenPlay::create([
                'open_play_code' => $openPlayCode,
                'booking_id' => $booking->booking_id,
                'host_user_id' => $userId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'sport_type' => $data['sport_type'] ?? 'badminton',
                'skill_level' => $data['skill_level'] ?? 'all_levels',
                'gender_rule' => $data['gender_rule'] ?? 'any',
                'match_type' => $data['match_type'] ?? 'doubles',
                'max_players' => $maxPlayers,
                'current_players' => 1, // Host is 1st player
                'join_mode' => $data['join_mode'] ?? 'auto',
                'payment_mode' => $paymentMode,
                'slot_price' => $slotPrice,
                'status' => 'open',
                'rules' => $data['rules'] ?? null,
            ]);

            // Tạo bản ghi Host Participant
            $hostUser = User::find($userId);
            $checkInToken = $this->generateCheckInToken($openPlay->id, $userId);

            OpenPlayParticipant::create([
                'open_play_id' => $openPlay->id,
                'user_id' => $userId,
                'guest_name' => $hostUser?->full_name ?? 'Host',
                'guest_phone' => $hostUser?->phone,
                'role' => 'host',
                'status' => 'confirmed',
                'payment_status' => $paymentMode === 'host_pays' ? 'paid' : 'free',
                'payment_amount' => $slotPrice,
                'payment_method' => $booking->payment_method,
                'joined_at' => now(),
                'approved_at' => now(),
                'check_in_token' => $checkInToken,
            ]);

            DB::afterCommit(function () use ($openPlay) {
                OpenPlayRealtimeEvent::dispatch('OpenPlayCreated', [
                    'open_play_id' => $openPlay->id,
                    'open_play_code' => $openPlay->open_play_code,
                    'title' => $openPlay->title,
                    'current_players' => $openPlay->current_players,
                    'max_players' => $openPlay->max_players,
                    'status' => $openPlay->status,
                ]);
            });

            return $openPlay->load(['booking.court', 'host', 'participants.user']);
        });
    }

    /**
     * Danh sách trận Open Play có phân trang, bộ lọc và tìm kiếm.
     */
    public function getOpenPlays(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = max(1, min(50, $perPage));

        $query = OpenPlay::query()
            ->join('court_bookings', 'open_plays.booking_id', '=', 'court_bookings.booking_id')
            ->whereNull('court_bookings.deleted_at')
            ->whereNotIn('court_bookings.status', ['cancelled', 'expired'])
            ->with(['booking.court', 'host', 'confirmedParticipants.user']);

        // Filter status
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('open_plays.status', $filters['status']);
        } else {
            $query->whereIn('open_plays.status', ['open', 'full', 'ongoing']);
        }

        // Filter date
        if (! empty($filters['date'])) {
            $query->where('court_bookings.booking_date', $filters['date']);
        } else {
            // Mặc định chỉ lấy các trận từ hôm nay trở đi
            $today = now()->toDateString();
            $currentTime = now()->format('H:i:s');
            $query->where(function ($q) use ($today, $currentTime) {
                $q->where('court_bookings.booking_date', '>', $today)
                    ->orWhere(function ($q2) use ($today, $currentTime) {
                        $q2->where('court_bookings.booking_date', '=', $today)
                            ->where('court_bookings.end_time', '>', $currentTime);
                    });
            });
        }

        // Filter sport_type
        if (! empty($filters['sport_type']) && $filters['sport_type'] !== 'all') {
            $query->where('open_plays.sport_type', $filters['sport_type']);
        }

        // Filter skill_level
        if (! empty($filters['skill_level']) && $filters['skill_level'] !== 'all') {
            $query->where(function ($q) use ($filters) {
                $q->where('open_plays.skill_level', $filters['skill_level'])
                    ->orWhere('open_plays.skill_level', 'all_levels');
            });
        }

        // Filter gender_rule
        if (! empty($filters['gender_rule']) && $filters['gender_rule'] !== 'all') {
            $query->where('open_plays.gender_rule', $filters['gender_rule']);
        }

        // Filter match_type
        if (! empty($filters['match_type']) && $filters['match_type'] !== 'all') {
            $query->where('open_plays.match_type', $filters['match_type']);
        }

        // Filter available slots only
        if (! empty($filters['available_only']) && filter_var($filters['available_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('open_plays.status', 'open')
                ->whereRaw('open_plays.current_players < open_plays.max_players');
        }

        // Search title or host name
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('open_plays.title', 'like', "%{$search}%")
                    ->orWhere('open_plays.open_play_code', 'like', "%{$search}%")
                    ->orWhereHas('host', function ($h) use ($search) {
                        $h->where('full_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('booking.court', function ($c) use ($search) {
                        $c->where('court_name', 'like', "%{$search}%");
                    });
            });
        }

        // Order by booking date and start time
        $query->orderBy('court_bookings.booking_date', 'asc')
            ->orderBy('court_bookings.start_time', 'asc')
            ->select('open_plays.*');

        return $query->paginate($perPage);
    }

    /**
     * Lấy thông tin chi tiết một trận Open Play.
     */
    public function getOpenPlayDetail(int $id, ?int $currentUserId = null): OpenPlay
    {
        $openPlay = OpenPlay::with([
            'booking.court',
            'host',
            'participants.user',
            'waitlists.user',
        ])->findOrFail($id);

        if ($currentUserId) {
            $myParticipant = $openPlay->participants
                ->where('user_id', $currentUserId)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->first();

            $myWaitlist = $openPlay->waitlists
                ->where('user_id', $currentUserId)
                ->where('status', 'waiting')
                ->first();

            $openPlay->setAttribute('my_participation', $myParticipant);
            $openPlay->setAttribute('my_waitlist', $myWaitlist);
        }

        return $openPlay;
    }

    /**
     * Tham gia trận Open Play (Chống Concurrency bằng lockForUpdate).
     */
    public function joinOpenPlay(int $id, int $userId, array $data = []): OpenPlayParticipant
    {
        return DB::transaction(function () use ($id, $userId, $data) {
            $openPlay = OpenPlay::whereKey($id)->lockForUpdate()->firstOrFail();

            if (! in_array($openPlay->status, ['open', 'full'], true)) {
                throw new Exception('Trận đấu này đã đóng hoặc đã bị hủy.');
            }

            // 1. Kiểm tra xem user đã có lượt tham gia chưa
            $existingParticipant = OpenPlayParticipant::where('open_play_id', $id)
                ->where('user_id', $userId)
                ->whereIn('status', ['registered', 'pending', 'approved', 'confirmed', 'checked_in'])
                ->first();

            if ($existingParticipant) {
                throw new Exception('Bạn đã tham gia hoặc đang chờ duyệt trong trận đấu này.');
            }

            // 2. Tính toán slot trống thực tế
            $availableSlots = (int) $openPlay->max_players - (int) $openPlay->current_players;

            if ($availableSlots <= 0 || $openPlay->status === 'full') {
                throw new Exception('OPEN_PLAY_FULL');
            }

            $user = User::findOrFail($userId);
            $joinMode = $openPlay->join_mode;
            $isAuto = ($joinMode === 'auto');

            $status = $isAuto ? 'confirmed' : 'pending';
            $paymentMode = $openPlay->payment_mode;
            $paymentStatus = ($paymentMode === 'split_payment') ? 'unpaid' : 'free';
            $checkInToken = $this->generateCheckInToken($openPlay->id, $userId);

            // 3. Tạo participant
            $participant = OpenPlayParticipant::create([
                'open_play_id' => $openPlay->id,
                'user_id' => $userId,
                'guest_name' => $data['guest_name'] ?? $user->full_name,
                'guest_phone' => $data['guest_phone'] ?? $user->phone,
                'role' => 'participant',
                'status' => $status,
                'payment_status' => $paymentStatus,
                'payment_amount' => $openPlay->slot_price,
                'joined_at' => now(),
                'approved_at' => $isAuto ? now() : null,
                'check_in_token' => $checkInToken,
            ]);

            // 4. Nếu auto join, tăng current_players
            if ($isAuto) {
                $openPlay->current_players = (int) $openPlay->current_players + 1;
                if ($openPlay->current_players >= $openPlay->max_players) {
                    $openPlay->status = 'full';
                }
                $openPlay->save();
            }

            // 5. Xóa khỏi waitlist nếu trước đó user đang chờ
            OpenPlayWaitlist::where('open_play_id', $id)
                ->where('user_id', $userId)
                ->where('status', 'waiting')
                ->update(['status' => 'cancelled']);

            // 6. Realtime & Notification side-effects
            DB::afterCommit(function () use ($openPlay, $participant, $user, $isAuto) {
                OpenPlayRealtimeEvent::dispatch('ParticipantJoined', [
                    'open_play_id' => $openPlay->id,
                    'participant_id' => $participant->id,
                    'user_id' => $user->user_id,
                    'user_name' => $user->full_name,
                    'status' => $participant->status,
                    'current_players' => $openPlay->current_players,
                    'max_players' => $openPlay->max_players,
                    'open_play_status' => $openPlay->status,
                ]);

                // Gửi thông báo cho Host
                $msg = $isAuto
                    ? "Người chơi {$user->full_name} vừa tham gia trận: {$openPlay->title}"
                    : "Người chơi {$user->full_name} vừa gửi yêu cầu tham gia trận: {$openPlay->title}";

                OpenPlayNotification::sendToUser(
                    $openPlay->host_user_id,
                    $openPlay,
                    $isAuto ? 'participant_joined' : 'join_request_received',
                    $isAuto ? 'Có người chơi mới' : 'Yêu cầu tham gia mới',
                    $msg,
                    ['participant_id' => $participant->id]
                );
            });

            return $participant->load('user');
        });
    }

    /**
     * Rời trận Open Play & Tự động đôn người từ Danh sách chờ (Waitlist FIFO).
     */
    public function leaveOpenPlay(int $id, int $userId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($id, $userId, $reason) {
            $openPlay = OpenPlay::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($openPlay->host_user_id === $userId) {
                throw new Exception('Host không thể rời trận. Nếu muốn hủy trận, vui lòng chọn chức năng Hủy Trận.');
            }

            $participant = OpenPlayParticipant::where('open_play_id', $id)
                ->where('user_id', $userId)
                ->whereIn('status', ['registered', 'pending', 'approved', 'confirmed'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($participant->status === 'checked_in') {
                throw new Exception('Bạn đã check-in vào sân nên không thể rời trận.');
            }

            $wasConfirmed = in_array($participant->status, ['confirmed', 'approved'], true);

            $participant->status = 'cancelled';
            $participant->cancelled_at = now();
            $participant->cancel_reason = $reason ?: 'Người chơi tự rời trận';
            $participant->save();

            $promotedUser = null;

            if ($wasConfirmed) {
                $openPlay->current_players = max(1, (int) $openPlay->current_players - 1);
                $openPlay->status = 'open';

                // Kiểm tra hàng đợi Waitlist (FIFO)
                $nextInWaitlist = OpenPlayWaitlist::where('open_play_id', $id)
                    ->where('status', 'waiting')
                    ->orderBy('position', 'asc')
                    ->lockForUpdate()
                    ->first();

                if ($nextInWaitlist) {
                    $nextInWaitlist->status = 'promoted';
                    $nextInWaitlist->promoted_at = now();
                    $nextInWaitlist->save();

                    $promotedUser = User::find($nextInWaitlist->user_id);

                    if ($promotedUser) {
                        $newParticipant = OpenPlayParticipant::create([
                            'open_play_id' => $openPlay->id,
                            'user_id' => $promotedUser->user_id,
                            'guest_name' => $promotedUser->full_name,
                            'guest_phone' => $promotedUser->phone,
                            'role' => 'participant',
                            'status' => 'confirmed',
                            'payment_status' => ($openPlay->payment_mode === 'split_payment') ? 'unpaid' : 'free',
                            'payment_amount' => $openPlay->slot_price,
                            'joined_at' => now(),
                            'approved_at' => now(),
                            'check_in_token' => $this->generateCheckInToken($openPlay->id, $promotedUser->user_id),
                        ]);

                        $openPlay->current_players = (int) $openPlay->current_players + 1;
                        if ($openPlay->current_players >= $openPlay->max_players) {
                            $openPlay->status = 'full';
                        }
                    }
                }

                $openPlay->save();
            }

            DB::afterCommit(function () use ($openPlay, $userId, $promotedUser) {
                OpenPlayRealtimeEvent::dispatch('ParticipantLeft', [
                    'open_play_id' => $openPlay->id,
                    'user_id' => $userId,
                    'current_players' => $openPlay->current_players,
                    'max_players' => $openPlay->max_players,
                    'open_play_status' => $openPlay->status,
                ]);

                if ($promotedUser) {
                    OpenPlayRealtimeEvent::dispatch('WaitlistPromoted', [
                        'open_play_id' => $openPlay->id,
                        'user_id' => $promotedUser->user_id,
                        'user_name' => $promotedUser->full_name,
                        'target_user_id' => $promotedUser->user_id,
                    ]);

                    OpenPlayNotification::sendToUser(
                        $promotedUser->user_id,
                        $openPlay,
                        'waitlist_promoted',
                        '🎉 Bạn đã được đôn vào trận!',
                        "Có vị trí trống trong trận \"{$openPlay->title}\". Bạn đã chính thức tham gia!",
                    );
                }
            });

            return [
                'status' => 'success',
                'message' => 'Bạn đã rời trận thành công.',
                'promoted_user' => $promotedUser ? $promotedUser->full_name : null,
            ];
        });
    }

    /**
     * Tham gia danh sách chờ (Waitlist).
     */
    public function joinWaitlist(int $id, int $userId): OpenPlayWaitlist
    {
        return DB::transaction(function () use ($id, $userId) {
            $openPlay = OpenPlay::whereKey($id)->lockForUpdate()->firstOrFail();

            if (! in_array($openPlay->status, ['open', 'full'], true)) {
                throw new Exception('Trận đấu này không còn nhận danh sách chờ.');
            }

            // Kiểm tra nếu đã tham gia trận
            $isParticipant = OpenPlayParticipant::where('open_play_id', $id)
                ->where('user_id', $userId)
                ->whereIn('status', ['registered', 'pending', 'approved', 'confirmed', 'checked_in'])
                ->exists();

            if ($isParticipant) {
                throw new Exception('Bạn đã là người chơi trong trận này.');
            }

            // Kiểm tra nếu đã có trong waitlist
            $existingWaitlist = OpenPlayWaitlist::where('open_play_id', $id)
                ->where('user_id', $userId)
                ->where('status', 'waiting')
                ->first();

            if ($existingWaitlist) {
                return $existingWaitlist;
            }

            $maxPosition = (int) OpenPlayWaitlist::where('open_play_id', $id)
                ->where('status', 'waiting')
                ->max('position');

            $waitlist = OpenPlayWaitlist::create([
                'open_play_id' => $id,
                'user_id' => $userId,
                'position' => $maxPosition + 1,
                'status' => 'waiting',
            ]);

            DB::afterCommit(function () use ($openPlay, $waitlist, $userId) {
                OpenPlayRealtimeEvent::dispatch('WaitlistUpdated', [
                    'open_play_id' => $openPlay->id,
                    'user_id' => $userId,
                    'position' => $waitlist->position,
                ]);
            });

            return $waitlist->load('user');
        });
    }

    /**
     * Hủy khỏi danh sách chờ.
     */
    public function leaveWaitlist(int $id, int $userId): bool
    {
        return DB::transaction(function () use ($id, $userId) {
            $waitlist = OpenPlayWaitlist::where('open_play_id', $id)
                ->where('user_id', $userId)
                ->where('status', 'waiting')
                ->first();

            if (! $waitlist) {
                return false;
            }

            $waitlist->status = 'cancelled';
            $waitlist->save();

            return true;
        });
    }

    /**
     * Host duyệt người chơi (Join Approval).
     */
    public function approveParticipant(int $id, int $participantId, int $hostUserId): OpenPlayParticipant
    {
        return DB::transaction(function () use ($id, $participantId, $hostUserId) {
            $openPlay = OpenPlay::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($openPlay->host_user_id !== $hostUserId) {
                throw new Exception('Chỉ Host mới có quyền duyệt người chơi.');
            }

            $participant = OpenPlayParticipant::where('id', $participantId)
                ->where('open_play_id', $id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            $availableSlots = (int) $openPlay->max_players - (int) $openPlay->current_players;
            if ($availableSlots <= 0) {
                throw new Exception('Trận đấu đã đủ số lượng người chơi.');
            }

            $participant->status = 'confirmed';
            $participant->approved_at = now();
            $participant->save();

            $openPlay->current_players = (int) $openPlay->current_players + 1;
            if ($openPlay->current_players >= $openPlay->max_players) {
                $openPlay->status = 'full';
            }
            $openPlay->save();

            DB::afterCommit(function () use ($openPlay, $participant) {
                OpenPlayRealtimeEvent::dispatch('ParticipantApproved', [
                    'open_play_id' => $openPlay->id,
                    'participant_id' => $participant->id,
                    'user_id' => $participant->user_id,
                    'current_players' => $openPlay->current_players,
                    'max_players' => $openPlay->max_players,
                    'open_play_status' => $openPlay->status,
                ]);

                if ($participant->user_id) {
                    OpenPlayNotification::sendToUser(
                        $participant->user_id,
                        $openPlay,
                        'join_request_approved',
                        '✅ Yêu cầu tham gia đã được duyệt!',
                        "Host đã chấp nhận yêu cầu tham gia trận \"{$openPlay->title}\" của bạn."
                    );
                }
            });

            return $participant->load('user');
        });
    }

    /**
     * Host từ chối người chơi.
     */
    public function rejectParticipant(int $id, int $participantId, int $hostUserId, ?string $reason = null): OpenPlayParticipant
    {
        return DB::transaction(function () use ($id, $participantId, $hostUserId, $reason) {
            $openPlay = OpenPlay::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($openPlay->host_user_id !== $hostUserId) {
                throw new Exception('Chỉ Host mới có quyền từ chối người chơi.');
            }

            $participant = OpenPlayParticipant::where('id', $participantId)
                ->where('open_play_id', $id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            $participant->status = 'rejected';
            $participant->cancel_reason = $reason ?: 'Host từ chối yêu cầu';
            $participant->cancelled_at = now();
            $participant->save();

            DB::afterCommit(function () use ($openPlay, $participant) {
                OpenPlayRealtimeEvent::dispatch('ParticipantRejected', [
                    'open_play_id' => $openPlay->id,
                    'participant_id' => $participant->id,
                    'user_id' => $participant->user_id,
                ]);

                if ($participant->user_id) {
                    OpenPlayNotification::sendToUser(
                        $participant->user_id,
                        $openPlay,
                        'join_request_rejected',
                        '❌ Yêu cầu tham gia bị từ chối',
                        "Host đã từ chối yêu cầu tham gia trận \"{$openPlay->title}\" của bạn."
                    );
                }
            });

            return $participant;
        });
    }

    /**
     * Host loại một thành viên ra khỏi trận (Kick participant).
     */
    public function removeParticipant(int $id, int $participantId, int $hostUserId, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($id, $participantId, $hostUserId, $reason) {
            $openPlay = OpenPlay::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($openPlay->host_user_id !== $hostUserId) {
                throw new Exception('Chỉ Host mới có quyền loại thành viên.');
            }

            $participant = OpenPlayParticipant::where('id', $participantId)
                ->where('open_play_id', $id)
                ->whereIn('status', ['confirmed', 'approved'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($participant->user_id === $hostUserId) {
                throw new Exception('Host không thể tự loại chính mình.');
            }

            $participant->status = 'cancelled';
            $participant->cancelled_at = now();
            $participant->cancel_reason = $reason ?: 'Host loại khỏi trận';
            $participant->save();

            $openPlay->current_players = max(1, (int) $openPlay->current_players - 1);
            $openPlay->status = 'open';

            // Tự động đôn người từ danh sách chờ nếu có
            $nextInWaitlist = OpenPlayWaitlist::where('open_play_id', $id)
                ->where('status', 'waiting')
                ->orderBy('position', 'asc')
                ->lockForUpdate()
                ->first();

            $promotedUser = null;
            if ($nextInWaitlist) {
                $nextInWaitlist->status = 'promoted';
                $nextInWaitlist->promoted_at = now();
                $nextInWaitlist->save();

                $promotedUser = User::find($nextInWaitlist->user_id);
                if ($promotedUser) {
                    OpenPlayParticipant::create([
                        'open_play_id' => $openPlay->id,
                        'user_id' => $promotedUser->user_id,
                        'guest_name' => $promotedUser->full_name,
                        'guest_phone' => $promotedUser->phone,
                        'role' => 'participant',
                        'status' => 'confirmed',
                        'payment_status' => ($openPlay->payment_mode === 'split_payment') ? 'unpaid' : 'free',
                        'payment_amount' => $openPlay->slot_price,
                        'joined_at' => now(),
                        'approved_at' => now(),
                        'check_in_token' => $this->generateCheckInToken($openPlay->id, $promotedUser->user_id),
                    ]);

                    $openPlay->current_players = (int) $openPlay->current_players + 1;
                    if ($openPlay->current_players >= $openPlay->max_players) {
                        $openPlay->status = 'full';
                    }
                }
            }

            $openPlay->save();

            DB::afterCommit(function () use ($openPlay, $participant, $promotedUser) {
                OpenPlayRealtimeEvent::dispatch('ParticipantLeft', [
                    'open_play_id' => $openPlay->id,
                    'user_id' => $participant->user_id,
                    'current_players' => $openPlay->current_players,
                    'max_players' => $openPlay->max_players,
                    'open_play_status' => $openPlay->status,
                ]);

                if ($participant->user_id) {
                    OpenPlayNotification::sendToUser(
                        $participant->user_id,
                        $openPlay,
                        'participant_removed',
                        'Thông báo từ Host',
                        "Bạn đã bị Host loại khỏi trận \"{$openPlay->title}\"."
                    );
                }

                if ($promotedUser) {
                    OpenPlayNotification::sendToUser(
                        $promotedUser->user_id,
                        $openPlay,
                        'waitlist_promoted',
                        '🎉 Bạn đã được đôn vào trận!',
                        "Có vị trí trống trong trận \"{$openPlay->title}\". Bạn đã chính thức tham gia!"
                    );
                }
            });

            return true;
        });
    }

    /**
     * Host đóng đăng ký sớm.
     */
    public function closeRegistration(int $id, int $hostUserId): OpenPlay
    {
        $openPlay = OpenPlay::whereKey($id)->where('host_user_id', $hostUserId)->firstOrFail();
        $openPlay->status = 'full';
        $openPlay->save();

        OpenPlayRealtimeEvent::dispatch('OpenPlayUpdated', [
            'open_play_id' => $openPlay->id,
            'status' => $openPlay->status,
        ]);

        return $openPlay;
    }

    /**
     * Host hủy trận Open Play.
     */
    public function cancelOpenPlay(int $id, int $hostUserId, ?string $reason = null): OpenPlay
    {
        return DB::transaction(function () use ($id, $hostUserId, $reason) {
            $openPlay = OpenPlay::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($openPlay->host_user_id !== $hostUserId) {
                throw new Exception('Chỉ Host mới có quyền hủy trận.');
            }

            $openPlay->status = 'cancelled';
            $openPlay->save();

            // Cập nhật tất cả participants
            $participants = OpenPlayParticipant::where('open_play_id', $id)
                ->whereIn('status', ['registered', 'pending', 'approved', 'confirmed'])
                ->get();

            foreach ($participants as $p) {
                $p->status = 'cancelled';
                $p->cancelled_at = now();
                $p->cancel_reason = $reason ?: 'Host hủy trận đấu';
                $p->save();
            }

            // Hủy toàn bộ waitlists
            OpenPlayWaitlist::where('open_play_id', $id)
                ->where('status', 'waiting')
                ->update(['status' => 'cancelled']);

            DB::afterCommit(function () use ($openPlay, $participants, $reason) {
                OpenPlayRealtimeEvent::dispatch('OpenPlayCancelled', [
                    'open_play_id' => $openPlay->id,
                    'reason' => $reason ?: 'Host đã hủy trận đấu.',
                ]);

                foreach ($participants as $p) {
                    if ($p->user_id && $p->role !== 'host') {
                        OpenPlayNotification::sendToUser(
                            $p->user_id,
                            $openPlay,
                            'open_play_cancelled',
                            'Trận đấu đã bị hủy',
                            "Trận đấu \"{$openPlay->title}\" đã bị Host hủy."
                        );
                    }
                }
            });

            return $openPlay;
        });
    }

    /**
     * Thanh toán phần tiền của Participant (Split Payment).
     */
    public function payParticipant(int $id, int $userId, array $paymentData): OpenPlayParticipant
    {
        return DB::transaction(function () use ($id, $userId, $paymentData) {
            $participant = OpenPlayParticipant::where('open_play_id', $id)
                ->where('user_id', $userId)
                ->whereIn('status', ['confirmed', 'approved'])
                ->lockForUpdate()
                ->firstOrFail();

            $openPlay = OpenPlay::findOrFail($id);
            $amount = (int) ($participant->payment_amount ?: $openPlay->slot_price);
            $method = $paymentData['payment_method'] ?? 'wallet';

            if ($method === 'wallet') {
                $walletService = app(WalletService::class);
                $walletService->debit(
                    userId: $userId,
                    amount: (float) $amount,
                    type: 'payment',
                    opts: [
                        'description' => "Thanh toán slot trận Open Play #{$openPlay->open_play_code}",
                        'metadata' => [
                            'open_play_id' => $openPlay->id,
                            'participant_id' => $participant->id,
                        ],
                    ]
                );
            }

            $txnCode = $paymentData['transaction_code'] ?? ('OPP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)));

            $participant->payment_status = 'paid';
            $participant->payment_method = $method;
            $participant->payment_transaction_code = $txnCode;
            $participant->save();

            DB::afterCommit(function () use ($openPlay, $participant, $userId) {
                OpenPlayRealtimeEvent::dispatch('PaymentUpdated', [
                    'open_play_id' => $openPlay->id,
                    'participant_id' => $participant->id,
                    'user_id' => $userId,
                    'payment_status' => 'paid',
                ]);

                // Thông báo cho Host biết participant đã đóng tiền
                OpenPlayNotification::sendToUser(
                    $openPlay->host_user_id,
                    $openPlay,
                    'participant_paid',
                    'Đã nhận thanh toán slot',
                    "Người chơi {$participant->guest_name} đã thanh toán slot: ".number_format($participant->payment_amount, 0, ',', '.').'đ'
                );
            });

            return $participant;
        });
    }

    /**
     * Check-in người chơi bằng QR token.
     */
    public function checkInParticipant(int $id, string $token, ?int $actorUserId = null): OpenPlayParticipant
    {
        return DB::transaction(function () use ($id, $token) {
            $participant = OpenPlayParticipant::where('open_play_id', $id)
                ->where('check_in_token', $token)
                ->whereIn('status', ['confirmed', 'approved'])
                ->lockForUpdate()
                ->first();

            if (! $participant) {
                throw new Exception('Mã QR check-in không hợp lệ hoặc người chơi chưa được xác nhận.');
            }

            $participant->status = 'checked_in';
            $participant->checked_in_at = now();
            $participant->save();

            $openPlay = OpenPlay::findOrFail($id);

            DB::afterCommit(function () use ($openPlay, $participant) {
                OpenPlayRealtimeEvent::dispatch('ParticipantCheckedIn', [
                    'open_play_id' => $openPlay->id,
                    'participant_id' => $participant->id,
                    'user_id' => $participant->user_id,
                    'user_name' => $participant->guest_name,
                ]);
            });

            return $participant;
        });
    }

    /**
     * Lấy danh sách trận mà user làm Host hoặc tham gia.
     */
    public function getMyOpenPlays(int $userId): array
    {
        $hosted = OpenPlay::where('host_user_id', $userId)
            ->with(['booking.court', 'participants.user', 'waitlists.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        $joined = OpenPlay::whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId)->where('role', '!=', 'host');
        })
            ->with(['booking.court', 'host', 'participants.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'hosted' => $hosted,
            'joined' => $joined,
        ];
    }

    /**
     * Gửi OTP xác thực số điện thoại cho khách vãng lai.
     */
    public function sendGuestOtp(string $phone): array
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) < 9 || strlen($cleanPhone) > 11) {
            throw new InvalidArgumentException('Số điện thoại không hợp lệ.');
        }

        // Tạo OTP 6 chữ số ngẫu nhiên
        $otp = (string) random_int(100000, 999999);
        $hashedOtp = Hash::make($otp);
        $expiresAt = now()->addMinutes(5);

        // Xóa OTP cũ của số này
        PhoneOtpVerification::where('phone', $cleanPhone)->delete();

        PhoneOtpVerification::create([
            'phone' => $cleanPhone,
            'otp' => $hashedOtp,
            'expires_at' => $expiresAt,
        ]);

        // Log OTP cho local/test environment
        Log::info("Guest OTP sent for {$cleanPhone}: {$otp}");

        return [
            'status' => 'success',
            'message' => 'Mã OTP đã được gửi đến số điện thoại của bạn. (Hiệu lực 5 phút)',
            'dev_otp' => app()->environment(['local', 'testing']) ? $otp : null,
        ];
    }

    /**
     * Xác thực OTP và đăng nhập/tạo tài khoản khách vãng lai cấp JWT.
     */
    public function verifyGuestOtp(string $phone, string $otp, ?string $fullName = null): array
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $record = PhoneOtpVerification::where('phone', $cleanPhone)->first();

        if (! $record || ! Hash::check($otp, $record->otp)) {
            throw new Exception('Mã OTP không chính xác.');
        }

        if (Carbon::parse($record->expires_at)->isPast()) {
            $record->delete();
            throw new Exception('Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới.');
        }

        $record->delete();

        // Tìm hoặc tạo User dựa vào số điện thoại
        $user = User::where('phone', $cleanPhone)->first();

        if (! $user) {
            $name = $fullName ?: 'Khách '.substr($cleanPhone, -4);
            $randomEmail = 'guest_'.Str::random(8).'@oceansport.vn';

            $user = User::create([
                'full_name' => $name,
                'email' => $randomEmail,
                'phone' => $cleanPhone,
                'password' => Hash::make(Str::random(16)),
            ]);
        }

        $token = JWTAuth::fromUser($user);

        return [
            'status' => 'success',
            'message' => 'Xác thực thành công!',
            'token' => $token,
            'user' => [
                'user_id' => $user->user_id,
                'full_name' => $user->full_name,
                'phone' => $user->phone,
                'email' => $user->email,
            ],
        ];
    }

    /**
     * Sinh token HMAC SHA-256 bảo mật cho check-in.
     */
    public function generateCheckInToken(int $openPlayId, int $userId): string
    {
        return hash_hmac('sha256', "OP_CHECKIN:{$openPlayId}:{$userId}", config('app.key'));
    }

    /**
     * Lấy hoặc khởi tạo Open Play / Collaboration gắn liền với một Booking đã đặt.
     */
    public function getOrCreateByBooking(int $bookingId, int $userId, array $params = []): OpenPlay
    {
        return DB::transaction(function () use ($bookingId, $userId, $params) {
            $booking = CourtBooking::where('booking_id', $bookingId)
                ->with(['court', 'user'])
                ->lockForUpdate()
                ->firstOrFail();

            $existing = OpenPlay::where('booking_id', $bookingId)
                ->with(['booking.court', 'host', 'participants.user', 'waitlists.user'])
                ->first();

            if ($existing) {
                if ($existing->host_user_id === $userId && ! empty($params['max_players'])) {
                    $newMax = (int) $params['max_players'];
                    if ($newMax >= $existing->current_players && $newMax <= 12) {
                        $existing->max_players = $newMax;
                        if (! empty($params['join_mode'])) {
                            $existing->join_mode = $params['join_mode'];
                        }
                        if ($existing->current_players >= $newMax) {
                            $existing->status = 'full';
                        } elseif ($existing->status === 'full' && $existing->current_players < $newMax) {
                            $existing->status = 'open';
                        }
                        $existing->save();
                    }
                }

                return $existing;
            }

            if ($booking->user_id !== $userId) {
                throw new Exception('Chỉ người đặt sân (Host) mới có quyền mở lời mời người chơi.');
            }

            if (in_array($booking->status, ['cancelled', 'expired', 'completed'])) {
                throw new Exception('Lịch đặt sân đã kết thúc hoặc bị hủy.');
            }

            $additionalSlots = isset($params['additional_slots']) ? (int) $params['additional_slots'] : 3;
            $maxPlayers = isset($params['max_players']) ? (int) $params['max_players'] : ($additionalSlots + 1);
            $maxPlayers = max(2, min(12, $maxPlayers));

            $openPlayCode = 'OP-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
            $courtName = $booking->court?->court_name ?? 'Sân thể thao';

            $openPlay = OpenPlay::create([
                'open_play_code' => $openPlayCode,
                'booking_id' => $booking->booking_id,
                'host_user_id' => $userId,
                'title' => $params['title'] ?? ('Giao lưu '.$courtName),
                'description' => $params['description'] ?? 'Rủ người chơi cùng',
                'sport_type' => 'badminton',
                'skill_level' => $params['skill_level'] ?? 'all_levels',
                'gender_rule' => $params['gender_rule'] ?? 'any',
                'match_type' => 'doubles',
                'max_players' => $maxPlayers,
                'current_players' => 1,
                'join_mode' => $params['join_mode'] ?? 'auto',
                'payment_mode' => 'host_pays',
                'slot_price' => 0,
                'status' => 'open',
                'rules' => $params['rules'] ?? null,
            ]);

            $checkInToken = $this->generateCheckInToken($openPlay->id, $userId);

            OpenPlayParticipant::create([
                'open_play_id' => $openPlay->id,
                'user_id' => $userId,
                'guest_name' => $booking->user?->full_name ?? 'Host',
                'guest_phone' => $booking->user?->phone,
                'role' => 'host',
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'payment_amount' => 0,
                'joined_at' => now(),
                'approved_at' => now(),
                'check_in_token' => $checkInToken,
            ]);

            return $openPlay->load(['booking.court', 'host', 'participants.user', 'waitlists.user']);
        });
    }

    /**
     * Gửi thông báo mời người chơi cụ thể trong hệ thống.
     */
    public function sendInvites(int $openPlayId, array $userIds, int $hostUserId): array
    {
        $openPlay = OpenPlay::with(['booking.court', 'host', 'participants'])->findOrFail($openPlayId);

        if ($openPlay->host_user_id !== $hostUserId) {
            throw new Exception('Chỉ Host mới có quyền gửi lời mời.');
        }

        if (in_array($openPlay->status, ['full', 'cancelled', 'completed'])) {
            throw new Exception('Trận chơi đã đủ người hoặc không còn nhận thêm.');
        }

        $hostName = $openPlay->host?->full_name ?? 'Bạn bè';
        $courtName = $openPlay->booking?->court?->court_name ?? 'Sân thể thao';
        $dateStr = Carbon::parse($openPlay->booking?->booking_date)->format('d/m/Y');
        $timeStr = substr($openPlay->booking?->start_time ?? '', 0, 5).' - '.substr($openPlay->booking?->end_time ?? '', 0, 5);

        $invitedCount = 0;
        $existingParticipantUserIds = $openPlay->participants->pluck('user_id')->toArray();

        foreach ($userIds as $targetUserId) {
            $targetUserId = (int) $targetUserId;
            if ($targetUserId === $hostUserId || in_array($targetUserId, $existingParticipantUserIds)) {
                continue;
            }

            $targetUser = User::find($targetUserId);
            if (! $targetUser) {
                continue;
            }

            $title = '🏸 Lời mời chơi thể thao';
            $message = "{$hostName} mời bạn tham gia trận chơi tại {$courtName} ({$timeStr}, ngày {$dateStr}). Còn ".max(0, $openPlay->max_players - $openPlay->current_players).' slot!';

            OpenPlayNotification::sendToUser(
                $targetUser,
                $openPlay,
                'player_invited',
                $title,
                $message,
                [
                    'booking_id' => $openPlay->booking_id,
                    'court_name' => $courtName,
                    'date_str' => $dateStr,
                    'time_str' => $timeStr,
                    'host_name' => $hostName,
                ]
            );

            $invitedCount++;
        }

        return [
            'status' => 'success',
            'message' => "Đã gửi lời mời tới {$invitedCount} người chơi!",
            'invited_count' => $invitedCount,
        ];
    }

    /**
     * Tìm kiếm người dùng trong hệ thống để Host chọn mời.
     */
    public function searchInvitees(string $query, int $currentUserId, ?int $openPlayId = null): array
    {
        $q = trim($query);
        if (strlen($q) < 1) {
            return [];
        }

        $excludedIds = [$currentUserId];
        if ($openPlayId) {
            $existing = OpenPlayParticipant::where('open_play_id', $openPlayId)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->toArray();
            $excludedIds = array_merge($excludedIds, $existing);
        }

        $users = User::whereNotIn('user_id', $excludedIds)
            ->where(function ($userQuery) use ($q) {
                $userQuery->where('full_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->select(['user_id', 'full_name', 'phone', 'email', 'avatar'])
            ->limit(15)
            ->get();

        return $users->toArray();
    }
}
