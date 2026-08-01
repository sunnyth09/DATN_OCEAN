<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder: Tạo user demo + admin demo + booking demo đầy đủ các trạng thái
 */
class CourtBookingSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // =============================================
        // 1. TẠO TÀI KHOẢN DEMO
        // =============================================
        echo "\n👤 Tạo tài khoản demo...\n";

        // --- Admin (bảng admins) ---
        $adminId = $this->createAdmin([
            'full_name' => 'Quản Trị Viên',
            'email' => 'court_admin@demo.com',
            'phone' => '0900000001',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $staffId = $this->createAdmin([
            'full_name' => 'Lễ Tân Sân',
            'email' => 'court_staff@demo.com',
            'phone' => '0900000002',
            'role' => 'staff',
            'status' => 'active',
        ]);

        // --- Users (bảng users) ---
        $userIds = [];
        $userNames = [
            ['Nguyễn Văn An',    'nguyen.an@demo.com',    '0911000001'],
            ['Trần Thị Bình',    'tran.binh@demo.com',    '0911000002'],
            ['Lê Hoàng Cường',   'le.cuong@demo.com',     '0911000003'],
            ['Phạm Minh Đức',    'pham.duc@demo.com',     '0911000004'],
            ['Võ Thanh Em',      'vo.em@demo.com',        '0911000005'],
        ];

        foreach ($userNames as $u) {
            $userId = $this->createUser($u[0], $u[1], $u[2]);
            $userIds[] = $userId;
        }

        // Dọn dữ liệu booking demo cũ để seeder có thể chạy lại nhiều lần
        // mà không vi phạm unique booking_code hoặc nhân đôi lịch sử/thanh toán.
        $this->clearDemoBookings($userIds);

        // =============================================
        // 2. LẤY DANH SÁCH SÂN & DỊCH VỤ
        // =============================================
        $courts = DB::table('courts')->whereNull('deleted_at')->get();
        $activeCourts = $courts->where('status', 'active');
        $services = DB::table('court_services')->where('is_active', true)->whereNull('deleted_at')->get();

        if ($activeCourts->isEmpty()) {
            echo "⚠️  Không tìm thấy sân nào active. Chạy CourtSeeder trước!\n";

            return;
        }

        // =============================================
        // 3. TẠO BOOKING DEMO — nhiều trạng thái
        // =============================================
        echo "\n📋 Tạo booking demo...\n";

        $today = Carbon::today();
        $bookingCounter = 0;

        // --- NHÓM 1: Booking đã HOÀN THÀNH (quá khứ, 7 ngày trước) ---
        for ($dayOffset = -7; $dayOffset <= -1; $dayOffset++) {
            $date = $today->copy()->addDays($dayOffset);
            $courtList = $activeCourts->values();
            $slotsUsed = [];

            // Mỗi ngày tạo 3-5 booking hoàn thành
            $numBookings = rand(3, 5);
            for ($i = 0; $i < $numBookings; $i++) {
                $court = $courtList[$i % $courtList->count()];
                $userId = $userIds[array_rand($userIds)];

                // Chọn khung giờ ngẫu nhiên không trùng
                $startHour = $this->pickAvailableSlot($slotsUsed, $court->court_id, $date->format('Y-m-d'));
                if ($startHour === null) {
                    continue;
                }

                $duration = rand(1, 2); // 1-2 giờ
                $endHour = $startHour + $duration;
                $slotsUsed[] = ['court' => $court->court_id, 'start' => $startHour, 'end' => $endHour];

                $price = $this->calculatePrice($court->court_id, $date, $startHour, $endHour);
                $bookingCode = 'BK-'.$date->format('Ymd').'-'.strtoupper(Str::random(4));

                $bookingId = DB::table('court_bookings')->insertGetId([
                    'booking_code' => $bookingCode,
                    'user_id' => $userId,
                    'staff_id' => null,
                    'court_id' => $court->court_id,
                    'booking_date' => $date->format('Y-m-d'),
                    'start_time' => sprintf('%02d:00:00', $startHour),
                    'end_time' => sprintf('%02d:00:00', $endHour),
                    'duration_minutes' => $duration * 60,
                    'status' => 'completed',
                    'original_price' => $price,
                    'discount_amount' => 0,
                    'service_amount' => 0,
                    'total_amount' => $price,
                    'deposit_amount' => 0,
                    'paid_amount' => $price,
                    'payment_status' => 'paid',
                    'payment_method' => ['cash', 'vnpay', 'momo', 'bank_transfer'][array_rand(['cash', 'vnpay', 'momo', 'bank_transfer'])],
                    'checked_in_at' => $date->copy()->setHour($startHour),
                    'checked_out_at' => $date->copy()->setHour($endHour),
                    'confirmed_at' => $date->copy()->setHour($startHour)->subMinutes(30),
                    'cancelled_at' => null,
                    'source' => 'web',
                    'note' => null,
                    'created_at' => $date->copy()->subDays(1)->setHour(rand(8, 20)),
                    'updated_at' => $date->copy()->setHour($endHour),
                ]);

                // Status history
                $this->createStatusHistory($bookingId, [
                    ['old' => null, 'new' => 'pending', 'actor' => 'user', 'actor_id' => $userId],
                    ['old' => 'pending', 'new' => 'confirmed', 'actor' => 'admin', 'actor_id' => $adminId],
                    ['old' => 'confirmed', 'new' => 'checked_in', 'actor' => 'admin', 'actor_id' => $staffId],
                    ['old' => 'checked_in', 'new' => 'completed', 'actor' => 'admin', 'actor_id' => $staffId],
                ], $date);

                // Payment record
                $this->createPayment($bookingId, 'full', $price, 'success', $staffId);

                // Random: thêm dịch vụ cho ~40% booking
                if (rand(1, 10) <= 4 && $services->isNotEmpty()) {
                    $this->addRandomServices($bookingId, $services, $staffId);
                }

                $bookingCounter++;
            }
        }

        // --- NHÓM 2: Booking HÔM NAY — đa dạng trạng thái ---
        $todayCourts = $activeCourts->values();
        $todaySlots = [];

        // 2a. 1 booking checked_in (đang chơi)
        $court = $todayCourts[0];
        $startH = (int) $now->format('H') - 1;
        if ($startH < 6) {
            $startH = 6;
        }
        $endH = $startH + 2;
        $todaySlots[] = ['court' => $court->court_id, 'start' => $startH, 'end' => $endH];
        $price = $this->calculatePrice($court->court_id, $today, $startH, $endH);
        $bId = DB::table('court_bookings')->insertGetId([
            'booking_code' => 'BK-'.$today->format('Ymd').'-PLAY',
            'user_id' => $userIds[0],
            'staff_id' => $staffId,
            'court_id' => $court->court_id,
            'booking_date' => $today->format('Y-m-d'),
            'start_time' => sprintf('%02d:00:00', $startH),
            'end_time' => sprintf('%02d:00:00', $endH),
            'duration_minutes' => 120,
            'status' => 'checked_in',
            'original_price' => $price,
            'discount_amount' => 0,
            'service_amount' => 0,
            'total_amount' => $price,
            'deposit_amount' => (int) ($price * 0.5),
            'paid_amount' => (int) ($price * 0.5),
            'payment_status' => 'deposit_paid',
            'payment_method' => 'cash',
            'checked_in_at' => $today->copy()->setHour($startH),
            'confirmed_at' => $today->copy()->setHour($startH)->subMinutes(20),
            'source' => 'web',
            'created_at' => $today->copy()->subDay(),
            'updated_at' => $now,
        ]);
        $this->createStatusHistory($bId, [
            ['old' => null, 'new' => 'pending', 'actor' => 'user', 'actor_id' => $userIds[0]],
            ['old' => 'pending', 'new' => 'confirmed', 'actor' => 'admin', 'actor_id' => $adminId],
            ['old' => 'confirmed', 'new' => 'checked_in', 'actor' => 'admin', 'actor_id' => $staffId],
        ], $today);
        $this->createPayment($bId, 'deposit', (int) ($price * 0.5), 'success', $staffId);
        $bookingCounter++;

        // 2b. 2 booking confirmed (chờ đến giờ)
        for ($i = 1; $i <= 2; $i++) {
            $court = $todayCourts[$i % $todayCourts->count()];
            $sH = (int) $now->format('H') + $i + 1;
            if ($sH > 20) {
                $sH = 18 + $i;
            }
            $eH = $sH + 1;
            $todaySlots[] = ['court' => $court->court_id, 'start' => $sH, 'end' => $eH];
            $price = $this->calculatePrice($court->court_id, $today, $sH, $eH);

            $bId = DB::table('court_bookings')->insertGetId([
                'booking_code' => 'BK-'.$today->format('Ymd').'-CF'.$i,
                'user_id' => $userIds[$i],
                'court_id' => $court->court_id,
                'booking_date' => $today->format('Y-m-d'),
                'start_time' => sprintf('%02d:00:00', $sH),
                'end_time' => sprintf('%02d:00:00', $eH),
                'duration_minutes' => 60,
                'status' => 'confirmed',
                'original_price' => $price,
                'discount_amount' => 0,
                'service_amount' => 0,
                'total_amount' => $price,
                'deposit_amount' => 0,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'payment_method' => 'cash',
                'confirmed_at' => $now->copy()->subHours(2),
                'source' => 'web',
                'created_at' => $today->copy()->subDay(),
                'updated_at' => $now,
            ]);
            $this->createStatusHistory($bId, [
                ['old' => null, 'new' => 'pending', 'actor' => 'user', 'actor_id' => $userIds[$i]],
                ['old' => 'pending', 'new' => 'confirmed', 'actor' => 'admin', 'actor_id' => $adminId],
            ], $today);
            $bookingCounter++;
        }

        // 2c. 2 booking pending (chờ xác nhận)
        for ($i = 3; $i <= 4; $i++) {
            $court = $todayCourts[$i % $todayCourts->count()];
            $sH = 18 + ($i - 3);
            $eH = $sH + 1;
            $price = $this->calculatePrice($court->court_id, $today, $sH, $eH);

            $bId = DB::table('court_bookings')->insertGetId([
                'booking_code' => 'BK-'.$today->format('Ymd').'-PD'.($i - 2),
                'user_id' => $userIds[$i % count($userIds)],
                'court_id' => $court->court_id,
                'booking_date' => $today->format('Y-m-d'),
                'start_time' => sprintf('%02d:00:00', $sH),
                'end_time' => sprintf('%02d:00:00', $eH),
                'duration_minutes' => 60,
                'status' => 'pending',
                'original_price' => $price,
                'discount_amount' => 0,
                'service_amount' => 0,
                'total_amount' => $price,
                'deposit_amount' => 0,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'payment_method' => 'vnpay',
                'source' => 'web',
                'created_at' => $now->copy()->subMinutes(rand(10, 60)),
                'updated_at' => $now,
            ]);
            $this->createStatusHistory($bId, [
                ['old' => null, 'new' => 'pending', 'actor' => 'user', 'actor_id' => $userIds[$i % count($userIds)]],
            ], $today);
            $bookingCounter++;
        }

        // --- NHÓM 3: Booking ĐÃ HỦY ---
        $cancelDate = $today->copy()->subDays(2);
        for ($i = 0; $i < 2; $i++) {
            $court = $activeCourts->values()[$i];
            $sH = 14 + $i * 2;
            $eH = $sH + 1;
            $price = $this->calculatePrice($court->court_id, $cancelDate, $sH, $eH);

            $cancelReasons = [
                ['type' => 'customer_request', 'reason' => 'Có việc bận đột xuất, không thể đến được.'],
                ['type' => 'court_issue', 'reason' => 'Sân bị ngấm nước do trời mưa lớn.'],
            ];

            $bId = DB::table('court_bookings')->insertGetId([
                'booking_code' => 'BK-'.$cancelDate->format('Ymd').'-CL'.($i + 1),
                'user_id' => $userIds[$i],
                'court_id' => $court->court_id,
                'booking_date' => $cancelDate->format('Y-m-d'),
                'start_time' => sprintf('%02d:00:00', $sH),
                'end_time' => sprintf('%02d:00:00', $eH),
                'duration_minutes' => 60,
                'status' => 'cancelled',
                'original_price' => $price,
                'discount_amount' => 0,
                'service_amount' => 0,
                'total_amount' => $price,
                'deposit_amount' => 0,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'payment_method' => 'cash',
                'cancelled_at' => $cancelDate->copy()->setHour($sH)->subHour(),
                'cancel_reason_type' => $cancelReasons[$i]['type'],
                'cancel_reason' => $cancelReasons[$i]['reason'],
                'source' => 'web',
                'created_at' => $cancelDate->copy()->subDays(1),
                'updated_at' => $cancelDate,
            ]);
            $this->createStatusHistory($bId, [
                ['old' => null, 'new' => 'pending', 'actor' => 'user', 'actor_id' => $userIds[$i]],
                ['old' => 'pending', 'new' => 'cancelled', 'actor' => ($i === 0 ? 'user' : 'admin'), 'actor_id' => ($i === 0 ? $userIds[$i] : $adminId)],
            ], $cancelDate);
            $bookingCounter++;
        }

        // --- NHÓM 4: Booking NO_SHOW ---
        $noShowDate = $today->copy()->subDays(3);
        $court = $activeCourts->values()[2];
        $price = $this->calculatePrice($court->court_id, $noShowDate, 8, 9);
        $bId = DB::table('court_bookings')->insertGetId([
            'booking_code' => 'BK-'.$noShowDate->format('Ymd').'-NS01',
            'user_id' => $userIds[3],
            'court_id' => $court->court_id,
            'booking_date' => $noShowDate->format('Y-m-d'),
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => 'no_show',
            'original_price' => $price,
            'discount_amount' => 0,
            'service_amount' => 0,
            'total_amount' => $price,
            'deposit_amount' => 0,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'payment_method' => 'cash',
            'source' => 'phone',
            'note' => 'Khách không đến, không liên lạc được.',
            'created_at' => $noShowDate->copy()->subDay(),
            'updated_at' => $noShowDate,
        ]);
        $this->createStatusHistory($bId, [
            ['old' => null, 'new' => 'pending', 'actor' => 'user', 'actor_id' => $userIds[3]],
            ['old' => 'pending', 'new' => 'confirmed', 'actor' => 'admin', 'actor_id' => $adminId],
            ['old' => 'confirmed', 'new' => 'no_show', 'actor' => 'admin', 'actor_id' => $staffId],
        ], $noShowDate);
        $bookingCounter++;

        // --- NHÓM 5: Booking TƯƠNG LAI (7 ngày tới) ---
        for ($dayOffset = 1; $dayOffset <= 7; $dayOffset++) {
            $futureDate = $today->copy()->addDays($dayOffset);
            $numBookings = rand(2, 4);
            $futureSlots = [];

            for ($i = 0; $i < $numBookings; $i++) {
                $court = $activeCourts->values()[$i % $activeCourts->count()];
                $sH = $this->pickAvailableSlot($futureSlots, $court->court_id, $futureDate->format('Y-m-d'));
                if ($sH === null) {
                    continue;
                }
                $eH = $sH + rand(1, 2);
                if ($eH > 22) {
                    $eH = 22;
                }
                $futureSlots[] = ['court' => $court->court_id, 'start' => $sH, 'end' => $eH];

                $price = $this->calculatePrice($court->court_id, $futureDate, $sH, $eH);
                $status = ($i === 0) ? 'confirmed' : 'pending';

                $bId = DB::table('court_bookings')->insertGetId([
                    'booking_code' => 'BK-'.$futureDate->format('Ymd').'-'.strtoupper(Str::random(4)),
                    'user_id' => $userIds[array_rand($userIds)],
                    'court_id' => $court->court_id,
                    'booking_date' => $futureDate->format('Y-m-d'),
                    'start_time' => sprintf('%02d:00:00', $sH),
                    'end_time' => sprintf('%02d:00:00', $eH),
                    'duration_minutes' => ($eH - $sH) * 60,
                    'status' => $status,
                    'original_price' => $price,
                    'discount_amount' => 0,
                    'service_amount' => 0,
                    'total_amount' => $price,
                    'deposit_amount' => 0,
                    'paid_amount' => 0,
                    'payment_status' => 'unpaid',
                    'payment_method' => ['cash', 'vnpay', 'momo', 'bank_transfer'][array_rand(['cash', 'vnpay', 'momo', 'bank_transfer'])],
                    'source' => 'web',
                    'confirmed_at' => $status === 'confirmed' ? $now : null,
                    'created_at' => $now->copy()->subHours(rand(1, 24)),
                    'updated_at' => $now,
                ]);

                $histories = [['old' => null, 'new' => 'pending', 'actor' => 'user', 'actor_id' => $userIds[array_rand($userIds)]]];
                if ($status === 'confirmed') {
                    $histories[] = ['old' => 'pending', 'new' => 'confirmed', 'actor' => 'admin', 'actor_id' => $adminId];
                }
                $this->createStatusHistory($bId, $histories, $futureDate);
                $bookingCounter++;
            }
        }

        // --- NHÓM 6: Booking có REFUND ---
        $refundDate = $today->copy()->subDays(4);
        $court = $activeCourts->values()[1];
        $price = $this->calculatePrice($court->court_id, $refundDate, 10, 12);
        $bId = DB::table('court_bookings')->insertGetId([
            'booking_code' => 'BK-'.$refundDate->format('Ymd').'-RF01',
            'user_id' => $userIds[1],
            'court_id' => $court->court_id,
            'booking_date' => $refundDate->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'duration_minutes' => 120,
            'status' => 'cancelled',
            'original_price' => $price,
            'discount_amount' => 0,
            'service_amount' => 0,
            'total_amount' => $price,
            'deposit_amount' => $price,
            'paid_amount' => 0,
            'payment_status' => 'refunded',
            'payment_method' => 'bank_transfer',
            'cancelled_at' => $refundDate->copy()->setHour(9),
            'cancel_reason_type' => 'maintenance',
            'cancel_reason' => 'Sân bị hỏng hệ thống đèn, CLB chủ động hoàn tiền cho khách.',
            'source' => 'web',
            'created_at' => $refundDate->copy()->subDays(2),
            'updated_at' => $refundDate,
        ]);
        $this->createStatusHistory($bId, [
            ['old' => null, 'new' => 'pending', 'actor' => 'user', 'actor_id' => $userIds[1]],
            ['old' => 'pending', 'new' => 'confirmed', 'actor' => 'admin', 'actor_id' => $adminId],
            ['old' => 'confirmed', 'new' => 'cancelled', 'actor' => 'admin', 'actor_id' => $adminId],
        ], $refundDate);
        // Payment: full paid → refunded
        $this->createPayment($bId, 'full', $price, 'success', $staffId);
        $this->createPayment($bId, 'refund', -$price, 'refunded', $adminId);
        $bookingCounter++;

        echo "✅ Tổng cộng {$bookingCounter} booking demo đã tạo.\n";
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    private function clearDemoBookings(array $userIds): void
    {
        $bookingIds = DB::table('court_bookings')
            ->whereIn('user_id', $userIds)
            ->where(function ($query) {
                $query->where('booking_code', 'like', 'BK-%-PLAY')
                    ->orWhere('booking_code', 'like', 'BK-%-CF%')
                    ->orWhere('booking_code', 'like', 'BK-%-PD%')
                    ->orWhere('booking_code', 'like', 'BK-%-CL%')
                    ->orWhere('booking_code', 'like', 'BK-%-NS%')
                    ->orWhere('booking_code', 'like', 'BK-%-RF%')
                    ->orWhere('source', 'web')
                    ->orWhere('source', 'phone');
            })
            ->pluck('booking_id');

        if ($bookingIds->isEmpty()) {
            return;
        }

        DB::table('court_booking_payments')->whereIn('booking_id', $bookingIds)->delete();
        DB::table('court_booking_services')->whereIn('booking_id', $bookingIds)->delete();
        DB::table('court_booking_status_histories')->whereIn('booking_id', $bookingIds)->delete();
        DB::table('court_booking_extensions')->whereIn('booking_id', $bookingIds)->delete();
        DB::table('court_bookings')->whereIn('booking_id', $bookingIds)->delete();

        echo "🧹 Đã dọn {$bookingIds->count()} booking demo cũ.\n";
    }

    private function createAdmin(array $data): int
    {
        $existing = DB::table('admins')->where('email', $data['email'])->first();
        if ($existing) {
            echo "ℹ️  Admin {$data['email']} đã tồn tại.\n";

            return $existing->admin_id;
        }

        $id = DB::table('admins')->insertGetId([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password' => Hash::make('password'),
            'role' => $data['role'],
            'status' => $data['status'],
            'phone' => $data['phone'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "✅ Admin: {$data['email']} / password\n";

        return $id;
    }

    private function createUser(string $name, string $email, string $phone): int
    {
        $existing = DB::table('users')->where('email', $email)->first();
        if ($existing) {
            echo "ℹ️  User {$email} đã tồn tại.\n";

            return $existing->user_id;
        }

        $id = DB::table('users')->insertGetId([
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'role' => 'customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "✅ User: {$email} / password\n";

        return $id;
    }

    private function calculatePrice(int $courtId, Carbon $date, int $startH, int $endH): int
    {
        $hours = $endH - $startH;
        $dayOfWeek = $date->dayOfWeek;
        $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
        $dayType = $isWeekend ? 'weekend' : 'weekday';
        $startTime = sprintf('%02d:00:00', $startH);

        $priceRow = DB::table('court_prices')
            ->where('court_id', $courtId)
            ->where('is_active', true)
            ->where(function ($q) use ($dayType) {
                $q->where('day_type', $dayType)->orWhere('day_type', 'all');
            })
            ->where('from_time', '<=', $startTime)
            ->where('to_time', '>=', sprintf('%02d:00:00', $endH))
            ->first();

        $perHour = $priceRow ? (int) $priceRow->price_per_hour : 80000;

        return $perHour * $hours;
    }

    private function pickAvailableSlot(array &$usedSlots, int $courtId, string $date): ?int
    {
        $possibleHours = [6, 7, 8, 9, 10, 14, 15, 16, 17, 18, 19, 20];
        shuffle($possibleHours);

        foreach ($possibleHours as $h) {
            $conflict = false;
            foreach ($usedSlots as $slot) {
                if ($slot['court'] === $courtId && $h >= $slot['start'] && $h < $slot['end']) {
                    $conflict = true;
                    break;
                }
            }
            if (! $conflict) {
                return $h;
            }
        }

        return null;
    }

    private function createStatusHistory(int $bookingId, array $transitions, Carbon $baseDate): void
    {
        foreach ($transitions as $i => $t) {
            DB::table('court_booking_status_histories')->insert([
                'booking_id' => $bookingId,
                'old_status' => $t['old'],
                'new_status' => $t['new'],
                'actor_type' => $t['actor'],
                'actor_id' => $t['actor_id'],
                'note' => null,
                'meta' => null,
                'created_at' => $baseDate->copy()->addMinutes($i * 15),
            ]);
        }
    }

    private function createPayment(int $bookingId, string $type, int $amount, string $status, ?int $processedBy): void
    {
        $method = DB::table('court_bookings')->where('booking_id', $bookingId)->value('payment_method') ?? 'cash';

        DB::table('court_booking_payments')->insert([
            'booking_id' => $bookingId,
            'payment_type' => $type,
            'payment_method' => $method,
            'transaction_code' => 'TXN-'.strtoupper(Str::random(8)),
            'amount' => $amount,
            'status' => $status,
            'paid_at' => ($status === 'success' || $status === 'refunded') ? now() : null,
            'gateway_response' => null,
            'note' => null,
            'processed_by' => $processedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addRandomServices(int $bookingId, $services, ?int $staffId): void
    {
        $picked = $services->random(min(rand(1, 3), $services->count()));
        $totalServiceAmount = 0;

        foreach ($picked as $service) {
            $qty = rand(1, 3);
            $subtotal = $service->unit_price * $qty;
            $totalServiceAmount += $subtotal;

            DB::table('court_booking_services')->insert([
                'booking_id' => $bookingId,
                'service_id' => $service->service_id,
                'quantity' => $qty,
                'unit_price' => $service->unit_price,
                'subtotal' => $subtotal,
                'note' => null,
                'added_by' => $staffId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Cập nhật service_amount & total_amount trên booking
        DB::table('court_bookings')->where('booking_id', $bookingId)->update([
            'service_amount' => $totalServiceAmount,
            'total_amount' => DB::raw("original_price + {$totalServiceAmount}"),
            'paid_amount' => DB::raw("original_price + {$totalServiceAmount}"), // Completed = đã thanh toán
        ]);
    }
}
