import re, html
DAY = r'(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)'
MON = r'(?:January|February|March|April|May|June|July|August|September|October|November|December)'
HEAD = re.compile(r'\s*Match Appointments\s*[-–|:]\s*(' + DAY + r'\s+\d{1,2}(?:st|nd|rd|th)?\s+' + MON + r')\s*', re.I)
ROLE = re.compile(r'^(.+? - (?:Referee|Assistant Referee|Fourth Official|4th Official|Assessor|Observer|Reserve Official))\s+(\S.+)$')
SPLIT = re.compile(r"(?<=\S) (?=[A-Z][\w'’.-]+ [A-Z][\w'’.-]+ - (?:Referee|Assistant Referee|Fourth Official|4th Official)\b)")
LUCK = re.compile(r'\s*(Best of luck[^\n]*)', re.I)

def esc(s): return html.escape(s.strip(), quote=False)

def to_html(text):
    t = text.replace('\r', '')
    t = HEAD.sub(lambda m: '\n\n@@H ' + m.group(1) + '\n', t)
    t = LUCK.sub(lambda m: '\n\n' + m.group(1), t)
    out = []
    for block in re.split(r'\n\s*\n', t):
        lines = [l.strip() for l in block.split('\n') if l.strip()]
        if not lines: continue
        if lines[0].startswith('@@H '):
            out.append('<h2>Match appointments: ' + esc(lines[0][4:]) + '</h2>')
            lines = lines[1:]
            if not lines: continue
        # Old posts run every appointment together on one line: split before each "First Last - Role".
        entries, cur = [], []
        for l in lines:
            parts = SPLIT.split(l) if len(l) > 140 and ' vs ' in l else [l]
            if len(parts) > 1:
                if cur: entries.append(cur); cur = []
                for part in parts: entries.append([part])
            else:
                cur.append(l)
        if cur: entries.append(cur)
        for e in entries:
            fixed = []
            for l in e:
                m = ROLE.match(l)
                if m and ' vs ' in m.group(2): fixed += [m.group(1)] + m.group(2).split(' | ')
                else: fixed += l.split(' | ') if ' vs ' in l else [l]
            out.append('<p>' + '<br>'.join(esc(l) for l in fixed) + '</p>')
    return '\n'.join(out)
