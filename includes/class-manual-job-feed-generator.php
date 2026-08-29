<?php
/**
 * Manual Job Feed Generator
 * Converts manual job submissions into XML feed format for processing
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Manual_Job_Feed_Generator
{
    private $feeds_dir;

    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->feeds_dir = trailingslashit($upload_dir['basedir']) . 'sffc-manual-feeds';
        
        if (!file_exists($this->feeds_dir)) {
            wp_mkdir_p($this->feeds_dir);
        }
    }

    public function create_job_entry($form_data)
    {
        try {
            // Validate required fields
            $required_fields = array('job_title', 'company_name', 'location_city', 'location_country');
            foreach ($required_fields as $field) {
                if (empty($form_data[$field])) {
                    return new WP_Error('missing_field', sprintf(__('Field %s is required', 'senna-finance'), $field));
                }
            }

            // Generate XML feed entry
            $xml_content = $this->generate_xml_feed($form_data);
            
            // Save feed to uploads directory
            $feed_filename = 'manual-job-' . time() . '-' . uniqid() . '.xml';
            $feed_path = trailingslashit($this->feeds_dir) . $feed_filename;
            
            $saved = file_put_contents($feed_path, $xml_content);
            
            if ($saved === false) {
                return new WP_Error('save_failed', __('Failed to save feed file', 'senna-finance'));
            }

            // Create the job post in draft mode with manual flag
            $job_id = $this->create_draft_job_post($form_data, $feed_filename);
            
            if (is_wp_error($job_id)) {
                // Clean up feed file on failure
                unlink($feed_path);
                return $job_id;
            }

            return $job_id;

        } catch (Exception $e) {
            return new WP_Error('generation_failed', $e->getMessage());
        }
    }

    private function generate_xml_feed($data)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><jobs></jobs>');
        
        $job = $xml->addChild('job');
        
        // Basic job information
        $job->addChild('title', htmlspecialchars($data['job_title']));
        $job->addChild('company', htmlspecialchars($data['company_name']));
        $job->addChild('city', htmlspecialchars($data['location_city']));
        $job->addChild('country', htmlspecialchars($data['location_country']));
        $job->addChild('date', date('Y-m-d'));
        $job->addChild('referencenumber', 'MANUAL-' . time() . '-' . uniqid());
        
        // Job family and level
        if (!empty($data['job_family'])) {
            $job->addChild('jobfamily', htmlspecialchars($data['job_family']));
        }
        
        if (!empty($data['job_level'])) {
            $job->addChild('joblevel', htmlspecialchars($data['job_level']));
        }
        
        // Salary information
        if (!empty($data['salary_min']) && !empty($data['salary_max'])) {
            $currency = !empty($data['salary_currency']) ? $data['salary_currency'] : 'USD';
            $salary_display = number_format($data['salary_min']) . ' - ' . number_format($data['salary_max']) . ' ' . $currency;
            $job->addChild('salary', htmlspecialchars($salary_display));
        }
        
        // Job type
        if (!empty($data['job_type'])) {
            $job->addChild('jobtype', htmlspecialchars($data['job_type']));
        }
        
        // Application URL
        if (!empty($data['application_url'])) {
            $job->addChild('url', htmlspecialchars($data['application_url']));
        }
        
        // Job description sections
        $description = '';
        
        if (!empty($data['job_summary'])) {
            $description .= '<h3>Job Summary</h3><p>' . nl2br(htmlspecialchars($data['job_summary'])) . '</p>';
        }
        
        if (!empty($data['job_responsibilities'])) {
            $description .= '<h3>Key Responsibilities</h3>';
            $responsibilities = explode("\n", $data['job_responsibilities']);
            $description .= '<ul>';
            foreach ($responsibilities as $responsibility) {
                $responsibility = trim($responsibility);
                if (!empty($responsibility)) {
                    // Remove bullet points if they exist
                    $responsibility = preg_replace('/^[•\-\*]\s*/', '', $responsibility);
                    $description .= '<li>' . htmlspecialchars($responsibility) . '</li>';
                }
            }
            $description .= '</ul>';
        }
        
        if (!empty($data['job_requirements'])) {
            $description .= '<h3>Requirements</h3>';
            $requirements = explode("\n", $data['job_requirements']);
            $description .= '<ul>';
            foreach ($requirements as $requirement) {
                $requirement = trim($requirement);
                if (!empty($requirement)) {
                    // Remove bullet points if they exist
                    $requirement = preg_replace('/^[•\-\*]\s*/', '', $requirement);
                    $description .= '<li>' . htmlspecialchars($requirement) . '</li>';
                }
            }
            $description .= '</ul>';
        }
        
        if (!empty($data['job_qualifications'])) {
            $description .= '<h3>Preferred Qualifications</h3>';
            $qualifications = explode("\n", $data['job_qualifications']);
            $description .= '<ul>';
            foreach ($qualifications as $qualification) {
                $qualification = trim($qualification);
                if (!empty($qualification)) {
                    // Remove bullet points if they exist
                    $qualification = preg_replace('/^[•\-\*]\s*/', '', $qualification);
                    $description .= '<li>' . htmlspecialchars($qualification) . '</li>';
                }
            }
            $description .= '</ul>';
        }
        
        if (!empty($data['education_requirements'])) {
            $description .= '<h3>Education Requirements</h3><p>' . nl2br(htmlspecialchars($data['education_requirements'])) . '</p>';
        }
        
        $job->addChild('description', $description);
        
        // Skills
        $skills_array = array();
        
        if (!empty($data['technical_skills'])) {
            $technical = array_map('trim', explode(',', $data['technical_skills']));
            $skills_array = array_merge($skills_array, $technical);
        }
        
        if (!empty($data['soft_skills'])) {
            $soft = array_map('trim', explode(',', $data['soft_skills']));
            $skills_array = array_merge($skills_array, $soft);
        }
        
        if (!empty($skills_array)) {
            $job->addChild('skills', htmlspecialchars(implode(', ', $skills_array)));
        }
        
        // Experience years
        if (!empty($data['experience_years'])) {
            $job->addChild('experienceyears', intval($data['experience_years']));
        }
        
        // Source
        $job->addChild('source', 'Manual Submission');
        $job->addChild('sourcename', 'Manual Submission');
        
        // Format XML with proper indentation
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        
        return $dom->saveXML();
    }

    private function create_draft_job_post($data, $feed_filename)
    {
        $job_data = array(
            'post_title' => sanitize_text_field($data['job_title']),
            'post_content' => '',
            'post_status' => 'draft',
            'post_type' => 'sffc_job',
            'meta_input' => array(
                'sffc_manual_submission' => true,
                'sffc_manual_feed_file' => $feed_filename,
                'sffc_source_name' => 'Manual Submission',
                'sffc_actual_company' => sanitize_text_field($data['company_name']),
                'sffc_location_city' => sanitize_text_field($data['location_city']),
                'sffc_location_country' => sanitize_text_field($data['location_country']),
                'sffc_posted_date_display' => date('Y-m-d'),
            )
        );

        // Add optional metadata
        if (!empty($data['job_family'])) {
            $job_data['meta_input']['sffc_job_family'] = sanitize_text_field($data['job_family']);
        }

        if (!empty($data['job_level'])) {
            $job_data['meta_input']['sffc_job_level'] = sanitize_text_field($data['job_level']);
        }

        if (!empty($data['salary_min']) && !empty($data['salary_max'])) {
            $currency = !empty($data['salary_currency']) ? $data['salary_currency'] : 'USD';
            $salary_display = number_format($data['salary_min']) . ' - ' . number_format($data['salary_max']) . ' ' . $currency;
            $job_data['meta_input']['sffc_salary_display'] = $salary_display;
            $job_data['meta_input']['sffc_estimated_salary'] = $salary_display;
        }

        if (!empty($data['job_type'])) {
            $job_data['meta_input']['sffc_time_type'] = sanitize_text_field($data['job_type']);
        }

        if (!empty($data['application_url'])) {
            $job_data['meta_input']['_sffc_job_application_url'] = esc_url_raw($data['application_url']);
            $job_data['meta_input']['sffc_application_url'] = esc_url_raw($data['application_url']);
        }

        if (!empty($data['job_summary'])) {
            $job_data['meta_input']['sffc_job_summary'] = sanitize_textarea_field($data['job_summary']);
        }

        if (!empty($data['experience_years'])) {
            $job_data['meta_input']['sffc_pe_years_experience'] = intval($data['experience_years']) . ' years';
        }

        if (!empty($data['education_requirements'])) {
            $job_data['meta_input']['sffc_education_requirements'] = sanitize_textarea_field($data['education_requirements']);
        }

        if (!empty($data['job_qualifications'])) {
            $job_data['meta_input']['sffc_qualifications'] = sanitize_textarea_field($data['job_qualifications']);
        }

        // Store skills
        $skills_array = array();
        if (!empty($data['technical_skills'])) {
            $technical = array_map('trim', explode(',', $data['technical_skills']));
            $skills_array = array_merge($skills_array, $technical);
            $job_data['meta_input']['sffc_technical_skills'] = sanitize_text_field($data['technical_skills']);
        }

        if (!empty($data['soft_skills'])) {
            $soft = array_map('trim', explode(',', $data['soft_skills']));
            $skills_array = array_merge($skills_array, $soft);
            $job_data['meta_input']['sffc_soft_skills'] = sanitize_text_field($data['soft_skills']);
        }

        if (!empty($skills_array)) {
            $job_data['meta_input']['sffc_skills'] = implode(', ', $skills_array);
        }

        $job_id = wp_insert_post($job_data);

        if (is_wp_error($job_id)) {
            return $job_id;
        }

        return $job_id;
    }

    public function get_manual_feeds()
    {
        $feeds = array();
        
        if (!is_dir($this->feeds_dir)) {
            return $feeds;
        }

        $files = scandir($this->feeds_dir);
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'xml' && strpos($file, 'manual-job-') === 0) {
                $feeds[] = array(
                    'filename' => $file,
                    'path' => trailingslashit($this->feeds_dir) . $file,
                    'created' => filemtime(trailingslashit($this->feeds_dir) . $file)
                );
            }
        }

        // Sort by creation time, newest first
        usort($feeds, function($a, $b) {
            return $b['created'] - $a['created'];
        });

        return $feeds;
    }

    public function delete_feed($filename)
    {
        $file_path = trailingslashit($this->feeds_dir) . $filename;
        
        if (file_exists($file_path) && strpos($filename, 'manual-job-') === 0) {
            return unlink($file_path);
        }
        
        return false;
    }
}