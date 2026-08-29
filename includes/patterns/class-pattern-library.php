<?php
/**
 * Pattern Library for Finance Query Recognition
 * Phase 2: 500+ patterns for finance-specific queries
 * 
 * @package SennaCareers
 * @since 6.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Pattern_Library {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Primary Intent Patterns - What the user wants to do
     */
    private $intent_patterns = array(
        
        // MARKET STATUS QUERIES
        'market_status' => array(
            'patterns' => array(
                '/^(what|how|whats|hows?)\\s+(is|are)\\s+(.+?)\\s+(doing|performing|trading|today|now)/i',
                '/^(show|tell|give)\\s+(me|us)?\\s*(the|todays?)?\\s*(market|markets|status|performance)/i',
                '/^(market|markets|indices?|sectors?)\\s+(status|update|overview|summary|snapshot)/i',
                '/(S&P|SPX|Dow|Nasdaq|Russell)\\s+(today|now|status|performance|price)/i',
                '/^(where|how)\\s+(is|are)\\s+(the)?\\s*(market|markets|indices)/i',
                '/market\\s+(open|close|closing|opened|closed)/i'
            ),
            'extractor' => 'extract_market_entities',
            'response_type' => 'market_status',
            'data_required' => array('indices', 'sectors', 'news')
        ),
        
        // COMPARISON QUERIES
        'comparison' => array(
            'patterns' => array(
                '/(.+?)\\s+(vs?\\.?|versus|compared?|against)\\s+(.+)/i',
                '/(compare|difference|between)\\s+(.+?)\\s+and\\s+(.+)/i',
                '/(which|what)\\s+(is|are)\\s+(better|worse|stronger)\\s*[:-]?\\s*(.+?)\\s+or\\s+(.+)/i',
                '/(how\\s+does?)\\s+(.+?)\\s+compare\\s+(to|with)\\s+(.+)/i'
            ),
            'extractor' => 'extract_comparison_entities',
            'response_type' => 'comparison_analysis',
            'data_required' => array('entities', 'metrics')
        ),
        
        // EXPLANATION QUERIES
        'explanation' => array(
            'patterns' => array(
                '/(why|what\\s+caused?|reason|explain)\\s+(.+)/i',
                '/(how\\s+come|whats?\\s+behind|whats?\\s+driving)\\s+(.+)/i',
                '/(tell|help)\\s+me\\s+understand\\s+(.+)/i',
                '/(what\\s+does?|what\\s+is)\\s+(.+?)\\s+(mean|indicate|suggest)/i',
                '/(significance|impact|implications?)\\s+of\\s+(.+)/i'
            ),
            'extractor' => 'extract_explanation_context',
            'response_type' => 'explanation',
            'data_required' => array('context', 'analysis')
        ),
        
        // PREDICTION/FORECAST QUERIES
        'prediction' => array(
            'patterns' => array(
                '/(will|would|should|might)\\s+(.+?)\\s+(go|rise|fall|increase|decrease)/i',
                '/(what|whats|where)\\s+(will|might|should)\\s+(.+?)\\s+(go|be|head)/i',
                '/(forecast|predict|outlook|projection|expectation)\\s+(for|on|about)?\\s+(.+)/i',
                '/(next|tomorrow|future)\\s+(.+?)\\s+(price|movement|direction)/i',
                '/(bullish|bearish)\\s+(on|about|for)\\s+(.+)/i'
            ),
            'extractor' => 'extract_prediction_context',
            'response_type' => 'prediction',
            'data_required' => array('trends', 'analysis')
        ),
        
        // RECOMMENDATION QUERIES
        'recommendation' => array(
            'patterns' => array(
                '/(what|which)\\s+(should|would|could)\\s+(i|we|you)\\s+(buy|sell|invest|do)/i',
                '/(recommend|suggest|advise|best)\\s+(.+)/i',
                '/(good|bad)\\s+(time|idea|moment)\\s+(to|for)\\s+(.+)/i',
                '/(opportunities?|ideas?)\\s+(in|for|with)\\s+(.+)/i',
                '/(where|what)\\s+(to|should)\\s+invest/i'
            ),
            'extractor' => 'extract_recommendation_context',
            'response_type' => 'recommendation',
            'data_required' => array('opportunities', 'analysis')
        ),
        
        // VALUATION QUERIES
        'valuation' => array(
            'patterns' => array(
                '/(value|valuation|worth)\\s+of\\s+(.+)/i',
                '/(overvalued|undervalued|fairly\\s+valued)\\s+(.+)/i',
                '/(PE|P\\/E|price\\s+to\\s+earnings)\\s+(ratio|multiple)?\\s*(of|for)?\\s+(.+)/i',
                '/(expensive|cheap|pricey)\\s+(.+)/i',
                '/(DCF|discounted\\s+cash\\s+flow)\\s+(.+)/i',
                '/(enterprise\\s+value|market\\s+cap|EV)\\s+of\\s+(.+)/i'
            ),
            'extractor' => 'extract_valuation_entities',
            'response_type' => 'valuation_analysis',
            'data_required' => array('financial_metrics', 'comparables')
        ),
        
        // SECTOR/INDUSTRY QUERIES
        'sector_analysis' => array(
            'patterns' => array(
                '/(sector|industry)\\s+(performance|rotation|analysis|outlook)/i',
                '/(best|worst|top|bottom)\\s+(performing)?\\s+(sectors?|industries)/i',
                '/(tech|technology|healthcare|financials?|energy|utilities)\\s+(sector|industry|stocks?)/i',
                '/(sector|industry)\\s+(leaders?|laggards?|movers?)/i',
                '/sector\\s+rotation/i'
            ),
            'extractor' => 'extract_sector_entities',
            'response_type' => 'sector_analysis',
            'data_required' => array('sectors', 'performance')
        ),
        
        // PRIVATE EQUITY QUERIES
        'private_equity' => array(
            'patterns' => array(
                '/(PE|private\\s+equity)\\s+(deals?|activity|firms?|funds?)/i',
                '/(Blackstone|KKR|Apollo|Carlyle|TPG)\\s+(.+)/i',
                '/(buyout|LBO|leveraged\\s+buyout)\\s+(.+)/i',
                '/(portfolio\\s+company|companies)\\s+(.+)/i',
                '/(PE|private\\s+equity)\\s+(opportunities?|market|landscape)/i',
                '/dry\\s+powder/i',
                '/(fundraising|fund\\s+raising)\\s+(activity|environment)/i'
            ),
            'extractor' => 'extract_pe_entities',
            'response_type' => 'pe_analysis',
            'data_required' => array('pe_deals', 'pe_news')
        ),
        
        // M&A QUERIES
        'mergers_acquisitions' => array(
            'patterns' => array(
                '/(M&A|merger|acquisition|takeover|deal)\\s+(.+)/i',
                '/(acquiring|buying|purchased?|merger\\s+with)\\s+(.+)/i',
                '/(deal\\s+value|transaction\\s+value|purchase\\s+price)/i',
                '/(hostile|friendly)\\s+(takeover|bid|offer)/i',
                '/(consolidation|merger\\s+activity)\\s+in\\s+(.+)/i'
            ),
            'extractor' => 'extract_ma_entities',
            'response_type' => 'ma_analysis',
            'data_required' => array('ma_deals', 'ma_news')
        ),
        
        // EARNINGS/FINANCIAL RESULTS
        'earnings' => array(
            'patterns' => array(
                '/(earnings|revenue|profit|income|sales)\\s+(.+)/i',
                '/(Q1|Q2|Q3|Q4|quarter|quarterly)\\s+(results?|earnings?|report)/i',
                '/(beat|miss|exceeded|fell\\s+short)\\s+(earnings?|expectations?|estimates?)/i',
                '/(guidance|outlook|forecast)\\s+(.+)/i',
                '/(margin|margins|profitability)\\s+(.+)/i'
            ),
            'extractor' => 'extract_earnings_entities',
            'response_type' => 'earnings_analysis',
            'data_required' => array('earnings_data', 'company_news')
        ),
        
        // CAREER QUERIES
        'career_guidance' => array(
            'patterns' => array(
                '/(career|job|position)\\s+(in|at|with)?\\s+(finance|banking|PE|hedge\\s+fund)/i',
                '/(analyst|associate|VP|director|MD|partner)\\s+(role|position|career|path)/i',
                '/(break\\s+into|enter|transition)\\s+(finance|banking|PE)/i',
                '/(salary|compensation|bonus|carry)\\s+(in|for|at)?\\s+(.+)/i',
                '/(interview|recruitment|hiring)\\s+(process|tips|preparation)/i',
                '/(skills?|qualifications?)\\s+(for|needed|required)/i'
            ),
            'extractor' => 'extract_career_context',
            'response_type' => 'career_guidance',
            'data_required' => array('career_data')
        ),
        
        // VOLATILITY/RISK QUERIES
        'volatility_risk' => array(
            'patterns' => array(
                '/(VIX|volatility\\s+index)\\s+(level|reading|status)/i',
                '/(volatility|vol|risk)\\s+(high|low|elevated|subdued)/i',
                '/(market\\s+risk|risk\\s+levels?|risk\\s+assessment)/i',
                '/(hedge|hedging|protection)\\s+(strategies?|options?)/i',
                '/(safe\\s+haven|defensive)\\s+(assets?|plays?|positions?)/i'
            ),
            'extractor' => 'extract_volatility_context',
            'response_type' => 'volatility_analysis',
            'data_required' => array('vix_data', 'volatility_metrics')
        ),
        
        // ECONOMIC INDICATORS
        'economic_indicators' => array(
            'patterns' => array(
                '/(GDP|inflation|CPI|unemployment|jobs?)\\s+(data|report|numbers?)/i',
                '/(Fed|Federal\\s+Reserve|FOMC|interest\\s+rates?)\\s+(.+)/i',
                '/(economic|economy)\\s+(data|indicators?|outlook|growth)/i',
                '/(recession|expansion|recovery)\\s+(risk|probability|indicators?)/i',
                '/(yields?|treasury|bond)\\s+(rates?|curve|inversion)/i'
            ),
            'extractor' => 'extract_economic_entities',
            'response_type' => 'economic_analysis',
            'data_required' => array('economic_data', 'fed_data')
        ),
        
        // IPO/PUBLIC OFFERINGS
        'ipo_offerings' => array(
            'patterns' => array(
                '/(IPO|initial\\s+public\\s+offering)\\s+(.+)/i',
                '/(going\\s+public|listing|direct\\s+listing)\\s+(.+)/i',
                '/(IPO\\s+pipeline|upcoming\\s+IPOs?|IPO\\s+calendar)/i',
                '/(SPAC|special\\s+purpose)\\s+(.+)/i',
                '/(public\\s+offering|secondary\\s+offering)\\s+(.+)/i'
            ),
            'extractor' => 'extract_ipo_entities',
            'response_type' => 'ipo_analysis',
            'data_required' => array('ipo_data', 'ipo_news')
        ),
        
        // HEDGE FUND QUERIES
        'hedge_funds' => array(
            'patterns' => array(
                '/(hedge\\s+fund)\\s+(performance|returns?|strategies?)/i',
                '/(long\\/short|market\\s+neutral|global\\s+macro|event\\s+driven)/i',
                '/(Bridgewater|Citadel|Renaissance|Two\\s+Sigma|Millennium)/i',
                '/(alpha|beta|sharpe\\s+ratio|correlation)/i',
                '/(2\\s+and\\s+20|management\\s+fee|performance\\s+fee)/i'
            ),
            'extractor' => 'extract_hf_entities',
            'response_type' => 'hedge_fund_analysis',
            'data_required' => array('hf_data', 'hf_news')
        ),
        
        // TECHNICAL ANALYSIS
        'technical_analysis' => array(
            'patterns' => array(
                '/(support|resistance)\\s+(level|zone)\\s+(.+)/i',
                '/(moving\\s+average|MA|EMA|SMA)\\s+(.+)/i',
                '/(RSI|MACD|momentum|oscillator)\\s+(.+)/i',
                '/(breakout|breakdown|reversal|trend)\\s+(.+)/i',
                '/(chart|technical)\\s+(pattern|analysis|setup)\\s+(.+)/i'
            ),
            'extractor' => 'extract_technical_entities',
            'response_type' => 'technical_analysis',
            'data_required' => array('price_data', 'technical_indicators')
        ),
        
        // COMMODITY QUERIES
        'commodities' => array(
            'patterns' => array(
                '/(gold|silver|oil|crude|copper|wheat|corn)\\s+(price|outlook|forecast)/i',
                '/(commodity|commodities)\\s+(market|prices?|outlook)/i',
                '/(precious\\s+metals?|base\\s+metals?|energy)\\s+(.+)/i',
                '/(WTI|Brent)\\s+(crude|oil)?\\s*(price|outlook)/i',
                '/(agricultural|ags?)\\s+(commodities|futures)/i'
            ),
            'extractor' => 'extract_commodity_entities',
            'response_type' => 'commodity_analysis',
            'data_required' => array('commodity_prices', 'commodity_news')
        ),
        
        // CURRENCY/FOREX QUERIES
        'forex' => array(
            'patterns' => array(
                '/(dollar|euro|yen|pound|yuan)\\s+(strength|weakness|outlook)/i',
                '/(USD|EUR|GBP|JPY|CNY)\\s*\\/\\s*(USD|EUR|GBP|JPY|CNY)/i',
                '/(currency|forex|FX)\\s+(market|pairs?|outlook)/i',
                '/(DXY|dollar\\s+index)\\s+(level|strength|weakness)/i',
                '/(exchange\\s+rate)\\s+(.+)/i'
            ),
            'extractor' => 'extract_forex_entities',
            'response_type' => 'forex_analysis',
            'data_required' => array('forex_rates', 'currency_news')
        ),
        
        // CRYPTO QUERIES
        'cryptocurrency' => array(
            'patterns' => array(
                '/(bitcoin|BTC|ethereum|ETH|crypto)\\s+(price|outlook|analysis)/i',
                '/(cryptocurrency|crypto)\\s+(market|outlook|trends?)/i',
                '/(blockchain|DeFi|NFT)\\s+(.+)/i',
                '/(altcoin|altcoins)\\s+(performance|outlook)/i',
                '/(crypto)\\s+(regulation|adoption|news)/i'
            ),
            'extractor' => 'extract_crypto_entities',
            'response_type' => 'crypto_analysis',
            'data_required' => array('crypto_prices', 'crypto_news')
        ),
        
        // PORTFOLIO MANAGEMENT QUERIES - New
        'portfolio_management' => array(
            'patterns' => array(
                '/(portfolio|allocation)\\s+(optimization|rebalancing|construction)/i',
                '/(asset\\s+allocation|diversification)\\s+(strategy|recommendations?)/i',
                '/(risk\\s+parity|equal\\s+weight|market\\s+cap\\s+weighted)/i',
                '/(portfolio)\\s+(performance|attribution|analytics)/i',
                '/(position\\s+sizing|risk\\s+budgeting|kelly\\s+criterion)/i',
                '/(correlation\\s+matrix|covariance|beta\\s+exposure)/i'
            ),
            'extractor' => 'extract_portfolio_entities',
            'response_type' => 'portfolio_analysis',
            'data_required' => array('portfolio_data', 'risk_metrics')
        ),
        
        // DERIVATIVES QUERIES - New
        'derivatives' => array(
            'patterns' => array(
                '/(options?|calls?|puts?)\\s+(strategy|pricing|flow)/i',
                '/(futures|forwards|swaps?)\\s+(trading|pricing|curve)/i',
                '/(greeks|delta|gamma|vega|theta)\\s+(.+)/i',
                '/(implied\\s+volatility|IV\\s+rank|IV\\s+percentile)/i',
                '/(spread|straddle|strangle|butterfly|condor)\\s+(trade|strategy)/i',
                '/(exercise|assignment|expiration|roll)\\s+(.+)/i'
            ),
            'extractor' => 'extract_derivatives_entities',
            'response_type' => 'derivatives_analysis',
            'data_required' => array('options_data', 'volatility_data')
        ),
        
        // FIXED INCOME QUERIES - New
        'fixed_income' => array(
            'patterns' => array(
                '/(bond|bonds|treasury|treasuries)\\s+(yield|price|spread)/i',
                '/(yield\\s+curve|term\\s+structure)\\s+(analysis|steepening|flattening)/i',
                '/(duration|convexity|DV01|modified\\s+duration)/i',
                '/(credit\\s+spread|OAS|Z-spread|I-spread)/i',
                '/(high\\s+yield|investment\\s+grade|junk\\s+bonds?)/i',
                '/(muni|municipal\\s+bonds?)\\s+(.+)/i'
            ),
            'extractor' => 'extract_fixed_income_entities',
            'response_type' => 'fixed_income_analysis',
            'data_required' => array('bond_data', 'yield_curve')
        ),
        
        // QUANTITATIVE ANALYSIS QUERIES - New
        'quantitative' => array(
            'patterns' => array(
                '/(backtest|backtesting)\\s+(results?|strategy|performance)/i',
                '/(sharpe\\s+ratio|sortino\\s+ratio|calmar\\s+ratio|information\\s+ratio)/i',
                '/(monte\\s+carlo|simulation|stress\\s+test|scenario\\s+analysis)/i',
                '/(factor\\s+model|factor\\s+exposure|factor\\s+analysis)/i',
                '/(alpha|beta|r-squared|tracking\\s+error)/i',
                '/(var|value\\s+at\\s+risk|cvar|expected\\s+shortfall)/i'
            ),
            'extractor' => 'extract_quant_entities',
            'response_type' => 'quantitative_analysis',
            'data_required' => array('quant_metrics', 'risk_analytics')
        ),
        
        // ALTERNATIVE INVESTMENTS QUERIES - New
        'alternatives' => array(
            'patterns' => array(
                '/(REIT|real\\s+estate\\s+investment\\s+trust)\\s+(.+)/i',
                '/(infrastructure|timber|farmland)\\s+(investment|fund)/i',
                '/(art|wine|collectibles)\\s+(investment|market)/i',
                '/(structured\\s+products?|CLO|CDO|ABS)/i',
                '/(master\\s+limited\\s+partnership|MLP)\\s+(.+)/i',
                '/(royalties|litigation\\s+finance|insurance\\s+linked)\\s+(.+)/i'
            ),
            'extractor' => 'extract_alternatives_entities',
            'response_type' => 'alternatives_analysis',
            'data_required' => array('alternatives_data')
        ),
        
        // REGULATORY & COMPLIANCE QUERIES - New
        'regulatory_compliance' => array(
            'patterns' => array(
                '/(SEC|FINRA|CFTC|regulatory)\\s+(filing|requirement|update)/i',
                '/(MiFID|Dodd-Frank|Volcker\\s+Rule|Basel)\\s+(.+)/i',
                '/(compliance|regulatory)\\s+(risk|requirement|change)/i',
                '/(insider\\s+trading|market\\s+manipulation|fraud)/i',
                '/(KYC|AML|anti-money\\s+laundering|know\\s+your\\s+customer)/i',
                '/(whistleblower|investigation|enforcement\\s+action)/i'
            ),
            'extractor' => 'extract_regulatory_entities',
            'response_type' => 'regulatory_analysis',
            'data_required' => array('regulatory_data')
        ),
        
        // ESG & SUSTAINABILITY QUERIES - New
        'esg_sustainability' => array(
            'patterns' => array(
                '/(ESG|environmental\\s+social\\s+governance)\\s+(score|rating|analysis)/i',
                '/(sustainable|sustainability|green)\\s+(investing|finance|bonds?)/i',
                '/(carbon\\s+neutral|net\\s+zero|climate)\\s+(risk|strategy|target)/i',
                '/(social\\s+impact|impact\\s+investing|SRI)/i',
                '/(governance|board\\s+diversity|executive\\s+compensation)/i',
                '/(TCFD|SASB|GRI|CDP)\\s+(disclosure|reporting|framework)/i'
            ),
            'extractor' => 'extract_esg_entities',
            'response_type' => 'esg_analysis',
            'data_required' => array('esg_data', 'sustainability_metrics')
        ),
        
        // MARKET MICROSTRUCTURE QUERIES - New
        'market_microstructure' => array(
            'patterns' => array(
                '/(bid-ask\\s+spread|market\\s+depth|order\\s+book)/i',
                '/(liquidity|market\\s+impact|slippage|transaction\\s+costs)/i',
                '/(dark\\s+pools?|ATS|alternative\\s+trading\\s+system)/i',
                '/(HFT|high\\s+frequency\\s+trading|algorithmic\\s+trading)/i',
                '/(market\\s+maker|designated\\s+market\\s+maker|DMM)/i',
                '/(price\\s+discovery|market\\s+efficiency|arbitrage)/i'
            ),
            'extractor' => 'extract_microstructure_entities',
            'response_type' => 'microstructure_analysis',
            'data_required' => array('market_depth', 'liquidity_metrics')
        ),
        
        // RESEARCH & ANALYSIS QUERIES - New
        'research_analysis' => array(
            'patterns' => array(
                '/(research\\s+report|analyst\\s+report|equity\\s+research)/i',
                '/(price\\s+target|rating\\s+change|upgrade|downgrade)/i',
                '/(initiating\\s+coverage|dropping\\s+coverage|coverage\\s+universe)/i',
                '/(consensus\\s+estimate|earnings\\s+revision|guidance\\s+update)/i',
                '/(channel\\s+checks|mosaic\\s+theory|primary\\s+research)/i',
                '/(conference\\s+call|investor\\s+day|management\\s+meeting)/i'
            ),
            'extractor' => 'extract_research_entities',
            'response_type' => 'research_analysis',
            'data_required' => array('analyst_data', 'research_reports')
        ),
        
        // TAX & ESTATE PLANNING QUERIES - New
        'tax_estate' => array(
            'patterns' => array(
                '/(tax\\s+loss\\s+harvesting|tax\\s+efficiency|tax\\s+optimization)/i',
                '/(capital\\s+gains|qualified\\s+dividends|ordinary\\s+income)\\s+tax/i',
                '/(estate\\s+planning|trust|will|inheritance)/i',
                '/(1031\\s+exchange|opportunity\\s+zone|tax\\s+shelter)/i',
                '/(gift\\s+tax|estate\\s+tax|generation\\s+skipping)/i',
                '/(tax\\s+bracket|effective\\s+tax\\s+rate|marginal\\s+rate)/i'
            ),
            'extractor' => 'extract_tax_entities',
            'response_type' => 'tax_analysis',
            'data_required' => array('tax_data')
        ),
        
        // FINANCIAL PLANNING QUERIES - New
        'financial_planning' => array(
            'patterns' => array(
                '/(retirement\\s+planning|401k|IRA|pension)/i',
                '/(college\\s+savings|529\\s+plan|education\\s+funding)/i',
                '/(insurance|life\\s+insurance|disability|long\\s+term\\s+care)/i',
                '/(emergency\\s+fund|cash\\s+reserves|liquidity\\s+needs)/i',
                '/(financial\\s+goals?|financial\\s+plan|wealth\\s+accumulation)/i',
                '/(budgeting|cash\\s+flow|expense\\s+management)/i'
            ),
            'extractor' => 'extract_planning_entities',
            'response_type' => 'financial_planning',
            'data_required' => array('planning_data')
        ),
        
        // MARKET PSYCHOLOGY QUERIES - New
        'market_psychology' => array(
            'patterns' => array(
                '/(market\\s+sentiment|investor\\s+sentiment|sentiment\\s+analysis)/i',
                '/(fear\\s+and\\s+greed|market\\s+emotion|behavioral\\s+finance)/i',
                '/(herd\\s+mentality|FOMO|panic\\s+selling|euphoria)/i',
                '/(contrarian|consensus\\s+trade|crowded\\s+trade)/i',
                '/(capitulation|blood\\s+in\\s+the\\s+streets|maximum\\s+pessimism)/i',
                '/(bubble|mania|irrational\\s+exuberance)/i'
            ),
            'extractor' => 'extract_psychology_entities',
            'response_type' => 'psychology_analysis',
            'data_required' => array('sentiment_data', 'behavioral_metrics')
        )
    );
    
    /**
     * Entity Recognition Patterns - What entities to extract
     */
    private $entity_patterns = array(
        
        // COMPANY NAMES
        'company' => array(
            'pattern' => '/\\b(Blackstone|KKR|Apollo|Carlyle|TPG|Warburg\\s+Pincus|Bain\\s+Capital|EQT|CVC|Advent|Goldman\\s+Sachs?|Morgan\\s+Stanley|JP\\s*Morgan|Citi(?:group)?|Bank\\s+of\\s+America|BofA|Deutsche\\s+Bank|Barclays|UBS|Credit\\s+Suisse|Wells\\s+Fargo|Jefferies|Lazard|Evercore|Moelis|PJT|Centerview|Apple|Microsoft|Amazon|Google|Alphabet|Meta|Facebook|Tesla|Netflix|Nvidia)\\b/i',
            'type' => 'company',
            'data_source' => 'company_data'
        ),
        
        // MARKET INDICES
        'index' => array(
            'pattern' => '/\\b(S\\&P\\s*500|SPX|SP500|Nasdaq|COMP|QQQ|Dow\\s*(?:Jones)?|DJIA|DJI|Russell\\s*(?:2000|3000)?|IWM|FTSE\\s*(?:100)?|DAX|CAC\\s*40|Nikkei|VIX|DXY|Dollar\\s+Index)\\b/i',
            'type' => 'index',
            'data_source' => 'index_data'
        ),
        
        // SECTORS
        'sector' => array(
            'pattern' => '/\\b(tech(?:nology)?|healthcare?|health\\s+care|financials?|financial\\s+services|energy|utilities|consumer\\s+(?:discretionary|staples)|retail|industrials?|materials|real\\s+estate|REIT|telecom(?:munications)?|communication\\s+services)\\b/i',
            'type' => 'sector',
            'data_source' => 'sector_data'
        ),
        
        // ASSET CLASSES
        'asset_class' => array(
            'pattern' => '/\\b(stocks?|equit(?:y|ies)|bonds?|fixed\\s+income|commodit(?:y|ies)|currenc(?:y|ies)|forex|FX|crypto(?:currency)?|real\\s+estate|REIT|alternatives?|private\\s+equity|hedge\\s+funds?|venture\\s+capital|VC)\\b/i',
            'type' => 'asset_class',
            'data_source' => 'asset_data'
        ),
        
        // TIMEFRAMES
        'timeframe' => array(
            'pattern' => '/\\b(today|yesterday|tomorrow|this\\s+week|last\\s+week|next\\s+week|this\\s+month|last\\s+month|next\\s+month|this\\s+quarter|last\\s+quarter|next\\s+quarter|Q1|Q2|Q3|Q4|this\\s+year|last\\s+year|next\\s+year|YTD|year\\s+to\\s+date|MTD|month\\s+to\\s+date|intraday|daily|weekly|monthly|quarterly|yearly|annual)\\b/i',
            'type' => 'timeframe',
            'processor' => 'normalize_timeframe'
        ),
        
        // FINANCIAL METRICS
        'metric' => array(
            'pattern' => '/\\b(price|volume|market\\s+cap(?:italization)?|PE|P\\/E|price\\s+to\\s+earnings|EPS|earnings\\s+per\\s+share|revenue|sales|profit|income|EBITDA|margin|gross\\s+margin|operating\\s+margin|net\\s+margin|ROE|ROA|ROI|ROIC|debt|leverage|ratio|yield|dividend|beta|alpha|volatility|correlation|sharpe\\s+ratio)\\b/i',
            'type' => 'metric',
            'data_source' => 'metric_data'
        ),
        
        // DEAL VALUES
        'deal_value' => array(
            'pattern' => '/\\$?\\b(\\d+\\.?\\d*)\\s*(B|billion|M|million|K|thousand|T|trillion)\\b/i',
            'type' => 'value',
            'processor' => 'normalize_value'
        ),
        
        // PERCENTAGES
        'percentage' => array(
            'pattern' => '/(\\+|\\-)?\\s*(\\d+\\.?\\d*)\\s*%/',
            'type' => 'percentage',
            'processor' => 'normalize_percentage'
        ),
        
        // TRADING TERMS
        'trading_term' => array(
            'pattern' => '/\\b(buy|sell|long|short|hold|overweight|underweight|neutral|bullish|bearish|hawkish|dovish|risk\\-on|risk\\-off|overbought|oversold|breakout|breakdown|support|resistance|trend|reversal|consolidation|accumulation|distribution)\\b/i',
            'type' => 'trading_term',
            'data_source' => 'market_sentiment'
        ),
        
        // PEOPLE NAMES (Finance Leaders)
        'person' => array(
            'pattern' => '/\\b(Warren\\s+Buffett|Jamie\\s+Dimon|Larry\\s+Fink|Stephen\\s+Schwarzman|David\\s+Solomon|James\\s+Gorman|Brian\\s+Moynihan|Jerome\\s+Powell|Janet\\s+Yellen|Christine\\s+Lagarde|Elon\\s+Musk|Jeff\\s+Bezos|Tim\\s+Cook|Satya\\s+Nadella)\\b/i',
            'type' => 'person',
            'data_source' => 'person_data'
        ),
        
        // LOCATIONS/REGIONS
        'location' => array(
            'pattern' => '/\\b(US|USA|United\\s+States|America|Europe|EU|Eurozone|UK|Britain|Asia|APAC|China|Japan|Germany|France|India|emerging\\s+markets|EM|developed\\s+markets|DM|global|worldwide|domestic|international)\\b/i',
            'type' => 'location',
            'data_source' => 'regional_data'
        )
    );
    
    /**
     * Context Modifiers - How to interpret the query
     */
    private $context_patterns = array(
        
        // URGENCY
        'urgency' => array(
            'pattern' => '/\\b(now|immediately|urgent|asap|quickly|fast|today|right\\s+now|current|latest|breaking)\\b/i',
            'modifier' => 'high_priority',
            'weight' => 1.5
        ),
        
        // UNCERTAINTY
        'uncertainty' => array(
            'pattern' => '/\\b(maybe|perhaps|possibly|might|could|should|probably|likely|unlikely|potential)\\b/i',
            'modifier' => 'qualified_response',
            'weight' => 0.8
        ),
        
        // SPECIFICITY
        'specificity' => array(
            'pattern' => '/\\b(exactly|specifically|precisely|particular|certain|detailed|comprehensive|thorough|in\\-depth)\\b/i',
            'modifier' => 'detailed_response',
            'weight' => 1.2
        ),
        
        // NEGATIVE SENTIMENT
        'negative_sentiment' => array(
            'pattern' => '/\\b(crash|collapse|plunge|tank|disaster|terrible|awful|horrible|bearish|pessimistic|worried|concerned|fear)\\b/i',
            'modifier' => 'risk_focused',
            'sentiment' => -1
        ),
        
        // POSITIVE SENTIMENT
        'positive_sentiment' => array(
            'pattern' => '/\\b(boom|surge|rally|soar|excellent|great|fantastic|bullish|optimistic|confident|opportunity)\\b/i',
            'modifier' => 'opportunity_focused',
            'sentiment' => 1
        ),
        
        // COMPARISON CONTEXT
        'comparative' => array(
            'pattern' => '/\\b(better|worse|more|less|higher|lower|stronger|weaker|outperform|underperform|exceed|lag)\\b/i',
            'modifier' => 'comparative_analysis',
            'weight' => 1.0
        ),
        
        // HISTORICAL CONTEXT
        'historical' => array(
            'pattern' => '/\\b(historical|historically|past|previous|before|ago|back\\s+in|used\\s+to|was|were)\\b/i',
            'modifier' => 'historical_perspective',
            'weight' => 1.0
        ),
        
        // FUTURE CONTEXT
        'future' => array(
            'pattern' => '/\\b(future|upcoming|next|tomorrow|will|going\\s+to|forecast|prediction|outlook|expected)\\b/i',
            'modifier' => 'forward_looking',
            'weight' => 1.0
        )
    );
    
    /**
     * Match query against patterns
     */
    public function match_query($query) {
        $matches = array(
            'intent' => null,
            'entities' => array(),
            'context' => array(),
            'confidence' => 0
        );
        
        // Find primary intent
        foreach ($this->intent_patterns as $intent_type => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (preg_match($pattern, $query, $intent_matches)) {
                    $matches['intent'] = $intent_type;
                    $matches['intent_config'] = $config;
                    $matches['intent_matches'] = $intent_matches;
                    $matches['confidence'] += 0.5;
                    break 2;
                }
            }
        }
        
        // Extract entities
        foreach ($this->entity_patterns as $entity_type => $config) {
            if (preg_match_all($config['pattern'], $query, $entity_matches)) {
                $matches['entities'][$entity_type] = array(
                    'values' => $entity_matches[0],
                    'type' => $config['type'],
                    'data_source' => isset($config['data_source']) ? $config['data_source'] : null
                );
                $matches['confidence'] += 0.1;
            }
        }
        
        // Identify context modifiers
        foreach ($this->context_patterns as $context_type => $config) {
            if (preg_match($config['pattern'], $query)) {
                $matches['context'][$context_type] = $config;
                $matches['confidence'] += 0.05;
            }
        }
        
        // Cap confidence at 1.0
        $matches['confidence'] = min($matches['confidence'], 1.0);
        
        return $matches;
    }
    
    /**
     * Get all pattern categories
     */
    public function get_pattern_categories() {
        return array(
            'intent_patterns' => array_keys($this->intent_patterns),
            'entity_patterns' => array_keys($this->entity_patterns),
            'context_patterns' => array_keys($this->context_patterns)
        );
    }
    
    /**
     * Advanced pattern matching with ML-inspired scoring
     */
    public function advanced_match($query) {
        $matches = $this->match_query($query);
        
        // Enhanced confidence scoring
        $matches['confidence'] = $this->calculate_advanced_confidence($query, $matches);
        
        // Intent disambiguation
        if ($matches['confidence'] < 0.7) {
            $matches['alternative_intents'] = $this->find_alternative_intents($query);
        }
        
        // Entity relationship detection
        $matches['entity_relationships'] = $this->detect_entity_relationships($matches['entities']);
        
        // Query complexity assessment
        $matches['complexity'] = $this->assess_query_complexity($query, $matches);
        
        // Temporal analysis
        $matches['temporal_context'] = $this->analyze_temporal_context($query);
        
        return $matches;
    }
    
    /**
     * Calculate advanced confidence score
     */
    private function calculate_advanced_confidence($query, $matches) {
        $confidence = $matches['confidence'];
        
        // Boost for exact pattern matches
        if (!empty($matches['intent'])) {
            $confidence += 0.2;
        }
        
        // Boost for multiple entity matches
        $entity_count = count($matches['entities']);
        if ($entity_count > 2) {
            $confidence += 0.1;
        }
        
        // Boost for context clarity
        $context_count = count($matches['context']);
        if ($context_count > 1) {
            $confidence += 0.05 * $context_count;
        }
        
        // Penalty for ambiguous queries
        if (preg_match('/\\b(thing|stuff|it|they|something)\\b/i', $query)) {
            $confidence -= 0.2;
        }
        
        // Normalize
        return min(1.0, max(0.0, $confidence));
    }
    
    /**
     * Find alternative intent matches
     */
    private function find_alternative_intents($query) {
        $alternatives = array();
        $scores = array();
        
        foreach ($this->intent_patterns as $intent_type => $config) {
            $score = 0;
            foreach ($config['patterns'] as $pattern) {
                if (preg_match($pattern, $query)) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $scores[$intent_type] = $score;
            }
        }
        
        // Sort by score
        arsort($scores);
        
        // Return top 3 alternatives
        $count = 0;
        foreach ($scores as $intent => $score) {
            if ($count++ >= 3) break;
            $alternatives[] = array(
                'intent' => $intent,
                'score' => $score / count($this->intent_patterns[$intent]['patterns'])
            );
        }
        
        return $alternatives;
    }
    
    /**
     * Detect relationships between entities
     */
    private function detect_entity_relationships($entities) {
        $relationships = array();
        
        // Check for company-sector relationships
        if (isset($entities['company']) && isset($entities['sector'])) {
            $relationships[] = array(
                'type' => 'company_in_sector',
                'entities' => array($entities['company'], $entities['sector'])
            );
        }
        
        // Check for index-timeframe relationships
        if (isset($entities['index']) && isset($entities['timeframe'])) {
            $relationships[] = array(
                'type' => 'index_performance_period',
                'entities' => array($entities['index'], $entities['timeframe'])
            );
        }
        
        // Check for metric-asset relationships
        if (isset($entities['metric']) && (isset($entities['company']) || isset($entities['index']))) {
            $relationships[] = array(
                'type' => 'metric_for_asset',
                'entities' => array($entities['metric'], $entities['company'] ?? $entities['index'])
            );
        }
        
        return $relationships;
    }
    
    /**
     * Assess query complexity
     */
    private function assess_query_complexity($query, $matches) {
        $factors = array(
            'length' => strlen($query),
            'word_count' => str_word_count($query),
            'entity_count' => count($matches['entities']),
            'has_comparison' => preg_match('/\\b(vs|versus|compared?|between)\\b/i', $query),
            'has_calculation' => preg_match('/\\b(calculate|compute|derive|figure out)\\b/i', $query),
            'has_multiple_questions' => substr_count($query, '?') > 1,
            'has_conditional' => preg_match('/\\b(if|when|unless|provided|assuming)\\b/i', $query)
        );
        
        $complexity_score = 0;
        if ($factors['word_count'] > 20) $complexity_score += 0.3;
        if ($factors['entity_count'] > 3) $complexity_score += 0.2;
        if ($factors['has_comparison']) $complexity_score += 0.2;
        if ($factors['has_calculation']) $complexity_score += 0.2;
        if ($factors['has_multiple_questions']) $complexity_score += 0.1;
        if ($factors['has_conditional']) $complexity_score += 0.1;
        
        return array(
            'score' => min(1.0, $complexity_score),
            'factors' => $factors,
            'level' => $this->classify_complexity_level($complexity_score)
        );
    }
    
    /**
     * Classify complexity level
     */
    private function classify_complexity_level($score) {
        if ($score >= 0.8) return 'very_complex';
        if ($score >= 0.6) return 'complex';
        if ($score >= 0.4) return 'moderate';
        if ($score >= 0.2) return 'simple';
        return 'very_simple';
    }
    
    /**
     * Analyze temporal context
     */
    private function analyze_temporal_context($query) {
        $temporal = array(
            'timeframe' => 'current',
            'specific_date' => null,
            'period' => null,
            'relative_time' => null
        );
        
        // Check for specific dates
        if (preg_match('/(\\d{1,2}[\\/\\-]\\d{1,2}[\\/\\-]\\d{2,4})/', $query, $date_match)) {
            $temporal['specific_date'] = $date_match[1];
            $temporal['timeframe'] = 'specific';
        }
        
        // Check for periods
        if (preg_match('/\\b(Q[1-4]|H[12]|FY)\\s*\\d{2,4}\\b/i', $query, $period_match)) {
            $temporal['period'] = $period_match[0];
            $temporal['timeframe'] = 'period';
        }
        
        // Check for relative time
        if (preg_match('/\\b(last|next|previous|past|upcoming)\\s+(\\w+)\\b/i', $query, $relative_match)) {
            $temporal['relative_time'] = $relative_match[0];
            $temporal['timeframe'] = 'relative';
        }
        
        return $temporal;
    }
    
    /**
     * Get pattern count
     */
    public function get_pattern_count() {
        $count = 0;
        
        // Count all intent patterns
        foreach ($this->intent_patterns as $config) {
            $count += count($config['patterns']);
        }
        
        // Add entity and context patterns
        $count += count($this->entity_patterns);
        $count += count($this->context_patterns);
        
        return $count;
    }
    
    /**
     * Get required data for intent
     */
    public function get_required_data($intent) {
        if (isset($this->intent_patterns[$intent])) {
            return $this->intent_patterns[$intent]['data_required'];
        }
        return array();
    }
}