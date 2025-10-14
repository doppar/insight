// Cache Component
class CacheComponent extends InsightComponent {
    constructor() {
        super('cache', 'cache-operations-list');
        this.summaryContainerId = 'overview-cache-summary';
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
        if (!data.cache_operations || data.cache_operations.length === 0) {
            return this.buildEmptyState();
        }
        
        return this.buildStats(data) + 
            '<div class="cache-list">' + 
            data.cache_operations.map((op, idx) => this.buildOperationItem(op, idx)).join('') + 
            '</div>';
    }
    
    buildStats(data) {
        const hits = data.cache_hits || 0;
        const misses = data.cache_misses || 0;
        const writes = data.cache_writes || 0;
        const deletes = data.cache_deletes || 0;
        const total = data.cache_total || 0;
        const hitRate = total > 0 ? ((hits / (hits + misses)) * 100).toFixed(1) : 0;
        
        return `
            <div class="cache-stats">
                <div class="cache-stat-card">
                    <div class="cache-stat-value">${total}</div>
                    <div class="cache-stat-label">Total Operations</div>
                </div>
                <div class="cache-stat-card">
                    <div class="cache-stat-value">${hits}</div>
                    <div class="cache-stat-label">Hits</div>
                </div>
                <div class="cache-stat-card">
                    <div class="cache-stat-value">${misses}</div>
                    <div class="cache-stat-label">Misses</div>
                </div>
                <div class="cache-stat-card">
                    <div class="cache-stat-value">${hitRate}%</div>
                    <div class="cache-stat-label">Hit Rate</div>
                </div>
                <div class="cache-stat-card">
                    <div class="cache-stat-value">${writes}</div>
                    <div class="cache-stat-label">Writes</div>
                </div>
                <div class="cache-stat-card">
                    <div class="cache-stat-value">${deletes}</div>
                    <div class="cache-stat-label">Deletes</div>
                </div>
            </div>
        `;
    }
    
    buildOperationItem(operation, index) {
        const type = operation.type || 'unknown';
        const key = operation.key || 'N/A';
        const value = operation.value !== null && operation.value !== undefined ? operation.value : '';
        const hit = operation.hit;
        
        const hitBadge = type === 'get' 
            ? `<span class="cache-hit-badge ${hit ? 'cache-hit' : 'cache-miss'}">${hit ? 'HIT' : 'MISS'}</span>`
            : '';
        
        const valueHtml = value !== '' 
            ? `<div class="cache-value">Value: ${this.escapeHtml(String(value))}</div>` 
            : '';
        
        return `
            <div class="cache-item">
                <div class="cache-header">
                    <div>
                        <span class="badge badge-info">#${index + 1}</span>
                        <span class="cache-type cache-type-${type}">${type}</span>
                        ${hitBadge}
                    </div>
                </div>
                <div class="cache-key">${this.escapeHtml(key)}</div>
                ${valueHtml}
            </div>
        `;
    }
    
    buildSummary(data) {
        if (!data.cache_operations || data.cache_operations.length === 0) {
            return this.buildEmptyState();
        }
        
        const hits = data.cache_hits || 0;
        const misses = data.cache_misses || 0;
        const total = data.cache_total || 0;
        const hitRate = total > 0 ? ((hits / (hits + misses)) * 100).toFixed(1) : 0;
        
        return `
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
                <div class="cache-stat-card">
                    <div class="cache-stat-value">${total}</div>
                    <div class="cache-stat-label">Operations</div>
                </div>
                <div class="cache-stat-card">
                    <div class="cache-stat-value">${hits}/${misses}</div>
                    <div class="cache-stat-label">Hits/Misses</div>
                </div>
                <div class="cache-stat-card">
                    <div class="cache-stat-value">${hitRate}%</div>
                    <div class="cache-stat-label">Hit Rate</div>
                </div>
            </div>
            <div style="color: #9ca3af; font-size: 13px;">
                See Cache tab for detailed operations.
            </div>
        `;
    }
    
    buildEmptyState() {
        return '<div class="no-data">No cache operations detected</div>';
    }
}
