(function ($, GGR) {

  "use strict";

  $(document).ready(function () {

    $("#ggr-run-audit").on("click", function () {

      const $btn = $(this);

      if (!GGR.Controller?.runAudit) {
        console.error("GGR Controller missing");
        return;
      }

      GGR.Controller.runAudit($btn);
    });

  });

})(jQuery, window.GGR);