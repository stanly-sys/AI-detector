<?php

/**
 * AI Image Detector — finished front-end interface.
 * This interface connects to the local image-analysis API.
 */

declare(strict_types=1);
require __DIR__ . '/includes/site-data.php';
require __DIR__ . '/includes/icons.php';
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f4f7ed">
  <meta name="description" content="AI Image Detector: an approachable interface for examining digital watermarks, image provenance, and machine-learning predictions.">
  <title><?= esc($siteName) ?> Look closer. Know more.</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/animations.css">
  <script src="assets/js/app.js" defer></script>
</head>

<body id="home">
  <a class="skip-link" href="#main">Skip to content</a>
  <div class="site-top">
    <div class="ambient ambient-one" aria-hidden="true"></div>
    <div class="ambient ambient-two" aria-hidden="true"></div>
    <header class="site-header container">
      <nav class="nav-shell" aria-label="Main navigation">
        <a class="brand" href="#home" aria-label="AI Image Detector home">
          <span class="brand-icon" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 28 28" fill="none">
              <circle cx="13" cy="12" r="7.2" stroke="currentColor" stroke-width="2.6" />
              <path d="m18.5 17.7 5.1 5.1" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" />
              <circle cx="13" cy="12" r="2" fill="currentColor" />
            </svg>
          </span>
          <span><b>AI</b> Image Detector</span>
        </a>
        <div class="nav-links" id="navLinks">
          <?php foreach ($navigation as [$label, $href]): ?>
            <a href="<?= esc($href) ?>" class="nav-link<?= $href === '#home' ? ' is-active' : '' ?>"><?= esc($label) ?></a>
          <?php endforeach; ?>
        </div>
        <div class="nav-actions">
          <a class="btn btn-dark btn-header" href="#upload">Try it now <?= icon('arrow-up-right', 17) ?></a>
          <button type="button" class="menu-button" id="menuButton" aria-expanded="false" aria-controls="navLinks" aria-label="Open menu"><?= icon('menu', 23) ?></button>
        </div>
      </nav>
    </header>

    <main id="main">
      <section class="hero container" aria-labelledby="heroTitle">
        <div class="hero-copy">
          <p class="eyebrow"><span class="status-dot"></span> IMAGE AUTHENTICITY, MADE SIMPLE <span class="eyebrow-arrow">↗</span></p>
          <h1 id="heroTitle">Look closer.<br>Know what's <span class="highlight">AI-made.<svg aria-hidden="true" viewBox="0 0 230 18" preserveAspectRatio="none">
                <path d="M4 12C55 5 155 7 225 8" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" />
              </svg></span></h1>
          <p class="hero-description">Not everything online is what it seems. Explore a clearer way to check an image's watermark, origin, and possible AI-generated patterns.</p>
          <div class="hero-actions">
            <a class="btn btn-dark btn-hero" href="#upload">Analyze an image <?= icon('arrow-up-right', 19) ?></a>
            <a href="#how-it-works" class="text-link">How it works <?= icon('arrow-right', 17) ?></a>
          </div>
          <ul class="benefit-list">
            <?php foreach ($benefits as [$glyph, $label]): ?>
              <li><span class="benefit-icon"><?= icon($glyph, 20) ?></span><span><?= esc($label) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <div class="hero-footnote"><span class="tiny-avatars" aria-hidden="true"><i><?= icon('image', 15) ?></i><i><?= icon('shield-check', 15) ?></i><i><?= icon('brain', 15) ?></i></span><span>Built for curious minds and a more transparent internet.</span></div>
        </div>

        <div class="hero-workspace" aria-label="Image upload and report preview">
          <section class="upload-card" id="upload" aria-labelledby="uploadTitle">
            <div class="card-topline"><span class="mini-label"><span class="mini-dot"></span> START HERE</span><span class="card-index">01 / UPLOAD</span></div>
            <div class="dropzone" id="dropzone" role="button" tabindex="0" aria-label="Choose an image to upload; or drag and drop a file">
              <div class="upload-graphic" id="uploadGraphic"><span class="graphic-ring ring-one"></span><span class="graphic-ring ring-two"></span><span class="upload-cloud-icon"><?= icon('upload-cloud', 36) ?></span></div>
              <div class="selected-preview" id="selectedPreview" hidden><img id="selectedImage" alt="Your selected image preview"><span class="preview-overlay">IMAGE SELECTED</span></div>
              <h2 id="uploadTitle">Drop your image here</h2>
              <p class="upload-support" id="uploadSupport">Drag &amp; drop or browse your files</p>
              <div class="file-badge">JPG <span>•</span> PNG <span>•</span> WEBP <span>•</span> MAX 2 MB</div>
            </div>
            <input type="file" id="imageInput" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" class="visually-hidden" aria-label="Choose an image" tabindex="-1">
            <fieldset class="model-picker" id="modelSelection">
              <legend>Choose model</legend>
              <div class="model-picker-options">
                <label><input type="radio" name="models" value="both" checked><span>Both models</span></label>
                <label><input type="radio" name="models" value="sdxl_detector"><span>SDXL only</span></label>
                <label><input type="radio" name="models" value="community_forensics"><span>Community only</span></label>
              </div>
            </fieldset>
            <div class="upload-actions">
              <button type="button" class="btn btn-dark btn-full" id="chooseButton"><?= icon('image', 18) ?> Choose a file</button>
              <button type="button" class="btn btn-lime btn-full" id="analyzeButton">Analyze image <?= icon('arrow-right', 18) ?></button>
            </div>
            <div class="upload-message" id="uploadMessage" role="status" aria-live="polite"><span class="message-icon"><?= icon('lock', 15) ?></span><span>Choose a model and image to analyze.</span></div>
            <button type="button" id="removeButton" class="remove-file" hidden><?= icon('close', 15) ?> Remove selected image</button>
          </section>



          <section class="report-card" id="report" aria-labelledby="reportTitle">
            <div class="report-heading">
              <div><span class="small-overline">INSIDE THE REPORT</span>
                <h2 id="reportTitle">Analysis preview</h2>
              </div><span class="report-dots" aria-hidden="true"><i></i><i></i><i></i></span>
            </div>
            <div class="report-image-wrap"><img id="reportImage" src="assets/img/sample-dog.jpg" alt="Example golden retriever image from the illustrative demo">
              <div class="image-corner-label" id="imageCornerLabel"><?= icon('eye', 13) ?> EXAMPLE IMAGE</div>
            </div>
            <div class="report-state sample" id="reportState"><span class="state-emblem"><?= icon('sparkles', 18) ?></span>
              <div><b id="reportStateTitle">Explore a sample report</b>
                <p id="reportStateDetail">Illustrative results — not a real scan</p>
              </div>
            </div>
            <div class="report-metrics" aria-label="Report checks">
              <div class="metric-row"><span class="metric-left"><span class="metric-icon"><?= icon('shield-check', 18) ?></span><span>Watermark check</span></span><span class="metric-right" id="watermarkValue">Not detected*</span></div>
              <div class="metric-row"><span class="metric-left"><span class="metric-icon"><?= icon('file-check', 18) ?></span><span>Provenance check</span></span><span class="metric-right" id="provenanceValue">No credentials*</span></div>
              <div class="metric-row"><span class="metric-left"><span class="metric-icon"><?= icon('brain', 18) ?></span><span>ML classification</span></span><span class="metric-right metric-pill" id="mlValue">AI-likely*</span></div>
            </div>
            <p class="report-disclaimer" id="reportDisclaimer">* Sample values for design illustration only. No verification has been performed.</p>

            <button type="button" id="demoButton" class="report-demo-button">Reset sample report <?= icon('refresh', 16) ?></button>
          </section>
        </div>
      </section>
      <section class="trust-bar" aria-label="Platform principles">
        <div class="container trust-inner"><span class="trust-kicker">BUILT AROUND CLARITY</span>
          <div><span class="trust-pip"></span> Understand the evidence</div>
          <div><span class="trust-pip"></span> Avoid false certainty</div>
          <div><span class="trust-pip"></span> Know the limitations</div>
        </div>
      </section>

      <section class="comparison-section" id="model-comparison" aria-labelledby="comparisonTitle">
        <div class="comparison-container">
          <div class="comparison-heading">
            <div><p class="section-kicker"><span></span> SAME IMAGE, SEPARATE RESULTS</p><h2 id="comparisonTitle">AI Model Comparison</h2></div>
            <p>Scores are model outputs, not proof of image origin. No combined score is calculated.</p>
          </div>
          <div class="comparison-grid" aria-live="polite">
            <article class="model-card" id="card-sdxl_detector" data-model="sdxl_detector">
              <div class="model-card-heading"><div><span class="small-overline">MODEL A</span><h3>SDXL Detector</h3></div><span class="model-status" id="sdxl_detector-status">Waiting</span></div>
              <p class="model-prediction" id="sdxl_detector-prediction">No analysis run yet</p>
              <div class="model-score-row"><div><span>AI-generated</span><strong id="sdxl_detector-ai-score">—</strong></div><div class="model-score-track" role="progressbar" aria-label="SDXL AI-generated score" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="sdxl_detector-ai-fill"></span></div></div>
              <div class="model-score-row"><div><span>Human-created</span><strong id="sdxl_detector-human-score">—</strong></div><div class="model-score-track" role="progressbar" aria-label="SDXL human-created score" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="sdxl_detector-human-fill"></span></div></div>
              <div class="model-card-footer"><span id="sdxl_detector-time">Processing time: —</span><span id="sdxl_detector-version"></span></div>
            </article>
            <article class="model-card" id="card-community_forensics" data-model="community_forensics">
              <div class="model-card-heading"><div><span class="small-overline">MODEL B</span><h3>Community Forensics</h3></div><span class="model-status" id="community_forensics-status">Waiting</span></div>
              <p class="model-prediction" id="community_forensics-prediction">No analysis run yet</p>
              <div class="model-score-row"><div><span>AI-generated</span><strong id="community_forensics-ai-score">—</strong></div><div class="model-score-track" role="progressbar" aria-label="Community Forensics AI-generated score" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="community_forensics-ai-fill"></span></div></div>
              <div class="model-score-row"><div><span>Human-created</span><strong id="community_forensics-human-score">—</strong></div><div class="model-score-track" role="progressbar" aria-label="Community Forensics human-created score" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="community_forensics-human-fill"></span></div></div>
              <div class="model-card-footer"><span id="community_forensics-time">Processing time: —</span><span id="community_forensics-version"></span></div>
            </article>
          </div>
          <p class="comparison-outcome" id="comparisonOutcome" role="status">Choose an image and run one or both models to compare their results.</p>
          <div class="evaluation-placeholder"><h3>Measured model performance</h3><p id="evaluationState">Not evaluated yet — labeled test data required.</p><div id="evaluationResults" hidden></div></div>
        </div>
      </section>

      <section id="analytics" class="analytics-section">
        <div class="analytics-container">

          <h2>AI Detection Analytics</h2>
          <p>
            Explore detection statistics and your recent analyses.
          </p>

          <div class="analytics-grid">

            <article class="analytics-card">
              <span>Total Analyses</span>
              <strong id="totalAnalyses">—</strong>
            </article>

            <article class="analytics-card">
              <span>AI-generated predictions</span>
              <strong id="aiTotal">—</strong>
            </article>

            <article class="analytics-card">
              <span>Human-created predictions</span>
              <strong id="humanTotal">—</strong>
            </article>

            <article class="analytics-card">
              <span>Average AI model score</span>
              <strong id="averageAiScore">—</strong>
            </article>

          </div>

          <p id="analyticsMessage" role="status"></p>

          <div class="analytics-history">
            <h3>Recent Analyses on This Browser</h3>

            <ul id="analysisHistory"></ul>

            <div class="analytics-actions">
              <button type="button" id="refreshAnalytics">
                Refresh Statistics
              </button>

              <button type="button" id="clearAnalysisHistory">
                Clear Browser History
              </button>
            </div>
          </div>

        </div>
      </section>

      <!-- Other existing website sections remain above -->

      <!-- Google SynthID verification section -->
      <section class="synthid-section" aria-label="Google SynthID verification">
        <div class="synthid-inner">
          <div class="synthid-content">
            <span class="synthid-eyebrow">ADDITIONAL VERIFICATION</span>

            <h2>Want to check for an invisible AI watermark?</h2>

            <p>
              Use Google's official SynthID Detector to
              check for supported AI watermarks.
            </p>
          </div>

          <a href="https://synthid.com/"
            target="_blank"
            rel="noopener noreferrer"
            class="synthid-action">
            Check with SynthID ↗
          </a>
        </div>
      </section>

    </main>

    </main>
  </div>

  <section class="section methods-section" id="features" aria-labelledby="featuresTitle">
    <div class="container">
      <div class="section-intro">
        <div>
          <p class="section-kicker"><span></span> WHAT'S UNDER THE HOOD</p>
          <h2 id="featuresTitle">More than a simple <span class="underline-accent">yes or no.</span></h2>
        </div>
        <p class="section-sidecopy">One image. Several kinds of evidence. Each check tells a different part of the story.</p>
      </div>
      <div class="method-grid">
        <?php foreach ($methods as [$num, $glyph, $heading, $description, $tag]): ?>
          <article class="method-card">
            <div class="method-head"><span class="method-icon"><?= icon($glyph, 30) ?></span><span class="method-num"><?= esc($num) ?></span></div>
            <h3><?= esc($heading) ?></h3>
            <p><?= esc($description) ?></p><span class="method-tag"><?= esc($tag) ?> <?= icon('arrow-up-right', 14) ?></span>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="methods-note"><?= icon('info', 17) ?> Model scores are estimates, and provenance declarations do not prove an image's origin. SynthID is a separate manual check.</p>
    </div>
  </section>

  <section class="section process-section" id="how-it-works" aria-labelledby="processTitle">
    <div class="container process-layout">
      <div class="process-heading">
        <p class="section-kicker"><span></span> THE PROCESS</p>
        <h2 id="processTitle">Three steps.<br><span class="highlight-small">A clearer picture.</span></h2>
        <p>A simple experience designed to make technical image checks easy to understand.</p><a href="#upload" class="btn btn-dark btn-process">Try the interface <?= icon('arrow-up-right', 18) ?></a>
      </div>
      <div class="steps-list">
        <?php foreach ($steps as [$num, $glyph, $heading, $description]): ?>
          <div class="process-step"><span class="process-num"><?= esc($num) ?></span>
            <div class="process-icon"><?= icon($glyph, 25) ?></div>
            <div class="process-copy">
              <h3><?= esc($heading) ?></h3>
              <p><?= esc($description) ?></p>
            </div><?= icon('arrow-up-right', 18, 'process-arrow') ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section about-section" id="about" aria-labelledby="aboutTitle">
    <div class="container about-grid">
      <div class="about-art" aria-hidden="true">
        <div class="about-orbit about-orbit-one"></div>
        <div class="about-orbit about-orbit-two"></div>
        <div class="about-art-badge badge-left"><?= icon('shield-check', 22) ?></div>
        <div class="about-art-center"><?= icon('scan-search', 64) ?><span>LOOK<br>CLOSER</span></div>
        <div class="about-art-badge badge-right"><?= icon('sparkles', 22) ?></div><span class="about-art-caption">Image origin · Evidence · Context</span>
      </div>
      <div class="about-text">
        <p class="section-kicker"><span></span> WHY THIS MATTERS</p>
        <h2 id="aboutTitle">A little more context goes <em>a long way.</em></h2>
        <p>As generated images become more convincing, people need better ways to understand where images come from. Our vision is to make technical origin signals readable for everyone.</p>
        <div class="about-callout"><?= icon('info', 20) ?><span><b>No single test is perfect.</b> Missing watermarks, metadata, or a model prediction should never be treated as proof that an image is genuine.</span></div>
      </div>
    </div>
  </section>

  <section class="section faq-section" id="faq" aria-labelledby="faqTitle">
    <div class="container faq-layout">
      <div>
        <p class="section-kicker"><span></span> GOOD TO KNOW</p>
        <h2 id="faqTitle">Frequently asked<br>questions<span class="lime-period">.</span></h2>
        <p class="faq-intro">A few important things to understand about this preview and image verification.</p>
      </div>
      <div class="faq-list">
        <?php foreach ($faq as [$question, $answer]): ?>
          <details class="faq-item">
            <summary><span><?= esc($question) ?></span><span class="faq-chevron"><?= icon('chevron-down', 20) ?></span></summary>
            <p><?= esc($answer) ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="bottom-cta">
    <div class="container">
      <div class="cta-inner">
        <div class="cta-orbit" aria-hidden="true"></div>
        <div>
          <p class="cta-overline">READY TO EXPLORE?</p>
          <h2>Every image has a story.<br><span>Look a little deeper.</span></h2>
          <p>Try the upload experience and take a look at the sample analysis layout.</p>
        </div><a href="#upload" class="btn btn-lime cta-button">Get started <?= icon('arrow-up-right', 18) ?></a>
      </div>
    </div>
  </section>

  <footer class="site-footer">
    <div class="container footer-content"><a class="brand footer-brand" href="#home"><span class="brand-icon"><svg width="23" height="23" viewBox="0 0 28 28" fill="none" aria-hidden="true">
            <circle cx="13" cy="12" r="7.2" stroke="currentColor" stroke-width="2.6" />
            <path d="m18.5 17.7 5.1 5.1" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" />
          </svg></span><span><b>AI</b> Image Detector</span></a>
      <p>Explore authenticity with more context.</p><span class="footer-credit">Built with Ubuntu · Apache · MySQL · PHP</span>
    </div>
    <div class="container footer-bottom"><small>&copy; <?= date('Y') ?> AI Image Detector. Academic UI project.</small><a href="#home">Back to top ↑</a></div>
  </footer>
  <script src="/assets/js/dashboard.js" defer></script>
</body>

</html>
