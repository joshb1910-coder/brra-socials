"""Convert Wix Ricos rich content (JSON nodes) to simple HTML."""
import html

WIX = 'https://static.wixstatic.com/media/'

def esc(s): return html.escape(s, quote=False)

def text(node):
    t = esc(node['textData']['text']).replace('\n', '<br>')
    for d in node['textData'].get('decorations', []):
        k = d['type']
        if k == 'BOLD' and d.get('fontWeightValue', 700) >= 600: t = f'<strong>{t}</strong>'
        elif k == 'ITALIC' and d.get('italicData', True) is not False: t = f'<em>{t}</em>'
        elif k == 'UNDERLINE': pass
        elif k == 'LINK':
            url = (d.get('linkData', {}).get('link', {}) or {}).get('url')
            if url: t = f'<a href="{html.escape(url)}">{t}</a>'
    return t

def kids(n): return ''.join(conv(c) for c in n.get('nodes', []))

def img_url(src):
    if not src: return ''
    if src.get('url'): return src['url']
    i = src.get('id', '')
    return i if i.startswith('http') else (WIX + i if i else '')

def conv(n):
    t = n['type']
    if t == 'TEXT': return text(n)
    if t == 'PARAGRAPH':
        inner = kids(n)
        stripped = inner.replace('<br>', '').strip()
        return f'<p>{inner}</p>\n' if stripped else ''
    if t == 'HEADING':
        lvl = min(max(n.get('headingData', {}).get('level', 2), 2), 4)
        inner = kids(n)
        return f'<h{lvl}>{inner}</h{lvl}>\n' if inner.strip() else ''
    if t == 'BULLETED_LIST': return f'<ul>{kids(n)}</ul>\n'
    if t == 'ORDERED_LIST': return f'<ol>{kids(n)}</ol>\n'
    if t == 'LIST_ITEM':
        inner = kids(n).replace('<p>', '').replace('</p>\n', ' ').strip()
        return f'<li>{inner}</li>'
    if t == 'BLOCKQUOTE': return f'<blockquote>{kids(n)}</blockquote>\n'
    if t == 'DIVIDER': return '<hr>\n'
    if t == 'IMAGE':
        d = n.get('imageData', {})
        u = img_url(d.get('image', {}).get('src'))
        alt = esc(d.get('altText', '') or '')
        cap = d.get('caption') or ''
        cap_html = ''.join(kids(c) for c in n.get('nodes', []) if c['type'] == 'CAPTION').strip()
        if not u: return ''
        if cap_html:
            return f'<figure class="wp-block-image"><img src="{u}" alt="{alt}"><figcaption>{cap_html}</figcaption></figure>\n'
        fig = f'<figure class="wp-block-image"><img src="{u}" alt="{alt}">'
        if cap: fig += f'<figcaption>{esc(cap)}</figcaption>'
        return fig + '</figure>\n'
    if t == 'GALLERY':
        out = ''
        for it in n.get('galleryData', {}).get('items', []):
            u = img_url(it.get('image', {}).get('media', {}).get('src'))
            if u: out += f'<figure class="wp-block-image"><img src="{u}" alt=""></figure>\n'
        return out
    if t == 'VIDEO':
        src = n.get('videoData', {}).get('video', {}).get('src', {})
        u = src.get('url') or ''
        if u: return f'\n{u}\n\n'  # WordPress turns a URL on its own line into an embedded video.
        if src.get('id'):
            return f'<figure class="wp-block-video"><video controls preload="metadata" src="https://video.wixstatic.com/{src["id"]}"></video></figure>\n'
        return ''
    if t in ('LINK_PREVIEW',):
        u = n.get('linkPreviewData', {}).get('link', {}).get('url', '')
        ttl = n.get('linkPreviewData', {}).get('title', '') or u
        return f'<p><a href="{html.escape(u)}">{esc(ttl)}</a></p>\n' if u else ''
    if t == 'EMBED':
        u = n.get('embedData', {}).get('src', '')
        return f'<p><a href="{html.escape(u)}">{esc(u)}</a></p>\n' if u else ''
    if t == 'BUTTON':
        bd = n.get('buttonData', {})
        u = (bd.get('link') or {}).get('url', '')
        label = esc(bd.get('text', 'Find out more'))
        return f'<p><a class="brra-btn" href="{html.escape(u)}">{label}</a></p>\n' if u else ''
    if t == 'TABLE':
        rows = ''
        for r in n.get('nodes', []):
            cells = ''.join('<td>' + kids(c).replace('<p>', '').replace('</p>\n', '<br>') + '</td>' for c in r.get('nodes', []))
            rows += f'<tr>{cells}</tr>'
        return f'<figure class="wp-block-table"><table>{rows}</table></figure>\n'
    if t == 'HTML':
        # Keep simple custom HTML; scripts are removed when WordPress saves the post.
        return '<div class="brra-embed">' + n.get('htmlData', {}).get('html', '') + '</div>\n'
    if t == 'CAPTION':
        return ''
    if t == 'COLLAPSIBLE_LIST':
        return kids(n)
    if t == 'COLLAPSIBLE_ITEM':
        title = ''.join(kids(c) for c in n.get('nodes', []) if c['type'] == 'COLLAPSIBLE_ITEM_TITLE')
        body = ''.join(kids(c) for c in n.get('nodes', []) if c['type'] == 'COLLAPSIBLE_ITEM_BODY')
        title = title.replace('<p>', '').replace('</p>\n', ' ').strip()
        return f'<details class="wp-block-details" open><summary>{title}</summary>{body}</details>\n'
    return kids(n)

def to_html(rich):
    return ''.join(conv(n) for n in rich.get('nodes', []))
