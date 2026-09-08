from typing import List, Optional
from pydantic import BaseModel


class TranslateRequest(BaseModel):
    text: str
    source_lang: str
    target_lang: str


class TranslationResult(BaseModel):
    translated_text: str
    source_lang: str
    target_lang: str
    provider: str
    cached: bool
    latency_ms: int


class Language(BaseModel):
    code: str
    name: str


class CacheStats(BaseModel):
    total_entries: int
    providers_used: List[str]


SUPPORTED_LANGUAGES = [
    Language(code="en", name="English"),
    Language(code="pt-BR", name="Portuguese (Brazil)"),
    Language(code="es", name="Spanish"),
    Language(code="fr", name="French"),
    Language(code="de", name="German"),
    Language(code="ja", name="Japanese"),
    Language(code="ko", name="Korean"),
    Language(code="zh-CN", name="Chinese (Simplified)"),
    Language(code="ru", name="Russian"),
    Language(code="ar", name="Arabic"),
]
