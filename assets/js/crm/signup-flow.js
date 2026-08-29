(function($) {
    'use strict';

    $(function() {
        var defaultMembershipUrl = (window.sffcSignupFlow && window.sffcSignupFlow.membershipUrl) || 'https://joinsenna.com/memberships/';
        var DISCOUNT_CODE = 'MEMBER15';

        var state = {
            step: 1,
            name: '',
            connectors: [],
            location: '',
            sectors: [],
            needs: [],
            planUrl: defaultMembershipUrl
        };

        var $flow = $('.sffc-signup-flow');
        if (!$flow.length) {
            return;
        }

        var $card = $flow.find('.sffc-signup-flow__card');
        var $navSegments = $flow.find('.sffc-signup-flow__nav-segment');
        var $typing = $('#sffc-signup-typing');
        var stepMessages = {
            1: "Let's get started.",
            2: "Great to meet you, {name}. Who should we connect you with?",
            3: "Which market should we focus on?",
            4: "Let's lock in the right sectors for you, {name}.",
            5: "Tell us what you need most right now.",
            6: "Here’s the account tailored for you, {name}."
        };

        function updateNav(step) {
            $navSegments.each(function(index) {
                var $seg = $(this);
                var segStep = index + 1;
                $seg.removeClass('is-active is-complete');
                if (segStep < step) {
                    $seg.addClass('is-complete');
                } else if (segStep === step) {
                    $seg.addClass('is-active');
                }
            });
        }

        function typeText($el, text) {
            if (!$el.length) {
                return;
            }
            var current = '';
            var i = 0;
            clearInterval($el.data('typingTimer'));
            $el.text('');
            var timer = setInterval(function() {
                current += text.charAt(i);
                $el.text(current);
                i += 1;
                if (i >= text.length) {
                    clearInterval(timer);
                }
            }, 40);
            $el.data('typingTimer', timer);
        }

        var lastTypingMessage = '';

        function updateSummaries() {
            var name = state.name || 'there';
            $card.find('.sffc-signup-dynamic-name').text(name);
            $card.find('.sffc-signup-summary-location').text(state.location || 'your target markets');
            if (state.sectors.length) {
                $card.find('.sffc-signup-summary-sectors').text(state.sectors.join(', '));
            }
        }

        function updateTyping() {
            var template = stepMessages[state.step] || stepMessages[1];
            var message = template.replace('{name}', state.name || 'there');
            if (message === lastTypingMessage) {
                return;
            }
            lastTypingMessage = message;
            typeText($typing, message);
        }

        function showStep(step) {
            state.step = step;
            $card.find('.sffc-signup-flow__step').removeClass('is-active');
            $card.find('.sffc-signup-flow__step[data-step="' + step + '"]').addClass('is-active');
            updateNav(step);
            updateSummaries();
            updateTyping();
            if (step === 6) {
                renderPlans();
            }
        }

    function toggleOption($option, multi) {
        var value = $option.data('value');
        var field = $option.closest('.sffc-signup-flow__choices').data('field');
        if (!field) {
            return;
        }

        if (multi) {
            $option.toggleClass('is-selected');
            var values = $option.closest('.sffc-signup-flow__choices').find('.is-selected').map(function() {
                return $(this).data('value');
            }).get();
            state[field] = values;
        } else {
            $option.closest('.sffc-signup-flow__choices').find('.sffc-signup-option').removeClass('is-selected');
            $option.addClass('is-selected');
            state[field] = value;
        }
        updateSummaries();
    }

    $('#sffc-signup-name-form').on('submit', function(e) {
        e.preventDefault();
        var name = $.trim($('#sffc-signup-name').val());
        if (!name) {
            return;
        }
        state.name = name;
        updateSummaries();
        showStep(2);
    });

    $card.on('click', '.sffc-signup-option', function() {
        var $btn = $(this);
        var field = $btn.closest('.sffc-signup-flow__choices').data('field');
        var multi = field === 'connectors' || field === 'sectors' || field === 'needs';
        toggleOption($btn, multi);
    });

    $card.on('click', '.sffc-signup-flow__btn[data-next]', function() {
        var next = parseInt($(this).data('next'), 10) || state.step + 1;
        showStep(next);
    });

    $('#sffc-signup-restart').on('click', function() {
        state = { step: 1, name: '', connectors: [], location: '', sectors: [], needs: [], planUrl: defaultMembershipUrl };
        lastTypingMessage = '';
        $card.find('.sffc-signup-option').removeClass('is-selected');
        $('#sffc-signup-name').val('');
        updateSummaries();
        showStep(1);
    });

    function getPlanPriceLabel(plan) {
        if (plan.price) {
            return plan.price;
        }
        if (plan.price_amount && plan.price_currency) {
            return plan.price_currency + ' ' + plan.price_amount + (plan.billing ? ' / ' + plan.billing : '');
        }
        return '';
    }

    function selectRecommendedPlan(plans) {
        if (!plans.length) {
            return null;
        }
        var premiumNeed = state.needs.indexOf('Get introduced to recruiters/hiring managers') !== -1;
        var wantsRecruiters = state.connectors.indexOf('Hiring Managers') !== -1 || state.connectors.indexOf('Both') !== -1;
        var featured = plans.find(function(plan) {
            return plan.featured || (plan.slug && plan.slug.indexOf('annual') !== -1);
        });
        if ((premiumNeed || wantsRecruiters) && featured) {
            return featured;
        }
        var connectNeed = state.needs.indexOf('Connect with recruiters hiring') !== -1;
        if (connectNeed && featured) {
            return featured;
        }
        return plans[0];
    }

    function renderPlans() {
        var plans = (window.sffcSignupFlow && window.sffcSignupFlow.plans) || [];
        var fallbackUrl = defaultMembershipUrl;
        var $container = $('#sffc-signup-flow-plans');
        if (!$container.length) {
            return;
        }

        if (!plans.length) {
            $container.html('<div class="sffc-signup-flow__plan-empty">Membership options will be shared shortly.</div>');
            return;
        }

        var plan = selectRecommendedPlan(plans);
        if (!plan) {
            $container.html('<div class="sffc-signup-flow__plan-empty">Membership options will be shared shortly.</div>');
            return;
        }

        var classes = ['sffc-signup-plan'];
        if (plan.featured) {
            classes.push('is-featured');
        }
        var feats = '';
        if (plan.features && plan.features.length) {
            feats = '<ul>' + plan.features.map(function(f) {
                return '<li>' + $('<div>').text(f).html() + '</li>';
            }).join('') + '</ul>';
        }
        var target = plan.url || fallbackUrl;
        state.planUrl = target;
        var price = getPlanPriceLabel(plan);
        var html = '<article class="' + classes.join(' ') + '">' +
            '<h4>' + (plan.name || 'Membership') + '</h4>' +
            (price ? '<div class="sffc-signup-plan__price">' + price + '</div>' : '') +
            (plan.billing ? '<p class="sffc-signup-plan__meta">' + plan.billing + '</p>' : '') +
            (plan.tagline ? '<p>' + plan.tagline + '</p>' : '') +
            feats +
            '<a href="' + target + '" class="sffc-signup-flow__btn sffc-signup-plan__cta" target="_blank" rel="noopener noreferrer">Activate plan</a>' +
        '</article>';

        html += '<p class="sffc-signup-plan-note">Need other options? <a href="' + fallbackUrl + '" target="_blank" rel="noopener noreferrer">View all plans</a></p>';

        $container.html(html);
        updateDiscountCta();
    }

    var discountShown = false;
    var $discount = $('.sffc-signup-discount');

    function buildCouponUrl(baseUrl) {
        var target = baseUrl || state.planUrl || defaultMembershipUrl;
        try {
            var url = new URL(target);
            url.searchParams.set('coupon', DISCOUNT_CODE);
            return url.toString();
        } catch (err) {
            var separator = target.indexOf('?') === -1 ? '?' : '&';
            return target + separator + 'coupon=' + encodeURIComponent(DISCOUNT_CODE);
        }
    }

    function updateDiscountCta() {
        if (!$discount.length) {
            return;
        }
        var $cta = $discount.find('.sffc-signup-flow__btn');
        if ($cta.length) {
            $cta.attr('href', buildCouponUrl(state.planUrl));
        }
    }

    function showDiscount() {
        if (discountShown || !$discount.length) {
            return;
        }
        discountShown = true;
        $discount.addClass('is-visible').attr('aria-hidden', 'false');
    }

    function hideDiscount() {
        if (!$discount.length) {
            return;
        }
        $discount.removeClass('is-visible').attr('aria-hidden', 'true');
    }

    $(document).on('mouseleave', function(e) {
        if (e.clientY <= 0) {
            showDiscount();
        }
    });

    $discount.on('click', '.sffc-signup-discount__close, .sffc-signup-discount__overlay', function() {
        hideDiscount();
    });

        renderPlans();
        updateSummaries();
        showStep(1);
        updateDiscountCta();
    });
})(jQuery);
