<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class VNPayService
{
    /**
     * Tạo URL thanh toán VNPay
     *
     * Flow: Build query params theo spec VNPay → sign bằng HMAC-SHA512 → trả URL đầy đủ
     *
     * @param  Order  $order  Đơn hàng cần thanh toán
     * @param  string  $ipAddr  IP address của client (bắt buộc theo VNPay)
     * @return string URL redirect đến cổng thanh toán VNPay
     */
    public static function createPaymentUrl(Order $order, string $ipAddr): string
    {
        // [FIX P0] Dùng config() thay vì env() — hoạt động đúng khi config:cache
        $vnpTmnCode = config('vnpay.tmn_code');
        $vnpHashSecret = config('vnpay.hash_secret');
        $vnpUrl = config('vnpay.url');
        $vnpReturnUrl = config('vnpay.return_url');

        // VNPay yêu cầu amount tính theo đơn vị nhỏ nhất (VND × 100)
        $vnpAmount = (int) ($order->grand_total * 100);

        // VNPay yêu cầu timezone Việt Nam (UTC+7) cho tất cả timestamps
        $vnTz = new \DateTimeZone('Asia/Ho_Chi_Minh');
        $now = new \DateTime('now', $vnTz);
        $expire = (clone $now)->modify('+30 minutes');

        // IPN URL — VNPay sẽ gọi server-to-server để xác nhận giao dịch
        $vnpIpnUrl = config('vnpay.ipn_url');

        $inputData = [
            'vnp_Version' => '2.1.0',
            'vnp_TmnCode' => $vnpTmnCode,
            'vnp_Amount' => $vnpAmount,
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => $ipAddr,
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Thanh toan don hang '.$order->order_code,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $vnpReturnUrl,
            'vnp_TxnRef' => $order->order_code,
            'vnp_ExpireDate' => $expire->format('YmdHis'),
        ];

        // Chỉ thêm IPN URL nếu đã cấu hình (tránh gửi param rỗng)
        if (! empty($vnpIpnUrl)) {
            $inputData['vnp_IpnUrl'] = $vnpIpnUrl;
        }

        // VNPay yêu cầu sort params theo alphabet trước khi hash
        ksort($inputData);

        $hashData = '';
        $query = '';
        $i = 0;
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData .= '&'.urlencode($key).'='.urlencode($value);
            } else {
                $hashData .= urlencode($key).'='.urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key).'='.urlencode($value).'&';
        }

        // Tạo secure hash bằng HMAC-SHA512
        $vnpSecureHash = hash_hmac('sha512', $hashData, $vnpHashSecret);
        $paymentUrl = $vnpUrl.'?'.$query.'vnp_SecureHash='.$vnpSecureHash;

        // [FIX P0] Chỉ log thông tin cần thiết, KHÔNG log hashData, URL (chứa secure hash)
        Log::info('VNPay payment URL created', [
            'order_code' => $order->order_code,
            'amount' => $order->grand_total,
        ]);

        return $paymentUrl;
    }

    /**
     * Verify dữ liệu trả về từ VNPay (Return URL / IPN)
     *
     * Kiểm tra tính toàn vẹn bằng cách hash lại tất cả params rồi so sánh
     * với vnp_SecureHash được VNPay gửi về.
     *
     * @param  array  $queryParams  Mảng query params từ VNPay redirect
     * @return array ['isValid' => bool, 'responseCode' => string, 'txnRef' => string, ...]
     */
    public static function verifyReturn(array $queryParams): array
    {
        // [FIX P0] Dùng config() thay vì env()
        $vnpHashSecret = config('vnpay.hash_secret');
        $vnpSecureHash = $queryParams['vnp_SecureHash'] ?? '';

        // Loại bỏ các trường hash/type khỏi data trước khi verify
        $inputData = [];
        foreach ($queryParams as $key => $value) {
            if (str_starts_with($key, 'vnp_') && $key !== 'vnp_SecureHash' && $key !== 'vnp_SecureHashType') {
                $inputData[$key] = $value;
            }
        }

        ksort($inputData);

        $hashData = '';
        $i = 0;
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData .= '&'.urlencode($key).'='.urlencode($value);
            } else {
                $hashData .= urlencode($key).'='.urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $vnpHashSecret);
        $isValid = hash_equals($secureHash, $vnpSecureHash);

        Log::info('VNPay verify return', [
            'txnRef' => $queryParams['vnp_TxnRef'] ?? 'N/A',
            'responseCode' => $queryParams['vnp_ResponseCode'] ?? 'N/A',
            'isValid' => $isValid,
        ]);

        return [
            'isValid' => $isValid,
            'responseCode' => $queryParams['vnp_ResponseCode'] ?? '',
            'txnRef' => $queryParams['vnp_TxnRef'] ?? '',
            'amount' => isset($queryParams['vnp_Amount']) ? (int) $queryParams['vnp_Amount'] / 100 : 0,
            'transactionNo' => $queryParams['vnp_TransactionNo'] ?? '',
            'bankCode' => $queryParams['vnp_BankCode'] ?? '',
            'payDate' => $queryParams['vnp_PayDate'] ?? '',
            'orderInfo' => $queryParams['vnp_OrderInfo'] ?? '',
        ];
    }
}
