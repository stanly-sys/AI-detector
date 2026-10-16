
(() => {
  "use strict";

  const $ = (id) => document.getElementById(id);

  const HISTORY_KEY = "ai_detector_history_v1";
  let lastStatsRequest = 0;

  // Read locally stored history
  function getHistory() {
    try {
      const records = JSON.parse(
        localStorage.getItem(HISTORY_KEY) || "[]"
      );

      return Array.isArray(records) ? records : [];
    } catch {
      return [];
    }
  }

  // Retrieve aggregate statistics from MySQL
  async function loadStatistics() {
    const message = $("analyticsMessage");

    if (Date.now() - lastStatsRequest < 15000) return;
    lastStatsRequest = Date.now();

    message.textContent = "Loading statistics...";

    try {
      const response = await fetch("/api/stats.php", {
        cache: "no-store"
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.error || "Request failed");
      }

      const stats = data.statistics;

      $("totalAnalyses").textContent = stats.total;
      $("aiTotal").textContent = stats.ai_total;
      $("humanTotal").textContent = stats.human_total;

      $("averageAiScore").textContent =
        `${Number(stats.average_ai_score).toFixed(2)}%`;

      message.textContent = "";

    } catch (error) {
      console.error("Statistics error:", error);
      message.textContent = "Could not load statistics.";
    }
  }

  // Display recent results from this browser
  function renderHistory() {
    const list = $("analysisHistory");
    list.replaceChildren();

    const history = getHistory();

    if (history.length === 0) {
      const item = document.createElement("li");
      item.textContent = "No analyses recorded in this browser yet.";
      list.appendChild(item);
      return;
    }

    history.forEach((record) => {
      const item = document.createElement("li");

      item.textContent =
        `${new Date(record.date).toLocaleString()} — ` +
        `${record.prediction} — ` +
        `AI: ${Number(record.aiScore).toFixed(2)}%, ` +
        `Human: ${Number(record.humanScore).toFixed(2)}%`;

      list.appendChild(item);
    });
  }

  // Store an analysis after it finishes successfully
  function saveAnalysis(predictions) {
    if (!Array.isArray(predictions)) return;

    const ai = predictions.find(
      (item) => item.label?.toLowerCase() === "artificial"
    );

    const human = predictions.find(
      (item) => item.label?.toLowerCase() === "human"
    );

    if (!ai || !human) return;

    const aiScore = Number(ai.score);
    const humanScore = Number(human.score);

    if (
      !Number.isFinite(aiScore) ||
      !Number.isFinite(humanScore) ||
      aiScore < 0 || aiScore > 1 ||
      humanScore < 0 || humanScore > 1
    ) {
      return;
    }

    const history = getHistory();

    history.unshift({
      date: new Date().toISOString(),
      prediction: aiScore >= humanScore
        ? "Likely AI-generated"
        : "Likely human-created",
      aiScore: aiScore * 100,
      humanScore: humanScore * 100
    });

    try {
      // Keep only the latest 10 records
      localStorage.setItem(
        HISTORY_KEY,
        JSON.stringify(history.slice(0, 10))
      );
    } catch (error) {
      console.warn("Could not save browser history:", error);
    }

    renderHistory();
    loadStatistics();
  }

  // Receive a successful analysis from app.js
  window.addEventListener("ai:analysis-completed", (event) => {
    saveAnalysis(event.detail.predictions);
  });

  $("refreshAnalytics").addEventListener(
    "click",
    loadStatistics
  );

  $("clearAnalysisHistory").addEventListener("click", () => {
    localStorage.removeItem(HISTORY_KEY);
    renderHistory();
  });

  loadStatistics();
  renderHistory();
})();
