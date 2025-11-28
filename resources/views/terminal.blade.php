<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PUNKTET BBS - Terminal</title>
    <link rel="stylesheet" href="{{ asset('css/terminal.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="terminal-container">
        <div id="terminal-header">
            <div class="header-left">
                <span class="bbs-name">PUNKTET BBS</span>
                <span class="node-info" id="nodeInfo">Node 1</span>
            </div>
            <div class="header-center">
                <span id="currentArea">Main Menu</span>
            </div>
            <div class="header-right">
                <span id="userInfo">Guest</span>
                <span id="timeRemaining" class="time-display">∞</span>
            </div>
        </div>

        <div id="terminal-screen">
            <div id="output"></div>
            <div id="input-line">
                <span id="prompt" class="prompt">></span>
                <input type="text" id="input" autocomplete="off" spellcheck="false" />
                <span id="cursor" class="cursor">█</span>
            </div>
        </div>

        <div id="terminal-footer">
            <div class="footer-left">
                <span id="connectionSpeed">Sci-Fi Speed</span>
            </div>
            <div class="footer-center">
                <span id="statusMessage"></span>
            </div>
            <div class="footer-right">
                <button id="soundToggle" class="icon-btn" title="Toggle Sound">🔊</button>
                <button id="settingsBtn" class="icon-btn" title="Settings">⚙️</button>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <h2>Terminal Settings</h2>
            <div class="setting-group">
                <label>Connection Speed:</label>
                <select id="speedSelect">
                    <option value="2400">2400 baud</option>
                    <option value="9600">9600 baud</option>
                    <option value="14400">14400 baud</option>
                    <option value="28800">28800 baud</option>
                    <option value="56000">56K modem</option>
                    <option value="0" selected>Sci-Fi Speed (instant)</option>
                </select>
            </div>
            <div class="setting-group">
                <label>Font Size:</label>
                <select id="fontSizeSelect">
                    <option value="12">Small (12px)</option>
                    <option value="14" selected>Medium (14px)</option>
                    <option value="16">Large (16px)</option>
                    <option value="18">Extra Large (18px)</option>
                </select>
            </div>
            <div class="setting-group">
                <label>Theme:</label>
                <select id="themeSelect">
                    <option value="classic" selected>Classic Green</option>
                    <option value="amber">Amber</option>
                    <option value="blue">IBM Blue</option>
                    <option value="white">White on Black</option>
                </select>
            </div>
            <div class="setting-group">
                <label><input type="checkbox" id="soundEnabled" checked> Enable Sound Effects</label>
            </div>
            <div class="setting-group">
                <label><input type="checkbox" id="scanlineEnabled"> Enable Scanlines</label>
            </div>
            <button id="closeSettings" class="btn">Close</button>
        </div>
    </div>

    <!-- Audio elements for modem sounds -->
    <audio id="modemConnect" preload="auto">
        <source src="{{ asset('sounds/modem-us-robotics-417318.mp3') }}" type="audio/mpeg">
    </audio>
    <audio id="keyClick" preload="auto">
        <source src="{{ asset('sounds/keyclick.mp3') }}" type="audio/mpeg">
    </audio>
    <audio id="bellSound" preload="auto">
        <source src="{{ asset('sounds/bell.mp3') }}" type="audio/mpeg">
    </audio>

    <script>
        window.PUNKTET = {
            apiBase: '{{ url("/api") }}',
            csrfToken: '{{ csrf_token() }}',
            locale: '{{ app()->getLocale() }}',
            user: null,
            token: localStorage.getItem('punktet_token') || null
        };
    </script>
    <script src="{{ asset('js/terminal.js') }}"></script>
</body>
</html>
