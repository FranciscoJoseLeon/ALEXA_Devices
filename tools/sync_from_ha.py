#!/usr/bin/env python3
"""
Herramienta de sincronización y detección de cambios en Home Assistant upstream.
Diseñada para alertar al desarrollador sobre cambios en alexa_devices de Home Assistant.
"""

import json
import os
import re
import sys
import urllib.request
from pathlib import Path

HA_API_COMMITS_URL = "https://api.github.com/repos/home-assistant/core/commits?path=homeassistant/components/alexa_devices&per_page=5"
HA_RAW_BASE = "https://raw.githubusercontent.com/home-assistant/core/dev/homeassistant/components/alexa_devices"
MANIFEST_URL = f"{HA_RAW_BASE}/manifest.json"
CONST_URL = f"{HA_RAW_BASE}/const.py"
SERVICES_URL = f"{HA_RAW_BASE}/services.yaml"

ROOT_DIR = Path(__file__).resolve().parent.parent
REQ_FILE = ROOT_DIR / "resources" / "requirements.txt"
STATE_FILE = ROOT_DIR / "tools" / ".upstream_state.json"


def fetch_url(url: str) -> str:
    headers = {
        "User-Agent": "AlexaDevicesJeedomDev/1.0",
        "Accept": "application/vnd.github.v3+json",
    }
    github_token = os.environ.get("GITHUB_TOKEN")
    if github_token:
        headers["Authorization"] = f"token {github_token}"

    req = urllib.request.Request(url, headers=headers)
    with urllib.request.urlopen(req, timeout=15) as resp:
        return resp.read().decode("utf-8")


def get_current_pinned_version() -> str:
    if not REQ_FILE.exists():
        return ""
    content = REQ_FILE.read_text(encoding="utf-8")
    for line in content.splitlines():
        if line.startswith("aioamazondevices"):
            match = re.search(r"[>=~]=?([0-9.]+)", line)
            if match:
                return match.group(1)
    return ""


def load_state() -> dict:
    if STATE_FILE.exists():
        try:
            return json.loads(STATE_FILE.read_text(encoding="utf-8"))
        except Exception:
            return {}
    return {}


def save_state(state: dict):
    STATE_FILE.parent.mkdir(parents=True, exist_ok=True)
    STATE_FILE.write_text(json.dumps(state, indent=2), encoding="utf-8")


def main():
    print("=" * 65)
    print("🔍 Comprobando novedades en Home Assistant para el desarrollador...")
    print("=" * 65)

    changes_detected = False
    report_lines = []

    # 1. Comprobar manifest.json y versión de aioamazondevices
    try:
        manifest_raw = fetch_url(MANIFEST_URL)
        manifest = json.loads(manifest_raw)
    except Exception as e:
        print(f"❌ Error al consultar manifest.json: {e}", file=sys.stderr)
        return

    ha_requirements = manifest.get("requirements", [])
    ha_version = None
    for req in ha_requirements:
        if req.startswith("aioamazondevices"):
            match = re.search(r"==([0-9.]+)", req)
            if match:
                ha_version = match.group(1)

    current_version = get_current_pinned_version()

    print(f"📦 aioamazondevices en Home Assistant: {ha_version}")
    print(f"📦 aioamazondevices en nuestro plugin:  {current_version}")

    version_changed = False
    if ha_version and ha_version != current_version:
        version_changed = True
        changes_detected = True
        msg = f"⚡ **Nueva versión de librería:** `{current_version}` ➔ `{ha_version}`"
        print(msg)
        report_lines.append(msg)

        # Actualizar requirements.txt
        lines = REQ_FILE.read_text(encoding="utf-8").splitlines()
        new_lines = []
        for l in lines:
            if l.startswith("aioamazondevices"):
                new_lines.append(f"aioamazondevices>={ha_version}")
            else:
                new_lines.append(l)
        REQ_FILE.write_text("\n".join(new_lines) + "\n", encoding="utf-8")

    # 2. Comprobar últimos commits en HA para este componente
    state = load_state()
    last_known_sha = state.get("last_commit_sha")

    new_commits = []
    latest_sha = None
    try:
        commits_data = json.loads(fetch_url(HA_API_COMMITS_URL))
        if isinstance(commits_data, list) and len(commits_data) > 0:
            latest_sha = commits_data[0].get("sha")
            for c in commits_data:
                sha = c.get("sha")
                if sha == last_known_sha:
                    break
                commit_msg = c.get("commit", {}).get("message", "").splitlines()[0]
                commit_author = c.get("commit", {}).get("author", {}).get("name", "Unknown")
                commit_url = c.get("html_url", "")
                new_commits.append(f"- [{sha[:7]}]({commit_url}) - **{commit_msg}** ({commit_author})")
    except Exception as e:
        print(f"⚠️ No se pudo consultar la lista de commits de GitHub: {e}")

    if new_commits and last_known_sha is not None:
        changes_detected = True
        report_lines.append("\n📝 **Nuevos cambios detectados en Home Assistant:**")
        report_lines.extend(new_commits)
        print(f"Se detectaron {len(new_commits)} nuevos commits en HA.")

    # Guardar estado actual
    if latest_sha:
        state["last_commit_sha"] = latest_sha
        state["last_aioamazondevices_version"] = ha_version
        save_state(state)

    # Exportar outputs para GitHub Actions
    gh_output = os.environ.get("GITHUB_OUTPUT")
    if gh_output:
        with open(gh_output, "a", encoding="utf-8") as f:
            f.write(f"changes_detected={'true' if changes_detected else 'false'}\n")
            f.write(f"ha_version={ha_version}\n")
            f.write(f"version_changed={'true' if version_changed else 'false'}\n")

    summary_file = os.environ.get("GITHUB_STEP_SUMMARY")
    report_content = "\n".join(report_lines)
    if summary_file and report_lines:
        with open(summary_file, "a", encoding="utf-8") as f:
            f.write(f"## 🔔 Novedades de alexa_devices en Home Assistant\n\n{report_content}\n")

    report_path = ROOT_DIR / "tools" / "last_report.md"
    if report_lines:
        report_path.write_text(report_content, encoding="utf-8")
    elif report_path.exists():
        report_path.unlink()

    print("=" * 65)
    if changes_detected:
        print("🔔 Se han registrado cambios que requieren tu revisión de desarrollador.")
    else:
        print("✅ Sin cambios pendientes en Home Assistant.")
    print("=" * 65)


if __name__ == "__main__":
    main()

