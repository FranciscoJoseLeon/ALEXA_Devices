# Plugin Alexa Devices para Jeedom

Port directo y completo del componente oficial **`alexa_devices`** de **Home Assistant** ([homeassistant/components/alexa_devices](https://github.com/home-assistant/core/tree/dev/homeassistant/components/alexa_devices)) adaptado a la arquitectura de **Jeedom v4 / v4.4+**.

Este plugin utiliza la librería asíncrona moderna [`aioamazondevices`](https://github.com/chemelli74/aioamazondevices), la misma empleada por Home Assistant, garantizando compatibilidad con el protocolo push oficial **HTTP/2 de Amazon AVS**.

---

## 🚀 Características Principales

- **Descubrimiento Automático**: Detecta todos los altavoces Amazon Echo (Dot, Show, Pop, Studio, Fire TV, etc.) asociados a tu cuenta.
- **Push en Tiempo Real (HTTP/2)**: Conexión persistente mediante stream HTTP/2 con Amazon. Las actualizaciones de volumen, sensores, estado multimedia y comandos de voz se reflejan instantáneamente sin necesidad de peticiones de sondeo continuas (evitando bloqueos por *Rate Limiting / Too Many Requests*).
- **Text-to-Speech (TTS)**: Comando para hacer hablar a cualquier Echo (`speak`).
- **Anuncios (Announcements)**: Emite avisos sonoros con tono de atención previo (`announcement`).
- **Comandos de Voz por Texto (`text_command`)**: Permite simular comandos hablados directamente en texto (por ejemplo: *"enciende la luz del salón"*, *"pon música jazz en Spotify"*, etc.).
- **Control Multimedia Completo**: Reproducir, Pausar, Siguiente, Anterior y estado actual (*PLAYING*, *PAUSED*, *IDLE*).
- **Control de Volumen Bidireccional**: Slider de 0 a 100 con sincronización inmediata.
- **Modo No Molestar (DND)**: Activación y desactivación del modo No Molestar.
- **Historial Vocal**: Muestra en un comando info el último comando de voz que capturó el Echo.
- **Sensores Integrados**: Soporte para sensores de temperatura y detección de presencia en dispositivos compatibles (Echo 4ª gen, Echo Dot con reloj, etc.).
- **Autenticación Segura con 2FA / OTP**: Asistente interactivo compatible con verificación en dos pasos (SMS o apps como Google Authenticator).

---

## 🏛️ Arquitectura del Port

```
┌─────────────────────────────────────────────────────────────┐
│                       JEEDOM CORE                           │
│  - eqLogic: Dispositivos Echo                               │
│  - cmd: Speak, Volume, DND, Media, TextCommand              │
│  - Webhook: core/php/jeeAlexaDevices.php                    │
└──────────────────────────────┬──────────────────────────────┘
                               │
               (Llamadas REST / Webhook local)
                               │
┌──────────────────────────────▼──────────────────────────────┐
│             DEMONIO PYTHON (alexa_devices_daemon.py)        │
│  - Servidor REST local (aiohttp.web) en 127.0.0.1:55065      │
│  - Librería: aioamazondevices + httpx (HTTP/2)              │
│  - Persistencia de sesión y tokens: data/storage/           │
└──────────────────────────────┬──────────────────────────────┘
                               │
            (Stream push HTTP/2 + APIs Amazon AVS)
                               │
┌──────────────────────────────▼──────────────────────────────┐
│                    SERVIDORES DE AMAZON                     │
│  - Dispositivos Echo, Estado, Sensores y Notificaciones     │
└─────────────────────────────────────────────────────────────┘
```

1. **Demonio en Python 3**: Dado que `aioamazondevices` es una librería asíncrona de Python (`asyncio`), el plugin ejecuta un demonio en segundo plano dentro de un entorno virtual aislado (`demond/venv`).
2. **Comunicación Local**:
   - Cuando Jeedom envía una orden (ej. *TTS*, *Volumen* o *Pausa*), la clase PHP hace una petición `POST` al demonio en `http://127.0.0.1:55065/api/command`.
   - Cuando Amazon envía un evento por el stream HTTP/2 (ej. alguien cambió el volumen físicamente o habló al Echo), el demonio llama al webhook de Jeedom (`core/php/jeeAlexaDevices.php`) con un token de seguridad para refrescar los comandos de forma inmediata.

---

## 📂 Estructura de Archivos

```
alexa_devices/
├── core/
│   ├── ajax/
│   │   └── alexa_devices.ajax.php        # Controlador AJAX (login, sync, health)
│   ├── class/
│   │   └── alexa_devices.class.php       # Clases eqLogic y cmd de Jeedom
│   └── php/
│       └── jeeAlexaDevices.php           # Webhook receptor de eventos push del demonio
├── desktop/
│   ├── css/
│   │   └── alexa_devices.css             # Estilos de tarjetas de equipos
│   ├── js/
│   │   └── alexa_devices.js              # Lógica de interfaz de usuario
│   ├── modal/
│   │   └── login.modal.php               # Modal de inicio de sesión con soporte 2FA OTP
│   └── php/
│       └── alexa_devices.php             # Pantalla de gestión de dispositivos
├── plugin_info/
│   ├── alexa_devices_icon.png            # Icono del plugin
│   ├── configuration.php                 # Página de configuración del plugin
│   ├── info.json                         # Metadatos del plugin Jeedom
│   └── install.php                       # Hooks de instalación / desinstalación
├── resources/
│   ├── data/
│   │   └── storage/                      # Almacenamiento seguro de tokens y cookies
│   ├── demond/
│   │   └── alexa_devices_daemon.py       # Demonio Python con aioamazondevices
│   ├── install_apt.sh                    # Script bash para dependencias y venv
│   └── requirements.txt                  # Dependencias Python
└── README.md
```

---

## 🔔 Sistema de Notificaciones y Mantenimiento

Este plugin cuenta con un sistema de separación de alertas entre el desarrollador y el usuario final:

### 1. Para ti (Desarrollador): Alertas de cambios en Home Assistant
- Los usuarios finales **no ven** nada sobre Home Assistant.
- El flujo [`.github/workflows/sync_upstream.yml`](.github/workflows/sync_upstream.yml) analiza los cambios en el componente `alexa_devices` de Home Assistant Core.
- Cuando hay una nueva versión de la librería `aioamazondevices` o commits en Home Assistant, GitHub crea automáticamente un **Issue privado en tu repositorio asignado a ti**, enviándote una notificación push/email con el informe de cambios y preparando un Pull Request.

### 2. Para el Usuario Final de Jeedom: Reinstalación de Dependencias
- Si Amazon actualiza sus servidores o protocolos de autenticación y la versión de la librería queda desfasada:
  1. **Detección Automática**: `dependancy_info()` en `alexa_devices.class.php` compara la versión instalada en el venv contra la versión requerida.
  2. **Aviso en Jeedom**: Jeedom marca las dependencias como `nok`, mostrando el aviso al usuario para que pulse en **Reinstalar**.
  3. **Centro de Mensajes de Jeedom**: Si el demonio detecta un fallo de protocolo de Amazon, envía una notificación al centro de mensajes de Jeedom pidiéndole al usuario que reinstale las dependencias desde el panel del plugin para actualizar los componentes de Amazon.

---

### 1. Despliegue en Jeedom
Copia o clona este repositorio dentro del directorio de plugins de Jeedom:
```bash
cd /var/www/html/plugins/
git clone https://github.com/tu-usuario/alexa_devices.git alexa_devices
chown -R www-data:www-data alexa_devices
```

### 2. Instalación de Dependencias
1. Entra en Jeedom y ve a **Plugins > Gestión de Plugins > Alexa Devices**.
2. Haz clic en **Activar**.
3. En la sección **Dependencias**, haz clic en **Instalar**. Espera a que el proceso termine (creará el entorno virtual Python y descargará `aioamazondevices`, `aiohttp` y `httpx`).

### 3. Autenticación con Amazon
1. En la configuración del plugin, selecciona tu dominio (ejemplo: `Amazon España (amazon.es)`).
2. Haz clic en el botón **Iniciar Sesión / 2FA OTP**.
3. Introduce tu correo, contraseña y el código de 6 dígitos de tu app de verificación en dos pasos (o SMS).
4. El demonio validará la sesión y guardará de forma cifrada los tokens de sesión en `resources/data/storage`.

### 4. Sincronización de Dispositivos
1. Dirígete a **Plugins > Multimedia > Alexa Devices**.
2. Haz clic en **Sincronizar Dispositivos**.
3. Todos tus altavoces y pantallas Echo aparecerán listados con sus mandos, volumen, sensores y controles interactivos listos para usar en tus escenarios.
