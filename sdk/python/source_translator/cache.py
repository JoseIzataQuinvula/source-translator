import hashlib
import json
import time
from pathlib import Path
from typing import Optional
from dataclasses import dataclass, asdict


@dataclass
class CacheEntry:
    text_hash: str
    source_lang: str
    target_lang: str
    original_text: str
    translated_text: str
    provider: str
    created_at: float


class JsonCache:
    def __init__(self, cache_dir: Optional[str] = None, max_age: int = 86400):
        if cache_dir:
            self.cache_path = Path(cache_dir) / "cache.json"
        else:
            self.cache_path = Path.home() / ".source-translator" / "cache.json"
        
        self.max_age = max_age
        self.data: dict[str, CacheEntry] = {}
        self._load()

    def _load(self) -> None:
        try:
            if self.cache_path.exists():
                with open(self.cache_path, "r") as f:
                    raw = json.load(f)
                    for key, entry in raw.items():
                        self.data[key] = CacheEntry(**entry)
        except Exception:
            pass

    def _save(self) -> None:
        try:
            self.cache_path.parent.mkdir(parents=True, exist_ok=True)
            with open(self.cache_path, "w") as f:
                json.dump(
                    {k: asdict(v) for k, v in self.data.items()},
                    f,
                    indent=2,
                )
        except Exception:
            pass

    def _compute_hash(self, text: str, target_lang: str) -> str:
        return hashlib.sha256(f"{text}{target_lang}".encode()).hexdigest()

    def get(self, text: str, target_lang: str) -> Optional[CacheEntry]:
        hash_key = self._compute_hash(text, target_lang)
        entry = self.data.get(hash_key)
        
        if entry is None:
            return None
        
        if time.time() - entry.created_at > self.max_age:
            del self.data[hash_key]
            self._save()
            return None
        
        return entry

    def set(self, text: str, source_lang: str, target_lang: str, translation: str, provider: str) -> None:
        hash_key = self._compute_hash(text, target_lang)
        self.data[hash_key] = CacheEntry(
            text_hash=hash_key,
            source_lang=source_lang,
            target_lang=target_lang,
            original_text=text,
            translated_text=translation,
            provider=provider,
            created_at=time.time(),
        )
        self._save()

    def stats(self) -> dict:
        providers = set()
        for entry in self.data.values():
            providers.add(entry.provider)
        return {
            "total_entries": len(self.data),
            "providers_used": list(providers),
        }
