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
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundTransaction;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Repositories\OrderRepository;
use App\Repositories\ReturnRequestRepository;
use App\Services\OceanExpressService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

            if (! $order) {
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
                    if (! $orderItem) {
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
                    'note' => 'Khách hàng gửi yêu cầu hoàn hàng: '.$returnRequest->reason,
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

        if (! $returnRequest) {
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

        if (! $returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        return $this->success('Chi tiết yêu cầu hoàn hàng.', $returnRequest);
    }

    public function approve(int $id, array $data): array
    {
        $returnRequest = $this->returnRequestRepository->findForAdmin($id);
        if (! $returnRequest) {
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
                'return_carrier' => $data['return_carrier'] ?? ($returnRequest->return_carrier ?: 'ocean_express'),
                'approved_at' => now(),
            ]);

            if ($returnRequest->return_shipping_method === 'pickup_original_address' && ! $returnRequest->return_tracking_code) {
                try {
                    $orderData = $this->buildOceanExpressOrderPayload($returnRequest);
                    $oeResult = OceanExpressService::createOrderDetailed($orderData);

                    if ($oeResult['success'] && ! empty($oeResult['tracking_number'])) {
                        $trackingCode = $oeResult['tracking_number'];
                        $returnRequest->update([
                            'return_carrier' => 'ocean_express',
                            'return_tracking_code' => $trackingCode,
                            'return_ghn_order_code' => $trackingCode,
                            'return_ghn_response' => $oeResult['data'] ?? [],
                            'return_label_created_at' => now(),
                        ]);

                        $message = "Đã duyệt yêu cầu hoàn hàng và tạo vận đơn thu hồi Ocean Express ({$trackingCode}).";
                    } else {
                        $errorMsg = $oeResult['error'] ?? 'Không nhận được mã vận đơn từ Ocean Express';
                        Log::warning('Không thể tạo vận đơn hoàn hàng Ocean Express khi duyệt', [
                            'return_request_id' => $returnRequest->id,
                            'error' => $errorMsg,
                        ]);
                        $message = "Đã duyệt yêu cầu hoàn hàng. Lưu ý: {$errorMsg}. Bạn có thể đẩy lại vận đơn trong trang chi tiết.";
                    }
                } catch (\Throwable $e) {
                    Log::warning('Lỗi ngoại lệ khi tạo vận đơn hoàn hàng Ocean Express', [
                        'return_request_id' => $returnRequest->id,
                        'error' => $e->getMessage(),
                    ]);
                    $message = 'Đã duyệt yêu cầu hoàn hàng. Chưa tạo được vận đơn thu hồi Ocean Express, vui lòng đẩy lại sau.';
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
        if (! $returnRequest) {
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
        if (! $returnRequest) {
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
        if (! $returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        if (! in_array($this->normalizeStatus($returnRequest->status), [
            ReturnRequestStatus::APPROVED->value,
            ReturnRequestStatus::RETURNING->value,
            ReturnRequestStatus::WAREHOUSE_RECEIVED->value, // Webhook có thể đã set sẵn, admin vẫn cần nhập received_quantity
        ], true)) {
            return $this->error('Chỉ có thể xác nhận nhận hàng hoàn sau khi đã duyệt, đang gửi hàng hoặc đã về kho.', 422);
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
        if (! $returnRequest) {
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
                    if (! $payload) {
                        throw new OrderException('Vui lòng nhập kết quả QC cho tất cả sản phẩm hoàn.');
                    }

                    $pass = (int) ($payload['qc_pass_quantity'] ?? 0);
                    $fail = (int) ($payload['qc_fail_quantity'] ?? 0);
                    // Nếu received_quantity chưa được set (webhook tự cập nhật không qua markReceived thủ công)
                    // thì dùng requested_quantity làm fallback để QC vẫn thực hiện được
                    $received = (int) ($item->received_quantity ?: $item->requested_quantity);

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

                    if ($pass > 0 && ! $item->inventory_updated_at) {
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

                if (! in_array($this->normalizeStatus($returnRequest->status), ReturnRequestStatus::refundableValues(), true)) {
                    throw new OrderException('Chỉ có thể hoàn tiền sau khi QC đạt hoặc khi retry hoàn tiền.');
                }

                if ($returnRequest->refundTransactions()->where('status', 'success')->exists()) {
                    throw new OrderException('Yêu cầu này đã được hoàn tiền trước đó.');
                }

                $order = Order::whereKey($returnRequest->order_id)->lockForUpdate()->firstOrFail();
                if (! in_array($order->payment_status, PaymentStatus::refundableValues(), true)) {
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
                    throw new OrderException('Số tiền hoàn vượt quá phần còn lại có thể hoàn ('.number_format($remaining).'đ).');
                }

                $method = $data['refund_method'] ?? $returnRequest->refund_method;
                $bankRef = ! empty($data['bank_reference_code']) ? trim((string) $data['bank_reference_code']) : null;
                $idempotencyKey = $data['idempotency_key'] ?? ("return_request:{$returnRequest->id}:refund:{$method}:{$refundAmount}".($bankRef ? ":{$bankRef}" : ''));

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
                        'gateway_refund_id' => $bankRef,
                        'attempt_count' => 0,
                        'requested_by' => auth('admin')->id(),
                    ]
                );

                if ($transaction->status === 'success') {
                    return;
                }

                $transaction->update(['status' => 'processing', 'gateway_refund_id' => $bankRef ?: $transaction->gateway_refund_id]);
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
                    'bank_reference_code' => $bankRef,
                ]);

                if (! $refundResult['success']) {
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

                $finalAdminNote = $data['admin_note'] ?? $returnRequest->admin_note;
                if ($bankRef && ! str_contains((string) $finalAdminNote, $bankRef)) {
                    $finalAdminNote = $finalAdminNote ? "{$finalAdminNote} | [Mã GD Ngân hàng: {$bankRef}]" : "[Mã GD Ngân hàng: {$bankRef}]";
                }

                $returnRequest->update([
                    'status' => ReturnRequestStatus::COMPLETED->value,
                    'refund_status' => RefundStatus::SUCCESS->value,
                    'refund_amount' => $refundAmount,
                    'admin_note' => $finalAdminNote,
                    'refunded_at' => now(),
                    'completed_at' => now(),
                ]);

                $totalRefunded = (float) RefundTransaction::where('order_id', $order->order_id)->where('status', 'success')->sum('amount');
                $paymentStatus = $totalRefunded >= (float) $order->grand_total
                    ? PaymentStatus::REFUNDED->value
                    : PaymentStatus::PARTIALLY_REFUNDED->value;

                $this->updateOrderStatus($order, OrderStatus::REFUNDED->value, 'Admin xác nhận hoàn tiền thủ công.');
                $this->updatePaymentStatus($order, $paymentStatus, '[Thanh toán] Đã hoàn tiền thủ công qua '.$method.'.');

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

    public function buildOceanExpressOrderPayload(ReturnRequest $returnRequest, array $data = []): array
    {
        $returnRequest->loadMissing(['items.orderItem.product', 'items.product', 'order']);

        // 1. NƠI GỬI (ĐIỂM LẤY HÀNG): Lấy từ thông tin đơn hàng ban đầu của khách
        $customerWardCode = trim((string) ($returnRequest->return_pickup_ward_code ?: ($returnRequest->order?->ward_code ?? '')));
        $customerAddress = trim((string) ($returnRequest->return_pickup_address ?: ($returnRequest->order?->shipping_address ?? 'Địa chỉ khách hàng')));
        $customerName = trim((string) ($returnRequest->return_pickup_name ?: ($returnRequest->order?->recipient_name ?? 'Khách Hàng')));
        
        $rawPhone = (string) ($returnRequest->return_pickup_phone ?: ($returnRequest->order?->recipient_phone ?? ''));
        $customerPhone = preg_replace('/[^\d]/', '', $rawPhone);
        if (str_starts_with($customerPhone, '84') && strlen($customerPhone) === 11) {
            $customerPhone = '0'.substr($customerPhone, 2);
        }

        // 2. NƠI NHẬN (ĐIỂM GIAO ĐẾN): Địa chỉ Kho của Shop để nhận lại hàng hoàn
        $warehouseWardCode = config('ocean_express.warehouse_ward_code')
            ?: (config('ghn.sender.ward_code') ?: 'VN-66-24163');
        $warehouseAddress = config('ocean_express.warehouse_address')
            ?: (config('ghn.sender.address') ?: '300/6 Hà Huy Tập, Phường Tân An, Tỉnh Đắk Lắk');
        $warehouseName = config('ocean_express.warehouse_name')
            ?: (config('ghn.sender.name') ?: 'Kho Tổng Ocean Sport');
        
        $rawWarehousePhone = (string) (config('ocean_express.warehouse_phone') ?: (config('ghn.sender.phone') ?: '0905094644'));
        $warehousePhone = preg_replace('/[^\d]/', '', $rawWarehousePhone);
        if (str_starts_with($warehousePhone, '84') && strlen($warehousePhone) === 11) {
            $warehousePhone = '0'.substr($warehousePhone, 2);
        }

        $defaultWeight = (int) config('ocean_express.default_weight', 500);
        $minWeight = (int) config('ocean_express.min_weight', 100);
        $calculatedWeight = 0;

        foreach ($returnRequest->items as $item) {
            $product = $item->product ?: $item->orderItem?->product;
            $w = max((int) ($product?->weight ?? $defaultWeight), $minWeight);
            $calculatedWeight += $w * max((int) $item->requested_quantity, 1);
        }

        $weight = (int) ($data['weight'] ?? max($calculatedWeight, $minWeight));
        $length = (int) ($data['length'] ?? 20);
        $width = (int) ($data['width'] ?? 15);
        $height = (int) ($data['height'] ?? 10);

        $customNote = ! empty($data['required_note']) ? trim((string) $data['required_note']) : 'Thu hồi kiện hàng hoàn từ khách về kho shop';
        $dispatchNote = "[ĐƠN HOÀN HÀNG #{$returnRequest->return_code}] {$customNote} | Shipper lấy hàng từ Khách: {$customerName} ({$customerPhone}) tại {$customerAddress}. Chuyển phát về Kho Shop: {$warehouseName} ({$warehouseAddress} - SĐT: {$warehousePhone}). COD: 0đ.";

        return [
            // Nơi nhận: Địa chỉ Kho của Shop (điểm giao hàng đến)
            'receiver_name' => $warehouseName,
            'receiver_phone' => $warehousePhone,
            'receiver_location_id' => $warehouseWardCode,
            'receiver_address_detail' => $warehouseAddress,

            // Nơi gửi / Điểm lấy hàng: Thông tin khách hàng từ đơn hàng
            'sender_name' => $customerName,
            'sender_phone' => $customerPhone,
            'sender_location_id' => $customerWardCode ?: $warehouseWardCode,
            'sender_address_detail' => $customerAddress,
            'pickup_name' => $customerName,
            'pickup_phone' => $customerPhone,
            'pickup_location_id' => $customerWardCode ?: $warehouseWardCode,
            'pickup_address_detail' => $customerAddress,

            'weight' => max(100, $weight),
            'length' => max(1, $length),
            'width' => max(1, $width),
            'height' => max(1, $height),
            'cod_amount' => 0,
            'note' => $dispatchNote,
        ];
    }

    public function dispatchShipping(int $id, array $data = []): array
    {
        $returnRequest = $this->returnRequestRepository->findForAdmin($id);
        if (! $returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        if (! in_array($this->normalizeStatus($returnRequest->status), [
            ReturnRequestStatus::PENDING->value,
            ReturnRequestStatus::APPROVED->value,
            ReturnRequestStatus::RETURNING->value,
        ], true)) {
            return $this->error('Chỉ có thể điều phối vận đơn cho yêu cầu đang chờ, đã duyệt hoặc đang gửi hoàn.', 422);
        }

        $carrier = $data['carrier'] ?? 'ocean_express';

        if ($carrier === 'dropoff_post_office' || $carrier === 'self_delivery') {
            $trackingCode = trim((string) ($data['tracking_code'] ?? $data['return_tracking_code'] ?? ''));
            $returnRequest->update([
                'return_carrier' => $carrier,
                'return_tracking_code' => $trackingCode ?: $returnRequest->return_tracking_code,
                'admin_note' => $data['admin_note'] ?? $returnRequest->admin_note,
                'status' => ReturnRequestStatus::RETURNING->value,
                'returning_at' => $returnRequest->returning_at ?: now(),
            ]);

            $this->updateOrderStatus($returnRequest->order, OrderStatus::RETURNING->value, 'Khách tự gửi hàng hoàn qua bưu cục/tự vận chuyển.');

            return $this->success('Đã ghi nhận phương thức khách tự gửi hàng hoàn.');
        }

        // Ocean Express dispatch
        try {
            $orderData = $this->buildOceanExpressOrderPayload($returnRequest, $data);
            $oeResult = OceanExpressService::createOrderDetailed($orderData);

            if (! $oeResult['success'] || empty($oeResult['tracking_number'])) {
                $errMsg = $oeResult['error'] ?? 'Ocean Express không thể tạo vận đơn thu hồi.';

                return $this->error($errMsg, 400);
            }

            $trackingNumber = $oeResult['tracking_number'];

            $returnRequest->update([
                'status' => ReturnRequestStatus::APPROVED->value,
                'return_carrier' => 'ocean_express',
                'return_tracking_code' => $trackingNumber,
                'return_ghn_order_code' => $trackingNumber,
                'return_ghn_response' => $oeResult['data'] ?? [],
                'return_label_created_at' => now(),
                'admin_note' => $data['admin_note'] ?? $returnRequest->admin_note,
                'approved_at' => $returnRequest->approved_at ?: now(),
            ]);

            $this->updateOrderStatus($returnRequest->order, OrderStatus::RETURN_APPROVED->value, 'Đã tạo vận đơn thu hồi Ocean Express: '.$trackingNumber);

            return $this->success("Đã đẩy vận đơn thu hồi sang Ocean Express thành công! (Mã: {$trackingNumber})", [
                'tracking_code' => $trackingNumber,
                'carrier' => 'ocean_express',
                'carrier_label' => 'Ocean Express',
            ]);
        } catch (\Throwable $e) {
            Log::error('dispatchShipping return error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return $this->error('Lỗi khi đẩy vận đơn sang Ocean Express: '.$e->getMessage(), 500);
        }
    }

    public function getShippingLabelData(int $id): array
    {
        $returnRequest = $this->returnRequestRepository->findForAdmin($id);
        if (! $returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        if (! $returnRequest->return_tracking_code) {
            return $this->error('Yêu cầu này chưa có mã vận đơn thu hồi để in phiếu.', 422);
        }

        $trackingNumber = $returnRequest->return_tracking_code;
        $labelRes = OceanExpressService::printLabel($trackingNumber);

        $printUrl = data_get($labelRes, 'data.print_url')
            ?? data_get($labelRes, 'data.label_url')
            ?? data_get($labelRes, 'data.pdf_url');

        $warehouseName = config('ocean_express.warehouse_name') ?: (config('ghn.sender.name') ?: 'Kho Tổng Ocean Sport');
        $warehousePhone = config('ocean_express.warehouse_phone') ?: (config('ghn.sender.phone') ?: '0901234567');
        $warehouseAddress = config('ocean_express.warehouse_address') ?: (config('ghn.sender.address') ?: 'Kho Ocean Sport, TP. Hồ Chí Minh');

        return $this->success('Lấy thông tin in phiếu vận đơn thu hồi thành công.', [
            'tracking_code' => $trackingNumber,
            'carrier' => $returnRequest->return_carrier ?: 'ocean_express',
            'carrier_label' => 'Ocean Express',
            'print_url' => $printUrl,
            'return_code' => $returnRequest->return_code,
            'pickup_name' => $returnRequest->return_pickup_name ?: ($returnRequest->order?->recipient_name ?? 'Khách Hàng'),
            'pickup_phone' => $returnRequest->return_pickup_phone ?: ($returnRequest->order?->recipient_phone ?? ''),
            'pickup_address' => $returnRequest->return_pickup_address ?: ($returnRequest->order?->shipping_address ?? ''),
            'warehouse_name' => $warehouseName,
            'warehouse_phone' => $warehousePhone,
            'warehouse_address' => $warehouseAddress,
            'items' => $returnRequest->items->map(fn ($item) => [
                'name' => $item->orderItem?->product_name ?? $item->product?->name ?? 'Sản phẩm',
                'quantity' => $item->requested_quantity,
            ]),
        ]);
    }

    public function getTrackingData(int $id, ?int $userId = null): array
    {
        $query = ReturnRequest::with(['items.orderItem', 'order']);
        if ($userId) {
            $query->where('user_id', $userId);
        }
        $returnRequest = $query->find($id);

        if (! $returnRequest) {
            return $this->error('Không tìm thấy yêu cầu hoàn hàng.', 404);
        }

        if (! $returnRequest->return_tracking_code) {
            return $this->error('Yêu cầu chưa có mã vận đơn thu hồi.', 422);
        }

        $trackingNumber = $returnRequest->return_tracking_code;
        $trackingData = OceanExpressService::getTracking($trackingNumber);

        $customerAddress = $returnRequest->return_pickup_address ?: ($returnRequest->order?->shipping_address ?? 'Địa chỉ khách hàng');
        $customerName = $returnRequest->return_pickup_name ?: ($returnRequest->order?->recipient_name ?? 'Khách Hàng');
        $customerPhone = $returnRequest->return_pickup_phone ?: ($returnRequest->order?->recipient_phone ?? '');

        $warehouseAddress = config('ocean_express.warehouse_address') ?: '300/6 Hà Huy Tập, Phường Tân An, Tỉnh Đắk Lắk';
        $warehouseName = config('ocean_express.warehouse_name') ?: 'Kho Tổng Ocean Sport';
        $warehousePhone = config('ocean_express.warehouse_phone') ?: '0905094644';

        $rawLogs = $trackingData['logs'] ?? ($trackingData['tracking_logs'] ?? []);
        $formattedLogs = array_map(function ($log) use ($customerName, $warehouseName) {
            $status = strtolower($log['status'] ?? ($log['action'] ?? ''));
            $desc = $log['note'] ?? ($log['description'] ?? '');

            // Chuẩn hóa mô tả nhật ký di chuyển sang ngữ cảnh ĐƠN HOÀN HÀNG (thu hồi từ Khách về Kho Shop):
            if ($status === 'ready_to_pick') {
                $desc = "Đơn thu hồi đã tạo trên Ocean Express, đang chờ tài xế đến lấy hàng từ khách ({$customerName})";
            } elseif (in_array($status, ['picking', 'picked', 'picked_up'])) {
                $desc = "Tài xế Ocean Express đã lấy kiện hàng hoàn thành công từ Khách ({$customerName}) và đang chuyển về bưu cục";
            } elseif (in_array($status, ['hub_inbound', 'in_hub', 'storing', 'stored'])) {
                $hubName = preg_match('/Bưu cục [^,\.]+/u', (string) $desc, $m) ? $m[0] : 'Bưu cục trung chuyển';
                $desc = "Kiện hàng hoàn đã nhập kho tại {$hubName}, tiếp tục luân chuyển về {$warehouseName}";
            } elseif (in_array($status, ['hub_outbound', 'transporting', 'in_transit', 'shipping'])) {
                $desc = "Kiện hàng hoàn đang được luân chuyển chặng cuối về {$warehouseName}";
            } elseif ($status === 'delivering') {
                $desc = "Tài xế Ocean Express đang di chuyển giao kiện hàng hoàn về {$warehouseName}";
            } elseif (in_array($status, ['delivered', 'completed', 'returned'])) {
                $desc = "Kiện hàng hoàn đã được giao về tận tay {$warehouseName} thành công";
            }

            $log['note'] = $desc;
            $log['description'] = $desc;

            return $log;
        }, $rawLogs);

        // ---------------------------------------------------------------
        // Auto-sync: Dong bo trang thai tu OE vao DB neu chua cap nhat.
        // Khi webhook OE khong ban thanh cong, admin bam "Cap nhat lai"
        // se tu dong cap nhat trang thai chinh xac tu OE vao he thong.
        // Chi ap dung cho admin (!$userId).
        // ---------------------------------------------------------------
        $statusSynced = false;
        if (! $userId && ! empty($rawLogs)) {
            $latestLog    = $rawLogs[0] ?? null;
            $latestOeSt   = strtolower((string) ($latestLog['status'] ?? ($latestLog['action'] ?? '')));
            $currentSt    = $this->normalizeStatus($returnRequest->status);

            $returningArr = [
                'picking','picked','picked_up','stored','storing',
                'in_hub','hub_inbound','hub_outbound','transporting',
                'in_transit','shipping','delivering',
            ];
            $deliveredArr = ['delivered','completed','returned'];

            if ($latestOeSt && in_array($latestOeSt, $deliveredArr, true) &&
                in_array($currentSt, [
                    ReturnRequestStatus::APPROVED->value,
                    ReturnRequestStatus::RETURNING->value,
                    ReturnRequestStatus::PENDING->value,
                ], true)
            ) {
                DB::transaction(function () use ($returnRequest) {
                    $returnRequest->update([
                        'status'                => ReturnRequestStatus::WAREHOUSE_RECEIVED->value,
                        'received_at'           => $returnRequest->received_at ?: now(),
                        'warehouse_received_at' => $returnRequest->warehouse_received_at ?: now(),
                    ]);
                    $this->updateOrderStatus(
                        $returnRequest->order,
                        OrderStatus::WAREHOUSE_RECEIVED->value,
                        'Ocean Express: Kien hang hoan da giao ve kho Shop (dong bo thu cong).'
                    );
                });
                $returnRequest->refresh();
                $statusSynced = true;
            } elseif ($latestOeSt && in_array($latestOeSt, $returningArr, true) &&
                in_array($currentSt, [
                    ReturnRequestStatus::APPROVED->value,
                    ReturnRequestStatus::PENDING->value,
                ], true)
            ) {
                DB::transaction(function () use ($returnRequest) {
                    $returnRequest->update([
                        'status'       => ReturnRequestStatus::RETURNING->value,
                        'returning_at' => $returnRequest->returning_at ?: now(),
                    ]);
                    $this->updateOrderStatus(
                        $returnRequest->order,
                        OrderStatus::RETURNING->value,
                        'Ocean Express: Shipper da nhan kien hang hoan (dong bo thu cong).'
                    );
                });
                $returnRequest->refresh();
                $statusSynced = true;
            }
        }

        return $this->success('Tra cuu hanh trinh van chuyen Ocean Express.', [
            'tracking_code'  => $trackingNumber,
            'carrier'        => $returnRequest->return_carrier ?: 'ocean_express',
            'carrier_label'  => 'Ocean Express',
            'status'         => $returnRequest->status,
            'status_synced'  => $statusSynced,
            'sender_name'    => $customerName,
            'sender_phone'   => $customerPhone,
            'sender_address' => $customerAddress,
            'receiver_name'  => $warehouseName,
            'receiver_phone' => $warehousePhone,
            'receiver_address' => $warehouseAddress,
            'tracking_data'  => $trackingData,
            'logs'           => $formattedLogs,
        ]);
    }

    public function syncFromOceanExpressWebhook(ReturnRequest $returnRequest, array $payload): void
    {
        $oeStatus = strtolower(trim((string) ($payload['status'] ?? '')));
        $note = $payload['note'] ?? $payload['description'] ?? "Cập nhật từ Ocean Express ({$oeStatus})";

        Log::info('Syncing ReturnRequest from Ocean Express webhook', [
            'return_request_id' => $returnRequest->id,
            'return_code' => $returnRequest->return_code,
            'status' => $oeStatus,
        ]);

        DB::transaction(function () use ($returnRequest, $oeStatus, $note) {
            $currentStatus = $this->normalizeStatus($returnRequest->status);

            // 1. Giai đoạn bưu tá lấy hàng / đang trên đường về kho
            $returningStatuses = [
                'picking', 'picked', 'picked_up', 'stored', 'storing',
                'in_hub', 'hub_inbound', 'hub_outbound', 'transporting',
                'in_transit', 'shipping', 'delivering',
            ];

            if (in_array($oeStatus, $returningStatuses, true)) {
                if (in_array($currentStatus, [ReturnRequestStatus::APPROVED->value, ReturnRequestStatus::PENDING->value], true)) {
                    $returnRequest->update([
                        'status' => ReturnRequestStatus::RETURNING->value,
                        'returning_at' => $returnRequest->returning_at ?: now(),
                        'admin_note' => $returnRequest->admin_note ? $returnRequest->admin_note." | {$note}" : $note,
                    ]);

                    $this->updateOrderStatus($returnRequest->order, OrderStatus::RETURNING->value, 'Ocean Express: Shipper đã nhận kiện hàng hoàn.');
                }

                return;
            }

            // 2. Giai đoạn kiện hàng hoàn đã về tới kho shop
            if (in_array($oeStatus, ['delivered', 'completed', 'returned'], true)) {
                if (in_array($currentStatus, [
                    ReturnRequestStatus::APPROVED->value,
                    ReturnRequestStatus::RETURNING->value,
                    ReturnRequestStatus::PENDING->value,
                ], true)) {
                    $returnRequest->update([
                        'status' => ReturnRequestStatus::WAREHOUSE_RECEIVED->value,
                        'received_at' => $returnRequest->received_at ?: now(),
                        'warehouse_received_at' => $returnRequest->warehouse_received_at ?: now(),
                        'admin_note' => $returnRequest->admin_note ? $returnRequest->admin_note." | {$note}" : $note,
                    ]);

                    $this->updateOrderStatus($returnRequest->order, OrderStatus::WAREHOUSE_RECEIVED->value, 'Ocean Express: Kiện hàng hoàn đã được giao về kho Shop.');
                }

                return;
            }

            // 3. Sự cố / Huỷ
            if (in_array($oeStatus, ['cancelled', 'delivery_fail', 'damaged', 'lost'], true)) {
                Log::warning("Ocean Express báo trạng thái bất thường cho đơn hoàn {$returnRequest->return_code}: {$oeStatus}");
                $returnRequest->update([
                    'admin_note' => $returnRequest->admin_note ? $returnRequest->admin_note." | [Cảnh báo vận chuyển: {$oeStatus}] {$note}" : "[Cảnh báo vận chuyển: {$oeStatus}] {$note}",
                ]);
            }
        });
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
        if (! in_array($order->fulfillment_status, OrderStatus::returnEligibleValues(), true)) {
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
            if (! $hasAvailable) {
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
        if (! $item->variant_id || $quantity <= 0) {
            return;
        }

        ProductVariant::where('variant_id', $item->variant_id)->lockForUpdate()->increment('stock', $quantity);
        $variant = ProductVariant::find($item->variant_id);
        if ($variant && $variant->product_id) {
            Product::where('product_id', $variant->product_id)->decrement('sold_count', $quantity);
            Cache::tags(['products:best-selling'])->flush();
        }

        InventoryTransaction::create([
            'variant_id' => $item->variant_id,
            'transaction_type' => 'return',
            'quantity' => $quantity,
            'reference_type' => 'order',
            'reference_id' => $item->return_request_id,
            'note' => 'Cộng tồn kho từ hoàn hàng #'.$item->returnRequest?->return_code,
            'created_by' => $item->returnRequest?->user_id,
        ]);

        $item->orderItem?->increment('returned_quantity', $quantity);
        $item->update(['inventory_updated_at' => now()]);
    }

    private function storeEvidenceFiles(Request $request, string $field, string $directory): array
    {
        $paths = [];
        if (! $request->hasFile($field)) {
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
            $code = 'RR'.now()->format('ymdHis').strtoupper(Str::random(4));
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
            Log::warning('ReturnRequest cleanupFiles failed: '.$e->getMessage());
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
