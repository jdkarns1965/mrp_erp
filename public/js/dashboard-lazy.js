/**
 * Lazy Loading Dashboard Widgets
 * Uses Intersection Observer API for efficient loading
 */

class DashboardLazyLoader {
    constructor() {
        this.widgets = new Map();
        this.observer = null;
        this.eventSource = null;
        this.init();
    }

    init() {
        // Set up Intersection Observer for lazy loading
        this.observer = new IntersectionObserver(
            (entries) => this.handleIntersection(entries),
            {
                root: null,
                rootMargin: '50px',
                threshold: 0.01
            }
        );

        // Observe all lazy-load widgets
        document.querySelectorAll('[data-widget-lazy]').forEach(widget => {
            this.observer.observe(widget);
            this.widgets.set(widget.id, {
                element: widget,
                loaded: false,
                type: widget.dataset.widgetType
            });
        });

        // Set up SSE for real-time updates
        this.initSSE();
    }

    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting && !this.widgets.get(entry.target.id)?.loaded) {
                this.loadWidget(entry.target);
            }
        });
    }

    async loadWidget(element) {
        const widgetId = element.id;
        const widget = this.widgets.get(widgetId);
        
        if (!widget || widget.loaded) return;

        // Show loading state
        element.innerHTML = '<div class="widget-loading">Loading...</div>';

        try {
            const endpoint = element.dataset.widgetEndpoint;
            const response = await fetch(endpoint);
            
            if (!response.ok) throw new Error('Failed to load widget');

            const contentType = response.headers.get('content-type');
            
            if (contentType.includes('application/x-ndjson')) {
                // Handle streaming response
                await this.handleStreamingWidget(element, response);
            } else {
                // Handle regular JSON response
                const data = await response.json();
                this.renderWidget(element, widget.type, data);
            }

            widget.loaded = true;
            element.classList.add('widget-loaded');

        } catch (error) {
            console.error(`Failed to load widget ${widgetId}:`, error);
            element.innerHTML = '<div class="widget-error">Failed to load</div>';
        }
    }

    async handleStreamingWidget(element, response) {
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop(); // Keep incomplete line in buffer

            for (const line of lines) {
                if (line.trim()) {
                    try {
                        const data = JSON.parse(line);
                        this.updateStreamingWidget(element, data);
                    } catch (e) {
                        console.error('Failed to parse streaming data:', e);
                    }
                }
            }
        }
    }

    updateStreamingWidget(element, data) {
        switch (data.type) {
            case 'stats':
                this.renderStats(element, data.data);
                break;
            case 'row':
                this.appendRow(element, data.data);
                break;
            case 'end':
                element.classList.add('streaming-complete');
                break;
        }
    }

    renderWidget(element, type, data) {
        switch (type) {
            case 'inventory-alerts':
                this.renderInventoryAlerts(element, data);
                break;
            case 'production-status':
                this.renderProductionStatus(element, data);
                break;
            case 'statistics':
                this.renderStatistics(element, data);
                break;
            case 'recent-orders':
                this.renderRecentOrders(element, data);
                break;
            default:
                element.innerHTML = JSON.stringify(data);
        }
    }

    renderInventoryAlerts(element, data) {
        if (!data.alerts || data.alerts.length === 0) {
            element.innerHTML = '<div class="no-alerts">No inventory alerts</div>';
            return;
        }

        const html = `
            <div class="alert-list">
                ${data.alerts.map(alert => `
                    <div class="alert-item ${alert.severity}">
                        <span class="alert-icon">${this.getAlertIcon(alert.severity)}</span>
                        <div class="alert-content">
                            <strong>${alert.material_name}</strong>
                            <span class="alert-message">${alert.message}</span>
                        </div>
                        <span class="alert-qty">${alert.shortage_qty}</span>
                    </div>
                `).join('')}
            </div>
        `;
        element.innerHTML = html;
    }

    renderProductionStatus(element, data) {
        const html = `
            <div class="production-grid">
                ${data.orders.map(order => `
                    <div class="production-card">
                        <div class="production-header">
                            <span class="order-number">${order.order_number}</span>
                            <span class="status-badge ${order.status_color}">${order.status_label}</span>
                        </div>
                        <div class="production-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${order.progress_percentage}%"></div>
                            </div>
                            <span class="progress-text">${order.progress_percentage}%</span>
                        </div>
                        <div class="production-info">
                            <span>${order.product_name}</span>
                            <span>${order.quantity} units</span>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        element.innerHTML = html;
    }

    renderStatistics(element, data) {
        const html = `
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value">${data.active_production}</div>
                    <div class="stat-label">Active Production</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">${data.pending_orders}</div>
                    <div class="stat-label">Pending Orders</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">${data.low_stock}</div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">${data.todays_shipments}</div>
                    <div class="stat-label">Today's Shipments</div>
                </div>
            </div>
        `;
        element.innerHTML = html;
    }

    renderRecentOrders(element, data) {
        const html = `
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.orders.map(order => `
                        <tr>
                            <td>${order.order_number}</td>
                            <td>${order.customer_name}</td>
                            <td>${order.product_name}</td>
                            <td><span class="status-badge ${order.status_class}">${order.status}</span></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        element.innerHTML = html;
    }

    initSSE() {
        if (!window.EventSource) {
            console.warn('SSE not supported');
            return;
        }

        this.eventSource = new EventSource('/mrp_erp/public/api/sse-updates.php');

        this.eventSource.addEventListener('dashboard_stats', (e) => {
            const data = JSON.parse(e.data);
            this.updateDashboardStats(data);
        });

        this.eventSource.addEventListener('alert', (e) => {
            const alert = JSON.parse(e.data);
            this.showAlert(alert);
        });

        this.eventSource.addEventListener('production_update', (e) => {
            const updates = JSON.parse(e.data);
            this.updateProductionStatus(updates);
        });

        this.eventSource.onerror = (e) => {
            console.error('SSE error:', e);
            if (this.eventSource.readyState === EventSource.CLOSED) {
                setTimeout(() => this.initSSE(), 5000);
            }
        };
    }

    updateDashboardStats(stats) {
        // Update stat cards with animation
        Object.entries(stats).forEach(([key, value]) => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                const currentValue = parseInt(element.textContent);
                if (currentValue !== value) {
                    this.animateValue(element, currentValue, value, 500);
                }
            }
        });
    }

    animateValue(element, start, end, duration) {
        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                element.textContent = end;
                clearInterval(timer);
            } else {
                element.textContent = Math.round(current);
            }
        }, 16);
    }

    showAlert(alert) {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `toast toast-${alert.severity}`;
        toast.innerHTML = `
            <div class="toast-header">
                <strong>${alert.type === 'inventory_alert' ? 'Inventory Alert' : 'System Alert'}</strong>
            </div>
            <div class="toast-body">
                ${alert.items ? `${alert.items.length} items need attention` : alert.message}
            </div>
        `;
        
        document.getElementById('toast-container')?.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    getAlertIcon(severity) {
        switch (severity) {
            case 'critical': return '🔴';
            case 'warning': return '🟡';
            case 'info': return 'ℹ️';
            default: return '⚫';
        }
    }

    destroy() {
        if (this.observer) {
            this.observer.disconnect();
        }
        if (this.eventSource) {
            this.eventSource.close();
        }
    }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.dashboardLoader = new DashboardLazyLoader();
    });
} else {
    window.dashboardLoader = new DashboardLazyLoader();
}