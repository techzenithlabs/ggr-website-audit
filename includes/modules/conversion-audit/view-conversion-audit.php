<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap ggrwa-conv-wrap" id="ggrwa-conv-root">

    <!-- Overlay -->
    <div id="ggrwa-conv-overlay" class="sapc-overlay" style="display:none;">
        <div class="sapc-spinner-box">
            <div class="sapc-spinner"></div>
            <div class="sapc-spinner-msg">Analysing conversion signals…</div>
        </div>
    </div>

    <!-- Header -->
    <div class="sapc-header">
        <div class="sapc-header-brand">
            <div class="sapc-logo-box">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                    <rect width="22" height="22" rx="6" fill="#7c3aed"/>
                    <path d="M7 15l3-4 3 2 3-6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <div class="sapc-plugin-name">Conversion Audit</div>
                <div class="sapc-plugin-version">Computed: <?php echo esc_html( $d['computed_at'] ?? 'never' ); ?></div>
            </div>
        </div>
        <div class="sapc-header-actions">
            <span class="sapc-live-badge"><span class="sapc-live-dot" style="background:#7c3aed;"></span>Live</span>
            <button type="button" id="ggrwa-conv-refresh" class="sapc-btn-primary" style="background:#7c3aed;">&#x21BB; Refresh Analysis</button>
        </div>
    </div>

    <!-- Top Stat Cards -->
    <div class="sapc-card ggrwa-conv-stat-row">
        <div class="ggrwa-conv-stat">
            <div class="sapc-stat-number"><?php echo (int)$d['total']; ?></div>
            <div class="sapc-stat-label">Pages Analysed</div>
        </div>
        <div class="ggrwa-conv-stat">
            <?php $sc = $d['avg_score'] >= 70 ? '#16a34a' : ($d['avg_score'] >= 50 ? '#f59e0b' : '#dc2626'); ?>
            <div class="sapc-stat-number" style="color:<?php echo $sc; ?>"><?php echo (int)$d['avg_score']; ?></div>
            <div class="sapc-stat-label">Avg Conversion Score</div>
            <div class="sapc-stat-delta sapc-delta-neutral">/100</div>
        </div>
        <div class="ggrwa-conv-stat">
            <div class="sapc-stat-number" style="color:#16a34a;"><?php echo (int)$d['total_ctas']; ?></div>
            <div class="sapc-stat-label">Pages with CTA</div>
        </div>
        <div class="ggrwa-conv-stat">
            <div class="sapc-stat-number" style="color:#dc2626;"><?php echo (int)$d['no_cta_count']; ?></div>
            <div class="sapc-stat-label">Pages Missing CTA</div>
        </div>
        <div class="ggrwa-conv-stat">
            <div class="sapc-stat-number" style="color:#16a34a;"><?php echo (int)$d['trust_count']; ?></div>
            <div class="sapc-stat-label">Trust Signals Found</div>
        </div>
        <div class="ggrwa-conv-stat">
            <div class="sapc-stat-number" style="color:#7c3aed;"><?php echo (int)$d['form_count']; ?></div>
            <div class="sapc-stat-label">Forms Detected</div>
        </div>
    </div>

    <!-- Row 2: Grade dist + CTA breakdown -->
    <div class="sapc-two-col" style="margin-bottom:16px;">

        <!-- Conversion Grade Distribution -->
        <div class="sapc-card">
            <div class="sapc-card-header">
                <span class="sapc-card-title">Conversion Grade Distribution</span>
                <span class="sapc-card-badge">All Pages</span>
            </div>
            <div class="ggrwa-ca-grade-bars">
                <?php
                $grade_colors = [ 'A' => '#16a34a', 'B' => '#7c3aed', 'C' => '#f59e0b', 'D' => '#dc2626' ];
                $total = max( 1, (int)$d['total'] );
                foreach ( $d['grade_dist'] as $g => $cnt ) :
                    $pct = round( $cnt / $total * 100 );
                ?>
                <div class="ggrwa-ca-grade-row">
                    <span class="sapc-grade sapc-grade-<?php echo strtolower($g); ?>"><?php echo $g; ?></span>
                    <div class="sapc-bar-track" style="flex:1;">
                        <div class="sapc-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $grade_colors[$g]; ?>;"></div>
                    </div>
                    <span class="ggrwa-ca-grade-count"><?php echo $cnt; ?> <small>(<?php echo $pct; ?>%)</small></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CTA Type Breakdown -->
        <div class="sapc-card">
            <div class="sapc-card-header">
                <span class="sapc-card-title">CTA Type Breakdown</span>
                <span class="sapc-card-badge">Detected across all pages</span>
            </div>
            <div class="ggrwa-ca-grade-bars">
                <?php
                $cta_labels = [
                    'buy_now'     => 'Buy Now / Shop',
                    'get_started' => 'Get Started / Try Free',
                    'contact'     => 'Contact Us',
                    'learn_more'  => 'Learn More / Discover',
                    'download'    => 'Download / Get Guide',
                    'subscribe'   => 'Subscribe / Sign Up',
                    'book'        => 'Book a Call / Demo',
                ];
                $max_cta = max( 1, max( array_values( $d['cta_type_dist'] ) ) );
                foreach ( $d['cta_type_dist'] as $key => $cnt ) :
                    $pct = round( $cnt / $max_cta * 100 );
                    $label = $cta_labels[$key] ?? $key;
                ?>
                <div class="ggrwa-ca-grade-row">
                    <span class="ggrwa-ca-hist-label" style="min-width:140px;"><?php echo esc_html($label); ?></span>
                    <div class="sapc-bar-track" style="flex:1;">
                        <div class="sapc-bar-fill" style="width:<?php echo $pct; ?>%;background:#7c3aed;"></div>
                    </div>
                    <span class="ggrwa-ca-grade-count"><?php echo $cnt; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Issue Overview -->
    <div class="sapc-card" style="margin-bottom:16px;">
        <div class="sapc-card-header">
            <span class="sapc-card-title">Issues Overview</span>
            <span class="sapc-card-badge">Click to filter table</span>
        </div>
        <div class="ggrwa-ca-issue-chips">
            <button class="ggrwa-ca-chip ggrwa-ca-chip-all ggrwa-ca-chip-active" data-filter="">All (<?php echo (int)$d['total']; ?>)</button>
            <button class="ggrwa-ca-chip ggrwa-ca-chip-red"    data-filter="no_cta">No CTA (<?php echo (int)$d['issue_counts']['no_cta']; ?>)</button>
            <button class="ggrwa-ca-chip ggrwa-ca-chip-orange" data-filter="no_trust">No Trust Signals (<?php echo (int)$d['issue_counts']['no_trust']; ?>)</button>
            <button class="ggrwa-ca-chip ggrwa-ca-chip-blue"   data-filter="no_form">No Form (<?php echo (int)$d['issue_counts']['no_form']; ?>)</button>
            <button class="ggrwa-ca-chip ggrwa-ca-chip-orange" data-filter="weak_atf">Weak Above-fold (<?php echo (int)$d['issue_counts']['weak_atf']; ?>)</button>
            <button class="ggrwa-ca-chip ggrwa-ca-chip-red"    data-filter="heavy_page">Heavy Page (<?php echo (int)$d['issue_counts']['heavy_page']; ?>)</button>
        </div>
    </div>

    <?php if ( $d['woo_active'] && ! empty($d['woo_stats']) ) : $ws = $d['woo_stats']; ?>
    <!-- WooCommerce Panel -->
    <div class="sapc-card" style="margin-bottom:16px;">
        <div class="sapc-card-header">
            <span class="sapc-card-title">&#x1F6D2; WooCommerce Product Health</span>
            <span class="sapc-card-badge"><?php echo (int)$ws['total']; ?> products</span>
        </div>
        <div class="ggrwa-conv-woo-grid">
            <div class="ggrwa-conv-woo-item ggrwa-conv-woo-<?php echo $ws['no_desc'] > 0 ? 'bad' : 'good'; ?>">
                <div class="ggrwa-conv-woo-num"><?php echo (int)$ws['no_desc']; ?></div>
                <div class="ggrwa-conv-woo-label">Missing Description</div>
            </div>
            <div class="ggrwa-conv-woo-item ggrwa-conv-woo-<?php echo $ws['no_img'] > 0 ? 'bad' : 'good'; ?>">
                <div class="ggrwa-conv-woo-num"><?php echo (int)$ws['no_img']; ?></div>
                <div class="ggrwa-conv-woo-label">Missing Product Image</div>
            </div>
            <div class="ggrwa-conv-woo-item ggrwa-conv-woo-<?php echo $ws['no_price'] > 0 ? 'bad' : 'good'; ?>">
                <div class="ggrwa-conv-woo-num"><?php echo (int)$ws['no_price']; ?></div>
                <div class="ggrwa-conv-woo-label">Missing Price</div>
            </div>
            <div class="ggrwa-conv-woo-item ggrwa-conv-woo-<?php echo $ws['no_short'] > 0 ? 'warn' : 'good'; ?>">
                <div class="ggrwa-conv-woo-num"><?php echo (int)$ws['no_short']; ?></div>
                <div class="ggrwa-conv-woo-label">Missing Short Desc.</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pages Table -->
    <div class="sapc-card">
        <div class="sapc-card-header">
            <span class="sapc-card-title">All Pages — Conversion Analysis</span>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="search" id="ggrwa-conv-search" placeholder="Search pages…" class="ggrwa-ca-search-input"/>
                <select id="ggrwa-conv-sort" class="ggrwa-ca-select">
                    <option value="score_desc">Score ↓</option>
                    <option value="score_asc">Score ↑</option>
                    <option value="issues_desc">Most Issues</option>
                    <option value="grade_asc">Grade A→D</option>
                </select>
            </div>
        </div>

        <div class="ggrwa-ca-table-wrap">
            <table class="ggrwa-ca-table" id="ggrwa-conv-table">
                <thead>
                    <tr>
                        <th>Page / Post</th>
                        <th>Type</th>
                        <th>Score</th>
                        <th>Grade</th>
                        <th>CTA Found</th>
                        <th>Trust Signals</th>
                        <th>Forms</th>
                        <th>ATF Words</th>
                        <th>Issues</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ggrwa-conv-tbody">
                <?php foreach ( $d['posts'] as $post ) :
                    $score_color = $post['score'] >= 70 ? '#16a34a' : ($post['score'] >= 50 ? '#f59e0b' : '#dc2626');
                    $issue_badges = '';
                    $issue_labels = [
                        'no_cta'     => ['No CTA',      'red'],
                        'no_trust'   => ['No Trust',    'orange'],
                        'no_form'    => ['No Form',     'blue'],
                        'weak_atf'   => ['Weak ATF',    'orange'],
                        'heavy_page' => ['Heavy Page',  'red'],
                    ];
                    foreach ( $post['issues'] as $iss ) {
                        if ( isset($issue_labels[$iss]) ) {
                            [$label,$col] = $issue_labels[$iss];
                            $issue_badges .= '<span class="ggrwa-ca-issue-badge ggrwa-ca-badge-'.$col.'">'.$label.'</span>';
                        }
                    }
                    $cta_html  = $post['has_cta']
                        ? '<span class="ggrwa-conv-yes">&#x2713; ' . esc_html(implode(', ', array_map(fn($c)=>str_replace('_',' ',$c), $post['ctas']))) . '</span>'
                        : '<span class="ggrwa-conv-no">&#x2717; None</span>';
                    $trust_html = ! empty($post['trust_signals'])
                        ? '<span class="ggrwa-conv-yes">&#x2713; ' . esc_html(implode(', ', $post['trust_signals'])) . '</span>'
                        : '<span class="ggrwa-conv-no">&#x2717; None</span>';
                    $form_html  = ! empty($post['forms'])
                        ? '<span class="ggrwa-conv-yes">&#x2713;</span>'
                        : '<span class="ggrwa-conv-no">&#x2717;</span>';
                ?>
                <tr data-issues="<?php echo esc_attr( implode(',', $post['issues']) ); ?>"
                    data-score="<?php echo esc_attr($post['score']); ?>">
                    <td class="ggrwa-ca-title-cell">
                        <strong><?php echo esc_html( wp_trim_words($post['title'], 8) ); ?></strong>
                    </td>
                    <td><span class="sapc-kw-type-badge sapc-type-<?php echo esc_attr($post['type']); ?>"><?php echo esc_html($post['type']); ?></span></td>
                    <td style="font-weight:700;color:<?php echo $score_color; ?>;"><?php echo (int)$post['score']; ?></td>
                    <td><span class="sapc-grade sapc-grade-<?php echo strtolower($post['grade']); ?>" style="width:24px;height:24px;font-size:12px;"><?php echo $post['grade']; ?></span></td>
                    <td><?php echo $cta_html; ?></td>
                    <td><?php echo $trust_html; ?></td>
                    <td><?php echo $form_html; ?></td>
                    <td><?php echo (int)$post['atf_words']; ?>w</td>
                    <td><?php echo $issue_badges ?: '<span style="color:#16a34a;">&#x2713; Good</span>'; ?></td>
                    <td class="ggrwa-ca-actions">
                        <?php if ($post['edit_url']): ?><a href="<?php echo esc_url($post['edit_url']); ?>" class="sapc-mpost-btn" target="_blank">Edit</a><?php endif; ?>
                        <?php if ($post['view_url']): ?><a href="<?php echo esc_url($post['view_url']); ?>" class="sapc-mpost-btn sapc-mpost-view" target="_blank">View</a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ( empty($d['posts']) ) : ?>
                <tr><td colspan="10" class="sapc-no-data">No published pages found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ggrwa-ca-table-footer">
            Showing <strong id="ggrwa-conv-showing"><?php echo count($d['posts']); ?></strong>
            of <strong><?php echo (int)$d['total']; ?></strong> pages &nbsp;·&nbsp;
            Data cached hourly — click <em>Refresh Analysis</em> to update now.
        </div>
    </div>

</div>
