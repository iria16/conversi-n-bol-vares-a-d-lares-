document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('modalVistaPreviaExpediente');
  var iframe = modal ? modal.querySelector('.student-record__pdf-preview iframe') : null;

  if (!modal || !iframe) return;

  modal.addEventListener('show.bs.modal', function () {
    var pdfUrl = iframe.dataset.src;
    if (!pdfUrl) return;

    iframe.src = pdfUrl;
    delete iframe.dataset.src;
  });
});
