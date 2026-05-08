@extends('layouts.mails')

@section('content')
  <p>Hello,</p>

  <p>
    An account payment record has been saved in ESOA by {{ $accountPayment->user?->name ?? $accountPayment->user?->email ?? 'an authenticated user' }}.
  </p>

  <div style="background-color: #f5f7fb; border-left: 4px solid #4f46e5; padding: 16px; margin: 20px 0; border-radius: 4px;">
    <p style="margin: 0 0 8px 0;"><strong>Deposit Date:</strong> {{ \App\Helpers\CommonHelper::formatDate($accountPayment->deposit_date) ?? 'N/A' }}</p>
    <p style="margin: 0 0 8px 0;"><strong>Payment Mode:</strong> {{ \App\Enums\AccountPaymentMode::label((int) $accountPayment->mode_of_payment) ?? 'N/A' }}</p>
    <p style="margin: 0 0 8px 0; white-space: pre-line;"><strong>Remarks:</strong> {{ $accountPayment->remarks ?? 'None' }}</p>
    <p style="margin: 0;"><strong>Reference ID:</strong> {{ $accountPayment->id }}</p>
  </div>

  <p>
    This notification was sent to {{ config('vc.contact_email') }} and copied to the account payment creator.
  </p>

  <p>Thank you,<br>ESOA System Team</p>
@endsection
