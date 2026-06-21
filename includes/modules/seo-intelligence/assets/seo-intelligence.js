jQuery(document).ready(function ($) {
  /*
  |--------------------------------------------------------------------------
  | Cached State
  |--------------------------------------------------------------------------
  */

  let ggrLastContent = "";
  let ggrLastTitle = "";

  let ggrKeywordTimer;

  /*
  |--------------------------------------------------------------------------
  | Toggle SEO Panel
  |--------------------------------------------------------------------------
  */

  $(".ggr-seo-toggle").on("click", function () {
    $(this).next(".ggr-seo-content").slideToggle(200);

    $(this)
      .find(".ggr-arrow")
      .text(function (_, text) {
        return text === "▼" ? "▲" : "▼";
      });
  });

  /*
  |--------------------------------------------------------------------------
  | Save Focus Keyword
  |--------------------------------------------------------------------------
  */

  $("#_ggrwa_focus_keyword").on("blur", function () {
    let keyword = $(this).val().trim();

    let postId = $("#ggr_post_id").val();

    if (!keyword.length) {
      $(".ggr-save-status")
        .removeClass("success")
        .addClass("error")
        .text("⚠ Focus keyword cannot be empty");

      $("#ggr-keyword-status")
        .removeClass("ggr-status-success")
        .addClass("ggr-status-error")
        .text("⚠ No focus keyword configured");

      resetChecks();

      return;
    }

    $(".ggr-save-status").removeClass("success error").text("Saving...");

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
            .removeClass("error")
            .addClass("success")
            .text("✓ Focus keyword saved");
        }
      },
    );
  });

  /*
  |--------------------------------------------------------------------------
  | Keyword Typing
  |--------------------------------------------------------------------------
  */

  $(document).on("input", "#_ggrwa_focus_keyword", function () {
    clearTimeout(ggrKeywordTimer);

    ggrKeywordTimer = setTimeout(triggerAnalysis, 500);
  });

  /*
  |--------------------------------------------------------------------------
  | Classic Title Watch
  |--------------------------------------------------------------------------
  */

  $(document).on("input", "#title", triggerAnalysis);

  /*
  |--------------------------------------------------------------------------
  | Classic Editor Watcher
  |--------------------------------------------------------------------------
  */

  setInterval(function () {
    let currentContent = getPostContent();

    let currentTitle = getPostTitle();

    if (currentContent !== ggrLastContent || currentTitle !== ggrLastTitle) {
      ggrLastContent = currentContent;

      ggrLastTitle = currentTitle;

      triggerAnalysis();
    }
  }, 800);

  /*
  |--------------------------------------------------------------------------
  | Gutenberg Watcher
  |--------------------------------------------------------------------------
  */

  if (typeof wp !== "undefined" && wp.data && wp.data.subscribe) {
    wp.data.subscribe(function () {
      let content = getPostContent();

      let title = getPostTitle();

      if (content !== ggrLastContent || title !== ggrLastTitle) {
        ggrLastContent = content;

        ggrLastTitle = title;

        triggerAnalysis();
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Initial Analysis
  |--------------------------------------------------------------------------
  */

  setTimeout(triggerAnalysis, 1000);

  /*
  |--------------------------------------------------------------------------
  | Main Trigger
  |--------------------------------------------------------------------------
  */

  function triggerAnalysis() {
    let keyword = $("#_ggrwa_focus_keyword").val();

    runRealtimeSEO(keyword);
  }

  /*
  |--------------------------------------------------------------------------
  | Get Post Title
  |--------------------------------------------------------------------------
  */

  function getPostTitle() {
    if (
      typeof wp !== "undefined" &&
      wp.data &&
      wp.data.select &&
      wp.data.select("core/editor")
    ) {
      return (
        wp.data.select("core/editor").getEditedPostAttribute("title") || ""
      ).toLowerCase();
    }

    return ($("#title").val() || "").toLowerCase();
  }

  /*
  |--------------------------------------------------------------------------
  | Get Plain Text Content
  |--------------------------------------------------------------------------
  */

  function getPostContent() {
    if (
      typeof wp !== "undefined" &&
      wp.data &&
      wp.data.select &&
      wp.data.select("core/editor")
    ) {
      return (
        wp.data.select("core/editor").getEditedPostContent() || ""
      ).toLowerCase();
    }

    if (typeof tinymce !== "undefined" && tinymce.get("content")) {
      return tinymce
        .get("content")
        .getContent({
          format: "text",
        })
        .toLowerCase();
    }

    return ($("#content").val() || "").toLowerCase();
  }

  /*
  |--------------------------------------------------------------------------
  | Get Raw HTML Content
  |--------------------------------------------------------------------------
  */

  function getPostContentHTML() {
    if (
      typeof wp !== "undefined" &&
      wp.data &&
      wp.data.select &&
      wp.data.select("core/editor")
    ) {
      return wp.data.select("core/editor").getEditedPostContent() || "";
    }

    if (typeof tinymce !== "undefined" && tinymce.get("content")) {
      return tinymce.get("content").getContent();
    }

    return $("#content").val() || "";
  }

  /*
  |--------------------------------------------------------------------------
  | Update Foundation Card
  |--------------------------------------------------------------------------
  */

  function updateCheck(selector, passed, label) {
    let icon = passed ? "✓" : "⚠";

    $(selector)
      .removeClass("success warning error neutral")
      .addClass(passed ? "success" : "warning")
      .html(icon + " " + label);
  }

  /*
  |--------------------------------------------------------------------------
  | Reset Dashboard
  |--------------------------------------------------------------------------
  */

  function resetChecks() {
    $("#ggr-live-score").text("0/100");

    updateCheck("#ggr-check-title", false, "Keyword in Title");

    updateCheck("#ggr-check-url", false, "Keyword in URL");

    updateCheck("#ggr-check-content", false, "Keyword in Content");

    updateCheck("#ggr-check-meta", false, "Meta Description");
  }

  /*
  |--------------------------------------------------------------------------
  | Normalize Text
  |--------------------------------------------------------------------------
  */

  function normalizeText(text) {
    return (text || "")
      .toLowerCase()
      .replace(/[^\w\s]/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  /*
  |--------------------------------------------------------------------------
  | Match Type
  |--------------------------------------------------------------------------
  |
  | exact
  | partial
  | missing
  |
  */

  function getMatchType(text, keyword) {
    text = normalizeText(text);

    keyword = normalizeText(keyword);

    if (!keyword.length) {
      return "missing";
    }

    if (text === keyword) {
      return "exact";
    }

    if (text.indexOf(keyword) !== -1) {
      return "partial";
    }

    return "missing";
  }
  /*
  |--------------------------------------------------------------------------
  | Exact Match Helper
  |--------------------------------------------------------------------------
  */

  function isExactMatch(text, keyword) {
    return getMatchType(text, keyword) === "exact";
  }

  /*
  |--------------------------------------------------------------------------
  | Count Keyword Occurrences
  |--------------------------------------------------------------------------
  */

  function countKeywordOccurrences(content, keyword) {
    content = normalizeText(content);

    keyword = normalizeText(keyword);

    if (!keyword.length) {
      return 0;
    }

    let regex = new RegExp(keyword, "gi");

    let matches = content.match(regex);

    return matches ? matches.length : 0;
  }

  /*
  |--------------------------------------------------------------------------
  | Word Count
  |--------------------------------------------------------------------------
  */

  function getWordCount(content) {
    content = normalizeText(content);

    if (!content.length) {
      return 0;
    }

    return content.split(" ").filter(Boolean).length;
  }

  /*
  |--------------------------------------------------------------------------
  | Keyword Density
  |--------------------------------------------------------------------------
  */

  function getKeywordDensity(occurrences, words) {
    if (!words) {
      return 0;
    }

    return ((occurrences / words) * 100).toFixed(2);
  }

  /*
  |--------------------------------------------------------------------------
  | Metric Updater
  |--------------------------------------------------------------------------
  */

  function updateMetric(selector, value) {
    if ($(selector).length) {
      $(selector).text(value);
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Advanced Analysis
  |--------------------------------------------------------------------------
  */

  function runAdvancedAnalysis() {
    let html = getPostContentHTML();

    let tempDiv = document.createElement("div");

    tempDiv.innerHTML = html;

    /*
    |--------------------------------------------------------------------------
    | H2 / H3
    |--------------------------------------------------------------------------
    */

    let h2Count = tempDiv.querySelectorAll("h2").length;

    let h3Count = tempDiv.querySelectorAll("h3").length;

    updateMetric("#ggr-h2-count", h2Count);

    updateMetric("#ggr-h3-count", h3Count);

    /*
    |--------------------------------------------------------------------------
    | Links
    |--------------------------------------------------------------------------
    */

    let internalLinks = 0;
    let externalLinks = 0;

    let siteHost = location.hostname;

    tempDiv.querySelectorAll("a[href]").forEach(function (a) {
      let href = a.getAttribute("href") || "";

      if (href.indexOf(siteHost) !== -1) {
        internalLinks++;
      } else {
        externalLinks++;
      }
    });

    updateMetric("#ggr-internal-links", internalLinks);

    updateMetric("#ggr-external-links", externalLinks);

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    */

    let imagesFound = 0;
    let missingAlt = 0;

    tempDiv.querySelectorAll("img").forEach(function (img) {
      imagesFound++;

      let alt = img.getAttribute("alt");

      if (!alt || !alt.trim()) {
        missingAlt++;
      }
    });

    updateMetric("#ggr-images-count", imagesFound);

    updateMetric("#ggr-image-alt", missingAlt);

    /*
    |--------------------------------------------------------------------------
    | Featured Image
    |--------------------------------------------------------------------------
    */

    let featuredImage = $(".editor-post-featured-image img").length > 0;

    updateMetric("#ggr-featured-image", featuredImage ? "Yes" : "No");
  }

  /*
  |--------------------------------------------------------------------------
  | Main SEO Engine
  |--------------------------------------------------------------------------
  */

  function runRealtimeSEO(keyword) {
    keyword = normalizeText(keyword);

    if (!keyword.length) {
      resetChecks();

      return;
    }

    let score = 0;

    let title = getPostTitle();

    let content = getPostContent();

    let meta = "";

    if ($("#rank_math_description").length) {
      meta = $("#rank_math_description").val() || "";
    }

    let slug = ($("#editable-post-name-full").text() || "")
      .toLowerCase()
      .trim();

    let titleMatch = isExactMatch(title, keyword);

    let contentMatch = getMatchType(content, keyword) !== "missing";

    let metaMatch = getMatchType(meta, keyword) !== "missing";

    let urlMatch = slug === keyword.replace(/\s+/g, "-");

    let wordCount = getWordCount(content);

    let occurrences = countKeywordOccurrences(content, keyword);

    let density = getKeywordDensity(occurrences, wordCount);

    let keywordOptimized =
      titleMatch && contentMatch && wordCount >= 600 && occurrences >= 2;

    updateCheck("#ggr-check-title", titleMatch, "Keyword in Title");

    updateCheck("#ggr-check-url", urlMatch, "Keyword in URL");

    updateCheck("#ggr-check-content", contentMatch, "Keyword in Content");

    updateCheck("#ggr-check-meta", metaMatch, "Meta Description");

    updateCheck(
      "#ggr-check-keyword",
      keywordOptimized,
      "Focus Keyword Optimized",
    );

    if (keywordOptimized) score += 20;

    if (titleMatch) score += 20;

    if (urlMatch) score += 20;

    if (contentMatch) score += 20;

    if (metaMatch) score += 20;

    updateMetric("#ggr-word-count", wordCount);

    updateMetric("#ggr-keyword-occurrences", occurrences);

    updateMetric("#ggr-keyword-density", density + "%");

    $("#ggr-live-score").text(score + "/100");

    $("#ggr-current-score").text(score + "/100");

    $("#ggr-potential-score").text("+" + (100 - score));

    let foundation = 0;

    if (keywordOptimized) foundation++;

    if (titleMatch) foundation++;

    if (urlMatch) foundation++;

    if (contentMatch) foundation++;

    if (metaMatch) foundation++;

    $("#ggr-foundation-count").text(foundation + "/5");

    let opportunities = 0;

    if (!titleMatch) opportunities++;

    if (!urlMatch) opportunities++;

    if (!contentMatch) opportunities++;

    if (!metaMatch) opportunities++;

    $("#ggr-opportunity-count").text(opportunities);

    runAdvancedAnalysis();
  }
});
