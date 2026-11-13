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
    ENCODE_BATCH      sentences encoded per batch (default 32). Lower it if the
                      GPU runs out of memory.
    MAX_SENT_WORDS    a sentence longer than this is split into parts, so that
                      no text is cut off by the encoder's length limit (default 60)

  AI-text estimate (/v1/ai-detect)
    AI_DETECTOR       binoculars (default), classifier, or off
    AI_OBSERVER_MODEL, AI_PERFORMER_MODEL
                      the two causal language models for the Binoculars score.
                      They must share a tokenizer. Defaults: Qwen/Qwen2.5-1.5B and
                      Qwen/Qwen2.5-1.5B-Instruct. Use the 0.5B pair on a CPU.
    AI_CLASSIFIER_MODEL
                      a sequence-classification model, for example the project's
                      own detector from Objective 1, when AI_DETECTOR=classifier
    AI_THRESHOLD      calibrated decision threshold. Without it the endpoint
                      returns scores but no flag, because an uncalibrated score
                      cannot be read as a decision.
    AI_SEGMENT_TOKENS tokens per scored segment (default 512)
    AI_MAX_SEGMENTS   segments scored per request (default 40)

Interactive API docs are served at /docs once the server is running.
"""
import hmac
import os
import re
from functools import lru_cache
from typing import Annotated, List, Optional

from fastapi import Depends, FastAPI, Header, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

MODEL_NAME = os.getenv("MODEL_NAME", "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2")
STYLE_MODEL_NAME = os.getenv("STYLE_MODEL_NAME", MODEL_NAME)
SIM_THRESHOLD = float(os.getenv("SIM_THRESHOLD", "0.80"))
API_KEY = os.getenv("API_KEY")
ENCODE_BATCH = int(os.getenv("ENCODE_BATCH", "32"))
MAX_SENT_WORDS = int(os.getenv("MAX_SENT_WORDS", "60"))
SHINGLE = 3
MAX_CHARS = 200_000
MAX_SAMPLES = 50
VERSION = "1.0.0"

AI_DETECTOR = os.getenv("AI_DETECTOR", "binoculars").strip().lower()
AI_OBSERVER_MODEL = os.getenv("AI_OBSERVER_MODEL", "Qwen/Qwen2.5-1.5B")
AI_PERFORMER_MODEL = os.getenv("AI_PERFORMER_MODEL", "Qwen/Qwen2.5-1.5B-Instruct")
AI_CLASSIFIER_MODEL = os.getenv("AI_CLASSIFIER_MODEL", "")
AI_THRESHOLD = float(os.environ["AI_THRESHOLD"]) if os.getenv("AI_THRESHOLD", "").strip() else None
AI_SEGMENT_TOKENS = int(os.getenv("AI_SEGMENT_TOKENS", "512"))
AI_MAX_SEGMENTS = int(os.getenv("AI_MAX_SEGMENTS", "40"))
AI_MIN_TOKENS = 64

app = FastAPI(title="Plagiarism and Authorship API", version=VERSION)

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
    return get_encoder(name).encode(texts, batch_size=ENCODE_BATCH, normalize_embeddings=True,
                                    convert_to_numpy=True, show_progress_bar=False)


# ---------- text helpers ----------

_WORD = re.compile(r"\w+(?:['’\-]\w+)*", re.UNICODE)
_SENT = re.compile(r"(?<=[.!?…])\s+|\n+")


def words(text: str) -> List[str]:
    return [w.lower() for w in _WORD.findall(text)]


def sentences(text: str) -> List[str]:
    """Split into sentences of three or more words.

    A sentence longer than MAX_SENT_WORDS (a list, a table, text without
    punctuation) is cut into consecutive parts. Encoders silently drop
    everything past their length limit, so without this the tail of a long
    sentence would never be compared.
    """
    out = []
    for s in _SENT.split(text):
        w = s.split()
        if len(w) < 3:
            continue
        if len(w) <= MAX_SENT_WORDS:
            out.append(s.strip())
            continue
        parts = [w[i:i + MAX_SENT_WORDS] for i in range(0, len(w), MAX_SENT_WORDS)]
        if len(parts) > 1 and len(parts[-1]) < 3:      # do not leave a stub
            parts[-2].extend(parts.pop())
        out.extend(" ".join(p) for p in parts)
    return out


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
    samples: List[Annotated[str, Field(max_length=MAX_CHARS)]] = Field(
        ..., min_length=1, max_length=MAX_SAMPLES)


class AuthorshipRequest(BaseModel):
    questioned: str = Field(..., max_length=MAX_CHARS)
    candidates: List[Candidate] = Field(..., min_length=1, max_length=50)


def check_key(x_api_key: Optional[str] = Header(None)):
    if API_KEY and not hmac.compare_digest((x_api_key or "").encode(), API_KEY.encode()):
        raise HTTPException(status_code=401, detail="Missing or wrong X-API-Key header")


# ---------- endpoints ----------

@app.get("/health")
def health():
    return {"status": "ok", "version": VERSION, "model": MODEL_NAME,
            "style_model": STYLE_MODEL_NAME, "threshold": SIM_THRESHOLD,
            "key_required": bool(API_KEY),
            "ai_detector": ai_detector_info(),
            "limits": {"max_chars": MAX_CHARS, "max_sources": 50}}


@app.get("/v1/ping", dependencies=[Depends(check_key)])
def ping():
    """Checks the API key without loading a model. Used by connectors' "test connection"."""
    return {"status": "ok"}


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


# ---------- AI-generated text estimate ----------
#
# Binoculars (Hans et al., 2024, "Spotting LLMs With Binoculars: Zero-Shot
# Detection of Machine-Generated Text", ICML). Two related language models read
# the text. The score divides how surprising the actual tokens are to the
# performer model by how surprising the observer's predictions are to the
# performer (cross-entropy). Text written by a language model is unsurprising
# relative to what language models expect, so it gets a LOW score.
#
# The score depends on the model pair, the language and the genre. It must be
# calibrated on texts with known origin before it is used as a decision, which
# is why AI_THRESHOLD has no default.

class AIDetectRequest(BaseModel):
    text: str = Field(..., max_length=MAX_CHARS)


def ai_detector_info():
    if AI_DETECTOR == "off":
        return {"method": "off"}
    if AI_DETECTOR == "classifier":
        return {"method": "classifier", "model": AI_CLASSIFIER_MODEL,
                "threshold": AI_THRESHOLD, "calibrated": AI_THRESHOLD is not None,
                "direction": "higher_is_more_ai_like"}
    return {"method": "binoculars", "observer": AI_OBSERVER_MODEL, "performer": AI_PERFORMER_MODEL,
            "threshold": AI_THRESHOLD, "calibrated": AI_THRESHOLD is not None,
            "direction": "lower_is_more_ai_like"}


def _log_softmax(x):
    import numpy as np
    x = x - x.max(axis=-1, keepdims=True)
    return x - np.log(np.exp(x).sum(axis=-1, keepdims=True))


def binoculars_sums(observer_logits, performer_logits, ids):
    """Per-segment sums for the Binoculars score.

    observer_logits, performer_logits: (L, V) arrays, row i predicts token i + 1.
    ids: (L,) token ids of the segment.
    Returns (sum of negative log-likelihood under the performer,
             sum of cross-entropy of the performer against the observer's distribution,
             number of predicted tokens).
    """
    import numpy as np
    v = min(observer_logits.shape[-1], performer_logits.shape[-1])
    obs = observer_logits[:-1, :v].astype(np.float64)
    perf = performer_logits[:-1, :v].astype(np.float64)
    target = np.asarray(ids[1:])
    perf_lp = _log_softmax(perf)
    nll = -perf_lp[np.arange(len(target)), target]
    obs_p = np.exp(_log_softmax(obs))
    xent = -(obs_p * perf_lp).sum(axis=-1)
    return float(nll.sum()), float(xent.sum()), int(len(target))


@lru_cache(maxsize=1)
def get_ai_models():
    import torch
    from transformers import (AutoModelForCausalLM, AutoModelForSequenceClassification,
                              AutoTokenizer)
    device = "cuda" if torch.cuda.is_available() else "cpu"
    dtype = torch.bfloat16 if device == "cuda" else torch.float32
    if AI_DETECTOR == "classifier":
        if not AI_CLASSIFIER_MODEL:
            raise RuntimeError("AI_DETECTOR=classifier needs AI_CLASSIFIER_MODEL")
        tok = AutoTokenizer.from_pretrained(AI_CLASSIFIER_MODEL)
        model = AutoModelForSequenceClassification.from_pretrained(AI_CLASSIFIER_MODEL, torch_dtype=dtype)
        return {"tok": tok, "clf": model.to(device).eval(), "device": device}
    tok = AutoTokenizer.from_pretrained(AI_OBSERVER_MODEL)
    perf_tok = AutoTokenizer.from_pretrained(AI_PERFORMER_MODEL)
    if tok.get_vocab() != perf_tok.get_vocab():
        raise RuntimeError("AI_OBSERVER_MODEL and AI_PERFORMER_MODEL must use the same tokenizer")
    obs = AutoModelForCausalLM.from_pretrained(AI_OBSERVER_MODEL, torch_dtype=dtype).to(device).eval()
    perf = AutoModelForCausalLM.from_pretrained(AI_PERFORMER_MODEL, torch_dtype=dtype).to(device).eval()
    return {"tok": tok, "obs": obs, "perf": perf, "device": device}


def _ai_class_index(model):
    labels = {i: str(n).lower() for i, n in (model.config.id2label or {}).items()}
    for i, name in labels.items():
        if re.search(r"\b(ai|machine|generated|fake|llm|gpt)\b", name):
            return int(i)
    return 1


def ai_segments(text: str):
    """Tokenise and cut into segments. Returns (segments, total_tokens, truncated).

    Each segment is (token ids, start character, end character).
    """
    tok = get_ai_models()["tok"]
    enc = tok(text, add_special_tokens=False, return_offsets_mapping=True)
    ids, offs = enc["input_ids"], enc["offset_mapping"]
    segs = []
    for i in range(0, len(ids), AI_SEGMENT_TOKENS):
        chunk = ids[i:i + AI_SEGMENT_TOKENS]
        end = offs[min(i + AI_SEGMENT_TOKENS, len(ids)) - 1][1]
        if len(chunk) < 32 and segs:          # a short tail is added to the previous segment
            prev_ids, prev_start, _ = segs.pop()
            segs.append((prev_ids + chunk, prev_start, end))
            break
        segs.append((chunk, offs[i][0], end))
    truncated = len(segs) > AI_MAX_SEGMENTS
    return segs[:AI_MAX_SEGMENTS], len(ids), truncated


def score_segment(ids):
    """Return (numerator, denominator, weight) for one segment. score = numerator / denominator."""
    import torch
    m = get_ai_models()
    x = torch.tensor([ids], device=m["device"])
    with torch.no_grad():
        if "clf" in m:
            probs = torch.softmax(m["clf"](input_ids=x).logits.float(), dim=-1)[0]
            p = float(probs[_ai_class_index(m["clf"])])
            return p * len(ids), float(len(ids)), len(ids)
        obs = m["obs"](input_ids=x).logits[0].float().cpu().numpy()
        perf = m["perf"](input_ids=x).logits[0].float().cpu().numpy()
    return binoculars_sums(obs, perf, ids)


@app.post("/v1/ai-detect", dependencies=[Depends(check_key)])
def ai_detect(req: AIDetectRequest):
    if AI_DETECTOR == "off":
        raise HTTPException(status_code=404, detail="AI-text estimate is switched off on this server")
    if len(req.text.split()) < 3:
        raise HTTPException(status_code=422, detail="Text is too short for an AI-text estimate")
    try:
        segs, total, truncated = ai_segments(req.text)
    except (ImportError, OSError, RuntimeError) as e:
        raise HTTPException(status_code=503, detail=f"AI-text models are not available: {e}") from e
    if total < AI_MIN_TOKENS:
        raise HTTPException(status_code=422,
                            detail=f"Text is too short for an AI-text estimate (at least {AI_MIN_TOKENS} tokens, "
                                   "about 50 words)")
    info = ai_detector_info()
    lower_is_ai = info["direction"] == "lower_is_more_ai_like"

    def flag(score):
        if AI_THRESHOLD is None:
            return None
        return score < AI_THRESHOLD if lower_is_ai else score > AI_THRESHOLD

    num = den = 0.0
    out = []
    for ids, start, end in segs:
        a, b, n = score_segment(ids)
        num, den = num + a, den + b
        sc = a / b if b else 0.0
        out.append({"start": start, "end": end, "tokens": len(ids), "score": round(sc, 4), "flagged": flag(sc)})
    score = num / den if den else 0.0
    return {**info, "score": round(score, 4), "flagged": flag(score),
            "tokens": total, "scored_tokens": sum(s["tokens"] for s in out),
            "truncated": truncated, "segments": out}
