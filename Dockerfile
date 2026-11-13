# CPU image for the reference API server.
#   docker build -t plagiarism-api .
#   docker run -p 8000:8000 -e API_KEY=choose-a-key plagiarism-api
# For a GPU, install the CUDA build of torch instead (see the PyTorch site for
# the index URL that matches your driver) and run with `--gpus all`.
FROM python:3.12-slim

ENV PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    HF_HOME=/models

WORKDIR /app
COPY requirements.txt .
RUN pip install torch --index-url https://download.pytorch.org/whl/cpu \
 && pip install -r requirements.txt
COPY server.py .

# Downloaded models are kept in this volume between restarts. To serve a model
# from disk, mount its folder and set MODEL_NAME to the mounted path.
RUN useradd --create-home --uid 10001 api && mkdir -p /models && chown api /models
VOLUME /models
USER api

EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s \
  CMD python -c "import urllib.request as u; u.urlopen('http://127.0.0.1:8000/health', timeout=4)"
CMD ["uvicorn", "server:app", "--host", "0.0.0.0", "--port", "8000"]
