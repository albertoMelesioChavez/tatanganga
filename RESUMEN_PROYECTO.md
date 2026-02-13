# Proyecto Moodle — Tatanganga

> **Resumen ejecutivo** del trabajo realizado, con jerarquías y estilo tipo presentación.

---

## 1. Panorama general

### 1.1 Plataforma
- **Moodle 5.0.4+** desplegado para **tatanganga.cloud**.
- Entorno preparado con configuración productiva y ruta de datos dedicada.

### 1.2 Objetivo
- Implementación completa de Moodle, con personalización visual y automatización de despliegues.

---

## 2. Configuración del servidor

### 2.1 Conexión a base de datos
- **Driver:** `mysqli`
- **DB:** `moodle`
- **Usuario:** `moodle`

### 2.2 Rutas y permisos
- **wwwroot:** `https://tatanganga.cloud`
- **dataroot:** `/home/user/htdocs/moodledata`
- **permisos:** `02777`

---

## 3. Instalación del software

### 3.1 Pasos ejecutados
1. Copia de archivos Moodle al servidor.
2. Creación de la base de datos.
3. Ejecución de `install.php` para generar `config.php`.
4. Verificación de accesos y configuración inicial.

---

## 4. Plugins y módulos incorporados

### 4.1 Plugins personalizados
- **local_calendario** (plugin propio, release 1.0.0).

### 4.2 Pasarela de pago
- **PayPal gateway** habilitado.

### 4.3 Proveedores de IA
- **OpenAI**
- **AzureAI**
- **Ollama**

---

## 5. Personalización visual (UI/UX)

### 5.1 Tema personalizado
- **theme_tatanganga** creado e integrado.

### 5.2 Estilo aplicado
- Navegación limpia, fondos claros, cards, y eliminación de acentos azules.
- Tipografía y layout optimizados para un look moderno y minimalista.

---

## 6. Automatización y despliegue

### 6.1 Auto-deploy
- Webhook de GitHub configurado para:
  - `git pull` al push en `main`.
  - `purge_caches.php` posterior al despliegue.

---

# 💰 Costos y cierre

> **Nota:** A partir de mañana incluyo **1 mes de asistencia** con cualquier modificación del contenido o modificación estética.

## 1. Desglose
- **Proyecto completo Moodle:** $18,000.00
- **Servidor:** $3,459.00

## 2. Total
**$21,459.00**

## 3. Pagado
**$10,000.00**

## 4. Saldo final
**$11,459.00**
