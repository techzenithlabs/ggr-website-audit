jQuery(document).ready(function ($) {
  /*
  |--------------------------------------------------------------------------
  | Cached State
  |--------------------------------------------------------------------------
  */

  let ggrLastContent = "";

  let ggrLastTitle = "";

  let ggrLastSlug = "";

  let ggrLastFeaturedImage = false;

  let ggrLastCategory = "";

  let ggrLastMetaTitle = "";

  let ggrLastMetaDescription = "";

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

          setTimeout(function () {
            $(".ggr-save-status").fadeOut(200, function () {
              $(this).text("").show();
            });
          }, 1500);
        }
      },
    );
  });

  /*
  |--------------------------------------------------------------------------
  | Save Meta Title
  |--------------------------------------------------------------------------
  */

  $("#ggr-meta-title").on("blur", function () {
    let metaTitle = $(this).val().trim();

    let postId = $("#ggr_post_id").val();

    $("#ggr-meta-title-save-status")
      .removeClass("success error")
      .text("Saving...");

    $.post(
      ajaxurl,
      {
        action: "ggrwa_save_meta_title",
        post_id: postId,
        meta_title: metaTitle,
      },
      function (response) {
        if (response.success) {
          $("#ggr-meta-title-save-status")
            .removeClass("error")
            .addClass("success")
            .text("✓ Meta title saved");

          setTimeout(function () {
            $("#ggr-meta-title-save-status").fadeOut(200, function () {
              $(this).text("").show();
            });
          }, 1500);
        }
      },
    );
  });

  /*
  |--------------------------------------------------------------------------
  | Save Meta Description
  |--------------------------------------------------------------------------
  */

  $("#ggr-meta-description").on("blur", function () {
    let metaDescription = $(this).val().trim();

    let postId = $("#ggr_post_id").val();

    $("#ggr-meta-description-save-status")
      .removeClass("success error")
      .text("Saving...");

    $.post(
      ajaxurl,
      {
        action: "ggrwa_save_meta_description",
        post_id: postId,
        meta_description: metaDescription,
      },
      function (response) {
        if (response.success) {
          $("#ggr-meta-description-save-status")
            .removeClass("error")
            .addClass("success")
            .text("✓ Meta description saved");

          setTimeout(function () {
            $("#ggr-meta-description-save-status").fadeOut(200, function () {
              $(this).text("").show();
            });
          }, 1500);
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
    let currentContent = getPostContentHTML();

    let currentTitle = getPostTitle();

    let currentSlug = getPostSlug();

    let currentFeaturedImage = hasFeaturedImage();

    let currentCategory = getSelectedCategory();

    let currentMetaTitle = $("#ggr-meta-title").val() || "";

    let currentMetaDescription = $("#ggr-meta-description").val() || "";

    if (
      currentContent !== ggrLastContent ||
      currentTitle !== ggrLastTitle ||
      currentSlug !== ggrLastSlug ||
      currentCategory !== ggrLastCategory ||
      currentFeaturedImage !== ggrLastFeaturedImage ||
      currentMetaTitle !== ggrLastMetaTitle ||
      currentMetaDescription !== ggrLastMetaDescription
    ) {
      ggrLastContent = currentContent;

      ggrLastTitle = currentTitle;

      ggrLastSlug = currentSlug;

      ggrLastCategory = currentCategory;

      ggrLastFeaturedImage = currentFeaturedImage;

      ggrLastMetaTitle = currentMetaTitle;

      ggrLastMetaDescription = currentMetaDescription;

      setTimeout(function () {
        triggerAnalysis();
      }, 500);
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
| SEO Snippet Optimization
|--------------------------------------------------------------------------
*/

  function updateSnippetOptimization() {
    let metaTitle = ($("#ggr-meta-title").val() || "").trim();

    let metaDescription = ($("#ggr-meta-description").val() || "").trim();

    let titleLength = metaTitle.length;

    let descriptionLength = metaDescription.length;

    /*
    |--------------------------------------------------------------------------
    | Title Validation
    |--------------------------------------------------------------------------
    */

    let titleStatus = "Neutral";
    let titleClass = "neutral";
    let titleValid = false;

    if (titleLength > 0 && titleLength < 30) {
      titleStatus = "Too Short";
      titleClass = "warning";
    } else if (titleLength >= 30 && titleLength <= 60) {
      titleStatus = "Optimized";
      titleClass = "success";
      titleValid = true;
    } else if (titleLength > 60) {
      titleStatus = "Too Long";
      titleClass = "error";
    }

    /*
    |--------------------------------------------------------------------------
    | Description Validation
    |--------------------------------------------------------------------------
    */

    let descriptionStatus = "Neutral";
    let descriptionClass = "neutral";
    let descriptionValid = false;

    if (descriptionLength > 0 && descriptionLength < 120) {
      descriptionStatus = "Too Short";
      descriptionClass = "warning";
    } else if (descriptionLength >= 120 && descriptionLength <= 160) {
      descriptionStatus = "Optimized";
      descriptionClass = "success";
      descriptionValid = true;
    } else if (descriptionLength > 160) {
      descriptionStatus = "Too Long";
      descriptionClass = "error";
    }

    /*
    |--------------------------------------------------------------------------
    | Status Badges
    |--------------------------------------------------------------------------
    */
    $("#ggr-meta-title-status")
      .removeClass("success warning error neutral")
      .addClass(titleClass)
      .text(titleStatus + " (" + titleLength + "/60)");

    $("#ggr-meta-description-status")
      .removeClass("success warning error neutral")
      .addClass(descriptionClass)
      .text(descriptionStatus + " (" + descriptionLength + "/160)");

    /*
    |--------------------------------------------------------------------------
    | Snippet Score
    |--------------------------------------------------------------------------
    */

    let snippetScore = 0;

    if (titleValid) {
      snippetScore++;
    }

    if (descriptionValid) {
      snippetScore++;
    }

    $("#ggr-snippet-score")
      .text(snippetScore + "/2")
      .removeClass("success warning error neutral");

    if (snippetScore === 0) {
      $("#ggr-snippet-score").addClass("error");
    } else if (snippetScore === 1) {
      $("#ggr-snippet-score").addClass("warning");
    } else {
      $("#ggr-snippet-score").addClass("success");
    }

    /*
    |--------------------------------------------------------------------------
    | SERP Preview
    |--------------------------------------------------------------------------
    */

    $("#ggr-serp-title").text(metaTitle || "Your SEO Title Preview");

    $("#ggr-serp-description").text(
      metaDescription || "Your meta description preview will appear here.",
    );
  }

  /*
  |--------------------------------------------------------------------------
  | Initial Analysis
  |--------------------------------------------------------------------------
  */

  setTimeout(triggerAnalysis, 1000);

  setTimeout(updateSnippetOptimization, 1000);

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
  | Get Post Slug
  |--------------------------------------------------------------------------
  */
  function getPostSlug() {
    let permalink = $("#sample-permalink").text() || "";

    return permalink.trim().toLowerCase();
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
  | Get Post Category
  |--------------------------------------------------------------------------
  */

  function getSelectedCategory() {
    let selectedCategory = "";

    $("#categorychecklist input[type='checkbox']:checked").each(function () {
      let categoryName = $(this)
        .closest("label")
        .contents()
        .filter(function () {
          return this.nodeType === 3;
        })
        .text()
        .trim();

      if (categoryName) {
        selectedCategory = categoryName;

        return false;
      }
    });

    return selectedCategory;
  }

  /*
|--------------------------------------------------------------------------
| Featured Image Check
|--------------------------------------------------------------------------
*/

  function hasFeaturedImage() {
    // Gutenberg

    if (
      typeof wp !== "undefined" &&
      wp.data &&
      wp.data.select &&
      wp.data.select("core/editor")
    ) {
      let mediaId = wp.data
        .select("core/editor")
        .getEditedPostAttribute("featured_media");

      return parseInt(mediaId || 0) > 0;
    }

    // Classic Editor

    return parseInt($("#_thumbnail_id").val() || 0) > 0;
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
      .addClass(passed ? "success" : "warning");

    let labelElement = $(selector).find(".ggr-check-label");

    if (labelElement.length) {
      labelElement.text(icon + " " + label);
    } else {
      $(selector).text(icon + " " + label);
    }
  }

  function updateQuickFix(selector, passed, label) {
    if (passed) {
      $(selector).hide();
    } else {
      $(selector)
        .show()
        .html("❌ " + label);
    }
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

    updateCheck("#ggr-check-category", false, "Category Assigned");
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
  | Inbuilt Wordpress Count Logic
  |--------------------------------------------------------------------------
  */

  function getWordPressWordCount() {
    let count = 0;

    $(".word-count").each(function () {
      let text = $(this).text();

      let match = text.match(/\d+/);

      if (match) {
        count = parseInt(match[0], 10);
      }
    });

    return count;
  }

  /*
  |--------------------------------------------------------------------------
  | Safe Wordpress Count Logic
  |--------------------------------------------------------------------------
  */

  function getSafeWordCount(content) {
    let wpCount = getWordPressWordCount();

    if (wpCount > 0) {
      return wpCount;
    }

    return getWordCount(content);
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
    | PostFeatured Image
    |--------------------------------------------------------------------------
    */

    let featuredImage = hasFeaturedImage();

    updateMetric("#ggr-featured-image", featuredImage ? "Yes" : "No");
  }

  /*
  |--------------------------------------------------------------------------
  | Main SEO Engine
  |--------------------------------------------------------------------------
  */

  function runRealtimeSEO(keyword) {
    let postType = $("#post_type").val();
    let categoryName = getSelectedCategory();
    let metaTitle = $("#ggr-meta-title").val() || "";
    let metaDescription = $("#ggr-meta-description").val() || "";
    let featuredImage = hasFeaturedImage();
    let categoryValid =
      categoryName && categoryName.toLowerCase() !== "uncategorized";

    checkPermalinkStructure();

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

    /*
    |--------------------------------------------------------------------------
    | URL / Slug
    |--------------------------------------------------------------------------
    */

    let slug = getPostSlug();

    let keywordSlug = normalizeText(keyword).replace(/\s+/g, "-");

    let normalizedSlug = normalizeText(slug).replace(/\s+/g, "-");

    let titleMatch = normalizeText(title).includes(keyword);

    let metaTitleMatch = getMatchType(metaTitle, keyword) !== "missing";

    let metaDescriptionMatch =
      getMatchType(metaDescription, keyword) !== "missing";

    let contentMatch = getMatchType(content, keyword) !== "missing";

    let metaMatch = getMatchType(meta, keyword) !== "missing";

    let urlMatch = normalizedSlug.includes(keywordSlug);

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */

    let wordCount = getSafeWordCount(content);

    let occurrences = countKeywordOccurrences(content, keyword);

    let density = getKeywordDensity(occurrences, wordCount);

    let keywordOptimized =
      titleMatch && contentMatch && wordCount >= 600 && occurrences >= 2;

    let passedChecks = 0;

    if (titleMatch) passedChecks++;
    if (urlMatch) passedChecks++;
    if (contentMatch) passedChecks++;
    if (wordCount >= 600) passedChecks++;
    if (occurrences >= 2) passedChecks++;

    let keywordExplanation = `
      <div class="ggr-keyword-details-header">
          Focus Keyword Optimization
      </div>

      <div class="ggr-keyword-details-item">
          ${titleMatch ? "✅" : "❌"} Keyword in Title
      </div>

      <div class="ggr-keyword-details-item">
          ${urlMatch ? "✅" : "❌"} Keyword in URL
      </div>

      <div class="ggr-keyword-details-item">
          ${contentMatch ? "✅" : "❌"} Keyword in Content
      </div>

      <div class="ggr-keyword-details-item">
          ${wordCount >= 600 ? "✅" : "❌"} Word Count (${wordCount}/600)
      </div>

      <div class="ggr-keyword-details-item">
          ${occurrences >= 2 ? "✅" : "❌"} Keyword Usage (${occurrences}/2)
      </div>

      <div class="ggr-keyword-details-footer">
          Progress: ${passedChecks}/5 Requirements Met
      </div>
      `;

    $("#ggr-keyword-details").html(keywordExplanation);

    /*
    |--------------------------------------------------------------------------
    | SEO Checks (Future Scalable)
    |--------------------------------------------------------------------------
    */

    const seoChecks = [
      {
        key: "keyword",
        label: "Focus Keyword Optimized",
        passed: keywordOptimized,
      },

      {
        key: "title",
        label: "Keyword in Title",
        passed: titleMatch,
        quickFix: "Add focus keyword to title",
      },

      {
        key: "url",
        label: "Keyword in URL",
        passed: urlMatch,
        quickFix: "Add focus keyword to URL",
      },

      {
        key: "content",
        label: "Keyword in Content",
        passed: contentMatch,
        quickFix: "Add focus keyword to content",
      },

      {
        key: "meta-title",
        label: "Meta Title",
        passed: metaTitleMatch,
        quickFix: "Add focus keyword to meta title",
      },

      {
        key: "meta-description",
        label: "Meta Description",
        passed: metaDescriptionMatch,
        quickFix: "Add focus keyword to meta description",
      },

      {
        key: "category",
        label: "Category Assigned",
        passed: postType !== "post" ? true : categoryValid,
        quickFix: "Assign a relevant category",
      },

      {
        key: "featured-image",
        label: "Featured Image",
        passed: featuredImage,
        quickFix: "Add Featured Image",
      },
    ];

    /*
    |--------------------------------------------------------------------------
    | Foundation Checks
    |--------------------------------------------------------------------------
    */

    seoChecks.forEach((check) => {
      if (check.key === "keyword") {
        $("#ggr-check-keyword")
          .removeClass("success warning error neutral")
          .addClass(keywordOptimized ? "success" : "warning");

        $("#ggr-check-keyword .ggr-check-label").text(
          (keywordOptimized ? "✓ " : "⚠ ") + "Focus Keyword Optimized",
        );

        $("#ggr-keyword-toggle")
          .text(keywordOptimized ? "View Details" : "Why?")
          .removeClass("success warning")
          .addClass(keywordOptimized ? "success" : "warning");

        return;
      }

      updateCheck("#ggr-check-" + check.key, check.passed, check.label);
    });

    /*
    |--------------------------------------------------------------------------
    | Quick Fixes
    |--------------------------------------------------------------------------
    */

    seoChecks.forEach((check) => {
      if (!check.quickFix) {
        return;
      }

      updateQuickFix("#ggr-fix-" + check.key, check.passed, check.quickFix);
    });

    /*
    |--------------------------------------------------------------------------
    | Score
    |--------------------------------------------------------------------------
    */

    let totalChecks = seoChecks.length;

    let passedChecksCount = seoChecks.filter((check) => check.passed).length;

    score = Math.round((passedChecksCount / totalChecks) * 100);

    /*
    |--------------------------------------------------------------------------
    | Metrics UI
    |--------------------------------------------------------------------------
    */

    updateMetric("#ggr-word-count", wordCount);

    updateMetric("#ggr-keyword-occurrences", occurrences);

    updateMetric("#ggr-keyword-density", density + "%");

    $("#ggr-live-score").text(score + "/100");

    $("#ggr-current-score").text(score + "/100");

    $("#ggr-potential-score").text("+" + (100 - score));

    /*
    |--------------------------------------------------------------------------
    | Foundation Count
    |--------------------------------------------------------------------------
    */

    let foundation = seoChecks.filter((check) => check.passed).length;

    let totalseoChecks = seoChecks.length;

    $("#ggr-foundation-count").text(foundation + "/" + totalseoChecks);

    /*
    |--------------------------------------------------------------------------
    | Opportunities
    |--------------------------------------------------------------------------
    */

    let opportunities = seoChecks.filter(
      (check) => check.quickFix && !check.passed,
    ).length;

    $("#ggr-opportunity-count").text(opportunities);

    if (opportunities === 0) {
      $("#ggr-opportunity-badge").text("ALL FIXED").addClass("success");
    } else {
      $("#ggr-opportunity-badge")
        .text("ACTION REQUIRED")
        .removeClass("success");
    }

    runAdvancedAnalysis();
  }

  /*
  |--------------------------------------------------------------------------
  | Check Permalink Structure
  |--------------------------------------------------------------------------
  */
  function checkPermalinkStructure() {
    let structure = $("#ggr_permalink_structure").val() || "";

    let warningBox = $("#ggr-permalink-warning");

    if (!warningBox.length) {
      return;
    }

    warningBox.hide();

    if (structure === "" || structure === "/archives/%post_id%") {
      warningBox.html(`
            ⚠ <strong>SEO Friendly URLs Recommended</strong><br>
            Your site is using Numeric Permalinks.<br>
            Recommended: Post Name<br><br>
                 
            <a href="${ajaxurl.replace(
              "/admin-ajax.php",
              "/options-permalink.php",
            )}" target="_blank">
                Fix Permalink
            </a> | <a href="#" id="ggr-view-permalink">
                  View permalink ↗
                  </a>
        `);

      warningBox.show();
    }
  }

  $(document).on(
    "input",
    "#ggr-meta-title, #ggr-meta-description",
    function () {
      updateSnippetOptimization();
    },
  );

  $(document).on("click", "#ggr-view-permalink", function (e) {
    e.preventDefault();

    let box = document.getElementById("edit-slug-box");

    if (box) {
      box.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });

      box.style.background = "#fff3cd";

      setTimeout(function () {
        box.style.background = "";
      }, 2000);
    }
  });
  $(document).on("click", "#ggr-keyword-toggle", function () {
    $("#ggr-keyword-details").stop(true, true).slideToggle(150);
  });
});
