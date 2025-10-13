// Session Component
class SessionComponent extends InsightComponent {
    constructor() {
        super('session', 'session-details');
    }
    
    buildContent(data) {
        return this.buildSession(data);
    }
    
    buildSession(data) {
        if (!data.session_data || Object.keys(data.session_data).length === 0) {
            return this.buildEmptyState();
        }
        
        return createJsonViewer(data.session_data, {
            title: 'Session Data',
            collapsible: true,
            copyButton: true
        });
    }
    
    buildEmptyState() {
        return '<div class="no-data">No session data available</div>';
    }
}
