# Security test report

**Date:** 2026-10-16  
**Target:** Source tree in `D:\AI`; no live target used.  
**Authorization boundary:** The included HTTP suite defaults to `127.0.0.1:8080` and refuses non-loopback hostnames. Do not point it at third parties.

## Executed checks

| Check | Command | Expected / observed | Result |
|---|---|---|---|
| Repository and source inspection | `rg --files`; inspect PHP, Python, Docker, Apache, JS | Reviewed all listed app source/config. No `.dockerignore` existed; created one. | PASS (source review) |
| PHP, Apache, Docker, local Pillow availability | `Get-Command php,python,docker`; inspect package/tool availability | Only host Python was available; PHP and Docker CLI were absent. | UNVERIFIED (runtime tools unavailable) |
| PHP syntax | `php -l ...` in built container | Not executable here: PHP/Docker unavailable. | UNVERIFIED |
| Python syntax | `python -m py_compile ml/detector.py tests/security_smoke.py` | Completed successfully. | PASS |
| Frontend untrusted text rendering | `Select-String` review of filename/error insertions in `assets/js/app.js` | Uploaded filename is assigned with `textContent`; no error-to-HTML insertion found. | PASS (static check) |
| Apache config | `apache2ctl configtest` inside image | Not executable here: Docker unavailable. | UNVERIFIED |
| Image build / service startup / DB / model / C2PA | See deployment commands | Not executable here: Docker unavailable and mounted resources not inspectable. | UNVERIFIED |

## Reproducible local HTTP smoke suite

From Git Bash (Pillow required in host Python; target must be your authorized local container):

```bash
python tests/security_smoke.py smoke
```

The suite has modes `smoke`, `extra-upload`, `rate-limit`, and `concurrency`. Run upload modes in separate one-minute rate windows (the production limiter intentionally counts invalid attempts); the concurrency mode can be run in its own window too. A valid image is considered accepted if inference completes (200) or the inference slot is busy (503); actual model output depends on the existing model and DB. The concurrency test initiates two valid requests and expects at least one busy response. Pillow is required in the test host. Tests are local only.

## Manual checks after build

```bash
curl -i http://127.0.0.1:8080/api/analyze.php
curl -i -X PUT http://127.0.0.1:8080/api/analyze.php
curl -i http://127.0.0.1:8080/includes/c2pa.php
curl -i http://127.0.0.1:8080/api/stats.php
curl -i -F image=@fixture.jpg http://127.0.0.1:8080/api/analyze.php
```

Expected: 405 for GET/PUT analyze; 403 for include; 200 JSON for stats; 200 JSON for valid analysis, or a clear 503/504/500 if busy/timeout/backend unavailable. Repeated analysis requests beyond five per minute per client return 429. The PHP suite does not itself prove all these runtime behaviors.

## Coverage status

| Requirement | Status |
|---|---|
| Valid JPEG/PNG/WebP, malformed, empty, disguised-script rejection | Test code added; live execution unverified |
| Oversized body and maximum dimensions | `extra-upload` test code added; live execution unverified |
| Traversal filename | Test code added; random server path and argv used; live execution unverified |
| SQLi/XSS | Prepared SQL; DOM updates use `textContent` for untrusted values. Dynamic probing not run |
| Internal files and unsupported methods | Test code added; Apache runtime unverified |
| Rate and concurrency limiting; deadlines | Implemented; stress/deadline tests not run |
| C2PA invalid/missing credentials | Status handling exists; runtime test images/tool unavailable |
| DB persistence/recreation | Not tested; do not recreate or delete existing volume for this check |
| Frontend regression | No browser available; static source inspection only |

Do not report any unverified item as passed in assignment results. Record actual status codes and logs from your local container when available.
