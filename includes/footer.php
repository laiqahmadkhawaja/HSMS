<?php
/**
 * HSMS - Master Layout Footer
 */

declare(strict_types=1);
?>
        </main>

        <!-- Footer -->
        <footer class="app-footer no-print">
            <div>
                &copy; <?= date('Y') ?> <strong><?= e(APP_NAME) ?></strong>. All rights reserved.
            </div>
            <div>
                <span class="badge bg-light text-dark border me-2">v<?= e(APP_VERSION) ?></span>
                <span class="text-muted"><i class="bi bi-clock me-1"></i> <?= date('d M Y, h:i A') ?></span>
            </div>
        </footer>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS (Includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- HSMS Core Scripts -->
<script src="<?= base_url('assets/js/main.js') ?>"></script>
</body>
</html>
