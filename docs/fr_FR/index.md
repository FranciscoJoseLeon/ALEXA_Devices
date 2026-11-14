# Plugin Alexa Devices pour Jeedom

Plugin d'intégration moderne pour les appareils Amazon Echo et compatibles Alexa dans Jeedom v4 / v4.4+, basé sur la bibliothèque asynchrone `aioamazondevices` et le protocole officiel de push HTTP/2 d'Amazon.

---

## Fonctionnalités principales
- **Découverte automatique** : Détecte automatiquement tous les appareils Amazon Echo (Dot, Show, Pop, Studio, Fire TV, etc.) associés à votre compte Amazon.
- **Push en temps réel (HTTP/2)** : Connexion persistante par flux HTTP/2 avec Amazon AVS. Les changements de volume, de capteurs, d'état multimédia et de commandes vocales sont répercutés instantanément sans requêtes de scrutation intensives.
- **Synthèse vocale (TTS)** : Commande pour faire parler n'importe quel Echo (`speak`).
- **Annonces** : Diffuse des annonces sonores avec carillon d'attention préalable (`announcement`).
- **Commandes vocales textuelles** : Permet de simuler des ordres vocaux par écrit (`text_command`), par exemple : *"allume la lumière du salon"*, *"mets du jazz"*.
- **Contrôle multimédia complet** : Lecture, Pause, Suivant, Précédent et état actuel (*PLAYING*, *PAUSED*, *IDLE*).
- **Contrôle de volume bidirectionnel** : Curseur de 0 à 100 avec synchronisation en direct.
- **Mode Ne pas déranger (DND)** : Activation et désactivation du mode DND.
- **Historique vocal** : Affiche la dernière commande vocale entendue par le microphone de l'Echo.
- **Authentification 2FA / OTP sécurisée** : Assistant interactif prenant en charge la vérification en deux étapes (OTP par application ou SMS).

---

## Installation et configuration

1. **Activer le plugin** : Rendez-vous dans **Plugins > Gestion des plugins > Alexa Devices** et cliquez sur **Activer**.
2. **Installer les dépendances** : Cliquez sur **Installer** dans la section Dépendances pour initialiser l'environnement virtuel Python 3 et les bibliothèques.
3. **Se connecter à Amazon** : Dans la configuration du plugin, choisissez votre domaine Amazon, cliquez sur **Connexion / 2FA OTP**, puis renseignez vos identifiants Amazon et le code OTP à 6 chiffres.
4. **Découvrir les appareils** : Allez dans **Plugins > Multimédia > Alexa Devices** et cliquez sur **Synchroniser les appareils**.

---

## Avertissement et limitation de responsabilité

- **Non-affiliation** : Ce plugin est un projet communautaire indépendant et n'est **en aucun cas** affilié, approuvé ou certifié par Amazon.com, Inc. ou Amazon Technologies, Inc. Toutes les marques appartiennent à leurs propriétaires respectifs.
- **Exclusion de garantie** : Ce logiciel est fourni **"en l'état" (AS IS)**, sans garantie d'aucune sorte, expresse ou implicite. Les protocoles et API tiers dans le cloud peuvent être modifiés sans préavis par Amazon.
- **Limitation de responsabilité** : Les auteurs et contributeurs ne peuvent en aucun cas être tenus responsables des dommages directs ou indirects, pertes de données, interruptions de service ou dysfonctionnements domotiques résultant de l'utilisation de ce logiciel. L'utilisation se fait sous votre seule responsabilité.
