(function () {
  "use strict";

  function getInstanceConfig(root) {
    var instanceId = root.getAttribute("data-instance-id") || "";
    var registry = window.SFFCEditorialFloatingChat || {};
    return (instanceId && registry[instanceId]) || {};
  }

  function postAjax(config, action, fields) {
    var data = new FormData();
    data.append("action", action);
    data.append("nonce", config.nonce || "");
    Object.keys(fields || {}).forEach(function (key) {
      data.append(key, fields[key]);
    });

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

  function postAjaxFormData(config, data) {
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

  function getSessionToken(root) {
    var key = "sffc_editorial_live_chat_session";
    try {
      var existing = window.localStorage.getItem(key);
      if (existing) {
        return existing;
      }
      var created =
        "efc_" +
        Date.now().toString(36) +
        "_" +
        Math.random().toString(36).slice(2, 12);
      window.localStorage.setItem(key, created);
      return created;
    } catch (error) {
      return (
        root.getAttribute("data-instance-id") +
        "_" +
        Date.now().toString(36) +
        "_" +
        Math.random().toString(36).slice(2, 10)
      );
    }
  }

  function markSeen(config, badge) {
    if (!badge || !badge.classList.contains("is-visible")) {
      return;
    }
    badge.classList.remove("is-visible");
    badge.textContent = "";
    try {
      window.localStorage.setItem(
        config.storageKey || "sffc_editorial_floating_chat_seen",
        "1"
      );
    } catch (error) {}

    if (!config.ajaxUrl || !config.nonce || !config.isLoggedIn) {
      return;
    }

    postAjax(config, "sffc_editorial_floating_chat_seen", {}).catch(function () {});
  }

  function openPanel(root, panel, trigger) {
    if (!panel) {
      return;
    }
    panel.hidden = false;
    panel.setAttribute("aria-hidden", "false");
    root.classList.add("is-open");
    if (trigger) {
      trigger.setAttribute("aria-expanded", "true");
    }
  }

  function closePanel(root, panel, trigger) {
    if (!panel) {
      return;
    }
    panel.hidden = true;
    panel.setAttribute("aria-hidden", "true");
    root.classList.remove("is-open");
    if (trigger) {
      trigger.setAttribute("aria-expanded", "false");
    }
  }

  function setNotice(notice, message, type) {
    if (!notice) {
      return;
    }
    notice.hidden = !message;
    notice.textContent = message || "";
    notice.classList.toggle("is-success", type === "success");
    notice.classList.toggle("is-error", type === "error");
  }

  function appendMessage(messages, body, speaker, fromName) {
    if (!messages || !body) {
      return;
    }
    var article = document.createElement("article");
    article.className =
      "sffc-editorial-floating-chat__message sffc-editorial-floating-chat__message--" +
      (speaker === "user" ? "user" : "assistant");

    if (fromName && speaker !== "user") {
      var meta = document.createElement("div");
      meta.className = "sffc-editorial-floating-chat__message-meta";
      meta.textContent = fromName;
      article.appendChild(meta);
    }

    var bubble = document.createElement("div");
    bubble.className = "sffc-editorial-floating-chat__bubble";
    bubble.textContent = body;
    article.appendChild(bubble);
    messages.appendChild(article);
    messages.scrollTop = messages.scrollHeight;
  }

  function normalizeAssistantName(fromName) {
    if (!fromName || fromName === "MENA Careers team") {
      return "Emily B.";
    }
    return fromName;
  }

  function playMessageSound(state) {
    if (!state || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      return;
    }

    try {
      var AudioContext = window.AudioContext || window.webkitAudioContext;
      if (!AudioContext) {
        return;
      }
      if (!state.audioContext) {
        state.audioContext = new AudioContext();
      }
      var context = state.audioContext;
      if (context.state === "suspended") {
        context.resume().catch(function () {});
      }

      var oscillator = context.createOscillator();
      var gain = context.createGain();
      var start = context.currentTime;
      oscillator.type = "sine";
      oscillator.frequency.setValueAtTime(740, start);
      oscillator.frequency.exponentialRampToValueAtTime(520, start + 0.12);
      gain.gain.setValueAtTime(0.0001, start);
      gain.gain.exponentialRampToValueAtTime(0.045, start + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.18);
      oscillator.connect(gain);
      gain.connect(context.destination);
      oscillator.start(start);
      oscillator.stop(start + 0.2);
    } catch (error) {}
  }

  function appendTypingIndicator(messages, fromName) {
    if (!messages) {
      return null;
    }

    var article = document.createElement("article");
    article.className =
      "sffc-editorial-floating-chat__message sffc-editorial-floating-chat__message--assistant is-typing";

    var meta = document.createElement("div");
    meta.className = "sffc-editorial-floating-chat__message-meta";
    meta.textContent = normalizeAssistantName(fromName);
    article.appendChild(meta);

    var bubble = document.createElement("div");
    bubble.className = "sffc-editorial-floating-chat__bubble";
    bubble.setAttribute("aria-label", normalizeAssistantName(fromName) + " is typing");

    var typing = document.createElement("span");
    typing.className = "sffc-editorial-floating-chat__typing";
    typing.setAttribute("aria-hidden", "true");
    typing.innerHTML = "<span></span><span></span><span></span>";
    bubble.appendChild(typing);

    article.appendChild(bubble);
    messages.appendChild(article);
    messages.scrollTop = messages.scrollHeight;
    return article;
  }

  function queueAssistantMessage(root, state, body, fromName) {
    if (!body) {
      return;
    }
    state.assistantQueue.push({
      body: body,
      fromName: normalizeAssistantName(fromName),
    });
    processAssistantQueue(root, state);
  }

  function processAssistantQueue(root, state) {
    if (state.isAssistantQueueRunning || !state.assistantQueue.length) {
      return;
    }

    state.isAssistantQueueRunning = true;
    var item = state.assistantQueue.shift();
    var typing = appendTypingIndicator(state.messages, item.fromName);
    var delay = Math.min(1800, Math.max(700, item.body.length * 18));

    window.setTimeout(function () {
      if (typing && typing.parentNode) {
        typing.parentNode.removeChild(typing);
      }
      appendMessage(state.messages, item.body, "assistant", item.fromName);
      playMessageSound(state);
      state.isAssistantQueueRunning = false;
      window.setTimeout(function () {
        processAssistantQueue(root, state);
      }, 260);
    }, delay);
  }

  function topicLabel(topic) {
    if (topic === "team") {
      return "Contact the team";
    }
    if (topic === "job_search") {
      return "Job search assistance";
    }
    if (topic === "cv_review") {
      return "Career Assessment";
    }
    if (topic === "recruiter_outreach") {
      return "Recruiter outreach";
    }
    return "Get Hired Quicker";
  }

  function updateAvailability(root, availability) {
    if (!availability) {
      return;
    }
    var status = root.querySelector("[data-sffc-efc-status-text]");
    if (status && availability.message) {
      status.textContent = availability.message;
    }
    root.classList.toggle("is-offline", !availability.is_online);
  }

  function bootLiveChat(root, state, topic) {
    var config = state.config;
    if (!config.ajaxUrl || !config.nonce) {
      return Promise.reject(new Error("Live chat is not configured."));
    }
    state.topic = topic || state.topic || "expert";
    return postAjax(config, "sffc_editorial_floating_chat_live_boot", {
      session_token: state.sessionToken,
      topic: state.topic,
      page_url: window.location.href,
    }).then(function (data) {
      state.conversationId = data.conversation_id || state.conversationId;
      updateAvailability(root, data.availability);
      return data;
    });
  }

  function fetchReplies(root, state) {
    if (!state.conversationId || state.isFetching) {
      return;
    }
    state.isFetching = true;
    postAjax(state.config, "sffc_editorial_floating_chat_live_fetch", {
      conversation_id: state.conversationId,
      last_message_id: state.lastMessageId || 0,
      page_url: window.location.href,
    })
      .then(function (data) {
        updateAvailability(root, data.availability);
        state.lastMessageId = data.last_message_id || state.lastMessageId || 0;
        (data.messages || []).forEach(function (message) {
          queueAssistantMessage(
            root,
            state,
            message.content || message.content_html || "",
            message.from_name || "MENA Careers team"
          );
        });
      })
      .catch(function () {})
      .finally(function () {
        state.isFetching = false;
      });
  }

  function showChatScreen(root, state, topic) {
    var menu = root.querySelector("[data-sffc-efc-live-menu]");
    var chat = root.querySelector("[data-sffc-efc-chat-screen]");
    var topicInput = root.querySelector("[data-sffc-efc-topic-value]");
    var endButton = root.querySelector("[data-sffc-efc-end-chat]");
    var input = root.querySelector("[data-sffc-efc-input]");

    state.topic = topic || "expert";
    if (topicInput) {
      topicInput.value = state.topic;
    }
    root.setAttribute("data-sffc-efc-active-topic", topicLabel(state.topic));
    if (menu) {
      menu.hidden = true;
    }
    if (chat) {
      chat.hidden = false;
    }
    if (endButton) {
      endButton.hidden = false;
    }
    if (!state.hasShownIntro) {
      queueAssistantMessage(
        root,
        state,
        "Hi, I’m Emily. Tell me the role, location, and type of help you want. You can attach your CV here too.",
        "Emily B."
      );
      state.hasShownIntro = true;
    }

    bootLiveChat(root, state, state.topic)
      .then(function () {
        fetchReplies(root, state);
      })
      .catch(function (error) {
        setNotice(state.notice, error.message, "error");
      });

    window.setTimeout(function () {
      var email = root.querySelector('.sffc-editorial-floating-chat__live-fields input[type="email"]');
      if (email && !email.value) {
        email.focus();
      } else if (input) {
        input.focus();
      }
    }, 90);
  }

  function showMenuScreen(root, state) {
    var menu = root.querySelector("[data-sffc-efc-live-menu]");
    var chat = root.querySelector("[data-sffc-efc-chat-screen]");
    var endButton = root.querySelector("[data-sffc-efc-end-chat]");
    if (menu) {
      menu.hidden = false;
    }
    if (chat) {
      chat.hidden = true;
    }
    if (endButton) {
      endButton.hidden = true;
    }
    root.setAttribute("data-sffc-efc-active-topic", "");
    state.topic = "expert";
  }

  function submitLiveMessage(root, state, form) {
    var input = form.querySelector("[data-sffc-efc-input]");
    var submit = form.querySelector("[data-sffc-efc-send]");
    var name = form.querySelector('input[name="candidate_name"]');
    var email = form.querySelector('input[name="email"]');
    var topicInput = form.querySelector("[data-sffc-efc-topic-value]");
    var attachmentInput = form.querySelector("[data-sffc-efc-attachment-input]");
    var attachmentPreview = form.querySelector("[data-sffc-efc-attachment-preview]");
    var message = input ? input.value.trim() : "";
    var files = attachmentInput && attachmentInput.files ? Array.prototype.slice.call(attachmentInput.files) : [];

    if (email && !email.checkValidity()) {
      form.reportValidity();
      return;
    }
    if (!message && !files.length) {
      setNotice(state.notice, "Write a message or attach a file first.", "error");
      return;
    }

    if (submit) {
      submit.disabled = true;
    }
    setNotice(state.notice, "", "");
    appendMessage(
      state.messages,
      message || "Attachment uploaded",
      "user"
    );
    if (files.length) {
      appendMessage(
        state.messages,
        "Attached: " +
          files
            .map(function (file) {
              return file.name;
            })
            .join(", "),
        "user"
      );
    }
    if (input) {
      input.value = "";
    }

    var data = new FormData();
    data.append("action", "sffc_editorial_floating_chat_live_send");
    data.append("session_token", state.sessionToken);
    data.append("conversation_id", state.conversationId || 0);
    data.append("topic", (topicInput && topicInput.value) || state.topic || "expert");
    data.append("page_url", window.location.href);
    data.append("candidate_name", name ? name.value : "");
    data.append("email", email ? email.value : "");
    data.append("message", message);
    files.forEach(function (file) {
      data.append("attachments[]", file);
    });

    postAjaxFormData(state.config, data)
      .then(function (data) {
        state.conversationId = data.conversation_id || state.conversationId;
        state.lastMessageId = Math.max(state.lastMessageId || 0, data.message_id || 0);
        updateAvailability(root, data.availability);
        if (attachmentInput) {
          attachmentInput.value = "";
        }
        if (attachmentPreview) {
          attachmentPreview.hidden = true;
          attachmentPreview.textContent = "";
        }
        if (data.reply && !state.hasShownAcknowledgement) {
          queueAssistantMessage(root, state, data.reply, "Emily B.");
          state.hasShownAcknowledgement = true;
        }
        window.setTimeout(function () {
          fetchReplies(root, state);
        }, 800);
      })
      .catch(function (error) {
        setNotice(
          state.notice,
          error.message || (state.config.labels && state.config.labels.sendError) || "Unable to send your message.",
          "error"
        );
      })
      .finally(function () {
        if (submit) {
          submit.disabled = false;
        }
      });
  }

  function updateAttachmentPreview(form) {
    var input = form.querySelector("[data-sffc-efc-attachment-input]");
    var preview = form.querySelector("[data-sffc-efc-attachment-preview]");
    if (!input || !preview) {
      return;
    }
    var files = input.files ? Array.prototype.slice.call(input.files) : [];
    preview.hidden = files.length === 0;
    preview.textContent = files.length
      ? "Attached: " +
        files
          .map(function (file) {
            return file.name;
          })
          .join(", ")
      : "";
  }

  function init(root) {
    var config = getInstanceConfig(root);
    var panel = root.querySelector("[data-sffc-efc-panel]");
    var trigger = root.querySelector("[data-sffc-efc-trigger]");
    var badge = root.querySelector("[data-sffc-efc-badge]");
    var form = root.querySelector("[data-sffc-efc-live-form]");
    var state = {
      config: config,
      sessionToken: getSessionToken(root),
      conversationId: 0,
      lastMessageId: 0,
      topic: "expert",
      isFetching: false,
      hasShownAcknowledgement: false,
      hasShownIntro: false,
      assistantQueue: [],
      isAssistantQueueRunning: false,
      audioContext: null,
      messages: root.querySelector("[data-sffc-efc-messages]"),
      notice: root.querySelector("[data-sffc-efc-live-notice]"),
    };

    if (!panel || !trigger) {
      return;
    }

    updateAvailability(root, {
      is_online: !!config.isOnline,
      message: config.offlineMessage || "",
    });

    try {
      if (config.storageKey && window.localStorage.getItem(config.storageKey)) {
        if (badge) {
          badge.classList.remove("is-visible");
          badge.textContent = "";
        }
      }
    } catch (error) {}

    trigger.addEventListener("click", function () {
      var isOpen = !panel.hidden;
      if (isOpen) {
        closePanel(root, panel, trigger);
        return;
      }
      openPanel(root, panel, trigger);
      markSeen(config, badge);
      showMenuScreen(root, state);
    });

    root.querySelectorAll("[data-sffc-efc-close]").forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        closePanel(root, panel, trigger);
      });
    });

    root.querySelectorAll("[data-sffc-efc-end-chat]").forEach(function (button) {
      button.addEventListener("click", function () {
        closePanel(root, panel, trigger);
      });
    });

    root.querySelectorAll("[data-sffc-efc-live-topic]").forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        showChatScreen(root, state, button.getAttribute("data-sffc-efc-live-topic") || "expert");
      });
    });

    if (form) {
      var input = form.querySelector("[data-sffc-efc-input]");
      var attachmentButton = form.querySelector("[data-sffc-efc-attachment-button]");
      var attachmentInput = form.querySelector("[data-sffc-efc-attachment-input]");

      form.addEventListener("submit", function (event) {
        event.preventDefault();
        submitLiveMessage(root, state, form);
      });
      if (input) {
        input.addEventListener("keydown", function (event) {
          if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            submitLiveMessage(root, state, form);
          }
        });
      }
      if (attachmentButton && attachmentInput) {
        attachmentButton.addEventListener("click", function () {
          attachmentInput.click();
        });
        attachmentInput.addEventListener("change", function () {
          updateAttachmentPreview(form);
        });
      }
    }

    window.setInterval(function () {
      if (!panel.hidden && state.conversationId) {
        fetchReplies(root, state);
      }
    }, 10000);
  }

  document.addEventListener("DOMContentLoaded", function () {
    document
      .querySelectorAll("[data-sffc-editorial-floating-chat]")
      .forEach(init);
  });
})();
