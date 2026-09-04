#!/usr/bin/env node
const path = require("path");
const { chromium } = require("playwright");

const repoRoot = path.resolve(__dirname, "..");
const chatScript = path.join(
  repoRoot,
  "assets/js/crm/crm-apply-chat-article.js"
);

function makeCases() {
  const base = [
    ["What does a private equity associate actually do?", "response"],
    ["How do I move from audit into private equity?", "response"],
    ["Should I do CFA Level 1 before applying to asset management?", "response"],
    ["Can you review my CV?", "response"],
    ["Review my CV for investment banking roles", "response"],
    ["Find me private banking jobs in Dubai", "search"],
    ["private equity jobs Riyadh", "search"],
    ["investment analyst salary Dubai", "response"],
    ["What salary should I expect as an associate in Abu Dhabi?", "response"],
    ["Is this role worth applying to?", "response"],
    ["Why do recruiters ghost after interviews?", "response"],
    ["I applied to 60 jobs and got no interviews. What should I change?", "response"],
    ["Can I ask a question first?", "response"],
    ["Hi Emily, are you good?", "response"],
    ["Yo!!", "response"],
    ["Hiya", "response"],
    ["wtf this feels like a waste of time", "response"],
    ["This is confusing, what happens next?", "response"],
    ["I work at Deloitte in audit", "statement"],
    ["I have 4 years of valuation experience", "statement"],
    ["My salary is AED 22k", "statement"],
    ["I'm based in Dubai", "statement"],
    ["I want to work in private equity", "statement"],
    ["I want you to find jobs in Dubai", "response"],
    ["I need a visa", "statement"],
    ["I need help finding a job", "response"],
    ["I need you to review this CV", "response"],
    ["Thanks", "statement"],
    ["yes", "statement"],
    ["no", "statement"],
    ["Rohith Roy", "statement"],
    ["rossvltn@gmail.com", "statement"],
    ["AED 45,000", "statement"],
    ["What I did was build the model", "statement"],
    ["How I got into finance was through an internship", "statement"],
    ["Why I left was compensation", "statement"],
    ["Do you think I can get PE?", "response"],
    ["I do think PE suits me", "statement"],
    ["Can I move from compliance to risk?", "response"],
    ["I can move to Riyadh immediately", "statement"],
    ["Should I quit without another job?", "response"],
    ["CFA or MBA for asset management?", "response"],
    ["private credit vs private equity", "response"],
    ["Best recruiters in Dubai for wealth management", "response"],
    ["What are good finance jobs for introverts?", "response"],
    ["How much does payment processing knowledge matter in fintech?", "response"],
    ["I am seeking a new opportunity in Compliance with CCO CFCS CME-1 CME-2", "response"],
    ["Hope you are doing well. I am looking for a job opportunity in UAE with 15 years valuation and finance Big4. Please suggest.", "response"],
    ["I came across the Investment Operations Analyst role in Riyadh. Are there similar roles in Dubai?", "response"],
    ["I have no degree but VC and startup experience. What paid roles can I target?", "response"],
    ["Recent CS grad preparing for Bloomberg Analytics/Sales interviews. What should I focus on?", "response"],
    ["How do I gather practical knowledge for wealth management?", "response"],
    ["Is it time to switch jobs?", "response"],
    ["When should a rising senior start applying?", "response"],
    ["What does carried interest mean?", "response"],
    ["Explain EBITDA simply", "response"],
    ["What is an LBO?", "response"],
    ["What is MOIC?", "response"],
    ["What is NAV?", "response"],
    ["What is a sukuk?", "response"],
    ["What is ADGM?", "response"],
    ["What is DIFC?", "response"],
    ["What does FP&A do?", "response"],
    ["What is financial control?", "response"],
    ["What does transaction services mean?", "response"],
    ["Can you compare FDD and investment banking?", "response"],
    ["Is audit to corporate finance realistic?", "response"],
    ["Would a family office consider my background?", "response"],
    ["Am I too senior for analyst roles?", "response"],
    ["Am I too junior for associate roles?", "response"],
    ["What roles should I target with KYC experience?", "response"],
    ["Compliance jobs UAE", "search"],
    ["Head of Compliance Saudi Arabia", "search"],
    ["Risk manager jobs Qatar", "search"],
    ["wealth management recruiter contacts Dubai", "search"],
    ["investment banking interview questions", "response"],
    ["How should I answer tell me about yourself?", "response"],
    ["Can you improve this bullet?", "response"],
    ["Responsible for preparing financial models", "statement"],
    ["Rewrite: responsible for preparing financial models", "response"],
    ["What is wrong with my LinkedIn headline?", "response"],
    ["Do I need Arabic for GCC finance roles?", "response"],
    ["This role requires Arabic but my CV doesn't show it", "statement"],
    ["I speak Arabic", "statement"],
    ["I don't speak Arabic", "statement"],
    ["I have ACCA", "statement"],
    ["I don't have ACCA", "statement"],
    ["I passed CFA Level II", "statement"],
    ["Should I mention CFA Level II candidate?", "response"],
    ["Do employers care about GPA?", "response"],
    ["My GPA is 3.7", "statement"],
    ["I graduated from LSE", "statement"],
    ["Does non-target hurt me?", "response"],
    ["How do I network with recruiters?", "response"],
    ["Can you write a recruiter message?", "response"],
    ["Write a LinkedIn message to a PE recruiter", "response"],
    ["What companies should I apply to?", "response"],
    ["Show me similar roles", "response"],
    ["Apply to this role", "response"],
    ["Start Auto Apply", "response"],
    ["Continue applying in the background", "response"],
    ["What happens after you apply?", "response"],
    ["Can Senna apply for me?", "response"],
    ["Is my data safe?", "response"],
    ["Why do you need my email?", "response"],
    ["Why do you need my name?", "response"],
    ["Can I use a different CV?", "response"],
    ["I already applied", "statement"],
    ["I got rejected yesterday", "statement"],
    ["I got rejected yesterday, what should I do?", "response"],
    ["I have an interview tomorrow", "statement"],
    ["I have an interview tomorrow, help me prepare", "response"],
    ["What questions will they ask?", "response"],
    ["How do I negotiate an offer?", "response"],
    ["I got an offer for AED 30k", "statement"],
    ["I got an offer for AED 30k, should I take it?", "response"],
    ["Should I ask for more money?", "response"],
    ["What is a fair bonus?", "response"],
    ["How do I ask for a raise without sounding emotional?", "response"],
    ["Should I negotiate salary after I get the offer?", "response"],
    ["How do I follow up after applying to a Dubai role?", "response"],
    ["Should I ask someone at the company for a referral straight away?", "response"],
    ["Are hidden jobs real in Dubai or is that just LinkedIn advice?", "response"],
    ["How do I make my LinkedIn profile show up in recruiter searches?", "response"],
    ["What are the biggest CV mistakes recruiters see in the UAE?", "response"],
    ["Should I rely on recruiters for my GCC job search?", "response"],
    ["How should I prepare for a video interview?", "response"],
    ["What jobs are more protected from AI?", "response"],
    ["What exactly does Senna do for my job search?", "response"],
    ["Is Riyadh better than Dubai for finance?", "response"],
    ["Dubai or Riyadh for private banking?", "response"],
    ["London vs Dubai for PE?", "response"],
    ["What about Abu Dhabi?", "response"],
    ["And Saudi?", "response"],
    ["same for VP?", "response"],
    ["VP?", "response"],
    ["Dubai?", "response"],
    ["I prefer Dubai", "statement"],
    ["No Saudi roles", "statement"],
    ["Only remote", "statement"],
    ["Hybrid preferred", "statement"],
    ["I can relocate", "statement"],
    ["I cannot relocate", "statement"],
    ["No visa sponsorship needed", "statement"],
    ["I need sponsorship", "statement"],
    ["Do I need sponsorship for UAE?", "response"],
    ["Can you search jobs that sponsor visas?", "response"],
    ["What's the fastest route into PE?", "response"],
    ["How do I get into venture capital?", "response"],
    ["What paid roles can I target without a degree?", "response"],
    ["Do I need a degree for VC?", "response"],
    ["How do I position unpaid startup work?", "response"],
    ["I helped raise $120k for a startup", "statement"],
    ["I built pitch decks and cap tables", "statement"],
    ["Does that count as deal experience?", "response"],
    ["What should my CV headline say?", "response"],
    ["Can you create a profile summary?", "response"],
    ["I want senior relationship management roles", "statement"],
    ["Private banking UAE Saudi", "search"],
    ["family office investment advisory GCC", "search"],
    ["Tell me the best route for private banker moving from India to GCC", "response"],
    ["Do you offer quarterly plans?", "response"],
    ["What is included in the plan?", "response"],
    ["Can a job search manager source roles for me?", "response"],
    ["Will someone approach recruiters?", "response"],
    ["I don't want to pay before seeing value", "response"],
    ["This sounds fake", "response"],
    ["Are these real jobs?", "response"],
    ["Are you just scraping LinkedIn?", "response"],
    ["Can I talk to a person?", "response"],
    ["Book a call", "response"],
    ["Cancel my subscription", "response"],
    ["I can't log in", "response"],
    ["I forgot my password", "response"],
    ["Reset password", "response"],
    ["Payment failed", "response"],
    ["My card was declined", "response"],
    ["The apply button is broken", "response"],
    ["The form won't load", "response"],
    ["The employer site refused to connect", "response"],
    ["Can you screenshot the form?", "response"],
    ["Can you submit Workable?", "response"],
    ["Can you submit Greenhouse?", "response"],
    ["Can you submit Workday?", "response"],
    ["What is the difference between embedded apply and apply for me?", "response"],
    ["I just want to apply myself", "statement"],
    ["Apply with current CV", "response"],
    ["Undo the tailoring", "response"],
    ["Use original CV", "response"],
    ["Don't tailor it", "response"],
    ["Tailor my CV", "response"],
    ["Tailor the CV to this role", "response"],
    ["Can I see the tailored version?", "response"],
    ["Do not add fake experience", "statement"],
    ["Only use what's in my CV", "statement"],
    ["I don't have that skill", "statement"],
    ["I do have budgeting experience", "statement"],
    ["I managed a team of 8", "statement"],
    ["I led budgeting and forecasting", "statement"],
    ["I worked in asset management", "statement"],
    ["I worked in education", "statement"],
    ["Education", "statement"],
    ["Skills", "statement"],
    ["Personal profile", "statement"],
    ["Project manager", "search"],
    ["project management roles Dubai", "search"],
    ["Can you not search education?", "response"],
    ["Why did you search investment banking?", "response"],
    ["That match is wrong", "response"],
    ["The CV parser is weak", "response"],
    ["I uploaded my CV", "statement"],
    ["Here's my CV", "statement"],
    ["Can you parse my CV?", "response"],
    ["What seniority am I?", "response"],
    ["What sector am I in?", "response"],
    ["Does my CV read as analyst or associate?", "response"],
    ["Do I look too junior?", "response"],
    ["My title is Director but I mostly supported reporting", "statement"],
    ["Does my CV read below my seniority?", "response"],
    ["What keywords am I missing?", "response"],
    ["What qualifications are missing?", "response"],
    ["Does this role require ACCA?", "response"],
    ["Does this job require Arabic?", "response"],
    ["What is the strongest part of my CV?", "response"],
    ["What is the weakest part?", "response"],
    ["Give me one quick insight", "response"],
    ["Don't do a full review", "statement"],
    ["No cover letter", "statement"],
    ["Yes cover letter", "statement"],
    ["Could you make it shorter?", "response"],
    ["Make it more senior", "response"],
    ["Make it sound less robotic", "response"],
    ["This answer is too generic", "response"],
    ["Be honest", "response"],
    ["Give me brutal feedback", "response"],
    ["I'm not convinced", "response"],
    ["keep testing", "response"],
    ["continue", "response"],
    ["What now?", "response"],
    ["Next", "response"],
    ["Ok continue", "response"],
    ["Nah", "statement"],
    ["No thanks", "statement"],
    ["Fine", "statement"],
    ["Correct", "statement"],
    ["Actually I have 6 years, not 4", "statement"],
    ["That's not right, I have 6 years", "response"],
    ["You misunderstood me", "response"],
    ["I meant Dubai not Riyadh", "statement"],
    ["Change it to Dubai", "response"],
    ["Search Dubai instead", "search"],
    ["Can you compare my CV against this JD?", "response"],
    ["This JD wants SAP and Oracle", "statement"],
    ["I don't have SAP", "statement"],
    ["Should I still apply without SAP?", "response"],
    ["The role asks for stakeholder management", "statement"],
    ["My CV doesn't mention stakeholder management", "statement"],
    ["How do I add stakeholder management without lying?", "response"],
    ["What are the benefits besides salary?", "response"],
    ["Do sales people get cars paid for?", "response"],
    ["Best area to stay in NYC for finance professionals?", "response"],
    ["Finance vs marketing?", "response"],
    ["Thoughts on CFA?", "response"],
    ["Advice on quant finance masters", "response"],
    ["How tf are yall making so much?", "response"],
    ["Is this company legit?", "response"],
    ["What does the company do?", "response"],
    ["Who is hiring in Dubai?", "response"],
    ["jobs", "search"],
    ["finance", "search"],
    ["Dubai finance", "search"],
    ["analyst London", "search"],
    ["compliance manager", "search"],
    ["private banker UAE", "search"],
    ["senior private banker GCC", "search"],
  ];
  const expandTheme = (theme, templates, subjects, expected = "response", limit = 36) =>
    templates
      .flatMap((template) =>
        subjects.map((subject) => [
          template.replace("{x}", subject),
          expected,
          theme,
        ])
      )
      .slice(0, limit);
  const themes = [
    ...expandTheme(
      "job_search_strategy_quality",
      [
        "What is the best time to job search in {x}?",
        "When should I start applying for jobs in {x}?",
        "What months are strongest for hiring in {x}?",
        "Should I wait until January to job search in {x}?",
        "How should I structure my job search in {x}?",
      ],
      [
        "Dubai",
        "Saudi Arabia",
        "the GCC",
        "Riyadh",
        "Abu Dhabi",
        "UAE finance",
        "Middle East private banking",
        "DIFC",
        "Saudi giga projects",
      ],
      "response",
      45
    ),
    ...expandTheme(
      "job_search_strategy_quality",
      [
        "How many jobs should I apply to for {x}?",
        "Should I use recruiters or direct apply for {x}?",
        "Why am I not getting interviews for {x}?",
        "How do I get more interviews for {x}?",
        "What is the best job hunt strategy for {x}?",
      ],
      [
        "Dubai finance",
        "GCC wealth management",
        "senior private banking",
        "Saudi finance roles",
        "entry level finance",
        "private equity in Dubai",
        "compliance roles in UAE",
        "FP&A manager roles",
        "investment analyst roles",
      ],
      "response",
      45
    ),
    ...expandTheme(
      "career_growth",
      [
        "How do I grow from {x}?",
        "What should I do next if I am currently {x}?",
        "How can I get promoted from {x}?",
        "What is the fastest way to progress from {x}?",
      ],
      [
        "financial analyst",
        "FP&A analyst",
        "audit senior",
        "investment analyst",
        "relationship manager",
        "KYC analyst",
        "finance manager",
        "senior accountant",
        "business analyst",
      ]
    ),
    ...expandTheme(
      "career_transition",
      [
        "How do I move from {x}?",
        "Can I switch from {x}?",
        "What route would you take from {x}?",
        "Is it realistic to transition from {x}?",
      ],
      [
        "audit to private equity",
        "valuation to PE deal team",
        "retail banking to wealth management",
        "compliance to risk",
        "marketing to finance",
        "data analytics to investment operations",
        "India private banking to GCC wealth management",
        "Big 4 FDD to investment banking",
        "treasury risk to markets",
      ]
    ),
    ...expandTheme(
      "salary",
      [
        "What salary should I expect for {x}?",
        "How much should {x} pay?",
        "Is my compensation fair for {x}?",
        "What bonus is normal for {x}?",
      ],
      [
        "private banking in Dubai",
        "VP finance in Riyadh",
        "investment analyst in Abu Dhabi",
        "FP&A manager in Saudi Arabia",
        "compliance head in UAE",
        "private equity associate in London",
        "wealth manager in GCC",
        "director of finance in Riyadh",
        "asset management analyst in Dubai",
      ]
    ),
    ...expandTheme(
      "profile_fit",
      [
        "Am I a fit for {x}?",
        "Would my profile work for {x}?",
        "How strong am I for {x}?",
        "Should I apply to {x}?",
      ],
      [
        "private equity associate",
        "director finance asset performance",
        "investment operations analyst",
        "head of compliance",
        "family office investment advisor",
        "wealth management relationship manager",
        "corporate finance manager",
        "real estate asset management",
        "venture capital associate",
      ]
    ),
    ...expandTheme(
      "stress_management",
      [
        "How do I handle stress in {x}?",
        "I'm overwhelmed by {x}, what should I do?",
        "How do I stop feeling anxious about {x}?",
        "What should I do if {x} is burning me out?",
      ],
      [
        "job searching",
        "interviews",
        "rejections",
        "a toxic manager",
        "working long hours",
        "waiting for an offer",
        "moving countries for work",
        "salary negotiation",
        "starting a new finance role",
      ]
    ),
    ...expandTheme(
      "role_information",
      [
        "What does {x} actually do?",
        "Tell me more about the {x} role",
        "What skills does {x} need?",
        "What does a normal day look like for {x}?",
      ],
      [
        "private equity associate",
        "investment operations analyst",
        "FP&A manager",
        "head of compliance",
        "private banker",
        "asset management analyst",
        "corporate finance director",
        "real estate asset manager",
        "transaction services manager",
      ]
    ),
    ...expandTheme(
      "qualifications_advice",
      [
        "Do I need {x}?",
        "Is {x} worth it for my career?",
        "Should I mention {x} on my CV?",
        "Will employers care about {x}?",
      ],
      [
        "CFA Level 1",
        "CFA Level 2 candidate status",
        "ACCA",
        "CPA",
        "an MBA",
        "a master's in finance",
        "FINRA Series 7",
        "CCO and CFCS",
        "CME-1 and CME-2",
      ]
    ),
    ...expandTheme(
      "senna_services",
      [
        "Can Senna help with {x}?",
        "What does Senna include for {x}?",
        "How does Emily handle {x}?",
        "Do I need to pay Senna for {x}?",
      ],
      [
        "applications submitted for me",
        "recruiter outreach",
        "CV tailoring",
        "finding GCC jobs",
        "tracking applications",
        "matching roles to my CV",
        "quarterly plans",
        "premium recruiter contacts",
        "background auto apply",
      ]
    ),
    ...expandTheme(
      "work_life_balance",
      [
        "How is work life balance in {x}?",
        "Is {x} usually intense?",
        "Which has better hours, {x}?",
        "Will {x} burn me out?",
      ],
      [
        "private equity",
        "investment banking",
        "asset management",
        "private banking",
        "FP&A",
        "Big 4 audit",
        "compliance",
        "Saudi giga projects",
        "Dubai finance roles",
      ]
    ),
    ...expandTheme(
      "high_paying_jobs",
      [
        "What are the highest paying jobs in {x}?",
        "Which {x} roles pay the most?",
        "How do I move into higher paying {x} roles?",
        "What {x} jobs have the best upside?",
      ],
      [
        "finance",
        "Dubai",
        "Saudi Arabia",
        "private banking",
        "asset management",
        "corporate finance",
        "compliance",
        "fintech",
        "real estate investment",
      ]
    ),
    ...expandTheme(
      "career_path",
      [
        "What is the career path for {x}?",
        "Where can {x} lead long term?",
        "What exits does {x} give me?",
        "How senior can I get from {x}?",
      ],
      [
        "investment analyst",
        "private banking relationship manager",
        "FP&A analyst",
        "KYC analyst",
        "transaction services associate",
        "real estate asset manager",
        "corporate finance manager",
        "compliance officer",
        "venture capital analyst",
      ]
    ),
    ...expandTheme(
      "career_guidance",
      [
        "I need career guidance on {x}",
        "What would you recommend for {x}?",
        "Help me decide what to do about {x}",
        "I'm confused about {x}",
      ],
      [
        "choosing finance or marketing",
        "leaving my current job",
        "moving to Dubai",
        "taking CFA",
        "switching to wealth management",
        "going into private equity",
        "accepting a lower salary",
        "waiting for promotion",
        "choosing between two offers",
      ]
    ),
    ...expandTheme(
      "recruiters_hr_outreach",
      [
        "How do I reach {x}?",
        "Can you write a message to {x}?",
        "Should I email {x} before applying?",
        "What should I say to {x}?",
      ],
      [
        "HR",
        "a recruiter in Dubai",
        "a hiring manager",
        "a private equity recruiter",
        "a wealth management recruiter",
        "Talent Acquisition at Qiddiya",
        "a family office recruiter",
        "a Saudi recruiter",
        "a LinkedIn contact",
      ]
    ),
    ...expandTheme(
      "future_jobs",
      [
        "What jobs will be most in-demand in {x}?",
        "What are the future high paying roles in {x}?",
        "Which {x} jobs should I target for the future?",
        "What career is safest long term in {x}?",
      ],
      [
        "finance",
        "AI and finance",
        "Dubai",
        "Saudi Arabia",
        "asset management",
        "compliance",
        "fintech",
        "private markets",
        "data analytics",
      ]
    ),
    ...expandTheme(
      "dubai_questions",
      [
        "What should I know about {x} in Dubai?",
        "Is Dubai good for {x}?",
        "How do I find {x} in Dubai?",
        "What is the market like for {x} in Dubai?",
      ],
      [
        "private banking",
        "family offices",
        "wealth management",
        "private equity",
        "compliance",
        "finance directors",
        "investment analysts",
        "recruiters",
        "visa sponsorship",
      ]
    ),
    ...expandTheme(
      "saudi_arabia_questions",
      [
        "What should I know about {x} in Saudi Arabia?",
        "Is Saudi Arabia good for {x}?",
        "How do I find {x} in Riyadh?",
        "What is the market like for {x} in KSA?",
      ],
      [
        "finance jobs",
        "giga project roles",
        "private banking",
        "corporate finance",
        "investment roles",
        "compliance",
        "asset management",
        "Arabic requirements",
        "expat finance candidates",
      ]
    ),
    ...expandTheme(
      "technical_email_issues",
      [
        "What should I do if {x}?",
        "Can you help me when {x}?",
        "Why is {x} happening?",
        "How do I fix {x}?",
      ],
      [
        "my email confirmation code is not arriving",
        "the employer form will not load",
        "the apply button is broken",
        "my uploaded CV is not showing",
        "the job link opens a blank page",
        "my payment failed",
        "I cannot log in",
        "the site says refused to connect",
        "I used the wrong email",
      ]
    ),
    ...expandTheme(
      "experience_based_questions",
      [
        "What roles can I target with {x}?",
        "Does {x} count as relevant experience?",
        "How should I position {x}?",
        "Is {x} enough experience for GCC roles?",
      ],
      [
        "15 years in valuation and Big 4",
        "9 years in compliance and governance",
        "unpaid VC and startup work",
        "2 years in business banking operations",
        "retail banking relationship management",
        "audit and financial reporting",
        "financial modelling and budgeting",
        "KYC and account opening",
        "project management in finance",
      ]
    ),
  ];
  return base
    .map(([input, expected], index) => ({
      id: index + 1,
      input,
      expected,
      theme: "baseline",
    }))
    .concat(
      themes.map(([input, expected, theme], index) => ({
        id: base.length + index + 1,
        input,
        expected,
        theme,
      }))
    );
}

async function main() {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  const pageErrors = [];
  page.on("pageerror", (error) => pageErrors.push(error.message));
  await page.setContent(`<!doctype html><html><body>
    <section class="sffc-crm-apply-chat" data-sffc-apply-chat data-role-title="Director, Finance & Asset Performance Film Studios" data-role-company="Qiddiya Investment Company" data-role-location="Riyadh, Saudi Arabia">
      <button type="button" data-sffc-apply-chat-open></button>
      <section data-sffc-apply-chat-desk hidden>
        <div data-sffc-apply-chat-desk-body>
          <div data-sffc-apply-chat-conversation-stage>
            <div data-sffc-apply-chat-messages></div>
          </div>
          <div data-sffc-apply-chat-results-stage>
            <div data-sffc-apply-chat-results-empty></div>
            <div data-sffc-apply-chat-results-body></div>
          </div>
          <form data-sffc-apply-chat-composer>
            <input data-sffc-apply-chat-input />
            <input data-sffc-apply-chat-file type="file" />
            <button data-sffc-apply-chat-upload type="button"></button>
          </form>
        </div>
      </section>
    </section>
  </body></html>`);
  await page.addInitScript(() => {
    window.sffcCrmApplyChatArticle = {
      enableTestHooks: true,
      isLoggedIn: false,
      ajaxUrl: "/wp-admin/admin-ajax.php",
    };
  });
  await page.addScriptTag({ path: chatScript });
  await page.evaluate(() => {
    window.sffcCrmApplyChatArticle = {
      enableTestHooks: true,
      isLoggedIn: false,
      ajaxUrl: "/wp-admin/admin-ajax.php",
    };
    document.dispatchEvent(new Event("DOMContentLoaded", { bubbles: true }));
  });

  const evaluateCases = (cases) => {
    const root = document.querySelector("[data-sffc-apply-chat]");
    const test = root && root.__sffcApplyChatTest;
    if (!test) {
      throw new Error("Apply chat test hook did not initialise.");
    }
      const weakAnswerPattern =
        /^(?:I can answer that|Tell me the detail|Just tell me|Say it in your own words|I do not have enough context)\b/i;
    const strategyQualityPattern =
      /\b(?:january|april|september|november|august|ramadan|summer|hiring window|recruiter|direct apply|applications? a week|interviews?|cv|linkedin|shortlist|market|seniority|visa|sponsorship|giga projects?|difc|entry points?|analyst programmes?|internships?|graduate|bridge roles?|proof)\b/i;
    return cases.map((item) => {
      const expectation = test.classifyResponseExpectation(item.input, {});
      const intent = test.detectIntent(item.input);
      const discovery = test.classifyRoleDiscoveryInput(item.input);
      const reply = test.previewKnowledgeReply(item.input);
      const answer = String((reply && reply.answer) || "").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
      const answerExpected = item.expected === "response" || item.expected === "search";
      const classifiedResponse =
        expectation.response_expected ||
        /^(?:QUESTION|REQUEST|QUERY)$/.test(expectation.classification || "") ||
        discovery.isConversationInput ||
        item.expected === "search";
      const hasAnswer = answer.length >= 35 && !weakAnswerPattern.test(answer);
      let passed = true;
      let reason = "";
      if (item.expected === "statement") {
        passed =
          !expectation.response_expected &&
          !/^(?:QUESTION|REQUEST|QUERY)$/.test(expectation.classification || "");
        if (!passed) reason = "statement_overclassified_as_response";
      } else if (item.expected === "search") {
        passed =
          classifiedResponse &&
          (discovery.action === "search" || expectation.classification === "QUERY");
        if (!passed) reason = "search_not_routed_as_actionable";
      } else if (answerExpected) {
        passed = classifiedResponse && hasAnswer;
        if (!classifiedResponse) reason = "response_expected_not_detected";
        else if (!hasAnswer) reason = "no_strong_answer_generated";
        else if (
          item.theme === "job_search_strategy_quality" &&
          !strategyQualityPattern.test(answer)
        ) {
          passed = false;
          reason = "job_search_strategy_answer_too_generic";
        }
      }
      return {
        id: item.id,
        input: item.input,
        expected: item.expected,
        theme: item.theme || "baseline",
        passed,
        reason,
        intent,
        classification: expectation.classification,
        responseExpected: expectation.response_expected,
        scores: {
          question: expectation.question_score,
          request: expectation.request_score,
          query: expectation.query_score,
          statement: expectation.statement_score,
          answer: expectation.answer_score,
        },
        discoveryAction: discovery.action,
        isConversationInput: discovery.isConversationInput,
        answerPreview: answer.slice(0, 220),
      };
    });
  };
  const selectedThemeArg = process.argv.find((arg) => arg.startsWith("--theme="));
  const selectedTheme = selectedThemeArg ? selectedThemeArg.slice("--theme=".length) : "";
  const cases = selectedTheme
    ? makeCases().filter((item) => item.theme === selectedTheme)
    : makeCases();
  const batchSize = Number(process.env.SFFC_CHAT_STRESS_BATCH_SIZE || 25);
  const result = [];
  for (let index = 0; index < cases.length; index += batchSize) {
    const batch = cases.slice(index, index + batchSize);
    console.error(
      `Evaluating chat stress cases ${index + 1}-${index + batch.length} of ${cases.length}`
    );
    const batchResult = await page.evaluate(evaluateCases, batch);
    result.push(...batchResult);
  }

  await browser.close();
  const failures = result.filter((item) => !item.passed);
  const byReason = failures.reduce((acc, item) => {
    acc[item.reason] = (acc[item.reason] || 0) + 1;
    return acc;
  }, {});
  const byTheme = result.reduce((acc, item) => {
    const theme = item.theme || "baseline";
    if (!acc[theme]) {
      acc[theme] = { total: 0, passed: 0, failed: 0 };
    }
    acc[theme].total += 1;
    if (item.passed) acc[theme].passed += 1;
    else acc[theme].failed += 1;
    return acc;
  }, {});
  const fullOutput = process.argv.includes("--full");
  const compactFailures = failures.slice(0, fullOutput ? 80 : 40).map((item) => ({
    id: item.id,
    input: item.input,
    expected: item.expected,
    reason: item.reason,
    intent: item.intent,
    classification: item.classification,
    responseExpected: item.responseExpected,
    scores: item.scores,
    discoveryAction: item.discoveryAction,
    isConversationInput: item.isConversationInput,
    answerPreview: item.answerPreview,
  }));
  const output = {
    total: result.length,
    passed: result.length - failures.length,
    failed: failures.length,
    byReason,
    byTheme,
    page_errors: pageErrors,
    failures: compactFailures,
  };
  console.log(JSON.stringify(output, null, 2));
  if (pageErrors.length || failures.length) {
    process.exitCode = 1;
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
