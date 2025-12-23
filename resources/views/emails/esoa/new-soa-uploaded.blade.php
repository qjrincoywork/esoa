{{-- New SOA Uploaded template --}}

@extends('layouts.mails')

@section('content')

  {{ __('labels.greetings') }}<br><br>
  {{ __('labels.new_soa_uploaded_line_1') }}<br>
  {{ __('labels.new_soa_uploaded_line_2', ['soanum' => $soa->up_soanum]) }}<br>
  {{ __('labels.new_soa_uploaded_line_3', ['actype' => $soa->up_actype ?? 'actype']) }}<br><br>
  {{ __('labels.new_soa_uploaded_line_4') }}<br><br>
  {{ __('labels.system_generated_message') }}

@endsection
