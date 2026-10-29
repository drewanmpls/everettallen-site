#!/usr/bin/env python3
"""Static site builder (no dependencies). Usage: python3 build.py  -> writes ./dist"""
import json, os, shutil, html, re, glob
R=os.path.dirname(os.path.abspath(__file__)); os.chdir(R)
site=json.load(open('content/site.json'))
e=html.escape
cats={'portraits':'Portraits','events':'Events'}
G=[]
for f in glob.glob('content/galleries/*.json'):
    g=json.load(open(f)); g['slug']=os.path.basename(f)[:-5]; G.append(g)
G.sort(key=lambda g:g['date'],reverse=True)
def esc_desc(t):
    # allow only simple inline tags the migration kept
    t=e(t); t=re.sub(r'&lt;(/?)(strong|em)&gt;',r'<\1\2>',t)
    return ''.join(f'<p>{p}</p>' for p in t.split('\n\n') if p.strip())
def page(title,body,path,desc='',extra_head='',main_class=''):
    t=site['title'] if not title else f"{title} | {site['title']}"
    d=e(desc or site['tagline'])
    nav=''.join(f'<a href="{h}"{" aria-current=page" if h==path or (h!="/" and path.startswith(h)) else ""}>{n}</a>' for h,n in [('/','Home'),('/portraits/','Portraits'),('/events/','Events'),('/contact/','Contact')])
    return f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{e(t)}</title><meta name="description" content="{d}"><link rel="canonical" href="{site['base_url']}{path}">
<meta property="og:title" content="{e(t)}"><meta property="og:description" content="{d}"><meta property="og:type" content="website">
<link rel="icon" href="/assets/icon.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet"><link rel="stylesheet" href="/css/style.css">{extra_head}</head>
<body><a class="skip" href="#content">Skip to content</a>
<header class="top"><a class="brand" href="/"><img src="/assets/logo.png" srcset="/assets/logo.png 1x, /assets/logo@4x.png 2x" alt="Everett Allen Photography" width="175" height="50"></a>
<button class="menu-btn" aria-label="Menu" aria-expanded="false" aria-controls="nav"><span></span><span></span><span></span></button><nav id="nav">{nav}</nav></header>
<main id="content" class="{main_class}">{body}</main>
<footer><nav class="fnav">{nav}</nav><p>Copyright &copy;2026 Everett Allen Photography. All rights reserved.</p>
<p class="social"><a href="{site['facebook']}" aria-label="Facebook" target="_blank" rel="noopener"><svg viewBox="0 0 320 512"><path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"/></svg></a>
<a href="{site['instagram']}" aria-label="Instagram" target="_blank" rel="noopener"><svg viewBox="0 0 448 512"><path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"/></svg></a></p></footer>
<script src="/js/site.js" defer></script></body></html>'''
def card(g):
    return f'<a class="card" href="/gallery/{g["slug"]}/"><img loading="lazy" src="/photos/{g["slug"]}/{g["cover"]}-t.jpg" alt=""><span><b>{e(g["title"])}</b><small>{len(g["images"])} photos</small></span></a>'
def w(path,content):
    p=os.path.join('dist',path.strip('/'),'index.html') if not path.endswith('.html') else os.path.join('dist',path.lstrip('/'))
    os.makedirs(os.path.dirname(p),exist_ok=True); open(p,'w').write(content)
shutil.rmtree('dist',ignore_errors=True); shutil.copytree('static','dist')
# home
n=site['hero_count']
slides=''.join(f'<div class="slide{" on" if i==1 else ""}" style="background-image:url(/photos/hero/{i:02d}.jpg)"></div>' for i in range(1,n+1))
recent=''.join(card(g) for g in G[:6] if g['images'])
w('/',page('',f'<section class="hero"><div class="slides">{slides}</div><div class="hero-in"><h1>{e(site["tagline"])}</h1><a class="btn" href="/contact/">Contact Me</a></div></section><section class="wrap"><h2>Recent galleries</h2><div class="cards">{recent}</div><p class="more"><a href="/portraits/">Portraits</a> &middot; <a href="/events/">Events</a></p></section>','/'))
for c,name in cats.items():
    gs=[g for g in G if g['category']==c and g['images']]
    w(f'/{c}/',page(name,f'<div class="wrap"><h1>{name}</h1><div class="cards">{"".join(card(g) for g in gs)}</div></div>',f'/{c}/',f'{name} galleries by Everett Allen Photography'))
for g in G:
    if not g['images']: continue
    s=g['slug']
    items=''.join(f'<a class="ph" href="/photos/{s}/{i["id"]}-l.jpg" style="--r:{i["ratio"]}" data-n="{k}"><img loading="lazy" src="/photos/{s}/{i["id"]}-t.jpg" alt="{e(g["title"])}, photo {k}" width="400" height="{round(400/i["ratio"])}"></a>' for k,i in enumerate(g['images'],1))
    d=esc_desc(g['description'])
    w(f'/gallery/{s}/',page(g['title'],f'<div class="wrap"><p class="crumb"><a href="/{g["category"]}/">&larr; {cats[g["category"]]}</a></p><h1>{e(g["title"])}</h1><div class="desc">{d}</div><div class="grid">{items}</div><p class="note">{e(site["usage_note"])} <a href="/contact/">Get in touch</a>.</p></div>',f'/{g["category"]}/',re.sub('<[^>]+>','',d)[:160] or f'{g["title"]} by Everett Allen Photography'))
w('/contact/',page('Contact',f'<div class="wrap narrow"><h1>Let\'s Connect!</h1><p>{e(site["contact_intro"])}</p><form class="contact" method="post" action="/contact/send.php"><div class="row"><label>First name<input name="first" required autocomplete="given-name"></label><label>Last name<input name="last" autocomplete="family-name"></label></div><label>Email<input type="email" name="email" required autocomplete="email"></label><label>Message<textarea name="message" rows="6" required></textarea></label><input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="btn" type="submit">Send</button></form></div>','/contact/','Contact Everett Allen Photography'))
w('/thanks/',page('Thanks','<div class="wrap narrow"><h1>Thanks!</h1><p>Your message was sent. I\'ll get back to you soon.</p><p><a href="/">Back home</a></p></div>','/thanks/'))
w('/404.html',page('Not found','<div class="wrap narrow"><h1>Page not found</h1><p><a href="/">Back home</a> or browse <a href="/portraits/">Portraits</a> and <a href="/events/">Events</a>.</p></div>','/404'))
urls=['/','/portraits/','/events/','/contact/']+[f'/gallery/{g["slug"]}/' for g in G if g['images']]
open('dist/sitemap.xml','w').write('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'+''.join(f'<url><loc>{site["base_url"]}{u}</loc></url>' for u in urls)+'</urlset>')
open('dist/robots.txt','w').write(f'User-agent: *\nAllow: /\nSitemap: {site["base_url"]}/sitemap.xml\n')
# .htaccess: old WordPress URLs -> new
ht=['ErrorDocument 404 /404.html','RewriteEngine On','RewriteCond %{HTTPS} off','RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]',
'RewriteRule ^client-galleries/gallery/(portraits|events)/?$ /$1/ [R=301,L]',
'RewriteRule ^client-galleries/gallery/(?:portraits|events)/([^/]+)/?$ /gallery/$1/ [R=301,L]',
'RewriteRule ^client-galleries/gallery/([^/]+)/?$ /gallery/$1/ [R=301,L]',
'RewriteRule ^(client-galleries|galleries|portrait-gallery|account|cart|checkout|favorites)(/.*)?$ /portraits/ [R=301,L]']
open('dist/.htaccess','w').write('\n'.join(ht)+'\n')
print('built',len(urls),'pages')
