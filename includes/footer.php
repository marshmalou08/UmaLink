    </main>
  </div>
</div>

<!-- Delete confirmation (shared by all modules) -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to permanently delete <strong class="delete-label"></strong>?
        This action cannot be undone.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Yes, Delete</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= e(url('assets/vendor/bootstrap.bundle.min.js')) ?>"></script>
<?php foreach ($extraScripts ?? [] as $s): ?>
  <script src="<?= e(url($s)) ?>"></script>
<?php endforeach; ?>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?= $inlineScript ?? '' ?>
</body>
</html>
