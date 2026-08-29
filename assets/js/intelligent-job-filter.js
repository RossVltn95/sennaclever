/**
 * Intelligent Job Filter System
 * Advanced NLP-like filtering for job searches
 */

class IntelligentJobFilter {
  constructor() {
    // Industry abbreviations and their full terms
    this.industryMappings = {
      // Investment Banking
      ib: {
        terms: [
          "investment banking",
          "investment bank",
          "ibd",
          "capital markets",
          "ecm",
          "dcm",
          "m&a",
          "mergers",
          "acquisitions",
        ],
        related: [
          "analyst",
          "associate",
          "vice president",
          "vp",
          "director",
          "managing director",
          "md",
        ],
        companies: [
          "goldman",
          "morgan stanley",
          "jp morgan",
          "jpmorgan",
          "barclays",
          "citi",
          "bofa",
          "deutsche",
          "ubs",
          "credit suisse",
        ],
      },

      // Private Equity
      pe: {
        terms: [
          "private equity",
          "buyout",
          "lbo",
          "leveraged buyout",
          "growth equity",
          "portfolio",
          "fund",
        ],
        related: [
          "analyst",
          "associate",
          "principal",
          "partner",
          "investment professional",
        ],
        companies: [
          "blackstone",
          "kkr",
          "apollo",
          "carlyle",
          "tpg",
          "warburg",
          "bain capital",
          "advent",
        ],
      },

      // Venture Capital
      vc: {
        terms: [
          "venture capital",
          "venture",
          "seed",
          "series a",
          "series b",
          "startup",
          "early stage",
          "growth stage",
        ],
        related: [
          "analyst",
          "associate",
          "principal",
          "partner",
          "investment",
          "portfolio",
        ],
        companies: [
          "sequoia",
          "andreessen",
          "accel",
          "benchmark",
          "greylock",
          "kleiner",
          "bessemer",
        ],
      },

      // Hedge Funds
      hf: {
        terms: [
          "hedge fund",
          "fund manager",
          "portfolio manager",
          "quant",
          "quantitative",
          "trading",
          "systematic",
        ],
        related: [
          "analyst",
          "trader",
          "pm",
          "researcher",
          "risk manager",
          "strategist",
        ],
        companies: [
          "bridgewater",
          "citadel",
          "millennium",
          "two sigma",
          "de shaw",
          "renaissance",
          "point72",
        ],
      },

      // Consulting
      consulting: {
        terms: [
          "consulting",
          "consultant",
          "advisory",
          "strategy",
          "management consulting",
          "transformation",
        ],
        related: [
          "analyst",
          "associate",
          "manager",
          "senior manager",
          "principal",
          "partner",
        ],
        companies: [
          "mckinsey",
          "bain",
          "bcg",
          "deloitte",
          "pwc",
          "ey",
          "kpmg",
          "accenture",
          "oliver wyman",
        ],
      },

      // Technology
      tech: {
        terms: [
          "technology",
          "software",
          "engineer",
          "developer",
          "programming",
          "coding",
          "full stack",
          "frontend",
          "backend",
        ],
        related: [
          "junior",
          "senior",
          "lead",
          "principal",
          "architect",
          "manager",
          "director",
        ],
        companies: [
          "google",
          "meta",
          "amazon",
          "apple",
          "microsoft",
          "netflix",
          "stripe",
          "uber",
        ],
      },

      // Data Science
      data: {
        terms: [
          "data science",
          "data scientist",
          "machine learning",
          "ml",
          "ai",
          "artificial intelligence",
          "analytics",
          "deep learning",
        ],
        related: [
          "analyst",
          "engineer",
          "researcher",
          "scientist",
          "lead",
          "manager",
        ],
        companies: [
          "openai",
          "deepmind",
          "anthropic",
          "databricks",
          "palantir",
          "snowflake",
        ],
      },

      // Product Management
      pm: {
        terms: [
          "product manager",
          "product management",
          "product owner",
          "product lead",
          "product strategy",
        ],
        related: ["associate", "senior", "principal", "director", "vp", "head"],
        companies: [
          "google",
          "meta",
          "amazon",
          "microsoft",
          "airbnb",
          "spotify",
          "netflix",
        ],
      },

      // Risk Management
      risk: {
        terms: [
          "risk",
          "compliance",
          "audit",
          "regulatory",
          "controls",
          "sox",
          "basel",
          "var",
          "credit risk",
          "market risk",
          "operational risk",
        ],
        related: [
          "analyst",
          "associate",
          "manager",
          "director",
          "officer",
          "specialist",
        ],
        companies: [
          "banks",
          "financial institutions",
          "big four",
          "regulators",
        ],
      },

      // Sales & Trading
      "s&t": {
        terms: [
          "sales",
          "trading",
          "trader",
          "sales and trading",
          "markets",
          "equities",
          "fixed income",
          "fx",
          "derivatives",
        ],
        related: [
          "analyst",
          "associate",
          "trader",
          "salesperson",
          "vp",
          "director",
          "md",
        ],
        companies: [
          "goldman",
          "morgan stanley",
          "jp morgan",
          "citi",
          "barclays",
          "ubs",
        ],
      },

      // Asset Management
      am: {
        terms: [
          "asset management",
          "portfolio management",
          "investment management",
          "wealth management",
          "fund management",
        ],
        related: [
          "analyst",
          "associate",
          "portfolio manager",
          "client advisor",
          "relationship manager",
        ],
        companies: [
          "blackrock",
          "vanguard",
          "fidelity",
          "pimco",
          "schroders",
          "aberdeen",
        ],
      },

      // Fintech
      fintech: {
        terms: [
          "fintech",
          "financial technology",
          "payments",
          "blockchain",
          "crypto",
          "defi",
          "neobank",
          "digital banking",
        ],
        related: [
          "engineer",
          "developer",
          "product",
          "analyst",
          "manager",
          "founder",
        ],
        companies: [
          "stripe",
          "square",
          "paypal",
          "revolut",
          "wise",
          "coinbase",
          "robinhood",
        ],
      },
    };

    // Common query patterns
    this.queryPatterns = {
      "show me": ["display", "find", "search", "get", "list"],
      jobs: ["roles", "positions", "opportunities", "openings", "careers"],
      in: ["at", "for", "within", "related to"],
      senior: ["sr", "lead", "principal", "staff", "experienced"],
      junior: ["jr", "entry", "graduate", "early career", "associate"],
    };

    // Salary range patterns
    this.salaryPatterns = {
      "six figure": { min: 100000, max: 999999 },
      "high paying": { min: 150000, max: null },
      "entry level": { min: 50000, max: 100000 },
      "mid level": { min: 100000, max: 200000 },
      "senior level": { min: 200000, max: null },
    };
  }

  /**
   * Parse user input and extract intent
   */
  parseQuery(input) {
    const query = input.toLowerCase();
    const result = {
      industry: null,
      level: null,
      location: null,
      salary: null,
      companies: [],
      keywords: [],
      skills: [],
      raw: input,
    };

    // Check for industry abbreviations
    for (const [abbrev, mapping] of Object.entries(this.industryMappings)) {
      // Check for exact abbreviation match
      const abbrevPattern = new RegExp(`\\b${abbrev}\\b`, "i");
      if (abbrevPattern.test(query)) {
        result.industry = abbrev;
        result.keywords.push(...mapping.terms);
        break;
      }

      // Check for full terms
      for (const term of mapping.terms) {
        if (query.includes(term)) {
          result.industry = abbrev;
          result.keywords.push(...mapping.terms);
          break;
        }
      }
    }

    // Extract level (senior, junior, etc.)
    if (query.includes("senior") || query.includes("sr")) {
      result.level = "senior";
    } else if (
      query.includes("junior") ||
      query.includes("jr") ||
      query.includes("entry")
    ) {
      result.level = "junior";
    } else if (query.includes("mid") || query.includes("associate")) {
      result.level = "mid";
    } else if (query.includes("lead") || query.includes("principal")) {
      result.level = "lead";
    } else if (query.includes("manager") || query.includes("director")) {
      result.level = "management";
    }

    // Extract location preferences
    if (query.includes("remote")) {
      result.location = "remote";
    } else if (query.includes("london")) {
      result.location = "london";
    } else if (query.includes("new york") || query.includes("ny")) {
      result.location = "new york";
    } else if (query.includes("san francisco") || query.includes("sf")) {
      result.location = "san francisco";
    }

    // Extract salary expectations
    for (const [pattern, range] of Object.entries(this.salaryPatterns)) {
      if (query.includes(pattern)) {
        result.salary = range;
        break;
      }
    }

    // Extract specific salary numbers
    const salaryMatch = query.match(/(\d+)k/i);
    if (salaryMatch) {
      const amount = parseInt(salaryMatch[1]) * 1000;
      result.salary = { min: amount, max: null };
    }

    // Extract company names if mentioned
    for (const mapping of Object.values(this.industryMappings)) {
      for (const company of mapping.companies) {
        if (query.includes(company)) {
          result.companies.push(company);
        }
      }
    }

    // Extract skills
    const commonSkills = [
      "python",
      "javascript",
      "react",
      "node",
      "sql",
      "excel",
      "powerpoint",
      "financial modeling",
      "valuation",
      "dcf",
      "lbo model",
      "pitch deck",
      "agile",
      "scrum",
      "aws",
      "azure",
      "gcp",
      "docker",
      "kubernetes",
    ];

    for (const skill of commonSkills) {
      if (query.includes(skill)) {
        result.skills.push(skill);
      }
    }

    return result;
  }

  /**
   * Filter jobs based on parsed query
   */
  filterJobs(jobs, parsedQuery) {
    if (
      !parsedQuery.industry &&
      !parsedQuery.level &&
      !parsedQuery.location &&
      !parsedQuery.salary &&
      parsedQuery.keywords.length === 0
    ) {
      return jobs; // No specific filters, return all
    }

    return jobs.filter((job) => {
      const jobTitle = (job.title || "").toLowerCase();
      const jobDescription = (job.description || "").toLowerCase();
      const jobCompany = (job.company || "").toLowerCase();
      const jobLocation = (job.location || "").toLowerCase();
      const jobSkills = (job.skills || []).join(" ").toLowerCase();
      const jobRequirements = (job.requirements || "").toLowerCase();

      // Combine all searchable text
      const searchableText = `${jobTitle} ${jobDescription} ${jobCompany} ${jobLocation} ${jobSkills} ${jobRequirements}`;

      // Check keywords
      if (parsedQuery.keywords.length > 0) {
        const keywordMatch = parsedQuery.keywords.some((keyword) =>
          searchableText.includes(keyword.toLowerCase())
        );
        if (!keywordMatch) return false;
      }

      // Check level
      if (parsedQuery.level) {
        const levelMatch = this.checkLevelMatch(jobTitle, parsedQuery.level);
        if (!levelMatch) return false;
      }

      // Check location
      if (parsedQuery.location) {
        if (parsedQuery.location === "remote") {
          if (
            !jobLocation.includes("remote") &&
            !jobLocation.includes("anywhere")
          ) {
            return false;
          }
        } else {
          if (!jobLocation.toLowerCase().includes(parsedQuery.location)) {
            return false;
          }
        }
      }

      // Check salary
      if (parsedQuery.salary) {
        const jobSalaryMin = job.salary_min || 0;
        const jobSalaryMax = job.salary_max || jobSalaryMin;

        if (parsedQuery.salary.min && jobSalaryMax < parsedQuery.salary.min) {
          return false;
        }
        if (parsedQuery.salary.max && jobSalaryMin > parsedQuery.salary.max) {
          return false;
        }
      }

      // Check companies
      if (parsedQuery.companies.length > 0) {
        const companyMatch = parsedQuery.companies.some((company) =>
          jobCompany.includes(company)
        );
        if (!companyMatch) return false;
      }

      // Check skills
      if (parsedQuery.skills.length > 0) {
        const skillMatch = parsedQuery.skills.some((skill) =>
          searchableText.includes(skill)
        );
        if (!skillMatch) return false;
      }

      return true;
    });
  }

  /**
   * Check if job title matches the level
   */
  checkLevelMatch(jobTitle, level) {
    const title = jobTitle.toLowerCase();

    switch (level) {
      case "senior":
        return (
          title.includes("senior") ||
          title.includes("sr") ||
          title.includes("lead") ||
          title.includes("principal") ||
          title.includes("staff")
        );
      case "junior":
        return (
          title.includes("junior") ||
          title.includes("jr") ||
          title.includes("entry") ||
          title.includes("graduate") ||
          (!title.includes("senior") && !title.includes("lead"))
        );
      case "mid":
        return (
          !title.includes("senior") &&
          !title.includes("junior") &&
          !title.includes("lead") &&
          !title.includes("principal")
        );
      case "lead":
        return (
          title.includes("lead") ||
          title.includes("principal") ||
          title.includes("staff") ||
          title.includes("head")
        );
      case "management":
        return (
          title.includes("manager") ||
          title.includes("director") ||
          title.includes("vp") ||
          title.includes("president") ||
          title.includes("head")
        );
      default:
        return true;
    }
  }

  /**
   * Generate response message based on query
   */
  generateResponse(parsedQuery, resultCount) {
    if (parsedQuery.industry) {
      const industryName = this.getIndustryName(parsedQuery.industry);
      if (resultCount > 0) {
        return `I've identified ${resultCount} ${industryName} opportunities that match your criteria.`;
      } else {
        return `I don't currently have specific ${industryName} roles, but let me show you related finance opportunities.`;
      }
    }

    if (parsedQuery.level) {
      return `Here are ${resultCount} ${parsedQuery.level}-level positions for you.`;
    }

    if (parsedQuery.location) {
      return `I found ${resultCount} opportunities in ${parsedQuery.location}.`;
    }

    if (parsedQuery.salary) {
      return `Here are ${resultCount} roles matching your salary expectations.`;
    }

    return `Based on your search, I found ${resultCount} relevant opportunities.`;
  }

  /**
   * Get friendly industry name
   */
  getIndustryName(abbrev) {
    const names = {
      ib: "Investment Banking",
      pe: "Private Equity",
      vc: "Venture Capital",
      hf: "Hedge Fund",
      consulting: "Consulting",
      tech: "Technology",
      data: "Data Science",
      pm: "Product Management",
      risk: "Risk Management",
      "s&t": "Sales & Trading",
      am: "Asset Management",
      fintech: "Fintech",
    };
    return names[abbrev] || abbrev;
  }

  /**
   * Get suggested follow-up questions
   */
  getSuggestions(parsedQuery) {
    const suggestions = [];

    if (parsedQuery.industry === "ib") {
      suggestions.push(
        "Show me bulge bracket IB roles",
        "Find boutique investment banks",
        "What about ECM or DCM positions?"
      );
    } else if (parsedQuery.industry === "pe") {
      suggestions.push(
        "Show me mega-fund PE opportunities",
        "Find middle-market PE roles",
        "What about growth equity positions?"
      );
    } else if (parsedQuery.industry === "tech") {
      suggestions.push(
        "Show me FAANG opportunities",
        "Find startup engineering roles",
        "What about remote tech positions?"
      );
    }

    return suggestions;
  }
}

// Export for use in other scripts
window.IntelligentJobFilter = IntelligentJobFilter;
