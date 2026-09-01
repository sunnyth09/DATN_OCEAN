<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hóa Đơn POS #{{ $order->order_code }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 10px;
            font-size: 12px;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        .header { margin-bottom: 15px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
        .header h2 { margin: 0 0 5px 0; font-size: 17px; letter-spacing: 0.5px; }
        .header .address { font-size: 10px; margin-bottom: 2px; }
        .header .hotline { font-size: 10px; font-weight: bold; }
        
        .info { margin-bottom: 12px; font-size: 11px; line-height: 1.4; }
        .info div { margin-bottom: 2px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { text-align: left; padding: 4px 0; border-bottom: 1px dotted #ccc; font-size: 11px; }
        th { border-bottom: 1px solid #000; font-size: 11px; }
        
        .item-name { font-size: 11px; font-weight: bold; margin-bottom: 2px; display: block; }
        .item-meta { font-size: 10px; color: #555; }
        
        .summary { border-top: 1px dashed #000; padding-top: 8px; margin-top: 8px; }
        .summary-line { overflow: hidden; margin-bottom: 4px; font-size: 11px; }
        .summary-line span:first-child { float: left; }
        .summary-line span:last-child { float: right; }
        .discount-line { color: #000; font-weight: bold; }
        .summary-total { font-size: 13px; font-weight: bold; border-top: 1px dotted #000; padding-top: 5px; margin-top: 4px; }
        
        .footer { text-align: center; margin-top: 16px; border-top: 1px dashed #000; padding-top: 8px; font-size: 10px; line-height: 1.3; }
    </style>
</head>
<body>
    <div class="header text-center">
        <h2>OCEAN SPORT</h2>
        <div class="address">134 Nguyễn Thị Định, P.Buôn Ma Thuột, Đắk Lắk</div>
        <div class="hotline">Hotline: 1900-OCEAN (1900 6232)</div>
    </div>
    
    <div class="info">
        <div><span class="font-bold">Mã đơn:</span> #{{ $order->order_code }}</div>
        <div><span class="font-bold">Thời gian:</span> {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</div>
        <div><span class="font-bold">Khách hàng:</span> {{ $order->recipient_name ?: 'Khách lẻ' }}</div>
        @if(!empty($order->recipient_phone))
        <div><span class="font-bold">SĐT khách:</span> {{ $order->recipient_phone }}</div>
        @endif
        @if($order->seller)
        <div><span class="font-bold">Thu ngân:</span> {{ $order->seller->full_name ?? $order->seller->name }}</div>
        @endif
        <div><span class="font-bold">PTTT:</span> {{ $order->payment_method === 'pos_cash' ? 'Tiền mặt' : ($order->payment_method === 'pos_transfer' ? 'Chuyển khoản' : ($order->payment_method === 'pos_card' ? 'Quẹt thẻ' : 'Bán tại quầy')) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 52%;">Sản phẩm</th>
                <th style="width: 16%;" class="text-center">SL</th>
                <th style="width: 32%;" class="text-right">T.Tiền</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>
                    <span class="item-name">{{ $item->product_name }}</span>
                    @if(!empty($item->color) || !empty($item->size))
                    <span class="item-meta">{{ $item->color }}{{ !empty($item->color) && !empty($item->size) ? ' - ' : '' }}{{ $item->size }}</span>
                    @endif
                </td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-right">{{ number_format($item->line_total, 0, ',', '.') }}đ</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-line">
            <span>Tạm tính:</span>
            <span>{{ number_format($order->subtotal, 0, ',', '.') }}đ</span>
        </div>
        @if($order->discount_amount > 0)
        <div class="summary-line discount-line">
            <span>Giảm giá / Voucher:</span>
            <span>-{{ number_format($order->discount_amount, 0, ',', '.') }}đ</span>
        </div>
        @endif
        @if(isset($order->combo_discount) && $order->combo_discount > 0)
        <div class="summary-line discount-line">
            <span>Giảm giá Combo:</span>
            <span>-{{ number_format($order->combo_discount, 0, ',', '.') }}đ</span>
        </div>
        @endif
        <div class="summary-line summary-total">
            <span>THANH TOÁN:</span>
            <span>{{ number_format($order->grand_total, 0, ',', '.') }}đ</span>
        </div>
    </div>

    <div class="footer">
        <div>Cảm ơn quý khách đã mua sắm tại Ocean Sport!</div>
        <div>Hẹn gặp lại quý khách!</div>
    </div>
</body>
</html>
