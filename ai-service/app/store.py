"""In-memory vector store. Every query is filtered by caller-supplied authorized document
ids BEFORE scoring. Reindex atomically replaces old chunks (no duplicates)."""
import os, pickle, threading
import numpy as np
from .chunking import Chunk
from .config import settings


class VectorStore:
    def __init__(self, embedder):
        self.embedder, self._lock = embedder, threading.Lock()
        self.chunks: dict[str, list[Chunk]] = {}
        self.vecs: dict[str, np.ndarray] = {}
        self.meta: dict[str, dict] = {}
        self.pages: dict[str, list] = {}
        self._file = os.path.join(settings.data_dir, "index.pkl") if settings.data_dir else None
        self._load()

    def _load(self):
        # Local, trusted file written only by this service. Never load pickles from untrusted sources.
        if self._file and os.path.exists(self._file):
            try:
                with open(self._file, "rb") as f:
                    self.chunks, self.vecs, self.meta, self.pages = pickle.load(f)
            except Exception:
                pass

    def _save(self):
        if self._file:
            os.makedirs(os.path.dirname(self._file), exist_ok=True)
            tmp = self._file + ".tmp"
            with open(tmp, "wb") as f:
                pickle.dump((self.chunks, self.vecs, self.meta, self.pages), f)
            os.replace(tmp, self._file)

    def index(self, document_id: str, chunks: list[Chunk], meta: dict, pages=None):
        vecs = self.embedder.embed([c.text for c in chunks]) if chunks else np.zeros((0, 1))
        with self._lock:
            self.chunks[document_id], self.vecs[document_id], self.meta[document_id] = chunks, vecs, meta
            self.pages[document_id] = pages or []
            self._save()

    def delete(self, document_id: str):
        with self._lock:
            for d in (self.chunks, self.vecs, self.meta, self.pages):
                d.pop(document_id, None)
            self._save()

    def search(self, query: str, authorized_ids: list[str], top_k: int = 5):
        allowed = [d for d in dict.fromkeys(authorized_ids) if d in self.chunks]
        if not allowed:
            return []
        q = self.embedder.embed([query])[0]
        hits = []
        for d in allowed:
            if len(self.vecs[d]) == 0:
                continue
            scores = self.vecs[d] @ q
            for c, s in zip(self.chunks[d], scores):
                if s > 0:
                    hits.append((float(s), c, self.meta[d]))
        hits.sort(key=lambda h: -h[0])
        return hits[:top_k]

    def get_chunk(self, document_id: str, chunk_id: str):
        return next((c for c in self.chunks.get(document_id, []) if c.chunk_id == chunk_id), None)

    def related(self, document_id: str, authorized_ids: list[str], top_k: int = 5, min_sim: float = 0.15):
        """Document-level similarity (mean chunk vector). Only authorized ids are compared."""
        if document_id not in self.vecs or len(self.vecs[document_id]) == 0:
            return []
        def centroid(d):
            v = self.vecs[d].mean(axis=0); n = np.linalg.norm(v)
            return v / n if n else v
        base, mine = centroid(document_id), self.meta[document_id]
        out = []
        for d in dict.fromkeys(authorized_ids):
            if d == document_id or d not in self.vecs or len(self.vecs[d]) == 0:
                continue
            sim = float(base @ centroid(d))
            if sim < min_sim:
                continue
            other = self.meta[d]
            shared = sorted(set(mine.get("keywords", [])) & set(other.get("keywords", [])))
            why = []
            if shared:
                why.append("Shared key terms: " + ", ".join(shared))
            if mine.get("category") and mine.get("category") == other.get("category") and mine["category"] != "Other":
                why.append("Same predicted category: " + mine["category"])
            out.append({"document_id": d, "title": other.get("title"), "category": other.get("category"),
                        "score": round(sim, 3), "explanation": "; ".join(why) or None,
                        "snippet": self.chunks[d][0].text[:200]})
        return sorted(out, key=lambda r: -r["score"])[:top_k]
