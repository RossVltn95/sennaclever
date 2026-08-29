/**
 * Institutional Article Terminal
 * Premium split-screen research interface
 *
 * Features:
 * - Resizable panels with drag handle
 * - localStorage persistence
 * - Sticky MENA Careers chatbox integration
 * - Institutional chart rendering
 * - Mobile responsive collapse
 */

(function() {
    'use strict';

    const CONFIG = {
        STORAGE_KEY: 'inst_panel_ratio',
        DEFAULT_RATIO: 0.55,
        MIN_ARTICLE_WIDTH: 420,
        MIN_CHARTS_WIDTH: 380,
        MOBILE_BREAKPOINT: 1024,
        SIDEBAR_WIDTH: 48
    };

    /**
     * Main Terminal Controller
     */
    class InstitutionalTerminal {
        constructor(container) {
            this.container = container;
            this.main = container.querySelector('.inst-main');
            this.articlePanel = container.querySelector('.inst-article-panel');
            this.resizeHandle = container.querySelector('.inst-resize-handle');
            this.chartsPanel = container.querySelector('.inst-charts-panel');
            this.chatbox = container.querySelector('.inst-chatbox');
            this.layoutRefreshAttempts = 0;
            this.layoutResizeObserver = null;

            if (!this.articlePanel || !this.chartsPanel) {
                console.warn('Institutional Terminal: Missing required panels');
                return;
            }

            this.container.__instTerminal = this;
            this.isDragging = false;
            this.startX = 0;
            this.startWidth = 0;

            this.init();
        }

        init() {
            if (window.innerWidth <= CONFIG.MOBILE_BREAKPOINT) {
                this.setMobileLayout();
            } else {
                this.loadSavedRatio();
                this.bindResizeEvents();
            }

            this.bindWindowResize();
            this.bindLayoutObserver();
            this.refreshLayoutWhenVisible();
            this.initCharts();
            this.initChatbox();
            this.initSidebarActions();
            this.initViewToggle();
            this.initReportSections();
            this.initPrintButton();
            this.initPdfDownload();
            this.initExcelDownload();
        }

        // ========================================
        // RESIZE FUNCTIONALITY
        // ========================================

        loadSavedRatio() {
            const saved = localStorage.getItem(CONFIG.STORAGE_KEY);
            const ratio = saved ? parseFloat(saved) : CONFIG.DEFAULT_RATIO;
            this.applyRatio(ratio);
        }

        saveRatio(ratio) {
            localStorage.setItem(CONFIG.STORAGE_KEY, ratio.toString());
        }

        applyRatio(ratio) {
            if (!this.main) return;

            const mainWidth = this.main.offsetWidth;
            if (!mainWidth || mainWidth <= 0) {
                return;
            }
            const minArticleRatio = CONFIG.MIN_ARTICLE_WIDTH / mainWidth;
            const maxArticleRatio = 1 - (CONFIG.MIN_CHARTS_WIDTH / mainWidth) - 0.02;

            ratio = Math.max(minArticleRatio, Math.min(maxArticleRatio, ratio));

            this.articlePanel.style.flex = `0 0 ${ratio * 100}%`;
            this.chartsPanel.style.flex = `0 0 ${(1 - ratio) * 100 - 1}%`;
        }

        refreshLayoutWhenVisible() {
            if (window.innerWidth <= CONFIG.MOBILE_BREAKPOINT) {
                this.setMobileLayout();
                return;
            }

            if (!this.main || this.main.offsetWidth <= 0) {
                if (this.layoutRefreshAttempts < 12) {
                    this.layoutRefreshAttempts += 1;
                    window.setTimeout(() => this.refreshLayoutWhenVisible(), 80);
                }
                return;
            }

            this.layoutRefreshAttempts = 0;
            this.loadSavedRatio();
        }

        bindLayoutObserver() {
            if (!this.main || typeof ResizeObserver === 'undefined') {
                return;
            }

            this.layoutResizeObserver = new ResizeObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.contentRect && entry.contentRect.width > 0 && window.innerWidth > CONFIG.MOBILE_BREAKPOINT) {
                        this.loadSavedRatio();
                    }
                });
            });

            this.layoutResizeObserver.observe(this.main);
        }

        bindResizeEvents() {
            if (!this.resizeHandle) return;

            // Mouse events
            this.resizeHandle.addEventListener('mousedown', (e) => this.onDragStart(e));
            document.addEventListener('mousemove', (e) => this.onDragMove(e));
            document.addEventListener('mouseup', () => this.onDragEnd());

            // Touch events
            this.resizeHandle.addEventListener('touchstart', (e) => this.onDragStart(e), { passive: false });
            document.addEventListener('touchmove', (e) => this.onDragMove(e), { passive: false });
            document.addEventListener('touchend', () => this.onDragEnd());

            // Double-click to reset
            this.resizeHandle.addEventListener('dblclick', () => {
                this.applyRatio(CONFIG.DEFAULT_RATIO);
                this.saveRatio(CONFIG.DEFAULT_RATIO);
            });
        }

        onDragStart(e) {
            if (window.innerWidth <= CONFIG.MOBILE_BREAKPOINT) return;

            e.preventDefault();
            this.isDragging = true;
            this.resizeHandle.classList.add('is-dragging');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
            this.startX = clientX;
            this.startWidth = this.articlePanel.offsetWidth;
        }

        onDragMove(e) {
            if (!this.isDragging) return;

            e.preventDefault();
            const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
            const deltaX = clientX - this.startX;
            const mainWidth = this.main.offsetWidth;
            const newWidth = this.startWidth + deltaX;
            const newRatio = newWidth / mainWidth;

            this.applyRatio(newRatio);
        }

        onDragEnd() {
            if (!this.isDragging) return;

            this.isDragging = false;
            this.resizeHandle.classList.remove('is-dragging');
            document.body.style.cursor = '';
            document.body.style.userSelect = '';

            const mainWidth = this.main.offsetWidth;
            const ratio = this.articlePanel.offsetWidth / mainWidth;
            this.saveRatio(ratio);
        }

        bindWindowResize() {
            let timeout;
            window.addEventListener('resize', () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    if (window.innerWidth <= CONFIG.MOBILE_BREAKPOINT) {
                        this.setMobileLayout();
                    } else {
                        this.loadSavedRatio();
                    }
                }, 100);
            });
        }

        setMobileLayout() {
            if (this.articlePanel) this.articlePanel.style.flex = '';
            if (this.chartsPanel) this.chartsPanel.style.flex = '';
        }

        // ========================================
        // VIEW TOGGLE (Report / Data)
        // ========================================

        initViewToggle() {
            const toggleContainer = this.container.querySelector('.inst-view-toggle');
            if (!toggleContainer) return;

            const toggleBtns = toggleContainer.querySelectorAll('.inst-view-toggle-btn');
            const reportView = this.container.querySelector('#inst-report-view');
            // Support both data view and toolkit view
            const dataView = this.container.querySelector('#inst-data-view') || this.container.querySelector('#inst-toolkit-view');
            const legend = this.container.querySelector('.inst-view-toggle-legend');

            // Fall back to charts view for backwards compatibility
            const chartsView = reportView || this.container.querySelector('#inst-charts-view');
            if (!chartsView || !dataView) return;

            // Load saved preference - default to 'report' for new system
            const savedView = localStorage.getItem('inst_view_mode') || 'report';
            if (savedView === 'data') {
                this.switchView('data', toggleBtns, chartsView, dataView, legend);
            } else {
                this.switchView('report', toggleBtns, chartsView, dataView, legend);
            }

            toggleBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const view = btn.dataset.view;
                    this.switchView(view, toggleBtns, chartsView, dataView, legend);
                    localStorage.setItem('inst_view_mode', view);
                });
            });
        }

        switchView(view, toggleBtns, reportView, dataView, legend) {
            // Update button states
            toggleBtns.forEach(btn => {
                const isActive = btn.dataset.view === view;
                btn.classList.toggle('is-active', isActive);
                btn.setAttribute('aria-selected', isActive.toString());
            });

            // Toggle views with smooth transition
            if (view === 'report' || view === 'charts') {
                dataView.style.display = 'none';
                reportView.style.display = 'block';
                reportView.classList.add('is-active');
                dataView.classList.remove('is-active');
                if (legend) legend.style.display = 'none';
            } else if (view === 'data' || view === 'toolkit') {
                reportView.style.display = 'none';
                dataView.style.display = 'block';
                dataView.classList.add('is-active');
                reportView.classList.remove('is-active');
                if (legend) legend.style.display = view === 'data' ? 'flex' : 'none';
            }
        }

        // ========================================
        // REPORT SECTIONS (Collapsible)
        // ========================================

        initReportSections() {
            const reportView = this.container.querySelector('#inst-report-view');
            if (!reportView) return;

            const sectionHeaders = reportView.querySelectorAll('.inst-report-section-header');

            sectionHeaders.forEach(header => {
                header.addEventListener('click', () => {
                    const section = header.closest('.inst-report-section');
                    if (!section) return;

                    // Toggle open state (CSS uses .is-open)
                    const isNowOpen = section.classList.toggle('is-open');

                    // Update aria-expanded
                    header.setAttribute('aria-expanded', isNowOpen.toString());
                });

                // Initialize ARIA attributes - all sections open by default
                header.setAttribute('aria-expanded', 'true');
                header.setAttribute('role', 'button');
                header.setAttribute('tabindex', '0');

                // Keyboard accessibility
                header.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        header.click();
                    }
                });
            });
        }

        // ========================================
        // PRINT BUTTON
        // ========================================

        initPrintButton() {
            const printBtn = this.container.querySelector('#inst-report-print-btn');
            if (!printBtn) return;

            printBtn.addEventListener('click', () => {
                // Expand all sections before printing (add is-open to any that don't have it)
                const closedSections = this.container.querySelectorAll('.inst-report-section:not(.is-open)');
                closedSections.forEach(section => {
                    section.classList.add('is-open');
                    const header = section.querySelector('.inst-report-section-header');
                    if (header) header.setAttribute('aria-expanded', 'true');
                });

                // Brief delay to ensure sections expand
                setTimeout(() => {
                    window.print();
                }, 100);
            });
        }

        // ========================================
        // PDF DOWNLOAD (Browser-based)
        // ========================================

        initPdfDownload() {
            const pdfBtn = this.container.querySelector('#inst-pdf-download');
            if (!pdfBtn) return;

            pdfBtn.addEventListener('click', () => {
                this.generatePdf();
            });
        }

        generatePdf() {
            const pdfBtn = this.container.querySelector('#inst-pdf-download');
            const originalText = pdfBtn.innerHTML;

            // Show loading state
            pdfBtn.innerHTML = `
                <svg class="inst-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" stroke-dasharray="31.4" stroke-dashoffset="10"/>
                </svg>
                <span>Generating...</span>
            `;
            pdfBtn.disabled = true;

            // Get the content to print
            const chartsPanel = this.container.querySelector('.inst-charts-panel');
            const articleTitle = this.container.querySelector('.inst-article-title')?.textContent || 'Report';

            // Create a clean filename
            const filename = articleTitle
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/-+/g, '-')
                .substring(0, 50) + '-report.pdf';

            // Use browser print dialog with PDF option
            // Set up print-specific styles
            document.body.classList.add('inst-printing');

            setTimeout(() => {
                window.print();

                // Restore button after print dialog closes
                setTimeout(() => {
                    document.body.classList.remove('inst-printing');
                    pdfBtn.innerHTML = originalText;
                    pdfBtn.disabled = false;
                }, 500);
            }, 100);
        }

        // ========================================
        // EXCEL DOWNLOAD (Browser-based)
        // ========================================

        initExcelDownload() {
            const excelBtn = this.container.querySelector('#inst-excel-download');
            if (!excelBtn) return;

            excelBtn.addEventListener('click', () => {
                this.generateExcel();
            });
        }

        generateExcel() {
            const excelBtn = this.container.querySelector('#inst-excel-download');
            const originalText = excelBtn.innerHTML;

            // Show loading state
            excelBtn.innerHTML = `
                <svg class="inst-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" stroke-dasharray="31.4" stroke-dashoffset="10"/>
                </svg>
                <span>Exporting...</span>
            `;
            excelBtn.disabled = true;

            try {
                // Get the data table
                const table = this.container.querySelector('.inst-data-table');
                if (!table) {
                    throw new Error('Data table not found');
                }

                // Get article title for filename
                const articleTitle = this.container.querySelector('.inst-article-title')?.textContent || 'Data Export';
                const filename = articleTitle
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/-+/g, '-')
                    .substring(0, 50) + '-data.xlsx';

                // Extract data from table
                const rows = [];
                const tableRows = table.querySelectorAll('tbody tr');

                tableRows.forEach(tr => {
                    const cells = tr.querySelectorAll('td');
                    if (cells.length >= 3) {
                        // Skip row number (first cell), get columns A, B, C
                        const rowData = [];
                        for (let i = 1; i < cells.length; i++) {
                            let cellText = cells[i].textContent.trim();
                            // Handle colspan
                            const colspan = cells[i].getAttribute('colspan');
                            rowData.push(cellText);
                            if (colspan && parseInt(colspan) > 1) {
                                for (let j = 1; j < parseInt(colspan); j++) {
                                    rowData.push('');
                                }
                            }
                        }
                        rows.push(rowData);
                    }
                });

                // Generate Excel XML (works without external libraries)
                const excelContent = this.generateExcelXML(rows, articleTitle);

                // Create and download the file
                const blob = new Blob([excelContent], {
                    type: 'application/vnd.ms-excel;charset=utf-8'
                });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename.replace('.xlsx', '.xls');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

            } catch (error) {
                console.error('Excel export failed:', error);
                alert('Failed to export data. Please try again.');
            }

            // Restore button
            setTimeout(() => {
                excelBtn.innerHTML = originalText;
                excelBtn.disabled = false;
            }, 500);
        }

        generateExcelXML(rows, title) {
            // Generate Excel-compatible XML with proper structure
            let xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
            xml += '<?mso-application progid="Excel.Sheet"?>\n';
            xml += '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"\n';
            xml += '  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">\n';
            xml += '  <Styles>\n';
            // Title style - large, bold, dark background
            xml += '    <Style ss:ID="title">\n';
            xml += '      <Font ss:Bold="1" ss:Size="14" ss:Color="#FFFFFF"/>\n';
            xml += '      <Interior ss:Color="#1a365d" ss:Pattern="Solid"/>\n';
            xml += '      <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>\n';
            xml += '    </Style>\n';
            // Section header style - bold, colored background
            xml += '    <Style ss:ID="section">\n';
            xml += '      <Font ss:Bold="1" ss:Size="11" ss:Color="#1a365d"/>\n';
            xml += '      <Interior ss:Color="#e2e8f0" ss:Pattern="Solid"/>\n';
            xml += '      <Borders>\n';
            xml += '        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94a3b8"/>\n';
            xml += '      </Borders>\n';
            xml += '    </Style>\n';
            // Column header style - bold, light background
            xml += '    <Style ss:ID="colheader">\n';
            xml += '      <Font ss:Bold="1" ss:Size="10" ss:Color="#475569"/>\n';
            xml += '      <Interior ss:Color="#f1f5f9" ss:Pattern="Solid"/>\n';
            xml += '      <Borders>\n';
            xml += '        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#cbd5e1"/>\n';
            xml += '      </Borders>\n';
            xml += '    </Style>\n';
            // Data style - normal
            xml += '    <Style ss:ID="data">\n';
            xml += '      <Font ss:Size="10"/>\n';
            xml += '      <Alignment ss:Vertical="Center"/>\n';
            xml += '    </Style>\n';
            // Value style - for numbers, right aligned
            xml += '    <Style ss:ID="value">\n';
            xml += '      <Font ss:Size="10" ss:Color="#0f172a"/>\n';
            xml += '      <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>\n';
            xml += '    </Style>\n';
            // Source style - italic, gray
            xml += '    <Style ss:ID="source">\n';
            xml += '      <Font ss:Size="9" ss:Italic="1" ss:Color="#64748b"/>\n';
            xml += '    </Style>\n';
            // Spacer style
            xml += '    <Style ss:ID="spacer">\n';
            xml += '      <Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>\n';
            xml += '    </Style>\n';
            xml += '  </Styles>\n';
            xml += '  <Worksheet ss:Name="Data Extract">\n';
            xml += '    <Table ss:DefaultRowHeight="18">\n';
            xml += '      <Column ss:Width="220"/>\n';
            xml += '      <Column ss:Width="140"/>\n';
            xml += '      <Column ss:Width="100"/>\n';

            // Add title row
            xml += '      <Row ss:Height="28">\n';
            xml += `        <Cell ss:StyleID="title" ss:MergeAcross="2"><Data ss:Type="String">${this.escapeXml(title)}</Data></Cell>\n`;
            xml += '      </Row>\n';

            // Add date row
            const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            xml += '      <Row ss:Height="16">\n';
            xml += `        <Cell ss:StyleID="source" ss:MergeAcross="2"><Data ss:Type="String">Exported: ${today} | Source: MENA Careers Research</Data></Cell>\n`;
            xml += '      </Row>\n';

            // Add spacer
            xml += '      <Row ss:Height="10"><Cell ss:StyleID="spacer"/></Row>\n';

            rows.forEach((row, index) => {
                const firstCell = (row[0] || '').trim();
                const isEmptyRow = !firstCell || row.every(c => !c || !c.trim());

                // Skip empty spacer rows but add a small gap
                if (isEmptyRow) {
                    xml += '      <Row ss:Height="8"><Cell ss:StyleID="spacer"/></Row>\n';
                    return;
                }

                // Detect row type
                const isSectionHeader = this.isSectionHeader(firstCell);
                const isColumnHeader = this.isColumnHeader(firstCell);
                const isSourceRow = firstCell.toLowerCase() === 'source' || firstCell.toLowerCase().includes('source:');

                if (isSectionHeader) {
                    // Section header - spans all columns
                    xml += '      <Row ss:Height="24">\n';
                    xml += `        <Cell ss:StyleID="section" ss:MergeAcross="2"><Data ss:Type="String">${this.escapeXml(firstCell)}</Data></Cell>\n`;
                    xml += '      </Row>\n';
                } else if (isColumnHeader) {
                    // Column headers
                    xml += '      <Row ss:Height="20">\n';
                    row.forEach(cell => {
                        xml += `        <Cell ss:StyleID="colheader"><Data ss:Type="String">${this.escapeXml(cell)}</Data></Cell>\n`;
                    });
                    xml += '      </Row>\n';
                } else if (isSourceRow) {
                    // Source attribution row
                    xml += '      <Row ss:Height="16">\n';
                    xml += `        <Cell ss:StyleID="source"><Data ss:Type="String">${this.escapeXml(firstCell)}</Data></Cell>\n`;
                    if (row[1]) {
                        xml += `        <Cell ss:StyleID="source" ss:MergeAcross="1"><Data ss:Type="String">${this.escapeXml(row[1])}</Data></Cell>\n`;
                    }
                    xml += '      </Row>\n';
                } else {
                    // Regular data row
                    xml += '      <Row ss:Height="18">\n';
                    row.forEach((cell, cellIndex) => {
                        const escapedCell = this.escapeXml(cell);
                        const style = cellIndex === 1 ? 'value' : 'data';

                        // Check if it's a pure number for Excel number type
                        const cleanNum = cell.replace(/[$,%\s]/g, '');
                        const isNumber = /^-?\d+\.?\d*$/.test(cleanNum) && cell.match(/[\d]/);

                        if (isNumber && cellIndex === 1) {
                            xml += `        <Cell ss:StyleID="${style}"><Data ss:Type="String">${escapedCell}</Data></Cell>\n`;
                        } else {
                            xml += `        <Cell ss:StyleID="${style}"><Data ss:Type="String">${escapedCell}</Data></Cell>\n`;
                        }
                    });
                    xml += '      </Row>\n';
                }
            });

            // Add footer
            xml += '      <Row ss:Height="10"><Cell ss:StyleID="spacer"/></Row>\n';
            xml += '      <Row ss:Height="14">\n';
            xml += '        <Cell ss:StyleID="source" ss:MergeAcross="2"><Data ss:Type="String">Generated by MENA Careers Intelligence Platform</Data></Cell>\n';
            xml += '      </Row>\n';

            xml += '    </Table>\n';
            xml += '  </Worksheet>\n';
            xml += '</Workbook>';

            return xml;
        }

        isSectionHeader(text) {
            if (!text) return false;
            const sectionKeywords = [
                'Key Metrics',
                'Article Info',
                'Classification',
                'Portfolio',
                'Allocation',
                'Winners',
                'Spend',
                'Revenue',
                'Market',
                'Analysis'
            ];
            // Check if it's a chart title (ends with specific patterns or contains keywords)
            return sectionKeywords.some(kw => text.includes(kw)) ||
                   /^[A-Z].*(?:Portfolio|Allocation|Distribution|Breakdown|Analysis|Overview|Data|Metrics|Winners|Performance)$/i.test(text) ||
                   (text.length > 15 && !text.includes(':') && /^[A-Z]/.test(text));
        }

        isColumnHeader(text) {
            if (!text) return false;
            const columnKeywords = ['Metric', 'Item', 'Value', 'Type', 'Note', 'Label', 'Category', 'Name'];
            return columnKeywords.includes(text);
        }

        escapeXml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&apos;');
        }

        // ========================================
        // SIDEBAR ACTIONS
        // ========================================

        initSidebarActions() {
            const sidebar = this.container.querySelector('.inst-sidebar');
            if (!sidebar) return;

            const saveBtn = sidebar.querySelector('[data-action="save"]');
            const shareBtn = sidebar.querySelector('[data-action="share"]');
            const analyzeBtn = sidebar.querySelector('[data-action="analyze"]');

            if (saveBtn) {
                saveBtn.addEventListener('click', () => this.handleSave());
            }

            if (shareBtn) {
                shareBtn.addEventListener('click', () => this.handleShare());
            }

            if (analyzeBtn) {
                analyzeBtn.addEventListener('click', () => this.handleAnalyze());
            }
        }

        handleSave() {
            const postId = this.container.dataset.postId;
            // Trigger save functionality - integrate with existing SFFC save system
            if (window.SFFC && window.SFFC.saveArticle) {
                window.SFFC.saveArticle(postId);
            } else {
                console.log('Save article:', postId);
                this.showToast('Article saved');
            }
        }

        handleShare() {
            const url = window.location.href;
            const title = document.title;

            if (navigator.share) {
                navigator.share({ title, url }).catch(() => {});
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    this.showToast('Link copied');
                });
            }
        }

        handleAnalyze() {
            // Open chatbox and focus input
            if (this.chatbox) {
                this.chatbox.classList.add('is-expanded');
                this.chatbox.classList.add('is-open');
                const input = this.chatbox.querySelector('.inst-chatbox-input');
                if (input) input.focus();
            }
        }

        showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'inst-toast';
            toast.textContent = message;
            toast.style.cssText = `
                position: fixed;
                bottom: 100px;
                left: 50%;
                transform: translateX(-50%);
                padding: 10px 20px;
                background: #0a1628;
                color: #fff;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 500;
                z-index: 1000;
                animation: instFadeIn 0.3s ease;
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        }

        // ========================================
        // CHATBOX
        // ========================================

        initChatbox() {
            if (!this.chatbox) return;

            const header = this.chatbox.querySelector('.inst-chatbox-header');
            const prompts = this.chatbox.querySelectorAll('.inst-chatbox-prompt');
            const input = this.chatbox.querySelector('.inst-chatbox-input');
            const sendBtn = this.chatbox.querySelector('.inst-chatbox-send');

            // Toggle expand/collapse (backup for inline onclick)
            if (header) {
                header.addEventListener('click', (e) => {
                    // Don't toggle if clicking on input or buttons inside
                    if (e.target.closest('.inst-chatbox-body')) return;
                    this.chatbox.classList.toggle('is-expanded');
                });
            }

            // Quick prompts
            prompts.forEach(prompt => {
                prompt.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const text = prompt.dataset.prompt || prompt.textContent.trim();
                    this.sendToSenna(text);
                    // Keep expanded to show response
                    this.chatbox.classList.add('is-expanded');
                });
            });

            // Input handling
            if (input) {
                // Auto-expand when focusing input
                input.addEventListener('focus', () => {
                    this.chatbox.classList.add('is-expanded');
                });

                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.submitChatInput();
                    }
                });
            }

            // Send button
            if (sendBtn) {
                sendBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.submitChatInput();
                });
            }

            // Close on outside click (mobile)
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 1024 &&
                    this.chatbox.classList.contains('is-expanded') &&
                    !this.chatbox.contains(e.target)) {
                    this.chatbox.classList.remove('is-expanded');
                }
            });
        }

        submitChatInput() {
            const input = this.chatbox.querySelector('.inst-chatbox-input');
            if (!input) return;

            const text = input.value.trim();
            if (text) {
                this.sendToSenna(text);
                input.value = '';
                // Keep expanded to show response
                this.chatbox.classList.add('is-expanded');
            }
        }

        sendToSenna(message) {
            // Get article context
            const title = this.container.querySelector('.inst-article-title')?.textContent || '';
            const postId = this.container.dataset.postId;
            const articleContent = this.container.querySelector('.inst-article-body')?.textContent?.substring(0, 2000) || '';

            // Show loading state in chatbox
            this.showChatLoading();

            // Build context-aware message
            const contextMessage = `[Article Context: "${title}"]\n\nUser Question: ${message}`;

            // Try different chat integrations
            if (window.SennaChat && typeof window.SennaChat.send === 'function') {
                // Use SennaChat API
                window.SennaChat.open();
                window.SennaChat.send(message, {
                    context: {
                        articleTitle: title,
                        postId: postId,
                        articleExcerpt: articleContent.substring(0, 500)
                    }
                });
                this.hideChatLoading();
            } else if (window.SFFCChat && typeof window.SFFCChat.sendMessage === 'function') {
                // Use SFFCChat API
                window.SFFCChat.sendMessage(message, {
                    context: { articleTitle: title, postId: postId }
                });
                this.hideChatLoading();
            } else {
                // Fallback: Use AJAX to get AI response
                this.fetchAIResponse(message, title, postId, articleContent);
            }
        }

        showChatLoading() {
            const body = this.chatbox?.querySelector('.inst-chatbox-body');
            if (!body) return;

            // Add response area if not exists
            let responseArea = body.querySelector('.inst-chatbox-response');
            if (!responseArea) {
                responseArea = document.createElement('div');
                responseArea.className = 'inst-chatbox-response';
                responseArea.style.cssText = `
                    padding: 12px;
                    margin-bottom: 12px;
                    background: #fff;
                    border: 1px solid #e4e4e7;
                    border-radius: 12px;
                    font-size: 14px;
                    line-height: 1.6;
                    color: #18181b;
                    max-height: 200px;
                    overflow-y: auto;
                `;
                body.insertBefore(responseArea, body.firstChild);
            }

            responseArea.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px; color: #71717a;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                    <span>Analyzing...</span>
                </div>
                <style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
            `;
        }

        hideChatLoading() {
            const responseArea = this.chatbox?.querySelector('.inst-chatbox-response');
            if (responseArea && responseArea.textContent.includes('Analyzing')) {
                responseArea.remove();
            }
        }

        showChatResponse(response) {
            const body = this.chatbox?.querySelector('.inst-chatbox-body');
            if (!body) return;

            let responseArea = body.querySelector('.inst-chatbox-response');
            if (!responseArea) {
                responseArea = document.createElement('div');
                responseArea.className = 'inst-chatbox-response';
                responseArea.style.cssText = `
                    padding: 12px;
                    margin-bottom: 12px;
                    background: #fff;
                    border: 1px solid #e4e4e7;
                    border-radius: 12px;
                    font-size: 14px;
                    line-height: 1.6;
                    color: #18181b;
                    max-height: 200px;
                    overflow-y: auto;
                `;
                body.insertBefore(responseArea, body.firstChild);
            }

            responseArea.innerHTML = response;
            this.chatbox.classList.add('is-expanded');
        }

        fetchAIResponse(message, title, postId, articleContent) {
            // Use WordPress AJAX
            const ajaxUrl = window.sffc_frontend?.ajaxUrl || '/wp-admin/admin-ajax.php';
            const nonce = window.sffc_frontend?.nonce || '';

            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'sffc_article_chat',
                    nonce: nonce,
                    message: message,
                    post_id: postId,
                    article_title: title,
                    article_excerpt: articleContent.substring(0, 1000)
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data?.response) {
                    this.showChatResponse(data.data.response);
                } else {
                    this.showChatResponse(`<p>I can help you understand this article about <strong>${title}</strong>. The AI service is currently being set up. Please try again shortly.</p>`);
                }
            })
            .catch(() => {
                this.showChatResponse(`<p>I can help you understand this article about <strong>${title}</strong>. The AI service is currently being set up. Please try again shortly.</p>`);
            });
        }

        // ========================================
        // CHART RENDERING
        // ========================================

        initCharts() {
            this.renderLineCharts();
            this.renderBarCharts();
            this.renderDonutCharts();
        }

        renderLineCharts() {
            const charts = this.container.querySelectorAll('[data-chart="line"]');
            charts.forEach(el => this.renderLineChart(el));
        }

        renderLineChart(container) {
            const dataAttr = container.dataset.chartData;
            if (!dataAttr) return;

            try {
                const data = JSON.parse(dataAttr);
                const points = data.points || [];
                if (points.length < 2) return;

                const width = 500;
                const height = 220;
                const padding = { top: 30, right: 60, bottom: 45, left: 50 };
                const chartWidth = width - padding.left - padding.right;
                const chartHeight = height - padding.top - padding.bottom;

                const values = points.map(p => p.value);
                const maxValue = Math.max(...values);
                const minValue = Math.min(...values);
                const range = maxValue - minValue || 1;

                // Build path
                let pathD = '';
                let areaD = '';
                let dotsHTML = '';
                const xStep = chartWidth / (points.length - 1);

                points.forEach((point, i) => {
                    const x = padding.left + i * xStep;
                    const y = padding.top + chartHeight - ((point.value - minValue) / range) * chartHeight;

                    if (i === 0) {
                        pathD = `M ${x} ${y}`;
                        areaD = `M ${x} ${height - padding.bottom} L ${x} ${y}`;
                    } else {
                        pathD += ` L ${x} ${y}`;
                        areaD += ` L ${x} ${y}`;
                    }

                    const isLast = i === points.length - 1;
                    const dotClass = isLast ? 'inst-line-dot inst-line-dot-highlight' : 'inst-line-dot';
                    dotsHTML += `<circle class="${dotClass}" cx="${x}" cy="${y}" r="${isLast ? 5 : 4}" />`;

                    // Value label on last point
                    if (isLast && data.showEndValue !== false) {
                        dotsHTML += `
                            <text class="inst-line-value-label" x="${x + 8}" y="${y - 8}">${point.value}${data.suffix || ''}</text>
                            ${data.annotation ? `<text class="inst-line-annotation" x="${x + 8}" y="${y + 8}">${data.annotation}</text>` : ''}
                        `;
                    }
                });

                areaD += ` L ${padding.left + (points.length - 1) * xStep} ${height - padding.bottom} Z`;

                // Grid lines
                let gridHTML = '';
                const gridCount = 4;
                for (let i = 0; i <= gridCount; i++) {
                    const y = padding.top + (i / gridCount) * chartHeight;
                    const val = Math.round(maxValue - (i / gridCount) * range);
                    gridHTML += `
                        <line class="inst-line-grid" x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" />
                        <text class="inst-line-label" x="${padding.left - 10}" y="${y + 4}" text-anchor="end">${val}</text>
                    `;
                }

                // X-axis labels
                let xLabelsHTML = '';
                points.forEach((point, i) => {
                    if (point.label) {
                        const x = padding.left + i * xStep;
                        xLabelsHTML += `<text class="inst-line-label" x="${x}" y="${height - 15}" text-anchor="middle">${point.label}</text>`;
                    }
                });

                container.innerHTML = `
                    <div class="inst-line-chart">
                        <svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMidYMid meet">
                            <defs>
                                <linearGradient id="lineGradient-${Date.now()}" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" style="stop-color:#2563eb;stop-opacity:0.15" />
                                    <stop offset="100%" style="stop-color:#2563eb;stop-opacity:0" />
                                </linearGradient>
                            </defs>
                            ${gridHTML}
                            <path class="inst-line-area" d="${areaD}" fill="url(#lineGradient-${Date.now()})" />
                            <path class="inst-line-path" d="${pathD}" />
                            ${dotsHTML}
                            ${xLabelsHTML}
                        </svg>
                    </div>
                `;
            } catch (e) {
                console.warn('Line chart error:', e);
            }
        }

        renderBarCharts() {
            const charts = this.container.querySelectorAll('[data-chart="bar"]');
            charts.forEach(el => this.renderBarChart(el));
        }

        renderBarChart(container) {
            const dataAttr = container.dataset.chartData;
            if (!dataAttr) return;

            try {
                const data = JSON.parse(dataAttr);
                const series = data.series || [];
                const maxValue = Math.max(...series.map(s => s.value));
                const suffix = data.suffix || '';

                let html = '<div class="inst-bar-chart">';

                series.forEach((item, i) => {
                    const pct = (item.value / maxValue) * 100;
                    const fillClass = item.highlighted ? ' is-highlight' : (i === 0 ? ' is-primary' : '');

                    html += `
                        <div class="inst-bar-row">
                            <div class="inst-bar-label" title="${item.label}">${item.label}</div>
                            <div class="inst-bar-track">
                                <div class="inst-bar-fill${fillClass}" style="width: ${pct}%"></div>
                            </div>
                            <div class="inst-bar-value">${item.value}${suffix}</div>
                        </div>
                    `;
                });

                html += '</div>';
                container.innerHTML = html;
            } catch (e) {
                console.warn('Bar chart error:', e);
            }
        }

        renderDonutCharts() {
            const charts = this.container.querySelectorAll('[data-chart="donut"]');
            charts.forEach(el => this.renderDonutChart(el));
        }

        renderDonutChart(container) {
            const dataAttr = container.dataset.chartData;
            if (!dataAttr) return;

            try {
                const data = JSON.parse(dataAttr);
                const slices = data.slices || [];
                const total = slices.reduce((sum, s) => sum + s.value, 0);

                const radius = 52;
                const strokeWidth = 24;
                const circumference = 2 * Math.PI * radius;
                const viewBox = (radius + strokeWidth / 2 + 4) * 2;
                const center = viewBox / 2;

                const colors = ['#0f2137', '#2563eb', '#60a5fa', '#a1a1aa', '#d4d4d8'];

                let offset = 0;
                let segmentsHTML = '';
                let legendHTML = '';

                slices.forEach((slice, i) => {
                    const pct = slice.value / total;
                    const length = circumference * pct;
                    const gap = circumference - length;
                    const color = colors[i % colors.length];

                    segmentsHTML += `
                        <circle
                            class="inst-donut-segment"
                            cx="${center}" cy="${center}" r="${radius}"
                            stroke="${color}"
                            stroke-dasharray="${length} ${gap}"
                            stroke-dashoffset="${-offset}"
                        />
                    `;

                    legendHTML += `
                        <div class="inst-legend-item">
                            <div class="inst-legend-color" style="background: ${color}"></div>
                            <span class="inst-legend-label">${slice.label}</span>
                            <span class="inst-legend-value">${slice.value}%</span>
                        </div>
                    `;

                    offset += length;
                });

                container.innerHTML = `
                    <div class="inst-donut-chart">
                        <div class="inst-donut-visual">
                            <svg viewBox="0 0 ${viewBox} ${viewBox}">
                                ${segmentsHTML}
                            </svg>
                            <div class="inst-donut-center">
                                <div class="inst-donut-center-value">${data.centerValue || total}</div>
                                <div class="inst-donut-center-label">${data.centerLabel || 'Total'}</div>
                            </div>
                        </div>
                        <div class="inst-donut-legend">
                            ${legendHTML}
                        </div>
                    </div>
                `;
            } catch (e) {
                console.warn('Donut chart error:', e);
            }
        }
    }

    // ========================================
    // EXCEL MODEL DOWNLOAD
    // ========================================

    class DealModelDownloader {
        constructor() {
            this.init();
        }

        init() {
            const downloadBtn = document.getElementById('sffc-download-model-btn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', (e) => this.handleDownload(e));
            }
        }

        handleDownload(e) {
            e.preventDefault();
            const btn = e.currentTarget;

            // Prevent double-clicks
            if (btn.classList.contains('loading')) return;

            const postId = btn.dataset.postId;
            const nonce = btn.dataset.nonce;

            if (!postId || !nonce) {
                this.showError(btn, 'Invalid configuration');
                return;
            }

            // Set loading state
            btn.classList.add('loading');
            btn.disabled = true;

            // Make AJAX request
            const ajaxUrl = window.sffc_frontend?.ajaxUrl || '/wp-admin/admin-ajax.php';

            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'sffc_generate_deal_model',
                    nonce: nonce,
                    post_id: postId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data?.download_url) {
                    // Success - trigger download
                    this.triggerDownload(data.data.download_url, data.data.filename);
                    this.showSuccess(btn);
                } else {
                    this.showError(btn, data.data?.message || 'Failed to generate model');
                }
            })
            .catch(error => {
                console.error('Download error:', error);
                this.showError(btn, 'Network error. Please try again.');
            })
            .finally(() => {
                // Reset button after delay
                setTimeout(() => {
                    btn.classList.remove('loading', 'success', 'error');
                    btn.disabled = false;
                }, 3000);
            });
        }

        triggerDownload(url, filename) {
            const a = document.createElement('a');
            a.href = url;
            a.download = filename || 'Deal_Model.xlsx';
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        showSuccess(btn) {
            btn.classList.remove('loading');
            btn.classList.add('success');

            const textSpan = btn.querySelector('.inst-download-text');
            const originalText = textSpan?.textContent;
            if (textSpan) {
                textSpan.textContent = 'Download Complete';
            }

            // Reset text after delay
            setTimeout(() => {
                if (textSpan && originalText) {
                    textSpan.textContent = originalText;
                }
            }, 3000);
        }

        showError(btn, message) {
            btn.classList.remove('loading');
            btn.classList.add('error');

            const textSpan = btn.querySelector('.inst-download-text');
            const originalText = textSpan?.textContent;
            if (textSpan) {
                textSpan.textContent = message || 'Download Failed';
            }

            // Reset text after delay
            setTimeout(() => {
                if (textSpan && originalText) {
                    textSpan.textContent = originalText;
                }
            }, 3000);
        }
    }

    // ========================================
    // MODEL GENERATION HANDLER
    // ========================================

    class ModelGenerator {
        constructor() {
            this.generateBtn = document.getElementById('sffc-generate-model-btn');
            this.guideBtn = document.getElementById('sffc-generate-guide-btn');
            this.chartsPanel = document.querySelector('.inst-charts-panel');
            this.previewCard = document.getElementById('sffc-model-preview');
            this.generatedContent = document.getElementById('sffc-generated-content');
            this.generateCta = document.getElementById('sffc-generate-cta');

            if (this.generateBtn) {
                this.bindGenerateButton();
            }
            if (this.guideBtn) {
                this.bindGuideButton();
            }

            // Initialize panel state
            this.setPanelState('initial');
        }

        setPanelState(state) {
            if (this.chartsPanel) {
                this.chartsPanel.setAttribute('data-state', state);
            }
        }

        bindGenerateButton() {
            this.generateBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleGenerate();
            });
        }

        bindGuideButton() {
            this.guideBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleGuideDownload();
            });
        }

        async handleGenerate() {
            const btn = this.generateBtn;
            const postId = btn.dataset.postId;
            const modelType = btn.dataset.modelType;
            const nonce = btn.dataset.nonce;
            const isLoggedIn = btn.dataset.userLoggedIn === '1';

            if (!postId || !modelType) {
                console.error('Missing post ID or model type');
                return;
            }

            // For non-logged-in users, show email gate first
            if (!isLoggedIn) {
                this.showEmailGateModal();
                return;
            }

            // Set generating state
            this.setPanelState('generating');

            // Show loading state
            this.setButtonLoading(btn, true);

            try {
                const formData = new FormData();
                formData.append('action', 'sffc_generate_model');
                formData.append('post_id', postId);
                formData.append('model_type', modelType);
                formData.append('nonce', nonce);

                const response = await fetch(sffc_frontend?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    this.handleGenerationSuccess(btn, data.data);
                } else {
                    this.handleGenerationError(btn, data.data?.message || 'Generation failed');
                }
            } catch (error) {
                console.error('Generation error:', error);
                this.handleGenerationError(btn, 'Network error. Please try again.');
            }
        }

        handleGenerationSuccess(btn, data) {
            this.setButtonLoading(btn, false);

            // Set generated state
            this.setPanelState('generated');

            // Update button to show success and download option
            const textSpan = btn.querySelector('.inst-download-text');
            if (textSpan) {
                textSpan.textContent = 'Download Excel Model';
            }

            // Update button to trigger download instead of generate
            btn.classList.add('generated');
            btn.onclick = (e) => {
                e.preventDefault();
                this.downloadExcel();
            };

            // Show success message
            this.showNotification('Analysis generated successfully!', 'success');

            // Update generated content section
            if (data.analysis) {
                this.populateGeneratedContent(data.analysis);
            }
        }

        populateGeneratedContent(analysis) {
            // Update timestamp
            const timestampEl = document.getElementById('sffc-generated-timestamp');
            if (timestampEl) {
                const now = new Date();
                timestampEl.textContent = `Generated ${now.toLocaleTimeString()}`;
            }

            // Populate metrics grid
            const metricsContainer = document.getElementById('sffc-generated-metrics');
            if (metricsContainer && analysis.inputs) {
                let metricsHtml = '';
                const inputs = analysis.inputs;

                // Show key metrics based on model type
                const keyMetrics = [
                    { key: 'deal_value', label: 'Deal Value' },
                    { key: 'entry_multiple', label: 'Entry Multiple' },
                    { key: 'exit_multiple', label: 'Exit Multiple' },
                    { key: 'debt_ratio', label: 'Debt / Equity' },
                ];

                keyMetrics.forEach(metric => {
                    let value = inputs[metric.key];
                    if (value) {
                        if (typeof value === 'object' && value.amount) {
                            value = `${value.currency || '$'}${this.formatNumber(value.amount)}`;
                        } else if (typeof value === 'number') {
                            value = metric.key.includes('multiple') ? `${value.toFixed(1)}x` : `${value}%`;
                        }
                        metricsHtml += `
                            <div class="inst-generated-metric">
                                <div class="inst-generated-metric-value">${value}</div>
                                <div class="inst-generated-metric-label">${metric.label}</div>
                            </div>
                        `;
                    }
                });

                metricsContainer.innerHTML = metricsHtml;
            }

            // Populate summary
            const summaryContainer = document.getElementById('sffc-generated-summary');
            if (summaryContainer && analysis.summary) {
                summaryContainer.innerHTML = `<p>${analysis.summary}</p>`;
            }

            // Show generated content
            if (this.generatedContent) {
                this.generatedContent.style.display = 'block';
            }

            // Hide preview card
            if (this.previewCard) {
                this.previewCard.style.display = 'none';
            }
        }

        formatNumber(num) {
            if (num >= 1000000000) {
                return (num / 1000000000).toFixed(1) + 'B';
            } else if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        }

        handleGenerationError(btn, message) {
            this.setButtonLoading(btn, false);
            this.setPanelState('initial');
            this.showNotification(message, 'error');

            // Check for special error codes
            if (message.includes('limit reached')) {
                this.showUpgradeModal();
            } else if (message.includes('email required')) {
                this.showEmailGateModal();
            }
        }

        showEmailGateModal() {
            // Create the email gate modal
            const modal = document.createElement('div');
            modal.className = 'inst-email-gate-modal';
            modal.innerHTML = `
                <div class="inst-email-gate-content">
                    <button class="inst-email-gate-close" aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                    <div class="inst-email-gate-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="12" y1="18" x2="12" y2="12"/>
                            <polyline points="9 15 12 18 15 15"/>
                        </svg>
                    </div>
                    <h3 class="inst-email-gate-title">Get Your Financial Model</h3>
                    <p class="inst-email-gate-text">Enter your email to generate and download the complete Excel model with scenario analysis.</p>
                    <form class="inst-email-gate-form" id="sffc-email-gate-form">
                        <input type="email" name="email" placeholder="your@email.com" required class="inst-email-gate-input">
                        <button type="submit" class="inst-email-gate-submit">Generate Model</button>
                    </form>
                    <p class="inst-email-gate-terms">
                        By continuing, you agree to our <a href="/privacy-policy/">Privacy Policy</a>.
                    </p>
                </div>
            `;

            document.body.appendChild(modal);

            // Trigger visibility with animation
            requestAnimationFrame(() => {
                modal.classList.add('is-visible');
            });

            // Close button
            modal.querySelector('.inst-email-gate-close').addEventListener('click', () => {
                this.closeEmailGateModal(modal);
            });

            // Click outside to close
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeEmailGateModal(modal);
                }
            });

            // Handle form submit
            modal.querySelector('#sffc-email-gate-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const email = e.target.querySelector('input[name="email"]').value;
                this.closeEmailGateModal(modal);
                await this.generateWithEmail(email);
            });
        }

        closeEmailGateModal(modal) {
            modal.classList.remove('is-visible');
            setTimeout(() => modal.remove(), 200);
        }

        async generateWithEmail(email) {
            const btn = this.generateBtn;
            const postId = btn.dataset.postId;
            const modelType = btn.dataset.modelType;
            const nonce = btn.dataset.nonce;

            // Set generating state
            this.setPanelState('generating');
            this.setButtonLoading(btn, true);

            const formData = new FormData();
            formData.append('action', 'sffc_generate_model');
            formData.append('post_id', postId);
            formData.append('model_type', modelType);
            formData.append('nonce', nonce);
            formData.append('email', email);

            try {
                const response = await fetch(sffc_frontend?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    this.handleGenerationSuccess(btn, data.data);
                } else {
                    this.handleGenerationError(btn, data.data?.message || 'Generation failed');
                }
            } catch (error) {
                this.handleGenerationError(btn, 'Network error. Please try again.');
            }
        }

        downloadExcel() {
            const btn = this.generateBtn;
            const postId = btn.dataset.postId;
            const modelType = btn.dataset.modelType;
            const nonce = btn.dataset.nonce;

            const url = `${sffc_frontend?.ajaxUrl || '/wp-admin/admin-ajax.php'}?action=sffc_download_excel&post_id=${postId}&model_type=${modelType}&nonce=${nonce}`;

            // Trigger download
            const a = document.createElement('a');
            a.href = url;
            a.download = '';
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        handleGuideDownload() {
            const btn = this.guideBtn;
            const postId = btn.dataset.postId;
            const modelType = btn.dataset.modelType;
            const nonce = btn.dataset.nonce;

            const url = `${sffc_frontend?.ajaxUrl || '/wp-admin/admin-ajax.php'}?action=sffc_download_pdf&post_id=${postId}&model_type=${modelType}&nonce=${nonce}`;

            // Trigger download
            const a = document.createElement('a');
            a.href = url;
            a.download = '';
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        setButtonLoading(btn, loading) {
            const textEl = btn.querySelector('.inst-download-text');
            const loadingEl = btn.querySelector('.inst-download-loading');

            if (loading) {
                btn.disabled = true;
                btn.classList.add('loading');
                if (textEl) textEl.style.display = 'none';
                if (loadingEl) loadingEl.style.display = 'inline-flex';
            } else {
                btn.disabled = false;
                btn.classList.remove('loading');
                if (textEl) textEl.style.display = 'inline';
                if (loadingEl) loadingEl.style.display = 'none';
            }
        }

        showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `inst-notification inst-notification-${type}`;
            notification.innerHTML = `
                <span class="inst-notification-message">${message}</span>
                <button class="inst-notification-close">&times;</button>
            `;

            // Add styles if not present
            if (!document.getElementById('inst-notification-styles')) {
                const style = document.createElement('style');
                style.id = 'inst-notification-styles';
                style.textContent = `
                    .inst-notification {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        padding: 12px 20px;
                        border-radius: 8px;
                        background: #0a1628;
                        color: #fff;
                        font-size: 14px;
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        z-index: 10000;
                        animation: inst-slide-in 0.3s ease;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    }
                    .inst-notification-success {
                        background: #1e6b4a;
                    }
                    .inst-notification-error {
                        background: #9a3c3c;
                    }
                    .inst-notification-close {
                        background: none;
                        border: none;
                        color: rgba(255,255,255,0.7);
                        cursor: pointer;
                        font-size: 18px;
                        padding: 0;
                        line-height: 1;
                    }
                    .inst-notification-close:hover {
                        color: #fff;
                    }
                    @keyframes inst-slide-in {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                `;
                document.head.appendChild(style);
            }

            document.body.appendChild(notification);

            // Close button
            notification.querySelector('.inst-notification-close').addEventListener('click', () => {
                notification.remove();
            });

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        showUpgradeModal() {
            this.showNotification('Monthly generation limit reached. Upgrade for unlimited access.', 'info');
        }
    }

    // ========================================
    // INTELLIGENCE BRIEF HANDLER
    // ========================================

    class IntelligenceBriefHandler {
        constructor() {
            this.buttons = document.querySelectorAll('.sffc-intelligence-brief-btn');
            if (this.buttons.length > 0) {
                this.init();
            }
        }

        init() {
            this.buttons.forEach(btn => {
                btn.addEventListener('click', (e) => this.handleBriefGeneration(e));
            });
        }

        async handleBriefGeneration(e) {
            e.preventDefault();
            const btn = e.currentTarget;

            // Prevent double-clicks
            if (btn.classList.contains('loading')) return;

            const postId = btn.dataset.postId;
            const nonce = btn.dataset.nonce;

            if (!postId || !nonce) {
                this.showNotification('Invalid configuration', 'error');
                return;
            }

            // Set loading state
            this.setButtonLoading(btn, true);

            try {
                const ajaxUrl = window.sffc_frontend?.ajaxUrl || '/wp-admin/admin-ajax.php';

                // Step 1: Generate the brief and get the download URL with proper nonce
                const generateResponse = await fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'sffc_generate_intelligence_brief',
                        nonce: nonce,
                        post_id: postId
                    })
                });

                const generateData = await generateResponse.json();

                if (!generateData.success) {
                    throw new Error(generateData.data?.message || 'Failed to generate brief');
                }

                // Step 2: Use the download URL returned from generate (has correct nonce)
                const downloadUrl = generateData.data?.download_url;

                if (!downloadUrl) {
                    throw new Error('No download URL returned');
                }

                // Create download link
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = '';
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);

                this.showSuccess(btn);
                this.showNotification('Intelligence Brief generated successfully!', 'success');

            } catch (error) {
                console.error('Intelligence Brief generation error:', error);
                this.showError(btn, error.message || 'Generation failed');
                this.showNotification(error.message || 'Failed to generate brief. Please try again.', 'error');
            } finally {
                setTimeout(() => {
                    this.setButtonLoading(btn, false);
                    btn.classList.remove('success', 'error');
                }, 3000);
            }
        }

        setButtonLoading(btn, loading) {
            const textEl = btn.querySelector('.sffc-brief-btn-text');
            const loadingEl = btn.querySelector('.sffc-brief-btn-loading');

            if (loading) {
                btn.disabled = true;
                btn.classList.add('loading');
                if (textEl) textEl.style.display = 'none';
                if (loadingEl) loadingEl.style.display = 'inline-flex';
            } else {
                btn.disabled = false;
                btn.classList.remove('loading');
                if (textEl) textEl.style.display = 'inline';
                if (loadingEl) loadingEl.style.display = 'none';
            }
        }

        showSuccess(btn) {
            btn.classList.remove('loading');
            btn.classList.add('success');
            const textEl = btn.querySelector('.sffc-brief-btn-text');
            if (textEl) {
                const originalText = textEl.textContent;
                textEl.textContent = 'Download Complete';
                setTimeout(() => {
                    textEl.textContent = originalText;
                }, 3000);
            }
        }

        showError(btn, message) {
            btn.classList.remove('loading');
            btn.classList.add('error');
            const textEl = btn.querySelector('.sffc-brief-btn-text');
            if (textEl) {
                const originalText = textEl.textContent;
                textEl.textContent = message || 'Generation Failed';
                setTimeout(() => {
                    textEl.textContent = originalText;
                }, 3000);
            }
        }

        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `inst-notification inst-notification-${type}`;
            notification.innerHTML = `
                <span class="inst-notification-message">${message}</span>
                <button class="inst-notification-close">&times;</button>
            `;

            document.body.appendChild(notification);

            notification.querySelector('.inst-notification-close').addEventListener('click', () => {
                notification.remove();
            });

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
    }

    // ========================================
    // INITIALIZATION
    // ========================================

    function init() {
        const terminals = document.querySelectorAll('.inst-terminal');
        terminals.forEach(terminal => new InstitutionalTerminal(terminal));

        // Initialize download handler
        new DealModelDownloader();

        // Initialize model generator
        new ModelGenerator();

        // Initialize intelligence brief handler
        new IntelligenceBriefHandler();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose globally
    window.InstitutionalTerminal = InstitutionalTerminal;
    window.DealModelDownloader = DealModelDownloader;
    window.ModelGenerator = ModelGenerator;

})();
