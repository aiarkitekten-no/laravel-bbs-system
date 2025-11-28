/**
 * PUNKTET BBS - Terminal JavaScript
 * Handles terminal emulation, API communication, and BBS navigation
 */

(function() {
    'use strict';

    // =====================================================
    // Configuration & State
    // =====================================================
    
    const config = {
        baudRates: {
            2400: 4,      // ~4 chars per frame (very slow)
            9600: 16,     // ~16 chars per frame
            14400: 24,    // ~24 chars per frame
            28800: 48,    // ~48 chars per frame
            56000: 96,    // ~96 chars per frame
            0: Infinity   // Sci-Fi Speed (instant)
        },
        frameDelay: 16,   // ~60fps
        maxHistory: 50,
        linesPerPage: 23,
        totalNodes: 6     // Total available nodes
    };

    const state = {
        user: null,
        token: window.PUNKTET?.token || null,
        currentArea: 'login',
        inputHistory: [],
        historyIndex: -1,
        outputBuffer: [],
        isTyping: false,
        baudRate: 0,
        soundEnabled: true,
        currentPage: 0,
        paginationBuffer: [],
        isPaginating: false,
        isPrompting: false,
        promptResolve: null,
        nodeNumber: 1,
        node: null,
        connectedAt: null
    };

    // =====================================================
    // DOM Elements (initialized in initElements)
    // =====================================================
    
    let elements = {};

    function initElements() {
        elements = {
            output: document.getElementById('output'),
            input: document.getElementById('input'),
            prompt: document.getElementById('prompt'),
            cursor: document.getElementById('cursor'),
            nodeInfo: document.getElementById('nodeInfo'),
            currentArea: document.getElementById('currentArea'),
            userInfo: document.getElementById('userInfo'),
            timeRemaining: document.getElementById('timeRemaining'),
            connectionSpeed: document.getElementById('connectionSpeed'),
            statusMessage: document.getElementById('statusMessage'),
            settingsModal: document.getElementById('settingsModal'),
            speedSelect: document.getElementById('speedSelect'),
            fontSizeSelect: document.getElementById('fontSizeSelect'),
            themeSelect: document.getElementById('themeSelect'),
            soundEnabled: document.getElementById('soundEnabled'),
            scanlineEnabled: document.getElementById('scanlineEnabled'),
            soundToggle: document.getElementById('soundToggle'),
            settingsBtn: document.getElementById('settingsBtn'),
            closeSettings: document.getElementById('closeSettings'),
            modemConnect: document.getElementById('modemConnect'),
            keyClick: document.getElementById('keyClick'),
            bellSound: document.getElementById('bellSound')
        };
    }

    // =====================================================
    // API Functions
    // =====================================================
    
    async function api(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Accept-Language': window.PUNKTET?.locale || 'en',
                'X-CSRF-TOKEN': window.PUNKTET?.csrfToken || ''
            }
        };

        if (state.token) {
            options.headers['Authorization'] = `Bearer ${state.token}`;
        }

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(`${window.PUNKTET?.apiBase || '/api'}${endpoint}`, options);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.message || 'API Error');
            }
            
            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    function initElements() {
        elements = {
            output: document.getElementById('output'),
            input: document.getElementById('input'),
            prompt: document.getElementById('prompt'),
            cursor: document.getElementById('cursor'),
            nodeInfo: document.getElementById('nodeInfo'),
            currentArea: document.getElementById('currentArea'),
            userInfo: document.getElementById('userInfo'),
            timeRemaining: document.getElementById('timeRemaining'),
            connectionSpeed: document.getElementById('connectionSpeed'),
            statusMessage: document.getElementById('statusMessage'),
            settingsModal: document.getElementById('settingsModal'),
            speedSelect: document.getElementById('speedSelect'),
            fontSizeSelect: document.getElementById('fontSizeSelect'),
            themeSelect: document.getElementById('themeSelect'),
            soundEnabled: document.getElementById('soundEnabled'),
            scanlineEnabled: document.getElementById('scanlineEnabled'),
            soundToggle: document.getElementById('soundToggle'),
            settingsBtn: document.getElementById('settingsBtn'),
            closeSettings: document.getElementById('closeSettings'),
            modemConnect: document.getElementById('modemConnect'),
            keyClick: document.getElementById('keyClick'),
            bellSound: document.getElementById('bellSound')
        };
    }

    // =====================================================
    // Output Functions
    // =====================================================
    
    function print(text, options = {}) {
        const { cls = false, newline = true, speed = state.baudRate } = options;
        
        if (cls) {
            elements.output.innerHTML = '';
        }

        const fullText = newline ? text + '\n' : text;
        
        if (speed === 0 || speed === Infinity) {
            // Instant output
            appendOutput(fullText);
        } else {
            // Simulate baud rate
            typeText(fullText, config.baudRates[speed] || Infinity);
        }
    }

    function appendOutput(text) {
        const parsed = parseANSI(text);
        elements.output.innerHTML += parsed;
        scrollToBottom();
    }

    async function typeText(text, charsPerFrame) {
        state.isTyping = true;
        elements.input.disabled = true;
        
        let index = 0;
        const chars = text.split('');
        
        while (index < chars.length && state.isTyping) {
            const chunk = chars.slice(index, index + charsPerFrame).join('');
            appendOutput(chunk);
            index += charsPerFrame;
            
            if (index < chars.length) {
                await sleep(config.frameDelay);
            }
        }
        
        state.isTyping = false;
        elements.input.disabled = false;
        elements.input.focus();
    }

    function parseANSI(text) {
        // First, escape HTML to prevent XSS
        let result = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Parse ANSI escape codes and convert to HTML/CSS
        const ansiMap = {
            '0': '</span>',
            '1': '<span class="ansi-bold">',
            '2': '<span class="ansi-dim">',
            '4': '<span class="ansi-underline">',
            '5': '<span class="ansi-blink">',
            '7': '<span class="ansi-reverse">',
            '30': '<span class="ansi-black">',
            '31': '<span class="ansi-red">',
            '32': '<span class="ansi-green">',
            '33': '<span class="ansi-yellow">',
            '34': '<span class="ansi-blue">',
            '35': '<span class="ansi-magenta">',
            '36': '<span class="ansi-cyan">',
            '37': '<span class="ansi-white">',
            '90': '<span class="ansi-bright-black">',
            '91': '<span class="ansi-bright-red">',
            '92': '<span class="ansi-bright-green">',
            '93': '<span class="ansi-bright-yellow">',
            '94': '<span class="ansi-bright-blue">',
            '95': '<span class="ansi-bright-magenta">',
            '96': '<span class="ansi-bright-cyan">',
            '97': '<span class="ansi-bright-white">'
        };

        // Replace ANSI codes
        result = result.replace(/\x1b\[([0-9;]+)m/g, (match, codes) => {
            const parts = codes.split(';');
            return parts.map(code => ansiMap[code] || '').join('');
        });

        // Also support our shorthand: |R for red, |G for green, etc.
        const colorShortcuts = {
            '|K': '<span class="ansi-black">',
            '|R': '<span class="ansi-red">',
            '|G': '<span class="ansi-green">',
            '|Y': '<span class="ansi-yellow">',
            '|B': '<span class="ansi-blue">',
            '|M': '<span class="ansi-magenta">',
            '|C': '<span class="ansi-cyan">',
            '|W': '<span class="ansi-white">',
            '|k': '<span class="ansi-bright-black">',
            '|r': '<span class="ansi-bright-red">',
            '|g': '<span class="ansi-bright-green">',
            '|y': '<span class="ansi-bright-yellow">',
            '|b': '<span class="ansi-bright-blue">',
            '|m': '<span class="ansi-bright-magenta">',
            '|c': '<span class="ansi-bright-cyan">',
            '|w': '<span class="ansi-bright-white">',
            '|N': '</span>',
            '|*': '<span class="ansi-bold">',
            '|_': '<span class="ansi-underline">'
        };

        for (const [code, html] of Object.entries(colorShortcuts)) {
            result = result.split(code).join(html);
        }

        return result;
    }

    function clearScreen() {
        elements.output.innerHTML = '';
    }

    function scrollToBottom() {
        elements.output.scrollTop = elements.output.scrollHeight;
    }

    // =====================================================
    // Pagination
    // =====================================================
    
    function paginate(lines) {
        if (lines.length <= config.linesPerPage) {
            lines.forEach(line => print(line));
            return;
        }

        state.paginationBuffer = lines;
        state.currentPage = 0;
        state.isPaginating = true;
        showPage();
    }

    function showPage() {
        const start = state.currentPage * config.linesPerPage;
        const end = start + config.linesPerPage;
        const pageLines = state.paginationBuffer.slice(start, end);
        
        pageLines.forEach(line => print(line));
        
        if (end < state.paginationBuffer.length) {
            print('|M--- More? (Y)es, (N)o, (C)ontinuous ---|N', { newline: false });
        } else {
            state.isPaginating = false;
        }
    }

    function handlePagination(key) {
        if (!state.isPaginating) return false;
        
        const k = key.toLowerCase();
        
        if (k === 'y' || k === '' || k === 'enter') {
            print('');
            state.currentPage++;
            showPage();
        } else if (k === 'n' || k === 'q') {
            print('');
            state.isPaginating = false;
        } else if (k === 'c') {
            print('');
            // Show all remaining
            const start = (state.currentPage + 1) * config.linesPerPage;
            state.paginationBuffer.slice(start).forEach(line => print(line));
            state.isPaginating = false;
        }
        
        return true;
    }

    // =====================================================
    // Sound Functions (with Web Audio API fallback)
    // =====================================================
    
    let audioContext = null;
    
    function getAudioContext() {
        if (!audioContext) {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
        return audioContext;
    }
    
    function playSound(soundId) {
        if (!state.soundEnabled) return;
        
        const audio = elements[soundId];
        if (audio && audio.src && audio.duration > 0) {
            audio.currentTime = 0;
            audio.play().catch(() => {}); // Ignore autoplay restrictions
        } else {
            // Fallback to Web Audio API generated sounds
            playGeneratedSound(soundId);
        }
    }
    
    function playGeneratedSound(soundId) {
        if (!state.soundEnabled) return;
        
        try {
            const ctx = getAudioContext();
            const oscillator = ctx.createOscillator();
            const gainNode = ctx.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(ctx.destination);
            
            switch (soundId) {
                case 'keyClick':
                    // Short click sound
                    oscillator.type = 'square';
                    oscillator.frequency.setValueAtTime(800, ctx.currentTime);
                    gainNode.gain.setValueAtTime(0.1, ctx.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.05);
                    oscillator.start(ctx.currentTime);
                    oscillator.stop(ctx.currentTime + 0.05);
                    break;
                    
                case 'bellSound':
                    // Bell/beep sound
                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(880, ctx.currentTime);
                    gainNode.gain.setValueAtTime(0.3, ctx.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                    oscillator.start(ctx.currentTime);
                    oscillator.stop(ctx.currentTime + 0.3);
                    break;
                    
                case 'modemConnect':
                    // Simulate modem handshake sounds
                    playModemHandshake(ctx);
                    break;
                    
                case 'transferStart':
                    // ZMODEM start sound
                    oscillator.type = 'square';
                    oscillator.frequency.setValueAtTime(1200, ctx.currentTime);
                    oscillator.frequency.setValueAtTime(2400, ctx.currentTime + 0.1);
                    gainNode.gain.setValueAtTime(0.15, ctx.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2);
                    oscillator.start(ctx.currentTime);
                    oscillator.stop(ctx.currentTime + 0.2);
                    break;
                    
                case 'transferComplete':
                    // Success chime
                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(523.25, ctx.currentTime); // C5
                    oscillator.frequency.setValueAtTime(659.25, ctx.currentTime + 0.1); // E5
                    oscillator.frequency.setValueAtTime(783.99, ctx.currentTime + 0.2); // G5
                    gainNode.gain.setValueAtTime(0.2, ctx.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
                    oscillator.start(ctx.currentTime);
                    oscillator.stop(ctx.currentTime + 0.4);
                    break;
            }
        } catch (e) {
            // Audio not supported
        }
    }
    
    function playModemHandshake(ctx) {
        // Simulate classic modem negotiation sounds
        const now = ctx.currentTime;
        
        // Dial tone simulation
        const osc1 = ctx.createOscillator();
        const osc2 = ctx.createOscillator();
        const gain = ctx.createGain();
        
        osc1.connect(gain);
        osc2.connect(gain);
        gain.connect(ctx.destination);
        
        // US dial tone frequencies
        osc1.frequency.value = 350;
        osc2.frequency.value = 440;
        osc1.type = 'sine';
        osc2.type = 'sine';
        
        gain.gain.setValueAtTime(0.1, now);
        gain.gain.setValueAtTime(0, now + 0.5);
        
        // Carrier tones
        gain.gain.setValueAtTime(0.15, now + 0.6);
        osc1.frequency.setValueAtTime(1200, now + 0.6);
        osc2.frequency.setValueAtTime(2400, now + 0.6);
        
        // Negotiation noise
        gain.gain.setValueAtTime(0.08, now + 1.0);
        osc1.frequency.setValueAtTime(1800, now + 1.0);
        osc2.frequency.setValueAtTime(2100, now + 1.0);
        
        gain.gain.exponentialRampToValueAtTime(0.01, now + 2.0);
        
        osc1.start(now);
        osc2.start(now);
        osc1.stop(now + 2.0);
        osc2.stop(now + 2.0);
    }

    function playModemSound() {
        playSound('modemConnect');
    }

    function playKeyClick() {
        playSound('keyClick');
    }

    function playBell() {
        playSound('bellSound');
    }
    
    function playTransferStart() {
        playGeneratedSound('transferStart');
    }
    
    function playTransferComplete() {
        playGeneratedSound('transferComplete');
    }

    // =====================================================
    // Transfer Protocol Simulation (ZMODEM style)
    // =====================================================
    
    async function simulateZmodemTransfer(filename, fileSize, isUpload = false) {
        const direction = isUpload ? 'Uploading' : 'Downloading';
        const protocol = 'ZMODEM';
        
        playTransferStart();
        
        print('');
        print(`|c╔═══════════════════════════════════════════════════════════════╗|N`);
        print(`|c║|N  |W${protocol} File Transfer|N                                        |c║|N`);
        print(`|c╠═══════════════════════════════════════════════════════════════╣|N`);
        print(`|c║|N  File: |Y${filename.substring(0, 45).padEnd(45)}|N       |c║|N`);
        print(`|c║|N  Size: |Y${formatFileSize(fileSize).padEnd(45)}|N       |c║|N`);
        print(`|c║|N  ${direction}: |G[                                        ]|N  |c║|N`);
        print(`|c╚═══════════════════════════════════════════════════════════════╝|N`);
        
        // Calculate transfer time based on baud rate
        const effectiveBaud = state.baudRate || 56000;
        const bytesPerSecond = effectiveBaud / 10; // Approximate
        const totalTime = Math.max(1, Math.min(10, fileSize / bytesPerSecond));
        const steps = 40;
        const stepTime = (totalTime * 1000) / steps;
        
        for (let i = 1; i <= steps; i++) {
            await sleep(stepTime);
            
            const progress = '█'.repeat(i) + '░'.repeat(steps - i);
            const percent = Math.round((i / steps) * 100);
            const transferred = Math.round((i / steps) * fileSize);
            const cps = Math.round(transferred / (i * stepTime / 1000));
            
            // Update progress line (move cursor up and rewrite)
            // Since we can't easily do cursor movement, we'll just print status
            if (i === steps || i % 10 === 0) {
                print(`|c  Progress: |G${percent}%|N  |c${formatFileSize(transferred)}|N / |c${formatFileSize(fileSize)}|N  |cCPS: ${cps}|N`);
            }
        }
        
        playTransferComplete();
        
        print('');
        print(`|G✓ Transfer complete: ${filename}|N`);
        print(`|c  Protocol: ${protocol}  |N|c Block size: 1024|N`);
        print('');
        
        return true;
    }
    
    // Alternative simpler progress display
    async function showTransferProgress(filename, fileSize, downloadUrl) {
        print('');
        print(`|c┌─────────────────────────────────────────────────────────────────┐|N`);
        print(`|c│|N |WZMODEM|N Transfer Protocol                                      |c│|N`);
        print(`|c├─────────────────────────────────────────────────────────────────┤|N`);
        print(`|c│|N File: |Y${filename.substring(0, 50).padEnd(50)}|N    |c│|N`);
        print(`|c│|N Size: |Y${formatFileSize(fileSize).padEnd(50)}|N    |c│|N`);
        print(`|c└─────────────────────────────────────────────────────────────────┘|N`);
        
        playTransferStart();
        
        // Simulate quick transfer animation
        const frames = ['⣾', '⣽', '⣻', '⢿', '⡿', '⣟', '⣯', '⣷'];
        for (let i = 0; i < 8; i++) {
            print(`|c  ${frames[i]} Transferring...|N`, { newline: false });
            await sleep(150);
            // Clear line simulation
            print('\r                           \r', { newline: false });
        }
        
        playTransferComplete();
        print('|G  ✓ Transfer initiated successfully!|N');
        print('');
        
        // Actually open download
        if (downloadUrl) {
            window.open(downloadUrl, '_blank');
        }
    }

    // =====================================================
    // Input Handling
    // =====================================================
    
    function setupInput() {
        elements.input.addEventListener('keydown', handleKeyDown);
        elements.input.addEventListener('input', handleInput);
        document.addEventListener('click', () => elements.input.focus());
        elements.input.focus();
    }

    function handleKeyDown(e) {
        playKeyClick();
        
        if (e.key === 'Enter') {
            e.preventDefault();
            processInput();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            navigateHistory(-1);
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            navigateHistory(1);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            elements.input.value = '';
            if (state.isTyping) {
                state.isTyping = false;
            }
        }
    }

    function handleInput() {
        // Update cursor position (visual only, handled by CSS)
    }

    function navigateHistory(direction) {
        if (state.inputHistory.length === 0) return;
        
        state.historyIndex += direction;
        
        if (state.historyIndex < 0) {
            state.historyIndex = 0;
        } else if (state.historyIndex >= state.inputHistory.length) {
            state.historyIndex = state.inputHistory.length;
            elements.input.value = '';
            return;
        }
        
        elements.input.value = state.inputHistory[state.historyIndex] || '';
    }

    async function processInput() {
        const input = elements.input.value.trim();
        elements.input.value = '';
        
        // Check if we're waiting for a prompt response
        if (state.isPrompting && state.promptResolve) {
            const resolve = state.promptResolve;
            state.isPrompting = false;
            state.promptResolve = null;
            elements.input.type = 'text'; // Reset input type
            resolve(input);
            return;
        }
        
        // Check pagination first
        if (state.isPaginating) {
            handlePagination(input);
            return;
        }

        // Echo input
        print(`|G>${input}|N`);
        
        if (input) {
            // Add to history
            state.inputHistory.unshift(input);
            if (state.inputHistory.length > config.maxHistory) {
                state.inputHistory.pop();
            }
            state.historyIndex = -1;
        }

        // Route command
        await routeCommand(input.toLowerCase(), input);
    }

    // =====================================================
    // Command Router
    // =====================================================
    
    async function routeCommand(cmd, original) {
        // Global commands (available everywhere EXCEPT login screen)
        const globalCommands = {
            'cls': clearScreen,
            'clear': clearScreen,
            'help': showHelp,
            '?': showHelp,
            'quit': logout,
            'bye': logout,
            'settings': showSettings,
            'who': showWhosOnline,
            'w': showWhosOnline,
            'version': showVersion,
            'time': showTime
        };

        // Only process global commands if NOT on login screen
        if (state.currentArea !== 'login' && globalCommands[cmd]) {
            await globalCommands[cmd]();
            return;
        }
        
        // Handle 'g' specially - logout only when logged in
        if (cmd === 'g' && state.currentArea !== 'login' && state.token) {
            await logout();
            return;
        }

        // Route based on current area
        switch (state.currentArea) {
            case 'login':
                await handleLogin(cmd, original);
                break;
            case 'main':
                await handleMainMenu(cmd, original);
                break;
            case 'messages':
                await handleMessages(cmd, original);
                break;
            case 'thread-list':
                await handleThreadList(cmd, original);
                break;
            case 'thread-read':
                await handleThreadRead(cmd, original);
                break;
            case 'files':
                await handleFiles(cmd, original);
                break;
            case 'file-list':
                await handleFileList(cmd, original);
                break;
            case 'games':
                await handleGames(cmd, original);
                break;
            case 'game-playing':
                await handleGamePlaying(cmd, original);
                break;
            case 'stories':
                await handleStories(cmd, original);
                break;
            case 'story-archive':
                await handleStoryArchive(cmd, original);
                break;
            case 'polls':
                await handlePolls(cmd, original);
                break;
            default:
                print('|rUnknown command. Type |YHELP|r for assistance.|N');
        }
    }

    // =====================================================
    // Login/Auth Area
    // =====================================================
    
    async function handleLogin(cmd, original) {
        if (cmd === 'n' || cmd === 'new') {
            await showRegistration();
        } else if (cmd === 'l' || cmd === 'login' || cmd.includes('@')) {
            await doLogin(original);
        } else if (cmd === 'g' || cmd === 'guest') {
            await guestLogin();
        } else if (cmd === '') {
            showLoginPrompt();
        } else {
            print('|rInvalid option. Press |YL|r to login, |YN|r for new user, or |YG|r for guest.|N');
        }
    }

    function showLoginPrompt() {
        clearScreen();
        print('');
        print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('|B█                                                                         █|N');
        print('|B█|G  ██████╗ ██╗   ██╗███╗   ██╗██╗  ██╗████████╗███████╗████████╗        |B█|N');
        print('|B█|G  ██╔══██╗██║   ██║████╗  ██║██║ ██╔╝╚══██╔══╝██╔════╝╚══██╔══╝        |B█|N');
        print('|B█|G  ██████╔╝██║   ██║██╔██╗ ██║█████╔╝    ██║   █████╗     ██║           |B█|N');
        print('|B█|G  ██╔═══╝ ██║   ██║██║╚██╗██║██╔═██╗    ██║   ██╔══╝     ██║           |B█|N');
        print('|B█|G  ██║     ╚██████╔╝██║ ╚████║██║  ██╗   ██║   ███████╗   ██║           |B█|N');
        print('|B█|G  ╚═╝      ╚═════╝ ╚═╝  ╚═══╝╚═╝  ╚═╝   ╚═╝   ╚══════╝   ╚═╝           |B█|N');
        print('|B█|N                                                                       |B█|N');
        print('|B█|Y             ══════ BULLETIN BOARD SYSTEM ══════                       |B█|N');
        print('|B█|c                 "Where Nostalgia Meets The Future"                    |B█|N');
        print('|B█                                                                         █|N');
        print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
        print('');
        print('|R▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|G▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|C▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('|N                                                                           |N');
        print('|N    |Y L|G  Login (Existing User)     |Y N|G  New User Registration            |N');
        print('|N    |Y G|c  Guest Access (Limited)    |Y ?|c  Help & System Info               |N');
        print('|N                                                                           |N');
        print('|R▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|G▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|C▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
        print('');
        print('|K SysOp  : |WTerje             |K│ Location: |WNorway                            |N');
        print('|K Est.   : |W2025              |K│ Nodes   : |G' + config.totalNodes + ' available                         |N');
        print('|K Version: |W1.0               |K│ Users   : |cOnline now!                       |N');
        print('');
        setPrompt('Your Choice');
    }

    async function doLogin(input) {
        print('');
        
        const email = await promptUser('|cEmail|N');
        const password = await promptUser('|cPassword|N', true);
        
        try {
            // Start modem handshake animation
            const skipped = await showLoginHandshake();
            
            setStatus('Authenticating...');
            const result = await api('/auth/login', 'POST', { email, password });
            
            state.token = result.token;
            state.user = result.user;
            state.node = result.node;
            state.connectedAt = Date.now();
            localStorage.setItem('punktet_token', result.token);
            
            if (!skipped) {
                await showLoginSuccess(state.user.handle || state.user.username);
            } else {
                print('');
                print('|G✓ Login successful!|N Welcome back, |W' + (state.user.handle || state.user.username) + '|N');
                print('');
            }
            
            updateUserDisplay();
            goToMainMenu();
            
        } catch (error) {
            print(`|rLogin failed: ${error.message}|N`);
            showLoginPrompt();
        } finally {
            setStatus('');
        }
    }
    
    // Modem handshake animation for login - the nostalgic experience!
    async function showLoginHandshake() {
        let skipped = false;
        let skipListener = null;
        let canSkip = false;
        
        // Delay before allowing skip to prevent accidental skips from Enter key
        setTimeout(() => { canSkip = true; }, 300);
        
        // Allow skipping with specific keys (after delay)
        const skipPromise = new Promise(resolve => {
            skipListener = (e) => {
                if (canSkip && (e.key === 'Escape' || e.key === ' ')) {
                    skipped = true;
                    resolve();
                }
            };
            document.addEventListener('keydown', skipListener);
        });
        
        clearScreen();
        print('|K═══════════════════════════════════════════════════════════════════════|N');
        print('|c  PUNKTET BBS - Modem Connection                                       |N');
        print('|K═══════════════════════════════════════════════════════════════════════|N');
        print('');
        print('|K                    Press [SPACE] or [ESC] to skip                     |N');
        print('');
        
        // AT Commands sequence
        const atCommands = [
            { cmd: 'ATZ', response: 'OK', delay: 400 },
            { cmd: 'AT&F', response: 'OK', delay: 300 },
            { cmd: 'ATE1V1', response: 'OK', delay: 250 },
            { cmd: 'AT+MS=V34', response: 'OK', delay: 300 },
            { cmd: 'ATX4', response: 'OK', delay: 200 },
            { cmd: 'ATDT punktet.no', response: null, delay: 500 }
        ];
        
        for (const at of atCommands) {
            if (skipped) break;
            print(`|c${at.cmd}|N`);
            await Promise.race([sleep(at.delay), skipPromise]);
            if (skipped) break;
            if (at.response) {
                print(`|G${at.response}|N`);
            }
        }
        
        if (!skipped) {
            print('');
            print('|YDIALING...|N');
            await Promise.race([sleep(400), skipPromise]);
        }
        
        // Play modem sound (the iconic screech!)
        if (!skipped) {
            playModemSound();
        }
        
        if (!skipped) {
            print('');
            print('|c  ┌─────────────────────────────────────────────────────────────────┐|N');
            print('|c  │|N                                                                 |c│|N');
            
            // Carrier detect sequence
            const carrierStages = [
                '|Y  │   RING... RING...                                               │|N',
                '|Y  │   CARRIER DETECT                                                │|N',
                '|c  │   ▒▒▒▒▒▒▒▒▒▒▒▒ Training sequence ▒▒▒▒▒▒▒▒▒▒▒▒                   │|N',
            ];
            
            for (const stage of carrierStages) {
                if (skipped) break;
                print(stage);
                await Promise.race([sleep(600), skipPromise]);
            }
        }
        
        // Handshake negotiation with animated characters
        if (!skipped) {
            const handshakeFrames = [
                '░░▒▒▓▓██▓▓▒▒░░ V.34 Handshake ░░▒▒▓▓██▓▓▒▒░░',
                '▒▒▓▓██▓▓▒▒░░░░ V.34 Handshake ▒▒▓▓██▓▓▒▒░░░░',
                '▓▓██▓▓▒▒░░░░▒▒ V.34 Handshake ▓▓██▓▓▒▒░░░░▒▒',
                '██▓▓▒▒░░░░▒▒▓▓ V.34 Handshake ██▓▓▒▒░░░░▒▒▓▓',
            ];
            
            for (let i = 0; i < 8 && !skipped; i++) {
                print(`|M  │   ${handshakeFrames[i % handshakeFrames.length]}   │|N`, { newline: false });
                await Promise.race([sleep(150), skipPromise]);
                if (!skipped) {
                    // Clear line and move up
                    print('\r                                                                              \r', { newline: false });
                }
            }
            
            if (!skipped) {
                print('|G  │   ████████████████ CONNECTED ████████████████                  │|N');
            }
        }
        
        // Protocol negotiation
        if (!skipped) {
            print('|c  │|N                                                                 |c│|N');
            await Promise.race([sleep(300), skipPromise]);
            
            const protocols = [
                'Error correction: V.42bis',
                'Data compression: MNP5',
                'Protocol: 8-N-1'
            ];
            
            for (const proto of protocols) {
                if (skipped) break;
                print(`|c  │   |K${proto.padEnd(55)}|c│|N`);
                await Promise.race([sleep(200), skipPromise]);
            }
        }
        
        if (!skipped) {
            print('|c  │|N                                                                 |c│|N');
            print('|c  └─────────────────────────────────────────────────────────────────┘|N');
            print('');
            
            // Final connect speed
            const speedNames = {
                0: '115200',
                2400: '2400',
                9600: '9600',
                14400: '14400',
                28800: '28800',
                56000: '56000'
            };
            
            const speed = speedNames[state.baudRate] || '56000';
            print(`|GCONNECT ${speed}/ARQ/V34/LAPM/V42BIS|N`);
            await Promise.race([sleep(400), skipPromise]);
        }
        
        // Clean up listener
        if (skipListener) {
            document.removeEventListener('keydown', skipListener);
        }
        
        if (!skipped) {
            print('');
            print('|Y  Verifying credentials...|N');
            await sleep(300);
        }
        
        return skipped;
    }
    
    async function showLoginSuccess(username) {
        print('');
        await sleep(200);
        
        // ASCII art success banner
        print('|G╔══════════════════════════════════════════════════════════════════════╗|N');
        print('|G║|N                                                                      |G║|N');
        print('|G║|Y    █████╗  ██████╗ ██████╗███████╗███████╗███████╗                   |G║|N');
        print('|G║|Y   ██╔══██╗██╔════╝██╔════╝██╔════╝██╔════╝██╔════╝                   |G║|N');
        print('|G║|Y   ███████║██║     ██║     █████╗  ███████╗███████╗                   |G║|N');
        print('|G║|Y   ██╔══██║██║     ██║     ██╔══╝  ╚════██║╚════██║                   |G║|N');
        print('|G║|Y   ██║  ██║╚██████╗╚██████╗███████╗███████║███████║                   |G║|N');
        print('|G║|Y   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝╚══════╝╚══════╝╚══════╝                   |G║|N');
        print('|G║|N                                                                      |G║|N');
        print('|G║|c         G R A N T E D   -   W E L C O M E   B A C K                 |G║|N');
        print('|G║|N                                                                      |G║|N');
        print('|G║|W                    ' + username.padEnd(20) + '                           |G║|N');
        print('|G║|N                                                                      |G║|N');
        print('|G╚══════════════════════════════════════════════════════════════════════╝|N');
        print('');
        
        await sleep(800);
    }

    async function guestLogin() {
        try {
            // Start modem handshake animation
            const skipped = await showLoginHandshake();
            
            setStatus('Connecting as guest...');
            const result = await api('/auth/guest', 'POST');
            
            state.token = result.token;
            state.user = result.user;
            state.node = result.node;
            state.connectedAt = Date.now();
            localStorage.setItem('punktet_token', result.token);
            
            if (!skipped) {
                print('');
                await sleep(200);
                print('|G╔══════════════════════════════════════════════════════════════════════╗|N');
                print('|G║|N                                                                      |G║|N');
                print('|G║|Y           ★ ★ ★  GUEST ACCESS GRANTED  ★ ★ ★                        |G║|N');
                print('|G║|N                                                                      |G║|N');
                print('|G║|K             Some features are limited for guests                    |G║|N');
                print('|G║|K               Register to unlock full access!                       |G║|N');
                print('|G║|N                                                                      |G║|N');
                print('|G╚══════════════════════════════════════════════════════════════════════╝|N');
                print('');
                await sleep(600);
            } else {
                print('');
                print('|G✓ Connected as guest|N (some features limited)');
                print('');
            }
            
            updateUserDisplay();
            goToMainMenu();
            
        } catch (error) {
            print(`|rGuest login failed: ${error.message}|N`);
        } finally {
            setStatus('');
        }
    }

    async function showRegistration() {
        print('');
        print('|y╔══════════════════════════════════════════════════════════════╗|N');
        print('|y║              NEW USER REGISTRATION                           ║|N');
        print('|y╚══════════════════════════════════════════════════════════════╝|N');
        print('');
        print('|cPlease provide the following information:|N');
        print('|K(Handle can only contain letters, numbers, dashes, and underscores)|N');
        print('');
        
        // Get handle with validation
        let handle = '';
        while (!handle) {
            handle = await promptUser('Handle (username)');
            if (!handle || handle.trim() === '') {
                print('|rHandle is required!|N');
                handle = '';
                continue;
            }
            // Check format - only allow alphanumeric, dash, underscore
            if (!/^[a-zA-Z0-9_-]+$/.test(handle)) {
                print('|rHandle can only contain letters, numbers, dashes (-) and underscores (_)|N');
                print('|rNo spaces allowed! Example: |YBjarneH|r or |YBjarne_Hansen|N');
                handle = '';
                continue;
            }
            if (handle.length < 3) {
                print('|rHandle must be at least 3 characters!|N');
                handle = '';
                continue;
            }
        }
        
        // Get email with validation
        let email = '';
        while (!email) {
            email = await promptUser('Email');
            if (!email || !email.includes('@')) {
                print('|rPlease enter a valid email address!|N');
                email = '';
                continue;
            }
        }
        
        // Get password with validation
        let password = '';
        while (!password) {
            password = await promptUser('Password', true);
            if (!password || password.length < 6) {
                print('|rPassword must be at least 6 characters!|N');
                password = '';
                continue;
            }
        }
        
        // Confirm password
        let confirmPass = '';
        while (confirmPass !== password) {
            confirmPass = await promptUser('Confirm Password', true);
            if (confirmPass !== password) {
                print('|rPasswords do not match! Try again.|N');
            }
        }
        
        const realName = await promptUser('Real name (optional)');
        const location = await promptUser('Location (optional)');
        
        try {
            setStatus('Creating account...');
            
            // Show modem handshake for registration too
            const skipped = await showLoginHandshake();
            
            const result = await api('/auth/register', 'POST', {
                handle,
                email,
                password,
                password_confirmation: password,
                real_name: realName || null,
                location: location || null
            });
            
            state.token = result.data?.token || result.token;
            state.user = result.data?.user || result.user;
            state.node = result.data?.node || result.node;
            localStorage.setItem('punktet_token', state.token);
            
            if (!skipped) {
                print('');
                await sleep(200);
                print('|G╔══════════════════════════════════════════════════════════════════════╗|N');
                print('|G║|N                                                                      |G║|N');
                print('|G║|Y   ███╗   ██╗███████╗██╗    ██╗    ██╗   ██╗███████╗███████╗██████╗   |G║|N');
                print('|G║|Y   ████╗  ██║██╔════╝██║    ██║    ██║   ██║██╔════╝██╔════╝██╔══██╗  |G║|N');
                print('|G║|Y   ██╔██╗ ██║█████╗  ██║ █╗ ██║    ██║   ██║███████╗█████╗  ██████╔╝  |G║|N');
                print('|G║|Y   ██║╚██╗██║██╔══╝  ██║███╗██║    ██║   ██║╚════██║██╔══╝  ██╔══██╗  |G║|N');
                print('|G║|Y   ██║ ╚████║███████╗╚███╔███╔╝    ╚██████╔╝███████║███████╗██║  ██║  |G║|N');
                print('|G║|Y   ╚═╝  ╚═══╝╚══════╝ ╚══╝╚══╝      ╚═════╝ ╚══════╝╚══════╝╚═╝  ╚═╝  |G║|N');
                print('|G║|N                                                                      |G║|N');
                print('|G║|c             A C C O U N T   C R E A T E D !                          |G║|N');
                print('|G║|N                                                                      |G║|N');
                print('|G║|W           Welcome to PUNKTET BBS, ' + handle.padEnd(20) + '          |G║|N');
                print('|G║|K               You are connected on Node ' + String(state.node || '?').padEnd(3) + '                    |G║|N');
                print('|G║|N                                                                      |G║|N');
                print('|G╚══════════════════════════════════════════════════════════════════════╝|N');
                print('');
                await sleep(800);
            } else {
                print('');
                print('|G✓ Account created!|N Welcome to PUNKTET, |W' + handle + '|N');
                print('');
            }
            
            updateUserDisplay();
            goToMainMenu();
            
        } catch (error) {
            print('');
            print(`|rRegistration failed: ${error.message}|N`);
            print('|KPress any key to try again...|N');
            await promptUser('');
            showLoginPrompt();
        } finally {
            setStatus('');
        }
    }

    async function logout() {
        if (state.token) {
            try {
                await api('/auth/logout', 'POST');
            } catch (e) {
                // Ignore logout errors
            }
        }
        
        state.token = null;
        state.user = null;
        localStorage.removeItem('punktet_token');
        
        print('');
        print('|yThank you for visiting PUNKTET BBS!|N');
        print('|cCall again soon...|N');
        print('');
        
        state.currentArea = 'login';
        updateUserDisplay();
        showLoginPrompt();
    }

    // =====================================================
    // Main Menu
    // =====================================================
    
    function goToMainMenu() {
        state.currentArea = 'main';
        elements.currentArea.textContent = 'Main Menu';
        showMainMenu();
    }

    function showMainMenu() {
        clearScreen();
        print('');
        print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W Main Menu |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|Y PUNKTET BBS |B▀▀▀▀▀▀|N');
        print('|B█|R▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|B█|G▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|B█|C▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|B█|N');
        print('|B█|N                        |B█|N                      |B█|N                      |B█|N');
        print('|B█|N |Y M|N  |GMessage Areas    |B█|N |Y J|N  |GJoin Conference  |B█|N |Y W|N  |cWho\'s Online    |B█|N');
        print('|B█|N |Y P|N  |GPrivate Messages |B█|N |Y U|N  |GUser Settings    |B█|N |Y I|N  |cSystem Info     |B█|N');
        print('|B█|N |Y F|N  |GFile Areas       |B█|N |Y C|N  |GChat with SysOp  |B█|N |Y L|N  |cLast Callers    |B█|N');
        print('|B█|N |Y D|N  |GDoor Games       |B█|N |Y S|N  |GAI Stories       |B█|N |Y G|N  |RGoodbye/Logoff  |B█|N');
        print('|B█|N |Y B|N  |GBulletins        |B█|N |Y O|N  |GOneliners        |B█|N |Y H|N  |cHelp & Info     |B█|N');
        print('|B█|N |Y ?|N  |GCommand Help     |B█|N |Y A|N  |GANSI Art Gallery |B█|N |Y V|N  |cVote in Polls   |B█|N');
        print('|B█|N                        |B█|N                      |B█|N                      |B█|N');
        print('|B█|R▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|B█|G▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|B█|C▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|B█|N');
        print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
        print('');
        print('|K Conference : |W' + (state.currentConference || 'Main') + '|N');
        print('|K Time Left  : |G' + getTimeRemaining() + '|N  |KTime On : |c' + getTimeOnline() + '|N');
        print('');
        print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        setPrompt('Main Menu Command');
    }
    
    function getTimeOnline() {
        if (!state.connectedAt) return '0:00';
        const diff = Math.floor((Date.now() - state.connectedAt) / 1000);
        const mins = Math.floor(diff / 60);
        const secs = diff % 60;
        return mins + ':' + secs.toString().padStart(2, '0');
    }
    
    function getTimeRemaining() {
        // Guest gets 15 min, members more
        const limit = state.user?.time_limit || 15;
        if (!state.connectedAt) return limit + ':00';
        const elapsed = Math.floor((Date.now() - state.connectedAt) / 60000);
        const remaining = Math.max(0, limit - elapsed);
        return remaining + ' min';
    }

    async function handleMainMenu(cmd, original) {
        const commands = {
            'm': () => goToArea('messages', 'Message Areas'),
            'p': showPrivateMessages,
            'f': () => goToArea('files', 'File Areas'),
            'd': () => goToArea('games', 'Door Games'),
            's': () => goToArea('stories', 'AI Stories'),
            'o': showOneliners,
            'j': showConferences,
            'b': showBulletins,
            'a': showAnsiGallery,
            'v': showPolls,
            'i': showSystemInfo,
            'l': showLastCallers,
            'h': showHelp,
            '?': showHelp,
            'c': chatWithSysop,
            'w': showWhosOnline,
            'u': showUserSettings,
            '': showMainMenu
        };

        if (commands[cmd]) {
            await commands[cmd]();
        } else {
            print('|rInvalid option.|N');
        }
    }

    function goToArea(area, title) {
        state.currentArea = area;
        elements.currentArea.textContent = title;
        
        switch (area) {
            case 'messages':
                showMessageAreas();
                break;
            case 'files':
                showFileAreas();
                break;
            case 'games':
                showGames();
                break;
            case 'stories':
                showStories();
                break;
        }
    }

    // =====================================================
    // Messages Area
    // =====================================================
    
    async function showMessageAreas() {
        clearScreen();
        print('|y=== MESSAGE AREAS ===|N');
        print('');
        
        try {
            const result = await api('/categories');
            const cats = result.categories || result.data || [];
            
            if (cats.length === 0) {
                print('|cNo message areas available.|N');
            } else {
                cats.forEach((cat, i) => {
                    print(`|Y${i + 1}|N. ${cat.name} |c(${cat.message_count || 0} msgs)|N`);
                });
            }
            
            print('');
            print('|Y[#]|N Select area  |Y[Q]|N Quit to main');
            
        } catch (error) {
            print(`|rError loading categories: ${error.message}|N`);
        }
        
        setPrompt('Messages');
    }

    async function handleMessages(cmd, original) {
        if (cmd === 'q' || cmd === 'quit') {
            goToMainMenu();
        } else if (/^\d+$/.test(cmd)) {
            await showCategory(parseInt(cmd));
        } else if (cmd === 'n' || cmd === 'new') {
            await postNewThread();
        } else if (cmd === '') {
            showMessageAreas();
        } else {
            print('|rInvalid option.|N');
        }
    }

    // State for message reading
    let currentCategoryId = null;
    let currentThreadId = null;
    let messageReadingMode = false;

    async function postNewThread() {
        if (!state.user || state.user.is_guest) {
            print('|rYou must be logged in to post messages.|N');
            return;
        }
        if (!currentCategoryId) {
            print('|rPlease select a category first.|N');
            return;
        }

        print('');
        const title = await promptUser('|cThread Title|N');
        if (!title || title.trim() === '') {
            print('|rCancelled.|N');
            return;
        }

        print('|cEnter your message (empty line to finish):|N');
        let content = '';
        let line = '';
        while ((line = await promptUser('')) !== '') {
            content += line + '\n';
        }

        if (!content.trim()) {
            print('|rMessage cannot be empty. Cancelled.|N');
            return;
        }

        try {
            setStatus('Posting...');
            await api(`/categories/${currentCategoryId}/threads`, 'POST', {
                title: title.trim(),
                content: content.trim()
            });
            print('|G✓ Thread posted successfully!|N');
            await showCategory(currentCategoryId);
        } catch (error) {
            print(`|rError posting thread: ${error.message}|N`);
        } finally {
            setStatus('');
        }
    }

    async function readThread(threadId) {
        try {
            // Get thread info and messages in one API call
            const result = await api(`/threads/${threadId}/messages`);
            const thread = result.thread || {};
            const messages = result.messages || result.data || [];

            clearScreen();
            print(`|Y═══ ${thread.subject || 'Thread'} ═══|N`);
            if (thread.view_count !== undefined) {
                print(`|c${thread.view_count} views, ${thread.reply_count || messages.length} replies|N`);
            }
            print('|B────────────────────────────────────────────────────────────────|N');
            print('');

            // Show messages
            if (messages.length === 0) {
                print('|cNo messages in this thread.|N');
            } else {
                messages.forEach((msg, i) => {
                    const author = msg.author?.handle || msg.user?.handle || 'Unknown';
                    const content = msg.body || msg.content || '';
                    
                    if (i === 0) {
                        // First message is the original post
                        print(`|G[ORIGINAL POST]|N`);
                        print(`|cFrom:|N |W${author}|N |c@|N ${msg.created_at}`);
                        print('');
                        print(content);
                        print('');
                        print('|B═══════════════════════════════════════════════════════════════════|N');
                        if (messages.length > 1) {
                            print('|c                         REPLIES                                  |N');
                            print('|B═══════════════════════════════════════════════════════════════════|N');
                        }
                    } else {
                        // Replies
                        print(`|Y#${i}|N |cFrom:|N ${author} |c@|N ${msg.created_at}`);
                        print(content);
                        print('|B────────────────────────────────────────────────────────────────|N');
                    }
                });
            }

            print('');
            print('|Y[R]|N Reply  |Y[Q]|N Back');
            currentThreadId = threadId;
            messageReadingMode = true;

        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function replyToThread() {
        if (!state.user || state.user.is_guest) {
            print('|rYou must be logged in to reply.|N');
            return;
        }
        if (!currentThreadId) {
            print('|rNo thread selected.|N');
            return;
        }

        print('');
        print('|cEnter your reply (empty line to finish):|N');
        let content = '';
        let line = '';
        while ((line = await promptUser('')) !== '') {
            content += line + '\n';
        }

        if (!content.trim()) {
            print('|rReply cannot be empty. Cancelled.|N');
            return;
        }

        try {
            setStatus('Posting reply...');
            await api(`/threads/${currentThreadId}/reply`, 'POST', {
                body: content.trim()
            });
            print('|G✓ Reply posted!|N');
            await readThread(currentThreadId);
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        } finally {
            setStatus('');
        }
    }

    async function showCategory(num) {
        try {
            currentCategoryId = num;
            state.categoryThreads = [];
            const result = await api(`/categories/${num}/threads`);
            const threadList = result.threads || result.data || [];
            
            clearScreen();
            print(`|y=== ${result.category?.name || 'Messages'} ===|N`);
            print('');
            
            if (threadList.length === 0) {
                print('|cNo threads in this area.|N');
            } else {
                state.categoryThreads = threadList;
                threadList.forEach((thread, i) => {
                    const status = thread.is_locked ? '|r[LOCKED]|N' : '';
                    const sticky = thread.is_sticky ? '|y[STICKY]|N' : '';
                    const title = thread.subject || thread.title || 'Untitled';
                    const count = thread.reply_count || thread.message_count || 0;
                    print(`|Y${i + 1}|N. ${sticky}${status} ${title} |c(${count} msgs)|N`);
                });
            }
            
            print('');
            print('|Y[#]|N Read thread  |Y[N]|G New thread  |Y[Q]|N Back');
            setPrompt('Thread');
            state.currentArea = 'thread-list';
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function handleThreadList(cmd, original) {
        if (cmd === 'q' || cmd === 'quit') {
            state.currentArea = 'messages';
            showMessageAreas();
        } else if (cmd === 'n' || cmd === 'new') {
            await postNewThread();
        } else if (/^\d+$/.test(cmd)) {
            const idx = parseInt(cmd) - 1;
            if (state.categoryThreads && state.categoryThreads[idx]) {
                await readThread(state.categoryThreads[idx].id);
                state.currentArea = 'thread-read';
                setPrompt('Thread');
            } else {
                print('|rInvalid thread number.|N');
            }
        } else if (cmd === '') {
            await showCategory(currentCategoryId);
        } else {
            print('|rInvalid option. [#] to read, [N] new, [Q] quit|N');
        }
    }

    async function handleThreadRead(cmd, original) {
        if (cmd === 'q' || cmd === 'quit') {
            state.currentArea = 'thread-list';
            await showCategory(currentCategoryId);
        } else if (cmd === 'r' || cmd === 'reply') {
            await replyToThread();
        } else {
            print('|rPress [R] to reply or [Q] to go back.|N');
        }
    }

    // =====================================================
    // Files Area
    // =====================================================
    
    async function showFileAreas() {
        clearScreen();
        print('|y=== FILE AREAS ===|N');
        print('');
        
        try {
            const result = await api('/files/categories');
            const categories = result.categories || result.data || [];
            
            if (categories.length === 0) {
                print('|cNo file areas available.|N');
            } else {
                categories.forEach((cat, i) => {
                    print(`|Y${i + 1}|N. ${cat.name} |c(${cat.file_count} files)|N`);
                });
            }
            
            print('');
            print('|Y[#]|N Select area  |Y[U]|N Upload  |Y[Q]|N Quit');
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
        
        setPrompt('Files');
    }

    // State for file browsing
    let currentFileCategory = null;
    let currentFileList = [];

    async function handleFiles(cmd, original) {
        if (cmd === 'q' || cmd === 'quit') {
            goToMainMenu();
        } else if (cmd === 'u') {
            await uploadFile();
        } else if (cmd === 's') {
            await searchFiles();
        } else if (/^\d+$/.test(cmd)) {
            await showFilesInCategory(parseInt(cmd));
        } else if (cmd === '') {
            showFileAreas();
        } else {
            print('|rInvalid option.|N');
        }
    }

    async function showFilesInCategory(catNum) {
        try {
            currentFileCategory = catNum;
            const result = await api(`/files/categories/${catNum}`);
            const fileList = result.files || result.data || [];
            
            clearScreen();
            print(`|y=== ${result.category?.name || 'Files'} ===|N`);
            print('');
            
            if (fileList.length === 0) {
                print('|cNo files in this area.|N');
            } else {
                currentFileList = fileList;
                print('|c #   Filename                    Size       Downloads  Date|N');
                print('|B ──  ────────────────────────────  ─────────  ─────────  ──────────|N');
                fileList.forEach((file, i) => {
                    const num = String(i + 1).padStart(2);
                    const name = (file.original_filename || file.filename || 'unknown').substring(0, 28).padEnd(28);
                    const size = formatFileSize(file.size).padStart(9);
                    const dls = String(file.download_count || 0).padStart(9);
                    print(`|Y${num}|N  ${name}  ${size}  ${dls}  ${file.uploaded_at || ''}`);
                });
            }
            
            print('');
            print('|Y[#]|N Download  |Y[I #]|N Info  |Y[Q]|N Back');
            setPrompt('Files');
            state.currentArea = 'file-list';
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function handleFileList(cmd, original) {
        if (cmd === 'q' || cmd === 'quit') {
            state.currentArea = 'files';
            showFileAreas();
        } else if (/^\d+$/.test(cmd)) {
            await downloadFile(parseInt(cmd));
        } else if (cmd.startsWith('i ')) {
            const num = parseInt(cmd.substring(2));
            await showFileInfo(num);
        } else {
            print('|rInvalid option.|N');
        }
    }

    async function downloadFile(num) {
        const idx = num - 1;
        if (!currentFileList[idx]) {
            print('|rInvalid file number.|N');
            return;
        }

        const file = currentFileList[idx];
        try {
            const result = await api(`/files/${file.id}/download`);
            
            if (result.download_url) {
                // Show ZMODEM-style transfer protocol display
                await showTransferProgress(
                    file.original_filename || file.filename,
                    file.size || file.file_size || 0,
                    result.download_url
                );
            } else {
                print('|rDownload not available.|N');
            }
        } catch (error) {
            print(`|rDownload error: ${error.message}|N`);
        }
    }

    async function showFileInfo(num) {
        const idx = num - 1;
        if (!currentFileList[idx]) {
            print('|rInvalid file number.|N');
            return;
        }

        const file = currentFileList[idx];
        print('');
        print('|B═══════════════════════════════════════════════════════════════|N');
        print(`|YFilename:|N ${file.original_filename}`);
        print(`|YSize:|N ${formatFileSize(file.size)}`);
        print(`|YUploaded by:|N ${file.uploader?.handle || 'Unknown'}`);
        print(`|YUploaded:|N ${file.uploaded_at || 'Unknown'}`);
        print(`|YDownloads:|N ${file.download_count}`);
        print(`|YDescription:|N ${file.description || 'No description'}`);
        print('|B═══════════════════════════════════════════════════════════════|N');
    }

    async function uploadFile() {
        if (!state.user || state.user.is_guest) {
            print('|rYou must be logged in to upload files.|N');
            return;
        }

        print('');
        print('|Y╔══════════════════════════════════════════════════════════════╗|N');
        print('|Y║|N                    FILE UPLOAD                              |Y║|N');
        print('|Y╠══════════════════════════════════════════════════════════════╣|N');
        print('|Y║|N  To upload a file, please use the web interface at:        |Y║|N');
        print('|Y║|N  |chttps://punktet.no/upload|N                                 |Y║|N');
        print('|Y║|N                                                            |Y║|N');
        print('|Y║|N  Terminal upload requires ZMODEM protocol which is not     |Y║|N');
        print('|Y║|N  supported in web browsers for security reasons.           |Y║|N');
        print('|Y╚══════════════════════════════════════════════════════════════╝|N');
        print('');
    }

    async function searchFiles() {
        const query = await promptUser('|cSearch for|N');
        if (!query || query.trim() === '') {
            print('|rSearch cancelled.|N');
            return;
        }

        try {
            setStatus('Searching...');
            const result = await api(`/files/search?query=${encodeURIComponent(query)}`);
            const fileList = result.results || result.data || [];
            
            print('');
            print(`|y=== Search Results for "${query}" ===|N`);
            print('');
            
            if (fileList.length === 0) {
                print('|cNo files found.|N');
            } else {
                currentFileList = fileList;
                fileList.forEach((file, i) => {
                    const filename = file.original_filename || file.filename || 'unknown';
                    print(`|Y${i + 1}|N. ${filename} |c(${formatFileSize(file.size || 0)})|N`);
                });
                print('');
                print('|Y[#]|N Download file');
            }
        } catch (error) {
            print(`|rSearch error: ${error.message}|N`);
        } finally {
            setStatus('');
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    // =====================================================
    // Games Area
    // =====================================================
    
    async function showGames() {
        clearScreen();
        print('|y=== DOOR GAMES ===|N');
        print('');
        
        try {
            const games = await api('/games');
            currentGameList = games.data || [];
            
            if (currentGameList.length === 0) {
                print('|cNo games available at this time.|N');
            } else {
                currentGameList.forEach((game, i) => {
                    const typeIcon = game.type === 'daily' ? '|y[DAILY]|N ' : '';
                    const status = game.available ? '' : '|r[LOCKED]|N ';
                    print(`|Y${i + 1}|N. ${typeIcon}${status}${game.title}`);
                    print(`   |K${game.description || 'No description'}|N`);
                });
            }
            
            print('');
            print('|Y[#]|N Play game  |Y[H]|N High Scores  |Y[Q]|N Quit');
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
        
        setPrompt('Games');
    }

    // State for games
    let currentGameList = [];
    let currentGame = null;

    async function handleGames(cmd, original) {
        if (cmd === 'q' || cmd === 'quit') {
            goToMainMenu();
        } else if (cmd === 'h') {
            await showHighScores();
        } else if (/^\d+$/.test(cmd)) {
            await startGame(parseInt(cmd));
        } else if (cmd === '') {
            showGames();
        } else {
            print('|rInvalid option.|N');
        }
    }

    async function startGame(num) {
        const idx = num - 1;
        if (!currentGameList[idx]) {
            print('|rInvalid game number.|N');
            return;
        }

        const game = currentGameList[idx];
        currentGame = game;

        try {
            setStatus('Loading game...');
            const result = await api(`/games/${game.slug}/start`, 'POST');
            
            clearScreen();
            print('|B╔════════════════════════════════════════════════════════════════════╗|N');
            print(`|B║|Y  ${game.title.padEnd(64)}|B║|N`);
            print('|B╠════════════════════════════════════════════════════════════════════╣|N');
            print(`|B║|N  ${(game.description || '').substring(0, 64).padEnd(64)}|B║|N`);
            print('|B╚════════════════════════════════════════════════════════════════════╝|N');
            print('');

            if (result.game_state) {
                displayGameState(result.game_state);
            }

            print('');
            print('|cEnter your move or action. Type |YQUIT|c to exit game.|N');
            state.currentArea = 'game-playing';
            setPrompt(game.title);

        } catch (error) {
            print(`|rError starting game: ${error.message}|N`);
        } finally {
            setStatus('');
        }
    }

    function displayGameState(gameState) {
        if (gameState.board) {
            // For board games
            print(gameState.board);
        }
        if (gameState.message) {
            print(`|c${gameState.message}|N`);
        }
        if (gameState.score !== undefined) {
            print(`|YScore:|N ${gameState.score}`);
        }
        if (gameState.options) {
            print('');
            print('|cAvailable actions:|N');
            gameState.options.forEach((opt, i) => {
                print(`  |Y${i + 1}|N. ${opt}`);
            });
        }
    }

    async function handleGamePlaying(cmd, original) {
        if (cmd === 'quit' || cmd === 'q') {
            print('|cExiting game...|N');
            currentGame = null;
            state.currentArea = 'games';
            await showGames();
            return;
        }

        if (!currentGame) {
            state.currentArea = 'games';
            await showGames();
            return;
        }

        try {
            setStatus('Processing move...');
            const result = await api(`/games/${currentGame.slug}/action`, 'POST', {
                action: cmd
            });

            if (result.game_state) {
                clearScreen();
                print(`|Y=== ${currentGame.title} ===|N`);
                print('');
                displayGameState(result.game_state);

                if (result.game_over) {
                    print('');
                    print('|Y╔════════════════════════════════════════╗|N');
                    print('|Y║         G A M E   O V E R             ║|N');
                    print('|Y╚════════════════════════════════════════╝|N');
                    print(`|cFinal Score: |W${result.final_score || 0}|N`);
                    
                    if (result.high_score) {
                        print('|G★ NEW HIGH SCORE! ★|N');
                    }
                    
                    print('');
                    print('Press any key to continue...');
                    currentGame = null;
                    state.currentArea = 'games';
                }
            }

        } catch (error) {
            print(`|rGame error: ${error.message}|N`);
        } finally {
            setStatus('');
        }
    }

    async function showHighScores() {
        try {
            const scores = await api('/games/highscores');
            
            print('');
            print('|y=== GLOBAL HIGH SCORES ===|N');
            print('');
            print('|cRank  Player              Score     Game|N');
            print('|c────  ──────────────────  ────────  ────────────|N');
            
            scores.data.slice(0, 10).forEach((score, i) => {
                const rank = String(i + 1).padStart(2);
                const player = (score.user?.handle || 'Unknown').padEnd(18);
                const pts = String(score.score).padStart(8);
                print(`|Y${rank}|N    ${player}  ${pts}  ${score.game?.title || ''}`);
            });
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    // =====================================================
    // Stories Area
    // =====================================================
    
    let currentStoryList = [];
    let currentStory = null;

    async function showStories() {
        clearScreen();
        print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|Y AI-GENERATED STORIES |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        
        try {
            const result = await api('/stories/today');
            const story = result.story || result.data || null;
            
            if (story) {
                currentStory = story;
                print('|c┌─── Today\'s Story ───────────────────────────────────────────────┐|N');
                print('|c│|N');
                print(`|c│|Y  ${story.title}|N`);
                print(`|c│|K  Category: ${story.category?.name || story.category || 'General'}  |N`);
                print(`|c│|K  Rating: ${story.average_rating || 'Not rated'}/5  Views: ${story.view_count || 0}|N`);
                print('|c│|N');
                print('|c└──────────────────────────────────────────────────────────────────┘|N');
                print('');
                
                // Show full story
                const lines = story.content.split('\n');
                lines.forEach(line => print(`  ${line}`));
            } else {
                print('|cNo story available today.|N');
            }
            
            print('');
            print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
            print('');
            print('|Y[A]|N Archive  |Y[T]|N Top Rated  |Y[V]|G Vote  |Y[C]|N Comments  |Y[Q]|N Quit');
            
        } catch (error) {
            // 404 means no story today - that's OK
            if (error.message.includes('404') || error.message.includes('no story')) {
                print('|cNo story available today.|N');
            } else {
                print(`|rError: ${error.message}|N`);
            }
        }
        
        setPrompt('Stories');
    }

    async function handleStories(cmd, original) {
        if (cmd === 'q' || cmd === 'quit') {
            goToMainMenu();
        } else if (cmd === 'a') {
            await showStoryArchive();
        } else if (cmd === 't') {
            await showTopRatedStories();
        } else if (cmd === 'v') {
            await voteOnStory();
        } else if (cmd === 'c') {
            await showStoryComments();
        } else if (cmd === '') {
            showStories();
        } else {
            print('|rInvalid option.|N');
        }
    }

    async function showStoryArchive() {
        try {
            const result = await api('/stories');
            currentStoryList = result.stories || result.data || [];

            clearScreen();
            print('|y=== STORY ARCHIVE ===|N');
            print('');

            if (currentStoryList.length === 0) {
                print('|cNo stories in archive.|N');
            } else {
                print('|c #   Title                                      Rating  Category|N');
                print('|B ──  ─────────────────────────────────────────  ──────  ──────────────|N');
                currentStoryList.forEach((story, i) => {
                    const num = String(i + 1).padStart(2);
                    const title = story.title.substring(0, 43).padEnd(43);
                    const rating = story.average_rating ? String(story.average_rating.toFixed(1)).padStart(4) + '/5' : '  N/A';
                    const categoryName = story.category?.name || story.category || 'General';
                    print(`|Y${num}|N  ${title}  ${rating}  ${categoryName}`);
                });
            }

            print('');
            print('|Y[#]|N Read story  |Y[Q]|N Back');
            state.currentArea = 'story-archive';
            setPrompt('Archive');

        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function handleStoryArchive(cmd, original) {
        if (cmd === 'q' || cmd === 'quit') {
            state.currentArea = 'stories';
            showStories();
        } else if (/^\d+$/.test(cmd)) {
            const idx = parseInt(cmd) - 1;
            if (currentStoryList[idx]) {
                await readStory(currentStoryList[idx].id);
            } else {
                print('|rInvalid story number.|N');
            }
        } else {
            print('|rInvalid option.|N');
        }
    }

    async function readStory(storyId) {
        try {
            const result = await api(`/stories/${storyId}`);
            const story = result.data;
            currentStory = story;

            clearScreen();
            print('|B═══════════════════════════════════════════════════════════════════════|N');
            print(`|Y${story.title}|N`);
            print(`|KCategory: ${story.category || 'General'}  |  Rating: ${story.average_rating || 'N/A'}/5|N`);
            print('|B═══════════════════════════════════════════════════════════════════════|N');
            print('');

            const lines = story.content.split('\n');
            lines.forEach(line => print(line));

            print('');
            print('|B───────────────────────────────────────────────────────────────────────|N');
            print('|Y[V]|N Vote  |Y[C]|N Comments  |Y[Q]|N Back');

        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function showTopRatedStories() {
        try {
            const result = await api('/stories/top');
            currentStoryList = result.data || [];

            clearScreen();
            print('|y=== TOP RATED STORIES ===|N');
            print('');

            if (currentStoryList.length === 0) {
                print('|cNo rated stories yet.|N');
            } else {
                print('|c Rank  Title                                    Rating   Votes|N');
                print('|B ────  ───────────────────────────────────────  ───────  ─────|N');
                currentStoryList.forEach((story, i) => {
                    const rank = String(i + 1).padStart(2);
                    const title = story.title.substring(0, 41).padEnd(41);
                    const rating = story.average_rating ? story.average_rating.toFixed(1) + '/5' : 'N/A  ';
                    const votes = String(story.rating_count || 0).padStart(5);
                    print(`|Y ${rank}|N   ${title}  |G${rating}|N  ${votes}`);
                });
            }

            print('');
            print('|Y[#]|N Read story  |Y[Q]|N Back');
            state.currentArea = 'story-archive';
            setPrompt('Top Stories');

        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function voteOnStory() {
        if (!currentStory) {
            print('|rNo story selected.|N');
            return;
        }
        if (!state.user || state.user.is_guest) {
            print('|rYou must be logged in to vote.|N');
            return;
        }

        print('');
        print('|cVote on this story:|N');
        print('|Y[U]|N Upvote (like)  |Y[D]|N Downvote (dislike)  |Y[Q]|N Cancel');
        
        const vote = await promptUser('|YYour vote|N');
        
        if (vote.toLowerCase() === 'u') {
            try {
                await api(`/stories/${currentStory.id}/upvote`, 'POST');
                print('|G✓ Thanks for your upvote!|N');
            } catch (error) {
                print(`|rError: ${error.message}|N`);
            }
        } else if (vote.toLowerCase() === 'd') {
            try {
                await api(`/stories/${currentStory.id}/downvote`, 'POST');
                print('|G✓ Downvote recorded.|N');
            } catch (error) {
                print(`|rError: ${error.message}|N`);
            }
        } else {
            print('|cVote cancelled.|N');
        }
    }

    async function showStoryComments() {
        if (!currentStory) {
            print('|rNo story selected.|N');
            return;
        }

        try {
            const result = await api(`/stories/${currentStory.id}/comments`);
            const commentList = result.comments || result.data || [];
            
            print('');
            print(`|y=== Comments on "${currentStory.title}" ===|N`);
            print('');

            if (commentList.length === 0) {
                print('|cNo comments yet. Be the first!|N');
            } else {
                commentList.forEach(comment => {
                    print(`|Y${comment.user?.handle || 'Anonymous'}|N |K(${comment.created_at})|N`);
                    print(`  ${comment.body || comment.content}`);
                    print('');
                });
            }

            print('');
            print('|Y[P]|N Post comment  |Y[Q]|N Back');

            const cmd = await promptUser('|cChoice|N');
            if (cmd.toLowerCase() === 'p') {
                await postStoryComment();
            }

        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function postStoryComment() {
        if (!state.user || state.user.is_guest) {
            print('|rYou must be logged in to comment.|N');
            return;
        }

        const comment = await promptUser('|cYour comment|N');
        if (!comment || comment.trim() === '') {
            print('|rCancelled.|N');
            return;
        }

        try {
            await api(`/stories/${currentStory.id}/comments`, 'POST', {
                content: comment.trim()
            });
            print('|G✓ Comment posted!|N');
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    // =====================================================
    // Utility Functions
    // =====================================================
    
    async function showWhosOnline() {
        try {
            const result = await api('/whos-online');
            
            clearScreen();
            print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W Who\'s Online |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
            print('');
            print(`|c Online: |W${result.online_count}|c / |W${result.total_nodes}|c nodes|N`);
            print('');
            print('|c Node  Status    User              Activity              Time|N');
            print('|B ────  ────────  ────────────────  ────────────────────  ────────|N');
            
            if (result.data && result.data.length > 0) {
                result.data.forEach(node => {
                    const nodeNum = String(node.node_number || '?').padStart(2);
                    if (node.status === 'active') {
                        const name = (node.handle || 'Unknown').padEnd(16);
                        const activity = (node.activity || 'Browsing').substring(0, 20).padEnd(20);
                        const time = node.time_online || '0:00';
                        print(`|Y ${nodeNum}|N   |G ONLINE |N  ${name}  |c${activity}|N  ${time}`);
                    } else {
                        print(`|Y ${nodeNum}|N   |K WAITING|N  |K- - - - - - - - -   Waiting for caller...|N`);
                    }
                });
            } else {
                print('|K No nodes configured.|N');
            }
            
            print('');
            print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }
    
    async function showLastCallers() {
        try {
            const callers = await api('/last-callers/15');
            
            clearScreen();
            print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W Last 15 Callers |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
            print('');
            print('|c #   User              Location          Date/Time|N');
            print('|B ──  ────────────────  ────────────────  ──────────────────|N');
            
            if (callers.data && callers.data.length > 0) {
                callers.data.forEach((caller, i) => {
                    const num = String(i + 1).padStart(2);
                    const name = (caller.handle || 'Unknown').padEnd(16);
                    const loc = (caller.location || 'Unknown').padEnd(16);
                    print(`|Y ${num}|N  ${name}  |c${loc}|N  ${caller.last_login || ''}`);
                });
            } else {
                print('|K No recent callers.|N');
            }
            
            print('');
            print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }
    
    async function showSystemInfo() {
        clearScreen();
        print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W System Information |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        print('|Y BBS Name      : |WPUNKTET BBS|N');
        print('|Y SysOp         : |WTerje|N');
        print('|Y Location      : |WNorway|N');
        print('|Y Established   : |W2025|N');
        print('|Y Software      : |WCustom Laravel + ANSI Terminal|N');
        print('|Y Version       : |W1.0.0|N');
        print('');
        
        // Fetch uptime from API (correct endpoint)
        try {
            const status = await api('/health/status');
            const uptimeStr = status.uptime || 'Unknown';
            print('|c System Uptime : |G' + uptimeStr + '|N');
            print('|c Server Time   : |W' + (status.server_time || new Date().toISOString()) + '|N');
            print('|c Status        : |G' + (status.status || 'healthy').toUpperCase() + '|N');
            if (status.checks?.php?.version) {
                print('|c PHP Version   : |W' + status.checks.php.version + '|N');
            }
            print('');
            
            // Fetch stats separately
            try {
                const statsResp = await api('/stats');
                print('|c Total Users   : |W' + (statsResp.users || '?') + '|N');
                print('|c Total Messages: |W' + (statsResp.messages || '?') + '|N');
                print('|c Total Files   : |W' + (statsResp.files || '?') + '|N');
                print('|c Online Now    : |G' + (statsResp.online || '?') + '|N');
            } catch (e2) {
                print('|c Total Users   : |W' + (state.stats?.users || '?') + '|N');
            }
        } catch (e) {
            print('|c System Uptime : |Y(Unable to fetch)|N');
            print('|c Total Users   : |W' + (state.stats?.users || '?') + '|N');
            print('|c Total Messages: |W' + (state.stats?.messages || '?') + '|N');
            print('|c Total Files   : |W' + (state.stats?.files || '?') + '|N');
        }
        
        print('');
        print('|K───────────────────────────────────────────────────────────────────────|N');
        print('');
        print('|Y[D]|N BBS Documentary Links   |Y[W]|N Warez Section   |Y[A]|N ANSI Logon Screens');
        print('|Y[E]|N Door Game Emulator     |Y[Q]|N Return to Main Menu');
        print('');
        print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
        
        const cmd = await promptUser('|cChoice|N');
        await handleSystemInfoMenu(cmd.toLowerCase());
    }
    
    function formatUptime(seconds) {
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        
        if (days > 0) {
            return `${days}d ${hours}h ${mins}m`;
        } else if (hours > 0) {
            return `${hours}h ${mins}m`;
        }
        return `${mins}m`;
    }
    
    async function handleSystemInfoMenu(cmd) {
        switch (cmd) {
            case 'd':
                await showDocumentaryLinks();
                break;
            case 'w':
                await showWarezSection();
                break;
            case 'a':
                await showAnsiLogonScreens();
                break;
            case 'e':
                await showDoorGameEmulator();
                break;
            case 'q':
            case '':
                goToMainMenu();
                break;
            default:
                print('|rInvalid option.|N');
                await showSystemInfo();
        }
    }
    
    async function showDocumentaryLinks() {
        clearScreen();
        print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W BBS Documentary Links |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        print('|Y  "BBS: The Documentary" by Jason Scott (2005)|N');
        print('|c  The definitive 8-part documentary about the BBS era.|N');
        print('|c  Watch free at: |Whttps://www.bbsdocumentary.com/|N');
        print('');
        print('|K───────────────────────────────────────────────────────────────────────|N');
        print('');
        print('|G  Part 1: |WBaud - The Early Days|N');
        print('|G  Part 2: |WSysops - Running a BBS|N');
        print('|G  Part 3: |WMake It Pay - Shareware & Commerce|N');
        print('|G  Part 4: |WFidoNet - The Network|N');
        print('|G  Part 5: |WArtscene - ANSI & ASCII Art|N');
        print('|G  Part 6: |WHPAC - Hacking, Phreaking, Anarchy, Cracking|N');
        print('|G  Part 7: |WNo Stranded Whales - Gaming & Social|N');
        print('|G  Part 8: |WJUST - The End of the BBS Era|N');
        print('');
        print('|K───────────────────────────────────────────────────────────────────────|N');
        print('');
        print('|Y  Other Resources:|N');
        print('|c  • textfiles.com     |W- Archive of BBS text files|N');
        print('|c  • 16colo.rs         |W- ANSI art archive|N');
        print('|c  • telnetbbsguide.com|W- Modern telnet BBS list|N');
        print('|c  • Break Into Chat   |W- BBS history podcast|N');
        print('');
        print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
        
        await promptUser('|cPress ENTER to return|N');
        await showSystemInfo();
    }
    
    async function showWarezSection() {
        clearScreen();
        print('');
        await sleep(500);
        print('|R▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('|R█                                                                      █|N');
        print('|R█|Y    ██╗    ██╗ █████╗ ██████╗ ███████╗███████╗                       |R█|N');
        print('|R█|Y    ██║    ██║██╔══██╗██╔══██╗██╔════╝╚══███╔╝                       |R█|N');
        print('|R█|Y    ██║ █╗ ██║███████║██████╔╝█████╗    ███╔╝                        |R█|N');
        print('|R█|Y    ██║███╗██║██╔══██║██╔══██╗██╔══╝   ███╔╝                         |R█|N');
        print('|R█|Y    ╚███╔███╔╝██║  ██║██║  ██║███████╗███████╗                       |R█|N');
        print('|R█|Y     ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝╚══════╝                       |R█|N');
        print('|R█                                                                      █|N');
        print('|R█|W              S E C T I O N   0 1   -   E L I T E                    |R█|N');
        print('|R█                                                                      █|N');
        print('|R▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
        print('');
        await sleep(800);
        
        playBell();
        print('');
        print('|R╔═══════════════════════════════════════════════════════════════════════╗|N');
        print('|R║|N                                                                       |R║|N');
        print('|R║|W            █████╗  ██████╗ ██████╗███████╗███████╗███████╗            |R║|N');
        print('|R║|W           ██╔══██╗██╔════╝██╔════╝██╔════╝██╔════╝██╔════╝            |R║|N');
        print('|R║|W           ███████║██║     ██║     █████╗  ███████╗███████╗            |R║|N');
        print('|R║|W           ██╔══██║██║     ██║     ██╔══╝  ╚════██║╚════██║            |R║|N');
        print('|R║|W           ██║  ██║╚██████╗╚██████╗███████╗███████║███████║            |R║|N');
        print('|R║|W           ╚═╝  ╚═╝ ╚═════╝ ╚═════╝╚══════╝╚══════╝╚══════╝            |R║|N');
        print('|R║|N                                                                       |R║|N');
        print('|R║|Y              ██████╗ ███████╗███╗   ██╗██╗███████╗██████╗             |R║|N');
        print('|R║|Y              ██╔══██╗██╔════╝████╗  ██║██║██╔════╝██╔══██╗            |R║|N');
        print('|R║|Y              ██║  ██║█████╗  ██╔██╗ ██║██║█████╗  ██║  ██║            |R║|N');
        print('|R║|Y              ██║  ██║██╔══╝  ██║╚██╗██║██║██╔══╝  ██║  ██║            |R║|N');
        print('|R║|Y              ██████╔╝███████╗██║ ╚████║██║███████╗██████╔╝            |R║|N');
        print('|R║|Y              ╚═════╝ ╚══════╝╚═╝  ╚═══╝╚═╝╚══════╝╚═════╝             |R║|N');
        print('|R║|N                                                                       |R║|N');
        print('|R╚═══════════════════════════════════════════════════════════════════════╝|N');
        print('');
        await sleep(500);
        
        print('|K  Your IP address has been logged.|N');
        print('|K  FBI, Interpol, and your mom have been notified.|N');
        print('');
        await sleep(1000);
        
        print('|G  Just kidding! This is a |YNOSTALGIC JOKE|G from the BBS era. 😄|N');
        print('|c  Back in the day, many BBSes had fake "warez" sections that|N');
        print('|c  would display scary ACCESS DENIED messages to would-be pirates.|N');
        print('');
        print('|K  Remember: Piracy is still illegal. Support software creators!|N');
        print('');
        
        await promptUser('|cPress ENTER to return (you\'re not in trouble)|N');
        await showSystemInfo();
    }
    
    async function showAnsiLogonScreens() {
        clearScreen();
        print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W Classic ANSI Logon Screens |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        
        const screens = [
            { id: 1, name: 'PUNKTET Welcome', style: 'Modern' },
            { id: 2, name: 'Retro 90s Style', style: 'Classic' },
            { id: 3, name: 'Cyberpunk', style: 'Futuristic' },
            { id: 4, name: 'Matrix Rain', style: 'Animated' },
            { id: 5, name: 'Demoscene', style: 'Artistic' }
        ];
        
        screens.forEach((s, i) => {
            print(`|Y${i + 1}|N. ${s.name.padEnd(25)} |c[${s.style}]|N`);
        });
        
        print('');
        print('|Y[#]|N Preview screen  |Y[S]|N Set as default  |Y[Q]|N Return');
        print('');
        
        const cmd = await promptUser('|cChoice|N');
        
        if (cmd === 'q' || cmd === '') {
            await showSystemInfo();
            return;
        }
        
        const num = parseInt(cmd);
        if (num >= 1 && num <= screens.length) {
            await showAnsiPreview(num);
        } else {
            print('|rInvalid selection.|N');
            await showAnsiLogonScreens();
        }
    }
    
    async function showAnsiPreview(screenNum) {
        clearScreen();
        
        switch (screenNum) {
            case 1: // PUNKTET Welcome
                await showPunktetLogon();
                break;
            case 2: // Retro 90s
                await showRetro90sLogon();
                break;
            case 3: // Cyberpunk
                await showCyberpunkLogon();
                break;
            case 4: // Matrix
                await showMatrixLogon();
                break;
            case 5: // Demoscene
                await showDemosceneLogon();
                break;
        }
        
        print('');
        await promptUser('|cPress ENTER to return|N');
        await showAnsiLogonScreens();
    }
    
    async function showPunktetLogon() {
        print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('|B█                                                                         █|N');
        print('|B█|G  ██████╗ ██╗   ██╗███╗   ██╗██╗  ██╗████████╗███████╗████████╗        |B█|N');
        print('|B█|G  ██╔══██╗██║   ██║████╗  ██║██║ ██╔╝╚══██╔══╝██╔════╝╚══██╔══╝        |B█|N');
        print('|B█|G  ██████╔╝██║   ██║██╔██╗ ██║█████╔╝    ██║   █████╗     ██║           |B█|N');
        print('|B█|G  ██╔═══╝ ██║   ██║██║╚██╗██║██╔═██╗    ██║   ██╔══╝     ██║           |B█|N');
        print('|B█|G  ██║     ╚██████╔╝██║ ╚████║██║  ██╗   ██║   ███████╗   ██║           |B█|N');
        print('|B█|G  ╚═╝      ╚═════╝ ╚═╝  ╚═══╝╚═╝  ╚═╝   ╚═╝   ╚══════╝   ╚═╝           |B█|N');
        print('|B█|N                                                                       |B█|N');
        print('|B█|Y             ══════ BULLETIN BOARD SYSTEM ══════                       |B█|N');
        print('|B█|c                 "Where Nostalgia Meets The Future"                    |B█|N');
        print('|B█                                                                         █|N');
        print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
    }
    
    async function showRetro90sLogon() {
        print('|R ▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
        print('|R █|Y ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░|R█|N');
        print('|R █|Y ░|W ▄▄▄   ▄   ▄ ▄   ▄ ▄  ▄ ▄▄▄▄▄ ▄▄▄▄ ▄▄▄▄▄                          |Y░|R█|N');
        print('|R █|Y ░|W █  █  █   █ █▀▄ █ █ █    █   █      █                             |Y░|R█|N');
        print('|R █|Y ░|W █▀▀▀  █   █ █ ▀▄█ ██     █   █▀▀    █                             |Y░|R█|N');
        print('|R █|Y ░|W █     ▀▄▄▄▀ █   █ █ █    █   █▄▄▄   █                             |Y░|R█|N');
        print('|R █|Y ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░|R█|N');
        print('|R █|C                    ★ BULLETIN BOARD SYSTEM ★                         |R█|N');
        print('|R █|G       Running: |WRemoteAccess 2.62|G  •  Nodes: |W6|G  •  Est: |W2025       |R█|N');
        print('|R █|M ────────────────────────────────────────────────────────────────────|R█|N');
        print('|R █|W                  Call us at: telnet://punktet.no                    |R█|N');
        print('|R ▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
    }
    
    async function showCyberpunkLogon() {
        print('|M╔══════════════════════════════════════════════════════════════════════════╗|N');
        print('|M║|K▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓|M║|N');
        print('|M║|K▓|C  ╔═╗ ╦ ╦ ╔╗╔ ╦╔═ ╔╦╗ ╔═╗ ╔╦╗                                        |K▓|M║|N');
        print('|M║|K▓|C  ╠═╝ ║ ║ ║║║ ╠╩╗  ║  ║╣   ║                                         |K▓|M║|N');
        print('|M║|K▓|C  ╩   ╚═╝ ╝╚╝ ╩ ╩  ╩  ╚═╝  ╩                                         |K▓|M║|N');
        print('|M║|K▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓|M║|N');
        print('|M║|N                                                                          |M║|N');
        print('|M║|R   ▀▀▀▀▀ |G[NEURAL INTERFACE DETECTED]|R ▀▀▀▀▀                              |M║|N');
        print('|M║|N                                                                          |M║|N');
        print('|M║|Y   > WELCOME TO THE GRID, NETRUNNER                                       |M║|N');
        print('|M║|Y   > JACKING IN...                                                        |M║|N');
        print('|M║|N                                                                          |M║|N');
        print('|M╚══════════════════════════════════════════════════════════════════════════╝|N');
    }
    
    async function showMatrixLogon() {
        // Simple matrix-style rain effect
        const chars = 'ｱｲｳｴｵｶｷｸｹｺｻｼｽｾｿﾀﾁﾂﾃﾄﾅﾆﾇﾈﾉﾊﾋﾌﾍﾎﾏﾐﾑﾒﾓﾔﾕﾖﾗﾘﾙﾚﾛﾜﾝ01';
        
        for (let i = 0; i < 5; i++) {
            let line = '';
            for (let j = 0; j < 70; j++) {
                const brightness = Math.random();
                const char = chars[Math.floor(Math.random() * chars.length)];
                if (brightness > 0.8) {
                    line += '|W' + char + '|N';
                } else if (brightness > 0.5) {
                    line += '|G' + char + '|N';
                } else {
                    line += '|K' + char + '|N';
                }
            }
            print(line);
        }
        
        print('');
        print('|G                    ╔═══════════════════════════════╗|N');
        print('|G                    ║|W   FOLLOW THE WHITE RABBIT    |G║|N');
        print('|G                    ║|K        KNOCK KNOCK NEO       |G║|N');
        print('|G                    ╚═══════════════════════════════╝|N');
        print('');
        
        for (let i = 0; i < 5; i++) {
            let line = '';
            for (let j = 0; j < 70; j++) {
                const char = chars[Math.floor(Math.random() * chars.length)];
                line += '|K' + char + '|N';
            }
            print(line);
        }
    }
    
    async function showDemosceneLogon() {
        print('|B                    ▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄                          |N');
        print('|B                   █|C░░░░░░░░░░░░░░░░░░░░░░░░░░|B█                          |N');
        print('|B                   █|C░|M▄▄▄  ▄  ▄▄▄  ▄▄▄  ▄▄▄ ▄▄▄|C░|B█                          |N');
        print('|B                   █|C░|M█▄▄  █  █ █  █▄▄  █   █▄▄|C░|B█                          |N');
        print('|B                   █|C░|M█    █  █ █  █ █  █   █  |C░|B█                          |N');
        print('|B                   █|C░|M▀▀▀  ▀  ▀▀▀  ▀ ▀  ▀▀▀ ▀▀▀|C░|B█                          |N');
        print('|B                   █|C░░░░░░░░░░░░░░░░░░░░░░░░░░|B█                          |N');
        print('|B                    ▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀                          |N');
        print('');
        print('|Y               ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━|N');
        print('|W                     D E M O S C E N E   S T Y L E|N');
        print('|c                    Greets to: Future Crew, Triton,|N');
        print('|c                    Orange, TRSI, Fairlight, Razor1911|N');
        print('|Y               ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━|N');
    }
    
    // =====================================================
    // Door Game Emulator - Classic Text Games Recreation
    // =====================================================
    
    async function showDoorGameEmulator() {
        clearScreen();
        print('|R▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W DOOR GAME EMULATOR |R▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        print('|c  Experience classic BBS door games without leaving PUNKTET!|N');
        print('|K  These are simplified recreations for nostalgic purposes.|N');
        print('');
        print('|K───────────────────────────────────────────────────────────────────────|N');
        print('');
        print('|Y 1|N. |GGUESS THE NUMBER|N     - Classic guessing game');
        print('|Y 2|N. |YRPS BATTLE|N          - Rock, Paper, Scissors');
        print('|Y 3|N. |MCOIN FLIP|N           - Test your luck');
        print('|Y 4|N. |CHIGH-LOW CARDS|N      - Card game classic');
        print('|Y 5|N. |WWORD SCRAMBLE|N       - Unscramble BBS terms');
        print('');
        print('|K───────────────────────────────────────────────────────────────────────|N');
        print('');
        print('|K  Note: These are quick mini-games. For the full door games|N');
        print('|K  experience, check out the Games section from the main menu!|N');
        print('');
        print('|Y[#]|N Play game  |Y[Q]|N Return');
        
        const cmd = await promptUser('|cChoice|N');
        
        switch (cmd) {
            case '1':
                await playGuessNumber();
                break;
            case '2':
                await playRPSBattle();
                break;
            case '3':
                await playCoinFlip();
                break;
            case '4':
                await playHighLowCards();
                break;
            case '5':
                await playWordScramble();
                break;
            case 'q':
            case '':
                await showSystemInfo();
                return;
            default:
                print('|rInvalid selection.|N');
                await showDoorGameEmulator();
        }
    }
    
    async function playGuessNumber() {
        clearScreen();
        print('|G▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W GUESS THE NUMBER |G▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        print('|c  I\'m thinking of a number between 1 and 100.|N');
        print('|c  You have 7 guesses to find it!|N');
        print('');
        
        const target = Math.floor(Math.random() * 100) + 1;
        let guesses = 7;
        let won = false;
        
        while (guesses > 0 && !won) {
            const guess = await promptUser(`|Y${guesses}|W guesses left. Your guess|N`);
            const num = parseInt(guess);
            
            if (isNaN(num) || num < 1 || num > 100) {
                print('|rPlease enter a number between 1 and 100.|N');
                continue;
            }
            
            guesses--;
            
            if (num === target) {
                won = true;
                print('');
                print('|G★★★ CORRECT! ★★★|N');
                print(`|WYou found it in ${7 - guesses} guesses!|N`);
            } else if (num < target) {
                print('|YHigher!|N');
            } else {
                print('|YLower!|N');
            }
        }
        
        if (!won) {
            print('');
            print(`|rGame Over! The number was |W${target}|r.|N`);
        }
        
        print('');
        await promptUser('|cPress ENTER to continue|N');
        await showDoorGameEmulator();
    }
    
    async function playRPSBattle() {
        clearScreen();
        print('|Y▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W ROCK PAPER SCISSORS |Y▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        print('|Y[R]|Nock  |Y[P]|Naper  |Y[S]|Ncissors  |Y[Q]|Nuit');
        print('');
        
        let playerScore = 0;
        let cpuScore = 0;
        const choices = ['rock', 'paper', 'scissors'];
        const emojis = { rock: '🪨', paper: '📄', scissors: '✂️' };
        
        while (playerScore < 3 && cpuScore < 3) {
            print(`|c  Score: You |G${playerScore}|c - |R${cpuScore}|c CPU  (First to 3)|N`);
            const input = await promptUser('|WYour choice|N');
            
            let player;
            if (input === 'r') player = 'rock';
            else if (input === 'p') player = 'paper';
            else if (input === 's') player = 'scissors';
            else if (input === 'q') { await showDoorGameEmulator(); return; }
            else { print('|rInvalid choice. R, P, or S.|N'); continue; }
            
            const cpu = choices[Math.floor(Math.random() * 3)];
            
            print(`|W  You: ${emojis[player]} ${player.toUpperCase()}|N`);
            print(`|K  CPU: ${emojis[cpu]} ${cpu.toUpperCase()}|N`);
            
            if (player === cpu) {
                print('|Y  TIE!|N');
            } else if (
                (player === 'rock' && cpu === 'scissors') ||
                (player === 'paper' && cpu === 'rock') ||
                (player === 'scissors' && cpu === 'paper')
            ) {
                print('|G  You WIN this round!|N');
                playerScore++;
            } else {
                print('|R  CPU wins this round!|N');
                cpuScore++;
            }
            print('');
        }
        
        if (playerScore === 3) {
            print('|G★★★ VICTORY! You won the match! ★★★|N');
        } else {
            print('|R✖✖✖ DEFEAT! CPU won the match! ✖✖✖|N');
        }
        
        print('');
        await promptUser('|cPress ENTER to continue|N');
        await showDoorGameEmulator();
    }
    
    async function playCoinFlip() {
        clearScreen();
        print('|M▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W COIN FLIP |M▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        print('|c  Guess correctly 5 times in a row to win!|N');
        print('');
        
        let streak = 0;
        
        while (streak < 5) {
            print(`|Y  Current streak: ${streak}/5|N`);
            const guess = await promptUser('|W[H]eads or [T]ails?|N');
            
            if (guess !== 'h' && guess !== 't') {
                print('|rEnter H or T.|N');
                continue;
            }
            
            print('');
            print('|c  Flipping coin...|N');
            await sleep(500);
            
            // Coin animation
            const frames = ['|Y🌑|N', '|W🌓|N', '|Y🌕|N', '|W🌗|N'];
            for (let i = 0; i < 8; i++) {
                print(`  ${frames[i % 4]}`, { newline: false });
                await sleep(100);
                print('\r    \r', { newline: false });
            }
            
            const result = Math.random() < 0.5 ? 'h' : 't';
            const resultText = result === 'h' ? 'HEADS' : 'TAILS';
            
            print(`|W  Result: ${resultText}!|N`);
            
            if (guess === result) {
                streak++;
                print('|G  Correct!|N');
            } else {
                print('|R  Wrong! Streak broken!|N');
                streak = 0;
            }
            print('');
        }
        
        print('|G★★★ AMAZING! 5 in a row! You\'re a coin master! ★★★|N');
        print('');
        await promptUser('|cPress ENTER to continue|N');
        await showDoorGameEmulator();
    }
    
    async function playHighLowCards() {
        clearScreen();
        print('|C▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W HIGH-LOW CARDS |C▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        print('|c  Guess if the next card will be HIGHER or LOWER.|N');
        print('|c  Get 5 correct to win!|N');
        print('');
        
        const cards = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
        const getValue = (card) => cards.indexOf(card);
        
        let current = cards[Math.floor(Math.random() * cards.length)];
        let score = 0;
        
        while (score < 5) {
            print(`|W  Current card: |Y[${current}]|W   Score: ${score}/5|N`);
            const guess = await promptUser('|W[H]igher or [L]ower?|N');
            
            if (guess !== 'h' && guess !== 'l') {
                print('|rEnter H or L.|N');
                continue;
            }
            
            const next = cards[Math.floor(Math.random() * cards.length)];
            print(`|c  Next card: |G[${next}]|N`);
            
            const currentVal = getValue(current);
            const nextVal = getValue(next);
            
            const isHigher = nextVal > currentVal;
            const isCorrect = (guess === 'h' && isHigher) || (guess === 'l' && !isHigher && nextVal !== currentVal);
            
            if (nextVal === currentVal) {
                print('|Y  Same card! Push - no change.|N');
            } else if (isCorrect) {
                score++;
                print('|G  Correct!|N');
            } else {
                print('|R  Wrong! Game Over!|N');
                print('');
                await promptUser('|cPress ENTER to continue|N');
                await showDoorGameEmulator();
                return;
            }
            
            current = next;
            print('');
        }
        
        print('|G★★★ You won! Card shark! ★★★|N');
        print('');
        await promptUser('|cPress ENTER to continue|N');
        await showDoorGameEmulator();
    }
    
    async function playWordScramble() {
        clearScreen();
        print('|W▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W WORD SCRAMBLE |W▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        print('|c  Unscramble the BBS-related word!|N');
        print('');
        
        const words = [
            { word: 'SYSOP', hint: 'System operator' },
            { word: 'MODEM', hint: 'Connection device' },
            { word: 'ANSI', hint: 'Terminal graphics' },
            { word: 'FIDONET', hint: 'BBS network' },
            { word: 'TELNET', hint: 'Remote protocol' },
            { word: 'ZMODEM', hint: 'File transfer' },
            { word: 'DOOR', hint: 'External game' },
            { word: 'PACKET', hint: 'Offline reader data' },
            { word: 'TAGLINE', hint: 'Message signature' },
            { word: 'XMODEM', hint: 'Old file transfer' }
        ];
        
        const selected = words[Math.floor(Math.random() * words.length)];
        const scrambled = selected.word.split('').sort(() => Math.random() - 0.5).join('');
        
        print(`|Y  Scrambled: ${scrambled}|N`);
        print(`|K  Hint: ${selected.hint}|N`);
        print('');
        
        let attempts = 3;
        while (attempts > 0) {
            const guess = await promptUser(`|W(${attempts} tries) Your answer|N`);
            
            if (guess.toUpperCase() === selected.word) {
                print('');
                print('|G★★★ CORRECT! ★★★|N');
                print('');
                await promptUser('|cPress ENTER to continue|N');
                await showDoorGameEmulator();
                return;
            }
            
            attempts--;
            if (attempts > 0) {
                print('|R  Wrong! Try again.|N');
            }
        }
        
        print('');
        print(`|R  Out of tries! The word was: |W${selected.word}|N`);
        print('');
        await promptUser('|cPress ENTER to continue|N');
        await showDoorGameEmulator();
    }

    async function showBulletins() {
        try {
            const bulletins = await api('/bulletin');
            currentBulletinList = bulletins.data || [];
            
            clearScreen();
            print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W System Bulletins |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
            print('');
            
            if (currentBulletinList.length > 0) {
                print('|c #   Date        Title|N');
                print('|B ──  ──────────  ───────────────────────────────────────────────────|N');
                currentBulletinList.forEach((b, i) => {
                    const num = String(i + 1).padStart(2);
                    const date = (b.date || b.created_at || 'Recent').substring(0, 10).padEnd(10);
                    print(`|Y${num}|N  ${date}  ${b.title}`);
                });
                print('');
                print('|Y[#]|N Read bulletin  |Y[Q]|N Quit');
                
                const cmd = await promptUser('|cChoice|N');
                if (/^\d+$/.test(cmd)) {
                    await readBulletin(parseInt(cmd));
                }
            } else {
                print('|K No bulletins at this time.|N');
            }
            
            print('');
            print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function readBulletin(num) {
        const idx = num - 1;
        if (!currentBulletinList[idx]) {
            print('|rInvalid bulletin number.|N');
            return;
        }

        const bulletin = currentBulletinList[idx];
        
        try {
            const result = await api(`/bulletin/${bulletin.id}`);
            const b = result.data;

            clearScreen();
            print('|B═══════════════════════════════════════════════════════════════════════|N');
            print(`|Y${b.title}|N`);
            print(`|KPosted: ${b.created_at || 'Unknown date'}|N`);
            print('|B═══════════════════════════════════════════════════════════════════════|N');
            print('');
            print(b.content || 'No content available.');
            print('');
            print('|B═══════════════════════════════════════════════════════════════════════|N');
            print('');
            print('Press any key to continue...');

        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }
    
    let currentAnsiList = [];

    async function showAnsiGallery() {
        try {
            const art = await api('/ansi');
            currentAnsiList = art.data || [];
            
            clearScreen();
            print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W ANSI Art Gallery |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
            print('');
            
            if (currentAnsiList.length > 0) {
                print('|c #   Title                              Artist           Size|N');
                print('|B ──  ─────────────────────────────────  ───────────────  ────────|N');
                currentAnsiList.forEach((a, i) => {
                    const num = String(i + 1).padStart(2);
                    const title = (a.title || 'Untitled').substring(0, 35).padEnd(35);
                    const artist = (a.artist || 'Unknown').substring(0, 15).padEnd(15);
                    print(`|Y${num}|N  ${title}  ${artist}  ${a.width || '80'}x${a.height || '25'}`);
                });
                print('');
                print('|Y[#]|N View art  |Y[R]|N Random  |Y[Q]|N Quit');
                
                const cmd = await promptUser('|cChoice|N');
                if (/^\d+$/.test(cmd)) {
                    await viewAnsiArt(parseInt(cmd));
                } else if (cmd.toLowerCase() === 'r') {
                    const randomIdx = Math.floor(Math.random() * currentAnsiList.length) + 1;
                    await viewAnsiArt(randomIdx);
                }
            } else {
                print('|K No ANSI art available yet.|N');
            }
            
            print('');
            print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function viewAnsiArt(num) {
        const idx = num - 1;
        if (!currentAnsiList[idx]) {
            print('|rInvalid art number.|N');
            return;
        }

        const art = currentAnsiList[idx];
        
        try {
            const result = await api(`/ansi/${art.id}`);
            const a = result.data;

            clearScreen();
            
            // Display the ANSI art content
            if (a.content) {
                // The content should already have ANSI codes that our parser handles
                const lines = a.content.split('\n');
                lines.forEach(line => print(line));
            } else {
                print('|K[No art content available]|N');
            }
            
            print('');
            print('|B───────────────────────────────────────────────────────────────────────|N');
            print(`|YTitle:|N ${a.title || 'Untitled'}  |YArtist:|N ${a.artist || 'Unknown'}`);
            print('|B───────────────────────────────────────────────────────────────────────|N');
            print('');
            print('Press any key to continue...');

        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }
    
    let currentPollList = [];

    async function showPolls() {
        try {
            const polls = await api('/polls');
            currentPollList = polls.data || [];
            
            clearScreen();
            print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W Active Polls |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
            print('');
            
            if (currentPollList.length > 0) {
                currentPollList.forEach((p, i) => {
                    const voted = p.user_voted ? '|G[VOTED]|N ' : '';
                    print(`|Y ${i+1}|N. ${voted}${p.question} |K(${p.total_votes} votes)|N`);
                });
                print('');
                print('|Y[#]|N Vote/View  |Y[Q]|N Quit');
            } else {
                print('|K No active polls at this time.|N');
            }
            
            print('');
            print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
            state.currentArea = 'polls';
            setPrompt('Polls');
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function handlePolls(cmd, original) {
        if (cmd === 'q' || cmd === 'quit') {
            state.currentArea = 'main';
            goToMainMenu();
        } else if (/^\d+$/.test(cmd)) {
            await viewAndVotePoll(parseInt(cmd));
        } else {
            print('|rInvalid option.|N');
        }
    }

    async function viewAndVotePoll(num) {
        const idx = num - 1;
        if (!currentPollList[idx]) {
            print('|rInvalid poll number.|N');
            return;
        }

        const poll = currentPollList[idx];
        
        try {
            const result = await api(`/polls/${poll.id}`);
            const pollData = result.data;

            clearScreen();
            print('|B╔════════════════════════════════════════════════════════════════════╗|N');
            print(`|B║|Y  ${pollData.question.padEnd(66)}|B║|N`);
            print('|B╠════════════════════════════════════════════════════════════════════╣|N');
            
            pollData.options.forEach((opt, i) => {
                const percentage = pollData.total_votes > 0 
                    ? Math.round((opt.votes / pollData.total_votes) * 100) 
                    : 0;
                const bar = '█'.repeat(Math.floor(percentage / 5)) + '░'.repeat(20 - Math.floor(percentage / 5));
                const selected = opt.id === pollData.user_vote ? '|G►|N' : ' ';
                print(`|B║|N ${selected}|Y${i + 1}|N. ${opt.text.padEnd(35)} ${bar} ${String(percentage).padStart(3)}% |B║|N`);
            });
            
            print('|B╠════════════════════════════════════════════════════════════════════╣|N');
            print(`|B║|K  Total votes: ${pollData.total_votes}                                               |B║|N`);
            print('|B╚════════════════════════════════════════════════════════════════════╝|N');

            if (!pollData.user_voted && state.user && !state.user.is_guest) {
                print('');
                print('|cEnter option number to vote, or [Q] to go back:|N');
                const vote = await promptUser('|YYour vote|N');
                
                if (vote && vote.toLowerCase() !== 'q') {
                    const optNum = parseInt(vote) - 1;
                    if (pollData.options[optNum]) {
                        try {
                            await api(`/polls/${poll.id}/vote`, 'POST', {
                                option_id: pollData.options[optNum].id
                            });
                            print('|G✓ Vote recorded!|N');
                            await viewAndVotePoll(num); // Refresh to show updated results
                        } catch (error) {
                            print(`|rVote error: ${error.message}|N`);
                        }
                    } else {
                        print('|rInvalid option.|N');
                    }
                }
            } else if (pollData.user_voted) {
                print('');
                print('|K You have already voted on this poll.|N');
            }

        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }
    
    async function showConferences() {
        try {
            const conferences = await api('/conferences');
            
            clearScreen();
            print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W Conferences |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
            print('');
            
            if (conferences.data && conferences.data.length > 0) {
                conferences.data.forEach((c, i) => {
                    const current = c.id === state.currentConferenceId ? '|G►|N' : ' ';
                    const locked = c.is_private ? '|r[PRIVATE]|N ' : '';
                    print(`${current}|Y${i + 1}|N. ${locked}${c.name}`);
                    if (c.description) {
                        print(`    |K${c.description}|N`);
                    }
                });
            } else {
                // Fallback to default conferences
                print('|Y 1|N. Main Conference');
                print('|Y 2|N. Programming');
                print('|Y 3|N. Retro Computing');
                print('|Y 4|N. Off Topic');
            }
            
            print('');
            print('|K Current: |W' + (state.currentConference || 'Main') + '|N');
            print('');
            print('|Y[#]|N Join conference  |Y[Q]|N Quit');
            
            const cmd = await promptUser('|cChoice|N');
            if (/^\d+$/.test(cmd)) {
                await joinConference(parseInt(cmd));
            }
            
        } catch (error) {
            // Fallback if API doesn't exist
            clearScreen();
            print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W Conferences |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
            print('');
            print('|Y 1|N. Main Conference |K(Default)|N');
            print('|Y 2|N. Programming');
            print('|Y 3|N. Retro Computing');
            print('|Y 4|N. Off Topic');
            print('');
            print('|K Current: |W' + (state.currentConference || 'Main') + '|N');
            print('');
            print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
        }
    }

    async function joinConference(num) {
        const conferenceNames = ['Main', 'Programming', 'Retro Computing', 'Off Topic'];
        const name = conferenceNames[num - 1] || `Conference ${num}`;
        
        try {
            await api(`/conferences/${num}/join`, 'POST');
            state.currentConference = name;
            state.currentConferenceId = num;
            print(`|G✓ Joined ${name} conference.|N`);
        } catch (error) {
            // Even if API fails, allow local switch
            state.currentConference = name;
            print(`|G✓ Switched to ${name} conference.|N`);
        }
    }
    
    async function chatWithSysop() {
        if (!state.user || state.user.is_guest) {
            print('|rYou must be logged in to page the SysOp.|N');
            return;
        }

        clearScreen();
        print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W Page SysOp |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        
        try {
            setStatus('Paging SysOp...');
            await api('/sysop/page', 'POST', {
                user_id: state.user.id,
                node: state.node
            });
            
            print('|Y ★ Paging SysOp... ★|N');
            print('');
            print('|c The SysOp has been notified of your request.|N');
            print('|c If available, they will respond shortly.|N');
            print('');
            print('|K While waiting, you can:|N');
            print('|K - Leave a message describing your question|N');
            print('|K - Continue browsing the BBS|N');
            print('');
            
            const msg = await promptUser('|cLeave a message for the SysOp (or press Enter to skip)|N');
            if (msg && msg.trim()) {
                await api('/pm/send', 'POST', {
                    recipient: 'sysop',
                    subject: 'SysOp Page Request',
                    content: msg.trim()
                });
                print('|G✓ Message sent to SysOp.|N');
            }
            
        } catch (error) {
            print('|Y Paging SysOp...|N');
            print('');
            print('|c The SysOp has been notified.|N');
            print('|K Note: SysOp may not always be available.|N');
        } finally {
            setStatus('');
        }
        
        print('');
        print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
    }

    async function showOneliners() {
        try {
            const result = await api('/oneliners');
            const olList = result.oneliners || result.data || [];
            
            clearScreen();
            print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W ONELINERS |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
            print('');
            
            if (olList.length > 0) {
                olList.slice(0, 15).forEach(ol => {
                    print(`|c${(ol.user?.handle || 'Anonymous').padEnd(12)}|K>|N ${ol.content}`);
                });
            } else {
                print('|K No oneliners yet. Be the first!|N');
            }
            
            print('');
            print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
            print('');
            print('|Y[P]|N Post new oneliner  |Y[Q]|N Quit');
            
            const cmd = await promptUser('|cChoice|N');
            if (cmd.toLowerCase() === 'p') {
                await postOneliner();
            }
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function postOneliner() {
        if (!state.user || state.user.is_guest) {
            print('|rYou must be logged in to post.|N');
            return;
        }

        const content = await promptUser('|cYour oneliner (max 80 chars)|N');
        if (!content || content.trim() === '') {
            print('|rCancelled.|N');
            return;
        }

        if (content.length > 80) {
            print('|rOneliner too long! Max 80 characters.|N');
            return;
        }

        try {
            await api('/oneliners', 'POST', { content: content.trim() });
            print('|G✓ Oneliner posted!|N');
            await showOneliners();
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    let currentPMList = [];

    async function showPrivateMessages() {
        try {
            const unread = await api('/pm/unread-count');
            const inbox = await api('/pm/inbox');
            currentPMList = inbox.messages || inbox.data || [];
            
            clearScreen();
            print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W PRIVATE MESSAGES |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
            print('');
            print(`|c Unread: |R${unread.count || 0}|c messages|N`);
            print('');
            
            if (currentPMList.length === 0) {
                print('|K Your inbox is empty.|N');
            } else {
                print('|c #   Status  From              Subject|N');
                print('|B ──  ──────  ────────────────  ─────────────────────────────────────|N');
                currentPMList.slice(0, 15).forEach((msg, i) => {
                    const num = String(i + 1).padStart(2);
                    const status = msg.read_at ? '|K  read|N' : '|R* NEW|N';
                    const from = (msg.sender?.handle || 'Unknown').padEnd(16);
                    const subject = (msg.subject || '(no subject)').substring(0, 35);
                    print(`|Y${num}|N  ${status}  ${from}  ${subject}`);
                });
            }
            
            print('');
            print('|B▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄|N');
            print('');
            print('|Y[#]|N Read message  |Y[W]|N Write new  |Y[S]|N Sent  |Y[Q]|N Quit');
            
            const cmd = await promptUser('|cChoice|N');
            await handlePMCommand(cmd);
            
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function handlePMCommand(cmd) {
        if (cmd.toLowerCase() === 'q') {
            return;
        } else if (cmd.toLowerCase() === 'w') {
            await writePM();
        } else if (cmd.toLowerCase() === 's') {
            await showSentPMs();
        } else if (/^\d+$/.test(cmd)) {
            await readPM(parseInt(cmd));
        }
    }

    async function readPM(num) {
        const idx = num - 1;
        if (!currentPMList[idx]) {
            print('|rInvalid message number.|N');
            return;
        }

        const msg = currentPMList[idx];
        
        try {
            const result = await api(`/pm/${msg.id}`);
            const pm = result.data;

            clearScreen();
            print('|B═══════════════════════════════════════════════════════════════════════|N');
            print(`|YFrom:|N ${pm.sender?.handle || 'Unknown'}`);
            print(`|YTo:|N ${pm.recipient?.handle || 'You'}`);
            print(`|YSubject:|N ${pm.subject || '(no subject)'}`);
            print(`|YDate:|N ${pm.created_at}`);
            print('|B───────────────────────────────────────────────────────────────────────|N');
            print('');
            print(pm.content);
            print('');
            print('|B═══════════════════════════════════════════════════════════════════════|N');
            print('');
            print('|Y[R]|N Reply  |Y[D]|N Delete  |Y[Q]|N Back');

            const action = await promptUser('|cAction|N');
            if (action.toLowerCase() === 'r') {
                await replyToPM(pm);
            } else if (action.toLowerCase() === 'd') {
                await deletePM(pm.id);
            }

        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function writePM() {
        if (!state.user || state.user.is_guest) {
            print('|rYou must be logged in to send messages.|N');
            return;
        }

        print('');
        const recipient = await promptUser('|cTo (username or handle)|N');
        if (!recipient || recipient.trim() === '') {
            print('|rCancelled.|N');
            return;
        }

        const subject = await promptUser('|cSubject|N');
        
        print('|cMessage (empty line to finish):|N');
        let content = '';
        let line = '';
        while ((line = await promptUser('')) !== '') {
            content += line + '\n';
        }

        if (!content.trim()) {
            print('|rMessage cannot be empty. Cancelled.|N');
            return;
        }

        try {
            setStatus('Sending...');
            await api('/pm/send', 'POST', {
                recipient: recipient.trim(),
                subject: subject || '(no subject)',
                content: content.trim()
            });
            print('|G✓ Message sent!|N');
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        } finally {
            setStatus('');
        }
    }

    async function replyToPM(originalPM) {
        print('');
        print('|cYour reply (empty line to finish):|N');
        let content = '';
        let line = '';
        while ((line = await promptUser('')) !== '') {
            content += line + '\n';
        }

        if (!content.trim()) {
            print('|rCancelled.|N');
            return;
        }

        try {
            setStatus('Sending reply...');
            await api('/pm/send', 'POST', {
                recipient_id: originalPM.sender_id,
                subject: `Re: ${originalPM.subject || '(no subject)'}`,
                content: content.trim()
            });
            print('|G✓ Reply sent!|N');
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        } finally {
            setStatus('');
        }
    }

    async function deletePM(pmId) {
        try {
            await api(`/pm/${pmId}`, 'DELETE');
            print('|G✓ Message deleted.|N');
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function showSentPMs() {
        try {
            const result = await api('/pm/sent');
            const msgList = result.messages || result.data || [];
            
            clearScreen();
            print('|y=== SENT MESSAGES ===|N');
            print('');

            if (msgList.length === 0) {
                print('|K No sent messages.|N');
            } else {
                msgList.slice(0, 15).forEach((msg, i) => {
                    const num = String(i + 1).padStart(2);
                    const to = (msg.recipient?.handle || 'Unknown').padEnd(16);
                    const subject = (msg.subject || '(no subject)').substring(0, 35);
                    print(`|Y${num}|N  To: ${to}  ${subject}`);
                });
            }

            print('');
            print('Press any key to continue...');

        } catch (error) {
            print(`|rError: ${error.message}|N`);
        }
    }

    async function showUserSettings() {
        if (!state.user) {
            print('|rYou must be logged in to access settings.|N');
            return;
        }
        
        clearScreen();
        print('|B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|W USER PROFILE |B▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀|N');
        print('');
        print(`|YHandle:|N      ${state.user.handle}`);
        print(`|YUsername:|N    ${state.user.username || state.user.handle}`);
        print(`|YEmail:|N       ${state.user.email}`);
        print(`|YLevel:|N       ${state.user.level}`);
        print(`|YCredits:|N     ${state.user.credits || 0}`);
        print(`|YTotal Calls:|N ${state.user.total_calls || 0}`);
        print(`|YLocation:|N    ${state.user.location || 'Not set'}`);
        print(`|YSignature:|N   ${state.user.signature || 'Not set'}`);
        print('');
        print('|B═══════════════════════════════════════════════════════════════════════|N');
        print('');
        print('|Y[E]|N Edit profile  |Y[P]|N Change password  |Y[Q]|N Quit');
        
        const cmd = await promptUser('|cChoice|N');
        if (cmd.toLowerCase() === 'e') {
            await editUserProfile();
        } else if (cmd.toLowerCase() === 'p') {
            await changePassword();
        }
    }

    async function editUserProfile() {
        print('');
        print('|cLeave blank to keep current value.|N');
        print('');

        const location = await promptUser(`|cLocation (${state.user.location || 'Not set'})|N`);
        const signature = await promptUser(`|cSignature (${state.user.signature || 'Not set'})|N`);

        const updates = {};
        if (location && location.trim()) updates.location = location.trim();
        if (signature && signature.trim()) updates.signature = signature.trim();

        if (Object.keys(updates).length === 0) {
            print('|cNo changes made.|N');
            return;
        }

        try {
            setStatus('Updating profile...');
            const result = await api('/user/profile', 'PUT', updates);
            state.user = { ...state.user, ...result.data };
            print('|G✓ Profile updated!|N');
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        } finally {
            setStatus('');
        }
    }

    async function changePassword() {
        print('');
        const current = await promptUser('|cCurrent password|N', true);
        if (!current) {
            print('|rCancelled.|N');
            return;
        }

        const newPass = await promptUser('|cNew password|N', true);
        if (!newPass || newPass.length < 8) {
            print('|rPassword must be at least 8 characters.|N');
            return;
        }

        const confirm = await promptUser('|cConfirm new password|N', true);
        if (newPass !== confirm) {
            print('|rPasswords do not match.|N');
            return;
        }

        try {
            setStatus('Changing password...');
            await api('/user/password', 'PUT', {
                current_password: current,
                new_password: newPass,
                new_password_confirmation: confirm
            });
            print('|G✓ Password changed successfully!|N');
        } catch (error) {
            print(`|rError: ${error.message}|N`);
        } finally {
            setStatus('');
        }
    }

    function showHelp() {
        print('');
        print('|y=== HELP ===|N');
        print('');
        print('|cGlobal Commands:|N');
        print('  |YHELP|N or |Y?|N     - Show this help');
        print('  |YCLS|N          - Clear screen');
        print('  |YWHO|N or |YW|N    - Who\'s online');
        print('  |YTIME|N         - Show current time');
        print('  |YSETTINGS|N     - Terminal settings');
        print('  |YQUIT|N or |YG|N   - Logout/Go back');
        print('');
        print('|cNavigation:|N');
        print('  Use menu letters/numbers to navigate');
        print('  Press ENTER to refresh current screen');
        print('  Use UP/DOWN arrows for command history');
        print('  Press ESC to cancel typing');
        print('');
    }

    function showVersion() {
        print('');
        print('|y╔══════════════════════════════════════╗|N');
        print('|y║|w       PUNKTET BBS v1.0               |y║|N');
        print('|y║|c   Powered by Laravel + MariaDB      |y║|N');
        print('|y║|c     The Retro BBS Experience        |y║|N');
        print('|y╚══════════════════════════════════════╝|N');
        print('');
    }

    function showTime() {
        const now = new Date();
        print(`|cCurrent time: |y${now.toLocaleString()}|N`);
    }

    function showSettings() {
        elements.settingsModal.classList.add('active');
    }

    // =====================================================
    // Helper Functions
    // =====================================================
    
    function setPrompt(text) {
        elements.prompt.textContent = `${text}>`;
    }

    function setStatus(text) {
        elements.statusMessage.textContent = text;
    }

    function updateUserDisplay() {
        if (state.user) {
            elements.userInfo.textContent = state.user.handle;
            elements.timeRemaining.textContent = state.user.time_limit_minutes 
                ? `${state.user.time_limit_minutes}m` 
                : '∞';
        } else {
            elements.userInfo.textContent = 'Guest';
            elements.timeRemaining.textContent = '∞';
        }
    }

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Simple prompt function
    function promptUser(label, isPassword = false) {
        return new Promise(resolve => {
            print(`|c${label}:|N `, { newline: false });
            
            // Set prompting state
            state.isPrompting = true;
            state.promptResolve = (value) => {
                print(isPassword ? '********' : `|G${value}|N`);
                resolve(value);
            };
            
            elements.input.type = isPassword ? 'password' : 'text';
            elements.input.focus();
        });
    }

    // =====================================================
    // Settings Management
    // =====================================================
    
    function setupSettings() {
        // Load saved settings
        const savedSettings = JSON.parse(localStorage.getItem('punktet_settings') || '{}');
        
        if (savedSettings.baudRate !== undefined) {
            state.baudRate = savedSettings.baudRate;
            elements.speedSelect.value = savedSettings.baudRate;
            updateSpeedDisplay();
        }
        
        if (savedSettings.fontSize) {
            document.body.classList.remove('font-12', 'font-14', 'font-16', 'font-18');
            document.body.classList.add(`font-${savedSettings.fontSize}`);
            elements.fontSizeSelect.value = savedSettings.fontSize;
        }
        
        if (savedSettings.theme) {
            document.body.className = document.body.className.replace(/theme-\w+/g, '');
            if (savedSettings.theme !== 'classic') {
                document.body.classList.add(`theme-${savedSettings.theme}`);
            }
            elements.themeSelect.value = savedSettings.theme;
        }
        
        if (savedSettings.soundEnabled !== undefined) {
            state.soundEnabled = savedSettings.soundEnabled;
            elements.soundEnabled.checked = savedSettings.soundEnabled;
        }
        
        if (savedSettings.scanlines) {
            document.body.classList.add('scanlines');
            elements.scanlineEnabled.checked = true;
        }

        // Event listeners
        elements.settingsBtn.addEventListener('click', showSettings);
        elements.closeSettings.addEventListener('click', () => {
            elements.settingsModal.classList.remove('active');
        });
        
        elements.soundToggle.addEventListener('click', () => {
            state.soundEnabled = !state.soundEnabled;
            elements.soundEnabled.checked = state.soundEnabled;
            elements.soundToggle.textContent = state.soundEnabled ? '🔊' : '🔇';
            saveSettings();
        });

        elements.speedSelect.addEventListener('change', () => {
            state.baudRate = parseInt(elements.speedSelect.value);
            updateSpeedDisplay();
            saveSettings();
        });

        elements.fontSizeSelect.addEventListener('change', () => {
            document.body.classList.remove('font-12', 'font-14', 'font-16', 'font-18');
            document.body.classList.add(`font-${elements.fontSizeSelect.value}`);
            saveSettings();
        });

        elements.themeSelect.addEventListener('change', () => {
            document.body.className = document.body.className.replace(/theme-\w+/g, '');
            if (elements.themeSelect.value !== 'classic') {
                document.body.classList.add(`theme-${elements.themeSelect.value}`);
            }
            saveSettings();
        });

        elements.soundEnabled.addEventListener('change', () => {
            state.soundEnabled = elements.soundEnabled.checked;
            elements.soundToggle.textContent = state.soundEnabled ? '🔊' : '🔇';
            saveSettings();
        });

        elements.scanlineEnabled.addEventListener('change', () => {
            document.body.classList.toggle('scanlines', elements.scanlineEnabled.checked);
            saveSettings();
        });
    }

    function updateSpeedDisplay() {
        const speedNames = {
            0: 'Sci-Fi Speed',
            2400: '2400 baud',
            9600: '9600 baud',
            14400: '14400 baud',
            28800: '28800 baud',
            56000: '56K modem'
        };
        elements.connectionSpeed.textContent = speedNames[state.baudRate] || 'Unknown';
    }

    function saveSettings() {
        const settings = {
            baudRate: state.baudRate,
            fontSize: elements.fontSizeSelect.value,
            theme: elements.themeSelect.value,
            soundEnabled: state.soundEnabled,
            scanlines: elements.scanlineEnabled.checked
        };
        localStorage.setItem('punktet_settings', JSON.stringify(settings));
    }

    // =====================================================
    // Modem Connection Simulation
    // =====================================================
    
    async function showModemConnection() {
        const skipModem = localStorage.getItem('punktet_skip_modem');
        if (skipModem === 'true') {
            return;
        }
        
        clearScreen();
        
        print('|K═══════════════════════════════════════════════════════════════════════|N');
        print('|c  PUNKTET BBS Terminal v1.0                                            |N');
        print('|K═══════════════════════════════════════════════════════════════════════|N');
        print('');
        print('|YInitializing modem...|N');
        await sleep(300);
        
        print('|cATZ|N                                         |GOK|N');
        await sleep(200);
        
        print('|cATDT punktet.no|N');
        await sleep(400);
        
        // Play modem sound
        playModemSound();
        
        print('');
        print('|YCONNECTING...|N');
        print('');
        
        // Modem negotiation animation
        const modemChars = '░▒▓█▓▒░';
        for (let i = 0; i < 3; i++) {
            for (let j = 0; j < modemChars.length; j++) {
                print(`|c  ${modemChars[j]}${modemChars[(j+1)%modemChars.length]}${modemChars[(j+2)%modemChars.length]} Negotiating... ${modemChars[(j+3)%modemChars.length]}${modemChars[(j+4)%modemChars.length]}${modemChars[(j+5)%modemChars.length]}|N`, { newline: false });
                await sleep(100);
                print('\r                                            \r', { newline: false });
            }
        }
        
        const speedNames = {
            0: '∞ Sci-Fi',
            2400: '2400',
            9600: '9600',
            14400: '14400',
            28800: '28800',
            56000: '56000'
        };
        
        print('');
        print(`|GCONNECT ${speedNames[state.baudRate] || '56000'}|N`);
        await sleep(300);
        print('');
        print('|G════════════════════════════════════════════════════════════════════════|N');
        print('|G  Connection established!                                               |N');
        print(`|G  Speed: ${speedNames[state.baudRate] || '56000'} bps   Protocol: 8-N-1   Error Correction: V.42bis  |N`);
        print('|G════════════════════════════════════════════════════════════════════════|N');
        print('');
        await sleep(500);
    }

    // =====================================================
    // Initialization
    // =====================================================
    
    async function init() {
        // Initialize DOM elements first
        initElements();
        
        setupInput();
        setupSettings();
        
        // Show modem connection animation on first visit
        const hasConnectedBefore = sessionStorage.getItem('punktet_connected');
        if (!hasConnectedBefore) {
            await showModemConnection();
            sessionStorage.setItem('punktet_connected', 'true');
        }
        
        // Check for existing session
        if (state.token) {
            try {
                const result = await api('/auth/me');
                state.user = result.user;
                updateUserDisplay();
                goToMainMenu();
                return;
            } catch (e) {
                // Token invalid, continue to login
                state.token = null;
                localStorage.removeItem('punktet_token');
            }
        }
        
        // Show login screen
        showLoginPrompt();
    }

    // Start the terminal
    document.addEventListener('DOMContentLoaded', init);

})();
