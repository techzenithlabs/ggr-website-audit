<?php
/**
 * SEO Audit Pro Dashboard View — fully dynamic
 *
 * All data comes from GGRWA_SEO_Data_Aggregator::get().
 * No static / demo values are used.
 *
 * @package GGR_Website_Audit
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Pull live data (from transient cache or freshly computed).
$d = GGRWA_SEO_Data_Aggregator::get();

/* ── Convenience variables ─────────────────────────────────────────────── */
$overall_score   = (int) ( $d['overall_score']   ?? 0 );
$score_label     = esc_html( $d['score_label']   ?? 'Run an audit to get your score' );
$score_class     = esc_attr( $d['score_class']   ?? 'warning' );
$last_scan_label = esc_html( $d['last_scan_label'] ?? 'Never' );

$posts_audited   = (int) ( $d['posts_audited']   ?? 0 );
$posts_new       = (int) ( $d['posts_new']       ?? 0 );
$critical_issues = (int) ( $d['critical_issues'] ?? 0 );
$critical_new    = (int) ( $d['critical_new']    ?? 0 );
$indexed_pages   = (int) ( $d['indexed_pages']   ?? 0 );
$indexed_pct     = (int) ( $d['indexed_pct']     ?? 0 );
$avg_readability = (int) ( $d['avg_readability'] ?? 0 );

$issues       = $d['issues']       ?? [];
$keyword_posts= $d['keyword_posts']?? [];
$readability  = $d['readability']  ?? [];
$quick_wins   = $d['quick_wins']   ?? [];
$schema_types = $d['schema_types'] ?? [];
$broken_links = $d['broken_links'] ?? [];

$active_redirects = (int) ( $d['active_redirects'] ?? 0 );
$pending_fixes    = (int) ( $d['pending_fixes']    ?? 0 );

$sitemap_posts  = (int) ( $d['sitemap_posts']  ?? 0 );
$sitemap_pages  = (int) ( $d['sitemap_pages']  ?? 0 );
$sitemap_images = (int) ( $d['sitemap_images'] ?? 0 );
$og_title_posts = (int) ( $d['og_title_posts'] ?? 0 );
$og_img_missing = (int) ( $d['og_img_missing'] ?? 0 );
$twitter_enabled= ! empty( $d['twitter_enabled'] );

/* ── Ring SVG ──────────────────────────────────────────────────────────── */
$ring_c      = 326.73;
$ring_offset = $ring_c - ( min( 100, $overall_score ) / 100 * $ring_c );
$ring_color  = $overall_score >= 80 ? '#16a34a' : ( $overall_score >= 60 ? '#1e3a5f' : '#dc2626' );
?>

<div class="wrap sapc-wrap" id="sapc-dashboard">

    <!-- ══ SPINNER OVERLAY (shown during audit) ════════════════════════════ -->
    <div class="sapc-overlay" id="sapc-overlay" style="display:none;">
        <div class="sapc-spinner-box">
            <div class="sapc-spinner"></div>
            <div class="sapc-spinner-msg" id="sapc-spinner-msg">Running site-wide audit…</div>
        </div>
    </div>

    <!-- ══ HEADER ══════════════════════════════════════════════════════════ -->
    <div class="sapc-header">
        <div class="sapc-header-brand">
            <div class="sapc-logo-box">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                    <rect width="22" height="22" rx="6" fill="#1e3a5f"/>
                    <path d="M5 16L9 8L13 12L16 6" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <div class="sapc-plugin-name">SEO Audit Pro</div>
                <div class="sapc-plugin-version">v1.0 &mdash; WordPress Plugin</div>
            </div>
        </div>
        <div class="sapc-header-actions">
            <span class="sapc-live-badge"><span class="sapc-live-dot"></span>Live</span>
            <span class="sapc-last-scan" id="sapc-last-scan">
                Last scan: <span id="sapc-last-scan-val"><?php echo esc_html( $last_scan_label ); ?></span>
            </span>
            <button type="button" id="sapc-run-audit-btn" class="sapc-btn-primary">Run Full Audit</button>
        </div>
    </div>

    <!-- ══ TOP STATS ═══════════════════════════════════════════════════════ -->
    <div class="sapc-card sapc-stats-row">
        <!-- Ring -->
        <div class="sapc-score-ring-wrap">
            <svg class="sapc-ring-svg" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="52" fill="none" stroke="#e5e7eb" stroke-width="10"/>
                <circle cx="60" cy="60" r="52" fill="none"
                    stroke="<?php echo esc_attr( $ring_color ); ?>"
                    stroke-width="10" stroke-linecap="round"
                    stroke-dasharray="<?php echo esc_attr( $ring_c ); ?>"
                    stroke-dashoffset="<?php echo esc_attr( $ring_offset ); ?>"
                    transform="rotate(-90 60 60)"
                    id="sapc-ring-circle"
                    data-score="<?php echo esc_attr( $overall_score ); ?>"
                    data-circ="<?php echo esc_attr( $ring_c ); ?>"
                    data-color="<?php echo esc_attr( $ring_color ); ?>"/>
                <text x="60" y="54" text-anchor="middle" class="sapc-ring-score-text" id="sapc-ring-val"><?php echo esc_html( $overall_score ); ?></text>
                <text x="60" y="70" text-anchor="middle" class="sapc-ring-denom-text">/100</text>
            </svg>
            <div class="sapc-score-label">Overall SEO Health</div>
            <div class="sapc-score-sublabel sapc-<?php echo $score_class; ?>" id="sapc-score-sublabel"><?php echo $score_label; ?></div>
            <?php if ( $overall_score === 0 ) : ?>
            <div class="sapc-score-hint">Audit posts to get your score</div>
            <?php endif; ?>
        </div>

        <!-- Stat cards -->
        <div class="sapc-stats-grid">
            <div class="sapc-stat-card">
                <div class="sapc-stat-number" id="sapc-stat-audited"><?php echo number_format( $posts_audited ); ?></div>
                <div class="sapc-stat-label">Posts Audited</div>
                <div class="sapc-stat-delta sapc-delta-up">&#x2191; <?php echo esc_html( $posts_new ); ?> new</div>
            </div>
            <div class="sapc-stat-card">
                <div class="sapc-stat-number sapc-number-red" id="sapc-stat-critical"><?php echo esc_html( $critical_issues ); ?></div>
                <div class="sapc-stat-label">Critical Issues</div>
                <div class="sapc-stat-delta sapc-delta-down">&#x2191; <?php echo esc_html( $critical_new ); ?> since last scan</div>
            </div>
            <div class="sapc-stat-card">
                <div class="sapc-stat-number" id="sapc-stat-indexed"><?php echo number_format( $indexed_pages ); ?></div>
                <div class="sapc-stat-label">Indexed Pages</div>
                <div class="sapc-stat-delta sapc-delta-neutral" id="sapc-stat-indexed-pct"><?php echo esc_html( $indexed_pct ); ?>% audited</div>
            </div>
            <div class="sapc-stat-card">
                <div class="sapc-stat-number sapc-number-orange" id="sapc-stat-readability"><?php echo esc_html( $avg_readability ); ?></div>
                <div class="sapc-stat-label">Avg Readability</div>
                <div class="sapc-stat-delta sapc-delta-neutral">Flesch-Kincaid</div>
            </div>
        </div>
    </div>

    <!-- ══ ROW 2: Issues + Keyword Performance ═════════════════════════════ -->
    <div class="sapc-two-col">

        <!-- Issues by Severity -->
        <div class="sapc-card">
            <div class="sapc-card-header">
                <span class="sapc-card-title">Issues by Severity</span>
                <span class="sapc-card-badge">Rank Math + Yoast</span>
            </div>
            <ul class="sapc-issues-list" id="sapc-issues-list">
                <?php foreach ( $issues as $issue ) : ?>
                <li class="sapc-issue-row sapc-issue-clickable"
                    data-issue="<?php echo esc_attr( $issue['key'] ?? '' ); ?>"
                    title="Click to see affected pages &amp; fix guide">
                    <span class="sapc-dot sapc-dot-<?php echo esc_attr( $issue['severity'] ); ?>"></span>
                    <span class="sapc-issue-label"><?php echo esc_html( $issue['label'] ); ?></span>
                    <span class="sapc-issue-count sapc-count-<?php echo esc_attr( $issue['severity'] ); ?>"><?php echo esc_html( $issue['count'] ); ?></span>
                    <?php if ( ! empty( $issue['key'] ) && (int)$issue['count'] > 0 ) : ?>
                    <span class="sapc-issue-arrow">&#x276F;</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
                <?php if ( empty( $issues ) ) : ?>
                <li class="sapc-no-data">Run a full audit to populate issue counts.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Keyword Performance -->
        <div class="sapc-card">
            <div class="sapc-card-header">
                <span class="sapc-card-title">Keyword Performance</span>
                <span class="sapc-card-badge">Yoast-style</span>
            </div>
            <div class="sapc-tabs">
                <button class="sapc-tab sapc-tab-active" data-tab="all">All Posts</button>
                <button class="sapc-tab" data-tab="pages">Pages</button>
                <button class="sapc-tab" data-tab="products">Products</button>
            </div>
            <ul class="sapc-keyword-list" id="sapc-keyword-list">
                <?php if ( ! empty( $keyword_posts ) ) : ?>
                <?php foreach ( $keyword_posts as $kw ) :
                    $g   = strtolower( $kw['grade'] );
                    $sc  = $kw['score'] >= 70 ? 'sapc-kscore-green' : ( $kw['score'] >= 50 ? 'sapc-kscore-orange' : 'sapc-kscore-red' );
                    $tab = esc_attr( $kw['tab'] ?? 'all' );
                    $pt  = esc_attr( $kw['post_type'] ?? 'post' );
                ?>
                <li class="sapc-keyword-row" data-tab="<?php echo $tab; ?>" data-posttype="<?php echo $pt; ?>">
                    <span class="sapc-grade sapc-grade-<?php echo esc_attr( $g ); ?>"><?php echo esc_html( $kw['grade'] ); ?></span>
                    <span class="sapc-kw-info">
                        <?php if ( ! empty( $kw['edit_url'] ) ) : ?>
                        <a href="<?php echo esc_url( $kw['edit_url'] ); ?>" class="sapc-kw-title" target="_blank"><?php echo esc_html( $kw['title'] ); ?></a>
                        <?php else : ?>
                        <span class="sapc-kw-title"><?php echo esc_html( $kw['title'] ); ?></span>
                        <?php endif; ?>
                        <span class="sapc-kw-meta">
                            <?php echo esc_html( $kw['keyword'] ); ?>
                            <span class="sapc-kw-type-badge sapc-type-<?php echo $pt; ?>"><?php echo esc_html( $kw['post_type'] ?? 'post' ); ?></span>
                        </span>
                    </span>
                    <span class="sapc-kscore <?php echo esc_attr( $sc ); ?>"><?php echo esc_html( $kw['score'] ); ?></span>
                </li>
                <?php endforeach; ?>
                <?php else : ?>
                <li class="sapc-no-data">Audit your posts to see keyword performance scores.</li>
                <?php endif; ?>
            </ul>
        </div>

    </div>

    <!-- ══ ROW 3: Readability + Quick Wins ═════════════════════════════════ -->
    <div class="sapc-two-col">

        <!-- Readability Breakdown -->
        <div class="sapc-card">
            <div class="sapc-card-header">
                <span class="sapc-card-title">Readability Breakdown</span>
                <span class="sapc-card-badge">Yoast-style</span>
            </div>
            <ul class="sapc-readability-list" id="sapc-readability-list">
                <?php foreach ( $readability as $r ) :
                    $val = is_numeric( $r['value'] ) ? $r['value'] . $r['unit'] : $r['value'];
                ?>
                <li class="sapc-read-row">
                    <span class="sapc-read-metric"><?php echo esc_html( $r['metric'] ); ?></span>
                    <div class="sapc-bar-track">
                        <div class="sapc-bar-fill sapc-bar-<?php echo esc_attr( $r['status'] ); ?>"
                             style="width:<?php echo esc_attr( min( 100, (int)$r['pct'] ) ); ?>%"></div>
                    </div>
                    <span class="sapc-read-value">
                        <?php echo esc_html( $val ); ?>
                        <?php if ( $r['status'] === 'bad' ) : ?>
                        <span class="sapc-read-low">Low</span>
                        <?php endif; ?>
                    </span>
                </li>
                <?php endforeach; ?>
                <?php if ( empty( $readability ) ) : ?>
                <li class="sapc-no-data">No readability data yet — run a full audit.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Quick Wins -->
        <div class="sapc-card">
            <div class="sapc-card-header">
                <span class="sapc-card-title">Quick Wins</span>
                <span class="sapc-card-badge">Unique feature</span>
            </div>
            <ul class="sapc-quickwins-list" id="sapc-quickwins-list">
                <?php foreach ( $quick_wins as $win ) : ?>
                <li class="sapc-win-row">
                    <span class="sapc-win-icon sapc-win-<?php echo esc_attr( $win['type'] ); ?>"><?php echo esc_html( $win['icon'] ); ?></span>
                    <span class="sapc-win-body">
                        <span class="sapc-win-title"><?php echo esc_html( $win['title'] ); ?></span>
                        <span class="sapc-win-desc"><?php echo esc_html( $win['desc'] ); ?></span>
                    </span>
                </li>
                <?php endforeach; ?>
                <?php if ( empty( $quick_wins ) ) : ?>
                <li class="sapc-no-data">Run a full audit to see recommended quick wins.</li>
                <?php endif; ?>
            </ul>
        </div>

    </div>

    <!-- ══ ROW 4: Schema + 404 Monitor + Sitemap ═══════════════════════════ -->
    <div class="sapc-three-col">

        <!-- Schema Status -->
        <div class="sapc-card">
            <div class="sapc-card-header">
                <span class="sapc-card-title">Schema Status</span>
                <span class="sapc-card-badge">Rank Math-style</span>
            </div>
            <div class="sapc-schema-grid" id="sapc-schema-grid">
                <?php foreach ( $schema_types as $s ) : ?>
                <div class="sapc-schema-item">
                    <span class="sapc-schema-name"><?php echo esc_html( $s['type'] ); ?></span>
                    <span class="sapc-dot sapc-dot-<?php echo esc_attr( $s['status'] ); ?>"></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 404 Monitor -->
        <div class="sapc-card">
            <div class="sapc-card-header">
                <span class="sapc-card-title">404 Monitor</span>
                <span class="sapc-card-badge">Rank Math-style</span>
            </div>
            <ul class="sapc-monitor-list" id="sapc-monitor-list">
                <?php if ( ! empty( $broken_links ) ) : ?>
                <?php foreach ( $broken_links as $b ) : ?>
                <li class="sapc-monitor-row">
                    <div class="sapc-monitor-label">
                        <strong><?php echo esc_html( $b['label'] ); ?></strong>
                        <?php if ( $b['hits'] > 0 ) : ?>
                        <span class="sapc-monitor-hits-inline"><?php echo esc_html( $b['hits'] ); ?> hits</span>
                        <?php endif; ?>
                    </div>
                    <div class="sapc-monitor-url"><code><?php echo esc_html( $b['url'] ); ?></code></div>
                </li>
                <?php endforeach; ?>
                <?php else : ?>
                <li class="sapc-no-data">No deleted/trashed posts found. Install Rank Math for full 404 tracking.</li>
                <?php endif; ?>
            </ul>
            <div class="sapc-monitor-footer">
                <div class="sapc-monitor-stat">
                    <span>Active redirections</span>
                    <strong id="sapc-redirects"><?php echo esc_html( $active_redirects ); ?></strong>
                </div>
                <div class="sapc-monitor-stat">
                    <span>Pending <span class="sapc-pending-arrow">&#x2193;</span></span>
                    <strong id="sapc-pending"><?php echo esc_html( $pending_fixes ); ?></strong>
                </div>
            </div>
        </div>

        <!-- Sitemap & Social -->
        <div class="sapc-card">
            <div class="sapc-card-header">
                <span class="sapc-card-title">Sitemap &amp; Social</span>
                <span class="sapc-card-badge">Both</span>
            </div>
            <div class="sapc-sitemap-section-label">Sitemap</div>
            <ul class="sapc-sitemap-list">
                <li><span>Posts indexed</span><strong id="sapc-sm-posts"><?php echo number_format( $sitemap_posts ); ?></strong></li>
                <li><span>Pages indexed</span><strong id="sapc-sm-pages"><?php echo number_format( $sitemap_pages ); ?></strong></li>
                <li><span>Images indexed</span><strong id="sapc-sm-images"><?php echo number_format( $sitemap_images ); ?></strong></li>
            </ul>
            <div class="sapc-sitemap-divider"></div>
            <div class="sapc-sitemap-section-label">Open Graph / Social</div>
            <ul class="sapc-sitemap-list">
                <li>
                    <span>OG Title set</span>
                    <span class="sapc-og-ok" id="sapc-og-title">&#x2713; <?php echo number_format( $og_title_posts ); ?> posts</span>
                </li>
                <li>
                    <span>OG Image set</span>
                    <?php if ( $og_img_missing > 0 ) : ?>
                    <span class="sapc-og-miss" id="sapc-og-img">&#x2717; <?php echo esc_html( $og_img_missing ); ?> missing</span>
                    <?php else : ?>
                    <span class="sapc-og-ok" id="sapc-og-img">&#x2713; All set</span>
                    <?php endif; ?>
                </li>
                <li>
                    <span>Twitter Card</span>
                    <?php if ( $twitter_enabled ) : ?>
                    <span class="sapc-og-ok">&#x2713; enabled</span>
                    <?php else : ?>
                    <span class="sapc-og-miss">&#x2717; not detected</span>
                    <?php endif; ?>
                </li>
            </ul>
        </div>

    </div><!-- /.sapc-three-col -->

    <!-- ══ LAST UPDATED FOOTER ═════════════════════════════════════════════ -->
    <div class="sapc-footer-note">
        Data computed: <?php echo esc_html( $d['computed_at'] ?? 'never' ); ?> &nbsp;·&nbsp;
        Cache refreshes automatically every hour, or click <em>Run Full Audit</em> to refresh now.
    </div>

<!-- ══ ISSUE DETAIL MODAL ══════════════════════════════════════════════════ -->
<div id="sapc-issue-modal" class="sapc-modal-backdrop" style="display:none;">
    <div class="sapc-modal">
        <button class="sapc-modal-close" id="sapc-modal-close" aria-label="Close">&times;</button>
        <div class="sapc-modal-header">
            <div class="sapc-modal-icon" id="sapc-modal-icon">!</div>
            <h2 class="sapc-modal-title" id="sapc-modal-title">Loading…</h2>
        </div>

        <!-- Fix Guide -->
        <div class="sapc-modal-section">
            <div class="sapc-modal-section-label">&#x1F527; How to Fix</div>
            <ol class="sapc-fix-steps" id="sapc-fix-steps"></ol>
            <a href="#" class="sapc-docs-link" id="sapc-docs-link" target="_blank" rel="noopener" style="display:none;">
                &#x1F4D6; Read the full guide &rarr;
            </a>
        </div>

        <!-- Affected Posts -->
        <div class="sapc-modal-section">
            <div class="sapc-modal-section-label">&#x1F4CB; Affected Pages (<span id="sapc-modal-count">0</span>)</div>
            <div id="sapc-modal-posts-wrap">
                <ul class="sapc-modal-posts" id="sapc-modal-posts"></ul>
            </div>
        </div>

        <div class="sapc-modal-footer">
            <button class="sapc-btn-primary" id="sapc-modal-close-btn">Done</button>
        </div>
    </div>
</div>

</div><!-- /.sapc-wrap -->
