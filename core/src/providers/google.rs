use async_trait::async_trait;
use reqwest::Client;
use serde_json::Value;

use super::{Provider, ProviderError, TranslationProvider, TranslationResult};

pub struct GoogleProvider {
    client: Client,
}

impl GoogleProvider {
    pub fn new() -> Self {
        Self {
            client: Client::new(),
        }
    }
}

#[async_trait]
impl TranslationProvider for GoogleProvider {
    async fn translate(
        &self,
        text: &str,
        source_lang: &str,
        target_lang: &str,
    ) -> Result<TranslationResult, ProviderError> {
        let url = format!(
            "https://translate.googleapis.com/translate_a/single?client=gtx&sl={}&tl={}&dt=t&q={}",
            source_lang,
            target_lang,
            urlencoding::encode(text)
        );

        let response = self.client.get(&url).send().await?.text().await?;

        let json: Value = serde_json::from_str(&response)
            .map_err(|e| ProviderError::ProviderError(e.to_string()))?;

        let translated = json[0]
            .as_array()
            .and_then(|arr| arr.first())
            .and_then(|first| first.as_array())
            .and_then(|arr| arr.first())
            .and_then(|s| s.as_str())
            .ok_or_else(|| ProviderError::ProviderError("Invalid response format".into()))?;

        Ok(TranslationResult {
            translated_text: translated.to_string(),
            provider: Provider::Google,
        })
    }
    
    fn name(&self) -> &str {
        "Google"
    }
}
