import { processTask } from "../src/worker.js";

const url =
  process.argv[2] ||
  "https://job-boards.eu.greenhouse.io/capitaldynamicsag/jobs/4904305101";

const requiredQuestions = [
  ["question_9413079101", "What is your desired base salary?", "text", "40000"],
  ["question_9413080101", "What is your current notice period?", "text", "2 months"],
  [
    "question_9413081101",
    "Data Use Declaration: I consent to the processing by Capital Dynamics AG and its affiliate companies of the information and data contained in this form as needed to progress the application process or maintain an approval. I agree that Capital Dynamics may make any enquiries in relation to this information and data for the purposes of verifying it.",
    "single_select",
    "Yes",
  ],
  ["question_9413082101", "How do you describe your gender identity?", "single_select", "Male"],
  ["question_9413083101", "What is your ethnic group or background?", "single_select", "White"],
  ["question_9413084101", "Do you Identify as LGBTQ+?", "single_select", "No"],
  ["question_9413085101", "Are you living with a disability?", "single_select", "No"],
  ["question_9413087101", "Are you a Veteran?", "single_select", "No"],
  ["question_9413088101", "Are you a Parent or Care-taker?", "single_select", "No"],
  ["question_9413089101", "How many languages do you speak?", "single_select", "1"],
  ["question_9413090101", "Are you local to the office applied to?", "single_select", "Yes"],
  ["question_9413091101", "Do you require a visa, to work in the country you are applying to?", "single_select", "No"],
  ["question_9413092101", "What is your highest level of education?", "single_select", "Bachelors"],
  ["question_9413093101", "How many days do you currently work from the office?", "text", "4"],
  [
    "question_9413094101",
    "Capital Dynamics are committed to cultivating a collaborative work environment. As such we operate a 4 days in-office work week. Are you able to work from the CD office 4 days per week?",
    "single_select",
    "Yes",
  ],
];

const applicationAnswers = Object.fromEntries(
  requiredQuestions.map(([name, , , answer]) => [name, answer])
);

const task = {
  task_uuid: `verify-worker-${Date.now()}`,
  provider: "greenhouse",
  application_url: url,
  candidate_name: "Luca Valentino Rosati",
  candidate_email: "greenhouse-dry-run@example.com",
  candidate_phone: "",
  cv_file_url: "data:application/pdf;base64,JVBERi0xLjQKJSBkcnkgcnVuIHBsYWNlaG9sZGVyCg==",
  cv_file_name: "sffc-greenhouse-test-cv.pdf",
  cover_letter_requested: 0,
  payload: {
    application_answers: applicationAnswers,
    application_schema: {
      questions: requiredQuestions.map(([name, label, type]) => ({
        label,
        required: true,
        fields: [{ name, type }],
      })),
    },
  },
};

const result = await processTask(task);
const failed = (result.missing_required_fields || []).filter(Boolean);
const interceptedRequest = result.intercepted_submit_request || null;
const interceptedPayloadReady =
  Boolean(interceptedRequest) &&
  interceptedRequest.has_job_application === true &&
  interceptedRequest.candidate_fields_present?.first_name === true &&
  interceptedRequest.candidate_fields_present?.last_name === true &&
  interceptedRequest.candidate_fields_present?.email === true &&
  Number(interceptedRequest.question_field_count || 0) >= 15;
const summary = {
  status: result.status,
  form_ready: result.form_ready,
  uploaded_resume: result.uploaded_resume,
  application_answers_attempted: result.application_answers_attempted,
  application_answers_filled: result.application_answers_filled,
  application_choice_answers_attempted: result.application_choice_answers_attempted,
  application_choice_answers_filled: result.application_choice_answers_filled,
  missing_required_fields: failed,
  intercepted_submit_request: interceptedRequest,
  intercepted_payload_ready: interceptedPayloadReady,
  field_diagnostics: (result.application_field_diagnostics || []).map((field) => ({
    label: field.label,
    field_names: field.field_names,
    choice_like: field.choice_like,
    control_found: field.control_found,
    role: field.role,
    value_present: field.value_present,
    selected_text: field.selected_text,
    hidden_value_present: field.hidden_value_present,
  })),
  screenshot_path: result.local_screenshot_path,
};

console.log(JSON.stringify(summary, null, 2));

if (!result.form_ready || !result.uploaded_resume || (!interceptedPayloadReady && failed.length > 0)) {
  process.exitCode = 1;
}
