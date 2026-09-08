use async_trait::async_trait;
use reqwest::Client;
use serde_json::Value;

use super::{Provider, ProviderError, TranslationProvider, TranslationResult};

pub struct BingProvider {
    client: Client,
}

impl BingProvider {
    pub fn new() -> Self {
        Self {
            client: Client::new(),
        }
    }
}

#[async_trait]
impl TranslationProvider for BingProvider {
    async fn translate(
        &self,
        text: &str,
        source_lang: &str,
        target_lang: &str,
    ) -> Result<TranslationResult, ProviderError> {
        let url = "https://www.bing.com/ttranslatev3";

        let params = [
            ("from", source_lang),
            ("to", target_lang),
            ("text", text),
        ];

        let response = self.client
            .post(url)
            .form(&params)
            .header("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36")
            .send()
            .await?;

        let json: Value = response.json().await?;

        let translated = json[0]["translations"][0]["text"]
            .as_str()
            .ok_or_else(|| ProviderError::ProviderError("Invalid Bing response".into()))?;

        Ok(TranslationResult {
            translated_text: translated.to_string(),
            provider: Provider::Bing,
        })
    }
    
    fn name(&self) -> &str {
        "Bing"
    }
}
