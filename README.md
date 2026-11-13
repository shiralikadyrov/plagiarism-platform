# Plagiarism and Authorship Platform

Open-access tools and documentation from the research project
**Advancing Plagiarism Detection through Contextual Embeddings**
(SDU NLP, SDU University, Kaskelen, Kazakhstan, 2024 to 2026).

**Live site:** https://shiralikadyrov.github.io/plagiarism-platform/

## What it does

| Part | What it is for | Where it runs |
|---|---|---|
| Similarity check | Compare a submission with source texts. Shows matched text per source, highlights passages, exports a report. | In the browser. No text leaves the device. |
| Paraphrase check | Sentence-level comparison by meaning with a contextual embedding model. | On the API server (`server.py`), called from the site or from other systems. |
| Authorship | Rank candidate authors by closeness of writing style. | In the browser (stylometric baseline) or on the API. |
| Documentation | User guide, method, API reference, LMS integration, privacy. | On the site, under Documentation. |

The site reads `.txt`, `.docx` and text-based `.pdf` files.

## Run the API server

```bash
pip install -r requirements.txt
MODEL_NAME=/path/to/fine-tuned-model \
CORS_ORIGINS=https://shiralikadyrov.github.io \
API_KEY=choose-a-key \
uvicorn server:app --host 0.0.0.0 --port 8000
```

Interactive API docs are at `/docs` on the running server. Serve it over HTTPS
behind a reverse proxy; browsers block calls from the https site to a plain http
server. Then open the site, expand **Connect to the project API**, and enter the
address and key.

| Setting | Meaning | Default |
|---|---|---|
| `MODEL_NAME` | Sentence encoder for `/v1/compare` | `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2` |
| `STYLE_MODEL_NAME` | Encoder for `/v1/authorship` | same as `MODEL_NAME` |
| `SIM_THRESHOLD` | Cosine at or above which a sentence pair is flagged | `0.80` |
| `API_KEY` | Required `X-API-Key` header value, if set | not set |
| `CORS_ORIGINS` | Browser origins allowed to call the API | none |

## Repository contents

```
index.html       the website (served by GitHub Pages)
server.py        reference API server
requirements.txt Python dependencies for the server
ROADMAP.md       remaining technical tasks
CITATION.cff     citation metadata
LICENSE          MIT licence for the code
.nojekyll        serve files as-is on GitHub Pages
```

## Responsible use

Scores are a prompt for human review. Quoted and cited text also counts as a
match, and short texts give unstable results. Decisions about misconduct belong
to people who have read the passages and heard the student.

## Licence and citation

Code: MIT licence (see `LICENSE`). Documentation: CC BY 4.0.
Use GitHub's **Cite this repository** button, or:

> Kadyrov, S., et al. (2026). *Plagiarism and Authorship Platform* (Version 1.0.0) [Computer software]. SDU NLP, SDU University. https://github.com/shiralikadyrov/plagiarism-platform

## Acknowledgement

This work is supported by grant funding for scientific and scientific-technical
projects (grant number to be added).
