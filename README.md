# SIGDE — Sistema de Gestión de Datos Educativos

Aplicación web para la gestión de inscripciones, ratificaciones, expedientes de
estudiantes, retiros, egresos y configuración académica de una escuela.

## Requisitos

| Componente | Versión |
|---|---|
| PHP | 8.4 con las extensiones `pdo_mysql` y `mbstring` |
| MySQL | 8.0 o superior |
| Composer | 2.x |
| Node.js | 18 o superior |

## Instalación

```bash
composer install     # dependencias de PHP (dompdf, para el reporte PDF)
npm install          # bootstrap, bootstrap-icons, sweetalert2 y sass
npm run build        # copia los recursos de terceros y compila los estilos
npm run verificar    # comprueba que todos los recursos del layout existan
```

### Por qué `npm run build` es un paso aparte

`npm install` deja las bibliotecas en `node_modules/`, pero el layout no las carga de
ahí: apunta a `public/assets/`. El paso `build` las copia a esa carpeta y compila
`resources/scss/app.scss` en `public/assets/css/app.css`.

Si se omite, la aplicación carga y responde bien pero **sin estilos y sin iconos**, que
es la forma más difícil de detectar de una vista. `npm run verificar` existe para eso.

## Base de datos

Las credenciales están en `app/Config/Database.php`.

```bash
mysql -u root -p sidge < database/schema_actual.sql
```

`database/migrations/` guarda el historial de cambios de esquema, en orden de fecha. **No
hay que ejecutarlas para instalar de cero**: `schema_actual.sql` ya está consolidado y
trae todo. Son para subir una base que quedó en una versión anterior.

Dos advertencias sobre ellas:

- No todas son idempotentes. `20261002_ciclo_vida_anio_escolar.sql` y
  `20261002_empleado_turno.sql` usan `ADD COLUMN` sin protección, así que fallan con
  «Duplicate column» si se aplican sobre un esquema ya consolidado. Es lo esperado: son
  incrementales, no de reinstalación.
- Varias imprimen líneas con el valor de sus variables de control (`1`, `0`). No son
  errores; el `mysql` CLI las muestra como resultado de un `SELECT`.

El orden se aplica por nombre de archivo, que es una fecha en formato `AAAAMMDD_nombre`.

`database/backups/` tiene el disparador de transición de matrícula tal como estaba antes
de corregirlo. Se conserva por el mismo motivo.

Para verificar que el esquema instala bien y que una migración corre, sobre una base
temporal:

```bash
mysql -u root -p -e "CREATE DATABASE sigde_prueba"
mysql -u root -p sigde_prueba < database/schema_actual.sql
mysql -u root -p sigde_prueba < database/migrations/20261006_trigger_transicion_orden_anio.sql
mysql -u root -p -e "DROP DATABASE sigde_prueba"
```

## Estructura

```
app/
  Config/         conexión a la base de datos
  Controllers/    un controlador por módulo
  Exceptions/     excepciones de dominio, con su código HTTP asociado
  Http/           utilidades de respuesta
  Interfaces/     contratos que implementan los modelos
  Models/         acceso a datos; aquí vive la lógica de negocio
  Support/        clases sin estado compartidas
  Traits/         reutilización entre controladores y modelos
  Views/          plantillas PHP
database/         esquema, migraciones y respaldos
docs/             documentos del proyecto
public/           raíz web: index.php y assets
resources/scss/   fuentes de estilos
```

El punto de entrada es `public/index.php`, que enruta por `/controlador/accion`. Con el
servidor embebido de PHP:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

Ese servidor no sirve archivos estáticos por su cuenta y devuelve 400 en ellos; para
desarrollar conviene apuntar el navegador a las rutas PHP y dejar los assets a un
servidor web real (Apache o Nginx), que sí los sirve directamente.

## Módulos

| Módulo | Ruta |
|---|---|
| Años escolares | `academicYear/index` |
| Estructura académica | `academicStructure/index` |
| Asignación docente | `teacherAssignment/index` |
| Catálogos | `catalogo/index` |
| Empleados | `empleado/index` |
| Inscripción | `inscripciones/index` |
| Ratificación | `ratificacion/index` |
| Retiro | `withdrawal/index` |
| Egreso | `egresos/index` |
| Expediente del estudiante | `estudiantes/show/{id}` |
| Cuentas de usuario | `cuentaUsuario/index` |
| Bitácora | `bitacora/index` |

Las acciones disponibles de cada módulo están en la lista blanca de
`public/index.php`, que es también lo que decide qué rol entra a qué.

## Reporte PDF

El expediente del estudiante se exporta a PDF con Dompdf. La plantilla es
`app/Views/consulta_academica/estudiantes/export-pdf.php` y los estilos que usa están
en `resources/scss/components/_student-record-pdf.scss`, que se compila dentro del mismo
`app.css`.

La plantilla comparte con la pantalla el método de agrupación de familiares
(`RatificacionModel::getFamiliaresParaExpediente()`), de modo que el documento impreso y
la ficha digital no pueden apartarse.

## Problemas frecuentes

**Los botones no abren modales.** Falta Bootstrap o SweetAlert2 en `public/assets/`.
Revisar con `npm run verificar`.

**La pantalla aparece sin iconos.** Falta la fuente `woff2` de bootstrap-icons. Es el
fallo más difícil de ver porque el CSS carga igual. `npm run verificar` lo detecta.

**Faltan estilos.** No se compiló el SCSS: `npm run sass:build`.

**Las fotos que sube la aplicación no aparecen.** `public/uploads/` está en
`.gitignore` a propósito: son datos personales y no deben entrar al repositorio.
