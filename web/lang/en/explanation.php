<?php

return [
    'media' => ['image' => 'The image', 'video' => 'The video', 'audio' => 'The audio recording'],

    'real' => 'The model found no strong signs of manipulation in :media; it estimated the manipulation probability at about :probability%, so the content is likely authentic.',
    'uncertain' => 'The model could not decide conclusively; it estimated the manipulation probability at about :probability%. Human review and source verification are recommended.',
    'fake' => 'The model detected signs suggesting manipulation in :media; it estimated the manipulation probability at about :probability%, so the content is likely manipulated.',

    'indicators' => 'Main indicators: :list.',
    'limits' => 'This result is a probabilistic estimate from an assistive system, not conclusive proof.',
];
