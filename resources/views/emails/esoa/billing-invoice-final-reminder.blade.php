{{-- New Billing invoice Uploaded template --}}

@extends('layouts.mails')

@section('content')

  {{ __('labels.billing_invoice_final_reminder.greetings') }}<br><br>
  {{ __('labels.billing_invoice_final_reminder.line_1', ['soanum' => $billingInvoice->soa_number ??  '']) }}<br>
  {{ __('labels.billing_invoice_final_reminder.line_2') }}<br><br>
  {{ __('labels.billing_invoice_final_reminder.line_3') }}<br><br>
  {{ __('labels.billing_invoice_final_reminder.line_4') }}<br><br>
  {{ __('labels.billing_invoice_final_reminder.line_4_1', ['contact' => $billingInvoice->contact ?? '']) }}<br><br>
  {{ __('labels.billing_invoice_final_reminder.line_5') }}<br><br>
  {{ __('labels.billing_invoice_final_reminder.line_6') }}<br><br>
  {{ __('labels.billing_invoice_final_reminder.line_7', ['fullname' => $billingInvoice->user->first_name . ' ' . $billingInvoice->user->last_name ?? '']) }}<br><br>
@endsection
