#!/usr/bin/env python3
import sys, json
from PIL import Image, ImageStat

def classify(rgb, brightness):
    r,g,b=rgb
    warmth=(r-b)/255.0
    if brightness>205: mood='light-airy'
    elif brightness<95: mood='moody-deep'
    else: mood='balanced-warm' if warmth>0.06 else 'balanced-neutral'
    palette=[]
    if warmth>0.08: palette=['warm ivory','natural oak','walnut']
    elif warmth<-0.05: palette=['soft grey','ash','charcoal']
    else: palette=['soft stone','leaf green','terracotta']
    return mood,palette

def main():
    if len(sys.argv)<2: print(json.dumps({'ok':False,'error':'image required'})); return 2
    try:
        with Image.open(sys.argv[1]) as im:
            im=im.convert('RGB'); im.thumbnail((720,720))
            stat=ImageStat.Stat(im); rgb=[int(x) for x in stat.mean[:3]]; brightness=int(sum(rgb)/3)
            mood,palette=classify(rgb,brightness)
            print(json.dumps({'ok':True,'provider':'sandbox-pillow-v1','average_rgb':rgb,'brightness':brightness,'visual_mood':mood,'palette':palette,'confidence':0.58}))
            return 0
    except Exception as e:
        print(json.dumps({'ok':False,'error':str(e)})); return 1
if __name__=='__main__': raise SystemExit(main())
