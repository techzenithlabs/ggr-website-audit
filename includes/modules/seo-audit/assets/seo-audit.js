/**
 * GGR SEO Intelligence — Dashboard JS
 *
 * @package GGR_Website_Audit
 * @since   3.0.0
 */
(function ($) {
    'use strict';

    const CFG      = window.ggrwa_seo_dashboard || {};
    const AJAX_URL = CFG.ajax_url || '';
    const NONCE    = CFG.nonce    || '';

    const CIRC = 326.73;

    let _allKeywordRows = [];

    /* ── Overlay ─────────────────────────────────────────────────────────── */
    const showOverlay = (msg) => {
        $('#sapc-spinner-msg').text(msg || 'Running…');
        $('#sapc-overlay').fadeIn(200);
    };
    const hideOverlay = () => $('#sapc-overlay').fadeOut(200);

    /* ── Ring animation ──────────────────────────────────────────────────── */
    function animateRing(score, circ, color) {
        const $circle = $('#sapc-ring-circle');
        if (!$circle.length) return;

        const targetOffset = circ - (Math.min(100, score) / 100 * circ);
        $circle.attr('stroke', color || '#1e3a5f').css('stroke-dashoffset', circ);

        $({ val: circ }).animate({ val: targetOffset }, {
            duration: 1100, easing: 'swing',
            step() { $circle.css('stroke-dashoffset', this.val); }
        });
        $({ n: 0 }).animate({ n: score }, {
            duration: 1100,
            step() { $('#sapc-ring-val').text(Math.round(this.n)); }
        });
    }

    /* ── Stat counter animation ──────────────────────────────────────────── */
    function animateCounter($el, target, duration = 900) {
        $({ n: 0 }).animate({ n: target }, {
            duration,
            step() { $el.text(Math.round(this.n).toLocaleString()); },
            done() { $el.text(target.toLocaleString()); }
        });
    }

    /* ── Card entrance animation ─────────────────────────────────────────── */
    function revealCards() {
        $('.sapc-card, .sapc-two-col > *, .sapc-three-col > *').each(function (i) {
            const $el = $(this);
            $el.css({ opacity: 0, transform: 'translateY(18px)' });
            setTimeout(() => {
                $el.css({ transition: 'opacity .45s ease, transform .45s ease', opacity: 1, transform: 'translateY(0)' });
            }, 80 + i * 60);
        });
    }

    /* ── Score ring glow on load ─────────────────────────────────────────── */
    function pulseRingGlow(color) {
        const $wrap = $('.sapc-score-ring-wrap');
        $wrap.css({ filter: `drop-shadow(0 0 0px ${color})` });
        $({ v: 0 }).animate({ v: 14 }, {
            duration: 700, easing: 'swing',
            step() { $wrap.css({ filter: `drop-shadow(0 0 ${this.v}px ${color})` }); }
        });
        setTimeout(() => {
            $({ v: 14 }).animate({ v: 4 }, {
                duration: 600,
                step() { $wrap.css({ filter: `drop-shadow(0 0 ${this.v}px ${color})` }); }
            });
        }, 750);
    }

    /* ── Score sublabel blink on critical ────────────────────────────────── */
    function blinkIfCritical(score) {
        if (score < 40) {
            const $lbl = $('#sapc-score-sublabel');
            let visible = true;
            const timer = setInterval(() => {
                $lbl.css('opacity', visible ? 0.3 : 1);
                visible = !visible;
            }, 600);
            setTimeout(() => { clearInterval(timer); $lbl.css('opacity', 1); }, 4200);
        }
    }

    /* ── Issues list ─────────────────────────────────────────────────────── */
    function renderIssues(issues) {
        const $ul = $('#sapc-issues-list').empty();
        if (!issues || !issues.length) {
            $ul.append('<li class="sapc-no-data">No issue data yet.</li>');
            return;
        }
        issues.forEach(issue => {
            const hasDetail = issue.key && parseInt(issue.count, 10) > 0;
            const arrow     = hasDetail ? '<span class="sapc-issue-arrow">&#x276F;</span>' : '';
            const cls       = 'sapc-issue-row' + (hasDetail ? ' sapc-issue-clickable' : '');
            const $li = $(`
                <li class="${cls}" data-issue="${esc(issue.key || '')}"
                    ${hasDetail ? 'title="Click to see affected pages &amp; fix guide"' : ''}>
                    <span class="sapc-dot sapc-dot-${issue.severity}"></span>
                    <span class="sapc-issue-label">${esc(issue.label)}</span>
                    <span class="sapc-issue-count sapc-count-${issue.severity}">${issue.count}</span>
                    ${arrow}
                </li>`);
            $ul.append($li);
        });
    }

    /* ── Keyword performance ─────────────────────────────────────────────── */
    function renderKeywords(posts, activeTab) {
        _allKeywordRows = posts || [];
        filterKeywords(activeTab || 'all');
    }

    function filterKeywords(tab) {
        const $ul = $('#sapc-keyword-list').empty();
        const rows = tab === 'all'
            ? _allKeywordRows
            : _allKeywordRows.filter(kw => kw.tab === tab);

        if (!rows.length) {
            $ul.append(`<li class="sapc-no-data">${tab === 'all' ? 'No audited posts yet — run a full audit.' : `No ${tab} found with SEO data.`}</li>`);
            return;
        }

        rows.forEach(kw => {
            const g     = (kw.grade || 'D').toLowerCase();
            const score = parseInt(kw.score, 10) || 0;
            const sc    = score >= 70 ? 'sapc-kscore-green' : (score >= 50 ? 'sapc-kscore-orange' : 'sapc-kscore-red');
            const title = kw.edit_url
                ? `<a href="${esc(kw.edit_url)}" class="sapc-kw-title" target="_blank">${esc(kw.title)}</a>`
                : `<span class="sapc-kw-title">${esc(kw.title)}</span>`;
            const badge = `<span class="sapc-kw-type-badge sapc-type-${esc(kw.post_type || 'post')}">${esc(kw.post_type || 'post')}</span>`;

            $ul.append(`
                <li class="sapc-keyword-row">
                    <span class="sapc-grade sapc-grade-${g}">${esc(kw.grade)}</span>
                    <span class="sapc-kw-info">${title}
                        <span class="sapc-kw-meta">${esc(kw.keyword)}${badge}</span>
                    </span>
                    <span class="sapc-kscore ${sc}">${score}</span>
                </li>`);
        });
    }

    /* ── Readability ─────────────────────────────────────────────────────── */
    function renderReadability(rows) {
        const $ul = $('#sapc-readability-list').empty();
        if (!rows || !rows.length) {
            $ul.append('<li class="sapc-no-data">No readability data yet.</li>');
            return;
        }
        rows.forEach(r => {
            const val = (r.value !== undefined ? r.value : 'N/A') + (r.unit || '');
            const pct = Math.min(100, parseInt(r.pct, 10) || 0);
            const low = r.status === 'bad' ? '<span class="sapc-read-low">Low</span>' : '';
            const $li = $(`
                <li class="sapc-read-row">
                    <span class="sapc-read-metric">${esc(r.metric)}</span>
                    <div class="sapc-bar-track"><div class="sapc-bar-fill sapc-bar-${r.status}" style="width:0%"></div></div>
                    <span class="sapc-read-value">${esc(String(val))}${low}</span>
                </li>`);
            $ul.append($li);
            $li.find('.sapc-bar-fill').animate({ width: pct + '%' }, 700);
        });
    }

    /* ── Quick wins ──────────────────────────────────────────────────────── */
    function renderQuickWins(wins) {
        const $ul = $('#sapc-quickwins-list').empty();
        if (!wins || !wins.length) { $ul.append('<li class="sapc-no-data">No quick wins yet.</li>'); return; }
        wins.forEach(w => {
            $ul.append(`
                <li class="sapc-win-row">
                    <span class="sapc-win-icon sapc-win-${w.type}">${esc(w.icon)}</span>
                    <span class="sapc-win-body">
                        <span class="sapc-win-title">${esc(w.title)}</span>
                        <span class="sapc-win-desc">${esc(w.desc)}</span>
                    </span>
                </li>`);
        });
    }

    /* ── Schema ──────────────────────────────────────────────────────────── */
    function renderSchema(types) {
        const $g = $('#sapc-schema-grid').empty();
        if (!types || !types.length) return;
        types.forEach(s => {
            $g.append(`<div class="sapc-schema-item"><span class="sapc-schema-name">${esc(s.type)}</span><span class="sapc-dot sapc-dot-${s.status}"></span></div>`);
        });
    }

    /* ── 404 Monitor ─────────────────────────────────────────────────────── */
    function renderMonitor(rows, redirects, pending) {
        const $ul = $('#sapc-monitor-list').empty();
        if (rows && rows.length) {
            rows.forEach(b => {
                const hits  = parseInt(b.hits, 10);
                const badge = hits > 0 ? `<span class="sapc-monitor-hits-inline">${hits} hits</span>` : '';
                $ul.append(`<li class="sapc-monitor-row"><div class="sapc-monitor-label"><strong>${esc(b.label)}</strong>${badge}</div><div class="sapc-monitor-url"><code>${esc(b.url)}</code></div></li>`);
            });
        } else {
            $ul.append('<li class="sapc-no-data">No deleted or trashed posts found.</li>');
        }
        $('#sapc-redirects').text(redirects || 0);
        $('#sapc-pending').text(pending || 0);
    }

    /* ── Issue detail modal ──────────────────────────────────────────────── */
    const SEVERITY_ICON  = { critical: '!', warning: '⚠', good: '✓' };
    const SEVERITY_COLOR = { critical: '#dc2626', warning: '#f59e0b', good: '#16a34a' };

    function openIssueModal(issueKey) {
        if (!issueKey) return;

        const $modal = $('#sapc-issue-modal');
        $('#sapc-modal-title').text('Loading…');
        $('#sapc-fix-steps, #sapc-modal-posts').empty();
        $('#sapc-modal-count').text('…');
        $('#sapc-docs-link').hide();
        $modal.fadeIn(180);
        $('body').addClass('sapc-modal-open');

        $.post(AJAX_URL, { action: 'ggrwa_seo_issue_detail', nonce: NONCE, issue_key: issueKey })
        .done(res => {
            if (!res?.success || !res?.data) {
                $('#sapc-modal-title').text('Could not load issue details.');
                return;
            }
            const d     = res.data;
            const sev   = d.severity || 'warning';
            $('#sapc-modal-icon').text(SEVERITY_ICON[sev] || '?').css({ background: SEVERITY_COLOR[sev] || '#f59e0b' });
            $('#sapc-modal-title').text(d.fix_title || 'Issue Detail');

            const $steps = $('#sapc-fix-steps').empty();
            (d.fix_steps || []).forEach(step => $steps.append(`<li>${esc(step)}</li>`));

            if (d.docs_url) $('#sapc-docs-link').attr('href', d.docs_url).show();

            const posts = d.posts || [];
            $('#sapc-modal-count').text(posts.length);
            const $list = $('#sapc-modal-posts').empty();

            if (!posts.length) {
                $list.append('<li class="sapc-no-data">No affected pages found — great work!</li>');
            } else {
                posts.forEach(p => {
                    const actions = [
                        p.edit_url ? `<a href="${esc(p.edit_url)}" class="sapc-mpost-btn" target="_blank">Edit</a>` : '',
                        p.view_url ? `<a href="${esc(p.view_url)}" class="sapc-mpost-btn sapc-mpost-view" target="_blank">View</a>` : '',
                    ].join('');
                    const note = p.note ? `<span class="sapc-mpost-note">${esc(p.note)}</span>` : '';
                    const type = p.type ? `<span class="sapc-kw-type-badge sapc-type-${esc(p.type)}">${esc(p.type)}</span>` : '';
                    $list.append(`
                        <li class="sapc-mpost-row">
                            <div class="sapc-mpost-info">
                                <span class="sapc-mpost-title">${esc(p.title)}${type}</span>${note}
                            </div>
                            <div class="sapc-mpost-actions">${actions}</div>
                        </li>`);
                });
            }
        })
        .fail(() => $('#sapc-modal-title').text('Request failed — please try again.'));
    }

    const closeModal = () => {
        $('#sapc-issue-modal').fadeOut(180);
        $('body').removeClass('sapc-modal-open');
    };

    /* ── Apply full data refresh ─────────────────────────────────────────── */
    function applyData(d) {
        if (!d) return;

        const score = parseInt(d.overall_score, 10) || 0;
        const color = score >= 80 ? '#16a34a' : (score >= 60 ? '#1e3a5f' : '#dc2626');
        animateRing(score, CIRC, color);
        pulseRingGlow(color);
        blinkIfCritical(score);

        $('#sapc-last-scan-val').text(d.last_scan_label || 'just now');
        $('#sapc-score-sublabel')
            .attr('class', 'sapc-score-sublabel sapc-' + (d.score_class || 'warning'))
            .text(d.score_label || '');

        animateCounter($('#sapc-stat-audited'),     parseInt(d.posts_audited, 10)  || 0);
        animateCounter($('#sapc-stat-critical'),    parseInt(d.critical_issues, 10)|| 0);
        animateCounter($('#sapc-stat-indexed'),     parseInt(d.indexed_pages, 10)  || 0);
        animateCounter($('#sapc-stat-readability'), parseInt(d.avg_readability, 10)|| 0);
        $('#sapc-stat-indexed-pct').text((d.indexed_pct || 0) + '% audited');

        renderIssues(d.issues);
        renderKeywords(d.keyword_posts, 'all');
        renderReadability(d.readability);
        renderQuickWins(d.quick_wins);
        renderSchema(d.schema_types);
        renderMonitor(d.broken_links, d.active_redirects, d.pending_fixes);

        animateCounter($('#sapc-sm-posts'),  parseInt(d.sitemap_posts, 10)  || 0);
        animateCounter($('#sapc-sm-pages'),  parseInt(d.sitemap_pages, 10)  || 0);
        animateCounter($('#sapc-sm-images'), parseInt(d.sitemap_images, 10) || 0);
        $('#sapc-og-title').html('&#x2713; ' + (parseInt(d.og_title_posts, 10) || 0).toLocaleString() + ' posts');

        if (parseInt(d.og_img_missing, 10) > 0) {
            $('#sapc-og-img').attr('class', 'sapc-og-miss').html('&#x2717; ' + d.og_img_missing + ' missing');
        } else {
            $('#sapc-og-img').attr('class', 'sapc-og-ok').html('&#x2713; All set');
        }
    }

    /* ── Utility ─────────────────────────────────────────────────────────── */
    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ── Event bindings ──────────────────────────────────────────────────── */
    $(document).on('click', '.sapc-tab', function () {
        const $tab = $(this);
        $tab.closest('.sapc-tabs').find('.sapc-tab').removeClass('sapc-tab-active');
        $tab.addClass('sapc-tab-active');
        filterKeywords($tab.data('tab'));
    });

    $(document).on('click', '.sapc-issue-clickable', function () {
        const key = $(this).data('issue');
        if (key) openIssueModal(key);
    });

    $(document).on('click', '#sapc-modal-close, #sapc-modal-close-btn', closeModal);
    $(document).on('click', '.sapc-modal-backdrop', e => {
        if ($(e.target).hasClass('sapc-modal-backdrop')) closeModal();
    });
    $(document).on('keydown', e => { if (e.key === 'Escape') closeModal(); });

    $('#sapc-run-audit-btn').on('click', function () {
        if (!AJAX_URL || !NONCE) { alert('Page not fully loaded — please refresh.'); return; }
        showOverlay('Running GGR site-wide SEO audit…');
        $.post(AJAX_URL, { action: 'ggrwa_run_seo_full_audit', nonce: NONCE })
        .done(res => {
            if (res?.success && res?.data) applyData(res.data);
            else alert('Audit returned an unexpected response. Please refresh.');
        })
        .fail(xhr => {
            alert(xhr.status === 0
                ? 'Could not reach the server. Check your hosting is running.'
                : `Audit request failed (HTTP ${xhr.status}).`);
        })
        .always(hideOverlay);
    });

    /* ── Init on DOM ready ───────────────────────────────────────────────── */
    $(function () {
        // Animate ring from PHP-rendered values.
        const $circle = $('#sapc-ring-circle');
        if ($circle.length) {
            const score = parseInt($circle.attr('data-score'), 10) || 0;
            const color = $circle.attr('data-color') || '#1e3a5f';
            animateRing(score, parseFloat($circle.attr('data-circ')) || CIRC, color);
            setTimeout(() => { pulseRingGlow(color); blinkIfCritical(score); }, 400);
        }

        // Animate stat counters on load.
        $('#sapc-stat-audited, #sapc-stat-critical, #sapc-stat-indexed, #sapc-stat-readability').each(function () {
            const $el  = $(this);
            const val  = parseInt($el.text().replace(/,/g, ''), 10) || 0;
            $el.text('0');
            animateCounter($el, val, 1000);
        });

        // Card entrance animations.
        revealCards();

        // Cache keyword rows for tab filtering.
        $('#sapc-keyword-list .sapc-keyword-row').each(function () {
            _allKeywordRows.push({
                _el      : this,
                tab      : $(this).data('tab') || 'all',
                post_type: $(this).data('posttype') || 'post',
            });
        });
    });

})(jQuery);
