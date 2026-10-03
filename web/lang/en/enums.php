<?php

return [
    'media_type' => [
        'image' => 'Image',
        'video' => 'Video',
        'audio' => 'Audio',
    ],

    'request_status' => [
        'pending' => 'Pending analysis',
        'uploading' => 'Uploading file',
        'analyzing' => 'Analyzing content',
        'completed' => 'Analysis complete',
        'failed' => 'Analysis failed',
        'needs_review' => 'Needs human review',
    ],

    'classification' => [
        'real' => 'Likely authentic',
        'uncertain' => 'Inconclusive result',
        'fake' => 'Likely manipulated',
    ],

    'risk_level' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ],

    'severity' => [
        'info' => 'Info',
        'low' => 'Weak',
        'medium' => 'Moderate',
        'high' => 'Strong',
    ],

    'user_status' => [
        'active' => 'Active',
        'suspended' => 'Suspended',
    ],

    'account_type' => [
        'general' => 'General user',
        'social_media' => 'Social media user',
        'journalist' => 'Journalist / media professional',
        'content_creator' => 'Content creator',
        'student' => 'Student',
        'researcher' => 'Researcher',
        'institution' => 'Educational institution',
        'media_org' => 'Media organization',
    ],
];
