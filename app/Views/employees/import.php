<?php declare(strict_types=1); ?>
<div class="card content-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">Import Employees</h5>
                <p class="text-muted mb-0">Bulk-create employees by uploading an Excel spreadsheet.</p>
            </div>
            <a href="<?= e(url('/employees')); ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <?php if ($importErrors = app()->session()->flash('import_errors')): ?>
            <div class="alert alert-warning">
                <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Import Errors</h6>
                <ul class="mb-0 small">
                    <?php foreach ($importErrors as $err): ?>
                        <li><?= e((string) $err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border">
                    <div class="card-body p-4">
                        <h6 class="mb-3"><i class="bi bi-cloud-upload"></i> Upload Excel File</h6>
                        <form method="post" action="<?= e(url('/employees/import')); ?>" enctype="multipart/form-data">
                            <?= csrf_field(); ?>
                            <div class="mb-3">
                                <label for="import_file" class="form-label">Select File (.xlsx or .xls)</label>
                                <input type="file" name="import_file" id="import_file" class="form-control" accept=".xlsx,.xls" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Import Employees</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border bg-light">
                    <div class="card-body p-4">
                        <h6 class="mb-3"><i class="bi bi-info-circle"></i> How to Import</h6>
                        <ol class="small mb-3">
                            <li class="mb-2"><strong>Download the template</strong> — click the button below to get a pre-formatted Excel file with all the correct column headers and a sample row.</li>
                            <li class="mb-2"><strong>Fill in your data</strong> — add one employee per row. Keep the header row exactly as-is. See the <em>Guide</em> sheet in the template for field descriptions.</li>
                            <li class="mb-2"><strong>Required fields:</strong> <code>employee_code</code>, <code>first_name</code>, <code>last_name</code>, <code>work_email</code>, <code>company</code>, <code>employment_type</code></li>
                            <li class="mb-2"><strong>Lookup fields</strong> (department, job_title, company, etc.) must match existing names exactly (case-insensitive).</li>
                            <li class="mb-2"><strong>Dates</strong> should be in <code>YYYY-MM-DD</code> format.</li>
                            <li class="mb-2"><strong>Upload</strong> the completed file using the form on the left.</li>
                        </ol>
                        <a href="<?= e(url('/employees/import-template')); ?>" class="btn btn-success w-100"><i class="bi bi-download"></i> Download Import Template</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

