{{-- Billing Invoice Status Changed Template --}}

@extends('layouts.mails')

@section('content')
  <!-- Main Message Section -->
  <div style="background-color: #f0f7ff; border-left: 4px solid #667eea; padding: 16px; margin: 20px 0; border-radius: 4px;">
    <p style="margin: 0 0 16px 0; color: #333333; font-size: 14px; line-height: 1.6;">
      {{ __('labels.billing_invoice_status_changed.greetings', ['client_name' => $soa->client_name ]) }}
    </p>

    <p style="margin: 0 0 8px 0; color: #333333; font-size: 14px; line-height: 1.6; font-weight: 500;">
      {{
          $soa->status == \App\Enums\SoaStatus::ENDORSED
              ? __('labels.billing_invoice_status_changed.line_1_2')
              : __('labels.billing_invoice_status_changed.line_1_1', [
                  'status_label' => \App\Enums\SoaStatus::label((int) $soa->status) ?? 'N/A'
              ])
      }}
    </p>
    <p style="margin: 0; color: #555555; font-size: 14px; line-height: 1.6;">
      {!! str_replace(':soanum', "<strong style='color: #333333; font-weight: 700;'>"
        . "<a href='" . route('soas.billing_attachments', ['id' => $soa->id, 'type' => 'pdf']) . "'"
            . " target='_blank'"
            . " rel='noopener noreferrer'"
            . " title='Click to open the billing invoice'"
          . ">"
        . $soa->soa_number
        . '</strong></a>', __('labels.billing_invoice_status_changed.line_2', ['soanum' => ':soanum']))
      !!}
    </p>
  </div>

  <div style="margin: 20px 0; padding: 16px; background-color: #fafafa; border-radius: 6px; border: 1px solid #e8e8e8;">
    <h3 style="margin: 0 0 12px 0; color: #333333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
      📋 {{ __('labels.billing_invoice_status_changed.line_3') }}
    </h3>
  </div>

  <div style="margin: 20px 0; padding: 16px; background-color: #f5f9ff; border-radius: 6px; border: 1px solid #d4e4f7;">
    <h3 style="margin: 0 0 12px 0; color: #333333; font-size: 14px; font-weight: 600; letter-spacing: 0.5px;">
      ☎️ {{ __('labels.billing_invoice_status_changed.line_4', ['contact' => config('vc.contact_email') ]) }}
    </h3>
  </div>

  <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e8e8e8;">
    <p style="margin: 0; color: #555555; font-size: 13px; line-height: 1.6;">
      <strong style="color: #333333;">Best Regards,</strong><br>
      <span style="color: #667eea; font-weight: 500;">ESOA System Team</span>
    </p>
  </div>
@endsection
