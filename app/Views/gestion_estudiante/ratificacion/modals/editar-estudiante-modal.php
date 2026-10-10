<?php
/**
 * Modal para editar datos del estudiante.
 *
 * Reutiliza las vistas de los pasos del wizard de inscripción (1, 4 y 6), así
 * que el formulario se ve igual en los dos módulos y no hay dos versiones que
 * se desincronicen.
 *
 * Reutilizada también desde el expediente; conserva un solo flujo de edición
 * para datos del estudiante e información familiar.
 *
 * `enctype` es obligatorio: el paso 1 manda la foto y sin esto el archivo no
 * viaja en el FormData.
 */
ob_start();
?>
<form id="formEditarEstudiante" method="POST" enctype="multipart/form-data"
      action="<?= BASE_URL ?>ratificacion/updateEstudiante">
  <input type="hidden" name="id_estudiante" id="editEstudianteId">
  
  <div class="d-flex align-items-center p-3 border-bottom">
    <div class="avatar avatar--lg avatar--primary me-3" id="editAvatar">
      <img src="" alt="" class="d-none" id="editAvatarImg">
      <span id="editAvatarInitials"></span>
    </div>
    <div>
      <div class="fw-bold text-dark fs-5" id="editNombreCompleto"></div>
      <div class="badge bg-light text-dark border mt-1">
        ID: <span id="editCedulaId"></span>
      </div>
    </div>
  </div>

  <ul class="nav nav-tabs modal-tabs px-3 pt-3 border-bottom-0" id="editTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <?php /* Solo la primera lleva `active`: es el estado INICIAL, y Bootstrap lo
             mueve solo al cambiar de pestaña. No se pone `text-dark` ni `text-muted`
             en ninguna: esas utilidades llevan `!important` y ganan sobre
             `.nav-link.active`, así que el color se quedaba clavado en la primera
             pestaña. El color lo pone el estado, en .modal-tabs. */ ?>
      <button class="nav-link active" id="tab-personales" data-bs-toggle="tab" data-bs-target="#pane-personales" type="button" role="tab">Datos Personales</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-adicional" data-bs-toggle="tab" data-bs-target="#pane-adicional" type="button" role="tab">Información Adicional</button>
    </li>
    <li class="nav-item" role="presentation">
      <?php /* La pestaña YA abre el sub-modal: es el mismo que usa el wizard de
             inscripción (inscripcion/modal-agregar-familiar.php, incluido más
             abajo), porque son los mismos familiares y las mismas reglas.

             Lo que cambia en esta pantalla es el alcance, y lo fija
             ratificacion.js al arrancar el módulo: se pueden agregar familiares y
             cambiar el contacto y el domicilio del REPRESENTANTE LEGAL; el
             contacto de los demás se muestra apagado, porque no es lo que la
             secretaria debe cambiar desde acá. El aviso está dentro del propio
             sub-modal (avisoContactoNoEditable). */ ?>
      <button class="nav-link" id="tab-familiares" data-bs-toggle="tab"
              data-bs-target="#pane-familiares" type="button" role="tab">
        Familiares
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <?php /* Los recaudos van AQUÍ y no en una pestaña aparte de la ficha porque este es el
             modal que comparten la ficha y la ratificación: los documentos del
             representante se actualizan igual en los dos sitios y no hacía falta otra
             pantalla. El partial es el mismo de la inscripción. */ ?>
      <button class="nav-link" id="tab-recaudos" data-bs-toggle="tab"
              data-bs-target="#pane-recaudos" type="button" role="tab">
        Recaudos
      </button>
    </li>
  </ul>

  <div class="tab-content bg-white p-3 border-top" id="editTabsContent">
    <!-- Datos Personales -->
    <div class="tab-pane fade show active" id="pane-personales" role="tabpanel">
      <?php /* Esta pantalla EDITA un alumno que ya existe, a diferencia del wizard de
             inscripción que es el alta. La bandera lo dice para que el <select> de
             nacionalidad no le proponga Venezolano a quien ya está en la base sin ese
             dato: eso escribiría una nacionalidad que nadie verificó. El sub-modal de
             familiares que se incluye más abajo no lleva el campo, así que la bandera
             no le afecta aunque siga en el scope. */
      $nacionalidadEsRegistroExistente = true;
      include __DIR__ . '/../../inscripcion/enrollments-steps/step-1-personales.php';
      ?>
    </div>

    <!-- Información Adicional -->
    <div class="tab-pane fade" id="pane-adicional" role="tabpanel">
      <?php include __DIR__ . '/../../inscripcion/enrollments-steps/step-4-adicional.php'; ?>
    </div>

    <!-- Familiares: se cargan desde ratificacion/datosEstudiante y los escribe
         public/assets/js/familiares.js, igual que en el wizard de inscripción. El
         hidden arranca con "[]": si no viniera la lista, un guardado no debe
         borrar a los familiares que ya están. -->
    <div class="tab-pane fade" id="pane-familiares" role="tabpanel">
      <?php include __DIR__ . '/../../inscripcion/enrollments-steps/step-6-familiares.php'; ?>
      <input type="hidden" name="familiares_json" id="familiaresJson" value="[]">
    </div>

    <!-- Recaudos: se cargan desde ratificacion/datosEstudiante junto con el resto de los
         datos y los guarda public/assets/js/recaudos.js. Van como <div> y no como
         <form> porque este modal ya es un formulario. -->
    <div class="tab-pane fade" id="pane-recaudos" role="tabpanel">
      <p class="text-support">
        Marque los documentos que el representante entregue. Lo que se registre aquí queda
        anotado con su fecha y con la persona que lo anotó.
      </p>
      <?php
        $recaudos            = [];
        $recaudosEstudiante  = 0;
        $recaudosEditable    = true;
        /* El guardado es el de "Guardar Cambios", el mismo botón del modal: un segundo
           botón para lo mismo hace dudar de cuál guarda qué. Y el panel no es <form>
           porque este modal ya lo es. */
        $recaudosBotonPropio = false;
        $recaudosContenedor  = 'recaudosModalEditar';
        require __DIR__ . '/../../inscripcion/partials/recaudos-panel.php';
      ?>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formEditarEstudiante" class="btn btn-primary" style="background-color: #0b2253; border-color: #0b2253;">
  <i class="bi bi-floppy me-1"></i> Guardar Cambios
</button>
<?php
$modalFooter = ob_get_clean();

/* El modal tiene DOS cabeceras a propósito y no es un descuido:
   el título del componente ("Editar Estudiante") dice qué acción se está haciendo,
   y el bloque de arriba, con la foto y el nombre, dice de quién. Quitando cualquiera
   de los dos la secretaria pierde una de esas dos cosas. */
$modalId       = 'modalEditarEstudiante';
$modalTitle    = 'Editar Estudiante';
$modalSubtitle = '';
/* xl y no lg como los demás modales. Es el único con cuatro pestañas (Datos
   Personales, Información Adicional, Familiares, Recaudos) y con una tabla de
   tres columnas dentro, y a 800px la caja de observación queda ilegible y el paso 1
   (foto + nombres) se parte en dos columnas que se leen con esfuerzo. El cuerpo
   además tiene max-height propio, así que el ancho es lo único que hace falta.

   Si algún día se normaliza a xl el resto de modales del sistema, esto se vuelve
   un 'lg' más. */
$modalSize     = 'xl';
include __DIR__ . '/../../../components/modal.php';

/* El sub-modal de familiares, como hermano inmediato del modal de edición: los
   modales anidados tienen que quedar así (ver "Modales anidados" en
   _form-wizard.scss). Es el MISMO archivo que usa el alta de inscripción, a
   propósito: son los mismos familiares y las mismas reglas. */
include __DIR__ . '/../../inscripcion/modal-agregar-familiar.php';
