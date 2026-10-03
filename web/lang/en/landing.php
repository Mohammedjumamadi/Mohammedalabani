<?php

// Copy for the public interface (home, sign-in/sign-up, guest progress and result pages).
// Everything listed here exists in the system (see lang/en/indicators.php and lang/en/enums.php)
return [
    'page_title' => 'Verify the authenticity of images, video and audio',
    'site_name' => 'AI Media Verifier',
    'logo_alt' => 'AI Media Verifier logo',
    'illustration_alt' => 'Illustration of an image, a video and an audio recording being analysed',
    'menu' => 'Main menu',
    'toggle_menu' => 'Open menu',
    'language' => 'Language',

    'nav' => [
        'home' => 'Home',
        'about_group' => 'About',
        'about' => 'About the system',
        'how' => 'How it works',
        'supported' => 'Supported formats',
        'awareness' => 'Digital awareness',
        'faq' => 'FAQ',
        'contact' => 'Contact us',
        'login' => 'Sign in',
        'register' => 'Create account',
        'dashboard' => 'Dashboard',
    ],

    'hero' => [
        'title' => 'Check images, video and audio',
        'highlight' => 'before you trust or share them',
        'text' => 'Upload the file, wait a few minutes for the analysis, then read a probabilistic estimate of manipulation or AI generation along with the indicators behind it.',
        'cta_try' => 'Start verifying',
        'cta_how' => 'How to read the result',
        'cta_new' => 'Start a new verification',
        'steps' => ['Upload a file', 'Wait for the analysis', 'Read the result and its indicators'],
    ],

    'upload' => [
        'signed_in_title' => 'Upload from your dashboard',
        'signed_in_text' => 'You are signed in: upload from your dashboard so results and reports are saved to your history.',
        'types_label' => 'What kind of file is it?',
        'file_label' => 'Choose the file',
        'case_label' => 'New verification case',
        'max' => 'up to :size',
        'change_file' => 'Change',
    ],

    'reading' => [
        'title' => 'How to read the result',
        'text' => 'The system estimates the probability of manipulation from 0 to 100, then places the file in one of three bands set by the platform administrator. There is no “certain” band.',
        'real_range' => 'Below :value%',
        'uncertain_range' => 'From :real% to below :fake%',
        'fake_range' => ':value% or more',
        'real_text' => 'No strong signs of manipulation were found. Still check the source and context of the content.',
        'uncertain_text' => 'The indicators conflict or are weak; the case needs a human review.',
        'fake_text' => 'Strong signs of manipulation or generation were found. This is not conclusive proof.',
        'confidence' => 'The confidence score shows how clear the decision was for the model, not how true the content is.',
    ],

    'checks' => [
        'title' => 'What the system checks',
        'text' => 'Each media type has its own analysis module, and every item below appears in your report as a separate indicator with a score and an explanation.',
        'image_title' => 'Images',
        'image_items' => [
            'A model that detects AI-generated images',
            'A model that detects fake and swapped faces',
            'Error level analysis (ELA) and compression traces',
            'EXIF metadata inspection',
        ],
        'video_title' => 'Video',
        'video_items' => [
            'Analysis of representative frames',
            'Suspicious frames shown with their timestamps',
            'Temporal consistency and face stability checks',
            'Analysis of the clip’s audio track',
        ],
        'audio_title' => 'Audio',
        'audio_items' => [
            'A model that detects generated or cloned voices',
            'Spectrum inspection',
            'Detection of pauses and pitch jumps',
            'Cut-and-splice traces with timestamps',
        ],
    ],

    'closing' => [
        'title' => 'Keep a record of your checks',
        'text' => 'With a free account your results are saved, and you can print or download their reports with a reference number anyone can verify.',
        'button' => 'Create a free account',
    ],

    'footer' => [
        'about' => 'A web platform that uses open-source AI models to help examine images, video clips and audio recordings.',
        'platform' => 'Platform',
        'legal' => 'Terms & privacy',
        'rights' => '© :year AI Media Verifier. All rights reserved.',
    ],

    'auth' => [
        'resume_title' => 'Your analysis result is waiting',
        'resume_text' => 'After you sign in or create your account you will return straight to the same result to print its report — no need to upload the file again.',
        'show_password' => 'Show password',
        'hide_password' => 'Hide password',
    ],

    // Guest progress and result pages
    'case' => [
        'label' => 'Verification case',
        'progress_lead' => 'Your file is being analysed. This page updates by itself and moves to the result when it is done.',
        'estimate_title' => 'A probabilistic reading, not a final verdict',
        'estimate_text' => 'This result comes from an assistive system and can be wrong. Review the indicators below and check the source of the content before making any decision.',
        'probabilities' => 'Probabilities estimated by the system',
        'indicators_text' => 'The factors behind the estimate, ordered from strongest to weakest.',
        'failed_title' => 'The analysis did not finish',
        'failed_hint' => 'No result was produced for this file, and we will not show an estimate that is not real.',
        'try_again' => 'Upload another file',
        'pending_title' => 'The analysis has not finished yet',
        'details' => 'File and model details',
    ],
];
