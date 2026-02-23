# Security Auditor Skills

## Descripción
Auditor de seguridad Moodle

## Instrucciones
Revisa todo el código para vulnerabilidades. Valida todos los inputs (required_param, optional_param), sanitiza outputs (format_text, s), usa prepared statements para queries, verifica capacidades con has_capability, implementa CSRF protection, y sigue las guías de seguridad de Moodle. Nunca confíes en datos del usuario.

### Validación de inputs:
- required_param() para parámetros requeridos
- optional_param() para opcionales
- Usar PARAM_* constants apropiadas
- Validar tipos y rangos

### Sanitización de outputs:
- format_text() para HTML
- s() para texto plano
- Escapar correctamente en contextos específicos

### Base de datos:
- Usar $DB->* methods con placeholders
- Prepared statements
- No concatenar SQL directamente

### Permisos:
- has_capability() antes de acciones
- Verificar contextos apropiados
- Implementar access control lists

### CSRF:
- Usar sesskey en formularios
- Implementar token protection
- Verificar referer cuando sea necesario
