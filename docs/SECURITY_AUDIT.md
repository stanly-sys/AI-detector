# Security audit

**Scope and method.** Source review of the repository as supplied, followed by source-level validation. No running PHP/Apache/MySQL/Docker environment was available during this review, so runtime claims are marked unverified. The application is a single Ubuntu container. Findings below distinguish the state observed before changes from residual risk.

## Findings and remediations

| Severity | Area / file | Finding and treatment |
|---|---|---|
| High | `api/analyze.php` | PHP drained subprocess pipes synchronously with no effective PHP-side deadline; concurrent requests could spawn unbounded Python/model loads. Added per-IP 5/minute fixed-window limiting, one nonblocking inference lock (503 when busy), bounded output, 120 second `timeout` plus kill-after, and guaranteed ordinary-path cleanup. |
| High | `ml/detector.py`, upload path | No explicit image dimension/pixel limit or full decode validation; model accepted the provided path. Added JPEG/PNG/WebP structure checks, maximum side 10,000 and 40 MP, Pillow verify and full load, path allow-list, and disabled truncated-image acceptance. |
| Medium | `api/analyze.php` | Existing MIME and `getimagesize` checks lacked limits on decoded dimensions, and temp path generation was predictable in placement though random. Added exact MIME/format matching, byte-size and dimensions, random 160-bit name in private `/tmp/ai-detector-uploads` (0700), and failure cleanup. |
| Medium | `apache-security.conf`, `php-security.ini`, `api/request-too-large.php` | Apache already disabled indexes/TRACE, hid version, restricted includes, applied slow-request timeout and body cap. PHP upload/execution/error limits were absent. Added PHP 2 MiB/2300 KiB limits, hidden errors, upload count, execution/input deadlines, additional file patterns/method restrictions, and a JSON 413 response for Apache body-limit rejections. No HSTS added for HTTP localhost. |
| Medium | Docker runtime | Apache prefork defaults could permit many PHP workers, while inference is memory intensive. Capped prefork workers at 20 and documented Docker PID/CPU/memory/log controls. No Compose or second service introduced. |
| Medium | `README.md`, `assets/js/app.js` | Documentation said no backend existed and UI allowed 10 MiB despite active API and 2 MiB backend. Corrected README and browser-side size check. Removed duplicate C2PA display call. Untrusted filenames/errors use `textContent`; confidence bar markup is constant template text. |
| Low | `api/stats.php`, frontend | Public aggregate endpoint could be repeatedly queried. Added 30/minute per-IP limit and 15-second client refresh throttle. It exposes only aggregate totals and mean score, not per-image records. |
| Low | `Dockerfile`, `.dockerignore` | Build context exclusions were absent and C2PA tool/trust data came from external upstream downloads. Added `.dockerignore`; tool version is fixed at 0.28.1. Binary checksum/signature verification and trust-list commit pinning remain advisable. |

## Controls observed and retained

- SQL writes use PDO prepared statements, and DB access uses the local MySQL socket as `www-data` with no password. This is appropriately narrow only if the pre-existing MySQL grant is restricted to the required table operations; that grant could not be inspected here.
- No uploaded image is stored in the database or exposed from the web root. Python and C2PA use argv arrays and fixed executable paths, with a server-generated image path.
- `includes/` is denied in Apache; stats returns aggregate-only data. There are no user-selectable record IDs or update/delete endpoints.
- SynthID is labeled unconfigured/not checked. C2PA origins use `*_declared` labels; this is a declaration, not proof of AI generation.

## Residual risks

1. **High, deployment dependent:** Runtime database users/grants, socket ownership, existing schema and the mounted model’s permissions have not been verified. Inspect before exposure; do not reset existing data.
2. **High, model/runtime dependent:** Each request loads PyTorch and Transformers anew. A single lock prevents parallel loads, but cold starts can be slow and memory-heavy. A persistent worker can improve latency but adds IPC, health-check, restart, privilege, and queue complexity; it was not introduced without runtime measurements.
3. **Medium:** Rate limits are process-local filesystem counters and are not shared across multiple containers/hosts; the intended deployment is one container. IP-based limits can be shared by NAT users and spoofing protection depends on Apache's direct client connection (no trusted proxy configuration).
4. **Medium:** PHP `memory_limit` does not constrain native PyTorch memory. Apply Docker memory/PID/CPU limits and monitor usage.
5. **Medium:** `c2patool` trust anchors track the upstream `main` branch at image-build time, and the downloaded release is not checksum-verified in this repository.
6. **Medium:** No CSP is applied. The page uses Google Fonts and JS-created inline style widths; a restrictive CSP needs a nonce/hash refactor and browser regression review. Other security headers are applied.
7. **Low:** Apache/PHP/Python OS and library packages are not all pinned. Ubuntu security updates should be applied and images rebuilt regularly; exact current CVE status was not scannable in this environment.
8. **Low:** Default security logs go to container stderr/error log and Docker's logging driver. Configure bounded rotation as shown in deployment docs.

This is a source hardening review, not a certification or penetration-test result.
