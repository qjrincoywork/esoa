{{-- Billing Invoice Due Reminder template --}}

@extends('layouts.mails')

@section('content')

<p style="margin: 0 0 16px 0; color: #333333; font-size: 16px; line-height: 1.6;">
  {{ __('labels.billing_invoice_due_reminder.greeting', ['name' => auth()->user()?->first_name ?? 'Valued Customer']) }}
</p>

<p style="margin: 0 0 24px 0; color: #333333; font-size: 15px; line-height: 1.6;">
  {{ __('labels.billing_invoice_due_reminder.intro', ['aging' => $agingLabel, 'count' => $soaCount]) }}
</p>

<table role="presentation" style="width: 100%; border-collapse: collapse; margin: 0 0 24px 0;">
  <tr>
    <td style="padding: 20px; background-color: #f5f7ff; border: 1px solid #e0e4f5; border-radius: 8px; text-align: center;">
      <p style="margin: 0 0 8px 0; color: #667eea; font-size: 18px; font-weight: 600; line-height: 1.4;">
        {{ $agingLabel }}
      </p>
      <p style="margin: 0 0 16px 0; color: #555555; font-size: 14px; line-height: 1.5;">
        {{ __('labels.billing_invoice_due_reminder.summary', ['count' => $soaCount]) }}
      </p>
      <a href="{{ $listUrl }}"
        style="display: inline-block; padding: 12px 24px; background-color: #667eea; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; border-radius: 6px;">
        {{ __('labels.billing_invoice_due_reminder.view_list') }}
      </a>
    </td>
  </tr>
</table>

<p style="margin: 16px 0; color: #d32f2f; font-size: 14px; line-height: 1.6; padding: 12px; background-color: #ffebee; border-left: 4px solid #d32f2f; border-radius: 4px;">
  <strong>{{ __('labels.billing_invoice_due_reminder.warning') }}</strong><br>
  {{ __('labels.billing_invoice_due_reminder.warning_text') }}
</p>

<p style="margin: 16px 0; color: #333333; font-size: 14px; line-height: 1.6;">
  {{ __('labels.billing_invoice_due_reminder.action_text') }}
</p>

<p style="margin: 16px 0 0 0; color: #333333; font-size: 14px; line-height: 1.6;">
  {{ __('labels.billing_invoice_due_reminder.closing') }}<br>
  <strong>{{ __('labels.esoa_team') }}</strong>
</p>

@endsection
