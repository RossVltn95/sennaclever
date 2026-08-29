/**
 * Logo Carousel - WordPress Admin JavaScript
 *
 * Handles logo upload, removal, and drag-and-drop reordering
 */

(function($) {
    'use strict';

    const LogoCarouselAdmin = {
        mediaUploader: null,
        logoIndex: 0,

        init: function() {
            this.logoIndex = $('.sffc-logo-item').length;
            this.bindEvents();
            this.initSortable();
        },

        bindEvents: function() {
            // Add logo button
            $('#sffc-add-logo').on('click', this.openMediaUploader.bind(this));

            // Remove logo button (delegated event)
            $(document).on('click', '.sffc-remove-logo', this.removeLogo.bind(this));
        },

        initSortable: function() {
            $('#sffc-logo-grid').sortable({
                items: '.sffc-logo-item',
                cursor: 'move',
                opacity: 0.8,
                placeholder: 'ui-sortable-placeholder',
                update: function() {
                    LogoCarouselAdmin.reindexLogos();
                }
            });
        },

        openMediaUploader: function(e) {
            e.preventDefault();

            // If media uploader exists, reopen it
            if (this.mediaUploader) {
                this.mediaUploader.open();
                return;
            }

            // Create media uploader
            this.mediaUploader = wp.media({
                title: 'Select or Upload Logo',
                button: {
                    text: 'Add Logo'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            // Handle selection
            this.mediaUploader.on('select', this.handleLogoSelect.bind(this));

            // Open uploader
            this.mediaUploader.open();
        },

        handleLogoSelect: function() {
            const attachment = this.mediaUploader.state().get('selection').first().toJSON();

            // Add logo to grid
            this.addLogoToGrid({
                id: attachment.id,
                url: attachment.url,
                alt: attachment.alt || attachment.title || ''
            });

            this.logoIndex++;
        },

        addLogoToGrid: function(logo) {
            const $grid = $('#sffc-logo-grid');
            const index = this.logoIndex;

            const html = `
                <div class="sffc-logo-item" data-index="${index}">
                    <div class="sffc-logo-preview">
                        <img src="${this.escapeHtml(logo.url)}" alt="${this.escapeHtml(logo.alt)}" />
                    </div>
                    <input type="hidden" name="sffc_logo_carousel_images[${index}][id]" value="${logo.id}" />
                    <input type="hidden" name="sffc_logo_carousel_images[${index}][url]" value="${this.escapeHtml(logo.url)}" />
                    <input type="text"
                           name="sffc_logo_carousel_images[${index}][alt]"
                           placeholder="Company name"
                           value="${this.escapeHtml(logo.alt)}"
                           class="sffc-logo-alt" />
                    <button type="button" class="button sffc-remove-logo">Remove</button>
                </div>
            `;

            $grid.append(html);
        },

        removeLogo: function(e) {
            e.preventDefault();

            if (confirm('Are you sure you want to remove this logo?')) {
                $(e.target).closest('.sffc-logo-item').fadeOut(300, function() {
                    $(this).remove();
                    LogoCarouselAdmin.reindexLogos();
                });
            }
        },

        reindexLogos: function() {
            // Reindex all logos after reordering or removal
            $('#sffc-logo-grid .sffc-logo-item').each(function(index) {
                const $item = $(this);
                $item.attr('data-index', index);

                // Update input names
                $item.find('input[type="hidden"]').eq(0).attr('name', `sffc_logo_carousel_images[${index}][id]`);
                $item.find('input[type="hidden"]').eq(1).attr('name', `sffc_logo_carousel_images[${index}][url]`);
                $item.find('.sffc-logo-alt').attr('name', `sffc_logo_carousel_images[${index}][alt]`);
            });
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        if ($('.sffc-logo-carousel-admin').length) {
            LogoCarouselAdmin.init();
        }
    });

})(jQuery);
