@extends('layouts.mails')

@section('content')
  <p>Hello,</p>

  <p>
    A concern was saved in ESAO by {{ $concern->user?->name ?? $concern->user?->email ?? 'an authenticated user' }}.
  </p>

  <div style="background-color: #f5f7fb; border-left: 4px solid #4f46e5; padding: 16px; margin: 20px 0; border-radius: 4px;">
    <p style="margin: 0 0 8px 0;"><strong>Title:</strong> {{ $concern->title ?? 'N/A' }}</p>
    <p style="margin: 0 0 8px 0;"><strong>Type:</strong> {{ $concern->type ?? 'N/A' }}</p>
    <p style="margin: 0 0 8px 0;"><strong>Status:</strong> {{ $concern->status ?? 'N/A' }}</p>
    <p style="margin: 0;"><strong>Description:</strong> {{ $concern->description ?? 'None' }}</p>
  </div>

  <p>
    This notification was sent to {{ config('vc.contact_email') }} and copied to the request creator.
  </p>

  <p>Thank you,<br>ESOA System Team</p>
@endsection
