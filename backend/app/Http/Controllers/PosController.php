<?php

namespace App\Http\Controllers;

use App\Events\PosBarcodeScanned;
use App\Http\Requests\MobileScanRequest;
use App\Http\Requests\PosCheckoutRequest;
use App\Services\PosService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PosController extends Controller
{
    public function __construct(
        protected PosService $posService
    ) {}

    /**
     * Quét barcode sản phẩm
     */
    public function scanProduct(Request $request)
    {
        $barcode = $request->input('code', '');

        if (empty($barcode)) {
            return response()->json(['status' => 'error', 'message' => 'Mã barcode không được để trống'], 422);
        }

        $data = $this->posService->scanByBarcode($barcode);

        if (! $data) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy sản phẩm'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Tìm kiếm sản phẩm theo tên hoặc SKU
     */
    public function searchProducts(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 1) {
            return response()->json(['status' => 'success', 'data' => []]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->posService->searchProducts($query),
        ]);
    }

    /**
     * Thanh toán POS - bán hàng trực tiếp
     */
    public function checkout(PosCheckoutRequest $request)
    {
        $staff = auth('admin')->user() ?? auth('api')->user();
        $staffId = $staff ? ($staff->admin_id ?? $staff->user_id) : null;

        try {
            $order = $this->posService->checkout($request->validated(), $staffId);

            return response()->json([
                'status' => 'success',
                'message' => 'Thanh toán thành công!',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            Log::error('POS Checkout failed: '.$e->getMessage());

            $isDbError = $e instanceof QueryException || $e instanceof \PDOException;
            $errorMsg = $isDbError ? 'Lỗi hệ thống, vui lòng thử lại sau.' : $e->getMessage();

            return response()->json([
                'status' => 'error',
                'message' => $errorMsg,
            ], 422);
        }
    }

    /**
     * Xuất hoá đơn POS thành PDF
     */
    public function exportReceiptPdf($id)
    {
        $order = $this->posService->findOrderForReceipt($id);

        if (! $order) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi: Không tìm thấy hoá đơn này!'], 404);
        }

        // Tạo PDF sử dụng DomPDF
        $pdf = Pdf::loadView('pdfs.pos_receipt', compact('order'));

        // Set khổ giấy cho máy in nhiệt 80mm
        $pdf->setPaper([0, 0, 226.77, 800], 'portrait');

        return $pdf->download("hoadon_{$order->order_code}.pdf");
    }

    /**
     * Nhận sự kiện barcode quét từ điện thoại
     */
    public function mobileScan(MobileScanRequest $request)
    {
        event(new PosBarcodeScanned($request->barcode, $request->session_id));

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi mã vạch lên màn hình POS',
        ]);
    }
}
