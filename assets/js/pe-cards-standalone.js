/**
 * PE Filter Cards - Standalone Working Version
 * This version works independently and will integrate with any container
 */

// Simplified card data (first 20 cards for testing)
const workingFilterCards = [
    {
        id: 'london-pe-guide',
        category: 'MARKET INTELLIGENCE',
        title: 'London PE landscape: Complete guide',
        preview: 'All major funds, their focus areas, and hiring patterns in the City.',
        trending: true
    },
    {
        id: 'paris-pe-elite',
        category: 'REGIONAL GUIDES',
        title: 'Paris PE powerhouses revealed',
        preview: 'Inside the French funds dominating European deals.',
        trending: false
    },
    {
        id: 'six-figure-secrets',
        category: 'COMPENSATION INSIGHTS',
        title: 'Breaking into £100k+ PE roles',
        preview: 'What it really takes to command six-figure packages.',
        trending: true
    },
    {
        id: 'pe-carry-compensation',
        category: 'COMPENSATION INSIGHTS',
        title: 'Private equity carry and wealth building',
        preview: 'How carry, co-invest, bonus, and fund economics shape PE compensation.',
        trending: false
    },
    {
        id: 'balance-myth',
        category: 'INSIDER STORIES',
        title: 'Work-life balance in PE: Reality check',
        preview: 'Which funds actually deliver on 50-60 hour weeks.',
        trending: true
    },
    {
        id: 'growth-equity-boom',
        category: 'INDUSTRY TRENDS',
        title: 'Growth equity: The hottest PE sector',
        preview: 'Why scale-up investing dominates European markets.',
        trending: false
    },
    {
        id: 'mba-worth-it',
        category: 'CAREER STRATEGIES',
        title: 'MBA to PE: ROI analysis',
        preview: 'Is INSEAD or LBS worth it for PE careers?',
        trending: true
    },
    {
        id: 'milan-hidden-gem',
        category: 'REGIONAL GUIDES',
        title: 'Milan: Europe\'s PE hidden gem',
        preview: 'Why Italian PE is quietly outperforming.',
        trending: false
    },
    {
        id: 'mega-fund-life',
        category: 'INSIDER STORIES',
        title: 'Life as a VP at mega-funds',
        preview: 'Inside €5bn+ funds: Culture, pay, and progression.',
        trending: true
    },
    {
        id: 'consulting-advantage',
        category: 'CAREER STRATEGIES',
        title: 'MBB to PE: Your consulting advantage',
        preview: 'How McKinsey/BCG alumni dominate PE recruiting.',
        trending: false
    },
    {
        id: 'latam-opportunity',
        category: 'REGIONAL GUIDES',
        title: 'Latin America PE: Untapped potential',
        preview: 'Why São Paulo is attracting global funds.',
        trending: true
    },
    {
        id: 'distressed-wave',
        category: 'INDUSTRY TRENDS',
        title: 'Distressed debt: The coming wave',
        preview: 'Credit funds preparing for opportunities.',
        trending: false
    },
    {
        id: 'tech-valuations',
        category: 'MARKET INTELLIGENCE',
        title: 'Tech PE: Bubble or bargain?',
        preview: 'How funds are valuing software companies now.',
        trending: true
    },
    {
        id: 'frankfurt-rise',
        category: 'REGIONAL GUIDES',
        title: 'Frankfurt: Post-Brexit financial center',
        preview: 'How Germany\'s financial hub evolved after Brexit.',
        trending: false
    },
    {
        id: 'analyst-pay-guide',
        category: 'COMPENSATION INSIGHTS',
        title: 'Entry-level PE: Who pays the most',
        preview: 'Ranking analyst programs by total compensation.',
        trending: true
    },
    {
        id: 'pan-euro-strategy',
        category: 'MARKET INTELLIGENCE',
        title: 'Pan-European PE strategies decoded',
        preview: 'How funds approach multi-market investments.',
        trending: false
    },
    {
        id: 'gs-to-pe',
        category: 'CAREER STRATEGIES',
        title: 'Goldman to PE: The proven path',
        preview: 'How GS analysts move to top-tier funds.',
        trending: true
    },
    {
        id: 'family-office-secrets',
        category: 'INSIDER STORIES',
        title: 'Family offices: PE\'s best kept secret',
        preview: 'Why ultra-wealthy families offer unique opportunities.',
        trending: false
    },
    {
        id: 'infra-boom',
        category: 'INDUSTRY TRENDS',
        title: 'Infrastructure PE: The trillion euro opportunity',
        preview: 'Energy transition driving massive investments.',
        trending: true
    },
    {
        id: 'mid-market-magic',
        category: 'MARKET INTELLIGENCE',
        title: 'Mid-market PE: Sweet spot for returns',
        preview: 'Why €100-500m deals outperform mega-buyouts.',
        trending: false
    }
];

// Simple working implementation
function initializeCardsNow() {
    console.log('🚀 Starting standalone cards initialization...');
    
    // Find or create container
    let container = document.querySelector('.pe-main-filters');
    if (!container) {
        const sidebar = document.querySelector('.pe-filter-sidebar') || document.querySelector('#pe-filter-container');
        if (sidebar) {
            container = document.createElement('div');
            container.className = 'pe-main-filters';
            sidebar.appendChild(container);
        } else {
            // Create everything from scratch
            const sidebar = document.createElement('div');
            sidebar.className = 'pe-filter-sidebar';
            sidebar.style.cssText = 'position: fixed; left: 20px; top: 100px; width: 300px; height: calc(100vh - 140px); background: rgba(255,255,255,0.98); border-radius: 16px; overflow: hidden; z-index: 997;';
            
            container = document.createElement('div');
            container.className = 'pe-main-filters';
            sidebar.appendChild(container);
            document.body.appendChild(sidebar);
        }
    }
    
    // Create scroll container
    const scrollContainer = document.createElement('div');
    scrollContainer.className = 'content-scroll';
    scrollContainer.style.cssText = 'height: 100%; overflow-y: scroll; scroll-snap-type: y proximity; background: #F5F2E8; padding-top: 20px;';
    
    // Shuffle cards for randomness
    const shuffledCards = [...workingFilterCards].sort(() => Math.random() - 0.5);
    
    // Create cards
    shuffledCards.forEach((card, index) => {
        const cardElement = createCard(card, index);
        scrollContainer.appendChild(cardElement);
        
        // Animate in with delay
        setTimeout(() => {
            cardElement.style.opacity = '1';
            cardElement.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Clear container and add scroll container
    container.innerHTML = '';
    container.appendChild(scrollContainer);
    
    console.log(`✅ ${shuffledCards.length} cards loaded successfully!`);
    return shuffledCards.length;
}

function createCard(card, index) {
    const cardDiv = document.createElement('div');
    cardDiv.className = 'question-card';
    cardDiv.style.cssText = `
        min-height: 60vh;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 80px 20px 60px;
        margin-bottom: 20px;
        border-radius: 0;
        overflow: hidden;
        cursor: pointer;
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.5s ease;
        background: linear-gradient(${135 + (index * 10)}deg, #0d353e 0%, #${['1a5a65', '2a6a75', '1f5460', '3a7a85'][index % 4]} 100%);
    `;
    
    cardDiv.innerHTML = `
        ${card.trending ? `
        <div style="position: absolute; top: 20px; left: 20px; background: rgba(245, 242, 232, 0.95); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 20px; display: flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.3); z-index: 2;">
            <div style="width: 16px; height: 16px; background: #0d353e; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #F5F2E8; font-size: 10px; font-weight: bold;">🔥</div>
            <span style="color: #0d353e; font-size: 12px; font-weight: 600;">Hot</span>
        </div>
        ` : ''}
        
        <div style="flex: 1; display: flex; flex-direction: column; justify-content: center; max-width: 340px; margin: 0 auto; position: relative; z-index: 1;">
            <div style="color: rgba(255,255,255,0.9); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">${card.category}</div>
            <h2 style="color: white; font-size: 28px; font-weight: 700; line-height: 1.3; margin-bottom: 20px; text-shadow: 0 2px 10px rgba(0,0,0,0.2);">${card.title}</h2>
            <p style="color: rgba(255,255,255,0.95); font-size: 16px; line-height: 1.6; margin-bottom: 30px;">${card.preview}</p>
        </div>

        <div style="position: absolute; bottom: 0; left: 0; right: 0; padding: 20px; background: linear-gradient(to top, rgba(13, 53, 62, 0.9), transparent);">
            <button onclick="applyCardFilter('${card.id}')" style="width: 100%; padding: 16px 24px; background: rgba(245, 242, 232, 0.95); backdrop-filter: blur(10px); border: none; border-radius: 12px; color: #0d353e; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                Apply This Filter
            </button>
        </div>
    `;
    
    return cardDiv;
}

// Filter application handler
window.applyCardFilter = function(cardId) {
    const card = workingFilterCards.find(c => c.id === cardId);
    if (card) {
        console.log('🎯 Applying filter:', card.title);
        
        // Show feedback
        showToast(`Exploring: ${card.title}`);
        
        // Send prompt to MENA Careers chat
        if (window.sennaConversational) {
            window.sennaConversational.addUserMessage(card.title);
            // Process with filters if available, otherwise just the title
            const filters = card.filters || {};
            window.sennaConversational.processUserIntent(card.title, filters);
        }
        
        // Check if on mobile and switch to chat
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            // Switch to chat tab
            const chatTab = document.querySelector('[data-mode="chat"], .mode-pill[data-mode="chat"]');
            if (chatTab) {
                chatTab.click();
            }
            
            // Show and scroll to chat
            const chatInterface = document.querySelector('#senna-chat-interface, .senna-chat-container, .chat-interface');
            if (chatInterface) {
                chatInterface.style.display = 'flex';
                setTimeout(() => {
                    chatInterface.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }
    }
};

function showToast(message) {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #0d353e 0%, #1a5a65 100%);
        color: white;
        padding: 16px 32px;
        border-radius: 30px;
        font-weight: 600;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        z-index: 10000;
        animation: slideUp 0.3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideDown 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCardsNow);
} else {
    initializeCardsNow();
}

// Export for manual initialization
window.initializeCardsNow = initializeCardsNow;
