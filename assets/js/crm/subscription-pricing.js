(function ($) {
  "use strict";

  function updateCycle($root, cycle) {
    $root.attr("data-active-cycle", cycle);

    $root.find("[data-pricing-cycle-toggle]").each(function () {
      var $toggle = $(this);
      var isActive = ($toggle.attr("data-pricing-cycle-toggle") || "") === cycle;
      $toggle.toggleClass("is-active", isActive);
      $toggle.attr("aria-selected", isActive ? "true" : "false");
    });

    $root.find("[data-pricing-grid]").each(function () {
      var $grid = $(this);
      var isActive = ($grid.attr("data-pricing-grid") || "") === cycle;
      $grid.toggleClass("is-active", isActive).prop("hidden", !isActive);
    });
  }

  function syncCheckoutShellState($root, slug) {
    var $shell;
    var submitLabel;

    if (!slug) {
      return;
    }

    $shell = $root.find('[data-pricing-shell="' + slug.replace(/"/g, '\\"') + '"]').first();
    if (!$shell.length) {
      return;
    }

    submitLabel = $.trim($shell.attr("data-pricing-submit-label") || "");
    if (submitLabel) {
      $shell.find(".mepr-submit, .mp-form-submit input[type='submit'], .mp-form-submit button, button[type='submit']").each(function () {
        var $submit = $(this);
        if ($submit.is("input")) {
          $submit.val(submitLabel);
        } else {
          $submit.text(submitLabel);
        }
      });
    }
  }

  function activatePlan($root, $card, shouldScroll) {
    var slug = $.trim($card.attr("data-plan-slug") || "");
    var name = $.trim($card.attr("data-plan-name") || "Membership plan");
    var price = $.trim($card.attr("data-plan-price") || "");
    var copy = $.trim(
      $card.attr("data-plan-copy") || "Secure checkout for your selected plan."
    );
    var cycle = $.trim($card.attr("data-plan-cycle") || "");
    var isCurrentPlan = ($card.attr("data-plan-current") || "") === "1";
    var $checkout = $root.find("[data-pricing-checkout]").first();

    if (cycle) {
      updateCycle($root, cycle);
    }

    $root.find("[data-pricing-card]").removeClass("is-active");
    $card.addClass("is-active");

    $root.find("[data-pricing-shell]").prop("hidden", true);
    if (slug) {
      $root.find('[data-pricing-shell="' + slug.replace(/"/g, '\\"') + '"]').prop("hidden", false);
    }

    $root.find("[data-pricing-summary-title]").text(name);
    $root.find("[data-pricing-summary-copy]").text(copy);
    $root.find("[data-pricing-summary-price]").text(price);
    $checkout.prop("hidden", false);
    syncCheckoutShellState($root, slug);

    $checkout.toggleClass("is-current-plan", isCurrentPlan);

    if (shouldScroll !== false && $checkout.length) {
      window.setTimeout(function () {
        $checkout[0].scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      }, 120);
    }
  }

  function initPricing($root) {
    var defaultCycle = $.trim($root.attr("data-default-cycle") || "monthly");
    var $initialCard;
    updateCycle($root, defaultCycle);

    $initialCard = $root.find('[data-pricing-grid="' + defaultCycle.replace(/"/g, '\\"') + '"] [data-pricing-card].is-active').first();
    if (!$initialCard.length) {
      $initialCard = $root.find('[data-pricing-grid="' + defaultCycle.replace(/"/g, '\\"') + '"] [data-pricing-card]').first();
    }
    if ($initialCard.length) {
      activatePlan($root, $initialCard, false);
    }

    $root.on("click", "[data-pricing-cycle-toggle]", function (event) {
      event.preventDefault();
      var cycle = $.trim($(this).attr("data-pricing-cycle-toggle") || "");
      var $activeGrid;
      var $defaultCard;

      if (!cycle) {
        return;
      }

      updateCycle($root, cycle);
      $activeGrid = $root.find('[data-pricing-grid="' + cycle.replace(/"/g, '\\"') + '"]').first();
      $defaultCard = $activeGrid.find("[data-pricing-card].is-active").first();

      if (!$defaultCard.length) {
        $defaultCard = $activeGrid.find("[data-pricing-card]").first();
      }

      if ($defaultCard.length) {
        $activeGrid.find("[data-pricing-card]").removeClass("is-active");
        $defaultCard.addClass("is-active");
        activatePlan($root, $defaultCard, false);
      }
    });

    $root.on("click", "[data-pricing-select]", function (event) {
      event.preventDefault();
      activatePlan($root, $(this).closest("[data-pricing-card]"), true);
    });
  }

  $(function () {
    $("[data-subscription-pricing]").each(function () {
      initPricing($(this));
    });
  });
})(jQuery);
