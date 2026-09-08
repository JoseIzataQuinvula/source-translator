use source_translator_core::{Translator, TranslateRequest};

#[tokio::main]
async fn main() {
    println!("Initializing Source Translator...");
    
    let translator = match Translator::new().await {
        Ok(t) => t,
        Err(e) => {
            eprintln!("Failed to initialize: {}", e);
            return;
        }
    };
    
    println!("Translator ready!\n");
    
    // Test single translation
    let request = TranslateRequest {
        text: "Hello, World!".to_string(),
        source_lang: "en".to_string(),
        target_lang: "pt-BR".to_string(),
    };
    
    println!("Translating: \"{}\"", request.text);
    println!("From: {} -> To: {}", request.source_lang, request.target_lang);
    
    match translator.translate(request).await {
        Ok(result) => {
            println!("\nResult:");
            println!("  Translation: \"{}\"", result.translated_text);
            println!("  Provider: {}", result.provider);
            println!("  Cached: {}", result.cached);
            println!("  Latency: {}ms", result.latency_ms);
        }
        Err(e) => {
            eprintln!("Translation failed: {}", e);
        }
    }
    
    // Test cache stats
    println!("\nCache Stats:");
    match translator.cache_stats().await {
        Ok(stats) => {
            println!("  Total entries: {}", stats.total_entries);
            println!("  Providers used: {:?}", stats.providers_used);
        }
        Err(e) => {
            eprintln!("Failed to get stats: {}", e);
        }
    }
}
