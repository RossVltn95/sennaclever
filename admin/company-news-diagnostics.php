<?php
if (!defined('ABSPATH')) {
    exit;
}

$news_test_nonce = wp_create_nonce(SFFC_News_Diagnostics::NONCE_KEY);
?>
<div class="wrap">
    <h1><?php esc_html_e('Company News Diagnostics', 'senna-finance'); ?></h1>
    <p><?php esc_html_e('Generate a synthetic headline to verify that the news linker associates coverage with a company profile.', 'senna-finance'); ?></p>

    <table class="form-table" style="max-width: 700px;">
        <tr>
            <th scope="row"><label for="sffc-news-test-company"><?php esc_html_e('Company Name', 'senna-finance'); ?></label></th>
            <td>
                <input type="text" id="sffc-news-test-company" class="regular-text" value="Blackstone" placeholder="<?php esc_attr_e('e.g. Blackstone', 'senna-finance'); ?>" />
                <p class="description"><?php esc_html_e('Must match an existing company profile.', 'senna-finance'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="sffc-news-test-title"><?php esc_html_e('Headline (optional)', 'senna-finance'); ?></label></th>
            <td><input type="text" id="sffc-news-test-title" class="regular-text" placeholder="<?php esc_attr_e('Leave blank to auto-generate', 'senna-finance'); ?>" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="sffc-news-test-description"><?php esc_html_e('Summary (optional)', 'senna-finance'); ?></label></th>
            <td><textarea id="sffc-news-test-description" class="large-text" rows="3" placeholder="<?php esc_attr_e('Short blurb for the test headline', 'senna-finance'); ?>"></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="sffc-news-test-source"><?php esc_html_e('Source Name (optional)', 'senna-finance'); ?></label></th>
            <td><input type="text" id="sffc-news-test-source" class="regular-text" placeholder="<?php esc_attr_e('Defaults to SFFC QA Harness', 'senna-finance'); ?>" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="sffc-news-test-link"><?php esc_html_e('Source URL (optional)', 'senna-finance'); ?></label></th>
            <td><input type="url" id="sffc-news-test-link" class="regular-text" placeholder="https://example.com/test-headline" /></td>
        </tr>
    </table>

    <p><button type="button" class="button button-primary" id="sffc-create-test-news"><?php esc_html_e('Create Test News', 'senna-finance'); ?></button></p>
    <div id="sffc-news-test-result" style="margin-top: 15px;"></div>
</div>

<script>
    const sffcCompanyNewsNonce = '<?php echo esc_js($news_test_nonce); ?>';

    (function($) {
        function sffcCompanyNewsEsc(value) {
            if (value === undefined || value === null) {
                value = '';
            }
            return $('<div>').text(value).html();
        }

        function sffcCompanyNewsAttr(value) {
            if (!value) {
                return '';
            }
            try {
                return encodeURI(value).replace(/"/g, '&quot;');
            } catch (err) {
                return '';
            }
        }

        $('#sffc-create-test-news').on('click', function(event) {
            event.preventDefault();

            const $button = $(this);
            const $result = $('#sffc-news-test-result');
            let originalText = $button.data('original-text');

            if (!originalText) {
                originalText = $.trim($button.text()) || 'Create Test News';
                $button.data('original-text', originalText);
            }

            const payload = {
                action: 'sffc_create_test_news',
                nonce: sffcCompanyNewsNonce,
                company: $('#sffc-news-test-company').val(),
                title: $('#sffc-news-test-title').val(),
                description: $('#sffc-news-test-description').val(),
                source: $('#sffc-news-test-source').val(),
                link: $('#sffc-news-test-link').val()
            };

            $button.prop('disabled', true).text('<?php echo esc_js(__('Generating…', 'senna-finance')); ?>');
            $result.empty();

            $.post(ajaxurl, payload)
                .done(function(response) {
                    if (response && response.success) {
                        const data = response.data || {};
                        let html = '<div class="notice notice-success is-dismissible">';
                        html += '<p><strong>' + sffcCompanyNewsEsc('<?php echo esc_js(__('Success', 'senna-finance')); ?>') + ':</strong> ' + sffcCompanyNewsEsc('<?php echo esc_js(__('Linked article #', 'senna-finance')); ?>' + (data.news_id || '?') + ' <?php echo esc_js(__('to', 'senna-finance')); ?> ' + (data.copany_name || '')) + '.</p>';
                        html += '<p>' + sffcCompanyNewsEsc('<?php echo esc_js(__('Linker rows:', 'senna-finance')); ?> ' + (data.link_count !== undefined ? data.link_count : 0)) + '</p>';

                        if (Array.isArray(data.matches)) {
                            if (data.matches.length === 0) {
                                html += '<p><em>' + sffcCompanyNewsEsc('<?php echo esc_js(__('No company matches detected in the news text. Double-check aliases and spelling.', 'senna-finance')); ?>') + '</em></p>';
                            } else {
                                html += '<h4>' + sffcCompanyNewsEsc('<?php echo esc_js(__('Detected Matches', 'senna-finance')); ?>') + '</h4><ul>';
                                data.matches.forEach(function(match) {
                                    const label = (match.primary_name || '') + ' (' + (match.relevance_score || 0) + ', ' + (match.confidence || '') + ')';
                                    const terms = Array.isArray(match.matched_terms) ? match.matched_terms.join(', ') : '';
                                    html += '<li>' + sffcCompanyNewsEsc(label) + (terms ? ': ' + sffcCompanyNewsEsc(terms) : '') + '</li>';
                                });
                                html += '</ul>';
                            }
                        }

                        if (data.permalink) {
                            html += '<p><a href="' + sffcCompanyNewsAttr(data.permalink) + '" target="_blank" rel="noopener noreferrer">' + sffcCompanyNewsEsc('<?php echo esc_js(__('View news article', 'senna-finance')); ?>') + '</a></p>';
                        }

                        if (data.copany_profile_url) {
                            html += '<p><a href="' + sffcCompanyNewsAttr(data.copany_profile_url) + '" target="_blank" rel="noopener noreferrer">' + sffcCompanyNewsEsc('<?php echo esc_js(__('Open company profile', 'senna-finance')); ?>') + '</a></p>';
                        }

                        if (data.edit_link) {
                            html += '<p><a href="' + sffcCompanyNewsAttr(data.edit_link) + '">' + sffcCompanyNewsEsc('<?php echo esc_js(__('Edit news article in admin', 'senna-finance')); ?>') + '</a></p>';
                        }

                        if (data.db_error) {
                            html += '<p><strong>' + sffcCompanyNewsEsc('<?php echo esc_js(__('Database error', 'senna-finance')); ?>') + ':</strong> ' + sffcCompanyNewsEsc(data.db_error) + '</p>';
                        }

                        if (Array.isArray(data.link_rows)) {
                            if (data.link_rows.length > 0) {
                                html += '<h4>' + sffcCompanyNewsEsc('<?php echo esc_js(__('Existing Link Rows', 'senna-finance')); ?>') + '</h4><ul>';
                                data.link_rows.forEach(function(row) {
                                    const label = 'ID ' + row.id + ' • company ' + row.copany_id + ' • score ' + row.relevance_score;
                                    html += '<li>' + sffcCompanyNewsEsc(label) + '</li>';
                                });
                                html += '</ul>';
                            } else {
                                html += '<p><em>' + sffcCompanyNewsEsc('<?php echo esc_js(__('No link rows present for this article.', 'senna-finance')); ?>') + '</em></p>';
                            }
                        }

                        html += '</div>';
                        $result.html(html);
                    } else {
                        const message = (response && response.data && response.data.message) ? response.data.message : '<?php echo esc_js(__('Unable to create news item.', 'senna-finance')); ?>';
                        $result.html('<div class="notice notice-error"><p>' + sffcCompanyNewsEsc(message) + '</p></div>');
                    }
                })
                .fail(function() {
                    $result.html('<div class="notice notice-error"><p>' + sffcCompanyNewsEsc('<?php echo esc_js(__('Request failed. Please try again.', 'senna-finance')); ?>') + '</p></div>');
                })
                .always(function() {
                    $button.prop('disabled', false).text(originalText);
                });
        });
    })(jQuery);
</script>