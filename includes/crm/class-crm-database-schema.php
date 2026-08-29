<?php
/**
 * CRM Database Schema
 * Phase 1: Foundation tables for Candidate-to-Recruiter CRM
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Database_Schema {

    private static $instance = null;
    private $db_version = '1.34.0';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create all CRM database tables
     */
    public function create_tables() {
        $installed_version = get_option('sffc_crm_db_version', '0');
        if (version_compare($installed_version, $this->db_version, '>=')) {
            return;
        }

        global $wpdb;

        $suppress = $wpdb->suppress_errors(true);
        $show_errors = $wpdb->show_errors(false);

        $charset_collate = $wpdb->get_charset_collate();

        // Normalize legacy pipeline stages before schema updates so enum changes are safe
        $this->migrate_legacy_pipeline_stages();

        // ============================================
        // RECRUITERS TABLE
        // Core CRM entity - people candidates reach out to
        // ============================================
        $table_recruiters = $wpdb->prefix . 'sffc_crm_recruiters';
        $sql_recruiters = "CREATE TABLE $table_recruiters (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            first_name varchar(100),
            last_name varchar(100),
            photo_url varchar(500),
            title varchar(200),
            firm varchar(200),
            firm_type enum('search_firm','corporate','independent','agency') DEFAULT 'search_firm',
            email varchar(200),
            email_verified tinyint(1) DEFAULT 0,
            linkedin_url varchar(500),
            linkedin_id varchar(100),
            website varchar(500),
            phone varchar(50),
            location varchar(200),
            sectors longtext,
            seniority_levels longtext,
            regions longtext,
            bio text,
            response_rate decimal(5,2),
            avg_response_days int(11),
            total_posts int(11) DEFAULT 0,
            last_post_date datetime,
            is_verified tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            data_source varchar(50),
            source_url varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_firm (firm),
            KEY idx_location (location),
            KEY idx_active (is_active),
            KEY idx_last_post (last_post_date),
            KEY idx_linkedin (linkedin_id),
            UNIQUE KEY unique_linkedin (linkedin_id),
            FULLTEXT KEY ft_search (name, firm, title, bio)
        ) $charset_collate;";

        // ============================================
        // RECRUITER POSTS TABLE
        // Opportunity posts from recruiters (from LinkedIn, websites, etc.)
        // ============================================
        $table_posts = $wpdb->prefix . 'sffc_crm_posts';
        $sql_posts = "CREATE TABLE $table_posts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            recruiter_id bigint(20) NOT NULL,
            source enum('linkedin','website','manual','import') DEFAULT 'linkedin',
            source_url varchar(500),
            source_id varchar(100),
            role_title varchar(300),
            role_title_standardized varchar(300),
            company varchar(200),
            location varchar(200),
            location_country varchar(100),
            location_city varchar(100),
            salary_min int(11),
            salary_max int(11),
            salary_currency varchar(10) DEFAULT 'USD',
            salary_text varchar(100),
            seniority enum('intern','analyst','senior_analyst','associate','senior_associate','vp','senior_vp','director','md','partner','c_level','board','other'),
            sector varchar(100),
            content text NOT NULL,
            content_snippet varchar(500),
            requirements longtext,
            skills_mentioned longtext,
            experience_years varchar(50),
            is_remote tinyint(1) DEFAULT 0,
            is_hybrid tinyint(1) DEFAULT 0,
            engagement_count int(11) DEFAULT 0,
            posted_at datetime,
            expires_at datetime,
            is_active tinyint(1) DEFAULT 1,
            is_featured tinyint(1) DEFAULT 0,
            is_early_bird tinyint(1) DEFAULT 0,
            exclude_from_early_bird tinyint(1) DEFAULT 0,
            response_label varchar(200) DEFAULT NULL,
            response_badge varchar(200) DEFAULT NULL,
            jobseeker_notes text DEFAULT NULL,
            knockout_questions longtext DEFAULT NULL,
            materials longtext DEFAULT NULL,
            interview_questions longtext DEFAULT NULL,
            interview_questions_docx varchar(500) DEFAULT NULL,
            cv_template_docx varchar(500) DEFAULT NULL,
            cover_letter_html longtext DEFAULT NULL,
            cover_letter_docx varchar(500) DEFAULT NULL,
            case_study_pdf varchar(500) DEFAULT NULL,
            opening_date date DEFAULT NULL,
            closing_date date DEFAULT NULL,
            starting_date date DEFAULT NULL,
            duration varchar(200) DEFAULT NULL,
            application_process longtext DEFAULT NULL,
            team_contacts longtext DEFAULT NULL,
            keywords text NULL,
            keywords_manual tinyint(1) DEFAULT 0,
            post_status enum('open','closed') DEFAULT 'open',
            admin_approved tinyint(1) DEFAULT 0,
            publish_to_jobs tinyint(1) DEFAULT 1,
            source_platform varchar(80) DEFAULT NULL,
            source_platform_custom varchar(120) DEFAULT NULL,
            company_logo varchar(500) DEFAULT NULL,
            recruiter_display_name varchar(200) DEFAULT NULL,
            recruiter_display_company varchar(200) DEFAULT NULL,
            admin_notes text,
            application_url varchar(500),
            wp_post_id bigint(20),
            jobs_post_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_posted (posted_at),
            KEY idx_active (is_active),
            KEY idx_approved (admin_approved),
            KEY idx_sector (sector),
            KEY idx_seniority (seniority),
            KEY idx_location (location_country, location_city),
            KEY idx_source (source_id),
            KEY idx_wp_post (wp_post_id),
            KEY idx_jobs_post (jobs_post_id),
            UNIQUE KEY unique_source (source, source_id),
            FULLTEXT KEY ft_content (role_title, content, company)
        ) $charset_collate;";

        // ============================================
        // JOB DRAFTS TABLE
        // Raw scanner/import intake before editorial approval
        // ============================================
        $table_job_drafts = $wpdb->prefix . 'sffc_crm_job_drafts';
        $sql_job_drafts = "CREATE TABLE $table_job_drafts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_url varchar(500) DEFAULT NULL,
            application_url varchar(500) DEFAULT NULL,
            source_platform varchar(80) DEFAULT NULL,
            source_platform_custom varchar(120) DEFAULT NULL,
            external_job_id varchar(160) DEFAULT NULL,
            source_hash varchar(64) DEFAULT NULL,
            raw_title varchar(300) DEFAULT NULL,
            raw_company varchar(200) DEFAULT NULL,
            raw_location varchar(200) DEFAULT NULL,
            raw_location_city varchar(100) DEFAULT NULL,
            raw_location_country varchar(100) DEFAULT NULL,
            raw_salary_text varchar(160) DEFAULT NULL,
            raw_company_logo varchar(500) DEFAULT NULL,
            raw_sector varchar(100) DEFAULT NULL,
            raw_seniority varchar(80) DEFAULT NULL,
            raw_experience_years varchar(50) DEFAULT NULL,
            posted_at datetime DEFAULT NULL,
            raw_posted_at varchar(160) DEFAULT NULL,
            raw_content longtext,
            extracted_payload longtext,
            rewritten_payload longtext,
            confidence_score decimal(5,2) DEFAULT 0.00,
            duplicate_of bigint(20) DEFAULT NULL,
            status varchar(40) DEFAULT 'new',
            error_message text DEFAULT NULL,
            approved_crm_post_id bigint(20) DEFAULT NULL,
            approved_by bigint(20) DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            rejected_by bigint(20) DEFAULT NULL,
            rejected_at datetime DEFAULT NULL,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_source_hash (source_hash),
            KEY idx_source_platform (source_platform),
            KEY idx_approved_crm_post (approved_crm_post_id),
            KEY idx_duplicate_of (duplicate_of),
            KEY idx_created_at (created_at),
            KEY idx_posted_at (posted_at),
            FULLTEXT KEY ft_draft_search (raw_title, raw_company, raw_location, raw_content)
        ) $charset_collate;";

        // NOTE: Column additions moved to after dbDelta to ensure table exists first

        // ============================================
        // HR OUTREACH TABLE
        // Curated talent acquisition + team contacts
        // ============================================
        $table_hr_outreach = $wpdb->prefix . 'sffc_crm_hr_outreach';
        $sql_hr_outreach = "CREATE TABLE $table_hr_outreach (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            company_name varchar(200) NOT NULL,
            company_logo varchar(500),
            company_url varchar(500),
            location varchar(200),
            regions varchar(200),
            industry varchar(500),
            program_types varchar(200),
            process varchar(500),
            hire_interns tinyint(1) DEFAULT 0,
            hire_graduates tinyint(1) DEFAULT 0,
            hire_analysts tinyint(1) DEFAULT 0,
            hire_associates tinyint(1) DEFAULT 0,
            hire_seniors tinyint(1) DEFAULT 0,
            hire_private_equity_candidates tinyint(1) DEFAULT 0,
            hire_expats tinyint(1) DEFAULT 0,
            hire_cfa_holders tinyint(1) DEFAULT 0,
            hire_oxbridge tinyint(1) DEFAULT 0,
            hire_russell_group tinyint(1) DEFAULT 0,
            hire_non_target tinyint(1) DEFAULT 0,
            hire_mba tinyint(1) DEFAULT 0,
            hire_visa_sponsorship tinyint(1) DEFAULT 0,
            hire_arabic_speakers tinyint(1) DEFAULT 0,
            hire_bilingual tinyint(1) DEFAULT 0,
            hire_trainee tinyint(1) DEFAULT 0,
            hire_placement tinyint(1) DEFAULT 0,
            skills varchar(1000),
            role_focus text,
            last_hire_proof varchar(500),
            interview_questions_url varchar(500),
            cv_template_url varchar(500),
            cover_letter_url varchar(500),
            company_intel_url varchar(500),
            contact_name varchar(200) NOT NULL,
            contact_title varchar(200),
            contact_email varchar(200),
            contact_phone varchar(100),
            contact_linkedin varchar(500),
            contact_photo varchar(500),
            team_contacts longtext,
            notes text,
            source_context varchar(50) DEFAULT 'curated',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_company (company_name)
        ) $charset_collate;";

        // ============================================
        // SAVED RECRUITERS TABLE
        // Candidates' saved/bookmarked recruiters
        // ============================================
        $table_saved_recruiters = $wpdb->prefix . 'sffc_crm_saved_recruiters';
        $sql_saved_recruiters = "CREATE TABLE $table_saved_recruiters (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            recruiter_id bigint(20) NOT NULL,
            status enum('new','contacted','replied','in_conversation','dormant') DEFAULT 'new',
            notes text,
            tags varchar(500),
            priority enum('low','medium','high') DEFAULT 'medium',
            saved_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_contacted_at datetime,
            last_reply_at datetime,
            next_followup_at datetime,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_status (status),
            KEY idx_followup (next_followup_at),
            UNIQUE KEY unique_user_recruiter (user_id, recruiter_id)
        ) $charset_collate;";

        // ============================================
        // SAVED POSTS TABLE
        // Candidates' saved opportunity posts
        // ============================================
        $table_saved_posts = $wpdb->prefix . 'sffc_crm_saved_posts';
        $sql_saved_posts = "CREATE TABLE $table_saved_posts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            recruiter_id bigint(20),
            folder varchar(100) DEFAULT 'default',
            notes text,
            saved_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_post (post_id),
            KEY idx_folder (folder),
            UNIQUE KEY unique_user_post (user_id, post_id)
        ) $charset_collate;";

        // ============================================
        // MEMBER SIGNUP EVENTS TABLE
        // External apply selections and related signup routing choices
        // ============================================
        $sql_member_signup_events = $this->get_member_signup_events_table_sql($charset_collate);
        $sql_application_tasks = $this->get_application_tasks_table_sql($charset_collate);

        // ============================================
        // PIPELINE TABLE
        // Track opportunities through stages
        // Supports both platform leads (with recruiter_id) and manual leads (without)
        // ============================================
        $table_pipeline = $wpdb->prefix . 'sffc_crm_pipeline';
        $sql_pipeline = "CREATE TABLE $table_pipeline (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            recruiter_id bigint(20) DEFAULT NULL,
            post_id bigint(20) DEFAULT NULL,
            stage enum('not_applied','interested','application_submitted','messaged','follow_up','online_assessment','case_study','hirevue','telephone_interview','video_interview','face_to_face_interview','assessment_centre','offer_received','rejected','not_interested') DEFAULT 'not_applied',
            outcome enum('won','lost','withdrawn') DEFAULT NULL,
            outcome_reason varchar(200),
            role_title varchar(300),
            company varchar(200),
            location varchar(200),
            salary_min int(11),
            salary_max int(11),
            notes text,
            next_action text,
            next_action_date datetime,
            cv_version_sent text,
            cover_letter_sent text,
            source enum('platform','linkedin','indeed','referral','company_website','other') DEFAULT 'platform',
            external_url varchar(500),
            contact_name varchar(200),
            contact_email varchar(200),
            contact_linkedin varchar(500),
            stage_entered_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            closed_at datetime,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_stage (stage),
            KEY idx_outcome (outcome),
            KEY idx_next_action (next_action_date),
            KEY idx_source (source)
        ) $charset_collate;";

        // NOTE: Column additions moved to after dbDelta to ensure table exists first

        // ============================================
        // PIPELINE HISTORY TABLE
        // Track stage transitions
        // ============================================
        $table_pipeline_history = $wpdb->prefix . 'sffc_crm_pipeline_history';
        $sql_pipeline_history = "CREATE TABLE $table_pipeline_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            pipeline_id bigint(20) NOT NULL,
            from_stage varchar(50),
            to_stage varchar(50) NOT NULL,
            notes text,
            transitioned_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pipeline (pipeline_id),
            KEY idx_date (transitioned_at)
        ) $charset_collate;";

        // ============================================
        // APPLICANTS TABLE
        // Capture applicant submissions from recruiter posts
        // ============================================
        $table_applicants = $wpdb->prefix . 'sffc_crm_applicants';
        $sql_applicants = "CREATE TABLE $table_applicants (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            recruiter_post_id bigint(20) DEFAULT NULL,
            crm_post_id bigint(20) DEFAULT NULL,
            recruiter_id bigint(20) DEFAULT NULL,
            job_title varchar(300),
            company varchar(200),
            first_name varchar(100),
            last_name varchar(100),
            email varchar(200),
            materials longtext,
            source varchar(50) DEFAULT 'wp',
            form_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_crm_post (crm_post_id),
            KEY idx_post (recruiter_post_id),
            KEY idx_email (email)
        ) $charset_collate;";

        // ============================================
        // OUTREACH TABLE
        // Track all messages sent to recruiters
        // ============================================
        $table_outreach = $wpdb->prefix . 'sffc_crm_outreach';
        $sql_outreach = "CREATE TABLE $table_outreach (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            recruiter_id bigint(20) NOT NULL,
            post_id bigint(20),
            pipeline_id bigint(20),
            sequence_id bigint(20),
            sequence_step int(11),
            channel enum('email','linkedin') NOT NULL,
            message_type enum('initial','followup','reply','connection_request') DEFAULT 'initial',
            subject varchar(500),
            content text NOT NULL,
            content_html text,
            template_id bigint(20),
            status enum('draft','scheduled','sent','opened','clicked','replied','bounced','failed') DEFAULT 'draft',
            scheduled_at datetime,
            sent_at datetime,
            opened_at datetime,
            clicked_at datetime,
            replied_at datetime,
            open_count int(11) DEFAULT 0,
            click_count int(11) DEFAULT 0,
            tracking_id varchar(100),
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_status (status),
            KEY idx_channel (channel),
            KEY idx_sent (sent_at),
            KEY idx_sequence (sequence_id, sequence_step),
            KEY idx_tracking (tracking_id)
        ) $charset_collate;";

        // ============================================
        // TEMPLATES TABLE
        // Reusable message templates
        // ============================================
        $table_templates = $wpdb->prefix . 'sffc_crm_templates';
        $sql_templates = "CREATE TABLE $table_templates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20),
            name varchar(200) NOT NULL,
            description text,
            channel enum('email','linkedin','both') DEFAULT 'email',
            template_type enum('initial','followup','connection','thank_you','custom') DEFAULT 'initial',
            subject varchar(500),
            content text NOT NULL,
            variables_used longtext,
            is_system tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            usage_count int(11) DEFAULT 0,
            reply_rate decimal(5,2),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_type (template_type),
            KEY idx_channel (channel),
            KEY idx_system (is_system),
            UNIQUE KEY unique_system_template (name, is_system)
        ) $charset_collate;";

        // ============================================
        // ACTIVITY LOG TABLE
        // Track all user actions in CRM
        // ============================================
        $table_activity = $wpdb->prefix . 'sffc_crm_activity';
        $sql_activity = "CREATE TABLE $table_activity (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            recruiter_id bigint(20),
            post_id bigint(20),
            pipeline_id bigint(20),
            activity_type varchar(50) NOT NULL,
            activity_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_type (activity_type),
            KEY idx_date (created_at)
        ) $charset_collate;";

        // ============================================
        // NOTES TABLE
        // Notes on recruiters, posts, pipeline items
        // ============================================
        $table_notes = $wpdb->prefix . 'sffc_crm_notes';
        $sql_notes = "CREATE TABLE $table_notes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            entity_type enum('recruiter','post','pipeline') NOT NULL,
            entity_id bigint(20) NOT NULL,
            content text NOT NULL,
            is_pinned tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_pinned (is_pinned)
        ) $charset_collate;";

        // ============================================
        // USAGE TRACKING TABLE
        // Track feature usage for limit enforcement
        // ============================================
        $table_usage = $wpdb->prefix . 'sffc_crm_usage';
        $sql_usage = "CREATE TABLE $table_usage (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            period_month varchar(7) NOT NULL,
            outreach_count int(11) DEFAULT 0,
            ai_generation_count int(11) DEFAULT 0,
            posts_viewed int(11) DEFAULT 0,
            recruiters_saved int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_month (user_id, period_month)
        ) $charset_collate;";

        // ============================================
        // TAGS TABLE (Phase 2)
        // User-defined tags for organizing recruiters
        // ============================================
        $table_tags = $wpdb->prefix . 'sffc_crm_tags';
        $sql_tags = "CREATE TABLE $table_tags (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(100) NOT NULL,
            color varchar(7) DEFAULT '#6b7280',
            usage_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            UNIQUE KEY unique_user_tag (user_id, name)
        ) $charset_collate;";

        // ============================================
        // RECRUITER TAGS TABLE (Phase 2)
        // Junction table for recruiter-tag relationships
        // ============================================
        $table_recruiter_tags = $wpdb->prefix . 'sffc_crm_recruiter_tags';
        $sql_recruiter_tags = "CREATE TABLE $table_recruiter_tags (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            recruiter_id bigint(20) NOT NULL,
            tag_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_recruiter (user_id, recruiter_id),
            KEY idx_tag (tag_id),
            UNIQUE KEY unique_recruiter_tag (user_id, recruiter_id, tag_id)
        ) $charset_collate;";

        // ============================================
        // TASKS TABLE (Phase 2)
        // User tasks and reminders
        // ============================================
        $table_tasks = $wpdb->prefix . 'sffc_crm_tasks';
        $sql_tasks = "CREATE TABLE $table_tasks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            recruiter_id bigint(20),
            post_id bigint(20),
            pipeline_id bigint(20),
            sequence_id bigint(20),
            enrollment_id bigint(20),
            step_index int(11),
            task_type enum('follow_up','send_email','linkedin_message','linkedin_connect','research','interview_prep','custom') DEFAULT 'custom',
            title varchar(300) NOT NULL,
            description text,
            pre_filled_subject varchar(500),
            pre_filled_content text,
            template_id bigint(20),
            due_date datetime,
            due_time time,
            completed_at datetime,
            skipped_at datetime,
            snoozed_until datetime,
            is_auto_generated tinyint(1) DEFAULT 0,
            priority enum('low','medium','high') DEFAULT 'medium',
            status enum('pending','completed','skipped','snoozed') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_due (due_date),
            KEY idx_status (status),
            KEY idx_completed (completed_at),
            KEY idx_recruiter (recruiter_id),
            KEY idx_enrollment (enrollment_id)
        ) $charset_collate;";

        // ============================================
        // SEQUENCES TABLE (Phase 4)
        // Automated outreach sequences
        // ============================================
        $table_sequences = $wpdb->prefix . 'sffc_crm_sequences';
        $sql_sequences = "CREATE TABLE $table_sequences (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(200) NOT NULL,
            description text,
            is_active tinyint(1) DEFAULT 1,
            is_system tinyint(1) DEFAULT 0,
            trigger_type enum('manual','on_save','on_outreach') DEFAULT 'manual',
            settings longtext,
            enrolled_count int(11) DEFAULT 0,
            completed_count int(11) DEFAULT 0,
            replied_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_active (is_active),
            KEY idx_system (is_system)
        ) $charset_collate;";

        // ============================================
        // SEQUENCE STEPS TABLE (Phase 4)
        // Individual steps within a sequence
        // ============================================
        $table_sequence_steps = $wpdb->prefix . 'sffc_crm_sequence_steps';
        $sql_sequence_steps = "CREATE TABLE $table_sequence_steps (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sequence_id bigint(20) NOT NULL,
            step_order int(11) NOT NULL DEFAULT 0,
            step_type enum('auto_email','manual_email','linkedin_message','linkedin_connect','wait','task') NOT NULL,
            delay_days int(11) DEFAULT 0,
            delay_hours int(11) DEFAULT 0,
            channel enum('email','linkedin') DEFAULT 'email',
            template_id bigint(20),
            subject varchar(500),
            content text,
            task_title varchar(300),
            task_description text,
            send_time_preference enum('morning','afternoon','evening','any') DEFAULT 'morning',
            skip_weekends tinyint(1) DEFAULT 1,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sequence (sequence_id),
            KEY idx_order (sequence_id, step_order)
        ) $charset_collate;";

        // ============================================
        // SEQUENCE ENROLLMENTS TABLE (Phase 4)
        // Track recruiters enrolled in sequences
        // ============================================
        $table_enrollments = $wpdb->prefix . 'sffc_crm_sequence_enrollments';
        $sql_enrollments = "CREATE TABLE $table_enrollments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sequence_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            recruiter_id bigint(20) NOT NULL,
            post_id bigint(20),
            current_step_index int(11) DEFAULT 0,
            status enum('active','paused','completed','replied','bounced','unsubscribed') DEFAULT 'active',
            enrolled_at datetime DEFAULT CURRENT_TIMESTAMP,
            next_step_at datetime,
            last_step_at datetime,
            completed_at datetime,
            paused_at datetime,
            pause_reason varchar(200),
            replied_at datetime,
            personalization_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sequence (sequence_id),
            KEY idx_user (user_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_status (status),
            KEY idx_next_step (next_step_at),
            UNIQUE KEY unique_enrollment (sequence_id, user_id, recruiter_id)
        ) $charset_collate;";

        // ============================================
        // SEQUENCE ENROLLMENT HISTORY TABLE (Phase 4)
        // Track step executions within enrollments
        // ============================================
        $table_enrollment_history = $wpdb->prefix . 'sffc_crm_enrollment_history';
        $sql_enrollment_history = "CREATE TABLE $table_enrollment_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            enrollment_id bigint(20) NOT NULL,
            step_index int(11) NOT NULL,
            step_type varchar(50) NOT NULL,
            action_taken enum('executed','skipped','failed') NOT NULL,
            outreach_id bigint(20),
            task_id bigint(20),
            notes text,
            executed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_enrollment (enrollment_id),
            KEY idx_step (enrollment_id, step_index)
        ) $charset_collate;";

        // ============================================
        // CONVERSATIONS TABLE (Phase 5)
        // Email threads/conversations with recruiters
        // ============================================
        $table_conversations = $wpdb->prefix . 'sffc_crm_conversations';
        $sql_conversations = "CREATE TABLE $table_conversations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            recruiter_id bigint(20),
            post_id bigint(20),
            subject varchar(500),
            channel enum('email','linkedin','manual') DEFAULT 'email',
            status enum('active','archived','spam') DEFAULT 'active',
            is_read tinyint(1) DEFAULT 0,
            is_starred tinyint(1) DEFAULT 0,
            last_message_at datetime,
            last_message_preview varchar(200),
            last_message_direction enum('inbound','outbound') DEFAULT 'outbound',
            message_count int(11) DEFAULT 0,
            unread_count int(11) DEFAULT 0,
            thread_id varchar(200),
            labels varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_status (status),
            KEY idx_read (is_read),
            KEY idx_last_message (last_message_at),
            KEY idx_thread (thread_id),
            UNIQUE KEY unique_thread (user_id, thread_id)
        ) $charset_collate;";

        // ============================================
        // MESSAGES TABLE (Phase 5)
        // Individual messages within conversations
        // ============================================
        $table_messages = $wpdb->prefix . 'sffc_crm_messages';
        $sql_messages = "CREATE TABLE $table_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            recruiter_id bigint(20),
            outreach_id bigint(20),
            direction enum('inbound','outbound') NOT NULL,
            channel enum('email','linkedin','manual') DEFAULT 'email',
            from_email varchar(200),
            from_name varchar(200),
            to_email varchar(200),
            to_name varchar(200),
            subject varchar(500),
            content text NOT NULL,
            content_html text,
            content_snippet varchar(200),
            is_read tinyint(1) DEFAULT 0,
            read_at datetime,
            external_id varchar(200),
            external_thread_id varchar(200),
            headers longtext,
            attachments longtext,
            sent_at datetime,
            received_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_conversation (conversation_id),
            KEY idx_user (user_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_direction (direction),
            KEY idx_read (is_read),
            KEY idx_sent (sent_at),
            KEY idx_external (external_id),
            KEY idx_outreach (outreach_id)
        ) $charset_collate;";

        // ============================================
        // EMAIL ACCOUNTS TABLE (Phase 5)
        // Connected email accounts (Gmail/Outlook)
        // ============================================
        $table_email_accounts = $wpdb->prefix . 'sffc_crm_email_accounts';
        $sql_email_accounts = "CREATE TABLE $table_email_accounts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            provider enum('gmail','outlook','other') NOT NULL,
            email_address varchar(200) NOT NULL,
            display_name varchar(200),
            access_token text,
            refresh_token text,
            token_expires_at datetime,
            sync_enabled tinyint(1) DEFAULT 1,
            last_sync_at datetime,
            last_sync_error text,
            sync_from_date datetime,
            settings longtext,
            is_primary tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_provider (provider),
            KEY idx_email (email_address),
            KEY idx_sync (sync_enabled, last_sync_at),
            UNIQUE KEY unique_user_email (user_id, email_address)
        ) $charset_collate;";

        // ============================================
        // ALERTS TABLE (Phase 6)
        // User-configured alerts for posts, keywords, etc.
        // ============================================
        $table_alerts = $wpdb->prefix . 'sffc_crm_alerts';
        $sql_alerts = "CREATE TABLE $table_alerts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            name varchar(200) NOT NULL,
            config longtext,
            is_active tinyint(1) DEFAULT 1,
            email_enabled tinyint(1) DEFAULT 1,
            push_enabled tinyint(1) DEFAULT 0,
            last_triggered_at datetime,
            trigger_count int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_type (type),
            KEY idx_active (is_active)
        ) $charset_collate;";

        // ============================================
        // NOTIFICATIONS TABLE (Phase 6)
        // Triggered notifications from alerts
        // ============================================
        $table_notifications = $wpdb->prefix . 'sffc_crm_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            alert_id bigint(20),
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text,
            data longtext,
            is_read tinyint(1) DEFAULT 0,
            read_at datetime,
            action_url varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_read (user_id, is_read),
            KEY idx_alert (alert_id),
            KEY idx_created (created_at)
        ) $charset_collate;";

        // ============================================
        // INTERNSHIP ALERT EMAIL QUEUE
        // Queued delivery records for batched internship alert emails
        // ============================================
        $table_internship_alert_queue = $wpdb->prefix . 'sffc_crm_internship_alert_queue';
        $sql_internship_alert_queue = "CREATE TABLE $table_internship_alert_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            alert_type varchar(50) NOT NULL DEFAULT 'internship',
            status varchar(20) NOT NULL DEFAULT 'pending',
            delivery_transport varchar(50) DEFAULT NULL,
            delivery_status varchar(50) DEFAULT NULL,
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 5,
            next_attempt_at datetime DEFAULT CURRENT_TIMESTAMP,
            locked_at datetime DEFAULT NULL,
            scheduled_for datetime DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            provider_reference varchar(255) DEFAULT NULL,
            last_event_type varchar(50) DEFAULT NULL,
            last_event_at datetime DEFAULT NULL,
            last_event_reason text,
            processed_at datetime DEFAULT NULL,
            delivered_at datetime DEFAULT NULL,
            deferred_at datetime DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            bounced_at datetime DEFAULT NULL,
            dropped_at datetime DEFAULT NULL,
            spamreport_at datetime DEFAULT NULL,
            last_error text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_post_user_alert (post_id, user_id, alert_type),
            KEY idx_status_attempt (status, next_attempt_at),
            KEY idx_delivery_status (delivery_status),
            KEY idx_post (post_id),
            KEY idx_user (user_id),
            KEY idx_locked (locked_at),
            KEY idx_created (created_at)
        ) $charset_collate;";

        // ============================================
        // FREE ALERT DIGEST QUEUE
        // Queued grouped digest emails for non-paying users
        // ============================================
        $table_free_alert_digest_queue = $wpdb->prefix . 'sffc_crm_free_alert_digest_queue';
        $sql_free_alert_digest_queue = "CREATE TABLE $table_free_alert_digest_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            cycle_key varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            match_count int(11) DEFAULT 0,
            selected_post_id bigint(20) DEFAULT 0,
            next_attempt_at datetime DEFAULT CURRENT_TIMESTAMP,
            locked_at datetime DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            provider_reference varchar(255) DEFAULT NULL,
            last_error text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_cycle (user_id, cycle_key),
            KEY idx_status_attempt (status, next_attempt_at),
            KEY idx_user (user_id),
            KEY idx_cycle (cycle_key),
            KEY idx_selected_post (selected_post_id),
            KEY idx_locked (locked_at),
            KEY idx_created (created_at)
        ) $charset_collate;";

        // ============================================
        // SENDGRID EVENT LOG
        // Raw event webhook history for internship alert delivery tracking
        // ============================================
        $table_sendgrid_events = $wpdb->prefix . 'sffc_crm_sendgrid_events';
        $sql_sendgrid_events = "CREATE TABLE $table_sendgrid_events (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            queue_id bigint(20) DEFAULT NULL,
            post_id bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            email varchar(200) DEFAULT NULL,
            event_type varchar(50) NOT NULL,
            event_at datetime DEFAULT NULL,
            sg_event_id varchar(255) DEFAULT NULL,
            sg_message_id varchar(255) DEFAULT NULL,
            provider_reference varchar(255) DEFAULT NULL,
            reason text,
            payload longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_sg_event_id (sg_event_id),
            KEY idx_queue (queue_id),
            KEY idx_post (post_id),
            KEY idx_user (user_id),
            KEY idx_event_type (event_type),
            KEY idx_event_at (event_at)
        ) $charset_collate;";

        // ============================================
        // ALERT DEFAULT PROFILES
        // Admin-managed default alert preferences for users until they customize.
        // ============================================
        $table_alert_default_profiles = $wpdb->prefix . 'sffc_crm_alert_default_profiles';
        $sql_alert_default_profiles = "CREATE TABLE $table_alert_default_profiles (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            description text,
            enabled_by_default tinyint(1) DEFAULT 1,
            sectors longtext,
            types longtext,
            locations longtext,
            work_modes longtext,
            group_ids longtext,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active (is_active),
            KEY idx_name (name)
        ) $charset_collate;";

        // ============================================
        // ALERT PROFILE USER ASSIGNMENTS
        // Maps one default alert profile to each user.
        // ============================================
        $table_alert_profile_users = $wpdb->prefix . 'sffc_crm_alert_profile_users';
        $sql_alert_profile_users = "CREATE TABLE $table_alert_profile_users (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            assigned_by bigint(20) DEFAULT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user (user_id),
            KEY idx_profile (profile_id),
            KEY idx_assigned_at (assigned_at)
        ) $charset_collate;";

        // ============================================
        // EXPERT OUTREACH TABLE
        // Manual and auto expert reach out requests
        // ============================================
        $table_expert_outreach = $wpdb->prefix . 'sffc_crm_expert_outreach';
        $sql_expert_outreach = "CREATE TABLE $table_expert_outreach (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            recruiter_id bigint(20),
            contact_id bigint(20),
            request_type enum('manual','auto') DEFAULT 'manual',
            status enum('pending','in_progress','sent','replied','failed','cancelled') DEFAULT 'pending',
            message_tone enum('formal','casual','professional') DEFAULT 'professional',
            custom_notes text,
            include_cv tinyint(1) DEFAULT 1,
            admin_notes text,
            sent_at datetime,
            replied_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_status (status),
            KEY idx_type (request_type),
            KEY idx_created (created_at)
        ) $charset_collate;";

        // ============================================
        // AUTO OUTREACH SETTINGS TABLE
        // User preferences for automated expert outreach
        // ============================================
        $table_auto_outreach = $wpdb->prefix . 'sffc_crm_auto_outreach_settings';
        $sql_auto_outreach = "CREATE TABLE $table_auto_outreach (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            is_enabled tinyint(1) DEFAULT 0,
            target_sectors longtext,
            target_seniority longtext,
            target_locations longtext,
            target_firm_types longtext,
            weekly_limit int(11) DEFAULT 10,
            message_tone enum('formal','casual','professional') DEFAULT 'professional',
            include_cv tinyint(1) DEFAULT 1,
            custom_intro text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user (user_id),
            KEY idx_enabled (is_enabled)
        ) $charset_collate;";

        $sql_auto_apply_contacts = $this->get_auto_apply_contacts_table_sql($charset_collate);

        // ============================================
        // OUTREACH LISTS TABLE
        // User-created lists of recruiters for organized outreach campaigns
        // ============================================
        $table_outreach_lists = $wpdb->prefix . 'sffc_crm_outreach_lists';
        $sql_outreach_lists = "CREATE TABLE $table_outreach_lists (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            list_name varchar(255) NOT NULL,
            description text,
            recruiter_count int(11) DEFAULT 0,
            last_outreach_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_created (created_at)
        ) $charset_collate;";

        // ============================================
        // OUTREACH LIST MEMBERS TABLE
        // Junction table linking recruiters to outreach lists
        // ============================================
        $table_outreach_list_members = $wpdb->prefix . 'sffc_crm_outreach_list_members';
        $sql_outreach_list_members = "CREATE TABLE $table_outreach_list_members (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            list_id bigint(20) NOT NULL,
            recruiter_id bigint(20) NOT NULL,
            added_at datetime DEFAULT CURRENT_TIMESTAMP,
            order_position int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_list (list_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_order (list_id, order_position),
            UNIQUE KEY unique_list_recruiter (list_id, recruiter_id)
        ) $charset_collate;";

        // ============================================
        // JOB OUTREACH LISTS TABLE
        // User-created role queues for one-by-one recruiter outreach
        // ============================================
        $table_job_outreach_lists = $wpdb->prefix . 'sffc_crm_job_outreach_lists';
        $sql_job_outreach_lists = "CREATE TABLE $table_job_outreach_lists (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            list_name varchar(255) NOT NULL,
            description text,
            total_items int(11) DEFAULT 0,
            generated_items int(11) DEFAULT 0,
            sent_items int(11) DEFAULT 0,
            skipped_items int(11) DEFAULT 0,
            last_generated_at datetime DEFAULT NULL,
            last_sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_created (created_at),
            KEY idx_updated (updated_at)
        ) $charset_collate;";

        // ============================================
        // JOB OUTREACH LIST MEMBERS TABLE
        // Stores per-role outreach draft/state for queue processing
        // ============================================
        $table_job_outreach_list_members = $wpdb->prefix . 'sffc_crm_job_outreach_list_members';
        $sql_job_outreach_list_members = "CREATE TABLE $table_job_outreach_list_members (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            list_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            crm_post_id bigint(20) DEFAULT NULL,
            recruiter_id bigint(20) DEFAULT NULL,
            queue_index int(11) DEFAULT 0,
            recruiter_name varchar(200) DEFAULT NULL,
            recruiter_title varchar(200) DEFAULT NULL,
            recruiter_email varchar(200) DEFAULT NULL,
            recruiter_linkedin varchar(500) DEFAULT NULL,
            recruiter_firm varchar(200) DEFAULT NULL,
            role_title varchar(300) DEFAULT NULL,
            company varchar(200) DEFAULT NULL,
            location varchar(200) DEFAULT NULL,
            match_score int(11) DEFAULT 0,
            insight text,
            reasons longtext,
            outreach_status varchar(40) DEFAULT 'queued',
            target_channel varchar(50) DEFAULT 'email',
            generated_subject text,
            generated_body longtext,
            generated_payload longtext,
            generated_with_claude tinyint(1) DEFAULT 0,
            last_generated_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            skipped_at datetime DEFAULT NULL,
            added_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_list (list_id),
            KEY idx_post (post_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_status (outreach_status),
            KEY idx_order (list_id, queue_index),
            UNIQUE KEY unique_list_post (list_id, post_id)
        ) $charset_collate;";

        // ============================================
        // POST GROUPS TABLE
        // Categorize posts into groups (e.g., "Finance Internships in France")
        // ============================================
        $table_post_groups = $wpdb->prefix . 'sffc_crm_post_groups';
        $sql_post_groups = "CREATE TABLE $table_post_groups (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            slug varchar(200) NOT NULL,
            description text,
            location varchar(200) DEFAULT NULL,
            icon varchar(500),
            display_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            is_premium tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slug (slug),
            KEY idx_location (location),
            KEY idx_active (is_active),
            KEY idx_premium (is_premium),
            KEY idx_order (display_order)
        ) $charset_collate;";

        // ============================================
        // POST GROUP RELATIONSHIPS TABLE
        // Many-to-many: posts can belong to multiple groups
        // ============================================
        $table_post_group_relationships = $wpdb->prefix . 'sffc_crm_post_group_relationships';
        $sql_post_group_relationships = "CREATE TABLE $table_post_group_relationships (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            group_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_post_group (post_id, group_id),
            KEY idx_post (post_id),
            KEY idx_group (group_id)
        ) $charset_collate;";

        // ============================================
        // HR CONTACT GROUPS TABLE
        // Categorize HR contacts into grouped lists.
        // ============================================
        $table_hr_contact_groups = $wpdb->prefix . 'sffc_crm_hr_contact_groups';
        $sql_hr_contact_groups = "CREATE TABLE $table_hr_contact_groups (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            slug varchar(200) NOT NULL,
            description text,
            location varchar(200) DEFAULT NULL,
            icon varchar(500),
            display_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slug (slug),
            KEY idx_location (location),
            KEY idx_active (is_active),
            KEY idx_order (display_order)
        ) $charset_collate;";

        // ============================================
        // HR CONTACT GROUP RELATIONSHIPS TABLE
        // Many-to-many: HR contacts can belong to multiple groups.
        // ============================================
        $table_hr_contact_group_relationships = $wpdb->prefix . 'sffc_crm_hr_contact_group_relationships';
        $sql_hr_contact_group_relationships = "CREATE TABLE $table_hr_contact_group_relationships (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) NOT NULL,
            group_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_contact_group (contact_id, group_id),
            KEY idx_contact (contact_id),
            KEY idx_group (group_id)
        ) $charset_collate;";

        // ============================================
        // USER CRITERIA GROUPS TABLE
        // User-created search criteria groups for personalized matching
        // ============================================
        $table_user_criteria_groups = $wpdb->prefix . 'sffc_crm_user_criteria_groups';
        $sql_user_criteria_groups = "CREATE TABLE $table_user_criteria_groups (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(200) NOT NULL,
            slug varchar(200) NOT NULL,
            job_title varchar(500),
            sector text,
            location text,
            experience_level text,
            years_experience varchar(50),
            skills_keywords text,
            cv_file_id bigint(20),
            cover_letter_file_id bigint(20),
            is_default tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_active (is_active),
            KEY idx_default (is_default),
            KEY idx_order (display_order)
        ) $charset_collate;";

        // ============================================
        // SAVED LISTS TABLE
        // User-saved CRM post and HR contact groups.
        // ============================================
        $table_saved_lists = $wpdb->prefix . 'sffc_crm_saved_lists';
        $sql_saved_lists = "CREATE TABLE $table_saved_lists (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            group_type varchar(50) NOT NULL DEFAULT 'jobs',
            group_id bigint(20) NOT NULL,
            group_slug varchar(200) NOT NULL,
            group_name varchar(200) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_group (user_id, group_type, group_id),
            KEY idx_user (user_id),
            KEY idx_group (group_type, group_id),
            KEY idx_created (created_at)
        ) $charset_collate;";

        // ============================================
        // COMPANY PREP TABLE
        // Companies with interview/prep materials
        // ============================================
        $table_company_prep = $wpdb->prefix . 'sffc_crm_company_prep';
        $sql_company_prep = "CREATE TABLE $table_company_prep (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            company_name varchar(200) NOT NULL,
            company_website varchar(500),
            location varchar(200),
            regions_covered text,
            logo_url varchar(500),
            banner_url varchar(500),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active (is_active),
            KEY idx_company_name (company_name)
        ) $charset_collate;";

        // ============================================
        // COMPANY PREP MATERIALS TABLE
        // Files/documents attached to companies
        // ============================================
        $table_prep_materials = $wpdb->prefix . 'sffc_crm_prep_materials';
        $sql_prep_materials = "CREATE TABLE $table_prep_materials (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            company_id bigint(20) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_url varchar(500) NOT NULL,
            file_type varchar(50),
            file_size bigint(20),
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_company (company_id)
        ) $charset_collate;";

        // ============================================
        // PREP MATERIAL REQUESTS TABLE
        // User requests for company prep materials
        // ============================================
        $table_prep_requests = $wpdb->prefix . 'sffc_crm_prep_requests';
        $sql_prep_requests = "CREATE TABLE $table_prep_requests (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            company_id bigint(20) NOT NULL,
            status enum('pending','approved','rejected') DEFAULT 'pending',
            approved_by bigint(20),
            materials_sent tinyint(1) DEFAULT 0,
            notes text,
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            responded_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_company (user_id, company_id),
            KEY idx_user (user_id),
            KEY idx_company (company_id),
            KEY idx_status (status)
        ) $charset_collate;";

        $sql_prep_library = $this->get_prep_library_table_sql($charset_collate);
        $sql_mailbox_pins = $this->get_mailbox_pins_table_sql($charset_collate);
        $sql_resource_library = $this->get_resource_library_table_sql($charset_collate);
        $sql_expert_qa = $this->get_expert_qa_table_sql($charset_collate);
        $sql_dashboard_insights = $this->get_dashboard_insights_table_sql($charset_collate);
        $sql_recommended_job_interactions = $this->get_recommended_job_interactions_table_sql($charset_collate);
        $sql_mentorship_sessions = $this->get_mentorship_sessions_table_sql($charset_collate);
        $sql_newsletters = $this->get_newsletters_table_sql($charset_collate);
        $sql_newsletter_subscriptions = $this->get_newsletter_subscriptions_table_sql($charset_collate);

        // ============================================
        // PREP MATERIAL LIBRARY TABLE
        // Global collection of reusable prep materials
        // ============================================
        $sql_prep_library = $this->get_prep_library_table_sql($charset_collate);

        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        @dbDelta($sql_recruiters);
        @dbDelta($sql_posts);
        @dbDelta($sql_job_drafts);
        @dbDelta($sql_saved_recruiters);
        @dbDelta($sql_saved_posts);
        @dbDelta($sql_member_signup_events);
        @dbDelta($sql_application_tasks);
        @dbDelta($sql_pipeline);
        @dbDelta($sql_pipeline_history);
        @dbDelta($sql_applicants);
        @dbDelta($sql_outreach);
        @dbDelta($sql_templates);
        @dbDelta($sql_activity);
        @dbDelta($sql_notes);
        @dbDelta($sql_usage);
        @dbDelta($sql_tags);
        @dbDelta($sql_recruiter_tags);
        @dbDelta($sql_tasks);
        @dbDelta($sql_sequences);
        @dbDelta($sql_sequence_steps);
        @dbDelta($sql_enrollments);
        @dbDelta($sql_enrollment_history);
        @dbDelta($sql_conversations);
        @dbDelta($sql_messages);
        @dbDelta($sql_email_accounts);
        @dbDelta($sql_alerts);
        @dbDelta($sql_notifications);
        @dbDelta($sql_internship_alert_queue);
        @dbDelta($sql_free_alert_digest_queue);
        @dbDelta($sql_sendgrid_events);
        @dbDelta($sql_alert_default_profiles);
        @dbDelta($sql_alert_profile_users);
        @dbDelta($sql_expert_outreach);
        @dbDelta($sql_auto_outreach);
        @dbDelta($sql_auto_apply_contacts);
        @dbDelta($sql_outreach_lists);
        @dbDelta($sql_outreach_list_members);
        @dbDelta($sql_job_outreach_lists);
        @dbDelta($sql_job_outreach_list_members);
        @dbDelta($sql_hr_outreach);
        @dbDelta($sql_post_groups);
        @dbDelta($sql_post_group_relationships);
        @dbDelta($sql_hr_contact_groups);
        @dbDelta($sql_hr_contact_group_relationships);
        @dbDelta($sql_user_criteria_groups);
        @dbDelta($sql_saved_lists);
        @dbDelta($sql_company_prep);
        @dbDelta($sql_prep_materials);
        @dbDelta($sql_prep_requests);
        @dbDelta($sql_prep_library);
        @dbDelta($sql_resource_library);
        @dbDelta($sql_expert_qa);
        @dbDelta($sql_dashboard_insights);
        @dbDelta($sql_recommended_job_interactions);
        @dbDelta($sql_mentorship_sessions);
        @dbDelta($sql_newsletters);
        @dbDelta($sql_newsletter_subscriptions);
        @dbDelta($sql_mailbox_pins);
        $this->migrate_member_signup_events_option_to_table();
        delete_transient('sffc_crm_dash_insights_table_ready');

        // Restore error handling
        $wpdb->suppress_errors($suppress);
        $wpdb->show_errors($show_errors);

        // ============================================
        // Add columns for upgrades (AFTER tables exist)
        // Use safe method compatible with MySQL 5.7 and MariaDB
        // ============================================
        $this->add_column_if_not_exists($table_post_groups, 'location', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_post_groups, 'is_premium', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_contact_groups, 'location', 'varchar(200) DEFAULT NULL');

        $table_posts = $wpdb->prefix . 'sffc_crm_posts';
        $this->add_column_if_not_exists($table_posts, 'application_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'wp_post_id', 'bigint(20) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'jobs_post_id', 'bigint(20) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'source_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'recruiter_display_name', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'recruiter_display_company', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'knockout_questions', 'longtext NULL');
        $this->add_column_if_not_exists($table_posts, 'is_early_bird', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_posts, 'exclude_from_early_bird', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_posts, 'response_label', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'response_badge', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'jobseeker_notes', 'text DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'company_logo', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'publish_to_jobs', 'tinyint(1) DEFAULT 1');
        $this->add_column_if_not_exists($table_posts, 'source_platform', 'varchar(80) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'source_platform_custom', 'varchar(120) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'materials', 'longtext DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'interview_questions', 'longtext DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'interview_questions_docx', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'cv_template_docx', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'cover_letter_html', 'longtext DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'cover_letter_docx', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'case_study_pdf', 'varchar(500) DEFAULT NULL');
        $table_internship_alert_queue = $wpdb->prefix . 'sffc_crm_internship_alert_queue';
        $this->add_column_if_not_exists($table_internship_alert_queue, 'delivery_transport', 'varchar(50) DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'delivery_status', 'varchar(50) DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'scheduled_for', 'datetime DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'submitted_at', 'datetime DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'provider_reference', 'varchar(255) DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'last_event_type', 'varchar(50) DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'last_event_at', 'datetime DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'last_event_reason', 'text');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'processed_at', 'datetime DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'delivered_at', 'datetime DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'deferred_at', 'datetime DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'opened_at', 'datetime DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'clicked_at', 'datetime DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'bounced_at', 'datetime DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'dropped_at', 'datetime DEFAULT NULL');
        $this->add_column_if_not_exists($table_internship_alert_queue, 'spamreport_at', 'datetime DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'keywords', 'text NULL');
        $this->add_column_if_not_exists($table_posts, 'keywords_manual', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_posts, 'application_process', 'longtext NULL');
        $this->add_column_if_not_exists($table_posts, 'team_contacts', 'longtext NULL');
        $this->add_column_if_not_exists($table_posts, 'post_status', "enum('open','closed') DEFAULT 'open'");
        $this->add_column_if_not_exists($table_posts, 'opening_date', 'date DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'closing_date', 'date DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'starting_date', 'date DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'duration', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'experience_years', 'varchar(50) DEFAULT NULL');

        $table_user_criteria_groups = $wpdb->prefix . 'sffc_crm_user_criteria_groups';
        $this->add_column_if_not_exists($table_user_criteria_groups, 'years_experience', 'varchar(50) DEFAULT NULL');

        $table_recruiters = $wpdb->prefix . 'sffc_crm_recruiters';
        $this->add_column_if_not_exists($table_recruiters, 'default_company', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_recruiters, 'default_company_logo', 'varchar(500) DEFAULT NULL');

        $table_pipeline = $wpdb->prefix . 'sffc_crm_pipeline';
        $this->add_column_if_not_exists($table_pipeline, 'source', "enum('platform','linkedin','indeed','referral','company_website','other') DEFAULT 'platform'");
        $this->add_column_if_not_exists($table_pipeline, 'external_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_pipeline, 'contact_name', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_pipeline, 'contact_email', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_pipeline, 'contact_linkedin', 'varchar(500) DEFAULT NULL');

        // Add list_id column to outreach table
        $table_outreach = $wpdb->prefix . 'sffc_crm_outreach';
        $this->add_column_if_not_exists($table_outreach, 'list_id', 'bigint(20) DEFAULT NULL');

        // Add link_url to company prep table
        $table_company_prep = $wpdb->prefix . 'sffc_crm_company_prep';
        $this->add_column_if_not_exists($table_company_prep, 'link_url', 'varchar(500) DEFAULT NULL');

        $table_resource_library = $wpdb->prefix . 'sffc_crm_resource_library';
        $this->add_column_if_not_exists($table_resource_library, 'is_case_study', 'tinyint(1) DEFAULT 0');

        // Add industry, process, and hiring columns to HR outreach table
        $table_hr_outreach = $wpdb->prefix . 'sffc_crm_hr_outreach';
        $this->add_column_if_not_exists($table_hr_outreach, 'industry', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'process', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_interns', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_graduates', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_analysts', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_associates', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_seniors', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_private_equity_candidates', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_expats', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_cfa_holders', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_oxbridge', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_russell_group', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_non_target', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_mba', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_visa_sponsorship', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_arabic_speakers', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_bilingual', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_trainee', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_placement', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'skills', 'varchar(1000) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'last_hire_proof', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'interview_questions_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'cv_template_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'cover_letter_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'company_intel_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'source_context', "varchar(50) DEFAULT 'curated'");

        // Add prep material columns to posts table
        $table_posts = $wpdb->prefix . 'sffc_crm_posts';
        $this->add_column_if_not_exists($table_posts, 'interview_questions', 'longtext DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'interview_questions_docx', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'cv_template_docx', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'cover_letter_html', 'longtext DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'cover_letter_docx', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'case_study_pdf', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'materials', 'longtext DEFAULT NULL');

        // Make recruiter_id nullable (for existing installations with manual leads)
        $wpdb->query("ALTER TABLE $table_pipeline MODIFY recruiter_id bigint(20) DEFAULT NULL");

        // Store database version
        update_option('sffc_crm_db_version', $this->db_version);

        // Initialize default templates
        $this->initialize_default_templates();
        $this->seed_default_newsletters();
    }

    private function get_member_signup_events_table_sql($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_crm_member_signup_events';

        return "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL DEFAULT 'external_apply',
            variant varchar(50) DEFAULT 'job',
            route varchar(100) DEFAULT 'external_apply',
            job_id bigint(20) DEFAULT 0,
            job_title varchar(300) DEFAULT NULL,
            apply_url varchar(500) DEFAULT NULL,
            email varchar(200) DEFAULT NULL,
            user_id bigint(20) DEFAULT 0,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_variant (variant),
            KEY idx_job_id (job_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
    }

    private function get_auto_apply_contacts_table_sql($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_crm_auto_apply_contacts';

        return "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_token varchar(191) DEFAULT NULL,
            conversation_id bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT 0,
            wp_post_id bigint(20) DEFAULT 0,
            crm_post_id bigint(20) DEFAULT 0,
            role_title varchar(255) DEFAULT NULL,
            company_name varchar(255) DEFAULT NULL,
            preferred_email varchar(200) DEFAULT NULL,
            full_name varchar(200) DEFAULT NULL,
            support_mode varchar(40) DEFAULT NULL,
            applications_per_week varchar(20) DEFAULT NULL,
            intros_per_week varchar(20) DEFAULT NULL,
            lane_preference varchar(40) DEFAULT NULL,
            priority_preference varchar(40) DEFAULT NULL,
            constraints_text text,
            highlight_text text,
            route_summary text,
            membership_account_type varchar(40) DEFAULT NULL,
            membership_url varchar(500) DEFAULT NULL,
            status varchar(40) DEFAULT 'signup_clicked',
            source varchar(80) DEFAULT 'apply_chat_auto_apply',
            payload longtext,
            admin_notified_at datetime DEFAULT NULL,
            signup_link_clicked_at datetime DEFAULT NULL,
            signup_completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_session_post (session_token, wp_post_id),
            KEY idx_status (status),
            KEY idx_user (user_id),
            KEY idx_conversation (conversation_id),
            KEY idx_created (created_at),
            KEY idx_email (preferred_email)
        ) $charset_collate;";
    }

    private function get_application_tasks_table_sql($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_crm_application_tasks';

        return "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            task_uuid varchar(64) NOT NULL,
            status varchar(40) DEFAULT 'queued',
            provider varchar(80) DEFAULT NULL,
            session_token varchar(191) DEFAULT NULL,
            conversation_id bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT 0,
            wp_post_id bigint(20) DEFAULT 0,
            crm_post_id bigint(20) DEFAULT 0,
            role_title varchar(255) DEFAULT NULL,
            company_name varchar(255) DEFAULT NULL,
            application_url varchar(1000) DEFAULT NULL,
            application_workspace_url varchar(1000) DEFAULT NULL,
            candidate_name varchar(200) DEFAULT NULL,
            candidate_email varchar(200) DEFAULT NULL,
            candidate_phone varchar(80) DEFAULT NULL,
            cv_file_name varchar(255) DEFAULT NULL,
            cv_file_url varchar(1000) DEFAULT NULL,
            cv_text longtext,
            cover_letter_requested tinyint(1) DEFAULT 0,
            worker_id varchar(120) DEFAULT NULL,
            locked_at datetime DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            last_error text,
            evidence_url varchar(1000) DEFAULT NULL,
            screenshot_url varchar(1000) DEFAULT NULL,
            payload longtext,
            result_payload longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_task_uuid (task_uuid),
            KEY idx_status (status),
            KEY idx_provider (provider),
            KEY idx_user (user_id),
            KEY idx_conversation (conversation_id),
            KEY idx_post (wp_post_id),
            KEY idx_locked (locked_at),
            KEY idx_created (created_at),
            KEY idx_email (candidate_email)
        ) $charset_collate;";
    }

    private function migrate_member_signup_events_option_to_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_crm_member_signup_events';
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        if ($table_exists !== $table_name) {
            return;
        }

        $option_key = 'sffc_member_signup_external_apply_events';
        $events = get_option($option_key, []);
        if (!is_array($events) || empty($events)) {
            return;
        }

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $created_at = isset($event['timestamp']) ? sanitize_text_field((string) $event['timestamp']) : current_time('mysql');
            $wpdb->insert(
                $table_name,
                [
                    'event_type' => sanitize_key((string) ($event['route'] ?? 'external_apply')) ?: 'external_apply',
                    'variant' => sanitize_key((string) ($event['variant'] ?? 'job')) ?: 'job',
                    'route' => sanitize_key((string) ($event['route'] ?? 'external_apply')) ?: 'external_apply',
                    'job_id' => absint($event['job_id'] ?? 0),
                    'job_title' => sanitize_text_field((string) ($event['job_title'] ?? '')),
                    'apply_url' => esc_url_raw((string) ($event['apply_url'] ?? '')),
                    'email' => sanitize_email((string) ($event['email'] ?? '')),
                    'user_id' => absint($event['user_id'] ?? 0),
                    'metadata' => wp_json_encode(['migrated_from_option' => true]),
                    'created_at' => $created_at,
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                ]
            );
        }

        delete_option($option_key);
    }

    /**
     * Initialize default message templates
     */
    private function initialize_default_templates() {
        global $wpdb;

        $table_templates = $wpdb->prefix . 'sffc_crm_templates';

        $default_templates = [
            [
                'name' => 'Standard Initial Outreach',
                'description' => 'Professional first contact for job opportunities',
                'channel' => 'email',
                'template_type' => 'initial',
                'subject' => '{role_title} opportunity',
                'content' => "Hi {recruiter_first_name},

I saw your post about the {role_title} role at {company} and wanted to reach out directly.

With my background in {candidate_experience}, I believe I could be a strong fit for what you're looking for.

Would you be open to a brief conversation?

Best regards,
{candidate_name}",
                'variables_used' => json_encode(['recruiter_first_name', 'role_title', 'company', 'candidate_experience', 'candidate_name']),
                'is_system' => 1
            ],
            [
                'name' => 'LinkedIn Connection Request',
                'description' => 'Short message for LinkedIn connection requests',
                'channel' => 'linkedin',
                'template_type' => 'connection',
                'subject' => '',
                'content' => "Hi {recruiter_first_name}, I noticed your post about the {role_title} role. I'd love to connect and discuss how my {candidate_experience} background might be relevant.",
                'variables_used' => json_encode(['recruiter_first_name', 'role_title', 'candidate_experience']),
                'is_system' => 1
            ],
            [
                'name' => 'Follow-up (No Response)',
                'description' => 'Polite follow-up after no initial response',
                'channel' => 'email',
                'template_type' => 'followup',
                'subject' => 'Re: {role_title} opportunity',
                'content' => "Hi {recruiter_first_name},

I wanted to follow up on my previous message regarding the {role_title} position.

I remain very interested in this opportunity and would welcome the chance to discuss how I could contribute.

Is there a convenient time for a brief call?

Best regards,
{candidate_name}",
                'variables_used' => json_encode(['recruiter_first_name', 'role_title', 'candidate_name']),
                'is_system' => 1
            ],
            [
                'name' => 'Thank You After Interview',
                'description' => 'Post-interview thank you note',
                'channel' => 'email',
                'template_type' => 'thank_you',
                'subject' => 'Thank you - {role_title} interview',
                'content' => "Dear {recruiter_first_name},

Thank you for taking the time to speak with me about the {role_title} position at {company}.

I enjoyed learning more about the role and the team. Our conversation reinforced my enthusiasm for this opportunity.

Please don't hesitate to reach out if you need any additional information.

Best regards,
{candidate_name}",
                'variables_used' => json_encode(['recruiter_first_name', 'role_title', 'company', 'candidate_name']),
                'is_system' => 1
            ]
        ];

        foreach ($default_templates as $template) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table_templates
                (name, description, channel, template_type, subject, content, variables_used, is_system, is_active)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %d, 1)",
                $template['name'],
                $template['description'],
                $template['channel'],
                $template['template_type'],
                $template['subject'],
                $template['content'],
                $template['variables_used'],
                $template['is_system']
            ));
        }
    }

    /**
     * Get table names
     */
    public function get_table_names() {
        global $wpdb;

        return [
            'recruiters' => $wpdb->prefix . 'sffc_crm_recruiters',
            'posts' => $wpdb->prefix . 'sffc_crm_posts',
            'job_drafts' => $wpdb->prefix . 'sffc_crm_job_drafts',
            'member_signup_events' => $wpdb->prefix . 'sffc_crm_member_signup_events',
            'saved_recruiters' => $wpdb->prefix . 'sffc_crm_saved_recruiters',
            'saved_posts' => $wpdb->prefix . 'sffc_crm_saved_posts',
            'mailbox_pins' => $wpdb->prefix . 'sffc_crm_mailbox_pins',
            'pipeline' => $wpdb->prefix . 'sffc_crm_pipeline',
            'pipeline_history' => $wpdb->prefix . 'sffc_crm_pipeline_history',
            'applicants' => $wpdb->prefix . 'sffc_crm_applicants',
            'outreach' => $wpdb->prefix . 'sffc_crm_outreach',
            'templates' => $wpdb->prefix . 'sffc_crm_templates',
            'activity' => $wpdb->prefix . 'sffc_crm_activity',
            'notes' => $wpdb->prefix . 'sffc_crm_notes',
            'usage' => $wpdb->prefix . 'sffc_crm_usage',
            'tags' => $wpdb->prefix . 'sffc_crm_tags',
            'recruiter_tags' => $wpdb->prefix . 'sffc_crm_recruiter_tags',
            'hr_outreach' => $wpdb->prefix . 'sffc_crm_hr_outreach',
            'tasks' => $wpdb->prefix . 'sffc_crm_tasks',
            'sequences' => $wpdb->prefix . 'sffc_crm_sequences',
            'sequence_steps' => $wpdb->prefix . 'sffc_crm_sequence_steps',
            'enrollments' => $wpdb->prefix . 'sffc_crm_sequence_enrollments',
            'enrollment_history' => $wpdb->prefix . 'sffc_crm_enrollment_history',
            'conversations' => $wpdb->prefix . 'sffc_crm_conversations',
            'messages' => $wpdb->prefix . 'sffc_crm_messages',
            'email_accounts' => $wpdb->prefix . 'sffc_crm_email_accounts',
            'internship_alert_queue' => $wpdb->prefix . 'sffc_crm_internship_alert_queue',
            'free_alert_digest_queue' => $wpdb->prefix . 'sffc_crm_free_alert_digest_queue',
            'sendgrid_events' => $wpdb->prefix . 'sffc_crm_sendgrid_events',
            'alert_default_profiles' => $wpdb->prefix . 'sffc_crm_alert_default_profiles',
            'alert_profile_users' => $wpdb->prefix . 'sffc_crm_alert_profile_users',
            'expert_outreach' => $wpdb->prefix . 'sffc_crm_expert_outreach',
            'auto_outreach_settings' => $wpdb->prefix . 'sffc_crm_auto_outreach_settings',
            'auto_apply_contacts' => $wpdb->prefix . 'sffc_crm_auto_apply_contacts',
            'application_tasks' => $wpdb->prefix . 'sffc_crm_application_tasks',
            'outreach_lists' => $wpdb->prefix . 'sffc_crm_outreach_lists',
            'outreach_list_members' => $wpdb->prefix . 'sffc_crm_outreach_list_members',
            'user_criteria_groups' => $wpdb->prefix . 'sffc_crm_user_criteria_groups',
            'dashboard_insights' => $wpdb->prefix . 'sffc_crm_dashboard_insights',
            'recommended_job_interactions' => $wpdb->prefix . 'sffc_crm_recommended_job_interactions',
            'newsletters' => $wpdb->prefix . 'sffc_crm_newsletters',
            'newsletter_subscriptions' => $wpdb->prefix . 'sffc_crm_newsletter_subscriptions',
        ];
    }

    /**
     * Drop all CRM tables
     */
    public function drop_tables() {
        global $wpdb;

        $tables = $this->get_table_names();

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option('sffc_crm_db_version');
    }

    /**
     * Check if tables need update
     */
    public function check_db_version() {
        $installed_version = get_option('sffc_crm_db_version', '0');

        if (version_compare($installed_version, $this->db_version, '<')) {
            $this->create_tables();
        }
    }

    /**
     * Safely add a column if it doesn't exist
     * Compatible with MySQL 5.7, MariaDB, and MySQL 8.0+
     *
     * @param string $table Table name
     * @param string $column Column name
     * @param string $definition Column definition (e.g., 'varchar(500) DEFAULT NULL')
     * @return bool
     */
    private function add_column_if_not_exists($table, $column, $definition) {
        global $wpdb;

        // Check if column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table,
            $column
        ));

        if (empty($column_exists)) {
            $result = $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            if ($result !== false) {
                error_log("SFFC CRM: Added column {$column} to {$table}");
                return true;
            } else {
                error_log("SFFC CRM: Failed to add column {$column} to {$table}: " . $wpdb->last_error);
                return false;
            }
        }

        return true; // Column already exists
    }

    private function get_prep_library_table_sql($charset_collate) {
        global $wpdb;
        $table_prep_library = $wpdb->prefix . 'sffc_crm_prep_library';

        return "CREATE TABLE $table_prep_library (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(200) NOT NULL,
            description text,
            resource_url varchar(500),
            attachment_id bigint(20) DEFAULT NULL,
            material_type varchar(50),
            icon_slug varchar(50),
            display_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active (is_active),
            KEY idx_display (display_order)
        ) $charset_collate;";
    }

    private function get_resource_library_table_sql($charset_collate) {
        global $wpdb;
        $table_resource_library = $wpdb->prefix . 'sffc_crm_resource_library';

        return "CREATE TABLE $table_resource_library (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(200) NOT NULL,
            description text,
            resource_type varchar(50) DEFAULT 'link',
            category varchar(100),
            resource_url varchar(500),
            attachment_id bigint(20) DEFAULT NULL,
            thumbnail_url varchar(500),
            access_level varchar(50) DEFAULT 'free',
            display_order int(11) DEFAULT 0,
            is_featured tinyint(1) DEFAULT 0,
            is_case_study tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active (is_active),
            KEY idx_featured (is_featured),
            KEY idx_type (resource_type),
            KEY idx_category (category),
            KEY idx_display (display_order)
        ) $charset_collate;";
    }

    private function get_newsletters_table_sql($charset_collate) {
        global $wpdb;
        $table_newsletters = $wpdb->prefix . 'sffc_crm_newsletters';

        return "CREATE TABLE $table_newsletters (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            slug varchar(120) NOT NULL,
            title varchar(200) NOT NULL,
            description text,
            image_url varchar(500),
            frequency varchar(80) DEFAULT 'Weekly',
            badge_label varchar(80) DEFAULT NULL,
            display_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slug (slug),
            KEY idx_active (is_active),
            KEY idx_display (display_order)
        ) $charset_collate;";
    }

    private function get_newsletter_subscriptions_table_sql($charset_collate) {
        global $wpdb;
        $table_subscriptions = $wpdb->prefix . 'sffc_crm_newsletter_subscriptions';

        return "CREATE TABLE $table_subscriptions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            newsletter_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            user_email varchar(200) DEFAULT NULL,
            status varchar(40) DEFAULT 'subscribed',
            subscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
            unsubscribed_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_newsletter_user (newsletter_id, user_id),
            KEY idx_newsletter (newsletter_id),
            KEY idx_user (user_id),
            KEY idx_status (status),
            KEY idx_email (user_email)
        ) $charset_collate;";
    }

    private function get_default_newsletters() {
        return [
            [
                'slug' => 'private-equity-analyst-roles',
                'title' => 'Private Equity Analyst Roles',
                'description' => 'Weekly private equity analyst and associate openings with recruiter context, application timing, and role notes.',
                'image_url' => 'https://media.joinsenna.com/2026/01/officeBuilding-scaled.jpg?1767311909',
                'frequency' => 'Weekly',
                'badge_label' => 'PE Analyst',
                'display_order' => 10,
            ],
            [
                'slug' => 'private-equity-vc-careers',
                'title' => 'Private Equity & Growth Careers',
                'description' => 'Private equity, venture capital, and growth investing roles with practical interview and market context.',
                'image_url' => 'https://joinsenna.com/wp-content/uploads/2023/08/vecteezy_this-panoramic-view-of-the-city-square-mile-financial_15540391_718-scaled-1.jpg',
                'frequency' => 'Weekly',
                'badge_label' => 'PE / Growth',
                'display_order' => 20,
            ],
            [
                'slug' => 'private-equity-interview-prep',
                'title' => 'Private Equity Interview Prep',
                'description' => 'Technical prep, LBO practice, case study notes, and deadline reminders for private equity candidates.',
                'image_url' => 'https://joinsenna.com/wp-content/uploads/2023/11/vecteezy_green-and-yellow-textured-land-from-above_1380388-scaled.jpeg',
                'frequency' => 'Weekly',
                'badge_label' => 'PE Prep',
                'display_order' => 30,
            ],
            [
                'slug' => 'private-equity-recruiter-contacts',
                'title' => 'Private Equity Recruiter Contacts',
                'description' => 'A private equity recruiter and hiring-contact digest covering funds, portfolio companies, and search firms.',
                'image_url' => 'https://joinsenna.com/wp-content/uploads/2023/09/vecteezy_low-angle-view-of-a-modern-white-and-orange-building-under_28139720_755-scaled-1.jpg',
                'frequency' => 'Weekly',
                'badge_label' => 'Recruiters',
                'display_order' => 40,
            ],
        ];
    }

    public function seed_default_newsletters() {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_newsletters';
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        if (!$table_exists) {
            return;
        }

        foreach ($this->get_default_newsletters() as $newsletter) {
            $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
                $newsletter['slug']
            ));

            if ($existing_id > 0) {
                continue;
            }

            $wpdb->insert(
                $table,
                [
                    'slug' => $newsletter['slug'],
                    'title' => $newsletter['title'],
                    'description' => $newsletter['description'],
                    'image_url' => $newsletter['image_url'],
                    'frequency' => $newsletter['frequency'],
                    'badge_label' => $newsletter['badge_label'],
                    'display_order' => (int) $newsletter['display_order'],
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
            );
        }
    }

    private function get_mentorship_sessions_table_sql($charset_collate) {
        global $wpdb;
        $table_mentorship_sessions = $wpdb->prefix . 'sffc_crm_mentorship_sessions';

        return "CREATE TABLE $table_mentorship_sessions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            request_type varchar(80) NOT NULL,
            request_label varchar(200) DEFAULT NULL,
            target_role varchar(200) DEFAULT NULL,
            target_location varchar(200) DEFAULT NULL,
            linkedin_url varchar(500) DEFAULT NULL,
            timeline varchar(200) DEFAULT NULL,
            request_details longtext,
            cv_attachment_name varchar(255) DEFAULT NULL,
            cv_attachment_path varchar(500) DEFAULT NULL,
            status varchar(40) DEFAULT 'requested',
            member_plan_limit int(11) DEFAULT NULL,
            session_notes longtext,
            internal_notes longtext,
            scheduled_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_status (status),
            KEY idx_requested (requested_at),
            KEY idx_scheduled (scheduled_at)
        ) $charset_collate;";
    }

    private function get_expert_qa_table_sql($charset_collate) {
        global $wpdb;
        $table_expert_qa = $wpdb->prefix . 'sffc_crm_expert_qa';

        return "CREATE TABLE $table_expert_qa (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            user_name varchar(200),
            user_email varchar(200),
            question text NOT NULL,
            answer longtext,
            status enum('pending','answered') DEFAULT 'pending',
            answered_by bigint(20) DEFAULT NULL,
            answered_by_name varchar(200),
            answered_by_title varchar(200),
            answered_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_user (user_id)
        ) $charset_collate;";
    }

    /**
     * Create or update internship alert queue table
     */
    public function create_internship_alert_queue_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'sffc_crm_internship_alert_queue';

        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            alert_type varchar(50) NOT NULL DEFAULT 'internship',
            status varchar(20) NOT NULL DEFAULT 'pending',
            delivery_transport varchar(50) DEFAULT NULL,
            delivery_status varchar(50) DEFAULT NULL,
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 5,
            next_attempt_at datetime DEFAULT CURRENT_TIMESTAMP,
            locked_at datetime DEFAULT NULL,
            scheduled_for datetime DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            provider_reference varchar(255) DEFAULT NULL,
            last_event_type varchar(50) DEFAULT NULL,
            last_event_at datetime DEFAULT NULL,
            last_event_reason text,
            processed_at datetime DEFAULT NULL,
            delivered_at datetime DEFAULT NULL,
            deferred_at datetime DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            bounced_at datetime DEFAULT NULL,
            dropped_at datetime DEFAULT NULL,
            spamreport_at datetime DEFAULT NULL,
            last_error text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_post_user_alert (post_id, user_id, alert_type),
            KEY idx_status_attempt (status, next_attempt_at),
            KEY idx_delivery_status (delivery_status),
            KEY idx_post (post_id),
            KEY idx_user (user_id),
            KEY idx_locked (locked_at),
            KEY idx_created (created_at)
        ) $charset_collate;";

        @dbDelta($sql);
    }

    /**
     * Create or update free alert digest queue table.
     */
    public function create_free_alert_digest_queue_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'sffc_crm_free_alert_digest_queue';

        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            cycle_key varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            match_count int(11) DEFAULT 0,
            selected_post_id bigint(20) DEFAULT 0,
            next_attempt_at datetime DEFAULT CURRENT_TIMESTAMP,
            locked_at datetime DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            provider_reference varchar(255) DEFAULT NULL,
            last_error text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_cycle (user_id, cycle_key),
            KEY idx_status_attempt (status, next_attempt_at),
            KEY idx_user (user_id),
            KEY idx_cycle (cycle_key),
            KEY idx_selected_post (selected_post_id),
            KEY idx_locked (locked_at),
            KEY idx_created (created_at)
        ) $charset_collate;";

        @dbDelta($sql);
    }

    /**
     * Create or update alert default profile tables.
     */
    public function create_alert_default_profile_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $profiles_table = $wpdb->prefix . 'sffc_crm_alert_default_profiles';
        $assignments_table = $wpdb->prefix . 'sffc_crm_alert_profile_users';

        $sql_profiles = "CREATE TABLE $profiles_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            description text,
            enabled_by_default tinyint(1) DEFAULT 1,
            sectors longtext,
            types longtext,
            locations longtext,
            work_modes longtext,
            group_ids longtext,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active (is_active),
            KEY idx_name (name)
        ) $charset_collate;";

        $sql_assignments = "CREATE TABLE $assignments_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            assigned_by bigint(20) DEFAULT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user (user_id),
            KEY idx_profile (profile_id),
            KEY idx_assigned_at (assigned_at)
        ) $charset_collate;";

        @dbDelta($sql_profiles);
        @dbDelta($sql_assignments);
    }

    /**
     * Create Company Prep tables without touching other schema pieces
     */
    public function create_company_prep_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $table_company_prep = $wpdb->prefix . 'sffc_crm_company_prep';
        $sql_company_prep = "CREATE TABLE $table_company_prep (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            company_name varchar(200) NOT NULL,
            company_website varchar(500),
            location varchar(200),
            regions_covered text,
            logo_url varchar(500),
            banner_url varchar(500),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active (is_active),
            KEY idx_company_name (company_name)
        ) $charset_collate;";

        $table_prep_materials = $wpdb->prefix . 'sffc_crm_prep_materials';
        $sql_prep_materials = "CREATE TABLE $table_prep_materials (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            company_id bigint(20) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_url varchar(500) NOT NULL,
            file_type varchar(50),
            file_size bigint(20),
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_company (company_id)
        ) $charset_collate;";

        $table_prep_requests = $wpdb->prefix . 'sffc_crm_prep_requests';
        $sql_prep_requests = "CREATE TABLE $table_prep_requests (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            company_id bigint(20) NOT NULL,
            status enum('pending','approved','rejected') DEFAULT 'pending',
            approved_by bigint(20),
            materials_sent tinyint(1) DEFAULT 0,
            notes text,
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            responded_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_company (user_id, company_id),
            KEY idx_user (user_id),
            KEY idx_company (company_id),
            KEY idx_status (status)
        ) $charset_collate;";

        @dbDelta($sql_company_prep);
        @dbDelta($sql_prep_materials);
        @dbDelta($sql_prep_requests);
        @dbDelta($this->get_prep_library_table_sql($charset_collate));
        @dbDelta($this->get_resource_library_table_sql($charset_collate));
        @dbDelta($this->get_expert_qa_table_sql($charset_collate));
    }

    /**
     * Create or update the prep materials library table
     */
    public function create_prep_library_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        @dbDelta($this->get_prep_library_table_sql($charset_collate));
    }

    /**
     * Create or update the resource library table
     */
    public function create_resource_library_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        @dbDelta($this->get_resource_library_table_sql($charset_collate));

        $table_resource_library = $wpdb->prefix . 'sffc_crm_resource_library';
        $this->add_column_if_not_exists($table_resource_library, 'is_case_study', 'tinyint(1) DEFAULT 0');
    }

    /**
     * Create or update expert Q&A table
     */
    public function create_expert_qa_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        @dbDelta($this->get_expert_qa_table_sql($charset_collate));
    }

    /**
     * Create or update dashboard insights cache table
     */
    public function create_dashboard_insights_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        @dbDelta($this->get_dashboard_insights_table_sql($charset_collate));
        delete_transient('sffc_crm_dash_insights_table_ready');
    }

    /**
     * Create or update recommended job interactions table
     */
    public function create_recommended_job_interactions_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        @dbDelta($this->get_recommended_job_interactions_table_sql($charset_collate));
    }

    /**
     * Create or update mailbox pins table
     */
    public function create_mailbox_pins_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        @dbDelta($this->get_mailbox_pins_table_sql($charset_collate));
    }

    public function create_mentorship_sessions_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($this->get_mentorship_sessions_table_sql($wpdb->get_charset_collate()));
    }

    public function create_newsletter_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        dbDelta($this->get_newsletters_table_sql($charset_collate));
        dbDelta($this->get_newsletter_subscriptions_table_sql($charset_collate));
        $this->seed_default_newsletters();
    }

    public function create_auto_apply_contacts_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        @dbDelta($this->get_auto_apply_contacts_table_sql($charset_collate));
    }

    public function create_application_tasks_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        @dbDelta($this->get_application_tasks_table_sql($charset_collate));
    }

    /**
     * Force update database schema
     * Call this to add any missing columns
     */
    public function force_schema_update() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$wpdb->prefix}sffc_crm_job_outreach_lists (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            list_name varchar(255) NOT NULL,
            description text,
            total_items int(11) DEFAULT 0,
            generated_items int(11) DEFAULT 0,
            sent_items int(11) DEFAULT 0,
            skipped_items int(11) DEFAULT 0,
            last_generated_at datetime DEFAULT NULL,
            last_sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_created (created_at),
            KEY idx_updated (updated_at)
        ) " . $wpdb->get_charset_collate() . ';');
        $this->create_mentorship_sessions_table();
        dbDelta("CREATE TABLE {$wpdb->prefix}sffc_crm_job_outreach_list_members (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            list_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            crm_post_id bigint(20) DEFAULT NULL,
            recruiter_id bigint(20) DEFAULT NULL,
            queue_index int(11) DEFAULT 0,
            recruiter_name varchar(200) DEFAULT NULL,
            recruiter_title varchar(200) DEFAULT NULL,
            recruiter_email varchar(200) DEFAULT NULL,
            recruiter_linkedin varchar(500) DEFAULT NULL,
            recruiter_firm varchar(200) DEFAULT NULL,
            role_title varchar(300) DEFAULT NULL,
            company varchar(200) DEFAULT NULL,
            location varchar(200) DEFAULT NULL,
            match_score int(11) DEFAULT 0,
            insight text,
            reasons longtext,
            outreach_status varchar(40) DEFAULT 'queued',
            target_channel varchar(50) DEFAULT 'email',
            generated_subject text,
            generated_body longtext,
            generated_payload longtext,
            generated_with_claude tinyint(1) DEFAULT 0,
            last_generated_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            skipped_at datetime DEFAULT NULL,
            added_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_list (list_id),
            KEY idx_post (post_id),
            KEY idx_recruiter (recruiter_id),
            KEY idx_status (outreach_status),
            KEY idx_order (list_id, queue_index),
            UNIQUE KEY unique_list_post (list_id, post_id)
        ) " . $wpdb->get_charset_collate() . ';');
        $table_hr_outreach = $wpdb->prefix . 'sffc_crm_hr_outreach';
        $sql_hr_outreach = "CREATE TABLE $table_hr_outreach (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            company_name varchar(200) NOT NULL,
            company_logo varchar(500),
            company_url varchar(500),
            location varchar(200),
            regions varchar(200),
            industry varchar(500),
            program_types varchar(200),
            process varchar(500),
            hire_interns tinyint(1) DEFAULT 0,
            hire_graduates tinyint(1) DEFAULT 0,
            hire_analysts tinyint(1) DEFAULT 0,
            hire_associates tinyint(1) DEFAULT 0,
            hire_seniors tinyint(1) DEFAULT 0,
            hire_private_equity_candidates tinyint(1) DEFAULT 0,
            hire_expats tinyint(1) DEFAULT 0,
            hire_cfa_holders tinyint(1) DEFAULT 0,
            hire_oxbridge tinyint(1) DEFAULT 0,
            hire_russell_group tinyint(1) DEFAULT 0,
            hire_non_target tinyint(1) DEFAULT 0,
            hire_mba tinyint(1) DEFAULT 0,
            hire_visa_sponsorship tinyint(1) DEFAULT 0,
            hire_arabic_speakers tinyint(1) DEFAULT 0,
            hire_bilingual tinyint(1) DEFAULT 0,
            hire_trainee tinyint(1) DEFAULT 0,
            hire_placement tinyint(1) DEFAULT 0,
            skills varchar(1000),
            role_focus text,
            last_hire_proof varchar(500),
            interview_questions_url varchar(500),
            cv_template_url varchar(500),
            cover_letter_url varchar(500),
            company_intel_url varchar(500),
            contact_name varchar(200) NOT NULL,
            contact_title varchar(200),
            contact_email varchar(200),
            contact_phone varchar(100),
            contact_linkedin varchar(500),
            contact_photo varchar(500),
            team_contacts longtext,
            notes text,
            source_context varchar(50) DEFAULT 'curated',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_company (company_name)
        ) " . $wpdb->get_charset_collate() . ';';
        dbDelta($sql_hr_outreach);
        $table_post_groups = $wpdb->prefix . 'sffc_crm_post_groups';
        dbDelta("CREATE TABLE $table_post_groups (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            slug varchar(200) NOT NULL,
            description text,
            location varchar(200) DEFAULT NULL,
            icon varchar(500),
            display_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            is_premium tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slug (slug),
            KEY idx_location (location),
            KEY idx_active (is_active),
            KEY idx_premium (is_premium),
            KEY idx_order (display_order)
        ) " . $wpdb->get_charset_collate() . ';');

        $table_post_group_relationships = $wpdb->prefix . 'sffc_crm_post_group_relationships';
        dbDelta("CREATE TABLE $table_post_group_relationships (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            group_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_post_group (post_id, group_id),
            KEY idx_post (post_id),
            KEY idx_group (group_id)
        ) " . $wpdb->get_charset_collate() . ';');

        $table_hr_contact_groups = $wpdb->prefix . 'sffc_crm_hr_contact_groups';
        dbDelta("CREATE TABLE $table_hr_contact_groups (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            slug varchar(200) NOT NULL,
            description text,
            location varchar(200) DEFAULT NULL,
            icon varchar(500),
            display_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slug (slug),
            KEY idx_location (location),
            KEY idx_active (is_active),
            KEY idx_order (display_order)
        ) " . $wpdb->get_charset_collate() . ';');

        $table_hr_contact_group_relationships = $wpdb->prefix . 'sffc_crm_hr_contact_group_relationships';
        dbDelta("CREATE TABLE $table_hr_contact_group_relationships (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) NOT NULL,
            group_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_contact_group (contact_id, group_id),
            KEY idx_contact (contact_id),
            KEY idx_group (group_id)
        ) " . $wpdb->get_charset_collate() . ';');

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_post_groups)) === $table_post_groups) {
            $this->add_column_if_not_exists($table_post_groups, 'location', 'varchar(200) DEFAULT NULL');
        }
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_hr_contact_groups)) === $table_hr_contact_groups) {
            $this->add_column_if_not_exists($table_hr_contact_groups, 'location', 'varchar(200) DEFAULT NULL');
        }

        $table_saved_lists = $wpdb->prefix . 'sffc_crm_saved_lists';
        dbDelta("CREATE TABLE $table_saved_lists (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            group_type varchar(50) NOT NULL DEFAULT 'jobs',
            group_id bigint(20) NOT NULL,
            group_slug varchar(200) NOT NULL,
            group_name varchar(200) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_group (user_id, group_type, group_id),
            KEY idx_user (user_id),
            KEY idx_group (group_type, group_id),
            KEY idx_created (created_at)
        ) " . $wpdb->get_charset_collate() . ';');

        // Add industry column if it doesn't exist
        $this->add_column_if_not_exists($table_hr_outreach, 'industry', 'varchar(500) DEFAULT NULL');
        // Add process column if it doesn't exist
        $this->add_column_if_not_exists($table_hr_outreach, 'process', 'varchar(500) DEFAULT NULL');
        // Add hiring columns if they don't exist
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_interns', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_graduates', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_analysts', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_associates', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_seniors', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_private_equity_candidates', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_expats', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_cfa_holders', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_oxbridge', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_russell_group', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_non_target', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_mba', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_visa_sponsorship', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_arabic_speakers', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_bilingual', 'tinyint(1) DEFAULT 0');
        // Add prep material columns if they don't exist
        $this->add_column_if_not_exists($table_hr_outreach, 'last_hire_proof', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'interview_questions_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'cv_template_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'cover_letter_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'company_intel_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_trainee', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'hire_placement', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_hr_outreach, 'skills', 'varchar(1000) DEFAULT NULL');
        $this->add_column_if_not_exists($table_hr_outreach, 'source_context', "varchar(50) DEFAULT 'curated'");

        $table_posts = $wpdb->prefix . 'sffc_crm_posts';
        $table_post_groups = $wpdb->prefix . 'sffc_crm_post_groups';

        $this->add_column_if_not_exists($table_post_groups, 'is_premium', 'tinyint(1) DEFAULT 0');

        // Add missing columns to posts table
        $this->add_column_if_not_exists($table_posts, 'application_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'wp_post_id', 'bigint(20) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'jobs_post_id', 'bigint(20) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'source_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'recruiter_display_name', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'recruiter_display_company', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'is_early_bird', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_posts, 'exclude_from_early_bird', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_posts, 'response_label', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'response_badge', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'jobseeker_notes', 'text DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'company_logo', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'publish_to_jobs', 'tinyint(1) DEFAULT 1');
        $this->add_column_if_not_exists($table_posts, 'source_platform', 'varchar(80) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'source_platform_custom', 'varchar(120) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'keywords', 'text NULL');
        $this->add_column_if_not_exists($table_posts, 'keywords_manual', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists($table_posts, 'knockout_questions', 'longtext NULL');
        $this->add_column_if_not_exists($table_posts, 'application_process', 'longtext NULL');
        $this->add_column_if_not_exists($table_posts, 'team_contacts', 'longtext NULL');
        $this->add_column_if_not_exists($table_posts, 'post_status', "enum('open','closed') DEFAULT 'open'");
        $this->add_column_if_not_exists($table_posts, 'opening_date', 'date DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'closing_date', 'date DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'starting_date', 'date DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'duration', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'interview_questions', 'longtext DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'interview_questions_docx', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'cv_template_docx', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'cover_letter_html', 'longtext DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'cover_letter_docx', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'case_study_pdf', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_posts, 'materials', 'longtext DEFAULT NULL');

        $table_recruiters = $wpdb->prefix . 'sffc_crm_recruiters';
        $this->add_column_if_not_exists($table_recruiters, 'default_company', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_recruiters, 'default_company_logo', 'varchar(500) DEFAULT NULL');

        // Add missing columns to pipeline table
        $table_pipeline = $wpdb->prefix . 'sffc_crm_pipeline';
        $this->add_column_if_not_exists($table_pipeline, 'source', "enum('platform','linkedin','indeed','referral','company_website','other') DEFAULT 'platform'");
        $this->add_column_if_not_exists($table_pipeline, 'external_url', 'varchar(500) DEFAULT NULL');
        $this->add_column_if_not_exists($table_pipeline, 'contact_name', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_pipeline, 'contact_email', 'varchar(200) DEFAULT NULL');
        $this->add_column_if_not_exists($table_pipeline, 'contact_linkedin', 'varchar(500) DEFAULT NULL');

        // Add list_id column to outreach table
        $table_outreach = $wpdb->prefix . 'sffc_crm_outreach';
        $this->add_column_if_not_exists($table_outreach, 'list_id', 'bigint(20) DEFAULT NULL AFTER pipeline_id');

        $table_resource_library = $wpdb->prefix . 'sffc_crm_resource_library';
        dbDelta($this->get_resource_library_table_sql($wpdb->get_charset_collate()));
        $this->add_column_if_not_exists($table_resource_library, 'is_case_study', 'tinyint(1) DEFAULT 0');
        dbDelta($this->get_dashboard_insights_table_sql($wpdb->get_charset_collate()));
        dbDelta($this->get_recommended_job_interactions_table_sql($wpdb->get_charset_collate()));
        dbDelta($this->get_mailbox_pins_table_sql($wpdb->get_charset_collate()));
        dbDelta($this->get_member_signup_events_table_sql($wpdb->get_charset_collate()));
        $this->create_auto_apply_contacts_table();
        $this->create_newsletter_tables();
        $this->migrate_member_signup_events_option_to_table();
        delete_transient('sffc_crm_dash_insights_table_ready');

        // Clean up duplicate templates and add unique index
        $this->fix_duplicate_templates();

        // Update version to force re-check
        update_option('sffc_crm_db_version', $this->db_version);

        return true;
    }

    public function create_user_criteria_groups_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'sffc_crm_user_criteria_groups';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(200) NOT NULL,
            slug varchar(200) NOT NULL,
            job_title varchar(500),
            sector text,
            location text,
            experience_level text,
            skills_keywords text,
            cv_file_id bigint(20),
            cover_letter_file_id bigint(20),
            is_default tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_active (is_active),
            KEY idx_default (is_default),
            KEY idx_order (display_order)
        ) $charset_collate;";

        dbDelta($sql);

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private function get_dashboard_insights_table_sql($charset_collate) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_dashboard_insights';

        return "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            day_key date NOT NULL,
            context_hash char(32) NOT NULL,
            role_label varchar(255) DEFAULT NULL,
            payload longtext NOT NULL,
            source varchar(20) NOT NULL DEFAULT 'claude',
            status varchar(20) NOT NULL DEFAULT 'generated',
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_day_context (user_id, day_key, context_hash),
            KEY idx_user_day (user_id, day_key),
            KEY idx_generated_at (generated_at),
            KEY idx_status (status)
        ) $charset_collate;";
    }

    private function get_recommended_job_interactions_table_sql($charset_collate) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_recommended_job_interactions';

        return "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            crm_post_id bigint(20) DEFAULT NULL,
            jobs_post_id bigint(20) DEFAULT NULL,
            wp_post_id bigint(20) DEFAULT NULL,
            event_type varchar(50) NOT NULL,
            event_weight decimal(6,2) NOT NULL DEFAULT 1.00,
            role_title varchar(300) DEFAULT NULL,
            sector varchar(150) DEFAULT NULL,
            seniority varchar(100) DEFAULT NULL,
            location varchar(200) DEFAULT NULL,
            keyword_snapshot longtext,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_created (user_id, created_at),
            KEY idx_user_event (user_id, event_type),
            KEY idx_crm_post (crm_post_id),
            KEY idx_jobs_post (jobs_post_id),
            KEY idx_sector (sector),
            KEY idx_seniority (seniority)
        ) $charset_collate;";
    }

    private function get_mailbox_pins_table_sql($charset_collate) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_mailbox_pins';

        return "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            mailbox_key varchar(191) NOT NULL,
            crm_post_id bigint(20) DEFAULT NULL,
            jobs_post_id bigint(20) DEFAULT NULL,
            wp_post_id bigint(20) DEFAULT NULL,
            role_title varchar(300) DEFAULT NULL,
            company varchar(200) DEFAULT NULL,
            location varchar(200) DEFAULT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_mailbox_key (user_id, mailbox_key),
            KEY idx_user_created (user_id, created_at),
            KEY idx_crm_post (crm_post_id),
            KEY idx_jobs_post (jobs_post_id),
            KEY idx_wp_post (wp_post_id)
        ) $charset_collate;";
    }

    /**
     * Convert legacy pipeline stages to the new LinkedIn-style schema
     */
    private function migrate_legacy_pipeline_stages() {
        global $wpdb;

        $table_pipeline = $wpdb->prefix . 'sffc_crm_pipeline';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_pipeline
        ));

        if (!$table_exists) {
            return;
        }

        $stage_map = [
            'reached_out' => 'messaged',
            'in_conversation' => 'follow_up',
            'interviewing' => 'video_interview',
            'offer' => 'offer_received',
            'closed' => 'rejected',
        ];

        foreach ($stage_map as $legacy => $modern) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_pipeline}
                 SET stage = %s
                 WHERE stage = %s",
                $modern,
                $legacy
            ));
        }
    }

    /**
     * Fix duplicate system templates and add unique constraint
     */
    private function fix_duplicate_templates() {
        global $wpdb;

        $table_templates = $wpdb->prefix . 'sffc_crm_templates';

        // First, remove duplicate system templates (keep lowest ID)
        $wpdb->query("
            DELETE t1 FROM $table_templates t1
            INNER JOIN $table_templates t2
            WHERE t1.is_system = 1
              AND t2.is_system = 1
              AND t1.name = t2.name
              AND t1.id > t2.id
        ");

        // Check if unique index exists
        $index_exists = $wpdb->get_results(
            "SHOW INDEX FROM $table_templates WHERE Key_name = 'unique_system_template'"
        );

        // Add unique index if it doesn't exist
        if (empty($index_exists)) {
            $wpdb->query("ALTER TABLE $table_templates ADD UNIQUE KEY unique_system_template (name, is_system)");
        }
    }
}
