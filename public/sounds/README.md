# Sound Files for PUNKTET BBS Terminal

This directory contains sound effect files for the terminal emulator.

## Required Files

1. **modem-connect.mp3** - Classic modem handshake sound (dial-up connection)
2. **keyclick.mp3** - Mechanical keyboard click sound
3. **bell.mp3** - Terminal bell sound (BEL character)

## Finding Sound Files

Free retro computer sounds can be found at:
- https://freesound.org/
- https://archive.org/details/audio

Search for:
- "modem dial up sound"
- "typewriter key"
- "terminal bell"

## Audio Format

- Format: MP3 (for browser compatibility)
- Sample rate: 44100 Hz
- Channels: Mono or Stereo
- Duration: Keep short (< 3 seconds for keyclick, < 30 seconds for modem)

## Usage

These sounds are played by the JavaScript terminal when:
- `modem-connect.mp3` - On successful login
- `keyclick.mp3` - On each keypress (optional)
- `bell.mp3` - When receiving a page or alert
