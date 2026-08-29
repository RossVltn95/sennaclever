/**
 * PE Filter Cards Initializer
 * Ensures the card system replaces old filters completely
 */

(function() {
    'use strict';
    
    console.log('PE Filter Cards Initializer: Starting...');
    
    // Wait for DOM ready
    function initializeCards() {
        // Find the PE filter sidebar
        const sidebar = document.querySelector('.pe-filter-sidebar');
        
        if (!sidebar) {
            console.log('PE Filter Cards: Sidebar not found yet, retrying...');
            setTimeout(initializeCards, 500);
            return;
        }
        
        console.log('PE Filter Cards: Found sidebar, replacing content...');
        
        // CLEAR ALL OLD CONTENT
        sidebar.innerHTML = '';
        
        // Create the structure from career-panel-mockup
        const structure = `
            <!-- Stories Bar -->
            <div class="pe-quick-filters stories-bar">
                <div class="pe-quick-filter-item story-bubble" data-category="MARKET INTELLIGENCE">
                    <div class="pe-quick-icon story-ring">
                        <div class="pe-quick-icon-inner story-avatar">MI</div>
                    </div>
                    <div class="pe-filter-label story-label">Market Intel</div>
                </div>
                <div class="pe-quick-filter-item story-bubble" data-category="CAREER STRATEGIES">
                    <div class="pe-quick-icon story-ring">
                        <div class="pe-quick-icon-inner story-avatar">CS</div>
                    </div>
                    <div class="pe-filter-label story-label">Career Strategy</div>
                </div>
                <div class="pe-quick-filter-item story-bubble" data-category="COMPENSATION INSIGHTS">
                    <div class="pe-quick-icon story-ring">
                        <div class="pe-quick-icon-inner story-avatar">£££</div>
                    </div>
                    <div class="pe-filter-label story-label">Compensation</div>
                </div>
                <div class="pe-quick-filter-item story-bubble" data-category="REGIONAL GUIDES">
                    <div class="pe-quick-icon story-ring">
                        <div class="pe-quick-icon-inner story-avatar">RG</div>
                    </div>
                    <div class="pe-filter-label story-label">Regional</div>
                </div>
                <div class="pe-quick-filter-item story-bubble" data-category="INDUSTRY TRENDS">
                    <div class="pe-quick-icon story-ring">
                        <div class="pe-quick-icon-inner story-avatar">IT</div>
                    </div>
                    <div class="pe-filter-label story-label">Trends</div>
                </div>
                <div class="pe-quick-filter-item story-bubble" data-category="INSIDER STORIES">
                    <div class="pe-quick-icon story-ring">
                        <div class="pe-quick-icon-inner story-avatar">IS</div>
                    </div>
                    <div class="pe-filter-label story-label">Insider</div>
                </div>
            </div>
            
            <!-- Main Content Scroll Area -->
            <div class="pe-main-filters content-scroll" id="pe-cards-container">
                <!-- Cards will be inserted here by pe-filter-cards-extended.js -->
            </div>
        `;
        
        // Insert the new structure
        sidebar.innerHTML = structure;
        
        console.log('PE Filter Cards: Structure created, initializing card system...');
        
        // Force a small delay to ensure DOM is updated
        setTimeout(() => {
            // Now initialize the extended cards system if it exists
            if (typeof PEFilterCardsSystem !== 'undefined') {
                // System already exists, reinitialize
                if (window.peFilterCardsSystem) {
                    console.log('PE Filter Cards: Reinitializing existing system...');
                    window.peFilterCardsSystem = null;
                }
                window.peFilterCardsSystem = new PEFilterCardsSystem();
                console.log('PE Filter Cards: System initialized successfully!');
            } else {
            console.log('PE Filter Cards: Waiting for PEFilterCardsSystem to load...');
            
            // Wait for the system to be available
            let waitCount = 0;
            const waitForSystem = setInterval(() => {
                waitCount++;
                if (typeof PEFilterCardsSystem !== 'undefined') {
                    clearInterval(waitForSystem);
                    window.peFilterCardsSystem = new PEFilterCardsSystem();
                    console.log('PE Filter Cards: System initialized after waiting!');
                } else if (waitCount > 20) {
                    clearInterval(waitForSystem);
                    console.error('PE Filter Cards: PEFilterCardsSystem not found after 10 seconds');
                    // Fallback: Load some basic cards
                    loadFallbackCards();
                }
            }, 500);
            }
        }, 100); // Small delay for DOM update
        
        // Add story bubble click handlers
        setTimeout(() => {
            document.querySelectorAll('.pe-quick-filter-item').forEach(item => {
                item.addEventListener('click', function() {
                    const label = this.querySelector('.pe-filter-label').textContent;
                    const category = this.dataset.category;
                    console.log('Filter clicked:', label, category);
                    
                    // Visual feedback
                    const icon = this.querySelector('.pe-quick-icon');
                    icon.style.transform = 'scale(0.9)';
                    setTimeout(() => {
                        icon.style.transform = '';
                    }, 200);
                    
                    // Apply filter based on category
                    applyQuickFilter(label, category);
                });
            });
        }, 1000);
    }
    
    // Apply quick filter based on story bubble clicked
    function applyQuickFilter(filterLabel, category) {
        // Filter cards by category
        if (category) {
            // Scroll to cards of this category
            const allCards = document.querySelectorAll('.question-card');
            let firstMatchingCard = null;
            
            allCards.forEach(card => {
                const cardCategory = card.querySelector('.question-category')?.textContent;
                if (cardCategory === category) {
                    card.style.display = 'block';
                    if (!firstMatchingCard) {
                        firstMatchingCard = card;
                    }
                } else {
                    // Optionally hide other cards or keep them visible
                    card.style.display = 'block'; // Keep all visible for now
                }
            });
            
            // Scroll to first matching card
            if (firstMatchingCard) {
                firstMatchingCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            
            // Send category as prompt to chat
            if (window.sennaConversational) {
                const prompts = {
                    'MARKET INTELLIGENCE': 'Show me the latest PE market intelligence',
                    'CAREER STRATEGIES': 'What are the best career strategies in PE?',
                    'COMPENSATION INSIGHTS': 'Tell me about PE compensation trends',
                    'REGIONAL GUIDES': 'Guide me through regional PE markets',
                    'INDUSTRY TRENDS': 'What are the latest PE industry trends?',
                    'INSIDER STORIES': 'Share some insider PE stories'
                };
                
                const prompt = prompts[category] || `Show me ${filterLabel} insights`;
                window.sennaConversational.addUserMessage(prompt);
            }
        }
        
        // Dispatch event for filter system
        const event = new CustomEvent('promptFilterApplied', {
            detail: { category, label: filterLabel }
        });
        document.dispatchEvent(event);
    }
    
    // Fallback function to load basic cards if system fails
    function loadFallbackCards() {
        const container = document.getElementById('pe-cards-container');
        if (!container) return;
        
        const fallbackCards = [
            {
                category: 'COMPENSATION INSIGHTS',
                title: 'Breaking the £150k barrier in PE',
                preview: 'Premium positions with exceptional compensation packages in London and Europe.',
            },
            {
                category: 'REGIONAL GUIDES',
                title: 'London PE ecosystem: Complete guide',
                preview: 'Explore opportunities at leading PE firms in the City.',
            },
            {
                category: 'CAREER STRATEGIES',
                title: 'Banking to PE: The proven playbook',
                preview: 'Perfect roles for IB analysts looking to transition to private equity.',
            }
        ];
        
        fallbackCards.forEach((card, index) => {
            const cardDiv = document.createElement('div');
            cardDiv.className = 'question-card';
            cardDiv.innerHTML = `
                <div class="trending-badge">
                    <div class="trending-icon">${card.category.substring(0, 1)}</div>
                    <span class="trending-text">${card.category}</span>
                </div>
                
                <div class="question-content">
                    <div class="question-category">${card.category}</div>
                    <h2 class="question-title">${card.title}</h2>
                    <p class="question-preview">${card.preview}</p>
                </div>
                
                <div class="bottom-cta">
                    <button class="ask-senna-btn">
                        Apply This Filter
                    </button>
                </div>
            `;
            container.appendChild(cardDiv);
        });
        
        console.log('PE Filter Cards: Loaded fallback cards');
    }
    
    // Start initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCards);
    } else {
        // DOM already loaded
        initializeCards();
    }
    
    // Also try after a delay to ensure all scripts are loaded
    setTimeout(initializeCards, 2000);
    
})();