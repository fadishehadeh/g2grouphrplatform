<?php
declare(strict_types=1);
$countries = ['Afghanistan','Albania','Algeria','Andorra','Angola','Antigua and Barbuda','Argentina','Armenia','Australia','Austria','Azerbaijan','Bahamas','Bahrain','Bangladesh','Barbados','Belarus','Belgium','Belize','Benin','Bhutan','Bolivia','Bosnia and Herzegovina','Botswana','Brazil','Brunei','Bulgaria','Burkina Faso','Burundi','Cabo Verde','Cambodia','Cameroon','Canada','Central African Republic','Chad','Chile','China','Colombia','Comoros','Congo','Costa Rica','Croatia','Cuba','Cyprus','Czech Republic','Denmark','Djibouti','Dominica','Dominican Republic','Ecuador','Egypt','El Salvador','Equatorial Guinea','Eritrea','Estonia','Eswatini','Ethiopia','Fiji','Finland','France','Gabon','Gambia','Georgia','Germany','Ghana','Greece','Grenada','Guatemala','Guinea','Guinea-Bissau','Guyana','Haiti','Honduras','Hungary','Iceland','India','Indonesia','Iran','Iraq','Ireland','Italy','Ivory Coast','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kiribati','Kosovo','Kuwait','Kyrgyzstan','Laos','Latvia','Lebanon','Lesotho','Liberia','Libya','Liechtenstein','Lithuania','Luxembourg','Madagascar','Malawi','Malaysia','Maldives','Mali','Malta','Marshall Islands','Mauritania','Mauritius','Mexico','Micronesia','Moldova','Monaco','Mongolia','Montenegro','Morocco','Mozambique','Myanmar','Namibia','Nauru','Nepal','Netherlands','New Zealand','Nicaragua','Niger','Nigeria','North Korea','North Macedonia','Norway','Oman','Pakistan','Palau','Palestine','Panama','Papua New Guinea','Paraguay','Peru','Philippines','Poland','Portugal','Qatar','Romania','Russia','Rwanda','Saint Kitts and Nevis','Saint Lucia','Saint Vincent and the Grenadines','Samoa','San Marino','Sao Tome and Principe','Saudi Arabia','Senegal','Serbia','Seychelles','Sierra Leone','Singapore','Slovakia','Slovenia','Solomon Islands','Somalia','South Africa','South Korea','South Sudan','Spain','Sri Lanka','Sudan','Suriname','Sweden','Switzerland','Syria','Taiwan','Tajikistan','Tanzania','Thailand','Timor-Leste','Togo','Tonga','Trinidad and Tobago','Tunisia','Turkey','Turkmenistan','Tuvalu','Uganda','Ukraine','United Arab Emirates','United Kingdom','United States','Uruguay','Uzbekistan','Vanuatu','Vatican City','Venezuela','Vietnam','Yemen','Zambia','Zimbabwe'];

$typesByCategory = [];
foreach ($documentTypes as $t) {
    $typesByCategory[$t['category_name']][] = $t;
}

$passportDocTypes = array_values(array_filter($identityDocTypes, fn($t) => stripos($t['name'], 'passport') !== false));
$idDocTypes       = array_values(array_filter($identityDocTypes, fn($t) =>
    stripos($t['name'], 'passport') === false &&
    (stripos($t['name'], 'qid') !== false || preg_match('/\bid\b/i', $t['name']))
));
$oldInput         = app()->session()->getFlash('old_input', []);
$oldStep          = (int) (app()->session()->getFlash('intake_form_step', 1) ?? 1);
?>
<div class="container py-2" style="max-width:860px">

    <!-- Step indicator -->
    <div class="d-flex align-items-start mb-4 step-indicator" id="stepIndicator">
        <?php
        $stepLabels = ['Personal Info','Address & ID','Emergency Contacts','Documents','Review & Submit'];
        $total = count($stepLabels);
        foreach ($stepLabels as $i => $label):
            $num = $i + 1;
        ?>
        <div class="step">
            <div class="step-circle <?= $num === 1 ? 'active' : ''; ?>" id="circle-<?= $num; ?>"><?= $num; ?></div>
            <div class="step-label <?= $num === 1 ? 'active' : ''; ?>" id="label-<?= $num; ?>"><?= e($label); ?></div>
        </div>
        <?php if ($num < $total): ?>
        <div class="step-connector" id="conn-<?= $num; ?>"></div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= e(url('/employee-registration')); ?>" enctype="multipart/form-data" id="intakeForm" novalidate>
        <?= csrf_field(); ?>

        <!-- Dev Mode Banner -->
        <script>
            if (new URLSearchParams(window.location.search).get('dev') === '1') {
                document.write('<div class="alert alert-warning mb-3" style="margin: 1rem 0;"><i class="bi bi-exclamation-circle me-2"></i><strong>DEV MODE ACTIVE:</strong> All validations disabled. Click "Next" to cycle through pages.</div>');
            }
        </script>

        <!-- ═══ Step 1: Personal Information ════════════════════════════════ -->
        <div class="intake-card p-4 wizard-panel active" id="step-1">
            <h5 class="mb-1 fw-semibold"><i class="bi bi-person me-2 text-primary"></i>Personal Information</h5>
            <p class="text-muted small mb-4">Fields marked <span class="text-danger">*</span> are required.</p>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" id="first_name" class="form-control" required autocomplete="given-name">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" autocomplete="additional-name">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" id="last_name" class="form-control" required autocomplete="family-name">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Personal Email <span class="text-danger">*</span></label>
                    <input type="email" name="personal_email" id="personal_email" class="form-control" required autocomplete="email">
                    <div class="form-text">Your personal email, not your work email.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" id="phone" class="form-control" required autocomplete="tel">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Alternate Phone</label>
                    <input type="tel" name="alternate_phone" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" required autocomplete="bday">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Gender <span class="text-danger">*</span></label>
                    <select name="gender" id="gender" class="form-select" required>
                        <option value="">Select</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Marital Status <span class="text-danger">*</span></label>
                    <select name="marital_status" id="marital_status" class="form-select" required>
                        <option value="">Select</option>
                        <option value="single">Single</option>
                        <option value="married">Married</option>
                        <option value="divorced">Divorced</option>
                        <option value="widowed">Widowed</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nationality <span class="text-danger">*</span></label>
                    <select name="nationality" id="nationality" class="form-select" required>
                        <option value="">Select country</option>
                        <?php foreach ($countries as $c): ?>
                        <option value="<?= e($c); ?>"><?= e($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Second Nationality</label>
                    <select name="second_nationality" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($countries as $c): ?>
                        <option value="<?= e($c); ?>"><?= e($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Company <span class="text-danger">*</span></label>
                    <select name="company_id" id="company_id" class="form-select" required>
                        <option value="">— Select company —</option>
                        <?php foreach ($companies as $co): ?>
                        <option value="<?= e((string) $co['id']); ?>"><?= e($co['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Profile Photo <span class="text-muted small">(optional)</span></label>
                    <input type="file" name="profile_photo" id="profile_photo" class="form-control"
                           accept=".jpg,.jpeg,.png">
                    <div class="form-text">JPG or PNG, max 2 MB.</div>
                </div>
            </div>
        </div>

        <!-- ═══ Step 2: Address & Identity ══════════════════════════════════ -->
        <div class="intake-card p-4 wizard-panel" id="step-2">
            <h5 class="mb-1 fw-semibold"><i class="bi bi-house me-2 text-primary"></i>Address &amp; Identity</h5>
            <p class="text-muted small mb-4">Fields marked <span class="text-danger">*</span> are required.</p>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                    <input type="text" name="address_line_1" id="address_line_1" class="form-control" required
                           placeholder="Street address, building, apartment" autocomplete="address-line1">
                </div>
                <div class="col-12">
                    <label class="form-label">Address Line 2</label>
                    <input type="text" name="address_line_2" class="form-control" placeholder="Zone, area (optional)" autocomplete="address-line2">
                </div>

                <div class="col-md-4">
                    <label class="form-label">City <span class="text-danger">*</span></label>
                    <input type="text" name="city" id="city" class="form-control" required autocomplete="address-level2">
                </div>
                <div class="col-md-4">
                    <label class="form-label">State / Region</label>
                    <input type="text" name="state" class="form-control" autocomplete="address-level1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Country <span class="text-danger">*</span></label>
                    <select name="country" id="country" class="form-select" required autocomplete="country-name">
                        <option value="">Select country</option>
                        <?php foreach ($countries as $c): ?>
                        <option value="<?= e($c); ?>"><?= e($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Postal / ZIP Code</label>
                    <input type="text" name="postal_code" class="form-control" autocomplete="postal-code">
                </div>
            </div>

            <hr class="my-4">
            <h6 class="fw-semibold mb-2"><i class="bi bi-shield-lock me-2 text-secondary"></i>Government Identification</h6>
            <div class="alert alert-info py-2 small mb-3">
                <i class="bi bi-lock me-1"></i> ID and passport numbers are encrypted and only visible to authorised HR staff.
            </div>

            <!-- Legacy fields (kept for backward compatibility) -->
            <div class="row g-3 mb-3" style="display: none;">
                <div class="col-md-6">
                    <label class="form-label">National ID Number <span class="text-danger">*</span></label>
                    <input type="text" name="id_number" id="id_number" class="form-control" placeholder="e.g. Qatar ID number">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Passport Number <span class="text-danger">*</span></label>
                    <input type="text" name="passport_number" id="passport_number" class="form-control">
                </div>
            </div>

            <!-- Identifications (Multiple IDs) -->
            <div class="mb-3">
                <label class="form-label fw-semibold mb-2">Identifications <span class="text-danger">*</span></label>
                <p class="text-muted small mb-3">Add at least one form of identification. You can add multiple IDs (e.g., National ID + Passport).</p>

                <div id="identifications-container">
                    <!-- Rows will be populated here via JavaScript -->
                </div>

                <button type="button" id="add-identification-btn" class="btn btn-secondary btn-sm mt-3">
                    <i class="bi bi-plus-circle me-1"></i>Add Another ID
                </button>
            </div>

            <!-- Hidden template for ID type options -->
            <template id="id-type-template">
                <option value="">Select ID Type</option>
                <?php foreach ($identificationTypes ?? [] as $type): ?>
                <option value="<?= (int)$type['id']; ?>"><?= e($type['name']); ?> <?php if ($type['country']): ?>(<?= e($type['country']); ?>)<?php endif; ?></option>
                <?php endforeach; ?>
            </template>

            <script>
                const idRowTemplate = `
                    <div class="identification-row mb-3 p-3" style="border:1px solid #e5e7eb; border-radius:0.375rem; background:#f9fafb">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label small">ID Type <span class="text-danger">*</span></label>
                                <select name="identifications[{INDEX}][type_id]" class="form-select form-select-sm id-type-select" required>
                                    {ID_TYPE_OPTIONS}
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label small">ID Number <span class="text-danger">*</span></label>
                                <input type="text" name="identifications[{INDEX}][number]" class="form-control form-control-sm" required placeholder="Enter ID number">
                            </div>
                        </div>
                        <div class="row g-2 mt-2">
                            <div class="col-md-5">
                                <label class="form-label small">Issue Date (optional)</label>
                                <input type="date" name="identifications[{INDEX}][issue_date]" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small">Expiry Date (optional)</label>
                                <input type="date" name="identifications[{INDEX}][expiry_date]" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">&nbsp;</label>
                                <button type="button" class="btn btn-danger btn-sm w-100 remove-id-btn" {REMOVE_DISABLED}>
                                    <i class="bi bi-trash me-1"></i>Remove
                                </button>
                            </div>
                        </div>
                        <div class="row g-2 mt-2">
                            <div class="col-12">
                                <label class="form-check">
                                    <input type="checkbox" name="identifications[{INDEX}][is_primary]" value="1" class="form-check-input">
                                    <span class="form-check-label small">Mark as primary ID</span>
                                </label>
                            </div>
                        </div>
                    </div>
                `;

                let idCount = 0;

                function getIdTypeOptions() {
                    const template = document.getElementById('id-type-template');
                    return template ? template.innerHTML : '';
                }

                function addIdentificationRow(focus = false) {
                    const container = document.getElementById('identifications-container');
                    const html = idRowTemplate
                        .replace(/{INDEX}/g, idCount)
                        .replace(/{ID_TYPE_OPTIONS}/g, getIdTypeOptions())
                        .replace(/{REMOVE_DISABLED}/g, idCount === 0 ? 'disabled' : '');

                    const div = document.createElement('div');
                    div.innerHTML = html;
                    container.appendChild(div.firstElementChild);

                    const newRow = container.lastElementChild;
                    if (focus) newRow.querySelector('.id-type-select')?.focus();

                    newRow.querySelector('.remove-id-btn')?.addEventListener('click', (e) => {
                        e.preventDefault();
                        newRow.remove();
                    });

                    idCount++;
                }

                // Initialize with one row
                document.addEventListener('DOMContentLoaded', () => {
                    addIdentificationRow();
                    document.getElementById('add-identification-btn')?.addEventListener('click', (e) => {
                        e.preventDefault();
                        addIdentificationRow(true);
                    });
                });
            </script>

            <!-- Joining Date (Optional) -->
            <hr class="my-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Joining Date</label>
                    <input type="date" name="joining_date" id="joining_date" class="form-control">
                    <small class="text-muted">Optional - date when employee joined the organization</small>
                </div>
            </div>
        </div>

        <!-- ═══ Step 3: Emergency Contacts ══════════════════════════════════ -->
        <div class="intake-card p-4 wizard-panel" id="step-3">
            <h5 class="mb-1 fw-semibold"><i class="bi bi-telephone-plus me-2 text-primary"></i>Emergency Contacts</h5>
            <p class="text-muted small mb-4">Please add at least one emergency contact and mark the primary contact.</p>

            <div id="contactRows">
                <div class="contact-row" data-contact-idx="0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold small text-muted">Contact #1</span>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-contact d-none">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="emergency_contacts[0][full_name]" class="form-control form-control-sm contact-name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Relationship <span class="text-danger">*</span></label>
                            <input type="text" name="emergency_contacts[0][relationship]" class="form-control form-control-sm" placeholder="e.g. Spouse, Parent">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Primary?</label>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="emergency_contacts[0][is_primary]" value="1" checked>
                                <label class="form-check-label small">Primary contact</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="emergency_contacts[0][phone]" class="form-control form-control-sm contact-phone">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Alternate Phone</label>
                            <input type="tel" name="emergency_contacts[0][alternate_phone]" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Email</label>
                            <input type="email" name="emergency_contacts[0][email]" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnAddContact">
                <i class="bi bi-plus-circle me-1"></i> Add Another Contact
            </button>
        </div>

        <!-- ═══ Step 4: Documents ════════════════════════════════════════════ -->
        <div class="intake-card p-4 wizard-panel" id="step-4">
            <h5 class="mb-1 fw-semibold"><i class="bi bi-file-earmark-arrow-up me-2 text-primary"></i>Document Uploads</h5>
            <p class="text-muted small mb-3">Max 5 MB per file. Allowed: PDF, JPG, PNG, DOC, DOCX.</p>

            <!-- Required: Passport -->
            <div class="alert alert-warning py-2 small mb-3">
                <i class="bi bi-exclamation-triangle me-1"></i> Passport and National ID documents are <strong>required</strong>.
            </div>

            <div class="intake-card p-3 mb-3" style="border-color:#ffc107 !important">
                <div class="fw-semibold small mb-3 text-warning-emphasis">
                    <i class="bi bi-passport me-1"></i> Passport Document <span class="text-danger">*</span>
                </div>
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label small">Document Type <span class="text-danger">*</span></label>
                        <select name="passport_doc[document_type_id]" id="passportDocType" class="form-select form-select-sm">
                            <option value="">— Select —</option>
                            <?php foreach ($passportDocTypes as $t): ?>
                            <option value="<?= e((string) $t['id']); ?>"
                                    data-requires-expiry="<?= $t['requires_expiry'] ? '1' : '0'; ?>"
                                    selected>
                                <?= e($t['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small">Title</label>
                        <input type="text" name="passport_doc[title]" class="form-control form-control-sm" value="Passport">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Passport Number <span class="text-danger">*</span></label>
                        <input type="text" name="passport_doc[document_number]" id="passportDocNumber" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Issue Date <span class="text-danger">*</span></label>
                        <input type="date" name="passport_doc[issue_date]" id="passportIssueDate" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Expiry Date <span class="text-danger">*</span></label>
                        <input type="date" name="passport_doc[expiry_date]" id="passportExpiryDate" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Upload Passport Copy <span class="text-danger">*</span></label>
                        <input type="file" name="passport_file" id="passportFile" class="form-control form-control-sm"
                               accept=".pdf,.png,.jpg,.jpeg" required>
                    </div>
                </div>
            </div>

            <!-- Required: National ID -->
            <div class="intake-card p-3 mb-3" style="border-color:#ffc107 !important">
                <div class="fw-semibold small mb-3 text-warning-emphasis">
                    <i class="bi bi-card-heading me-1"></i> National ID Document <span class="text-danger">*</span>
                </div>
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label small">ID Type <span class="text-danger">*</span></label>
                        <select name="id_doc[id_type_name]" id="idDocType" class="form-select form-select-sm" required>
                            <option value="">— Select ID Type —</option>
                            <?php foreach ($identificationTypes ?? [] as $t): ?>
                            <option value="<?= e($t['name']); ?>">
                                <?= e($t['name']); ?> <?php if ($t['country']): ?>(<?= e($t['country']); ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small">Title</label>
                        <input type="text" name="id_doc[title]" class="form-control form-control-sm" value="National ID">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">ID Number <span class="text-danger">*</span></label>
                        <input type="text" name="id_doc[document_number]" id="idDocNumber" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Issue Date <span class="text-danger">*</span></label>
                        <input type="date" name="id_doc[issue_date]" id="idIssueDate" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Expiry Date <span class="text-danger">*</span></label>
                        <input type="date" name="id_doc[expiry_date]" id="idExpiryDate" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Upload ID Copy <span class="text-danger">*</span></label>
                        <input type="file" name="id_file" id="idFile" class="form-control form-control-sm"
                               accept=".pdf,.png,.jpg,.jpeg" required>
                    </div>
                </div>
            </div>

            <!-- Optional additional documents -->
            <hr class="my-3">
            <p class="small fw-semibold mb-2"><i class="bi bi-plus-circle me-1 text-secondary"></i>Additional Documents <span class="text-muted fw-normal">(optional)</span></p>

            <div id="docRows">
                <div class="doc-row" data-doc-idx="0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold small text-muted">Document #1</span>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-doc d-none">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label small">Document Type</label>
                            <select name="documents[0][document_type_id]" class="form-select form-select-sm doc-type-select">
                                <option value="">— Select type —</option>
                                <?php foreach ($typesByCategory as $catName => $types): ?>
                                <optgroup label="<?= e($catName); ?>">
                                    <?php foreach ($types as $t): ?>
                                    <option value="<?= e((string) $t['id']); ?>"
                                            data-requires-expiry="<?= $t['requires_expiry'] ? '1' : '0'; ?>">
                                        <?= e($t['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small">Document Title</label>
                            <input type="text" name="documents[0][title]" class="form-control form-control-sm doc-title" placeholder="Auto-filled from type, or enter manually">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Document Number</label>
                            <input type="text" name="documents[0][document_number]" class="form-control form-control-sm" placeholder="Optional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small doc-issue-label">Issue Date</label>
                            <input type="date" name="documents[0][issue_date]" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small doc-expiry-label">Expiry Date</label>
                            <input type="date" name="documents[0][expiry_date]" class="form-control form-control-sm doc-expiry">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">File</label>
                            <input type="file" name="documents[0]" class="form-control form-control-sm doc-file"
                                   accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnAddDoc">
                <i class="bi bi-plus-circle me-1"></i> Add Another Document
            </button>
        </div>

        <!-- ═══ Step 5: Review & Submit ══════════════════════════════════════ -->
        <div class="intake-card p-4 wizard-panel" id="step-5">
            <h5 class="mb-1 fw-semibold"><i class="bi bi-check2-circle me-2 text-primary"></i>Review &amp; Submit</h5>
            <p class="text-muted small mb-4">Please review your information before submitting. You will not be able to edit after submission.</p>

            <div id="reviewContent"></div>

            <div class="alert alert-warning small mt-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                By submitting, you confirm that all information provided is accurate and complete.
                Your submission will be reviewed by the HR team before your employee record is created.
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <button type="button" class="btn btn-outline-secondary" id="btnPrev5">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </button>
                <button type="submit" class="btn btn-primary btn-lg px-5" id="btnSubmit">
                    <i class="bi bi-send me-2"></i>Submit Form
                </button>
            </div>
        </div>

        <!-- Shared Prev/Next nav (steps 1-4) -->
        <div class="d-flex justify-content-between mt-3" id="wizardNav">
            <button type="button" class="btn btn-outline-secondary" id="btnPrev" style="visibility:hidden">
                <i class="bi bi-arrow-left me-1"></i> Previous
            </button>
            <button type="button" class="btn btn-primary" id="btnNext">
                Next <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </form>

</div>

<script>
(function () {
    localStorage.removeItem('intakeFormData');
    localStorage.removeItem('intakeCurrentStep');

    const TOTAL_STEPS = 5;
    const SERVER_OLD_INPUT = <?= json_encode($oldInput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const SERVER_OLD_STEP = <?= (int) max(1, min(5, $oldStep)); ?>;
    let current = 1;

    function showStep(n) {
        document.querySelectorAll('.wizard-panel').forEach(function (el, i) {
            el.classList.toggle('active', i + 1 === n);
        });
        for (let i = 1; i <= TOTAL_STEPS; i++) {
            var circle = document.getElementById('circle-' + i);
            var label  = document.getElementById('label-' + i);
            circle.classList.remove('active', 'done');
            label.classList.remove('active');
            if (i < n)        { circle.classList.add('done'); circle.innerHTML = '<i class="bi bi-check-lg"></i>'; }
            else if (i === n) { circle.classList.add('active'); circle.textContent = i; label.classList.add('active'); }
            else              { circle.textContent = i; }
        }
        for (var i = 1; i < TOTAL_STEPS; i++) {
            var conn = document.getElementById('conn-' + i);
            if (conn) conn.classList.toggle('done', i < n);
        }
        var nav  = document.getElementById('wizardNav');
        var prev = document.getElementById('btnPrev');
        nav.style.display = n < TOTAL_STEPS ? '' : 'none';
        prev.style.visibility = n > 1 ? 'visible' : 'hidden';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        current = n;
    }

    function val(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function showError(msg) {
        var existing = document.getElementById('wizardError');
        if (existing) existing.remove();
        var div = document.createElement('div');
        div.id        = 'wizardError';
        div.className = 'alert alert-danger alert-dismissible mt-3';
        div.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>' + msg +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        var panel = document.querySelector('.wizard-panel.active');
        if (panel) panel.appendChild(div);
    }

    function validateStep(n) {
        // Dev mode: skip all validation when ?dev=1
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('dev') === '1') {
            return true;
        }

        if (n === 1) {
            if (!val('first_name'))     { showError('First name is required.');        return false; }
            if (!val('last_name'))      { showError('Last name is required.');         return false; }
            var emailEl = document.getElementById('personal_email');
            if (!emailEl || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())) {
                showError('A valid personal email is required.'); return false;
            }
            if (!val('phone'))          { showError('Phone number is required.');      return false; }
            if (!val('date_of_birth'))  { showError('Date of birth is required.');     return false; }
            if (!val('gender'))         { showError('Gender is required.');            return false; }
            if (!val('marital_status')) { showError('Marital status is required.');    return false; }
            if (!val('nationality'))    { showError('Nationality is required.');       return false; }
            if (!val('company_id'))     { showError('Please select a company.');       return false; }
        }
        if (n === 2) {
            if (!val('address_line_1')) { showError('Address line 1 is required.');   return false; }
            if (!val('city'))           { showError('City is required.');              return false; }
            if (!val('country'))        { showError('Country is required.');           return false; }

            // Check for at least one identification
            var idRows = document.querySelectorAll('.identification-row');
            if (idRows.length === 0) { showError('At least one form of identification is required.'); return false; }
            var hasValidId = false;
            idRows.forEach(function(row) {
                var typeSelect = row.querySelector('.id-type-select');
                var numberInput = row.querySelector('input[name*="[number]"]');
                if (typeSelect && typeSelect.value && numberInput && numberInput.value.trim()) {
                    hasValidId = true;
                }
            });
            if (!hasValidId) { showError('Please provide at least one ID type and number.'); return false; }
        }
        if (n === 3) {
            var firstRow = document.querySelector('#contactRows .contact-row');
            if (firstRow) {
                var name  = firstRow.querySelector('.contact-name');
                var phone = firstRow.querySelector('.contact-phone');
                if ((!name || !name.value.trim()) || (!phone || !phone.value.trim())) {
                    showError('Please provide a name and phone for the first emergency contact.');
                    return false;
                }
            }
        }
        if (n === 4) {
            if (!val('passportDocNumber'))  { showError('Passport document number is required.');  return false; }
            if (!val('passportIssueDate'))  { showError('Passport issue date is required.');       return false; }
            if (!val('passportExpiryDate')) { showError('Passport expiry date is required.');      return false; }
            var pFile = document.getElementById('passportFile');
            if (!pFile || !pFile.files.length) { showError('Please upload a copy of your passport.'); return false; }

            var idTypeSelect = document.getElementById('idDocType');
            if (!idTypeSelect || !idTypeSelect.value) { showError('National ID type is required.'); return false; }
            if (!val('idDocNumber'))  { showError('National ID document number is required.');  return false; }
            if (!val('idIssueDate'))  { showError('National ID issue date is required.');       return false; }
            if (!val('idExpiryDate')) { showError('National ID expiry date is required.');      return false; }
            var iFile = document.getElementById('idFile');
            if (!iFile || !iFile.files.length) { showError('Please upload a copy of your National ID.'); return false; }
        }
        return true;
    }

    function buildReview() {
        function getVal(name) {
            var el = document.querySelector('[name="' + name + '"]');
            return el ? el.value.trim() : '';
        }
        function getSelText(name) {
            var el = document.querySelector('[name="' + name + '"]');
            return el && el.selectedIndex > 0 ? el.options[el.selectedIndex].text : '';
        }
        function esc(str) {
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(str || ''));
            return d.innerHTML;
        }
        function section(title, rows) {
            var html = '<div class="mb-3"><h6 class="fw-semibold border-bottom pb-1 mb-2">' + esc(title) + '</h6><dl class="row mb-0 small">';
            rows.forEach(function(r) {
                html += '<dt class="col-sm-4 text-muted">' + esc(r[0]) + '</dt><dd class="col-sm-8">' + esc(r[1] || '—') + '</dd>';
            });
            return html + '</dl></div>';
        }

        var html = '';

        html += section('Personal Information', [
            ['Full Name', [getVal('first_name'), getVal('middle_name'), getVal('last_name')].filter(Boolean).join(' ')],
            ['Email', getVal('personal_email')],
            ['Phone', getVal('phone')],
            ['Date of Birth', getVal('date_of_birth')],
            ['Gender', getSelText('gender')],
            ['Marital Status', getSelText('marital_status')],
            ['Nationality', getSelText('nationality')],
            ['Company', getSelText('company_id')],
        ]);

        var addrParts = [getVal('address_line_1'), getVal('address_line_2'), getVal('city'), getVal('state'), getSelText('country'), getVal('postal_code')].filter(Boolean);
        // Build identifications display
        var idRows = document.querySelectorAll('.identification-row');
        var idLines = [];
        var idCount = 0;
        idRows.forEach(function(row) {
            var typeSelect = row.querySelector('.id-type-select');
            var numberInput = row.querySelector('input[name*="[number]"]');
            var typeName = typeSelect && typeSelect.options[typeSelect.selectedIndex] ? typeSelect.options[typeSelect.selectedIndex].text : '';
            var number = numberInput && numberInput.value.trim() ? '•••• (provided)' : '';
            if (typeName && number) {
                idCount++;
                idLines.push([typeName, number]);
            }
        });

        var addrIdLines = [['Address', addrParts.join(', ') || '—']];
        if (idLines.length > 0) {
            addrIdLines = addrIdLines.concat(idLines);
        }
        html += section('Address & Identity', addrIdLines);

        // Contacts
        var contactRows = document.querySelectorAll('#contactRows .contact-row');
        var contactHtml = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Name</th><th>Relationship</th><th>Phone</th><th>Primary</th></tr></thead><tbody>';
        var hasContact = false;
        contactRows.forEach(function(row) {
            var name  = row.querySelector('.contact-name')  ? row.querySelector('.contact-name').value.trim()  : '';
            var rel   = row.querySelector('[name*="relationship"]') ? row.querySelector('[name*="relationship"]').value.trim() : '';
            var phone = row.querySelector('.contact-phone') ? row.querySelector('.contact-phone').value.trim() : '';
            var isPri = row.querySelector('[name*="is_primary"]') ? row.querySelector('[name*="is_primary"]').checked : false;
            if (name || phone) {
                hasContact = true;
                contactHtml += '<tr><td>' + esc(name) + '</td><td>' + esc(rel) + '</td><td>' + esc(phone) + '</td><td>' + (isPri ? '<i class="bi bi-check-circle-fill text-success"></i>' : '') + '</td></tr>';
            }
        });
        contactHtml += '</tbody></table></div>';
        html += '<div class="mb-3"><h6 class="fw-semibold border-bottom pb-1 mb-2">Emergency Contacts</h6>' + (hasContact ? contactHtml : '<p class="text-muted small">None provided.</p>') + '</div>';

        // Required docs
        var pFile = document.getElementById('passportFile');
        var iFile = document.getElementById('idFile');
        html += section('Required Documents', [
            ['Passport', pFile && pFile.files.length ? pFile.files[0].name : '—'],
            ['National ID', iFile && iFile.files.length ? iFile.files[0].name : '—'],
        ]);

        // Optional docs
        var docRows = document.querySelectorAll('#docRows .doc-row');
        var docLines = [];
        docRows.forEach(function(row) {
            var fileEl = row.querySelector('.doc-file');
            if (fileEl && fileEl.files.length) {
                var typeEl = row.querySelector('.doc-type-select');
                docLines.push([typeEl && typeEl.selectedIndex > 0 ? typeEl.options[typeEl.selectedIndex].text : 'Other', fileEl.files[0].name]);
            }
        });
        if (docLines.length) {
            html += section('Additional Documents', docLines);
        }

        document.getElementById('reviewContent').innerHTML = html;
    }

    function normalizedRows(value) {
        if (Array.isArray(value)) return value;
        if (!value || typeof value !== 'object') return [];
        return Object.keys(value)
            .sort(function(a, b) { return Number(a) - Number(b); })
            .map(function(key) { return value[key]; });
    }

    function setFieldValue(name, value) {
        var elements = document.querySelectorAll('[name="' + name + '"]');
        if (!elements.length) return;
        elements.forEach(function(el) {
            if (el.type === 'file') return;
            if (el.type === 'checkbox' || el.type === 'radio') {
                if (Array.isArray(value)) {
                    el.checked = value.includes(el.value || 'on');
                } else {
                    el.checked = value === true || value === 1 || value === '1' || value === (el.value || 'on');
                }
                return;
            }
            el.value = value ?? '';
        });
    }

    function syncIdentificationButtons() {
        var rows = document.querySelectorAll('.identification-row');
        rows.forEach(function(row, idx) {
            var btn = row.querySelector('.remove-id-btn');
            if (btn) {
                btn.disabled = rows.length === 1 && idx === 0;
            }
        });
    }

    function ensureContactRows(count) {
        var button = document.getElementById('btnAddContact');
        while (document.querySelectorAll('#contactRows .contact-row').length < count) {
            button.click();
        }
    }

    function ensureDocumentRows(count) {
        var button = document.getElementById('btnAddDoc');
        while (document.querySelectorAll('#docRows .doc-row').length < count) {
            button.click();
        }
    }

    function ensureIdentificationRows(count) {
        while (document.querySelectorAll('.identification-row').length < count) {
            addIdentificationRow();
        }
        syncIdentificationButtons();
    }

    function restoreStructuredRows(source) {
        if (!source || typeof source !== 'object') return;

        var ids = normalizedRows(source.identifications).filter(function(item) {
            if (!item || typeof item !== 'object') return false;
            return Boolean(item.type_id || item.number || item.issue_date || item.expiry_date || item.is_primary);
        });
        if (ids.length) {
            var idContainer = document.getElementById('identifications-container');
            if (idContainer) {
                idContainer.innerHTML = '';
                if (typeof idCount !== 'undefined') idCount = 0;
            }
            ensureIdentificationRows(ids.length);
            ids.forEach(function(item, idx) {
                if (!item || typeof item !== 'object') return;
                setFieldValue('identifications[' + idx + '][type_id]', item.type_id || '');
                setFieldValue('identifications[' + idx + '][number]', item.number || '');
                setFieldValue('identifications[' + idx + '][issue_date]', item.issue_date || '');
                setFieldValue('identifications[' + idx + '][expiry_date]', item.expiry_date || '');
                setFieldValue('identifications[' + idx + '][is_primary]', item.is_primary ? '1' : '');
            });
        }

        var contacts = normalizedRows(source.emergency_contacts);
        if (contacts.length) {
            ensureContactRows(contacts.length);
            contacts.forEach(function(item, idx) {
                if (!item || typeof item !== 'object') return;
                setFieldValue('emergency_contacts[' + idx + '][full_name]', item.full_name || '');
                setFieldValue('emergency_contacts[' + idx + '][relationship]', item.relationship || '');
                setFieldValue('emergency_contacts[' + idx + '][phone]', item.phone || '');
                setFieldValue('emergency_contacts[' + idx + '][alternate_phone]', item.alternate_phone || '');
                setFieldValue('emergency_contacts[' + idx + '][email]', item.email || '');
                setFieldValue('emergency_contacts[' + idx + '][is_primary]', item.is_primary ? '1' : '');
            });
        }

        var docs = normalizedRows(source.documents);
        if (docs.length) {
            ensureDocumentRows(docs.length);
            docs.forEach(function(item, idx) {
                if (!item || typeof item !== 'object') return;
                setFieldValue('documents[' + idx + '][document_type_id]', item.document_type_id || '');
                setFieldValue('documents[' + idx + '][title]', item.title || '');
                setFieldValue('documents[' + idx + '][document_number]', item.document_number || '');
                setFieldValue('documents[' + idx + '][issue_date]', item.issue_date || '');
                setFieldValue('documents[' + idx + '][expiry_date]', item.expiry_date || '');
            });
        }
    }

    function restoreFormState() {
        restoreStructuredRows(SERVER_OLD_INPUT);

        var hasServerOldInput = SERVER_OLD_INPUT && Object.keys(SERVER_OLD_INPUT).length > 0;
        if (hasServerOldInput) {
            Object.keys(SERVER_OLD_INPUT).forEach(function(key) {
                var value = SERVER_OLD_INPUT[key];
                if (value !== null && typeof value !== 'object') {
                    setFieldValue(key, value);
                }
            });
        }
    }

    function getInitialStep() {
        if (SERVER_OLD_STEP > 1) {
            return SERVER_OLD_STEP;
        }

        return 1;
    }

    // Button events
    document.getElementById('btnNext').addEventListener('click', function () {
        if (!validateStep(current)) return;
        if (current < TOTAL_STEPS - 1) showStep(current + 1);
        else if (current === TOTAL_STEPS - 1) { buildReview(); showStep(TOTAL_STEPS); }
    });
    document.getElementById('btnPrev').addEventListener('click', function () { if (current > 1) showStep(current - 1); });
    document.getElementById('btnPrev5').addEventListener('click', function () { showStep(TOTAL_STEPS - 1); });

    // Dynamic contacts
    var contactIdx = 1;
    document.getElementById('btnAddContact').addEventListener('click', function () {
        var template = document.querySelector('#contactRows .contact-row');
        var clone    = template.cloneNode(true);
        var idx      = contactIdx++;
        clone.dataset.contactIdx = idx;
        clone.querySelector('span').textContent = 'Contact #' + (idx + 1);
        clone.querySelector('.remove-contact').classList.remove('d-none');
        clone.querySelectorAll('input, select').forEach(function (el) {
            el.name  = el.name.replace(/\[\d+\]/, '[' + idx + ']');
            el.value = '';
            if (el.type === 'checkbox') el.checked = false;
        });
        document.getElementById('contactRows').appendChild(clone);
    });
    document.getElementById('contactRows').addEventListener('click', function (e) {
        if (e.target.closest('.remove-contact')) e.target.closest('.contact-row').remove();
    });

    // Dynamic optional docs
    var docIdx = 1;
    document.getElementById('btnAddDoc').addEventListener('click', function () {
        var template = document.querySelector('#docRows .doc-row');
        var clone    = template.cloneNode(true);
        var idx      = docIdx++;
        clone.dataset.docIdx = idx;
        clone.querySelector('span').textContent = 'Document #' + (idx + 1);
        clone.querySelector('.remove-doc').classList.remove('d-none');
        clone.querySelectorAll('input, select').forEach(function (el) {
            el.name  = el.name.replace(/\[\d+\]/, '[' + idx + ']');
            el.value = '';
        });
        document.getElementById('docRows').appendChild(clone);
    });
    document.getElementById('docRows').addEventListener('click', function (e) {
        if (e.target.closest('.remove-doc')) e.target.closest('.doc-row').remove();
    });

    // Auto-fill title + toggle expiry for optional docs
    document.getElementById('docRows').addEventListener('change', function (e) {
        if (!e.target.classList.contains('doc-type-select')) return;
        var row    = e.target.closest('.doc-row');
        var titleEl= row.querySelector('.doc-title');
        var expEl  = row.querySelector('.doc-expiry');
        var expLbl = row.querySelector('.doc-expiry-label');
        var opt    = e.target.options[e.target.selectedIndex];
        if (e.target.selectedIndex > 0 && (!titleEl.value || titleEl.dataset.autofilled === '1')) {
            titleEl.value = opt.text; titleEl.dataset.autofilled = '1';
        }
        var reqExp = opt.dataset.requiresExpiry === '1';
        expEl.required = reqExp;
        expLbl.textContent = 'Expiry Date' + (reqExp ? ' *' : '');
    });
    document.getElementById('docRows').addEventListener('input', function (e) {
        if (e.target.classList.contains('doc-title')) e.target.dataset.autofilled = '0';
    });

    restoreFormState();
    showStep(getInitialStep());

    // File selected feedback
    function attachFilePreview(inputEl) {
        inputEl.addEventListener('change', function () {
            var existing = this.parentElement.querySelector('.file-chosen');
            if (existing) existing.remove();
            if (this.files && this.files.length) {
                var badge = document.createElement('div');
                badge.className = 'file-chosen mt-1 d-flex align-items-center gap-1 small text-success';
                badge.innerHTML = '<i class="bi bi-paperclip"></i><span class="text-truncate" style="max-width:320px">' +
                    this.files[0].name + ' (' + (this.files[0].size / 1024).toFixed(0) + ' KB)</span>';
                this.parentElement.appendChild(badge);
            }
        });
    }
    document.querySelectorAll('input[type="file"]').forEach(attachFilePreview);
    // Also attach to dynamically added doc rows
    document.getElementById('docRows').addEventListener('change', function (e) {
        if (e.target.type === 'file' && !e.target._previewAttached) {
            e.target._previewAttached = true;
            attachFilePreview(e.target);
            e.target.dispatchEvent(new Event('change'));
        }
    });

    // Submit loading state
    document.getElementById('intakeForm').addEventListener('submit', function () {
        var btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Submitting…';
    });
})();
</script>
