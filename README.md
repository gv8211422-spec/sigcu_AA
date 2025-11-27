# SIGCU_AA - Sistema Integral de Gestión de Comunidad Universitaria

## 📁 Estructura del Proyecto

```
sigcu_aa/
├── config/
│   ├── database.php        # Conexión PDO a MySQL
│   └── config.php          # Configuración general del sistema
├── includes/
│   ├── session.php         # Manejo de sesiones y seguridad
│   ├── header.php          # Header HTML común
│   ├── footer.php          # Footer HTML común
│   └── navbar.php          # Navegación por roles
├── modules/
│   ├── admin/              # Módulo Administrador
│   │   └── dashboard.php
│   ├── docente/            # Módulo Docente
│   │   └── dashboard.php
│   ├── alumno/             # Módulo Alumno
│   │   └── dashboard.php
│   └── administrativo/     # Módulo Administrativo
│       └── dashboard.php
├── assets/
│   ├── css/
│   │   └── style.css       # Estilos principales
│   ├── js/
│   │   └── main.js         # JavaScript principal
│   └── images/
├── uploads/                # Archivos subidos por usuarios
├── BD.sql                  # Script de base de datos
├── index.php               # Punto de entrada (redirige según rol)
├── login.php               # Login
├── register.php            # Registro de usuarios
├── logout.php              # Cerrar sesión
└── acceso_denegado.php     # Página de error de permisos
```

## 🚀 Instalación

1. **Importar la base de datos:**
   ```bash
   mysql -u root -p < BD.sql
   ```

2. **Configurar conexión:**
   Editar `config/database.php` con tus credenciales MySQL

3. **Iniciar servidor:**
   ```bash
   php -S localhost:8000
   ```

4. **Acceder al sistema:**
   - URL: http://localhost:8000
   - Credenciales: Dependen de los datos poblados por tu compañero

## 👥 Roles del Sistema

- **Administrador**: Gestión completa de usuarios y sistema
- **Docente**: Gestión de actividades, calificaciones y asistencia
- **Alumno**: Consulta de calificaciones, materias y actividades
- **Administrativo**: Asignación de horarios y gestión operativa

## 🔐 Sistema de Seguridad

- Contraseñas hasheadas con `password_hash()`
- Sesiones con `session_regenerate_id()`
- Validación de roles con `requiere_rol()`
- Registro de acciones en `historial_sistema`

## 📝 División de Trabajo Sugerida

### Persona A:
- [ ] Módulo de Autenticación (recuperación de contraseña)
- [ ] Módulo Alumno completo
- [ ] Módulo de Comunicación (mensajes/foros)

### Persona B:
- [ ] Módulo Administrador completo
- [ ] Módulo Docente completo
- [ ] Módulo Administrativo completo

## 🎯 Próximos Pasos

1. Esperar datos de prueba en BD
2. Probar login con usuarios poblados
3. Implementar funcionalidades específicas de cada módulo
4. Desarrollar módulo de comunicación

## 📌 Notas Importantes

- Todo en español (columnas, variables, UI)
- Charset: `utf8mb4` para caracteres españoles
- Estados: activo, inactivo, pendiente
- Sistema de calificaciones: 70% exámenes + 30% actividades
