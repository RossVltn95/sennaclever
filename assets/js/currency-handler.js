/**
 * Currency Handler for Frontend
 * Manages currency selection and conversion in the UI
 */

(function($) {
    'use strict';
    
    class CurrencyHandler {
        constructor() {
            this.currentCurrency = 'USD';
            this.currencies = {};
            this.rates = {};
            this.initialized = false;
            
            this.init();
        }
        
        async init() {
            // Load currency settings
            await this.loadCurrencySettings();
            
            // Bind events
            this.bindEvents();
            
            // Initialize currency selector UI
            this.initializeSelectorUI();
            
            // Update all displayed amounts
            this.updateAllAmounts();
            
            this.initialized = true;
        }
        
        async loadCurrencySettings() {
            try {
                const response = await $.ajax({
                    url: window.sffc_ajax?.url || '/wp-admin/admin-ajax.php',
                    type: 'POST',
                    data: {
                        action: 'sffc_get_currency_settings',
                        nonce: window.sffc_ajax?.nonce || ''
                    }
                });
                
                if (response.success) {
                    this.currentCurrency = response.data.current;
                    this.currencies = response.data.currencies;
                    this.rates = response.data.rates;
                    
                    // Store in localStorage for quick access
                    localStorage.setItem('sffc_currency', this.currentCurrency);
                    localStorage.setItem('sffc_rates', JSON.stringify(this.rates));
                }
            } catch (error) {
                console.error('Failed to load currency settings:', error);
                // Use cached data if available
                this.currentCurrency = localStorage.getItem('sffc_currency') || 'USD';
                const cachedRates = localStorage.getItem('sffc_rates');
                if (cachedRates) {
                    this.rates = JSON.parse(cachedRates);
                }
            }
        }
        
        bindEvents() {
            // Currency selector change
            $(document).on('change', '#currency-selector', (e) => {
                this.setCurrency($(e.target).val());
            });
            
            // Quick currency buttons
            $(document).on('click', '.currency-quick-select', (e) => {
                const currency = $(e.currentTarget).data('currency');
                this.setCurrency(currency);
            });
        }
        
        async setCurrency(currencyCode) {
            if (!this.rates[currencyCode]) {
                console.error('Invalid currency:', currencyCode);
                return;
            }
            
            this.currentCurrency = currencyCode;
            
            // Save preference
            try {
                await $.ajax({
                    url: window.sffc_ajax?.url || '/wp-admin/admin-ajax.php',
                    type: 'POST',
                    data: {
                        action: 'sffc_set_currency_preference',
                        currency: currencyCode,
                        nonce: window.sffc_ajax?.nonce || ''
                    }
                });
            } catch (error) {
                console.error('Failed to save currency preference:', error);
            }
            
            // Update localStorage
            localStorage.setItem('sffc_currency', currencyCode);
            
            // Update all amounts on page
            this.updateAllAmounts();
            
            // Trigger event for other components
            $(document).trigger('sffc:currency:changed', [currencyCode]);
        }
        
        convert(amount, fromCurrency = 'USD', toCurrency = null) {
            if (!toCurrency) {
                toCurrency = this.currentCurrency;
            }
            
            if (fromCurrency === toCurrency) {
                return amount;
            }
            
            // Convert to USD first (base currency)
            const usdAmount = amount / (this.rates[fromCurrency] || 1);
            
            // Convert to target currency
            const converted = usdAmount * (this.rates[toCurrency] || 1);
            
            // Round appropriately for salary amounts
            if (converted > 10000) {
                return Math.round(converted / 1000) * 1000;
            } else if (converted > 1000) {
                return Math.round(converted / 100) * 100;
            }
            
            return Math.round(converted);
        }
        
        format(amount, currencyCode = null) {
            if (!currencyCode) {
                currencyCode = this.currentCurrency;
            }
            
            const currency = this.getCurrencyConfig(currencyCode);
            let formatted = amount;
            
            // Format large numbers
            if (amount >= 1000000) {
                formatted = (amount / 1000000).toFixed(1) + 'M';
            } else if (amount >= 1000) {
                formatted = Math.round(amount / 1000) + 'k';
            } else {
                formatted = amount.toLocaleString();
            }
            
            // Add currency symbol
            return currency.symbol + formatted;
        }
        
        formatRange(min, max, currencyCode = null) {
            if (!currencyCode) {
                currencyCode = this.currentCurrency;
            }
            
            if (!min && !max) {
                return 'Competitive';
            }
            
            if (!max || max === min) {
                return this.format(min, currencyCode) + '+';
            }
            
            if (!min) {
                return 'Up to ' + this.format(max, currencyCode);
            }
            
            return this.format(min, currencyCode) + ' - ' + this.format(max, currencyCode);
        }
        
        getCurrencyConfig(currencyCode) {
            const configs = {
                'USD': { symbol: '$', name: 'US Dollar' },
                'GBP': { symbol: '£', name: 'British Pound' },
                'EUR': { symbol: '€', name: 'Euro' },
                'CAD': { symbol: 'C$', name: 'Canadian Dollar' },
                'AUD': { symbol: 'A$', name: 'Australian Dollar' },
                'CHF': { symbol: 'CHF ', name: 'Swiss Franc' },
                'SGD': { symbol: 'S$', name: 'Singapore Dollar' },
                'HKD': { symbol: 'HK$', name: 'Hong Kong Dollar' },
                'INR': { symbol: '₹', name: 'Indian Rupee' },
                'BRL': { symbol: 'R$', name: 'Brazilian Real' },
                'ZAR': { symbol: 'R', name: 'South African Rand' },
                'JPY': { symbol: '¥', name: 'Japanese Yen' },
                'CNY': { symbol: '¥', name: 'Chinese Yuan' }
            };
            
            return configs[currencyCode] || configs['USD'];
        }
        
        updateAllAmounts() {
            // Update all salary displays on the page
            $('.salary-amount').each((index, element) => {
                const $elem = $(element);
                const min = parseInt($elem.data('salary-min') || 0);
                const max = parseInt($elem.data('salary-max') || 0);
                const originalCurrency = $elem.data('currency') || 'USD';
                
                if (min || max) {
                    // Convert amounts
                    const convertedMin = this.convert(min, originalCurrency);
                    const convertedMax = this.convert(max, originalCurrency);
                    
                    // Format and display
                    $elem.text(this.formatRange(convertedMin, convertedMax));
                }
            });
            
            // Update currency selector if exists
            $('#currency-selector').val(this.currentCurrency);
            $('.current-currency-display').text(this.getCurrencyConfig(this.currentCurrency).name);
            
            // Update any currency indicators
            $('.currency-indicator').text(this.getCurrencyConfig(this.currentCurrency).symbol);
        }
        
        // Create currency selector UI
        createCurrencySelector() {
            const currencies = [
                { code: 'USD', symbol: '$', name: 'USD' },
                { code: 'GBP', symbol: '£', name: 'GBP' },
                { code: 'EUR', symbol: '€', name: 'EUR' },
                { code: 'CAD', symbol: 'C$', name: 'CAD' },
                { code: 'AUD', symbol: 'A$', name: 'AUD' },
                { code: 'CHF', symbol: 'CHF', name: 'CHF' },
                { code: 'SGD', symbol: 'S$', name: 'SGD' },
                { code: 'HKD', symbol: 'HK$', name: 'HKD' },
                { code: 'INR', symbol: '₹', name: 'INR' },
                { code: 'BRL', symbol: 'R$', name: 'BRL' }
            ];
            
            const currentCurrency = currencies.find(c => c.code === this.currentCurrency) || currencies[0];
            
            return `
                <div class="currency-selector-wrapper">
                    <button class="currency-selector-toggle" id="currency-toggle">
                        <span class="currency-symbol">${currentCurrency.symbol}</span>
                        <span class="currency-code">${currentCurrency.code}</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="currency-dropdown" id="currency-dropdown">
                        ${currencies.map(curr => `
                            <button class="currency-option ${curr.code === this.currentCurrency ? 'active' : ''}" 
                                    data-currency="${curr.code}">
                                <span class="currency-symbol">${curr.symbol}</span>
                                <span class="currency-name">${curr.name}</span>
                            </button>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        
        toggleSelector() {
            $('#currency-dropdown').toggleClass('show');
            $('.currency-selector-toggle').toggleClass('active');
        }
        
        initializeSelectorUI() {
            const container = document.getElementById('currency-selector-container');
            if (container) {
                container.innerHTML = this.createCurrencySelector();
                
                // Bind toggle button
                $('#currency-toggle').on('click', (e) => {
                    e.stopPropagation();
                    this.toggleSelector();
                });
                
                // Bind currency options
                $('.currency-option').on('click', (e) => {
                    const currency = $(e.currentTarget).data('currency');
                    this.setCurrency(currency);
                    this.toggleSelector(); // Close dropdown after selection
                });
                
                // Close dropdown when clicking outside
                $(document).on('click', (e) => {
                    if (!$(e.target).closest('.currency-selector-wrapper').length) {
                        $('#currency-dropdown').removeClass('show');
                        $('.currency-selector-toggle').removeClass('active');
                    }
                });
            }
        }
    }
    
    // Initialize when document is ready
    $(document).ready(() => {
        window.currencyHandler = new CurrencyHandler();
        
        // Make it available to MENA Careers conversation
        if (window.sennaConversational) {
            window.sennaConversational.currencyHandler = window.currencyHandler;
        }
    });
    
})(jQuery);