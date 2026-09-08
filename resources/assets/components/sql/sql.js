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
        const currentQueries = Array.isArray(data.sql) ? data.sql : [];
        const redirectedQueries = this.getRedirectedQueries(data);

        if (currentQueries.length === 0 && redirectedQueries.length === 0) {
            return this.buildEmptyState();
        }

        let content = '';
        if (currentQueries.length > 0) {
            content += '<div class="sql-list">' +
                currentQueries.map((q, idx) => this.buildQueryItem(q, idx)).join('') +
                '</div>';
        }

        if (redirectedQueries.length > 0) {
            content += `
                <div class="subsection-title">Queries from redirected requests</div>
                <div class="sql-list redirected-sql-list">
                    ${redirectedQueries.map((item, idx) => this.buildQueryItem(item.query, idx, item.label)).join('')}
                </div>
            `;
        }

        return content;
    }

    getRedirectedQueries(data) {
        const chain = Array.isArray(data.redirect_chain) ? data.redirect_chain : [];

        return chain.flatMap((hop) => {
            const queries = Array.isArray(hop.sql) ? hop.sql : [];
            const method = hop.method || hop.request_server?.METHOD || 'GET';
            const route = hop.route || hop.request_server?.PATH || '/';
            const label = `${method} ${route}`;

            return queries.map((query) => ({ query, label }));
        });
    }

    buildQueryItem(query, index, requestLabel = '') {
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
                        ${requestLabel ? `<span class="badge badge-warning">${this.escapeHtml(requestLabel)}</span>` : ''}
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
            html += `<div class="inline-note">
                ... and ${data.sql.length - 3} more queries. See Database tab for full list.
            </div>`;
        }
        
        return html;
    }
    
    buildEmptyState() {
        return '<div class="no-data">No database queries detected</div>';
    }
}
