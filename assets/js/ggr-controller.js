window.GGR = window.GGR || {};

GGR.Controller = (function ($, GGR) {

  "use strict";

  function beforeAudit($btn) {
    const $loader = $(".ggr-audit-loader");

    GGR.UI?.reset?.();

    $btn.addClass("is-loading").prop("disabled", true);
    $loader.show();
  }

  function afterAudit($btn) {
    const $loader = $(".ggr-audit-loader");

    $btn.removeClass("is-loading").prop("disabled", false);
    $loader.hide();
  }

  async function processResponse(response) {
    const data = response?.data || {};

    //  fresh base score
    let baseScore = Number(data.score?.total || data.score || 50);

    let finalScore = baseScore;
    let result = null;

    try {
     
      const results = await GGR.Analyzer.run({
        postId: ggrwa_admin.postId,
        audit: data.audit || {}
      });

      console.log("Analyzer Results:", results);

      if (results && GGR.Engine?.calculate) {
        result = GGR.Engine.calculate(baseScore, results, data);

      
        finalScore = Number(result?.score || baseScore);
      }

    } catch (e) {
      console.warn("GGR Analyzer failed:", e);
    }

    //  Performance mix (optional)
    if (GGR.Performance?.getScore) {
      try {
        const perfScore = await GGR.Performance.getScore();
        finalScore = Math.round(finalScore * 0.8 + perfScore * 0.2);
      } catch (e) {}
    }

    // Clamp score 
    finalScore = Math.max(0, Math.min(100, finalScore));

    console.log("Final Score:", finalScore);

    // UI update (clean)
    GGR.UI?.updateScore(finalScore);
    GGR.UI?.updateStatus(finalScore);
    GGR.UI?.renderSections(data.audit?.sections || {});
    GGR.Utils?.scrollToResults();

    if (result) {
      GGR.UI?.updateSummary(result.scenario);
      GGR.UI?.renderInsights(result.insights);
    }
  }

  function handleError() {
    GGR.Utils?.showNotice("Audit request failed.", "error");
  }

  function runAudit($btn) {

    beforeAudit($btn);

    $.ajax({
      url: ggrwa_admin.ajaxUrl,
      type: "POST",
      cache: false, 
      dataType: "json",
      data: {
        action: "ggrwa_run_audit",
        nonce: ggrwa_admin.auditNonce,
        url: $("#ggr-audit-url").val().trim(),
        post_id: ggrwa_admin.postId || 0,
        t: Date.now() 
      },
    })
    .done(async function (response) {

      console.log("Audit Response:", response);

      if (!response || !response.success) {
        GGR.Utils?.showNotice("Audit failed", "warning");
        return;
      }

      await processResponse(response);

    })
    .fail(handleError)
    .always(function () {
      afterAudit($btn);
    });

  }

  return {
    runAudit
  };

})(jQuery, window.GGR);