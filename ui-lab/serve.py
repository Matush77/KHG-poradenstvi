#!/usr/bin/env python3
"""UI lab server: serves the site and injects the polish layer + lab toolbar.

Run from anywhere:  python3 ui-lab/serve.py   (port 8800, all interfaces)
?lab=original  -> page served untouched (used by the ghost overlay iframe)
Nothing in ui-lab/ is committed or deployed (listed in .git/info/exclude).
"""
import http.server, os, socketserver, sys, urllib.parse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8800
INJECT = ('\n<!-- ui-lab -->\n<link rel="stylesheet" id="lab-polish" href="/ui-lab/polish.css">\n'
          '<script src="/ui-lab/polish.js" defer></script>\n'
          '<script src="/ui-lab/lab.js" defer></script>\n')


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *a, **kw):
        super().__init__(*a, directory=ROOT, **kw)

    def end_headers(self):
        self.send_header("Cache-Control", "no-store")
        super().end_headers()

    def do_GET(self):
        url = urllib.parse.urlsplit(self.path)
        path = url.path
        if path.endswith("/"):
            path += "index.html"
        q = urllib.parse.parse_qs(url.query)
        fs = os.path.join(ROOT, path.lstrip("/"))
        if path.endswith(".html") and os.path.isfile(fs) and q.get("lab") != ["original"] \
                and not path.startswith("/ui-lab/"):
            with open(fs, "r", encoding="utf-8") as f:
                html = f.read()
            i = html.lower().rfind("</body>")
            html = html[:i] + INJECT + html[i:] if i != -1 else html + INJECT
            body = html.encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return
        super().do_GET()

    def log_message(self, *a):
        pass


class Server(socketserver.ThreadingMixIn, http.server.HTTPServer):
    daemon_threads = True
    allow_reuse_address = True


if __name__ == "__main__":
    print(f"UI lab on http://0.0.0.0:{PORT}", flush=True)
    Server(("0.0.0.0", PORT), Handler).serve_forever()
