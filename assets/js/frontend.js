(function () {
    'use strict';

    function initSliders() {
        document.querySelectorAll('[data-dsc-slider]').forEach(function (slider) {
            var viewport = slider.querySelector('[data-dsc-viewport]');
            var track = slider.querySelector('[data-dsc-track]');
            var previous = slider.querySelector('[data-dsc-prev]');
            var next = slider.querySelector('[data-dsc-next]');
            var cards = track ? Array.prototype.slice.call(track.children) : [];
            var index = 0;
            var startX = null;

            if (!viewport || !track || !cards.length) {
                return;
            }

            function visibleCards() {
                if (window.matchMedia('(max-width: 640px)').matches) {
                    return 1;
                }
                if (window.matchMedia('(max-width: 1024px)').matches) {
                    return 2;
                }
                return 3;
            }

            function update() {
                var visible = visibleCards();
                var maxIndex = Math.max(0, cards.length - visible);
                var cardWidth;
                var gap = parseFloat(window.getComputedStyle(track).gap) || 0;

                index = Math.min(Math.max(index, 0), maxIndex);
                cardWidth = cards[0].getBoundingClientRect().width;
                track.style.transform = 'translate3d(' + (-index * (cardWidth + gap)) + 'px,0,0)';
                previous.disabled = index === 0;
                next.disabled = index === maxIndex;
            }

            previous.addEventListener('click', function () {
                index -= visibleCards();
                update();
            });
            next.addEventListener('click', function () {
                index += visibleCards();
                update();
            });
            viewport.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                    event.preventDefault();
                    index += event.key === 'ArrowRight' ? 1 : -1;
                    update();
                }
            });
            viewport.addEventListener('pointerdown', function (event) {
                startX = event.clientX;
            });
            viewport.addEventListener('pointerup', function (event) {
                if (startX === null || Math.abs(event.clientX - startX) < 40) {
                    startX = null;
                    return;
                }
                index += event.clientX < startX ? 1 : -1;
                startX = null;
                update();
            });
            window.addEventListener('resize', update, { passive: true });
            update();
        });
    }

    function initSearch() {
        var config = window.directoristShoppingCentres || {};
        var minimum = Number(config.minChars || 3);
        var inputs = document.querySelectorAll('.directorist-search-form input[name="q"], .directorist-search-form input[name="search_q"], .directorist-search-form input[type="search"]');

        inputs.forEach(function (input, inputIndex) {
            var timer;
            var request;
            var current = -1;
            var wrap = input.parentElement;
            var list = document.createElement('div');
            var listId = 'dsc-search-suggestions-' + inputIndex;

            if (!wrap || input.dataset.dscSearchReady) {
                return;
            }
            input.dataset.dscSearchReady = '1';
            wrap.classList.add('dsc-search-wrap');
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('aria-autocomplete', 'list');
            input.setAttribute('aria-controls', listId);
            input.setAttribute('aria-expanded', 'false');
            list.id = listId;
            list.className = 'dsc-search-suggestions';
            list.setAttribute('role', 'listbox');
            list.hidden = true;
            wrap.appendChild(list);

            function close() {
                list.hidden = true;
                list.innerHTML = '';
                input.setAttribute('aria-expanded', 'false');
                input.removeAttribute('aria-activedescendant');
                current = -1;
            }

            function options() {
                return Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
            }

            function selectIndex(nextIndex) {
                var items = options();
                if (!items.length) {
                    return;
                }
                current = (nextIndex + items.length) % items.length;
                items.forEach(function (item, index) {
                    item.setAttribute('aria-selected', index === current ? 'true' : 'false');
                });
                input.setAttribute('aria-activedescendant', items[current].id);
                items[current].scrollIntoView({ block: 'nearest' });
            }

            function render(results) {
                if (!results.length) {
                    close();
                    return;
                }
                list.innerHTML = '<p class="dsc-search-suggestions__label">' + String(config.groupName || 'Shopping Centres') + '</p>';
                results.forEach(function (centre, index) {
                    var option = document.createElement('a');
                    var image = centre.image ? '<img src="' + centre.image + '" alt="">' : '<span class="dsc-search-option__fallback"></span>';
                    option.id = listId + '-option-' + index;
                    option.className = 'dsc-search-option';
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.href = centre.url;
                    option.innerHTML = image + '<span><strong></strong><small></small></span><span class="dsc-search-option__count"></span>';
                    option.querySelector('strong').textContent = centre.name;
                    option.querySelector('small').textContent = centre.address || '';
                    option.querySelector('.dsc-search-option__count').textContent = centre.deal_count + (centre.deal_count === 1 ? ' deal' : ' deals');
                    list.appendChild(option);
                });
                list.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                current = -1;
            }

            input.addEventListener('input', function () {
                var query = input.value.trim();
                window.clearTimeout(timer);
                if (request && request.abort) {
                    request.abort();
                }
                if (query.length < minimum) {
                    close();
                    return;
                }
                timer = window.setTimeout(function () {
                    request = new AbortController();
                    window.fetch(config.restUrl + '?q=' + encodeURIComponent(query) + '&limit=8', { signal: request.signal, credentials: 'same-origin' })
                        .then(function (response) { return response.ok ? response.json() : []; })
                        .then(render)
                        .catch(function (error) { if (error.name !== 'AbortError') { close(); } });
                }, 180);
            });

            input.addEventListener('keydown', function (event) {
                if (list.hidden) {
                    return;
                }
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    selectIndex(current + (event.key === 'ArrowDown' ? 1 : -1));
                } else if (event.key === 'Enter' && current >= 0) {
                    event.preventDefault();
                    window.location.href = options()[current].href;
                } else if (event.key === 'Escape') {
                    close();
                }
            });
            document.addEventListener('click', function (event) {
                if (!wrap.contains(event.target)) {
                    close();
                }
            });
        });
    }

    function respectReducedMotionForElementor() {
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        document.querySelectorAll('.iso-homepage-banner-section .swiper').forEach(function (swiperElement) {
            if (swiperElement.swiper && swiperElement.swiper.autoplay) {
                swiperElement.swiper.autoplay.stop();
            }
        });
    }

    function initElementorBannerBehavior() {
        document.querySelectorAll('.iso-homepage-banner-section .swiper').forEach(function (swiperElement) {
            var swiper = swiperElement.swiper;
            if (!swiper || !swiper.autoplay || swiperElement.dataset.dscBannerReady) {
                return;
            }
            swiperElement.dataset.dscBannerReady = '1';
            swiperElement.addEventListener('focusin', function () { swiper.autoplay.stop(); });
            swiperElement.addEventListener('focusout', function () {
                if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    swiper.autoplay.start();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSliders();
        initSearch();
        respectReducedMotionForElementor();
        initElementorBannerBehavior();
        window.setTimeout(respectReducedMotionForElementor, 1000);
        window.setTimeout(initElementorBannerBehavior, 1000);
    });
}());
