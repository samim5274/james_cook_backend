<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="bn">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Order Confirmed</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F8FAFC; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1E293B;">

    <!-- Wrapper Table (for centering) -->
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #F8FAFC; padding: 40px 10px;">
        <tr>
            <td align="center">

                <!-- Main Container Card -->
                <table width="100%" max-width="600" border="0" cellspacing="0" cellpadding="0" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.02);">

                    <!-- Top Accent Bar -->
                    <tr>
                        <td height="6" style="background-color: #4f46e5; line-height: 6px; font-size: 1px;">&nbsp;</td>
                    </tr>

                    <!-- Header -->
                    <tr>
                        <td style="padding: 30px 30px 20px 30px; border-bottom: 1px solid #F1F5F9;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td>
                                        <h1 style="margin: 0; font-size: 24px; font-weight: 900; tracking-style: tight; color: #0F172A; text-transform: uppercase;">
                                            {{ config('app.name', 'Ogrova') }}
                                        </h1>
                                        <p style="margin: 4px 0 0 0; font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: 1px;">অফিসিয়াল ইনভয়েস</p>
                                    </td>
                                    <td align="right" valign="top">
                                        <span style="background-color: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                            {{ $order->status }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Hero Banner / Thank You Message -->
                    <tr>
                        <td style="padding: 30px; background-color: #F8FAFC; border-bottom: 1px solid #F1F5F9;">
                            <p style="margin: 0 0 4px 0; font-size: 12px; font-weight: 700; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.5px;">অর্ডার সফলভাবে গৃহীত</p>
                            <h2 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 800; color: #0F172A;">ধন্যবাদ, আপনার অর্ডারটি কনফার্ম হয়েছে</h2>
                            <p style="margin: 0; font-size: 14px; color: #64748B; line-height: 1.6;">
                                প্রিয় <strong style="color: #0F172A; font-weight: 600;">{{ $order->contact_name }}</strong>, আপনার অর্ডারটি আমাদের সিস্টেমে যুক্ত হয়েছে। খুব শীঘ্রই আমাদের প্রতিনিধি আপনার সাথে যোগাযোগ করবেন।
                            </p>
                        </td>
                    </tr>

                    <!-- Information Blocks (Responsive Stack via Tables) -->
                    <tr>
                        <td style="padding: 30px 30px 10px 30px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <!-- Left Column: Order Info -->
                                    <td width="48%" valign="top" style="background-color: #F8FAFC; border: 1px solid #E2E8F0; padding: 16px; border-radius: 12px;">
                                        <h3 style="margin: 0 0 10px 0; font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px;">অর্ডারের তথ্য</h3>
                                        <div style="font-size: 12px; color: #475569; line-height: 1.8;">
                                            <p style="margin: 0;"><span style="color: #94A3B8;">অর্ডার নম্বর:</span> <strong style="color: #0F172A;">#{{ $order->reg }}</strong></p>
                                            <p style="margin: 0;"><span style="color: #94A3B8;">তারিখ:</span> {{ $order->created_at->format('d M Y, h:i A') }}</p>
                                            <p style="margin: 0;"><span style="color: #94A3B8;">পেমেন্ট মেথড:</span> <span style="text-transform: uppercase; color: #0F172A; font-weight: 500;">{{ $order->payment_method }}</span></p>
                                            <p style="margin: 0;"><span style="color: #94A3B8;">পেমেন্ট স্ট্যাটাস:</span> <span style="color: #D97706; font-weight: 600; text-transform: uppercase;">{{ $order->payment_status }}</span></p>
                                        </div>
                                    </td>

                                    <!-- Spacer -->
                                    <td width="4%">&nbsp;</td>

                                    <!-- Right Column: Shipping Info -->
                                    <td width="48%" valign="top" style="background-color: #F8FAFC; border: 1px solid #E2E8F0; padding: 16px; border-radius: 12px;">
                                        <h3 style="margin: 0 0 10px 0; font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px;">ডেলিভারি ঠিকানা</h3>
                                        <div style="font-size: 12px; color: #475569; line-height: 1.5;">
                                            <p style="margin: 0; font-weight: 700; color: #0F172A; font-size: 13px;">{{ $order->contact_name }}</p>
                                            <p style="margin: 2px 0 0 0;"><span style="color: #94A3B8;">মোবাইল:</span> {{ $order->contact_number }}</p>
                                            <p style="margin: 2px 0 6px 0;"><span style="color: #94A3B8;">ইমেইল:</span> {{ $order->contact_email }}</p>
                                            <div style="border-top: 1px solid #E2E8F0; padding-top: 6px; font-size: 11px; color: #64748B;">
                                                {{ $order->shipping_address }}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Pricing Table -->
                    <tr>
                        <td style="padding: 20px 30px 30px 30px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden;">
                                <tr style="background-color: #F8FAFC;">
                                    <td colspan="2" style="padding: 10px 16px; border-bottom: 1px solid #E2E8F0; font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase;">বিলিং সামারি</td>
                                </tr>

                                <tr>
                                    <td style="padding: 12px 16px; font-size: 13px; color: #64748B; border-bottom: 1px solid #F1F5F9;">Subtotal</td>
                                    <td align="right" style="padding: 12px 16px; font-size: 13px; font-weight: 500; color: #0F172A; border-bottom: 1px solid #F1F5F9;">৳ {{ number_format($order->amount, 2) }}</td>
                                </tr>

                                @if($order->discount > 0)
                                <tr>
                                    <td style="padding: 12px 16px; font-size: 13px; color: #64748B; border-bottom: 1px solid #F1F5F9;">Discount</td>
                                    <td align="right" style="padding: 12px 16px; font-size: 13px; font-weight: 600; color: #E11D48; border-bottom: 1px solid #F1F5F9;">- ৳ {{ number_format($order->discount, 2) }}</td>
                                </tr>
                                @endif

                                @if($order->coupon_discount > 0)
                                <tr>
                                    <td style="padding: 12px 16px; font-size: 13px; color: #64748B; border-bottom: 1px solid #F1F5F9;">Coupon Discount</td>
                                    <td align="right" style="padding: 12px 16px; font-size: 13px; font-weight: 600; color: #E11D48; border-bottom: 1px solid #F1F5F9;">- ৳ {{ number_format($order->coupon_discount, 2) }}</td>
                                </tr>
                                @endif

                                <tr>
                                    <td style="padding: 12px 16px; font-size: 13px; color: #64748B; border-bottom: 1px solid #F1F5F9;">Shipping Charge</td>
                                    <td align="right" style="padding: 12px 16px; font-size: 13px; font-weight: 500; color: #0F172A; border-bottom: 1px solid #F1F5F9;">৳ {{ number_format($order->shipping_charge, 2) }}</td>
                                </tr>

                                <tr style="background-color: #F8FAFC;">
                                    <td style="padding: 16px; font-size: 14px; font-weight: 700; color: #0F172A;">Grand Total</td>
                                    <td align="right" style="padding: 16px; font-size: 18px; font-weight: 900; color: #4f46e5;">৳ {{ number_format($order->payable_amount, 2) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Dark Footer -->
                    <!-- Premium Corporate Footer for Large & Mobile Devices -->
                    <tr>
                        <td align="center" style="background-color: #0F172A; padding: 45px 40px; text-align: center;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 520px; margin: 0 auto;">
                                <!-- Brand Name / Logo Line -->
                                <tr>
                                    <td align="center" style="padding-bottom: 12px;">
                                        <p style="margin: 0; font-size: 16px; font-weight: 800; color: #ffffff; text-transform: uppercase; letter-spacing: 2px;">
                                            {{ config('app.name', 'Ogrova') }}
                                        </p>
                                    </td>
                                </tr>

                                <!-- System Message -->
                                <tr>
                                    <td align="center" style="padding-bottom: 24px;">
                                        <p style="margin: 0; font-size: 12px; color: #94A3B8; line-height: 1.6; font-weight: 400;">
                                            নিরাপত্তার স্বার্থে এই ইমেইলটি সিস্টেম থেকে স্বয়ংক্রিয়ভাবে পাঠানো হয়েছে। এটি একটি ডিজিটাল ইনভয়েস, তাই এতে কোনো ম্যানুয়াল স্বাক্ষর বা সিলের প্রয়োজন নেই।
                                        </p>
                                    </td>
                                </tr>

                                <!-- Divider Line & Copyright -->
                                <tr>
                                    <td align="center" style="border-top: 1px solid #1E293B; padding-top: 16px;">
                                        <p style="margin: 0; font-size: 11px; color: #475569; tracking-style: wide;">
                                            &copy; {{ date('Y') }} <span style="color: #64748B; font-weight: 600;">{{ config('app.name', 'Ogrova') }}</span>. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
