// SQL Component
class SqlComponent extends InsightComponent {
    constructor() {
        super('sql', 'sql-queries-list');
        this.summaryContainerId = 'overview-sql-summary';
    }
    
    render(data) {
        super.render(data);
        
        // Also render summary for overview tab
        const summaryContainer = document.getElementById(this.summaryContainerId);
        if (summaryContainer) {
            summaryContainer.innerHTML = this.buildSummary(data);
        }
    }
    
    buildContent(data) {
        if (!data.sql || data.sql.length === 0) {
            return this.buildEmptyState();
        }
        
        return '<div class="sql-list">' + 
            data.sql.map((q, idx) => this.buildQueryItem(q, idx)).join('') + 
            '</div>';
    }
    
    buildQueryItem(query, index) {
        const duration = query.duration_ms?.toFixed?.(2) ?? query.duration_ms ?? 0;
        const rowCount = query.row_count !== null && query.row_count !== undefined ? query.row_count : '?';
        const bindings = query.bindings && Object.keys(query.bindings).length > 0 
            ? JSON.stringify(query.bindings) 
            : '';
        const error = query.error 
            ? `<div class="sql-error">Error: ${this.escapeHtml(query.error)}</div>` 
            : '';
        
        return `
            <div class="sql-item">
                <div class="sql-header">
                    <div>
                        <span class="badge badge-info">#${index + 1}</span>
                        <span class="sql-time">${duration} ms</span>
                        <span class="sql-rows">${rowCount} rows</span>
                    </div>
                </div>
                <div class="sql-query">${this.escapeHtml(query.sql || 'N/A')}</div>
                ${bindings ? `<div class="sql-bindings">Bindings: ${this.escapeHtml(bindings)}</div>` : ''}
                ${error}
            </div>
        `;
    }
    
    buildSummary(data) {
        if (!data.sql || data.sql.length === 0) {
            return this.buildEmptyState();
        }
        
        const summary = data.sql.slice(0, 3);
        let html = '<div class="sql-list">' + 
            summary.map((q, idx) => this.buildQueryItem(q, idx)).join('') + 
            '</div>';
        
        if (data.sql.length > 3) {
            html += `<div style="margin-top: 12px; color: #9ca3af; font-size: 13px;">
                ... and ${data.sql.length - 3} more queries. See Database tab for full list.
            </div>`;
        }
        
        return html;
    }
    
    buildEmptyState() {
        return '<div class="no-data">No database queries detected</div>';
    }
}
