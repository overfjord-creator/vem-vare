(function($) {
    'use strict';

    const __ = function(key) {
        return (vvData.i18n && vvData.i18n[key]) ? vvData.i18n[key] : key;
    };

    // =========================================================================
    // STATE
    // =========================================================================
    const state = {
        page: 1,
        perPage: 25,
        search: '',
        country: '',
        period: '',
        commentFilter: '',
        sort: 'last_visit',
        countriesLoaded: false,
        countryBarExpanded: false,
    };

    let searchTimer = null;

    // =========================================================================
    // INIT
    // =========================================================================
    $(document).ready(function() {
        loadStats();
        loadCountryStats();
        loadVisitors();
        bindEvents();
    });

    // =========================================================================
    // EVENT BINDINGS
    // =========================================================================
    function bindEvents() {
        // Search with debounce
        $('#vv-search').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                state.search = $('#vv-search').val().trim();
                state.page = 1;
                loadVisitors();
            }, 350);
        });

        // Filters
        $('#vv-filter-country').on('change', function() {
            state.country = $(this).val();
            state.page = 1;
            loadVisitors();
            updateActiveChip();
        });

        $('#vv-filter-period').on('change', function() {
            state.period = $(this).val();
            state.page = 1;
            loadVisitors();
            loadStats();
        });

        $('#vv-filter-comment').on('change', function() {
            state.commentFilter = $(this).val();
            state.page = 1;
            loadVisitors();
        });

        $('#vv-filter-sort').on('change', function() {
            state.sort = $(this).val();
            state.page = 1;
            loadVisitors();
        });

        // Refresh
        $('#vv-refresh-btn').on('click', function() {
            loadStats();
            loadCountryStats();
            loadVisitors();
            toast(__('dataRefreshed'), 'success');
        });

        // Export
        $('#vv-export-btn').on('click', exportCSV);

        // Modal
        $('#vv-modal-close, #vv-modal-cancel').on('click', closeModal);
        $('#vv-modal-save').on('click', saveComment);
        $('#vv-modal').on('click', function(e) {
            if (e.target === this) closeModal();
        });

        // ESC to close modal
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // Country bar toggle
        $('#vv-country-toggle').on('click', function() {
            state.countryBarExpanded = !state.countryBarExpanded;
            const $chips = $('#vv-country-chips');
            if (state.countryBarExpanded) {
                $chips.addClass('vv-country-chips--expanded');
                $(this).html(__('showAll').replace(__('showAll'), '&#x25B2; ' + __('showAll')));
            } else {
                $chips.removeClass('vv-country-chips--expanded');
                $(this).html(__('showAll') + ' <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>');
            }
        });
    }

    // =========================================================================
    // API CALLS
    // =========================================================================
    function loadStats() {
        $.post(vvData.ajaxUrl, {
            action: 'vv_get_stats',
            nonce: vvData.nonce,
        }, function(res) {
            if (res.success) {
                animateNumber('#vv-stat-total', res.data.total);
                animateNumber('#vv-stat-today', res.data.today);
                animateNumber('#vv-stat-countries', res.data.countries);
                animateNumber('#vv-stat-comments', res.data.comments);
            }
        });
    }

    function loadCountryStats() {
        $.post(vvData.ajaxUrl, {
            action: 'vv_get_country_stats',
            nonce: vvData.nonce,
        }, function(res) {
            if (!res.success || !res.data.countries.length) {
                $('#vv-country-bar').hide();
                return;
            }

            $('#vv-country-bar').show();
            const countries = res.data.countries;
            const totalVisitors = res.data.total_visitors || 1;

            let html = '';

            // "All" chip
            html += `<button class="vv-chip vv-chip--active" data-country="">
                ${countryFlag('')} ${__('allCountries')}
                <span class="vv-chip-count">${totalVisitors}</span>
            </button>`;

            countries.forEach(function(c) {
                const pct = Math.round((c.visitor_count / totalVisitors) * 100);
                html += `<button class="vv-chip" data-country="${esc(c.country_code)}">
                    ${countryFlag(c.country_code)} ${esc(c.country)}
                    <span class="vv-chip-count">${c.visitor_count}</span>
                    <span class="vv-chip-pct">${pct}%</span>
                </button>`;
            });

            $('#vv-country-chips').html(html);

            // Chip click → filter
            $('.vv-chip').on('click', function() {
                const code = $(this).data('country');
                state.country = code;
                state.page = 1;

                // Update dropdown to match
                $('#vv-filter-country').val(code);

                loadVisitors();
                updateActiveChip();
            });
        });
    }

    function updateActiveChip() {
        $('.vv-chip').removeClass('vv-chip--active');
        $(`.vv-chip[data-country="${state.country}"]`).addClass('vv-chip--active');
    }

    function loadVisitors() {
        const $tbody = $('#vv-tbody');
        $tbody.html(`
            <tr>
                <td colspan="10" class="vv-loading">
                    <div class="vv-spinner"></div>
                    <span>${esc(__('loading'))}</span>
                </td>
            </tr>
        `);

        $.post(vvData.ajaxUrl, {
            action: 'vv_get_visitors',
            nonce: vvData.nonce,
            page: state.page,
            per_page: state.perPage,
            search: state.search,
            country: state.country,
            period: state.period,
            comment_filter: state.commentFilter,
            sort: state.sort,
        }, function(res) {
            if (!res.success) {
                $tbody.html('<tr><td colspan="10" class="vv-empty">' + esc(__('loadError')) + '</td></tr>');
                return;
            }

            const { visitors, total, page, pages, countries } = res.data;

            // Populate country filter dropdown (with counts)
            if (!state.countriesLoaded && countries.length) {
                const $select = $('#vv-filter-country');
                countries.forEach(function(c) {
                    $select.append(`<option value="${esc(c.country_code)}">${countryFlag(c.country_code)} ${esc(c.country)} (${c.visitor_count})</option>`);
                });
                state.countriesLoaded = true;
            }

            if (!visitors.length) {
                $tbody.html(`
                    <tr>
                        <td colspan="10" class="vv-empty">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                            ${esc(__('noVisitors'))}
                        </td>
                    </tr>
                `);
                $('#vv-pagination').empty();
                return;
            }

            // Render rows
            let html = '';
            visitors.forEach(function(v) {
                const visits = parseInt(v.visit_count, 10);
                const visitsClass = visits >= 10 ? 'vv-visits vv-visits-high' : 'vv-visits';
                const dns = v.reverse_dns
                    ? `<span class="vv-dns" title="${esc(v.reverse_dns)}">${esc(v.reverse_dns)}</span>`
                    : '<span class="vv-dns-empty">&mdash;</span>';
                let org;
                if (v.org && v.country_code === 'SE') {
                    const abUrl = 'https://www.allabolag.se/bransch-s%C3%B6k?q=' + encodeURIComponent(v.org + (v.city ? ' ' + v.city : ''));
                    org = `<a href="${abUrl}" target="_blank" rel="noopener noreferrer" class="vv-org vv-org-link" title="${esc(v.org)} — Sök på Allabolag">${esc(v.org)} <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></a>`;
                } else if (v.org) {
                    org = `<span class="vv-org" title="${esc(v.org)}">${esc(v.org)}</span>`;
                } else {
                    org = '<span class="vv-dns-empty">&mdash;</span>';
                }
                const comment = v.comment
                    ? `<span class="vv-comment-text" title="${esc(v.comment)}" data-id="${v.id}">${esc(v.comment)}</span>`
                    : `<span class="vv-no-comment" data-id="${v.id}">${esc(__('addComment'))}</span>`;

                html += `
                <tr data-id="${v.id}">
                    <td><span class="vv-flag">${countryFlag(v.country_code)}</span></td>
                    <td><span class="vv-ip">${esc(v.ip_address)}</span></td>
                    <td>${dns}</td>
                    <td>${org}</td>
                    <td><span class="vv-city">${esc(v.city) || '<span class="vv-dns-empty">&mdash;</span>'}</span></td>
                    <td><span class="vv-country">${esc(v.country) || '<span class="vv-dns-empty">&mdash;</span>'}</span></td>
                    <td><span class="${visitsClass}">${visits}</span></td>
                    <td><span class="vv-time">${formatDate(v.last_visit)}</span></td>
                    <td class="vv-comment-cell">${comment}</td>
                    <td>
                        <div class="vv-actions">
                            <button class="vv-btn-icon vv-edit-comment" data-id="${v.id}" title="${esc(__('commentSaved').replace('!',''))}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="vv-btn-icon vv-btn-danger vv-delete-visitor" data-id="${v.id}" title="${esc(__('visitorDeleted').replace('n',''))}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });

            $tbody.html(html);
            renderPagination(page, pages, total);

            // Bind row-level events
            $('.vv-edit-comment, .vv-comment-text, .vv-no-comment').on('click', function() {
                const id = $(this).data('id');
                const row = visitors.find(v => parseInt(v.id) === parseInt(id));
                if (row) openModal(row);
            });

            $('.vv-delete-visitor').on('click', function() {
                const id = $(this).data('id');
                if (confirm(__('confirmDelete'))) {
                    deleteVisitor(id);
                }
            });
        });
    }

    function saveComment() {
        const id = $('#vv-comment-id').val();
        const comment = $('#vv-comment-text').val();

        $.post(vvData.ajaxUrl, {
            action: 'vv_save_comment',
            nonce: vvData.nonce,
            visitor_id: id,
            comment: comment,
        }, function(res) {
            if (res.success) {
                closeModal();
                loadVisitors();
                loadStats();
                toast(__('commentSaved'), 'success');
            } else {
                toast(__('commentSaveError') + (res.data || ''), 'error');
            }
        });
    }

    function deleteVisitor(id) {
        $.post(vvData.ajaxUrl, {
            action: 'vv_delete_visitor',
            nonce: vvData.nonce,
            visitor_id: id,
        }, function(res) {
            if (res.success) {
                loadVisitors();
                loadStats();
                loadCountryStats();
                toast(__('visitorDeleted'), 'success');
            } else {
                toast(__('deleteError'), 'error');
            }
        });
    }

    function exportCSV() {
        $.post(vvData.ajaxUrl, {
            action: 'vv_export_csv',
            nonce: vvData.nonce,
        }, function(res) {
            if (res.success && res.data.csv) {
                const bom = '\uFEFF';
                const blob = new Blob([bom + res.data.csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'vem-vare-export-' + new Date().toISOString().slice(0, 10) + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                toast(__('csvExported'), 'success');
            }
        });
    }

    // =========================================================================
    // UI HELPERS
    // =========================================================================
    function openModal(visitor) {
        $('#vv-comment-id').val(visitor.id);
        $('#vv-comment-text').val(visitor.comment || '');
        $('#vv-modal-info').html(`
            <div><strong>IP:</strong> ${esc(visitor.ip_address)}</div>
            <div><strong>DNS:</strong> ${esc(visitor.reverse_dns) || '&mdash;'}</div>
            <div><strong>${esc(__('location'))}:</strong> ${esc(visitor.city)}${visitor.city && visitor.country ? ', ' : ''}${esc(visitor.country)}</div>
            <div><strong>${esc(__('visitsCount'))}:</strong> ${visitor.visit_count}</div>
        `);
        $('#vv-modal').fadeIn(150);
        setTimeout(function() { $('#vv-comment-text').focus(); }, 200);
    }

    function closeModal() {
        $('#vv-modal').fadeOut(150);
    }

    function renderPagination(page, pages, total) {
        const $pag = $('#vv-pagination');
        if (pages <= 1) {
            $pag.html(`<span class="vv-page-info">${total} ${esc(__('visitors'))}</span>`);
            return;
        }

        let html = '';
        html += `<button class="vv-page-btn" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>&laquo;</button>`;

        const maxVisible = 7;
        let start = Math.max(1, page - Math.floor(maxVisible / 2));
        let end = Math.min(pages, start + maxVisible - 1);
        if (end - start < maxVisible - 1) {
            start = Math.max(1, end - maxVisible + 1);
        }

        if (start > 1) {
            html += `<button class="vv-page-btn" data-page="1">1</button>`;
            if (start > 2) html += `<span class="vv-page-info">&hellip;</span>`;
        }

        for (let i = start; i <= end; i++) {
            html += `<button class="vv-page-btn ${i === page ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        if (end < pages) {
            if (end < pages - 1) html += `<span class="vv-page-info">&hellip;</span>`;
            html += `<button class="vv-page-btn" data-page="${pages}">${pages}</button>`;
        }

        html += `<button class="vv-page-btn" data-page="${page + 1}" ${page >= pages ? 'disabled' : ''}>&raquo;</button>`;
        html += `<span class="vv-page-info">${total} ${esc(__('visitors'))}</span>`;

        $pag.html(html);

        $pag.find('.vv-page-btn').on('click', function() {
            const p = parseInt($(this).data('page'));
            if (p >= 1 && p <= pages) {
                state.page = p;
                loadVisitors();
                $('html, body').animate({ scrollTop: $('.vv-table-container').offset().top - 100 }, 200);
            }
        });
    }

    function toast(message, type) {
        const $toast = $(`<div class="vv-toast vv-toast-${type || ''}">${esc(message)}</div>`);
        $('body').append($toast);
        setTimeout(function() {
            $toast.fadeOut(300, function() { $(this).remove(); });
        }, 3000);
    }

    function animateNumber(selector, target) {
        const $el = $(selector);
        const current = parseInt($el.text()) || 0;
        if (current === target) { $el.text(target); return; }
        $({ val: current }).animate({ val: target }, {
            duration: 500,
            easing: 'swing',
            step: function() { $el.text(Math.round(this.val)); },
            complete: function() { $el.text(target); }
        });
    }

    function countryFlag(code) {
        if (!code || code.length !== 2) return '&#127760;';
        const codePoints = code.toUpperCase().split('').map(c => 127397 + c.charCodeAt(0));
        return String.fromCodePoint(...codePoints);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '&mdash;';
        const d = new Date(dateStr.replace(' ', 'T'));
        const now = new Date();
        const diff = now - d;
        const mins = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);

        if (mins < 1) return __('justNow');
        if (mins < 60) return mins + ' ' + __('minAgo');
        if (hours < 24) return hours + ' ' + __('hoursAgo');

        const day = d.getDate().toString().padStart(2, '0');
        const month = (d.getMonth() + 1).toString().padStart(2, '0');
        const year = d.getFullYear();
        const time = d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');

        return `${year}-${month}-${day} ${time}`;
    }

    function esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

})(jQuery);
