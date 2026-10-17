"""Embedder interface. Default is a deterministic hashing embedder (no downloads);
swap in a sentence-transformer by implementing the same `embed` method."""
import hashlib, re
import numpy as np

DIM = 512
_tok = re.compile(r"[\w\u0980-\u09FF]+")  # include Bengali vowel signs


class HashEmbedder:
    name = "hash-512"

    def embed(self, texts: list[str]) -> np.ndarray:
        m = np.zeros((len(texts), DIM), dtype=np.float32)
        for i, t in enumerate(texts):
            words = [w.lower() for w in _tok.findall(t)]
            for w in words:
                m[i, int(hashlib.md5(w.encode()).hexdigest(), 16) % DIM] += 1.0
            for a, b in zip(words, words[1:]):
                m[i, int(hashlib.md5(f"{a} {b}".encode()).hexdigest(), 16) % DIM] += 0.5
            n = np.linalg.norm(m[i])
            if n:
                m[i] /= n
        return m


def get_embedder():
    return HashEmbedder()
