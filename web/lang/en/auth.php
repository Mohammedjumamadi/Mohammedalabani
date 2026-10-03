<?php

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'suspended' => 'Your account is currently suspended. Please contact the platform administrators.',

    'login' => [
        'title' => 'Sign in',
        'heading' => 'Welcome back',
        'subheading' => 'Sign in to continue verifying your content.',
        'submit' => 'Sign in',
        'remember' => 'Remember me',
        'forgot' => 'Forgot your password?',
        'no_account' => "Don't have an account?",
        'create_account' => 'Create a new account',
    ],

    'register' => [
        'title' => 'Create account',
        'heading' => 'Create a new account',
        'subheading' => 'Create your account to upload files and verify them.',
        'submit' => 'Create account',
        'have_account' => 'Already have an account?',
        'terms_prefix' => 'I agree to the',
        'terms_link' => 'Terms of Use',
        'terms_and' => 'and',
        'privacy_link' => 'Privacy Policy',
        'closed' => 'Registration is currently closed. Please try again later.',
        'welcome' => 'Your account has been created. Welcome to the platform.',
        // 3-step sign-up wizard
        'steps' => [
            'label' => 'Sign-up steps',
            'step_1' => 'Name & email',
            'step_2' => 'Phone & city',
            'step_3' => 'Password',
            'progress' => 'Step :current of :total',
            'next' => 'Next',
            'back' => 'Back',
            'phone_hint' => 'Example: +218 91 234 5678',
            'city_placeholder' => 'Type your city',
        ],
    ],

    'forgot' => [
        'title' => 'Password recovery',
        'heading' => 'Recover your password',
        'subheading' => "Enter your email and we'll send you a link to reset your password.",
        'submit' => 'Send reset link',
        'back' => 'Back to sign in',
        // Same message whether or not the email exists, to avoid account enumeration
        'sent' => 'If the email address is registered with us, a password reset link has been sent to it.',
    ],

    'reset' => [
        'title' => 'Reset password',
        'heading' => 'Set a new password',
        'subheading' => 'Choose a strong new password for your account.',
        'submit' => 'Save password',
        'done' => 'Your password has been reset. You can sign in now.',
    ],

    'brand' => [
        'headline' => 'Verify content before you trust it',
        'text' => 'A platform that uses AI to examine images, videos and audio recordings and gives you a clear report with the indicators and a confidence estimate.',
        'point_1' => 'Detect deepfakes and AI-generated images',
        'point_2' => 'Detailed reports you can download and share',
        'point_3' => 'Your files stay private and can be deleted any time',
    ],

    'logout' => 'Sign out',
    'logged_out' => 'You have been signed out.',
];
