<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Enums\ReturnRequestStatus;
use App\Exceptions\OrderException;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\RefundTransaction;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Repositories\OrderRepository;
use App\Repositories\ReturnRequestRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $storedImages = [];
        $storedVideos = [];

        try {
            // Validate eligibility before uploading files to save bandwidth & I/O
            $order = Order::with(['items.product', 'items.variant'])
                ->where('order_id', $orderId)
                ->where('user_id', $userId)
                ->first();

            if (!$order) {
                throw new OrderException('Không tìm thấy đơn hàng hoặc đơn hàng không thuộc về bạn.');
            }

            $validationError = $this->validateReturnEligibility($order, false);
            if ($validationError) {
                throw new OrderException($validationError);
            }

            $idempotencyKey = $data['idempotency_key'] ?? null;
            if ($idempotencyKey) {
                $existing = ReturnRequest::where('user_id', $userId)
                    ->where('order_id', $orderId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->with(['items', 'order', 'refundTransactions'])
                    ->first();

                if ($existing) {
                    return $this->success('Đã gửi yêu cầu hoàn hàng thành công.', $existing);
                }
            }

            // Upload files OUTSIDE of transaction to prevent long database locks
            $storedImages = $this->storeEvidenceFiles($request, 'images', 'return-requests/images');
            $storedVideos = $this->storeEvidenceFiles($request, 'videos', 'return-requests/videos');

            $created = DB::transaction(function () use ($userId, $order, $data, $storedImages, $storedVideos, $idempotencyKey) {
                // Re-fetch with lock inside transaction to ensure atomicity
                $orderLocked = Order::where('order_id', $order->order_id)->lockForUpdate()->first();
                $validationError = $this->validateReturnEligibility($orderLocked, false);
                if ($validationError) {
                    throw new OrderException($validationError);
                }

                $itemsPayload = collect($data['items'] ?? [])
                    ->groupBy('order_item_id')
                    ->map(fn ($items) => (int) $items->sum('quantity'));

                if ($itemsPayload->isEmpty()) {
                    throw new OrderException('Vui lòng chọn sản phẩm cần hoàn.');
                }

                $orderItems = $order->items->keyBy('order_item_id');
                $returnItems = [];
                $estimatedRefund = 0;

                foreach ($itemsPayload as $orderItemId => $quantity) {
                    /** @var OrderItem|null $orderItem */
                    $orderItem = $orderItems->get((int) $orderItemId);
                    if (!$orderItem) {
                        throw new OrderException('Sản phẩm hoàn hàng không thuộc đơn hàng này.');
                    }

                    if ($quantity <= 0) {
                        throw new OrderException('Số lượng hoàn phải lớn hơn 0.');
                    }

                    $available = $this->getAvailableReturnQuantity($orderItem);
                    if ($quantity > $available) {
                        throw new OrderException("Số lượng hoàn của {$orderItem->product_name} vượt quá số lượng còn được hoàn ({$available}).");
                    }

                    $unitPrice = (float) $orderItem->unit_price;
                    $lineRefund = min((float) $orderItem->line_total, $unitPrice * $quantity);
                    $estimatedRefund += $lineRefund;

                    $returnItems[] = [
                        'order_item' => $orderItem,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'refundable_amount' => $lineRefund,
                    ];
                }

                $returnRequest = $this->returnRequestRepository->create([
                    'return_code' => $this->generateReturnCode(),
                    'order_id' => $order->order_id,
                    'user_id' => $userId,
                    'reason' => $data['reason'],
                    'description' => $data['description'] ?? null,
                    'images' => $storedImages,
                    'videos' => $storedVideos,
                    'return_shipping_method' => $data['return_shipping_method'],
                    'return_pickup_name' => $order->recipient_name,
                    'return_pickup_phone' => $order->recipient_phone,
                    'return_pickup_address' => $order->shipping_address,
                    'return_pickup_province_code' => $order->province_code,
                    'return_pickup_district_code' => $order->district_code,
                    'return_pickup_ward_code' => $order->ward_code,
                    'status' => ReturnRequestStatus::PENDING->value,
                    'refund_amount' => $estimatedRefund,
                    'refund_method' => $data['refund_method'],
                    'refund_status' => RefundStatus::NONE->value,
                    'idempotency_key' => $idempotencyKey,
                    'requested_at' => now(),
                ]);

                foreach ($returnItems as $item) {
                    /** @var OrderItem $orderItem */
                    $orderItem = $item['order_item'];
                    ReturnRequestItem::create([
                        'return_request_id' => $returnRequest->id,
                        'order_item_id' => $orderItem->order_item_id,
                        'product_id' => $orderItem->product_id,
                        'variant_id' => $orderItem->variant_id,
                        'ordered_quantity' => $orderItem->quantity,
                        'requested_quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'refundable_amount' => $item['refundable_amount'],
                    ]);
                }

                $oldStatus = $orderLocked->fulfillment_status;
                $orderLocked->update(['fulfillment_status' => OrderStatus::RETURN_REQUESTED->value]);

                $this->orderRepository->createStatusHistory([
                    'order_id' => $orderLocked->order_id,
                    'old_status' => $oldStatus,
                    'new_status' => OrderStatus::RETURN_REQUESTED->value,
                    'note' => 'Khách hàng gửi yêu cầu hoàn hàng: ' . $returnRequest->reason,
                    'changed_by' => $userId,
                ]);

                return $returnRequest->load(['items.orderItem', 'items.product', 'items.variant', 'order', 'refundTransactions']);
            });

            return $this->success('Đã gửi yêu cầu hoàn hàng thành công.', $created);
        } catch (OrderException $e) {
            $this->cleanupFiles($storedImages, $storedVideos);
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            $this->cleanupFiles($storedImages, $storedVideos);
            Log::error('Return Request creation failed: '.$e->getMessage());
            return $this->error('Đã xảy ra lỗi hệ thống, vui lòng thử lại sau.', 500);
        }
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

        if ($this->normalizeStatus($returnRequest->status) !== ReturnRequestStatus::PENDING->value) {
            return $this->error('Chỉ có thể duyệt yêu cầu đang chờ xử lý.', 422);
        }

        $message = 'Đã duyệt yêu cầu hoàn hàng.';

        DB::transaction(function () use ($returnRequest, $data, &$message) {
            $returnRequest->update([
                'status' => ReturnRequestStatus::APPROVED->value,
                'admin_note' => $data['admin_note'] ?? null,
                'return_tracking_code' => $data['return_tracking_code'] ?? $returnRequest->return_tracking_code,
                'return_carrier' => $data['return_carrier'] ?? $returnRequest->return_carrier,
                'approved_at' => now(),
            ]);

            if ($returnRequest->return_shipping_method === 'pickup_original_address' && !$returnRequest->return_tracking_code) {
                try {
                    $ghnResponse = GHNService::createReturnOrder($returnRequest->fresh(['items.orderItem.product', 'items.product', 'order']));
                    $ghnOrderCode = data_get($ghnResponse, 'data.order_code')
                        ?? data_get($ghnResponse, 'order_code')
                        ?? data_get($ghnResponse, 'data.orderCode');

                    $returnRequest->update([
                        'return_carrier' => 'GHN',
                        'return_tracking_code' => $ghnOrderCode,
                        'return_ghn_order_code' => $ghnOrderCode,
                        'return_ghn_response' => $ghnResponse,
                        'return_label_created_at' => now(),
                    ]);

                    $message = 'Đã duyệt yêu cầu hoàn hàng và tạo vận đơn lấy hàng hoàn.';
                } catch (\Throwable $e) {
                    Log::warning('Không thể tạo vận đơn hoàn hàng khi duyệt yêu cầu', [
                        'return_request_id' => $returnRequest->id,
                        'error' => $e->getMessage(),
                    ]);
                    $message = 'Đã duyệt yêu cầu hoàn hàng. Chưa tạo được vận đơn lấy hàng, vui lòng xử lý thủ công.';
                }
            } elseif ($returnRequest->return_shipping_method === 'dropoff_post_office') {
                $message = 'Đã duyệt yêu cầu hoàn hàng. Khách sẽ tự gửi hàng lên bưu cục.';
            }

            $this->updateOrderStatus($returnRequest->order, OrderStatus::RETURN_APPROVED->value, 'Admin duyệt yêu cầu hoàn hàng.');
        });

        return $this->success($message);
    }

    public function reject(int $id, array $data): array
    {
        $returnRequest = $this->returnRequestRepository->findForAdmin($id);
        if (!$returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        if ($this->normalizeStatus($returnRequest->status) !== ReturnRequestStatus::PENDING->value) {
            return $this->error('Chỉ có thể từ chối yêu cầu đang chờ xử lý.', 422);
        }

        DB::transaction(function () use ($returnRequest, $data) {
            $returnRequest->update([
                'status' => ReturnRequestStatus::REJECTED->value,
                'admin_note' => $data['admin_note'],
                'reject_reason' => $data['admin_note'],
                'rejected_at' => now(),
            ]);

            $fallback = $returnRequest->order->completed_at ? OrderStatus::COMPLETED->value : OrderStatus::DELIVERED->value;
            $this->updateOrderStatus($returnRequest->order, $fallback, 'Admin từ chối yêu cầu hoàn hàng.');
        });

        return $this->success('Đã từ chối yêu cầu hoàn hàng.');
    }

    public function markReturning(int $id, array $data = []): array
    {
        $returnRequest = $this->returnRequestRepository->findForAdmin($id);
        if (!$returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        if ($this->normalizeStatus($returnRequest->status) !== ReturnRequestStatus::APPROVED->value) {
            return $this->error('Chỉ có thể chuyển sang khách đang gửi hàng sau khi đã duyệt.', 422);
        }

        DB::transaction(function () use ($returnRequest, $data) {
            $returnRequest->update([
                'status' => ReturnRequestStatus::RETURNING->value,
                'return_tracking_code' => $data['return_tracking_code'] ?? $returnRequest->return_tracking_code,
                'return_carrier' => $data['return_carrier'] ?? $returnRequest->return_carrier,
                'admin_note' => $data['admin_note'] ?? $returnRequest->admin_note,
                'returning_at' => now(),
            ]);

            $this->updateOrderStatus($returnRequest->order, OrderStatus::RETURNING->value, 'Khách đang gửi hàng hoàn.');
        });

        return $this->success('Đã cập nhật trạng thái khách đang gửi hàng hoàn.');
    }

    public function markReceived(int $id, array $data = []): array
    {
        $returnRequest = $this->returnRequestRepository->findForAdmin($id);
        if (!$returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        if (!in_array($this->normalizeStatus($returnRequest->status), [
            ReturnRequestStatus::APPROVED->value,
            ReturnRequestStatus::RETURNING->value,
        ], true)) {
            return $this->error('Chỉ có thể xác nhận nhận hàng hoàn sau khi đã duyệt hoặc đang gửi hàng.', 422);
        }

        DB::transaction(function () use ($returnRequest, $data) {
            $itemsById = collect($data['items'] ?? [])->keyBy('return_request_item_id');
            foreach ($returnRequest->items as $item) {
                $received = (int) ($itemsById->get($item->id)['received_quantity'] ?? $item->requested_quantity);
                $item->update(['received_quantity' => min($received, $item->requested_quantity)]);
            }

            $returnRequest->update([
                'status' => ReturnRequestStatus::WAREHOUSE_RECEIVED->value,
                'admin_note' => $data['admin_note'] ?? $returnRequest->admin_note,
                'received_at' => now(),
                'warehouse_received_at' => now(),
            ]);

            $this->updateOrderStatus($returnRequest->order, OrderStatus::WAREHOUSE_RECEIVED->value, 'Kho xác nhận đã nhận hàng hoàn.');
        });

        return $this->success('Đã xác nhận kho nhận hàng hoàn.');
    }

    public function inspect(int $id, array $data): array
    {
        $returnRequest = $this->returnRequestRepository->findForAdmin($id);
        if (!$returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        if ($this->normalizeStatus($returnRequest->status) !== ReturnRequestStatus::WAREHOUSE_RECEIVED->value) {
            return $this->error('Chỉ có thể QC sau khi kho đã nhận hàng hoàn.', 422);
        }

        try {
            DB::transaction(function () use ($id, $data) {
                $returnRequest = ReturnRequest::with(['items.orderItem', 'order'])
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $payloadById = collect($data['items'] ?? [])->keyBy('return_request_item_id');
                $totalPass = 0;
                $refundAmount = 0;

                foreach ($returnRequest->items as $item) {
                    $payload = $payloadById->get($item->id);
                    if (!$payload) {
                        throw new OrderException('Vui lòng nhập kết quả QC cho tất cả sản phẩm hoàn.');
                    }

                    $pass = (int) ($payload['qc_pass_quantity'] ?? 0);
                    $fail = (int) ($payload['qc_fail_quantity'] ?? 0);
                    $received = (int) $item->received_quantity;

                    if ($pass < 0 || $fail < 0 || ($pass + $fail) > $received) {
                        throw new OrderException('Số lượng QC không hợp lệ.');
                    }

                    $qcStatus = match (true) {
                        $pass > 0 && $fail > 0 => 'partial',
                        $pass > 0 => 'passed',
                        default => 'failed',
                    };

                    $itemRefund = $item->requested_quantity > 0
                        ? round(((float) $item->refundable_amount / $item->requested_quantity) * $pass, 2)
                        : 0;

                    $item->update([
                        'qc_pass_quantity' => $pass,
                        'qc_fail_quantity' => $fail,
                        'qc_status' => $qcStatus,
                        'qc_note' => $payload['qc_note'] ?? null,
                        'refundable_amount' => $itemRefund,
                    ]);

                    if ($pass > 0 && !$item->inventory_updated_at) {
                        $this->restoreInventoryForReturnItem($item, $pass);
                    }

                    $totalPass += $pass;
                    $refundAmount += $itemRefund;
                }

                $newStatus = $totalPass > 0
                    ? ReturnRequestStatus::INSPECTED_OK->value
                    : ReturnRequestStatus::INSPECTION_FAILED->value;

                $returnRequest->update([
                    'status' => $newStatus,
                    'refund_amount' => $refundAmount,
                    'inspection_note' => $data['inspection_note'] ?? null,
                    'inspected_at' => now(),
                ]);

                $this->updateOrderStatus(
                    $returnRequest->order,
                    $totalPass > 0 ? OrderStatus::INSPECTED_OK->value : OrderStatus::INSPECTION_FAILED->value,
                    $totalPass > 0 ? 'QC hàng hoàn đạt.' : 'QC hàng hoàn không đạt.'
                );
            });
        } catch (OrderException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Đã cập nhật kết quả QC hàng hoàn.');
    }

    public function refund(int $id, array $data): array
    {
        $message = 'Đã xác nhận hoàn tiền thành công.';

        try {
            DB::transaction(function () use ($id, $data, &$message) {
                $returnRequest = ReturnRequest::with(['order', 'refundTransactions'])
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!in_array($this->normalizeStatus($returnRequest->status), ReturnRequestStatus::refundableValues(), true)) {
                    throw new OrderException('Chỉ có thể hoàn tiền sau khi QC đạt hoặc khi retry hoàn tiền.');
                }

                if ($returnRequest->refundTransactions()->where('status', 'success')->exists()) {
                    throw new OrderException('Yêu cầu này đã được hoàn tiền trước đó.');
                }

                $order = Order::whereKey($returnRequest->order_id)->lockForUpdate()->firstOrFail();
                if (!in_array($order->payment_status, PaymentStatus::refundableValues(), true)) {
                    throw new OrderException('Đơn hàng hiện không ở trạng thái có thể hoàn tiền.');
                }

                $refundAmount = (float) ($data['refund_amount'] ?? $returnRequest->refund_amount);
                $refundAmount = min($refundAmount, (float) $returnRequest->refund_amount);
                if ($refundAmount <= 0) {
                    throw new OrderException('Số tiền hoàn phải lớn hơn 0.');
                }

                $alreadyRefunded = (float) RefundTransaction::where('order_id', $order->order_id)
                    ->where('status', 'success')
                    ->sum('amount');
                $remaining = (float) $order->grand_total - $alreadyRefunded;
                if ($refundAmount > $remaining) {
                    throw new OrderException('Số tiền hoàn vượt quá phần còn lại có thể hoàn (' . number_format($remaining) . 'đ).');
                }

                $method = $data['refund_method'] ?? $returnRequest->refund_method;
                $idempotencyKey = $data['idempotency_key'] ?? "return_request:{$returnRequest->id}:refund:{$method}:{$refundAmount}";

                $transaction = RefundTransaction::firstOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    [
                        'return_request_id' => $returnRequest->id,
                        'order_id' => $order->order_id,
                        'payment_id' => Payment::where('order_id', $order->order_id)->latest('payment_id')->value('payment_id'),
                        'gateway' => $method === RefundMethod::VNPAY->value ? 'vnpay' : 'manual',
                        'method' => $method,
                        'amount' => $refundAmount,
                        'status' => 'processing',
                        'attempt_count' => 0,
                        'requested_by' => auth('admin')->id(),
                    ]
                );

                if ($transaction->status === 'success') {
                    return;
                }

                $transaction->update(['status' => 'processing']);
                $transaction->increment('attempt_count');
                $returnRequest->update([
                    'status' => ReturnRequestStatus::REFUNDING->value,
                    'refund_status' => RefundStatus::PENDING->value,
                    'refund_started_at' => now(),
                    'refund_method' => $method,
                ]);
                $this->updatePaymentStatus($order, PaymentStatus::REFUND_PENDING->value, '[Thanh toán] Đang xử lý hoàn tiền.');

                if ($method === RefundMethod::VNPAY->value) {
                    $transaction->update([
                        'gateway' => 'vnpay',
                        'status' => 'processing',
                        'gateway_response' => [
                            'message' => 'VNPay refund API chưa được cấu hình; yêu cầu đang chờ xử lý thủ công/đối soát.',
                        ],
                    ]);
                    $returnRequest->update([
                        'status' => ReturnRequestStatus::REFUND_PENDING->value,
                        'refund_status' => RefundStatus::PENDING->value,
                        'admin_note' => $data['admin_note'] ?? $returnRequest->admin_note,
                    ]);
                    $message = 'Đã ghi nhận yêu cầu hoàn tiền VNPay ở trạng thái chờ xử lý.';

                    return;
                }

                $refundResult = $this->refundService->processManualRefund($order, [
                    'refund_amount' => $refundAmount,
                    'refund_method' => $method,
                    'refund_transaction_id' => $transaction->id,
                    'return_request_id' => $returnRequest->id,
                ]);

                if (!$refundResult['success']) {
                    $transaction->update([
                        'status' => 'failed',
                        'failure_reason' => $refundResult['message'] ?? 'Hoàn tiền thất bại.',
                        'gateway_response' => $refundResult['metadata'] ?? null,
                        'processed_at' => now(),
                    ]);
                    $returnRequest->update([
                        'status' => ReturnRequestStatus::REFUND_FAILED->value,
                        'refund_status' => RefundStatus::FAILED->value,
                        'refund_failed_at' => now(),
                    ]);
                    $this->updatePaymentStatus($order, PaymentStatus::REFUND_FAILED->value, '[Thanh toán] Hoàn tiền thất bại.');
                    throw new OrderException($refundResult['message']);
                }

                $transaction->update([
                    'status' => 'success',
                    'gateway_response' => $refundResult['metadata'] ?? null,
                    'processed_at' => now(),
                ]);

                $returnRequest->update([
                    'status' => ReturnRequestStatus::COMPLETED->value,
                    'refund_status' => RefundStatus::SUCCESS->value,
                    'refund_amount' => $refundAmount,
                    'admin_note' => $data['admin_note'] ?? $returnRequest->admin_note,
                    'refunded_at' => now(),
                    'completed_at' => now(),
                ]);

                $totalRefunded = (float) RefundTransaction::where('order_id', $order->order_id)->where('status', 'success')->sum('amount');
                $paymentStatus = $totalRefunded >= (float) $order->grand_total
                    ? PaymentStatus::REFUNDED->value
                    : PaymentStatus::PARTIALLY_REFUNDED->value;

                $this->updateOrderStatus($order, OrderStatus::REFUNDED->value, 'Admin xác nhận hoàn tiền thủ công.');
                $this->updatePaymentStatus($order, $paymentStatus, '[Thanh toán] Đã hoàn tiền thủ công qua ' . $method . '.');

                $payment = Payment::where('order_id', $order->order_id)->latest('payment_id')->first();
                if ($payment && $paymentStatus === PaymentStatus::REFUNDED->value) {
                    $payment->update(['status' => 'refunded']);
                }
            });
        } catch (OrderException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($message);
    }

    public function getLatestOrderReturnRequest(int $orderId)
    {
        return $this->returnRequestRepository->findLatestByOrderId($orderId);
    }

    public function canUserRequestReturn(Order $order): bool
    {
        return $this->validateReturnEligibility($order, true) === null;
    }

    public function getReturnWindowDays(): int
    {
        return (int) config('orders.return_request_window_days', 7);
    }

    private function validateReturnEligibility(Order $order, bool $checkAnyAvailableItem = false): ?string
    {
        if (!in_array($order->fulfillment_status, OrderStatus::returnEligibleValues(), true)) {
            return 'Chỉ có thể gửi yêu cầu hoàn hàng cho đơn đã hoàn thành hoặc đã giao.';
        }

        $baseDate = $order->completed_at ?? $order->delivered_at ?? $order->created_at;
        $deadline = Carbon::parse($baseDate)->addDays($this->getReturnWindowDays());
        if (now()->greaterThan($deadline)) {
            return 'Đơn hàng đã quá thời hạn yêu cầu hoàn hàng.';
        }

        if ($checkAnyAvailableItem) {
            $order->loadMissing('items');
            $hasAvailable = $order->items->contains(fn (OrderItem $item) => $this->getAvailableReturnQuantity($item) > 0);
            if (!$hasAvailable) {
                return 'Đơn hàng không còn sản phẩm có thể hoàn.';
            }
        }

        return null;
    }

    private function getAvailableReturnQuantity(OrderItem $orderItem): int
    {
        $activeQuantity = (int) ReturnRequestItem::where('order_item_id', $orderItem->order_item_id)
            ->whereHas('returnRequest', function ($query) {
                $query->whereIn('status', ReturnRequestStatus::activeValues());
            })
            ->sum('requested_quantity');

        return max(0, (int) $orderItem->quantity - (int) ($orderItem->returned_quantity ?? 0) - $activeQuantity);
    }

    private function restoreInventoryForReturnItem(ReturnRequestItem $item, int $quantity): void
    {
        if (!$item->variant_id || $quantity <= 0) {
            return;
        }

        ProductVariant::where('variant_id', $item->variant_id)->lockForUpdate()->increment('stock', $quantity);

        InventoryTransaction::create([
            'variant_id' => $item->variant_id,
            'transaction_type' => 'return',
            'quantity' => $quantity,
            'reference_type' => 'order',
            'reference_id' => $item->return_request_id,
            'note' => 'Cộng tồn kho từ hoàn hàng #' . $item->returnRequest?->return_code,
            'created_by' => $item->returnRequest?->user_id,
        ]);

        $item->orderItem?->increment('returned_quantity', $quantity);
        $item->update(['inventory_updated_at' => now()]);
    }

    private function storeEvidenceFiles(Request $request, string $field, string $directory): array
    {
        $paths = [];
        if (!$request->hasFile($field)) {
            return $paths;
        }

        foreach ((array) $request->file($field) as $file) {
            if ($file) {
                $paths[] = Storage::disk('public')->put($directory, $file);
            }
        }

        return $paths;
    }

    private function generateReturnCode(): string
    {
        do {
            $code = 'RR' . now()->format('ymdHis') . strtoupper(Str::random(4));
        } while (ReturnRequest::where('return_code', $code)->exists());

        return $code;
    }

    private function normalizeStatus(?string $status): ?string
    {
        return ReturnRequestStatus::normalize($status);
    }

    private function updateOrderStatus(Order $order, string $newStatus, string $note): void
    {
        $oldStatus = $order->fulfillment_status;
        if ($oldStatus === $newStatus) {
            return;
        }

        $order->update(['fulfillment_status' => $newStatus]);
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

        $order->update(['payment_status' => $newStatus]);
        $this->orderRepository->createStatusHistory([
            'order_id' => $order->order_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
        ]);
    }

    private function cleanupFiles(array $images, array $videos): void
    {
        try {
            foreach ($images as $img) {
                $path = str_replace(Storage::url(''), '', $img);
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            foreach ($videos as $vid) {
                $path = str_replace(Storage::url(''), '', $vid);
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ReturnRequest cleanupFiles failed: ' . $e->getMessage());
        }
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
