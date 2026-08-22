<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 500px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px;">
        <h2 style="color: #1a1a1a;">Verify Your Email</h2>
        <p>Thanks for signing up on Job Board Platform. Use the code below to verify your email address:</p>
        <div style="font-size: 28px; font-weight: bold; letter-spacing: 4px; text-align: center; padding: 15px; background: #f0f0f0; border-radius: 6px; margin: 20px 0;">
            {{ $code }}
        </div>
        <p>This code will expire in 10 minutes.</p>
        <p style="color: #888; font-size: 12px;">If you didn't request this, you can safely ignore this email.</p>
    </div>
</body>
</html>