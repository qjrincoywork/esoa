{{-- New Billing invoice Uploaded template --}}

@extends('layouts.mails')

@section('content')

  {{ __('labels.greetings_billing_invoice') }}<br><br>
  {{ __('labels.new_billing_invoice_uploaded.line_1') }}<br>
  {{ __('labels.new_billing_invoice_uploaded.line_2', ['soanum' => $soa->soa_number ]) }}<br>
  {{ __('labels.new_billing_invoice_uploaded.line_3') }}<br><br>
  {{ __('labels.new_billing_invoice_uploaded.line_4') }}<br><br>
  {{ __('labels.new_billing_invoice_uploaded.line_5') }}<br><br>
  {{ __('labels.new_billing_invoice_uploaded.line_6') }}<br><br>
  {{ __('labels.new_billing_invoice_uploaded.line_7') }}<br><br>
  {{ __('labels.new_billing_invoice_uploaded.line_8') }}<br><br>
@endsection
