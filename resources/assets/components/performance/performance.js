// Performance Component
class PerformanceComponent extends InsightComponent {
    constructor() {
        super('performance', 'performance-metrics');
    }
    
    buildContent(data) {
        const metrics = {
            'Total Duration': `${(data.duration_ms?.toFixed?.(2) ?? data.duration_ms ?? 0)} ms`,
            'Memory Peak': `${((data.memory_peak || 0) / (1024*1024)).toFixed(2)} MB`,
        };
        
        if (data.sql_total_time_ms !== undefined) {
            metrics['Database Time'] = `${(data.sql_total_time_ms?.toFixed?.(2) ?? data.sql_total_time_ms)} ms`;
        }
        
        if (data.sql_total_count !== undefined) {
            metrics['Database Queries'] = data.sql_total_count;
        }
        
        return this.buildMetrics(metrics);
    }
    
    buildMetrics(metrics) {
        let html = '';
        for (const [key, value] of Object.entries(metrics)) {
            html += `<div class="row">
                <span class="key">${key}:</span>
                <span class="val">${value}</span>
            </div>`;
        }
        return html;
    }
}
