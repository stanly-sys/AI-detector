<div align="center">

<img src="assets/readme-icons/brand.svg" alt="AI Image Detector logo" width="76" height="76" />

# AI Image Detector

### AI-image classification · Content Credentials verification · Analysis analytics

**A Dockerized, multi-model image-forensics web application built with PHP, Python, PyTorch, MySQL, and C2PA.**

<p>
  <img alt="Ubuntu 24.04" src="https://img.shields.io/badge/Ubuntu-24.04-E95420?logo=ubuntu&logoColor=white" />
  <img alt="Docker" src="https://img.shields.io/badge/Docker-Containerized-2496ED?logo=docker&logoColor=white" />
  <img alt="PHP" src="https://img.shields.io/badge/PHP-Backend-777BB4?logo=php&logoColor=white" />
  <img alt="Python" src="https://img.shields.io/badge/Python-AI%20Inference-3776AB?logo=python&logoColor=white" />
  <img alt="PyTorch" src="https://img.shields.io/badge/PyTorch-CPU%20Inference-EE4C2C?logo=pytorch&logoColor=white" />
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-Analytics-4479A1?logo=mysql&logoColor=white" />
  <img alt="C2PA" src="https://img.shields.io/badge/C2PA-Content%20Credentials-84CC16" />
  <img alt="ML models" src="https://img.shields.io/badge/AI%20Models-SDXL%20%2B%20Community%20Forensics-8B5CF6" />
</p>

**Current detectors: [`Organika/sdxl-detector`](https://huggingface.co/Organika/sdxl-detector) and [`OwensLab/commfor-model-224`](https://huggingface.co/OwensLab/commfor-model-224).**  
*Both remain available while their behavior is compared. No final model has been selected.*

[Features](#features) · [Quick Start](#quick-start-with-docker) · [How It Works](#how-it-works) · [API](#api-reference) · [Security](#security-notes) · [Troubleshooting](#troubleshooting)

</div>

---

> [!IMPORTANT]
> This application provides **model estimates and provenance observations**, not proof that an image is authentic or AI-generated. **Community Forensics has appeared more reliable than the SDXL detector in the project’s preliminary manual tests, but it is not 100% accurate; this is not a formal benchmark result.** High scores do **not** guarantee correctness. Missing C2PA credentials do **not** imply that an image is human-created.

## <img src="assets/readme-icons/sparkles.svg" width="22" height="22" alt="" /> Features

| Feature | What it does |
|:--|:--|
| <img src="assets/readme-icons/image.svg" width="19" height="19" alt="" /> **Image upload** | Drag-and-drop or select **JPEG, PNG, or WebP** images, with a **2 MB** application-level limit. |
| <img src="assets/readme-icons/brain.svg" width="19" height="19" alt="" /> **Two AI detectors** | Runs **SDXL Detector** and **Community Forensics** as separate model options; the original SDXL model remains installed for comparison. |
| <img src="assets/readme-icons/sliders.svg" width="19" height="19" alt="" /> **Model selection** | Choose **Both models**, **SDXL only**, or **Community only** in the upload interface. |
| <img src="assets/readme-icons/chart.svg" width="19" height="19" alt="" /> **Visual results** | Displays the selected model results, separate **AI** and **human** scores, and animated confidence bars. Use comparison mode to inspect differences. |
| <img src="assets/readme-icons/shield.svg" width="19" height="19" alt="" /> **C2PA verification** | Uses [`c2patool`](https://github.com/contentauth/c2patool) to inspect signed Content Credentials and report their validation/trust status. |
| <img src="assets/readme-icons/file-text.svg" width="19" height="19" alt="" /> **Declared origin** | Reads supported C2PA `digitalSourceType` declarations, if available. |
| <img src="assets/readme-icons/database.svg" width="19" height="19" alt="" /> **MySQL records** | Stores classification labels, scores, and timestamps; no need to retain the uploaded image. |
| <img src="assets/readme-icons/trending-up.svg" width="19" height="19" alt="" /> **Analytics** | Shows aggregate counts and model-score statistics from MySQL. |
| <img src="assets/readme-icons/clock.svg" width="19" height="19" alt="" /> **Recent history** | Shows a limited recent-analysis history in the current browser's local storage. |
| <img src="assets/readme-icons/link.svg" width="19" height="19" alt="" /> **SynthID link** | Opens Google's external SynthID verification service; **automatic SynthID detection is not integrated**. |
| <img src="assets/readme-icons/boxes.svg" width="19" height="19" alt="" /> **Single container** | Runs Apache, PHP, MySQL, and Python under Supervisor in **one Ubuntu container**, without Docker Compose. |

## <img src="assets/readme-icons/workflow.svg" width="22" height="22" alt="" /> How It Works

```mermaid
flowchart TD
    A[User selects an image] --> B[PHP upload validation]
    B -->|Invalid| X[JSON validation error]
    B -->|Accepted| C[Temporary image file]
    C --> M{Model selection}
    M -->|SDXL only or Both| D[SDXL detector]
    M -->|Community only or Both| Q[Community Forensics]
    C --> E[c2patool: C2PA provenance check]
    D --> F[Store model-specific result]
    Q --> F
    F --> G[PHP JSON response]
    E --> G
    G --> H[Frontend results + comparison]
    G --> I[Browser-local recent history]
    F --> J[Aggregate analytics endpoint]
    J --> K[Analytics dashboard]
    C -. cleanup .-> L[Remove temporary image]
```

### Three different kinds of evidence

| Component | Can report | Cannot establish by itself |
|:--|:--|:--|
| **SDXL classifier** | Its image-based `artificial` versus `human` classification. | Reliable results for every generator; it has produced questionable classifications during this project’s manual testing. |
| **Community Forensics** | Its image-based classification of AI-generated versus real images. | Perfect accuracy or guaranteed authenticity; preliminary tests have been promising, not conclusive. |
| **C2PA** | Whether signed credentials exist, their validation state, and any supported source declarations. | AI generation when no declaration exists; a trustworthy claim when the signer is untrusted. |
| **SynthID** | **Manual external check only**, via the official site. | Automatic watermark results inside this application (not implemented). |

> [!NOTE]
> `valid_untrusted` means the credentials passed integrity checks **but their signer is not trusted** by the configured trust anchors. `not_found` means no embedded credentials were detected; it is **not** a “real photo” verdict.

## <img src="assets/readme-icons/layers.svg" width="22" height="22" alt="" /> Technology Stack

| Layer | Technology |
|:--|:--|
| Operating system | Ubuntu 24.04 (Docker image) |
| Web server | Apache HTTP Server |
| Backend | PHP |
| Frontend | HTML, CSS, vanilla JavaScript |
| Database | MySQL |
| ML runtime | Python virtual environment, PyTorch (CPU), Hugging Face Transformers, Pillow |
| Detector A | [`Organika/sdxl-detector`](https://huggingface.co/Organika/sdxl-detector) (original model, retained) |
| Detector B | [`OwensLab/commfor-model-224`](https://huggingface.co/OwensLab/commfor-model-224) (current preferred candidate for evaluation) |
| Provenance | `c2patool` **0.28.1** with a configured C2PA trust-anchor file |
| Process manager | Supervisor |
| Container orchestration | Docker CLI (**no Docker Compose**) |

## <img src="assets/readme-icons/folder.svg" width="22" height="22" alt="" /> Project Structure

```text
AI/
├── Dockerfile                 # Ubuntu image, runtime and dependencies
├── supervisord.conf          # Starts Apache and MySQL
├── apache-security.conf      # Apache hardening rules
├── index.php                 # Main website
├── api/
│   ├── analyze.php           # Upload → classify → verify → persist
│   └── stats.php             # Aggregate statistics JSON
├── includes/
│   ├── c2pa.php              # c2patool integration
│   └── ...                   # Shared PHP helpers/content
├── ml/
│   ├── detector.py           # Python inference entry point / compatibility layer
│   └── ...                   # Model adapters and test resources (repository-dependent)
├── assets/
│   ├── css/                  # Layout, styles and animations
│   ├── js/                   # Upload, results and dashboard behavior
│   ├── img/                  # Website image assets and favicon
│   └── readme-icons/         # GitHub README SVG icons
└── README.md
```

Pretrained model weights belong in the **persistent Docker model volume**, not in Git. The existing SDXL model uses `/opt/models/sdxl-detector/`; the Community Forensics installation uses its own location as configured in the project’s current model adapter. Do not remove either model while comparing them.

## <img src="assets/readme-icons/rocket.svg" width="22" height="22" alt="" /> Quick Start with Docker

These commands assume **Windows + Docker Desktop (Linux containers) + Git Bash**. Run them from the project root, `D:\AI`.

### Prerequisites

- Docker Desktop installed, running, and using **Linux containers**.
- Git Bash (or adapt the commands to PowerShell/Linux).
- Sufficient free RAM, disk space, and internet access for the initial image build and pretrained-model download.
- TCP port **8080** available on the Windows host.

### 1. Build the application image

```bash
cd /d/AI
docker build -t ai-detector-ubuntu .
```

### 2. Create persistent volumes

```bash
docker volume create ai-detector-mysql-data
docker volume create ai-detector-models
```

These commands are safe to repeat; they do **not** wipe existing named volumes.

### 3. Prepare model weights (first-time setup only)

If the model weights are already in `ai-detector-models`, **skip downloading them again**. The command below prepares the original SDXL model:

```bash
MSYS_NO_PATHCONV=1 docker run --rm \
  --entrypoint /opt/ai-venv/bin/python \
  -v ai-detector-models:/opt/models \
  ai-detector-ubuntu \
  -c "from huggingface_hub import snapshot_download; snapshot_download(repo_id='Organika/sdxl-detector', local_dir='/opt/models/sdxl-detector')"
```

**Community Forensics:** Its weight files and model-specific loader must also be prepared using the implementation in your current repository. Follow the repository’s model-installation script or deployment instructions; **do not assume SDXL’s download or `pipeline()` procedure also works for Community Forensics**.

The temporary setup container needs a **writable** model volume. The actual web container mounts the model volume **read-only**. Preserve both models in that volume.

### 4. Start the application

```bash
MSYS_NO_PATHCONV=1 docker run -d \
  --name ai-detector-web \
  -p 127.0.0.1:8080:80 \
  -v ai-detector-mysql-data:/var/lib/mysql \
  -v ai-detector-models:/opt/models:ro \
  ai-detector-ubuntu
```

Open **http://127.0.0.1:8080**.

> [!TIP]
> In **Git Bash**, prefix Docker commands that include Linux container paths with `MSYS_NO_PATHCONV=1` to prevent Git Bash from converting paths such as `/opt/models` into Windows paths.

### 5. Initialize MySQL (first-time setup only)

Existing installations with `ai_image_detector.analysis_results` and the restricted `www-data` MySQL account can **skip this step**. Wait for MySQL to start and check:

```bash
docker exec ai-detector-web mysqladmin ping
```

To initialize a new database, execute:

```bash
docker exec ai-detector-web mysql -e "
CREATE DATABASE IF NOT EXISTS ai_image_detector;
CREATE TABLE IF NOT EXISTS ai_image_detector.analysis_results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    prediction VARCHAR(16) NOT NULL,
    ai_score DECIMAL(9,8) NOT NULL,
    human_score DECIMAL(9,8) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;
CREATE USER IF NOT EXISTS 'www-data'@'localhost' IDENTIFIED WITH auth_socket;
GRANT SELECT, INSERT ON ai_image_detector.analysis_results TO 'www-data'@'localhost';
"
```

The PHP connection uses the local MySQL Unix socket with the `www-data` operating-system user. This setup assumes Ubuntu's MySQL `auth_socket` authentication plugin is available. If the current repository already includes a database migration or initialization script, use **that project's schema** instead of creating a competing table.

Verify database access with the same user Apache uses:

```bash
docker exec -u www-data ai-detector-web \
  mysql -u www-data -D ai_image_detector \
  -e "SELECT COUNT(*) FROM analysis_results;"
```

### 6. Verify the installation

```bash
# Running container
docker ps

# MySQL
docker exec ai-detector-web mysqladmin ping

# PHP syntax
MSYS_NO_PATHCONV=1 docker exec ai-detector-web \
  php -l /var/www/html/api/analyze.php

# C2PA utility
docker exec ai-detector-web c2patool -V

# Apache configuration
docker exec ai-detector-web apache2ctl configtest

# Aggregate statistics
curl -i http://127.0.0.1:8080/api/stats.php
```

Test an image using an existing local sample (for example, `ml/test.jpg`):

```bash
curl -i --max-time 180 \
  -F "image=@ml/test.jpg" \
  http://127.0.0.1:8080/api/analyze.php
```

**Expected:** HTTP `200` with the executed model result(s), plus provenance information when checked. On the website, test **Both models**, **SDXL only**, and **Community only**. CPU inference can be slow; the currently configured model adapter and security limits control execution.

## <img src="assets/readme-icons/plug.svg" width="22" height="22" alt="" /> API Reference

### `POST /api/analyze.php`

Uploads an image for the **selected detector or detectors** and returns available model results, C2PA information where executed, and SynthID integration status. The model selector in the frontend supports **Both models**, **SDXL only**, and **Community only**.

**Request:** `multipart/form-data` with form field `image` (JPEG, PNG, or WebP; max **2 MB**).

```bash
curl -sS --max-time 180 \
  -F "image=@ml/test.jpg" \
  http://127.0.0.1:8080/api/analyze.php
```

<details>
<summary><strong>What to expect in the response</strong></summary>

The current **multi-model** implementation returns classification information for whichever models ran. For each executed detector, use its returned label and score, not a score copied from the other detector. C2PA and SynthID are **independent** of the classifier's prediction.

Use the request above to inspect the exact JSON keys for the checked-in version of `api/analyze.php`. Model-specific response keys may differ from the original single-model `predictions` format as the comparison feature evolves. Do **not** interpret these as ground-truth probabilities.

For example, an actual manual test in the project's UI showed **Community only** reporting **0.22% AI score / 99.78% human score** on one image. This is a **single observed model output**, **not** an accuracy measurement or a guarantee about similar images.

</details>

Possible responses include `200` for successful analysis, `400` for invalid uploads, `405` for unsupported methods, and `500` if analysis fails. Additional security controls may produce `413`, `429`, or `503` where configured. A failure in one model should be reported clearly rather than misrepresented as a successful classification.

### `GET /api/stats.php`

Returns aggregate results from MySQL; it does **not** expose uploaded images.

```bash
curl -sS http://127.0.0.1:8080/api/stats.php
```

```json
{
  "success": true,
  "statistics": {
    "total": 25,
    "ai_total": 15,
    "human_total": 10,
    "average_ai_score": 61.42
  }
}
```

*Example values only.* The stored analysis count includes successful requests, not a ground-truth measure of detection accuracy.

## <img src="assets/readme-icons/microscope.svg" width="22" height="22" alt="" /> Model Comparison and Current Observations

| Model | Purpose | What has been observed so far | Current decision |
|:--|:--|:--|:--|
| **[SDXL Detector](https://huggingface.co/Organika/sdxl-detector)** | Original model specialized for identifying SDXL-like generated images. | Has produced some results that did not match expectations on manually tested photographs. | **Keep installed** as a baseline. |
| **[Community Forensics](https://huggingface.co/OwensLab/commfor-model-224)** | Broader synthetic-image detection candidate. | **Appears to classify the images tested so far more convincingly than SDXL.** | **Preferred candidate for further testing**, not yet a proven winner. |

> [!NOTE]
> **Community Forensics is not 100% accurate.** It can still label AI-generated images as real, or real images as AI-generated. These notes reflect the project owner's *informal testing*, **not** results from a labeled evaluation dataset or a verified accuracy benchmark. A disagreement between the two models does not tell us which is correct.

### Comparing results on the website

1. Upload the **same image** and choose **Both models** to compare the two outputs.
2. Alternatively choose **SDXL only** or **Community only** to inspect each model independently.
3. Review the **AI score**, **human score**, and prediction for each selected model. Treat the scores as model outputs, not proof.
4. For a fair final decision, collect images with **known real or AI-generated origins** and calculate accuracy, precision, recall, F1, false-positive rate, and processing time for each model.
5. **Do not delete either model yet.** Model selection and removal are postponed until these tests are complete.

## <img src="assets/readme-icons/shield.svg" width="22" height="22" alt="" /> Interpreting C2PA Results

| `c2pa.status` | Interpretation |
|:--|:--|
| `trusted` | Credentials verified against the configured trust anchors. |
| `valid_untrusted` | Integrity checks passed, but signer trust is **not** established. |
| `invalid` | Credential verification failed. |
| `not_found` | No embedded Content Credentials detected. |
| `present_unverified` | Credentials found, but verification status not established. |
| `unavailable` | Verification could not be performed. |

If an origin declaration is available, `c2pa.origin.type` can contain:

| Value | Declared source |
|:--|:--|
| `ai_generated_declared` | Generative-AI-created media |
| `ai_assisted_declared` | AI-generated content used in a composite |
| `digital_capture_declared` | Digital device capture |
| `unknown` | No recognized source declaration |

**Origin declarations and signature trust are separate findings.** A valid declaration from an untrusted signer is not equivalent to verified provenance. The current origin extraction reads supported actions from the **active** manifest; it does not prove the full editing history.

### SynthID status

The website links to [Google's SynthID Detector](https://synthid.com/) for **separate manual verification**. The backend deliberately reports `not_configured`: it does **not** automatically inspect SynthID watermarks.

## <img src="assets/readme-icons/lock.svg" width="22" height="22" alt="" /> Security Notes

The application has security-focused design elements, including server-side image validation, limited file size, temporary upload cleanup, parameterized database writes, a restricted MySQL application account, and an Apache security configuration.

> [!WARNING]
> **Do not assume the application is penetration-test-ready just because it runs successfully.** Review the actual code and configuration in the committed revision, and independently test upload validation, pixel/decompression limits, concurrency, request throttling, subprocess timeouts, dependency vulnerabilities, and permissions.

- **Local exposure:** The documented Docker command binds Apache to `127.0.0.1` only. Do not publish MySQL or expose this host to an untrusted network by accident.
- **User-supplied images:** Treat uploaded files and all image/C2PA parsing libraries as untrusted-input attack surfaces.
- **Data handling:** The current design saves analysis scores and timestamps in MySQL, not the original uploaded image. Uploaded files should be deleted after processing.
- **Credentials:** Do not commit passwords, API tokens, `.env` files, model caches, database contents, or sensitive test images.
- **C2PA:** A cryptographically valid manifest does **not** mean its signer is trusted or that all metadata claims are true.
- **Deployment:** The current image uses one container for several services as required for this project; production systems would normally isolate them more strongly.

Only perform security testing on systems you own or have explicit permission to test.

## <img src="assets/readme-icons/clipboard-check.svg" width="22" height="22" alt="" /> Testing Checklist

- [ ] Upload a known JPEG/PNG/WebP image successfully.
- [ ] Confirm AI and human model scores appear for **Community only**, **SDXL only**, and **Both models**.
- [ ] Compare both predictions on the **same** known-origin test image.
- [ ] Confirm the browser correctly reports model disagreement rather than automatically picking a winner.
- [ ] Confirm analytics increase after a successful analysis.
- [ ] Upload a corrupted image and confirm a clean validation error.
- [ ] Verify that uploads larger than 2 MB are rejected.
- [ ] Verify `includes/` and sensitive files are not publicly accessible.
- [ ] Verify a normal image without C2PA credentials shows `not_found`.
- [ ] Verify a signed C2PA test image reports its actual validation state.
- [ ] Verify stored results survive container recreation.
- [ ] Verify no analysis leaves temporary uploaded images behind.
- [ ] Run authorized request-rate, concurrency, and resource-limit tests.

A known C2PA test image is available in the [official c2patool samples](https://github.com/contentauth/c2patool/tree/main/sample). It may be **valid but signed by an untrusted test certificate**—that is a useful expected test case, not necessarily an error.

## <img src="assets/readme-icons/refresh.svg" width="22" height="22" alt="" /> Updating or Restarting the Container

### Stop/start without rebuilding

```bash
docker stop ai-detector-web
docker start ai-detector-web
```

### Rebuild after changing source code

From `D:\AI`:

```bash
docker build -t ai-detector-ubuntu .
```

**Only after the image builds successfully**, recreate the container using the same named volumes:

```bash
docker rm -f ai-detector-web
MSYS_NO_PATHCONV=1 docker run -d \
  --name ai-detector-web \
  -p 127.0.0.1:8080:80 \
  -v ai-detector-mysql-data:/var/lib/mysql \
  -v ai-detector-models:/opt/models:ro \
  ai-detector-ubuntu
```

> [!CAUTION]
> Do **not** run `docker volume rm ai-detector-mysql-data` or `docker volume rm ai-detector-models` when upgrading. Deleting these volumes can remove your analysis records or pretrained model weights.

## <img src="assets/readme-icons/wrench.svg" width="22" height="22" alt="" /> Troubleshooting

<details>
<summary><strong>HTTP 500 when analyzing an image</strong></summary>

Check Apache/PHP logs and confirm Python inference, model files, and MySQL access:

```bash
docker exec ai-detector-web sh -c 'tail -n 40 /var/log/apache2/error.log'
docker exec -u www-data ai-detector-web \
  mysql -u www-data -D ai_image_detector \
  -e "SELECT COUNT(*) FROM analysis_results;"
```

Do not enable PHP error display in public responses just to debug an exception.

</details>

<details>
<summary><strong>MySQL socket permission denied (error 13)</strong></summary>

Check `/run/mysqld` permissions and the application's MySQL account:

```bash
MSYS_NO_PATHCONV=1 docker exec ai-detector-web \
  sh -c 'ls -ld /run/mysqld; ls -l /run/mysqld/mysqld.sock'
```

In this project's setup, `/run/mysqld` needs to permit Apache's `www-data` user to traverse the directory. Fix it in the **Dockerfile/startup configuration**, not just temporarily inside a running container.

</details>

<details>
<summary><strong>Model loading fails or downloads repeatedly</strong></summary>

Verify the persistent volume contains `/opt/models/sdxl-detector` and the configured Community Forensics weights, and that the application container mounts `/opt/models:ro`. Check each model adapter’s expected path rather than assuming they share an identical directory structure. Initial downloads require the one-time writable preparation step above.

</details>

<details>
<summary><strong>“No Content Credentials found”</strong></summary>

This is common. Most ordinary files are not guaranteed to contain embedded C2PA manifests; some sites also strip them during processing. Test your C2PA integration against a **known signed sample**, not just random images.

</details>

<details>
<summary><strong>Git Bash changes paths passed to Docker</strong></summary>

Use `MSYS_NO_PATHCONV=1` before Docker commands that include Linux filesystem paths, particularly `docker exec`, `docker cp`, and volume mounts.

</details>

## <img src="assets/readme-icons/map.svg" width="22" height="22" alt="" /> Project Scope & Next Steps

This repository revision supports **two selectable detection models**: SDXL Detector and Community Forensics. **Community Forensics has performed better in the project owner’s preliminary hands-on checks, but its accuracy is not 100% and has not been formally measured.** Both models remain present while comparison continues.

Future work includes **independent labeled-dataset evaluation**, a documented final model selection, better inference performance, further security testing, richer provenance details, and authorized automatic watermark integrations where available.

## <img src="assets/readme-icons/book.svg" width="22" height="22" alt="" /> References

- [Organika SDXL Detector — Hugging Face](https://huggingface.co/Organika/sdxl-detector)
- [OwensLab Community Forensics — Hugging Face](https://huggingface.co/OwensLab/commfor-model-224)
- [c2patool — Content Authenticity Initiative](https://github.com/contentauth/c2patool)
- [C2PA specification](https://c2pa.org/specifications/specifications/)
- [Google SynthID](https://synthid.com/)
- [Docker documentation](https://docs.docker.com/)

---

<div align="center">

**Built as a personal project to explore AI-image classification and verifiable content provenance.**

*Compare both models critically. Neither is infallible. Verify provenance independently and preserve test evidence.*

</div>
