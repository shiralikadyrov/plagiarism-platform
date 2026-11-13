"""
Reference API server: plagiarism detection and authorship comparison
with contextual embeddings.

Project: "Advancing Plagiarism Detection through Contextual Embeddings" (SDU NLP)

Install
    pip install fastapi uvicorn sentence-transformers

Run
    uvicorn server:app --host 0.0.0.0 --port 8000

Configuration (environment variables)
    MODEL_NAME        sentence-embedding model used for /v1/compare.
                      Default: sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2
                      Set this to the path of the project's fine-tuned model.
    STYLE_MODEL_NAME  model used for /v1/authorship. Defaults to MODEL_NAME.
                      A general sentence model mostly captures topic, so set this
                      to the project's style (triplet-loss) model for real use.
    SIM_THRESHOLD     sentence cosine at or above which a pair is flagged (default 0.80)
    API_KEY           if set, clients must send it in the X-API-Key header
    CORS_ORIGINS      comma-separated list of allowed browser origins (default: none)

Interactive API docs are served at /docs once the server is running.
"""
import os
import re
from functools import lru_cache
from typing import List, Optional

from fastapi import Depends, FastAPI, Header, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

MODEL_NAME = os.getenv("MODEL_NAME", "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2")
STYLE_MODEL_NAME = os.getenv("STYLE_MODEL_NAME", MODEL_NAME)
SIM_THRESHOLD = float(os.getenv("SIM_THRESHOLD", "0.80"))
API_KEY = os.getenv("API_KEY")
SHINGLE = 3
MAX_CHARS = 200_000

app = FastAPI(title="Plagiarism and Authorship API", version="1.0.0")

_origins = [o.strip() for o in os.getenv("CORS_ORIGINS", "").split(",") if o.strip()]
if _origins:
    app.add_middleware(CORSMiddleware, allow_origins=_origins,
                       allow_methods=["GET", "POST"], allow_headers=["*"])


# ---------- models (loaded once, on first use) ----------

@lru_cache(maxsize=2)
def get_encoder(name: str):
    from sentence_transformers import SentenceTransformer
    return SentenceTransformer(name)


def embed(texts: List[str], name: str):
    """Return L2-normalised embeddings as a numpy array (n, d)."""
    return get_encoder(name).encode(texts, normalize_embeddings=True, convert_to_numpy=True)


# ---------- text helpers ----------

_WORD = re.compile(r"\w+(?:['’\-]\w+)*", re.UNICODE)
_SENT = re.compile(r"(?<=[.!?…])\s+|\n+")


def words(text: str) -> List[str]:
    return [w.lower() for w in _WORD.findall(text)]


def sentences(text: str) -> List[str]:
    return [s.strip() for s in _SENT.split(text) if len(s.split()) >= 3]


def shingles(tok: List[str], k: int = SHINGLE) -> set:
    return {" ".join(tok[i:i + k]) for i in range(len(tok) - k + 1)}


def lexical(sub_tok: List[str], src_tok: List[str]):
    """Jaccard on word 3-shingles and share of submission words inside a shared shingle."""
    a, b = shingles(sub_tok), shingles(src_tok)
    inter = a & b
    union = len(a) + len(b) - len(inter)
    covered = [False] * len(sub_tok)
    for i in range(len(sub_tok) - SHINGLE + 1):
        if " ".join(sub_tok[i:i + SHINGLE]) in b:
            for j in range(i, i + SHINGLE):
                covered[j] = True
    jaccard = len(inter) / union if union else 0.0
    containment = sum(covered) / len(sub_tok) if sub_tok else 0.0
    return jaccard, containment


# ---------- schemas ----------

class Source(BaseModel):
    id: str
    text: str = Field(..., max_length=MAX_CHARS)


class CompareRequest(BaseModel):
    submission: str = Field(..., max_length=MAX_CHARS)
    sources: List[Source] = Field(..., min_length=1, max_length=50)
    threshold: Optional[float] = Field(None, ge=0, le=1)


class Candidate(BaseModel):
    id: str
    samples: List[str] = Field(..., min_length=1)


class AuthorshipRequest(BaseModel):
    questioned: str = Field(..., max_length=MAX_CHARS)
    candidates: List[Candidate] = Field(..., min_length=1, max_length=50)


def check_key(x_api_key: Optional[str] = Header(None)):
    if API_KEY and x_api_key != API_KEY:
        raise HTTPException(status_code=401, detail="Missing or wrong X-API-Key header")


# ---------- endpoints ----------

@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_NAME, "style_model": STYLE_MODEL_NAME,
            "threshold": SIM_THRESHOLD}


@app.post("/v1/compare", dependencies=[Depends(check_key)])
def compare(req: CompareRequest):
    thr = SIM_THRESHOLD if req.threshold is None else req.threshold
    sub_sents = sentences(req.submission)
    if not sub_sents:
        raise HTTPException(status_code=422, detail="Submission has no sentence of three or more words")
    sub_tok = words(req.submission)
    sub_emb = embed(sub_sents, MODEL_NAME)

    results = []
    flagged_any = [False] * len(sub_sents)
    for src in req.sources:
        jaccard, containment = lexical(sub_tok, words(src.text))
        src_sents = sentences(src.text)
        matches, best_mean = [], 0.0
        if src_sents:
            sim = sub_emb @ embed(src_sents, MODEL_NAME).T      # cosine: vectors are normalised
            best_idx = sim.argmax(axis=1)
            best = sim.max(axis=1)
            best_mean = float(best.mean())
            for i, (j, s) in enumerate(zip(best_idx, best)):
                if s >= thr:
                    flagged_any[i] = True
                    matches.append({"submission_sentence": sub_sents[i],
                                    "source_sentence": src_sents[int(j)],
                                    "cosine": round(float(s), 4)})
        results.append({
            "source_id": src.id,
            "lexical": {"jaccard": round(jaccard, 4), "containment": round(containment, 4)},
            "semantic": {"mean_best_cosine": round(best_mean, 4),
                         "flagged_share": round(len(matches) / len(sub_sents), 4)},
            "matches": matches,
        })

    results.sort(key=lambda r: r["semantic"]["flagged_share"], reverse=True)
    return {"model": MODEL_NAME, "threshold": thr,
            "submission_sentences": len(sub_sents),
            "overall_flagged_share": round(sum(flagged_any) / len(sub_sents), 4),
            "sources": results}


@app.post("/v1/authorship", dependencies=[Depends(check_key)])
def authorship(req: AuthorshipRequest):
    q_sents = sentences(req.questioned)
    if not q_sents:
        raise HTTPException(status_code=422, detail="Questioned text has no sentence of three or more words")

    def profile(sents: List[str]):
        v = embed(sents, STYLE_MODEL_NAME).mean(axis=0)
        n = float((v @ v) ** 0.5)
        return v / n if n else v

    q = profile(q_sents)
    ranking = []
    for cand in req.candidates:
        c_sents = [s for sample in cand.samples for s in sentences(sample)]
        if not c_sents:
            continue
        ranking.append({"candidate_id": cand.id, "sentences": len(c_sents),
                        "style_cosine": round(float(q @ profile(c_sents)), 4)})
    if not ranking:
        raise HTTPException(status_code=422, detail="No candidate has usable sample text")
    ranking.sort(key=lambda r: r["style_cosine"], reverse=True)
    return {"model": STYLE_MODEL_NAME, "questioned_sentences": len(q_sents), "ranking": ranking}
