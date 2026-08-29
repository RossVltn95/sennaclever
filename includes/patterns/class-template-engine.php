<?php
/**
 * Template Engine - Phase 3
 * Dynamic template selection and rendering for response generation
 * 
 * @package SennaCareers
 * @since 6.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Template_Engine {
    
    private static $instance = null;
    private $templates = array();
    private $variables = array();
    private $cache = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initialize_templates();
    }
    
    /**
     * Initialize comprehensive template library
     */
    private function initialize_templates() {
        // MARKET STATUS TEMPLATES - Expanded
        $this->templates['market_status'] = array(
            'brief' => 'Markets {direction} with {index_leader} leading at {change}. {sector_highlight}',
            'detailed' => 'The {market_period} session shows {trend} momentum. {index_summary}. {sector_analysis}. {volume_note}',
            'technical' => '{indices_performance}. Technical indicators suggest {market_sentiment}. {support_resistance}',
            'news_driven' => 'Markets reacting to {news_catalyst}. {index_impacts}. {outlook}',
            'pre_market' => 'Pre-market futures {futures_direction} pointing to {expected_open}. {overnight_developments}. {key_events_today}',
            'after_hours' => 'After-hours trading shows {ah_movement}. {notable_movers}. {earnings_reactions}',
            'weekly_wrap' => '{week_performance} for major indices. {weekly_winners}. {weekly_losers}. {next_week_outlook}',
            'monthly_review' => '{month_name} delivered {monthly_return}. {monthly_themes}. {sector_rotation}. {month_ahead}',
            'volatility_spike' => 'VIX surged to {vix_level} as {volatility_trigger}. {defensive_rotation}. {risk_management}',
            'market_correction' => 'Markets entered correction territory with {drawdown}. {correction_catalyst}. {support_levels}. {recovery_timeline}'
        );
        
        // CAREER GUIDANCE TEMPLATES - Expanded
        $this->templates['career_guidance'] = array(
            'entry_level' => 'For breaking into {industry}, focus on {key_skills}. {networking_tip}. {application_strategy}',
            'experienced' => 'With your background, target {role_suggestions}. {skill_gaps}. {career_pivot}',
            'executive' => 'At senior level, {leadership_focus}. {strategic_positioning}. {board_readiness}',
            'analyst_path' => 'Investment analyst roles require {technical_skills}. {cfa_guidance}. {typical_progression}. Starting comp: {salary_range}',
            'pe_career' => 'PE careers demand {pe_requirements}. {deal_experience}. Target firms: {pe_firms}. {pe_compensation}',
            'ib_track' => 'Investment banking path: {ib_timeline}. {boutique_vs_bulge}. {exit_opportunities}. {lifestyle_reality}',
            'hedge_fund' => 'Hedge fund roles need {hf_skills}. {strategy_focus}. {performance_metrics}. {hf_compensation}',
            'corp_dev' => 'Corporate development combines {corpdev_skills}. {internal_ma}. {strategic_planning}. {career_trajectory}',
            'equity_research' => 'Equity research requires {er_skills}. {sector_specialization}. {buy_vs_sell_side}. {er_compensation}',
            'venture_capital' => 'VC careers need {vc_background}. {sourcing_skills}. {portfolio_management}. {carry_structure}',
            'wealth_management' => 'Wealth management focuses on {wm_skills}. {client_acquisition}. {aum_targets}. {wm_compensation}',
            'fintech' => 'Fintech roles blend {fintech_skills}. {traditional_vs_startup}. {equity_component}. {growth_potential}',
            'quant_finance' => 'Quant roles require {quant_skills}. {programming_languages}. {strategy_types}. {quant_compensation}',
            'risk_management' => 'Risk management needs {risk_skills}. {regulatory_knowledge}. {risk_frameworks}. {risk_compensation}',
            'compliance' => 'Compliance careers require {compliance_skills}. {regulatory_landscape}. {certification_path}. {compliance_growth}'
        );
        
        // COMPANY ANALYSIS TEMPLATES - Expanded
        $this->templates['company_analysis'] = array(
            'snapshot' => '{company} ({ticker}) trading at {price}, {change_direction} {change}. {market_cap_category}',
            'fundamental' => '{company} shows {financial_health} with PE of {pe_ratio}. {revenue_trend}. {analyst_consensus}',
            'comparative' => '{company} {performance_vs_sector} sector average. {competitive_position}. {key_differentiator}',
            'earnings_preview' => '{company} reports {earnings_date}. Consensus EPS: {eps_estimate}. {whisper_number}. {key_metrics_watch}',
            'earnings_reaction' => '{company} {beat_or_miss} with EPS of {actual_eps} vs {expected_eps}. {revenue_performance}. {guidance_update}',
            'valuation_deep' => 'At {price}, {company} trades at {pe_ratio}x earnings, {price_to_book}x book, {ev_ebitda}x EBITDA. {dcf_value}. {valuation_verdict}',
            'competitive_analysis' => '{company} ranks {market_position} with {market_share}% share. Key competitors: {competitors}. {competitive_advantages}',
            'management_review' => 'CEO {ceo_name} has delivered {ceo_performance}. {management_changes}. {insider_activity}. {governance_score}',
            'dividend_analysis' => '{company} yields {dividend_yield}% with {payout_ratio}% payout. {dividend_history}. {dividend_safety}. {growth_prospects}',
            'debt_analysis' => '{company} has {debt_to_equity} D/E ratio, {interest_coverage}x interest coverage. {credit_rating}. {refinancing_needs}',
            'growth_metrics' => '{company} growing revenue at {revenue_cagr}% CAGR. {margin_expansion}. {market_expansion}. {growth_drivers}',
            'catalyst_watch' => 'Key catalysts for {company}: {catalyst_1}, {catalyst_2}, {catalyst_3}. {timeline}. {potential_impact}',
            'technical_setup' => '{company} showing {chart_pattern} pattern. RSI: {rsi}. MACD: {macd_signal}. {technical_outlook}',
            'options_activity' => 'Unusual options activity in {company}: {options_flow}. {put_call_ratio}. {implied_volatility}. {options_sentiment}',
            'esg_analysis' => '{company} ESG score: {esg_rating}. {environmental_score}. {social_score}. {governance_score}. {esg_risks}'
        );
        
        // PRIVATE EQUITY TEMPLATES - Expanded
        $this->templates['private_equity'] = array(
            'market_overview' => 'PE market {activity_level} with {deal_volume} in deals. {sector_focus}. {exit_environment}',
            'fund_analysis' => '{fund_name} {fundraising_status} targeting {fund_size}. {investment_thesis}. {track_record}',
            'deal_insight' => '{deal_type} activity {trend} in {sector}. {valuation_multiples}. {key_drivers}',
            'lbo_analysis' => '{target} LBO at {purchase_multiple}x EBITDA. {leverage_ratio}x debt. {equity_contribution}. {irr_target}. {exit_strategy}',
            'portfolio_update' => '{fund_name} portfolio: {portfolio_companies} companies, {portfolio_value} value. {recent_exits}. {troubled_assets}',
            'fundraising' => '{fund_name} raising Fund {fund_number} targeting {target_size}. {first_close}. {anchor_investors}. {fund_terms}',
            'exit_analysis' => '{portfolio_company} exit via {exit_type} at {exit_multiple}x. {holding_period} year hold. {irr_achieved}%. {moic}x MOIC',
            'sector_focus' => 'PE focusing on {hot_sectors}. Avoiding {cold_sectors}. {sector_multiples}. {sector_dynamics}',
            'dry_powder' => 'Global dry powder at {dry_powder_amount}. {deployment_pressure}. {vintage_performance}. {fundraising_environment}',
            'secondaries' => 'Secondary market {secondary_activity}. {gp_led_deals}. {lp_portfolio_sales}. {pricing_trends}',
            'co_investment' => '{fund_name} offering co-invest in {target_company}. {deal_size}. {co_invest_terms}. {expected_returns}',
            'distressed' => 'Distressed opportunity in {company}. {distress_trigger}. {restructuring_plan}. {recovery_value}',
            'growth_equity' => '{company} raising {round_size} growth round at {valuation}. {growth_metrics}. {use_of_proceeds}. {investor_syndicate}',
            'venture_debt' => '{company} secured {debt_amount} venture debt from {lender}. {warrant_coverage}. {debt_terms}. {runway_extension}',
            'roll_up' => '{platform} executing roll-up in {industry}. {acquisitions_completed}. {acquisition_pipeline}. {synergy_targets}'
        );
        
        // M&A TEMPLATES - Expanded
        $this->templates['mergers_acquisitions'] = array(
            'deal_announcement' => '{acquirer} {action} {target} for {value}. {strategic_rationale}. {completion_timeline}',
            'market_activity' => 'M&A activity {trend} with {volume} deals worth {total_value}. {sector_hotspots}',
            'analysis' => '{deal_logic}. {synergy_potential}. {integration_challenges}. {market_reaction}',
            'hostile_takeover' => '{acquirer} launched hostile bid for {target} at {price_per_share}. {premium_offered}%. {defense_strategy}. {success_probability}',
            'merger_arb' => '{target} trading at {current_price} vs {deal_price} offer. {spread_percentage}% spread. {closing_risk}. {expected_close}',
            'bidding_war' => 'Bidding war for {target} between {bidder_1} and {bidder_2}. Current bid: {highest_bid}. {next_move}. {winner_prediction}',
            'regulatory_review' => '{deal} under {regulatory_body} review. {antitrust_concerns}. {remedies_required}. {approval_timeline}',
            'deal_break' => '{deal} terminated due to {break_reason}. {breakup_fee}. {next_steps}. {market_impact}',
            'synergy_analysis' => '{acquirer}-{target} merger targeting {synergy_amount} synergies. {cost_synergies}. {revenue_synergies}. {realization_timeline}',
            'financing' => '{acquirer} financing {deal_value} acquisition with {debt_portion} debt, {equity_portion} equity. {financing_sources}. {leverage_impact}',
            'spac_merger' => '{target} going public via SPAC merger with {spac_name} at {valuation}. {pipe_investment}. {redemption_rate}. {closing_timeline}',
            'cross_border' => '{acquirer} ({country_1}) acquiring {target} ({country_2}) for {value}. {regulatory_hurdles}. {currency_impact}. {tax_implications}',
            'activist_driven' => 'Activist {activist_name} pushing {company} to {action}. {stake_size}%. {demands}. {likely_outcome}',
            'divestiture' => '{company} divesting {division} for {value}. {strategic_reason}. {use_of_proceeds}. {remaining_business}',
            'joint_venture' => '{company_1} and {company_2} forming JV for {purpose}. {ownership_split}. {investment_amount}. {strategic_benefits}'
        );
        
        // ECONOMIC INDICATORS TEMPLATES - Expanded
        $this->templates['economic_data'] = array(
            'indicators' => 'GDP {gdp_direction} at {gdp_rate}%, inflation {inflation_status} at {inflation_rate}%. {fed_outlook}',
            'employment' => 'Unemployment {unemployment_trend} to {unemployment_rate}%. {job_growth}. {wage_pressure}',
            'market_impact' => 'Economic data {impact_direction} markets. {sector_implications}. {investment_thesis}',
            'fed_decision' => 'Fed {rate_action} rates by {basis_points}bps to {new_rate}%. {dot_plot}. {powell_commentary}. {market_reaction}',
            'inflation_report' => 'CPI came in at {cpi_actual}% vs {cpi_expected}% expected. Core CPI: {core_cpi}%. {inflation_components}. {fed_implications}',
            'jobs_report' => 'Nonfarm payrolls {payroll_number} vs {payroll_expected} expected. {unemployment_rate}%. {wage_growth}%. {participation_rate}%',
            'gdp_release' => 'GDP grew {gdp_actual}% vs {gdp_expected}% expected. {gdp_components}. {economic_narrative}. {forecast_revision}',
            'retail_sales' => 'Retail sales {retail_direction} {retail_change}% MoM. {consumer_strength}. {category_breakdown}. {holiday_impact}',
            'housing_data' => 'Housing starts at {housing_starts}K. Building permits: {permits}. {mortgage_rates}%. {housing_outlook}',
            'manufacturing' => 'ISM Manufacturing at {ism_level}. {above_below_50}. {new_orders}. {employment_component}. {prices_paid}',
            'consumer_confidence' => 'Consumer confidence at {confidence_level}. {expectations_index}. {present_situation}. {spending_implications}',
            'trade_balance' => 'Trade deficit at {deficit_amount}. Exports: {exports}. Imports: {imports}. {trade_dynamics}',
            'recession_watch' => 'Recession probability at {recession_prob}%. {yield_curve_status}. {leading_indicators}. {economist_consensus}',
            'global_economy' => 'Global growth at {global_gdp}%. China: {china_gdp}%. EU: {eu_gdp}%. {emerging_markets}. {trade_tensions}',
            'central_banks' => 'ECB {ecb_action}. BOJ {boj_action}. BOE {boe_action}. {policy_divergence}. {currency_impacts}'
        );
        
        // NEWS TEMPLATES - Expanded
        $this->templates['news_summary'] = array(
            'breaking' => 'BREAKING: {headline}. {immediate_impact}. {market_reaction}',
            'digest' => 'Key developments: {news_points}. {market_implications}. {outlook}',
            'analysis' => '{news_context}. {stakeholder_impact}. {forward_looking}',
            'earnings_news' => '{company} {beat_miss} earnings. EPS: {eps_actual} vs {eps_estimate}. Revenue: {revenue_actual} vs {revenue_estimate}. Stock {stock_move}%',
            'regulatory_news' => '{regulatory_body} announced {regulatory_action}. {affected_companies}. {compliance_deadline}. {industry_impact}',
            'scandal_news' => '{company} facing {scandal_type}. {allegations}. {company_response}. {legal_implications}. Stock down {stock_decline}%',
            'product_launch' => '{company} launched {product_name}. {product_details}. {market_opportunity}. {competitive_impact}. {revenue_potential}',
            'partnership_news' => '{company_1} partners with {company_2} for {partnership_purpose}. {deal_terms}. {strategic_benefits}. {market_response}',
            'bankruptcy_news' => '{company} filed Chapter {chapter_type} bankruptcy. {debt_amount}. {restructuring_plan}. {creditor_recovery}',
            'ipo_news' => '{company} IPO priced at {ipo_price}. {shares_offered}. {valuation}. {first_day_performance}. {investor_demand}',
            'activist_news' => '{activist} takes {stake_size}% stake in {company}. {demands}. {board_changes}. {strategic_changes}',
            'lawsuit_news' => '{plaintiff} sues {defendant} for {lawsuit_amount}. {allegations}. {legal_merit}. {settlement_likelihood}',
            'trade_news' => 'Trade tensions between {country_1} and {country_2}. {tariff_details}. {affected_sectors}. {resolution_timeline}',
            'crypto_news' => '{crypto_asset} {crypto_movement} to {crypto_price}. {crypto_catalyst}. {regulatory_development}. {institutional_adoption}',
            'commodity_news' => '{commodity} prices {commodity_direction} to {commodity_price}. {supply_demand}. {geopolitical_factors}. {forecast}'
        );
        
        // TECHNICAL ANALYSIS TEMPLATES - Expanded
        $this->templates['technical_analysis'] = array(
            'indicators' => '{asset} RSI at {rsi_value} ({rsi_signal}). {moving_average_status}. {volume_analysis}',
            'chart_pattern' => '{pattern_identified} forming on {timeframe}. {breakout_level}. {target_price}',
            'trend' => '{trend_direction} trend {trend_strength}. {support_levels}. {resistance_levels}',
            'momentum' => '{asset} momentum {momentum_status}. RSI: {rsi}. MACD: {macd_status}. Stochastics: {stoch_status}. {momentum_verdict}',
            'moving_averages' => '{asset} trading {ma_position} key MAs. 50-day: {ma_50}. 200-day: {ma_200}. {golden_death_cross}. {ma_trend}',
            'fibonacci' => '{asset} respecting Fibonacci levels. {fib_support} support at {fib_support_level}. {fib_resistance} resistance at {fib_resistance_level}',
            'elliott_wave' => '{asset} in Wave {wave_number} of {wave_degree}. {wave_target}. {invalidation_level}. {next_wave_projection}',
            'volume_profile' => 'Volume profile shows {volume_node} at {price_level}. {accumulation_distribution}. {volume_trend}. {smart_money}',
            'market_structure' => '{asset} market structure {structure_status}. {higher_highs_lows}. {trend_change_signal}. {structure_target}',
            'supply_demand' => 'Supply zone at {supply_level}. Demand zone at {demand_level}. {zone_strength}. {trading_plan}',
            'divergence' => '{divergence_type} divergence detected on {indicator}. Price: {price_action}. Indicator: {indicator_action}. {divergence_implications}',
            'ichimoku' => 'Ichimoku shows {ichimoku_signal}. Price {kumo_position} cloud. TK cross: {tk_status}. {future_cloud}',
            'options_flow' => 'Options flow {bullish_bearish}. Put/Call: {put_call_ratio}. GEX: {gex_level}. {max_pain}. {dealer_positioning}',
            'breadth' => 'Market breadth {breadth_status}. A/D line: {advance_decline}. New highs/lows: {new_highs_lows}. {breadth_divergence}',
            'sentiment' => 'Sentiment indicators {sentiment_reading}. Fear/Greed: {fear_greed}. VIX: {vix}. Put/Call: {pc_ratio}. {sentiment_extreme}'
        );
        
        // SECTOR ANALYSIS TEMPLATES - New
        $this->templates['sector_analysis'] = array(
            'performance' => '{sector} sector {performance_status} with {sector_return}% return. {outperform_underperform} market by {relative_performance}%',
            'rotation' => 'Sector rotation from {rotating_out} into {rotating_into}. {rotation_catalyst}. {rotation_strength}. {duration_expectation}',
            'fundamental' => '{sector} trading at {sector_pe}x PE vs historical {historical_pe}x. {valuation_assessment}. {earnings_outlook}',
            'leaders_laggards' => '{sector} leaders: {top_performers}. Laggards: {bottom_performers}. {performance_drivers}. {outlook}',
            'correlation' => '{sector} showing {correlation_level} correlation with {correlated_factor}. {correlation_implications}. {hedging_opportunity}',
            'cyclical' => '{sector} in {cycle_phase} phase of cycle. {cycle_drivers}. {cycle_duration}. {positioning_recommendation}',
            'thematic' => '{theme_name} theme driving {beneficiary_sectors}. {theme_catalyst}. {investment_opportunities}. {theme_duration}',
            'regulatory_impact' => '{regulation} impacting {sector}. {compliance_costs}. {competitive_dynamics}. {investment_implications}',
            'technology_disruption' => '{technology} disrupting {traditional_sector}. {disruption_timeline}. {winners_losers}. {adaptation_strategies}',
            'esg_sector' => '{sector} ESG transformation. {esg_leaders}. {esg_laggards}. {esg_opportunities}. {regulatory_pressure}'
        );
        
        // CRYPTOCURRENCY TEMPLATES - New
        $this->templates['cryptocurrency'] = array(
            'price_action' => '{crypto} trading at {price}, {change_direction} {change_percent}% in 24h. Market cap: {market_cap}. {dominance}% dominance',
            'defi_update' => 'Total Value Locked (TVL): {tvl}. {protocol_name} leads with {protocol_tvl}. {yield_opportunities}. {risk_factors}',
            'blockchain_metrics' => '{blockchain} metrics: {transaction_count} transactions. {active_addresses} active addresses. {hash_rate}. {network_value}',
            'institutional' => '{institution} {action} {amount} in {crypto}. {institutional_trend}. {market_impact}. {future_flows}',
            'regulatory_crypto' => '{country} announced {crypto_regulation}. {regulatory_stance}. {industry_impact}. {compliance_requirements}',
            'nft_market' => 'NFT volume at {nft_volume}. Top collection: {top_collection}. Floor price: {floor_price}. {nft_trend}',
            'mining_update' => '{crypto} mining difficulty {difficulty_change}. Hash rate: {hash_rate}. {miner_revenue}. {mining_profitability}',
            'staking_yields' => '{crypto} staking yields {staking_apy}% APY. {staking_requirements}. {lock_period}. {staking_risks}',
            'layer2_update' => '{layer2_name} processing {transaction_volume}. {scaling_metrics}. {adoption_rate}. {competitive_position}',
            'crypto_correlation' => '{crypto} correlation with {traditional_asset}: {correlation_coefficient}. {correlation_trend}. {portfolio_implications}'
        );
        
        // COMMODITIES TEMPLATES - New
        $this->templates['commodities'] = array(
            'energy_update' => 'WTI Crude at ${oil_price}, {oil_change}%. Natural Gas: ${gas_price}. {supply_demand}. {geopolitical_factors}',
            'precious_metals' => 'Gold at ${gold_price}, {gold_change}%. Silver: ${silver_price}. {dollar_correlation}. {safe_haven_demand}',
            'agriculture' => '{commodity} prices {direction} on {catalyst}. {weather_impact}. {supply_outlook}. {demand_factors}',
            'industrial_metals' => 'Copper at ${copper_price} signaling {economic_signal}. {china_demand}. {supply_constraints}. {ev_demand}',
            'commodity_futures' => '{commodity} futures curve in {contango_backwardation}. {term_structure}. {roll_yield}. {storage_costs}',
            'commodity_etf' => '{etf_name} {etf_flow} with {flow_amount} {inflow_outflow}. {etf_premium_discount}. {tracking_performance}',
            'supply_shock' => '{commodity} supply disrupted by {disruption_cause}. {supply_loss}. {price_impact}. {duration_estimate}',
            'demand_shift' => '{commodity} demand {demand_change} due to {demand_driver}. {structural_cyclical}. {price_forecast}',
            'commodity_correlation' => '{commodity} showing {correlation} correlation with {correlated_asset}. {trading_opportunity}. {hedge_ratio}',
            'commodity_seasonality' => '{commodity} entering {seasonal_period}. Historical performance: {historical_return}%. {seasonal_factors}'
        );
        
        // FOREX TEMPLATES - New
        $this->templates['forex'] = array(
            'currency_pair' => '{pair} trading at {rate}, {change_direction} {change_pips} pips. {daily_range}. {volatility_level}',
            'central_bank_fx' => '{central_bank} {policy_action} impacting {currency}. {rate_differential}. {carry_trade}. {currency_outlook}',
            'dollar_index' => 'DXY at {dxy_level}, {dxy_change}%. {dollar_strength_weakness}. {global_implications}. {fed_policy_impact}',
            'emerging_fx' => '{em_currency} {em_movement} on {em_catalyst}. {risk_sentiment}. {capital_flows}. {em_outlook}',
            'fx_intervention' => '{country} intervened in FX market. {intervention_size}. {intervention_effectiveness}. {market_reaction}',
            'currency_forecast' => '{pair} forecast: {target_level} by {timeframe}. {forecast_drivers}. {risk_factors}. {confidence_level}',
            'fx_volatility' => '{pair} implied volatility at {iv_level}%. {volatility_regime}. {option_strategies}. {event_risk}',
            'carry_trade' => '{high_yield_currency} vs {low_yield_currency} carry trade yielding {carry_yield}%. {risk_adjusted_return}. {unwind_risk}',
            'fx_correlation' => '{currency} correlation with {asset}: {correlation_value}. {correlation_driver}. {trading_implications}',
            'currency_valuation' => '{currency} {overvalued_undervalued} by {valuation_percent}% on PPP basis. {reer_level}. {adjustment_catalyst}'
        );
        
        // VOLATILITY TEMPLATES - New
        $this->templates['volatility'] = array(
            'vix_update' => 'VIX at {vix_level}, {vix_change}%. {fear_greed_interpretation}. {term_structure}. {volatility_regime}',
            'implied_realized' => 'Implied vol at {iv}% vs realized {rv}%. {vol_premium}. {volatility_arbitrage}. {mean_reversion}',
            'volatility_smile' => 'Volatility smile shows {skew_direction} skew. {put_call_skew}. {tail_risk}. {market_positioning}',
            'volatility_surface' => 'Vol surface {surface_shape}. {term_structure_shape}. {butterfly_spread}. {calendar_opportunities}',
            'correlation_breakdown' => 'Correlation breakdown detected. {asset_correlations}. {dispersion_trade}. {sector_opportunities}',
            'volatility_regime' => 'Entering {vol_regime} volatility regime. {regime_characteristics}. {strategy_adjustment}. {risk_management}',
            'event_volatility' => '{event_name} driving volatility. {event_premium}%. {volatility_crush}. {positioning_strategy}',
            'cross_asset_vol' => 'Cross-asset volatility: Equities {equity_vol}, Bonds {bond_vol}, FX {fx_vol}. {relative_value}',
            'vol_of_vol' => 'VVIX at {vvix_level}. {vix_option_activity}. {tail_hedge_demand}. {dealer_positioning}',
            'volatility_forecast' => 'Volatility forecast: {vol_forecast}% over {forecast_period}. {forecast_drivers}. {confidence_interval}'
        );
    }
    
    /**
     * Get template for intent and response type
     */
    public function get_template($intent, $response_type = 'brief') {
        if (isset($this->templates[$intent][$response_type])) {
            return $this->templates[$intent][$response_type];
        }
        
        // Fallback to first available template for intent
        if (isset($this->templates[$intent])) {
            return reset($this->templates[$intent]);
        }
        
        // Generic fallback
        return $this->get_generic_template();
    }
    
    /**
     * Render template with data
     */
    public function render($template, $data, $context = array()) {
        // Cache key for repeated renders
        $cache_key = md5($template . serialize($data));
        
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // Process the template
        $result = $template;
        
        // Extract and process variables
        $variables = $this->extract_variables($template);
        
        foreach ($variables as $variable) {
            $value = $this->resolve_variable($variable, $data, $context);
            $result = str_replace('{' . $variable . '}', $value, $result);
        }
        
        // Post-process for readability
        $result = $this->post_process($result);
        
        // Cache the result
        $this->cache[$cache_key] = $result;
        
        return $result;
    }
    
    /**
     * Extract variables from template
     */
    private function extract_variables($template) {
        preg_match_all('/\{([^}]+)\}/', $template, $matches);
        return array_unique($matches[1]);
    }
    
    /**
     * Resolve variable value from data
     */
    private function resolve_variable($variable, $data, $context) {
        // Direct data lookup
        if (isset($data[$variable])) {
            return $this->format_value($data[$variable], $variable);
        }
        
        // Nested data lookup
        if (strpos($variable, '_') !== false) {
            $parts = explode('_', $variable, 2);
            if (isset($data[$parts[0]][$parts[1]])) {
                return $this->format_value($data[$parts[0]][$parts[1]], $variable);
            }
        }
        
        // Dynamic resolution
        $value = $this->resolve_dynamic_variable($variable, $data, $context);
        if ($value !== null) {
            return $value;
        }
        
        // Context lookup
        if (isset($context[$variable])) {
            return $this->format_value($context[$variable], $variable);
        }
        
        // Default values
        return $this->get_default_value($variable);
    }
    
    /**
     * Resolve dynamic variables
     */
    private function resolve_dynamic_variable($variable, $data, $context) {
        switch ($variable) {
            case 'direction':
                return $this->determine_market_direction($data);
                
            case 'trend':
                return $this->determine_trend($data);
                
            case 'market_period':
                return $this->get_market_period();
                
            case 'index_summary':
                return $this->create_index_summary($data);
                
            case 'sector_analysis':
                return $this->create_sector_analysis($data);
                
            case 'news_points':
                return $this->format_news_points($data);
                
            case 'change_direction':
                return $this->format_change_direction($data);
                
            case 'performance_vs_sector':
                return $this->calculate_relative_performance($data);
                
            default:
                return null;
        }
    }
    
    /**
     * Format value based on variable type
     */
    private function format_value($value, $variable) {
        // Format arrays
        if (is_array($value)) {
            return $this->format_array_value($value, $variable);
        }
        
        // Format numbers
        if (is_numeric($value)) {
            return $this->format_numeric_value($value, $variable);
        }
        
        // Format strings
        return $this->format_string_value($value, $variable);
    }
    
    /**
     * Format array values
     */
    private function format_array_value($value, $variable) {
        if (empty($value)) {
            return $this->get_default_value($variable);
        }
        
        // Special formatting for certain variables
        if (in_array($variable, array('news_points', 'key_skills', 'sector_focus'))) {
            return implode(', ', array_slice($value, 0, 3));
        }
        
        return implode(', ', $value);
    }
    
    /**
     * Format numeric values
     */
    private function format_numeric_value($value, $variable) {
        // Percentage variables
        if (strpos($variable, '_rate') !== false || strpos($variable, '_percent') !== false) {
            return number_format($value, 2) . '%';
        }
        
        // Price variables
        if (strpos($variable, 'price') !== false || strpos($variable, 'value') !== false) {
            return '$' . number_format($value, 2);
        }
        
        // Volume variables
        if (strpos($variable, 'volume') !== false) {
            return $this->format_large_number($value);
        }
        
        return number_format($value, 0);
    }
    
    /**
     * Format string values
     */
    private function format_string_value($value, $variable) {
        // Escape HTML
        $value = esc_html($value);
        
        // Title case for certain variables
        if (in_array($variable, array('company', 'fund_name', 'sector'))) {
            return ucwords($value);
        }
        
        return $value;
    }
    
    /**
     * Determine market direction from data
     */
    private function determine_market_direction($data) {
        if (!isset($data['market_data']['indices'])) {
            return 'mixed';
        }
        
        $positive = 0;
        $negative = 0;
        
        foreach ($data['market_data']['indices'] as $index) {
            if (isset($index['change_percent'])) {
                if ($index['change_percent'] > 0) {
                    $positive++;
                } else {
                    $negative++;
                }
            }
        }
        
        if ($positive > $negative * 2) return 'surge';
        if ($positive > $negative) return 'advance';
        if ($negative > $positive * 2) return 'plunge';
        if ($negative > $positive) return 'decline';
        
        return 'trade flat';
    }
    
    /**
     * Determine trend from data
     */
    private function determine_trend($data) {
        $direction = $this->determine_market_direction($data);
        
        if (in_array($direction, array('surge', 'advance'))) {
            return 'bullish';
        }
        
        if (in_array($direction, array('plunge', 'decline'))) {
            return 'bearish';
        }
        
        return 'mixed';
    }
    
    /**
     * Get current market period
     */
    private function get_market_period() {
        $hour = (int)current_time('G');
        
        if ($hour < 9) return 'pre-market';
        if ($hour < 16) return 'trading';
        if ($hour < 20) return 'after-hours';
        
        return 'overnight';
    }
    
    /**
     * Create index summary
     */
    private function create_index_summary($data) {
        if (!isset($data['market_data']['indices'])) {
            return 'Major indices showing mixed performance';
        }
        
        $summaries = array();
        foreach (array_slice($data['market_data']['indices'], 0, 3) as $index) {
            $summaries[] = sprintf(
                '%s %s %s',
                $index['name'],
                $index['change_percent'] > 0 ? 'up' : 'down',
                abs($index['change_percent']) . '%'
            );
        }
        
        return implode(', ', $summaries);
    }
    
    /**
     * Create sector analysis
     */
    private function create_sector_analysis($data) {
        if (!isset($data['sector_data']) || empty($data['sector_data'])) {
            return 'Broad-based market movement across sectors';
        }
        
        $leaders = array();
        $laggards = array();
        
        foreach ($data['sector_data'] as $sector => $performance) {
            if (isset($performance['change'])) {
                if ($performance['change'] > 0) {
                    $leaders[] = $sector;
                } else {
                    $laggards[] = $sector;
                }
            }
        }
        
        $analysis = '';
        if (!empty($leaders)) {
            $analysis .= implode(', ', array_slice($leaders, 0, 2)) . ' leading';
        }
        if (!empty($laggards)) {
            $analysis .= (!empty($analysis) ? '; ' : '') . 
                        implode(', ', array_slice($laggards, 0, 2)) . ' lagging';
        }
        
        return $analysis ?: 'Sector performance mixed';
    }
    
    /**
     * Format news points
     */
    private function format_news_points($data) {
        if (!isset($data['news_data']) || empty($data['news_data'])) {
            return 'Market developments being monitored';
        }
        
        $points = array();
        foreach (array_slice($data['news_data'], 0, 3) as $news) {
            if (isset($news['title'])) {
                $points[] = $news['title'];
            }
        }
        
        return implode('; ', $points);
    }
    
    /**
     * Format change direction
     */
    private function format_change_direction($data) {
        if (isset($data['change'])) {
            if (is_numeric($data['change'])) {
                return $data['change'] > 0 ? 'up' : 'down';
            }
            if (strpos($data['change'], '+') !== false) {
                return 'up';
            }
            if (strpos($data['change'], '-') !== false) {
                return 'down';
            }
        }
        return 'unchanged';
    }
    
    /**
     * Calculate relative performance
     */
    private function calculate_relative_performance($data) {
        // This would compare company to sector average
        return 'performs in line with';
    }
    
    /**
     * Format large numbers
     */
    private function format_large_number($number) {
        if ($number >= 1000000000) {
            return round($number / 1000000000, 2) . 'B';
        }
        if ($number >= 1000000) {
            return round($number / 1000000, 2) . 'M';
        }
        if ($number >= 1000) {
            return round($number / 1000, 2) . 'K';
        }
        return number_format($number, 0);
    }
    
    /**
     * Get default value for variable
     */
    private function get_default_value($variable) {
        $defaults = array(
            'price' => 'market price',
            'change' => 'unchanged',
            'trend' => 'stable',
            'volume' => 'average volume',
            'sentiment' => 'neutral',
            'outlook' => 'conditions developing',
            'news_catalyst' => 'market developments',
            'sector_highlight' => 'broad participation',
            'key_skills' => 'analytical and technical skills',
            'networking_tip' => 'build industry connections',
            'market_cap_category' => 'established company'
        );
        
        return $defaults[$variable] ?? '';
    }
    
    /**
     * Post-process rendered template
     */
    private function post_process($result) {
        // Remove double spaces
        $result = preg_replace('/\s+/', ' ', $result);
        
        // Fix punctuation spacing
        $result = preg_replace('/\s+([.,;!?])/', '$1', $result);
        
        // Ensure sentence ending
        if (!preg_match('/[.!?]$/', trim($result))) {
            $result .= '.';
        }
        
        return trim($result);
    }
    
    /**
     * Get generic template
     */
    private function get_generic_template() {
        return 'Based on current data: {analysis}. {recommendation}.';
    }
    
    /**
     * Register custom template
     */
    public function register_template($intent, $response_type, $template) {
        if (!isset($this->templates[$intent])) {
            $this->templates[$intent] = array();
        }
        $this->templates[$intent][$response_type] = $template;
    }
    
    /**
     * Clear template cache
     */
    public function clear_cache() {
        $this->cache = array();
    }
}