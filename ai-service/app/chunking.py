from dataclasses import dataclass
from .extraction import Page


@dataclass
class Chunk:
    chunk_id: str
    document_id: str
    page: int
    index: int
    text: str


def chunk_pages(document_id: str, pages: list[Page], size: int = 800, overlap: int = 120) -> list[Chunk]:
    """Chunks never cross page boundaries so every chunk has one exact page reference."""
    if overlap >= size:
        raise ValueError("overlap must be smaller than size")
    out: list[Chunk] = []
    for pg in pages:
        t, start, k = pg.text, 0, 0
        while start < len(t):
            piece = t[start:start + size].strip()
            if piece:
                out.append(Chunk(f"{document_id}:p{pg.number}:c{k}", document_id, pg.number, len(out), piece))
                k += 1
            if start + size >= len(t):
                break
            start += size - overlap
    return out
