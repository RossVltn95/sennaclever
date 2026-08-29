/**
 * Conversation Admin JavaScript
 *
 * Handles admin message sending for candidate conversations
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        const $sendButton = $('#sffc_send_message');
        const $messageInput = $('#sffc_new_message');
        const $statusSpan = $('#sffc_message_status');
        const $messagesContainer = $('#sffc-messages-container');

        if (!$sendButton.length) {
            return;
        }

        $sendButton.on('click', function() {
            const postId = $(this).data('post-id');
            const message = $messageInput.val().trim();

            if (!message) {
                $statusSpan.html('<span style="color: #d63638;">Please enter a message</span>');
                return;
            }

            // Disable button and show loading
            $sendButton.prop('disabled', true).text('Sending...');
            $statusSpan.html('<span style="color: #666;">Sending message...</span>');

            $.ajax({
                url: sffcConvAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_admin_send_message',
                    nonce: sffcConvAdmin.nonce,
                    post_id: postId,
                    message: message
                },
                success: function(response) {
                    if (response.success) {
                        // Clear input
                        $messageInput.val('');

                        // Add message to container
                        const messageHtml = `
                            <div class="sffc-message sffc-message--admin">
                                <div class="sffc-message-text">${escapeHtml(message)}</div>
                                <div class="sffc-message-meta">
                                    Admin &bull; ${response.data.time}
                                </div>
                            </div>
                        `;

                        // Remove "no messages" placeholder if present
                        $messagesContainer.find('.sffc-no-messages').remove();

                        // Add new message
                        $messagesContainer.append(messageHtml);

                        // Scroll to bottom
                        $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);

                        $statusSpan.html('<span style="color: #46b450;">Message sent successfully!</span>');

                        // Clear status after 3 seconds
                        setTimeout(function() {
                            $statusSpan.html('');
                        }, 3000);
                    } else {
                        $statusSpan.html('<span style="color: #d63638;">Error: ' + (response.data || 'Unknown error') + '</span>');
                    }
                },
                error: function() {
                    $statusSpan.html('<span style="color: #d63638;">Network error. Please try again.</span>');
                },
                complete: function() {
                    $sendButton.prop('disabled', false).text('Send Message');
                }
            });
        });

        // Allow Enter to send (Shift+Enter for new line)
        $messageInput.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $sendButton.click();
            }
        });
    });

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
