<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset OTP</title>
</head>

<body style="margin:0; padding:0; background:#f3f6fb; font-family: Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:30px 0; background:#f3f6fb;">
        <tr>
            <td align="center">

                <!-- Container -->
                <table width="500" style="max-width:500px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#dc3545,#ff6b6b); padding:20px; text-align:center;">
                            <h2 style="margin:0; color:#fff; font-size:20px;">
                                Password Reset Verification
                            </h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px; text-align:center;">

                            <p style="color:#444; font-size:14px; margin-bottom:5px;">
                                Hello <b>{{ $user->name }}</b>,
                            </p>

                            <p style="color:#666; font-size:14px; line-height:1.6;">
                                We received a request to reset your password. Use the OTP below to continue the process.
                            </p>

                            <!-- OTP Box -->
                            <div style="margin:25px 0;">
                                <div style="display:inline-block; padding:15px 25px; font-size:28px; letter-spacing:8px; font-weight:bold; color:#dc3545; background:#fff1f1; border:1px dashed #dc3545; border-radius:10px;">
                                    {{ $otp }}
                                </div>
                            </div>

                            <p style="color:#888; font-size:13px;">
                                This OTP will expire in <b style="color:#333;">10 minutes</b>.
                            </p>

                            <!-- Warning -->
                            <div style="margin-top:20px; padding:12px; background:#fff8f8; border-left:4px solid #dc3545; text-align:left;">
                                <p style="margin:0; font-size:12px; color:#777;">
                                    If you did not request this password reset, you can safely ignore this email. No changes will be made to your account.
                                </p>
                            </div>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f8f9fb; text-align:center; padding:15px; font-size:12px; color:#888;">
                            © {{ date('Y') }} OGROVA | Bangladesh's Smart Online Marketplace. All rights reserved.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
