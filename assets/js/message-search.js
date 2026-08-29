/**
 * Simple Message Search - Like Microsoft Word Find
 * Just scans the chat for text matches
 */

(function($) {
    'use strict';
    
    class MessageSearch {
        constructor() {
            this.currentMatches = [];
            this.currentIndex = -1;
            this.init();
        }
        
        init() {
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setupSearch());
            } else {
                this.setupSearch();
            }
        }
        
        setupSearch() {
            // Only target the dedicated message search bar, not every search input on the site
            this.searchInput = document.querySelector('.sffc-message-search .sffc-search-input');
            if (!this.searchInput) {
                // Try the explicit ID as a fallback for legacy templates
                this.searchInput = document.getElementById('message-search');
            }

            // Bail if we still haven't found a scoped message search input
            if (!this.searchInput) {
                return;
            }

            // Guard against accidentally binding to the global results search bar
            if (!this.searchInput.closest('.sffc-message-search')) {
                return;
            }
            
            // Add event listeners for SIMPLE text search
            this.searchInput.addEventListener('input', (e) => {
                this.simpleTextSearch(e.target.value);
            });
            
            this.searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.navigateToNext();
                } else if (e.key === 'Escape') {
                    this.clearSearch();
                }
            });
            
            // Add navigation controls
            this.addNavigationControls();
        }
        
        addNavigationControls() {
            const wrapper = this.searchInput.parentElement;
            if (!wrapper || wrapper.querySelector('.search-nav-buttons')) return;
            
            const navHTML = `
                <div class="search-nav-buttons">
                    <button class="search-nav-prev" title="Previous">↑</button>
                    <button class="search-nav-next" title="Next">↓</button>
                    <span class="search-counter">0/0</span>
                </div>
            `;
            
            wrapper.insertAdjacentHTML('beforeend', navHTML);
            
            // Add click handlers
            wrapper.querySelector('.search-nav-prev').addEventListener('click', () => this.navigateToPrevious());
            wrapper.querySelector('.search-nav-next').addEventListener('click', () => this.navigateToNext());
        }
        
        simpleTextSearch(searchText) {
            // Clear previous highlights
            this.clearHighlights();
            this.currentMatches = [];
            this.currentIndex = -1;
            
            if (!searchText || searchText.trim().length === 0) {
                this.updateCounter();
                return;
            }
            
            // Get ALL text content from chat messages
            const messages = document.querySelectorAll('.senna-message, .user-message, .sffc-message-user, .sffc-message-senna');
            
            messages.forEach(message => {
                // Get the full text content
                const text = message.textContent || message.innerText || '';
                
                // Simple case-insensitive check
                if (text.toLowerCase().includes(searchText.toLowerCase())) {
                    // Highlight this entire message
                    this.highlightTextInElement(message, searchText);
                }
            });
            
            // Get all highlighted spans
            this.currentMatches = Array.from(document.querySelectorAll('.message-search-highlight'));
            
            // Navigate to first match
            if (this.currentMatches.length > 0) {
                this.currentIndex = 0;
                this.highlightCurrent();
            }
            
            this.updateCounter();
        }
        
        highlightTextInElement(element, searchText) {
            // Walk through all text nodes and highlight matches
            const walker = document.createTreeWalker(
                element,
                NodeFilter.SHOW_TEXT,
                {
                    acceptNode: (node) => {
                        // Skip empty nodes
                        if (!node.textContent.trim()) return NodeFilter.FILTER_REJECT;
                        // Skip script/style
                        if (node.parentElement.tagName === 'SCRIPT' || 
                            node.parentElement.tagName === 'STYLE') {
                            return NodeFilter.FILTER_REJECT;
                        }
                        return NodeFilter.FILTER_ACCEPT;
                    }
                }
            );
            
            const nodesToReplace = [];
            let node;
            
            while (node = walker.nextNode()) {
                const text = node.textContent;
                const regex = new RegExp(this.escapeRegex(searchText), 'gi');
                
                if (regex.test(text)) {
                    nodesToReplace.push(node);
                }
            }
            
            // Replace text nodes with highlighted versions
            nodesToReplace.forEach(node => {
                const text = node.textContent;
                const regex = new RegExp(this.escapeRegex(searchText), 'gi');
                const fragment = document.createDocumentFragment();
                let lastIndex = 0;
                let match;
                
                regex.lastIndex = 0; // Reset regex
                while ((match = regex.exec(text)) !== null) {
                    // Add text before match
                    if (match.index > lastIndex) {
                        fragment.appendChild(
                            document.createTextNode(text.substring(lastIndex, match.index))
                        );
                    }
                    
                    // Add highlighted match
                    const span = document.createElement('span');
                    span.className = 'message-search-highlight';
                    span.textContent = match[0];
                    fragment.appendChild(span);
                    
                    lastIndex = match.index + match[0].length;
                }
                
                // Add remaining text
                if (lastIndex < text.length) {
                    fragment.appendChild(
                        document.createTextNode(text.substring(lastIndex))
                    );
                }
                
                // Replace the text node
                node.parentNode.replaceChild(fragment, node);
            });
        }
        
        escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        clearHighlights() {
            const highlights = document.querySelectorAll('.message-search-highlight');
            highlights.forEach(highlight => {
                const parent = highlight.parentNode;
                const text = document.createTextNode(highlight.textContent);
                parent.replaceChild(text, highlight);
                parent.normalize(); // Merge adjacent text nodes
            });
            
            // Remove current marker
            document.querySelectorAll('.current-search-match').forEach(el => {
                el.classList.remove('current-search-match');
            });
        }
        
        highlightCurrent() {
            // Remove previous current highlight
            document.querySelectorAll('.current-search-match').forEach(el => {
                el.classList.remove('current-search-match');
            });
            
            // Add current highlight
            if (this.currentMatches[this.currentIndex]) {
                this.currentMatches[this.currentIndex].classList.add('current-search-match');
                this.currentMatches[this.currentIndex].scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }
        
        navigateToNext() {
            if (this.currentMatches.length === 0) return;
            
            this.currentIndex = (this.currentIndex + 1) % this.currentMatches.length;
            this.highlightCurrent();
            this.updateCounter();
        }
        
        navigateToPrevious() {
            if (this.currentMatches.length === 0) return;
            
            this.currentIndex = this.currentIndex - 1;
            if (this.currentIndex < 0) {
                this.currentIndex = this.currentMatches.length - 1;
            }
            this.highlightCurrent();
            this.updateCounter();
        }
        
        updateCounter() {
            const counter = document.querySelector('.search-counter');
            if (!counter) return;
            
            if (this.currentMatches.length === 0) {
                counter.textContent = '0/0';
            } else {
                counter.textContent = `${this.currentIndex + 1}/${this.currentMatches.length}`;
            }
        }
        
        clearSearch() {
            if (this.searchInput) {
                this.searchInput.value = '';
            }
            this.clearHighlights();
            this.currentMatches = [];
            this.currentIndex = -1;
            this.updateCounter();
        }
    }
    
    // Initialize on document ready
    $(document).ready(() => {
        window.messageSearch = new MessageSearch();
    });
    
    // Reinitialize when messages are added
    $(document).on('sennaMessageAdded senna:message:added', () => {
        if (window.messageSearch && window.messageSearch.searchInput && window.messageSearch.searchInput.value) {
            setTimeout(() => {
                window.messageSearch.simpleTextSearch(window.messageSearch.searchInput.value);
            }, 100);
        }
    });
    
})(jQuery);

/* Search Highlight Styles */
const searchStyles = `
<style>
/* Simple Search Highlights */
.message-search-highlight {
    background: rgba(201, 169, 97, 0.25) !important;
    padding: 2px 0;
    border-radius: 3px;
    color: inherit;
}

.current-search-match {
    background: rgba(201, 169, 97, 0.5) !important;
    font-weight: 600;
    box-shadow: 0 0 0 2px rgba(201, 169, 97, 0.3);
}

/* Search Navigation Controls */
.search-nav-buttons {
    position: absolute;
    right: 40px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    gap: 4px;
}

.search-nav-prev,
.search-nav-next {
    width: 24px;
    height: 24px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    background: white;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #666;
    transition: all 0.2s ease;
}

.search-nav-prev:hover,
.search-nav-next:hover {
    background: var(--vogue-black);
    color: white;
    border-color: var(--vogue-black);
}

.search-counter {
    padding: 0 8px;
    font-size: 12px;
    color: #666;
    font-family: 'Inter', sans-serif;
    white-space: nowrap;
}

/* Better mobile search */
@media (max-width: 768px) {
    .search-nav-buttons {
        right: 10px;
    }
    
    .sffc-search-input {
        padding-right: 100px !important;
    }
}
</style>
`;

// Inject styles
document.head.insertAdjacentHTML('beforeend', searchStyles);
