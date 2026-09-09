const display = document.getElementById('display');
const ghost = document.getElementById('ghost');
const input = document.getElementById('cmd-input');
const form = document.getElementById('input-form');
let buffer = '';

const commands = [
    '@en hello @pt-AO',
    '@en goodbye @pt-AO',
    '@en good morning @pt-AO',
    '@en good night @pt-AO',
    '@en thank you @pt-AO',
    '@en how are you @pt-AO',
    '@pt-AO bom dia @en',
    '@pt-AO obrigado @en',
    '@pt-AO ate logo @en',
    '@pt-BR bom dia @en',
    '@pt-BR obrigado @en',
    '@pt-BR ate mais @en',
    '@login ',
    '@user',
    '@pacotes list',
    '@pacotes locais list',
    '@pt-BR download',
    '@pt-BR update',
    '@pt-BR create',
    '@pt-BR edit ',
    '@en download',
    '@en update',
    '@en create',
    '@en edit ',
    '@pt-AO download',
    '@pt-AO update',
    '@pt-AO create',
    '@pt-AO edit ',
    '@login ',
    '@pacotes list',
    '@pacotes locais list',
    'logout',
    'help',
    'stats',
    'clear',
];

function getSuggestion(text) {
    if (!text || text.length < 1) return null;
    
    const match = commands.find(c => 
        c.toLowerCase().indexOf(text.toLowerCase()) === 0 && c.length > text.length
    );
    
    return match ? match.slice(text.length) : null;
}

function updateGhost() {
    const suggestion = getSuggestion(buffer);
    if (suggestion) {
        ghost.textContent = buffer + suggestion;
    } else {
        ghost.textContent = '';
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        input.value = buffer;
        form.submit();
    } else if (e.key === 'Backspace') {
        buffer = buffer.slice(0, -1);
        display.textContent = buffer;
        updateGhost();
    } else if (e.key === 'Tab') {
        e.preventDefault();
        const suggestion = getSuggestion(buffer);
        if (suggestion) {
            buffer += suggestion;
            display.textContent = buffer;
            ghost.textContent = '';
        }
    } else if (e.key === 'ArrowRight') {
        const suggestion = getSuggestion(buffer);
        if (suggestion && display.textContent.length === buffer.length) {
            buffer += suggestion;
            display.textContent = buffer;
            ghost.textContent = '';
        }
    } else if (e.key.length === 1) {
        buffer += e.key;
        display.textContent = buffer;
        updateGhost();
    }
});
