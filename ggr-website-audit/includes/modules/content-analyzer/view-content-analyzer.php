<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap ggrwa-ca-wrap" id="ggrwa-ca-root">

    <!-- Spinner overlay -->
    <div id="ggrwa-ca-overlay" class="sapc-overlay" style="display:none;">
        <div class="sapc-spinner-box">
            <div class="sapc-spinner"></div>
            <div class="sapc-spinner-msg">Analysing content…</div>
        </div>
    </div>

    <!-- Header -->
    <div class="sapc-header">
        <div class="sapc-header-brand">
            <div class="sapc-logo-box">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                    <rect width="22" height="22" rx="6" fill="#0f766e"/>
                    <path d="M6 7h10M6 11h7M6 15h9" stroke="white" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <div>
                <div class="sapc-plugin-name">Content Analyzer</div>
                <div class="sapc-plugin-version">Computed: <?php echo esc_html( $d['computed_at'] ?? 'never' ); ?></div>
            </div>
        </div>
        <div class="sapc-header-actions">
            <span class="sapc-live-badge"><span class="sapc-live-dot" style="background:#0f766e;"></span>Live</span>
            <button type="button" id="ggrwa-ca-refresh" class="sapc-btn-primary" style="background:#0f766e;">&#x21BB; Refresh Analysis</button>
        </div>
    </div>

    <!-- Top stats -->
    <div class="sapc-card sapc-stats-row" style="margin-bottom:16px;">
        <div class="ggrwa-ca-stat"><div class="sapc-stat-number"><?php echo (int)$d['total']; ?></div><div class="sapc-stat-label">Total Pages</div></div>
        <div class="ggrwa-ca-stat"><div class="sapc-stat-number"><?php echo (int)$d['avg_words']; ?></div><div class="sapc-stat-label">Avg Word Count</div></div>
        <div class="ggrwa-ca-stat"><div class="sapc-stat-number" style="color:<?php echo $d['avg_flesch'] >= 60 ? '#16a34a' : '#f59e0b'; ?>"><?php echo (int)$d['avg_flesch']; ?></div><div class="sapc-stat-label">Avg Flesch Score</div></div>
        <div class="ggrwa-ca-stat"><div class="sapc-stat-number" style="color:#dc2626;"><?php echo (int)$d['thin_count']; ?></div><div class="sapc-stat-label">Thin Pages (&lt;300w)</div></div>
        <div class="ggrwa-ca-stat"><div class="sapc-stat-number" style="color:#16a34a;"><?php echo (int)$d['good_count']; ?></div><div class="sapc-stat-label">Strong Pages (800w+)</div></div>
        <div class="ggrwa-ca-stat"><div class="sapc-stat-number" style="color:#f59e0b;"><?php echo (int)$d['no_alt_count']; ?></div><div class="sapc-stat-label">Missing Alt Text</div></div>
    </div>

    <!-- Grade dist + Word count histogram -->
    <div class="sapc-two-col" style="margin-bottom:16px;">

        <!-- Content Grade Distribution -->
        <div class="sapc-card">
            <div class="sapc-card-header">
                <span class="sapc-card-title">Content Grade Distribution</span>
                <span class="sapc-card-badge">All Pages</span>
            </div>
            <div class="ggrwa-ca-grade-bars">
                <?php
                $grade_colors = [ 'A' => '#16a34a', 'B' => '#3b82f6', 'C' => '#f59e0b', 'D' => '#dc2626' ];
                $total = max(1, (int)$d['total']);
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

        <!-- Word Count Distribution -->
        <div class="sapc-card">
            <div class="sapc-card-header">
                <span class="sapc-card-title">Word Count Distribution</span>
                <span class="sapc-card-badge">All Pages</span>
            </div>
            <div class="ggrwa-ca-grade-bars">
                <?php
                $hist_colors = [ '<300' => '#dc2626', '300–600' => '#f59e0b', '600–1000' => '#3b82f6', '1000–2000' => '#16a34a', '2000+' => '#059669' ];
                foreach ( $d['wc_hist'] as $label => $cnt ) :
                    $pct = round( $cnt / $total * 100 );
                ?>
                <div class="ggrwa-ca-grade-row">
                    <span class="ggrwa-ca-hist-label"><?php echo esc_html($label); ?></span>
                    <div class="sapc-bar-track" style="flex:1;">
                        <div class="sapc-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $hist_colors[$label]; ?>;"></div>
                    </div>
                    <span class="ggrwa-ca-grade-count"><?php echo $cnt; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Issue summary cards -->
    <div class="sapc-card" style="margin-bottom:16px;">
        <div class="sapc-card-header">
            <span class="sapc-card-title">Issues Overview</span>
            <span class="sapc-card-badge">Click to filter table below</span>
        </div>
        <div class="ggrwa-ca-issue-chips">
            <button class="ggrwa-ca-chip ggrwa-ca-chip-all ggrwa-ca-chip-active" data-filter="">All (<?php echo (int)$d['total']; ?>)</button>
            <button class="ggrwa-ca-chip ggrwa-ca-chip-red"  data-filter="thin">Thin Content (<?php echo (int)$d['thin_count']; ?>)</button>
            <button class="ggrwa-ca-chip ggrwa-ca-chip-orange" data-filter="no_h2">No H2 (<?php echo (int)$d['no_h2_count']; ?>)</button>
            <button class="ggrwa-ca-chip ggrwa-ca-chip-orange" data-filter="missing_alt">Missing Alt (<?php echo (int)$d['no_alt_count']; ?>)</button>
            <button class="ggrwa-ca-chip ggrwa-ca-chip-blue"  data-filter="no_int_links">No Internal Links (<?php echo count(array_filter($d['posts'], fn($r) => $r['int_links'] === 0)); ?>)</button>
            <button class="ggrwa-ca-chip ggrwa-ca-chip-orange" data-filter="no_keyword">No Keyword (<?php echo (int)$d['no_kw_count']; ?>)</button>
        </div>
    </div>

    <!-- Posts Table -->
    <div class="sapc-card">
        <div class="sapc-card-header">
            <span class="sapc-card-title">All Content</span>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="search" id="ggrwa-ca-search" placeholder="Search pages…" class="ggrwa-ca-search-input"/>
                <select id="ggrwa-ca-sort" class="ggrwa-ca-select">
                    <option value="word_count_desc">Words ↓</option>
                    <option value="word_count_asc">Words ↑</option>
                    <option value="flesch_desc">Readability ↓</option>
                    <option value="grade_asc">Grade A→D</option>
                    <option value="issues_desc">Most Issues</option>
                </select>
            </div>
        </div>

        <div class="ggrwa-ca-table-wrap">
            <table class="ggrwa-ca-table" id="ggrwa-ca-table">
                <thead>
                    <tr>
                        <th>Page / Post</th>
                        <th>Type</th>
                        <th>Words</th>
                        <th>Flesch</th>
                        <th>Grade</th>
                        <th>H1/H2/H3</th>
                        <th>Links</th>
                        <th>Images</th>
                        <th>Issues</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ggrwa-ca-tbody">
                <?php foreach ( $d['posts'] as $post ) :
                    $issue_badges = '';
                    $issue_labels = [
                        'thin'            => ['Thin','red'],
                        'no_h1'           => ['No H1','red'],
                        'no_h2'           => ['No H2','orange'],
                        'missing_alt'     => ['Alt','orange'],
                        'no_int_links'    => ['No Links','blue'],
                        'no_keyword'      => ['No KW','orange'],
                        'keyword_stuffing'=> ['KW Stuff','red'],
                    ];
                    foreach ( $post['issues'] as $iss ) {
                        if ( isset( $issue_labels[$iss] ) ) {
                            [$label,$color] = $issue_labels[$iss];
                            $issue_badges .= '<span class="ggrwa-ca-issue-badge ggrwa-ca-badge-'.$color.'">'.$label.'</span>';
                        }
                    }
                    $fk_color = $post['flesch'] >= 60 ? '#16a34a' : ( $post['flesch'] >= 40 ? '#f59e0b' : '#dc2626' );
                ?>
                <tr data-issues="<?php echo esc_attr( implode(',', $post['issues'] ) ); ?>">
                    <td class="ggrwa-ca-title-cell">
                        <strong><?php echo esc_html( wp_trim_words( $post['title'], 8 ) ); ?></strong>
                        <?php if ( $post['focus_kw'] ) : ?>
                        <small class="ggrwa-ca-kw-tag">kw: <?php echo esc_html( $post['focus_kw'] ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="sapc-kw-type-badge sapc-type-<?php echo esc_attr($post['type']); ?>"><?php echo esc_html($post['type']); ?></span></td>
                    <td data-val="<?php echo $post['word_count']; ?>"><?php echo number_format($post['word_count']); ?></td>
                    <td style="color:<?php echo $fk_color; ?>;font-weight:700;" data-val="<?php echo $post['flesch']; ?>"><?php echo $post['flesch']; ?></td>
                    <td><span class="sapc-grade sapc-grade-<?php echo strtolower($post['grade']); ?>" style="width:24px;height:24px;font-size:12px;"><?php echo $post['grade']; ?></span></td>
                    <td><?php echo $post['h1']; ?> / <?php echo $post['h2']; ?> / <?php echo $post['h3']; ?></td>
                    <td><?php echo $post['int_links']; ?> int · <?php echo $post['ext_links']; ?> ext</td>
                    <td><?php echo $post['imgs_total']; ?> <?php if($post['imgs_no_alt']>0): ?><span style="color:#dc2626;">(<?php echo $post['imgs_no_alt']; ?> no alt)</span><?php endif; ?></td>
                    <td><?php echo $issue_badges ?: '<span style="color:#16a34a;">✓</span>'; ?></td>
                    <td class="ggrwa-ca-actions">
                        <?php if ( $post['edit_url'] ) : ?><a href="<?php echo esc_url($post['edit_url']); ?>" class="sapc-mpost-btn" target="_blank">Edit</a><?php endif; ?>
                        <?php if ( $post['view_url'] ) : ?><a href="<?php echo esc_url($post['view_url']); ?>" class="sapc-mpost-btn sapc-mpost-view" target="_blank">View</a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ( empty( $d['posts'] ) ) : ?>
                <tr><td colspan="10" class="sapc-no-data">No published posts or pages found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ggrwa-ca-table-footer">
            Showing <strong id="ggrwa-ca-showing"><?php echo count($d['posts']); ?></strong> of <strong><?php echo (int)$d['total']; ?></strong> pages
            &nbsp;·&nbsp; Cache refreshes hourly or click <em>Refresh Analysis</em>
        </div>
    </div>

</div>
