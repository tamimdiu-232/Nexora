"""Lexical hashing embedder and embedding interfaces."""
import hashlib
from abc import ABC, abstractmethod
from typing import List
import numpy as np

from app.textutil import stem, tokenize


class Embedder(ABC):
    """Abstract interface for text embedding models."""

    @abstractmethod
    def embed(self, texts: List[str]) -> np.ndarray:
        """Generate normalized embedding vectors for a list of texts."""
        pass


class HashingEmbedder(Embedder):
    """
    Deterministic lexical feature hashing embedder over stemmed unigrams and bigrams.
    Provides fast, reproducible, offline retrieval with zero external dependencies.
    Note: Lexical feature hashing captures exact tokens, stems, and local bigrams;
    it is not a dense neural semantic representation.
    """

    def __init__(self, dimension: int = 512):
        self.dimension = dimension

    def _hash_token(self, token: str) -> int:
        # Use md5 for uniform distribution across dimension buckets
        h = hashlib.md5(token.encode("utf-8")).digest()
        val = int.from_bytes(h[:4], byteorder="big", signed=False)
        return val % self.dimension

    def embed_single(self, text: str) -> np.ndarray:
        vec = np.zeros(self.dimension, dtype=np.float32)
        tokens = tokenize(text, lowercase=True, remove_stopwords=False)
        if not tokens:
            return vec

        stemmed = [stem(t) for t in tokens]

        # 1. Unigrams
        for st in stemmed:
            bucket = self._hash_token(st)
            vec[bucket] += 1.0

        # 2. Bigrams
        for i in range(len(stemmed) - 1):
            bigram = f"{stemmed[i]}_{stemmed[i+1]}"
            bucket = self._hash_token(bigram)
            vec[bucket] += 1.5

        # L2 normalization so cosine similarity is simply the inner product
        norm = np.linalg.norm(vec)
        if norm > 0:
            vec = vec / norm
        return vec

    def embed(self, texts: List[str]) -> np.ndarray:
        if not texts:
            return np.empty((0, self.dimension), dtype=np.float32)
        vectors = [self.embed_single(t) for t in texts]
        return np.vstack(vectors)


# Global default embedder instance
default_embedder = HashingEmbedder(dimension=512)
