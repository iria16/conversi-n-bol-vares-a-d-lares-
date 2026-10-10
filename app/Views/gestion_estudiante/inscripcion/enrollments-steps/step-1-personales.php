<?php
/**
 * Paso 1 - Datos personales del estudiante.
 * Sin variables externas. El id/name de cada campo lo lee enrollments.js
 * (validación del paso y resumen).
*/

$prefixId = '';
$prefixName = '';
$showPhoto = true;
$minDate = '';
$maxDate = '';
$dateTitle = 'Indique la fecha de nacimiento.';
$docMinLength = '7';
$docMaxLength = '20';
$btnQuitarFotoId = 'btnQuitarFotoEstudiante';
$tipoDocOpciones = ['CE', 'V', 'E'];
/* Este es el único formulario de persona que pide los tres niveles del lugar de
   nacimiento. No hay un campo "tipo de persona" en el sistema, así que la marca
   se pone aquí: es lo que convierte esta pantalla en el alta de estudiante. */
$pideMunicipioParroquiaNacimiento = true;
/* $nacionalidadEsRegistroExistente se deja pasar tal cual. Esta vista es el alta, así
   que aquí no se pone en true nunca; quien la incluye desde la edición (el modal de
   editar estudiante) la deja en true antes del include y llega intacta. */
$nacionalidadEsRegistroExistente = $nacionalidadEsRegistroExistente ?? false;
/* El catálogo de nacionalidades se pasa entero y sin desarmar: persona-form.php recibe
   el sobre ['nacionalidades' => [...], 'id_por_defecto' => int] y lo abre él, que es
   el mismo camino que siguen el sub-modal de familiares y el del empleado. */
$tipoDocSeleccionado = 'CE';

include __DIR__ . '/../../../components/persona-form.php';
?>
