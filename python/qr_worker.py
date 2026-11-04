#!/usr/bin/env python3
import sys, qrcode
if len(sys.argv)<3: raise SystemExit(2)
img=qrcode.make(sys.argv[1]); img.save(sys.argv[2])
