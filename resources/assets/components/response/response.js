// Response Component
class ResponseComponent extends InsightComponent {
    constructor() {
        super('response', 'response-details');
    }
    
    buildContent(data) {
        let html = '';
        
        // Response info
        if (data.response_status || data.response_content_type) {
            const info = {};
            if (data.response_status) info['Status Code'] = data.response_status;
            if (data.response_content_type) info['Content Type'] = data.response_content_type;
            if (data.response_body_size !== undefined) {
                info['Body Size'] = this.formatBytes(data.response_body_size);
            }
            html += this.buildSection('Response Info', this.buildTable(info, ['Property', 'Value']));
        }
        
        // Headers
        html += this.buildSection('Headers', this.buildTable(data.response_headers));
        
        // Redirect info
        html += this.buildRedirectInfo(data);
        
        return html || this.buildEmptyState();
    }
    
    buildRedirectInfo(data) {
        if (!data.is_redirect || !data.redirect_url) return '';
        
        return `
            <div class="redirect-info">
                <span class="key">Redirect to:</span> 
                <span class="val redirect-url">→ ${this.escapeHtml(data.redirect_url)}</span>
            </div>
        `;
    }
    
    formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }
    
    buildEmptyState() {
        return '<div class="no-data">No response details available</div>';
    }
}
