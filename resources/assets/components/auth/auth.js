// Auth Component
class AuthComponent extends InsightComponent {
    constructor() {
        super('auth', 'auth-details');
    }
    
    buildContent(data) {
        return data.auth_authenticated 
            ? this.buildAuthenticated(data) 
            : this.buildGuest();
    }
    
    buildAuthenticated(data) {
        let html = '<div class="auth-authenticated">';
        
        if (data.auth_user) {
            html += this.buildSection('User Information', this.buildTable(data.auth_user));
        }
        
        html += '</div>';
        
        return html;
    }
    
    buildGuest() {
        return '<div class="auth-guest"><span>Not Authenticated</span></div>';
    }
}
