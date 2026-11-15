# Changelog Alexa Devices

## [0.9.0] - 15/11/2026
### Añadido y Mejorado
- Versión inicial beta v0.9.0 para Jeedom v4 / v4.4+ con soporte HTTP/2 push en tiempo real (`aioamazondevices >= 17.2.12`).
- Descubrimiento automático de dispositivos Echo, TV con Alexa y grupos de altavoces (`WHA` / Speaker Group).
- **Widget del Dashboard completo**: controles multimedia (`Play`, `Pause`, `Stop`, `Next`, `Previous`), deslizador de volumen bidireccional, selector desplegable de **todos** los comandos del dispositivo, desplegables de sonidos, *info skills* y rutinas sincronizadas, más enlace directo (`?` y título) a la configuración del dispositivo.
- **TTS en grupos de altavoces (`WHA`)**: `speak` envía el audio en paralelo a todos los miembros del grupo; soporte nativo de susurro (`whisper`) y etiquetas SSML.
- **Música y Sonidos**: soporte de `PROVEEDOR|búsqueda` (Spotify, TuneIn, Amazon Music, Apple Music, Deezer) y comandos de voz por texto (`text_command`).
- **Probador interactivo de comandos y Rutinas**: ejemplos listos para usar en 3 idiomas, sincronización de rutinas Alexa y guía de integración con escenarios Jeedom y acciones de hogar digital.
