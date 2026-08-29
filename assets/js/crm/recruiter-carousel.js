/**
 * Recruiter Carousel - Auto-sliding carousel for membership page
 * Handles carousel navigation, auto-slide, checkbox selection, and conversion modals
 */

(function($) {
    'use strict';

    const RecruiterCarousel = {
        currentSlide: 0,
        totalSlides: 0,
        autoSlideInterval: null,
        autoSlideDelay: 5000,
        selectedPosts: new Set(),
        reviewIntervals: new WeakMap(),

        init: function() {
            const $wrapper = $('.sffc-recruiter-carousel-wrapper');
            if (!$wrapper.length) return;

            this.autoSlideDelay = parseInt($wrapper.data('interval')) || 5000;
            this.totalSlides = $('.sffc-carousel-card').length;
            this.bindEvents();
            this.initPlanSelection();

            if (this.totalSlides === 0) return;

            this.buildIndicators();
            this.startAutoSlide();
            this.goToSlide(0);
        },

        buildIndicators: function() {
            const $indicators = $('.sffc-carousel-indicators');
            $indicators.empty();

            for (let i = 0; i < this.totalSlides; i++) {
                $indicators.append(`<button class="sffc-carousel-indicator" data-slide="${i}" aria-label="Go to slide ${i + 1}"></button>`);
            }
        },

        bindEvents: function() {
            const self = this;

            // Navigation buttons
            $('.sffc-carousel-nav--prev').on('click', function() {
                self.stopAutoSlide();
                self.prevSlide();
                self.startAutoSlide();
            });

            $('.sffc-carousel-nav--next').on('click', function() {
                self.stopAutoSlide();
                self.nextSlide();
                self.startAutoSlide();
            });

            // Indicator clicks
            $(document).on('click', '.sffc-carousel-indicator', function() {
                const slide = parseInt($(this).data('slide'));
                self.stopAutoSlide();
                self.goToSlide(slide);
                self.startAutoSlide();
            });

            // Checkbox selection (delegated for dynamic content)
            $(document).on('change', '.sffc-carousel-select', function() {
                const postId = $(this).val();
                if ($(this).is(':checked')) {
                    self.selectedPosts.add(postId);
                } else {
                    self.selectedPosts.delete(postId);
                }
                self.updateBulkBar();
            });

            // Bulk outreach button scrolls to plan grid
            $(document).on('click', '.sffc-carousel-outreach-btn', function(e) {
                e.preventDefault();
                if (!self.scrollToPlans()) {
                    self.showMembershipModal();
                }
            });

            // Hero CTA scrolls to plan grid
            $(document).on('click', '.sffc-carousel-cta-btn', function(e) {
                e.preventDefault();
                if (!self.scrollToPlans()) {
                    const href = $(this).attr('href');
                    if (href && href.startsWith('#')) {
                        window.location.hash = href;
                    }
                }
            });

            // Pricing influence interactions
            $(document).on('click', '[data-carousel-cycle-toggle]', function() {
                self.switchCycle($(this).closest('.sffc-carousel-plans'), String($(this).data('carouselCycleToggle') || 'monthly'));
            });

            $(document).on('click', '[data-carousel-plan-open]', function(e) {
                e.preventDefault();
                self.showCheckout($(this).closest('.sffc-carousel-plans'));
            });

            $(document).on('click', '[data-carousel-plan-back]', function(e) {
                e.preventDefault();
                self.showFront($(this).closest('.sffc-carousel-plans'));
            });

            $(document).on('click', '[data-carousel-other-toggle]', function(e) {
                e.preventDefault();
                self.toggleOtherPlans($(this).closest('.sffc-carousel-plans'));
            });

            $(document).on('click', '[data-carousel-other-plan]', function(e) {
                e.preventDefault();
                self.selectPlanBySlug($(this).closest('.sffc-carousel-plans'), String($(this).data('planSlug') || ''), false);
            });

            $(document).on('click', '[data-carousel-review-dot]', function(e) {
                e.preventDefault();
                const $plans = $(this).closest('.sffc-carousel-plans');
                self.showReviewAt($plans, parseInt($(this).data('index'), 10) || 0, true);
            });

            // Pause on hover
            $('.sffc-recruiter-carousel').on('mouseenter', function() {
                self.stopAutoSlide();
            }).on('mouseleave', function() {
                self.startAutoSlide();
            });
        },

        goToSlide: function(index) {
            if (index < 0) index = this.totalSlides - 1;
            if (index >= this.totalSlides) index = 0;

            this.currentSlide = index;

            // Update carousel position
            const offset = -index * 100;
            $('.sffc-carousel-track').css('transform', `translateX(${offset}%)`);

            // Update indicators
            $('.sffc-carousel-indicator').removeClass('active');
            $(`.sffc-carousel-indicator[data-slide="${index}"]`).addClass('active');
        },

        nextSlide: function() {
            this.goToSlide(this.currentSlide + 1);
        },

        prevSlide: function() {
            this.goToSlide(this.currentSlide - 1);
        },

        startAutoSlide: function() {
            const self = this;
            this.stopAutoSlide();
            this.autoSlideInterval = setInterval(function() {
                self.nextSlide();
            }, this.autoSlideDelay);
        },

        stopAutoSlide: function() {
            if (this.autoSlideInterval) {
                clearInterval(this.autoSlideInterval);
                this.autoSlideInterval = null;
            }
        },

        updateBulkBar: function() {
            const count = this.selectedPosts.size;
            const $bulkBar = $('#sffc-carousel-bulk-bar');
            const $count = $('.inst-similar-bulk-count');

            $count.text(count);

            if (count > 0) {
                $bulkBar.slideDown(200);
            } else {
                $bulkBar.slideUp(200);
            }
        },

        initPlanSelection: function() {
            const self = this;
            $('.sffc-carousel-plans').each(function() {
                const $plans = $(this);
                const defaultCycle = String($plans.data('defaultCycle') || 'monthly');
                self.switchCycle($plans, defaultCycle, true);
            });
        },

        getPlanSource: function($plans, slug) {
            if (!slug) {
                return $();
            }
            return $plans.find(`[data-carousel-plan-source][data-plan-slug="${slug}"]`).first();
        },

        getPlansForCycle: function($plans, cycle) {
            return $plans.find(`[data-carousel-plan-source][data-plan-cycle="${cycle}"]`);
        },

        getPrimarySourceForCycle: function($plans, cycle) {
            const $sources = this.getPlansForCycle($plans, cycle);
            const $featured = $sources.filter(function() {
                return String($(this).data('planFeatured')) === '1';
            }).first();

            return $featured.length ? $featured : $sources.first();
        },

        readPlanData: function($source) {
            if (!$source.length) {
                return null;
            }

            return {
                slug: String($source.data('planSlug') || ''),
                cycle: String($source.data('planCycle') || 'monthly'),
                featured: String($source.data('planFeatured') || '') === '1',
                name: String($source.data('planName') || ''),
                price: String($source.data('planPrice') || ''),
                billingCycle: String($source.data('planBillingCycle') || ''),
                tagline: String($source.data('planTagline') || ''),
                heroEyebrow: String($source.data('planHeroEyebrow') || ''),
                heroTitle: String($source.data('planHeroTitle') || ''),
                heroCopy: String($source.data('planHeroCopy') || ''),
                heroCopyHtml: String($source.attr('data-plan-hero-copy-html') || ''),
                heroImageUrl: String($source.data('planHeroImageUrl') || ''),
                heroImageAlt: String($source.data('planHeroImageAlt') || ''),
                heroCtaLabel: String($source.data('planHeroCtaLabel') || ''),
                authorityTitle: String($source.data('planAuthorityTitle') || ''),
                authorityCopy: String($source.data('planAuthorityCopy') || ''),
                socialTitle: String($source.data('planSocialTitle') || ''),
                socialCopy: String($source.data('planSocialCopy') || ''),
                socialReviewScore: String($source.data('planSocialReviewScore') || ''),
                socialReviewCount: String($source.data('planSocialReviewCount') || ''),
                socialReviews: this.parseReviews($source.attr('data-plan-social-reviews')),
                freeTitle: String($source.data('planFreeTitle') || ''),
                freeCopy: String($source.data('planFreeCopy') || ''),
                categoryTitle: String($source.data('planCategoryTitle') || ''),
                categoryCopy: String($source.data('planCategoryCopy') || ''),
                scarcityTitle: String($source.data('planScarcityTitle') || ''),
                scarcityCopy: String($source.data('planScarcityCopy') || ''),
                nowTitle: String($source.data('planNowTitle') || ''),
                nowCopy: String($source.data('planNowCopy') || ''),
                otherPlansLabel: String($source.data('planOtherPlansLabel') || ''),
                backLabel: String($source.data('planBackLabel') || '')
            };
        },

        rememberSelection: function($plans, plan) {
            if (!plan || !plan.slug) {
                return;
            }

            $plans.attr(`data-selected-${plan.cycle}-slug`, plan.slug);
        },

        getRememberedSlug: function($plans, cycle) {
            return String($plans.attr(`data-selected-${cycle}-slug`) || '');
        },

        selectPlanBySlug: function($plans, slug, keepFace) {
            const $source = this.getPlanSource($plans, slug);
            const plan = this.readPlanData($source);
            if (!plan) {
                return;
            }

            this.renderPlan($plans, plan);
            this.rememberSelection($plans, plan);
            this.syncCycleButtons($plans, plan.cycle);
            this.syncOtherPlans($plans, plan.slug);

            if (!keepFace) {
                this.showFront($plans);
            }
        },

        switchCycle: function($plans, cycle, keepFace) {
            const rememberedSlug = this.getRememberedSlug($plans, cycle);
            let $source = this.getPlanSource($plans, rememberedSlug);

            if (!$source.length) {
                $source = this.getPrimarySourceForCycle($plans, cycle);
            }

            const plan = this.readPlanData($source);
            if (!plan) {
                return;
            }

            this.renderPlan($plans, plan);
            this.rememberSelection($plans, plan);
            this.syncCycleButtons($plans, cycle);
            this.syncOtherPlans($plans, plan.slug);

            if (!keepFace) {
                this.showFront($plans);
            }
        },

        syncCycleButtons: function($plans, cycle) {
            $plans.find('[data-carousel-cycle-toggle]').each(function() {
                const $button = $(this);
                const isActive = String($button.data('carouselCycleToggle') || '') === cycle;
                $button.toggleClass('is-active', isActive).attr('aria-pressed', isActive ? 'true' : 'false');
            });
        },

        syncOtherPlans: function($plans, activeSlug) {
            $plans.find('[data-carousel-other-plan]').each(function() {
                $(this).toggleClass('is-active', String($(this).data('planSlug') || '') === activeSlug);
            });
        },

        renderPlan: function($plans, plan) {
            const bindings = {
                hero_eyebrow: plan.heroEyebrow,
                hero_title: plan.heroTitle,
                hero_copy: plan.heroCopy,
                authority_title: plan.authorityTitle,
                authority_copy: plan.authorityCopy,
                social_title: plan.socialTitle,
                social_copy: plan.socialCopy,
                social_review_score: plan.socialReviewScore,
                social_review_count: plan.socialReviewCount ? `${Number(plan.socialReviewCount).toLocaleString()} reviews` : '',
                free_title: plan.freeTitle,
                free_copy: plan.freeCopy,
                category_title: plan.categoryTitle,
                category_copy: plan.categoryCopy,
                scarcity_title: plan.scarcityTitle,
                scarcity_copy: plan.scarcityCopy,
                now_title: plan.nowTitle,
                now_copy: plan.nowCopy,
                price: plan.price,
                billing_cycle: plan.billingCycle,
                hero_cta_label: plan.heroCtaLabel,
                other_plans_label: plan.otherPlansLabel,
                back_label: plan.backLabel
            };

            Object.keys(bindings).forEach(function(key) {
                $plans.find(`[data-carousel-bind="${key}"]`).text(bindings[key] || '');
            });

            const heroCopyHtml = plan.heroCopyHtml || '';
            if (heroCopyHtml) {
                $plans.find('[data-carousel-bind-html="hero_copy"]').html(heroCopyHtml);
            } else {
                $plans.find('[data-carousel-bind-html="hero_copy"]').empty();
            }

            const $starsWrap = $plans.find('[data-carousel-social-stars-wrap]');
            const $stars = $plans.find('[data-carousel-social-stars]');
            if (plan.socialReviewScore) {
                $starsWrap.removeClass('is-hidden');
                $stars.html(this.buildStars(plan.socialReviewScore));
            } else {
                $starsWrap.addClass('is-hidden');
                $stars.empty();
            }

            this.renderReviews($plans, plan.socialReviews || [], plan.socialCopy || '');

            const $heroImageWrap = $plans.find('[data-carousel-hero-image-wrap]');
            const $heroImage = $plans.find('[data-carousel-bind-image="hero_image"]');
            if (plan.heroImageUrl) {
                $heroImageWrap.removeClass('is-hidden');
                $heroImage.attr('src', plan.heroImageUrl).attr('alt', plan.heroImageAlt || plan.name);
            } else {
                $heroImageWrap.addClass('is-hidden');
                $heroImage.attr('src', '').attr('alt', '');
            }

            $plans.find('[data-carousel-plan-title]').text(plan.name || '');
            $plans.find('[data-carousel-plan-price]').text(plan.price || '');
            $plans.find('[data-carousel-plan-cycle]').text(plan.billingCycle || '');
            $plans.find('[data-carousel-plan-copy]').text(plan.tagline || plan.heroCopy || '');

            const $checkout = $plans.find('[data-carousel-plan-checkout-form]');
            const $allShells = $checkout.children('[data-carousel-plan-shell], [data-carousel-plan-external]');
            const $targetShell = $checkout.children(`[data-carousel-plan-shell="${plan.slug}"], [data-carousel-plan-external="${plan.slug}"]`).first();
            $allShells.removeClass('is-active').attr('hidden', 'hidden');
            if ($targetShell.length) {
                $targetShell.addClass('is-active').removeAttr('hidden');
            }

            $plans.find('[data-carousel-browser-badge]').text(plan.featured ? 'Most popular' : 'Recommended');
        },

        parseReviews: function(raw) {
            if (!raw) {
                return [];
            }

            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed.filter(Boolean) : [];
            } catch (error) {
                return [];
            }
        },

        formatReviewerName: function(review) {
            const first = String(review.first_name || '').trim();
            const last = String(review.last_name || '').trim();
            const lastInitial = last ? `${last.charAt(0).toUpperCase()}.` : '';
            const name = `${first} ${lastInitial}`.trim();
            return name || 'Anonymous';
        },

        truncateReview: function(text) {
            const value = String(text || '').trim();
            if (value.length <= 150) {
                return value;
            }

            return `${value.slice(0, 147).trim()}...`;
        },

        renderReviews: function($plans, reviews, fallbackCopy) {
            const self = this;
            const $wrap = $plans.find('[data-carousel-reviews-wrap]');
            const $track = $plans.find('[data-carousel-reviews-track]');
            const $dots = $plans.find('[data-carousel-review-dots]');
            const $fallback = $plans.find('[data-carousel-bind="social_copy"]');

            this.stopReviewRotation($plans);

            if (!reviews || !reviews.length) {
                $wrap.addClass('is-hidden');
                $track.empty();
                $dots.empty();
                $fallback.removeClass('is-hidden').text(fallbackCopy || '');
                return;
            }

            $fallback.addClass('is-hidden');
            $wrap.removeClass('is-hidden');
            $track.empty();
            $dots.empty();

            reviews.forEach(function(review, index) {
                const reviewer = self.formatReviewerName(review);
                const rating = self.buildStars(review.rating || 5);
                const text = self.truncateReview(review.text || '');
                $track.append(
                    `<article class="sffc-carousel-social-review${index === 0 ? ' is-active' : ''}" data-carousel-review-item ${index === 0 ? '' : 'hidden'}>` +
                        `<div class="sffc-carousel-social-review__head">` +
                            `<strong>${$('<div>').text(reviewer).html()}</strong>` +
                            `<div class="sffc-carousel-social-review__rating">${rating}</div>` +
                        `</div>` +
                        `<p>${$('<div>').text(text).html()}</p>` +
                    `</article>`
                );
                $dots.append(`<button type="button" class="sffc-carousel-social-review-dot${index === 0 ? ' is-active' : ''}" data-carousel-review-dot data-index="${index}" aria-label="Show review ${index + 1}"></button>`);
            });

            if (reviews.length > 1) {
                this.startReviewRotation($plans, reviews.length);
            }
        },

        startReviewRotation: function($plans, total) {
            const self = this;
            const interval = setInterval(function() {
                const current = parseInt($plans.attr('data-review-index') || '0', 10);
                const next = (current + 1) % total;
                self.showReviewAt($plans, next, false);
            }, 4000);

            this.reviewIntervals.set($plans.get(0), interval);
        },

        stopReviewRotation: function($plans) {
            const node = $plans.get(0);
            if (!node) {
                return;
            }

            const interval = this.reviewIntervals.get(node);
            if (interval) {
                clearInterval(interval);
                this.reviewIntervals.delete(node);
            }
        },

        showReviewAt: function($plans, index, restartTimer) {
            const $items = $plans.find('[data-carousel-review-item]');
            const $dots = $plans.find('[data-carousel-review-dot]');
            if (!$items.length) {
                return;
            }

            const safeIndex = Math.max(0, Math.min(index, $items.length - 1));
            $plans.attr('data-review-index', safeIndex);

            $items.each(function(itemIndex) {
                const isActive = itemIndex === safeIndex;
                $(this).toggleClass('is-active', isActive);
                if (isActive) {
                    $(this).removeAttr('hidden');
                } else {
                    $(this).attr('hidden', 'hidden');
                }
            });

            $dots.each(function(dotIndex) {
                $(this).toggleClass('is-active', dotIndex === safeIndex);
            });

            if (restartTimer && $items.length > 1) {
                this.stopReviewRotation($plans);
                this.startReviewRotation($plans, $items.length);
            }
        },

        buildStars: function(score) {
            const value = Math.max(0, Math.min(5, parseFloat(score) || 0));
            const filled = Math.floor(value);
            const half = (value - filled) >= 0.5 ? 1 : 0;
            const empty = 5 - filled - half;
            let html = '';

            for (let i = 0; i < filled; i++) {
                html += '<span class="sffc-carousel-star is-filled" aria-hidden="true">★</span>';
            }

            if (half) {
                html += '<span class="sffc-carousel-star is-half" aria-hidden="true">★</span>';
            }

            for (let i = 0; i < empty; i++) {
                html += '<span class="sffc-carousel-star" aria-hidden="true">★</span>';
            }

            return html;
        },

        showCheckout: function($plans) {
            $plans.addClass('is-showing-form');
            $plans.find('[data-carousel-browser-front]').attr('hidden', 'hidden').removeClass('is-active');
            $plans.find('[data-carousel-browser-back]').removeAttr('hidden').addClass('is-active');
        },

        showFront: function($plans) {
            $plans.removeClass('is-showing-form');
            $plans.find('[data-carousel-browser-back]').attr('hidden', 'hidden').removeClass('is-active');
            $plans.find('[data-carousel-browser-front]').removeAttr('hidden').addClass('is-active');
        },

        toggleOtherPlans: function($plans) {
            const $panel = $plans.find('[data-carousel-other-plans]');
            const $button = $plans.find('[data-carousel-other-toggle]');
            const isOpen = !$panel.attr('hidden');
            if (isOpen) {
                $panel.attr('hidden', 'hidden');
                $button.attr('aria-expanded', 'false');
            } else {
                $panel.removeAttr('hidden');
                $button.attr('aria-expanded', 'true');
            }
        },

        showMembershipModal: function() {
            const count = this.selectedPosts.size;
            const $wrapper = $('.sffc-recruiter-carousel-wrapper').first();

            const modal = `
                <div class="sffc-carousel-membership-modal" id="sffc-membership-modal">
                    <div class="sffc-carousel-modal-overlay"></div>
                    <div class="sffc-carousel-modal-content">
                        <button class="sffc-carousel-modal-close" id="sffc-close-modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>

                        <div class="sffc-carousel-modal-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 6v6l4 2"/>
                            </svg>
                        </div>

                        <h2>Unlock Direct Recruiter Access</h2>
                        <p class="sffc-carousel-modal-subtitle">You've selected ${count} recruiter${count !== 1 ? 's' : ''}. Join MENA Careers to reach them all with one click!</p>

                        <div class="sffc-carousel-modal-benefits">
                            <div class="sffc-carousel-modal-benefit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                <div>
                                    <h4>Direct Access to Hiring Managers</h4>
                                    <p>Skip HR and speak directly to decision makers</p>
                                </div>
                            </div>
                            <div class="sffc-carousel-modal-benefit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                <div>
                                    <h4>AI-Powered Personalization</h4>
                                    <p>Generate tailored messages for each recruiter instantly</p>
                                </div>
                            </div>
                            <div class="sffc-carousel-modal-benefit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                <div>
                                    <h4>10x More Responses</h4>
                                    <p>Members get 10x more responses than traditional job boards</p>
                                </div>
                            </div>
                            <div class="sffc-carousel-modal-benefit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                <div>
                                    <h4>Bulk Outreach Tools</h4>
                                    <p>Contact multiple recruiters simultaneously and track responses</p>
                                </div>
                            </div>
                        </div>

                        <div class="sffc-carousel-modal-stats">
                            <div class="sffc-carousel-modal-stat">
                                <div class="sffc-carousel-modal-stat-value">10x</div>
                                <div class="sffc-carousel-modal-stat-label">More Responses</div>
                            </div>
                            <div class="sffc-carousel-modal-stat">
                                <div class="sffc-carousel-modal-stat-value">5,000+</div>
                                <div class="sffc-carousel-modal-stat-label">Active Recruiters</div>
                            </div>
                            <div class="sffc-carousel-modal-stat">
                                <div class="sffc-carousel-modal-stat-value">2.5x</div>
                                <div class="sffc-carousel-modal-stat-label">Faster Hiring</div>
                            </div>
                        </div>

                        <div class="sffc-carousel-modal-actions">
                            <a href="https://joinsenna.com/memberships/" class="sffc-carousel-modal-btn sffc-carousel-modal-btn--primary">
                                Join MENA Careers Premium
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                    <polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </a>
                            <button class="sffc-carousel-modal-btn sffc-carousel-modal-btn--secondary" id="sffc-close-modal-btn">
                                Maybe Later
                            </button>
                        </div>
                    </div>
                </div>
            `;

            if ($wrapper.length) {
                $wrapper.append(modal);
            } else {
                $('body').append(modal);
            }

            setTimeout(function() {
                $('#sffc-membership-modal').addClass('show');
            }, 10);
        },

        scrollToPlans: function() {
            const $target = $('#sffcCarouselPlans');
            if ($target.length) {
                const offset = $target.offset().top - 60;
                $('html, body').animate({ scrollTop: offset }, 600);
                return true;
            }
            return false;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RecruiterCarousel.init();
    });

    // Global modal close handling
    $(document).on('click', '.sffc-carousel-modal-overlay, #sffc-close-modal, #sffc-close-modal-btn, .sffc-carousel-modal-close', function() {
        const $modal = $(this).closest('.sffc-carousel-membership-modal');
        if ($modal.length) {
            $modal.removeClass('show');
            setTimeout(function() {
                $modal.remove();
            }, 300);
        }
    });

})(jQuery);
