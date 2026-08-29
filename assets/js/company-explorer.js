(function () {
    if (typeof window.SFFCCompanyExplorerData === 'undefined') {
        return;
    }

    var data = window.SFFCCompanyExplorerData;
    var root = document.querySelector('.sffc-company-explorer');

    if (!root) {
        return;
    }

    var resultsWrapper = root.querySelector('.sffc-company-results');
    var loadMoreBtn = root.querySelector('.sffc-company-results__load-more');
    var searchInput = root.querySelector('#sffc-company-search-field');
    var sortSelect = root.querySelector('#sffc-company-sort-select');
    var selectedFiltersWrap = root.querySelector('.sffc-company-selected-filters');
    var filtersPane = root.querySelector('.sffc-company-explorer__filters');

    var defaults = data.defaults || { perPage: 12, sort: 'aum_desc' };
    var state = {
        page: 1,
        perPage: defaults.perPage,
        sort: defaults.sort,
        search: '',
        tags: [],
        isLoading: false
    };

    var debouncedTimeout = null;
    var termMap = {};

    if (Array.isArray(data.filters)) {
        data.filters.forEach(function (group) {
            if (!group || !Array.isArray(group.options)) {
                return;
            }
            group.options.forEach(function (option) {
                termMap[option.id] = {
                    name: option.name,
                    group: group.slug
                };
            });
        });
    }

    function toggleFilter(termId) {
        termId = parseInt(termId, 10);
        if (!termId) {
            return;
        }
        var index = state.tags.indexOf(termId);
        if (index === -1) {
            state.tags.push(termId);
        } else {
            state.tags.splice(index, 1);
        }
        updateFilterButtons();
        updateSelectedFiltersUI();
        requestResults({ resetPage: true });
    }

    function clearGroup(groupSlug) {
        if (!groupSlug) {
            return;
        }
        var removed = false;
        state.tags = state.tags.filter(function (termId) {
            var descriptor = termMap[termId];
            if (descriptor && descriptor.group === groupSlug) {
                removed = true;
                return false;
            }
            return true;
        });
        if (removed) {
            updateFilterButtons();
            updateSelectedFiltersUI();
            requestResults({ resetPage: true });
        }
    }

    function updateFilterButtons() {
        if (!filtersPane) {
            return;
        }
        var active = state.tags.slice();
        filtersPane.querySelectorAll('.sffc-company-filter-option').forEach(function (button) {
            var termId = parseInt(button.getAttribute('data-term-id'), 10);
            if (!termId) {
                return;
            }
            if (active.indexOf(termId) !== -1) {
                button.classList.add('is-active');
            } else {
                button.classList.remove('is-active');
            }
        });
    }

    function updateSelectedFiltersUI() {
        if (!selectedFiltersWrap) {
            return;
        }
        selectedFiltersWrap.innerHTML = '';
        if (!state.tags.length) {
            return;
        }
        state.tags.forEach(function (termId) {
            if (!termMap[termId]) {
                return;
            }
            var button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('data-remove-term', termId);
            button.innerHTML = '<span>' + termMap[termId].name + '</span><span aria-hidden="true">×</span>';
            selectedFiltersWrap.appendChild(button);
        });
    }

    function buildCardMarkup(item) {
        var logo = item.logo ? '<img src="' + item.logo + '" alt="' + escapeHtml(item.name) + '" loading="lazy">'
            : '<span class="sffc-company-card-tile__initials">' + escapeHtml(extractInitials(item.name)) + '</span>';

        var metaPieces = [];
        if (item.headquarters) {
            metaPieces.push(item.headquarters);
        } else if (Array.isArray(item.regions) && item.regions.length) {
            metaPieces.push(item.regions[0]);
        }
        if (Array.isArray(item.sectors) && item.sectors.length) {
            metaPieces.push(item.sectors.slice(0, 2).join(' · '));
        }
        if (item.portfolio_count) {
            metaPieces.push(String(item.portfolio_count) + ' portfolio');
        }

        var tagsMarkup = '';
        if (Array.isArray(item.tags) && item.tags.length) {
            var tags = item.tags.slice(0, 3).map(function (tag) {
                return '<span class="sffc-company-card-tile__tag">' + escapeHtml(tag.name) + '</span>';
            }).join('');
            var extra = item.tags.length > 3 ? '<span class="sffc-company-card-tile__tag sffc-company-card-tile__tag--more">+' + (item.tags.length - 3) + '</span>' : '';
            tagsMarkup = '<div class="sffc-company-card-tile__tags">' + tags + extra + '</div>';
        }

        var excerpt = item.excerpt ? '<p class="sffc-company-card-tile__excerpt">' + escapeHtml(item.excerpt) + '</p>' : '';
        var aum = item.aum ? '<span class="sffc-company-card-tile__aum">' + escapeHtml(item.aum) + ' AUM</span>' : '';
        var meta = '';
        if (metaPieces.length) {
            meta = '<div class="sffc-company-card-tile__meta">' + metaPieces.map(function (piece) {
                return '<span>' + escapeHtml(piece) + '</span>';
            }).join('') + '</div>';
        }

        return '<article class="sffc-company-card-tile" data-company-id="' + item.id + '">' +
            '<a class="sffc-company-card-tile__link" href="' + item.permalink + '">' +
            '<div class="sffc-company-card-tile__thumb">' + logo + '</div>' +
            '<div class="sffc-company-card-tile__body">' +
            '<div class="sffc-company-card-tile__header"><h3>' + escapeHtml(item.name) + '</h3>' + aum + '</div>' +
            excerpt + meta + tagsMarkup +
            '</div></a></article>';
    }

    function escapeHtml(string) {
        if (typeof string !== 'string') {
            string = String(string || '');
        }
        return string.replace(/[&<>'"]/g, function (match) {
            switch (match) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                case "'": return '&#039;';
                default: return match;
            }
        });
    }

    function extractInitials(name) {
        if (typeof name !== 'string' || !name.trim()) {
            return 'PE';
        }
        var parts = name.trim().split(/\s+/);
        var initials = parts.slice(0, 2).map(function (part) {
            return part.charAt(0).toUpperCase();
        }).join('');
        return initials || 'PE';
    }

    function renderResults(response, options) {
        options = options || {};
        var items = Array.isArray(response.items) ? response.items : [];
        var pagination = response.pagination || {};

        if (!resultsWrapper) {
            return;
        }

        var gridHtml = '';
        if (items.length) {
            gridHtml = '<div class="sffc-company-explorer__grid">' + items.map(buildCardMarkup).join('') + '</div>';
        } else {
            gridHtml = '<div class="sffc-company-explorer__empty">' + escapeHtml('No firms match the selected filters yet.') + '</div>';
        }

        var summaryHtml = '';
        if (pagination.total_items) {
            summaryHtml = '<div class="sffc-company-explorer__summary">' + escapeHtml(pagination.total_items) + ' firms • Page ' + escapeHtml(pagination.page || 1) + ' of ' + escapeHtml(pagination.total_pages || 1) + '</div>';
        }

        if (options.append) {
            var grid = resultsWrapper.querySelector('.sffc-company-explorer__grid');
            if (grid && items.length) {
                grid.insertAdjacentHTML('beforeend', items.map(buildCardMarkup).join(''));
            }
            if (summaryHtml) {
                var summaryNode = resultsWrapper.querySelector('.sffc-company-explorer__summary');
                if (summaryNode) {
                    summaryNode.outerHTML = summaryHtml;
                } else {
                    resultsWrapper.insertAdjacentHTML('beforeend', summaryHtml);
                }
            }
        } else {
            resultsWrapper.innerHTML = gridHtml + summaryHtml;
        }

        updateLoadMore(pagination);
    }

    function updateLoadMore(pagination) {
        if (!loadMoreBtn) {
            return;
        }
        if (!pagination || !pagination.total_pages || pagination.page >= pagination.total_pages) {
            loadMoreBtn.hidden = true;
            loadMoreBtn.disabled = true;
            state.page = pagination && pagination.page ? pagination.page : state.page;
            return;
        }
        loadMoreBtn.hidden = false;
        loadMoreBtn.disabled = false;
        state.page = pagination.page || state.page;
    }

    function requestResults(options) {
        options = options || {};
        if (state.isLoading) {
            return;
        }

        var append = options.append === true;
        if (options.resetPage) {
            state.page = 1;
        }

        state.isLoading = true;
        if (loadMoreBtn) {
            loadMoreBtn.disabled = true;
        }

        var form = new window.FormData();
        form.append('action', 'sffc_get_company_cards');
        form.append('nonce', data.nonce);
        form.append('perPage', state.perPage);
        form.append('page', append ? state.page + 1 : state.page);
        form.append('sort', state.sort);
        form.append('search', state.search);
        state.tags.forEach(function (termId) {
            form.append('tags[]', termId);
        });

        window.fetch(data.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: form
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Request failed');
            }
            return response.json();
        }).then(function (payload) {
            if (!payload || payload.success !== true || !payload.data) {
                throw new Error('Unexpected response');
            }
            renderResults(payload.data, { append: append });
        }).catch(function () {
            // Silently fail in UI but reset load button.
        }).finally(function () {
            state.isLoading = false;
            if (loadMoreBtn) {
                loadMoreBtn.disabled = false;
            }
        });
    }

    if (filtersPane) {
        filtersPane.addEventListener('click', function (event) {
            var target = event.target.closest('.sffc-company-filter-option');
            if (target) {
                event.preventDefault();
                toggleFilter(target.getAttribute('data-term-id'));
                return;
            }
            var clear = event.target.closest('.sffc-company-filter-clear');
            if (clear) {
                event.preventDefault();
                clearGroup(clear.getAttribute('data-filter-clear'));
            }
        });
    }

    if (selectedFiltersWrap) {
        selectedFiltersWrap.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-remove-term]');
            if (!button) {
                return;
            }
            event.preventDefault();
            var termId = parseInt(button.getAttribute('data-remove-term'), 10);
            if (!termId) {
                return;
            }
            var idx = state.tags.indexOf(termId);
            if (idx !== -1) {
                state.tags.splice(idx, 1);
                updateFilterButtons();
                updateSelectedFiltersUI();
                requestResults({ resetPage: true });
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function (event) {
            var value = event.target.value || '';
            clearTimeout(debouncedTimeout);
            debouncedTimeout = setTimeout(function () {
                state.search = value.trim();
                requestResults({ resetPage: true });
            }, 280);
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', function (event) {
            state.sort = event.target.value;
            requestResults({ resetPage: true });
        });
    }

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function (event) {
            event.preventDefault();
            requestResults({ append: true });
        });
    }

    updateFilterButtons();
    updateSelectedFiltersUI();
})();
