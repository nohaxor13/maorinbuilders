<?php
// public/templates/footer.php
?>

<footer class="mt-5" style="background:#0f172a">
  <div class="container py-4 mb-footer">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="h5 text-white mb-1">Maorin Builders</div>
        <div class="mb-0">Construction • Renovation • Design &amp; Build</div>
        <div class="small opacity-75">&copy; <?= date('Y') ?> Maorin Builders. All rights reserved.</div>
      </div>
      <div class="col-md-6 text-md-end">
        <div class="small opacity-75">Need a quotation?</div>
        <a class="btn btn-light btn-sm mt-1" href="<?= htmlspecialchars(function_exists('pub_url') ? pub_url('/public/contact.php') : '/public/contact.php', ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
      </div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= htmlspecialchars(function_exists('pub_url') ? pub_url('/assets/js/public.js') : '/assets/js/public.js', ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
