jQuery(function ($) {
  $(".ggr-seo-toggle").on("click", function () {
    $(this).next(".ggr-seo-content").slideToggle(200);

    $(this)
      .find(".ggr-arrow")
      .text(function (_, text) {
        return text === "▼" ? "▲" : "▼";
      });
  });

  $("#_ggrwa_focus_keyword").on("blur", function () {
    let keyword = $(this).val();
    let postId = $("#ggr_post_id").val();

    $(".ggr-save-status").removeClass("success").text("Saving...");

    $.post(
      ajaxurl,
      {
        action: "ggr_save_focus_keyword",
        post_id: postId,
        keyword: keyword,
      },
      function (response) {
        if (response.success) {
          $(".ggr-save-status")
            .addClass("success")
            .text("✓ Focus keyword saved");

          setTimeout(function () {
            $(".ggr-save-status").fadeOut();
          }, 4000);
        }
      },
    );
  });
});
