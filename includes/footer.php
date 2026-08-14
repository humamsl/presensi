</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// Ubah dropdown filter/picker menjadi Select2 (bisa dicari, tema Bootstrap 5).
// Select kecil di dalam tabel padat (.form-select-sm) & yang ditandai .no-select2 tetap native.
jQuery(function ($) {
  $('select:not(.form-select-sm):not(.no-select2)').each(function () {
    $(this).select2({
      theme: 'bootstrap-5',
      width: '100%',
      language: {
        noResults: () => 'Tidak ada data',
        searching: () => 'Mencari…'
      }
    });
  });
});
</script>
</body>
</html>
