<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mã OTP đặt lại mật khẩu</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8; font-family: 'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f4f6f8; padding: 40px 0;">
        <tr><td align="center">
            <table width="100%" max-width="520" style="max-width: 520px; width: 100%; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid #eef2f6;">
                <!-- Header -->
                <tr><td style="background: linear-gradient(135deg, #E63B6F 0%, #d62b5f 100%); padding: 32px 24px; text-align: center;">
                    <h1 style="color: #ffffff; font-size: 22px; font-weight: 800; margin: 0; letter-spacing: 0.5px; text-transform: uppercase;">OCEAN SPORT</h1>
                    <p style="color: rgba(255,255,255,0.9); font-size: 13px; margin: 6px 0 0 0;">Yêu cầu đặt lại mật khẩu</p>
                </td></tr>

                <!-- Body -->
                <tr><td style="padding: 32px 28px;">
                    <p style="color: #334155; font-size: 15px; line-height: 1.6; margin: 0 0 16px 0;">
                        Xin chào <strong>{{ $email }}</strong>,
                    </p>
                    <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;">
                        Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản Ocean Sport của bạn. Vui lòng sử dụng mã OTP xác thực 6 số dưới đây để tiếp tục:
                    </p>

                    <!-- OTP Code Box -->
                    <div style="background: #fdf2f6; border: 2px dashed #E63B6F; border-radius: 12px; padding: 18px 24px; text-align: center; margin: 0 0 24px 0;">
                        <span style="font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #E63B6F; font-family: 'Courier New', monospace;">{{ $otp }}</span>
                    </div>

                    <p style="color: #94a3b8; font-size: 13px; line-height: 1.5; margin: 0 0 8px 0;">
                        ⏱️ Mã OTP có hiệu lực trong vòng <strong>5 phút</strong>.
                    </p>
                    <p style="color: #94a3b8; font-size: 13px; line-height: 1.5; margin: 0;">
                        🔒 Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này. Tài khoản của bạn vẫn được bảo mật an toàn.
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
