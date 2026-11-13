# Plagiarism and Authorship Platform

Open-access tools and documentation from the research project
**Advancing Plagiarism Detection through Contextual Embeddings**
(project No. АР2348777; SDU NLP, SDU University, Kaskelen, Kazakhstan, 2024 to 2026).

**Live site:** https://shiralikadyrov.github.io/plagiarism-platform/

## What it does

| Part | What it is for | Where it runs |
|---|---|---|
| Similarity check | Compare a submission with source texts. Shows matched text per source, highlights passages, exports a report. | In the browser. No text leaves the device. |
| Paraphrase check | Sentence-level comparison by meaning with a contextual embedding model. | On the API server (`server.py`), called from the site, from Moodle or from other systems. |
| Authorship | Rank candidate authors by closeness of writing style. | In the browser (stylometric baseline) or on the API. |
| Moodle plugin | Checks assignment submissions against classmates' work and shows the result in the grading view. | In Moodle 4.1 or later, calling the API server. See [`integrations/moodle`](integrations/moodle/README.md). Beta. |
| Documentation | Instructor guide, method, API reference, installation guide for administrators, privacy. | On the site, under Documentation. |

The site is available in English, Kazakh and Russian and reads `.txt`, `.docx`
and text-based `.pdf` files. The Kazakh and Russian texts are drafts that still
need review by native-speaker linguists; they live in `i18n.js` and in the
blocks marked `data-l="kk"` and `data-l="ru"` in `index.html`.

## Publishing project facts on the site

Edit [`project-data.js`](project-data.js) to add the grant number, the
validation results from task 3.2, publications, model links, the release DOI
and pilot sites. The site fills the matching sections from that file. Leave a
value empty until it is known.

## Run the API server

```bash
pip install -r requirements.txt
MODEL_NAME=/path/to/fine-tuned-model \
CORS_ORIGINS=https://shiralikadyrov.github.io \
API_KEY=choose-a-key \
uvicorn server:app --host 0.0.0.0 --port 8000
```

Or with Docker (CPU image):

```bash
docker build -t plagiarism-api .
docker run -p 8000:8000 -v plagiarism-models:/models -e API_KEY=choose-a-key plagiarism-api
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
| `ENCODE_BATCH` | Sentences encoded per batch | `32` |
| `MAX_SENT_WORDS` | Longer sentences are split before encoding, so no text is cut off | `60` |

The site's Documentation has a step-by-step installation guide for
administrators, with an HTTPS reverse-proxy example.

## Tests

```bash
pip install -r requirements-dev.txt
pytest
ruff check .
```

The tests replace the model with a small stub, so they need no GPU and no model
download. GitHub Actions runs them on every push, together with a PHP syntax
check of the Moodle plugin.

## Repository contents

```
index.html            the website (served by GitHub Pages)
i18n.js               Kazakh and Russian interface texts
project-data.js       grant number, evaluation results, publications (edit this)
server.py             reference API server
requirements.txt      Python dependencies for the server
requirements-dev.txt  dependencies for running the tests
tests/                API tests (pytest)
Dockerfile            CPU container image for the API server
integrations/moodle/  Moodle plagiarism plugin (GPL v3 or later)
.github/workflows/    continuous integration
ROADMAP.md            remaining tasks
CITATION.cff          citation metadata
LICENSE               MIT licence for the code
.nojekyll             serve files as-is on GitHub Pages
```

## Responsible use

Scores are a prompt for human review. Quoted and cited text also counts as a
match, and short texts give unstable results. Decisions about misconduct belong
to people who have read the passages and heard the student.

## Licence and citation

Code: MIT licence (see `LICENSE`). Moodle plugin: GNU GPL v3 or later, as Moodle
requires. Documentation: CC BY 4.0.
Use GitHub's **Cite this repository** button, or:

> Kadyrov, S., et al. (2026). *Plagiarism and Authorship Platform* (Version 1.0.0) [Computer software]. SDU NLP, SDU University. https://github.com/shiralikadyrov/plagiarism-platform

## Acknowledgement

This work is supported by grant funding for scientific and scientific-technical
projects, project No. АР2348777.
