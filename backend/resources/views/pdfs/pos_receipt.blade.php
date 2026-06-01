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
        
        .header { margin-bottom: 20px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
        .header h2 { margin: 0 0 5px 0; font-size: 18px; }
        .info { margin-bottom: 15px; }
        .info div { margin-bottom: 3px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { text-align: left; padding: 5px 0; border-bottom: 1px dotted #ccc; }
        th { border-bottom: 1px solid #000; }
        
        .item-name { font-size: 11px; font-weight: bold; margin-bottom: 2px; display: block; }
        .item-meta { font-size: 10px; color: #555; }
        
        .summary { border-top: 1px dashed #000; padding-top: 10px; margin-top: 10px; }
        .summary-line { overflow: hidden; margin-bottom: 5px; }
        .summary-line span:first-child { float: left; }
        .summary-line span:last-child { float: right; }
        .summary-total { font-size: 14px; font-weight: bold; }
        
        .footer { text-align: center; margin-top: 20px; border-top: 1px dashed #000; padding-top: 10px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header text-center">
        <h2>OCEAN STORE</h2>
        <div>101 Y Ngông, P. Tân Lập, TP.Buôn Ma Thuột, Đắk Lắk</div>
        <div>SĐT: 1900-OCEAN (1900 6232)</div>
    </div>
    
    <div class="info">
        <div><span class="font-bold">Mã đơn:</span> {{ $order->order_code }}</div>
        <div><span class="font-bold">Ngày:</span> {{ $order->created_at->format('d/m/Y H:i') }}</div>
        <div><span class="font-bold">Khách:</span> {{ $order->recipient_name }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 50%;">Sản phẩm</th>
                <th style="width: 20%;" class="text-center">SL</th>
                <th style="width: 30%;" class="text-right">T.Tiền</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>
                    <span class="item-name">{{ $item->product_name }}</span>
                    <span class="item-meta">{{ $item->color }} - {{ $item->size }}</span>
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
        <div class="summary-line">
            <span>Chiết khấu:</span>
            <span>- {{ number_format($order->discount_amount, 0, ',', '.') }}đ</span>
        </div>
        <div class="summary-line summary-total">
            <span>THANH TOÁN:</span>
            <span>{{ number_format($order->grand_total, 0, ',', '.') }}đ</span>
        </div>
    </div>

    <div class="footer">
        <div>Cảm ơn quý khách đã mua hàng!</div>
        <div>Hẹn gặp lại!</div>
    </div>
</body>
</html>
