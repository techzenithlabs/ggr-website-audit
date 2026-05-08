/**
 * GGR Page Analyzer — page-analyzer.js
 * @package GGR_Website_Audit  @since 3.0.0
 */
(function ($) {
    'use strict';

    const CFG      = window.ggrwa_page_analyzer || {};
    const AJAX_URL = CFG.ajax_url || '';
    const NONCE    = CFG.nonce    || '';

    let _selectedPost = null;
    let _searchTimer  = null;
    let _activeType   = '';
    let _activeGroup  = 'all';
    let _checks       = [];

    /* ── Status label map ────────────────────────────────────────────────── */
    const STATUS_LABELS = {
        publish : 'Live',
        draft   : 'Draft',
        pending : 'Pending Review',
        future  : 'Scheduled',
        private : 'Private',
    };

    const STATUS_ICONS = {
        publish : '🟢',
        draft   : '📝',
        pending : '🕐',
        future  : '📅',
        private : '🔒',
    };

    /* ── Helpers ─────────────────────────────────────────────────────────── */
    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ── Type pills ──────────────────────────────────────────────────────── */
    $(document).on('click', '.gpa-type-pill', function () {
        $('.gpa-type-pill').removeClass('gpa-type-active');
        $(this).addClass('gpa-type-active');
        _activeType = $(this).data('type') || '';
        const label = $(this).text().trim();
        $('#gpa-type-hint').html(
            _activeType
                ? `Filtering by <strong>${esc(label)}</strong> only. Results from other types will show a mismatch warning.`
                : 'Searching across <strong>all content types</strong>. Select a type above to narrow results and catch type mismatches.'
        );
        const q = $('#gpa-search-input').val().trim();
        if (q.length >= 2) doSearch(q);
    });

    /* ── Search input ────────────────────────────────────────────────────── */
    $('#gpa-search-input').on('input', function () {
        const q = $(this).val().trim();
        clearTimeout(_searchTimer);
        if (q.length < 2) { closeDropdown(); return; }
        showDropdownLoading();
        _searchTimer = setTimeout(() => doSearch(q), 280);
    }).on('keydown', function (e) {
        if (e.key === 'Escape') closeDropdown();
    });

    function doSearch(q) {
        $.post(AJAX_URL, {
            action    : 'ggrwa_search_posts',
            nonce     : NONCE,
            q         : q,
            post_type : _activeType
        }).done(res => {
            if (res && res.success) renderDropdown(res.data || []);
            else closeDropdown();
        }).fail(closeDropdown);
    }

    function showDropdownLoading() {
        $('#gpa-search-dropdown')
            .html('<div class="gpa-dd-loading">Searching…</div>')
            .addClass('is-open');
    }

    function renderDropdown(items) {
        const $dd = $('#gpa-search-dropdown').empty();
        if (!items.length) {
            $dd.html('<div class="gpa-dd-empty">No results found.</div>').addClass('is-open');
            return;
        }
        items.forEach(p => {
            const typeCls     = 'gpa-dd-type-' + esc(p.type);
            const statusLabel = STATUS_LABELS[p.status] || p.status;
            const statusCls   = 'gpa-dd-status gpa-dd-status-' + esc(p.status);
            const statusBadge = p.status !== 'publish'
                ? `<span class="${statusCls}">${STATUS_ICONS[p.status] || ''} ${esc(statusLabel)}</span>`
                : '';
            const mismatch = p.type_mismatch
                ? `<span class="gpa-dd-mismatch" title="${esc(p.mismatch_msg)}">⚠ type mismatch</span>`
                : '';
            const $item = $(`
                <div class="gpa-dd-item">
                    <span class="gpa-dd-title">${esc(p.title)}</span>
                    ${mismatch}
                    ${statusBadge}
                    <span class="gpa-dd-type ${typeCls}">${esc(p.type_label || p.type)}</span>
                </div>`);
            $item.data('post', p);
            $dd.append($item);
        });
        $dd.addClass('is-open');
    }

    function closeDropdown() {
        $('#gpa-search-dropdown').removeClass('is-open').empty();
    }

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.gpa-search-box').length) closeDropdown();
    });

    $(document).on('click', '.gpa-dd-item', function () {
        const post = $(this).data('post');
        selectPost(post);
        closeDropdown();
    });

    /* ── Post selection ──────────────────────────────────────────────────── */
    function selectPost(post) {
        _selectedPost = post;
        $('#gpa-sel-title').text(post.title);
        $('#gpa-sel-type').text(post.type).attr('class', 'gpa-sel-type gpa-dd-type-' + post.type);
        $('#gpa-selected-post').show();
        $('#gpa-search-input').val(post.title);
        $('#gpa-analyze-btn').prop('disabled', false);
    }

    $('#gpa-sel-clear').on('click', function () {
        _selectedPost = null;
        $('#gpa-selected-post').hide();
        $('#gpa-search-input').val('');
        $('#gpa-analyze-btn').prop('disabled', true);
        showEmpty();
    });

    /* ── Analyze button ──────────────────────────────────────────────────── */
    $('#gpa-analyze-btn').on('click', function () {
        if (!_selectedPost) return;
        runAnalysis(_selectedPost.id);
    });

    function runAnalysis(postId) {
        $('#gpa-empty').hide();
        $('#gpa-results').hide();
        $('#gpa-loading').show();

        $.post(AJAX_URL, {
            action  : 'ggrwa_analyze_single_post',
            nonce   : NONCE,
            post_id : postId
        }).done(res => {
            $('#gpa-loading').hide();
            if (res && res.success && res.data) {
                renderResults(res.data);
            } else {
                showEmpty();
                alert('Analysis failed. Please try again.');
            }
        }).fail(() => {
            $('#gpa-loading').hide();
            showEmpty();
            alert('Request failed. Please check your connection.');
        });
    }

    /* ── Status banner ───────────────────────────────────────────────────── */
    function renderStatusBanner(ctx) {
        if (!ctx) return;

        const $banner = $('#gpa-status-banner');
        const type    = ctx.banner_type || 'info';

        // Banner config per type
        const bannerCfg = {
            success   : { icon: '✅', label: 'Live & Indexable',    cls: 'gpa-banner-success'   },
            warning   : { icon: '⚠️',  label: 'Visibility Issue',    cls: 'gpa-banner-warning'   },
            draft     : { icon: '📝',  label: 'Draft — Not Live',    cls: 'gpa-banner-draft'     },
            pending   : { icon: '🕐',  label: 'Pending Review',      cls: 'gpa-banner-pending'   },
            scheduled : { icon: '📅',  label: 'Scheduled',           cls: 'gpa-banner-scheduled' },
            info      : { icon: 'ℹ️',  label: ctx.status,            cls: 'gpa-banner-info'      },
        };

        const cfg = bannerCfg[type] || bannerCfg.info;

        // Visibility badge
        const visMap = {
            public   : { label: 'Public',             cls: 'gpa-vis-public'   },
            private  : { label: 'Private',            cls: 'gpa-vis-private'  },
            password : { label: 'Password Protected', cls: 'gpa-vis-password' },
        };
        const vis    = visMap[ctx.visibility] || visMap.public;
        const visBadge = `<span class="gpa-vis-badge ${vis.cls}">👁 ${vis.label}</span>`;

        // Permalink badge
        const plGood   = ctx.permalink_type === 'post-name';
        const plBadge  = `<span class="gpa-permalink-badge ${plGood ? 'gpa-pl-good' : 'gpa-pl-warn'}">
            🔗 ${esc(ctx.permalink_label)}
        </span>`;

        // Advisory messages
        const advisoryHtml = (ctx.advisories || []).map((a, i) =>
            `<div class="gpa-advisory-line ${i === 0 ? 'gpa-advisory-main' : ''}">${esc(a)}</div>`
        ).join('');

        // Google indexability indicator
        const indexHtml = ctx.can_index
            ? `<span class="gpa-index-badge gpa-index-yes">✓ Google can index this page</span>`
            : `<span class="gpa-index-badge gpa-index-no">✕ Google cannot index this page</span>`;

        $banner.html(`
            <div class="gpa-status-banner ${cfg.cls}">
                <div class="gpa-status-banner-left">
                    <div class="gpa-status-banner-icon">${cfg.icon}</div>
                    <div class="gpa-status-banner-body">
                        <div class="gpa-status-banner-title">${cfg.label}</div>
                        ${advisoryHtml}
                    </div>
                </div>
                <div class="gpa-status-banner-right">
                    ${visBadge}
                    ${plBadge}
                    ${indexHtml}
                    <a href="${esc(ctx.edit_url)}" class="gpa-banner-edit-btn" target="_blank">
                        ✏ Fix in Editor
                    </a>
                </div>
            </div>
            <div class="gpa-permalink-row">
                <span class="gpa-permalink-label">Permalink:</span>
                <code class="gpa-permalink-url">${esc(ctx.permalink)}</code>
                <span class="gpa-permalink-advice ${plGood ? 'gpa-pl-advice-good' : 'gpa-pl-advice-warn'}">
                    ${esc(ctx.permalink_advice)}
                </span>
            </div>
        `).show();
    }

    /* ── Render results ──────────────────────────────────────────────────── */
    function renderResults(d) {
        _checks = d.checks || [];

        // Status banner — unique GGR feature.
        renderStatusBanner(d.status_context || null);

        // Ring.
        const score = parseInt(d.score, 10) || 0;
        const color = score >= 80 ? '#16a34a' : (score >= 60 ? '#3b82f6' : (score >= 40 ? '#f59e0b' : '#dc2626'));
        animateRing(score, color);

        // Grade.
        $('#gpa-score-grade')
            .text(d.grade || '–')
            .attr('class', 'gpa-score-grade gpa-score-grade-' + (d.grade || 'D'));

        // Meta.
        $('#gpa-score-title').text(d.title || '–');
        $('#gpa-score-url').html(`<a href="${esc(d.url)}" target="_blank" rel="noopener">${esc(d.url)}</a>`);
        $('#gpa-pill-type').text(d.post_type || 'post');
        $('#gpa-pill-words').text((d.word_count || 0) + ' words');
        $('#gpa-pill-kw').text(d.focus_kw ? '🔑 ' + d.focus_kw : 'No focus keyword');
        $('#gpa-btn-edit').attr('href', d.edit_url || '#');

        // Hide "View Live" for non-published posts — they have no live URL.
        const ctx = d.status_context || {};
        if (ctx.can_index) {
            $('#gpa-btn-view').attr('href', d.url || '#').show();
        } else {
            $('#gpa-btn-view').hide();
        }

        // Summary counts.
        let good = 0, warn = 0, crit = 0;
        (_checks).forEach(c => {
            if (c.status === 'good')         good++;
            else if (c.status === 'warning') warn++;
            else                             crit++;
        });
        $('#gpa-sum-good .gpa-sum-num').text(good);
        $('#gpa-sum-warn .gpa-sum-num').text(warn);
        $('#gpa-sum-crit .gpa-sum-num').text(crit);

        // Reset group tab.
        _activeGroup = 'all';
        $('.gpa-gtab').removeClass('gpa-gtab-active');
        $('.gpa-gtab[data-group="all"]').addClass('gpa-gtab-active');

        renderChecks();
        $('#gpa-results').show();

        // CWV — only fetch for live public pages.
        if (ctx.can_index && d.url) fetchCWV(d.url, 'mobile');
    }

    /* ── Checks grid ─────────────────────────────────────────────────────── */
    function renderChecks() {
        const $grid = $('#gpa-checks-grid').empty();
        const rows  = _activeGroup === 'all'
            ? _checks
            : _checks.filter(c => c.group === _activeGroup);

        if (!rows.length) {
            $grid.html('<div style="grid-column:1/-1;text-align:center;padding:30px;color:#9ca3af;font-size:13px;">No checks in this category.</div>');
            return;
        }

        const icons = { good: '✓', warning: '⚠', critical: '✕' };
        rows.forEach(c => {
            $grid.append(`
                <div class="gpa-check-card gpa-check-${esc(c.status)}">
                    <div class="gpa-check-icon gpa-icon-${esc(c.status)}">${icons[c.status] || '?'}</div>
                    <div class="gpa-check-body">
                        <div class="gpa-check-label">${esc(c.label)}</div>
                        <div class="gpa-check-desc">${esc(c.desc)}</div>
                        <span class="gpa-check-value gpa-val-${esc(c.status)}">${esc(c.value)}</span>
                    </div>
                </div>`);
        });
    }

    /* ── Group tabs ──────────────────────────────────────────────────────── */
    $(document).on('click', '.gpa-gtab', function () {
        $('.gpa-gtab').removeClass('gpa-gtab-active');
        $(this).addClass('gpa-gtab-active');
        _activeGroup = $(this).data('group') || 'all';
        renderChecks();
    });

    /* ── Ring animation ──────────────────────────────────────────────────── */
    function animateRing(score, color) {
        const circ   = 326.73;
        const target = circ - (Math.min(100, score) / 100 * circ);
        const $c     = $('#gpa-ring-circle');
        $c.attr('stroke', color).css('stroke-dashoffset', circ);
        $({ v: circ }).animate({ v: target }, {
            duration: 900, easing: 'swing',
            step() { $c.css('stroke-dashoffset', this.v); }
        });
        $({ n: 0 }).animate({ n: score }, {
            duration: 900,
            step() { $('#gpa-ring-val').text(Math.round(this.n)); }
        });
    }

    /* ── Core Web Vitals ─────────────────────────────────────────────────── */
    let _currentUrl      = '';
    let _currentStrategy = 'mobile';

    function fetchCWV(url, strategy) {
        if (!url) return;
        _currentUrl      = url;
        _currentStrategy = strategy || 'mobile';

        $('#gpa-cwv-loading').show();
        $('#gpa-cwv-metrics, #gpa-cwv-opps').hide();

        $.post(AJAX_URL, {
            action   : 'ggrwa_get_pagespeed',
            nonce    : NONCE,
            url      : url,
            strategy : _currentStrategy
        }).done(res => {
            $('#gpa-cwv-loading').hide();
            if (res && res.success && res.data) {
                renderCWV(res.data);
            } else {
                $('#gpa-cwv-metrics').show();
                ['lcp','cls','fid','perf'].forEach(id => setCWVMetric(id, '–', 'warn', 0));
            }
        }).fail(() => {
            $('#gpa-cwv-loading').hide();
            $('#gpa-cwv-metrics').show();
        });
    }

    function renderCWV(d) {
        $('#gpa-cwv-metrics').show();

        const lcpPct = Math.min(100, (d.lcp / 6) * 100);
        setCWVMetric('lcp', d.lcp + 's', d.lcp_status === 'good' ? 'good' : (d.lcp_status === 'warning' ? 'warn' : 'crit'), lcpPct);

        const clsPct = Math.min(100, (d.cls / 0.5) * 100);
        setCWVMetric('cls', d.cls, d.cls_status === 'good' ? 'good' : (d.cls_status === 'warning' ? 'warn' : 'crit'), clsPct);

        const tbt    = d.tbt_ms || 0;
        const tbtPct = Math.min(100, (tbt / 1000) * 100);
        setCWVMetric('fid', tbt + 'ms', tbt <= 200 ? 'good' : (tbt <= 600 ? 'warn' : 'crit'), tbtPct);

        const perf = d.perf_score || 0;
        setCWVMetric('perf', perf, perf >= 90 ? 'good' : (perf >= 50 ? 'warn' : 'crit'), perf);

        const $opps = $('#gpa-cwv-opps').empty().show();
        (d.opportunities || []).forEach(o => {
            const cls = o.severity === 'critical' ? 'gpa-opp-critical' : '';
            const sav = o.savings ? `<span class="gpa-cwv-opp-savings">↓ ${esc(o.savings)}</span>` : '';
            $opps.append(`<div class="gpa-cwv-opp ${cls}"><div><span class="gpa-cwv-opp-title">${esc(o.title)}</span>${sav}</div></div>`);
        });
    }

    function setCWVMetric(id, val, status, pct) {
        $(`#gpa-cwv-${id}`).attr('class', `gpa-cwv-metric gpa-cwv-${status}`);
        $(`#gpa-cwv-${id}-val`).text(val);
        $(`#gpa-cwv-${id}-bar`).css('width', Math.round(pct) + '%');
    }

    $(document).on('click', '.gpa-strat-btn', function () {
        $('.gpa-strat-btn').removeClass('gpa-strat-active');
        $(this).addClass('gpa-strat-active');
        fetchCWV(_currentUrl, $(this).data('strategy'));
    });

    /* ── Empty / loading states ──────────────────────────────────────────── */
    function showEmpty() {
        $('#gpa-results, #gpa-loading, #gpa-status-banner').hide();
        $('#gpa-empty').show();
    }

})(jQuery);