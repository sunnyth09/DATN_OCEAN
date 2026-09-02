<?php

namespace App\Domain\Order;

use App\Enums\OrderActor;
use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * =====================================================================
 * OrderStateMachine — NGUỒN SỰ THẬT DUY NHẤT cho luồng trạng thái đơn hàng
 * =====================================================================
 *
 * TRƯỚC ĐÂY bản đồ transition bị nhân bản 3 chỗ (AdminOrderService,
 * AdminOrder.vue, AdminOrderDetail.vue) và đã trôi lệch khỏi nhau. Tệ hơn:
 * không chỗ nào xét tới vận đơn, nên Admin vẫn bấm được `shipping`/`delivered`
 * trong khi hãng vận chuyển chưa hề nhận hàng.
 *
 * NGUYÊN TẮC THIẾT KẾ
 * -------------------
 * 1. Mỗi transition khai báo kèm DANH SÁCH CHỦ THỂ được phép (xem OrderActor).
 * 2. Khi đơn đã có vận đơn (tracking_number / ghn_order_code) thì hãng vận
 *    chuyển là CHỦ SỞ HỮU DUY NHẤT của vòng đời giao hàng. Admin chỉ còn các
 *    quyền ngoại lệ có kiểm soát (xem `force-status`).
 * 3. `awaiting_pickup` tách biệt với `shipping`: tạo vận đơn KHÔNG đồng nghĩa
 *    hàng đã lên đường.
 * 4. Chặn transition lùi (theo `weight()`) để webhook đến trễ không kéo đơn về
 *    trạng thái cũ.
 *
 * Class này THUẦN (pure) — không truy vấn DB, không side-effect. Nhờ vậy có thể
 * dùng ở mọi tầng: service, controller, command, và cả endpoint trả bản đồ
 * transition cho frontend.
 */
class OrderStateMachine
{
    /**
     * Bản đồ transition: from => [to => [chủ thể được phép, ...]].
     *
     * Trạng thái không xuất hiện trong bản đồ (hoặc map rỗng) là trạng thái
     * kết thúc — chỉ có thể thoát bằng `force-status`.
     */
    private const TRANSITIONS = [
        OrderStatus::PENDING->value => [
            OrderStatus::CONFIRMED->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
            OrderStatus::CANCELLED->value => [OrderActor::ADMIN, OrderActor::SYSTEM, OrderActor::CARRIER],
        ],

        OrderStatus::CONFIRMED->value => [
            OrderStatus::PROCESSING->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
            OrderStatus::PACKING->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
            OrderStatus::CANCELLED->value => [OrderActor::ADMIN, OrderActor::SYSTEM, OrderActor::CARRIER],
        ],

        OrderStatus::PROCESSING->value => [
            OrderStatus::PACKING->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
            OrderStatus::CANCELLED->value => [OrderActor::ADMIN, OrderActor::SYSTEM, OrderActor::CARRIER],
            // Không có PROCESSING → SHIPPING cho ADMIN nữa: muốn giao hàng thì
            // phải tạo vận đơn (→ awaiting_pickup) rồi để hãng báo đã lấy hàng.
        ],

        OrderStatus::PACKING->value => [
            // Chỉ SYSTEM đặt được: đây là kết quả của việc tạo vận đơn thành công
            // ở hãng vận chuyển (AdminOrderService::syncGHN), không phải một nút bấm.
            OrderStatus::AWAITING_PICKUP->value => [OrderActor::SYSTEM],
            OrderStatus::CANCELLED->value => [OrderActor::ADMIN, OrderActor::SYSTEM, OrderActor::CARRIER],
        ],

        OrderStatus::AWAITING_PICKUP->value => [
            // Hãng thực sự nhận hàng → mới là SHIPPING.
            OrderStatus::SHIPPING->value => [OrderActor::CARRIER],
            OrderStatus::CANCELLED->value => [OrderActor::CARRIER, OrderActor::SYSTEM],
        ],

        OrderStatus::SHIPPING->value => [
            OrderStatus::DELIVERED->value => [OrderActor::CARRIER],
            OrderStatus::CANCELLED->value => [OrderActor::CARRIER, OrderActor::SYSTEM],
            // Giao thất bại → hãng trả hàng về kho.
            OrderStatus::RETURNING->value => [OrderActor::CARRIER],
            OrderStatus::RETURN_REQUESTED->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
        ],

        OrderStatus::DELIVERED->value => [
            OrderStatus::COMPLETED->value => [OrderActor::ADMIN, OrderActor::SYSTEM, OrderActor::CARRIER],
            OrderStatus::RETURN_REQUESTED->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
        ],

        OrderStatus::COMPLETED->value => [
            OrderStatus::RETURN_REQUESTED->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
        ],

        OrderStatus::CANCELLED->value => [],

        // ── Luồng hoàn hàng ──
        // Chặng hoàn hàng do KHO/ADMIN vận hành (shop không tự tạo vận đơn cho
        // hàng khách gửi về), nên chủ thể ở đây là ADMIN — trừ RETURNING vốn có
        // thể do hãng báo khi giao thất bại.
        OrderStatus::RETURN_REQUESTED->value => [
            OrderStatus::RETURN_APPROVED->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
            OrderStatus::RETURN_REJECTED->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
        ],

        OrderStatus::RETURN_APPROVED->value => [
            OrderStatus::RETURNING->value => [OrderActor::ADMIN, OrderActor::SYSTEM, OrderActor::CARRIER],
        ],

        OrderStatus::RETURNING->value => [
            OrderStatus::WAREHOUSE_RECEIVED->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
            OrderStatus::RETURNED->value => [OrderActor::CARRIER],
        ],

        OrderStatus::WAREHOUSE_RECEIVED->value => [
            OrderStatus::INSPECTED_OK->value => [OrderActor::ADMIN],
            OrderStatus::INSPECTION_FAILED->value => [OrderActor::ADMIN],
        ],

        OrderStatus::INSPECTED_OK->value => [
            OrderStatus::RETURNED->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
        ],

        OrderStatus::INSPECTION_FAILED->value => [
            OrderStatus::RETURN_REJECTED->value => [OrderActor::ADMIN],
        ],

        OrderStatus::RETURN_REJECTED->value => [],

        OrderStatus::RETURNED->value => [
            OrderStatus::REFUNDED->value => [OrderActor::ADMIN, OrderActor::SYSTEM],
        ],

        OrderStatus::REFUNDED->value => [],
    ];

    /**
     * Trạng thái thuộc quyền sở hữu của hãng vận chuyển: khi đơn đã có vận đơn,
     * CHỈ webhook/sync của hãng mới được đặt các trạng thái này.
     */
    private const CARRIER_OWNED = [
        OrderStatus::SHIPPING->value,
        OrderStatus::DELIVERED->value,
    ];

    /**
     * "Trọng số" tiến độ — dùng để chặn transition lùi khi webhook đến trễ /
     * sai thứ tự. Luồng hoàn hàng cố tình KHÔNG có trọng số (trả null) vì nó là
     * nhánh riêng, không so sánh được tuyến tính với luồng giao hàng.
     */
    private const WEIGHTS = [
        OrderStatus::PENDING->value => 10,
        OrderStatus::CONFIRMED->value => 20,
        OrderStatus::PROCESSING->value => 30,
        OrderStatus::PACKING->value => 40,
        OrderStatus::AWAITING_PICKUP->value => 45,
        OrderStatus::SHIPPING->value => 50,
        OrderStatus::DELIVERED->value => 60,
        OrderStatus::COMPLETED->value => 70,
    ];

    /** status => cột timestamp tương ứng trên bảng orders. */
    private const TIMESTAMP_FIELDS = [
        OrderStatus::CONFIRMED->value => 'confirmed_at',
        OrderStatus::PROCESSING->value => 'processing_at',
        OrderStatus::PACKING->value => 'packing_at',
        OrderStatus::AWAITING_PICKUP->value => 'packing_at',
        OrderStatus::SHIPPING->value => 'shipped_at',
        OrderStatus::DELIVERED->value => 'delivered_at',
        OrderStatus::COMPLETED->value => 'completed_at',
        OrderStatus::CANCELLED->value => 'cancelled_at',
    ];

    /**
     * Danh sách trạng thái mà `$actor` được phép chuyển tới từ trạng thái hiện
     * tại của `$order`. Đây là hàm mà frontend nên hỏi thay vì tự hardcode.
     *
     * @return array<int, string>
     */
    public function allowedTransitions(Order $order, OrderActor $actor): array
    {
        $from = (string) $order->fulfillment_status;
        $targets = array_keys(self::TRANSITIONS[$from] ?? []);

        return array_values(array_filter(
            $targets,
            fn (string $to) => $this->validate($order, $to, $actor) === null
        ));
    }

    public function can(Order $order, string $to, OrderActor $actor): bool
    {
        return $this->validate($order, $to, $actor) === null;
    }

    /**
     * Kiểm tra một transition. Trả về `null` nếu hợp lệ, hoặc thông điệp lỗi
     * (tiếng Việt, hiển thị được cho admin) nếu không.
     */
    public function validate(Order $order, string $to, OrderActor $actor): ?string
    {
        $from = (string) $order->fulfillment_status;

        if (OrderStatus::tryFrom($to) === null) {
            return "Trạng thái '{$to}' không tồn tại.";
        }

        if ($from === $to) {
            $label = OrderStatus::tryFrom($from)?->label() ?? $from;

            return "Đơn hàng đang ở trạng thái '{$label}' rồi. Vui lòng chọn trạng thái tiếp theo!";
        }

        $edge = self::TRANSITIONS[$from][$to] ?? null;
        if ($edge === null) {
            return sprintf(
                "Không thể chuyển từ '%s' sang '%s'. Vui lòng thực hiện theo đúng quy trình!",
                OrderStatus::tryFrom($from)?->label() ?? $from,
                OrderStatus::tryFrom($to)?->label() ?? $to,
            );
        }

        if (! in_array($actor, $edge, true)) {
            return $this->actorRejectionMessage($to, $actor);
        }

        // ── Ràng buộc theo vận đơn ──
        $hasWaybill = self::hasWaybill($order);

        if ($actor === OrderActor::ADMIN && $hasWaybill) {
            if (in_array($to, self::CARRIER_OWNED, true)) {
                return 'Đơn hàng đã có vận đơn — trạng thái giao hàng do hãng vận chuyển cập nhật tự động. Quản trị viên không thể đặt thủ công.';
            }

            if (in_array($to, [
                OrderStatus::PENDING->value,
                OrderStatus::CONFIRMED->value,
                OrderStatus::PROCESSING->value,
                OrderStatus::PACKING->value,
                OrderStatus::AWAITING_PICKUP->value,
            ], true)) {
                return 'Đơn hàng đã được bàn giao cho đối tác vận chuyển. Không thể chuyển về các bước xử lý nội bộ.';
            }

            // Huỷ đơn đã có vận đơn: phải huỷ vận đơn ở hãng trước, nếu không hàng
            // vẫn được giao trong khi hệ thống đã hoàn tiền + hoàn tồn kho.
            if ($to === OrderStatus::CANCELLED->value) {
                return 'Đơn hàng đã có vận đơn. Vui lòng huỷ vận đơn ở hãng vận chuyển trước khi huỷ đơn hàng.';
            }
        }

        // Chặn lùi tiến độ trong luồng giao hàng.
        $fromWeight = self::WEIGHTS[$from] ?? null;
        $toWeight = self::WEIGHTS[$to] ?? null;
        if ($fromWeight !== null && $toWeight !== null && $toWeight < $fromWeight) {
            return 'Không thể chuyển đơn hàng về trạng thái trước đó.';
        }

        return null;
    }

    private function actorRejectionMessage(string $to, OrderActor $actor): string
    {
        $label = OrderStatus::tryFrom($to)?->label() ?? $to;

        if ($actor === OrderActor::ADMIN && in_array($to, self::CARRIER_OWNED, true)) {
            return "Trạng thái '{$label}' chỉ được cập nhật bởi hãng vận chuyển (qua webhook). Quản trị viên không thể đặt thủ công.";
        }

        if ($actor === OrderActor::ADMIN && $to === OrderStatus::AWAITING_PICKUP->value) {
            return 'Trạng thái này được đặt tự động khi tạo vận đơn thành công. Vui lòng dùng chức năng "Tạo vận đơn".';
        }

        return "{$actor->label()} không có quyền chuyển đơn hàng sang trạng thái '{$label}'.";
    }

    /** Đơn đã có vận đơn ở đối tác thứ 3 (GHN / Ocean Express)? */
    public static function hasWaybill(Order $order): bool
    {
        return (! empty($order->tracking_number) && $order->tracking_number !== 'SELF-DELIVERY')
            || ! empty($order->ghn_order_code);
    }

    public static function isCarrierOwned(string $status): bool
    {
        return in_array($status, self::CARRIER_OWNED, true);
    }

    /** Cột timestamp cần set khi vào trạng thái này (null nếu không có). */
    public static function timestampField(string $status): ?string
    {
        return self::TIMESTAMP_FIELDS[$status] ?? null;
    }

    /** Trọng số tiến độ; null nếu trạng thái nằm ngoài luồng giao hàng tuyến tính. */
    public static function weight(string $status): ?int
    {
        return self::WEIGHTS[$status] ?? null;
    }

    /**
     * Toàn bộ bản đồ transition kèm chủ thể — phục vụ endpoint metadata cho FE
     * và test. Trả về from => to => ['admin', 'carrier', ...].
     *
     * @return array<string, array<string, array<int, string>>>
     */
    public static function graph(): array
    {
        $graph = [];
        foreach (self::TRANSITIONS as $from => $edges) {
            foreach ($edges as $to => $actors) {
                $graph[$from][$to] = array_map(fn (OrderActor $a) => $a->value, $actors);
            }
            $graph[$from] ??= [];
        }

        return $graph;
    }
}
