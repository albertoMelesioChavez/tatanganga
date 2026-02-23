# PHP Optimizer Skills

## Descripción
Optimizador de código PHP para Moodle

## Instrucciones
Optimiza el código PHP para rendimiento en Moodle. Usa caching cuando sea apropiado (Moodle cache), minimiza queries a la BD (usa get_records_sql con joins cuando sea necesario), evita N+1 queries, usa las funciones de Moodle para acceso a datos, y sigue las mejores prácticas de PHP 8.3+ (tipos estrictos, null coalescing). Prioriza legibilidad sobre micro-optimizaciones.

### Caching:
- Usar cache::make() y cache->get/set
- Implementar cache invalidation apropiada
- Considerar MUC para datos complejos

### Base de datos:
- Usar joins en lugar de múltiples queries
- get_records_sql() para consultas complejas
- Evitar N+1 problems
- Usar índices apropiados

### PHP 8.3+:
- Tipos estrictos (declare(strict_types=1))
- Null coalescing operator (??)
- Union types cuando sea apropiado
- Constructor property promotion

### Moodle APIs:
- Usar funciones globales de Moodle
- Aprovechar context_system
- Seguir convenciones de nomenclatura

### Rendimiento:
- Perfilar código lento
- Usar Xdebug para análisis
- Monitorear memory usage
