window.GGR = window.GGR || {};

GGR.Engine = (function () {
  "use strict";

  function calculate(baseScore, results, data = {}) {
    let score = baseScore || 100;

    let insights = [];
    let priority = {
      high: [],
      medium: [],
      low: [],
    };

    // use details array
    const items = results?.details || [];

    items.forEach((r) => {
      if (r.score === 0) {
        switch (r.id) {
          case "keywordInTitle":
            score -= 15;
            insights.push(
              "Your title does not include the main keyword → reduces ranking potential.",
            );
            priority.high.push("Add focus keyword in SEO title");
            break;

          case "lengthContent":
            score -= 10;
            insights.push(
              "Content is too short → not enough depth to compete.",
            );
            priority.medium.push("Expand content with real use-cases");
            break;

          case "keywordDensity":
            score -= 8;
            insights.push(
              "Keyword usage is too low → search engines may not understand topic.",
            );
            priority.medium.push("Use keyword naturally in content");
            break;

          case "internalLinks": //FIX name match (important)
            score -= 8;
            insights.push(
              "No internal links → weak site structure and SEO flow.",
            );
            priority.medium.push("Add 2–3 internal links");
            break;

          default:
            score -= 3;
            priority.low.push(r.text || "Minor issue detected");
        }
      }
    });

    //  Extra penalty from analyzer summary (optional but powerful)
    score -= (results?.critical || 0) * 2;

    // Normalize
    const finalScore = Math.min(Math.max(score, 0), 100);

    return {
      score: finalScore,
      insights,
      priority,
      scenario: generateScenario(finalScore),
      conversion: conversionInsight(data),
    };
  }

  function generateScenario(score) {
    if (score < 40) {
      return "Your page may struggle to rank on Google due to critical SEO gaps.";
    }

    if (score < 70) {
      return "Your page has potential but is missing key optimization signals.";
    }

    return "Your page is well optimized and ready to compete in search results.";
  }

  function conversionInsight(data = {}) {
    if (!data.has_cta) {
      return "Visitors may not convert because there is no clear call-to-action.";
    }

    if (data.load_time > 3) {
      return "Slow loading speed may reduce conversions significantly.";
    }

    return null;
  }

  return {
    calculate,
  };
})();
