window.DopparProfiler = {
  open: false,
  ensurePanelRoot(){
    let host = document.getElementById('doppar-profiler-panel');
    if(!host){
      host = document.createElement('div');
      host.id = 'doppar-profiler-panel';
      // Isolate the host from page CSS as much as possible
      host.style.all = 'initial';
      host.style.position = 'fixed';
      host.style.right = '10px';
      host.style.bottom = '48px';
      host.style.zIndex = '2147483647';
      document.body.appendChild(host);
    }
    if(!host.shadowRoot){
      host.attachShadow({mode:'open'});
    }
    return host.shadowRoot;
  },
  toggle(){
    this.open = !this.open;
    const root = this.ensurePanelRoot();
    if(this.open){
      const id = document.getElementById('doppar-profiler').dataset.requestId;
      fetch('/_profiler/' + id).then(r=>r.json()).then(data=>{
        // Shadow DOM content
        const css = `
          :host{all:initial}
          .panel{background:#111827;color:#e5e7eb;border:1px solid #374151;border-radius:8px;padding:16px;width:680px;max-height:70vh;overflow:auto;box-shadow:0 6px 24px rgba(0,0,0,.25);font:13px/1.5 system-ui,Segoe UI,Roboto,Helvetica,Arial}
          .section{margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #374151}
          .section:last-child{border-bottom:none;margin-bottom:0}
          .section-title{font-size:15px;font-weight:600;color:#f3f4f6;margin-bottom:10px;display:flex;align-items:center;gap:8px}
          .badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600}
          .badge-info{background:#1e3a8a;color:#93c5fd}
          .badge-success{background:#065f46;color:#6ee7b7}
          .badge-warning{background:#78350f;color:#fcd34d}
          .badge-error{background:#7f1d1d;color:#fca5a5}
          .row{margin:6px 0;display:flex;align-items:baseline}
          .key{color:#9ca3af;display:inline-block;min-width:120px;font-weight:500}
          .val{color:#e5e7eb;flex:1}
          .sql-list{margin-top:8px}
          .sql-item{background:#1f2937;border:1px solid #374151;border-radius:6px;padding:10px;margin-bottom:8px}
          .sql-item:last-child{margin-bottom:0}
          .sql-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
          .sql-time{color:#34d399;font-weight:600;font-size:12px}
          .sql-rows{color:#9ca3af;font-size:11px;margin-left:8px}
          .sql-query{color:#e5e7eb;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:12px;line-height:1.6;word-break:break-all;white-space:pre-wrap;background:#0f172a;padding:8px;border-radius:4px;margin-bottom:6px}
          .sql-bindings{font-size:11px;color:#9ca3af;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
          .sql-error{color:#ef4444;font-size:12px;margin-top:4px}
          .no-data{color:#6b7280;font-style:italic;font-size:12px}
          .redirect-row{background:#1e3a8a15;border-left:3px solid #3b82f6;padding:8px;margin:8px 0;border-radius:4px}
          .redirect-url{color:#60a5fa;font-weight:600;word-break:break-all}
          .redirect-chain-list{margin-top:8px}
          .redirect-chain-item{background:#1f2937;border:1px solid #374151;border-radius:6px;padding:10px;margin-bottom:8px}
          .redirect-chain-item:last-child{margin-bottom:0}
          .redirect-chain-current{border-color:#10b981;background:#065f4615}
          .redirect-chain-header{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
          .redirect-chain-status{color:#fbbf24;font-weight:600;font-size:12px}
          .redirect-chain-method{color:#9ca3af;font-size:11px;font-weight:600}
          .redirect-chain-path{color:#e5e7eb;font-size:12px}
          .redirect-chain-duration{color:#34d399;font-size:11px;margin-left:auto}
          .redirect-chain-arrow{color:#60a5fa;font-size:11px;margin-top:6px;padding-left:24px}
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
          sqlSection = `
            <div class="section">
              <div class="section-title">
                <span>Database Queries</span>
                <span class="badge badge-info">${totalCount} queries</span>
                <span class="badge badge-success">${totalTime} ms</span>
              </div>
              <div class="sql-list">
                ${data.sql.map((q, idx) => {
                  const duration = q.duration_ms?.toFixed?.(2) ?? q.duration_ms ?? 0;
                  const rowCount = q.row_count !== null && q.row_count !== undefined ? q.row_count : '?';
                  const bindings = q.bindings && Object.keys(q.bindings).length > 0 
                    ? JSON.stringify(q.bindings) 
                    : '';
                  const error = q.error ? `<div class="sql-error">Error: ${q.error}</div>` : '';
                  return `
                    <div class="sql-item">
                      <div class="sql-header">
                        <div>
                          <span class="badge badge-info">#${idx + 1}</span>
                          <span class="sql-time">${duration} ms</span>
                          <span class="sql-rows">${rowCount} rows</span>
                        </div>
                      </div>
                      <div class="sql-query">${q.sql || 'N/A'}</div>
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
            <div class="section">
              <div class="section-title">Database Queries</div>
              <div class="no-data">No database queries detected</div>
            </div>
          `;
        }
        
        // Build redirect section if applicable
        let redirectSection = '';
        if (data.is_redirect && data.redirect_url) {
          redirectSection = `
            <div class="row redirect-row">
              <span class="key">Redirect to:</span> 
              <span class="val redirect-url">→ ${data.redirect_url}</span>
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
                      <span class="redirect-chain-method">${itemMethod}</span>
                      <span class="redirect-chain-path">${itemPath}</span>
                      <span class="redirect-chain-duration">${itemDuration} ms</span>
                    </div>
                    ${itemRedirectUrl ? `<div class="redirect-chain-arrow">↓ Redirected to: ${itemRedirectUrl}</div>` : ''}
                  </div>
                `;
              }).join('');
              
              redirectChainSection = `
                <div class="section">
                  <div class="section-title">
                    <span>Redirect Chain</span>
                    <span class="badge badge-warning">${chain.length} redirect${chain.length > 1 ? 's' : ''}</span>
                  </div>
                  <div class="redirect-chain-list">
                    ${chainItems}
                    <div class="redirect-chain-item redirect-chain-current">
                      <div class="redirect-chain-header">
                        <span class="badge badge-success">Current</span>
                        <span class="redirect-chain-status">${data.status}</span>
                        <span class="redirect-chain-method">${data.method}</span>
                        <span class="redirect-chain-path">${data.route}</span>
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
        
        wrap.innerHTML = `
          <div class="section">
            <div class="section-title">Request Information</div>
            <div class="row"><span class="key">Request ID:</span> <span class="val">${data.id}</span></div>
            <div class="row"><span class="key">Method:</span> <span class="val">${data.method}</span></div>
            <div class="row"><span class="key">Path:</span> <span class="val">${data.route}</span></div>
            <div class="row"><span class="key">Status:</span> <span class="val">${data.status}</span></div>
            ${redirectSection}
            <div class="row"><span class="key">Duration:</span> <span class="val">${(data.duration_ms?.toFixed?.(1) ?? data.duration_ms)} ms</span></div>
            <div class="row"><span class="key">Memory Peak:</span> <span class="val">${((data.memory_peak || 0) / (1024*1024)).toFixed(2)} MB</span></div>
          </div>
          ${redirectChainSection}
          ${sqlSection}
        `;
        root.appendChild(style);
        root.appendChild(wrap);
      }).catch(()=>{});
    } else {
      root.innerHTML = '';
    }
  }
};
