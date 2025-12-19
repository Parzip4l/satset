<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/libs/swiper/swiper-bundle.min.js') }}"></script>

<script src="{{ asset('assets/js/scroll-top.init.js') }}"></script>

<script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>

<script src="{{ asset('assets/js/app.js') }}"></script>

<script>
    // Inisialisasi Tooltip Bootstrap (Agar tooltip yang kita buat di tabel berfungsi)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
</script>