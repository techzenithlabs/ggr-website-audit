window.GGR = window.GGR || {};

GGR.Utils = (function ($) {

  function scrollToResults() {
    const $target = $(".ggr-analyzer-header");
    if (!$target.length) return;

    $("html, body").animate({
      scrollTop: $target.offset().top - 40
    }, 600);
  }

  function showNotice(message, type = "info") {
    alert(message); // simple fallback
  }

  return {
    scrollToResults,
    showNotice
  };

})(jQuery);