// JSON Viewer Component
class JsonViewer {
    constructor(data, options = {}) {
        this.data = data;
        this.options = {
            collapsible: true,
            copyButton: true,
            title: options.title || 'JSON Data',
            ...options
        };
        this.uniqueId = 'json-' + Math.random().toString(36).substr(2, 9);
    }
    
    render() {
        let html = '<div class="json-viewer">';
        
        if (this.options.copyButton || this.options.title) {
            html += '<div class="json-header">';
            if (this.options.title) {
                html += `<div class="json-title">${this.escapeHtml(this.options.title)}</div>`;
            }
            if (this.options.copyButton) {
                html += `<button class="json-copy-btn" onclick="JsonViewer.copy('${this.uniqueId}')">Copy JSON</button>`;
            }
            html += '</div>';
        }
        
        html += `<div id="${this.uniqueId}" class="json-content">`;
        html += this.renderValue(this.data, 0);
        html += '</div>';
        html += '</div>';
        
        return html;
    }
    
    renderValue(value, indent, key = null, path = '') {
        let html = '';
        const indentSpans = '<span class="json-indent"></span>'.repeat(indent);
        
        if (value === null) {
            html += `<div class="json-line">${indentSpans}`;
            if (key !== null) {
                html += `<span class="json-key">"${this.escapeHtml(key)}"</span><span class="json-colon">:</span>`;
            }
            html += `<span class="json-null">null</span></div>`;
        }
        else if (typeof value === 'boolean') {
            html += `<div class="json-line">${indentSpans}`;
            if (key !== null) {
                html += `<span class="json-key">"${this.escapeHtml(key)}"</span><span class="json-colon">:</span>`;
            }
            html += `<span class="json-boolean">${value}</span></div>`;
        }
        else if (typeof value === 'number') {
            html += `<div class="json-line">${indentSpans}`;
            if (key !== null) {
                html += `<span class="json-key">"${this.escapeHtml(key)}"</span><span class="json-colon">:</span>`;
            }
            html += `<span class="json-number">${value}</span></div>`;
        }
        else if (typeof value === 'string') {
            html += `<div class="json-line">${indentSpans}`;
            if (key !== null) {
                html += `<span class="json-key">"${this.escapeHtml(key)}"</span><span class="json-colon">:</span>`;
            }
            html += `<span class="json-string">"${this.escapeHtml(value)}"</span></div>`;
        }
        else if (Array.isArray(value)) {
            const itemCount = value.length;
            const toggleId = path + (key || 'root');
            
            html += `<div class="json-line">${indentSpans}`;
            if (this.options.collapsible && itemCount > 0) {
                html += `<span class="json-toggle expanded" onclick="JsonViewer.toggle('${toggleId}')"></span>`;
            } else if (itemCount > 0) {
                html += `<span style="width: 16px; display: inline-block;"></span>`;
            }
            if (key !== null) {
                html += `<span class="json-key">"${this.escapeHtml(key)}"</span><span class="json-colon">:</span>`;
            }
            html += `<span class="json-bracket">[</span>`;
            html += `<span class="json-count">${itemCount} item${itemCount !== 1 ? 's' : ''}</span>`;
            html += `</div>`;
            
            if (itemCount > 0) {
                html += `<div id="${toggleId}" class="json-collapsible">`;
                value.forEach((item, index) => {
                    html += this.renderValue(item, indent + 1, null, `${path}${key || 'root'}.${index}.`);
                    if (index < value.length - 1) {
                        const commaIndent = '<span class="json-indent"></span>'.repeat(indent + 1);
                        html += `<div class="json-line">${commaIndent}<span class="json-comma">,</span></div>`;
                    }
                });
                html += `</div>`;
            }
            
            const closingIndent = '<span class="json-indent"></span>'.repeat(indent);
            html += `<div class="json-line">${closingIndent}<span class="json-bracket">]</span></div>`;
        }
        else if (typeof value === 'object') {
            const keys = Object.keys(value);
            const keyCount = keys.length;
            const toggleId = path + (key || 'root');
            
            html += `<div class="json-line">${indentSpans}`;
            if (this.options.collapsible && keyCount > 0) {
                html += `<span class="json-toggle expanded" onclick="JsonViewer.toggle('${toggleId}')"></span>`;
            } else if (keyCount > 0) {
                html += `<span style="width: 16px; display: inline-block;"></span>`;
            }
            if (key !== null) {
                html += `<span class="json-key">"${this.escapeHtml(key)}"</span><span class="json-colon">:</span>`;
            }
            html += `<span class="json-bracket">{</span>`;
            html += `<span class="json-count">${keyCount} key${keyCount !== 1 ? 's' : ''}</span>`;
            html += `</div>`;
            
            if (keyCount > 0) {
                html += `<div id="${toggleId}" class="json-collapsible">`;
                keys.forEach((k, index) => {
                    html += this.renderValue(value[k], indent + 1, k, `${path}${key || 'root'}.${k}.`);
                    if (index < keys.length - 1) {
                        const commaIndent = '<span class="json-indent"></span>'.repeat(indent + 1);
                        html += `<div class="json-line">${commaIndent}<span class="json-comma">,</span></div>`;
                    }
                });
                html += `</div>`;
            }
            
            const closingIndent = '<span class="json-indent"></span>'.repeat(indent);
            html += `<div class="json-line">${closingIndent}<span class="json-bracket">}</span></div>`;
        }
        
        return html;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }
    
    // Static methods for interaction
    static toggle(id) {
        const element = document.getElementById(id);
        const toggle = element.previousElementSibling.querySelector('.json-toggle');
        
        if (element.classList.contains('json-collapsed')) {
            element.classList.remove('json-collapsed');
            toggle.classList.remove('collapsed');
            toggle.classList.add('expanded');
        } else {
            element.classList.add('json-collapsed');
            toggle.classList.remove('expanded');
            toggle.classList.add('collapsed');
        }
    }
    
    static copy(id) {
        const element = document.getElementById(id);
        const button = element.previousElementSibling.querySelector('.json-copy-btn');
        
        // Get the original data from the viewer
        const viewer = element.closest('.json-viewer');
        const dataStr = viewer.dataset.json || '';
        
        if (dataStr) {
            navigator.clipboard.writeText(dataStr).then(() => {
                const originalText = button.textContent;
                button.textContent = '✓ Copied!';
                button.classList.add('copied');
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('copied');
                }, 2000);
            });
        }
    }
}

// Helper function to create and render JSON viewer
function createJsonViewer(data, options = {}) {
    const viewer = new JsonViewer(data, options);
    const html = viewer.render();
    
    // Store the JSON data for copying
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    wrapper.querySelector('.json-viewer').dataset.json = JSON.stringify(data, null, 2);
    
    return wrapper.innerHTML;
}
