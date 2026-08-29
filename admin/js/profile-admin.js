/**
 * Profile Admin JavaScript
 * Handles user profile management in WordPress admin
 */

jQuery(document).ready(function($) {
    'use strict';
    
    let currentProfile = null;
    
    // Initialize Select2 for user search
    $('#user-selector').select2({
        ajax: {
            url: sffcProfileAdmin.ajaxUrl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    action: 'sffc_search_users'
                };
            },
            processResults: function(data) {
                return data;
            },
            cache: true
        },
        minimumInputLength: 2,
        placeholder: 'Search for a user...'
    });
    
    // Initialize Select2 for multi-selects
    $('#preferred_locations, #preferred_industries').select2({
        placeholder: 'Select options...',
        allowClear: true
    });
    
    // Initialize skills dropdown
    initializeSkillsDropdown();
    
    // Load profile button
    $('#load-profile').on('click', function() {
        const userId = $('#user-selector').val();
        if (!userId) {
            alert('Please select a user first');
            return;
        }
        
        loadUserProfile(userId);
    });
    
    // Add skill functionality
    $('#add-skill').on('click', function() {
        const skillData = {
            skill_name: $('#new-skill-name').val(),
            skill_category: $('#new-skill-category').val(),
            proficiency_level: $('#new-skill-proficiency').val(),
            years_experience: parseInt($('#new-skill-years').val()) || 1
        };
        
        if (!skillData.skill_name) {
            alert('Please select a skill');
            return;
        }
        
        addSkillToProfile(skillData);
    });
    
    // Remove skill functionality
    $(document).on('click', '.remove-skill', function() {
        const skillId = $(this).data('skill-id');
        const $skillTag = $(this).closest('.skill-tag');
        
        if (confirm('Remove this skill?')) {
            removeSkillFromProfile(skillId, $skillTag);
        }
    });
    
    // Add experience functionality
    $('#add-experience').on('click', function() {
        openExperienceModal();
    });
    
    // Experience form submission
    $('#experience-form').on('submit', function(e) {
        e.preventDefault();
        saveExperience();
    });
    
    // Modal close functionality
    $('.close-modal, .cancel-modal').on('click', function() {
        $('.profile-modal').fadeOut();
    });
    
    // Current role checkbox
    $('#exp-is-current').on('change', function() {
        if ($(this).is(':checked')) {
            $('#exp-end-date').val('').prop('disabled', true);
        } else {
            $('#exp-end-date').prop('disabled', false);
        }
    });
    
    // Profile form submission
    $('#profile-form').on('submit', function(e) {
        e.preventDefault();
        saveProfile();
    });
    
    // Auto-save on field changes
    $('#profile-form input, #profile-form select').on('change', function() {
        if (currentProfile) {
            updateCompletionPercentage();
        }
    });
    
    /**
     * Initialize skills dropdown with taxonomy
     */
    function initializeSkillsDropdown() {
        const skillsOptions = [];
        
        Object.keys(sffcProfileAdmin.skillsTaxonomy).forEach(category => {
            sffcProfileAdmin.skillsTaxonomy[category].forEach(skill => {
                skillsOptions.push({
                    id: skill,
                    text: skill,
                    category: category
                });
            });
        });
        
        $('#new-skill-name').select2({
            data: skillsOptions,
            placeholder: 'Select or type skill...',
            allowClear: true,
            tags: true, // Allow custom skills
            templateResult: function(skill) {
                if (skill.loading) return skill.text;
                
                const $result = $('<span></span>');
                $result.text(skill.text);
                
                if (skill.category) {
                    $result.append(' <small style="color: #666;">(' + skill.category + ')</small>');
                }
                
                return $result;
            }
        });
        
        // Auto-set category when skill is selected
        $('#new-skill-name').on('select2:select', function(e) {
            const selectedSkill = e.params.data;
            if (selectedSkill.category) {
                $('#new-skill-category').val(selectedSkill.category);
            }
        });
    }
    
    /**
     * Load user profile
     */
    function loadUserProfile(userId) {
        const $button = $('#load-profile');
        $button.prop('disabled', true).text('Loading...');
        
        $.ajax({
            url: sffcProfileAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_admin_get_user_profile',
                user_id: userId,
                nonce: sffcProfileAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentProfile = response.data;
                    populateProfileForm(currentProfile);
                    $('#profile-editor').fadeIn();
                } else {
                    alert('Failed to load profile: ' + response.data.message);
                }
            },
            error: function() {
                alert('Network error while loading profile');
            },
            complete: function() {
                $button.prop('disabled', false).text('Load Profile');
            }
        });
    }
    
    /**
     * Populate profile form with data
     */
    function populateProfileForm(profile) {
        // Basic information
        $('#profile-user-id').val(profile.user_id);
        $('#career_stage').val(profile.career_stage || 'Graduate');
        $('#years_experience').val(profile.years_experience || 0);
        $('#current_title').val(profile.current_title || '');
        $('#current_company').val(profile.current_company || '');
        $('#salary_current').val(profile.salary_current || 0);
        $('#salary_target_min').val(profile.salary_target_min || 0);
        $('#salary_target_max').val(profile.salary_target_max || 0);
        $('#notice_period').val(profile.notice_period || '1 month');
        $('#visa_status').val(profile.visa_status || 'Citizen');
        
        // Preferences
        $('#preferred_locations').val(profile.preferred_locations || []).trigger('change');
        $('#preferred_industries').val(profile.preferred_industries || []).trigger('change');
        
        // Skills
        renderSkills(profile.skills || []);
        
        // Experience
        renderExperience(profile.experience || []);
        
        // Update completion percentage
        updateCompletionDisplay(profile.profile_completion_percentage || 0);
    }
    
    /**
     * Render skills list
     */
    function renderSkills(skills) {
        const $skillsList = $('#skills-list');
        $skillsList.empty();
        
        if (skills.length === 0) {
            $skillsList.html('<p style="color: #666;">No skills added yet.</p>');
            return;
        }
        
        skills.forEach(skill => {
            const $skillTag = $(`
                <div class="skill-tag">
                    <strong>${skill.skill_name}</strong>
                    <small>(${skill.proficiency_level}, ${skill.years_experience}y)</small>
                    <span class="remove-skill" data-skill-id="${skill.id}" title="Remove skill">&times;</span>
                </div>
            `);
            
            $skillsList.append($skillTag);
        });
    }
    
    /**
     * Render experience list
     */
    function renderExperience(experiences) {
        const $experienceList = $('#experience-list');
        $experienceList.empty();
        
        if (experiences.length === 0) {
            $experienceList.html('<p style="color: #666;">No experience added yet.</p>');
            return;
        }
        
        experiences.forEach(exp => {
            const endDate = exp.is_current ? 'Present' : (exp.end_date || 'Present');
            const duration = calculateDuration(exp.start_date, exp.end_date, exp.is_current);
            
            const $expItem = $(`
                <div class="experience-item">
                    <h4>${exp.job_title} at ${exp.company_name}</h4>
                    <div class="experience-meta">
                        ${exp.start_date} - ${endDate} (${duration})
                        ${exp.industry ? ' • ' + exp.industry : ''}
                    </div>
                    ${exp.description ? '<p>' + exp.description + '</p>' : ''}
                    <div class="experience-actions">
                        <button type="button" class="button edit-experience" data-exp-id="${exp.id}">Edit</button>
                        <button type="button" class="button remove-experience" data-exp-id="${exp.id}">Remove</button>
                    </div>
                </div>
            `);
            
            $experienceList.append($expItem);
        });
    }
    
    /**
     * Calculate duration between dates
     */
    function calculateDuration(startDate, endDate, isCurrent) {
        const start = new Date(startDate);
        const end = isCurrent || !endDate ? new Date() : new Date(endDate);
        
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        const years = Math.floor(diffDays / 365);
        const months = Math.floor((diffDays % 365) / 30);
        
        if (years > 0) {
            return years + ' year' + (years > 1 ? 's' : '') + 
                   (months > 0 ? ', ' + months + ' month' + (months > 1 ? 's' : '') : '');
        } else if (months > 0) {
            return months + ' month' + (months > 1 ? 's' : '');
        } else {
            return 'Less than 1 month';
        }
    }
    
    /**
     * Add skill to profile
     */
    function addSkillToProfile(skillData) {
        if (!currentProfile) {
            alert('Please load a profile first');
            return;
        }
        
        const userId = currentProfile.user_id;
        
        $.ajax({
            url: sffcProfileAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_add_skill',
                skill_data: skillData,
                nonce: sffcProfileAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Reload profile to get updated skills
                    loadUserProfile(userId);
                    
                    // Clear form
                    $('#new-skill-name').val(null).trigger('change');
                    $('#new-skill-category').val('Technical');
                    $('#new-skill-proficiency').val('Intermediate');
                    $('#new-skill-years').val(1);
                    
                    showMessage('Skill added successfully', 'success');
                } else {
                    alert('Failed to add skill: ' + response.data.message);
                }
            },
            error: function() {
                alert('Network error while adding skill');
            }
        });
    }
    
    /**
     * Remove skill from profile
     */
    function removeSkillFromProfile(skillId, $skillTag) {
        if (!currentProfile) return;
        
        $.ajax({
            url: sffcProfileAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_remove_skill',
                skill_id: skillId,
                nonce: sffcProfileAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $skillTag.fadeOut(function() {
                        $(this).remove();
                        updateCompletionPercentage();
                    });
                    showMessage('Skill removed', 'success');
                } else {
                    alert('Failed to remove skill');
                }
            },
            error: function() {
                alert('Network error while removing skill');
            }
        });
    }
    
    /**
     * Open experience modal
     */
    function openExperienceModal(experienceData = null) {
        if (experienceData) {
            // Editing existing experience
            $('#experience-id').val(experienceData.id);
            $('#exp-company-name').val(experienceData.company_name);
            $('#exp-job-title').val(experienceData.job_title);
            $('#exp-industry').val(experienceData.industry);
            $('#exp-start-date').val(experienceData.start_date);
            $('#exp-end-date').val(experienceData.end_date);
            $('#exp-is-current').prop('checked', experienceData.is_current);
            $('#exp-description').val(experienceData.description);
        } else {
            // Adding new experience
            $('#experience-form')[0].reset();
            $('#experience-id').val('');
        }
        
        $('#experience-modal').fadeIn();
    }
    
    /**
     * Save experience
     */
    function saveExperience() {
        const experienceData = {
            company_name: $('#exp-company-name').val(),
            job_title: $('#exp-job-title').val(),
            industry: $('#exp-industry').val(),
            start_date: $('#exp-start-date').val(),
            end_date: $('#exp-end-date').val(),
            is_current: $('#exp-is-current').is(':checked') ? 1 : 0,
            description: $('#exp-description').val()
        };
        
        if (!experienceData.company_name || !experienceData.job_title || !experienceData.start_date) {
            alert('Please fill in all required fields');
            return;
        }
        
        $.ajax({
            url: sffcProfileAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_add_experience',
                experience_data: experienceData,
                nonce: sffcProfileAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#experience-modal').fadeOut();
                    // Reload profile to get updated experience
                    loadUserProfile(currentProfile.user_id);
                    showMessage('Experience saved successfully', 'success');
                } else {
                    alert('Failed to save experience: ' + response.data.message);
                }
            },
            error: function() {
                alert('Network error while saving experience');
            }
        });
    }
    
    /**
     * Save profile
     */
    function saveProfile() {
        if (!currentProfile) {
            alert('No profile loaded');
            return;
        }
        
        const formData = $('#profile-form').serializeArray();
        const profileData = {};
        
        formData.forEach(field => {
            if (field.name.endsWith('[]')) {
                const key = field.name.replace('[]', '');
                if (!profileData[key]) profileData[key] = [];
                profileData[key].push(field.value);
            } else {
                profileData[field.name] = field.value;
            }
        });
        
        const $button = $('#profile-form button[type="submit"]');
        const $status = $('#save-status');
        
        $button.prop('disabled', true).text('Saving...');
        $status.text('Saving...');
        
        $.ajax({
            url: sffcProfileAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_admin_save_user_profile',
                user_id: currentProfile.user_id,
                profile_data: profileData,
                nonce: sffcProfileAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.text('✓ Saved').css('color', 'green');
                    updateCompletionPercentage();
                    
                    setTimeout(() => {
                        $status.text('');
                    }, 3000);
                } else {
                    $status.text('✗ Error: ' + response.data.message).css('color', 'red');
                }
            },
            error: function() {
                $status.text('✗ Network error').css('color', 'red');
            },
            complete: function() {
                $button.prop('disabled', false).text('Save Profile');
            }
        });
    }
    
    /**
     * Update completion percentage
     */
    function updateCompletionPercentage() {
        // Simple calculation based on filled fields
        const fields = [
            '#career_stage', '#years_experience', '#current_title', '#current_company',
            '#salary_target_min', '#salary_target_max'
        ];
        
        let filled = 0;
        fields.forEach(field => {
            if ($(field).val()) filled++;
        });
        
        // Add points for preferences
        if ($('#preferred_locations').val()?.length > 0) filled += 2;
        if ($('#preferred_industries').val()?.length > 0) filled += 2;
        
        // Add points for skills and experience (estimated)
        const skillsCount = $('#skills-list .skill-tag').length;
        const experienceCount = $('#experience-list .experience-item').length;
        
        if (skillsCount >= 3) filled += 3;
        else if (skillsCount >= 1) filled += 1;
        
        if (experienceCount >= 1) filled += 2;
        
        const totalFields = 10; // Adjust based on calculation
        const percentage = Math.min(100, Math.round((filled / totalFields) * 100));
        
        updateCompletionDisplay(percentage);
    }
    
    /**
     * Update completion display
     */
    function updateCompletionDisplay(percentage) {
        $('#completion-fill').css('width', percentage + '%');
        $('#completion-text').text(percentage + '% Complete');
        
        if (percentage >= 80) {
            $('#completion-fill').css('background', '#6bcf7f');
        } else if (percentage >= 50) {
            $('#completion-fill').css('background', '#ffd93d');
        } else {
            $('#completion-fill').css('background', '#ff6b6b');
        }
    }
    
    /**
     * Show message
     */
    function showMessage(message, type = 'info') {
        const alertClass = type === 'success' ? 'notice-success' : 
                          type === 'error' ? 'notice-error' : 'notice-info';
        
        const $alert = $('<div class="notice ' + alertClass + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($alert);
        
        setTimeout(() => {
            $alert.fadeOut(() => $alert.remove());
        }, 3000);
    }
});