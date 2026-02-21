# 📘 EDU PAY PRO — Backend Laravel

Plateforme SaaS multi-tenant de gestion des frais scolaires.
**Stack :** Laravel 11 · PHP 8.2+ · MySQL 8 · Redis · Sanctum · Queues

---

## ⚡ Installation rapide sur Laragon / XAMPP (Windows)

### Prérequis
- PHP 8.2+ (activé dans Laragon)
- MySQL 8.0+ (via Laragon)
- Composer installé globalement
- Redis (optionnel pour les queues — peut utiliser `database` en dev)

### 1. Créer le projet Laravel

```bash
composer create-project laravel/laravel edupaypro
cd edupaypro
```

### 2. Copier tous les fichiers de ce dossier dans votre projet

Copiez le contenu de chaque sous-dossier dans le projet Laravel créé.

### 3. Installer les dépendances

```bash
composer require laravel/sanctum
composer require spatie/laravel-permission
composer require barryvdh/laravel-dompdf
composer require maatwebsite/excel
composer require guzzlehttp/guzzle
composer require symfony/uid
```

### 4. Configurer le fichier .env

```env
APP_NAME="EDU PAY PRO"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://edupaypro.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=edupaypro
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database

CACHE_DRIVER=file
SESSION_DRIVER=file

# WhatsApp Meta Cloud API
WHATSAPP_TOKEN=your_token_here
WHATSAPP_PHONE_ID=your_phone_id
WHATSAPP_VERIFY_TOKEN=your_verify_token

# MTN MoMo
MTN_MOMO_BASE_URL=https://sandbox.momodeveloper.mtn.com
MTN_MOMO_SUBSCRIPTION_KEY=your_key
MTN_MOMO_API_USER=your_api_user
MTN_MOMO_API_KEY=your_api_key
MTN_MOMO_TARGET_ENV=sandbox

# Moov Money
MOOV_MERCHANT_ID=your_merchant_id
MOOV_SECRET=your_secret
MOOV_BASE_URL=https://api.moov.africa
```

### 5. Créer la base de données

Dans phpMyAdmin (Laragon) ou en ligne de commande :
```sql
CREATE DATABASE edupaypro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 6. Lancer les migrations et seeders

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### 7. Publier les configs

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate   # re-run pour les tables de permissions
```

### 8. Démarrer le serveur de dev

```bash
php artisan serve
# ou sur Laragon : http://edupaypro.test (virtual host auto)
```

### 9. Démarrer le worker de queues (relances auto)

```bash
php artisan queue:work --queue=reminders,receipts,default
```

---

## 📁 Architecture des fichiers

```
app/
├── Models/              ← Eloquent : Tenant, School, Student, Payment...
├── Http/
│   ├── Controllers/Api/ ← Controllers REST JSON
│   ├── Middleware/      ← TenantMiddleware, RoleMiddleware
│   ├── Requests/        ← FormRequests (validation)
│   └── Resources/       ← JSON Resources (transformateurs)
├── Services/            ← WhatsAppService, MTNMoMoService, RiskScoreService
└── Traits/              ← BelongsToTenant, HasAuditLog

database/
├── migrations/          ← Toutes les tables (ordre important)
├── seeders/             ← Données de démo
└── factories/           ← Factories pour les tests

routes/
├── api.php              ← Routes API v1

config/
├── whatsapp.php
└── mobilemoney.php
```

---

## 🔐 Rôles utilisateurs

| Rôle | Accès |
|------|-------|
| `super_admin` | Toutes les écoles, admin système |
| `director` | Toute son école |
| `accountant` | Élèves, paiements, reçus |
| `teacher` | Lecture seule : élèves de sa classe |

---

## 📡 Routes API disponibles (après Module A)

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | `/api/v1/auth/register` | Créer un compte école |
| POST | `/api/v1/auth/login` | Connexion |
| POST | `/api/v1/auth/logout` | Déconnexion |
| GET | `/api/v1/auth/me` | Profil utilisateur connecté |
| GET | `/api/v1/schools/current` | Infos de l'école courante |
| PUT | `/api/v1/schools/current` | Modifier l'école |
| GET | `/api/v1/academic-years` | Liste des années scolaires |
| POST | `/api/v1/academic-years` | Créer une année scolaire |
| POST | `/api/v1/academic-years/{id}/activate` | Activer une année |
| GET | `/api/v1/users` | Liste des utilisateurs |
| POST | `/api/v1/users` | Créer un utilisateur |
| PUT | `/api/v1/users/{id}` | Modifier un utilisateur |
| DELETE | `/api/v1/users/{id}` | Désactiver un utilisateur |

---

## 🧪 Tests

```bash
php artisan test
php artisan test --filter=AuthTest
```

---

## 📌 Modules à venir

- [x] **SETUP** — Structure, migrations, modèles, auth
- [ ] **Module A** — Auth & gestion école
- [ ] **Module B** — Gestion élèves & parents
- [ ] **Module C** — Configuration des frais
- [ ] **Module D** — Paiements multi-modes
- [ ] **Module E** — Reçus PDF + QR code
- [ ] **Module F** — Relances automatiques
- [ ] **Module G** — Dashboard & rapports
- [ ] **Module H** — IA & score de risque
- [ ] **Module I** — Financement scolaire
