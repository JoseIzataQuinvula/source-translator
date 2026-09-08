# Source Translator

An open-source, cache-first translation library designed to make i18n completely free for websites, mobile apps, and CLI tools.

**Zero server costs. Zero API keys. Runs locally on your machine.**

---

![Status](https://img.shields.io/badge/STATUS-Active-brightgreen?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)

### Languages

![Rust](https://img.shields.io/badge/Rust-000000?style=for-the-badge&logo=rust&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-3178C6?style=for-the-badge&logo=typescript&logoColor=white)
![Python](https://img.shields.io/badge/Python-3776AB?style=for-the-badge&logo=python&logoColor=white)
![Go](https://img.shields.io/badge/Go-00ADD8?style=for-the-badge&logo=go&logoColor=white)

### Tools & Infrastructure

![SQLite](https://img.shields.io/badge/SQLite-003B57?style=for-the-badge&logo=sqlite&logoColor=white)
![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)
![Git](https://img.shields.io/badge/Git-F05032?style=for-the-badge&logo=git&logoColor=white)
![npm](https://img.shields.io/badge/npm-CB3837?style=for-the-badge&logo=npm&logoColor=white)
![PyPI](https://img.shields.io/badge/PyPI-3775A9?style=for-the-badge&logo=pypi&logoColor=white)
![crates.io](https://img.shields.io/badge/crates.io-FFA740?style=for-the-badge&logo=rust&logoColor=white)

---

## Key Features

- **100% Free & Open Source:** No paid API keys or hidden costs.
- **Local Cache-First:** SQLite (Rust) or JSON (JS/Python/Go) cache stored locally.
- **Multi-Provider Engine:** Automatic fallback between free web translation engines (Google, Bing).
- **Privacy-First:** Your data never leaves your machine.
- **Universal SDK:** Works in Web, Mobile, Backend, and CLI applications.

---

## Installation

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

### Rust

```rust
use source_translator_sdk::{Translator, TranslateRequest};

#[tokio::main]
async fn main() {
    let translator = Translator::new().await.unwrap();

    let result = translator.translate(TranslateRequest {
        text: "Hello, World!".to_string(),
        source_lang: "en".to_string(),
        target_lang: "pt-BR".to_string(),
    }).await.unwrap();

    println!("Translation: {}", result.translated_text);
    println!("Provider: {}", result.provider);
    println!("From cache: {}", result.cached);
}
```

### JavaScript/TypeScript

```typescript
import { SourceTranslator } from 'source-translator';

const translator = new SourceTranslator();

const result = await translator.translate({
  text: 'Hello, World!',
  sourceLang: 'en',
  targetLang: 'pt-BR',
});

console.log(result.translated_text);
console.log(result.cached);
```

### Python

```python
from source_translator import SourceTranslator

translator = SourceTranslator()

result = translator.translate(
    text='Hello, World!',
    source_lang='en',
    target_lang='pt-BR'
)

print(result.translated_text)
print(result.cached)
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

    result, err := t.Translate(translator.TranslateRequest{
        Text:       "Hello, World!",
        SourceLang: "en",
        TargetLang: "pt-BR",
    })

    if err != nil {
        panic(err)
    }

    fmt.Println(result.TranslatedText)
    fmt.Println(result.Cached)
}
```

---

## Architecture

```
+---------------------------------------------------------------+
|                   Your Application                            |
+---------------------------------------------------------------+
                              |
                              v
+---------------------------------------------------------------+
|              Source Translator (Local Library)                |
|                                                               |
|  1. Check Local Cache (SQLite / JSON)                         |
|  2. If miss -> Call Google/Bing directly                      |
|  3. Save to Local Cache                                       |
|  4. Return result                                             |
+---------------------------------------------------------------+
```

---

## Supported Languages

| Code  | Language            |
|-------|---------------------|
| `en`  | English             |
| `pt-BR` | Portuguese (Brazil) |
| `es`  | Spanish             |
| `fr`  | French              |
| `de`  | German              |
| `ja`  | Japanese            |
| `ko`  | Korean              |
| `zh-CN` | Chinese (Simplified) |
| `ru`  | Russian             |
| `ar`  | Arabic              |

---

## Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) first.

## License

MIT License - see [LICENSE](LICENSE) for details.
