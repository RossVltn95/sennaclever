#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const repoRoot = path.join(__dirname, "..");
const shortcodePath = path.join(repoRoot, "includes/crm/class-crm-shortcodes.php");
const source = fs.readFileSync(shortcodePath, "utf8");
const failures = [];

function countMatches(pattern) {
  const matches = source.match(pattern);
  return matches ? matches.length : 0;
}

function assertIncludes(label, needle) {
  if (!source.includes(needle)) {
    failures.push(`Missing ${label}: ${needle}`);
  }
}

function assertNotIncludes(label, needle) {
  if (source.includes(needle)) {
    failures.push(`Unexpected ${label}: ${needle}`);
  }
}

assertIncludes(
  "lazy CV intelligence ontology URL",
  "'cvIntelligenceOntologyUrl' => file_exists(SFFC_PLUGIN_DIR . 'assets/data/cv-intelligence-ontology.json')"
);
if (countMatches(/'cvIntelligenceOntology'\s*=>\s*null/g) < 2) {
  failures.push("CV intelligence ontology must not be inlined into either apply-chat shortcode config");
}
assertNotIncludes(
  "inline CV intelligence ontology file read",
  "'cvIntelligenceOntology' => file_exists(SFFC_PLUGIN_DIR . 'assets/data/cv-intelligence-ontology.json')"
);

if (
  countMatches(
    /\$matching_roles_seed_limit\s*=\s*\(int\)\s*apply_filters\('sffc_crm_apply_chat_initial_matching_roles_seed_limit',\s*12\)/g
  ) < 2
) {
  failures.push("Both apply-chat render paths should cap initial matching role seeds at 12 by default");
}

if (
  countMatches(
    /\$matching_recruiters_seed_limit\s*=\s*\(int\)\s*apply_filters\('sffc_crm_apply_chat_initial_matching_recruiters_seed_limit',\s*8\)/g
  ) < 2
) {
  failures.push("Both apply-chat render paths should cap initial recruiter seeds at 8 by default");
}

if (countMatches(/get_apply_chat_matching_roles_seed\(\[\s*'location' => [\s\S]*?\], \$matching_roles_seed_limit\)/g) < 2) {
  failures.push("Initial render matching role seed calls must use the capped seed limit");
}

if (countMatches(/'limit'\s*=>\s*\$matching_recruiters_seed_limit/g) < 2) {
  failures.push("Initial recruiter seed queries must use the capped recruiter seed limit");
}

if (
  countMatches(
    /wp_script_add_data\('sffc-crm-apply-chat-article',\s*'defer',\s*true\)/g
  ) < 2
) {
  failures.push("The heavy apply-chat article script should be deferred in both shortcode render paths");
}

assertIncludes(
  "small seed limit support",
  "$limit = max(1, min(180, absint($limit)));"
);
assertNotIncludes(
  "forced 48 item first-load seed minimum",
  "$limit = max(48, min(180, absint($limit)));"
);

if (failures.length) {
  console.error("Apply chat first-load payload checks failed:");
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log("Apply chat first-load payload checks passed.");
