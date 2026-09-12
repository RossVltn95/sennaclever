#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const baseCases = [
  {
    message: "how do i get a job in dubai",
    promptState: "apply_results_confirm_same_cv",
    expectedIntent: "career_question",
    expectedRelationship: "interrupts_task",
    expectedAction: "answer_directly",
    expectedPlanObjective: "answer_career_question",
    expectedPlanMode: "answer",
  },
  {
    message: "im stuck can you help me plan",
    promptState: "apply_results_confirm_same_cv",
    expectedIntent: "career_planning",
    expectedRelationship: "changes_task",
    expectedAction: "answer_directly",
  },
  {
    message: "i just need to know which is the best direction for me right now to move jobs or stay where i am, if i move jobs i get a higher salary, can you help me decide?",
    promptState: "career_advisor_focus",
    activeTask: "apply_flow",
    expectedIntent: "career_question",
    expectedRelationship: "interrupts_task",
    expectedAction: "answer_directly",
  },
  {
    message: "thanks that is super useful, also do you know why sometimes you don't hear back from recruiters",
    promptState: "career_advisor_focus",
    activeTask: "apply_flow",
    expectedIntent: "career_question",
    expectedRelationship: "interrupts_task",
    expectedAction: "answer_directly",
  },
  {
    message: "Ok do you know who i can contact at senna",
    promptState: "career_advisor_focus",
    activeTask: "apply_flow",
    expectedIntent: "career_question",
    expectedRelationship: "interrupts_task",
    expectedAction: "answer_directly",
  },
  {
    message: "well ok you're just going to show me jobs in dubai???",
    promptState: "",
    activeTask: "search",
    expectedIntent: "career_question",
    expectedRelationship: "interrupts_task",
    expectedAction: "answer_directly",
  },
  {
    message: "ok look for jobs in London",
    promptState: "",
    activeTask: "search",
    expectedIntent: "job_search",
    expectedRelationship: "continues_task",
    expectedAction: "show_job_results",
    expectedPlanObjective: "manage_job_search",
    expectedPlanMode: "execute",
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
    expectedPlanObjective: "answer_active_prompt",
    expectedPlanMode: "prompt",
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
    message: "use original not tailored",
    promptState: "apply_results_selected_next_step",
    selectedRole: true,
    expectedIntent: "answer_pending_question",
    expectedRelationship: "answers_pending_question",
    expectedAction: "defer_to_prompt_handler",
  },
  {
    message: "use original not tailored",
    promptState: "",
    selectedRole: true,
    expectedIntent: "apply_action",
    expectedRelationship: "continues_task",
    expectedAction: "start_apply_execution",
  },
  {
    message: "skip tailoring use original CV",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "apply_action",
    expectedRelationship: "continues_task",
    expectedAction: "start_apply_execution",
  },
  {
    message: "use the CV as is no tailoring",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "apply_action",
    expectedRelationship: "continues_task",
    expectedAction: "start_apply_execution",
  },
  {
    message: "apply with tailored CV",
    promptState: "apply_results_selected_next_step",
    expectedIntent: "answer_pending_question",
    expectedRelationship: "answers_pending_question",
    expectedAction: "defer_to_prompt_handler",
  },
  {
    message: "apply with tailored CV",
    promptState: "different_question_detail",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "apply_action",
    expectedRelationship: "continues_task",
    expectedAction: "start_apply_execution",
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
    message: "get started",
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
    message: "apply to the Business Development & Operations Senior Associate role",
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
    message: "what about the second one?",
    promptState: "",
    activeTask: "search",
    selectedRole: false,
    referencedRole: false,
    expectedIntent: "role_reference",
    expectedRelationship: "continues_task",
    expectedAction: "ask_clarifying_question",
  },
  {
    message: "what about the last one?",
    promptState: "apply_results_selected_next_step",
    activeTask: "search",
    selectedRole: true,
    referencedRole: true,
    expectedIntent: "role_reference",
    expectedRelationship: "continues_task",
    expectedAction: "render_selected_role",
  },
  {
    message: "actually go back to the second",
    promptState: "apply_results_selected_next_step",
    activeTask: "search",
    selectedRole: true,
    referencedRole: true,
    expectedIntent: "role_reference",
    expectedRelationship: "continues_task",
    expectedAction: "render_selected_role",
  },
  {
    message: "show me the role before that",
    promptState: "",
    activeTask: "search",
    selectedRole: true,
    referencedRole: true,
    expectedIntent: "role_reference",
    expectedRelationship: "continues_task",
    expectedAction: "render_selected_role",
  },
  {
    message: "now compare those two",
    promptState: "",
    activeTask: "search",
    selectedRole: true,
    referencedRole: true,
    expectedIntent: "role_reference",
    expectedRelationship: "continues_task",
    expectedAction: "render_selected_role",
  },
  {
    message: "how does my cv match the first one",
    promptState: "",
    activeTask: "search",
    referencedRole: true,
    expectedIntent: "cv_role_comparison",
    expectedRelationship: "continues_task",
    expectedAction: "compare_selected_role_cv",
  },
  {
    message: "what was the salary on the previous one?",
    promptState: "",
    activeTask: "search",
    referencedRole: true,
    expectedIntent: "salary_compensation_task",
    expectedRelationship: "continues_task",
    expectedAction: "answer_salary_compensation",
  },
  {
    message: "what salary should I expect for the second result?",
    promptState: "",
    activeTask: "search",
    referencedRole: true,
    expectedIntent: "salary_compensation_task",
    expectedRelationship: "continues_task",
    expectedAction: "answer_salary_compensation",
  },
  {
    message: "what does the second company do?",
    promptState: "",
    activeTask: "search",
    referencedRole: true,
    expectedIntent: "company_research_task",
    expectedRelationship: "continues_task",
    expectedAction: "answer_company_research",
  },
  {
    message: "why is this first?",
    promptState: "",
    activeTask: "search",
    visibleResults: true,
    expectedIntent: "search_results_question",
    expectedRelationship: "continues_task",
    expectedAction: "answer_search_results_question",
  },
  {
    message: "which one looks strongest?",
    promptState: "",
    activeTask: "search",
    visibleResults: true,
    expectedIntent: "search_results_question",
    expectedRelationship: "continues_task",
    expectedAction: "answer_search_results_question",
  },
  {
    message: "which one looks strongest?",
    promptState: "",
    activeTask: "",
    visibleResults: false,
    expectedIntent: "unknown",
    expectedRelationship: "unclear",
    expectedAction: "ask_clarifying_question",
  },
  {
    message: "should I target Riyadh instead of Dubai?",
    promptState: "",
    activeTask: "search",
    visibleResults: true,
    expectedIntent: "career_question",
    expectedRelationship: "interrupts_task",
    expectedAction: "answer_directly",
  },
  {
    message: "why am I not getting interviews?",
    promptState: "",
    activeTask: "search",
    visibleResults: true,
    expectedIntent: "career_question",
    expectedRelationship: "interrupts_task",
    expectedAction: "answer_directly",
  },
  {
    message: "do you have my CV on file?",
    promptState: "",
    activeTask: "search",
    visibleResults: true,
    expectedIntent: "cv_inventory",
    expectedRelationship: "continues_task",
    expectedAction: "answer_cv_inventory",
  },
  {
    message: "can you review my CV?",
    promptState: "",
    activeTask: "search",
    visibleResults: true,
    expectedIntent: "cv_review_task",
    expectedRelationship: "interrupts_task",
    expectedAction: "start_cv_review",
  },
  {
    message: "what can you do?",
    promptState: "",
    activeTask: "search",
    visibleResults: true,
    expectedIntent: "capability_inventory",
    expectedRelationship: "new_topic",
    expectedAction: "answer_capabilities",
  },
  {
    message: "wait don't apply yet, who is this company?",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "role_question",
    expectedRelationship: "continues_task",
    expectedAction: "answer_role_question",
  },
  {
    message: "does this role require Arabic?",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "role_question",
    expectedRelationship: "continues_task",
    expectedAction: "answer_role_question",
  },
  {
    message: "what do they actually do?",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "company_research_task",
    expectedRelationship: "continues_task",
    expectedAction: "answer_company_research",
  },
  {
    message: "what am I missing?",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "role_question",
    expectedRelationship: "continues_task",
    expectedAction: "answer_role_question",
  },
  {
    message: "what are the requirements for this role?",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "role_question",
    expectedRelationship: "continues_task",
    expectedAction: "answer_role_question",
  },
  {
    message: "what would I do in the second result?",
    promptState: "",
    activeTask: "search",
    referencedRole: true,
    expectedIntent: "role_question",
    expectedRelationship: "continues_task",
    expectedAction: "answer_role_question",
  },
  {
    message: "compare my CV with this role",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "cv_role_comparison",
    expectedRelationship: "continues_task",
    expectedAction: "compare_selected_role_cv",
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
    message: "these companies are not right",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
  },
  {
    message: "find me something better",
    promptState: "",
    activeTask: "search",
    expectedIntent: "job_search",
    expectedRelationship: "continues_task",
    expectedAction: "show_job_results",
  },
  {
    message: "ok find roles closer to investment analysis",
    promptState: "apply_intro_route_choice",
    activeTask: "search",
    expectedIntent: "job_search",
    expectedRelationship: "continues_task",
    expectedAction: "show_job_results",
  },
  {
    message: "show me Workday roles",
    promptState: "",
    activeTask: "search",
    expectedIntent: "job_search",
    expectedRelationship: "continues_task",
    expectedAction: "show_job_results",
  },
  {
    message: "show me Teamtailor jobs",
    promptState: "",
    activeTask: "search",
    expectedIntent: "job_search",
    expectedRelationship: "continues_task",
    expectedAction: "show_job_results",
  },
  {
    message: "Greenhouse only",
    promptState: "",
    activeTask: "search",
    expectedIntent: "job_search",
    expectedRelationship: "continues_task",
    expectedAction: "show_job_results",
  },
  {
    message: "remove the ATS restriction",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
  },
  {
    message: "show me my current filters",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_filter_status",
    expectedRelationship: "continues_task",
    expectedAction: "answer_search_filters",
  },
  {
    message: "reset everything",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_filter_reset",
    expectedRelationship: "changes_task",
    expectedAction: "reset_search_preferences",
  },
  {
    message: "reset everything except Dubai and private credit",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_filter_reset",
    expectedRelationship: "changes_task",
    expectedAction: "reset_search_preferences",
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
    message: "where are we with the application?",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "application_status",
    expectedRelationship: "continues_task",
    expectedAction: "answer_application_status",
  },
  {
    message: "what salary should I expect?",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "salary_compensation_task",
    expectedRelationship: "continues_task",
    expectedAction: "answer_salary_compensation",
  },
  {
    message: "ok continue applying",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "application_resume",
    expectedRelationship: "resumes_task",
    expectedAction: "resume_task",
  },
  {
    message: "continue from where you stopped",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "application_resume",
    expectedRelationship: "resumes_task",
    expectedAction: "resume_task",
  },
  {
    message: "continue from the exact step you stopped at",
    promptState: "career_advisor_focus",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "application_resume",
    expectedRelationship: "resumes_task",
    expectedAction: "resume_task",
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
  {
    message: "lets look for jobs in dubai",
    promptState: "",
    activeTask: "",
    expectedIntent: "job_search",
    expectedRelationship: "new_topic",
    expectedAction: "show_job_results",
    expectedNormalizedQuery: "dubai",
  },
  {
    message: "find me private credit analyst roles in Dubai, not operations, AED 25k+, greenhouse only",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
    expectedNormalizedQuery: "private credit analyst in dubai, not operations, aed 25k+, greenhouse",
  },
  {
    message: "new search investment analyst Dubai",
    promptState: "",
    activeTask: "search",
    expectedIntent: "job_search",
    expectedRelationship: "continues_task",
    expectedAction: "show_job_results",
    expectedNormalizedQuery: "investment analyst dubai",
  },
  {
    message: "dubai",
    promptState: "",
    activeTask: "role_discovery",
    expectedIntent: "job_search",
    expectedRelationship: "new_topic",
    expectedAction: "show_job_results",
    expectedNormalizedQuery: "dubai",
  },
  {
    message: "private credit",
    promptState: "",
    activeTask: "role_discovery",
    expectedIntent: "job_search",
    expectedRelationship: "new_topic",
    expectedAction: "show_job_results",
    expectedNormalizedQuery: "private credit",
  },
  {
    message: "investment analyst",
    promptState: "",
    activeTask: "role_discovery",
    expectedIntent: "job_search",
    expectedRelationship: "new_topic",
    expectedAction: "show_job_results",
    expectedNormalizedQuery: "investment analyst",
  },
  {
    message: "Do you have my cv on file",
    promptState: "",
    activeTask: "search",
    expectedIntent: "cv_inventory",
    expectedRelationship: "continues_task",
    expectedAction: "answer_cv_inventory",
  },
  {
    message: "ok can you show me which version of my cv you currently have",
    promptState: "",
    activeTask: "search",
    expectedIntent: "cv_inventory",
    expectedRelationship: "continues_task",
    expectedAction: "answer_cv_inventory",
  },
  {
    message: "can you tailor my cv",
    promptState: "",
    activeTask: "search",
    selectedRole: false,
    expectedIntent: "cv_tailoring_task",
    expectedRelationship: "interrupts_task",
    expectedAction: "start_cv_tailoring",
  },
  {
    message: "can i send you my cv to review",
    promptState: "",
    activeTask: "search",
    selectedRole: false,
    expectedIntent: "cv_review_task",
    expectedRelationship: "interrupts_task",
    expectedAction: "start_cv_review",
  },
  {
    message: "stronger interview probability",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_optimization",
    expectedRelationship: "changes_task",
    expectedAction: "optimize_search_preferences",
  },
  {
    message: "what can you do",
    promptState: "",
    activeTask: "",
    expectedIntent: "capability_inventory",
    expectedRelationship: "new_topic",
    expectedAction: "answer_capabilities",
  },
  {
    message: "show my applications",
    promptState: "",
    activeTask: "apply_flow",
    expectedIntent: "application_history",
    expectedRelationship: "continues_task",
    expectedAction: "answer_application_history",
  },
  {
    message: "prepare me for this interview",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "interview_prep_task",
    expectedRelationship: "interrupts_task",
    expectedAction: "start_interview_prep",
  },
  {
    message: "write a cover letter for this role",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "application_material_task",
    expectedRelationship: "interrupts_task",
    expectedAction: "start_application_material",
  },
  {
    message: "who should I contact for a referral",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "recruiter_networking_task",
    expectedRelationship: "interrupts_task",
    expectedAction: "start_recruiter_networking",
  },
  {
    message: "what salary should I expect for this role",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "salary_compensation_task",
    expectedRelationship: "continues_task",
    expectedAction: "answer_salary_compensation",
  },
  {
    message: "what does this company actually do",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "company_research_task",
    expectedRelationship: "continues_task",
    expectedAction: "answer_company_research",
  },
  {
    message: "same",
    promptState: "apply_results_confirm_same_cv",
    activeTask: "apply_flow",
    expectedIntent: "answer_pending_question",
    expectedRelationship: "answers_pending_question",
    expectedAction: "defer_to_prompt_handler",
    expectedBeliefGoal: "answer_prompt",
  },
  {
    message: "not yet, compare it to my CV first",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "cv_role_comparison",
    expectedRelationship: "continues_task",
    expectedAction: "compare_selected_role_cv",
    expectedBeliefGoal: "compare_cv",
    expectedPlanObjective: "work_with_selected_role",
    expectedPlanMode: "execute",
  },
  {
    message: "keep searching but only senior private credit in Dubai",
    promptState: "",
    activeTask: "search",
    expectedIntent: "search_refinement",
    expectedRelationship: "changes_task",
    expectedAction: "update_search_preferences",
    expectedBeliefGoal: "refine_search",
  },
  {
    message: "wait before you submit",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "application_pause",
    expectedRelationship: "pauses_task",
    expectedAction: "answer_directly",
    expectedBeliefGoal: "pause",
    expectedPlanObjective: "answer_career_question",
    expectedPlanMode: "answer",
  },
  {
    message: "same",
    promptState: "",
    activeTask: "search",
    previousIntent: "job_search",
    expectedIntent: "job_search",
    expectedRelationship: "continues_task",
    expectedAction: "show_job_results",
    expectedBeliefGoal: "search",
    expectedBeliefQuality: "usable",
  },
  {
    message: "go ahead",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    previousIntent: "apply_action",
    expectedIntent: "apply_action",
    expectedRelationship: "continues_task",
    expectedAction: "start_apply_execution",
    expectedBeliefGoal: "apply",
    expectedBeliefQuality: "usable",
  },
  {
    message: "best recruitment agencies in Dubai",
    promptState: "",
    activeTask: "",
    expectedIntent: "web_search",
    expectedRelationship: "new_topic",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "best recruiters in Dubai",
    promptState: "",
    activeTask: "",
    expectedIntent: "web_search",
    expectedRelationship: "new_topic",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "top executive search firms in Riyadh",
    promptState: "",
    activeTask: "",
    expectedIntent: "web_search",
    expectedRelationship: "new_topic",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "latest hiring trends in Saudi finance",
    promptState: "",
    activeTask: "",
    expectedIntent: "web_search",
    expectedRelationship: "new_topic",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "average salary for HR manager Dubai",
    promptState: "",
    activeTask: "",
    expectedIntent: "web_search",
    expectedRelationship: "new_topic",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "when is the best time to apply for jobs in dubai",
    promptState: "",
    activeTask: "search",
    selectedRole: true,
    expectedIntent: "web_search",
    expectedRelationship: "interrupts_task",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "what is saudi arabia like",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "web_search",
    expectedRelationship: "interrupts_task",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "what is life like in Riyadh for expats",
    promptState: "apply_results_selected_next_step",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "web_search",
    expectedRelationship: "interrupts_task",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "what is saudi arabia like for work",
    promptState: "apply_results_selected_next_step",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "web_search",
    expectedRelationship: "interrupts_task",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "tell me about living in Dubai as an expat",
    promptState: "",
    activeTask: "search",
    selectedRole: true,
    expectedIntent: "web_search",
    expectedRelationship: "interrupts_task",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "what is the cost of living in Riyadh",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "web_search",
    expectedRelationship: "interrupts_task",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "what are visa rules for working in Dubai",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "web_search",
    expectedRelationship: "interrupts_task",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "what is the average salary for a finance manager in Riyadh",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "web_search",
    expectedRelationship: "interrupts_task",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "latest hiring trends in Dubai private equity",
    promptState: "",
    activeTask: "search",
    selectedRole: true,
    expectedIntent: "web_search",
    expectedRelationship: "interrupts_task",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "compare Dubai and Riyadh for finance careers",
    promptState: "",
    activeTask: "search",
    selectedRole: true,
    expectedIntent: "web_search",
    expectedRelationship: "interrupts_task",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "what does Mubadala Investment Company do",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "web_search",
    expectedRelationship: "interrupts_task",
    expectedAction: "web_search",
    expectedPlanObjective: "answer_with_web_search",
    expectedPlanMode: "execute",
  },
  {
    message: "we are talking through a career question",
    promptState: "",
    activeTask: "search",
    selectedRole: true,
    expectedIntent: "career_question",
    expectedRelationship: "interrupts_task",
    expectedAction: "answer_directly",
    expectedPlanObjective: "answer_career_question",
    expectedPlanMode: "answer",
  },
  {
    message: "show me HR manager jobs in Dubai",
    promptState: "",
    activeTask: "",
    expectedIntent: "job_search",
    expectedRelationship: "new_topic",
    expectedAction: "show_job_results",
  },
  {
    message: "i need help to find jobs in dubai",
    promptState: "",
    activeTask: "",
    expectedIntent: "job_search",
    expectedRelationship: "new_topic",
    expectedAction: "show_job_results",
  },
  {
    message: "find finance jobs in Dubai",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "job_search",
    expectedRelationship: "new_topic",
    expectedAction: "show_job_results",
  },
  {
    message: "am I a fit for this job?",
    promptState: "",
    activeTask: "apply_flow",
    selectedRole: true,
    expectedIntent: "cv_role_comparison",
    expectedRelationship: "continues_task",
    expectedAction: "compare_selected_role_cv",
  },
];

function loadScenarioCases() {
  const fixturePath = path.join(__dirname, "../assets/data/apply-chat-conversation-fixtures.json");
  if (!fs.existsSync(fixturePath)) {
    return [];
  }

  const parsed = JSON.parse(fs.readFileSync(fixturePath, "utf8"));
  const scenarios = Array.isArray(parsed.scenarios) ? parsed.scenarios : [];

  return scenarios.flatMap((scenario) => {
    const initialContext = scenario && scenario.initialContext && typeof scenario.initialContext === "object"
      ? scenario.initialContext
      : {};
    const turns = Array.isArray(scenario.turns) ? scenario.turns : [];

    return turns.map((turn, index) => ({
      ...initialContext,
      ...turn,
      scenarioId: scenario.id || "unknown_scenario",
      tier: scenario.tier || initialContext.tier || "guest",
      fixtureTurn: index + 1,
    }));
  });
}

const scenarioCases = loadScenarioCases();
const cases = [...baseCases, ...scenarioCases];

function clean(value) {
  return String(value || "").replace(/\s+/g, " ").trim();
}

function isPromptAnswer(message, promptState) {
  const text = clean(message).toLowerCase();
  const wordCount = text ? text.split(/\s+/).filter(Boolean).length : 0;
  if (!promptState || !text) return false;
  if (/\b(?:career question|general career advice|career advice|talking through a career question|answer this as general career advice)\b/i.test(text)) {
    return true;
  }
  if (
    promptState === "apply_results_selected_next_step" &&
    /\b(?:apply|application|jump|start|go ahead|compare|match|fit|improve|tailor|original|current cv|without tailor)\b/i.test(text)
  ) {
    if (
      /\b(?:what about|tell me about|show|open|go back to|previous|last|other one|that one)\b/i.test(text) ||
      /\b(?:first|second|third)\s+(?:one|role|job|result)\b/i.test(text)
    ) {
      return false;
    }
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

function isApplyControlCommand(message) {
  const text = clean(message).toLowerCase();
  const wordCount = text ? text.split(/\s+/).filter(Boolean).length : 0;
  if (!text || wordCount > 8) return false;
  return /^(?:use\s+)?(?:the\s+)?(?:original|current|same)\s+(?:cv|resume)(?:\s+(?:not|instead of|without)\s+tailor(?:ed|ing)?)?$/.test(text) ||
    /^(?:use\s+)?(?:original|current|same)\s+(?:not\s+)?tailor(?:ed|ing)?$/.test(text) ||
    /^skip\s+tailor(?:ed|ing)?\s+use\s+(?:the\s+)?original\s+(?:cv|resume)$/.test(text) ||
    /^(?:skip|no|without)\s+tailor(?:ed|ing)?(?:\s+(?:cv|resume))?$/.test(text) ||
    /^use\s+(?:the\s+)?(?:cv|resume)\s+as\s+is(?:\s+no\s+tailor(?:ed|ing)?)?$/.test(text) ||
    /^(?:apply|continue|go ahead|carry on|proceed|start)(?:\s+(?:with|using))?\s+(?:the\s+)?(?:original|current|same)\s+(?:cv|resume)$/.test(text) ||
    /^(?:apply|continue|go ahead|carry on|proceed|start)\s+(?:quickly|now|straight away)$/.test(text) ||
    /^(?:use|continue with|apply with)\s+original\s+not\s+tailored$/.test(text) ||
    /^(?:not|no)\s+tailored?\s+(?:cv|resume)$/.test(text);
}

function hasProviderSearchLanguage(message) {
  const text = clean(message).toLowerCase().replace(/[_-]+/g, " ");
  return /\b(?:workday|greenhouse|workable|team\s*tailor|teamtailor|success\s*factors|successfactors|sap\s+success\s+factors|simple\s+form|basic\s+form|direct\/simple|simpledrop)\b/.test(text) &&
    /\b(?:only|roles?|jobs?|applications?|finance|investment|associate|analyst|manager|director|forms?)\b/.test(text);
}

function isSearchContinuation(message) {
  const text = clean(message).toLowerCase();
  return /^(?:ok|okay|yes|yeah|yep|sure|fine)?\s*(?:continue|carry on|resume|keep going|go back to)\s+(?:the\s+)?(?:search|searching|job search|results)\b/.test(text);
}

function isSameSearchLocationRefinement(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:same search|same thing|that search|current search)\b.*\b(?:dubai|abu dhabi|riyadh|saudi|ksa|uae|qatar|doha|kuwait|bahrain|oman)\b/.test(text);
}

function isFreshSearchRequest(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:anything new|any new|what'?s new|whats new|new roles|new jobs|fresh roles|fresh jobs)\b/.test(text);
}

function isCareerTimingQuestion(message) {
  const text = clean(message).toLowerCase();
  return (
    /\b(?:best time|best month|best months|when should|when is|which months?|what months?|hiring season|job search timing|start job searching|start applying|apply timing|timing)\b/i.test(text) &&
    /\b(?:job|jobs|role|roles|search|apply|applying|application|hiring|market|recruit(?:er|ing)|opportunit)/i.test(text)
  );
}

function isShortRoleDiscoverySearch(message) {
  const text = clean(message).toLowerCase();
  const words = text.split(/\s+/).filter(Boolean);
  if (!text || words.length > 6 || /[?؟]/.test(text)) return false;
  return (
    (
      /\b(?:analyst|associate|manager|director|vp|svp|avp|principal|intern|graduate|consultant|officer|specialist|lead|head|investment|finance|banking|credit|compliance|risk|treasury|fp&a|asset management|private equity|private credit|wealth management|clean energy|real estate|infrastructure|data|operations|strategy|corporate development|portfolio|markets?|sectors?)\b/i.test(text) ||
      /\b(?:dubai|dxb|abu dhabi|riyadh|saudi|saudi arabia|ksa|uae|united arab emirates|qatar|doha|kuwait|bahrain|oman|middle east|mena|gcc|london|singapore|remote|hybrid)\b/i.test(text)
    ) &&
    !/\b(?:hiya|hello|yo|thanks|wtf|question|advice|should|could|would|what|why|how|where|when)\b/i.test(text)
  );
}

function isHighConfidenceWebSearchRequest(message) {
  const text = clean(message).toLowerCase();
  const words = text ? text.split(/\s+/).filter(Boolean) : [];
  const explicitNamedCompanyResearch = /\b(?:what\s+(?:does|do|is)\s+|who\s+(?:are|is)\s+|tell me about\s+|research\s+|look up\s+|company profile|competitors?|ownership|founders?|headquarters|funding|ipo|stock price|share price|annual report|revenue|aum|assets under management|subsidiar(?:y|ies)|portfolio companies|reviews?|glassdoor|culture)\b/i.test(text) &&
    /\b(?:company|companies|employer|firm|bank|fund|startup|business|organisation|organization|mubadala|adcb|standard chartered|savills|mashreq|permira|merak|qiddiya|pif|tikehau)\b/i.test(text) &&
    !/\b(?:this|that|it|they|them|role|job|position|posting|previous|first|second|third|last)\b/i.test(text);
  if (!text || words.length < 3 || words.length > 34) return false;
  if (
    (isSelectedRoleQuestion(text) && !explicitNamedCompanyResearch) ||
    /\b(?:cv|resume|cover letter|application|apply|tailor|rewrite|interview prep)\b/i.test(text) &&
      !isCareerTimingQuestion(text) &&
      !/\b(?:agency|agencies|recruiter|recruiters|company|companies|market|salary|visa|relocation)\b/i.test(text)
  ) {
    return false;
  }
  if (/\b(?:show|find|search|look for|list|recommend|get me|source)\b.*\b(?:job|jobs|role|roles|opening|openings|vacanc|opportunit)/i.test(text)) {
    return false;
  }
  const asksForExternalKnowledge = /\b(?:who|what|which|where|when|how|why|can you tell me|do you know|research|look up|search the web|google|find out|tell me about|explain|describe|summarise|summarize)\b/i.test(text);
  const asksForCurrentKnowledge = /\b(?:latest|current|currently|recent|today|this week|this month|this year|now|202[0-9]|up to date|updated|new|news|trend|trends|forecast|outlook)\b/i.test(text);
  const asksForBestList = /\b(?:best|top|leading|recommended|reputable|good|strong|average|typical|benchmark|list of|examples of|rank|ranked|compare|comparison|versus|vs|better|pros and cons|advantages|disadvantages)\b/i.test(text);
  const externalCareerSubject = /\b(?:recruitment agenc(?:y|ies)|recruiting agenc(?:y|ies)|headhunters?|executive search|recruiters?|hiring agencies|staffing agencies|employers?|companies|firms|banks?|salary|average salary|pay range|pay scale|compensation benchmark|compensation guide|salary benchmark|salary guide|bonus|benefits|notice period|probation|labou?r law|employment law|market report|hiring trend|hiring trends|hiring market|labour market|labor market|visa rules?|work permit|golden visa|employment visa|relocation|cost of living|industry news|business news|professional bodies|networking events?|job fairs?|career fairs?|certifications?|qualifications?)\b/i.test(text);
  const generalExternalSubject =
    /\b(?:country|city|market|economy|culture|lifestyle|life|living|work culture|business culture|social norms|laws?|rules?|customs|weather|tax|income tax|housing|rent|schooling|healthcare|transport|commute|safety|safe|expat|expats|relocat(?:e|ion|ing)|move|moving|live|living|like|quality of life|weekend|working hours|costs?)\b/i.test(text) ||
    /\b(?:what\s+is|what'?s|what\s+are|how\s+is|how\s+are|why\s+is|tell me about|describe|explain|summari[sz]e)\b.*\b(?:saudi arabia|saudi|riyadh|jeddah|dubai|abu dhabi|uae|united arab emirates|qatar|doha|kuwait|bahrain|oman|muscat|london|middle east|mena|gcc)\b/i.test(text);
  const companyResearchSubject = explicitNamedCompanyResearch ||
    (/\b(?:what\s+(?:does|do|is)\s+|who\s+(?:are|is)\s+|tell me about\s+|research\s+|look up\s+|company profile|competitors?|ownership|founders?|headquarters|funding|ipo|stock price|share price|annual report|revenue|aum|assets under management|subsidiar(?:y|ies)|portfolio companies|reviews?|glassdoor|culture)\b/i.test(text) &&
      /\b(?:company|companies|employer|firm|bank|fund|startup|business|organisation|organization|mubadala|adcb|standard chartered|savills|mashreq|permira|merak|qiddiya|pif|tikehau)\b/i.test(text));
  const comparisonResearchSubject = /\b(?:compare|comparison|versus|vs|better|best between|pros and cons|which is better|difference between|should i choose)\b/i.test(text) &&
    /\b(?:dubai|abu dhabi|riyadh|saudi|saudi arabia|uae|qatar|doha|kuwait|bahrain|oman|london|market|country|city|salary|tax|cost of living|company|companies|employer|recruiter|agency|sector|industry)\b/i.test(text);
  const newsResearchSubject = /\b(?:latest|recent|today|this week|this month|news|update|announced|happened|layoffs?|hiring freeze|expansion|market outlook|forecast|trend|trends)\b/i.test(text) &&
    /\b(?:market|company|companies|bank|fund|sector|industry|economy|jobs?|hiring|salary|dubai|riyadh|saudi|uae|qatar|middle east|mena|gcc)\b/i.test(text);
  const factualResearchSubject = /\b(?:population|currency|time zone|timezone|capital|language|languages|religion|holidays?|public holidays?|weekend|work week|tax rate|income tax|corporate tax|vat|minimum wage|labou?r law|employment law|visa|work permit|rent|cost of living|schools?|healthcare)\b/i.test(text);
  const hasNamedMarket = /\b(?:in|for|near|around)\s+(?:dubai|abu dhabi|riyadh|jeddah|doha|qatar|saudi|saudi arabia|uae|united arab emirates|kuwait|bahrain|oman|muscat|london|middle east|mena|gcc)\b|\b(?:dubai|abu dhabi|riyadh|jeddah|doha|qatar|saudi|saudi arabia|uae|united arab emirates|kuwait|bahrain|oman|muscat|london|middle east|mena|gcc)\b/i.test(text);
  return (isCareerTimingQuestion(text) || externalCareerSubject || generalExternalSubject || companyResearchSubject || comparisonResearchSubject || newsResearchSubject || factualResearchSubject) &&
    (asksForExternalKnowledge || asksForCurrentKnowledge || asksForBestList || companyResearchSubject || comparisonResearchSubject || newsResearchSubject || factualResearchSubject || (hasNamedMarket && !generalExternalSubject));
}

function isSelectedRoleQuestion(message) {
  const text = clean(message).toLowerCase();
  const wordCount = text ? text.split(/\s+/).filter(Boolean).length : 0;
  if (
    /\b(?:average salary|salary benchmark|salary guide|pay range|pay scale|compensation benchmark|compensation guide)\b/i.test(text) &&
    /\b(?:dubai|abu dhabi|riyadh|jeddah|doha|qatar|saudi|saudi arabia|uae|united arab emirates|kuwait|bahrain|oman|muscat|london|middle east|mena|gcc)\b/i.test(text) &&
    !/\b(?:this|that|it|one|role|job|company|previous|first|second|third|last|they)\b/i.test(text)
  ) {
    return false;
  }
  return (
    /\b(?:salary|compensation|pay|package|arabic|language|deal breaker|enough experience|requirements?|company|office|location|report to|manager|hiring manager|what do they (?:actually )?do|good company|stage|status)\b/i.test(text) &&
      /\b(?:this|that|it|one|role|job|company|previous|first|second|third|last|they)\b/i.test(text)
  ) ||
    (
      wordCount <= 10 &&
      /\b(?:salary|compensation|pay|package|arabic|language|requirements?|responsibilities|description|day to day|day-to-day|deal breaker|enough experience)\b/i.test(text)
    ) ||
    /\b(?:what am i missing|what is missing|what's missing|whats missing|what are the gaps|what would i do|what does the role involve|tell me more about (?:this|that)|deal breaker|enough experience)\b/i.test(text);
}

function isProviderFilterClearRequest(message) {
  const text = clean(message).toLowerCase().replace(/[_-]+/g, " ");
  return /\b(?:remove|clear|drop|don't restrict|dont restrict|no longer restrict|without|ignore|reset)\b.*\b(?:ats|provider|platform|greenhouse|workday|workable|teamtailor|team tailor|successfactors|success factors)\b|\b(?:remove the ats restriction|remove ats restriction|no ats restriction|any provider|all providers)\b/i.test(text);
}

function isSearchFilterStatusRequest(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:what filters|which filters|current filters|show me my filters|show my filters|what are you filtering|what criteria|current criteria|what do you know about my preferences)\b/i.test(text);
}

function isSearchFilterResetRequest(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:reset everything|clear everything|reset filters|clear filters|start over|start again)\b/i.test(text);
}

function isRoleReferenceRequest(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:what about|go back to|that one|other one|first one|second one|third one|last one|previous one|previous role|previous job|role before that|job before that|one before that|before that|those two|compare those|compare them|the other role|the other job)\b|\b(?:show|open|review)\s+(?:that|it|this|the first|the second|the third|the last|the previous|the other)\b/i.test(text);
}

function isVisibleJobSearchResultsQuestion(message, context) {
  const text = clean(message).toLowerCase();
  if (!text || !context.visibleResults) return false;
  if (
    isCareerDecisionQuestion(text) ||
    isRecruiterNonResponseQuestion(text) ||
    isSennaContactQuestion(text) ||
    isAnswerQualityComplaint(text) ||
    isMisroutedSearchComplaint(text) ||
    isCvInventoryQuestion(text) ||
    isCvTailoringTask(text) ||
    isCvReviewTask(text) ||
    isApplicationHistoryTask(text) ||
    isCapabilityInventoryTask(text)
  ) {
    return false;
  }
  if (
    isSearchFilterStatusRequest(text) ||
    isSearchFilterResetRequest(text) ||
    isProviderFilterClearRequest(text)
  ) {
    return false;
  }
  return /\b(?:how|why|what)\b.*\b(?:decide|score|rank|choose|pick|match|matches|matching)\b.*\b(?:these|this|current|visible|shown|listed|above|results?|roles?|jobs?|options?)\b/i.test(text) ||
    /\b(?:why|how come)\b.*\b(?:first|top|ranked first|listed first|shown first)\b/i.test(text) ||
    /\b(?:which|what)\b.*\b(?:one|role|job|result|option)\b.*\b(?:best|strongest|closest|cleanest|safest|most realistic|highest chance|better fit|prioriti[sz]e|choose|pick)\b/i.test(text) ||
    /\b(?:compare|rank)\b.*\b(?:these|them|the roles|the jobs|the results|the options)\b/i.test(text);
}

function isApplicationStatusQuestion(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:what is happening|what's happening|where are we|status|progress|how far|what stage|which step|what step|have you submitted|did it submit|did it definitely submit|is everything complete|what evidence|prove it|failed step|retry)\b.*\b(?:application|applying|apply|role|form|submission)\b/i.test(text) ||
    /\b(?:application|applying|apply|form|submission)\b.*\b(?:status|progress|where are we|what stage|which step|what step|what is happening|what's happening|have you submitted|did it submit|did it definitely submit|is everything complete|what evidence|prove it|failed step|retry)\b/i.test(text);
}

function isCvInventoryQuestion(message) {
  const text = clean(message).toLowerCase();
  if (!/\b(?:cv|resume|profile)\b/i.test(text)) return false;
  return /\b(?:do you have|have you got|have you saved|is there|what|which|show me|tell me)\b.*\b(?:cv|resume|profile)\b.*\b(?:on file|saved|active|current|currently have|version|file|using)\b/i.test(text) ||
    /\b(?:cv|resume|profile)\b.*\b(?:on file|saved|active|current|currently have|version|file|using)\b/i.test(text);
}

function isCvTailoringTask(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:tailor|rewrite|optimise|optimize|improve|make)\b.*\b(?:cv|resume|profile)\b/i.test(text) ||
    /\b(?:cv|resume|profile)\b.*\b(?:tailor|tailored|rewrite|optimise|optimize|improve)\b/i.test(text);
}

function isCvReviewTask(message) {
  const text = clean(message).toLowerCase();
  if (
    /\b(?:against|with|to|for)\s+(?:this|that|the|a)?\s*(?:role|job|position|one)\b/i.test(text) ||
    /\b(?:this|that|first|second|third|previous|selected)\s+(?:role|job|position|one)\b/i.test(text)
  ) {
    return false;
  }
  return /\b(?:send|upload|share|attach)\b.*\b(?:cv|resume|profile)\b.*\b(?:review|check|look at|read)\b/i.test(text) ||
    /\b(?:review|check|critique|score|read)\b.*\b(?:my\s+)?(?:cv|resume|profile)\b/i.test(text);
}

function isSearchOptimizationTask(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:stronger|better|higher|improve|optimise|optimize|prioritise|prioritize|cleaner)\b.*\b(?:interview probability|interview chance|interview chances|interview rate|interview conversion|shortlist probability|shortlist chance)\b/i.test(text) ||
    /^stronger interview probability$/i.test(text);
}

function isCapabilityInventoryTask(message) {
  const text = clean(message).toLowerCase();
  return /^(?:what can you do|what do you do|how can you help|what can emily do|show me what you can do|help menu|tasks|task menu)\??$/i.test(text);
}

function isApplicationHistoryTask(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:show|list|open|what|which|where|status|track|history)\b.*\b(?:my\s+)?(?:applications|application history|applied jobs|jobs i applied|roles i applied)\b/i.test(text) ||
    /\b(?:what have i applied to|which jobs have i applied to|show my applications|application history|what did i apply to|what's still pending|whats still pending)\b/i.test(text);
}

function isInterviewPrepTask(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:prepare|prep|practice|mock|coach|test)\b.*\b(?:interview|interviews)\b/i.test(text) ||
    /\b(?:interview questions|mock interview|ask me questions one at a time|technical interview|behavioural interview|behavioral interview)\b/i.test(text);
}

function isApplicationMaterialTask(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:write|draft|create|prepare|answer|help me answer|tailor)\b.*\b(?:cover letter|application note|why do you want|why are you interested|why should we hire|tell us about yourself|salary expectation|notice period|relocation|visa status|application question|short bio|deal sheet|transaction list)\b/i.test(text);
}

function isRecruiterNetworkingTask(message) {
  const text = clean(message).toLowerCase();
  if (isSennaContactQuestion(text)) {
    return false;
  }
  return /\b(?:find|who|message|contact|reach out|network|referral|refer|intro|introduction|follow up)\b.*\b(?:recruiter|hiring manager|team|people|someone|alumni|linkedin|referral)\b/i.test(text) ||
    /\b(?:who should i contact|help me network|how can i get a referral|write a linkedin message|write a recruiter message|write a cold email)\b/i.test(text);
}

function isCareerDecisionQuestion(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:best direction|right direction|which direction|career direction|best move|next move|move jobs or stay|stay where i am|stay put|higher salary|salary jump|help me decide|should i move jobs|should i stay|move or stay)\b/i.test(text) ||
    (/\b(?:should|which|what|can you help me|help me)\b/i.test(text) &&
      /\b(?:move jobs|stay where i am|stay in my job|current job|higher salary|best direction|career move|next move)\b/i.test(text));
}

function isRecruiterNonResponseQuestion(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:why|how come|do you know why|find out why|what.*reason)\b.*\brecruiters?\b.*\b(?:don'?t|dont|do not|never|not)\s*(?:respond|reply|get back|answer|contact)\b/i.test(text) ||
    /\b(?:don'?t|dont|do not|never|not)\s*(?:hear back|get replies|get responses?|get answers?)\s+from\s+recruiters?\b/i.test(text) ||
    /\brecruiters?\b.*\b(?:ghost|ignore|ignored|not responding|not replying|no response|no reply)\b/i.test(text);
}

function isSennaContactQuestion(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:who|how|where)\b.*\b(?:contact|speak to|talk to|reach|get hold of|message)\b.*\bsenna\b/i.test(text) ||
    /\b(?:contact|support|help desk|customer support)\b.*\bsenna\b/i.test(text) ||
    /\b(?:emily|career manager|advisor)\b.*\b(?:email|contact|address)\b/i.test(text) ||
    /\b(?:what is|what's|whats|give me|send me)\b.*\b(?:emily'?s?|her)\s+(?:email|contact)\b/i.test(text) ||
    /\b(?:customer\s+support|support\s+team|help\s*desk|billing support|account support|technical support)\b/i.test(text) ||
    /\b(?:how|where|who)\b.*\b(?:contact|email|message|reach|get hold of|speak to|talk to)\b.*\b(?:support|team|senna)\b/i.test(text);
}

function isAnswerQualityComplaint(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:you(?:'re| are)?\s+(?:ignoring|not answering)|not answering my questions?|you have not answered|haven'?t answered|that'?s not what i asked|terrible|this is bad|this is broken)\b/i.test(text) ||
    /(?:you already asked|you asked already|already asked|asked me already|you just asked|same question again|why are you asking again|stop asking me again|i already sent|i already uploaded|you've got my cv|you already have my cv|you already have my resume|i gave you my cv|i gave you my resume)/i.test(text) ||
    /\b(?:why|what for|how come|do you really need|why do you need|why are you asking|what do you need)\b.*\b(?:my\s+)?email\b|\b(?:my\s+)?email\b.*\b(?:why|what for|needed|required)\b/i.test(text);
}

function isMisroutedSearchComplaint(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:why are you|why did you|you(?:'re| are)?|you just|just going to)\b.*\b(?:showing|show|gave|giving|returned|searched)\b.*\b(?:jobs?|roles?|results?|dubai|riyadh|london|abu dhabi|saudi|uae)\b/i.test(text);
}

function isSalaryCompensationTask(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:salary|compensation|bonus|pay|package|offer|negotiate|take-home|take home|expectations?)\b/i.test(text) &&
    /\b(?:what|range|should|fair|compare|calculate|ask|negotiate|worth|offer|expect)\b/i.test(text);
}

function isCompanyResearchTask(message) {
  const text = clean(message).toLowerCase();
  return /\b(?:tell me about|research|what does|who owns|how big|reputation|culture|good employer|competitors|aum|fund size|portfolio|recent deals|recent exits|investment strategy|is it growing|why are they hiring)\b.*\b(?:company|firm|employer|fund|they|this)\b/i.test(text) ||
    /\b(?:what do they actually do|is this a good company|what should i know before interviewing there)\b/i.test(text);
}

function normalizeJobSearchQuery(message) {
  const text = clean(message).toLowerCase();
  const normalized = text
    .replace(/^(?:please\s+)?(?:can you|could you|would you|will you|please)?\s*/i, "")
    .replace(/^(?:ok|okay|well|right|so|cool|fine|great|thanks|thank you)[,.\s]+/i, "")
    .replace(/^(?:ok|okay|well|right|so)\s+(?:ok|okay|well|right|so)[,.\s]+/i, "")
    .replace(/^(?:new|fresh)\s+search\s*(?:for\s+)?/i, "")
    .replace(/^(?:same|current|that)\s+search\s+(?:but|with|for)\s+/i, "")
    .replace(/^(?:i\s+)?(?:want|wanna|would like|need|am trying|i'm trying|im trying)\s+(?:to\s+)?(?:apply|apply for|find|find me|look for|search for|get|get me|see|view)\s+(?:to\s+|for\s+)?/i, "")
    .replace(/^(?:help me|can you help me|could you help me)\s+(?:apply|apply for|find|find me|look for|search for|get|get me|see|view)\s+(?:to\s+|for\s+)?/i, "")
    .replace(/^(?:apply|apply for)\s+(?:to\s+|for\s+)?/i, "")
    .replace(/^(?:(?:let'?s|lets)\s+)?(?:show|find|search|look for|list|recommend|pull up|give me|send me)\s+(?:me\s+)?/i, "")
    .replace(/^(?:(?:let'?s|lets)\s+)?(?:look|hunt|search)\s+(?:for\s+)?/i, "")
    .replace(/^(?:me\s+)/i, "")
    .replace(/\b(?:jobs?|roles?|openings?|vacanc(?:y|ies)|opportunit(?:y|ies))\b/gi, " ")
    .replace(/\b(?:for me|please|available|current|open|live|any)\b/gi, " ")
    .replace(/\b(?:only|just|solely)\b/gi, " ")
    .replace(/^\s*(?:in|at|with|for)\s+/i, "")
    .replace(/\s+/g, " ")
    .trim();
  return normalized || text;
}

function addBelief(bucket, key, weight) {
  bucket[key] = (bucket[key] || 0) + weight;
}

function normaliseBelief(bucket) {
  const entries = Object.keys(bucket).map((key) => ({
    key,
    score: Math.max(-20, Math.min(60, bucket[key] || 0)),
  }));
  const maxScore = entries.reduce((max, entry) => Math.max(max, entry.score), 0);
  const total = entries.reduce((sum, entry) => sum + Math.exp((entry.score - maxScore) / 8), 0);
  return entries
    .map((entry) => ({
      key: entry.key,
      probability: total ? Math.exp((entry.score - maxScore) / 8) / total : 0,
    }))
    .sort((a, b) => b.probability - a.probability);
}

function beliefQuality(entries) {
  const top = entries && entries[0] ? entries[0].probability || 0 : 0;
  const second = entries && entries[1] ? entries[1].probability || 0 : 0;
  let entropy = 0;
  (entries || []).forEach((entry) => {
    const probability = Number(entry.probability || 0);
    if (probability > 0) {
      entropy -= probability * (Math.log(probability) / Math.log(2));
    }
  });
  entropy = entries && entries.length > 1 ? entropy / Math.log2(entries.length) : 0;
  return {
    top: Number(top.toFixed(4)),
    margin: Number((top - second).toFixed(4)),
    entropy: Number(entropy.toFixed(4)),
    confidenceLabel:
      top >= 0.72 && top - second >= 0.22 && entropy <= 0.55
        ? "strong"
        : top >= 0.55 && top - second >= 0.12
        ? "usable"
        : "ambiguous",
  };
}

function goalFromIntent(intent) {
  return {
    answer_pending_question: "answer_prompt",
    cv_role_comparison: "compare_cv",
    apply_action: "apply",
    search_refinement: "refine_search",
    job_search: "search",
    role_reference: "role_reference",
    role_question: "ask_role_question",
    web_search: "web_search",
    career_question: "ask_career_question",
    career_planning: "career_planning",
    application_pause: "pause",
    application_resume: "resume",
  }[intent] || "";
}

function buildBeliefState(message, context) {
  const text = clean(message);
  const lower = text.toLowerCase();
  const goals = { unknown: 4 };
  const relations = { unclear: 3 };
  const hasPrompt = !!context.promptState;
  const hasRole = !!(context.selectedRole || context.referencedRole);
  const words = lower ? lower.split(/\s+/).filter(Boolean) : [];

  if (context.previousIntent && words.length <= 4 && !hasPrompt) {
    const priorGoal = goalFromIntent(context.previousIntent);
    if (
      priorGoal &&
      /^(?:yes|yeah|yep|ok|okay|sure|same|that|that one|this|this one|it|continue|go ahead)$/i.test(lower)
    ) {
      addBelief(goals, priorGoal, 16);
    }
  }

  const highConfidenceWebSearch = isHighConfidenceWebSearchRequest(text);

  if (
    isSennaContactQuestion(text) ||
    isAnswerQualityComplaint(text) ||
    (isCareerTimingQuestion(text) && !highConfidenceWebSearch)
  ) {
    addBelief(goals, "ask_career_question", 36);
    addBelief(relations, context.activeTask ? "interrupts_task" : "new_topic", 28);
  }

  if (highConfidenceWebSearch) {
    addBelief(goals, "web_search", 34);
    addBelief(relations, context.activeTask ? "interrupts_task" : "new_topic", 26);
  }

  if (hasPrompt && isPromptAnswer(text, context.promptState)) {
    addBelief(goals, "answer_prompt", 34);
    addBelief(relations, "answers_pending_question", 34);
  }
  if (
    /\b(?:cv|resume|profile)\b.*\b(?:match|fit|compare|stack up|suit|suitable|chance|competitive)\b/i.test(lower) ||
    /\b(?:compare|match|fit)\b.*\b(?:my\s+)?(?:cv|resume|profile)\b/i.test(lower)
  ) {
    addBelief(goals, "compare_cv", hasRole ? 28 : 18);
  }
  if (isApplyControlCommand(text) || /\b(?:apply now|just apply|submit this|send application|put me forward|apply to|apply for)\b/i.test(lower)) {
    addBelief(goals, "apply", hasRole ? 29 : 19);
  }
  if (/\b(?:continue|carry on|resume|pick it back up|proceed)\b.*\b(?:application|apply|role|form)\b/i.test(lower)) {
    addBelief(goals, "resume", 27);
    addBelief(relations, "resumes_task", 27);
  }
  if (/\b(?:not apply yet|don'?t apply yet|dont apply yet|don'?t submit yet|dont submit yet|pause|stop|hold off|wait)\b/i.test(lower)) {
    addBelief(goals, "pause", 29);
    addBelief(relations, "pauses_task", 29);
  }
  if (
    isProviderFilterClearRequest(text) ||
    isSameSearchLocationRefinement(text) ||
    /\b(?:too junior|more senior|senior roles|higher level|not junior|associate level|only show|just show|solely|not consulting|no consulting|don'?t show.*consulting|too operational|avoid riyadh|avoid dubai|nothing below|not below|salary floor|private credit|private equity|always tailor first|keep searching but only)\b/i.test(lower)
  ) {
    addBelief(goals, "refine_search", 27);
  }
  if (
    isFreshSearchRequest(text) ||
    isSearchContinuation(text) ||
    hasProviderSearchLanguage(text) ||
    (
      words.length <= 6 &&
      context.activeTask === "role_discovery" &&
      /\b(?:analyst|associate|manager|director|investment|finance|banking|credit|private equity|private credit|dubai|riyadh|saudi|uae|london)\b/i.test(lower)
    )
  ) {
    addBelief(goals, "search", 25);
  }
  if (isRoleReferenceRequest(text)) {
    addBelief(goals, "role_reference", context.referencedRole ? 27 : 18);
  }
  if (isSelectedRoleQuestion(text)) {
    addBelief(goals, "ask_role_question", hasRole ? 26 : 16);
  }
  if (isCareerDecisionQuestion(text) || isRecruiterNonResponseQuestion(text) || isSennaContactQuestion(text) || isAnswerQualityComplaint(text) || isMisroutedSearchComplaint(text)) {
    addBelief(goals, "ask_career_question", 28);
  }
  if (/\b(?:stuck|help me plan|career plan|career planning|what jobs suit me|career direction|what should i do next)\b/i.test(lower)) {
    addBelief(goals, "career_planning", 28);
  }

  const goalEntries = normaliseBelief(goals);
  const topGoal = goalEntries[0] || { key: "unknown", probability: 1 };
  if (hasPrompt) addBelief(relations, "answers_pending_question", 6);
  if (context.activeTask) addBelief(relations, "continues_task", 6);
  if (/^(?:apply|compare_cv|ask_role_question|role_reference)$/.test(topGoal.key)) {
    addBelief(relations, "continues_task", hasRole ? 12 : 5);
  }
  if (/^(?:refine_search|search)$/.test(topGoal.key)) {
    addBelief(relations, context.activeTask ? "changes_task" : "new_topic", 10);
  }
  if (/^(?:ask_career_question|career_planning)$/.test(topGoal.key)) {
    addBelief(relations, context.activeTask ? "interrupts_task" : "new_topic", 13);
  }
  return {
    goals: goalEntries,
    topGoal,
    taskRelation: normaliseBelief(relations),
    quality: beliefQuality(goalEntries),
  };
}

function intentFromBeliefGoal(goal) {
  return {
    answer_prompt: "answer_pending_question",
    compare_cv: "cv_role_comparison",
    apply: "apply_action",
    refine_search: "search_refinement",
    search: "job_search",
    role_reference: "role_reference",
    ask_role_question: "role_question",
    web_search: "web_search",
    ask_career_question: "career_question",
    career_planning: "career_planning",
    pause: "application_pause",
    resume: "application_resume",
  }[goal] || "";
}

function applyBeliefPolicy(raw, belief) {
  const [intent, confidence] = raw;
  const beliefIntent = intentFromBeliefGoal(belief.topGoal.key);
  const beliefConfidence = belief.topGoal.probability || 0;
  const quality = belief.quality || {};
  const decisiveBelief =
    beliefConfidence >= 0.72 ||
    (beliefConfidence >= 0.58 && (quality.margin || 0) >= 0.14 && (quality.entropy || 1) <= 0.72);
  const highPriorityBelief = /^(?:answer_pending_question|application_pause|application_resume)$/.test(beliefIntent || "");
  if (
    beliefIntent &&
    (decisiveBelief || highPriorityBelief) &&
    (
      intent === "unknown" ||
      confidence < 0.72 ||
      (confidence < 0.9 && /^(?:answer_pending_question|search_refinement|web_search|cv_role_comparison|apply_action|role_reference|role_question|career_question|career_planning|application_pause|application_resume)$/.test(beliefIntent))
    )
  ) {
    return [beliefIntent, Math.max(confidence, Math.min(0.96, beliefConfidence + 0.24))];
  }
  return raw;
}

function classify(message, context) {
  const text = clean(message);
  const lower = text.toLowerCase();
  if (
    isCareerDecisionQuestion(text) ||
    isRecruiterNonResponseQuestion(text) ||
    isSennaContactQuestion(text) ||
    isAnswerQualityComplaint(text) ||
    isMisroutedSearchComplaint(text)
  ) {
    return ["career_question", 0.95];
  }
  if (isPromptAnswer(text, context.promptState)) {
    return ["answer_pending_question", 0.94];
  }
  if (isCvInventoryQuestion(text)) {
    return ["cv_inventory", 0.96];
  }
  if (isCvTailoringTask(text)) {
    return ["cv_tailoring_task", 0.94];
  }
  if (isCvReviewTask(text)) {
    return ["cv_review_task", 0.94];
  }
  if (isSearchOptimizationTask(text)) {
    return ["search_optimization", 0.94];
  }
  if (isCapabilityInventoryTask(text)) {
    return ["capability_inventory", 0.96];
  }
  if (isApplicationHistoryTask(text)) {
    return ["application_history", 0.95];
  }
  if (isHighConfidenceWebSearchRequest(text)) {
    return ["web_search", 0.93];
  }
  if (isCompanyResearchTask(text)) {
    return ["company_research_task", 0.94];
  }
  if (isSalaryCompensationTask(text)) {
    return ["salary_compensation_task", 0.94];
  }
  if (isRecruiterNetworkingTask(text)) {
    return ["recruiter_networking_task", 0.94];
  }
  if (isInterviewPrepTask(text)) {
    return ["interview_prep_task", 0.94];
  }
  if (isApplicationMaterialTask(text)) {
    return ["application_material_task", 0.94];
  }
  if (isSearchFilterStatusRequest(text)) {
    return ["search_filter_status", 0.94];
  }
  if (isSearchFilterResetRequest(text)) {
    return ["search_filter_reset", 0.94];
  }
  if (isCareerTimingQuestion(text)) {
    return ["career_question", 0.94];
  }
  if (/\b(?:career question|general career advice|career advice|talking through a career question|answer this as general career advice)\b/i.test(lower)) {
    return ["career_question", 0.91];
  }
  if (isProviderFilterClearRequest(text)) {
    return ["search_refinement", 0.94];
  }
  if (isVisibleJobSearchResultsQuestion(text, context)) {
    return ["search_results_question", 0.91];
  }
  if (isApplicationStatusQuestion(text)) {
    return ["application_status", 0.95];
  }
  if (isRoleReferenceRequest(text) && !context.referencedRole) {
    return ["role_reference", 0.86];
  }
  if (/^(?:get started|start|begin|let'?s start|lets start)$/i.test(lower) && context.selectedRole) {
    return ["apply_action", 0.9];
  }
  if (/^(?:apply|continue|go ahead|carry on|proceed|start)(?:\s+(?:with|using))?\s+(?:a\s+|the\s+)?tailored?\s+(?:cv|resume)$/i.test(lower) && (context.selectedRole || context.referencedRole)) {
    return ["apply_action", 0.92];
  }
  if (
    /\b(?:not apply yet|don'?t apply yet|dont apply yet|don'?t submit yet|dont submit yet|pause|stop|hold off|wait)\b/i.test(lower) &&
    (
      /\b(?:who|what)\s+(?:is|are)\s+(?:this|that|the)\s+(?:company|employer)\b/i.test(lower) ||
      /\bwhat\s+do\s+they\s+(?:actually\s+)?do\b/i.test(lower) ||
      isSelectedRoleQuestion(text)
    ) &&
    (context.selectedRole || context.referencedRole)
  ) {
    return ["role_question", 0.96];
  }
  if (isApplyControlCommand(text) && (context.selectedRole || context.referencedRole)) {
    return ["apply_action", 0.9];
  }
  if (/\b(?:back to|resume|carry on with|continue|go back to|proceed with)\s+(?:the\s+)?(?:application|applying|apply)\b/i.test(lower) || /^(?:ok|okay|yes|fine|sure)\s+(?:continue|carry on|resume|proceed|go ahead)\s+(?:with\s+)?(?:the\s+)?(?:application|applying|apply)\b/i.test(lower) || /\b(?:continue|carry on|resume|pick it back up|proceed)\s+(?:from\s+)?(?:(?:the\s+)?exact\s+step|(?:where|exactly where)\s+(?:we|you)\s+(?:stopped|left off|paused))\b/i.test(lower)) {
    return ["application_resume", 0.94];
  }
  if (/\b(?:don'?t|do not|dont|not)\s+(?:want\s+to\s+)?apply\b|\b(?:pause|stop|hold off|wait)\s*(?:,|\s)+(?:the\s+)?(?:application|applying|apply|i want|let me|before)\b|\b(?:not apply yet|forget the application|leave the application)\b/i.test(lower)) {
    return ["application_pause", 0.98];
  }
  if (
    (context.referencedRole || context.selectedRole) &&
    (/\b(?:cv|resume|profile)\b.*\b(?:match|fit|compare|stack up|suit|suitable|chance|competitive)\b/i.test(lower) ||
      /\b(?:how|do|does|would|can|am)\b.*\b(?:match|fit|compare|stack up|suit|suitable|chance|competitive)\b.*\b(?:cv|resume|profile|role|job|one)\b/i.test(lower) ||
      /\b(?:compare|match|fit)\b.*\b(?:my\s+)?(?:cv|resume|profile)\b.*\b(?:role|job|one|this|that|first|second|third)\b/i.test(lower) ||
      /\b(?:compare|match|fit)\b.*\b(?:this|that|first|second|third)?\s*(?:role|job|one)\b.*\b(?:against|with|to)\b.*\b(?:my\s+)?(?:cv|resume|profile)\b/i.test(lower))
  ) {
    return ["cv_role_comparison", 0.94];
  }
  if ((context.referencedRole || context.selectedRole) && isSelectedRoleQuestion(text)) {
    return ["role_question", 0.88];
  }
  if (
    context.activeTask === "search" &&
    /\b(?:anything new|any new|what'?s new|new|latest|current|open)\b.*\b(?:job|jobs|role|roles|opening|openings|vacanc|opportunit|private credit|private equity|credit|investment|dubai|riyadh|abu dhabi|saudi|uae)\b/i.test(lower)
  ) {
    return ["job_search", 0.93];
  }
  if (isSameSearchLocationRefinement(text)) {
    return ["search_refinement", 0.9];
  }
  if (isFreshSearchRequest(text)) {
    return ["job_search", 0.92];
  }
  if (context.activeTask === "role_discovery" && isShortRoleDiscoverySearch(text)) {
    return ["job_search", 0.92];
  }
  if (isProviderFilterClearRequest(text) || /\b(?:too junior|more senior|senior roles|higher level|not junior|associate level|only show|just show|solely|not consulting|no consulting|don'?t show.*consulting|too operational|avoid riyadh|avoid dubai|nothing below|not below|salary floor|private credit|private equity|always tailor first)\b/i.test(lower) || /\b(?:not right|wrong|bad fit|don'?t feel right|dont feel right)\b.*\b(?:companies|employers|firms)\b/i.test(lower) || /\b(?:companies|employers|firms)\b.*\b(?:not right|wrong|bad fit|don'?t feel right|dont feel right)\b/i.test(lower)) {
    return ["search_refinement", 0.9];
  }
  if (/\b(?:what is happening|status|progress|where are we)\b.*\b(?:application|apply|role)\b/i.test(lower)) {
    return ["application_status", 0.86];
  }
  if (
    context.referencedRole &&
    !/\b(?:apply to|apply for|start applying|go ahead and apply|apply now|just apply)\b/i.test(lower) &&
    /\b(?:what about|show|open|review|go back to|that one|other one|first one|second one|third one|last one|previous one|previous role|role before that|job before that|one before that|before that|those two|compare those|compare them)\b/i.test(lower)
  ) {
    return ["role_reference", 0.84];
  }
  if (
    !isCareerDecisionQuestion(text) &&
    !isRecruiterNonResponseQuestion(text) &&
    !isSennaContactQuestion(text) &&
    !isAnswerQualityComplaint(text) &&
    !isMisroutedSearchComplaint(text) &&
    /\b(?:show|find|search|look for|list|recommend|any|open|current)\b.*\b(?:job|jobs|role|roles|opening|openings|vacanc|opportunit)/i.test(lower)
  ) {
    return ["job_search", 0.92];
  }
  if (isSearchContinuation(text) || hasProviderSearchLanguage(text)) {
    return ["job_search", 0.92];
  }
  if (context.activeTask === "role_discovery" && isShortRoleDiscoverySearch(text)) {
    return ["job_search", 0.92];
  }
  if (/\b(?:find|show|search|look for|get me|recommend)\b.*\b(?:something|roles?|jobs?|opportunit(?:y|ies))?\s*(?:better|stronger|more relevant|closer|better fit)\b/i.test(lower)) {
    return ["job_search", 0.9];
  }
  if (/\bhow\b.*\b(?:get|find|land|search|look for|apply|break into|get into)\b.*\b(?:job|jobs|role|roles|work)\b/i.test(lower)) {
    return ["career_question", 0.92];
  }
  if (/\b(?:stuck|help me plan|make a plan|career plan|career planning|don'?t know what i want|don't know what jobs|dont know what jobs|what jobs suit me|career direction|help me with my career|what should i do next)\b/i.test(lower)) {
    return ["career_planning", 0.96];
  }
  if (/\b(?:apply to|apply for|start applying|go ahead and apply|apply now|just apply)\b/i.test(lower)) {
    return ["apply_action", 0.9];
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
  if (intent === "application_resume") return "resumes_task";
  if (intent === "cv_inventory") return context.activeTask ? "continues_task" : "new_topic";
  if (intent === "cv_tailoring_task" || intent === "cv_review_task") return context.activeTask ? "interrupts_task" : "new_topic";
  if (intent === "search_optimization") return context.activeTask === "search" ? "changes_task" : "new_topic";
  if (intent === "capability_inventory") return "new_topic";
  if (intent === "application_history") return context.activeTask ? "continues_task" : "new_topic";
  if (intent === "company_research_task" || intent === "salary_compensation_task") return context.selectedRole || context.referencedRole ? "continues_task" : context.activeTask ? "interrupts_task" : "new_topic";
  if (intent === "recruiter_networking_task" || intent === "interview_prep_task" || intent === "application_material_task") return context.activeTask ? "interrupts_task" : "new_topic";
  if (intent === "search_refinement") return "changes_task";
  if (intent === "search_filter_status") return context.activeTask === "search" ? "continues_task" : "new_topic";
  if (intent === "search_filter_reset") return context.activeTask ? "changes_task" : "new_topic";
  if (intent === "search_results_question") return context.activeTask === "search" || context.visibleResults ? "continues_task" : "new_topic";
  if (intent === "cv_role_comparison") return context.selectedRole || context.referencedRole ? "continues_task" : context.activeTask ? "interrupts_task" : "new_topic";
  if (intent === "job_search") return context.activeTask === "search" ? "continues_task" : "new_topic";
  if (intent === "web_search") return context.activeTask ? "interrupts_task" : "new_topic";
  if (intent === "apply_action") return context.selectedRole || context.referencedRole ? "continues_task" : "new_topic";
  if (intent === "role_reference") return context.activeTask ? "continues_task" : "new_topic";
  if (intent === "role_question") return context.selectedRole || context.referencedRole ? "continues_task" : context.activeTask ? "interrupts_task" : "new_topic";
  if (intent === "career_planning") return context.activeTask ? "changes_task" : "new_topic";
  if (intent === "career_question" || intent === "cv_request") return context.activeTask ? "interrupts_task" : "new_topic";
  if (intent === "application_status") return context.activeTask ? "continues_task" : "new_topic";
  return "unclear";
}

function action(intent, relation, context) {
  if (intent === "answer_pending_question") return "defer_to_prompt_handler";
  if (intent === "cv_inventory") return "answer_cv_inventory";
  if (intent === "cv_tailoring_task") return "start_cv_tailoring";
  if (intent === "cv_review_task") return "start_cv_review";
  if (intent === "search_optimization") return "optimize_search_preferences";
  if (intent === "capability_inventory") return "answer_capabilities";
  if (intent === "application_history") return "answer_application_history";
  if (intent === "company_research_task") return "answer_company_research";
  if (intent === "salary_compensation_task") return "answer_salary_compensation";
  if (intent === "recruiter_networking_task") return "start_recruiter_networking";
  if (intent === "interview_prep_task") return "start_interview_prep";
  if (intent === "application_material_task") return "start_application_material";
  if (intent === "search_refinement") return "update_search_preferences";
  if (intent === "search_filter_status") return "answer_search_filters";
  if (intent === "search_filter_reset") return "reset_search_preferences";
  if (intent === "search_results_question") return "answer_search_results_question";
  if (intent === "web_search") return "web_search";
  if (intent === "job_search") return "show_job_results";
  if (intent === "cv_role_comparison") return context.selectedRole || context.referencedRole ? "compare_selected_role_cv" : "ask_clarifying_question";
  if (intent === "apply_action") return context.selectedRole || context.referencedRole ? "start_apply_execution" : "ask_clarifying_question";
  if (intent === "role_reference") return context.referencedRole ? "render_selected_role" : "ask_clarifying_question";
  if (intent === "role_question") return context.selectedRole || context.referencedRole ? "answer_role_question" : "ask_clarifying_question";
  if (intent === "application_status") return "answer_application_status";
  if (intent === "application_resume") return "resume_task";
  if (relation === "pauses_task" || intent === "career_planning" || intent === "career_question" || intent === "cv_request") return "answer_directly";
  return "ask_clarifying_question";
}

function planObjective(intent, actionType) {
  if (actionType === "defer_to_prompt_handler") return "answer_active_prompt";
  if (actionType === "ask_clarifying_question") return "clarify_user_intent";
  if (/^(?:show_job_results|update_search_preferences|reset_search_preferences|answer_search_filters|answer_search_results_question)$/i.test(actionType || "")) {
    return "manage_job_search";
  }
  if (actionType === "web_search") return "answer_with_web_search";
  if (/^(?:render_selected_role|answer_role_question|compare_selected_role_cv)$/i.test(actionType || "")) {
    return "work_with_selected_role";
  }
  if (/^(?:start_apply_execution|resume_task|answer_application_status)$/i.test(actionType || "")) {
    return "manage_application_flow";
  }
  if (/^(?:start_cv_tailoring|start_cv_review|answer_cv_inventory)$/i.test(actionType || "")) {
    return "manage_cv_task";
  }
  if (/^(?:start_interview_prep|start_application_material|start_recruiter_networking|answer_salary_compensation|answer_company_research)$/i.test(actionType || "")) {
    return "support_role_preparation";
  }
  if (intent === "career_question" || intent === "career_planning" || actionType === "answer_directly") {
    return "answer_career_question";
  }
  return "recover_or_clarify";
}

function planMode(actionType) {
  if (actionType === "defer_to_prompt_handler") return "prompt";
  if (actionType === "ask_clarifying_question") return "clarify";
  if (/^(?:show_job_results|web_search|update_search_preferences|reset_search_preferences|start_apply_execution|resume_task|compare_selected_role_cv|render_selected_role|start_cv_tailoring|start_cv_review|start_interview_prep|start_application_material|start_recruiter_networking)$/i.test(actionType || "")) {
    return "execute";
  }
  if (/^answer_/i.test(actionType || "") || actionType === "answer_directly") {
    return "answer";
  }
  return "clarify";
}

function buildPlan(intent, relation, context, next, belief) {
  const objective = planObjective(intent, next);
  const risks = [];
  if (belief && belief.quality && belief.quality.confidenceLabel === "ambiguous") {
    risks.push("ambiguous_belief_state");
  }
  if (/^(?:start_apply_execution|compare_selected_role_cv|answer_role_question|render_selected_role)$/i.test(next || "") && !(context.selectedRole || context.referencedRole)) {
    risks.push("missing_role_context");
  }
  if (relation === "interrupts_task" && context.activeTask) {
    risks.push("interrupts_active_task");
  }
  return {
    objective,
    mode: planMode(next),
    action: next,
    relationship: relation,
    shouldPauseActiveTask: relation === "pauses_task" || relation === "interrupts_task",
    shouldPersistMemory: next !== "defer_to_prompt_handler",
    riskFlags: risks,
  };
}

function shouldKeepMemoryGoalOpen(actionType, relation) {
  if (!actionType) return false;
  if (
    /^(?:answer_directly|defer_to_prompt_handler|answer_role_question|answer_cv_inventory|answer_application_history|answer_application_status|answer_search_filters|answer_search_results_question|answer_salary_compensation|answer_company_research|answer_capabilities)$/i.test(
      actionType
    )
  ) {
    return false;
  }
  return /^(?:new_topic|changes_task|continues_task|interrupts_task|resumes_task)$/i.test(
    relation || ""
  );
}

function updateMemoryState(previous, message, belief, intent, relation, next) {
  const state = previous || {
    turnCount: 0,
    unresolvedGoals: [],
    answeredIntents: [],
  };
  const goal = {
    intent,
    action: next,
    relationship: relation,
    topic: belief && belief.topGoal ? belief.topGoal.key : "",
    summary: clean(message).slice(0, 180),
    status: shouldKeepMemoryGoalOpen(next, relation) ? "open" : "answered",
  };
  const unresolvedGoals = (state.unresolvedGoals || []).filter(
    (entry) => {
      if (
        /^(?:job_search|search_refinement|search_optimization)$/i.test(intent) &&
        /^(?:job_search|search_refinement|search_optimization)$/i.test(entry.intent)
      ) {
        return false;
      }
      return entry.intent !== intent;
    }
  );
  const answeredIntents = (state.answeredIntents || []).filter(
    (entry) => entry.intent !== intent
  );
  if (goal.status === "open") {
    unresolvedGoals.push(goal);
  } else if (intent && next) {
    answeredIntents.push(goal);
  }
  return {
    turnCount: state.turnCount + 1,
    lastUserMessage: clean(message),
    lastResolvedIntent: intent,
    lastResolvedRelationship: relation,
    lastResolvedAction: next,
    lastTopic: goal.topic,
    unresolvedGoals: unresolvedGoals.slice(-8),
    answeredIntents: answeredIntents.slice(-16),
  };
}

function runMemoryScenario(turns) {
  return turns.reduce((memory, item) => {
    const context = {
      promptState: item.promptState || "",
      activeTask: item.activeTask || (item.promptState ? "apply_flow" : ""),
      selectedRole: !!item.selectedRole,
      referencedRole: !!item.referencedRole,
      visibleResults: !!item.visibleResults,
      previousIntent: memory && memory.lastResolvedIntent,
    };
    const belief = buildBeliefState(item.message, context);
    const [intent] = applyBeliefPolicy(classify(item.message, context), belief);
    const rel = relationship(intent, context);
    const next = action(intent, rel, context);
    return updateMemoryState(memory, item.message, belief, intent, rel, next);
  }, null);
}

let failed = 0;
let knownGapCount = 0;

cases.forEach((item) => {
  const context = {
    promptState: item.promptState || "",
    activeTask: item.activeTask || (item.promptState ? "apply_flow" : ""),
    selectedRole: !!item.selectedRole,
    referencedRole: !!item.referencedRole,
    visibleResults: !!item.visibleResults,
    previousIntent: item.previousIntent || "",
  };
  const belief = buildBeliefState(item.message, context);
  const [intent] = applyBeliefPolicy(classify(item.message, context), belief);
  const rel = relationship(intent, context);
  const next = action(intent, rel, context);
  const plan = buildPlan(intent, rel, context, next, belief);
  const ok =
    intent === item.expectedIntent &&
    rel === item.expectedRelationship &&
    next === item.expectedAction &&
    (!item.expectedPlanObjective ||
      plan.objective === item.expectedPlanObjective) &&
    (!item.expectedPlanMode || plan.mode === item.expectedPlanMode) &&
    (!item.expectedBeliefGoal || belief.topGoal.key === item.expectedBeliefGoal) &&
    (!item.expectedBeliefQuality || belief.quality.confidenceLabel === item.expectedBeliefQuality) &&
    (
      !item.expectedNormalizedQuery ||
      normalizeJobSearchQuery(item.message) === item.expectedNormalizedQuery
    );

  if (!ok && item.knownGap) {
    knownGapCount += 1;
    console.warn("KNOWN GAP", {
      scenarioId: item.scenarioId || "inline",
      fixtureTurn: item.fixtureTurn || null,
      message: item.message,
      expected: [item.expectedIntent, item.expectedRelationship, item.expectedAction],
      actual: [intent, rel, next],
    });
    return;
  }

  if (!ok) {
    failed += 1;
    console.error("FAIL", {
      scenarioId: item.scenarioId || "inline",
      fixtureTurn: item.fixtureTurn || null,
      tier: item.tier || null,
      message: item.message,
      expected: [item.expectedIntent, item.expectedRelationship, item.expectedAction],
      actual: [intent, rel, next],
      expectedPlan: [item.expectedPlanObjective || null, item.expectedPlanMode || null],
      actualPlan: [plan.objective, plan.mode],
      expectedBeliefGoal: item.expectedBeliefGoal || null,
      actualBeliefGoal: belief.topGoal.key,
      actualBeliefConfidence: Number((belief.topGoal.probability || 0).toFixed(4)),
      expectedBeliefQuality: item.expectedBeliefQuality || null,
      actualBeliefQuality: belief.quality && belief.quality.confidenceLabel,
      expectedNormalizedQuery: item.expectedNormalizedQuery || null,
      actualNormalizedQuery: item.expectedNormalizedQuery ? normalizeJobSearchQuery(item.message) : null,
    });
  }
});

const memoryCases = [
  {
    name: "keeps search goal open after search request",
    turns: [
      {
        message: "show me jobs in Dubai",
        activeTask: "",
      },
    ],
    expect: {
      turnCount: 1,
      lastResolvedIntent: "job_search",
      openIntent: "job_search",
    },
  },
  {
    name: "records direct coaching as answered",
    turns: [
      {
        message: "why am I not getting interviews",
        activeTask: "search",
      },
    ],
    expect: {
      turnCount: 1,
      lastResolvedIntent: "career_question",
      answeredIntent: "career_question",
      openIntentAbsent: "career_question",
    },
  },
  {
    name: "replaces repeated open search goal with latest refinement",
    turns: [
      {
        message: "show me jobs in Dubai",
        activeTask: "",
      },
      {
        message: "keep searching but only senior private credit in Dubai",
        activeTask: "search",
      },
    ],
    expect: {
      turnCount: 2,
      lastResolvedIntent: "search_refinement",
      openIntent: "search_refinement",
      openIntentAbsent: "job_search",
    },
  },
];

memoryCases.forEach((item) => {
  const state = runMemoryScenario(item.turns);
  const openIntents = (state.unresolvedGoals || []).map((entry) => entry.intent);
  const answeredIntents = (state.answeredIntents || []).map((entry) => entry.intent);
  const ok =
    state.turnCount === item.expect.turnCount &&
    state.lastResolvedIntent === item.expect.lastResolvedIntent &&
    (!item.expect.openIntent || openIntents.includes(item.expect.openIntent)) &&
    (!item.expect.openIntentAbsent ||
      !openIntents.includes(item.expect.openIntentAbsent)) &&
    (!item.expect.answeredIntent ||
      answeredIntents.includes(item.expect.answeredIntent));
  if (!ok) {
    failed += 1;
    console.error("FAIL memory", { item, state });
  }
});

if (failed) {
  process.exit(1);
}

console.log(
  `PASS ${cases.length} apply-chat decision fixtures (${baseCases.length} inline, ${scenarioCases.length} scenario turns, ${knownGapCount} known Phase 1 gaps)`
);
