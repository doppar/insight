// AJAX Component
class AjaxComponent extends InsightComponent {
    constructor() {
        super('ajax', 'ajax-details');
        this.summaryContainerId = 'overview-ajax-summary';
        this.limit = 100;
    }

    render(data) {
        const container = document.getElementById(this.containerId);
        if (!container) {
            console.warn(`Container ${this.containerId} not found for component ${this.name}`);
            return;
        }

        container.innerHTML = '<div class="ajax-loading">Loading AJAX requests...</div>';
        this.fetchRequests(data, container);
    }

    async fetchRequests(currentData, container) {
        try {
            const response = await fetch(`/_insight/api/history?limit=${this.limit}`, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error(`AJAX history returned ${response.status}`);
            }

            const history = await response.json();
            const requests = Array.isArray(history)
                ? history.filter((item) => item.is_ajax === true)
                : [];

            container.innerHTML = this.buildContent(requests, currentData);
            const summary = document.getElementById(this.summaryContainerId);
            if (summary) {
                summary.innerHTML = this.buildSummary(requests, currentData);
            }
        } catch (error) {
            console.error('Unable to load Insight AJAX history:', error);
            container.innerHTML = '<div class="no-data">Unable to load AJAX requests right now.</div>';
        }
    }

    buildSummary(requests, currentData) {
        const currentIsAjax = currentData.is_ajax === true;
        const recent = requests.slice(0, 3).map((item) => {
            const route = this.escapeHtml(String(item.route || '/'));
            const status = Number(item.status || 0);
            const duration = Number(item.duration_ms || 0).toFixed(2);
            return `<a class="ajax-summary-item" href="/_insight/${encodeURIComponent(String(item.id || ''))}">
                <span><strong>${this.escapeHtml(String(item.method || 'GET'))}</strong> ${route}</span>
                <span>${status} · ${duration} ms</span>
            </a>`;
        }).join('');

        return `
            <div class="ajax-summary-grid">
                <div class="summary-card">
                    <div class="summary-card-label">AJAX captured</div>
                    <div class="summary-card-value">${requests.length}</div>
                    <div class="summary-card-note">Recent asynchronous requests available to inspect.</div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-label">Current request</div>
                    <div class="summary-card-value">${currentIsAjax ? 'AJAX' : 'HTTP'}</div>
                    <div class="summary-card-note">This snapshot ${currentIsAjax ? 'was' : 'was not'} initiated asynchronously.</div>
                </div>
            </div>
            ${recent ? `<div class="ajax-summary-list">${recent}</div>` : '<div class="no-data">No recent AJAX request.</div>'}
        `;
    }

    buildContent(requests, currentData) {
        if (requests.length === 0) {
            return '<div class="no-data">No AJAX requests have been captured yet.</div>';
        }

        const totalTime = requests.reduce((total, item) => total + Number(item.duration_ms || 0), 0);
        const items = requests.map((item) => this.buildRequest(item, currentData)).join('');

        return `
            <div class="ajax-summary">
                <span class="badge badge-info">${requests.length} request${requests.length === 1 ? '' : 's'}</span>
                <span class="badge badge-neutral">${totalTime.toFixed(2)} ms total</span>
                <span class="badge badge-success">Click a request to inspect it</span>
            </div>
            <div class="ajax-list">${items}</div>
        `;
    }

    buildRequest(item, currentData) {
        const id = String(item.id || '');
        const status = Number(item.status || 0);
        const duration = Number(item.duration_ms || 0).toFixed(2);
        const method = this.escapeHtml(String(item.method || 'GET'));
        const route = this.escapeHtml(String(item.route || item.url || '/'));
        const current = id === String(currentData.id || '');
        const statusClass = status >= 400 ? 'badge-error' : (status >= 300 ? 'badge-warning' : 'badge-success');

        return `
            <a class="ajax-item ${current ? 'is-current' : ''}" href="/_insight/${encodeURIComponent(id)}">
                <div class="ajax-item-main">
                    <div class="ajax-item-head">
                        <span class="ajax-method">${method}</span>
                        <span class="ajax-route">${route}</span>
                    </div>
                    <div class="ajax-item-meta">
                        <span class="badge ${statusClass}">HTTP ${status}</span>
                        <span class="ajax-duration">${duration} ms</span>
                        <span class="ajax-type">${this.escapeHtml(String(item.response_content_type || item.content_type || 'response'))}</span>
                    </div>
                </div>
                <div class="ajax-item-side">
                    ${current ? '<span class="badge badge-success">Current</span>' : `<span class="ajax-request-id">${this.escapeHtml(id.slice(0, 8))}</span>`}
                </div>
            </a>
        `;
    }
}
