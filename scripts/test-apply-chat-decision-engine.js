#!/usr/bin/env node

const cases = [
  {
    message: "how do i get a job in dubai",
    promptState: "apply_results_confirm_same_cv",
    expectedIntent: "job_search",
    expectedRelationship: "new_topic",
    expectedAction: "show_job_results",
  },
  {
    message: "im stuck can you help me plan",
    promptState: "apply_results_confirm_same_cv",
    expectedIntent: "career_planning",
    expectedRelationship: "changes_task",
    expectedAction: "answer_directly",
  },
  {
    message: "no i don't want to apply yet please help",
    promptState: "apply_results_confirm_same_cv",
    expectedIntent: "application_pause",
    expectedRelationship: "pauses_task",
    expectedAction: "answer_directly",
  },
  {
    message: "these are too junior",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
  },
  {
    message: "only show me Dubai",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
  },
  {
    message: "yes use the same CV",
    promptState: "apply_results_confirm_same_cv",
    expectedIntent: "answer_pending_question",
    expectedRelationship: "answers_pending_question",
    expectedAction: "defer_to_prompt_handler",
  },
  {
    message: "improve CV first",
    promptState: "apply_results_selected_next_step",
    expectedIntent: "answer_pending_question",
    expectedRelationship: "answers_pending_question",
    expectedAction: "defer_to_prompt_handler",
  },
  {
    message: "continue with original CV",
    promptState: "apply_results_selected_next_step",
    expectedIntent: "answer_pending_question",
    expectedRelationship: "answers_pending_question",
    expectedAction: "defer_to_prompt_handler",
  },
  {
    message: "apply with tailored CV",
    promptState: "apply_results_selected_next_step",
    expectedIntent: "answer_pending_question",
    expectedRelationship: "answers_pending_question",
    expectedAction: "defer_to_prompt_handler",
  },
  {
    message: "jump straight into the application",
    promptState: "apply_results_selected_next_step",
    expectedIntent: "answer_pending_question",
    expectedRelationship: "answers_pending_question",
    expectedAction: "defer_to_prompt_handler",
  },
  {
    message: "apply now",
    promptState: "",
    selectedRole: true,
    expectedIntent: "apply_action",
    expectedRelationship: "continues_task",
    expectedAction: "start_apply_execution",
  },
  {
    message: "just apply",
    promptState: "",
    selectedRole: true,
    expectedIntent: "apply_action",
    expectedRelationship: "continues_task",
    expectedAction: "start_apply_execution",
  },
  {
    message: "apply to the first one",
    promptState: "",
    selectedRole: false,
    referencedRole: true,
    expectedIntent: "apply_action",
    expectedRelationship: "continues_task",
    expectedAction: "start_apply_execution",
  },
  {
    message: "what about the other one?",
    promptState: "",
    activeTask: "search",
    selectedRole: true,
    referencedRole: true,
    expectedIntent: "role_reference",
    expectedRelationship: "continues_task",
    expectedAction: "render_selected_role",
  },
  {
    message: "can you help me with my career",
    promptState: "apply_results_confirm_same_cv",
    expectedIntent: "career_planning",
    expectedRelationship: "changes_task",
    expectedAction: "answer_directly",
  },
  {
    message: "I don't know what jobs I should target",
    promptState: "",
    expectedIntent: "career_planning",
    expectedRelationship: "new_topic",
    expectedAction: "answer_directly",
  },
  {
    message: "these roles are too operational",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
  },
  {
    message: "don't show me consulting roles",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
  },
  {
    message: "avoid Riyadh for now",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
  },
  {
    message: "nothing below AED 30k",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
  },
  {
    message: "I want associate level roles",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
  },
  {
    message: "always tailor first",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
  },
  {
    message: "what is happening with my application",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "application_status",
    expectedRelationship: "continues_task",
    expectedAction: "answer_application_status",
  },
  {
    message: "should I move to Riyadh or Dubai",
    promptState: "apply_results_confirm_same_cv",
    expectedIntent: "career_question",
    expectedRelationship: "interrupts_task",
    expectedAction: "answer_directly",
  },
  {
    message: "why am I not getting interviews",
    promptState: "",
    expectedIntent: "career_question",
    expectedRelationship: "new_topic",
    expectedAction: "answer_directly",
  },
  {
    message: "review my CV against this role",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "cv_request",
    expectedRelationship: "interrupts_task",
    expectedAction: "answer_directly",
  },
];

function clean(value) {
  return String(value || "").replace(/\s+/g, " ").trim();
}

function isPromptAnswer(message, promptState) {
  const text = clean(message).toLowerCase();
  const wordCount = text ? text.split(/\s+/).filter(Boolean).length : 0;
  if (!promptState || !text) return false;
  if (
    promptState === "apply_results_selected_next_step" &&
    /\b(?:apply|application|jump|start|go ahead|compare|match|fit|improve|tailor|original|current cv|without tailor)\b/i.test(text)
  ) {
    return true;
  }
  if (/^(?:yes|y|yeah|yep|sure|ok|okay)?\s*(?:use\s+)?(?:the\s+)?same\s+(?:cv|resume)\b/i.test(text)) {
    return true;
  }
  if (/\b(?:apply|applying|application|job|jobs|role|roles|career|search|dubai|riyadh|saudi|uae|consulting|junior|senior)\b/i.test(text)) {
    return false;
  }
  if (/^(?:yes|y|yeah|yep|sure|ok|okay|correct|same|use it|go ahead|no|n|nope|different|change|another|back|cancel)/i.test(text)) {
    return true;
  }
  if (wordCount > 5) return false;
  if (/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(text)) return true;
  return /^\d{4,8}$/.test(text) && /verification|code/i.test(promptState);
}

function classify(message, context) {
  const text = clean(message);
  const lower = text.toLowerCase();
  if (isPromptAnswer(text, context.promptState)) {
    return ["answer_pending_question", 0.94];
  }
  if (/\b(?:too junior|more senior|senior roles|higher level|not junior|associate level|only show|just show|solely|not consulting|no consulting|don'?t show.*consulting|too operational|avoid riyadh|avoid dubai|nothing below|not below|salary floor|private credit|private equity|always tailor first)\b/i.test(lower)) {
    return ["search_refinement", 0.9];
  }
  if (/\b(?:what is happening|status|progress|where are we)\b.*\b(?:application|apply|role)\b/i.test(lower)) {
    return ["application_status", 0.86];
  }
  if (/\b(?:show|find|search|look for|list|recommend|any|open|current)\b.*\b(?:job|jobs|role|roles|opening|openings|vacanc|opportunit)/i.test(lower)) {
    return ["job_search", 0.92];
  }
  if (/\bhow\b.*\b(?:get|find|land|search|look for|apply|break into|get into)\b.*\b(?:job|jobs|role|roles|work)\b/i.test(lower)) {
    return ["job_search", 0.92];
  }
  if (/\b(?:don'?t|do not|dont|not)\s+(?:want\s+to\s+)?apply\b|\b(?:pause|stop|hold off|wait)\s+(?:the\s+)?(?:application|applying|apply)\b|\b(?:not apply yet|forget the application|leave the application)\b/i.test(lower)) {
    return ["application_pause", 0.98];
  }
  if (/\b(?:stuck|help me plan|make a plan|career plan|career planning|don'?t know what i want|don't know what jobs|dont know what jobs|what jobs suit me|career direction|help me with my career|what should i do next)\b/i.test(lower)) {
    return ["career_planning", 0.96];
  }
  if (/\b(?:apply to|apply for|start applying|go ahead and apply|apply now|just apply)\b/i.test(lower)) {
    return ["apply_action", 0.9];
  }
  if (context.referencedRole && /\b(?:what about|show|open|review|that one|other one|first one|second one|third one)\b/i.test(lower)) {
    return ["role_reference", 0.84];
  }
  if (/\b(?:should i|do i have a chance|am i competitive|worth applying|salary|compensation|move to|dubai|riyadh|abu dhabi|saudi|uae|market|recruiter|interviews?)\b/i.test(lower)) {
    return ["career_question", 0.82];
  }
  if (/\b(?:cv|resume|profile)\b.*\b(?:review|fix|improve|tailor|rewrite|compare|match|fit)\b|\b(?:review|fix|improve|tailor|rewrite|compare)\b.*\b(?:cv|resume|profile)\b/i.test(lower)) {
    return ["cv_request", 0.82];
  }
  return ["unknown", 0.35];
}

function relationship(intent, context) {
  if (intent === "answer_pending_question") return "answers_pending_question";
  if (intent === "application_pause") return "pauses_task";
  if (intent === "search_refinement") return "changes_task";
  if (intent === "job_search") return context.activeTask === "search" ? "continues_task" : "new_topic";
  if (intent === "apply_action") return context.selectedRole || context.referencedRole ? "continues_task" : "new_topic";
  if (intent === "role_reference") return context.activeTask ? "continues_task" : "new_topic";
  if (intent === "career_planning") return context.activeTask ? "changes_task" : "new_topic";
  if (intent === "career_question" || intent === "cv_request") return context.activeTask ? "interrupts_task" : "new_topic";
  if (intent === "application_status") return context.activeTask ? "continues_task" : "new_topic";
  return "unclear";
}

function action(intent, relation, context) {
  if (intent === "answer_pending_question") return "defer_to_prompt_handler";
  if (intent === "search_refinement") return "update_search_preferences";
  if (intent === "job_search") return "show_job_results";
  if (intent === "apply_action") return context.selectedRole || context.referencedRole ? "start_apply_execution" : "ask_clarifying_question";
  if (intent === "role_reference") return context.referencedRole ? "render_selected_role" : "ask_clarifying_question";
  if (intent === "application_status") return "answer_application_status";
  if (relation === "pauses_task" || intent === "career_planning" || intent === "career_question" || intent === "cv_request") return "answer_directly";
  return "ask_clarifying_question";
}

let failed = 0;

cases.forEach((item) => {
  const context = {
    promptState: item.promptState || "",
    activeTask: item.activeTask || (item.promptState ? "apply_flow" : ""),
    selectedRole: !!item.selectedRole,
    referencedRole: !!item.referencedRole,
  };
  const [intent] = classify(item.message, context);
  const rel = relationship(intent, context);
  const next = action(intent, rel, context);
  const ok =
    intent === item.expectedIntent &&
    rel === item.expectedRelationship &&
    next === item.expectedAction;

  if (!ok) {
    failed += 1;
    console.error("FAIL", {
      message: item.message,
      expected: [item.expectedIntent, item.expectedRelationship, item.expectedAction],
      actual: [intent, rel, next],
    });
  }
});

if (failed) {
  process.exit(1);
}

console.log(`PASS ${cases.length} apply-chat decision fixtures`);
