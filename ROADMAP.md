# Roadmap: Plagiarism and Authorship Platform

Project: **Advancing Plagiarism Detection through Contextual Embeddings**
SDU NLP, SDU University, Kaskelen, Kazakhstan · Project period 2024 to 2026
This roadmap covers task 3.3, *User-Friendly Integration and Documentation* (July to December 2026).

The project ends on 31 December 2026. Tasks marked **Must** are needed to report the 3.3 deliverable ("Integrated system into platforms; Open-access documentation"). Tasks marked **Should** strengthen the report. Tasks marked **Later** can continue after the grant.

Status values: `Done`, `In progress`, `To do`.

---

## Current state

| Component | Status | Notes |
|---|---|---|
| Web platform (`index.html`) | Done | Similarity check, authorship comparison, documentation, project page. Reads .txt, .docx and .pdf. Exports reports. Optional connection to the API. Interface in English, Kazakh and Russian (translations await native-speaker review). Passes an automated accessibility audit (axe-core, WCAG 2.1 AA rules) on all four views. |
| Reference API (`server.py`) | In progress | `/health`, `/v1/ping`, `/v1/compare`, `/v1/authorship`. 12 automated tests with a stub encoder, lint, CI workflow. `Dockerfile` written but not yet built. Not yet run with the project's fine-tuned model. |
| Moodle plugin (`integrations/moodle/sduembed`) | In progress | Code complete for assignments (queue, scheduled task, grading-view result, report, settings, privacy API, three languages). API client and result merging tested against the live API server. **Not yet installed on a Moodle site.** |
| Public hosting | Done | https://shiralikadyrov.github.io/plagiarism-platform/ |
| Project facts on the site | To do | Grant number, task 3.2 results, publications, model links and pilot sites are entered in `project-data.js`. All are empty. |

---

## Repository layout

The website stays in the repository root because GitHub Pages serves it from
there. `server.py` stays in the root so that the documented commands keep
working.

```
plagiarism-platform/
├── index.html              the web platform, served by GitHub Pages
├── i18n.js                 Kazakh and Russian interface texts
├── project-data.js         grant number, results, publications, pilots
├── server.py               reference API server
├── requirements.txt        (plus requirements-dev.txt for tests)
├── Dockerfile
├── tests/                  API tests
├── integrations/
│   └── moodle/sduembed/    Moodle plagiarism plugin
├── .github/workflows/      CI
├── README.md, ROADMAP.md, CITATION.cff, LICENSE
```

---

## A. Repository and open access

| ID | Task | Deliverable | Done when | Priority |
|---|---|---|---|---|
| A1 | Create a public GitHub repository under the project or SDU NLP account and add the layout above. | Repository | Repository is public and the layout is in place. | Must · Done |
| A2 | Choose licences for code and documentation. | `LICENSE`, `LICENSE-docs` | Both files present and referenced in README. | Must · Done |
| A3 | Publish the platform with GitHub Pages from `docs/`. | Public URL | Page opens without signing in, in a private browser window. | Must · Done |
| A4 | Write README: purpose, project name and grant, screenshots, quick start for the web page and the API, link to the site. | `README.md` | A new user can run the API from the README alone. | Must · Done |
| A5 | Add `CITATION.cff` with authors, title, version and repository URL. | `CITATION.cff` | GitHub shows the "Cite this repository" button. | Should · Done |
| A6 | Fill the "Source, licence and citation" section on the platform with the repository URL, licence and citation. | Updated `index.html` | Placeholder text removed. | Must · Done |
| A7 | Tag release `v1.0.0` and archive it on Zenodo to get a DOI. | Release, DOI | DOI resolves and is shown in README and on the platform. | Should |
| A8 | Add the grant number to the platform's project page and README. | Updated pages | Grant number visible. | Must · Site reads it from `project-data.js`; number still to be entered |

---

## B. Model integration

| ID | Task | Deliverable | Done when | Priority |
|---|---|---|---|---|
| B1 | Export the fine-tuned contextual embedding model from tasks 1.2 and 2.2 in `sentence-transformers` format. | Model folder | `SentenceTransformer(path)` loads it and encodes a sentence. | Must |
| B2 | Export the style (triplet-loss) model used for authorship. | Model folder | Loads the same way; set as `STYLE_MODEL_NAME`. | Must |
| B3 | Publish both models on Hugging Face Hub with a model card: training data summary, languages, intended use, limitations, evaluation results from task 3.2. | Model pages | Models are public and linked from README. | Should |
| B4 | Calibrate `SIM_THRESHOLD` on the validation set from task 3.2 and record the chosen value with precision and recall. | Short note in `evaluation/` | Default in `server.py` matches the calibrated value. | Must |
| B5 | Pin library versions (`torch`, `sentence-transformers`, `fastapi`) in `requirements.txt`. | `requirements.txt` | Fresh install reproduces the results in B4. | Must · To do, after B4. Versions are lower bounds only |

---

## C. API server

| ID | Task | Deliverable | Done when | Priority |
|---|---|---|---|---|
| C1 | Run `server.py` with the project model on real texts in Kazakh, Russian and English and fix any errors. | Test log | All three endpoints return correct results with the real model. | Must |
| C2 | Write `pytest` tests: health, compare, authorship, 401 with wrong key, 422 on empty text, long-text limit. Use a small stub encoder so tests run without a GPU. | `server/tests/` | `pytest` passes locally. | Must · Done (12 tests) |
| C3 | Add a `Dockerfile` (CPU image; optional GPU image based on a CUDA base). | `Dockerfile` | `docker run -p 8000:8000 ...` serves `/health`. | Must · Written, not yet built or run |
| C4 | Add GitHub Actions: run tests and a lint step on every push and pull request. | `.github/workflows/ci.yml` | Green check on `main`. | Should · Workflow added; confirm the first run is green |
| C5 | Split long documents into chunks and batch sentence encoding to bound memory use. | Code change | A 200,000-character document is processed without running out of memory on the lab GPU. | Should · Partly done: batched encoding and splitting of over-long sentences. The 200,000-character memory test on the lab GPU is still to do |
| C6 | Add a source index endpoint so institutions can store reference documents once and compare new submissions against all of them (vector index, e.g. FAISS). | `/v1/index`, `/v1/search` | A submission is compared with 10,000 stored documents in under a few seconds on the lab GPU. | Later |
| C7 | Add rate limiting and request-size limits at the reverse proxy; document an HTTPS deployment. | Deployment notes | Documented in the platform's deployment checklist. | Should · Documented in the installation guide; apply on the real server |
| C8 | Deploy a demo instance on the SDU GPU server for partner institutions. | Internal URL | Partners from task 3.1 can call it with their key. | Should |

---

## D. Web platform

| ID | Task | Deliverable | Done when | Priority |
|---|---|---|---|---|
| D1 | Add an optional "Use API" mode: the user enters the API address and key, and the similarity check calls `/v1/compare` and shows embedding matches next to the lexical ones. | Updated `index.html` | Paraphrased example source is flagged when connected to the API. | Should · Done |
| D2 | Accept `.docx` and `.pdf` uploads by extracting text in the browser (e.g. mammoth.js and pdf.js). | Updated `index.html` | Text from a sample DOCX and PDF appears in the input box. | Should · Done |
| D3 | Translate the interface and documentation into Kazakh and Russian with a language switch. | Updated `index.html` | All labels and docs available in three languages. | Should · Draft done for the interface and the instructor-facing documentation. Technical sections stay in English |
| D4 | Review the Kazakh and Russian function-word lists with native speakers. | Updated lists | Reviewed and signed off. | Must · To do. Now also covers the translations in `i18n.js`, `index.html` and the plugin language packs |
| D5 | Accessibility check (keyboard use, screen reader labels, contrast). | Checklist | No blocking issues in a Lighthouse accessibility audit. | Should · Done: no violations in an axe-core audit (WCAG 2.1 A and AA rules, light and dark theme). A manual screen-reader pass is still worthwhile |
| D6 | Add an "Evaluation" section showing the validation results from task 3.2 (accuracy per discipline and per plagiarism type). | Updated docs | Numbers match the project's validation report. | Must · Section added; it fills from `project-data.js`. Numbers still to be entered |

---

## E. Integration into educational platforms

This is the "integrated system into platforms" part of the 3.3 deliverable.

| ID | Task | Deliverable | Done when | Priority |
|---|---|---|---|---|
| E1 | Agree with at least one partner institution from task 3.1 which platform to integrate with (Moodle is most likely). | Written agreement or email | Platform and contact person confirmed. | Must |
| E2 | Build a Moodle plagiarism plugin (`plagiarism_sduembed`): admin settings for API URL and key; listen for assignment submission events; send text to `/v1/compare` from a scheduled task; show the flagged share and a link to matched passages in the grading view. | `integrations/moodle/` | Plugin installs on a test Moodle and scores a submitted essay. | Must · Code complete, tested against the API. Not yet installed on Moodle |
| E3 | Install the plugin on SDU's or the partner's Moodle test site and run it on one real course with consent. | Pilot report | At least one course's submissions scored; instructor feedback collected. | Must |
| E4 | Build an LTI 1.3 tool for Canvas and other LTI platforms. | `integrations/lti/` | Tool launches from a Canvas test course and shows a report. | Later |
| E5 | Write an installation guide for IT administrators (Moodle plugin and API server). | `docs/` page | An administrator outside the team installs it from the guide alone. | Must · Written (site Documentation and `integrations/moodle/README.md`). Not yet tried by an outside administrator |

---

## F. Documentation and dissemination

| ID | Task | Deliverable | Done when | Priority |
|---|---|---|---|---|
| F1 | Record a short screen video: running a check, reading the report, connecting Moodle. | Video link | Linked from the platform and README. | Should |
| F2 | Add a "Publications" section listing the project's papers with DOIs. | Updated docs | All accepted papers listed. | Must · Section added; it fills from `project-data.js`. Papers still to be entered |
| F3 | Add a privacy notice and data-processing description for institutions. | Updated docs | Reviewed by SDU's data protection contact. | Should · Privacy section and Moodle privacy API done. Review by the data protection contact still to do |
| F4 | Write a one-page summary of task 3.3 for the final grant report: what was integrated, where it is hosted, usage numbers from the pilot. | Report text | Included in the final report. | Must |

---

## Schedule to 31 December 2026

| Week | Dates | Tasks |
|---|---|---|
| 1 | 16 to 22 Nov | A1, A2, A3, A4, B1, B2, D4 |
| 2 | 23 to 29 Nov | B4, B5, C1, C2, C3, E1 |
| 3 | 30 Nov to 6 Dec | E2, C4, D6, A6, A8 |
| 4 | 7 to 13 Dec | E2 testing, E5, C8, D1 |
| 5 | 14 to 20 Dec | E3 pilot, F2, A5, A7 |
| 6 | 21 to 27 Dec | F4, fixes from pilot, release v1.0.0 |

Critical path: B1 → C1 → E2 (install on a test Moodle) → E3 → F4. The Moodle pilot (E3) depends on a partner's test site, so E1 should start in week 1.

---

## Suggested GitHub labels

`must`, `should`, `later`, `api`, `web`, `model`, `moodle`, `lti`, `docs`, `evaluation`

Each table row can be opened as one GitHub issue with its ID in the title (for example `E2: Moodle plagiarism plugin`) and grouped in a GitHub Project board with columns To do, In progress, Done.
