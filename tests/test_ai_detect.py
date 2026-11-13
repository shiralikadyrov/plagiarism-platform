"""Tests for /v1/ai-detect. The maths is checked with numpy; the endpoint with a
stub scorer; the model path with two tiny randomly initialised GPT-2 models,
when torch and transformers are installed (they are not needed otherwise)."""
import importlib.util

import numpy as np
import pytest
from fastapi.testclient import TestClient

import server

TEXT = " ".join(f"word{i}" for i in range(120))


def test_binoculars_sums_match_a_direct_computation():
    rng = np.random.default_rng(0)
    L, V = 6, 11
    obs, perf = rng.normal(size=(L, V)), rng.normal(size=(L, V))
    ids = rng.integers(0, V, size=L)
    nll, xent, n = server.binoculars_sums(obs, perf, ids)

    def softmax(x):
        e = np.exp(x - x.max())
        return e / e.sum()
    want_nll = sum(-np.log(softmax(perf[i])[ids[i + 1]]) for i in range(L - 1))
    want_xent = sum(-(softmax(obs[i]) * np.log(softmax(perf[i]))).sum() for i in range(L - 1))
    assert n == L - 1
    assert nll == pytest.approx(want_nll)
    assert xent == pytest.approx(want_xent)


def test_binoculars_sums_handle_different_vocab_sizes():
    obs, perf = np.zeros((4, 10)), np.zeros((4, 12))
    nll, xent, n = server.binoculars_sums(obs, perf, [1, 2, 3, 4])
    assert nll == pytest.approx(3 * np.log(10)) and xent == pytest.approx(3 * np.log(10)) and n == 3


@pytest.fixture
def client(monkeypatch):
    monkeypatch.setattr(server, "API_KEY", None)
    monkeypatch.setattr(server, "AI_DETECTOR", "binoculars")
    monkeypatch.setattr(server, "AI_THRESHOLD", None)

    def fake_segments(text):
        n = len(text.split())
        segs = [(list(range(min(60, n - i))), 0, 10) for i in range(0, n, 60)]
        return segs, n, False
    monkeypatch.setattr(server, "ai_segments", fake_segments)
    # Segment score 0.8 for the first segment, 1.2 for the rest.
    calls = []

    def fake_score(ids):
        calls.append(len(ids))
        r = 0.8 if len(calls) == 1 else 1.2
        return r * len(ids), float(len(ids)), len(ids)
    monkeypatch.setattr(server, "score_segment", fake_score)
    return TestClient(server.app)


def test_uncalibrated_returns_scores_without_a_flag(client):
    r = client.post("/v1/ai-detect", json={"text": TEXT})
    assert r.status_code == 200
    body = r.json()
    assert body["method"] == "binoculars" and body["calibrated"] is False
    assert body["flagged"] is None and all(s["flagged"] is None for s in body["segments"])
    assert [s["score"] for s in body["segments"]] == [0.8, 1.2]
    assert body["score"] == pytest.approx(1.0)                       # token-weighted
    assert body["direction"] == "lower_is_more_ai_like"


def test_calibrated_threshold_flags_low_scores(client, monkeypatch):
    monkeypatch.setattr(server, "AI_THRESHOLD", 0.9)
    body = client.post("/v1/ai-detect", json={"text": TEXT}).json()
    assert [s["flagged"] for s in body["segments"]] == [True, False]
    assert body["flagged"] is False and body["calibrated"] is True


def test_short_text_and_switched_off(client, monkeypatch):
    assert client.post("/v1/ai-detect", json={"text": "Too short."}).status_code == 422
    assert client.post("/v1/ai-detect", json={"text": "word " * 40}).status_code == 422   # under 64 tokens
    monkeypatch.setattr(server, "AI_DETECTOR", "off")
    assert client.post("/v1/ai-detect", json={"text": TEXT}).status_code == 404
    assert client.get("/health").json()["ai_detector"] == {"method": "off"}


def test_needs_the_api_key(client, monkeypatch):
    monkeypatch.setattr(server, "API_KEY", "k")
    assert client.post("/v1/ai-detect", json={"text": TEXT}).status_code == 401
    assert client.post("/v1/ai-detect", json={"text": TEXT}, headers={"X-API-Key": "k"}).status_code == 200


def test_missing_models_give_503(monkeypatch):
    monkeypatch.setattr(server, "API_KEY", None)
    monkeypatch.setattr(server, "AI_DETECTOR", "binoculars")

    def broken(text):
        raise OSError("model not found")
    monkeypatch.setattr(server, "ai_segments", broken)
    r = TestClient(server.app).post("/v1/ai-detect", json={"text": TEXT})
    assert r.status_code == 503 and "model not found" in r.json()["detail"]


needs_torch = pytest.mark.skipif(
    not (importlib.util.find_spec("torch") and importlib.util.find_spec("transformers")),
    reason="torch and transformers not installed")


@needs_torch
def test_real_model_path_with_tiny_random_models(tmp_path, monkeypatch):
    """Runs the full model path, offline, with two tiny GPT-2 models that share a tokenizer."""
    import torch
    from tokenizers import Tokenizer, models, pre_tokenizers
    from transformers import GPT2Config, GPT2LMHeadModel, PreTrainedTokenizerFast

    words = sorted({w for w in TEXT.split()} | {"[UNK]"})
    tk = Tokenizer(models.WordLevel({w: i for i, w in enumerate(words)}, unk_token="[UNK]"))
    tk.pre_tokenizer = pre_tokenizers.WhitespaceSplit()
    fast = PreTrainedTokenizerFast(tokenizer_object=tk, unk_token="[UNK]")
    for name, seed in (("obs", 1), ("perf", 2)):
        torch.manual_seed(seed)
        GPT2LMHeadModel(GPT2Config(vocab_size=len(words), n_positions=256, n_embd=16, n_layer=1, n_head=2)) \
            .save_pretrained(tmp_path / name)
        fast.save_pretrained(tmp_path / name)

    monkeypatch.setattr(server, "API_KEY", None)
    monkeypatch.setattr(server, "AI_DETECTOR", "binoculars")
    monkeypatch.setattr(server, "AI_OBSERVER_MODEL", str(tmp_path / "obs"))
    monkeypatch.setattr(server, "AI_PERFORMER_MODEL", str(tmp_path / "perf"))
    monkeypatch.setattr(server, "AI_SEGMENT_TOKENS", 50)
    server.get_ai_models.cache_clear()
    try:
        body = TestClient(server.app).post("/v1/ai-detect", json={"text": TEXT}).json()
        # 120 tokens in segments of 50: 50, 50, and a 20-token tail joined to the second.
        assert [s["tokens"] for s in body["segments"]] == [50, 70]
        assert body["tokens"] == 120 and body["segments"][-1]["end"] == len(TEXT)
        assert body["segments"][1]["start"] == TEXT.index("word50")
        assert all(np.isfinite(s["score"]) and s["score"] > 0 for s in body["segments"])
        # Same text, same models: the score is deterministic.
        again = TestClient(server.app).post("/v1/ai-detect", json={"text": TEXT}).json()
        assert again["score"] == body["score"]
    finally:
        server.get_ai_models.cache_clear()
