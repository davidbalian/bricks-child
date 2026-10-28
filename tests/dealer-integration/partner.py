#!/usr/bin/env python3
"""Compatibility entry point for the local administrator workspace. No secrets or sessions."""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

class Handler(BaseHTTPRequestHandler):
    def log_message(self, *args): pass
    def do_GET(self):
        self.send_response(303)
        self.send_header('Location', 'http://127.0.0.1:8899/?autoagora_connect=workspace')
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Robots-Tag', 'noindex, nofollow')
        self.end_headers()
    def do_POST(self):
        self.send_response(410)
        self.send_header('Cache-Control', 'no-store')
        self.end_headers()
        self.wfile.write(b'The old demonstration has been retired. Use the administrator workspace.')

if __name__ == '__main__':
    ThreadingHTTPServer(('127.0.0.1', 8898), Handler).serve_forever()
