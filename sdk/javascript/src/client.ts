import { JsonCache } from './cache';
import { GoogleProvider, BingProvider, TranslationProvider } from './providers';
import {
  TranslateRequest,
  TranslationResult,
  Language,
  CacheStats,
  TranslatorConfig,
} from './types';

const SUPPORTED_LANGUAGES: Language[] = [
  { code: 'en', name: 'English' },
  { code: 'pt-BR', name: 'Portuguese (Brazil)' },
  { code: 'es', name: 'Spanish' },
  { code: 'fr', name: 'French' },
  { code: 'de', name: 'German' },
  { code: 'ja', name: 'Japanese' },
  { code: 'ko', name: 'Korean' },
  { code: 'zh-CN', name: 'Chinese (Simplified)' },
  { code: 'ru', name: 'Russian' },
  { code: 'ar', name: 'Arabic' },
];

export class SourceTranslator {
  private cache: JsonCache;
  private providers: TranslationProvider[];

  constructor(config?: TranslatorConfig) {
    this.cache = new JsonCache(config?.cacheDir, config?.maxCacheAge);
    this.providers = [
      new GoogleProvider(),
      new BingProvider(),
    ];
  }

  async translate(request: TranslateRequest): Promise<TranslationResult> {
    const start = Date.now();

    const cached = this.cache.get(request.text, request.targetLang);
    if (cached) {
      return {
        translated_text: cached.translated_text,
        source_lang: cached.source_lang,
        target_lang: cached.target_lang,
        provider: cached.provider,
        cached: true,
        latency_ms: Date.now() - start,
      };
    }

    for (const provider of this.providers) {
      try {
        const result = await provider.translate(
          request.text,
          request.sourceLang,
          request.targetLang
        );

        this.cache.set(
          request.text,
          request.sourceLang,
          request.targetLang,
          result.translated_text,
          result.provider
        );

        return {
          translated_text: result.translated_text,
          source_lang: request.sourceLang,
          target_lang: request.targetLang,
          provider: result.provider,
          cached: false,
          latency_ms: Date.now() - start,
        };
      } catch (error) {
        console.warn(`Provider ${provider.name} failed:`, error);
        continue;
      }
    }

    throw new Error('All translation providers failed');
  }

  async translateBatch(texts: TranslateRequest[]): Promise<TranslationResult[]> {
    const results: TranslationResult[] = [];

    for (const request of texts) {
      try {
        const result = await this.translate(request);
        results.push(result);
      } catch (error) {
        console.warn('Batch translation failed:', error);
      }
    }

    return results;
  }

  static getLanguages(): Language[] {
    return SUPPORTED_LANGUAGES;
  }

  cacheStats(): CacheStats {
    return this.cache.stats();
  }
}
