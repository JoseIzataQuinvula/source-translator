pub use source_translator_core::*;

#[cfg(test)]
mod tests {
    use super::*;

    #[tokio::test]
    async fn test_translator_creation() {
        let translator = Translator::new().await;
        assert!(translator.is_ok());
    }

    #[test]
    fn test_supported_languages() {
        let langs = SupportedLanguages::new();
        assert!(!langs.languages.is_empty());
    }
}
