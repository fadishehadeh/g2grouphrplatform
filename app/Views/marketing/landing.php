<?php declare(strict_types=1); ?>
<header class="marketing-header">
    <div class="container marketing-nav">
        <a href="<?= e(url('/')); ?>" class="marketing-brand">
            <span class="marketing-brand-mark">P</span>
            <span>
                <strong><?= e($productName); ?></strong>
                <small>HR operations platform</small>
            </span>
        </a>
        <div class="marketing-nav-actions">
            <?php if ($isAuthenticated): ?>
                <a href="<?= e($dashboardUrl); ?>" class="btn btn-sm btn-light">Open Dashboard</a>
            <?php endif; ?>
            <a href="#book-demo" class="btn btn-sm btn-primary">Book a Demo</a>
            <a href="<?= e($loginUrl); ?>" class="btn btn-sm btn-outline-light">Staff Login</a>
        </div>
    </div>
</header>

<main>
    <section class="marketing-hero">
        <div class="container">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-lg-6">
                    <span class="marketing-kicker">Implementation-led HR platform</span>
                    <h1 class="marketing-title"><?= e($productName); ?> gives HR leaders one operational system to run people workflows with clarity.</h1>
                    <p class="marketing-copy">
                        Centralize employee records, approvals, leave, onboarding, offboarding, documents, and hiring in one structured workspace.
                        <?= e($productName); ?> is delivered through a guided rollout, so the setup matches your structure, access model, and internal process expectations.
                    </p>
                    <div class="marketing-hero-actions">
                        <a href="#book-demo" class="btn btn-primary btn-lg">Book a Demo</a>
                        <a href="#capabilities" class="btn btn-outline-light btn-lg">Explore the Platform</a>
                    </div>
                    <div class="marketing-proof-grid">
                        <div class="marketing-proof-card">
                            <strong>One source of truth</strong>
                            <span>Employee data, approvals, documents, and reporting in one operating layer.</span>
                        </div>
                        <div class="marketing-proof-card">
                            <strong>Built for HR leadership</strong>
                            <span>Visibility across requests, workloads, compliance pressure, and team structure.</span>
                        </div>
                        <div class="marketing-proof-card">
                            <strong>Guided rollout</strong>
                            <span>Configured with your process instead of forcing a generic self-serve setup.</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="marketing-showcase-shell">
                        <div class="marketing-showcase-orbit marketing-showcase-orbit-one"></div>
                        <div class="marketing-showcase-orbit marketing-showcase-orbit-two"></div>
                        <div class="marketing-showcase">
                            <div class="marketing-showcase-topbar">
                                <span class="marketing-showcase-pill">HR Command Center</span>
                                <span class="marketing-showcase-badge">Live workflow view</span>
                            </div>
                            <div class="marketing-showcase-frame">
                                <aside class="marketing-ui-sidebar">
                                    <div class="marketing-ui-brand">
                                        <span><?= e(substr($productName, 0, 1)); ?></span>
                                        <strong><?= e($productName); ?></strong>
                                    </div>
                                    <div class="marketing-ui-nav">
                                        <span class="active">Overview</span>
                                        <span>Employees</span>
                                        <span>Leave</span>
                                        <span>Documents</span>
                                        <span>Recruitment</span>
                                    </div>
                                </aside>
                                <div class="marketing-ui-canvas">
                                    <div class="marketing-ui-summary">
                                        <article>
                                            <small>Active employees</small>
                                            <strong>248</strong>
                                        </article>
                                        <article>
                                            <small>Pending approvals</small>
                                            <strong>19</strong>
                                        </article>
                                        <article>
                                            <small>Expiring documents</small>
                                            <strong>07</strong>
                                        </article>
                                    </div>
                                    <div class="marketing-ui-grid">
                                        <div class="marketing-ui-panel marketing-ui-panel-large">
                                            <div class="marketing-ui-panel-head">
                                                <strong>Today’s HR workload</strong>
                                                <span>Updated now</span>
                                            </div>
                                            <div class="marketing-ui-bars">
                                                <div><span>Leave queue</span><i style="width: 82%;"></i></div>
                                                <div><span>Onboarding</span><i style="width: 54%;"></i></div>
                                                <div><span>Recruitment</span><i style="width: 66%;"></i></div>
                                            </div>
                                        </div>
                                        <div class="marketing-ui-panel">
                                            <div class="marketing-ui-panel-head">
                                                <strong>Escalations</strong>
                                            </div>
                                            <ul class="marketing-ui-list">
                                                <li><span>Medical leave approval</span><strong>Manager</strong></li>
                                                <li><span>Passport renewal</span><strong>48h</strong></li>
                                                <li><span>Offer pipeline</span><strong>HR</strong></li>
                                            </ul>
                                        </div>
                                        <div class="marketing-ui-panel">
                                            <div class="marketing-ui-panel-head">
                                                <strong>Hiring stream</strong>
                                            </div>
                                            <div class="marketing-ui-tags">
                                                <span>New applicants</span>
                                                <span>Interviewing</span>
                                                <span>Offers sent</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section marketing-proof-band">
        <div class="container">
            <div class="marketing-proof-strip">
                <div>
                    <small>Operational scope</small>
                    <strong>Employee lifecycle, leave, hiring, compliance, and approvals in one system.</strong>
                </div>
                <div>
                    <small>Implementation model</small>
                    <strong>Guided setup aligned to your organization, not a generic self-serve template.</strong>
                </div>
                <div>
                    <small>Decision support</small>
                    <strong>Built for leaders who need better control, visibility, and cleaner execution.</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section" id="capabilities">
        <div class="container">
            <div class="marketing-section-heading">
                <span>Core capabilities</span>
                <h2>Everything HR leaders need to keep operations structured and moving.</h2>
                <p><?= e($productName); ?> is designed to reduce admin drag, tighten control, and connect the workflows that usually live across spreadsheets, inboxes, and disconnected tools.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-xl-4">
                    <article class="marketing-module-card">
                        <div class="marketing-module-icon"><i class="bi bi-person-badge"></i></div>
                        <h3>Employee records with context</h3>
                        <p>Keep structure, reporting lines, emergency contacts, employment details, and history in one controlled record.</p>
                    </article>
                </div>
                <div class="col-md-6 col-xl-4">
                    <article class="marketing-module-card">
                        <div class="marketing-module-icon"><i class="bi bi-check2-square"></i></div>
                        <h3>Approval visibility</h3>
                        <p>Track leave, approvals, balances, policies, escalation paths, and workflow ownership without manual chasing.</p>
                    </article>
                </div>
                <div class="col-md-6 col-xl-4">
                    <article class="marketing-module-card">
                        <div class="marketing-module-icon"><i class="bi bi-file-earmark-lock2"></i></div>
                        <h3>Compliance and document control</h3>
                        <p>Monitor employee files, expiries, secure access, uploads, and document ownership from one place.</p>
                    </article>
                </div>
                <div class="col-md-6 col-xl-4">
                    <article class="marketing-module-card">
                        <div class="marketing-module-icon"><i class="bi bi-person-plus"></i></div>
                        <h3>Smoother handovers</h3>
                        <p>Run onboarding and offboarding through task-based workflows that reduce missed steps and loose ends.</p>
                    </article>
                </div>
                <div class="col-md-6 col-xl-4">
                    <article class="marketing-module-card">
                        <div class="marketing-module-icon"><i class="bi bi-megaphone"></i></div>
                        <h3>Internal HR communications</h3>
                        <p>Keep announcements, notifications, and employee-facing request flows inside the same operating environment.</p>
                    </article>
                </div>
                <div class="col-md-6 col-xl-4">
                    <article class="marketing-module-card">
                        <div class="marketing-module-icon"><i class="bi bi-graph-up-arrow"></i></div>
                        <h3>Reporting and connected hiring</h3>
                        <p>See headcount and audit insight while linking internal HR operations with your jobs and applicant flow.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section marketing-section-alt">
        <div class="container">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-6">
                    <div class="marketing-story-card">
                        <span>Why HR leaders buy</span>
                        <h2>More control, less fragmentation, and a clearer view of what HR is carrying.</h2>
                        <p>
                            HR teams do not need another tool that looks polished but still leaves operational work scattered.
                            <?= e($productName); ?> is built for organizations that want one system to anchor daily execution and leadership visibility.
                        </p>
                        <ul class="marketing-checklist">
                            <li>Replace spreadsheet trackers, inbox-driven approvals, and disconnected employee records</li>
                            <li>Give leadership a cleaner picture of pending work, bottlenecks, and compliance exposure</li>
                            <li>Connect hiring, onboarding, employee support, and exits in one operational rhythm</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="marketing-story-card marketing-story-card-accent">
                        <span>Rollout approach</span>
                        <h2>Delivered through direct consultation and tailored implementation.</h2>
                        <p>
                            The platform is introduced through a guided process, so your org structure, permissions, branding, and workflows can be aligned properly from the start.
                            That creates a more intentional rollout than a self-serve setup that leaves HR to figure it out alone.
                        </p>
                        <ul class="marketing-checklist">
                            <li>Direct walkthrough to assess fit and implementation scope</li>
                            <li>Configuration aligned to your approval model and operational structure</li>
                            <li>Clear handoff from product interest to guided rollout</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section marketing-preview-band">
        <div class="container">
            <div class="marketing-preview-grid">
                <div class="marketing-preview-copy">
                    <span>Platform preview</span>
                    <h2>A product surface that feels operational, not ornamental.</h2>
                    <p>The experience is designed around the reality of HR work: queues, exceptions, structure, employee data, and handoffs between teams.</p>
                </div>
                <div class="marketing-preview-cards">
                    <article class="marketing-preview-card">
                        <small>Approvals</small>
                        <strong>Manager and HR queues stay visible instead of disappearing into email.</strong>
                    </article>
                    <article class="marketing-preview-card">
                        <small>Employee data</small>
                        <strong>Role, reporting line, documents, status, and lifecycle history stay connected.</strong>
                    </article>
                    <article class="marketing-preview-card">
                        <small>Recruitment</small>
                        <strong>Jobs, careers, and applicant flow stay linked to the HR operating model.</strong>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section marketing-cta" id="book-demo">
        <div class="container">
            <div class="marketing-cta-panel">
                <div class="marketing-cta-copy">
                    <span>Book a demo</span>
                    <h2>See how <?= e($productName); ?> could run HR operations more cleanly in your organization.</h2>
                    <p>Send a short inquiry and we’ll follow up to schedule a walkthrough.</p>
                    <div class="marketing-cta-notes">
                        <div>
                            <strong>What we’ll cover</strong>
                            <span>Workflow fit, org setup, access model, and rollout scope.</span>
                        </div>
                        <div>
                            <strong>Who this is for</strong>
                            <span>HR leaders and operations stakeholders evaluating a more structured system.</span>
                        </div>
                    </div>
                </div>
                <div class="marketing-form-card">
                    <form method="post" action="<?= e(url('/demo-request')); ?>" class="marketing-form" novalidate>
                        <?= csrf_field(); ?>
                        <div class="marketing-form-grid">
                            <div>
                                <label class="form-label" for="demoName">Your name</label>
                                <input id="demoName" type="text" name="name" class="form-control" value="<?= e((string) old('name', '')); ?>" maxlength="120" required>
                            </div>
                            <div>
                                <label class="form-label" for="demoEmail">Work email</label>
                                <input id="demoEmail" type="email" name="work_email" class="form-control" value="<?= e((string) old('work_email', '')); ?>" maxlength="190" required>
                            </div>
                            <div>
                                <label class="form-label" for="demoCompany">Company</label>
                                <input id="demoCompany" type="text" name="company" class="form-control" value="<?= e((string) old('company', '')); ?>" maxlength="150" required>
                            </div>
                            <div>
                                <label class="form-label" for="demoTeamSize">Team size</label>
                                <input id="demoTeamSize" type="text" name="team_size" class="form-control" value="<?= e((string) old('team_size', '')); ?>" maxlength="60" placeholder="e.g. 50-200 employees" required>
                            </div>
                            <div class="marketing-form-full">
                                <label class="form-label" for="demoMessage">What are you trying to improve?</label>
                                <textarea id="demoMessage" name="message" class="form-control" rows="5" maxlength="2000" required><?= e((string) old('message', '')); ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">Submit Demo Request</button>
                        <?php if ($contactEmail !== ''): ?>
                            <p class="marketing-form-alt">Prefer email? <a href="<?= e($mailTo ?? ('mailto:' . $contactEmail)); ?>"><?= e($contactEmail); ?></a></p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
