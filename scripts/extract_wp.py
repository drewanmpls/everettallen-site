#!/usr/bin/env python3
"""One-time migration: WordPress export XML -> content/galleries/*.json + scripts/copy-images.sh
Usage: python3 scripts/extract_wp.py export.xml"""
import sys, re, json, os
import xml.etree.ElementTree as ET
ns={'wp':'http://wordpress.org/export/1.2/','content':'http://purl.org/rss/1.0/modules/content/'}
root=ET.parse(sys.argv[1]).getroot().find('channel')
items=[]
for it in root.findall('item'):
    f=lambda t:(it.findtext(t,namespaces=ns) or '')
    items.append(dict(id=f('wp:post_id'),type=f('wp:post_type'),status=f('wp:status'),title=f('title'),slug=f('wp:post_name'),
        parent=f('wp:post_parent'),date=f('wp:post_date')[:10],content=f('content:encoded'),url=f('wp:attachment_url'),
        meta={m.findtext('wp:meta_key',namespaces=ns):(m.findtext('wp:meta_value',namespaces=ns) or '') for m in it.findall('wp:postmeta',ns)}))
byid={i['id']:i for i in items}
def php_ids(s): return re.findall(r'i:\d+;i:(\d+);',s)
def size(meta,name):
    m=re.search(r's:\d+:"%s";a:5:\{s:4:"file";s:\d+:"([^"]+)"'%name,meta); return m.group(1) if m else None
def dims(meta):
    w=re.search(r'"width";i:(\d+);s:6:"height";i:(\d+)',meta); return (int(w.group(1)),int(w.group(2))) if w else (0,0)
def desc(html):
    html=re.sub(r'<!--.*?-->','',html,flags=re.S)
    ps=[re.sub(r'<(?!/?(strong|em|a)\b)[^>]+>','',p).strip() for p in re.findall(r'<p>(.*?)</p>',html,flags=re.S)]
    return '\n\n'.join(p.replace('&nbsp;',' ') for p in ps if p)
cats={i['id']:i['slug'] for i in items if i['type']=='sunshine-gallery' and i['parent']=='0' and i['slug'] in('portraits','events')}
sh=['#!/bin/bash','# Run ON THE DREAMHOST SERVER from the new site\'s web directory (e.g. ~/new.everettallenphotography.com).','# Copies resized (watermarked) photos from the WordPress uploads folder into ./photos/<gallery>/.','set -e','UP="${1:-$HOME/everettallenphotography.com/wp-content/uploads}"','[ -d "$UP/sunshine" ] || { echo "Uploads folder not found: $UP (pass it as the first argument)"; exit 1; }','mkdir -p photos assets']
count=0
for g in sorted((i for i in items if i['type']=='sunshine-gallery' and i['status']=='publish' and i['parent'] in cats.values() or i['parent'] in cats),key=lambda x:x['date'],reverse=True):
    if g['parent'] not in cats: continue
    ids=php_ids(g['meta'].get('images',''))
    slug=g['slug']; imgs=[]; cover=0
    thumb_id=g['meta'].get('_thumbnail_id')
    sh.append(f'mkdir -p photos/{slug}')
    for n,aid in enumerate(ids,1):
        a=byid.get(aid)
        if not a: continue
        m=a['meta'].get('_wp_attachment_metadata','')
        d=os.path.dirname(a['meta'].get('_wp_attached_file',''))
        t,l=size(m,'sunshine-thumbnail'),size(m,'sunshine-large')
        if not(t and l): continue
        w,h=dims(m)
        if aid==thumb_id: cover=len(imgs)
        k=f'{len(imgs)+1:03d}'
        sh.append(f'cp "$UP/{d}/{t}" photos/{slug}/{k}-t.jpg; cp "$UP/{d}/{l}" photos/{slug}/{k}-l.jpg')
        imgs.append({'id':k,'ratio':round(w/h,4) if h else 1.5})
        count+=1
    out={'title':g['title'].strip(),'category':cats[g['parent']],'date':g['date'],'cover':imgs[cover]['id'] if imgs else '','description':desc(g['content']),'images':imgs}
    json.dump(out,open(f'content/galleries/{slug}.json','w'),indent=1,ensure_ascii=False)
hero=[i for i in items if i['type']=='attachment' and '/uploads/2025/03/' in i['url'] and not re.search('handj',i['url'])]
sh.append('mkdir -p photos/hero')
for n,a in enumerate(hero,1): sh.append(f'cp "$UP/{a["meta"]["_wp_attached_file"]}" photos/hero/{n:02d}.jpg')
for f,dst in [('EAP-horizontal-black-350x100-1.png','logo.png'),('eap_logo_horizontal_black@4x.png','logo@4x.png'),('EAP-icon.png','icon.png'),('EAP-horizontal-white-new.png','logo-white.png')]:
    sh.append(f'cp "$UP/2025/02/{f}" assets/{dst}')
sh.append('echo Done: $(ls photos | wc -l) folders')
open('scripts/copy-images.sh','w').write('\n'.join(sh)+'\n'); os.chmod('scripts/copy-images.sh',0o755)
print(count,'images;',len(hero),'hero images')
