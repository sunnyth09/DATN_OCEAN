<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnRequestStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Repositories\OrderRepository;
use App\Repositories\ReturnRequestRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReturnRequestService
{
    public function __construct(
        protected ReturnRequestRepository $returnRequestRepository,
        protected OrderRepository $orderRepository,
        protected RefundService $refundService,
        protected AffiliateService $affiliateService
    ) {}

    public function create(int $userId, int $orderId, array $data, Request $request): array
    {
        $order = $this->orderRepository->findUserOrder($userId, $orderId);

        if (!$order) {
            return $this->error('Không tìm thấy đơn hàng hoặc đơn hàng không thuộc về bạn.', 404);
        }

        $validationError = $this->validateReturnEligibility($order);
        if ($validationError) {
            return $this->error($validationError, 422);
        }

        DB::transaction(function () use ($order, $userId, $data, $request) {
            $storedImages = $this->storeEvidenceImages($request);

            $returnRequest = $this->returnRequestRepository->create([
                'order_id' => $order->order_id,
                'user_id' => $userId,
                'reason' => $data['reason'],
                'description' => $data['description'] ?? null,
                'images' => $storedImages,
                'status' => ReturnRequestStatus::PENDING->value,
                'refund_amount' => 0,
                'refund_status' => RefundStatus::NONE->value,
                'requested_at' => now(),
            ]);

            $oldStatus = $order->fulfillment_status;

            $order->update([
                'fulfillment_status' => OrderStatus::RETURN_REQUESTED->value,
            ]);

            $this->orderRepository->createStatusHistory([
                'order_id' => $order->order_id,
                'old_status' => $oldStatus,
                'new_status' => OrderStatus::RETURN_REQUESTED->value,
                'note' => 'Khách hàng gửi yêu cầu hoàn hàng: ' . $returnRequest->reason,
                'changed_by' => $userId,
            ]);
        });

        return $this->success('Đã gửi yêu cầu hoàn hàng thành công.');
    }

    public function getMyRequests(int $userId, array $filters = []): array
    {
        return $this->success('Danh sách yêu cầu hoàn hàng.', $this->returnRequestRepository->getUserRequests($userId, $filters));
    }

    public function getMyRequestDetail(int $userId, int $id): array
    {
        $returnRequest = $this->returnRequestRepository->findUserRequest($userId, $id);

        if (!$returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        return $this->success('Chi tiết yêu cầu hoàn hàng.', $returnRequest);
    }

    public function getAdminRequests(array $filters = []): array
    {
        return $this->success('Danh sách yêu cầu hoàn hàng.', $this->returnRequestRepository->getAdminRequests($filters));
    }

    public function getAdminRequestDetail(int $id): array
    {
        $returnRequest = $this->returnRequestRepository->findForAdmin($id);

        if (!$returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        return $this->success('Chi tiết yêu cầu hoàn hàng.', $returnRequest);
    }

    public function approve(int $id, array $data): array
    {
        $returnRequest = $this->returnRequestRepository->findForAdmin($id);

        if (!$returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        if ($returnRequest->status !== ReturnRequestStatus::PENDING->value) {
            return $this->error('Chỉ có thể duyệt yêu cầu đang chờ xử lý.', 422);
        }

        DB::transaction(function () use ($returnRequest, $data) {
            $returnRequest->update([
                'status' => ReturnRequestStatus::APPROVED->value,
                'admin_note' => $data['admin_note'] ?? null,
                'refund_status' => in_array($returnRequest->order->payment_status, [
                    PaymentStatus::PAID->value,
                    PaymentStatus::PARTIALLY_REFUNDED->value,
                ], true)
                    ? RefundStatus::PENDING->value
                    : $returnRequest->refund_status,
                'approved_at' => now(),
            ]);

            $this->updateOrderStatus(
                $returnRequest->order,
                OrderStatus::RETURN_APPROVED->value,
                'Admin duyệt yêu cầu hoàn hàng.'
            );

            if (in_array($returnRequest->order->payment_status, [
                PaymentStatus::PAID->value,
                PaymentStatus::PARTIALLY_REFUNDED->value,
            ], true)) {
                $this->updatePaymentStatus(
                    $returnRequest->order,
                    PaymentStatus::REFUND_PENDING->value,
                    '[Thanh toán] Chờ hoàn tiền sau khi admin duyệt hoàn hàng.'
                );
            }
        });

        return $this->success('Đã duyệt yêu cầu hoàn hàng.');
    }

    public function reject(int $id, array $data): array
    {
        $returnRequest = $this->returnRequestRepository->findForAdmin($id);

        if (!$returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        if ($returnRequest->status !== ReturnRequestStatus::PENDING->value) {
            return $this->error('Chỉ có thể từ chối yêu cầu đang chờ xử lý.', 422);
        }

        DB::transaction(function () use ($returnRequest, $data) {
            $returnRequest->update([
                'status' => ReturnRequestStatus::REJECTED->value,
                'admin_note' => $data['admin_note'],
                'rejected_at' => now(),
            ]);

            $this->updateOrderStatus(
                $returnRequest->order,
                OrderStatus::COMPLETED->value,
                'Admin từ chối yêu cầu hoàn hàng.'
            );
        });

        return $this->success('Đã từ chối yêu cầu hoàn hàng.');
    }

    public function markReceived(int $id, array $data = []): array
    {
        $returnRequest = $this->returnRequestRepository->findForAdmin($id);

        if (!$returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        if ($returnRequest->status !== ReturnRequestStatus::APPROVED->value) {
            return $this->error('Chỉ có thể xác nhận nhận hàng hoàn sau khi đã duyệt.', 422);
        }

        DB::transaction(function () use ($returnRequest, $data) {
            $returnRequest->update([
                'status' => ReturnRequestStatus::RECEIVED->value,
                'admin_note' => $data['admin_note'] ?? $returnRequest->admin_note,
                'received_at' => now(),
            ]);

            $this->updateOrderStatus(
                $returnRequest->order,
                OrderStatus::RETURNED->value,
                'Admin xác nhận đã nhận hàng hoàn.'
            );
        });

        $this->affiliateService->updateConversionOnStatusChange($returnRequest->order->fresh(), OrderStatus::RETURNED->value);

        return $this->success('Đã xác nhận nhận hàng hoàn.');
    }

    public function refund(int $id, array $data): array
    {
        // Kiểm tra sơ bộ ngoài transaction để trả message thân thiện (fail nhanh).
        $preCheck = $this->returnRequestRepository->findForAdmin($id);

        if (!$preCheck) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        try {
            DB::transaction(function () use ($id, $data) {
                // Lock chính bản ghi return request để chống double-refund do race.
                $returnRequest = ReturnRequest::whereKey($id)->lockForUpdate()->firstOrFail();

                // Re-check trạng thái BÊN TRONG lock — đây mới là guard có hiệu lực.
                if ($returnRequest->status !== ReturnRequestStatus::RECEIVED->value) {
                    throw new \App\Exceptions\OrderException('Chỉ có thể hoàn tiền sau khi đã nhận hàng hoàn.');
                }

                if ($returnRequest->refund_status === RefundStatus::SUCCESS->value) {
                    throw new \App\Exceptions\OrderException('Yêu cầu này đã được hoàn tiền trước đó.');
                }

                // Lock đơn hàng để tính tổng đã hoàn một cách nhất quán.
                $order = Order::whereKey($returnRequest->order_id)->lockForUpdate()->firstOrFail();
                $refundAmount = (float) $data['refund_amount'];

                if ($refundAmount <= 0) {
                    throw new \App\Exceptions\OrderException('Số tiền hoàn phải lớn hơn 0.');
                }

                // Chống hoàn vượt tổng đơn khi có nhiều yêu cầu trả hàng (cộng dồn).
                $alreadyRefunded = (float) ReturnRequest::where('order_id', $order->order_id)
                    ->where('refund_status', RefundStatus::SUCCESS->value)
                    ->where('id', '!=', $returnRequest->id)
                    ->sum('refund_amount');
                $remaining = (float) $order->grand_total - $alreadyRefunded;

                if ($refundAmount > $remaining) {
                    throw new \App\Exceptions\OrderException(
                        'Số tiền hoàn vượt quá phần còn lại có thể hoàn (' . number_format($remaining) . 'đ).'
                    );
                }

                if (!in_array($order->payment_status, PaymentStatus::refundableValues(), true)) {
                    throw new \App\Exceptions\OrderException('Đơn hàng hiện không ở trạng thái có thể hoàn tiền.');
                }

                // Credit ví NẰM TRONG transaction → nếu bất kỳ bước sau lỗi, tiền được rollback.
                $refundResult = $this->refundService->processManualRefund($order, $data);
                if (!$refundResult['success']) {
                    throw new \App\Exceptions\OrderException($refundResult['message']);
                }

                $returnRequest->update([
                    'status' => ReturnRequestStatus::REFUNDED->value,
                    'refund_status' => RefundStatus::SUCCESS->value,
                    'refund_amount' => $refundAmount,
                    'refund_method' => $data['refund_method'],
                    'admin_note' => $data['admin_note'] ?? $returnRequest->admin_note,
                    'refunded_at' => now(),
                ]);

                $this->updateOrderStatus(
                    $order,
                    OrderStatus::REFUNDED->value,
                    'Admin xác nhận hoàn tiền thủ công.'
                );

                $this->updatePaymentStatus(
                    $order,
                    PaymentStatus::REFUNDED->value,
                    '[Thanh toán] Đã hoàn tiền thủ công qua ' . $data['refund_method'] . '.'
                );

                $payment = Payment::where('order_id', $order->order_id)
                    ->latest('payment_id')
                    ->first();

                if ($payment) {
                    $payment->update([
                        'status' => 'refunded',
                        'gateway_response' => array_merge($payment->gateway_response ?? [], [
                            'manual_refund' => $refundResult['metadata'] ?? [],
                            'refunded_at' => now()->toDateTimeString(),
                        ]),
                    ]);
                }
            });
        } catch (\App\Exceptions\OrderException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Đã xác nhận hoàn tiền thành công.');
    }

    public function getLatestOrderReturnRequest(int $orderId)
    {
        return $this->returnRequestRepository->findLatestByOrderId($orderId);
    }

    public function canUserRequestReturn(Order $order): bool
    {
        return $this->validateReturnEligibility($order) === null;
    }

    public function getReturnWindowDays(): int
    {
        return (int) config('orders.return_request_window_days', 7);
    }

    private function validateReturnEligibility(Order $order): ?string
    {
        if (!in_array($order->fulfillment_status, OrderStatus::returnEligibleValues(), true)) {
            return 'Chỉ có thể gửi yêu cầu hoàn hàng cho đơn đã hoàn thành hoặc đã giao.';
        }

        if ($this->returnRequestRepository->findActiveByOrderId($order->order_id)) {
            return 'Đơn hàng này đang có yêu cầu hoàn hàng được xử lý.';
        }

        $latestRequest = $this->returnRequestRepository->findLatestByOrderId($order->order_id);
        if ($latestRequest && in_array($latestRequest->status, [
            ReturnRequestStatus::REFUNDED->value,
            ReturnRequestStatus::RECEIVED->value,
        ], true)) {
            return 'Đơn hàng này đã hoàn hàng hoặc hoàn tiền trước đó.';
        }

        $baseDate = $order->completed_at ?? $order->delivered_at ?? $order->created_at;
        $deadline = Carbon::parse($baseDate)->addDays($this->getReturnWindowDays());

        if (now()->greaterThan($deadline)) {
            return 'Đơn hàng đã quá thời hạn yêu cầu hoàn hàng.';
        }

        return null;
    }

    private function storeEvidenceImages(Request $request): array
    {
        $paths = [];

        if (!$request->hasFile('images')) {
            return $paths;
        }

        foreach ((array) $request->file('images') as $image) {
            if ($image) {
                $paths[] = Storage::disk('public')->put('return-requests', $image);
            }
        }

        return $paths;
    }

    private function updateOrderStatus(Order $order, string $newStatus, string $note): void
    {
        $oldStatus = $order->fulfillment_status;

        if ($oldStatus === $newStatus) {
            return;
        }

        $order->update([
            'fulfillment_status' => $newStatus,
        ]);

        $this->orderRepository->createStatusHistory([
            'order_id' => $order->order_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
        ]);
    }

    private function updatePaymentStatus(Order $order, string $newStatus, string $note): void
    {
        $oldStatus = $order->payment_status;

        if ($oldStatus === $newStatus) {
            return;
        }

        $order->update([
            'payment_status' => $newStatus,
        ]);

        $this->orderRepository->createStatusHistory([
            'order_id' => $order->order_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
        ]);
    }

    private function success(string $message, $data = null, int $statusCode = 200): array
    {
        return [
            'status_code' => $statusCode,
            'body' => [
                'status' => 'success',
                'message' => $message,
                'data' => $data,
            ],
        ];
    }

    private function error(string $message, int $statusCode): array
    {
        return [
            'status_code' => $statusCode,
            'body' => [
                'status' => 'error',
                'message' => $message,
            ],
        ];
    }
}
