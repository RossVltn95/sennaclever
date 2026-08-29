=== MENA Careers ===
Contributors: mena-careers
Tags: finance, career, ai, chat, professional-development
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 6.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered career guidance platform for financial services professionals featuring MENA Careers, your intelligent career advisor.

== Description ==

MENA Careers is a comprehensive WordPress plugin designed specifically for financial services professionals seeking to advance their careers. The plugin features MENA Careers, an AI-powered career advisor who provides personalized guidance through multiple specialized modes.

= Key Features =

* **Career Assistance Mode**: Strategic career planning and CV analysis
* **Market Analysis Mode**: Real-time market insights with career implications
* **Build Skills Mode**: Interactive financial modeling and skill development
* **Opportunities Mode**: Curated job opportunities with matching algorithms
* **Live Expert Mode**: Connect with MENA Careers' expert team for personalized help

= Premium Design Features =

* Elegant glassmorphism chat interface
* Professional typography with Playfair Display and Inter fonts
* Smooth typing animations for natural conversation flow
* Time-based contextual greetings
* Mobile-responsive design

= Technical Highlights =

* Secure database architecture with manual table management
* Session management for guests and registered users
* AJAX-powered real-time messaging
* File upload support for CV analysis (Phase 2+)
* Claude API integration for intelligent responses (Phase 2+)

== Installation ==

1. Upload the `mena-careers` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'SF Finance' in your WordPress admin menu
4. Go to 'Database' and click 'Create All Tables' to set up the database
5. Configure your settings under 'SF Finance > Settings'
6. Add the shortcode `[skill_farm_finance_chat]` to any page or post

== Frequently Asked Questions ==

= Do I need an API key to use this plugin? =

For Phase 1, the plugin works with placeholder responses. Starting from Phase 2, you'll need a Claude API key from Anthropic for full AI functionality.

= Can multiple users chat simultaneously? =

Yes, the plugin supports multiple concurrent users with individual session management.

= Is the chat history saved? =

Yes, all conversations are stored in the database and can be viewed from the admin panel.

= Can I customize the appearance? =

Yes, you can customize colors, font sizes, and enable/disable glassmorphism effects from the settings page.

= Which modes are available in Phase 1? =

Phase 1 includes the basic infrastructure for all modes with placeholder responses. Full mode functionality will be implemented in subsequent phases.

== Screenshots ==

1. Main chat interface with glassmorphism design
2. Admin dashboard with conversation statistics
3. Database management interface
4. Settings configuration page
5. Mode selection sidebar

== Changelog ==

= 1.0.0 - Phase 1 =
* Initial release
* Core plugin infrastructure
* Database table creation and management
* Admin dashboard and settings
* Basic chat UI with glassmorphism effects
* Session management for users and guests
* AJAX messaging foundation
* Mode switching interface

== Upgrade Notice ==

= 5.1.0 =
Critical fix for 500 Internal Server Error. Fixed missing class dependencies and non-existent class references.

= 5.0.0 =
Major update: Added contextual engagement buttons, hyper-personalization, removed fabricated data, improved user focus.

= 1.0.0 =
Initial release of MENA Careers plugin.

== Changelog ==

= 6.0.1 =
* Fixed: Removed MENA Careers greeting messages and profile completion prompts
* Fixed: Removed search wrapper section from interface
* Fixed: Removed application tracker widget display
* Fixed: Adjusted shortlist panel positioning to prevent blocking content
* Improved: Reduced top padding and spacing to push content up
* Improved: Shortlist panel now slides in from right edge only when activated

= 5.1.0 =
* Fixed: Critical 500 Internal Server Error in AJAX handlers
* Fixed: Missing require_once for SFFC_Session_Manager and SFFC_Database classes
* Fixed: Removed non-existent SFFC_Response_Formatter class reference
* Fixed: PHP Fatal errors preventing plugin functionality

= 5.0.0 =
* Added: Contextual engagement button system
* Added: Hyper-personalization with natural name collection
* Added: Professional greeting system without bizarre messages
* Improved: User-focused responses instead of fabricated market data
* Added: Engagement buttons above and below messages
* Added: Mini engagement buttons for quick actions
* Fixed: Multiple greeting and response issues

== Development Roadmap ==

= Phase 2 (Upcoming) =
* Claude API integration
* Comprehensive template library (200+ templates)
* Template rotation system

= Phase 3 (Planned) =
* Premium typing experience
* Natural conversation flow
* Time-based greeting system

= Phase 4 (Planned) =
* File processing system (PDF, DOCX, XLSX, TXT)
* CV analysis capabilities
* Export functionality

= Phase 5-10 (Future) =
* Full implementation of all modes
* Live expert system
* Advanced analytics
* Performance optimizations

== Support ==

For support, please contact support.team@joinsenna.com or visit our documentation at https://menacareers.com/docs

== Privacy Policy ==

This plugin stores user conversations and profile data locally in your WordPress database. No data is sent to external servers except when using the Claude API (Phase 2+) for generating responses.