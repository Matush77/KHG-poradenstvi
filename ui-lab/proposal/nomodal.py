"""The booking pop-up is gone (15 Nov, as on redesign-ship): "Objednat se" buttons link straight to the
booking page, where online or in person is chosen. build.py runs every lab page through page()."""
import re

BOOKING = 'ui-lab/rezervace-ukazka.html'   # the lab's booking page (on the site: /rezervace.html)
LINK = {'openBookingModal': BOOKING, 'bookOnline': BOOKING, 'bookInPerson': BOOKING + '?forma=osobne'}
SEL = re.compile(r'booking-modal|booking-option|bookingModal')


def fix_links(s):
    def tag(m):
        t = m.group(0)
        f = re.search(r'onclick="(openBookingModal|bookOnline|bookInPerson)\(\); return false;"', t)
        if not f:
            return t
        t = re.sub(r'\s+onclick="(openBookingModal|bookOnline|bookInPerson)\(\); return false;"', '', t)
        return t.replace('href="#"', 'href="%s"' % LINK[f.group(1)], 1)
    return re.sub(r'<a\b[^>]*>', tag, s)


def drop_modal(s):
    m = re.search(r'<div\b[^>]*\bid="bookingModal"[^>]*>', s)
    if not m:
        return s
    depth, i = 0, m.start()
    for t in re.finditer(r'<div\b[^>]*>|</div>', s[m.start():]):
        depth += 1 if t.group(0) != '</div>' else -1
        if depth == 0:
            end = m.start() + t.end()
            break
    start = m.start()
    # the generator comment line above it and the indent before it
    before = s[:start]
    c = re.search(r'\n[ \t]*<!--[^\n]*(?:booking-modal\.html|Booking Modal)[^\n]*-->[ \t]*\n[ \t]*$', before)
    start = c.start() if c else before.rstrip(' \t').rfind('\n')
    tail = re.match(r'[ \t]*\n', s[end:])
    end += tail.end() - 1 if tail else 0
    return s[:start] + s[end:]


def strip_css(css):
    """Drops selectors that name the pop-up; a rule left with none goes, an at-block left empty goes."""
    out, i, n = [], 0, len(css)
    while i < n:
        j = css.find('{', i)
        if j < 0:
            out.append(css[i:]); break
        # prelude: from i to j, but keep comments/whitespace before the selector
        k = css.find('}', i)
        if 0 <= k < j:   # closing brace of an enclosing block
            out.append(css[i:k + 1]); i = k + 1; continue
        prelude = css[i:j]
        # find matching close brace
        d, e = 0, j
        while e < n:
            if css[e] == '{': d += 1
            elif css[e] == '}':
                d -= 1
                if d == 0: break
            e += 1
        body = css[j + 1:e]
        lead = re.match(r'(\s*(?:/\*.*?\*/\s*)*)', prelude, re.S).group(1)
        sel = prelude[len(lead):]
        if sel.lstrip().startswith('@'):
            if '{' in body and not sel.lstrip().startswith('@font-face') and not sel.lstrip().startswith('@keyframes'):
                inner = strip_css(body)
                if inner.strip():
                    out.append(prelude + '{' + inner + '}')
                else:
                    out.append(lead.rstrip(' \t') if '\n' not in lead else lead[:lead.rfind('\n')])
            else:
                out.append(css[i:e + 1])
        else:
            # a pop-up class inside :is(...) only leaves that list
            sel2 = re.sub(r',\s*\.booking-modal-title(?=[^()]*\))', '', sel)
            parts = re.split(r',(?![^()]*\))', sel2)
            keep = [p for p in parts if not SEL.search(p)]
            if sel2 == sel and len(keep) == len(parts):
                out.append(css[i:e + 1])
            elif keep:
                first = re.match(r'\s*', parts[0]).group(0)
                keep[0] = first + keep[0].lstrip()
                tail = re.search(r'\s*$', sel).group(0)
                out.append(lead + ','.join(keep).rstrip() + tail + '{' + body + '}')
            else:
                # drop the rule and its line if it sat alone on one
                out.append(lead[:lead.rfind('\n')].rstrip(' \t') if '\n' in lead else lead.rstrip(' \t'))
        i = e + 1
    return ''.join(out)


def fix_styles(s):
    return re.sub(r'(<style\b[^>]*>)(.*?)(</style>)', lambda m: m.group(1) + strip_css(m.group(2)) + m.group(3), s, flags=re.S)




def page(html):
    return fix_styles(drop_modal(fix_links(html)))
