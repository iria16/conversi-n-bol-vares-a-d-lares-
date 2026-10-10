<?php
/**
 * Paso 2 - Contacto.
 * Los teléfonos usan components/phone-input.php (allí viven los prefijos del ENUM de `contacto`).
 *
 * El correo es opcional.
*/
$prefixId = '';
$prefixName = '';
$supportText = 'Complete los datos de comunicación del trabajador para asegurar una vía de contacto efectiva.';
$phoneRequired = true;
include __DIR__ . '/../../components/contacto-form.php';
?>
