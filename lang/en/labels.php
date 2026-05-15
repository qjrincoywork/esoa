<?php

declare(strict_types=1);

return [
    'new_soa_uploaded_subject' => '[eSOA: :soanum] A new SOA has been uploaded.',
    'greetings' => 'Hello',
    'new_billing_invoice_uploaded' => [
        'subject' => '[eSOA: :soanum] A new Billing Invoice has been uploaded.',
        'greetings' => 'Dear :client_name,',
        'line_1' => 'A new Billing Invoice was uploaded to your eSOA Account.',
        'line_2' => 'Your invoice :soanum is now available in the Customer Portal, ready for review and processing. ',
        'line_3' => 'Please be advised that this invoice is considered final. Any amendments will be handled separately and processed within seven (7) calendar days from receipt of request. Any deletion to the masterlist will be processed through the issuance of a Credit Memo (CM), while for any additional member not included in the original billing, a separate invoice will be issued accordingly.',
        'line_4' => 'To ensure uninterrupted service, we encourage you to make payment on or before the invoice due date.',
        'line_5' => 'Thank you for your continued support and trust in Valucare Health.',
        'line_6' => 'If you have any concerns, please kindly submit a concern or reach out to us at: :contact.',
        'line_7' => 'Sincerely,',
        'line_8' => 'BILLING & COLLECTION DEPARTMENT',
    ],
    'billing_invoice_endorsed' => [
        'subject' => '[eSOA: :soanum] A Billing Invoice Has Been Endorsed.',
        'greetings' => 'Hello,',
        'line_1' => 'A Billing Invoice Has Been Endorsed.',
        'line_2' => 'Billing invoice :soanum is available in the Customer Portal, ready for review and checking. ',
        'line_3' => 'Thank you for your continued support and trust in Valucare Health.',
        'line_4' => 'If you have any concerns, please kindly submit a concern or reach out to us at: :contact.',
    ],
    'billing_invoice_status_changed' => [
        'subject' => '[eSOA: :soanum] Billing Invoice has been :status_label.',
        'greetings' => 'Hello,',
        'line_1_1' => 'A Billing Invoice has been :status_label.',
        'line_1_2' => 'A Billing Invoice has been endorsed to accounting.',
        'line_2' => 'Billing invoice :soanum is available in the Customer Portal, ready for review and checking.',
        'line_3' => 'Thank you for your continued support and trust in Valucare Health.',
        'line_4' => 'If you have any concerns, please kindly submit a concern or reach out to us at: :contact.',
    ],
    'billing_invoice_final_reminder' => [
        'subject' => '[eSOA: :soanum] Billing Invoice Final Reminder',
        'greetings' => 'Dear Valued Customer,',
        // 'line_1' => 'This is a reminder that your invoice :soanum is due on :due_date. Please make payment on or before the due date to avoid any late fees.',
        'line_1' => 'This is a reminder that your invoice :soanum is due on :due_date.',
        'line_2' => 'We kindly remind you to settle the balance at your earliest convenience to avoid any potential disruption in your health insurance coverage and related benefits.',
        'line_3' => 'If payment has already been made, please disregard this notice. Otherwise, we would appreciate it if you could send us copy of the proof of payment and a copy of BIR Form 2307 (if 2% EWT was deducted) to ensure proper recording and reconciliation on our end.',
        'line_4' => 'Should you need assistance or require a copy of the billing statement, please do not hesitate to contact us at the numbers provided below.',
        'line_4_1' => ':contact.',
        'line_5' => 'Thank you for your immediate attention to this matter.',
        'line_6' => 'Sincerely,',
        'line_7' => ':fullname',
    ],
    'new_soa_uploaded_line_1' => 'A new SOA was uploaded to your eSOA Account.',
    'new_soa_uploaded_line_2' => 'SOA no: :soanum',
    'new_soa_uploaded_line_3' => 'Type: :actype',
    'new_soa_uploaded_line_4' => 'Please log in to your e-SOA account by going to www.valucarehealth.com/esoa to view the uploaded SOA.',
    'system_generated_message' => '***This is a system generated message. Do not reply to this message***',
    // Modify or append as needed
];
