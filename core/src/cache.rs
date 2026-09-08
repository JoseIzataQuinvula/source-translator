use std::path::PathBuf;
use tokio_rusqlite::Connection;
use sha2::{Sha256, Digest};
use serde::{Deserialize, Serialize};

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct CachedTranslation {
    pub text_hash: String,
    pub source_lang: String,
    pub target_lang: String,
    pub original_text: String,
    pub translated_text: String,
    pub provider: String,
    pub created_at: i64,
}

#[derive(Clone)]
pub struct Cache {
    conn: Connection,
}

impl Cache {
    pub async fn new() -> Result<Self, CacheError> {
        let cache_dir = Self::get_cache_dir();
        std::fs::create_dir_all(&cache_dir).map_err(|e| CacheError::IoError(e))?;
        
        let db_path = cache_dir.join("translations.db");
        let conn = Connection::open(db_path).await.map_err(|e| CacheError::DatabaseError(e))?;
        
        conn.call(|conn| {
            conn.execute_batch(
                "CREATE TABLE IF NOT EXISTS translations (
                    text_hash TEXT PRIMARY KEY,
                    source_lang TEXT NOT NULL,
                    target_lang TEXT NOT NULL,
                    original_text TEXT NOT NULL,
                    translated_text TEXT NOT NULL,
                    provider TEXT NOT NULL,
                    created_at INTEGER NOT NULL
                );"
            )?;
            Ok(())
        }).await.map_err(|e| CacheError::DatabaseError(e))?;
        
        Ok(Self { conn })
    }

    pub async fn get(&self, text: &str, target_lang: &str) -> Option<CachedTranslation> {
        let hash = compute_hash(text, target_lang);
        let hash_clone = hash.clone();
        
        self.conn.call(move |conn| {
            let mut stmt = conn.prepare(
                "SELECT text_hash, source_lang, target_lang, original_text, translated_text, provider, created_at 
                 FROM translations WHERE text_hash = ?1"
            )?;
            
            let result = stmt.query_row([hash_clone], |row| {
                Ok(CachedTranslation {
                    text_hash: row.get(0)?,
                    source_lang: row.get(1)?,
                    target_lang: row.get(2)?,
                    original_text: row.get(3)?,
                    translated_text: row.get(4)?,
                    provider: row.get(5)?,
                    created_at: row.get(6)?,
                })
            }).ok();
            
            Ok(result)
        }).await.ok().flatten()
    }

    pub async fn set(&self, text: &str, source_lang: &str, target_lang: &str, translation: &str, provider: &str) -> Result<(), CacheError> {
        let hash = compute_hash(text, target_lang);
        let text = text.to_string();
        let source_lang = source_lang.to_string();
        let target_lang = target_lang.to_string();
        let translation = translation.to_string();
        let provider = provider.to_string();
        let created_at = chrono::Utc::now().timestamp();
        
        self.conn.call(move |conn| {
            conn.execute(
                "INSERT OR REPLACE INTO translations (text_hash, source_lang, target_lang, original_text, translated_text, provider, created_at) 
                 VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7)",
                rusqlite::params![hash, source_lang, target_lang, text, translation, provider, created_at],
            )?;
            Ok(())
        }).await.map_err(|e| CacheError::DatabaseError(e))?;
        
        Ok(())
    }

    pub async fn stats(&self) -> Result<CacheStats, CacheError> {
        self.conn.call(|conn| {
            let count: i64 = conn.query_row("SELECT COUNT(*) FROM translations", [], |row| row.get(0))?;
            let providers: String = conn.query_row(
                "SELECT GROUP_CONCAT(DISTINCT provider) FROM translations", 
                [], 
                |row| row.get(0)
            ).unwrap_or_default();
            
            Ok(CacheStats {
                total_entries: count,
                providers_used: providers.split(',').map(|s| s.to_string()).collect(),
            })
        }).await.map_err(|e| CacheError::DatabaseError(e))
    }

    fn get_cache_dir() -> PathBuf {
        if let Some(dirs) = directories::ProjectDirs::from("", "", "source-translator") {
            dirs.cache_dir().to_path_buf()
        } else {
            PathBuf::from(".source-translator-cache")
        }
    }
}

fn compute_hash(text: &str, target_lang: &str) -> String {
    let mut hasher = Sha256::new();
    hasher.update(text.as_bytes());
    hasher.update(target_lang.as_bytes());
    hex::encode(hasher.finalize())
}

#[derive(Debug, thiserror::Error)]
pub enum CacheError {
    #[error("IO error: {0}")]
    IoError(#[from] std::io::Error),
    #[error("Database error: {0}")]
    DatabaseError(#[from] tokio_rusqlite::Error),
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct CacheStats {
    pub total_entries: i64,
    pub providers_used: Vec<String>,
}
