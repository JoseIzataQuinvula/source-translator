# Source Translator v1.0.0

Ultra-fast translation engine based on ID-indexed JSON dictionaries.

**Author:** Jose Izata Quinvula  
**Ecosystem:** DUCK STACK  
**Portfolio:** [joseizataquinvula.pages.dev](https://joseizataquinvula.pages.dev/)  

---

![Status](https://img.shields.io/badge/STATUS-Active-brightgreen?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)

---

## How It Works

Source Translator translates content between languages without paid third-party APIs. Translations are performed by direct mapping of **unique IDs** in native JSON package files (`/locales/{lang}.json`).

```
PT-BR: {"1001": "Ola", "1002": "Mundo"}
EN: {"1001": "Hello", "1002": "World"}

Lookup: PT-BR #1001 -> EN #1001 = "Hello" (0ms)
```

---

## Installation

```bash
git clone https://github.com/JoseIzataQuinvula/source-translator.git
```

### PHP Usage

```php
require_once __DIR__ . '/sdk/php/src/SmartEngine.php';

use SourceTranslator\SmartEngine;

$engine = new SmartEngine(['en', 'pt-AO', 'pt-BR']);

$result = $engine->translate('Hello', 'pt-AO', 'en');
echo $result['translated_text']; // "Ola"
echo $result['latency_ms'];      // 0
```

---

## Features

| Feature | Description |
|---------|-------------|
| Indexed Dictionary | ID-based word mapping for instant lookups |
| Smart Filters | Skip same language, Do Not Translate, HTML tags |
| Segment Translation | Word-by-word fallback for compound sentences |
| Cache-First | Local JSON cache, then web providers |
| Multi-Provider | Google, Bing, MyMemory with automatic fallback |
| Missing Tracker | Records untranslated words for developer review |
| Auto-Download | Fetches language packages from GitHub on demand |
| Author Protection | Protected terms cannot be translated |
| Role-Based Auth | Root (full access) and User (limited access) |

---

## On-Demand Language Packages

If your project requests a language not in your local `/locales/` folder, the engine automatically downloads the official package from the GitHub repository.

```php
// Automatically downloads pt-BR.json if not present locally
$engine->translate('Hello', 'pt-BR', 'en');
```

---

## Running the Terminal UI

```bash
php -S localhost:8000 index.php
```

Access `http://localhost:8000/index.php?key=YOUR_ADMIN_KEY` in your browser.

Set `ST_ADMIN_KEY` in your `.env` file.

### Terminal Commands

| Command | Description |
|---------|-------------|
| `@en text @pt-AO` | Translate between languages |
| `@pacotes list` | List packages from GitHub |
| `@pacotes locais list` | List local packages |
| `@idioma download` | Download package from GitHub |
| `@idioma update` | Update local package |
| `@login user pass` | Authenticate as root or user |
| `@user` | Show current user |
| `logout` | Logout |
| `term:find <text>` | Search term across dictionaries |
| `stats` | Show statistics |
| `clear` | Clear terminal |
| `help` | Show help |

---

## Architecture

```
Input Text
    |
    v
[1] Same Language? -> Skip
[2] Do Not Translate? -> Preserve
[3] HTML notranslate? -> Preserve
[4] Indexed Dictionary -> Full match (0ms)
[5] Segment Translation -> Word-by-word
[6] Local Cache -> Cached result
[7] Web Providers -> Google/Bing/MyMemory
[8] Offline Fallback -> Pending queue
```

---

## Supported Languages

| Code | Language |
|------|----------|
| `en` | English |
| `pt-AO` | Portuguese (Angola) |
| `pt-BR` | Portuguese (Brazil) |
| `es` | Spanish |
| `fr` | French |

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
| 108 | HTML notranslate tag |
| 109 | Segment translation |

---

## Roles

| Role | Access |
|------|--------|
| `root` | Create, edit, term:add, full access |
| `editor` | Edit existing packages |
| `user` | Download, update, pkg:list, term:find |

---

## License

MIT License - see [LICENSE](LICENSE) for details.

---

**Jose Izata Quinvula | DUCK STACK | [joseizataquinvula.pages.dev](https://joseizataquinvula.pages.dev/)**
