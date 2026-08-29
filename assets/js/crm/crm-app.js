/**
 * MENA Careers CRM - Main JavaScript
 */

(function($) {
    'use strict';

    // CRM Application
    window.SennaCRM = {
        config: {},
        currentTab: 'all-roles',
        activeModal: null,
        outreachListCache: null,
        inlineListDropdown: null,
        currentPage: 1,
        isLoading: false,
        emailAccounts: [],
        renderedPostCount: 0,
        recruiterIntroCache: [],
        recruiterIntroStatuses: {},
        smartApplyRequestCache: [],
        smartApplyStatusLabels: {},
        smartApplyJobMap: {},
        smartApplyMapRequested: false,
        smartMessageState: null,
        recruiterOpeningsState: null,
        membershipOnlySelectors: '.sffc-crm-match-introduce-btn, .sffc-crm-match-message-btn, .sffc-crm-app-toolkit-btn',

        // Phase 2: State management
        recruitersState: {
            page: 1,
            filters: {},
            sort: 'saved_at',
            sortDir: 'desc',
            tags: []
        },
        userTags: [],
        cvState: {
            items: [],
            isLoading: false,
            loaded: false,
            selectedId: null
        },

        gapAnalyzer: {
            $park: null,
            $shell: null,
            $component: null
        },
        gapAnalyzerPendingContext: null,

        // Smart outreach state
        smartOutreachQueue: [],
        smartOutreachIndex: 0,
        smartOutreachResults: [],
        introComposerState: null,
        introMessageTemplates: (function() {
            var groups = [
                {
                    tags: ['modeling', 'valuation'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nI noticed the {jobTitle}{companyClause} leans heavily on LBO/DCF rebuilds, sensitivities, and valuation bridge work. {matchSentence}',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} calls for someone who lives inside Excel — rebuilding cash flow waterfalls, covenant models, and roll-forward analysis. That\'s been my day-to-day.',
                        'Hi {recruiterFirst},\n\nI spend my time linking operating drivers to valuation outcomes, building IC-ready models with layered sensitivities. The remit described for the {jobTitle}{companyClause} is exactly that.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} expects rigorous modeling, scenario design, and variance unpacking. That\'s precisely how I support our deal teams.',
                        'Hi {recruiterFirst},\n\nAdvanced Excel, multi-tab models, and valuation memo prep are where I\'m most comfortable. The {jobTitle}{companyClause} mirrors that setup.',
                        'Hi {recruiterFirst},\n\nI saw the {jobTitle}{companyClause} references modeling complex capital structures and linking them to KPIs. That overlap encouraged me to reach out.',
                        'Hi {recruiterFirst},\n\nBuilding exit scenarios, refining WACC assumptions, and translating growth cases into valuation outputs is exactly what the {jobTitle}{companyClause} needs.',
                        'Hi {recruiterFirst},\n\nI thrive on model audits, error-checking complex formulas, and ensuring IC packs hold up under scrutiny. The {jobTitle}{companyClause} emphasizes those disciplines.',
                        'Hi {recruiterFirst},\n\nMy days are spent flexing models across base/bull/bear cases, rebuilding precedent analyses, and documenting assumptions. That\'s the core of the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} mentions bridge analysis, accretion/dilution math, and transaction comps — exactly the technical work I gravitate toward.',
                        'Hi {recruiterFirst},\n\nI enjoy roles like the {jobTitle}{companyClause} that require meticulous model architecture, scenario flexibility, and clear documentation for investment committees.'
                    ]
                },
                {
                    tags: ['deals', 'execution'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} highlights full deal-cycle support, from diligence to SPA review. That matches how I operate alongside investment teams.',
                        'Hi {recruiterFirst},\n\nI gravitate to roles like the {jobTitle}{companyClause} because they combine deal execution, memo drafting, and closing coordination — all core parts of my remit.',
                        'Hi {recruiterFirst},\n\nMy current seat has me managing diligence trackers, prepping IC readouts, and coordinating closing workstreams. The {jobTitle}{companyClause} sounds identical.',
                        'Hi {recruiterFirst},\n\nI noticed the {jobTitle}{companyClause} expects someone comfortable with SPA commentary, lender decks, and post-close integration. That\'s where I\'m most hands-on.',
                        'Hi {recruiterFirst},\n\nGiven the {jobTitle}{companyClause} blends underwriting, documentation, and deal logistics, I thought it made sense to raise my hand.',
                        'Hi {recruiterFirst},\n\nI enjoy shepherding transactions from teaser to close — coordinating advisors, refining memos, and owning the detail. The {jobTitle}{companyClause} promises exactly that.',
                        'Hi {recruiterFirst},\n\nI thrive when I can orchestrate deal timelines, manage work streams, and keep the team aligned through signing. The {jobTitle}{companyClause} is built for that.',
                        'Hi {recruiterFirst},\n\nFrom red-flag call prep to funding wire coordination, I\'ve built muscle around transaction rhythm. The {jobTitle}{companyClause} promises that same cadence.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} references Q&A log management, vendor liaison, and closing checklist ownership — all deliverables I run today.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} values someone who sweats the execution details while keeping strategic sight lines. That\'s exactly my approach.'
                    ]
                },
                {
                    tags: ['portfolio', 'operations'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} has the kind of portfolio operating exposure I\'m targeting — operator calls, KPI reviews, and value-creation tracking.',
                        'Hi {recruiterFirst},\n\nI\'m most engaged when I\'m bridging investor expectations with operator execution. The {jobTitle}{companyClause} sounds like that perfect intersection.',
                        'Hi {recruiterFirst},\n\nI\'ve been partnering with management teams on dashboards, cadence, and accountability. That reads like the playbook for the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nWhat stood out in the {jobTitle}{companyClause} is the hands-on work with portfolio leadership — something I\'ve been doing across our companies.',
                        'Hi {recruiterFirst},\n\nLean portfolio teams suit me because I can own the analytics while staying close to execution. The {jobTitle}{companyClause} promises that balance.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} expects someone to weave data into operating rhythms. That\'s exactly how I support our CEOs.',
                        'Hi {recruiterFirst},\n\nI\'ve been running board prep, tracking plan-to-actual gaps, and embedding metrics into operator routines. The {jobTitle}{companyClause} sounds like a continuation.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} mentions value creation initiatives and management partnership. That\'s where I spend most of my energy across our portfolio.',
                        'Hi {recruiterFirst},\n\nI enjoy building operating infrastructure — KPI frameworks, reporting cadences, and leadership engagement. The {jobTitle}{companyClause} is structured around that.',
                        'Hi {recruiterFirst},\n\nMy favorite part of portfolio work is being a trusted thought partner to CEOs and CFOs. The {jobTitle}{companyClause} promises exactly that dynamic.'
                    ]
                },
                {
                    tags: ['investor_relations', 'reporting'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} emphasizes LP reporting, quarterly letters, and fundraising support. That mirrors my investor-relations responsibilities.',
                        'Hi {recruiterFirst},\n\nI\'m used to turning analytics into crisp LP-ready narratives — everything from data rooms to AGM decks. The {jobTitle}{companyClause} sounds like that remit.',
                        'Hi {recruiterFirst},\n\nMy current role blends portfolio analytics with investor comms, ensuring LPs see the signal quickly. I saw similar expectations in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} mentions liaising with fundraising, compliance, and reporting. That cross-functional mix is what I\'m looking for next.',
                        'Hi {recruiterFirst},\n\nI enjoy reworking reporting packs, enhancing data rooms, and prepping GP updates. The {jobTitle}{companyClause} reads like a natural continuation.',
                        'Hi {recruiterFirst},\n\nLP diligence, co-invest decks, and performance narratives are my wheelhouse. The {jobTitle}{companyClause} is tailored for that skill set.',
                        'Hi {recruiterFirst},\n\nI thrive when I can translate complex portfolio data into investor-friendly stories. The {jobTitle}{companyClause} is structured around that skill.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} references ILPA compliance, capital call management, and LP query handling — exactly my current deliverables.',
                        'Hi {recruiterFirst},\n\nI\'ve been orchestrating annual meetings, refining quarterly letters, and managing LP onboarding. The {jobTitle}{companyClause} describes identical work.',
                        'Hi {recruiterFirst},\n\nWhat excites me about the {jobTitle}{companyClause} is the opportunity to own both the numbers and the narrative for sophisticated investors.'
                    ]
                },
                {
                    tags: ['accounting', 'acca'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nI noticed the {jobTitle}{companyClause} prefers ACCA/ACA/CIMA profiles with strong reconciliation discipline. That\'s exactly my background.',
                        'Hi {recruiterFirst},\n\nI\'m a qualified accountant who enjoys tightening close processes, ledger integrity, and control narratives — all highlighted in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nMulti-entity consolidations, IFRS/GAAP reconciliations, and control remediation are where I add value. I saw that theme in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nFrom statutory filings to audit liaison, I\'ve owned the full controllership cycle. The {jobTitle}{companyClause} sounds similar.',
                        'Hi {recruiterFirst},\n\nMy toolkit spans balance-sheet substantiation, process mapping, and control uplift — the same language used in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nI wanted to flag my profile because the {jobTitle}{companyClause} stresses reconciliations, controls, and audit readiness — the areas I run every quarter.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} highlights month-end discipline, intercompany eliminations, and technical memos — exactly my daily cadence.',
                        'Hi {recruiterFirst},\n\nI thrive when I can bring order to complex accounting structures. The {jobTitle}{companyClause} promises that challenge.',
                        'Hi {recruiterFirst},\n\nFrom technical accounting research to system implementations, I\'ve built the infrastructure for clean closes. The {jobTitle}{companyClause} requires that foundation.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} needs someone who pairs technical rigor with practical process improvement. That\'s my strength.'
                    ]
                },
                {
                    tags: ['automation', 'data'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nI saw references to BI automation, SQL/Python workflows, and dashboard improvements in the {jobTitle}{companyClause}. That\'s where I focus.',
                        'Hi {recruiterFirst},\n\nI partner with teams to automate reporting in Power BI/Tableau, reduce manual steps, and surface insights faster. The {jobTitle}{companyClause} mirrors that.',
                        'Hi {recruiterFirst},\n\nBringing structure to messy data sets, scripting checks, and pushing clean outputs is what I enjoy. The {jobTitle}{companyClause} is built for that skill set.',
                        'Hi {recruiterFirst},\n\nI noticed the {jobTitle}{companyClause} mentions KPI automation and data quality. Those are exactly the initiatives I\'ve led across functions.',
                        'Hi {recruiterFirst},\n\nI\'d love to support the {jobTitle}{companyClause} by pairing analytics with engineering-lite automation — something I\'ve done with SQL/Python in my current seat.',
                        'Hi {recruiterFirst},\n\nBuilding self-serve dashboards, QA\'ing pipelines, and translating requirements is my sweet spot. The {jobTitle}{companyClause} fits perfectly.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} highlights ETL optimization and data governance — exactly the infrastructure work I\'ve been driving.',
                        'Hi {recruiterFirst},\n\nI thrive when I can eliminate manual reconciliation through smart automation. The {jobTitle}{companyClause} is designed for that mindset.',
                        'Hi {recruiterFirst},\n\nFrom API integrations to scheduled reporting scripts, I build systems that reduce manual reporting load for finance teams. The {jobTitle}{companyClause} values that approach.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} needs someone who bridges finance and data engineering. That\'s exactly my profile.'
                    ]
                },
                {
                    tags: ['markets', 'research'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nWith Bloomberg, Capital IQ, and FactSet open all day, I synthesize macro trends for decision-makers. The {jobTitle}{companyClause} references the same toolkit.',
                        'Hi {recruiterFirst},\n\nI\'m a CFA track candidate who thrives on market/macro research, writing insights for the desk. The {jobTitle}{companyClause} resonated with that.',
                        'Hi {recruiterFirst},\n\nMonitoring spreads, building relative-value views, and briefing leadership are my favorite deliverables. That sounds like the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} asks for someone to marry data with judgment in the public/alternatives space — exactly where I sit.',
                        'Hi {recruiterFirst},\n\nI\'m comfortable toggling between macro dashboards, channel checks, and thematic write-ups. The {jobTitle}{companyClause} looks identical.',
                        'Hi {recruiterFirst},\n\nI\'d love to plug into the {jobTitle}{companyClause}, especially given the emphasis on research briefings and market color distribution.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} highlights sector coverage, earnings analysis, and PM support — exactly the research workflow I run today.',
                        'Hi {recruiterFirst},\n\nI thrive on distilling complex market dynamics into clear investment implications. The {jobTitle}{companyClause} is designed for that skill.',
                        'Hi {recruiterFirst},\n\nFrom thematic research to daily market commentary, I\'ve built the rhythms that inform portfolio decisions. The {jobTitle}{companyClause} mirrors that cadence.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} needs someone who can balance quantitative rigor with qualitative insight. That\'s my research approach.'
                    ]
                },
                {
                    tags: ['credit', 'debt'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} stresses credit underwriting, term-sheet negotiation, and portfolio surveillance. That\'s my playground.',
                        'Hi {recruiterFirst},\n\nI\'m deep in private credit — structuring facilities, modeling downside cases, and tracking covenants. The {jobTitle}{companyClause} mirrors that.',
                        'Hi {recruiterFirst},\n\nLeveraged loans, cov-lite dynamics, and workout planning are where I\'ve built experience. The {jobTitle}{companyClause} calls for the same.',
                        'Hi {recruiterFirst},\n\nI spotted references to underwriting memos and sponsor relationships in the {jobTitle}{companyClause}. That\'s exactly how I spend my weeks.',
                        'Hi {recruiterFirst},\n\nFrom credit committee prep to covenant resets, I\'m used to managing detail. I saw that expectation in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nI\'d love to plug into the {jobTitle}{companyClause} given its focus on debt structuring, monitoring, and asset management.',
                        'Hi {recruiterFirst},\n\nI enjoy the mix of initial underwriting, ongoing surveillance, and restructuring dialogue. The {jobTitle}{companyClause} spans all three.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} mentions credit modeling, covenant tracking, and borrower engagement — the core workflow I run today.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} values people who can balance risk discipline with commercial flexibility. That\'s my approach to credit.',
                        'Hi {recruiterFirst},\n\nFrom EBITDA adjustments to cash-flow waterfalls, I build the analytics that inform credit decisions. The {jobTitle}{companyClause} requires that same rigor.'
                    ]
                },
                {
                    tags: ['infrastructure', 'project_finance'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nPPP concessions, toll-road models, and availability-based projects are my specialty. The {jobTitle}{companyClause} mentions all of those.',
                        'Hi {recruiterFirst},\n\nI\'ve been modeling renewable assets, revisiting project IRRs, and negotiating lender cases. That\'s the flavor of the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} references project finance disciplines like debt sculpting and DSCR monitoring — areas I run point on.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} values people who can balance concession obligations with commercial levers. That\'s my current remit.',
                        'Hi {recruiterFirst},\n\nMy background spans greenfield bids, refinancing packages, and government stakeholder work — the same themes in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nInfrastructure deals are most exciting when you marry engineering realities with Excel logic. The {jobTitle}{companyClause} promises that challenge.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} highlights construction-phase monitoring, operational handover, and lender reporting — exactly my project-finance workflow.',
                        'Hi {recruiterFirst},\n\nI thrive on modeling long-dated cash flows with complex regulatory overlays. The {jobTitle}{companyClause} is built for that complexity.',
                        'Hi {recruiterFirst},\n\nFrom bid-phase economics to post-COD refinancing, I\'ve worked the full infrastructure life cycle. The {jobTitle}{companyClause} spans that same arc.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} needs someone who can bridge technical feasibility with financial structuring. That\'s exactly my background.'
                    ]
                },
                {
                    tags: ['venture', 'growth'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nSeed-to-Series C diligence, founder referencing, and cohort analysis are my wheelhouse. I saw that in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nI\'m energized by roles like the {jobTitle}{companyClause} that blend market mapping, product teardown, and growth-unit economics.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} looks perfect for someone used to weekly operator syncs, funnel analytics, and board exposure. That\'s me.',
                        'Hi {recruiterFirst},\n\nI noticed mentions of PLG metrics, burn runway, and stakeholder updates. That\'s exactly what I handle for our venture-backed companies.',
                        'Hi {recruiterFirst},\n\nI love roles that keep you close to founders, decks, and demos — precisely what the {jobTitle}{companyClause} describes.',
                        'Hi {recruiterFirst},\n\nGrowth equity is most fun when you balance product intuition with data. The {jobTitle}{companyClause} promises that mix.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} combines sourcing, diligence, and portfolio engagement — the full venture cycle I\'m targeting.',
                        'Hi {recruiterFirst},\n\nI thrive on rapid pattern recognition across startups, translating product momentum into investment theses. The {jobTitle}{companyClause} is designed for that.',
                        'Hi {recruiterFirst},\n\nFrom CAC payback to NRR analysis, I\'ve been building the dashboards that inform our growth bets. The {jobTitle}{companyClause} reflects that same rigor.',
                        'Hi {recruiterFirst},\n\nI\'m drawn to the {jobTitle}{companyClause} because it blends founder engagement with analytical depth — exactly where I add value to venture teams.'
                    ]
                },
                {
                    tags: ['real_estate'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nI specialize in underwriting real estate deals, reforecasting leasing assumptions, and producing Argus-to-Excel bridges. The {jobTitle}{companyClause} mirrors that.',
                        'Hi {recruiterFirst},\n\nAsset management, rent roll analytics, and capex planning fill my calendar — the same responsibilities listed for the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nI noticed the {jobTitle}{companyClause} references acquisitions, refinancing, and RE operations. That\'s the life cycle I manage today.',
                        'Hi {recruiterFirst},\n\nI\'ve been working across multifamily, office, and logistics portfolios, marrying ground data with analyst packs. Sounds like the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nI\'m comfortable toggling between Argus, Excel, and stakeholder presentations. The {jobTitle}{companyClause} expects that versatility.',
                        'Hi {recruiterFirst},\n\nLeasing sensitivity, debt coverage, and valuation writes are my bread and butter — all highlighted in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} combines acquisition underwriting with portfolio analytics — exactly the dual mandate I\'m running today.',
                        'Hi {recruiterFirst},\n\nI thrive on roles where I can build cash-flow models, track NOI drivers, and support investment decisions. The {jobTitle}{companyClause} is built for that.',
                        'Hi {recruiterFirst},\n\nFrom market comps to waterfall returns, I\'ve built the modeling infrastructure that supports our CRE investments. The {jobTitle}{companyClause} requires that depth.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} values people who understand both the property fundamentals and the financial structuring. That\'s my lane.'
                    ]
                },
                {
                    tags: ['treasury', 'liquidity'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nI\'ve been running liquidity ladders, cash pooling, and hedging programs. The {jobTitle}{companyClause} highlights those very themes.',
                        'Hi {recruiterFirst},\n\nTreasury roles like the {jobTitle}{companyClause} excite me because they mix analytics with direct capital deployment decisions.',
                        'Hi {recruiterFirst},\n\nI noticed references to short-term funding, FX risk, and covenant management. That\'s exactly what I\'ve been handling.',
                        'Hi {recruiterFirst},\n\nI enjoy building liquidity dashboards for CFOs, optimizing working capital, and liaising with banking partners. All of that shows up in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nCash forecasting, hedging strategies, and capital market interfaces are what I do daily. The {jobTitle}{companyClause} sounds like the perfect fit.',
                        'Hi {recruiterFirst},\n\nManaging liquidity is most interesting when you\'re close to the numbers and the leadership discussions. The {jobTitle}{companyClause} promises both.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} emphasizes debt facility management, bank relationship coordination, and daily cash positioning — exactly my treasury remit.',
                        'Hi {recruiterFirst},\n\nI thrive when I can optimize funding costs while maintaining liquidity buffers. The {jobTitle}{companyClause} is structured for that balance.',
                        'Hi {recruiterFirst},\n\nFrom revolver draws to FX hedging execution, I\'ve built the treasury operations that support corporate finance. The {jobTitle}{companyClause} requires that experience.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} needs someone who bridges cash management with strategic capital decisions. That\'s my lane.'
                    ]
                },
                {
                    tags: ['risk', 'compliance'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} calls for someone to strengthen controls, policies, and risk frameworks. That\'s what I\'ve been delivering.',
                        'Hi {recruiterFirst},\n\nI\'m familiar with SOX, ICFR, and policy rollouts — all highlighted in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nI thrive in roles where I partners with audit, tech, and ops to build durable control environments. The {jobTitle}{companyClause} reads that way.',
                        'Hi {recruiterFirst},\n\nDesigning risk dashboards, remediation plans, and governance cadences is what I do. The {jobTitle}{companyClause} looks similar.',
                        'Hi {recruiterFirst},\n\nFrom policy drafting to control testing, I like owning the details. The {jobTitle}{companyClause} emphasizes the same.',
                        'Hi {recruiterFirst},\n\nI\'d love to contribute to the {jobTitle}{companyClause} with my background in compliance reviews and control uplift.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} highlights regulatory monitoring, issue tracking, and audit engagement — exactly the risk workflow I manage.',
                        'Hi {recruiterFirst},\n\nI thrive when I can translate complex regulations into practical controls. The {jobTitle}{companyClause} is designed for that translation skill.',
                        'Hi {recruiterFirst},\n\nFrom control documentation to testing execution, I\'ve built the frameworks that ensure audit readiness. The {jobTitle}{companyClause} requires that rigor.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} needs someone who balances compliance discipline with operational pragmatism. That\'s my approach.'
                    ]
                },
                {
                    tags: ['fpna', 'corporate_finance'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nBudgeting, forecasting, and variance storytelling are core to my FP&A remit. I saw the same priorities in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nI\'ve been retooling planning processes, building driver trees, and supporting CFO conversations. That lines up with the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nI enjoy roles that blend financial modeling with strategic business partnering. The {jobTitle}{companyClause} sounds like that intersection.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} mentions executive-ready packs, forecast accuracy, and operational engagement — exactly what I run.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} needs someone to connect data with decision-makers. That\'s where I\'m most effective.',
                        'Hi {recruiterFirst},\n\nRolling forecasts, scenario planning, and board prep are my cadence. The {jobTitle}{companyClause} echoes that remit.',
                        'Hi {recruiterFirst},\n\nI thrive when FP&A moves beyond spreadsheets into commercial insight and leadership dialogue. The {jobTitle}{companyClause} promises that evolution.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} highlights monthly closes, exec summaries, and operational rhythm — that\'s my workflow every period.',
                        'Hi {recruiterFirst},\n\nI\'ve been partnering with department heads to improve driver visibility and refine forecasts. The {jobTitle}{companyClause} describes identical work.',
                        'Hi {recruiterFirst},\n\nWhat excites me about the {jobTitle}{companyClause} is the balance between analytical depth and stakeholder influence — exactly where FP&A should sit.'
                    ]
                },
                {
                    tags: ['transformation', 'operations'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nTransformation programs excite me when they combine analytics, change management, and execution. The {jobTitle}{companyClause} ticks all three.',
                        'Hi {recruiterFirst},\n\nI\'ve been leading cross-functional sprints, reworking operating cadences, and making insights actionable. Sounds like the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} references change roadmaps and tangible value creation. That overlap made me reach out.',
                        'Hi {recruiterFirst},\n\nI enjoy translating data into simple routines teams actually follow. The {jobTitle}{companyClause} is built around that.',
                        'Hi {recruiterFirst},\n\nI\'ve driven transformation tracks from diagnostic through implementation. The {jobTitle}{companyClause} describes the same journey.',
                        'Hi {recruiterFirst},\n\nPairing operational detail with financial clarity is what I bring. I saw that duality in the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} highlights process redesign and stakeholder alignment — exactly the initiatives I\'ve been leading.',
                        'Hi {recruiterFirst},\n\nI thrive in roles where I can diagnose bottlenecks, build solutions, and drive adoption. The {jobTitle}{companyClause} is structured for that approach.',
                        'Hi {recruiterFirst},\n\nFrom efficiency reviews to new-system rollouts, I\'ve managed the full transformation arc. The {jobTitle}{companyClause} mirrors that scope.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} values people who can balance analytical rigor with pragmatic execution. That\'s exactly my method.'
                    ]
                },
                {
                    tags: ['quant', 'markets'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nVaR tuning, derivatives pricing, and scenario stress are the core of my work. The {jobTitle}{companyClause} references similar quant skills.',
                        'Hi {recruiterFirst},\n\nI\'m used to coding valuation tools, back-testing strategies, and briefing PMs. The {jobTitle}{companyClause} caught my eye for that reason.',
                        'Hi {recruiterFirst},\n\nI noticed the {jobTitle}{companyClause} expects someone comfortable with volatility surfaces, hedging math, and rapid iteration. That\'s me.',
                        'Hi {recruiterFirst},\n\nI have fun translating quantitative insight into clear actions for traders and risk. The {jobTitle}{companyClause} suggests a similar remit.',
                        'Hi {recruiterFirst},\n\nMy toolkit spans Python, R, and Excel to deliver daily quant updates. The {jobTitle}{companyClause} calls for that mix.',
                        'Hi {recruiterFirst},\n\nI\'d welcome the chance to support the {jobTitle}{companyClause} with my derivatives and structured-product background.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} highlights model validation, risk analytics, and desk support — exactly the quant workflow I run.',
                        'Hi {recruiterFirst},\n\nI thrive when I can build pricing tools that traders actually trust and use. The {jobTitle}{companyClause} is structured for that impact.',
                        'Hi {recruiterFirst},\n\nFrom Monte Carlo simulations to Greek analytics, I\'ve coded the frameworks that inform trading decisions. The {jobTitle}{companyClause} requires that depth.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} needs someone who blends mathematical rigor with practical market intuition. That\'s my approach.'
                    ]
                },
                {
                    tags: ['esg', 'sustainability'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} pairs ESG diligence with impact KPIs — exactly where I\'ve been focusing my energy.',
                        'Hi {recruiterFirst},\n\nI\'ve been building sustainability dashboards, aligning them with frameworks like SFDR and TCFD. The {jobTitle}{companyClause} highlights similar needs.',
                        'Hi {recruiterFirst},\n\nFrom carbon accounting to stakeholder storytelling, ESG roles like the {jobTitle}{companyClause} are where I thrive.',
                        'Hi {recruiterFirst},\n\nI noticed references to data-backed sustainability narratives. That\'s precisely how I support leadership today.',
                        'Hi {recruiterFirst},\n\nImpact investing is most compelling when analytics are tight and messaging is authentic. The {jobTitle}{companyClause} sounds like that balance.',
                        'Hi {recruiterFirst},\n\nI\'d love to discuss the {jobTitle}{companyClause} given my experience embedding ESG KPIs across portfolio reviews.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} emphasizes regulatory alignment, impact measurement, and stakeholder engagement — exactly my ESG workflow.',
                        'Hi {recruiterFirst},\n\nI thrive when I can translate sustainability commitments into measurable progress. The {jobTitle}{companyClause} is designed for that rigor.',
                        'Hi {recruiterFirst},\n\nFrom Scope 3 estimation to biodiversity metrics, I\'ve built the frameworks that inform our impact strategy. The {jobTitle}{companyClause} requires that expertise.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} needs someone who balances ESG idealism with analytical pragmatism. That\'s my approach.'
                    ]
                },
                {
                    tags: ['diligence', 'consulting'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nConsulting-style work — hypothesis trees, rapid diligence, and crisp storytelling — is what I do. The {jobTitle}{companyClause} mirrors that.',
                        'Hi {recruiterFirst},\n\nI enjoy structuring ambiguous mandates, marshalling data, and presenting answers. That\'s exactly what the {jobTitle}{companyClause} describes.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} mentions cross-functional sprints and concise insight packs. That\'s my happy place.',
                        'Hi {recruiterFirst},\n\nI\'ve run multiple diligence workstreams — customer, market, product, and synergy. I saw similar expectations for the {jobTitle}{companyClause}.',
                        'Hi {recruiterFirst},\n\nI\'m adept at turning messy data rooms into board-ready narratives. The {jobTitle}{companyClause} calls for that muscle.',
                        'Hi {recruiterFirst},\n\nI reached out because the {jobTitle}{companyClause} rewards people who can think like consultants but execute like operators.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} emphasizes structured problem-solving and executive-ready deliverables — exactly the consulting toolkit I bring.',
                        'Hi {recruiterFirst},\n\nI thrive on rapid-fire projects where clarity matters more than perfection. The {jobTitle}{companyClause} is built for that pace.',
                        'Hi {recruiterFirst},\n\nFrom issue trees to synthesis slides, I\'ve refined the frameworks that drive crisp recommendations. The {jobTitle}{companyClause} requires that discipline.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} needs someone who can parachute into complexity and extract signal. That\'s my specialty.'
                    ]
                },
                {
                    tags: ['general'],
                    snippets: [
                        'Hi {recruiterFirst},\n\nI\'m drawn to the {jobTitle}{companyClause} because it blends analytical rigor with meaningful ownership. {matchSentence}',
                        'Hi {recruiterFirst},\n\nThe responsibilities under the {jobTitle}{companyClause} line up cleanly with how I like to operate: close to the data, close to stakeholders.',
                        'Hi {recruiterFirst},\n\nIt\'s rare to find a role like the {jobTitle}{companyClause} that values both deep work and cross-functional influence. That combination resonated with me.',
                        'Hi {recruiterFirst},\n\nI wanted to share my profile because the {jobTitle}{companyClause} describes an environment where I know I can contribute from day one.',
                        'Hi {recruiterFirst},\n\nMy favorite roles combine structured analysis with clear communication. The {jobTitle}{companyClause} promises just that.',
                        'Hi {recruiterFirst},\n\nI\'m always keen to join teams that value thoughtful operators who can synthesize and execute. That is how the {jobTitle}{companyClause} reads.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} caught my attention because it emphasizes impact over process. That\'s exactly the type of environment I thrive in.',
                        'Hi {recruiterFirst},\n\nI appreciate roles that balance autonomy with collaboration — the {jobTitle}{companyClause} appears to strike that balance perfectly.',
                        'Hi {recruiterFirst},\n\nWhat drew me to the {jobTitle}{companyClause} was the emphasis on both strategic thinking and hands-on execution. That duality excites me.',
                        'Hi {recruiterFirst},\n\nI\'m reaching out because the {jobTitle}{companyClause} aligns with my approach: deliver tangible results while maintaining analytical integrity.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} resonates because it values substance over presentation — that\'s exactly how I operate.',
                        'Hi {recruiterFirst},\n\nSeeing the {jobTitle}{companyClause} reminded me why I love this work: direct access to decisions, clean data, and smart teams.',
                        'Hi {recruiterFirst},\n\nI\'m drawn to opportunities like the {jobTitle}{companyClause} where precision matters and contributions are visible.',
                        'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} describes my ideal setup: analytical depth, stakeholder exposure, and room to own outcomes.',
                        'Hi {recruiterFirst},\n\nI\'m most effective in roles like the {jobTitle}{companyClause} that blend technical rigor with commercial judgment.',
                        'Hi {recruiterFirst},\n\nWhat stood out about the {jobTitle}{companyClause} is the opportunity to combine data fluency with relationship building — exactly my sweet spot.'
                    ]
                }
            ];

            var templates = [];
            groups.forEach(function(group) {
                var tags = group.tags || ['general'];
                (group.snippets || []).forEach(function(snippet) {
                    templates.push({
                        text: snippet,
                        tags: tags.slice()
                    });
                });
            });
            return templates;
        })(),

        isMailtoMode: function() {
            var mode = (this.config && this.config.emailMode) ? this.config.emailMode : 'oauth';
            return mode === 'mailto';
        },

        isEmailOAuthMode: function() {
            return !this.isMailtoMode();
        },

        /**
         * Initialize CRM
         */
        init: function() {
            this.config = window.sffcCRM || {};
            this.config.emailMode = this.config.emailMode || 'oauth';
            this.config.isPremium = !!this.config.isPremium;
            this.config.isLoggedIn = !!this.config.isLoggedIn;
            this.config.membershipUrl = this.config.membershipUrl || 'https://joinsenna.com/memberships/';
            this.config.loginUrl = this.config.loginUrl || this.config.membershipUrl;
            this.config.avatarUpload = this.config.avatarUpload || {};
            this.recruiterOpeningsState = null;

            // Premium Members placeholder avatars for blur effect
            this.earlyBirdAvatars = [
                'https://media.joinsenna.com/2025/11/paulGray.jpg?1764020383',
                'https://media.joinsenna.com/2025/11/jakeClements.jpg?1764020375',
                'https://media.joinsenna.com/2025/11/269647838-smile-professional-and-portrai.jpeg?1764020359',
                'https://media.joinsenna.com/2025/11/237358380-shes-positively-professional-p.jpeg?1764020365',
                'https://media.joinsenna.com/2025/11/242769510-smile-portrait-and-face-young--scaled.jpeg?1764020340',
                'https://media.joinsenna.com/2025/11/254548926-go-out-there-and-get-what-you--scaled.jpeg?1764020323',
                'https://media.joinsenna.com/2025/11/267845496-mature-business-woman-portrait-scaled.jpeg?1764020306',
                'https://media.joinsenna.com/2025/06/martin-jewell.jpeg?1749048532',
                'https://joinsenna.com/wp-content/uploads/2025/01/moira-2.png',
                'https://joinsenna.com/wp-content/uploads/2024/10/tara.png',
                'https://joinsenna.com/wp-content/uploads/2024/10/241900549-happy-smile-and-portrait-busin.jpeg',
                'https://joinsenna.com/wp-content/uploads/2024/12/6453342.jpeg',
                'https://joinsenna.com/wp-content/uploads/2025/01/340340_Lydia.jpeg',
                'https://joinsenna.com/wp-content/uploads/2025/01/adrianh.png'
            ];
            this.bindEvents();
            this.initTabs();
            this.initFilters();
            this.initBulkSelectionEvents();
            this.initKeyboardShortcuts();
            this.initTouchGestures();
            this.initEmailConnection();
            this.initPlanModal();
            this.initGapAnalyzer();
            this.initAuthModal();
            this.initProfileAvatar();
            this.initAvatarUpload();
            this.loadAllBadgeCounts(); // Load badge counts immediately
            this.prefetchSmartApplyMap();
            this.bindMatchEvents();

            // Note: Onboarding tour now triggers after first CV upload
            // See renderMatches function for trigger logic
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            var authRestrictedSelectors = '.sffc-crm-match-introduce-btn, .sffc-crm-match-message-btn, .sffc-crm-app-toolkit-btn, .sffc-crm-save-btn';
            var membershipOnlySelectors = this.membershipOnlySelectors;
            var guestAccessibleTabs = ['all-roles', 'account'];
            $(document).off('click.authGate').on('click.authGate', authRestrictedSelectors, function(e) {
                // If logged out, show auth modal (State 1 - create account)
                if (!self.config.isLoggedIn) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.stopImmediatePropagation) {
                        e.stopImmediatePropagation();
                    }
                    self.showAuthModal();
                    return;
                }

                var requiresMembership = $(this).is(membershipOnlySelectors);

                if (requiresMembership && !self.config.isPremium) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.stopImmediatePropagation) {
                        e.stopImmediatePropagation();
                    }
                    self.showMembershipPrompt();
                    return;
                }

                // If logged in, check if they've seen membership prompt
                var hasSeenMembershipPrompt = localStorage.getItem('sffc_crm_seen_membership_prompt');

                // If they haven't seen it yet, show membership selection (State 2)
                if (!hasSeenMembershipPrompt && !self.config.isPremium) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.stopImmediatePropagation) {
                        e.stopImmediatePropagation();
                    }
                    self.showMembershipPrompt();
                    return;
                }

                // Otherwise, let the action proceed normally
            });

            // Recruiter openings load more
            $(document).off('click.recruiterOpeningsMore', '#recruiter-openings-load-more').on('click.recruiterOpeningsMore', '#recruiter-openings-load-more', function(e) {
                e.preventDefault();
                if (!self.recruiterOpeningsState || self.recruiterOpeningsState.isLoading) {
                    return;
                }
                if (!self.recruiterOpeningsState.hasMore) {
                    return;
                }
                var nextPage = (self.recruiterOpeningsState.page || 1) + 1;
                self.fetchRecruiterOpenings(nextPage, true);
            });

            // Tab switching
            $(document).on('click', '.sffc-crm-tab', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');

                if (!self.config.isLoggedIn && guestAccessibleTabs.indexOf(tab) === -1) {
                    self.showAuthModal();
                    if ($(this).closest('.sffc-crm-mobile-nav').length) {
                        self.closeMobileNav();
                    }
                    return;
                }

                if (tab === 'matches' && self.config.isLoggedIn && !self.config.isPremium) {
                    if ($(this).closest('.sffc-crm-mobile-nav').length) {
                        self.closeMobileNav();
                    }
                    e.stopImmediatePropagation();
                    self.showMonetizationModal('matches');
                    return;
                }

                if (tab === 'recruiter-intros' && self.config.isLoggedIn && !self.config.isPremium) {
                    if ($(this).closest('.sffc-crm-mobile-nav').length) {
                        self.closeMobileNav();
                    }
                    self.showMonetizationModal('intro');
                    return;
                }

                self.switchTab(tab);
            });

            $(document).on('click', '.sffc-crm-profile-join', function(e) {
                e.preventDefault();
                self.showAuthModal();
            });

            $(document).on('click', '.sffc-crm-page-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var target = $btn.closest('.sffc-crm-pagination').data('target');
                var direction = ($btn.data('direction') || '').toString();
                var page = parseInt($btn.data('page'), 10) || 1;

                if (!self.config.isPremium && target === 'recruiters' && direction === 'next') {
                    self.showMonetizationModal('recruiters');
                    return;
                }

                self.handlePaginationClick(target, page);
            });

            // Save post
            $(document).on('click', '.sffc-crm-save-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var postId = $btn.data('post-id');
                var isSaved = $btn.hasClass('is-saved');

                if (isSaved) {
                    self.unsavePost(postId, $btn);
                } else {
                    self.savePost(postId, $btn);
                }
            });

            $(document).on('click', '.sffc-crm-gap-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var postId = $(this).data('post-id');
                if (!postId) {
                    return;
                }
                self.clearGapAnalyzerPendingContext();
                self.openGapAnalyzerModal(postId);
            });

            $(document).on('submit', '#sffc-crm-password-form', function(e) {
                e.preventDefault();
                self.handlePasswordForm($(this));
            });

            // Send CV button
            $(document).on('click', '.sffc-crm-reach-out-btn', function(e) {
                e.preventDefault();

                if (!self.config.isLoggedIn || !self.config.isPremium) {
                    self.showReachOutUpgradeModal();
                    return;
                }

                var postId = $(this).data('post-id');
                var recruiterId = $(this).data('recruiter-id');
                self.openReachOutModal(postId, recruiterId);
            });

            // Inline "Add to List" dropdown toggle
            $(document).on('click', '.sffc-crm-add-list-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var recruiterId = $(this).data('recruiter-id');

                if (!self.config.isLoggedIn) {
                    alert('Please log in to add recruiters to your outreach lists.');
                    return;
                }

                self.toggleInlineAddListDropdown($(this).closest('.sffc-crm-add-list-wrapper'), recruiterId);
            });

            // Inline existing list add action
            $(document).on('click', '.sffc-crm-inline-add-existing', function(e) {
                e.preventDefault();
                var $dropdown = $(this).closest('.sffc-crm-add-list-dropdown');
                var listId = parseInt($dropdown.find('.sffc-crm-inline-select').val(), 10);
                var recruiterId = parseInt($dropdown.data('recruiter-id'), 10);
                if (!listId) {
                    alert('Select a list');
                    return;
                }
                self.addRecruitersToListInline(listId, [recruiterId], $dropdown, $(this));
            });

            // Inline create list action
            $(document).on('click', '.sffc-crm-inline-create', function(e) {
                e.preventDefault();
                var $dropdown = $(this).closest('.sffc-crm-add-list-dropdown');
                var listName = $dropdown.find('.sffc-crm-inline-new-name').val().trim();
                var recruiterId = parseInt($dropdown.data('recruiter-id'), 10);
                if (!listName) {
                    alert('Enter a list name');
                    return;
                }
                self.createInlineListAndAdd(listName, recruiterId, $dropdown, $(this));
            });

            $(document).on('click', '.sffc-crm-add-list-dropdown', function(e) {
                e.stopPropagation();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('.sffc-crm-add-list-wrapper').length) {
                    self.closeInlineAddListDropdown();
                }
            });

            $(document).on('click', '.sffc-crm-contact-row--blurred', function(e) {
                if (self.config.isPremium) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                self.triggerPlanModal();
            });

            $(document).on('click', '[data-requires-membership="true"]', function(e) {
                if (self.config.isPremium) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                self.triggerPlanModal();
            });

            // CV form submit
            $(document).on('submit', '#crm-cv-form', function(e) {
                e.preventDefault();
                self.saveCvVersion($(this));
            });

            // Set default CV
            $(document).on('click', '.sffc-crm-cv-set-default', function(e) {
                e.preventDefault();
                var cvId = $(this).data('cv-id');
                self.setDefaultCv(cvId, $(this));
            });

            // Refresh CV list
            $(document).on('click', '#crm-cv-refresh', function(e) {
                e.preventDefault();
                self.cvState.loaded = false;
                self.loadResumeTab(true);
            });

            // Expert Send CV button
            $(document).on('click', '.sffc-crm-expert-request-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var recruiter = {
                    id: $btn.data('recruiter-id'),
                    name: $btn.data('recruiter-name'),
                    title: $btn.data('recruiter-title'),
                    firm: $btn.data('recruiter-firm'),
                    photo_url: $btn.data('recruiter-photo')
                };
                self.openExpertOutreachModal(recruiter);
            });

            // Load more
            $(document).on('click', '#crm-load-more button', function(e) {
                e.preventDefault();
                if (!self.config.isPremium) {
                    self.triggerPlanModal();
                    return;
                }
                self.loadMorePosts();
            });

            // CV Gap Analysis button
            $(document).on('click', '#sffc-crm-cv-gap-analysis', function(e) {
                e.preventDefault();
                // Open gap analyzer modal without a specific post ID (general analysis)
                self.clearGapAnalyzerPendingContext();
                self.openGapAnalyzerModal(null);
            });

            // Filter toggle
            $(document).on('click', '#sffc-crm-filter-toggle', function(e) {
                e.preventDefault();
                $('#sffc-crm-filters').slideToggle(200);
            });

            // Apply filters
            $(document).on('click', '#apply-filters', function(e) {
                e.preventDefault();
                self.applyFilters();
            });

            // Clear filters
            $(document).on('click', '#clear-filters', function(e) {
                e.preventDefault();
                self.clearFilters();
            });

            // Auto-apply filters when dropdowns change (for All Roles tab)
            $(document).on('change', '#filter-sector, #filter-seniority, #filter-country, #filter-firm', function() {
                if (self.currentTab === 'all-roles') {
                    self.applyFilters();
                }
            });

            // Auto-apply search filter as you type (debounced)
            var searchTimeout;
            $(document).on('input', '#filter-search', function() {
                if (self.currentTab === 'all-roles') {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        self.applyFilters();
                    }, 300);
                }
            });

            // Recruiter actions
            $(document).on('click', '[data-action="view-recruiter"]', function(e) {
                e.preventDefault();
                var recruiterId = $(this).closest('.sffc-crm-recruiter-row').data('recruiter-id');
                self.viewRecruiter(recruiterId);
            });

            // Pipeline card click
            $(document).on('click', '.sffc-crm-pipeline-card', function(e) {
                e.preventDefault();
                var pipelineId = $(this).data('pipeline-id');
                self.viewPipelineItem(pipelineId);
            });

            // Phase 2: Enhanced recruiter filtering
            $(document).on('change', '#recruiter-filter-status, #recruiter-filter-tag, #recruiter-sort', function() {
                self.applyRecruiterFilters();
            });

            $(document).on('input', '#recruiter-search', function() {
                clearTimeout(self.recruiterSearchTimeout);
                self.recruiterSearchTimeout = setTimeout(function() {
                    self.applyRecruiterFilters();
                }, 300);
            });

            // Phase 2: Tag management
            $(document).on('click', '.sffc-crm-add-tag-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openTagManager($(this).data('recruiter-id'));
            });

            $(document).on('click', '.sffc-crm-tag-remove', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var tagId = $(this).closest('.sffc-crm-user-tag').data('tag-id');
                var recruiterId = $(this).closest('.sffc-crm-recruiter-row').data('recruiter-id');
                self.removeTagFromRecruiter(recruiterId, tagId);
            });

            $(document).on('click', '.sffc-crm-create-tag-btn', function(e) {
                e.preventDefault();
                self.createNewTag();
            });

            $(document).on('click', '.sffc-crm-tag-option', function(e) {
                e.preventDefault();
                var tagId = $(this).data('tag-id');
                var recruiterId = $('.sffc-crm-tag-manager-modal').data('recruiter-id');
                self.addTagToRecruiter(recruiterId, tagId);
            });

            // Phase 2: Notes management
            $(document).on('click', '.sffc-crm-add-note-btn', function(e) {
                e.preventDefault();
                var recruiterId = $(this).data('recruiter-id');
                self.openAddNoteModal(recruiterId);
            });

            $(document).on('click', '.sffc-crm-note-edit', function(e) {
                e.preventDefault();
                var noteId = $(this).closest('.sffc-crm-note-item').data('note-id');
                self.editNote(noteId);
            });

            $(document).on('click', '.sffc-crm-note-delete', function(e) {
                e.preventDefault();
                if (confirm('Delete this note?')) {
                    var noteId = $(this).closest('.sffc-crm-note-item').data('note-id');
                    self.deleteNote(noteId);
                }
            });

            $(document).on('click', '.sffc-crm-note-pin', function(e) {
                e.preventDefault();
                var noteId = $(this).closest('.sffc-crm-note-item').data('note-id');
                self.toggleNotePin(noteId);
            });

            // Phase 2: Follow-up date
            $(document).on('change', '.sffc-crm-followup-date', function() {
                var recruiterId = $(this).data('recruiter-id');
                var date = $(this).val();
                self.setRecruiterFollowup(recruiterId, date);
            });

            // Phase 2: Dashboard tab
            $(document).on('click', '[data-tab="dashboard"]', function(e) {
                e.preventDefault();
                self.switchTab('dashboard');
            });

            // Post card/row click (not on buttons)
            $(document).on('click', '.sffc-crm-post-card, .sffc-crm-post-row', function(e) {
                if ($(e.target).closest('button, a, .sffc-crm-post-actions, .sffc-crm-row-actions').length) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                var postId = $(this).data('post-id');
                if (!postId) {
                    return;
                }

                if (!self.config.isLoggedIn) {
                    var loginUrl = self.config.loginUrl || self.config.membershipUrl || '/wp-login.php';
                    window.location = loginUrl;
                    return;
                }

                self.clearGapAnalyzerPendingContext();
                self.openGapAnalyzerModal(postId);
            });

            // Close modal
            $(document).on('click', '.sffc-crm-modal-close, .sffc-crm-modal-overlay', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Close modal via data-action="close" buttons
            $(document).on('click', 'button[data-action="close"]', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Prevent modal content click from closing
            $(document).on('click', '.sffc-crm-modal-content', function(e) {
                e.stopPropagation();
            });

            // Add to pipeline from modal
            $(document).on('click', '.sffc-crm-add-pipeline-btn', function(e) {
                e.preventDefault();
                var postId = $(this).data('post-id');
                var recruiterId = $(this).data('recruiter-id');
                self.addToPipeline(postId, recruiterId);
            });

            // Keyboard escape to close modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('.sffc-crm-modal').length) {
                    self.closeModal();
                }
            });

            // Enroll recruiter in sequence
            $(document).on('click', '.sffc-crm-enroll-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var recruiterId = $(this).data('recruiter-id');
                var recruiterName = $(this).data('recruiter-name');
                self.openEnrollmentModal(recruiterId, recruiterName);
            });

            // Handle enrollment actions in recruiter detail
            $(document).on('click', '[data-action="pause-enrollment"]', function(e) {
                e.preventDefault();
                var enrollmentId = $(this).data('enrollment-id');
                self.pauseEnrollmentFromDetail(enrollmentId);
            });

            $(document).on('click', '[data-action="resume-enrollment"]', function(e) {
                e.preventDefault();
                var enrollmentId = $(this).data('enrollment-id');
                self.resumeEnrollmentFromDetail(enrollmentId);
            });

            $(document).on('click', '[data-action="remove-enrollment"]', function(e) {
                e.preventDefault();
                var enrollmentId = $(this).data('enrollment-id');
                if (confirm('Are you sure you want to remove this enrollment?')) {
                    self.removeEnrollmentFromDetail(enrollmentId);
                }
            });

            // User dropdown toggle
            $(document).on('click', '.sffc-crm-dropdown-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $dropdown = $(this).closest('.sffc-crm-user-dropdown');
                $dropdown.toggleClass('open');
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.sffc-crm-user-dropdown').length) {
                    $('.sffc-crm-user-dropdown').removeClass('open');
                }
            });

            // Mobile menu toggle
            $(document).on('click', '.sffc-crm-mobile-menu-toggle', function(e) {
                e.preventDefault();
                self.openMobileNav();
            });

            // Close mobile nav
            $(document).on('click', '.sffc-crm-mobile-nav-close, .sffc-crm-mobile-nav-overlay', function(e) {
                e.preventDefault();
                self.closeMobileNav();
            });

            // Mobile nav tab click
            $(document).on('click', '.sffc-crm-mobile-nav-tabs .sffc-crm-tab', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                if (!self.config.isLoggedIn && guestAccessibleTabs.indexOf(tab) === -1) {
                    self.showAuthModal();
                    return;
                }
                self.switchTab(tab);
                self.closeMobileNav();
            });

            // Bottom navigation bar click (LinkedIn-style mobile nav)
            $(document).on('click', '.sffc-crm-bottom-nav-item', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');

                if ($(this).is('#sffc-crm-bottom-more')) {
                    self.openMobileNav();
                    return;
                }

                if ($(this).is('#sffc-crm-bottom-join')) {
                    self.showAuthModal();
                    return;
                }

                if (!self.config.isLoggedIn && guestAccessibleTabs.indexOf(tab) === -1) {
                    self.showAuthModal();
                    return;
                }

                if (tab === 'matches' && self.config.isLoggedIn && !self.config.isPremium) {
                    self.showMonetizationModal('matches');
                    return;
                }

                // Update bottom nav active state
                $('.sffc-crm-bottom-nav-item').removeClass('active');
                $(this).addClass('active');

                // Switch to the corresponding tab
                self.switchTab(tab);

                // Scroll to top smoothly
                $('html, body').animate({ scrollTop: 0 }, 300);
            });

            // Mobile overlay click to close panels
            $(document).on('click', '.sffc-crm-mobile-overlay', function(e) {
                e.preventDefault();
                self.closeMobilePanels();
            });

            // Action sheet backdrop click to close
            $(document).on('click', '.sffc-crm-action-sheet-backdrop', function(e) {
                e.preventDefault();
                self.closeActionSheet();
            });

            // Action sheet option click
            $(document).on('click', '.sffc-crm-action-sheet-option', function(e) {
                var action = $(this).data('action');
                if (action) {
                    self.handleActionSheetAction(action);
                }
                self.closeActionSheet();
            });

            // FAB button click
            $(document).on('click', '.sffc-crm-fab', function(e) {
                e.preventDefault();
                self.openQuickAddSheet();
            });

            // Filter toggle button
            $(document).on('click', '.sffc-crm-filter-toggle', function(e) {
                e.preventDefault();
                $(this).toggleClass('active');
                $('#sffc-crm-filters').slideToggle(200);
            });
        },

        /**
         * Open mobile navigation
         */
        openMobileNav: function() {
            var $nav = $('.sffc-crm-mobile-nav');
            if ($nav.length === 0) {
                this.createMobileNav();
                $nav = $('.sffc-crm-mobile-nav');
            }
            $nav.addClass('open');
            $('body').addClass('sffc-crm-mobile-nav-open');
        },

        /**
         * Close mobile navigation
         */
        closeMobileNav: function() {
            $('.sffc-crm-mobile-nav').removeClass('open');
            $('body').removeClass('sffc-crm-mobile-nav-open');
        },

        /**
         * Create mobile navigation element
         */
        createMobileNav: function() {
            var tabs = $('.sffc-crm-tabs .sffc-crm-tab').clone();

            var html = '<div class="sffc-crm-mobile-nav">' +
                '<div class="sffc-crm-mobile-nav-overlay"></div>' +
                '<div class="sffc-crm-mobile-nav-content">' +
                    '<div class="sffc-crm-mobile-nav-header">' +
                        '<span class="sffc-crm-mobile-nav-title">Menu</span>' +
                        '<button class="sffc-crm-mobile-nav-close" type="button">' +
                            '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                                '<line x1="18" y1="6" x2="6" y2="18"></line>' +
                                '<line x1="6" y1="6" x2="18" y2="18"></line>' +
                            '</svg>' +
                        '</button>' +
                    '</div>' +
                    '<div class="sffc-crm-mobile-nav-tabs"></div>' +
                '</div>' +
            '</div>';

            $('body').append(html);
            $('.sffc-crm-mobile-nav-tabs').append(tabs);
        },

        /**
         * Close mobile panels (overlay, action sheets, etc.)
         */
        closeMobilePanels: function() {
            $('.sffc-crm-mobile-overlay').removeClass('active');
            $('.sffc-crm-mobile-panel').removeClass('open');
            $('body').removeClass('sffc-crm-panel-open');
        },

        /**
         * Open action sheet
         */
        openActionSheet: function(options) {
            var $sheet = $('#sffc-crm-action-sheet');
            var $content = $sheet.find('.sffc-crm-action-sheet-content');

            if (options && options.items) {
                var html = '<div class="sffc-crm-action-sheet-header">' +
                    '<span class="sffc-crm-action-sheet-title">' + (options.title || 'Options') + '</span>' +
                '</div>';

                options.items.forEach(function(item) {
                    html += '<button type="button" class="sffc-crm-action-sheet-option" data-action="' + item.action + '">' +
                        (item.icon ? '<span class="sffc-crm-action-sheet-icon">' + item.icon + '</span>' : '') +
                        '<span>' + item.label + '</span>' +
                    '</button>';
                });

                html += '<button type="button" class="sffc-crm-action-sheet-option sffc-crm-action-sheet-cancel">Cancel</button>';
                $content.html(html);
            }

            $sheet.addClass('open');
            $('#sffc-crm-mobile-overlay').addClass('active');
            $('body').addClass('sffc-crm-panel-open');
        },

        /**
         * Close action sheet
         */
        closeActionSheet: function() {
            $('#sffc-crm-action-sheet').removeClass('open');
            $('#sffc-crm-mobile-overlay').removeClass('active');
            $('body').removeClass('sffc-crm-panel-open');
        },

        /**
         * Open quick add sheet (FAB action)
         */
        openQuickAddSheet: function() {
            this.openActionSheet({
                title: 'Quick Add',
                items: [
                    { action: 'add-post', label: 'Add Job Post', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>' },
                    { action: 'add-recruiter', label: 'Add Recruiter', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' },
                    { action: 'add-note', label: 'Quick Note', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' }
                ]
            });
        },

        /**
         * Handle action sheet action
         */
        handleActionSheetAction: function(action) {
            var self = this;
            switch (action) {
                case 'add-post':
                    // Switch to feed tab and trigger add
                    this.switchTab('feed');
                    setTimeout(function() {
                        $('.sffc-crm-add-btn[data-action="add-post"]').click();
                    }, 100);
                    break;
                case 'add-recruiter':
                    this.switchTab('contacts');
                    setTimeout(function() {
                        $('.sffc-crm-add-btn[data-action="add-recruiter"]').click();
                    }, 100);
                    break;
                case 'add-note':
                    // Open quick note modal if available
                    if (typeof this.openQuickNoteModal === 'function') {
                        this.openQuickNoteModal();
                    }
                    break;
            }
        },

        /**
         * Sync bottom nav with current tab
         */
        syncBottomNav: function(tab) {
            $('.sffc-crm-bottom-nav-item').removeClass('active');
            $('.sffc-crm-bottom-nav-item[data-tab="' + tab + '"]').addClass('active');
        },

        /**
         * Initialize touch gestures for mobile
         */
        initTouchGestures: function() {
            var self = this;
            var touchStartX = 0;
            var touchStartY = 0;
            var touchEndX = 0;
            var touchEndY = 0;
            var minSwipeDistance = 80;
            var maxVerticalMovement = 100;

            // Tab order for swipe navigation
            var tabOrder = ['feed', 'recruiters', 'saved', 'inbox', 'pipeline'];

            // Get panels container
            var $container = $('.sffc-crm-content');

            if ($container.length === 0) return;

            $container[0].addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
                touchStartY = e.changedTouches[0].screenY;
            }, { passive: true });

            $container[0].addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                touchEndY = e.changedTouches[0].screenY;

                var horizontalDistance = touchEndX - touchStartX;
                var verticalDistance = Math.abs(touchEndY - touchStartY);

                // Only trigger swipe if mostly horizontal
                if (Math.abs(horizontalDistance) > minSwipeDistance && verticalDistance < maxVerticalMovement) {
                    var currentIndex = tabOrder.indexOf(self.currentTab);
                    if (currentIndex === -1) return;

                    if (horizontalDistance < 0) {
                        // Swipe left - go to next tab
                        if (currentIndex < tabOrder.length - 1) {
                            self.switchTab(tabOrder[currentIndex + 1]);
                        }
                    } else {
                        // Swipe right - go to previous tab
                        if (currentIndex > 0) {
                            self.switchTab(tabOrder[currentIndex - 1]);
                        }
                    }
                }
            }, { passive: true });

            // Pull to refresh on feed
            var pullStartY = 0;
            var isPulling = false;
            var $feedPanel = $('#panel-feed');

            if ($feedPanel.length) {
                $feedPanel[0].addEventListener('touchstart', function(e) {
                    if ($feedPanel.scrollTop() === 0) {
                        pullStartY = e.changedTouches[0].screenY;
                        isPulling = true;
                    }
                }, { passive: true });

                $feedPanel[0].addEventListener('touchmove', function(e) {
                    if (!isPulling) return;
                    var pullDistance = e.changedTouches[0].screenY - pullStartY;
                    if (pullDistance > 60 && $feedPanel.scrollTop() === 0) {
                        $feedPanel.addClass('sffc-crm-pulling');
                    }
                }, { passive: true });

                $feedPanel[0].addEventListener('touchend', function(e) {
                    if ($feedPanel.hasClass('sffc-crm-pulling')) {
                        $feedPanel.removeClass('sffc-crm-pulling');
                        // Trigger refresh
                        self.loadFeed(true);
                    }
                    isPulling = false;
                }, { passive: true });
            }
        },

        initEmailConnection: function() {
            var self = this;
            if (!this.config.isLoggedIn || this.isMailtoMode()) {
                return;
            }

            this.loadEmailAccounts();

            $(document).on('click', '#sffc-crm-connect-email', function(e) {
                e.preventDefault();
                self.openEmailConnectModal();
            });

            window.addEventListener('message', function(event) {
                if (!event.data || event.origin !== window.location.origin) {
                    return;
                }
                if (event.data.type === 'sffc-crm-oauth') {
                    self.loadEmailAccounts();
                    if (event.data.status === 'success') {
                        self.showSuccess('Email account connected');
                    } else {
                        self.showError('Unable to connect email account');
                    }
                }
            });
        },

        loadEmailAccounts: function() {
            var self = this;
            if (!this.config.isLoggedIn || this.isMailtoMode()) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_email_accounts',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.emailAccounts = response.data.accounts || [];
                        self.renderEmailStatus();
                        if (self.activeModal === 'emailConnect') {
                            self.openEmailConnectModal();
                        }
                    }
                }
            });
        },

        initPlanModal: function() {
            this.$planModal = $('[data-plan-modal]');
            if (!this.$planModal.length) {
                this.$planModal = null;
                return;
            }

            this.planOptionsCache = null;

            var self = this;
            this.$planForms = this.$planModal.find('[data-plan-form]');
            this.$planCheckout = this.$planModal.find('[data-plan-checkout]');
            this.$planMessage = this.$planModal.find('[data-plan-message]');
            this.$planExternal = this.$planModal.find('[data-plan-external]');
            this.$planExternalLink = this.$planModal.find('[data-plan-external-link]');

            this.$planModal.on('click', '[data-plan-close]', function(e) {
                e.preventDefault();
                self.hidePlanModal();
            });

            this.$planModal.on('click', '.sffc-plan-modal__overlay', function(e) {
                e.preventDefault();
                self.hidePlanModal();
            });

            this.$planModal.on('click', '[data-plan-select]', function(e) {
                e.preventDefault();
                self.handlePlanSelect($(this));
            });

            $(document).on('keydown.sffcPlanModal', function(e) {
                if (e.key === 'Escape' && self.$planModal && self.$planModal.hasClass('is-open')) {
                    self.hidePlanModal();
                }
            });
        },

        initGapAnalyzer: function() {
            this.ensureGapAnalyzerConfig();
            this.gapAnalyzer.$park = $('#sffc-gap-analyzer-park');
            this.gapAnalyzer.$shell = $('#sffc-gap-analyzer-shell');

            if (this.gapAnalyzer.$shell && this.gapAnalyzer.$shell.length) {
                this.gapAnalyzer.$component = this.gapAnalyzer.$shell.find('[data-component="gap-analyzer"]');
                this.gapAnalyzer.$shell.attr('hidden', 'hidden');
            } else {
                this.gapAnalyzer.$park = null;
                this.gapAnalyzer.$component = null;
            }
        },

        ensureGapAnalyzerConfig: function() {
            if (typeof window.sffc_gap_analyzer === 'undefined' && this.config && this.config.gapAnalyzer) {
                window.sffc_gap_analyzer = this.config.gapAnalyzer;
            }
        },

        initProfileAvatar: function() {
            var $avatars = $('.sffc-crm-profile-avatar img');
            if (!$avatars.length) {
                return;
            }

            $avatars.off('error.crmAvatar load.crmAvatar')
                .on('error.crmAvatar', function() {
                    $(this).closest('.sffc-crm-profile-avatar').addClass('sffc-crm-profile-avatar--placeholder');
                })
                .on('load.crmAvatar', function() {
                    if (this.naturalWidth > 0) {
                        $(this).closest('.sffc-crm-profile-avatar').removeClass('sffc-crm-profile-avatar--placeholder');
                    }
                });
        },

        initAvatarUpload: function() {
            var cfg = this.config.avatarUpload || {};
            if (!cfg.enabled) {
                return;
            }

            var $input = $('#sffc-crm-avatar-input');
            if (!$input.length) {
                return;
            }

            $(document).on('click', '.sffc-crm-profile-avatar:not(.sffc-crm-profile-avatar--static)', function(e) {
                if ($(e.target).closest('.sffc-crm-avatar-upload-trigger').length) {
                    return;
                }
                if ($(this).hasClass('is-uploading')) {
                    return;
                }
                e.preventDefault();
                $input.trigger('click');
            });

            $(document).on('click', '.sffc-crm-avatar-upload-trigger', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $avatar = $('.sffc-crm-profile-avatar');
                if ($avatar.hasClass('is-uploading')) {
                    return;
                }
                $input.trigger('click');
            });

            var self = this;
            $input.on('change', function() {
                var file = this.files && this.files[0];
                if (!file) {
                    return;
                }
                self.handleAvatarUpload(file);
                $(this).val('');
            });
        },

        handlePlanSelect: function($button) {
            if (!this.$planModal) {
                return;
            }

            var slug = $button.data('planSlug');
            var url = $button.data('planUrl');
            var hasShortcode = !!$button.data('planShortcode');
            var planName = $button.data('planName') || '';

            this.$planModal.find('[data-plan-card]').removeClass('is-active');
            $button.closest('[data-plan-card]').addClass('is-active');

            if (hasShortcode && slug && this.$planForms && this.$planForms.length) {
                this.$planForms.attr('hidden', 'hidden');
                var $targetForm = this.$planForms.filter('[data-plan-form="' + slug + '"]');
                if ($targetForm.length) {
                    $targetForm.removeAttr('hidden');
                }
                if (this.$planCheckout && this.$planCheckout.length) {
                    this.$planCheckout.removeAttr('hidden');
                }
                if (this.$planMessage && this.$planMessage.length) {
                    this.$planMessage.text('Complete checkout to join ' + (planName || 'this plan') + '.');
                }
                if (this.$planExternal && this.$planExternal.length) {
                    this.$planExternal.attr('hidden', 'hidden');
                }
                return;
            }

            if (url) {
                if (this.$planExternal && this.$planExternal.length) {
                    this.$planExternal.removeAttr('hidden');
                    if (this.$planExternalLink && this.$planExternalLink.length) {
                        this.$planExternalLink.attr('href', url);
                    }
                } else {
                    window.open(url, '_blank', 'noopener');
                }
                if (this.$planCheckout && this.$planCheckout.length) {
                    this.$planCheckout.attr('hidden', 'hidden');
                }
                if (this.$planForms && this.$planForms.length) {
                    this.$planForms.attr('hidden', 'hidden');
                }
            }
        },

        showPlanModal: function() {
            if (!this.$planModal) {
                return false;
            }
            this.$planModal.addClass('is-open').attr('aria-hidden', 'false').prop('inert', false);
            $('body').addClass('sffc-plan-modal-open');
            return true;
        },

        hidePlanModal: function() {
            if (!this.$planModal) {
                return;
            }

            // Blur any focused element inside the modal before hiding
            var activeElement = document.activeElement;
            if (activeElement && this.$planModal[0].contains(activeElement)) {
                activeElement.blur();
            }

            this.$planModal.removeClass('is-open').attr('aria-hidden', 'true').prop('inert', true);
            $('body').removeClass('sffc-plan-modal-open');
            if (this.$planCheckout && this.$planCheckout.length) {
                this.$planCheckout.attr('hidden', 'hidden');
            }
            if (this.$planForms && this.$planForms.length) {
                this.$planForms.attr('hidden', 'hidden');
            }
            if (this.$planExternal && this.$planExternal.length) {
                this.$planExternal.attr('hidden', 'hidden');
            }
            this.$planModal.find('[data-plan-card]').removeClass('is-active');
        },

        triggerPlanModal: function(options) {
            options = options || {};
            if (this.config.isPremium) {
                return false;
            }
            if (this.showPlanModal()) {
                return true;
            }
            if (!options.skipFallback) {
                var membershipUrl = this.config.membershipUrl || 'https://joinsenna.com/memberships/';
                window.open(membershipUrl, '_blank');
            }
            return false;
        },

        getPrimaryEmailAccount: function() {
            if (!this.emailAccounts || !this.emailAccounts.length) {
                return null;
            }
            var primary = this.emailAccounts.find(function(account) {
                return account.is_primary;
            });
            return primary || this.emailAccounts[0];
        },

        renderEmailStatus: function() {
            var $btn = $('#sffc-crm-connect-email');
            var $pill = $('#sffc-crm-email-pill');
            if (!$btn.length) {
                return;
            }

            var primary = this.getPrimaryEmailAccount();
            if (primary) {
                $btn.find('span').text('Email Connected');
                $pill.text(primary.email_address).addClass('is-connected').show();
            } else {
                $btn.find('span').text('Connect Email');
                $pill.removeClass('is-connected').hide();
            }
        },

        openEmailConnectModal: function() {
            if (!this.config.isLoggedIn) {
                this.showLoginPrompt();
                return;
            }
            if (this.isMailtoMode()) {
                this.showError('Direct sending is temporarily disabled. Switch CRM settings back to Gmail/Outlook mode to connect an inbox.');
                return;
            }

            var html = '<div class="sffc-crm-email-modal">';
            html += '<div class="sffc-crm-modal-header"><h3>Connect Your Email</h3><p>Send outreach directly from MENA Careers and track opens/clicks automatically.</p></div>';

            if (this.emailAccounts.length) {
                html += '<div class="sffc-crm-email-list">';
                this.emailAccounts.forEach(function(account) {
                    html += '<div class="sffc-crm-email-card">';
                    html += '<div><strong>' + account.email_address + '</strong><span>' + account.provider.charAt(0).toUpperCase() + account.provider.slice(1) + '</span></div>';
                    html += '<div class="sffc-crm-email-card-actions">';
                    if (account.is_primary) {
                        html += '<span class="sffc-crm-email-badge">Primary</span>';
                    }
                    html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-email-disconnect" data-account-id="' + account.id + '">Disconnect</button>';
                    html += '</div></div>';
                });
                html += '</div>';
            } else {
                html += '<div class="sffc-crm-email-empty">Connect Gmail or Outlook to send within MENA Careers.</div>';
            }

            html += '<div class="sffc-crm-email-connect-grid">';
            html += '<button class="sffc-crm-email-provider" data-provider="gmail"><span>Connect Gmail</span></button>';
            html += '<button class="sffc-crm-email-provider" data-provider="outlook"><span>Connect Outlook</span></button>';
            html += '</div>';

            html += '<div class="sffc-crm-email-divider"><span>or</span></div>';
            html += '<form id="sffc-crm-app-form" class="sffc-crm-email-form">';
            html += '<div class="sffc-crm-form-group"><label>Email Address</label><input type="email" name="email" required></div>';
            html += '<div class="sffc-crm-form-group"><label>Display Name</label><input type="text" name="name" placeholder="What recruiters should see"></div>';
            html += '<div class="sffc-crm-form-row">';
            html += '<div class="sffc-crm-form-group"><label>SMTP Host</label><input type="text" name="host" required></div>';
            html += '<div class="sffc-crm-form-group"><label>Port</label><input type="number" name="port" value="587" required></div>';
            html += '</div>';
            html += '<div class="sffc-crm-form-row">';
            html += '<div class="sffc-crm-form-group"><label>Username</label><input type="text" name="username" required></div>';
            html += '<div class="sffc-crm-form-group"><label>Encryption</label><select name="encryption"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></select></div>';
            html += '</div>';
            html += '<div class="sffc-crm-form-group"><label>App Password</label><input type="password" name="password" required></div>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" type="submit">Save SMTP Credentials</button>';
            html += '</form>';
            html += '</div>';

            this.activeModal = 'emailConnect';
            if ($('.sffc-crm-modal').length) {
                this.updateModalContent(html);
            } else {
                this.showModal(html);
            }
            this.bindEmailModalEvents();
        },

        bindEmailModalEvents: function() {
            var self = this;
            $(document).off('click.emailConnect', '.sffc-crm-email-provider').on('click.emailConnect', '.sffc-crm-email-provider', function(e) {
                e.preventDefault();
                self.startEmailOAuth($(this).data('provider'));
            });

            $(document).off('submit.emailConnect', '#sffc-crm-app-form').on('submit.emailConnect', '#sffc-crm-app-form', function(e) {
                e.preventDefault();
                self.submitAppPassword($(this));
            });

            $(document).off('click.emailConnect', '.sffc-crm-email-disconnect').on('click.emailConnect', '.sffc-crm-email-disconnect', function(e) {
                e.preventDefault();
                var id = $(this).data('account-id');
                if (confirm('Disconnect this email account?')) {
                    self.disconnectEmailAccount(id);
                }
            });
        },

        startEmailOAuth: function(provider) {
            var self = this;
            if (!provider) {
                return;
            }
            var $btn = $('.sffc-crm-email-provider[data-provider="' + provider + '"]');
            $btn.addClass('is-busy');
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_start_email_oauth',
                    provider: provider,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.open(response.data.auth_url, 'sffcEmailConnect', 'width=520,height=640');
                    } else {
                        self.handleError(response);
                    }
                },
                error: function(jqXHR) {
                    var message = 'Unable to start OAuth flow.';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                        message = jqXHR.responseJSON.data.message;
                    }
                    self.showError(message);
                },
                complete: function() {
                    $btn.removeClass('is-busy');
                }
            });
        },

        submitAppPassword: function($form) {
            var self = this;
            var $btn = $form.find('button[type="submit"]');
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Connecting...');

            var data = {
                action: 'sffc_crm_connect_app_password',
                nonce: this.config.nonce,
                email: $form.find('input[name="email"]').val(),
                name: $form.find('input[name="name"]').val(),
                host: $form.find('input[name="host"]').val(),
                port: $form.find('input[name="port"]').val(),
                username: $form.find('input[name="username"]').val(),
                password: $form.find('input[name="password"]').val(),
                encryption: $form.find('select[name="encryption"]').val()
            };

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('SMTP account connected');
                        $form[0].reset();
                        self.loadEmailAccounts();
                    } else {
                        self.handleError(response);
                    }
                },
                error: function(jqXHR) {
                    var message = 'Unable to connect account';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                        message = jqXHR.responseJSON.data.message;
                    }
                    self.showError(message);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        disconnectEmailAccount: function(accountId) {
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_disconnect_email_account',
                    account_id: accountId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Email account disconnected');
                        self.loadEmailAccounts();
                    } else {
                        self.handleError(response);
                    }
                },
                error: function(jqXHR) {
                    var message = 'Unable to disconnect account';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                        message = jqXHR.responseJSON.data.message;
                    }
                    self.showError(message);
                }
            });
        },

        showEmailConnectPrompt: function(recruiterId, postId) {
            if (this.isMailtoMode()) {
                this.sendMailClientFallback(recruiterId, postId);
                return;
            }
            var html = '<div class="sffc-crm-email-upsell">';
            html += '<h3>Add One-Click Sending</h3>';
            html += '<p>Connect your mailbox once to send instantly and track recruiter opens.</p>';
            html += '<div class="sffc-crm-email-upsell-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="sffc-crm-prompt-connect">Connect Email</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary" id="sffc-crm-prompt-copy">Copy Message Instead</button>';
            html += '</div></div>';
            this.activeModal = 'emailConnect';
            this.showModal(html);

            var self = this;
            $(document).off('click.emailConnect', '#sffc-crm-prompt-connect').on('click.emailConnect', '#sffc-crm-prompt-connect', function(e) {
                e.preventDefault();
                self.openEmailConnectModal();
            });
            $(document).off('click.emailConnect', '#sffc-crm-prompt-copy').on('click.emailConnect', '#sffc-crm-prompt-copy', function(e) {
                e.preventDefault();
                self.sendMailClientFallback(recruiterId, postId);
            });
        },

        sendMailClientFallback: function(recruiterId, postId) {
            var self = this;
            var message = $('#compose-message').val();
            var subject = $('#compose-subject').val();
            var recruiterEmail = this.outreachState.recruiterEmail;

            if (!message.trim()) {
                this.showError('Please write a message');
                return;
            }
            if (!subject.trim()) {
                this.showError('Please add a subject line');
                return;
            }

            var $btn = $('#compose-send-btn');
            var originalText = $btn.html();
            $btn.html('<svg class="sffc-crm-spinner" width="16" height="16" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="32" stroke-dashoffset="32"><animate attributeName="stroke-dashoffset" dur="1s" values="32;0" repeatCount="indefinite"/></circle></svg> Logging...');
            $btn.prop('disabled', true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_create_outreach',
                    recruiter_id: recruiterId,
                    post_id: postId,
                    channel: 'email',
                    template_id: this.outreachState.templateId,
                    subject: subject,
                    content: message,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (recruiterEmail) {
                            self.openMailto(recruiterEmail, subject, message);
                            self.showSuccess('Email client opened! Send from your email app.');
                        } else {
                            var fullText = 'Subject: ' + subject + "\n\n" + message;
                            navigator.clipboard.writeText(fullText).then(function() {
                                self.showSuccess('Message copied. Paste it into your email.');
                            }).catch(function() {
                                self.showSuccess('Message logged. Recruiter email not available.');
                            });
                        }
                        self.closeModal();
                    } else {
                        self.handleError(response);
                    }
                },
                error: function() {
                    self.showError('Failed to log message');
                },
                complete: function() {
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            });
        },

        sendConnectedEmail: function(recruiterId, postId) {
            var self = this;
            if (this.isMailtoMode()) {
                this.sendMailClientFallback(recruiterId, postId);
                return;
            }
            var message = $('#compose-message').val();
            var subject = $('#compose-subject').val();
            var accountId = $('#compose-send-btn').data('account-id') || null;
            var primary = accountId ? this.emailAccounts.find(function(acc) { return acc.id === accountId; }) : this.getPrimaryEmailAccount();

            if (!message.trim()) {
                this.showError('Please write a message');
                return;
            }
            if (!subject.trim()) {
                this.showError('Please add a subject line');
                return;
            }
            if (!primary) {
                this.showEmailConnectPrompt(recruiterId, postId);
                return;
            }

            var $btn = $('#compose-send-btn');
            var originalText = $btn.html();
            $btn.html('<svg class="sffc-crm-spinner" width="16" height="16" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="32" stroke-dashoffset="32"><animate attributeName="stroke-dashoffset" dur="1s" values="32;0" repeatCount="indefinite"/></circle></svg> Sending...');
            $btn.prop('disabled', true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_send_outreach_email',
                    recruiter_id: recruiterId,
                    post_id: postId,
                    subject: subject,
                    content: message,
                    account_id: primary ? primary.id : null,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Email sent and tracked!');
                        self.closeModal();
                    } else if (response.data && response.data.code === 'email_account_missing') {
                        self.showEmailConnectPrompt(recruiterId, postId);
                    } else {
                        self.handleError(response);
                    }
                },
                error: function() {
                    self.showError('Unable to send email.');
                },
                complete: function() {
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Initialize tabs
         */
        initTabs: function() {
            var defaultTab = $('.sffc-crm-container').data('default-tab') || 'matches';
            var urlTab = this.getTabFromUrl();
            if (urlTab) {
                defaultTab = urlTab;
            }
            this.switchTab(defaultTab);
        },

        /**
         * Determine tab slug from URL query or hash
         */
        getTabFromUrl: function() {
            var search = (typeof URLSearchParams !== 'undefined' && window.location.search) ? new URLSearchParams(window.location.search) : null;
            var tabParam = search ? (search.get('tab') || '') : '';
            if (tabParam) {
                return tabParam.replace(/[^a-z0-9\-]/gi, '').toLowerCase();
            }
            var hash = window.location.hash || '';
            if (hash.indexOf('#panel-') === 0) {
                return hash.replace('#panel-', '').toLowerCase();
            }
            return '';
        },

        /**
         * Switch tab
         */
        switchTab: function(tab) {
            if (!$('#panel-' + tab).length) {
                tab = 'all-roles';
            }
            this.currentTab = tab;

            // Update tab buttons
            $('.sffc-crm-tab').removeClass('active');
            $('.sffc-crm-tab[data-tab="' + tab + '"]').addClass('active');

            // Update bottom nav active state (mobile)
            this.syncBottomNav(tab);

            // Update panels - ensure only one is active
            $('.sffc-crm-panel').removeClass('active').hide();
            $('#panel-' + tab).addClass('active').show();

            // Load tab content if needed
            this.loadTabContent(tab);
        },

        /**
         * Load tab content
         */
        loadTabContent: function(tab) {
            var self = this;
            var $panel = $('#panel-' + tab);

            // Skip if already loaded (has content beyond loading message)
            if ($panel.find('.sffc-crm-loading').length === 0) {
                return;
            }

            switch (tab) {
                case 'all-roles':
                    this.loadAllRoles();
                    break;
                case 'contacts':
                    this.loadRecruitersEnhanced();
                    break;
                case 'matches':
                    this.loadMatches();
                    break;
                case 'recent-posts':
                    this.loadRecentPostsTab();
                    break;
                case 'pipeline':
                    this.loadPipeline();
                    break;
                case 'resume':
                    this.loadResumeTab();
                    break;
                case 'recruiter-intros':
                    this.loadRecruiterIntros();
                    break;
                case 'saved':
                    this.loadSaved();
                    break;
                case 'dashboard':
                    this.loadDashboard();
                    break;
                case 'outreach-lists':
                    this.loadOutreachLists();
                    break;
                case 'smart-apply':
                    this.loadSmartApplyTab();
                    break;
                case 'tasks':
                    this.loadTasks();
                    break;
                case 'analytics':
                    this.loadAnalytics();
                    break;
            }
        },

        handlePasswordForm: function($form) {
            if (!$form || !$form.length) {
                return;
            }

            var self = this;
            var password = $.trim(($form.find('input[name="new_password"]').val() || ''));
            var confirm = $.trim(($form.find('input[name="confirm_password"]').val() || ''));
            var login = ($form.data('login') || '').toString();
            var key = ($form.data('key') || '').toString();
            var $submit = $form.find('button[type="submit"]');
            var submitText = $submit.text();
            var $feedback = $form.find('.sffc-crm-password-feedback');

            var showFeedback = function(type, message) {
                if (!$feedback.length) {
                    return;
                }
                $feedback.removeClass('is-error is-success');
                if (message) {
                    $feedback.addClass(type === 'success' ? 'is-success' : 'is-error').text(message).show();
                } else {
                    $feedback.text('').hide();
                }
            };

            if (!password || password.length < 8) {
                showFeedback('error', 'Please choose a password with at least 8 characters.');
                return;
            }

            if (password !== confirm) {
                showFeedback('error', 'Passwords do not match.');
                return;
            }

            if (!login || !key) {
                showFeedback('error', 'This reset link is no longer valid.');
                return;
            }

            showFeedback(null, '');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_set_password',
                    nonce: this.config.nonce,
                    login: login,
                    key: key,
                    password: password,
                    confirm_password: confirm
                },
                beforeSend: function() {
                    $submit.prop('disabled', true).text('Updating...');
                }
            }).done(function(response) {
                if (response && response.success) {
                    var successMsg = (response.data && response.data.message) ? response.data.message : 'Password updated successfully.';
                    showFeedback('success', successMsg);
                    $form.find('input[type="password"]').val('');
                    if (response.data && response.data.redirect) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 1800);
                    }
                } else {
                    var errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Unable to update password. Please request a new link.';
                    showFeedback('error', errorMsg);
                }
            }).fail(function() {
                showFeedback('error', 'Something went wrong. Please try again.');
            }).always(function() {
                $submit.prop('disabled', false).text(submitText);
            });
        },

        /**
         * Initialize filters
         */
        initFilters: function() {
            // Store initial filter values for reset
            this.initialFilters = {
                sector: '',
                seniority: '',
                country: '',
                firm: '',
                search: ''
            };
        },

        /**
         * Get current filters
         */
        getFilters: function() {
            return {
                sector: $('#filter-sector').val() || '',
                seniority: $('#filter-seniority').val() || '',
                location: $('#filter-country').val() || '',
                search: $('#filter-search').val() || ''
            };
        },

        /**
         * Apply filters
         */
        applyFilters: function() {
            // If on All Roles tab, filter client-side
            if (this.currentTab === 'all-roles') {
                this.filterAllRoles();
            } else {
                // Otherwise reload feed with filters
                this.currentPage = 1;
                this.loadFeed(true);
            }
        },

        /**
         * Clear filters
         */
        clearFilters: function() {
            $('#filter-sector').val('');
            $('#filter-seniority').val('');
            $('#filter-country').val('');
            $('#filter-firm').val('');
            $('#filter-search').val('');
            this.applyFilters();
        },

        /**
         * Filter All Roles client-side
         */
        filterAllRoles: function() {
            var filters = this.getFilters();
            var $panel = $('#panel-all-roles');
            var $rows = $panel.find('.sffc-crm-match-row');
            var visibleCount = 0;

            // Get firm filter value
            var firmFilter = $('#filter-firm').val() || '';

            $rows.each(function() {
                var $row = $(this);
                var show = true;

                // Get row data
                var title = $row.find('.sffc-crm-match-title').text().toLowerCase();
                var meta = $row.find('.sffc-crm-match-meta').text().toLowerCase();
                var sector = ($row.data('sector') || '').toString().toLowerCase();
                var seniority = ($row.data('seniority') || '').toString().toLowerCase();
                var company = ($row.data('company') || '').toString().toLowerCase();
                var location = ($row.data('location') || '').toString().toLowerCase();

                // Search filter (search across title, company, and location)
                if (filters.search) {
                    var searchTerm = filters.search.toLowerCase();
                    if (title.indexOf(searchTerm) === -1 &&
                        company.indexOf(searchTerm) === -1 &&
                        location.indexOf(searchTerm) === -1) {
                        show = false;
                    }
                }

                // Sector filter
                if (filters.sector && sector) {
                    if (sector !== filters.sector.toLowerCase()) {
                        show = false;
                    }
                }

                // Seniority filter
                if (filters.seniority && seniority) {
                    if (seniority !== filters.seniority.toLowerCase()) {
                        show = false;
                    }
                }

                // Location/Country filter
                if (filters.location && location) {
                    if (location.toLowerCase().indexOf(filters.location.toLowerCase()) === -1) {
                        show = false;
                    }
                }

                // Firm/Company filter
                if (firmFilter && company) {
                    if (company.toLowerCase().indexOf(firmFilter.toLowerCase()) === -1) {
                        show = false;
                    }
                }

                if (show) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });

            // Update header count
            var totalDisplayed = $rows.length;
            var totalAvailable = this.allRolesData && this.allRolesData.items ? this.allRolesData.items.length : totalDisplayed;

            if (visibleCount === totalAvailable && totalDisplayed === totalAvailable) {
                $panel.find('.sffc-crm-matches-header p').text('Showing ' + visibleCount + ' available opportunities');
            } else {
                $panel.find('.sffc-crm-matches-header p').text('Showing ' + visibleCount + ' of ' + totalAvailable + ' available opportunities');
            }

            // Update or hide Load More button based on filters
            var $loadMoreBtn = $panel.find('#load-more-all-roles');
            if ($loadMoreBtn.length) {
                var remainingUnfiltered = totalAvailable - totalDisplayed;
                if (remainingUnfiltered > 0) {
                    $loadMoreBtn.html(
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">' +
                        '<circle cx="12" cy="12" r="10"></circle>' +
                        '<polyline points="8 12 12 16 16 12"></polyline>' +
                        '<line x1="12" y1="8" x2="12" y2="16"></line>' +
                        '</svg>' +
                        'Load More (' + remainingUnfiltered + ' remaining)'
                    );
                }
            }
        },

        /**
         * Load feed posts
         */
        loadFeed: function(replace) {
            var self = this;

            if (this.isLoading) return;
            this.isLoading = true;

            var filters = this.getFilters();
            filters.page = this.currentPage;
            filters.per_page = 20;
            filters.action = 'sffc_crm_get_feed';
            filters.nonce = this.config.nonce;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: filters,
                success: function(response) {
                    if (response.success) {
                        self.renderFeed(response.data.posts, replace);
                        self.updateLoadMore(response.data.has_more);
                    }
                },
                error: function() {
                    self.showError('Failed to load posts');
                },
                complete: function() {
                    self.isLoading = false;
                }
            });
        },

        /**
         * Render feed posts
         */
        renderFeed: function(posts, replace) {
            this.ensureReachOutHelper();
            var $grid = $('#crm-feed-grid');

            if (replace) {
                $grid.empty();
            }

            if (posts.length === 0 && replace) {
                $grid.html(this.getEmptyState('No posts found', 'Try adjusting your filters.'));
                return;
            }

            if (replace) {
                this.renderedPostCount = 0;
            }

            posts.forEach(function(post) {
                $grid.append(this.renderPostRow(post, this.renderedPostCount));
                this.renderedPostCount += 1;
            }, this);
        },

        /**
         * Render post card HTML
         */
        renderPostCard: function(post) {
            var isSaved = post.is_saved == 1;
            var postedAgo = this.timeAgo(post.posted_at);

            var html = '<div class="sffc-crm-post-card" data-post-id="' + post.id + '">';

            // Recruiter header
            html += '<div class="sffc-crm-post-recruiter">';
            if (post.recruiter_photo) {
                html += '<img src="' + post.recruiter_photo + '" alt="" class="sffc-crm-avatar">';
            } else {
                var initial = (post.recruiter_name || 'R').charAt(0);
                html += '<div class="sffc-crm-avatar sffc-crm-avatar-placeholder">' + initial + '</div>';
            }
            html += '<div class="sffc-crm-recruiter-info">';
            html += '<span class="sffc-crm-recruiter-name">' + this.escapeHtml(post.recruiter_name || 'Unknown') + '</span>';
            html += '<span class="sffc-crm-recruiter-firm">' + this.escapeHtml(post.recruiter_firm || '') + '</span>';
            html += '</div>';
            html += '<span class="sffc-crm-post-time">' + postedAgo + '</span>';
            html += '</div>';

            // Role info
            html += '<div class="sffc-crm-post-role">';
            html += '<h3 class="sffc-crm-post-title">' + this.escapeHtml(post.role_title) + '</h3>';
            if (post.company) {
                html += '<span class="sffc-crm-post-company">' + this.escapeHtml(post.company) + '</span>';
            }
            html += '</div>';

            // Meta tags
            html += '<div class="sffc-crm-post-meta">';
            if (post.location) {
                html += '<span class="sffc-crm-tag">' + this.escapeHtml(post.location) + '</span>';
            }
            if (post.salary_text) {
                html += '<span class="sffc-crm-tag">' + this.escapeHtml(post.salary_text) + '</span>';
            }
            if (post.seniority) {
                html += '<span class="sffc-crm-tag sffc-crm-tag-seniority">' + post.seniority.toUpperCase() + '</span>';
            }
            if (post.is_remote == 1) {
                html += '<span class="sffc-crm-tag sffc-crm-tag-remote">Remote</span>';
            }
            html += '</div>';

            // Snippet
            if (post.content_snippet) {
                html += '<p class="sffc-crm-post-snippet">' + this.escapeHtml(post.content_snippet) + '</p>';
            }

            // Actions
            html += '<div class="sffc-crm-post-actions">';
            if (post.application_url) {
                html += '<a href="' + this.escapeHtml(post.application_url) + '" class="sffc-crm-btn sffc-crm-btn-success" target="_blank" rel="noopener noreferrer">Apply</a>';
            }
            html += this.buildReachOutButton({
                postId: post.id,
                recruiterId: post.recruiter_id
            });
            html += '<button class="sffc-crm-btn sffc-crm-btn-icon sffc-crm-save-btn ' + (isSaved ? 'is-saved' : '') + '" data-action="save" data-post-id="' + post.id + '" title="' + (isSaved ? 'Saved' : 'Save') + '">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="' + (isSaved ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>';
            html += '</button>';
            html += '</div>';

            html += '</div>';

            return html;
        },

        /**
         * Render post row HTML (list format)
         */
        renderPostRow: function(post, previewIndex) {
            this.ensureReachOutHelper();
            var isSaved = post.is_saved == 1;
            var postedAgo = this.timeAgo(post.posted_at);

            var html = '<div class="sffc-crm-post-row" data-post-id="' + post.id + '">';

            var recruiterName = post.recruiter_name || 'Unknown Recruiter';
            var recruiterFirm = post.recruiter_firm || '';
            if (!this.config.isPremium) {
                if (post.recruiter_display_name) {
                    recruiterName = post.recruiter_display_name;
                }
                if (post.recruiter_display_company) {
                    recruiterFirm = post.recruiter_display_company;
                }
            }

            // Recruiter avatar
            var revealAvatar = this.config.isPremium || (typeof previewIndex === 'number' && previewIndex < 2);
            var avatarClass = 'sffc-crm-row-avatar' + (revealAvatar ? '' : ' is-blurred');
            html += '<div class="' + avatarClass + '">';
            if (post.recruiter_photo) {
                html += '<img src="' + post.recruiter_photo + '" alt="" class="sffc-crm-avatar">';
            } else {
                var initial = (recruiterName || 'R').charAt(0);
                html += '<div class="sffc-crm-avatar sffc-crm-avatar-placeholder">' + initial + '</div>';
            }
            html += '</div>';

            // Main content
            html += '<div class="sffc-crm-row-content">';
            html += '<div class="sffc-crm-row-header">';
            html += '<h3 class="sffc-crm-row-title">' + this.escapeHtml(post.role_title) + '</h3>';
            html += '<span class="sffc-crm-row-time">' + postedAgo + '</span>';
            html += '</div>';

            html += '<div class="sffc-crm-row-meta">';
            html += '<span class="sffc-crm-row-recruiter">' + this.escapeHtml(recruiterName);
            if (recruiterFirm) {
                html += ' <span class="sffc-crm-row-firm">at ' + this.escapeHtml(recruiterFirm) + '</span>';
            }
            html += '</span>';
            if (post.company) {
                html += '<span class="sffc-crm-row-sep">•</span>';
                html += '<span class="sffc-crm-row-company">' + this.escapeHtml(post.company) + '</span>';
            }
            html += '</div>';

            // Tags
            html += '<div class="sffc-crm-row-tags">';
            if (post.location) {
                html += '<span class="sffc-crm-tag sffc-crm-tag-location"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ' + this.escapeHtml(post.location) + '</span>';
            }
            if (post.salary_text) {
                html += '<span class="sffc-crm-tag">' + this.escapeHtml(post.salary_text) + '</span>';
            }
            if (post.seniority) {
                html += '<span class="sffc-crm-tag sffc-crm-tag-seniority">' + post.seniority.toUpperCase() + '</span>';
            }
            if (post.is_remote == 1) {
                html += '<span class="sffc-crm-tag sffc-crm-tag-remote">Remote</span>';
            }
            html += '</div>';
            html += '</div>';

            // Actions
            html += '<div class="sffc-crm-row-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-icon sffc-crm-save-btn ' + (isSaved ? 'is-saved' : '') + '" data-action="save" data-post-id="' + post.id + '" title="' + (isSaved ? 'Saved' : 'Save') + '">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="' + (isSaved ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>';
            html += '</button>';
            if (post.application_url) {
                html += '<a href="' + this.escapeHtml(post.application_url) + '" class="sffc-crm-btn sffc-crm-btn-success sffc-crm-btn-small" target="_blank" rel="noopener noreferrer">Apply</a>';
            }
            // Add to Outreach List button (if recruiter exists)
            if (post.recruiter_id) {
                html += '<div class="sffc-crm-add-list-wrapper">';
                html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-small sffc-crm-add-list-toggle" ';
                html += 'data-recruiter-id="' + post.recruiter_id + '">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                html += '<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>';
                html += '</svg>';
                html += '<span> Add to List</span>';
                html += '</button>';
                html += '<div class="sffc-crm-add-list-dropdown" aria-hidden="true"></div>';
                html += '</div>';
            }
            html += '<button class="sffc-crm-btn sffc-crm-btn-outline sffc-crm-btn-small sffc-crm-gap-btn" data-post-id="' + post.id + '">';
            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<circle cx="11" cy="11" r="7"></circle><line x1="16.65" y1="16.65" x2="21" y2="21"></line>';
            html += '</svg>';
            html += '<span>Scan CV</span>';
            html += '</button>';
            html += this.buildReachOutButton({
                postId: post.id,
                recruiterId: post.recruiter_id,
                small: true
            });
            html += '</div>';

            html += '</div>';

            return html;
        },

        /**
         * Load more posts
         */
        loadMorePosts: function() {
            this.currentPage++;
            this.loadFeed(false);
        },

        updateLoadMore: function(hasMore) {
            var $loadMore = $('#crm-load-more');
            if (hasMore) {
                $loadMore.show();
            } else {
                $loadMore.hide();
            }
        },

        togglePreviewCta: function(show) {
            var $cta = $('#crm-preview-cta');
            if (!$cta.length) {
                return;
            }
            if (show) {
                $cta.show();
            } else {
                $cta.hide();
            }
        },

        /**
         * Save post
         */
        savePost: function(postId, $btn) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_save_post',
                    post_id: postId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.addClass('is-saved');
                        $btn.find('svg').attr('fill', 'currentColor');
                        $btn.attr('title', 'Saved to Watchlist');
                        self.showSuccess('Added to Watchlist');

                        // Force watchlist (smart-apply) tab to reload next time
                        var $watchlistPanel = $('#panel-smart-apply');
                        if ($watchlistPanel.find('.sffc-crm-loading').length === 0) {
                            $watchlistPanel.html('<div class="sffc-crm-loading">Loading watchlist...</div>');
                        }

                        // Also force saved tab to reload for backward compatibility
                        var $savedPanel = $('#panel-saved');
                        if ($savedPanel.find('.sffc-crm-loading').length === 0) {
                            $savedPanel.html('<div class="sffc-crm-loading">Loading saved...</div>');
                        }
                    } else {
                        self.handleError(response);
                    }
                }
            });
        },

        /**
         * Unsave post
         */
        unsavePost: function(postId, $btn) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_unsave_post',
                    post_id: postId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.removeClass('is-saved');
                        $btn.find('svg').attr('fill', 'none');
                        $btn.attr('title', 'Add to Watchlist');

                        // If we're on the watchlist (smart-apply) tab, remove the item from the list
                        if (self.currentTab === 'smart-apply') {
                            $btn.closest('.sffc-crm-post-row, .sffc-crm-match-row').fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else if (self.currentTab === 'saved') {
                            // Also handle saved tab for backward compatibility
                            $btn.closest('.sffc-crm-post-row, .sffc-crm-match-row').fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            // Force watchlist tab to reload next time
                            var $watchlistPanel = $('#panel-smart-apply');
                            if ($watchlistPanel.find('.sffc-crm-loading').length === 0) {
                                $watchlistPanel.html('<div class="sffc-crm-loading">Loading watchlist...</div>');
                            }

                            // Also force saved tab to reload for backward compatibility
                            var $savedPanel = $('#panel-saved');
                            if ($savedPanel.find('.sffc-crm-loading').length === 0) {
                                $savedPanel.html('<div class="sffc-crm-loading">Loading saved...</div>');
                            }
                        }
                    }
                }
            });
        },

        /**
         * Load recruiters
         */
        loadRecruiters: function() {
            var self = this;
            var $panel = $('#panel-contacts');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_recruiters',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderRecruiters(response.data);
                    }
                }
            });
        },

        /**
         * Render recruiters
         */
        renderRecruiters: function(data) {
            var $panel = $('#panel-contacts');
            var html = '';

            // Header with stats
            html += '<div class="sffc-crm-recruiters-header">';
            html += '<h2>My Recruiters</h2>';
            html += '<div class="sffc-crm-stats-inline">';
            html += '<span class="sffc-crm-stat"><strong>' + data.stats.total + '</strong> Total</span>';
            html += '<span class="sffc-crm-stat"><strong>' + data.stats.replied + '</strong> Replied</span>';
            html += '<span class="sffc-crm-stat"><strong>' + data.stats.in_conversation + '</strong> In Conversation</span>';
            html += '</div>';
            html += '</div>';

            // List
            html += '<div class="sffc-crm-recruiters-list">';

            if (data.recruiters.length === 0) {
                html += this.getEmptyState('No recruiters saved yet', 'Save recruiters from the Feed to track your relationships.');
            } else {
                data.recruiters.forEach(function(recruiter) {
                    html += this.renderRecruiterRow(recruiter);
                }, this);
            }

            html += '</div>';

            $panel.html(html);
        },

        /**
         * Render recruiter row
         */
        renderRecruiterRow: function(recruiter) {
            var statusColors = {
                new: '#6b7280',
                contacted: '#3b82f6',
                replied: '#10b981',
                in_conversation: '#0d353e',
                dormant: '#f59e0b'
            };

            var html = '<div class="sffc-crm-recruiter-row" data-recruiter-id="' + recruiter.id + '">';

            // Avatar
            html += '<div class="sffc-crm-recruiter-avatar">';
            if (recruiter.photo_url) {
                html += '<img src="' + recruiter.photo_url + '" alt="">';
            } else {
                html += '<div class="sffc-crm-avatar-placeholder">' + recruiter.name.charAt(0) + '</div>';
            }
            html += '</div>';

            // Details
            html += '<div class="sffc-crm-recruiter-details">';
            html += '<span class="sffc-crm-recruiter-name">' + this.escapeHtml(recruiter.name) + '</span>';
            html += '<span class="sffc-crm-recruiter-firm">' + this.escapeHtml(recruiter.firm || '') + '</span>';
            html += '</div>';

            // Status
            html += '<div class="sffc-crm-recruiter-status">';
            var status = recruiter.status || 'new';
            html += '<span class="sffc-crm-status-badge" style="background: ' + (statusColors[status] || '#6b7280') + ';">';
            html += status.replace('_', ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
            html += '</span>';
            html += '</div>';

            // Last activity
            html += '<div class="sffc-crm-recruiter-last">';
            if (recruiter.last_contacted_at) {
                html += 'Contacted ' + this.timeAgo(recruiter.last_contacted_at);
            } else {
                html += 'Saved ' + this.timeAgo(recruiter.saved_at);
            }
            html += '</div>';

            // Actions
            html += '<div class="sffc-crm-recruiter-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-small" data-action="view-recruiter">View</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-btn-small" data-action="reach-out-recruiter">Send CV</button>';
            html += '</div>';

            html += '</div>';

            return html;
        },

        /**
         * Load pipeline
         */
        loadPipeline: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_pipeline',
                    view: 'kanban',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderPipeline(response.data);
                    }
                }
            });
        },

        /**
         * Render pipeline
         */
        renderPipeline: function(data) {
            var self = this;
            var $panel = $('#panel-pipeline');
            var stages = data.stages;
            var pipeline = data.pipeline;

            // Count total items
            var totalItems = 0;
            Object.keys(pipeline).forEach(function(stageKey) {
                if (pipeline[stageKey] && pipeline[stageKey].items) {
                    totalItems += pipeline[stageKey].items.length;
                }
            });

            var html = '';

            // Header with view toggle
            html += '<div class="sffc-crm-pipeline-header">';
            html += '<div class="sffc-crm-pipeline-header-top">';
            html += '<h2>My Pipeline</h2>';
            html += '<button type="button" class="sffc-crm-pipeline-help-btn" data-action="toggle-pipeline-help" title="How does the pipeline work?">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
            html += '</button>';
            html += '</div>';
            html += '<div class="sffc-crm-pipeline-controls">';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-add-lead-btn" data-action="open-add-lead-modal">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>';
            html += 'Add Lead';
            html += '</button>';
            html += '<div class="sffc-crm-view-toggle">';
            html += '<button class="sffc-crm-view-btn active" data-view="kanban" title="Kanban View">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>';
            html += '</button>';
            html += '<button class="sffc-crm-view-btn" data-view="list" title="List View">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';
            html += '<div class="sffc-crm-stats-inline">';
            html += '<span class="sffc-crm-stat"><strong>' + data.stats.total + '</strong> Total</span>';
            html += '<span class="sffc-crm-stat sffc-crm-stat-success"><strong>' + (data.stats.by_outcome.won || 0) + '</strong> Won</span>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            // Collapsible help section
            html += '<div class="sffc-crm-pipeline-help" id="pipeline-help" style="display: none;">';
            html += '<div class="sffc-crm-pipeline-help-content">';
            html += '<div class="sffc-crm-pipeline-help-header">';
            html += '<h3>How Your Pipeline Works</h3>';
            html += '<button type="button" class="sffc-crm-pipeline-help-close" data-action="toggle-pipeline-help">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';
            html += '<p class="sffc-crm-pipeline-help-intro">Track every job opportunity from first contact to offer. Move cards between stages as your conversations progress.</p>';
            html += '<div class="sffc-crm-pipeline-stages-guide">';
            html += '<div class="sffc-crm-pipeline-stage-item"><span class="sffc-crm-stage-dot" style="background: #6b7280;"></span><strong>Interested</strong><span>Roles you want to pursue</span></div>';
            html += '<div class="sffc-crm-pipeline-stage-item"><span class="sffc-crm-stage-dot" style="background: #3b82f6;"></span><strong>Reached Out</strong><span>You\'ve made first contact</span></div>';
            html += '<div class="sffc-crm-pipeline-stage-item"><span class="sffc-crm-stage-dot" style="background: #0d353e;"></span><strong>In Conversation</strong><span>Active dialogue with recruiter</span></div>';
            html += '<div class="sffc-crm-pipeline-stage-item"><span class="sffc-crm-stage-dot" style="background: #f59e0b;"></span><strong>Interviewing</strong><span>Formal interview process</span></div>';
            html += '<div class="sffc-crm-pipeline-stage-item"><span class="sffc-crm-stage-dot" style="background: #10b981;"></span><strong>Offer</strong><span>You\'ve received an offer</span></div>';
            html += '<div class="sffc-crm-pipeline-stage-item"><span class="sffc-crm-stage-dot" style="background: #ef4444;"></span><strong>Closed</strong><span>Won, lost, or withdrawn</span></div>';
            html += '</div>';
            html += '<div class="sffc-crm-pipeline-help-tips">';
            html += '<h4>Tips</h4>';
            html += '<ul>';
            html += '<li><strong>Add to pipeline</strong> from any recruiter post by clicking "Add to Pipeline"</li>';
            html += '<li><strong>Drag cards</strong> between columns to update their stage</li>';
            html += '<li><strong>Click a card</strong> to add notes, set next actions, or view history</li>';
            html += '</ul>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            // Show empty state if no items
            if (totalItems === 0) {
                html += '<div class="sffc-crm-pipeline-empty">';
                html += '<div class="sffc-crm-pipeline-empty-icon">';
                html += '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">';
                html += '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>';
                html += '</svg>';
                html += '</div>';
                html += '<h3>Your pipeline is empty</h3>';
                html += '<p>Start tracking job opportunities by adding roles you\'re interested in. Your pipeline helps you manage every conversation from first contact to offer.</p>';
                html += '<div class="sffc-crm-pipeline-empty-steps">';
                html += '<div class="sffc-crm-pipeline-empty-step">';
                html += '<span class="sffc-crm-pipeline-step-num">1</span>';
                html += '<div>';
                html += '<strong>Browse the Feed</strong>';
                html += '<p>Find roles posted by recruiters in your target sectors</p>';
                html += '</div>';
                html += '</div>';
                html += '<div class="sffc-crm-pipeline-empty-step">';
                html += '<span class="sffc-crm-pipeline-step-num">2</span>';
                html += '<div>';
                html += '<strong>Add to Pipeline</strong>';
                html += '<p>Click "Add to Pipeline" on any role you want to track</p>';
                html += '</div>';
                html += '</div>';
                html += '<div class="sffc-crm-pipeline-empty-step">';
                html += '<span class="sffc-crm-pipeline-step-num">3</span>';
                html += '<div>';
                html += '<strong>Track Your Progress</strong>';
                html += '<p>Move opportunities through stages as conversations progress</p>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary" data-action="go-to-feed">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>';
                html += 'Browse Feed';
                html += '</button>';
                html += '</div>';
            } else {
                // Kanban board
                html += '<div class="sffc-crm-pipeline-kanban" id="pipeline-kanban">';

            Object.keys(stages).forEach(function(stageKey) {
                var stage = stages[stageKey];
                var stageData = pipeline[stageKey] || { items: [] };

                html += '<div class="sffc-crm-pipeline-column" data-stage="' + stageKey + '">';
                html += '<div class="sffc-crm-pipeline-column-header" style="border-color: ' + stage.color + ';">';
                html += '<span class="sffc-crm-pipeline-stage-name">' + stage.label + '</span>';
                html += '<span class="sffc-crm-pipeline-count">' + stageData.items.length + '</span>';
                html += '</div>';

                html += '<div class="sffc-crm-pipeline-items" data-stage="' + stageKey + '">';
                stageData.items.forEach(function(item) {
                    html += self.renderPipelineCard(item);
                });
                html += '</div>';

                html += '</div>';
            });

            html += '</div>';

                // List view (hidden by default)
                html += '<div class="sffc-crm-pipeline-list" id="pipeline-list" style="display: none;">';
                html += this.renderPipelineListView(data, stages);
                html += '</div>';
            }

            $panel.html(html);

            // Bind help toggle
            this.bindPipelineHelp();

            // Initialize drag and drop only if we have items
            if (totalItems > 0) {
                this.initPipelineDragDrop();
                this.bindPipelineViewToggle();
            }
        },

        /**
         * Bind pipeline help toggle
         */
        bindPipelineHelp: function() {
            var self = this;

            $(document).off('click.pipelineHelp').on('click.pipelineHelp', '[data-action="toggle-pipeline-help"]', function(e) {
                e.preventDefault();
                $('#pipeline-help').slideToggle(200);
            });

            $(document).off('click.goToFeed').on('click.goToFeed', '[data-action="go-to-feed"]', function(e) {
                e.preventDefault();
                self.switchTab('feed');
            });

            // Add Lead Modal
            $(document).off('click.openAddLeadModal').on('click.openAddLeadModal', '[data-action="open-add-lead-modal"]', function(e) {
                e.preventDefault();
                self.openAddLeadModal();
            });
        },

        /**
         * Open Add Lead Modal
         */
        openAddLeadModal: function() {
            var self = this;
            var stages = this.config.stages || {
                interested: { label: 'Expressed Interest' },
                reached_out: { label: 'Reached Out' },
                in_conversation: { label: 'In Conversation' },
                interviewing: { label: 'Interviewing' },
                offer: { label: 'Offer' }
            };

            var modalHtml = '<div class="sffc-crm-modal" id="add-lead-modal">';
            modalHtml += '<div class="sffc-crm-modal-backdrop"></div>';
            modalHtml += '<div class="sffc-crm-modal-content sffc-crm-modal-content--add-lead">';
            modalHtml += '<div class="sffc-crm-modal-header">';
            modalHtml += '<h3>Add Lead</h3>';
            modalHtml += '<button type="button" class="sffc-crm-modal-close" data-action="close-add-lead-modal">';
            modalHtml += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            modalHtml += '</button>';
            modalHtml += '</div>';
            modalHtml += '<div class="sffc-crm-modal-body">';
            modalHtml += '<p class="sffc-crm-add-lead-intro">Track opportunities from any source - LinkedIn, Indeed, referrals, or company websites.</p>';
            modalHtml += '<form id="add-lead-form" class="sffc-crm-add-lead-form">';

            // Required fields
            modalHtml += '<div class="sffc-crm-form-row">';
            modalHtml += '<div class="sffc-crm-form-group sffc-crm-form-group--required">';
            modalHtml += '<label for="lead-role-title">Role Title <span class="required">*</span></label>';
            modalHtml += '<input type="text" id="lead-role-title" name="role_title" placeholder="e.g. Finance Director, VP Strategy" required>';
            modalHtml += '</div>';
            modalHtml += '<div class="sffc-crm-form-group sffc-crm-form-group--required">';
            modalHtml += '<label for="lead-company">Company <span class="required">*</span></label>';
            modalHtml += '<input type="text" id="lead-company" name="company" placeholder="e.g. Goldman Sachs, McKinsey" required>';
            modalHtml += '</div>';
            modalHtml += '</div>';

            // Source and Stage
            modalHtml += '<div class="sffc-crm-form-row">';
            modalHtml += '<div class="sffc-crm-form-group">';
            modalHtml += '<label for="lead-source">Source</label>';
            modalHtml += '<select id="lead-source" name="source">';
            modalHtml += '<option value="linkedin">LinkedIn</option>';
            modalHtml += '<option value="indeed">Indeed</option>';
            modalHtml += '<option value="referral">Referral</option>';
            modalHtml += '<option value="company_website">Company Website</option>';
            modalHtml += '<option value="other">Other</option>';
            modalHtml += '</select>';
            modalHtml += '</div>';
            modalHtml += '<div class="sffc-crm-form-group">';
            modalHtml += '<label for="lead-stage">Starting Stage</label>';
            modalHtml += '<select id="lead-stage" name="stage">';
            Object.keys(stages).forEach(function(key) {
                if (key !== 'closed') {
                    var selected = key === 'interested' ? ' selected' : '';
                    modalHtml += '<option value="' + key + '"' + selected + '>' + stages[key].label + '</option>';
                }
            });
            modalHtml += '</select>';
            modalHtml += '</div>';
            modalHtml += '</div>';

            // Location and Job URL
            modalHtml += '<div class="sffc-crm-form-row">';
            modalHtml += '<div class="sffc-crm-form-group">';
            modalHtml += '<label for="lead-location">Location</label>';
            modalHtml += '<input type="text" id="lead-location" name="location" placeholder="e.g. Global, Remote, New York">';
            modalHtml += '</div>';
            modalHtml += '<div class="sffc-crm-form-group">';
            modalHtml += '<label for="lead-external-url">Job URL</label>';
            modalHtml += '<input type="url" id="lead-external-url" name="external_url" placeholder="https://...">';
            modalHtml += '</div>';
            modalHtml += '</div>';

            // Contact section header
            modalHtml += '<div class="sffc-crm-form-section-header">';
            modalHtml += '<span>Contact Details (optional)</span>';
            modalHtml += '</div>';

            // Contact fields
            modalHtml += '<div class="sffc-crm-form-row sffc-crm-form-row--three">';
            modalHtml += '<div class="sffc-crm-form-group">';
            modalHtml += '<label for="lead-contact-name">Contact Name</label>';
            modalHtml += '<input type="text" id="lead-contact-name" name="contact_name" placeholder="e.g. John Smith">';
            modalHtml += '</div>';
            modalHtml += '<div class="sffc-crm-form-group">';
            modalHtml += '<label for="lead-contact-email">Contact Email</label>';
            modalHtml += '<input type="email" id="lead-contact-email" name="contact_email" placeholder="email@example.com">';
            modalHtml += '</div>';
            modalHtml += '<div class="sffc-crm-form-group">';
            modalHtml += '<label for="lead-contact-linkedin">LinkedIn URL</label>';
            modalHtml += '<input type="url" id="lead-contact-linkedin" name="contact_linkedin" placeholder="https://linkedin.com/in/...">';
            modalHtml += '</div>';
            modalHtml += '</div>';

            // Salary range
            modalHtml += '<div class="sffc-crm-form-row">';
            modalHtml += '<div class="sffc-crm-form-group">';
            modalHtml += '<label for="lead-salary-min">Salary Min</label>';
            modalHtml += '<input type="number" id="lead-salary-min" name="salary_min" placeholder="e.g. 80000">';
            modalHtml += '</div>';
            modalHtml += '<div class="sffc-crm-form-group">';
            modalHtml += '<label for="lead-salary-max">Salary Max</label>';
            modalHtml += '<input type="number" id="lead-salary-max" name="salary_max" placeholder="e.g. 120000">';
            modalHtml += '</div>';
            modalHtml += '</div>';

            // Notes
            modalHtml += '<div class="sffc-crm-form-group">';
            modalHtml += '<label for="lead-notes">Notes</label>';
            modalHtml += '<textarea id="lead-notes" name="notes" rows="3" placeholder="Any additional notes about this opportunity..."></textarea>';
            modalHtml += '</div>';

            modalHtml += '</form>';
            modalHtml += '</div>';
            modalHtml += '<div class="sffc-crm-modal-footer">';
            modalHtml += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary" data-action="close-add-lead-modal">Cancel</button>';
            modalHtml += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary" data-action="submit-add-lead">Add to Pipeline</button>';
            modalHtml += '</div>';
            modalHtml += '</div>';
            modalHtml += '</div>';

            // Remove existing modal if any
            $('#add-lead-modal').remove();

            // Append and show modal
            $('body').append(modalHtml);
            $('#add-lead-modal').addClass('is-open');

            // Bind modal events
            this.bindAddLeadModal();
        },

        /**
         * Bind Add Lead Modal events
         */
        bindAddLeadModal: function() {
            var self = this;

            // Close modal
            $(document).off('click.closeAddLeadModal').on('click.closeAddLeadModal', '[data-action="close-add-lead-modal"], .sffc-crm-modal-backdrop', function(e) {
                if ($(e.target).is('.sffc-crm-modal-backdrop') || $(e.target).closest('[data-action="close-add-lead-modal"]').length) {
                    self.closeAddLeadModal();
                }
            });

            // Submit form
            $(document).off('click.submitAddLead').on('click.submitAddLead', '[data-action="submit-add-lead"]', function(e) {
                e.preventDefault();
                self.submitAddLead();
            });

            // Form submit on enter
            $('#add-lead-form').off('submit').on('submit', function(e) {
                e.preventDefault();
                self.submitAddLead();
            });
        },

        /**
         * Close Add Lead Modal
         */
        closeAddLeadModal: function() {
            $('#add-lead-modal').removeClass('is-open');
            setTimeout(function() {
                $('#add-lead-modal').remove();
            }, 300);
        },

        /**
         * Submit Add Lead form
         */
        submitAddLead: function() {
            var self = this;
            var $form = $('#add-lead-form');
            var $submitBtn = $('[data-action="submit-add-lead"]');

            // Validate required fields
            var roleTitle = $('#lead-role-title').val().trim();
            var company = $('#lead-company').val().trim();

            if (!roleTitle || !company) {
                self.showError('Role title and company are required');
                return;
            }

            // Disable button
            $submitBtn.prop('disabled', true).text('Adding...');

            // Collect form data
            var formData = {
                action: 'sffc_crm_add_manual_lead',
                nonce: self.config.nonce,
                role_title: roleTitle,
                company: company,
                source: $('#lead-source').val(),
                stage: $('#lead-stage').val(),
                location: $('#lead-location').val().trim(),
                external_url: $('#lead-external-url').val().trim(),
                contact_name: $('#lead-contact-name').val().trim(),
                contact_email: $('#lead-contact-email').val().trim(),
                contact_linkedin: $('#lead-contact-linkedin').val().trim(),
                salary_min: $('#lead-salary-min').val(),
                salary_max: $('#lead-salary-max').val(),
                notes: $('#lead-notes').val().trim()
            };

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Lead added to pipeline');
                        self.closeAddLeadModal();
                        self.loadPipeline(); // Refresh pipeline
                    } else {
                        self.showError(response.data?.message || 'Failed to add lead');
                        $submitBtn.prop('disabled', false).text('Add to Pipeline');
                    }
                },
                error: function() {
                    self.showError('Network error. Please try again.');
                    $submitBtn.prop('disabled', false).text('Add to Pipeline');
                }
            });
        },

        /**
         * Render pipeline list view
         */
        renderPipelineListView: function(data, stages) {
            var html = '';

            // Combine all items
            var allItems = [];
            Object.keys(data.pipeline).forEach(function(stageKey) {
                var stageData = data.pipeline[stageKey];
                if (stageData.items) {
                    stageData.items.forEach(function(item) {
                        item.stage = stageKey;
                        item.stage_label = stages[stageKey] ? stages[stageKey].label : stageKey;
                        item.stage_color = stages[stageKey] ? stages[stageKey].color : '#6b7280';
                        allItems.push(item);
                    });
                }
            });

            if (allItems.length === 0) {
                html += this.getEmptyState('No opportunities yet', 'Add posts to your pipeline to track your job search progress.');
                return html;
            }

            html += '<table class="sffc-crm-pipeline-table">';
            html += '<thead>';
            html += '<tr>';
            html += '<th>Role</th>';
            html += '<th>Company</th>';
            html += '<th>Recruiter</th>';
            html += '<th>Stage</th>';
            html += '<th>Next Action</th>';
            html += '<th>Updated</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';

            allItems.forEach(function(item) {
                html += '<tr class="sffc-crm-pipeline-list-row" data-pipeline-id="' + item.id + '">';
                html += '<td class="sffc-crm-pl-role">' + this.escapeHtml(item.role_title || 'Opportunity') + '</td>';
                html += '<td class="sffc-crm-pl-company">' + this.escapeHtml(item.company || '-') + '</td>';
                html += '<td class="sffc-crm-pl-recruiter">' + this.escapeHtml(item.recruiter_name || 'Unknown') + '</td>';
                html += '<td><span class="sffc-crm-status-badge" style="background: ' + item.stage_color + ';">' + item.stage_label + '</span></td>';
                html += '<td class="sffc-crm-pl-action">' + this.escapeHtml(item.next_action || '-') + '</td>';
                html += '<td class="sffc-crm-pl-date">' + this.timeAgo(item.updated_at) + '</td>';
                html += '</tr>';
            }, this);

            html += '</tbody>';
            html += '</table>';

            return html;
        },

        /**
         * Bind pipeline view toggle
         */
        bindPipelineViewToggle: function() {
            var self = this;

            $(document).off('click', '.sffc-crm-view-btn').on('click', '.sffc-crm-view-btn', function() {
                var view = $(this).data('view');

                $('.sffc-crm-view-btn').removeClass('active');
                $(this).addClass('active');

                if (view === 'kanban') {
                    $('#pipeline-kanban').show();
                    $('#pipeline-list').hide();
                } else {
                    $('#pipeline-kanban').hide();
                    $('#pipeline-list').show();
                }
            });

            // List row click
            $(document).off('click', '.sffc-crm-pipeline-list-row').on('click', '.sffc-crm-pipeline-list-row', function() {
                var pipelineId = $(this).data('pipeline-id');
                self.viewPipelineItem(pipelineId);
            });
        },

        /**
         * Initialize pipeline drag and drop
         */
        initPipelineDragDrop: function() {
            var self = this;

            // Check if jQuery UI sortable is available
            if (typeof $.fn.sortable !== 'function') {
                return;
            }

            $('.sffc-crm-pipeline-items').sortable({
                connectWith: '.sffc-crm-pipeline-items',
                placeholder: 'sffc-crm-drag-placeholder',
                cursor: 'grabbing',
                opacity: 0.8,
                tolerance: 'pointer',
                scroll: true,
                scrollSensitivity: 50,
                helper: function(e, item) {
                    return item.clone().addClass('sffc-crm-dragging');
                },
                start: function(e, ui) {
                    ui.item.addClass('sffc-crm-drag-origin');
                    ui.placeholder.height(ui.item.outerHeight());
                },
                stop: function(e, ui) {
                    ui.item.removeClass('sffc-crm-drag-origin');
                },
                receive: function(e, ui) {
                    var $item = ui.item;
                    var pipelineId = $item.data('pipeline-id');
                    var newStage = $(this).data('stage');

                    // Update stage via AJAX
                    self.updatePipelineStageDrag(pipelineId, newStage, function(success) {
                        if (success) {
                            // Update column counts
                            self.updatePipelineCounts();
                            self.showSuccess('Moved to ' + newStage.replace('_', ' '));
                        } else {
                            // Revert on failure
                            $(ui.sender).sortable('cancel');
                        }
                    });
                }
            }).disableSelection();
        },

        /**
         * Update pipeline stage (from drag)
         */
        updatePipelineStageDrag: function(pipelineId, newStage, callback) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_update_pipeline_stage',
                    pipeline_id: pipelineId,
                    stage: newStage,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (callback) {
                        callback(response.success);
                    }
                    if (!response.success) {
                        self.showError(response.data && response.data.message ? response.data.message : 'Failed to update stage');
                    }
                },
                error: function() {
                    if (callback) {
                        callback(false);
                    }
                    self.showError('Failed to update stage');
                }
            });
        },

        /**
         * Update pipeline column counts
         */
        updatePipelineCounts: function() {
            $('.sffc-crm-pipeline-column').each(function() {
                var count = $(this).find('.sffc-crm-pipeline-card').length;
                $(this).find('.sffc-crm-pipeline-count').text(count);
            });
        },

        /**
         * Render pipeline card
         */
        renderPipelineCard: function(item) {
            var isManualLead = !item.recruiter_id;
            var source = item.source || 'platform';
            var cardClass = 'sffc-crm-pipeline-card';
            if (isManualLead) {
                cardClass += ' sffc-crm-pipeline-card--manual';
            }

            var html = '<div class="' + cardClass + '" data-pipeline-id="' + item.id + '">';

            // Source badge for non-platform leads
            if (source && source !== 'platform') {
                var sourceLabels = {
                    'linkedin': 'LinkedIn',
                    'indeed': 'Indeed',
                    'referral': 'Referral',
                    'company_website': 'Company',
                    'other': 'Manual'
                };
                var sourceLabel = sourceLabels[source] || 'Manual';
                html += '<div class="sffc-crm-pipeline-card-source sffc-crm-pipeline-card-source--' + source + '">' + sourceLabel + '</div>';
            }

            html += '<div class="sffc-crm-pipeline-card-title">' + this.escapeHtml(item.role_title || 'Opportunity') + '</div>';
            if (item.company) {
                html += '<div class="sffc-crm-pipeline-card-company">' + this.escapeHtml(item.company) + '</div>';
            }

            // Show recruiter name for platform leads, or contact name for manual leads
            if (item.recruiter_name) {
                html += '<div class="sffc-crm-pipeline-card-recruiter">' + this.escapeHtml(item.recruiter_name) + '</div>';
            } else if (item.contact_name) {
                html += '<div class="sffc-crm-pipeline-card-recruiter">' + this.escapeHtml(item.contact_name) + '</div>';
            }

            if (item.next_action_date) {
                var actionDate = new Date(item.next_action_date);
                html += '<div class="sffc-crm-pipeline-card-action">' + actionDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + '</div>';
            }
            html += '</div>';
            return html;
        },

        /**
         * Load saved items
         */
        loadSaved: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_saved',
                    type: 'all',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderSaved(response.data);

                        // Update saved badge count (always update, even if 0)
                        var totalSaved = (response.data.posts ? response.data.posts.length : 0) +
                                        (response.data.recruiters ? response.data.recruiters.length : 0);
                        self.updateTabBadge('saved', totalSaved);
                    }
                }
            });
        },

        /**
         * Render saved items
         */
        renderSaved: function(data) {
            var $panel = $('#panel-saved');
            var html = '<h2>Saved Items</h2>';

            // Saved posts
            html += '<h3>Saved Posts (' + data.posts.length + ')</h3>';
            if (data.posts.length === 0) {
                html += '<p class="sffc-crm-empty-text">No saved posts yet. Save posts from the Feed to see them here.</p>';
            } else {
                html += '<div class="sffc-crm-feed-list">';
                data.posts.forEach(function(post) {
                    post.is_saved = 1;
                    html += this.renderPostRow(post);
                }, this);
                html += '</div>';
            }

            $panel.html(html);
        },

        /**
         * Render reach out modal (legacy - kept for compatibility)
         */
        renderReachOutModal: function(data) {
            var self = this;
            var hasEmail = data.recruiter.email ? true : false;
            var hasConnectedEmail = this.emailAccounts && this.emailAccounts.length > 0;
            var hasLinkedIn = data.recruiter.linkedin_url ? true : false;
            var html = '<div class="sffc-crm-reach-out-modal">';

            // Header
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Send CV to ' + this.escapeHtml(data.recruiter.name) + '</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            // Recruiter info
            html += '<div class="sffc-crm-reach-out-recruiter">';
            if (data.recruiter.photo_url) {
                html += '<img src="' + data.recruiter.photo_url + '" alt="" class="sffc-crm-avatar">';
            }
            html += '<div>';
            html += '<strong>' + this.escapeHtml(data.recruiter.name) + '</strong>';
            if (data.recruiter.firm) {
                html += '<br><span>' + this.escapeHtml(data.recruiter.firm) + '</span>';
            }
            // Show email/LinkedIn availability
            if (hasEmail) {
                html += '<br><small class="sffc-crm-contact-indicator sffc-crm-has-email"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg> Email available</small>';
            }
            html += '</div>';
            html += '</div>';

            // Channel selector
            html += '<div class="sffc-crm-reach-out-channel">';
            html += '<label>Channel</label>';
            html += '<div class="sffc-crm-channel-options">';
            html += '<button class="sffc-crm-channel-btn active" data-channel="email">Email</button>';
            html += '<button class="sffc-crm-channel-btn" data-channel="linkedin">LinkedIn</button>';
            html += '</div>';
            html += '</div>';

            // Template selector
            html += '<div class="sffc-crm-reach-out-template">';
            html += '<label>Template</label>';
            html += '<select id="reach-out-template" class="sffc-crm-select">';
            html += '<option value="">Select a template...</option>';
            if (data.templates) {
                data.templates.forEach(function(template) {
                    html += '<option value="' + template.id + '" data-content="' + self.escapeHtml(template.content) + '">' + self.escapeHtml(template.name) + '</option>';
                });
            }
            html += '</select>';
            html += '</div>';

            // Subject (for email)
            html += '<div class="sffc-crm-reach-out-subject" id="reach-out-subject-group">';
            html += '<label>Subject</label>';
            html += '<input type="text" id="reach-out-subject" class="sffc-crm-input" placeholder="Subject line...">';
            html += '</div>';

            // Message
            html += '<div class="sffc-crm-reach-out-message">';
            html += '<label>Message</label>';
            html += '<textarea id="reach-out-message" class="sffc-crm-textarea" rows="8" placeholder="Write your message..."></textarea>';
            html += '</div>';

            // Actions
            html += '<div class="sffc-crm-reach-out-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary" id="reach-out-save-draft">Save Draft</button>';

            // Primary button with data attributes for email/linkedin
            var btnLabel = hasEmail ? 'Open in Email Client' : 'Copy to Clipboard';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="reach-out-send" ';
            html += 'data-recruiter-id="' + data.recruiter.id + '" ';
            html += 'data-post-id="' + (data.post ? data.post.id : '') + '" ';
            html += 'data-recruiter-email="' + (data.recruiter.email || '') + '" ';
            html += 'data-recruiter-linkedin="' + (data.recruiter.linkedin_url || '') + '">';
            html += '<span class="btn-label-email">' + btnLabel + '</span>';
            html += '<span class="btn-label-linkedin" style="display:none;">Copy to Clipboard</span>';
            html += '</button>';
            html += '</div>';

            // Note about sending method
            html += '<p class="sffc-crm-reach-out-note">';
            html += '<span class="note-email">' + (hasEmail ? 'Your default email app will open with the message ready to send.' : 'Message will be copied. No email address on file for this recruiter.') + '</span>';
            html += '<span class="note-linkedin" style="display:none;">Message will be copied to clipboard. Paste in LinkedIn.</span>';
            html += '</p>';

            html += '</div>';

            this.updateModalContent(html);
            this.bindReachOutEvents();
        },

        /**
         * Bind reach out modal events
         */
        bindReachOutEvents: function() {
            var self = this;
            var currentChannel = 'email';

            // Channel switch
            $(document).off('click', '.sffc-crm-channel-btn').on('click', '.sffc-crm-channel-btn', function() {
                $('.sffc-crm-channel-btn').removeClass('active');
                $(this).addClass('active');

                currentChannel = $(this).data('channel');
                if (currentChannel === 'linkedin') {
                    $('#reach-out-subject-group').hide();
                    $('.btn-label-email').hide();
                    $('.btn-label-linkedin').show();
                    $('.note-email').hide();
                    $('.note-linkedin').show();
                } else {
                    $('#reach-out-subject-group').show();
                    $('.btn-label-email').show();
                    $('.btn-label-linkedin').hide();
                    $('.note-email').show();
                    $('.note-linkedin').hide();
                }
            });

            // Template selection
            $(document).off('change', '#reach-out-template').on('change', '#reach-out-template', function() {
                var content = $(this).find(':selected').data('content');
                if (content) {
                    $('#reach-out-message').val(content);
                }
            });

            // Send button
            $(document).off('click', '#reach-out-send').on('click', '#reach-out-send', function() {
                var $btn = $(this);
                var message = $('#reach-out-message').val();
                var subject = $('#reach-out-subject').val();
                var recruiterEmail = $btn.data('recruiter-email');
                var channel = $('.sffc-crm-channel-btn.active').data('channel');

                if (!message.trim()) {
                    self.showError('Please write a message');
                    return;
                }

                if (channel === 'email' && !subject.trim()) {
                    self.showError('Please add a subject line');
                    return;
                }

                // For email channel with email address, use mailto:
                if (channel === 'email' && recruiterEmail) {
                    self.openMailto(recruiterEmail, subject, message);
                    self.showSuccess('Email client opened! Send from your email app.');
                    self.closeModal();
                    return;
                }

                // For LinkedIn or email without address, copy to clipboard
                var fullText = (channel === 'email' && subject) ? 'Subject: ' + subject + '\n\n' + message : message;

                navigator.clipboard.writeText(fullText).then(function() {
                    if (channel === 'linkedin') {
                        self.showSuccess('Message copied! Paste in LinkedIn.');
                    } else {
                        self.showSuccess('Message copied to clipboard!');
                    }
                    self.closeModal();
                }).catch(function() {
                    // Fallback for older browsers
                    var textarea = document.createElement('textarea');
                    textarea.value = fullText;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    if (channel === 'linkedin') {
                        self.showSuccess('Message copied! Paste in LinkedIn.');
                    } else {
                        self.showSuccess('Message copied to clipboard!');
                    }
                    self.closeModal();
                });
            });
        },

        /**
         * View recruiter detail
         */
        viewRecruiter: function(recruiterId) {
            var self = this;

            this.showModal('<div class="sffc-crm-modal-loading">Loading recruiter...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_recruiter',
                    recruiter_id: recruiterId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderRecruiterDetail(response.data);
                    } else {
                        self.closeModal();
                        self.showError('Failed to load recruiter');
                    }
                },
                error: function() {
                    self.closeModal();
                    self.showError('Failed to load recruiter');
                }
            });
        },

        /**
         * Render recruiter detail modal
         */
        renderRecruiterDetail: function(recruiter) {
            var html = '<div class="sffc-crm-recruiter-detail">';

            // Header
            html += '<div class="sffc-crm-modal-header">';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            // Recruiter header
            html += '<div class="sffc-crm-detail-recruiter-header">';
            if (recruiter.photo_url) {
                html += '<img src="' + recruiter.photo_url + '" alt="" class="sffc-crm-detail-avatar-lg">';
            } else {
                html += '<div class="sffc-crm-detail-avatar-lg sffc-crm-avatar-placeholder">' + recruiter.name.charAt(0) + '</div>';
            }
            html += '<div class="sffc-crm-detail-recruiter-main">';
            html += '<h2>' + this.escapeHtml(recruiter.name) + '</h2>';
            if (recruiter.title) {
                html += '<p class="sffc-crm-detail-title">' + this.escapeHtml(recruiter.title) + '</p>';
            }
            if (recruiter.firm) {
                html += '<p class="sffc-crm-detail-firm">' + this.escapeHtml(recruiter.firm) + '</p>';
            }
            html += '</div>';
            html += '</div>';

            // Status badge
            if (recruiter.status) {
                var statusColors = {
                    new: '#6b7280',
                    contacted: '#3b82f6',
                    replied: '#10b981',
                    in_conversation: '#0d353e',
                    dormant: '#f59e0b'
                };
                var status = recruiter.status || 'new';
                html += '<div class="sffc-crm-detail-status">';
                html += '<span class="sffc-crm-status-badge" style="background: ' + (statusColors[status] || '#6b7280') + ';">';
                html += status.replace('_', ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                html += '</span>';
                html += '</div>';
            }

            // Contact info
            html += '<div class="sffc-crm-detail-contact">';
            if (recruiter.linkedin_url) {
                html += '<a href="' + recruiter.linkedin_url + '" target="_blank" class="sffc-crm-detail-link">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>';
                html += 'LinkedIn';
                html += '</a>';
            }
            if (recruiter.email) {
                html += '<a href="mailto:' + recruiter.email + '" class="sffc-crm-detail-link">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
                html += recruiter.email;
                html += '</a>';
            }
            html += '</div>';

            // Recent posts
            if (recruiter.recent_posts && recruiter.recent_posts.length > 0) {
                html += '<div class="sffc-crm-detail-section">';
                html += '<h4>Recent Posts</h4>';
                html += '<div class="sffc-crm-detail-posts">';
                recruiter.recent_posts.forEach(function(post) {
                    html += '<div class="sffc-crm-detail-post-item">';
                    html += '<strong>' + this.escapeHtml(post.role_title) + '</strong>';
                    if (post.company) {
                        html += ' at ' + this.escapeHtml(post.company);
                    }
                    html += '<br><small>' + this.timeAgo(post.posted_at) + '</small>';
                    html += '</div>';
                }, this);
                html += '</div>';
                html += '</div>';
            }

            // Actions
            html += '<div class="sffc-crm-detail-actions">';
            html += this.buildReachOutButton({
                recruiterId: recruiter.id
            });
            html += '<button class="sffc-crm-expert-reach-btn sffc-crm-expert-request-btn" data-recruiter-id="' + recruiter.id + '" data-recruiter-name="' + this.escapeHtml(recruiter.name) + '" data-recruiter-title="' + this.escapeHtml(recruiter.title || '') + '" data-recruiter-firm="' + this.escapeHtml(recruiter.firm || '') + '" data-recruiter-photo="' + this.escapeHtml(recruiter.photo_url || '') + '">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>';
            html += '<span>Expert Send CV</span></button>';
            html += '</div>';

            html += '</div>';

            this.updateModalContent(html);
        },

        /**
         * View pipeline item
         */
        viewPipelineItem: function(pipelineId) {
            var self = this;

            this.showModal('<div class="sffc-crm-modal-loading">Loading...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_pipeline_item',
                    pipeline_id: pipelineId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderPipelineDetail(response.data);
                    } else {
                        self.closeModal();
                        self.showError('Failed to load pipeline item');
                    }
                },
                error: function() {
                    self.closeModal();
                    self.showError('Failed to load pipeline item');
                }
            });
        },

        /**
         * Render pipeline item detail
         */
        renderPipelineDetail: function(item) {
            var stages = this.config.stages || {
                interested: { label: 'Expressed Interest', color: '#6b7280' },
                reached_out: { label: 'Reached Out', color: '#3b82f6' },
                in_conversation: { label: 'In Conversation', color: '#0d353e' },
                interviewing: { label: 'Interviewing', color: '#f59e0b' },
                offer: { label: 'Offer', color: '#10b981' },
                closed: { label: 'Closed', color: '#ef4444' }
            };

            var currentStage = stages[item.stage] || { label: item.stage, color: '#6b7280' };

            var html = '<div class="sffc-crm-pipeline-detail">';

            // Header
            html += '<div class="sffc-crm-modal-header">';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            // Title
            html += '<h2 class="sffc-crm-pipeline-detail-title">' + this.escapeHtml(item.role_title || 'Opportunity') + '</h2>';
            if (item.company) {
                html += '<p class="sffc-crm-pipeline-detail-company">' + this.escapeHtml(item.company) + '</p>';
            }

            // Current stage
            html += '<div class="sffc-crm-pipeline-detail-stage">';
            html += '<span class="sffc-crm-status-badge" style="background: ' + currentStage.color + ';">' + currentStage.label + '</span>';
            html += '</div>';

            // Recruiter
            html += '<div class="sffc-crm-pipeline-detail-recruiter">';
            html += '<strong>Recruiter:</strong> ' + this.escapeHtml(item.recruiter_name || 'Unknown');
            if (item.recruiter_firm) {
                html += ' (' + this.escapeHtml(item.recruiter_firm) + ')';
            }
            html += '</div>';

            // Stage selector
            html += '<div class="sffc-crm-pipeline-detail-section">';
            html += '<label>Move to Stage</label>';
            html += '<div class="sffc-crm-stage-selector">';
            Object.keys(stages).forEach(function(stageKey) {
                var stage = stages[stageKey];
                var isActive = stageKey === item.stage;
                html += '<button class="sffc-crm-stage-btn ' + (isActive ? 'active' : '') + '" data-stage="' + stageKey + '" data-pipeline-id="' + item.id + '" style="border-color: ' + stage.color + '; ' + (isActive ? 'background: ' + stage.color + '; color: white;' : '') + '">';
                html += stage.label;
                html += '</button>';
            });
            html += '</div>';
            html += '</div>';

            // Notes
            html += '<div class="sffc-crm-pipeline-detail-section">';
            html += '<label>Notes</label>';
            html += '<textarea id="pipeline-notes" class="sffc-crm-textarea" rows="3" placeholder="Add notes...">' + this.escapeHtml(item.notes || '') + '</textarea>';
            html += '</div>';

            // Next action
            html += '<div class="sffc-crm-pipeline-detail-section">';
            html += '<label>Next Action</label>';
            html += '<input type="text" id="pipeline-next-action" class="sffc-crm-input" placeholder="What\'s the next step?" value="' + this.escapeHtml(item.next_action || '') + '">';
            html += '</div>';

            // History
            if (item.history && item.history.length > 0) {
                html += '<div class="sffc-crm-pipeline-detail-section">';
                html += '<label>History</label>';
                html += '<div class="sffc-crm-pipeline-history">';
                item.history.forEach(function(entry) {
                    html += '<div class="sffc-crm-history-item">';
                    html += '<span class="sffc-crm-history-stage">';
                    if (entry.from_stage) {
                        html += (stages[entry.from_stage] ? stages[entry.from_stage].label : entry.from_stage) + ' → ';
                    }
                    html += stages[entry.to_stage] ? stages[entry.to_stage].label : entry.to_stage;
                    html += '</span>';
                    html += '<span class="sffc-crm-history-date">' + this.timeAgo(entry.transitioned_at) + '</span>';
                    html += '</div>';
                }, this);
                html += '</div>';
                html += '</div>';
            }

            // Actions
            html += '<div class="sffc-crm-pipeline-detail-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary" id="pipeline-save-notes" data-pipeline-id="' + item.id + '">Save Notes</button>';
            html += this.buildReachOutButton({
                recruiterId: item.recruiter_id,
                postId: item.post_id || ''
            });
            html += '</div>';

            html += '</div>';

            this.updateModalContent(html);
            this.bindPipelineDetailEvents();
        },

        /**
         * Bind pipeline detail events
         */
        bindPipelineDetailEvents: function() {
            var self = this;

            // Stage change
            $(document).off('click', '.sffc-crm-stage-btn').on('click', '.sffc-crm-stage-btn', function() {
                var $btn = $(this);
                var pipelineId = $btn.data('pipeline-id');
                var newStage = $btn.data('stage');

                if ($btn.hasClass('active')) {
                    return;
                }

                $btn.addClass('loading');

                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_update_pipeline_stage',
                        pipeline_id: pipelineId,
                        stage: newStage,
                        nonce: self.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.sffc-crm-stage-btn').removeClass('active').css({ background: '', color: '' });
                            $btn.addClass('active').css({ background: $btn.css('border-color'), color: 'white' });
                            self.showSuccess('Stage updated');

                            // Refresh pipeline if on that tab
                            if (self.currentTab === 'pipeline') {
                                self.loadPipeline();
                            }
                        } else {
                            self.handleError(response);
                        }
                        $btn.removeClass('loading');
                    },
                    error: function() {
                        $btn.removeClass('loading');
                        self.showError('Failed to update stage');
                    }
                });
            });

            // Save notes
            $(document).off('click', '#pipeline-save-notes').on('click', '#pipeline-save-notes', function() {
                var pipelineId = $(this).data('pipeline-id');
                var notes = $('#pipeline-notes').val();
                var nextAction = $('#pipeline-next-action').val();

                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_update_pipeline_notes',
                        pipeline_id: pipelineId,
                        notes: notes,
                        next_action: nextAction,
                        nonce: self.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.showSuccess('Notes saved');
                        } else {
                            self.showError('Failed to save notes');
                        }
                    }
                });
            });
        },

        /**
         * Handle error response
         */
        handleError: function(response) {
            if (response.data && response.data.upgrade_prompt) {
                this.showUpgradePrompt(response.data.upgrade_prompt);
            } else {
                this.showError(response.data && response.data.message ? response.data.message : 'An error occurred');
            }
        },

        /**
         * Show upgrade prompt
         */
        showUpgradePrompt: function(prompt) {
            var html = '<div class="sffc-crm-upgrade-modal">';
            html += '<div class="sffc-crm-modal-header">';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';
            html += '<div class="sffc-crm-upgrade-content">';
            html += '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>';
            html += '<h3>' + this.escapeHtml(prompt.title || 'Upgrade Required') + '</h3>';
            html += '<p>' + this.escapeHtml(prompt.message) + '</p>';
            if (prompt.upgrade_url) {
                html += '<a href="' + prompt.upgrade_url + '" class="sffc-crm-btn sffc-crm-btn-primary">Upgrade Now</a>';
            }
            html += '</div>';
            html += '</div>';

            this.showModal(html);
        },

        showReachOutUpgradeModal: function() {
            if (this.triggerPlanModal({ skipFallback: true })) {
                return;
            }
            var membershipUrl = this.config.membershipUrl || 'https://joinsenna.com/memberships/';
            var loginUrl = this.config.loginUrl || '/wp-login.php';

            var benefits = [
                'AI-personalized outreach messages tailored to the recruiter',
                'Email + LinkedIn tracking inside your MENA Careers CRM',
                'Follow-up reminders so you never drop a conversation',
                'Instant context on every recruiter you contact'
            ];

            var html = '<div class="sffc-crm-upgrade-modal sffc-crm-reach-out-upgrade">';
            html += '<div class="sffc-crm-modal-header">';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';
            html += '<div class="sffc-crm-upgrade-content">';
            html += '<div class="sffc-crm-upgrade-icon">';
            html += '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>';
            html += '</div>';
            html += '<h3>Unlock Smart CV Sending</h3>';
            html += '<p>"Apply" just sends a resume. Premium Send CV delivers a tracked, personalized intro that actually lands replies.</p>';

            html += '<div class="sffc-crm-upgrade-compare">';
            html += '<div class="sffc-crm-upgrade-card">';
            html += '<h4>Basic Apply</h4>';
            html += '<ul><li>Generic application link</li><li>No visibility on replies</li><li>Manual follow-up</li></ul>';
            html += '</div>';
            html += '<div class="sffc-crm-upgrade-card">';
            html += '<h4>MENA Careers Send CV</h4>';
            html += '<ul>';
            benefits.forEach(function(item) {
                html += '<li>' + this.escapeHtml(item) + '</li>';
            }, this);
            html += '</ul>';
            html += '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-upgrade-actions">';
            html += '<a href="' + membershipUrl + '" class="sffc-crm-btn sffc-crm-btn-primary" target="_blank" rel="noopener">Join MENA Careers Premium</a>';
            html += '<a href="' + loginUrl + '" class="sffc-crm-btn sffc-crm-btn-outline">I already have access</a>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            this.showModal(html);
        },

        // Note: showSuccess, showError, showToast are defined in Phase 7 section with improved implementations

        /**
         * Get empty state HTML
         */
        getEmptyState: function(title, message) {
            return '<div class="sffc-crm-empty">' +
                '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
                '<circle cx="12" cy="12" r="10"></circle>' +
                '<line x1="12" y1="8" x2="12" y2="12"></line>' +
                '<line x1="12" y1="16" x2="12.01" y2="16"></line>' +
                '</svg>' +
                '<h3>' + title + '</h3>' +
                '<p>' + message + '</p>' +
                '</div>';
        },

        /**
         * Time ago helper
         */
        timeAgo: function(dateString) {
            if (!dateString) return '';

            var date = new Date(dateString);
            var now = new Date();
            var seconds = Math.floor((now - date) / 1000);

            var interval = Math.floor(seconds / 31536000);
            if (interval >= 1) return interval + ' year' + (interval > 1 ? 's' : '') + ' ago';

            interval = Math.floor(seconds / 2592000);
            if (interval >= 1) return interval + ' month' + (interval > 1 ? 's' : '') + ' ago';

            interval = Math.floor(seconds / 86400);
            if (interval >= 1) return interval + 'd ago';

            interval = Math.floor(seconds / 3600);
            if (interval >= 1) return interval + 'h ago';

            interval = Math.floor(seconds / 60);
            if (interval >= 1) return interval + 'm ago';

            return 'just now';
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        getCurrencySymbol: function(currency) {
            var symbols = {
                'GBP': '\u00A3',
                'USD': '$',
                'EUR': '\u20AC',
                'SGD': 'S$',
                'HKD': 'HK$',
                'AED': 'AED ',
                'CHF': 'CHF ',
                'JPY': '\u00A5',
                'CNY': '\u00A5',
                'KRW': '\u20A9',
                'INR': '\u20B9',
                'AUD': 'A$',
                'CAD': 'C$',
                'NZD': 'NZ$'
            };
            return symbols[currency] || (currency + ' ');
        },

        formatPlanPrice: function(plan) {
            // Use fallback price if no price_amount
            if (!plan.price_amount || plan.price_amount <= 0) {
                return plan.price ? this.escapeHtml(plan.price) : '';
            }

            var currency = plan.price_currency || 'USD';
            var symbol = this.getCurrencySymbol(currency);

            // Currencies that don't use decimals
            var noDecimalCurrencies = ['JPY', 'KRW', 'VND', 'IDR', 'CLP', 'PYG', 'UGX'];
            var decimals = noDecimalCurrencies.indexOf(currency) !== -1 ? 0 : 2;

            var amount = parseFloat(plan.price_amount).toFixed(decimals);
            var formattedAmount = symbol + this.formatNumber(amount, decimals);

            // Add billing cycle if available
            if (plan.billing_cycle) {
                var cycle = this.escapeHtml(plan.billing_cycle);
                // Remove "per " prefix if present
                if (cycle.toLowerCase().indexOf('per ') === 0) {
                    cycle = cycle.substring(4).trim();
                }
                return formattedAmount + ' / ' + cycle;
            }

            return formattedAmount;
        },

        formatNumber: function(number, decimals) {
            var num = parseFloat(number);
            var parts = num.toFixed(decimals).split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return parts.join('.');
        },

        insertAtCursor: function($textarea, text) {
            if (!$textarea || !$textarea.length) {
                return;
            }
            var el = $textarea[0];
            var start = el.selectionStart || 0;
            var end = el.selectionEnd || 0;
            var value = $textarea.val();
            var newValue = value.substring(0, start) + text + value.substring(end);
            $textarea.val(newValue);
            var newPos = start + text.length;
            if (el.setSelectionRange) {
                el.setSelectionRange(newPos, newPos);
            }
            el.focus();
        },

        ensureReachOutHelper: function() {
            if (typeof this.buildReachOutButton !== 'function') {
                this.buildReachOutButton = function() {
                    return '';
                };
            }
        },

        /**
         * Build reach out button HTML
         */
        buildReachOutButton: function(options) {
            var opts = options || {};
            var recruiterId = opts.recruiterId || 0;
            var postId = opts.postId || 0;
            var small = opts.small || false;

            if (!recruiterId && !postId) {
                return '';
            }

            var btnClass = 'sffc-crm-btn sffc-crm-btn-primary sffc-crm-reach-out-btn';
            if (small) {
                btnClass += ' sffc-crm-btn-small';
            }

            var html = '<button class="' + btnClass + '" ';
            if (recruiterId) {
                html += 'data-recruiter-id="' + recruiterId + '" ';
            }
            if (postId) {
                html += 'data-post-id="' + postId + '" ';
            }
            html += 'title="Send your CV to this recruiter">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>';
            html += '<polyline points="14 2 14 8 20 8"></polyline>';
            html += '<line x1="16" y1="13" x2="8" y2="13"></line>';
            html += '<line x1="16" y1="17" x2="8" y2="17"></line>';
            html += '<polyline points="10 9 9 9 8 9"></polyline>';
            html += '</svg>';
            if (!small) {
                html += '<span>Send CV</span>';
            }
            html += '</button>';

            return html;
        },

        /**
         * Open post detail modal
         */
        openPostDetail: function(postId) {
            var self = this;

            // Show loading modal
            this.showModal('<div class="sffc-crm-modal-loading">Loading...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_post_detail',
                    post_id: postId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderPostDetail(response.data);
                    } else {
                        self.closeModal();
                        self.showError('Failed to load post details');
                    }
                },
                error: function() {
                    self.closeModal();
                    self.showError('Failed to load post details');
                }
            });
        },

        /**
         * Render post detail in modal
         */
        renderPostDetail: function(post) {
            var isSaved = post.is_saved == 1;
            var postedAgo = this.timeAgo(post.posted_at);

            var html = '<div class="sffc-crm-post-detail">';

            // Header with close button
            html += '<div class="sffc-crm-modal-header">';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            // Recruiter info
            html += '<div class="sffc-crm-detail-recruiter">';
            if (post.recruiter_photo) {
                html += '<img src="' + post.recruiter_photo + '" alt="" class="sffc-crm-detail-avatar">';
            } else {
                var initial = (post.recruiter_name || 'R').charAt(0);
                html += '<div class="sffc-crm-detail-avatar sffc-crm-avatar-placeholder">' + initial + '</div>';
            }
            html += '<div class="sffc-crm-detail-recruiter-info">';
            html += '<h3 class="sffc-crm-detail-recruiter-name">' + this.escapeHtml(post.recruiter_name || 'Unknown Recruiter') + '</h3>';
            if (post.recruiter_firm) {
                html += '<span class="sffc-crm-detail-recruiter-firm">' + this.escapeHtml(post.recruiter_firm) + '</span>';
            }
            html += '</div>';
            html += '<span class="sffc-crm-detail-time">' + postedAgo + '</span>';
            html += '</div>';

            // Role title
            html += '<div class="sffc-crm-detail-role">';
            html += '<h2 class="sffc-crm-detail-title">' + this.escapeHtml(post.role_title) + '</h2>';
            if (post.company) {
                html += '<span class="sffc-crm-detail-company">' + this.escapeHtml(post.company) + '</span>';
            }
            html += '</div>';

            // Meta info grid
            html += '<div class="sffc-crm-detail-meta">';

            if (post.location) {
                html += '<div class="sffc-crm-detail-meta-item">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>';
                html += '<span>' + this.escapeHtml(post.location) + '</span>';
                html += '</div>';
            }

            if (post.salary_text) {
                html += '<div class="sffc-crm-detail-meta-item">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>';
                html += '<span>' + this.escapeHtml(post.salary_text) + '</span>';
                html += '</div>';
            }

            if (post.seniority) {
                html += '<div class="sffc-crm-detail-meta-item">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
                html += '<span>' + post.seniority.toUpperCase().replace('_', ' ') + '</span>';
                html += '</div>';
            }

            if (post.experience_years) {
                html += '<div class="sffc-crm-detail-meta-item">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
                html += '<span>' + this.escapeHtml(post.experience_years) + '</span>';
                html += '</div>';
            }

            if (post.is_remote == 1) {
                html += '<div class="sffc-crm-detail-meta-item sffc-crm-detail-remote">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';
                html += '<span>Remote</span>';
                html += '</div>';
            }

            if (post.is_hybrid == 1) {
                html += '<div class="sffc-crm-detail-meta-item">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>';
                html += '<span>Hybrid</span>';
                html += '</div>';
            }

            html += '</div>';

            // Full content
            html += '<div class="sffc-crm-detail-content">';
            html += '<p>' + this.escapeHtml(post.content).replace(/\n/g, '<br>') + '</p>';
            html += '</div>';

            // Skills
            if (post.skills_mentioned) {
                var skills = typeof post.skills_mentioned === 'string' ? JSON.parse(post.skills_mentioned) : post.skills_mentioned;
                if (skills && skills.length > 0) {
                    html += '<div class="sffc-crm-detail-skills">';
                    html += '<h4>Skills Mentioned</h4>';
                    html += '<div class="sffc-crm-skill-tags">';
                    skills.forEach(function(skill) {
                        html += '<span class="sffc-crm-skill-tag">' + this.escapeHtml(skill) + '</span>';
                    }, this);
                    html += '</div>';
                    html += '</div>';
                }
            }

            // Actions
            html += '<div class="sffc-crm-detail-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-icon sffc-crm-save-btn ' + (isSaved ? 'is-saved' : '') + '" data-action="save" data-post-id="' + post.id + '">';
            html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="' + (isSaved ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>';
            html += '</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-add-pipeline-btn" data-post-id="' + post.id + '" data-recruiter-id="' + post.recruiter_id + '">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>';
            html += 'Add to Pipeline';
            html += '</button>';
            if (post.application_url) {
                html += '<a href="' + this.escapeHtml(post.application_url) + '" class="sffc-crm-btn sffc-crm-btn-success" target="_blank" rel="noopener noreferrer">Apply</a>';
            }
            html += '<button class="sffc-crm-btn sffc-crm-btn-outline sffc-crm-gap-btn" data-post-id="' + post.id + '">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<circle cx="11" cy="11" r="7"></circle><line x1="16.65" y1="16.65" x2="21" y2="21"></line>';
            html += '</svg>';
            html += '<span>Scan CV</span>';
            html += '</button>';
            html += this.buildReachOutButton({
                postId: post.id,
                recruiterId: post.recruiter_id
            });
            html += '</div>';

            html += '</div>';

            this.updateModalContent(html);
        },

        /**
         * Show modal
         */
        showModal: function(content, extraContentClass) {
            var contentClass = 'sffc-crm-modal-content';
            if (extraContentClass) {
                contentClass += ' ' + extraContentClass;
            }

            var html = '<div class="sffc-crm-modal">';
            html += '<div class="sffc-crm-modal-overlay"></div>';
            html += '<div class="' + contentClass + '">' + content + '</div>';
            html += '</div>';

            $('body').append(html);
            $('body').addClass('sffc-crm-modal-open');

            // Animate in
            setTimeout(function() {
                $('.sffc-crm-modal').addClass('is-visible');
            }, 10);
        },

        /**
         * Update modal content
         */
        updateModalContent: function(content) {
            $('.sffc-crm-modal-content').html(content);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            var $modal = $('.sffc-crm-modal');
            $modal.removeClass('is-visible');
            this.activeModal = null;
            this.introComposerState = null;
            this.smartMessageState = null;
            this.recruiterOpeningsState = null;
            $(document).off('.emailConnect');

             // If the gap analyzer is mounted, move it back to the park container
            this.restoreGapAnalyzer();

            setTimeout(function() {
                $modal.remove();
                $('body').removeClass('sffc-crm-modal-open');
            }, 200);
        },

        hasGapAnalyzer: function() {
            return !!(this.gapAnalyzer.$shell && this.gapAnalyzer.$shell.length && this.gapAnalyzer.$component && this.gapAnalyzer.$component.length);
        },

        openGapAnalyzerModal: function(postId) {
            if (!this.hasGapAnalyzer()) {
                this.initGapAnalyzer();
            }

            if (!this.hasGapAnalyzer()) {
                this.showError('Gap Analyzer is unavailable right now.');
                return;
            }

            this.showModal('<div class="sffc-crm-modal-loading">Preparing your pre-application audit...</div>');
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_gap_payload',
                    nonce: this.config.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderGapAnalyzerModal(response.data);
                    } else {
                        self.closeModal();
                        self.showError(response.data && response.data.message ? response.data.message : 'Unable to load the gap analyzer.');
                    }
                },
                error: function() {
                    self.closeModal();
                    self.showError('Unable to load the gap analyzer right now.');
                }
            });
        },

        renderGapAnalyzerModal: function(data) {
            var jobTitle = data.job_title ? this.escapeHtml(data.job_title) : 'Selected role';
            var metaParts = [];
            if (data.company) {
                metaParts.push(this.escapeHtml(data.company));
            }
            if (data.location) {
                metaParts.push(this.escapeHtml(data.location));
            }

            var header = '<div class="sffc-crm-gap-modal-head">';
            header += '<div class="sffc-crm-gap-head-bar">';
            header += '<h3>Pre-application audit</h3>';
            header += '<button class="sffc-crm-modal-close" aria-label="Close">';
            header += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            header += '</button>';
            header += '</div>';
            header += '<div class="sffc-crm-gap-modal-meta">';
            header += '<span>' + jobTitle + '</span>';
            metaParts.forEach(function(part) {
                header += '<span>' + part + '</span>';
            });
            header += '</div>';
            if (!data.has_cv) {
                header += '<div class="sffc-crm-gap-warning">Add a CV in the Resume tab or paste your resume below for the most accurate scan.</div>';
            }
            header += '</div>';

            var contextPayload = null;
            if (data.match_context && Object.keys(data.match_context).length) {
                contextPayload = data.match_context;
            } else if (this.gapAnalyzerPendingContext && Object.keys(this.gapAnalyzerPendingContext).length) {
                contextPayload = this.gapAnalyzerPendingContext;
            }

            var html = header;
            if (contextPayload) {
                html += '<div class="sffc-crm-gap-summary">' + this.buildGapSummaryRow(contextPayload) + '</div>';
            }
            html += '<div class="sffc-crm-gap-body" id="sffc-gap-modal-body"></div>';

            this.updateModalContent(html);

            var $modalContent = $('.sffc-crm-modal-content');
            $modalContent.addClass('sffc-crm-modal-content--gap');

            var $body = $('#sffc-gap-modal-body');
            this.moveGapAnalyzerInto($body);
            this.fillGapAnalyzerFields(data);
            this.clearGapAnalyzerPendingContext();
        },

        buildGapSummaryRow: function(context) {
            var recruiterName = context.recruiter_name || '';
            var recruiterDisplay = recruiterName || 'Recruiter';
            var nameParts = recruiterDisplay.split(/\s+/);
            if (nameParts.length >= 2) {
                var lastInitial = nameParts[nameParts.length - 1].charAt(0).toUpperCase();
                recruiterDisplay = nameParts[0] + ' ' + lastInitial + '.';
            }

            var recruiterInitial = recruiterName ? recruiterName.charAt(0).toUpperCase() : 'S';
            var metaParts = [];
            if (context.company) {
                metaParts.push(this.escapeHtml(context.company));
            }
            if (context.location) {
                metaParts.push(this.escapeHtml(context.location));
            }

            var chips = [];
            if (context.seniority) {
                chips.push(context.seniority.toUpperCase());
            }
            if (context.sector) {
                chips.push(context.sector);
            }
            if (context.salary_text) {
                chips.push(context.salary_text);
            }

            var postedText = this.formatRelativeTime(context.posted_at);

            var html = '<div class="sffc-crm-match-row sffc-crm-match-row--summary" data-static-row="true" data-post-id="' + (context.post_id || '') + '">';
            html += '<div class="sffc-crm-match-indicator">';
            html += '<div class="sffc-crm-match-circle-container">';
            html += '<div class="sffc-crm-match-avatar">';
            if (context.recruiter_photo) {
                html += '<img src="' + this.escapeHtml(context.recruiter_photo) + '" alt="' + this.escapeHtml(recruiterName || 'Recruiter') + '" data-initial="' + this.escapeHtml(recruiterInitial) + '">';
            } else {
                html += '<div class="sffc-crm-avatar-placeholder">' + this.escapeHtml(recruiterInitial) + '</div>';
            }
            html += '</div>';
            html += '</div>';
            html += '<div class="sffc-crm-match-recruiter-name">' + this.escapeHtml(recruiterDisplay) + '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-match-content">';
            html += '<div class="sffc-crm-match-header">';
            html += '<h4 class="sffc-crm-match-title">' + this.escapeHtml(context.role_title || 'Selected Role') + '</h4>';
            if (metaParts.length) {
                html += '<div class="sffc-crm-match-meta">' + metaParts.join(' • ') + '</div>';
            }
            html += '</div>';

            if (chips.length) {
                html += '<div class="sffc-crm-gap-chips">';
                chips.forEach(function(chip) {
                    html += '<span class="sffc-crm-tag">' + this.escapeHtml(chip) + '</span>';
                }, this);
                html += '</div>';
            }

            if (postedText) {
                html += '<div class="sffc-crm-gap-meta-line">Posted ' + this.escapeHtml(postedText) + '</div>';
            }

            if (context.summary) {
                html += '<p class="sffc-crm-gap-summary-text">' + this.escapeHtml(context.summary) + '</p>';
            }

            html += '</div>';
            html += '</div>';

            return html;
        },

        captureGapAnalyzerContextFromRow: function($row) {
            if (!$row || !$row.length) {
                this.gapAnalyzerPendingContext = null;
                return;
            }

            var metaText = $row.find('.sffc-crm-match-meta').first().text().trim();
            var metaParts = metaText ? metaText.split(' • ').map(function(part) {
                return part.trim();
            }).filter(Boolean) : [];

            var recruiterName = $row.data('recruiter-name') || '';
            var $avatar = $row.find('.sffc-crm-match-avatar img');
            var recruiterPhoto = $avatar.length ? $avatar.attr('src') : '';

            var reasons = $row.find('.sffc-crm-match-reasons li span').map(function() {
                return $(this).text().trim();
            }).get().filter(Boolean);

            var summaryText = reasons.length ? reasons.join(' • ') : metaText;

            this.gapAnalyzerPendingContext = {
                post_id: $row.data('post-id') || '',
                role_title: $row.find('.sffc-crm-match-title').first().text().trim() || '',
                company: $row.data('company') || metaParts[0] || '',
                location: $row.data('location') || metaParts[1] || '',
                sector: $row.data('sector') || '',
                seniority: $row.data('seniority') || '',
                salary_text: $row.data('salaryText') || '',
                posted_at: $row.data('posted') || '',
                summary: summaryText || '',
                recruiter_id: $row.data('recruiter-id') || 0,
                recruiter_name: recruiterName,
                recruiter_photo: recruiterPhoto,
                recruiter_linkedin: $row.data('recruiter-linkedin') || '',
                recruiter_firm: $row.data('recruiter-firm') || '',
                recruiter_title: $row.data('recruiter-title') || ''
            };
        },

        clearGapAnalyzerPendingContext: function() {
            this.gapAnalyzerPendingContext = null;
        },

        moveGapAnalyzerInto: function($target) {
            if (!this.hasGapAnalyzer() || !$target || !$target.length) {
                return;
            }

            this.gapAnalyzer.$shell.detach().appendTo($target);
            this.gapAnalyzer.$shell.removeAttr('hidden');
        },

        fillGapAnalyzerFields: function(data) {
            if (!this.hasGapAnalyzer()) {
                return;
            }

            var $component = this.gapAnalyzer.$component;
            var $jdInput = $component.find('[data-input="jd"]');
            var $cvInput = $component.find('[data-input="cv"]');

            if ($jdInput.length) {
                $jdInput.val(data.jd_text || '');
                $jdInput.trigger('input');
            }

            if ($cvInput.length) {
                $cvInput.val(data.cv_text || '');
                $cvInput.trigger('input');
            }

            $component.scrollTop(0);
        },

        restoreGapAnalyzer: function() {
            if (!this.hasGapAnalyzer()) {
                return;
            }

            var $shell = this.gapAnalyzer.$shell;
            var $park = this.gapAnalyzer.$park;
            if (!$park || !$park.length) {
                return;
            }

            if (!$shell.closest('#sffc-gap-analyzer-park').length) {
                $shell.detach();
                $shell.attr('hidden', 'hidden');
                $park.append($shell);
            }

            $('.sffc-crm-modal-content').removeClass('sffc-crm-modal-content--gap');
        },

        /**
         * Add to pipeline
         */
        addToPipeline: function(postId, recruiterId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_add_to_pipeline',
                    post_id: postId,
                    recruiter_id: recruiterId,
                    stage: 'interested',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Added to pipeline');
                        self.closeModal();
                        // Refresh pipeline if on that tab
                        if (self.currentTab === 'pipeline') {
                            self.loadPipeline();
                        }
                    } else {
                        self.handleError(response);
                    }
                },
                error: function() {
                    self.showError('Failed to add to pipeline');
                }
            });
        },

        // ============================================
        // PHASE 2: Enhanced Recruiter List
        // ============================================

        /**
         * Load enhanced recruiters with filtering
         */
        loadRecruitersEnhanced: function() {
            var self = this;
            var $panel = $('#panel-contacts');

            // Load user tags first if not loaded
            if (this.userTags.length === 0) {
                this.loadUserTags();
            }

            var data = {
                action: 'sffc_crm_get_recruiters_enhanced',
                page: this.recruitersState.page,
                per_page: 20,
                sort: this.recruitersState.sort,
                sort_dir: this.recruitersState.sortDir,
                nonce: this.config.nonce
            };

            // Add filters
            if (this.recruitersState.filters.status) {
                data.status = this.recruitersState.filters.status;
            }
            if (this.recruitersState.filters.tag_id) {
                data.tag_id = this.recruitersState.filters.tag_id;
            }
            if (this.recruitersState.filters.search) {
                data.search = this.recruitersState.filters.search;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.renderRecruitersEnhanced(response.data);
                    } else {
                        // Handle error response
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to load recruiters';
                        $panel.html('<div class="sffc-crm-empty-state">' +
                            '<p>' + errorMsg + '</p>' +
                            '<button class="sffc-crm-btn sffc-crm-btn-primary" onclick="window.sffcCRMApp.loadRecruitersEnhanced()">Try Again</button>' +
                        '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('CRM Recruiters AJAX error:', error);
                    $panel.html('<div class="sffc-crm-empty-state">' +
                        '<p>Failed to load recruiters. Please try again.</p>' +
                        '<button class="sffc-crm-btn sffc-crm-btn-primary" onclick="window.sffcCRMApp.loadRecruitersEnhanced()">Try Again</button>' +
                    '</div>');
                }
            });
        },

        /**
         * Render enhanced recruiters view
         */
        renderRecruitersEnhanced: function(data) {
            var $panel = $('#panel-contacts');
            var html = '';

            // Ensure data has required properties with defaults
            var stats = data.stats || { total: 0, replied: 0, in_conversation: 0 };
            var recruiters = data.recruiters || [];

            // Header with stats
            html += '<div class="sffc-crm-recruiters-header">';
            html += '<div class="sffc-crm-header-left">';
            html += '<h2>Recruiters</h2>';
            html += '<div class="sffc-crm-stats-inline">';
            html += '<span class="sffc-crm-stat"><strong>' + (stats.total || 0) + '</strong> Total</span>';
            html += '<span class="sffc-crm-stat"><strong>' + (stats.replied || 0) + '</strong> Replied</span>';
            html += '<span class="sffc-crm-stat"><strong>' + (stats.in_conversation || 0) + '</strong> In Conversation</span>';
            html += '</div>';
            html += '</div>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-small" id="toggle-bulk-select">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>';
            html += 'Bulk Select';
            html += '</button>';
            html += '</div>';

            // Bulk action toolbar (hidden by default)
            html += '<div class="sffc-crm-bulk-toolbar" id="bulk-toolbar" style="display: none;">';
            html += '<div class="sffc-crm-bulk-count"><span id="bulk-count">0</span> selected</div>';
            html += '<div class="sffc-crm-bulk-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" id="bulk-select-all">Select All</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" id="bulk-clear">Clear</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-small" id="bulk-add-to-list">';
            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>';
            html += 'Add to Outreach List';
            html += '</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-btn-small" id="bulk-compose">';
            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
            html += 'Compose Message';
            html += '</button>';
            html += '</div>';
            html += '</div>';

            // Filter bar
            html += '<div class="sffc-crm-recruiter-filters">';
            html += '<div class="sffc-crm-filter-row">';

            // Search
            html += '<div class="sffc-crm-filter-search-box">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
            html += '<input type="text" id="recruiter-search" placeholder="Search recruiters..." value="' + (this.recruitersState.filters.search || '') + '">';
            html += '</div>';

            // Status filter
            html += '<select id="recruiter-filter-status" class="sffc-crm-select">';
            html += '<option value="">All Statuses</option>';
            html += '<option value="new"' + (this.recruitersState.filters.status === 'new' ? ' selected' : '') + '>New</option>';
            html += '<option value="contacted"' + (this.recruitersState.filters.status === 'contacted' ? ' selected' : '') + '>Contacted</option>';
            html += '<option value="replied"' + (this.recruitersState.filters.status === 'replied' ? ' selected' : '') + '>Replied</option>';
            html += '<option value="in_conversation"' + (this.recruitersState.filters.status === 'in_conversation' ? ' selected' : '') + '>In Conversation</option>';
            html += '<option value="dormant"' + (this.recruitersState.filters.status === 'dormant' ? ' selected' : '') + '>Dormant</option>';
            html += '</select>';

            // Tag filter
            html += '<select id="recruiter-filter-tag" class="sffc-crm-select">';
            html += '<option value="">All Tags</option>';
            this.userTags.forEach(function(tag) {
                var selected = this.recruitersState.filters.tag_id == tag.id ? ' selected' : '';
                html += '<option value="' + tag.id + '"' + selected + '>' + this.escapeHtml(tag.name) + '</option>';
            }, this);
            html += '</select>';

            // Sort
            html += '<select id="recruiter-sort" class="sffc-crm-select">';
            html += '<option value="saved_at"' + (this.recruitersState.sort === 'saved_at' ? ' selected' : '') + '>Recently Saved</option>';
            html += '<option value="last_contacted_at"' + (this.recruitersState.sort === 'last_contacted_at' ? ' selected' : '') + '>Recently Contacted</option>';
            html += '<option value="name"' + (this.recruitersState.sort === 'name' ? ' selected' : '') + '>Name A-Z</option>';
            html += '<option value="priority"' + (this.recruitersState.sort === 'priority' ? ' selected' : '') + '>Priority</option>';
            html += '</select>';

            html += '</div>';
            html += '</div>';

            // List
            html += '<div class="sffc-crm-recruiters-list">';

            if (recruiters.length === 0) {
                html += this.getEmptyState('No recruiters found', 'Browse the Feed to discover recruiters posting relevant opportunities.');
            } else {
                recruiters.forEach(function(recruiter) {
                    html += this.renderRecruiterRowEnhanced(recruiter);
                }, this);
            }

            html += '</div>';

            // Pagination
            if (data.total_pages > 1) {
                html += this.renderPagination(data.page, data.total_pages, 'recruiters');
            }

            $panel.html(html);
        },

        /**
         * Render enhanced recruiter row
         */
        renderRecruiterRowEnhanced: function(recruiter) {
            var statusColors = {
                new: '#6b7280',
                contacted: '#3b82f6',
                replied: '#10b981',
                in_conversation: '#0d353e',
                dormant: '#f59e0b'
            };

            var html = '<div class="sffc-crm-recruiter-row" data-recruiter-id="' + recruiter.id + '">';

            // Bulk select checkbox (visible when bulk mode is on)
            html += '<div class="sffc-crm-bulk-checkbox" style="display: none;">';
            html += '<label class="sffc-crm-checkbox-wrapper">';
            html += '<input type="checkbox" class="sffc-crm-bulk-select-item" data-recruiter-id="' + recruiter.id + '" data-name="' + this.escapeHtml(recruiter.name) + '" data-email="' + (recruiter.email || '') + '">';
            html += '<span class="sffc-crm-checkmark"></span>';
            html += '</label>';
            html += '</div>';

            // Avatar
            html += '<div class="sffc-crm-recruiter-avatar">';
            if (recruiter.photo_url) {
                html += '<img src="' + recruiter.photo_url + '" alt="">';
            } else {
                html += '<div class="sffc-crm-avatar-placeholder">' + recruiter.name.charAt(0) + '</div>';
            }
            html += '</div>';

            // Details
            html += '<div class="sffc-crm-recruiter-details">';
            html += '<span class="sffc-crm-recruiter-name">' + this.escapeHtml(recruiter.name) + '</span>';
            html += '<span class="sffc-crm-recruiter-firm">' + this.escapeHtml(recruiter.firm || '') + '</span>';

            // Tags
            if (recruiter.tags && recruiter.tags.length > 0) {
                html += '<div class="sffc-crm-recruiter-tags">';
                recruiter.tags.forEach(function(tag) {
                    html += '<span class="sffc-crm-user-tag" data-tag-id="' + tag.id + '" style="background: ' + tag.color + '20; color: ' + tag.color + '; border-color: ' + tag.color + ';">';
                    html += this.escapeHtml(tag.name);
                    html += '<button class="sffc-crm-tag-remove" title="Remove tag">&times;</button>';
                    html += '</span>';
                }, this);
                html += '<button class="sffc-crm-add-tag-btn" data-recruiter-id="' + recruiter.id + '" title="Add tag">+</button>';
                html += '</div>';
            } else {
                html += '<div class="sffc-crm-recruiter-tags">';
                html += '<button class="sffc-crm-add-tag-btn" data-recruiter-id="' + recruiter.id + '" title="Add tag">+ Add Tag</button>';
                html += '</div>';
            }

            html += '</div>';

            // Status
            html += '<div class="sffc-crm-recruiter-status">';
            var status = recruiter.status || 'new';
            html += '<span class="sffc-crm-status-badge" style="background: ' + (statusColors[status] || '#6b7280') + ';">';
            html += status.replace('_', ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
            html += '</span>';
            html += '</div>';

            // Priority indicator
            if (recruiter.priority === 'high') {
                html += '<div class="sffc-crm-recruiter-priority sffc-crm-priority-high" title="High Priority">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
                html += '</div>';
            }

            // Last activity
            html += '<div class="sffc-crm-recruiter-last">';
            if (recruiter.last_contacted_at) {
                html += 'Contacted ' + this.timeAgo(recruiter.last_contacted_at);
            } else {
                html += 'Saved ' + this.timeAgo(recruiter.saved_at);
            }
            html += '</div>';

            // Actions
            html += '<div class="sffc-crm-recruiter-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-small" data-action="view-recruiter">View</button>';

            // Sequence enrollment button (if feature enabled)
            if (this.config.features && this.config.features.sequences) {
                html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-small sffc-crm-enroll-btn" data-recruiter-id="' + recruiter.id + '" data-recruiter-name="' + this.escapeHtml(recruiter.name) + '" title="Enroll in Sequence">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>';
                html += '</button>';
            }

            html += this.buildReachOutButton({
                recruiterId: recruiter.id,
                small: true
            });
            html += '</div>';

            html += '</div>';

            return html;
        },

        /**
         * Apply recruiter filters
         */
        applyRecruiterFilters: function() {
            this.recruitersState.filters = {
                status: $('#recruiter-filter-status').val(),
                tag_id: $('#recruiter-filter-tag').val(),
                search: $('#recruiter-search').val()
            };
            this.recruitersState.sort = $('#recruiter-sort').val();
            this.recruitersState.page = 1;

            // Mark as needing reload
            $('#panel-contacts').html('<div class="sffc-crm-loading">Loading...</div>');
            this.loadRecruitersEnhanced();
        },

        /**
         * Render pagination
         */
        renderPagination: function(currentPage, totalPages, target) {
            var html = '<div class="sffc-crm-pagination" data-target="' + target + '">';

            if (currentPage > 1) {
                html += '<button class="sffc-crm-page-btn" data-direction="prev" data-page="' + (currentPage - 1) + '">&larr; Previous</button>';
            }

            html += '<span class="sffc-crm-page-info">Page ' + currentPage + ' of ' + totalPages + '</span>';

            if (currentPage < totalPages) {
                html += '<button class="sffc-crm-page-btn" data-direction="next" data-page="' + (currentPage + 1) + '">Next &rarr;</button>';
            }

            html += '</div>';
            return html;
        },

        handlePaginationClick: function(target, page) {
            if (!target || !page) {
                return;
            }

            switch (target) {
                case 'recruiters':
                    this.recruitersState.page = page;
                    this.loadRecruitersEnhanced();
                    break;
            }
        },

        // ============================================
        // PHASE 2: Tags Management
        // ============================================

        /**
         * Load user tags
         */
        loadUserTags: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_tags',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.userTags = response.data.tags || [];
                    }
                }
            });
        },

        /**
         * Open tag manager modal
         */
        openTagManager: function(recruiterId) {
            var self = this;
            var html = '<div class="sffc-crm-tag-manager-modal" data-recruiter-id="' + recruiterId + '">';

            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Manage Tags</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            html += '<div class="sffc-crm-tag-manager-content">';

            // Create new tag
            html += '<div class="sffc-crm-create-tag">';
            html += '<input type="text" id="new-tag-name" placeholder="Create new tag..." class="sffc-crm-input">';
            html += '<input type="color" id="new-tag-color" value="#6b7280" class="sffc-crm-color-input">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-btn-small sffc-crm-create-tag-btn">Create</button>';
            html += '</div>';

            // Existing tags
            html += '<div class="sffc-crm-tag-list">';
            if (this.userTags.length === 0) {
                html += '<p class="sffc-crm-no-tags">No tags created yet. Create your first tag above.</p>';
            } else {
                html += '<p class="sffc-crm-tag-instruction">Click a tag to add it to this recruiter:</p>';
                this.userTags.forEach(function(tag) {
                    html += '<button class="sffc-crm-tag-option" data-tag-id="' + tag.id + '" style="background: ' + tag.color + '20; color: ' + tag.color + '; border-color: ' + tag.color + ';">';
                    html += this.escapeHtml(tag.name);
                    html += '</button>';
                }, this);
            }
            html += '</div>';

            html += '</div>';
            html += '</div>';

            this.showModal(html);
        },

        /**
         * Create new tag
         */
        createNewTag: function() {
            var self = this;
            var name = $('#new-tag-name').val().trim();
            var color = $('#new-tag-color').val();

            if (!name) {
                this.showError('Please enter a tag name');
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_create_tag',
                    name: name,
                    color: color,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.userTags.push(response.data.tag);
                        self.showSuccess('Tag created');
                        // Refresh the modal
                        var recruiterId = $('.sffc-crm-tag-manager-modal').data('recruiter-id');
                        self.closeModal();
                        self.openTagManager(recruiterId);
                    } else {
                        self.showError(response.data.message || 'Failed to create tag');
                    }
                }
            });
        },

        /**
         * Add tag to recruiter
         */
        addTagToRecruiter: function(recruiterId, tagId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_add_tag_to_recruiter',
                    recruiter_id: recruiterId,
                    tag_id: tagId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Tag added');
                        self.closeModal();
                        self.loadRecruitersEnhanced();
                    } else {
                        self.showError(response.data.message || 'Failed to add tag');
                    }
                }
            });
        },

        /**
         * Remove tag from recruiter
         */
        removeTagFromRecruiter: function(recruiterId, tagId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_remove_tag_from_recruiter',
                    recruiter_id: recruiterId,
                    tag_id: tagId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Tag removed');
                        self.loadRecruitersEnhanced();
                    } else {
                        self.showError(response.data.message || 'Failed to remove tag');
                    }
                }
            });
        },

        // ============================================
        // PHASE 2: Notes Management
        // ============================================

        /**
         * Open add note modal
         */
        openAddNoteModal: function(recruiterId) {
            var html = '<div class="sffc-crm-note-modal" data-recruiter-id="' + recruiterId + '">';

            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Add Note</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            html += '<div class="sffc-crm-note-form">';
            html += '<textarea id="note-content" class="sffc-crm-textarea" rows="6" placeholder="Write your note..."></textarea>';
            html += '<div class="sffc-crm-note-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-modal-close">Cancel</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="save-note-btn" data-recruiter-id="' + recruiterId + '">Save Note</button>';
            html += '</div>';
            html += '</div>';

            html += '</div>';

            this.showModal(html);
            this.bindNoteModalEvents();
        },

        /**
         * Bind note modal events
         */
        bindNoteModalEvents: function() {
            var self = this;

            $(document).off('click', '#save-note-btn').on('click', '#save-note-btn', function() {
                var recruiterId = $(this).data('recruiter-id');
                var content = $('#note-content').val().trim();

                if (!content) {
                    self.showError('Please enter a note');
                    return;
                }

                self.saveNote(recruiterId, content);
            });
        },

        /**
         * Save note
         */
        saveNote: function(recruiterId, content) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_add_recruiter_note',
                    recruiter_id: recruiterId,
                    content: content,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Note saved');
                        self.closeModal();
                        // Refresh recruiter detail if open
                        if ($('.sffc-crm-recruiter-detail').length) {
                            self.viewRecruiter(recruiterId);
                        }
                    } else {
                        self.showError(response.data.message || 'Failed to save note');
                    }
                }
            });
        },

        /**
         * Edit note - opens inline editor
         */
        editNote: function(noteId) {
            var self = this;
            var $noteItem = $('.sffc-crm-note-item[data-note-id="' + noteId + '"]');
            var $content = $noteItem.find('.sffc-crm-note-content');
            var currentText = $content.text().trim();

            // Replace content with textarea
            var $editor = $('<div class="sffc-crm-note-editor">' +
                '<textarea class="sffc-crm-note-editor-textarea">' + currentText + '</textarea>' +
                '<div class="sffc-crm-note-editor-actions">' +
                    '<button type="button" class="sffc-crm-btn sffc-crm-btn-sm sffc-crm-btn-primary sffc-crm-note-save">Save</button>' +
                    '<button type="button" class="sffc-crm-btn sffc-crm-btn-sm sffc-crm-btn-text sffc-crm-note-cancel">Cancel</button>' +
                '</div>' +
            '</div>');

            $content.hide().after($editor);
            $editor.find('textarea').focus();

            // Save handler
            $editor.find('.sffc-crm-note-save').on('click', function() {
                var newContent = $editor.find('textarea').val().trim();
                if (!newContent) {
                    self.showError('Note cannot be empty');
                    return;
                }

                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_update_note',
                        note_id: noteId,
                        content: newContent,
                        nonce: self.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $content.text(newContent).show();
                            $editor.remove();
                            self.showSuccess('Note updated');
                        } else {
                            self.showError(response.data.message || 'Failed to update note');
                        }
                    }
                });
            });

            // Cancel handler
            $editor.find('.sffc-crm-note-cancel').on('click', function() {
                $editor.remove();
                $content.show();
            });
        },

        /**
         * Delete note
         */
        deleteNote: function(noteId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_delete_note',
                    note_id: noteId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Note deleted');
                        $('.sffc-crm-note-item[data-note-id="' + noteId + '"]').fadeOut(200, function() {
                            $(this).remove();
                        });
                    } else {
                        self.showError(response.data.message || 'Failed to delete note');
                    }
                }
            });
        },

        /**
         * Toggle note pin
         */
        toggleNotePin: function(noteId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_toggle_note_pin',
                    note_id: noteId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var $note = $('.sffc-crm-note-item[data-note-id="' + noteId + '"]');
                        $note.toggleClass('is-pinned');
                        self.showSuccess(response.data.is_pinned ? 'Note pinned' : 'Note unpinned');
                    }
                }
            });
        },

        // ============================================
        // PHASE 2: Follow-up Scheduling
        // ============================================

        /**
         * Set recruiter follow-up date
         */
        setRecruiterFollowup: function(recruiterId, date) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_set_recruiter_followup',
                    recruiter_id: recruiterId,
                    date: date,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Follow-up scheduled');
                    } else {
                        self.showError(response.data.message || 'Failed to set follow-up');
                    }
                }
            });
        },

        // ============================================
        // PHASE 2: Enhanced Recruiter Detail
        // ============================================

        /**
         * View recruiter detail (enhanced)
         */
        viewRecruiterEnhanced: function(recruiterId) {
            var self = this;

            this.showModal('<div class="sffc-crm-modal-loading">Loading recruiter...</div>');

            // Load both recruiter and intelligence data
            $.when(
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_get_recruiter',
                        recruiter_id: recruiterId,
                        nonce: this.config.nonce
                    }
                }),
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_get_recruiter_intelligence',
                        recruiter_id: recruiterId,
                        nonce: this.config.nonce
                    }
                })
            ).done(function(recruiterRes, intelligenceRes) {
                if (recruiterRes[0].success) {
                    var data = recruiterRes[0].data;
                    if (intelligenceRes[0].success) {
                        data.intelligence = intelligenceRes[0].data;
                    }
                    self.renderRecruiterDetailEnhanced(data);
                } else {
                    self.closeModal();
                    self.showError('Failed to load recruiter');
                }
            }).fail(function() {
                self.closeModal();
                self.showError('Failed to load recruiter');
            });
        },

        /**
         * Render enhanced recruiter detail modal
         */
        renderRecruiterDetailEnhanced: function(recruiter) {
            var html = '<div class="sffc-crm-recruiter-detail sffc-crm-recruiter-detail-enhanced">';

            // Header
            html += '<div class="sffc-crm-modal-header">';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            // Recruiter header
            html += '<div class="sffc-crm-detail-recruiter-header">';
            if (recruiter.photo_url) {
                html += '<img src="' + recruiter.photo_url + '" alt="" class="sffc-crm-detail-avatar-lg">';
            } else {
                html += '<div class="sffc-crm-detail-avatar-lg sffc-crm-avatar-placeholder">' + recruiter.name.charAt(0) + '</div>';
            }
            html += '<div class="sffc-crm-detail-recruiter-main">';
            html += '<h2>' + this.escapeHtml(recruiter.name) + '</h2>';
            if (recruiter.title) {
                html += '<p class="sffc-crm-detail-title">' + this.escapeHtml(recruiter.title) + '</p>';
            }
            if (recruiter.firm) {
                html += '<p class="sffc-crm-detail-firm">' + this.escapeHtml(recruiter.firm) + '</p>';
            }
            html += '</div>';
            html += '</div>';

            // Status and Follow-up
            html += '<div class="sffc-crm-detail-status-row">';

            // Status badge
            if (recruiter.status) {
                var statusColors = {
                    new: '#6b7280',
                    contacted: '#3b82f6',
                    replied: '#10b981',
                    in_conversation: '#0d353e',
                    dormant: '#f59e0b'
                };
                var status = recruiter.status || 'new';
                html += '<span class="sffc-crm-status-badge" style="background: ' + (statusColors[status] || '#6b7280') + ';">';
                html += status.replace('_', ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                html += '</span>';
            }

            // Follow-up date
            html += '<div class="sffc-crm-followup-section">';
            html += '<label>Follow-up:</label>';
            html += '<input type="date" class="sffc-crm-followup-date sffc-crm-input" data-recruiter-id="' + recruiter.id + '" value="' + (recruiter.followup_date || '') + '">';
            html += '</div>';

            html += '</div>';

            // Tags
            html += '<div class="sffc-crm-detail-tags">';
            if (recruiter.tags && recruiter.tags.length > 0) {
                recruiter.tags.forEach(function(tag) {
                    html += '<span class="sffc-crm-user-tag" style="background: ' + tag.color + '20; color: ' + tag.color + ';">' + this.escapeHtml(tag.name) + '</span>';
                }, this);
            }
            html += '<button class="sffc-crm-add-tag-btn" data-recruiter-id="' + recruiter.id + '">+ Add Tag</button>';
            html += '</div>';

            // Intelligence Section
            if (recruiter.intelligence) {
                html += '<div class="sffc-crm-intelligence-section">';
                html += '<h4>Intelligence</h4>';
                html += '<div class="sffc-crm-intelligence-grid">';

                var intel = recruiter.intelligence;

                // Response rate
                if (intel.response_rate !== undefined) {
                    html += '<div class="sffc-crm-intel-card">';
                    html += '<span class="sffc-crm-intel-value">' + Math.round(intel.response_rate) + '%</span>';
                    html += '<span class="sffc-crm-intel-label">Response Rate</span>';
                    html += '</div>';
                }

                // Total posts
                if (intel.total_posts !== undefined) {
                    html += '<div class="sffc-crm-intel-card">';
                    html += '<span class="sffc-crm-intel-value">' + intel.total_posts + '</span>';
                    html += '<span class="sffc-crm-intel-label">Job Posts</span>';
                    html += '</div>';
                }

                // Top sectors
                if (intel.top_sectors && intel.top_sectors.length > 0) {
                    html += '<div class="sffc-crm-intel-card sffc-crm-intel-wide">';
                    html += '<span class="sffc-crm-intel-label">Top Sectors</span>';
                    html += '<div class="sffc-crm-intel-tags">';
                    intel.top_sectors.slice(0, 3).forEach(function(sector) {
                        html += '<span class="sffc-crm-tag">' + this.escapeHtml(sector.sector) + ' (' + sector.count + ')</span>';
                    }, this);
                    html += '</div>';
                    html += '</div>';
                }

                // Seniority levels
                if (intel.seniority_levels && intel.seniority_levels.length > 0) {
                    html += '<div class="sffc-crm-intel-card sffc-crm-intel-wide">';
                    html += '<span class="sffc-crm-intel-label">Seniority Levels</span>';
                    html += '<div class="sffc-crm-intel-tags">';
                    intel.seniority_levels.slice(0, 3).forEach(function(level) {
                        html += '<span class="sffc-crm-tag">' + this.escapeHtml(level.seniority || 'Unknown').toUpperCase() + ' (' + level.count + ')</span>';
                    }, this);
                    html += '</div>';
                    html += '</div>';
                }

                html += '</div>';
                html += '</div>';
            }

            // Notes section
            html += '<div class="sffc-crm-notes-section">';
            html += '<div class="sffc-crm-notes-header">';
            html += '<h4>Notes</h4>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-add-note-btn" data-recruiter-id="' + recruiter.id + '">+ Add Note</button>';
            html += '</div>';
            html += '<div class="sffc-crm-notes-list">';

            if (recruiter.notes && recruiter.notes.length > 0) {
                recruiter.notes.forEach(function(note) {
                    html += '<div class="sffc-crm-note-item' + (note.is_pinned == 1 ? ' is-pinned' : '') + '" data-note-id="' + note.id + '">';
                    html += '<div class="sffc-crm-note-content">' + this.escapeHtml(note.content) + '</div>';
                    html += '<div class="sffc-crm-note-meta">';
                    html += '<span class="sffc-crm-note-date">' + this.timeAgo(note.created_at) + '</span>';
                    html += '<div class="sffc-crm-note-actions-inline">';
                    html += '<button class="sffc-crm-note-pin" title="Pin note"><svg width="14" height="14" viewBox="0 0 24 24" fill="' + (note.is_pinned == 1 ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2"><path d="M12 17.75l-6.172 3.245 1.179-6.873-4.993-4.867 6.9-1.002L12 2l3.086 6.253 6.9 1.002-4.993 4.867 1.179 6.873z"/></svg></button>';
                    html += '<button class="sffc-crm-note-delete" title="Delete note"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                }, this);
            } else {
                html += '<p class="sffc-crm-no-notes">No notes yet. Add your first note above.</p>';
            }

            html += '</div>';
            html += '</div>';

            // Activity timeline
            if (recruiter.activity && recruiter.activity.length > 0) {
                html += '<div class="sffc-crm-activity-section">';
                html += '<h4>Activity</h4>';
                html += '<div class="sffc-crm-timeline">';

                recruiter.activity.forEach(function(event) {
                    html += '<div class="sffc-crm-timeline-item">';
                    html += '<div class="sffc-crm-timeline-dot"></div>';
                    html += '<div class="sffc-crm-timeline-content">';
                    html += '<span class="sffc-crm-timeline-action">' + this.escapeHtml(event.action_type) + '</span>';
                    if (event.details) {
                        html += '<span class="sffc-crm-timeline-details">' + this.escapeHtml(event.details) + '</span>';
                    }
                    html += '<span class="sffc-crm-timeline-date">' + this.timeAgo(event.created_at) + '</span>';
                    html += '</div>';
                    html += '</div>';
                }, this);

                html += '</div>';
                html += '</div>';
            }

            // Recent posts
            if (recruiter.recent_posts && recruiter.recent_posts.length > 0) {
                html += '<div class="sffc-crm-detail-section">';
                html += '<h4>Recent Posts</h4>';
                html += '<div class="sffc-crm-detail-posts">';
                recruiter.recent_posts.forEach(function(post) {
                    html += '<div class="sffc-crm-detail-post-item">';
                    html += '<strong>' + this.escapeHtml(post.role_title) + '</strong>';
                    if (post.company) {
                        html += ' at ' + this.escapeHtml(post.company);
                    }
                    html += '<br><small>' + this.timeAgo(post.posted_at) + '</small>';
                    html += '</div>';
                }, this);
                html += '</div>';
                html += '</div>';
            }

            // Sequence Enrollment Section (if feature enabled)
            if (this.config.features && this.config.features.sequences) {
                html += '<div class="sffc-crm-sequence-enrollment-section">';
                html += '<div class="sffc-crm-section-header">';
                html += '<h4>Sequences</h4>';
                html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-secondary sffc-crm-enroll-btn" data-recruiter-id="' + recruiter.id + '" data-recruiter-name="' + this.escapeHtml(recruiter.name) + '">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Enroll';
                html += '</button>';
                html += '</div>';

                // Active enrollments
                if (recruiter.active_enrollments && recruiter.active_enrollments.length > 0) {
                    html += '<div class="sffc-crm-active-enrollments">';
                    recruiter.active_enrollments.forEach(function(enrollment) {
                        html += '<div class="sffc-crm-enrollment-item" data-enrollment-id="' + enrollment.id + '">';
                        html += '<div class="sffc-crm-enrollment-info">';
                        html += '<span class="sffc-crm-enrollment-sequence">' + this.escapeHtml(enrollment.sequence_name) + '</span>';
                        html += '<span class="sffc-crm-enrollment-progress">Step ' + (enrollment.current_step_index + 1) + ' of ' + enrollment.total_steps + '</span>';
                        html += '</div>';
                        html += '<div class="sffc-crm-enrollment-actions">';
                        if (enrollment.status === 'active') {
                            html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" data-action="pause-enrollment" data-enrollment-id="' + enrollment.id + '" title="Pause">Pause</button>';
                        } else if (enrollment.status === 'paused') {
                            html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" data-action="resume-enrollment" data-enrollment-id="' + enrollment.id + '" title="Resume">Resume</button>';
                        }
                        html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small sffc-crm-btn-danger" data-action="remove-enrollment" data-enrollment-id="' + enrollment.id + '" title="Remove">Remove</button>';
                        html += '</div>';
                        html += '</div>';
                    }, this);
                    html += '</div>';
                } else {
                    html += '<p class="sffc-crm-no-enrollments">Not enrolled in any sequences yet.</p>';
                }

                html += '</div>';
            }

            // Actions
            html += '<div class="sffc-crm-detail-actions">';
            html += this.buildReachOutButton({
                recruiterId: recruiter.id
            });
            html += '<button class="sffc-crm-expert-reach-btn sffc-crm-expert-request-btn" data-recruiter-id="' + recruiter.id + '" data-recruiter-name="' + this.escapeHtml(recruiter.name) + '" data-recruiter-title="' + this.escapeHtml(recruiter.title || '') + '" data-recruiter-firm="' + this.escapeHtml(recruiter.firm || '') + '" data-recruiter-photo="' + this.escapeHtml(recruiter.photo_url || '') + '">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>';
            html += '<span>Expert Send CV</span></button>';
            html += '</div>';

            html += '</div>';

            this.updateModalContent(html);
        },

        // ============================================
        // PHASE 2: Dashboard & Analytics
        // ============================================

        /**
         * Load dashboard
         */
        loadDashboard: function() {
            var self = this;
            var $panel = $('#panel-dashboard');

            if (!this.config.isLoggedIn) {
                $panel.html('<div class="sffc-crm-empty-state"><p>Please <a href="' + this.config.loginUrl + '">sign in</a> to view your dashboard.</p></div>');
                return;
            }

            $panel.html('<div class="sffc-crm-loading">Loading dashboard...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_dashboard_stats',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.renderDashboard(response.data);
                    } else {
                        $panel.html('<div class="sffc-crm-empty-state"><p>Failed to load dashboard data.</p></div>');
                    }
                },
                error: function() {
                    $panel.html('<div class="sffc-crm-empty-state"><p>Error loading dashboard. Please try again.</p></div>');
                }
            });
        },

        /**
         * Render dashboard - consolidated view
         */
        renderDashboard: function(data) {
            var self = this;
            var $panel = $('#panel-dashboard');
            var html = '';

            // Safely get values with defaults
            var summary = data.summary || {};
            var outreach = data.outreach || {};
            var funnel = data.funnel || [];
            var upcoming = data.upcoming || {};
            var recentActivity = data.recent_activity || [];

            // Header
            html += '<div class="sffc-crm-dashboard-header">';
            html += '<h2>Dashboard</h2>';
            html += '</div>';

            // === SUMMARY STATS CARDS ===
            html += '<div class="sffc-crm-dashboard-stats">';

            html += '<div class="sffc-crm-stat-card">';
            html += '<span class="sffc-crm-stat-value">' + (summary.recruiters || 0) + '</span>';
            html += '<span class="sffc-crm-stat-label">Recruiters</span>';
            html += '</div>';

            html += '<div class="sffc-crm-stat-card">';
            html += '<span class="sffc-crm-stat-value">' + (summary.posts || 0) + '</span>';
            html += '<span class="sffc-crm-stat-label">Posts</span>';
            html += '</div>';

            html += '<div class="sffc-crm-stat-card">';
            html += '<span class="sffc-crm-stat-value">' + (summary.contacts || 0) + '</span>';
            html += '<span class="sffc-crm-stat-label">Contacts</span>';
            html += '</div>';

            html += '<div class="sffc-crm-stat-card">';
            html += '<span class="sffc-crm-stat-value">' + (summary.pipeline || 0) + '</span>';
            html += '<span class="sffc-crm-stat-label">Pipeline</span>';
            html += '</div>';

            html += '<div class="sffc-crm-stat-card sffc-crm-stat-success">';
            html += '<span class="sffc-crm-stat-value">' + (summary.offers || 0) + '</span>';
            html += '<span class="sffc-crm-stat-label">Offers</span>';
            html += '</div>';

            html += '</div>';

            // === OUTREACH ACTIVITY BAR ===
            html += '<div class="sffc-crm-dashboard-section sffc-crm-outreach-summary">';
            html += '<h3>Outreach Activity</h3>';
            html += '<div class="sffc-crm-outreach-stats">';

            html += '<div class="sffc-crm-outreach-stat">';
            html += '<span class="sffc-crm-outreach-value">' + (outreach.reached_out || 0) + '</span>';
            html += '<span class="sffc-crm-outreach-label">Reached Out</span>';
            html += '</div>';

            html += '<div class="sffc-crm-outreach-stat sffc-crm-outreach-pending">';
            html += '<span class="sffc-crm-outreach-value">' + (outreach.awaiting_reply || 0) + '</span>';
            html += '<span class="sffc-crm-outreach-label">Awaiting Reply</span>';
            html += '</div>';

            html += '<div class="sffc-crm-outreach-stat sffc-crm-outreach-success">';
            html += '<span class="sffc-crm-outreach-value">' + (outreach.replied || 0) + '</span>';
            html += '<span class="sffc-crm-outreach-label">Replied</span>';
            html += '</div>';

            html += '</div>';
            html += '</div>';

            // === PIPELINE FUNNEL ===
            if (funnel.length > 0) {
                html += '<div class="sffc-crm-dashboard-section">';
                html += '<h3>Pipeline Funnel</h3>';
                html += '<div class="sffc-crm-funnel">';

                var maxCount = 1;
                for (var i = 0; i < funnel.length; i++) {
                    var cnt = parseInt(funnel[i].count) || 0;
                    if (cnt > maxCount) maxCount = cnt;
                }

                for (var j = 0; j < funnel.length; j++) {
                    var stage = funnel[j];
                    var stageCount = parseInt(stage.count) || 0;
                    var width = Math.max((stageCount / maxCount) * 100, 5);
                    var stageLabel = stage.label || (stage.stage ? stage.stage.replace(/_/g, ' ') : 'Unknown');
                    var stageColor = stage.color || '#6b7280';

                    html += '<div class="sffc-crm-funnel-stage">';
                    html += '<span class="sffc-crm-funnel-label">' + self.escapeHtml(stageLabel) + '</span>';
                    html += '<div class="sffc-crm-funnel-bar-wrap">';
                    html += '<div class="sffc-crm-funnel-bar" style="width: ' + width + '%; background-color: ' + stageColor + ';">';
                    html += '<span class="sffc-crm-funnel-count">' + stageCount + '</span>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                }

                html += '</div>';
                html += '</div>';
            }

            // === TWO-COLUMN LAYOUT FOR UPCOMING ITEMS ===
            html += '<div class="sffc-crm-dashboard-grid">';

            // Upcoming Follow-ups
            html += '<div class="sffc-crm-dashboard-section">';
            html += '<h3>Upcoming Follow-ups</h3>';
            var followups = upcoming.followups || [];
            if (followups.length > 0) {
                html += '<div class="sffc-crm-upcoming-list">';
                for (var k = 0; k < followups.length; k++) {
                    var fu = followups[k];
                    var fuDate = fu.followup_date ? new Date(fu.followup_date).toLocaleDateString() : '';
                    html += '<div class="sffc-crm-upcoming-item">';
                    html += '<span class="sffc-crm-upcoming-name">' + self.escapeHtml(fu.name || 'Unknown') + '</span>';
                    html += '<span class="sffc-crm-upcoming-date">' + fuDate + '</span>';
                    html += '</div>';
                }
                html += '</div>';
            } else {
                html += '<p class="sffc-crm-empty-text">No upcoming follow-ups</p>';
            }
            html += '</div>';

            // Pending Tasks
            html += '<div class="sffc-crm-dashboard-section">';
            html += '<h3>Pending Tasks</h3>';
            var tasks = upcoming.tasks || [];
            if (tasks.length > 0) {
                html += '<div class="sffc-crm-upcoming-list">';
                for (var m = 0; m < tasks.length; m++) {
                    var task = tasks[m];
                    var taskDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : 'No date';
                    html += '<div class="sffc-crm-upcoming-item">';
                    html += '<span class="sffc-crm-upcoming-name">' + self.escapeHtml(task.title || 'Task') + '</span>';
                    html += '<span class="sffc-crm-upcoming-date">' + taskDate + '</span>';
                    html += '</div>';
                }
                html += '</div>';
            } else {
                html += '<p class="sffc-crm-empty-text">No pending tasks</p>';
            }
            html += '</div>';

            html += '</div>';

            // === RECENT ACTIVITY ===
            html += '<div class="sffc-crm-dashboard-section">';
            html += '<h3>Recent Activity</h3>';
            if (recentActivity.length > 0) {
                html += '<div class="sffc-crm-activity-list">';
                for (var n = 0; n < recentActivity.length; n++) {
                    var activity = recentActivity[n];
                    var activityType = self.formatActivityType(activity.activity_type);
                    var activityTime = activity.created_at ? self.timeAgo(activity.created_at) : '';
                    var recruiterName = activity.recruiter_name || '';

                    html += '<div class="sffc-crm-activity-item">';
                    html += '<span class="sffc-crm-activity-icon">' + self.getActivityIcon(activity.activity_type) + '</span>';
                    html += '<div class="sffc-crm-activity-content">';
                    html += '<span class="sffc-crm-activity-text">' + activityType;
                    if (recruiterName) {
                        html += ' - ' + self.escapeHtml(recruiterName);
                    }
                    html += '</span>';
                    html += '<span class="sffc-crm-activity-time">' + activityTime + '</span>';
                    html += '</div>';
                    html += '</div>';
                }
                html += '</div>';
            } else {
                html += '<p class="sffc-crm-empty-text">No recent activity</p>';
            }
            html += '</div>';

            $panel.html(html);
        },

        /**
         * Format activity type for display
         */
        formatActivityType: function(type) {
            if (!type) return 'Activity';
            var typeMap = {
                'recruiter_saved': 'Saved recruiter',
                'recruiter_unsaved': 'Removed recruiter',
                'post_saved': 'Saved post',
                'post_unsaved': 'Removed post',
                'outreach_created': 'Created outreach',
                'outreach_sent': 'Sent message',
                'outreach_replied': 'Received reply',
                'pipeline_added': 'Added to pipeline',
                'stage_changed': 'Updated pipeline stage',
                'contact_saved': 'Saved contact',
                'contact_unsaved': 'Removed contact',
                'contact_added': 'Added contact',
                'task_created': 'Created task',
                'task_completed': 'Completed task',
                'post_viewed': 'Viewed post',
                'post_detail_viewed': 'Viewed post details',
                'status_changed': 'Updated status',
                'note_added': 'Added note',
                'followup_set': 'Set follow-up'
            };
            return typeMap[type] || type.replace(/_/g, ' ');
        },

        /**
         * Get icon for activity type
         */
        getActivityIcon: function(type) {
            var icons = {
                'recruiter_saved': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
                'recruiter_unsaved': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle><line x1="18" y1="8" x2="23" y2="13"></line></svg>',
                'post_saved': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>',
                'post_unsaved': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>',
                'outreach_sent': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>',
                'outreach_created': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>',
                'outreach_replied': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>',
                'pipeline_added': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
                'contact_saved': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
                'contact_unsaved': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>',
                'note_added': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>',
                'followup_set': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>'
            };
            return icons[type] || '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>';
        },

        // ============================================
        // PHASE 3: Bulk Selection State
        // ============================================

        /**
         * Bulk selection state
         */
        bulkSelection: {
            mode: false,
            selected: [],
            type: null  // 'recruiters' or 'posts'
        },

        // Track selected roles for outreach lists and the originating tab
        selectedPostsForOutreach: [],
        selectedPostsSource: 'all-roles',

        // ============================================
        // PHASE 3: Enhanced Message Composer
        // ============================================

        /**
         * Outreach state
         */
        outreachState: {
            channel: 'email',
            templateId: null,
            recruiterId: null,
            postId: null,
            recruiterEmail: null,
            recruiterName: null,
            recruiterLinkedIn: null,
            variables: {},
            isGenerating: false
        },

        /**
         * Build mailto URL with subject and body
         */
        buildMailtoUrl: function(email, subject, body) {
            if (!email) return null;

            var url = 'mailto:' + encodeURIComponent(email);
            var params = [];

            if (subject) {
                params.push('subject=' + encodeURIComponent(subject));
            }
            if (body) {
                params.push('body=' + encodeURIComponent(body));
            }

            if (params.length > 0) {
                url += '?' + params.join('&');
            }

            return url;
        },

        /**
         * Open mailto link
         */
        openMailto: function(email, subject, body) {
            var url = this.buildMailtoUrl(email, subject, body);
            if (url) {
                window.location.href = url;
                return true;
            }
            return false;
        },

        /**
         * Open enhanced reach out modal
         * @param {number} postId - The post ID (optional)
         * @param {number} recruiterId - The recruiter ID
         * @param {boolean} autoGenerate - Whether to auto-generate AI message (default: true)
         */
        openReachOutModal: function(postId, recruiterId, autoGenerate) {
            var self = this;

            // Default autoGenerate to true if not specified
            if (typeof autoGenerate === 'undefined') {
                autoGenerate = true;
            }

            // Show loading modal
            this.showModal('<div class="sffc-crm-modal-loading">Loading composer...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_compose_data',
                    post_id: postId,
                    recruiter_id: recruiterId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.outreachState.recruiterId = recruiterId;
                        self.outreachState.postId = postId;
                        self.outreachState.variables = response.data.variables || {};
                        self.outreachState.recruiterEmail = response.data.recruiter.email || null;
                        self.outreachState.recruiterName = response.data.recruiter.name || null;
                        self.outreachState.recruiterLinkedIn = response.data.recruiter.linkedin_url || null;
                        self.outreachState.autoGenerate = autoGenerate; // Set auto-generate flag
                        self.renderEnhancedComposeModal(response.data);
                    } else {
                        self.closeModal();
                        self.handleError(response);
                    }
                },
                error: function() {
                    self.closeModal();
                    self.showError('Failed to load compose data');
                }
            });
        },

        /**
         * Render enhanced compose modal
         */
        renderEnhancedComposeModal: function(data) {
            var self = this;
            var hasEmail = data.recruiter.email ? true : false;
            var hasLinkedIn = data.recruiter.linkedin_url ? true : false;
            var directSendEnabled = this.isEmailOAuthMode();
            var hasConnectedEmail = directSendEnabled && this.emailAccounts && this.emailAccounts.length > 0;
            var sendInfoText;
            var sendButtonText;

            if (hasConnectedEmail) {
                sendInfoText = 'Sent directly via your connected inbox';
                sendButtonText = 'Send via MENA Careers';
            } else if (directSendEnabled) {
                sendInfoText = 'Connect your email or copy the message to send manually';
                sendButtonText = 'Copy & Send Manually';
            } else {
                sendInfoText = 'Opens your email client so you can send from your own address';
                sendButtonText = 'Open Email App';
            }
            var html = '<div class="sffc-crm-compose-modal">';

            html += '<div class="sffc-crm-compose-header">';
            html += '<h3>Compose Message</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            html += '<div class="sffc-crm-compose-layout">';
            html += '<aside class="sffc-crm-compose-sidebar">';
            html += '<div class="sffc-crm-compose-recipient">';
            if (data.recruiter.photo_url) {
                html += '<img src="' + data.recruiter.photo_url + '" alt="" class="sffc-crm-avatar">';
            } else {
                html += '<div class="sffc-crm-avatar sffc-crm-avatar-placeholder">' + data.recruiter.name.charAt(0) + '</div>';
            }
            html += '<div class="sffc-crm-compose-recipient-info">';
            html += '<strong>' + this.escapeHtml(data.recruiter.name) + '</strong>';
            if (data.recruiter.firm) {
                html += '<span>' + this.escapeHtml(data.recruiter.firm) + '</span>';
            }
            html += '</div>';
            if (data.post && data.post.role_title) {
                html += '<div class="sffc-crm-compose-context">';
                html += '<span class="sffc-crm-compose-role">Re: ' + this.escapeHtml(data.post.role_title) + '</span>';
                html += '</div>';
            }
            html += '</div>';

            html += '<div class="sffc-crm-contact-indicators">';
            if (hasEmail) {
                html += '<small class="sffc-crm-contact-indicator sffc-crm-has-email" title="' + this.escapeHtml(data.recruiter.email) + '"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg> Email</small>';
            }
            if (hasLinkedIn) {
                html += '<small class="sffc-crm-contact-indicator sffc-crm-has-linkedin"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg> LinkedIn</small>';
            }
            if (!hasEmail && !hasLinkedIn) {
                html += '<small class="sffc-crm-contact-indicator sffc-crm-no-contact">No contact info</small>';
            }
            html += '</div>';

            html += '<div class="sffc-crm-compose-section">';
            html += '<label>Channel</label>';
            html += '<div class="sffc-crm-channel-toggle">';
            html += '<button class="sffc-crm-channel-btn active" data-channel="email">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
            html += 'Email';
            html += '</button>';
            html += '<button class="sffc-crm-channel-btn" data-channel="linkedin">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>';
            html += 'LinkedIn';
            html += '</button>';
            html += '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-compose-send-info">';
            html += '<span class="sffc-crm-send-info-email">' + sendInfoText + '</span>';
            html += '<span class="sffc-crm-send-info-linkedin" style="display: none;">Message will be copied to clipboard</span>';
            html += '</div>';

            if (data.usage) {
                html += '<div class="sffc-crm-compose-usage">';
                html += '<span class="sffc-crm-usage-count">' + data.usage.used + '/' + data.usage.limit + ' messages sent this month</span>';
                if (data.usage.remaining <= 5) {
                    html += '<span class="sffc-crm-usage-warning">Running low!</span>';
                }
                html += '</div>';
            }

            html += '</aside>';

            html += '<div class="sffc-crm-compose-main">';
            html += '<div class="sffc-crm-compose-section">';
            html += '<div class="sffc-crm-compose-template-row">';
            html += '<label for="compose-template">Template</label>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" id="manage-templates-btn">Manage</button>';
            html += '</div>';
            html += '<select id="compose-template" class="sffc-crm-select">';
            html += '<option value="">Start from scratch...</option>';
            if (data.templates && data.templates.length > 0) {
                data.templates.forEach(function(template) {
                    html += '<option value="' + template.id + '" data-channel="' + template.channel + '">' + this.escapeHtml(template.name) + '</option>';
                }, this);
            }
            html += '</select>';
            html += '</div>';

            html += '<div class="sffc-crm-compose-section sffc-crm-compose-subject" id="compose-subject-group">';
            html += '<label>Subject</label>';
            html += '<input type="text" id="compose-subject" class="sffc-crm-input" placeholder="Subject line...">';
            html += '</div>';

            html += '<div class="sffc-crm-compose-section">';
            html += '<div class="sffc-crm-compose-message-header">';
            html += '<label>Message</label>';
            html += '<div class="sffc-crm-compose-ai-actions">';
            if (this.config.features && this.config.features.ai_personalization) {
                html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" id="ai-generate-btn">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>';
                html += 'AI Generate';
                html += '</button>';
                html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" id="ai-improve-btn">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
                html += 'Improve';
                html += '</button>';
            }
            html += '</div>';
            html += '</div>';
            html += '<textarea id="compose-message" class="sffc-crm-textarea" rows="10" placeholder="Write your message..."></textarea>';
            html += '<div class="sffc-crm-compose-char-count"><span id="compose-char-count">0</span> characters</div>';
            html += '</div>';

            html += '<div class="sffc-crm-compose-section sffc-crm-compose-variables">';
            html += '<div class="sffc-crm-compose-variables-header">';
            html += '<label>Available Variables</label>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" id="toggle-variables-btn">';
            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>';
            html += '</button>';
            html += '</div>';
            html += '<div class="sffc-crm-variables-list" id="variables-list" style="display: none;">';
            html += this.renderVariablesList(data.variables);
            html += '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-compose-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary" id="compose-save-draft">Save Draft</button>';
            html += '<div class="sffc-crm-compose-send-group">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="compose-send-btn" data-recruiter-id="' + data.recruiter.id + '" data-post-id="' + (data.post ? data.post.id : '') + '">';
            html += '<span class="sffc-crm-send-email">' + sendButtonText + '</span>';
            html += '<span class="sffc-crm-send-linkedin" style="display: none;">Copy for LinkedIn</span>';
            html += '</button>';
            html += '</div>';
            html += '</div>';

            html += '</div>';
            html += '</div>';
            html += '</div>';

            this.updateModalContent(html);
            this.bindComposeEvents();
        },

        /**
         * Render variables list
         */
        renderVariablesList: function(variables) {
            if (!variables || Object.keys(variables).length === 0) {
                return '<p class="sffc-crm-no-variables">No variables available.</p>';
            }

            var html = '';
            var categories = {
                recruiter: { label: 'Recruiter', vars: [] },
                role: { label: 'Role', vars: [] },
                candidate: { label: 'Your Info', vars: [] }
            };

            // Categorize variables
            Object.keys(variables).forEach(function(key) {
                var value = variables[key];
                if (key.startsWith('recruiter_')) {
                    categories.recruiter.vars.push({ key: key, value: value });
                } else if (key.startsWith('candidate_')) {
                    categories.candidate.vars.push({ key: key, value: value });
                } else {
                    categories.role.vars.push({ key: key, value: value });
                }
            });

            Object.keys(categories).forEach(function(catKey) {
                var cat = categories[catKey];
                if (cat.vars.length > 0) {
                    html += '<div class="sffc-crm-variable-category">';
                    html += '<h5>' + cat.label + '</h5>';
                    cat.vars.forEach(function(v) {
                        html += '<div class="sffc-crm-variable-item" data-variable="' + v.key + '">';
                        html += '<code>{' + v.key + '}</code>';
                        html += '<span>' + this.escapeHtml(v.value || '(not set)') + '</span>';
                        html += '</div>';
                    }, this);
                    html += '</div>';
                }
            }, this);

            return html;
        },

        /**
         * Bind compose modal events
         */
        bindComposeEvents: function() {
            var self = this;

            // Channel switch
            $(document).off('click.compose', '.sffc-crm-channel-btn').on('click.compose', '.sffc-crm-channel-btn', function() {
                var channel = $(this).data('channel');
                self.outreachState.channel = channel;

                $('.sffc-crm-channel-btn').removeClass('active');
                $(this).addClass('active');

                if (channel === 'linkedin') {
                    $('#compose-subject-group').hide();
                    $('.sffc-crm-send-email').hide();
                    $('.sffc-crm-send-linkedin').show();
                    $('.sffc-crm-send-info-email').hide();
                    $('.sffc-crm-send-info-linkedin').show();
                } else {
                    $('#compose-subject-group').show();
                    $('.sffc-crm-send-email').show();
                    $('.sffc-crm-send-linkedin').hide();
                    $('.sffc-crm-send-info-email').show();
                    $('.sffc-crm-send-info-linkedin').hide();
                }

                // Filter templates by channel
                self.filterTemplatesByChannel(channel);
            });

            // Template selection
            $(document).off('change.compose', '#compose-template').on('change.compose', '#compose-template', function() {
                var templateId = $(this).val();
                if (templateId) {
                    self.loadTemplate(templateId);
                }
            });

            // Message character count
            $(document).off('input.compose', '#compose-message').on('input.compose', '#compose-message', function() {
                var count = $(this).val().length;
                $('#compose-char-count').text(count);
            });

            // Toggle variables
            $(document).off('click.compose', '#toggle-variables-btn').on('click.compose', '#toggle-variables-btn', function() {
                $('#variables-list').slideToggle(200);
                $(this).find('svg').toggleClass('rotated');
            });

            // Insert variable on click
            $(document).off('click.compose', '.sffc-crm-variable-item').on('click.compose', '.sffc-crm-variable-item', function() {
                var variable = '{' + $(this).data('variable') + '}';
                var $textarea = $('#compose-message');
                var cursorPos = $textarea[0].selectionStart;
                var textBefore = $textarea.val().substring(0, cursorPos);
                var textAfter = $textarea.val().substring(cursorPos);
                $textarea.val(textBefore + variable + textAfter);
                $textarea.focus();
                $textarea[0].selectionStart = $textarea[0].selectionEnd = cursorPos + variable.length;
            });

            // Preview message
            $(document).off('click.compose', '#preview-message-btn').on('click.compose', '#preview-message-btn', function() {
                self.previewMessage();
            });

            // AI Generate
            $(document).off('click.compose', '#ai-generate-btn').on('click.compose', '#ai-generate-btn', function() {
                self.generateAIMessage('generate');
            });

            // AI Improve
            $(document).off('click.compose', '#ai-improve-btn').on('click.compose', '#ai-improve-btn', function() {
                self.generateAIMessage('improve');
            });

            // Manage templates
            $(document).off('click.compose', '#manage-templates-btn').on('click.compose', '#manage-templates-btn', function() {
                self.openTemplateManager();
            });

            // Save draft
            $(document).off('click.compose', '#compose-save-draft').on('click.compose', '#compose-save-draft', function() {
                self.saveDraft();
            });

            // Send/Copy button
            $(document).off('click.compose', '#compose-send-btn').on('click.compose', '#compose-send-btn', function() {
                var recruiterId = $(this).data('recruiter-id');
                var postId = $(this).data('post-id');
                self.sendOutreach(recruiterId, postId);
            });

            // Auto-generate AI message when modal opens (if feature enabled)
            if (this.config.features && this.config.features.ai_personalization && this.outreachState.autoGenerate) {
                this.outreachState.autoGenerate = false; // Reset flag
                setTimeout(function() {
                    self.generateAIMessage('generate');
                }, 300); // Small delay to ensure modal is fully rendered
            }
        },

        /**
         * Filter templates by channel
         */
        filterTemplatesByChannel: function(channel) {
            $('#compose-template option').each(function() {
                var $opt = $(this);
                var templateChannel = $opt.data('channel');
                if (!templateChannel || templateChannel === 'both' || templateChannel === channel) {
                    $opt.show();
                } else {
                    $opt.hide();
                    if ($opt.is(':selected')) {
                        $('#compose-template').val('');
                    }
                }
            });
        },

        /**
         * Load template content
         */
        loadTemplate: function(templateId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_render_template',
                    template_id: templateId,
                    recruiter_id: this.outreachState.recruiterId,
                    post_id: this.outreachState.postId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.outreachState.templateId = templateId;
                        $('#compose-subject').val(response.data.subject || '');
                        $('#compose-message').val(response.data.content || '');
                        $('#compose-char-count').text((response.data.content || '').length);
                    }
                }
            });
        },

        /**
         * Preview message with variables substituted
         */
        previewMessage: function() {
            var self = this;
            var content = $('#compose-message').val();
            var subject = $('#compose-subject').val();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_render_template',
                    content: content,
                    subject: subject,
                    recruiter_id: this.outreachState.recruiterId,
                    post_id: this.outreachState.postId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showPreviewModal(response.data);
                    }
                }
            });
        },

        /**
         * Show preview modal
         */
        showPreviewModal: function(data) {
            var html = '<div class="sffc-crm-preview-modal">';
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Message Preview</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            if (data.subject) {
                html += '<div class="sffc-crm-preview-subject">';
                html += '<label>Subject:</label>';
                html += '<p>' + this.escapeHtml(data.subject) + '</p>';
                html += '</div>';
            }

            html += '<div class="sffc-crm-preview-content">';
            html += '<label>Message:</label>';
            html += '<div class="sffc-crm-preview-body">' + this.escapeHtml(data.content).replace(/\n/g, '<br>') + '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-preview-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-modal-close">Back to Edit</button>';
            html += '</div>';

            html += '</div>';

            // Show in a nested modal or replace content
            this.updateModalContent(html);
        },

        /**
         * Generate AI message
         */
        generateAIMessage: function(mode) {
            var self = this;

            if (this.outreachState.isGenerating) return;

            var currentContent = $('#compose-message').val();

            if (mode === 'improve' && !currentContent.trim()) {
                this.showError('Write some content first to improve');
                return;
            }

            this.outreachState.isGenerating = true;
            var $btn = mode === 'generate' ? $('#ai-generate-btn') : $('#ai-improve-btn');
            var originalText = $btn.html();
            $btn.html('<svg class="sffc-crm-spinner" width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="32" stroke-dashoffset="32"><animate attributeName="stroke-dashoffset" dur="1s" values="32;0" repeatCount="indefinite"/></circle></svg> Generating...');
            $btn.prop('disabled', true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_generate_message',
                    recruiter_id: this.outreachState.recruiterId,
                    post_id: this.outreachState.postId,
                    channel: this.outreachState.channel,
                    mode: mode,
                    current_content: currentContent,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.subject && self.outreachState.channel === 'email') {
                            $('#compose-subject').val(response.data.subject);
                        }
                        $('#compose-message').val(response.data.content);
                        $('#compose-char-count').text(response.data.content.length);
                        self.showSuccess('Message generated!');
                    } else {
                        self.handleError(response);
                    }
                },
                error: function() {
                    self.showError('AI generation failed. Please try again.');
                },
                complete: function() {
                    self.outreachState.isGenerating = false;
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Save draft
         */
        saveDraft: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_create_outreach',
                    recruiter_id: this.outreachState.recruiterId,
                    post_id: this.outreachState.postId,
                    channel: this.outreachState.channel,
                    template_id: this.outreachState.templateId,
                    subject: $('#compose-subject').val(),
                    content: $('#compose-message').val(),
                    status: 'draft',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Draft saved');
                    } else {
                        self.handleError(response);
                    }
                }
            });
        },

        /**
         * Send outreach message
         */
        sendOutreach: function(recruiterId, postId) {
            var self = this;
            var channel = this.outreachState.channel;
            var message = $('#compose-message').val();
            var subject = $('#compose-subject').val();
            var recruiterEmail = this.outreachState.recruiterEmail;

            if (!message.trim()) {
                this.showError('Please write a message');
                return;
            }

            if (channel === 'email' && !subject.trim()) {
                this.showError('Please add a subject line');
                return;
            }

            // For LinkedIn, copy to clipboard and log
            if (channel === 'linkedin') {
                this.copyToClipboardAndLog(recruiterId, postId, message);
                return;
            }
            if (channel === 'email') {
                if (this.isEmailOAuthMode()) {
                    if (this.emailAccounts && this.emailAccounts.length) {
                        this.sendConnectedEmail(recruiterId, postId);
                    } else {
                        this.showEmailConnectPrompt(recruiterId, postId);
                    }
                } else {
                    this.sendMailClientFallback(recruiterId, postId);
                }
            }
        },

        /**
         * Copy to clipboard and log for LinkedIn
         */
        copyToClipboardAndLog: function(recruiterId, postId, message) {
            var self = this;

            // First copy to clipboard
            navigator.clipboard.writeText(message).then(function() {
                // Then log the outreach
                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_create_outreach',
                        recruiter_id: recruiterId,
                        post_id: postId,
                        channel: 'linkedin',
                        template_id: self.outreachState.templateId,
                        content: message,
                        status: 'sent',
                        nonce: self.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.showSuccess('Message copied! Paste in LinkedIn.');
                            self.closeModal();
                        }
                    }
                });
            }).catch(function() {
                // Fallback
                var textarea = document.createElement('textarea');
                textarea.value = message;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                self.showSuccess('Message copied! Paste in LinkedIn.');
                self.closeModal();
            });
        },

        /**
         * Open template manager
         */
        openTemplateManager: function() {
            var self = this;

            // Store current compose state to return to
            var returnState = {
                subject: $('#compose-subject').val(),
                message: $('#compose-message').val(),
                channel: this.outreachState.channel
            };

            // Show loading
            this.updateModalContent('<div class="sffc-crm-modal-loading">Loading templates...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_templates',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderTemplateManager(response.data.templates, returnState);
                    }
                }
            });
        },

        /**
         * Render template manager
         */
        renderTemplateManager: function(templates, returnState) {
            var self = this;
            var html = '<div class="sffc-crm-template-manager">';

            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Message Templates</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            // Create new template
            html += '<div class="sffc-crm-template-create">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="create-template-btn">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>';
            html += 'Create New Template';
            html += '</button>';
            html += '</div>';

            // Templates list
            html += '<div class="sffc-crm-template-list">';
            if (!templates || templates.length === 0) {
                html += '<p class="sffc-crm-no-templates">No templates yet. Create your first one!</p>';
            } else {
                templates.forEach(function(template) {
                    html += '<div class="sffc-crm-template-item" data-template-id="' + template.id + '">';
                    html += '<div class="sffc-crm-template-info">';
                    html += '<strong>' + this.escapeHtml(template.name) + '</strong>';
                    if (template.is_system) {
                        html += '<span class="sffc-crm-template-badge">System</span>';
                    }
                    html += '<span class="sffc-crm-template-channel">' + template.channel + '</span>';
                    html += '<span class="sffc-crm-template-type">' + template.template_type.replace('_', ' ') + '</span>';
                    html += '</div>';
                    html += '<div class="sffc-crm-template-actions">';
                    if (!template.is_system) {
                        html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" data-action="edit" data-template-id="' + template.id + '">Edit</button>';
                        html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" data-action="delete" data-template-id="' + template.id + '">Delete</button>';
                    }
                    html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" data-action="duplicate" data-template-id="' + template.id + '">Duplicate</button>';
                    html += '</div>';
                    html += '</div>';
                }, this);
            }
            html += '</div>';

            // Back button
            html += '<div class="sffc-crm-template-footer">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary" id="back-to-compose-btn">Back to Compose</button>';
            html += '</div>';

            html += '</div>';

            this.updateModalContent(html);

            // Bind events
            $(document).off('click.templates', '#back-to-compose-btn').on('click.templates', '#back-to-compose-btn', function() {
                self.openReachOutModal(self.outreachState.postId, self.outreachState.recruiterId);
                // Restore state after modal renders
                setTimeout(function() {
                    $('#compose-subject').val(returnState.subject);
                    $('#compose-message').val(returnState.message);
                    $('#compose-char-count').text(returnState.message.length);
                }, 100);
            });

            $(document).off('click.templates', '#create-template-btn').on('click.templates', '#create-template-btn', function() {
                self.openTemplateEditor(null, returnState);
            });

            $(document).off('click.templates', '[data-action="edit"]').on('click.templates', '[data-action="edit"]', function() {
                var templateId = $(this).data('template-id');
                self.openTemplateEditor(templateId, returnState);
            });

            $(document).off('click.templates', '[data-action="delete"]').on('click.templates', '[data-action="delete"]', function() {
                if (confirm('Delete this template?')) {
                    var templateId = $(this).data('template-id');
                    self.deleteTemplate(templateId, returnState);
                }
            });

            $(document).off('click.templates', '[data-action="duplicate"]').on('click.templates', '[data-action="duplicate"]', function() {
                var templateId = $(this).data('template-id');
                self.duplicateTemplate(templateId, returnState);
            });
        },

        /**
         * Open template editor
         */
        openTemplateEditor: function(templateId, returnState) {
            var self = this;
            var isEdit = !!templateId;

            // If editing, load template first
            if (isEdit) {
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_get_template',
                        template_id: templateId,
                        nonce: this.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.renderTemplateEditor(response.data.template, returnState);
                        }
                    }
                });
            } else {
                this.renderTemplateEditor(null, returnState);
            }
        },

        /**
         * Render template editor
         */
        renderTemplateEditor: function(template, returnState) {
            var self = this;
            var isEdit = !!template;
            var html = '<div class="sffc-crm-template-editor">';

            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>' + (isEdit ? 'Edit Template' : 'Create Template') + '</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            html += '<form id="template-form">';

            // Name
            html += '<div class="sffc-crm-compose-section">';
            html += '<label>Template Name *</label>';
            html += '<input type="text" id="template-name" class="sffc-crm-input" required value="' + this.escapeHtml((template ? template.name : '') || '') + '">';
            html += '</div>';

            // Channel
            html += '<div class="sffc-crm-compose-section">';
            html += '<label>Channel</label>';
            html += '<select id="template-channel" class="sffc-crm-select">';
            html += '<option value="email"' + (template && template.channel === 'email' ? ' selected' : '') + '>Email</option>';
            html += '<option value="linkedin"' + (template && template.channel === 'linkedin' ? ' selected' : '') + '>LinkedIn</option>';
            html += '<option value="both"' + (template && template.channel === 'both' ? ' selected' : '') + '>Both</option>';
            html += '</select>';
            html += '</div>';

            // Type
            html += '<div class="sffc-crm-compose-section">';
            html += '<label>Type</label>';
            html += '<select id="template-type" class="sffc-crm-select">';
            html += '<option value="initial"' + (template && template.template_type === 'initial' ? ' selected' : '') + '>Initial Outreach</option>';
            html += '<option value="followup"' + (template && template.template_type === 'followup' ? ' selected' : '') + '>Follow Up</option>';
            html += '<option value="connection"' + (template && template.template_type === 'connection' ? ' selected' : '') + '>Connection Request</option>';
            html += '<option value="thank_you"' + (template && template.template_type === 'thank_you' ? ' selected' : '') + '>Thank You</option>';
            html += '<option value="custom"' + (template && template.template_type === 'custom' ? ' selected' : '') + '>Custom</option>';
            html += '</select>';
            html += '</div>';

            // Subject
            html += '<div class="sffc-crm-compose-section" id="template-subject-group">';
            html += '<label>Subject Line</label>';
            html += '<input type="text" id="template-subject" class="sffc-crm-input" value="' + this.escapeHtml((template ? template.subject : '') || '') + '">';
            html += '</div>';

            // Content
            html += '<div class="sffc-crm-compose-section">';
            html += '<label>Message Content *</label>';
            html += '<textarea id="template-content" class="sffc-crm-textarea" rows="10" required>' + this.escapeHtml((template ? template.content : '') || '') + '</textarea>';
            html += '<p class="sffc-crm-help-text">Use {variable_name} for dynamic content. E.g., {recruiter_first_name}, {role_title}</p>';
            html += '</div>';

            html += '</form>';

            // Actions
            html += '<div class="sffc-crm-template-footer">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary" id="template-cancel-btn">Cancel</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="template-save-btn" data-template-id="' + (template ? template.id : '') + '">' + (isEdit ? 'Save Changes' : 'Create Template') + '</button>';
            html += '</div>';

            html += '</div>';

            this.updateModalContent(html);

            // Bind events
            $(document).off('click.editor', '#template-cancel-btn').on('click.editor', '#template-cancel-btn', function() {
                self.openTemplateManager();
            });

            $(document).off('click.editor', '#template-save-btn').on('click.editor', '#template-save-btn', function() {
                self.saveTemplate($(this).data('template-id'), returnState);
            });
        },

        /**
         * Save template
         */
        saveTemplate: function(templateId, returnState) {
            var self = this;
            var isEdit = !!templateId;

            var name = $('#template-name').val().trim();
            var content = $('#template-content').val().trim();

            if (!name || !content) {
                this.showError('Please fill in required fields');
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: isEdit ? 'sffc_crm_update_template' : 'sffc_crm_create_template',
                    template_id: templateId,
                    name: name,
                    channel: $('#template-channel').val(),
                    template_type: $('#template-type').val(),
                    subject: $('#template-subject').val(),
                    content: content,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(isEdit ? 'Template updated' : 'Template created');
                        self.openTemplateManager();
                    } else {
                        self.handleError(response);
                    }
                }
            });
        },

        /**
         * Delete template
         */
        deleteTemplate: function(templateId, returnState) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_delete_template',
                    template_id: templateId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Template deleted');
                        self.openTemplateManager();
                    } else {
                        self.handleError(response);
                    }
                }
            });
        },

        /**
         * Duplicate template
         */
        duplicateTemplate: function(templateId, returnState) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_duplicate_template',
                    template_id: templateId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Template duplicated');
                        self.openTemplateManager();
                    } else {
                        self.handleError(response);
                    }
                }
            });
        },

        // ============================================
        // PHASE 3: Bulk Selection & Bulk Compose
        // ============================================

        /**
         * Initialize bulk selection events
         */
        initBulkSelectionEvents: function() {
            var self = this;

            // Toggle bulk select mode
            $(document).on('click', '#toggle-bulk-select', function() {
                self.toggleBulkSelectMode();
            });

            // Select all
            $(document).on('click', '#bulk-select-all', function() {
                self.selectAllRecruiters();
            });

            // Clear selection
            $(document).on('click', '#bulk-clear', function() {
                self.clearBulkSelection();
            });

            // Individual checkbox change
            $(document).on('change', '.sffc-crm-bulk-select-item', function() {
                var recruiterId = $(this).data('recruiter-id');
                var name = $(this).data('name');
                var email = $(this).data('email') || null;
                if ($(this).is(':checked')) {
                    self.addToBulkSelection(recruiterId, name, email);
                } else {
                    self.removeFromBulkSelection(recruiterId);
                }
            });

            // Add to Outreach List button
            $(document).on('click', '#bulk-add-to-list', function() {
                self.openAddToOutreachListModal();
            });

            // Bulk compose button
            $(document).on('click', '#bulk-compose', function() {
                self.openBulkComposeModal();
            });
        },

        /**
         * Toggle bulk select mode
         */
        toggleBulkSelectMode: function() {
            this.bulkSelection.mode = !this.bulkSelection.mode;
            this.bulkSelection.type = 'recruiters';

            if (this.bulkSelection.mode) {
                $('#toggle-bulk-select').addClass('active').text('Cancel');
                $('#bulk-toolbar').slideDown(200);
                $('.sffc-crm-bulk-checkbox').show();
            } else {
                $('#toggle-bulk-select').removeClass('active').html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg> Bulk Select');
                $('#bulk-toolbar').slideUp(200);
                $('.sffc-crm-bulk-checkbox').hide();
                this.clearBulkSelection();
            }
        },

        /**
         * Select all recruiters on current page
         */
        selectAllRecruiters: function() {
            var self = this;
            $('.sffc-crm-bulk-select-item').each(function() {
                if (!$(this).is(':checked')) {
                    $(this).prop('checked', true);
                    var recruiterId = $(this).data('recruiter-id');
                    var name = $(this).data('name');
                    var email = $(this).data('email') || null;
                    self.addToBulkSelection(recruiterId, name, email);
                }
            });
        },

        /**
         * Add item to bulk selection
         */
        addToBulkSelection: function(recruiterId, name, email) {
            // Avoid duplicates
            var exists = this.bulkSelection.selected.some(function(item) {
                return item.id === recruiterId;
            });
            if (!exists) {
                this.bulkSelection.selected.push({ id: recruiterId, name: name, email: email || null });
                this.updateBulkCount();
            }
        },

        /**
         * Remove item from bulk selection
         */
        removeFromBulkSelection: function(recruiterId) {
            this.bulkSelection.selected = this.bulkSelection.selected.filter(function(item) {
                return item.id !== recruiterId;
            });
            this.updateBulkCount();
        },

        /**
         * Clear bulk selection
         */
        clearBulkSelection: function() {
            this.bulkSelection.selected = [];
            $('.sffc-crm-bulk-select-item').prop('checked', false);
            this.updateBulkCount();
        },

        /**
         * Update bulk selection count display
         */
        updateBulkCount: function() {
            $('#bulk-count').text(this.bulkSelection.selected.length);

            if (this.bulkSelection.selected.length > 0) {
                $('#bulk-compose').prop('disabled', false);
                $('#bulk-add-to-list').prop('disabled', false);
            } else {
                $('#bulk-compose').prop('disabled', true);
                $('#bulk-add-to-list').prop('disabled', true);
            }
        },

        /**
         * Open bulk compose modal
         */
        openBulkComposeModal: function() {
            var self = this;

            if (this.bulkSelection.selected.length === 0) {
                this.showError('Select at least one recruiter');
                return;
            }

            // Show loading modal
            this.showModal('<div class="sffc-crm-modal-loading">Loading templates...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_templates',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderBulkComposeModal(response.data.templates);
                    } else {
                        self.closeModal();
                        self.handleError(response);
                    }
                }
            });
        },

        /**
         * Render bulk compose modal
         */
        renderBulkComposeModal: function(templates) {
            var self = this;
            var selectedCount = this.bulkSelection.selected.length;

            var html = '<div class="sffc-crm-bulk-compose-modal">';

            // Header
            html += '<div class="sffc-crm-compose-header">';
            html += '<h3>Bulk Compose</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            // Recipients summary
            html += '<div class="sffc-crm-bulk-recipients">';
            html += '<div class="sffc-crm-bulk-recipients-header">';
            html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>';
            html += '<span>' + selectedCount + ' recipients selected</span>';
            html += '</div>';
            html += '<div class="sffc-crm-bulk-recipients-list">';
            this.bulkSelection.selected.slice(0, 5).forEach(function(item) {
                html += '<span class="sffc-crm-bulk-recipient-chip">' + this.escapeHtml(item.name) + '</span>';
            }, this);
            if (selectedCount > 5) {
                html += '<span class="sffc-crm-bulk-recipient-more">+' + (selectedCount - 5) + ' more</span>';
            }
            html += '</div>';
            html += '</div>';

            // Channel selector
            html += '<div class="sffc-crm-compose-section">';
            html += '<label>Channel</label>';
            html += '<div class="sffc-crm-channel-toggle">';
            html += '<button class="sffc-crm-channel-btn active" data-channel="email">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
            html += 'Email';
            html += '</button>';
            html += '<button class="sffc-crm-channel-btn" data-channel="linkedin">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>';
            html += 'LinkedIn';
            html += '</button>';
            html += '</div>';
            html += '</div>';

            // Template selector
            html += '<div class="sffc-crm-compose-section">';
            html += '<label>Template (Required for bulk)</label>';
            html += '<select id="bulk-compose-template" class="sffc-crm-select">';
            html += '<option value="">Select a template...</option>';
            if (templates && templates.length > 0) {
                templates.forEach(function(template) {
                    html += '<option value="' + template.id + '" data-channel="' + template.channel + '">' + this.escapeHtml(template.name) + '</option>';
                }, this);
            }
            html += '</select>';
            html += '<p class="sffc-crm-help-text">Variables will be personalized for each recipient.</p>';
            html += '</div>';

            // Subject (for email)
            html += '<div class="sffc-crm-compose-section" id="bulk-subject-group">';
            html += '<label>Subject</label>';
            html += '<input type="text" id="bulk-compose-subject" class="sffc-crm-input" placeholder="Subject line...">';
            html += '</div>';

            // Preview of personalized message
            html += '<div class="sffc-crm-compose-section">';
            html += '<label>Message Preview</label>';
            html += '<div class="sffc-crm-bulk-preview" id="bulk-message-preview">';
            html += '<p class="sffc-crm-help-text">Select a template to see the preview.</p>';
            html += '</div>';
            html += '</div>';

            // Actions
            html += '<div class="sffc-crm-compose-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-modal-close">Cancel</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="bulk-send-btn" disabled>';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
            html += '<span class="sffc-crm-bulk-send-email">Send to ' + selectedCount + ' Recipients</span>';
            html += '<span class="sffc-crm-bulk-send-linkedin" style="display: none;">Generate ' + selectedCount + ' Messages</span>';
            html += '</button>';
            html += '</div>';

            html += '</div>';

            this.updateModalContent(html);
            this.bindBulkComposeEvents();
        },

        /**
         * Bind bulk compose modal events
         */
        bindBulkComposeEvents: function() {
            var self = this;
            var selectedCount = this.bulkSelection.selected.length;

            // Channel switch
            $(document).off('click.bulkcompose', '.sffc-crm-bulk-compose-modal .sffc-crm-channel-btn').on('click.bulkcompose', '.sffc-crm-bulk-compose-modal .sffc-crm-channel-btn', function() {
                var channel = $(this).data('channel');
                self.outreachState.channel = channel;

                $('.sffc-crm-bulk-compose-modal .sffc-crm-channel-btn').removeClass('active');
                $(this).addClass('active');

                if (channel === 'linkedin') {
                    $('#bulk-subject-group').hide();
                    $('.sffc-crm-bulk-send-email').hide();
                    $('.sffc-crm-bulk-send-linkedin').show();
                } else {
                    $('#bulk-subject-group').show();
                    $('.sffc-crm-bulk-send-email').show();
                    $('.sffc-crm-bulk-send-linkedin').hide();
                }

                // Filter templates by channel
                self.filterTemplatesByChannel(channel);
            });

            // Template selection - load preview
            $(document).off('change.bulkcompose', '#bulk-compose-template').on('change.bulkcompose', '#bulk-compose-template', function() {
                var templateId = $(this).val();
                if (templateId) {
                    self.loadBulkTemplatePreview(templateId);
                    $('#bulk-send-btn').prop('disabled', false);
                } else {
                    $('#bulk-message-preview').html('<p class="sffc-crm-help-text">Select a template to see the preview.</p>');
                    $('#bulk-send-btn').prop('disabled', true);
                }
            });

            // Send button
            $(document).off('click.bulkcompose', '#bulk-send-btn').on('click.bulkcompose', '#bulk-send-btn', function() {
                self.sendBulkOutreach();
            });
        },

        /**
         * Load bulk template preview
         */
        loadBulkTemplatePreview: function(templateId) {
            var self = this;
            var firstRecruiter = this.bulkSelection.selected[0];

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_render_template',
                    template_id: templateId,
                    recruiter_id: firstRecruiter.id,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="sffc-crm-bulk-preview-content">';
                        html += '<p class="sffc-crm-bulk-preview-note">Preview for: <strong>' + self.escapeHtml(firstRecruiter.name) + '</strong></p>';
                        if (response.data.subject) {
                            html += '<div class="sffc-crm-bulk-preview-subject"><strong>Subject:</strong> ' + self.escapeHtml(response.data.subject) + '</div>';
                            $('#bulk-compose-subject').val(response.data.subject);
                        }
                        html += '<div class="sffc-crm-bulk-preview-body">' + self.escapeHtml(response.data.content).replace(/\n/g, '<br>') + '</div>';
                        html += '</div>';
                        $('#bulk-message-preview').html(html);
                    }
                }
            });
        },

        /**
         * Send bulk outreach
         */
        sendBulkOutreach: function() {
            var self = this;
            var templateId = $('#bulk-compose-template').val();
            var subject = $('#bulk-compose-subject').val();
            var channel = this.outreachState.channel;

            if (!templateId) {
                this.showError('Please select a template');
                return;
            }

            var recruiterIds = this.bulkSelection.selected.map(function(item) {
                return item.id;
            });

            // Get recruiter emails for bulk email flow
            var recruiterEmails = {};
            this.bulkSelection.selected.forEach(function(item) {
                if (item.email) {
                    recruiterEmails[item.id] = item.email;
                }
            });

            var $btn = $('#bulk-send-btn');
            var originalText = $btn.html();
            $btn.html('<svg class="sffc-crm-spinner" width="16" height="16" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="32" stroke-dashoffset="32"><animate attributeName="stroke-dashoffset" dur="1s" values="32;0" repeatCount="indefinite"/></circle></svg> Processing...');
            $btn.prop('disabled', true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_bulk_create_outreach',
                    recruiter_ids: recruiterIds,
                    template_id: templateId,
                    subject: subject,
                    channel: channel,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        // Add emails to the result messages
                        if (result.messages) {
                            result.messages.forEach(function(msg) {
                                msg.email = recruiterEmails[msg.recruiter_id] || null;
                            });
                        }
                        if (channel === 'linkedin') {
                            self.showBulkLinkedInResults(result);
                        } else {
                            self.showBulkEmailResults(result);
                        }
                    } else {
                        self.handleError(response);
                    }
                },
                error: function() {
                    self.showError('Failed to process bulk outreach');
                },
                complete: function() {
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Open Add to Outreach List modal
         */
        openAddToOutreachListModal: function() {
            var self = this;

            if (this.bulkSelection.selected.length === 0) {
                this.showError('Please select at least one recruiter');
                return;
            }

            // Load user's outreach lists
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_outreach_lists',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderRecruiterAddToOutreachListModal(response.data || []);
                    } else {
                        self.renderRecruiterAddToOutreachListModal([]);
                    }
                },
                error: function() {
                    self.renderRecruiterAddToOutreachListModal([]);
                }
            });
        },

        /**
         * Show add single recruiter to list modal (for post rows)
         */
        showAddSingleRecruiterToListModal: function(recruiterIds) {
            var self = this;

            // Temporarily store the recruiter IDs
            this.tempRecruiterIds = recruiterIds;

            // Load lists and show modal
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_outreach_lists',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderSingleRecruiterToListModal(response.data || [], recruiterIds);
                    } else {
                        self.renderSingleRecruiterToListModal([], recruiterIds);
                    }
                },
                error: function() {
                    self.renderSingleRecruiterToListModal([], recruiterIds);
                }
            });
        },

        /**
         * Render single recruiter add to list modal
         */
        renderSingleRecruiterToListModal: function(lists, recruiterIds) {
            var self = this;
            var selectedCount = recruiterIds.length;

            var html = '<div class="sffc-crm-outreach-list-modal">';

            // Header
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Add Recruiter to Outreach List</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            html += '<div class="sffc-crm-modal-body">';

            // Option 1: Create new list
            html += '<div class="sffc-crm-list-option">';
            html += '<label class="sffc-crm-radio-wrapper">';
            html += '<input type="radio" name="list-option" value="new" checked>';
            html += '<span>Create new list</span>';
            html += '</label>';
            html += '<div class="sffc-crm-new-list-form" id="new-list-form">';
            html += '<input type="text" id="new-list-name" class="sffc-crm-input" placeholder="List name (e.g., Finance Director Targets)" maxlength="255">';
            html += '<textarea id="new-list-description" class="sffc-crm-textarea" placeholder="Description (optional)" rows="2"></textarea>';
            html += '</div>';
            html += '</div>';

            // Option 2: Add to existing list
            html += '<div class="sffc-crm-list-option">';
            html += '<label class="sffc-crm-radio-wrapper">';
            html += '<input type="radio" name="list-option" value="existing"' + (lists.length === 0 ? ' disabled' : '') + '>';
            html += '<span>Add to existing list</span>';
            html += '</label>';
            html += '<div class="sffc-crm-existing-list-form" id="existing-list-form" style="display: none;">';
            if (lists.length > 0) {
                html += '<select id="existing-list-select" class="sffc-crm-select">';
                html += '<option value="">Select a list...</option>';
                lists.forEach(function(list) {
                    html += '<option value="' + list.id + '">';
                    html += self.escapeHtml(list.list_name) + ' (' + (list.recruiter_count || 0) + ' recruiters)';
                    html += '</option>';
                });
                html += '</select>';
            } else {
                html += '<p class="sffc-crm-empty-note">No existing lists. Create your first list above.</p>';
            }
            html += '</div>';
            html += '</div>';

            html += '</div>';

            // Footer
            html += '<div class="sffc-crm-modal-footer">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text" id="cancel-add-list">Cancel</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="confirm-add-single-recruiter">Add to List</button>';
            html += '</div>';

            html += '</div>';

            this.showModal(html);
            this.bindSingleRecruiterToListEvents(recruiterIds);
        },

        /**
         * Bind events for single recruiter modal
         */
        bindSingleRecruiterToListEvents: function(recruiterIds) {
            var self = this;

            // Radio button toggle
            $(document).off('change.addtolist', 'input[name="list-option"]').on('change.addtolist', 'input[name="list-option"]', function() {
                if ($(this).val() === 'new') {
                    $('#new-list-form').show();
                    $('#existing-list-form').hide();
                } else {
                    $('#new-list-form').hide();
                    $('#existing-list-form').show();
                }
            });

            // Cancel button
            $(document).off('click.addtolist', '#cancel-add-list').on('click.addtolist', '#cancel-add-list', function() {
                self.closeModal();
            });

            // Confirm button
            $(document).off('click.addtolist', '#confirm-add-single-recruiter').on('click.addtolist', '#confirm-add-single-recruiter', function() {
                self.confirmAddSingleRecruiterToList(recruiterIds);
            });
        },

        /**
         * Confirm add single recruiter to list
         */
        confirmAddSingleRecruiterToList: function(recruiterIds) {
            var self = this;
            var option = $('input[name="list-option"]:checked').val();

            if (option === 'new') {
                var listName = $('#new-list-name').val().trim();
                var description = $('#new-list-description').val().trim();

                if (!listName) {
                    alert('Please enter a list name');
                    return;
                }

                // Create new list first
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_create_outreach_list',
                        nonce: this.config.nonce,
                        list_name: listName,
                        description: description
                    },
                    success: function(response) {
                        if (response.success) {
                            self.addRecruitersToList(response.data.list_id, recruiterIds);
                        } else {
                            self.handleError(response);
                        }
                    },
                    error: function() {
                        self.showError('Failed to create list');
                    }
                });
            } else {
                var listId = parseInt($('#existing-list-select').val());

                if (!listId) {
                    alert('Please select a list');
                    return;
                }

                this.addRecruitersToList(listId, recruiterIds);
            }
        },

        /**
         * Render Add to Outreach List modal
         */
        renderRecruiterAddToOutreachListModal: function(lists) {
            var self = this;
            var selectedCount = this.bulkSelection.selected.length;

            var html = '<div class="sffc-crm-outreach-list-modal">';

            // Header
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Add ' + selectedCount + ' Recruiter' + (selectedCount > 1 ? 's' : '') + ' to Outreach List</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            html += '<div class="sffc-crm-modal-body">';

            // Option 1: Create new list
            html += '<div class="sffc-crm-list-option">';
            html += '<label class="sffc-crm-radio-wrapper">';
            html += '<input type="radio" name="list-option" value="new" checked>';
            html += '<span>Create new list</span>';
            html += '</label>';
            html += '<div class="sffc-crm-new-list-form" id="new-list-form">';
            html += '<input type="text" id="new-list-name" class="sffc-crm-input" placeholder="List name (e.g., Private Credit Targets)" maxlength="255">';
            html += '<textarea id="new-list-description" class="sffc-crm-textarea" placeholder="Description (optional)" rows="2"></textarea>';
            html += '</div>';
            html += '</div>';

            // Option 2: Add to existing list
            html += '<div class="sffc-crm-list-option">';
            html += '<label class="sffc-crm-radio-wrapper">';
            html += '<input type="radio" name="list-option" value="existing"' + (lists.length === 0 ? ' disabled' : '') + '>';
            html += '<span>Add to existing list</span>';
            html += '</label>';
            html += '<div class="sffc-crm-existing-list-form" id="existing-list-form" style="display: none;">';
            if (lists.length > 0) {
                html += '<select id="existing-list-select" class="sffc-crm-select">';
                html += '<option value="">Select a list...</option>';
                lists.forEach(function(list) {
                    html += '<option value="' + list.id + '">';
                    html += self.escapeHtml(list.list_name) + ' (' + (list.recruiter_count || 0) + ' recruiters)';
                    html += '</option>';
                });
                html += '</select>';
            } else {
                html += '<p class="sffc-crm-empty-note">No existing lists. Create your first list above.</p>';
            }
            html += '</div>';
            html += '</div>';

            html += '</div>';

            // Footer
            html += '<div class="sffc-crm-modal-footer">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text" id="cancel-add-list">Cancel</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="confirm-add-list">Add to List</button>';
            html += '</div>';

            html += '</div>';

            this.showModal(html);
            this.bindAddToListEvents();
        },

        /**
         * Bind events for Add to Outreach List modal
         */
        bindAddToListEvents: function() {
            var self = this;

            // Radio button toggle
            $(document).off('change.addtolist', 'input[name="list-option"]').on('change.addtolist', 'input[name="list-option"]', function() {
                if ($(this).val() === 'new') {
                    $('#new-list-form').show();
                    $('#existing-list-form').hide();
                } else {
                    $('#new-list-form').hide();
                    $('#existing-list-form').show();
                }
            });

            // Cancel button
            $(document).off('click.addtolist', '#cancel-add-list').on('click.addtolist', '#cancel-add-list', function() {
                self.closeModal();
            });

            // Confirm button
            $(document).off('click.addtolist', '#confirm-add-list').on('click.addtolist', '#confirm-add-list', function() {
                self.confirmAddToList();
            });
        },

        /**
         * Confirm add to list action
         */
        confirmAddToList: function() {
            var self = this;
            var option = $('input[name="list-option"]:checked').val();
            var recruiterIds = this.bulkSelection.selected.map(function(r) { return r.id; });

            if (option === 'new') {
                // Create new list
                var listName = $('#new-list-name').val().trim();
                var description = $('#new-list-description').val().trim();

                if (!listName) {
                    this.showError('Please enter a list name');
                    return;
                }

                // Create list and add recruiters
                this.createOutreachList(listName, description, recruiterIds);

            } else {
                // Add to existing list
                var listId = $('#existing-list-select').val();

                if (!listId) {
                    this.showError('Please select a list');
                    return;
                }

                this.addRecruitersToList(listId, recruiterIds);
            }
        },

        /**
         * Create new outreach list with recruiters
         */
        createOutreachList: function(listName, description, recruiterIds) {
            var self = this;
            var $btn = $('#confirm-add-list');
            var originalText = $btn.html();
            $btn.html('<span class="sffc-crm-spinner"></span> Creating...').prop('disabled', true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_create_outreach_list',
                    list_name: listName,
                    description: description,
                    recruiter_ids: recruiterIds,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(recruiterIds.length + ' recruiter' + (recruiterIds.length > 1 ? 's' : '') + ' added to "' + listName + '"');
                        self.closeModal();
                        self.clearBulkSelection();

                        // Refresh lists if on Smart message tab
                        if (self.currentTab === 'outreach-lists') {
                            self.loadOutreachLists();
                        }
                    } else {
                        self.handleError(response);
                    }
                },
                error: function() {
                    self.showError('Failed to create list');
                },
                complete: function() {
                    $btn.html(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Add recruiters to existing list
         */
        addRecruitersToList: function(listId, recruiterIds) {
            var self = this;
            var $btn = $('#confirm-add-list');
            var originalText = $btn.html();
            $btn.html('<span class="sffc-crm-spinner"></span> Adding...').prop('disabled', true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_add_to_outreach_list',
                    list_id: listId,
                    recruiter_ids: recruiterIds,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var listName = $('#existing-list-select option:selected').text().split(' (')[0];
                        self.showSuccess(recruiterIds.length + ' recruiter' + (recruiterIds.length > 1 ? 's' : '') + ' added to list');
                        self.closeModal();
                        self.clearBulkSelection();

                        // Refresh lists if on Smart message tab
                        if (self.currentTab === 'outreach-lists') {
                            self.loadOutreachLists();
                        }
                    } else {
                        self.handleError(response);
                    }
                },
                error: function() {
                    self.showError('Failed to add to list');
                },
                complete: function() {
                    $btn.html(originalText).prop('disabled', false);
                }
            });
        },

        fetchOutreachLists: function(forceRefresh, callback) {
            callback = callback || function() {};
            if (!forceRefresh && Array.isArray(this.outreachListCache)) {
                callback(this.outreachListCache);
                return;
            }

            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_outreach_lists',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.outreachListCache = response.data || [];
                        callback(self.outreachListCache);
                    } else {
                        self.outreachListCache = [];
                        callback([]);
                    }
                },
                error: function() {
                    callback([]);
                }
            });
        },

        toggleInlineAddListDropdown: function($wrapper, recruiterId) {
            if (!$wrapper.length) return;

            var $dropdown = $wrapper.find('.sffc-crm-add-list-dropdown');
            if (!$dropdown.length) return;

            if (this.inlineListDropdown && this.inlineListDropdown[0] !== $dropdown[0]) {
                this.closeInlineAddListDropdown();
            }

            if ($dropdown.hasClass('is-open')) {
                this.closeInlineAddListDropdown();
                return;
            }

            $dropdown
                .attr('aria-hidden', 'false')
                .prop('inert', false)
                .addClass('is-open')
                .data('recruiter-id', recruiterId)
                .html('<div class="sffc-crm-inline-loading">Loading lists...</div>');

            this.inlineListDropdown = $dropdown;

            var self = this;
            this.fetchOutreachLists(false, function(lists) {
                self.renderInlineAddListDropdown($dropdown, lists);
            });
        },

        closeInlineAddListDropdown: function() {
            if (this.inlineListDropdown) {
                // Blur any focused element inside the dropdown before hiding
                var activeElement = document.activeElement;
                if (activeElement && this.inlineListDropdown[0].contains(activeElement)) {
                    activeElement.blur();
                }

                this.inlineListDropdown.removeClass('is-open').attr('aria-hidden', 'true').prop('inert', true).empty();
                this.inlineListDropdown = null;
            }
        },

        renderInlineAddListDropdown: function($dropdown, lists) {
            var html = '';

            html += '<div class="sffc-crm-inline-group">';
            if (lists.length) {
                html += '<label class="sffc-crm-inline-label">Saved lists</label>';
                html += '<select class="sffc-crm-select sffc-crm-inline-select">';
                html += '<option value="">Select a list...</option>';
                lists.forEach(function(list) {
                    html += '<option value="' + list.id + '">' + (list.list_name || 'Untitled List');
                    if (list.recruiter_count !== undefined) {
                        html += ' (' + list.recruiter_count + ')';
                    }
                    html += '</option>';
                });
                html += '</select>';
                html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-btn-small sffc-crm-inline-add-existing">Add</button>';
            } else {
                html += '<p class="sffc-crm-empty-note">No lists yet. Create one below.</p>';
            }
            html += '</div>';

            html += '<div class="sffc-crm-inline-divider"><span>or</span></div>';

            html += '<div class="sffc-crm-inline-group">';
            html += '<label class="sffc-crm-inline-label">Create new list</label>';
            html += '<input type="text" class="sffc-crm-input sffc-crm-inline-new-name" placeholder="List name" maxlength="255">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-small sffc-crm-inline-create">Create & Add</button>';
            html += '</div>';

            html += '<div class="sffc-crm-inline-status" role="status"></div>';

            $dropdown.html(html);
        },

        addRecruitersToListInline: function(listId, recruiterIds, $dropdown, $button) {
            if (!listId) return;
            var self = this;
            var $status = $dropdown.find('.sffc-crm-inline-status');

            if ($button && $button.length) {
                $button.data('original-text', $button.text());
                $button.prop('disabled', true).text('Adding...');
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_add_to_outreach_list',
                    list_id: listId,
                    recruiter_ids: recruiterIds,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.outreachListCache = null;
                        self.showInlineStatus($status, 'Recruiter added to list.', 'success');
                        self.showSuccess('Recruiter added to list');
                        self.closeInlineAddListDropdown();

                        if (self.currentTab === 'outreach-lists') {
                            self.loadOutreachLists();
                        }
                    } else {
                        self.showInlineStatus($status, response.data && response.data.message ? response.data.message : 'Failed to add to list', 'error');
                    }
                },
                error: function() {
                    self.showInlineStatus($status, 'Failed to add to list', 'error');
                },
                complete: function() {
                    if ($button && $button.length) {
                        $button.prop('disabled', false).text($button.data('original-text') || 'Add');
                    }
                }
            });
        },

        createInlineListAndAdd: function(listName, recruiterId, $dropdown, $button) {
            var self = this;
            var $status = $dropdown.find('.sffc-crm-inline-status');

            if ($button && $button.length) {
                $button.data('original-text', $button.text());
                $button.prop('disabled', true).text('Creating...');
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_create_outreach_list',
                    nonce: this.config.nonce,
                    list_name: listName,
                    description: ''
                },
                success: function(response) {
                    if (response.success && response.data && response.data.list_id) {
                        self.outreachListCache = null;
                        self.showInlineStatus($status, 'List created. Adding recruiter...', 'success');
                        self.addRecruitersToListInline(response.data.list_id, [recruiterId], $dropdown);
                    } else {
                        self.showInlineStatus($status, response.data && response.data.message ? response.data.message : 'Failed to create list', 'error');
                    }
                },
                error: function() {
                    self.showInlineStatus($status, 'Failed to create list', 'error');
                },
                complete: function() {
                    if ($button && $button.length) {
                        $button.prop('disabled', false).text($button.data('original-text') || 'Create & Add');
                    }
                }
            });
        },

        showInlineStatus: function($statusEl, message, type) {
            if (!$statusEl || !$statusEl.length) return;
            $statusEl.removeClass('is-error is-success');
            if (type === 'success') {
                $statusEl.addClass('is-success');
            } else if (type === 'error') {
                $statusEl.addClass('is-error');
            }
            $statusEl.text(message);
        },

        /**
         * Show bulk email results with mailto buttons
         */
        showBulkEmailResults: function(result) {
            var self = this;

            var html = '<div class="sffc-crm-bulk-results-modal">';
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Email Messages Ready</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            html += '<p class="sffc-crm-bulk-results-intro">' + result.created + ' personalized messages generated. Click to open each in your email client.</p>';

            html += '<div class="sffc-crm-bulk-results-list">';
            if (result.messages) {
                result.messages.forEach(function(msg, index) {
                    var hasEmail = msg.email ? true : false;
                    html += '<div class="sffc-crm-bulk-result-item">';
                    html += '<div class="sffc-crm-bulk-result-header">';
                    html += '<div class="sffc-crm-bulk-result-info">';
                    html += '<strong>' + (index + 1) + '. ' + self.escapeHtml(msg.recruiter_name) + '</strong>';
                    if (hasEmail) {
                        html += '<small class="sffc-crm-email-badge">' + self.escapeHtml(msg.email) + '</small>';
                    } else {
                        html += '<small class="sffc-crm-no-email-badge">No email on file</small>';
                    }
                    html += '</div>';
                    if (hasEmail) {
                        html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-primary sffc-crm-open-email" ';
                        html += 'data-email="' + self.escapeHtml(msg.email) + '" ';
                        html += 'data-subject="' + self.escapeHtml(msg.subject || '') + '" ';
                        html += 'data-content="' + self.escapeHtml(msg.content) + '">';
                        html += 'Open in Email</button>';
                    } else {
                        html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-secondary sffc-crm-copy-message" ';
                        html += 'data-content="Subject: ' + self.escapeHtml(msg.subject || '') + '\n\n' + self.escapeHtml(msg.content) + '">';
                        html += 'Copy</button>';
                    }
                    html += '</div>';
                    html += '<div class="sffc-crm-bulk-result-preview">' + self.escapeHtml(msg.content.substring(0, 100)) + '...</div>';
                    html += '</div>';
                });
            }
            html += '</div>';

            html += '<div class="sffc-crm-compose-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-copy-all-emails">Copy All</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-modal-close">Done</button>';
            html += '</div>';

            html += '</div>';

            this.updateModalContent(html);

            // Bind open email events
            $(document).off('click.bulkemail', '.sffc-crm-open-email').on('click.bulkemail', '.sffc-crm-open-email', function() {
                var email = $(this).data('email');
                var subject = $(this).data('subject');
                var content = $(this).data('content');
                self.openMailto(email, subject, content);
                $(this).text('Opened!').addClass('opened');
            });

            // Bind copy events
            $(document).off('click.bulkcopy', '.sffc-crm-copy-message').on('click.bulkcopy', '.sffc-crm-copy-message', function() {
                var content = $(this).data('content');
                navigator.clipboard.writeText(content).then(function() {
                    self.showSuccess('Message copied!');
                });
                $(this).text('Copied!').addClass('copied');
            });

            // Bind copy all
            $(document).off('click.copyall', '.sffc-crm-copy-all-emails').on('click.copyall', '.sffc-crm-copy-all-emails', function() {
                var allMessages = [];
                if (result.messages) {
                    result.messages.forEach(function(msg) {
                        allMessages.push('To: ' + (msg.email || 'No email') + '\nSubject: ' + (msg.subject || '') + '\n\n' + msg.content);
                    });
                }
                navigator.clipboard.writeText(allMessages.join('\n\n---\n\n')).then(function() {
                    self.showSuccess('All messages copied!');
                });
            });

            self.toggleBulkSelectMode();
        },

        /**
         * Show bulk LinkedIn results for copy
         */
        showBulkLinkedInResults: function(result) {
            var self = this;

            var html = '<div class="sffc-crm-bulk-results-modal">';
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>LinkedIn Messages Ready</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            html += '<p class="sffc-crm-bulk-results-intro">' + result.created + ' personalized messages generated. Click to copy each message.</p>';

            html += '<div class="sffc-crm-bulk-results-list">';
            if (result.messages) {
                result.messages.forEach(function(msg, index) {
                    html += '<div class="sffc-crm-bulk-result-item">';
                    html += '<div class="sffc-crm-bulk-result-header">';
                    html += '<strong>' + (index + 1) + '. ' + this.escapeHtml(msg.recruiter_name) + '</strong>';
                    html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-secondary sffc-crm-copy-message" data-content="' + this.escapeHtml(msg.content) + '">Copy</button>';
                    html += '</div>';
                    html += '<div class="sffc-crm-bulk-result-preview">' + this.escapeHtml(msg.content.substring(0, 100)) + '...</div>';
                    html += '</div>';
                }, this);
            }
            html += '</div>';

            html += '<div class="sffc-crm-compose-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-modal-close">Done</button>';
            html += '</div>';

            html += '</div>';

            this.updateModalContent(html);

            // Bind copy events
            $(document).off('click.bulkcopy', '.sffc-crm-copy-message').on('click.bulkcopy', '.sffc-crm-copy-message', function() {
                var content = $(this).data('content');
                navigator.clipboard.writeText(content).then(function() {
                    self.showSuccess('Message copied!');
                });
                $(this).text('Copied!').addClass('copied');
            });
        },

        // ============================================
        // PHASE 4: EXPERT OUTREACH
        // ============================================

        expertOutreachState: {
            requests: [],
            stats: {},
            autoSettings: {},
            currentRecruiter: null
        },

        // Legacy sequences state (keeping for compatibility)
        sequencesState: {
            sequences: [],
            currentSequence: null,
            isEditing: false
        },

        tasksState: {
            tasks: [],
            counts: {},
            filter: 'pending',
            sort: 'due_date'
        },

        // Phase 5: Inbox state
        inboxState: {
            conversations: [],
            counts: {},
            filter: 'active',
            currentConversation: null,
            currentMessages: [],
            viewingThread: false
        },

        /**
         * Load expert outreach tab
         */
        /**
         * Load Smart message tab (formerly outreach lists)
         */
        loadOutreachLists: function() {
            var self = this;
            var $panel = $('#panel-outreach-lists');

            if (!this.config.isLoggedIn) {
                $panel.html('<div class="sffc-crm-empty-state"><p>Please <a href="' + this.config.loginUrl + '">sign in</a> to view your outreach lists.</p></div>');
                return;
            }

            // Show loading
            $panel.html('<div class="sffc-crm-loading">Loading outreach lists...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_job_outreach_lists',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderOutreachListsView(response.data || []);

                        // Update outreach lists badge count
                        var count = response.data ? response.data.length : 0;
                        self.updateTabBadge('outreach-lists', count);
                    } else {
                        $panel.html('<div class="sffc-crm-empty-state">Failed to load outreach lists</div>');
                    }
                },
                error: function() {
                    $panel.html('<div class="sffc-crm-empty-state">Error loading outreach lists</div>');
                }
            });
        },

        /**
         * Render job outreach lists view
         */
        renderOutreachListsView: function(lists) {
            var self = this;
            var $panel = $('#panel-outreach-lists');
            var html = '';

            // Header
            html += '<div class="sffc-crm-outreach-lists-header">';
            html += '<h2>Outreach Lists</h2>';
            html += '<p class="sffc-crm-outreach-subtitle">Organize roles into campaigns and track your outreach progress</p>';
            html += '</div>';

            // Lists grid
            html += '<div class="sffc-crm-outreach-lists-grid">';

            if (lists.length === 0) {
                html += '<div class="sffc-crm-empty-state">';
                html += '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>';
                html += '<h3>No outreach lists yet</h3>';
                html += '<p>Select roles from All Roles or Matches and click "Add to Outreach List" to create your first campaign.</p>';
                html += '</div>';
            } else {
                lists.forEach(function(list) {
                    html += self.renderJobOutreachListCard(list);
                });
            }

            html += '</div>';

            $panel.html(html);
            this.bindJobOutreachListEvents();
        },

        /**
         * Render individual Smart message brief card
         */
        renderOutreachListCard: function(list) {
            var html = '<div class="sffc-crm-outreach-list-card" data-list-id="' + list.id + '">';

            // List icon & info
            html += '<div class="sffc-crm-list-card-header">';
            html += '<div class="sffc-crm-list-icon">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path></svg>';
            html += '</div>';
            html += '<div class="sffc-crm-list-info">';
            html += '<h3>' + this.escapeHtml(list.list_name) + '</h3>';
            if (list.description) {
                html += '<p>' + this.escapeHtml(list.description) + '</p>';
            }
            html += '</div>';
            html += '</div>';

            // Stats
            html += '<div class="sffc-crm-list-stats">';
            html += '<div class="sffc-crm-list-stat">';
            html += '<span class="sffc-crm-stat-value">' + (list.recruiter_count || 0) + '</span>';
            html += '<span class="sffc-crm-stat-label">Recruiters</span>';
            html += '</div>';
            html += '<div class="sffc-crm-list-stat">';
            var createdDate = list.created_at ? this.timeAgo(list.created_at) : 'Unknown';
            html += '<span class="sffc-crm-stat-value">' + createdDate + '</span>';
            html += '<span class="sffc-crm-stat-label">Created</span>';
            html += '</div>';
            html += '</div>';

            // Actions
            html += '<div class="sffc-crm-list-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-small view-list-btn" data-list-id="' + list.id + '">';
            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            html += 'View List';
            html += '</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-btn-small reach-out-all-btn" data-list-id="' + list.id + '" ' + (list.recruiter_count === 0 ? 'disabled' : '') + '>';
            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
            html += 'Send CV to All';
            html += '</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small delete-list-btn" data-list-id="' + list.id + '">';
            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
            html += '</button>';
            html += '</div>';

            html += '</div>';

            return html;
        },

        /**
         * Render individual job outreach list card
         */
        renderJobOutreachListCard: function(list) {
            var html = '<div class="sffc-crm-outreach-list-card" data-list-id="' + list.id + '">';

            // List icon & info
            html += '<div class="sffc-crm-list-card-header">';
            html += '<div class="sffc-crm-list-icon">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>';
            html += '</div>';
            html += '<div class="sffc-crm-list-info">';
            html += '<h3>' + this.escapeHtml(list.list_name) + '</h3>';
            if (list.description) {
                html += '<p>' + this.escapeHtml(list.description) + '</p>';
            }
            html += '</div>';
            html += '</div>';

            // Stats
            html += '<div class="sffc-crm-list-stats">';
            html += '<div class="sffc-crm-list-stat">';
            html += '<span class="sffc-crm-stat-value">' + (list.job_count || 0) + '</span>';
            html += '<span class="sffc-crm-stat-label">Roles</span>';
            html += '</div>';
            html += '<div class="sffc-crm-list-stat">';
            var createdDate = list.created_at ? this.timeAgo(list.created_at) : 'Unknown';
            html += '<span class="sffc-crm-stat-value">' + createdDate + '</span>';
            html += '<span class="sffc-crm-stat-label">Created</span>';
            html += '</div>';
            html += '</div>';

            // Actions
            html += '<div class="sffc-crm-list-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-small view-job-list-btn" data-list-id="' + list.id + '">';
            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            html += 'View Roles';
            html += '</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small delete-job-list-btn" data-list-id="' + list.id + '">';
            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
            html += '</button>';
            html += '</div>';

            html += '</div>';

            return html;
        },

        /**
         * Bind job outreach list events
         */
        bindJobOutreachListEvents: function() {
            var self = this;

            // View job list
            $(document).off('click.viewjoblist', '.view-job-list-btn').on('click.viewjoblist', '.view-job-list-btn', function() {
                var listId = $(this).data('list-id');
                self.viewJobOutreachList(listId);
            });

            // Delete job list
            $(document).off('click.deletejoblist', '.delete-job-list-btn').on('click.deletejoblist', '.delete-job-list-btn', function() {
                var listId = $(this).data('list-id');
                if (confirm('Are you sure you want to delete this outreach list? This cannot be undone.')) {
                    self.deleteJobOutreachList(listId);
                }
            });
        },

        /**
         * View job outreach list details
         */
        viewJobOutreachList: function(listId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_job_outreach_list_details',
                    list_id: listId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderJobListDetailView(response.data);
                    } else {
                        self.showError('Failed to load list details');
                    }
                },
                error: function() {
                    self.showError('Error loading list details');
                }
            });
        },

        /**
         * Render job list detail view
         */
        renderJobListDetailView: function(data) {
            var self = this;
            var list = data.list;
            var jobs = data.jobs || [];
            var $panel = $('#panel-outreach-lists');
            var html = '';

            // Back button & header
            html += '<div class="sffc-crm-list-detail-header">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text" id="back-to-job-lists">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>';
            html += 'Back to Lists';
            html += '</button>';
            html += '<div class="sffc-crm-list-title">';
            html += '<h2>' + this.escapeHtml(list.list_name) + '</h2>';
            if (list.description) {
                html += '<p>' + this.escapeHtml(list.description) + '</p>';
            }
            html += '</div>';
            html += '</div>';

            // Action bar
            html += '<div class="sffc-crm-list-action-bar">';
            html += '<span class="sffc-crm-list-count">' + jobs.length + ' role' + (jobs.length !== 1 ? 's' : '') + '</span>';
            html += '</div>';

            // Roles list - render as match rows
            html += '<div class="sffc-crm-matches-list">';
            if (jobs.length === 0) {
                html += '<div class="sffc-crm-empty-state">';
                html += '<p>No roles in this list yet.</p>';
                html += '</div>';
            } else {
                jobs.forEach(function(job) {
                    html += self.renderJobOutreachRow(job, list.id);
                });
            }
            html += '</div>';

            $panel.html(html);
            this.bindJobListDetailEvents(list.id);
        },

        /**
         * Render job row in outreach list (simplified match row)
         */
        renderJobOutreachRow: function(job, listId) {
            var html = '<div class="sffc-crm-match-row" data-post-id="' + job.id + '">';

            // Checkbox removed - not needed in list view
            html += '<div class="sffc-crm-match-content">';

            // Role title
            html += '<h3 class="sffc-crm-match-title">' + this.escapeHtml(job.post_title) + '</h3>';

            // Meta info
            html += '<div class="sffc-crm-match-meta">';
            var metaParts = [];
            if (job.company) metaParts.push(job.company);
            if (job.location) metaParts.push(job.location);
            html += metaParts.join(' • ');
            html += '</div>';

            html += '</div>';

            // Actions
            html += '<div class="sffc-crm-match-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small remove-job-from-list-btn" data-list-id="' + listId + '" data-post-id="' + job.id + '" title="Remove from list">';
            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            html += '</div>';

            return html;
        },

        /**
         * Bind job list detail events
         */
        bindJobListDetailEvents: function(listId) {
            var self = this;

            // Back button
            $(document).off('click.joblistdetail', '#back-to-job-lists').on('click.joblistdetail', '#back-to-job-lists', function() {
                self.loadOutreachLists();
            });

            // Remove from list
            $(document).off('click.removejobfromlist', '.remove-job-from-list-btn').on('click.removejobfromlist', '.remove-job-from-list-btn', function() {
                var postId = $(this).data('post-id');
                var listId = $(this).data('list-id');
                self.removeJobFromList(listId, postId);
            });
        },

        /**
         * Delete job outreach list
         */
        deleteJobOutreachList: function(listId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_delete_job_outreach_list',
                    list_id: listId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('List deleted successfully');
                        self.loadOutreachLists();
                    } else {
                        self.showError(response.data || 'Failed to delete list');
                    }
                },
                error: function() {
                    self.showError('Error deleting list');
                }
            });
        },

        /**
         * Remove job from outreach list
         */
        removeJobFromList: function(listId, postId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_remove_job_from_outreach_list',
                    list_id: listId,
                    post_id: postId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Role removed from list');
                        // Reload list details
                        self.viewJobOutreachList(listId);
                    } else {
                        self.showError(response.data || 'Failed to remove role');
                    }
                },
                error: function() {
                    self.showError('Error removing role');
                }
            });
        },

        /**
         * Load CV/Resume tab
         */
        loadResumeTab: function(forceReload) {
            var self = this;
            var $panel = $('#panel-resume');

            if (!this.config.isLoggedIn) {
                $panel.html('<div class="sffc-crm-empty-state"><p>Please <a href="' + this.config.loginUrl + '">sign in</a> to manage your CV.</p></div>');
                return;
            }

            if (this.cvState.isLoading) {
                return;
            }

            if (this.cvState.loaded && !forceReload) {
                this.renderCvList();
                return;
            }

            this.cvState.isLoading = true;
            $('#crm-cv-list').html('<div class="sffc-crm-loading">Loading CVs...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_cv_versions',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    self.cvState.isLoading = false;
                    if (response.success) {
                        self.cvState.items = response.data.items || [];
                        self.cvState.selectedId = response.data.default_id || null;
                        self.cvState.loaded = true;
                        self.renderCvList();
                    } else {
                        $('#crm-cv-list').html('<div class="sffc-crm-cv-empty">Unable to load CVs right now.</div>');
                    }
                },
                error: function() {
                    self.cvState.isLoading = false;
                    $('#crm-cv-list').html('<div class="sffc-crm-cv-empty">Error loading CVs.</div>');
                }
            });
        },

        renderCvList: function() {
            var self = this;
            var $list = $('#crm-cv-list');

            if (!this.cvState.items.length) {
                $list.html('<div class="sffc-crm-cv-empty">Paste your first CV to have MENA Careers personalise every outreach.</div>');
                return;
            }

            var html = '';
            this.cvState.items.forEach(function(item) {
                html += '<div class="sffc-crm-cv-card" data-cv-id="' + item.id + '">';
                html += '<div class="sffc-crm-cv-card-header">';
                html += '<h3>' + self.escapeHtml(item.label || 'CV Version') + '</h3>';
                if (item.is_default) {
                    html += '<span class="sffc-crm-cv-badge">Default</span>';
                }
                html += '</div>';
                html += '<div class="sffc-crm-cv-card-meta">';
                if (item.created_at) {
                    html += '<span>Saved ' + self.timeAgo(item.created_at) + '</span>';
                }
                if (item.word_count) {
                    html += '<span>' + item.word_count + ' words</span>';
                }
                html += '</div>';
                if (item.preview) {
                    html += '<div class="sffc-crm-cv-card-preview">' + self.escapeHtml(item.preview) + '</div>';
                }
                html += '<div class="sffc-crm-cv-actions">';
                if (!item.is_default) {
                    html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-small sffc-crm-cv-set-default" data-cv-id="' + item.id + '">Set as Default</button>';
                }
                html += '</div>';
                html += '</div>';
            });

            $list.html(html);
        },

        ensureCvDataLoaded: function(callback, errorCallback) {
            var self = this;
            if (this.cvState.loaded) {
                if (typeof callback === 'function') {
                    callback(this.cvState.items);
                }
                return;
            }

            if (this.cvState.isLoading) {
                var attempts = 0;
                var watcher = setInterval(function() {
                    attempts++;
                    if (!self.cvState.isLoading) {
                        clearInterval(watcher);
                        if (typeof callback === 'function') {
                            callback(self.cvState.items);
                        }
                    } else if (attempts > 60) {
                        clearInterval(watcher);
                        if (typeof errorCallback === 'function') {
                            errorCallback();
                        }
                    }
                }, 150);
                return;
            }

            this.cvState.isLoading = true;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_cv_versions',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    self.cvState.isLoading = false;
                    if (response.success) {
                        self.cvState.items = response.data.items || [];
                        self.cvState.selectedId = response.data.default_id || null;
                        self.cvState.loaded = true;
                        if (typeof callback === 'function') {
                            callback(self.cvState.items);
                        }
                    } else if (typeof errorCallback === 'function') {
                        errorCallback(response);
                    }
                },
                error: function() {
                    self.cvState.isLoading = false;
                    if (typeof errorCallback === 'function') {
                        errorCallback();
                    }
                }
            });
        },

        renderIntroCvOptions: function(containerSelector) {
            var self = this;
            var $container = $(containerSelector);
            if (!$container.length) {
                return;
            }

            this.ensureCvDataLoaded(function(items) {
                if (!items || !items.length) {
                    $container.html('<div class="sffc-crm-intro-cv-empty">Add a CV to your library so we can attach it here.</div>');
                    return;
                }

                var html = '';
                items.forEach(function(cv, index) {
                    var checked = (self.cvState.selectedId && cv.id === self.cvState.selectedId) || (!self.cvState.selectedId && index === 0);
                    var previewId = 'intro-cv-preview-' + cv.id;
                    html += '<label class="sffc-crm-intro-cv-option">';
                    html += '<input type="radio" name="intro-cv-option" value="' + self.escapeHtml(cv.id) + '" ' + (checked ? 'checked' : '') + '>';
                    html += '<div class="sffc-crm-intro-cv-label">';
                    html += '<strong>' + self.escapeHtml(cv.label || 'CV Version') + '</strong>';
                    if (cv.created_at) {
                        html += '<span>Updated ' + self.escapeHtml(self.timeAgo(cv.created_at)) + '</span>';
                    }
                    if (cv.preview) {
                        html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small sffc-crm-intro-cv-toggle" data-target="' + self.escapeHtml(previewId) + '">Preview CV</button>';
                    }
                    html += '</div>';
                    if (cv.preview) {
                        html += '<div class="sffc-crm-intro-cv-preview" id="' + self.escapeHtml(previewId) + '">' + self.escapeHtml(cv.preview) + '</div>';
                    }
                    html += '</label>';
                });

                $container.html(html);
            }, function() {
                $container.html('<div class="sffc-crm-intro-cv-empty">Unable to load your CVs right now.</div>');
            });
        },

        saveCvVersion: function($form) {
            var self = this;
            var title = $form.find('#crm-cv-title').val().trim();
            var content = $form.find('#crm-cv-content').val().trim();

            if (!content) {
                alert('Please paste your CV before saving.');
                return;
            }

            var $submit = $form.find('button[type="submit"]');
            var originalText = $submit.text();
            $submit.prop('disabled', true).text('Saving...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_save_cv_version',
                    nonce: this.config.nonce,
                    title: title,
                    content: content
                },
                success: function(response) {
                    $submit.prop('disabled', false).text(originalText);
                    if (response.success) {
                        self.cvState.items = response.data.items || [];
                        self.cvState.selectedId = response.data.default_id || null;
                        self.cvState.loaded = true;
                        self.renderCvList();
                        $form[0].reset();
                        self.showSuccess('CV saved successfully');
                    } else {
                        self.showError(response.data?.message || 'Unable to save CV');
                    }
                },
                error: function() {
                    $submit.prop('disabled', false).text(originalText);
                    self.showError('Unable to save CV');
                }
            });
        },

        setDefaultCv: function(cvId, $button) {
            var self = this;
            if (!cvId) {
                return;
            }

            var originalText = $button.text();
            $button.prop('disabled', true).text('Setting...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_set_cv_default',
                    nonce: this.config.nonce,
                    cv_id: cvId
                },
                success: function(response) {
                    $button.prop('disabled', false).text(originalText);
                    if (response.success) {
                        self.cvState.items = response.data.items || [];
                        self.cvState.selectedId = response.data.default_id || null;
                        self.renderCvList();
                        self.showSuccess('Default CV updated');
                    } else {
                        self.showError(response.data?.message || 'Unable to update default');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(originalText);
                    self.showError('Unable to update default');
                }
            });
        },

        bindQuickCvForm: function() {
            var self = this;

            // Handle quick CV form submission
            $(document).off('submit.quickcv', '#crm-quick-cv-form').on('submit.quickcv', '#crm-quick-cv-form', function(e) {
                e.preventDefault();
                var $form = $(this);
                var content = $form.find('#crm-quick-cv-content').val().trim();

                if (!content) {
                    self.showError('Please paste your CV before analyzing.');
                    return;
                }

                var $submitBtn = $form.find('.sffc-crm-analyze-cv-btn');
                var originalText = $submitBtn.html();
                $submitBtn.prop('disabled', true).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px; animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10"></circle></svg>Analyzing...');

                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_save_cv_version',
                        nonce: self.config.nonce,
                        title: 'My CV',
                        content: content
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update CV state
                            self.cvState.items = response.data.items || [];
                            self.cvState.selectedId = response.data.default_id || null;
                            self.cvState.loaded = true;

                            // Show success animation
                            var $panel = $('#panel-matches');
                            var successHtml = '<div class="sffc-crm-success-state">';
                            successHtml += '<div class="sffc-crm-success-icon">';
                            successHtml += '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                            successHtml += '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>';
                            successHtml += '<polyline points="22 4 12 14.01 9 11.01"></polyline>';
                            successHtml += '</svg>';
                            successHtml += '</div>';
                            successHtml += '<h3>CV Analyzed Successfully!</h3>';
                            successHtml += '<p>Finding your best matches...</p>';
                            successHtml += '<div class="sffc-crm-progress-bar">';
                            successHtml += '<div class="sffc-crm-progress-fill"></div>';
                            successHtml += '</div>';
                            successHtml += '</div>';

                            $panel.html(successHtml);

                            // Show success toast
                            self.showSuccess('CV analyzed successfully!');

                            // Reload matches after a brief delay to show animation
                            setTimeout(function() {
                                self.matchesLoaded = false;
                                self.loadMatches();
                            }, 1500);
                        } else {
                            $submitBtn.prop('disabled', false).html(originalText);
                            self.showError(response.data?.message || 'Unable to save CV');
                        }
                    },
                    error: function() {
                        $submitBtn.prop('disabled', false).html(originalText);
                        self.showError('Unable to save CV. Please try again.');
                    }
                });
            });

            // Handle upload file button click
            $(document).off('click.quickcv', '.sffc-crm-upload-file-btn').on('click.quickcv', '.sffc-crm-upload-file-btn', function() {
                $('#sffc-crm-quick-cv-file').click();
            });

            // Handle file selection
            $(document).off('change.quickcv', '#sffc-crm-quick-cv-file').on('change.quickcv', '#sffc-crm-quick-cv-file', function(e) {
                var file = e.target.files[0];
                if (!file) return;

                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    self.showError('File size must be less than 5MB');
                    return;
                }

                // Validate file type
                var allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
                if (!allowedTypes.includes(file.type)) {
                    self.showError('Please upload a PDF, DOC, DOCX, or TXT file');
                    return;
                }

                // Read file content
                var reader = new FileReader();
                reader.onload = function(e) {
                    var content = e.target.result;

                    // For text files, use content directly
                    // For PDF/DOC files, we'll need to extract text on the backend
                    if (file.type === 'text/plain') {
                        $('#crm-quick-cv-content').val(content);
                        self.showSuccess('File loaded! Click "Analyze CV" to continue.');
                    } else {
                        // For PDF/DOC files, redirect to Resume tab with more robust upload
                        self.showError('For PDF/DOC files, please use the Resume tab for full file processing.');
                        setTimeout(function() {
                            self.switchTab('resume');
                        }, 2000);
                    }
                };

                reader.onerror = function() {
                    self.showError('Failed to read file');
                };

                // Read as text for TXT files, otherwise show message
                if (file.type === 'text/plain') {
                    reader.readAsText(file);
                } else {
                    reader.readAsText(file); // Will try to read but redirect to Resume tab
                }
            });

            // Handle welcome banner dismiss
            $(document).off('click.quickcv', '.sffc-crm-welcome-banner-close, .sffc-crm-welcome-banner-btn').on('click.quickcv', '.sffc-crm-welcome-banner-close, .sffc-crm-welcome-banner-btn', function() {
                localStorage.setItem('sffc_crm_welcome_banner_dismissed', 'true');
                $('#sffc-crm-welcome-banner').fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Bind outreach list events
         */
        bindOutreachListEvents: function() {
            var self = this;

            // Create new list
            $(document).off('click.outreachlists', '#create-outreach-list-btn, #create-first-list-btn').on('click.outreachlists', '#create-outreach-list-btn, #create-first-list-btn', function() {
                self.showCreateListModal();
            });

            // View list
            $(document).off('click.outreachlists', '.view-list-btn').on('click.outreachlists', '.view-list-btn', function() {
                var listId = $(this).data('list-id');
                self.viewOutreachList(listId);
            });

            // Reach out to all
            $(document).off('click.outreachlists', '.reach-out-all-btn').on('click.outreachlists', '.reach-out-all-btn', function() {
                var listId = $(this).data('list-id');
                self.startListOutreach(listId);
            });

            // Delete list
            $(document).off('click.outreachlists', '.delete-list-btn').on('click.outreachlists', '.delete-list-btn', function() {
                var listId = $(this).data('list-id');
                if (confirm('Are you sure you want to delete this list? This will not delete the recruiters, only the list.')) {
                    self.deleteOutreachList(listId);
                }
            });
        },

        /**
         * Show create list modal
         */
        showCreateListModal: function() {
            // For now, just show a simple prompt
            // In Phase 5, this will be a full modal
            var listName = prompt('Enter list name:');
            if (listName) {
                this.createOutreachList(listName, '', []);
            }
        },

        /**
         * View outreach list details
         */
        viewOutreachList: function(listId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_outreach_list_recruiters',
                    list_id: listId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderListDetailView(response.data);
                    } else {
                        self.showError('Failed to load list');
                    }
                }
            });
        },

        /**
         * Render list detail view
         */
        renderListDetailView: function(data) {
            var self = this;
            var list = data.list;
            var recruiters = data.recruiters || [];
            var $panel = $('#panel-outreach-lists');
            var html = '';

            // Back button & header
            html += '<div class="sffc-crm-list-detail-header">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text" id="back-to-lists">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>';
            html += 'Back to Lists';
            html += '</button>';
            html += '<div class="sffc-crm-list-title">';
            html += '<h2>' + this.escapeHtml(list.list_name) + '</h2>';
            if (list.description) {
                html += '<p>' + this.escapeHtml(list.description) + '</p>';
            }
            html += '</div>';
            html += '</div>';

            // Action bar
            html += '<div class="sffc-crm-list-action-bar">';
            html += '<span class="sffc-crm-list-count">' + recruiters.length + ' recruiter' + (recruiters.length !== 1 ? 's' : '') + '</span>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="reach-out-to-selected" data-list-id="' + list.id + '" ' + (recruiters.length === 0 ? 'disabled' : '') + '>';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
            html += 'Send CV to All';
            html += '</button>';
            html += '</div>';

            // Recruiters list
            html += '<div class="sffc-crm-list-recruiters">';
            if (recruiters.length === 0) {
                html += '<div class="sffc-crm-empty-state">';
                html += '<p>No recruiters in this list yet.</p>';
                html += '</div>';
            } else {
                recruiters.forEach(function(recruiter) {
                    html += '<div class="sffc-crm-list-recruiter-row" data-recruiter-id="' + recruiter.id + '">';

                    // Avatar
                    html += '<div class="sffc-crm-recruiter-avatar">';
                    if (recruiter.photo_url) {
                        html += '<img src="' + recruiter.photo_url + '" alt="">';
                    } else {
                        html += '<div class="sffc-crm-avatar-placeholder">' + recruiter.name.charAt(0) + '</div>';
                    }
                    html += '</div>';

                    // Details
                    html += '<div class="sffc-crm-recruiter-details">';
                    html += '<span class="sffc-crm-recruiter-name">' + self.escapeHtml(recruiter.name) + '</span>';
                    html += '<span class="sffc-crm-recruiter-firm">' + self.escapeHtml(recruiter.firm || '') + '</span>';
                    html += '</div>';

                    // Actions
                    html += '<div class="sffc-crm-list-recruiter-actions">';
                    html += '<button class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small remove-from-list-btn" data-list-id="' + list.id + '" data-recruiter-id="' + recruiter.id + '">';
                    html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
                    html += 'Remove';
                    html += '</button>';
                    html += '</div>';

                    html += '</div>';
                });
            }
            html += '</div>';

            $panel.html(html);
            this.bindListDetailEvents(list.id);
        },

        /**
         * Bind list detail events
         */
        bindListDetailEvents: function(listId) {
            var self = this;

            // Back button
            $(document).off('click.listdetail', '#back-to-lists').on('click.listdetail', '#back-to-lists', function() {
                self.loadOutreachLists();
            });

            // Reach out to all
            $(document).off('click.listdetail', '#reach-out-to-selected').on('click.listdetail', '#reach-out-to-selected', function() {
                self.startListOutreach(listId);
            });

            // Remove from list
            $(document).off('click.listdetail', '.remove-from-list-btn').on('click.listdetail', '.remove-from-list-btn', function() {
                var recruiterId = $(this).data('recruiter-id');
                self.removeFromList(listId, recruiterId);
            });
        },

        /**
         * Start outreach for a list
         */
        startListOutreach: function(listId) {
            var self = this;

            // Load recruiters from list
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_outreach_list_recruiters',
                    list_id: listId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.recruiters.length > 0) {
                        var recruiterIds = response.data.recruiters.map(function(r) { return r.id; });
                        self.generateAndShowOutreachCarousel(recruiterIds, listId);
                    } else {
                        self.showError('No recruiters in this list');
                    }
                }
            });
        },

        /**
         * Generate messages and show outreach carousel
         */
        generateAndShowOutreachCarousel: function(recruiterIds, listId) {
            var self = this;

            // Show loading modal
            var loadingHtml = '<div class="sffc-crm-outreach-loading-modal">';
            loadingHtml += '<div class="sffc-crm-loading-content">';
            loadingHtml += '<div class="sffc-crm-spinner"></div>';
            loadingHtml += '<h3>Generating personalized messages...</h3>';
            loadingHtml += '<p>MENA Careers is creating unique messages for each recruiter.</p>';
            loadingHtml += '</div>';
            loadingHtml += '</div>';

            this.showModal(loadingHtml);

            // Generate messages via Claude
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_generate_outreach_messages',
                    recruiter_ids: recruiterIds,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.messages) {
                        self.closeModal();
                        self.showOutreachCarousel(response.data.messages, listId);
                    } else {
                        self.closeModal();
                        self.handleError(response);
                    }
                },
                error: function() {
                    self.closeModal();
                    self.showError('Failed to generate messages');
                }
            });
        },

        /**
         * Show outreach carousel - Sequential mailto sending interface
         */
        showOutreachCarousel: function(messages, listId) {
            this.carouselState = {
                messages: messages,
                listId: listId,
                currentIndex: 0,
                sentCount: 0,
                skippedCount: 0,
                sentRecruiterIds: [],
                startTime: Date.now()
            };

            this.renderCarouselSlide();
        },

        /**
         * Render current carousel slide
         */
        renderCarouselSlide: function() {
            var self = this;
            var state = this.carouselState;
            var current = state.messages[state.currentIndex];
            var total = state.messages.length;
            var progress = Math.round(((state.currentIndex + 1) / total) * 100);

            var html = '<div class="sffc-crm-outreach-carousel">';

            // Header
            html += '<div class="sffc-crm-carousel-header">';
            html += '<div class="sffc-crm-carousel-title">';
            html += '<h3>Multi-Recruiter Outreach</h3>';
            html += '<p>Recruiter ' + (state.currentIndex + 1) + ' of ' + total + '</p>';
            html += '</div>';
            html += '<button class="sffc-crm-modal-close" id="close-carousel" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            // Progress bar
            html += '<div class="sffc-crm-carousel-progress">';
            html += '<div class="sffc-crm-progress-bar">';
            html += '<div class="sffc-crm-progress-fill" style="width: ' + progress + '%"></div>';
            html += '</div>';
            html += '<div class="sffc-crm-progress-dots">';
            for (var i = 0; i < total; i++) {
                var dotClass = 'sffc-crm-progress-dot';
                if (i < state.currentIndex) {
                    dotClass += ' completed';
                } else if (i === state.currentIndex) {
                    dotClass += ' active';
                }
                html += '<span class="' + dotClass + '"></span>';
            }
            html += '</div>';
            html += '</div>';

            // Recruiter info
            html += '<div class="sffc-crm-carousel-recruiter">';
            html += '<div class="sffc-crm-recruiter-avatar-large">';
            html += '<div class="sffc-crm-avatar-placeholder-large">' + current.recruiter_name.charAt(0) + '</div>';
            html += '</div>';
            html += '<div class="sffc-crm-recruiter-info-large">';
            html += '<h4>' + this.escapeHtml(current.recruiter_name) + '</h4>';
            if (current.recruiter_firm) {
                html += '<p>' + this.escapeHtml(current.recruiter_firm) + '</p>';
            }
            html += '<span class="sffc-crm-recruiter-email">' + this.escapeHtml(current.recruiter_email) + '</span>';
            html += '</div>';
            html += '</div>';

            // Message preview
            html += '<div class="sffc-crm-carousel-message">';
            html += '<div class="sffc-crm-message-subject">';
            html += '<label>Subject:</label>';
            html += '<div class="sffc-crm-subject-text">' + this.escapeHtml(current.subject) + '</div>';
            html += '</div>';
            html += '<div class="sffc-crm-message-body">';
            html += '<label>Message:</label>';
            html += '<div class="sffc-crm-message-preview">';
            html += this.escapeHtml(current.message).replace(/\n/g, '<br>');
            html += '</div>';
            html += '</div>';
            html += '</div>';

            // Actions
            html += '<div class="sffc-crm-carousel-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text" id="skip-recruiter">';
            html += 'Skip';
            html += '</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-btn-large" id="send-email-btn">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
            html += 'Send Email to ' + current.recruiter_name.split(' ')[0];
            html += '</button>';
            html += '</div>';

            // Footer stats
            html += '<div class="sffc-crm-carousel-footer">';
            html += '<span class="sffc-crm-carousel-stat">✓ ' + state.sentCount + ' sent</span>';
            if (state.skippedCount > 0) {
                html += '<span class="sffc-crm-carousel-stat">⊘ ' + state.skippedCount + ' skipped</span>';
            }
            html += '<span class="sffc-crm-carousel-stat">' + (total - state.currentIndex - 1) + ' remaining</span>';
            html += '</div>';

            html += '</div>';

            this.showModal(html);
            this.bindCarouselEvents();
        },

        /**
         * Bind carousel events
         */
        bindCarouselEvents: function() {
            var self = this;

            // Send email button
            $(document).off('click.carousel', '#send-email-btn').on('click.carousel', '#send-email-btn', function() {
                self.openMailtoAndConfirm();
            });

            // Skip button
            $(document).off('click.carousel', '#skip-recruiter').on('click.carousel', '#skip-recruiter', function() {
                self.skipRecruiter();
            });

            // Close carousel
            $(document).off('click.carousel', '#close-carousel').on('click.carousel', '#close-carousel', function() {
                if (confirm('Are you sure you want to cancel? Your progress will be lost.')) {
                    self.closeCarousel();
                }
            });
        },

        /**
         * Open mailto and show confirmation dialog
         */
        openMailtoAndConfirm: function() {
            var self = this;
            var current = this.carouselState.messages[this.carouselState.currentIndex];

            // Open mailto link
            window.location.href = current.mailto_url;

            // Show confirmation dialog after brief delay
            setTimeout(function() {
                self.showSendConfirmation();
            }, 500);
        },

        /**
         * Show send confirmation dialog
         */
        showSendConfirmation: function() {
            var self = this;
            var current = this.carouselState.messages[this.carouselState.currentIndex];
            var firstName = current.recruiter_name.split(' ')[0];

            var html = '<div class="sffc-crm-send-confirmation">';
            html += '<div class="sffc-crm-confirmation-icon">';
            html += '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
            html += '</div>';
            html += '<h3>Did you send the email to ' + this.escapeHtml(firstName) + '?</h3>';
            html += '<p>Your email client should have opened with the pre-filled message.</p>';
            html += '<div class="sffc-crm-confirmation-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-text" id="didnt-send">No, I didn\'t send it</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="confirm-sent">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            html += 'Yes, I sent it';
            html += '</button>';
            html += '</div>';
            html += '</div>';

            this.showModal(html);

            // Bind confirmation events
            $(document).off('click.confirm', '#confirm-sent').on('click.confirm', '#confirm-sent', function() {
                self.confirmSent();
            });

            $(document).off('click.confirm', '#didnt-send').on('click.confirm', '#didnt-send', function() {
                self.closeModal();
                self.renderCarouselSlide();
            });
        },

        /**
         * Confirm email was sent
         */
        confirmSent: function() {
            var self = this;
            var state = this.carouselState;
            var current = state.messages[state.currentIndex];

            // Track in CRM
            this.trackOutreachSent(current.recruiter_id, current.message, current.subject);

            // Update state
            state.sentCount++;
            state.sentRecruiterIds.push(current.recruiter_id);

            // Move to next or finish
            this.advanceCarousel();
        },

        /**
         * Skip current recruiter
         */
        skipRecruiter: function() {
            this.carouselState.skippedCount++;
            this.advanceCarousel();
        },

        /**
         * Advance to next recruiter or show completion
         */
        advanceCarousel: function() {
            var state = this.carouselState;
            state.currentIndex++;

            if (state.currentIndex < state.messages.length) {
                // More recruiters remaining
                this.closeModal();
                setTimeout(() => {
                    this.renderCarouselSlide();
                }, 300);
            } else {
                // All done!
                this.showCarouselCompletion();
            }
        },

        /**
         * Show carousel completion screen
         */
        showCarouselCompletion: function() {
            var self = this;
            var state = this.carouselState;
            var duration = Math.round((Date.now() - state.startTime) / 1000 / 60); // minutes

            var html = '<div class="sffc-crm-carousel-completion">';

            // Success icon
            html += '<div class="sffc-crm-completion-icon">';
            html += '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
            html += '</div>';

            // Title
            html += '<h2>Outreach Campaign Complete!</h2>';

            // Stats
            html += '<div class="sffc-crm-completion-stats">';
            html += '<div class="sffc-crm-completion-stat">';
            html += '<span class="sffc-crm-stat-number">' + state.sentCount + '</span>';
            html += '<span class="sffc-crm-stat-label">Emails Sent</span>';
            html += '</div>';
            if (state.skippedCount > 0) {
                html += '<div class="sffc-crm-completion-stat">';
                html += '<span class="sffc-crm-stat-number">' + state.skippedCount + '</span>';
                html += '<span class="sffc-crm-stat-label">Skipped</span>';
                html += '</div>';
            }
            html += '<div class="sffc-crm-completion-stat">';
            html += '<span class="sffc-crm-stat-number">' + duration + ' min</span>';
            html += '<span class="sffc-crm-stat-label">Duration</span>';
            html += '</div>';
            html += '</div>';

            // Summary
            if (state.sentCount > 0) {
                html += '<div class="sffc-crm-completion-summary">';
                html += '<p>Your personalized outreach emails have been tracked in the CRM.</p>';
                html += '<p>You\'ll be notified when recruiters respond.</p>';
                html += '</div>';
            } else {
                html += '<div class="sffc-crm-completion-summary">';
                html += '<p>No emails were sent. You can try again anytime from the Smart message tab.</p>';
                html += '</div>';
            }

            // Actions
            html += '<div class="sffc-crm-completion-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary" id="view-pipeline">';
            html += 'View Pipeline';
            html += '</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="done-carousel">';
            html += 'Done';
            html += '</button>';
            html += '</div>';

            html += '</div>';

            this.showModal(html);

            // Bind completion events
            $(document).off('click.completion', '#done-carousel').on('click.completion', '#done-carousel', function() {
                self.closeModal();
                self.loadOutreachLists();
            });

            $(document).off('click.completion', '#view-pipeline').on('click.completion', '#view-pipeline', function() {
                self.closeModal();
                self.switchTab('pipeline');
            });
        },

        /**
         * Track outreach in CRM
         */
        trackOutreachSent: function(recruiterId, message, subject) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_track_outreach_sent',
                    recruiter_id: recruiterId,
                    message: message,
                    subject: subject,
                    list_id: this.carouselState.listId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    // Silently track - don't interrupt user flow
                    console.log('Outreach tracked for recruiter', recruiterId);
                },
                error: function() {
                    console.error('Failed to track outreach for recruiter', recruiterId);
                }
            });
        },

        /**
         * Close carousel
         */
        closeCarousel: function() {
            this.closeModal();
            this.carouselState = null;
        },

        /**
         * Remove recruiter from list
         */
        removeFromList: function(listId, recruiterId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_remove_from_outreach_list',
                    list_id: listId,
                    recruiter_id: recruiterId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Recruiter removed from list');
                        // Reload list detail
                        self.viewOutreachList(listId);
                    } else {
                        self.handleError(response);
                    }
                }
            });
        },

        /**
         * Delete outreach list
         */
        deleteOutreachList: function(listId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_delete_outreach_list',
                    list_id: listId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('List deleted successfully');
                        self.loadOutreachLists();
                    } else {
                        self.handleError(response);
                    }
                }
            });
        },

        loadExpertOutreach: function() {
            var self = this;
            this.loadExpertOutreachStats();
            this.loadExpertOutreachRequests();
            this.loadAutoOutreachSettings();
            this.initExpertOutreachEvents();
        },

        /**
         * Load expert outreach stats
         */
        loadExpertOutreachStats: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_expert_outreach_stats',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.expertOutreachState.stats = response.data.stats;
                        self.renderExpertOutreachStats(response.data.stats);
                    }
                }
            });
        },

        /**
         * Render expert outreach stats
         */
        renderExpertOutreachStats: function(stats) {
            $('#stat-total').text(stats.total || 0);
            $('#stat-pending').text(stats.pending || 0);
            $('#stat-sent').text(stats.sent || 0);
            $('#stat-replied').text(stats.replied || 0);
        },

        /**
         * Load expert outreach requests
         */
        loadExpertOutreachRequests: function() {
            var self = this;
            var $container = $('#expert-outreach-requests');

            $container.html('<div class="sffc-crm-loading">Loading requests...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_expert_outreach_list',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.expertOutreachState.requests = response.data.requests;
                        self.renderExpertOutreachRequests(response.data.requests);
                    } else {
                        $container.html('<div class="sffc-crm-error">Failed to load requests</div>');
                    }
                },
                error: function() {
                    $container.html('<div class="sffc-crm-error">Failed to load requests</div>');
                }
            });
        },

        /**
         * Render expert outreach requests
         */
        renderExpertOutreachRequests: function(requests) {
            var self = this;
            var $container = $('#expert-outreach-requests');

            if (!requests || requests.length === 0) {
                $container.html(
                    '<div class="sffc-crm-expert-empty">' +
                    '<svg class="sffc-crm-expert-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
                    '<path d="M12 2L2 7l10 5 10-5-10-5z"></path>' +
                    '<path d="M2 17l10 5 10-5"></path>' +
                    '<path d="M2 12l10 5 10-5"></path>' +
                    '</svg>' +
                    '<h3>No outreach requests yet</h3>' +
                    '<p>Visit the Recruiters tab to request expert outreach for any recruiter.</p>' +
                    '</div>'
                );
                return;
            }

            var html = '';
            requests.forEach(function(request) {
                html += self.renderExpertRequest(request);
            });

            $container.html(html);
        },

        /**
         * Render single expert request
         */
        renderExpertRequest: function(request) {
            var photo = request.recruiter_photo || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(request.recruiter_name || 'R') + '&background=0d353e&color=fff';
            var name = request.recruiter_name || 'Unknown Recruiter';
            var meta = request.recruiter_firm || '';
            if (request.created_at) {
                var date = new Date(request.created_at);
                meta += (meta ? ' · ' : '') + date.toLocaleDateString();
            }

            var html = '<div class="sffc-crm-expert-request" data-request-id="' + request.id + '">';
            html += '<img class="sffc-crm-expert-request-photo" src="' + this.escapeHtml(photo) + '" alt="">';
            html += '<div class="sffc-crm-expert-request-info">';
            html += '<div class="sffc-crm-expert-request-name">' + this.escapeHtml(name) + '</div>';
            html += '<div class="sffc-crm-expert-request-meta">' + this.escapeHtml(meta) + '</div>';
            html += '</div>';
            html += '<span class="sffc-crm-expert-request-status ' + request.status + '">' + this.formatStatus(request.status) + '</span>';

            if (request.status === 'pending') {
                html += '<button type="button" class="sffc-crm-expert-request-cancel" data-request-id="' + request.id + '" title="Cancel request">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                html += '<line x1="18" y1="6" x2="6" y2="18"></line>';
                html += '<line x1="6" y1="6" x2="18" y2="18"></line>';
                html += '</svg></button>';
            }

            html += '</div>';
            return html;
        },

        /**
         * Format status for display
         */
        formatStatus: function(status) {
            var statusMap = {
                'pending': 'Pending',
                'in_progress': 'In Progress',
                'sent': 'Sent',
                'replied': 'Replied',
                'failed': 'Failed',
                'cancelled': 'Cancelled'
            };
            return statusMap[status] || status;
        },

        /**
         * Load auto outreach settings
         */
        loadAutoOutreachSettings: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_auto_outreach_settings',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.expertOutreachState.autoSettings = response.data.settings;
                        self.updateAutoOutreachUI(response.data.settings);
                    }
                }
            });
        },

        /**
         * Update auto outreach UI
         */
        updateAutoOutreachUI: function(settings) {
            var $card = $('#auto-outreach-card');
            var $toggle = $('#auto-outreach-toggle');

            if (settings.is_enabled) {
                $card.removeClass('is-disabled');
                $toggle.addClass('is-active');
            } else {
                $card.addClass('is-disabled');
                $toggle.removeClass('is-active');
            }

            // Update settings modal values
            $('#auto-weekly-limit').val(settings.weekly_limit || 10);
            $('#auto-message-tone').val(settings.message_tone || 'professional');
            $('#auto-custom-intro').val(settings.custom_intro || '');
            $('#auto-include-cv').prop('checked', settings.include_cv !== 0);
        },

        /**
         * Initialize expert outreach events
         */
        initExpertOutreachEvents: function() {
            var self = this;

            // Unbind first to prevent duplicates
            $(document).off('click.expertOutreach');

            // Auto outreach toggle
            $(document).on('click.expertOutreach', '#auto-outreach-toggle', function() {
                var $toggle = $(this);
                var isActive = $toggle.hasClass('is-active');

                // Toggle immediately for UX
                $toggle.toggleClass('is-active');
                $('#auto-outreach-card').toggleClass('is-disabled');

                // Save to server
                self.saveAutoOutreachSettings({ is_enabled: !isActive ? 1 : 0 });
            });

            // Configure auto outreach button
            $(document).on('click.expertOutreach', '#configure-auto-outreach', function() {
                $('#auto-outreach-settings-modal').addClass('is-active');
            });

            // Close auto settings modal
            $(document).on('click.expertOutreach', '#close-auto-settings-modal, #cancel-auto-settings', function() {
                $('#auto-outreach-settings-modal').removeClass('is-active');
            });

            // Save auto settings
            $(document).on('click.expertOutreach', '#save-auto-settings', function() {
                var settings = {
                    weekly_limit: $('#auto-weekly-limit').val(),
                    message_tone: $('#auto-message-tone').val(),
                    custom_intro: $('#auto-custom-intro').val(),
                    include_cv: $('#auto-include-cv').is(':checked') ? 1 : 0,
                    is_enabled: self.expertOutreachState.autoSettings.is_enabled
                };

                self.saveAutoOutreachSettings(settings);
                $('#auto-outreach-settings-modal').removeClass('is-active');
            });

            // Expert outreach modal close
            $(document).on('click.expertOutreach', '#close-expert-modal, #cancel-expert-request', function() {
                self.closeExpertOutreachModal();
            });

            // Submit expert outreach request
            $(document).on('click.expertOutreach', '#submit-expert-request', function() {
                self.submitExpertOutreachRequest();
            });

            // Cancel request
            $(document).on('click.expertOutreach', '.sffc-crm-expert-request-cancel', function(e) {
                e.stopPropagation();
                var requestId = $(this).data('request-id');
                self.cancelExpertOutreachRequest(requestId);
            });

            // Close modal on overlay click
            $(document).on('click.expertOutreach', '.sffc-crm-expert-modal-overlay', function(e) {
                if ($(e.target).hasClass('sffc-crm-expert-modal-overlay')) {
                    $(this).removeClass('is-active');
                }
            });

            // Custom Expert Request Modal
            $(document).on('click.expertOutreach', '#open-custom-request-modal', function() {
                self.openCustomRequestModal();
            });

            $(document).on('click.expertOutreach', '#close-custom-request-modal, #cancel-custom-request', function() {
                self.closeCustomRequestModal();
            });

            $(document).on('click.expertOutreach', '#submit-custom-request', function() {
                self.submitCustomRequest();
            });

            // CV Upload handlers
            $(document).on('click.expertOutreach', '#custom-request-cv-dropzone', function() {
                $('#custom-request-cv').trigger('click');
            });

            $(document).on('change.expertOutreach', '#custom-request-cv', function(e) {
                self.handleCvFileSelect(e.target.files[0]);
            });

            $(document).on('dragover.expertOutreach', '#custom-request-cv-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });

            $(document).on('dragleave.expertOutreach', '#custom-request-cv-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            });

            $(document).on('drop.expertOutreach', '#custom-request-cv-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.handleCvFileSelect(files[0]);
                }
            });

            $(document).on('click.expertOutreach', '#remove-cv-file', function() {
                self.removeCvFile();
            });
        },

        /**
         * Open custom expert request modal
         */
        openCustomRequestModal: function() {
            // Reset form
            $('#custom-request-sectors input').prop('checked', false);
            $('#custom-request-seniority input').prop('checked', false);
            $('#custom-request-locations').val('');
            $('#custom-request-firms').val('');
            $('#custom-request-quantity').val('10');
            $('#custom-request-tone').val('professional');
            $('#custom-request-notes').val('');
            $('#custom-request-include-cv').prop('checked', true);

            // Reset CV upload
            this.removeCvFile();

            $('#custom-request-modal').addClass('is-active');
        },

        /**
         * Handle CV file selection
         */
        handleCvFileSelect: function(file) {
            if (!file) return;

            // Validate file type
            var allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (allowedTypes.indexOf(file.type) === -1) {
                this.showError('Please upload a PDF or Word document (.pdf, .doc, .docx)');
                return;
            }

            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                this.showError('File size must be less than 5MB');
                return;
            }

            // Store the file reference
            this.customRequestCvFile = file;

            // Show preview
            var fileName = file.name;
            var fileSize = (file.size / 1024).toFixed(1) + ' KB';
            if (file.size > 1024 * 1024) {
                fileSize = (file.size / (1024 * 1024)).toFixed(1) + ' MB';
            }

            $('#cv-file-name').text(fileName);
            $('#cv-file-size').text(fileSize);
            $('#custom-request-cv-dropzone').hide();
            $('#custom-request-cv-preview').show();
        },

        /**
         * Remove CV file
         */
        removeCvFile: function() {
            this.customRequestCvFile = null;
            $('#custom-request-cv').val('');
            $('#custom-request-cv-preview').hide();
            $('#custom-request-cv-dropzone').show();
        },

        /**
         * Close custom expert request modal
         */
        closeCustomRequestModal: function() {
            $('#custom-request-modal').removeClass('is-active');
        },

        /**
         * Submit custom expert request
         */
        submitCustomRequest: function() {
            var self = this;

            // Collect sectors
            var sectors = [];
            $('#custom-request-sectors input:checked').each(function() {
                sectors.push($(this).val());
            });

            // Collect seniority levels
            var seniority = [];
            $('#custom-request-seniority input:checked').each(function() {
                seniority.push($(this).val());
            });

            var notes = $('#custom-request-notes').val().trim();

            // Validation
            if (sectors.length === 0) {
                this.showError('Please select at least one target sector');
                return;
            }

            if (!notes) {
                this.showError('Please provide additional notes about your request');
                return;
            }

            var $btn = $('#submit-custom-request');
            $btn.prop('disabled', true).html('Submitting...');

            // Use FormData to support file uploads
            var formData = new FormData();
            formData.append('action', 'sffc_crm_submit_custom_expert_request');
            formData.append('nonce', this.config.nonce);
            formData.append('locations', $('#custom-request-locations').val());
            formData.append('firms', $('#custom-request-firms').val());
            formData.append('quantity', $('#custom-request-quantity').val());
            formData.append('message_tone', $('#custom-request-tone').val());
            formData.append('notes', notes);
            formData.append('include_cv', $('#custom-request-include-cv').is(':checked') ? 1 : 0);

            // Append sectors array
            sectors.forEach(function(sector) {
                formData.append('sectors[]', sector);
            });

            // Append seniority array
            seniority.forEach(function(level) {
                formData.append('seniority[]', level);
            });

            // Append CV file if uploaded
            if (this.customRequestCvFile) {
                formData.append('cv_file', this.customRequestCvFile);
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Custom expert request submitted! Our team will contact you within 24 hours.');
                        self.closeCustomRequestModal();
                        // Refresh stats and requests
                        self.loadExpertOutreachStats();
                        self.loadExpertOutreachRequests();
                    } else {
                        self.showError(response.data.message || 'Failed to submit request');
                    }
                },
                error: function() {
                    self.showError('Failed to submit request');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Submit Request');
                }
            });
        },

        /**
         * Save auto outreach settings
         */
        saveAutoOutreachSettings: function(settings) {
            var self = this;

            // Merge with existing settings
            settings = $.extend({}, this.expertOutreachState.autoSettings, settings);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: $.extend({
                    action: 'sffc_crm_save_auto_outreach_settings',
                    nonce: this.config.nonce
                }, settings),
                success: function(response) {
                    if (response.success) {
                        self.expertOutreachState.autoSettings = settings;
                        self.showSuccess('Settings saved');
                    } else {
                        self.showError('Failed to save settings');
                    }
                },
                error: function() {
                    self.showError('Failed to save settings');
                }
            });
        },

        /**
         * Open expert outreach modal for a recruiter
         */
        openExpertOutreachModal: function(recruiter) {
            this.expertOutreachState.currentRecruiter = recruiter;

            var $preview = $('#expert-recruiter-preview');
            if (recruiter) {
                var photo = recruiter.photo_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(recruiter.name || 'R') + '&background=0d353e&color=fff';
                $('#expert-recruiter-photo').attr('src', photo);
                $('#expert-recruiter-name').text(recruiter.name || 'Unknown');
                $('#expert-recruiter-title').text([recruiter.title, recruiter.firm].filter(Boolean).join(' at '));
                $preview.show();
            } else {
                $preview.hide();
            }

            // Reset form
            $('#expert-message-tone').val('professional');
            $('#expert-custom-notes').val('');
            $('#expert-include-cv').prop('checked', true);

            $('#expert-outreach-modal').addClass('is-active');
        },

        /**
         * Close expert outreach modal
         */
        closeExpertOutreachModal: function() {
            $('#expert-outreach-modal').removeClass('is-active');
            this.expertOutreachState.currentRecruiter = null;
        },

        /**
         * Submit expert outreach request
         */
        submitExpertOutreachRequest: function() {
            var self = this;
            var recruiter = this.expertOutreachState.currentRecruiter;

            if (!recruiter || !recruiter.id) {
                this.showError('No recruiter selected');
                return;
            }

            var $btn = $('#submit-expert-request');
            $btn.prop('disabled', true).html('Submitting...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_request_expert_outreach',
                    nonce: this.config.nonce,
                    recruiter_id: recruiter.id,
                    message_tone: $('#expert-message-tone').val(),
                    custom_notes: $('#expert-custom-notes').val(),
                    include_cv: $('#expert-include-cv').is(':checked') ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Expert outreach request submitted!');
                        self.closeExpertOutreachModal();
                        // Refresh if on expert outreach tab
                        if (self.currentTab === 'expert-outreach') {
                            self.loadExpertOutreachStats();
                            self.loadExpertOutreachRequests();
                        }
                    } else {
                        self.showError(response.data.message || 'Failed to submit request');
                    }
                },
                error: function() {
                    self.showError('Failed to submit request');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Submit Request');
                }
            });
        },

        /**
         * Cancel expert outreach request
         */
        cancelExpertOutreachRequest: function(requestId) {
            var self = this;

            if (!confirm('Are you sure you want to cancel this request?')) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_cancel_expert_outreach',
                    nonce: this.config.nonce,
                    request_id: requestId
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Request cancelled');
                        self.loadExpertOutreachStats();
                        self.loadExpertOutreachRequests();
                    } else {
                        self.showError(response.data.message || 'Failed to cancel request');
                    }
                },
                error: function() {
                    self.showError('Failed to cancel request');
                }
            });
        },

        /**
         * Load sequences tab
         */
        loadSequences: function() {
            var self = this;
            var $panel = $('#panel-sequences');

            $panel.html('<div class="sffc-crm-loading">Loading sequences...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_sequences',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.sequencesState.sequences = response.data.sequences;
                        self.renderSequencesList(response.data);
                    } else {
                        $panel.html('<div class="sffc-crm-error">Failed to load sequences</div>');
                    }
                },
                error: function() {
                    $panel.html('<div class="sffc-crm-error">Failed to load sequences</div>');
                }
            });
        },

        /**
         * Render sequences list
         */
        renderSequencesList: function(data) {
            var self = this;
            var $panel = $('#panel-sequences');

            var html = '<div class="sffc-crm-sequences-container">';

            // Header
            html += '<div class="sffc-crm-sequences-header">';
            html += '<h3>Outreach Sequences</h3>';
            if (data.can_create) {
                html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-create-sequence-btn">+ New Sequence</button>';
            } else {
                html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-upgrade-btn" title="Upgrade to create sequences">+ New Sequence (Upgrade)</button>';
            }
            html += '</div>';

            // Usage info
            if (data.limit > 0) {
                html += '<div class="sffc-crm-sequences-usage">';
                html += '<span>' + data.used + ' of ' + data.limit + ' sequences used</span>';
                html += '</div>';
            }

            // Sequences list
            if (data.sequences && data.sequences.length > 0) {
                html += '<div class="sffc-crm-sequences-list">';
                data.sequences.forEach(function(seq) {
                    html += self.renderSequenceCard(seq);
                });
                html += '</div>';
            } else {
                html += '<div class="sffc-crm-empty-state">';
                html += '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>';
                html += '<h4>No Sequences Yet</h4>';
                html += '<p>Create your first outreach sequence to automate follow-ups with recruiters.</p>';
                if (data.can_create) {
                    html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-create-sequence-btn">Create First Sequence</button>';
                }
                html += '</div>';
            }

            html += '</div>';

            $panel.html(html);
            this.bindSequenceEvents();
        },

        /**
         * Render single sequence card
         */
        renderSequenceCard: function(seq) {
            var statusClass = seq.is_active ? 'active' : 'paused';
            var statusLabel = seq.is_active ? 'Active' : 'Paused';

            var html = '<div class="sffc-crm-sequence-card" data-sequence-id="' + seq.id + '">';

            html += '<div class="sffc-crm-sequence-card-header">';
            html += '<h4>' + this.escapeHtml(seq.name) + '</h4>';
            html += '<span class="sffc-crm-sequence-status sffc-crm-sequence-status-' + statusClass + '">' + statusLabel + '</span>';
            html += '</div>';

            if (seq.description) {
                html += '<p class="sffc-crm-sequence-description">' + this.escapeHtml(seq.description) + '</p>';
            }

            html += '<div class="sffc-crm-sequence-stats">';
            html += '<div class="sffc-crm-sequence-stat">';
            html += '<span class="sffc-crm-sequence-stat-value">' + (seq.step_count || 0) + '</span>';
            html += '<span class="sffc-crm-sequence-stat-label">Steps</span>';
            html += '</div>';
            html += '<div class="sffc-crm-sequence-stat">';
            html += '<span class="sffc-crm-sequence-stat-value">' + (seq.enrolled_count || 0) + '</span>';
            html += '<span class="sffc-crm-sequence-stat-label">Enrolled</span>';
            html += '</div>';
            html += '<div class="sffc-crm-sequence-stat">';
            html += '<span class="sffc-crm-sequence-stat-value">' + (seq.completed_count || 0) + '</span>';
            html += '<span class="sffc-crm-sequence-stat-label">Completed</span>';
            html += '</div>';
            html += '<div class="sffc-crm-sequence-stat">';
            html += '<span class="sffc-crm-sequence-stat-value">' + (seq.replied_count || 0) + '</span>';
            html += '<span class="sffc-crm-sequence-stat-label">Replied</span>';
            html += '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-sequence-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-secondary sffc-crm-edit-sequence-btn" data-id="' + seq.id + '">Edit</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-secondary sffc-crm-view-enrollments-btn" data-id="' + seq.id + '">View Enrollments</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-secondary sffc-crm-duplicate-sequence-btn" data-id="' + seq.id + '">Duplicate</button>';
            if (!seq.is_system) {
                html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-danger sffc-crm-delete-sequence-btn" data-id="' + seq.id + '">Delete</button>';
            }
            html += '</div>';

            html += '</div>';

            return html;
        },

        /**
         * Bind sequence events
         */
        bindSequenceEvents: function() {
            var self = this;

            $(document).off('click.sequences');

            $(document).on('click.sequences', '.sffc-crm-create-sequence-btn', function(e) {
                e.preventDefault();
                self.openSequenceBuilder();
            });

            $(document).on('click.sequences', '.sffc-crm-edit-sequence-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var seqId = $(this).data('id');
                self.openSequenceBuilder(seqId);
            });

            $(document).on('click.sequences', '.sffc-crm-duplicate-sequence-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var seqId = $(this).data('id');
                self.duplicateSequence(seqId);
            });

            $(document).on('click.sequences', '.sffc-crm-delete-sequence-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var seqId = $(this).data('id');
                if (confirm('Are you sure you want to delete this sequence? This will also remove all enrollments.')) {
                    self.deleteSequence(seqId);
                }
            });

            $(document).on('click.sequences', '.sffc-crm-view-enrollments-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var seqId = $(this).data('id');
                self.viewSequenceEnrollments(seqId);
            });

            $(document).on('click.sequences', '.sffc-crm-sequence-card', function(e) {
                if (!$(e.target).closest('button').length) {
                    var seqId = $(this).data('sequence-id');
                    self.openSequenceBuilder(seqId);
                }
            });
        },

        /**
         * Open sequence builder modal
         */
        openSequenceBuilder: function(sequenceId) {
            var self = this;

            if (sequenceId) {
                // Load existing sequence
                this.showModal('<div class="sffc-crm-modal-loading">Loading sequence...</div>');

                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_get_sequence',
                        sequence_id: sequenceId,
                        nonce: this.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.sequencesState.currentSequence = response.data.sequence;
                            self.sequencesState.currentSequence.steps = response.data.steps;
                            self.renderSequenceBuilder();
                        } else {
                            self.closeModal();
                            self.showError('Failed to load sequence');
                        }
                    }
                });
            } else {
                // New sequence
                this.sequencesState.currentSequence = {
                    id: null,
                    name: '',
                    description: '',
                    is_active: true,
                    steps: []
                };
                this.showModal('');
                this.renderSequenceBuilder();
            }
        },

        /**
         * Render sequence builder modal
         */
        renderSequenceBuilder: function() {
            var self = this;
            var seq = this.sequencesState.currentSequence;
            var isNew = !seq.id;

            var html = '<div class="sffc-crm-sequence-builder">';

            // Header
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>' + (isNew ? 'Create New Sequence' : 'Edit Sequence') + '</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            // Sequence info
            html += '<div class="sffc-crm-sequence-info">';
            html += '<div class="sffc-crm-form-group">';
            html += '<label>Sequence Name</label>';
            html += '<input type="text" class="sffc-crm-input" id="sequence-name" value="' + this.escapeHtml(seq.name) + '" placeholder="e.g., Standard Follow-up">';
            html += '</div>';
            html += '<div class="sffc-crm-form-group">';
            html += '<label>Description (optional)</label>';
            html += '<textarea class="sffc-crm-input" id="sequence-description" rows="2" placeholder="Brief description of this sequence">' + this.escapeHtml(seq.description || '') + '</textarea>';
            html += '</div>';
            html += '<div class="sffc-crm-form-group sffc-crm-form-inline">';
            html += '<label><input type="checkbox" id="sequence-active" ' + (seq.is_active ? 'checked' : '') + '> Active</label>';
            html += '</div>';
            html += '</div>';

            // Steps
            html += '<div class="sffc-crm-sequence-steps">';
            html += '<h4>Steps</h4>';
            html += '<div class="sffc-crm-steps-list" id="sequence-steps-list">';

            if (seq.steps && seq.steps.length > 0) {
                seq.steps.forEach(function(step, index) {
                    html += self.renderSequenceStep(step, index);
                });
            } else {
                html += '<div class="sffc-crm-steps-empty">No steps yet. Add your first step below.</div>';
            }

            html += '</div>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-add-step-btn">+ Add Step</button>';
            html += '</div>';

            // Footer
            html += '<div class="sffc-crm-modal-footer">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-modal-close">Cancel</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-save-sequence-btn">Save Sequence</button>';
            html += '</div>';

            html += '</div>';

            this.updateModalContent(html);
            this.bindSequenceBuilderEvents();
        },

        /**
         * Render single sequence step
         */
        renderSequenceStep: function(step, index) {
            var typeLabels = {
                'manual_email': 'Send Email',
                'linkedin_message': 'LinkedIn Message',
                'linkedin_connect': 'LinkedIn Connect',
                'task': 'Custom Task',
                'wait': 'Wait'
            };
            var channelIcons = {
                'email': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>',
                'linkedin': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
                'task': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'
            };

            var typeLabel = typeLabels[step.step_type] || step.step_type;
            var delay = '';
            if (step.delay_days > 0 || step.delay_hours > 0) {
                delay = 'After ';
                if (step.delay_days > 0) delay += step.delay_days + ' day' + (step.delay_days > 1 ? 's' : '');
                if (step.delay_hours > 0) delay += (step.delay_days > 0 ? ' ' : '') + step.delay_hours + ' hr' + (step.delay_hours > 1 ? 's' : '');
            } else if (index === 0) {
                delay = 'Immediately';
            }

            var html = '<div class="sffc-crm-step-item" data-step-id="' + (step.id || 'new-' + index) + '" data-index="' + index + '">';
            html += '<div class="sffc-crm-step-drag-handle">⠿</div>';
            html += '<div class="sffc-crm-step-number">' + (index + 1) + '</div>';
            html += '<div class="sffc-crm-step-content">';
            html += '<div class="sffc-crm-step-type">' + typeLabel + '</div>';
            if (delay) {
                html += '<div class="sffc-crm-step-delay">' + delay + '</div>';
            }
            if (step.subject) {
                html += '<div class="sffc-crm-step-subject">' + this.escapeHtml(step.subject) + '</div>';
            }
            html += '</div>';
            html += '<div class="sffc-crm-step-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-text sffc-crm-edit-step-btn" data-index="' + index + '">Edit</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-text sffc-crm-delete-step-btn" data-index="' + index + '">Delete</button>';
            html += '</div>';
            html += '</div>';

            return html;
        },

        /**
         * Bind sequence builder events
         */
        bindSequenceBuilderEvents: function() {
            var self = this;

            $(document).off('click.seqbuilder');

            $(document).on('click.seqbuilder', '.sffc-crm-add-step-btn', function(e) {
                e.preventDefault();
                self.openStepEditor();
            });

            $(document).on('click.seqbuilder', '.sffc-crm-edit-step-btn', function(e) {
                e.preventDefault();
                var index = $(this).data('index');
                self.openStepEditor(index);
            });

            $(document).on('click.seqbuilder', '.sffc-crm-delete-step-btn', function(e) {
                e.preventDefault();
                var index = $(this).data('index');
                if (confirm('Delete this step?')) {
                    self.deleteStep(index);
                }
            });

            $(document).on('click.seqbuilder', '.sffc-crm-save-sequence-btn', function(e) {
                e.preventDefault();
                self.saveSequence();
            });
        },

        /**
         * Open step editor
         */
        openStepEditor: function(stepIndex) {
            var self = this;
            var isNew = stepIndex === undefined;
            var step = isNew ? {
                step_type: 'manual_email',
                delay_days: isNew && this.sequencesState.currentSequence.steps.length === 0 ? 0 : 1,
                delay_hours: 0,
                channel: 'email',
                subject: '',
                content: '',
                task_title: '',
                task_description: '',
                skip_weekends: true,
                send_time_preference: 'morning'
            } : this.sequencesState.currentSequence.steps[stepIndex];

            var html = '<div class="sffc-crm-step-editor">';
            html += '<h4>' + (isNew ? 'Add Step' : 'Edit Step') + '</h4>';

            html += '<div class="sffc-crm-form-group">';
            html += '<label>Step Type</label>';
            html += '<select class="sffc-crm-input" id="step-type">';
            html += '<option value="manual_email" ' + (step.step_type === 'manual_email' ? 'selected' : '') + '>Send Email (opens email client)</option>';
            html += '<option value="linkedin_message" ' + (step.step_type === 'linkedin_message' ? 'selected' : '') + '>LinkedIn Message (copy to clipboard)</option>';
            html += '<option value="linkedin_connect" ' + (step.step_type === 'linkedin_connect' ? 'selected' : '') + '>LinkedIn Connection Request</option>';
            html += '<option value="task" ' + (step.step_type === 'task' ? 'selected' : '') + '>Custom Task</option>';
            html += '</select>';
            html += '</div>';

            html += '<div class="sffc-crm-form-row">';
            html += '<div class="sffc-crm-form-group">';
            html += '<label>Wait Days</label>';
            html += '<input type="number" class="sffc-crm-input" id="step-delay-days" value="' + (step.delay_days || 0) + '" min="0">';
            html += '</div>';
            html += '<div class="sffc-crm-form-group">';
            html += '<label>Wait Hours</label>';
            html += '<input type="number" class="sffc-crm-input" id="step-delay-hours" value="' + (step.delay_hours || 0) + '" min="0" max="23">';
            html += '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-step-email-fields" style="' + (step.step_type === 'task' ? 'display:none' : '') + '">';
            html += '<div class="sffc-crm-form-group">';
            html += '<label>Subject</label>';
            html += '<input type="text" class="sffc-crm-input" id="step-subject" value="' + this.escapeHtml(step.subject || '') + '" placeholder="Email subject line">';
            html += '</div>';
            html += '<div class="sffc-crm-form-group">';
            html += '<label>Message Content</label>';
            html += '<textarea class="sffc-crm-input" id="step-content" rows="6" placeholder="Use {{recruiter_first_name}}, {{candidate_name}}, etc.">' + this.escapeHtml(step.content || '') + '</textarea>';
            html += '<small class="sffc-crm-form-help">Available: {{recruiter_name}}, {{recruiter_first_name}}, {{recruiter_company}}, {{candidate_name}}, {{candidate_first_name}}</small>';
            html += '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-step-task-fields" style="' + (step.step_type !== 'task' ? 'display:none' : '') + '">';
            html += '<div class="sffc-crm-form-group">';
            html += '<label>Task Title</label>';
            html += '<input type="text" class="sffc-crm-input" id="step-task-title" value="' + this.escapeHtml(step.task_title || '') + '" placeholder="e.g., Research their company">';
            html += '</div>';
            html += '<div class="sffc-crm-form-group">';
            html += '<label>Task Description</label>';
            html += '<textarea class="sffc-crm-input" id="step-task-description" rows="3">' + this.escapeHtml(step.task_description || '') + '</textarea>';
            html += '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-form-group sffc-crm-form-inline">';
            html += '<label><input type="checkbox" id="step-skip-weekends" ' + (step.skip_weekends ? 'checked' : '') + '> Skip weekends</label>';
            html += '</div>';

            html += '<div class="sffc-crm-step-editor-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-cancel-step-btn">Cancel</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-confirm-step-btn" data-index="' + (isNew ? 'new' : stepIndex) + '">Save Step</button>';
            html += '</div>';

            html += '</div>';

            // Show in modal overlay
            var $stepEditor = $('<div class="sffc-crm-step-editor-overlay">' + html + '</div>');
            $('body').append($stepEditor);

            // Toggle fields based on type
            $('#step-type').on('change', function() {
                var type = $(this).val();
                if (type === 'task') {
                    $('.sffc-crm-step-email-fields').hide();
                    $('.sffc-crm-step-task-fields').show();
                } else {
                    $('.sffc-crm-step-email-fields').show();
                    $('.sffc-crm-step-task-fields').hide();
                }
            });

            // Cancel
            $('.sffc-crm-cancel-step-btn').on('click', function() {
                $stepEditor.remove();
            });

            // Save
            $('.sffc-crm-confirm-step-btn').on('click', function() {
                var idx = $(this).data('index');
                self.saveStep(idx === 'new' ? null : idx);
                $stepEditor.remove();
            });
        },

        /**
         * Save step to current sequence
         */
        saveStep: function(stepIndex) {
            var step = {
                step_type: $('#step-type').val(),
                delay_days: parseInt($('#step-delay-days').val()) || 0,
                delay_hours: parseInt($('#step-delay-hours').val()) || 0,
                channel: $('#step-type').val().includes('linkedin') ? 'linkedin' : 'email',
                subject: $('#step-subject').val(),
                content: $('#step-content').val(),
                task_title: $('#step-task-title').val(),
                task_description: $('#step-task-description').val(),
                skip_weekends: $('#step-skip-weekends').is(':checked'),
                send_time_preference: 'morning'
            };

            if (stepIndex === null) {
                this.sequencesState.currentSequence.steps.push(step);
            } else {
                this.sequencesState.currentSequence.steps[stepIndex] = step;
            }

            this.renderSequenceBuilder();
        },

        /**
         * Delete step from sequence
         */
        deleteStep: function(stepIndex) {
            this.sequencesState.currentSequence.steps.splice(stepIndex, 1);
            this.renderSequenceBuilder();
        },

        /**
         * Save sequence
         */
        saveSequence: function() {
            var self = this;
            var seq = this.sequencesState.currentSequence;

            var name = $('#sequence-name').val().trim();
            if (!name) {
                this.showError('Please enter a sequence name');
                return;
            }

            var data = {
                action: seq.id ? 'sffc_crm_update_sequence' : 'sffc_crm_create_sequence',
                nonce: this.config.nonce,
                name: name,
                description: $('#sequence-description').val(),
                is_active: $('#sequence-active').is(':checked') ? 1 : 0
            };

            if (seq.id) {
                data.sequence_id = seq.id;
            }

            this.showLoading('Saving...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        var sequenceId = response.data.sequence.id;

                        // Save steps
                        self.saveSequenceSteps(sequenceId, seq.steps);
                    } else {
                        self.hideLoading();
                        self.showError(response.data.message || 'Failed to save sequence');
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showError('Failed to save sequence');
                }
            });
        },

        /**
         * Save sequence steps
         */
        saveSequenceSteps: function(sequenceId, steps) {
            var self = this;
            var savedCount = 0;

            if (steps.length === 0) {
                this.hideLoading();
                this.closeModal();
                this.showSuccess('Sequence saved');
                this.loadSequences();
                return;
            }

            steps.forEach(function(step, index) {
                var stepData = {
                    action: 'sffc_crm_add_sequence_step',
                    nonce: self.config.nonce,
                    sequence_id: sequenceId,
                    step_type: step.step_type,
                    delay_days: step.delay_days,
                    delay_hours: step.delay_hours,
                    channel: step.channel,
                    subject: step.subject,
                    content: step.content,
                    task_title: step.task_title,
                    task_description: step.task_description,
                    skip_weekends: step.skip_weekends ? 1 : 0,
                    send_time_preference: step.send_time_preference,
                    order: index
                };

                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: stepData,
                    success: function() {
                        savedCount++;
                        if (savedCount === steps.length) {
                            self.hideLoading();
                            self.closeModal();
                            self.showSuccess('Sequence saved');
                            self.loadSequences();
                        }
                    }
                });
            });
        },

        /**
         * Delete sequence
         */
        deleteSequence: function(sequenceId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_delete_sequence',
                    sequence_id: sequenceId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Sequence deleted');
                        self.loadSequences();
                    } else {
                        self.showError(response.data.message || 'Failed to delete sequence');
                    }
                }
            });
        },

        /**
         * Duplicate sequence
         */
        duplicateSequence: function(sequenceId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_duplicate_sequence',
                    sequence_id: sequenceId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Sequence duplicated');
                        self.loadSequences();
                    } else {
                        self.showError(response.data.message || 'Failed to duplicate sequence');
                    }
                }
            });
        },

        /**
         * View sequence enrollments
         */
        viewSequenceEnrollments: function(sequenceId) {
            var self = this;

            this.showModal('<div class="sffc-crm-modal-loading">Loading enrollments...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_enrollments',
                    sequence_id: sequenceId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderEnrollmentsList(response.data.enrollments, sequenceId);
                    } else {
                        self.closeModal();
                        self.showError('Failed to load enrollments');
                    }
                }
            });
        },

        /**
         * Render enrollments list
         */
        renderEnrollmentsList: function(enrollments, sequenceId) {
            var self = this;

            var html = '<div class="sffc-crm-enrollments-modal">';
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Sequence Enrollments</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            if (enrollments.length === 0) {
                html += '<div class="sffc-crm-empty-state sffc-crm-empty-small">';
                html += '<p>No recruiters enrolled in this sequence yet.</p>';
                html += '</div>';
            } else {
                html += '<div class="sffc-crm-enrollments-list">';
                enrollments.forEach(function(enroll) {
                    var statusClass = 'status-' + enroll.status;
                    html += '<div class="sffc-crm-enrollment-item ' + statusClass + '" data-enrollment-id="' + enroll.id + '">';
                    html += '<div class="sffc-crm-enrollment-info">';
                    html += '<strong>' + self.escapeHtml(enroll.recruiter_name || 'Unknown') + '</strong>';
                    html += '<span class="sffc-crm-enrollment-step">Step ' + (parseInt(enroll.current_step_index) + 1) + '</span>';
                    html += '<span class="sffc-crm-enrollment-status">' + enroll.status + '</span>';
                    html += '</div>';
                    html += '<div class="sffc-crm-enrollment-actions">';
                    if (enroll.status === 'active') {
                        html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-text sffc-crm-pause-enrollment-btn" data-id="' + enroll.id + '">Pause</button>';
                    } else if (enroll.status === 'paused') {
                        html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-text sffc-crm-resume-enrollment-btn" data-id="' + enroll.id + '">Resume</button>';
                    }
                    html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-text sffc-crm-remove-enrollment-btn" data-id="' + enroll.id + '">Remove</button>';
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';
            }

            html += '</div>';

            this.updateModalContent(html);

            // Bind events
            $(document).off('click.enrollments');
            $(document).on('click.enrollments', '.sffc-crm-pause-enrollment-btn', function(e) {
                e.preventDefault();
                self.pauseEnrollment($(this).data('id'), sequenceId);
            });
            $(document).on('click.enrollments', '.sffc-crm-resume-enrollment-btn', function(e) {
                e.preventDefault();
                self.resumeEnrollment($(this).data('id'), sequenceId);
            });
            $(document).on('click.enrollments', '.sffc-crm-remove-enrollment-btn', function(e) {
                e.preventDefault();
                if (confirm('Remove this recruiter from the sequence?')) {
                    self.removeEnrollment($(this).data('id'), sequenceId);
                }
            });
        },

        pauseEnrollment: function(enrollmentId, sequenceId) {
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_pause_enrollment',
                    enrollment_id: enrollmentId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Enrollment paused');
                        self.viewSequenceEnrollments(sequenceId);
                    }
                }
            });
        },

        resumeEnrollment: function(enrollmentId, sequenceId) {
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_resume_enrollment',
                    enrollment_id: enrollmentId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Enrollment resumed');
                        self.viewSequenceEnrollments(sequenceId);
                    }
                }
            });
        },

        removeEnrollment: function(enrollmentId, sequenceId) {
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_remove_enrollment',
                    enrollment_id: enrollmentId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Enrollment removed');
                        self.viewSequenceEnrollments(sequenceId);
                    }
                }
            });
        },

        // ============================================
        // PHASE 4: ENROLLMENT FROM RECRUITER CARDS
        // ============================================

        /**
         * Open enrollment modal to select a sequence
         */
        openEnrollmentModal: function(recruiterId, recruiterName) {
            var self = this;

            // First, load available sequences
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_sequences',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderEnrollmentModal(recruiterId, recruiterName, response.data.sequences);
                    } else {
                        self.showError('Failed to load sequences');
                    }
                }
            });
        },

        /**
         * Render enrollment modal
         */
        renderEnrollmentModal: function(recruiterId, recruiterName, sequences) {
            var self = this;

            var html = '<div class="sffc-crm-enroll-modal">';
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Enroll ' + this.escapeHtml(recruiterName) + ' in Sequence</h3>';
            html += '<button class="sffc-crm-modal-close">&times;</button>';
            html += '</div>';
            html += '<div class="sffc-crm-modal-body">';

            if (sequences.length === 0) {
                html += '<div class="sffc-crm-empty-state">';
                html += '<p>You haven\'t created any sequences yet.</p>';
                html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="goto-sequences">Create a Sequence</button>';
                html += '</div>';
            } else {
                html += '<p class="sffc-crm-enroll-intro">Select a sequence to enroll this recruiter:</p>';
                html += '<div class="sffc-crm-sequence-select-list">';

                sequences.forEach(function(seq) {
                    html += '<div class="sffc-crm-sequence-select-item" data-sequence-id="' + seq.id + '">';
                    html += '<div class="sffc-crm-sequence-select-info">';
                    html += '<span class="sffc-crm-sequence-select-name">' + self.escapeHtml(seq.name) + '</span>';
                    html += '<span class="sffc-crm-sequence-select-steps">' + (seq.step_count || 0) + ' steps</span>';
                    html += '</div>';
                    if (seq.description) {
                        html += '<p class="sffc-crm-sequence-select-desc">' + self.escapeHtml(seq.description) + '</p>';
                    }
                    html += '</div>';
                });

                html += '</div>';
            }

            html += '</div>';
            html += '</div>';

            this.showModal(html);

            // Bind events
            $('.sffc-crm-sequence-select-item').on('click', function() {
                var sequenceId = $(this).data('sequence-id');
                self.enrollRecruiterInSequence(recruiterId, sequenceId, recruiterName);
            });

            $('#goto-sequences').on('click', function() {
                self.closeModal();
                self.switchTab('sequences');
            });
        },

        /**
         * Enroll recruiter in selected sequence
         */
        enrollRecruiterInSequence: function(recruiterId, sequenceId, recruiterName) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_enroll_recruiter',
                    recruiter_id: recruiterId,
                    sequence_id: sequenceId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.closeModal();
                        self.showSuccess((recruiterName || 'Recruiter') + ' enrolled successfully');

                        // Refresh recruiters list if on that tab
                        if (self.currentTab === 'recruiters') {
                            self.loadRecruitersEnhanced();
                        }
                    } else {
                        self.showError(response.data || 'Failed to enroll recruiter');
                    }
                },
                error: function() {
                    self.showError('Failed to enroll recruiter');
                }
            });
        },

        /**
         * Pause enrollment from recruiter detail
         */
        pauseEnrollmentFromDetail: function(enrollmentId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_pause_enrollment',
                    enrollment_id: enrollmentId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Enrollment paused');
                        // Refresh the detail view
                        var recruiterId = $('.sffc-crm-recruiter-detail').find('[data-recruiter-id]').first().data('recruiter-id');
                        if (recruiterId) {
                            self.viewRecruiterEnhanced(recruiterId);
                        }
                    } else {
                        self.showError(response.data || 'Failed to pause enrollment');
                    }
                }
            });
        },

        /**
         * Resume enrollment from recruiter detail
         */
        resumeEnrollmentFromDetail: function(enrollmentId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_resume_enrollment',
                    enrollment_id: enrollmentId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Enrollment resumed');
                        // Refresh the detail view
                        var recruiterId = $('.sffc-crm-recruiter-detail').find('[data-recruiter-id]').first().data('recruiter-id');
                        if (recruiterId) {
                            self.viewRecruiterEnhanced(recruiterId);
                        }
                    } else {
                        self.showError(response.data || 'Failed to resume enrollment');
                    }
                }
            });
        },

        /**
         * Remove enrollment from recruiter detail
         */
        removeEnrollmentFromDetail: function(enrollmentId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_remove_enrollment',
                    enrollment_id: enrollmentId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Enrollment removed');
                        // Refresh the detail view
                        var recruiterId = $('.sffc-crm-recruiter-detail').find('[data-recruiter-id]').first().data('recruiter-id');
                        if (recruiterId) {
                            self.viewRecruiterEnhanced(recruiterId);
                        }
                    } else {
                        self.showError(response.data || 'Failed to remove enrollment');
                    }
                }
            });
        },

        // ============================================
        // PHASE 4: TASKS
        // ============================================

        /**
         * Load tasks tab
         */
        loadTasks: function() {
            var self = this;
            var $panel = $('#panel-tasks');

            $panel.html('<div class="sffc-crm-loading">Loading tasks...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_tasks',
                    status: this.tasksState.filter === 'all' ? null : this.tasksState.filter,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.tasksState.tasks = response.data.tasks;
                        self.tasksState.counts = response.data.counts;
                        self.renderTasksList();
                    } else {
                        $panel.html('<div class="sffc-crm-error">Failed to load tasks</div>');
                    }
                }
            });
        },

        /**
         * Render tasks list
         */
        renderTasksList: function() {
            var self = this;
            var $panel = $('#panel-tasks');
            var tasks = this.tasksState.tasks;
            var counts = this.tasksState.counts;

            var html = '<div class="sffc-crm-tasks-container">';

            // Header with counts
            html += '<div class="sffc-crm-tasks-header">';
            html += '<h3>Tasks</h3>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-create-task-btn">+ New Task</button>';
            html += '</div>';

            // Filters
            html += '<div class="sffc-crm-tasks-filters">';
            html += '<button class="sffc-crm-task-filter ' + (this.tasksState.filter === 'pending' ? 'active' : '') + '" data-filter="pending">Pending (' + (counts.pending || 0) + ')</button>';
            html += '<button class="sffc-crm-task-filter ' + (this.tasksState.filter === 'overdue' ? 'active' : '') + '" data-filter="overdue">Overdue (' + (counts.overdue || 0) + ')</button>';
            html += '<button class="sffc-crm-task-filter ' + (this.tasksState.filter === 'today' ? 'active' : '') + '" data-filter="today">Due Today (' + (counts.due_today || 0) + ')</button>';
            html += '<button class="sffc-crm-task-filter ' + (this.tasksState.filter === 'completed' ? 'active' : '') + '" data-filter="completed">Completed (' + (counts.completed || 0) + ')</button>';
            html += '<button class="sffc-crm-task-filter ' + (this.tasksState.filter === 'all' ? 'active' : '') + '" data-filter="all">All (' + (counts.total || 0) + ')</button>';
            html += '</div>';

            // Tasks list
            if (tasks && tasks.length > 0) {
                html += '<div class="sffc-crm-tasks-list">';
                tasks.forEach(function(task) {
                    html += self.renderTaskItem(task);
                });
                html += '</div>';
            } else {
                html += '<div class="sffc-crm-empty-state">';
                html += '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>';
                html += '<h4>No Tasks</h4>';
                html += '<p>You\'re all caught up! Create a task or enroll recruiters in sequences to generate tasks.</p>';
                html += '</div>';
            }

            html += '</div>';

            $panel.html(html);
            this.bindTaskEvents();
        },

        /**
         * Render single task item
         */
        renderTaskItem: function(task) {
            var priorityColors = {
                low: '#64748b',
                medium: '#3b82f6',
                high: '#f59e0b',
                urgent: '#ef4444'
            };
            var typeIcons = {
                send_email: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>',
                linkedin_message: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
                linkedin_connect: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>',
                follow_up: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>',
                research: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                interview_prep: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
                custom: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>'
            };

            var isOverdue = task.is_overdue;
            var statusClass = task.status + (isOverdue ? ' overdue' : '');

            var html = '<div class="sffc-crm-task-item sffc-crm-task-' + statusClass + '" data-task-id="' + task.id + '">';

            html += '<div class="sffc-crm-task-checkbox">';
            if (task.status !== 'completed' && task.status !== 'skipped') {
                html += '<button class="sffc-crm-complete-task-btn" data-id="' + task.id + '" title="Mark complete">';
                html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>';
                html += '</button>';
            } else {
                html += '<span class="sffc-crm-task-done"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></span>';
            }
            html += '</div>';

            html += '<div class="sffc-crm-task-main">';
            html += '<div class="sffc-crm-task-title-row">';
            html += '<span class="sffc-crm-task-type-icon">' + (typeIcons[task.task_type] || typeIcons.custom) + '</span>';
            html += '<span class="sffc-crm-task-title">' + this.escapeHtml(task.title) + '</span>';
            html += '<span class="sffc-crm-task-priority" style="background: ' + priorityColors[task.priority] + '">' + task.priority + '</span>';
            html += '</div>';

            if (task.recruiter_name) {
                html += '<div class="sffc-crm-task-recruiter">' + this.escapeHtml(task.recruiter_name);
                if (task.recruiter_company) html += ' at ' + this.escapeHtml(task.recruiter_company);
                html += '</div>';
            }

            html += '<div class="sffc-crm-task-due">';
            if (isOverdue) {
                html += '<span class="sffc-crm-task-overdue-label">OVERDUE</span> ';
            }
            html += task.due_label || 'No due date';
            html += '</div>';

            html += '</div>';

            html += '<div class="sffc-crm-task-actions">';
            if (task.status !== 'completed' && task.status !== 'skipped') {
                if (task.pre_filled_content) {
                    html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-primary sffc-crm-execute-task-btn" data-id="' + task.id + '">Execute</button>';
                }
                html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-secondary sffc-crm-snooze-task-btn" data-id="' + task.id + '">Snooze</button>';
                html += '<button class="sffc-crm-btn sffc-crm-btn-small sffc-crm-btn-text sffc-crm-skip-task-btn" data-id="' + task.id + '">Skip</button>';
            }
            html += '</div>';

            html += '</div>';

            return html;
        },

        /**
         * Bind task events
         */
        bindTaskEvents: function() {
            var self = this;

            $(document).off('click.tasks');

            $(document).on('click.tasks', '.sffc-crm-task-filter', function(e) {
                e.preventDefault();
                var filter = $(this).data('filter');
                self.tasksState.filter = filter;

                // Update special filters
                var ajaxData = {
                    action: 'sffc_crm_get_tasks',
                    nonce: self.config.nonce
                };

                if (filter === 'overdue') {
                    ajaxData.overdue_only = 1;
                } else if (filter === 'today') {
                    ajaxData.today_only = 1;
                } else if (filter !== 'all') {
                    ajaxData.status = filter;
                }

                $('#panel-tasks').html('<div class="sffc-crm-loading">Loading tasks...</div>');

                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: ajaxData,
                    success: function(response) {
                        if (response.success) {
                            self.tasksState.tasks = response.data.tasks;
                            self.tasksState.counts = response.data.counts;
                            self.renderTasksList();
                        }
                    }
                });
            });

            $(document).on('click.tasks', '.sffc-crm-complete-task-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.completeTask($(this).data('id'));
            });

            $(document).on('click.tasks', '.sffc-crm-skip-task-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.skipTask($(this).data('id'));
            });

            $(document).on('click.tasks', '.sffc-crm-snooze-task-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openSnoozeMenu($(this).data('id'), $(this));
            });

            $(document).on('click.tasks', '.sffc-crm-execute-task-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.executeTask($(this).data('id'));
            });

            $(document).on('click.tasks', '.sffc-crm-create-task-btn', function(e) {
                e.preventDefault();
                self.openCreateTaskModal();
            });
        },

        /**
         * Complete task
         */
        completeTask: function(taskId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_complete_task',
                    task_id: taskId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Task completed');
                        self.tasksState.counts = response.data.counts;
                        // Animate removal
                        $('.sffc-crm-task-item[data-task-id="' + taskId + '"]').fadeOut(300, function() {
                            $(this).remove();
                            if ($('.sffc-crm-tasks-list .sffc-crm-task-item').length === 0) {
                                self.loadTasks();
                            }
                        });
                    }
                }
            });
        },

        /**
         * Skip task
         */
        skipTask: function(taskId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_skip_task',
                    task_id: taskId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Task skipped');
                        self.tasksState.counts = response.data.counts;
                        $('.sffc-crm-task-item[data-task-id="' + taskId + '"]').fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                }
            });
        },

        /**
         * Open snooze menu
         */
        openSnoozeMenu: function(taskId, $btn) {
            var self = this;
            var $menu = $('<div class="sffc-crm-snooze-menu">' +
                '<button data-snooze="+1 hour">1 hour</button>' +
                '<button data-snooze="+3 hours">3 hours</button>' +
                '<button data-snooze="tomorrow 9am">Tomorrow</button>' +
                '<button data-snooze="+2 days">2 days</button>' +
                '<button data-snooze="+1 week">1 week</button>' +
                '</div>');

            $btn.after($menu);

            $menu.find('button').on('click', function() {
                var snooze = $(this).data('snooze');
                self.snoozeTask(taskId, snooze);
                $menu.remove();
            });

            // Close on click outside
            $(document).one('click', function() {
                $menu.remove();
            });
        },

        /**
         * Snooze task
         */
        snoozeTask: function(taskId, snoozeTime) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_snooze_task',
                    task_id: taskId,
                    snooze_to: snoozeTime,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Task snoozed');
                        self.loadTasks();
                    }
                }
            });
        },

        /**
         * Execute task (open email/copy message)
         */
        executeTask: function(taskId) {
            var self = this;

            // Get task details
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_task',
                    task_id: taskId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var task = response.data.task;

                        if (task.task_type === 'send_email' && task.recruiter_email) {
                            // Open mailto
                            self.openMailto(task.recruiter_email, task.pre_filled_subject, task.pre_filled_content);
                            self.completeTask(taskId);
                        } else if (task.task_type === 'linkedin_message' || task.task_type === 'linkedin_connect') {
                            // Copy to clipboard
                            navigator.clipboard.writeText(task.pre_filled_content).then(function() {
                                self.showSuccess('Message copied to clipboard!');
                                self.completeTask(taskId);
                            });
                        } else {
                            // Show task content
                            self.showTaskContent(task);
                        }
                    }
                }
            });
        },

        /**
         * Show task content in modal
         */
        showTaskContent: function(task) {
            var html = '<div class="sffc-crm-task-content-modal">';
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>' + this.escapeHtml(task.title) + '</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            if (task.description) {
                html += '<p>' + this.escapeHtml(task.description) + '</p>';
            }

            if (task.pre_filled_content) {
                html += '<div class="sffc-crm-task-prefilled">';
                if (task.pre_filled_subject) {
                    html += '<div class="sffc-crm-task-subject"><strong>Subject:</strong> ' + this.escapeHtml(task.pre_filled_subject) + '</div>';
                }
                html += '<div class="sffc-crm-task-body">' + this.escapeHtml(task.pre_filled_content) + '</div>';
                html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-copy-task-content">Copy to Clipboard</button>';
                html += '</div>';
            }

            html += '</div>';

            this.showModal(html);

            var self = this;
            $(document).on('click.taskcontent', '.sffc-crm-copy-task-content', function() {
                navigator.clipboard.writeText(task.pre_filled_content).then(function() {
                    self.showSuccess('Content copied!');
                });
            });
        },

        /**
         * Open create task modal
         */
        openCreateTaskModal: function() {
            var self = this;

            var html = '<div class="sffc-crm-create-task-modal">';
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Create Task</h3>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            html += '<div class="sffc-crm-form-group">';
            html += '<label>Title</label>';
            html += '<input type="text" class="sffc-crm-input" id="new-task-title" placeholder="Task title">';
            html += '</div>';

            html += '<div class="sffc-crm-form-group">';
            html += '<label>Type</label>';
            html += '<select class="sffc-crm-input" id="new-task-type">';
            html += '<option value="follow_up">Follow Up</option>';
            html += '<option value="send_email">Send Email</option>';
            html += '<option value="linkedin_message">LinkedIn Message</option>';
            html += '<option value="linkedin_connect">LinkedIn Connect</option>';
            html += '<option value="research">Research</option>';
            html += '<option value="interview_prep">Interview Prep</option>';
            html += '<option value="custom">Other</option>';
            html += '</select>';
            html += '</div>';

            html += '<div class="sffc-crm-form-group">';
            html += '<label>Due Date</label>';
            html += '<input type="datetime-local" class="sffc-crm-input" id="new-task-due">';
            html += '</div>';

            html += '<div class="sffc-crm-form-group">';
            html += '<label>Priority</label>';
            html += '<select class="sffc-crm-input" id="new-task-priority">';
            html += '<option value="low">Low</option>';
            html += '<option value="medium" selected>Medium</option>';
            html += '<option value="high">High</option>';
            html += '<option value="urgent">Urgent</option>';
            html += '</select>';
            html += '</div>';

            html += '<div class="sffc-crm-form-group">';
            html += '<label>Description (optional)</label>';
            html += '<textarea class="sffc-crm-input" id="new-task-description" rows="3"></textarea>';
            html += '</div>';

            html += '<div class="sffc-crm-modal-footer">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-modal-close">Cancel</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-save-new-task-btn">Create Task</button>';
            html += '</div>';

            html += '</div>';

            this.showModal(html);

            $(document).on('click.newtask', '.sffc-crm-save-new-task-btn', function() {
                self.createTask();
            });
        },

        /**
         * Create task
         */
        createTask: function() {
            var self = this;
            var title = $('#new-task-title').val().trim();

            if (!title) {
                this.showError('Please enter a task title');
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_create_task',
                    title: title,
                    type: $('#new-task-type').val(),
                    due_date: $('#new-task-due').val(),
                    priority: $('#new-task-priority').val(),
                    description: $('#new-task-description').val(),
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.closeModal();
                        self.showSuccess('Task created');
                        self.loadTasks();
                    } else {
                        self.showError(response.data.message || 'Failed to create task');
                    }
                }
            });
        },

        // ============================================
        // PHASE 5: Inbox/Conversations
        // ============================================

        /**
         * Load inbox tab
         */
        loadInbox: function() {
            var self = this;
            var $panel = $('#panel-inbox');

            $panel.html('<div class="sffc-crm-loading">Loading inbox...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_conversations',
                    status: this.inboxState.filter === 'archived' ? 'archived' : 'active',
                    starred: this.inboxState.filter === 'starred' ? '1' : '',
                    is_read: this.inboxState.filter === 'unread' ? 0 : null,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.inboxState.conversations = response.data.conversations;
                        self.inboxState.counts = response.data.counts;
                        self.renderInboxList();
                    } else {
                        $panel.html('<div class="sffc-crm-error">Failed to load inbox</div>');
                    }
                }
            });
        },

        /**
         * Render inbox conversation list
         */
        renderInboxList: function() {
            var self = this;
            var $panel = $('#panel-inbox');
            var conversations = this.inboxState.conversations;
            var counts = this.inboxState.counts;

            var html = '<div class="sffc-crm-inbox-container">';

            // Header
            html += '<div class="sffc-crm-inbox-header">';
            html += '<h3>Inbox</h3>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-compose-btn">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>';
            html += ' Compose';
            html += '</button>';
            html += '</div>';

            // Filters
            html += '<div class="sffc-crm-inbox-filters">';
            html += '<button class="sffc-crm-inbox-filter ' + (this.inboxState.filter === 'active' ? 'active' : '') + '" data-filter="active">';
            html += 'All (' + (counts.total || 0) + ')';
            html += '</button>';
            html += '<button class="sffc-crm-inbox-filter ' + (this.inboxState.filter === 'unread' ? 'active' : '') + '" data-filter="unread">';
            html += 'Unread (' + (counts.unread || 0) + ')';
            html += '</button>';
            html += '<button class="sffc-crm-inbox-filter ' + (this.inboxState.filter === 'starred' ? 'active' : '') + '" data-filter="starred">';
            html += 'Starred (' + (counts.starred || 0) + ')';
            html += '</button>';
            html += '<button class="sffc-crm-inbox-filter ' + (this.inboxState.filter === 'archived' ? 'active' : '') + '" data-filter="archived">';
            html += 'Archived (' + (counts.archived || 0) + ')';
            html += '</button>';
            html += '</div>';

            // Search
            html += '<div class="sffc-crm-inbox-search">';
            html += '<input type="text" id="inbox-search" class="sffc-crm-input" placeholder="Search conversations...">';
            html += '</div>';

            // Conversation list
            if (conversations && conversations.length > 0) {
                html += '<div class="sffc-crm-conversation-list">';
                conversations.forEach(function(conv) {
                    html += self.renderConversationItem(conv);
                });
                html += '</div>';
            } else {
                html += '<div class="sffc-crm-empty-state">';
                html += '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">';
                html += '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>';
                html += '<polyline points="22,6 12,13 2,6"></polyline>';
                html += '</svg>';
                html += '<h4>No conversations</h4>';
                html += '<p>Start a conversation with a recruiter to track your communication here.</p>';
                html += '</div>';
            }

            html += '</div>';

            $panel.html(html);
            this.bindInboxEvents();
        },

        /**
         * Render single conversation item
         */
        renderConversationItem: function(conv) {
            var isUnread = !conv.is_read;
            var isStarred = conv.is_starred;
            var recruiterName = conv.recruiter ? conv.recruiter.name : 'Unknown';
            var recruiterFirm = conv.recruiter ? conv.recruiter.firm : '';
            var snippet = conv.latest_message ? conv.latest_message.snippet : 'No messages yet';
            var lastTime = conv.last_message_at ? this.formatRelativeTime(conv.last_message_at) : '';

            var html = '<div class="sffc-crm-conversation-item' + (isUnread ? ' unread' : '') + '" data-id="' + conv.id + '">';

            // Star button
            html += '<button class="sffc-crm-star-btn' + (isStarred ? ' starred' : '') + '" data-id="' + conv.id + '">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="' + (isStarred ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2">';
            html += '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>';
            html += '</svg>';
            html += '</button>';

            // Conversation content
            html += '<div class="sffc-crm-conversation-content">';

            // Header row
            html += '<div class="sffc-crm-conversation-header">';
            html += '<span class="sffc-crm-conversation-name">' + this.escapeHtml(recruiterName) + '</span>';
            if (recruiterFirm) {
                html += '<span class="sffc-crm-conversation-firm">' + this.escapeHtml(recruiterFirm) + '</span>';
            }
            html += '<span class="sffc-crm-conversation-time">' + lastTime + '</span>';
            html += '</div>';

            // Subject
            if (conv.subject) {
                html += '<div class="sffc-crm-conversation-subject">' + this.escapeHtml(conv.subject) + '</div>';
            }

            // Snippet
            html += '<div class="sffc-crm-conversation-snippet">';
            if (conv.latest_message && conv.latest_message.direction === 'outbound') {
                html += '<span class="sffc-crm-direction-indicator">You: </span>';
            }
            html += this.escapeHtml(snippet);
            html += '</div>';

            html += '</div>'; // content

            // Channel indicator
            html += '<div class="sffc-crm-conversation-channel">';
            if (conv.channel === 'linkedin') {
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';
            } else {
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
            }
            html += '</div>';

            // Unread indicator
            if (isUnread) {
                html += '<div class="sffc-crm-unread-dot"></div>';
            }

            html += '</div>';

            return html;
        },

        /**
         * Bind inbox events
         */
        bindInboxEvents: function() {
            var self = this;

            // Filter buttons
            $(document).off('click.inboxfilter').on('click.inboxfilter', '.sffc-crm-inbox-filter', function() {
                var filter = $(this).data('filter');
                self.inboxState.filter = filter;
                self.loadInbox();
            });

            // Click conversation to view thread
            $(document).off('click.viewthread').on('click.viewthread', '.sffc-crm-conversation-item', function(e) {
                if ($(e.target).closest('.sffc-crm-star-btn').length) return;
                var convId = $(this).data('id');
                self.viewConversationThread(convId);
            });

            // Star conversation
            $(document).off('click.starconv').on('click.starconv', '.sffc-crm-star-btn', function(e) {
                e.stopPropagation();
                var convId = $(this).data('id');
                self.toggleConversationStar(convId);
            });

            // Compose button
            $(document).off('click.compose').on('click.compose', '.sffc-crm-compose-btn', function() {
                self.showComposeModal();
            });

            // Search
            var searchTimeout;
            $(document).off('input.inboxsearch').on('input.inboxsearch', '#inbox-search', function() {
                clearTimeout(searchTimeout);
                var query = $(this).val().trim();
                searchTimeout = setTimeout(function() {
                    if (query.length >= 2) {
                        self.searchInbox(query);
                    } else {
                        self.loadInbox();
                    }
                }, 300);
            });
        },

        /**
         * View conversation thread
         */
        viewConversationThread: function(convId) {
            var self = this;
            var $panel = $('#panel-inbox');

            $panel.html('<div class="sffc-crm-loading">Loading conversation...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_conversation',
                    conversation_id: convId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.inboxState.currentConversation = response.data.conversation;
                        self.inboxState.currentMessages = response.data.messages;
                        self.inboxState.viewingThread = true;
                        self.renderThreadView(response.data);
                    } else {
                        self.showError('Failed to load conversation');
                        self.loadInbox();
                    }
                }
            });
        },

        /**
         * Render thread view
         */
        renderThreadView: function(data) {
            var self = this;
            var $panel = $('#panel-inbox');
            var conv = data.conversation;
            var messages = data.messages;
            var recruiter = data.recruiter;

            var recruiterName = recruiter ? recruiter.name : 'Unknown';
            var recruiterFirm = recruiter ? (recruiter.firm || '') : '';

            var html = '<div class="sffc-crm-thread-container">';

            // Thread header
            html += '<div class="sffc-crm-thread-header">';
            html += '<button class="sffc-crm-back-btn">';
            html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>';
            html += '</button>';
            html += '<div class="sffc-crm-thread-title">';
            html += '<h3>' + this.escapeHtml(recruiterName) + '</h3>';
            if (recruiterFirm) {
                html += '<span class="sffc-crm-thread-subtitle">' + this.escapeHtml(recruiterFirm) + '</span>';
            }
            html += '</div>';
            html += '<div class="sffc-crm-thread-actions">';
            html += '<button class="sffc-crm-thread-action sffc-crm-star-thread-btn' + (conv.is_starred ? ' starred' : '') + '" data-id="' + conv.id + '" title="Star">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="' + (conv.is_starred ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
            html += '</button>';
            html += '<button class="sffc-crm-thread-action sffc-crm-archive-btn" data-id="' + conv.id + '" title="Archive">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>';
            html += '</button>';
            html += '</div>';
            html += '</div>';

            // Messages
            html += '<div class="sffc-crm-messages-container">';
            if (messages && messages.length > 0) {
                messages.forEach(function(msg) {
                    html += self.renderMessageBubble(msg);
                });
            } else {
                html += '<div class="sffc-crm-empty-messages">';
                html += '<p>No messages yet. Send the first message below.</p>';
                html += '</div>';
            }
            html += '</div>';

            // Reply form
            html += '<div class="sffc-crm-reply-form">';
            html += '<textarea id="reply-message" class="sffc-crm-input" placeholder="Type your message..." rows="3"></textarea>';
            html += '<div class="sffc-crm-reply-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-save-draft-btn">Save Draft</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-send-reply-btn">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
            html += ' Send';
            html += '</button>';
            html += '</div>';
            html += '</div>';

            html += '</div>';

            $panel.html(html);
            this.bindThreadEvents();

            // Scroll to bottom of messages
            var $msgContainer = $panel.find('.sffc-crm-messages-container');
            $msgContainer.scrollTop($msgContainer[0].scrollHeight);
        },

        /**
         * Render message bubble
         */
        renderMessageBubble: function(msg) {
            var isOutbound = msg.direction === 'outbound';
            var time = this.formatRelativeTime(msg.sent_at);
            var content = msg.body_html || msg.body_text || '';

            var html = '<div class="sffc-crm-message ' + (isOutbound ? 'outbound' : 'inbound') + '">';
            html += '<div class="sffc-crm-message-bubble">';

            if (msg.subject) {
                html += '<div class="sffc-crm-message-subject">' + this.escapeHtml(msg.subject) + '</div>';
            }

            html += '<div class="sffc-crm-message-body">' + content + '</div>';
            html += '<div class="sffc-crm-message-meta">';
            html += '<span class="sffc-crm-message-time">' + time + '</span>';
            if (isOutbound) {
                html += '<span class="sffc-crm-message-status">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                html += '</span>';
            }
            html += '</div>';
            html += '</div>';
            html += '</div>';

            return html;
        },

        /**
         * Bind thread events
         */
        bindThreadEvents: function() {
            var self = this;

            // Back button
            $(document).off('click.backbtn').on('click.backbtn', '.sffc-crm-back-btn', function() {
                self.inboxState.viewingThread = false;
                self.inboxState.currentConversation = null;
                self.inboxState.currentMessages = [];
                self.loadInbox();
            });

            // Send reply
            $(document).off('click.sendreply').on('click.sendreply', '.sffc-crm-send-reply-btn', function() {
                self.sendReply();
            });

            // Save draft
            $(document).off('click.savedraft').on('click.savedraft', '.sffc-crm-save-draft-btn', function() {
                self.saveDraft();
            });

            // Star thread
            $(document).off('click.starthread').on('click.starthread', '.sffc-crm-star-thread-btn', function() {
                var convId = $(this).data('id');
                self.toggleConversationStar(convId);
            });

            // Archive
            $(document).off('click.archiveconv').on('click.archiveconv', '.sffc-crm-archive-btn', function() {
                var convId = $(this).data('id');
                self.archiveConversation(convId);
            });
        },

        /**
         * Send reply
         */
        sendReply: function() {
            var self = this;
            var body = $('#reply-message').val().trim();
            var convId = this.inboxState.currentConversation.id;

            if (!body) {
                this.showError('Please enter a message');
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_send_message',
                    conversation_id: convId,
                    body: body,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Message sent');
                        self.viewConversationThread(convId);
                    } else {
                        self.showError(response.data.message || 'Failed to send message');
                    }
                }
            });
        },

        /**
         * Save draft
         */
        saveDraft: function() {
            var self = this;
            var body = $('#reply-message').val();
            var convId = this.inboxState.currentConversation.id;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_save_message_draft',
                    conversation_id: convId,
                    body: body,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Draft saved');
                    } else {
                        self.showError(response.data.message || 'Failed to save draft');
                    }
                }
            });
        },

        /**
         * Toggle conversation star
         */
        toggleConversationStar: function(convId) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_toggle_conversation_star',
                    conversation_id: convId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        var $btn = $('.sffc-crm-star-btn[data-id="' + convId + '"], .sffc-crm-star-thread-btn[data-id="' + convId + '"]');
                        if (response.data.is_starred) {
                            $btn.addClass('starred').find('svg').attr('fill', 'currentColor');
                        } else {
                            $btn.removeClass('starred').find('svg').attr('fill', 'none');
                        }
                    }
                }
            });
        },

        /**
         * Archive conversation
         */
        archiveConversation: function(convId) {
            var self = this;

            if (!confirm('Archive this conversation?')) return;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_archive_conversation',
                    conversation_id: convId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Conversation archived');
                        self.inboxState.viewingThread = false;
                        self.loadInbox();
                    } else {
                        self.showError(response.data.message || 'Failed to archive');
                    }
                }
            });
        },

        /**
         * Search inbox
         */
        searchInbox: function(query) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_search_conversations',
                    query: query,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.inboxState.conversations = response.data.conversations;
                        self.renderInboxList();
                    }
                }
            });
        },

        /**
         * Show compose modal
         */
        showComposeModal: function() {
            var self = this;

            var html = '<div class="sffc-crm-modal-content sffc-crm-compose-modal">';
            html += '<h3>New Conversation</h3>';

            html += '<div class="sffc-crm-form-group">';
            html += '<label>Select Recruiter</label>';
            html += '<select class="sffc-crm-input" id="compose-recruiter">';
            html += '<option value="">Loading recruiters...</option>';
            html += '</select>';
            html += '</div>';

            html += '<div class="sffc-crm-form-group">';
            html += '<label>Channel</label>';
            html += '<select class="sffc-crm-input" id="compose-channel">';
            html += '<option value="email">Email</option>';
            html += '<option value="linkedin">LinkedIn</option>';
            html += '<option value="manual">Manual Entry</option>';
            html += '</select>';
            html += '</div>';

            html += '<div class="sffc-crm-form-group">';
            html += '<label>Subject (optional)</label>';
            html += '<input type="text" class="sffc-crm-input" id="compose-subject">';
            html += '</div>';

            html += '<div class="sffc-crm-modal-footer">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-modal-close">Cancel</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-create-conversation-btn">Create Conversation</button>';
            html += '</div>';

            html += '</div>';

            this.showModal(html);

            // Load recruiters
            this.loadRecruitersForCompose();
        },

        /**
         * Load recruiters for compose dropdown
         */
        loadRecruitersForCompose: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_recruiters',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.recruiters) {
                        var $select = $('#compose-recruiter');
                        $select.empty();
                        $select.append('<option value="">Select a recruiter...</option>');
                        response.data.recruiters.forEach(function(r) {
                            $select.append('<option value="' + r.id + '">' + self.escapeHtml(r.name) + (r.firm ? ' (' + self.escapeHtml(r.firm) + ')' : '') + '</option>');
                        });
                    }
                }
            });

            // Bind create button
            $(document).off('click.createconv').on('click.createconv', '.sffc-crm-create-conversation-btn', function() {
                self.createConversation();
            });
        },

        /**
         * Create conversation
         */
        createConversation: function() {
            var self = this;
            var recruiterId = $('#compose-recruiter').val();
            var channel = $('#compose-channel').val();
            var subject = $('#compose-subject').val().trim();

            if (!recruiterId) {
                this.showError('Please select a recruiter');
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_create_conversation',
                    recruiter_id: recruiterId,
                    channel: channel,
                    subject: subject,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.closeModal();
                        self.viewConversationThread(response.data.conversation.id);
                    } else {
                        self.showError(response.data.message || 'Failed to create conversation');
                    }
                }
            });
        },

        // ============================================
        // PHASE 6: Analytics & Intelligence
        // ============================================

        analyticsState: {
            period: 'month',
            loaded: false
        },

        /**
         * Load analytics dashboard
         */
        loadAnalytics: function() {
            var self = this;
            var $panel = $('#panel-analytics');

            $panel.html('<div class="sffc-crm-loading">Loading analytics...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_analytics_overview',
                    period: this.analyticsState.period,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.analyticsState.loaded = true;
                        self.renderAnalyticsDashboard(response.data.metrics);
                    } else {
                        self.showError('Failed to load analytics');
                    }
                }
            });
        },

        /**
         * Render analytics dashboard
         */
        renderAnalyticsDashboard: function(metrics) {
            var self = this;
            var $panel = $('#panel-analytics');

            var html = '<div class="sffc-crm-analytics-container">';

            // Header with period selector
            html += '<div class="sffc-crm-analytics-header">';
            html += '<h3>Analytics Dashboard</h3>';
            html += '<div class="sffc-crm-analytics-controls">';
            html += '<select id="analytics-period" class="sffc-crm-select">';
            html += '<option value="week"' + (this.analyticsState.period === 'week' ? ' selected' : '') + '>Last 7 Days</option>';
            html += '<option value="month"' + (this.analyticsState.period === 'month' ? ' selected' : '') + '>Last 30 Days</option>';
            html += '<option value="quarter"' + (this.analyticsState.period === 'quarter' ? ' selected' : '') + '>Last 90 Days</option>';
            html += '</select>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-export-btn">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
            html += ' Export';
            html += '</button>';
            html += '</div>';
            html += '</div>';

            // Overview metrics cards
            html += '<div class="sffc-crm-metrics-grid">';
            html += this.renderMetricCard('Outreach Sent', metrics.outreach.total, metrics.outreach.change, 'outreach');
            html += this.renderMetricCard('Response Rate', metrics.responses.reply_rate + '%', metrics.responses.rate_change, 'rate');
            html += this.renderMetricCard('Pipeline Active', metrics.pipeline.total_active, null, 'pipeline');
            html += this.renderMetricCard('Interviews', metrics.pipeline.interviews, null, 'interviews');
            html += '</div>';

            // Tabs for different analytics sections
            html += '<div class="sffc-crm-analytics-tabs">';
            html += '<button class="sffc-crm-analytics-tab active" data-section="outreach">Outreach</button>';
            html += '<button class="sffc-crm-analytics-tab" data-section="pipeline">Pipeline</button>';
            html += '<button class="sffc-crm-analytics-tab" data-section="sequences">Sequences</button>';
            html += '<button class="sffc-crm-analytics-tab" data-section="timing">Timing</button>';
            html += '<button class="sffc-crm-analytics-tab" data-section="recruiters">Recruiters</button>';
            html += '<button class="sffc-crm-analytics-tab" data-section="alerts">Alerts</button>';
            html += '</div>';

            // Analytics sections
            html += '<div class="sffc-crm-analytics-sections">';
            html += '<div class="sffc-crm-analytics-section active" id="analytics-outreach">' + this.renderOutreachSection(metrics) + '</div>';
            html += '<div class="sffc-crm-analytics-section" id="analytics-pipeline"><div class="sffc-crm-loading">Loading...</div></div>';
            html += '<div class="sffc-crm-analytics-section" id="analytics-sequences"><div class="sffc-crm-loading">Loading...</div></div>';
            html += '<div class="sffc-crm-analytics-section" id="analytics-timing"><div class="sffc-crm-loading">Loading...</div></div>';
            html += '<div class="sffc-crm-analytics-section" id="analytics-recruiters"><div class="sffc-crm-loading">Loading...</div></div>';
            html += '<div class="sffc-crm-analytics-section" id="analytics-alerts"><div class="sffc-crm-loading">Loading...</div></div>';
            html += '</div>';
            html += '</div>';

            $panel.html(html);
            this.bindAnalyticsEvents();
        },

        renderMetricCard: function(label, value, change, type) {
            var changeHtml = '';
            if (change !== null && change !== undefined) {
                var isPositive = parseFloat(change) >= 0;
                changeHtml = '<span class="sffc-crm-metric-change ' + (isPositive ? 'positive' : 'negative') + '">' + (isPositive ? '↑' : '↓') + ' ' + Math.abs(change) + '%</span>';
            }
            return '<div class="sffc-crm-metric-card"><div class="sffc-crm-metric-value">' + value + '</div><div class="sffc-crm-metric-label">' + label + '</div>' + changeHtml + '</div>';
        },

        renderOutreachSection: function(metrics) {
            var html = '<div class="sffc-crm-analytics-cards">';
            html += '<div class="sffc-crm-analytics-card"><h4>Response Breakdown</h4>';
            html += '<div class="sffc-crm-stats-grid">';
            html += '<div class="sffc-crm-stat"><span class="sffc-crm-stat-value">' + metrics.responses.total_sent + '</span><span class="sffc-crm-stat-label">Total Sent</span></div>';
            html += '<div class="sffc-crm-stat"><span class="sffc-crm-stat-value">' + metrics.responses.opened + '</span><span class="sffc-crm-stat-label">Opened</span></div>';
            html += '<div class="sffc-crm-stat"><span class="sffc-crm-stat-value">' + metrics.responses.replied + '</span><span class="sffc-crm-stat-label">Replied</span></div>';
            html += '<div class="sffc-crm-stat"><span class="sffc-crm-stat-value">' + metrics.responses.open_rate + '%</span><span class="sffc-crm-stat-label">Open Rate</span></div>';
            html += '</div></div>';
            html += '<div class="sffc-crm-analytics-card"><h4>By Channel</h4>';
            var emailCount = metrics.outreach.by_channel.email || 0;
            var linkedinCount = metrics.outreach.by_channel.linkedin || 0;
            var total = emailCount + linkedinCount;
            if (total > 0) {
                html += '<div class="sffc-crm-channel-bars">';
                html += '<div class="sffc-crm-channel-bar"><span>Email</span><div class="sffc-crm-bar-wrap"><div class="sffc-crm-bar" style="width:' + (emailCount/total*100) + '%"></div></div><span>' + emailCount + '</span></div>';
                html += '<div class="sffc-crm-channel-bar"><span>LinkedIn</span><div class="sffc-crm-bar-wrap"><div class="sffc-crm-bar sffc-crm-bar-alt" style="width:' + (linkedinCount/total*100) + '%"></div></div><span>' + linkedinCount + '</span></div>';
                html += '</div>';
            } else {
                html += '<p class="sffc-crm-empty-text">No outreach data yet</p>';
            }
            html += '</div></div>';
            return html;
        },

        bindAnalyticsEvents: function() {
            var self = this;
            $(document).off('change.analyticsperiod').on('change.analyticsperiod', '#analytics-period', function() {
                self.analyticsState.period = $(this).val();
                self.loadAnalytics();
            });
            $(document).off('click.analyticstab').on('click.analyticstab', '.sffc-crm-analytics-tab', function() {
                var section = $(this).data('section');
                $('.sffc-crm-analytics-tab').removeClass('active');
                $(this).addClass('active');
                $('.sffc-crm-analytics-section').removeClass('active');
                $('#analytics-' + section).addClass('active');
                self.loadAnalyticsSection(section);
            });
            $(document).off('click.analyticsexport').on('click.analyticsexport', '.sffc-crm-export-btn', function() {
                self.showExportModal();
            });
        },

        loadAnalyticsSection: function(section) {
            var self = this;
            var $section = $('#analytics-' + section);
            if ($section.data('loaded')) return;

            var actions = {
                pipeline: 'sffc_crm_get_analytics_pipeline',
                sequences: 'sffc_crm_get_analytics_sequences',
                timing: 'sffc_crm_get_analytics_timing',
                recruiters: 'sffc_crm_get_analytics_recruiters'
            };

            if (section === 'alerts') {
                this.loadAlertsSection();
                return;
            }

            if (!actions[section]) return;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: { action: actions[section], period: this.analyticsState.period, nonce: this.config.nonce },
                success: function(response) {
                    if (response.success) {
                        $section.data('loaded', true);
                        $section.html(self.renderAnalyticsSection(section, response.data));
                    }
                }
            });
        },

        renderAnalyticsSection: function(section, data) {
            switch (section) {
                case 'pipeline': return this.renderPipelineAnalytics(data);
                case 'sequences': return this.renderSequenceAnalytics(data);
                case 'timing': return this.renderTimingAnalytics(data);
                case 'recruiters': return this.renderRecruiterAnalytics(data);
            }
            return '';
        },

        renderPipelineAnalytics: function(data) {
            var html = '<div class="sffc-crm-analytics-cards">';
            html += '<div class="sffc-crm-analytics-card sffc-crm-analytics-card-wide"><h4>Pipeline Funnel</h4>';
            if (data.funnel && data.funnel.length > 0) {
                html += '<div class="sffc-crm-funnel">';
                var maxCount = data.funnel[0].count || 1;
                data.funnel.forEach(function(stage) {
                    var width = Math.max(20, (stage.count / maxCount) * 100);
                    html += '<div class="sffc-crm-funnel-stage"><div class="sffc-crm-funnel-bar" style="width:' + width + '%"><span>' + stage.label + '</span><span>' + stage.count + '</span></div>';
                    if (stage.conversion_rate !== undefined) html += '<span class="sffc-crm-funnel-rate">' + stage.conversion_rate + '%</span>';
                    html += '</div>';
                });
                html += '</div>';
            } else {
                html += '<p class="sffc-crm-empty-text">No pipeline data</p>';
            }
            html += '</div>';
            html += '<div class="sffc-crm-analytics-card"><h4>Outcomes</h4><div class="sffc-crm-stats-grid">';
            html += '<div class="sffc-crm-stat sffc-crm-stat-success"><span class="sffc-crm-stat-value">' + (data.metrics.outcomes.won || 0) + '</span><span class="sffc-crm-stat-label">Won</span></div>';
            html += '<div class="sffc-crm-stat sffc-crm-stat-danger"><span class="sffc-crm-stat-value">' + (data.metrics.outcomes.lost || 0) + '</span><span class="sffc-crm-stat-label">Lost</span></div>';
            html += '<div class="sffc-crm-stat"><span class="sffc-crm-stat-value">' + data.metrics.win_rate + '%</span><span class="sffc-crm-stat-label">Win Rate</span></div>';
            html += '</div></div></div>';
            return html;
        },

        renderSequenceAnalytics: function(data) {
            var html = '<div class="sffc-crm-analytics-card sffc-crm-analytics-card-wide"><h4>Sequence Performance</h4>';
            if (data.sequences && data.sequences.length > 0) {
                html += '<table class="sffc-crm-table"><thead><tr><th>Sequence</th><th>Enrolled</th><th>Active</th><th>Replied</th><th>Reply Rate</th></tr></thead><tbody>';
                data.sequences.forEach(function(seq) {
                    html += '<tr><td>' + seq.name + '</td><td>' + seq.total_enrolled + '</td><td>' + seq.active + '</td><td>' + seq.replied + '</td><td>' + seq.reply_rate + '%</td></tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<p class="sffc-crm-empty-text">No sequences yet</p>';
            }
            html += '</div>';
            return html;
        },

        renderTimingAnalytics: function(data) {
            var html = '<div class="sffc-crm-analytics-cards">';
            if (data.recommendation) {
                html += '<div class="sffc-crm-analytics-card sffc-crm-analytics-card-wide sffc-crm-insight"><h4>Best Time to Reach Out</h4><p>' + data.recommendation + '</p></div>';
            }
            html += '<div class="sffc-crm-analytics-card"><h4>By Day of Week</h4>';
            if (data.by_day && data.by_day.length > 0) {
                html += '<div class="sffc-crm-timing-chart">';
                data.by_day.forEach(function(d) {
                    var h = Math.max(10, d.reply_rate * 3);
                    html += '<div class="sffc-crm-timing-bar-group' + (d.day_name === data.best_day ? ' best' : '') + '"><div class="sffc-crm-timing-bar" style="height:' + h + 'px"></div><span>' + d.day_name.substring(0,3) + '</span></div>';
                });
                html += '</div>';
            }
            html += '</div></div>';
            return html;
        },

        renderRecruiterAnalytics: function(data) {
            var html = '<div class="sffc-crm-analytics-cards">';
            html += '<div class="sffc-crm-analytics-card"><h4>Overview</h4><div class="sffc-crm-stats-grid">';
            html += '<div class="sffc-crm-stat"><span class="sffc-crm-stat-value">' + data.metrics.total_saved + '</span><span class="sffc-crm-stat-label">Saved</span></div>';
            html += '<div class="sffc-crm-stat"><span class="sffc-crm-stat-value">' + data.metrics.contacted + '</span><span class="sffc-crm-stat-label">Contacted</span></div>';
            html += '<div class="sffc-crm-stat"><span class="sffc-crm-stat-value">' + data.metrics.replied + '</span><span class="sffc-crm-stat-label">Replied</span></div>';
            html += '</div></div>';
            html += '<div class="sffc-crm-analytics-card sffc-crm-analytics-card-wide"><h4>Top Responding</h4>';
            if (data.top_responding && data.top_responding.length > 0) {
                html += '<table class="sffc-crm-table"><thead><tr><th>Recruiter</th><th>Firm</th><th>Outreach</th><th>Replies</th><th>Rate</th></tr></thead><tbody>';
                data.top_responding.forEach(function(r) {
                    html += '<tr><td>' + r.name + '</td><td>' + (r.firm || '-') + '</td><td>' + r.total_outreach + '</td><td>' + r.replied + '</td><td>' + r.response_rate + '%</td></tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<p class="sffc-crm-empty-text">No data yet</p>';
            }
            html += '</div></div>';
            return html;
        },

        loadAlertsSection: function() {
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: { action: 'sffc_crm_get_alerts', nonce: this.config.nonce },
                success: function(response) {
                    if (response.success) {
                        $('#analytics-alerts').data('loaded', true).html(self.renderAlertsSection(response.data.alerts, response.data.type_labels));
                        self.bindAlertEvents();
                        self.bindDigestEvents();
                        self.loadDigestPreferences();
                    }
                }
            });
        },

        renderAlertsSection: function(alerts, typeLabels) {
            var self = this;
            var html = '<div class="sffc-crm-analytics-card sffc-crm-analytics-card-wide">';
            html += '<div class="sffc-crm-card-header"><h4>Your Alerts</h4><button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-btn-small sffc-crm-create-alert-btn">+ New Alert</button></div>';
            if (alerts && alerts.length > 0) {
                html += '<div class="sffc-crm-alerts-list">';
                alerts.forEach(function(a) {
                    html += '<div class="sffc-crm-alert-item' + (a.is_active ? '' : ' inactive') + '" data-id="' + a.id + '">';
                    html += '<div class="sffc-crm-alert-info"><strong>' + self.escapeHtml(a.name) + '</strong><span class="sffc-crm-alert-type">' + (typeLabels[a.type] || a.type) + '</span></div>';
                    html += '<div class="sffc-crm-alert-actions">';
                    html += '<button class="sffc-crm-toggle-alert-btn" title="Toggle">' + (a.is_active ? 'Disable' : 'Enable') + '</button>';
                    html += '<button class="sffc-crm-delete-alert-btn" title="Delete">Delete</button>';
                    html += '</div></div>';
                });
                html += '</div>';
            } else {
                html += '<p class="sffc-crm-empty-text">No alerts configured. Create an alert to get notified.</p>';
            }
            html += '</div>';

            // Email Digest Section
            html += '<div class="sffc-crm-analytics-card sffc-crm-analytics-card-wide sffc-crm-digest-section">';
            html += '<div class="sffc-crm-card-header"><h4>Email Digest</h4></div>';
            html += '<p class="sffc-crm-digest-description">Get a summary of your CRM activity delivered to your inbox.</p>';
            html += '<div class="sffc-crm-digest-options" id="digest-options">';
            html += '<div class="sffc-crm-digest-option"><label><input type="checkbox" id="digest-daily" class="sffc-crm-digest-checkbox"> Daily Digest</label><span class="sffc-crm-digest-hint">Sent every morning at 8 AM</span></div>';
            html += '<div class="sffc-crm-digest-option"><label><input type="checkbox" id="digest-weekly" class="sffc-crm-digest-checkbox"> Weekly Digest</label><span class="sffc-crm-digest-hint">Sent every Monday at 8 AM</span></div>';
            html += '</div>';
            html += '<div class="sffc-crm-digest-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-small sffc-crm-test-digest-btn">Send Test</button>';
            html += '</div>';
            html += '</div>';

            return html;
        },

        loadDigestPreferences: function() {
            var self = this;
            $.post(this.config.ajaxUrl, {
                action: 'sffc_crm_get_digest_preferences',
                nonce: this.config.nonce
            }, function(r) {
                if (r.success) {
                    $('#digest-daily').prop('checked', r.data.daily);
                    $('#digest-weekly').prop('checked', r.data.weekly);
                }
            });
        },

        bindDigestEvents: function() {
            var self = this;

            $(document).off('change.digest').on('change.digest', '.sffc-crm-digest-checkbox', function() {
                $.post(self.config.ajaxUrl, {
                    action: 'sffc_crm_update_digest_preferences',
                    daily: $('#digest-daily').is(':checked').toString(),
                    weekly: $('#digest-weekly').is(':checked').toString(),
                    nonce: self.config.nonce
                }, function(r) {
                    if (r.success) {
                        self.showSuccess('Digest preferences updated');
                    }
                });
            });

            $(document).off('click.testdigest').on('click.testdigest', '.sffc-crm-test-digest-btn', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Sending...');
                $.post(self.config.ajaxUrl, {
                    action: 'sffc_crm_send_test_digest',
                    type: 'daily',
                    nonce: self.config.nonce
                }, function(r) {
                    $btn.prop('disabled', false).text('Send Test');
                    if (r.success) {
                        self.showSuccess(r.data.message);
                    } else {
                        self.showError('Failed to send test digest');
                    }
                });
            });
        },

        bindAlertEvents: function() {
            var self = this;
            $(document).off('click.createalert').on('click.createalert', '.sffc-crm-create-alert-btn', function() { self.showCreateAlertModal(); });
            $(document).off('click.togglealert').on('click.togglealert', '.sffc-crm-toggle-alert-btn', function(e) {
                e.stopPropagation();
                var id = $(this).closest('.sffc-crm-alert-item').data('id');
                $.post(self.config.ajaxUrl, { action: 'sffc_crm_toggle_alert', alert_id: id, nonce: self.config.nonce }, function(r) {
                    if (r.success) { self.showSuccess(r.data.message); self.loadAlertsSection(); }
                });
            });
            $(document).off('click.deletealert').on('click.deletealert', '.sffc-crm-delete-alert-btn', function(e) {
                e.stopPropagation();
                if (!confirm('Delete this alert?')) return;
                var id = $(this).closest('.sffc-crm-alert-item').data('id');
                $.post(self.config.ajaxUrl, { action: 'sffc_crm_delete_alert', alert_id: id, nonce: self.config.nonce }, function(r) {
                    if (r.success) { self.showSuccess('Alert deleted'); self.loadAlertsSection(); }
                });
            });
        },

        showCreateAlertModal: function() {
            var self = this;
            var html = '<div class="sffc-crm-modal-header"><h3>Create Alert</h3><button class="sffc-crm-modal-close">&times;</button></div>';
            html += '<div class="sffc-crm-modal-body">';
            html += '<div class="sffc-crm-form-group"><label>Name</label><input type="text" id="alert-name" class="sffc-crm-input" placeholder="e.g., Finance Director Roles"></div>';
            html += '<div class="sffc-crm-form-group"><label>Type</label><select id="alert-type" class="sffc-crm-select"><option value="keyword">Keyword Alert</option><option value="recruiter_post">Saved Recruiter Posts</option></select></div>';
            html += '<div class="sffc-crm-form-group" id="keyword-config"><label>Keywords (comma separated)</label><input type="text" id="alert-keywords" class="sffc-crm-input" placeholder="e.g., asset management, private equity"></div>';
            html += '<div class="sffc-crm-form-group"><label class="sffc-crm-checkbox-label"><input type="checkbox" id="alert-email" checked> Email notifications</label></div>';
            html += '</div>';
            html += '<div class="sffc-crm-modal-footer"><button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-modal-close">Cancel</button><button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-save-alert-btn">Create</button></div>';
            this.showModal(html);

            $('#alert-type').on('change', function() { $('#keyword-config').toggle($(this).val() === 'keyword'); });
            $('.sffc-crm-save-alert-btn').on('click', function() {
                var name = $('#alert-name').val().trim();
                var type = $('#alert-type').val();
                if (!name) { self.showError('Please enter a name'); return; }
                var config = {};
                if (type === 'keyword') {
                    var kw = $('#alert-keywords').val().trim();
                    if (!kw) { self.showError('Please enter keywords'); return; }
                    config.keywords = kw.split(',').map(function(k) { return k.trim(); });
                } else {
                    config.watch_all_saved = true;
                }
                $.post(self.config.ajaxUrl, { action: 'sffc_crm_create_alert', name: name, type: type, config: config, email_enabled: $('#alert-email').is(':checked') ? 1 : 0, nonce: self.config.nonce }, function(r) {
                    if (r.success) { self.closeModal(); self.showSuccess('Alert created'); self.loadAlertsSection(); }
                    else { self.showError(r.data.message || 'Failed'); }
                });
            });
        },

        showExportModal: function() {
            var self = this;
            var html = '<div class="sffc-crm-modal-header"><h3>Export Data</h3><button class="sffc-crm-modal-close">&times;</button></div>';
            html += '<div class="sffc-crm-modal-body">';
            html += '<div class="sffc-crm-form-group"><label>Export Type</label><select id="export-type" class="sffc-crm-select"><option value="recruiters">Saved Recruiters</option><option value="pipeline">Pipeline</option><option value="outreach">Outreach History</option><option value="analytics">Analytics Summary</option></select></div>';
            html += '<div class="sffc-crm-form-group"><label>Format</label><select id="export-format" class="sffc-crm-select"><option value="csv">CSV</option><option value="json">JSON</option></select></div>';
            html += '</div>';
            html += '<div class="sffc-crm-modal-footer"><button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-modal-close">Cancel</button><button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-do-export-btn">Export</button></div>';
            this.showModal(html);

            $('.sffc-crm-do-export-btn').on('click', function() {
                $.post(self.config.ajaxUrl, { action: 'sffc_crm_export_data', type: $('#export-type').val(), format: $('#export-format').val(), nonce: self.config.nonce }, function(r) {
                    if (r.success) {
                        var blob = new Blob([r.data.content], { type: r.data.type });
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url; a.download = r.data.filename;
                        document.body.appendChild(a); a.click();
                        URL.revokeObjectURL(url); document.body.removeChild(a);
                        self.closeModal(); self.showSuccess('Export complete');
                    } else {
                        self.showError(r.data.message || 'Export failed');
                    }
                });
            });
        },

        // ============================================
        // PHASE 7: Polish & Scale Features
        // ============================================

        /**
         * Initialize keyboard shortcuts
         */
        initKeyboardShortcuts: function() {
            var self = this;

            $(document).on('keydown', function(e) {
                // Don't trigger shortcuts when typing in inputs
                if ($(e.target).is('input, textarea, select, [contenteditable]')) {
                    return;
                }

                var key = e.key.toLowerCase();
                var isCmd = e.metaKey || e.ctrlKey;

                // ? - Show keyboard shortcuts
                if (key === '?' && !isCmd) {
                    e.preventDefault();
                    self.showKeyboardShortcuts();
                    return;
                }

                // Escape - Close modal or deselect
                if (key === 'escape') {
                    if ($('.sffc-crm-modal').is(':visible')) {
                        self.closeModal();
                    } else if (self.selectedItems && self.selectedItems.length > 0) {
                        self.clearSelection();
                    }
                    return;
                }

                // Tab navigation: 1-6 for tabs
                if (['1', '2', '3', '4', '5', '6'].includes(key) && !isCmd) {
                    e.preventDefault();
                    var tabIndex = parseInt(key) - 1;
                    var $tabs = $('.sffc-crm-tab');
                    if ($tabs.eq(tabIndex).length) {
                        $tabs.eq(tabIndex).click();
                    }
                    return;
                }

                // n - New/Compose (context-dependent)
                if (key === 'n' && !isCmd) {
                    e.preventDefault();
                    var currentTab = self.currentTab || 'feed';
                    switch (currentTab) {
                        case 'sequences':
                            self.showCreateSequenceModal();
                            break;
                        case 'inbox':
                            self.showComposeModal();
                            break;
                        default:
                            // Open compose if recruiter is selected
                            if (self.selectedRecruiter) {
                                self.showComposeModal(self.selectedRecruiter);
                            }
                    }
                    return;
                }

                // / - Focus search
                if (key === '/' && !isCmd) {
                    e.preventDefault();
                    $('.sffc-crm-search-input, .sffc-crm-filter-search input').first().focus();
                    return;
                }

                // j/k - Navigate list items
                if (key === 'j' || key === 'k') {
                    e.preventDefault();
                    self.navigateListItems(key === 'j' ? 1 : -1);
                    return;
                }

                // Cmd/Ctrl + Enter - Submit form
                if (key === 'enter' && isCmd) {
                    var $form = $('.sffc-crm-modal:visible form, .sffc-crm-modal:visible .sffc-crm-modal-footer .sffc-crm-btn-primary');
                    if ($form.length) {
                        $form.first().click();
                    }
                    return;
                }

                // a - Select all (in list context)
                if (key === 'a' && isCmd) {
                    e.preventDefault();
                    self.selectAllItems();
                    return;
                }
            });
        },

        /**
         * Navigate list items with j/k keys
         */
        navigateListItems: function(direction) {
            var $items = $('.sffc-crm-post-card, .sffc-crm-post-row, .sffc-crm-recruiter-row, .sffc-crm-pipeline-card, .sffc-crm-conversation-item');
            var $focused = $items.filter('.keyboard-focused');

            if ($focused.length === 0) {
                $items.first().addClass('keyboard-focused').focus();
            } else {
                var currentIndex = $items.index($focused);
                var newIndex = currentIndex + direction;

                if (newIndex >= 0 && newIndex < $items.length) {
                    $focused.removeClass('keyboard-focused');
                    $items.eq(newIndex).addClass('keyboard-focused').focus();

                    // Scroll into view
                    $items.eq(newIndex)[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        },

        /**
         * Show keyboard shortcuts modal
         */
        showKeyboardShortcuts: function() {
            var shortcuts = [
                { keys: ['?'], label: 'Show keyboard shortcuts' },
                { keys: ['1-6'], label: 'Switch tabs' },
                { keys: ['/'], label: 'Focus search' },
                { keys: ['n'], label: 'New item (context-dependent)' },
                { keys: ['j', 'k'], label: 'Navigate up/down' },
                { keys: ['Esc'], label: 'Close modal / Clear selection' },
                { keys: ['Cmd', 'Enter'], label: 'Submit form' },
                { keys: ['Cmd', 'a'], label: 'Select all' },
            ];

            var html = '<div class="sffc-crm-modal-header"><h3>Keyboard Shortcuts</h3><button class="sffc-crm-modal-close">&times;</button></div>';
            html += '<div class="sffc-crm-modal-body"><div class="sffc-crm-shortcuts-list">';

            shortcuts.forEach(function(s) {
                html += '<div class="sffc-crm-shortcut-item">';
                html += '<span class="sffc-crm-shortcut-label">' + s.label + '</span>';
                html += '<span class="sffc-crm-shortcut-keys">';
                s.keys.forEach(function(k) {
                    html += '<kbd class="sffc-crm-kbd">' + k + '</kbd>';
                });
                html += '</span></div>';
            });

            html += '</div></div>';

            this.showModal(html, 'sffc-crm-shortcuts-modal');
        },

        /**
         * Select all items in current view
         */
        selectAllItems: function() {
            var $checkboxes = $('.sffc-crm-panel:visible .sffc-crm-select-checkbox:not(:checked)');
            $checkboxes.prop('checked', true).trigger('change');
        },

        /**
         * Clear all selections
         */
        clearSelection: function() {
            $('.sffc-crm-select-checkbox:checked').prop('checked', false).trigger('change');
            this.updateBulkBar();
        },

        /**
         * Generate skeleton loading HTML
         */
        renderSkeleton: function(type, count) {
            var html = '';
            count = count || 3;

            for (var i = 0; i < count; i++) {
                switch (type) {
                    case 'card':
                        html += '<div class="sffc-crm-skeleton-card">';
                        html += '<div class="sffc-crm-skeleton sffc-crm-skeleton-avatar"></div>';
                        html += '<div class="sffc-crm-skeleton sffc-crm-skeleton-text medium"></div>';
                        html += '<div class="sffc-crm-skeleton sffc-crm-skeleton-text short"></div>';
                        html += '</div>';
                        break;
                    case 'row':
                        html += '<div class="sffc-crm-skeleton-card" style="display:flex;gap:16px;align-items:center;">';
                        html += '<div class="sffc-crm-skeleton sffc-crm-skeleton-avatar"></div>';
                        html += '<div style="flex:1;">';
                        html += '<div class="sffc-crm-skeleton sffc-crm-skeleton-text medium"></div>';
                        html += '<div class="sffc-crm-skeleton sffc-crm-skeleton-text short"></div>';
                        html += '</div></div>';
                        break;
                    case 'metric':
                        html += '<div class="sffc-crm-metric-card">';
                        html += '<div class="sffc-crm-skeleton sffc-crm-skeleton-text short"></div>';
                        html += '<div class="sffc-crm-skeleton" style="height:40px;width:80px;margin:8px 0;"></div>';
                        html += '<div class="sffc-crm-skeleton sffc-crm-skeleton-text" style="width:50%;"></div>';
                        html += '</div>';
                        break;
                }
            }

            return html;
        },

        /**
         * Render empty state
         */
        renderEmptyState: function(options) {
            var defaults = {
                icon: 'inbox',
                title: 'Nothing here yet',
                description: 'Get started by taking an action.',
                actionLabel: null,
                actionCallback: null
            };

            var opts = $.extend({}, defaults, options);

            var icons = {
                inbox: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
                users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
                briefcase: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
                search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>',
                chart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>',
                bell: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>'
            };

            var html = '<div class="sffc-crm-empty-state">';
            html += '<div class="sffc-crm-empty-icon">' + (icons[opts.icon] || icons.inbox) + '</div>';
            html += '<h3 class="sffc-crm-empty-title">' + this.escapeHtml(opts.title) + '</h3>';
            html += '<p class="sffc-crm-empty-description">' + this.escapeHtml(opts.description) + '</p>';

            if (opts.actionLabel) {
                html += '<div class="sffc-crm-empty-action">';
                html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-empty-action-btn">' + this.escapeHtml(opts.actionLabel) + '</button>';
                html += '</div>';
            }

            html += '</div>';

            return html;
        },

        /**
         * Render error state
         */
        handleAvatarUpload: function(file) {
            var cfg = this.config.avatarUpload || {};
            if (!cfg.enabled) {
                return;
            }

            var maxSize = cfg.maxSize || (2 * 1024 * 1024);
            if (file.size > maxSize) {
                this.showError('Please upload images up to ' + (cfg.maxSizeLabel || '2MB') + '.');
                return;
            }

            var allowed = cfg.allowedTypes || [];
            if (allowed.length && allowed.indexOf(file.type) === -1) {
                this.showError('Please upload a JPG, PNG, or WebP file.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'sffc_crm_upload_avatar');
            formData.append('nonce', cfg.nonce);
            formData.append('avatar_file', file);

            var self = this;
            var $avatar = $('.sffc-crm-profile-avatar');
            $avatar.addClass('is-uploading');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $avatar.removeClass('is-uploading');
                    var url = response && response.data ? response.data.url : '';
                    if (!response || !response.success || !url) {
                        var errMsg = response && response.data && response.data.message ? response.data.message : 'Could not update your photo. Please try again.';
                        self.showError(errMsg);
                        return;
                    }
                    self.updateProfileAvatar(url);
                    self.showSuccess('Profile photo updated');
                },
                error: function(xhr) {
                    $avatar.removeClass('is-uploading');
                    var errMsg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                        ? xhr.responseJSON.data.message
                        : 'Upload failed. Please try again.';
                    self.showError(errMsg);
                }
            });
        },

        updateProfileAvatar: function(url) {
            var $wrapper = $('.sffc-crm-profile-avatar');
            if (!$wrapper.length) {
                return;
            }

            var $img = $wrapper.find('img');
            if (!$img.length) {
                $img = $('<img alt="" loading="lazy">');
                $wrapper.prepend($img);
            }

            var cacheBustedUrl = url;
            if (cacheBustedUrl && cacheBustedUrl.indexOf('data:') !== 0) {
                cacheBustedUrl += (cacheBustedUrl.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
            }

            $wrapper.removeClass('sffc-crm-profile-avatar--placeholder');
            $img.attr('src', cacheBustedUrl || url || '');
            this.initProfileAvatar();
        },

        initAuthModal: function() {
            this.$authModal = $('#sffc-crm-auth-modal');
            if (!this.$authModal.length) {
                return;
            }
            var self = this;
            this.$authForm = $('#sffc-crm-auth-form');
            this.$authModal.on('click', '[data-auth-close]', function(e) {
                e.preventDefault();
                self.hideAuthModal();
            });
            this.$authForm.on('submit', function(e) {
                e.preventDefault();
                self.submitAuthLead($(this));
            });

            // Don't show auth modal immediately - let users browse first
            // Modal will be triggered on intent-based actions (save, message, apply)
            // if (!this.config.isLoggedIn) {
            //     this.showAuthModal();
            // }
        },

        resetAuthModalState: function() {
            if (!this.$authModal || !this.$authModal.length) {
                return;
            }
            this.$authModal.find('#sffc-crm-auth-form').show();
            this.$authModal.find('.sffc-crm-auth-plan-selection').hide();
            this.$authModal.find('.sffc-crm-auth-membership-form').hide();
            this.$authModal.find('#crm-memberpress-form-container').html('');
        },

        showAuthModal: function() {
            if (!this.$authModal || !this.$authModal.length) {
                window.location.href = this.config.membershipUrl || '/memberships/';
                return;
            }
            this.resetAuthModalState();
            this.$authModal.addClass('is-visible').attr('aria-hidden', 'false').prop('inert', false);
            $('body').addClass('sffc-crm-auth-open');
        },

        hideAuthModal: function() {
            if (!this.$authModal || !this.$authModal.length) {
                return;
            }

            // Blur any focused element inside the modal before hiding
            var activeElement = document.activeElement;
            if (activeElement && this.$authModal[0].contains(activeElement)) {
                activeElement.blur();
            }

            this.$authModal.removeClass('is-visible').attr('aria-hidden', 'true').prop('inert', true);
            $('body').removeClass('sffc-crm-auth-open');
            this.resetAuthModalState();
        },

        submitAuthLead: function($form) {
            var first = $form.find('input[name="first_name"]').val().trim();
            var last = $form.find('input[name="last_name"]').val().trim();
            var email = $form.find('input[name="email"]').val().trim();

            if (!first || !last || !email) {
                this.showWarning('Please complete all fields.');
                return;
            }

            var self = this;

            // Create free account via AJAX
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_capture_lead',
                    nonce: this.config.nonce,
                    first_name: first,
                    last_name: last,
                    email: email
                },
                success: function(response) {
                    if (response && response.success) {
                        // Account created successfully
                        self.hideAuthModal();

                        // Show success message
                        self.showSuccess('Account created! You can now browse and apply to jobs.');

                        // Reload page to update login state
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        var errorMsg = response && response.data && response.data.message
                            ? response.data.message
                            : 'Unable to create account. Please try again.';
                        self.showError(errorMsg);
                    }
                },
                error: function() {
                    self.showError('Unable to create account. Please try again.');
                }
            });
        },

        showMembershipPrompt: function() {
            // Membership prompts should only show for logged-in users
            if (!this.config.isLoggedIn) {
                this.showAuthModal();
                return;
            }

            // Open modal and show State 2 (membership selection) directly
            if (!this.$authModal || !this.$authModal.length) {
                return;
            }

            // Mark that they've seen the prompt
            localStorage.setItem('sffc_crm_seen_membership_prompt', '1');

            // Show modal
            this.$authModal.addClass('is-visible').attr('aria-hidden', 'false').prop('inert', false);
            $('body').addClass('sffc-crm-auth-open');

            // Hide State 1, show State 2
            $('#sffc-crm-auth-form').hide();
            $('.sffc-crm-auth-plan-selection').show();
            $('.sffc-crm-auth-membership-form').hide();
            $('#crm-memberpress-form-container').html('');

            // Load plans
            this.showAuthState2();
        },

        showAuthState2: function() {
            var self = this;

            // Hide State 1 (form)
            $('#sffc-crm-auth-form').hide();

            // Show State 2 (plan selection)
            $('.sffc-crm-auth-plan-selection').show();

            // Load membership plans
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_signup_plans',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    console.log('Plans loaded from backend:', response);

                    if (response && response.success && response.data) {
                        var featuredPlan = response.data.featured;
                        var annualPlan = response.data.annual;

                        console.log('Featured plan:', featuredPlan);
                        console.log('Annual plan:', annualPlan);

                        // Check if at least one plan is configured
                        if (!featuredPlan && !annualPlan) {
                            self.showError('No membership plans configured. Please contact support.');
                            return;
                        }

                        if (featuredPlan) {
                            var $featuredBtn = $('#crm-select-featured-plan');
                            var featuredHtml = '<div class="sffc-crm-plan-header">';
                            featuredHtml += '<span class="sffc-crm-plan-name">' + self.escapeHtml(featuredPlan.name) + '</span>';
                            if (featuredPlan.badge) {
                                featuredHtml += '<span class="sffc-crm-plan-badge">' + self.escapeHtml(featuredPlan.badge) + '</span>';
                            }
                            featuredHtml += '</div>';

                            var formattedPrice = self.formatPlanPrice(featuredPlan);
                            if (formattedPrice) {
                                featuredHtml += '<div class="sffc-crm-plan-price">' + formattedPrice + '</div>';
                            }

                            $featuredBtn.html(featuredHtml);
                            $featuredBtn.data('plan', featuredPlan);
                        } else {
                            $('#crm-select-featured-plan').hide();
                        }

                        if (annualPlan) {
                            var $annualBtn = $('#crm-select-annual-plan');
                            var annualHtml = '<div class="sffc-crm-plan-header">';
                            annualHtml += '<span class="sffc-crm-plan-name">' + self.escapeHtml(annualPlan.name) + '</span>';
                            if (annualPlan.badge) {
                                annualHtml += '<span class="sffc-crm-plan-badge">' + self.escapeHtml(annualPlan.badge) + '</span>';
                            }
                            annualHtml += '</div>';

                            var formattedPrice = self.formatPlanPrice(annualPlan);
                            if (formattedPrice) {
                                annualHtml += '<div class="sffc-crm-plan-price">' + formattedPrice + '</div>';
                            }

                            $annualBtn.html(annualHtml);
                            $annualBtn.data('plan', annualPlan);
                        } else {
                            $('#crm-select-annual-plan').hide();
                        }

                        // Bind plan selection handlers
                        self.bindPlanSelectionHandlers();
                    } else {
                        self.showError('Unable to load membership plans. Please try again.');
                    }
                },
                error: function() {
                    self.showError('Unable to load membership plans. Please try again.');
                }
            });
        },

        bindPlanSelectionHandlers: function() {
            var self = this;

            $('#crm-select-featured-plan, #crm-select-annual-plan').off('click').on('click', function() {
                var plan = $(this).data('plan');
                if (plan && plan.shortcode) {
                    self.showAuthState3(plan);
                } else if (plan && plan.url) {
                    // No shortcode, redirect to URL
                    window.location.href = plan.url;
                }
            });

            $('#crm-auth-back-to-plans').off('click').on('click', function() {
                self.backToAuthState2();
            });

            $('#crm-auth-skip-upgrade').off('click').on('click', function() {
                // User chose to skip upgrade, close modal
                self.hideAuthModal();
            });
        },

        showAuthState3: function(plan) {
            var self = this;

            console.log('showAuthState3 called with plan:', plan);

            // Hide State 2
            $('.sffc-crm-auth-plan-selection').hide();

            // Show State 3
            $('.sffc-crm-auth-membership-form').show();

            // Inject MemberPress shortcode
            var $container = $('#crm-memberpress-form-container');
            $container.html('<div class="sffc-crm-loading">Loading form...</div>');

            console.log('Sending AJAX request with shortcode:', plan.shortcode);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_render_membership_form',
                    nonce: this.config.nonce,
                    shortcode: plan.shortcode
                },
                success: function(response) {
                    if (response && response.success && response.data && response.data.html) {
                        $container.html(response.data.html);

                        // Pre-fill form with saved user data
                        self.prefillMemberPressForm();
                    } else {
                        var errorMsg = 'Unable to load membership form. Please try again.';
                        if (response && response.data && response.data.message) {
                            errorMsg = response.data.message;
                        }
                        // Log debug info to console
                        if (response && response.data && response.data.debug) {
                            console.error('Membership form error:', response.data);
                        }
                        $container.html('<p class="sffc-crm-error">' + self.escapeHtml(errorMsg) + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error rendering membership form:', status, error, xhr.responseText);
                    var errorMsg = 'Unable to load membership form. Please try again.';
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response && response.data && response.data.message) {
                            errorMsg = response.data.message;
                        }
                        if (response && response.data && response.data.debug) {
                            console.error('Debug info:', response.data);
                        }
                    } catch (e) {
                        // Ignore JSON parse errors
                    }
                    $container.html('<p class="sffc-crm-error">' + self.escapeHtml(errorMsg) + '</p>');
                }
            });
        },

        prefillMemberPressForm: function() {
            var userData = sessionStorage.getItem('sffc_crm_signup_data');
            if (!userData) return;

            try {
                var data = JSON.parse(userData);

                // Try common MemberPress field selectors
                var firstNameSelectors = ['input[name="user_first_name"]', 'input[name="first_name"]', '#user_first_name', '#first_name'];
                var lastNameSelectors = ['input[name="user_last_name"]', 'input[name="last_name"]', '#user_last_name', '#last_name'];
                var emailSelectors = ['input[name="user_email"]', 'input[name="email"]', '#user_email', '#mepr_email'];

                firstNameSelectors.forEach(function(selector) {
                    $(selector).val(data.first_name);
                });

                lastNameSelectors.forEach(function(selector) {
                    $(selector).val(data.last_name);
                });

                emailSelectors.forEach(function(selector) {
                    $(selector).val(data.email);
                });
            } catch (e) {
                console.error('Error pre-filling form:', e);
            }
        },

        backToAuthState2: function() {
            // Hide State 3
            $('.sffc-crm-auth-membership-form').hide();
            $('#crm-memberpress-form-container').html('');

            // Show State 2
            $('.sffc-crm-auth-plan-selection').show();
        },

        renderErrorState: function(message, retryCallback) {
            var self = this;
            var html = '<div class="sffc-crm-error-state">';
            html += '<div class="sffc-crm-error-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>';
            html += '<p class="sffc-crm-error-message">' + this.escapeHtml(message || 'Something went wrong. Please try again.') + '</p>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-retry-btn">Retry</button>';
            html += '</div>';

            // Bind retry callback
            if (retryCallback) {
                setTimeout(function() {
                    $('.sffc-crm-retry-btn').off('click').on('click', retryCallback);
                }, 0);
            }

            return html;
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 4000;

            // Create container if not exists
            if (!$('.sffc-crm-toast-container').length) {
                $('body').append('<div class="sffc-crm-toast-container"></div>');
            }

            var $toast = $('<div class="sffc-crm-toast ' + type + '">' +
                '<span>' + this.escapeHtml(message) + '</span>' +
                '<button class="sffc-crm-toast-close">&times;</button>' +
                '</div>');

            $('.sffc-crm-toast-container').append($toast);

            // Auto-remove after duration
            var removeTimeout = setTimeout(function() {
                $toast.fadeOut(300, function() { $(this).remove(); });
            }, duration);

            // Manual close
            $toast.find('.sffc-crm-toast-close').on('click', function() {
                clearTimeout(removeTimeout);
                $toast.fadeOut(300, function() { $(this).remove(); });
            });
        },

        /**
         * Improved showSuccess using toast
         */
        showSuccess: function(message) {
            this.showToast(message, 'success');
        },

        /**
         * Improved showError using toast
         */
        showError: function(message) {
            this.showToast(message, 'error', 6000);
        },

        /**
         * Show warning toast
         */
        showWarning: function(message) {
            this.showToast(message, 'warning', 5000);
        },

        /**
         * Update bulk action bar
         */
        updateBulkBar: function() {
            var $checked = $('.sffc-crm-select-checkbox:checked');
            var count = $checked.length;

            if (count === 0) {
                $('.sffc-crm-bulk-bar').remove();
                return;
            }

            var $bar = $('.sffc-crm-bulk-bar');

            if (!$bar.length) {
                var html = '<div class="sffc-crm-bulk-bar">';
                html += '<span class="sffc-crm-bulk-count"><span class="count">' + count + '</span> selected</span>';
                html += '<div class="sffc-crm-bulk-actions">';
                html += '<button class="sffc-crm-bulk-btn" data-action="reach-out">Send CV</button>';
                html += '<button class="sffc-crm-bulk-btn" data-action="add-sequence">Add to Sequence</button>';
                html += '<button class="sffc-crm-bulk-btn" data-action="save">Save</button>';
                html += '</div>';
                html += '<button class="sffc-crm-bulk-close">&times;</button>';
                html += '</div>';

                $('body').append(html);
                this.bindBulkBarEvents();
            } else {
                $bar.find('.count').text(count);
            }
        },

        /**
         * Bind bulk bar events
         */
        bindBulkBarEvents: function() {
            var self = this;

            $(document).off('click.bulkbar').on('click.bulkbar', '.sffc-crm-bulk-bar .sffc-crm-bulk-btn', function() {
                var action = $(this).data('action');
                var selectedIds = [];

                $('.sffc-crm-select-checkbox:checked').each(function() {
                    selectedIds.push($(this).closest('[data-id]').data('id'));
                });

                switch (action) {
                    case 'reach-out':
                        self.showBulkComposeModal(selectedIds);
                        break;
                    case 'add-sequence':
                        self.showAddToSequenceModal(selectedIds);
                        break;
                    case 'save':
                        self.bulkSave(selectedIds);
                        break;
                }
            });

            $(document).off('click.bulkclose').on('click.bulkclose', '.sffc-crm-bulk-close', function() {
                self.clearSelection();
            });
        },

        /**
         * Show loading overlay
         */
        showLoading: function(message) {
            var $loading = $('<div class="sffc-crm-loading-overlay"><div class="sffc-crm-loading-spinner"></div><p>' + (message || 'Loading...') + '</p></div>');
            $('body').append($loading);
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('.sffc-crm-loading-overlay').remove();
        },

        /**
         * Show bulk compose modal for multiple recruiters
         */
        showBulkComposeModal: function(recruiterIds) {
            this.showError('Bulk compose is available in the Pro plan');
        },

        /**
         * Show add to sequence modal for multiple recruiters
         */
        showAddToSequenceModal: function(recruiterIds) {
            this.showError('Bulk sequence enrollment is available in the Pro plan');
        },

        /**
         * Show create sequence modal
         */
        showCreateSequenceModal: function() {
            this.showError('Create sequence from the Sequences tab');
        },

        /**
         * Bulk save recruiters
         */
        bulkSave: function(recruiterIds) {
            var self = this;
            var saved = 0;

            recruiterIds.forEach(function(id) {
                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_save_recruiter',
                        recruiter_id: id,
                        nonce: self.config.nonce
                    },
                    success: function() {
                        saved++;
                        if (saved === recruiterIds.length) {
                            self.showSuccess(saved + ' recruiters saved');
                            self.clearSelection();
                        }
                    }
                });
            });
        },

        /**
         * Onboarding tour steps
         */
        tourSteps: [
            {
                target: '.sffc-crm-tabs',
                title: 'Navigation',
                content: 'Use these tabs to navigate your CRM: Matches shows opportunities matched to your CV, Contacts manages your recruiter network, Pipeline tracks applications, and more.',
                position: 'bottom'
            },
            {
                target: '.sffc-crm-match-row:first',
                title: 'CV-Matched Opportunities',
                content: 'Each match shows a job opportunity scored against your CV. The circular indicator shows your match percentage. Click to analyze the gap or use the buttons to take action.',
                position: 'right'
            },
            {
                target: '.sffc-crm-save-btn:first',
                title: 'Save for Later',
                content: 'Click the bookmark icon to save opportunities you want to review later. Access them anytime from the Saved tab.',
                position: 'left'
            },
            {
                target: '.sffc-crm-header-actions',
                title: 'Quick Actions',
                content: 'Access your account settings, notifications, and other tools from the header menu.',
                position: 'bottom'
            }
        ],

        /**
         * Start onboarding tour
         */
        startOnboardingTour: function() {
            var self = this;
            this.tourCurrentStep = 0;

            this.showTourStep(0);
        },

        /**
         * Show tour step
         */
        showTourStep: function(stepIndex) {
            var self = this;
            var step = this.tourSteps[stepIndex];

            if (!step) {
                this.endOnboardingTour();
                return;
            }

            var $target = $(step.target);
            if (!$target.length) {
                // Skip if target not found
                this.showTourStep(stepIndex + 1);
                return;
            }

            // Remove any existing tour elements
            $('.sffc-crm-tour-overlay, .sffc-crm-tour-tooltip').remove();

            // Add overlay
            $('body').append('<div class="sffc-crm-tour-overlay"></div>');

            // Highlight target
            $target.addClass('sffc-crm-tour-highlight');

            // Create tooltip
            var html = '<div class="sffc-crm-tour-tooltip ' + step.position + '">';
            html += '<div class="sffc-crm-tour-step">Step ' + (stepIndex + 1) + ' of ' + this.tourSteps.length + '</div>';
            html += '<h4 class="sffc-crm-tour-title">' + step.title + '</h4>';
            html += '<p class="sffc-crm-tour-content">' + step.content + '</p>';
            html += '<div class="sffc-crm-tour-actions">';
            html += '<div class="sffc-crm-tour-progress">';
            for (var i = 0; i < this.tourSteps.length; i++) {
                var dotClass = i < stepIndex ? 'completed' : (i === stepIndex ? 'active' : '');
                html += '<div class="sffc-crm-tour-dot ' + dotClass + '"></div>';
            }
            html += '</div>';
            html += '<div class="sffc-crm-tour-buttons">';
            if (stepIndex > 0) {
                html += '<button class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-tour-prev">Back</button>';
            }
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-tour-next">' +
                (stepIndex === this.tourSteps.length - 1 ? 'Finish' : 'Next') + '</button>';
            html += '</div></div></div>';

            $('body').append(html);

            // Position tooltip
            var $tooltip = $('.sffc-crm-tour-tooltip');
            var targetOffset = $target.offset();
            var targetWidth = $target.outerWidth();
            var targetHeight = $target.outerHeight();
            var tooltipWidth = $tooltip.outerWidth();
            var tooltipHeight = $tooltip.outerHeight();
            var viewportWidth = $(window).width();
            var viewportHeight = $(window).height();
            var scrollTop = $(window).scrollTop();
            var scrollLeft = $(window).scrollLeft();
            var padding = 16;
            var tooltipTop, tooltipLeft;

            switch (step.position) {
                case 'bottom':
                    tooltipTop = targetOffset.top + targetHeight + padding;
                    tooltipLeft = targetOffset.left + (targetWidth / 2) - (tooltipWidth / 2);
                    break;
                case 'top':
                    tooltipTop = targetOffset.top - tooltipHeight - padding;
                    tooltipLeft = targetOffset.left + (targetWidth / 2) - (tooltipWidth / 2);
                    break;
                case 'right':
                    tooltipTop = targetOffset.top + (targetHeight / 2) - (tooltipHeight / 2);
                    tooltipLeft = targetOffset.left + targetWidth + padding;
                    break;
                case 'left':
                    tooltipTop = targetOffset.top + (targetHeight / 2) - (tooltipHeight / 2);
                    tooltipLeft = targetOffset.left - tooltipWidth - padding;
                    break;
            }

            // Ensure tooltip stays within viewport bounds
            // Horizontal bounds
            if (tooltipLeft < scrollLeft + padding) {
                tooltipLeft = scrollLeft + padding;
            } else if (tooltipLeft + tooltipWidth > scrollLeft + viewportWidth - padding) {
                tooltipLeft = scrollLeft + viewportWidth - tooltipWidth - padding;
            }

            // Vertical bounds
            if (tooltipTop < scrollTop + padding) {
                tooltipTop = scrollTop + padding;
            } else if (tooltipTop + tooltipHeight > scrollTop + viewportHeight - padding) {
                tooltipTop = scrollTop + viewportHeight - tooltipHeight - padding;
            }

            $tooltip.css({
                top: tooltipTop,
                left: tooltipLeft
            });

            // Bind navigation
            // prevent double-binding by namespacing events
            $(document).off('.crmtour');

            $('.sffc-crm-tour-next').on('click.crmtour', function() {
                $target.removeClass('sffc-crm-tour-highlight');
                self.showTourStep(stepIndex + 1);
            });

            $('.sffc-crm-tour-prev').on('click.crmtour', function() {
                $target.removeClass('sffc-crm-tour-highlight');
                self.showTourStep(stepIndex - 1);
            });

            // Close on overlay click
            $('.sffc-crm-tour-overlay').on('click.crmtour', function() {
                self.endOnboardingTour();
            });
        },

        /**
         * End onboarding tour
         */
        endOnboardingTour: function() {
            $('.sffc-crm-tour-overlay, .sffc-crm-tour-tooltip').remove();
            $('.sffc-crm-tour-highlight').removeClass('sffc-crm-tour-highlight');
            $(document).off('.crmtour');

            // Mark as completed
            if (this.config.userId) {
                $.post(this.config.ajaxUrl, {
                    action: 'sffc_crm_complete_onboarding',
                    nonce: this.config.nonce
                });
            }

            // Store locally
            localStorage.setItem('sffc_crm_onboarding_complete', '1');
        },

        /**
         * Check if should show onboarding
         */
        shouldShowOnboarding: function() {
            // Check if tour already completed
            if (localStorage.getItem('sffc_crm_onboarding_complete') === '1') {
                return false;
            }

            // Check if this is first time seeing matches after CV upload
            var seenMatchesBefore = localStorage.getItem('sffc_crm_seen_matches');
            return !seenMatchesBefore;
        },

        /**
         * Format relative time
         */
        formatRelativeTime: function(dateStr) {
            if (!dateStr) return '';

            var date = new Date(dateStr);
            var now = new Date();
            var diff = now - date;
            var seconds = Math.floor(diff / 1000);
            var minutes = Math.floor(seconds / 60);
            var hours = Math.floor(minutes / 60);
            var days = Math.floor(hours / 24);

            if (days > 7) {
                return date.toLocaleDateString();
            } else if (days > 0) {
                return days + 'd ago';
            } else if (hours > 0) {
                return hours + 'h ago';
            } else if (minutes > 0) {
                return minutes + 'm ago';
            } else {
                return 'Just now';
            }
        },

        getRoleSnippet: function(item) {
            if (!item) {
                return '';
            }

            var sources = [
                item.content_snippet,
                item.job_snippet,
                item.summary,
                item.role_summary,
                item.job_summary,
                item.description,
                item.job_description,
                item.short_description,
                item.content
            ];

            var raw = '';
            for (var i = 0; i < sources.length; i++) {
                var val = sources[i];
                if (typeof val === 'string' && val.trim()) {
                    raw = val;
                    break;
                }
            }

            if (!raw) {
                return '';
            }

            raw = raw.replace(/<[^>]+>/g, ' ');
            raw = raw.replace(/&nbsp;/gi, ' ');
            raw = raw.replace(/\s+/g, ' ').trim();

            if (!raw) {
                return '';
            }

            if (raw.length > 220) {
                raw = raw.substring(0, 217).trim() + '…';
            }

            return raw;
        },

        // ============================================
        // MATCHES TAB
        // ============================================

        matchesLoaded: false,

        loadMatches: function() {
            var self = this;
            var $panel = $('#panel-matches');

            if (this.matchesLoaded) {
                return;
            }

            // Show analysis loader with steps
            var loaderHtml = '<div class="inst-analysis-loader" data-loader="matches" style="display: flex;">';
            loaderHtml += '<div class="inst-loader-content">';
            loaderHtml += '<div class="inst-loader-icon">';
            loaderHtml += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            loaderHtml += '<circle cx="12" cy="12" r="10" stroke-opacity="0.2"/>';
            loaderHtml += '<path d="M12 2a10 10 0 0 1 10 10" class="inst-loader-spinner"/>';
            loaderHtml += '</svg>';
            loaderHtml += '</div>';
            loaderHtml += '<div class="inst-loader-percentage" data-loader-percent>0%</div>';
            loaderHtml += '<div class="inst-loader-status" data-loader-status>Initializing...</div>';
            loaderHtml += '<div class="inst-loader-bar">';
            loaderHtml += '<div class="inst-loader-bar-fill" data-loader-bar></div>';
            loaderHtml += '</div>';
            loaderHtml += '<div class="inst-loader-steps">';
            loaderHtml += '<div class="inst-loader-step" data-step="profile">';
            loaderHtml += '<span class="inst-loader-step-icon"></span>';
            loaderHtml += '<span class="inst-loader-step-text">Analysing Profile</span>';
            loaderHtml += '</div>';
            loaderHtml += '<div class="inst-loader-step" data-step="jobs">';
            loaderHtml += '<span class="inst-loader-step-icon"></span>';
            loaderHtml += '<span class="inst-loader-step-text">Evaluating Job Descriptions</span>';
            loaderHtml += '</div>';
            loaderHtml += '<div class="inst-loader-step" data-step="skills">';
            loaderHtml += '<span class="inst-loader-step-icon"></span>';
            loaderHtml += '<span class="inst-loader-step-text">Matching Skills & Experience</span>';
            loaderHtml += '</div>';
            loaderHtml += '<div class="inst-loader-step" data-step="score">';
            loaderHtml += '<span class="inst-loader-step-icon"></span>';
            loaderHtml += '<span class="inst-loader-step-text">Calculating Compatibility</span>';
            loaderHtml += '</div>';
            loaderHtml += '<div class="inst-loader-step" data-step="results">';
            loaderHtml += '<span class="inst-loader-step-icon"></span>';
            loaderHtml += '<span class="inst-loader-step-text">Preparing Results</span>';
            loaderHtml += '</div>';
            loaderHtml += '</div>';
            loaderHtml += '</div>';
            loaderHtml += '</div>';

            $panel.html(loaderHtml);

            // Simulate progress animation
            this.animateMatchesLoader();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_matches',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        try {
                            var items = (response.data && response.data.items) ? response.data.items : [];
                            var semanticCount = items.filter(function(item) {
                                return item && typeof item.semantic_score === 'number';
                            }).length;
                            console.log('[MENA Careers CRM] Matches loaded:', items.length, 'Semantic scored:', semanticCount);
                        } catch (err) {
                            console.warn('[MENA Careers CRM] Unable to inspect matches payload:', err);
                        }
                        self.matchesLoaded = true;
                        self.renderMatches(response.data);

                        // Update matches badge count (always update, even if 0)
                        var count = response.data ? response.data.length : 0;
                        self.updateTabBadge('matches', count);
                    } else {
                        $panel.html('<div class="sffc-crm-empty">Failed to load matches</div>');
                    }
                },
                error: function() {
                    $panel.html('<div class="sffc-crm-empty">Failed to load matches</div>');
                }
            });
        },

        animateMatchesLoader: function() {
            var steps = [
                { name: 'profile', percent: 20, status: 'Analysing Profile...', delay: 300 },
                { name: 'jobs', percent: 40, status: 'Evaluating Job Descriptions...', delay: 600 },
                { name: 'skills', percent: 60, status: 'Matching Skills & Experience...', delay: 800 },
                { name: 'score', percent: 80, status: 'Calculating Compatibility...', delay: 1000 },
                { name: 'results', percent: 95, status: 'Preparing Results...', delay: 1200 }
            ];

            var currentStep = 0;
            var $panel = $('#panel-matches');

            function updateStep() {
                if (currentStep < steps.length) {
                    var step = steps[currentStep];

                    // Update progress
                    $panel.find('[data-loader-percent]').text(step.percent + '%');
                    $panel.find('[data-loader-status]').text(step.status);
                    $panel.find('[data-loader-bar]').css('width', step.percent + '%');

                    // Mark step as active
                    $panel.find('[data-step="' + step.name + '"]').addClass('is-active');

                    // Mark previous steps as complete
                    if (currentStep > 0) {
                        $panel.find('[data-step="' + steps[currentStep - 1].name + '"]')
                            .removeClass('is-active')
                            .addClass('is-complete');
                    }

                    currentStep++;
                    setTimeout(updateStep, step.delay);
                }
            }

            setTimeout(updateStep, 500);
        },

        // ============================================
        // ALL ROLES TAB
        // ============================================

        allRolesLoaded: false,
        allRolesData: null,
        allRolesDisplayCount: 15,

        loadAllRoles: function() {
            var self = this;
            var $panel = $('#panel-all-roles');

            if (this.allRolesLoaded) {
                return;
            }

            // Show simple loader
            $panel.html('<div class="sffc-crm-loading">Loading all roles...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_all_roles',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.allRolesLoaded = true;
                        self.allRolesData = response.data; // Store all data
                        self.allRolesDisplayCount = 15; // Reset display count
                        self.renderAllRoles(response.data);

                        // Update all-roles badge count
                        var count = response.data && response.data.items ? response.data.items.length : 0;
                        self.updateTabBadge('all-roles', count);
                    } else {
                        $panel.html('<div class="sffc-crm-empty">Failed to load roles</div>');
                    }
                },
                error: function() {
                    $panel.html('<div class="sffc-crm-empty">Failed to load roles</div>');
                }
            });
        },

        renderAllRoles: function(data) {
            var self = this;
            var $panel = $('#panel-all-roles');
            var html = '';

            // ALL ROLES VIEW - Show Actively Hiring badge for non-early bird posts
            if (!data || !data.items || data.items.length === 0) {
                html = '<div class="sffc-crm-empty">';
                html += '<div class="sffc-crm-empty-icon">';
                html += '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">';
                html += '<circle cx="12" cy="12" r="10"></circle>';
                html += '<line x1="12" y1="8" x2="12" y2="12"></line>';
                html += '<line x1="12" y1="16" x2="12.01" y2="16"></line>';
                html += '</svg>';
                html += '</div>';
                html += '<h3>No roles found</h3>';
                html += '<p>No job posts are currently available. Check back later!</p>';
                html += '</div>';
                $panel.html(html);
                return;
            }

            var totalItems = data.items.length;
            var itemsToShow = Math.min(this.allRolesDisplayCount, totalItems);

            // Header
            html += '<div class="sffc-crm-matches-header">';
            html += '<h3>All Available Roles</h3>';
            html += '<p>Showing ' + itemsToShow + ' of ' + totalItems + ' available opportunities</p>';
            html += '</div>';

            // Roles list (same layout as matches but without match indicator)
            html += '<div class="sffc-crm-matches-list">';

            // Only show first N items
            data.items.slice(0, itemsToShow).forEach(function(item) {
                html += self.buildStandardMatchRow(item, {
                    checkboxPrefix: 'role-select'
                });
            });

            html += '</div>'; // Close matches-list

            // Load More button (show if there are more items to display)
            if (itemsToShow < totalItems) {
                html += '<div class="sffc-crm-load-more" id="all-roles-load-more">';
                html += '<button class="sffc-crm-btn sffc-crm-btn-secondary" id="load-more-all-roles">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">';
                html += '<circle cx="12" cy="12" r="10"></circle>';
                html += '<polyline points="8 12 12 16 16 12"></polyline>';
                html += '<line x1="12" y1="8" x2="12" y2="16"></line>';
                html += '</svg>';
                html += 'Load More (' + (totalItems - itemsToShow) + ' remaining)';
                html += '</button>';
                html += '</div>';
            }

            // Floating action buttons (hidden by default, shown when items are selected)
            html += '<div class="sffc-crm-floating-actions" style="display: none;">';
            html += '<div class="sffc-crm-floating-actions-content">';
            html += '<div class="sffc-crm-floating-actions-count">';
            html += '<span class="sffc-crm-selected-count">0</span> selected';
            html += '</div>';
            html += '<div class="sffc-crm-floating-actions-buttons">';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-floating-action-btn" id="bulk-request-intros-all-roles">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<path d="M9 11l3 3L22 4"></path>';
            html += '<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>';
            html += '</svg>';
            html += 'Add to Outreach List';
            html += '</button>';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-floating-action-btn" id="bulk-add-to-list-all-roles">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>';
            html += '</svg>';
            html += 'Add to Watchlist';
            html += '</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            $panel.html(html);

            // Bind events for all roles
            this.bindAllRolesEvents();
            this.refreshSmartApplyButtonStates();
        },

        buildStandardMatchRow: function(item, options) {
            options = options || {};
            var self = this;
            var postId = item && (item.id || item.post_id);
            if (!postId) {
                return '';
            }

            var checkboxPrefix = options.checkboxPrefix || 'match-select';
            var matchScore = Math.round(item.match_score || item.matchScore || 0);
            var matchColor = this.getMatchColor(matchScore);
            var radius = 35;
            var angle = (matchScore / 100) * 360;
            var radians = (angle - 90) * Math.PI / 180;
            var scoreX = 40 + radius * Math.cos(radians);
            var scoreY = 40 + radius * Math.sin(radians);
            var isSaved = item.is_saved == 1;
            var isEarlyBird = !!item.is_early_bird;
            var recruiterName = item.recruiter_name || 'Unknown Recruiter';
            var canViewContact = this.config.isPremium || !isEarlyBird;
            var recruiterPhoto = '';
            if (canViewContact) {
                recruiterPhoto = item.recruiter_photo || '';
            } else if (isEarlyBird && this.earlyBirdAvatars && this.earlyBirdAvatars.length > 0) {
                recruiterPhoto = this.earlyBirdAvatars[postId % this.earlyBirdAvatars.length];
            }
            var recruiterInitial = (recruiterName.charAt(0) || 'R').toUpperCase();
            var recruiterLinkedIn = item.recruiter_linkedin_url || item.recruiterLinkedIn || item.linkedin_url || '';
            var recruiterEmail = item.recruiter_email || '';
            var recruiterFirm = item.recruiter_firm || '';
            var recruiterTitle = item.recruiter_title || '';
            var nameParts = recruiterName.split(' ');
            var recruiterDisplayName = recruiterName;
            if (nameParts.length >= 2) {
                var firstName = nameParts[0];
                var lastInitial = nameParts[nameParts.length - 1].charAt(0).toUpperCase();
                recruiterDisplayName = firstName + ' ' + lastInitial + '.';
            }
            var recruiterDisplayFirm = recruiterFirm || item.recruiter_display_company || item.company || '';
            var recruiterPublicFirm = item.recruiter_display_company || item.company || 'Confidential Search Firm';
            var responseLabel = (item.response_label ? String(item.response_label) : '').trim();

            var keywords = [];
            if (Array.isArray(item.match_keywords)) {
                keywords = item.match_keywords;
            } else if (Array.isArray(item.keywords)) {
                keywords = item.keywords;
            }
            var keywordAttr = keywords.join(',');

            var html = '';
            html += '<div class="sffc-crm-match-row" data-post-id="' + postId + '" data-match-score="' + matchScore + '" data-recruiter-id="' + (item.recruiter_id || '') + '" data-recruiter-name="' + self.escapeHtml(recruiterName) + '" data-company="' + self.escapeHtml(item.company || '') + '" data-location="' + self.escapeHtml(item.location || '') + '" data-sector="' + self.escapeHtml(item.sector || '') + '" data-seniority="' + self.escapeHtml(item.seniority || '') + '" data-salary-text="' + self.escapeHtml(item.salary_text || '') + '" data-posted="' + self.escapeHtml(item.posted_at || '') + '" data-recruiter-firm="' + self.escapeHtml(recruiterFirm) + '" data-recruiter-title="' + self.escapeHtml(recruiterTitle) + '" data-recruiter-linkedin="' + self.escapeHtml(recruiterLinkedIn) + '" data-recruiter-email="' + self.escapeHtml(recruiterEmail) + '" data-early-bird="' + (isEarlyBird ? 1 : 0) + '" data-keywords="' + self.escapeHtml(keywordAttr) + '">';

            html += '<div class="sffc-crm-match-checkbox">';
            html += '<input type="checkbox" class="sffc-crm-match-select" id="' + checkboxPrefix + '-' + postId + '" data-post-id="' + postId + '">';
            html += '<label for="' + checkboxPrefix + '-' + postId + '">';
            html += '<svg class="sffc-crm-checkbox-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            html += '</label>';
            html += '</div>';

            html += '<div class="sffc-crm-match-indicator">';
            html += '<div class="sffc-crm-match-circle-container">';
            html += '<svg class="sffc-crm-match-circle" width="80" height="80" viewBox="0 0 80 80">';
            html += '<circle class="sffc-crm-match-circle-bg" cx="40" cy="40" r="35" fill="none" stroke="#e5e7eb" stroke-width="5"></circle>';
            html += '<circle class="sffc-crm-match-circle-fg" cx="40" cy="40" r="35" fill="none" stroke="' + matchColor + '" stroke-width="5" stroke-dasharray="' + (matchScore * 2.199) + ' 219.91" stroke-linecap="round" transform="rotate(-90 40 40)"></circle>';
            html += '</svg>';
            html += '<div class="sffc-crm-match-avatar' + (isEarlyBird && !self.config.isPremium ? ' sffc-crm-avatar-blurred' : '') + '">';
            if (recruiterPhoto) {
                html += '<img src="' + self.escapeHtml(recruiterPhoto) + '" alt="' + self.escapeHtml(recruiterName) + '" data-initial="' + self.escapeHtml(recruiterInitial) + '">';
            } else {
                html += '<div class="sffc-crm-avatar-placeholder">' + recruiterInitial + '</div>';
            }
            html += '</div>';
            html += '<div class="sffc-crm-match-score" style="left: ' + scoreX + 'px; top: ' + scoreY + 'px; color: ' + matchColor + '; border-color: ' + matchColor + ';">' + matchScore + '%</div>';
            html += '</div>';
            html += '<div class="sffc-crm-match-recruiter-name">' + self.escapeHtml(recruiterDisplayName) + '</div>';
            if (self.config.isPremium && recruiterDisplayFirm) {
                html += '<div class="sffc-crm-match-recruiter-meta">' + self.escapeHtml(recruiterDisplayFirm) + '</div>';
            } else if (!self.config.isPremium) {
                html += '<div class="sffc-crm-match-recruiter-meta">' + self.escapeHtml(recruiterPublicFirm) + '</div>';
            } else if (!recruiterDisplayFirm && canViewContact) {
                html += '<div class="sffc-crm-match-recruiter-meta">' + self.escapeHtml(recruiterPublicFirm) + '</div>';
            }
            html += '</div>';

            html += '<div class="sffc-crm-match-content">';
            html += '<div class="sffc-crm-match-header">';
            html += '<h4 class="sffc-crm-match-title">' + self.escapeHtml(item.role_title || 'Untitled');
            if (isEarlyBird) {
                html += ' <span class="sffc-crm-early-bird-badge">Pro+ Members</span>';
            } else {
                var badgeText = responseLabel || 'Actively Hiring';
                html += ' <span class="sffc-crm-free-contact-badge">' + self.escapeHtml(badgeText) + '</span>';
            }
            html += '</h4>';

            var metaParts = [];
            if (item.company) metaParts.push(self.escapeHtml(item.company));
            if (item.location) metaParts.push(self.escapeHtml(item.location));
            if (metaParts.length > 0) {
                html += '<div class="sffc-crm-match-meta">' + metaParts.join(' • ') + '</div>';
            }
            var snippetText = self.getRoleSnippet(item);
            if (snippetText) {
                html += '<p class="sffc-crm-match-snippet">' + self.escapeHtml(snippetText) + '</p>';
            }
            html += '</div>';

            if (item.match_reasons && item.match_reasons.length) {
                html += '<ul class="sffc-crm-match-reasons">';
                item.match_reasons.slice(0, 3).forEach(function(reason) {
                    html += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>' + self.escapeHtml(reason) + '</span></li>';
                });
                html += '</ul>';
            }

            if (item.match_warnings && item.match_warnings.length) {
                html += '<div class="sffc-crm-match-warning">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg>';
                html += '<span>' + self.escapeHtml(item.match_warnings[0]) + '</span>';
                html += '</div>';
            }

            if (item.gaps && Array.isArray(item.gaps)) {
                item.gaps.filter(function(gap) {
                    return gap.type === 'nationality_requirement';
                }).forEach(function(gap) {
                    html += '<div class="sffc-crm-nationality-requirement">';
                    html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg>';
                    html += '<span><strong>Critical Requirement:</strong> ' + self.escapeHtml(gap.recommendation || gap.item || 'Nationality requirement') + '</span>';
                    html += '</div>';
                });
            }

            var recruiterFirstName = nameParts.length >= 1 ? nameParts[0] : 'Recruiter';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-match-message-btn sffc-crm-match-inline-btn" data-post-id="' + postId + '" data-recruiter-id="' + (item.recruiter_id || '') + '" data-recruiter-name="' + self.escapeHtml(recruiterName) + '" data-match-score="' + matchScore + '">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path></svg>';
            html += 'Message ' + self.escapeHtml(recruiterFirstName);
            html += '</button>';

            if (item.application_url) {
                html += '<a href="' + self.escapeHtml(item.application_url) + '" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-match-inline-btn sffc-crm-apply-btn" target="_blank" rel="noopener noreferrer">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>';
                html += 'External Apply (Free)';
                html += '</a>';
            }

            html += '</div>';

            html += '<div class="sffc-crm-match-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-icon sffc-crm-save-btn ' + (isSaved ? 'is-saved' : '') + '" data-action="save" data-post-id="' + postId + '" title="' + (isSaved ? 'Saved' : 'Save') + '">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="' + (isSaved ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>';
            html += '</button>';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-icon sffc-crm-app-toolkit-btn" data-post-id="' + postId + '" title="Application Toolkit">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="9"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>';
            html += '</button>';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-match-introduce-btn sffc-crm-match-inline-btn" data-post-id="' + postId + '" data-recruiter-id="' + (item.recruiter_id || '') + '" data-recruiter-name="' + self.escapeHtml(recruiterName) + '" data-match-score="' + matchScore + '">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><path d="M3 7h18M3 12h18M3 17h18"></path></svg>';
            html += 'View All Posts';
            html += '</button>';
            html += '</div>';

            html += '</div>';
            return html;
        },

        enforceEarlyBirdSelection: function($checkbox) {
            var $row = $checkbox.closest('.sffc-crm-match-row');
            if (!$row.length || parseInt($row.data('early-bird'), 10) !== 1) {
                return false;
            }

            $checkbox.prop('checked', false);
            $row.removeClass('is-selected');

            if (!this.config.isLoggedIn) {
                this.showAuthModal();
                return true;
            }

            if (!this.config.isPremium) {
                var jobTitle = $row.find('.sffc-crm-match-title').text().trim();
                var metaText = $row.find('.sffc-crm-match-meta').first().text().trim();
                var company = '';
                if (metaText) {
                    company = (metaText.split(' • ')[0] || '').trim();
                }

                this.showMonetizationModal('intro', {
                    jobTitle: jobTitle,
                    company: company
                });
                return true;
            }

            return false;
        },

        bindAllRolesEvents: function() {
            var self = this;

            // Row click - open gap analyzer
            $(document).off('click.allRoleRow', '#panel-all-roles .sffc-crm-match-row').on('click.allRoleRow', '#panel-all-roles .sffc-crm-match-row', function(e) {
                // Ignore clicks on buttons and checkboxes
                if ($(e.target).closest('button, .sffc-crm-match-checkbox').length) {
                    return;
                }

                if (!self.config.isLoggedIn) {
                    self.showAuthModal();
                    return;
                }

                var postId = $(this).data('post-id');
                if (postId) {
                    self.captureGapAnalyzerContextFromRow($(this));
                    self.openGapAnalyzerModal(postId);
                }
            });

            // Save button
            $(document).off('click.allRoleSave', '#panel-all-roles .sffc-crm-save-btn').on('click.allRoleSave', '#panel-all-roles .sffc-crm-save-btn', function(e) {
                e.stopPropagation();
                var $btn = $(this);
                var postId = $btn.data('post-id');
                var isSaved = $btn.hasClass('is-saved');

                if (isSaved) {
                    self.unsavePost(postId, $btn);
                } else {
                    self.savePost(postId, $btn);
                }
            });

            // Application Toolkit button
            $(document).off('click.allRoleToolkit', '#panel-all-roles .sffc-crm-app-toolkit-btn').on('click.allRoleToolkit', '#panel-all-roles .sffc-crm-app-toolkit-btn', function(e) {
                e.stopPropagation();
                var postId = $(this).data('post-id');
                self.openApplicationToolkit(postId);
            });

            // Express Interest button
            $(document).off('click.allRoleMessage', '#panel-all-roles .sffc-crm-match-message-btn').on('click.allRoleMessage', '#panel-all-roles .sffc-crm-match-message-btn', function(e) {
                e.stopPropagation();
                var $btn = $(this);
                var postId = $btn.data('post-id');
                var recruiterId = $btn.data('recruiter-id');
                var $matchRow = $btn.closest('.sffc-crm-match-row');

                if (!self.config.isLoggedIn) {
                    self.showAuthModal();
                    return;
                }

                // Extract job details for monetization modal
                var jobTitle = $matchRow.find('.sffc-crm-match-title').text().trim();
                var jobMeta = $matchRow.find('.sffc-crm-match-meta').text().trim();
                var metaParts = jobMeta.split(' • ');
                var company = metaParts[0] || '';
                var location = metaParts[1] || '';
                var recruiterName = $btn.data('recruiter-name') || '';
                var matchScore = $btn.data('match-score');
                if (typeof matchScore === 'undefined' || matchScore === '') {
                    matchScore = $matchRow.data('match-score');
                }
                matchScore = parseInt(matchScore, 10) || 0;

                // Extract Premium Members status and recruiter contact info
                var isEarlyBird = $matchRow.data('early-bird') == 1;
                var recruiterEmail = $matchRow.data('recruiter-email') || '';
                var recruiterLinkedIn = $matchRow.data('recruiter-linkedin') || '';
                var recruiterFirm = $matchRow.data('recruiter-firm') || '';
                var recruiterTitle = $matchRow.data('recruiter-title') || '';

                self.showExpressInterestModal(postId, recruiterId, recruiterName, matchScore, [], [], {
                    jobTitle: jobTitle,
                    company: company,
                    location: location,
                    recruiterEmail: recruiterEmail,
                    recruiterLinkedIn: recruiterLinkedIn,
                    recruiterFirm: recruiterFirm,
                    recruiterTitle: recruiterTitle,
                    recruiterPhoto: '',
                    recruiterInitial: recruiterName.charAt(0) || 'S',
                    keywords: [],
                    isEarlyBird: isEarlyBird
                });
            });

            // Checkbox selection
            $(document).off('change.allRoleSelect', '#panel-all-roles .sffc-crm-match-select').on('change.allRoleSelect', '#panel-all-roles .sffc-crm-match-select', function() {
                var $checkbox = $(this);
                var $row = $checkbox.closest('.sffc-crm-match-row');

                if ($checkbox.is(':checked')) {
                    if (self.enforceEarlyBirdSelection($checkbox)) {
                        return;
                    }
                    $row.addClass('is-selected');
                } else {
                    $row.removeClass('is-selected');
                }

                self.updateAllRolesFloatingActions();
            });

            // Bulk Add to Outreach List button
            $(document).off('click.bulkIntrosAllRoles', '#bulk-request-intros-all-roles').on('click.bulkIntrosAllRoles', '#bulk-request-intros-all-roles', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (!self.config.isLoggedIn) {
                    self.showAuthModal();
                    return;
                }

                if (!self.config.isPremium) {
                    self.showMembershipPrompt();
                    return;
                }

                self.showAddToOutreachListModal();
            });

            // Bulk Add to Watchlist button
            $(document).off('click.bulkAddListAllRoles', '#bulk-add-to-list-all-roles').on('click.bulkAddListAllRoles', '#bulk-add-to-list-all-roles', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (!self.config.isLoggedIn) {
                    self.showAuthModal();
                    return;
                }

                if (!self.config.isPremium) {
                    self.showMembershipPrompt();
                    return;
                }

                self.handleBulkAddToWatchlist();
            });

            // Load More button
            $(document).off('click.loadMoreAllRoles', '#load-more-all-roles').on('click.loadMoreAllRoles', '#load-more-all-roles', function() {
                self.loadMoreAllRoles();
            });
        },

        loadMoreAllRoles: function() {
            // Increase display count by 15
            this.allRolesDisplayCount += 15;

            // Re-render with updated count
            this.renderAllRoles(this.allRolesData);

            // Rebind events
            this.bindAllRolesEvents();
        },

        updateAllRolesFloatingActions: function() {
            var $panel = $('#panel-all-roles');
            var $selected = $panel.find('.sffc-crm-match-select:checked');
            var $floatingActions = $panel.find('.sffc-crm-floating-actions');

            if ($selected.length > 0) {
                $floatingActions.fadeIn(200);
            } else {
                $floatingActions.fadeOut(200);
                return;
            }

            var totalSelected = $selected.length;

            // Update count
            $panel.find('.sffc-crm-selected-count').text(totalSelected);

            // Both buttons are always enabled when items are selected
            var $outreachBtn = $('#bulk-request-intros-all-roles');
            var $watchlistBtn = $('#bulk-add-to-list-all-roles');

            $outreachBtn.prop('disabled', false).css('opacity', '1');
            $watchlistBtn.prop('disabled', false).css('opacity', '1');
        },

        handleBulkRequestIntrosAllRoles: function() {
            var self = this;
            if (!(self.config.features && self.config.features.ai_personalization)) {
                return;
            }
            if (!self.config.isLoggedIn || !self.config.isPremium) {
                if (!self.config.isLoggedIn) {
                    self.showAuthModal();
                } else {
                    self.showMonetizationModal('intro');
                }
                return;
            }
            var $panel = $('#panel-all-roles');
            var selectedPosts = [];

            // Collect eligible posts (60%+)
            $panel.find('.sffc-crm-match-select:checked').each(function() {
                var $row = $(this).closest('.sffc-crm-match-row');
                var matchScore = parseInt($row.data('match-score')) || 0;
                if (matchScore >= 60) {
                    selectedPosts.push({
                        postId: $row.data('post-id'),
                        recruiterId: $row.data('recruiter-id'),
                        recruiterName: $row.data('recruiter-name'),
                        matchScore: matchScore
                    });
                }
            });

            if (selectedPosts.length === 0) {
                this.showError('No eligible posts selected for Express Interest');
                return;
            }

            this.showBulkIntroConfirmation(selectedPosts);
        },

        handleBulkAddToListAllRoles: function() {
            var self = this;
            var $panel = $('#panel-all-roles');
            var selectedPosts = [];

            // Collect all selected posts
            $panel.find('.sffc-crm-match-select:checked').each(function() {
                var $row = $(this).closest('.sffc-crm-match-row');
                selectedPosts.push({
                    postId: $row.data('post-id'),
                    recruiterId: $row.data('recruiter-id'),
                    recruiterName: $row.data('recruiter-name')
                });
            });

            if (selectedPosts.length === 0) {
                this.showError('No posts selected');
                return;
            }

            // Open add to list modal
            this.openAddToListModal(selectedPosts);
        },

        showAddToOutreachListModal: function() {
            var self = this;
            var $panel = $('#panel-all-roles');
            var selectedPosts = [];

            // Collect all selected posts
            $panel.find('.sffc-crm-match-select:checked').each(function() {
                var $row = $(this).closest('.sffc-crm-match-row');
                selectedPosts.push({
                    postId: $row.data('post-id'),
                    title: $row.find('.sffc-crm-match-title').text().trim(),
                    company: $row.find('.sffc-crm-match-meta').text().split(' • ')[0] || ''
                });
            });

            if (selectedPosts.length === 0) {
                this.showError('No roles selected');
                return;
            }

            // Store selected posts for later use
            this.selectedPostsForOutreach = selectedPosts;
            this.selectedPostsSource = 'all-roles';

            // Fetch existing outreach lists
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_job_outreach_lists',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderJobOutreachListModal(response.data || []);
                    } else {
                        self.renderJobOutreachListModal([]);
                    }
                },
                error: function() {
                    self.renderJobOutreachListModal([]);
                }
            });
        },

        renderJobOutreachListModal: function(lists) {
            var self = this;
            var count = this.selectedPostsForOutreach.length;

            var html = '<div id="job-outreach-modal" class="sffc-crm-job-outreach-modal">';
            html += '<div class="sffc-crm-modal-header">';
            html += '<h3>Add ' + count + ' role' + (count !== 1 ? 's' : '') + ' to Outreach List</h3>';
            html += '<button class="sffc-crm-modal-close" id="close-outreach-modal">';
            html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<line x1="18" y1="6" x2="6" y2="18"></line>';
            html += '<line x1="6" y1="6" x2="18" y2="18"></line>';
            html += '</svg>';
            html += '</button>';
            html += '</div>';
            html += '<div class="sffc-crm-modal-body">';

            // New list option
            html += '<div class="sffc-crm-outreach-option sffc-crm-outreach-new">';
            html += '<div class="sffc-crm-outreach-option-header">';
            html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<line x1="12" y1="5" x2="12" y2="19"></line>';
            html += '<line x1="5" y1="12" x2="19" y2="12"></line>';
            html += '</svg>';
            html += '<span>Create New List</span>';
            html += '</div>';
            html += '<input type="text" class="sffc-crm-input" id="new-outreach-list-name" placeholder="Enter list name (e.g., \'Fintech Week 1\')" style="margin-top: 12px;">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="create-and-add-to-list" style="margin-top: 12px;">Create & Add</button>';
            html += '</div>';

            // Existing lists
            if (lists.length > 0) {
                html += '<div class="sffc-crm-outreach-divider"><span>or add to existing list</span></div>';
                html += '<div class="sffc-crm-outreach-lists">';
                lists.forEach(function(list) {
                    html += '<div class="sffc-crm-outreach-option sffc-crm-outreach-existing" data-list-id="' + list.id + '">';
                    html += '<div class="sffc-crm-outreach-option-content">';
                    html += '<div class="sffc-crm-list-icon">';
                    html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                    html += '<path d="M9 11l3 3L22 4"></path>';
                    html += '<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>';
                    html += '</svg>';
                    html += '</div>';
                    html += '<div>';
                    html += '<h4>' + self.escapeHtml(list.list_name) + '</h4>';
                    if (list.description) {
                        html += '<p>' + self.escapeHtml(list.description) + '</p>';
                    }
                    html += '<span class="sffc-crm-list-count">' + (list.job_count || 0) + ' roles</span>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';
            }

            html += '</div>';
            html += '</div>';

            this.showModal(html, 'sffc-crm-modal-content--outreach');

            var $modal = $('.sffc-crm-modal');
            $modal.find('#create-and-add-to-list').on('click', function() {
                var listName = $('#new-outreach-list-name').val().trim();
                if (!listName) {
                    self.showError('Please enter a list name');
                    return;
                }
                self.createAndAddToOutreachList(listName);
            });

            $modal.find('.sffc-crm-outreach-existing').on('click', function() {
                var listId = $(this).data('list-id');
                self.addToExistingOutreachList(listId);
            });
        },

        createAndAddToOutreachList: function(listName) {
            var self = this;
            var postIds = this.selectedPostsForOutreach.map(function(p) { return p.postId; });

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_create_job_outreach_list',
                    list_name: listName,
                    post_ids: postIds,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Created list and added ' + postIds.length + ' role' + (postIds.length !== 1 ? 's' : ''));
                        self.closeModal();
                        self.clearSelectedRolesAfterOutreach();
                        self.loadOutreachLists();
                    } else {
                        self.showError(response.data || 'Failed to create list');
                    }
                },
                error: function() {
                    self.showError('Error creating list');
                }
            });
        },

        addToExistingOutreachList: function(listId) {
            var self = this;
            var postIds = this.selectedPostsForOutreach.map(function(p) { return p.postId; });

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_add_jobs_to_outreach_list',
                    list_id: listId,
                    post_ids: postIds,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Added ' + postIds.length + ' role' + (postIds.length !== 1 ? 's' : '') + ' to list');
                        self.closeModal();
                        self.clearSelectedRolesAfterOutreach();
                        self.loadOutreachLists();
                    } else {
                        self.showError(response.data || 'Failed to add to list');
                    }
                },
                error: function() {
                    self.showError('Error adding to list');
                }
            });
        },

        clearSelectedRolesAfterOutreach: function() {
            var source = this.selectedPostsSource || 'all-roles';
            var $context = source === 'matches' ? $('#panel-matches') : $('#panel-all-roles');

            $context.find('.sffc-crm-match-select:checked').each(function() {
                var $checkbox = $(this);
                $checkbox.prop('checked', false);
                $checkbox.closest('.sffc-crm-match-row').removeClass('is-selected');
            });

            if (source === 'matches') {
                this.updateFloatingActions();
            } else {
                this.updateAllRolesFloatingActions();
            }

            this.selectedPostsForOutreach = [];
            this.selectedPostsSource = 'all-roles';
        },

        handleBulkAddToWatchlist: function() {
            var self = this;
            var $panel = $('#panel-all-roles');
            var postIds = [];

            // Collect all selected post IDs
            $panel.find('.sffc-crm-match-select:checked').each(function() {
                var $row = $(this).closest('.sffc-crm-match-row');
                var postId = $row.data('post-id');
                if (postId) {
                    postIds.push(postId);
                }
            });

            if (postIds.length === 0) {
                this.showError('No roles selected');
                return;
            }

            // Bulk save posts
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_bulk_save_posts',
                    post_ids: postIds,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Added ' + postIds.length + ' role' + (postIds.length !== 1 ? 's' : '') + ' to Watchlist');

                        // Update save button states for selected rows
                        $panel.find('.sffc-crm-match-select:checked').each(function() {
                            var $row = $(this).closest('.sffc-crm-match-row');
                            var $saveBtn = $row.find('.sffc-crm-save-btn');
                            $saveBtn.addClass('is-saved');
                            $saveBtn.find('svg').attr('fill', 'currentColor');
                            $saveBtn.attr('title', 'Saved to Watchlist');
                        });

                        // Uncheck all checkboxes
                        $panel.find('.sffc-crm-match-select:checked').prop('checked', false);
                        self.updateAllRolesFloatingActions();

                        // Force watchlist tab to reload
                        var $watchlistPanel = $('#panel-smart-apply');
                        if ($watchlistPanel.find('.sffc-crm-loading').length === 0) {
                            $watchlistPanel.html('<div class="sffc-crm-loading">Loading watchlist...</div>');
                        }
                    } else {
                        self.showError(response.data || 'Failed to add to Watchlist');
                    }
                },
                error: function() {
                    self.showError('Error adding to Watchlist');
                }
            });
        },

        showAddToOutreachListModalMatches: function() {
            var self = this;
            var selectedPosts = [];

            // Collect all selected posts from Matches tab
            $('.sffc-crm-match-select:checked').each(function() {
                var $row = $(this).closest('.sffc-crm-match-row');
                selectedPosts.push({
                    postId: $row.data('post-id'),
                    title: $row.find('.sffc-crm-match-title').text().trim(),
                    company: $row.find('.sffc-crm-match-meta').text().split(' • ')[0] || ''
                });
            });

            if (selectedPosts.length === 0) {
                this.showError('No roles selected');
                return;
            }

            // Store selected posts for later use
            this.selectedPostsForOutreach = selectedPosts;
            this.selectedPostsSource = 'matches';

            // Fetch existing outreach lists
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_job_outreach_lists',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderJobOutreachListModal(response.data || []);
                    } else {
                        self.renderJobOutreachListModal([]);
                    }
                },
                error: function() {
                    self.renderJobOutreachListModal([]);
                }
            });
        },

        handleBulkAddToWatchlistMatches: function() {
            var self = this;
            var postIds = [];

            // Collect all selected post IDs from Matches tab
            $('.sffc-crm-match-select:checked').each(function() {
                var $row = $(this).closest('.sffc-crm-match-row');
                var postId = $row.data('post-id');
                if (postId) {
                    postIds.push(postId);
                }
            });

            if (postIds.length === 0) {
                this.showError('No roles selected');
                return;
            }

            // Bulk save posts
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_bulk_save_posts',
                    post_ids: postIds,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Added ' + postIds.length + ' role' + (postIds.length !== 1 ? 's' : '') + ' to Watchlist');

                        // Update save button states for selected rows
                        $('.sffc-crm-match-select:checked').each(function() {
                            var $row = $(this).closest('.sffc-crm-match-row');
                            var $saveBtn = $row.find('.sffc-crm-save-btn');
                            $saveBtn.addClass('is-saved');
                            $saveBtn.find('svg').attr('fill', 'currentColor');
                            $saveBtn.attr('title', 'Saved to Watchlist');
                        });

                        // Uncheck all checkboxes
                        $('.sffc-crm-match-select:checked').prop('checked', false);
                        self.updateFloatingActions();

                        // Force watchlist tab to reload
                        var $watchlistPanel = $('#panel-smart-apply');
                        if ($watchlistPanel.find('.sffc-crm-loading').length === 0) {
                            $watchlistPanel.html('<div class="sffc-crm-loading">Loading watchlist...</div>');
                        }
                    } else {
                        self.showError(response.data || 'Failed to add to Watchlist');
                    }
                },
                error: function() {
                    self.showError('Error adding to Watchlist');
                }
            });
        },

        renderMatches: function(data) {
            var self = this;
            var $panel = $('#panel-matches');
            var html = '';

            // Show first-visit banner (only if no CV and banner not dismissed)
            var bannerDismissed = localStorage.getItem('sffc_crm_welcome_banner_dismissed');
            if (!data.has_cv && !bannerDismissed) {
                html += '<div class="sffc-crm-welcome-banner" id="sffc-crm-welcome-banner">';
                html += '<div class="sffc-crm-welcome-banner-content">';
                html += '<div class="sffc-crm-welcome-banner-icon">👋</div>';
                html += '<div class="sffc-crm-welcome-banner-text">';
                html += '<strong>New to MENA Careers? Here\'s how it works:</strong> ';
                html += '<span>1. Upload CV &nbsp;→&nbsp; 2. See Matches &nbsp;→&nbsp; 3. Get Introduced</span>';
                html += '</div>';
                html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-sm sffc-crm-welcome-banner-btn">Got it</button>';
                html += '<button type="button" class="sffc-crm-welcome-banner-close" aria-label="Close">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                html += '<line x1="18" y1="6" x2="6" y2="18"></line>';
                html += '<line x1="6" y1="6" x2="18" y2="18"></line>';
                html += '</svg>';
                html += '</button>';
                html += '</div>';
                html += '</div>';
            }

            if (!data.has_cv) {
                html = '<div class="sffc-crm-empty sffc-crm-empty-enhanced">';

                // Icon
                html += '<div class="sffc-crm-empty-icon">';
                html += '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">';
                html += '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>';
                html += '<path d="M9 11h6"></path><path d="M9 15h6"></path><path d="M9 7h6"></path>';
                html += '</svg>';
                html += '</div>';

                // Heading and value proposition
                html += '<h2 class="sffc-crm-empty-title">Discover Senior Finance Careers</h2>';
                html += '<p class="sffc-crm-empty-subtitle">MENA Careers analyzes your CV against finance roles, recruiter routes, and senior hiring signals.</p>';

                // Quick start box
                html += '<div class="sffc-crm-quick-start-box">';
                html += '<h3 class="sffc-crm-quick-start-title">Quick Start: Paste Your CV</h3>';
                html += '<form id="crm-quick-cv-form" class="sffc-crm-quick-cv-form">';
                html += '<textarea id="crm-quick-cv-content" class="sffc-crm-quick-cv-textarea" placeholder="Paste your CV text here..." rows="6"></textarea>';
                html += '<div class="sffc-crm-quick-cv-actions">';
                html += '<input type="file" id="sffc-crm-quick-cv-file" accept=".pdf,.doc,.docx,.txt" style="display: none;">';
                html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-upload-file-btn">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">';
                html += '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>';
                html += '<polyline points="17 8 12 3 7 8"></polyline>';
                html += '<line x1="12" y1="3" x2="12" y2="15"></line>';
                html += '</svg>';
                html += 'Upload File';
                html += '</button>';
                html += '<button type="submit" class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-analyze-cv-btn">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">';
                html += '<circle cx="11" cy="11" r="8"></circle>';
                html += '<path d="M21 21l-4.35-4.35"></path>';
                html += '</svg>';
                html += 'Analyze CV';
                html += '</button>';
                html += '</div>';
                html += '</form>';
                html += '</div>';

                // Alternative link to Resume tab
                html += '<p class="sffc-crm-empty-note">';
                html += 'Prefer to manage multiple CV versions? Visit the <button type="button" class="sffc-crm-tab sffc-crm-link-btn" data-tab="resume">Resume tab</button>';
                html += '</p>';

                html += '</div>';
                $panel.html(html);

                // Bind quick CV form submit
                self.bindQuickCvForm();
                return;
            }

            if (!data.items || data.items.length === 0) {
                html = '<div class="sffc-crm-empty">';
                html += '<div class="sffc-crm-empty-icon">';
                html += '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">';
                html += '<circle cx="12" cy="12" r="10"></circle>';
                html += '<line x1="12" y1="8" x2="12" y2="12"></line>';
                html += '<line x1="12" y1="16" x2="12.01" y2="16"></line>';
                html += '</svg>';
                html += '</div>';
                html += '<h3>No matches found</h3>';
                html += '<p>We couldn\'t find any job posts matching your profile at the moment. Check back later!</p>';
                html += '</div>';
                $panel.html(html);
                return;
            }

            // Header
            html += '<div class="sffc-crm-matches-header">';
            html += '<h3>Top Matches for Your Profile</h3>';
            html += '<p>Found ' + data.items.length + ' matching opportunities based on your CV</p>';
            html += '</div>';

            // Fetch and display intro usage counter
            setTimeout(function() {
                self.fetchIntroUsage();
            }, 100);

            // Matches list
            html += '<div class="sffc-crm-matches-list">';

            data.items.forEach(function(item) {
                html += self.buildStandardMatchRow(item, {
                    checkboxPrefix: 'match-select'
                });
            });

            html += '</div>';

            // Floating action buttons (hidden by default, shown when items are selected)
            html += '<div class="sffc-crm-floating-actions" style="display: none;">';
            html += '<div class="sffc-crm-floating-actions-content">';
            html += '<div class="sffc-crm-floating-actions-count">';
            html += '<span class="sffc-crm-selected-count">0</span> selected';
            html += '</div>';
            html += '<div class="sffc-crm-floating-actions-buttons">';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-floating-action-btn" id="bulk-request-intros">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<path d="M9 11l3 3L22 4"></path>';
            html += '<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>';
            html += '</svg>';
            html += 'Add to Outreach List';
            html += '</button>';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-floating-action-btn" id="bulk-smart-outreach">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>';
            html += '</svg>';
            html += 'Add to Watchlist';
            html += '</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            $panel.html(html);

            // Ensure avatar fallbacks render if image fails to load
            $panel.find('.sffc-crm-match-avatar img').on('error', function() {
                var $img = $(this);
                var initial = $img.data('initial') || ($img.attr('alt') || 'S').charAt(0).toUpperCase();
                $img.replaceWith('<div class="sffc-crm-avatar-placeholder">' + initial + '</div>');
            });

            // Bind events for match rows
            this.bindMatchEvents();

            // Initialize multi-selection handlers
            this.initMultiSelection();

            this.refreshSmartApplyButtonStates();

            // Mark that user has seen matches
            localStorage.setItem('sffc_crm_seen_matches', '1');

            // Trigger onboarding tour if this is first time seeing matches
            if (this.shouldShowOnboarding()) {
                var self = this;
                setTimeout(function() {
                    self.startOnboardingTour();
                }, 1000);
            }
        },

        loadRecruiterIntros: function() {
            var self = this;
            var $panel = $('#panel-recruiter-intros');

            if (!this.config.isLoggedIn) {
                $panel.html('<div class="sffc-crm-empty-state"><p>Please <a href="' + this.config.loginUrl + '">sign in</a> to view your expressed interest queue.</p></div>');
                return;
            }

            $panel.html('<div class="sffc-crm-loading">Loading emails sent...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_intro_requests',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var payload = response.data || {};
                        var items = payload.items || [];
                        var statuses = payload.statuses || {};
                        self.renderRecruiterIntros(items, statuses);
                    } else {
                        $panel.html('<div class="sffc-crm-empty-state">Unable to load expressed interest.</div>');
                    }
                },
                error: function() {
                    $panel.html('<div class="sffc-crm-empty-state">Unable to load expressed interest.</div>');
                }
            });
        },

        renderRecruiterIntros: function(items, statuses) {
            var self = this;
            var $panel = $('#panel-recruiter-intros');
            this.recruiterIntroCache = items || [];
            this.recruiterIntroStatuses = statuses || this.recruiterIntroStatuses || {};

            if (!items || !items.length) {
                var emptyHtml = '<div class="sffc-crm-empty sffc-crm-empty-enhanced">';
                emptyHtml += '<div class="sffc-crm-empty-icon">';
                emptyHtml += '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5">';
                emptyHtml += '<path d="M17 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>';
                emptyHtml += '<circle cx="12" cy="7" r="4"></circle>';
                emptyHtml += '</svg>';
                emptyHtml += '</div>';
                emptyHtml += '<h2 class="sffc-crm-empty-title">No emails sent yet</h2>';
                emptyHtml += '<p class="sffc-crm-empty-subtitle">Email hiring managers from All Roles or Matches to see them tracked here.</p>';
                emptyHtml += '</div>';
                $panel.html(emptyHtml);
                this.updateTabBadge('recruiter-intros', 0);
                return;
            }

            var html = '<div class="sffc-crm-matches-list">';
            items.forEach(function(request) {
                html += self.renderRecruiterIntroRow(request, self.recruiterIntroStatuses);
            });
            html += '</div>';

            $panel.html(html);
            this.updateTabBadge('recruiter-intros', items.length);
            this.bindRecruiterIntroEvents();
        },

        renderRecruiterIntroRow: function(request, statuses) {
            var self = this;
            statuses = statuses || {};
            if (!Object.keys(statuses).length) {
                statuses = {
                    'pending_review': 'Under Review',
                    'approved': 'Approved',
                    'sent': 'Sent to Recruiter',
                    'awaiting_response': 'Awaiting Response',
                    'accepted': 'Accepted',
                    'rejected': 'Not Proceeding',
                    'expired': 'Expired'
                };
            }
            var matchScore = parseInt(request.compatibility_score, 10);
            if (isNaN(matchScore)) {
                matchScore = 0;
            }
            matchScore = Math.max(0, Math.min(100, matchScore));
            var matchColor = this.getMatchColor(matchScore);
            var recruiterInitial = (request.recruiter_initials || 'S').substring(0, 2);
            var statusClass = request.status ? request.status.replace(/_/g, '-') : 'pending';
            var statusLabel = request.status_label || 'Pending';
            var company = request.job_company || '';
            var location = request.job_location || '';
            var metaParts = [];
            if (company) metaParts.push(this.escapeHtml(company));
            if (location) metaParts.push(this.escapeHtml(location));

            var rowId = 'intro-select-' + request.id;
            var html = '<div class="sffc-crm-match-row sffc-crm-match-row--intro" data-intro-id="' + request.id + '" data-post-id="' + (request.job_id || '') + '" data-recruiter-id="' + (request.recruiter_id || '') + '" data-recruiter-name="' + this.escapeHtml(request.recruiter_name || '') + '" data-match-score="' + matchScore + '">';

            html += '<div class="sffc-crm-match-checkbox">';
            html += '<input type="checkbox" id="' + rowId + '" disabled>';
            html += '<label for="' + rowId + '">';
            html += '<svg class="sffc-crm-checkbox-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">';
            html += '<polyline points="20 6 9 17 4 12"></polyline>';
            html += '</svg>';
            html += '</label>';
            html += '</div>';

            // Calculate score label position at ring endpoint (matches Match & Apply renderer)
            var introAngle = matchScore === 100 ? 90 : ((matchScore / 100) * 360 - 90);
            var introRadians = introAngle * (Math.PI / 180);
            var scoreX = 40 + 35 * Math.cos(introRadians);
            var scoreY = 40 + 35 * Math.sin(introRadians);
            var recruiterPhoto = request.recruiter_photo || '';

            html += '<div class="sffc-crm-match-indicator">';
            html += '<div class="sffc-crm-match-circle-container">';
            html += '<svg class="sffc-crm-match-circle" width="80" height="80" viewBox="0 0 80 80">';
            html += '<circle class="sffc-crm-match-circle-bg" cx="40" cy="40" r="35" fill="none" stroke="#e5e7eb" stroke-width="5"></circle>';
            var dashLength = (matchScore * 2.199).toFixed(1);
            html += '<circle class="sffc-crm-match-circle-fg" cx="40" cy="40" r="35" fill="none" stroke="' + matchColor + '" stroke-width="5" stroke-dasharray="' + dashLength + ' 219.91" stroke-linecap="round" transform="rotate(-90 40 40)"></circle>';
            html += '</svg>';
            html += '<div class="sffc-crm-match-avatar">';
            if (recruiterPhoto) {
                html += '<img src="' + this.escapeHtml(recruiterPhoto) + '" alt="' + this.escapeHtml(request.recruiter_name || 'Recruiter') + '" data-initial="' + this.escapeHtml(recruiterInitial) + '">';
            } else {
                html += '<div class="sffc-crm-avatar-placeholder">' + this.escapeHtml(recruiterInitial) + '</div>';
            }
            html += '</div>';
            html += '<div class="sffc-crm-match-score" style="left: ' + scoreX + 'px; top: ' + scoreY + 'px; color: ' + matchColor + '; border-color: ' + matchColor + ';">' + matchScore + '%</div>';
            html += '</div>';
            html += '<div class="sffc-crm-match-recruiter-name">' + this.escapeHtml(request.recruiter_name || 'Recruiter') + '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-match-content">';
            html += '<div class="sffc-crm-match-header">';
            html += '<h4 class="sffc-crm-match-title">' + this.escapeHtml(request.job_title || 'Untitled Role') + '</h4>';
            if (metaParts.length) {
                html += '<div class="sffc-crm-match-meta">' + metaParts.join(' • ') + '</div>';
            }
            html += '</div>';

            html += '<ul class="sffc-crm-match-reasons">';
            html += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Status: ' + this.escapeHtml(statusLabel) + '</span></li>';
            if (request.created_at_formatted) {
                html += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Requested ' + this.escapeHtml(request.created_at_formatted) + '</span></li>';
            }
            if (request.recruiter_firm) {
                html += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>' + this.escapeHtml(request.recruiter_firm) + '</span></li>';
            }
            html += '</ul>';

            html += '<div class="sffc-crm-match-actions">';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-match-message-btn sffc-crm-match-inline-btn" data-post-id="' + (request.job_id || '') + '" data-recruiter-id="' + (request.recruiter_id || '') + '" data-recruiter-name="' + self.escapeHtml(request.recruiter_name || '') + '" data-match-score="' + matchScore + '">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">';
            html += '<rect x="3" y="5" width="18" height="14" rx="2"></rect>';
            html += '<path d="M3 7l9 6 9-6"></path>';
            html += '</svg>';
            html += 'Follow Up';
            html += '</button>';
            html += '</div>';

            html += '</div>';
            html += '</div>';

            return html;
        },

        bindRecruiterIntroEvents: function() {
            var self = this;
            $(document).off('click.recruiterIntroView', '.sffc-crm-intro-view-btn').on('click.recruiterIntroView', '.sffc-crm-intro-view-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var introId = $(this).data('intro-id');
                self.openRecruiterIntroDetail(introId);
            });

            $(document).off('click.recruiterIntroRow', '#panel-recruiter-intros .sffc-crm-match-row').on('click.recruiterIntroRow', '#panel-recruiter-intros .sffc-crm-match-row', function(e) {
                if ($(e.target).closest('button, select').length) {
                    return;
                }
                var introId = $(this).data('intro-id');
                self.openRecruiterIntroDetail(introId);
            });

            $(document).off('change.recruiterIntroStatus', '.sffc-crm-intro-status-select').on('change.recruiterIntroStatus', '.sffc-crm-intro-status-select', function(e) {
                e.stopPropagation();
                var introId = $(this).data('intro-id');
                var status = $(this).val();
                self.updateRecruiterIntroStatus(introId, status);
            });

            $(document).off('click.recruiterIntroRemove', '.sffc-crm-intro-remove-btn').on('click.recruiterIntroRemove', '.sffc-crm-intro-remove-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var introId = $(this).data('intro-id');
                if (confirm('Remove this Express Interest request?')) {
                    self.removeRecruiterIntro(introId);
                }
            });
        },

        openRecruiterIntroDetail: function(introId) {
            var self = this;
            introId = parseInt(introId, 10);
            if (isNaN(introId)) {
                this.showIntroRequestsModal();
                return;
            }
            var entry = (this.recruiterIntroCache || []).find(function(item) {
                return parseInt(item.id, 10) === introId;
            });
            if (!entry) {
                this.showIntroRequestsModal();
                return;
            }

            var statusClass = entry.status ? entry.status.replace(/_/g, '-') : 'pending';
            var statusLabel = entry.status_label || 'Pending Review';
            var keywords = [];
            if (entry.keywords) {
                if (Array.isArray(entry.keywords)) {
                    keywords = entry.keywords;
                } else {
                    try {
                        var parsed = JSON.parse(entry.keywords);
                        if (Array.isArray(parsed)) {
                            keywords = parsed;
                        }
                    } catch (err) {
                        keywords = [];
                    }
                }
            }

            var html = '<div class="sffc-crm-intro-detail">';
            html += '<div class="sffc-crm-modal-header">';
            html += '<div class="sffc-crm-intro-detail-status sffc-crm-intro-status sffc-crm-intro-status--' + this.escapeHtml(statusClass) + '">' + this.escapeHtml(statusLabel) + '</div>';
            html += '<button type="button" class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            html += '<div class="sffc-crm-intro-detail-body">';
            html += '<h3>' + this.escapeHtml(entry.job_title || 'Untitled Role') + '</h3>';
            if (entry.job_company || entry.job_location) {
                html += '<p class="sffc-crm-intro-detail-meta">';
                if (entry.job_company) html += this.escapeHtml(entry.job_company);
                if (entry.job_company && entry.job_location) html += ' • ';
                if (entry.job_location) html += this.escapeHtml(entry.job_location);
                html += '</p>';
            }
            html += '<p class="sffc-crm-intro-detail-meta">Interest logged ' + this.escapeHtml(entry.created_at_formatted || '') + '</p>';

            html += '<div class="sffc-crm-intro-detail-section">';
            html += '<h4>Recruiter</h4>';
            html += '<p>' + this.escapeHtml(entry.recruiter_name || 'Recruiter') + '</p>';
            if (entry.recruiter_firm) {
                html += '<p class="sffc-crm-intro-detail-subtext">' + this.escapeHtml(entry.recruiter_firm) + '</p>';
            }
            html += '</div>';

            if (entry.message) {
                html += '<div class="sffc-crm-intro-detail-section">';
                html += '<h4>Your note</h4>';
                html += '<p>' + this.escapeHtml(entry.message) + '</p>';
                html += '</div>';
            }

            if (entry.pitch_message) {
                html += '<div class="sffc-crm-intro-detail-section">';
                html += '<h4>AI message</h4>';
                html += '<p>' + this.escapeHtml(entry.pitch_message) + '</p>';
                html += '</div>';
            }

            if (keywords.length) {
                html += '<div class="sffc-crm-intro-detail-section">';
                html += '<h4>Focus keywords</h4>';
                html += '<div class="sffc-crm-intro-keyword-chips">';
                keywords.forEach(function(keyword) {
                    html += '<span class="sffc-crm-intro-keyword-chip">' + self.escapeHtml(keyword) + '</span>';
                });
                html += '</div>';
                html += '</div>';
            }

            html += '</div>';
            html += '</div>';

            this.showModal(html);
        },

        updateRecruiterIntroStatus: function(introId, status) {
            if (!introId || !status) {
                return;
            }
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_update_intro_request',
                    nonce: this.config.nonce,
                    intro_id: introId,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess((response.data && response.data.message) || 'Status updated');
                        self.loadRecruiterIntros();
                        self.refreshRecruiterIntrosBadge();
                    } else {
                        self.showError((response.data && response.data.message) || 'Failed to update status');
                    }
                },
                error: function() {
                    self.showError('Failed to update status');
                }
            });
        },

        removeRecruiterIntro: function(introId) {
            if (!introId) {
                return;
            }
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_remove_intro_request',
                    nonce: this.config.nonce,
                    intro_id: introId
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess((response.data && response.data.message) || 'Request removed');
                        self.loadRecruiterIntros();
                        self.refreshRecruiterIntrosBadge();
                    } else {
                        self.showError((response.data && response.data.message) || 'Failed to remove request');
                    }
                },
                error: function() {
                    self.showError('Failed to remove request');
                }
            });
        },

        refreshRecruiterIntrosBadge: function() {
            if (this.currentTab === 'recruiter-intros') {
                this.loadRecruiterIntros();
                return;
            }
            // Mark the panel dirty so loadTabContent's guard will trigger a full
            // reload the next time the user clicks the Recruiter Intros tab.
            // Without this, the panel keeps its previous DOM content and the guard
            // at loadTabContent (which skips panels with no .sffc-crm-loading) would
            // never reload the freshly-submitted intro.
            $('#panel-recruiter-intros').html('<div class="sffc-crm-loading">Loading emails sent...</div>');
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_intro_requests',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.items) {
                        self.updateTabBadge('recruiter-intros', response.data.items.length);
                        self.recruiterIntroCache = response.data.items;
                    }
                }
            });
        },

        /**
         * Immediately inject a new intro row into #panel-recruiter-intros
         * using data returned by the PHP handler, with no second AJAX call.
         */
        injectRecruiterIntroRow: function(request) {
            var $panel = $('#panel-recruiter-intros');
            var statuses = this.recruiterIntroStatuses || {
                'pending_review':    'Under Review',
                'approved':          'Approved',
                'sent':              'Sent to Recruiter',
                'awaiting_response': 'Awaiting Response',
                'accepted':          'Accepted',
                'rejected':          'Not Proceeding',
                'expired':           'Expired'
            };

            var rowHtml = this.renderRecruiterIntroRow(request, statuses);

            var $list = $panel.find('.sffc-crm-matches-list');
            if ($list.length) {
                // Panel already has rows — prepend the new one
                $list.prepend(rowHtml);
            } else {
                // Panel is empty / loading / showing empty-state — replace entirely
                $panel.html('<div class="sffc-crm-matches-list">' + rowHtml + '</div>');
            }

            // Keep the cache in sync so badge and future operations are accurate
            if (!Array.isArray(this.recruiterIntroCache)) {
                this.recruiterIntroCache = [];
            }
            this.recruiterIntroCache.unshift(request);
            this.updateTabBadge('recruiter-intros', this.recruiterIntroCache.length);

            // Bind events on the newly added row
            this.bindRecruiterIntroEvents();
        },

        loadSmartApplyTab: function() {
            var self = this;
            var $panel = $('#panel-smart-apply');

            if (!this.config.isLoggedIn) {
                $panel.html('<div class="sffc-crm-empty-state"><p>Please <a href="' + this.config.loginUrl + '">sign in</a> to view your Watchlist.</p></div>');
                return;
            }

            $panel.html('<div class="sffc-crm-loading">Loading watchlist...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_saved',
                    type: 'all',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.renderWatchlistTab(response.data);
                        var totalSaved = (response.data.posts ? response.data.posts.length : 0);
                        self.updateTabBadge('smart-apply', totalSaved);
                    } else {
                        $panel.html('<div class="sffc-crm-empty-state">Unable to load Watchlist.</div>');
                    }
                },
                error: function() {
                    $panel.html('<div class="sffc-crm-empty-state">Unable to load Watchlist.</div>');
                }
            });
        },

        renderSmartApplyTab: function(items) {
            var self = this;
            var $panel = $('#panel-smart-apply');
            var html = '<div class="sffc-crm-smart-apply-header">';
            html += '<div class="sffc-crm-smart-apply-heading">';
            html += '<h2>Smart message Queue</h2>';
            html += '<p>Every role you add is tracked here with concierge updates and status controls.</p>';
            html += '</div>';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary" data-scroll-target="smart-apply-form">Refine preferences</button>';
            html += '</div>';

            if (!items || !items.length) {
                html += '<div class="sffc-crm-smart-apply-empty">';
                html += '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5">';
                html += '<circle cx="12" cy="12" r="10"></circle>';
                html += '<path d="M8 12h8"></path>';
                html += '<path d="M12 8v8"></path>';
                html += '</svg>';
                html += '<h3>Nothing in Smart message yet</h3>';
                html += '<p>Add a role from Match &amp; Apply or submit a brief to kick things off.</p>';
                html += '</div>';
            } else {
                html += '<div class="sffc-crm-matches-list">';
                items.forEach(function(item) {
                    html += self.renderSmartApplyRow(item);
                });
                html += '</div>';
            }

            html += '<div class="sffc-crm-smart-apply-form-card" id="smart-apply-form">';
            html += this.renderSmartApplyFormCard();
            html += '</div>';

            $panel.html(html);
            this.bindSmartApplyEvents();
        },

        renderWatchlistTab: function(data) {
            var self = this;
            var $panel = $('#panel-smart-apply');
            var posts = data.posts || [];

            if (posts.length === 0) {
                var emptyHtml = '<div class="sffc-crm-empty sffc-crm-empty-enhanced">';
                emptyHtml += '<div class="sffc-crm-empty-icon">';
                emptyHtml += '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5">';
                emptyHtml += '<circle cx="12" cy="12" r="10"></circle>';
                emptyHtml += '<path d="M12 7v5l3 2"></path>';
                emptyHtml += '</svg>';
                emptyHtml += '</div>';
                emptyHtml += '<h2 class="sffc-crm-empty-title">Your Watchlist is empty</h2>';
                emptyHtml += '<p class="sffc-crm-empty-subtitle">Save roles from All Roles or Matches to track them here.</p>';
                emptyHtml += '</div>';
                $panel.html(emptyHtml);
                return;
            }

            // Use the same rendering logic as All Roles
            var modifiedData = {
                items: posts.map(function(post) {
                    post.is_saved = 1; // Mark all as saved
                    return post;
                })
            };

            // Temporarily save display count and set it to show all watchlist items
            var originalCount = this.allRolesDisplayCount;
            this.allRolesDisplayCount = posts.length;

            // Render using All Roles logic
            var html = '';
            html += '<div class="sffc-crm-matches-list">';

            modifiedData.items.forEach(function(item) {
                var postId = item.id || item.post_id;
                var isSaved = true; // Always true for watchlist

                var recruiterName = item.recruiter_name || 'Unknown';
                var isEarlyBird = !!item.is_early_bird;
                var canViewContact = self.config.isPremium || !isEarlyBird;
                var recruiterPhoto = '';
                if (canViewContact) {
                    recruiterPhoto = item.recruiter_photo || '';
                } else if (isEarlyBird && self.earlyBirdAvatars && self.earlyBirdAvatars.length > 0) {
                    recruiterPhoto = self.earlyBirdAvatars[postId % self.earlyBirdAvatars.length];
                }
                var recruiterEmail = canViewContact ? (item.recruiter_email || '') : '';
                var recruiterLinkedIn = canViewContact ? (item.recruiter_linkedin_url || item.recruiterLinkedIn || item.linkedin_url || '') : '';
                var recruiterFirm = item.recruiter_firm || '';
                var recruiterTitle = item.recruiter_title || '';
                var recruiterInitial = (recruiterName.charAt(0) || 'R').toUpperCase();
                var matchScore = Math.round(item.match_score || item.matchScore || 0);

                var recruiterDisplayName = recruiterName;
                if (!canViewContact) {
                    recruiterDisplayName = item.recruiter_display_name || 'LinkedIn Recruiter';
                    recruiterInitial = (recruiterDisplayName.charAt(0) || 'R').toUpperCase();
                } else {
                    var nameParts = recruiterName.split(/\s+/);
                    if (nameParts.length >= 2) {
                        var firstName = nameParts[0];
                        var lastInitial = nameParts[nameParts.length - 1].charAt(0).toUpperCase();
                        recruiterDisplayName = firstName + ' ' + lastInitial + '.';
                    }
                }

                var recruiterDisplayFirm = recruiterFirm || item.recruiter_display_company || item.company || '';
                var recruiterPublicFirm = item.recruiter_display_company || item.company || 'Confidential Search Firm';

                html += '<div class="sffc-crm-match-row" data-post-id="' + postId + '" data-match-score="' + matchScore + '" data-recruiter-id="' + (item.recruiter_id || '') + '" data-recruiter-name="' + self.escapeHtml(recruiterName) + '" data-sector="' + self.escapeHtml(item.sector || '') + '" data-seniority="' + self.escapeHtml(item.seniority || '') + '" data-company="' + self.escapeHtml(item.company || '') + '" data-location="' + self.escapeHtml(item.location || '') + '" data-salary-text="' + self.escapeHtml(item.salary_text || '') + '" data-posted="' + self.escapeHtml(item.posted_at || '') + '" data-recruiter-firm="' + self.escapeHtml(recruiterFirm) + '" data-recruiter-title="' + self.escapeHtml(recruiterTitle) + '" data-recruiter-linkedin="' + self.escapeHtml(recruiterLinkedIn) + '" data-recruiter-email="' + self.escapeHtml(recruiterEmail) + '" data-early-bird="' + (isEarlyBird ? 1 : 0) + '">';

                html += '<div class="sffc-crm-match-checkbox">';
                html += '<input type="checkbox" class="sffc-crm-match-select" id="role-select-' + postId + '" data-post-id="' + postId + '">';
                html += '<label for="role-select-' + postId + '">';
                html += '<svg class="sffc-crm-checkbox-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">';
                html += '<polyline points="20 6 9 17 4 12"></polyline>';
                html += '</svg>';
                html += '</label>';
                html += '</div>';

                html += '<div class="sffc-crm-match-indicator">';
                html += '<div class="sffc-crm-match-circle-container">';
                html += '<div class="sffc-crm-match-avatar' + (isEarlyBird && !self.config.isPremium ? ' sffc-crm-avatar-blurred' : '') + '">';
                if (recruiterPhoto) {
                    html += '<img src="' + self.escapeHtml(recruiterPhoto) + '" alt="' + self.escapeHtml(recruiterName) + '" data-initial="' + self.escapeHtml(recruiterInitial) + '">';
                } else {
                    html += '<div class="sffc-crm-avatar-placeholder">' + recruiterInitial + '</div>';
                }
                html += '</div>';
                html += '</div>';

                html += '<div class="sffc-crm-match-recruiter-name">' + self.escapeHtml(recruiterDisplayName) + '</div>';
                if (self.config.isPremium && recruiterDisplayFirm) {
                    html += '<div class="sffc-crm-match-recruiter-meta">' + self.escapeHtml(recruiterDisplayFirm) + '</div>';
                } else if (!self.config.isPremium) {
                    html += '<div class="sffc-crm-match-recruiter-meta">' + self.escapeHtml(recruiterPublicFirm) + '</div>';
                } else if (!recruiterDisplayFirm && canViewContact) {
                    html += '<div class="sffc-crm-match-recruiter-meta">' + self.escapeHtml(recruiterPublicFirm) + '</div>';
                }

                html += '</div>';

                html += '<div class="sffc-crm-match-content">';
                html += '<div class="sffc-crm-match-header">';
                html += '<h4 class="sffc-crm-match-title">' + self.escapeHtml(item.role_title || 'Untitled');
                if (isEarlyBird) {
                    html += ' <span class="sffc-crm-early-bird-badge">Pro+ Members</span>';
                } else {
                    var badgeTextMatches = (item.response_label ? String(item.response_label) : '').trim() || 'Actively Hiring';
                    html += ' <span class="sffc-crm-free-contact-badge">' + self.escapeHtml(badgeTextMatches) + '</span>';
                }
                html += '</h4>';

                var metaParts = [];
                if (item.company) metaParts.push(self.escapeHtml(item.company));
                if (item.location) metaParts.push(self.escapeHtml(item.location));
                if (metaParts.length > 0) {
                    html += '<div class="sffc-crm-match-meta">' + metaParts.join(' • ') + '</div>';
                }
                var snippetText = self.getRoleSnippet(item);
                if (snippetText) {
                    html += '<p class="sffc-crm-match-snippet">' + self.escapeHtml(snippetText) + '</p>';
                }
                html += '</div>';

                var recruiterFirstNameMatches = recruiterName.split(/\s+/)[0] || 'Recruiter';
                html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-match-message-btn sffc-crm-match-inline-btn" ';
                html += 'data-post-id="' + postId + '" data-recruiter-id="' + (item.recruiter_id || '') + '" data-match-score="' + matchScore + '" ';
                html += 'data-recruiter-name="' + self.escapeHtml(recruiterName) + '">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">';
                html += '<rect x="3" y="5" width="18" height="14" rx="2"></rect>';
                html += '<path d="M3 7l9 6 9-6"></path>';
                html += '</svg>';
                html += 'Message ' + self.escapeHtml(recruiterFirstNameMatches);
                html += '</button>';

                if (item.application_url) {
                    html += '<a href="' + self.escapeHtml(item.application_url) + '" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-match-inline-btn sffc-crm-apply-btn" target="_blank" rel="noopener noreferrer">';
                    html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">';
                    html += '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>';
                    html += '<polyline points="15 3 21 3 21 9"></polyline>';
                    html += '<line x1="10" y1="14" x2="21" y2="3"></line>';
                    html += '</svg>';
                    html += 'External Apply (Free)';
                    html += '</a>';
                }

                html += '</div>';

                html += '<div class="sffc-crm-match-actions">';

                html += '<button class="sffc-crm-btn sffc-crm-btn-icon sffc-crm-save-btn is-saved" data-action="save" data-post-id="' + postId + '" title="Saved to Watchlist">';
                html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">';
                html += '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>';
                html += '</svg>';
                html += '</button>';

                html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-icon sffc-crm-app-toolkit-btn" ';
                html += 'data-post-id="' + postId + '" title="Application Toolkit">';
                html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                html += '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>';
                html += '<line x1="9" y1="9" x2="15" y2="9"></line>';
                html += '<line x1="9" y1="15" x2="15" y2="15"></line>';
                html += '</svg>';
                html += '</button>';

                html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-match-introduce-btn sffc-crm-match-inline-btn" ';
                html += 'data-post-id="' + postId + '" data-recruiter-id="' + (item.recruiter_id || '') + '" ';
                html += 'data-recruiter-name="' + self.escapeHtml(recruiterName) + '">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">';
                html += '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>';
                html += '<circle cx="8.5" cy="7" r="4"></circle>';
                html += '<path d="M20 8v6"></path>';
                html += '<path d="M23 11h-6"></path>';
                html += '</svg>';
                html += 'View All Posts';
                html += '</button>';

                html += '</div>';
                html += '</div>';
            });

            html += '</div>';

            // Restore original display count
            this.allRolesDisplayCount = originalCount;

            $panel.html(html);
        },

        renderSmartApplyRow: function(item) {
            var self = this;
            var statusLabels = this.smartApplyStatusLabels || {};
            var status = item.status || 'pending_review';
            var statusLabel = statusLabels[status] || 'Queued';
            var isCustom = item.request_type === 'custom';
            var matchScore = parseInt(item.match_score, 10);
            if (isNaN(matchScore) || isCustom) {
                matchScore = 0;
            }
            var matchColor = this.getMatchColor(matchScore);
            var company = item.company || '';
            var location = item.location || '';
            var metaParts = [];
            if (company) metaParts.push(this.escapeHtml(company));
            if (location) metaParts.push(this.escapeHtml(location));
            var recruiterName = item.recruiter_name || '';
            var recruiterInitial = recruiterName ? recruiterName.charAt(0).toUpperCase() : 'S';

            var preferencesList = [];
            if (item.preferences) {
                try {
                    var parsed = typeof item.preferences === 'string' ? JSON.parse(item.preferences) : item.preferences;
                    if (parsed.role_focus) preferencesList.push('Focus: ' + parsed.role_focus);
                    if (parsed.target_locations) preferencesList.push('Locations: ' + parsed.target_locations);
                    if (parsed.compensation) preferencesList.push('Compensation: ' + parsed.compensation);
                    if (parsed.notes) preferencesList.push('Notes: ' + parsed.notes);
                } catch (err) {
                    // ignore parsing errors
                }
            }

            var rowClasses = 'sffc-crm-match-row sffc-crm-match-row--intro sffc-crm-smart-apply-entry';
            if (isCustom) {
                rowClasses += ' sffc-crm-smart-apply-entry--custom';
            }

            var html = '<div class="' + rowClasses + '" data-request-id="' + item.id + '" data-job-id="' + (item.job_id || '') + '" data-match-score="' + matchScore + '">';

            html += '<div class="sffc-crm-match-checkbox">';
            html += '<input type="checkbox" id="smart-apply-' + item.id + '" disabled>';
            html += '<label for="smart-apply-' + item.id + '">';
            html += '<svg class="sffc-crm-checkbox-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">';
            html += '<polyline points="20 6 9 17 4 12"></polyline>';
            html += '</svg>';
            html += '</label>';
            html += '</div>';

            html += '<div class="sffc-crm-match-indicator">';
            html += '<div class="sffc-crm-match-circle-container">';
            html += '<svg class="sffc-crm-match-circle" width="80" height="80" viewBox="0 0 80 80">';
            html += '<circle class="sffc-crm-match-circle-bg" cx="40" cy="40" r="35" fill="none" stroke="#e5e7eb" stroke-width="5"></circle>';
            if (!isCustom) {
                var dashLength = (matchScore * 2.199).toFixed(1);
                html += '<circle class="sffc-crm-match-circle-fg" cx="40" cy="40" r="35" fill="none" stroke="' + matchColor + '" stroke-width="5" stroke-dasharray="' + dashLength + ' 219.91" stroke-linecap="round" transform="rotate(-90 40 40)"></circle>';
            }
            html += '</svg>';
            html += '<div class="sffc-crm-match-avatar">';
            if (!isCustom && item.recruiter_photo) {
                html += '<img src="' + this.escapeHtml(item.recruiter_photo) + '" alt="' + this.escapeHtml(recruiterName || 'Recruiter') + '" data-initial="' + this.escapeHtml(recruiterInitial) + '">';
            } else {
                html += '<div class="sffc-crm-avatar-placeholder">' + this.escapeHtml(recruiterInitial) + '</div>';
            }
            html += '</div>';
            if (!isCustom) {
                var angle = matchScore === 100 ? 90 : ((matchScore / 100) * 360 - 90);
                var radians = angle * (Math.PI / 180);
                var scoreX = 40 + 35 * Math.cos(radians);
                var scoreY = 40 + 35 * Math.sin(radians);
                html += '<div class="sffc-crm-match-score" style="left:' + scoreX + 'px;top:' + scoreY + 'px;color:' + matchColor + ';border-color:' + matchColor + ';">' + matchScore + '%</div>';
            }
            html += '</div>';
            html += '<div class="sffc-crm-match-recruiter-name">' + (isCustom ? 'Smart message' : this.escapeHtml(recruiterName || 'Recruiter')) + '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-match-content">';
            html += '<div class="sffc-crm-match-header">';
            html += '<h4 class="sffc-crm-match-title">' + this.escapeHtml(isCustom ? 'Custom Smart message Brief' : (item.job_title || 'Untitled Role')) + '</h4>';
            if (metaParts.length && !isCustom) {
                html += '<div class="sffc-crm-match-meta">' + metaParts.join(' • ') + '</div>';
            }
            var snippetText = (!isCustom) ? this.getRoleSnippet(item) : '';
            if (snippetText) {
                html += '<p class="sffc-crm-match-snippet">' + this.escapeHtml(snippetText) + '</p>';
            }
            html += '</div>';

            html += '<ul class="sffc-crm-match-reasons">';
            html += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Status: ' + this.escapeHtml(statusLabel) + '</span></li>';
            if (item.created_at_formatted) {
                html += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Requested ' + this.escapeHtml(item.created_at_formatted) + '</span></li>';
            }
            if (!isCustom && matchScore) {
                html += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Match score ' + matchScore + '%</span></li>';
            }
            if (!isCustom && recruiterName) {
                html += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Recruiter: ' + this.escapeHtml(recruiterName) + '</span></li>';
            }
            if (item.notes) {
                html += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Notes: ' + this.escapeHtml(item.notes) + '</span></li>';
            }
            var self = this;
            preferencesList.forEach(function(line) {
                html += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>' + self.escapeHtml(line) + '</span></li>';
            });
            html += '</ul>';

            html += '</div>';

            html += '<div class="sffc-crm-match-actions">';
            if (Object.keys(statusLabels).length) {
                html += '<div class="sffc-crm-smart-apply-status-control">';
                html += '<label>Status</label>';
                html += '<select class="sffc-crm-select sffc-crm-smart-apply-status-select" data-request-id="' + item.id + '">';
                for (var key in statusLabels) {
                    if (!statusLabels.hasOwnProperty(key)) continue;
                    var selected = key === status ? 'selected' : '';
                    html += '<option value="' + key + '" ' + selected + '>' + this.escapeHtml(statusLabels[key]) + '</option>';
                }
                html += '</select>';
                html += '</div>';
            }
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-smart-apply-remove" data-request-id="' + item.id + '" data-job-id="' + (item.job_id || '') + '">Remove</button>';
            html += '</div>';

            html += '</div>';
            return html;
        },

        renderSmartApplyFormCard: function() {
            var html = '<div class="sffc-crm-smart-apply-form">';
            html += '<h3>Request Concierge Smart message</h3>';
            html += '<p>Tell us the mandates you want MENA Careers to target and we\'ll brief 50+ recruiters on your behalf.</p>';
            html += '<form id="sffc-crm-smart-apply-brief">';
            html += '<div class="sffc-crm-form-group">';
            html += '<label for="smart-apply-role-focus">Target role focus</label>';
            html += '<input type="text" class="sffc-crm-input" id="smart-apply-role-focus" name="role_focus" placeholder="e.g., VP, Portfolio value creation" required />';
            html += '</div>';
            html += '<div class="sffc-crm-form-group">';
            html += '<label for="smart-apply-location">Preferred locations</label>';
            html += '<input type="text" class="sffc-crm-input" id="smart-apply-location" name="target_locations" placeholder="Global, Remote, New York" />';
            html += '</div>';
            html += '<div class="sffc-crm-form-group">';
            html += '<label for="smart-apply-comp">Compensation expectations (optional)</label>';
            html += '<input type="text" class="sffc-crm-input" id="smart-apply-comp" name="compensation" placeholder="$300k+ total comp" />';
            html += '</div>';
            html += '<div class="sffc-crm-form-group">';
            html += '<label for="smart-apply-notes">Anything else we should know?</label>';
            html += '<textarea id="smart-apply-notes" name="additional_notes" rows="4" placeholder="Team size, sector focus, visa requirements..."></textarea>';
            html += '</div>';
            html += '<button type="submit" class="sffc-crm-btn sffc-crm-btn-primary">Submit Smart message brief</button>';
            html += '</form>';
            html += '<p class="sffc-crm-smart-apply-note">Our concierge team reviews every brief and follows up via CRM within a few hours.</p>';
            html += '</div>';
            return html;
        },

        bindSmartApplyEvents: function() {
            var self = this;
            $(document).off('change.smartApplyStatus', '.sffc-crm-smart-apply-status-select').on('change.smartApplyStatus', '.sffc-crm-smart-apply-status-select', function() {
                var $select = $(this);
                var requestId = $select.data('request-id');
                var status = $select.val();
                if (!requestId || !status) {
                    return;
                }
                $select.prop('disabled', true);
                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_update_smart_apply_request',
                        nonce: self.config.nonce,
                        request_id: requestId,
                        status: status
                    },
                    success: function(response) {
                        $select.prop('disabled', false);
                        if (response.success) {
                            self.showSuccess((response.data && response.data.message) || 'Status updated');
                        } else {
                            self.showError((response.data && response.data.message) || 'Failed to update request');
                        }
                    },
                    error: function() {
                        $select.prop('disabled', false);
                        self.showError('Failed to update request');
                    }
                });
            });

            $(document).off('click.smartApplyRemove', '.sffc-crm-smart-apply-remove').on('click.smartApplyRemove', '.sffc-crm-smart-apply-remove', function() {
                var $btn = $(this);
                var requestId = $btn.data('request-id');
                var jobId = $btn.data('job-id');
                if (!requestId) {
                    return;
                }
                $btn.prop('disabled', true);
                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_crm_remove_smart_apply_request',
                        nonce: self.config.nonce,
                        request_id: requestId
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        if (response.success) {
                            self.showSuccess((response.data && response.data.message) || 'Removed');
                            if (jobId) {
                                self.removeSmartApplyMapping(jobId);
                            }
                            if (self.currentTab === 'smart-apply') {
                                self.loadSmartApplyTab();
                            }
                            if (response.data && typeof response.data.total !== 'undefined') {
                                self.updateTabBadge('smart-apply', response.data.total);
                            }
                        } else {
                            self.showError((response.data && response.data.message) || 'Failed to remove');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false);
                        self.showError('Failed to remove');
                    }
                });
            });

            $(document).off('submit.smartApplyBrief', '#sffc-crm-smart-apply-brief').on('submit.smartApplyBrief', '#sffc-crm-smart-apply-brief', function(e) {
                e.preventDefault();
                self.submitSmartApplyBrief($(this));
            });

            $(document).off('click.smartApplyScroll', '[data-scroll-target="smart-apply-form"]').on('click.smartApplyScroll', '[data-scroll-target="smart-apply-form"]', function(e) {
                e.preventDefault();
                var target = document.getElementById('smart-apply-form');
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },

        loadRecentPostsTab: function() {
            var self = this;
            var $panel = $('#panel-recent-posts');
            $panel.html('<div class="sffc-crm-loading">Loading recent posts...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_feed',
                    nonce: this.config.nonce,
                    page: 1,
                    per_page: 6,
                    orderby: 'posted_at',
                    order: 'DESC'
                },
                success: function(response) {
                    if (response.success && response.data && Array.isArray(response.data.posts)) {
                        self.renderRecentPosts(response.data.posts);
                    } else {
                        $panel.html('<div class="sffc-crm-empty-state">Unable to load recent posts.</div>');
                    }
                },
                error: function() {
                    $panel.html('<div class="sffc-crm-empty-state">Unable to load recent posts.</div>');
                }
            });
        },

        renderRecentPosts: function(posts) {
            var self = this;
            var $panel = $('#panel-recent-posts');

            if (!posts || !posts.length) {
                $panel.html('<div class="sffc-crm-empty-state">No new roles yet—check back in a bit.</div>');
                return;
            }

            var html = '<div class="sffc-crm-feed-header">';
            html += '<h2>Recent Posts</h2>';
            html += '<p>Fresh mandates from recruiters MENA Careers works with. Tap any card to apply, request an intro, or add to Smart message.</p>';
            html += '</div>';
            html += '<div class="sffc-crm-feed-stack">';
            posts.forEach(function(post) {
                html += self.renderRecentPostCard(post);
            });
            html += '</div>';

            $panel.html(html);
            this.bindMatchEvents();
        },

        renderRecentPostCard: function(post) {
            var isPremium = !!this.config.isPremium;
            var matchScore = parseInt(post.match_score, 10);
            if (isNaN(matchScore)) {
                matchScore = 68;
            }
            matchScore = Math.max(0, Math.min(100, matchScore));
            var matchColor = this.getMatchColor(matchScore);

            var recruiterName = isPremium ? (post.recruiter_name || 'Recruiter') : (post.recruiter_display_name || 'LinkedIn Recruiter');
            var recruiterFirm = isPremium ? (post.recruiter_firm || post.company || '') : (post.recruiter_display_company || '');
            var recruiterPhoto = isPremium ? (post.recruiter_photo || '') : '';

            var company = post.company || '';
            var location = post.location || '';
            var sector = post.sector ? post.sector.replace(/_/g, ' ') : '';
            var seniority = post.seniority ? post.seniority.toUpperCase() : '';
            var salaryText = post.salary_text || '';
            var snippet = (post.content_snippet || post.content || '').replace(/\s+/g, ' ').trim();
            if (snippet.length > 240) {
                snippet = snippet.substring(0, 237).trim() + '…';
            }
            var postedAgo = post.posted_at ? this.timeAgo(post.posted_at) : 'Just now';

            var html = '<article class="sffc-crm-feed-card">';
            html += '<div class="sffc-crm-feed-card-head">';
            html += '<div class="sffc-crm-feed-avatar">';
            if (recruiterPhoto) {
                html += '<img src="' + this.escapeHtml(recruiterPhoto) + '" alt="' + this.escapeHtml(recruiterName) + '">';
            } else {
                html += '<div class="sffc-crm-feed-avatar-placeholder">' + this.escapeHtml((recruiterName || 'S').charAt(0)) + '</div>';
            }
            html += '</div>';
            html += '<div class="sffc-crm-feed-author">';
            html += '<strong>' + this.escapeHtml(recruiterName) + '</strong>';
            if (recruiterFirm) {
                html += '<span>' + this.escapeHtml(recruiterFirm) + '</span>';
            }
            html += '<span class="sffc-crm-feed-time">' + this.escapeHtml(postedAgo) + '</span>';
            html += '</div>';
            if (sector) {
                html += '<span class="sffc-crm-feed-pill">' + this.escapeHtml(sector) + '</span>';
            }
            html += '</div>';

            html += '<div class="sffc-crm-feed-body">';
            html += '<div class="sffc-crm-feed-title">' + this.escapeHtml(post.role_title || 'Untitled Role') + '</div>';
            if (company || location) {
                html += '<div class="sffc-crm-feed-subtitle">' + this.escapeHtml(company) + (company && location ? ' • ' : '') + this.escapeHtml(location) + '</div>';
            }
            html += '<div class="sffc-crm-feed-snippet">' + (snippet || 'Tap to add this mandate to your Smart message run or express interest.') + '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-feed-tags">';
            html += '<span class="sffc-crm-feed-score" style="color:' + matchColor + ';border-color:' + matchColor + ';">' + matchScore + '% match</span>';
            if (salaryText) {
                html += '<span>' + this.escapeHtml(salaryText) + '</span>';
            }
            if (seniority) {
                html += '<span>' + this.escapeHtml(seniority) + '</span>';
            }
            html += '</div>';

            html += '<div class="sffc-crm-feed-actions">';
            if (post.recruiter_id) {
                html += '<button type="button" class="sffc-crm-feed-action-btn sffc-crm-match-message-btn" '
                    + 'data-post-id="' + post.id + '" data-recruiter-id="' + post.recruiter_id + '" '
                    + 'data-recruiter-name="' + this.escapeHtml(recruiterName) + '" data-match-score="' + matchScore + '">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a3 3 0 0 1-3 3h-2v3"></path><path d="M6 16v2a3 3 0 0 0 3 3h2"></path><path d="M8 8a3 3 0 0 0-3 3v1"></path><path d="M16 16v3"></path><path d="M22 16h-6"></path></svg>';
                html += '<span>Express Interest</span>';
                html += '</button>';
            }

            if (post.application_url) {
                html += '<a href="' + this.escapeHtml(post.application_url) + '" class="sffc-crm-feed-action-btn" target="_blank" rel="noopener noreferrer">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>';
                html += '<span>Apply</span>';
                html += '</a>';
            }

            html += '<button type="button" class="sffc-crm-feed-action-btn sffc-crm-app-toolkit-btn" data-post-id="' + post.id + '">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="9"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>';
            html += '<span>Toolkit</span>';
            html += '</button>';

            if (post.recruiter_id) {
                html += '<button type="button" class="sffc-crm-feed-action-btn sffc-crm-match-introduce-btn" data-post-id="' + post.id + '" data-recruiter-id="' + post.recruiter_id + '" data-match-score="' + matchScore + '" data-recruiter-name="' + this.escapeHtml(recruiterName) + '">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>';
            html += '<span>View All Posts</span>';
                html += '</button>';
            }

            html += '</div>';
            html += '</article>';

            return html;
        },

        prefetchSmartApplyMap: function(force) {
            if (!this.config || !this.config.nonce) {
                return;
            }
            if (this.smartApplyMapRequested && !force) {
                return;
            }
            if (!force) {
                this.smartApplyMapRequested = true;
            }
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_smart_apply_requests',
                    nonce: this.config.nonce,
                    context: 'map'
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.smartApplyJobMap = {};
                        (response.data.job_ids || []).forEach(function(id) {
                            if (id) {
                                self.smartApplyJobMap[id] = true;
                            }
                        });
                        if (typeof response.data.total !== 'undefined') {
                            self.updateTabBadge('smart-apply', response.data.total);
                        }
                        self.refreshSmartApplyButtonStates();
                    }
                }
            });
        },

        refreshSmartApplyButtonStates: function() {
            var self = this;
            $('.sffc-crm-match-row').each(function() {
                var $row = $(this);
                var postId = parseInt($row.data('post-id'), 10);
                var added = postId && self.smartApplyJobMap && self.smartApplyJobMap[postId];
                self.updateSmartApplyButtonUI($row.find('.sffc-crm-match-introduce-btn'), !!added);
            });
        },

        updateSmartApplyButtonUI: function($btn, isAdded) {
            if (!$btn || !$btn.length) {
                return;
            }
            if (!$btn.data('default-html')) {
                $btn.data('default-html', $btn.html());
            }
            if (isAdded) {
                if (!$btn.hasClass('is-added')) {
                    $btn.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><polyline points="20 6 9 17 4 12"></polyline></svg>Added');
                }
                $btn.addClass('is-added').prop('disabled', true).attr('aria-pressed', 'true');
            } else {
                if ($btn.hasClass('is-added') && $btn.data('default-html')) {
                    $btn.html($btn.data('default-html'));
                }
                $btn.removeClass('is-added').prop('disabled', false).attr('aria-pressed', 'false');
            }
        },

        markSmartApplyAdded: function(postId) {
            if (!postId) {
                return;
            }
            if (!this.smartApplyJobMap) {
                this.smartApplyJobMap = {};
            }
            this.smartApplyJobMap[postId] = true;
            this.refreshSmartApplyButtonStates();
        },

        removeSmartApplyMapping: function(postId) {
            if (!postId || !this.smartApplyJobMap) {
                return;
            }
            delete this.smartApplyJobMap[postId];
            this.refreshSmartApplyButtonStates();
        },

        handleViewAllOpeningsButtonClick: function($btn) {
            if (!$btn || !$btn.length) {
                return;
            }

            if (!this.config.isLoggedIn) {
                this.showAuthModal();
                return;
            }

            var $row = $btn.closest('.sffc-crm-match-row');
            var recruiterId = $btn.data('recruiter-id') || ($row.length ? $row.data('recruiter-id') : null);
            recruiterId = recruiterId ? parseInt(recruiterId, 10) : null;

            if (!recruiterId) {
                this.showError('Recruiter contact is unavailable for this role.');
                return;
            }

            var recruiterName = $btn.data('recruiter-name') || ($row.length ? ($row.data('recruiter-name') || '') : '');
            var recruiterFirm = $row.length ? ($row.data('recruiter-firm') || '') : '';
            var recruiterTitle = $row.length ? ($row.data('recruiter-title') || '') : '';
            var recruiterEmail = $row.length ? ($row.data('recruiter-email') || '') : '';
            var recruiterLinkedin = $row.length ? ($row.data('recruiter-linkedin') || '') : '';
            var recruiterPhoto = '';
            var recruiterInitial = (recruiterName && recruiterName.length) ? recruiterName.charAt(0) : 'R';

            if ($row.length) {
                var $avatar = $row.find('.sffc-crm-match-avatar img');
                if ($avatar.length) {
                    recruiterPhoto = $avatar.attr('src') || '';
                    if ($avatar.data('initial')) {
                        recruiterInitial = $avatar.data('initial');
                    }
                }
            }

            this.recruiterOpeningsState = {
                recruiterId: recruiterId,
                recruiterName: recruiterName,
                recruiterFirm: recruiterFirm,
                recruiterTitle: recruiterTitle,
                recruiterEmail: recruiterEmail,
                recruiterLinkedIn: recruiterLinkedin,
                recruiterPhoto: recruiterPhoto,
                recruiterInitial: (recruiterInitial || 'R').toUpperCase(),
                posts: [],
                page: 0,
                perPage: 10,
                total: 0,
                hasMore: false,
                isLoading: false,
                errorMessage: ''
            };

            this.fetchRecruiterOpenings(1, false);
        },

        fetchRecruiterOpenings: function(page, append) {
            if (!this.recruiterOpeningsState || !this.recruiterOpeningsState.recruiterId) {
                return;
            }

            var self = this;
            var state = this.recruiterOpeningsState;
            state.isLoading = true;
            state.errorMessage = '';

            if (!append) {
                this.showModal('<div class="sffc-crm-modal-loading">Loading recruiter openings...</div>', 'sffc-crm-modal-content--openings');
            } else {
                this.updateModalContent(this.renderRecruiterOpeningsModal());
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_recruiter_openings',
                    nonce: this.config.nonce,
                    recruiter_id: state.recruiterId,
                    page: page
                }
            }).done(function(response) {
                state.isLoading = false;
                if (!response || !response.success) {
                    var message = (response && response.data && response.data.message) ? response.data.message : 'Unable to load openings right now.';
                    state.errorMessage = message;
                    self.updateModalContent(self.renderRecruiterOpeningsModal());
                    return;
                }

                var payload = response.data || {};
                var incoming = self.normalizeRecruiterOpenings(payload.posts || []);

                if (append && state.posts && state.posts.length) {
                    state.posts = state.posts.concat(incoming);
                } else {
                    state.posts = incoming;
                }

                state.page = payload.page || page;
                state.perPage = payload.per_page || state.perPage;
                state.total = typeof payload.total !== 'undefined' ? payload.total : state.total;
                state.hasMore = !!payload.has_more;

                if (payload.recruiter) {
                    var recruiter = payload.recruiter;
                    state.recruiterName = recruiter.name || state.recruiterName;
                    state.recruiterFirm = recruiter.firm || state.recruiterFirm;
                    state.recruiterTitle = recruiter.title || state.recruiterTitle;
                    state.recruiterEmail = recruiter.email || state.recruiterEmail;
                    state.recruiterLinkedIn = recruiter.linkedin_url || state.recruiterLinkedIn;
                    state.recruiterPhoto = recruiter.photo_url || state.recruiterPhoto;
                    if (state.recruiterName && state.recruiterName.length) {
                        state.recruiterInitial = state.recruiterName.charAt(0).toUpperCase();
                    }
                }

                self.updateModalContent(self.renderRecruiterOpeningsModal());
                self.activeModal = 'recruiter-openings';
            }).fail(function() {
                state.isLoading = false;
                state.errorMessage = 'Unable to load openings right now. Please try again.';
                self.updateModalContent(self.renderRecruiterOpeningsModal());
            });
        },

        normalizeRecruiterOpenings: function(posts) {
            posts = Array.isArray(posts) ? posts : [];
            return posts.map(function(post) {
                return {
                    id: parseInt(post.id, 10) || 0,
                    role_title: post.role_title || '',
                    company: post.company || '',
                    location: post.location || '',
                    sector: post.sector || '',
                    seniority: post.seniority || '',
                    salary_text: post.salary_text || '',
                    posted_at: post.posted_at || '',
                    snippet: post.snippet || '',
                    application_url: post.application_url || '',
                    source_url: post.source_url || '',
                    permalink: post.permalink || '',
                    match_score: parseInt(post.match_score, 10) || 0,
                    recruiter_id: post.recruiter_id || 0,
                    recruiter_email: post.recruiter_email || '',
                    recruiter_linkedin: post.recruiter_linkedin || '',
                    recruiter_photo: post.recruiter_photo || '',
                    recruiter_title: post.recruiter_title || '',
                    recruiter_firm: post.recruiter_firm || ''
                };
            });
        },

        renderRecruiterOpeningsModal: function() {
            var state = this.recruiterOpeningsState || {};
            var name = state.recruiterName || 'Recruiter';
            var metaLine = [];
            if (state.recruiterTitle) {
                metaLine.push(state.recruiterTitle);
            }
            if (state.recruiterFirm) {
                metaLine.push(state.recruiterFirm);
            }
            var totalLabel = state.total ? (state.total + ' live role' + (state.total === 1 ? '' : 's')) : 'Live mandates';
            var showingCount = state.posts ? state.posts.length : 0;

            var html = '<div class="sffc-crm-openings-modal">';
            html += '<button type="button" class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';

            html += '<div class="sffc-crm-openings-header">';
            html += '<div class="sffc-crm-openings-recruiter">';
            if (state.recruiterPhoto) {
                html += '<div class="sffc-crm-openings-avatar"><img src="' + this.escapeHtml(state.recruiterPhoto) + '" alt="' + this.escapeHtml(name) + '"></div>';
            } else {
                html += '<div class="sffc-crm-openings-avatar">' + this.escapeHtml(state.recruiterInitial || (name.charAt(0) || 'R')) + '</div>';
            }
            html += '<div class="sffc-crm-openings-recruiter-meta">';
            html += '<div class="sffc-crm-openings-name">' + this.escapeHtml(name) + '</div>';
            if (metaLine.length) {
                html += '<div class="sffc-crm-openings-meta-line">' + this.escapeHtml(metaLine.join(' • ')) + '</div>';
            }
            html += '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-openings-header-summary">';
            html += '<div class="sffc-crm-openings-total">' + this.escapeHtml(totalLabel) + '</div>';
            if (state.total && showingCount) {
                html += '<div class="sffc-crm-openings-count">Showing ' + this.escapeHtml(String(Math.min(showingCount, state.total))) + ' of ' + this.escapeHtml(String(state.total)) + '</div>';
            }
            if (state.recruiterEmail || state.recruiterLinkedIn) {
                html += '<div class="sffc-crm-openings-contact">';
                if (state.recruiterEmail) {
                    html += '<a class="sffc-crm-openings-link" href="mailto:' + this.escapeHtml(state.recruiterEmail) + '">Email</a>';
                }
                if (state.recruiterLinkedIn) {
                    html += '<a class="sffc-crm-openings-link" href="' + this.escapeHtml(state.recruiterLinkedIn) + '" target="_blank" rel="noopener">LinkedIn</a>';
                }
                html += '</div>';
            }
            html += '</div>';

            html += '</div>';

            html += '<div class="sffc-crm-openings-body">';
            if (state.errorMessage) {
                html += '<div class="sffc-crm-openings-error">' + this.escapeHtml(state.errorMessage) + '</div>';
            }

            var posts = state.posts || [];
            if (!posts.length && !state.errorMessage) {
                html += '<div class="sffc-crm-openings-empty">This recruiter hasn\'t posted additional openings yet.</div>';
            } else if (posts.length) {
                var self = this;
                html += '<div class="sffc-crm-openings-list">';
                posts.forEach(function(post) {
                    html += self.renderRecruiterOpeningCard(post, state);
                });
                html += '</div>';
            }

            if (state.hasMore) {
                var btnText = state.isLoading ? 'Loading roles…' : 'See more openings';
                html += '<div class="sffc-crm-openings-footer">';
                html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary" id="recruiter-openings-load-more" ' + (state.isLoading ? 'disabled' : '') + '>' + btnText + '</button>';
                html += '</div>';
            }

            html += '</div>';
            html += '</div>';

            return html;
        },

        renderRecruiterOpeningCard: function(post, state) {
            var companyBits = [];
            if (post.company) {
                companyBits.push(post.company);
            }
            if (post.location) {
                companyBits.push(post.location);
            }

            var postedAgo = post.posted_at ? (this.timeAgo(post.posted_at) || 'Just posted') : 'Just posted';
            var detailUrl = post.permalink || post.application_url || post.source_url;
            var recruiterNameAttr = this.escapeHtml(state.recruiterName || 'Recruiter');
            var recruiterEmailAttr = this.escapeHtml(post.recruiter_email || state.recruiterEmail || '');
            var recruiterLinkedAttr = this.escapeHtml(post.recruiter_linkedin || state.recruiterLinkedIn || '');
            var recruiterFirmAttr = this.escapeHtml(post.recruiter_firm || state.recruiterFirm || '');
            var recruiterTitleAttr = this.escapeHtml(post.recruiter_title || state.recruiterTitle || '');
            var isEarlyBird = !!post.is_early_bird;

            var html = '<div class="sffc-crm-match-row sffc-crm-openings-card" data-static-row="true" data-post-id="' + post.id + '" data-match-score="' + post.match_score + '" data-recruiter-id="' + state.recruiterId + '" data-recruiter-name="' + recruiterNameAttr + '" data-company="' + this.escapeHtml(post.company || '') + '" data-location="' + this.escapeHtml(post.location || '') + '" data-recruiter-email="' + recruiterEmailAttr + '" data-recruiter-linkedin="' + recruiterLinkedAttr + '" data-recruiter-firm="' + recruiterFirmAttr + '" data-recruiter-title="' + recruiterTitleAttr + '" data-early-bird="' + (isEarlyBird ? 1 : 0) + '">';

            html += '<div class="sffc-crm-openings-card-head">';
            html += '<div class="sffc-crm-openings-headline">';
            html += '<div class="sffc-crm-openings-role">' + this.escapeHtml(post.role_title || 'Open role');
                if (isEarlyBird) {
                    html += ' <span class="sffc-crm-early-bird-badge">Pro+ Members</span>';
                } else {
                    var badgeTextSaved = (post.response_label ? String(post.response_label) : '').trim() || 'Actively Hiring';
                    html += ' <span class="sffc-crm-free-contact-badge">' + self.escapeHtml(badgeTextSaved) + '</span>';
                }
            html += '</div>';
            if (companyBits.length) {
                html += '<div class="sffc-crm-openings-company">' + this.escapeHtml(companyBits.join(' • ')) + '</div>';
            }
            html += '</div>';
            html += '<div class="sffc-crm-openings-pill-group">';
            html += '<span class="sffc-crm-openings-pill">' + this.escapeHtml(postedAgo) + '</span>';
            if (post.match_score && post.match_score >= 40) {
                html += '<span class="sffc-crm-openings-pill">Match ' + this.escapeHtml(String(post.match_score)) + '%</span>';
            }
            html += '</div>';
            html += '</div>';

            if (post.snippet) {
                html += '<p class="sffc-crm-openings-snippet">' + this.escapeHtml(post.snippet) + '</p>';
            }

            var metaChips = [];
            if (post.seniority) {
                metaChips.push('Seniority: ' + post.seniority);
            }
            if (post.sector) {
                metaChips.push(post.sector);
            }
            if (post.salary_text) {
                metaChips.push(post.salary_text);
            }
            if (metaChips.length) {
                html += '<div class="sffc-crm-openings-meta-row">';
                metaChips.forEach(function(item) {
                    html += '<span>' + this.escapeHtml(item) + '</span>';
                }, this);
                html += '</div>';
            }

            html += '<div class="sffc-crm-openings-actions">';
            var recruiterFirstNameSaved = (state.recruiterName || 'Recruiter').split(/\s+/)[0] || 'Recruiter';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-match-message-btn sffc-crm-match-inline-btn" data-post-id="' + post.id + '" data-recruiter-id="' + state.recruiterId + '" data-recruiter-name="' + recruiterNameAttr + '" data-match-score="' + post.match_score + '">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">';
            html += '<rect x="3" y="5" width="18" height="14" rx="2"></rect>';
            html += '<path d="M3 7l9 6 9-6"></path>';
            html += '</svg>';
            html += 'Message ' + this.escapeHtml(recruiterFirstNameSaved) + '</button>';
            if (detailUrl) {
                html += '<a class="sffc-crm-openings-view-link" href="' + this.escapeHtml(detailUrl) + '" target="_blank" rel="noopener">View role</a>';
            }
            html += '</div>';

            html += '</div>';
            return html;
        },

        handleSmartApplyButtonClick: function($btn) {
            if (!$btn || !$btn.length) {
                return;
            }
            if ($btn.hasClass('is-added') || $btn.hasClass('is-loading')) {
                return;
            }
            var $matchRow = $btn.closest('.sffc-crm-match-row');
            var jobTitle = $matchRow.find('.sffc-crm-match-title').text().trim();
            var jobMeta = $matchRow.find('.sffc-crm-match-meta').text().trim();
            var metaParts = jobMeta ? jobMeta.split(' • ') : [];
            var company = metaParts[0] || '';
            var location = metaParts[1] || '';
            var recruiterName = $btn.data('recruiter-name') || $matchRow.data('recruiter-name') || '';
            var recruiterFirst = recruiterName ? recruiterName.split(/\s+/)[0] : 'there';
            var recruiterId = $btn.data('recruiter-id') || $matchRow.data('recruiter-id') || '';
            var postId = $matchRow.data('post-id');
            var matchScore = parseInt($btn.data('match-score'), 10);
            if (isNaN(matchScore)) {
                matchScore = parseInt($matchRow.data('match-score'), 10) || 0;
            }

            var matchReasons = [];
            $matchRow.find('.sffc-crm-match-reasons li span').each(function() {
                var text = $(this).text().trim();
                if (text) {
                    matchReasons.push(text);
                }
            });

            var matchWarnings = [];
            $matchRow.find('.sffc-crm-match-warning span').each(function() {
                var text = $(this).text().trim();
                if (text) {
                    matchWarnings.push(text);
                }
            });

            var keywordsAttr = $matchRow.data('keywords') || '';
            var keywords = [];
            if (Array.isArray(keywordsAttr)) {
                keywords = keywordsAttr;
            } else if (typeof keywordsAttr === 'string' && keywordsAttr.length) {
                keywords = keywordsAttr.split('|').map(function(keyword) {
                    return keyword.trim();
                }).filter(function(keyword) { return keyword.length > 0; });
            }

            if (!(this.config.features && this.config.features.ai_personalization)) {
                this.showMonetizationModal('smart_apply', {
                    jobTitle: jobTitle,
                    company: company
                });
                return;
            }

            this.launchSmartMessageModal({
                postId: postId,
                recruiterId: recruiterId,
                matchScore: matchScore,
                jobTitle: jobTitle,
                company: company,
                location: location,
                recruiterName: recruiterName,
                recruiterFirstName: recruiterFirst,
                matchReasons: matchReasons,
                matchWarnings: matchWarnings,
                keywords: keywords
            }, $btn);
        },

        enqueueSmartApplyRequest: function(context, $btn) {
            var self = this;
            $btn = $btn || $();
            if ($btn.length) {
                $btn.addClass('is-loading').prop('disabled', true).text('Adding...');
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_add_smart_apply_request',
                    nonce: this.config.nonce,
                    post_id: context.postId,
                    recruiter_id: context.recruiterId,
                    match_score: context.matchScore,
                    job_title: context.jobTitle,
                    company: context.company,
                    location: context.location,
                    recruiter_name: context.recruiterName,
                    notes: context.notes || ''
                },
                success: function(response) {
                    if ($btn.length) {
                        $btn.removeClass('is-loading');
                    }
                    if (response.success && response.data) {
                        if (response.data.job_id) {
                            self.markSmartApplyAdded(response.data.job_id);
                        }
                        if (typeof response.data.total !== 'undefined') {
                            self.updateTabBadge('smart-apply', response.data.total);
                        }
                        if (self.currentTab === 'smart-apply') {
                            self.loadSmartApplyTab();
                        }
                        self.showSuccess(response.data.already_added ? 'Already in Smart message' : 'Added to Smart message');
                        if (typeof context.onSuccess === 'function') {
                            context.onSuccess(response);
                        }
                    } else {
                        self.showError((response.data && response.data.message) || 'Unable to add Smart message request');
                        if ($btn.length) {
                            $btn.prop('disabled', false);
                            if ($btn.data('default-html')) {
                                $btn.html($btn.data('default-html'));
                            }
                        }
                        if (typeof context.onError === 'function') {
                            context.onError(response);
                        }
                    }
                },
                error: function() {
                    if ($btn.length) {
                        $btn.removeClass('is-loading').prop('disabled', false);
                        if ($btn.data('default-html')) {
                            $btn.html($btn.data('default-html'));
                        }
                    }
                    self.showError('Unable to add Smart message request');
                    if (typeof context.onError === 'function') {
                        context.onError();
                    }
                }
            });
        },

        launchSmartMessageModal: function(context, $btn) {
            context = context || {};
            this.smartMessageState = $.extend({}, context, {
                triggerButton: $btn || $(),
                isGenerating: false,
                generatedMessage: '',
                recruiterFirstName: context.recruiterFirstName || (context.recruiterName ? context.recruiterName.split(/\s+/)[0] : 'there')
            });

            var modalHtml = this.buildSmartMessageModal(context);
            this.showModal(modalHtml, 'sffc-crm-modal-content--smart-message');
            this.bindSmartMessageModalEvents();
            this.generateSmartMessageDraft(true);
        },

        buildSmartMessageModal: function(context) {
            var jobTitle = this.escapeHtml(context.jobTitle || 'Untitled Role');
            var company = context.company ? this.escapeHtml(context.company) : '';
            var location = context.location ? this.escapeHtml(context.location) : '';
            var recruiterName = this.escapeHtml(context.recruiterName || 'the hiring team');
            var score = parseInt(context.matchScore, 10) || 0;
            var metaLine = '';
            if (company && location) {
                metaLine = company + ' • ' + location;
            } else if (company) {
                metaLine = company;
            } else if (location) {
                metaLine = location;
            }

            var reasonsList = this.renderSmartMessageInsightList(context.matchReasons, 'Upload your CV or complete the match questions to see highlighted strengths.');
            var warningsList = this.renderSmartMessageInsightList(context.matchWarnings, 'No obvious gaps detected — we will still mention polish areas tactfully.');

            var html = '';
            html += '<div class="sffc-crm-smart-message-modal">';
            html += '<button type="button" class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>';
            html += '</svg>';
            html += '</button>';

            html += '<div class="sffc-crm-smart-message-header">';
            html += '<span class="sffc-crm-smart-message-eyebrow">Smart message queue</span>';
            html += '<h3>Approve this recruiter email</h3>';
            html += '<p>We auto-draft a concierge email with MENA Careers. Once you send it, the recruiter and role appear inside Smart message.</p>';
            html += '</div>';

            html += '<div class="sffc-crm-smart-message-role">';
            html += '<div class="sffc-crm-smart-message-role-info">';
            html += '<strong>' + jobTitle + '</strong>';
            if (metaLine) {
                html += '<p>' + metaLine + '</p>';
            }
            html += '</div>';
            html += '<div class="sffc-crm-smart-message-role-meta">';
            html += '<div class="sffc-crm-smart-message-score">';
            html += '<span>Match score</span>';
            html += '<strong>' + score + '%</strong>';
            html += '</div>';
            html += '<div class="sffc-crm-smart-message-recruiter">';
            html += '<span>Recruiter</span>';
            html += '<strong>' + recruiterName + '</strong>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-smart-message-layout">';
            html += '  <div class="sffc-crm-smart-message-editor">';
            html += '    <div class="sffc-crm-smart-message-editor-head">';
            html += '      <span class="sffc-crm-smart-message-pill">MENA Careers AI</span>';
            html += '      <button type="button" class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" id="smart-message-regenerate">';
            html += '        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '          <path d="M21 3v6h-6"></path><path d="M3 21v-6h6"></path><path d="M3 11a9 9 0 0 1 14-7"></path><path d="M21 13a9 9 0 0 1-14 7"></path>';
            html += '        </svg>';
            html += '        Regenerate';
            html += '      </button>';
            html += '    </div>';
            html += '    <textarea id="smart-message-output" class="sffc-crm-textarea" rows="8" placeholder="Generating your tailored note..."></textarea>';
            html += '    <div class="sffc-crm-smart-message-meta">';
            html += '      <span class="sffc-crm-smart-message-status is-loading" id="smart-message-status">Preparing MENA Careers AI copy…</span>';
            html += '      <button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-small" id="smart-message-copy">';
            html += '        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '          <rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>';
            html += '        </svg>';
            html += '        Copy message';
            html += '      </button>';
            html += '    </div>';
            html += '  </div>';
            html += '  <div class="sffc-crm-smart-message-side">';
            html += '    <div class="sffc-crm-smart-message-card">';
            html += '      <p class="sffc-crm-smart-message-card-label">Why this stands out</p>';
            html +=        reasonsList;
            html += '    </div>';
            html += '    <div class="sffc-crm-smart-message-card">';
            html += '      <p class="sffc-crm-smart-message-card-label">We\'ll address</p>';
            html +=        warningsList;
            html += '    </div>';
            html += '  </div>';
            html += '</div>';

            html += '<div class="sffc-crm-smart-message-footer">';
            html += '  <div class="sffc-crm-smart-message-footer-copy">We\'ll add this recruiter to Smart message once you hit send.</div>';
            html += '  <div class="sffc-crm-smart-message-footer-actions">';
            html += '    <button type="button" class="sffc-crm-btn sffc-crm-btn-primary" id="smart-message-submit">Send Smart message</button>';
            html += '    <button type="button" class="sffc-crm-btn sffc-crm-btn-text" data-action="close">Keep browsing roles</button>';
            html += '  </div>';
            html += '</div>';

            html += '</div>';
            return html;
        },

        renderSmartMessageInsightList: function(items, emptyCopy) {
            var list = Array.isArray(items) ? items.filter(Boolean) : [];
            var self = this;
            if (!list.length) {
                return '<p class="sffc-crm-smart-message-empty">' + this.escapeHtml(emptyCopy || 'Insights will appear once we review your profile.') + '</p>';
            }
            var html = '<ul class="sffc-crm-smart-message-list">';
            list.slice(0, 3).forEach(function(entry) {
                html += '<li>';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                html += '<polyline points="20 6 9 17 4 12"></polyline>';
                html += '</svg>';
                html += '<span>' + self.escapeHtml(entry) + '</span>';
                html += '</li>';
            });
            html += '</ul>';
            return html;
        },

        bindSmartMessageModalEvents: function() {
            var self = this;
            $(document).off('click.smartMessageCopy', '#smart-message-copy').on('click.smartMessageCopy', '#smart-message-copy', function(e) {
                e.preventDefault();
                self.copySmartMessageToClipboard();
            });
            $(document).off('click.smartMessageRegen', '#smart-message-regenerate').on('click.smartMessageRegen', '#smart-message-regenerate', function(e) {
                e.preventDefault();
                self.generateSmartMessageDraft(false);
            });
            $(document).off('click.smartMessageSubmit', '#smart-message-submit').on('click.smartMessageSubmit', '#smart-message-submit', function(e) {
                e.preventDefault();
                self.submitSmartMessage();
            });
        },

        generateSmartMessageDraft: function(isAuto) {
            var self = this;
            var state = this.smartMessageState;
            if (!state || !state.postId) {
                return;
            }
            var $field = $('#smart-message-output');
            var $regenerate = $('#smart-message-regenerate');

            if (!(this.config.features && this.config.features.ai_personalization)) {
                $field.val(this.buildIntroMessage(state));
                this.setSmartMessageStatus('Upgrade to unlock concierge AI copy.', 'warning');
                return;
            }

            if (state.isGenerating) {
                return;
            }
            state.isGenerating = true;
            this.setSmartMessageStatus('Generating with MENA Careers AI…', 'loading');
            $regenerate.prop('disabled', true).text('Generating...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_generate_intro_message',
                    nonce: this.config.nonce,
                    post_id: state.postId,
                    recruiter_id: state.recruiterId,
                    match_score: state.matchScore || 0,
                    match_reasons: state.matchReasons || [],
                    match_warnings: state.matchWarnings || [],
                    keywords: state.keywords || []
                },
                success: function(response) {
                    var messageText = '';
                    if (response.success && response.data && response.data.message) {
                        messageText = response.data.message;
                        state.generatedMessage = messageText;
                        self.setSmartMessageStatus('Smart message ready — review and send.', 'success');
                    } else {
                        if (!isAuto) {
                            self.showError((response.data && response.data.message) || 'AI message unavailable');
                        }
                        messageText = self.buildIntroMessage(state);
                        self.setSmartMessageStatus('Using template copy until AI is available.', 'warning');
                    }
                    $field.val(messageText);
                },
                error: function() {
                    if (!isAuto) {
                        self.showError('Unable to generate Smart message');
                    }
                    $field.val(self.buildIntroMessage(state));
                    self.setSmartMessageStatus('We could not reach MENA Careers AI. Try again shortly.', 'error');
                },
                complete: function() {
                    state.isGenerating = false;
                    $regenerate.prop('disabled', false).text('Regenerate');
                }
            });
        },

        setSmartMessageStatus: function(text, state) {
            var $status = $('#smart-message-status');
            if (!$status.length) {
                return;
            }
            $status.text(text || '');
            $status.removeClass('is-loading is-success is-warning is-error');
            if (state) {
                $status.addClass('is-' + state);
            }
        },

        copySmartMessageToClipboard: function() {
            var self = this;
            var message = $('#smart-message-output').val();
            if (!message) {
                this.showError('There is no message to copy yet.');
                return;
            }
            var handleSuccess = function() {
                self.showSuccess('Smart message copied to your clipboard.');
            };
            var handleError = function() {
                self.showError('Unable to copy the message.');
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(message).then(handleSuccess).catch(handleError);
                return;
            }

            var temp = document.createElement('textarea');
            temp.value = message;
            document.body.appendChild(temp);
            temp.select();
            try {
                document.execCommand('copy');
                handleSuccess();
            } catch (err) {
                handleError();
            }
            document.body.removeChild(temp);
        },

        submitSmartMessage: function() {
            var state = this.smartMessageState;
            if (!state) {
                return;
            }
            var self = this;
            var $submit = $('#smart-message-submit');
            var message = $('#smart-message-output').val().trim();

            if (!message.length) {
                this.showError('Add or wait for a Smart message before sending.');
                return;
            }

            $submit.addClass('is-loading').prop('disabled', true).text('Adding...');

            this.enqueueSmartApplyRequest({
                postId: state.postId,
                recruiterId: state.recruiterId,
                matchScore: state.matchScore,
                jobTitle: state.jobTitle,
                company: state.company,
                location: state.location,
                recruiterName: state.recruiterName,
                notes: message,
                onSuccess: function() {
                    self.closeModal();
                },
                onError: function() {
                    $submit.removeClass('is-loading').prop('disabled', false).text('Send Smart message');
                }
            }, state.triggerButton);
        },

        submitSmartApplyBrief: function($form) {
            if (!$form || !$form.length) {
                return;
            }
            var self = this;
            var data = {
                role_focus: $form.find('[name="role_focus"]').val().trim(),
                target_locations: $form.find('[name="target_locations"]').val().trim(),
                compensation: $form.find('[name="compensation"]').val().trim(),
                additional_notes: $form.find('[name="additional_notes"]').val().trim()
            };

            if (!data.role_focus.length) {
                this.showError('Please describe the roles you want to target.');
                return;
            }

            var $submit = $form.find('button[type="submit"]');
            $submit.prop('disabled', true).text('Submitting...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: Object.assign({}, data, {
                    action: 'sffc_crm_submit_smart_apply_brief',
                    nonce: this.config.nonce
                }),
                success: function(response) {
                    $submit.prop('disabled', false).text('Submit Smart message brief');
                    if (response.success) {
                        self.showSuccess((response.data && response.data.message) || 'Brief received');
                        $form[0].reset();
                        if (response.data && typeof response.data.total !== 'undefined') {
                            self.updateTabBadge('smart-apply', response.data.total);
                        }
                        if (self.currentTab === 'smart-apply') {
                            self.loadSmartApplyTab();
                        }
                    } else {
                        self.showError((response.data && response.data.message) || 'Unable to submit brief');
                    }
                },
                error: function() {
                    $submit.prop('disabled', false).text('Submit Smart message brief');
                    self.showError('Unable to submit brief');
                }
            });
        },

        bindMatchEvents: function() {
            var self = this;
            var membershipOnlySelectors = this.membershipOnlySelectors;

            // Match row click - open gap analyzer
            $(document).off('click.matchRow', '.sffc-crm-match-row').on('click.matchRow', '.sffc-crm-match-row', function(e) {
                var $row = $(this);
                if ($row.data('staticRow')) {
                    return;
                }

                // Ignore clicks on buttons and checkboxes
                if ($(e.target).closest('button, .sffc-crm-match-checkbox, .sffc-crm-apply-btn').length) {
                    return;
                }

                if (!self.config.isLoggedIn) {
                    self.showAuthModal();
                    return;
                }

                var postId = $row.data('post-id');
                if (postId) {
                    self.captureGapAnalyzerContextFromRow($row);
                    self.openGapAnalyzerModal(postId);
                }
            });

            // View all openings button
            $(document).off('click.matchIntroduce', '.sffc-crm-match-introduce-btn').on('click.matchIntroduce', '.sffc-crm-match-introduce-btn', function(e) {
                e.stopPropagation();
                self.handleViewAllOpeningsButtonClick($(this));
            });

            // Express Interest button - opens interest modal
            $(document).off('click.matchMessage', '.sffc-crm-match-message-btn').on('click.matchMessage', '.sffc-crm-match-message-btn', function(e) {
                e.stopPropagation();
                var $btn = $(this);
                var $matchRow = $btn.closest('.sffc-crm-match-row');

                if (!self.config.isLoggedIn) {
                    self.showAuthModal();
                    return;
                }

                var postId = $btn.data('post-id');
                var recruiterId = $btn.data('recruiter-id');
                var recruiterName = $btn.data('recruiter-name');
                var matchScore = $btn.data('match-score');
                if (typeof matchScore === 'undefined' || matchScore === '') {
                    matchScore = $matchRow.data('match-score');
                }
                matchScore = parseInt(matchScore, 10) || 0;

                var matchReasons = [];
                var matchWarnings = [];

                $matchRow.find('.sffc-crm-match-reasons li').each(function() {
                    matchReasons.push($(this).find('span').text().trim());
                });

                $matchRow.find('.sffc-crm-match-warning span').each(function() {
                    matchWarnings.push($(this).text().trim());
                });

                var jobTitle = $matchRow.find('.sffc-crm-match-title').text().trim();
                var jobMeta = $matchRow.find('.sffc-crm-match-meta').text().trim();
                var metaParts = jobMeta.split(' • ');
                var company = metaParts[0] || '';
                var location = metaParts[1] || '';
                var keywordsAttr = $matchRow.data('keywords') || '';
                var jobKeywords = [];
                if (Array.isArray(keywordsAttr)) {
                    jobKeywords = keywordsAttr;
                } else if (typeof keywordsAttr === 'string' && keywordsAttr.length) {
                    jobKeywords = keywordsAttr.split('|').map(function(keyword) {
                        return keyword.trim();
                    }).filter(function(keyword) { return keyword.length > 0; });
                }

                var $avatar = $matchRow.find('.sffc-crm-match-avatar img');
                var recruiterPhoto = $avatar.length ? $avatar.attr('src') : '';
                var recruiterInitial = $avatar.length ? $avatar.data('initial') : (recruiterName.charAt(0) || 'S');

                // Extract Premium Members status and recruiter contact info
                var isEarlyBird = $matchRow.data('early-bird') == 1;
                var recruiterEmail = $matchRow.data('recruiter-email') || '';
                var recruiterFirm = $matchRow.data('recruiter-firm') || '';
                var recruiterTitle = $matchRow.data('recruiter-title') || '';

                self.showExpressInterestModal(postId, recruiterId, recruiterName, matchScore, matchReasons, matchWarnings, {
                    jobTitle: jobTitle,
                    company: company,
                    location: location,
                    recruiterEmail: recruiterEmail,
                    recruiterLinkedIn: $matchRow.data('recruiter-linkedin') || '',
                    recruiterFirm: recruiterFirm,
                    recruiterTitle: recruiterTitle,
                    recruiterPhoto: recruiterPhoto,
                    recruiterInitial: recruiterInitial,
                    keywords: jobKeywords,
                    isEarlyBird: isEarlyBird
                });
            });

            $(document).off('click.authActions', '.sffc-crm-match-actions, .sffc-crm-match-inline-btn, .sffc-crm-match-actions .sffc-crm-btn, .sffc-crm-feed-action-btn').on('click.authActions', '.sffc-crm-match-actions, .sffc-crm-match-inline-btn, .sffc-crm-match-actions .sffc-crm-btn, .sffc-crm-feed-action-btn', function(e) {
                var $target = $(e.target);
                if ($target.closest('.sffc-crm-match-select, label').length) {
                    return;
                }

                var requiresMembership = $target.closest(membershipOnlySelectors).length > 0;

                // If logged out, show auth modal (State 1 - create account)
                if (!self.config.isLoggedIn) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.stopImmediatePropagation) {
                        e.stopImmediatePropagation();
                    }
                    self.showAuthModal();
                    return;
                }

                if (requiresMembership && !self.config.isPremium) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.stopImmediatePropagation) {
                        e.stopImmediatePropagation();
                    }
                    self.showMembershipPrompt();
                    return;
                }

                // If logged in, check if they've seen membership prompt
                var hasSeenMembershipPrompt = localStorage.getItem('sffc_crm_seen_membership_prompt');

                // If they haven't seen it yet, show membership selection (State 2)
                if (!hasSeenMembershipPrompt && !self.config.isPremium) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.stopImmediatePropagation) {
                        e.stopImmediatePropagation();
                    }
                    self.showMembershipPrompt();
                    return;
                }

                // Otherwise, let the action proceed normally
            });

            // Save button in matches
            $(document).off('click.matchSave', '.sffc-crm-match-row .sffc-crm-save-btn').on('click.matchSave', '.sffc-crm-match-row .sffc-crm-save-btn', function(e) {
                e.stopPropagation();
                var $btn = $(this);
                var postId = $btn.data('post-id');
                var isSaved = $btn.hasClass('is-saved');

                if (isSaved) {
                    self.unsavePost(postId, $btn);
                } else {
                    self.savePost(postId, $btn);
                }
            });

            // Application Toolkit button in matches
            $(document).off('click.appToolkit', '.sffc-crm-app-toolkit-btn').on('click.appToolkit', '.sffc-crm-app-toolkit-btn', function(e) {
                e.stopPropagation();
                var postId = $(this).data('post-id');
                self.openGapAnalyzerModal(postId);
            });
        },

        /**
         * Initialize multi-selection handlers
         */
        initMultiSelection: function() {
            var self = this;

            // Checkbox change handler
            $(document).off('change.matchSelect', '.sffc-crm-match-select').on('change.matchSelect', '.sffc-crm-match-select', function() {
                var $checkbox = $(this);
                var $row = $checkbox.closest('.sffc-crm-match-row');

                if ($checkbox.is(':checked')) {
                    if (self.enforceEarlyBirdSelection($checkbox)) {
                        return;
                    }
                    $row.addClass('is-selected');
                } else {
                    $row.removeClass('is-selected');
                }

                self.updateFloatingActions();
            });

            // Bulk Add to Outreach List button (Matches tab)
            $(document).off('click.bulkIntros', '#bulk-request-intros').on('click.bulkIntros', '#bulk-request-intros', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (!self.config.isLoggedIn) {
                    self.showAuthModal();
                    return;
                }

                if (!self.config.isPremium) {
                    self.showMembershipPrompt();
                    return;
                }

                self.showAddToOutreachListModalMatches();
            });

            // Bulk Add to Watchlist button (Matches tab)
            $(document).off('click.bulkOutreach', '#bulk-smart-outreach').on('click.bulkOutreach', '#bulk-smart-outreach', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (!self.config.isLoggedIn) {
                    self.showAuthModal();
                    return;
                }

                if (!self.config.isPremium) {
                    self.showMembershipPrompt();
                    return;
                }

                self.handleBulkAddToWatchlistMatches();
            });
        },

        /**
         * Update floating action buttons based on selection
         */
        updateFloatingActions: function() {
            var $selected = $('.sffc-crm-match-select:checked');
            var totalSelected = $selected.length;

            if (totalSelected === 0) {
                $('.sffc-crm-floating-actions').fadeOut(200);
                return;
            }

            // Update count
            $('.sffc-crm-selected-count').text(totalSelected);

            // Both buttons are always enabled when items are selected
            $('#bulk-request-intros').prop('disabled', false).css('opacity', '1');
            $('#bulk-smart-outreach').prop('disabled', false).css('opacity', '1');

            // Show floating actions
            $('.sffc-crm-floating-actions').fadeIn(200);
        },

        /**
         * Load all badge counts immediately on page load
         */
        loadAllBadgeCounts: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_badge_counts',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Update all badge counts
                        if (response.data.matches !== undefined) {
                            self.updateTabBadge('matches', response.data.matches);
                        }
                        if (response.data.saved !== undefined) {
                            self.updateTabBadge('saved', response.data.saved);
                        }
                        if (response.data.outreach_lists !== undefined) {
                            self.updateTabBadge('outreach-lists', response.data.outreach_lists);
                        }
                        if (response.data.smart_apply !== undefined) {
                            self.updateTabBadge('smart-apply', response.data.smart_apply);
                        }
                        if (response.data.recruiter_intros !== undefined) {
                            self.updateTabBadge('recruiter-intros', response.data.recruiter_intros);
                        }
                        if (response.data.replies !== undefined) {
                            self.updateTabBadge('replies', response.data.replies);
                        }
                    }
                }
            });
        },

        /**
         * Update tab notification badge
         */
        updateTabBadge: function(tab, count) {
            var $badges = $('[data-badge="' + tab + '"]');

            // Always show badge and update count (like LinkedIn/Facebook)
            $badges.text(count > 99 ? '99+' : count).show();
        },

        /**
         * Update matches badge count
         */
        updateMatchesBadge: function() {
            // This can be called when new matches are loaded
            // For now, we'll set it based on match count
            var matchCount = $('.sffc-crm-match-row').length;
            if (matchCount > 0) {
                this.updateTabBadge('matches', matchCount);
            }
        },

        /**
         * Update outreach lists badge count
         */
        updateOutreachListsBadge: function(count) {
            this.updateTabBadge('outreach-lists', count);
        },

        /**
         * Handle bulk request intros
         */
        handleBulkRequestIntros: function() {
            var self = this;
            if (!(self.config.features && self.config.features.ai_personalization)) {
                return;
            }
            if (!self.config.isLoggedIn || !self.config.isPremium) {
                if (!self.config.isLoggedIn) {
                    self.showAuthModal();
                } else {
                    self.showMonetizationModal('intro');
                }
                return;
            }
            var selectedPosts = [];

            // Collect selected posts that are eligible (60%+)
            $('.sffc-crm-match-select:checked').each(function() {
                var $row = $(this).closest('.sffc-crm-match-row');
                var matchScore = parseInt($row.data('match-score')) || 0;

                if (matchScore >= 60) {
                    selectedPosts.push({
                        postId: $row.data('post-id'),
                        recruiterId: $row.data('recruiter-id'),
                        recruiterName: $row.data('recruiter-name'),
                        matchScore: matchScore
                    });
                }
            });

            if (selectedPosts.length === 0) {
                this.showError('No eligible posts selected for Express Interest');
                return;
            }

            // Show confirmation modal
            this.showBulkIntroConfirmation(selectedPosts);
        },

        /**
         * Show bulk introduction confirmation
         */
        showBulkIntroConfirmation: function(posts) {
            var self = this;

            var html = '<div class="sffc-crm-bulk-intro-modal">';
            html += '<h3>Express interest in ' + posts.length + ' role' + (posts.length > 1 ? 's' : '') + '?</h3>';
            html += '<p>You\'re about to express interest with the following recruiters:</p>';
            html += '<ul class="sffc-crm-bulk-intro-list">';
            posts.forEach(function(post) {
                html += '<li>';
                html += '<strong>' + self.escapeHtml(post.recruiterName) + '</strong>';
                html += '<span class="sffc-crm-bulk-intro-score">' + post.matchScore + '% match</span>';
                html += '</li>';
            });
            html += '</ul>';
            html += '<p class="sffc-crm-bulk-intro-note">The MENA Careers team will review your profile and send personalized express-interest notes within 24 hours.</p>';
            html += '<div class="sffc-crm-modal-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary" data-action="close">Cancel</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="confirm-bulk-intros">Send Express Interest</button>';
            html += '</div>';
            html += '</div>';

            this.showModal(html);

            // Bind confirmation
            $('#confirm-bulk-intros').on('click', function() {
                self.submitBulkIntroRequests(posts);
            });
        },

        /**
         * Submit bulk intro requests
         */
        submitBulkIntroRequests: function(posts) {
            var self = this;

            // Show loading
            this.showModal('<div class="sffc-crm-modal-loading">Submitting Express Interest...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_bulk_request_intros',
                    posts: posts.map(function(p) { return { post_id: p.postId, recruiter_id: p.recruiterId, match_score: p.matchScore }; }),
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.closeModal();
                        self.showSuccess(posts.length + ' Express Interest request' + (posts.length > 1 ? 's' : '') + ' submitted successfully');

                        // Uncheck all selected checkboxes
                        $('.sffc-crm-match-select:checked').prop('checked', false).trigger('change');

                        // Refresh intro usage counter and mark the Recruiter Intros
                        // tab for reload so the new requests appear immediately.
                        self.fetchIntroUsage();
                        self.refreshRecruiterIntrosBadge();
                    } else {
                        self.closeModal();
                        self.handleError(response);
                    }
                },
                error: function() {
                    self.closeModal();
                    self.showError('Failed to submit Express Interest requests');
                }
            });
        },

        /**
         * Handle bulk smart outreach
         */
        handleBulkSmartOutreach: function() {
            var recruiterEntries = [];

            $('.sffc-crm-match-select:checked').each(function() {
                var $row = $(this).closest('.sffc-crm-match-row');
                var recruiterId = $row.data('recruiter-id');
                if (recruiterId) {
                    recruiterEntries.push({
                        id: recruiterId,
                        name: $row.data('recruiter-name') || 'Recruiter'
                    });
                }
            });

            if (!recruiterEntries.length) {
                this.showError('Select at least one recruiter to add to Smart message.');
                return;
            }

            this.bulkSelection.mode = true;
            this.bulkSelection.type = 'recruiters';
            this.bulkSelection.selected = recruiterEntries;
            this.openAddToOutreachListModal();
        },

        /**
         * Start smart outreach flow (one-at-a-time)
         */
        startSmartOutreachFlow: function(posts) {
            this.smartOutreachQueue = posts;
            this.smartOutreachIndex = 0;
            this.smartOutreachResults = [];

            // Hide floating actions
            $('.sffc-crm-floating-actions').fadeOut(200);

            // Show first outreach
            this.showNextSmartOutreach();
        },

        /**
         * Show next smart outreach in queue
         */
        showNextSmartOutreach: function() {
            var self = this;

            if (this.smartOutreachIndex >= this.smartOutreachQueue.length) {
                // All done - show summary
                this.showSmartOutreachSummary();
                return;
            }

            var currentPost = this.smartOutreachQueue[this.smartOutreachIndex];
            var progress = {
                current: this.smartOutreachIndex + 1,
                total: this.smartOutreachQueue.length
            };

            // Show loading modal while fetching data
            this.showModal('<div class="sffc-crm-modal-loading">Generating personalized outreach...</div>');

            // Fetch compose data and render smart outreach modal
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_compose_data',
                    post_id: currentPost.postId,
                    recruiter_id: currentPost.recruiterId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderSmartOutreachModal(response.data, progress);
                    } else {
                        self.closeModal();
                        self.handleError(response);
                    }
                },
                error: function() {
                    self.closeModal();
                    self.showError('Failed to load outreach data');
                }
            });
        },

        /**
         * Render redesigned smart outreach modal
         */
        renderSmartOutreachModal: function(data, progress) {
            var self = this;

            var html = '<div class="sffc-crm-smart-outreach-modal">';

            // Header with progress
            html += '<div class="sffc-crm-smart-outreach-header">';
            html += '<div class="sffc-crm-smart-outreach-progress">';
            html += '<span class="sffc-crm-outreach-step">Outreach ' + progress.current + ' of ' + progress.total + '</span>';
            html += '<div class="sffc-crm-outreach-progress-bar">';
            html += '<div class="sffc-crm-outreach-progress-fill" style="width: ' + ((progress.current / progress.total) * 100) + '%;"></div>';
            html += '</div>';
            html += '</div>';
            html += '<button class="sffc-crm-modal-close" aria-label="Close">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';

            // Recruiter & Role Info
            html += '<div class="sffc-crm-smart-outreach-info">';
            html += '<div class="sffc-crm-smart-outreach-recruiter">';
            if (data.recruiter.photo_url) {
                html += '<img src="' + this.escapeHtml(data.recruiter.photo_url) + '" alt="" class="sffc-crm-outreach-avatar">';
            } else {
                html += '<div class="sffc-crm-outreach-avatar sffc-crm-avatar-placeholder">' + data.recruiter.name.charAt(0) + '</div>';
            }
            html += '<div class="sffc-crm-outreach-details">';
            html += '<h4>' + this.escapeHtml(data.recruiter.name) + '</h4>';
            if (data.recruiter.firm) {
                html += '<p class="sffc-crm-outreach-firm">' + this.escapeHtml(data.recruiter.firm) + '</p>';
            }
            if (data.post && data.post.role_title) {
                html += '<p class="sffc-crm-outreach-role">' + this.escapeHtml(data.post.role_title) + '</p>';
            }
            html += '</div>';
            html += '</div>';
            html += '</div>';

            // AI-Generated Message Section
            html += '<div class="sffc-crm-smart-outreach-content">';
            html += '<div class="sffc-crm-outreach-message-section">';
            html += '<label for="smart-outreach-subject">Subject</label>';
            html += '<input type="text" id="smart-outreach-subject" class="sffc-crm-outreach-input" placeholder="Generating...">';
            html += '</div>';

            html += '<div class="sffc-crm-outreach-message-section">';
            html += '<label for="smart-outreach-message">Message</label>';
            html += '<div class="sffc-crm-ai-status">';
            html += '<svg class="sffc-crm-ai-loading" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>';
            html += '<span>MENA Careers is personalizing your message...</span>';
            html += '</div>';
            html += '<textarea id="smart-outreach-message" class="sffc-crm-outreach-textarea" rows="12" placeholder="Generating personalized message..."></textarea>';
            html += '<div class="sffc-crm-outreach-char-count"><span id="smart-outreach-char-count">0</span> characters</div>';
            html += '</div>';

            html += '<div class="sffc-crm-outreach-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-secondary" id="mark-pending-btn" data-post-id="' + (data.post ? data.post.id : '') + '" data-recruiter-id="' + data.recruiter.id + '">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<circle cx="12" cy="12" r="10"></circle>';
            html += '<polyline points="12 6 12 12 16 14"></polyline>';
            html += '</svg>';
            html += 'Mark as Pending';
            html += '</button>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" id="mark-outreached-btn" data-post-id="' + (data.post ? data.post.id : '') + '" data-recruiter-id="' + data.recruiter.id + '">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>';
            html += '<polyline points="22,6 12,13 2,6"></polyline>';
            html += '</svg>';
            html += 'Mark as Outreached';
            html += '</button>';
            html += '</div>';

            html += '</div>';

            html += '</div>';

            this.updateModalContent(html);

            // Auto-generate AI message
            this.generateSmartOutreachMessage(data);

            // Bind smart outreach events
            this.bindSmartOutreachEvents();
        },

        /**
         * Generate AI message for smart outreach
         */
        generateSmartOutreachMessage: function(data) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_ai_generate',
                    context: 'outreach',
                    post_id: data.post ? data.post.id : null,
                    recruiter_id: data.recruiter.id,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $('#smart-outreach-subject').val(response.data.subject || '');
                        $('#smart-outreach-message').val(response.data.message || '');
                        $('#smart-outreach-char-count').text((response.data.message || '').length);

                        // Hide AI loading status
                        $('.sffc-crm-ai-status').fadeOut(300);
                    } else {
                        $('.sffc-crm-ai-status span').text('Failed to generate. Please write manually.');
                    }
                },
                error: function() {
                    $('.sffc-crm-ai-status span').text('Failed to generate. Please write manually.');
                }
            });

            // Update character count on typing
            $('#smart-outreach-message').on('input', function() {
                $('#smart-outreach-char-count').text($(this).val().length);
            });
        },

        /**
         * Bind smart outreach events
         */
        bindSmartOutreachEvents: function() {
            var self = this;

            // Mark as Pending
            $('#mark-pending-btn').on('click', function() {
                var postId = $(this).data('post-id');
                var recruiterId = $(this).data('recruiter-id');
                var subject = $('#smart-outreach-subject').val();
                var message = $('#smart-outreach-message').val();

                self.markSmartOutreachStatus(postId, recruiterId, 'pending', subject, message);
            });

            // Mark as Outreached
            $('#mark-outreached-btn').on('click', function() {
                var postId = $(this).data('post-id');
                var recruiterId = $(this).data('recruiter-id');
                var subject = $('#smart-outreach-subject').val();
                var message = $('#smart-outreach-message').val();

                if (!subject || !message) {
                    self.showError('Please complete the subject and message');
                    return;
                }

                self.markSmartOutreachStatus(postId, recruiterId, 'outreached', subject, message);
            });
        },

        /**
         * Mark smart outreach status and move to next
         */
        markSmartOutreachStatus: function(postId, recruiterId, status, subject, message) {
            var self = this;

            // Record result
            this.smartOutreachResults.push({
                postId: postId,
                recruiterId: recruiterId,
                status: status,
                subject: subject,
                message: message
            });

            // Move to next
            this.smartOutreachIndex++;

            // Uncheck the current match row
            $('.sffc-crm-match-select[data-post-id="' + postId + '"]').prop('checked', false).trigger('change');

            // Show next or summary
            this.showNextSmartOutreach();
        },

        /**
         * Show smart outreach summary
         */
        showSmartOutreachSummary: function() {
            var outreached = this.smartOutreachResults.filter(function(r) { return r.status === 'outreached'; }).length;
            var pending = this.smartOutreachResults.filter(function(r) { return r.status === 'pending'; }).length;

            var html = '<div class="sffc-crm-outreach-summary-modal">';
            html += '<div class="sffc-crm-outreach-summary-icon">';
            html += '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">';
            html += '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>';
            html += '<polyline points="22 4 12 14.01 9 11.01"></polyline>';
            html += '</svg>';
            html += '</div>';
            html += '<h3>Smart Outreach Complete!</h3>';
            html += '<div class="sffc-crm-outreach-summary-stats">';
            html += '<div class="sffc-crm-outreach-stat">';
            html += '<span class="sffc-crm-outreach-stat-number">' + outreached + '</span>';
            html += '<span class="sffc-crm-outreach-stat-label">Outreached</span>';
            html += '</div>';
            html += '<div class="sffc-crm-outreach-stat">';
            html += '<span class="sffc-crm-outreach-stat-number">' + pending + '</span>';
            html += '<span class="sffc-crm-outreach-stat-label">Pending</span>';
            html += '</div>';
            html += '</div>';
            html += '<p>Your pipeline has been updated with the outreach status.</p>';
            html += '<button class="sffc-crm-btn sffc-crm-btn-primary" data-action="close">Close</button>';
            html += '</div>';

            this.showModal(html);

            // Refresh pipeline to show updates
            if (this.currentTab === 'pipeline') {
                this.loadPipeline();
            }
        },

        getMatchColor: function(score) {
            if (score >= 80) return '#10b981'; // Green
            if (score >= 60) return '#3b82f6'; // Blue
            if (score >= 40) return '#f59e0b'; // Orange
            return '#ef4444'; // Red
        },

        /**
         * Fetch and display intro usage counter
         */
        fetchIntroUsage: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_intro_usage',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.displayUsageCounter(response.data);
                        // Add link to view intro requests
                        self.addIntroRequestsLink(response.data);
                    }
                }
            });
        },

        /**
         * Add link to view introduction requests
         */
        addIntroRequestsLink: function(usage) {
            if (usage.current === 0) return; // No requests yet

            var $header = $('.sffc-crm-matches-header');
            if (!$header.length) return;

            // Update existing link if present
            var $existingLink = $('.sffc-crm-intro-requests-link');
            if ($existingLink.length) {
                $existingLink.find('a').html(
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                    '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"></path>' +
                    '<polyline points="9 5 9 3 15 3 15 5"></polyline>' +
                    '</svg>' +
                    'View My Expressed Interest (' + usage.current + ')'
                );
                return;
            }

            // Create new link
            var html = '<div class="sffc-crm-intro-requests-link">';
            html += '<a href="#" id="view-intro-requests">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"></path>';
            html += '<polyline points="9 5 9 3 15 3 15 5"></polyline>';
            html += '</svg>';
            html += 'View My Expressed Interest (' + usage.current + ')';
            html += '</a>';
            html += '</div>';

            $header.append(html);

            var self = this;
            $('#view-intro-requests').on('click', function(e) {
                e.preventDefault();
                self.showIntroRequestsModal();
            });
        },

        /**
         * Show introduction requests modal
         */
        showIntroRequestsModal: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_intro_requests',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderIntroRequestsModal(response.data.items);
                    } else {
                        self.showError('Failed to load Express Interest requests');
                    }
                },
                error: function() {
                    self.showError('Failed to load Express Interest requests');
                }
            });
        },

        /**
         * Render introduction requests modal
         */
        renderIntroRequestsModal: function(requests) {
            var html = '<div class="sffc-crm-intro-requests-modal">';

            // Header
            html += '<div class="sffc-crm-intro-requests-header">';
            html += '<h2>My Expressed Interest</h2>';
            html += '<button type="button" class="sffc-crm-modal-close" data-action="close">';
            html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<line x1="18" y1="6" x2="6" y2="18"></line>';
            html += '<line x1="6" y1="6" x2="18" y2="18"></line>';
            html += '</svg>';
            html += '</button>';
            html += '</div>';

            if (requests.length === 0) {
                html += '<div class="sffc-crm-empty">';
                html += '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5">';
                html += '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>';
                html += '<circle cx="9" cy="7" r="4"></circle>';
                html += '<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>';
                html += '<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>';
                html += '</svg>';
                html += '<p>No Express Interest submissions yet</p>';
                html += '</div>';
            } else {
                html += '<div class="sffc-crm-intro-requests-list">';

                var self = this;
                requests.forEach(function(request) {
                    var statusClass = request.status.replace(/_/g, '-');

                    html += '<div class="sffc-crm-intro-request-item">';

                    // Status badge at top
                    html += '<div class="sffc-crm-intro-request-status-bar">';
                    html += '<span class="sffc-crm-intro-status sffc-crm-intro-status--' + statusClass + '">';
                    html += self.escapeHtml(request.status_label);
                    html += '</span>';
                    html += '</div>';

                    // Main content
                    html += '<div class="sffc-crm-intro-request-content">';

                    // Recruiter section
                    html += '<div class="sffc-crm-intro-request-recruiter">';
                    html += '<div class="sffc-crm-intro-recruiter-avatar">';
                    html += '<div class="sffc-crm-avatar-placeholder">';
                    html += self.escapeHtml(request.recruiter_initials || 'UK');
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="sffc-crm-intro-recruiter-info">';
                    html += '<div class="sffc-crm-intro-recruiter-name">' + self.escapeHtml(request.recruiter_name || 'Unknown Recruiter') + '</div>';
                    if (request.recruiter_role || request.recruiter_firm) {
                        html += '<div class="sffc-crm-intro-recruiter-meta">';
                        if (request.recruiter_role) {
                            html += self.escapeHtml(request.recruiter_role);
                        }
                        if (request.recruiter_role && request.recruiter_firm) {
                            html += ' at ';
                        }
                        if (request.recruiter_firm) {
                            html += self.escapeHtml(request.recruiter_firm);
                        }
                        html += '</div>';
                    }
                    html += '</div>';
                    html += '</div>';

                    // Divider
                    html += '<div class="sffc-crm-intro-request-divider"></div>';

                    // Opportunity section
                    html += '<div class="sffc-crm-intro-request-opportunity">';
                    html += '<div class="sffc-crm-intro-opportunity-label">Opportunity</div>';
                    html += '<div class="sffc-crm-intro-opportunity-title">' + self.escapeHtml(request.job_title || 'Untitled Role') + '</div>';
                    html += '<div class="sffc-crm-intro-opportunity-company">';
                    html += self.escapeHtml(request.job_company || 'Unknown Company');
                    if (request.job_location) {
                        html += ' • ' + self.escapeHtml(request.job_location);
                    }
                    html += '</div>';
                    html += '</div>';

                    // Meta info
                    html += '<div class="sffc-crm-intro-request-meta">';
                    html += '<div class="sffc-crm-intro-request-score">';
                    html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                    html += '<polyline points="20 6 9 17 4 12"></polyline>';
                    html += '</svg>';
                    html += '<span>' + self.escapeHtml(request.compatibility_score || '0') + '% Match</span>';
                    html += '</div>';
                    html += '<div class="sffc-crm-intro-request-date">';
                    html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                    html += '<circle cx="12" cy="12" r="10"></circle>';
                    html += '<polyline points="12 6 12 12 16 14"></polyline>';
                    html += '</svg>';
                    html += '<span>' + self.escapeHtml(request.created_at_formatted || 'Unknown date') + '</span>';
                    html += '</div>';
                    html += '</div>';

                    html += '</div>'; // .sffc-crm-intro-request-content

                    // Recruiter response if present
                    if (request.recruiter_response) {
                        html += '<div class="sffc-crm-intro-request-response">';
                        html += '<div class="sffc-crm-intro-response-label">';
                        html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                        html += '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>';
                        html += '</svg>';
                        html += '<span>Response from Recruiter</span>';
                        html += '</div>';
                        html += '<div class="sffc-crm-intro-response-text">' + self.escapeHtml(request.recruiter_response) + '</div>';
                        html += '</div>';
                    }

                    html += '</div>'; // .sffc-crm-intro-request-item
                });

                html += '</div>'; // .sffc-crm-intro-requests-list
            }

            html += '</div>'; // .sffc-crm-intro-requests-modal

            this.showModal(html);
        },

        /**
         * Display usage counter in matches header
         */
        displayUsageCounter: function() {
            return;
        },

        updateUsageCounter: function() {
            return;
        },

        requestIntroduction: function(postId, recruiterId, recruiterName, matchScore, matchReasons, matchWarnings, jobData) {
            var self = this;

            if (!this.config.isLoggedIn) {
                this.showAuthModal();
                return;
            }
            if (!this.config.isPremium) {
                this.showMonetizationModal('intro', {
                    jobTitle: jobData && jobData.jobTitle ? jobData.jobTitle : '',
                    company: jobData && jobData.company ? jobData.company : '',
                    recruiterName: recruiterName || ''
                });
                return;
            }

            matchReasons = matchReasons || [];
            matchWarnings = matchWarnings || [];
            jobData = jobData || {};

            if (typeof matchScore === 'string') {
                matchScore = parseInt(matchScore, 10);
            }
            if (isNaN(matchScore)) {
                matchScore = 0;
            }

            var jobTitle = jobData.jobTitle || 'This Role';
            var company = jobData.company || '';
            var location = jobData.location || '';
            var recruiterPhoto = jobData.recruiterPhoto || '';
            var recruiterInitial = jobData.recruiterInitial || (recruiterName ? recruiterName.charAt(0).toUpperCase() : 'R');
            var keywordSuggestions = Array.isArray(jobData.keywords) ? jobData.keywords.slice(0, 8) : [];
            var recruiterLinkedIn = jobData.recruiterLinkedIn || jobData.recruiterLinkedin || jobData.linkedin || jobData.linkedin_url || (this.outreachState && this.outreachState.recruiterLinkedIn) || '';
            var recruiterFirstName = recruiterName ? recruiterName.split(' ')[0] : 'there';

            var matchQuality = matchScore >= 80 ? 'Excellent' : (matchScore >= 70 ? 'Strong' : 'Good');
            var matchColor = this.getMatchColor(matchScore);
            var clampedScore = Math.max(0, Math.min(100, matchScore));
            var circleRadius = 42;
            var circumference = 2 * Math.PI * circleRadius;
            var dashLength = (clampedScore / 100) * circumference;

            this.introComposerState = {
                recruiterName: recruiterName,
                recruiterFirstName: recruiterFirstName,
                jobTitle: jobTitle,
                company: company,
                location: location,
                postId: postId,
                recruiterId: recruiterId,
                matchScore: matchScore,
                matchReasons: matchReasons.slice(),
                matchWarnings: matchWarnings.slice(),
                keywords: keywordSuggestions.slice(0, 5),
                isGenerating: false
            };

            var introPreview = this.buildIntroMessage(this.introComposerState);

            var roleMeta = [];
            if (company) roleMeta.push(this.escapeHtml(company));
            if (location) roleMeta.push(this.escapeHtml(location));
            var roleMetaText = roleMeta.join(' • ');

            var scheduleOptions = [
                { value: 'asap', label: 'Send ASAP', description: 'Queue my introduction immediately after review.' },
                { value: 'weekday_morning', label: 'Weekday Morning', description: 'Deliver between 8-11am local time.' },
                { value: 'weekday_afternoon', label: 'Weekday Afternoon', description: 'Deliver between 12-4pm local time.' },
                { value: 'weekday_evening', label: 'Weekday Evening', description: 'Deliver between 6-9pm local time.' }
            ];

            var html = '<div class="sffc-crm-intro-request-modal">';

            html += '<div class="sffc-crm-intro-modal-header">';
            html += '<div class="sffc-crm-intro-match-section">';

            var showCalculateMatch = (matchScore === 0 && this.currentTab === 'all-roles');

            // When coming from All Roles with 0%, prompt the user to calculate their match
            if (showCalculateMatch) {
                html += '<div class="sffc-crm-calculate-match-wrapper">';
                html += '<div class="sffc-crm-intro-match-avatar">';
                if (recruiterPhoto) {
                    html += '<img src="' + this.escapeHtml(recruiterPhoto) + '" alt="' + this.escapeHtml(recruiterName || 'Recruiter') + '">';
                } else {
                    html += '<div class="sffc-crm-avatar-placeholder">' + this.escapeHtml(recruiterInitial) + '</div>';
                }
                html += '</div>';
                html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary" id="calculate-match-btn" data-post-id="' + postId + '">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">';
                html += '<circle cx="12" cy="12" r="10"></circle>';
                html += '<path d="M9 12l2 2 4-4"></path>';
                html += '</svg>';
                html += 'Calculate Match';
                html += '</button>';
                html += '<p class="sffc-crm-calculate-match-note">Upload your CV to see how well you match this role</p>';
                html += '</div>';
            } else {
                // Show regular match indicator with percentage
                html += '<div class="sffc-crm-intro-match-circle">';
                html += '<svg width="96" height="96" viewBox="0 0 96 96" class="sffc-crm-intro-match-ring">';
                html += '<circle cx="48" cy="48" r="' + circleRadius + '" fill="none" stroke="#e5e7eb" stroke-width="5"></circle>';
                html += '<circle cx="48" cy="48" r="' + circleRadius + '" fill="none" stroke="' + matchColor + '" stroke-width="5" stroke-linecap="round" transform="rotate(-90 48 48)" stroke-dasharray="' + dashLength.toFixed(2) + ' ' + circumference.toFixed(2) + '"></circle>';
                html += '</svg>';
                html += '<div class="sffc-crm-intro-match-avatar">';
                if (recruiterPhoto) {
                    html += '<img src="' + this.escapeHtml(recruiterPhoto) + '" alt="' + this.escapeHtml(recruiterName || 'Recruiter') + '">';
                } else {
                    html += '<div class="sffc-crm-avatar-placeholder">' + this.escapeHtml(recruiterInitial) + '</div>';
                }
                html += '</div>';
                html += '<div class="sffc-crm-intro-match-score" style="color: ' + matchColor + ';">' + matchScore + '%</div>';
                html += '</div>';
                html += '<div class="sffc-crm-intro-match-meta">';
                html += '<span>' + matchQuality + ' match</span>';
                html += '<strong>' + matchScore + '% fit</strong>';
                html += '</div>';
            }
            html += '</div>';

            html += '<div class="sffc-crm-intro-recruiter-info">';
            html += '<p class="sffc-crm-intro-eyebrow">MENA Careers concierge Express Interest</p>';
            html += '<h3>' + this.escapeHtml(jobTitle) + '</h3>';
            if (roleMetaText) {
                html += '<p class="sffc-crm-intro-role-meta">' + roleMetaText + '</p>';
            }
            html += '<div class="sffc-crm-intro-contact-line">We\'ll reach out to ' + this.escapeHtml(recruiterName || 'the recruiter') + '</div>';
            html += '</div>';
            html += '</div>';

            var insightsHtmlParts = [];
            if (matchReasons.length > 0) {
                var strengthsHtml = '';
                strengthsHtml += '<div class="sffc-crm-intro-strengths-section">';
                strengthsHtml += '<h5>';
                strengthsHtml += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                strengthsHtml += '<polyline points="20 6 9 17 4 12"></polyline>';
                strengthsHtml += '</svg>';
                strengthsHtml += 'Why this role is a great match';
                strengthsHtml += '</h5>';
                strengthsHtml += '<ul class="sffc-crm-intro-strengths-list">';
                matchReasons.slice(0, 3).forEach(function(reason) {
                    strengthsHtml += '<li>';
                    strengthsHtml += '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">';
                    strengthsHtml += '<polyline points="20 6 9 17 4 12"></polyline>';
                    strengthsHtml += '</svg>';
                    strengthsHtml += '<span>' + self.escapeHtml(reason) + '</span>';
                    strengthsHtml += '</li>';
                });
                strengthsHtml += '</ul>';
                strengthsHtml += '</div>';
                insightsHtmlParts.push(strengthsHtml);
            }

            if (matchWarnings.length > 0) {
                var warningsHtml = '';
                warningsHtml += '<div class="sffc-crm-intro-strengths-section">';
                warningsHtml += '<h5>';
                warningsHtml += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                warningsHtml += '<path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>';
                warningsHtml += '</svg>';
                warningsHtml += 'Where we\'ll guide the recruiter';
                warningsHtml += '</h5>';
                warningsHtml += '<ul class="sffc-crm-intro-strengths-list sffc-crm-intro-strengths-list--warnings">';
                matchWarnings.slice(0, 3).forEach(function(warning) {
                    warningsHtml += '<li>';
                    warningsHtml += '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                    warningsHtml += '<path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>';
                    warningsHtml += '</svg>';
                    warningsHtml += '<span>' + self.escapeHtml(warning) + '</span>';
                    warningsHtml += '</li>';
                });
                warningsHtml += '</ul>';
                warningsHtml += '</div>';
                insightsHtmlParts.push(warningsHtml);
            }

            if (!insightsHtmlParts.length) {
                insightsHtmlParts.push('<div class="sffc-crm-intro-info-card"><h5>We\'ll highlight your best angles</h5><p>Upload your CV or calculate your match to unlock MENA Careers\'s tailored talking points.</p></div>');
            }

            var composeHtml = '';
            composeHtml += '<div class="sffc-crm-intro-preferences">';
            composeHtml += '<div class="sffc-crm-compose-section sffc-crm-intro-compose">';
            composeHtml += '<div class="sffc-crm-intro-compose-header">';
            composeHtml += '<div class="sffc-crm-intro-pill">MENA Careers AI</div>';
            composeHtml += '<button type="button" class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" id="intro-regenerate-btn">';
            composeHtml += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 13a9 9 0 1 1-3-7.7"></path><path d="M21 3v6h-6"></path></svg>';
            composeHtml += 'Regenerate with AI';
            composeHtml += '</button>';
            composeHtml += '</div>';
            composeHtml += '<textarea id="intro-ai-message" rows="5" class="sffc-crm-textarea" placeholder="MENA Careers AI will write a compelling LinkedIn InMail for you."></textarea>';
            composeHtml += '<p class="sffc-crm-intro-note-hint">Tap a keyword below to insert it instantly.</p>';
            composeHtml += '</div>';

            composeHtml += '<div class="sffc-crm-intro-preference-grid">';
            composeHtml += '<div class="sffc-crm-compose-section sffc-crm-intro-cv-section">';
            composeHtml += '<div class="sffc-crm-intro-section-heading">';
            composeHtml += '<label>Select CV to include</label>';
            composeHtml += '<button type="button" class="sffc-crm-btn sffc-crm-btn-text sffc-crm-btn-small" id="manage-cv-library">Manage CVs</button>';
            composeHtml += '</div>';
            composeHtml += '<div class="sffc-crm-intro-cv-options" id="intro-cv-options">';
            composeHtml += '<div class="sffc-crm-inline-loading">Loading CVs...</div>';
            composeHtml += '</div>';
            composeHtml += '</div>';

            composeHtml += '<div class="sffc-crm-compose-section sffc-crm-intro-keyword-section">';
            composeHtml += '<div class="sffc-crm-intro-section-heading">';
            composeHtml += '<label>Smart keywords from the job description</label>';
            composeHtml += '</div>';
            composeHtml += '<div class="sffc-crm-intro-keyword-chips" id="intro-keyword-chips">';
            composeHtml += self.renderIntroKeywordChips(keywordSuggestions);
            composeHtml += '</div>';
            composeHtml += '</div>';

            composeHtml += '<div class="sffc-crm-compose-section sffc-crm-intro-schedule-section">';
            composeHtml += '<label>Schedule the outreach</label>';
            composeHtml += '<div class="sffc-crm-intro-schedule-options">';
            scheduleOptions.forEach(function(option, index) {
                composeHtml += '<label class="sffc-crm-intro-schedule-option">';
                composeHtml += '<input type="radio" name="intro-send-window" value="' + option.value + '" ' + (index === 0 ? 'checked' : '') + '>';
                composeHtml += '<div class="sffc-crm-intro-schedule-label">';
                composeHtml += '<strong>' + option.label + '</strong>';
                composeHtml += '<span>' + option.description + '</span>';
                composeHtml += '</div>';
                composeHtml += '</label>';
            });
            composeHtml += '</div>';
            composeHtml += '</div>';
            composeHtml += '</div>';
            composeHtml += '</div>';

            if (recruiterLinkedIn) {
                composeHtml += '<div class="sffc-crm-intro-linkedin-card">';
                composeHtml += '<div class="sffc-crm-intro-linkedin-heading">Send it via LinkedIn</div>';
                composeHtml += '<p>Copy the MENA Careers AI note above, then open LinkedIn to send ' + this.escapeHtml(recruiterFirstName) + ' an InMail.</p>';
                composeHtml += '<a href="' + this.escapeHtml(recruiterLinkedIn) + '" target="_blank" rel="noopener noreferrer" class="sffc-crm-intro-linkedin-btn">';
                composeHtml += '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>';
                composeHtml += '<span>Open LinkedIn Profile</span>';
                composeHtml += '</a>';
                composeHtml += '<small>Opens in a new tab so you can paste the AI-generated note.</small>';
                composeHtml += '</div>';
            }

            composeHtml += '<div class="sffc-crm-intro-note-section">';
            composeHtml += '<label for="intro-request-note">Add extra context for the MENA Careers team (optional)</label>';
            composeHtml += '<textarea id="intro-request-note" rows="3" placeholder="Anything else we should mention? Projects, timing, etc."></textarea>';
            composeHtml += '</div>';

            html += '<div class="sffc-crm-intro-body-grid">';
            html += '<div class="sffc-crm-intro-body-col sffc-crm-intro-body-col--intel">' + insightsHtmlParts.join('') + '</div>';
            html += '<div class="sffc-crm-intro-body-col sffc-crm-intro-body-col--compose">' + composeHtml + '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-intro-request-actions">';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary" data-action="close">Cancel</button>';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary" id="confirm-intro-request">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">';
            html += '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>';
            html += '<circle cx="9" cy="7" r="4"></circle>';
            html += '<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>';
            html += '<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>';
            html += '</svg>';
            html += 'Express Interest';
            html += '</button>';
            html += '</div>';

            html += '</div>';

            this.showModal(html);

            var $messageField = $('#intro-ai-message');
            if ($messageField.length) {
                $messageField.val(introPreview);
            }

            this.bindIntroPreferenceEvents();
            this.renderIntroCvOptions('#intro-cv-options');

            if (this.config.features && this.config.features.ai_personalization) {
                this.generateIntroAIMessage(true);
            }

            $('#confirm-intro-request').on('click', function() {
                var payload = {
                    note: $('#intro-request-note').val().trim(),
                    cvId: $('input[name="intro-cv-option"]:checked').val() || '',
                    introMessage: $('#intro-ai-message').val().trim(),
                    scheduleOption: $('input[name="intro-send-window"]:checked').val() || 'asap',
                    keywords: $('.sffc-crm-intro-keyword-chip.is-selected').map(function() {
                        return $(this).data('keyword');
                    }).get().filter(function(item) { return item && item.length; })
                };
                self.submitIntroductionRequest(postId, recruiterId, payload, matchScore);
            });

            // Calculate Match button handler
            $('#calculate-match-btn').on('click', function() {
                self.closeModal();
                // Switch to Matches tab to trigger CV upload
                $('.sffc-crm-tab[data-tab="matches"]').click();
            });
        },

        /**
         * Show monetization modal with personalized preview
         */
        showMonetizationModal: function(type, jobData) {
            var self = this;
            jobData = jobData || {};

            var jobTitle = jobData.jobTitle || 'this role';
            var company = jobData.company || '';
            var recruiterName = jobData.recruiterName || 'the recruiter';
            var recruiterFirstName = recruiterName ? recruiterName.split(' ')[0] : 'there';

            // Build preview message based on type
            var previewMessage = '';
            var modalTitle = '';
            var modalDescription = '';
            var previewLabel = 'Personalized Preview';
            var previewNote = 'MENA Careers AI · Based on your profile';
            var overlayNote = 'Preview locked · Upgrade to view the full AI message';
            var previewButtonLabel = 'Subscribe';
            var ctaPrimaryLabel = 'Subscribe to Continue';
            var planHeaderTitle = 'Unlock MENA Careers AI outreach';
            var planHeaderDescription = 'Membership unlocks Express Interest submissions, AI applications, and CRM automations.';
            var featureList = [
                { title: 'AI-Powered Outreach', description: 'Highly convincing, personalized messages' },
                { title: 'Unlimited Applications', description: 'Apply to as many roles as you want' },
                { title: 'Match Analysis', description: 'See your fit score for every role' },
                { title: 'Application Toolkit', description: 'Cover letters, interview prep & more' }
            ];
            var previewInnerContent = '';

            if (type === 'linkedin') {
                modalTitle = 'AI LinkedIn Outreach';
                modalDescription = 'See how MENA Careers AI crafts highly convincing LinkedIn InMail messages that get responses.';

                // Generate sample LinkedIn message
                previewMessage = 'Hi ' + recruiterFirstName + ',\n\n';
                previewMessage += 'I came across the ' + jobTitle;
                if (company) previewMessage += ' at ' + company;
                previewMessage += ' and was immediately drawn to it. My background in [your key skills] aligns closely with what you\'re looking for.\n\n';
                previewMessage += 'I bring proven expertise in [specific areas from job description], with a track record of [relevant achievements]. ';
                previewMessage += 'I\'m particularly excited about [specific aspect of the role].\n\n';
                previewMessage += 'I\'d welcome the opportunity to discuss how my experience could add value to your team. Would you be open to a brief conversation?\n\n';
                previewMessage += 'Best regards';
            } else if (type === 'email') {
                modalTitle = 'Smart Email Application';
                modalDescription = 'See how MENA Careers AI creates personalized, professional email applications that stand out.';

                // Generate sample email
                previewMessage = 'Subject: Application for ' + jobTitle;
                if (company) previewMessage += ' at ' + company;
                previewMessage += '\n\n';
                previewMessage += 'Dear ' + recruiterFirstName + ',\n\n';
                previewMessage += 'I am writing to express my strong interest in the ' + jobTitle + ' position';
                if (company) previewMessage += ' at ' + company;
                previewMessage += '.\n\n';
                previewMessage += 'With [X years] of experience in [relevant field], I have developed deep expertise in [key skills mentioned in job description]. ';
                previewMessage += 'My background includes [specific achievements and credentials].\n\n';
                previewMessage += 'I am particularly drawn to this opportunity because [specific reasons that show you researched the role]. ';
                previewMessage += 'I believe my experience in [relevant areas] would enable me to [specific contribution].\n\n';
                previewMessage += 'I have attached my CV for your review and would welcome the opportunity to discuss how I can contribute to your team.\n\n';
                previewMessage += 'Thank you for your consideration.\n\n';
                previewMessage += 'Best regards,\nCandidate';
            } else if (type === 'intro') {
                modalTitle = 'Express Interest';
                modalDescription = 'Upgrade to have MENA Careers concierge express your interest with recruiters and log every reply.';
                previewLabel = 'Express Interest Preview';
                overlayNote = 'Express Interest locked · Upgrade to unlock concierge outreach and live CRM tracking';
                planHeaderTitle = 'Unlock Express Interest';
                featureList = [
                    { title: 'Concierge-drafted outreach', description: 'MENA Careers writes, personalizes, and sends the express-interest note for you' },
                    { title: 'Live status tracking', description: 'Watch recruiter opens, replies, and nudges from the CRM' },
                    { title: 'Premium recruiter access', description: 'Unlock curated contacts and partner-managed conversations' }
                ];
                previewMessage = 'Express Interest Status: Pending review\n';
                previewMessage += 'Recruiter: ' + recruiterName + '\n';
                previewMessage += 'Role: ' + jobTitle;
                if (company) previewMessage += ' at ' + company;
                previewMessage += '\n\nConcierge talking points:\n';
                previewMessage += '- Quantified impact from your recent deal\n';
                previewMessage += '- Relevant sector experience the recruiter cares about\n';
                previewMessage += '- Suggested follow-up window managed by MENA Careers\n';
            } else if (type === 'matches') {
                modalTitle = 'AI Match Insights';
                modalDescription = 'Preview how MENA Careers scores each role, surfaces strengths, and guides outreach priorities.';
                previewMessage = 'Match Score: 92% - Strategic Value Creation\n\n';
                previewMessage += 'Key Reasons:\n';
                previewMessage += '- Led 6 PE portfolio value creation programs\n';
                previewMessage += '- Advanced modeling depth with market mapping\n';
                previewMessage += '- Stakeholder coaching + deal execution support\n\n';
                previewMessage += 'Action Plan:\n';
                previewMessage += '- Prep recruiter outreach to 2 partner contacts\n';
                previewMessage += '- Fill skill gap: Advanced LBO narrative refresh\n';
                previewLabel = 'Match Insight Preview';
                previewNote = 'MENA Careers AI · Based on your CV & goals';
                overlayNote = 'Match insights locked · Upgrade to reveal scores, gap analysis, and recruiter actions';
                previewButtonLabel = 'Unlock Insights';
                ctaPrimaryLabel = 'Unlock Match Insights';
                planHeaderTitle = 'Unlock AI Match Insights';
                planHeaderDescription = 'Membership unlocks match scoring, Express Interest submissions, and CRM automations.';

                var previewMatchScore = 98;
                var previewMatchColor = this.getMatchColor(previewMatchScore);
                var previewCircleRadius = 35;
                var previewCircleDash = (previewMatchScore * 2.199).toFixed(2);
                var previewAngle = (previewMatchScore / 100) * 360 - 90;
                var previewRadians = previewAngle * (Math.PI / 180);
                var previewScoreX = (40 + previewCircleRadius * Math.cos(previewRadians)).toFixed(2);
                var previewScoreY = (40 + previewCircleRadius * Math.sin(previewRadians)).toFixed(2);
                var matchPreviewImage = 'https://media.joinsenna.com/2026/02/SophieMayRec.png?1771245927';
                var matchPreviewReasons = [
                    'PE portfolio acceleration lead with 7 value-creation sprints delivered',
                    'Ex-McKinsey operator covering diligence, GTM, and digital playbooks',
                    'Warm MENA Careers concierge intro path to partners running this mandate'
                ];

                previewInnerContent = '';
                previewInnerContent += '<div class="sffc-crm-match-row sffc-crm-match-row--preview" data-match-score="' + previewMatchScore + '">';
                previewInnerContent += '<div class="sffc-crm-match-indicator">';
                previewInnerContent += '<div class="sffc-crm-match-circle-container">';
                previewInnerContent += '<svg class="sffc-crm-match-circle" width="80" height="80" viewBox="0 0 80 80">';
                previewInnerContent += '<circle class="sffc-crm-match-circle-bg" cx="40" cy="40" r="35" fill="none" stroke="#e5e7eb" stroke-width="5"></circle>';
                previewInnerContent += '<circle class="sffc-crm-match-circle-fg" cx="40" cy="40" r="35" fill="none" stroke="' + previewMatchColor + '" stroke-width="5" stroke-dasharray="' + previewCircleDash + ' 219.91" stroke-linecap="round" transform="rotate(-90 40 40)"></circle>';
                previewInnerContent += '</svg>';
                previewInnerContent += '<div class="sffc-crm-match-avatar">';
                previewInnerContent += '<img src="' + self.escapeHtml(matchPreviewImage) + '" alt="Sophie May" data-initial="S">';
                previewInnerContent += '</div>';
                previewInnerContent += '<div class="sffc-crm-match-score" style="left: ' + previewScoreX + 'px; top: ' + previewScoreY + 'px; color: ' + previewMatchColor + '; border-color: ' + previewMatchColor + ';">' + previewMatchScore + '%</div>';
                previewInnerContent += '</div>';
                previewInnerContent += '<div class="sffc-crm-match-recruiter-name">Sophie M.</div>';
                previewInnerContent += '</div>';
                previewInnerContent += '<div class="sffc-crm-match-content">';
                previewInnerContent += '<div class="sffc-crm-match-header">';
                previewInnerContent += '<h4 class="sffc-crm-match-title">Operating Partner · Portfolio Acceleration</h4>';
                previewInnerContent += '<div class="sffc-crm-match-meta">Northwind Equity • Private Equity</div>';
                previewInnerContent += '</div>';
                previewInnerContent += '<ul class="sffc-crm-match-reasons">';
                matchPreviewReasons.forEach(function(reason) {
                    previewInnerContent += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>' + self.escapeHtml(reason) + '</span></li>';
                });
                previewInnerContent += '</ul>';
                previewInnerContent += '<p class="sffc-crm-match-commentary">Upload your CV to unlock full match breakdowns, recruiter readiness cues, and MENA Careers-intro action steps.</p>';
                previewInnerContent += '</div>';
                previewInnerContent += '</div>';
            } else if (type === 'smart_apply') {
                modalTitle = 'Smart message Automations';
                modalDescription = 'Preview how MENA Careers tailors your materials and files 50+ high-match applications in one click.';
                previewLabel = 'Smart message Preview';
                overlayNote = 'Smart message locked · Upgrade to run concierge applications and dashboard tracking';
                planHeaderTitle = 'Unlock Smart message';
                featureList = [
                    { title: 'High-match shortlist', description: 'Concierge scans 100+ roles and prioritizes the best 50+' },
                    { title: 'Tailored CV & cover letters', description: 'Every submission includes a refreshed CV plus intro note' },
                    { title: 'Live dashboard', description: 'Watch every Smart message submission update inside MENA Careers CRM' }
                ];
                previewInnerContent = '';
                previewInnerContent += '<div class="sffc-crm-matches-list">';
                previewInnerContent += '<div class="sffc-crm-match-row sffc-crm-match-row--preview" data-static-row="true">';
                previewInnerContent += '<div class="sffc-crm-match-content">';
                previewInnerContent += '<div class="sffc-crm-match-header">';
                previewInnerContent += '<h4 class="sffc-crm-match-title">Smart message Queue</h4>';
                previewInnerContent += '<div class="sffc-crm-match-meta">52 mandates • Tailored CV + cover letter • Live tracking</div>';
                previewInnerContent += '</div>';
                previewInnerContent += '<ul class="sffc-crm-match-reasons">';
                ['Drafting concierge CV updates','Auto-logging submissions to CRM','Scheduling recruiter follow-ups in 48h'].forEach(function(reason) {
                    previewInnerContent += '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>' + self.escapeHtml(reason) + '</span></li>';
                });
                previewInnerContent += '</ul>';
                previewInnerContent += '<p class="sffc-crm-match-commentary">Upgrade to let MENA Careers shortlist roles, tailor materials, and file applications while you watch progress live.</p>';
                previewInnerContent += '</div>';
                previewInnerContent += '</div>';
                previewInnerContent += '</div>';
            } else if (type === 'recruiters') {
                modalTitle = 'Recruiter Contact Network';
                modalDescription = 'Preview how MENA Careers unlocks verified recruiter contact details, engagement history, and warm intro workflows.';
                previewLabel = 'Recruiter Access Preview';
                previewNote = 'MENA Careers CRM · Curated recruiter feed';
                overlayNote = 'Recruiter contacts locked · Upgrade to view emails, notes, and status history';
                previewButtonLabel = 'Unlock Contacts';
                ctaPrimaryLabel = 'Unlock Recruiter Contacts';
                planHeaderTitle = 'Unlock recruiter contacts';
                planHeaderDescription = 'Membership reveals recruiter emails, intro workflows, and CRM automations.';
                featureList = [
                    { title: 'Verified Contact Details', description: 'Unlock recruiter emails, LinkedIn, and firm intel' },
                    { title: 'Warm Interest Scripts', description: 'Concierge-crafted outreach prompts for every contact' },
                    { title: 'Engagement Tracking', description: 'Follow replies, nudges, and MENA Careers concierge signals' },
                    { title: 'CRM Automations', description: 'Automate follow-ups, reminders, and recruiter notes' }
                ];

                var recruiterPreview = [
                    { initials: 'AS', name: 'Ava Sterling', firm: 'Northwind Equity', tags: ['PE', 'Portfolio Ops'], status: 'Replied 2 days ago', email: 'ava@northwind.com' },
                    { initials: 'MH', name: 'Miguel Hart', firm: 'Redwood Capital', tags: ['Growth', 'NYC'], status: 'Needs follow-up', email: 'miguel@redwoodcap.com' },
                    { initials: 'LO', name: 'Leila Osei', firm: 'Atlas Partners', tags: ['Buyout', 'EMEA'], status: 'Interest ready', email: 'leila@atlaspartners.com' }
                ];

                previewInnerContent = '<div class="sffc-crm-recruiter-preview">';
                recruiterPreview.forEach(function(contact) {
                    var emailParts = contact.email.split('@');
                    var obfuscated = '••••@' + (emailParts[1] || 'firm.com');
                    previewInnerContent += '<div class="sffc-crm-recruiter-row sffc-crm-recruiter-row--preview">';
                    previewInnerContent += '<div class="sffc-crm-recruiter-avatar"><div class="sffc-crm-avatar-placeholder">' + self.escapeHtml(contact.initials) + '</div></div>';
                    previewInnerContent += '<div class="sffc-crm-recruiter-details">';
                    previewInnerContent += '<span class="sffc-crm-recruiter-name">' + self.escapeHtml(contact.name) + '</span>';
                    previewInnerContent += '<span class="sffc-crm-recruiter-firm">' + self.escapeHtml(contact.firm) + '</span>';
                    if (Array.isArray(contact.tags) && contact.tags.length) {
                        previewInnerContent += '<div class="sffc-crm-recruiter-tags">';
                        contact.tags.forEach(function(tag) {
                            previewInnerContent += '<span class="sffc-crm-tag">' + self.escapeHtml(tag) + '</span>';
                        });
                        previewInnerContent += '</div>';
                    }
                    previewInnerContent += '<div class="sffc-crm-recruiter-email">' + self.escapeHtml(obfuscated) + '</div>';
                    previewInnerContent += '</div>';
                    previewInnerContent += '<div class="sffc-crm-recruiter-last">' + self.escapeHtml(contact.status) + '</div>';
                    previewInnerContent += '</div>';
                });
                previewInnerContent += '<p class="sffc-crm-match-commentary">Upgrade to unlock recruiter emails, conversation history, and concierge-backed intros.</p>';
                previewInnerContent += '</div>';
            }

            var plans = this.getPlanOptions();
            if (!previewInnerContent) {
                previewInnerContent = '<pre>' + this.escapeHtml(previewMessage) + '</pre>';
            }

            var html = '<div class="sffc-crm-monetization-modal">';

            // Header
            html += '<div class="sffc-crm-monetization-header">';
            html += '<div class="sffc-crm-monetization-icon">';
            html += '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            if (type === 'linkedin') {
                html += '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>';
                html += '<rect x="2" y="9" width="4" height="12"></rect>';
                html += '<circle cx="4" cy="4" r="2"></circle>';
            } else if (type === 'matches') {
                html += '<path d="M4 17l4-5 3 4 5-8 4 6"></path>';
                html += '<circle cx="4" cy="17" r="1"></circle>';
                html += '<circle cx="8" cy="12" r="1"></circle>';
                html += '<circle cx="11" cy="16" r="1"></circle>';
                html += '<circle cx="16" cy="8" r="1"></circle>';
                html += '<circle cx="20" cy="14" r="1"></circle>';
            } else {
                html += '<rect x="3" y="5" width="18" height="14" rx="2"></rect>';
                html += '<path d="m3 7 9 6 9-6"></path>';
            }
            html += '</svg>';
            html += '</div>';
            html += '<h3>' + this.escapeHtml(modalTitle) + '</h3>';
            html += '<p class="sffc-crm-monetization-description">' + this.escapeHtml(modalDescription) + '</p>';
            html += '</div>';

            // Preview section with blur
            html += '<div class="sffc-crm-monetization-preview">';
            html += '<div class="sffc-crm-preview-label">';
            html += '<span class="sffc-crm-preview-pill">' + this.escapeHtml(previewLabel) + '</span>';
            html += '<span class="sffc-crm-preview-note">' + this.escapeHtml(previewNote) + '</span>';
            html += '</div>';
            html += '<div class="sffc-crm-preview-content is-blurred">';
            html += previewInnerContent;
            html += '</div>';
            html += '<div class="sffc-crm-preview-overlay">';
            html += '<div class="sffc-crm-preview-lock">';
            html += '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>';
            html += '<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>';
            html += '</svg>';
            html += '</div>';
            html += '<p class="sffc-crm-preview-overlay-note">' + this.escapeHtml(overlayNote) + '</p>';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-preview-subscribe">' + this.escapeHtml(previewButtonLabel) + '</button>';
            html += '</div>';
            html += '</div>';

            if (plans && plans.length) {
                html += '<div class="sffc-crm-monetization-plans">';
                html += '<div class="sffc-crm-monetization-plans-header">';
                html += '<h4>' + this.escapeHtml(planHeaderTitle) + '</h4>';
                html += '<p>' + this.escapeHtml(planHeaderDescription) + '</p>';
                html += '</div>';
                html += '<div class="sffc-crm-monetization-plan-grid">';
                plans.slice(0, 3).forEach(function(plan) {
                    html += '<div class="sffc-crm-monetization-plan" data-plan-slug="' + self.escapeHtml(plan.slug || '') + '">';
                    html += '<div class="sffc-crm-monetization-plan-head">';
                    html += '<span class="sffc-crm-monetization-plan-name">' + self.escapeHtml(plan.name || 'Membership') + '</span>';
                    if (plan.tagline) {
                        html += '<p class="sffc-crm-monetization-plan-tagline">' + self.escapeHtml(plan.tagline) + '</p>';
                    }
                    if (plan.audience) {
                        html += '<span class="sffc-crm-monetization-plan-audience">' + self.escapeHtml(plan.audience) + '</span>';
                    }
                    html += '</div>';
                    if (plan.price) {
                        html += '<div class="sffc-crm-monetization-plan-price">' + self.escapeHtml(plan.price);
                        if (plan.cycle) {
                            html += '<span>' + self.escapeHtml(plan.cycle) + '</span>';
                        }
                        html += '</div>';
                    }
                    html += '<button type="button" class="sffc-crm-btn sffc-crm-monetization-plan-cta" data-plan-slug="' + self.escapeHtml(plan.slug || '') + '">Join ' + self.escapeHtml(plan.name || 'Plan') + '</button>';
                    html += '</div>';
                });
                html += '</div>';
                html += '</div>';
            }

            // Features list
            html += '<div class="sffc-crm-monetization-features">';
            html += '<h4>What you get with MENA Careers Premium:</h4>';
            html += '<ul>';
            featureList.forEach(function(feature) {
                html += '<li>';
                html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                html += '<polyline points="20 6 9 17 4 12"></polyline>';
                html += '</svg>';
                html += '<span><strong>' + self.escapeHtml(feature.title) + '</strong> - ' + self.escapeHtml(feature.description) + '</span>';
                html += '</li>';
            });
            html += '</ul>';
            html += '</div>';

            // CTA buttons
            html += '<div class="sffc-crm-monetization-actions">';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-btn-large" id="subscribe-continue-btn">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">';
            html += '<circle cx="12" cy="12" r="10"></circle>';
            html += '<polyline points="12 6 12 12 16 14"></polyline>';
            html += '</svg>';
            html += this.escapeHtml(ctaPrimaryLabel);
            html += '</button>';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary" data-action="close">Maybe Later</button>';
            html += '</div>';

            html += '</div>';

            this.showModal(html, 'sffc-crm-modal-content--monetization');

            // Bind events
            $('#subscribe-continue-btn').on('click', function() {
                self.closeModal();
                if (!self.triggerPlanModal({ skipFallback: true })) {
                    window.location.href = self.config.membershipUrl || '/account/membership/';
                }
            });

            $('.sffc-crm-monetization-plan-cta').on('click', function() {
                var slug = $(this).data('planSlug');
                self.closeModal();
                self.handleMonetizationPlanClick(slug);
            });

            $('.sffc-crm-preview-subscribe').on('click', function() {
                var $planSection = $('.sffc-crm-monetization-plans');
                var $container = $('.sffc-crm-modal-content--monetization');
                if ($planSection.length && $container.length) {
                    var target = $planSection.offset().top - $container.offset().top + $container.scrollTop();
                    $container.animate({ scrollTop: target }, 300, function() {
                        $planSection.addClass('is-highlighted');
                        setTimeout(function() {
                            $planSection.removeClass('is-highlighted');
                        }, 1200);
                    });
                } else {
                    $('#subscribe-continue-btn').trigger('click');
                }
            });
        },

        getPlanOptions: function() {
            if (this.planOptionsCache) {
                return this.planOptionsCache.slice();
            }

            var plans = [];
            if (this.$planModal && this.$planModal.length) {
                this.$planModal.find('[data-plan-select]').each(function() {
                    var $btn = $(this);
                    plans.push({
                        slug: $btn.data('planSlug') || '',
                        name: $btn.data('planName') || '',
                        price: $btn.data('planPrice') || '',
                        cycle: $btn.data('planCycle') || '',
                        tagline: $btn.data('planTagline') || '',
                        audience: $btn.data('planAudience') || '',
                        url: $btn.data('planUrl') || '',
                        hasShortcode: !!$btn.data('planShortcode')
                    });
                });
            }

            this.planOptionsCache = plans;
            return plans.slice();
        },

        handleMonetizationPlanClick: function(slug) {
            var plans = this.getPlanOptions();
            var plan = plans.find(function(entry) {
                return entry.slug === slug;
            });

            if (plan && this.triggerPlanModal({ skipFallback: true })) {
                var $target = this.$planModal.find('[data-plan-select][data-plan-slug="' + plan.slug + '"]');
                if ($target.length) {
                    $target.trigger('click');
                    return;
                }
            }

            var fallbackUrl = (plan && plan.url) ? plan.url : (this.config.membershipUrl || 'https://joinsenna.com/memberships/');
            if (fallbackUrl) {
                window.open(fallbackUrl, '_blank', 'noopener');
            }
        },

        renderIntroKeywordChips: function(keywords) {
            var self = this;
            if (!keywords || !keywords.length) {
                return '<p class="sffc-crm-intro-keyword-empty">Upload a CV + job description to unlock auto keyword prompts.</p>';
            }
            return keywords.map(function(keyword) {
                return '<button type="button" class="sffc-crm-intro-keyword-chip" data-keyword="' + self.escapeHtml(keyword) + '">' + self.escapeHtml(keyword) + '</button>';
            }).join('');
        },

        buildIntroMessage: function(context) {
            context = context || {};
            var templates = (Array.isArray(this.introMessageTemplates) && this.introMessageTemplates.length)
                ? this.introMessageTemplates
                : this.getFallbackIntroTemplates();

            var firstName = context.recruiterFirstName || 'there';
            var role = context.jobTitle || 'this opportunity';
            var companyClause = context.company ? ' at ' + context.company : '';
            var reasonList = Array.isArray(context.matchReasons) ? context.matchReasons.filter(Boolean).slice(0, 3) : [];
            var keywordList = Array.isArray(context.keywords) ? context.keywords.filter(Boolean).slice(0, 4) : [];
            var keywordSignals = keywordList.slice();
            if (reasonList && reasonList.length) {
                keywordSignals = keywordSignals.concat(reasonList);
            }

            var matchSentence = 'My background aligns well with the role requirements.';
            if (keywordList.length >= 2) {
                matchSentence = 'My experience in ' + this.formatList(keywordList.slice(0, 2)) + ' aligns closely with what you\'re looking for.';
            } else if (keywordList.length === 1) {
                matchSentence = 'My background in ' + keywordList[0] + ' is a strong match for this position.';
            }

            var templateObj = this.selectIntroTemplate(templates, keywordSignals);
            var template = (templateObj && templateObj.text) ? templateObj.text : templates[Math.floor(Math.random() * templates.length)].text;
            var opener = template
                .replace(/\{recruiterFirst\}/g, firstName)
                .replace(/\{jobTitle\}/g, role)
                .replace(/\{companyClause\}/g, companyClause)
                .replace(/\{matchSentence\}/g, matchSentence);

            var openerDetails = this.buildAlignmentLine(role, context.company || '', reasonList, context.matchScore);
            var introLines = [openerDetails ? (opener + ' ' + openerDetails).trim() : opener];

            var strengthParagraph = this.buildStrengthParagraph(keywordList);
            if (strengthParagraph) {
                introLines.push(strengthParagraph);
            }

            var contributionBullets = this.buildContributionBullets(reasonList, keywordList);
            if (contributionBullets.length) {
                introLines.push('Here are a few areas where I can contribute:\n' + contributionBullets.map(function(item) { return '• ' + item; }).join('\n'));
            }

            introLines.push('If helpful, I’d love to connect briefly and introduce myself properly.');
            introLines.push('Best regards');

            return introLines.join('\n\n').replace(/\n{3,}/g, '\n\n').trim();
        },

        formatList: function(items) {
            if (!items || !items.length) return '';
            if (items.length === 1) return items[0];
            if (items.length === 2) return items[0] + ' and ' + items[1];
            return items.slice(0, -1).join(', ') + ', and ' + items[items.length - 1];
        },

        buildAlignmentLine: function(role, company, reasons, matchScore) {
            var phrases = [];
            if (reasons && reasons.length) {
                phrases.push('What stood out to me was ' + this.formatList(reasons) + '.');
            } else if (matchScore && matchScore >= 60) {
                phrases.push('Given the ~' + matchScore + '% overlap with my background, I thought it made sense to reach out directly.');
            }
            if (company) {
                phrases.push('I’ve been tracking ' + company + ' and appreciate how the team structures mandates like this.');
            }
            return phrases.join(' ').trim();
        },

        buildStrengthParagraph: function(keywords) {
            if (!keywords || !keywords.length) return '';
            return 'In my current role I’ve been focused on ' + this.formatList(keywords) + ', pairing strong analytics with concise communication for senior stakeholders.';
        },

        buildContributionBullets: function(reasons, keywords) {
            var bullets = [];
            var seen = new Set();

            (reasons || []).forEach(function(reason) {
                var cleaned = reason ? reason.replace(/^[-•\s]+/, '').trim() : '';
                if (cleaned && !seen.has(cleaned.toLowerCase())) {
                    bullets.push(cleaned);
                    seen.add(cleaned.toLowerCase());
                }
            });

            (keywords || []).forEach(function(keyword) {
                if (bullets.length >= 3) return;
                var bullet = this.mapKeywordToBullet(keyword);
                if (bullet && !seen.has(bullet.toLowerCase())) {
                    bullets.push(bullet);
                    seen.add(bullet.toLowerCase());
                }
            }, this);

            var fallback = [
                'Build detailed financial models and scenario analysis that feed directly into IC-ready materials.',
                'Translate complex data into crisp portfolio updates and talking points for leadership.',
                'Partner closely with founders and operating teams to surface the drivers that truly move the needle.'
            ];

            for (var i = 0; i < fallback.length && bullets.length < 3; i++) {
                var idea = fallback[i];
                if (!seen.has(idea.toLowerCase())) {
                    bullets.push(idea);
                    seen.add(idea.toLowerCase());
                }
            }

            return bullets.slice(0, 3);
        },

        mapKeywordToBullet: function(keyword) {
            if (!keyword) return '';
            var patterns = [
                { regex: /(model|valuation|lbo|build)/i, text: 'Build and maintain high-fidelity models that tie operating drivers to value creation.' },
                { regex: /(diligence|underwrite|deal)/i, text: 'Lead diligence and underwriting workstreams, translating findings into clear investment theses.' },
                { regex: /(portfolio|monitor|kpi|report)/i, text: 'Tighten portfolio monitoring and KPI reporting so stakeholders see the signal immediately.' },
                { regex: /(market|macro|research|mapping)/i, text: 'Synthesize market and macro research to frame risks and opportunities for the team.' },
                { regex: /(capital|fund|investor|liquidity)/i, text: 'Support capital allocation and liquidity planning by linking analytics to investor narratives.' },
                { regex: /(excel|automation|process)/i, text: 'Automate Excel/BI processes that free up the team for higher-value analysis.' },
                { regex: /(stakeholder|partner|founder|operator)/i, text: 'Partner directly with senior stakeholders to surface the levers that drive returns.' }
            ];

            for (var i = 0; i < patterns.length; i++) {
                if (patterns[i].regex.test(keyword)) {
                    return patterns[i].text;
                }
            }

            return 'Apply structured thinking and clear communication to keep investment discussions moving forward.';
        },

        selectIntroTemplate: function(templates, keywordSignals) {
            if (!templates || !templates.length) {
                return { text: 'Hi {recruiterFirst},\n\nI saw the {jobTitle}{companyClause} and wanted to introduce myself directly. {matchSentence}' };
            }

            keywordSignals = keywordSignals || [];
            var categories = this.categorizeKeywords(keywordSignals);
            var bestScore = -1;
            var best = [];

            templates.forEach(function(entry) {
                var tags = Array.isArray(entry.tags) ? entry.tags : ['general'];
                var score = 0;
                tags.forEach(function(tag) {
                    if (categories.indexOf(tag) !== -1) {
                        score++;
                    }
                });
                if (!categories.length && tags.indexOf('general') !== -1) {
                    score += 0.5;
                }
                if (score > bestScore) {
                    bestScore = score;
                    best = [entry];
                } else if (score === bestScore) {
                    best.push(entry);
                }
            });

            var pool = (bestScore > 0 && best.length) ? best : templates;
            return pool[Math.floor(Math.random() * pool.length)];
        },

        categorizeKeywords: function(keywords) {
            var categories = new Set();
            (keywords || []).forEach(function(keyword) {
                if (!keyword) return;
                var k = String(keyword).toLowerCase();
                if (/(model|valuation|lbo|dcf|excel|sensitivity)/.test(k)) categories.add('modeling');
                if (/(deal|transaction|execution|m&a|spa|closing)/.test(k)) categories.add('deals');
                if (/(portfolio|operator|kpi|value creation|ops)/.test(k)) categories.add('portfolio');
                if (/(investor|lp|fundraising|ir|reporting)/.test(k)) categories.add('investor_relations');
                if (/(acca|aca|cima|cpa|reconciliation|ledger|audit)/.test(k)) categories.add('accounting');
                if (/(automation|sql|python|power bi|tableau|bi|dashboard)/.test(k)) categories.add('automation');
                if (/(macro|market|bloomberg|capital iq|factset|cfa|research)/.test(k)) categories.add('markets');
                if (/(credit|debt|lending|underwriting|covenant)/.test(k)) categories.add('credit');
                if (/(infrastructure|project finance|ppp|concession|renewable)/.test(k)) categories.add('infrastructure');
                if (/(venture|startup|series|growth equity|founder)/.test(k)) categories.add('venture');
                if (/(real estate|leasing|argus|asset management)/.test(k)) categories.add('real_estate');
                if (/(treasury|liquidity|cash|hedging|fx)/.test(k)) categories.add('treasury');
                if (/(risk|compliance|control|policy|sox)/.test(k)) categories.add('risk');
                if (/(fp&a|forecast|budget|planning)/.test(k)) categories.add('fpna');
                if (/(transformation|change|operating model|turnaround)/.test(k)) categories.add('transformation');
                if (/(quant|derivative|trading|structured)/.test(k)) categories.add('quant');
                if (/(esg|sustainability|impact|sfdr|tcfd)/.test(k)) categories.add('esg');
                if (/(diligence|consulting|hypothesis|workstream)/.test(k)) categories.add('diligence');
                if (/(data|analytics|insight)/.test(k)) categories.add('data');
            });
            if (!categories.size) {
                categories.add('general');
            }
            return Array.from(categories);
        },

        getFallbackIntroTemplates: function() {
            return [
                { text: 'Hi {recruiterFirst},\n\nI came across the {jobTitle}{companyClause} and wanted to introduce myself directly. {matchSentence}', tags: ['general'] },
                { text: 'Hi {recruiterFirst},\n\nThe {jobTitle}{companyClause} sounds like a great match for my background, so I wanted to reach out personally. {matchSentence}', tags: ['general'] },
                { text: 'Hi {recruiterFirst},\n\nI’m excited by the {jobTitle}{companyClause} and thought it would be helpful to share a quick note. {matchSentence}', tags: ['general'] }
            ];
        },

        refreshIntroMessage: function() {
            var context = $.extend({}, this.introComposerState || {});
            var keywordSelections = $('.sffc-crm-intro-keyword-chip.is-selected').map(function() {
                return $(this).data('keyword');
            }).get().filter(function(item) { return item && item.length; });
            if (keywordSelections.length) {
                context.keywords = keywordSelections;
            }
            var nextMessage = this.buildIntroMessage(context);
            $('#intro-ai-message').val(nextMessage);
        },

        generateIntroAIMessage: function(isAuto) {
            var self = this;
            var state = this.introComposerState || {};

            if (!state.postId || !state.recruiterId) {
                this.refreshIntroMessage();
                return;
            }

            if (!(this.config.features && this.config.features.ai_personalization)) {
                if (!isAuto) {
                    this.showError('AI personalization requires an upgraded membership.');
                }
                this.refreshIntroMessage();
                return;
            }

            if (state.isGenerating) return;
            state.isGenerating = true;

            var $btn = $('#intro-regenerate-btn');
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<svg class="sffc-crm-spinner" width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="32" stroke-dashoffset="32"><animate attributeName="stroke-dashoffset" dur="1s" values="32;0" repeatCount="indefinite"/></circle></svg> Generating...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_generate_intro_message',
                    nonce: this.config.nonce,
                    post_id: state.postId,
                    recruiter_id: state.recruiterId,
                    cv_id: $('input[name="intro-cv-option"]:checked').val() || '',
                    match_score: state.matchScore || 0,
                    match_reasons: state.matchReasons || [],
                    match_warnings: state.matchWarnings || [],
                    keywords: state.keywords || []
                },
                success: function(response) {
                    if (response.success && response.data && response.data.message) {
                        $('#intro-ai-message').val(response.data.message);
                        if (!isAuto) {
                            self.showSuccess('Concierge note refreshed.');
                        }
                    } else {
                        if (!isAuto) {
                            self.handleError(response);
                        }
                        self.refreshIntroMessage();
                    }
                },
                error: function() {
                    if (!isAuto) {
                        self.showError('AI generation failed.');
                    }
                    self.refreshIntroMessage();
                },
                complete: function() {
                    state.isGenerating = false;
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        bindIntroPreferenceEvents: function() {
            var self = this;

            $(document).off('click.introKeywords').on('click.introKeywords', '.sffc-crm-intro-keyword-chip', function() {
                var $chip = $(this);
                if ($chip.hasClass('is-selected')) {
                    return;
                }
                $chip.addClass('is-selected');
                var keyword = $chip.data('keyword');
                var $field = $('#intro-ai-message');
                if (keyword && $field.length) {
                    var spacer = '';
                    if ($field.val() && !$field.val().match(/\s$/)) {
                        spacer = ' ';
                    }
                    self.insertAtCursor($field, spacer + keyword);
                }
            });

            $(document).off('click.introRegenerate', '#intro-regenerate-btn').on('click.introRegenerate', '#intro-regenerate-btn', function() {
                self.generateIntroAIMessage(false);
            });

            $(document).off('click.introManageCv', '#manage-cv-library').on('click.introManageCv', '#manage-cv-library', function() {
                self.closeModal();
                self.switchTab('resume');
            });

            $(document).off('click.introCvPreview', '.sffc-crm-intro-cv-toggle').on('click.introCvPreview', '.sffc-crm-intro-cv-toggle', function() {
                var targetId = $(this).data('target');
                var $preview = $('#' + targetId);
                if ($preview.length) {
                    $preview.toggleClass('is-visible');
                    $(this).text($preview.hasClass('is-visible') ? 'Hide Preview' : 'Preview CV');
                }
            });
        },

        // NEW Simple Express Interest Modal (LinkedIn-style, single column, mailto)
        showExpressInterestModal: function(postId, recruiterId, recruiterName, matchScore, matchReasons, matchWarnings, jobData) {
            var self = this;
            jobData = jobData || {};
            matchReasons = Array.isArray(matchReasons) ? matchReasons.filter(Boolean) : [];
            matchWarnings = Array.isArray(matchWarnings) ? matchWarnings.filter(Boolean) : [];

            var isEarlyBird = !!jobData.isEarlyBird;

            // Premium Members gating
            if (isEarlyBird && !this.config.isLoggedIn) {
                this.showAuthModal();
                return;
            }

            if (isEarlyBird && this.config.isLoggedIn && !this.config.isPremium) {
                this.showMonetizationModal('intro', {
                    jobTitle: jobData.jobTitle || '',
                    company: jobData.company || '',
                    recruiterName: recruiterName || 'Recruiter'
                });
                return;
            }

            var recruiterEmail = jobData.recruiterEmail || '';
            if (!recruiterEmail) {
                this.showError('Recruiter email is not available for this role.');
                return;
            }

            recruiterName = recruiterName || 'Recruiter';

            if (typeof matchScore === 'string') {
                matchScore = parseInt(matchScore, 10);
            }
            if (isNaN(matchScore)) {
                matchScore = 0;
            }

            var jobTitle = jobData.jobTitle || 'this opportunity';
            var company = jobData.company || '';
            var location = jobData.location || '';
            var recruiterLinkedIn = jobData.recruiterLinkedIn || jobData.recruiterLinkedin || jobData.linkedin || jobData.linkedin_url || '';
            var recruiterFirm = jobData.recruiterFirm || '';
            var recruiterTitle = jobData.recruiterTitle || '';
            var recruiterPhoto = jobData.recruiterPhoto || '';
            var recruiterInitial = (jobData.recruiterInitial || recruiterName.charAt(0) || 'R').toUpperCase();
            var recruiterFirstName = recruiterName.split(' ')[0] || 'there';
            var keywordSuggestions = Array.isArray(jobData.keywords) ? jobData.keywords.filter(Boolean).slice(0, 5) : [];

            this.introComposerState = {
                recruiterName: recruiterName,
                recruiterFirstName: recruiterFirstName,
                jobTitle: jobTitle,
                company: company,
                location: location,
                postId: postId,
                recruiterId: recruiterId,
                matchScore: matchScore,
                matchReasons: matchReasons.slice(0, 3),
                keywords: keywordSuggestions.slice(0, 3)
            };

            this.expressInterestState = {
                recruiterEmail: recruiterEmail,
                recruiterName: recruiterName,
                recruiterId: recruiterId,
                postId: postId,
                jobTitle: jobTitle,
                company: company,
                isEarlyBird: isEarlyBird
            };

            var suggestedSubject = this.buildIntroSubject(jobTitle, company);
            var introMessage = this.buildIntroMessage(this.introComposerState);
            var escapedMessage = this.escapeHtml(introMessage).replace(/\n/g, '&#10;');

            var badgeText = isEarlyBird ? 'Pro+ Members • Exclusive access' : 'Direct recruiter contact • Free';
            var roleLineParts = [];
            if (jobTitle) roleLineParts.push(jobTitle);
            if (company) roleLineParts.push(company);
            if (location) roleLineParts.push(location);
            var roleLine = roleLineParts.join(' • ');

            var recruiterMetaParts = [];
            if (recruiterTitle) recruiterMetaParts.push(recruiterTitle);
            if (recruiterFirm) recruiterMetaParts.push(recruiterFirm);
            var recruiterMeta = recruiterMetaParts.join(' • ');

            var html = '<div class="sffc-crm-intro-request-modal">';
            html += '<div class="sffc-crm-intro-modal-header">';
            html += '<div class="sffc-crm-intro-recipient">';
            if (recruiterPhoto) {
                html += '<div class="sffc-crm-intro-recipient-avatar"><img src="' + this.escapeHtml(recruiterPhoto) + '" alt="' + this.escapeHtml(recruiterName) + '"></div>';
            } else {
                html += '<div class="sffc-crm-intro-recipient-avatar"><span>' + this.escapeHtml(recruiterInitial) + '</span></div>';
            }
            html += '<div class="sffc-crm-intro-recipient-text">';
            html += '<p class="sffc-crm-intro-eyebrow">' + this.escapeHtml(badgeText) + '</p>';
            html += '<h2>Message ' + this.escapeHtml(recruiterName) + '</h2>';
            if (recruiterMeta) {
                html += '<p class="sffc-crm-intro-recipient-role">' + this.escapeHtml(recruiterMeta) + '</p>';
            }
            if (roleLine) {
                html += '<p class="sffc-crm-intro-recipient-role">' + this.escapeHtml(roleLine) + '</p>';
            }
            html += '</div>';
            html += '</div>';
            html += '<div class="sffc-crm-intro-job-pill">';
            html += '<span>Live role</span>';
            html += '<strong>' + this.escapeHtml(jobTitle) + '</strong>';
            if (company || location) {
                html += '<p>' + this.escapeHtml([company, location].filter(Boolean).join(' • ')) + '</p>';
            }
            if (matchScore > 0) {
                html += '<div class="sffc-crm-intro-match-pill">' + matchScore + '% match</div>';
            }
            html += '</div>';
            html += '</div>';

            html += '<div class="sffc-crm-intro-body-layout">';
            html += '<div class="sffc-crm-intro-context">';
            if (matchReasons.length) {
                html += '<div class="sffc-crm-intro-context-card">';
                html += '<div class="sffc-crm-intro-context-title">Match highlights</div>';
                html += '<ul>';
                matchReasons.slice(0, 3).forEach(function(reason) {
                    html += '<li>' + self.escapeHtml(reason) + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }
            if (keywordSuggestions.length) {
                html += '<div class="sffc-crm-intro-context-card">';
                html += '<div class="sffc-crm-intro-context-title">Talking points</div>';
                html += '<div class="sffc-crm-intro-keyword-chips">' + this.renderIntroKeywordChips(keywordSuggestions) + '</div>';
                html += '</div>';
            }
            html += '<div class="sffc-crm-intro-contact-card">';
            html += '<div class="sffc-crm-intro-contact-row">';
            html += '<div>'; 
            html += '<span>Email</span>';
            html += '<strong id="intro-email-display">' + this.escapeHtml(recruiterEmail) + '</strong>';
            html += '</div>';
            html += '<div class="sffc-crm-intro-contact-actions">';
            html += '<button type="button" class="sffc-crm-intro-copy" id="intro-copy-email" data-email="' + this.escapeHtml(recruiterEmail) + '">Copy</button>';
            if (recruiterLinkedIn) {
                html += '<a class="sffc-crm-intro-linkedin-btn" href="' + this.escapeHtml(recruiterLinkedIn) + '" target="_blank" rel="noopener">LinkedIn</a>';
            }
            html += '</div>';
            html += '</div>';
            if (matchWarnings.length) {
                html += '<p class="sffc-crm-intro-context-note">' + this.escapeHtml(matchWarnings[0]) + '</p>';
            }
            html += '</div>';
            html += '</div>'; // context

            html += '<div class="sffc-crm-intro-message-card">';
            html += '<div class="sffc-crm-intro-message-header">';
            html += '<div>You → ' + this.escapeHtml(recruiterFirstName) + '</div>';
            if (matchScore > 0) {
                html += '<span class="sffc-crm-intro-message-badge">' + matchScore + '% fit</span>';
            }
            html += '</div>';
            html += '<label for="intro-email-subject">Subject</label>';
            html += '<div class="sffc-crm-intro-subject-row">';
            html += '<input type="text" id="intro-email-subject" value="' + this.escapeHtml(suggestedSubject) + '">';
            html += '<button type="button" class="sffc-crm-intro-copy" id="intro-copy-subject">Copy</button>';
            html += '</div>';
            html += '<label for="intro-ai-message">Message</label>';
            html += '<textarea id="intro-ai-message" rows="12">' + escapedMessage + '</textarea>';
            html += '<div class="sffc-crm-intro-message-actions">';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary" id="intro-regenerate-message"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>Regenerate</button>';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary" id="intro-copy-message">Copy message</button>';
            html += '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary" id="intro-open-mail" data-email="' + this.escapeHtml(recruiterEmail) + '">Send via email</button>';
            html += '</div>';
            html += '<p class="sffc-crm-intro-note">Your default email client opens with this subject and message so you can send immediately.</p>';
            html += '</div>'; // message card
            html += '</div>'; // body layout
            html += '</div>';

            this.showModal(html, 'sffc-crm-modal-content--gap sffc-crm-modal-content--narrow');
            this.bindExpressInterestModalEvents();
            this.activeModal = 'express_interest';
        },

        buildIntroSubject: function(jobTitle, company) {
            var role = (jobTitle && jobTitle.trim()) ? jobTitle.trim() : 'this opportunity';
            var subject = 'Interest in ' + role;
            if (company && company.trim()) {
                subject += ' at ' + company.trim();
            }
            return subject;
        },

        bindExpressInterestModalEvents: function() {
            var self = this;
            $(document).off('click.introCopyEmail', '#intro-copy-email').on('click.introCopyEmail', '#intro-copy-email', function() {
                var email = $(this).data('email') || (self.expressInterestState && self.expressInterestState.recruiterEmail);
                self.copyExpressInterestValue(email, 'Email copied to clipboard');
            });

            $(document).off('click.introCopySubject', '#intro-copy-subject').on('click.introCopySubject', '#intro-copy-subject', function() {
                var subject = ($('#intro-email-subject').val() || '').trim();
                self.copyExpressInterestValue(subject, 'Subject copied to clipboard');
            });

            $(document).off('click.introCopyMessage', '#intro-copy-message').on('click.introCopyMessage', '#intro-copy-message', function() {
                var message = ($('#intro-ai-message').val() || '').trim();
                self.copyExpressInterestValue(message, 'Message copied to clipboard');
            });

            $(document).off('click.introRegenerateMessage', '#intro-regenerate-message').on('click.introRegenerateMessage', '#intro-regenerate-message', function() {
                if (self.expressInterestState) {
                    var newMessage = self.buildIntroMessage(self.expressInterestState);
                    $('#intro-ai-message').val(newMessage);
                    self.showTemporaryToast('✨ New message generated');
                }
            });

            $(document).off('click.introOpenMail', '#intro-open-mail').on('click.introOpenMail', '#intro-open-mail', function() {
                var email = $(this).data('email') || (self.expressInterestState && self.expressInterestState.recruiterEmail);
                self.launchExpressInterestEmail(email);
            });
        },

        copyExpressInterestValue: function(value, successMessage) {
            if (!value || !value.length) {
                this.showError('Nothing to copy.');
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(function() {
                    SFFCCRM.showSuccess(successMessage || 'Copied to clipboard');
                }).catch(function() {
                    SFFCCRM.showError('Failed to copy. Please copy manually.');
                });
            } else {
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(value).select();
                try {
                    document.execCommand('copy');
                    SFFCCRM.showSuccess(successMessage || 'Copied to clipboard');
                } catch (err) {
                    SFFCCRM.showError('Failed to copy. Please copy manually.');
                }
                $temp.remove();
            }
        },

        launchExpressInterestEmail: function(explicitEmail) {
            var state = this.expressInterestState || {};
            var targetEmail = explicitEmail || state.recruiterEmail;
            if (!targetEmail) {
                this.showError('Recruiter email is missing.');
                return;
            }

            var subject = ($('#intro-email-subject').val() || '').trim();
            var message = ($('#intro-ai-message').val() || '').trim();

            if (!subject.length) {
                this.showError('Add a subject before continuing.');
                return;
            }
            if (!message.length) {
                this.showError('Add a message before continuing.');
                return;
            }

            var mailto = 'mailto:' + encodeURIComponent(targetEmail)
                + '?subject=' + encodeURIComponent(subject)
                + '&body=' + encodeURIComponent(message);
            window.location.href = mailto;
        },

        submitIntroductionRequest: function(postId, recruiterId, introPayload, matchScore) {
            var self = this;
            introPayload = introPayload || {};

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_request_introduction',
                    nonce: this.config.nonce,
                    post_id: postId,
                    recruiter_id: recruiterId,
                    note: introPayload.note || '',
                    match_score: matchScore,
                    cv_id: introPayload.cvId || '',
                    intro_message: introPayload.introMessage || '',
                    schedule_option: introPayload.scheduleOption || 'asap',
                    selected_keywords: introPayload.keywords || []
                },
                success: function(response) {
                    self.closeModal();
                    if (response.success) {
                        // Update usage counter
                        if (response.data && response.data.usage) {
                            self.updateUsageCounter(response.data.usage);
                        }
                    self.showSuccess('Express Interest submitted! The MENA Careers team will be in touch soon.');

                        // Inject the row immediately using data returned by PHP —
                        // no second round-trip needed.  Fall back to a full reload
                        // only if the server didn't send back the enriched row (e.g.
                        // older cached PHP).
                        if (response.data && response.data.request) {
                            self.injectRecruiterIntroRow(response.data.request);
                        } else {
                            self.refreshRecruiterIntrosBadge();
                        }
                    } else {
                        // Handle usage limit / validation errors
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to send Express Interest request.';
                        if (response.data && response.data.upgrade_url) {
                            errorMsg += ' <a href="' + response.data.upgrade_url + '" style="color: #fff; text-decoration: underline;">Upgrade to premium</a>';
                        }
                        self.showError(errorMsg);
                    }
                },
                error: function() {
                    self.closeModal();
                    self.showError('Failed to send Express Interest request. Please try again.');
                }
            });
        },

        openContactDetail: function(contactId) {
            var self = this;
            this.showModal('<div class="sffc-crm-modal-loading">Loading contact details...</div>');
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_contact',
                    nonce: this.config.nonce,
                    contact_id: contactId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderContactDetail(response.data.contact);
                    } else {
                        self.closeModal();
                        self.showError('Failed to load contact details');
                    }
                },
                error: function() {
                    self.closeModal();
                    self.showError('Failed to load contact details');
                }
            });
        },

        renderContactDetail: function(contact) {
            var isSaved = contact.is_saved == 1;
            var initial = (contact.first_name || 'C').charAt(0).toUpperCase();
            var html = '<div class="sffc-crm-contact-detail">';
            html += '<div class="sffc-crm-modal-header"><button class="sffc-crm-modal-close" aria-label="Close"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button></div>';
            html += '<div class="sffc-crm-detail-recruiter"><div class="sffc-crm-detail-avatar sffc-crm-avatar-placeholder">' + initial + '</div>';
            html += '<div class="sffc-crm-detail-recruiter-info"><h3 class="sffc-crm-detail-recruiter-name">' + this.escapeHtml(contact.full_name) + '</h3>';
            if (contact.job_title) html += '<span class="sffc-crm-detail-recruiter-firm">' + this.escapeHtml(contact.job_title) + '</span>';
            if (contact.company_name) html += '<span class="sffc-crm-detail-recruiter-firm">at ' + this.escapeHtml(contact.company_name) + '</span>';
            html += '</div></div>';

            html += '<div class="sffc-crm-detail-meta">';
            if (contact.email) {
                html += '<div class="sffc-crm-detail-meta-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg><span>' + this.escapeHtml(contact.email) + '</span></div>';
            }
            if (contact.phone_1) {
                html += '<div class="sffc-crm-detail-meta-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg><span>' + this.escapeHtml(contact.phone_1) + '</span></div>';
            }
            var location = [contact.city, contact.country].filter(Boolean).join(', ');
            if (location) {
                html += '<div class="sffc-crm-detail-meta-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg><span>' + this.escapeHtml(location) + '</span></div>';
            }
            if (contact.seniority) {
                html += '<div class="sffc-crm-detail-meta-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><span>' + this.escapeHtml(contact.seniority) + '</span></div>';
            }
            html += '</div>';

            if (contact.company_name) {
                html += '<div class="sffc-crm-detail-section"><h4>Company</h4><div class="sffc-crm-detail-company-info">';
                html += '<p class="sffc-crm-detail-company-name">' + this.escapeHtml(contact.company_name) + '</p>';
                if (contact.company_industry) html += '<p class="sffc-crm-detail-company-meta">Industry: ' + this.escapeHtml(contact.company_industry) + '</p>';
                if (contact.company_size) html += '<p class="sffc-crm-detail-company-meta">Size: ' + this.escapeHtml(contact.company_size) + ' employees</p>';
                html += '</div></div>';
            }

            html += '<div class="sffc-crm-detail-actions">';
            html += '<button class="sffc-crm-btn sffc-crm-btn-icon sffc-crm-contact-save-btn ' + (isSaved ? 'is-saved' : '') + '" data-contact-id="' + contact.id + '"><svg width="20" height="20" viewBox="0 0 24 24" fill="' + (isSaved ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg></button>';
            if (contact.linkedin_url) {
                html += '<a href="' + this.escapeHtml(contact.linkedin_url) + '" class="sffc-crm-btn sffc-crm-btn-primary" target="_blank" rel="noopener noreferrer"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg> View LinkedIn</a>';
            }
            if (contact.email) {
                html += '<a href="mailto:' + this.escapeHtml(contact.email) + '" class="sffc-crm-btn sffc-crm-btn-secondary"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Email</a>';
            }
            html += '</div></div>';

            this.updateModalContent(html);
        }
    };

    // Create alias for backwards compatibility
    window.sffcCRMApp = window.SennaCRM;

    // Initialize on DOM ready
    $(document).ready(function() {
        if ($('.sffc-crm-container').length) {
            SennaCRM.init();
        }
    });

})(jQuery);
