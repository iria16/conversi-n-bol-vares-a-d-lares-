<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * FotoSubidaTrait
 * -----------------------------------------------------------------
 * Validación y guardado de una foto de perfil subida por un formulario
 * (wizard de empleado, de estudiante y de familiar).
 *
 * Existía como private procesarFotoSubida() duplicado en EmpleadoController
 * e InscripcionesController: el cuerpo era idéntico y solo cambiaban el
 * prefijo del archivo y la carpeta. Acá vive una sola vez y ambos lo usan.
 *
 * El método usa $this->jsonResponse() (BaseController) para cortar la
 * ejecución con el error, igual que antes: si algo falla, nunca se sigue al
 * guardado. Quien lo use debe heredar de Controller (o implementar ese
 * método). Por eso el trait está partido en dos responsabilidades:
 *
 *  - procesarFotoSubida(): necesita jsonResponse(), es de los CONTROLADORES
 *    (empleado, estudiante; el familiar todavía no la persiste).
 *  - borrarFoto(): no depende del controlador, sirve en modelos y
 *    controladores (por ejemplo, para limpiar la foto cuando la
 *    transacción del alta falla y el archivo queda huérfano).
 *
 * Uso:
 *   use FotoSubidaTrait;
 *   ...
 *   'foto' => $this->procesarFotoSubida(['carpeta' => 'estudiantes', 'prefijo' => 'estudiante_']),
 */
trait FotoSubidaTrait
{
    /**
     * Formatos y tamaños permitidos por defecto (2 MB).
     *
     * @var array<string,string> mime => extensión
     */
    private static array $fotoMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const FOTO_PESO_MAXIMO = 2 * 1024 * 1024;

    /**
     * Valida la foto subida y la deja en public/uploads/{carpeta}.
     *
     * @param array $opciones [
     *   'campo'   => string  nombre del input en $_FILES (default 'foto')
     *   'carpeta' => string  subcarpeta de public/uploads (default 'empleados')
     *   'prefijo' => string  prefijo del nombre del archivo (default 'foto')
     *   'requerida' => bool  si true, la ausencia del archivo es un error 422
     * ]
     * @return string|null Ruta relativa ('/uploads/...') o null si no se envió archivo.
     */
    protected function procesarFotoSubida(array $opciones = []): ?string
    {
        $campo     = $opciones['campo'] ?? 'foto';
        $carpeta   = $opciones['carpeta'] ?? 'empleados';
        $prefijo   = $opciones['prefijo'] ?? 'foto';
        $requerida = (bool) ($opciones['requerida'] ?? false);

        // Un archivo con error de subida (distinto de "no se envió nada") es
        // un fallo de la petición: hay que avisar, no seguir como si no hubs.
        if (!empty($_FILES[$campo]['error']) && $_FILES[$campo]['error'] !== UPLOAD_ERR_NO_FILE) {
            $this->jsonResponse(false, null, 'No se pudo subir la foto. Inténtalo de nuevo.', 422);
        }

        if (empty($_FILES[$campo]['name'])) {
            if ($requerida) {
                $this->jsonResponse(false, null, 'La foto es obligatoria.', 422);
            }
            return null;
        }

        $archivo = $_FILES[$campo];

        // getimagesize() valida que el archivo sea realmente una imagen: un
        // .php renombrado a .jpg pasa el check de extensión, pero no esto.
        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false) {
            $this->jsonResponse(false, null, 'La foto debe ser una imagen válida.', 422);
        }

        $mime = $info['mime'] ?? '';
        if (!isset(self::$fotoMimes[$mime])) {
            $this->jsonResponse(false, null, 'La foto debe ser JPG, PNG o WEBP.', 422);
        }

        if ($archivo['size'] > self::FOTO_PESO_MAXIMO) {
            $this->jsonResponse(false, null, 'La foto no puede superar los 2 MB.', 422);
        }

        // Nombre aleatorio: el del archivo lo elige quien lo sube y no se
        // puede usar para predecir rutas.
        $nombre      = $prefijo . bin2hex(random_bytes(8)) . '.' . self::$fotoMimes[$mime];
        $directorio  = __DIR__ . '/../../public/uploads/' . $carpeta;

        if (!is_dir($directorio) && !mkdir($directorio, 0755, true)) {
            $this->jsonResponse(false, null, 'No se pudo crear el directorio de fotos.', 500);
        }

        if (!move_uploaded_file($archivo['tmp_name'], $directorio . '/' . $nombre)) {
            $this->jsonResponse(false, null, 'No se pudo guardar la foto en el servidor.', 500);
        }

        return '/uploads/' . $carpeta . '/' . $nombre;
    }

    /**
     * Borra una foto ya guardada, si existe.
     *
     * Complemento de procesarFotoSubida(): cuando el guardado del registro
     * falla (cédula duplicada, excepción del modelo, rollback de la
     * transacción) el archivo recién subido queda sin referencia en la base,
     * así que hay que limpiarlo del disco.
     *
     * Solo admite rutas dentro de public/uploads/: cualquier otra cosa
     * (incluido un ../../) se ignora, para que un valor manipulado no
     * permita borrar un archivo del sistema.
     *
     * @param string|null $ruta Ruta relativa devuelta por procesarFotoSubida().
     */
    protected function borrarFoto(?string $ruta): void
    {
        if ($ruta === null || !str_starts_with($ruta, '/uploads/')) {
            return;
        }

        // Sin '..' ni barras iniciales duplicadas: la ruta debe seguir siendo
        // relativa al directorio de public/.
        if (str_contains($ruta, '..')) {
            return;
        }

        $archivo = __DIR__ . '/../../public' . $ruta;

        if (is_file($archivo) && !unlink($archivo)) {
            error_log('FotoSubidaTrait::borrarFoto(): no se pudo eliminar ' . $ruta);
        }
    }
}
