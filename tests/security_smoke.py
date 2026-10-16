"""Authorized local HTTP checks. Requires Pillow; target is loopback by default."""
import io
import os
import sys
import concurrent.futures
import urllib.error
import urllib.request
from urllib.parse import urlparse

BASE = os.environ.get("AI_DETECTOR_URL", "http://127.0.0.1:8080").rstrip("/")
parsed = urlparse(BASE)
if parsed.hostname not in {"127.0.0.1", "localhost", "::1"}:
    raise SystemExit("Refusing non-loopback target; use only an explicitly authorized local deployment.")

try:
    from PIL import Image
except ImportError as exc:
    raise SystemExit("Install Pillow in the test environment to generate image fixtures") from exc

def request(path, method="GET", body=None, headers=None):
    req = urllib.request.Request(BASE + path, data=body, method=method, headers=headers or {})
    try:
        with urllib.request.urlopen(req, timeout=180) as response:
            return response.status, response.read()
    except urllib.error.HTTPError as exc:
        return exc.code, exc.read()

def multipart(content, filename="test.jpg", mime="image/jpeg"):
    boundary = "----security-smoke-boundary"
    body = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"image\"; filename=\"{filename}\"\r\nContent-Type: {mime}\r\n\r\n".encode() + content + f"\r\n--{boundary}--\r\n".encode())
    return body, {"Content-Type": f"multipart/form-data; boundary={boundary}"}

def encoded_image(fmt):
    stream = io.BytesIO()
    Image.new("RGB", (2, 2), "white").save(stream, format=fmt)
    return stream.getvalue()

checks = []
def check(name, expected, actual):
    passed = actual == expected
    checks.append((name, expected, actual, passed))
    print(f"{'PASS' if passed else 'FAIL'} | {name} | expected={expected} actual={actual}")

mode = sys.argv[1] if len(sys.argv) > 1 else "smoke"

if mode == "rate-limit":
    body, headers = multipart(b"invalid", "bad.jpg")
    codes = [request("/api/analyze.php", "POST", body, headers)[0] for _ in range(6)]
    check("five per-minute invalid attempts rejected by validation", True, all(c == 400 for c in codes[:5]))
    check("sixth request rate limited", 429, codes[5])
elif mode == "concurrency":
    body, headers = multipart(encoded_image("PNG"), "concurrent.png", "image/png")
    def submit(_):
        return request("/api/analyze.php", "POST", body, headers)[0]
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        codes = list(pool.map(submit, range(2)))
    check("concurrent expensive request rejected as busy", True, 503 in codes)
    check("other request completes or times out safely", True, all(c in (200, 503, 504, 500) for c in codes))
elif mode == "extra-upload":
    for name, data in [("empty image", b""), ("script disguised as image", b"<?php echo 1; ?>")]:
        body, headers = multipart(data, "probe.php.jpg")
        check(name + " rejected", 400, request("/api/analyze.php", "POST", body, headers)[0])
    body, headers = multipart(b"A" * (2 * 1024 * 1024 + 100), "large.jpg")
    check("oversized upload rejected", True, request("/api/analyze.php", "POST", body, headers)[0] in (413, 400))
    stream = io.BytesIO()
    Image.new("RGB", (10001, 1), "white").save(stream, format="PNG")
    body, headers = multipart(stream.getvalue(), "wide.png", "image/png")
    check("excessive image dimension rejected", 400, request("/api/analyze.php", "POST", body, headers)[0])
elif mode != "smoke":
    raise SystemExit("Usage: security_smoke.py [smoke|extra-upload|rate-limit|concurrency]")
else:
  for fmt, mime, ext in [("JPEG", "image/jpeg", "jpg"), ("PNG", "image/png", "png"), ("WEBP", "image/webp", "webp")]:
      body, headers = multipart(encoded_image(fmt), f"fixture.{ext}", mime)
      status, _ = request("/api/analyze.php", "POST", body, headers)
      # 503 is a valid protective response if the single inference slot is busy.
      check(f"valid {fmt} reaches analysis backend", True, status in (200, 503))

  for name, data, mime in [("corrupt payload", b"not an image", "image/jpeg"), ("PHP disguised as JPEG", b"<?php echo 1; ?>", "image/jpeg")]:
      body, headers = multipart(data, "../../probe.php", mime)
      status, _ = request("/api/analyze.php", "POST", body, headers)
      check(name + " rejected", 400, status)

  check("GET analysis rejected", 405, request("/api/analyze.php")[0])
  check("PUT analysis rejected", 405, request("/api/analyze.php", "PUT")[0])
  check("cross-origin analysis rejected", 403, request("/api/analyze.php", "POST", b"", {"Origin": "https://attacker.example"})[0])
  check("internal C2PA include denied", 403, request("/includes/c2pa.php")[0])
  check("Dockerfile not served", 404, request("/Dockerfile")[0])
  check("database aggregate endpoint works", 200, request("/api/stats.php")[0])
  # These endpoints accept no query parameters; payload is ignored and cannot select a record.
  check("SQL injection shaped query remains aggregate endpoint", 200, request("/api/stats.php?id=1%20OR%201=1")[0])
  # Untrusted filenames and API errors are rendered through textContent in the browser code.
  app_js = open(os.path.join(os.path.dirname(__file__), "..", "assets", "js", "app.js"), encoding="utf-8").read()
  check("frontend uses textContent for uploaded filename", True, '$("uploadTitle").textContent = file.name' in app_js)
  check("frontend avoids API error HTML insertion", True, 'message(error.message, "error")' in app_js and "innerHTML = error" not in app_js)

print(f"Summary: {sum(row[3] for row in checks)}/{len(checks)} passed")
sys.exit(0 if all(row[3] for row in checks) else 1)
