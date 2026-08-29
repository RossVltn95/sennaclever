<?php
/**
 * CRM Analytics Model
 *
 * Phase 6: Intelligence & Analytics
 * Handles data aggregation, metrics calculation, and insights generation.
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Analytics {

    private $outreach_table;
    private $pipeline_table;
    private $recruiters_table;
    private $sequences_table;
    private $enrollments_table;
    private $templates_table;
    private $tasks_table;
    private $conversations_table;
    private $messages_table;
    private $activity_table;

    public function __construct() {
        global $wpdb;
        $this->outreach_table = $wpdb->prefix . 'sffc_crm_outreach';
        $this->pipeline_table = $wpdb->prefix . 'sffc_crm_pipeline';
        $this->recruiters_table = $wpdb->prefix . 'sffc_crm_recruiters';
        $this->sequences_table = $wpdb->prefix . 'sffc_crm_sequences';
        $this->enrollments_table = $wpdb->prefix . 'sffc_crm_sequence_enrollments';
        $this->templates_table = $wpdb->prefix . 'sffc_crm_templates';
        $this->tasks_table = $wpdb->prefix . 'sffc_crm_tasks';
        $this->conversations_table = $wpdb->prefix . 'sffc_crm_conversations';
        $this->messages_table = $wpdb->prefix . 'sffc_crm_messages';
        $this->activity_table = $wpdb->prefix . 'sffc_crm_activity';
    }

    // ============================================
    // OVERVIEW METRICS
    // ============================================

    /**
     * Get dashboard overview metrics
     *
     * @param int    $user_id User ID
     * @param string $period  Period: 'week', 'month', 'quarter', 'year', 'all'
     * @return array Overview metrics
     */
    public function get_overview_metrics($user_id, $period = 'month') {
        $date_from = $this->get_period_start($period);

        return [
            'outreach'           => $this->get_outreach_metrics($user_id, $date_from),
            'responses'          => $this->get_response_metrics($user_id, $date_from),
            'pipeline'           => $this->get_pipeline_metrics($user_id),
            'tasks'              => $this->get_task_metrics($user_id),
            'sequences'          => $this->get_sequence_overview($user_id),
            'recruiters'         => $this->get_recruiter_metrics($user_id),
            'period'             => $period,
            'date_from'          => $date_from,
        ];
    }

    /**
     * Get outreach volume metrics
     *
     * @param int    $user_id   User ID
     * @param string $date_from Start date
     * @return array Outreach metrics
     */
    public function get_outreach_metrics($user_id, $date_from = null) {
        global $wpdb;

        $where_date = $date_from ? $wpdb->prepare(" AND created_at >= %s", $date_from) : '';

        // Total sent
        $total_sent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->outreach_table}
            WHERE user_id = %d AND status IN ('sent', 'opened', 'clicked', 'replied')
            {$where_date}",
            $user_id
        ));

        // Previous period for comparison
        $prev_date_from = $this->get_previous_period_start($date_from);
        $prev_where = $date_from ? $wpdb->prepare(
            " AND created_at >= %s AND created_at < %s",
            $prev_date_from,
            $date_from
        ) : '';

        $prev_sent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->outreach_table}
            WHERE user_id = %d AND status IN ('sent', 'opened', 'clicked', 'replied')
            {$prev_where}",
            $user_id
        ));

        // By channel
        $by_channel = $wpdb->get_results($wpdb->prepare(
            "SELECT channel, COUNT(*) as count
            FROM {$this->outreach_table}
            WHERE user_id = %d AND status IN ('sent', 'opened', 'clicked', 'replied')
            {$where_date}
            GROUP BY channel",
            $user_id
        ), ARRAY_A);

        $channels = [];
        foreach ($by_channel as $row) {
            $channels[$row['channel']] = (int) $row['count'];
        }

        return [
            'total'       => $total_sent,
            'previous'    => $prev_sent,
            'change'      => $prev_sent > 0 ? round((($total_sent - $prev_sent) / $prev_sent) * 100, 1) : 0,
            'by_channel'  => $channels,
        ];
    }

    /**
     * Get response rate metrics
     *
     * @param int    $user_id   User ID
     * @param string $date_from Start date
     * @return array Response metrics
     */
    public function get_response_metrics($user_id, $date_from = null) {
        global $wpdb;

        $where_date = $date_from ? $wpdb->prepare(" AND created_at >= %s", $date_from) : '';

        // Total sent (excluding drafts)
        $total_sent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->outreach_table}
            WHERE user_id = %d AND status IN ('sent', 'opened', 'clicked', 'replied')
            {$where_date}",
            $user_id
        ));

        // Opened
        $opened = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->outreach_table}
            WHERE user_id = %d AND status IN ('opened', 'clicked', 'replied')
            {$where_date}",
            $user_id
        ));

        // Replied
        $replied = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->outreach_table}
            WHERE user_id = %d AND status = 'replied'
            {$where_date}",
            $user_id
        ));

        // Calculate rates
        $open_rate = $total_sent > 0 ? round(($opened / $total_sent) * 100, 1) : 0;
        $reply_rate = $total_sent > 0 ? round(($replied / $total_sent) * 100, 1) : 0;

        // Previous period comparison
        $prev_date_from = $this->get_previous_period_start($date_from);
        $prev_where = $date_from ? $wpdb->prepare(
            " AND created_at >= %s AND created_at < %s",
            $prev_date_from,
            $date_from
        ) : '';

        $prev_sent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->outreach_table}
            WHERE user_id = %d AND status IN ('sent', 'opened', 'clicked', 'replied')
            {$prev_where}",
            $user_id
        ));

        $prev_replied = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->outreach_table}
            WHERE user_id = %d AND status = 'replied'
            {$prev_where}",
            $user_id
        ));

        $prev_reply_rate = $prev_sent > 0 ? round(($prev_replied / $prev_sent) * 100, 1) : 0;

        return [
            'total_sent'      => $total_sent,
            'opened'          => $opened,
            'replied'         => $replied,
            'open_rate'       => $open_rate,
            'reply_rate'      => $reply_rate,
            'prev_reply_rate' => $prev_reply_rate,
            'rate_change'     => round($reply_rate - $prev_reply_rate, 1),
        ];
    }

    /**
     * Get pipeline metrics
     *
     * @param int $user_id User ID
     * @return array Pipeline metrics
     */
    public function get_pipeline_metrics($user_id) {
        global $wpdb;

        $stage_meta = SFFC_CRM_Pipeline::get_stages();
        $stage_keys = array_keys($stage_meta);
        $stage_order_sql = "'" . implode("','", array_map('esc_sql', $stage_keys)) . "'";

        $by_stage = $wpdb->get_results($wpdb->prepare(
            "SELECT stage, COUNT(*) as count
            FROM {$this->pipeline_table}
            WHERE user_id = %d AND (outcome IS NULL OR outcome = '')
            GROUP BY stage
            ORDER BY FIELD(stage, {$stage_order_sql})",
            $user_id
        ), ARRAY_A);

        $stages = [];
        foreach ($stage_keys as $stage_key) {
            $stages[$stage_key] = 0;
        }

        $total_active = 0;
        foreach ($by_stage as $row) {
            if (!isset($stages[$row['stage']])) {
                $stages[$row['stage']] = 0;
            }
            $stages[$row['stage']] += (int) $row['count'];
            $total_active += (int) $row['count'];
        }

        // Outcomes
        $outcomes = $wpdb->get_results($wpdb->prepare(
            "SELECT outcome, COUNT(*) as count
            FROM {$this->pipeline_table}
            WHERE user_id = %d AND outcome IS NOT NULL
            GROUP BY outcome",
            $user_id
        ), ARRAY_A);

        $outcome_counts = [
            'won'       => 0,
            'lost'      => 0,
            'withdrawn' => 0,
        ];

        foreach ($outcomes as $row) {
            $outcome_counts[$row['outcome']] = (int) $row['count'];
        }

        // Interviews scheduled (sum of assessment/interview stages)
        $interview_stages = ['online_assessment', 'case_study', 'hirevue', 'telephone_interview', 'video_interview', 'face_to_face_interview', 'assessment_centre'];
        $interviews = 0;
        foreach ($interview_stages as $stage_key) {
            if (isset($stages[$stage_key])) {
                $interviews += $stages[$stage_key];
            }
        }

        $offers = $stages['offer_received'] ?? 0;

        return [
            'total_active'  => $total_active,
            'by_stage'      => $stages,
            'outcomes'      => $outcome_counts,
            'interviews'    => $interviews,
            'offers'        => $offers,
            'win_rate'      => ($outcome_counts['won'] + $outcome_counts['lost']) > 0
                ? round(($outcome_counts['won'] / ($outcome_counts['won'] + $outcome_counts['lost'])) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get task metrics
     *
     * @param int $user_id User ID
     * @return array Task metrics
     */
    public function get_task_metrics($user_id) {
        global $wpdb;

        $today = current_time('Y-m-d');

        // Overdue
        $overdue = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tasks_table}
            WHERE user_id = %d AND status = 'pending' AND due_date < %s",
            $user_id,
            $today
        ));

        // Due today
        $due_today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tasks_table}
            WHERE user_id = %d AND status = 'pending' AND due_date = %s",
            $user_id,
            $today
        ));

        // Upcoming (next 7 days)
        $upcoming = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tasks_table}
            WHERE user_id = %d AND status = 'pending'
            AND due_date > %s AND due_date <= DATE_ADD(%s, INTERVAL 7 DAY)",
            $user_id,
            $today,
            $today
        ));

        // Completed this week
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $completed_week = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tasks_table}
            WHERE user_id = %d AND status = 'completed' AND completed_at >= %s",
            $user_id,
            $week_start
        ));

        return [
            'overdue'        => $overdue,
            'due_today'      => $due_today,
            'upcoming'       => $upcoming,
            'completed_week' => $completed_week,
        ];
    }

    /**
     * Get sequence overview
     *
     * @param int $user_id User ID
     * @return array Sequence metrics
     */
    public function get_sequence_overview($user_id) {
        global $wpdb;

        // Active sequences
        $active_sequences = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->sequences_table}
            WHERE user_id = %d AND is_active = 1",
            $user_id
        ));

        // Active enrollments
        $active_enrollments = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->enrollments_table} e
            INNER JOIN {$this->sequences_table} s ON e.sequence_id = s.id
            WHERE s.user_id = %d AND e.status = 'active'",
            $user_id
        ));

        // Completed enrollments
        $completed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->enrollments_table} e
            INNER JOIN {$this->sequences_table} s ON e.sequence_id = s.id
            WHERE s.user_id = %d AND e.status = 'completed'",
            $user_id
        ));

        // Replied (from enrollments)
        $replied = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->enrollments_table} e
            INNER JOIN {$this->sequences_table} s ON e.sequence_id = s.id
            WHERE s.user_id = %d AND e.status = 'replied'",
            $user_id
        ));

        return [
            'active_sequences'   => $active_sequences,
            'active_enrollments' => $active_enrollments,
            'completed'          => $completed,
            'replied'            => $replied,
        ];
    }

    /**
     * Get recruiter metrics
     *
     * @param int $user_id User ID
     * @return array Recruiter metrics
     */
    public function get_recruiter_metrics($user_id) {
        global $wpdb;

        $saved_table = $wpdb->prefix . 'sffc_crm_saved_recruiters';

        // Total saved recruiters
        $total_saved = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$saved_table} WHERE user_id = %d",
            $user_id
        ));

        // By status (from pipeline or outreach)
        $contacted = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT recruiter_id) FROM {$this->outreach_table}
            WHERE user_id = %d AND status IN ('sent', 'opened', 'clicked', 'replied')",
            $user_id
        ));

        $replied = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT recruiter_id) FROM {$this->outreach_table}
            WHERE user_id = %d AND status = 'replied'",
            $user_id
        ));

        return [
            'total_saved' => $total_saved,
            'contacted'   => $contacted,
            'replied'     => $replied,
            'not_contacted' => max(0, $total_saved - $contacted),
        ];
    }

    // ============================================
    // TIME SERIES DATA
    // ============================================

    /**
     * Get outreach volume over time
     *
     * @param int    $user_id     User ID
     * @param string $period      Period: 'week', 'month', 'quarter'
     * @param string $granularity Granularity: 'day', 'week', 'month'
     * @return array Time series data
     */
    public function get_outreach_over_time($user_id, $period = 'month', $granularity = 'day') {
        global $wpdb;

        $date_from = $this->get_period_start($period);

        $date_format = match($granularity) {
            'day'   => '%Y-%m-%d',
            'week'  => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(created_at, %s) as period,
                    COUNT(*) as total,
                    SUM(CASE WHEN channel = 'email' THEN 1 ELSE 0 END) as email,
                    SUM(CASE WHEN channel = 'linkedin' THEN 1 ELSE 0 END) as linkedin
            FROM {$this->outreach_table}
            WHERE user_id = %d
            AND status IN ('sent', 'opened', 'clicked', 'replied')
            AND created_at >= %s
            GROUP BY DATE_FORMAT(created_at, %s)
            ORDER BY period ASC",
            $date_format,
            $user_id,
            $date_from,
            $date_format
        ), ARRAY_A);

        return $results;
    }

    /**
     * Get response rates over time
     *
     * @param int    $user_id User ID
     * @param string $period  Period
     * @return array Time series data
     */
    public function get_response_rates_over_time($user_id, $period = 'month') {
        global $wpdb;

        $date_from = $this->get_period_start($period);

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(created_at, '%%Y-%%m-%%d') as date,
                    COUNT(*) as sent,
                    SUM(CASE WHEN status IN ('opened', 'clicked', 'replied') THEN 1 ELSE 0 END) as opened,
                    SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied
            FROM {$this->outreach_table}
            WHERE user_id = %d
            AND status IN ('sent', 'opened', 'clicked', 'replied')
            AND created_at >= %s
            GROUP BY DATE_FORMAT(created_at, '%%Y-%%m-%%d')
            ORDER BY date ASC",
            $user_id,
            $date_from
        ), ARRAY_A);

        // Calculate rates
        foreach ($results as &$row) {
            $row['sent'] = (int) $row['sent'];
            $row['opened'] = (int) $row['opened'];
            $row['replied'] = (int) $row['replied'];
            $row['open_rate'] = $row['sent'] > 0 ? round(($row['opened'] / $row['sent']) * 100, 1) : 0;
            $row['reply_rate'] = $row['sent'] > 0 ? round(($row['replied'] / $row['sent']) * 100, 1) : 0;
        }

        return $results;
    }

    // ============================================
    // PIPELINE ANALYTICS
    // ============================================

    /**
     * Get pipeline funnel data
     *
     * @param int    $user_id   User ID
     * @param string $date_from Optional date filter
     * @return array Funnel data
     */
    public function get_pipeline_funnel($user_id, $date_from = null) {
        $pipeline = new SFFC_CRM_Pipeline();
        $stats = $pipeline->get_user_stats($user_id);
        $stage_meta = SFFC_CRM_Pipeline::get_stages();

        $funnel = [];
        foreach ($stage_meta as $stage_key => $stage_data) {
            $funnel[] = [
                'stage' => $stage_key,
                'label' => $stage_data['label'],
                'count' => $stats['by_stage'][$stage_key] ?? 0,
                'conversion_rate' => 0,
            ];
        }

        for ($i = 0; $i < count($funnel); $i++) {
            if ($i === 0) {
                $funnel[$i]['conversion_rate'] = 100;
                continue;
            }

            $prev_count = $funnel[$i - 1]['count'];
            $funnel[$i]['conversion_rate'] = $prev_count > 0
                ? round(($funnel[$i]['count'] / $prev_count) * 100, 1)
                : 0;
        }

        return $funnel;
    }

    /**
     * Get average time in each pipeline stage
     *
     * @param int $user_id User ID
     * @return array Average times by stage
     */
    public function get_stage_duration_averages($user_id) {
        global $wpdb;

        $history_table = $wpdb->prefix . 'sffc_crm_pipeline_history';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT from_stage, to_stage,
                    AVG(TIMESTAMPDIFF(HOUR, h1.created_at, h2.created_at)) as avg_hours
            FROM {$history_table} h1
            INNER JOIN {$history_table} h2 ON h1.pipeline_id = h2.pipeline_id
            INNER JOIN {$this->pipeline_table} p ON h1.pipeline_id = p.id
            WHERE p.user_id = %d
            AND h2.created_at > h1.created_at
            GROUP BY from_stage, to_stage",
            $user_id
        ), ARRAY_A);

        $durations = [];
        foreach ($results as $row) {
            $key = $row['from_stage'] . '_to_' . $row['to_stage'];
            $durations[$key] = round($row['avg_hours'] / 24, 1); // Convert to days
        }

        return $durations;
    }

    // ============================================
    // SEQUENCE ANALYTICS
    // ============================================

    /**
     * Get sequence performance stats
     *
     * @param int $user_id User ID
     * @return array Sequence performance data
     */
    public function get_sequence_performance($user_id) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                s.id,
                s.name,
                COUNT(e.id) as total_enrolled,
                SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN e.status = 'replied' THEN 1 ELSE 0 END) as replied,
                SUM(CASE WHEN e.status = 'paused' THEN 1 ELSE 0 END) as paused
            FROM {$this->sequences_table} s
            LEFT JOIN {$this->enrollments_table} e ON s.id = e.sequence_id
            WHERE s.user_id = %d
            GROUP BY s.id, s.name
            ORDER BY total_enrolled DESC",
            $user_id
        ), ARRAY_A);

        foreach ($results as &$row) {
            $row['total_enrolled'] = (int) $row['total_enrolled'];
            $row['active'] = (int) $row['active'];
            $row['completed'] = (int) $row['completed'];
            $row['replied'] = (int) $row['replied'];
            $row['paused'] = (int) $row['paused'];
            $row['reply_rate'] = $row['total_enrolled'] > 0
                ? round(($row['replied'] / $row['total_enrolled']) * 100, 1)
                : 0;
            $row['completion_rate'] = $row['total_enrolled'] > 0
                ? round((($row['completed'] + $row['replied']) / $row['total_enrolled']) * 100, 1)
                : 0;
        }

        return $results;
    }

    // ============================================
    // TEMPLATE ANALYTICS
    // ============================================

    /**
     * Get template performance stats
     *
     * @param int $user_id User ID
     * @return array Template performance data
     */
    public function get_template_performance($user_id) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                t.id,
                t.name,
                t.channel,
                COUNT(o.id) as times_used,
                SUM(CASE WHEN o.status IN ('opened', 'clicked', 'replied') THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN o.status = 'replied' THEN 1 ELSE 0 END) as replied
            FROM {$this->templates_table} t
            LEFT JOIN {$this->outreach_table} o ON o.template_id = t.id
            WHERE t.user_id = %d OR t.user_id IS NULL
            GROUP BY t.id, t.name, t.channel
            HAVING times_used > 0
            ORDER BY times_used DESC
            LIMIT 20",
            $user_id
        ), ARRAY_A);

        foreach ($results as &$row) {
            $row['times_used'] = (int) $row['times_used'];
            $row['opened'] = (int) $row['opened'];
            $row['replied'] = (int) $row['replied'];
            $row['open_rate'] = $row['times_used'] > 0
                ? round(($row['opened'] / $row['times_used']) * 100, 1)
                : 0;
            $row['reply_rate'] = $row['times_used'] > 0
                ? round(($row['replied'] / $row['times_used']) * 100, 1)
                : 0;
        }

        return $results;
    }

    // ============================================
    // TIMING ANALYTICS
    // ============================================

    /**
     * Get best times to send analysis
     *
     * @param int $user_id User ID
     * @return array Best times data
     */
    public function get_best_send_times($user_id) {
        global $wpdb;

        // By day of week
        $by_day = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DAYNAME(sent_at) as day_name,
                DAYOFWEEK(sent_at) as day_num,
                COUNT(*) as sent,
                SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied
            FROM {$this->outreach_table}
            WHERE user_id = %d
            AND status IN ('sent', 'opened', 'clicked', 'replied')
            AND sent_at IS NOT NULL
            GROUP BY DAYNAME(sent_at), DAYOFWEEK(sent_at)
            ORDER BY day_num",
            $user_id
        ), ARRAY_A);

        foreach ($by_day as &$row) {
            $row['sent'] = (int) $row['sent'];
            $row['replied'] = (int) $row['replied'];
            $row['reply_rate'] = $row['sent'] > 0
                ? round(($row['replied'] / $row['sent']) * 100, 1)
                : 0;
        }

        // By hour of day
        $by_hour = $wpdb->get_results($wpdb->prepare(
            "SELECT
                HOUR(sent_at) as hour,
                COUNT(*) as sent,
                SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied
            FROM {$this->outreach_table}
            WHERE user_id = %d
            AND status IN ('sent', 'opened', 'clicked', 'replied')
            AND sent_at IS NOT NULL
            GROUP BY HOUR(sent_at)
            ORDER BY hour",
            $user_id
        ), ARRAY_A);

        foreach ($by_hour as &$row) {
            $row['hour'] = (int) $row['hour'];
            $row['sent'] = (int) $row['sent'];
            $row['replied'] = (int) $row['replied'];
            $row['reply_rate'] = $row['sent'] > 0
                ? round(($row['replied'] / $row['sent']) * 100, 1)
                : 0;
            $row['hour_label'] = sprintf('%02d:00', $row['hour']);
        }

        // Find best day and hour
        $best_day = null;
        $best_day_rate = 0;
        foreach ($by_day as $row) {
            if ($row['reply_rate'] > $best_day_rate && $row['sent'] >= 3) {
                $best_day_rate = $row['reply_rate'];
                $best_day = $row['day_name'];
            }
        }

        $best_hour = null;
        $best_hour_rate = 0;
        foreach ($by_hour as $row) {
            if ($row['reply_rate'] > $best_hour_rate && $row['sent'] >= 3) {
                $best_hour_rate = $row['reply_rate'];
                $best_hour = $row['hour_label'];
            }
        }

        return [
            'by_day'         => $by_day,
            'by_hour'        => $by_hour,
            'best_day'       => $best_day,
            'best_day_rate'  => $best_day_rate,
            'best_hour'      => $best_hour,
            'best_hour_rate' => $best_hour_rate,
            'recommendation' => $best_day && $best_hour
                ? "Best time to reach out: {$best_day} at {$best_hour}"
                : 'Not enough data to determine best send time',
        ];
    }

    // ============================================
    // RECRUITER INSIGHTS
    // ============================================

    /**
     * Get recruiter response insights
     *
     * @param int $user_id User ID
     * @param int $limit   Number of results
     * @return array Top responding recruiters
     */
    public function get_top_responding_recruiters($user_id, $limit = 10) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                r.id,
                r.name,
                r.firm,
                COUNT(o.id) as total_outreach,
                SUM(CASE WHEN o.status = 'replied' THEN 1 ELSE 0 END) as replied,
                MIN(o.sent_at) as first_contact,
                MAX(o.sent_at) as last_contact
            FROM {$this->recruiters_table} r
            INNER JOIN {$this->outreach_table} o ON r.id = o.recruiter_id
            WHERE o.user_id = %d
            AND o.status IN ('sent', 'opened', 'clicked', 'replied')
            GROUP BY r.id, r.name, r.firm
            HAVING total_outreach >= 1
            ORDER BY replied DESC, total_outreach DESC
            LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);

        foreach ($results as &$row) {
            $row['total_outreach'] = (int) $row['total_outreach'];
            $row['replied'] = (int) $row['replied'];
            $row['response_rate'] = $row['total_outreach'] > 0
                ? round(($row['replied'] / $row['total_outreach']) * 100, 1)
                : 0;
        }

        return $results;
    }

    /**
     * Get sector performance breakdown
     *
     * @param int $user_id User ID
     * @return array Sector stats
     */
    public function get_sector_performance($user_id) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                r.sector,
                COUNT(DISTINCT r.id) as recruiter_count,
                COUNT(o.id) as total_outreach,
                SUM(CASE WHEN o.status = 'replied' THEN 1 ELSE 0 END) as replied
            FROM {$this->recruiters_table} r
            LEFT JOIN {$this->outreach_table} o ON r.id = o.recruiter_id AND o.user_id = %d
            WHERE r.sector IS NOT NULL AND r.sector != ''
            GROUP BY r.sector
            ORDER BY total_outreach DESC",
            $user_id
        ), ARRAY_A);

        foreach ($results as &$row) {
            $row['recruiter_count'] = (int) $row['recruiter_count'];
            $row['total_outreach'] = (int) $row['total_outreach'];
            $row['replied'] = (int) $row['replied'];
            $row['reply_rate'] = $row['total_outreach'] > 0
                ? round(($row['replied'] / $row['total_outreach']) * 100, 1)
                : 0;
        }

        return $results;
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Get period start date
     *
     * @param string $period Period identifier
     * @return string Date string
     */
    private function get_period_start($period) {
        return match($period) {
            'week'    => date('Y-m-d', strtotime('-7 days')),
            'month'   => date('Y-m-d', strtotime('-30 days')),
            'quarter' => date('Y-m-d', strtotime('-90 days')),
            'year'    => date('Y-m-d', strtotime('-365 days')),
            default   => null,
        };
    }

    /**
     * Get previous period start date (for comparison)
     *
     * @param string $current_start Current period start
     * @return string Previous period start
     */
    private function get_previous_period_start($current_start) {
        if (!$current_start) {
            return null;
        }

        $days_diff = (strtotime('now') - strtotime($current_start)) / 86400;
        return date('Y-m-d', strtotime("-" . ($days_diff * 2) . " days"));
    }
}
