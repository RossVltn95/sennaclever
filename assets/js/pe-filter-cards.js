/**
 * PE Filter Prompt Cards
 * Version: 1.0.0
 * Description: Comprehensive prompt-based filtering system for PE jobs
 */

const filterPromptCards = [
    // MARKET INTELLIGENCE CARDS
    {
        id: 'top-firms-london',
        category: 'MARKET INTELLIGENCE',
        title: 'Top 10 PE firms in London',
        preview: 'Discover the leading private equity players dominating London\'s financial landscape.',
        filters: { location: 'london', fundSize: 'mega' },
        trending: true
    },
    {
        id: 'rising-boutiques',
        category: 'MARKET INTELLIGENCE',
        title: 'Rising boutiques in European Growth Equity',
        preview: 'Emerging funds making waves in the growth equity space across Europe.',
        filters: { fundType: 'growth', fundSize: 'small' }
    },
    {
        id: 'hiring-trends',
        category: 'MARKET INTELLIGENCE',
        title: 'Who\'s hiring: Q1 2025 PE recruitment trends',
        preview: 'Current hiring patterns and hot sectors in private equity recruitment.',
        filters: { seniority: 'associate', geoFocus: 'pan-european' }
    },
    {
        id: 'comp-breakdown',
        category: 'MARKET INTELLIGENCE',
        title: 'Mega-funds vs Mid-market: Compensation breakdown',
        preview: 'Detailed analysis of pay scales across different fund sizes.',
        filters: { fundSize: 'mega', salaryMin: 150 }
    },
    
    // CAREER STRATEGIES CARDS
    {
        id: 'pe-associate-entry',
        category: 'CAREER STRATEGIES',
        title: 'How to land a private equity associate role',
        preview: 'Insider guide to positioning your CV, deal experience, and recruiter outreach.',
        filters: { fundType: 'buyout', seniority: 'associate' },
        trending: true
    },
    {
        id: 'pe-roadmap',
        category: 'CAREER STRATEGIES',
        title: 'Breaking into PE: The 3-year roadmap',
        preview: 'Proven pathway from university to your first PE role.',
        filters: { seniority: 'analyst', fundType: 'growth' }
    },
    {
        id: 'big4-to-pe',
        category: 'CAREER STRATEGIES',
        title: 'From Big 4 to PE: Success stories',
        preview: 'How consultants successfully transition into private equity.',
        filters: { seniority: 'associate', fundSize: 'mid' }
    },
    {
        id: 'burnout-prevention',
        category: 'CAREER STRATEGIES',
        title: 'Why 73% of Associates burn out (and how to avoid it)',
        preview: 'Mental health strategies for sustainable PE careers.',
        filters: { seniority: 'associate', salaryMin: 100 }
    },

    // COMPENSATION INSIGHTS CARDS
    {
        id: 'comp-report-2024',
        category: 'COMPENSATION INSIGHTS',
        title: '2024 PE compensation report: London',
        preview: 'Complete breakdown of salaries, bonuses, and carried interest.',
        filters: { location: 'london', salaryMin: 120 },
        trending: true
    },
    {
        id: 'carried-interest',
        category: 'COMPENSATION INSIGHTS',
        title: 'Carried interest explained in 2 minutes',
        preview: 'Understanding the golden ticket of PE compensation.',
        filters: { seniority: 'principal', salaryMin: 200 }
    },
    {
        id: 'negotiation-timing',
        category: 'COMPENSATION INSIGHTS',
        title: 'When to negotiate: Timing your ask',
        preview: 'Strategic approach to salary negotiations in PE.',
        filters: { seniority: 'vp', fundSize: 'large' }
    },
    {
        id: 'hidden-perks',
        category: 'COMPENSATION INSIGHTS',
        title: 'Hidden perks in PE contracts',
        preview: 'Co-invest rights, insurance, and benefits you should know about.',
        filters: { fundSize: 'mega', salaryMin: 150 }
    },
    {
        id: 'excel-shortcuts',
        category: 'SKILL BUILDERS',
        title: '5 Excel shortcuts every PE analyst needs',
        preview: 'Master the technical skills that set you apart.',
        filters: { seniority: 'analyst', fundType: 'buyout' }
    },
    {
        id: 'lbo-mistakes',
        category: 'SKILL BUILDERS',
        title: 'The LBO model mistake 90% make',
        preview: 'Common pitfalls in leveraged buyout modeling.',
        filters: { seniority: 'associate', fundType: 'buyout' }
    },

    // SKILL BUILDERS CONTINUED
    {
        id: 'lp-reports',
        category: 'SKILL BUILDERS',
        title: 'Reading between the lines: LP reports',
        preview: 'How to analyze limited partner communications.',
        filters: { seniority: 'vp', fundSize: 'large' },
        trending: true
    },
    {
        id: 'networking-tactics',
        category: 'SKILL BUILDERS',
        title: 'Networking tactics that actually work',
        preview: 'Build meaningful connections in the PE industry.',
        filters: { seniority: 'associate', geoFocus: 'pan-european' }
    },
    {
        id: 'singapore-guide',
        category: 'REGIONAL GUIDES',
        title: 'PE in Singapore: The complete guide',
        preview: 'Asia\'s financial hub and gateway to Southeast Asian markets.',
        filters: { location: 'singapore', fundType: 'growth' }
    },

    // REGIONAL GUIDES CONTINUED
    {
        id: 'milan-hub',
        category: 'REGIONAL GUIDES',
        title: 'Why Milan is Europe\'s hidden PE hub',
        preview: 'Italy\'s financial capital and its growing PE ecosystem.',
        filters: { location: 'milan', fundType: 'growth' }
    },
    {
        id: 'pe-myths',
        category: 'CAREER STRATEGIES',
        title: 'Private equity recruiting: Myths vs Reality',
        preview: 'The truth about getting hired by private equity funds.',
        filters: { fundType: 'buyout', salaryMin: 100 }
    },
    {
        id: 'frankfurt-brexit',
        category: 'REGIONAL GUIDES',
        title: 'Frankfurt after Brexit: Opportunities',
        preview: 'How Germany\'s financial center has evolved post-Brexit.',
        filters: { location: 'frankfurt', fundSize: 'large' }
    },
    {
        id: 'ai-impact',
        category: 'INDUSTRY TRENDS',
        title: 'AI\'s impact on due diligence',
        preview: 'How artificial intelligence is transforming PE deal analysis.',
        filters: { fundType: 'growth', seniority: 'associate' }
    },
    {
        id: 'esg-buyout',
        category: 'INDUSTRY TRENDS',
        title: 'ESG: From buzzword to buyout criteria',
        preview: 'Environmental and social factors driving investment decisions.',
        filters: { fundType: 'buyout', fundSize: 'mega' }
    },

    // INDUSTRY TRENDS CONTINUED
    {
        id: 'continuation-funds',
        category: 'INDUSTRY TRENDS',
        title: 'The rise of continuation funds',
        preview: 'GP-led secondaries reshaping PE exit strategies.',
        filters: { fundSize: 'large', seniority: 'principal' },
        trending: true
    },
    {
        id: 'dry-powder',
        category: 'INDUSTRY TRENDS',
        title: 'Why dry powder hit $3.9 trillion',
        preview: 'Record capital waiting to be deployed and what it means.',
        filters: { fundType: 'buyout', geoFocus: 'pan-european' }
    },
    {
        id: 'carlyle-principal',
        category: 'INSIDER STORIES',
        title: 'Day in the life: Carlyle Principal',
        preview: 'Inside look at a typical day at a top-tier PE firm.',
        filters: { seniority: 'principal', fundSize: 'mega' }
    },

    // INSIDER STORIES CONTINUED
    {
        id: 'deal-changed-pe',
        category: 'INSIDER STORIES',
        title: 'The deal that changed European PE',
        preview: 'How one mega-buyout reshaped the industry landscape.',
        filters: { fundSize: 'mega', geoFocus: 'pan-european' }
    },
    {
        id: 'interview-horror',
        category: 'INSIDER STORIES',
        title: 'Interview horror stories (and lessons)',
        preview: 'What went wrong and how to avoid these mistakes.',
        filters: { seniority: 'analyst', fundType: 'buyout' }
    },
    {
        id: 'rejection-to-partner',
        category: 'INSIDER STORIES',
        title: 'From rejection to Partner: 5 journeys',
        preview: 'Inspiring stories of persistence in PE careers.',
        filters: { seniority: 'partner', fundSize: 'large' }
    },
    {
        id: 'top-performers',
        category: 'MARKET INTELLIGENCE',
        title: 'Q4 2024: Top performing PE funds',
        preview: 'Which funds delivered the best returns this quarter.',
        filters: { fundSize: 'mega', geoFocus: 'pan-european' }
    },
    {
        id: 'emerging-markets',
        category: 'MARKET INTELLIGENCE',
        title: 'Emerging markets: The next PE frontier',
        preview: 'Opportunities in developing economies gaining traction.',
        filters: { fundType: 'growth', location: 'singapore' }
    },

    // MORE CAREER INSIGHTS
    {
        id: 'mba-worth-it',
        category: 'CAREER STRATEGIES',
        title: 'Is an MBA worth it for PE careers?',
        preview: 'ROI analysis and alternative paths to private equity.',
        filters: { seniority: 'associate', salaryMin: 120 }
    },
    {
        id: 'lateral-moves',
        category: 'CAREER STRATEGIES',
        title: 'Lateral moves that accelerate careers',
        preview: 'Strategic job changes that fast-track progression.',
        filters: { seniority: 'vp', fundSize: 'large' }
    },
    {
        id: 'portfolio-value',
        category: 'SKILL BUILDERS',
        title: 'Portfolio value creation playbook',
        preview: 'Operating partner strategies that drive returns.',
        filters: { fundType: 'operations', seniority: 'principal' }
    },
    {
        id: 'deal-sourcing',
        category: 'SKILL BUILDERS',
        title: 'Master deal sourcing like a Principal',
        preview: 'Building networks and originating proprietary deals.',
        filters: { seniority: 'principal', fundType: 'growth' }
    },
    {
        id: 'asia-pacific-boom',
        category: 'REGIONAL GUIDES',
        title: 'Asia-Pacific PE: The boom continues',
        preview: 'Why APAC is attracting record capital.',
        filters: { location: 'singapore', fundType: 'growth' }
    },

    // FINAL INFO CARDS
    {
        id: 'women-in-pe',
        category: 'INSIDER STORIES',
        title: 'Women leading European PE',
        preview: 'Success stories and diversity initiatives reshaping the industry.',
        filters: { geoFocus: 'pan-european', seniority: 'partner' }
    },
    {
        id: 'crypto-pe',
        category: 'INDUSTRY TRENDS',
        title: 'Crypto funds meet traditional PE',
        preview: 'How blockchain is entering private equity.',
        filters: { fundType: 'venture', location: 'london' }
    },
    {
        id: 'sustainable-investing',
        category: 'INDUSTRY TRENDS',
        title: 'Sustainable investing: Beyond greenwashing',
        preview: 'PE funds genuinely driving environmental impact.',
        filters: { fundSize: 'mega', geoFocus: 'pan-european' }
    }
];

class PEFilterCards {
    constructor() {
        this.currentFilter = null;
        this.cards = filterPromptCards;
        this.init();
    }

    init() {
        // Wait for DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.render());
        } else {
            this.render();
        }
    }

    render() {
        const container = document.querySelector('.pe-main-filters');
        if (!container) return;

        // Replace traditional filters with prompt cards
        container.innerHTML = `
            <div class="content-scroll">
                ${this.cards.map((card, index) => this.renderCard(card, index)).join('')}
            </div>
        `;

        this.bindEvents();
    }

    renderCard(card, index) {
        const gradients = [
            'linear-gradient(135deg, #0d353e 0%, #1a5a65 100%)',
            'linear-gradient(145deg, #0d353e 0%, #2a6a75 100%)',
            'linear-gradient(125deg, #0d353e 0%, #1f5460 100%)',
            'linear-gradient(155deg, #0d353e 0%, #3a7a85 100%)'
        ];

        const gradient = gradients[index % gradients.length];

        return `
            <div class="question-card" data-card-id="${card.id}" data-filters='${JSON.stringify(card.filters)}' style="background: ${gradient};">
                ${card.trending ? `
                <div class="trending-badge">
                    <div class="trending-icon">🔥</div>
                    <span class="trending-text">Trending</span>
                </div>
                ` : ''}
                
                <div class="question-content">
                    <div class="question-category">${card.category}</div>
                    <h2 class="question-title">${card.title}</h2>
                    <p class="question-preview">${card.preview}</p>
                </div>

                <div class="bottom-cta">
                    <button class="ask-senna-btn" data-card-id="${card.id}">
                        Apply This Filter
                    </button>
                </div>

                <div class="question-card::before" style="background: radial-gradient(circle at ${20 + (index * 10) % 60}% ${30 + (index * 15) % 50}%, rgba(107, 142, 143, 0.15) 0%, transparent 60%);"></div>
            </div>
        `;
    }

    bindEvents() {
        // Handle card clicks
        document.querySelectorAll('.ask-senna-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const cardId = e.target.closest('.ask-senna-btn').dataset.cardId;
                const card = this.cards.find(c => c.id === cardId);
                if (card) {
                    this.applyFilters(card.filters, card);
                }
            });
        });

        // Smooth scroll behavior
        const scrollContainer = document.querySelector('.content-scroll');
        if (scrollContainer) {
            scrollContainer.addEventListener('scroll', this.handleScroll.bind(this));
        }
    }

    handleScroll(e) {
        const cards = e.target.querySelectorAll('.question-card');
        cards.forEach(card => {
            const rect = card.getBoundingClientRect();
            const isVisible = rect.top < window.innerHeight && rect.bottom > 0;
            
            if (isVisible) {
                card.style.opacity = '1';
                card.style.transform = 'translateX(0)';
            }
        });
    }

    applyFilters(filters, card) {
        // Send the card title as a prompt to MENA Careers
        const prompt = card ? card.title : 'Show me relevant opportunities';
        console.log('Sending prompt to chat:', prompt);
        
        // Check if on mobile
        const isMobile = window.innerWidth <= 768;
        
        // Send to MENA Careers conversational chat
        if (window.sennaConversational) {
            window.sennaConversational.addUserMessage(prompt);
            window.sennaConversational.processUserIntent(prompt, filters);
            
            // On mobile, switch to chat mode
            if (isMobile) {
                // Switch to chat tab if using tabbed interface
                const chatTab = document.querySelector('[data-mode="chat"]');
                if (chatTab) {
                    chatTab.click();
                }
                // Or show chat interface
                const chatInterface = document.querySelector('#senna-chat-interface, .senna-chat-container');
                if (chatInterface) {
                    chatInterface.style.display = 'flex';
                    chatInterface.scrollIntoView({ behavior: 'smooth' });
                }
            }
        }
        
        // Also dispatch event for filter system
        const event = new CustomEvent('applyPromptFilters', { 
            detail: { filters, prompt } 
        });
        document.dispatchEvent(event);

        // Visual feedback
        this.showFilterApplied();
    }

    showFilterApplied() {
        // Show toast or visual feedback
        const toast = document.createElement('div');
        toast.className = 'filter-applied-toast';
        toast.innerHTML = 'Filter Applied Successfully';
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
}

// Initialize when ready
window.PEFilterCards = PEFilterCards;
new PEFilterCards();
