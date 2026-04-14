/**
 * Admin JavaScript for GGR Website Audit (FINAL FIXED)
 */
/* global jQuery, ggrwa_admin */

(function ($) {
  "use strict";

  /* -----------------------------------------
   * Utilities
   * ----------------------------------------- */

  function ggrScrollToResults() {
    const $target = $(".ggr-card.ggr-analyzer-header");
    if (!$target.length) return;

    $("html, body")
      .stop()
      .animate({ scrollTop: $target.offset().top - 40 }, 600);
  }

  function ggrShowNotice(message, type) {
    if (!message || message === "undefined") {
      message = "Something went wrong.";
    }

    $(".ggr-audit-notice").remove();

    let noticeClass = "notice-info";
    if (type === "success") noticeClass = "notice-success";
    if (type === "warning") noticeClass = "notice-warning";
    if (type === "error") noticeClass = "notice-error";

    const notice =
      '<div class="notice ' +
      noticeClass +
      ' inline ggr-audit-notice"><p>' +
      message +
      "</p></div>";

    $(".ggr-analyzer-header").before(notice);
  }

  /* -----------------------------------------
   * Hide Timezone Notice
   * ----------------------------------------- */

  $(document).on("click", ".ggr-notice-close", function () {
    $("#ggr-timezone-notice").fadeOut(200);

    $.post(ajaxurl, {
      action: "ggrwa_hide_timezone_notice",
    });
  });

  /* -----------------------------------------
   * Run Audit (MAIN BUTTON)
   * ----------------------------------------- */

  $(document).on("click", ".ggr-run-audit-btn", function (e) {
    e.preventDefault();

    const $btn = $(this);
    const $loader = $(".ggr-audit-loader");

    if ($btn.hasClass("is-loading")) return;

    if (
      typeof ggrwa_admin !== "undefined" &&
      typeof ggrwa_admin.auditEnabled !== "undefined" &&
      !ggrwa_admin.auditEnabled
    ) {
      const msg =
        ggrwa_admin.enableMsg || "Please enable Website Audit from Settings.";
      ggrShowNotice(msg, "warning");
      return;
    }

    $(".ggr-audit-notice").remove();
    $(".ggr-analyzer-header").hide();
    $(".ggr-priority-card").hide();
    $(".ggr-score-number").text("--");
    $(".ggr-analyzer-status").text("");
    $(".ggr-analyzer-summary").text("");

    $btn.addClass("is-loading").prop("disabled", true);
    if ($loader.length) $loader.show();

    $.ajax({
      url: ggrwa_admin.ajaxUrl,
      type: "POST",
      dataType: "json",
      data: {
        action: "ggrwa_run_audit",
        nonce: ggrwa_admin.auditNonce,
        url: $("#ggr-audit-url").val().trim(),
        post_id: ggrwa_admin.postId || 0,
      },
    })
      .done(function (response) {
        if (!response || !response.success) {
          const msg =
            response?.data?.message || "Audit failed. Please try again.";
          ggrShowNotice(msg, "warning");
          return;
        }

        ggrShowNotice(
          "Audit completed successfully using real on-page signals.",
          "success",
        );

        const data = response.data;
        const $header = $(".ggr-analyzer-header");

        let hasCriticalIssues = false;
        let firstCriticalMessage = "";

        if (data.audit?.critical_issues?.length) {
          hasCriticalIssues = true;
          firstCriticalMessage = data.audit.critical_issues[0];
        }

        let score = null;
        let confidence = null;

        if (typeof data.score === "object") {
          score = data.score.total ?? null;
          confidence = data.score.confidence ?? null;
        } else if (typeof data.score === "number") {
          score = data.score;
        }

        $header.removeClass("ggr-good ggr-medium ggr-poor");

        if (score !== null) {
          const $liquid = $(".ggr-score-liquid");
          const $fill = $(".ggr-score-liquid-fill");

          $liquid.css("height", "0%");
          $liquid[0].offsetHeight;

          setTimeout(() => {
            $liquid.css("height", score + "%");
          }, 200);

          $(".ggr-score-number").text(score);

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
          $(".ggr-score-pill").css("--ggr-score", score);
        }

        $(".ggr-analyzer-summary").text(
          hasCriticalIssues ? firstCriticalMessage : data.summary || "",
        );

        $header.show();

        const $card = $(".ggr-priority-card");
        const $list = $(".ggr-priority-list");
        $list.empty();

        function render(title, items, type) {
          if (!items?.length) return;

          $list.append(
            "<li class='ggr-audit-card ggr-" +
              type +
              "'><div class='ggr-audit-card-header'>" +
              title +
              "</div><ul class='ggr-audit-card-list'>" +
              items.map((i) => "<li>" + i + "</li>").join("") +
              "</ul></li>",
          );
        }

        const sections = data.audit?.sections || {};

        let w = [],
          n = [],
          c = [];

        Object.values(sections).forEach((s) => {
          w = w.concat(s.working_well || []);
          n = n.concat(s.needs_improvement || []);
          c = c.concat(s.critical_issues || []);
        });

        render("Working Well", w, "success");
        render("Needs Improvement", n, "warning");
        render("Critical Issues", c, "critical");

        $card.show();
        ggrScrollToResults();

        const actions = response.data.page_actions;
        $(".ggr-actions").remove();

        if (actions && actions.post_id) {
          const actionsHtml = `
            <div class="ggr-actions">
              <a href="${actions.edit_url}" target="_blank" class="ggr-btn ggr-btn-edit">
                ✏️ Fix SEO
              </a>
              <a href="${actions.view_url}" target="_blank" class="ggr-btn ggr-btn-view">
                👁️ Preview
              </a>
            </div>
          `;

          $(".ggr-priority-list").after(actionsHtml);
        }
      })
      .fail(function () {
        ggrShowNotice("Audit request failed.", "error");
      })
      .always(function () {
        $btn.removeClass("is-loading").prop("disabled", false);
        if ($loader.length) $loader.hide();
      });
  });

  /* -----------------------------------------
   * Admin Bar Scan → Redirect
   * ----------------------------------------- */
  $(document).on(
    "click",
    "#wp-admin-bar-ggrwa-scan-page a,#wp-admin-bar-ggrwa-rescan-page a",
    function (e) {
      e.preventDefault();

      let currentUrl = "";

      if (window.location.href.includes("post.php")) {
        const permalink = $("#sample-permalink a").attr("href");
        if (permalink) {
          currentUrl = permalink;
        }
      }


      if (!currentUrl) {
        const viewLink = $("#wp-admin-bar-view a").attr("href");
        if (viewLink && !viewLink.includes("post.php")) {
          currentUrl = viewLink;
        }
      }

      if (!currentUrl) {
        currentUrl = window.location.href;
      }   

      const redirectUrl =
        ggrwa_admin.dashboardUrl +
        "&scan_url=" +
        encodeURIComponent(currentUrl) +
        "&ggrwa_nonce=" +
        ggrwa_admin.scanNonce;
      window.location.href = redirectUrl;
    },
  );

  /* -----------------------------------------
   * Auto Scan Trigger (Dashboard)
   * ----------------------------------------- */

  $(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const scanUrl = urlParams.get("scan_url");

    if (scanUrl) {
      setTimeout(function () {
        const btn = document.querySelector(".ggr-run-audit-btn");
        if (btn) btn.click();
      }, 800);
    }
  });
})(jQuery);
