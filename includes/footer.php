
<footer class="dairy-footer mt-auto">
    <div class="container-fluid px-4">
        <div class="row align-items-center py-3">
            <div class="col-md-4 text-center text-md-start">
                <span class="footer-brand">🐄 Shree Tri Shakti Dairy</span>
            </div>
            <div class="col-md-4 text-center my-2 my-md-0">
                <small class="text-muted">Milk Collection Management System &copy; <?= date('Y') ?></small>
            </div>
            <div class="col-md-4 text-center text-md-end">
                <small class="text-muted">Built for BCA 4th Semester Project</small>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>window.BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>assets/js/helpers.js"></script>
<script src="<?= BASE_URL ?>assets/js/ui.js"></script>
<?php
// Load milk-entry.js and validation.js on pages that need them
$milkEntryPages = ['Milk Collection', 'Dana (Cow Feed)'];
if (in_array($pageTitle, $milkEntryPages, true)) :
?>
<script src="<?= BASE_URL ?>assets/js/milk-entry.js"></script>
<script src="<?= BASE_URL ?>assets/js/validation.js"></script>
<?php endif; ?>
</body>
</html>
