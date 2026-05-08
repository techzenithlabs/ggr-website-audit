/**
 * Frontend JS for GGR Website Audit
 *
 * - Shows loader on audit submit
 * - Handles tooltips for audit result sections
 */

(function ($) {
  "use strict";
  
  
  /* -----------------------------------------
   * Tooltips (Frontend Audit Results)
   * ----------------------------------------- */

  const ggrTooltips = {
    working_well:
      "These SEO checks are already optimized and follow best practices. No action is needed here.",
    needs_improvement:
      "Fixing these items can help improve rankings, visibility, and your overall SEO score.",
    critical_issues:
      "These are serious SEO problems that can block your page from appearing in search results.",
  };

  let ggrActiveTooltip = null;

  $(document).on("mouseenter", ".ggr-tooltip-trigger", function () {
    const key = $(this).data("tooltip");
    const text = ggrTooltips[key];

    if (!text) {
      return;
    }

    // Remove existing tooltip
    if (ggrActiveTooltip) {
      ggrActiveTooltip.remove();
      ggrActiveTooltip = null;
    }

    const $tooltip = $("<div>", {
      class: "ggr-tooltip-box is-visible",
      text: text,
    });

    $("body").append($tooltip);

    const rect = this.getBoundingClientRect();
    $tooltip.css({
    left: rect.left + "px",
    top: rect.bottom + 8 + "px",
    });

    ggrActiveTooltip = $tooltip;
  });

  $(document).on("mouseleave", ".ggr-tooltip-trigger", function () {
    if (ggrActiveTooltip) {
      ggrActiveTooltip.remove();
      ggrActiveTooltip = null;
    }
  });

})(jQuery);
