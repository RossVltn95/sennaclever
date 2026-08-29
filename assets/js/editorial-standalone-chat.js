(function () {
  "use strict";

  function getConfig(root) {
    var id = root.getAttribute("data-instance-id") || "";
    var registry = window.SFFCEditorialStandaloneChat || {};
    return (id && registry[id]) || {};
  }

  function getSessionToken(config) {
    var key = config.sessionKey || "sffc_editorial_standalone_live_chat_session";
    try {
      var existing = window.localStorage.getItem(key);
      if (existing) {
        return existing;
      }
      var created =
        "esc_" +
        Date.now().toString(36) +
        "_" +
        Math.random().toString(36).slice(2, 12);
      window.localStorage.setItem(key, created);
      return created;
    } catch (error) {
      return "esc_" + Date.now().toString(36) + "_" + Math.random().toString(36).slice(2, 10);
    }
  }

  function postForm(config, data) {
    data.append("nonce", config.nonce || "");
    return window
      .fetch(config.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: data,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error(
            (payload && payload.data && payload.data.message) ||
              "Unable to complete that request."
          );
        }
        return payload.data || {};
      });
  }

  function appendMessage(state, body, speaker, fromName) {
    if (!state.messages || !body) {
      return;
    }
    var article = document.createElement("article");
    article.className =
      "sffc-editorial-standalone-chat__message sffc-editorial-standalone-chat__message--" +
      (speaker === "user" ? "user" : "assistant");

    if (speaker !== "user" && fromName) {
      var meta = document.createElement("div");
      meta.className = "sffc-editorial-standalone-chat__message-meta";
      meta.textContent = fromName;
      article.appendChild(meta);
    }

    var bubble = document.createElement("div");
    bubble.className = "sffc-editorial-standalone-chat__bubble";
    bubble.textContent = body;
    article.appendChild(bubble);
    state.messages.appendChild(article);
    state.messages.scrollTop = state.messages.scrollHeight;
  }

  function appendTyping(state) {
    if (!state.messages) {
      return null;
    }
    var article = document.createElement("article");
    article.className =
      "sffc-editorial-standalone-chat__message sffc-editorial-standalone-chat__message--assistant is-typing";

    var meta = document.createElement("div");
    meta.className = "sffc-editorial-standalone-chat__message-meta";
    meta.textContent = "Emily";
    article.appendChild(meta);

    var bubble = document.createElement("div");
    bubble.className = "sffc-editorial-standalone-chat__bubble";
    bubble.innerHTML = '<span class="sffc-editorial-standalone-chat__typing"><span></span><span></span><span></span></span>';
    article.appendChild(bubble);
    state.messages.appendChild(article);
    state.messages.scrollTop = state.messages.scrollHeight;
    return article;
  }

  function queueEmily(state, body, delay) {
    var typing = appendTyping(state);
    window.setTimeout(function () {
      if (typing && typing.parentNode) {
        typing.parentNode.removeChild(typing);
      }
      appendMessage(state, body, "assistant", "Emily");
    }, delay || 700);
  }

  function setNotice(state, message, type) {
    if (!state.notice) {
      return;
    }
    state.notice.hidden = !message;
    state.notice.textContent = message || "";
    state.notice.classList.toggle("is-error", type === "error");
    state.notice.classList.toggle("is-success", type === "success");
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function formatFileSize(file) {
    var size = file && typeof file.size === "number" ? file.size : 0;
    if (size >= 1024 * 1024) {
      return (size / (1024 * 1024)).toFixed(size >= 10 * 1024 * 1024 ? 0 : 1) + " MB";
    }
    if (size >= 1024) {
      return Math.max(1, Math.round(size / 1024)) + " KB";
    }
    return Math.max(1, size) + " B";
  }

  function clearPreviewObjectUrls(state) {
    (state.previewObjectUrls || []).forEach(function (url) {
      try {
        window.URL.revokeObjectURL(url);
      } catch (error) {}
    });
    state.previewObjectUrls = [];
  }

  function renderFilePreviewCard(state, file, options) {
    options = options || {};
    if (!state.attachmentPreview || !file) {
      return;
    }

    var fileName = escapeHtml(file.name || "Uploaded CV");
    var meta = escapeHtml(formatFileSize(file) + (options.label ? " · " + options.label : ""));
    var type = String(file.type || "").toLowerCase();
    var extension = String(file.name || "").split(".").pop().toLowerCase();
    var cardClass = "sffc-editorial-standalone-chat__upload-preview-card";

    clearPreviewObjectUrls(state);

    if (type === "application/pdf" || extension === "pdf") {
      var pdfUrl = window.URL.createObjectURL(file);
      state.previewObjectUrls.push(pdfUrl);
      state.attachmentPreview.innerHTML =
        '<div class="' + cardClass + '">' +
        '<div class="sffc-editorial-standalone-chat__upload-preview-sheet">' +
        '<object class="sffc-editorial-standalone-chat__upload-preview-object" data="' + escapeHtml(pdfUrl) + '" type="application/pdf">' +
        '<a href="' + escapeHtml(pdfUrl) + '" target="_blank" rel="noopener noreferrer">Open CV preview</a>' +
        "</object>" +
        "</div>" +
        '<div class="sffc-editorial-standalone-chat__upload-preview-meta"><strong>' + fileName + '</strong><span>' + meta + "</span></div>" +
        "</div>";
      return;
    }

    if (type.indexOf("image/") === 0) {
      var imageUrl = window.URL.createObjectURL(file);
      state.previewObjectUrls.push(imageUrl);
      state.attachmentPreview.innerHTML =
        '<div class="' + cardClass + '">' +
        '<div class="sffc-editorial-standalone-chat__upload-preview-sheet">' +
        '<img class="sffc-editorial-standalone-chat__upload-preview-image" src="' + escapeHtml(imageUrl) + '" alt="CV preview">' +
        "</div>" +
        '<div class="sffc-editorial-standalone-chat__upload-preview-meta"><strong>' + fileName + '</strong><span>' + meta + "</span></div>" +
        "</div>";
      return;
    }

    if (type.indexOf("text/") === 0 || extension === "txt") {
      var reader = new FileReader();
      state.attachmentPreview.innerHTML =
        '<div class="' + cardClass + '">' +
        '<div class="sffc-editorial-standalone-chat__upload-preview-sheet is-loading">Loading preview...</div>' +
        '<div class="sffc-editorial-standalone-chat__upload-preview-meta"><strong>' + fileName + '</strong><span>' + meta + "</span></div>" +
        "</div>";
      reader.onload = function () {
        state.attachmentPreview.innerHTML =
          '<div class="' + cardClass + '">' +
          '<div class="sffc-editorial-standalone-chat__upload-preview-sheet">' +
          "<pre>" + escapeHtml(String(reader.result || "").slice(0, 4000)) + "</pre>" +
          "</div>" +
          '<div class="sffc-editorial-standalone-chat__upload-preview-meta"><strong>' + fileName + '</strong><span>' + meta + "</span></div>" +
          "</div>";
      };
      reader.onerror = function () {
        state.attachmentPreview.innerHTML =
          '<div class="' + cardClass + ' is-file-only">' +
          '<div class="sffc-editorial-standalone-chat__upload-preview-meta"><strong>' + fileName + '</strong><span>' + meta + "</span></div>" +
          "</div>";
      };
      reader.readAsText(file);
      return;
    }

    state.attachmentPreview.innerHTML =
      '<div class="' + cardClass + ' is-file-only">' +
      '<div class="sffc-editorial-standalone-chat__upload-preview-meta"><strong>' + fileName + '</strong><span>' + meta + "</span></div>" +
      "</div>";
  }

  function bootLiveChat(root, state) {
    if (!state.config.ajaxUrl || !state.config.nonce || state.booted) {
      return;
    }
    var data = new FormData();
    data.append("action", "sffc_editorial_floating_chat_live_boot");
    data.append("session_token", state.sessionToken);
    data.append("topic", state.topic || "job_search");
    data.append("page_url", window.location.href);
    state.booted = true;

    postForm(state.config, data)
      .then(function (payload) {
        state.conversationId = payload.conversation_id || state.conversationId || 0;
        if (payload.availability && payload.availability.message && state.status) {
          state.status.textContent = payload.availability.message;
        }
      })
      .catch(function () {
        state.booted = false;
      });
  }

  function fetchReplies(state) {
    if (!state.conversationId || state.fetching) {
      return;
    }
    state.fetching = true;
    var data = new FormData();
    data.append("action", "sffc_editorial_floating_chat_live_fetch");
    data.append("conversation_id", state.conversationId);
    data.append("last_message_id", state.lastMessageId || 0);
    data.append("page_url", window.location.href);

    postForm(state.config, data)
      .then(function (payload) {
        state.lastMessageId = payload.last_message_id || state.lastMessageId || 0;
        (payload.messages || []).forEach(function (message) {
          appendMessage(
            state,
            message.content || message.content_html || "",
            "assistant",
            message.from_name || "Senna career expert"
          );
        });
      })
      .catch(function () {})
      .finally(function () {
        state.fetching = false;
      });
  }

  function updateAttachmentPreview(state) {
    var files = state.fileInput && state.fileInput.files ? Array.prototype.slice.call(state.fileInput.files) : [];
    state.hasUploadedCv = files.length > 0;
    if (!state.attachmentPreview) {
      return;
    }
    if (!files.length) {
      state.attachmentPreview.hidden = true;
      state.attachmentPreview.innerHTML = "";
      clearPreviewObjectUrls(state);
      return;
    }
    state.attachmentPreview.hidden = false;
    renderFilePreviewCard(state, files[0], { label: "Ready to send" });
  }

  function submitMessage(root, state) {
    var message = state.input ? state.input.value.trim() : "";
    var files = state.fileInput && state.fileInput.files ? Array.prototype.slice.call(state.fileInput.files) : [];
    var name = state.form.querySelector('input[name="candidate_name"]');
    var email = state.form.querySelector('input[name="email"]');

    if (email && !email.checkValidity()) {
      state.form.reportValidity();
      return;
    }
    if (!message && !files.length) {
      setNotice(state, "Write a message or attach your CV first.", "error");
      return;
    }

    setNotice(state, "", "");
    if (state.sendButton) {
      state.sendButton.disabled = true;
    }

    appendMessage(state, message || "CV uploaded", "user");
    if (files.length) {
      appendMessage(state, "Attached: " + files.map(function (file) {
        return file.name;
      }).join(", "), "user");
    }

    var data = new FormData();
    data.append("action", "sffc_editorial_floating_chat_live_send");
    data.append("session_token", state.sessionToken);
    data.append("conversation_id", state.conversationId || 0);
    data.append("topic", state.topic || "job_search");
    data.append("page_url", window.location.href);
    data.append("candidate_name", name ? name.value : "");
    data.append("email", email ? email.value : "");
    data.append("message", message);
    files.forEach(function (file) {
      data.append("attachments[]", file);
    });

    if (state.input) {
      state.input.value = "";
    }

    postForm(state.config, data)
      .then(function (payload) {
        state.conversationId = payload.conversation_id || state.conversationId || 0;
        state.lastMessageId = Math.max(state.lastMessageId || 0, payload.message_id || 0);
        if (files.length) {
          state.attachmentPreview.hidden = false;
          renderFilePreviewCard(state, files[0], { label: "Sent to Senna" });
        }
        if (state.fileInput) {
          state.fileInput.value = "";
        }
        if (files.length && !state.handoverShown) {
          root.classList.add("has-handover");
          queueEmily(
            state,
            (state.config.labels && state.config.labels.handover) ||
              "Thanks. I have sent your CV and job-search goal to a real Senna career expert. They will reply here with next steps.",
            900
          );
          state.handoverShown = true;
        } else if (payload.reply) {
          queueEmily(state, payload.reply, 700);
        }
        window.setTimeout(function () {
          fetchReplies(state);
        }, 900);
      })
      .catch(function (error) {
        setNotice(
          state,
          error.message || (state.config.labels && state.config.labels.sendError) || "Unable to send your message.",
          "error"
        );
      })
      .finally(function () {
        if (state.sendButton) {
          state.sendButton.disabled = false;
        }
      });
  }

  function init(root) {
    var config = getConfig(root);
    var state = {
      config: config,
      sessionToken: getSessionToken(config),
      conversationId: 0,
      lastMessageId: 0,
      topic: "job_search",
      booted: false,
      fetching: false,
      handoverShown: false,
      hasUploadedCv: false,
      messages: root.querySelector("[data-sffc-esc-messages]"),
      status: root.querySelector("[data-sffc-esc-status]"),
      form: root.querySelector("[data-sffc-esc-form]"),
      input: root.querySelector("[data-sffc-esc-input]"),
      fileInput: root.querySelector("[data-sffc-esc-attachment-input]"),
      attachmentPreview: root.querySelector("[data-sffc-esc-attachment-preview]"),
      notice: root.querySelector("[data-sffc-esc-notice]"),
      sendButton: root.querySelector("[data-sffc-esc-send]"),
      previewObjectUrls: [],
    };

    if (!state.form || !state.messages) {
      return;
    }

    if (state.status && config.offlineMessage) {
      state.status.textContent = config.offlineMessage;
    }

    queueEmily(
      state,
      "Hi, I'm Emily, your job search assistant. Would you prefer English or Arabic?\n\nمرحباً، أنا إيميلي، مساعدتك في البحث عن عمل. هل تفضل العربية أم الإنجليزية؟",
      250
    );
    bootLiveChat(root, state);

    root.querySelectorAll("[data-sffc-esc-language]").forEach(function (button) {
      button.addEventListener("click", function () {
        var language = button.getAttribute("data-sffc-esc-language") || "English";
        appendMessage(state, language, "user");
        if (language.toLowerCase() === "arabic") {
          queueEmily(state, "تمام. ارفع سيرتك الذاتية أو أخبريني بنوع الوظائف التي تبحث عنها، ثم سأرسلها إلى خبير حقيقي من فريق Senna.", 600);
        } else {
          queueEmily(state, "Great. Start with your CV or tell me the roles you want. I will use your real background first, then hand this to a real Senna career expert.", 600);
        }
      });
    });

    var attachButton = root.querySelector("[data-sffc-esc-attachment-button]");
    if (attachButton && state.fileInput) {
      attachButton.addEventListener("click", function () {
        state.fileInput.click();
      });
      state.fileInput.addEventListener("change", function () {
        updateAttachmentPreview(state);
        if (state.fileInput.files && state.fileInput.files.length && state.input && !state.input.value.trim()) {
          state.input.value = "I have uploaded my CV. Please review it and help me with my job search.";
        }
      });
    }

    state.form.addEventListener("submit", function (event) {
      event.preventDefault();
      submitMessage(root, state);
    });

    if (state.input) {
      state.input.addEventListener("keydown", function (event) {
        if (event.key === "Enter" && !event.shiftKey) {
          event.preventDefault();
          submitMessage(root, state);
        }
      });
    }

    window.setInterval(function () {
      fetchReplies(state);
    }, 10000);
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-sffc-editorial-standalone-chat]").forEach(init);
  });
})();
