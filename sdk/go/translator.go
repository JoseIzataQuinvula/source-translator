package translator

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"
)

var localeRegex = regexp.MustCompile(`^[a-zA-Z0-9_-]+$`)

func isValidLocale(locale string) bool {
	return localeRegex.MatchString(locale)
}

func sanitizeText(text string) string {
	text = strings.ReplaceAll(text, "\x00", "")
	text = strings.ReplaceAll(text, "\x01", "")
	text = strings.ReplaceAll(text, "\x02", "")
	text = strings.ReplaceAll(text, "\x03", "")
	if len(text) > 10000 {
		text = text[:10000]
	}
	return text
}

type TranslateRequest struct {
	Text       string `json:"text"`
	SourceLang string `json:"source_lang"`
	TargetLang string `json:"target_lang"`
}

type TranslationResult struct {
	TranslatedText string `json:"translated_text"`
	SourceLang     string `json:"source_lang"`
	TargetLang     string `json:"target_lang"`
	Provider       string `json:"provider"`
	Cached         bool   `json:"cached"`
	LatencyMs      int64  `json:"latency_ms"`
}

type Language struct {
	Code string `json:"code"`
	Name string `json:"name"`
}

type CacheStats struct {
	TotalEntries  int      `json:"total_entries"`
	ProvidersUsed []string `json:"providers_used"`
}

type cacheEntry struct {
	TextHash       string `json:"text_hash"`
	SourceLang     string `json:"source_lang"`
	TargetLang     string `json:"target_lang"`
	OriginalText   string `json:"original_text"`
	TranslatedText string `json:"translated_text"`
	Provider       string `json:"provider"`
	CreatedAt      int64  `json:"created_at"`
}

type jsonCache struct {
	Entries map[string]cacheEntry `json:"entries"`
	path    string
	maxAge  time.Duration
}

type SourceTranslator struct {
	cache     *jsonCache
	providers []TranslationProvider
}

type TranslationProvider interface {
	Translate(text, sourceLang, targetLang string) (string, error)
	Name() string
}

func NewSourceTranslator(cacheDir ...string) *SourceTranslator {
	dir := filepath.Join(os.Getenv("HOME"), ".source-translator")
	if len(cacheDir) > 0 && cacheDir[0] != "" {
		dir = cacheDir[0]
	}

	cache := &jsonCache{
		Entries: make(map[string]cacheEntry),
		path:    filepath.Join(dir, "cache.json"),
		maxAge:  24 * time.Hour,
	}
	cache.load()

	return &SourceTranslator{
		cache: cache,
		providers: []TranslationProvider{
			&GoogleProvider{},
			&BingProvider{},
		},
	}
}

func (t *SourceTranslator) Translate(req TranslateRequest) (*TranslationResult, error) {
	start := time.Now()

	req.Text = sanitizeText(req.Text)

	if !isValidLocale(req.SourceLang) || !isValidLocale(req.TargetLang) {
		return nil, fmt.Errorf("invalid locale code")
	}

	if cached := t.cache.get(req.Text, req.TargetLang); cached != nil {
		return &TranslationResult{
			TranslatedText: cached.TranslatedText,
			SourceLang:     cached.SourceLang,
			TargetLang:     cached.TargetLang,
			Provider:       cached.Provider,
			Cached:         true,
			LatencyMs:      time.Since(start).Milliseconds(),
		}, nil
	}

	for _, provider := range t.providers {
		translated, err := provider.Translate(req.Text, req.SourceLang, req.TargetLang)
		if err != nil {
			fmt.Printf("Provider %s failed: %v\n", provider.Name(), err)
			continue
		}

		t.cache.set(req.Text, req.SourceLang, req.TargetLang, translated, provider.Name())

		return &TranslationResult{
			TranslatedText: translated,
			SourceLang:     req.SourceLang,
			TargetLang:     req.TargetLang,
			Provider:       provider.Name(),
			Cached:         false,
			LatencyMs:      time.Since(start).Milliseconds(),
		}, nil
	}

	return nil, fmt.Errorf("all translation providers failed")
}

func (t *SourceTranslator) TranslateBatch(reqs []TranslateRequest) []TranslationResult {
	var results []TranslationResult
	for _, req := range reqs {
		result, err := t.Translate(req)
		if err != nil {
			fmt.Printf("Batch translation failed: %v\n", err)
			continue
		}
		results = append(results, *result)
	}
	return results
}

func GetLanguages() []Language {
	return []Language{
		{Code: "en", Name: "English"},
		{Code: "pt-BR", Name: "Portuguese (Brazil)"},
		{Code: "es", Name: "Spanish"},
		{Code: "fr", Name: "French"},
		{Code: "de", Name: "German"},
		{Code: "ja", Name: "Japanese"},
		{Code: "ko", Name: "Korean"},
		{Code: "zh-CN", Name: "Chinese (Simplified)"},
		{Code: "ru", Name: "Russian"},
		{Code: "ar", Name: "Arabic"},
	}
}

func (t *SourceTranslator) CacheStats() CacheStats {
	providers := make(map[string]bool)
	for _, entry := range t.cache.Entries {
		providers[entry.Provider] = true
	}
	providerList := make([]string, 0, len(providers))
	for p := range providers {
		providerList = append(providerList, p)
	}
	return CacheStats{
		TotalEntries:  len(t.cache.Entries),
		ProvidersUsed: providerList,
	}
}

// Cache methods

func (c *jsonCache) computeHash(text, targetLang string) string {
	h := sha256.New()
	h.Write([]byte(text))
	h.Write([]byte(targetLang))
	return hex.EncodeToString(h.Sum(nil))
}

func (c *jsonCache) get(text, targetLang string) *cacheEntry {
	hash := c.computeHash(text, targetLang)
	entry, ok := c.Entries[hash]
	if !ok {
		return nil
	}
	if time.Since(time.Unix(entry.CreatedAt, 0)) > c.maxAge {
		delete(c.Entries, hash)
		c.save()
		return nil
	}
	return &entry
}

func (c *jsonCache) set(text, sourceLang, targetLang, translation, provider string) {
	hash := c.computeHash(text, targetLang)
	c.Entries[hash] = cacheEntry{
		TextHash:       hash,
		SourceLang:     sourceLang,
		TargetLang:     targetLang,
		OriginalText:   text,
		TranslatedText: translation,
		Provider:       provider,
		CreatedAt:      time.Now().Unix(),
	}
	c.save()
}

func (c *jsonCache) load() {
	data, err := os.ReadFile(c.path)
	if err != nil {
		return
	}
	json.Unmarshal(data, c)
	if c.Entries == nil {
		c.Entries = make(map[string]cacheEntry)
	}
}

func (c *jsonCache) save() {
	os.MkdirAll(filepath.Dir(c.path), 0755)
	data, _ := json.MarshalIndent(c, "", "  ")
	os.WriteFile(c.path, data, 0644)
}

// Google Provider

type GoogleProvider struct{}

func (g *GoogleProvider) Name() string { return "Google" }

func (g *GoogleProvider) Translate(text, sourceLang, targetLang string) (string, error) {
	u := fmt.Sprintf(
		"https://translate.googleapis.com/translate_a/single?client=gtx&sl=%s&tl=%s&dt=t&q=%s",
		sourceLang, targetLang, url.QueryEscape(text),
	)

	resp, err := http.Get(u)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	var data []interface{}
	if err := json.Unmarshal(body, &data); err != nil {
		return "", err
	}

	sentences, ok := data[0].([]interface{})
	if !ok || len(sentences) == 0 {
		return "", fmt.Errorf("invalid response")
	}

	var result string
	for _, s := range sentences {
		if arr, ok := s.([]interface{}); ok && len(arr) > 0 {
			if str, ok := arr[0].(string); ok {
				result += str
			}
		}
	}

	if result == "" {
		return "", fmt.Errorf("empty translation")
	}
	return result, nil
}

// Bing Provider

type BingProvider struct{}

func (b *BingProvider) Name() string { return "Bing" }

func (b *BingProvider) Translate(text, sourceLang, targetLang string) (string, error) {
	data := url.Values{}
	data.Set("from", sourceLang)
	data.Set("to", targetLang)
	data.Set("text", text)

	resp, err := http.PostForm("https://www.bing.com/ttranslatev3", data)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	var result []interface{}
	if err := json.Unmarshal(body, &result); err != nil {
		return "", err
	}

	if len(result) == 0 {
		return "", fmt.Errorf("invalid response")
	}

	obj, ok := result[0].(map[string]interface{})
	if !ok {
		return "", fmt.Errorf("invalid response format")
	}

	translations, ok := obj["translations"].([]interface{})
	if !ok || len(translations) == 0 {
		return "", fmt.Errorf("no translations")
	}

	translationObj, ok := translations[0].(map[string]interface{})
	if !ok {
		return "", fmt.Errorf("invalid translation format")
	}

	translated, ok := translationObj["text"].(string)
	if !ok {
		return "", fmt.Errorf("no text in translation")
	}

	return translated, nil
}
