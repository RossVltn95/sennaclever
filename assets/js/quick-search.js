(function ($) {
  "use strict";

  function QuickSearch($root) {
    this.$root = $root;
    this.$input = $root.find(".sffc-quick-search__input");
    this.$dropdown = $root.find(".sffc-quick-search__dropdown");
    this.$list = $root.find(".sffc-quick-search__dropdown-list");
    this.$clear = $root.find(".sffc-quick-search__clear");
    this.$modes = $root.find(".sffc-quick-search__mode");
    this.$submit = $root.find(".sffc-quick-search__submit");
    this.routes = this.readRoutes();
    this.mode = "case-studies";
    this.items = [];
    this.selectedIndex = -1;
    this.requestTimer = null;
    this.pendingRequest = null;

    this.bind();
  }

  QuickSearch.prototype.readRoutes = function () {
    var raw = this.$root.attr("data-routes") || "{}";
    try {
      return JSON.parse(raw);
    } catch (error) {
      return {};
    }
  };

  QuickSearch.prototype.isMobileGlobalMode = function () {
    return window.matchMedia("(max-width: 768px)").matches;
  };

  QuickSearch.prototype.getEffectiveMode = function () {
    return this.isMobileGlobalMode() ? "global" : this.mode;
  };

  QuickSearch.prototype.bind = function () {
    var self = this;

    this.$modes.on("click", function () {
      self.setMode($(this).data("mode") || "case-studies");
    });

    this.$input.on("input", function () {
      var value = self.$input.val().trim();
      self.$clear.prop("hidden", value.length === 0);
      clearTimeout(self.requestTimer);
      self.requestTimer = setTimeout(function () {
        self.fetchSuggestions();
      }, 180);
    });

    this.$input.on("focus", function () {
      self.fetchSuggestions();
    });

    this.$input.on("click", function () {
      self.fetchSuggestions();
    });

    this.$input.on("keydown", function (event) {
      if (event.key === "ArrowDown") {
        event.preventDefault();
        self.moveSelection(1);
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        self.moveSelection(-1);
      } else if (event.key === "Enter") {
        event.preventDefault();
        self.submit();
      } else if (event.key === "Escape") {
        self.hideDropdown();
      }
    });

    this.$clear.on("click", function () {
      self.$input.val("").trigger("focus");
      self.$clear.prop("hidden", true);
      self.items = [];
      self.hideDropdown();
    });

    this.$list.on("mousedown", ".sffc-quick-search__item", function (event) {
      event.preventDefault();
      var index = parseInt($(this).attr("data-index"), 10);
      if (!isNaN(index)) {
        self.openItem(index);
      }
    });

    this.$submit.on("click", function () {
      self.submit();
    });

    $(document).on("click", function (event) {
      if (!$(event.target).closest(".sffc-quick-search__bar").length) {
        self.hideDropdown();
      }
    });
  };

  QuickSearch.prototype.setMode = function (mode) {
    this.mode = mode;
    this.$modes.removeClass("is-active");
    this.$modes.filter('[data-mode="' + mode + '"]').addClass("is-active");
    this.items = [];
    this.selectedIndex = -1;
    this.fetchSuggestions();
  };

  QuickSearch.prototype.fetchSuggestions = function () {
    var self = this;

    if (this.pendingRequest && this.pendingRequest.readyState !== 4) {
      this.pendingRequest.abort();
    }

    this.pendingRequest = $.ajax({
      url: sffcQuickSearch.ajaxUrl,
      type: "POST",
      dataType: "json",
      data: {
        action: "sffc_quick_search_suggestions",
        nonce: sffcQuickSearch.nonce,
        mode: this.getEffectiveMode(),
        q: this.$input.val().trim(),
        limit: this.$input.val().trim().length ? 6 : 3,
      },
      beforeSend: function () {
        self.renderLoading();
      },
    })
      .done(function (response) {
        self.items =
          response && response.success && response.data && response.data.items
            ? response.data.items
            : [];
        self.selectedIndex = -1;
        self.renderItems();
      })
      .fail(function () {
        self.items = [];
        self.renderEmpty();
      });
  };

  QuickSearch.prototype.renderLoading = function () {
    this.$list.html(
      '<div class="sffc-quick-search__empty">' +
        this.escapeHtml(sffcQuickSearch.strings.loading || "Searching...") +
        "</div>"
    );
    this.showDropdown();
  };

  QuickSearch.prototype.renderEmpty = function () {
    this.$list.html(
      '<div class="sffc-quick-search__empty">' +
        this.escapeHtml(
          sffcQuickSearch.strings.empty ||
            "No matches yet. Press enter to open the best matching section."
        ) +
        "</div>"
    );
    this.showDropdown();
  };

  QuickSearch.prototype.renderItems = function () {
    if (!this.items.length) {
      this.renderEmpty();
      return;
    }

    var html = "";
    for (var i = 0; i < this.items.length; i++) {
      var item = this.items[i];
      var thumb = item.thumb
        ? '<img src="' + this.escapeAttribute(item.thumb) + '" alt="">'
        : "<span>" + this.escapeHtml((item.badge || "S").charAt(0)) + "</span>";

      html +=
        '<button type="button" class="sffc-quick-search__item" data-index="' +
        i +
        '">' +
        '<span class="sffc-quick-search__item-thumb">' +
        thumb +
        "</span>" +
        '<span class="sffc-quick-search__item-copy">' +
        '<strong class="sffc-quick-search__item-title">' +
        this.escapeHtml(item.title || "") +
        "</strong>" +
        '<span class="sffc-quick-search__item-subtitle">' +
        this.escapeHtml(item.subtitle || item.badge || "") +
        "</span>" +
        "</span>" +
        "</button>";
    }

    this.$list.html(html);
    this.showDropdown();
  };

  QuickSearch.prototype.moveSelection = function (direction) {
    if (!this.items.length) {
      return;
    }

    this.selectedIndex += direction;
    if (this.selectedIndex < 0) {
      this.selectedIndex = this.items.length - 1;
    }
    if (this.selectedIndex >= this.items.length) {
      this.selectedIndex = 0;
    }

    this.$list
      .find(".sffc-quick-search__item")
      .removeClass("is-selected")
      .eq(this.selectedIndex)
      .addClass("is-selected");
  };

  QuickSearch.prototype.submit = function () {
    if (this.selectedIndex >= 0 && this.items[this.selectedIndex]) {
      this.openItem(this.selectedIndex);
      return;
    }

    if (this.items.length) {
      this.openItem(0);
      return;
    }

    window.location.href = this.buildFallbackUrl();
  };

  QuickSearch.prototype.openItem = function (index) {
    var item = this.items[index];
    if (!sffcQuickSearch.isLoggedIn) {
      window.location.href = sffcQuickSearch.membershipUrl || "/memberships/";
      return;
    }

    if (!item || !item.url) {
      window.location.href = this.buildFallbackUrl();
      return;
    }

    window.location.href = item.url;
  };

  QuickSearch.prototype.buildFallbackUrl = function () {
    var query = this.$input.val().trim();
    var effectiveMode = this.getEffectiveMode();
    var baseUrl =
      effectiveMode === "global"
        ? this.routes.careers || "/"
        : this.routes[this.mode] || "/";
    var url = new URL(baseUrl, window.location.origin);

    if (query) {
      if (effectiveMode === "global") {
        url.searchParams.set("quick_search", query);
      } else if (this.mode === "case-studies") {
        url.searchParams.set("resource_search", query);
      } else {
        url.searchParams.set("quick_search", query);
      }
    }

    return url.toString();
  };

  QuickSearch.prototype.showDropdown = function () {
    this.$dropdown.prop("hidden", false);
  };

  QuickSearch.prototype.hideDropdown = function () {
    this.$dropdown.prop("hidden", true);
  };

  QuickSearch.prototype.escapeHtml = function (value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  };

  QuickSearch.prototype.escapeAttribute = function (value) {
    return this.escapeHtml(value);
  };

  $(function () {
    $(".sffc-quick-search").each(function () {
      new QuickSearch($(this));
    });
  });
})(jQuery);
