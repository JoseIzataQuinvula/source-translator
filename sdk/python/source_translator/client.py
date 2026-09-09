import re
import time
from typing import List, Optional

from .cache import JsonCache
from .providers import GoogleProvider, BingProvider, TranslationProvider
from .models import (
    TranslateRequest,
    TranslationResult,
    Language,
    CacheStats,
    SUPPORTED_LANGUAGES,
)

LOCALE_REGEX = re.compile(r'^[a-zA-Z0-9_-]+$')


def is_valid_locale(locale: str) -> bool:
    return bool(LOCALE_REGEX.match(locale))


def sanitize_text(text: str) -> str:
    text = text.replace('\x00', '').replace('\x01', '').replace('\x02', '').replace('\x03', '')
    return text[:10000]


class SourceTranslator:
    def __init__(
        self,
        cache_dir: Optional[str] = None,
        max_cache_age: int = 86400,
    ):
        self.cache = JsonCache(cache_dir, max_cache_age)
        self.providers: List[TranslationProvider] = [
            GoogleProvider(),
            BingProvider(),
        ]

    def translate(
        self,
        text: str,
        source_lang: str,
        target_lang: str,
    ) -> TranslationResult:
        start = time.time()

        text = sanitize_text(text)

        if not is_valid_locale(source_lang) or not is_valid_locale(target_lang):
            raise ValueError("Invalid locale code")

        cached = self.cache.get(text, target_lang)
        if cached:
            return TranslationResult(
                translated_text=cached.translated_text,
                source_lang=cached.source_lang,
                target_lang=cached.target_lang,
                provider=cached.provider,
                cached=True,
                latency_ms=int((time.time() - start) * 1000),
            )

        for provider in self.providers:
            try:
                import asyncio
                result = asyncio.run(provider.translate(text, source_lang, target_lang))
                
                self.cache.set(
                    text,
                    source_lang,
                    target_lang,
                    result.translated_text,
                    result.provider,
                )
                
                return TranslationResult(
                    translated_text=result.translated_text,
                    source_lang=source_lang,
                    target_lang=target_lang,
                    provider=result.provider,
                    cached=False,
                    latency_ms=int((time.time() - start) * 1000),
                )
            except Exception as e:
                print(f"Provider {provider.name} failed: {e}")
                continue

        raise Exception("All translation providers failed")

    def translate_batch(self, texts: List[TranslateRequest]) -> List[TranslationResult]:
        results = []
        
        for request in texts:
            try:
                result = self.translate(
                    request.text,
                    request.source_lang,
                    request.target_lang,
                )
                results.append(result)
            except Exception as e:
                print(f"Batch translation failed: {e}")
        
        return results

    @staticmethod
    def get_languages() -> List[Language]:
        return SUPPORTED_LANGUAGES

    def cache_stats(self) -> CacheStats:
        stats = self.cache.stats()
        return CacheStats(**stats)
