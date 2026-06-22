{{-- New Billing Invoice Uploaded Template --}}

@extends('layouts.mails')

@section('content')
  <!-- Greeting Section -->
  <p style="margin: 0 0 16px 0; color: #333333; font-size: 14px; line-height: 1.6;">
    {{ __('labels.new_billing_invoice_uploaded.greetings', ['client_name' => $soa->client_name ]) }}
  </p>

  <!-- Main Message Section -->
  <div style="background-color: #f0f7ff; border-left: 4px solid #667eea; padding: 16px; margin: 20px 0; border-radius: 4px;">
    <p style="margin: 0 0 8px 0; color: #333333; font-size: 14px; line-height: 1.6; font-weight: 500;">
      {{ __('labels.new_billing_invoice_uploaded.line_1') }}
    </p>
    <p style="margin: 0; color: #555555; font-size: 14px; line-height: 1.6;">
      {!! str_replace(':soanum', "<strong style='color: #333333; font-weight: 700;'>"
        . "<a href='" . e(route('soas.view_billing_invoice', ['id' => $soa->id])) . "'
            target='_blank'
            rel='noopener noreferrer'
            title='Click to open the billing invoice'
          >"
        . e($soa->soa_number)
        . '</strong></a>', __('labels.new_billing_invoice_uploaded.line_2', ['soanum' => ':soanum']))
      !!}
    </p>
  </div>

  <!-- Information Details -->
  <div style="margin: 20px 0; padding: 16px; background-color: #fafafa; border-radius: 6px; border: 1px solid #e8e8e8;">
    <h3 style="margin: 0 0 12px 0; color: #333333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
      📋 {{ __('labels.new_billing_invoice_uploaded.line_3') }}
    </h3>
    <p style="margin: 0 0 12px 0; color: #555555; font-size: 13px; line-height: 1.6;">
      {{ __('labels.new_billing_invoice_uploaded.line_4') }}
    </p>
    <p style="margin: 0 0 12px 0; color: #555555; font-size: 13px; line-height: 1.6;">
      {{ __('labels.new_billing_invoice_uploaded.line_5') }}
    </p>
  </div>

  <!-- Contact Information -->
  <div style="margin: 20px 0; padding: 16px; background-color: #f5f9ff; border-radius: 6px; border: 1px solid #d4e4f7;">
    <h3 style="margin: 0 0 12px 0; color: #333333; font-size: 14px; font-weight: 600; letter-spacing: 0.5px;">
      ☎️ {{ __('labels.new_billing_invoice_uploaded.line_6', ['contact' => $soa->contact ]) }}
    </h3>
  </div>

  <!-- Additional Information -->
  <p style="margin: 20px 0 12px 0; color: #555555; font-size: 13px; line-height: 1.6;">
    {{ __('labels.new_billing_invoice_uploaded.line_7') }}
  </p>

  <!-- Closing Section -->
  <p style="margin: 20px 0 0 0; color: #666666; font-size: 13px; line-height: 1.6;">
    {{ __('labels.new_billing_invoice_uploaded.line_8') }}
  </p>

  <!-- Signature -->
  <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e8e8e8;">
    <p style="margin: 0; color: #555555; font-size: 13px; line-height: 1.6;">
      <strong style="color: #333333;">Best Regards,</strong><br>
      <span style="color: #667eea; font-weight: 500;">ESOA System Team</span>
    </p>
  </div>
@endsection
