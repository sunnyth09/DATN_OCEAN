<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phản hồi hỗ trợ từ Ocean Sport</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8; font-family: 'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f4f6f8; padding: 40px 0;">
        <tr><td align="center">
            <table width="100%" style="max-width: 560px; width: 100%; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid #eef2f6;">

                <!-- Header -->
                <tr><td style="background: linear-gradient(135deg, #E63B6F 0%, #d62b5f 100%); padding: 32px 24px; text-align: center;">
                    <div style="font-size: 12px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: #ffd9de; margin-bottom: 6px;">OCEAN SPORT</div>
                    <h1 style="color: #ffffff; font-size: 22px; font-weight: 800; margin: 0; letter-spacing: 0.3px;">Phản Hồi Yêu Cầu Hỗ Trợ</h1>
                    <p style="color: rgba(255,255,255,0.85); font-size: 13px; margin: 6px 0 0 0;">Bộ phận Chăm sóc Khách hàng Ocean Sport</p>
                </td></tr>

                <!-- Body -->
                <tr><td style="padding: 32px 28px 24px;">
                    <p style="color: #1e293b; font-size: 15px; line-height: 1.5; margin: 0 0 8px 0;">
                        Xin chào <strong>{{ $contactName }}</strong>,
                    </p>
                    <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
                        Cảm ơn bạn đã liên hệ với chúng tôi về chủ đề: <em>"{{ $contactSubject }}"</em>. Dưới đây là phản hồi từ đội ngũ hỗ trợ:
                    </p>

                    <!-- Reply box -->
                    <div style="background: #FFF0F3; border-left: 4px solid #E63B6F; padding: 18px 20px; border-radius: 0 10px 10px 0; margin: 0 0 24px 0;">
                        <p style="color: #1e293b; margin: 0; white-space: pre-wrap; font-size: 14px; line-height: 1.7;">{{ $replyContent }}</p>
                    </div>

                    <p style="color: #64748b; font-size: 13px; line-height: 1.6; margin: 0;">
                        Nếu bạn cần thêm thông tin, hãy phản hồi trực tiếp email này hoặc gọi hotline
                        <strong style="color: #E63B6F;">1900 6868</strong> để được hỗ trợ tức thì.
                    </p>
                </td></tr>

                <!-- Footer -->
                <tr><td style="background: #f8fafc; padding: 20px 24px; border-top: 1px solid #f1f5f9; text-align: center;">
                    <div style="font-weight: 700; color: #64748b; font-size: 12px; margin-bottom: 4px;">OCEAN SPORT — CỬA HÀNG THỂ THAO CAO CẤP</div>
                    <div style="font-size: 11px; color: #94a3b8; line-height: 1.5;">
                        Hotline: <strong style="color: #E63B6F;">1900 6868</strong> | Email: <strong style="color: #E63B6F;">contact@oceansport.vn</strong><br>
                        &copy; {{ date('Y') }} Ocean Sport. All rights reserved.
                    </div>
                </td></tr>

            </table>
        </td></tr>
    </table>
</body>
</html>
