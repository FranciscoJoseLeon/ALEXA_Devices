#!/usr/bin/env python3
"""
Alexa Devices Daemon for Jeedom
Async daemon powered by aioamazondevices and HTTP/2 AVS push.
"""

import argparse
import asyncio
import json
import logging
import os
import signal
import sys
from pathlib import Path
from typing import Any, Optional

import aiohttp
from aiohttp import web
import httpx

try:
    from aioamazondevices.api import AmazonEchoApi
    from aioamazondevices.exceptions import (
        CannotAuthenticate,
        CannotConnect,
        CannotRetrieveData,
        CannotSetFeature,
    )
    from aioamazondevices.structures import AmazonSaveDataConfig, AmazonDevice
except ImportError as err:
    print(f"Error importing aioamazondevices: {err}", file=sys.stderr)
    print("Please install requirements: pip install -r requirements.txt", file=sys.stderr)
    sys.exit(1)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
LOGGER = logging.getLogger("alexa_devices_daemon")


class AlexaDaemon:
    def __init__(
        self,
        port: int,
        jeedom_url: str,
        token: str,
        storage_dir: str,
        poll_interval: int = 300,
    ):
        self.port = port
        self.jeedom_url = jeedom_url
        self.token = token
        self.storage_dir = Path(storage_dir)
        self.storage_dir.mkdir(parents=True, exist_ok=True)
        self.poll_interval = poll_interval

        self.session: Optional[aiohttp.ClientSession] = None
        self.httpx_client: Optional[httpx.AsyncClient] = None
        self.api: Optional[AmazonEchoApi] = None
        self.app: Optional[web.Application] = None
        self.runner: Optional[web.AppRunner] = None
        self.devices: dict[str, AmazonDevice] = {}
        self.is_authenticated = False
        self.current_user: Optional[str] = None
        self.poll_task: Optional[asyncio.Task] = None
        self.session_file = self.storage_dir / "daemon_session.json"

    async def init_session_from_disk(self):
        """Intenta inicializar la sesión de Amazon a partir de datos guardados previamente."""
        if not self.session_file.exists():
            LOGGER.info("No se encontró sesión previa en %s", self.session_file)
            return False

        try:
            with open(self.session_file, "r", encoding="utf-8") as f:
                saved = json.load(f)

            username = saved.get("username")
            password = saved.get("password")
            login_data = saved.get("login_data")

            if not username or not password:
                return False

            LOGGER.info("Intentando restaurar sesión para el usuario %s", username)
            self.session = aiohttp.ClientSession()
            self.api = AmazonEchoApi(
                self.session,
                username,
                password,
                login_data=login_data,
                save_data=AmazonSaveDataConfig(path=self.storage_dir),
            )

            # Registrar listeners de eventos push HTTP/2
            self._register_event_listeners()

            await self.api.login.login_mode_stored_data()
            self.is_authenticated = True
            self.current_user = username
            LOGGER.info("Sesión restaurada correctamente.")

            # Sincronización inicial de dispositivos
            await self.refresh_devices()
            await self.start_http2()
            return True
        except Exception as err:
            LOGGER.warning("No se pudo restaurar la sesión guardada: %s", err)
            self.is_authenticated = False
            return False

    def _register_event_listeners(self):
        """Registra los manejadores de eventos push HTTP/2 desde la API de Amazon."""
        if not self.api:
            return

        self.api.on_volume_state_event.append(self._on_volume_event)
        self.api.on_volume_state_event.freeze()

        self.api.on_media_state_event.append(self._on_media_event)
        self.api.on_media_state_event.freeze()

        self.api.on_dnd_event.append(self._on_dnd_event)
        self.api.on_dnd_event.freeze()

        self.api.on_sensor_event.append(self._on_sensor_event)
        self.api.on_sensor_event.freeze()

        self.api.on_history_event.append(self._on_history_event)
        self.api.on_history_event.freeze()

        self.api.on_todo_event.append(self._on_todo_event)
        self.api.on_todo_event.freeze()

    async def _send_to_jeedom(self, event_type: str, payload: dict[str, Any]):
        """Envía notificaciones de cambios y eventos a Jeedom vía webhook."""
        if not self.jeedom_url:
            return

        post_data = {
            "token": self.token,
            "event_type": event_type,
            "payload": payload,
        }

        try:
            async with aiohttp.ClientSession() as client:
                async with client.post(
                    self.jeedom_url,
                    json=post_data,
                    timeout=aiohttp.ClientTimeout(total=5),
                ) as resp:
                    if resp.status != 200:
                        LOGGER.warning("Jeedom respondió con estado HTTP %s", resp.status)
        except Exception as err:
            LOGGER.debug("Error enviando webhook a Jeedom (%s): %s", self.jeedom_url, err)

    async def _on_volume_event(self, volume_states):
        LOGGER.debug("Evento de volumen recibido: %s", volume_states)
        formatted = {}
        for serial, state in volume_states.items():
            formatted[serial] = {
                "volume": getattr(state, "volume", None),
                "muted": getattr(state, "muted", None),
            }
        await self._send_to_jeedom("volume_state", formatted)

    async def _on_media_event(self, media_states):
        LOGGER.debug("Evento de estado multimedia recibido: %s", media_states)
        formatted = {}
        for serial, state in media_states.items():
            formatted[serial] = {
                "state": getattr(state, "state", None),
                "title": getattr(state, "title", None),
                "artist": getattr(state, "artist", None),
            }
        await self._send_to_jeedom("media_state", formatted)

    async def _on_dnd_event(self, dnd_states):
        LOGGER.debug("Evento No Molestar (DND): %s", dnd_states)
        await self._send_to_jeedom("dnd_state", dict(dnd_states))

    async def _on_sensor_event(self):
        LOGGER.debug("Evento de sensor recibido.")
        if not self.api:
            return
        sensor_data = {}
        for serial, sensors in self.api.sensors.items():
            sensor_data[serial] = {
                k: {"name": s.name, "value": s.value, "scale": s.scale}
                for k, s in sensors.items()
            }
        await self._send_to_jeedom("sensor_state", sensor_data)

    async def _on_history_event(self, vocal_records):
        LOGGER.debug("Evento de historial de voz recibido: %s", vocal_records)
        formatted = {}
        for serial, record in vocal_records.items():
            formatted[serial] = {
                "description": getattr(record, "description", None),
                "time": getattr(record, "time", None),
            }
        await self._send_to_jeedom("history_state", formatted)

    async def _on_todo_event(self, list_event):
        LOGGER.debug("Evento de lista de tareas: %s", list_event)
        await self._send_to_jeedom("todo_event", {"list_id": getattr(list_event, "list_id", None)})

    async def start_http2(self):
        """Inicia el procesamiento de la conexión push HTTP/2 persistente con Amazon AVS."""
        if not self.api or not self.is_authenticated:
            return

        try:
            if not self.httpx_client:
                self.httpx_client = httpx.AsyncClient(http2=True, timeout=60.0)

            async def _on_reauth():
                LOGGER.warning("Amazon solicita re-autenticación en la conexión HTTP/2.")
                self.is_authenticated = False
                await self._send_to_jeedom("auth_required", {})

            await self.api.start_http2_processing(
                self.httpx_client,
                on_reauth_required=_on_reauth,
            )
            LOGGER.info("Conexión push HTTP/2 iniciada con éxito.")
        except Exception as err:
            LOGGER.error("Error al iniciar stream HTTP/2: %s", err)

    async def refresh_devices(self) -> dict[str, Any]:
        """Obtiene la lista actualizada de dispositivos desde Amazon."""
        if not self.api or not self.is_authenticated:
            return {}

        try:
            self.devices = await self.api.get_devices_data()
            try:
                await self.api.sync_media_state()
            except Exception:
                pass
            try:
                await self.api.sync_dnd_state()
            except Exception:
                pass

            devs_summary = {}
            for serial, dev in self.devices.items():
                devs_summary[serial] = {
                    "serial_number": dev.serial_number,
                    "account_name": dev.account_name,
                    "device_family": dev.device_family,
                    "device_type": dev.device_type,
                    "online": dev.online,
                    "media_player_supported": dev.media_player_supported,
                    "notifications_supported": dev.notifications_supported,
                    "voice_control_supported": dev.voice_control_supported,
                    "software_version": dev.software_version,
                }

            LOGGER.info("Sincronizados %d dispositivos Alexa.", len(self.devices))
            # Notificar a Jeedom de la lista de dispositivos
            await self._send_to_jeedom("devices_list", devs_summary)
            return devs_summary
        except CannotRetrieveData as err:
            LOGGER.error("Error al obtener datos de Amazon (posible cambio de API): %s", err)
            await self._send_to_jeedom("reinstall_dependencies_needed", {"reason": "Cambio en la API de Amazon detectado"})
            return {}
        except Exception as err:
            LOGGER.error("Error actualizando dispositivos: %s", err)
            return {}

    async def periodic_polling(self):
        """Bucle de polling de seguridad."""
        while True:
            try:
                await asyncio.sleep(self.poll_interval)
                if self.is_authenticated:
                    LOGGER.debug("Ejecutando polling de seguridad...")
                    await self.refresh_devices()
            except asyncio.CancelledError:
                break
            except Exception as err:
                LOGGER.error("Error en ciclo de polling: %s", err)

    # ================= HTTP REST API Handlers =================

    async def handle_health(self, request: web.Request) -> web.Response:
        return web.json_response({
            "status": "ok",
            "authenticated": self.is_authenticated,
            "user": self.current_user,
            "devices_count": len(self.devices),
        })

    async def handle_login(self, request: web.Request) -> web.Response:
        """Inicia sesión interactiva solicitando código OTP de 2FA."""
        try:
            data = await request.json()
        except Exception:
            return web.json_response({"error": "JSON inválido"}, status=400)

        username = data.get("username")
        password = data.get("password")
        code = data.get("code")
        site = data.get("site", "https://www.amazon.es")

        if not username or not password or not code:
            return web.json_response({"error": "Faltan campos: username, password o code"}, status=400)

        try:
            if self.session:
                await self.session.close()
            self.session = aiohttp.ClientSession()

            self.api = AmazonEchoApi(
                self.session,
                username,
                password,
                save_data=AmazonSaveDataConfig(path=self.storage_dir),
            )

            self._register_event_listeners()

            LOGGER.info("Iniciando validación de credenciales para %s...", username)
            login_result = await self.api.login.login_mode_interactive(code)

            self.is_authenticated = True
            self.current_user = username

            # Guardar datos de sesión localmente
            with open(self.session_file, "w", encoding="utf-8") as f:
                json.dump({
                    "username": username,
                    "password": password,
                    "site": site,
                    "login_data": login_result,
                }, f, indent=2)

            LOGGER.info("Autenticación exitosa para %s.", username)

            # Iniciar stream HTTP2 y descubrir dispositivos
            await self.refresh_devices()
            await self.start_http2()

            return web.json_response({
                "status": "ok",
                "message": "Autenticación realizada con éxito",
                "user": username,
            })
        except (CannotAuthenticate, CannotConnect, CannotRetrieveData) as err:
            LOGGER.error("Fallo de autenticación: %s", err)
            self.is_authenticated = False
            return web.json_response({"error": f"Fallo al autenticar: {err}"}, status=401)
        except Exception as err:
            LOGGER.exception("Error inesperado durante el login: %s", err)
            return web.json_response({"error": str(err)}, status=500)

    async def handle_devices(self, request: web.Request) -> web.Response:
        summary = {
            serial: {
                "serial_number": dev.serial_number,
                "account_name": dev.account_name,
                "device_family": dev.device_family,
                "device_type": dev.device_type,
                "online": dev.online,
                "media_player_supported": dev.media_player_supported,
                "notifications_supported": dev.notifications_supported,
                "voice_control_supported": dev.voice_control_supported,
                "software_version": dev.software_version,
            }
            for serial, dev in self.devices.items()
        }
        return web.json_response({"devices": summary})

    async def handle_sync(self, request: web.Request) -> web.Response:
        devs = await self.refresh_devices()
        return web.json_response({"status": "ok", "devices_count": len(devs)})

    async def handle_command(self, request: web.Request) -> web.Response:
        """Ejecuta una acción o comando en un dispositivo Echo específico."""
        try:
            data = await request.json()
        except Exception:
            return web.json_response({"error": "JSON inválido"}, status=400)

        serial = data.get("serial_number")
        action = data.get("action")
        params = data.get("params", {})

        if not self.is_authenticated or not self.api:
            return web.json_response({"error": "Demonio no autenticado con Amazon"}, status=403)

        device = self.devices.get(serial)
        if not device and serial != "all":
            return web.json_response({"error": f"Dispositivo {serial} no encontrado"}, status=404)

        try:
            if action == "speak":
                text = params.get("text", "")
                await self.api.call_alexa_speak(device, text)

            elif action == "announcement":
                text = params.get("text", "")
                title = params.get("title", "Jeedom")
                await self.api.call_alexa_announcement(device, text, display_text=title)

            elif action == "text_command":
                cmd = params.get("command", "")
                await self.api.call_alexa_text_command(device, cmd)

            elif action == "set_volume":
                vol = int(params.get("volume", 50))
                await self.api.set_device_volume(device, vol)

            elif action == "media_command":
                cmd = params.get("command")  # "play", "pause", "next", "previous"
                await self.api.send_media_command(device, cmd)

            elif action == "dnd":
                enable = bool(params.get("enable", True))
                await self.api.set_do_not_disturb(device, enable)

            elif action == "info_skill":
                skill = params.get("skill", "weather")
                mapping = {
                    "weather": "Alexa.Weather.Play",
                    "traffic": "Alexa.Traffic.Play",
                    "flash_briefing": "Alexa.FlashBriefing.Play",
                    "time": "Alexa.Time.Play",
                    "date": "Alexa.Date.Play",
                    "joke": "Alexa.Joke.Play",
                    "fun_fact": "Alexa.FunFact.Play",
                    "sing_song": "Alexa.SingASong.Play",
                    "good_morning": "Alexa.GoodMorning.Play",
                    "goodnight": "Alexa.GoodNight.Play",
                    "national_news": "Alexa.News.NationalNews",
                }
                alexa_skill = mapping.get(skill, skill)
                await self.api.call_alexa_info_skill(device, alexa_skill)

            elif action == "restart":
                await self.api.restart_device(device)

            else:
                return web.json_response({"error": f"Acción desconocida: {action}"}, status=400)

            return web.json_response({"status": "ok", "action": action, "serial": serial})

        except Exception as err:
            LOGGER.error("Error ejecutando comando %s en %s: %s", action, serial, err)
            return web.json_response({"error": str(err)}, status=500)

    async def start(self):
        """Inicia el servidor HTTP del demonio y los servicios en segundo plano."""
        self.app = web.Application()
        self.app.router.add_get("/api/health", self.handle_health)
        self.app.router.add_post("/api/login", self.handle_login)
        self.app.router.add_get("/api/devices", self.handle_devices)
        self.app.router.add_post("/api/sync", self.handle_sync)
        self.app.router.add_post("/api/command", self.handle_command)

        self.runner = web.AppRunner(self.app)
        await self.runner.setup()
        site = web.TCPSite(self.runner, "127.0.0.1", self.port)
        await site.start()
        LOGGER.info("Demonio Alexa Devices escuchando en http://127.0.0.1:%d", self.port)

        # Intento de reconexión con sesión existente
        await self.init_session_from_disk()

        # Iniciar tarea periódica de refresco
        self.poll_task = asyncio.create_task(self.periodic_polling())

    async def stop(self):
        """Detiene de forma limpia el demonio y cierra conexiones."""
        LOGGER.info("Deteniendo demonio Alexa Devices...")
        if self.poll_task:
            self.poll_task.cancel()
        if self.api:
            try:
                await self.api.stop_http2_processing()
            except Exception:
                pass
        if self.httpx_client:
            await self.httpx_client.aclose()
        if self.session:
            await self.session.close()
        if self.runner:
            await self.runner.cleanup()
        LOGGER.info("Demonio detenido.")


async def main():
    parser = argparse.ArgumentParser(description="Demonio Alexa Devices para Jeedom")
    parser.add_argument("--port", type=int, default=55065, help="Puerto HTTP local")
    parser.add_argument("--jeedom-url", type=str, default="", help="URL de webhook Jeedom")
    parser.add_argument("--token", type=str, default="", help="Token de autenticación interno")
    parser.add_argument("--storage-dir", type=str, default="./data/storage", help="Ruta de persistencia")
    parser.add_argument("--poll-interval", type=int, default=300, help="Intervalo de sondeo en segundos")
    parser.add_argument("--loglevel", type=str, default="info", choices=["debug", "info", "warning", "error"])

    args = parser.parse_args()

    numeric_level = getattr(logging, args.loglevel.upper(), logging.INFO)
    LOGGER.setLevel(numeric_level)

    daemon = AlexaDaemon(
        port=args.port,
        jeedom_url=args.jeedom_url,
        token=args.token,
        storage_dir=args.storage_dir,
        poll_interval=args.poll_interval,
    )

    loop = asyncio.get_running_loop()
    stop_event = asyncio.Event()

    def _sig_handler():
        LOGGER.info("Señal de terminación recibida.")
        stop_event.set()

    for sig in (signal.SIGTERM, signal.SIGINT):
        try:
            loop.add_signal_handler(sig, _sig_handler)
        except NotImplementedError:
            # En Windows los signal handlers pueden no ser soportados en asyncio loop
            pass

    await daemon.start()

    try:
        await stop_event.wait()
    except asyncio.CancelledError:
        pass
    finally:
        await daemon.stop()


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        pass
