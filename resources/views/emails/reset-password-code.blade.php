<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt lại mật khẩu Fluid Notes</title>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f0ff;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
        style="max-width: 480px; margin: 40px auto; background: #ffffff; border-radius: 16px; box-shadow: 0 4px 24px rgba(99, 102, 241, 0.1); overflow: hidden;">
        {{-- Header gradient --}}
        <tr>
            <td
                style="background: linear-gradient(135deg, #6366f1 0%, #a78bfa 100%); padding: 32px 24px; text-align: center;">
                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">
                    Fluid Notes
                </h1>
                <p style="margin: 8px 0 0; color: rgba(255, 255, 255, 0.85); font-size: 14px;">
                    Đặt lại mật khẩu
                </p>
            </td>
        </tr>

        {{-- Content --}}
        <tr>
            <td style="padding: 32px 24px;">
                <p style="margin: 0 0 8px; color: #1e1b4b; font-size: 16px;">
                    Xin chào <strong>{{ $userName }}</strong>,
                </p>
                <p style="margin: 0 0 24px; color: #6b7280; font-size: 14px; line-height: 1.6;">
                    Bạn đã yêu cầu đặt lại mật khẩu cho tài khoản Fluid Notes. Vui lòng nhập mã bên dưới để tiếp tục:
                </p>

                {{-- OTP Code --}}
                <div style="text-align: center; margin: 0 0 24px;">
                    <div
                        style="display: inline-block; background: linear-gradient(135deg, #f5f3ff 0%, #eef2ff 100%); border: 2px solid #e0e7ff; border-radius: 12px; padding: 16px 32px;">
                        <span
                            style="font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #4f46e5; font-family: 'Courier New', monospace;">
                            {{ $code }}
                        </span>
                    </div>
                </div>

                {{-- Expiry warning --}}
                <div
                    style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; margin: 0 0 24px;">
                    <p style="margin: 0; color: #92400e; font-size: 13px;">
                        Mã sẽ hết hạn sau <strong>5 phút</strong>. Vui lòng không chia sẻ mã này với bất kỳ ai.
                    </p>
                </div>

                <p style="margin: 0; color: #9ca3af; font-size: 13px; line-height: 1.5;">
                    Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này. Mật khẩu của bạn sẽ không thay đổi.
                </p>
            </td>
        </tr>

        {{-- Footer --}}
        <tr>
            <td style="background: #f9fafb; padding: 20px 24px; text-align: center; border-top: 1px solid #f3f4f6;">
                <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                    © {{ date('Y') }} Fluid Notes. All rights reserved.
                </p>
            </td>
        </tr>
    </table>
</body>

</html>