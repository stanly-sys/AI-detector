<?php
/**
 * Presentation content for the active image-analysis application.
 */
declare(strict_types=1);

$siteName = 'AI Image Detector';
$navigation = [
    ['Home', '#home'],
    ['How it works', '#how-it-works'],
    ['Features', '#features'],
    ['About', '#about'],
];

$benefits = [
    ['shield-check', 'Multiple detection methods'],
    ['zap', 'Clear, understandable results'],
    ['sparkles', 'Simple to use'],
];

$methods = [
    [
        '01', 'shield-check', 'Watermark detection',
        'SynthID is not checked automatically. Use the manual Google SynthID link below for supported images.',
        'Manual check',
    ],
    [
        '02', 'file-check', 'Content provenance',
        'Inspect supported signed credentials and metadata for clues about an image’s history.',
        'Source verification',
    ],
    [
        '03', 'brain', 'ML classification',
        'Estimate whether image patterns are consistent with AI-generated content using a model.',
        'Model-based estimate',
    ],
];

$steps = [
    ['01', 'upload-cloud', 'Upload a picture', 'Select or drop a JPG, PNG or WebP image into the upload area.'],
    ['02', 'scan-search', 'Check for signals', 'The server checks signed Content Credentials and estimates image patterns with a machine-learning model.'],
    ['03', 'chart-no-axes-column', 'Understand the result', 'See each check separately, including uncertainties and missing evidence.'],
];

$faq = [
    ['Does “no watermark found” mean the image is real?', 'No. It means a supported watermark was not detected. AI-generated images may not have any watermark at all.'],
    ['Is the detection feature active?', 'Yes. When you choose Analyze, the image is sent to this server for model analysis and Content Credentials verification. Model predictions are estimates, not proof.'],
    ['What file types can I upload?', 'The interface accepts valid JPEG, PNG and WebP images up to 2 MB. The server also limits image dimensions.'],
    ['Will my uploaded image be stored?', 'The server temporarily stages the image for analysis and removes it afterward. Only the prediction scores are stored in the database.'],
];
