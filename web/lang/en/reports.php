<?php

return [
    'pdf' => [
        'subtitle' => 'Digital content verification report',
        'verify_title' => 'Verify report authenticity',
        'verify_text' => 'Scan the QR code or open the link below to match this report against the platform records using its reference number.',
    ],
    // Print-friendly report (HTML printed from the browser)
    'print' => [
        'button' => 'Print report',
        'title' => 'Verification result report',
        'print_now' => 'Print',
        'back' => 'Back to the result',
        'reference' => 'Reference number',
        'uploaded_at' => 'Uploaded on',
        'analyzed_at' => 'Analysed on',
        'printed_at' => 'Printed on',
        'summary' => 'Result summary',
        'file' => 'File details',
        'indicators' => 'Explanatory indicators',
        'model' => 'Model used',
        'severity' => 'Severity',
        'score' => 'Score',
        'time' => 'Time',
        'details' => 'Explanation',
        'claimed' => 'The analysis result has been saved to your account. You can print its report now.',
        'claim_unavailable' => 'The previous analysis result is no longer available in this browser. You can upload the file again from your dashboard.',
    ],

    'public' => [
        'title' => 'Shared verification report',
        'shared_by' => 'Shared by',
    ],
    'verify' => [
        'title' => 'Verify a report',
        'intro' => 'Enter the reference number printed on the PDF report to confirm it was issued by the platform and matches its records.',
        'check' => 'Verify',
        'found' => 'The report exists and matches the platform records',
        'not_found' => 'No report was found with this reference number. Check that it was typed correctly.',
        'privacy_note' => 'Only limited details are shown here; the report owner and the file itself are not revealed.',
    ],
];
