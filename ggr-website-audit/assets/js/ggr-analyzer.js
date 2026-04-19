window.GGR = window.GGR || {};

GGR.Analyzer = (function ($) {

  "use strict";

  function normalize(results = []) {
    return results.map(r => ({
      id: r.id || "unknown",
      score: Number(r.score || 0),
      text: r.text || "",
      severity: r.score === 0 ? "critical" : "good"
    }));
  }

  function extractContent() {
    const article = document.querySelector("article") || document.body;
    return article.innerText || "";
  }

  function runCustom(context = {}) {

    const content = extractContent();
    const title = document.title || "";
    const url = window.location.href || "";
    const keyword = (context.keyword || "").toLowerCase();

    let results = [];

    const wordCount = content.split(/\s+/).length;

    // TITLE
    results.push({
      id: "keywordInTitle",
      score: keyword && title.toLowerCase().includes(keyword) ? 1 : 0,
      text: "Keyword in title"
    });

    // URL
    results.push({
      id: "keywordInUrl",
      score: keyword && url.toLowerCase().includes(keyword) ? 1 : 0,
      text: "Keyword in URL"
    });

    // CONTENT LENGTH
    results.push({
      id: "contentLength",
      score: wordCount >= 600 ? 1 : 0,
      text: "Content length >= 600 words"
    });

    // KEYWORD DENSITY
    if (keyword) {
      const occurrences = (content.toLowerCase().match(new RegExp(keyword, "g")) || []).length;
      const density = (occurrences / wordCount) * 100;

      results.push({
        id: "keywordDensity",
        score: density >= 0.5 && density <= 2.5 ? 1 : 0,
        text: "Keyword density optimized"
      });
    }

    // INTERNAL LINKS
    const internalLinks = document.querySelectorAll(`a[href*="${location.hostname}"]`);
    results.push({
      id: "internalLinks",
      score: internalLinks.length > 0 ? 1 : 0,
      text: "Internal links present"
    });

    return normalize(results);
  }

  async function run(context = {}) {

    let results = [];

    results = runCustom(context);

    // Aggregate for engine
    return {
      total: results.length,
      passed: results.filter(r => r.score > 0).length,
      failed: results.filter(r => r.score === 0).length,
      critical: results.filter(r => r.severity === "critical").length,
      details: results
    };
  }

  return {
    run
  };

})(jQuery);