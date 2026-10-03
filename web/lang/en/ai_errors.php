<?php

// Safe messages shown to users when analysis fails. Technical details are only written to the logs.
return [
    'service_unavailable' => 'The analysis service is currently unavailable. You can retry shortly.',
    'timeout' => 'The analysis took longer than allowed. Try a shorter or smaller file.',
    'rejected' => 'The analysis service rejected this file. It may be corrupt or in an unsupported format.',
    'invalid_response' => 'The analysis service response could not be understood. The issue was logged.',
    'no_model' => 'No analysis model is currently available for this type of content.',
    'file_missing' => 'The original file is no longer available (it may have been deleted after the retention period).',
    'unexpected' => 'An unexpected error occurred during analysis. The issue was logged.',
];
