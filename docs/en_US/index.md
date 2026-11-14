# Alexa Devices Plugin for Jeedom

Modern integration plugin for Amazon Echo and Alexa-enabled devices in Jeedom v4 / v4.4+, based on the asynchronous `aioamazondevices` library and Amazon's official HTTP/2 push protocol.

---

## Features
- **Automatic Discovery**: Automatically discovers all Amazon Echo devices (Dot, Show, Pop, Studio, Fire TV, etc.) linked to your Amazon account.
- **Real-Time Push (HTTP/2)**: Persistent stream connection with Amazon AVS. Volume changes, sensors, media state, and voice commands update instantly without polling delays.
- **Text-to-Speech (TTS)**: Send text to make any Echo speaker speak (`speak`).
- **Announcements**: Broadcast attention chimes and spoken messages across devices (`announcement`).
- **Text Voice Commands**: Execute voice commands via text (`text_command`), e.g., *"turn on the living room light"*, *"play jazz on Spotify"*.
- **Full Media Player Control**: Play, Pause, Next, Previous, and state reporting (*PLAYING*, *PAUSED*, *IDLE*).
- **Bidirectional Volume Control**: 0-100 slider with live feedback.
- **Do Not Disturb (DND)**: Enable or disable DND mode.
- **Voice History**: Displays the last voice command captured by each Echo microphone.
- **Interactive 2FA / OTP Authentication**: Step-by-step connection wizard supporting Two-Factor Authentication (OTP).

---

## Installation & Configuration

1. **Activate the plugin**: Navigate to **Plugins > Plugin management > Alexa Devices** and click **Enable**.
2. **Install Dependencies**: Click on **Install** in the Dependencies section to set up the Python 3 virtual environment and required libraries.
3. **Connect to Amazon**: In the plugin configuration, select your Amazon domain, click **Sign In / 2FA OTP**, enter your Amazon credentials and the 6-digit OTP verification code.
4. **Discover Devices**: Go to **Plugins > Multimedia > Alexa Devices** and click **Synchronize Devices**.

---

## Disclaimer & Limitation of Liability

- **Non-Affiliation**: This software is an independent community project and is **not** endorsed, certified, or affiliated with Amazon.com, Inc. or Amazon Technologies, Inc. All trademarks belong to their respective owners.
- **Warranty Disclaimer**: This plugin is provided **"as is"**, without warranty of any kind, express or implied. Third-party cloud APIs are subject to change without notice.
- **Limitation of Liability**: Under no circumstances shall the authors or contributors be held liable for any direct or indirect damages, data loss, home automation failures, or service interruptions resulting from the use or inability to use this plugin. Use at your own risk.
