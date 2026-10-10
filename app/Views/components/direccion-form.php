<?php
/**
 * Componente reutilizable para la dirección de una persona.
 *
 * @var string $prefixId Prefijo para los IDs de los campos
 * @var string $prefixName Prefijo para los names de los campos
 * @var bool $direccionRequired Si la dirección es requerida (default: true)
 * @var array $estados Lista de estados
*/

$prefixId = $prefixId ?? '';
$prefixName = $prefixName ?? '';
$direccionRequired = $direccionRequired ?? true;
$estados = $estados ?? [];

$reqAttr = $direccionRequired ? 'required' : '';
$reqAst = $direccionRequired ? ' *' : '';
$reqClass = $direccionRequired ? '' : 'd-none'; // Para ocultar el asterisco dinámicamente si se usa JS

$idEstado = $prefixId ? $prefixId . 'Estado' : 'direccionEstado';
$idMunicipio = $prefixId ? $prefixId . 'Municipio' : 'direccionMunicipio';
$idParroquia = $prefixId ? $prefixId . 'Parroquia' : 'direccionParroquia';
$idSector = $prefixId ? $prefixId . 'Sector' : 'sectorUrbanizacion';
$idCalle = $prefixId ? $prefixId . 'Calle' : 'calleAvenida';
$idCasa = $prefixId ? $prefixId . 'Casa' : 'nroCasaApto';
$idReferencia = $prefixId ? $prefixId . 'Referencia' : 'punto_referencia';

$nameEstado = $prefixName ? $prefixName . 'id_estado_ui' : 'id_estado_ui';
$nameMunicipio = $prefixName ? $prefixName . 'id_municipio_ui' : 'id_municipio_ui';
$nameParroquia = $prefixName ? $prefixName . 'id_parroquia' : 'id_parroquia';
$nameSector = $prefixName ? $prefixName . 'sector_urbanizacion' : 'sector_urbanizacion';
$nameCalle = $prefixName ? $prefixName . 'calle_avenida' : 'calle_avenida';
$nameCasa = $prefixName ? $prefixName . 'nro_casa_apto' : 'nro_casa_apto';
$nameReferencia = $prefixName ? $prefixName . 'punto_referencia' : 'punto_referencia';
?>
<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Dirección de habitación</span>
  </div>
  <div class="wizard-summary__card">
    <div class="row g-2 mb-2">
      <div class="col-md-4">
        <label class="label-sigde" for="<?= $idEstado ?>">Estado<span data-requerido-direccion class="<?= $reqClass ?>"> *</span></label>
        <select class="form-select" id="<?= $idEstado ?>" name="<?= $nameEstado ?>" <?= $reqAttr ?>>
          <option value="">Seleccione el estado</option>
          <?php foreach ($estados as $estado): ?>
            <option value="<?= (int) $estado['id_estado'] ?>"><?= htmlspecialchars($estado['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="label-sigde" for="<?= $idMunicipio ?>">Municipio<span data-requerido-direccion class="<?= $reqClass ?>"> *</span></label>
        <select class="form-select" id="<?= $idMunicipio ?>" name="<?= $nameMunicipio ?>"
                data-depende-de="<?= $idEstado ?>" <?= $reqAttr ?>>
          <option value="">Seleccione el municipio</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="label-sigde" for="<?= $idParroquia ?>">Parroquia<span data-requerido-direccion class="<?= $reqClass ?>"> *</span></label>
        <select class="form-select" id="<?= $idParroquia ?>" name="<?= $nameParroquia ?>"
                data-depende-de="<?= $idMunicipio ?>" <?= $reqAttr ?>>
          <option value="">Seleccione la parroquia</option>
        </select>
      </div>
    </div>
    <div class="row g-2">
      <div class="col-md-6">
        <label class="label-sigde" for="<?= $idSector ?>">Sector / Urbanización<span data-requerido-direccion class="<?= $reqClass ?>"> *</span></label>
        <input
          type="text"
          class="form-control"
          id="<?= $idSector ?>"
          name="<?= $nameSector ?>"
          placeholder="Ej: Los Dos Caminos"
          minlength="3"
          maxlength="100"
          data-requerido-direccion
          <?= $reqAttr ?>>
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="<?= $idCalle ?>">Calle / Avenida<span data-requerido-direccion class="<?= $reqClass ?>"> *</span></label>
        <input
          type="text"
          class="form-control"
          id="<?= $idCalle ?>"
          name="<?= $nameCalle ?>"
          placeholder="Ej: Av. Francisco de Miranda"
          minlength="2"
          maxlength="100"
          data-requerido-direccion
          <?= $reqAttr ?>>
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="<?= $idCasa ?>">N° Casa / Apto<span data-requerido-direccion class="<?= $reqClass ?>"> *</span></label>
        <input
          type="text"
          class="form-control"
          id="<?= $idCasa ?>"
          name="<?= $nameCasa ?>"
          placeholder="Ej: Edif. Sigde, Apto 48"
          minlength="1"
          maxlength="20"
          data-requerido-direccion
          <?= $reqAttr ?>>
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="<?= $idReferencia ?>">Punto de Referencia</label>
        <input
          type="text"
          class="form-control"
          id="<?= $idReferencia ?>"
          name="<?= $nameReferencia ?>"
          maxlength="150"
          placeholder="Ej. Frente a la cancha deportiva"
        >
        <div class="invalid-feedback">El punto de referencia no debe superar los 150 caracteres.</div>
      </div>
    </div>
  </div>
</section>
