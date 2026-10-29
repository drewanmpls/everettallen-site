#!/usr/bin/env python3
"""Static site builder (no dependencies). Usage: python3 build.py  -> writes ./dist"""
import json, os, shutil, html, re, glob
R=os.path.dirname(os.path.abspath(__file__)); os.chdir(R)
site=json.load(open('content/site.json'))
e=html.escape
cats={'portraits':'Portraits','events':'Events'}
G=[]
for f in glob.glob('content/galleries/*.json'):
    g=json.load(open(f)); g['slug']=os.path.basename(f)[:-5]; g.setdefault('year',g['date'][:4]); G.append(g)
G=[g for g in G if g['images']]
by_slug={g['slug']:g for g in G}
newest=lambda g:(g['year'],g['date'],g['slug'])
def esc_desc(t):
    t=e(t); t=re.sub(r'&lt;(/?)(strong|em)&gt;',r'<\1\2>',t)
    return ''.join(f'<p>{p}</p>' for p in t.split('\n\n') if p.strip())
SOC=f'<a href="{site["facebook"]}" target="_blank" rel="noopener">Facebook</a> / <a href="{site["instagram"]}" target="_blank" rel="noopener">Instagram</a>'
def page(title,body,path,section,desc=''):
    t=site['title'] if not title else f"{title} | {site['title']}"
    d=e(desc or site['tagline'])
    if section=='Portraits': nav='<a href="/events/">Events &rarr;</a><a href="/contact/">Contact</a>'
    elif section=='Events': nav='<a href="/">&larr; Portraits</a><a href="/contact/">Contact</a>'
    else: nav='<a href="/">Portraits</a><a href="/events/">Events</a><a href="/contact/">Contact</a>'
    return f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{e(t)}</title><meta name="description" content="{d}"><link rel="canonical" href="{site['base_url']}{path}">
<meta property="og:title" content="{e(t)}"><meta property="og:description" content="{d}"><meta property="og:type" content="website">
<link rel="icon" href="/assets/icon.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;800&family=Archivo+Narrow:wght@500&display=swap" rel="stylesheet"><link rel="stylesheet" href="/css/style.css"></head>
<body><a class="skip" href="#content">Skip to content</a>
<header class="top"><a class="brand" href="{'/events/' if section=='Events' else '/'}">EA / {section}</a><nav>{nav}</nav></header>
<main id="content">{body}</main>
<footer><span>Copyright 2026 Everett Allen Photography</span><span>{SOC}</span></footer>
<script src="/js/site.js" defer></script></body></html>'''
def w(path,content):
    p=os.path.join('dist',path.strip('/'),'index.html') if not path.endswith('.html') else os.path.join('dist',path.lstrip('/'))
    os.makedirs(os.path.dirname(p),exist_ok=True); open(p,'w').write(content)
def img(g,size): return f'/photos/{g["slug"]}/{g["cover"]}-{size}.jpg'
shutil.rmtree('dist',ignore_errors=True); shutil.copytree('static','dist')
portraits=[g for g in G if g['category']=='portraits']
events=sorted([g for g in G if g['category']=='events'],key=newest,reverse=True)
# ---- Home (portraits face)
feat=[by_slug[s] for s in site['featured_portraits'] if s in by_slug]
strip=''.join(f'<a class="fcard" href="/gallery/{g["slug"]}/"><img loading="lazy" src="{img(g,"l")}" alt=""><span>{e(g["title"])} / {g["year"]}</span></a>' for g in feat)
home=f'''<section class="homehead"><h1 class="giant">Everett<br>Allen<span class="photo">Photography</span></h1>
<div class="sub"><p class="lede">{e(site["tagline"])}</p><p class="meta">{len(portraits)} portrait sessions / {min(g["year"] for g in portraits)} &ndash; {max(g["year"] for g in portraits)} / Scroll &rarr;</p></div></section>
<div class="strip" tabindex="0" aria-label="Featured portraits">{strip}</div>
<p class="allp"><a href="/portraits/">All portraits A to Z</a></p>
<a class="band" href="/events/"><span class="bigword">Events</span><span class="bandtxt">Pride, TCLFW, contests and community nights, by year &rarr;</span></a>
<section class="cta"><span>Book a portrait session</span><a class="btn" href="/contact/">Contact me</a></section>'''
w('/',page('',home,'/','Portraits'))
# ---- Portraits A-Z
pa=''.join(f'<a class="card" href="/gallery/{g["slug"]}/"><img loading="lazy" src="{img(g,"t")}" alt=""><span><b>{e(g["title"])}</b><small>{g["year"]} / {len(g["images"])} photos</small></span></a>' for g in sorted(portraits,key=lambda g:g['title'].lower()))
w('/portraits/',page('Portraits',f'<div class="wrap"><h1 class="h1">Portraits</h1><p class="meta">A to Z</p><div class="cards">{pa}</div></div>','/portraits/','Portraits','Portrait galleries by Everett Allen Photography'))
# ---- Events front page
def tile(g,big=False):
    cls='tile big' if big else 'tile'
    badge=f'<em>New / {g["date"][:7]}</em>' if big else ''
    return f'<a class="{cls}" href="/gallery/{g["slug"]}/"><img loading="lazy" src="{img(g,"l")}" alt=""><span>{badge}<b>{e(g["title"])}</b></span></a>'
top=events[:3]
news=f'<div class="news">{tile(top[0],True)}<div class="side">{"".join(tile(g) for g in top[1:])}</div></div>' if len(top)==3 else ''
years=sorted({g['year'] for g in events},reverse=True)
ysec=''
for y in years:
    rows=''.join(f'<a class="row" href="/gallery/{g["slug"]}/"><span>{e(g["title"])}</span><small>{len(g["images"])} photos &rarr;</small></a>' for g in events if g['year']==y)
    ysec+=f'<section class="year" id="y{y}"><div class="ynum">{y}</div><div class="rows">{rows}</div></section>'
yj=''.join(f'<a href="#y{y}">{y}</a>' for y in years)
w('/events/',page('Events',f'<div class="evhead"><h1 class="giant sm">Events</h1><p class="meta">Community nights, contests and parades, newest first<br><span class="yj">{yj}</span></p></div>{news}<div class="years">{ysec}</div>','/events/','Events','Event photo galleries by Everett Allen Photography'))
# ---- Gallery pages
for g in G:
    s=g['slug']; sec=cats[g['category']]
    items=''.join(f'<a class="ph" href="/photos/{s}/{i["id"]}-l.jpg" style="--r:{i["ratio"]}" data-n="{k}"><img loading="lazy" src="/photos/{s}/{i["id"]}-t.jpg" alt="{e(g["title"])}, photo {k}" width="400" height="{round(400/i["ratio"])}"></a>' for k,i in enumerate(g['images'],1))
    d=esc_desc(g['description'])
    back=f'<a href="/{g["category"]}/">&larr; {sec}</a>'
    w(f'/gallery/{s}/',page(g['title'],f'<div class="wrap"><p class="meta">{back}</p><h1 class="h1">{e(g["title"])}</h1><p class="meta">{g["year"]} / {len(g["images"])} photos</p><div class="desc">{d}</div><div class="grid">{items}</div><p class="note">{e(site["usage_note"])} <a href="/contact/">Get in touch</a>.</p></div>',f'/{g["category"]}/',sec,re.sub('<[^>]+>','',d)[:160] or f'{g["title"]} by Everett Allen Photography'))
# ---- Contact etc.
w('/contact/',page('Contact',f'<div class="wrap narrow"><h1 class="h1">Let\'s connect</h1><p class="lede2">{e(site["contact_intro"])}</p><form class="contact" method="post" action="/contact/send.php"><div class="row2"><label>First name<input name="first" required autocomplete="given-name"></label><label>Last name<input name="last" autocomplete="family-name"></label></div><label>Email<input type="email" name="email" required autocomplete="email"></label><label>Message<textarea name="message" rows="6" required></textarea></label><input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="btn" type="submit">Send</button></form></div>','/contact/','Contact','Contact Everett Allen Photography'))
w('/thanks/',page('Thanks','<div class="wrap narrow"><h1 class="h1">Thanks!</h1><p class="lede2">Your message was sent. I\'ll get back to you soon.</p><p><a href="/">Back home</a></p></div>','/thanks/','Contact'))
w('/404.html',page('Not found','<div class="wrap narrow"><h1 class="h1">Page not found</h1><p class="lede2"><a href="/">Back home</a> or browse <a href="/portraits/">Portraits</a> and <a href="/events/">Events</a>.</p></div>','/404','Contact'))
urls=['/','/portraits/','/events/','/contact/']+[f'/gallery/{g["slug"]}/' for g in G]
open('dist/sitemap.xml','w').write('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'+''.join(f'<url><loc>{site["base_url"]}{u}</loc></url>' for u in urls)+'</urlset>')
open('dist/robots.txt','w').write(f'User-agent: *\nAllow: /\nSitemap: {site["base_url"]}/sitemap.xml\n')
ht=['ErrorDocument 404 /404.html','RewriteEngine On','RewriteCond %{HTTPS} off','RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]',
'RewriteRule ^client-galleries/gallery/(portraits|events)/?$ /$1/ [R=301,L]',
'RewriteRule ^client-galleries/gallery/(?:portraits|events)/([^/]+)/?$ /gallery/$1/ [R=301,L]',
'RewriteRule ^client-galleries/gallery/([^/]+)/?$ /gallery/$1/ [R=301,L]',
'RewriteRule ^(client-galleries|galleries|portrait-gallery|account|cart|checkout|favorites)(/.*)?$ /portraits/ [R=301,L]']
open('dist/.htaccess','w').write('\n'.join(ht)+'\n')
print('built',len(urls),'pages')
