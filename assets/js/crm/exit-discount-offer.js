(function ($) {
    'use strict';

    $(function () {
        var $offer = $('[data-sffc-exit-discount-offer]').first();
        if (!$offer.length) {
            return;
        }

        var config = window.sffcExitDiscountOffer || {};
        var storageKey = config.storageKey || 'sffc_exit_discount_offer_seen';
        var shownInMemory = false;

        function hasShown() {
            if (shownInMemory) {
                return true;
            }

            try {
                return window.sessionStorage.getItem(storageKey) === '1';
            } catch (err) {
                return shownInMemory;
            }
        }

        function rememberShown() {
            shownInMemory = true;

            try {
                window.sessionStorage.setItem(storageKey, '1');
            } catch (err) {
                // Ignore storage failures.
            }
        }

        function showOffer() {
            if (hasShown()) {
                return;
            }

            rememberShown();
            $offer.addClass('is-visible').attr('aria-hidden', 'false');
        }

        function hideOffer() {
            $offer.removeClass('is-visible').attr('aria-hidden', 'true');
        }

        $(document).on('mouseleave.sffcExitDiscountOffer', function (event) {
            if (event.clientY <= 0) {
                showOffer();
            }
        });

        $offer.on('click', '[data-action="dismiss-exit-discount"]', function () {
            hideOffer();
        });
    });
})(jQuery);
