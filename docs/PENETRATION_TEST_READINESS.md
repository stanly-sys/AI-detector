# Penetration-test readiness

## Authorized scope

Approved target: the locally controlled AI Image Detector container, normally `http://127.0.0.1:8080`. A remote tester may connect only to a specific private interface and source-IP allowlist after written university/project authorization. No third-party hosts, public exposure, MySQL port, or Docker daemon are in scope. Use synthetic images and records; preserve the existing volumes and data.

## Exposed application surface

- `GET /`: PHP-rendered frontend and static assets
- `POST /api/analyze.php`: JPEG/PNG/WebP upload up to 2 MiB, 5 requests/minute/IP, one inference at a time
- `GET /api/stats.php`: aggregate-only statistics, 30 requests/minute/IP
- Internal `/includes/` paths are denied; no upload URL or record-level CRUD endpoint is provided.
- Host port default is `127.0.0.1:8080`; MySQL should have no host port mapping.

## Defensive controls

Apache hides detailed version, disables TRACE/indexing, blocks unsupported methods and internal files, applies slow-request and request-body limits, and adds MIME/frame/referrer/permissions headers. PHP caps uploads/input/execution/memory and hides errors. Uploads are format checked, dimension bounded, privately staged with random names and deleted after processing. Python checks and fully decodes image data, runs on CPU and has a deadline. Subprocesses use fixed argv and executable paths. PDO uses prepared statements; the stats API exposes no individual records. DOM output uses text nodes. Inference concurrency is serialized.

## Suggested authorized checks

Run `python tests/security_smoke.py` from the project directory. For deeper work use Burp Suite Community or OWASP ZAP in passive/manual mode against the loopback or explicitly approved private address; inspect request/response boundaries, then run low-rate malformed-file and method checks. Do not run aggressive scanners against a resource-constrained host without an approved rate and maintenance window. Monitor `docker stats`, `docker logs`, PID count, and disk usage during tests.

## Limitations to disclose

- No live integration tests were possible in the authoring environment: Docker and PHP CLIs were unavailable.
- Existing DB grants, persistent data, model mount, C2PA verification outcomes and Apache effective config need target-side confirmation.
- Per-IP rate counters are per-container and not proxy-aware; one global inference slot favors memory safety over throughput.
- Per-analysis model loading has substantial cold-start/resource cost. Persistent inference worker design was deferred because it introduces additional process supervision and IPC failure modes without performance measurements.
- PyTorch native memory use is not bounded by PHP `memory_limit`; use Docker resource limits and monitor.
- Dependency CVE scan was not possible. Ubuntu packages and Python ranges are not fully locked; C2PA binary checksum and trust-list pinning remain open supply-chain improvements.
- No CSP or HSTS is enabled. HSTS is inappropriate for this HTTP localhost setup. A compatible CSP needs a nonce/hash design for Google Fonts and JS-generated width styles plus browser verification.

This readiness note defines preparation and scope; it is not evidence that an external penetration test has been completed.
