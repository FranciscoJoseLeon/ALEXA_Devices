# Présentation Jeedom Community — Alexa Devices

- **Catégorie :** `Développeur(s) > Présentation plugin` (https://community.jeedom.com/c/developpeur-developpeurs/presentation-plugin/20)
- **Titre du sujet :** `Alexa Devices (alexa_devices)`
- **Tags recommandés :** `gratuit`, `beta` (ou `stable`), `demon`, `dependance_install`, `python`, `cron`

---

## Texte à copier-coller sur le forum

```markdown
Bonjour à tous,

À défaut de disposer d'un plugin gratuit pour Alexa, et en m'appuyant sur les informations publiques disponibles pour d'autres contrôleurs domotiques, j'ai consacré un peu de temps à développer cette solution afin qu'elle reste **100 % gratuite**. Comme le résultat me semble plutôt réussi, j'ai décidé de le partager avec la communauté : cet écosystème, nous le construisons tous ensemble, et quand certains ne peuvent plus assurer la relève, d'autres prennent le relais. Sans cette entraide, d'autres solutions domotiques dotées d'une communauté plus large finiront par s'imposer.

Dans cette même optique d'ouverture, **le plugin ainsi que toute sa documentation sont disponibles en français, en espagnol et en anglais**, afin de permettre à davantage de personnes qui n'ont pas la chance de parler français de rejoindre et d'enrichir la communauté Jeedom.

---

### Fonctionnalités principales
- **Découverte automatique** des enceintes et groupes Echo (Echo Dot, Show, Pop, Studio, Fire TV, groupes multiroom) avec affichage du modèle correspondant.
- **Push HTTP/2 en temps réel** via le protocole Amazon AVS (sans polling agressif), mettant à jour instantanément le volume, l'état multimédia, les capteurs et la dernière commande vocale.
- **Widget Dashboard dédié** : Lecture, Pause, Stop, Précédent, Suivant, volume bidirectionnel, mode Ne pas déranger (DND), listes déroulantes de sons/musiques, TTS (`speak`), Annonces (`announcement`) et commandes vocales par texte (`text_command`). Un clic sur l'en-tête ouvre directement la configuration de l'équipement.
- **Authentification 2FA / OTP** intégrée via une modale interactive.
- **Capteurs intégrés** (température et détection de présence sur les appareils compatibles).

### Fiche technique
- **ID du plugin :** `alexa_devices`
- **Langages :** PHP, JavaScript, Python 3
- **Démon :** Oui (démon Python basé sur `aioamazondevices`, `aiohttp` et `httpx[http2]`)
- **Dépendances :** Oui (environnement virtuel Python installé automatiquement)
- **Cron :** Oui (`cron` / `cron5` / `cronHourly` selon besoin)
- **Panel dédié :** Non (interface de gestion dans le plugin + widget Dashboard personnalisé)
- **Statut / Licence :** Gratuit — Licence GPL
- **Version Jeedom requise :** `>= 4.2`

### Liens
- **Dépôt GitHub :** https://github.com/FranciscoJoseLeon/plugin-alexa_devices
- **Documentation (FR) :** https://github.com/FranciscoJoseLeon/plugin-alexa_devices/blob/main/docs/fr_FR/index.md
- **Changelog (FR) :** https://github.com/FranciscoJoseLeon/plugin-alexa_devices/blob/main/docs/fr_FR/changelog.md

Vos retours et suggestions sont les bienvenus !
```
