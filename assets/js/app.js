/**
 * Uploads selected by the user are sent to the local analysis API only on request.
 * The sample report remains explicitly illustrative.
 */
(() => {
  "use strict";
  const $ = (id) => document.getElementById(id);
  const input = $("imageInput");
  const dropzone = $("dropzone");
  const previewWrap = $("selectedPreview");
  const selectedImage = $("selectedImage");
  const chooseButton = $("chooseButton");
  const analyzeButton = $("analyzeButton");
  const modelSelection = $("modelSelection");
  const selectedModel = () => modelSelection.querySelector('input[name="models"]:checked')?.value || "both";
  const disableModelSelection = (disabled) => {
    modelSelection.querySelectorAll('input[name="models"]').forEach((input) => { input.disabled = disabled; });
  };
  const removeButton = $("removeButton");
  const uploadMessage = $("uploadMessage");
  const reportImage = $("reportImage");
  const sampleImageSrc = reportImage.getAttribute("src");
  const menuButton = $("menuButton");
  const navLinks = $("navLinks");
  let objectUrl = null;
  let selectedFile = null;
  let dragDepth = 0;

  // Restored from the original PHP design: scroll reveal and painted lime highlights.
  // Elements stay visible if JavaScript or IntersectionObserver is unavailable.
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  if (!reducedMotion.matches && "IntersectionObserver" in window) {
    document.documentElement.classList.add("motion-enabled");
    const revealNodes = document.querySelectorAll(
      [
        ".section-intro",
        ".method-grid",
        ".methods-note",
        ".process-heading",
        ".steps-list",
        ".about-art",
        ".about-text",
        ".faq-layout > div:first-child",
        ".faq-list",
        ".cta-inner",
      ].join(", "),
    );
    const revealObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-visible");
          revealObserver.unobserve(entry.target);
        });
      },
      { threshold: 0.06, rootMargin: "0px 0px -7% 0px" },
    );
    revealNodes.forEach((node) => {
      node.classList.add("will-reveal");
      revealObserver.observe(node);
    });
  }

  // Touch feedback for cards/icons, since mobile devices don't have a real hover.
  const touchCards = document.querySelectorAll(
    [
      ".upload-card",
      ".report-card",
      ".method-card",
      ".process-step",
      ".benefit-list li",
      ".metric-row",
      ".about-art",
    ].join(", "),
  );
  touchCards.forEach((card) => {
    let touchEndTimer;
    card.addEventListener("pointerdown", (event) => {
      if (event.pointerType !== "touch" && event.pointerType !== "pen") return;
      if (reducedMotion.matches) return;
      card.classList.remove("is-touched");
      // Restart the animation on every tap, including repeated taps.
      void card.offsetWidth;
      card.classList.add("is-touched");
      clearTimeout(touchEndTimer);
      touchEndTimer = setTimeout(
        () => card.classList.remove("is-touched"),
        1850,
      );
    });
  });

  // Original invalid-upload nudge (used only for real validation errors).
  let uploadErrorTimer;
  function nudgeUploadCard() {
    if (reducedMotion.matches) return;
    const uploadCard = document.querySelector(".upload-card");
    uploadCard.classList.remove("is-invalid");
    void uploadCard.offsetWidth;
    uploadCard.classList.add("is-invalid");
    clearTimeout(uploadErrorTimer);
    uploadErrorTimer = setTimeout(
      () => uploadCard.classList.remove("is-invalid"),
      470,
    );
  }

  // Mobile menu: keyboard and pointer accessible.
  const closeMenu = () => {
    navLinks.classList.remove("is-open");
    menuButton.setAttribute("aria-expanded", "false");
    menuButton.setAttribute("aria-label", "Open menu");
  };
  menuButton.addEventListener("click", () => {
    const opening = !navLinks.classList.contains("is-open");
    navLinks.classList.toggle("is-open", opening);
    menuButton.setAttribute("aria-expanded", String(opening));
    menuButton.setAttribute("aria-label", opening ? "Close menu" : "Open menu");
  });
  navLinks
    .querySelectorAll("a")
    .forEach((link) => link.addEventListener("click", closeMenu));
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeMenu();
  });
  document.addEventListener("click", (event) => {
    if (
      !event.target.closest(".nav-shell") &&
      navLinks.classList.contains("is-open")
    )
      closeMenu();
  });

  // Keep current section recognizable in the desktop menu.
  const navAnchors = [...document.querySelectorAll(".nav-link")];
  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          navAnchors.forEach((link) => {
            const active = link.getAttribute("href") === "#" + entry.target.id;
            link.classList.toggle("is-active", active);
            if (active) link.setAttribute("aria-current", "location");
            else link.removeAttribute("aria-current");
          });
        });
      },
      { rootMargin: "-20% 0px -60% 0px" },
    );
    ["home", "features", "how-it-works", "about"].forEach((id) => {
      const section = $(id);
      if (section) observer.observe(section);
    });
  }

  function message(text, kind = "") {
    uploadMessage.className = "upload-message" + (kind ? " is-" + kind : "");
    if (kind === "error") nudgeUploadCard();
    uploadMessage.replaceChildren();
    const icon = document.createElement("span");
    icon.className = "message-icon";
    icon.textContent = kind === "error" ? "!" : kind === "ready" ? "✓" : "ⓘ";
    const span = document.createElement("span");
    span.textContent = text;
    uploadMessage.append(icon, span);
  }

  function setReportState(mode) {
    const confidenceBars = $("confidenceBars");

    if (confidenceBars) {
      confidenceBars.hidden = true;
    }
    const state = $("reportState");
    const title = $("reportStateTitle");
    const detail = $("reportStateDetail");
    const watermark = $("watermarkValue");
    const provenance = $("provenanceValue");
    const ml = $("mlValue");
    const imageLabel = $("imageCornerLabel");
    const disclaimer = $("reportDisclaimer");
    const reportTitle = $("reportTitle");
    state.classList.toggle("waiting", mode === "selected");
    state.classList.toggle("sample", mode !== "selected");
    if (mode === "selected") {
      reportImage.src = objectUrl;
      reportImage.alt = "Preview of your selected image";
      title.textContent = "Your image is ready";
      detail.textContent = "Ready for AI analysis";
      reportTitle.textContent = "Your image preview";
      imageLabel.textContent = "YOUR IMAGE";
      watermark.textContent = "Not run";
      provenance.textContent = "Not run";
      ml.textContent = "Not run";
      ml.classList.remove("metric-pill");
      disclaimer.textContent =
        "Only your local browser preview is shown. No scan or server upload has happened.";
      $("demoButton").textContent = "View sample report ↗";
    } else {
      reportImage.src = sampleImageSrc;
      reportImage.alt = "Example dog photograph in the illustrative report";
      title.textContent = "Explore a sample report";
      detail.textContent = "Illustrative results — not a real scan";
      reportTitle.textContent = "Analysis preview";
      imageLabel.textContent = "EXAMPLE IMAGE";
      watermark.textContent = "Not detected*";
      provenance.textContent = "No credentials*";
      ml.textContent = "AI-likely*";
      ml.classList.add("metric-pill");
      disclaimer.textContent =
        "* Sample values for design illustration only. No verification has been performed.";
      $("demoButton").textContent = "Reset sample report ↻";
    }
  }

  function updateConfidenceBars(aiScore, humanScore) {
    let container = $("confidenceBars");

    // Create the bars once
    if (!container) {
      container = document.createElement("div");
      container.id = "confidenceBars";
      container.className = "confidence-bars";

      container.innerHTML = `
      <div class="confidence-row">
        <div class="confidence-label">
          <span>AI-generated</span>
          <strong id="aiConfidenceText">0%</strong>
        </div>
        <div class="confidence-track">
          <div id="aiConfidenceFill"
               class="confidence-fill confidence-ai"></div>
        </div>
      </div>

      <div class="confidence-row">
        <div class="confidence-label">
          <span>Human-created</span>
          <strong id="humanConfidenceText">0%</strong>
        </div>
        <div class="confidence-track">
          <div id="humanConfidenceFill"
               class="confidence-fill confidence-human"></div>
        </div>
      </div>
    `;

      // Place bars above the report disclaimer
      $("reportDisclaimer").insertAdjacentElement("beforebegin", container);
    }

    container.hidden = false;

    const scores = [
      { name: "ai", score: aiScore },
      { name: "human", score: humanScore },
    ];

    scores.forEach(({ name, score }) => {
      const percent = Math.max(0, Math.min(100, score * 100));

      const fill = $(`${name}ConfidenceFill`);

      $(`${name}ConfidenceText`).textContent = `${percent.toFixed(2)}%`;

      // Restart animation on each analysis
      fill.style.width = "0%";
      void fill.offsetWidth;

      requestAnimationFrame(() => {
        if (!container.hidden) {
          fill.style.width = `${percent}%`;
        }
      });
    });
  }

  function displayC2paResult(c2pa, synthid) {
    const provenance = $("provenanceValue");
    const watermark = $("watermarkValue");
    const disclaimer = $("reportDisclaimer");

    // 1. C2PA signature verification status
    const verificationLabels = {
      trusted: "Verified - trusted signer",
      valid_untrusted: "Valid - untrusted signer",
      invalid: "Invalid credentials",
      not_found: "No credentials found",
      present_unverified: "Credentials not verified",
      unavailable: "Verification unavailable",
    };

    const status = c2pa?.status || "unavailable";

    provenance.textContent =
      verificationLabels[status] || "Unknown verification status";

    // 2. Extract declared image origin
    const originLabels = {
      ai_generated_declared: "AI-generated image declared",
      ai_assisted_declared: "Generative AI involvement declared",
      digital_capture_declared: "Digital capture declared",
      unknown: "Image creation method not declared",
    };

    const originType = c2pa?.origin?.type || "unknown";
    const originDescription = originLabels[originType] || originLabels.unknown;

    // 3. Explain how much trust to place in the declaration
    let trustMessage;

    if (status === "trusted") {
      trustMessage =
        "The credentials passed verification against the configured trust anchors.";
    } else if (status === "valid_untrusted") {
      trustMessage =
        "The credentials passed integrity validation, but the signer is not trusted.";
    } else if (status === "invalid") {
      trustMessage =
        "The credentials failed verification. Origin declarations cannot be relied upon.";
    } else {
      trustMessage = "No trustworthy provenance conclusion is available.";
    }

    // 4. SynthID remains separate
    watermark.textContent =
      synthid?.status === "not_configured"
        ? "SynthID not configured"
        : "SynthID not checked";

    // 5. Display the actual C2PA origin information
    disclaimer.textContent =
      `C2PA origin: ${originDescription}. ` +
      `${trustMessage} ` +
      "AI model scores are estimates, not proof of origin.";
  }

  function displayAnalysisResults(predictions) {
    // Extract model predictions
    const aiResult = predictions.find(
      (item) => item.label.toLowerCase() === "artificial",
    );

    const humanResult = predictions.find(
      (item) => item.label.toLowerCase() === "human",
    );

    if (
      !aiResult ||
      !humanResult ||
      !Number.isFinite(aiResult.score) ||
      !Number.isFinite(humanResult.score)
    ) {
      throw new Error("Invalid prediction results received.");
    }

    // Convert model scores into percentages
    const aiScore = (aiResult.score * 100).toFixed(2);
    const humanScore = (humanResult.score * 100).toFixed(2);
    updateConfidenceBars(aiResult.score, humanResult.score);
    const isAI = aiResult.score >= humanResult.score;

    const prediction = isAI ? "Likely AI-generated" : "Likely human-created";

    const topScore = isAI ? aiScore : humanScore;

    // Update the existing report card
    const reportState = $("reportState");
    reportState.classList.remove("waiting", "sample");

    $("reportStateTitle").textContent = prediction;

    $("reportStateDetail").textContent =
      `AI: ${aiScore}% | Human: ${humanScore}%`;

    $("reportTitle").textContent = "Your analysis results";
    $("imageCornerLabel").textContent = "ANALYZED IMAGE";

    // Only the ML detector was executed
    $("watermarkValue").textContent = "Not checked";
    $("provenanceValue").textContent = "Not checked";

    const mlValue = $("mlValue");
    mlValue.textContent = `${prediction} (${topScore}%)`;
    mlValue.classList.add("metric-pill");

    $("reportDisclaimer").textContent =
      "These are AI model estimates, not proof of image origin. Watermark and provenance checks were not performed.";

    console.log("Analysis completed:", {
      prediction,
      aiScore,
      humanScore,
    });
  }

  const modelIds = ["sdxl_detector", "community_forensics"];

  function resetModelCard(modelId, state = "waiting", messageText = "No analysis run yet") {
    const card = $(`card-${modelId}`);
    card.dataset.state = state;
    $(`${modelId}-status`).textContent = state === "loading" ? "Loading" : "Waiting";
    $(`${modelId}-prediction`).textContent = messageText;
    $(`${modelId}-ai-score`).textContent = "—";
    $(`${modelId}-human-score`).textContent = "—";
    $(`${modelId}-time`).textContent = "Processing time: —";
    $(`${modelId}-version`).textContent = "";
    card.querySelectorAll(".model-score-track span").forEach((bar) => { bar.style.width = "0%"; });
    card.querySelectorAll(".model-score-track").forEach((track) => track.setAttribute("aria-valuenow", "0"));
  }

  function beginModelComparison(selection) {
    for (const modelId of modelIds) {
      const selected = selection === "both" || selection === modelId;
      resetModelCard(modelId, selected ? "loading" : "not_selected", selected ? "Running model on this image…" : "Not run for this analysis");
      $(`${modelId}-status`).textContent = selected ? "Loading" : "Not selected";
    }
    $("comparisonOutcome").classList.remove("is-disagree");
    $("comparisonOutcome").textContent = selection === "both" ? "Both models are processing this image sequentially." : "The selected model is processing this image.";
  }

  function renderModelResult(modelId, result) {
    const card = $(`card-${modelId}`);
    if (!result || result.status === "not_selected") {
      resetModelCard(modelId, "not_selected", "Not run for this analysis");
      $(`${modelId}-status`).textContent = "Not selected";
      return;
    }
    if (result.status !== "success") {
      const state = result.status === "unavailable" ? "unavailable" : "error";
      resetModelCard(modelId, state, result.error || (state === "unavailable" ? "Weights or dependencies unavailable" : "Model inference failed"));
      $(`${modelId}-status`).textContent = state === "unavailable" ? "Unavailable" : "Error";
      return;
    }
    const ai = Number(result.ai_score);
    const human = Number(result.human_score);
    if (!Number.isFinite(ai) || !Number.isFinite(human) || ai < 0 || ai > 1 || human < 0 || human > 1 || !["artificial", "human"].includes(result.prediction)) {
      resetModelCard(modelId, "error", "Model returned invalid scores");
      $(`${modelId}-status`).textContent = "Error";
      return;
    }
    card.dataset.state = "success";
    $(`${modelId}-status`).textContent = "Completed";
    $(`${modelId}-prediction`).textContent = result.prediction === "artificial" ? "Likely AI-generated" : "Likely human-created";
    $(`${modelId}-ai-score`).textContent = `${(ai * 100).toFixed(2)}%`;
    $(`${modelId}-human-score`).textContent = `${(human * 100).toFixed(2)}%`;
    const bars = card.querySelectorAll(".model-score-track span");
    const tracks = card.querySelectorAll(".model-score-track");
    [ai, human].forEach((score, i) => {
      const percent = Math.max(0, Math.min(100, score * 100));
      bars[i].style.width = `${percent}%`;
      tracks[i].setAttribute("aria-valuenow", percent.toFixed(0));
    });
    const ms = Number(result.inference_time_ms);
    $(`${modelId}-time`).textContent = Number.isFinite(ms) ? `Processing time: ${(ms / 1000).toFixed(2)} s` : "Processing time: unavailable";
    $(`${modelId}-version`).textContent = result.model_version || "";
  }

  function renderModelComparison(results) {
    modelIds.forEach((modelId) => renderModelResult(modelId, results?.[modelId]));
    const sdxl = results?.sdxl_detector;
    const commfor = results?.community_forensics;
    const outcome = $("comparisonOutcome");
    outcome.classList.remove("is-disagree");
    if (sdxl?.status === "success" && commfor?.status === "success") {
      if (sdxl.prediction === commfor.prediction) {
        outcome.textContent = "Both models agree on the classification. Agreement does not establish that the classification is correct.";
      } else {
        outcome.textContent = "The models disagree on this image. This result requires further evaluation.";
        outcome.classList.add("is-disagree");
      }
    } else if (sdxl?.status === "success" || commfor?.status === "success") {
      const other = sdxl?.status === "success" ? commfor : sdxl;
      outcome.textContent = other?.status === "not_selected" ? "The selected model completed this analysis." : "One model completed; the other did not. Review each status above.";
    } else {
      outcome.textContent = "No selected model completed successfully. Check model availability and server logs.";
    }
  }

  function clearImage() {
    selectedFile = null;
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null;
    input.value = "";
    selectedImage.removeAttribute("src");
    previewWrap.hidden = true;
    dropzone.classList.remove("has-file", "is-dragging");
    $("uploadTitle").textContent = "Drop your image here";
    $("uploadSupport").textContent = "Drag & drop or browse your files";
    removeButton.hidden = true;
    message("Choose an image to start analysis.");
    setReportState("sample");
    modelIds.forEach((modelId) => resetModelCard(modelId));
    $("comparisonOutcome").classList.remove("is-disagree");
    $("comparisonOutcome").textContent = "Choose an image and run one or both models to compare their results.";
  }

  function selectImage(file) {
    if (!file) return;
    const allowedTypes = ["image/jpeg", "image/png", "image/webp"];
    if (!allowedTypes.includes(file.type)) {
      message("Choose a JPG, PNG or WebP image.", "error");
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      message(
        "That file is too large. Please choose an image under 2 MB.",
        "error",
      );
      return;
    }
    if (file.size === 0) {
      message("The selected file is empty.", "error");
      return;
    }
    const nextUrl = URL.createObjectURL(file);
    modelIds.forEach((modelId) => resetModelCard(modelId));
    $("comparisonOutcome").classList.remove("is-disagree");
    $("comparisonOutcome").textContent = "Run selected models to compare their results for this image.";
    const verifier = new Image();
    verifier.onload = () => {
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = nextUrl;
      selectedFile = file;
      selectedImage.src = nextUrl;
      previewWrap.hidden = false;
      dropzone.classList.add("has-file");
      $("uploadTitle").textContent = file.name;
      $("uploadSupport").textContent =
        `${(file.size / 1024 / 1024).toFixed(2)} MB • ${verifier.naturalWidth} × ${verifier.naturalHeight}px`;
      removeButton.hidden = false;
      message("Image selected. Your preview is ready.", "ready");
      setReportState("selected");
    };
    verifier.onerror = () => {
      URL.revokeObjectURL(nextUrl);
      message("This file could not be opened as an image.", "error");
    };
    verifier.src = nextUrl;
  }

  chooseButton.addEventListener("click", () => input.click());
  dropzone.addEventListener("click", () => input.click());
  dropzone.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      input.click();
    }
  });
  input.addEventListener("change", () => selectImage(input.files?.[0]));
  dropzone.addEventListener("dragenter", (event) => {
    event.preventDefault();
    dragDepth++;
    dropzone.classList.add("is-dragging");
  });
  dropzone.addEventListener("dragover", (event) => {
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = "copy";
  });
  dropzone.addEventListener("dragleave", (event) => {
    event.preventDefault();
    dragDepth = Math.max(0, dragDepth - 1);
    if (!dragDepth) dropzone.classList.remove("is-dragging");
  });
  dropzone.addEventListener("drop", (event) => {
    event.preventDefault();
    dragDepth = 0;
    dropzone.classList.remove("is-dragging");
    selectImage(event.dataTransfer?.files?.[0]);
  });
  removeButton.addEventListener("click", clearImage);
  analyzeButton.addEventListener("click", async () => {
    if (!selectedFile) {
      message("Please choose an image first.", "error");
      dropzone.focus();
      return;
    }

    // Prepare image for PHP upload
    const formData = new FormData();
    formData.append("image", selectedFile);
    const selection = selectedModel();
    formData.append("models", selection);
    beginModelComparison(selection);
    disableModelSelection(true);

    analyzeButton.disabled = true;
    analyzeButton.classList.add("is-analyzing");
    analyzeButton.setAttribute("aria-busy", "true");

    message("The system is analyzing your image. Please wait...");
    try {
      // Send image to our PHP backend
      const response = await fetch("/api/analyze.php", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();
      renderModelComparison(data.models);
      if (data.c2pa) displayC2paResult(data.c2pa, data.synthid);
      if (Array.isArray(data.predictions) && data.predictions.length) {
        displayAnalysisResults(data.predictions);
        window.dispatchEvent(new CustomEvent("ai:analysis-completed", { detail: { predictions: data.predictions } }));
      }
      if (!response.ok || !data.success) {
        if (data.models) {
          message(data.error || "No selected model completed successfully.", "error");
          return;
        }
        throw new Error(data.error || "Analysis failed.");
      }
      const modelSuccesses = modelIds.filter((modelId) => data.models?.[modelId]?.status === "success").length;
      message(selection === "both" && modelSuccesses === 1 ? "One model completed; the other did not return a result." : selection === "both" ? "Both model comparisons completed." : "Selected model analysis complete.", "ready");
    } catch (error) {
      console.error("Detection error:", error);
      for (const modelId of modelIds) {
        const selected = selection === "both" || selection === modelId;
        resetModelCard(modelId, selected ? "error" : "not_selected", selected ? "Could not reach the inference service" : "Not run for this analysis");
        $(`${modelId}-status`).textContent = selected ? "Error" : "Not selected";
      }
      $("comparisonOutcome").textContent = "The analysis request did not return model results.";
      message(error.message, "error");
    } finally {
      analyzeButton.disabled = false;
      analyzeButton.classList.remove("is-analyzing");
      analyzeButton.removeAttribute("aria-busy");
      disableModelSelection(false);
    }
  });

  $("demoButton").addEventListener("click", () => {
    // Switch to explicitly labeled sample data; never pretend to analyze the selected file.
    if (selectedFile) clearImage();
    else setReportState("sample");
  });

  window.addEventListener("beforeunload", () => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
  });
})();
