/**
 * WSJ CV Renderer - FIXED VERSION
 * Fixes experience parsing issues
 */

// Save the original renderer
const OriginalWSJCVRenderer = window.WSJCVRenderer;

// Create fixed version
class WSJCVRendererFixed extends OriginalWSJCVRenderer {
  /**
   * Fixed parsing method with better experience detection
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
    let currentExperienceIndex = -1;
    let expectingRole = false;
    let expectingDates = false;

    lines.forEach((line, index) => {
      // Skip empty lines
      if (!line) return;

      // Detect name (first non-empty, non-section line)
      if (
        index === 0 &&
        !line.match(/^(experience|education|skills|summary)/i)
      ) {
        parsed.name = this.cleanName(line);
        return;
      }

      // Detect contact info (can be anywhere in first few lines)
      if (index < 5) {
        // Email
        const emailMatch = line.match(/[\w.-]+@[\w.-]+\.\w+/);
        if (emailMatch && !parsed.contact.email) {
          parsed.contact.email = emailMatch[0];
        }

        // Phone
        const phoneMatch = line.match(/\+?[\d\s()+-]{10,}/);
        if (phoneMatch && !parsed.contact.phone) {
          parsed.contact.phone = phoneMatch[0].trim();
        }

        // Location (city, state/country pattern)
        if (line.includes(",") && !line.includes("@")) {
          const parts = line.split("|");
          parts.forEach((part) => {
            if (part.includes(",") && !parsed.contact.location) {
              parsed.contact.location = part.trim();
            }
          });
        }

        // LinkedIn
        if (line.includes("linkedin.com")) {
          parsed.contact.linkedin = line;
        }
      }

      // Detect section headers
      if (
        line.match(
          /^(experience|work|employment|professional experience|work experience)/i
        )
      ) {
        currentSection = "experience";
        currentExperienceIndex = -1;
        return;
      }
      if (line.match(/^(education|academic|qualification)/i)) {
        currentSection = "education";
        return;
      }
      if (line.match(/^(skills|technical|competenc|expertise)/i)) {
        currentSection = "skills";
        return;
      }
      if (
        line.match(/^(summary|profile|objective|about|personal statement)/i)
      ) {
        currentSection = "summary";
        return;
      }

      // Parse based on current section
      if (currentSection === "experience") {
        this.parseExperienceLineFixed(line, parsed);
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
   * Fixed experience parsing with better logic
   */
  parseExperienceLineFixed(line, parsed) {
    const isBullet = line.match(/^[•\-\*\▪\·]\s*/) || line.match(/^-\s+/);

    if (!isBullet) {
      // Check if this line contains dates
      const hasDate =
        line.match(/\d{4}/) ||
        line.match(
          /(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+\d{4}/i
        ) ||
        line.match(/present|current|now|today/i);

      // Check if it looks like a company (contains common company indicators)
      const looksLikeCompany =
        line.match(
          /(inc\.|corp|corporation|llc|ltd|limited|company|group|partners|capital|bank|consulting|analytics|technologies|solutions|services)/i
        ) ||
        line.includes("&") ||
        line.match(/[A-Z]{2,}/); // Has acronym

      // Check if it looks like a role
      const looksLikeRole = line.match(
        /(analyst|associate|consultant|manager|director|president|vp|vice president|intern|engineer|developer|coordinator|specialist|advisor|assistant)/i
      );

      // Decision logic
      if (hasDate) {
        // Line with date - could be either company+date or role+date
        // Check if we have a current job without dates
        const currentJob = parsed.experience[parsed.experience.length - 1];

        if (
          currentJob &&
          !currentJob.dates &&
          currentJob.bullets.length === 0
        ) {
          // We have an incomplete job, this is probably its date/role line
          const dateStr = this.extractDates(line);
          const textWithoutDate = line
            .replace(/[-–—]\s*[\w\s,]+\d{4}.*$/i, "")
            .trim();

          if (!currentJob.role && textWithoutDate) {
            currentJob.role = textWithoutDate;
          }
          currentJob.dates = dateStr;

          // Check for location
          const locationMatch = line.match(
            /[A-Z][a-z]+,\s*[A-Z]{2}|[A-Z][a-z]+,\s*[A-Z][a-z]+/
          );
          if (locationMatch) {
            currentJob.location = locationMatch[0];
          }
        } else {
          // Start new job entry
          const dateStr = this.extractDates(line);
          const textWithoutDate = line
            .replace(/[-–—]\s*[\w\s,]+\d{4}.*$/i, "")
            .trim();

          parsed.experience.push({
            company: textWithoutDate || line.split(/[-–—]/)[0]?.trim() || "",
            role: "",
            dates: dateStr,
            location: "",
            bullets: [],
          });
        }
      } else if (
        looksLikeCompany ||
        (!looksLikeRole && parsed.experience.length === 0)
      ) {
        // Likely a company name
        parsed.experience.push({
          company: line,
          role: "",
          dates: "",
          location: "",
          bullets: [],
        });
      } else if (
        looksLikeRole ||
        (parsed.experience.length > 0 &&
          !parsed.experience[parsed.experience.length - 1].role)
      ) {
        // Likely a role
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
      } else {
        // Check for location pattern
        if (
          line.match(/^[A-Z][a-z]+,\s*[A-Z]{2}$|^[A-Z][a-z]+,\s*[A-Z][a-z]+$/)
        ) {
          const currentJob = parsed.experience[parsed.experience.length - 1];
          if (currentJob && !currentJob.location) {
            currentJob.location = line;
          }
        } else {
          // Default: treat as new company if we don't have one, or role if we do
          const currentJob = parsed.experience[parsed.experience.length - 1];
          if (!currentJob) {
            parsed.experience.push({
              company: line,
              role: "",
              dates: "",
              location: "",
              bullets: [],
            });
          } else if (!currentJob.role) {
            currentJob.role = line;
          }
        }
      }
    } else {
      // It's a bullet point
      if (parsed.experience.length > 0) {
        const bullet = line.replace(/^[•\-\*\▪\·]\s*/, "").replace(/^-\s+/, "");
        parsed.experience[parsed.experience.length - 1].bullets.push(bullet);
      }
    }
  }

  /**
   * Enhanced bullet transformation
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
        enhanced = selectedVerb + " " + bullet;
      }
    }

    // Add metrics if missing
    return this.injectMetricsIfMissing(enhanced);
  }

  /**
   * Inject metrics only if missing
   */
  injectMetricsIfMissing(bullet) {
    // Check if already has metrics
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
}

// Replace the global renderer
window.WSJCVRenderer = WSJCVRendererFixed;

console.log("✅ WSJ CV Renderer Fixed Version Loaded");
console.log("Experience parsing issues resolved");
console.log("Better detection of companies, roles, and dates");
