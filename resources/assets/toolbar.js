window.DopparProfiler = {
  open: false,
  closeAnimationPromise: null,
  cancelCloseAnimation(){
    if(this.closeAnimationPromise){
      this.closeAnimationPromise.cancelled = true;
      this.closeAnimationPromise = null;
    }
  },
  async copyText(text){
    if(navigator.clipboard && window.isSecureContext){
      await navigator.clipboard.writeText(text);
      return true;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', 'readonly');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    textarea.style.pointerEvents = 'none';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    let copied = false;
    try {
      copied = document.execCommand('copy');
    } finally {
      document.body.removeChild(textarea);
    }

    if(!copied){
      throw new Error('Copy command failed');
    }

    return true;
  },
  setCopyButtonState(button, state){
    if(!button){
      return;
    }
    button.classList.remove('is-success', 'is-error');
    if(state === 'success'){
      button.classList.add('is-success');
      button.innerHTML = '<span class="copy-icon copy-icon-success" aria-hidden="true"></span>Copied';
      return;
    }
    if(state === 'error'){
      button.classList.add('is-error');
      button.innerHTML = '<span class="copy-icon copy-icon-error" aria-hidden="true"></span>Copy failed';
      return;
    }
    button.innerHTML = '<span class="copy-icon" aria-hidden="true"></span>Copy Payload';
  },
  getQuickViewTheme(){
    try {
      return window.localStorage.getItem('doppar.insight.quickview.theme') === 'light' ? 'light' : 'dark';
    } catch (error) {
      return 'light';
    }
  },
  setQuickViewTheme(panel, theme){
    if(!panel){
      return;
    }
    const resolved = theme === 'dark' ? 'dark' : 'light';
    panel.setAttribute('data-theme', resolved);
    panel.querySelectorAll('[data-action="toggle-theme"]').forEach((button) => {
      button.setAttribute('data-theme-mode', resolved);
      button.setAttribute('aria-pressed', resolved === 'dark' ? 'true' : 'false');
      button.setAttribute('aria-label', resolved === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
      button.innerHTML = resolved === 'dark'
        ? '<span class="theme-switch-icon theme-switch-icon-dark" aria-hidden="true"></span>'
        : '<span class="theme-switch-icon theme-switch-icon-light" aria-hidden="true"></span>';
    });
    try {
      window.localStorage.setItem('doppar.insight.quickview.theme', resolved);
    } catch (error) {}
  },
  getToolbarBounds(){
    const host = document.getElementById('doppar-profiler');
    if(!host || !host.shadowRoot){
      return null;
    }
    const toolbar = host.shadowRoot.querySelector('.dp-root');
    return toolbar ? toolbar.getBoundingClientRect() : null;
  },
  animatePanelOpen(panel){
    if(!panel){
      return;
    }
    this.cancelCloseAnimation();
    panel.style.willChange = '';
    panel.style.overflow = '';
    panel.style.opacity = '1';
    panel.style.transform = '';
    panel.style.filter = '';
    panel.style.clipPath = '';
    panel.style.borderRadius = '';
  },
  closePanel(root){
    this.cancelCloseAnimation();
    if(root){
      root.innerHTML = '';
    }
    return Promise.resolve();
  },
  syncPanelPosition(){
    const host = document.getElementById('doppar-profiler-panel');
    if(!host){
      return;
    }
    host.style.display = 'flex';
    const bounds = this.getToolbarBounds();
    if(!bounds){
      return;
    }

    const gap = 16;
    const availableHeight = Math.max(220, bounds.top - gap - 12);
    const panelHeight = Math.min(500, availableHeight);
    // Panel is wider than the dock; keep them decoupled.
    const panelWidth = Math.min(1200, window.innerWidth - 24);
    const left = Math.max(12, Math.min(
      bounds.left + ((bounds.width - panelWidth) / 2),
      window.innerWidth - panelWidth - 12
    ));
    const panelTop = Math.max(12, bounds.top - gap - panelHeight);

    host.style.left = left + 'px';
    host.style.right = 'auto';
    host.style.width = panelWidth + 'px';
    host.style.height = panelHeight + 'px';
    host.style.top = panelTop + 'px';
    host.style.bottom = 'auto';
    host.style.transform = '';
    host.style.boxSizing = 'border-box';
    host.style.overflow = 'hidden';
    host.style.background = 'transparent';
    host.style.borderRadius = '12px';
    host.style.setProperty('--dp-panel-height', panelHeight + 'px');
  },
  ensurePanelRoot(){
    let host = document.getElementById('doppar-profiler-panel');
    if(!host){
      host = document.createElement('div');
      host.id = 'doppar-profiler-panel';
      // Isolate the host from page CSS as much as possible
      host.style.all = 'initial';
      host.style.position = 'fixed';
      host.style.zIndex = '2147483647';
      host.style.background = 'transparent';
      host.style.borderRadius = '12px';
      host.style.overflow = 'hidden';
      document.body.appendChild(host);
    }
    this.syncPanelPosition();
    if(!host.shadowRoot){
      host.attachShadow({mode:'open'});
    }
    return host.shadowRoot;
  },
  openDetailsPage(){
    const id = document.getElementById('doppar-profiler').dataset.requestId;
    window.open('/_insight/' + id, '_blank');
  },
  toggle(){
    this.open = !this.open;
    const root = this.ensurePanelRoot();
    if(this.open){
      this.cancelCloseAnimation();
      const id = document.getElementById('doppar-profiler').dataset.requestId;
      fetch('/_insight/api/' + encodeURIComponent(id) + '?_=' + Date.now(), { cache: 'no-store' }).then(r=>r.json()).then(data=>{
        const toolbarLogo = document.getElementById('doppar-profiler')?.shadowRoot?.querySelector('.dp-launcher-mark img')?.getAttribute('src') || '';
        const escapeHtml = (value) => {
          const div = document.createElement('div');
          div.textContent = String(value ?? '');
          return div.innerHTML;
        };
        const formatBytes = (bytes) => {
          const size = Number(bytes || 0);
          if(!size){
            return '0 Bytes';
          }
          const units = ['Bytes', 'KB', 'MB', 'GB'];
          const index = Math.min(units.length - 1, Math.floor(Math.log(size) / Math.log(1024)));
          return `${Math.round((size / Math.pow(1024, index)) * 100) / 100} ${units[index]}`;
        };
        const truncateText = (value, max = 88) => {
          const text = String(value ?? '');
          return text.length > max ? `${text.slice(0, max - 1)}…` : text;
        };
        const prettyJson = (value) => escapeHtml(JSON.stringify(value, null, 2));
        const hasEntries = (value) => {
          if(Array.isArray(value)){
            return value.length > 0;
          }
          return !!value && typeof value === 'object' && Object.keys(value).length > 0;
        };
        const formatCellValue = (value) => {
          if(value === null || value === undefined || value === ''){
            return '<span class="muted-value">N/A</span>';
          }
          if(typeof value === 'object'){
            return `<pre class="code-block code-block-compact">${prettyJson(value)}</pre>`;
          }
          return escapeHtml(String(value));
        };
        const buildPropertyTable = (dataset, keyHeader = 'Property', valueHeader = 'Value', tableClass = 'property-table') => {
          if(!hasEntries(dataset)){
            return '';
          }
          const rows = Object.entries(dataset).map(([key, value]) => `
            <tr>
              <td>${escapeHtml(key)}</td>
              <td>${formatCellValue(value)}</td>
            </tr>
          `).join('');
          return `
            <table class="${escapeHtml(tableClass)}">
              <thead>
                <tr>
                  <th>${escapeHtml(keyHeader)}</th>
                  <th>${escapeHtml(valueHeader)}</th>
                </tr>
              </thead>
              <tbody>${rows}</tbody>
            </table>
          `;
        };
        const buildFilesTable = (files) => {
          if(!hasEntries(files)){
            return '';
          }
          const rows = Object.entries(files).map(([key, file]) => `
            <tr>
              <td>${escapeHtml(key)}</td>
              <td>${escapeHtml(file?.name || 'N/A')}</td>
              <td>${escapeHtml(file?.type || 'N/A')}</td>
              <td>${file?.size ? `${(Number(file.size) / 1024).toFixed(2)} KB` : 'N/A'}</td>
            </tr>
          `).join('');
          return `
            <table class="request-table">
              <thead>
                <tr>
                  <th>Field</th>
                  <th>Name</th>
                  <th>Type</th>
                  <th>Size</th>
                </tr>
              </thead>
              <tbody>${rows}</tbody>
            </table>
          `;
        };
        const buildResponseBlock = (title, content) => {
          if(!content){
            return '';
          }
          return `
            <div class="response-block">
              <div class="subsection-title">${escapeHtml(title)}</div>
              ${content}
            </div>
          `;
        };
        const buildRequestBlock = (title, content) => {
          if(!content){
            return '';
          }
          return `
            <div class="request-block">
              <div class="subsection-title">${escapeHtml(title)}</div>
              ${content}
            </div>
          `;
        };
        const buildSubsection = (title, content) => {
          if(!content){
            return '';
          }
          return `
            <div class="subsection">
              <div class="subsection-title">${escapeHtml(title)}</div>
              ${content}
            </div>
          `;
        };
        const buildCodeBlock = (value) => {
          if(value === null || value === undefined || value === ''){
            return '';
          }
          const output = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
          return `<pre class="code-block">${escapeHtml(output)}</pre>`;
        };
        const requestUrl = data.url || data.route || '/';
        const buildJsonViewerItem = (key, value) => {
          const isArray = Array.isArray(value);
          const type = isArray ? 'array' : typeof value;
          const keyHtml = escapeHtml(String(key));

          if(value !== null && (type === 'object' || isArray)){
            const label = isArray ? `array[${value.length}]` : 'object';
            const children = Object.entries(value).map(([childKey, childValue]) => buildJsonViewerItem(childKey, childValue)).join('');

            return `
              <details class="json__node">
                <summary class="json__item json__item--collapsible">
                  <span class="json__key">${keyHtml}</span>
                  <span class="json__value json__value--type-${type}">${escapeHtml(label)}</span>
                </summary>
                <div class="json__children">
                  ${children}
                </div>
              </details>
            `;
          }

          const valueOutput = type === 'string'
            ? `"${escapeHtml(String(value))}"`
            : escapeHtml(String(value));

          return `
            <div class="json__item">
              <div class="json__key">${keyHtml}</div>
              <div class="json__value json__value--${type}">${valueOutput}</div>
            </div>
          `;
        };
        const buildJsonViewer = (value) => {
          if(!value || typeof value !== 'object'){
            return buildCodeBlock(value);
          }

          return `
            <div class="json">
              ${Object.entries(value).map(([key, childValue]) => buildJsonViewerItem(key, childValue)).join('')}
            </div>
          `;
        };
        const css = `
          :host{all:initial}

          .panel {
            --dp-scrollbar-size: 2px;
            --dp-scrollbar-track: transparent;
            --dp-scrollbar-thumb: rgba(255,255,255,0.18);
            --dp-scrollbar-thumb-hover: rgba(255,255,255,0.28);
            box-sizing: border-box;
            width: 100%;
            height: 100%;
            max-height: var(--dp-panel-height, 500px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border-radius: 12px;
            padding: 14px;
            padding-right: 0;
            color: #ebebf0;
            font: 13px/1.55 "Aptos", "Segoe UI Variable", "Segoe UI", sans-serif;
            background: #141418;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
            position: relative;
            scrollbar-width: thin;
            scrollbar-color: var(--dp-scrollbar-thumb) var(--dp-scrollbar-track);
          }
          .panel::-webkit-scrollbar,
          .sidebar-nav::-webkit-scrollbar,
          .canvas::-webkit-scrollbar,
          .history-table-scroll::-webkit-scrollbar {
            width: var(--dp-scrollbar-size);
            height: var(--dp-scrollbar-size);
          }
          .panel::-webkit-scrollbar-track,
          .sidebar-nav::-webkit-scrollbar-track,
          .canvas::-webkit-scrollbar-track,
          .history-table-scroll::-webkit-scrollbar-track {
            background: var(--dp-scrollbar-track);
          }
          .panel::-webkit-scrollbar-button,
          .sidebar-nav::-webkit-scrollbar-button,
          .canvas::-webkit-scrollbar-button,
          .history-table-scroll::-webkit-scrollbar-button {
            display: none;
            width: 0;
            height: 0;
          }
          .panel::-webkit-scrollbar-thumb,
          .sidebar-nav::-webkit-scrollbar-thumb,
          .canvas::-webkit-scrollbar-thumb,
          .history-table-scroll::-webkit-scrollbar-thumb {
            background: var(--dp-scrollbar-thumb);
            border-radius: 999px;
          }
          .panel::-webkit-scrollbar-thumb:hover,
          .sidebar-nav::-webkit-scrollbar-thumb:hover,
          .canvas::-webkit-scrollbar-thumb:hover,
          .history-table-scroll::-webkit-scrollbar-thumb:hover {
            background: var(--dp-scrollbar-thumb-hover);
          }

          .panel::before {
            display: none;
          }

          .workspace {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 48px minmax(0, 1fr);
            gap: 0;
            flex: 1 1 auto;
            min-height: 0;
            margin: -14px 0 -14px -14px;
            overflow: hidden;
          }
          .sidebar {
            z-index: 2;
            display: flex;
            flex-direction: column;
            width: 48px;
            height: 100%;
            min-height: 0;
            padding: 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            color: #888899;
            box-shadow: none;
            overflow: hidden;
          }
          .sidebar-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            padding: 14px 0 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
          }
          .sidebar-mark {
            width: auto;
            height: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border-radius: 0;
            background: transparent;
            border: 0;
            box-shadow: none;
          }
          .sidebar-mark img {
            width: 22px;
            height: 22px;
          }
          .sidebar-title,
          .sidebar-copy {
            display: none;
          }
          .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-height: 0;
            flex: 1 1 auto;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 0;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: var(--dp-scrollbar-thumb) var(--dp-scrollbar-track);
          }
          .sidebar-nav::-webkit-scrollbar-track {
            margin-block: 4px;
          }
          .nav-group {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 4px;
          }
          .nav-group-divider {
            height: 1px;
            margin: 1px 3px;
            background: rgba(255, 255, 255, 0.08);
          }
          .nav-rail {
            display: flex;
            flex-direction: column;
            gap: 0;
          }
          .nav-rail-button {
            appearance: none;
            border: 0;
            width: 48px;
            height: 36px;
            margin: 0;
            padding: 0;
            border-radius: 0;
            background: transparent;
            color: #888899;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: background 0.18s ease, opacity 0.18s ease;
          }
          .nav-rail-tooltip {
            position: fixed;
            padding: 7px 10px;
            background: rgba(20, 20, 24, 0.98);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            z-index: 2147483646;
            transition: opacity 0.12s ease, visibility 0.12s ease;
          }
          .nav-rail-tooltip.is-visible {
            opacity: 1;
            visibility: visible;
          }
          .nav-rail-button:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
          }
          .nav-rail-button.active {
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
            box-shadow: none;
          }
          .nav-icon {
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
            background-repeat: no-repeat;
            background-position: center;
            background-size: 18px 18px;
            opacity: 0.72;
          }
          .nav-rail-button:hover .nav-icon,
          .nav-rail-button.active .nav-icon {
            opacity: 1;
          }
          .nav-icon-overview { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='3' width='7' height='7' rx='1.5'/%3E%3Crect x='14' y='3' width='7' height='7' rx='1.5'/%3E%3Crect x='3' y='14' width='7' height='7' rx='1.5'/%3E%3Crect x='14' y='14' width='7' height='7' rx='1.5'/%3E%3C/svg%3E"); }
          .nav-icon-history { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 8v5l3 2'/%3E%3Cpath d='M3.05 11A9 9 0 1 1 6 17.3'/%3E%3Cpath d='M3 4v5h5'/%3E%3C/svg%3E"); }
          .nav-icon-http { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='9'/%3E%3Cpath d='M3 12h18'/%3E%3Cpath d='M12 3a15 15 0 0 1 0 18'/%3E%3Cpath d='M12 3a15 15 0 0 0 0 18'/%3E%3C/svg%3E"); }
          .nav-icon-database { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cellipse cx='12' cy='5' rx='7' ry='3'/%3E%3Cpath d='M5 5v14c0 1.7 3.1 3 7 3s7-1.3 7-3V5'/%3E%3Cpath d='M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3'/%3E%3C/svg%3E"); }
          .nav-icon-cache { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 3 4 7l8 4 8-4-8-4Z'/%3E%3Cpath d='m4 12 8 4 8-4'/%3E%3Cpath d='m4 17 8 4 8-4'/%3E%3C/svg%3E"); }
          .nav-icon-auth { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 3 5 6v6c0 5 3.4 8 7 9 3.6-1 7-4 7-9V6l-7-3Z'/%3E%3Cpath d='m9.5 12 1.7 1.7L14.8 10'/%3E%3C/svg%3E"); }
          .nav-icon-request { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 4v14'/%3E%3Cpath d='m7 13 5 5 5-5'/%3E%3Cpath d='M5 5h14'/%3E%3C/svg%3E"); }
          .nav-icon-response { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 20V6'/%3E%3Cpath d='m17 11-5-5-5 5'/%3E%3Cpath d='M5 19h14'/%3E%3C/svg%3E"); }
          .nav-icon-performance { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4.5 16a8 8 0 1 1 15 0'/%3E%3Cpath d='M12 12 9 15'/%3E%3Cpath d='M12 12h5'/%3E%3C/svg%3E"); }
          .nav-icon-session { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='4' y='4' width='16' height='16' rx='2'/%3E%3Cpath d='M8 9h8'/%3E%3Cpath d='M8 13h8'/%3E%3Cpath d='M8 17h5'/%3E%3C/svg%3E"); }
          .nav-icon-logs { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8 3h8l4 4v14H4V3h4'/%3E%3Cpath d='M14 3v5h5'/%3E%3Cpath d='M8 13h8'/%3E%3Cpath d='M8 17h6'/%3E%3C/svg%3E"); }
          .nav-icon-json { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dce7f7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8 8c-1.5 0-2.5 1-2.5 2.5v3C5.5 15 4.5 16 3 16'/%3E%3Cpath d='M16 8c1.5 0 2.5 1 2.5 2.5v3c0 1 1 2 2.5 2'/%3E%3Cpath d='M10 6 8 18'/%3E%3Cpath d='M14 6l2 12'/%3E%3C/svg%3E"); }
          .nav-kicker {
            font-size: 10px;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #55566a;
          }
          .canvas {
            min-width: 0;
            min-height: 0;
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 14px;
            scrollbar-width: thin;
            scrollbar-color: var(--dp-scrollbar-thumb) var(--dp-scrollbar-track);
          }
          .view-section {
            display: none;
            margin-bottom: 24px;
          }
          .view-section.active {
            display: block;
          }
          .hero {
            padding: 0 0 16px;
            border-radius: 0;
            background: transparent;
            border: 0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            box-shadow: none;
            margin-bottom: 16px;
          }
          .hero-top {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            flex-wrap: wrap;
            margin-bottom: 14px;
          }
          .hero-top > div {
            min-width: 0;
            flex: 1 1 420px;
          }
          .eyebrow {
            font-size: 11px;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: #6b7890;
            font-weight: 800;
            margin-bottom: 8px;
          }
          .hero-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
          }
          .hero-title {
            font-size: 28px;
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -.04em;
            margin: 0 0 8px;
            color: #152238;
          }
          .hero-request-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
            min-height: 44px;
            padding: 10px 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            box-shadow: none;
            margin: 0 0 12px;
            overflow: hidden;
          }
          .hero-method-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 56px;
            padding: 8px 12px;
            border-radius: 10px;
            background: linear-gradient(180deg, #d8f8e7, #cdf6df);
            border: 1px solid rgba(20, 125, 100, 0.08);
            color: #0f7a66;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .16em;
            text-transform: uppercase;
            flex: 0 0 auto;
          }
          .hero-url {
            min-width: 0;
            flex: 1 1 auto;
            color: #5d6b80;
            font: 13px/1.5 "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
          }
          .hero-request-copy {
            appearance: none;
            border: 0;
            background: transparent;
            color: #69778f;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex: 0 0 34px;
            margin-left: auto;
            transition: background .18s ease, color .18s ease, transform .18s ease;
          }
          .hero-request-copy:hover {
            background: rgba(240, 244, 255, 0.72);
            color: #475672;
            transform: translateY(-1px);
          }
          .hero-request-copy.is-success {
            background: rgba(20, 125, 100, 0.12);
            color: #147d64;
          }
          .hero-request-copy.is-error {
            background: rgba(191, 60, 68, 0.12);
            color: #bf3c44;
          }
          .hero-request-copy-icon {
            width: 18px;
            height: 18px;
            display: inline-flex;
            background-repeat: no-repeat;
            background-position: center;
            background-size: 18px 18px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2369778f' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='9' y='9' width='11' height='11' rx='2'/%3E%3Cpath d='M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1'/%3E%3C/svg%3E");
          }
          .hero-title small {
            display: inline-flex;
            margin-right: 10px;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(132, 134, 255, 0.1);
            color: #5a5ef0;
            border: 1px solid rgba(132, 134, 255, 0.16);
            font-size: 11px;
            letter-spacing: .14em;
            text-transform: uppercase;
          }
          .hero-copy {
            color: #5e6d85;
            max-width: 520px;
          }
          .hero-top-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
          }
          .theme-switch {
            appearance: none;
            border: 1px solid rgba(132,134,255,0.14);
            background: rgba(255,255,255,0.82);
            color: #4f5d75;
            width: 38px;
            height: 38px;
            min-height: 38px;
            padding: 0;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.82);
            transition: background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease;
            flex: 0 0 38px;
          }
          .theme-switch:hover {
            transform: translateY(-1px);
            background: rgba(248,250,255,0.96);
            color: #273550;
          }
          .theme-switch-icon {
            width: 16px;
            height: 16px;
            display: inline-flex;
            background-repeat: no-repeat;
            background-position: center;
            background-size: 16px 16px;
          }
          .theme-switch-icon-light {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%235a5ef0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='4'/%3E%3Cpath d='M12 2v2'/%3E%3Cpath d='M12 20v2'/%3E%3Cpath d='m4.93 4.93 1.41 1.41'/%3E%3Cpath d='m17.66 17.66 1.41 1.41'/%3E%3Cpath d='M2 12h2'/%3E%3Cpath d='M20 12h2'/%3E%3Cpath d='m6.34 17.66-1.41 1.41'/%3E%3Cpath d='m19.07 4.93-1.41 1.41'/%3E%3C/svg%3E");
          }
          .theme-switch-icon-dark {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23d9e4ff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 3a6 6 0 1 0 9 9 9 9 0 1 1-9-9Z'/%3E%3C/svg%3E");
          }
          .hero-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(132,134,255,0.14);
            background: rgba(255,255,255,0.76);
            font-weight: 800;
          }
          .hero-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: currentColor;
          }
          .hero-status.ok { color: #8ef0bd; }
          .hero-status.warn { color: #ffd277; }
          .hero-status.err { color: #ff9f97; }

          .metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
          }
          .metric {
            padding: 12px 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            box-shadow: none;
          }
          .metric-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: #888899;
            font-weight: 700;
            margin-bottom: 6px;
          }
          .metric-value {
            font-size: 22px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -.03em;
            color: #ffffff;
            font-variant-numeric: tabular-nums;
          }
          .metric-note {
            margin-top: 6px;
            color: #55566a;
            font-size: 12px;
          }

          .section {
            padding: 14px;
            border-radius: 8px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: none;
            margin-bottom: 12px;
          }
          .section:last-child {
            margin-bottom: 0;
          }
          .section-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
          }
          .section-title-main {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
          }
          .section-copy {
            color: #888899;
            margin-bottom: 12px;
            font-size: 12px;
          }
          .json-api-shell {
            display: grid;
            gap: 12px;
          }
          .api-group-card {
            display: grid;
            gap: 12px;
            padding: 14px;
            border-radius: 8px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            min-width: 0;
          }
          .api-payload-card {
            gap: 0;
            padding: 0;
            overflow: hidden;
          }
          .api-payload-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            padding: 14px;
          }
          .api-payload-card .response-preview-scroll {
            max-height: 460px;
            overflow: auto;
          }
          .api-payload-card .code-block {
            border-top: 1px solid rgba(255,255,255,0.08);
            border-radius: 0 0 8px 8px;
          }
          .api-line {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
          }
          .api-method {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 52px;
            padding: 6px 10px;
            border-radius: 6px;
            background: rgba(34, 197, 94, 0.14);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.22);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
          }
          .api-path {
            display: inline-flex;
            align-items: center;
            flex: 1;
            min-height: 32px;
            padding: 0 10px;
            border-radius: 0;
            background: transparent;
            border: 0;
            border-left: 1px solid rgba(255,255,255,0.1);
            color: #ebebf0;
            font: 13px/1.4 "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
            word-break: break-all;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }
          .api-open-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 10px;
            border-radius: 6px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            color: #888899;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            transition: background .18s ease, color .18s ease;
          }
          .api-open-btn:hover {
            background: rgba(255,255,255,0.06);
            color: #ebebf0;
          }
          .api-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
          }
          .api-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
          }
          .api-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 6px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            color: #888899;
            font-size: 11px;
            font-weight: 700;
          }
          .api-chip-success {
            background: rgba(34, 197, 94, 0.10);
            border-color: rgba(34, 197, 94, 0.18);
            color: #22c55e;
          }
          .api-chip-teal {
            background: rgba(15, 139, 141, 0.10);
            border-color: rgba(15, 139, 141, 0.18);
            color: #2ab4b6;
          }
          .api-chip-mono {
            background: rgba(167, 139, 250, 0.10);
            border-color: rgba(167, 139, 250, 0.16);
            color: #a78bfa;
            font-family: "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
          }
          .copy-action {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 10px;
            border-radius: 6px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            color: #ebebf0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            cursor: pointer;
            transition: background .18s ease, color .18s ease;
          }
          .copy-action:hover {
            background: rgba(255,255,255,0.06);
            color: #ffffff;
          }
          .copy-action.is-success {
            background: rgba(34, 197, 94, 0.14);
            border-color: rgba(34, 197, 94, 0.24);
            color: #22c55e;
          }
          .copy-action.is-error {
            background: rgba(239, 68, 68, 0.14);
            border-color: rgba(239, 68, 68, 0.24);
            color: #ef4444;
          }
          .copy-icon {
            width: 14px;
            height: 14px;
            flex: 0 0 14px;
            background-repeat: no-repeat;
            background-position: center;
            background-size: 14px 14px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='9' y='9' width='11' height='11' rx='2'/%3E%3Cpath d='M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1'/%3E%3C/svg%3E");
          }
          .copy-icon-success {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m5 12 4 4L19 6'/%3E%3C/svg%3E");
          }
          .copy-icon-error {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M18 6 6 18'/%3E%3Cpath d='m6 6 12 12'/%3E%3C/svg%3E");
          }
          .section-stack {
            display: grid;
            gap: 16px;
          }
          .subsection {
            display: grid;
            gap: 10px;
          }
          .subsection-title {
            color: #888899;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
          }

          .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            border: 1px solid transparent;
          }
          .badge-info { background: rgba(255,255,255,0.06); color: #ebebf0; border-color: rgba(255,255,255,0.1); }
          .badge-success { background: rgba(34, 197, 94, 0.12); color: #22c55e; border-color: rgba(34, 197, 94, 0.2); }
          .badge-warning { background: rgba(245, 158, 11, 0.12); color: #f59e0b; border-color: rgba(245, 158, 11, 0.2); }
          .badge-error { background: rgba(239, 68, 68, 0.12); color: #ef4444; border-color: rgba(239, 68, 68, 0.2); }

          .row {
            margin: 0;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 10px 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
          }
          .key {
            min-width: 108px;
            color: #888899;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
          }
          .val {
            flex: 1;
            color: #ebebf0;
            font-weight: 600;
            word-break: break-word;
          }

          .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 24px;
          }
          .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
          }
          .summary-card {
            padding: 14px;
            border-radius: 8px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: none;
          }
          .summary-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .14em;
            font-weight: 700;
            color: #888899;
            margin-bottom: 8px;
          }
          .summary-value {
            color: #ffffff;
            font-size: 20px;
            line-height: 1.08;
            letter-spacing: -.02em;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
          }
          .summary-note {
            margin-top: 6px;
            font-size: 12px;
            color: #55566a;
          }
          .response-status-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-weight: 900;
            border: 0;
            font-family: "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
            box-shadow: none;
          }
          .response-status-chip-success {
            background: #16a34a;
            color: #ffffff;
          }
          .response-status-chip-warning {
            background: #f59e0b;
            color: #ffffff;
          }
          .response-status-chip-error {
            background: #dc2626;
            color: #ffffff;
          }
          .history-shell {
            display: grid;
            gap: 12px;
          }
          .history-loading {
            padding: 18px;
            border-radius: 0;
            border: 1px solid rgba(255,255,255,0.08);
            background: transparent;
            color: #888899;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
          }
          .history-list {
            display: grid;
            gap: 8px;
          }
          .history-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            padding: 12px 14px;
            border-radius: 8px;
            text-decoration: none;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            transition: background .18s ease, border-color .18s ease;
          }
          .history-item:hover {
            border-color: rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.05);
          }
          .history-main {
            min-width: 0;
            display: grid;
            gap: 8px;
          }
          .history-top,
          .history-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            min-width: 0;
          }
          .history-method {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 52px;
            padding: 5px 10px;
            border-radius: 8px;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.20);
            color: #22c55e;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .14em;
            text-transform: uppercase;
            flex: 0 0 auto;
          }
          .history-route {
            min-width: 0;
            color: #ebebf0;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
          }
          .history-duration,
          .history-captured,
          .history-request-id {
            color: #55566a;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
          }
          .history-request-id {
            font-family: "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
          }
          .history-open {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            color: #888899;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
            transition: background .18s ease, color .18s ease;
          }
          .history-open:hover {
            background: rgba(255,255,255,0.09);
            color: #ebebf0;
          }
          .history-open::after {
            content: "↗";
            font-size: 12px;
          }
          .history-dashboard {
            display: grid;
            gap: 14px;
          }
          .history-dashboard .history-table-shell {
            margin-top: 2px;
          }
          .history-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
          }
          .history-range-pills {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
          }
          .history-range-pill {
            appearance: none;
            border: 1px solid rgba(255,255,255,0.1);
            background: transparent;
            color: #888899;
            padding: 5px 9px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .18s ease, color .18s ease, border-color .18s ease;
          }
          .history-range-pill:hover {
            background: rgba(255,255,255,0.06);
            color: #ebebf0;
          }
          .history-range-pill.active {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.16);
            color: #ffffff;
          }
          .history-shell {
            gap: 18px;
          }
          .history-overview-grid {
            display: grid;
            grid-template-columns: 1.15fr .95fr;
            gap: 12px;
          }
          .history-card {
            padding: 14px;
            border-radius: 8px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
          }
          .history-card-wide {
            grid-column: 1 / -1;
          }
          .history-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
          }
          .history-card-title {
            font-size: 13px;
            letter-spacing: -.01em;
            text-transform: none;
            color: #ffffff;
            font-weight: 700;
            margin-bottom: 0;
          }
          .history-card-value {
            color: #ebebf0;
            font-size: 28px;
            line-height: 1;
            letter-spacing: -.04em;
            font-weight: 900;
          }
          .history-card-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
          }
          .history-card-note {
            color: #888899;
            font-size: 12px;
            font-weight: 700;
          }
          .history-kpis {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 14px;
          }
          .history-kpi {
            padding: 10px 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            border-right: 1px solid rgba(255,255,255,0.08);
          }
          .history-kpi:last-child {
            border-right: 0;
          }
          .history-kpi-label {
            color: #55566a;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
            margin-bottom: 8px;
          }
          .history-kpi-value {
            color: #ebebf0;
            font-size: 17px;
            font-weight: 900;
            line-height: 1;
          }
          .history-chart-wrap {
            display: grid;
            gap: 6px;
          }
          .history-chart-shell {
            display: grid;
            grid-template-columns: 52px minmax(0, 1fr);
            gap: 10px;
            align-items: stretch;
          }
          .history-chart-y-axis {
            display: grid;
            grid-template-rows: repeat(5, 1fr);
            height: 160px;
            color: #4a4d62;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            text-align: right;
          }
          .history-chart-y-axis span {
            display: flex;
            align-items: center;
            justify-content: flex-end;
          }
          .history-chart-canvas {
            min-width: 0;
          }
          .history-chart {
            width: 100%;
            height: 160px;
            display: block;
          }
          .history-chart-axis {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: #4a4d62;
            font-size: 11px;
            font-weight: 700;
          }
          .history-grid-line {
            stroke: rgba(255,255,255,0.045);
            stroke-width: 1;
            stroke-dasharray: 3 7;
          }
          .history-bar-muted {
            fill: rgba(255,255,255,0.07);
          }
          .history-bar-success {
            fill: #2ab4b6;
          }
          .history-bar-warning {
            fill: #f59e0b;
          }
          .history-bar-error {
            fill: #ef4444;
          }
          .history-line-avg {
            fill: none;
            stroke: rgba(255,255,255,0.38);
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
          }
          .history-line-max {
            fill: none;
            stroke: #fbbf24;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
          }
          .history-table-shell {
            display: grid;
            gap: 12px;
            min-width: 0;
          }
          .history-table-head {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
          }
          .history-route-count {
            color: #ebebf0;
            font-size: 14px;
            font-weight: 800;
          }
          .history-search-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1 1 auto;
          }
          .history-search {
            flex: 1 1 160px;
            min-width: 0;
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
            color: #ebebf0;
            font-size: 12px;
            font-weight: 600;
            outline: none;
            transition: border-color .18s ease, background .18s ease;
          }
          .history-search:focus {
            border-color: rgba(42,180,182,0.30);
            background: rgba(255,255,255,0.06);
          }
          .history-search::placeholder {
            color: #55566a;
            font-weight: 600;
          }
          .history-toggle-group {
            display: inline-flex;
            gap: 0;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 0;
            padding: 0;
            flex-wrap: nowrap;
          }
          .history-toggle {
            appearance: none;
            border: 0;
            border-right: 1px solid rgba(255,255,255,0.1);
            background: transparent;
            color: #888899;
            padding: 6px 11px;
            border-radius: 0;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: background .18s ease, color .18s ease;
          }
          .history-toggle:last-child {
            border-right: 0;
          }
          .history-toggle:hover {
            color: #ebebf0;
            background: rgba(255,255,255,0.06);
          }
          .history-toggle.active {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.1);
            color: #ffffff;
          }
          .history-route-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border-radius: 0;
            border: 1px solid rgba(255,255,255,0.08);
            background: transparent;
            table-layout: auto;
            min-width: 520px;
          }
          .history-route-table th,
          .history-route-table td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            text-align: left;
            vertical-align: middle;
          }
          .history-route-table th {
            color: #55566a;
            font-size: 10px;
            letter-spacing: .14em;
            text-transform: uppercase;
            font-weight: 800;
            background: rgba(255,255,255,0.04);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 1;
            background: #1f2024;
          }
          .history-route-table td {
            color: #ebebf0;
            font-size: 13px;
            font-weight: 600;
          }
          .history-route-table tr:last-child td {
            border-bottom: 0;
          }
          .history-route-table tbody tr:hover td {
            background: rgba(42,180,182,0.04);
          }
          .history-table-scroll {
            width: 100%;
            max-width: 100%;
            max-height: 380px;
            overflow: auto;
            border-radius: 0;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: var(--dp-scrollbar-thumb) var(--dp-scrollbar-track);
          }
          .history-method-chip {
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            min-width: 0;
            padding: 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            color: #cbd5e1;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .14em;
            text-transform: uppercase;
          }
          .history-method-chip[data-method="GET"] { color: #22c55e; }
          .history-method-chip[data-method="POST"] { color: #14b8a6; }
          .history-method-chip[data-method="PUT"] { color: #f59e0b; }
          .history-method-chip[data-method="PATCH"] { color: #fb923c; }
          .history-method-chip[data-method="DELETE"] { color: #ef4444; }
          .history-method-chip[data-method="OPTIONS"] { color: #60a5fa; }
          .history-method-chip[data-method="HEAD"] { color: #a78bfa; }
          .history-path-cell {
            min-width: 80px;
            max-width: 200px;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #ebebf0;
            font-family: "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
            font-size: 12px;
            cursor: default;
          }
          .history-cell-number {
            white-space: nowrap;
            color: #888899;
            font-size: 13px;
            font-weight: 700;
          }
          .history-cell-number.has-value {
            color: #ebebf0;
          }
          .history-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
          }
          .history-count-badge-alert {
            color: #ffb5c0;
            background: rgba(239, 68, 68, 0.16);
            border: 1px solid rgba(239, 68, 68, 0.28);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
          }
          .history-col-method { width: 90px; }
          .history-col-path { width: auto; }
          .history-col-requests { width: 80px; }
          .history-col-3xx,
          .history-col-4xx,
          .history-col-5xx { width: 60px; }
          .history-col-avg,
          .history-col-max { width: 68px; }
          .history-empty-state {
            padding: 18px 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            border-top: 1px solid rgba(255,255,255,0.08);
            color: #888899;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
          }
          .cache-shell {
            display: grid;
            gap: 12px;
          }
          .auth-shell {
            display: grid;
            gap: 12px;
          }
          .request-shell {
            display: grid;
            gap: 12px;
          }
          .response-shell {
            display: grid;
            gap: 12px;
          }
          .perf-shell {
            display: grid;
            gap: 12px;
          }
          .perf-group-card {
            display: grid;
            gap: 14px;
            padding: 14px;
            border-radius: 8px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            min-width: 0;
          }
          .perf-group-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
          }
          .perf-group-title {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -.01em;
          }
          .perf-group-card .cache-kpis {
            padding-bottom: 0;
            border-bottom: 0;
          }
          .perf-section {
            display: grid;
            gap: 10px;
          }
          .perf-section-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #888899;
          }
          .perf-group-card .perf-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
          }
          .perf-group-card .perf-metric {
            padding: 0 14px;
            border-left: 1px solid rgba(255,255,255,0.08);
          }
          .perf-group-card .perf-metric:nth-child(2n+1) {
            padding-left: 0;
            border-left: 0;
          }
          .request-stack,
          .response-stack,
          .perf-stack {
            display: grid;
            gap: 16px;
          }
          .perf-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
          }
          .perf-block {
            display: grid;
            gap: 10px;
            min-width: 0;
          }
          .perf-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px 0;
          }
          .perf-metric {
            min-width: 0;
            padding: 0 18px;
            border-left: 1px solid rgba(255,255,255,0.08);
          }
          .perf-metric:nth-child(3n+1) {
            padding-left: 0;
            border-left: 0;
          }
          .response-preview-head {
            margin-bottom: 8px;
          }
          .auth-identity {
            display: flex;
            align-items: stretch;
            flex-wrap: wrap;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
          }
          .auth-identity-item {
            flex: 1 1 140px;
            min-width: 0;
            padding: 0 18px;
            border-left: 1px solid rgba(255,255,255,0.08);
          }
          .auth-identity-item:first-child {
            padding-left: 0;
            border-left: 0;
          }
          .auth-identity-label {
            color: #55566a;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
            margin-bottom: 8px;
          }
          .auth-identity-value {
            color: #ebebf0;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.35;
            word-break: break-word;
          }
          .auth-block,
          .request-block,
          .response-block {
            display: grid;
            gap: 10px;
          }
          .auth-table,
          .request-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border-radius: 0;
            border: 1px solid rgba(255,255,255,0.08);
            background: transparent;
          }
          .auth-table th,
          .auth-table td,
          .request-table th,
          .request-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            vertical-align: top;
          }
          .auth-table th,
          .request-table th {
            background: transparent;
            color: #888899;
            font-size: 11px;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 700;
          }
          .auth-table td,
          .request-table td {
            color: #ebebf0;
            font-size: 13px;
            font-weight: 600;
            word-break: break-word;
          }
          .auth-table tr:last-child td,
          .request-table tr:last-child td {
            border-bottom: 0;
          }
          .cache-kpis {
            display: flex;
            align-items: stretch;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
          }
          .cache-kpi {
            flex: 1 1 0;
            min-width: 0;
            padding: 0 18px;
            border-left: 1px solid rgba(255,255,255,0.08);
          }
          .cache-kpi:first-child {
            padding-left: 0;
            border-left: 0;
          }
          .cache-kpi:last-child {
            padding-right: 0;
          }
          .cache-kpi-label {
            color: #55566a;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
            margin-bottom: 8px;
          }
          .cache-kpi-value {
            color: #ebebf0;
            font-size: 20px;
            font-weight: 900;
            line-height: 1;
            font-variant-numeric: tabular-nums;
          }
          .cache-table-scroll {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            border-radius: 0;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: var(--dp-scrollbar-thumb) var(--dp-scrollbar-track);
          }
          .cache-detail-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border-radius: 0;
            border: 1px solid rgba(255,255,255,0.08);
            background: transparent;
            table-layout: auto;
            min-width: 780px;
          }
          .cache-detail-table th,
          .cache-detail-table td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            text-align: left;
            vertical-align: top;
          }
          .cache-detail-table th {
            color: #55566a;
            font-size: 10px;
            letter-spacing: .14em;
            text-transform: uppercase;
            font-weight: 800;
            background: rgba(255,255,255,0.04);
            white-space: nowrap;
          }
          .cache-detail-table td {
            color: #ebebf0;
            font-size: 13px;
            font-weight: 600;
          }
          .cache-detail-table tr:last-child td {
            border-bottom: 0;
          }
          .cache-detail-table tbody tr:hover td {
            background: rgba(42,180,182,0.04);
          }
          .cache-type-chip {
            display: inline-flex;
            align-items: center;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #2ab4b6;
          }
          .cache-key-cell {
            display: block;
            min-width: 160px;
            max-width: 260px;
            color: #ebebf0;
            font-family: "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
            font-size: 12px;
            line-height: 1.55;
            word-break: break-word;
          }
          .cache-store-cell {
            display: grid;
            gap: 2px;
          }
          .cache-store-name {
            color: #ebebf0;
            font-size: 12px;
            font-weight: 800;
          }
          .cache-store-driver {
            color: #888899;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
          }
          .cache-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 58px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .10em;
            text-transform: uppercase;
            white-space: nowrap;
          }
          .cache-status-hit {
            background: rgba(34, 197, 94, 0.14);
            border: 1px solid rgba(34, 197, 94, 0.22);
            color: #22c55e;
          }
          .cache-status-miss,
          .cache-status-failed {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.18);
            color: #f87171;
          }
          .cache-status-write,
          .cache-status-pass {
            background: rgba(96, 165, 250, 0.12);
            border: 1px solid rgba(96, 165, 250, 0.18);
            color: #60a5fa;
          }
          .cache-status-neutral {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.10);
            color: #cbd5e1;
          }
          .cache-detail-meta {
            display: grid;
            gap: 4px;
          }
          .cache-meta-text {
            color: #888899;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.5;
            word-break: break-word;
          }
          .cache-value-preview {
            color: #ebebf0;
            font-size: 12px;
            font-family: "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
            line-height: 1.55;
            word-break: break-word;
          }
          .cache-value-details {
            min-width: 0;
          }
          .cache-value-summary {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            list-style: none;
            min-width: 0;
          }
          .cache-value-summary::-webkit-details-marker {
            display: none;
          }
          .cache-value-summary::before {
            content: "▸";
            flex: 0 0 auto;
            color: #888899;
            font-size: 12px;
            line-height: 1;
            transition: transform .18s ease;
          }
          .cache-value-summary-text {
          max-width: 300px;
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #ebebf0;
            font-size: 12px;
            font-family: "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
            font-weight: 400;
            line-height: 1.4;
          }
          .cache-value-details[open] .cache-value-summary::before {
            transform: rotate(90deg);
          }
          .cache-value-body {
            margin-top: 8px;
          }
          .cache-col-type { width: 86px; }
          .cache-col-key { width: 180px; }
          .cache-col-value { width: auto; }
          .cache-col-store { width: 110px; }
          .cache-col-status { width: 90px; }
          .cache-col-meta { width: 210px; }
          .history-error-feed {
            display: grid;
            gap: 8px;
          }
          .history-error-item {
            display: grid;
            gap: 8px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.07);
            background: rgba(255,255,255,0.03);
            min-width: 0;
          }
          .history-error-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
          }
          .history-error-path {
            color: #ebebf0;
            font-size: 13px;
            font-weight: 800;
            min-width: 0;
            overflow-wrap: anywhere;
          }
          .history-error-text {
            color: #888899;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.45;
            overflow-wrap: anywhere;
          }
          .history-error-meta {
            color: #55566a;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
          }
          .history-error-empty {
            padding: 16px;
          }
          .muted-value {
            color: #8391a8;
            font-style: italic;
          }

          .property-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03);
          }
          .property-table th,
          .property-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            vertical-align: top;
          }
          .property-table th {
            background: rgba(255,255,255,0.03);
            color: #888899;
            font-size: 11px;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 700;
          }
          .property-table td {
            color: #ebebf0;
            font-size: 13px;
            font-weight: 600;
            word-break: break-word;
          }
          .property-table tr:last-child td {
            border-bottom: 0;
          }
          .response-highlight-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border-radius: 0;
            border: 1px solid rgba(255,255,255,0.08);
            background: #1a1a1f;
          }
          .response-highlight-table th,
          .response-highlight-table td {
            padding: 14px 16px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid rgba(255,255,255,0.08);
          }
          .response-highlight-table th {
            background: #232329;
            color: #8ea0bd;
            font-size: 11px;
            letter-spacing: .14em;
            text-transform: uppercase;
            font-weight: 800;
          }
          .response-highlight-table td {
            color: #f3f6fb;
            font-size: 13px;
            font-weight: 700;
            word-break: break-word;
            background: #1a1a1f;
          }
          .response-highlight-table tr:last-child td {
            border-bottom: 0;
          }
          .code-block {
            margin: 0;
            padding: 16px 18px;
            border-radius: 0;
            border: none;
            background: #0d0d10;
            color: #ebebf0;
            font: 12px/1.75 "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-x: auto;
          }
          .response-preview-scroll {
            max-height: 460px;
            overflow: auto;
          }
          .response-preview-scroll > .code-block {
            border-radius: 0;
          }
          .code-block-compact {
            padding: 10px 12px;
            border-radius: 0;
            font-size: 11px;
          }
          .json {
            font: 12px/1.6 "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
            color: #ebebf0;
            background: #0d0d10;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 0;
            padding: 14px 16px;
            box-shadow: none;
          }
          .json > .json__item {
            display: block;
          }
          .json__node {
            display: block;
            margin-top: 8px;
          }
          .json__node:first-child {
            margin-top: 0;
          }
          .json__children {
            display: none;
            padding-left: 18px;
          }
          .json__node[open] > .json__children {
            display: block;
          }
          .json__item {
            display: block;
            margin-top: 8px;
            padding-left: 18px;
          }
          .json__item--collapsible {
            display: block;
            cursor: pointer;
            position: relative;
            list-style: none;
            margin-top: 0;
          }
          .json__item--collapsible::-webkit-details-marker {
            display: none;
          }
          .json__item--collapsible::before {
            content: "+";
            position: absolute;
            left: 2px;
            top: 0;
            color: #5a5ef0;
            font-weight: 800;
            user-select: none;
          }
          .json__item--collapsible::after {
            content: "";
            position: absolute;
            left: 6px;
            top: 22px;
            width: 1px;
            height: calc(100% - 18px);
            background: rgba(17, 40, 61, 0.16);
          }
          .json__item--collapsible:hover > .json__key,
          .json__item--collapsible:hover > .json__value {
            text-decoration: underline;
          }
          .json__node[open] > .json__item--collapsible::before {
            content: "-";
          }
          .json__key {
            color: #5a5ef0;
            display: inline;
            font-weight: 700;
          }
          .json__key::after {
            content: ": ";
            color: #7f8ca3;
          }
          .json__value {
            display: inline;
          }
          .json__value--string {
            color: #147d64;
          }
          .json__value--number {
            color: #7c3aed;
          }
          .json__value--boolean {
            color: #b66912;
          }
          .json__value--object,
          .json__value--type-object,
          .json__value--type-array {
            color: #c05621;
          }
          .json__value--undefined {
            color: #8391a8;
            font-style: italic;
          }
          .log-list {
            display: grid;
            gap: 10px;
          }
          .logs-shell {
            display: grid;
            gap: 12px;
          }
          .log-card {
            display: grid;
            gap: 8px;
            padding: 14px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03);
          }
          .log-card .code-block {
            margin: 0 -14px -14px;
            padding: 12px 14px;
            border-top: 1px solid rgba(255,255,255,0.08);
            border-radius: 0 0 8px 8px;
          }
          .log-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
          }
          .log-level {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 10px;
            letter-spacing: .1em;
            text-transform: uppercase;
            font-weight: 700;
            background: rgba(255,255,255,0.06);
            color: #ebebf0;
            border: 1px solid rgba(255,255,255,0.1);
          }
          .log-time {
            color: #888899;
            font-size: 12px;
            font-weight: 600;
          }
          .log-message {
            color: #ebebf0;
            font-weight: 600;
            word-break: break-word;
          }
          .pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
          }
          .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 6px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            color: #888899;
            font-size: 11px;
            font-weight: 600;
          }

          .sql-list { display: grid; gap: 10px; }
          .sql-shell {
            display: grid;
            gap: 12px;
          }
          .sql-item,
          .redirect-chain-item {
            padding: 14px;
            border-radius: 8px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
          }
          .sql-header,
          .redirect-chain-header {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 10px;
          }
          .sql-header > div > .badge,
          .sql-header > div > .sql-time,
          .sql-header > div > .sql-rows {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            height: 22px;
            padding: 0 8px;
            line-height: 1;
            font-size: 11px;
            font-weight: 700;
            border-radius: 6px;
            vertical-align: middle;
          }
          .sql-header > div > .badge {
            gap: 0;
            text-transform: none;
            letter-spacing: 0;
          }
          .sql-time,
          .redirect-chain-duration {
            color: #22c55e;
            font-weight: 700;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 6px;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.18);
          }
          .sql-rows,
          .redirect-chain-method,
          .redirect-chain-status {
            font-size: 11px;
            font-weight: 700;
            border-radius: 6px;
            padding: 3px 8px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            color: #888899;
          }
          .sql-query,
          .sql-bindings,
          .redirect-chain-arrow {
            background: #0d0d10;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 0;
            padding: 12px 14px;
            font-family: "Berkeley Mono", "SFMono-Regular", Consolas, monospace;
          }
          .sql-query,
          .redirect-chain-path {
            color: #ebebf0;
            word-break: break-word;
          }
          .sql-bindings,
          .redirect-chain-arrow {
            margin-top: 10px;
            color: #888899;
            font-size: 12px;
          }
          .sql-error {
            margin-top: 10px;
            color: #ef4444;
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.16);
            border-radius: 0;
            padding: 10px 12px;
            font-weight: 700;
          }
          .no-data {
            color: #888899;
            font-style: normal;
            padding: 18px 0;
            text-align: center;
            background: transparent;
            border-radius: 0px 8px;
            border: 0;
            border-top: 1px solid rgba(255,255,255,0.08);
          }

          .panel[data-theme="dark"] {
            color: #ebebf0;
            background: #141418;
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
          }
          .panel[data-theme="dark"]::before {
            display: none;
          }
          .panel[data-theme="dark"] .hero,
          .panel[data-theme="dark"] .section,
          .panel[data-theme="dark"] .summary-card,
          .panel[data-theme="dark"] .sql-item,
          .panel[data-theme="dark"] .redirect-chain-item,
          .panel[data-theme="dark"] .log-card,
          .panel[data-theme="dark"] .history-card,
          .panel[data-theme="dark"] .perf-group-card,
          .panel[data-theme="dark"] .api-group-card,
          .panel[data-theme="dark"] .history-item {
            background: rgba(255,255,255,0.03);
            border-color: rgba(255,255,255,0.08);
            box-shadow: none;
          }
          .panel[data-theme="dark"] .metric,
          .panel[data-theme="dark"] .cache-op-card,
          .panel[data-theme="dark"] .cache-kpi,
          .panel[data-theme="dark"] .auth-identity-item,
          .panel[data-theme="dark"] .perf-metric,
          .panel[data-theme="dark"] .row,
          .panel[data-theme="dark"] .history-kpi {
            background: transparent;
            border-color: rgba(255,255,255,0.08);
            box-shadow: none;
          }
          .panel[data-theme="dark"] .property-table {
            background: rgba(255,255,255,0.03);
            border-color: rgba(255,255,255,0.08);
            box-shadow: none;
          }
          .panel[data-theme="dark"] .hero-request-bar,
          .panel[data-theme="dark"] .code-block,
          .panel[data-theme="dark"] .json,
          .panel[data-theme="dark"] .sql-query,
          .panel[data-theme="dark"] .sql-bindings,
          .panel[data-theme="dark"] .redirect-chain-arrow {
            background: #0d0d10;
            border-color: rgba(255,255,255,0.08);
            color: #ebebf0;
            box-shadow: none;
          }
          .panel[data-theme="dark"] .hero-url,
          .panel[data-theme="dark"] .hero-copy,
          .panel[data-theme="dark"] .summary-note,
          .panel[data-theme="dark"] .metric-note,
          .panel[data-theme="dark"] .history-duration,
          .panel[data-theme="dark"] .history-captured,
          .panel[data-theme="dark"] .history-request-id,
          .panel[data-theme="dark"] .history-card-note,
          .panel[data-theme="dark"] .history-chart-axis,
          .panel[data-theme="dark"] .key,
          .panel[data-theme="dark"] .log-time,
          .panel[data-theme="dark"] .api-meta-chip,
          .panel[data-theme="dark"] .sql-bindings,
          .panel[data-theme="dark"] .redirect-chain-arrow,
          .panel[data-theme="dark"] .muted-value,
          .panel[data-theme="dark"] .no-data,
          .panel[data-theme="dark"] .eyebrow,
          .panel[data-theme="dark"] .subsection-title,
          .panel[data-theme="dark"] .property-table th,
          .panel[data-theme="dark"] .pill {
            color: #888899;
          }
          .panel[data-theme="dark"] .hero-title,
          .panel[data-theme="dark"] .summary-value,
          .panel[data-theme="dark"] .metric-value,
          .panel[data-theme="dark"] .section-title-main,
          .panel[data-theme="dark"] .history-route,
          .panel[data-theme="dark"] .history-card-value,
          .panel[data-theme="dark"] .history-route-count,
          .panel[data-theme="dark"] .history-error-path,
          .panel[data-theme="dark"] .val,
          .panel[data-theme="dark"] .log-message,
          .panel[data-theme="dark"] .sql-query,
          .panel[data-theme="dark"] .redirect-chain-path,
          .panel[data-theme="dark"] .api-path,
          .panel[data-theme="dark"] .property-table td,
          .panel[data-theme="dark"] .json,
          .panel[data-theme="dark"] .json__value {
            color: #ebebf0;
          }
          .panel[data-theme="dark"] .theme-switch,
          .panel[data-theme="dark"] .hero-status,
          .panel[data-theme="dark"] .hero-request-copy {
            background: #18181B;
            border-color: rgba(255,255,255,0.08);
            color: #ebebf0;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
          }
          .panel[data-theme="dark"] .hero-request-copy:hover,
          .panel[data-theme="dark"] .theme-switch:hover {
            background: rgba(255,255,255,0.08);
          }
          .panel[data-theme="dark"] .hero-method-pill,
          .panel[data-theme="dark"] .api-method {
            background: rgba(34, 197, 94, 0.14);
            border-color: rgba(34, 197, 94, 0.20);
            color: #22c55e;
          }
          .panel[data-theme="dark"] .summary-card,
          .panel[data-theme="dark"] .metric,
          .panel[data-theme="dark"] .history-item,
          .panel[data-theme="dark"] .history-card,
          .panel[data-theme="dark"] .history-kpi,
          .panel[data-theme="dark"] .cache-kpi,
          .panel[data-theme="dark"] .nav-group-divider,
          .panel[data-theme="dark"] .history-route-table,
          .panel[data-theme="dark"] .cache-detail-table,
          .panel[data-theme="dark"] .auth-table,
          .panel[data-theme="dark"] .request-table,
          .panel[data-theme="dark"] .history-error-item,
          .panel[data-theme="dark"] .history-search,
          .panel[data-theme="dark"] .history-toggle,
          .panel[data-theme="dark"] .history-range-pill,
          .panel[data-theme="dark"] .history-empty-state {
            border-color: rgba(255,255,255,0.07);
          }
          .panel[data-theme="dark"] .history-item {
            background: rgba(255,255,255,0.03);
            border-color: rgba(255,255,255,0.08);
          }
          .panel[data-theme="dark"] .history-card {
            background: rgba(255,255,255,0.03);
            border-color: rgba(255,255,255,0.08);
            box-shadow: none;
          }
          .panel[data-theme="dark"] .history-kpi,
          .panel[data-theme="dark"] .history-route-table,
          .panel[data-theme="dark"] .cache-detail-table,
          .panel[data-theme="dark"] .auth-table,
          .panel[data-theme="dark"] .request-table,
          .panel[data-theme="dark"] .history-error-item,
          .panel[data-theme="dark"] .history-search,
          .panel[data-theme="dark"] .history-toggle,
          .panel[data-theme="dark"] .history-range-pill,
          .panel[data-theme="dark"] .history-empty-state {
            background: transparent;
            box-shadow: none;
          }
          .panel[data-theme="dark"] .nav-group-divider {
            background: rgba(255,255,255,0.06);
            box-shadow: none;
          }
          .panel[data-theme="dark"] .history-loading {
            background: rgba(255,255,255,0.02);
            border-color: rgba(255,255,255,0.10);
            color: #888899;
          }
          .panel[data-theme="dark"] .api-meta-chip,
          .panel[data-theme="dark"] .pill,
          .panel[data-theme="dark"] .sql-rows,
          .panel[data-theme="dark"] .redirect-chain-method,
          .panel[data-theme="dark"] .redirect-chain-status,
          .panel[data-theme="dark"] .history-method {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.08);
            color: #888899;
          }
          .panel[data-theme="dark"] .history-method-chip {
            background: transparent;
            border-color: transparent;
            color: #cbd5e1;
          }
          .panel[data-theme="dark"] .history-method-chip[data-method="GET"] { color: #22c55e; }
          .panel[data-theme="dark"] .history-method-chip[data-method="POST"] { color: #2dd4bf; }
          .panel[data-theme="dark"] .history-method-chip[data-method="PUT"] { color: #fbbf24; }
          .panel[data-theme="dark"] .history-method-chip[data-method="PATCH"] { color: #fb923c; }
          .panel[data-theme="dark"] .history-method-chip[data-method="DELETE"] { color: #f87171; }
          .panel[data-theme="dark"] .history-method-chip[data-method="OPTIONS"] { color: #60a5fa; }
          .panel[data-theme="dark"] .history-method-chip[data-method="HEAD"] { color: #c4b5fd; }
          .panel[data-theme="dark"] .history-toggle.active {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.1);
            color: #ffffff;
          }
          .panel[data-theme="dark"] .history-range-pill.active {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.16);
            color: #ffffff;
          }
          .panel[data-theme="dark"] .history-route-table th {
            background: rgba(255,255,255,0.04);
          }
          .panel[data-theme="dark"] .cache-detail-table th {
            background: rgba(255,255,255,0.04);
          }
          .panel[data-theme="dark"] .auth-table th,
          .panel[data-theme="dark"] .request-table th {
            background: rgba(255,255,255,0.04);
          }
          .panel[data-theme="dark"] .history-route-table td,
          .panel[data-theme="dark"] .cache-detail-table td,
          .panel[data-theme="dark"] .auth-table td,
          .panel[data-theme="dark"] .request-table td,
          .panel[data-theme="dark"] .history-search,
          .panel[data-theme="dark"] .history-toggle,
          .panel[data-theme="dark"] .history-range-pill {
            color: #ebebf0;
          }
          .panel[data-theme="dark"] .history-search::placeholder {
            color: #55566a;
          }
          .panel[data-theme="dark"] .history-error-text {
            color: #888899;
          }
          .panel[data-theme="dark"] .history-error-meta {
            color: #55566a;
          }
          .panel[data-theme="dark"] .history-route-table tbody tr:hover td {
            background: rgba(255,255,255,0.03);
          }
          .panel[data-theme="dark"] .cache-detail-table tbody tr:hover td {
            background: rgba(255,255,255,0.03);
          }
          .panel[data-theme="dark"] .cache-meta-text,
          .panel[data-theme="dark"] .cache-store-driver {
            color: #888899;
          }
          .panel[data-theme="dark"] .cache-value-summary,
          .panel[data-theme="dark"] .cache-value-preview,
          .panel[data-theme="dark"] .cache-store-name,
          .panel[data-theme="dark"] .cache-key-cell {
            color: #ebebf0;
          }
          .panel[data-theme="dark"] {
            --dp-scrollbar-thumb: rgba(255,255,255,0.16);
            --dp-scrollbar-thumb-hover: rgba(255,255,255,0.28);
          }
          .panel[data-theme="dark"] .history-grid-line {
            stroke: rgba(255,255,255,0.08);
          }
          .panel[data-theme="dark"] .history-bar-success {
            fill: rgba(42,180,182,0.72);
          }
          .panel[data-theme="dark"] .history-bar-warning {
            fill: rgba(245,158,11,0.74);
          }
          .panel[data-theme="dark"] .history-bar-error {
            fill: rgba(239,68,68,0.74);
          }
          .panel[data-theme="dark"] .history-line-avg {
            stroke: rgba(255,255,255,0.20);
          }
          .panel[data-theme="dark"] .history-line-max {
            stroke: rgba(245,158,11,0.90);
          }
          .panel[data-theme="dark"] .log-level {
            background: rgba(42, 180, 182, 0.12);
            border: 1px solid rgba(42, 180, 182, 0.18);
            color: #2ab4b6;
          }
          .panel[data-theme="dark"] .sql-time,
          .panel[data-theme="dark"] .redirect-chain-duration,
          .panel[data-theme="dark"] .badge-success {
            background: rgba(34, 197, 94, 0.12);
            border-color: rgba(34, 197, 94, 0.18);
            color: #22c55e;
          }
          .panel[data-theme="dark"] .badge-info {
            background: rgba(96, 165, 250, 0.12);
            border-color: rgba(96, 165, 250, 0.18);
            color: #60a5fa;
          }
          .panel[data-theme="dark"] .badge-warning {
            background: rgba(245, 158, 11, 0.12);
            border-color: rgba(245, 158, 11, 0.18);
            color: #f59e0b;
          }
          .panel[data-theme="dark"] .badge-error {
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.18);
            color: #ef4444;
          }
          .panel[data-theme="dark"] .history-open {
            color: #60a5fa;
          }
          .panel[data-theme="dark"] .hero-dot {
            background: currentColor;
          }
          .panel[data-theme="dark"] .property-table th {
            background: rgba(255,255,255,0.04);
          }
          .panel[data-theme="dark"] .no-data {
            background: rgba(255,255,255,0.02);
            border-color: rgba(255,255,255,0.10);
            color: #55566a;
            box-shadow: none;
          }
          .panel[data-theme="dark"] .section-copy,
          .panel[data-theme="dark"] .metric-label,
          .panel[data-theme="dark"] .summary-label {
            color: #55566a;
          }
          .panel[data-theme="dark"] .hero-request-copy-icon {
            filter: brightness(1.6);
          }
          .panel[data-theme="dark"] .json__item--collapsible::before {
            color: #60a5fa;
          }
          .panel[data-theme="dark"] .json__item--collapsible::after {
            background: rgba(255, 255, 255, 0.12);
          }
          .panel[data-theme="dark"] .json__key {
            color: #60a5fa;
          }
          .panel[data-theme="dark"] .json__value--string {
            color: #22c55e;
          }
          .panel[data-theme="dark"] .json__value--number {
            color: #a78bfa;
          }
          .panel[data-theme="dark"] .json__value--boolean {
            color: #f59e0b;
          }
          .panel[data-theme="dark"] .json__value--object,
          .panel[data-theme="dark"] .json__value--type-object,
          .panel[data-theme="dark"] .json__value--type-array {
            color: #ef8354;
          }

          .ov-dashboard {
            display: grid;
            gap: 22px;
            margin-top: 4px;
          }
          .ov-group {
            display: grid;
            gap: 12px;
            min-width: 0;
          }
          .ov-group + .ov-group {
            padding-top: 18px;
            border-top: 1px solid rgba(255,255,255,0.08);
          }
          .ov-group-label {
            font-size: 10px;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #55566a;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
          }
          .ov-group-label span {
            color: #55566a;
            font-weight: 600;
            letter-spacing: .08em;
          }
          .ov-section-label {
            font-size: 13px;
            letter-spacing: -.01em;
            text-transform: none;
            color: #ffffff;
            font-weight: 700;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
          }
          .ov-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
          }
          .ov-app-stack {
            display: grid;
            gap: 12px;
          }
          .ov-kpi-chip {
            font-size: 11px;
            font-weight: 800;
            color: #888899;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            padding: 4px 8px;
            border-radius: 6px;
            white-space: nowrap;
          }
          .ov-kpi-warn {
            color: #f59e0b;
            background: rgba(245,158,11,0.09);
            border-color: rgba(245,158,11,0.16);
          }
          .ov-app-headline {
            font-size: 18px;
            font-weight: 900;
            color: #ebebf0;
            margin: 8px 0 4px;
            line-height: 1.2;
            letter-spacing: -.02em;
          }
          .ov-app-sub {
            font-size: 12px;
            color: #888899;
            font-weight: 600;
            margin-bottom: 12px;
          }
          .ov-stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-top: 12px;
          }
          .ov-stat-card {
            min-width: 0;
            padding: 10px 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            border-right: 1px solid rgba(255,255,255,0.08);
            padding-right: 12px;
          }
          .ov-stat-card:last-child {
            border-right: 0;
            padding-right: 0;
          }
          .ov-stat-label {
            font-size: 10px;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #66687e;
            font-weight: 800;
            margin-bottom: 6px;
          }
          .ov-stat-value {
            line-height: 1.2;
            color: #ebebf0;
            font-weight: 900;
            overflow-wrap: anywhere;
          }
          .ov-stat-note {
            margin-top: 4px;
            font-size: 11px;
            line-height: 1.35;
            color: #888899;
            font-weight: 600;
            overflow-wrap: anywhere;
          }
          .ov-chart-wrap {
            margin-top: 12px;
          }
          .ov-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
          }
          .ov-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
            max-width: 100%;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.1);
            background: transparent;
            color: #888899;
            font-size: 11px;
            font-weight: 700;
          }
          .ov-chip-value {
            color: #ebebf0;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }
          .perf-bar-list {
            display: grid;
            gap: 10px;
            margin-top: 12px;
          }
          .perf-bar-item {
            display: grid;
            gap: 6px;
          }
          .perf-bar-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-width: 0;
          }
          .perf-bar-label {
            color: #ebebf0;
            font-size: 12px;
            font-weight: 800;
            min-width: 0;
          }
          .perf-bar-value {
            color: #888899;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
          }
          .perf-bar-track {
            position: relative;
            height: 4px;
            border-radius: 0;
            background: rgba(255,255,255,0.08);
            overflow: hidden;
          }
          .perf-bar-fill {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: 0;
          }
          .perf-bar-fill-sql {
            background: linear-gradient(90deg, rgba(96,165,250,0.95), rgba(59,130,246,0.7));
          }
          .perf-bar-fill-outbound {
            background: linear-gradient(90deg, rgba(45,212,191,0.9), rgba(20,184,166,0.68));
          }
          .perf-bar-fill-app {
            background: linear-gradient(90deg, rgba(245,158,11,0.88), rgba(217,119,6,0.66));
          }
          .perf-flag-list {
            display: grid;
            gap: 7px;
            margin-top: 10px;
          }
          .perf-flag-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 10px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
          }
          .perf-flag-copy {
            color: #cdd5e4;
            font-size: 12px;
            font-weight: 700;
            min-width: 0;
          }
          .ov-slow-table-scroll {
            margin-top: 12px;
            max-height: 320px;
            overflow: auto;
            border-radius: 0;
            border: 1px solid rgba(255,255,255,0.08);
            background: transparent;
            -webkit-overflow-scrolling: touch;
          }
          .ov-slow-table {
            width: 100%;
            min-width: 560px;
            border-collapse: separate;
            border-spacing: 0;
          }
          .ov-slow-table th,
          .ov-slow-table td {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            text-align: left;
            vertical-align: middle;
          }
          .ov-slow-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #1f2024;
            color: #7f89a6;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .14em;
            text-transform: uppercase;
            white-space: nowrap;
          }
          .ov-slow-table tbody td {
            color: #ebebf0;
            font-size: 13px;
            font-weight: 700;
          }
          .ov-slow-table tbody tr:hover td {
            background: rgba(56,189,248,0.04);
          }
          .ov-slow-table tbody tr:last-child td {
            border-bottom: 0;
          }
          .ov-slow-col-requests,
          .ov-slow-col-min,
          .ov-slow-col-avg,
          .ov-slow-col-peak {
            white-space: nowrap;
          }
          .ov-slow-col-route {
            max-width: 240px;
          }
          .ov-slow-route-cell {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
          }
          .ov-slow-col-route .history-path-cell {
            max-width: 160px;
          }
          .ov-p95-chip {
            flex: 0 0 auto;
            font-size: 11px;
            font-weight: 800;
            color: #f59e0b;
            background: rgba(245,158,11,0.09);
            border: 1px solid rgba(245,158,11,0.16);
            padding: 3px 7px;
            border-radius: 6px;
            white-space: nowrap;
          }
          .ov-empty {
            padding: 16px 0;
            color: #888899;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
            background: transparent;
            border-radius: 0;
            border: 0;
            border-top: 1px solid rgba(255,255,255,0.08);
            margin-top: 10px;
          }

          @media (max-width: 1100px) {
            .history-col-3xx,
            .history-col-4xx,
            .history-col-5xx {
              display: none;
            }
          }
          @media (max-width: 920px) {
            .history-col-avg {
              display: none;
            }
          }
          @media (max-width: 820px) {
            .panel { width: 100%; padding: 12px; }
            .workspace {
              grid-template-columns: 48px minmax(0, 1fr);
              gap: 0;
              margin: -12px 0 -12px -12px;
            }
            .sidebar { width: 48px; padding: 0; }
            .sidebar-brand { padding-top: 12px; }
            .canvas { padding: 12px; }
            .hero-top, .row { flex-direction: column; }
            .hero-head {
              width: 100%;
            }
            .history-overview-grid,
            .history-kpis,
            .cache-kpis,
            .ov-two-col,
            .perf-two-col,
            .stats-grid { grid-template-columns: 1fr; }
            .perf-metrics {
              grid-template-columns: 1fr;
            }
            .perf-metric {
              padding: 10px 0;
              border-left: 0;
              border-bottom: 1px solid rgba(255,255,255,0.08);
            }
            .perf-metric:last-child {
              border-bottom: 0;
              padding-bottom: 0;
            }
            .cache-kpis {
              flex-direction: column;
            }
            .cache-kpi {
              padding: 10px 0;
              border-left: 0;
              border-bottom: 1px solid rgba(255,255,255,0.08);
            }
            .cache-kpi:last-child {
              border-bottom: 0;
              padding-bottom: 0;
            }
            .auth-identity {
              flex-direction: column;
            }
            .auth-identity-item {
              padding: 10px 0;
              border-left: 0;
              border-bottom: 1px solid rgba(255,255,255,0.08);
            }
            .auth-identity-item:last-child {
              border-bottom: 0;
              padding-bottom: 0;
            }
            .history-table-head,
            .history-search-wrap {
              align-items: stretch;
            }
            .history-search {
              min-width: 100%;
            }
            .ov-slow-table {
              min-width: 0;
            }
            .ov-stat-grid {
              grid-template-columns: 1fr;
            }
            .metrics,
            .history-item {
              grid-template-columns: 1fr;
            }
            .history-col-max {
              display: none;
            }
            .cache-col-store,
            .cache-col-meta {
              display: none;
            }
            .key { min-width: 0; }
          }
        `;
        root.innerHTML = '';
        const style = document.createElement('style');
        style.textContent = css;
        const wrap = document.createElement('div');
        wrap.className = 'panel';
        
        // Build SQL section
        let sqlSection = '';
        if(data.sql && Array.isArray(data.sql) && data.sql.length > 0){
          const totalCount = data.sql_total_count || data.sql.length;
          const totalTime = data.sql_total_time_ms?.toFixed?.(2) ?? data.sql_total_time_ms ?? 0;
          const slowCount = Number(data.sql_slow_count || 0);
          const nPlusOneCount = Number(data.sql_n_plus_one_count || 0);
          const nPlusOneHints = Array.isArray(data.sql_n_plus_one_hints) ? data.sql_n_plus_one_hints : [];
          sqlSection = `
            <div class="sql-shell">
              <div class="section-title">
                <span class="section-title-main">Database Queries</span>
                <span>
                  <span class="badge badge-info">${totalCount} queries</span>
                  <span class="badge badge-success">${totalTime} ms</span>
                  ${slowCount > 0 ? `<span class="badge badge-warning">${slowCount} slow</span>` : ''}
                  ${nPlusOneCount > 0 ? `<span class="badge badge-error">${nPlusOneCount} N+1</span>` : ''}
                </span>
              </div>
              ${nPlusOneHints.length ? `
                <div class="subsection" style="margin-bottom:14px;">
                  <div class="subsection-title">Potential N+1 patterns</div>
                  <div class="pill-row">
                    ${nPlusOneHints.map((hint) => `
                      <span class="pill">
                        ${(hint.connection_name || 'db')} ${escapeHtml(hint.sql || '').slice(0, 60)}${(hint.sql || '').length > 60 ? '…' : ''} • ${escapeHtml(String(hint.duplicate_count || 0))}x • ${escapeHtml(String(hint.binding_variant_count || 0))} variants
                      </span>
                    `).join('')}
                  </div>
                </div>
              ` : ''}
              <div class="sql-list">
                ${data.sql.map((q, idx) => {
                  const duration = q.duration_ms?.toFixed?.(2) ?? q.duration_ms ?? 0;
                  const rowCount = q.row_count !== null && q.row_count !== undefined ? q.row_count : '?';
                  const bindings = q.bindings && Object.keys(q.bindings).length > 0 
                    ? escapeHtml(JSON.stringify(q.bindings)) 
                    : '';
                  const error = q.error ? `<div class="sql-error">Error: ${escapeHtml(q.error)}</div>` : '';
                  const metaPills = [
                    q.connection_name ? `Connection: ${escapeHtml(q.connection_name)}` : '',
                    q.driver_name ? `Driver: ${escapeHtml(String(q.driver_name).toUpperCase())}` : '',
                    q.transaction_active === true ? 'Transaction: active' : (q.transaction_active === false ? 'Transaction: none' : ''),
                    q.is_slow ? `Slow threshold ${escapeHtml(String(data.sql_slow_threshold_ms || 0))}ms` : '',
                    q.duplicate_count > 1 ? `Duplicate ${escapeHtml(String(q.duplicate_count))}x` : '',
                    q.n_plus_one_suspected ? `N+1 suspicion (${escapeHtml(String(q.binding_variant_count || 0))} variants)` : '',
                  ].filter(Boolean);
                  return `
                    <div class="sql-item">
                      <div class="sql-header">
                        <div>
                          <span class="badge badge-info">#${idx + 1}</span>
                          <span class="sql-time">${duration} ms</span>
                          <span class="sql-rows">${rowCount} rows</span>
                          ${q.is_slow ? `<span class="badge badge-warning">Slow</span>` : ''}
                          ${q.n_plus_one_suspected ? `<span class="badge badge-error">N+1</span>` : ''}
                        </div>
                      </div>
                      ${metaPills.length ? `<div class="pill-row" style="margin:0 0 12px 0;">${metaPills.map((pill) => `<span class="pill">${pill}</span>`).join('')}</div>` : ''}
                      <div class="sql-query">${escapeHtml(q.sql || 'N/A')}</div>
                      ${bindings ? `<div class="sql-bindings">Bindings: ${bindings}</div>` : ''}
                      ${error}
                    </div>
                  `;
                }).join('')}
              </div>
            </div>
          `;
        } else {
          sqlSection = `
            <div class="sql-shell">
              <div class="section-title"><span class="section-title-main">Database Queries</span></div>
              <div class="no-data">No database queries detected</div>
            </div>
          `;
        }
        
        // Build redirect chain section
        let redirectChainSection = '';
        const host = document.getElementById('doppar-profiler');
        const chainData = host ? host.dataset.redirectChain : null;
        if (chainData && chainData !== '[]') {
          try {
            const chain = JSON.parse(chainData);
            if (chain && chain.length > 0) {
              const chainItems = chain.map((item, idx) => {
                const itemStatus = item.status || '?';
                const itemMethod = item.method || 'GET';
                const itemPath = item.route || item.url || '/';
                const itemDuration = item.duration_ms ? item.duration_ms.toFixed(1) : '0.0';
                const itemRedirectUrl = item.redirect_url || '';
                return `
                  <div class="redirect-chain-item">
                    <div class="redirect-chain-header">
                      <span class="badge badge-info">#${idx + 1}</span>
                      <span class="redirect-chain-status">${itemStatus}</span>
                      <span class="redirect-chain-method">${escapeHtml(itemMethod)}</span>
                      <span class="redirect-chain-path">${escapeHtml(itemPath)}</span>
                      <span class="redirect-chain-duration">${itemDuration} ms</span>
                    </div>
                    ${itemRedirectUrl ? `<div class="redirect-chain-arrow">Redirected to: ${escapeHtml(itemRedirectUrl)}</div>` : ''}
                  </div>
                `;
              }).join('');
              
              redirectChainSection = `
                <div class="section">
                  <div class="section-title">
                    <span class="section-title-main">Redirect Chain</span>
                    <span class="badge badge-warning">${chain.length} redirect${chain.length > 1 ? 's' : ''}</span>
                  </div>
                  <div class="redirect-chain-list">
                    ${chainItems}
                    <div class="redirect-chain-item redirect-chain-current">
                      <div class="redirect-chain-header">
                        <span class="badge badge-success">Current</span>
                        <span class="redirect-chain-status">${data.status}</span>
                        <span class="redirect-chain-method">${escapeHtml(data.method)}</span>
                        <span class="redirect-chain-path">${escapeHtml(data.route)}</span>
                        <span class="redirect-chain-duration">${(data.duration_ms?.toFixed?.(1) ?? data.duration_ms)} ms</span>
                      </div>
                    </div>
                  </div>
                </div>
              `;
            }
          } catch (e) {
            console.error('Failed to parse redirect chain:', e);
          }
        }
        
        // Build auth section
        let authSection = `
          <div class="auth-shell">
            <div class="section-title"><span class="section-title-main">Authentication</span></div>
            <div class="no-data">No authenticated user context captured for this request</div>
          </div>
        `;
        if (data.auth_authenticated) {
          const userName = escapeHtml(data.auth_user_name || 'User');
          const userEmail = escapeHtml(data.auth_user_email || '');
          const authMeta = {};
          if (data.auth_user_id !== null && data.auth_user_id !== undefined) authMeta['User ID'] = data.auth_user_id;
          if (data.auth_guard) authMeta['Guard'] = data.auth_guard;
          const authIdentityItems = [
            `<div class="auth-identity-item"><div class="auth-identity-label">User</div><div class="auth-identity-value">${userName}</div></div>`,
            userEmail ? `<div class="auth-identity-item"><div class="auth-identity-label">Email</div><div class="auth-identity-value">${userEmail}</div></div>` : '',
            authMeta['User ID'] !== undefined ? `<div class="auth-identity-item"><div class="auth-identity-label">User ID</div><div class="auth-identity-value">${escapeHtml(String(authMeta['User ID']))}</div></div>` : '',
            authMeta['Guard'] ? `<div class="auth-identity-item"><div class="auth-identity-label">Guard</div><div class="auth-identity-value">${escapeHtml(String(authMeta['Guard']))}</div></div>` : '',
          ].filter(Boolean).join('');
          authSection = `
            <div class="auth-shell">
              <div class="section-title">
                <span class="section-title-main">Authentication</span>
                <span class="badge badge-success">Authenticated</span>
              </div>
              <div class="auth-identity">${authIdentityItems}</div>
              ${hasEntries(data.auth_user) ? `
                <div class="auth-block">
                  <div class="subsection-title">User Payload</div>
                  ${buildPropertyTable(data.auth_user, 'Property', 'Value', 'auth-table')}
                </div>
              ` : ''}
            </div>
          `;
        }


        const requestDetailsSection = `
          <div class="request-shell">
            <div class="section-title"><span class="section-title-main">Request Payload</span></div>
            <div class="request-stack">
              ${buildRequestBlock('Headers', buildPropertyTable(data.request_headers, 'Header', 'Value', 'request-table'))}
              ${buildRequestBlock('Query Parameters', buildPropertyTable(data.request_query, 'Parameter', 'Value', 'request-table'))}
              ${buildRequestBlock('POST Parameters', buildPropertyTable(data.request_params, 'Parameter', 'Value', 'request-table'))}
              ${buildRequestBlock('Request Body', buildCodeBlock(data.request_body))}
              ${buildRequestBlock('Cookies', buildPropertyTable(data.request_cookies, 'Cookie', 'Value', 'request-table'))}
              ${buildRequestBlock('Uploaded Files', buildFilesTable(data.request_files))}
              ${buildRequestBlock('Server', buildPropertyTable(data.request_server, 'Variable', 'Value', 'request-table'))}
            </div>
            ${!hasEntries(data.request_headers) && !hasEntries(data.request_query) && !hasEntries(data.request_params) && !data.request_body && !hasEntries(data.request_cookies) && !hasEntries(data.request_files) && !hasEntries(data.request_server) ? '<div class="no-data">No detailed request payload captured</div>' : ''}
          </div>
        `;

        const historySection = `
          <div class="history-dashboard">
            <div class="history-header">
              <div>
                <div class="section-title"><span class="section-title-main">Requests</span></div>
                <p class="section-copy">Time-filtered request history with route-level status and latency trends.</p>
              </div>
              <div class="history-range-pills" data-history-ranges></div>
            </div>
            <div class="history-shell" data-history-shell>
              <div class="history-loading">Loading request history...</div>
            </div>
          </div>
        `;

        const perfDurationMs = Number(data.duration_ms || 0);
        const perfSqlMs = Number(data.sql_total_time_ms || 0);
        const perfOutboundMs = Number(data.http_requests_total_time_ms || 0);
        const perfAccountedMs = Math.min(perfDurationMs, perfSqlMs + perfOutboundMs);
        const perfAppMs = Math.max(0, perfDurationMs - perfAccountedMs);
        const perfSqlPct = perfDurationMs > 0 ? Math.min(100, (perfSqlMs / perfDurationMs) * 100) : 0;
        const perfOutboundPct = perfDurationMs > 0 ? Math.min(100, (perfOutboundMs / perfDurationMs) * 100) : 0;
        const perfAppPct = perfDurationMs > 0 ? Math.max(0, 100 - perfSqlPct - perfOutboundPct) : 0;
        const perfCacheReads = Number(data.cache_hits || 0) + Number(data.cache_misses || 0);
        const perfCacheHitRate = perfCacheReads > 0 ? ((Number(data.cache_hits || 0) / perfCacheReads) * 100).toFixed(1) : '0.0';
        const perfWarnings = [
          Number(data.sql_slow_count || 0) > 0 ? { label: 'Slow queries detected', value: `${escapeHtml(data.sql_slow_count || 0)} slow` } : null,
          Number(data.sql_n_plus_one_count || 0) > 0 ? { label: 'Potential N+1 patterns', value: `${escapeHtml(data.sql_n_plus_one_count || 0)} hint${Number(data.sql_n_plus_one_count || 0) === 1 ? '' : 's'}` } : null,
          Number(data.cache_misses || 0) > 0 ? { label: 'Cache misses in this request', value: `${escapeHtml(data.cache_misses || 0)} miss${Number(data.cache_misses || 0) === 1 ? '' : 'es'}` } : null,
          Number(data.http_requests_count || 0) > 0 ? { label: 'Outgoing HTTP calls made', value: `${escapeHtml(data.http_requests_count || 0)} request${Number(data.http_requests_count || 0) === 1 ? '' : 's'}` } : null,
          Number(data.redirect_count || 0) > 0 ? { label: 'Redirect chain followed', value: `${escapeHtml(data.redirect_count || 0)} hop${Number(data.redirect_count || 0) === 1 ? '' : 's'}` } : null,
          data.exception_class ? { label: 'Exception captured', value: escapeHtml(String(data.exception_class).split(String.fromCharCode(92)).pop() || data.exception_class) } : null,
        ].filter(Boolean);
        const buildPerfMetricGrid = (metrics) => `
          <div class="perf-metrics">
            ${metrics.map((metric) => `
              <div class="perf-metric">
                <div class="cache-kpi-label">${escapeHtml(metric.label)}</div>
                <div class="cache-kpi-value">${metric.value}</div>
              </div>
            `).join('')}
          </div>
        `;
        const performanceSection = `
          <div class="perf-shell">
            <div class="section-title">
              <span class="section-title-main">Performance Profile</span>
              <span>
                <span class="badge badge-info">${escapeHtml(perfDurationMs.toFixed(1))} ms</span>
                <span class="badge ${perfWarnings.length ? 'badge-warning' : 'badge-success'}">${perfWarnings.length ? `${perfWarnings.length} flags` : 'Healthy'}</span>
              </span>
            </div>
            <div class="perf-group-card">
              <div class="perf-group-head">
                <div class="perf-group-title">Overview</div>
              </div>
              <div class="cache-kpis">
                <div class="cache-kpi">
                  <div class="cache-kpi-label">Duration</div>
                  <div class="cache-kpi-value">${escapeHtml(data.duration_ms?.toFixed?.(1) ?? data.duration_ms)} ms</div>
                </div>
                <div class="cache-kpi">
                  <div class="cache-kpi-label">Peak Memory</div>
                  <div class="cache-kpi-value">${((data.memory_peak || 0) / (1024*1024)).toFixed(2)} MB</div>
                </div>
                <div class="cache-kpi">
                  <div class="cache-kpi-label">Response Size</div>
                  <div class="cache-kpi-value">${formatBytes(data.response_body_size || 0)}</div>
                </div>
                <div class="cache-kpi">
                  <div class="cache-kpi-label">Outgoing HTTP</div>
                  <div class="cache-kpi-value">${escapeHtml(data.http_requests_count || 0)}</div>
                </div>
              </div>
            </div>
            <div class="perf-section">
              <div class="perf-section-label">Timing & Request Signals</div>
              <div class="perf-two-col">
                <div class="perf-group-card">
                  <div class="perf-group-head">
                    <div class="perf-group-title">Timing Mix</div>
                  </div>
                  <div class="ov-app-headline">${escapeHtml(perfAppMs.toFixed(2))} ms stayed inside app work</div>
                  <div class="ov-app-sub">Breakdown of observed request time between SQL, outbound HTTP, and everything else in the app lifecycle.</div>
                  <div class="perf-bar-list">
                    <div class="perf-bar-item">
                      <div class="perf-bar-head">
                        <span class="perf-bar-label">Database time</span>
                        <span class="perf-bar-value">${escapeHtml(perfSqlMs.toFixed(2))} ms • ${escapeHtml(perfSqlPct.toFixed(1))}%</span>
                      </div>
                      <div class="perf-bar-track"><span class="perf-bar-fill perf-bar-fill-sql" style="width:${perfSqlPct.toFixed(1)}%"></span></div>
                    </div>
                    <div class="perf-bar-item">
                      <div class="perf-bar-head">
                        <span class="perf-bar-label">Outbound HTTP time</span>
                        <span class="perf-bar-value">${escapeHtml(perfOutboundMs.toFixed(2))} ms • ${escapeHtml(perfOutboundPct.toFixed(1))}%</span>
                      </div>
                      <div class="perf-bar-track"><span class="perf-bar-fill perf-bar-fill-outbound" style="width:${perfOutboundPct.toFixed(1)}%"></span></div>
                    </div>
                    <div class="perf-bar-item">
                      <div class="perf-bar-head">
                        <span class="perf-bar-label">App / framework time</span>
                        <span class="perf-bar-value">${escapeHtml(perfAppMs.toFixed(2))} ms • ${escapeHtml(perfAppPct.toFixed(1))}%</span>
                      </div>
                      <div class="perf-bar-track"><span class="perf-bar-fill perf-bar-fill-app" style="width:${perfAppPct.toFixed(1)}%"></span></div>
                    </div>
                  </div>
                </div>
                <div class="perf-group-card">
                  <div class="perf-group-head">
                    <div class="perf-group-title">Request Signals</div>
                  </div>
                  <div class="ov-app-headline">${escapeHtml(data.response_status || data.status || 0)} response with ${escapeHtml(data.logs_total_count || 0)} log${Number(data.logs_total_count || 0) === 1 ? '' : 's'}</div>
                  <div class="ov-app-sub">Fast high-signal checks for issues that matter in a single request: slow SQL, N+1 hints, cache misses, redirects, and exceptions.</div>
                  ${perfWarnings.length ? `
                    <div class="perf-flag-list">
                      ${perfWarnings.map((flag) => `
                        <div class="perf-flag-item">
                          <span class="perf-flag-copy">${flag.label}</span>
                          <span class="ov-kpi-chip ov-kpi-warn">${flag.value}</span>
                        </div>
                      `).join('')}
                    </div>
                  ` : '<div class="ov-empty">No obvious performance warnings were detected in this request.</div>'}
                </div>
              </div>
            </div>
            <div class="perf-section">
              <div class="perf-section-label">Database & Response</div>
              <div class="perf-two-col">
                <div class="perf-group-card">
                  <div class="perf-group-head">
                    <div class="perf-group-title">Database Behavior</div>
                  </div>
                  ${buildPerfMetricGrid([
                    { label: 'SQL Time', value: `${escapeHtml(perfSqlMs.toFixed(2))} ms` },
                    { label: 'Slow Queries', value: escapeHtml(data.sql_slow_count || 0) },
                    { label: 'Duplicate Groups', value: escapeHtml(data.sql_duplicate_group_count || 0) },
                    { label: 'N+1 Hints', value: escapeHtml(data.sql_n_plus_one_count || 0) },
                    { label: 'Slow Threshold', value: `${escapeHtml(data.sql_slow_threshold_ms || 0)} ms` },
                    { label: 'Result Pressure', value: escapeHtml(data.sql_duplicate_total_count || 0) },
                  ])}
                </div>
                <div class="perf-group-card">
                  <div class="perf-group-head">
                    <div class="perf-group-title">Response + Cache</div>
                  </div>
                  ${buildPerfMetricGrid([
                    { label: 'Cache Hit Rate', value: `${perfCacheHitRate}%` },
                    { label: 'Cache Ops', value: escapeHtml(data.cache_total || 0) },
                    { label: 'Outbound Calls', value: escapeHtml(data.http_requests_count || 0) },
                    { label: 'Logs', value: escapeHtml(data.logs_total_count || 0) },
                    { label: 'Redirects', value: escapeHtml(data.redirect_count || 0) },
                    { label: 'Response Size', value: formatBytes(data.response_body_size || 0) },
                  ])}
                </div>
              </div>
            </div>
          </div>
        `;

        const responseStatusCode = Number(data.response_status ?? data.status ?? 0);
        const responseStatusText = data.response_status_text || '';
        const responseStatusLabel = responseStatusCode ? (responseStatusText ? `${responseStatusCode} - ${responseStatusText}` : String(responseStatusCode)) : 'Unknown';
        const responseHeaderCount = Number(data.response_header_count || (hasEntries(data.response_headers) ? Object.keys(data.response_headers).length : 0));
        const responseRedirectChain = Array.isArray(data.redirect_chain) ? data.redirect_chain : [];
        const responseRedirectCount = responseRedirectChain.length + (data.is_redirect ? 1 : 0);
        const responseInfo = {};
        if (responseStatusCode) responseInfo['Status Code'] = responseStatusText ? `${responseStatusCode} ${responseStatusText}` : responseStatusCode;
        if (data.response_classification) responseInfo['Classification'] = data.response_classification;
        if (data.response_content_type) responseInfo['Content Type'] = data.response_content_type;
        if (data.response_body_size !== undefined) responseInfo['Body Size'] = formatBytes(data.response_body_size);
        if (responseHeaderCount > 0) responseInfo['Header Count'] = responseHeaderCount;

        const responseHighlightSection = hasEntries(data.response_header_highlights)
          ? buildResponseBlock('Header Highlights', buildPropertyTable(data.response_header_highlights, 'Header', 'Value', 'request-table'))
          : '';
        const responseAllHeadersSection = hasEntries(data.response_headers)
          ? buildResponseBlock('All Headers', buildPropertyTable(data.response_headers, 'Header', 'Value', 'request-table'))
          : '';

        const responseRedirectSummary = (data.is_redirect && data.redirect_url) || responseRedirectCount > 0
          ? buildResponseBlock('Redirect Summary', buildPropertyTable({
              Redirected: data.is_redirect ? 'Yes' : 'No',
              'Redirect Count': responseRedirectCount,
              ...(data.redirect_url ? { Location: data.redirect_url } : {}),
            }, 'Property', 'Value', 'request-table'))
          : '';

        const responsePreviewSection = data.response_preview
          ? buildResponseBlock('Body Preview', `
              <div class="response-preview-head">
                <span class="api-meta">
                  <span class="api-meta-chip api-chip-mono">${escapeHtml(data.response_preview_format || 'text')} preview</span>
                  ${data.response_classification ? `<span class="api-meta-chip api-chip-teal">${escapeHtml(data.response_classification)}</span>` : ''}
                  ${data.response_preview_truncated ? `<span class="api-meta-chip api-chip-mono">Truncated</span>` : ''}
                </span>
              </div>
              <div class="response-preview-scroll">${buildCodeBlock(data.response_preview)}</div>
            `)
          : buildResponseBlock('Body Preview', `<div class="no-data">${data.response_classification === 'File Download' || data.response_classification === 'File Response' ? 'Preview skipped for file or binary response' : 'No response body preview captured'}</div>`);

        const responseSection = `
          <div class="response-shell">
            <div class="section-title">
              <span class="section-title-main">Response Details</span>
              <span>
                <span class="badge ${responseStatusCode >= 500 ? 'badge-error' : responseStatusCode >= 300 ? 'badge-warning' : 'badge-success'}">${escapeHtml(responseStatusLabel)}</span>
                <span class="badge badge-info">${escapeHtml(responseHeaderCount || 0)} headers</span>
              </span>
            </div>
            <div class="cache-kpis">
              <div class="cache-kpi">
                <div class="cache-kpi-label">Classification</div>
                <div class="cache-kpi-value">${escapeHtml(data.response_classification || 'HTTP Response')}</div>
              </div>
              <div class="cache-kpi">
                <div class="cache-kpi-label">Body Size</div>
                <div class="cache-kpi-value">${formatBytes(data.response_body_size || 0)}</div>
              </div>
              <div class="cache-kpi">
                <div class="cache-kpi-label">Headers</div>
                <div class="cache-kpi-value">${escapeHtml(responseHeaderCount || 0)}</div>
              </div>
              <div class="cache-kpi">
                <div class="cache-kpi-label">Redirects</div>
                <div class="cache-kpi-value">${escapeHtml(responseRedirectCount)}</div>
              </div>
            </div>
            <div class="response-stack">
              ${buildResponseBlock('Response', buildPropertyTable(responseInfo, 'Property', 'Value', 'request-table'))}
              ${responseHighlightSection}
              ${responseAllHeadersSection}
              ${responseRedirectSummary}
              ${responsePreviewSection}
            </div>
            ${!hasEntries(responseInfo) && !hasEntries(data.response_header_highlights) && !hasEntries(data.response_headers) && !(data.is_redirect && data.redirect_url) && !data.response_preview ? '<div class="no-data">No response details available</div>' : ''}
          </div>
        `;

        const cacheOperations = Array.isArray(data.cache_operations) ? data.cache_operations : [];
        const cacheReads = Number(data.cache_hits || 0) + Number(data.cache_misses || 0);
        const cacheHitRate = cacheReads > 0 ? ((Number(data.cache_hits || 0) / cacheReads) * 100).toFixed(1) : '0.0';
        const buildCacheStatusBadge = (operation) => {
          const type = String(operation.type || '');
          if(type === 'lock_prepare'){
            return '<span class="cache-status-badge cache-status-neutral">Ready</span>';
          }
          if(type === 'get' || type === 'has' || type === 'get_multiple'){
            return `<span class="cache-status-badge ${operation.hit ? 'cache-status-hit' : 'cache-status-miss'}">${operation.hit ? 'Hit' : 'Miss'}</span>`;
          }
          if(type.startsWith('lock_')){
            return `<span class="cache-status-badge ${operation.hit ? 'cache-status-pass' : 'cache-status-failed'}">${operation.hit ? 'Success' : 'Failed'}</span>`;
          }
          return `<span class="cache-status-badge cache-status-write">Write</span>`;
        };
        const buildCacheMetaLines = (operation) => {
          const bits = [];
          if(operation.ttl_seconds !== null && operation.ttl_seconds !== undefined){
            bits.push(`TTL ${escapeHtml(String(operation.ttl_seconds))}s`);
          }
          if(operation.expires_at){
            bits.push(`Expires ${escapeHtml(operation.expires_at)}`);
          }
          if(Array.isArray(operation.tags) && operation.tags.length){
            bits.push(`Tags ${escapeHtml(operation.tags.join(', '))}`);
          }
          if(operation.lock_action){
            bits.push(`Lock ${escapeHtml(operation.lock_action)}`);
          }
          if(operation.lock_seconds){
            bits.push(`Lock TTL ${escapeHtml(String(operation.lock_seconds))}s`);
          }
          if(operation.lock_wait_seconds){
            bits.push(`Wait ${escapeHtml(String(operation.lock_wait_seconds))}s`);
          }
          if(operation.delta !== undefined){
            bits.push(`Delta ${escapeHtml(String(operation.delta))}`);
          }
          if(operation.lock_owner){
            bits.push(`Owner ${escapeHtml(truncateText(operation.lock_owner, 32))}`);
          }

          return bits.length
            ? `<div class="cache-detail-meta">${bits.map((bit) => `<div class="cache-meta-text">${bit}</div>`).join('')}</div>`
            : '<span class="muted-value">No extra metadata</span>';
        };
        const buildCacheValueCell = (operation) => {
          if(operation.value_json){
            let body = '';
            try {
              body = buildJsonViewer(JSON.parse(operation.value_json));
            } catch (error) {
              body = buildCodeBlock(operation.value_json);
            }
            const preview = operation.value !== null && operation.value !== undefined && operation.value !== ''
              ? truncateText(String(operation.value), 84)
              : 'View payload';

            return `
              <details class="cache-value-details">
                <summary class="cache-value-summary"><span class="cache-value-summary-text">${escapeHtml(preview)}</span></summary>
                <div class="cache-value-body">${body}</div>
              </details>
            `;
          }
          if(operation.value !== null && operation.value !== undefined && operation.value !== ''){
            return `<div class="cache-value-preview">${escapeHtml(truncateText(String(operation.value), 120))}</div>`;
          }

          return '<span class="muted-value">No payload</span>';
        };
        const cacheSection = `
          <div class="cache-shell">
            <div class="section-title">
              <span class="section-title-main">Cache Activity</span>
              ${cacheOperations.length ? `
                <span>
                  <span class="badge badge-info">${escapeHtml(cacheOperations.length)} events</span>
                  <span class="badge badge-success">${cacheHitRate}% hit rate</span>
                </span>
              ` : ''}
            </div>
            ${cacheOperations.length ? `
              <div class="cache-kpis">
                <div class="cache-kpi">
                  <div class="cache-kpi-label">Operations</div>
                  <div class="cache-kpi-value">${escapeHtml(data.cache_total || cacheOperations.length)}</div>
                </div>
                <div class="cache-kpi">
                  <div class="cache-kpi-label">Hit Rate</div>
                  <div class="cache-kpi-value">${cacheHitRate}%</div>
                </div>
                <div class="cache-kpi">
                  <div class="cache-kpi-label">Writes</div>
                  <div class="cache-kpi-value">${escapeHtml(data.cache_writes || 0)}</div>
                </div>
                <div class="cache-kpi">
                  <div class="cache-kpi-label">Deletes</div>
                  <div class="cache-kpi-value">${escapeHtml(data.cache_deletes || 0)}</div>
                </div>
                <div class="cache-kpi">
                  <div class="cache-kpi-label">Locks</div>
                  <div class="cache-kpi-value">${escapeHtml(data.cache_lock_operations || 0)}</div>
                </div>
              </div>
              <div class="cache-table-scroll">
                <table class="cache-detail-table">
                  <thead>
                    <tr>
                      <th class="cache-col-type">Type</th>
                      <th class="cache-col-key">Key</th>
                      <th class="cache-col-value">Value</th>
                      <th class="cache-col-store">Store</th>
                      <th class="cache-col-status">Status</th>
                      <th class="cache-col-meta">Details</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${cacheOperations.map((operation) => `
                      <tr>
                        <td class="cache-col-type"><span class="cache-type-chip">${escapeHtml(operation.type || 'unknown')}</span></td>
                        <td class="cache-col-key"><span class="cache-key-cell">${escapeHtml(operation.key || 'N/A')}</span></td>
                        <td class="cache-col-value">${buildCacheValueCell(operation)}</td>
                        <td class="cache-col-store">
                          <div class="cache-store-cell">
                            <span class="cache-store-name">${escapeHtml(operation.store_name || 'default')}</span>
                            <span class="cache-store-driver">${escapeHtml(String(operation.store_driver || 'unknown').toUpperCase())}</span>
                          </div>
                        </td>
                        <td class="cache-col-status">${buildCacheStatusBadge(operation)}</td>
                        <td class="cache-col-meta">${buildCacheMetaLines(operation)}</td>
                      </tr>
                    `).join('')}
                  </tbody>
                </table>
              </div>
            ` : '<div class="no-data">No cache operations detected</div>'}
          </div>
        `;

        const sessionSection = `
          <div class="section">
            <div class="section-title"><span class="section-title-main">Session Snapshot</span></div>
            ${hasEntries(data.session_data) ? buildCodeBlock(data.session_data) : '<div class="no-data">No session data available</div>'}
          </div>
        `;

        const logsList = Array.isArray(data.logs) ? data.logs : [];
        const logsSection = `
          <div class="logs-shell">
            <div class="section-title">
              <span class="section-title-main">Runtime Logs</span>
              ${logsList.length ? `<span class="badge badge-info">${escapeHtml(logsList.length)} entries</span>` : ''}
            </div>
            ${logsList.length ? `
              <div class="log-list">
                ${logsList.map((log) => `
                  <div class="log-card">
                    <div class="log-head">
                      <span class="log-level">${escapeHtml((log.level || 'info').toUpperCase())}</span>
                      <span class="log-time">${escapeHtml(log.time || '')}</span>
                    </div>
                    <div class="log-message">${escapeHtml(log.message || '')}</div>
                    ${hasEntries(log.context) ? buildCodeBlock(log.context) : ''}
                  </div>
                `).join('')}
              </div>
            ` : '<div class="no-data">No logs captured during this request</div>'}
          </div>
        `;

        const outgoingRequests = Array.isArray(data.http_requests) ? data.http_requests : [];
        const jsonApiPath = `/_insight/api/${escapeHtml(data.id)}`;
        const jsonApiSection = `
          <div class="json-api-shell">
            <div class="section-title">
              <span class="section-title-main">JSON API</span>
              <a class="api-open-btn" href="${jsonApiPath}" target="_blank" rel="noreferrer">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                Open in Browser
              </a>
            </div>
            <div class="api-group-card">
              <div class="perf-group-title">Profiler Endpoint</div>
              <div class="api-line">
                <span class="api-method">GET</span>
                <span class="api-path">${jsonApiPath}</span>
              </div>
              <div class="api-meta">
                <span class="api-meta-chip api-chip-success">● 200 OK</span>
                <span class="api-meta-chip api-chip-teal">application/json</span>
                <span class="api-meta-chip api-chip-mono">${escapeHtml(data.id.slice(0, 12))}…</span>
              </div>
            </div>
            <div class="api-group-card api-payload-card">
              <div class="api-payload-head">
                <span class="perf-group-title">Response Payload</span>
                <button class="copy-action" type="button" data-action="json-api-copy">
                  <span class="copy-icon" aria-hidden="true"></span>
                  Copy JSON
                </button>
              </div>
              <div class="response-preview-scroll">${buildCodeBlock(data)}</div>
            </div>
          </div>
        `;

        const httpSection = `
          <div class="section-stack">
            ${redirectChainSection || '<div class="section"><div class="section-title"><span class="section-title-main">Redirect Chain</span></div><div class="no-data">No redirects were detected for this request chain</div></div>'}
            <div class="section">
              <div class="section-title"><span class="section-title-main">Outgoing HTTP Requests</span></div>
              ${outgoingRequests.length ? `
                <div class="section-stack">
                  ${outgoingRequests.map((request, index) => `
                    <div class="subsection">
                      <div class="subsection-title">Request #${index + 1}</div>
                      <div class="pill-row">
                        <span class="pill">${escapeHtml(request.method || 'GET')}</span>
                        <span class="pill">${escapeHtml(request.status ?? 'N/A')}</span>
                        <span class="pill">${escapeHtml(request.duration_ms?.toFixed?.(1) ?? request.duration_ms ?? 0)} ms</span>
                        ${request.successful === true ? '<span class="pill">Successful</span>' : ''}
                        ${request.successful === false ? '<span class="pill">Failed</span>' : ''}
                      </div>
                      <div class="row"><span class="key">URL:</span><span class="val">${escapeHtml(request.url || 'unknown')}</span></div>
                      ${request.error ? `<div class="row"><span class="key">Error:</span><span class="val">${escapeHtml(request.error)}</span></div>` : ''}
                    </div>
                  `).join('')}
                </div>
              ` : '<div class="no-data">No outgoing HTTP requests recorded</div>'}
            </div>
          </div>
        `;

        const sidebarLogo = toolbarLogo
          ? `<img src="${escapeHtml(toolbarLogo)}" alt="Doppar Logo" width="18" height="18">`
          : 'D';

        wrap.innerHTML = `
          <div class="workspace">
            <aside class="sidebar">
              <div class="sidebar-brand">
                <span class="sidebar-mark">${sidebarLogo}</span>
              </div>
              <nav class="sidebar-nav">
                <div class="nav-group">
                  <div class="nav-rail">
                    <button class="nav-rail-button active" type="button" data-view="overview" data-nav-label="Overview" aria-label="Overview"><span class="nav-icon nav-icon-overview"></span></button>
                    <button class="nav-rail-button" type="button" data-view="history" data-nav-label="History" aria-label="History"><span class="nav-icon nav-icon-history"></span></button>
                  </div>
                </div>
                <div class="nav-group-divider" aria-hidden="true"></div>
                <div class="nav-group">
                  <div class="nav-rail">
                    <button class="nav-rail-button" type="button" data-view="http" data-nav-label="HTTP" aria-label="HTTP"><span class="nav-icon nav-icon-http"></span></button>
                    <button class="nav-rail-button" type="button" data-view="database" data-nav-label="Database" aria-label="Database"><span class="nav-icon nav-icon-database"></span></button>
                    <button class="nav-rail-button" type="button" data-view="cache" data-nav-label="Cache" aria-label="Cache"><span class="nav-icon nav-icon-cache"></span></button>
                    <button class="nav-rail-button" type="button" data-view="auth" data-nav-label="Auth" aria-label="Auth"><span class="nav-icon nav-icon-auth"></span></button>
                    <button class="nav-rail-button" type="button" data-view="request" data-nav-label="Request" aria-label="Request"><span class="nav-icon nav-icon-request"></span></button>
                    <button class="nav-rail-button" type="button" data-view="response" data-nav-label="Response" aria-label="Response"><span class="nav-icon nav-icon-response"></span></button>
                    <button class="nav-rail-button" type="button" data-view="performance" data-nav-label="Performance" aria-label="Performance"><span class="nav-icon nav-icon-performance"></span></button>
                    <button class="nav-rail-button" type="button" data-view="session" data-nav-label="Session" aria-label="Session"><span class="nav-icon nav-icon-session"></span></button>
                    <button class="nav-rail-button" type="button" data-view="logs" data-nav-label="Logs" aria-label="Logs"><span class="nav-icon nav-icon-logs"></span></button>
                    <button class="nav-rail-button" type="button" data-view="json-api" data-nav-label="JSON API" aria-label="JSON API"><span class="nav-icon nav-icon-json"></span></button>
                  </div>
                </div>
              </nav>
            </aside>
            <main class="canvas">
              <section class="view-section active" data-view-section="overview">
                <div data-overview-shell>
                  <div class="history-loading">Loading activity…</div>
                </div>
              </section>
              <section class="view-section" data-view-section="history">
                ${historySection}
              </section>
              <section class="view-section" data-view-section="http">
                ${httpSection}
              </section>
              <section class="view-section" data-view-section="request">
                ${requestDetailsSection}
              </section>
              <section class="view-section" data-view-section="response">
                ${responseSection}
              </section>
              <section class="view-section" data-view-section="performance">
                ${performanceSection}
              </section>
              <section class="view-section" data-view-section="database">
                ${sqlSection}
              </section>
              <section class="view-section" data-view-section="cache">
                ${cacheSection}
              </section>
              <section class="view-section" data-view-section="auth">
                ${authSection}
              </section>
              <section class="view-section" data-view-section="session">
                ${sessionSection}
              </section>
              <section class="view-section" data-view-section="logs">
                ${logsSection}
              </section>
              <section class="view-section" data-view-section="json-api">
                ${jsonApiSection}
              </section>
            </main>
          </div>
        `;
        root.appendChild(style);
        root.appendChild(wrap);
        const navTooltip = document.createElement('div');
        navTooltip.className = 'nav-rail-tooltip';
        navTooltip.setAttribute('role', 'tooltip');
        root.appendChild(navTooltip);
        const hideNavTooltip = () => navTooltip.classList.remove('is-visible');
        const positionNavTooltip = (button) => {
          const label = button.getAttribute('data-nav-label');
          if(!label){
            return;
          }
          const rect = button.getBoundingClientRect();
          navTooltip.textContent = label;
          navTooltip.style.left = `${rect.right + 8}px`;
          navTooltip.style.top = `${rect.top + (rect.height / 2)}px`;
          navTooltip.style.transform = 'translateY(-50%)';
          navTooltip.classList.add('is-visible');
        };
        const navButtons = wrap.querySelectorAll('[data-view]');
        const viewSections = wrap.querySelectorAll('[data-view-section]');
        const setActiveView = (target) => {
          navButtons.forEach((item) => {
            item.classList.toggle('active', item.getAttribute('data-view') === target);
          });
          viewSections.forEach((section) => {
            section.classList.toggle('active', section.getAttribute('data-view-section') === target);
          });
        };
        navButtons.forEach((button) => {
          button.addEventListener('click', () => setActiveView(button.getAttribute('data-view')));
        });
        wrap.querySelectorAll('.nav-rail-button[data-nav-label]').forEach((button) => {
          button.addEventListener('mouseenter', () => positionNavTooltip(button));
          button.addEventListener('mouseleave', hideNavTooltip);
          button.addEventListener('focus', () => positionNavTooltip(button));
          button.addEventListener('blur', hideNavTooltip);
        });
        wrap.querySelector('.sidebar-nav')?.addEventListener('scroll', hideNavTooltip);
        const overviewShell = wrap.querySelector('[data-overview-shell]');
        const historyShell = wrap.querySelector('[data-history-shell]');
        const historyRanges = wrap.querySelector('[data-history-ranges]');
        if(historyShell && historyRanges){
          const rangeOptions = [
            { key: '1h', label: '1H', ms: 60 * 60 * 1000 },
            { key: '24h', label: '24H', ms: 24 * 60 * 60 * 1000 },
            { key: '7d', label: '7D', ms: 7 * 24 * 60 * 60 * 1000 },
            { key: '14d', label: '14D', ms: 14 * 24 * 60 * 60 * 1000 },
            { key: '30d', label: '30D', ms: 30 * 24 * 60 * 60 * 1000 }
          ];
          let activeRange = '24h';
          let activeMode = 'all';
          let searchTerm = '';
          let historyDataset = [];

          const formatCapturedAt = (value) => {
            if(!value){
              return 'Unknown';
            }
            const timestamp = new Date(value);
            if(Number.isNaN(timestamp.getTime())){
              return 'Unknown';
            }
            return timestamp.toLocaleString();
          };
          const formatCompactNumber = (value) => {
            const number = Number(value || 0);
            if(number >= 1000000){
              return `${(number / 1000000).toFixed(1)}M`;
            }
            if(number >= 1000){
              return `${(number / 1000).toFixed(1)}K`;
            }
            return String(Math.round(number));
          };
          const formatDuration = (value) => {
            const duration = Number(value || 0);
            if(duration >= 1000){
              return `${(duration / 1000).toFixed(2)}s`;
            }
            return `${duration.toFixed(0)}ms`;
          };
          const buildScaleLabels = (maxValue, formatter) => {
            const safeMax = Math.max(1, Number(maxValue || 0));
            const steps = [1, 0.75, 0.5, 0.25, 0];
            return steps.map((step) => ({
              value: safeMax * step,
              label: formatter(safeMax * step),
            }));
          };
          let _cid = 0;
          const buildGridLines = (width, height) => {
            return [0, 0.25, 0.5, 0.75, 1].map((step) => `<line class="history-grid-line" x1="0" y1="${height * step}" x2="${width}" y2="${height * step}"></line>`).join('');
          };
          const buildChartShell = (svg, labels) => {
            return `
              <div class="history-chart-shell">
                <div class="history-chart-y-axis">
                  ${labels.map((label) => `<span>${escapeHtml(label.label)}</span>`).join('')}
                </div>
                <div class="history-chart-canvas">
                  ${svg}
                </div>
              </div>
            `;
          };
          const formatRangeAxisLabel = (timestamp, rangeMs) => {
            const date = new Date(timestamp);
            if(Number.isNaN(date.getTime())){
              return '—';
            }
            if(rangeMs <= 60 * 60 * 1000){
              return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            }
            if(rangeMs <= 24 * 60 * 60 * 1000){
              return date.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
            }
            return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
          };
          const formatExceptionLabel = (value) => {
            if(!value){
              return 'Unhandled exception';
            }
            const label = String(value);
            const namespaceSeparator = String.fromCharCode(92);
            const lastSeparatorIndex = label.lastIndexOf(namespaceSeparator);
            return (lastSeparatorIndex >= 0 ? label.slice(lastSeparatorIndex + 1) : label) || 'Unhandled exception';
          };
          const renderMethodLabel = (method) => {
            const normalizedMethod = String(method || 'GET').toUpperCase();
            return `<span class="history-method-chip" data-method="${escapeHtml(normalizedMethod)}">${escapeHtml(normalizedMethod)}</span>`;
          };
          const renderPathLabel = (path) => {
            const fullPath = String(path || '/');
            const shortPath = fullPath.length > 10 ? fullPath.slice(0, 10) + '…' : fullPath;
            return `<span class="history-path-cell" title="${escapeHtml(fullPath)}">${escapeHtml(shortPath)}</span>`;
          };
          const renderStatusCountCell = (value) => {
            const numericValue = Number(value || 0);
            const displayValue = escapeHtml(formatCompactNumber(numericValue));
            if(numericValue > 0){
              return `<span class="history-count-badge history-count-badge-alert">${displayValue}</span>`;
            }
            return `<span class="history-cell-number">${displayValue}</span>`;
          };
          const historyBadgeClass = (status) => {
            const code = Number(status || 0);
            if(code >= 500 || code >= 400){
              return 'badge-error';
            }
            if(code >= 300){
              return 'badge-warning';
            }
            if(code >= 200){
              return 'badge-success';
            }
            return 'badge-info';
          };
          const resolveTimestamp = (item) => {
            if(item?.captured_at){
              const date = new Date(item.captured_at);
              if(!Number.isNaN(date.getTime())){
                return date.getTime();
              }
            }
            if(item?.captured_at_unix){
              return Number(item.captured_at_unix) * 1000;
            }
            if(item?.time_start){
              return Number(item.time_start) * 1000;
            }
            return Date.now();
          };
          const buildCombinedHistory = (history) => {
            const currentTimestamp = data.time_start ? Number(data.time_start) * 1000 : Date.now();
            const currentItem = {
              id: data.id,
              method: data.method,
              route: data.route,
              status: data.status,
              duration_ms: data.total_duration_ms ?? data.duration_ms ?? 0,
              exception_class: data.exception_class || null,
              exception_message: data.exception_message || null,
              captured_at: new Date(currentTimestamp).toISOString(),
              captured_at_unix: Math.floor(currentTimestamp / 1000),
            };
            const items = [currentItem].concat(Array.isArray(history) ? history : []);
            const unique = new Map();
            items.forEach((item) => {
              const normalized = {
                id: String(item?.id || ''),
                method: String(item?.method || 'GET').toUpperCase(),
                route: String(item?.route || '/'),
                status: Number(item?.status || 0),
                duration_ms: Number(item?.duration_ms || 0),
                exception_class: item?.exception_class || null,
                exception_message: item?.exception_message || null,
                captured_at: item?.captured_at || null,
                captured_at_unix: Number(item?.captured_at_unix || 0),
                timestamp: resolveTimestamp(item),
              };
              if(!normalized.id){
                return;
              }
              unique.set(normalized.id, normalized);
            });
            return Array.from(unique.values()).sort((left, right) => left.timestamp - right.timestamp);
          };
          const buildBuckets = (items, rangeMs, bucketCount = 24) => {
            const latest = items.length ? items[items.length - 1].timestamp : Date.now();
            const earliest = latest - rangeMs;
            const bucketSize = Math.max(1, rangeMs / bucketCount);
            const buckets = Array.from({ length: bucketCount }, (_, index) => ({
              start: earliest + (index * bucketSize),
              total: 0,
              status4xx: 0,
              status5xx: 0,
              durations: [],
            }));
            items.forEach((item) => {
              if(item.timestamp < earliest || item.timestamp > latest){
                return;
              }
              const bucketIndex = Math.min(bucketCount - 1, Math.max(0, Math.floor((item.timestamp - earliest) / bucketSize)));
              const bucket = buckets[bucketIndex];
              bucket.total += 1;
              if(item.status >= 500){
                bucket.status5xx += 1;
              } else if(item.status >= 400){
                bucket.status4xx += 1;
              }
              bucket.durations.push(item.duration_ms);
            });
            return buckets.map((bucket) => {
              const max = bucket.durations.length ? Math.max(...bucket.durations) : 0;
              const avg = bucket.durations.length ? bucket.durations.reduce((sum, value) => sum + value, 0) / bucket.durations.length : 0;
              const tone = bucket.status5xx > 0 ? 'error' : (bucket.status4xx > 0 ? 'warning' : (bucket.total > 0 ? 'success' : 'muted'));
              return { ...bucket, avg, max, tone };
            });
          };
          const buildBarChart = (buckets) => {
            const id = ++_cid;
            const width = 560;
            const height = 140;
            const maxValue = Math.max(1, ...buckets.map((bucket) => bucket.total));
            const barWidth = width / Math.max(1, buckets.length);
            const grid = buildGridLines(width, height);
            const colorMap = {
              success: { top: '#2ab4b6', bot: '#1a7e80', id: `bs${id}` },
              warning: { top: '#f59e0b', bot: '#b97308', id: `bw${id}` },
              error:   { top: '#ef4444', bot: '#b91c1c', id: `be${id}` },
              muted:   { top: 'rgba(255,255,255,0.12)', bot: 'rgba(255,255,255,0.04)', id: `bm${id}` },
            };
            const defs = Object.values(colorMap).map((c) =>
              `<linearGradient id="${c.id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="${c.top}"/><stop offset="100%" stop-color="${c.bot}" stop-opacity="0.7"/></linearGradient>`
            ).join('');
            const bars = buckets.map((bucket, index) => {
              const columnHeight = bucket.total > 0 ? Math.max(6, (bucket.total / maxValue) * (height - 8)) : 4;
              const x = index * barWidth + 2;
              const y = height - columnHeight;
              const c = colorMap[bucket.tone] || colorMap.muted;
              return `<rect fill="url(#${c.id})" x="${x.toFixed(2)}" y="${y.toFixed(2)}" width="${Math.max(6, barWidth - 5).toFixed(2)}" height="${columnHeight.toFixed(2)}" rx="2"></rect>`;
            }).join('');
            const svg = `<svg class="history-chart" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none"><defs>${defs}</defs>${grid}${bars}</svg>`;
            return buildChartShell(svg, buildScaleLabels(maxValue, formatCompactNumber));
          };
          const buildLineChart = (buckets) => {
            const id = ++_cid;
            const width = 560;
            const height = 140;
            const maxDuration = Math.max(1, ...buckets.map((bucket) => Math.max(bucket.avg, bucket.max)));
            const ptFor = (value, index) => {
              const x = buckets.length === 1 ? width / 2 : (index / (buckets.length - 1)) * width;
              const y = height - ((value / maxDuration) * (height - 12)) - 6;
              return { x, y };
            };
            const linePath = (values) => values.map((v, i) => { const p = ptFor(v, i); return `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(2)} ${p.y.toFixed(2)}`; }).join(' ');
            const areaPath = (values) => {
              const pts = values.map((v, i) => ptFor(v, i));
              const move = pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(2)} ${p.y.toFixed(2)}`).join(' ');
              const lastX = pts[pts.length - 1].x;
              const firstX = pts[0].x;
              return `${move} L ${lastX.toFixed(2)} ${height} L ${firstX.toFixed(2)} ${height} Z`;
            };
            const avgValues = buckets.map((b) => b.avg);
            const maxValues = buckets.map((b) => b.max);
            const defs = `
              <linearGradient id="la${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="rgba(255,255,255,0.13)"/><stop offset="100%" stop-color="rgba(255,255,255,0.01)"/></linearGradient>
              <linearGradient id="lm${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="rgba(251,191,36,0.22)"/><stop offset="100%" stop-color="rgba(251,191,36,0.01)"/></linearGradient>
            `;
            const grid = buildGridLines(width, height);
            const svg = `<svg class="history-chart" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none"><defs>${defs}</defs>${grid}<path fill="url(#lm${id})" stroke="none" d="${areaPath(maxValues)}"></path><path fill="url(#la${id})" stroke="none" d="${areaPath(avgValues)}"></path><path class="history-line-max" fill="none" d="${linePath(maxValues)}"></path><path class="history-line-avg" fill="none" d="${linePath(avgValues)}"></path></svg>`;
            return buildChartShell(svg, buildScaleLabels(maxDuration, formatDuration));
          };
          const buildExceptionChart = (buckets) => {
            const id = ++_cid;
            const width = 560;
            const height = 140;
            const maxValue = Math.max(1, ...buckets.map((bucket) => bucket.status4xx + bucket.status5xx));
            const barWidth = width / Math.max(1, buckets.length);
            const defs = `
              <linearGradient id="ew${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f59e0b"/><stop offset="100%" stop-color="#b97308" stop-opacity="0.7"/></linearGradient>
              <linearGradient id="ee${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#ef4444"/><stop offset="100%" stop-color="#b91c1c" stop-opacity="0.7"/></linearGradient>
            `;
            const grid = buildGridLines(width, height);
            const bars = buckets.map((bucket, index) => {
              const x = index * barWidth + 2;
              const widthValue = Math.max(6, barWidth - 5);
              const total4xxHeight = bucket.status4xx > 0 ? Math.max(6, (bucket.status4xx / maxValue) * (height - 8)) : 0;
              const total5xxHeight = bucket.status5xx > 0 ? Math.max(6, (bucket.status5xx / maxValue) * (height - 8)) : 0;
              const baseY = height;
              const warningRect = total4xxHeight > 0
                ? `<rect fill="url(#ew${id})" x="${x.toFixed(2)}" y="${(baseY - total4xxHeight).toFixed(2)}" width="${widthValue.toFixed(2)}" height="${total4xxHeight.toFixed(2)}" rx="2"></rect>`
                : '';
              const errorRect = total5xxHeight > 0
                ? `<rect fill="url(#ee${id})" x="${x.toFixed(2)}" y="${(baseY - total4xxHeight - total5xxHeight).toFixed(2)}" width="${widthValue.toFixed(2)}" height="${total5xxHeight.toFixed(2)}" rx="2"></rect>`
                : '';
              const emptyTick = (bucket.status4xx + bucket.status5xx) === 0
                ? `<rect class="history-bar-muted" x="${x.toFixed(2)}" y="${(height - 4).toFixed(2)}" width="${widthValue.toFixed(2)}" height="3" rx="2"></rect>`
                : '';
              return `${emptyTick}${warningRect}${errorRect}`;
            }).join('');
            const svg = `<svg class="history-chart" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none"><defs>${defs}</defs>${grid}${bars}</svg>`;
            return buildChartShell(svg, buildScaleLabels(maxValue, formatCompactNumber));
          };
          const aggregateRoutes = (items) => {
            const routes = new Map();
            items.forEach((item) => {
              const key = `${item.method} ${item.route}`;
              if(!routes.has(key)){
                routes.set(key, {
                  method: item.method,
                  route: item.route,
                  requests: 0,
                  status3xx: 0,
                  status4xx: 0,
                  status5xx: 0,
                  errorRequests: 0,
                  totalDuration: 0,
                  maxDuration: 0,
                  minDuration: Infinity,
                  lastSeen: item.timestamp,
                  latestStatus: item.status,
                  latestExceptionClass: item.exception_class,
                  latestExceptionMessage: item.exception_message,
                });
              }
              const route = routes.get(key);
              route.requests += 1;
              route.totalDuration += item.duration_ms;
              route.maxDuration = Math.max(route.maxDuration, item.duration_ms);
              route.minDuration = Math.min(route.minDuration, item.duration_ms);
              if(item.timestamp >= route.lastSeen){
                route.lastSeen = item.timestamp;
                route.latestStatus = item.status;
                route.latestExceptionClass = item.exception_class;
                route.latestExceptionMessage = item.exception_message;
              }
              if(item.status >= 500){
                route.status5xx += 1;
                route.errorRequests += 1;
              } else if(item.status >= 400){
                route.status4xx += 1;
                route.errorRequests += 1;
              } else if(item.status >= 300){
                route.status3xx += 1;
              }
            });
            return Array.from(routes.values()).map((route) => ({
              ...route,
              avgDuration: route.requests > 0 ? route.totalDuration / route.requests : 0,
              minDuration: route.minDuration === Infinity ? 0 : route.minDuration,
            }));
          };
          const renderRangeButtons = () => {
            historyRanges.innerHTML = rangeOptions.map((range) => `
              <button class="history-range-pill ${range.key === activeRange ? 'active' : ''}" type="button" data-history-range="${range.key}">
                ${range.label}
              </button>
            `).join('');
            historyRanges.querySelectorAll('[data-history-range]').forEach((button) => {
              button.addEventListener('click', () => {
                activeRange = button.getAttribute('data-history-range') || '24h';
                renderRangeButtons();
                renderHistoryDashboard();
              });
            });
          };
          const renderHistoryDashboard = (preserveSearchFocus = false) => {
            const selectedRange = rangeOptions.find((range) => range.key === activeRange) || rangeOptions[1];
            const latestTimestamp = historyDataset.length ? historyDataset[historyDataset.length - 1].timestamp : Date.now();
            const cutoff = latestTimestamp - selectedRange.ms;
            const axisStart = formatRangeAxisLabel(cutoff, selectedRange.ms);
            const axisEnd = formatRangeAxisLabel(latestTimestamp, selectedRange.ms);
            const filtered = historyDataset.filter((item) => item.timestamp >= cutoff);
            const matched = aggregateRoutes(filtered).filter((route) => {
              const query = searchTerm.trim().toLowerCase();
              if(query && !`${route.method} ${route.route}`.toLowerCase().includes(query)){
                return false;
              }
              if(activeMode === 'errors'){
                return route.status4xx > 0 || route.status5xx > 0;
              }
              if(activeMode === 'slow'){
                return route.maxDuration >= 1000 || route.avgDuration >= 500;
              }
              return true;
            }).sort((left, right) => {
              if(activeMode === 'slow'){
                return right.maxDuration - left.maxDuration || right.requests - left.requests;
              }
              const leftErrors = left.status5xx + left.status4xx;
              const rightErrors = right.status5xx + right.status4xx;
              return right.requests - left.requests || rightErrors - leftErrors || right.maxDuration - left.maxDuration;
            });
            const totalRequests = filtered.length;
            const status3xx = filtered.filter((item) => item.status >= 300 && item.status < 400).length;
            const status4xx = filtered.filter((item) => item.status >= 400 && item.status < 500).length;
            const status5xx = filtered.filter((item) => item.status >= 500).length;
            const durations = filtered.map((item) => item.duration_ms);
            const avgDuration = durations.length ? durations.reduce((sum, value) => sum + value, 0) / durations.length : 0;
            const maxDuration = durations.length ? Math.max(...durations) : 0;
            const minDuration = durations.length ? Math.min(...durations) : 0;
            const buckets = buildBuckets(filtered, selectedRange.ms, 24);
            const recentErrors = filtered
              .filter((item) => item.status >= 400)
              .sort((left, right) => right.timestamp - left.timestamp)
              .slice(0, 6);
            const topErrorRoutes = aggregateRoutes(filtered)
              .filter((route) => route.status4xx > 0 || route.status5xx > 0)
              .sort((left, right) => (right.status5xx + right.status4xx) - (left.status5xx + left.status4xx) || right.requests - left.requests)
              .slice(0, 3);
            const errorRoutes = new Set(filtered.filter((item) => item.status >= 400).map((item) => item.route));
            const errorTypes = new Set(filtered.filter((item) => item.status >= 400).map((item) => formatExceptionLabel(item.exception_class)));
            const latestErrorTime = recentErrors.length ? formatCapturedAt(recentErrors[0].captured_at) : '—';
            const hottestErrorRoute = topErrorRoutes.length ? `${topErrorRoutes[0].method} ${topErrorRoutes[0].route}` : '—';
            const routeRows = matched.map((route) => `
              <tr>
                <td class="history-col-method">${renderMethodLabel(route.method)}</td>
                <td class="history-col-path">${renderPathLabel(route.route)}</td>
                <td class="history-col-requests"><span class="history-cell-number">${escapeHtml(formatCompactNumber(route.requests))}</span></td>
                <td class="history-col-3xx">${renderStatusCountCell(route.status3xx)}</td>
                <td class="history-col-4xx">${renderStatusCountCell(route.status4xx)}</td>
                <td class="history-col-5xx">${renderStatusCountCell(route.status5xx)}</td>
                <td class="history-col-avg"><span class="history-cell-number">${escapeHtml(formatDuration(route.avgDuration))}</span></td>
                <td class="history-col-max"><span class="history-cell-number">${escapeHtml(formatDuration(route.maxDuration))}</span></td>
              </tr>
            `).join('');
            const recentErrorMarkup = recentErrors.length ? `
              <div class="ov-chart-wrap">
                ${buildExceptionChart(buckets)}
                <div class="history-chart-axis"><span>${escapeHtml(axisStart)}</span><span>${escapeHtml(axisEnd)}</span></div>
              </div>
              <div class="ov-stat-grid">
                <div class="ov-stat-card">
                  <div class="ov-stat-label">Error Routes</div>
                  <div class="ov-stat-value">${escapeHtml(String(errorRoutes.size))}</div>
                  <div class="ov-stat-note">Routes with at least one 4XX or 5XX in ${escapeHtml(selectedRange.label)}.</div>
                </div>
                <div class="ov-stat-card">
                  <div class="ov-stat-label">Exception Types</div>
                  <div class="ov-stat-value">${escapeHtml(String(errorTypes.size))}</div>
                  <div class="ov-stat-note">Distinct error families captured in this window.</div>
                </div>
                <div class="ov-stat-card">
                  <div class="ov-stat-label">Latest Error</div>
                  <div class="ov-stat-value">${escapeHtml(latestErrorTime)}</div>
                  <div class="ov-stat-note">${escapeHtml(hottestErrorRoute)}</div>
                </div>
              </div>
              <div class="ov-chip-row">
                ${topErrorRoutes.map((route) => `
                  <span class="ov-chip">
                    ${renderMethodLabel(route.method)}
                    <span class="ov-chip-value" title="${escapeHtml(route.route)}">${escapeHtml(route.route)} • ${escapeHtml(formatCompactNumber(route.status4xx + route.status5xx))}</span>
                  </span>
                `).join('')}
              </div>
            ` : `<div class="history-empty-state history-error-empty">No 4XX or 5XX requests in this range.</div>`;

            historyShell.innerHTML = `
              <div class="history-overview-grid">
                <div class="history-card">
                  <div class="history-card-header">
                    <div>
                      <div class="history-card-title">Requests</div>
                      <div class="history-card-value">${escapeHtml(formatCompactNumber(totalRequests))}</div>
                    </div>
                    <div class="history-card-meta">
                      <span class="badge badge-info">3XX ${escapeHtml(formatCompactNumber(status3xx))}</span>
                      <span class="badge badge-warning">4XX ${escapeHtml(formatCompactNumber(status4xx))}</span>
                      <span class="badge badge-error">5XX ${escapeHtml(formatCompactNumber(status5xx))}</span>
                    </div>
                  </div>
                  <div class="history-kpis">
                    <div class="history-kpi">
                      <div class="history-kpi-label">Routes</div>
                      <div class="history-kpi-value">${escapeHtml(formatCompactNumber(aggregateRoutes(filtered).length))}</div>
                    </div>
                    <div class="history-kpi">
                      <div class="history-kpi-label">Current</div>
                      <div class="history-kpi-value">${escapeHtml(selectedRange.label)}</div>
                    </div>
                    <div class="history-kpi">
                      <div class="history-kpi-label">Latest</div>
                      <div class="history-kpi-value">${escapeHtml(formatCapturedAt(filtered.length ? filtered[filtered.length - 1].captured_at : null))}</div>
                    </div>
                  </div>
                  <div class="history-chart-wrap">
                    ${buildBarChart(buckets)}
                    <div class="history-chart-axis">
                      <span>${escapeHtml(selectedRange.label)} request volume</span>
                      <span>Newest at right</span>
                    </div>
                  </div>
                </div>
                <div class="history-card">
                  <div class="history-card-header">
                    <div>
                      <div class="history-card-title">Duration</div>
                      <div class="history-card-value">${escapeHtml(formatDuration(minDuration))} - ${escapeHtml(formatDuration(maxDuration))}</div>
                    </div>
                    <div class="history-card-meta">
                      <span class="history-card-note">AVG ${escapeHtml(formatDuration(avgDuration))}</span>
                      <span class="history-card-note">MAX ${escapeHtml(formatDuration(maxDuration))}</span>
                    </div>
                  </div>
                  <div class="history-kpis">
                    <div class="history-kpi">
                      <div class="history-kpi-label">Healthy</div>
                      <div class="history-kpi-value">${escapeHtml(formatCompactNumber(totalRequests - status4xx - status5xx))}</div>
                    </div>
                    <div class="history-kpi">
                      <div class="history-kpi-label">Errors</div>
                      <div class="history-kpi-value">${escapeHtml(formatCompactNumber(status4xx + status5xx))}</div>
                    </div>
                    <div class="history-kpi">
                      <div class="history-kpi-label">Peaks</div>
                      <div class="history-kpi-value">${escapeHtml(formatCompactNumber(buckets.filter((bucket) => bucket.max >= 1000).length))}</div>
                    </div>
                  </div>
                  <div class="history-chart-wrap">
                    ${buildLineChart(buckets)}
                    <div class="history-chart-axis">
                      <span>Average latency</span>
                      <span>Peak latency</span>
                    </div>
                  </div>
                </div>
                <div class="history-card history-card-wide">
                  <div class="history-card-header">
                    <div>
                      <div class="history-card-title">Recent Errors</div>
                      <div class="history-card-value">${escapeHtml(formatCompactNumber(status4xx + status5xx))}</div>
                    </div>
                    <div class="history-card-meta">
                      <span class="badge badge-warning">4XX ${escapeHtml(formatCompactNumber(status4xx))}</span>
                      <span class="badge badge-error">5XX ${escapeHtml(formatCompactNumber(status5xx))}</span>
                    </div>
                  </div>
                  ${recentErrorMarkup}
                </div>
              </div>
              <div class="history-card history-table-shell">
                <div class="history-table-head">
                  <div class="history-route-count">${escapeHtml(formatCompactNumber(matched.length))} routes</div>
                  <div class="history-search-wrap">
                    <input class="history-search" type="search" value="${escapeHtml(searchTerm)}" placeholder="Search method or route" data-history-search>
                    <div class="history-toggle-group">
                      <button class="history-toggle ${activeMode === 'all' ? 'active' : ''}" type="button" data-history-mode="all">View all</button>
                      <button class="history-toggle ${activeMode === 'errors' ? 'active' : ''}" type="button" data-history-mode="errors">Errors only</button>
                      <button class="history-toggle ${activeMode === 'slow' ? 'active' : ''}" type="button" data-history-mode="slow">Slow routes</button>
                    </div>
                  </div>
                </div>
                ${matched.length ? `
                  <div class="history-table-scroll">
                    <table class="history-route-table">
                      <thead>
                        <tr>
                          <th class="history-col-method">Method</th>
                          <th class="history-col-path">Path</th>
                          <th class="history-col-requests">Requests</th>
                          <th class="history-col-3xx">3XX</th>
                          <th class="history-col-4xx">4XX</th>
                          <th class="history-col-5xx">5XX</th>
                          <th class="history-col-avg">Avg</th>
                          <th class="history-col-max">Max</th>
                        </tr>
                      </thead>
                      <tbody>${routeRows}</tbody>
                    </table>
                  </div>
                ` : `<div class="history-empty-state">No routes matched this history filter.</div>`}
              </div>
            `;

            const searchInput = historyShell.querySelector('[data-history-search]');
            if(searchInput){
              searchInput.addEventListener('input', (event) => {
                const nextValue = event.target.value || '';
                if(nextValue === searchTerm){
                  return;
                }
                searchTerm = nextValue;
                renderHistoryDashboard(true);
              });
              if(preserveSearchFocus){
                searchInput.focus();
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
              }
            }
            historyShell.querySelectorAll('[data-history-mode]').forEach((button) => {
              button.addEventListener('click', () => {
                activeMode = button.getAttribute('data-history-mode') || 'all';
                renderHistoryDashboard();
              });
            });
          };

          const renderOverviewDashboard = () => {
            if(!overviewShell) return;
            const rangeMs = 24 * 60 * 60 * 1000;
            const latestTs = historyDataset.length ? historyDataset[historyDataset.length - 1].timestamp : Date.now();
            const cutoff = latestTs - rangeMs;
            const filtered = historyDataset.filter((item) => item.timestamp >= cutoff);
            const totalRequests = filtered.length;
            const status123xx = filtered.filter((item) => item.status >= 100 && item.status < 400).length;
            const status4xx = filtered.filter((item) => item.status >= 400 && item.status < 500).length;
            const status5xx = filtered.filter((item) => item.status >= 500).length;
            const durations = filtered.map((item) => item.duration_ms);
            const avgDuration = durations.length ? durations.reduce((s, v) => s + v, 0) / durations.length : 0;
            const minDuration = durations.length ? Math.min(...durations) : 0;
            const maxDuration = durations.length ? Math.max(...durations) : 0;
            const calcP95 = (vals) => {
              if(!vals.length) return 0;
              const sorted = [...vals].sort((a, b) => a - b);
              return sorted[Math.floor(sorted.length * 0.95)] ?? sorted[sorted.length - 1] ?? 0;
            };
            const p95 = calcP95(durations);
            const buckets = buildBuckets(filtered, rangeMs, 24);
            const axisStart = formatRangeAxisLabel(cutoff, rangeMs);
            const axisEnd = formatRangeAxisLabel(latestTs, rangeMs);
            const durationRange = maxDuration > 0
              ? (minDuration > 0 ? `${formatDuration(minDuration)} – ${formatDuration(maxDuration)}` : formatDuration(maxDuration))
              : '—';
            const recentErrors = filtered
              .filter((item) => item.status >= 400)
              .sort((a, b) => b.timestamp - a.timestamp)
              .slice(0, 6);
            const errorCount = filtered.filter((item) => item.status >= 400).length;
            const errorRoutes = new Set(filtered.filter((item) => item.status >= 400).map((item) => item.route));
            const errorTypes = new Set(filtered.filter((item) => item.status >= 400).map((item) => formatExceptionLabel(item.exception_class)));
            const topErrorRoutes = aggregateRoutes(filtered)
              .filter((route) => route.status4xx > 0 || route.status5xx > 0)
              .sort((left, right) => (right.status5xx + right.status4xx) - (left.status5xx + left.status4xx) || right.requests - left.requests)
              .slice(0, 3);
            const hottestErrorRoute = recentErrors.length ? `${recentErrors[0].method} ${recentErrors[0].route}` : '—';
            const latestErrorTime = recentErrors.length ? formatCapturedAt(recentErrors[0].captured_at) : '—';
            const slowThreshold = 1000;
            const slowRoutes = aggregateRoutes(filtered)
              .filter((route) => route.maxDuration >= slowThreshold)
              .sort((a, b) => b.maxDuration - a.maxDuration);
            overviewShell.innerHTML = `
              <div class="ov-dashboard">
                <section class="ov-group">
                  <div class="ov-group-label">Activity <span>24h</span></div>
                  <div class="ov-two-col">
                    <div class="history-card">
                      <div class="history-card-header">
                        <div class="history-card-title">Requests</div>
                        <div class="history-card-meta">
                          <span class="badge badge-success">${escapeHtml(formatCompactNumber(status123xx))} 1/2/3XX</span>
                          <span class="badge badge-warning">${escapeHtml(formatCompactNumber(status4xx))} 4XX</span>
                          <span class="badge badge-error">${escapeHtml(formatCompactNumber(status5xx))} 5XX</span>
                        </div>
                      </div>
                      <div class="history-card-value">${escapeHtml(formatCompactNumber(totalRequests))}</div>
                      <div class="history-chart-wrap">
                        ${buildBarChart(buckets)}
                        <div class="history-chart-axis"><span>${escapeHtml(axisStart)}</span><span>${escapeHtml(axisEnd)}</span></div>
                      </div>
                    </div>
                    <div class="history-card">
                      <div class="history-card-header">
                        <div class="history-card-title">Duration</div>
                        <div class="history-card-meta">
                          <span class="ov-kpi-chip">AVG ${escapeHtml(formatDuration(avgDuration))}</span>
                          <span class="ov-kpi-chip ov-kpi-warn">P95 ${escapeHtml(formatDuration(p95))}</span>
                        </div>
                      </div>
                      <div class="history-card-value">${escapeHtml(durationRange)}</div>
                      <div class="history-chart-wrap">
                        ${buildLineChart(buckets)}
                        <div class="history-chart-axis"><span>${escapeHtml(axisStart)}</span><span>${escapeHtml(axisEnd)}</span></div>
                      </div>
                    </div>
                  </div>
                </section>
                <section class="ov-group">
                  <div class="ov-group-label">Application</div>
                  <div class="ov-app-stack">
                    <div class="history-card">
                      <div class="history-card-header">
                        <div class="history-card-title">Exceptions</div>
                        <div class="history-card-meta">
                          <span class="badge badge-warning">${escapeHtml(formatCompactNumber(status4xx))} 4XX</span>
                          <span class="badge badge-error">${escapeHtml(formatCompactNumber(status5xx))} 5XX</span>
                        </div>
                      </div>
                      ${errorCount > 0 ? `
                        <div class="ov-app-headline">${escapeHtml(formatCompactNumber(errorCount))} exception${errorCount !== 1 ? 's' : ''} in 24h</div>
                        <div class="ov-app-sub">Errors across ${escapeHtml(String(errorRoutes.size))} unique route${errorRoutes.size !== 1 ? 's' : ''}</div>
                        <div class="ov-chart-wrap">
                          ${buildExceptionChart(buckets)}
                          <div class="history-chart-axis"><span>${escapeHtml(axisStart)}</span><span>${escapeHtml(axisEnd)}</span></div>
                        </div>
                        <div class="ov-stat-grid">
                          <div class="ov-stat-card">
                            <div class="ov-stat-label">Error Routes</div>
                            <div class="ov-stat-value">${escapeHtml(String(errorRoutes.size))}</div>
                            <div class="ov-stat-note">Unique endpoints returning 4XX or 5XX.</div>
                          </div>
                          <div class="ov-stat-card">
                            <div class="ov-stat-label">Exception Types</div>
                            <div class="ov-stat-value">${escapeHtml(String(errorTypes.size))}</div>
                            <div class="ov-stat-note">Distinct exception families seen in 24h.</div>
                          </div>
                          <div class="ov-stat-card">
                            <div class="ov-stat-label">Latest Error</div>
                            <div class="ov-stat-value">${escapeHtml(latestErrorTime)}</div>
                            <div class="ov-stat-note">${escapeHtml(hottestErrorRoute)}</div>
                          </div>
                        </div>
                        <div class="ov-chip-row">
                          ${topErrorRoutes.map((route) => `
                            <span class="ov-chip">
                              ${renderMethodLabel(route.method)}
                              <span class="ov-chip-value" title="${escapeHtml(route.route)}">${escapeHtml(route.route)} • ${escapeHtml(formatCompactNumber(route.status4xx + route.status5xx))}</span>
                            </span>
                          `).join('')}
                        </div>
                      ` : '<div class="ov-empty">No exceptions recorded in 24h.</div>'}
                    </div>
                    <div class="history-card">
                      <div class="history-card-title">Slow Routes</div>
                      ${slowRoutes.length > 0 ? `
                        <div class="ov-app-headline">${slowRoutes.length} route${slowRoutes.length !== 1 ? 's' : ''} slower than 1s</div>
                        <div class="ov-app-sub">Sorted by worst observed response time. The table stays capped and scrollable when many slow routes are captured.</div>
                        <div class="ov-slow-table-scroll">
                          <table class="ov-slow-table">
                            <thead>
                              <tr>
                                <th class="ov-slow-col-route">Route</th>
                                <th class="ov-slow-col-requests">Hits</th>
                                <th class="ov-slow-col-min">Min</th>
                                <th class="ov-slow-col-avg">Avg</th>
                                <th class="ov-slow-col-peak">Peak</th>
                              </tr>
                            </thead>
                            <tbody>
                              ${slowRoutes.map((route) => `
                                <tr>
                                  <td class="ov-slow-col-route">
                                    <span class="ov-slow-route-cell">
                                      ${renderMethodLabel(route.method)}${renderPathLabel(route.route)}
                                    </span>
                                  </td>
                                  <td class="ov-slow-col-requests"><span class="history-cell-number">${escapeHtml(formatCompactNumber(route.requests))}</span></td>
                                  <td class="ov-slow-col-min"><span class="history-cell-number">${escapeHtml(formatDuration(route.minDuration))}</span></td>
                                  <td class="ov-slow-col-avg">${escapeHtml(formatDuration(route.avgDuration))}</td>
                                  <td class="ov-slow-col-peak"><span class="ov-p95-chip">MAX ${escapeHtml(formatDuration(route.maxDuration))}</span></td>
                                </tr>
                              `).join('')}
                            </tbody>
                          </table>
                        </div>
                      ` : '<div class="ov-empty">No slow routes detected in 24h.</div>'}
                    </div>
                  </div>
                </section>
              </div>
            `;
          };

          renderRangeButtons();
          fetch('/_insight/api/history?limit=500&_=' + Date.now(), { cache: 'no-store' }).then((response) => response.json()).then((history) => {
            if(!Array.isArray(history)){
              historyShell.innerHTML = '<div class="no-data">Unable to load request history right now.</div>';
              if(overviewShell) overviewShell.innerHTML = '<div class="no-data">Unable to load activity data.</div>';
              return;
            }
            historyDataset = buildCombinedHistory(history);
            renderOverviewDashboard();
            renderHistoryDashboard();
          }).catch(() => {
            historyShell.innerHTML = '<div class="no-data">Unable to load request history right now.</div>';
            if(overviewShell) overviewShell.innerHTML = '<div class="no-data">Unable to load activity data.</div>';
          });
        }
        wrap.querySelectorAll('[data-action="json-api-copy"]').forEach((button) => {
          button.addEventListener('click', async () => {
            const payload = JSON.stringify(data, null, 2);
            try {
              await this.copyText(payload);
              this.setCopyButtonState(button, 'success');
            } catch (error) {
              this.setCopyButtonState(button, 'error');
            }
            window.setTimeout(() => {
              this.setCopyButtonState(button, 'idle');
            }, 1600);
          });
        });
        wrap.querySelectorAll('[data-action="copy-request-url"]').forEach((button) => {
          button.addEventListener('click', async () => {
            button.classList.remove('is-success', 'is-error');
            try {
              await this.copyText(String(requestUrl));
              button.classList.add('is-success');
            } catch (error) {
              button.classList.add('is-error');
            }
            window.setTimeout(() => {
              button.classList.remove('is-success', 'is-error');
            }, 1600);
          });
        });
        wrap.querySelectorAll('[data-action="toggle-theme"]').forEach((button) => {
          button.addEventListener('click', () => {
            const current = wrap.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            this.setQuickViewTheme(wrap, current === 'dark' ? 'light' : 'dark');
          });
        });
        this.setQuickViewTheme(wrap, this.getQuickViewTheme());
        this.syncPanelPosition();
        this.animatePanelOpen(wrap);
      }).catch(()=>{});
    } else {
      this.closePanel(root);
    }
  },
  bindLayoutSync(){
    if(this.layoutSyncBound){
      return;
    }
    this.layoutSyncBound = true;
    const sync = () => {
      if(this.open){
        this.syncPanelPosition();
      }
    };
    window.addEventListener('resize', sync);
    window.addEventListener('scroll', sync, { passive: true });
  }
};

window.DopparProfiler.bindLayoutSync();
