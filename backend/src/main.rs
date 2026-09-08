use axum::{routing::get, routing::post, Router, extract::State, Json};
use tower_http::cors::{Any, CorsLayer};
use tracing_subscriber::EnvFilter;

use source_translator_core::{Translator, TranslateRequest, SupportedLanguages};

#[tokio::main]
async fn main() {
    tracing_subscriber::fmt()
        .with_env_filter(EnvFilter::from_default_env().add_directive("info".parse().unwrap()))
        .init();

    let translator = Translator::new().await.expect("Failed to initialize translator");

    let cors = CorsLayer::new()
        .allow_origin(Any)
        .allow_methods(Any)
        .allow_headers(Any);

    let app = Router::new()
        .route("/health", get(health_check))
        .route("/api/v1/translate", post(translate))
        .route("/api/v1/translate/batch", post(translate_batch))
        .route("/api/v1/languages", get(languages))
        .layer(cors)
        .with_state(translator);

    let port = std::env::var("PORT").unwrap_or_else(|_| "3000".to_string());
    let listener = tokio::net::TcpListener::bind(format!("0.0.0.0:{}", port))
        .await
        .unwrap();

    tracing::info!("Source Translator API running on 0.0.0.0:{}", port);

    axum::serve(listener, app).await.unwrap();
}

async fn health_check() -> Json<serde_json::Value> {
    Json(serde_json::json!({
        "status": "healthy",
        "version": env!("CARGO_PKG_VERSION"),
        "engine": "local"
    }))
}

async fn translate(
    State(translator): State<Translator>,
    Json(request): Json<TranslateRequest>,
) -> Json<serde_json::Value> {
    match translator.translate(request).await {
        Ok(result) => Json(serde_json::json!({
            "success": true,
            "data": result
        })),
        Err(e) => Json(serde_json::json!({
            "success": false,
            "error": e.to_string()
        })),
    }
}

async fn translate_batch(
    State(translator): State<Translator>,
    Json(requests): Json<Vec<TranslateRequest>>,
) -> Json<serde_json::Value> {
    match translator.translate_batch(requests).await {
        Ok(results) => Json(serde_json::json!({
            "success": true,
            "data": results,
            "count": results.len()
        })),
        Err(e) => Json(serde_json::json!({
            "success": false,
            "error": e.to_string()
        })),
    }
}

async fn languages() -> Json<SupportedLanguages> {
    Json(SupportedLanguages::new())
}
