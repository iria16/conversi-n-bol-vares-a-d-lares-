<?php
/**
 * Paso 6 - Confirmación.
 * staff.js rellena cada [data-resumen="clave"] al entrar al paso y el botón
 * [data-editar-paso="n"] regresa al paso n. Los títulos se pintan en #resumenTitulos.
 *
 * $filas = filas del resumen; cada fila agrupa 1 o 2 secciones (la fila se
 * divide sola en columnas, ver .wizard-summary__row). 'campos' => null indica
 * la sección de títulos, que se pinta por JS.
 */
$filas = [
    [
        ['paso' => 1, 'icono' => 'bi-person', 'titulo' => 'Datos Personales', 'campos' => [
            ['Nombre completo',     'nombre_completo'],
            ['Cédula',              'documento'],
            ['Fecha de nacimiento', 'fecha_nacimiento'],
            ['Sexo',                'sexo'],
        ]],
    ],
    [
        ['paso' => 2, 'icono' => 'bi-telephone', 'titulo' => 'Información de Contacto', 'campos' => [
            ['Teléfono principal', 'telefono_principal'],
            ['Correo electrónico', 'correo'],
        ]],
        ['paso' => 3, 'icono' => 'bi-geo-alt', 'titulo' => 'Dirección de Habitación', 'campos' => [
            ['Parroquia', 'parroquia'],
            ['Dirección', 'direccion'],
        ]],
    ],
    [
        ['paso' => 4, 'icono' => 'bi-briefcase', 'titulo' => 'Información Laboral', 'campos' => [
            ['Cargo',            'cargo'],
            ['Fecha de ingreso', 'fecha_ingreso'],
        ]],
        ['paso' => 5, 'icono' => 'bi-mortarboard', 'titulo' => 'Formación Académica', 'campos' => null],
    ],
];
?>
<div class="form-section__label">Confirmar Registro</div>
<p class="text-support mb-4">Revisa que todo esté correcto antes de finalizar.</p>

<div class="wizard-summary">
  <?php foreach ($filas as $fila): ?>
    <div class="wizard-summary__row">
      <?php foreach ($fila as $seccion): ?>
        <div class="wizard-summary__section">
          <div class="wizard-summary__section-header">
            <span class="wizard-summary__icon"><i class="bi <?= $seccion['icono'] ?>"></i></span>
            <span class="wizard-summary__section-title"><?= htmlspecialchars($seccion['titulo']) ?></span>
            <button type="button" class="wizard-summary__edit-btn" data-editar-paso="<?= $seccion['paso'] ?>">
              <i class="bi bi-pencil"></i> Editar
            </button>
          </div>
          <div class="wizard-summary__card">
            <?php if ($seccion['campos'] === null): ?>
              <div id="resumenTitulos" class="wizard-summary__titulos">
                <p class="text-support mb-0">Sin títulos registrados.</p>
              </div>
            <?php else: ?>
              <div class="wizard-summary__grid">
                <?php foreach ($seccion['campos'] as [$etiqueta, $clave]): ?>
                  <div class="wizard-summary__item">
                    <div class="wizard-summary__label"><?= htmlspecialchars($etiqueta) ?></div>
                    <div class="wizard-summary__value" data-resumen="<?= $clave ?>">—</div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="info-alert info-alert--warning">
    <div class="info-alert__icon"><i class="bi bi-exclamation-circle"></i></div>
    <div>
      <div class="info-alert__title">Verificación Requerida</div>
      <p class="info-alert__text">Por favor, verifique que todos los datos sean correctos antes de finalizar el registro. Una vez procesado, el empleado tendrá acceso a su portal personal.</p>
    </div>
  </div>
</div>