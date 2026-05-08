<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Notification</title>
  <style>
    @media only screen and (max-width: 600px) {
      .container { width: 100% !important; }
      .content { padding: 16px !important; }
    }
  </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: 'Segoe UI', Arial, sans-serif; -webkit-font-smoothing: antialiased;">
  <!-- Main Container -->
  <div class="container" style="max-width: 600px; margin: 0 auto; background-color: #f5f5f5;">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; text-align: center;">
      <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600; letter-spacing: -0.5px;">ESOA</h1>
      <p style="margin: 4px 0 0 0; color: rgba(255,255,255,0.9); font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">Notification</p>
    </div>

    <!-- Content Body -->
    <div class="content" style="background-color: #ffffff; padding: 32px 24px; margin: 12px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
      @yield('content')
    </div>

    <!-- Footer -->
    <div style="background-color: #f9f9f9; padding: 20px 24px; margin: 0 12px 12px 12px; border-radius: 8px; border-top: 1px solid #e8e8e8;">
      <p style="margin: 0; color: #666666; font-size: 12px; line-height: 1.6; text-align: center;">
        <strong style="color: #333333;">ESOA System</strong><br>
        This is an automated notification. Please do not reply to this email.
      </p>
    </div>

    <!-- Copyright -->
    <div style="padding: 12px 24px; text-align: center;">
      <p style="margin: 0; color: #999999; font-size: 11px; line-height: 1.4;">
        &copy; {{ date('Y') }} ESOA System. All rights reserved.
      </p>
    </div>
  </div>
</body>
</html>
