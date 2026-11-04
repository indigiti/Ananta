#!/usr/bin/env python3
import sys, json, os
from PIL import Image, ImageOps

def main():
    if len(sys.argv) < 3:
        print(json.dumps({'ok':False,'error':'usage: media_worker.py input output'})); return 2
    src,dst=sys.argv[1],sys.argv[2]
    try:
        with Image.open(src) as im:
            im.verify()
        with Image.open(src) as im:
            w,h=im.size
            if w>6000 or h>6000 or w*h>36000000:
                raise ValueError('Image dimensions exceed the 6000 px / 36 MP safety ceiling.')
            im=ImageOps.exif_transpose(im).convert('RGB')
            im.save(dst,'WEBP',quality=84,method=6)
        print(json.dumps({'ok':True,'width':w,'height':h,'output':os.path.basename(dst)})); return 0
    except Exception as e:
        print(json.dumps({'ok':False,'error':str(e)})); return 1
if __name__=='__main__': raise SystemExit(main())
