# Source Translator

Open-source, cache-first translation library for websites, mobile apps, and CLI tools.

**Zero server costs. Zero API keys. Runs locally on your machine.**

---

![Status](https://img.shields.io/badge/STATUS-Active-brightgreen?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)

### Languages

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Rust](https://img.shields.io/badge/Rust-000000?style=for-the-badge&logo=rust&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-3178C6?style=for-the-badge&logo=typescript&logoColor=white)
![Python](https://img.shields.io/badge/Python-3776AB?style=for-the-badge&logo=python&logoColor=white)
![Go](https://img.shields.io/badge/Go-00ADD8?style=for-the-badge&logo=go&logoColor=white)

### Infrastructure

![SQLite](https://img.shields.io/badge/SQLite-003B57?style=for-the-badge&logo=sqlite&logoColor=white)
![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-885630?style=for-the-badge&logo=composer&logoColor=white)

---

## Supported Languages

| Code | Language | Default |
|------|----------|---------|
| `en` | English | Yes |
| `pt-AO` | Portuguese (Angola) | Yes |
| `pt` | Portuguese | Yes |
| `es` | Spanish | No |
| `fr` | French | No |

---

## Features

- **Offline Dictionary:** Indexed word mapping (ID-based) for instant lookups
- **Smart Filters:** Skip same language, Do Not Translate list, HTML notranslate tags
- **Segment Translation:** Word-by-word fallback for compound sentences
- **Cache-First:** Local JSON cache, then web providers
- **Multi-Provider:** Google, Bing, MyMemory with automatic fallback
- **Missing Tracker:** Records untranslated words for developer review
- **Pending Queue:** Offline fallback with automatic retry

---

## Installation

### PHP

```bash
composer require source-translator/php
```

### Rust

```bash
cargo add source-translator-sdk
```

### JavaScript/TypeScript

```bash
npm install source-translator
```

### Python

```bash
pip install source-translator
```

### Go

```bash
go get github.com/source-translator/source-translator/sdk/go
```

---

## Usage

### PHP

```php
<?php
require_once 'vendor/autoload.php';

use SourceTranslator\SmartEngine;

$engine = new SmartEngine(['en', 'pt-AO']);

// Basic translation
$result = $engine->translate('Good morning', 'pt-AO', 'en');
echo $result['translated_text']; // "Bom dia"

// Sentence with partial dictionary
$result = $engine->translate('Good morning friend', 'pt-AO', 'en');
// Returns: "Bom dia friend" (friend not in dictionary)
```

### Rust

```rust
use source_translator_sdk::{Translator, TranslateRequest};

#[tokio::main]
async fn main() {
    let translator = Translator::new().await.unwrap();

    let result = translator.translate(TranslateRequest {
        text: "Good morning".to_string(),
        source_lang: "en".to_string(),
        target_lang: "pt-AO".to_string(),
    }).await.unwrap();

    println!("{}", result.translated_text);
}
```

### JavaScript/TypeScript

```typescript
import { SourceTranslator } from 'source-translator';

const translator = new SourceTranslator();

const result = await translator.translate({
  text: 'Good morning',
  sourceLang: 'en',
  targetLang: 'pt-AO',
});

console.log(result.translated_text);
```

### Python

```python
from source_translator import SourceTranslator

translator = SourceTranslator()

result = translator.translate(
    text='Good morning',
    source_lang='en',
    target_lang='pt-AO'
)

print(result.translated_text)
```

### Go

```go
package main

import (
    "fmt"
    translator "github.com/source-translator/source-translator/sdk/go"
)

func main() {
    t := translator.NewSourceTranslator()

    result, _ := t.Translate(translator.TranslateRequest{
        Text:       "Good morning",
        SourceLang: "en",
        TargetLang: "pt-AO",
    })

    fmt.Println(result.TranslatedText)
}
```

---

## Architecture

```
Input Text
    |
    v
[1] Same Language? -> Skip (code 106)
    |
    v
[2] Do Not Translate? -> Preserve (code 107)
    |
    v
[3] HTML notranslate? -> Preserve (code 108)
    |
    v
[4] Indexed Dictionary -> Full match (code 109)
    |
    v
[5] Segment Translation -> Word-by-word (code 109)
    |
    v
[6] Local Cache -> Cached result (code 103)
    |
    v
[7] Web Providers -> Google/Bing/MyMemory
    |
    v
[8] Offline Fallback -> Pending queue (code 105)
```

---

## Status Codes

| Code | Description |
|------|-------------|
| 0 | Success |
| 101 | Language not supported |
| 102 | Translation missing |
| 103 | Cache hit |
| 104 | Web translated |
| 105 | Offline fallback |
| 106 | Same language (skip) |
| 107 | Do Not Translate (skip) |
| 108 | HTML notranslate tag (skip) |
| 109 | Segment translation |

---

## License

MIT License - see [LICENSE](LICENSE) for details.
