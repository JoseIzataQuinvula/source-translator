use std::time::Duration;

use axum::{routing::get, routing::post, Router, extract::State, Json};
use tower_http::cors::{Any, CorsLayer};
use tower_http::limit::RequestBodyLimitLayer;
use tower_http::timeout::TimeoutLayer;
use tracing_subscriber::EnvFilter;

use source_translator_core::{Translator, TranslateRequest, SupportedLanguages};

const MAX_BODY_SIZE: usize = 1024 * 1024;
const REQUEST_TIMEOUT: Duration = Duration::from_secs(10);

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
        .layer(RequestBodyLimitLayer::new(MAX_BODY_SIZE))
        .layer(TimeoutLayer::new(REQUEST_TIMEOUT))
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
    let text = request.text.clone();
    let sanitized = text.replace(['\0', '\x01', '\x02', '\x03'], "");

    let mut req = request;
    req.text = sanitized;

    match translator.translate(req).await {
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
    let sanitized: Vec<TranslateRequest> = requests
        .into_iter()
        .map(|r| {
            let clean = r.text.replace(['\0', '\x01', '\x02', '\x03'], "");
            let mut req = r;
            req.text = clean;
            req
        })
        .collect();

    match translator.translate_batch(sanitized).await {
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
