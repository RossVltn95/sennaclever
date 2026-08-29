/**
 * Recruiter Post Article - Application Pack Flow v3.0
 *
 * Handles:
 * - Tab navigation (Job Details, Application Pack, Express Interest)
 * - CV upload/paste functionality
 * - Application Pack product selection with Add to Pack buttons
 * - Generate Pack with premium subscription check
 * - Express Interest with upsell products and mailto functionality
 *
 * @package SennaCareers
 * @since 3.0.0
 */

const EMAIL_INTRO_VARIANTS = [
  (ctx) =>
    `Hi ${ctx.recruiterName},\n\nI spotted the ${ctx.rolePhrase} search and wanted to introduce myself directly before the shortlist locks.`,
  (ctx) =>
    `Hi ${ctx.recruiterName},\n\nI'm wrapping up a similar mandate and the ${ctx.rolePhrase} brief feels like a natural continuation, so I wanted to put my name forward.`,
  (ctx) =>
    `Hi ${ctx.recruiterName},\n\nThanks for circulating the ${
      ctx.roleTitle
    } opportunity${
      ctx.company ? ` at ${ctx.company}` : ""
    }. The scope mirrors my current focus, so I wanted to reach out personally.`,
  (ctx) =>
    `Hi ${ctx.recruiterName},\n\nI'm reaching out about the ${ctx.rolePhrase} because it's aligned with the kind of transformation projects I've delivered lately.`,
  (ctx) =>
    `Hi ${ctx.recruiterName},\n\nI've been partnering with teams on ${ctx.roleTitlePlural} and thought it made sense to share how I could support this search.`,
  (ctx) =>
    `Hi ${ctx.recruiterName},\n\nJumping in with a quick note on the ${ctx.rolePhrase}. The priorities outlined line up with my last few deliverables.`,
];

const EMAIL_BODY_VARIANTS = [
  (ctx) =>
    `${
      ctx.focusLine ? ctx.focusLine + " " : ""
    }I've been building playbooks across similar environments and can plug in fast. Here are a few highlights:\n${
      ctx.valueHeading
    }\n${ctx.valueBullets}`,
  (ctx) =>
    `In my current role I've been owning end-to-end delivery for mandates that look a lot like this. Snapshot:\n${ctx.valueHeading}\n${ctx.valueBullets}`,
  (ctx) =>
    `I've stayed close to ${ctx.roleTitlePlural} work for the past few years and have the scars to prove it. Recent proof points:\n${ctx.valueHeading}\n${ctx.valueBullets}`,
  (ctx) =>
    `The operators you described need someone who can move quickly and show a quantified track record. My last few wins are below:\n${ctx.valueHeading}\n${ctx.valueBullets}`,
  (ctx) =>
    `I've been building systems around this exact mix of strategy and execution. A few fast facts:\n${ctx.valueHeading}\n${ctx.valueBullets}`,
];

const EMAIL_CTA_VARIANTS = [
  (ctx) =>
    `Could we grab 15 minutes this week? I can send a short 30/60/90 outline before the call so you can see my angle.`,
  () =>
    `If you're still shortlisting, I'd value a quick conversation to compare notes and make sure I'm mapped correctly against the brief.`,
  () =>
    `Happy to forward the tailored CV, cover letter, and outreach scripts if it helps you pressure test the fit before we speak.`,
  () =>
    `Let me know if a quick video call would be useful—I can walk through the playbooks and introduce a few references.`,
  (ctx) =>
    `I'm flexible on timing and can accommodate whatever window works best for you this week.`,
];

const EMAIL_REASSURE_VARIANTS = [
  () =>
    `Everything is ready to go on my side—materials, references, and follow-up steps—so the conversation can stay focused on what will move the needle.`,
  () =>
    `I can also share a draft recruiter pack if you'd like to see how I'm positioning myself in-market.`,
  (ctx) =>
    ctx.hasCv
      ? `I've attached my MENA Careers-generated materials so you can skim the depth before we chat.`
      : "",
];

let avatarFallbackBound = false;

(function ($) {
  "use strict";

  console.log("RecruiterPostArticle v3.0 loaded - Application Pack Flow");

  // Ensure dependencies are loaded
  if (typeof sffc_recruiter_post === "undefined") {
    console.error(
      "Recruiter Post Article: sffc_recruiter_post is not defined."
    );
    return;
  }

  class ApplicationPackController {
    constructor(container) {
      this.$container = $(container);
      this.postId = sffc_recruiter_post.post_id;
      this.jdText = sffc_recruiter_post.jd_text || "";
      this.recruiterEmail = sffc_recruiter_post.recruiter_email || "";
      this.recruiterName =
        sffc_recruiter_post.recruiter_name || "Hiring Manager";
      this.jobTitle = sffc_recruiter_post.job_title || "";
      this.companyName = sffc_recruiter_post.company_name || "";
      this.membershipUrl =
        sffc_recruiter_post.membership_url ||
        "https://joinsenna.com/memberships/";

      // Premium access
      this.isPremium = sffc_recruiter_post.is_premium || false;
      this.isLoggedIn = sffc_recruiter_post.is_logged_in || false;

      // State
      this.cvText = "";
      this.cvFile = null;
      this.selectedProducts = new Set();
      this.selectedUpsells = new Set();
      this.currentTab = "job-details";
      this.preloaderStepTimer = null;
      this.preloaderProgressInterval = null;
      this.preloaderPercentValue = 0;
      this.bodyLockCount = 0;
      this.highlightTokensCache = [];
      this.readyModalTimer = null;
      this.$body = $("body");
      this.mobilePanelBreakpoint = 900;
      this.mobileResizeTimer = null;
      this.pendingRecruiterData = null;
      this.pendingIntroduceData = null;
      this.introduceReplyRate = "72%";
      this.introduceConfirmationTimer = null;

      // Smart Message (AI-generated)
      this.smartMessage = null;
      this.smartMessageLoaded = false;

      // Step-by-step analysis data
      this.stepAnalysisData = null;
      this.stepCoverLetterText = "";
      this.stepLinkedinMessage = "";
      this.coverLetterData = null;
      this.emailPreviewData = null;

      // Debug
      console.log("ApplicationPackController initialized");
      console.log("Post ID:", this.postId);
      console.log("Job Title:", this.jobTitle);
      console.log("Premium:", this.isPremium);
      console.log("Logged In:", this.isLoggedIn);

      this.init();
    }

    init() {
      this.cacheElements();
      this.defaultJobDescription =
        this.$jdTextarea && this.$jdTextarea.length
          ? this.$jdTextarea.val()
          : this.jdText || "";
      this.baseJobTitle = this.$headerTitle.length
        ? this.$headerTitle.text().trim()
        : this.jobTitle || "";
      this.baseJobSubtitle = this.$headerSubtitle.length
        ? this.$headerSubtitle.text().trim()
        : this.companyName || "";
      this.baseJobLocation = this.$jobLocationValue.length
        ? this.$jobLocationValue.text().trim()
        : "";
      this.baseLinkedinMeta = this.$linkedinMeta.length
        ? this.$linkedinMeta.text().trim()
        : "";
      this.baseExpressRecruiterName = this.$expressRecruiterName.length
        ? this.$expressRecruiterName.text().trim()
        : this.recruiterName || "Recruiter";
      this.baseExpressRecruiterMeta = this.$expressRecruiterMeta.length
        ? this.$expressRecruiterMeta.text().trim()
        : this.companyName || "";
      this.baseInlineRecruiterName = this.$inlineRecruiterName.length
        ? this.$inlineRecruiterName.text().trim()
        : this.baseExpressRecruiterName;
      this.baseInlineRecruiterMeta = this.$inlineRecruiterMeta.length
        ? this.$inlineRecruiterMeta.text().trim()
        : this.baseExpressRecruiterMeta;
      this.baseMessageRecruiterLabel = this.$messageRecruiterLabel.length
        ? this.$messageRecruiterLabel.text().trim()
        : "Message Recruiter";
      this.baseCrmRecruiterName = this.$crmModalRecruiterName.length
        ? this.$crmModalRecruiterName.text().trim()
        : this.recruiterName || "this recruiter";
      this.baseIntroduceRecruiterName = this.$introduceRecruiterName.length
        ? this.$introduceRecruiterName.text().trim()
        : this.baseExpressRecruiterName;
      this.baseIntroduceRoleTitle = this.$introduceRoleTitle.length
        ? this.$introduceRoleTitle.text().trim()
        : this.jobTitle || "this role";
      this.baseIntroduceRoleCompany = this.$introduceRoleCompany.length
        ? this.$introduceRoleCompany.text().trim()
        : this.companyName || "";
      this.bindTabEvents();
      this.bindCvEvents();
      this.bindProductEvents();
      this.bindExpressInterestEvents();
      this.bindNavigationEvents();
      this.bindMobileLayoutEvents();
      this.bindAddToListEvents();
      this.bindPackModalEvents();
      this.bindCrmModalEvents();
      this.bindIntroduceModalEvents();
      this.bindIntroduceGateModalEvents();
      this.bindIntroduceConfirmationEvents();
      this.bindPipelineModalEvents();
      this.bindExpertModalEvents();
      this.bindDetailsModalEvents();
      this.bindReadyModalEvents();
      this.bindEmailPreviewEvents();
      this.bindAvatarFallbacks();
      this.bindSimilarPostsEvents();
      this.bindRecruiterOutreachEvents();
      this.bindPipelineEvents();
      this.bindStepFlowEvents();
      this.bindMaterialsEvents();
      this.populatePreviews();
      this.applyPremiumLocks();
    }

    cacheElements() {
      // Tabs
      this.$tabBtns = this.$container.find(".inst-view-toggle-btn");
      this.$tabViews = this.$container.find(".inst-tab-view");
      this.$tabWrapper = this.$container.find(".inst-tab-views");

      // Step flow
      this.$stepCards = this.$container.find(".inst-step-card");
      this.$stepTwoCard = this.$container.find(
        '.inst-step-card[data-step="2"]'
      );
      this.$jdTextarea = this.$container.find("#instStepJobDescription");
      this.$analysisBtn = this.$container.find("#instRunAnalysisBtn");
      this.$stepStatus = this.$container.find("#instStepStatus");
      this.$stepStatusText = this.$container.find("#instStepStatusText");
      this.$stepDownloadBtn = this.$container.find("#instDownloadMaterialsBtn");
      this.$applyWithoutBtn = this.$container.find("#instApplyWithoutBtn");
      this.$kitPreview = this.$container.find("#instKitPreview");
      this.$kitPreviewDownload = this.$container.find(
        "#instKitPreviewDownload"
      );
      this.$coverLetterSlot = this.$container.find("#instStepCoverLetter");
      this.$interviewSlot = this.$container.find("#instStepInterviewQuestions");
      this.$linkedinSlot = this.$container.find("#instStepLinkedinMessage");
      this.$linkedinMeta = this.$container.find("#instStepLinkedinMeta");
      this.$keywordsSlot = this.$container.find("#instStepKeywordsList");
      this.$linkedinLayout = this.$container.find("#instLinkedinLayout");
      this.$leftLinkedinColumn = this.$container.find(
        "#instRecruiterListColumn"
      );
      this.$rightLinkedinColumn = this.$container.find("#instRoleDetailColumn");
      this.$mobilePanelToggle = this.$container.find("#instMobilePanelToggle");
      this.$mobilePanelButtons = this.$mobilePanelToggle.find("[data-panel]");
      this.$introducePreviewMessage = this.$container.find(
        "#instIntroducePreviewMessage"
      );
      this.$introducePreviewReasons = this.$container.find(
        "#instIntroducePreviewReasons"
      );
      this.$introduceReplyStat = this.$container.find(
        "#instIntroduceReplyStat"
      );
      this.$mobileRoleTitle = this.$container.find("#instMobileRoleTitle");
      this.$mobileRoleSubtitle = this.$container.find(
        "#instMobileRoleSubtitle"
      );
      this.$summaryRecommendation = this.$container.find(
        "#instStepSummaryRecommendation"
      );
      this.$summaryVerdict = this.$container.find("#instStepSummaryVerdict");
      this.$summaryInsight = this.$container.find("#instStepSummaryInsight");
      this.$summaryScore = this.$container.find("#instStepSummaryScore");
      this.$scoreCards = this.$container.find(".inst-score-card");
      this.$improvementList = this.$container.find("#instStepImprovementList");
      this.$strengthList = this.$container.find("#instStepStrengthList");
      this.$coverPanelBody = this.$container.find("#instStepCoverPanel");
      this.$interviewPanelBody = this.$container.find(
        "#instStepInterviewPanel"
      );
      this.$keywordsPanelBody = this.$container.find("#instStepKeywordsPanel");
      this.$linkedinPanelBody = this.$container.find("#instStepLinkedinPanel");
      this.$applyModal = this.$container.find("#instApplyModal");
      this.$applyModalOverlay = this.$applyModal.find(
        ".inst-apply-modal-overlay"
      );
      this.$applyModalClose = this.$applyModal.find("#instApplyModalClose");
      this.$applyUpgradeBtn = this.$applyModal.find(
        '[data-apply-action="upgrade"]'
      );
      this.$applyContinueBtn = this.$applyModal.find(
        '[data-apply-action="continue"]'
      );
      this.$materialCheckboxes = this.$container.find(
        ".inst-material-checkbox"
      );
      this.$getPackBtn = this.$container.find("#instGetPackBtn");

      // CV Elements
      this.$cvUploadZone = this.$container.find("#instCvUploadZone");
      this.$cvFileInput = this.$container.find("#instCvFileInput");
      this.$cvPasteInput = this.$container.find("#instCvPasteInput");
      this.$cvFilePreview = this.$container.find("#instCvFilePreview");
      this.$cvFilename = this.$container.find("#instCvFilename");
      this.$cvRemove = this.$container.find("#instCvRemove");
      this.$cvModeBtns = this.$container.find(".inst-cv-mode-btn");
      this.$cvPanels = this.$container.find(".inst-cv-panel");

      // Products
      this.$productCards = this.$container.find(".inst-product-card");
      this.$addToPackBtns = this.$container.find(".inst-add-to-pack-btn");
      this.$packSummary = this.$container.find("#instPackSummary");
      this.$packItems = this.$container.find("#instPackItems");
      this.$packCount = this.$container.find("#instPackCount");
      this.$generatePackBtn = this.$container.find("#instGeneratePackBtn");

      // Upsell
      this.$upsellCards = this.$container.find(".inst-upsell-card");

      // Express Interest
      this.$firstName = this.$container.find("#instFirstName");
      this.$lastName = this.$container.find("#instLastName");
      this.$email = this.$container.find("#instEmail");
      this.$messageRecruiterBtn = this.$container.find(
        "#instMessageRecruiterBtn"
      );
      this.$trackApplication = this.$container.find("#instTrackApplication");
      this.$messageRecruiterLabel = this.$container.find(
        "#instMessageRecruiterLabel"
      );
      this.$expressRecruiterCard = this.$container.find(
        ".inst-express-recruiter"
      );
      this.$expressRecruiterName = this.$container.find(
        "#instExpressRecruiterName"
      );
      this.$expressRecruiterMeta = this.$container.find(
        "#instExpressRecruiterMeta"
      );
      this.$expressRecruiterAvatar = this.$container.find(
        "#instExpressRecruiterAvatar"
      );
      this.$selectedRecruiterBrief = this.$container.find(
        "#instSelectedRecruiterBrief"
      );
      this.$inlineRecruiterName = this.$container.find(
        "#instInlineRecruiterName"
      );
      this.$inlineRecruiterMeta = this.$container.find(
        "#instInlineRecruiterMeta"
      );
      this.$inlineRecruiterAvatar = this.$container.find(
        "#instInlineRecruiterAvatar"
      );
      this.$introduceConfirmation = this.$container.find(
        "#instIntroduceConfirmation"
      );
      this.$introduceConfirmationRecruiter = this.$container.find(
        "#instIntroduceConfirmationRecruiter"
      );
      this.$introduceDismissBtn = this.$container.find("#instIntroduceDismiss");

      // Preloader
      this.$preloader = this.$container.find("#inst-premium-preloader");
      this.$preloaderPercent = this.$preloader.find("#preloader-percentage");
      this.$preloaderSteps = this.$preloader.find(".inst-preloader-step");
      this.$preloaderProgress = this.$preloader.find("#loader-progress");

      // Navigation
      this.$tailorCvBtn = this.$container.find(".inst-tailor-cv-btn");

      // Likelihood Modal
      this.$likelihoodModal = this.$container.find("#instLikelihoodModal");
      this.$likelihoodClose = this.$container.find("#instLikelihoodClose");
      this.$likelihoodOverlay = this.$container.find(
        ".inst-likelihood-overlay"
      );
      this.$likelihoodSingleBtn = this.$container.find(
        "#instLikelihoodSingleBtn"
      );
      this.$likelihoodMultiBtn = this.$container.find(
        "#instLikelihoodMultiBtn"
      );

      // Product Action Buttons
      this.$productActionBtns = this.$container.find(
        ".inst-product-action-btn"
      );

      // Add to List Buttons
      this.$addToListBtns = this.$container.find(".inst-add-to-list-btn");

      // Recruiter Outreach Grid
      this.$outreachSection = this.$container.find(
        "#instRecruiterOutreachSection"
      );
      this.$outreachCheckboxes = this.$container.find(
        ".inst-outreach-checkbox"
      );
      this.$outreachCards = this.$container.find(".inst-outreach-card");
      this.$bulkReachOutBtn = this.$container.find("#instBulkReachOutBtn");
      this.$bulkAddBtn = this.$container.find("#instBulkAddBtn");
      this.$outreachCount = this.$container.find("#instOutreachCount");
      this.$floatingActions = this.$container.find(
        "#instOutreachFloatingActions"
      );
      this.$floatingMessageBtn = this.$container.find(
        "#instFloatingMessageBtn"
      );
      this.$floatingIntroduceBtn = this.$container.find(
        "#instFloatingIntroduceBtn"
      );

      // Floating Sidebar
      this.$packSidebar = this.$container.find("#instPackSidebar");

      // Introduce CTA
      this.$addPipelineBtn = this.$container.find("#instAddPipelineBtn");
      this.$inlineIntroduceBtn = this.$container.find(
        "#instExpressIntroduceBtn"
      );

      // Pack Modal (Membership Upsell)
      this.$packModal = this.$container.find("#instPackModal");
      this.$packModalClose = this.$container.find("#instPackModalClose");
      this.$packModalOverlay = this.$container.find(".inst-pack-modal-overlay");
      this.$packModalItems = this.$container.find("#instPackModalItems");
      this.$unlockPackBtn = this.$container.find("#instUnlockPackBtn");

      // Header + meta elements for recruiter previews
      this.$headerTitle = this.$container.find("#instHeaderTitle");
      this.$headerSubtitle = this.$container.find("#instHeaderSubtitle");
      this.$jobLocationValue = this.$container.find("#instJobMetaLocation");

      // CRM Modal (Add to List Explainer)
      this.$crmModal = this.$container.find("#instCrmModal");
      this.$crmModalClose = this.$container.find("#instCrmModalClose");
      this.$crmModalOverlay = this.$crmModal.length
        ? this.$crmModal.find(".inst-crm-modal-overlay")
        : $();
      this.$crmModalRecruiterName = this.$container.find(
        "#crmModalRecruiterName"
      );
      this.$saveRecruiterBtn = this.$container.find("#instSaveRecruiterBtn");

      // Pipeline Modal
      this.$pipelineModal = this.$container.find("#instPipelineModal");
      this.$pipelineModalClose = this.$container.find("#instPipelineClose");
      this.$pipelineModalOverlay = this.$pipelineModal.find(
        ".inst-crm-modal-overlay"
      );
      this.$pipelineJoinBtn = this.$container.find("#instPipelineJoinBtn");

      // Introduce Modal
      this.$introduceModal = this.$container.find("#instIntroduceModal");
      this.$introduceModalClose = this.$container.find("#instIntroduceClose");
      this.$introduceModalOverlay = this.$introduceModal.length
        ? this.$introduceModal.find(".inst-crm-modal-overlay")
        : $();
      this.$introduceJoinBtn = this.$container.find("#instIntroduceJoinBtn");
      this.$introduceRecruiterName = this.$container.find(
        "#instIntroduceRecruiterName"
      );
      this.$introduceRoleTitle = this.$container.find(
        "#instIntroduceRoleTitle"
      );
      this.$introduceRoleCompany = this.$container.find(
        "#instIntroduceRoleCompany"
      );
      this.$introduceButtons = this.$container.find("[data-introduce-trigger]");
      this.$introduceGateModal = this.$container.find(
        "#instIntroduceGateModal"
      );
      this.$introduceGateClose = this.$container.find(
        "#instIntroduceGateClose"
      );
      this.$introduceGateOverlay = this.$introduceGateModal.length
        ? this.$introduceGateModal.find(".inst-crm-modal-overlay")
        : $();
      this.$introduceGateJoinBtn = this.$container.find(
        "#instIntroduceGateJoinBtn"
      );

      this.$speakExpertBtn = this.$container.find("#instSpeakExpertBtn");
      this.$expertModal = this.$container.find("#instExpertModal");
      this.$expertOverlay = this.$expertModal.find(".inst-expert-overlay");
      this.$expertClose = this.$container.find("#instExpertClose");
      this.$expertJoinBtn = this.$container.find("#instExpertJoinBtn");

      this.$detailsModal = this.$container.find("#instDetailsMissingModal");
      this.$detailsOverlay = this.$detailsModal.find(".inst-details-overlay");
      this.$detailsClose = this.$container.find("#instDetailsClose");
      this.$detailsList = this.$container.find("#instMissingFieldsList");
      this.$detailsUpdateBtn = this.$container.find("#instDetailsUpdateBtn");
      this.$readyModal = this.$container.find("#instReadyModal");
      this.$readyOverlay = this.$readyModal.find(".inst-ready-overlay");
      this.$readyClose = this.$container.find("#instReadyClose");
      this.$readyJoinBtn = this.$container.find("#instReadyJoinBtn");
      this.$emailPreviewModal = this.$container.find("#instEmailPreviewModal");
      this.$emailPreviewOverlay = this.$emailPreviewModal.length
        ? this.$emailPreviewModal.find(".inst-email-preview-overlay")
        : $();
      this.$emailPreviewClose = this.$container.find("#instEmailPreviewClose");
      this.$emailPreviewSubject = this.$container.find(
        "#instEmailPreviewSubject"
      );
      this.$emailPreviewBody = this.$container.find("#instEmailPreviewBody");
      this.$emailPreviewCopySubject = this.$container.find(
        '[data-copy-type="subject"]'
      );
      this.$emailPreviewCopyBody = this.$container.find(
        "#instEmailPreviewCopyBody"
      );
      this.$emailPreviewContinue = this.$container.find(
        "#instEmailPreviewContinue"
      );
    }

    getCandidateFirstName() {
      const firstName = this.$firstName && this.$firstName.length
        ? this.$firstName.val().trim()
        : "";

      return firstName || "Candidate";
    }

    // ========================================
    // TAB NAVIGATION
    // ========================================

    bindTabEvents() {
      const self = this;

      this.$tabBtns.on("click", function () {
        const view = $(this).data("view");
        self.switchTab(view);
      });
    }

    switchTab(view) {
      const viewMap = {
        "job-details": "#inst-job-details-view",
        "application-pack": "#inst-application-pack-view",
        "express-interest": "#inst-express-interest-view",
      };
      const selector = viewMap[view];
      if (!selector) {
        return;
      }

      this.scrollToSection(selector);
      this.currentTab = view;
    }

    scrollToSection(selector) {
      const $target = this.$container.find(selector);
      if ($target.length) {
        $("html, body").animate(
          {
            scrollTop: $target.offset().top - 40,
          },
          400
        );
      }
    }

    // ========================================
    // NAVIGATION BUTTONS
    // ========================================

    bindNavigationEvents() {
      const self = this;

      // Tailor CV button navigates to Application Pack
      this.$tailorCvBtn.on("click", function () {
        self.switchTab("application-pack");
      });

      // Any element with data-navigate attribute
      this.$container.on("click", "[data-navigate]", function () {
        const target = $(this).data("navigate");
        self.switchTab(target);
      });
    }

    scrollToOutreachSection() {
      const section = document.getElementById("instRecruiterOutreachSection");
      if (section) {
        section.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    }

    bindMobileLayoutEvents() {
      if (!this.$linkedinLayout.length) {
        return;
      }

      this.handleResponsiveLayout();

      if (this.$mobilePanelButtons && this.$mobilePanelButtons.length) {
        this.$mobilePanelButtons.on("click", (event) => {
          const panel = $(event.currentTarget).data("panel");
          this.activateMobilePanel(panel);
        });
      }

      $(window).on("resize.instMobilePanels", () => {
        clearTimeout(this.mobileResizeTimer);
        this.mobileResizeTimer = setTimeout(() => {
          this.handleResponsiveLayout();
        }, 150);
      });
    }

    handleResponsiveLayout() {
      if (!this.$linkedinLayout.length) {
        return;
      }

      const isMobile = window.innerWidth <= this.mobilePanelBreakpoint;
      this.$linkedinLayout.toggleClass("is-mobile-stack", isMobile);

      if (this.$mobilePanelToggle && this.$mobilePanelToggle.length) {
        this.$mobilePanelToggle.toggleClass("is-visible", isMobile);
      }

      if (!isMobile) {
        if (this.$mobilePanelButtons && this.$mobilePanelButtons.length) {
          this.$mobilePanelButtons
            .removeClass("is-active")
            .attr("aria-pressed", false);
        }
        if (this.$leftLinkedinColumn && this.$leftLinkedinColumn.length) {
          this.$leftLinkedinColumn.removeClass("is-active");
        }
        if (this.$rightLinkedinColumn && this.$rightLinkedinColumn.length) {
          this.$rightLinkedinColumn.removeClass("is-active");
        }
        return;
      }

      if (this.$linkedinLayout.find(".inst-linkedin-column.is-active").length) {
        const activePanel =
          this.$leftLinkedinColumn &&
          this.$leftLinkedinColumn.hasClass("is-active")
            ? "recruiters"
            : "role";
        this.setMobilePanelState(activePanel);
        return;
      }

      this.activateMobilePanel("role", false);
    }

    activateMobilePanel(panel, shouldScroll = true) {
      if (
        !this.$linkedinLayout.length ||
        !this.$linkedinLayout.hasClass("is-mobile-stack")
      ) {
        return;
      }

      const targetColumn =
        panel === "recruiters"
          ? this.$leftLinkedinColumn
          : this.$rightLinkedinColumn;

      if (!targetColumn || !targetColumn.length) {
        return;
      }

      if (this.$leftLinkedinColumn && this.$leftLinkedinColumn.length) {
        this.$leftLinkedinColumn.removeClass("is-active");
      }
      if (this.$rightLinkedinColumn && this.$rightLinkedinColumn.length) {
        this.$rightLinkedinColumn.removeClass("is-active");
      }

      targetColumn.addClass("is-active");
      this.setMobilePanelState(panel);

      if (shouldScroll && targetColumn[0]) {
        targetColumn[0].scrollIntoView({ behavior: "smooth", block: "start" });
      }
    }

    setMobilePanelState(panel) {
      if (!this.$mobilePanelButtons || !this.$mobilePanelButtons.length) {
        return;
      }

      this.$mobilePanelButtons.each(function () {
        const $btn = $(this);
        const isActive = $btn.data("panel") === panel;
        $btn.toggleClass("is-active", isActive).attr("aria-pressed", isActive);
      });
    }

    bindRecruiterOutreachEvents() {
      const self = this;
      if (!this.$outreachCheckboxes.length) {
        return;
      }

      this.$outreachCheckboxes.on("change", function () {
        const selected = self.getSelectedOutreachRecruiters();
        if (selected.length > 6) {
          this.checked = false;
          self.showToast("You can select up to 6 recruiters.", "error");
          return;
        }
        self.updateOutreachSelection();
        self.previewRecruiterCard($(this).closest(".inst-outreach-card"));
      });

      this.$container.on("click", ".inst-outreach-card-inner", function () {
        const $card = $(this).closest(".inst-outreach-card");
        self.previewRecruiterCard($card);
      });

      if (this.$bulkAddBtn.length) {
        this.$bulkAddBtn.on("click", function () {
          self.handleBulkAdd();
        });
      }

      if (this.$bulkReachOutBtn.length) {
        this.$bulkReachOutBtn.on("click", function () {
          self.handleBulkReachOut();
        });
      }

      if (this.$floatingMessageBtn.length) {
        this.$floatingMessageBtn.on("click", function () {
          self.handleFloatingMessage();
        });
      }

      if (this.$floatingIntroduceBtn.length) {
        this.$floatingIntroduceBtn.on("click", function () {
          self.handleFloatingIntroduce();
        });
      }

      this.updateOutreachSelection();
      if (this.$outreachCards && this.$outreachCards.length) {
        this.previewRecruiterCard(this.$outreachCards.first());
      }
    }

    previewRecruiterCard($card) {
      if (!$card || !$card.length) {
        return;
      }

      const self = this;

      if (this.$outreachCards && this.$outreachCards.length) {
        this.$outreachCards.removeClass("is-preview");
      }
      $card.addClass("is-preview");

      const data = $card.data() || {};
      const recruiterName = (
        data.recruiterName ||
        this.recruiterName ||
        this.baseExpressRecruiterName
      ).trim();
      const recruiterCompany = (
        data.recruiterCompany ||
        data.roleCompany ||
        this.companyName ||
        ""
      ).trim();
      const roleTitle = (data.roleTitle || this.jobTitle || "").trim();
      const location = (data.roleLocation || this.baseJobLocation || "").trim();
      const description =
        data.roleDescription || this.defaultJobDescription || this.jdText;
      const recruiterPhoto = data.recruiterPhoto || "";
      const recruiterInitial = (
        data.recruiterInitial || (recruiterName ? recruiterName.charAt(0) : "R")
      ).toUpperCase();
      const safeRecruiterName =
        recruiterName || this.baseIntroduceRecruiterName;
      const safeCompany = recruiterCompany || this.baseIntroduceRoleCompany;
      const safeRoleTitle = roleTitle || this.baseIntroduceRoleTitle;

      if (this.$addPipelineBtn && this.$addPipelineBtn.length) {
        this.$addPipelineBtn
          .data("recruiterName", safeRecruiterName)
          .data("recruiterCompany", safeCompany)
          .data("roleTitle", safeRoleTitle)
          .attr("data-recruiter-name", safeRecruiterName)
          .attr("data-recruiter-company", safeCompany)
          .attr("data-role-title", safeRoleTitle);
      }

      if (this.$jdTextarea && this.$jdTextarea.length && description) {
        this.$jdTextarea.val(description);
      }

      if (this.$headerTitle && this.$headerTitle.length) {
        this.$headerTitle.text(roleTitle || this.baseJobTitle);
      }

      if (this.$headerSubtitle && this.$headerSubtitle.length) {
        const subtitleParts = [];
        if (recruiterCompany) {
          subtitleParts.push(recruiterCompany);
        } else if (this.baseJobSubtitle) {
          subtitleParts.push(this.baseJobSubtitle.split(" • ")[0]);
        }
        if (location) {
          subtitleParts.push(location);
        }
        this.$headerSubtitle.text(
          subtitleParts.length
            ? subtitleParts.join(" • ")
            : this.baseJobSubtitle
        );
        if (this.$mobileRoleSubtitle && this.$mobileRoleSubtitle.length) {
          this.$mobileRoleSubtitle.text(
            subtitleParts.length
              ? subtitleParts.join(" • ")
              : this.baseJobSubtitle
          );
        }
      }
      if (this.$mobileRoleTitle && this.$mobileRoleTitle.length) {
        this.$mobileRoleTitle.text(roleTitle || this.baseJobTitle);
      }

      if (this.$jobLocationValue && this.$jobLocationValue.length) {
        this.$jobLocationValue.text(location || this.baseJobLocation);
      }

      if (this.$selectedRecruiterBrief && this.$selectedRecruiterBrief.length) {
        let brief = roleTitle
          ? `Currently viewing ${roleTitle}`
          : "Currently viewing this job description";
        if (recruiterCompany) {
          brief += ` @ ${recruiterCompany}`;
        }
        if (location) {
          brief += ` (${location})`;
        }
        this.$selectedRecruiterBrief.text(brief);
      }

      if (this.$inlineRecruiterName && this.$inlineRecruiterName.length) {
        this.$inlineRecruiterName.text(
          recruiterName ||
            this.baseInlineRecruiterName ||
            this.baseExpressRecruiterName
        );
      }
      if (this.$inlineRecruiterMeta && this.$inlineRecruiterMeta.length) {
        const inlineParts = [];
        if (roleTitle) {
          inlineParts.push(roleTitle);
        }
        if (recruiterCompany) {
          inlineParts.push(`at ${recruiterCompany}`);
        }
        const inlineText = inlineParts.length
          ? inlineParts.join(" ")
          : this.baseInlineRecruiterMeta || this.baseExpressRecruiterMeta;
        this.$inlineRecruiterMeta.text(inlineText);
      }
      if (this.$inlineRecruiterAvatar && this.$inlineRecruiterAvatar.length) {
        this.$inlineRecruiterAvatar.toggleClass(
          "inst-recruiter-avatar--has-image",
          !!recruiterPhoto
        );
        this.$inlineRecruiterAvatar.empty();
        if (recruiterPhoto) {
          const img = document.createElement("img");
          img.src = recruiterPhoto;
          img.alt = recruiterName;
          this.$inlineRecruiterAvatar.append(img);
        } else {
          this.$inlineRecruiterAvatar.text(recruiterInitial);
        }
      }

      if (this.$expressRecruiterName && this.$expressRecruiterName.length) {
        this.$expressRecruiterName.text(
          recruiterName || this.baseExpressRecruiterName
        );
      }
      if (this.$expressRecruiterMeta && this.$expressRecruiterMeta.length) {
        const metaParts = [];
        if (roleTitle) {
          metaParts.push(roleTitle);
        }
        const metaCompany =
          recruiterCompany ||
          this.baseExpressRecruiterMeta.replace(/^.*at\s+/i, "").trim();
        if (metaCompany) {
          metaParts.push(`at ${metaCompany}`);
        }
        const metaText = metaParts.length
          ? metaParts.join(" ")
          : this.baseExpressRecruiterMeta;
        this.$expressRecruiterMeta.text(metaText);
      }

      if (this.$expressRecruiterAvatar && this.$expressRecruiterAvatar.length) {
        this.$expressRecruiterAvatar.toggleClass(
          "inst-recruiter-avatar--has-image",
          !!recruiterPhoto
        );
        this.$expressRecruiterAvatar.empty();
        if (recruiterPhoto) {
          const img = document.createElement("img");
          img.src = recruiterPhoto;
          img.alt = recruiterName;
          this.$expressRecruiterAvatar.append(img);
        } else {
          this.$expressRecruiterAvatar.text(recruiterInitial);
        }
      }

      if (this.$linkedinMeta && this.$linkedinMeta.length) {
        this.$linkedinMeta.text(
          `Draft tailored to ${recruiterName || this.baseExpressRecruiterName}`
        );
      }

      if (this.$messageRecruiterLabel && this.$messageRecruiterLabel.length) {
        this.$messageRecruiterLabel.text(
          recruiterName
            ? `Message ${recruiterName}`
            : this.baseMessageRecruiterLabel
        );
      }

      if (this.$crmModalRecruiterName && this.$crmModalRecruiterName.length) {
        this.$crmModalRecruiterName.text(
          safeRecruiterName || this.baseCrmRecruiterName
        );
      }

      if (this.$introduceRecruiterName && this.$introduceRecruiterName.length) {
        this.$introduceRecruiterName.text(safeRecruiterName);
      }
      if (this.$introduceRoleTitle && this.$introduceRoleTitle.length) {
        this.$introduceRoleTitle.text(safeRoleTitle);
      }
      if (this.$introduceRoleCompany && this.$introduceRoleCompany.length) {
        this.$introduceRoleCompany.text(safeCompany);
      }

      if (this.$introduceButtons && this.$introduceButtons.length) {
        this.$introduceButtons.each(function () {
          const $btn = $(this);
          $btn
            .data("recruiterName", safeRecruiterName)
            .data("recruiterCompany", safeCompany)
            .data("roleTitle", safeRoleTitle)
            .attr("data-recruiter-name", safeRecruiterName)
            .attr("data-recruiter-company", safeCompany)
            .attr("data-role-title", safeRoleTitle);
        });
      }

      if (
        this.$linkedinLayout &&
        this.$linkedinLayout.hasClass("is-mobile-stack")
      ) {
        this.activateMobilePanel("role");
      }
    }

    bindPipelineEvents() {
      const self = this;
      this.$introduceButtons = this.$container.find("[data-introduce-trigger]");
      if (!this.$introduceButtons.length) {
        return;
      }

      this.$introduceButtons.on("click", function (e) {
        e.preventDefault();
        const $btn = $(this);
        const context = {
          recruiter_name:
            $btn.data("recruiterName") || self.baseIntroduceRecruiterName,
          recruiter_company:
            $btn.data("recruiterCompany") || self.baseIntroduceRoleCompany,
          role_title: $btn.data("roleTitle") || self.baseIntroduceRoleTitle,
        };
        const triggerType = ($btn.data("introduceTrigger") || "").toString();

        if (self.isPremium && $btn.is("#instAddPipelineBtn")) {
          self.showIntroduceConfirmation(context.recruiter_name);
          self.handlePipelineAdd($btn);
          return;
        }

        if (self.isPremium && triggerType === "inline") {
          self.showIntroduceConfirmation(context.recruiter_name);
          self.switchTab("express-interest");
          self.scrollToExpressHeader();
          return;
        }

        self.showIntroduceModal(context);
      });
    }

    handlePipelineAdd($btn) {
      if (!$btn || !$btn.length) {
        return;
      }

      this.switchTab("express-interest");
      this.scrollToExpressHeader();

      if (!this.isLoggedIn) {
        return;
      }

      if (!sffc_recruiter_post.crm_nonce) {
        this.showToast(
          "CRM connection unavailable. Please try again later.",
          "error"
        );
        return;
      }

      const recruiterId =
        parseInt($btn.data("recruiter-id"), 10) ||
        parseInt(sffc_recruiter_post.recruiter_id || 0, 10);
      if (!recruiterId) {
        this.showToast(
          "Recruiter profile is syncing. Try again soon.",
          "error"
        );
        return;
      }

      const crmPostId =
        parseInt($btn.data("crm-post-id"), 10) ||
        parseInt(sffc_recruiter_post.crm_post_id || 0, 10);
      const roleTitle = $btn.data("role-title") || this.jobTitle;
      const company = $btn.data("company") || this.companyName;

      const $label = $btn.find("span");
      const originalText = $label.text();
      $btn.prop("disabled", true).addClass("is-loading");
      $label.text("Adding...");

      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_crm_add_to_pipeline",
          nonce: sffc_recruiter_post.crm_nonce,
          recruiter_id: recruiterId,
          post_id: crmPostId,
          role_title: roleTitle,
          company: company,
          stage: "interested",
        },
        success: (response) => {
          $btn.removeClass("is-loading");
          if (response.success) {
            this.showToast("Added to pipeline", "success");
            $btn.addClass("is-added").prop("disabled", true);
            $label.text("Added");
          } else {
            $btn.prop("disabled", false);
            $label.text(originalText);
            const message =
              response.data?.message || "Unable to add to pipeline";
            this.showToast(message, "error");
          }
        },
        error: () => {
          $btn.prop("disabled", false).removeClass("is-loading");
          $label.text(originalText);
          this.showToast("Unable to add to pipeline", "error");
        },
      });
    }

    scrollToExpressHeader() {
      const $target = this.$container.find(".inst-express-header").first();
      if (!$target.length) {
        return;
      }
      const offset = Math.max(($target.offset()?.top || 0) - 80, 0);
      $("html, body").animate({ scrollTop: offset }, 500);
    }

    getSelectedOutreachRecruiters() {
      const self = this;
      const selected = [];
      if (!this.$outreachCheckboxes.length) {
        return selected;
      }
      this.$outreachCheckboxes.filter(":checked").each(function () {
        const $input = $(this);
        selected.push({
          recruiter_id: $input.data("recruiter-id"),
          recruiter_name: $input.data("recruiter-name"),
          recruiter_company: $input.data("recruiter-company"),
          recruiter_email: $input.data("recruiter-email"),
          recruiter_role: $input.data("recruiter-role"),
          post_id: self.postId,
          job_title: self.jobTitle,
          company_name: self.companyName,
        });
      });
      return selected;
    }

    updateOutreachSelection() {
      if (!this.$outreachCount.length) {
        return;
      }
      const count = this.getSelectedOutreachRecruiters().length;
      this.$outreachCount.text(count);
      const canAct = count >= 3;
      if (this.$bulkAddBtn.length) {
        this.$bulkAddBtn.prop("disabled", !canAct);
      }
      if (this.$bulkReachOutBtn.length) {
        this.$bulkReachOutBtn.prop("disabled", !canAct);
      }
      this.updateFloatingActions(count);
    }

    updateFloatingActions(selectedCount) {
      if (!this.$floatingActions.length) {
        return;
      }
      const hasSelection = selectedCount > 0;
      this.$floatingActions.toggleClass("is-visible", hasSelection);
      if (this.$floatingMessageBtn.length) {
        this.$floatingMessageBtn.prop("disabled", selectedCount < 3);
      }
      if (this.$floatingIntroduceBtn.length) {
        this.$floatingIntroduceBtn.prop("disabled", !hasSelection);
      }
    }

    handleFloatingMessage() {
      this.handleBulkReachOut();
    }

    handleFloatingIntroduce() {
      const selected = this.getSelectedOutreachRecruiters();
      if (!selected.length) {
        this.showToast("Select recruiters first.", "error");
        return;
      }
      if (!this.isPremium) {
        this.showIntroduceGateModal();
        return;
      }
      const target = selected[0];
      this.showIntroduceConfirmation(target.recruiter_name || null);
      this.switchTab("express-interest");
      this.scrollToExpressHeader();
    }

    handleBulkAdd() {
      if (!this.$bulkAddBtn.length) {
        return;
      }
      if (!this.isLoggedIn) {
        this.showCrmModal();
        return;
      }

      const selected = this.getSelectedOutreachRecruiters();
      if (selected.length < 3) {
        this.showToast("Select at least 3 recruiters.", "error");
        return;
      }

      // Store selected recruiters and load lists
      this.selectedRecruitersForList = selected;
      this.loadOutreachListsForModal();
    }

    handleBulkReachOut() {
      if (!this.$bulkReachOutBtn.length) {
        return;
      }
      const selected = this.getSelectedOutreachRecruiters();

      if (!selected.length) {
        this.showToast("Select recruiters first.", "error");
        return;
      }

      if (!this.isLoggedIn) {
        this.showCrmModal(selected[0]?.recruiter_name || null);
        return;
      }

      if (selected.length < 3) {
        this.showToast("Select at least 3 recruiters.", "error");
        return;
      }

      if (!sffc_recruiter_post.crm_nonce) {
        this.showToast(
          "CRM connection unavailable. Please try again later.",
          "error"
        );
        return;
      }

      this.bulkAddToPipeline(selected);
    }

    bulkAddToPipeline(recruiters) {
      if (!recruiters.length) {
        return;
      }

      const $btn = this.$bulkReachOutBtn;
      if (!$btn.length) {
        return;
      }

      const originalText = $btn.text();
      $btn.prop("disabled", true).addClass("is-loading").text("Adding...");

      let successCount = 0;
      const errors = [];

      const finalize = () => {
        $btn.removeClass("is-loading").text(originalText);
        this.updateOutreachSelection();
        if (successCount) {
          const label = successCount > 1 ? "recruiters" : "recruiter";
          this.showToast(
            `Added ${successCount} ${label} to pipeline`,
            "success"
          );
        }
        if (errors.length) {
          this.showToast(errors[0], "error");
        }
      };

      const processRecruiter = (index) => {
        if (index >= recruiters.length) {
          finalize();
          return;
        }

        this.addRecruiterToPipeline(recruiters[index])
          .done(() => {
            successCount += 1;
          })
          .fail((jqXHR) => {
            const message =
              jqXHR?.responseJSON?.data?.message || "Unable to add to pipeline";
            errors.push(message);
          })
          .always(() => {
            processRecruiter(index + 1);
          });
      };

      processRecruiter(0);
    }

    addRecruiterToPipeline(recruiterData) {
      const recruiterId = parseInt(recruiterData.recruiter_id, 10) || 0;
      const postId =
        parseInt(
          recruiterData.post_id || sffc_recruiter_post.crm_post_id || 0,
          10
        ) || 0;

      const payload = {
        action: "sffc_crm_add_to_pipeline",
        nonce: sffc_recruiter_post.crm_nonce,
        recruiter_id: recruiterId,
        post_id: postId,
        role_title:
          recruiterData.recruiter_role ||
          recruiterData.job_title ||
          this.jobTitle,
        company: recruiterData.recruiter_company || this.companyName,
        stage: "interested",
      };

      return $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: payload,
      });
    }

    sendRecruiterListRequest(action, recruiterData) {
      return $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: action,
          nonce: sffc_recruiter_post.crm_nonce,
          recruiter_id: recruiterData.recruiter_id,
          recruiter_name: recruiterData.recruiter_name,
          recruiter_company: recruiterData.recruiter_company,
          recruiter_email: recruiterData.recruiter_email,
          post_id: recruiterData.post_id,
          job_title: recruiterData.job_title,
          company_name: recruiterData.company_name,
        },
      });
    }

    // ========================================
    // ADD TO LIST (Smart Outreach)
    // ========================================

    bindAddToListEvents() {
      const self = this;

      this.$addToListBtns.on("click", function (e) {
        e.preventDefault();
        const $btn = $(this);
        const behavior = $btn.data("behavior");

        if (behavior === "message") {
          self.switchTab("express-interest");
          self.scrollToExpressHeader();
          if (self.$firstName.length) {
            self.$firstName.trigger("focus");
          }
          return;
        }

        if ($btn.is("[data-scroll-target]")) {
          return;
        }

        const $card = $btn.closest(".inst-recruiter-card");

        // Get recruiter data from card attributes
        const recruiterData = {
          recruiter_id: $card.data("recruiter-id"),
          recruiter_name: $card.data("recruiter-name"),
          recruiter_company: $card.data("recruiter-company"),
          recruiter_email: $card.data("recruiter-email"),
          post_id: self.postId,
          job_title: self.jobTitle,
          company_name: self.companyName,
        };

        self.toggleRecruiterInList($btn, recruiterData);
      });
    }

    toggleRecruiterInList($btn, recruiterData) {
      const self = this;
      const isAdded = $btn.hasClass("is-added");

      // Check if user is logged in - show CRM modal for non-members
      if (!this.isLoggedIn) {
        this.pendingRecruiterData = recruiterData;
        this.showCrmModal(recruiterData.recruiter_name);
        return;
      }

      // Disable button while processing
      $btn.prop("disabled", true);

      const action = isAdded
        ? "sffc_crm_unsave_recruiter_from_post"
        : "sffc_crm_save_recruiter_from_post";

      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: action,
          nonce: sffc_recruiter_post.crm_nonce,
          ...recruiterData,
        },
        success: function (response) {
          $btn.prop("disabled", false);

          if (response.success) {
            if (isAdded) {
              // Remove from list
              $btn.removeClass("is-added");
              $btn.find("span").text("Add to List");
              self.showToast("Removed from outreach list");
            } else {
              // Add to list
              $btn.addClass("is-added");
              $btn.find("span").text("Added");
              self.showToast("Added to outreach list");
            }

            // Update all matching buttons on the page
            self.$container.find(`.inst-add-to-list-btn`).each(function () {
              const $otherBtn = $(this);
              const $otherCard = $otherBtn.closest(".inst-recruiter-card");
              if (
                $otherCard.data("recruiter-id") === recruiterData.recruiter_id
              ) {
                if (isAdded) {
                  $otherBtn.removeClass("is-added");
                  $otherBtn.find("span").text("Add to List");
                } else {
                  $otherBtn.addClass("is-added");
                  $otherBtn.find("span").text("Added");
                }
              }
            });
          } else {
            self.showToast(
              response.data?.message || "Something went wrong",
              "error"
            );
          }
        },
        error: function () {
          $btn.prop("disabled", false);
          self.showToast("Failed to update list", "error");
        },
      });
    }

    showToast(message, type = "success") {
      // Create toast if doesn't exist
      let $toast = $(".inst-toast");
      if ($toast.length === 0) {
        $toast = $('<div class="inst-toast"></div>').appendTo("body");
      }

      // Set message and type
      $toast
        .text(message)
        .removeClass("inst-toast--success inst-toast--error")
        .addClass(`inst-toast--${type}`)
        .addClass("is-visible");

      // Hide after 3 seconds
      clearTimeout(this.toastTimeout);
      this.toastTimeout = setTimeout(function () {
        $toast.removeClass("is-visible");
      }, 3000);
    }

    // ========================================
    // PACK MODAL (Membership Upsell)
    // ========================================

    bindPackModalEvents() {
      const self = this;

      // Close button
      this.$packModalClose.on("click", function () {
        self.hidePackModal();
      });

      // Overlay click to close
      this.$packModalOverlay.on("click", function () {
        self.hidePackModal();
      });

      // Unlock button - redirect to membership
      this.$unlockPackBtn.on("click", function () {
        window.open(self.membershipUrl, "_blank");
      });

      // ESC key to close
      $(document).on("keydown", function (e) {
        if (e.key === "Escape" && self.$packModal.is(":visible")) {
          self.hidePackModal();
        }
      });
    }

    showPackModal() {
      if (!this.$packModal.length) {
        this.showMembershipModal();
        return;
      }

      this.populatePackModalItems();
      this.$packModal.show();
      this.lockBodyScroll();
    }

    hidePackModal() {
      if (!this.$packModal.length) {
        return;
      }
      this.$packModal.hide();
      this.unlockBodyScroll();
    }

    populatePackModalItems() {
      const productIcons = {
        "tailored-cv":
          '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        "cover-letter":
          '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        "interview-questions":
          '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        "ats-optimisation":
          '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
      };

      const productLabels = {
        "tailored-cv": "Tailored CV",
        "cover-letter": "Cover Letter",
        "interview-questions": "Interview Questions",
        "ats-optimisation": "ATS Optimisation",
      };

      let itemsHtml = "";
      this.selectedProducts.forEach((product) => {
        itemsHtml += `
                    <div class="inst-pack-modal-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            ${productIcons[product] || ""}
                        </svg>
                        <span>${productLabels[product] || product}</span>
                    </div>
                `;
      });

      this.$packModalItems.html(itemsHtml);
    }

    // ========================================
    // CRM MODAL (Add to List Explainer)
    // ========================================

    bindCrmModalEvents() {
      const self = this;

      if (this.$crmModalClose.length) {
        this.$crmModalClose.on("click", function () {
          self.hideCrmModal();
        });
      }

      if (this.$crmModalOverlay.length) {
        this.$crmModalOverlay.on("click", function () {
          self.hideCrmModal();
        });
      }

      if (this.$saveRecruiterBtn.length) {
        this.$saveRecruiterBtn.on("click", function () {
          if (self.pendingRecruiterData) {
            localStorage.setItem(
              "senna_pending_recruiter",
              JSON.stringify(self.pendingRecruiterData)
            );
          }
          window.open(self.membershipUrl, "_blank");
        });
      }

      // ESC key to close
      $(document).on("keydown", function (e) {
        if (e.key === "Escape" && self.$crmModal.is(":visible")) {
          self.hideCrmModal();
        }
      });
    }

    showCrmModal(recruiterName) {
      if (!this.$crmModal.length) {
        return;
      }
      const safeName = recruiterName || this.baseCrmRecruiterName;
      if (this.$crmModalRecruiterName.length && safeName) {
        this.$crmModalRecruiterName.text(safeName);
      }

      this.$crmModal.show();
      this.lockBodyScroll();
    }

    hideCrmModal() {
      if (!this.$crmModal.length) {
        return;
      }
      this.$crmModal.hide();
      this.unlockBodyScroll();
    }

    bindIntroduceModalEvents() {
      if (!this.$introduceModal.length) {
        return;
      }
      const self = this;
      if (this.$introduceModalClose.length) {
        this.$introduceModalClose.on("click", function () {
          self.hideIntroduceModal();
        });
      }
      if (this.$introduceModalOverlay.length) {
        this.$introduceModalOverlay.on("click", function () {
          self.hideIntroduceModal();
        });
      }
      if (this.$introduceJoinBtn.length) {
        this.$introduceJoinBtn.on("click", function () {
          if (self.pendingIntroduceData) {
            localStorage.setItem(
              "senna_pending_introduction",
              JSON.stringify(self.pendingIntroduceData)
            );
          }
          window.open(self.membershipUrl, "_blank");
        });
      }
      $(document).on("keydown", function (e) {
        if (e.key === "Escape" && self.$introduceModal.is(":visible")) {
          self.hideIntroduceModal();
        }
      });
    }

    showIntroduceModal(context = {}) {
      if (!this.$introduceModal.length) {
        window.open(this.membershipUrl, "_blank");
        return;
      }
      this.pendingIntroduceData = context;
      const recruiterName =
        context.recruiter_name ||
        context.recruiterName ||
        this.baseIntroduceRecruiterName;
      const recruiterCompany =
        context.recruiter_company ||
        context.recruiterCompany ||
        this.baseIntroduceRoleCompany;
      const roleTitle =
        context.role_title || context.roleTitle || this.baseIntroduceRoleTitle;

      if (this.$introduceRecruiterName.length) {
        this.$introduceRecruiterName.text(
          recruiterName || this.baseIntroduceRecruiterName
        );
      }
      if (this.$introduceRoleTitle.length) {
        this.$introduceRoleTitle.text(roleTitle || this.baseIntroduceRoleTitle);
      }
      if (this.$introduceRoleCompany.length) {
        this.$introduceRoleCompany.text(
          recruiterCompany || this.baseIntroduceRoleCompany
        );
      }

      this.populateIntroducePreview({
        recruiter_name: recruiterName,
        recruiter_company: recruiterCompany,
        role_title: roleTitle,
      });

      this.$introduceModal.show();
      this.lockBodyScroll();
    }

    hideIntroduceModal() {
      if (!this.$introduceModal.length) {
        return;
      }
      this.$introduceModal.hide();
      this.unlockBodyScroll();
    }

    bindIntroduceGateModalEvents() {
      if (!this.$introduceGateModal.length) {
        return;
      }
      const self = this;
      if (this.$introduceGateClose.length) {
        this.$introduceGateClose.on("click", function () {
          self.hideIntroduceGateModal();
        });
      }
      if (this.$introduceGateOverlay.length) {
        this.$introduceGateOverlay.on("click", function () {
          self.hideIntroduceGateModal();
        });
      }
      if (this.$introduceGateJoinBtn.length) {
        this.$introduceGateJoinBtn.on("click", function () {
          window.open("https://joinsenna.com/memberships/", "_blank");
        });
      }
      $(document).on("keydown", (e) => {
        if (e.key === "Escape" && this.$introduceGateModal.is(":visible")) {
          this.hideIntroduceGateModal();
        }
      });
    }

    showIntroduceGateModal() {
      if (!this.$introduceGateModal.length) {
        window.open("https://joinsenna.com/memberships/", "_blank");
        return;
      }
      this.$introduceGateModal.show();
      this.lockBodyScroll();
    }

    hideIntroduceGateModal() {
      if (!this.$introduceGateModal.length) {
        return;
      }
      this.$introduceGateModal.hide();
      this.unlockBodyScroll();
    }

    generateIntroducePreview(context = {}) {
      const recruiterName =
        context.recruiter_name || context.recruiterName || "there";
      const roleTitle =
        context.role_title || context.roleTitle || this.jobTitle || "this role";
      const roleCompany =
        context.recruiter_company ||
        context.recruiterCompany ||
        this.companyName ||
        "";
      const location = this.baseJobLocation || "";
      const skills = (this.highlightTokensCache || []).slice(0, 3);
      const achievements = (
        this.stepAnalysisData?.summary?.highlights || []
      ).slice(0, 2);

      const lines = [];
      lines.push(`Hi ${recruiterName},`);
      lines.push("");
      lines.push(
        `I'm sharing a MENA Careers member for the ${roleTitle}${
          roleCompany ? ` at ${roleCompany}` : ""
        }.`
      );
      lines.push(
        `They've been delivering the same mix of projects you've outlined${
          location ? ` in ${location}` : ""
        }.`
      );
      lines.push("");
      if (skills.length) {
        lines.push(`Key skills: ${skills.join(", ")}.`);
      }
      if (achievements.length) {
        if (skills.length) {
          lines.push("");
        }
        lines.push(`Recent results: ${achievements.join(" | ")}.`);
      }
      if (skills.length || achievements.length) {
        lines.push("");
      }
      lines.push(
        `I'll include their tailored CV and send availability once you give me the nod.`
      );
      lines.push("");
      lines.push("Best,");
      lines.push("MENA Careers");

      const reasons = [];
      if (skills.length) {
        reasons.push(
          `Mentions the same skills you listed (${skills.join(", ")}).`
        );
      } else {
        reasons.push(
          `Calls out why the ${roleTitle} brief fits their recent work.`
        );
      }
      if (achievements.length) {
        reasons.push(
          "Includes two quantified achievements so you can skim quickly."
        );
      }
      reasons.push(
        `Drops the exact keywords from your job brief${
          location ? ` for ${location}` : ""
        }.`
      );

      return {
        message: lines.join("\n"),
        reasons,
        replyStat: this.introduceReplyRate,
      };
    }

    populateIntroducePreview(context = {}) {
      if (!this.$introducePreviewMessage.length) {
        return;
      }
      const preview = this.generateIntroducePreview(context);
      this.$introducePreviewMessage.text(preview.message);
      if (this.$introducePreviewReasons.length) {
        const items = (preview.reasons || [])
          .map((reason) => `<li>${this.escapeHtml(reason)}</li>`)
          .join("");
        this.$introducePreviewReasons.html(items);
      }
      if (this.$introduceReplyStat.length && preview.replyStat) {
        this.$introduceReplyStat.text(preview.replyStat);
      }
    }

    bindIntroduceConfirmationEvents() {
      if (!this.$introduceConfirmation.length) {
        return;
      }
      if (this.$introduceDismissBtn.length) {
        this.$introduceDismissBtn.on("click", () => {
          this.hideIntroduceConfirmation();
        });
      }
    }

    showIntroduceConfirmation(recruiterName) {
      if (!this.$introduceConfirmation.length) {
        return;
      }
      if (this.$introduceConfirmationRecruiter.length) {
        const label =
          recruiterName || this.baseIntroduceRecruiterName || "this recruiter";
        this.$introduceConfirmationRecruiter.text(label);
      }
      this.$introduceConfirmation
        .addClass("is-visible")
        .attr("aria-hidden", "false");
      if (this.introduceConfirmationTimer) {
        clearTimeout(this.introduceConfirmationTimer);
      }
      this.introduceConfirmationTimer = setTimeout(() => {
        this.hideIntroduceConfirmation();
      }, 8000);
    }

    hideIntroduceConfirmation() {
      if (!this.$introduceConfirmation.length) {
        return;
      }
      this.$introduceConfirmation
        .removeClass("is-visible")
        .attr("aria-hidden", "true");
      if (this.introduceConfirmationTimer) {
        clearTimeout(this.introduceConfirmationTimer);
        this.introduceConfirmationTimer = null;
      }
    }

    bindPipelineModalEvents() {
      if (!this.$pipelineModal.length) {
        return;
      }
      const self = this;
      this.$pipelineModalClose.on("click", function () {
        self.hidePipelineModal();
      });
      this.$pipelineModalOverlay.on("click", function () {
        self.hidePipelineModal();
      });
      this.$pipelineJoinBtn.on("click", function () {
        window.open(self.membershipUrl, "_blank");
        self.hidePipelineModal();
      });
      $(document).on("keydown", function (e) {
        if (e.key === "Escape" && self.$pipelineModal.is(":visible")) {
          self.hidePipelineModal();
        }
      });
    }

    showPipelineModal() {
      if (!this.$pipelineModal.length) {
        window.open(this.membershipUrl, "_blank");
        return;
      }
      this.$pipelineModal.show();
      this.lockBodyScroll();
    }

    hidePipelineModal() {
      if (!this.$pipelineModal.length) {
        return;
      }
      this.$pipelineModal.hide();
      this.unlockBodyScroll();
    }

    // ========================================
    // STEP-BY-STEP APPLICATION FLOW
    // ========================================

    bindStepFlowEvents() {
      const self = this;

      if (this.$jdTextarea.length) {
        this.$jdTextarea.on("input", function () {
          self.jdText = $(this).val().trim();
        });
      }

      if (this.$analysisBtn.length) {
        this.$analysisBtn.on("click", function (e) {
          e.preventDefault();
          self.runStepAnalysis();
        });
      }

      this.$container.on("click", "[data-step-target]", function (e) {
        const target = parseInt($(this).data("stepTarget"), 10);
        if (target) {
          e.preventDefault();
          self.scrollToStep(target);
        }
      });

      this.$container.on("click", "[data-scroll-target]", function (e) {
        const selector = $(this).data("scrollTarget");
        if (!selector) {
          return;
        }
        const $target = self.$container.find(selector);
        if ($target.length) {
          e.preventDefault();
          $("html, body").animate(
            {
              scrollTop: $target.offset().top - 40,
            },
            400
          );
        }
      });

      if (this.$kitPreviewDownload.length) {
        this.$kitPreviewDownload.on("click", function (e) {
          e.preventDefault();
          self.scrollToStep(2);
        });
      }

      this.$container.on("click", "[data-copy-source]", function (e) {
        e.preventDefault();
        const source = $(this).data("copySource");
        const gatedSources = ["cover-letter", "linkedin"];
        if (!self.userHasPremiumAccess() && gatedSources.includes(source)) {
          self.showPackModal();
          return;
        }
        if (source === "cover-letter") {
          self.copyTextContent(self.stepCoverLetterText, "Cover letter copied");
        } else if (source === "linkedin") {
          self.copyTextContent(
            self.stepLinkedinMessage,
            "LinkedIn message copied"
          );
        } else if (source === "interview-question") {
          const encoded = $(this).attr("data-copy-text");
          const text = encoded ? decodeURIComponent(encoded) : "";
          self.copyTextContent(text, "Answer copied");
        }
      });

      this.$container.on("click", '[data-download="cover-word"]', function (e) {
        e.preventDefault();
        if (!self.userHasPremiumAccess()) {
          self.showPackModal();
          return;
        }
        self.downloadCoverLetterAsWord();
      });

      if (this.$stepDownloadBtn.length) {
        this.$stepDownloadBtn.on("click", function (e) {
          e.preventDefault();
          if (!self.userHasPremiumAccess()) {
            self.showPackModal();
            return;
          }
          self.exportStepAnalysis();
        });
      }

      if (this.$applyWithoutBtn.length) {
        this.$applyWithoutBtn.on("click", function (e) {
          e.preventDefault();
          if (self.userHasPremiumAccess()) {
            self.scrollToExpressInterest();
          } else {
            self.showApplyModal();
          }
        });
      }

      if (this.$applyModal.length) {
        this.$applyModalClose.on("click", () => this.hideApplyModal());
        this.$applyModalOverlay.on("click", () => this.hideApplyModal());
        this.$applyUpgradeBtn.on("click", () => {
          window.open(this.membershipUrl, "_blank");
        });
        this.$applyContinueBtn.on("click", () => {
          this.hideApplyModal();
          this.scrollToExpressInterest();
        });
      }

      this.setActiveStep(2);
    }

    revealCvStep() {
      if (this.$kitPreview.length) {
        this.$kitPreview.addClass("is-hidden");
      }
      if (this.$stepTwoCard.length) {
        this.$stepTwoCard.removeClass("is-hidden").attr("aria-hidden", "false");
        if (this.$cvPasteInput && this.$cvPasteInput.length) {
          this.$cvPasteInput.trigger("focus");
        }
      }
    }

    bindMaterialsEvents() {
      const self = this;
      if (this.$materialCheckboxes.length) {
        this.$materialCheckboxes.each(function () {
          if ($(this).is(":checked")) {
            self.selectedMaterials.add($(this).val());
          }
        });

        this.$materialCheckboxes.on("change", function () {
          const value = $(this).val();
          if ($(this).is(":checked")) {
            self.selectedMaterials.add(value);
          } else {
            self.selectedMaterials.delete(value);
          }
        });
      }

      if (this.$getPackBtn.length) {
        this.$getPackBtn.on("click", function (e) {
          e.preventDefault();
          if (!self.userHasPremiumAccess()) {
            window.open(self.membershipUrl, "_blank");
            return;
          }
          self.showPackModal();
        });
      }
    }

    setActiveStep(stepNumber) {
      if (!this.$stepCards || !this.$stepCards.length) {
        return;
      }
      this.$stepCards.each(function () {
        const $card = $(this);
        const value = parseInt($card.data("step"), 10);
        $card.removeClass("is-current is-complete");
        if (value < stepNumber) {
          $card.addClass("is-complete");
        } else if (value === stepNumber) {
          $card.addClass("is-current");
        }
      });
    }

    scrollToStep(stepNumber) {
      if (stepNumber === 2) {
        this.revealCvStep();
      }
      const $target = this.$stepCards.filter(`[data-step="${stepNumber}"]`);
      if ($target.length) {
        this.setActiveStep(stepNumber);
        $("html, body").animate(
          {
            scrollTop: $target.offset().top - 40,
          },
          400
        );
      }
    }

    // ========================================
    // CV UPLOAD/PASTE
    // ========================================

    bindCvEvents() {
      const self = this;

      // Paste input - update ATS match when CV is pasted
      this.$cvPasteInput.on("input", function () {
        self.cvText = $(this).val().trim();
        self.updateAtsMatch();
      });
    }

    runStepAnalysis() {
      if (
        !this.$analysisBtn.length ||
        typeof sffc_gap_analyzer === "undefined"
      ) {
        this.showToast(
          "Analysis service unavailable. Please try again later.",
          "error"
        );
        return;
      }

      const jdText = (
        this.$jdTextarea.length ? this.$jdTextarea.val() : this.jdText || ""
      ).trim();
      const cvText = (
        this.$cvPasteInput.length ? this.$cvPasteInput.val() : this.cvText || ""
      ).trim();

      if (jdText.length < 100) {
        this.showToast(
          "Please include the full job description before running the analysis.",
          "error"
        );
        this.scrollToStep(1);
        return;
      }

      if (cvText.length < 100) {
        this.showToast(
          "Paste your full CV so MENA Careers can tailor the materials.",
          "error"
        );
        this.scrollToStep(2);
        return;
      }

      this.jdText = jdText;
      this.cvText = cvText;

      this.setActiveStep(3);
      this.updateStepStatus("Analyzing your documents...", "loading");
      this.$analysisBtn.addClass("is-loading").attr("disabled", true);
      this.showPreloader();

      const self = this;
      $.ajax({
        url: sffc_gap_analyzer.ajax_url,
        type: "POST",
        dataType: "text",
        timeout: 90000,
        data: {
          action: "sffc_analyze_gap",
          nonce: sffc_gap_analyzer.nonce,
          jd_text: jdText,
          cv_text: cvText,
        },
        success(raw) {
          const response = self.parseAnalysisResponse(raw);
          self.completePreloaderProgress();
          setTimeout(() => self.hidePreloader(), 300);
          self.$analysisBtn.removeClass("is-loading").attr("disabled", false);

          if (response && response.success) {
            self.stepAnalysisData = response.data;
            self.renderStepOutputs(response.data);
            self.updateStepStatus(
              "Analysis complete. Materials ready below.",
              "success"
            );
            if (self.$stepDownloadBtn.length) {
              self.$stepDownloadBtn.prop(
                "disabled",
                !self.userHasPremiumAccess()
              );
            }
            self.scrollToStep(3);
          } else {
            const message =
              response?.data?.message || "Analysis failed. Please try again.";
            self.updateStepStatus(message, "error");
            self.stepAnalysisData = null;
            if (self.$stepDownloadBtn.length) {
              self.$stepDownloadBtn.prop("disabled", true);
            }
          }
        },
        error(xhr, status) {
          self.completePreloaderProgress();
          setTimeout(() => self.hidePreloader(), 300);
          self.$analysisBtn.removeClass("is-loading").attr("disabled", false);
          const message =
            status === "timeout"
              ? "Analysis timed out. Try shortening the CV text or check your network connection."
              : "Unable to analyze your application right now.";
          self.updateStepStatus(message, "error");
          if (self.$stepDownloadBtn.length) {
            self.$stepDownloadBtn.prop("disabled", true);
          }
        },
      });
    }

    parseAnalysisResponse(raw) {
      if (!raw) {
        return null;
      }
      try {
        const match = raw.match(/\{[\s\S]*\}$/);
        if (match) {
          return JSON.parse(match[0]);
        }
      } catch (error) {
        console.error("Failed to parse analysis response", error);
      }
      return null;
    }

    updateStepStatus(message, state = "idle") {
      if (!this.$stepStatus.length) {
        return;
      }
      this.$stepStatus.removeClass("is-loading is-success is-error");
      if (state === "loading") {
        this.$stepStatus.addClass("is-loading");
      } else if (state === "success") {
        this.$stepStatus.addClass("is-success");
      } else if (state === "error") {
        this.$stepStatus.addClass("is-error");
      }
      if (this.$stepStatusText.length) {
        this.$stepStatusText.text(message);
      }
    }

    renderStepOutputs(data) {
      this.$container.find(".inst-gap-panel-body").removeClass("has-data");
      this.stepCoverLetterText = "";
      this.stepLinkedinMessage = "";
      this.coverLetterData = null;
      this.$coverLetterSlot.empty();
      this.$interviewSlot.empty();
      this.$linkedinSlot.empty();
      this.$keywordsSlot.empty();
      this.setHighlightTokensFromAnalysis(data);
      this.renderStepSummary(data);
      this.renderStepScores(data);
      this.renderStepImprovements(data);
      this.renderStepStrengths(data);
      this.renderStepCoverLetter(data);
      this.renderStepInterviewQuestions(data);
      this.renderStepLinkedinMessage(data);
      this.renderStepKeywords(data);
      this.applyPremiumLocks();
    }

    renderStepSummary(data) {
      if (!this.$summaryVerdict.length) {
        return;
      }
      const summary = data.executive_summary || {};
      const scores = data.scores || {};
      const recommendation = summary.recommendation || "Analysis Ready";
      const verdict = summary.verdict || "MENA Careers is reviewing your documents.";
      const insight =
        summary.key_insight ||
        summary.verdict ||
        "Paste your CV to generate insights.";
      const matchScore = parseInt(
        summary.match_score || scores.overall || 0,
        10
      );

      const formattedRecommendation = recommendation.replace(/_/g, " ").trim();
      this.$summaryRecommendation.text(
        this.formatTitleCase(formattedRecommendation)
      );
      this.$summaryVerdict.text(verdict);
      this.$summaryInsight.text(insight);
      this.$summaryScore.text(`${matchScore}%`);
    }

    renderStepScores(data) {
      if (!this.$scoreCards.length) {
        return;
      }
      const scores = data.scores || {};
      const mapping = {
        skills: parseInt(scores.skills_match || 0, 10),
        experience: parseInt(scores.experience_match || 0, 10),
        keywords: parseInt(scores.keywords_match || 0, 10),
        readiness: parseInt((data.interview_prep || []).length ? 80 : 50, 10),
      };

      this.$scoreCards.each(function () {
        const $card = $(this);
        const type = $card.data("step-score");
        const value = mapping[type] ?? 0;
        $card.find("[data-score-value]").text(`${value}%`);
        $card.removeClass(
          "inst-score-card--low inst-score-card--medium inst-score-card--high"
        );
        if (value >= 70) {
          $card.addClass("inst-score-card--high");
        } else if (value >= 40) {
          $card.addClass("inst-score-card--medium");
        } else {
          $card.addClass("inst-score-card--low");
        }
        const cardEl = $card.get(0);
        if (cardEl) {
          cardEl.style.setProperty("--score-progress", value);
        }
        $card.find(".inst-score-card-heat span").css("width", `${value}%`);
      });
    }

    renderStepImprovements(data) {
      if (!this.$improvementList.length) {
        return;
      }
      const improvements = data.cv_improvements || [];
      const reqAnalysis = data.requirements_analysis || [];
      const criticalGaps = reqAnalysis
        .filter(
          (item) =>
            item.gap_severity === "significant" ||
            item.gap_severity === "critical"
        )
        .slice(0, 2);

      let html = "";

      improvements.slice(0, 3).forEach((item, index) => {
        const section = `Priority ${index + 1}: ${item.section || "CV Update"}`;
        const recommendation = item.suggested || "Add more detail";
        html += `
                    <div class="inst-gap-list-item">
                        <strong>${this.decorateHighlights(section)}</strong>
                        <span class="inst-gap-list-meta">${this.decorateHighlights(
                          recommendation
                        )}</span>
                    </div>
                `;
      });

      criticalGaps.forEach((gap) => {
        const requirement = gap.requirement || "Gap";
        const action = gap.action_needed || "Add evidence to your CV";
        html += `
                    <div class="inst-gap-list-item">
                        <strong>${this.decorateHighlights(requirement)}</strong>
                        <span class="inst-gap-list-meta">${this.decorateHighlights(
                          action
                        )}</span>
                    </div>
                `;
      });

      if (!html) {
        html =
          '<p class="inst-gap-empty">No gaps detected yet. Paste your CV to uncover specific fixes.</p>';
      }

      this.$improvementList.html(html);
    }

    renderStepStrengths(data) {
      if (!this.$strengthList.length) {
        return;
      }
      let strengths = Array.isArray(data.strengths_to_highlight)
        ? data.strengths_to_highlight.slice()
        : [];
      if (!strengths.length) {
        strengths = this.generateFallbackStrengths();
      }

      let html = "";

      strengths.slice(0, 4).forEach((item) => {
        const title =
          typeof item === "string"
            ? item
            : item.strength || item.skill || "Strength";
        const detail =
          typeof item === "string"
            ? ""
            : item.how_to_leverage || item.relevance || "";
        const titleHtml = this.decorateHighlights(title);
        const detailHtml = detail ? this.decorateHighlights(detail) : "";
        html += `
                    <div class="inst-gap-list-item">
                        <strong>${titleHtml}</strong>
                        ${
                          detailHtml
                            ? `<span class="inst-gap-list-meta">${detailHtml}</span>`
                            : ""
                        }
                    </div>
                `;
      });

      if (!html) {
        html =
          '<p class="inst-gap-empty">Your standout wins will appear here after the analysis.</p>';
        this.$strengthList.removeClass("has-data");
      } else {
        this.$strengthList.addClass("has-data");
      }

      this.$strengthList.html(html);
    }

    generateFallbackStrengths() {
      const highlights = [];
      const topSkills = (this.extractSkills() || []).slice(0, 3);
      const supportingKeywords = (this.extractKeywords() || []).slice(0, 2);

      topSkills.forEach((skill) => {
        const formatted = this.formatTitleCase(skill);
        highlights.push({
          strength: formatted,
          how_to_leverage: `Reference quantified wins that prove your ${formatted.toLowerCase()} expertise.`,
        });
      });

      supportingKeywords.forEach((keyword) => {
        highlights.push({
          strength: this.formatTitleCase(keyword),
          how_to_leverage: `Mirror this keyword in your CV bullets and opening paragraph to align with the JD.`,
        });
      });

      if (!highlights.length && this.jobTitle) {
        highlights.push({
          strength: "Relevant track record",
          how_to_leverage: `Draw a line between your recent projects and the outcomes required for ${this.jobTitle}.`,
        });
      }

      return highlights.slice(0, 4);
    }

    renderStepCoverLetter(data) {
      if (!this.$coverLetterSlot.length || !this.$coverPanelBody.length) {
        return;
      }

      const summary = data.executive_summary || {};
      const strengths = data.strengths_to_highlight || [];
      const keywords = data.keyword_analysis || {};
      const missingKeywords = keywords.critical_missing || [];
      const matchedKeywords = keywords.well_represented || [];

      const roleTitle = summary.role_title || this.jobTitle || "this role";
      const company = summary.company || this.companyName || "";
      const date = new Date().toLocaleDateString("en-US", {
        month: "long",
        day: "numeric",
        year: "numeric",
      });

      let html = '<div class="inst-step-cover-letter-content">';
      html += `<p>${this.escapeHtml(date)}</p>`;
      html += "<p>Dear Hiring Manager,</p>";
      html += `<p>I am writing to express my interest in the <strong>${this.escapeHtml(
        roleTitle
      )}</strong>${
        company
          ? ` opportunity at <strong>${this.escapeHtml(company)}</strong>`
          : ""
      }. After studying the brief, I believe my experience and toolkit align closely with what your team needs.</p>`;

      if (strengths.length) {
        html +=
          "<p>Key strengths I would bring to this search include:</p><ul>";
        strengths.slice(0, 3).forEach((strength) => {
          const title =
            typeof strength === "string"
              ? strength
              : strength.strength || strength.skill || "Relevant experience";
          const detail =
            typeof strength === "string"
              ? ""
              : strength.how_to_leverage ||
                strength.relevance ||
                "Directly applicable to the role.";
          html += `<li><strong>${this.escapeHtml(title)}</strong>${
            detail ? ` — ${this.escapeHtml(detail)}` : ""
          }</li>`;
        });
        html += "</ul>";
      }

      if (matchedKeywords.length) {
        html += `<p>I consistently deliver in environments that prioritise ${this.escapeHtml(
          this.formatReadableList(matchedKeywords.slice(0, 4))
        )} and would bring that same focus to this mandate.</p>`;
      }

      if (missingKeywords.length) {
        html += `<p>To further tighten the fit, I'm already incorporating ${this.escapeHtml(
          this.formatReadableList(missingKeywords.slice(0, 3))
        )} throughout my application materials so your ATS sees immediate alignment.</p>`;
      }

      html += `<p>I would welcome the opportunity to discuss how this experience can support ${
        company ? this.escapeHtml(company) : "your team"
      } and share specific examples from my recent work.</p>`;
      html +=
        "<p>Thank you for your consideration.<br>" +
        this.escapeHtml(this.getCandidateFirstName()) +
        "<br>[Your Email]</p>";
      html += "</div>";

      this.coverLetterData = {
        html,
        roleTitle,
        company,
        date,
      };

      this.$coverLetterSlot.html(html);
      this.$coverPanelBody.addClass("has-data");
      this.stepCoverLetterText = this.stripHtml(html);
    }

    renderStepInterviewQuestions(data) {
      if (!this.$interviewSlot.length || !this.$interviewPanelBody.length) {
        return;
      }

      let questions = Array.isArray(data.interview_prep)
        ? data.interview_prep.slice()
        : [];
      if (!questions.length) {
        questions = this.generateFallbackInterviewPrep();
      } else if (questions.length < 4) {
        const supplemental = this.generateFallbackInterviewPrep();
        for (
          let i = 0;
          i < supplemental.length && questions.length < 4;
          i += 1
        ) {
          questions.push(supplemental[i]);
        }
      }

      questions = questions.map((question, idx) => {
        if (typeof question === "string") {
          return {
            likely_question: question,
            suggested_response_angle: "",
            example_answer: "",
          };
        }
        if (!question || typeof question !== "object") {
          return {
            likely_question: `Interview question ${idx + 1}`,
            suggested_response_angle: "",
            example_answer: "",
          };
        }
        return question;
      });

      let html = "";
      questions.slice(0, 4).forEach((question, index) => {
        const title =
          question.likely_question ||
          question.question ||
          `Question ${index + 1}`;
        const why =
          question.why_theyll_ask || question.suggested_response_angle || "";
        const answer = question.example_answer || "";
        html += '<div class="inst-step-question-card">';
        html += `<h4>${this.escapeHtml(title)}</h4>`;
        if (why) {
          html += `<p>${this.escapeHtml(why)}</p>`;
        }
        if (answer) {
          html += `<p>${this.escapeHtml(answer)}</p>`;
          const encodedAnswer = encodeURIComponent(answer);
          html += `<button type="button" data-copy-source="interview-question" data-copy-text="${encodedAnswer}">Copy response</button>`;
        }
        html += "</div>";
      });

      this.$interviewSlot.html(html);
      if (html) {
        this.$interviewPanelBody.addClass("has-data");
      }
    }

    renderStepLinkedinMessage(data) {
      if (!this.$linkedinSlot.length || !this.$linkedinPanelBody.length) {
        return;
      }

      const summary = data.executive_summary || {};
      const scores = data.scores || {};
      const strengths = data.strengths_to_highlight || [];
      const keywords = data.keyword_analysis || {};
      const recruiterFirstName =
        (this.recruiterName || "").split(" ")[0] || "there";
      const roleTitle = summary.role_title || this.jobTitle || "this role";
      const company = summary.company || this.companyName || "";
      const strengthSentences = strengths.slice(0, 2).map((item) => {
        const label =
          typeof item === "string"
            ? item
            : item.strength || item.skill || "Relevant experience";
        const detail =
          typeof item === "string"
            ? ""
            : item.relevance || item.how_to_leverage || "";
        return detail ? `${label} — ${detail}` : label;
      });

      const keywordMentions = (keywords.well_represented || [])
        .concat(keywords.critical_missing || [])
        .slice(0, 3);

      const messageParts = [];
      messageParts.push(`Hi ${recruiterFirstName},`);
      messageParts.push("");
      messageParts.push(
        `I'm finalising my materials for the ${roleTitle}${
          company ? ` opportunity at ${company}` : ""
        } and wanted to introduce myself directly.`
      );

      if (strengthSentences.length) {
        messageParts.push("");
        messageParts.push("Snapshot of where I add value:");
        strengthSentences.forEach((sentence) => {
          messageParts.push(`• ${sentence}`);
        });
      }

      if (keywordMentions.length) {
        messageParts.push("");
        messageParts.push(
          `The focus on ${this.formatReadableList(
            keywordMentions
          )} lines up with recent projects I've delivered, so the tailored CV + cover letter already speak that language.`
        );
      }

      messageParts.push("");
      messageParts.push(
        "If you are still shortlisting, could we schedule a quick 15-minute call this week? I can forward a concise plan before we speak."
      );
      messageParts.push("");
      messageParts.push("Thanks,");
      messageParts.push(this.getCandidateFirstName());
      messageParts.push("[Email | LinkedIn]");

      const message = messageParts.join("\n");

      this.stepLinkedinMessage = message;
      const highlightedMessage = this.decorateHighlights(message);
      this.$linkedinSlot.html(
        `<div class="inst-step-message">${highlightedMessage}</div>`
      );
      if (this.$linkedinMeta.length) {
        this.$linkedinMeta.text(
          `Draft tailored to ${this.recruiterName || "the recruiter"}`
        );
      }
      this.$linkedinPanelBody.addClass("has-data");
    }

    renderStepKeywords(data) {
      if (!this.$keywordsSlot.length || !this.$keywordsPanelBody.length) {
        return;
      }

      const keywords = data.keyword_analysis || {};
      const matched = keywords.well_represented || [];
      const missing = keywords.critical_missing || [];
      let html = "";

      if (matched.length) {
        html +=
          '<div class="inst-gap-keywords-group"><span>Already covered</span><div class="inst-gap-keyword-tags">';
        matched.slice(0, 10).forEach((keyword) => {
          html += `<span class="inst-step-keyword">${this.escapeHtml(
            keyword
          )}</span>`;
        });
        html += "</div></div>";
      }

      if (missing.length) {
        html +=
          '<div class="inst-gap-keywords-group"><span>Add these next</span><div class="inst-gap-keyword-tags">';
        missing.slice(0, 10).forEach((keyword) => {
          html += `<span class="inst-step-keyword inst-step-keyword--missing">${this.escapeHtml(
            keyword
          )}</span>`;
        });
        html += "</div></div>";
      }

      if (!html) {
        this.$keywordsSlot.html(
          '<p class="inst-gap-empty">No additional keywords detected for this role.</p>'
        );
        this.$keywordsPanelBody.removeClass("has-data");
        return;
      }

      this.$keywordsPanelBody.addClass("has-data");
      this.$keywordsSlot.html(html);
    }

    exportStepAnalysis() {
      if (!this.stepAnalysisData) {
        this.showToast("Generate your materials before downloading.", "error");
        return;
      }

      if (!this.$stepDownloadBtn.length) {
        return;
      }

      const $btn = this.$stepDownloadBtn;
      const originalHtml = $btn.html();
      $btn.addClass("is-loading").attr("disabled", true).text("Preparing...");

      const self = this;
      $.ajax({
        url: sffc_gap_analyzer.ajax_url,
        type: "POST",
        dataType: "text",
        data: {
          action: "sffc_export_gap_pdf",
          nonce: sffc_gap_analyzer.nonce,
          analysis_data: JSON.stringify(this.stepAnalysisData),
        },
        success(raw) {
          const response = self.parseAnalysisResponse(raw);
          if (response && response.success) {
            const printWindow = window.open("", "_blank");
            printWindow.document.write(response.data.html);
            printWindow.document.close();
            printWindow.onload = function () {
              setTimeout(() => {
                printWindow.print();
              }, 300);
            };
          } else {
            const message =
              response?.data?.message || "Failed to prepare download.";
            self.showToast(message, "error");
          }
        },
        error() {
          self.showToast("Download failed. Please try again.", "error");
        },
        complete() {
          $btn
            .removeClass("is-loading")
            .attr("disabled", false)
            .html(originalHtml);
        },
      });
    }

    copyTextContent(text, successMessage = "Copied!") {
      if (!text) {
        this.showToast("Nothing to copy yet.", "error");
        return;
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard
          .writeText(text)
          .then(() => {
            this.showToast(successMessage, "success");
          })
          .catch(() => {
            this.showToast("Clipboard not available on this device.", "error");
          });
      }
    }

    downloadCoverLetterAsWord() {
      if (!this.coverLetterData) {
        this.showToast("Generate the cover letter first.", "error");
        return;
      }

      const $btn = this.$container.find('[data-download="cover-word"]');
      $btn.addClass("is-loading").attr("disabled", true);

      try {
        const { html, roleTitle, company } = this.coverLetterData;
        const doc = `
                    <!DOCTYPE html>
                    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
                    <head>
                        <meta charset="UTF-8" />
                        <title>Cover Letter - ${this.escapeHtml(
                          roleTitle
                        )}</title>
                        <style>
                            body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; line-height: 1.6; color: #1e293b; margin: 60px; }
                            p { margin: 0 0 12pt; }
                            ul { margin: 0 0 12pt 18pt; }
                            strong { color: #0d353e; }
                        </style>
                    </head>
                    <body>
                        <h2 style="margin-bottom:12pt;">${this.escapeHtml(
                          roleTitle
                        )}${
          company ? " – " + this.escapeHtml(company) : ""
        }</h2>
                        ${html}
                    </body>
                    </html>
                `;

        const blob = new Blob([doc], { type: "application/msword" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `Cover_Letter_${
          new Date().toISOString().split("T")[0]
        }.doc`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        this.showToast("Cover letter downloaded");
      } catch (error) {
        console.error("cover letter export error", error);
        this.showToast("Failed to generate Word document.", "error");
      } finally {
        $btn.removeClass("is-loading").attr("disabled", false);
      }
    }

    applyPremiumLocks() {
      const requiresLock = !this.userHasPremiumAccess();
      if (this.$stepDownloadBtn.length) {
        if (requiresLock) {
          this.$stepDownloadBtn.addClass("is-disabled").attr("disabled", true);
        } else {
          this.$stepDownloadBtn
            .removeClass("is-disabled")
            .attr("disabled", false);
        }
      }
    }

    showApplyModal() {
      if (!this.$applyModal.length) {
        this.showPackModal();
        return;
      }
      this.$applyModal.show().addClass("show");
      this.lockBodyScroll();
    }

    hideApplyModal() {
      if (!this.$applyModal.length) {
        return;
      }
      this.$applyModal.removeClass("show").hide();
      this.unlockBodyScroll();
    }

    scrollToExpressInterest() {
      this.scrollToSection("#inst-express-interest-view");
    }

    userHasPremiumAccess() {
      return !!(this.isLoggedIn && this.isPremium);
    }

    switchCvMode(mode) {
      this.$cvModeBtns.removeClass("is-active").attr("aria-selected", "false");
      this.$cvModeBtns
        .filter(`[data-cv-mode="${mode}"]`)
        .addClass("is-active")
        .attr("aria-selected", "true");

      this.$cvPanels.removeClass("is-active").prop("hidden", true);
      this.$cvPanels
        .filter(`[data-cv-panel="${mode}"]`)
        .addClass("is-active")
        .prop("hidden", false);
    }

    handleFileUpload(file) {
      const validTypes = [
        "application/pdf",
        "application/msword",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "text/plain",
      ];
      const maxSize = 5 * 1024 * 1024; // 5MB

      if (!validTypes.includes(file.type)) {
        alert("Please upload a PDF, DOC, DOCX, or TXT file.");
        return;
      }

      if (file.size > maxSize) {
        alert("File size must be less than 5MB.");
        return;
      }

      this.cvFile = file;
      this.$cvFilename.text(file.name);
      this.$cvUploadZone.hide();
      this.$cvFilePreview.show();

      // Read file content for text files
      if (file.type === "text/plain") {
        const reader = new FileReader();
        reader.onload = (e) => {
          this.cvText = e.target.result;
          this.updateAtsMatch();
        };
        reader.readAsText(file);
      }
    }

    removeFile() {
      this.cvFile = null;
      this.cvText = "";
      this.$cvFileInput.val("");
      this.$cvFilePreview.hide();
      this.$cvUploadZone.show();
      this.updateAtsMatch();
    }

    // ========================================
    // PRODUCT SELECTION (Application Pack)
    // ========================================

    bindProductEvents() {
      const self = this;

      this.$addToPackBtns.on("click", function (e) {
        e.preventDefault();
        const product = $(this).data("product");
        const $card = $(this).closest(".inst-product-card");

        self.toggleProduct(product, $card, $(this));
      });

      this.$generatePackBtn.on("click", function () {
        self.generatePack();
      });
    }

    toggleProduct(product, $card, $btn) {
      if (this.selectedProducts.has(product)) {
        // Remove from pack
        this.selectedProducts.delete(product);
        $card.removeClass("is-selected");
        $btn.html(`
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add to Pack
                `);
      } else {
        // Add to pack
        this.selectedProducts.add(product);
        $card.addClass("is-selected");
        $btn.html(`
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Added
                `);
      }

      this.updatePackSummary();
    }

    updatePackSummary() {
      const count = this.selectedProducts.size;
      this.$packCount.text(count);

      // Product icons for sidebar
      const productIcons = {
        "tailored-cv":
          '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        "cover-letter":
          '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        "interview-questions":
          '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>',
        "ats-optimisation":
          '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
      };

      const productLabels = {
        "tailored-cv": "Tailored CV",
        "cover-letter": "Cover Letter",
        "interview-questions": "Interview Prep",
        "ats-optimisation": "ATS Optimised",
      };

      if (count > 0) {
        // Add has-items class to sidebar
        this.$packSidebar.addClass("has-items");
        this.$generatePackBtn.prop("disabled", false);

        let itemsHtml = "";
        this.selectedProducts.forEach((product) => {
          itemsHtml += `
                        <div class="inst-pack-item" data-product="${product}">
                            <div class="inst-pack-item-info">
                                <div class="inst-pack-item-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        ${productIcons[product] || ""}
                                    </svg>
                                </div>
                                <span class="inst-pack-item-name">${
                                  productLabels[product] || product
                                }</span>
                            </div>
                            <button type="button" class="inst-pack-item-remove" data-remove="${product}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                    `;
        });
        this.$packItems.html(itemsHtml);

        // Bind remove buttons in sidebar
        const self = this;
        this.$packItems.find(".inst-pack-item-remove").on("click", function () {
          const product = $(this).data("remove");
          const $card = self.$container.find(
            `.inst-product-card[data-product="${product}"]`
          );
          const $btn = $card.find(".inst-add-to-pack-btn");
          self.toggleProduct(product, $card, $btn);
        });
      } else {
        this.$packSidebar.removeClass("has-items");
        this.$generatePackBtn.prop("disabled", true);
        this.$packItems.html(
          '<div class="inst-pack-empty"><p>Add items to your pack</p></div>'
        );
      }
    }

    generatePack() {
      // Check if products are selected
      if (this.selectedProducts.size === 0) {
        alert("Please select at least one item to add to your pack.");
        return;
      }

      // Check if user is logged in or premium - show modal instead of redirect
      if (!this.isLoggedIn || !this.isPremium) {
        this.showPackModal();
        return;
      }

      // Check if CV is provided (paste only) - only for premium users
      if (!this.cvText && !this.$cvPasteInput.val().trim()) {
        alert("Please paste your CV first.");
        return;
      }

      // Show preloader
      this.showPreloader();

      // TODO: Implement actual generation via AJAX
      console.log("Generating pack with:", Array.from(this.selectedProducts));
      console.log("CV Text:", this.cvText || this.$cvPasteInput.val());

      // Simulate generation (replace with actual AJAX call)
      setTimeout(() => {
        this.hidePreloader();
        alert("Pack generated successfully! (Demo mode)");
      }, 3000);
    }

    showPreloader() {
      this.$preloader.show();
      this.resetPreloaderVisuals();
      this.startPreloaderSteps();
      this.startPreloaderProgress();
      this.lockBodyScroll();
    }

    hidePreloader() {
      this.stopPreloaderSteps();
      this.stopPreloaderProgress();
      this.$preloader.hide();
      this.$preloaderSteps.removeClass("is-active is-complete");
      this.unlockBodyScroll();
    }

    resetPreloaderVisuals() {
      this.$preloaderSteps.removeClass("is-active is-complete");
      this.updatePreloaderPercent(0);
    }

    startPreloaderSteps() {
      if (!this.$preloaderSteps.length) {
        return;
      }
      this.stopPreloaderSteps();
      let current = 0;
      this.preloaderStepTimer = setInterval(() => {
        if (current < this.$preloaderSteps.length) {
          const $step = $(this.$preloaderSteps[current]);
          $step.addClass("is-active");
          if (current > 0) {
            $(this.$preloaderSteps[current - 1])
              .removeClass("is-active")
              .addClass("is-complete");
          }
          current++;
        } else {
          this.stopPreloaderSteps();
          const $last = $(
            this.$preloaderSteps[this.$preloaderSteps.length - 1]
          );
          $last.removeClass("is-active").addClass("is-complete");
        }
      }, 1000);
    }

    stopPreloaderSteps() {
      if (this.preloaderStepTimer) {
        clearInterval(this.preloaderStepTimer);
        this.preloaderStepTimer = null;
      }
    }

    startPreloaderProgress() {
      this.stopPreloaderProgress();
      this.preloaderPercentValue = 0;
      this.updatePreloaderPercent(0);
      this.preloaderProgressInterval = setInterval(() => {
        if (this.preloaderPercentValue < 85) {
          this.updatePreloaderPercent(this.preloaderPercentValue + 3);
        }
      }, 400);
    }

    stopPreloaderProgress() {
      if (this.preloaderProgressInterval) {
        clearInterval(this.preloaderProgressInterval);
        this.preloaderProgressInterval = null;
      }
    }

    updatePreloaderPercent(value) {
      this.preloaderPercentValue = Math.min(Math.max(value, 0), 100);
      if (this.$preloaderProgress && this.$preloaderProgress.length) {
        this.$preloaderProgress.css("width", `${this.preloaderPercentValue}%`);
      }
      if (this.$preloaderPercent && this.$preloaderPercent.length) {
        this.$preloaderPercent.text(`${this.preloaderPercentValue}%`);
      }
    }

    completePreloaderProgress() {
      this.updatePreloaderPercent(100);
    }

    // ========================================
    // EXPRESS INTEREST (Upsell & mailto)
    // ========================================

    bindExpressInterestEvents() {
      const self = this;

      // Upsell card checkboxes
      this.$upsellCards.on("change", 'input[type="checkbox"]', function () {
        const product = $(this).val();
        const $card = $(this).closest(".inst-upsell-card");

        if ($(this).is(":checked")) {
          self.selectedUpsells.add(product);
          $card.addClass("is-selected");
        } else {
          self.selectedUpsells.delete(product);
          $card.removeClass("is-selected");
        }
      });

      // Message Recruiter button - show likelihood modal first
      this.$messageRecruiterBtn.on("click", function (e) {
        e.preventDefault();
        self.showLikelihoodModal();
      });

      // Modal close button
      this.$likelihoodClose.on("click", function () {
        self.hideLikelihoodModal();
      });

      // Modal overlay click to close
      this.$likelihoodOverlay.on("click", function () {
        self.hideLikelihoodModal();
      });

      // Reach out to one recruiter
      this.$likelihoodSingleBtn.on("click", function () {
        self.hideLikelihoodModal();
        self.proceedToSendMessage();
      });

      // Reach out to multiple recruiters
      this.$likelihoodMultiBtn.on("click", function () {
        self.hideLikelihoodModal();
        self.scrollToOutreachSection();
      });

      // Product action buttons
      this.$productActionBtns.on("click", function () {
        const product = $(this).data("product");
        // Add to pack if not already added
        const $card = self.$container.find(
          `.inst-product-card[data-product="${product}"]`
        );
        if (!$card.hasClass("is-selected")) {
          const $btn = $card.find(".inst-add-to-pack-btn");
          self.toggleProduct(product, $card, $btn);
        }
        // Check premium access
        if (!self.isPremium) {
          window.open(self.membershipUrl, "_blank");
          return;
        }
        // TODO: Trigger generation for this specific product
        alert("Generating " + product + "... This feature is coming soon!");
      });
    }

    // ========================================
    // LIKELIHOOD MODAL
    // ========================================

    showLikelihoodModal() {
      // Validate form first
      const missingFields = this.getMissingContactFields();

      if (missingFields.length) {
        this.showMissingDetailsModal(missingFields);
        this.switchTab("express-interest");
        this.scrollToExpressHeader();
        return;
      }

      if (!this.$likelihoodModal.length) {
        return;
      }
      this.$likelihoodModal.show();
      this.lockBodyScroll();

      // Load smart message preview
      this.loadSmartMessagePreview(
        this.$firstName.val().trim(),
        this.$lastName.val().trim()
      );
    }

    hideLikelihoodModal() {
      if (!this.$likelihoodModal.length) {
        return;
      }
      this.$likelihoodModal.hide();
      this.unlockBodyScroll();
    }

    /**
     * Load AI-generated smart message preview
     */
    loadSmartMessagePreview(firstName, lastName) {
      const self = this;
      const $previewContainer = this.$container.find(
        "#instSmartMessagePreview"
      );

      // Skip if already loaded
      if (this.smartMessageLoaded) {
        return;
      }

      // Show loading state
      $previewContainer.html(`
                <div class="inst-preview-loading">
                    <span class="inst-preview-spinner"></span>
                    Generating tailored preview...
                </div>
            `);

      // Call AJAX to generate preview
      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_generate_smart_message",
          nonce: sffc_recruiter_post.nonce,
          post_id: this.postId,
          recruiter_name: this.recruiterName,
          job_title: this.jobTitle,
          company_name: this.companyName,
          jd_text: this.jdText,
          first_name: firstName,
          last_name: lastName,
          is_preview: true,
        },
        success: function (response) {
          if (response.success && response.data.message) {
            self.smartMessage = response.data.message;
            self.smartMessageLoaded = true;

            // Format preview (show first ~150 chars with ellipsis)
            const previewText = self.formatSmartMessagePreview(
              response.data.message
            );

            $previewContainer.html(`
                            <div class="inst-smart-message-content">
                                <p>${previewText}</p>
                            </div>
                            <div class="inst-smart-message-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                                    <path d="M2 17l10 5 10-5"/>
                                    <path d="M2 12l10 5 10-5"/>
                                </svg>
                                AI-Tailored Message
                            </div>
                        `);
          } else {
            $previewContainer.html(`
                            <div class="inst-smart-message-content">
                                <p><em>Personalized message tailored to this specific role and recruiter...</em></p>
                            </div>
                        `);
          }
        },
        error: function () {
          $previewContainer.html(`
                        <div class="inst-smart-message-content">
                            <p><em>AI-powered personalized messaging available with Smart Pack...</em></p>
                        </div>
                    `);
        },
      });
    }

    getMissingContactFields() {
      const fields = [];
      const firstName = this.$firstName.val().trim();
      const lastName = this.$lastName.val().trim();
      const email = this.$email.val().trim();

      if (!firstName) {
        fields.push("First name");
      }
      if (!lastName) {
        fields.push("Last name");
      }
      if (!email) {
        fields.push("Email address");
      } else if (!this.validateEmail(email)) {
        fields.push("Valid email address");
      }

      return fields;
    }

    buildMailtoLink(firstName, lastName, email) {
      const recipient = (this.recruiterEmail || "").trim();
      const subject = this.buildEmailSubject();
      const body = this.composeEmailBody(firstName, lastName, email);

      this.emailPreviewData = {
        subject,
        body,
        recipient,
      };

      if (!recipient) {
        return null;
      }

      return `mailto:${encodeURIComponent(
        recipient
      )}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(
        body
      )}`;
    }

    composeEmailBody(firstName, lastName, email) {
      const recruiterFirstName =
        (this.recruiterName || "").split(" ")[0] || "there";
      const emailCore = this.generateOutreachEmail(
        recruiterFirstName,
        firstName,
        lastName,
        email
      );
      const signatureLines = ["Best,", `${firstName} ${lastName}`];
      if (email) {
        signatureLines.push(email);
      }
      if (!/linkedin/i.test(emailCore)) {
        signatureLines.push("LinkedIn: [Add your LinkedIn URL]");
      }

      return `${emailCore}\n\n${signatureLines.filter(Boolean).join("\n")}`;
    }

    buildEmailSubject() {
      const role = this.jobTitle || "Opportunity";
      return `Enquiry: ${role}`;
    }

    generateOutreachEmail(recruiterFirstName, firstName, lastName, email) {
      const aiMessage = this.getAiOutreachSnippet(
        recruiterFirstName,
        firstName,
        lastName,
        email
      );
      if (aiMessage) {
        return aiMessage;
      }
      return this.generateFallbackOutreach(recruiterFirstName);
    }

    getAiOutreachSnippet(recruiterFirstName, firstName, lastName, email) {
      if (!this.stepLinkedinMessage || !this.stepLinkedinMessage.trim()) {
        return "";
      }
      let message = this.stepLinkedinMessage.trim();
      message = message.replace(/\r/g, "");
      const fullName = `${firstName || ""} ${lastName || ""}`.trim();
      if (fullName) {
        message = message.replace(/\[Your Name\]/gi, fullName);
      }
      if (email) {
        message = message.replace(
          /\[(?:Email\s*(?:\||\/)?\s*LinkedIn|Email)\]/gi,
          email
        );
      }
      message = message.replace(/LinkedIn:\s*\[Add your LinkedIn URL\]/gi, "");
      message = message.replace(/Thanks,[\s\S]*$/i, "").trim();
      const hiPattern = /^hi[^,]*,/i;
      if (hiPattern.test(message)) {
        message = message.replace(hiPattern, `Hi ${recruiterFirstName},`);
      } else {
        message = `Hi ${recruiterFirstName},\n\n${message}`;
      }
      if (message.length < 80) {
        return "";
      }
      return message;
    }

    generateFallbackOutreach(recruiterFirstName) {
      const context = this.buildFallbackContext(recruiterFirstName);
      const introTemplate = this.randomChoice(EMAIL_INTRO_VARIANTS);
      const bodyTemplate = this.randomChoice(EMAIL_BODY_VARIANTS);
      const ctaTemplate = this.randomChoice(EMAIL_CTA_VARIANTS);
      const reassuranceTemplate = this.randomChoice(EMAIL_REASSURE_VARIANTS);

      const intro = introTemplate ? introTemplate(context) : "";
      const body = bodyTemplate ? bodyTemplate(context) : "";
      const cta = ctaTemplate ? ctaTemplate(context) : "";
      const reassurance = reassuranceTemplate
        ? reassuranceTemplate(context)
        : "";

      const parts = [intro, body, cta];
      if (reassurance) {
        parts.push(reassurance);
      }

      return parts.filter(Boolean).join("\n\n");
    }

    buildFallbackContext(recruiterFirstName) {
      const roleTitle = this.jobTitle || "this role";
      const company = this.companyName || "";
      const rolePhrase = `${roleTitle}${company ? ` at ${company}` : ""}`;
      const keywords = this.extractKeywords().slice(0, 3);
      const valueStatements = this.buildValueStatements();
      const cleanedStatements = valueStatements.map((statement) =>
        this.cleanValueStatement(statement)
      );
      const bulletBlock = cleanedStatements
        .map((statement) => `• ${statement}`)
        .join("\n");
      const hasCv = Boolean(this.getCvSourceText());

      return {
        recruiterName: recruiterFirstName,
        roleTitle,
        roleTitlePlural: roleTitle.endsWith("s")
          ? roleTitle
          : `${roleTitle} mandates`,
        company,
        rolePhrase,
        focusLine: keywords.length
          ? `The focus on ${this.formatReadableList(
              keywords
            )} is exactly what I've been delivering recently.`
          : "",
        valueHeading: hasCv ? "Recent wins:" : "Where I move the needle:",
        valueBullets: bulletBlock,
        hasCv,
      };
    }

    buildValueStatements() {
      const cvWins = this.extractCvWins(3);
      if (cvWins.length) {
        return cvWins;
      }
      const requirementStatements = this.generateRequirementStatements(3);
      if (requirementStatements.length) {
        return requirementStatements;
      }
      return [
        "Led regional hiring sprints that compressed time-to-offer without compromising candidate quality.",
        "Partnered with leadership to translate complex briefs into recruiter-ready scorecards.",
        "Delivered recruiter collateral (CVs, outreach, trackers) that keeps interviews moving quickly.",
      ];
    }

    extractCvWins(limit = 3) {
      const source = this.getCvSourceText();
      if (!source) {
        return [];
      }
      const segments = source
        .split(/\r?\n|•|\u2022/)
        .map((segment) => segment.trim())
        .filter((segment) => segment.length > 25);
      const unique = [];
      segments.forEach((segment) => {
        const clean = this.cleanValueStatement(segment);
        if (clean && !unique.includes(clean)) {
          unique.push(clean);
        }
      });
      const metricSegments = unique.filter((segment) =>
        /\d|%|\$/.test(segment)
      );
      const ordered = metricSegments.concat(
        unique.filter((segment) => !metricSegments.includes(segment))
      );
      return ordered.slice(0, limit);
    }

    getCvSourceText() {
      if (this.cvText && this.cvText.trim()) {
        return this.cvText.trim();
      }
      if (this.$cvPasteInput && this.$cvPasteInput.length) {
        const pasted = this.$cvPasteInput.val().trim();
        if (pasted) {
          return pasted;
        }
      }
      return "";
    }

    generateRequirementStatements(limit = 3) {
      const statements = [];
      const requirements = this.extractKeyRequirements();
      requirements.slice(0, limit).forEach((requirement) => {
        const cleanReq = requirement
          .replace(/^[-•*]\s*/, "")
          .trim()
          .replace(/\.$/, "");
        statements.push(
          `Your emphasis on ${cleanReq} is exactly what I delivered in my last role.`
        );
      });
      if (statements.length < limit) {
        const keywords = this.extractKeywords().slice(
          0,
          limit - statements.length
        );
        keywords.forEach((keyword) => {
          statements.push(
            `Built ${keyword} frameworks across global teams and can replicate that playbook quickly.`
          );
        });
      }
      return statements.slice(0, limit);
    }

    cleanValueStatement(statement) {
      return statement
        .replace(/^[-•\s]+/, "")
        .replace(/\s+/g, " ")
        .trim();
    }

    randomChoice(list) {
      if (!Array.isArray(list) || !list.length) {
        return null;
      }
      const index = Math.floor(Math.random() * list.length);
      return list[index];
    }

    /**
     * Format smart message for preview (truncate with highlights)
     */
    formatSmartMessagePreview(message) {
      // Get first 180 chars approximately (cut at word boundary)
      let preview = message;
      if (preview.length > 180) {
        preview = preview.substring(0, 180);
        const lastSpace = preview.lastIndexOf(" ");
        if (lastSpace > 100) {
          preview = preview.substring(0, lastSpace);
        }
        preview += "...";
      }

      // Escape HTML and convert newlines to breaks
      preview = preview
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\n/g, "<br>");

      return preview;
    }

    proceedToSendMessage() {
      const missingFields = this.getMissingContactFields();
      if (missingFields.length) {
        this.hideLikelihoodModal();
        this.showMissingDetailsModal(missingFields);
        this.switchTab("express-interest");
        this.scrollToExpressHeader();
        return;
      }

      const firstName = this.$firstName.val().trim();
      const lastName = this.$lastName.val().trim();
      const email = this.$email.val().trim();
      const mailtoLink = this.buildMailtoLink(firstName, lastName, email);

      if (mailtoLink) {
        window.location.href = mailtoLink;
      } else {
        this.showToast("Recruiter email unavailable.", "error");
      }

      this.saveApplicant(firstName, lastName, email);

      if (
        this.$trackApplication &&
        this.$trackApplication.is(":checked") &&
        this.isLoggedIn
      ) {
        this.trackApplication(firstName, lastName, email);
      }

      if (this.emailPreviewData) {
        this.showEmailPreviewModal(this.emailPreviewData);
      }
    }

    performExpressSignup(firstName, lastName, email) {
      const self = this;
      const deferred = $.Deferred();

      if (this.$messageRecruiterBtn && this.$messageRecruiterBtn.length) {
        this.$messageRecruiterBtn.prop("disabled", true).addClass("is-loading");
      }

      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_quick_register_candidate",
          nonce: sffc_recruiter_post.nonce,
          first_name: firstName,
          last_name: lastName,
          email: email,
          redirect_to:
            sffc_recruiter_post.crm_url ||
            sffc_recruiter_post.pipeline_url ||
            window.location.href,
        },
      })
        .done((response) => {
          if (!response || !response.success) {
            const message =
              response?.data?.message ||
              "Unable to create your account right now.";
            deferred.reject(message);
            return;
          }

          const status = response.data?.status || "created";

          if (status === "exists") {
            const loginUrl =
              response.data?.redirect ||
              response.data?.login_url ||
              this.membershipUrl ||
              "https://joinsenna.com/memberships/";
            const message =
              response.data?.message || "Continue to memberships.";
            self.showToast(message, "info");
            if (loginUrl) {
              setTimeout(() => {
                window.location.href = loginUrl;
              }, 800);
            }
            deferred.reject(message);
            return;
          }

          if (status === "lead_captured" && response.data?.redirect) {
            if (response.data.message) {
              self.showToast(response.data.message, "success");
            }
            setTimeout(() => {
              window.location.href = response.data.redirect;
            }, 500);
            deferred.reject(response.data.message || "Continue to memberships.");
            return;
          }

          self.isLoggedIn = true;
          self.$container.attr("data-is-logged-in", "true");

          if (response.data?.message) {
            self.showToast(response.data.message, "success");
          }

          deferred.resolve(response.data);
        })
        .fail(() => {
          deferred.reject("Unable to create your account right now.");
        })
        .always(() => {
          if (self.$messageRecruiterBtn && self.$messageRecruiterBtn.length) {
            self.$messageRecruiterBtn
              .prop("disabled", false)
              .removeClass("is-loading");
          }
        });

      return deferred.promise();
    }

    sendToRecruiter() {
      // Validate form
      const firstName = this.$firstName.val().trim();
      const lastName = this.$lastName.val().trim();
      const email = this.$email.val().trim();

      if (!firstName || !lastName || !email) {
        alert("Please fill in all your details.");
        return;
      }

      if (!this.validateEmail(email)) {
        alert("Please enter a valid email address.");
        return;
      }

      // Check if upsells are selected and user is not premium
      if (this.selectedUpsells.size > 0 && !this.isPremium) {
        // Redirect to membership page in new tab
        window.open(this.membershipUrl, "_blank");
      }

      this.saveApplicant(firstName, lastName, email);

      // Track application if checkbox is checked
      if (this.$trackApplication.is(":checked") && this.isLoggedIn) {
        this.trackApplication(firstName, lastName, email);
      }

      this.redirectToCrmWorkspace();
    }

    redirectToCrmWorkspace() {
      let target =
        sffc_recruiter_post.crm_url || "https://joinsenna.com/terminal/";

      try {
        const url = new URL(target, window.location.origin);
        if (!url.searchParams.has("utm_source")) {
          url.searchParams.set("utm_source", "recruiter_post_single");
        }
        target = url.toString();
      } catch (err) {
        // If URL constructor fails, fall back to absolute path.
        if (target.charAt(0) === "/") {
          target = window.location.origin + target;
        }
      }

      window.location.href = target;
    }

    generateOutreachMessage(
      firstName,
      lastName,
      email,
      allowSmartMessage = false
    ) {
      const fullName = `${firstName} ${lastName}`.trim() || "Candidate";

      if (allowSmartMessage && this.smartMessage) {
        return this.personalizeSmartMessage(fullName, email);
      }

      const recruiterFirstName =
        this.recruiterName.split(" ")[0] || "Hiring Manager";
      const companySnippet = this.companyName ? ` at ${this.companyName}` : "";
      const keyRequirements = this.extractKeyRequirements();
      const highlights = keyRequirements
        .slice(0, 3)
        .map((req) => req.replace(/^[-•*]\s*/, "").trim());
      const highlightSentence = highlights.length
        ? `Your focus on ${this.formatReadableList(
            highlights
          )} mirrors the work I've been doing—most recently [RELEVANT ACCOMPLISHMENT OR KPI] across a similar mandate.`
        : "";

      let message = `Dear ${recruiterFirstName},\n\n`;
      message += `I was energised when I saw the ${this.jobTitle}${companySnippet} search because it fuses strategic analysis with hands-on delivery—the same blend that helped me drive [RELEVANT RESULT OR METRIC].\n\n`;

      if (highlightSentence) {
        message += `${highlightSentence}\n\n`;
      }

      message += `I'm ready to map out a crisp 30/60/90-day plan, align stakeholders fast, and extend your capacity from day one. I'm happy to share that outline or meet for 15 minutes next week to show you how I'd plug into the team.\n\n`;
      message += `Best regards,\n`;
      message += `${fullName}\n`;
      message += `${email}\n`;
      message += `LinkedIn: [Add your LinkedIn URL]`;

      return message;
    }

    personalizeSmartMessage(fullName, email) {
      const recruiterFocus = this.jobTitle || "this role";
      let personalizedMessage = this.smartMessage.trim();

      personalizedMessage = personalizedMessage.replace(
        /\[YOUR NAME\]/gi,
        fullName
      );
      personalizedMessage = personalizedMessage.replace(
        /\[SPECIFIC ROLE ASPECT\]/gi,
        recruiterFocus
      );
      personalizedMessage = personalizedMessage.replace(
        /\[YOUR EXPERIENCE\]/gi,
        "my background driving [RELEVANT RESULT]"
      );

      if (!/best regards|kind regards|sincerely/i.test(personalizedMessage)) {
        personalizedMessage += `\n\nBest regards,\n${fullName}`;
      }

      if (
        email &&
        !personalizedMessage.toLowerCase().includes(email.toLowerCase())
      ) {
        personalizedMessage += `\n${email}`;
      }

      if (!/linkedin/i.test(personalizedMessage)) {
        personalizedMessage += `\nLinkedIn: [Add your LinkedIn URL]`;
      }

      return personalizedMessage;
    }

    formatReadableList(items) {
      const clean = items.filter(Boolean);
      if (!clean.length) {
        return "";
      }
      if (clean.length === 1) {
        return clean[0];
      }
      if (clean.length === 2) {
        return `${clean[0]} and ${clean[1]}`;
      }
      return `${clean.slice(0, -1).join(", ")}, and ${clean[clean.length - 1]}`;
    }

    formatTitleCase(text) {
      if (!text) {
        return "";
      }
      return text.toLowerCase().replace(/\b\w/g, (char) => char.toUpperCase());
    }

    extractKeyRequirements() {
      const requirements = [];
      const lines = this.jdText.split("\n");

      let inRequirements = false;
      for (const line of lines) {
        if (
          line.toLowerCase().includes("requirements") ||
          line.toLowerCase().includes("qualifications")
        ) {
          inRequirements = true;
          continue;
        }
        if (inRequirements && line.trim()) {
          // Clean up the line
          let req = line.replace(/^[-•*]\s*/, "").trim();
          if (req && req.length > 10 && req.length < 200) {
            requirements.push(req);
          }
          if (requirements.length >= 5) break;
        }
        if (inRequirements && line.toLowerCase().includes(":")) {
          inRequirements = false;
        }
      }

      return requirements;
    }

    // ========================================
    // PREVIEW POPULATION
    // ========================================

    populatePreviews() {
      console.log("Populating previews from JD...");

      const keywords = this.extractKeywords();
      const skills = this.extractSkills();
      const coverPoints = this.extractCoverLetterPoints();
      const interviewQuestions = this.generateInterviewQuestions();
      const atsKeywords = this.extractAtsKeywords();

      // Populate CV Preview (document layout)
      this.renderTags(
        "#cv-skills",
        skills.concat(keywords.slice(0, 2)),
        "inst-preview-skill"
      );

      // Update keywords count badge
      const totalKeywords = keywords.length + skills.length;
      this.$container.find("#cv-keywords-count").text(totalKeywords);

      // Show first keyword inline in experience section
      if (keywords.length > 0) {
        this.$container.find("#cv-keyword-1").text(keywords[0]);
      }

      // Populate Cover Letter Points
      this.renderList("#cover-points", coverPoints);

      // Populate Interview Questions
      this.renderList("#interview-questions-list", interviewQuestions);

      // Populate ATS Keywords
      this.renderTags("#ats-keywords", atsKeywords, "inst-preview-ats-keyword");

      // Calculate ATS Match potential
      this.updateAtsMatch();

      this.primeHighlightTokens();
    }

    extractKeywords() {
      const keywords = new Set();
      const jdLower = this.jdText.toLowerCase();

      // Common industry/technical keywords to look for
      const technicalPatterns = [
        // Finance/Accounting
        /\b(financial model(?:l?ing)?|valuation|dcf|lbo|m&a|due diligence)\b/gi,
        /\b(private equity|venture capital|investment banking|asset management)\b/gi,
        /\b(portfolio management|risk management|derivatives|fixed income)\b/gi,
        /\b(cfa|acca|aca|cpa|frm)\b/gi,
        /\b(gaap|ifrs|sox|audit)\b/gi,

        // Tech
        /\b(python|java(?:script)?|sql|excel|vba|tableau|power bi)\b/gi,
        /\b(machine learning|data science|analytics|automation)\b/gi,
        /\b(bloomberg|reuters|factset|capital iq)\b/gi,

        // General Professional
        /\b(stakeholder management|client facing|presentation skills)\b/gi,
        /\b(project management|team lead(?:ership)?|cross-functional)\b/gi,
      ];

      technicalPatterns.forEach((pattern) => {
        const matches = this.jdText.match(pattern);
        if (matches) {
          matches.forEach((match) => {
            keywords.add(this.capitalizeKeyword(match.trim()));
          });
        }
      });

      // Also extract from title/role
      if (this.jobTitle) {
        const titleWords = this.jobTitle.split(/\s+/);
        titleWords.forEach((word) => {
          if (
            word.length > 3 &&
            !["the", "and", "for", "with"].includes(word.toLowerCase())
          ) {
            keywords.add(this.capitalizeKeyword(word));
          }
        });
      }

      return Array.from(keywords).slice(0, 6);
    }

    extractSkills() {
      const skills = new Set();
      const jdLower = this.jdText.toLowerCase();

      // Look for skills section
      const skillsPatterns = [
        /\b(communication skills?)\b/gi,
        /\b(analytical skills?|analysis)\b/gi,
        /\b(problem[- ]solving)\b/gi,
        /\b(attention to detail)\b/gi,
        /\b(teamwork|collaboration)\b/gi,
        /\b(leadership|management)\b/gi,
        /\b(strategic thinking)\b/gi,
        /\b(time management)\b/gi,
        /\b(negotiation)\b/gi,
        /\b(research skills?)\b/gi,
        /\b(quantitative skills?)\b/gi,
        /\b(interpersonal skills?)\b/gi,
      ];

      skillsPatterns.forEach((pattern) => {
        const matches = this.jdText.match(pattern);
        if (matches) {
          matches.forEach((match) => {
            skills.add(this.capitalizeKeyword(match.trim()));
          });
        }
      });

      // Extract experience requirements
      const expMatch = this.jdText.match(
        /(\d+)\+?\s*years?\s*(?:of\s*)?experience/i
      );
      if (expMatch) {
        skills.add(`${expMatch[1]}+ Years Experience`);
      }

      return Array.from(skills).slice(0, 5);
    }

    extractCoverLetterPoints() {
      const points = [];
      const requirements = this.extractKeyRequirements();

      // Convert requirements to cover letter talking points
      requirements.slice(0, 4).forEach((req) => {
        // Shorten long requirements
        let point = req;
        if (point.length > 60) {
          point = point.substring(0, 57) + "...";
        }
        points.push(point);
      });

      // Add role-specific point if we have a title
      if (this.jobTitle && points.length < 4) {
        points.push(`Experience relevant to ${this.jobTitle}`);
      }

      return points;
    }

    generateInterviewQuestions() {
      const questions = [];
      const requirements = this.extractKeyRequirements();

      // Generate questions from requirements
      const templates = [
        (req) =>
          `Tell me about your experience with ${this.extractKeyPhrase(req)}?`,
        (req) =>
          `How have you demonstrated ${this.extractKeyPhrase(
            req
          )} in your previous roles?`,
        (req) =>
          `Can you give an example of when you ${this.actionizeRequirement(
            req
          )}?`,
      ];

      requirements.slice(0, 3).forEach((req, idx) => {
        const template = templates[idx % templates.length];
        const question = template(req);
        if (question.length < 100) {
          questions.push(question);
        }
      });

      // Add common questions based on seniority keywords
      const jdLower = this.jdText.toLowerCase();
      if (
        jdLower.includes("senior") ||
        jdLower.includes("lead") ||
        jdLower.includes("manager")
      ) {
        questions.push(
          "Describe your leadership style and how you manage teams."
        );
      }
      if (jdLower.includes("client") || jdLower.includes("stakeholder")) {
        questions.push("How do you handle challenging client relationships?");
      }

      return questions.slice(0, 4);
    }

    generateFallbackInterviewPrep(limit = 5) {
      const fallback = [];
      const requirements = this.extractKeyRequirements();
      const roleTitle = this.jobTitle || "this role";
      const company = this.companyName || "";

      requirements.slice(0, limit).forEach((requirement) => {
        const focus = this.extractKeyPhrase(requirement);
        const action = this.actionizeRequirement(requirement);
        fallback.push({
          likely_question: `Can you share an example of ${
            focus || "delivering against this mandate"
          }?`,
          suggested_response_angle: `They want proof that you have executed on ${
            focus || "similar priorities"
          } in recent roles. Frame your answer with metrics.`,
          example_answer: `In my last role I ${
            action || "led the same initiative"
          } and delivered measurable impact (e.g., +25% efficiency). I would bring that same playbook here ${
            company ? `for ${company}` : ""
          }.`,
        });
      });

      while (fallback.length < limit) {
        fallback.push({
          likely_question: `How would you approach your first 30/60/90 days in ${roleTitle}?`,
          suggested_response_angle:
            "Outline discovery, quick wins, and scalable systems tied to the JD priorities.",
          example_answer:
            "I would begin with stakeholder mapping and quick diagnostics, deliver a fast proof point in month one, and then scale the programme with documented processes.",
        });
      }

      return fallback.slice(0, limit);
    }

    extractKeyPhrase(requirement) {
      // Extract the key skill/concept from a requirement
      let phrase = requirement
        .replace(
          /^(must have|should have|experience with|knowledge of|strong|excellent|proven)/i,
          ""
        )
        .replace(/\b(required|preferred|essential|desirable)\b/gi, "")
        .trim();

      // Limit length
      if (phrase.length > 40) {
        const words = phrase.split(" ").slice(0, 5);
        phrase = words.join(" ");
      }

      return phrase.toLowerCase();
    }

    actionizeRequirement(requirement) {
      // Convert a requirement into an action phrase
      let action = requirement
        .replace(/^(must|should|will|need to)\s+/i, "")
        .replace(/experience (with|in)/i, "worked with")
        .replace(/knowledge of/i, "used")
        .toLowerCase()
        .trim();

      if (action.length > 50) {
        action = action.substring(0, 47) + "...";
      }

      return action;
    }

    extractAtsKeywords() {
      const atsKeywords = new Set();

      // Combine keywords and skills for ATS
      const keywords = this.extractKeywords();
      const skills = this.extractSkills();

      // Take a mix of both
      keywords.slice(0, 4).forEach((k) => atsKeywords.add(k));
      skills.slice(0, 3).forEach((s) => atsKeywords.add(s));

      // Add qualifications
      const qualPatterns = [
        /\b(bachelor'?s?|master'?s?|mba|phd|degree)\b/gi,
        /\b(certified|qualification|license)\b/gi,
      ];

      qualPatterns.forEach((pattern) => {
        const matches = this.jdText.match(pattern);
        if (matches) {
          matches.slice(0, 2).forEach((match) => {
            atsKeywords.add(this.capitalizeKeyword(match.trim()));
          });
        }
      });

      return Array.from(atsKeywords).slice(0, 8);
    }

    updateAtsMatch() {
      const $atsValue = this.$container.find("#ats-match");

      // If we have CV text, calculate actual match
      if (this.cvText) {
        const atsKeywords = this.extractAtsKeywords();
        let matches = 0;
        const cvLower = this.cvText.toLowerCase();

        atsKeywords.forEach((keyword) => {
          if (cvLower.includes(keyword.toLowerCase())) {
            matches++;
          }
        });

        const score = Math.round(
          (matches / Math.max(atsKeywords.length, 1)) * 100
        );
        $atsValue.text(`${score}%`);

        if (score >= 70) {
          $atsValue.removeClass("ats-medium ats-low").addClass("ats-high");
        } else if (score >= 40) {
          $atsValue.removeClass("ats-high ats-low").addClass("ats-medium");
        } else {
          $atsValue.removeClass("ats-high ats-medium").addClass("ats-low");
        }
      } else {
        // Show potential based on JD analysis
        const keywordCount =
          this.extractKeywords().length + this.extractSkills().length;
        if (keywordCount >= 8) {
          $atsValue.text("High").addClass("ats-high");
        } else if (keywordCount >= 4) {
          $atsValue.text("Medium").addClass("ats-medium");
        } else {
          $atsValue.text("--");
        }
      }
    }

    capitalizeKeyword(str) {
      // Capitalize first letter of each word, handle acronyms
      return str.replace(/\b\w+/g, (word) => {
        // Keep acronyms uppercase
        if (word.length <= 3 && word === word.toUpperCase()) {
          return word;
        }
        return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
      });
    }

    renderTags(selector, items, className) {
      const $container = this.$container.find(selector);
      $container.empty();

      if (items.length === 0) {
        $container.append(
          '<span class="' + className + '">No keywords found</span>'
        );
        return;
      }

      items.forEach((item) => {
        $container.append(
          `<span class="${className}">${this.escapeHtml(item)}</span>`
        );
      });
    }

    renderList(selector, items) {
      const $container = this.$container.find(selector);
      $container.empty();

      if (items.length === 0) {
        $container.append("<li>Will be generated from job description</li>");
        return;
      }

      items.forEach((item) => {
        $container.append(`<li>${this.escapeHtml(item)}</li>`);
      });
    }

    primeHighlightTokens() {
      if (this.highlightTokensCache && this.highlightTokensCache.length) {
        return;
      }
      const jdKeywords = this.extractKeywords();
      this.highlightTokensCache = jdKeywords.filter(
        (token) => token && token.length > 3
      );
    }

    setHighlightTokensFromAnalysis(data) {
      const keywordAnalysis = (data && data.keyword_analysis) || {};
      const tokens = []
        .concat(keywordAnalysis.well_represented || [])
        .concat(keywordAnalysis.critical_missing || [])
        .map((token) => (typeof token === "string" ? token.trim() : ""))
        .filter((token) => token.length > 2);

      if (tokens.length) {
        this.highlightTokensCache = tokens;
      } else {
        this.primeHighlightTokens();
      }
    }

    getHighlightTokens(limit = 6) {
      if (!this.highlightTokensCache || !this.highlightTokensCache.length) {
        this.primeHighlightTokens();
      }
      return (this.highlightTokensCache || []).slice(0, limit);
    }

    decorateHighlights(text) {
      if (typeof text === "undefined" || text === null) {
        return "";
      }

      let safe = this.escapeHtml(String(text));
      const tokens = this.getHighlightTokens();
      if (!tokens.length) {
        return safe;
      }

      tokens.forEach((token) => {
        const tokenValue = token.trim();
        if (!tokenValue) {
          return;
        }
        const escapedToken = this.escapeHtml(tokenValue);
        const pattern = new RegExp(`(${this.escapeRegex(escapedToken)})`, "ig");
        safe = safe.replace(
          pattern,
          '<span class="inst-gap-highlight">$1</span>'
        );
      });

      return safe;
    }

    escapeRegex(value) {
      return value.replace(/[-/\\^$*+?.()|[\]{}]/g, "\\$&");
    }

    // ========================================
    // SIMILAR POSTS EVENTS
    // ========================================

    bindSimilarPostsEvents() {
      const self = this;

      // Initialize state
      this.selectedSimilarPosts = new Set();
      this.outreachLists = [];
      this.cvContext = "";

      // Select All checkbox
      this.$container.find("#instSelectAllSimilar").on("change", function () {
        const isChecked = $(this).is(":checked");
        self.$container
          .find(".inst-similar-post-select")
          .prop("checked", isChecked)
          .trigger("change");
      });

      // Individual post selection
      this.$container.on("change", ".inst-similar-post-select", function () {
        const postId = $(this).val();
        const $card = $(this).closest(".inst-similar-post-card");

        if ($(this).is(":checked")) {
          self.selectedSimilarPosts.add(postId);
          $card.addClass("is-selected");
        } else {
          self.selectedSimilarPosts.delete(postId);
          $card.removeClass("is-selected");
        }

        self.updateSimilarBulkBar();
      });

      // Bulk Add to List
      this.$container.find("#instBulkAddToList").on("click", function () {
        if (!self.isLoggedIn) {
          self.showMembershipModal();
          return;
        }
        self.showCreateListModal();
      });

      // Bulk Outreach
      this.$container.find("#instBulkOutreach").on("click", function () {
        if (!self.isLoggedIn) {
          self.showMembershipModal();
          return;
        }
        self.showBulkOutreachModal();
      });

      // Individual Add to List button
      this.$container.on("click", ".inst-similar-add-to-list", function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!self.isLoggedIn) {
          self.showMembershipModal();
          return;
        }

        const postId = $(this).data("post-id");
        self.selectedSimilarPosts.clear();
        self.selectedSimilarPosts.add(postId.toString());
        self.showCreateListModal();
      });

      // Load More button
      this.$container.find("#instLoadMoreSimilar").on("click", function () {
        self.loadMoreSimilarPosts($(this));
      });

      // Membership Modal events
      this.$container
        .find("#instMembershipClose, .inst-membership-overlay")
        .on("click", function () {
          self.hideMembershipModal();
        });

      // Create List Modal events
      this.$container
        .find("#instCreateListClose, .inst-create-list-overlay")
        .on("click", function () {
          self.hideCreateListModal();
        });

      this.$container.find("#instCreateListBtn").on("click", function () {
        self.createNewList();
      });

      // Bulk Outreach Modal events
      this.$container
        .find("#instBulkOutreachClose, .inst-bulk-outreach-overlay")
        .on("click", function () {
          self.hideBulkOutreachModal();
        });

      this.$container.find("#instBulkSaveCv").on("click", function () {
        self.saveCvContext();
      });

      this.$container.find("#instBulkGenerateBtn").on("click", function () {
        self.generateBulkMessages();
      });

      // Scan Request button (Full form - no posts found)
      this.$container.find("#instScanRequestBtn").on("click", function () {
        self.submitScanRequest("main");
      });

      // "Can't find what you're looking for?" trigger button
      // Set the inline form to be visible/open by default
      const $cantFindTrigger = this.$container.find("#instCantFindTrigger");
      const $cantFindWrapper = this.$container.find("#instCantFindFormWrapper");
      $cantFindTrigger.addClass("is-open");
      $cantFindWrapper.show();

      $cantFindTrigger.on("click", function () {
        const $trigger = $(this);
        const $wrapper = self.$container.find("#instCantFindFormWrapper");

        if ($wrapper.is(":visible")) {
          $wrapper.slideUp(200);
          $trigger.removeClass("is-open");
        } else {
          $wrapper.slideDown(200);
          $trigger.addClass("is-open");
        }
      });

      // Cancel button for inline form
      this.$container.find("#instScanRequestCancel").on("click", function () {
        const $wrapper = self.$container.find("#instCantFindFormWrapper");
        const $trigger = self.$container.find("#instCantFindTrigger");

        $wrapper.slideUp(200);
        $trigger.removeClass("is-open");
      });

      // Inline scan request button
      this.$container
        .find("#instScanRequestBtnInline")
        .on("click", function () {
          self.submitScanRequest("inline");
        });

      // Load existing CV context
      this.loadCvContext();
    }

    submitScanRequest(formType = "main") {
      const self = this;

      // Check if user is logged in
      if (!this.isLoggedIn) {
        this.showScanMembershipModal();
        return;
      }

      // Determine which form elements to use based on formType
      const isInline = formType === "inline";
      const suffix = isInline ? "Inline" : "";
      const roleId = isInline ? "#instScanRoleInline" : "#instScanRequestRole";
      const locationId = isInline
        ? "#instScanLocationInline"
        : "#instScanRequestLocation";
      const industryId = isInline
        ? "#instScanIndustryInline"
        : "#instScanRequestIndustry";
      const salaryId = isInline
        ? "#instScanSalaryInline"
        : "#instScanRequestSalary";
      const experienceId = isInline
        ? "#instScanExperienceInline"
        : "#instScanRequestExperience";
      const notesId = isInline
        ? "#instScanNotesInline"
        : "#instScanRequestNotes";

      const $form = this.$container.find(
        isInline ? "#instScanRequestFormInline" : "#instScanRequestForm"
      );
      const $btn = this.$container.find(
        isInline ? "#instScanRequestBtnInline" : "#instScanRequestBtn"
      );

      // Collect form data
      const role = this.$container.find(roleId).val().trim();
      const location = this.$container.find(locationId).val().trim();
      const industry = this.$container.find(industryId).val().trim();
      const salary = this.$container.find(salaryId).val().trim();
      const experience = this.$container.find(experienceId).val();
      const notes = this.$container.find(notesId).val().trim();

      // Validate required fields
      if (!role) {
        this.showToast("Please specify what role you are looking for", "error");
        this.$container.find(roleId).focus();
        return;
      }

      if (!location) {
        this.showToast("Please specify your preferred location(s)", "error");
        this.$container.find(locationId).focus();
        return;
      }

      // Save original button text
      const originalBtnHtml = $btn.html();
      $btn
        .prop("disabled", true)
        .html('<div class="inst-spinner"></div> Submitting...');

      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_request_recruiter_scan",
          nonce: sffc_recruiter_post.nonce,
          role: role,
          location: location,
          industry: industry,
          salary: salary,
          experience: experience,
          notes: notes,
          current_post_id: sffc_recruiter_post.post_id,
        },
        success: function (response) {
          if (response.success) {
            // Replace form with success message
            const successHtml = `
                            <div class="inst-scan-request-success">
                                <div class="inst-scan-request-success-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                </div>
                                <h4>Request Submitted!</h4>
                                <p>Our team will search for recruiters matching your criteria and notify you when we find matches. This usually takes 1-2 business days.</p>
                            </div>
                        `;

            if (isInline) {
              // For inline form, replace the form wrapper content
              self.$container
                .find("#instCantFindFormWrapper")
                .html(successHtml);
            } else {
              // For main form, replace the card content
              $form.html(successHtml);
            }

            self.showToast("Scan request submitted successfully!", "success");
          } else {
            self.showToast(
              response.data?.message || "Failed to submit request",
              "error"
            );
            $btn.prop("disabled", false).html(originalBtnHtml);
          }
        },
        error: function () {
          self.showToast("Network error. Please try again.", "error");
          $btn.prop("disabled", false).html(originalBtnHtml);
        },
      });
    }

    showScanMembershipModal() {
      // Show a modal explaining benefits and redirecting to membership page
      const modalHtml = `
                <div class="inst-membership-modal" id="instScanMembershipModal">
                    <div class="inst-membership-overlay"></div>
                    <div class="inst-membership-content">
                        <button type="button" class="inst-membership-close" id="instScanMembershipClose">&times;</button>

                        <div class="inst-membership-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                        </div>

                        <h3>Unlock Recruiter Scanning</h3>
                        <p>Join MENA Careers to request personalized recruiter scans and access powerful career tools</p>

                        <ul class="inst-membership-benefits">
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span>Manual recruiter search by our experts</span>
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span>Curated list of recruiters matching your criteria</span>
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span>AI-powered outreach message generation</span>
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span>Email notifications when matches are found</span>
                            </li>
                        </ul>

                        <a href="https://joinsenna.com/memberships/" class="inst-membership-btn">
                            Become a Member
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"/>
                                <polyline points="12 5 19 12 12 19"/>
                            </svg>
                        </a>

                        <p class="inst-membership-login">Already a member? <a href="${
                          sffc_recruiter_post.login_url || "/login/"
                        }">Log in</a></p>
                    </div>
                </div>
            `;

      // Remove existing modal if any
      this.$container.find("#instScanMembershipModal").remove();

      // Append and show modal
      this.$container.append(modalHtml);
      this.$container.find("#instScanMembershipModal").fadeIn(200);
      this.lockBodyScroll();

      // Bind close events
      const self = this;
      this.$container
        .find(
          "#instScanMembershipClose, #instScanMembershipModal .inst-membership-overlay"
        )
        .on("click", function () {
          self.$container
            .find("#instScanMembershipModal")
            .fadeOut(200, function () {
              $(this).remove();
            });
          self.unlockBodyScroll();
        });
    }

    updateSimilarBulkBar() {
      const count = this.selectedSimilarPosts.size;
      const $bar = this.$container.find("#instSimilarBulkBar");
      const $count = $bar.find(".inst-similar-bulk-count");

      $count.text(count);

      if (count > 0) {
        $bar.slideDown(200);
      } else {
        $bar.slideUp(200);
      }

      // Update Select All checkbox state
      const totalCheckboxes = this.$container.find(
        ".inst-similar-post-select"
      ).length;
      const $selectAll = this.$container.find("#instSelectAllSimilar");

      if (count === 0) {
        $selectAll.prop("checked", false).prop("indeterminate", false);
      } else if (count === totalCheckboxes) {
        $selectAll.prop("checked", true).prop("indeterminate", false);
      } else {
        $selectAll.prop("checked", false).prop("indeterminate", true);
      }
    }

    showMembershipModal() {
      this.$container.find("#instMembershipModal").fadeIn(200);
      this.lockBodyScroll();
    }

    hideMembershipModal() {
      this.$container.find("#instMembershipModal").fadeOut(200);
      this.unlockBodyScroll();
    }

    showCreateListModal() {
      const self = this;
      const $modal = this.$container.find("#instCreateListModal");

      // Fetch existing lists
      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_get_outreach_lists",
          nonce: sffc_recruiter_post.nonce,
        },
        success: function (response) {
          if (
            response.success &&
            response.data.lists &&
            response.data.lists.length > 0
          ) {
            self.outreachLists = response.data.lists;
            self.renderExistingLists();
          }
        },
      });

      $modal.fadeIn(200);
      this.lockBodyScroll();
    }

    hideCreateListModal() {
      this.$container.find("#instCreateListModal").fadeOut(200);
      this.unlockBodyScroll();
    }

    renderExistingLists() {
      const self = this;
      const $container = this.$container.find(".inst-existing-lists-container");
      const $section = this.$container.find("#instExistingLists");

      if (this.outreachLists.length === 0) {
        $section.hide();
        return;
      }

      let html = "";
      this.outreachLists.forEach(function (list) {
        html +=
          '<button type="button" class="inst-existing-list-btn" data-list-id="' +
          list.id +
          '">';
        html +=
          '<span class="inst-list-name">' +
          self.escapeHtml(list.name) +
          "</span>";
        html +=
          '<span class="inst-list-count">' + list.count + " recruiters</span>";
        html += "</button>";
      });

      $container.html(html);
      $section.show();

      // Bind click events
      $container.find(".inst-existing-list-btn").on("click", function () {
        const listId = $(this).data("list-id");
        self.addToExistingList(listId);
      });
    }

    createNewList() {
      const self = this;
      const listName = this.$container.find("#instNewListName").val().trim();

      if (!listName) {
        this.$container.find("#instNewListName").addClass("is-error").focus();
        return;
      }

      const $btn = this.$container.find("#instCreateListBtn");
      $btn.prop("disabled", true).text("Creating...");

      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_create_outreach_list",
          nonce: sffc_recruiter_post.nonce,
          name: listName,
          post_ids: Array.from(this.selectedSimilarPosts),
        },
        success: function (response) {
          $btn.prop("disabled", false).text("Create List");

          if (response.success) {
            self.hideCreateListModal();
            self.showToast(
              response.data.message || "List created successfully!",
              "success"
            );

            // Mark added items
            self.selectedSimilarPosts.forEach(function (postId) {
              self.$container
                .find(
                  '.inst-similar-add-to-list[data-post-id="' + postId + '"]'
                )
                .addClass("is-added")
                .html(
                  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'
                );
            });

            // Clear selection
            self.selectedSimilarPosts.clear();
            self.$container
              .find(".inst-similar-post-select")
              .prop("checked", false);
            self.$container
              .find(".inst-similar-post-card")
              .removeClass("is-selected");
            self.updateSimilarBulkBar();
          } else {
            self.showToast(
              response.data.message || "Failed to create list",
              "error"
            );
          }
        },
        error: function () {
          $btn.prop("disabled", false).text("Create List");
          self.showToast("Network error. Please try again.", "error");
        },
      });
    }

    addToExistingList(listId) {
      const self = this;

      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_add_posts_to_outreach_list",
          nonce: sffc_recruiter_post.nonce,
          list_id: listId,
          post_ids: Array.from(this.selectedSimilarPosts),
        },
        success: function (response) {
          if (response.success) {
            self.hideCreateListModal();
            self.showToast(
              response.data.message || "Added to list!",
              "success"
            );

            // Mark added items
            self.selectedSimilarPosts.forEach(function (postId) {
              self.$container
                .find(
                  '.inst-similar-add-to-list[data-post-id="' + postId + '"]'
                )
                .addClass("is-added")
                .html(
                  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'
                );
            });

            // Clear selection
            self.selectedSimilarPosts.clear();
            self.$container
              .find(".inst-similar-post-select")
              .prop("checked", false);
            self.$container
              .find(".inst-similar-post-card")
              .removeClass("is-selected");
            self.updateSimilarBulkBar();
          } else {
            self.showToast(
              response.data.message || "Failed to add to list",
              "error"
            );
          }
        },
      });
    }

    showBulkOutreachModal() {
      const self = this;
      const $modal = this.$container.find("#instBulkOutreachModal");
      const $list = this.$container.find("#instBulkOutreachList");

      // Build list of selected recruiters
      let html = "";
      this.selectedSimilarPosts.forEach(function (postId) {
        const $card = self.$container.find(
          '.inst-similar-post-card[data-post-id="' + postId + '"]'
        );
        const recruiterName = $card.data("recruiter-name") || "Recruiter";
        const recruiterCompany = $card.data("recruiter-company") || "";
        const jobTitle = $card.data("job-title") || "";

        html +=
          '<div class="inst-bulk-recruiter-item" data-post-id="' +
          postId +
          '">';
        html += '<div class="inst-bulk-recruiter-info">';
        html +=
          '<div class="inst-bulk-recruiter-avatar">' +
          self.escapeHtml(recruiterName.charAt(0)) +
          "</div>";
        html += '<div class="inst-bulk-recruiter-details">';
        html +=
          '<span class="inst-bulk-recruiter-name">' +
          self.escapeHtml(recruiterName) +
          "</span>";
        if (recruiterCompany) {
          html +=
            '<span class="inst-bulk-recruiter-company">' +
            self.escapeHtml(recruiterCompany) +
            "</span>";
        }
        if (jobTitle) {
          html +=
            '<span class="inst-bulk-recruiter-role">' +
            self.escapeHtml(jobTitle) +
            "</span>";
        }
        html += "</div>";
        html += "</div>";
        html +=
          '<div class="inst-bulk-recruiter-status"><span class="inst-status-pending">Pending</span></div>';
        html += "</div>";
      });

      $list.html(html);

      // Update total count
      this.$container
        .find("#instBulkProgressTotal")
        .text(this.selectedSimilarPosts.size);

      // Show/hide CV section based on saved context
      if (this.cvContext) {
        this.$container.find("#instBulkCvContext").val(this.cvContext);
        this.$container
          .find("#instBulkOutreachCv h4")
          .text("Your Background (saved)");
        this.$container.find("#instBulkSaveCv").hide();
      }

      $modal.fadeIn(200);
      this.lockBodyScroll();
    }

    hideBulkOutreachModal() {
      this.$container.find("#instBulkOutreachModal").fadeOut(200);
      this.$container.find("#instBulkOutreachProgress").hide();
      this.$container.find("#instBulkOutreachResults").hide();
      this.unlockBodyScroll();
    }

    loadCvContext() {
      const self = this;

      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_get_cv_context",
          nonce: sffc_recruiter_post.crm_nonce,
        },
        success: function (response) {
          if (response.success && response.data.cv_context) {
            self.cvContext = response.data.cv_context;
          }
        },
      });
    }

    saveCvContext() {
      const self = this;
      const cvText = this.$container.find("#instBulkCvContext").val().trim();

      if (!cvText) {
        return;
      }

      const $btn = this.$container.find("#instBulkSaveCv");
      $btn.prop("disabled", true).text("Saving...");

      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_crm_save_cv_context",
          nonce: sffc_recruiter_post.crm_nonce,
          cv_text: cvText,
        },
        success: function (response) {
          if (response.success) {
            self.cvContext = cvText;
            self.$container
              .find("#instBulkOutreachCv h4")
              .text("Your Background (saved)");
            $btn.hide();
            self.showToast("CV context saved!", "success");
          } else {
            $btn.prop("disabled", false).text("Save for Future");
          }
        },
        error: function () {
          $btn.prop("disabled", false).text("Save for Future");
        },
      });
    }

    async generateBulkMessages() {
      const self = this;
      const cvContext =
        this.$container.find("#instBulkCvContext").val().trim() ||
        this.cvContext;
      const $btn = this.$container.find("#instBulkGenerateBtn");
      const $progress = this.$container.find("#instBulkOutreachProgress");
      const $results = this.$container.find("#instBulkOutreachResults");

      $btn
        .prop("disabled", true)
        .html('<div class="inst-spinner"></div> Generating...');
      $progress.show();

      const postIds = Array.from(this.selectedSimilarPosts);
      let current = 0;
      let resultsHtml = "";

      for (const postId of postIds) {
        current++;
        this.$container.find("#instBulkProgressCurrent").text(current);
        this.$container
          .find("#instBulkProgressFill")
          .css("width", (current / postIds.length) * 100 + "%");

        const $item = this.$container.find(
          '.inst-bulk-recruiter-item[data-post-id="' + postId + '"]'
        );
        $item
          .find(".inst-bulk-recruiter-status")
          .html('<span class="inst-status-generating">Generating...</span>');

        try {
          const response = await $.ajax({
            url: sffc_recruiter_post.ajax_url,
            type: "POST",
            data: {
              action: "sffc_generate_similar_outreach",
              nonce: sffc_recruiter_post.nonce,
              post_id: postId,
              cv_context: cvContext,
              current_job_title: this.jobTitle,
            },
          });

          if (response.success) {
            $item
              .find(".inst-bulk-recruiter-status")
              .html('<span class="inst-status-done">Done</span>');

            const $card = this.$container.find(
              '.inst-similar-post-card[data-post-id="' + postId + '"]'
            );
            const recruiterName = $card.data("recruiter-name") || "Recruiter";
            const recruiterEmail = $card.data("recruiter-email") || "";

            resultsHtml += '<div class="inst-bulk-result-item">';
            resultsHtml += '<div class="inst-bulk-result-header">';
            resultsHtml +=
              "<strong>" + self.escapeHtml(recruiterName) + "</strong>";
            resultsHtml += '<div class="inst-bulk-result-actions">';
            resultsHtml +=
              '<button type="button" class="inst-copy-message-btn" data-message="' +
              self.escapeHtml(response.data.message).replace(/"/g, "&quot;") +
              '">Copy</button>';
            if (recruiterEmail) {
              resultsHtml +=
                '<a href="mailto:' +
                recruiterEmail +
                "?subject=Regarding the " +
                encodeURIComponent($card.data("job-title") || "position") +
                "&body=" +
                encodeURIComponent(response.data.message) +
                '" class="inst-email-btn">Email</a>';
            }
            resultsHtml += "</div>";
            resultsHtml += "</div>";
            resultsHtml +=
              '<div class="inst-bulk-result-message">' +
              self.escapeHtml(response.data.message) +
              "</div>";
            resultsHtml += "</div>";
          } else {
            $item
              .find(".inst-bulk-recruiter-status")
              .html('<span class="inst-status-error">Failed</span>');
          }
        } catch (error) {
          $item
            .find(".inst-bulk-recruiter-status")
            .html('<span class="inst-status-error">Error</span>');
        }

        // Small delay between requests
        await new Promise((resolve) => setTimeout(resolve, 500));
      }

      $results.html(resultsHtml).show();
      $btn
        .prop("disabled", false)
        .html(
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Generate All Messages'
        );

      // Bind copy buttons
      $results.find(".inst-copy-message-btn").on("click", function () {
        const message = $(this).data("message");
        navigator.clipboard.writeText(message).then(function () {
          self.showToast("Message copied!", "success");
        });
      });
    }

    loadMoreSimilarPosts($btn) {
      const self = this;
      const currentPage = parseInt($btn.data("page")) || 1;
      const nextPage = currentPage + 1;
      const $container = this.$container.find("#instSimilarPostsRegion");
      const currentPostId = $container.data("current-post");
      const jobTitle = $container.data("job-title");

      $btn
        .prop("disabled", true)
        .html('<div class="inst-spinner"></div> Loading...');

      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_load_more_similar_posts",
          nonce: sffc_recruiter_post.nonce,
          page: nextPage,
          current_post_id: currentPostId,
          job_title: jobTitle,
        },
        success: function (response) {
          if (response.success && response.data.html) {
            self.$container
              .find("#instSimilarPostsGrid")
              .append(response.data.html);
            $btn.data("page", nextPage);

            if (response.data.has_more) {
              const remaining = response.data.total - nextPage * 9;
              $btn
                .prop("disabled", false)
                .html(
                  '<span>Load More Roles</span><span class="inst-similar-load-count">(' +
                    remaining +
                    " more)</span>"
                );
            } else {
              $btn.parent().remove();
            }
          } else {
            $btn.parent().remove();
          }
        },
        error: function () {
          $btn
            .prop("disabled", false)
            .html(
              '<span>Load More Roles</span><span class="inst-similar-load-count">(try again)</span>'
            );
        },
      });
    }

    showToast(message, type) {
      const $toast = $(
        '<div class="inst-toast inst-toast--' + type + '">' + message + "</div>"
      );
      $("body").append($toast);

      setTimeout(function () {
        $toast.addClass("is-visible");
      }, 10);

      setTimeout(function () {
        $toast.removeClass("is-visible");
        setTimeout(function () {
          $toast.remove();
        }, 300);
      }, 3000);
    }

    escapeHtml(str) {
      const div = document.createElement("div");
      div.textContent = str;
      return div.innerHTML;
    }

    stripHtml(html) {
      if (!html) {
        return "";
      }
      const div = document.createElement("div");
      div.innerHTML = html;
      return div.textContent || div.innerText || "";
    }

    trackApplication(firstName, lastName, email) {
      // Send AJAX to track application
      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_track_application",
          nonce: sffc_recruiter_post.nonce,
          post_id: this.postId,
          first_name: firstName,
          last_name: lastName,
          email: email,
          materials: Array.from(this.selectedUpsells),
        },
        success: function (response) {
          console.log("Application tracked:", response);
        },
        error: function (xhr, status, error) {
          console.error("Failed to track application:", error);
        },
      });
    }

    saveApplicant(firstName, lastName, email) {
      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_save_applicant",
          nonce: sffc_recruiter_post.nonce,
          post_id: this.postId,
          crm_post_id: sffc_recruiter_post.crm_post_id || "",
          recruiter_id: sffc_recruiter_post.recruiter_id || "",
          job_title: this.jobTitle,
          company_name: this.companyName,
          first_name: firstName,
          last_name: lastName,
          email: email,
          materials: Array.from(this.selectedUpsells || []),
          source: sffc_recruiter_post.source || "wp",
        },
      });
    }

    validateEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
    }

    // ========================================
    // OUTREACH LISTS INTEGRATION
    // ========================================

    loadOutreachListsForModal() {
      const self = this;

      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_crm_get_outreach_lists",
          nonce: sffc_recruiter_post.crm_nonce,
        },
        success: function (response) {
          if (response.success) {
            self.showAddToOutreachListModal(response.data.lists || []);
          } else {
            self.showToast(
              response.data?.message || "Failed to load lists",
              "error"
            );
          }
        },
        error: function () {
          self.showToast("Failed to load outreach lists", "error");
        },
      });
    }

    showAddToOutreachListModal(lists) {
      const self = this;
      const selected = this.selectedRecruitersForList || [];

      // Build modal HTML
      let html =
        '<div class="sffc-crm-modal-overlay" id="addToOutreachListModal">';
      html += '    <div class="sffc-crm-modal">';
      html += '        <div class="sffc-crm-modal-header">';
      html +=
        "            <h3>Add " +
        selected.length +
        " Recruiters to Outreach List</h3>";
      html +=
        '            <button class="sffc-crm-modal-close">&times;</button>';
      html += "        </div>";
      html += '        <div class="sffc-crm-modal-body">';

      // Option 1: Create New List
      html += '            <div class="sffc-crm-list-option">';
      html += '                <label class="sffc-crm-radio-label">';
      html +=
        '                    <input type="radio" name="list_action" value="new" checked>';
      html += "                    <span>Create New List</span>";
      html += "                </label>";
      html +=
        '                <div class="sffc-crm-new-list-fields" style="margin-top: 12px;">';
      html +=
        '                    <input type="text" id="newListName" class="sffc-crm-input" placeholder="List name (e.g., Top Tier Recruiters)" required>';
      html +=
        '                    <textarea id="newListDescription" class="sffc-crm-input" placeholder="Description (optional)" rows="2"></textarea>';
      html += "                </div>";
      html += "            </div>";

      // Option 2: Add to Existing List
      if (lists.length > 0) {
        html +=
          '            <div class="sffc-crm-list-option" style="margin-top: 16px;">';
        html += '                <label class="sffc-crm-radio-label">';
        html +=
          '                    <input type="radio" name="list_action" value="existing">';
        html += "                    <span>Add to Existing List</span>";
        html += "                </label>";
        html +=
          '                <div class="sffc-crm-existing-list-fields" style="margin-top: 12px; display: none;">';
        html +=
          '                    <select id="existingListId" class="sffc-crm-input">';
        html +=
          '                        <option value="">Select a list...</option>';
        lists.forEach(function (list) {
          html +=
            '                    <option value="' +
            list.id +
            '">' +
            self.escapeHtml(list.list_name);
          html += " (" + list.recruiter_count + " recruiters)</option>";
        });
        html += "                    </select>";
        html += "                </div>";
        html += "            </div>";
      }

      html += "        </div>";
      html += '        <div class="sffc-crm-modal-footer">';
      html +=
        '            <button class="sffc-crm-btn sffc-crm-btn-secondary" id="cancelAddToList">Cancel</button>';
      html +=
        '            <button class="sffc-crm-btn sffc-crm-btn-primary" id="confirmAddToList">Add to List</button>';
      html += "        </div>";
      html += "    </div>";
      html += "</div>";

      // Append to body
      $("body").append(html);

      // Bind events
      const $modal = $("#addToOutreachListModal");

      // Radio button toggle
      $modal.find('input[name="list_action"]').on("change", function () {
        if ($(this).val() === "new") {
          $modal.find(".sffc-crm-new-list-fields").show();
          $modal.find(".sffc-crm-existing-list-fields").hide();
        } else {
          $modal.find(".sffc-crm-new-list-fields").hide();
          $modal.find(".sffc-crm-existing-list-fields").show();
        }
      });

      // Close handlers
      $modal
        .find(".sffc-crm-modal-close, #cancelAddToList")
        .on("click", function () {
          $modal.remove();
        });

      $modal.find(".sffc-crm-modal-overlay").on("click", function (e) {
        if (e.target === this) {
          $modal.remove();
        }
      });

      // Confirm handler
      $modal.find("#confirmAddToList").on("click", function () {
        self.processAddToOutreachList($modal);
      });
    }

    processAddToOutreachList($modal) {
      const self = this;
      const action = $modal.find('input[name="list_action"]:checked').val();

      if (action === "new") {
        const listName = $modal.find("#newListName").val().trim();
        const description = $modal.find("#newListDescription").val().trim();

        if (!listName) {
          this.showToast("Please enter a list name", "error");
          return;
        }

        // Create new list first
        $.ajax({
          url: sffc_recruiter_post.ajax_url,
          type: "POST",
          data: {
            action: "sffc_crm_create_outreach_list",
            nonce: sffc_recruiter_post.crm_nonce,
            list_name: listName,
            description: description,
          },
          success: function (response) {
            if (response.success) {
              const listId = response.data.list_id;
              self.addRecruitersToList(listId, $modal);
            } else {
              self.showToast(
                response.data?.message || "Failed to create list",
                "error"
              );
            }
          },
          error: function () {
            self.showToast("Failed to create list", "error");
          },
        });
      } else {
        const listId = $modal.find("#existingListId").val();

        if (!listId) {
          this.showToast("Please select a list", "error");
          return;
        }

        this.addRecruitersToList(parseInt(listId), $modal);
      }
    }

    addRecruitersToList(listId, $modal) {
      const self = this;
      const selected = this.selectedRecruitersForList || [];

      if (!selected.length) {
        this.showToast("No recruiters selected", "error");
        return;
      }

      const recruiterIds = selected.map((r) => r.recruiter_id);

      $.ajax({
        url: sffc_recruiter_post.ajax_url,
        type: "POST",
        data: {
          action: "sffc_crm_add_to_outreach_list",
          nonce: sffc_recruiter_post.crm_nonce,
          list_id: listId,
          recruiter_ids: recruiterIds,
        },
        success: function (response) {
          if (response.success) {
            $modal.remove();
            self.showToast(selected.length + " recruiters added to list");

            // Optionally uncheck the checkboxes
            self.$outreachCheckboxes.filter(":checked").prop("checked", false);
            self.updateOutreachSelection();
          } else {
            self.showToast(
              response.data?.message || "Failed to add recruiters",
              "error"
            );
          }
        },
        error: function () {
          self.showToast("Failed to add recruiters to list", "error");
        },
      });
    }

    lockBodyScroll() {
      if (!this.$body || !this.$body.length) {
        this.$body = $("body");
      }
      this.bodyLockCount = (this.bodyLockCount || 0) + 1;
      this.$body.addClass("inst-modal-open modal-open");
    }

    unlockBodyScroll() {
      if (!this.$body || !this.$body.length) {
        this.$body = $("body");
      }
      this.bodyLockCount = Math.max((this.bodyLockCount || 0) - 1, 0);
      if (this.bodyLockCount === 0) {
        this.$body.removeClass("inst-modal-open modal-open");
      }
    }

    bindDetailsModalEvents() {
      if (!this.$detailsModal.length) {
        return;
      }
      const self = this;
      this.$detailsClose.on("click", function () {
        self.hideMissingDetailsModal();
      });
      this.$detailsOverlay.on("click", function () {
        self.hideMissingDetailsModal();
      });
      this.$detailsUpdateBtn.on("click", function () {
        self.hideMissingDetailsModal();
        self.switchTab("express-interest");
        self.scrollToExpressHeader();
        if (self.$firstName.length) {
          self.$firstName.trigger("focus");
        }
      });
    }

    showMissingDetailsModal(fields) {
      if (!this.$detailsModal.length) {
        return;
      }
      if (this.$detailsList && this.$detailsList.length) {
        this.$detailsList.empty();
        fields.forEach((field) => {
          this.$detailsList.append(`<li>${this.escapeHtml(field)}</li>`);
        });
      }
      this.$detailsModal.show();
      this.lockBodyScroll();
    }

    hideMissingDetailsModal() {
      if (!this.$detailsModal.length) {
        return;
      }
      this.$detailsModal.hide();
      this.unlockBodyScroll();
    }

    bindReadyModalEvents() {
      if (!this.$readyModal.length) {
        return;
      }
      const self = this;
      this.$readyClose.on("click", function () {
        self.hideReadyModal();
      });
      this.$readyOverlay.on("click", function () {
        self.hideReadyModal();
      });
      this.$readyJoinBtn.on("click", function () {
        window.open(self.membershipUrl, "_blank");
      });
    }

    bindEmailPreviewEvents() {
      if (!this.$emailPreviewModal.length) {
        return;
      }
      const closePreview = () => this.hideEmailPreviewModal();
      this.$emailPreviewClose.on("click", closePreview);
      if (this.$emailPreviewOverlay.length) {
        this.$emailPreviewOverlay.on("click", closePreview);
      }
      this.$emailPreviewContinue.on("click", () =>
        this.hideEmailPreviewModal()
      );
      if (
        this.$emailPreviewCopySubject &&
        this.$emailPreviewCopySubject.length
      ) {
        this.$emailPreviewCopySubject.on("click", (e) => {
          e.preventDefault();
          if (this.emailPreviewData) {
            this.copyTextContent(
              this.emailPreviewData.subject || "",
              "Subject copied"
            );
          }
        });
      }
      this.$emailPreviewCopyBody.on("click", (e) => {
        e.preventDefault();
        if (this.emailPreviewData) {
          this.copyTextContent(
            this.emailPreviewData.body || "",
            "Message copied"
          );
        }
      });
    }

    bindAvatarFallbacks() {
      if (avatarFallbackBound) {
        return;
      }
      avatarFallbackBound = true;
      const self = this;
      $(document).on("error", ".inst-recruiter-avatar img", function () {
        const $img = $(this);
        const $avatar = $img.closest(".inst-recruiter-avatar");
        if (!$avatar.length) {
          return;
        }
        const rawInitial = ($avatar.data("avatarInitial") || "")
          .toString()
          .trim();
        const fallbackInitial =
          rawInitial || ($img.attr("alt") || "").trim().charAt(0) || "R";
        $img.remove();
        $avatar.removeClass("inst-recruiter-avatar--has-image");
        if (!$avatar.find("span").length) {
          $avatar.append(
            `<span>${self.escapeHtml(fallbackInitial.toUpperCase())}</span>`
          );
        }
      });

      $(".inst-recruiter-avatar img").each(function () {
        if (
          this.complete &&
          (typeof this.naturalWidth === "undefined" || this.naturalWidth === 0)
        ) {
          $(this).trigger("error");
        }
      });
    }

    showReadyModal() {
      if (!this.$readyModal.length) {
        return;
      }
      this.$readyModal.show();
      this.lockBodyScroll();
    }

    showEmailPreviewModal(data = {}) {
      if (!this.$emailPreviewModal.length) {
        return;
      }
      const subject = data.subject || this.buildEmailSubject();
      const body = data.body || "";
      this.emailPreviewData = {
        subject,
        body,
        recipient: data.recipient || this.recruiterEmail || "",
      };
      if (this.$emailPreviewSubject.length) {
        this.$emailPreviewSubject.text(subject);
      }
      if (this.$emailPreviewBody.length) {
        const safeBody = this.escapeHtml(body).replace(/\n/g, "<br>");
        this.$emailPreviewBody.html(
          safeBody || "<p><em>No message content generated.</em></p>"
        );
      }
      this.$emailPreviewModal.show();
      this.lockBodyScroll();
    }

    hideEmailPreviewModal() {
      if (this.$emailPreviewModal && this.$emailPreviewModal.length) {
        this.$emailPreviewModal.hide();
        this.unlockBodyScroll();
      }
    }

    hideReadyModal() {
      if (!this.$readyModal.length) {
        return;
      }
      if (this.readyModalTimer) {
        clearTimeout(this.readyModalTimer);
        this.readyModalTimer = null;
      }
      this.$readyModal.hide();
      this.unlockBodyScroll();
    }

    bindExpertModalEvents() {
      if (!this.$speakExpertBtn.length) {
        return;
      }
      this.$speakExpertBtn.on("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.switchTab("express-interest");
        this.scrollToExpressHeader();
      });
    }

    escapeHtml(text) {
      const map = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      };
      return text.replace(/[&<>"']/g, function (m) {
        return map[m];
      });
    }
  }

  // Initialize on document ready
  $(document).ready(function () {
    const $containers = $(".inst-express-interest-flow");

    $containers.each(function () {
      new ApplicationPackController(this);
    });
  });
})(jQuery);
