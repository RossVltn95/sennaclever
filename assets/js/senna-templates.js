/**
 * MENA Careers Response Templates
 * Pre-defined responses for common queries
 */

const SennaTemplates = {
    
    // Greeting templates
    greetings: {
        welcome: "I'm MENA Careers, your strategic career advisor. I'll help you navigate opportunities with precision and position yourself for maximum impact.",
        
        withOpportunities: (count) => 
            `I see you have ${count} strategic opportunities in your pipeline. Let me provide detailed analysis to position you for success.`,
        
        noOpportunities: "Start by exploring roles from the opportunities page. I'll then provide strategic analysis tailored to your profile.",
        
        returning: "Welcome back. Let's continue optimizing your career strategy."
    },
    
    // Role analysis templates
    roleAnalysis: {
        starting: (title, company) => 
            `Analyzing ${title} at ${company}. I'll identify key requirements, match points, and positioning strategies.`,
        
        matchAssessment: (score) => {
            if (score >= 90) return `Your ${score}% match indicates exceptional alignment. You're in the top tier of candidates.`;
            if (score >= 80) return `Your ${score}% match shows strong alignment. Focus on differentiating factors.`;
            if (score >= 70) return `Your ${score}% match is solid. Emphasize transferable skills and growth potential.`;
            return `Your ${score}% match suggests this is a stretch role. Position it as strategic career development.`;
        },
        
        requirements: {
            header: "Key requirements for this role:",
            noData: "Review the full job description for detailed requirements.",
            found: (reqs) => reqs.map((req, i) => `${i+1}. ${req}`).join('\n\n')
        }
    },
    
    // Application strategy templates
    applicationStrategy: {
        header: (company) => `Your application strategy for ${company}:`,
        
        steps: [
            "Lead with quantifiable achievements that mirror their requirements",
            "Research the company's recent initiatives and reference them specifically",
            "Address any gaps proactively as areas of growth interest",
            "Close with enthusiasm for their specific mission and culture",
            "Follow up within 48 hours with a LinkedIn connection to the hiring manager"
        ],
        
        timeline: {
            urgent: "Apply within 24 hours - this role has high urgency",
            normal: "Apply within 2-3 days for optimal timing",
            strategic: "Take 3-4 days to craft a highly tailored application"
        }
    },
    
    // Salary negotiation templates
    salaryNegotiation: {
        analysis: (min, max) => 
            `Based on market data, the compensation range is $${Math.round(min/1000)}k-$${Math.round(max/1000)}k.`,
        
        strategy: {
            header: "Negotiation framework:",
            approach: [
                "Anchor at the 75th percentile of the range",
                "Justify with competing offers or unique value",
                "Focus on total compensation, not just base",
                "Consider signing bonus if base is capped",
                "Negotiate after verbal offer, before written"
            ]
        },
        
        leverage: (company) => 
            `Your profile commands premium compensation. Use market data and competing interest to maximize your ${company} offer.`
    },
    
    // Interview preparation templates
    interviewPrep: {
        header: "Interview preparation framework:",
        
        rounds: {
            phone: [
                "30-minute screening with recruiter or hiring manager",
                "Focus on motivation and high-level experience",
                "Prepare 2-minute pitch and 3 key achievements"
            ],
            
            technical: [
                "Deep dive into technical competencies",
                "Prepare case studies from your experience",
                "Review industry frameworks and methodologies"
            ],
            
            behavioral: [
                "Use STAR method for all responses",
                "Prepare 5-7 stories covering key competencies",
                "Practice with specific examples of impact"
            ],
            
            final: [
                "Executive or panel interview",
                "Focus on strategic thinking and cultural fit",
                "Prepare thoughtful questions about company direction"
            ]
        },
        
        research: (company) => 
            `Research ${company}'s recent news, leadership changes, financial performance, and strategic initiatives.`
    },
    
    // Skills analysis templates
    skillsAnalysis: {
        match: (matched, total) => 
            `You match ${matched} out of ${total} key skills. ${matched >= total * 0.8 ? 'Excellent alignment.' : 'Focus on transferable skills for gaps.'}`,
        
        gaps: {
            header: "For skill gaps, emphasize:",
            strategies: [
                "Similar technologies or methodologies you've mastered",
                "Your proven ability to learn quickly",
                "Relevant coursework or certifications in progress",
                "How your unique skills compensate"
            ]
        },
        
        strengths: (skills) => 
            `Your key strengths for this role: ${skills.join(', ')}. Prepare specific examples demonstrating mastery.`
    },
    
    // Comparison templates
    comparison: {
        header: (count) => `Comparing your ${count} opportunities:`,
        
        noOpportunities: "Add more opportunities to your saved list for comparison analysis.",
        
        recommendation: (bestMatch) => 
            `Based on alignment, ${bestMatch.company} offers the strongest match at ${bestMatch.match}%.`,
        
        factors: [
            "Career trajectory alignment",
            "Compensation potential",
            "Growth opportunities",
            "Cultural fit",
            "Work-life balance",
            "Learning potential"
        ],
        
        strategy: "Prioritize based on long-term career goals, not just immediate benefits."
    },
    
    // General advice templates
    generalAdvice: {
        networking: [
            "Leverage alumni networks from your school",
            "Attend industry events and conferences",
            "Maintain regular touchpoints with your network",
            "Offer value before asking for favors"
        ],
        
        careerGrowth: [
            "Focus on impact, not just activity",
            "Document and quantify all achievements",
            "Build visibility through thought leadership",
            "Develop both depth and breadth of skills"
        ],
        
        marketTiming: {
            q1: "Q1 is optimal for senior roles - budgets are fresh and teams are planning.",
            q2: "Q2 sees increased hiring as teams fill approved headcount.",
            q3: "Q3 can be slower but good for strategic moves.",
            q4: "Q4 is fast-paced but competitive with year-end pushes."
        }
    },
    
    // Error/fallback templates
    fallbacks: {
        unclear: (query) => 
            `I understand you're asking about "${query}". Let me provide strategic insights based on your current opportunities.`,
        
        noData: "I need more information to provide specific guidance. Try adding opportunities to your saved list first.",
        
        error: "I'm having trouble processing that request. Let me help you with career strategy instead."
    }
};

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SennaTemplates;
} else {
    window.SennaTemplates = SennaTemplates;
}