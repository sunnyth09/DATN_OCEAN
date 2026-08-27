<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Cap nhat khieu nai - Ocean Sport</title>
<style>
body{margin:0;padding:0;font-family:Arial,sans-serif;background:#f5f5f5;color:#333}
.wrapper{max-width:600px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#E63B6F,#c0255a);padding:32px 36px;text-align:center}
.header h1{color:#fff;font-size:22px;margin:0}
.body{padding:32px 36px}
.info-box{background:#fdf2f6;border-left:4px solid #E63B6F;border-radius:6px;padding:16px 20px;margin:20px 0}
.info-box p{margin:6px 0;font-size:14px}
.info-box strong{color:#E63B6F}
.status-badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:13px;font-weight:600;background:#E63B6F;color:#fff}
.reply-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;margin:20px 0}
.reply-box h3{margin:0 0 8px 0;font-size:14px;color:#555}
.cta{text-align:center;margin:28px 0 10px}
.cta a{display:inline-block;background:linear-gradient(135deg,#E63B6F,#c0255a);color:#fff;text-decoration:none;padding:13px 32px;border-radius:8px;font-weight:600;font-size:15px}
.footer{background:#f8fafc;border-top:1px solid #e2e8f0;padding:20px 36px;text-align:center;font-size:12px;color:#888}
.footer a{color:#E63B6F;text-decoration:none}
</style>
</head>
<body>
<div class="wrapper">
<div class="header"><h1>Ocean Sport</h1></div>
<div class="body">
<p>Xin chao <strong>{{ $ticket->user->full_name ?? 'Quy khach' }}</strong>,</p>
<p>Khieu nai cua ban vua duoc cap nhat. Thong tin chi tiet:</p>
<div class="info-box">
<p><strong>Ma khieu nai:</strong> #{{ $ticket->ticket_id }}</p>
<p><strong>Ly do:</strong> {{ $ticket->reason }}</p>
<p><strong>Trang thai moi:</strong> <span class="status-badge">{{ $statusText }}</span></p>
@if($ticket->order)
<p><strong>Ma don hang:</strong> {{ $ticket->order->order_code ?? ('#' . $ticket->order_id) }}</p>
@endif
</div>
@if($ticket->admin_reply)
<div class="reply-box">
<h3>Phan hoi tu Ocean Sport:</h3>
<p>{{ $ticket->admin_reply }}</p>
</div>
@endif
<div class="cta"><a href="{{ $frontendUrl }}/profile/tickets">Xem chi tiet khieu nai</a></div>
</div>
<div class="footer">&copy; {{ date('Y') }} Ocean Sport. Lien he <a href="mailto:support@oceansport.pro.vn">support@oceansport.pro.vn</a></div>
</div>
</body>
</html>
