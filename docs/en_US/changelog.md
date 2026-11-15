# Changelog Alexa Devices

## [0.9.0] - 2026-11-15
### Added & Improved
- Initial v0.9.0 beta release for Jeedom v4 / v4.4+ with real-time HTTP/2 push events (`aioamazondevices >= 17.2.12`).
- Automatic discovery of Echo speakers, Alexa-enabled TVs, and Speaker Groups (`WHA`).
- **Full Dashboard Widget**: media controls (`Play`, `Pause`, `Stop`, `Next`, `Previous`), bidirectional volume slider, dropdown selector for **all** device commands, dropdown lists for built-in sounds, info skills, and synchronized routines, plus direct link (`?` and title) to device configuration.
- **Speaker Group (`WHA`) TTS**: `speak` dispatches speech in parallel to every cluster member; native `whisper` command and SSML tag support.
- **Music & Sounds**: supports `PROVIDER|query` (Spotify, TuneIn, Amazon Music, Apple Music, Deezer) and emulated voice commands (`text_command`).
- **Interactive Command Tester & Routines**: trilingual examples, Alexa routine synchronization, and guide for Jeedom scenarios and smart home actions.
