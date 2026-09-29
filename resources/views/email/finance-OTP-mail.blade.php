<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal OTP</title>
</head>

<body style="margin:0; padding:0; background:#f3f6fb; font-family: Arial, sans-serif;">

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f6fb; padding:30px 0;">
        <tr>
            <td align="center">

                <!-- Card -->
                <table role="presentation" width="500" style="max-width:500px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 8px 25px rgba(0,0,0,0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#0d6efd,#4f9cff); padding:20px; text-align:center;">
                            <h2 style="margin:0; color:#fff; font-size:20px;">Withdrawal Verification</h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px; text-align:center;">

                            <p style="color:#555; font-size:14px; margin-bottom:10px;">
                                Hello,
                            </p>

                            <p style="color:#666; font-size:14px; line-height:1.6;">
                                You requested a withdrawal from your account. Use the OTP below to confirm your transaction.
                            </p>

                            <!-- OTP Box -->
                            <div style="margin:25px 0;">
                                <div style="display:inline-block; padding:15px 25px; font-size:28px; letter-spacing:8px; font-weight:bold; color:#0d6efd; background:#eef5ff; border:1px dashed #0d6efd; border-radius:10px;">
                                    {{ $otp }}
                                </div>
                            </div>

                            <p style="color:#888; font-size:13px;">
                                This OTP will expire in <b style="color:#333;">5 minutes</b>.
                            </p>

                            <p style="color:#999; font-size:12px; margin-top:20px;">
                                If you did not request this withdrawal, please ignore this email or contact support immediately.
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f8f9fb; text-align:center; padding:15px; font-size:12px; color:#888;">
                            © {{ date('Y') }} Your Company Name. All rights reserved.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
