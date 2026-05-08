window.GGR = window.GGR || {};

GGR.UI = (function ($) {
  "use strict";

  /**
   * Reset UI before audit
   */
  function reset() {
    $(".ggr-analyzer-header").hide();
    $(".ggr-priority-card").hide();

    $(".ggr-score-number").text("--");
    $(".ggr-analyzer-status").text("");
    $(".ggr-analyzer-summary").text("");

    $(".ggr-priority-list").empty();
    $(".ggr-insights").empty(); // 🔥 added
  }

  /**
   * Update Score Number + animation
   */
  function updateScore(score) {
    const $liquid = $(".ggr-score-liquid");
    const $fill = $(".ggr-score-liquid-fill");

    // reset animation
    $liquid.css("height", "0%");
    $liquid[0]?.offsetHeight;

    setTimeout(() => {
      $liquid.css("height", score + "%");
    }, 200);

    $(".ggr-score-number").text(score);

    // optional css variable
    $(".ggr-score-pill").css("--ggr-score", score);
  }

  /**
   * Update Status + Color
   */
  function updateStatus(score) {
    const $header = $(".ggr-analyzer-header");
    const $fill = $(".ggr-score-liquid-fill");

    $header.removeClass("ggr-good ggr-medium ggr-poor");

    if (score <= 30) {
      $header.addClass("ggr-poor");
      $(".ggr-analyzer-status").text("Critical Issues Detected");
      $fill.css("background", "#ef4444");
    } else if (score <= 70) {
      $header.addClass("ggr-medium");
      $(".ggr-analyzer-status").text("Needs Attention");
      $fill.css("background", "#f59e0b");
    } else {
      $header.addClass("ggr-good");
      $(".ggr-analyzer-status").text("Website is Healthy");
      $fill.css("background", "#22c55e");
    }

    $header.show();
  }

  /**
   * Update Summary Text
   */
  function updateSummary(text) {
    $(".ggr-analyzer-summary").text(text || "");
  }

  /**
   * Render Sections (NEW ENGINE COMPATIBLE)
   */
  function renderSections(sections) {
    const $list = $(".ggr-priority-list");
    $list.empty();

    if (!sections || typeof sections !== "object") return;

    let working = [];
    let improve = [];
    let critical = [];

    Object.values(sections).forEach((section) => {
      working = working.concat(section.working_well || []);
      improve = improve.concat(section.needs_improvement || []);
      critical = critical.concat(section.critical_issues || []);
    });

    function render(title, items, type) {
      if (!items.length) return;

      $list.append(`
        <li class="ggr-audit-card ggr-${type}">
          <div class="ggr-audit-card-header">${title}</div>
          <ul class="ggr-audit-card-list">
            ${items.map((i) => `<li>${i}</li>`).join("")}
          </ul>
        </li>
      `);
    }

    render("Working Well", working, "success");
    render("Needs Improvement", improve, "warning");
    render("Critical Issues", critical, "critical");

    $(".ggr-priority-card").show();
  }

  /**
   * Render Insights
   */
  function renderInsights(insights) {
    if (!insights || !insights.length) return;

    $(".ggr-insights").html(`
      <div class="ggr-insight-box">
        <h4>💡 Key Insights</h4>
        <ul>
          ${insights.map((i) => `<li>${i}</li>`).join("")}
        </ul>
      </div>
    `);
  }

  /**
   * Scroll to result section
   */
  function scrollToResults() {
    const $target = $(".ggr-card.ggr-analyzer-header");

    if (!$target.length) return;

    $("html, body")
      .stop()
      .animate(
        {
          scrollTop: $target.offset().top - 40,
        },
        600
      );
  }

  /**
   * Show notice
   */
  function showNotice(message, type = "info") {
    $(".ggr-audit-notice").remove();

    const html = `
      <div class="notice notice-${type} inline ggr-audit-notice">
        <p>${message || "Something went wrong."}</p>
      </div>
    `;

    $(".ggr-analyzer-header").before(html);
  }

  /**
   * Public API
   */
  return {
    reset,
    updateScore,
    updateStatus,
    updateSummary,
    renderSections,
    renderInsights,
    scrollToResults,
    showNotice,
  };

})(jQuery);