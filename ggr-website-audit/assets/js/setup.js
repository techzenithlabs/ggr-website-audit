jQuery(document).ready(function ($) {
  $(document).on("click", ".ggr-start-btn", function (e) {
    e.preventDefault();

    $(".ggr-setup-card").html(`
             <div class="ggr-scan-loader">

        <div class="ggr-scan-animation">
            <div class="ggr-screen">
                <div class="ggr-bars">
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="ggr-glass"></div>
            </div>
        </div>

        <h2>Scanning Your Website...</h2>
        <p class="ggr-scan-text">Analyzing SEO signals, content & performance</p>

    </div>
        `);

    $.ajax({
      url: ggrwa_setup.ajaxUrl,
      type: "POST",
      dataType: "json",
      data: {
        action: "ggrwa_run_audit",
        nonce: ggrwa_setup.auditNonce,
        url: ggrwa_setup.homeUrl,
      },

      success: function (response) {
          const minTime = 4500; 
          const startTime = window.ggrStartTime || Date.now();
          const elapsed = Date.now() - startTime;
          const remaining = Math.max(0, minTime - elapsed);
        if (response && response.success) {
        setTimeout(function () {       
            $('.ggr-setup-card').html(`
                <h2>✅ Audit Completed</h2>
                <p>Your website scan is complete.</p>
                <p><strong>Score: ${response.data.score.total}</strong></p>
                <p>Redirecting to dashboard...</p>
            `);
   
            setTimeout(function () {
                window.location.href = ggrwa_setup.dashboardUrl;
            }, 2500);

        }, remaining);
        } else {
          alert("Audit failed");
        }
      },

      error: function () {
        alert("Server error");
      },
    });
  });
});
