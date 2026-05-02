/**
 * SEO Audit Pro — Dashboard JS
 *
 * Features:
 *  - Ring animation on load
 *  - Keyword tab filtering (All Posts / Pages / Products)
 *  - Clickable issues → modal with affected posts + fix guide
 *  - Run Full Audit AJAX refresh
 *
 * @package GGR_Website_Audit
 * @since   2.5.0
 */
(function ($) {
    'use strict';

    var AJAX_URL = (window.ggrwa_seo_dashboard || {}).ajax_url || '';
    var NONCE    = (window.ggrwa_seo_dashboard || {}).nonce    || '';

    /* ── All keyword rows (stored for tab filtering) ─────────────────────── */
    var _allKeywordRows = [];

    /* ════════════════════════════════════════════════════════════════════════
       OVERLAY
    ════════════════════════════════════════════════════════════════════════ */
    function showOverlay(msg) {
        $('#sapc-spinner-msg').text(msg || 'Running…');
        $('#sapc-overlay').fadeIn(200);
    }
    function hideOverlay() { $('#sapc-overlay').fadeOut(200); }

    /* ════════════════════════════════════════════════════════════════════════
       RING ANIMATION
    ════════════════════════════════════════════════════════════════════════ */
    function animateRing(score, circ, color) {
        var circle = $('#sapc-ring-circle');
        if (!circle.length) return;

        var targetOffset = circ - (Math.min(100, score) / 100 * circ);
        circle.attr('stroke', color || '#1e3a5f').css('stroke-dashoffset', circ);

        $({ val: circ }).animate({ val: targetOffset }, {
            duration: 900, easing: 'swing',
            step: function () { circle.css('stroke-dashoffset', this.val); }
        });
        $({ n: 0 }).animate({ n: score }, {
            duration: 900,
            step: function () { $('#sapc-ring-val').text(Math.round(this.n)); }
        });
    }

    /* ════════════════════════════════════════════════════════════════════════
       ISSUES LIST  (clickable rows)
    ════════════════════════════════════════════════════════════════════════ */
    function renderIssues(issues) {
        var $ul = $('#sapc-issues-list').empty();
        if (!issues || !issues.length) {
            $ul.append('<li class="sapc-no-data">No issue data yet.</li>');
            return;
        }
        $.each(issues, function (i, issue) {
            var hasDetail = issue.key && parseInt(issue.count, 10) > 0;
            var arrow     = hasDetail ? '<span class="sapc-issue-arrow">&#x276F;</span>' : '';
            var cls       = 'sapc-issue-row' + (hasDetail ? ' sapc-issue-clickable' : '');
            $ul.append(
                '<li class="' + cls + '" data-issue="' + escHtml(issue.key || '') + '" ' +
                (hasDetail ? 'title="Click to see affected pages &amp; fix guide"' : '') + '>' +
                '<span class="sapc-dot sapc-dot-' + issue.severity + '"></span>' +
                '<span class="sapc-issue-label">' + escHtml(issue.label) + '</span>' +
                '<span class="sapc-issue-count sapc-count-' + issue.severity + '">' + issue.count + '</span>' +
                arrow + '</li>'
            );
        });
    }

    /* ════════════════════════════════════════════════════════════════════════
       KEYWORD PERFORMANCE  (with tab filtering)
    ════════════════════════════════════════════════════════════════════════ */
    function renderKeywords(posts, activeTab) {
        _allKeywordRows = posts || [];
        filterKeywords(activeTab || 'all');
    }

    function filterKeywords(tab) {
        var $ul = $('#sapc-keyword-list').empty();
        var rows = tab === 'all'
            ? _allKeywordRows
            : _allKeywordRows.filter(function (kw) { return kw.tab === tab; });

        if (!rows.length) {
            var msg = tab === 'all'
                ? 'No audited posts yet. Run a full audit first.'
                : 'No ' + tab + ' found with SEO data.';
            $ul.append('<li class="sapc-no-data">' + msg + '</li>');
            return;
        }

        $.each(rows, function (i, kw) {
            var g     = (kw.grade || 'D').toLowerCase();
            var score = parseInt(kw.score, 10) || 0;
            var sc    = score >= 70 ? 'sapc-kscore-green' : (score >= 50 ? 'sapc-kscore-orange' : 'sapc-kscore-red');
            var title = kw.edit_url
                ? '<a href="' + escHtml(kw.edit_url) + '" class="sapc-kw-title" target="_blank">' + escHtml(kw.title) + '</a>'
                : '<span class="sapc-kw-title">' + escHtml(kw.title) + '</span>';
            var badge = '<span class="sapc-kw-type-badge sapc-type-' + escHtml(kw.post_type || 'post') + '">'
                      + escHtml(kw.post_type || 'post') + '</span>';

            $ul.append(
                '<li class="sapc-keyword-row">' +
                '<span class="sapc-grade sapc-grade-' + g + '">' + escHtml(kw.grade) + '</span>' +
                '<span class="sapc-kw-info">' + title +
                '<span class="sapc-kw-meta">' + escHtml(kw.keyword) + badge + '</span>' +
                '</span>' +
                '<span class="sapc-kscore ' + sc + '">' + score + '</span>' +
                '</li>'
            );
        });
    }

    /* ════════════════════════════════════════════════════════════════════════
       OTHER PANELS
    ════════════════════════════════════════════════════════════════════════ */
    function renderReadability(rows) {
        var $ul = $('#sapc-readability-list').empty();
        if (!rows || !rows.length) {
            $ul.append('<li class="sapc-no-data">No readability data yet.</li>');
            return;
        }
        $.each(rows, function (i, r) {
            var val = (r.value !== undefined ? r.value : 'N/A') + (r.unit || '');
            var pct = Math.min(100, parseInt(r.pct, 10) || 0);
            var low = r.status === 'bad' ? '<span class="sapc-read-low">Low</span>' : '';
            $ul.append(
                '<li class="sapc-read-row">' +
                '<span class="sapc-read-metric">' + escHtml(r.metric) + '</span>' +
                '<div class="sapc-bar-track"><div class="sapc-bar-fill sapc-bar-' + r.status + '" style="width:0%"></div></div>' +
                '<span class="sapc-read-value">' + escHtml(String(val)) + low + '</span>' +
                '</li>'
            );
            $ul.find('.sapc-bar-fill').last().animate({ width: pct + '%' }, 600);
        });
    }

    function renderQuickWins(wins) {
        var $ul = $('#sapc-quickwins-list').empty();
        if (!wins || !wins.length) { $ul.append('<li class="sapc-no-data">No quick wins yet.</li>'); return; }
        $.each(wins, function (i, w) {
            $ul.append(
                '<li class="sapc-win-row">' +
                '<span class="sapc-win-icon sapc-win-' + w.type + '">' + escHtml(w.icon) + '</span>' +
                '<span class="sapc-win-body">' +
                '<span class="sapc-win-title">' + escHtml(w.title) + '</span>' +
                '<span class="sapc-win-desc">'  + escHtml(w.desc)  + '</span>' +
                '</span></li>'
            );
        });
    }

    function renderSchema(types) {
        var $g = $('#sapc-schema-grid').empty();
        if (!types || !types.length) return;
        $.each(types, function (i, s) {
            $g.append('<div class="sapc-schema-item"><span class="sapc-schema-name">' + escHtml(s.type) + '</span><span class="sapc-dot sapc-dot-' + s.status + '"></span></div>');
        });
    }

    function renderMonitor(rows, redirects, pending) {
        var $ul = $('#sapc-monitor-list').empty();
        if (rows && rows.length) {
            $.each(rows, function (i, b) {
                var hits  = parseInt(b.hits, 10);
                var badge = hits > 0 ? '<span class="sapc-monitor-hits-inline">' + hits + ' hits</span>' : '';
                $ul.append('<li class="sapc-monitor-row"><div class="sapc-monitor-label"><strong>' + escHtml(b.label) + '</strong>' + badge + '</div><div class="sapc-monitor-url"><code>' + escHtml(b.url) + '</code></div></li>');
            });
        } else {
            $ul.append('<li class="sapc-no-data">No deleted/trashed posts found.</li>');
        }
        $('#sapc-redirects').text(redirects || 0);
        $('#sapc-pending').text(pending || 0);
    }

    /* ════════════════════════════════════════════════════════════════════════
       ISSUE DETAIL MODAL
    ════════════════════════════════════════════════════════════════════════ */
    var severityIcon = { critical: '!', warning: '⚠', good: '✓' };
    var severityColor = { critical: '#dc2626', warning: '#f59e0b', good: '#16a34a' };

    function openIssueModal(issueKey) {
        if (!issueKey) return;

        // Show modal with loading state.
        var $modal = $('#sapc-issue-modal');
        $('#sapc-modal-title').text('Loading…');
        $('#sapc-fix-steps').empty();
        $('#sapc-modal-posts').empty();
        $('#sapc-modal-count').text('…');
        $('#sapc-docs-link').hide();
        $modal.fadeIn(180);
        $('body').addClass('sapc-modal-open');

        $.post(AJAX_URL, {
            action    : 'ggrwa_seo_issue_detail',
            nonce     : NONCE,
            issue_key : issueKey
        })
        .done(function (res) {
            if (!res || !res.success || !res.data) {
                $('#sapc-modal-title').text('Could not load issue details.');
                return;
            }
            var d = res.data;

            // Icon + title.
            var sev   = d.severity || 'warning';
            var icon  = severityIcon[sev]  || '?';
            var color = severityColor[sev] || '#f59e0b';
            $('#sapc-modal-icon').text(icon).css({ background: color });
            $('#sapc-modal-title').text(d.fix_title || 'Issue Detail');

            // Fix steps.
            var $steps = $('#sapc-fix-steps').empty();
            if (d.fix_steps && d.fix_steps.length) {
                $.each(d.fix_steps, function (i, step) {
                    $steps.append('<li>' + escHtml(step) + '</li>');
                });
            }

            // Docs link.
            if (d.docs_url) {
                $('#sapc-docs-link').attr('href', d.docs_url).show();
            }

            // Affected posts.
            var posts = d.posts || [];
            $('#sapc-modal-count').text(posts.length);
            var $list = $('#sapc-modal-posts').empty();

            if (!posts.length) {
                $list.append('<li class="sapc-no-data">No affected pages found — great work!</li>');
            } else {
                $.each(posts, function (i, p) {
                    var actions = '';
                    if (p.edit_url) {
                        actions += '<a href="' + escHtml(p.edit_url) + '" class="sapc-mpost-btn" target="_blank">Edit</a>';
                    }
                    if (p.view_url) {
                        actions += '<a href="' + escHtml(p.view_url) + '" class="sapc-mpost-btn sapc-mpost-view" target="_blank">View</a>';
                    }
                    var note = p.note ? '<span class="sapc-mpost-note">' + escHtml(p.note) + '</span>' : '';
                    var type = p.type ? '<span class="sapc-kw-type-badge sapc-type-' + escHtml(p.type) + '">' + escHtml(p.type) + '</span>' : '';
                    $list.append(
                        '<li class="sapc-mpost-row">' +
                        '<div class="sapc-mpost-info">' +
                        '<span class="sapc-mpost-title">' + escHtml(p.title) + type + '</span>' +
                        note +
                        '</div>' +
                        '<div class="sapc-mpost-actions">' + actions + '</div>' +
                        '</li>'
                    );
                });
            }
        })
        .fail(function () {
            $('#sapc-modal-title').text('Request failed — please try again.');
        });
    }

    function closeModal() {
        $('#sapc-issue-modal').fadeOut(180);
        $('body').removeClass('sapc-modal-open');
    }

    /* ════════════════════════════════════════════════════════════════════════
       APPLY DATA (after AJAX audit refresh)
    ════════════════════════════════════════════════════════════════════════ */
    function applyData(d) {
        if (!d) return;

        var score = parseInt(d.overall_score, 10) || 0;
        var circ  = 326.73;
        var color = score >= 80 ? '#16a34a' : (score >= 60 ? '#1e3a5f' : '#dc2626');
        animateRing(score, circ, color);

        $('#sapc-last-scan-val').text(d.last_scan_label || 'just now');
        $('#sapc-score-sublabel')
            .attr('class', 'sapc-score-sublabel sapc-' + (d.score_class || 'warning'))
            .text(d.score_label || '');

        $('#sapc-stat-audited').text(formatNum(d.posts_audited));
        $('#sapc-stat-critical').text(d.critical_issues || 0);
        $('#sapc-stat-indexed').text(formatNum(d.indexed_pages));
        $('#sapc-stat-indexed-pct').text((d.indexed_pct || 0) + '% audited');
        $('#sapc-stat-readability').text(d.avg_readability || 0);

        renderIssues(d.issues);
        renderKeywords(d.keyword_posts, 'all');
        renderReadability(d.readability);
        renderQuickWins(d.quick_wins);
        renderSchema(d.schema_types);
        renderMonitor(d.broken_links, d.active_redirects, d.pending_fixes);

        $('#sapc-sm-posts').text(formatNum(d.sitemap_posts));
        $('#sapc-sm-pages').text(formatNum(d.sitemap_pages));
        $('#sapc-sm-images').text(formatNum(d.sitemap_images));
        $('#sapc-og-title').html('&#x2713; ' + formatNum(d.og_title_posts) + ' posts');

        if (parseInt(d.og_img_missing, 10) > 0) {
            $('#sapc-og-img').attr('class', 'sapc-og-miss').html('&#x2717; ' + d.og_img_missing + ' missing');
        } else {
            $('#sapc-og-img').attr('class', 'sapc-og-ok').html('&#x2713; All set');
        }
    }

    /* ════════════════════════════════════════════════════════════════════════
       UTILITIES
    ════════════════════════════════════════════════════════════════════════ */
    function formatNum(n) { return (parseInt(n, 10) || 0).toLocaleString(); }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ════════════════════════════════════════════════════════════════════════
       EVENT BINDINGS
    ════════════════════════════════════════════════════════════════════════ */

    // ── Keyword tabs ─────────────────────────────────────────────────────────
    $(document).on('click', '.sapc-tab', function () {
        var $tab = $(this);
        $tab.closest('.sapc-tabs').find('.sapc-tab').removeClass('sapc-tab-active');
        $tab.addClass('sapc-tab-active');
        filterKeywords($tab.data('tab'));
    });

    // ── Issue row click → modal ───────────────────────────────────────────────
    $(document).on('click', '.sapc-issue-clickable', function () {
        var key = $(this).data('issue');
        if (key) openIssueModal(key);
    });

    // ── Close modal ───────────────────────────────────────────────────────────
    $(document).on('click', '#sapc-modal-close, #sapc-modal-close-btn', closeModal);
    $(document).on('click', '.sapc-modal-backdrop', function (e) {
        if ($(e.target).hasClass('sapc-modal-backdrop')) closeModal();
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // ── Run Full Audit ────────────────────────────────────────────────────────
    $('#sapc-run-audit-btn').on('click', function () {
        if (!AJAX_URL || !NONCE) {
            alert('Page not fully loaded — please refresh and try again.');
            return;
        }

        showOverlay('Running site-wide SEO audit…');

        $.post(AJAX_URL, { action: 'ggrwa_run_seo_full_audit', nonce: NONCE })
        .done(function (res) {
            if (res && res.success && res.data) {
                applyData(res.data);
            } else {
                alert('Audit returned an unexpected response. Please refresh the page.');
            }
        })
        .fail(function (xhr) {
            var msg = 'Audit request failed (HTTP ' + xhr.status + ').';
            if (xhr.status === 0) msg = 'Could not reach the server. Check your hosting is running.';
            alert(msg);
        })
        .always(hideOverlay);
    });

    // ── On page load: initialise ring + store keyword rows ───────────────────
    $(function () {
        var circle = $('#sapc-ring-circle');
        if (circle.length) {
            animateRing(
                parseInt(circle.attr('data-score'), 10) || 0,
                parseFloat(circle.attr('data-circ'))    || 326.73,
                circle.attr('data-color')               || '#1e3a5f'
            );
        }

        // Cache keyword rows from pre-rendered PHP so tabs work immediately.
        $('#sapc-keyword-list .sapc-keyword-row').each(function () {
            _allKeywordRows.push({
                _el      : this,
                tab      : $(this).data('tab') || 'all',
                post_type: $(this).data('posttype') || 'post',
            });
        });
    });

})(jQuery);
