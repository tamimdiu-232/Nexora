"""Thread-safe in-memory vector store with pre-filtering and persistence."""
import os
import pickle
import threading
from dataclasses import dataclass
from typing import Any, Dict, List, Optional, Set
import numpy as np

from app.chunking import DocumentChunk
from app.embeddings import default_embedder


@dataclass
class StoredChunk:
    chunk_id: str
    document_id: str
    page_number: int
    chunk_index: int
    char_count: int
    text: str
    vector: np.ndarray


@dataclass
class DocumentIndexMeta:
    document_id: str
    title: str
    category: str
    keywords: List[str]
    chunk_ids: List[str]
    centroid: Optional[np.ndarray] = None


class VectorStore:
    def __init__(self):
        self._lock = threading.RLock()
        self.chunks: Dict[str, StoredChunk] = {}
        self.documents: Dict[str, DocumentIndexMeta] = {}

    def add_or_replace_document(
        self,
        document_id: str,
        title: str,
        category: str,
        keywords: List[str],
        chunks: List[DocumentChunk]
    ) -> None:
        """
        Atomically replace all chunks and metadata for a document.
        Ensures no duplicate chunks on reprocessing.
        """
        with self._lock:
            # 1. Remove existing chunks if re-processing
            self.delete_document(document_id)

            if not chunks:
                self.documents[document_id] = DocumentIndexMeta(
                    document_id=document_id,
                    title=title,
                    category=category,
                    keywords=keywords,
                    chunk_ids=[],
                    centroid=None
                )
                return

            # 2. Compute embeddings
            texts = [c.text for c in chunks]
            vectors = default_embedder.embed(texts)

            chunk_ids = []
            chunk_vectors = []

            for chunk, vec in zip(chunks, vectors):
                stored = StoredChunk(
                    chunk_id=chunk.chunk_id,
                    document_id=document_id,
                    page_number=chunk.page_number,
                    chunk_index=chunk.chunk_index,
                    char_count=chunk.char_count,
                    text=chunk.text,
                    vector=vec
                )
                self.chunks[chunk.chunk_id] = stored
                chunk_ids.append(chunk.chunk_id)
                chunk_vectors.append(vec)

            # 3. Calculate document centroid for related-documents matching
            centroid = np.mean(np.vstack(chunk_vectors), axis=0)
            c_norm = np.linalg.norm(centroid)
            if c_norm > 0:
                centroid = centroid / c_norm

            self.documents[document_id] = DocumentIndexMeta(
                document_id=document_id,
                title=title,
                category=category,
                keywords=keywords,
                chunk_ids=chunk_ids,
                centroid=centroid
            )

    def delete_document(self, document_id: str) -> bool:
        """Delete document metadata and all associated chunks."""
        with self._lock:
            meta = self.documents.pop(document_id, None)
            if meta:
                for c_id in meta.chunk_ids:
                    self.chunks.pop(c_id, None)
                return True
            # Also clean up any stray chunks with this document_id
            stray_ids = [c_id for c_id, c in self.chunks.items() if c.document_id == document_id]
            for c_id in stray_ids:
                self.chunks.pop(c_id, None)
            return len(stray_ids) > 0

    def search(
        self,
        query: str,
        authorized_document_ids: List[str],
        top_k: int = 20
    ) -> List[Dict[str, Any]]:
        """
        Search vector index.
        CRITICAL: Filter chunks by authorized_document_ids BEFORE scoring.
        """
        with self._lock:
            if not authorized_document_ids or not query.strip():
                return []

            auth_set = set(authorized_document_ids)

            # 1. Pre-filter chunks belonging ONLY to authorized documents
            eligible_chunks = [
                c for c in self.chunks.values()
                if c.document_id in auth_set
            ]

            if not eligible_chunks:
                return []

            # 2. Embed query vector
            query_vec = default_embedder.embed_single(query)
            q_norm = np.linalg.norm(query_vec)
            if q_norm == 0:
                return []

            # 3. Score candidate chunks
            results = []
            for chunk in eligible_chunks:
                # Cosine similarity (vectors are pre-normalized)
                score = float(np.dot(query_vec, chunk.vector))
                if score > 0.05:
                    results.append({
                        "document_id": chunk.document_id,
                        "chunk_id": chunk.chunk_id,
                        "page_number": chunk.page_number,
                        "score": round(score, 4),
                        "snippet": chunk.text[:300] + ("..." if len(chunk.text) > 300 else "")
                    })

            # Sort by score descending
            results.sort(key=lambda x: x["score"], reverse=True)
            return results[:top_k]

    def get_chunks_for_qa(
        self,
        query: str,
        authorized_document_ids: List[str],
        top_k: int = 8
    ) -> List[StoredChunk]:
        """Retrieve top ranked StoredChunk objects for QA within authorized scope."""
        with self._lock:
            if not authorized_document_ids:
                return []

            auth_set = set(authorized_document_ids)
            eligible_chunks = [c for c in self.chunks.values() if c.document_id in auth_set]
            if not eligible_chunks:
                return []

            query_vec = default_embedder.embed_single(query)
            scored = []
            for c in eligible_chunks:
                score = float(np.dot(query_vec, c.vector))
                scored.append((score, c))

            scored.sort(key=lambda x: x[0], reverse=True)
            return [c for _, c in scored[:top_k]]

    def find_related_documents(
        self,
        document_id: str,
        authorized_document_ids: List[str],
        top_k: int = 5
    ) -> List[Dict[str, Any]]:
        """
        Find related documents using centroid cosine similarity and metadata overlap.
        Only considers authorized document IDs.
        """
        with self._lock:
            if document_id not in self.documents:
                return []

            target = self.documents[document_id]
            if target.centroid is None:
                return []

            auth_set = set(authorized_document_ids)
            if document_id in auth_set:
                auth_set.remove(document_id)

            candidates = [
                meta for meta in self.documents.values()
                if meta.document_id in auth_set and meta.centroid is not None
            ]

            results = []
            target_keywords = set(target.keywords)

            for cand in candidates:
                sim = float(np.dot(target.centroid, cand.centroid))
                cand_keywords = set(cand.keywords)
                shared_kw = list(target_keywords.intersection(cand_keywords))
                same_cat = (target.category == cand.category)

                reasons = []
                if same_cat and cand.category != "Other":
                    reasons.append(f"Same category ({cand.category})")
                if shared_kw:
                    reasons.append(f"Shared topics: {', '.join(shared_kw[:3])}")
                if not reasons:
                    reasons.append("High text similarity")

                results.append({
                    "document_id": cand.document_id,
                    "title": cand.title,
                    "category": cand.category,
                    "similarity": round(max(0.0, sim), 4),
                    "shared_keywords": shared_kw[:5],
                    "explanation": "; ".join(reasons)
                })

            results.sort(key=lambda x: x["similarity"], reverse=True)
            return results[:top_k]

    def index_health(self) -> Dict[str, Any]:
        """Diagnostic health metrics for vector store."""
        with self._lock:
            empty_docs = sum(1 for m in self.documents.values() if not m.chunk_ids)
            total_chunks = len(self.chunks)
            total_docs = len(self.documents)
            
            # Check for orphaned chunks
            registered_chunks = {cid for m in self.documents.values() for cid in m.chunk_ids}
            orphaned = sum(1 for cid in self.chunks if cid not in registered_chunks)

            return {
                "indexed_documents": total_docs,
                "total_chunks": total_chunks,
                "empty_documents": empty_docs,
                "orphaned_chunks": orphaned,
                "healthy": (orphaned == 0)
            }

    def save(self, filepath: str) -> None:
        """Persist vector index to a local pickle file."""
        with self._lock:
            dir_name = os.path.dirname(filepath)
            if dir_name:
                os.makedirs(dir_name, exist_ok=True)
            with open(filepath, "wb") as f:
                pickle.dump({"chunks": self.chunks, "documents": self.documents}, f)

    def load(self, filepath: str) -> bool:
        """Load vector index from local pickle file."""
        if not os.path.exists(filepath):
            return False
        with self._lock:
            try:
                with open(filepath, "rb") as f:
                    data = pickle.load(f)
                    self.chunks = data.get("chunks", {})
                    self.documents = data.get("documents", {})
                return True
            except Exception:
                return False


# Global vector store instance
global_vector_store = VectorStore()
