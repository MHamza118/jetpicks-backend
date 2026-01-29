<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        .header {
            background-color: #FFDF57;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        .content {
            background-color: #fff;
            padding: 20px;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            background-color: #FFDF57;
            color: #333;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reset Your Password</h1>
        </div>
        <div class="content">
            <p>Hi {{ $userName }},</p>
            
            <p>We received a request to reset your JetPicker password. Click the button below to create a new password:</p>
            
            <center>
                <a href="{{ $resetUrl }}" class="button">Reset Password</a>
            </center>
            
            <p>Or copy and paste this link in your browser:</p>
            <p style="word-break: break-all; background-color: #f5f5f5; padding: 10px; border-radius: 4px;">
                {{ $resetUrl }}
            </p>
            
            <div class="warning">
                <strong>⚠️ Security Notice:</strong> This link will expire in 15 minutes. If you didn't request a password reset, please ignore this email or contact support if you have concerns.
            </div>
            
            <p>If you have any questions, please contact our support team.</p>
            
            <p>Best regards,<br>The JetPicker Team</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} JetPicker. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
