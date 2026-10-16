# Multi-model deployment

The Docker image includes both model runtimes, but the existing model volume is preserved. Community Forensics weights are not bundled in the image and must be prepared once in the existing model volume. Ensure that volume has enough free space, then temporarily mount it writable and run this from PowerShell:

```sh
docker run --rm -v ai-detector-models:/opt/models `
  ai-detector-ubuntu /opt/ai-venv/bin/python /opt/ai-detector/prepare_community_model.py
```

The downloader fetches the official pinned `OwensLab/commfor-model-224` config and SafeTensors checkpoint, validates configuration, classifier-head shape and file size, then records a SHA-256 manifest. Review the manifest and keep the volume mounted at `/opt/models` for normal operation. Run the application with the model volume read-only where the deployment permits. The existing SDXL directory remains `/opt/models/sdxl-detector`; Community Forensics is stored under `/opt/models/community-forensics`.

After preparing the model volume, build and recreate the existing named container while preserving both named volumes:

```powershell
docker build -t ai-detector-ubuntu .
docker stop ai-detector-web
docker rm ai-detector-web
docker run -d --name ai-detector-web -p 8080:80 `
  -v ai-detector-mysql-data:/var/lib/mysql `
  -v ai-detector-models:/opt/models:ro `
  ai-detector-ubuntu
```

The `stop`/`rm` steps recreate only the container; the database and model volumes remain named and mounted. Confirm the existing container’s actual mounts and database initialization before applying these commands to a customized deployment.

Apply `db/migrations/001_model_analysis_results.sql` to the existing database as a separate migration. Back up the database first and grant the web application account INSERT access to the new table. The migration creates only the model-specific history table; existing `analysis_results` rows remain intact. The application continues to insert at most one legacy row per upload, preferring SDXL, and stores each selected model outcome in the new table. If the migration is not applied, inference still returns to the browser and the save failure is logged.

Inference stays sequential under the existing single-inference lock and request timeout. Both models receive the same decoded RGB image. Verify with `both`, each individual selection, an unavailable-checkpoint case, and a labeled evaluation run after deployment.
