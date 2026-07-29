<?php

namespace App\Http\Controllers;

use App\Services\PaymentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VNPayController extends Controller
{
    public function __construct(
        protected PaymentProcessingService $paymentService
    ) {}

    /**
     * GET /api/payment/vnpay-return — VNPay redirect user về
     */
    public function vnpayReturn(Request $request)
    {
        try {
            $result = $this->paymentService->handleVnpayReturn($request->all(), $request->ip());
            $status = $result['_status'] ?? 200;
            unset($result['_status']);

            return response()->json($result, $status);
        } catch (\Exception $e) {
            Log::error('VNPay return error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Đã xảy ra lỗi khi xử lý thanh toán.',
                'payment_status' => 'failed',
            ], 500);
        }
    }

    /**
     * POST /api/payment/vnpay-ipn — VNPay server-to-server callback
     */
    public function vnpayIpn(Request $request)
    {
        try {
            return response()->json(
                $this->paymentService->handleVnpayIpn($request->all(), $request->ip())
            );
        } catch (\Exception $e) {
            Log::error('VNPay IPN error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'RspCode' => '99',
                'Message' => 'Unknown error',
            ]);
        }
    }
}
