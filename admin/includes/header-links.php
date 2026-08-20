<!-- Tell the browser to be responsive to screen width -->
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Font Awesome -->
<link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
<!-- Ionicons -->
<link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
<!-- Tempusdominus Bbootstrap 4 -->
<link rel="stylesheet" href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
<!-- iCheck -->
<link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
<!-- JQVMap -->
<link rel="stylesheet" href="plugins/jqvmap/jqvmap.min.css">
<!-- Theme style -->
<link rel="stylesheet" href="dist/css/adminlte.min.css">
<!-- overlayScrollbars -->
<link rel="stylesheet" href="plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
<!-- Daterange picker -->
<link rel="stylesheet" href="plugins/daterangepicker/daterangepicker.css">
<!-- summernote -->
<link rel="stylesheet" href="plugins/summernote/summernote-bs4.css">
<link rel="stylesheet" href="custom/admin-modals.css">
<link rel="stylesheet" href="crm/assets/crm-list.css">
<!-- DataTables -->
<link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- Custom CSS last: system UI font stack overrides AdminLTE -->
<link rel="stylesheet" href="custom/custom-css.css">
<link rel="shortcut icon" href="favicon.ico" type="img/web-logo.png">
<!-- <script src="ckeditor/ckeditor.js"></script> -->
<link rel="icon" href="../images/icons1.png" type="image/x-icon">
<script>
(function () {
  var storageKey = 'remember.lte.pushmenu';
  var collapsedClass = 'sidebar-collapse';
  try {
    if (localStorage.getItem(storageKey) === null) {
      localStorage.setItem(storageKey, collapsedClass);
    }
  } catch (e) {}

  function applyCollapsedBodyClass() {
    if (document.body && !document.body.classList.contains(collapsedClass)) {
      document.body.classList.add(collapsedClass);
    }
  }

  if (document.body) {
    applyCollapsedBodyClass();
  } else {
    new MutationObserver(function (_mutations, observer) {
      if (document.body) {
        applyCollapsedBodyClass();
        observer.disconnect();
      }
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
})();
</script>