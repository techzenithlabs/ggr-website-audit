window.GGR = window.GGR || {};

(function ($, GGR) {
  "use strict";

  $(function () {

    $(document).on("click", ".ggr-run-audit-btn", function (e) {
      e.preventDefault();

      const $btn = $(this);

      if ($btn.hasClass("is-loading")) return;

      if (!GGR.Controller || typeof GGR.Controller.runAudit !== "function") {
        console.warn("GGR Controller not available");
        return;
      }

      $(document).trigger("ggr:audit:start");

      GGR.Controller.runAudit($btn);

    });

  });

})(jQuery, window.GGR);