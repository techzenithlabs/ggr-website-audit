/**
 * Conversion Audit — JS
 * Filter chips, search, sort, refresh.
 * @package GGR_Website_Audit @since 2.5.0
 */
(function ($) {
    'use strict';

    var AJAX_URL = (window.ggrwa_conv || {}).ajax_url || '';
    var NONCE    = (window.ggrwa_conv || {}).nonce    || '';
    var _activeFilter = '';

    function applyFilters() {
        var search = ($('#ggrwa-conv-search').val() || '').toLowerCase();
        var count  = 0;
        $('#ggrwa-conv-tbody tr').each(function(){
            var $tr    = $(this);
            var issues = ($tr.attr('data-issues') || '').split(',');
            var text   = $tr.text().toLowerCase();
            var matchF = !_activeFilter || issues.indexOf(_activeFilter) !== -1;
            var matchS = !search || text.indexOf(search) !== -1;
            if (matchF && matchS) { $tr.removeClass('ggrwa-ca-hidden'); count++; }
            else                  { $tr.addClass('ggrwa-ca-hidden'); }
        });
        $('#ggrwa-conv-showing').text(count);
    }

    function sortTable(key) {
        var $tbody = $('#ggrwa-conv-tbody');
        var rows   = $tbody.find('tr').toArray();
        rows.sort(function(a, b){
            switch(key){
                case 'score_desc': return (parseInt($(b).attr('data-score'),10)||0) - (parseInt($(a).attr('data-score'),10)||0);
                case 'score_asc':  return (parseInt($(a).attr('data-score'),10)||0) - (parseInt($(b).attr('data-score'),10)||0);
                case 'issues_desc':
                    var ai = ($(a).attr('data-issues')||'').split(',').filter(Boolean).length;
                    var bi = ($(b).attr('data-issues')||'').split(',').filter(Boolean).length;
                    return bi - ai;
                case 'grade_asc':
                    var ag = $(a).find('.sapc-grade').text();
                    var bg = $(b).find('.sapc-grade').text();
                    return ag < bg ? -1 : ag > bg ? 1 : 0;
                default: return 0;
            }
        });
        $.each(rows, function(i, r){ $tbody.append(r); });
        applyFilters();
    }

    $(document).on('click', '.ggrwa-ca-chip', function(){
        $('.ggrwa-ca-chip').removeClass('ggrwa-ca-chip-active');
        $(this).addClass('ggrwa-ca-chip-active');
        _activeFilter = $(this).data('filter') || '';
        applyFilters();
    });

    $(document).on('input',  '#ggrwa-conv-search', applyFilters);
    $(document).on('change', '#ggrwa-conv-sort', function(){ sortTable($(this).val()); });

    $('#ggrwa-conv-refresh').on('click', function(){
        $('#ggrwa-conv-overlay').fadeIn(200);
        $.post(AJAX_URL, { action:'ggrwa_conversion_refresh', nonce:NONCE })
        .done(function(res){
            if (res && res.success) { location.reload(); }
            else { alert('Refresh failed. Please try again.'); }
        })
        .fail(function(){ alert('Request failed. Please check your connection.'); })
        .always(function(){ $('#ggrwa-conv-overlay').fadeOut(200); });
    });

    $(function(){ applyFilters(); });

})(jQuery);
