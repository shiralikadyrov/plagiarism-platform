"""Tests for the reference API. They replace the encoder with a small
bag-of-words stub, so they run in seconds without a GPU or a model download."""
import hashlib

import numpy as np
import pytest
from fastapi.testclient import TestClient

import server

DIM = 256


def stub_embed(texts, name):
    out = np.zeros((len(texts), DIM), dtype=np.float32)
    for i, t in enumerate(texts):
        for w in server.words(t):
            out[i, int(hashlib.md5(w.encode()).hexdigest(), 16) % DIM] += 1.0
        n = np.linalg.norm(out[i])
        if n:
            out[i] /= n
    return out


@pytest.fixture
def client(monkeypatch):
    monkeypatch.setattr(server, "embed", stub_embed)
    monkeypatch.setattr(server, "API_KEY", None)
    return TestClient(server.app)


COPIED = "Contextual embeddings give each word a vector that depends on the sentence."
OTHER = "The harvest was late that year because the spring rains never came."


def test_health(client):
    r = client.get("/health")
    assert r.status_code == 200
    body = r.json()
    assert body["status"] == "ok"
    assert body["threshold"] == server.SIM_THRESHOLD
    assert body["key_required"] is False


def test_compare_flags_copied_sentence_only(client):
    r = client.post("/v1/compare", json={
        "submission": COPIED + " " + OTHER,
        "sources": [{"id": "a", "text": COPIED}, {"id": "b", "text": "Bread is baked from flour and water."}],
    })
    assert r.status_code == 200
    body = r.json()
    assert body["submission_sentences"] == 2
    assert body["overall_flagged_share"] == 0.5
    assert body["sources"][0]["source_id"] == "a"          # sorted by flagged share
    assert body["sources"][0]["matches"][0]["submission_sentence"] == COPIED
    assert body["sources"][0]["lexical"]["containment"] > 0.4
    assert body["sources"][1]["matches"] == []


def test_compare_threshold_override(client):
    payload = {"submission": COPIED, "sources": [{"id": "a", "text": OTHER}]}
    assert client.post("/v1/compare", json=payload).json()["overall_flagged_share"] == 0.0
    r = client.post("/v1/compare", json={**payload, "threshold": 0.0})
    assert r.json()["overall_flagged_share"] == 1.0
    assert r.json()["threshold"] == 0.0


def test_compare_rejects_empty_and_short_text(client):
    for sub in ("", "Too short."):
        r = client.post("/v1/compare", json={"submission": sub, "sources": [{"id": "a", "text": COPIED}]})
        assert r.status_code == 422


def test_compare_rejects_missing_sources(client):
    assert client.post("/v1/compare", json={"submission": COPIED, "sources": []}).status_code == 422


def test_compare_rejects_text_over_limit(client):
    big = "word " * (server.MAX_CHARS // 5 + 1)
    r = client.post("/v1/compare", json={"submission": big, "sources": [{"id": "a", "text": COPIED}]})
    assert r.status_code == 422
    r = client.post("/v1/compare", json={"submission": COPIED, "sources": [{"id": "a", "text": big}]})
    assert r.status_code == 422


def test_compare_handles_source_without_sentences(client):
    r = client.post("/v1/compare", json={"submission": COPIED, "sources": [{"id": "a", "text": "n/a"}]})
    assert r.status_code == 200
    assert r.json()["sources"][0]["semantic"]["flagged_share"] == 0.0


def test_api_key(client, monkeypatch):
    monkeypatch.setattr(server, "API_KEY", "secret")
    payload = {"submission": COPIED, "sources": [{"id": "a", "text": COPIED}]}
    assert client.post("/v1/compare", json=payload).status_code == 401
    assert client.post("/v1/compare", json=payload, headers={"X-API-Key": "wrong"}).status_code == 401
    assert client.post("/v1/compare", json=payload, headers={"X-API-Key": "secret"}).status_code == 200
    assert client.get("/v1/ping").status_code == 401
    assert client.get("/v1/ping", headers={"X-API-Key": "secret"}).status_code == 200
    assert client.get("/health").status_code == 200          # health stays open
    assert client.get("/health").json()["key_required"] is True


def test_authorship_ranks_closest_first(client):
    r = client.post("/v1/authorship", json={
        "questioned": COPIED,
        "candidates": [
            {"id": "far", "samples": [OTHER]},
            {"id": "near", "samples": [COPIED, "Embeddings depend on the sentence around each word."]},
            {"id": "empty", "samples": ["n/a"]},
        ],
    })
    assert r.status_code == 200
    ranking = r.json()["ranking"]
    assert [c["candidate_id"] for c in ranking] == ["near", "far"]     # "empty" is dropped
    assert ranking[0]["style_cosine"] > ranking[1]["style_cosine"]


def test_authorship_rejects_unusable_input(client):
    r = client.post("/v1/authorship", json={"questioned": COPIED, "candidates": [{"id": "x", "samples": ["n/a"]}]})
    assert r.status_code == 422
    r = client.post("/v1/authorship", json={"questioned": "No.", "candidates": [{"id": "x", "samples": [COPIED]}]})
    assert r.status_code == 422


def test_long_sentence_is_split_and_nothing_is_lost(monkeypatch):
    monkeypatch.setattr(server, "MAX_SENT_WORDS", 10)
    text = " ".join(f"w{i}" for i in range(31))               # one "sentence" of 31 words
    parts = server.sentences(text)
    assert [len(p.split()) for p in parts] == [10, 10, 11]    # the 1-word tail joins the last part
    assert " ".join(parts) == text


def test_lexical_scores():
    a = server.words("one two three four five six")
    assert server.lexical(a, a) == (1.0, 1.0)
    assert server.lexical(a, server.words("seven eight nine ten")) == (0.0, 0.0)
    assert server.lexical([], a) == (0.0, 0.0)
