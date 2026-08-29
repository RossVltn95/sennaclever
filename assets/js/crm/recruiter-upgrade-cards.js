(function ($) {
  "use strict";

  function selectPlan($wrap, slug) {
    if (!slug) {
      return;
    }

    var $card = $wrap.find('[data-upgrade-plan-card][data-plan-slug="' + slug + '"]');
    if (!$card.length || $card.is("[hidden]")) {
      return;
    }

    $wrap.attr("data-selected-plan", slug);
    $wrap.find("[data-upgrade-plan-card]").removeClass("is-selected");
    $card.addClass("is-selected");

    $wrap.find("[data-upgrade-form]").removeClass("is-active").attr("hidden", "hidden");
    $wrap
      .find('[data-upgrade-form="' + slug + '"]')
      .addClass("is-active")
      .removeAttr("hidden");

    $wrap.find("[data-upgrade-checkout-title]").text($.trim($card.find("h3").first().text()));
  }

  function setCycle($wrap, cycle) {
    if (!cycle) {
      return;
    }

    $wrap.find("[data-upgrade-cycle]").removeClass("is-active").attr("aria-pressed", "false");
    $wrap
      .find('[data-upgrade-cycle="' + cycle + '"]')
      .addClass("is-active")
      .attr("aria-pressed", "true");

    $wrap.find("[data-upgrade-plan-card]").attr("hidden", "hidden");
    var $cycleCards = $wrap.find('[data-upgrade-plan-card][data-plan-cycle="' + cycle + '"]');
    $cycleCards.removeAttr("hidden");

    var currentSlug = $wrap.attr("data-selected-plan");
    var $current = $cycleCards.filter('[data-plan-slug="' + currentSlug + '"]');
    var nextSlug = $current.length
      ? currentSlug
      : ($cycleCards.filter(".is-featured").first().data("plan-slug") ||
          $cycleCards.first().data("plan-slug"));

    selectPlan($wrap, nextSlug);
  }

  $(function () {
    $("[data-recruiter-upgrade-cards]").each(function () {
      var $wrap = $(this);
      setCycle($wrap, $wrap.attr("data-default-cycle") || "monthly");
    });
  });

  $(document).on("click", "[data-upgrade-cycle]", function () {
    var $button = $(this);
    setCycle($button.closest("[data-recruiter-upgrade-cards]"), $button.data("upgrade-cycle"));
  });

  $(document).on("click", "[data-upgrade-select]", function () {
    var $button = $(this);
    var $wrap = $button.closest("[data-recruiter-upgrade-cards]");
    selectPlan($wrap, $button.data("upgrade-select"));

    var $checkout = $wrap.find("[data-upgrade-checkout]").first();
    if ($checkout.length && $checkout[0].scrollIntoView) {
      $checkout[0].scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
  });
})(jQuery);
