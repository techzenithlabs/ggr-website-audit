/**
 * Content Analyzer — JS
 * Filter chips, search, sort, refresh.
 * @package GGR_Website_Audit @since 2.5.0
 */
(function ($) {
    'use strict';

    var AJAX_URL = (window.ggrwa_ca || {}).ajax_url || '';
    var NONCE    = (window.ggrwa_ca || {}).nonce    || '';
    var _activeFilter = '';

    /* ── helpers ── */
    function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function applyFilters() {
        var search = ($('#ggrwa-ca-search').val() || '').toLowerCase();
        var count  = 0;
        $('#ggrwa-ca-tbody tr').each(function(){
            var $tr     = $(this);
            var issues  = ($tr.attr('data-issues') || '').split(',');
            var text    = $tr.text().toLowerCase();
            var matchF  = !_activeFilter || issues.indexOf(_activeFilter) !== -1;
            var matchS  = !search || text.indexOf(search) !== -1;
            if (matchF && matchS) { $tr.removeClass('ggrwa-ca-hidden'); count++; }
            else                  { $tr.addClass('ggrwa-ca-hidden'); }
        });
        $('#ggrwa-ca-showing').text(count);
    }

    function sortTable(key) {
        var $tbody = $('#ggrwa-ca-tbody');
        var rows   = $tbody.find('tr').toArray();
        rows.sort(function(a, b){
            var av, bv;
            switch(key){
                case 'word_count_desc': av = parseInt($(a).find('[data-val]').eq(0).attr('data-val'),10)||0;
                                        bv = parseInt($(b).find('[data-val]').eq(0).attr('data-val'),10)||0;
                                        return bv - av;
                case 'word_count_asc':  av = parseInt($(a).find('[data-val]').eq(0).attr('data-val'),10)||0;
                                        bv = parseInt($(b).find('[data-val]').eq(0).attr('data-val'),10)||0;
                                        return av - bv;
                case 'flesch_desc':     av = parseInt($(a).find('[data-val]').eq(1).attr('data-val'),10)||0;
                                        bv = parseInt($(b).find('[data-val]').eq(1).attr('data-val'),10)||0;
                                        return bv - av;
                case 'grade_asc':       av = $(a).find('.sapc-grade').text();
                                        bv = $(b).find('.sapc-grade').text();
                                        return av < bv ? -1 : av > bv ? 1 : 0;
                case 'issues_desc':     av = ($(a).attr('data-issues')||'').split(',').filter(Boolean).length;
                                        bv = ($(b).attr('data-issues')||'').split(',').filter(Boolean).length;
                                        return bv - av;
                default: return 0;
            }
        });
        $.each(rows, function(i, r){ $tbody.append(r); });
        applyFilters();
    }

    /* ── filter chips ── */
    $(document).on('click', '.ggrwa-ca-chip', function(){
        $('.ggrwa-ca-chip').removeClass('ggrwa-ca-chip-active');
        $(this).addClass('ggrwa-ca-chip-active');
        _activeFilter = $(this).data('filter') || '';
        applyFilters();
    });

    /* ── search ── */
    $(document).on('input', '#ggrwa-ca-search', function(){ applyFilters(); });

    /* ── sort ── */
    $(document).on('change', '#ggrwa-ca-sort', function(){ sortTable($(this).val()); });

    /* ── refresh ── */
    $('#ggrwa-ca-refresh').on('click', function(){
        $('#ggrwa-ca-overlay').fadeIn(200);
        $.post(AJAX_URL, { action:'ggrwa_content_analyzer_refresh', nonce:NONCE })
        .done(function(res){
            if (res && res.success) { location.reload(); }
            else { alert('Refresh failed. Please try again.'); }
        })
        .fail(function(){ alert('Request failed. Please check your connection.'); })
        .always(function(){ $('#ggrwa-ca-overlay').fadeOut(200); });
    });

    /* ── init ── */
    $(function(){ applyFilters(); });

})(jQuery);
