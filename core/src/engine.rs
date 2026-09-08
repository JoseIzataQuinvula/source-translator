use std::sync::Arc;
use serde::{Deserialize, Serialize};
use crate::cache::{Cache, CacheError};
use crate::providers::{google::GoogleProvider, bing::BingProvider, TranslationProvider, ProviderError};

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct TranslateRequest {
    pub text: String,
    pub source_lang: String,
    pub target_lang: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct TranslationResult {
    pub translated_text: String,
    pub source_lang: String,
    pub target_lang: String,
    pub provider: String,
    pub cached: bool,
    pub latency_ms: u64,
}

#[derive(Debug, thiserror::Error)]
pub enum TranslationError {
    #[error("Cache error: {0}")]
    CacheError(#[from] CacheError),
    #[error("Provider error: {0}")]
    ProviderError(#[from] ProviderError),
    #[error("All providers failed")]
    AllProvidersFailed,
}

#[derive(Clone)]
pub struct Translator {
    cache: Cache,
    providers: Vec<Arc<dyn TranslationProvider>>,
}

impl Translator {
    pub async fn new() -> Result<Self, TranslationError> {
        let cache = Cache::new().await?;
        
        let providers: Vec<Arc<dyn TranslationProvider>> = vec![
            Arc::new(GoogleProvider::new()),
            Arc::new(BingProvider::new()),
        ];
        
        Ok(Self { cache, providers })
    }
    
    pub async fn translate(&self, request: TranslateRequest) -> Result<TranslationResult, TranslationError> {
        let start = std::time::Instant::now();
        
        if let Some(cached) = self.cache.get(&request.text, &request.target_lang).await {
            return Ok(TranslationResult {
                translated_text: cached.translated_text,
                source_lang: request.source_lang,
                target_lang: request.target_lang,
                provider: cached.provider,
                cached: true,
                latency_ms: start.elapsed().as_millis() as u64,
            });
        }
        
        for provider in &self.providers {
            match provider.translate(&request.text, &request.source_lang, &request.target_lang).await {
                Ok(result) => {
                    let _ = self.cache.set(
                        &request.text,
                        &request.source_lang,
                        &request.target_lang,
                        &result.translated_text,
                        &result.provider.to_string(),
                    ).await;
                    
                    return Ok(TranslationResult {
                        translated_text: result.translated_text,
                        source_lang: request.source_lang,
                        target_lang: request.target_lang,
                        provider: provider.name().to_string(),
                        cached: false,
                        latency_ms: start.elapsed().as_millis() as u64,
                    });
                }
                Err(e) => {
                    tracing::warn!("Provider {} failed: {}", provider.name(), e);
                    continue;
                }
            }
        }
        
        Err(TranslationError::AllProvidersFailed)
    }
    
    pub async fn translate_batch(&self, requests: Vec<TranslateRequest>) -> Result<Vec<TranslationResult>, TranslationError> {
        let mut results = Vec::new();
        
        for req in requests {
            match self.translate(req).await {
                Ok(result) => results.push(result),
                Err(e) => tracing::warn!("Batch translation failed: {}", e),
            }
        }
        
        Ok(results)
    }
    
    pub async fn cache_stats(&self) -> Result<crate::cache::CacheStats, CacheError> {
        self.cache.stats().await
    }
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct SupportedLanguages {
    pub languages: Vec<Language>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct Language {
    pub code: String,
    pub name: String,
}

impl SupportedLanguages {
    pub fn new() -> Self {
        Self {
            languages: vec![
                Language { code: "en".to_string(), name: "English".to_string() },
                Language { code: "pt-BR".to_string(), name: "Portuguese (Brazil)".to_string() },
                Language { code: "pt-AO".to_string(), name: "Portuguese (Angola)".to_string() },
                Language { code: "pt-PT".to_string(), name: "Portuguese (Portugal)".to_string() },
                Language { code: "es".to_string(), name: "Spanish".to_string() },
                Language { code: "fr".to_string(), name: "French".to_string() },
                Language { code: "de".to_string(), name: "German".to_string() },
                Language { code: "ja".to_string(), name: "Japanese".to_string() },
                Language { code: "ko".to_string(), name: "Korean".to_string() },
                Language { code: "zh-CN".to_string(), name: "Chinese (Simplified)".to_string() },
                Language { code: "ru".to_string(), name: "Russian".to_string() },
                Language { code: "ar".to_string(), name: "Arabic".to_string() },
            ],
        }
    }
}
