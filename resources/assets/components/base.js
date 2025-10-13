// Base Component Class
class InsightComponent {
    constructor(name, containerId) {
        this.name = name;
        this.containerId = containerId;
    }
    
    render(data) {
        const container = document.getElementById(this.containerId);
        if (!container) {
            console.warn(`Container ${this.containerId} not found for component ${this.name}`);
            return;
        }
        
        const content = this.buildContent(data);
        container.innerHTML = content || this.buildEmptyState();
    }
    
    buildContent(data) {
        // To be overridden by child classes
        return '';
    }
    
    buildEmptyState() {
        return '<div class="no-data">No data available</div>';
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    buildTable(data, headers = ['Key', 'Value']) {
        if (!data || Object.keys(data).length === 0) return '';
        
        let html = '<table class="data-table"><thead><tr>';
        headers.forEach(header => {
            html += `<th>${header}</th>`;
        });
        html += '</tr></thead><tbody>';
        
        for (const [key, value] of Object.entries(data)) {
            html += '<tr>';
            html += `<td>${this.escapeHtml(key)}</td>`;
            html += `<td>${this.escapeHtml(String(value))}</td>`;
            html += '</tr>';
        }
        
        html += '</tbody></table>';
        return html;
    }
    
    buildCodeBlock(data) {
        const content = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
        return `<div class="code-block">${this.escapeHtml(content)}</div>`;
    }
    
    buildSection(title, content) {
        if (!content) return '';
        return `<h3 style="color: #f3f4f6; margin: 24px 0 12px;">${title}</h3>${content}`;
    }
}
