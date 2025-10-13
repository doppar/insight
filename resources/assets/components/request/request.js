// Request Component
class RequestComponent extends InsightComponent {
    constructor() {
        super('request', 'request-details');
    }
    
    buildContent(data) {
        let html = '';
        
        html += this.buildSection('Headers', this.buildTable(data.request_headers));
        html += this.buildSection('Query Parameters', this.buildTable(data.request_query, ['Parameter', 'Value']));
        html += this.buildSection('POST Parameters', this.buildTable(data.request_params, ['Parameter', 'Value']));
        html += this.buildSection('Request Body', this.buildRequestBody(data.request_body));
        html += this.buildSection('Cookies', this.buildTable(data.request_cookies, ['Cookie', 'Value']));
        html += this.buildFilesSection(data.request_files);
        html += this.buildSection('Server Variables', this.buildTable(data.request_server, ['Variable', 'Value']));
        
        return html || this.buildEmptyState();
    }
    
    buildRequestBody(body) {
        if (!body) return '';
        return this.buildCodeBlock(body);
    }
    
    buildFilesSection(files) {
        if (!files || Object.keys(files).length === 0) return '';
        
        let html = '<h3 style="color: #f3f4f6; margin: 24px 0 12px;">Uploaded Files</h3>';
        html += '<table class="data-table"><thead><tr><th>Field</th><th>Name</th><th>Type</th><th>Size</th></tr></thead><tbody>';
        
        for (const [key, file] of Object.entries(files)) {
            const size = file.size ? (file.size / 1024).toFixed(2) + ' KB' : 'N/A';
            html += `<tr>
                <td>${this.escapeHtml(key)}</td>
                <td>${this.escapeHtml(file.name || 'N/A')}</td>
                <td>${this.escapeHtml(file.type || 'N/A')}</td>
                <td>${size}</td>
            </tr>`;
        }
        
        html += '</tbody></table>';
        return html;
    }
    
    buildEmptyState() {
        return '<div class="no-data">No request details available</div>';
    }
}
