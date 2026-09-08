from abc import ABC, abstractmethod
from typing import Optional
import httpx
from pydantic import BaseModel


class ProviderResult(BaseModel):
    translated_text: str
    provider: str


class TranslationProvider(ABC):
    @abstractmethod
    async def translate(self, text: str, source_lang: str, target_lang: str) -> ProviderResult:
        pass
    
    @property
    @abstractmethod
    def name(self) -> str:
        pass


class GoogleProvider(TranslationProvider):
    name = "Google"

    async def translate(self, text: str, source_lang: str, target_lang: str) -> ProviderResult:
        url = f"https://translate.googleapis.com/translate_a/single?client=gtx&sl={source_lang}&tl={target_lang}&dt=t&q={text}"
        
        async with httpx.AsyncClient() as client:
            response = await client.get(url)
            response.raise_for_status()
            data = response.json()
        
        translated = "".join(
            item[0] for item in data[0] if item[0]
        )
        
        if not translated:
            raise ValueError("Invalid Google Translate response")
        
        return ProviderResult(
            translated_text=translated,
            provider=self.name,
        )


class BingProvider(TranslationProvider):
    name = "Bing"

    async def translate(self, text: str, source_lang: str, target_lang: str) -> ProviderResult:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                "https://www.bing.com/ttranslatev3",
                data={
                    "from": source_lang,
                    "to": target_lang,
                    "text": text,
                },
                headers={
                    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
                },
            )
            response.raise_for_status()
            data = response.json()
        
        translated = data[0]["translations"][0]["text"]
        
        if not translated:
            raise ValueError("Invalid Bing Translate response")
        
        return ProviderResult(
            translated_text=translated,
            provider=self.name,
        )
