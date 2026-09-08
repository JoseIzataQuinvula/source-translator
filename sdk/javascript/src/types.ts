export interface TranslateRequest {
  text: string;
  sourceLang: string;
  targetLang: string;
}

export interface TranslationResult {
  translated_text: string;
  source_lang: string;
  target_lang: string;
  provider: string;
  cached: boolean;
  latency_ms: number;
}

export interface Language {
  code: string;
  name: string;
}

export interface CacheStats {
  total_entries: number;
  providers_used: string[];
}

export interface TranslatorConfig {
  cacheDir?: string;
  maxCacheAge?: number;
}

export interface TranslateBatchRequest {
  texts: TranslateRequest[];
}
