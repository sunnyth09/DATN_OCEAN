<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoMoService
{
    /**
     * Tạo URL thanh toán MoMo
     *
     * @param  Order  $order  Đơn hàng cần thanh toán
     * @return string URL redirect đến cổng thanh toán MoMo
     *
     * @throws \Exception
     */
    public static function createPaymentUrl(Order $order): string
    {
        $partnerCode = config('momo.partner_code');
        $accessKey = config('momo.access_key');
        $secretKey = config('momo.secret_key');
        $endpoint = config('momo.endpoint');
        $redirectUrl = config('momo.redirect_url');
        $ipnUrl = config('momo.ipn_url');

        $amount = (string) $order->grand_total;
        $orderId = $order->order_code; // Mã đơn hàng (duy nhất)
        $orderInfo = 'Thanh toan don hang '.$order->order_code;
        $extraData = '';

        $requestId = (string) time().rand(1000, 9999);
        $requestType = 'payWithATM'; // Hoặc "captureWallet"

        $rawHash = 'accessKey='.$accessKey.
            '&amount='.$amount.
            '&extraData='.$extraData.
            '&ipnUrl='.$ipnUrl.
            '&orderId='.$orderId.
            '&orderInfo='.$orderInfo.
            '&partnerCode='.$partnerCode.
            '&redirectUrl='.$redirectUrl.
            '&requestId='.$requestId.
            '&requestType='.$requestType;

        $signature = hash_hmac('sha256', $rawHash, $secretKey);

        $body = [
            'partnerCode' => $partnerCode,
            'partnerName' => 'OceanStore',
            'storeId' => 'OceanStore',
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'extraData' => $extraData,
            'lang' => 'vi',
            'requestType' => $requestType,
            'signature' => $signature,
        ];

        // Gọi lên MoMo
        $response = Http::post($endpoint, $body);

        if ($response->successful()) {
            $responseData = $response->json();
            if (isset($responseData['payUrl'])) {
                Log::info('MoMo payment URL created', [
                    'order_code' => $order->order_code,
                    'amount' => $order->grand_total,
                ]);

                return $responseData['payUrl'];
            }

            throw new \Exception('Không tìm thấy payUrl từ MoMo. Response: '.json_encode($responseData));
        }

        throw new \Exception('Lỗi kết nối API MoMo. Check config endpoint.');
    }
}
