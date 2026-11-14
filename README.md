# Plugin Alexa Devices para Jeedom

Integración nativa y completa para dispositivos **Amazon Echo y Alexa** adaptada a la arquitectura de **Jeedom v4 / v4.4+**.

Este plugin utiliza la librería asíncrona moderna [`aioamazondevices`](https://github.com/chemelli74/aioamazondevices), garantizando compatibilidad con el protocolo push oficial **HTTP/2 de Amazon AVS**.

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

## 🏛️ Arquitectura del Plugin

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

1. **Demonio en Python 3**: El plugin ejecuta un servicio en segundo plano dentro de un entorno virtual aislado (`demond/venv`) con `aioamazondevices`.
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
│   ├── i18n/
│   │   ├── en_US.json                    # Diccionario oficial en inglés
│   │   ├── es_ES.json                    # Diccionario oficial en español
│   │   └── fr_FR.json                    # Diccionario oficial en francés
│   ├── php/
│   │   └── jeeAlexaDevices.php           # Webhook receptor de eventos push del demonio
│   └── template/
│       ├── dashboard/                    # Widget interactivo para Dashboard
│       └── mobile/                       # Widget optimizado para móvil
├── desktop/
│   ├── css/
│   │   └── alexa_devices.css             # Estilos de tarjetas de equipos
│   ├── js/
│   │   └── alexa_devices.js              # Lógica de interfaz de usuario
│   ├── modal/
│   │   └── login.modal.php               # Modal de inicio de sesión con soporte 2FA OTP
│   └── php/
│       └── alexa_devices.php             # Pantalla de gestión de dispositivos
├── docs/                                 # Documentación multilingüe oficial (en_US, fr_FR, es_ES)
├── plugin_info/
│   ├── alexa_devices_icon.png            # Icono del plugin para Jeedom
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

## 🛠️ Instalación y Configuración

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
3. Todos tus altavoces y pantallas Echo aparecerán listados con sus mandos, volumen, sensores y controles interactivos listos para usar en tus escenarios y en el Dashboard.

---

## ⚖️ Descargo de Responsabilidad (Disclaimer)

1. **No Afiliación con Amazon**: Este plugin es un proyecto de código abierto desarrollado de forma independiente por la comunidad y **NO** está respaldado, patrocinado, certificado ni afiliado de ninguna manera con Amazon.com, Inc., Amazon Technologies, Inc. ni ninguna de sus filiales. *Amazon*, *Echo*, *Alexa* y todas las marcas y logotipos relacionados son marcas registradas propiedad de Amazon.com, Inc. o sus subsidiarias.
2. **Exención de Garantía (AS IS)**: Este software se suministra "tal cual" (*AS IS*), sin garantías de ningún tipo, explícitas o implícitas. Dado que depende de servicios en la nube y protocolos de terceros sujetos a modificaciones sin previo aviso por parte de Amazon, el funcionamiento puede verse afectado o interrumpido temporalmente.
3. **Limitación de Responsabilidad**: En ningún caso los autores o colaboradores serán responsables de ningún daño directo, indirecto, incidental, especial o consecuente (incluyendo, entre otros, pérdida de datos, interrupción de servicios, fallos en automatizaciones del hogar o costes de recuperación) derivados del uso o imposibilidad de uso de este software. El usuario asume toda la responsabilidad derivada de su utilización.
