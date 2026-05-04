# Análisis Profundo del Proyecto BiwengerProManagerAPI

Este documento proporciona un análisis exhaustivo del proyecto **BiwengerProManagerAPI**, un wrapper API REST en PHP para funcionalidades de Biwenger, con persistencia en MongoDB.

---

## 1. Estructura del Proyecto

El proyecto sigue una arquitectura **MVC + Servicios + Repositorios**. Esta separación de responsabilidades asegura un código mantenible y escalable.

### Directorios Principales
- `config/`: Archivos de configuración (entornos `dev/`, `local/`, `prod/` mencionados en docs, además de `.env.example`).
- `public/`: El punto de entrada web (`index.php`), documentación (`docs/`), y archivos estáticos.
- `scripts/`: Scripts de utilidad (`run.sh`, `setup-unix.sh`, `test-logger.php`).
- `src/`: Código fuente de la aplicación, siguiendo PSR-4 (espacio de nombres `BiwengerProManagerAPI`).
  - `Controllers/`: Manejan las peticiones HTTP y delegan la lógica de negocio a los servicios.
  - `Services/`: Contienen la lógica de negocio e integraciones externas.
  - `Models/`: Modelos de datos de la aplicación.
  - `Database/`: Conexión a MongoDB y clases de Repositorio para persistencia de datos.
  - `Utils/`: Utilidades comunes (Logger, ApiAuth).
- `tests/`: Pruebas automatizadas (principalmente tests de integración de controladores).

### Arquitectura
- **MVC (Model-View-Controller)**: Las peticiones entran por `public/index.php`, se enrutan al `Controller` correspondiente, que interactúa con `Models` y responde con JSON (usando `Response.php`).
- **Capa de Servicios**: Los controladores no ejecutan lógica compleja; usan clases en `Services/` (ej. `AccountService`) o el `BiwengerClient` directamente.
- **Capa de Repositorios**: Los servicios utilizan repositorios en `Database/` para aislar la lógica de acceso a la base de datos (MongoDB) de la lógica de negocio.

---

## 2. Dependencias y Configuración

### Dependencias (`composer.json`)
- **Requisitos de Sistema**: PHP `>= 7.4`.
- **Librerías Core**: `mongodb/mongodb: ^1.20` para interactuar con la base de datos MongoDB.
- **Librerías de Desarrollo**: `phpunit/phpunit: ^9.0` para pruebas automatizadas.

### Configuración
- El proyecto utiliza un archivo `.env` para la configuración, con una plantilla en `.env.example`.
- **Variables Clave**:
  - `APP_ENV`, `APP_PORT`
  - `DB_DRIVER` (mongodb), `MONGODB_URI`, `MONGODB_DB`
  - `API_KEY` (Para proteger endpoints V1)
  - `CONFIG_SECRET`
  - `BOT_EMAIL`, `BOT_PASSWORD` (Credenciales para el bot automatizado en Biwenger)
  - `LOG_PATH`

---

## 3. Base de Datos

La API utiliza un enfoque de persistencia híbrida, combinando datos en tiempo real de Biwenger con caché/almacenamiento local en MongoDB.

### Conexión
- Gestionada por `MongoConnection.php`, utilizando el patrón Singleton. Se conecta mediante las variables de entorno `MONGODB_URI` y `MONGODB_DB`.

### Repositorios y Colecciones Mapeadas
- `AccountsRepository`: Para la colección de cuentas (`Account.php`).
- `UsersRepository`: Para la colección de usuarios (`User.php`).
- `LeaguesRepository`: Para la colección de ligas (`League.php`).
- `SettingsRepository`: Para configuraciones (`Setting.php`).
- `ClausulazosRepository`: Para el histórico o reglas de transferencias/cláusulas.

### Modelos (`src/Models/`)
Incluyen representaciones simples de los datos: `Account`, `League`, `Player`, `Round`, `Setting`, `User`.

---

## 4. Sistema de Autenticación

El sistema tiene una autenticación dual para separar los endpoints públicos (V0) de los premium (V1).

### Bearer Token (Autenticación Biwenger)
- Usado para identificar al usuario logueado.
- Obtenido en el endpoint `/api/v0/auth/login`, que utiliza `BiwengerClient` para obtener un token real de la API de Biwenger.
- El token es validado y extraído usando la clase `Utils\ApiAuth` (`extractBearerToken`, `requireBearerToken`).
- Los endpoints públicos utilizan este token para acceder a los datos del usuario en Biwenger.

### API Key (Autorización Premium)
- Usado para autorizar el acceso a funcionalidades que modifican datos (V1).
- Extraído mediante `ApiAuth::getApiKeyFromRequest()` y validado contra la variable de entorno `API_KEY`.
- Se requiere en endpoints como transferencias, pagos de cláusulas o modificación de configuraciones de liga.

---

## 5. Servicios Existentes

Ubicados en `src/Services/`, centralizan la lógica:
- **`BiwengerClient`**: Es el núcleo de la integración. Realiza peticiones cURL a `https://biwenger.as.com/api/` y `https://cf.biwenger.com/api/`. Métodos clave: `getToken`, `getPlayers`, `getLeagues`, `getAccount`, `getUsersOfLeague`, `getPlayersOfUser`, `transferPlayer`, `clausePlayer`. Maneja tanto llamadas públicas como llamadas autenticadas (requieren headers como `x-league`, `x-user`).
- **`AccountService`**: Gestiona datos de cuenta (guardar en DB con `AccountsRepository`).
- **`LeagueService` / `LeagueSettingsService`**: Gestiona información y configuración de las ligas.
- **`PlayerService` / `UsersService`**: Lógica sobre jugadores y usuarios de una liga.
- **`RoundsService` / `TransfersService`**: Lógica sobre jornadas y transferencias/mercado.

---

## 6. Controladores y Endpoints

El enrutamiento es simple y se define en `public/index.php`.

### V0 (Públicos - Requieren Bearer Token)
- **Auth**:
  - `POST /api/v0/auth/login` (Obtener token Biwenger)
  - `POST /api/v0/auth/token` (Setear token directamente)
  - `GET /api/v0/account` (Obtener info de cuenta actual)
- **Leagues**:
  - `GET /api/v0/leagues`
  - `GET /api/v0/leagues/{id}`
- **Players**:
  - `GET /api/v0/players`
  - `GET /api/v0/players/{id}`
- **Rounds**:
  - `GET /api/v0/rounds`
  - `GET /api/v0/rounds/results`
- **Users**:
  - `GET /api/v0/users`
  - `GET /api/v0/users/{id}`
  - `GET /api/v0/users/{id}/players`
  - `POST /api/v0/users/sync`

### V1 (Premium - Requieren API Key + Bearer Token)
- **Transfers / Clauses**:
  - `POST /api/v1/transfers`
  - `POST /api/v1/clauses`
- **League Settings**:
  - `GET /api/v1/leagues/{id}/settings`
  - `POST/PUT /api/v1/leagues/{id}/settings`

### Controladores (`src/Controllers/`)
- `AuthController`, `LeagueController`, `PlayerController`, `RoundsController`, `TransfersController`, `UsersController`.

---

## 7. Funcionalidades

### Expuestas y Operativas
- **Lectura**: Obtención de ligas, cuentas, usuarios por liga, plantillas (jugadores de usuario), jugadores generales de competición y resultados de jornadas.
- **Escritura / Premium**: Sistema de transferencias entre usuarios y pago de cláusulas (`clausePlayer`), actualizando en tiempo real en Biwenger (usando las credenciales BOT en algunos casos como `transferPlayer`).
- **Sincronización**: Posibilidad de sincronizar datos locales de usuarios con Biwenger.

### Áreas de Mejora o Posiblemente Incompletas
- Falta un enrutador (Router) robusto; la lógica actual en `index.php` usando expresiones regulares podría ser difícil de mantener a medida que la API crezca.
- En el `AuthController`, si la DB no está presente/falla, hay mecanismos de fallback ("returning raw account data"), pero en otros endpoints la dependencia con Mongo podría ser más estricta.

---

## 8. Tests

### Estructura
- El proyecto utiliza **PHPUnit**.
- Archivo de configuración `phpunit.xml`.
- Pruebas ubicadas en el directorio `tests/`, con un subdirectorio `Controllers/` que contiene `V0EndpointsTest.php` y `V1EndpointsTest.php`.

### Cobertura (Coverage Gaps)
- Los tests actuales parecen estar muy enfocados en integraciones de end-to-end de los controladores y respuestas HTTP (Endpoints V0 y V1).
- **Gaps Identificados**:
  - No se observan tests unitarios profundos para los **Servicios** (ej. mockear `BiwengerClient` para probar lógicas complejas de fallo sin llamar a la API real).
  - Faltan pruebas unitarias para los **Repositorios** (interacción con MongoDB).
  - Los **Modelos** y clases de **Utils** (`ApiAuth`, `Logger`) no parecen tener tests dedicados.