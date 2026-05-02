<?php
/**
 * Page Analyzer — per-page/post/CPT deep SEO analysis view.
 *
 * @package GGR_Website_Audit
 * @since   3.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$post_types = ggrwa_get_analyzable_post_types();
?>
<div class="wrap gpa-wrap" id="gpa-app">

    <!-- ══ HEADER ══════════════════════════════════════════════════════════ -->
    <div class="gpa-header">
        <div class="gpa-header-brand">
            <div class="gpa-logo-box">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                    <rect width="22" height="22" rx="6" fill="#1e3a5f"/>
                    <circle cx="9" cy="9" r="4" stroke="white" stroke-width="2"/>
                    <line x1="12" y1="12" x2="17" y2="17" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
            <div>
                <div class="gpa-plugin-name">Page Analyzer</div>
                <div class="gpa-plugin-sub">Deep per-page SEO analysis</div>
            </div>
        </div>
        <div class="gpa-header-right">
            <span class="gpa-badge-pro">PRO</span>
        </div>
    </div>

    <!-- ══ SEARCH PANEL ════════════════════════════════════════════════════ -->
    <div class="gpa-search-panel">
        <div class="gpa-search-top">
            <div class="gpa-type-pills" id="gpa-type-pills">
                <button class="gpa-type-pill gpa-type-active" data-type="" title="Search across all content types — posts, pages, and any custom post type">
                    All Types
                </button>
                <?php foreach ( $post_types as $slug => $label ) : ?>
                <button class="gpa-type-pill" data-type="<?php echo esc_attr( $slug ); ?>"
                    title="Search only <?php echo esc_attr( $label ); ?>s — results will be filtered to this type only">
                    <?php echo esc_html( $label ); ?>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="gpa-type-hint" id="gpa-type-hint">Searching across <strong>all content types</strong>. Select a type above to narrow results and catch type mismatches.</div>
        </div>
        <div class="gpa-search-row">
            <div class="gpa-search-box">
                <svg class="gpa-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <circle cx="6.5" cy="6.5" r="4.5" stroke="#9ca3af" stroke-width="1.5"/>
                    <line x1="10" y1="10" x2="14" y2="14" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <input type="text" id="gpa-search-input" class="gpa-search-input"
                    placeholder="Search by title, slug, URL (?p=123), category, tag, or custom field…" autocomplete="off"/>
                <div class="gpa-search-dropdown" id="gpa-search-dropdown"></div>
            </div>
            <button class="gpa-analyze-btn" id="gpa-analyze-btn" disabled>
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M7 1v6M7 7l3-3M7 7l-3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M1 10c0 1.657 2.686 3 6 3s6-1.343 6-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                Analyze Page
            </button>
        </div>
        <div class="gpa-selected-post" id="gpa-selected-post" style="display:none;">
            <span class="gpa-sel-icon">📄</span>
            <span class="gpa-sel-title" id="gpa-sel-title"></span>
            <span class="gpa-sel-type" id="gpa-sel-type"></span>
            <button class="gpa-sel-clear" id="gpa-sel-clear" title="Clear selection">×</button>
        </div>
    </div>

    <!-- ══ RESULTS AREA ════════════════════════════════════════════════════ -->
    <div id="gpa-results" style="display:none;">

        <!-- Status & Permalink Banner (injected by JS) -->
        <div id="gpa-status-banner" style="display:none;"></div>

        <!-- Score Hero -->
        <div class="gpa-score-hero" id="gpa-score-hero">
            <div class="gpa-score-ring-wrap">
                <svg class="gpa-ring-svg" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="#e5e7eb" stroke-width="10"/>
                    <circle cx="60" cy="60" r="52" fill="none"
                        stroke="#1e3a5f" stroke-width="10" stroke-linecap="round"
                        stroke-dasharray="326.73" stroke-dashoffset="326.73"
                        transform="rotate(-90 60 60)" id="gpa-ring-circle"/>
                    <text x="60" y="56" text-anchor="middle" class="gpa-ring-score-text" id="gpa-ring-val">0</text>
                    <text x="60" y="74" text-anchor="middle" class="gpa-ring-denom">/100</text>
                </svg>
                <div class="gpa-score-grade" id="gpa-score-grade">–</div>
            </div>
            <div class="gpa-score-meta">
                <h2 class="gpa-score-title" id="gpa-score-title">–</h2>
                <div class="gpa-score-url" id="gpa-score-url"></div>
                <div class="gpa-score-pills">
                    <span class="gpa-pill" id="gpa-pill-type"></span>
                    <span class="gpa-pill" id="gpa-pill-words"></span>
                    <span class="gpa-pill" id="gpa-pill-kw"></span>
                </div>
                <div class="gpa-score-actions">
                    <a href="#" class="gpa-btn-edit" id="gpa-btn-edit" target="_blank">✏ Edit Post</a>
                    <a href="#" class="gpa-btn-view" id="gpa-btn-view" target="_blank">↗ View Live</a>
                </div>
            </div>
            <div class="gpa-score-summary" id="gpa-score-summary">
                <div class="gpa-summary-stat" id="gpa-sum-good"><span class="gpa-sum-num">0</span><span class="gpa-sum-lbl">Passed</span></div>
                <div class="gpa-summary-stat" id="gpa-sum-warn"><span class="gpa-sum-num">0</span><span class="gpa-sum-lbl">Warnings</span></div>
                <div class="gpa-summary-stat" id="gpa-sum-crit"><span class="gpa-sum-num">0</span><span class="gpa-sum-lbl">Critical</span></div>
            </div>
        </div>

        <!-- Core Web Vitals Panel -->
        <div class="gpa-cwv-panel" id="gpa-cwv-panel">
            <div class="gpa-cwv-header">
                <span class="gpa-cwv-title">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="vertical-align:-2px;margin-right:5px;">
                        <circle cx="7" cy="7" r="6" stroke="#1e3a5f" stroke-width="1.5"/>
                        <path d="M4 7l2 2 4-4" stroke="#1e3a5f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Core Web Vitals
                </span>
                <span class="gpa-cwv-source">via Google PageSpeed Insights</span>
                <div class="gpa-cwv-strategy-toggle">
                    <button class="gpa-strat-btn gpa-strat-active" data-strategy="mobile">Mobile</button>
                    <button class="gpa-strat-btn" data-strategy="desktop">Desktop</button>
                </div>
            </div>
            <?php if ( ! GGRWA_PageSpeed::has_api_key() ) : ?>
            <div class="gpa-cwv-no-key">
                <span>&#x1F511;</span>
                No PageSpeed API key configured.
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ggrwa-audit-settings' ) ); ?>">Add your free key →</a>
            </div>
            <?php else : ?>
            <div class="gpa-cwv-metrics" id="gpa-cwv-metrics">
                <div class="gpa-cwv-metric" id="gpa-cwv-lcp">
                    <div class="gpa-cwv-metric-label">LCP <span class="gpa-cwv-hint">(Largest Contentful Paint)</span></div>
                    <div class="gpa-cwv-metric-val" id="gpa-cwv-lcp-val">–</div>
                    <div class="gpa-cwv-bar"><div class="gpa-cwv-bar-fill" id="gpa-cwv-lcp-bar"></div></div>
                    <div class="gpa-cwv-threshold">Good ≤ 2.5s</div>
                </div>
                <div class="gpa-cwv-metric" id="gpa-cwv-cls">
                    <div class="gpa-cwv-metric-label">CLS <span class="gpa-cwv-hint">(Cumulative Layout Shift)</span></div>
                    <div class="gpa-cwv-metric-val" id="gpa-cwv-cls-val">–</div>
                    <div class="gpa-cwv-bar"><div class="gpa-cwv-bar-fill" id="gpa-cwv-cls-bar"></div></div>
                    <div class="gpa-cwv-threshold">Good ≤ 0.1</div>
                </div>
                <div class="gpa-cwv-metric" id="gpa-cwv-fid">
                    <div class="gpa-cwv-metric-label">TBT <span class="gpa-cwv-hint">(Total Blocking Time)</span></div>
                    <div class="gpa-cwv-metric-val" id="gpa-cwv-fid-val">–</div>
                    <div class="gpa-cwv-bar"><div class="gpa-cwv-bar-fill" id="gpa-cwv-fid-bar"></div></div>
                    <div class="gpa-cwv-threshold">Good ≤ 200ms</div>
                </div>
                <div class="gpa-cwv-metric" id="gpa-cwv-perf">
                    <div class="gpa-cwv-metric-label">Performance Score</div>
                    <div class="gpa-cwv-metric-val" id="gpa-cwv-perf-val">–</div>
                    <div class="gpa-cwv-bar"><div class="gpa-cwv-bar-fill" id="gpa-cwv-perf-bar"></div></div>
                    <div class="gpa-cwv-threshold">Good ≥ 90</div>
                </div>
            </div>
            <div class="gpa-cwv-opps" id="gpa-cwv-opps"></div>
            <div class="gpa-cwv-loading" id="gpa-cwv-loading" style="display:none;">
                <div class="gpa-loading-spinner" style="width:24px;height:24px;border-width:3px;"></div>
                <span>Fetching Core Web Vitals from Google…</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Group Tabs -->
        <div class="gpa-group-tabs" id="gpa-group-tabs">
            <button class="gpa-gtab gpa-gtab-active" data-group="all">All Checks</button>
            <button class="gpa-gtab" data-group="title">Title</button>
            <button class="gpa-gtab" data-group="meta">Meta</button>
            <button class="gpa-gtab" data-group="content">Content</button>
            <button class="gpa-gtab" data-group="headings">Headings</button>
            <button class="gpa-gtab" data-group="images">Images</button>
            <button class="gpa-gtab" data-group="links">Links</button>
            <button class="gpa-gtab" data-group="url">URL</button>
            <button class="gpa-gtab" data-group="social">Social</button>
        </div>

        <!-- Checks Grid -->
        <div class="gpa-checks-grid" id="gpa-checks-grid"></div>

    </div>

    <!-- ══ EMPTY STATE ══════════════════════════════════════════════════════ -->
    <div class="gpa-empty" id="gpa-empty">
        <div class="gpa-empty-icon">🔍</div>
        <div class="gpa-empty-title">Search for any page or post to analyze</div>
        <div class="gpa-empty-sub">Get a full SEO breakdown — title, meta, content, readability, links, schema and more.</div>
        <div class="gpa-empty-features">
            <span>✓ Focus keyword analysis</span>
            <span>✓ Readability score</span>
            <span>✓ Schema detection</span>
            <span>✓ Image alt audit</span>
            <span>✓ Internal link check</span>
            <span>✓ OG / Social tags</span>
        </div>
    </div>

    <!-- ══ LOADING STATE ════════════════════════════════════════════════════ -->
    <div class="gpa-loading" id="gpa-loading" style="display:none;">
        <div class="gpa-loading-spinner"></div>
        <div class="gpa-loading-text">Analyzing page…</div>
    </div>

</div><!-- /.gpa-wrap -->
