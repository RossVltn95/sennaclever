const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'includes/crm/class-crm-shortcodes.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/crm/community-editorial.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/crm/community-editorial.css'), 'utf8');

const checks = [
  {
    name: 'shortcode is registered',
    pass: php.includes("add_shortcode('sffc_community_apply_for_me'")
      && php.includes("add_shortcode('sffc_apply_for_me'")
      && php.includes("add_shortcode('sffc_community_apply_for_me_results'")
      && php.includes("add_shortcode('sffc_apply_for_me_results'")
  },
  {
    name: 'shortcode renderer exists',
    pass: php.includes('public function render_community_apply_for_me_shortcode')
      && php.includes('data-sffc-community-apply-for-me-shortcode-trigger')
  },
  {
    name: 'shortcode renders actual sequence panel',
    pass: php.includes('render_crm_editorial_community_apply_for_me_panel(true)')
      && php.includes('$show_sequence = $has_premium_access || (bool) $force_sequence;')
  },
  {
    name: 'shortcode config includes mandate nonce',
    pass: php.includes("'applyForMeMandateNonce' => wp_create_nonce('sffc_crm_editorial_apply_for_me_mandate')")
  },
  {
    name: 'launcher click opens existing panel',
    pass: js.includes('function openApplyForMeShortcodeSequence')
      && js.includes("event.target.closest('[data-sffc-community-apply-for-me-shortcode-trigger]')")
  },
  {
    name: 'standalone shortcode skips community feed side effects',
    pass: js.includes("contains('sffc-community-editorial__apply-for-me-shortcode')")
      && js.includes("contains('sffc-community-apply-results')")
      && js.includes('!isApplyForMeShortcodeRoot && storedGuestCvToken')
      && js.includes('!isApplyForMeShortcodeRoot && typeof window.requestIdleCallback')
  },
  {
    name: 'shortcode has standalone styling',
    pass: css.includes('.sffc-community-editorial__apply-for-me-shortcode')
      && css.includes('.sffc-community-editorial__apply-for-me-shortcode-button')
  },
  {
    name: 'apply results shortcode renderer exists',
    pass: php.includes('public function render_community_apply_for_me_results_shortcode')
      && php.includes('data-sffc-community-apply-results')
      && php.includes('data-sffc-community-apply-results-surface')
      && php.includes('data-sffc-community-apply-results-process-selected')
      && php.includes('data-sffc-community-apply-results-run')
  },
  {
    name: 'apply results shortcode config includes search and application task nonces',
    pass: php.includes("'jobsSearchNonce' => wp_create_nonce('sffc_crm_apply_chat_search_jobs')")
      && php.includes("'applicationTaskNonce' => wp_create_nonce('sffc_crm_apply_chat_queue_application_task')")
      && php.includes("'currentUserName' => is_user_logged_in()")
  },
  {
    name: 'apply results controller supports flexible criteria and OR-style searches',
    pass: js.includes('function initApplyForMeResultsShortcode')
      && js.includes('function applyResultsMandateHasCriteria')
      && js.includes('function buildApplyResultsSearchQueries')
      && js.includes('Promise.all(queries.slice(0, 8).map')
      && js.includes("event.target.closest('[data-sffc-community-apply-results-run]')")
      && js.includes("body.append('action', 'sffc_crm_apply_chat_search_jobs')")
  },
  {
    name: 'apply results cards support shortlist and multiselect',
    pass: js.includes('data-sffc-community-apply-results-shortlist')
      && js.includes('data-sffc-community-apply-results-select')
      && !js.includes('sffc-community-apply-results__btn--process')
      && !js.includes('>Process Application</button>')
      && js.includes('Shortlisted')
      && js.includes('✓')
  },
  {
    name: 'apply results selected roles can still be batch processed',
    pass: js.includes('data-sffc-community-apply-results-process-selected')
      && js.includes('function queueApplyResultsApplication')
      && js.includes('This role does not have a usable employer application link yet.')
      && js.includes("body.append('action', 'sffc_crm_apply_chat_queue_application_task')")
  },
  {
    name: 'apply results shortcode has isolated Google-style classes',
    pass: css.includes('.sffc-community-apply-results')
      && css.includes('.sffc-community-apply-results__card')
      && css.includes('.sffc-community-apply-results__search')
      && css.includes('.sffc-community-apply-results__process-selected')
      && css.includes('.sffc-community-apply-results__run')
      && css.includes('.sffc-community-apply-results__btn--shortlist.is-shortlisted')
      && css.includes('@media (max-width: 760px)')
  }
];

const failed = checks.filter((check) => !check.pass);

checks.forEach((check) => {
  console.log(`${check.pass ? 'PASS' : 'FAIL'} ${check.name}`);
});

if (failed.length) {
  process.exit(1);
}
