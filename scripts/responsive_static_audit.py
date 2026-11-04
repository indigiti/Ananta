from playwright.sync_api import sync_playwright
import json, os
from pathlib import Path

indir=Path(os.getenv('SANJAY_STATIC_DIR','/mnt/data/sanjay_nursery_static'))
outdir=Path(os.getenv('SANJAY_AUDIT_OUT','/mnt/data/sanjay_nursery_static_audit'))
outdir.mkdir(exist_ok=True)
viewports=[('desktop1440',1440,1000),('desktop1280',1280,900),('tablet1024',1024,900),('tablet820',820,900),('mobile430',430,900),('mobile390',390,844),('mobile360',360,800)]
pages=['home','catalogue','care','about','contact']
results=[]
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox','--allow-file-access-from-files'])
    for vp,w,h in viewports:
        for name in pages:
            page=browser.new_page(viewport={'width':w,'height':h},device_scale_factor=1)
            errors=[]
            page.on('console',lambda m,arr=errors: arr.append('console:'+m.text) if m.type=='error' else None)
            page.on('pageerror',lambda e,arr=errors: arr.append('pageerror:'+str(e)))
            try:
                page.goto((indir/f'{name}.html').as_uri(),wait_until='load',timeout=30000)
                page.wait_for_timeout(150)
                dims=page.evaluate("({sw:document.documentElement.scrollWidth,cw:document.documentElement.clientWidth,bw:document.body.scrollWidth})")
                broken=page.evaluate("Array.from(document.images).filter(i=>i.complete&&i.naturalWidth===0).map(i=>i.src)")
                page.screenshot(path=str(outdir/f'{name}_{vp}.png'),full_page=False)
                results.append({'viewport':vp,'width':w,'page':name,'overflow':max(dims['sw'],dims['bw'])>dims['cw']+1,'broken_images':len(broken),'errors':errors})
            except Exception as e:
                results.append({'viewport':vp,'width':w,'page':name,'overflow':None,'broken_images':None,'errors':[str(e)]})
            page.close()
    browser.close()
(outdir/'RESPONSIVE_UX_RESULTS.json').write_text(json.dumps(results,indent=2))
fail=[r for r in results if r.get('overflow') or r.get('broken_images') or r.get('errors')]
print('screens',len(results),'failures',len(fail))
if fail:
    print(json.dumps(fail,indent=2)); raise SystemExit(1)
print('PASS')
