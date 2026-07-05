# 🐾 PetsWorld API

API REST para el sistema de mascotas perdidas de PetsWorld. Construida con **Laravel 11** y **Sanctum** para autenticación, conectada a **PostgreSQL** en Supabase.

---

## 🏗️ Stack tecnológico

| Componente | Tecnología |
|---|---|
| Framework | Laravel 11 |
| Autenticación | Laravel Sanctum (tokens) |
| Base de datos | PostgreSQL (Supabase) |
| Almacenamiento | Supabase Storage |
| Entorno de desarrollo | Fedora Linux |

---

## 📋 Requisitos previos

- PHP 8.2+
- Composer 2+
- PostgreSQL (o cuenta en Supabase)

---

## ⚙️ Instalación

```bash
# 1. Clonar el repositorio
git clone https://github.com/tu-usuario/petsworld-api.git
cd petsworld-api

# 2. Instalar dependencias
composer install

# 3. Copiar el archivo de entorno
cp .env.example .env

# 4. Generar la clave de aplicación
php artisan key:generate
```

---

## 🔧 Configuración del `.env`

Edita el archivo `.env` con tus credenciales:

```dotenv
APP_NAME=PetsWorld
APP_ENV=local
APP_DEBUG=true

# Base de datos (Supabase)
DB_CONNECTION=pgsql
DB_HOST=db.<tu-proyecto>.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=<tu-password>

# Supabase Storage
SUPABASE_URL=https://<tu-proyecto>.supabase.co
SUPABASE_SERVICE_KEY=<tu-service-role-key>
SUPABASE_BUCKET=lost-pets

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:8081
SESSION_DRIVER=file
CACHE_STORE=file
```

---

## 🗄️ Base de datos

El esquema ya debe estar creado en Supabase. Las migraciones de Laravel solo sincronizan el historial:

```bash
php artisan migrate
```

### Tablas del sistema

| Tabla | Descripción |
|---|---|
| `users` | Usuarios del ecosistema PetsWorld |
| `pets` | Catálogo de mascotas en adopción (web) |
| `pet_images` | Fotos múltiples por mascota |
| `lost_pet_reports` | Reportes de mascotas perdidas (app móvil) |
| `lost_pet_report_photos` | Fotos adicionales por reporte |
| `lost_pet_report_updates` | Historial de cambios de estado |
| `pet_alerts` | Alertas locales para usuarios en el área |
| `geo_zones` | Zonas geográficas jerárquicas (escalabilidad) |
| `personal_access_tokens` | Tokens de Sanctum |

---

## 🚀 Levantar el servidor

```bash
# Desarrollo local (accesible desde celular en la misma red)
php artisan serve --host=0.0.0.0 --port=8000
```

---

## 📡 Endpoints de la API

### Base URL
```
http://<tu-ip>:8000/api
```

### 🔐 Autenticación

| Método | Endpoint | Descripción | Auth |
|---|---|---|---|
| `POST` | `/auth/register` | Registrar nuevo usuario | ❌ |
| `POST` | `/auth/login` | Iniciar sesión | ❌ |
| `GET` | `/auth/me` | Obtener usuario autenticado | ✅ |
| `POST` | `/auth/logout` | Cerrar sesión | ✅ |

### 🐾 Reportes de mascotas perdidas

| Método | Endpoint | Descripción | Auth |
|---|---|---|---|
| `GET` | `/reports` | Listar reportes (paginado, con filtros) | ❌ |
| `POST` | `/reports` | Crear nuevo reporte | ✅ |
| `GET` | `/reports/{id}` | Ver detalle de reporte | ❌ |
| `PUT` | `/reports/{id}` | Actualizar reporte (solo dueño) | ✅ |
| `DELETE` | `/reports/{id}` | Cancelar reporte — soft delete (solo dueño) | ✅ |

### Filtros disponibles en `GET /reports`

| Parámetro | Tipo | Descripción |
|---|---|---|
| `status` | string | `active`, `found`, `cancelled`, `expired` |
| `species` | string | `dog`, `cat`, `bird`, `rabbit`, `other` |
| `lat` | decimal | Latitud para búsqueda por proximidad |
| `lng` | decimal | Longitud para búsqueda por proximidad |
| `radius` | integer | Radio en km (Haversine) |
| `page` | integer | Número de página (15 por página) |

### Ejemplo de request

```bash
# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "alex@petsworld.com", "password": "password123"}'

# Crear reporte (con token)
curl -X POST http://localhost:8000/api/reports \
  -H "Authorization: Bearer TU_TOKEN" \
  -F "species=dog" \
  -F "description=Perro labrador negro perdido" \
  -F "latitude=-0.1807" \
  -F "longitude=-78.4678" \
  -F "radius_km=5"
```

---

## 📖 Documentación Postman

La colección completa de Postman está en:

```
docs/PetsWorld_API.postman_collection.json
```

### Cómo importarla

1. Abre **Postman**
2. Clic en **Import** (arriba a la izquierda)
3. Selecciona el archivo `docs/PetsWorld_API.postman_collection.json`
4. La colección aparece con todos los endpoints organizados en carpetas

### Variables de colección

| Variable | Descripción |
|---|---|
| `base_url` | URL base de la API — cambiar por tu IP |
| `token` | Token Sanctum — se llena automáticamente al hacer login |

> **Tip:** Al ejecutar "Iniciar sesión", el script de test guarda el token automáticamente. No necesitas copiarlo manualmente.

---

## 🗺️ Arquitectura de storage

```
Supabase Storage
├── bucket: pets        ← fotos del catálogo de adopciones (web/NestJS)
└── bucket: lost-pets   ← fotos de reportes desde la app móvil (Laravel)
    └── reports/{report_id}/{uuid}.jpg
```

---

## 🌍 Zonas geográficas (escalabilidad)

La tabla `geo_zones` implementa una jerarquía geográfica para filtrado por zona:

```
Ecuador (country)
└── Pichincha (province)
    └── Quito (canton)
        ├── La Mariscal (parish)
        ├── La Carolina (parish)
        ├── Cumbayá (parish)
        └── ...
```

Tanto `pets` como `lost_pet_reports` tienen una columna `geo_zone_id` nullable para vincularse a esta jerarquía cuando el sistema escale.

---

## 🔗 Ecosistema PetsWorld

| Sistema | Stack | Función |
|---|---|---|
| Web (adopciones) | Angular + NestJS | Catálogo de mascotas en adopción |
| App móvil (perdidas) | React Native + Expo + **Laravel** | Red comunitaria de mascotas perdidas |
| Base de datos | PostgreSQL (Supabase) | Compartida entre todos los sistemas |
| Storage | Supabase Storage | Fotos de mascotas |

---

## 👨‍💻 Autor

Desarrollado como proyecto universitario para la materia **Aplicaciones Móviles Híbridas**.
Universidad Laica Eloy Alfaro de Manabí — Carrera de Software.
