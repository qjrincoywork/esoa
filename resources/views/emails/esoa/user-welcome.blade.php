{{-- User Welcome / Account Created Email --}}

@extends('layouts.mails')

@section('content')
  {{-- Greeting --}}
  <p style="margin: 0 0 16px 0; color: #333333; font-size: 14px; line-height: 1.6;">
    Dear <strong>{{ $user->userDetail?->first_name }} {{ $user->userDetail?->last_name }}</strong>,
  </p>

  {{-- Opening --}}
  <p style="margin: 0 0 20px 0; color: #555555; font-size: 14px; line-height: 1.6;">
    Welcome to the <strong>ESOA System</strong>. Your account has been successfully created by an administrator.
    Please find your login credentials below and follow the instructions to secure your account.
  </p>

  {{-- Credentials Block --}}
  <div style="background-color: #f0f7ff; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 4px;">
    <h3 style="margin: 0 0 14px 0; color: #333333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
      🔐 Your Login Credentials
    </h3>
    <table style="width: 100%; border-collapse: collapse;">
      <tr>
        <td style="padding: 6px 0; color: #555555; font-size: 13px; width: 40%; vertical-align: top;">Username</td>
        <td style="padding: 6px 0; color: #333333; font-size: 13px; font-weight: 600;">{{ $user->username }}</td>
      </tr>
      <tr>
        <td style="padding: 6px 0; color: #555555; font-size: 13px; vertical-align: top;">Temporary Password</td>
        <td style="padding: 6px 0;">
          <code style="display: inline-block; background-color: #ffffff; border: 1px solid #d0d0d0; border-radius: 4px; padding: 4px 10px; font-size: 15px; font-family: 'Courier New', monospace; font-weight: 700; color: #333333; letter-spacing: 1px;">{{ $plainPassword }}</code>
        </td>
      </tr>
    </table>
  </div>

  {{-- Expiry Warning --}}
  <div style="background-color: #fff8e1; border-left: 4px solid #f59e0b; padding: 16px; margin: 20px 0; border-radius: 4px;">
    <p style="margin: 0; color: #7c4a00; font-size: 13px; line-height: 1.6;">
      ⏰ <strong>Important:</strong> This temporary password will expire on
      <strong>{{ $expiresAt }}</strong>.
      You must log in and change your password before it expires.
    </p>
  </div>

  {{-- Steps --}}
  <div style="margin: 20px 0; padding: 16px; background-color: #fafafa; border-radius: 6px; border: 1px solid #e8e8e8;">
    <h3 style="margin: 0 0 12px 0; color: #333333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
      📋 Next Steps
    </h3>
    <ol style="margin: 0; padding-left: 18px; color: #555555; font-size: 13px; line-height: 2;">
      <li>Visit the ESOA login page using the button below.</li>
      <li>Enter your username and the temporary password above.</li>
      <li>Once logged in, please change your temporary password to a new permanent password.</li>
      <li>Keep your new password confidential and do not share it with anyone.</li>
    </ol>
  </div>

  {{-- CTA Button --}}
  <div style="text-align: center; margin: 28px 0;">
    <a href="{{ url(config('app.url')) }}"
       target="_blank"
       rel="noopener noreferrer"
       style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; padding: 12px 32px; border-radius: 6px; letter-spacing: 0.5px;">
      Click Here to Log In to ESOA
    </a>
    <p style="margin: 8px 0 0 0; color: #555555; font-size: 13px; line-height: 1.6;">
      or copy and paste the following URL into your browser:
    </p>
    <p style="margin: 8px 0 0 0; color: #555555; font-size: 13px; line-height: 1.6;">
      <a href="{{ url(config('app.url')) }}" style="color: #667eea; text-decoration: none;">{{ url(config('app.url')) }}</a>
    </p>
  </div>

  {{-- Security Notice --}}
  <div style="margin: 20px 0; padding: 14px 16px; background-color: #fff0f0; border-left: 4px solid #ef4444; border-radius: 4px;">
    <p style="margin: 0; color: #7f1d1d; font-size: 12px; line-height: 1.6;">
      🔒 <strong>Security Reminder:</strong> ESOA staff will never ask for your password via email, phone, or any other channel.
      If you did not expect this email, please contact your administrator immediately.
    </p>
  </div>

  {{-- Closing --}}
  <p style="margin: 20px 0 0 0; color: #666666; font-size: 13px; line-height: 1.6;">
    If you have any questions or need assistance, please reach out to our support team at
    <a href="mailto:{{ config('vc.contact_email') }}" style="color: #667eea; text-decoration: none;">{{ config('vc.contact_email') }}</a>.
  </p>

  {{-- Signature --}}
  <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e8e8e8;">
    <p style="margin: 0; color: #555555; font-size: 13px; line-height: 1.6;">
      <strong style="color: #333333;">Best Regards,</strong><br>
      <span style="color: #667eea; font-weight: 500;">ESOA System Team</span>
    </p>
  </div>
@endsection
