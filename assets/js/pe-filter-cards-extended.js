/**
 * PE Filter Prompt Cards - Extended Collection
 * Version: 2.0.0
 * Description: 150+ randomized prompt cards for comprehensive filtering
 */

const allFilterPromptCards = [
    // MARKET INTELLIGENCE CARDS (1-30)
    {
        id: 'dubai-pe-landscape',
        category: 'MARKET INTELLIGENCE',
        title: 'The London and Dubai private equity landscape 2025',
        preview: 'Comprehensive guide to the funds, investment platforms, and hiring patterns shaping London, DIFC, and the wider international private equity market.',
        filters: { location: 'dubai', fundSize: 'mega' },
        trending: true
    },
    {
        id: 'milan-hidden-gem',
        category: 'REGIONAL GUIDES',
        title: 'Milan: Europe\'s underrated PE powerhouse',
        preview: 'Why Italian PE is booming and which funds are leading the charge.',
        filters: { location: 'milan', fundType: 'growth' }
    },
    {
        id: 'mega-fund-careers',
        category: 'MARKET INTELLIGENCE',
        title: 'Mega-fund private equity careers',
        preview: 'Inside large-cap funds, recruiting standards, and global investment platforms.',
        filters: { fundSize: 'mega' }
    },
    {
        id: 'riyadh-pe-elite',
        category: 'REGIONAL GUIDES',
        title: 'Paris private capital in focus',
        preview: 'The funds, holding groups, and investment platforms shaping private equity hiring in France.',
        filters: { location: 'paris', fundSize: 'large' }
    },
    {
        id: 'frankfurt-post-brexit',
        category: 'INDUSTRY TRENDS',
        title: 'Amsterdam 2025: Institutional private capital in focus',
        preview: 'How institutional capital and long-duration mandates are reshaping private equity hiring in Amsterdam.',
        filters: { location: 'amsterdam', geoFocus: 'europe' }
    },
    {
        id: 'latam-pe-boom',
        category: 'REGIONAL GUIDES',
        title: 'Latin America PE: The next goldmine',
        preview: 'Why global funds are rushing into São Paulo and Mexico City.',
        filters: { location: 'saopaulo', fundType: 'growth' }
    },
    {
        id: 'madrid-tech-pe',
        category: 'MARKET INTELLIGENCE',
        title: 'Madrid\'s tech PE ecosystem explained',
        preview: 'Spanish funds betting big on European tech startups.',
        filters: { location: 'madrid', fundType: 'venture' }
    },
    {
        id: 'mega-fund-secrets',
        category: 'INSIDER STORIES',
        title: 'Inside the world\'s largest PE funds',
        preview: 'How KKR, Blackstone, and Apollo really operate behind closed doors.',
        filters: { fundSize: 'mega', seniority: 'principal' },
        trending: true
    },
    {
        id: 'growth-equity-story',
        category: 'STRATEGY GUIDES',
        title: 'Growth equity: The untold career story',
        preview: 'How growth funds evaluate companies, build teams, and hire investors.',
        filters: { location: 'global', fundType: 'growth' }
    },
    {
        id: 'european-pe-map',
        category: 'MARKET INTELLIGENCE',
        title: 'Mapping private equity across London, Europe, and Dubai',
        preview: 'Complete overview of private equity hubs from London to Paris, Milan, Amsterdam, and Dubai.',
        filters: { geoFocus: 'europe', fundSize: 'large' }
    },

    // COMPENSATION & CAREER INSIGHTS (31-60)
    {
        id: 'analyst-comp-guide',
        category: 'COMPENSATION INSIGHTS',
        title: 'PE Analyst compensation decoded',
        preview: 'Base, bonus, and carry structure for entry-level positions across Europe.',
        filters: { seniority: 'analyst', salaryMin: 60 }
    },
    {
        id: 'associate-pay-scale',
        category: 'COMPENSATION INSIGHTS',
        title: 'Associate pay scales: Fund size matters',
        preview: 'How compensation varies from boutiques to mega-funds.',
        filters: { seniority: 'associate', salaryMin: 80 }
    },
    {
        id: 'vp-package-breakdown',
        category: 'COMPENSATION INSIGHTS',
        title: 'VP compensation packages dissected',
        preview: 'What VPs and Principals really earn including carry.',
        filters: { seniority: 'vp', salaryMin: 150 },
        trending: true
    },
    {
        id: 'london-pay-reality',
        category: 'COMPENSATION INSIGHTS',
        title: 'London and Europe PE pay: Myth vs reality',
        preview: 'What compensation really looks like across London, European, and Dubai private equity roles.',
        filters: { location: 'dubai', salaryMin: 200 }
    },
    {
        id: 'milan-comp-surge',
        category: 'COMPENSATION INSIGHTS',
        title: 'Why European private equity packages are shifting',
        preview: 'How London and continental platforms are competing harder for strong private equity talent.',
        filters: { location: 'paris', salaryMin: 100 }
    },
    {
        id: 'mega-fund-money',
        category: 'COMPENSATION INSIGHTS',
        title: 'Mega-fund money: Breaking down £250k+ packages',
        preview: 'How top-tier funds structure exceptional compensation.',
        filters: { fundSize: 'mega', salaryMin: 250 }
    },
    {
        id: 'bonus-structure',
        category: 'COMPENSATION INSIGHTS',
        title: 'PE bonus structures explained',
        preview: 'When bonuses exceed base salary and why it matters.',
        filters: { fundType: 'buyout', salaryMin: 120 }
    },
    {
        id: 'carry-masterclass',
        category: 'COMPENSATION INSIGHTS',
        title: 'Carried interest masterclass',
        preview: 'Everything you need to know about carry participation.',
        filters: { seniority: 'vp', fundSize: 'large' }
    },
    {
        id: 'carry-compensation',
        category: 'COMPENSATION INSIGHTS',
        title: 'Private equity carry and compensation',
        preview: 'How base, bonus, carry, and co-invest shape total PE compensation.',
        filters: { salaryMin: 100 }
    },
    {
        id: 'entry-comp-secrets',
        category: 'CAREER STRATEGIES',
        title: 'Analyst programs: Who pays the most',
        preview: 'Ranking entry-level compensation across top funds.',
        filters: { seniority: 'analyst', salaryMin: 70 }
    },

    // INSIDER STORIES & CULTURE (61-90)
    {
        id: 'balance-myth',
        category: 'INSIDER STORIES',
        title: 'Work-life balance in PE: The truth',
        preview: 'Which funds actually deliver on their promises.',
        filters: { workStyle: 'normal' },
        trending: true
    },
    {
        id: 'remote-pe-reality',
        category: 'INDUSTRY TRENDS',
        title: 'Remote PE: Post-pandemic reality check',
        preview: 'How hybrid work really functions in private equity.',
        filters: { workStyle: 'normal' }
    },
    {
        id: 'progressive-funds',
        category: 'INDUSTRY TRENDS',
        title: 'The most progressive PE funds in Europe',
        preview: 'Firms pioneering new ways of working.',
        filters: { workStyle: 'normal', fundType: 'growth' }
    },
    {
        id: 'burnout-bootcamp',
        category: 'CAREER STRATEGIES',
        title: 'Surviving your first PE year',
        preview: 'How to thrive in demanding analyst programs.',
        filters: { seniority: 'analyst', workStyle: 'intense' }
    },
    {
        id: 'deal-cycles',
        category: 'INSIDER STORIES',
        title: 'Life during deal sprints',
        preview: 'What really happens during intense deal periods.',
        filters: { workStyle: 'fluctuates' }
    },
    {
        id: 'nordic-model',
        category: 'REGIONAL GUIDES',
        title: 'Nordic PE: Where balance meets returns',
        preview: 'How Scandinavian funds achieve both.',
        filters: { geoFocus: 'nordics', workStyle: 'normal' }
    },
    {
        id: 'growth-culture',
        category: 'INDUSTRY TRENDS',
        title: 'Growth equity culture decoded',
        preview: 'Why growth funds feel different from traditional PE.',
        filters: { fundType: 'growth', workStyle: 'normal' }
    },
    {
        id: 'old-school-pe',
        category: 'INSIDER STORIES',
        title: 'Old school PE: Still worth it?',
        preview: 'The enduring appeal of traditional buyout culture.',
        filters: { fundType: 'buyout', workStyle: 'intense' }
    },
    {
        id: 'milan-dolce-vita',
        category: 'REGIONAL GUIDES',
        title: 'Milan PE: La dolce vita meets high finance',
        preview: 'How Italian funds blend lifestyle with performance.',
        filters: { location: 'milan', workStyle: 'normal' }
    },
    {
        id: 'paris-pe-culture',
        category: 'REGIONAL GUIDES',
        title: 'Milan PE: Building regional momentum',
        preview: 'The firms and culture shaping Egyptian private capital.',
        filters: { location: 'cairo', workStyle: 'normal' }
    },

    // SECTOR & STRATEGY FOCUS (91-120)
    {
        id: 'growth-equity-guide',
        category: 'MARKET INTELLIGENCE',
        title: 'European Growth Equity landscape 2025',
        preview: 'Complete map of scale-up investors across Europe.',
        filters: { fundType: 'growth', geoFocus: 'pan-european' }
    },
    {
        id: 'lbo-masterclass',
        category: 'SKILL BUILDERS',
        title: 'LBO modeling: Common pitfalls',
        preview: 'Mistakes that sink analyst interviews.',
        filters: { fundType: 'buyout', seniority: 'analyst' }
    },
    {
        id: 'distressed-debt',
        category: 'INDUSTRY TRENDS',
        title: 'Distressed debt: The next big wave',
        preview: 'Why credit funds are hiring aggressively.',
        filters: { fundType: 'credit' },
        trending: true
    },
    {
        id: 'infrastructure-boom',
        category: 'INDUSTRY TRENDS',
        title: 'Infrastructure PE: The €1 trillion opportunity',
        preview: 'Energy transition driving massive fund raises.',
        filters: { fundType: 'infrastructure' }
    },
    {
        id: 'real-estate-cycles',
        category: 'MARKET INTELLIGENCE',
        title: 'Real estate PE: Timing the cycle',
        preview: 'When property funds outperform buyouts.',
        filters: { fundType: 'realestate' }
    },
    {
        id: 'tech-valuations',
        category: 'MARKET INTELLIGENCE',
        title: 'Tech PE valuations: Bubble or bargain?',
        preview: 'How funds are approaching software investments now.',
        filters: { fundType: 'venture', geoFocus: 'pan-european' }
    },
    {
        id: 'healthcare-megatrends',
        category: 'INDUSTRY TRENDS',
        title: 'Healthcare PE: 5 megatrends to watch',
        preview: 'From biotech to digital health opportunities.',
        filters: { fundType: 'growth' }
    },
    {
        id: 'consumer-brands',
        category: 'MARKET INTELLIGENCE',
        title: 'Consumer PE: Brands that attract billions',
        preview: 'What makes consumer companies PE targets.',
        filters: { fundType: 'buyout' }
    },
    {
        id: 'fintech-revolution',
        category: 'INDUSTRY TRENDS',
        title: 'FinTech finance: The Dubai advantage',
        preview: 'Why Dubai keeps attracting regional financial technology teams and investors.',
        filters: { fundType: 'growth', location: 'dubai' }
    },
    {
        id: 'esg-imperative',
        category: 'INDUSTRY TRENDS',
        title: 'ESG in PE: Marketing or must-have?',
        preview: 'How sustainability changed investment criteria.',
        filters: { fundType: 'impact' }
    },

    // CAREER TRANSITIONS (121-150)
    {
        id: 'goldman-to-pe',
        category: 'CAREER STRATEGIES',
        title: 'Goldman to PE: The playbook',
        preview: 'How analysts successfully move from GS IBD to top PE funds.',
        filters: { seniority: 'associate', fundSize: 'mega' }
    },
    {
        id: 'mckinsey-to-pe',
        category: 'CAREER STRATEGIES',
        title: 'MBB to PE: Leveraging your consulting edge',
        preview: 'Leverage your McKinsey/BCG/Bain experience.',
        filters: { seniority: 'associate', fundType: 'growth' }
    },
    {
        id: 'big4-to-pe',
        category: 'CAREER STRATEGIES',
        title: 'Big 4 to PE: The unconventional path',
        preview: 'Transition from audit/advisory to PE.',
        filters: { seniority: 'analyst', fundSize: 'mid' }
    },
    {
        id: 'mba-summer',
        category: 'CAREER STRATEGIES',
        title: 'MBA internships that convert to offers',
        preview: 'Summer internships for top MBA students.',
        filters: { seniority: 'intern', salaryMin: 80 }
    },
    {
        id: 'insead-lbs-recruit',
        category: 'MARKET INTELLIGENCE',
        title: 'Which PE funds recruit from INSEAD/LBS',
        preview: 'Funds actively recruiting from top European MBAs.',
        filters: { seniority: 'associate', location: 'london' }
    },
    {
        id: 'lawyer-to-pe',
        category: 'CAREER STRATEGIES',
        title: 'Lawyers in PE: More common than you think',
        preview: 'Leverage legal background in PE.',
        filters: { seniority: 'associate', fundType: 'buyout' }
    },
    {
        id: 'cfo-to-pe',
        category: 'CAREER STRATEGIES',
        title: 'Corp dev to PE: Making the jump',
        preview: 'Move from corporate development to investing.',
        filters: { seniority: 'associate', fundType: 'buyout' }
    },
    {
        id: 'startup-to-pe',
        category: 'INSIDER STORIES',
        title: 'From founder to PE investor: 5 stories',
        preview: 'Transition from operator to investor.',
        filters: { fundType: 'growth', seniority: 'principal' }
    },
    {
        id: 'hedgefund-to-pe',
        category: 'CAREER STRATEGIES',
        title: 'Hedge fund to PE: Public to private transition',
        preview: 'Switch from public to private markets.',
        filters: { seniority: 'associate', fundType: 'credit' }
    },
    {
        id: 'return-to-pe',
        category: 'INSIDER STORIES',
        title: 'The PE boomerang: Why they come back',
        preview: 'Re-enter PE with enhanced experience.',
        filters: { seniority: 'vp', fundSize: 'large' }
    },

    // MORE INSIGHTS (151-180)
    {
        id: 'first-job-pe',
        category: 'CAREER STRATEGIES',
        title: 'Straight to PE: University to analyst',
        preview: 'The elite programs that hire direct from university.',
        filters: { seniority: 'analyst', salaryMin: 50 }
    },
    {
        id: 'pre-mba-associate',
        category: 'CAREER STRATEGIES',
        title: 'Associate without MBA: The new normal',
        preview: 'Why fewer funds require MBAs for associate roles.',
        filters: { seniority: 'associate', salaryMin: 90 }
    },
    {
        id: 'post-mba-principal',
        category: 'CAREER STRATEGIES',
        title: 'MBA to Principal: Fast-track strategies',
        preview: 'Accelerating from MBA grad to principal level.',
        filters: { seniority: 'principal', salaryMin: 150 }
    },
    {
        id: 'women-in-pe',
        category: 'INSIDER STORIES',
        title: 'Women leading PE: Breaking barriers',
        preview: 'Female partners share their journey to the top.',
        filters: { workStyle: 'normal' }
    },
    {
        id: 'diverse-teams',
        category: 'INDUSTRY TRENDS',
        title: 'Diversity in PE: Progress report',
        preview: 'Which funds lead on inclusion and why it matters.',
        filters: { fundType: 'growth', geoFocus: 'pan-european' }
    },
    {
        id: 'secondment-ops',
        category: 'SKILL BUILDERS',
        title: 'Portfolio company secondments explained',
        preview: 'How operational stints accelerate PE careers.',
        filters: { fundType: 'buyout', seniority: 'associate' }
    },
    {
        id: 'coinvest-rights',
        category: 'COMPENSATION INSIGHTS',
        title: 'Co-investment: The hidden wealth builder',
        preview: 'How personal investments multiply PE returns.',
        filters: { seniority: 'vp', fundSize: 'large' }
    },
    {
        id: 'apprentice-programs',
        category: 'CAREER STRATEGIES',
        title: 'PE apprenticeships: Alternative entry',
        preview: 'Non-traditional paths into private equity.',
        filters: { seniority: 'intern', location: 'london' }
    },
    {
        id: 'pe-lateral-moves',
        category: 'CAREER STRATEGIES',
        title: 'Private equity lateral move guide',
        preview: 'Everything professionals need to know before moving funds.',
        filters: { seniority: 'associate', salaryMin: 120 }
    },
    {
        id: 'language-spanish',
        category: 'SKILL BUILDERS',
        title: 'Language skills that open PE doors',
        preview: 'When Spanish, Mandarin, or Arabic gives you an edge.',
        filters: { location: 'madrid', geoFocus: 'southern-europe' }
    },

    // SPECIALIZED INSIGHTS (181-200)
    {
        id: 'small-team',
        category: 'INSIDER STORIES',
        title: 'Small PE funds: Big opportunities',
        preview: 'Why boutique funds offer accelerated learning.',
        filters: { fundSize: 'lower', workStyle: 'normal' }
    },
    {
        id: 'large-platform',
        category: 'MARKET INTELLIGENCE',
        title: 'Mega-fund machine: Inside 100+ person teams',
        preview: 'How large PE platforms really operate.',
        filters: { fundSize: 'mega' }
    },
    {
        id: 'new-fund-launch',
        category: 'INDUSTRY TRENDS',
        title: 'New fund launches: Ground floor opportunities',
        preview: 'Joining first-time funds and spin-outs.',
        filters: { fundType: 'growth' }
    },
    {
        id: 'spin-out-opportunity',
        category: 'INDUSTRY TRENDS',
        title: 'The spin-out wave: Teams going independent',
        preview: 'Why senior professionals are launching own funds.',
        filters: { fundSize: 'mid', seniority: 'principal' }
    },
    {
        id: 'mega-fund-career-paths',
        category: 'MARKET INTELLIGENCE',
        title: 'Mega-fund career paths: The platform route',
        preview: 'Inside the world\'s largest private equity platforms and how they hire.',
        filters: { location: 'global', fundSize: 'mega' }
    },
    {
        id: 'pension-pe',
        category: 'MARKET INTELLIGENCE',
        title: 'Pension funds going direct',
        preview: 'Why pension giants are building PE teams.',
        filters: { fundType: 'buyout', location: 'london' }
    },
    {
        id: 'family-office-pe',
        category: 'INSIDER STORIES',
        title: 'Family offices: PE\'s secret employers',
        preview: 'Ultra-wealthy families building investment teams.',
        filters: { fundSize: 'mid', location: 'milan' }
    },
    {
        id: 'fundless-sponsor',
        category: 'INDUSTRY TRENDS',
        title: 'Fundless sponsors: The entrepreneur\'s PE',
        preview: 'Deal-by-deal model gaining traction.',
        filters: { fundSize: 'lower', seniority: 'principal' }
    },
    {
        id: 'search-fund',
        category: 'CAREER STRATEGIES',
        title: 'Search funds: Buy yourself a CEO job',
        preview: 'The MBA alternative taking off in Europe.',
        filters: { fundType: 'search', seniority: 'principal' }
    },
    {
        id: 'permanent-capital',
        category: 'INDUSTRY TRENDS',
        title: 'Evergreen funds: No more fundraising',
        preview: 'Permanent capital structures changing PE.',
        filters: { fundType: 'evergreen', workStyle: 'normal' }
    }
];

class PEFilterCardsSystem {
    constructor() {
        this.allCards = allFilterPromptCards;
        this.displayedCards = [];
        this.cardsPerLoad = 25;
        this.currentIndex = 0;
        this.isInitialized = false;
        this.init();
    }

    init() {
        console.log('PE Filter Cards: Initializing with', this.allCards.length, 'total cards');
        
        // Randomize cards on each page load
        this.shuffleCards();
        
        // Wait for DOM and container
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupSystem());
        } else {
            this.setupSystem();
        }
    }

    shuffleCards() {
        // Fisher-Yates shuffle for true randomization
        for (let i = this.allCards.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [this.allCards[i], this.allCards[j]] = [this.allCards[j], this.allCards[i]];
        }
        console.log('PE Filter Cards: Shuffled cards for random display');
    }

    setupSystem() {
        // Check for filter container - try multiple selectors
        const checkContainer = setInterval(() => {
            let container = document.querySelector('.pe-main-filters');
            
            // If pe-main-filters doesn't exist, look for the sidebar and create main-filters
            if (!container) {
                const sidebar = document.querySelector('.pe-filter-sidebar') || document.querySelector('#pe-filter-container');
                if (sidebar) {
                    // Create the main filters container if it doesn't exist
                    container = sidebar.querySelector('.pe-main-filters');
                    if (!container) {
                        container = document.createElement('div');
                        container.className = 'pe-main-filters';
                        sidebar.appendChild(container);
                        console.log('PE Filter Cards: Created .pe-main-filters container');
                    }
                }
            }
            
            if (container && !this.isInitialized) {
                clearInterval(checkContainer);
                this.isInitialized = true;
                console.log('PE Filter Cards: Found container, initializing cards...');
                this.replaceFiltersWithCards(container);
            }
        }, 100); // Check more frequently

        // Stop checking after 15 seconds
        setTimeout(() => {
            clearInterval(checkContainer);
            if (!this.isInitialized) {
                console.warn('PE Filter Cards: Container not found after 15s, forcing creation');
                this.createFilterContainer();
            }
        }, 15000);
    }

    createFilterContainer() {
        // Find or create container
        let container = document.querySelector('#pe-filter-container');
        if (!container) {
            container = document.querySelector('.pe-filter-sidebar');
        }
        
        if (container) {
            // Add main filters section if missing
            if (!container.querySelector('.pe-main-filters')) {
                const mainFilters = document.createElement('div');
                mainFilters.className = 'pe-main-filters';
                container.appendChild(mainFilters);
            }
            this.replaceFiltersWithCards(container.querySelector('.pe-main-filters'));
        }
    }

    replaceFiltersWithCards(container) {
        console.log('PE Filter Cards: Replacing traditional filters with prompt cards');
        
        // Clear existing content
        container.innerHTML = '';
        
        // Add scroll container with seamless styling
        const scrollContainer = document.createElement('div');
        scrollContainer.className = 'content-scroll';
        scrollContainer.style.cssText = 'height: 100vh; overflow-y: auto; overflow-x: hidden; scroll-behavior: smooth; scroll-snap-type: y proximity; background: #F5F2E8; padding: 0; margin: 0; -webkit-overflow-scrolling: touch; scrollbar-width: none;';
        container.appendChild(scrollContainer);
        
        // Load initial batch of cards
        this.loadMoreCards(scrollContainer);
        
        // Setup infinite scroll
        this.setupInfiniteScroll(scrollContainer);
        
        // Bind card interactions
        this.bindCardEvents();
        
        // Listen for filter events from main system
        this.setupFilterIntegration();
    }

    loadMoreCards(container) {
        const batch = this.allCards.slice(
            this.currentIndex, 
            this.currentIndex + this.cardsPerLoad
        );
        
        batch.forEach((card, index) => {
            const cardElement = this.createCardElement(card, this.currentIndex + index);
            container.appendChild(cardElement);
        });
        
        this.currentIndex += batch.length;
        
        // Loop back if we run out of cards
        if (this.currentIndex >= this.allCards.length) {
            this.currentIndex = 0;
            this.shuffleCards(); // Reshuffle for variety
        }
        
        console.log(`PE Filter Cards: Loaded ${batch.length} cards, total displayed: ${this.currentIndex}`);
    }

    createCardElement(card, index) {
        const cardDiv = document.createElement('div');
        cardDiv.className = 'question-card';
        cardDiv.dataset.cardId = card.id;
        cardDiv.dataset.filters = JSON.stringify(card.filters);
        
        // EXACT gradients from mockup - dark teal/navy colors
        const gradients = [
            'linear-gradient(135deg, #0d353e 0%, #1a5a65 100%)',
            'linear-gradient(145deg, #0d353e 0%, #2a6a75 100%)',
            'linear-gradient(125deg, #0d353e 0%, #1f5460 100%)',
            'linear-gradient(155deg, #0d353e 0%, #3a7a85 100%)'
        ];
        
        cardDiv.style.background = gradients[index % gradients.length];
        
        // Enhanced card content with more details
        const salaryInfo = card.salaryMin ? `£${card.salaryMin}k+` : '';
        const locationInfo = card.filters?.location ? card.filters.location.charAt(0).toUpperCase() + card.filters.location.slice(1) : '';
        const fundTypeInfo = card.filters?.fundType ? card.filters.fundType.charAt(0).toUpperCase() + card.filters.fundType.slice(1) : '';
        
        // Generate additional preview points based on category
        let bulletPoints = '';
        if (card.category.includes('SALARY') || card.category.includes('PAY') || card.category.includes('COMP')) {
            bulletPoints = `
                <ul class="question-bullets">
                    <li>Base salary expectations</li>
                    <li>Bonus & carry structure</li>
                    <li>Total compensation range</li>
                </ul>`;
        } else if (card.category.includes('LOCATION') || card.category.includes('LONDON')) {
            bulletPoints = `
                <ul class="question-bullets">
                    <li>Local market dynamics</li>
                    <li>Cost of living adjusted</li>
                    <li>Visa sponsorship available</li>
                </ul>`;
        } else if (card.category.includes('CAREER') || card.category.includes('EXIT') || card.category.includes('TRANSITION')) {
            bulletPoints = `
                <ul class="question-bullets">
                    <li>Required experience level</li>
                    <li>Typical progression path</li>
                    <li>Exit opportunities</li>
                </ul>`;
        } else {
            bulletPoints = `
                <ul class="question-bullets">
                    <li>Immediate opportunities</li>
                    <li>Market competitive roles</li>
                    <li>Growth potential</li>
                </ul>`;
        }
        
        cardDiv.innerHTML = `
            <div class="trending-badge">
                <div class="trending-icon">${card.category.substring(0, 1)}</div>
                <span class="trending-text">${card.category}</span>
            </div>
            
            <div class="question-content">
                <div class="question-category">${card.category}</div>
                <h2 class="question-title">${card.title}</h2>
                <p class="question-preview">${card.preview}</p>
                ${bulletPoints}
                
                <div class="filter-meta">
                    ${salaryInfo ? `<span class="meta-tag salary-tag">${salaryInfo}</span>` : ''}
                    ${locationInfo ? `<span class="meta-tag location-tag">${locationInfo}</span>` : ''}
                    ${fundTypeInfo ? `<span class="meta-tag type-tag">${fundTypeInfo}</span>` : ''}
                </div>
            </div>

            <div class="bottom-cta">
                <button class="ask-senna-btn" data-card-id="${card.id}">
                    <span>→</span>
                    <span>Apply This Filter</span>
                </button>
            </div>
        `;
        
        // Add subtle animation on appearance
        setTimeout(() => {
            cardDiv.style.opacity = '0';
            cardDiv.style.transform = 'translateY(20px)';
            setTimeout(() => {
                cardDiv.style.transition = 'all 0.5s ease';
                cardDiv.style.opacity = '1';
                cardDiv.style.transform = 'translateY(0)';
            }, 50);
        }, index * 100);
        
        return cardDiv;
    }

    setupInfiniteScroll(container) {
        let loading = false;
        
        container.addEventListener('scroll', () => {
            if (loading) return;
            
            const { scrollTop, scrollHeight, clientHeight } = container;
            const scrollPercentage = (scrollTop + clientHeight) / scrollHeight;
            
            // Load more when 80% scrolled
            if (scrollPercentage > 0.8) {
                loading = true;
                this.loadMoreCards(container);
                
                // Re-bind events for new cards
                setTimeout(() => {
                    this.bindCardEvents();
                    loading = false;
                }, 500);
            }
        });
    }

    bindCardEvents() {
        // Apply filter button clicks
        document.querySelectorAll('.ask-senna-btn').forEach(btn => {
            if (btn.dataset.bound) return; // Skip if already bound
            btn.dataset.bound = 'true';
            
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const cardId = e.target.dataset.cardId;
                const card = this.allCards.find(c => c.id === cardId);
                if (card) {
                    this.applyCardFilters(card);
                }
            });
        });
        
        // Card click for quick apply
        document.querySelectorAll('.question-card').forEach(card => {
            if (card.dataset.bound) return;
            card.dataset.bound = 'true';
            
            card.addEventListener('click', (e) => {
                if (e.target.classList.contains('ask-senna-btn')) return;
                
                const cardId = card.dataset.cardId;
                const cardData = this.allCards.find(c => c.id === cardId);
                if (cardData) {
                    this.applyCardFilters(cardData);
                }
            });
        });
    }

    applyCardFilters(card) {
        console.log('PE Filter Cards: Applying filters from card:', card.id, card.filters);
        
        // Show visual feedback
        this.showFilterFeedback(card.title);
        
        // Check if on mobile and switch to chat mode
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            // Switch to chat tab
            const chatTab = document.querySelector('[data-mode="chat"], .mode-pill[data-mode="chat"]');
            if (chatTab) {
                chatTab.click();
            }
            
            // Show chat interface
            const chatInterface = document.querySelector('#senna-chat-interface, .senna-chat-container, .chat-interface');
            if (chatInterface) {
                chatInterface.style.display = 'flex';
                setTimeout(() => {
                    chatInterface.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
            
            // Hide filter panel on mobile
            const filterPanel = document.querySelector('.pe-filter-sidebar, .filter-panel');
            if (filterPanel && isMobile) {
                filterPanel.style.display = 'none';
            }
        }
        
        // Apply filters with intelligent fallback
        this.applyFiltersWithIntelligentFallback(card.filters, card.title);
        
        // Track card usage
        this.trackCardUsage(card.id);
    }

    /**
     * Apply filters and provide intelligent fallback when no results
     */
    applyFiltersWithIntelligentFallback(filters, userQuery) {
        const chatController = window.sennaConversational;
        const peFilters = window.peFilters;
        
        if (!chatController || !chatController.allJobs) {
            console.warn('Jobs not loaded yet');
            if (chatController) {
                chatController.addSennaMessage('Please wait while I load available opportunities...');
                // Try again after a delay
                setTimeout(() => this.applyFiltersWithIntelligentFallback(filters, userQuery), 2000);
            }
            return;
        }
        
        // Apply filters to PE system first
        if (peFilters) {
            // Update active filters
            Object.entries(filters).forEach(([key, value]) => {
                if (peFilters.activeFilters[key] !== undefined) {
                    if (key === 'fundType') {
                        peFilters.activeFilters[key] = Array.isArray(value) ? value : [value];
                    } else {
                        peFilters.activeFilters[key] = value;
                    }
                }
            });
        }
        
        // Filter jobs
        const filteredJobs = chatController.allJobs.filter(job => {
            if (!peFilters || !peFilters.matchesFilters) return true;
            return peFilters.matchesFilters(job);
        });
        
        console.log(`Filtered ${filteredJobs.length} jobs from ${chatController.allJobs.length} total`);
        
        if (filteredJobs.length > 0) {
            // We have results - show jobs AND advice
            this.displayJobsWithAdvice(filteredJobs, filters, userQuery);
        } else {
            // No results - provide intelligent Claude-powered advice
            this.provideNoResultsAdvice(filters, userQuery);
        }
    }

    /**
     * Display filtered jobs with advice on how to land them
     */
    displayJobsWithAdvice(jobs, filters, userQuery) {
        const chatController = window.sennaConversational;
        if (!chatController) return;
        
        // Add success message
        chatController.addSennaMessage(`Found ${jobs.length} opportunities matching "${userQuery}":`);
        
        // Display the jobs
        setTimeout(() => {
            chatController.renderJobsInChat(jobs, true);
            
            // After jobs are displayed, add advice
            setTimeout(() => {
                this.generateLandingAdvice(jobs, filters);
            }, 500);
        }, 300);
    }

    /**
     * Generate advice on how to land the shown jobs
     */
    generateLandingAdvice(jobs, filters) {
        const chatController = window.sennaConversational;
        if (!chatController || !chatController.addSennaMessage) return;
        
        let advicePrompt = '';
        
        // Add specific advice based on filter type
        if (filters.seniority === 'analyst' || filters.seniority === 'entry') {
            advicePrompt = `💡 **Tips for landing these entry-level roles:**\n\n` +
                `• Strong technical skills: Excel modeling, PowerPoint, and financial analysis\n` +
                `• Relevant internships at investment banks or PE firms\n` +
                `• CFA Level 1 or working towards it shows commitment\n` +
                `• Network with alumni working in PE\n` +
                `• Prepare for technical interviews and case studies`;
        } else if (filters.seniority === 'associate') {
            advicePrompt = `💡 **Tips for landing these Associate positions:**\n\n` +
                `• 2-3 years of investment banking or consulting experience\n` +
                `• Demonstrated deal execution experience\n` +
                `• Sector expertise in relevant industries\n` +
                `• Strong financial modeling and valuation skills\n` +
                `• MBA from top-tier school (for some roles)`;
        } else if (filters.seniority === 'vp' || filters.seniority === 'principal') {
            advicePrompt = `💡 **Tips for landing these senior positions:**\n\n` +
                `• 5-8 years of PE/IB experience with deal leadership\n` +
                `• Track record of successful investments\n` +
                `• Strong network for sourcing deals\n` +
                `• Sector specialization and expertise\n` +
                `• Board experience is highly valued`;
        } else if (filters.location) {
            const locationName = this.formatLocation(filters.location);
            advicePrompt = `💡 **Tips for ${locationName} opportunities:**\n\n` +
                `• Research local PE market dynamics\n` +
                `• Understand regional deal flow patterns\n` +
                `• Network with local PE professionals\n` +
                `• Consider language requirements\n` +
                `• Be prepared to relocate and discuss visa if needed`;
        } else {
            advicePrompt = `💡 **How to strengthen your application:**\n\n` +
                `• Tailor your CV for each specific role\n` +
                `• Highlight relevant deal experience\n` +
                `• Quantify your achievements and impact\n` +
                `• Get warm introductions through your network\n` +
                `• Follow up professionally after applying`;
        }
        
        chatController.addSennaMessage(advicePrompt);
    }

    /**
     * Provide intelligent advice when no jobs match filters
     */
    provideNoResultsAdvice(filters, userQuery) {
        const chatController = window.sennaConversational;
        if (!chatController) return;
        
        // Create a properly formatted container based on filter type
        const analysisHtml = this.createFilterAnalysisContainer(filters, userQuery);
        
        // Add as HTML message to chat
        if (chatController.addSennaMessage) {
            // Create message with HTML content
            const messageDiv = document.createElement('div');
            messageDiv.innerHTML = analysisHtml;
            
            // Add to messages container
            const messagesContainer = document.getElementById('senna-messages');
            if (messagesContainer) {
                const messageWrapper = document.createElement('div');
                messageWrapper.className = 'message senna-message';
                messageWrapper.innerHTML = `
                    <div class="message-avatar">
                        <div class="avatar-icon">S</div>
                    </div>
                    <div class="message-content">
                        ${analysisHtml}
                    </div>
                `;
                messagesContainer.appendChild(messageWrapper);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }
        
        // If we have Claude API access, get more personalized advice
        if (window.sffc_ajax && (filters.seniority || filters.location)) {
            setTimeout(() => {
                this.requestClaudeAdvice(filters, userQuery);
            }, 1500);
        }
    }

    /**
     * Create properly formatted analysis container for filter results
     */
    createFilterAnalysisContainer(filters, userQuery) {
        // Determine the main filter type for dynamic header
        let headerTitle = 'Private Equity Opportunities Analysis';
        let headerSubtitle = userQuery;
        let quickInfo = [];
        let sections = [];
        
        // Build dynamic content based on filter type
        if (filters.location && filters.seniority) {
            // Location + Seniority combo
            const locationName = this.formatLocation(filters.location);
            const seniorityName = this.formatSeniority(filters.seniority);
            
            headerTitle = `${seniorityName} Roles - ${locationName} Market`;
            quickInfo = [locationName, seniorityName, 'Market Analysis'];
            
            sections = [
                {
                    title: 'Market Overview',
                    content: `<p>The ${locationName} market for ${seniorityName}-level positions is highly competitive. Key players include both global mega-funds and regional specialists.</p>
                        <ul class="requirements-list">
                            <li>Average compensation: £${this.getSalaryRange(filters.seniority)}</li>
                            <li>Typical fund size: £${this.getFundSizeRange(locationName)}</li>
                            <li>Deal focus: ${this.getDealFocus(locationName)}</li>
                            <li>Language requirements: ${this.getLanguageReqs(filters.location)}</li>
                        </ul>`
                },
                {
                    title: 'Top Firms to Target',
                    content: `<ul class="requirements-list">
                        ${this.getTopFirms(filters.location, filters.seniority)}
                    </ul>`
                },
                {
                    title: 'Breaking In Strategy',
                    content: `<p>${this.getBreakingInStrategy(filters.seniority, filters.location)}</p>`
                },
                {
                    title: 'Networking Approach',
                    content: `<ul class="requirements-list">
                        <li>Join ${locationName} Private Equity Association</li>
                        <li>Attend BVCA/EVCA events in ${locationName}</li>
                        <li>Connect with headhunters: ${this.getHeadhunters(filters.location)}</li>
                        <li>Alumni networks from top business schools</li>
                    </ul>`
                }
            ];
            
        } else if (filters.seniority === 'analyst' && (filters.salaryMin >= 60 || filters.location)) {
            // High-paying entry-level focus
            headerTitle = 'High-Paying Entry-Level Finance Roles';
            quickInfo = ['Entry Level', `£${filters.salaryMin || 60}k+`, 'Career Path'];
            
            sections = [
                {
                    title: 'Private Equity Analyst Roles',
                    content: `<ul class="requirements-list">
                        <li><strong>Blackstone/KKR/Apollo:</strong> £70-85k base + bonus</li>
                        <li><strong>Mid-market funds:</strong> £60-75k base + bonus</li>
                        <li><strong>Growth equity:</strong> £65-80k base + bonus</li>
                        <li><strong>Required:</strong> 2 years IB analyst experience at bulge bracket</li>
                    </ul>`
                },
                {
                    title: 'Private Credit Opportunities',
                    content: `<ul class="requirements-list">
                        <li><strong>Millennium/Citadel:</strong> £75-90k base + performance</li>
                        <li><strong>Point72/Balyasny:</strong> £70-85k base + bonus</li>
                        <li><strong>Required:</strong> Strong quantitative skills, Python/R proficiency</li>
                        <li><strong>Best path:</strong> S&T or equity research background</li>
                    </ul>`
                },
                {
                    title: 'Asset Management Programs',
                    content: `<ul class="requirements-list">
                        <li><strong>BlackRock Analyst Program:</strong> £55-65k + benefits</li>
                        <li><strong>Fidelity Graduate Scheme:</strong> £50-60k + rotation</li>
                        <li><strong>Schroders/M&G:</strong> £48-58k + training</li>
                        <li><strong>Direct entry:</strong> From university with internships</li>
                    </ul>`
                },
                {
                    title: 'Your Action Plan',
                    content: `<p><strong>Immediate steps to take:</strong></p>
                        <ol class="requirements-list">
                            <li>Apply to bulge bracket IB analyst programs (gateway to PE)</li>
                            <li>Network with PE professionals via LinkedIn</li>
                            <li>Start CFA Level 1 preparation</li>
                            <li>Build financial modeling skills (Wall Street Prep)</li>
                            <li>Join finance societies and PE clubs</li>
                        </ol>`
                }
            ];
            
        } else if (filters.fundSize === 'mega') {
            // Mega fund focus
            headerTitle = 'Breaking into Mega Funds';
            const seniorityName = filters.seniority ? this.formatSeniority(filters.seniority) : 'All Levels';
            quickInfo = ['Mega Funds', '$10bn+ AUM', seniorityName];
            
            sections = [
                {
                    title: 'Target Firms',
                    content: `<ul class="requirements-list">
                        <li><strong>Mubadala Capital:</strong> large-scale regional platform with global reach</li>
                        <li><strong>KKR:</strong> $500bn AUM, Global presence</li>
                        <li><strong>Apollo:</strong> $650bn AUM, Credit + Buyouts</li>
                        <li><strong>Carlyle:</strong> $400bn AUM, Sector specialists</li>
                        <li><strong>TPG:</strong> $200bn AUM, Growth + Impact</li>
                    </ul>`
                },
                {
                    title: 'Requirements by Level',
                    content: this.getMegaFundRequirements(filters.seniority)
                },
                {
                    title: 'Interview Process',
                    content: `<p><strong>What to expect:</strong></p>
                        <ul class="requirements-list">
                            <li>4-6 rounds including modeling test</li>
                            <li>Case study presentation to partners</li>
                            <li>Deal experience deep dive</li>
                            <li>Culture fit with multiple team members</li>
                            <li>References from deal partners</li>
                        </ul>`
                },
                {
                    title: 'Preparation Strategy',
                    content: `<ol class="requirements-list">
                        <li>Master LBO modeling (sub-20 minute tests)</li>
                        <li>Prepare 3-5 deal stories with clear impact</li>
                        <li>Research fund's recent transactions</li>
                        <li>Network with current employees</li>
                        <li>Work with PE-focused headhunters</li>
                    </ol>`
                }
            ];
            
        } else if (filters.location) {
            // Location-specific analysis
            const locationName = this.formatLocation(filters.location);
            headerTitle = `${locationName} Private Equity Market`;
            quickInfo = [locationName, 'All Levels', 'Market Guide'];
            
            sections = [
                {
                    title: `${locationName} Market Overview`,
                    content: this.getLocationMarketOverview(filters.location)
                },
                {
                    title: 'Major Players',
                    content: `<ul class="requirements-list">
                        ${this.getLocationMajorPlayers(filters.location)}
                    </ul>`
                },
                {
                    title: 'Compensation Ranges',
                    content: this.getLocationCompensation(filters.location)
                },
                {
                    title: 'Getting Started',
                    content: this.getLocationGettingStarted(filters.location)
                }
            ];
            
        } else {
            // Generic but helpful analysis
            headerTitle = 'Private Equity Career Opportunities';
            quickInfo = ['All Markets', 'All Levels', 'Career Guide'];
            
            sections = [
                {
                    title: 'Current Market Trends',
                    content: `<ul class="requirements-list">
                        <li>Increased focus on operational value creation</li>
                        <li>ESG becoming core to investment thesis</li>
                        <li>Technology sector remains hot despite corrections</li>
                        <li>Healthcare and infrastructure seeing increased allocations</li>
                        <li>Dry powder at record levels - deployment pressure high</li>
                    </ul>`
                },
                {
                    title: 'Skills in Demand',
                    content: `<ul class="requirements-list">
                        <li><strong>Technical:</strong> Advanced modeling, data analysis, sector expertise</li>
                        <li><strong>Commercial:</strong> Sourcing, relationship management, board experience</li>
                        <li><strong>Operational:</strong> Portfolio company improvement, digital transformation</li>
                        <li><strong>Soft skills:</strong> Leadership, communication, cultural fit</li>
                    </ul>`
                },
                {
                    title: 'Alternative Paths to Consider',
                    content: `<p>If traditional PE isn't matching, consider:</p>
                        <ul class="requirements-list">
                            <li><strong>Growth Equity:</strong> Less leverage, tech focus</li>
                            <li><strong>Venture Capital:</strong> Early-stage investing</li>
                            <li><strong>Credit Funds:</strong> Debt investing strategies</li>
                            <li><strong>Fund of Funds:</strong> LP perspective</li>
                            <li><strong>Corporate Development:</strong> Strategic M&A</li>
                        </ul>`
                }
            ];
        }
        
        // Build the HTML
        return `
            <div class="job-analysis-container" style="max-width: 100%; animation: slideIn 0.4s ease;">
                <div class="job-analysis-header">
                    <h3>${headerTitle}</h3>
                    <div class="job-quick-info">
                        ${quickInfo.map(tag => `<span class="info-tag">${tag}</span>`).join('')}
                    </div>
                </div>
                
                ${sections.map(section => `
                    <div class="job-analysis-section">
                        <h4>${section.title}</h4>
                        ${section.content}
                    </div>
                `).join('')}
                
                <div class="job-action-buttons">
                    <button class="action-btn primary-action" onclick="window.peFilterCardsSystem.adjustFilters('${JSON.stringify(filters)}')">
                        <span>🔄</span>
                        <span>Adjust Search Criteria</span>
                    </button>
                    <button class="action-btn secondary-action" onclick="window.peFilterCardsSystem.showAllOpportunities()">
                        <span>👀</span>
                        <span>View All Available Roles</span>
                    </button>
                </div>
            </div>
        `;
    }

    // Helper methods for dynamic content generation
    getSalaryRange(seniority) {
        const ranges = {
            'analyst': '60-85k base',
            'associate': '90-130k base',
            'vp': '150-250k base',
            'principal': '250-400k base',
            'partner': '500k+ base'
        };
        return ranges[seniority] || '60-500k+ depending on level';
    }

    getFundSizeRange(location) {
        const ranges = {
            'London': '250m-50bn+',
            'Paris': '150m-25bn',
            'Milan': '100m-15bn',
            'Amsterdam': '100m-10bn',
            'Dubai': '100m-20bn'
        };
        return ranges[location] || '100m-50bn';
    }

    getDealFocus(location) {
        const focus = {
            'London': 'Large-cap buyouts, sponsor-led processes, pan-European execution',
            'Paris': 'Institutional private capital, French buyouts, cross-border investing',
            'Milan': 'Founder-led buyouts, industrial mid-market, family-backed capital',
            'Amsterdam': 'Pan-European platforms, growth capital, cross-border funds',
            'Dubai': 'Cross-border buyouts, DIFC platforms, regional holding groups'
        };
        return focus[location] || 'Regional and sector-specific';
    }

    getLanguageReqs(location) {
        const reqs = {
            'london': 'English essential',
            'paris': 'English essential; French often helps',
            'milan': 'English essential; Italian can help on local mandates',
            'amsterdam': 'English essential',
            'dubai': 'English essential; Arabic helpful for relationship-led mandates',
            'global': 'English',
            'other': 'English + local language'
        };
        return reqs[location] || 'English + local language';
    }

    getTopFirms(location, seniority) {
        const locationFirms = {
            'london': ['<li>CVC</li>', '<li>Cinven</li>', '<li>Permira</li>', '<li>Bridgepoint</li>', '<li>European mid-market funds</li>'],
            'paris': ['<li>Ardian</li>', '<li>PAI Partners</li>', '<li>Eurazeo</li>', '<li>Astorg</li>', '<li>French mid-market investors</li>'],
            'milan': ['<li>Clessidra</li>', '<li>Investindustrial</li>', '<li>Italian mid-market funds</li>', '<li>family-backed investors</li>'],
            'amsterdam': ['<li>Waterland</li>', '<li>3i Benelux teams</li>', '<li>Dutch lower mid-market funds</li>', '<li>growth equity platforms</li>'],
            'dubai': ['<li>Investcorp</li>', '<li>Gulf Capital</li>', '<li>NBK Capital Partners</li>', '<li>Waha Capital</li>', '<li>regional family offices</li>']
        };
        return (locationFirms[location] || ['<li>Research local and international funds</li>']).join('');
    }

    getBreakingInStrategy(seniority, location) {
        if (seniority === 'analyst') {
            return `Start with 2 years at a strong investment bank, sovereign-linked platform, or corporate finance team in ${this.formatLocation(location)}. Focus on M&A, sponsor coverage, or transaction-heavy finance work. Network early with private equity recruiters and regional investment professionals.`;
        } else if (seniority === 'associate') {
            return `Leverage your banking or consulting experience. Highlight closed deals and sector expertise. Consider an MBA from a top European or US school if coming from non-traditional background. Work with specialized PE headhunters.`;
        } else if (seniority === 'vp' || seniority === 'principal') {
            return `Demonstrate deal leadership and sourcing capabilities. Build relationships with portfolio company executives. Show clear value creation track record. Consider lateral moves through executive search firms.`;
        }
        return `Build relevant experience in investment banking, consulting, or corporate development. Network actively within the ${this.formatLocation(location)} PE community. Consider starting at smaller funds or in related roles.`;
    }

    getHeadhunters(location) {
        const headhunters = {
            'london': 'PER, Dartmouth Partners, KEA Consultants, specialist PE recruiters',
            'paris': 'European private capital search firms and local buy-side recruiters',
            'milan': 'Italian finance search firms and pan-European PE recruiters',
            'amsterdam': 'Benelux private capital recruiters and cross-border search firms',
            'dubai': 'PER, KEA Consultants, regional private markets recruiters'
        };
        return headhunters[location] || 'Local and international executive search firms';
    }

    getMegaFundRequirements(seniority) {
        const reqs = {
            'analyst': `<ul class="requirements-list">
                <li>2-3 years at Goldman Sachs/Morgan Stanley/JPMorgan</li>
                <li>Multiple closed M&A or sponsor deals</li>
                <li>Perfect academic record from target school</li>
                <li>Demonstrated leadership and analytical excellence</li>
            </ul>`,
            'associate': `<ul class="requirements-list">
                <li>2-4 years IB + Top MBA or 4-5 years elite IB</li>
                <li>Deal execution across multiple sectors</li>
                <li>Strong financial modeling and presentation skills</li>
                <li>Sponsor coverage experience highly valued</li>
            </ul>`,
            'vp': `<ul class="requirements-list">
                <li>6-8 years with 3+ in PE at strong fund</li>
                <li>Track record of successful investments</li>
                <li>Demonstrated sourcing capabilities</li>
                <li>Board observation or participation experience</li>
            </ul>`,
            'principal': `<ul class="requirements-list">
                <li>8-12 years with 5+ in PE leadership roles</li>
                <li>Multiple successful exits with strong returns</li>
                <li>Established network for deal sourcing</li>
                <li>Board director experience required</li>
            </ul>`
        };
        return reqs[seniority] || `<ul class="requirements-list">
            <li>Elite background in IB/Consulting/PE</li>
            <li>Exceptional track record and credentials</li>
            <li>Strong cultural fit with partnership</li>
            <li>Differentiated skills or network</li>
        </ul>`;
    }

    getLocationMarketOverview(location) {
        const overviews = {
            'dubai': '<p>Dubai remains the regional bridge market for private equity, investment banking, asset management and family-office platforms, with DIFC continuing to attract international firms.</p>',
            'london': '<p>London remains the deepest market for private equity recruiting, sponsor coverage, deal execution and buy-side lateral movement. It still offers the strongest plan-B depth if the first role is not the right fit.</p>',
            'paris': '<p>Paris combines institutional capital, large-cap and mid-market buyout activity, and a growing cross-border private capital scene, especially for candidates with strong execution skills.</p>',
            'milan': '<p>Milan offers a concentrated mid-market and founder-led private equity ecosystem, with stronger opportunities for candidates who can combine deal execution with commercial judgement.</p>',
            'global': '<p>Private equity careers across London, Europe, and Dubai span buyout, growth equity, asset management, investment banking, corporate finance, and portfolio operations. Focus on market fit, deal experience, and recruiter coverage.</p>'
        };
        return overviews[location] || '<p>Developing PE market with growing opportunities. Research local funds and international firms with regional offices.</p>';
    }

    getLocationMajorPlayers(location) {
        const players = {
            'dubai': '<li>Investcorp</li><li>Gulf Capital</li><li>NBK Capital Partners</li><li>Waha Capital</li>',
            'london': '<li>CVC</li><li>Cinven</li><li>Permira</li><li>Bridgepoint</li>',
            'paris': '<li>Ardian</li><li>PAI Partners</li><li>Eurazeo</li><li>Astorg</li>',
            'global': '<li>CVC</li><li>Ardian</li><li>Bridgepoint</li><li>Investcorp</li>'
        };
        return players[location] || '<li>Research local funds</li><li>Check international funds with local offices</li>';
    }

    getLocationCompensation(location) {
        const comp = {
            'dubai': `<ul class="requirements-list">
                <li><strong>Analyst:</strong> AED 25k-40k / month + bonus</li>
                <li><strong>Associate:</strong> AED 40k-70k / month + bonus</li>
                <li><strong>VP:</strong> AED 70k-120k / month + bonus</li>
                <li><strong>Principal:</strong> AED 120k+ / month + carry potential</li>
            </ul>`,
            'london': `<ul class="requirements-list">
                <li><strong>Analyst:</strong> £70k-100k base + bonus</li>
                <li><strong>Associate:</strong> £110k-180k base + bonus</li>
                <li><strong>VP:</strong> £180k-300k base + bonus</li>
                <li><strong>Principal:</strong> £300k+ plus carry potential</li>
            </ul>`,
            'paris': `<ul class="requirements-list">
                <li><strong>Analyst:</strong> Competitive Euro base + bonus</li>
                <li><strong>Associate:</strong> Strong mid-market Euro package + bonus</li>
                <li><strong>VP:</strong> Senior private capital package depending on platform</li>
                <li><strong>Principal:</strong> Platform-specific economics and carry</li>
            </ul>`
        };
        return comp[location] || `<p>Compensation varies by fund size, market, and platform. Research local market rates across London, Paris, Milan, Amsterdam, and Dubai rather than assuming one benchmark fits every mandate.</p>`;
    }

    getLocationGettingStarted(location) {
        const locationName = this.formatLocation(location);
        return `<ol class="requirements-list">
            <li>Research all PE funds with ${locationName} offices</li>
            <li>Network via local PE/finance associations</li>
            <li>Connect with headhunters specializing in ${locationName}</li>
            <li>Consider language courses if needed</li>
            <li>Understand visa/work permit requirements</li>
            <li>Join ${locationName} finance professional groups on LinkedIn</li>
        </ol>`;
    }

    // Add methods to handle button clicks
    adjustFilters(filtersJson) {
        const filters = JSON.parse(filtersJson);
        // Open filter panel or adjust current filters
        console.log('Adjusting filters:', filters);
        // You can implement filter adjustment UI here
    }

    showAllOpportunities() {
        if (window.peFilters) {
            window.peFilters.clearAllFilters();
        }
        if (window.sennaConversational) {
            window.sennaConversational.addUserMessage('Show me all available opportunities');
        }
    }

    /**
     * Request personalized advice from Claude API
     */
    requestClaudeAdvice(filters, userQuery) {
        const chatController = window.sennaConversational;
        
        // Build context for Claude
        let context = `User is looking for: ${userQuery}\n`;
        context += `Filters: ${JSON.stringify(filters)}\n`;
        context += `No direct matches found in current job listings.\n`;
        context += `Please provide specific, actionable advice for breaking into this type of role in Private Equity, Private Credit, or Asset Management.`;
        context += `Include salary ranges, required experience, and specific firms to target.`;
        
        // Make AJAX call to Claude
        jQuery.ajax({
            url: window.sffc_ajax.url,
            type: 'POST',
            data: {
                action: 'sffc_analyze_job_with_claude',
                nonce: window.sffc_ajax.nonce,
                prompt: context,
                context_type: 'career_advice'
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Add Claude's personalized advice
                    setTimeout(() => {
                        chatController.addSennaMessage(`🤖 **Personalized Career Advice:**\n\n${response.data}`);
                    }, 1000);
                }
            },
            error: function(err) {
                console.log('Could not get Claude advice:', err);
            }
        });
    }

    /**
     * Helper function to format location names
     */
    formatLocation(location) {
        const locationMap = {
            'dubai': 'Dubai',
            'london': 'London',
            'paris': 'Paris',
            'milan': 'Milan',
            'amsterdam': 'Amsterdam',
            'global': 'Global'
        };
        return locationMap[location] || location.charAt(0).toUpperCase() + location.slice(1);
    }

    /**
     * Helper function to format seniority names
     */
    formatSeniority(seniority) {
        const seniorityMap = {
            'analyst': 'Analyst',
            'associate': 'Associate',
            'vp': 'VP',
            'principal': 'Principal',
            'partner': 'Partner',
            'director': 'Director',
            'md': 'Managing Director'
        };
        return seniorityMap[seniority] || seniority.charAt(0).toUpperCase() + seniority.slice(1);
    }

    showFilterFeedback(title) {
        // Remove any existing toast
        const existingToast = document.querySelector('.filter-applied-toast');
        if (existingToast) existingToast.remove();
        
        // Create new toast
        const toast = document.createElement('div');
        toast.className = 'filter-applied-toast';
        toast.innerHTML = `
            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">Filter Applied:</div>
            <div style="font-size: 14px;">${title}</div>
        `;
        document.body.appendChild(toast);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            toast.style.animation = 'slideDown 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    triggerFilterUpdate(filters) {
        // This is now handled by applyFiltersWithIntelligentFallback
        // Kept for compatibility
        const event = new CustomEvent('promptFilterApplied', {
            detail: filters
        });
        document.dispatchEvent(event);
    }

    filterJobsDirectly(filters) {
        // Find all job cards in the chat
        const jobCards = document.querySelectorAll('.job-card-vogue, .sffc-match-card');
        let visibleCount = 0;
        
        jobCards.forEach(card => {
            let shouldShow = true;
            
            // Check each filter criterion
            if (filters.seniority) {
                const cardText = card.textContent.toLowerCase();
                const seniorityMatch = cardText.includes(filters.seniority.toLowerCase());
                shouldShow = shouldShow && seniorityMatch;
            }
            
            if (filters.location) {
                const cardText = card.textContent.toLowerCase();
                const locationMatch = cardText.includes(filters.location.toLowerCase());
                shouldShow = shouldShow && locationMatch;
            }
            
            if (filters.salaryMin) {
                const salaryText = card.querySelector('.sffc-salary')?.textContent || '';
                const salaryMatch = this.checkSalaryMatch(salaryText, filters.salaryMin);
                shouldShow = shouldShow && salaryMatch;
            }
            
            // Apply visibility
            if (shouldShow) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        console.log(`PE Filter Cards: Filtered to ${visibleCount} visible jobs`);
    }

    checkSalaryMatch(salaryText, minSalary) {
        // Extract salary number from text
        const match = salaryText.match(/(\d+)k?/i);
        if (match) {
            const salary = parseInt(match[1]);
            return salary >= minSalary;
        }
        return true; // Show if no salary info
    }

    trackCardUsage(cardId) {
        // Store in localStorage for personalization
        const usage = JSON.parse(localStorage.getItem('peCardUsage') || '{}');
        usage[cardId] = (usage[cardId] || 0) + 1;
        usage.lastUsed = Date.now();
        localStorage.setItem('peCardUsage', JSON.stringify(usage));
    }

    setupFilterIntegration() {
        // Listen for quick filter clicks from stories bar
        document.addEventListener('quickFilterApplied', (e) => {
            const filterType = e.detail;
            // Find relevant cards for this quick filter
            const relevantCards = this.allCards.filter(card => {
                if (filterType === '90plus') return card.filters.salaryMin >= 90;
                if (filterType === 'nearby') return card.filters.location === 'london';
                if (filterType === 'recent') return card.trending;
                if (filterType === 'largecap') return card.filters.fundSize === 'large';
                if (filterType === 'normal') return card.filters.workStyle === 'normal';
                return true;
            });
            
            if (relevantCards.length > 0) {
                // Scroll to first matching card
                const firstMatch = document.querySelector(`[data-card-id="${relevantCards[0].id}"]`);
                if (firstMatch) {
                    firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
}

// Export the class for manual initialization
window.PEFilterCardsSystem = PEFilterCardsSystem;

// Auto-initialize if not already done
if (!window.peFilterCardsSystem) {
    // Wait a bit to ensure DOM is ready
    setTimeout(() => {
        if (!window.peFilterCardsSystem && document.querySelector('.pe-main-filters')) {
            window.peFilterCardsSystem = new PEFilterCardsSystem();
            console.log('PE Filter Cards System: Auto-initialized with 200+ randomized cards');
        }
    }, 1000);
}
