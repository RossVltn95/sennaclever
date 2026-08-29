/**
 * WSJ CV Renderer
 * Real-time CV rendering engine with Wall Street Journal aesthetic
 * Handles text parsing, formatting, and live preview updates
 */

class WSJCVRenderer {
  constructor(options = {}) {
    this.options = {
      container: options.container || ".wsj-cv-container",
      editable: options.editable !== false,
      autoSave: options.autoSave !== false,
      animations: options.animations !== false,
      ...options,
    };

    this.cvData = {
      name: "",
      title: "",
      contact: {
        email: "",
        phone: "",
        location: "",
        linkedin: "",
      },
      summary: "",
      experience: [],
      education: [],
      skills: [],
    };

    this.container = null;
    this.isRendering = false;
    this.powerVerbs = this.initPowerVerbs();
    this.init();
  }

  /**
   * Initialize the renderer
   */
  init() {
    this.container = document.querySelector(this.options.container);
    if (!this.container) {
      console.error("WSJ CV Renderer: Container not found");
      return;
    }

    // Load WSJ CSS if not already loaded
    this.loadStyles();

    // Set up event listeners
    this.setupEventListeners();

    // Initial render with empty state
    this.render();
  }

  /**
   * Load WSJ styles
   */
  loadStyles() {
    if (!document.querySelector('link[href*="wsj-cv-display.css"]')) {
      const link = document.createElement("link");
      link.rel = "stylesheet";
      link.href =
        "/wp-content/plugins/senna-finance-career/assets/css/wsj-cv-display.css";
      document.head.appendChild(link);
    }
  }

  /**
   * Initialize power verbs for transformation
   */
  /**
   * Extensive library of power verbs categorized for CV bullet rewriting
   * Covers leadership, strategy, finance, operations, compliance, growth, innovation, etc.
   */
  initPowerVerbs() {
    return {
      leadership: [
        "Led",
        "Directed",
        "Managed",
        "Supervised",
        "Oversaw",
        "Coordinated",
        "Mentored",
        "Headed",
        "Chaired",
        "Mobilized",
        "Delegated",
        "Motivated",
        "Governed",
        "Guided",
        "Commanded",
        "Steered",
        "Administered",
      ],
      strategy: [
        "Strategized",
        "Planned",
        "Formulated",
        "Architected",
        "Outlined",
        "Structured",
        "Devised",
        "Mapped",
        "Positioned",
        "Spearheaded",
        "Pioneered",
        "Conceived",
        "Orchestrated",
        "Instituted",
      ],
      achievement: [
        "Delivered",
        "Achieved",
        "Exceeded",
        "Surpassed",
        "Generated",
        "Attained",
        "Accomplished",
        "Realized",
        "Produced",
        "Fulfilled",
        "Secured",
      ],
      analysis: [
        "Analyzed",
        "Evaluated",
        "Assessed",
        "Audited",
        "Investigated",
        "Reviewed",
        "Interpreted",
        "Scrutinized",
        "Monitored",
        "Benchmarked",
        "Examined",
        "Explored",
        "Researched",
        "Quantified",
        "Calculated",
      ],
      execution: [
        "Executed",
        "Implemented",
        "Launched",
        "Deployed",
        "Completed",
        "Carried out",
        "Administered",
        "Delivered",
        "Rolled out",
        "Activated",
        "Enforced",
        "Operated",
        "Conducted",
        "Ran",
        "Applied",
      ],
      optimization: [
        "Optimized",
        "Streamlined",
        "Enhanced",
        "Refined",
        "Upgraded",
        "Revamped",
        "Improved",
        "Reengineered",
        "Reinforced",
        "Modernized",
        "Strengthened",
        "Fine-tuned",
        "Simplified",
        "Recalibrated",
      ],
      creation: [
        "Developed",
        "Built",
        "Designed",
        "Engineered",
        "Established",
        "Crafted",
        "Created",
        "Produced",
        "Deployed",
        "Instituted",
        "Set up",
        "Formed",
        "Initiated",
        "Composed",
      ],
      communication: [
        "Presented",
        "Negotiated",
        "Liaised",
        "Communicated",
        "Advised",
        "Consulted",
        "Briefed",
        "Facilitated",
        "Mediated",
        "Moderated",
        "Corresponded",
        "Influenced",
        "Articulated",
        "Delivered briefings to",
        "Engaged with",
        "Collaborated with",
      ],
      innovation: [
        "Innovated",
        "Piloted",
        "Modernized",
        "Redefined",
        "Reimagined",
        "Conceptualized",
        "Revolutionized",
        "Transformed",
        "Devised",
        "Invented",
        "Initiated",
        "Engineered",
        "Introduced",
      ],
      financial: [
        "Valuated",
        "Modeled",
        "Forecasted",
        "Budgeted",
        "Audited",
        "Projected",
        "Capitalized",
        "Appraised",
        "Financed",
        "Estimated",
        "Calculated",
        "Costed",
        "Priced",
        "Underwrote",
        "Structured",
      ],
      growth: [
        "Scaled",
        "Expanded",
        "Accelerated",
        "Boosted",
        "Increased",
        "Multiplied",
        "Amplified",
        "Grew",
        "Strengthened",
        "Maximized",
        "Elevated",
        "Broadened",
        "Augmented",
        "Doubled",
      ],
      risk_compliance: [
        "Mitigated",
        "Monitored",
        "Controlled",
        "Ensured",
        "Complied",
        "Audited",
        "Regulated",
        "Enforced",
        "Safeguarded",
        "Validated",
        "Reviewed",
        "Inspected",
        "Secured",
        "Tested",
        "Vetted",
      ],
      dealmaking: [
        "Negotiated",
        "Originated",
        "Executed",
        "Closed",
        "Structured",
        "Underwrote",
        "Syndicated",
        "Arranged",
        "Brokered",
        "Facilitated",
        "Secured",
        "Finalized",
        "Managed",
        "Advised",
      ],
      stakeholder: [
        "Collaborated",
        "Partnered",
        "Aligned",
        "Influenced",
        "Advised",
        "Coordinated",
        "Facilitated",
        "Supported",
        "Engaged",
        "Involved",
        "Consulted",
        "Worked closely with",
        "Integrated",
      ],
      technology: [
        "Programmed",
        "Coded",
        "Automated",
        "Systematized",
        "Configured",
        "Integrated",
        "Deployed",
        "Architected",
        "Optimized",
        "Debugged",
        "Refactored",
        "Built",
        "Implemented",
        "Developed",
      ],
      legal_regulatory: [
        "Drafted",
        "Reviewed",
        "Interpreted",
        "Advised on",
        "Ensured compliance with",
        "Liaised with regulators",
        "Submitted",
        "Filed",
        "Implemented policy for",
        "Supported legal processes",
        "Assessed regulatory frameworks",
      ],
    };
  }

  /**
   * Rewrite a bullet with strong power verbs based on detected intent/category
   */
  rewriteBulletWithPowerVerb(bullet) {
    const verbs = this.initPowerVerbs();
    const lower = bullet.toLowerCase().trim();

    // Check if the bullet already starts with a power verb
    for (const category in verbs) {
      if (verbs[category].some((v) => lower.startsWith(v.toLowerCase()))) {
        return bullet;
      }
    }

    // Detect category
    let category = "achievement";
    if (lower.match(/\bteam|manage|lead|mentor|supervis/))
      category = "leadership";
    else if (lower.match(/\bstrateg|roadmap|initiative|program|approach/))
      category = "strategy";
    else if (lower.match(/\banaly|evaluat|audit|research|investigat|assess/))
      category = "analysis";
    else if (lower.match(/\bexecut|implement|deliver|run|deploy/))
      category = "execution";
    else if (lower.match(/\boptim|streamlin|enhanc|improv|upgrad|reengin/))
      category = "optimization";
    else if (lower.match(/\bcreat|develop|design|build|establish|engineer/))
      category = "creation";
    else if (lower.match(/\bcommunicat|liais|present|advise|consult|negotiate/))
      category = "communication";
    else if (lower.match(/\binnov|moderni|pilot|reimagin|concept/))
      category = "innovation";
    else if (lower.match(/\bvaluat|invest|budget|model|forecast|audit|financ/))
      category = "financial";
    else if (lower.match(/\bgrow|expand|scale|boost|accelerate|increase/))
      category = "growth";
    else if (lower.match(/\brisk|complian|monitor|control|mitigat|regulat/))
      category = "risk_compliance";
    else if (lower.match(/\bdeal|negotiat|close|originate|underwrit|syndicat/))
      category = "dealmaking";
    else if (
      lower.match(/\bstakeholder|partner|collaborat|align|engag|influenc/)
    )
      category = "stakeholder";
    else if (lower.match(/\bcode|automate|system|tech|script|software|cursor/))
      category = "technology";
    else if (lower.match(/\blegal|regulat|draft|policy|compliance framework/))
      category = "legal_regulatory";

    // Pick a verb randomly
    const categoryVerbs = verbs[category];
    const chosenVerb =
      categoryVerbs[Math.floor(Math.random() * categoryVerbs.length)];
    const capitalizedVerb =
      chosenVerb.charAt(0).toUpperCase() + chosenVerb.slice(1);

    // Remove bullets or leading symbols
    const cleaned = bullet.replace(/^[-–•\s]+/, "");

    return `${capitalizedVerb} ${cleaned}`;
  }

  /**
   * Parse text input into structured CV data
   */
  parseTextInput(text) {
    const lines = text
      .split("\n")
      .map((l) => l.trim())
      .filter((l) => l);
    const parsed = { ...this.cvData };

    // Smart parsing logic
    let currentSection = null;
    let currentExperience = null;
    let currentEducation = null;

    lines.forEach((line, index) => {
      // Detect name (usually first non-empty line)
      if (
        index === 0 &&
        !line.match(/^(experience|education|skills|summary)/i)
      ) {
        parsed.name = this.cleanName(line);
        return;
      }

      // Detect contact info
      if (line.includes("@")) {
        parsed.contact.email = line.match(/[\w.-]+@[\w.-]+\.\w+/)?.[0] || "";
      }
      if (line.match(/\+?\d{10,}|[\d\s()-]+\d{4}/)) {
        parsed.contact.phone = line.match(/[\d\s()+-]+/)?.[0] || "";
      }
      if (line.includes("linkedin.com")) {
        parsed.contact.linkedin = line;
      }

      // Detect sections
      if (line.match(/^(experience|work|employment)/i)) {
        currentSection = "experience";
        return;
      }
      if (line.match(/^(education|academic|qualification)/i)) {
        currentSection = "education";
        return;
      }
      if (line.match(/^(skills|technical|competenc)/i)) {
        currentSection = "skills";
        return;
      }
      if (line.match(/^(summary|profile|objective|about)/i)) {
        currentSection = "summary";
        return;
      }

      // Parse based on current section
      if (currentSection === "experience") {
        this.parseExperienceLine(line, parsed, currentExperience);
      } else if (currentSection === "education") {
        this.parseEducationLine(line, parsed, currentEducation);
      } else if (currentSection === "skills") {
        this.parseSkillsLine(line, parsed);
      } else if (currentSection === "summary") {
        parsed.summary += (parsed.summary ? " " : "") + line;
      }
    });

    return parsed;
  }

  /**
   * Parse experience line
   */
  parseExperienceLine(line, parsed, currentExp) {
    // Check if it's a company/role line
    if (
      !line.startsWith("•") &&
      !line.startsWith("-") &&
      !line.startsWith("*")
    ) {
      // Likely a company or role
      if (
        line.match(/\d{4}/) ||
        line.match(/(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/i)
      ) {
        // Has dates, probably a complete job line
        const exp = {
          company: line.split(/[-–]/)[0]?.trim() || "",
          role: "",
          dates: this.extractDates(line),
          location: "",
          bullets: [],
        };
        parsed.experience.push(exp);
      } else {
        // Could be company name or role
        if (
          parsed.experience.length === 0 ||
          parsed.experience[parsed.experience.length - 1].bullets.length > 0
        ) {
          // Start new experience
          parsed.experience.push({
            company: line,
            role: "",
            dates: "",
            location: "",
            bullets: [],
          });
        } else {
          // Add as role to current experience
          const current = parsed.experience[parsed.experience.length - 1];
          if (!current.role) {
            current.role = line;
          }
        }
      }
    } else {
      // It's a bullet point
      if (parsed.experience.length > 0) {
        const bullet = line.replace(/^[•\-\*]\s*/, "");
        const enhanced = this.enhanceBullet(bullet);
        parsed.experience[parsed.experience.length - 1].bullets.push(enhanced);
      }
    }
  }

  /**
   * Parse education line
   */
  parseEducationLine(line, parsed, currentEdu) {
    if (!parsed.education.find((e) => e.institution === line)) {
      // Check if it's an institution
      if (line.match(/(university|college|school|institute)/i)) {
        parsed.education.push({
          institution: line,
          degree: "",
          dates: "",
          details: "",
        });
      } else if (parsed.education.length > 0) {
        // Add to current education
        const current = parsed.education[parsed.education.length - 1];
        if (
          !current.degree &&
          line.match(/(bsc|ba|msc|ma|mba|phd|bachelor|master|diploma)/i)
        ) {
          current.degree = line;
        } else if (!current.dates && line.match(/\d{4}/)) {
          current.dates = this.extractDates(line);
        } else if (!current.details) {
          current.details = line;
        }
      }
    }
  }

  /**
   * Parse skills line
   */
  parseSkillsLine(line, parsed) {
    // Split by common delimiters
    const skills = line
      .split(/[,;|]/)
      .map((s) => s.trim())
      .filter((s) => s);
    parsed.skills = [...parsed.skills, ...skills];
  }

  /**
   * Enhance bullet point with power verbs and metrics
   */
  enhanceBullet(bullet) {
    let enhanced = bullet;

    // Ensure it starts with a power verb
    const firstWord = bullet.split(" ")[0];
    if (!this.isPowerVerb(firstWord)) {
      const category = this.detectBulletCategory(bullet);
      const verbs = this.powerVerbs[category] || this.powerVerbs.achievement;
      enhanced =
        verbs[Math.floor(Math.random() * verbs.length)] +
        " " +
        bullet.charAt(0).toLowerCase() +
        bullet.slice(1);
    }

    // Add metrics if missing
    if (!enhanced.match(/\d+/)) {
      enhanced = this.injectMetrics(enhanced);
    }

    return enhanced;
  }

  /**
   * Check if word is a power verb
   */
  isPowerVerb(word) {
    return Object.values(this.powerVerbs)
      .flat()
      .some((verb) => verb.toLowerCase() === word.toLowerCase());
  }

  /**
   * Detect bullet category for appropriate verb selection
   */
  detectBulletCategory(bullet) {
    const lower = bullet.toLowerCase();
    if (lower.includes("led") || lower.includes("managed")) return "leadership";
    if (lower.includes("analyz") || lower.includes("research"))
      return "analysis";
    if (lower.includes("develop") || lower.includes("creat")) return "creation";
    if (lower.includes("improv") || lower.includes("optim"))
      return "optimization";
    if (lower.includes("implement") || lower.includes("execut"))
      return "execution";
    return "achievement";
  }

  /**
   * Inject metrics into bullet
   */
  injectMetrics(bullet) {
    const lower = bullet.toLowerCase();

    // Locale configuration — UK by default
    const locale = window.sffc_locale || "UK";

    // 💰 Currency & number ranges for each region
    const metricConfig = {
      UK: {
        moneySymbol: "£",
        moneyRange: { min: 0.5, max: 50 }, // millions
        percentRange: { min: 5, max: 70 },
        teamRange: { min: 3, max: 50 },
        clientRange: { min: 5, max: 100 },
      },
      EU: {
        moneySymbol: "€",
        moneyRange: { min: 0.5, max: 50 },
        percentRange: { min: 5, max: 70 },
        teamRange: { min: 3, max: 50 },
        clientRange: { min: 5, max: 100 },
      },
      US: {
        moneySymbol: "$",
        moneyRange: { min: 0.5, max: 100 },
        percentRange: { min: 5, max: 80 },
        teamRange: { min: 3, max: 100 },
        clientRange: { min: 5, max: 200 },
      },
    };

    const cfg = metricConfig[locale] || metricConfig["UK"];

    const randomInRange = (min, max) => {
      const val = Math.random() * (max - min) + min;
      return Math.round(val * 10) / 10; // 1 decimal place if needed
    };

    // ✍️ Apply metrics based on keyword patterns
    if (lower.includes("team")) {
      const num = Math.floor(
        randomInRange(cfg.teamRange.min, cfg.teamRange.max)
      );
      return bullet.replace(/team/i, `${num}+ member team`);
    }

    if (lower.includes("project")) {
      const amount = randomInRange(cfg.moneyRange.min, cfg.moneyRange.max);
      return bullet.replace(
        /project/i,
        `${cfg.moneySymbol}${amount}M+ project`
      );
    }

    if (lower.includes("portfolio")) {
      const amount = randomInRange(
        cfg.moneyRange.min * 2,
        cfg.moneyRange.max * 2
      );
      return bullet.replace(
        /portfolio/i,
        `${cfg.moneySymbol}${amount}M+ portfolio`
      );
    }

    if (lower.includes("revenue") || lower.includes("sales")) {
      const amount = randomInRange(cfg.moneyRange.min, cfg.moneyRange.max);
      return bullet.replace(
        /(revenue|sales)/i,
        `${cfg.moneySymbol}${amount}M+ $1`
      );
    }

    if (lower.includes("growth") || lower.includes("improv")) {
      const pct = Math.floor(
        randomInRange(cfg.percentRange.min, cfg.percentRange.max)
      );
      return bullet + `, achieving ${pct}% improvement`;
    }

    if (lower.includes("cost") || lower.includes("saving")) {
      const pct = Math.floor(
        randomInRange(cfg.percentRange.min, cfg.percentRange.max)
      );
      return bullet + `, reducing costs by ${pct}%`;
    }

    if (lower.includes("client") || lower.includes("customer")) {
      const num = Math.floor(
        randomInRange(cfg.clientRange.min, cfg.clientRange.max)
      );
      return bullet.replace(/(client|customer)/i, `${num}+ $1s`);
    }

    if (lower.includes("deal") || lower.includes("transaction")) {
      const amount = randomInRange(cfg.moneyRange.min, cfg.moneyRange.max);
      return bullet.replace(
        /(deal|transaction)/i,
        `${cfg.moneySymbol}${amount}M+ $1`
      );
    }

    if (lower.includes("investment")) {
      const amount = randomInRange(cfg.moneyRange.min, cfg.moneyRange.max);
      return bullet.replace(
        /investment/i,
        `${cfg.moneySymbol}${amount}M+ investment`
      );
    }

    if (lower.includes("budget")) {
      const amount = randomInRange(cfg.moneyRange.min, cfg.moneyRange.max);
      return bullet.replace(/budget/i, `${cfg.moneySymbol}${amount}M+ budget`);
    }

    if (
      lower.includes("initiative") ||
      lower.includes("programme") ||
      lower.includes("program")
    ) {
      const num = Math.floor(
        randomInRange(cfg.teamRange.min, cfg.teamRange.max)
      );
      return bullet.replace(
        /(initiative|programme|program)/i,
        `${num}+ person $1`
      );
    }

    if (lower.includes("process")) {
      const pct = Math.floor(
        randomInRange(cfg.percentRange.min, cfg.percentRange.max)
      );
      return bullet + `, improving efficiency by ${pct}%`;
    }

    if (lower.includes("strategy")) {
      const pct = Math.floor(
        randomInRange(cfg.percentRange.min, cfg.percentRange.max)
      );
      return bullet + `, driving ${pct}% business impact`;
    }

    // Fallback: return unchanged
    return bullet;
  }

  /**
   * Extract dates from text
   */
  extractDates(text) {
    const dateMatch = text.match(
      /\d{4}|\d{1,2}\/\d{4}|(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+\d{4}/gi
    );
    return dateMatch ? dateMatch.join(" - ") : "";
  }

  /**
   * Clean name formatting
   */
  cleanName(name) {
    return name
      .replace(/[^\w\s]/g, "")
      .trim()
      .split(" ")
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
      .join(" ");
  }

  /**
   * Main render method
   */
  render(cvData = null) {
    if (this.isRendering) return;
    this.isRendering = true;

    if (cvData) {
      this.cvData = cvData;
    }

    const html = this.generateHTML();

    if (this.container) {
      this.container.innerHTML = html;

      if (this.options.animations) {
        this.animateElements();
      }

      if (this.options.editable) {
        this.enableEditing();
      }
    }

    this.isRendering = false;
  }

  /**
   * Generate HTML from CV data
   */
  generateHTML() {
    const { name, title, contact, summary, experience, education, skills } =
      this.cvData;

    return `
            <div class="wsj-cv-wrapper">
                <div class="wsj-cv-paper">
                    ${this.generateHeader(name, title, contact)}
                    ${summary ? this.generateSummary(summary) : ""}
                    ${
                      experience.length > 0
                        ? this.generateExperience(experience)
                        : ""
                    }
                    ${
                      education.length > 0
                        ? this.generateEducation(education)
                        : ""
                    }
                    ${skills.length > 0 ? this.generateSkills(skills) : ""}
                </div>
            </div>
        `;
  }

  /**
   * Generate header section
   */
  generateHeader(name, title, contact) {
    const contactItems = [];
    if (contact.email)
      contactItems.push(
        `<span class="wsj-cv-contact-item">${contact.email}</span>`
      );
    if (contact.phone)
      contactItems.push(
        `<span class="wsj-cv-contact-item">${contact.phone}</span>`
      );
    if (contact.location)
      contactItems.push(
        `<span class="wsj-cv-contact-item">${contact.location}</span>`
      );

    const fallbackName =
      (window.sffcCrmCvMatchStudio &&
        window.sffcCrmCvMatchStudio.currentUser &&
        window.sffcCrmCvMatchStudio.currentUser.firstName) ||
      (window.sffcCrmCvMatchStudio && window.sffcCrmCvMatchStudio.firstName) ||
      "Candidate";

    return `
            <header class="wsj-cv-header">
                ${
                  name
                    ? `<h1 class="wsj-cv-name">${name}</h1>`
                    : `<h1 class="wsj-cv-name">${fallbackName}</h1>`
                }
                ${title ? `<div class="wsj-cv-title">${title}</div>` : ""}
                ${
                  contactItems.length > 0
                    ? `<div class="wsj-cv-contact">${contactItems.join(
                        ""
                      )}</div>`
                    : ""
                }
            </header>
        `;
  }

  /**
   * Generate summary section
   */
  generateSummary(summary) {
    return `
            <section class="wsj-cv-section">
                <h2 class="wsj-cv-section-title">Professional Summary</h2>
                <div class="wsj-cv-summary">${summary}</div>
            </section>
        `;
  }

  /**
   * Generate experience section
   */
  generateExperience(experiences) {
    const items = experiences
      .map(
        (exp) => `
            <div class="wsj-cv-experience-item">
                <div class="wsj-cv-job-header">
                    <div class="wsj-cv-company">
                        ${exp.company}
                        ${
                          exp.location
                            ? `<span class="wsj-cv-location">${exp.location}</span>`
                            : ""
                        }
                    </div>
                    ${
                      exp.dates
                        ? `<div class="wsj-cv-dates">${exp.dates}</div>`
                        : ""
                    }
                </div>
                ${exp.role ? `<div class="wsj-cv-role">${exp.role}</div>` : ""}
                ${
                  exp.bullets.length > 0
                    ? `
                    <ul class="wsj-cv-bullets">
                        ${exp.bullets
                          .map(
                            (b) =>
                              `<li class="wsj-cv-bullet">${this.highlightMetrics(
                                b
                              )}</li>`
                          )
                          .join("")}
                    </ul>
                `
                    : ""
                }
            </div>
        `
      )
      .join("");

    return `
            <section class="wsj-cv-section">
                <h2 class="wsj-cv-section-title">Experience</h2>
                ${items}
            </section>
        `;
  }

  /**
   * Generate education section
   */
  generateEducation(educations) {
    const items = educations
      .map(
        (edu) => `
            <div class="wsj-cv-education-item">
                <div class="wsj-cv-institution">${edu.institution}</div>
                ${
                  edu.degree
                    ? `<div class="wsj-cv-degree">${edu.degree}</div>`
                    : ""
                }
                ${
                  edu.dates || edu.details
                    ? `
                    <div class="wsj-cv-edu-details">
                        ${edu.dates} ${edu.details ? `• ${edu.details}` : ""}
                    </div>
                `
                    : ""
                }
            </div>
        `
      )
      .join("");

    return `
            <section class="wsj-cv-section">
                <h2 class="wsj-cv-section-title">Education</h2>
                ${items}
            </section>
        `;
  }

  /**
   * Generate skills section
   */
  generateSkills(skills) {
    const skillItems = skills
      .map((skill) => `<span class="wsj-cv-skill">${skill}</span>`)
      .join("");

    return `
            <section class="wsj-cv-section">
                <h2 class="wsj-cv-section-title">Skills & Expertise</h2>
                <div class="wsj-cv-skills-grid">${skillItems}</div>
            </section>
        `;
  }

  /**
   * Highlight metrics in text
   */
  highlightMetrics(text) {
    // Highlight percentages, dollar amounts, and numbers
    return text.replace(
      /(\$[\d,]+[MBK]?|\d+%|\d+\+)/g,
      '<span class="wsj-cv-metric">$1</span>'
    );
  }

  /**
   * Enable inline editing
   */
  enableEditing() {
    const editables = this.container.querySelectorAll(
      ".wsj-cv-name, .wsj-cv-title, .wsj-cv-summary, .wsj-cv-bullet"
    );

    editables.forEach((el) => {
      el.classList.add("wsj-cv-editable");
      el.contentEditable = true;

      el.addEventListener("blur", () => {
        this.handleEdit(el);
      });

      el.addEventListener("keydown", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          el.blur();
        }
      });
    });
  }

  /**
   * Handle edit events
   */
  handleEdit(element) {
    const newValue = element.textContent.trim();

    // Update the data model
    if (element.classList.contains("wsj-cv-name")) {
      this.cvData.name = newValue;
    } else if (element.classList.contains("wsj-cv-title")) {
      this.cvData.title = newValue;
    } else if (element.classList.contains("wsj-cv-summary")) {
      this.cvData.summary = newValue;
    }

    // Trigger save if enabled
    if (this.options.autoSave) {
      this.save();
    }

    // Visual feedback
    element.classList.add("wsj-cv-success");
    setTimeout(() => element.classList.remove("wsj-cv-success"), 600);
  }

  /**
   * Animate elements on render
   */
  animateElements() {
    const sections = this.container.querySelectorAll(".wsj-cv-section");
    sections.forEach((section, index) => {
      section.style.animationDelay = `${0.1 * (index + 1)}s`;
    });
  }

  /**
   * Update CV from text input
   */
  updateFromText(text) {
    const parsed = this.parseTextInput(text);
    this.render(parsed);
  }

  /**
   * Get current CV data
   */
  getData() {
    return this.cvData;
  }

  /**
   * Set CV data
   */
  setData(data) {
    this.cvData = { ...this.cvData, ...data };
    this.render();
  }

  /**
   * Save CV data (can be overridden)
   */
  save() {
    const event = new CustomEvent("wsj-cv-save", {
      detail: this.cvData,
    });
    document.dispatchEvent(event);
  }

  /**
   * Export as text
   */
  exportText() {
    const { name, contact, summary, experience, education, skills } =
      this.cvData;
    let text = `${name}\n`;

    if (contact.email || contact.phone) {
      text += `${contact.email} | ${contact.phone}\n`;
    }

    if (summary) {
      text += `\nSUMMARY\n${summary}\n`;
    }

    if (experience.length > 0) {
      text += "\nEXPERIENCE\n";
      experience.forEach((exp) => {
        text += `${exp.company} - ${exp.role}\n${exp.dates}\n`;
        exp.bullets.forEach((b) => (text += `• ${b}\n`));
        text += "\n";
      });
    }

    if (education.length > 0) {
      text += "\nEDUCATION\n";
      education.forEach((edu) => {
        text += `${edu.institution}\n${edu.degree} ${edu.dates}\n\n`;
      });
    }

    if (skills.length > 0) {
      text += `\nSKILLS\n${skills.join(", ")}\n`;
    }

    return text;
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Listen for external update events
    document.addEventListener("wsj-cv-update", (e) => {
      if (e.detail) {
        this.updateFromText(e.detail);
      }
    });

    // Listen for data updates
    document.addEventListener("wsj-cv-set-data", (e) => {
      if (e.detail) {
        this.setData(e.detail);
      }
    });
  }
}

// Export for global use
window.WSJCVRenderer = WSJCVRenderer;

// Auto-initialize if container exists
document.addEventListener("DOMContentLoaded", () => {
  if (document.querySelector(".wsj-cv-container")) {
    window.wsjCV = new WSJCVRenderer();
  }
});
