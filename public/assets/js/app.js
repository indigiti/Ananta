(() => {
  const root = document.documentElement;
  const themeMeta = document.querySelector('meta[data-theme-color]');
  let stored = null;
  try { stored = localStorage.getItem('sanjay-theme'); } catch (e) { stored = null; }
  const systemDark = () => !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  const resolvedTheme = () => (stored === 'dark' || stored === 'light') ? stored : (systemDark() ? 'dark' : 'light');
  const syncThemeControls = theme => {
    document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
      const dark = theme === 'dark';
      btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
      btn.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
      if (btn.dataset.tooltip !== undefined) btn.dataset.tooltip = dark ? 'Light mode' : 'Dark mode';
      const label = btn.querySelector('[data-theme-label]');
      if (label) label.textContent = dark ? 'Light mode' : 'Dark mode';
    });
  };
  const applyTheme = (theme, persist = false) => {
    const next = theme === 'dark' ? 'dark' : 'light';
    root.dataset.theme = next;
    root.style.colorScheme = next;
    if (themeMeta) themeMeta.setAttribute('content', next === 'dark' ? '#121a16' : '#173f35');
    syncThemeControls(next);
    if (persist) {
      stored = next;
      try { localStorage.setItem('sanjay-theme', next); } catch (e) {}
    }
  };
  applyTheme(resolvedTheme());
  document.querySelectorAll('[data-theme-toggle]').forEach(btn => btn.addEventListener('click', () => {
    applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark', true);
  }));
  if (window.matchMedia) {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const onSystemTheme = () => { if (stored !== 'dark' && stored !== 'light') applyTheme(media.matches ? 'dark' : 'light'); };
    if (typeof media.addEventListener === 'function') media.addEventListener('change', onSystemTheme);
    else if (typeof media.addListener === 'function') media.addListener(onSystemTheme);
  }

  const menuBtn = document.querySelector('[data-menu-toggle]');
  const menu = document.querySelector('[data-menu]');
  if (menuBtn && menu) {
    menuBtn.addEventListener('click', () => {
      const open = menu.hasAttribute('hidden');
      if (open) menu.removeAttribute('hidden'); else menu.setAttribute('hidden','');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.classList.toggle('menu-open', open);
    });
    menu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
      menu.setAttribute('hidden',''); document.body.classList.remove('menu-open'); menuBtn.setAttribute('aria-expanded','false');
    }));
  }

  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.add('in'); io.unobserve(entry.target); }
    }), {threshold:.08, rootMargin:'0px 0px -25px'});
    document.querySelectorAll('.reveal').forEach(el => io.observe(el));
  } else document.querySelectorAll('.reveal').forEach(el => el.classList.add('in'));

  const finder = document.querySelector('[data-finder]');
  if (finder) {
    const steps = [...finder.querySelectorAll('.finder-step')];
    const result = finder.querySelector('.finder-result');
    const bar = finder.querySelector('.finder-progress i');
    const status = finder.querySelector('[data-finder-status]');
    const state = {};
    let step = 0;
    const show = () => {
      steps.forEach((s,i) => s.classList.toggle('active', i === step));
      bar.style.width = `${Math.min(100, ((step+1)/steps.length)*100)}%`;
      status.textContent = step === 0 ? 'Choose a room to begin.' : step === 1 ? `Room: ${state.room}` : `Style: ${state.style}`;
    };
    finder.querySelectorAll('[data-choice]').forEach(btn => btn.addEventListener('click', () => {
      state[btn.dataset.choice] = btn.dataset.value;
      step++;
      if (step < steps.length) show(); else {
        steps.forEach(s=>s.classList.remove('active'));
        result.hidden = false;
        bar.style.width = '100%';
        status.textContent = 'Profile complete.';
        result.querySelector('[data-result-title]').textContent = `${state.style || 'Warm'} · ${state.material || 'Botanical' }`;
        result.querySelector('[data-result-copy]').textContent = `For your ${state.room || 'space'}, start with a ${state.material || 'plant-led'} palette and keep the composition ${String(state.style || 'quiet').toLowerCase()}. We recommend one focal plant, one suitable planter and a simple care routine.`;
      }
    }));
    finder.querySelector('[data-restart]')?.addEventListener('click', () => {
      Object.keys(state).forEach(k => delete state[k]); step=0; result.hidden=true; show();
    });
    show();
  }
})();

// Progressive confirmation for consequential browser forms.
document.addEventListener('submit', function(e){ const f=e.target.closest('form[data-confirm]'); if(f && !window.confirm(f.dataset.confirm || 'Continue?')) e.preventDefault(); });

// v0.4.0 — server-authoritative live product configurator.
(() => {
  const money = n => new Intl.NumberFormat('en-IN', {style:'currency', currency:'INR', maximumFractionDigits:0}).format(Number(n || 0));
  document.querySelectorAll('[data-configurator]').forEach(shell => {
    const form = shell.querySelector('[data-config-form]');
    if (!form) return;
    const quoteUrl = shell.dataset.quoteUrl;
    const total = shell.querySelector('[data-config-total]');
    const base = shell.querySelector('[data-config-base]');
    const status = shell.querySelector('[data-config-status]');
    const adjustments = shell.querySelector('[data-config-adjustments]');
    const productPrice = document.querySelector('[data-config-price]');
    const addButton = shell.querySelector('[data-add-config]');
    const saveForm = shell.querySelector('[data-save-config]');
    let timer = null;

    const syncRange = input => {
      const out = input.closest('.range-field')?.querySelector('output');
      if (out) out.textContent = `${input.value} ${input.closest('.range-field')?.querySelector('small')?.textContent.match(/cm|mm|in/)?.[0] || 'cm'}`;
    };
    shell.querySelectorAll('[data-range]').forEach(input => { syncRange(input); input.addEventListener('input', () => syncRange(input)); });

    const syncSaveForm = () => {
      if (!saveForm) return;
      const bucket = saveForm.querySelector('[data-save-config-fields]');
      if (!bucket) return;
      bucket.innerHTML = '';
      const data = new FormData(form);
      for (const [key,value] of data.entries()) {
        if (['_csrf','slug','qty','configuration_guid'].includes(key)) continue;
        const input = document.createElement('input'); input.type='hidden'; input.name=key; input.value=String(value); bucket.appendChild(input);
      }
      // unchecked boolean options are intentionally absent and normalize to false server-side.
    };

    const renderQuote = quote => {
      if (!quote || !quote.ok) {
        status.textContent = (quote?.errors || ['Please review this configuration.']).join(' ');
        status.classList.add('error');
        if (addButton) addButton.disabled = true;
        return;
      }
      status.classList.remove('error');
      status.textContent = `Server-authoritative · pricing rules ${quote.pricing_version}`;
      if (base) base.textContent = money(quote.base_price);
      if (total) total.textContent = money(quote.unit_price);
      if (productPrice) productPrice.innerHTML = `${money(quote.unit_price)} <small>configured staging price</small>`;
      if (addButton) { addButton.disabled = false; const span=addButton.querySelector('span'); if(span) span.textContent=money(quote.unit_price); }
      if (adjustments) adjustments.innerHTML = (quote.adjustments || []).map(a => `<span>${String(a.label).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))} <b>${a.delta >= 0 ? '+' : ''}${money(a.delta)}</b></span>`).join('');
      syncSaveForm();
    };

    const quote = async () => {
      if (!quoteUrl) return;
      status.textContent = 'Updating price…'; status.classList.remove('error');
      try {
        const body = new FormData(form);
        const response = await fetch(quoteUrl, {method:'POST', body, headers:{'X-Requested-With':'XMLHttpRequest'}});
        const payload = await response.json(); renderQuote(payload);
      } catch (err) {
        status.textContent = 'Live pricing is temporarily unavailable. Refresh and try again.'; status.classList.add('error');
        if (addButton) addButton.disabled = true;
      }
    };
    const schedule = () => { clearTimeout(timer); timer = setTimeout(quote, 120); syncSaveForm(); };
    form.addEventListener('change', schedule); form.addEventListener('input', e => { if (e.target.matches('input[type="range"],input[type="text"]')) schedule(); });
    saveForm?.addEventListener('submit', syncSaveForm);
    syncSaveForm();
  });
})();

// v0.9.8.1 — Marketplace Luxe interaction layer.
(() => {
  const megaBtn = document.querySelector('[data-mega-toggle]');
  const mega = document.querySelector('[data-mega]');
  const closeMega = () => { if (!megaBtn || !mega) return; mega.hidden = true; megaBtn.setAttribute('aria-expanded','false'); };
  if (megaBtn && mega) {
    megaBtn.addEventListener('click', e => { e.stopPropagation(); const open = mega.hidden; mega.hidden = !open; megaBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); });
    document.addEventListener('click', e => { if (!mega.hidden && !mega.contains(e.target) && e.target !== megaBtn) closeMega(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeMega(); megaBtn.focus(); } });
  }

  const bindSheet = (toggleSelector, sheetSelector) => {
    const toggle = document.querySelector(toggleSelector); const sheet = document.querySelector(sheetSelector);
    if (!toggle || !sheet) return;
    let previous = null;
    const open = () => { previous = document.activeElement; sheet.hidden = false; document.body.classList.add('sheet-open'); sheet.querySelector('button,[href],input,select')?.focus(); };
    const close = () => { sheet.hidden = true; document.body.classList.remove('sheet-open'); previous?.focus?.(); };
    toggle.addEventListener('click', open); sheet.querySelectorAll('[data-sheet-close]').forEach(el => el.addEventListener('click', close));
    document.addEventListener('keydown', e => { if (!sheet.hidden && e.key === 'Escape') close(); });
  };
  bindSheet('[data-filter-toggle]','[data-filter-sheet]');
  bindSheet('[data-sort-toggle]','[data-sort-sheet]');

  document.querySelectorAll('[data-pincode-widget]').forEach(widget => {
    const input = widget.querySelector('input'); const button = widget.querySelector('[data-pincode-check]'); const result = widget.querySelector('[data-pincode-result]');
    if (!input || !button || !result) return;
    const check = async () => {
      const pin = String(input.value || '').replace(/\D/g,'').slice(0,6); input.value = pin;
      result.className = ''; result.textContent = 'Checking delivery…';
      if (pin.length !== 6) { result.classList.add('error'); result.textContent = 'Enter a valid 6-digit pincode.'; return; }
      try {
        const variantSelect = document.querySelector('[data-variant-select]');
        const variantGuid = variantSelect?.value || '';
        if (variantSelect && !variantGuid) { result.classList.add('error'); result.textContent = 'Choose a live plant size / batch first.'; return; }
        const endpoint = `${widget.dataset.shippingUrl || '/api/shipping'}?pincode=${encodeURIComponent(pin)}${variantGuid ? `&variant_guid=${encodeURIComponent(variantGuid)}` : ''}`;
        const r = await fetch(endpoint, {headers:{'X-Requested-With':'XMLHttpRequest'}});
        const data = await r.json();
        if (!data.serviceable) { result.classList.add('error'); result.textContent = data.error || 'Delivery is unavailable.'; return; }
        result.classList.add('success'); result.textContent = `${data.eta} · ${data.installation_available ? 'Installation available' : 'Delivery available'}`;
      } catch (e) { result.classList.add('error'); result.textContent = 'Delivery check is temporarily unavailable.'; }
    };
    button.addEventListener('click', check); input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); check(); } });
  });

  // Keep the compact mobile purchase price aligned with server-authoritative live quote rendering.
  const mobilePrice = document.querySelector('[data-mobile-pdp-price]');
  const configuredPrice = document.querySelector('[data-config-price]');
  if (mobilePrice && configuredPrice && 'MutationObserver' in window) {
    const sync = () => { const text = configuredPrice.textContent.trim().split('configured')[0].trim(); if (text) mobilePrice.textContent = text; };
    new MutationObserver(sync).observe(configuredPrice,{childList:true,subtree:true,characterData:true}); sync();
  }
})();

// Sanjay Nursery v1.4.0 — consent-aware growth measurement.
(() => {
  const body = document.body;
  if (!body || body.dataset.analyticsEnabled !== '1') return;
  const ga4 = String(body.dataset.ga4 || '').trim();
  const gtm = String(body.dataset.gtm || '').trim();
  const needsConsent = body.dataset.analyticsConsent === '1';
  const consentKey = 'sanjay-analytics-consent';
  const banner = document.querySelector('[data-analytics-consent-banner]');
  let loaded = false;

  const readConsent = () => {
    if (!needsConsent) return 'accepted';
    try { return localStorage.getItem(consentKey) || ''; } catch (e) { return ''; }
  };
  const writeConsent = value => { try { localStorage.setItem(consentKey, value); } catch (e) {} };
  const push = (event, params = {}) => {
    window.dataLayer = window.dataLayer || [];
    if (gtm) { window.dataLayer.push({event, ...params}); return; }
    if (typeof window.gtag === 'function' && ga4) window.gtag('event', event, params);
  };
  window.sanjayTrack = push;

  const loadMeasurement = () => {
    if (loaded || (!ga4 && !gtm)) return;
    loaded = true;
    window.dataLayer = window.dataLayer || [];
    if (gtm) {
      window.dataLayer.push({'gtm.start': Date.now(), event: 'gtm.js'});
      const s = document.createElement('script'); s.async = true; s.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(gtm)}`; document.head.appendChild(s);
    } else if (ga4) {
      window.gtag = function(){ window.dataLayer.push(arguments); };
      window.gtag('js', new Date()); window.gtag('config', ga4, {send_page_view: true});
      const s = document.createElement('script'); s.async = true; s.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(ga4)}`; document.head.appendChild(s);
    }
  };
  const applyConsent = value => {
    if (value === 'accepted') loadMeasurement();
    if (banner) banner.hidden = true;
  };

  const existing = readConsent();
  if (existing) applyConsent(existing); else if (banner) banner.hidden = false;
  document.querySelector('[data-consent-accept]')?.addEventListener('click', () => { writeConsent('accepted'); applyConsent('accepted'); });
  document.querySelector('[data-consent-decline]')?.addEventListener('click', () => { writeConsent('declined'); applyConsent('declined'); });
  document.querySelector('[data-consent-save]')?.addEventListener('click', () => { const allow=!!document.querySelector('[data-consent-analytics]')?.checked; writeConsent(allow?'accepted':'declined'); applyConsent(allow?'accepted':'declined'); });

  document.addEventListener('click', e => {
    const el = e.target.closest('[data-track]'); if (!el || readConsent() !== 'accepted') return;
    const params = {};
    for (const [k,v] of Object.entries(el.dataset)) if (k !== 'track' && v !== '') params[k.replace(/[A-Z]/g,m=>'_'+m.toLowerCase())] = v;
    push(el.dataset.track, params);
  });
  document.addEventListener('submit', e => {
    const form = e.target.closest('form'); if (!form || readConsent() !== 'accepted') return;
    if (form.dataset.trackSubmit) push(form.dataset.trackSubmit, {form_location: location.pathname});
    if (form.matches('[role="search"]')) {
      const q = String(new FormData(form).get('q') || '').trim(); if (q) push('search', {search_term: q});
    }
  });
  if (readConsent() === 'accepted') {
    document.querySelectorAll('[data-product-card]').forEach((card, index) => {
      card.dataset.itemListPosition = String(index + 1);
    });
    const viewed = document.querySelector('[data-view-item]');
    if (viewed) push('view_item', {item_id: viewed.dataset.itemId || '', item_name: viewed.dataset.itemName || '', item_category: viewed.dataset.itemCategory || ''});
  }
})();

// Sanjay Nursery v1.5.0 — Shopping Intelligence & Personalisation Engine.
(() => {
  const cfgNode = document.getElementById('sanjay-intelligence-config');
  let cfg = {};
  try { cfg = JSON.parse(cfgNode?.textContent || '{}'); } catch (e) { cfg = {}; }
  if (!cfg.enabled) return;

  const CONSENT_KEY = 'sanjay-consent-v2';
  const VISITOR_KEY = 'sanjay-visitor-id';
  const SESSION_KEY = 'sanjay-session-id';
  const LAST_SEARCH_KEY = 'sanjay-last-search';
  const apiBase = (path) => `${document.querySelector('base')?.href || ''}${path}`;
  const randomId = (prefix) => {
    const bytes = new Uint8Array(8);
    if (window.crypto?.getRandomValues) window.crypto.getRandomValues(bytes); else for (let i=0;i<bytes.length;i++) bytes[i]=Math.floor(Math.random()*256);
    return `${prefix}-${[...bytes].map(b=>b.toString(16).padStart(2,'0')).join('').toUpperCase()}`;
  };
  const safeJSON = (key, fallback) => { try { const v=localStorage.getItem(key); return v ? JSON.parse(v) : fallback; } catch(e){ return fallback; } };
  const setJSON = (key, value) => { try { localStorage.setItem(key, JSON.stringify(value)); } catch(e) {} };
  const defaultConsent = {essential:true, analytics:false, personalisation:false, marketing:false};
  let consent = safeJSON(CONSENT_KEY, null);
  if (!consent || typeof consent !== 'object') consent = {...defaultConsent};
  consent = {...defaultConsent, ...consent, essential:true};

  const persistentIdentityAllowed = () => !!(consent.analytics || consent.personalisation);
  const getVisitor = () => {
    let v = '';
    try { v = persistentIdentityAllowed() ? (localStorage.getItem(VISITOR_KEY) || '') : (sessionStorage.getItem(VISITOR_KEY) || ''); } catch(e) {}
    if (!/^V-[A-Z0-9]{8,32}$/.test(v)) v=randomId('V');
    try { if (persistentIdentityAllowed()) { localStorage.setItem(VISITOR_KEY,v); sessionStorage.removeItem(VISITOR_KEY); } else sessionStorage.setItem(VISITOR_KEY,v); } catch(e) {}
    return v;
  };
  const getSession = () => { let s='';try{s=sessionStorage.getItem(SESSION_KEY)||'';}catch(e){} if(!/^S-[A-Z0-9]{8,32}$/.test(s)){s=randomId('S');try{sessionStorage.setItem(SESSION_KEY,s);}catch(e){}}return s; };
  let visitorId=getVisitor(); const sessionId=getSession();

  const syncLegacyAnalyticsConsent = () => { try { localStorage.setItem('sanjay-analytics-consent', consent.analytics ? 'accepted':'declined'); } catch(e) {} };
  const syncConsentToServer = () => {
    fetch('/api/intelligence/consent',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({visitor_id:visitorId,consent})}).catch(()=>{});
  };
  const applyConsentUI = () => {
    document.querySelectorAll('[data-consent-analytics]').forEach(x=>x.checked=!!consent.analytics);
    document.querySelectorAll('[data-consent-personalisation]').forEach(x=>x.checked=!!consent.personalisation);
    document.querySelectorAll('[data-consent-marketing]').forEach(x=>x.checked=!!consent.marketing);
  };
  const saveConsent = (next) => {
    consent={...defaultConsent,...next,essential:true};setJSON(CONSENT_KEY,consent);syncLegacyAnalyticsConsent();visitorId=getVisitor();syncConsentToServer();
    document.querySelector('[data-si-consent-banner]')?.setAttribute('hidden','');
  };
  const banner=document.querySelector('[data-si-consent-banner]');
  const hasSavedConsent=(()=>{try{return !!localStorage.getItem(CONSENT_KEY);}catch(e){return false;}})();
  applyConsentUI();if(banner&&!hasSavedConsent)banner.removeAttribute('hidden');
  document.querySelector('[data-consent-essential]')?.addEventListener('click',()=>saveConsent(defaultConsent));
  document.querySelector('[data-consent-accept]')?.addEventListener('click',()=>saveConsent({essential:true,analytics:true,personalisation:true,marketing:false}));
  document.querySelector('[data-consent-save]')?.addEventListener('click',()=>saveConsent({essential:true,analytics:!!document.querySelector('[data-consent-analytics]')?.checked,personalisation:!!document.querySelector('[data-consent-personalisation]')?.checked,marketing:!!document.querySelector('[data-consent-marketing]')?.checked}));

  const canTrack = () => !!(consent.analytics || consent.personalisation);
  const lastSearch = () => { try{return sessionStorage.getItem(LAST_SEARCH_KEY)||'';}catch(e){return '';} };
  const eventQueue=[];let eventFlushTimer=null;
  const flushEvents=(beacon=false)=>{
    if(!eventQueue.length||!canTrack())return;const events=eventQueue.splice(0,40);const json=JSON.stringify({events});clearTimeout(eventFlushTimer);eventFlushTimer=null;
    if(beacon&&navigator.sendBeacon&&json.length<64000){try{const blob=new Blob([json],{type:'application/json'});if(navigator.sendBeacon('/api/intelligence/events',blob))return;}catch(e){}}
    fetch('/api/intelligence/events',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:json,keepalive:beacon}).catch(()=>{events.forEach(x=>eventQueue.push(x));});
  };
  const sendEvent = (type, props={}) => {
    if (!canTrack()) return;
    eventQueue.push({visitor_id:visitorId,session_id:sessionId,type,consent,props:{path:location.pathname,...props}});
    if(eventQueue.length>=12)flushEvents(false);else if(!eventFlushTimer)eventFlushTimer=setTimeout(()=>flushEvents(false),350);
  };
  document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')flushEvents(true);});
  window.addEventListener('pagehide',()=>flushEvents(true));
  window.sanjayIntelligence={track:sendEvent,visitorId,sessionId,consent:()=>({...consent})};

  // Page + discovery events.
  sendEvent('page_view',{referrer_path:document.referrer ? (()=>{try{return new URL(document.referrer).pathname}catch(e){return ''}})() : ''});
  if(location.pathname==='/')sendEvent('homepage_view');
  if(location.pathname.startsWith('/collections/'))sendEvent('collection_view',{collection:location.pathname.split('/').pop()||''});
  const catalog=document.querySelector('[data-search-results]');
  if(catalog){
    const q=catalog.dataset.searchQuery||''; const count=Number(catalog.dataset.searchResults||0);
    if(q){try{sessionStorage.setItem(LAST_SEARCH_KEY,q);}catch(e){} sendEvent('search_submitted',{search_query:q,result_count:count}); if(count===0)sendEvent('search_no_result',{search_query:q,result_count:0});}
    if(catalog.dataset.searchCategory)sendEvent('filter_used',{filter_name:'category',filter_value:catalog.dataset.searchCategory});
    if(catalog.dataset.searchCollection)sendEvent('filter_used',{filter_name:'collection',filter_value:catalog.dataset.searchCollection});
    const activeFilters=new URLSearchParams(location.search);['light','water','difficulty','size','placement','pet_safe','flowering','air_purifying','availability','price_min','price_max'].forEach(key=>{const value=activeFilters.get(key);if(value)sendEvent('filter_used',{filter_name:key,filter_value:value});});
    const sort=activeFilters.get('sort');if(sort&&sort!=='recommended')sendEvent('sort_used',{sort_value:sort});
  }
  const viewed=document.querySelector('[data-view-item]');
  if(viewed){
    const props={product_slug:location.pathname.split('/').pop()||'',product_sku:viewed.dataset.itemId||'',collection:viewed.dataset.itemCategory||'',search_query:lastSearch()};
    sendEvent('product_view',props);setTimeout(()=>{if(document.visibilityState==='visible')sendEvent('engagement_20s',{...props,duration_seconds:20});},20000);
    document.querySelector('.product-gallery')?.addEventListener('click',()=>sendEvent('gallery_view',props),{once:true});
  }

  // Product impressions are observed, not fired for every DOM card at load.
  if('IntersectionObserver' in window){
    const seen=new WeakSet();const io=new IntersectionObserver(entries=>entries.forEach(en=>{if(!en.isIntersecting||seen.has(en.target))return;seen.add(en.target);const c=en.target;sendEvent('product_impression',{product_slug:c.dataset.productSlug||'',product_guid:c.dataset.productGuid||'',collection:c.dataset.itemCategory||'',search_query:lastSearch()});io.unobserve(c);}),{threshold:.35});document.querySelectorAll('[data-product-card]').forEach(c=>io.observe(c));
  }
  document.addEventListener('click',e=>{
    const card=e.target.closest('[data-product-card]');const link=e.target.closest('[data-product-card] a');if(card&&link)sendEvent('product_click',{product_slug:card.dataset.productSlug||'',product_guid:card.dataset.productGuid||'',collection:card.dataset.itemCategory||'',search_query:lastSearch()});if(card&&link&&lastSearch())sendEvent('search_result_click',{product_slug:card.dataset.productSlug||'',product_guid:card.dataset.productGuid||'',search_query:lastSearch()});
  });
  document.addEventListener('change',e=>{
    if(e.target.matches('[name="variant_preview"],[name="variant_guid"]'))sendEvent('variant_selected',{product_slug:viewed?location.pathname.split('/').pop()||'':'',variant_guid:e.target.value||''});
    if(e.target.matches('[name="qty"],input[name^="qty["]'))sendEvent('quantity_changed',{quantity:Number(e.target.value||0)});
  });
  document.addEventListener('submit',e=>{const f=e.target.closest('[data-intel-event]');if(!f)return;sendEvent(f.dataset.intelEvent,{product_slug:f.dataset.productSlug||'',search_query:lastSearch()});});
  document.querySelectorAll('[data-intel-event="checkout_started"]').forEach(el=>el.addEventListener('click',()=>sendEvent('checkout_started',{cart_total:Number(document.querySelector('[data-intelligence-cart]')?.dataset.cartTotal||0)})));

  // Generic search suggestions, plus learning only after submission.
  document.querySelectorAll('form[role="search"] input[name="q"]').forEach(input=>{
    const wrap=input.closest('form'); if(!wrap)return; wrap.classList.add('si-search-wrap');
    const box=document.createElement('div');box.className='si-search-suggestions';box.hidden=true;wrap.appendChild(box);let timer=null;
    let searchStarted=false;input.addEventListener('focus',()=>{if(!searchStarted){searchStarted=true;sendEvent('search_started',{query:input.value.trim()});}if(input.value.trim().length>=2)input.dispatchEvent(new Event('input'));});
    input.addEventListener('input',()=>{clearTimeout(timer);const q=input.value.trim();if(q.length<2){box.hidden=true;box.innerHTML='';return;}timer=setTimeout(async()=>{try{const r=await fetch(`/api/discovery/search-suggestions?q=${encodeURIComponent(q)}`);const d=await r.json();box.innerHTML=(d.items||[]).map(x=>`<a href="${x.url}"><small>${x.type}</small><b>${escapeHTML(x.label)}</b></a>`).join('');box.hidden=!box.innerHTML;}catch(e){box.hidden=true;}},140);});
    document.addEventListener('click',e=>{if(!wrap.contains(e.target))box.hidden=true;});
  });

  function escapeHTML(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
  function reasonLabel(reason){return ({similar_product:'Similar to what you viewed',visitor_interest:'Based on your plant interests',search_intent:'Matches this session',cart_gap:'Complete your order',complementary:'Pairs well with your plant',recently_viewed:'Recently viewed',trending:'Popular discovery',category_similarity:'Explore something related'})[reason]||'Recommended';}
  function recCard(item, quick=false){
    const img=item.image?`<img src="${escapeHTML(item.image)}" alt="${escapeHTML(item.name)}" loading="lazy">`:`<div class="si-rec-placeholder">🌿</div>`;
    const quickBtn=quick&&item.variant?`<button type="button" class="btn primary si-quick-add" data-variant="${escapeHTML(item.variant.guid)}" data-rec-id="${escapeHTML(item.recommendation_id)}" data-algorithm="${escapeHTML(item.algorithm)}" data-placement="${escapeHTML(item.placement)}" data-product="${escapeHTML(item.slug)}" data-experiment-variant="${escapeHTML(item.experiment_variant||'')}">+ Add · ₹${Number(item.variant.price).toLocaleString('en-IN')}</button>`:'';
    return `<article class="si-rec-card" data-rec-item data-rec-id="${escapeHTML(item.recommendation_id)}" data-product="${escapeHTML(item.slug)}" data-algorithm="${escapeHTML(item.algorithm)}" data-placement="${escapeHTML(item.placement)}" data-reason="${escapeHTML(item.reason)}"><a href="/product/${encodeURIComponent(item.slug)}">${img}<small>${escapeHTML(reasonLabel(item.reason))}</small><h3>${escapeHTML(item.name)}</h3><p>${escapeHTML(item.botanical_name||item.collection||'')}</p><strong>${escapeHTML(item.price_label)}</strong></a>${quickBtn}</article>`;
  }
  async function loadRail(el, cart=false){
    try{
      const params=new URLSearchParams({visitor:visitorId,session:sessionId,placement:el.dataset.placement||'generic',algorithm:el.dataset.algorithm||'recommended_for_you',limit:el.dataset.limit||'4'});if(el.dataset.product)params.set('product',el.dataset.product);
      const endpoint=cart?'/api/intelligence/cart-recommendations':'/api/intelligence/recommendations';const r=await fetch(`${endpoint}?${params}`);const data=await r.json();const items=cart?(data.recommendations||[]):(data.items||[]);
      if(!items.length){el.closest('[data-intelligence-section]')?.setAttribute('hidden',''); if(cart)el.innerHTML='<p class="admin-muted">No checkout-ready cart additions are available right now.</p>';return;}
      el.innerHTML=items.map(x=>recCard(x,cart && !!cfg.cart_intelligence)).join('');
      items.forEach(x=>sendEvent(cart?'cart_recommendation_seen':'recommendation_seen',{product_slug:x.slug,recommendation_id:x.recommendation_id,algorithm:x.algorithm,placement:x.placement,experiment_variant:x.experiment_variant||'',reason:x.reason}));
      el.querySelectorAll('[data-rec-item] a').forEach(a=>a.addEventListener('click',()=>{const c=a.closest('[data-rec-item]');sendEvent('recommendation_clicked',{product_slug:c.dataset.product,recommendation_id:c.dataset.recId,algorithm:c.dataset.algorithm,placement:c.dataset.placement,reason:c.dataset.reason,search_query:lastSearch()});}));
      el.querySelectorAll('.si-quick-add').forEach(btn=>btn.addEventListener('click',async()=>{
        btn.disabled=true;const old=btn.textContent;btn.textContent='Adding…';try{const rr=await fetch('/api/intelligence/cart-add',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({csrf:cfg.csrf,variant_guid:btn.dataset.variant,qty:1,recommendation_id:btn.dataset.recId,algorithm:btn.dataset.algorithm,placement:btn.dataset.placement,experiment_variant:btn.dataset.experimentVariant||'',search_query:lastSearch()})});const out=await rr.json();if(!out.ok)throw new Error(out.error||'Unable to add');sendEvent('cart_recommendation_add',{product_slug:btn.dataset.product,recommendation_id:btn.dataset.recId,algorithm:btn.dataset.algorithm,placement:btn.dataset.placement,price:0,cart_total:out.cart_total});document.querySelectorAll('[data-cart-count]').forEach(n=>n.textContent=String(out.cart_count));document.querySelectorAll('[data-cart-subtotal],[data-cart-total]').forEach(n=>n.textContent=`₹${Number(out.cart_total).toLocaleString('en-IN')}`);if(out.progress?.enabled){const p=document.querySelector('[data-cart-progress]');if(p){p.querySelector('i span').style.width=`${out.progress.progress}%`;p.querySelector('div span').textContent=out.progress.gap>0?`₹${Number(out.progress.gap).toLocaleString('en-IN')} away from checkout`:'Minimum order reached';p.querySelector('div b').textContent=`${out.progress.progress}%`;}}btn.textContent='Added ✓';setTimeout(()=>loadRail(el,true),350);}catch(err){btn.textContent='Try again';btn.disabled=false;setTimeout(()=>btn.textContent=old,1800);}}));
    }catch(e){ if(cart)el.innerHTML=''; else el.closest('[data-intelligence-section]')?.setAttribute('hidden',''); }
  }
  document.querySelectorAll('[data-intelligence-rail]').forEach(el=>loadRail(el,false));
  document.querySelectorAll('[data-intelligence-cart-rail]').forEach(el=>loadRail(el,true));
  if(document.querySelector('[data-intelligence-cart]'))sendEvent('cart_open',{cart_total:Number(document.querySelector('[data-intelligence-cart]').dataset.cartTotal||0)});
  const progressNode=document.querySelector('[data-cart-progress]');if(progressNode){const cartNode=document.querySelector('[data-intelligence-cart]');sendEvent('minimum_order_progress',{cart_total:Number(cartNode?.dataset.cartTotal||0),minimum:Number(progressNode.querySelector('small')?.textContent.replace(/[^0-9]/g,'')||0)});}
  document.querySelectorAll('.sn-pagination a').forEach(a=>a.addEventListener('click',()=>sendEvent('pagination',{source:'catalogue'})));
  const checkoutForm=document.querySelector('form.checkout-main');if(checkoutForm){let checkoutSubmitted=false;checkoutForm.addEventListener('submit',()=>{checkoutSubmitted=true;});window.addEventListener('pagehide',()=>{if(!checkoutSubmitted)sendEvent('checkout_abandoned',{source:'checkout_page'});});}

  // Purchase attribution is emitted once per confirmed order and never includes customer PII.
  const purchaseNode=document.querySelector('[data-si-purchase]');
  if(purchaseNode&&canTrack()){
    const orderGuid=purchaseNode.dataset.orderGuid||'';const guard=`sanjay-purchase-event-${orderGuid}`;let already=false;try{already=!!localStorage.getItem(guard);}catch(e){}
    if(!already){let items=[];try{items=JSON.parse(purchaseNode.dataset.items||'[]');}catch(e){}const total=Number(purchaseNode.dataset.orderTotal||0);items.forEach(item=>sendEvent('purchase_completed',{...item,cart_total:total,source:'order_confirmation'}));try{localStorage.setItem(guard,'1');}catch(e){}}
  }

  // Privacy preference page actions.
  const privacy=document.querySelector('[data-si-privacy-page]');
  if(privacy){
    const status=privacy.querySelector('[data-si-profile-status]');
    const refresh=async()=>{try{const r=await fetch(`/api/intelligence/profile?visitor=${encodeURIComponent(visitorId)}`);const d=await r.json();status.textContent=d.profile?`Profile has ${d.profile.events} permitted events. Top interests: ${Object.keys(d.profile.interests||{}).slice(0,4).join(', ')||'not enough data yet'}.`:'No personalisation profile exists for this browser.';}catch(e){status.textContent='Profile status is temporarily unavailable.';}};refresh();
    privacy.querySelector('[data-si-reset]')?.addEventListener('click',async()=>{await fetch('/api/intelligence/profile/reset',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({visitor_id:visitorId})});refresh();});
    privacy.querySelector('[data-si-delete]')?.addEventListener('click',async()=>{await fetch('/api/intelligence/profile/delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({visitor_id:visitorId})});saveConsent(defaultConsent);try{localStorage.removeItem(VISITOR_KEY);}catch(e){}visitorId=getVisitor();refresh();});
    privacy.querySelector('[data-si-disable]')?.addEventListener('click',()=>{saveConsent({...consent,personalisation:false,marketing:false});refresh();});
    privacy.querySelector('[data-si-export]')?.addEventListener('click',async()=>{const r=await fetch(`/api/intelligence/profile?visitor=${encodeURIComponent(visitorId)}&format=export`);const d=await r.json();const blob=new Blob([JSON.stringify(d,null,2)],{type:'application/json'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='sanjay-nursery-anonymous-profile.json';a.click();URL.revokeObjectURL(a.href);});
  }
})();


// v1.6.0 — Commerce Activation & Plant Discovery interaction layer.
(() => {
  document.querySelectorAll('[data-auto-submit]').forEach(select => select.addEventListener('change', () => select.form?.requestSubmit()));

  const filterPanel = document.querySelector('[data-discovery-filters]');
  const filterToggle = document.querySelector('[data-discovery-filter-toggle]');
  const filterClose = document.querySelector('[data-discovery-filter-close]');
  const closeFilters = () => {
    if (!filterPanel) return;
    filterPanel.classList.remove('is-open');
    document.body.classList.remove('discovery-filter-open');
    filterToggle?.setAttribute('aria-expanded','false');
  };
  const openFilters = () => {
    if (!filterPanel) return;
    filterPanel.classList.add('is-open');
    document.body.classList.add('discovery-filter-open');
    filterToggle?.setAttribute('aria-expanded','true');
  };
  filterToggle?.addEventListener('click', () => filterPanel?.classList.contains('is-open') ? closeFilters() : openFilters());
  filterClose?.addEventListener('click', closeFilters);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFilters(); });

  document.querySelectorAll('.sn-variant-engine').forEach(engine => {
    const select = engine.querySelector('[data-variant-select]');
    if (!select) return;
    const radios = [...engine.querySelectorAll('[data-variant-card] input[type="radio"]')];
    const sync = value => {
      engine.querySelectorAll('[data-variant-card]').forEach(card => card.classList.toggle('is-selected', card.dataset.variantCard === value));
      radios.forEach(r => { r.checked = r.value === value; });
      if (select.value !== value) select.value = value;
    };
    radios.forEach(r => r.addEventListener('change', () => { if (r.checked) sync(r.value); }));
    select.addEventListener('change', () => sync(select.value));
  });

  document.querySelectorAll('.sn-discovery-filters input[type="radio"]').forEach(input => input.addEventListener('click', e => {
    // Clicking an already-selected radio again clears that facet without JavaScript-generated hidden state.
    const last = input.dataset.wasChecked === '1';
    document.querySelectorAll(`input[type="radio"][name="${CSS.escape(input.name)}"]`).forEach(x => x.dataset.wasChecked = '0');
    if (last) { input.checked = false; input.dataset.wasChecked = '0'; e.preventDefault(); }
    else input.dataset.wasChecked = '1';
  }));
  document.querySelectorAll('.sn-discovery-filters input[type="radio"]:checked').forEach(input => input.dataset.wasChecked = '1');
})();
