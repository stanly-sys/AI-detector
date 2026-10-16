
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

# Install LAMP stack, Supervisor, and Python
RUN apt-get update && \
    apt-get install -y \
    apache2 \
    php \
    libapache2-mod-php \
    mysql-server \
    php-mysql \
    supervisor \
    python3 \
    python3-venv && \
    rm -rf /var/lib/apt/lists/*

# Create Python virtual environment for AI detection
RUN python3 -m venv /opt/ai-venv

# Use virtual environment Python and pip by default
ENV PATH="/opt/ai-venv/bin:$PATH"


# Install CPU version of PyTorch
RUN pip install --no-cache-dir "torch==2.7.1" \
    --index-url https://download.pytorch.org/whl/cpu

RUN pip install --no-cache-dir --timeout 300 --retries 10 "torchvision==0.22.1" \
    --index-url https://download.pytorch.org/whl/cpu



# Install known previously working ML dependencies
RUN pip install --no-cache-dir --timeout 300 --retries 10 \
    "transformers==4.57.6" \
    "pillow==12.3.0"

# Verify Python dependency compatibility
RUN pip check

# Install model loading and image processing libraries
RUN pip install --no-cache-dir --timeout 300 --retries 10 \
    "transformers>=4.40,<5" \
    "pillow>=10,<13" \
    "timm==1.0.15" \
    "safetensors>=0.4,<1" \
    "huggingface-hub>=0.30,<2"

RUN python -c "import torch, torchvision, timm, safetensors; print('torch', torch.__version__, 'torchvision', torchvision.__version__, 'timm', timm.__version__, 'safetensors', safetensors.__version__)"

# Remove Apache's default webpage
RUN rm -f /var/www/html/index.html


# Install tools needed to download C2PA
RUN apt-get update && apt-get install -y \
    --no-install-recommends \
    curl ca-certificates tar \
    && rm -rf /var/lib/apt/lists/*

# Install official C2PA Tool for Linux AMD64
ARG C2PATOOL_VERSION=0.28.1

RUN set -eu; \
    mkdir -p /tmp/c2pa-install; \
    curl -fL --retry 3 \
    "https://github.com/contentauth/c2patool/releases/download/v${C2PATOOL_VERSION}/v${C2PATOOL_VERSION}-x86_64-unknown-linux-gnu.tar.gz" \
    -o /tmp/c2patool.tar.gz; \
    tar -xzf /tmp/c2patool.tar.gz -C /tmp/c2pa-install; \
    BINARY="$(find /tmp/c2pa-install -type f -name c2patool -print -quit)"; \
    test -n "$BINARY"; \
    install -m 0755 "$BINARY" /usr/local/bin/c2patool; \
    rm -rf /tmp/c2pa-install /tmp/c2patool.tar.gz; \
    c2patool -V

# Install the official C2PA certificate trust anchors
RUN mkdir -p /etc/c2pa \
    && curl -fL --retry 3 \
    "https://raw.githubusercontent.com/c2pa-org/conformance-public/refs/heads/main/trust-list/C2PA-TRUST-LIST.pem" \
    -o /etc/c2pa/trust.pem \
    && grep -q "BEGIN CERTIFICATE" /etc/c2pa/trust.pem



# Copy PHP frontend into Apache's web directory
COPY index.php /var/www/html/
COPY assets/ /var/www/html/assets/
COPY includes/ /var/www/html/includes/

# Copy PHP backend API into Apache's web directory
COPY api/ /var/www/html/api/

# Keep temporary uploads private and writable only by Apache's PHP worker.
RUN install -d -o www-data -g www-data -m 0700 /tmp/ai-detector-uploads /tmp/ai-detector-limits \
    && install -d -o root -g www-data -m 0770 /var/log/ai-detector

COPY php-security.ini /etc/php/8.3/apache2/conf.d/99-ai-security.ini


# Copy Python AI detector into Ubuntu
COPY ml/detector.py /opt/ai-detector/detector.py
COPY ml/inference.py /opt/ai-detector/inference.py
COPY ml/model_contract.py /opt/ai-detector/model_contract.py
COPY ml/models/ /opt/ai-detector/models/
COPY ml/prepare_community_model.py /opt/ai-detector/prepare_community_model.py
COPY ml/evaluation/ /opt/ai-detector/evaluation/

# Copy Supervisor configuration
COPY supervisord.conf /etc/supervisor/conf.d/lamp.conf

# Create MySQL runtime directory
RUN mkdir -p /run/mysqld \
    && chown mysql:mysql /run/mysqld \
    && chmod 755 /run/mysqld


# Install Apache security configuration
COPY apache-security.conf /etc/apache2/conf-available/ai-security.conf
COPY apache-prefork.conf /etc/apache2/conf-available/ai-prefork.conf

RUN a2enmod headers reqtimeout \
    && a2enconf ai-security ai-prefork

EXPOSE 80

CMD ["/usr/bin/supervisord", "-n"]
