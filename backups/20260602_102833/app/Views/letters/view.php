<?php declare(strict_types=1); ?>
<?php
use App\Modules\Letters\LetterRepository;
$isAdmin = can('letters.manage');
?>
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <a href="<?= e(url($isAdmin ? '/letters/admin' : '/letters/my')); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('/letters/' . (int) ($letter['id'] ?? 0) . '/download')); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download"></i> Download PDF
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
</div>

<style>
/* Screen */
.letter-paper {
    background: #fff;
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 20mm 22mm;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 11pt;
    line-height: 1.8;
    color: #212529;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
}
.letter-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}
.letter-company h2 {
    font-size: 16pt;
    font-weight: 700;
    margin: 4px 0 2px;
    color: #0d6efd;
}
.letter-meta {
    font-size: 10pt;
    color: #6c757d;
    text-align: right;
}
.letter-divider {
    border: none;
    border-top: 2px solid #0d6efd;
    margin: 0 0 18px;
}
.letter-subject {
    font-size: 12pt;
    text-decoration: underline;
    text-align: center;
    margin-bottom: 16px;
    letter-spacing: 0.5px;
    font-weight: bold;
}
.letter-salutation { margin-bottom: 14px; }
.letter-body p { margin-bottom: 14px; text-align: justify; }
.letter-closing { margin-top: 28px; }
.letter-signature { margin-top: 44px; }
.signature-line {
    width: 150px;
    border-top: 1px solid #212529;
    margin-bottom: 6px;
}
.letter-footer {
    margin-top: 36px;
    padding-top: 12px;
    border-top: 1px solid #dee2e6;
    font-size: 9pt;
    color: #6c757d;
}
@media print {
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
    html, body { background: #fff !important; margin: 0; padding: 0; }
    .letter-paper {
        border: none !important;
        box-shadow: none !important;
        width: 100%;
        min-height: unset;
        margin: 0;
        padding: 15mm 18mm;
        border-radius: 0;
    }
    .sidebar, .topbar, nav, header, footer { display: none !important; }
    @page { size: A4 portrait; margin: 0; }
}
</style>

<?= $letter['letter_content'] ?? ''; ?>
