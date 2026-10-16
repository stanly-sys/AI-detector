# Secure deployment

Keep the existing one-container Ubuntu architecture and preserve existing volumes. Build and inspect a replacement image before recreating or replacing a running container.

## Build and validate

From Git Bash at the project root:

```bash
docker build -t ai-detector-ubuntu:hardened .
docker run --rm ai-detector-ubuntu:hardened apache2ctl configtest
```

The image expects the existing model files at `/opt/models/sdxl-detector`, and the existing database files at `/var/lib/mysql`. Do not delete either named volume. Inspect actual volume names with `docker volume ls` before starting.

## Local-only run

```bash
MSYS_NO_PATHCONV=1 docker run -d --name ai-detector-web-hardened \
  --restart unless-stopped \
  --security-opt no-new-privileges:true \
  --cap-drop=NET_RAW --cap-drop=SYS_ADMIN --cap-drop=SYS_PTRACE \
  --pids-limit 256 --memory 5g --cpus 2 \
  --log-driver json-file --log-opt max-size=10m --log-opt max-file=3 \
  --mount source=ai-detector-mysql-data,target=/var/lib/mysql \
  --mount source=ai-detector-models,target=/opt/models/sdxl-detector,readonly \
  -p 127.0.0.1:8080:80 \
  ai-detector-ubuntu:hardened
```

This creates a parallel container and preserves the named volumes; do not start it simultaneously with the existing MySQL container against the same database volume. Verify backups and stop the existing container before reusing its volume. A 5 GiB memory limit is a conservative starting point, not a guarantee that the chosen model fits; adjust to measured host capacity. Do not add `--privileged`. Validate capabilities and limits with the actual MySQL/PHP image before replacing the existing container.

For local verification:

```bash
docker ps --filter name=ai-detector-web-hardened
docker logs --tail 100 ai-detector-web-hardened
docker exec ai-detector-web-hardened apache2ctl configtest
docker exec ai-detector-web-hardened mysqladmin --protocol=socket ping
curl -i http://127.0.0.1:8080/api/stats.php
```

Check restricted DB grants with a MySQL administrator account, not the web account. Confirm the `www-data` DB user can perform only the required select/insert on `analysis_results`. The project does not contain schema/bootstrap SQL, so do not initialize or alter an existing database based on assumptions.

## Authorized remote test network

Only after approval and network isolation, map the port to a specific private interface address (not `0.0.0.0`), use a host firewall allowlist for the tester's source IP, and restrict the test window. Example: `-p 192.168.50.10:8080:80`. The app is HTTP-only; HSTS is intentionally absent. Do not expose MySQL. Stop the remote mapping after the assignment.

## Persistence, secrets, logs

- Keep `ai-detector-mysql-data` and `ai-detector-models`; take a tested backup before planned changes. Never run `docker volume rm` as part of rebuild.
- The web app uses local socket auth for MySQL and has no DB password in source. Ensure the existing account/grant is least privilege.
- Logs are available with `docker logs`; configured JSON rotation bounds retained Docker logs. Security events include rejects, inference failures/timeouts and API errors; no image bytes are logged.
- Avoid adding credentials to image layers or build args. Use runtime secret files/environment only if future integrations require credentials.
