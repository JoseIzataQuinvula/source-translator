import * as fs from 'fs';
import * as path from 'path';
import * as crypto from 'crypto';
import { homedir } from 'os';

interface CacheEntry {
  text_hash: string;
  source_lang: string;
  target_lang: string;
  original_text: string;
  translated_text: string;
  provider: string;
  created_at: number;
}

interface CacheData {
  entries: Record<string, CacheEntry>;
}

export class JsonCache {
  private cachePath: string;
  private data: CacheData;
  private maxAge: number;

  constructor(cacheDir?: string, maxAge: number = 86400000) {
    const dir = cacheDir || path.join(homedir(), '.source-translator');
    this.cachePath = path.join(dir, 'cache.json');
    this.maxAge = maxAge;
    this.data = this.load();
  }

  private load(): CacheData {
    try {
      if (fs.existsSync(this.cachePath)) {
        const content = fs.readFileSync(this.cachePath, 'utf-8');
        return JSON.parse(content);
      }
    } catch {
      // Ignore errors
    }
    return { entries: {} };
  }

  private save(): void {
    try {
      const dir = path.dirname(this.cachePath);
      if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
      }
      fs.writeFileSync(this.cachePath, JSON.stringify(this.data, null, 2));
    } catch (err) {
      console.error('Failed to save cache:', err);
    }
  }

  private computeHash(text: string, targetLang: string): string {
    return crypto.createHash('sha256')
      .update(text)
      .update(targetLang)
      .digest('hex');
  }

  get(text: string, targetLang: string): CacheEntry | null {
    const hash = this.computeHash(text, targetLang);
    const entry = this.data.entries[hash];
    
    if (!entry) return null;
    
    if (Date.now() - entry.created_at > this.maxAge) {
      delete this.data.entries[hash];
      this.save();
      return null;
    }
    
    return entry;
  }

  set(text: string, sourceLang: string, targetLang: string, translation: string, provider: string): void {
    const hash = this.computeHash(text, targetLang);
    this.data.entries[hash] = {
      text_hash: hash,
      source_lang: sourceLang,
      target_lang: targetLang,
      original_text: text,
      translated_text: translation,
      provider,
      created_at: Date.now(),
    };
    this.save();
  }

  stats(): { total_entries: number; providers_used: string[] } {
    const providers = new Set<string>();
    for (const entry of Object.values(this.data.entries)) {
      providers.add(entry.provider);
    }
    return {
      total_entries: Object.keys(this.data.entries).length,
      providers_used: Array.from(providers),
    };
  }
}
