import fetch from 'node-fetch';

export interface ProviderResult {
  translated_text: string;
  provider: string;
}

export interface TranslationProvider {
  translate(text: string, sourceLang: string, targetLang: string): Promise<ProviderResult>;
  name: string;
}

export class GoogleProvider implements TranslationProvider {
  name = 'Google';

  async translate(text: string, sourceLang: string, targetLang: string): Promise<ProviderResult> {
    const url = `https://translate.googleapis.com/translate_a/single?client=gtx&sl=${sourceLang}&tl=${targetLang}&dt=t&q=${encodeURIComponent(text)}`;
    
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error(`Google Translate HTTP error: ${response.status}`);
    }
    
    const json = await response.json() as any[][];
    
    const translated = json[0]
      ?.map((item: any[]) => item[0])
      .filter(Boolean)
      .join('') || '';
    
    if (!translated) {
      throw new Error('Invalid Google Translate response');
    }
    
    return {
      translated_text: translated,
      provider: this.name,
    };
  }
}

export class BingProvider implements TranslationProvider {
  name = 'Bing';

  async translate(text: string, sourceLang: string, targetLang: string): Promise<ProviderResult> {
    const params = new URLSearchParams();
    params.append('from', sourceLang);
    params.append('to', targetLang);
    params.append('text', text);

    const response = await fetch('https://www.bing.com/ttranslatev3', {
      method: 'POST',
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: params.toString(),
    });

    if (!response.ok) {
      throw new Error(`Bing Translate HTTP error: ${response.status}`);
    }

    const json = await response.json() as any;
    const translated = json?.[0]?.translations?.[0]?.text;

    if (!translated) {
      throw new Error('Invalid Bing Translate response');
    }

    return {
      translated_text: translated,
      provider: this.name,
    };
  }
}
