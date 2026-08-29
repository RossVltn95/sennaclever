(function ($) {
  "use strict";

  function updateSummary($root, $card) {
    var title = $.trim($card.attr("data-plan-name") || "Membership plan");
    var copy = $.trim(
      $card.attr("data-plan-copy") || "Choose a plan to continue."
    );
    var price = $.trim($card.attr("data-plan-price") || "TBC");
    var billing = $.trim(
      $card.attr("data-plan-billing") || "Secure recurring membership"
    );
    var tagline = $.trim($card.attr("data-plan-tagline") || "Selected plan");

    $root.find("[data-plan-summary-title]").text(title);
    $root.find("[data-plan-summary-copy]").text(copy);
    $root.find("[data-plan-summary-checkout-title]").text(title);
    $root.find("[data-plan-summary-price]").text(price);
    $root.find("[data-plan-summary-total]").text(price);
    $root.find("[data-plan-summary-checkout-billing]").text(billing);
    $root.find("[data-plan-summary-checkout-tagline]").text(tagline);
  }

  function activatePlan($root, $card) {
    var slug = $.trim($card.attr("data-plan-slug") || "");
    var $checkoutPane = $root.find(
      ".sffc-subscription-onboarding__checkout-pane"
    ).first();

    $root.find("[data-plan-card]").removeClass("is-active");
    $root
      .find(".sffc-subscription-onboarding__plan-panel")
      .prop("hidden", true);
    $card.addClass("is-active");
    $card
      .find(".sffc-subscription-onboarding__plan-panel")
      .prop("hidden", false);

    $root
      .find("[data-plan-shell]")
      .prop("hidden", true)
      .removeClass("is-active");
    if (slug) {
      $root
        .find('[data-plan-shell="' + slug.replace(/"/g, '\\"') + '"]')
        .prop("hidden", false)
        .addClass("is-active");
    }

    updateSummary($root, $card);

    if (
      $checkoutPane.length &&
      window.matchMedia &&
      window.matchMedia("(max-width: 1080px)").matches
    ) {
      window.setTimeout(function () {
        $checkoutPane[0].scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      }, 120);
    }
  }

  function initSubscriptionOnboarding($root) {
    var $cards = $root.find("[data-plan-card]");
    if (!$cards.length) {
      return;
    }

    $root.on("click", "[data-plan-toggle]", function (event) {
      event.preventDefault();
      activatePlan($root, $(this).closest("[data-plan-card]"));
    });

    activatePlan(
      $root,
      $cards.filter(".is-active").first().length
        ? $cards.filter(".is-active").first()
        : $cards.first()
    );
  }

  $(function () {
    $("[data-subscription-onboarding]").each(function () {
      initSubscriptionOnboarding($(this));
    });
  });
})(jQuery);
