<?php
$footer_year = date('Y');
?>
<style>
    .tpo-footer {
        width: 100%;
        margin-top: 36px;
        background: #101828;
        color: #e5edf7;
        border-top: 4px solid #667eea;
        box-shadow: 0 -8px 24px rgba(16, 24, 40, 0.08);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .tpo-footer * {
        box-sizing: border-box;
    }

    .tpo-footer-inner {
        max-width: 1180px;
        margin: 0 auto;
        padding: 34px 24px 20px;
    }

    .tpo-footer-grid {
        display: grid;
        grid-template-columns: 1.3fr 1fr 1fr 1fr;
        gap: 26px;
    }

    .tpo-footer h2,
    .tpo-footer h3 {
        color: #ffffff;
        margin: 0 0 12px;
        line-height: 1.25;
    }

    .tpo-footer h2 {
        font-size: 1.35rem;
    }

    .tpo-footer h3 {
        font-size: 0.98rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .tpo-footer p,
    .tpo-footer li,
    .tpo-footer a {
        color: #c8d3e1;
        font-size: 0.9rem;
        line-height: 1.7;
    }

    .tpo-footer p {
        margin: 0;
    }

    .tpo-footer ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .tpo-footer a {
        text-decoration: none;
    }

    .tpo-footer a:hover {
        color: #ffffff;
        text-decoration: underline;
    }

    .tpo-footer-badge {
        display: inline-block;
        margin-top: 14px;
        padding: 7px 10px;
        border: 1px solid rgba(255, 255, 255, 0.16);
        border-radius: 6px;
        color: #ffffff;
        font-size: 0.78rem;
        background: rgba(102, 126, 234, 0.18);
    }

    .tpo-footer-bottom {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        margin-top: 28px;
        padding-top: 18px;
        border-top: 1px solid rgba(255, 255, 255, 0.14);
        color: #a8b4c4;
        font-size: 0.82rem;
        line-height: 1.6;
    }

    @media (max-width: 960px) {
        .tpo-footer-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 640px) {
        .tpo-footer-inner {
            padding: 28px 18px 18px;
        }

        .tpo-footer-grid {
            grid-template-columns: 1fr;
        }

        .tpo-footer-bottom {
            flex-direction: column;
        }
    }
</style>

<footer class="tpo-footer" id="tpoFooter">
    <div class="tpo-footer-inner">
        <div class="tpo-footer-grid">
            <section>
                <h2>Task Performance Optimizer</h2>
                <p>
                    A web-based academic group contribution system for tracking group membership,
                    task assignment, individual submissions, progress evidence, final reports,
                    lecturer review, and accountability in collaborative coursework.
                </p>
                <span class="tpo-footer-badge">Student contribution tracking and project oversight</span>
            </section>

            <section>
                <h3>Contact</h3>
                <ul>
                    <li>System support: Administrator or assigned lecturer</li>
                    <li>Email: support@task-performance.local</li>
                    <li>Office: Academic projects coordination desk</li>
                    <li>Hours: Monday to Friday, 8:00 AM - 5:00 PM</li>
                </ul>
            </section>

            <section>
                <h3>Policies</h3>
                <ul>
                    <li>Use official names and valid academic email details.</li>
                    <li>Upload only coursework-related files and progress evidence.</li>
                    <li>Do not submit another student's work or identity evidence.</li>
                    <li>Leaders should assign tasks fairly and review updates responsibly.</li>
                    <li>Reports and uploads may be reviewed by lecturers or administrators.</li>
                </ul>
            </section>

            <section>
                <h3>Regulations</h3>
                <ul>
                    <li>Users must follow institutional academic integrity rules.</li>
                    <li>Personal data is used only for authentication, tracking, and assessment support.</li>
                    <li>Uploaded files must not contain malware, illegal content, or private third-party data.</li>
                    <li>Video verification is used only to support task ownership and participation review.</li>
                    <li>Unauthorized access, tampering, or deletion of records is prohibited.</li>
                </ul>
            </section>
        </div>

        <div class="tpo-footer-bottom">
            <span>&copy; <?php echo $footer_year; ?> Task Performance Optimizer. All rights reserved.</span>
            <span>Built for transparent academic collaboration, fair contribution tracking, and responsible project reporting.</span>
        </div>
    </div>
</footer>

<script>
    (function () {
        var footer = document.getElementById('tpoFooter');
        if (!footer) {
            return;
        }

        var target = document.querySelector('.main-content, .app-container .main, .app-container .content, main');
        if (target && !target.contains(footer)) {
            target.appendChild(footer);
        }
    })();
</script>
