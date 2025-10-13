// Component Loader and Registry
const InsightComponentRegistry = {
    components: [],
    
    register(ComponentClass) {
        const instance = new ComponentClass();
        this.components.push(instance);
        return this;
    },
    
    renderAll(data) {
        this.components.forEach(component => {
            try {
                component.render(data);
            } catch (error) {
                console.error(`Error rendering component ${component.name}:`, error);
            }
        });
    }
};

// Main Insight Application
const DopparInsight = {
    data: null,
    registry: InsightComponentRegistry,
    
    init(profilerData) {
        this.data = profilerData;
        this.setupTabs();
        this.registry.renderAll(profilerData);
    },
    
    setupTabs() {
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const tabId = button.dataset.tab;
                this.switchToTab(tabId);
            });
        });
        
        // Restore tab from URL hash
        const hash = window.location.hash.substring(1);
        if (hash && document.getElementById(hash)) {
            this.switchToTab(hash);
        }
        
        // Handle browser back/forward
        window.addEventListener('hashchange', () => {
            const hash = window.location.hash.substring(1);
            if (hash && document.getElementById(hash)) {
                this.switchToTab(hash);
            } else if (!hash) {
                this.switchToTab('overview');
            }
        });
    },
    
    switchToTab(tabId) {
        // Update buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        const activeButton = document.querySelector(`[data-tab="${tabId}"]`);
        if (activeButton) {
            activeButton.classList.add('active');
        }
        
        // Update panes
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('active');
        });
        const activePane = document.getElementById(tabId);
        if (activePane) {
            activePane.classList.add('active');
        }
        
        // Update URL hash
        history.replaceState(null, null, '#' + tabId);
        
        // Scroll to top
        window.scrollTo({ top: 0 });
    }
};
