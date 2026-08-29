<?php
/**
 * Career Advice Library
 *
 * Contains 90 pre-written career advice messages for the Daily Market Briefing.
 * Each message is designed for experienced professionals across private equity, growth equity, private credit, portfolio operations, and adjacent buy-side roles.
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Career_Advice_Library {

    private static $instance = null;

    /**
     * All 90 career advice messages
     */
    private $messages = [];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_messages();
    }

    /**
     * Get total number of messages
     */
    public function get_message_count() {
        return count($this->messages);
    }

    /**
     * Get a specific message by index (1-based)
     */
    public function get_message($index) {
        $index = max(1, min($index, count($this->messages)));
        return $this->messages[$index - 1] ?? null;
    }

    /**
     * Get all messages
     */
    public function get_all_messages() {
        return $this->messages;
    }

    /**
     * Load all 90 messages
     */
    private function load_messages() {
        $this->messages = [
            // ============================================
            // CATEGORY 1: CAREER STRATEGY & PLANNING (1-15)
            // ============================================
            [
                'title' => 'The 5-Year Vision Exercise',
                'content' => 'Top performers in finance don\'t just plan their next role—they map their next three. Take 30 minutes this week to write down where you want to be in 5 years, then work backward. What skills, relationships, and experiences do you need at year 3? Year 1? This quarter? The clarity this provides will transform how you evaluate every opportunity that comes your way.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'Strategic Patience vs. Complacency',
                'content' => 'In private equity, the best returns come from patient capital. The same applies to your career. Staying 3-4 years in a role to build deep expertise often creates more value than jumping every 18 months. However, patience must be strategic—you should be accumulating skills, relationships, and achievements. If six months pass without meaningful growth, it\'s time to reassess.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'The T-Shaped Career Advantage',
                'content' => 'The most successful finance professionals develop T-shaped expertise: deep specialization in one area (your vertical bar) combined with broad knowledge across adjacent domains (your horizontal bar). If you\'re in M&A, understand enough about restructuring, capital markets, and operations to speak intelligently. This versatility becomes invaluable as you advance to senior roles.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'Owning Your Career Narrative',
                'content' => 'Every career move tells a story. Before your next transition, ensure you can articulate a clear narrative: "I started in X, developed Y skills, and now I\'m moving to Z because..." Hiring managers and headhunters are pattern-matching—help them see the logical progression. Gaps and pivots are fine if you frame them purposefully.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'The Sponsor vs. Mentor Distinction',
                'content' => 'Mentors give advice. Sponsors put their reputation on the line for you. In finance, sponsors are career accelerators—they advocate for you in rooms you\'re not in. Identify who has genuine influence over your advancement and invest in those relationships. A sponsor at the right moment can compress your timeline by years.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'Reading Market Cycles for Career Timing',
                'content' => 'Smart career moves often align with market cycles. Hiring expands during bull markets when firms are deploying capital and growing teams. Downturns, while painful, create opportunities at distressed funds or turnaround situations. Study the cycle, not just your readiness. The best opportunities appear when you\'ve prepared before the market shifts.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'The Portfolio Approach to Career Risk',
                'content' => 'Apply investment principles to your career. Diversify your skills so you\'re not dependent on one sector thriving. Maintain optionality by keeping relationships warm across multiple firms. Build your personal brand as an uncorrelated asset. Just as concentrated bets can destroy portfolios, over-specialization in a declining area can stall careers.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'When to Leave a Great Situation',
                'content' => 'Counterintuitively, the best time to move is often when things are going well. You negotiate from strength, not desperation. Your references are stellar. You\'re choosing, not settling. If you\'ve achieved what you set out to accomplish and see diminishing returns ahead, consider whether the next chapter might begin elsewhere—even if leaving feels difficult.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'The Hidden Cost of Title Chasing',
                'content' => 'A VP title at a small shop isn\'t equivalent to VP at Blackstone. Before optimizing for titles, optimize for learning velocity and brand equity. Two years at a top-tier firm learning from exceptional investors often outweighs a senior title at an unknown fund. The market eventually prices in both—prioritize what compounds.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'Building Career Capital at Every Stage',
                'content' => 'At the execution and manager stage, prioritize measurable delivery, hiring-manager credibility, and relationship depth. At VP and director stage, accumulate mandate ownership, team leadership, and decision-making trust. At leadership level, your strategic judgement and reputation become the primary currency. Trying to accumulate the wrong type of capital at the wrong stage often means accumulating nothing of lasting value.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'The Reference Check You Control',
                'content' => 'Your reputation precedes every introduction. In finance\'s tight networks, what former colleagues say about you matters enormously. Invest in relationships even after you\'ve moved on. Stay professional during exits. The colleague you worked with five years ago may now control budget, hiring, or access to your next opportunity.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'Geographic Arbitrage in Private Equity Careers',
                'content' => 'London, New York, Hong Kong, and Singapore remain major private equity hubs, but opportunities increasingly exist in secondary markets such as Frankfurt, Sydney, Toronto, and other expanding buy-side centres. These markets can offer faster advancement, less competition, and strong compensation. Consider whether a geographic move might accelerate your trajectory.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'The Founder\'s Path vs. The Operator\'s Path',
                'content' => 'Some finance professionals are builders—they want to start funds, launch ventures, or create platforms. Others are operators—they excel at executing within established systems. Neither is superior, but choosing the wrong path creates friction. Understand your orientation before the pressure to launch your own thing clouds your judgment.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'Reverse Engineering Target Roles',
                'content' => 'Identify three people who have your dream job. Study their career paths. What did they do at your stage? What experiences were non-negotiable? What surprising pivots did they make? LinkedIn makes this research trivially easy. The patterns you discover become your roadmap.',
                'category' => 'career_strategy'
            ],
            [
                'title' => 'The Two-Year Learning Threshold',
                'content' => 'Research suggests it takes roughly two years to move from competence to mastery in a new role. Before that point, you\'re still absorbing context and building credibility. Moving before the two-year mark often means leaving before you\'ve captured the full value of your learning investment—and raising questions about your commitment.',
                'category' => 'career_strategy'
            ],

            // ============================================
            // CATEGORY 2: SKILLS & PROFESSIONAL DEVELOPMENT (16-30)
            // ============================================
            [
                'title' => 'The Excel Speed Advantage',
                'content' => 'In private equity and investment banking, Excel fluency separates the efficient from the exhausted. Invest 30 minutes daily for one month in keyboard shortcuts and formula mastery. Tools like Macabacus and Wall Street Prep\'s Excel course pay for themselves within weeks. The time you save compounds—faster modeling means more time for analysis.',
                'category' => 'skills'
            ],
            [
                'title' => 'Financial Modeling as a Language',
                'content' => 'A well-built model tells a story. It communicates your assumptions, your logic, and your judgment. Beyond technical accuracy, focus on clarity: consistent formatting, logical flow, transparent assumptions. The best modelers make their work accessible to anyone who opens the file. This clarity differentiates strong operators and earns trust.',
                'category' => 'skills'
            ],
            [
                'title' => 'The CFA Decision Framework',
                'content' => 'The CFA is valuable, but not universally. For asset management and research roles, it\'s nearly essential. For PE and IB, it\'s a nice-to-have. Before committing 1,000+ hours, assess your target path. If you pursue it, treat the process seriously—the discipline and knowledge compound, even if the credential matters less in some areas.',
                'category' => 'skills'
            ],
            [
                'title' => 'Writing That Wins in Finance',
                'content' => 'Clear writing is clear thinking. In a world of dense memos and lengthy decks, professionals who communicate concisely stand out. Practice writing investment theses in one paragraph. Summarize complex situations in bullet points. The ability to distill matters enormously as you advance to client-facing and leadership roles.',
                'category' => 'skills'
            ],
            [
                'title' => 'Learning from Deal Autopsies',
                'content' => 'After every deal—won or lost, successful or not—conduct a personal autopsy. What did you learn? What would you do differently? What surprised you? Keep a deal journal. Over years, patterns emerge that accelerate your judgment. The professionals who improve fastest systematically extract lessons from experience.',
                'category' => 'skills'
            ],
            [
                'title' => 'The Python Advantage in Finance',
                'content' => 'Python is increasingly the lingua franca of quantitative finance. Even if you\'re not building trading algorithms, basic Python skills let you automate data pulls, screen securities, and prototype analyses. Platforms like DataCamp offer finance-specific tracks. An hour weekly for six months creates meaningful capability.',
                'category' => 'skills'
            ],
            [
                'title' => 'Mastering the Art of the Question',
                'content' => 'Senior professionals are distinguished not by having all the answers but by asking incisive questions. In meetings and due diligence, practice asking "What would have to be true for this to work?" and "What\'s the biggest risk we\'re not discussing?" Questions that reframe discussions earn respect and surface critical insights.',
                'category' => 'skills'
            ],
            [
                'title' => 'Building Your Investment Intuition',
                'content' => 'Intuition in finance isn\'t magic—it\'s pattern recognition built from exposure. Read annual reports of companies you admire. Study failed investments to understand what went wrong. Follow markets daily, not for trading but for education. Over years, you\'ll develop the ability to sense when something is wrong before you can articulate why.',
                'category' => 'skills'
            ],
            [
                'title' => 'The Valuation Toolbox',
                'content' => 'DCF, comps, precedent transactions, LBO analysis—each valuation method tells a different story. Master all of them, but more importantly, understand when each applies. Knowing which tool fits which situation demonstrates judgment that pure technical skill doesn\'t. Context matters as much as calculation.',
                'category' => 'skills'
            ],
            [
                'title' => 'Presentation Skills That Advance Careers',
                'content' => 'The best analysis means nothing if you can\'t present it compellingly. Record yourself presenting. Watch the playback critically. Join Toastmasters or take a public speaking course. In finance, advancement increasingly depends on your ability to command a room, win over clients, and persuade investment committees.',
                'category' => 'skills'
            ],
            [
                'title' => 'Deep Sector Knowledge as Moat',
                'content' => 'Generalist skills get you in the door. Specialist knowledge keeps you irreplaceable. Choose a sector—healthcare, technology, energy, financial services—and become genuinely expert. Read trade publications, attend industry conferences, build relationships with operators. Deep sector knowledge becomes increasingly valuable as you advance.',
                'category' => 'skills'
            ],
            [
                'title' => 'The Due Diligence Mindset',
                'content' => 'Great investors approach every situation with healthy skepticism. They verify claims independently, triangulate data sources, and pressure-test assumptions. Develop this mindset in all your work—not just formal due diligence. Question the obvious, explore the footnotes, and never take management presentations at face value.',
                'category' => 'skills'
            ],
            [
                'title' => 'Learning from Investor Letters',
                'content' => 'The best free education in investing is reading investor letters. Buffett, Klarman, Marks, Dalio—they\'ve written millions of words explaining how they think. Spend an hour weekly with these letters. Note their frameworks, their vocabulary, and their reasoning. You\'re learning from masters who\'ve earned returns across decades.',
                'category' => 'skills'
            ],
            [
                'title' => 'The Negotiation Skills Gap',
                'content' => 'Finance professionals spend years developing technical skills but often neglect negotiation. Yet negotiation determines compensation, deal terms, and career opportunities. Read "Never Split the Difference" or take a negotiation course. These skills compound—better negotiation in your next role covers the investment many times over.',
                'category' => 'skills'
            ],
            [
                'title' => 'Mental Models for Finance',
                'content' => 'The best investors think in mental models: margin of safety, circle of competence, second-order effects, incentive structures. Building a library of mental models gives you frameworks for any situation. Charlie Munger\'s writings and Shane Parrish\'s Farnam Street are excellent starting points for developing this toolkit.',
                'category' => 'skills'
            ],

            // ============================================
            // CATEGORY 3: NETWORKING & RELATIONSHIPS (31-45)
            // ============================================
            [
                'title' => 'The 15-Minute Coffee Philosophy',
                'content' => 'Most professionals overestimate how much time networking requires. Fifteen minutes of genuine conversation creates more value than hour-long formal meetings. Propose brief coffees rather than lengthy lunches. People are more likely to say yes, the conversation stays focused, and you can maintain more relationships with the same time investment.',
                'category' => 'networking'
            ],
            [
                'title' => 'Giving Before Asking',
                'content' => 'The most effective networkers lead with generosity. Before asking for anything, ask yourself: "What can I give this person?" Share relevant articles, make useful introductions, offer your expertise. This approach builds genuine relationships rather than transactional exchanges—and it\'s simply more pleasant for everyone involved.',
                'category' => 'networking'
            ],
            [
                'title' => 'The Alumni Network Advantage',
                'content' => 'Your university and employer alumni networks are underutilized assets. Most professionals are happy to help fellow alumni—it\'s easy to say yes when there\'s a shared connection. Before cold outreach to strangers, search your alumni databases. A warm introduction through shared experience dramatically increases response rates.',
                'category' => 'networking'
            ],
            [
                'title' => 'Following Up Without Being Annoying',
                'content' => 'The fortune is in the follow-up. After meeting someone, send a brief note within 24 hours referencing something specific from your conversation. Then, maintain the relationship with periodic touchpoints—share a relevant article, congratulate achievements, or simply check in quarterly. Consistency without pressure builds lasting connections.',
                'category' => 'networking'
            ],
            [
                'title' => 'Conference Strategy for Introverts',
                'content' => 'Conferences drain introverts, but they\'re valuable for relationship building. Set modest goals: three meaningful conversations per event rather than collecting dozens of cards. Research attendees beforehand and prioritize quality. Give yourself permission to recharge alone. Depth beats breadth for long-term relationship value.',
                'category' => 'networking'
            ],
            [
                'title' => 'The Informational Interview Framework',
                'content' => 'Informational interviews work best with structure. Prepare specific questions: How did you navigate X transition? What do you wish you knew at my stage? What\'s changing in your field? Take notes. Follow up with thanks and any promised materials. These conversations compound when you approach them as learning opportunities.',
                'category' => 'networking'
            ],
            [
                'title' => 'LinkedIn as Professional Infrastructure',
                'content' => 'Your LinkedIn profile is your always-on professional presence. Optimize it: clear headline, specific accomplishments with metrics, relevant skills, professional photo. Engage thoughtfully with content in your field. When you\'re not looking for a job is exactly when you should be building this infrastructure—it pays dividends when you need it.',
                'category' => 'networking'
            ],
            [
                'title' => 'Building Cross-Functional Relationships',
                'content' => 'Don\'t network only within your function. The lawyer who understands your work, the IR professional who knows your investors, the operations lead who makes execution possible—these relationships provide perspective and opportunity. Invest in understanding what others do. You\'ll be a better professional and more valuable colleague.',
                'category' => 'networking'
            ],
            [
                'title' => 'The Reconnection Habit',
                'content' => 'Once monthly, reach out to someone you haven\'t spoken with in over a year. A simple "I was thinking about you and wanted to say hello" requires no ask and maintains dormant ties. These reconnections often surface unexpected opportunities because the other person has grown and changed—and so have you.',
                'category' => 'networking'
            ],
            [
                'title' => 'Cultivating Headhunter Relationships',
                'content' => 'Build relationships with headhunters before you need them. Take their calls even when you\'re not looking. Provide referrals when you can. Be responsive and professional. When you\'re ready to move, these relationships give you early access to roles and advocates who understand your profile.',
                'category' => 'networking'
            ],
            [
                'title' => 'The Power of Thoughtful Introductions',
                'content' => 'Making good introductions is networking leverage. When you connect two people who benefit from knowing each other, both remember you positively. Always ask permission before introducing, explain why you\'re connecting them, and provide context to both parties. Quality introductions build your reputation as a valuable network node.',
                'category' => 'networking'
            ],
            [
                'title' => 'Navigating Office Politics Professionally',
                'content' => 'Politics exist in every organization. Rather than avoiding them, learn to navigate professionally. Understand the power dynamics. Build relationships across factions without taking sides. Focus on doing excellent work while being politically aware. The professionals who advance fastest do great work AND understand how their organization works.',
                'category' => 'networking'
            ],
            [
                'title' => 'The Thank You Note Difference',
                'content' => 'Handwritten thank you notes are rare enough to be memorable. After significant meetings, interviews, or favors, a brief handwritten note stands out in a world of email. Keep cards at your desk and develop the habit. This small gesture disproportionately strengthens relationships because so few people bother.',
                'category' => 'networking'
            ],
            [
                'title' => 'Building Your Personal Board of Directors',
                'content' => 'Construct a personal board: 5-7 people who genuinely want you to succeed and whose judgment you trust. Include mentors, peers, and even mentees who offer fresh perspectives. Meet with each periodically. Share challenges and seek counsel. This group becomes invaluable for major decisions and ongoing development.',
                'category' => 'networking'
            ],
            [
                'title' => 'Relationship Maintenance Systems',
                'content' => 'Without a system, relationships fade. Use a simple CRM (even a spreadsheet) to track key contacts, last touchpoints, and follow-up reminders. Set calendar reminders for quarterly check-ins with important relationships. The most connected professionals aren\'t more social—they\'re more systematic about maintaining connections.',
                'category' => 'networking'
            ],

            // ============================================
            // CATEGORY 4: INTERVIEW & APPLICATION SUCCESS (46-60)
            // ============================================
            [
                'title' => 'The STAR Method Mastered',
                'content' => 'Behavioral interviews assess how you\'ve handled real situations. Master the STAR format: Situation (context), Task (your responsibility), Action (what you did), Result (quantified outcome). Prepare 8-10 stories covering leadership, conflict, failure, and achievement. Practice until you can adapt any story to any question naturally.',
                'category' => 'interviews'
            ],
            [
                'title' => 'Researching Like an Investor',
                'content' => 'Before any interview, research the firm like you\'re investing in it. Read their investor letters, understand their portfolio, know their recent deals. Study the interviewers on LinkedIn. This preparation demonstrates genuine interest and provides talking points. Walk in knowing more than the minimum expected.',
                'category' => 'interviews'
            ],
            [
                'title' => 'The Technical Interview Mindset',
                'content' => 'Technical interviews test your reasoning, not just your knowledge. When you don\'t know something, say so—then explain how you\'d figure it out. Think aloud so interviewers understand your process. Ask clarifying questions. The ability to work through problems systematically often matters more than having the right answer immediately.',
                'category' => 'interviews'
            ],
            [
                'title' => 'Case Study Preparation Strategy',
                'content' => 'For case study interviews, structure is everything. Practice a framework: market analysis, competitive dynamics, financial assessment, recommendation. Use consistent formatting. The goal isn\'t the "right" answer—it\'s demonstrating how you think. Do 20+ practice cases before any PE or consulting interview.',
                'category' => 'interviews'
            ],
            [
                'title' => 'Managing Interview Nerves',
                'content' => 'Nervousness means you care. Channel it productively: prepare thoroughly to reduce uncertainty, arrive early, do a brief physical warmup (posture, deep breaths), and reframe anxiety as excitement. Remember that interviews are also your evaluation of them. This mindset shift often reduces pressure significantly.',
                'category' => 'interviews'
            ],
            [
                'title' => 'The Art of the Follow-Up Email',
                'content' => 'Post-interview follow-up matters. Send personalized thank you emails within 24 hours to each interviewer. Reference something specific from your conversation. Briefly reinforce your interest and fit. Keep it concise—three paragraphs maximum. This touchpoint maintains momentum and demonstrates professionalism.',
                'category' => 'interviews'
            ],
            [
                'title' => 'Explaining Career Transitions',
                'content' => 'Career changes require compelling narratives. Never apologize or seem defensive. Instead, frame transitions positively: "I developed X at my previous role, and now I\'m excited to apply that to Y because..." Connect the dots for interviewers. Show that each move was intentional and that this role is a logical next step.',
                'category' => 'interviews'
            ],
            [
                'title' => 'The "Tell Me About Yourself" Framework',
                'content' => 'This opening question sets the tone. Prepare a 90-second story arc: where you started, key experiences that shaped you, why this role now. Practice until it sounds natural, not rehearsed. End with forward momentum—why you\'re excited about this specific opportunity. A strong opening establishes confidence for the entire interview.',
                'category' => 'interviews'
            ],
            [
                'title' => 'Asking Questions That Impress',
                'content' => 'Your questions reveal your thinking. Ask about strategy, challenges, and culture—not logistics you can find online. "What separates good from great performance here?" or "What\'s the biggest challenge the team faces this year?" Thoughtful questions demonstrate genuine engagement and often create the most memorable part of interviews.',
                'category' => 'interviews'
            ],
            [
                'title' => 'Salary Negotiation Fundamentals',
                'content' => 'Never give a number first if you can avoid it. When pressed, provide ranges based on research. After receiving an offer, don\'t accept immediately—express enthusiasm, ask for time to consider, then negotiate. Almost everything is negotiable: base, bonus, sign-on, start date, title. The worst they can say is no.',
                'category' => 'interviews'
            ],
            [
                'title' => 'Reference Strategy',
                'content' => 'Prepare your references before you need them. Brief them on the role, remind them of relevant accomplishments, and give them your resume. Choose references who can speak to different strengths. Thank them afterward regardless of outcome. Strong references who are well-prepared can tip close decisions in your favor.',
                'category' => 'interviews'
            ],
            [
                'title' => 'Addressing Weaknesses Authentically',
                'content' => 'The "weakness" question isn\'t a trap if you approach it honestly. Choose a genuine developmental area, explain what you\'re doing about it, and keep it brief. Avoid clichés ("I work too hard") and anything that undermines core job requirements. Authenticity here actually builds trust—everyone has weaknesses.',
                'category' => 'interviews'
            ],
            [
                'title' => 'The LBO Modeling Interview',
                'content' => 'Private equity modeling tests are structured predictably. Practice the standard LBO: sources and uses, operating model, debt schedule, returns analysis. Focus on accuracy first, speed second. Explain your assumptions as you go. Keep a clean model structure. Do 10+ practice LBOs with time pressure before any PE modeling test.',
                'category' => 'interviews'
            ],
            [
                'title' => 'Panel Interview Dynamics',
                'content' => 'Panel interviews require tactical awareness. Make eye contact with everyone, not just the question-asker. Note who asks follow-ups—they may be the decision-maker. Treat each interviewer as an individual even in a group setting. Direct your answer to the questioner but periodically scan the room. Everyone\'s impression counts.',
                'category' => 'interviews'
            ],
            [
                'title' => 'When to Walk Away',
                'content' => 'Not every opportunity is right for you. Trust the signals: unclear role definitions, concerning culture indicators, compensation far below market, or interviewers who seem unengaged. The desperation to land any job can lead to accepting wrong fits. Walking away from misaligned opportunities protects your career trajectory.',
                'category' => 'interviews'
            ],

            // ============================================
            // CATEGORY 5: MARKET TRENDS & OPPORTUNITIES (61-75)
            // ============================================
            [
                'title' => 'The Private Credit Opportunity',
                'content' => 'Private credit has expanded dramatically as banks retreated post-2008. Direct lending, mezzanine, and distressed strategies offer paths for professionals with credit analysis backgrounds. If you\'re in leveraged finance or high-yield research, understand this growing segment. The skills transfer, and the opportunity set continues expanding.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'ESG Integration in Investment Careers',
                'content' => 'ESG has moved from niche to mainstream. Every major allocator now considers sustainability factors. Professionals who understand ESG integration—not just compliance but value creation—are increasingly valuable. Build this knowledge whether or not you specialize. It\'s becoming table stakes for investment roles across asset classes.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'Technology\'s Reshaping of Finance',
                'content' => 'AI, blockchain, and automation are transforming finance operations. Rather than fearing displacement, position yourself at the intersection of finance and technology. The professionals who thrive will be those who can apply new tools while maintaining judgment that machines can\'t replicate. Learn enough about emerging tech to guide its application.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'The Private Equity Renaissance',
                'content' => 'Private equity remains one of the clearest markets where specialist positioning compounds. Funds still reward candidates who can show repeatable deal judgement, clean modelling, commercial instinct, and credible exposure to value creation. If you want to move upmarket, focus less on broad finance language and more on the specific investing, diligence, execution, and portfolio signals that a fund can underwrite quickly.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'Healthcare Investment Specialization',
                'content' => 'Healthcare remains recession-resistant with structural tailwinds from aging demographics and innovation. Specialized knowledge in life sciences, healthcare services, or medtech creates differentiation. If you\'re choosing a sector focus, healthcare offers both interesting complexity and sustained deal flow across cycles.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'The Infrastructure Investment Supercycle',
                'content' => 'Infrastructure investment is entering a multi-decade supercycle driven by energy transition, digitalization, and deferred maintenance. Professionals with infrastructure experience—from project finance to fund investment—are in demand. Consider whether your skills might apply to this growing segment of alternatives.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'Secondaries Market Expansion',
                'content' => 'The secondaries market has grown from niche to essential liquidity provider. LP stakes, GP-led transactions, and continuation vehicles create opportunities for professionals who understand portfolio valuation and complex transactions. This corner of PE offers interesting work with less deal-by-deal sourcing pressure.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'Quantitative Skills in Fundamental Investing',
                'content' => 'Even fundamental investors now use quantitative tools for screening, risk management, and process improvement. You don\'t need a PhD, but basic Python, SQL, and statistical literacy increasingly separate candidates. These skills complement rather than replace fundamental analysis—the future belongs to professionals who can do both.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'The Family Office Opportunity',
                'content' => 'Family offices offer an alternative path in private wealth and direct investing. They typically offer diverse exposure, long-term orientation, and less institutional bureaucracy. For professionals seeking variety and impact over brand name, sophisticated family offices provide compelling career opportunities with competitive compensation.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'Operating Roles Within PE',
                'content' => 'Private equity firms increasingly build operating capabilities—not just deal teams but value creation specialists. If you have operational experience alongside financial skills, this hybrid path offers differentiated positioning. Operating partners and portfolio support roles provide paths for those who enjoy hands-on business building.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'Geographic Diversification in Portfolios and Careers',
                'content' => 'As investors diversify globally, so should you consider geographic flexibility. Emerging markets experience, language skills, and cultural fluency create advantages. Even if you prefer developed markets long-term, international exposure at the right stage can expand your judgement, operating range, and long-term optionality.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'The Credit Cycle Implications',
                'content' => 'After years of low rates, credit markets are normalizing. This creates opportunities in distressed investing, special situations, and restructuring—areas that wane during benign credit environments. If you have the stomach for complexity and sometimes adversarial situations, this cycle shift may offer career acceleration.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'Energy Transition Investment',
                'content' => 'The energy transition is perhaps the largest capital reallocation in history. Professionals who understand both conventional energy and renewables are valuable bridges. Whether in project finance, infrastructure PE, or specialized funds, energy transition expertise offers multi-decade career tailwinds aligned with global priorities.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'Venture Capital Adjacent Roles',
                'content' => 'Not interested in pure VC? Consider adjacent roles: corporate venture, growth equity, or venture debt. These positions offer exposure to innovation without extreme risk profiles. For professionals from traditional finance backgrounds, these hybrids may offer better culture fits while still providing technology exposure.',
                'category' => 'market_trends'
            ],
            [
                'title' => 'The Search Fund Path',
                'content' => 'Search funds offer an entrepreneurial path to company ownership, backed by investors. For professionals seeking CEO experience without starting from scratch, this model provides a structured approach to buying and running a business. Consider whether search aligns with your long-term ambition for ownership and operating responsibility.',
                'category' => 'market_trends'
            ],

            // ============================================
            // CATEGORY 6: MINDSET & LEADERSHIP (76-90)
            // ============================================
            [
                'title' => 'The Compound Effect of Showing Up',
                'content' => 'Consistency beats intensity. The professional who improves 1% daily outperforms the one who sprints and burns out. Show up every day with intention. Over years, small consistent efforts compound into extraordinary results. Most of your competition will quit, get distracted, or coast. Simply continuing to grow puts you in rare company.',
                'category' => 'mindset'
            ],
            [
                'title' => 'Managing Energy, Not Just Time',
                'content' => 'Time management matters, but energy management matters more. Protect your peak hours for highest-value work. Understand your energy patterns—when you\'re sharp for analysis, when you\'re better suited for meetings. Align work types with energy states. You can\'t create more hours, but you can optimize how you use them.',
                'category' => 'mindset'
            ],
            [
                'title' => 'The Growth Mindset in Finance',
                'content' => 'Intelligence is less fixed than we once believed. The willingness to learn, fail, and iterate—what Carol Dweck calls a "growth mindset"—predicts success better than raw ability. Embrace challenges as learning opportunities. See feedback as useful data rather than judgment. The professionals who improve fastest believe improvement is possible.',
                'category' => 'mindset'
            ],
            [
                'title' => 'Building Resilience Through Setbacks',
                'content' => 'Every successful career includes rejections, failures, and disappointments. What separates those who advance from those who stall is resilience—the ability to learn from setbacks and continue forward. After any disappointment, allow yourself to feel it, extract the lessons, then recommit. Resilience is a skill you build through practice.',
                'category' => 'mindset'
            ],
            [
                'title' => 'The Executive Presence Myth',
                'content' => 'Executive presence isn\'t about commanding attention through dominance. It\'s confidence without arrogance, poise under pressure, and the ability to make others feel heard. You can develop these qualities through practice: pausing before responding, maintaining composure during stress, focusing on impact rather than impression.',
                'category' => 'mindset'
            ],
            [
                'title' => 'Managing Imposter Syndrome',
                'content' => 'Imposter syndrome affects even the most accomplished professionals—often especially them. Recognize that feeling like a fraud is nearly universal. Focus on evidence: past accomplishments, positive feedback, your actual performance. You don\'t need to feel confident to act confidently. Often, the feeling fades once you\'ve proven yourself again.',
                'category' => 'mindset'
            ],
            [
                'title' => 'The Art of Saying No',
                'content' => 'Your effectiveness depends on protecting focus. Every "yes" is implicitly a "no" to something else. Practice declining non-essential requests graciously: "I\'d love to help, but my current commitments don\'t allow it right now." Saying no strategically isn\'t selfish—it\'s essential for doing your actual work excellently.',
                'category' => 'mindset'
            ],
            [
                'title' => 'Leading Without Authority',
                'content' => 'You don\'t need a title to lead. Influence through competence, reliability, and genuine interest in others\' success. Volunteer for coordination roles. Help colleagues solve problems. Share credit generously. These behaviors build leadership reputation long before formal authority arrives—and they\'re precisely what earns you that authority.',
                'category' => 'mindset'
            ],
            [
                'title' => 'The Long Game Perspective',
                'content' => 'Finance careers span 30-40 years. Decisions that seem urgent today often matter little in a decade. Take the longer view: optimize for learning early, relationships always, and reputation forever. Short-term thinking—about compensation, titles, or status—often undermines long-term success. Patience and perspective are competitive advantages.',
                'category' => 'mindset'
            ],
            [
                'title' => 'Giving Effective Feedback',
                'content' => 'As you advance, your ability to develop others becomes essential. Master the art of useful feedback: specific, timely, balanced, and actionable. Focus on behaviors, not personality. Frame feedback as investment in someone\'s growth. The managers who build great teams can have difficult conversations with compassion and clarity.',
                'category' => 'mindset'
            ],
            [
                'title' => 'Managing Up Effectively',
                'content' => 'Your relationship with your manager determines much of your work experience. Understand their priorities, communication preferences, and pressures. Make their job easier by anticipating needs and managing expectations. Keep them informed without overwhelming. The best finance professionals manage up skillfully—it\'s a critical professional skill at every level.',
                'category' => 'mindset'
            ],
            [
                'title' => 'Work-Life Integration Realities',
                'content' => 'Finance is demanding. Rather than seeking perfect balance, focus on integration: periods of intense work punctuated by genuine recovery. Protect what matters most—relationships, health, interests outside work—even if imperfectly. Burnout destroys careers; sustainable intensity builds them. Know your limits and design your life accordingly.',
                'category' => 'mindset'
            ],
            [
                'title' => 'The Authenticity Advantage',
                'content' => 'Professional success doesn\'t require becoming someone you\'re not. Authenticity—knowing your values and living by them—creates sustainable performance. The effort of maintaining a false persona drains energy better spent on actual work. Find organizations and roles where your natural style fits. You\'ll perform better and enjoy the journey more.',
                'category' => 'mindset'
            ],
            [
                'title' => 'Building a Legacy Through Others',
                'content' => 'The most respected senior professionals are remembered for who they developed, not just what they achieved. Invest in others coming up behind you—mentor, sponsor, teach. This generosity creates ripples that extend far beyond your own career. Your legacy is shaped by how you lift others along the way.',
                'category' => 'mindset'
            ],
            [
                'title' => 'Defining Your Own Success',
                'content' => 'Ultimately, you must define what success means for you—not your parents, peers, or society. Is it compensation? Impact? Flexibility? Prestige? Some combination? Clarity about your personal definition prevents chasing goals that won\'t satisfy even when achieved. Take time regularly to ensure you\'re climbing a ladder leaning against the right wall.',
                'category' => 'mindset'
            ],
        ];
    }
}

// Initialize
SFFC_Career_Advice_Library::get_instance();
