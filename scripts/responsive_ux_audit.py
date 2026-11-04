from playwright.sync_api import sync_playwright
import json, os

base=os.getenv('SANJAY_AUDIT_BASE','http://127.0.0.1:8124')
outdir=os.getenv('SANJAY_AUDIT_OUT','/mnt/data/sanjay_nursery_responsive_audit')
os.makedirs(outdir,exist_ok=True)
viewports=[('desktop1440',1440,1000),('desktop1280',1280,900),('tablet1024',1024,900),('tablet820',820,900),('tablet768',768,900),('mobile430',430,900),('mobile390',390,844),('mobile360',360,800)]
pages=[('home','/'),('catalogue','/shop'),('care','/materials'),('about','/about'),('contact','/contact')]
results=[]
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for vp,w,h in viewports:
        for name,path in pages:
            page=browser.new_page(viewport={'width':w,'height':h},device_scale_factor=1)
            errors=[]
            page.on('console',lambda msg,arr=errors: arr.append('console:'+msg.text) if msg.type=='error' else None)
            page.on('pageerror',lambda exc,arr=errors: arr.append('pageerror:'+str(exc)))
            try:
                r=page.goto(base+path,wait_until='networkidle',timeout=30000)
                status=r.status if r else 0
                page.evaluate("document.querySelectorAll('.reveal').forEach(e=>e.classList.add('in'))")
                dims=page.evaluate("({sw:document.documentElement.scrollWidth,cw:document.documentElement.clientWidth,body:document.body.scrollWidth})")
                broken=page.evaluate("Array.from(document.images).filter(i=>i.complete&&i.naturalWidth===0).map(i=>i.src)")
                shot=os.path.join(outdir,f'{name}_{vp}.png')
                page.screenshot(path=shot,full_page=False)
                results.append({'viewport':vp,'width':w,'page':name,'status':status,'overflow':max(dims['sw'],dims['body'])>dims['cw']+1,'scrollWidth':dims['sw'],'clientWidth':dims['cw'],'broken_images':len(broken),'errors':errors})
            except Exception as e:
                results.append({'viewport':vp,'width':w,'page':name,'status':0,'overflow':None,'broken_images':None,'errors':[str(e)]})
            page.close()
    for label,w,h in [('desktop',1440,900),('tablet',820,900),('mobile',390,844)]:
        page=browser.new_page(viewport={'width':w,'height':h})
        errs=[]; page.on('pageerror',lambda e,arr=errs: arr.append(str(e)))
        page.goto(base+'/shop',wait_until='networkidle')
        data={'viewport':label,'interaction':True,'catalogue_count':page.locator('.product-card').count()}
        try:
            if label=='desktop' and page.locator('[data-mega-toggle]').count():
                btn=page.locator('[data-mega-toggle]'); btn.click(); data['mega_open']=page.locator('[data-mega]').is_visible(); page.keyboard.press('Escape'); data['mega_escape_closes']=not page.locator('[data-mega]').is_visible()
            elif label!='desktop' and page.locator('[data-menu-toggle]').count():
                page.locator('[data-menu-toggle]').click(); data['menu_open']=page.locator('[data-menu]').is_visible(); page.locator('[data-menu-toggle]').click(); data['menu_close']=not page.locator('[data-menu]').is_visible()
            data['errors']=errs
        except Exception as e: data['errors']=errs+[str(e)]
        results.append(data); page.close()
    browser.close()

result_file=os.path.join(outdir,'RESPONSIVE_UX_RESULTS.json')
with open(result_file,'w') as f: json.dump(results,f,indent=2)
fail=[]
for r in results:
    if r.get('interaction'): continue
    if r.get('status')!=200 or r.get('overflow') or r.get('broken_images') or r.get('errors'): fail.append(r)
print('screens',sum(1 for r in results if not r.get('interaction')),'failures',len(fail))
if fail:
    print(json.dumps(fail,indent=2)); raise SystemExit(1)
print('PASS')
