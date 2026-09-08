pub mod google;
pub mod bing;

use std::fmt;
use async_trait::async_trait;

#[derive(Debug, Clone, PartialEq)]
pub enum Provider {
    Google,
    Bing,
}

impl fmt::Display for Provider {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            Provider::Google => write!(f, "Google"),
            Provider::Bing => write!(f, "Bing"),
        }
    }
}

#[derive(Debug)]
pub struct TranslationResult {
    pub translated_text: String,
    pub provider: Provider,
}

#[async_trait]
pub trait TranslationProvider: Send + Sync {
    async fn translate(
        &self,
        text: &str,
        source_lang: &str,
        target_lang: &str,
    ) -> Result<TranslationResult, ProviderError>;
    
    fn name(&self) -> &str;
}

#[derive(Debug, thiserror::Error)]
pub enum ProviderError {
    #[error("HTTP request failed: {0}")]
    HttpError(#[from] reqwest::Error),
    #[error("Provider returned error: {0}")]
    ProviderError(String),
    #[error("Rate limited")]
    RateLimited,
}
