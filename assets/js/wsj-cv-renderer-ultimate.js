/**
 * WSJ CV Renderer - Ultimate Version
 * Handles all CV formats with robust parsing
 */

class WSJCVRendererUltimate {
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

  init() {
    this.container = document.querySelector(this.options.container);
    if (!this.container) {
      console.error("WSJ CV Renderer: Container not found");
      return;
    }

    this.loadStyles();
    this.setupEventListeners();
    this.render();
  }

  loadStyles() {
    if (!document.querySelector('link[href*="wsj-cv-display.css"]')) {
      const link = document.createElement("link");
      link.rel = "stylesheet";
      link.href =
        "/wp-content/plugins/senna-finance-career/assets/css/wsj-cv-display.css";
      document.head.appendChild(link);
    }
  }

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
   * Enhanced parsing with better pattern recognition
   */
  parseTextInput(text) {
    const lines = text
      .split("\n")
      .map((l) => l.trim())
      .filter((l) => l);
    const parsed = {
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

    let currentSection = null;
    let currentExperience = null;
    let expectingRole = false;
    let expectingDates = false;

    lines.forEach((line, index) => {
      // Skip empty lines
      if (!line) return;

      // Detect name (first non-empty, non-section line)
      if (index === 0 && !this.isSectionHeader(line)) {
        parsed.name = this.cleanName(line);
        return;
      }

      // Detect contact info (usually in first few lines)
      if (index < 5) {
        this.parseContactInfo(line, parsed.contact);
      }

      // Detect section headers (including French/other languages)
      if (this.isSectionHeader(line)) {
        const section = this.detectSection(line);
        if (section) {
          currentSection = section;
          currentExperience = null;
          expectingRole = false;
          expectingDates = false;
          return;
        }
      }

      // Parse based on current section
      if (currentSection === "experience") {
        this.parseExperienceLineUltimate(line, parsed);
      } else if (currentSection === "education") {
        this.parseEducationLine(line, parsed);
      } else if (currentSection === "skills") {
        this.parseSkillsLine(line, parsed);
      } else if (currentSection === "summary") {
        parsed.summary += (parsed.summary ? " " : "") + line;
      }
    });

    // Enhance all bullets after parsing
    parsed.experience.forEach((exp) => {
      if (exp.bullets) {
        exp.bullets = exp.bullets.map((bullet) => this.enhanceBullet(bullet));
      }
    });

    return parsed;
  }

  /**
   * Check if line is a section header
   */
  isSectionHeader(line) {
    const patterns = [
      // English
      /^(experience|work|employment|professional experience|work experience)/i,
      /^(education|academic|qualification|training|formation)/i,
      /^(skills|technical|competenc|expertise|abilities)/i,
      /^(summary|profile|objective|about|personal statement)/i,
      // French
      /^(expérience|emploi|travail)/i,
      /^(formation|éducation|études)/i,
      /^(compétences|aptitudes)/i,
      // Spanish
      /^(experiencia|trabajo|empleo)/i,
      /^(educación|formación|estudios)/i,
      /^(habilidades|competencias)/i,
    ];

    return patterns.some((pattern) => pattern.test(line));
  }

  /**
   * Detect which section type
   */
  detectSection(line) {
    const lower = line.toLowerCase();

    if (
      lower.match(
        /(experience|expérience|experiencia|work|emploi|trabajo|employment|professional)/
      )
    ) {
      return "experience";
    }
    if (
      lower.match(
        /(education|éducation|educación|formation|formación|academic|qualification|training)/
      )
    ) {
      return "education";
    }
    if (
      lower.match(
        /(skills|compétences|habilidades|technical|competenc|expertise|aptitudes)/
      )
    ) {
      return "skills";
    }
    if (
      lower.match(/(summary|résumé|resumen|profile|objective|about|personal)/)
    ) {
      return "summary";
    }

    return null;
  }

  /**
   * Parse contact information
   */
  parseContactInfo(line, contact) {
    // Email
    const emailMatch = line.match(/[\w.-]+@[\w.-]+\.\w+/);
    if (emailMatch && !contact.email) {
      contact.email = emailMatch[0];
    }

    // Phone (various formats)
    const phoneMatch = line.match(/[\+]?[\d\s\(\)\-\.]+[\d]{4,}/);
    if (
      phoneMatch &&
      phoneMatch[0].replace(/\D/g, "").length >= 10 &&
      !contact.phone
    ) {
      contact.phone = phoneMatch[0].trim();
    }

    // Location (city, state/country pattern)
    if (
      line.match(/[A-Z][a-z]+,\s*[A-Z]{2}|[A-Z][a-z]+,\s*[A-Z][a-z]+/) &&
      !line.includes("@")
    ) {
      const locationMatch = line.match(/[A-Z][a-z]+[,\s]+[A-Z][a-zA-Z\s]+/);
      if (locationMatch && !contact.location) {
        contact.location = locationMatch[0].trim();
      }
    }

    // LinkedIn
    if (line.includes("linkedin.com") && !contact.linkedin) {
      contact.linkedin = line;
    }
  }

  /**
   * Ultimate experience parsing with better pattern recognition
   */
  parseExperienceLineUltimate(line, parsed) {
    const isBullet = line.match(/^[•\-\*▪·]\s*/) || line.match(/^-\s+/);

    if (!isBullet) {
      // Enhanced date detection
      const hasDate = this.containsDate(line);

      // Enhanced company detection
      const looksLikeCompany = this.looksLikeCompany(line);

      // Enhanced role detection
      const looksLikeRole = this.looksLikeRole(line);

      // Check if it's a company-role-date combo line
      if (line.includes("|") || line.includes("–") || line.includes("-")) {
        const parts = line.split(/[|–-]/).map((p) => p.trim());

        if (parts.length >= 2) {
          // Could be "Company | Location" or "Role | Dates" format
          const firstPart = parts[0];
          const lastPart = parts[parts.length - 1];

          if (this.looksLikeCompany(firstPart)) {
            parsed.experience.push({
              company: firstPart,
              role: "",
              dates: this.containsDate(lastPart) ? lastPart : "",
              location: this.looksLikeLocation(lastPart) ? lastPart : "",
              bullets: [],
            });
            return;
          } else if (this.looksLikeRole(firstPart)) {
            // Check if we have a current job without a role
            const currentJob = parsed.experience[parsed.experience.length - 1];
            if (currentJob && !currentJob.role) {
              currentJob.role = firstPart;
              if (this.containsDate(lastPart)) {
                currentJob.dates = lastPart;
              }
            } else {
              // Create new job with role
              parsed.experience.push({
                company: "",
                role: firstPart,
                dates: this.containsDate(lastPart) ? lastPart : "",
                location: "",
                bullets: [],
              });
            }
            return;
          }
        }
      }

      // Handle "Stage" or "Internship" patterns (French/International)
      if (line.match(/^(stage|internship|projet)/i)) {
        parsed.experience.push({
          company: line,
          role: "",
          dates: "",
          location: "",
          bullets: [],
        });
        return;
      }

      // Decision logic for standalone lines
      if (
        looksLikeCompany ||
        (!looksLikeRole && !hasDate && parsed.experience.length === 0)
      ) {
        // It's a company
        parsed.experience.push({
          company: line,
          role: "",
          dates: "",
          location: "",
          bullets: [],
        });
      } else if (looksLikeRole) {
        // It's a role
        const currentJob = parsed.experience[parsed.experience.length - 1];
        if (currentJob && !currentJob.role) {
          currentJob.role = line;
        } else if (!currentJob) {
          // No current job, create one with role
          parsed.experience.push({
            company: "",
            role: line,
            dates: "",
            location: "",
            bullets: [],
          });
        }
      } else if (hasDate) {
        // It's a date line
        const currentJob = parsed.experience[parsed.experience.length - 1];
        if (currentJob && !currentJob.dates) {
          currentJob.dates = line;
        }
      } else if (this.looksLikeLocation(line)) {
        // It's a location
        const currentJob = parsed.experience[parsed.experience.length - 1];
        if (currentJob && !currentJob.location) {
          currentJob.location = line;
        }
      } else {
        // Default: if we have a job without a role, it's probably the role
        const currentJob = parsed.experience[parsed.experience.length - 1];
        if (currentJob && !currentJob.role) {
          currentJob.role = line;
        } else if (!currentJob) {
          // Start new job entry
          parsed.experience.push({
            company: line,
            role: "",
            dates: "",
            location: "",
            bullets: [],
          });
        }
      }
    } else {
      // It's a bullet point
      if (parsed.experience.length > 0) {
        const bullet = line.replace(/^[•\-\*▪·]\s*/, "").replace(/^-\s+/, "");
        parsed.experience[parsed.experience.length - 1].bullets.push(bullet);
      }
    }
  }

  /**
   * Check if string contains a date
   */
  containsDate(text) {
    return !!(
      text.match(/\d{4}/) ||
      text.match(
        /(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)/i
      ) ||
      text.match(/(present|current|now|today|présent|actuel)/i)
    );
  }

  /**
   * Check if string looks like a company name
   */
  looksLikeCompany(text) {
    const companyPatterns = [
      /\b(inc|corp|corporation|llc|ltd|limited|company|group|partners|capital|bank|consulting|analytics|technologies|solutions|services|holdings|ventures|investments|advisors|associates|enterprises|industries|international|global)\b/i,
      /\b(family office|hedge fund|private equity|venture capital|investment bank)\b/i,
      /\b(goldman|morgan|jpmorgan|citi|barclays|ubs|deutsche|credit suisse|bnp|société|santander)\b/i,
      /\b(google|amazon|microsoft|apple|facebook|meta|netflix|tesla|uber|airbnb)\b/i,
      /\b(deloitte|pwc|kpmg|ey|mckinsey|bain|bcg|accenture)\b/i,
      /&/, // Companies often have & in name
      /\b[A-Z]{2,}\b/, // Acronyms like IBM, GE, etc.
      /^[A-Z][a-z]+\s+[A-Z][a-z]+$/, // Two capitalized words (common company pattern)
    ];

    return companyPatterns.some((pattern) => pattern.test(text));
  }

  /**
   * Check if string looks like a job role
   */
  looksLikeRole(text) {
    const rolePatterns = [
      /\b(analyst|associate|consultant|manager|director|president|vp|vice president|partner|principal|head|chief|lead|senior|junior|intern|trainee|coordinator|specialist|advisor|assistant|executive|officer|developer|engineer|designer|architect|administrator|supervisor|team lead)\b/i,
      /\b(finance|financial|investment|banking|trading|risk|portfolio|strategy|operations|marketing|sales|business|product|project|program|account|client|customer)\b.*\b(analyst|associate|manager|director|consultant|specialist|coordinator|advisor)\b/i,
      /\b(stage|internship|stagiaire|apprentice|trainee|placement)\b/i, // International internship terms
    ];

    return rolePatterns.some((pattern) => pattern.test(text));
  }

  /**
   * Check if string looks like a location
   */
  looksLikeLocation(text) {
    const locationPatterns = [
      /^[A-Z][a-z]+,\s*[A-Z]{2}$/, // City, ST
      /^[A-Z][a-z]+,\s*[A-Z][a-z]+$/, // City, Country
      /\b(london|paris|new york|tokyo|singapore|hong kong|zurich|frankfurt|amsterdam|dublin|luxembourg|geneva|milan|madrid|barcelona)\b/i,
      /\b(uk|usa|us|france|germany|switzerland|netherlands|spain|italy|japan|china|india)\b/i,
      /\b(royaume-uni|états-unis|france|allemagne|suisse|pays-bas|espagne|italie|japon|chine|inde)\b/i, // French country names
    ];

    return locationPatterns.some((pattern) => pattern.test(text));
  }

  /**
   * Parse education line
   */
  parseEducationLine(line, parsed) {
    if (!parsed.education.find((e) => e.institution === line)) {
      if (
        line.match(
          /(university|université|universidad|college|school|école|institute|academy)/i
        )
      ) {
        parsed.education.push({
          institution: line,
          degree: "",
          dates: "",
          details: "",
        });
      } else if (parsed.education.length > 0) {
        const current = parsed.education[parsed.education.length - 1];
        if (
          !current.degree &&
          line.match(
            /(bsc|ba|bba|msc|ma|mba|phd|bachelor|master|licence|diplôme|diploma)/i
          )
        ) {
          current.degree = line;
        } else if (!current.dates && this.containsDate(line)) {
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
      .filter((s) => s && s.length > 1);
    parsed.skills = [...parsed.skills, ...skills];
  }

  /**
   * Enhance bullet point with power verbs and metrics
   */
  enhanceBullet(bullet) {
    // Don't double-enhance
    if (this.isPowerVerb(bullet.split(" ")[0])) {
      return this.injectMetricsIfMissing(bullet);
    }

    let enhanced = bullet;

    // Ensure it starts with a power verb
    const firstWord = bullet.split(" ")[0];
    if (!this.isPowerVerb(firstWord)) {
      const category = this.detectBulletCategory(bullet);
      const verbs = this.powerVerbs[category] || this.powerVerbs.achievement;
      const selectedVerb = verbs[Math.floor(Math.random() * verbs.length)];

      // Handle different starting patterns
      if (firstWord.match(/^[A-Z]/)) {
        // Starts with capital letter - replace first word
        enhanced = selectedVerb + " " + bullet.split(" ").slice(1).join(" ");
      } else {
        // Starts with lowercase - prepend verb
        enhanced =
          selectedVerb + " " + bullet.charAt(0).toLowerCase() + bullet.slice(1);
      }
    }

    // Add metrics if missing
    return this.injectMetricsIfMissing(enhanced);
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
  // ---------- Category Scoring Config (finance-tuned) ----------
  getCategoryDefs() {
    if (this._CATEGORY_DEFS) return this._CATEGORY_DEFS;

    // boundary helpers
    const wb = String.raw`\b`;
    const num = String.raw`\d+(?:[.,]\d+)?`;
    const kmb = String.raw`(?:k|m|bn|b)`;
    const curr = String.raw`[$£€]\s?${num}(?:[,\s]\d{3})*(?:\.\d+)?(?:\s?${kmb})?`;
    const pct = String.raw`${num}\s?%`;

    // precompile regex helpers
    const r = (p) => new RegExp(p, "i");
    const or = (arr) => new RegExp(arr.map((s) => `(?:${s})`).join("|"), "i");

    this._CATEGORY_DEFS = {
      leadership: {
        weight: 1.15,
        patterns: [
          r(
            `${wb}(led|managed|supervis(?:ed|ing)?|mentored|coached|oversaw|coordinat(?:ed|ing)?|directed|headed|chaired|delegat(?:ed|ing)?)${wb}`
          ),
          r(`${wb}(built|scaled)\s+(?:a\s+)?team${wb}`),
          r(`${wb}(hired|recruited|grew)\s+(?:a\s+)?team${wb}`),
        ],
      },
      strategy: {
        weight: 1.1,
        patterns: [
          r(
            `${wb}(strateg(?:y|ic)|roadmap|vision|position(?:ing)?|go[- ]?to[- ]?market|market entry|commercial strategy)${wb}`
          ),
          r(`${wb}(pricing|portfolio strategy|capital allocation)${wb}`),
        ],
      },
      financialImpact: {
        weight: 1.2,
        patterns: [
          r(
            `${wb}(revenue|profit|margin|ebitda|p&l|cash flow|cashflow|roi|roic|irr|moic|npv|dcf|return on)${wb}`
          ),
          r(`${curr}|${pct}`), // numbers, currency, %
        ],
      },
      analysis: {
        weight: 1.1,
        patterns: [
          r(
            `${wb}(analy(?:s(?:is)?|zed)|evaluat(?:ed|ion)|assess(?:ed|ment)|resear(?:ch|ched)|investigat(?:ed|ion))${wb}`
          ),
          r(
            `${wb}(valuation|model(?:ed|ling)?|forecast(?:ed|ing)?|scenario|sensitivity|benchmark(?:ed|ing)?)${wb}`
          ),
          r(`${wb}(due diligence|teaser|cim|ic memo|investment memo)${wb}`),
        ],
      },
      execution: {
        weight: 1.0,
        patterns: [
          r(
            `${wb}(implement(?:ed|ation)|execut(?:ed|ion)|deployed|launched|rolled out|delivered|completed)${wb}`
          ),
        ],
      },
      optimization: {
        weight: 1.05,
        patterns: [
          r(
            `${wb}(optim(?:iz|is)ed|improv(?:ed|ement)|enhanc(?:ed|ement)|streamlin(?:ed|ing)|autom(?:ated|ation)|reengineer(?:ed|ing)|standardiz(?:ed|ation)|transformed)${wb}`
          ),
        ],
      },
      compliance: {
        weight: 1.15,
        patterns: [
          r(
            `${wb}(compliance|policy|procedure|control|audit|governance|sox|ifrs|gaap|sec|finra|esma|mifid|gdpr|hipaa|aml|kyc|basel|solvency ii)${wb}`
          ),
        ],
      },
      riskManagement: {
        weight: 1.15,
        patterns: [
          r(
            `${wb}(risk|credit risk|market risk|operational risk|liquidity risk|stress test|var|loss model|limits|controls)${wb}`
          ),
        ],
      },
      technical: {
        weight: 1.05,
        patterns: [
          r(
            `${wb}(python|r|sql|excel|vba|tableau|power\s*bi|look(er|ml)|snowflake|databricks|sas|stata|spark|git|api|automation|ml|nlp|saas|cloud|aws|gcp|azure)${wb}`
          ),
        ],
      },
      dealsTransactions: {
        weight: 1.2,
        patterns: [
          r(
            `${wb}(m&a|mergers?|\bacq(?:uisition)?s?\b|buyout|lbo|add[- ]on|bolt[- ]on|carve[- ]out|divest(?:iture)?|exit|ipo|sell[- ]side|buy[- ]side|deal|transaction|term sheet)${wb}`
          ),
        ],
      },
      fundraisingIR: {
        weight: 1.15,
        patterns: [
          r(
            `${wb}(fundrais(?:ed|ing)|roadshow|lp relations?|limited partners?|commitments?|capital raise|co[- ]?invest(?:ment)?)${wb}`
          ),
        ],
      },
      portfolioOps: {
        weight: 1.1,
        patterns: [
          r(
            `${wb}(portfolio company|value creation|synerg(?:y|ies)|integration|post[- ]merger|operating partner|commercial due diligence)${wb}`
          ),
        ],
      },
      communication: {
        weight: 1.0,
        patterns: [
          r(
            `${wb}(present(?:ed|ation)|communicat(?:ed|ion)|report(?:ed|ing)?|brief(?:ed|ing)?|negotiat(?:ed|ion)|stakeholder|advis(?:ed|ory)|liais(?:ed|on)|represented)${wb}`
          ),
        ],
      },
      projectMgmt: {
        weight: 1.0,
        patterns: [
          r(
            `${wb}(project|program(?:me)?|pm[io]|milestone|workstream|timeline|deliverables|backlog|roadmap|scrum|kanban)${wb}`
          ),
        ],
      },
      salesClient: {
        weight: 0.95,
        patterns: [
          r(
            `${wb}(client|customer|account|pipeline|win rate|proposal|rfi|rfp|renewal|upsell|cross[- ]sell|churn)${wb}`
          ),
        ],
      },
    };

    // power verbs → category nudges (first verb in bullet is strong signal)
    this._VERB_HINTS = {
      leadership: [
        "led",
        "managed",
        "supervised",
        "mentored",
        "coached",
        "oversaw",
        "coordinated",
        "directed",
        "chaired",
        "delegated",
      ],
      strategy: [
        "strategized",
        "positioned",
        "spearheaded",
        "pioneered",
        "architected",
        "formulated",
        "mapped",
      ],
      financialImpact: [
        "grew",
        "increased",
        "expanded",
        "boosted",
        "improved",
        "monetized",
        "commercialized",
        "optimized",
      ],
      analysis: [
        "analyzed",
        "valued",
        "modeled",
        "assessed",
        "evaluated",
        "researched",
        "investigated",
        "benchmarked",
        "forecasted",
      ],
      execution: [
        "implemented",
        "executed",
        "deployed",
        "launched",
        "delivered",
        "completed",
        "rolled",
      ],
      optimization: [
        "optimized",
        "streamlined",
        "enhanced",
        "automated",
        "standardized",
        "reengineered",
        "transformed",
      ],
      compliance: [
        "audited",
        "complied",
        "enforced",
        "governed",
        "validated",
        "tested",
      ],
      riskManagement: [
        "mitigated",
        "hedged",
        "stress-tested",
        "monitored",
        "controlled",
      ],
      dealsTransactions: [
        "acquired",
        "divested",
        "exited",
        "structured",
        "negotiated",
        "closed",
        "originated",
        "executed",
      ],
      fundraisingIR: [
        "raised",
        "fundraised",
        "secured",
        "committed",
        "syndicated",
        "roadshowed",
      ],
      communication: [
        "presented",
        "advised",
        "briefed",
        "liaised",
        "negotiated",
        "reported",
      ],
      projectMgmt: ["planned", "scoped", "prioritized", "tracked", "delivered"],
      salesClient: [
        "sold",
        "renewed",
        "upsold",
        "cross-sold",
        "pitched",
        "closed",
      ],
    };

    // numeric/KPI amplifiers
    this._AMPLIFIERS = {
      percent: new RegExp(`${pct}`, "i"),
      currency: new RegExp(`${curr}`, "i"),
      quantity: new RegExp(`${wb}${num}\\s?(?:${kmb})?${wb}`, "i"),
    };

    return this._CATEGORY_DEFS;
  }

  // ---------- Scorer: returns multiple categories with confidence ----------
  scoreBulletCategories(bullet) {
    const defs = this.getCategoryDefs();
    const text = (bullet || "").toLowerCase();
    const scores = {};
    const matches = {};

    // init
    Object.keys(defs).forEach((k) => {
      scores[k] = 0;
      matches[k] = [];
    });

    // regex scoring
    for (const [cat, def] of Object.entries(defs)) {
      for (const rgx of def.patterns) {
        const m = text.match(rgx);
        if (m) {
          scores[cat] += def.weight;
          matches[cat].push(m[0]);
        }
      }
    }

    // first verb hint (very strong)
    const firstWord = (text.match(/^[a-z]+/) || [""])[0];
    if (firstWord) {
      for (const [cat, verbs] of Object.entries(this._VERB_HINTS)) {
        if (verbs.includes(firstWord)) {
          scores[cat] += 0.6;
          matches[cat].push(`verb:${firstWord}`);
        }
      }
    }

    // numeric/KPI amplifiers
    if (
      this._AMPLIFIERS.percent.test(text) ||
      this._AMPLIFIERS.currency.test(text)
    ) {
      scores.financialImpact += 0.7;
      matches.financialImpact.push("metric:+");
    }
    if (
      this._AMPLIFIERS.quantity.test(text) &&
      /team|client|model|deal|transaction|portfolio/.test(text)
    ) {
      // quantity tied to typical nouns
      const bumps = [
        "leadership",
        "salesClient",
        "analysis",
        "dealsTransactions",
        "portfolioOps",
      ];
      bumps.forEach((c) => {
        scores[c] += 0.25;
        matches[c].push("qty:+");
      });
    }

    // Cross-category co-signals
    if (
      scores.dealsTransactions > 0 &&
      /valuation|model|due diligence|ic memo|cim/.test(text)
    ) {
      scores.analysis += 0.35;
      matches.analysis.push("deal+analysis");
    }
    if (scores.leadership > 0 && /strategy|roadmap|vision/.test(text)) {
      scores.strategy += 0.3;
      matches.strategy.push("leadership+strategy");
    }
    if (scores.compliance > 0 && /risk|control|policy|audit/.test(text)) {
      scores.riskManagement += 0.25;
      matches.riskManagement.push("comp+risk");
    }

    // Normalize → sorted categories
    const categories = Object.keys(scores)
      .map((name) => ({
        name,
        score: +scores[name].toFixed(3),
        matched: matches[name],
      }))
      .filter((c) => c.score > 0)
      .sort((a, b) => b.score - a.score);

    const top = categories[0] || { name: "other", score: 0, matched: [] };

    return { top, categories, scores, matches };
  }

  // ---------- Back-compat wrapper: returns top category as string ----------
  detectBulletCategory(bullet) {
    return this.scoreBulletCategories(bullet).top.name;
  }

  /**
   * Inject metrics only if missing
   */
  /**
   * Inject metrics into bullet points intelligently — only if missing.
   * Handles UK/EU/US CV styles and finance-relevant contexts.
   */
  injectMetricsIfMissing(bullet) {
    // 🛑 Skip if already contains numbers or currency
    if (bullet.match(/\d+[%$£€MBK]?|[$£€][\d,]+/)) return bullet;

    let updated = bullet;
    const lower = bullet.toLowerCase();

    const pick = (arr) => arr[Math.floor(Math.random() * arr.length)];

    // 🌍 Currency detection (can be improved to match CV region later)
    const currency = pick(["£", "$", "€"]);

    /**
     * 🎲 Generate a realistic number with weighted randomness
     * e.g. getNumber(5, 100, { skew: 'low' }) → more small numbers
     * e.g. getNumber(100, 10000, { skew: 'high' }) → more large numbers
     */
    function getNumber(min, max, opts = {}) {
      const { skew = "none" } = opts;
      let r = Math.random();
      if (skew === "low") r = Math.pow(r, 2); // bias towards low end
      else if (skew === "high") r = Math.sqrt(r); // bias towards high end
      return Math.floor(min + (max - min) * r);
    }

    /**
     * 💰 Format currency realistically
     * - Below 1M → show "k" or full number
     * - 1M+ → show "M"
     * - 1000M+ → show "B"
     */
    function formatCurrency(amount) {
      if (amount >= 1000) {
        return `${currency}${(amount / 1000).toFixed(1)}B`;
      } else if (amount >= 1) {
        return `${currency}${amount}M`;
      } else {
        return `${currency}${(amount * 1000).toFixed(0)}k`;
      }
    }

    const replaceWord = (pattern, replacement) => {
      updated = updated.replace(
        new RegExp(`\\b${pattern}\\b`, "i"),
        replacement
      );
    };

    // 👥 Team / People
    if (/\bteam\b/i.test(lower)) {
      const teamSize = getNumber(3, 100, { skew: "low" });
      replaceWord("team", `${teamSize}+ member team`);
    }

    // 💼 Project
    else if (/\bproject\b/i.test(lower)) {
      const amount = getNumber(1, 500, { skew: "low" });
      replaceWord("project", `${formatCurrency(amount)} project`);
    }

    // 📊 Portfolio
    else if (/\bportfolio\b/i.test(lower)) {
      const amount = getNumber(50, 5000, { skew: "high" });
      replaceWord("portfolio", `${formatCurrency(amount)} portfolio`);
    }

    // 👤 Clients / Users
    else if (/\bclient\b/i.test(lower)) {
      const count = getNumber(5, 500, { skew: "low" });
      replaceWord("client", `${count}+ clients`);
    } else if (/\buser\b/i.test(lower)) {
      const count = getNumber(1000, 500000, { skew: "high" });
      replaceWord("user", `${count.toLocaleString()}+ users`);
    }

    // 🧮 Models
    else if (/\bmodel\b/i.test(lower)) {
      const models = getNumber(5, 100, { skew: "low" });
      replaceWord("model", `${models}+ financial models`);
    }

    // 📈 Revenue / Profit / Savings
    else if (/\brevenue\b/i.test(lower)) {
      const percent = getNumber(5, 75, { skew: "low" });
      replaceWord("revenue", `revenue by ${percent}%`);
    } else if (/\bprofit\b/i.test(lower)) {
      const percent = getNumber(5, 50, { skew: "low" });
      replaceWord("profit", `profit by ${percent}%`);
    } else if (/\bsavings?\b/i.test(lower)) {
      const amount = getNumber(1, 250, { skew: "high" });
      replaceWord("savings?", `${formatCurrency(amount)} in savings`);
    } else if (/\bcosts?\b/i.test(lower)) {
      const percent = getNumber(5, 50, { skew: "low" });
      replaceWord("costs?", `costs by ${percent}%`);
    }

    // 📑 Reports / KPIs
    else if (/\breport\b/i.test(lower)) {
      const count = getNumber(5, 500, { skew: "low" });
      replaceWord("report", `${count}+ reports`);
    } else if (/\bkpi\b/i.test(lower) || /\bmetric\b/i.test(lower)) {
      const count = getNumber(5, 30, { skew: "low" });
      replaceWord("(kpi|metric)", `${count}+ KPIs`);
    }

    // 🌍 Geography / Markets
    else if (/\bmarket\b/i.test(lower)) {
      const markets = getNumber(2, 25, { skew: "low" });
      replaceWord("market", `${markets}+ markets`);
    } else if (/\bcountry\b/i.test(lower)) {
      const countries = getNumber(2, 20, { skew: "low" });
      replaceWord("country", `${countries}+ countries`);
    }

    // ⚙️ Processes
    else if (/\bprocess\b/i.test(lower)) {
      const efficiency = getNumber(10, 60, { skew: "low" });
      replaceWord("process", `process, improving efficiency by ${efficiency}%`);
    }

    return updated;
  }

  /**
   * Extract dates from text
   */
  extractDates(text) {
    const dateMatch = text.match(/\d{4}|present|current|présent|actuel/gi);
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

  // ... (rest of the rendering methods remain the same as original)
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

  generateSummary(summary) {
    return `
            <section class="wsj-cv-section">
                <h2 class="wsj-cv-section-title">Professional Summary</h2>
                <div class="wsj-cv-summary">${summary}</div>
            </section>
        `;
  }

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

  highlightMetrics(text) {
    // Highlight percentages, dollar amounts, and numbers
    return text.replace(
      /(\$[\d,]+[MBK]?|\d+%|\d+\+)/g,
      '<span class="wsj-cv-metric">$1</span>'
    );
  }

  animateElements() {
    const sections = this.container.querySelectorAll(".wsj-cv-section");
    sections.forEach((section, index) => {
      section.style.animationDelay = `${0.1 * (index + 1)}s`;
    });
  }

  updateFromText(text) {
    const parsed = this.parseTextInput(text);
    this.render(parsed);
  }

  getData() {
    return this.cvData;
  }

  setData(data) {
    this.cvData = { ...this.cvData, ...data };
    this.render();
  }

  save() {
    const event = new CustomEvent("wsj-cv-save", {
      detail: this.cvData,
    });
    document.dispatchEvent(event);
  }

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
}

// Export for global use
window.WSJCVRendererUltimate = WSJCVRendererUltimate;

// Replace the original if it exists
if (window.WSJCVRenderer) {
  window.WSJCVRenderer = WSJCVRendererUltimate;
}

// Auto-initialize if container exists
document.addEventListener("DOMContentLoaded", () => {
  if (document.querySelector(".wsj-cv-container")) {
    window.wsjCV = new WSJCVRendererUltimate();
  }
});

console.log("✅ WSJ CV Renderer Ultimate loaded - handles all CV formats");
