"""Page-aware document chunker with strict page isolation."""
from dataclasses import dataclass
from typing import List
from app.extraction import ExtractedPage


@dataclass
class DocumentChunk:
    chunk_id: str
    document_id: str
    page_number: int
    chunk_index: int
    char_count: int
    text: str


def chunk_document(
    document_id: str,
    pages: List[ExtractedPage],
    chunk_size: int = 800,
    overlap: int = 120
) -> List[DocumentChunk]:
    """
    Split pages into chunks.
    Chunks never cross page boundaries.
    """
    chunks: List[DocumentChunk] = []

    for page in pages:
        text = page.text.strip()
        if not text:
            continue

        text_len = len(text)
        if text_len <= chunk_size:
            chunk_id = f"{document_id}:p{page.number}:c0"
            chunks.append(DocumentChunk(
                chunk_id=chunk_id,
                document_id=document_id,
                page_number=page.number,
                chunk_index=0,
                char_count=text_len,
                text=text
            ))
            continue

        start = 0
        chunk_idx = 0
        step = max(1, chunk_size - overlap)

        while start < text_len:
            end = min(start + chunk_size, text_len)
            chunk_text = text[start:end].strip()
            
            if chunk_text:
                chunk_id = f"{document_id}:p{page.number}:c{chunk_idx}"
                chunks.append(DocumentChunk(
                    chunk_id=chunk_id,
                    document_id=document_id,
                    page_number=page.number,
                    chunk_index=chunk_idx,
                    char_count=len(chunk_text),
                    text=chunk_text
                ))
                chunk_idx += 1

            if end >= text_len:
                break
            start += step

    return chunks
