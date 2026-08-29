/**
 * MENA Careers Extended Templates
 * Comprehensive response library for all scenarios
 */

const SennaTemplatesExtended = {
    
    // Industry-specific insights
    industryInsights: {
        technology: {
            trends: "The tech industry is experiencing rapid growth in AI/ML, cloud infrastructure, and cybersecurity. Companies are prioritizing candidates with both technical depth and strategic thinking.",
            compensation: window.sffc_frontend?.is_logged_in === '1' ? "Tech compensation packages typically include significant equity components. Login to see detailed salary ranges." : "Login to reveal compensation insights for tech industry.",
            culture: "Tech companies value innovation, rapid iteration, and data-driven decision making. Demonstrate your ability to work in ambiguous environments.",
            skills: ["Cloud Architecture", "Machine Learning", "System Design", "Agile Methodologies", "Data Analysis"]
        },
        
        finance: {
            trends: "Financial services are transforming through digital innovation, regulatory changes, and ESG initiatives. Fintech disruption creates opportunities for tech-savvy professionals.",
            compensation: window.sffc_frontend?.is_logged_in === '1' ? "Finance offers competitive compensation with bonus structures. Login to see detailed ranges." : "Login to reveal compensation insights for finance industry.",
            culture: "Finance values precision, risk management, and relationship building. Emphasize your analytical rigor and client focus.",
            skills: ["Financial Modeling", "Risk Analysis", "Regulatory Compliance", "Client Relations", "Market Analysis"]
        },
        
        healthcare: {
            trends: "Healthcare is evolving with digital health, personalized medicine, and value-based care models. Regulatory expertise and patient focus are critical.",
            compensation: window.sffc_frontend?.is_logged_in === '1' ? "Healthcare compensation varies by sector. Login to see detailed ranges." : "Login to reveal compensation insights for healthcare industry.",
            culture: "Healthcare prioritizes patient outcomes, compliance, and scientific rigor. Highlight your impact on patient care and operational efficiency.",
            skills: ["Clinical Knowledge", "Regulatory Affairs", "Data Privacy", "Quality Assurance", "Population Health"]
        },
        
        consulting: {
            trends: "Consulting firms are expanding digital transformation practices and specialized industry expertise. Hybrid work models are becoming standard.",
            compensation: window.sffc_frontend?.is_logged_in === '1' ? "Consulting offers structured compensation with bonus potential. Login to see detailed ranges." : "Login to reveal compensation insights for consulting industry.",
            culture: "Consulting values intellectual curiosity, client service excellence, and team collaboration. Showcase your problem-solving frameworks.",
            skills: ["Strategic Thinking", "Stakeholder Management", "Data Analysis", "Presentation Skills", "Industry Expertise"]
        }
    },
    
    // Level-specific guidance
    levelGuidance: {
        entry: {
            focus: "Build foundational skills and establish your professional brand",
            strategies: [
                "Emphasize measurable experience, projects, and achievements",
                "Demonstrate eagerness to learn and grow",
                "Highlight relevant projects and coursework",
                "Show cultural fit and team collaboration"
            ],
            negotiation: window.sffc_frontend?.is_logged_in === '1' ? "Focus on learning opportunities over compensation. Login to see typical salary ranges." : "Login to reveal negotiation strategies and salary ranges."
        },
        
        mid: {
            focus: "Demonstrate independent contribution and emerging leadership",
            strategies: [
                "Quantify your individual impact with metrics",
                "Show progression in responsibilities",
                "Highlight cross-functional collaboration",
                "Demonstrate technical and soft skills balance"
            ],
            negotiation: window.sffc_frontend?.is_logged_in === '1' ? "Leverage your proven track record. Login to see target salary ranges." : "Login to reveal negotiation strategies and salary benchmarks."
        },
        
        senior: {
            focus: "Showcase strategic thinking and team leadership",
            strategies: [
                "Lead with business impact and ROI",
                "Demonstrate team building and mentorship",
                "Show industry thought leadership",
                "Highlight complex problem-solving"
            ],
            negotiation: window.sffc_frontend?.is_logged_in === '1' ? "Negotiate full package including equity. Login to see market percentiles." : "Login to reveal senior-level negotiation strategies."
        },
        
        executive: {
            focus: "Position yourself as a transformational leader",
            strategies: [
                "Emphasize P&L responsibility and strategic vision",
                "Showcase organizational transformation",
                "Highlight board and stakeholder management",
                "Demonstrate market expertise"
            ],
            negotiation: window.sffc_frontend?.is_logged_in === '1' ? "Structure comprehensive executive packages. Login to see detailed compensation structures." : "Login to reveal executive compensation strategies."
        }
    },
    
    // Detailed role analysis
    roleAnalysis: {
        technical: {
            positioning: "Emphasize your technical depth while showing business acumen. Balance hard skills with collaborative abilities.",
            keyPoints: [
                "Specific technologies and frameworks mastered",
                "Scale of systems designed or managed",
                "Performance improvements delivered",
                "Technical leadership and mentorship"
            ],
            questions: [
                "What's your approach to system design?",
                "How do you balance technical debt with feature delivery?",
                "Describe a complex technical challenge you solved"
            ]
        },
        
        management: {
            positioning: "Focus on team building, strategic execution, and stakeholder management. Show both results and people leadership.",
            keyPoints: [
                "Team size and growth managed",
                "Budget responsibility and ROI",
                "Cross-functional collaboration",
                "Strategic initiatives led"
            ],
            questions: [
                "How do you build high-performing teams?",
                "Describe your management philosophy",
                "How do you handle underperformers?"
            ]
        },
        
        strategic: {
            positioning: "Demonstrate vision, market understanding, and execution capability. Show how you drive organizational change.",
            keyPoints: [
                "Strategic initiatives conceived and executed",
                "Market analysis and competitive positioning",
                "Partnership and M&A experience",
                "Board and executive engagement"
            ],
            questions: [
                "How do you develop strategic priorities?",
                "Describe a successful transformation you led",
                "How do you balance short and long-term goals?"
            ]
        }
    },
    
    // Communication templates
    communications: {
        networking: {
            initialOutreach: (name, company, role) => 
                `Hi ${name}, I noticed you made a successful transition to ${company}. I'm exploring similar ${role} opportunities and would value 15 minutes of your insights on the role and culture. Coffee on me at your convenience?`,
            
            followUp: "Thank you for our conversation yesterday. Your insights on [specific topic] were particularly valuable. As discussed, I'll [specific action]. Looking forward to staying connected.",
            
            thankYou: "I wanted to thank you again for your time and guidance. Your perspective on [specific insight] has shaped my approach. I'll keep you updated on my progress."
        },
        
        application: {
            coverLetterOpen: (company, role) =>
                `Your search for a ${role} who can ${key_requirement} while driving ${business_goal} aligns perfectly with my track record of ${relevant_achievement}.`,
            
            coverLetterClose: (company) =>
                `I'm excited about the opportunity to contribute to ${company}'s ${specific_initiative} and would welcome the chance to discuss how my experience can drive immediate impact.`,
            
            emailSubject: (role, name) =>
                `${role} Application - ${name} - [Key Differentiator]`
        },
        
        interview: {
            openingPitch: "I'm a [level] [function] professional with [X] years driving [key value]. Most recently at [company], I [major achievement]. I'm excited about [company] because [specific reason].",
            
            closingStatement: "Based on our discussion, I'm even more enthusiastic about this opportunity. My experience in [relevant area] positions me to immediately contribute to [specific initiative]. What are the next steps?",
            
            questions: [
                "What does success look like in this role in the first 90 days?",
                "What are the biggest challenges facing the team currently?",
                "How does this role contribute to the broader organizational strategy?",
                "What's the team culture and working style?",
                "What growth opportunities exist within this role?"
            ]
        }
    },
    
    // Negotiation scripts
    negotiation: {
        openingPosition: {
            script: "I'm excited about the opportunity and appreciate the offer. Based on my research and the scope of the role, I was targeting a range of $[X] to $[Y]. Can we discuss the flexibility in the compensation package?",
            
            justification: [
                "Market data from similar roles at peer companies",
                "The expanded scope compared to the original JD",
                "My unique qualifications that exceed requirements",
                "The immediate impact I can deliver"
            ]
        },
        
        counterOffer: {
            acceptance: "I appreciate you working with me on this. The revised offer of $[X] works well, and I'm excited to accept pending the written offer.",
            
            continued: "I appreciate the movement to $[X]. Given [specific justification], could we explore $[Y] or perhaps address the gap through [signing bonus/equity/other]?",
            
            alternative: "I understand the constraints on base salary. Could we explore other elements like signing bonus, equity acceleration, or additional PTO?"
        },
        
        closingDeal: {
            acceptance: "This package works well for me. I'm excited to join the team and start contributing. When should I expect the written offer?",
            
            timeline: "I'd like to review the written offer with my family. Could I provide a response by [specific date]?",
            
            startDate: "My ideal start date would be [date] to ensure a smooth transition from my current role. Does that timeline work?"
        }
    },
    
    // Skills gap strategies
    skillsGap: {
        technical: {
            missing: (skill) => 
                `While I haven't worked directly with ${skill}, my experience with [similar technology] provides a strong foundation. I'm confident I can quickly ramp up.`,
            
            learning: "I'm currently enrolled in [course/certification] to deepen my expertise in this area.",
            
            transferable: "My experience with [related skill] demonstrates my ability to quickly master new technologies."
        },
        
        experience: {
            level: "While I may have fewer years in this specific industry, my experience in [related field] provides unique perspectives and transferable skills.",
            
            scope: "Although I haven't managed teams of this size, I've successfully scaled teams from X to Y and developed the systems needed for larger organizations.",
            
            industry: "My cross-industry experience brings fresh perspectives and proven practices from [other industry] that can drive innovation here."
        },
        
        leadership: {
            formal: "While I haven't held a formal leadership title, I've led cross-functional initiatives involving X stakeholders and $Y budget.",
            
            indirect: "I've demonstrated leadership through influence, driving consensus among senior stakeholders without formal authority.",
            
            potential: "I'm eager to step into formal leadership and have been preparing through [specific development activities]."
        }
    },
    
    // Market timing advice
    marketTiming: {
        immediate: "The market is hot for your profile. Move quickly but deliberately. Multiple offers will give you leverage.",
        
        strategic: "Take 2-3 weeks to build a strong pipeline. The holiday slowdown gives you time to prepare for Q1 hiring.",
        
        patient: "Market conditions suggest waiting 1-2 months. Use this time to upskill and strengthen your network.",
        
        urgent: "This role has been open for a while and they're eager to fill it. Express strong interest and availability for fast-track process."
    },
    
    // Company research guidance
    companyResearch: {
        financial: [
            "Review recent 10-K/10-Q filings for public companies",
            "Understand revenue growth, profitability, and key metrics",
            "Identify strategic priorities from earnings calls",
            "Research recent M&A activity and partnerships"
        ],
        
        cultural: [
            "Read Glassdoor reviews focusing on your level/function",
            "Review LinkedIn posts from current employees",
            "Understand promotion and development practices",
            "Research diversity, equity, and inclusion initiatives"
        ],
        
        strategic: [
            "Identify key competitors and differentiation",
            "Understand industry challenges and opportunities",
            "Research recent product launches or initiatives",
            "Review leadership changes and organizational updates"
        ],
        
        preparation: [
            "Prepare specific examples that align with company values",
            "Develop perspective on their strategic challenges",
            "Identify how your experience addresses their needs",
            "Prepare thoughtful questions showing deep research"
        ]
    },
    
    // Rejection recovery
    rejectionRecovery: {
        response: "Thank you for letting me know. While disappointed, I appreciate the opportunity to interview and learn about [company]. I remain interested in future opportunities and would appreciate any feedback to help me grow.",
        
        feedback: "I appreciate your consideration throughout the process. To help me continue developing, could you share any specific feedback about areas where I could strengthen my candidacy?",
        
        networking: "Although this role wasn't the right fit, I enjoyed our conversations and would value staying connected. I'd be happy to refer strong candidates from my network.",
        
        reapplication: "I understand the timing wasn't right for this role. I remain very interested in [company] and would appreciate being considered for future opportunities that align with my background."
    }
};

// Merge with existing templates
if (typeof window !== 'undefined') {
    window.SennaTemplatesExtended = SennaTemplatesExtended;
    
    // Merge with base templates if they exist
    if (window.SennaTemplates) {
        window.SennaTemplates = {
            ...window.SennaTemplates,
            ...SennaTemplatesExtended
        };
    }
}
