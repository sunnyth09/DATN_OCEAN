<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosController extends Controller
{
    /**
     * Quét barcode sản phẩm
     */
    public function scanProduct(Request $request)
    {
        $barcode = $request->input('code', '');

        if (empty($barcode)) {
            return response()->json(['status' => 'error', 'message' => 'Mã barcode không được để trống'], 422);
        }

        $variant = ProductVariant::with(['product.images', 'product.category'])
            ->where('barcode', $barcode)
            ->where('status', 'active')
            ->first();

        if (!$variant) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy sản phẩm'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatVariantResponse($variant)
        ]);
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

        $products = Product::with(['variants' => function ($q) {
                $q->where('status', 'active')->where('stock', '>', 0);
            }, 'images', 'category'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('slug', 'LIKE', "%{$query}%")
                  ->orWhereHas('variants', function ($vq) use ($query) {
                      $vq->where('sku', 'LIKE', "%{$query}%")
                         ->orWhere('barcode', 'LIKE', "%{$query}%");
                  });
            })
            ->where('status', 'active')
            ->limit(20)
            ->get();

        $results = $products->map(function ($product) {
            $mainImage = $product->images->where('is_main', 1)->first();
            $thumbnail = $mainImage
                ? $mainImage->image_url
                : ($product->images->first()->image_url ?? $product->thumbnail_url);

            return [
                'product_id' => $product->product_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'thumbnail' => $thumbnail,
                'category_name' => $product->category->name ?? '',
                'variants' => $product->variants->map(function ($v) {
                    return [
                        'variant_id' => $v->variant_id,
                        'variant_name' => $v->variant_name,
                        'sku' => $v->sku,
                        'barcode' => $v->barcode,
                        'color' => $v->color,
                        'size' => $v->size,
                        'price' => $v->price,
                        'compare_at_price' => $v->compare_at_price,
                        'stock' => $v->stock,
                        'image_url' => $v->image_url,
                    ];
                }),
            ];
        });

        return response()->json(['status' => 'success', 'data' => $results]);
    }

    /**
     * Thanh toán POS - bán hàng trực tiếp
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|integer|exists:product_variants,variant_id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'payment_method' => 'nullable|string|in:pos_cash,pos_transfer,pos_card',
            'note' => 'nullable|string|max:500',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        // Lấy admin/staff đang đăng nhập
        $staff = auth('admin')->user() ?? auth('api')->user();
        $staffId = $staff ? ($staff->admin_id ?? $staff->user_id) : null;

        // Khóa input tự do user_id: Không tin tưởng user_id từ client.
        // Tự động tìm khách hàng dựa trên customer_phone để gắn user_id chính xác.
        $customerId = null;
        if (!empty($request->customer_phone)) {
            $customer = \App\Models\User::where('phone', $request->customer_phone)->first();
            if ($customer) {
                $customerId = $customer->user_id;
                // Có thể overwrite tên khách hàng bằng tên thật trong hệ thống nếu muốn
                if (empty($request->customer_name)) {
                    $request->merge(['customer_name' => $customer->full_name]);
                }
            }
        }

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                /** @var \App\Models\ProductVariant $variant */
                $variant = ProductVariant::with('product')->where('variant_id', $item['variant_id'])->lockForUpdate()->first();

                if (!$variant || $variant->status !== 'active') {
                    $productName = $variant && $variant->product ? $variant->product->name : 'N/A';
                    throw new \Exception('Sản phẩm "' . $productName . '" không khả dụng.');
                }

                if ($variant->stock < $item['quantity']) {
                    throw new \Exception('Sản phẩm "' . $variant->product->name . '" (' . $variant->variant_name . ') chỉ còn ' . $variant->stock . ' trong kho.');
                }

                $lineTotal = $variant->price * $item['quantity'];
                $subtotal += $lineTotal;

                $itemsData[] = [
                    'variant' => $variant,
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                ];
            }

            $discountAmount = min($request->input('discount_amount', 0), $subtotal);
            $grandTotal = $subtotal - $discountAmount;

            // Tạo đơn hàng POS
            $order = Order::create([
                'order_code' => 'POS' . strtoupper(uniqid()) . rand(10, 99),
                'order_type' => 'pos',
                'user_id' => $customerId ?? $staffId, // Ưu tiên customerId lấy được từ DB, nếu không thì dùng staffId (khách vãng lai)
                'seller_id' => $staffId,
                'recipient_name' => !empty($request->customer_name) ? $request->customer_name : 'Khách lẻ',
                'recipient_phone' => !empty($request->customer_phone) ? $request->customer_phone : '',
                'shipping_address' => 'Mua tại cửa hàng',
                'note' => !empty($request->note) ? $request->note : '',
                'payment_method' => !empty($request->payment_method) ? $request->payment_method : 'pos_cash',
                'payment_status' => 'paid',
                'fulfillment_status' => 'completed',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'shipping_fee' => 0,
                'grand_total' => $grandTotal,
                'completed_at' => now(),
            ]);

            // Tạo order items + trừ kho
            foreach ($itemsData as $data) {
                $v = $data['variant'];
                OrderItem::create([
                    'order_id' => $order->order_id,
                    'product_id' => $v->product_id,
                    'variant_id' => $v->variant_id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->variant_name,
                    'sku' => $v->sku,
                    'color' => $v->color,
                    'size' => $v->size,
                    'quantity' => $data['quantity'],
                    'unit_price' => $v->price,
                    'line_total' => $data['line_total'],
                ]);

                /** @var \App\Models\ProductVariant $v */
                $v = $data['variant'];
                $v->decrement('stock', $data['quantity']);
            }

            // Ghi lịch sử
            OrderStatusHistory::create([
                'order_id' => $order->order_id,
                'new_status' => 'completed',
                'note' => 'Bán hàng trực tiếp tại cửa hàng (POS)',
            ]);

            DB::commit();

            \Illuminate\Support\Facades\Cache::flush();

            // Load lại order kèm items để trả về cho in hoá đơn
            $order->load('items');

            return response()->json([
                'status' => 'success',
                'message' => 'Thanh toán thành công!',
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('POS Checkout failed: ' . $e->getMessage());
            
            $isDbError = $e instanceof \Illuminate\Database\QueryException || $e instanceof \PDOException;
            $errorMsg = $isDbError ? 'Lỗi hệ thống, vui lòng thử lại sau.' : $e->getMessage();
            
            return response()->json([
                'status' => 'error',
                'message' => $errorMsg,
            ], 422);
        }
    }

    /**
     * Format variant response cho scan
     */
    private function formatVariantResponse($variant)
    {
        $product = $variant->product;
        $mainImage = $product->images->where('is_main', 1)->first();

        return [
            'variant_id' => $variant->variant_id,
            'variant_name' => $variant->variant_name,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'color' => $variant->color,
            'size' => $variant->size,
            'price' => $variant->price,
            'stock' => $variant->stock,
            'image_url' => $variant->image_url,
            'product' => [
                'product_id' => $product->product_id,
                'name' => $product->name,
                'thumbnail' => $mainImage->image_url ?? $product->thumbnail_url,
            ],
        ];
    }

    /**
     * Xuất hoá đơn POS thành PDF
     */
    public function exportReceiptPdf($id)
    {
        $order = \App\Models\Order::with('items')->find($id);

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi: Không tìm thấy hoá đơn này!'], 404);
        }

        // Tạo PDF sử dụng DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.pos_receipt', compact('order'));

        // Set khổ giấy cho máy in nhiệt 80mm
        $pdf->setPaper(array(0,0,226.77,800), 'portrait');

        return $pdf->download("hoadon_{$order->order_code}.pdf");
    }
    /**
     * Nhận sự kiện barcode quét từ điện thoại
     */
    public function mobileScan(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
            'session_id' => 'required|string',
        ]);
        
        event(new \App\Events\PosBarcodeScanned($request->barcode, $request->session_id));
        
        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi mã vạch lên màn hình POS',
        ]);
    }
}