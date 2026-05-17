{{-- Billing Invoice Due Reminder template --}}

@extends('layouts.mails')

@section('content')

<p style="margin: 0 0 16px 0; color: #333333; font-size: 16px; line-height: 1.6;">
  {{ __('labels.billing_invoice_due_reminder.greeting', ['name' => auth()->user()?->first_name ?? 'Valued Customer']) }}
</p>

<p style="margin: 0 0 16px 0; color: #333333; font-size: 15px; line-height: 1.6;">
  {{ __('labels.billing_invoice_due_reminder.intro', ['aging' => $agingLabel, 'count' => $soaCount]) }}
</p>

<p style="margin: 0 0 16px 0; color: #555555; font-size: 14px; line-height: 1.6; font-weight: 500;">
  {{ __('labels.billing_invoice_due_reminder.list_heading') }}
</p>

{{-- SOAs Table --}}
<table style="width: 100%; border-collapse: collapse; margin: 16px 0; background-color: #f9f9f9;">
  <thead>
    <tr style="background-color: #667eea; color: #ffffff;">
      <th style="padding: 12px; text-align: left; font-weight: 600; border: 1px solid #e0e0e0;">{{ __('labels.soa_number') }}</th>
      <th style="padding: 12px; text-align: left; font-weight: 600; border: 1px solid #e0e0e0;">{{ __('labels.account_code') }}</th>
      <th style="padding: 12px; text-align: left; font-weight: 600; border: 1px solid #e0e0e0;">{{ __('labels.due_date') }}</th>
      <th style="padding: 12px; text-align: right; font-weight: 600; border: 1px solid #e0e0e0;">{{ __('labels.amount') }}</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($soas as $soa)
      <tr style="border-bottom: 1px solid #e0e0e0;">
        <td style="padding: 12px; border: 1px solid #e0e0e0; font-size: 14px;">
          <span style="color: #667eea; font-weight: 500;">{{ $soa->soa_number }}</span>
        </td>
        <td style="padding: 12px; border: 1px solid #e0e0e0; font-size: 14px; color: #555555;">
          {{ $soa->account_code }}
        </td>
        <td style="padding: 12px; border: 1px solid #e0e0e0; font-size: 14px; color: #555555;">
          {{ $soa->due_date?->format('M d, Y') ?? 'N/A' }}
        </td>
        <td style="padding: 12px; border: 1px solid #e0e0e0; font-size: 14px; color: #333333; font-weight: 500; text-align: right;">
          {{ number_format($soa->amount ?? 0, 2) }}
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="4" style="padding: 20px; text-align: center; color: #999999;">
          {{ __('labels.no_records_found') }}
        </td>
      </tr>
    @endforelse
  </tbody>
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
