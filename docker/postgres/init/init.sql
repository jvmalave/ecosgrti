-- 1. Crear el usuario user
CREATE USER ecos_user WITH PASSWORD 'ecos_password_2024';

-- 2. Crear los Esquemas
CREATE SCHEMA IF NOT EXISTS security;
CREATE SCHEMA IF NOT EXISTS catalogs;
CREATE SCHEMA IF NOT EXISTS core;
CREATE SCHEMA IF NOT EXISTS audit;
CREATE SCHEMA IF NOT EXISTS ia;
CREATE SCHEMA IF NOT EXISTS workflow;

-- 3. Habilitar extensión UUID en el esquema público
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 4. Asignar permisos al usuario user sobre los esquemas
GRANT ALL PRIVILEGES ON DATABASE ecosgrti_db TO ecos_user;
GRANT ALL ON SCHEMA security TO ecos_user;
GRANT ALL ON SCHEMA catalogs TO ecos_user;
GRANT ALL ON SCHEMA core TO ecos_user;
GRANT ALL ON SCHEMA audit TO ecos_user;
GRANT ALL ON SCHEMA ia TO ecos_user;
GRANT ALL ON SCHEMA workflow TO ecos_user;

-- 5. Configurar el search_path para el user
ALTER ROLE ecos_user SET search_path TO core, security, catalogs, workflow, public;
