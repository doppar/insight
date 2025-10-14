// HTTP Component
class HttpComponent extends InsightComponent {
    constructor() {
        super('http', 'http-details');
    }
    
    buildContent(data) {
        let html = '';
        
        // Show redirect chain if available
        const redirectChain = this.getRedirectChain();
        if (redirectChain && redirectChain.length > 0) {
            html += this.buildRedirectChain(redirectChain, data);
        }
        
        // Show outgoing HTTP requests if available
        if (data.http_requests && data.http_requests.length > 0) {
            html += this.buildHttpRequests(data);
        }
        
        // If nothing to show
        if (!html) {
            return this.buildEmptyState();
        }
        
        return html;
    }
    
    
    buildRedirectChain(chain, currentData) {
        // Calculate total duration
        let totalDuration = currentData.duration_ms || 0;
        chain.forEach(item => {
            totalDuration += item.duration_ms || 0;
        });
        
        const chainItems = chain.map((item, idx) => {
            const status = item.status || item.response_status || 302;
            const method = item.method || 'GET';
            const path = item.route || item.url || '/';
            const duration = item.duration_ms ? item.duration_ms.toFixed(1) : '0.0';
            const redirectUrl = item.redirect_url || '';
            const statusClass = status >= 300 && status < 400 ? 'status-3xx' : 'status-2xx';
            
            return `
                <div class="redirect-chain-item">
                    <div class="redirect-chain-header">
                        <span class="badge badge-info">#${idx + 1}</span>
                        <span class="redirect-chain-status ${statusClass}">${status}</span>
                        <span class="redirect-chain-method">${this.escapeHtml(method)}</span>
                        <span class="redirect-chain-path">${this.escapeHtml(path)}</span>
                        <span class="redirect-chain-duration">${duration} ms</span>
                    </div>
                    ${redirectUrl ? `
                        <div class="redirect-chain-arrow">
                            <span class="redirect-chain-arrow-icon">↓</span>
                            <span>Redirected to: ${this.escapeHtml(redirectUrl)}</span>
                        </div>
                    ` : ''}
                    <div class="redirect-chain-connector"></div>
                </div>
            `;
        }).join('');
        
        // Add current request
        const currentStatus = currentData.status || currentData.response_status || 200;
        const currentMethod = currentData.method || 'GET';
        const currentPath = currentData.route || currentData.url || '/';
        const currentDuration = currentData.duration_ms ? currentData.duration_ms.toFixed(1) : '0.0';
        const currentStatusClass = currentStatus >= 300 && currentStatus < 400 ? 'status-3xx' : 'status-2xx';
        
        return `
            <div class="redirect-chain-container">
                <div class="redirect-chain-title">
                    <span>Redirect Chain</span>
                    <span class="badge badge-warning">${chain.length} redirect${chain.length > 1 ? 's' : ''}</span>
                    <span class="badge badge-info">Total: ${totalDuration.toFixed(1)} ms</span>
                </div>
                <div class="redirect-chain-list">
                    ${chainItems}
                    <div class="redirect-chain-item current">
                        <div class="redirect-chain-header">
                            <span class="badge badge-success">Current</span>
                            <span class="redirect-chain-status ${currentStatusClass}">${currentStatus}</span>
                            <span class="redirect-chain-method">${this.escapeHtml(currentMethod)}</span>
                            <span class="redirect-chain-path">${this.escapeHtml(currentPath)}</span>
                            <span class="redirect-chain-duration">${currentDuration} ms</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    getRedirectChain() {
        // Try to get redirect chain from data attribute
        const profilerElement = document.querySelector('[data-redirect-chain]');
        if (profilerElement) {
            const chainData = profilerElement.dataset.redirectChain;
            if (chainData && chainData !== '[]') {
                try {
                    return JSON.parse(chainData);
                } catch (e) {
                    return [];
                }
            }
        }
        return [];
    }
    
    buildHttpRequests(data) {
        const requests = data.http_requests || [];
        const totalTime = data.http_requests_total_time_ms || 0;
        const count = data.http_requests_count || 0;
        
        const requestItems = requests.map((req, idx) => {
            const method = req.method || 'GET';
            const url = req.url || 'unknown';
            const duration = req.duration_ms ? req.duration_ms.toFixed(1) : '0.0';
            const status = req.status || null;
            const successful = req.successful !== undefined ? req.successful : null;
            const error = req.error || null;
            
            const methodClass = `method-${method.toLowerCase()}`;
            const statusClass = successful === true ? 'status-success' : (successful === false ? 'status-error' : '');
            
            return `
                <div class="http-request-item">
                    <div class="http-request-header">
                        <span class="badge badge-info">#${idx + 1}</span>
                        <span class="http-request-method ${methodClass}">${this.escapeHtml(method)}</span>
                        <span class="http-request-url">${this.escapeHtml(url)}</span>
                        ${status ? `<span class="http-request-status ${statusClass}">${status}</span>` : ''}
                        <span class="http-request-duration">${duration} ms</span>
                    </div>
                    ${error ? `<div class="http-request-error">Error: ${this.escapeHtml(error)}</div>` : ''}
                </div>
            `;
        }).join('');
        
        return `
            <div class="http-requests-section">
                <div class="redirect-chain-container">
                    <div class="redirect-chain-title">
                        <span>Outgoing HTTP Requests</span>
                        <span class="badge badge-info">${count} request${count > 1 ? 's' : ''}</span>
                        <span class="badge badge-success">Total: ${totalTime.toFixed(1)} ms</span>
                    </div>
                    <div style="margin-top: 12px;">
                        ${requestItems}
                    </div>
                </div>
            </div>
        `;
    }
    
    buildEmptyState() {
        return '<div class="no-data">No redirects or outgoing HTTP requests detected</div>';
    }
}
